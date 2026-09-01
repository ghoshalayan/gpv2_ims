<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PurchaseInvoiceRepository;
use App\Repositories\PurchasePaymentRepository;
use App\Repositories\SupplierAdvanceRepository;
use App\Repositories\SupplierRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * Supplier-level bulk payment allocation. Mirror of CustomerPaymentService::postBulk.
 *
 * Receives a single combined amount (cash + online + from-advance) paid TO one supplier
 * and splits it across multiple unpaid purchase invoices in one DB transaction. Each
 * invoice slice becomes a separate purchase_payments row via PurchasePaymentService::record(),
 * so the per-invoice invariants (account-balance debit, advance-balance lock,
 * paid_amount/due_amount/payment_status recompute) are preserved row-by-row.
 *
 * Cheque is intentionally NOT supported here — same reason as customer side.
 *
 * Allocation per invoice (in order):
 *   1. Drain remaining advance balance up to allocation_amount
 *   2. Drain remaining cash up to remaining
 *   3. Drain remaining online up to remaining
 */
class SupplierPaymentService
{
    public function __construct(
        private SupplierRepository        $suppliers      = new SupplierRepository(),
        private PurchaseInvoiceRepository $invoices       = new PurchaseInvoiceRepository(),
        private PurchasePaymentRepository $payments       = new PurchasePaymentRepository(),
        private SupplierAdvanceRepository $advances       = new SupplierAdvanceRepository(),
        private PurchasePaymentService    $paymentService = new PurchasePaymentService(),
    ) {}

    public function postBulk(array $input, int $userId): array
    {
        $supplierId       = (int)    ($input['supplier_id'] ?? 0);
        $paymentDate      = \trim((string) ($input['payment_date'] ?? \date('Y-m-d')));
        $cashAmount       = \round((float) ($input['cash_amount']   ?? 0), 2);
        $onlineAmount     = \round((float) ($input['online_amount'] ?? 0), 2);
        $onlineReference  = \trim((string) ($input['online_reference'] ?? '')) ?: null;
        $advanceAmount    = \round((float) ($input['from_advance_amount'] ?? 0), 2);
        $advanceId        = ((int) ($input['from_advance_id'] ?? 0)) ?: null;
        $accountKind      = (string) ($input['paid_from_kind'] ?? '');
        $accountId        = (int)    ($input['paid_from_id']   ?? 0);
        $remarks          = \trim((string) ($input['remarks'] ?? '')) ?: null;
        $allocationsRaw   = (array)  ($input['allocations'] ?? []);

        if ($supplierId <= 0) {
            throw new \RuntimeException('Supplier is required.');
        }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            throw new \RuntimeException('Payment date must be YYYY-MM-DD.');
        }
        $supplier = $this->suppliers->find($supplierId);
        if ($supplier === null) {
            throw new \RuntimeException('Supplier not found.');
        }
        if ($cashAmount < 0 || $onlineAmount < 0 || $advanceAmount < 0) {
            throw new \RuntimeException('Amounts cannot be negative.');
        }

        $totalPayment = \round($cashAmount + $onlineAmount + $advanceAmount, 2);
        if ($totalPayment <= 0) {
            throw new \RuntimeException('Enter at least one of cash, online, or apply-from-advance.');
        }

        if (($cashAmount + $onlineAmount) > 0) {
            if (!\in_array($accountKind, ['bank_account', 'cashier_drawer'], true) || $accountId <= 0) {
                throw new \RuntimeException('Pick the bank or drawer that pays the cash/online portion.');
            }
        }
        if ($advanceAmount > 0 && $advanceId === null) {
            throw new \RuntimeException('Pick which supplier advance to draw from.');
        }

        $allocations = [];
        $totalAllocated = 0.0;
        foreach ($allocationsRaw as $invIdRaw => $amtRaw) {
            $invId = (int) $invIdRaw;
            $amt   = \round((float) $amtRaw, 2);
            if ($invId <= 0 || $amt <= 0.001) { continue; }
            $allocations[$invId] = $amt;
            $totalAllocated += $amt;
        }
        $totalAllocated = \round($totalAllocated, 2);

        if ($allocations === []) {
            throw new \RuntimeException('Allocate the payment to at least one invoice.');
        }
        if (\abs($totalPayment - $totalAllocated) > 0.01) {
            throw new \RuntimeException(\sprintf(
                'Allocation total (₹%0.2f) does not match payment total (₹%0.2f). Adjust the per-invoice amounts.',
                $totalAllocated, $totalPayment
            ));
        }

