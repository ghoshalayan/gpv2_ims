<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\OperatingExpenseCategoryRepository;
use App\Repositories\OperatingExpenseRepository;
use App\Repositories\RecurringExpenseRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * Manages recurring-expense templates and the firing engine.
 *
 *   - create / update validate the same way as a regular expense.
 *   - fireDue($asOf) finds all status='active' rows with next_run_date <= $asOf,
 *     posts each as a regular expense (debiting the paying account), and advances
 *     next_run_date by one frequency step. If the new next_run_date overshoots
 *     end_date, the template is auto-paused.
 *   - fireOne fires a single template once (used by the "Run Now" button).
 *
 * Run summary is returned to the caller for flash messaging.
 */
class RecurringExpenseService
{
    public function __construct(
        private RecurringExpenseRepository           $recurring  = new RecurringExpenseRepository(),
        private OperatingExpenseRepository           $expenses   = new OperatingExpenseRepository(),
        private OperatingExpenseCategoryRepository   $categories = new OperatingExpenseCategoryRepository(),
        private BankAccountRepository                $banks      = new BankAccountRepository(),
        private CashierDrawerRepository              $drawers    = new CashierDrawerRepository(),
        private BusinessProfileRepository            $business   = new BusinessProfileRepository(),
        private PricingService                       $pricing    = new PricingService(),
    ) {}

    public function create(array $input, int $userId): int
    {
        $data = $this->validateAndShape($input);
        $data['next_run_date'] = $data['start_date'];   // first run = start_date
        return $this->recurring->insert($data, $userId);
    }

    public function update(int $id, array $input, int $userId): void
    {
        $existing = $this->recurring->find($id);
        if ($existing === null) {
            throw new \RuntimeException("Recurring expense #{$id} not found.");
        }
        $data = $this->validateAndShape($input);
        // Preserve next_run_date if the user didn't explicitly reset it via start_date change.
        // If start_date moved earlier than current next_run_date, snap back to start_date.
        $data['next_run_date'] = $data['start_date'] > $existing['next_run_date']
            ? $data['start_date']
            : $existing['next_run_date'];
        $this->recurring->update($id, $data, $userId);
    }

    /**
     * Fire all due recurring templates as of $asOf (default = today).
     * @return array{fired:int, paused:int, errors:array<int,string>}
     */
    public function fireDue(string $asOf, int $userId): array
    {
        $due = $this->recurring->dueAsOf($asOf);
        $fired = 0; $paused = 0; $errors = [];
        foreach ($due as $row) {
            try {
                if ($this->fireOne((int) $row['id'], $asOf, $userId)) {
                    $fired++;
                } else {
                    $paused++;
                }
            } catch (\Throwable $e) {
                $errors[] = "{$row['name']}: " . $e->getMessage();
            }
        }
        return ['fired' => $fired, 'paused' => $paused, 'errors' => $errors];
    }

    /**
     * Fire one recurring template once. Posts an expense for next_run_date and advances.
     * @return bool true if the template still has more runs ahead; false if auto-paused.
     */
    public function fireOne(int $recurringId, string $asOf, int $userId): bool
    {
        $row = $this->recurring->find($recurringId);
        if ($row === null) {
            throw new \RuntimeException("Recurring expense #{$recurringId} not found.");
        }
        if ($row['status'] !== 'active') {
            throw new \RuntimeException("Template '{$row['name']}' is paused.");
        }

        $expenseDate = (string) $row['next_run_date'];
        $stillActive = true;

        Db::transaction(function () use ($row, $expenseDate, $userId, &$stillActive): void {
            $payingRow = $row['paid_from_kind'] === 'bank_account'
                ? $this->banks->find((int) $row['paid_from_id'])
                : $this->drawers->find((int) $row['paid_from_id']);
            if ($payingRow === null) {
                throw new \RuntimeException('Paying account on template no longer exists.');
            }
            if ($payingRow['status'] !== 'active') {
                throw new \RuntimeException("Paying account '{$payingRow['name']}' is inactive.");
            }
            $cat = $this->categories->find((int) $row['category_id']);
            if ($cat === null || $cat['status'] !== 'active') {
                throw new \RuntimeException('Template category is missing or inactive.');
            }

            $amount       = (float) $row['amount'];
            $gstRate      = (float) $row['gst_rate'];
            $gstAmount    = $this->pricing->money($amount * $gstRate / 100);
            $totalWithGst = $this->pricing->money($amount + $gstAmount);

            $fy   = $this->fiscalYearLabel($expenseDate);
            $next = $this->expenses->maxSequenceForFy("GP-EXP/{$fy}") + 1;
            $expenseNumber = \sprintf('GP-EXP/%s/%05d', $fy, $next);

            $this->expenses->insert([
                'expense_number'           => $expenseNumber,
                'expense_date'             => $expenseDate,
                'category_id'              => (int) $row['category_id'],
                'vendor_name'              => $row['vendor_name'],
                'amount'                   => $amount,
                'gst_rate'                 => $gstRate,
                'gst_amount'               => $gstAmount,
                'gst_claimable'            => (int) $row['gst_claimable'],
                'total_with_gst'           => $totalWithGst,
                'paid_from_kind'           => (string) $row['paid_from_kind'],
                'paid_from_id'             => (int) $row['paid_from_id'],
                'payment_mode'             => (string) $row['payment_mode'],
                'payment_reference'        => $row['payment_reference'],
                'remarks'                  => $row['remarks'],
                'spawned_from_recurring_id'=> (int) $row['id'],
            ], $userId);

            // Debit paying account.
            if ($row['paid_from_kind'] === 'bank_account') {
                $this->banks->applyDelta((int) $row['paid_from_id'], -$totalWithGst);
            } else {
                $this->drawers->applyDelta((int) $row['paid_from_id'], -$totalWithGst);
            }

            // Advance next_run_date.
            $newNext = $this->advance($expenseDate, (string) $row['frequency'], (int) $row['interval_count']);
            $newStatus = 'active';
            if ($row['end_date'] !== null && $newNext > $row['end_date']) {
                $newStatus   = 'paused';
                $stillActive = false;
            }
            $this->recurring->recordRun((int) $row['id'], $newNext, $newStatus);
            ActivityLog::log('create', 'operating_expense', null, [
                'label' => $expenseNumber,
                'notes' => 'auto-spawn from recurring template "' . $row['name'] . '" (#' . $row['id'] . ')',
            ]);
        });

        return $stillActive;
    }

