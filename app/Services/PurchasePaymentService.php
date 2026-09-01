<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BankAccountRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\PurchaseInvoiceRepository;
use App\Repositories\PurchasePaymentRepository;
use App\Repositories\SupplierAdvanceRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * Orchestrates purchase-payment writes. Every mutation:
 *   - Debits the linked paying bank/drawer by the cash-effective amount.
 *   - If a supplier advance was applied (from_advance_amount > 0), decrements that
 *     advance's balance atomically (delete reverses it). Advance application does NOT
 *     touch any bank/drawer — it's a paper credit drawdown, not cash movement.
 *   - Recomputes the parent purchase_invoices paid_amount / due_amount / payment_status.
 *
 * cash_effective = cash + online + (cheque if status IN ('pending', 'cleared'))
 * total_paid     = cash_effective + from_advance_amount   (used by effectivePaidForInvoice)
 *
 * Pre-existing rows (paid_from_kind/id NULL) are treated as legacy un-linked.
 */
class PurchasePaymentService
{
    public function __construct(
        private PurchasePaymentRepository $payments  = new PurchasePaymentRepository(),
        private PurchaseInvoiceRepository $invoices  = new PurchaseInvoiceRepository(),
        private BankAccountRepository     $banks     = new BankAccountRepository(),
        private CashierDrawerRepository   $drawers   = new CashierDrawerRepository(),
        private SupplierAdvanceRepository $advances  = new SupplierAdvanceRepository(),
    ) {}

    public function record(int $invoiceId, array $data, int $userId): int
    {
        return Db::transaction(function () use ($invoiceId, $data, $userId): int {
            // Row-lock the invoice for the duration of the txn — prevents the over-pay
            // validation from racing against a concurrent payment on the same bill.
            $invoice = $this->lockInvoiceOrThrow($invoiceId);
            $alreadyPaid = $this->payments->effectivePaidForInvoice($invoiceId);
            $currentDue  = \max(0, (float) $invoice['grand_total'] - $alreadyPaid);

            if ((float) $data['total_amount'] > $currentDue + 0.001) {
                throw new \RuntimeException(\sprintf(
                    'Payment total ₹%0.2f exceeds current due ₹%0.2f.',
                    $data['total_amount'], $currentDue
                ));
            }

            $kind  = $data['paid_from_kind'] ?? null;
            $accId = (int) ($data['paid_from_id'] ?? 0);
            if ($kind !== null) {
                $acc = $this->resolveAccount($kind, $accId);
                if ($acc === null) {
                    throw new \RuntimeException('Paying account not found.');
                }
                if ($acc['status'] !== 'active') {
                    throw new \RuntimeException("Paying account '{$acc['name']}' is inactive.");
                }
            }

            // ---- Supplier-advance application (paper credit drawdown, no cash movement) ----
            $fromAdvanceAmount = (float) ($data['from_advance_amount'] ?? 0);
            $fromAdvanceId     = ((int) ($data['from_advance_id'] ?? 0)) ?: null;
            if ($fromAdvanceAmount > 0.001) {
                if ($fromAdvanceId === null) {
                    throw new \RuntimeException('Pick which supplier advance to draw from.');
                }
                $advance = $this->advances->findForUpdate($fromAdvanceId);
                if ($advance === null) {
                    throw new \RuntimeException('Supplier advance not found.');
                }
                if ((int) $advance['supplier_id'] !== (int) $invoice['supplier_id']) {
                    throw new \RuntimeException("Advance #{$fromAdvanceId} belongs to a different supplier.");
                }
                if ($advance['status'] !== 'active') {
                    throw new \RuntimeException("Advance #{$fromAdvanceId} is {$advance['status']} — no balance to apply.");
                }
                if ((float) $advance['balance'] < $fromAdvanceAmount - 0.001) {
                    throw new \RuntimeException(\sprintf(
                        'Advance balance ₹%0.2f is less than apply amount ₹%0.2f.',
                        $advance['balance'], $fromAdvanceAmount
                    ));
                }
                $this->advances->applyDelta($fromAdvanceId, -$fromAdvanceAmount);
            } elseif ($fromAdvanceId !== null) {
                $fromAdvanceId = null;
            }

            $data['purchase_invoice_id']  = $invoiceId;
            $data['from_advance_amount']  = $fromAdvanceAmount;
            $data['from_advance_id']      = $fromAdvanceId;
            $paymentId = $this->payments->insert($data, $userId);

            // Debit the paying account by the cash-effective amount only
            // (advance application is intentionally excluded — no cash moved).
            if ($kind !== null) {
                $cashEffective = $this->effectiveAmountFromData($data);
                if ($cashEffective > 0) {
                    $this->applyDelta($kind, $accId, -$cashEffective);
                }
            }

            $this->recomputeInvoice($invoiceId);
            ActivityLog::log('create', 'purchase_payment', $paymentId, [
                'label' => 'PAY-' . $paymentId,
                'notes' => 'invoice_id=' . $invoiceId . ', total=' . ($data['total_amount'] ?? '?'),
            ]);
            return $paymentId;
        });
    }

