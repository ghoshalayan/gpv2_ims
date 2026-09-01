<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ProductCompositionRepository;
use App\Repositories\ProductionOrderRepository;
use App\Services\ProductionOrderService;
use App\Support\Auth;
use App\Support\Db;
use App\Support\Flash;

class ProductionOrderController
{
    public function __construct(
        private ProductionOrderRepository    $orders       = new ProductionOrderRepository(),
        private ProductCompositionRepository $compositions = new ProductCompositionRepository(),
        private ProductionOrderService       $service      = new ProductionOrderService(),
    ) {}

    public function index(): string
    {
        return view('production-orders/index', [
            'title'  => 'Production Orders',
            'orders' => $this->orders->all(),
        ]);
    }

    public function create(): string
    {
        $products = $this->irreversibleCompositesWithRecipe();
        if ($products === []) {
            Flash::warning('No Irreversible composite with a recipe exists. Create a composite product and add its recipe first.');
            return redirect('/products');
        }
        $recipes = $this->recipesForProducts(\array_column($products, 'id'));

        return view('production-orders/create', [
            'title'    => 'New Production Order',
            'products' => $products,
            'recipes'  => $recipes,
        ]);
    }

    public function store(): string
    {
        $productId    = (int)   ($_POST['product_id']        ?? 0);
        $quantity     = (float) ($_POST['quantity_produced'] ?? 0);
        $producedDate = \trim((string) ($_POST['produced_date'] ?? \date('Y-m-d')));
        $notes        = \trim((string) ($_POST['notes'] ?? '')) ?: null;

        try {
            $id = $this->service->produce($productId, $quantity, $producedDate, $notes, (int) Auth::id());
            Flash::success("Production recorded. Composite stock increased by {$quantity}. Components deducted.");
            return redirect("/production-orders/{$id}");
        } catch (\Throwable $e) {
            Flash::error('Production failed: ' . $e->getMessage());
            return redirect('/production-orders/create');
        }
    }

    public function show(array $params): string
    {
        $id = (int) $params['id'];
        $order = $this->orders->find($id);
        if ($order === null) {
            Flash::error('Production order not found.');
            return redirect('/production-orders');
        }
        return view('production-orders/show', [
            'title' => "Production {$order['order_number']}",
            'order' => $order,
            'lines' => $this->orders->linesByOrder($id),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function irreversibleCompositesWithRecipe(): array
    {
        return Db::pdo()->query(
            "SELECT p.id, p.name, p.unit, p.quantity_on_hand, p.moving_avg_cost,
                    b.name AS brand_name
             FROM products p
             JOIN brands b ON b.id = p.brand_id
             JOIN product_compositions pc ON pc.product_id = p.id
             WHERE p.is_composite = 1 AND p.is_irreversible = 1
               AND p.status = 'active' AND b.status = 'active'
               AND EXISTS (SELECT 1 FROM product_composition_items pci WHERE pci.composition_id = pc.id)
             ORDER BY p.name"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Embed recipes for the given products as a JSON map for the create form preview.
     * @param array<int, int> $productIds
     */
    private function recipesForProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $placeholders = \implode(',', \array_fill(0, \count($productIds), '?'));
        $stmt = Db::pdo()->prepare(
            "SELECT pc.product_id,
                    pci.component_product_id,
                    pci.quantity AS recipe_quantity,
                    p.name AS component_name, p.unit AS component_unit,
                    p.moving_avg_cost, p.quantity_on_hand, p.status AS component_status
             FROM product_compositions pc
             JOIN product_composition_items pci ON pci.composition_id = pc.id
             JOIN products p ON p.id = pci.component_product_id
             WHERE pc.product_id IN ({$placeholders})
             ORDER BY pc.product_id, pci.id"
        );
        $stmt->execute($productIds);
        $byProduct = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $pid = (int) $row['product_id'];
            $byProduct[$pid][] = [
                'component_id'       => (int) $row['component_product_id'],
                'name'               => $row['component_name'],
                'unit'               => $row['component_unit'],
                'recipe_quantity'    => (float) $row['recipe_quantity'],
                'moving_avg_cost'    => (float) $row['moving_avg_cost'],
                'component_stock'    => (float) $row['quantity_on_hand'],
                'component_status'   => $row['component_status'],
            ];
        }
        return $byProduct;
    }
}
