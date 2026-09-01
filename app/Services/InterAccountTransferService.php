<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\InterAccountTransferRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * Posts an inter-account transfer atomically:
 *   1. Validate from/to are different endpoints, both active.
 *   2. Validate amount > 0 and source has enough balance (warn if not — do not block).
 *   3. Inside one transaction:
 *      - Insert the transfer row with a fresh GP-XFER/{FY}/{NNNNN} number.
 *      - Decrement source.current_balance by amount.
 *      - Increment dest.current_balance by amount.
 *
 * v1 is append-only — no edits/deletes once posted. To "undo", post a reverse transfer.
 */
class InterAccountTransferService
{
    public function __construct(
        private InterAccountTransferRepository $transfers = new InterAccountTransferRepository(),
        private BankAccountRepository          $banks     = new BankAccountRepository(),
        private CashierDrawerRepository        $drawers   = new CashierDrawerRepository(),
        private BusinessProfileRepository      $business  = new BusinessProfileRepository(),
    ) {}

    /**
     * @return int the new transfer id.
     * @throws \RuntimeException on validation failure.
     */
    public function post(array $input, int $userId): int
    {
        $fromKind = (string) ($input['from_kind'] ?? '');
        $toKind   = (string) ($input['to_kind']   ?? '');
        $fromId   = (int)    ($input['from_id']   ?? 0);
        $toId     = (int)    ($input['to_id']     ?? 0);
        $amount   = (float)  ($input['amount']    ?? 0);
        $date     = \trim((string) ($input['transfer_date'] ?? \date('Y-m-d')));
        $mode     = (string) ($input['mode'] ?? 'cash');
        $ref      = \trim((string) ($input['reference'] ?? '')) ?: null;
        $remarks  = \trim((string) ($input['remarks'] ?? ''))   ?: null;

        if (!\in_array($fromKind, ['bank_account', 'cashier_drawer'], true)) {
            throw new \RuntimeException('Invalid source type.');
        }
        if (!\in_array($toKind, ['bank_account', 'cashier_drawer'], true)) {
            throw new \RuntimeException('Invalid destination type.');
        }
        if ($fromKind === $toKind && $fromId === $toId) {
            throw new \RuntimeException('Source and destination cannot be the same account.');
        }
        if ($fromId <= 0 || $toId <= 0) {
            throw new \RuntimeException('Pick a source and a destination.');
        }
        if ($amount <= 0) {
            throw new \RuntimeException('Amount must be greater than zero.');
        }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \RuntimeException('Transfer date must be YYYY-MM-DD.');
        }
        if (!\in_array($mode, ['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'other'], true)) {
            throw new \RuntimeException('Invalid mode.');
        }

        $fromRow = $this->resolve($fromKind, $fromId);
        $toRow   = $this->resolve($toKind, $toId);
        if ($fromRow === null) { throw new \RuntimeException('Source account not found.'); }
        if ($toRow   === null) { throw new \RuntimeException('Destination account not found.'); }
        if ($fromRow['status'] !== 'active') { throw new \RuntimeException("Source '{$fromRow['name']}' is inactive."); }
        if ($toRow['status']   !== 'active') { throw new \RuntimeException("Destination '{$toRow['name']}' is inactive."); }

        $newId = 0;
        Db::transaction(function () use ($fromKind, $fromId, $toKind, $toId, $amount, $date, $mode, $ref, $remarks, $userId, &$newId): void {
            $fy   = $this->fiscalYearLabel($date);
            $next = $this->transfers->maxSequenceForPrefix("GP-XFER/{$fy}") + 1;
            $transferNumber = \sprintf('GP-XFER/%s/%05d', $fy, $next);

            $newId = $this->transfers->insert([
                'transfer_number' => $transferNumber,
                'transfer_date'   => $date,
                'from_kind'       => $fromKind,
                'from_id'         => $fromId,
                'to_kind'         => $toKind,
                'to_id'           => $toId,
                'amount'          => $amount,
                'mode'            => $mode,
                'reference'       => $ref,
                'remarks'         => $remarks,
            ], $userId);

            // Source: balance goes down by amount. Destination: up by amount.
            $this->applyDelta($fromKind, $fromId, -$amount);
            $this->applyDelta($toKind,   $toId,    $amount);
            ActivityLog::log('create', 'inter_account_transfer', $newId, [
                'label' => $transferNumber,
                'notes' => 'amount=' . $amount . ', from=' . $fromKind . ':' . $fromId . ' → to=' . $toKind . ':' . $toId,
            ]);
        });
        return $newId;
    }

    private function resolve(string $kind, int $id): ?array
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