    /** @return array<string, mixed> validated + shaped data ready to write. */
    private function validateAndShape(array $input): array
    {
        $name        = \trim((string) ($input['name'] ?? ''));
        $categoryId  = (int)    ($input['category_id'] ?? 0);
        $vendorName  = \trim((string) ($input['vendor_name'] ?? '')) ?: null;
        $amount      = (float)  ($input['amount']    ?? 0);
        $gstRate     = (float)  ($input['gst_rate']  ?? 0);
        $gstClaimable= !empty($input['gst_claimable']) ? 1 : 0;
        $paidFromKind= (string) ($input['paid_from_kind'] ?? '');
        $paidFromId  = (int)    ($input['paid_from_id']   ?? 0);
        $paymentMode = (string) ($input['payment_mode']   ?? 'cash');
        $paymentRef  = \trim((string) ($input['payment_reference'] ?? '')) ?: null;
        $remarks     = \trim((string) ($input['remarks'] ?? '')) ?: null;
        $frequency   = (string) ($input['frequency']      ?? 'monthly');
        $intervalCnt = \max(1, (int) ($input['interval_count'] ?? 1));
        $startDate   = \trim((string) ($input['start_date'] ?? \date('Y-m-d')));
        $endDate     = \trim((string) ($input['end_date'] ?? '')) ?: null;

        if ($name === '')        { throw new \RuntimeException('Name is required.'); }
        if ($amount <= 0)        { throw new \RuntimeException('Amount must be greater than zero.'); }
        if ($gstRate < 0 || $gstRate > 100) { throw new \RuntimeException('GST rate must be 0-100.'); }
        if (!\in_array($frequency, ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'], true)) {
            throw new \RuntimeException('Invalid frequency.');
        }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            throw new \RuntimeException('Start date must be YYYY-MM-DD.');
        }
        if ($endDate !== null && !\preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            throw new \RuntimeException('End date must be YYYY-MM-DD.');
        }
        if ($endDate !== null && $endDate < $startDate) {
            throw new \RuntimeException('End date cannot be before start date.');
        }
        if (!\in_array($paidFromKind, ['bank_account', 'cashier_drawer'], true)) {
            throw new \RuntimeException('Pick a paying account (bank or drawer).');
        }
        if (!\in_array($paymentMode, ['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other'], true)) {
            throw new \RuntimeException('Invalid payment mode.');
        }
        $cat = $this->categories->find($categoryId);
        if ($cat === null || $cat['status'] !== 'active') {
            throw new \RuntimeException('Pick an active expense category.');
        }
        $payingRow = $paidFromKind === 'bank_account'
            ? $this->banks->find($paidFromId)
            : $this->drawers->find($paidFromId);
        if ($payingRow === null) {
            throw new \RuntimeException('Paying account not found.');
        }
        if ($payingRow['status'] !== 'active') {
            throw new \RuntimeException("Paying account '{$payingRow['name']}' is inactive.");
        }

        return [
            'name'              => $name,
            'category_id'       => $categoryId,
            'vendor_name'       => $vendorName,
            'amount'            => $amount,
            'gst_rate'          => $gstRate,
            'gst_claimable'     => $gstClaimable,
            'paid_from_kind'    => $paidFromKind,
            'paid_from_id'      => $paidFromId,
            'payment_mode'      => $paymentMode,
            'payment_reference' => $paymentRef,
            'remarks'           => $remarks,
            'frequency'         => $frequency,
            'interval_count'    => $intervalCnt,
            'start_date'        => $startDate,
            'end_date'          => $endDate,
        ];
    }

    /** Compute the next run date by advancing $fromDate by N×frequency. */
    private function advance(string $fromDate, string $frequency, int $intervalCount): string
    {
        $modifier = match ($frequency) {
            'daily'     => "+{$intervalCount} day",
            'weekly'    => "+{$intervalCount} week",
            'monthly'   => "+{$intervalCount} month",
            'quarterly' => "+" . ($intervalCount * 3) . " month",
            'yearly'    => "+{$intervalCount} year",
        };
        $ts = \strtotime($modifier, \strtotime($fromDate) ?: \time());
        return \date('Y-m-d', $ts ?: \time());
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
