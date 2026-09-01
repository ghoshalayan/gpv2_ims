<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class BrandRebateRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(?int $brandId = null, ?int $supplierId = null, ?string $modeFilter = null, ?string $statusFilter = null): array
    {
        $sql = "SELECT br.*,
                       b.name AS brand_name,
                       s.name AS supplier_name,
                       p.name AS free_product_name,
                       CASE br.received_into_kind
                           WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = br.received_into_id)
                           WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = br.received_into_id)
                       END AS received_into_name,
                       pi.invoice_number AS target_invoice_number,
                       sa.advance_number AS linked_advance_number
                FROM brand_rebates br
                JOIN brands    b  ON b.id  = br.brand_id
                JOIN suppliers s  ON s.id  = br.supplier_id
                LEFT JOIN products           p  ON p.id  = br.free_product_id
                LEFT JOIN purchase_invoices  pi ON pi.id = br.target_invoice_id
                LEFT JOIN supplier_advances  sa ON sa.id = br.linked_advance_id";
        $params = [];
        $conds  = [];
        if ($brandId !== null)    { $conds[] = 'br.brand_id = ?';    $params[] = $brandId; }
        if ($supplierId !== null) { $conds[] = 'br.supplier_id = ?'; $params[] = $supplierId; }
        if ($modeFilter !== null   && $modeFilter !== '')   { $conds[] = 'br.mode = ?';   $params[] = $modeFilter; }
        if ($statusFilter !== null && $statusFilter !== '') { $conds[] = 'br.status = ?'; $params[] = $statusFilter; }
        if ($conds !== []) { $sql .= ' WHERE ' . \implode(' AND ', $conds); }
        $sql .= ' ORDER BY br.rebate_date DESC, br.id DESC';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['brand_id']))    { $where[] = 'br.brand_id = ?';    $params[] = (int) $filters['brand_id']; }
        if (!empty($filters['supplier_id'])) { $where[] = 'br.supplier_id = ?'; $params[] = (int) $filters['supplier_id']; }
        if (!empty($filters['mode']))        { $where[] = 'br.mode = ?';        $params[] = (string) $filters['mode']; }
        if (!empty($filters['status']))      { $where[] = 'br.status = ?';      $params[] = (string) $filters['status']; }
        if ($search !== '') {
            $where[]  = '(br.rebate_number LIKE ? OR b.name LIKE ? OR s.name LIKE ? OR br.credit_note_number LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM brand_rebates br
             JOIN brands b ON b.id = br.brand_id
             JOIN suppliers s ON s.id = br.supplier_id
             {$whereSql}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT br.*,
                    b.name AS brand_name,
                    s.name AS supplier_name,
                    p.name AS free_product_name,
                    CASE br.received_into_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = br.received_into_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = br.received_into_id)
                    END AS received_into_name,
                    pi.invoice_number AS target_invoice_number,
                    sa.advance_number AS linked_advance_number
             FROM brand_rebates br
             JOIN brands    b  ON b.id  = br.brand_id
             JOIN suppliers s  ON s.id  = br.supplier_id
             LEFT JOIN products           p  ON p.id  = br.free_product_id
             LEFT JOIN purchase_invoices  pi ON pi.id = br.target_invoice_id
             LEFT JOIN supplier_advances  sa ON sa.id = br.linked_advance_id
             {$whereSql}
             ORDER BY br.rebate_date DESC, br.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT br.*,
                    b.name AS brand_name,
                    s.name AS supplier_name,
                    p.name AS free_product_name,
                    CASE br.received_into_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = br.received_into_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = br.received_into_id)
                    END AS received_into_name,
                    pi.invoice_number AS target_invoice_number,
                    sa.advance_number AS linked_advance_number,
                    sa.balance        AS linked_advance_balance,
                    sa.status         AS linked_advance_status
             FROM brand_rebates br
             JOIN brands    b  ON b.id  = br.brand_id
             JOIN suppliers s  ON s.id  = br.supplier_id
             LEFT JOIN products           p  ON p.id  = br.free_product_id
             LEFT JOIN purchase_invoices  pi ON pi.id = br.target_invoice_id
             LEFT JOIN supplier_advances  sa ON sa.id = br.linked_advance_id
             WHERE br.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insert(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO brand_rebates
             (rebate_number, rebate_date, brand_id, supplier_id, mode, amount,
              credit_note_number, credit_note_date,
              received_into_kind, received_into_id, payment_mode, payment_reference,
              linked_advance_id,
              target_invoice_id, income_spillover,
              free_product_id, free_quantity, free_value_per_unit, free_stock_avg_before,
              remarks, created_by)
             VALUES (?, ?, ?, ?, ?, ?,
                     ?, ?,
                     ?, ?, ?, ?,
                     ?,
                     ?, ?,
                     ?, ?, ?, ?,
                     ?, ?)'
        );
        $stmt->execute([
            $data['rebate_number'], $data['rebate_date'], $data['brand_id'], $data['supplier_id'], $data['mode'], $data['amount'],
            $data['credit_note_number'], $data['credit_note_date'],
            $data['received_into_kind'] ?? null, $data['received_into_id'] ?? null,
            $data['payment_mode']       ?? null, $data['payment_reference']  ?? null,
            $data['linked_advance_id']  ?? null,
            $data['target_invoice_id']  ?? null, $data['income_spillover'] ?? 0.00,
            $data['free_product_id']    ?? null, $data['free_quantity'] ?? null,
            $data['free_value_per_unit'] ?? null, $data['free_stock_avg_before'] ?? null,
            $data['remarks']            ?? null,
            $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    /** Records the moving-avg AFTER applyStockIn so void can detect intervening drift. */
    public function setFreeStockAvgAfter(int $rebateId, float $avgAfter): void
    {
        $stmt = Db::pdo()->prepare('UPDATE brand_rebates SET free_stock_avg_after = ? WHERE id = ?');
        $stmt->execute([$avgAfter, $rebateId]);
    }

    public function attachLinkedAdvance(int $rebateId, int $advanceId): void
    {
        $stmt = Db::pdo()->prepare('UPDATE brand_rebates SET linked_advance_id = ? WHERE id = ?');
        $stmt->execute([$advanceId, $rebateId]);
    }

    public function markVoided(int $id, string $reason, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE brand_rebates
             SET status = 'voided', voided_at = NOW(), voided_by = ?, void_reason = ?
             WHERE id = ? AND status = 'posted'"
        );
        $stmt->execute([$userId, $reason, $id]);
    }

    public function maxSequenceForFy(string $fyPrefix): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(rebate_number, "/", -1) AS UNSIGNED)), 0)
             FROM brand_rebates
             WHERE rebate_number LIKE ?'
        );
        $stmt->execute([$fyPrefix . '/%']);
        return (int) $stmt->fetchColumn();
    }

    /** Per-product cost-adjustment trace (for cost_reduction rebates). */
    public function insertCostAdjustment(array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO brand_rebate_cost_adjustments
             (rebate_id, product_id, on_hand_qty_at_post, moving_avg_before,
              amount_applied, moving_avg_delta, moving_avg_after)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['rebate_id'], $data['product_id'], $data['on_hand_qty_at_post'], $data['moving_avg_before'],
            $data['amount_applied'], $data['moving_avg_delta'], $data['moving_avg_after'],
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function costAdjustmentsForRebate(int $rebateId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT rca.*, p.name AS product_name
             FROM brand_rebate_cost_adjustments rca
             JOIN products p ON p.id = rca.product_id
             WHERE rca.rebate_id = ?
             ORDER BY rca.id ASC"
        );
        $stmt->execute([$rebateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
