<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ProductRepository;
use App\Repositories\ProductUnitRepository;
use App\Repositories\UnitRepository;
use App\Support\ActivityLog;
use App\Support\Db;
use App\Support\Flash;

/**
 * Per-product unit overrides — the buy/sell units a particular product accepts
 * in addition to its base stock unit. E.g. product is stocked in PCS but bought
 * in CASE (1 case = 12 pcs). The conversion is stored per (product, unit) so
 * each product's "case" can have its own count.
 */
class ProductUnitController
{
    public function __construct(
        private ProductUnitRepository $repo     = new ProductUnitRepository(),
        private ProductRepository     $products = new ProductRepository(),
        private UnitRepository        $units    = new UnitRepository(),
    ) {}

    public function store(array $params): string
    {
        $productId = (int) $params['id'];
        $product   = $this->products->find($productId);
        if ($product === null) {
            Flash::error('Product not found.');
            return redirect('/products');
        }

        $unitId = (int) ($_POST['unit_id'] ?? 0);
        $factor = (float) ($_POST['factor_to_product_base'] ?? 0);
        $errs   = [];
        if ($unitId <= 0) { $errs[] = 'Pick a unit.'; }
        if ($factor <= 0) { $errs[] = 'Conversion factor must be > 0.'; }
        if ($unitId === (int) $product['unit_id']) {
            $errs[] = "That's already this product's stock unit — no override needed.";
        }
        if ($errs === [] && $this->repo->existsForProduct($productId, $unitId)) {
            $errs[] = 'That unit is already configured for this product.';
        }
        if ($errs !== []) {
            flash_old($_POST);
            Flash::error($errs[0]);
            return redirect("/products/{$productId}/edit");
        }

        $isPurchase     = !empty($_POST['is_purchase']);
        $isSale         = !empty($_POST['is_sale']);
        $isDefPurchase  = !empty($_POST['is_default_purchase']);
        $isDefSale      = !empty($_POST['is_default_sale']);

        $notes = \trim((string) ($_POST['notes'] ?? '')) ?: null;
        Db::transaction(function () use ($productId, $unitId, $factor, $isPurchase, $isSale, $isDefPurchase, $isDefSale, $notes): void {
            $newId = $this->repo->insert([
                'product_id'              => $productId,
                'unit_id'                 => $unitId,
                'factor_to_product_base'  => $factor,
                'is_purchase'             => $isPurchase,
                'is_sale'                 => $isSale,
                'is_default_purchase'     => $isDefPurchase,
                'is_default_sale'         => $isDefSale,
                'notes'                   => $notes,
            ]);
            if ($isDefPurchase) { $this->repo->clearDefaultsForProduct($productId, 'purchase', $newId); }
            if ($isDefSale)     { $this->repo->clearDefaultsForProduct($productId, 'sale',     $newId); }
        });

        ActivityLog::log('create', 'product_unit', null, [
            'label' => $product['name'],
            'notes' => "unit_id={$unitId}, factor={$factor}",
        ]);
        Flash::success('Unit added to product.');
        return redirect("/products/{$productId}/edit");
    }

    public function update(array $params): string
    {
        $productId = (int) $params['id'];
        $puId      = (int) $params['puId'];
        $row = $this->repo->find($puId);
        if ($row === null || (int) $row['product_id'] !== $productId) {
            Flash::error('Unit override not found for this product.');
            return redirect("/products/{$productId}/edit");
        }

        $factor = (float) ($_POST['factor_to_product_base'] ?? 0);
        if ($factor <= 0) {
            Flash::error('Conversion factor must be > 0.');
            return redirect("/products/{$productId}/edit");
        }

        $isPurchase    = !empty($_POST['is_purchase']);
        $isSale        = !empty($_POST['is_sale']);
        $isDefPurchase = !empty($_POST['is_default_purchase']);
        $isDefSale     = !empty($_POST['is_default_sale']);

        $notes = \trim((string) ($_POST['notes'] ?? '')) ?: null;
        Db::transaction(function () use ($puId, $productId, $factor, $isPurchase, $isSale, $isDefPurchase, $isDefSale, $notes): void {
            $this->repo->update($puId, [
                'factor_to_product_base'  => $factor,
                'is_purchase'             => $isPurchase,
                'is_sale'                 => $isSale,
                'is_default_purchase'     => $isDefPurchase,
                'is_default_sale'         => $isDefSale,
                'notes'                   => $notes,
            ]);
            if ($isDefPurchase) { $this->repo->clearDefaultsForProduct($productId, 'purchase', $puId); }
            if ($isDefSale)     { $this->repo->clearDefaultsForProduct($productId, 'sale',     $puId); }
        });

        Flash::success('Unit override updated.');
        return redirect("/products/{$productId}/edit");
    }

    public function destroy(array $params): string
    {
        $productId = (int) $params['id'];
        $puId      = (int) $params['puId'];
        $row = $this->repo->find($puId);
        if ($row === null || (int) $row['product_id'] !== $productId) {
            Flash::error('Unit override not found for this product.');
            return redirect("/products/{$productId}/edit");
        }
        $this->repo->delete($puId);
        Flash::success('Unit override removed.');
        return redirect("/products/{$productId}/edit");
    }
}
