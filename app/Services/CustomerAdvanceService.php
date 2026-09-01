<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\CustomerAdvanceRepository;
use App\Repositories\CustomerRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * Customer-advance lifecycle, with strict balance integrity.
 *
 * receive() — credits the receiving account and opens an advance row.
 * refund()  — debits the same account by remaining balance and marks refunded.
 *
 * Application against an invoice is handled by SalesPaymentService at payment time —
 * staff pass `from_advance_amount` + `from_advance_id` and that service decrements
 * the advance balance atomically.
 */
class CustomerAdvanceService
{
    public function __construct(
        private CustomerAdvanceRepository $repo      = new CustomerAdvanceRepository(),
        private CustomerRepository        $customers = new CustomerRepository(),
        private BankAccountRepository     $banks     = new BankAccountRepository(),
        private CashierDrawerRepository   $drawers   = new CashierDrawerRepository(),
        private BusinessProfileRepository $business  = new BusinessProfileRepository(),
    ) {}

    public function receive(array $input, int $userId): int
    {
        $customerId        = (int)    ($input['customer_id'] ?? 0);
        $amount            = (float)  ($input['amount']      ?? 0);
        $advanceDate       = \trim((string) ($input['advance_date'] ?? \date('Y-m-d')));
        $accountKind       = (string) ($input['received_into_kind'] ?? '');
        $accountId         = (int)    ($input['received_into_id']   ?? 0);
        $paymentMode       = (string) ($input['payment_mode']       ?? 'cash');
        $paymentReference  = \trim((string) ($input['payment_reference'] ?? '')) ?: null;
        $remarks           = \trim((string) ($input['remarks'] ?? '')) ?: null;

        if ($amount <= 0) {
            throw new \RuntimeException('Amount must be greater than zero.');
        }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $advanceDate)) {
            throw new \RuntimeException('Advance date must be YYYY-MM-DD.');
        }
        if (!\in_array($accountKind, ['bank_account', 'cashier_drawer'], true)) {
            throw new \RuntimeException('Pick a receiving account.');
        }
        if (!\in_array($paymentMode, ['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other'], true)) {
            throw new \RuntimeException('Invalid payment mode.');
        }
        $customer = $this->customers->find($customerId);
        if ($customer === null || $customer['status'] !== 'active') {
            throw new \RuntimeException('Pick an active customer.');
        }
        $accountRow = $this->resolveAccount($accountKind, $accountId);
        if ($accountRow === null) {
            throw new \RuntimeException('Receiving account not found.');
        }
        if ($accountRow['status'] !== 'active') {
            throw new \RuntimeException("Receiving account '{$accountRow['name']}' is inactive.");
        }

        $newId = 0;
        Db::transaction(function () use (
            $advanceDate, $customerId, $amount, $accountKind, $accountId,
            $paymentMode, $paymentReference, $remarks, $userId, &$newId
        ): void {
            $fy   = $this->fiscalYearLabel($advanceDate);
            $next = $this->repo->maxSequenceForFy("GP-ADV/{$fy}") + 1;
            $advanceNumber = \sprintf('GP-ADV/%s/%05d', $fy, $next);

            $newId = $this->repo->insert([
                'advance_number'      => $advanceNumber,
                'advance_date'        => $advanceDate,
                'customer_id'         => $customerId,
                'amount'              => $amount,
                'received_into_kind'  => $accountKind,
                'received_into_id'    => $accountId,
                'payment_mode'        => $paymentMode,
                'payment_reference'   => $paymentReference,
                'remarks'             => $remarks,
            ], $userId);

            // Credit the receiving account by amount.
            $this->applyAccountDelta($accountKind, $accountId, $amount);
            ActivityLog::log('create', 'customer_advance', $newId, [
                'label' => $advanceNumber,
                'notes' => 'amount=' . $amount . ', mode=' . $paymentMode,
            ]);
        });
        return $newId;
    }

    /**
     * Refund the remaining balance to the customer (admin only at route layer).
     * Debits the receiving account by current balance, marks 'refunded'.
     * Will not refund what's already been applied — only the unused balance.
     */
    public function refund(int $advanceId, string $reason, int $userId): void
    {
        Db::transaction(function () use ($advanceId, $reason, $userId): void {
            $row = $this->repo->findForUpdate($advanceId);
            if ($row === null) {
                throw new \RuntimeException("Advance #{$advanceId} not found.");
            }
            if ($row['status'] === 'refunded') {
                throw new \RuntimeException('Advance is already refunded.');
            }
            $remaining = (float) $row['balance'];
            if ($remaining <= 0.001) {
                // Nothing left to refund — just mark for audit history.
                $this->repo->markRefunded($advanceId, $reason, $userId);
                return;
            }
            $this->applyAccountDelta(
                (string) $row['received_into_kind'],
                (int) $row['received_into_id'],
                -$remaining
            );
            $this->repo->markRefunded($advanceId, $reason, $userId);
            ActivityLog::log('refund', 'customer_advance', $advanceId, [
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
