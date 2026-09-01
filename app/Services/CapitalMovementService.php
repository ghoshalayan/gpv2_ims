<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CapitalMovementRepository;
use App\Repositories\CashierDrawerRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * Posts capital movements (owner injections, drawings, loans in/out).
 *
 * Direction is fixed by txn_type for the four standard cases; 'other' lets the user pick.
 *   capital_in / loan_in   → in  (account credited, +balance)
 *   drawing    / loan_repay → out (account debited, -balance)
 *
 * v1 is append-only. To "undo" a posted movement, void it (admin-only at the route layer):
 * voiding posts a reverse balance delta and marks status='voided'.
 */
class CapitalMovementService
{
    private const DIRECTION_BY_TYPE = [
        'capital_in' => 'in',
        'drawing'    => 'out',
        'loan_in'    => 'in',
        'loan_repay' => 'out',
    ];

    public function __construct(
        private CapitalMovementRepository $repo     = new CapitalMovementRepository(),
        private BankAccountRepository     $banks    = new BankAccountRepository(),
        private CashierDrawerRepository   $drawers  = new CashierDrawerRepository(),
        private BusinessProfileRepository $business = new BusinessProfileRepository(),
    ) {}

    public function post(array $input, int $userId): int
    {
        $txnType          = (string) ($input['txn_type'] ?? '');
        $counterpartyName = \trim((string) ($input['counterparty_name'] ?? ''));
        $amount           = (float)  ($input['amount']    ?? 0);
        $movementDate     = \trim((string) ($input['movement_date'] ?? \date('Y-m-d')));
        $accountKind      = (string) ($input['account_kind'] ?? '');
        $accountId        = (int)    ($input['account_id']   ?? 0);
        $mode             = (string) ($input['mode']         ?? 'cash');
        $reference        = \trim((string) ($input['reference'] ?? '')) ?: null;
        $remarks          = \trim((string) ($input['remarks']   ?? '')) ?: null;
        $directionPicked  = (string) ($input['direction'] ?? '');

        if (!\in_array($txnType, ['capital_in', 'drawing', 'loan_in', 'loan_repay', 'other'], true)) {
            throw new \RuntimeException('Invalid transaction type.');
        }
        if ($counterpartyName === '') {
            throw new \RuntimeException('Counterparty name is required (owner / lender / partner).');
        }
        if ($amount <= 0) {
            throw new \RuntimeException('Amount must be greater than zero.');
        }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $movementDate)) {
            throw new \RuntimeException('Movement date must be YYYY-MM-DD.');
        }
        if (!\in_array($accountKind, ['bank_account', 'cashier_drawer'], true)) {
            throw new \RuntimeException('Pick a bank account or cashier drawer.');
        }
        if (!\in_array($mode, ['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other'], true)) {
            throw new \RuntimeException('Invalid mode.');
        }

        // Direction: fixed by txn_type for standard cases; user picks for 'other'.
        $direction = self::DIRECTION_BY_TYPE[$txnType] ?? null;
        if ($direction === null) {
            if (!\in_array($directionPicked, ['in', 'out'], true)) {
                throw new \RuntimeException("Pick a direction (in or out) for 'other'.");
            }
            $direction = $directionPicked;
        }

        $accountRow = $this->resolveAccount($accountKind, $accountId);
        if ($accountRow === null) {
            throw new \RuntimeException('Account not found.');
        }
        if ($accountRow['status'] !== 'active') {
            throw new \RuntimeException("Account '{$accountRow['name']}' is inactive.");
        }

        $newId = 0;
        Db::transaction(function () use (
            $movementDate, $txnType, $direction, $counterpartyName, $amount,
            $accountKind, $accountId, $mode, $reference, $remarks, $userId, &$newId
        ): void {
            $fy   = $this->fiscalYearLabel($movementDate);
            $next = $this->repo->maxSequenceForFy("GP-CAP/{$fy}") + 1;
            $movementNumber = \sprintf('GP-CAP/%s/%05d', $fy, $next);

            $newId = $this->repo->insert([
                'movement_number'   => $movementNumber,
                'movement_date'     => $movementDate,
                'txn_type'          => $txnType,
                'direction'         => $direction,
                'counterparty_name' => $counterpartyName,
                'amount'            => $amount,
                'account_kind'      => $accountKind,
                'account_id'        => $accountId,
                'mode'              => $mode,
                'reference'         => $reference,
                'remarks'           => $remarks,
            ], $userId);

            // direction=in  → credit account (+amount)
            // direction=out → debit account  (-amount)
            $delta = $direction === 'in' ? $amount : -$amount;
            $this->applyDelta($accountKind, $accountId, $delta);
            ActivityLog::log('create', 'capital_movement', $newId, [
                'label' => $movementNumber,
                'notes' => 'type=' . $txnType . ', direction=' . $direction . ', amount=' . $amount,
            ]);
        });
        return $newId;
    }

    /** Reverse a posted movement — flips the original balance impact. Admin-only at route. */
    public function void(int $movementId, string $reason, int $userId): void
    {
        Db::transaction(function () use ($movementId, $reason, $userId): void {
            $row = $this->repo->find($movementId);
            if ($row === null) {
                throw new \RuntimeException("Capital movement #{$movementId} not found.");
            }
            if ($row['status'] !== 'posted') {
                throw new \RuntimeException("Movement is already {$row['status']}.");
            }
            // Reverse the original delta.
            $delta = $row['direction'] === 'in' ? -(float) $row['amount'] : (float) $row['amount'];
            $this->applyDelta((string) $row['account_kind'], (int) $row['account_id'], $delta);
            $this->repo->markVoided($movementId, $reason, $userId);
            ActivityLog::log('void', 'capital_movement', $movementId, [
                'label' => $row['movement_number'] ?? null,
                'notes' => 'reason=' . $reason,
            ]);
        });
    }

    private function resolveAccount(string $kind, int $id): ?array
    {
        return $kind === 'bank_account' ? $this->banks->find($id) : $this->drawers->find($id);
    }

    private function applyDelta(string $kind, int $id, float $delta): void
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
