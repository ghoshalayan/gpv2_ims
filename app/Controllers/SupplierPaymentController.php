<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\PurchaseInvoiceRepository;
use App\Repositories\SupplierAdvanceRepository;
use App\Repositories\SupplierRepository;
use App\Services\SupplierPaymentService;
use App\Support\Auth;
use App\Support\Flash;

class SupplierPaymentController
{
    public function __construct(
        private SupplierRepository        $suppliers = new SupplierRepository(),
        private PurchaseInvoiceRepository $invoices  = new PurchaseInvoiceRepository(),
        private SupplierAdvanceRepository $advances  = new SupplierAdvanceRepository(),
        private BankAccountRepository     $banks     = new BankAccountRepository(),
        private CashierDrawerRepository   $drawers   = new CashierDrawerRepository(),
        private SupplierPaymentService    $service   = new SupplierPaymentService(),
    ) {}

    public function create(): string
    {
        $supplierId = (int) ($_GET['supplier_id'] ?? 0);
        $suppliers  = $this->suppliers->listActive();
        if ($suppliers === []) {
            Flash::warning('No active suppliers.');
            return redirect('/suppliers');
        }

        $context = null;
        if ($supplierId > 0) {
            $sup = $this->suppliers->find($supplierId);
            if ($sup !== null) {
                $context = [
                    'supplier'                => $sup,
                    'unpaid_invoices'         => $this->invoices->unpaidForSupplier($supplierId),
                    'available_advances'      => $this->advances->activeForSupplier($supplierId),
                    'available_advance_total' => $this->advances->availableBalanceForSupplier($supplierId),
                ];
            }
        }

        return view('supplier-payments/create', [
            'title'      => 'Bulk Supplier Payment',
            'suppliers'  => $suppliers,
            'banks'      => $this->banks->listActive(),
            'drawers'    => $this->drawers->listActive(),
            'context'    => $context,
        ]);
    }

    public function store(): string
    {
        [$kind, $id] = $this->parseAccountChoice((string) ($_POST['paid_from'] ?? ''));
        $_POST['paid_from_kind'] = $kind;
        $_POST['paid_from_id']   = $id;

        $supplierId = (int) ($_POST['supplier_id'] ?? 0);

        try {
            [$createdIds, $summary] = $this->service->postBulk($_POST, (int) Auth::id());
            Flash::success(\sprintf(
                'Bulk payment posted: %d invoice(s) updated. Cash ₹%0.2f, Online ₹%0.2f, Advance ₹%0.2f. Total ₹%0.2f.',
                $summary['applied_count'], $summary['cash'], $summary['online'], $summary['advance'], $summary['total']
            ));
            return redirect("/supplier-payments/create?supplier_id={$supplierId}");
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Could not post bulk payment: ' . $e->getMessage());
            return redirect("/supplier-payments/create?supplier_id={$supplierId}");
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
