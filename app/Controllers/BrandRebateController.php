<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\BrandRebateRepository;
use App\Repositories\BrandRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\ProductRepository;
use App\Repositories\PurchaseInvoiceRepository;
use App\Repositories\SupplierRepository;
use App\Services\BrandRebateService;
use App\Support\Auth;
use App\Support\Db;
use App\Support\Flash;

class BrandRebateController
{
    public function __construct(
        private BrandRebateRepository     $repo      = new BrandRebateRepository(),
        private BrandRepository           $brands    = new BrandRepository(),
        private SupplierRepository        $suppliers = new SupplierRepository(),
        private ProductRepository         $products  = new ProductRepository(),
        private PurchaseInvoiceRepository $invoices  = new PurchaseInvoiceRepository(),
        private BankAccountRepository     $banks     = new BankAccountRepository(),
        private CashierDrawerRepository   $drawers   = new CashierDrawerRepository(),
        private BrandRebateService        $service   = new BrandRebateService(),
    ) {}

    public function index(): string
    {
        $p          = \App\Support\Paginator::fromQuery();
        $brandId    = (int) ($_GET['brand_id'] ?? 0) ?: null;
        $supplierId = (int) ($_GET['supplier_id'] ?? 0) ?: null;
        $modeFilter = (string) ($_GET['mode'] ?? '');
        $stsFilter  = (string) ($_GET['status'] ?? '');

        $filters = [
            'brand_id'    => $brandId,
            'supplier_id' => $supplierId,
            'mode'        => $modeFilter,
            'status'      => $stsFilter,
        ];
        $result = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);

        return view('brand-rebates/index', [
            'title'        => 'Brand Rebates',
            'rebates'      => $result['rows'],
            'brands'       => $this->brands->listActive(),
            'suppliers'    => $this->suppliers->listActive(),
            'brandId'      => $brandId,
            'supplierId'   => $supplierId,
            'modeFilter'   => $modeFilter,
            'statusFilter' => $stsFilter,
            'pg'           => $p->buildContext($result['total'], url('/brand-rebates'), $filters + ['q' => $p->search]),
        ]);
    }

    public function create(): string
    {
        $brands = $this->brands->listActive();
        if ($brands === []) {
            Flash::warning('Create at least one brand first.');
            return redirect('/brands');
        }
        $suppliers = $this->suppliers->listActive();
        if ($suppliers === []) {
            Flash::warning('Create at least one active supplier first.');
            return redirect('/suppliers');
        }

        $preBrand    = (int) ($_GET['brand_id'] ?? 0);
        $preSupplier = (int) ($_GET['supplier_id'] ?? 0);
        $preMode     = (string) ($_GET['mode'] ?? '');

        // For free_stock + cost_reduction modes we need brand-scoped product list / invoice list.
        $brandProducts = [];
        $brandInvoices = [];
        if ($preBrand > 0) {
            $brandProducts = $this->products->listByBrand($preBrand);
            // Recent finalized purchase invoices under this brand from the chosen supplier (if any).
            $brandInvoices = $this->finalizedInvoicesForBrand($preBrand, $preSupplier ?: null);
        }

        return view('brand-rebates/create', [
            'title'         => 'Post Brand Rebate',
            'brands'        => $brands,
            'suppliers'     => $suppliers,
            'banks'         => $this->banks->listActive(),
            'drawers'       => $this->drawers->listActive(),
            'preBrand'      => $preBrand,
            'preSupplier'   => $preSupplier,
            'preMode'       => $preMode,
            'brandProducts' => $brandProducts,
            'brandInvoices' => $brandInvoices,
        ]);
    }

    public function store(): string
    {
        try {
            $newId = $this->service->post($_POST, (int) Auth::id());
            Flash::success('Brand rebate posted.');
            return redirect("/brand-rebates/{$newId}");
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Could not post: ' . $e->getMessage());
            $brandId = (int) ($_POST['brand_id'] ?? 0);
            $mode    = (string) ($_POST['mode'] ?? '');
            $supId   = (int) ($_POST['supplier_id'] ?? 0);
            return redirect('/brand-rebates/create?brand_id=' . $brandId . '&supplier_id=' . $supId . '&mode=' . $mode);
        }
    }

    public function show(array $params): string
    {
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            Flash::error('Rebate not found.');
            return redirect('/brand-rebates');
        }
        $costAdjustments = [];
        if ($row['mode'] === 'cost_reduction') {
            $costAdjustments = $this->repo->costAdjustmentsForRebate((int) $row['id']);
        }
        return view('brand-rebates/show', [
            'title'           => $row['rebate_number'],
            'row'             => $row,
            'costAdjustments' => $costAdjustments,
        ]);
    }

    public function void(array $params): string
    {
        $id     = (int) $params['id'];
        $reason = \trim((string) ($_POST['void_reason'] ?? '')) ?: 'No reason given';
        try {
            $this->service->void($id, $reason, (int) Auth::id());
            Flash::success('Rebate voided. Effects reversed.');
        } catch (\Throwable $e) {
            Flash::error('Void failed: ' . $e->getMessage());
        }
        return redirect("/brand-rebates/{$id}");
    }

    /**
     * Recent finalized purchase invoices under a brand (optionally filtered by supplier).
     * Used by cost_reduction mode to scope the rebate to one bill.
     */
    private function finalizedInvoicesForBrand(int $brandId, ?int $supplierId): array
    {
        $sql = "SELECT pi.id, pi.invoice_number, pi.invoice_date, pi.grand_total, s.name AS supplier_name
                FROM purchase_invoices pi
                JOIN suppliers s ON s.id = pi.supplier_id
                WHERE pi.brand_id = ? AND pi.status = 'finalized'";
        $params = [$brandId];
        if ($supplierId !== null) {
            $sql .= ' AND pi.supplier_id = ?';
            $params[] = $supplierId;
        }
        $sql .= ' ORDER BY pi.invoice_date DESC, pi.id DESC LIMIT 50';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
