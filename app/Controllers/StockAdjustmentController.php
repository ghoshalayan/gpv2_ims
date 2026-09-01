<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ProductRepository;
use App\Repositories\StockAdjustmentLineRepository;
use App\Repositories\StockAdjustmentRepository;
use App\Services\StockAdjustmentService;
use App\Support\Auth;
use App\Support\Flash;

class StockAdjustmentController
{
    public function __construct(
        private StockAdjustmentRepository     $repo     = new StockAdjustmentRepository(),
        private StockAdjustmentLineRepository $lines    = new StockAdjustmentLineRepository(),
        private ProductRepository             $products = new ProductRepository(),
        private StockAdjustmentService        $service  = new StockAdjustmentService(),
    ) {}

    public function index(): string
    {
        $p         = \App\Support\Paginator::fromQuery();
        $reason    = (string) ($_GET['reason'] ?? '');
        $direction = (string) ($_GET['direction'] ?? '');
        $status    = (string) ($_GET['status'] ?? '');
        $filters   = ['reason' => $reason, 'direction' => $direction, 'status' => $status];
        $result    = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);
        return view('stock-adjustments/index', [
            'title'           => 'Stock Adjustments',
            'adjustments'     => $result['rows'],
            'reasonFilter'    => $reason,
            'directionFilter' => $direction,
            'statusFilter'    => $status,
            'pg'              => $p->buildContext($result['total'], url('/stock-adjustments'), $filters + ['q' => $p->search]),
        ]);
    }

    public function create(): string
    {
        $products = $this->products->listSelectable();
        if ($products === []) {
            Flash::warning('Create at least one active product first.');
            return redirect('/products');
        }
        // Bring on-hand qty + moving-avg into the picker so JS can auto-fill cost-at-adjustment.
        $stockSnapshot = [];
        foreach ($products as $p) {
            $row = $this->products->find((int) $p['id']);
            if ($row !== null) {
                $stockSnapshot[(int) $p['id']] = [
                    'qty' => (int)   $row['quantity_on_hand'],
                    'avg' => (float) $row['moving_avg_cost'],
                ];
            }
        }
        return view('stock-adjustments/create', [
            'title'         => 'Post Stock Adjustment',
            'products'      => $products,
            'stockSnapshot' => $stockSnapshot,
        ]);
    }

    public function store(): string
    {
        try {
            $newId = $this->service->post($_POST, (int) Auth::id());
            Flash::success('Stock adjustment posted.');
            return redirect("/stock-adjustments/{$newId}");
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Could not post: ' . $e->getMessage());
            return redirect('/stock-adjustments/create');
        }
    }

    public function show(array $params): string
    {
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            Flash::error('Adjustment not found.');
            return redirect('/stock-adjustments');
        }
        return view('stock-adjustments/show', [
            'title' => $row['adjustment_number'],
            'row'   => $row,
            'lines' => $this->lines->byHeader((int) $row['id']),
        ]);
    }

    public function void(array $params): string
    {
        $id     = (int) $params['id'];
        $reason = \trim((string) ($_POST['void_reason'] ?? '')) ?: 'No reason given';
        try {
            $this->service->void($id, $reason, (int) Auth::id());
            Flash::success('Adjustment voided. Stock effects reversed.');
        } catch (\Throwable $e) {
            Flash::error('Void failed: ' . $e->getMessage());
        }
        return redirect("/stock-adjustments/{$id}");
    }
}
