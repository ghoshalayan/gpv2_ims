<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\CustomerAdvanceRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\SalesInvoiceRepository;
use App\Repositories\SalesPaymentRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * Customer-level bulk payment allocation.
 *
 * Receives a single combined amount (cash + online + from-advance) from one customer and
 * splits it across multiple unpaid invoices in one DB transaction. Each invoice slice
 * becomes a separate sales_payments row via SalesPaymentService::record(), so all the
 * existing per-invoice invariants (account-balance update, advance-balance lock,
 * paid_amount/due_amount/payment_status recompute) are preserved row-by-row.
 *
 * Cheque is intentionally NOT supported here — it carries unique cheque_number/bank/date
 * fields that can't sensibly split across rows. A cheque payment goes through the per-
 * invoice page.
 *
 * Allocation strategy per invoice (in order):
 *   1. Drain remaining advance balance up to allocation_amount
 *   2. Drain remaining cash up to remaining
 *   3. Drain remaining online up to remaining
 *
 * The first invoice that consumes any online amount gets the online_reference; subsequent
 * invoices get blank. Rows with allocation = 0 are skipped (no empty payment row written).
 */
class CustomerPaymentService
{
    public function __construct(
        private CustomerRepository        $customers      = new CustomerRepository(),
        private SalesInvoiceRepository    $invoices       = new SalesInvoiceRepository(),
        private SalesPaymentRepository    $payments       = new SalesPaymentRepository(),
        private CustomerAdvanceRepository $advances       = new CustomerAdvanceRepository(),
        private SalesPaymentService       $paymentService = new SalesPaymentService(),
    ) {}

    /**
     * @param array $input expected keys:
     *   customer_id, payment_date,
     *   cash_amount, online_amount, online_reference,
     *   from_advance_amount, from_advance_id,
     *   received_into_kind, received_into_id,
     *   allocations => [invoice_id => apply_amount]   (sum must equal cash+online+advance)
     *   remarks
     * @return array{0:int[], 1:array{cash:float, online:float, advance:float, total:float, applied_count:int}}
     */
    public function postBulk(array $input, int $userId): array
    {
        $customerId       = (int)    ($input['customer_id'] ?? 0);
        $paymentDate      = \trim((string) ($input['payment_date'] ?? \date('Y-m-d')));
        $cashAmount       = \round((float) ($input['cash_amount']   ?? 0), 2);
        $onlineAmount     = \round((float) ($input['online_amount'] ?? 0), 2);
        $onlineReference  = \trim((string) ($input['online_reference'] ?? '')) ?: null;
        $advanceAmount    = \round((float) ($input['from_advance_amount'] ?? 0), 2);
        $advanceId        = ((int) ($input['from_advance_id'] ?? 0)) ?: null;
        $accountKind      = (string) ($input['received_into_kind'] ?? '');
        $accountId        = (int)    ($input['received_into_id']   ?? 0);
        $remarks          = \trim((string) ($input['remarks'] ?? '')) ?: null;
        $allocationsRaw   = (array)  ($input['allocations'] ?? []);

        if ($customerId <= 0) {
            throw new \RuntimeException('Customer is required.');
        }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            throw new \RuntimeException('Payment date must be YYYY-MM-DD.');
        }
        $customer = $this->customers->find($customerId);
        if ($customer === null) {
            throw new \RuntimeException('Customer not found.');
        }
        if ($cashAmount < 0 || $onlineAmount < 0 || $advanceAmount < 0) {
            throw new \RuntimeException('Amounts cannot be negative.');
        }

        $totalPayment = \round($cashAmount + $onlineAmount + $advanceAmount, 2);
        if ($totalPayment <= 0) {
            throw new \RuntimeException('Enter at least one of cash, online, or apply-from-advance.');
        }

        // Account is required only when there's a cash/online component.
        if (($cashAmount + $onlineAmount) > 0) {
            if (!\in_array($accountKind, ['bank_account', 'cashier_drawer'], true) || $accountId <= 0) {
                throw new \RuntimeException('Pick the bank or drawer that receives the cash/online portion.');
            }
        }
        if ($advanceAmount > 0 && $advanceId === null) {
            throw new \RuntimeException('Pick which advance to draw from.');
        }

        // Normalize + filter allocations.
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

        // ============= POST =============
        // All cross-validation reads + posts run inside a single transaction so the snapshot
        // is consistent. If a concurrent payment posts between bulk validation and the bulk
        // post, the per-row record() inside the loop catches it and rolls back the whole
        // bulk cleanly.
        $createdIds = [];
        Db::transaction(function () use (
            &$allocations, $allocationsRaw, $customerId, $cashAmount, $onlineAmount, $advanceAmount, $advanceId,
            $onlineReference, $accountKind, $accountId, $paymentDate, $remarks, $userId,
            &$createdIds
        ): void {
            // Re-validate each allocation against current DB state (inside the txn).
            $invoiceCache = [];
            foreach ($allocations as $invId => $amt) {
                $inv = $this->invoices->find($invId);
                if ($inv === null) {
                    throw new \RuntimeException("Invoice #{$invId} not found.");
                }
                if ((int) $inv['customer_id'] !== $customerId) {
                    throw new \RuntimeException("Invoice {$inv['invoice_number']} belongs to a different customer.");
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

            // Stable order: oldest invoice_date first (matches the auto-fill order so user
            // doesn't get surprises if they edited).
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

                // 1) drain advance
                $advForThis = \min($remAdvance, $remAlloc);
                $remAdvance -= $advForThis; $remAlloc -= $advForThis;
                // 2) drain cash
                $cashForThis = \min($remCash, $remAlloc);
                $remCash -= $cashForThis; $remAlloc -= $cashForThis;
                // 3) drain online
                $onlineForThis = \min($remOnline, $remAlloc);
                $remOnline -= $onlineForThis; $remAlloc -= $onlineForThis;

                $thisOnlineRef = null;
                if ($onlineForThis > 0 && !$onlineRefUsed) {
                    $thisOnlineRef = $onlineReference;
                    $onlineRefUsed = true;
                }

                $rowTotal = \round($advForThis + $cashForThis + $onlineForThis, 2);
                if ($rowTotal <= 0.001) { continue; }   // defensive

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
                    'received_into_kind'  => ($cashForThis + $onlineForThis) > 0 ? $accountKind : null,
                    'received_into_id'    => ($cashForThis + $onlineForThis) > 0 ? $accountId   : null,
                    'from_advance_amount' => $advForThis,
                    'from_advance_id'     => $advForThis > 0 ? $advanceId : null,
                    'total_amount'        => $rowTotal,
                    'remarks'             => $remarks,
                ];
                $createdIds[] = $this->paymentService->record($invId, $rowData, $userId);
            }
        });

        ActivityLog::log('create', 'customer_bulk_payment', $customerId, [
            'label' => 'Customer #' . $customerId,
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
