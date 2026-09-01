<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\OperatingExpenseCategoryRepository;
use App\Repositories\OperatingExpenseRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * Posts an operating expense atomically:
 *   1. Validate category active, paying account active, amount > 0.
 *   2. Compute totals: total_with_gst = amount + gst_amount.
 *   3. Inside one transaction:
 *      - Insert expense row with GP-EXP/{FY}/{NNNNN}.
 *      - Decrement paid_from account's current_balance by total_with_gst.
 *
 * Void: reverse balance impact + mark status='voided'. Admin only at the route layer.
 */
class OperatingExpenseService
{
    public function __construct(
        private OperatingExpenseRepository           $expenses   = new OperatingExpenseRepository(),
        private OperatingExpenseCategoryRepository   $categories = new OperatingExpenseCategoryRepository(),
        private BankAccountRepository                $banks      = new BankAccountRepository(),
        private CashierDrawerRepository              $drawers    = new CashierDrawerRepository(),
        private BusinessProfileRepository            $business   = new BusinessProfileRepository(),
        private PricingService                       $pricing    = new PricingService(),
    ) {}

    /**
     * @return int new expense id.
     * @throws \RuntimeException on validation failure.
     */
    public function post(array $input, int $userId): int
    {
        $categoryId   = (int)    ($input['category_id']  ?? 0);
        $vendorName   = \trim((string) ($input['vendor_name'] ?? '')) ?: null;
        $amount       = (float)  ($input['amount']       ?? 0);
        $gstRate      = (float)  ($input['gst_rate']     ?? 0);
        $gstClaimable = !empty($input['gst_claimable']) ? 1 : 0;
        $expenseDate  = \trim((string) ($input['expense_date'] ?? \date('Y-m-d')));
        $paidFromKind = (string) ($input['paid_from_kind'] ?? '');
        $paidFromId   = (int)    ($input['paid_from_id']   ?? 0);
        $paymentMode  = (string) ($input['payment_mode']   ?? 'cash');
        $paymentRef   = \trim((string) ($input['payment_reference'] ?? '')) ?: null;
        $remarks      = \trim((string) ($input['remarks'] ?? '')) ?: null;

        if ($amount <= 0)        { throw new \RuntimeException('Amount must be greater than zero.'); }
        if ($gstRate < 0 || $gstRate > 100) { throw new \RuntimeException('GST rate must be 0-100.'); }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
            throw new \RuntimeException('Expense date must be YYYY-MM-DD.');
        }
        if (!\in_array($paidFromKind, ['bank_account', 'cashier_drawer'], true)) {
            throw new \RuntimeException('Pick a paying account (bank or drawer).');
        }
        if (!\in_array($paymentMode, ['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other'], true)) {
            throw new \RuntimeException('Invalid payment mode.');
        }

        $category = $this->categories->find($categoryId);
        if ($category === null || $category['status'] !== 'active') {
            throw new \RuntimeException('Pick an active expense category.');
        }
        $payingRow = $this->resolveAccount($paidFromKind, $paidFromId);
        if ($payingRow === null) {
            throw new \RuntimeException('Paying account not found.');
        }
        if ($payingRow['status'] !== 'active') {
            throw new \RuntimeException("Paying account '{$payingRow['name']}' is inactive.");
        }

        $gstAmount     = $this->pricing->money($amount * $gstRate / 100);
        $totalWithGst  = $this->pricing->money($amount + $gstAmount);