        // Validate + post inside one transaction (consistent snapshot).
        $createdIds = [];
        Db::transaction(function () use (
            &$allocations, $supplierId, $cashAmount, $onlineAmount, $advanceAmount, $advanceId,
            $onlineReference, $accountKind, $accountId, $paymentDate, $remarks, $userId,
            &$createdIds
        ): void {
            $invoiceCache = [];
            foreach ($allocations as $invId => $amt) {
                $inv = $this->invoices->find($invId);
                if ($inv === null) {
                    throw new \RuntimeException("Invoice #{$invId} not found.");
                }
                if ((int) $inv['supplier_id'] !== $supplierId) {
                    throw new \RuntimeException("Invoice {$inv['invoice_number']} belongs to a different supplier.");
                }
                if ($inv['status'] !== 'finalized') {
                    throw new \RuntimeException("Invoice {$inv['invoice_number']} is not finalized.");
                }
                $alreadyPaid = $this->payments->effectivePaidForInvoice($invId);
                $currentDue  = \max(0.0, (float) $inv['grand_total'] - $alreadyPaid);
                if ($amt > $currentDue + 0.001) {
                    throw new \RuntimeException(\sprintf(
                        'Allocation ₹%0.2f exceeds remaining due ₹%0.2f on invoice %s. (May have changed since you opened the form — refresh and try again.)',
                        $amt, $currentDue, $inv['invoice_number']
                    ));
                }
                $invoiceCache[$invId] = $inv;
            }

            // Stable order: oldest first.
            \uksort($allocations, function ($a, $b) use ($invoiceCache): int {
                $da = $invoiceCache[$a]['invoice_date'];
                $db = $invoiceCache[$b]['invoice_date'];
                return $da === $db ? ($a <=> $b) : \strcmp($da, $db);
            });

            $remCash    = $cashAmount;
            $remOnline  = $onlineAmount;
            $remAdvance = $advanceAmount;
            $onlineRefUsed = false;

            foreach ($allocations as $invId => $allocAmt) {
                $remAlloc = $allocAmt;

                $advForThis = \min($remAdvance, $remAlloc);
                $remAdvance -= $advForThis; $remAlloc -= $advForThis;
                $cashForThis = \min($remCash, $remAlloc);
                $remCash -= $cashForThis; $remAlloc -= $cashForThis;
                $onlineForThis = \min($remOnline, $remAlloc);
                $remOnline -= $onlineForThis; $remAlloc -= $onlineForThis;

                $thisOnlineRef = null;
                if ($onlineForThis > 0 && !$onlineRefUsed) {
                    $thisOnlineRef = $onlineReference;
                    $onlineRefUsed = true;
                }

                $rowTotal = \round($advForThis + $cashForThis + $onlineForThis, 2);
                if ($rowTotal <= 0.001) { continue; }

                $rowData = [
                    'payment_date'        => $paymentDate,
                    'cash_amount'         => $cashForThis,
                    'online_amount'       => $onlineForThis,
                    'online_reference'    => $thisOnlineRef,
                    'cheque_amount'       => 0.0,
                    'cheque_number'       => null,
                    'cheque_date'         => null,
                    'cheque_bank_name'    => null,
                    'cheque_status'       => 'pending',
                    'paid_from_kind'      => ($cashForThis + $onlineForThis) > 0 ? $accountKind : null,
                    'paid_from_id'        => ($cashForThis + $onlineForThis) > 0 ? $accountId   : null,
                    'from_advance_amount' => $advForThis,
                    'from_advance_id'     => $advForThis > 0 ? $advanceId : null,
                    'total_amount'        => $rowTotal,
                    'remarks'             => $remarks,
                ];
                $createdIds[] = $this->paymentService->record($invId, $rowData, $userId);
            }
        });

        ActivityLog::log('create', 'supplier_bulk_payment', $supplierId, [
            'label' => 'Supplier #' . $supplierId,
            'notes' => 'invoices=' . \count($createdIds) . ', total=₹' . \number_format($totalPayment, 2)
                     . ' (cash=' . $cashAmount . ', online=' . $onlineAmount . ', advance=' . $advanceAmount . ')',
        ]);

        return [
            $createdIds,
            [
                'cash'          => $cashAmount,
                'online'        => $onlineAmount,
                'advance'       => $advanceAmount,
                'total'         => $totalPayment,
                'applied_count' => \count($createdIds),
            ],
        ];
    }
}
