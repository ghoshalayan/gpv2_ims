<?php
declare(strict_types=1);

namespace App\Support;

use App\Repositories\ProductUnitRepository;

/**
 * Converts a quantity ENTERED in some unit into the product's BASE stock unit.
 *
 * Design: only quantity is converted. Price stays per base unit, so the line
 * invariant (subtotal = quantity × unit_price) and the derived
 * landed_cost_per_unit (= subtotal / quantity) remain exact — no rounding drift
 * is introduced into money or cost-of-goods. The entry unit is recorded for
 * display only.
 */
class UnitConversion
{
    public function __construct(
        private ProductUnitRepository $productUnits = new ProductUnitRepository(),
    ) {}

    /**
     * Factor: how many base units one of $unitId equals for this product.
     * Returns 1.0 for the base unit (or unitId 0/empty), the configured factor
     * for a valid override, or null if the unit isn't valid for the product.
     *
     * @param array<string,mixed> $product a product row (needs id, unit_id)
     */
    public function factorToBase(array $product, int $unitId): ?float
    {
        $baseUnitId = (int) ($product['unit_id'] ?? 0);
        if ($unitId <= 0 || $unitId === $baseUnitId) {
            return 1.0;
        }
        $pu = $this->productUnits->findByProductAndUnit((int) $product['id'], $unitId);
        if ($pu === null) {
            return null;
        }
        $f = (float) $pu['factor_to_product_base'];
        return $f > 0 ? $f : null;
    }

    /**
     * Resolve an entry (unitId, enteredQty) into the base quantity to store,
     * plus the entry columns (null when the base unit was used).
     *
     * @param array<string,mixed> $product
     * @return array{base_qty: float, entry_unit_id: ?int, entry_quantity: ?float}
     */
    public function resolve(array $product, int $unitId, float $enteredQty): array
    {
        $factor = $this->factorToBase($product, $unitId);
        if ($factor === null) {
            throw new \RuntimeException('Selected unit is not valid for this product.');
        }
        $baseUnitId = (int) ($product['unit_id'] ?? 0);
        $isAlternate = $unitId > 0 && $unitId !== $baseUnitId;

        return [
            'base_qty'       => \round($enteredQty * $factor, 3),
            'entry_unit_id'  => $isAlternate ? $unitId : null,
            'entry_quantity' => $isAlternate ? \round($enteredQty, 3) : null,
        ];
    }
}
