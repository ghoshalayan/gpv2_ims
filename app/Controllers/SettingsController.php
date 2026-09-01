<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BusinessProfileRepository;
use App\Repositories\UserRepository;
use App\Services\SystemResetService;
use App\Support\Auth;
use App\Support\Db;
use App\Support\Flash;
use App\Support\IndianStates;
use App\Support\Settings;
use App\Support\Validator;

class SettingsController
{
    private const COMMISSION_OPTIONS = ['invoice_finalization', 'invoice_payment', 'manual_only'];
    private const MONTHS = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    private const RESET_CONFIRM_PHRASE = 'RESET DATA';

    public function __construct(
        private BusinessProfileRepository $bp = new BusinessProfileRepository(),
        private UserRepository $users = new UserRepository(),
        private SystemResetService $reset = new SystemResetService(),
    ) {}

    public function show(): string
    {
        return view('settings/index', [
            'title'            => 'System Settings',
            'profile'          => $this->bp->get(),
            'settings'         => Settings::all(),
            'states'           => IndianStates::LIST,
            'months'           => self::MONTHS,
            'commissionModes'  => self::COMMISSION_OPTIONS,
        ]);
    }

    public function update(): string
    {
        // ---- Business Profile ----
        $profile = [
            'legal_name'                 => \trim((string) ($_POST['legal_name']   ?? '')),
            'display_name'               => \trim((string) ($_POST['display_name'] ?? '')),
            'gstin'                      => \strtoupper(\trim((string) ($_POST['gstin'] ?? ''))) ?: null,
            'state'                      => \trim((string) ($_POST['state'] ?? '')),
            'address'                    => \trim((string) ($_POST['address'] ?? '')) ?: null,
            'phone'                      => \trim((string) ($_POST['phone'] ?? '')) ?: null,
            'email'                      => \trim((string) ($_POST['email'] ?? '')) ?: null,
            'financial_year_start_month' => (int) ($_POST['financial_year_start_month'] ?? 4),
        ];

        $v = Validator::make([
                'legal_name'   => $profile['legal_name'],
                'display_name' => $profile['display_name'],
                'state'        => $profile['state'],
                'gstin'        => $profile['gstin'] ?? '',
                'phone'        => $profile['phone'] ?? '',
                'email'        => $profile['email'] ?? '',
            ])
            ->required('legal_name')->max('legal_name', 250)
            ->required('display_name')->max('display_name', 150)
            ->required('state')
            ->gstin('gstin')
            ->phone('phone')
            ->max('email', 150);

        if ($profile['email'] !== null && !\filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
            flash_old($_POST);
            Flash::error('Business email must be a valid address.');
            return redirect('/settings');
        }
        if (!IndianStates::isValid($profile['state'])) {
            flash_old($_POST);
            Flash::error('Business state must be chosen from the list.');
            return redirect('/settings');
        }
        if ($profile['financial_year_start_month'] < 1 || $profile['financial_year_start_month'] > 12) {
            flash_old($_POST);
            Flash::error('Financial year start month must be between 1 and 12.');
            return redirect('/settings');
        }
        if ($v->fails()) {
            flash_old($_POST);
            Flash::error($v->firstError() ?? 'Validation failed.');
            return redirect('/settings');
        }

        // ---- System Settings ----
        $commissionMode = (string) ($_POST['commission_accrues_on'] ?? 'invoice_finalization');
        if (!\in_array($commissionMode, self::COMMISSION_OPTIONS, true)) {
            flash_old($_POST);
            Flash::error('Commission accrual mode is invalid.');
            return redirect('/settings');
        }
        $enableRoundOff      = !empty($_POST['enable_round_off']) ? '1' : '0';
        $lowStockWidget      = !empty($_POST['low_stock_dashboard_widget']) ? '1' : '0';

        Db::transaction(function () use ($profile, $commissionMode, $enableRoundOff, $lowStockWidget): void {
            $userId = (int) Auth::id();
            $this->bp->update($profile, $userId);
            Settings::set('commission_accrues_on', $commissionMode, $userId);
            Settings::set('enable_round_off', $enableRoundOff, $userId);
            Settings::set('low_stock_dashboard_widget', $lowStockWidget, $userId);
        });

        Flash::success('System settings saved.');
        return redirect('/settings');
    }

    /**
     * Hard reset entry point. Wipes every user table and re-seeds defaults
     * (business profile, system settings, role permissions, single admin user).
     *
     * Guards (in order):
     *   1. Caller must be admin (route middleware).
     *   2. CSRF (global middleware).
     *   3. Confirmation phrase typed exactly (defends against form replay / accidental click).
     *   4. Caller's CURRENT password verified against their hash (re-auth).
     *
     * On success, the calling admin is logged out — their user row was just
     * truncated, so the session is meaningless.
     */
    public function hardReset(): string
    {
        $confirm  = (string) ($_POST['confirm_phrase'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($confirm !== self::RESET_CONFIRM_PHRASE) {
            Flash::error('Confirmation phrase did not match. Reset aborted.');
            return redirect('/settings');
        }
        if ($password === '') {
            Flash::error('Admin password is required to authorise a hard reset.');
            return redirect('/settings');
        }

        $current = Auth::user();
        if ($current === null) {
            Flash::error('Session expired.');
            return redirect('/login');
        }
        $userRow = $this->users->findByUsername((string) $current['username']);
        if ($userRow === null || !\password_verify($password, (string) $userRow['password_hash'])) {
            Flash::error('Password verification failed. Reset aborted.');
            return redirect('/settings');
        }

        // Optional new admin credentials — fall back to defaults if blank.
        $newUsername = \trim((string) ($_POST['new_admin_username'] ?? '')) ?: 'admin';
        $newName     = \trim((string) ($_POST['new_admin_name']     ?? '')) ?: 'Administrator';
        $newPhone    = \trim((string) ($_POST['new_admin_phone']    ?? '')) ?: '0000000000';
        $newEmail    = \trim((string) ($_POST['new_admin_email']    ?? '')) ?: null;
        $newPassword = (string) ($_POST['new_admin_password'] ?? '');
        if ($newPassword === '') {
            $newPassword = 'admin123';
        } elseif (\strlen($newPassword) < 6) {
            Flash::error('New admin password must be at least 6 characters.');
            return redirect('/settings');
        }

        try {
            $this->reset->reset([
                'username' => $newUsername,
                'password' => $newPassword,
                'name'     => $newName,
                'phone'    => $newPhone,
                'email'    => $newEmail,
            ]);
        } catch (\Throwable $e) {
            Flash::error('Reset failed: ' . $e->getMessage());
            return redirect('/settings');
        }

        Auth::logout();
        Flash::success(
            'System has been hard-reset. New admin credentials: '
            . $newUsername . ' / ' . $newPassword
            . ' — please change this password immediately after logging in.'
        );
        return redirect('/login');
    }
}
