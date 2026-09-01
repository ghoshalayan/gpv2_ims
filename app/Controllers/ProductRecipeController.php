<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ProductCompositionRepository;
use App\Repositories\ProductRepository;
use App\Support\Auth;
use App\Support\Db;
use App\Support\Flash;

class ProductRecipeController
{
    public function __construct(
        private ProductRepository            $products     = new ProductRepository(),
        private ProductCompositionRepository $compositions = new ProductCompositionRepository(),
    ) {}

    public function show(array $params): string
    {
        $productId = (int) $params['id'];
        $product = $this->products->find($productId);
        if ($product === null) {
            Flash::error('Product not found.');
            return redirect('/products');
        }
        if ((int) $product['is_composite'] !== 1) {
            Flash::warning("'{$product['name']}' is not flagged as a composite. Enable the Composite checkbox on the product first.");
            return redirect("/products/{$productId}/edit");
        }

        $compositionId = $this->compositions->ensureForProduct($productId, (int) Auth::id());
        $composition   = $this->compositions->findByProduct($productId);
        $items         = $this->compositions->itemsByComposition($compositionId);

        // Candidate components: all active products except this one and any that would create a cycle.
        $candidates = $this->selectableComponents($productId, $items);

        return view('products/recipe', [
            'title'         => "Recipe — {$product['name']}",
            'product'       => $product,
            'composition'   => $composition,
            'items'         => $items,
            'candidates'    => $candidates,
            'rawCost'       => $this->computeRawCost($items),
        ]);
    }

    public function updateNotes(array $params): string
    {
        $productId = (int) $params['id'];
        $composition = $this->compositions->findByProduct($productId);
        if ($composition === null) {
            Flash::error('Composition not found.');
            return redirect("/products/{$productId}/recipe");
        }
        $notes = \trim((string) ($_POST['notes'] ?? '')) ?: null;
        $this->compositions->updateNotes((int) $composition['id'], $notes, (int) Auth::id());
        Flash::success('Recipe notes updated.');
        return redirect("/products/{$productId}/recipe");
    }

    public function addItem(array $params): string
    {
        $productId   = (int) $params['id'];
        $componentId = (int) ($_POST['component_product_id'] ?? 0);
        $quantity    = (float) ($_POST['quantity'] ?? 0);
        $notes       = \trim((string) ($_POST['notes'] ?? '')) ?: null;

        $product = $this->products->find($productId);
        if ($product === null || (int) $product['is_composite'] !== 1) {
            Flash::error('Parent product not found or is not a composite.');
            return redirect('/products');
        }
        if ($componentId <= 0) {
            Flash::error('Pick a component product.');
            return redirect("/products/{$productId}/recipe");
        }
        if ($quantity <= 0) {
            Flash::error('Component quantity must be > 0.');
            return redirect("/products/{$productId}/recipe");
        }
        if ($componentId === $productId) {
            Flash::error('A composite cannot include itself as a component.');
            return redirect("/products/{$productId}/recipe");
        }
        if ($this->compositions->wouldCreateCycle($productId, $componentId)) {
            Flash::error('That component would create a circular recipe dependency.');
            return redirect("/products/{$productId}/recipe");
        }

        $compositionId = $this->compositions->ensureForProduct($productId, (int) Auth::id());
        if ($this->compositions->hasComponent($compositionId, $componentId)) {
            Flash::error('That component is already in this recipe.');
            return redirect("/products/{$productId}/recipe");
        }

        $this->compositions->addItem($compositionId, $componentId, $quantity, $notes);
        Flash::success('Component added.');
        return redirect("/products/{$productId}/recipe");
    }

    public function deleteItem(array $params): string
    {
        $productId = (int) $params['id'];
        $itemId    = (int) $params['itemId'];

        $composition = $this->compositions->findByProduct($productId);
        if ($composition === null) {
            Flash::error('Composition not found.');
            return redirect("/products/{$productId}/recipe");
        }
        $this->compositions->deleteItem($itemId, (int) $composition['id']);
        Flash::success('Component removed.');
        return redirect("/products/{$productId}/recipe");
    }

    /**
     * Edit a recipe row in place — change quantity and/or notes. Component
     * product is locked (delete + re-add to swap).
     */
    public function updateItem(array $params): string
    {
        $productId = (int) $params['id'];
        $itemId    = (int) $params['itemId'];
        $quantity  = (float) ($_POST['quantity'] ?? 0);
        $notes     = \trim((string) ($_POST['notes'] ?? '')) ?: null;

        $composition = $this->compositions->findByProduct($productId);
        if ($composition === null) {
            Flash::error('Composition not found.');
            return redirect("/products/{$productId}/recipe");
        }
        if ($quantity <= 0) {
            Flash::error('Component quantity must be > 0.');
            return redirect("/products/{$productId}/recipe");
        }
        $item = $this->compositions->findItem($itemId, (int) $composition['id']);
        if ($item === null) {
            Flash::error('Recipe row not found.');
            return redirect("/products/{$productId}/recipe");
        }

        $this->compositions->updateItem($itemId, (int) $composition['id'], $quantity, $notes);
        Flash::success('Component updated.');
        return redirect("/products/{$productId}/recipe");
    }

    /** Compute raw cost per 1 unit of the composite at moving-avg and latest prices. */
    private function computeRawCost(array $items): array
    {
        $movingAvg = 0.0;
        $latest    = 0.0;
        foreach ($items as $it) {
            $movingAvg += (float) $it['quantity'] * (float) $it['moving_avg_cost'];
            $latest    += (float) $it['quantity'] * (float) $it['latest_purchase_landed_cost'];
        }
        return [
            'moving_avg' => \round($movingAvg, 2),
            'latest'     => \round($latest, 2),
        ];
    }

    /** Active non-composite products of any brand, excluding the parent itself. */
    private function selectableComponents(int $parentProductId, array $existingItems): array
    {
        $existingIds = \array_column($existingItems, 'component_product_id');
        $existingIds[] = $parentProductId;
        $placeholders = \implode(',', \array_fill(0, \count($existingIds), '?'));

        $stmt = Db::pdo()->prepare(
            "SELECT p.id, p.name, p.unit, p.moving_avg_cost, p.latest_purchase_landed_cost,
                    p.quantity_on_hand, b.name AS brand_name, p.is_composite
             FROM products p
             JOIN brands b ON b.id = p.brand_id
             WHERE p.status = 'active' AND b.status = 'active'
               AND p.id NOT IN ({$placeholders})
             ORDER BY p.name"
        );
        $stmt->execute($existingIds);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