        $newId = 0;
        Db::transaction(function () use (
            $expenseDate, $categoryId, $vendorName, $amount, $gstRate, $gstAmount, $gstClaimable,
            $totalWithGst, $paidFromKind, $paidFromId, $paymentMode, $paymentRef, $remarks, $userId, &$newId
        ): void {
            $fy   = $this->fiscalYearLabel($expenseDate);
            $next = $this->expenses->maxSequenceForFy("GP-EXP/{$fy}") + 1;
            $expenseNumber = \sprintf('GP-EXP/%s/%05d', $fy, $next);

            $newId = $this->expenses->insert([
                'expense_number'   => $expenseNumber,
                'expense_date'     => $expenseDate,
                'category_id'      => $categoryId,
                'vendor_name'      => $vendorName,
                'amount'           => $amount,
                'gst_rate'         => $gstRate,
                'gst_amount'       => $gstAmount,
                'gst_claimable'    => $gstClaimable,
                'total_with_gst'   => $totalWithGst,
                'paid_from_kind'   => $paidFromKind,
                'paid_from_id'     => $paidFromId,
                'payment_mode'     => $paymentMode,
                'payment_reference'=> $paymentRef,
                'remarks'          => $remarks,
            ], $userId);

            // Debit the paying account.
            $this->applyDelta($paidFromKind, $paidFromId, -$totalWithGst);
            ActivityLog::log('create', 'operating_expense', $newId, [
                'label' => $expenseNumber,
                'notes' => 'amount=' . $totalWithGst . ', vendor=' . ($vendorName ?? '?'),
            ]);
        });
        return $newId;
    }

    /**
     * Edit a posted expense. Atomically:
     *   - credit the original paying account by the original total (reverse the debit)
     *   - debit the (possibly new) paying account by the new total
     *   - update the expense row with the new values
     *
     * Voided expenses cannot be edited. Expense number / status / created_at are preserved.
     */
    public function editPost(int $expenseId, array $input, int $userId): void
    {
        $existing = $this->expenses->find($expenseId);
        if ($existing === null) {
            throw new \RuntimeException("Expense #{$expenseId} not found.");
        }
        if ($existing['status'] !== 'posted') {
            throw new \RuntimeException("Expense is {$existing['status']} — cannot edit.");
        }

        // Re-validate the input shape using the same rules as post().
        $categoryId   = (int)    ($input['category_id']  ?? 0);
        $vendorName   = \trim((string) ($input['vendor_name'] ?? '')) ?: null;
        $amount       = (float)  ($input['amount']       ?? 0);
        $gstRate      = (float)  ($input['gst_rate']     ?? 0);
        $gstClaimable = !empty($input['gst_claimable']) ? 1 : 0;
        $expenseDate  = \trim((string) ($input['expense_date'] ?? \date('Y-m-d')));
        $paidFromKind = (string) ($input['paid_from_kind'] ?? '');
        $paidFromId   = (int)    ($input['paid_from_id']   ?? 0);
        $paymentMode  = (string) ($input['payment_mode']   ?? 'cash');
        $paymentRef   = \trim((string) ($input['payment_reference'] ?? '')) ?: null;
        $remarks      = \trim((string) ($input['remarks'] ?? '')) ?: null;

        if ($amount <= 0)        { throw new \RuntimeException('Amount must be greater than zero.'); }
        if ($gstRate < 0 || $gstRate > 100) { throw new \RuntimeException('GST rate must be 0-100.'); }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
            throw new \RuntimeException('Expense date must be YYYY-MM-DD.');
        }
        if (!\in_array($paidFromKind, ['bank_account', 'cashier_drawer'], true)) {
            throw new \RuntimeException('Pick a paying account (bank or drawer).');
        }
        if (!\in_array($paymentMode, ['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other'], true)) {
            throw new \RuntimeException('Invalid payment mode.');
        }

        $category = $this->categories->find($categoryId);
        if ($category === null || $category['status'] !== 'active') {
            throw new \RuntimeException('Pick an active expense category.');
        }
        $newPaying = $this->resolveAccount($paidFromKind, $paidFromId);
        if ($newPaying === null) {
            throw new \RuntimeException('Paying account not found.');
        }
        if ($newPaying['status'] !== 'active') {
            throw new \RuntimeException("Paying account '{$newPaying['name']}' is inactive.");
        }

        $newGstAmount    = $this->pricing->money($amount * $gstRate / 100);
        $newTotalWithGst = $this->pricing->money($amount + $newGstAmount);

        Db::transaction(function () use (
            $expenseId,
            $paidFromKind, $paidFromId, $newTotalWithGst,
            $expenseDate, $categoryId, $vendorName, $amount, $gstRate, $newGstAmount, $gstClaimable,
            $paymentMode, $paymentRef, $remarks, $userId
        ): void {
            // Re-read the existing row INSIDE the transaction with FOR UPDATE — closes
            // the race window where two admins simultaneously edit the same expense and
            // the second reads stale "old total / old account" values.
            $locked = $this->expenses->findForUpdate($expenseId);
            if ($locked === null) {
                throw new \RuntimeException("Expense #{$expenseId} vanished during edit.");
            }
            if ($locked['status'] !== 'posted') {
                throw new \RuntimeException("Expense is {$locked['status']} — cannot edit.");
            }
            $oldKind  = (string) $locked['paid_from_kind'];
            $oldId    = (int)    $locked['paid_from_id'];
            $oldTotal = (float)  $locked['total_with_gst'];

            // Reverse the original debit (credit old account back).
            $this->applyDelta($oldKind, $oldId, $oldTotal);
            // Apply the new debit on the (possibly new) account.
            $this->applyDelta($paidFromKind, $paidFromId, -$newTotalWithGst);

            $this->expenses->updateRow($expenseId, [
                'expense_date'     => $expenseDate,
                'category_id'      => $categoryId,
                'vendor_name'      => $vendorName,
                'amount'           => $amount,
                'gst_rate'         => $gstRate,
                'gst_amount'       => $newGstAmount,
                'gst_claimable'    => $gstClaimable,
                'total_with_gst'   => $newTotalWithGst,
                'paid_from_kind'   => $paidFromKind,
                'paid_from_id'     => $paidFromId,
                'payment_mode'     => $paymentMode,
                'payment_reference'=> $paymentRef,
                'remarks'          => $remarks,
            ], $userId);
            ActivityLog::log('update', 'operating_expense', $expenseId, [
                'label' => $locked['expense_number'] ?? null,
                'before' => ['amount' => $locked['amount'], 'total_with_gst' => $oldTotal],
                'after'  => ['amount' => $amount, 'total_with_gst' => $newTotalWithGst],
            ]);
        });
    }

    /** Reverse a posted expense — credits the originally-debited account back. Admin only. */
    public function void(int $expenseId, string $reason, int $userId): void
    {
        Db::transaction(function () use ($expenseId, $reason, $userId): void {
            $expense = $this->expenses->find($expenseId);
            if ($expense === null) {
                throw new \RuntimeException("Expense #{$expenseId} not found.");
            }
            if ($expense['status'] !== 'posted') {
                throw new \RuntimeException("Expense is already {$expense['status']}.");
            }
            $this->applyDelta(
                (string) $expense['paid_from_kind'],
                (int) $expense['paid_from_id'],
                (float) $expense['total_with_gst']           // credit back
            );
            $this->expenses->markVoided($expenseId, $reason, $userId);
            ActivityLog::log('void', 'operating_expense', $expenseId, [
                'label' => $expense['expense_number'] ?? null,
                'notes' => 'reason=' . $reason,
            ]);
        });
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
