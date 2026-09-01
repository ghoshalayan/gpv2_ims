<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\SupplierAdvanceRepository;
use App\Repositories\SupplierRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * Supplier-advance lifecycle. Mirror of CustomerAdvanceService with directions inverted.
 *
 * pay()    — debits the paying account (we paid supplier ahead) and opens an advance row.
 * refund() — credits the same account by remaining balance and marks 'refunded'
 *            (the supplier returned the unused prepayment to us).
 *
 * Application against a purchase invoice is handled by PurchasePaymentService at payment
 * time — staff pass `from_advance_amount` + `from_advance_id` and that service decrements
 * the advance balance atomically.
 */
class SupplierAdvanceService
{
    public function __construct(
        private SupplierAdvanceRepository $repo      = new SupplierAdvanceRepository(),
        private SupplierRepository        $suppliers = new SupplierRepository(),
        private BankAccountRepository     $banks     = new BankAccountRepository(),
        private CashierDrawerRepository   $drawers   = new CashierDrawerRepository(),
        private BusinessProfileRepository $business  = new BusinessProfileRepository(),
    ) {}

    public function pay(array $input, int $userId): int
    {
        $supplierId        = (int)    ($input['supplier_id'] ?? 0);
        $amount            = (float)  ($input['amount']      ?? 0);
        $advanceDate       = \trim((string) ($input['advance_date'] ?? \date('Y-m-d')));
        $accountKind       = (string) ($input['paid_from_kind'] ?? '');
        $accountId         = (int)    ($input['paid_from_id']   ?? 0);
        $paymentMode       = (string) ($input['payment_mode']   ?? 'cash');
        $paymentReference  = \trim((string) ($input['payment_reference'] ?? '')) ?: null;
        $remarks           = \trim((string) ($input['remarks'] ?? '')) ?: null;

        if ($amount <= 0) {
            throw new \RuntimeException('Amount must be greater than zero.');
        }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $advanceDate)) {
            throw new \RuntimeException('Advance date must be YYYY-MM-DD.');
        }
        if (!\in_array($accountKind, ['bank_account', 'cashier_drawer'], true)) {
            throw new \RuntimeException('Pick a paying account.');
        }
        if (!\in_array($paymentMode, ['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other'], true)) {
            throw new \RuntimeException('Invalid payment mode.');
        }
        $supplier = $this->suppliers->find($supplierId);
        if ($supplier === null || $supplier['status'] !== 'active') {
            throw new \RuntimeException('Pick an active supplier.');
        }
        $accountRow = $this->resolveAccount($accountKind, $accountId);
        if ($accountRow === null) {
            throw new \RuntimeException('Paying account not found.');
        }
        if ($accountRow['status'] !== 'active') {
            throw new \RuntimeException("Paying account '{$accountRow['name']}' is inactive.");
        }

        $newId = 0;
        Db::transaction(function () use (
            $advanceDate, $supplierId, $amount, $accountKind, $accountId,
            $paymentMode, $paymentReference, $remarks, $userId, &$newId
        ): void {
            $fy   = $this->fiscalYearLabel($advanceDate);
            $next = $this->repo->maxSequenceForFy("GP-SADV/{$fy}") + 1;
            $advanceNumber = \sprintf('GP-SADV/%s/%05d', $fy, $next);

            $newId = $this->repo->insert([
                'advance_number'    => $advanceNumber,
                'advance_date'      => $advanceDate,
                'supplier_id'       => $supplierId,
                'amount'            => $amount,
                'paid_from_kind'    => $accountKind,
                'paid_from_id'      => $accountId,
                'payment_mode'      => $paymentMode,
                'payment_reference' => $paymentReference,
                'remarks'           => $remarks,
            ], $userId);

            // Debit the paying account (we paid OUT to supplier).
            $this->applyAccountDelta($accountKind, $accountId, -$amount);
            ActivityLog::log('create', 'supplier_advance', $newId, [
                'label' => $advanceNumber,
                'notes' => 'amount=' . $amount . ', mode=' . $paymentMode,
            ]);
        });
        return $newId;
    }

    /**
     * Refund: supplier returns the unused balance to us. Credit the original paying account
     * by the remaining balance and mark 'refunded'. Already-applied portions stay locked.
     */
    public function refund(int $advanceId, string $reason, int $userId): void
    {
        Db::transaction(function () use ($advanceId, $reason, $userId): void {
            $row = $this->repo->findForUpdate($advanceId);
            if ($row === null) {
                throw new \RuntimeException("Supplier advance #{$advanceId} not found.");
            }
            if ($row['status'] === 'refunded') {
                throw new \RuntimeException('Supplier advance is already refunded.');
            }
            // Rebate-linked non-cash advances cannot be refunded through this flow —
            // they have no originating cash leg to credit back. To reverse, void the
            // parent brand-rebate row instead.
            if ($row['paid_from_kind'] === null || $row['paid_from_id'] === null) {
                throw new \RuntimeException(
                    'This advance was created from a brand rebate. Void the rebate to reverse it.'
                );
            }
            $remaining = (float) $row['balance'];
            if ($remaining <= 0.001) {
                $this->repo->markRefunded($advanceId, $reason, $userId);
                return;
            }
            // Credit the originally-paying account (money came back to us).
            $this->applyAccountDelta(
                (string) $row['paid_from_kind'],
                (int) $row['paid_from_id'],
                $remaining
            );
            $this->repo->markRefunded($advanceId, $reason, $userId);
            ActivityLog::log('refund', 'supplier_advance', $advanceId, [
                'label' => $row['advance_number'] ?? null,
                'notes' => 'refunded ₹' . \round($remaining, 2) . ', reason=' . $reason,
            ]);
        });
    }

    private function resolveAccount(string $kind, int $id): ?array
    {
        return $kind === 'bank_account' ? $this->banks->find($id) : $this->drawers->find($id);
    }

    private function applyAccountDelta(string $kind, int $id, float $delta): void
    {
        if ($kind === 'bank_account') {
            $this->banks->applyDelta($id, $delta);
        } else {
            $this->drawers->applyDelta($id, $delta);
        }
    }

    private function fiscalYearLabel(string $dateStr): string
    {
        $bp = $this->business->get();
        $fyStartMonth = (int) ($bp['financial_year_start_month'] ?? 4);
        $ts    = \strtotime($dateStr) ?: \time();
        $year  = (int) \date('Y', $ts);
        $month = (int) \date('n', $ts);
        if ($month >= $fyStartMonth) {
            return \sprintf('%02d-%02d', $year % 100, ($year + 1) % 100);
        }
        return \sprintf('%02d-%02d', ($year - 1) % 100, $year % 100);
    }
}