    public function delete(int $paymentId, int $userId): void
    {
        Db::transaction(function () use ($paymentId, $userId): void {
            $payment = $this->payments->find($paymentId);
            if ($payment === null) {
                throw new \RuntimeException('Payment not found.');
            }
            $invoiceId = (int) $payment['purchase_invoice_id'];
            // Lock the invoice for the txn — prevents racing recomputes if another
            // payment is being recorded against the same bill simultaneously.
            $this->lockInvoiceOrThrow($invoiceId);

            // Pre-flight: refunded supplier advance can't be unrefunded by deleting a payment.
            $fromAdvAmt = (float) ($payment['from_advance_amount'] ?? 0);
            $fromAdvId  = $payment['from_advance_id'] !== null ? (int) $payment['from_advance_id'] : null;
            if ($fromAdvAmt > 0.001 && $fromAdvId !== null) {
                $advance = $this->advances->find($fromAdvId);
                if ($advance !== null && $advance['status'] === 'refunded') {
                    throw new \RuntimeException(
                        "Cannot delete this payment: it used ₹" . \number_format($fromAdvAmt, 2) .
                        " from supplier advance {$advance['advance_number']}, which has since been refunded. " .
                        'To resolve: post a new manual supplier advance and redo the payment, or contact the owner.'
                    );
                }
            }

            // 1) Reverse cash-portion debit on the linked account.
            if ($payment['paid_from_kind'] !== null) {
                $cashEffective = $this->effectiveAmountFromRow($payment);
                if ($cashEffective > 0) {
                    $this->applyDelta(
                        (string) $payment['paid_from_kind'],
                        (int) $payment['paid_from_id'],
                        $cashEffective       // positive = credit back to undo the debit
                    );
                }
            }
            // 2) Restore the supplier-advance balance if any was applied.
            if ($fromAdvAmt > 0.001 && $fromAdvId !== null) {
                $this->advances->applyDelta($fromAdvId, $fromAdvAmt);
            }
            $this->payments->delete($paymentId);
            $this->recomputeInvoice($invoiceId);
            ActivityLog::log('delete', 'purchase_payment', $paymentId, [
                'label' => 'PAY-' . $paymentId,
                'notes' => 'invoice_id=' . $invoiceId . ', total=' . ($payment['total_amount'] ?? '?'),
            ]);
        });
    }

    public function updateChequeStatus(int $paymentId, string $newStatus, int $userId): void
    {
        if (!\in_array($newStatus, ['pending', 'cleared', 'bounced', 'cancelled'], true)) {
            throw new \RuntimeException('Invalid cheque status.');
        }
        Db::transaction(function () use ($paymentId, $newStatus, $userId): void {
            $payment = $this->payments->find($paymentId);
            if ($payment === null) {
                throw new \RuntimeException('Payment not found.');
            }
            if ((float) $payment['cheque_amount'] <= 0) {
                throw new \RuntimeException('This payment has no cheque component.');
            }
            // Lock the parent invoice for the duration of this txn (cheque-flip mutates
            // invoice paid_amount via recomputeInvoice).
            $this->lockInvoiceOrThrow((int) $payment['purchase_invoice_id']);

            // Apply cheque-portion delta when status flips between counted/not-counted.
            if ($payment['paid_from_kind'] !== null) {
                $oldCounts = \in_array($payment['cheque_status'], ['pending', 'cleared'], true);
                $newCounts = \in_array($newStatus, ['pending', 'cleared'], true);
                if ($oldCounts !== $newCounts) {
                    // Newly counted = debit account further; un-counted = credit account back.
                    $delta = $newCounts ? -(float) $payment['cheque_amount'] : (float) $payment['cheque_amount'];
                    $this->applyDelta(
                        (string) $payment['paid_from_kind'],
                        (int) $payment['paid_from_id'],
                        $delta
                    );
                }
            }

            $this->payments->updateChequeStatus($paymentId, $newStatus, $userId);
            $this->recomputeInvoice((int) $payment['purchase_invoice_id']);
            ActivityLog::log('update', 'purchase_payment', $paymentId, [
                'label' => 'PAY-' . $paymentId,
                'notes' => 'cheque_status: ' . $payment['cheque_status'] . ' → ' . $newStatus,
            ]);
        });
    }

