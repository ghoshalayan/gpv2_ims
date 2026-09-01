<?php
declare(strict_types=1);

/**
 * One-time backfill: recompute every finalized invoice's paid_amount / due_amount /
 * payment_status using the RETURN-AWARE formula
 *
 *     due = max(0, grand_total − effective_paid − Σ finalized return grand_totals)
 *
 * Run once after deploying the return-aware recompute change so any invoice whose due
 * had drifted (a payment recorded/deleted after a return, under the old return-unaware
 * recompute) is healed and the "due_amount vs returns" integrity check reads clean.
 *
 * Idempotent and safe to re-run: it only writes the canonical values.
 *
 *   php database/backfill_invoice_due.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Db;

$pdo = Db::pdo();

$configs = [
    'Sales' => [
        'inv'      => 'sales_invoices',
        'pay'      => 'sales_payments',
        'pay_fk'   => 'sales_invoice_id',
        'ret'      => 'sales_returns',
        'ret_fk'   => 'original_sales_invoice_id',
    ],
    'Purchase' => [
        'inv'      => 'purchase_invoices',
        'pay'      => 'purchase_payments',
        'pay_fk'   => 'purchase_invoice_id',
        'ret'      => 'purchase_returns',
        'ret_fk'   => 'original_purchase_invoice_id',
    ],
];

$grand = 0;
foreach ($configs as $label => $c) {
    $rows = $pdo->query("SELECT id, grand_total FROM {$c['inv']} WHERE status = 'finalized'")
                ->fetchAll(PDO::FETCH_ASSOC);

    $paidStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(cash_amount + online_amount + from_advance_amount +
                 CASE WHEN cheque_status IN ('pending','cleared') THEN cheque_amount ELSE 0 END), 0)
         FROM {$c['pay']} WHERE {$c['pay_fk']} = ?"
    );
    $retStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(grand_total), 0) FROM {$c['ret']}
         WHERE {$c['ret_fk']} = ? AND status = 'finalized'"
    );
    $updStmt = $pdo->prepare(
        "UPDATE {$c['inv']} SET paid_amount = ?, due_amount = ?, payment_status = ? WHERE id = ?"
    );

    $changed = 0;
    foreach ($rows as $r) {
        $id    = (int) $r['id'];
        $grandTotal = (float) $r['grand_total'];

        $paidStmt->execute([$id]);
        $paid = (float) $paidStmt->fetchColumn();
        $retStmt->execute([$id]);
        $returned = (float) $retStmt->fetchColumn();

        $due = round($grandTotal - $paid - $returned, 2);
        if ($due < 0.01 && $due > -0.01) { $due = 0.0; }
        $due = max(0.0, $due);

        $status = 'unpaid';
        if ($paid > 0 && $due > 0) { $status = 'partial'; }
        elseif ($due <= 0.0)       { $status = 'paid'; }

        $updStmt->execute([round($paid, 2), $due, $status, $id]);
        if ($updStmt->rowCount() > 0) { $changed++; }
    }
    echo "{$label}: recomputed " . count($rows) . " finalized invoices ({$changed} rows written).\n";
    $grand += count($rows);
}

echo "Done. {$grand} invoices processed.\n";
