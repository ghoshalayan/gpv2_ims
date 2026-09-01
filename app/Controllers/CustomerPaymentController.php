<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\CustomerAdvanceRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\SalesInvoiceRepository;
use App\Services\CustomerPaymentService;
use App\Support\Auth;
use App\Support\Flash;

class CustomerPaymentController
{
    public function __construct(
        private CustomerRepository        $customers = new CustomerRepository(),
        private SalesInvoiceRepository    $invoices  = new SalesInvoiceRepository(),
        private CustomerAdvanceRepository $advances  = new CustomerAdvanceRepository(),
        private BankAccountRepository     $banks     = new BankAccountRepository(),
        private CashierDrawerRepository   $drawers   = new CashierDrawerRepository(),
        private CustomerPaymentService    $service   = new CustomerPaymentService(),
    ) {}

    public function create(): string
    {
        $customerId = (int) ($_GET['customer_id'] ?? 0);
        $customers  = $this->customers->listActive();
        if ($customers === []) {
            Flash::warning('No active customers.');
            return redirect('/customers');
        }

        $context = null;
        if ($customerId > 0) {
            $cust = $this->customers->find($customerId);
            if ($cust !== null) {
                $context = [
                    'customer'         => $cust,
                    'unpaid_invoices'  => $this->invoices->unpaidForCustomer($customerId),
                    'available_advances' => $this->advances->activeForCustomer($customerId),
                    'available_advance_total' => $this->advances->availableBalanceForCustomer($customerId),
                ];
            }
        }

        return view('customer-payments/create', [
            'title'      => 'Bulk Customer Payment',
            'customers'  => $customers,
            'banks'      => $this->banks->listActive(),
            'drawers'    => $this->drawers->listActive(),
            'context'    => $context,
        ]);
    }

    public function store(): string
    {
        // Convert combobox value to kind/id.
        [$kind, $id] = $this->parseAccountChoice((string) ($_POST['received_into'] ?? ''));
        $_POST['received_into_kind'] = $kind;
        $_POST['received_into_id']   = $id;

        $customerId = (int) ($_POST['customer_id'] ?? 0);

        try {
            [$createdIds, $summary] = $this->service->postBulk($_POST, (int) Auth::id());
            Flash::success(\sprintf(
                'Bulk payment posted: %d invoice(s) updated. Cash ₹%0.2f, Online ₹%0.2f, Advance ₹%0.2f. Total ₹%0.2f.',
                $summary['applied_count'], $summary['cash'], $summary['online'], $summary['advance'], $summary['total']
            ));
            return redirect("/customer-payments/create?customer_id={$customerId}");
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Could not post bulk payment: ' . $e->getMessage());
            return redirect("/customer-payments/create?customer_id={$customerId}");
        }
    }

    private function parseAccountChoice(string $raw): array
    {
        if ($raw === '' || !\str_contains($raw, ':')) { return [null, 0]; }
        [$kind, $id] = \explode(':', $raw, 2);
        if (!\in_array($kind, ['bank_account', 'cashier_drawer'], true)) { return [null, 0]; }
        return [$kind, (int) $id];
    }
}