    private function effectiveAmountFromData(array $data): float
    {
        $cheque = ($data['cheque_status'] ?? 'pending') === 'pending' || ($data['cheque_status'] ?? '') === 'cleared'
            ? (float) ($data['cheque_amount'] ?? 0)
            : 0.0;
        return (float) ($data['cash_amount'] ?? 0)
             + (float) ($data['online_amount'] ?? 0)
             + $cheque;
    }

    private function effectiveAmountFromRow(array $row): float
    {
        $cheque = \in_array($row['cheque_status'], ['pending', 'cleared'], true)
            ? (float) $row['cheque_amount']
            : 0.0;
        return (float) $row['cash_amount']
             + (float) $row['online_amount']
             + $cheque;
    }

    private function resolveAccount(string $kind, int $id): ?array
    {
        return $kind === 'bank_account'
            ? $this->banks->find($id)
            : $this->drawers->find($id);
    }

    private function applyDelta(string $kind, int $id, float $delta): void
    {
        if ($kind === 'bank_account') {
            $this->banks->applyDelta($id, $delta);
        } else {
            $this->drawers->applyDelta($id, $delta);
        }
    }

    /** Recompute invoice totals from its payments. Caller must be inside a transaction. */
    private function recomputeInvoice(int $invoiceId): void
    {
        $invoice = $this->loadInvoiceOrThrow($invoiceId);
        $grandTotal = (float) $invoice['grand_total'];
        $paid = $this->payments->effectivePaidForInvoice($invoiceId);
        // Net of finalized returns so due is always grand_total − paid − returns. Without this,
        // a payment recorded/deleted after a return would silently discard the return's reduction.
        $returned = $this->finalizedReturnTotal($invoiceId);
        $due  = \round($grandTotal - $paid - $returned, 2);

        if ($due < 0.01 && $due > -0.01) { $due = 0.0; }

        $status = 'unpaid';
        if ($paid > 0 && $due > 0) { $status = 'partial'; }
        elseif ($due <= 0.0)       { $status = 'paid'; }

        $stmt = Db::pdo()->prepare(
            'UPDATE purchase_invoices SET paid_amount = ?, due_amount = ?, payment_status = ? WHERE id = ?'
        );
        $stmt->execute([\round($paid, 2), \max(0.0, $due), $status, $invoiceId]);
    }

    /** Sum of finalized purchase-return grand totals against this invoice (reduces due). */
    private function finalizedReturnTotal(int $invoiceId): float
    {
        $stmt = Db::pdo()->prepare(
            "SELECT COALESCE(SUM(grand_total), 0) FROM purchase_returns
             WHERE original_purchase_invoice_id = ? AND status = 'finalized'"
        );
        $stmt->execute([$invoiceId]);
        return (float) $stmt->fetchColumn();
    }

    private function loadInvoiceOrThrow(int $invoiceId): array
    {
        $invoice = $this->invoices->find($invoiceId);
        if ($invoice === null) {
            throw new \RuntimeException("Invoice #{$invoiceId} not found.");
        }
        if ($invoice['status'] !== 'finalized') {
            throw new \RuntimeException('Payments can only be recorded against finalized invoices.');
        }
        return $invoice;
    }

    /**
     * Lock the purchase_invoice row inside the current transaction so concurrent
     * payment posts on the same bill serialise their over-pay validation. Caller MUST
     * be inside Db::transaction.
     */
    private function lockInvoiceOrThrow(int $invoiceId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT * FROM purchase_invoices WHERE id = ? FOR UPDATE"
        );
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($invoice === false) {
            throw new \RuntimeException("Invoice #{$invoiceId} not found.");
        }
        if ($invoice['status'] !== 'finalized') {
            throw new \RuntimeException('Payments can only be recorded against finalized invoices.');
        }
        return $invoice;
    }
}
