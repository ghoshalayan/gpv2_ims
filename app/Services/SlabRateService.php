<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BrandSlabRepository;
use App\Support\Settings;

/**
 * CR-1b — resolves the purchase rate for a product at a given quantity.
 *
 * This service is a SUGGESTION ENGINE and nothing more. Its output prefills the
 * unit_price field on the purchase line form; it is never consulted by
 * PurchaseInvoiceService, never applied to a saved line, and never rewrites a posted
 * document. That is what makes the feature safe to switch on and off on a live system:
 * with the flag off, or with no slab configured, callers get null and the form behaves
 * exactly as it does today.
 */
class SlabRateService
{
    public function __construct(
        private BrandSlabRepository $repo = new BrandSlabRepository(),
    ) {}

    /** Master switch — `enable_brand_slab_rates`, seeded off. */
    public function enabled(): bool
    {
        return Settings::bool('enable_brand_slab_rates', false);
    }

    /**
     * The rate for this product at this quantity, or null when there is nothing to
     * suggest (feature off, quantity non-positive, no slab covers it, or no rate is
     * configured for the covering slab).
     */
    public function resolve(int $productId, float $quantity): ?float
    {
        if (!$this->enabled() || $quantity <= 0) {
            return null;
        }
        $band = $this->resolveBand($productId, $quantity);
        return $band === null ? null : $band['rate'];
    }

    /**
     * As resolve(), but returns the whole band so the UI can name the slab it applied
     * ("Under 50", "50 and above") rather than silently changing a price.
     *
     * @return array{min:float, max:float|null, rate:float, label:string}|null
     */
    public function resolveBand(int $productId, float $quantity): ?array
    {
        if (!$this->enabled() || $quantity <= 0) {
            return null;
        }
        foreach ($this->repo->ratesForProductWithBands($productId) as $band) {
            if ($this->covers($band, $quantity)) {
                return $band;
            }
        }
        return null;
    }

    /**
     * Per-brand lookup table for the purchase line form. Empty when the feature is off,
     * so the view has nothing to act on.
     *
     * @return array<int, array<int, array{min:float, max:float|null, rate:float, label:string}>>
     */
    public function slabMapForBrand(int $brandId): array
    {
        if (!$this->enabled()) {
            return [];
        }
        return $this->repo->slabMapForBrand($brandId);
    }

    /**
     * Validate a slab definition before saving.
     * @return array<int, string> error messages; empty means valid
     */
    public function validateSlab(int $brandId, string $label, float $minQty, ?float $maxQty, ?int $excludeId = null): array
    {
        $errors = [];

        if (\trim($label) === '') {
            $errors[] = 'Label is required.';
        }
        if ($minQty < 0) {
            $errors[] = 'Minimum quantity cannot be negative.';
        }
        if ($maxQty !== null && $maxQty <= $minQty) {
            $errors[] = 'Maximum quantity must be greater than the minimum.';
        }

        // Overlapping bands would make the resolved rate depend on row order, which is
        // exactly the kind of silent ambiguity a pricing rule must not have.
        if ($errors === []) {
            $clashes = $this->repo->overlappingSlabs($brandId, $minQty, $maxQty, $excludeId);
            if ($clashes !== []) {
                $names = \array_map(static fn($s) => $s['label'], \array_slice($clashes, 0, 3));
                $errors[] = 'This range overlaps an existing slab: ' . \implode(', ', $names) . '.';
            }
        }

        return $errors;
    }

    /** Half-open [min, max); null max is unbounded. */
    private function covers(array $band, float $qty): bool
    {
        if ($qty < $band['min']) {
            return false;
        }
        return $band['max'] === null || $qty < $band['max'];
    }
}
