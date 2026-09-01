<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BrandRepository;
use App\Repositories\BrandSlabRepository;
use App\Services\SlabRateService;
use App\Support\ActivityLog;
use App\Support\Auth;
use App\Support\Flash;

/**
 * CR-1b — manage a brand's purchase quantity slabs and the per-product rates in them.
 *
 * Everything here edits only this feature's own two tables. No invoice, stock or cost
 * column is reachable from these actions.
 */
class BrandSlabController
{
    public function __construct(
        private BrandSlabRepository $repo    = new BrandSlabRepository(),
        private BrandRepository     $brands  = new BrandRepository(),
        private SlabRateService     $service = new SlabRateService(),
    ) {}

    /** The slab list + the product × slab rate grid for one brand. */
    public function index(array $params): string
    {
        $brandId = (int) $params['id'];
        $brand   = $this->brands->find($brandId);
        if ($brand === null) {
            Flash::error('Brand not found.');
            return redirect('/brands');
        }

        return view('brand-slabs/index', [
            'title'    => 'Purchase Rate Slabs · ' . $brand['name'],
            'brand'    => $brand,
            'slabs'    => $this->repo->slabsForBrand($brandId),
            'products' => $this->repo->productsWithRates($brandId),
            'enabled'  => $this->service->enabled(),
        ]);
    }

    /** POST — add a slab. */
    public function storeSlab(array $params): string
    {
        $brandId = (int) $params['id'];
        [$label, $minQty, $maxQty] = $this->slabInput();

        $errors = $this->service->validateSlab($brandId, $label, $minQty, $maxQty);
        if ($errors !== []) {
            Flash::error(\implode(' ', $errors));
            return redirect("/brands/{$brandId}/slabs");
        }

        $id = $this->repo->insertSlab([
            'brand_id' => $brandId,
            'label'    => $label,
            'min_qty'  => $minQty,
            'max_qty'  => $maxQty,
        ], (int) Auth::id());

        ActivityLog::log('create', 'brand_purchase_slab', $id, [
            'label' => $label,
            'after' => ['brand_id' => $brandId, 'min_qty' => $minQty, 'max_qty' => $maxQty],
        ]);

        Flash::success("Slab '{$label}' added.");
        return redirect("/brands/{$brandId}/slabs");
    }

    /** POST — edit a slab (label, range, active/inactive). */
    public function updateSlab(array $params): string
    {
        $slabId = (int) $params['slabId'];
        $slab   = $this->repo->findSlab($slabId);
        if ($slab === null) {
            Flash::error('Slab not found.');
            return redirect('/brands');
        }
        $brandId = (int) $slab['brand_id'];

        [$label, $minQty, $maxQty] = $this->slabInput();
        $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

        $errors = $this->service->validateSlab($brandId, $label, $minQty, $maxQty, $slabId);
        if ($errors !== []) {
            Flash::error(\implode(' ', $errors));
            return redirect("/brands/{$brandId}/slabs");
        }

        $this->repo->updateSlab($slabId, [
            'label' => $label, 'min_qty' => $minQty, 'max_qty' => $maxQty, 'status' => $status,
        ], (int) Auth::id());

        ActivityLog::log('update', 'brand_purchase_slab', $slabId, [
            'label'  => $label,
            'before' => ['min_qty' => $slab['min_qty'], 'max_qty' => $slab['max_qty'], 'status' => $slab['status']],
            'after'  => ['min_qty' => $minQty, 'max_qty' => $maxQty, 'status' => $status],
        ]);

        Flash::success("Slab '{$label}' updated.");
        return redirect("/brands/{$brandId}/slabs");
    }

    /** POST — delete a slab and the rates recorded under it. */
    public function deleteSlab(array $params): string
    {
        $slabId = (int) $params['slabId'];
        $slab   = $this->repo->findSlab($slabId);
        if ($slab === null) {
            Flash::error('Slab not found.');
            return redirect('/brands');
        }
        $brandId = (int) $slab['brand_id'];

        $this->repo->deleteSlab($slabId);
        ActivityLog::log('delete', 'brand_purchase_slab', $slabId, [
            'label'  => (string) $slab['label'],
            'before' => ['min_qty' => $slab['min_qty'], 'max_qty' => $slab['max_qty']],
            'notes'  => 'Slab deleted; per-product rates under it were removed with it.',
        ]);

        Flash::success("Slab '{$slab['label']}' deleted.");
        return redirect("/brands/{$brandId}/slabs");
    }

    /**
     * POST — save the whole product × slab rate grid in one submit.
     * A blank cell clears that product's rate for that slab.
     */
    public function saveRates(array $params): string
    {
        $brandId = (int) $params['id'];
        $brand   = $this->brands->find($brandId);
        if ($brand === null) {
            Flash::error('Brand not found.');
            return redirect('/brands');
        }

        // rates[productId][slabId] = value
        $grid    = $_POST['rates'] ?? [];
        $userId  = (int) Auth::id();
        $changed = 0;

        // Only accept ids that actually belong to this brand, so a crafted post cannot
        // write rates onto another brand's products or slabs.
        $validSlabs    = \array_column($this->repo->slabsForBrand($brandId), 'id');
        $validProducts = \array_column($this->repo->productsWithRates($brandId), 'id');
        $validSlabs    = \array_map('intval', $validSlabs);
        $validProducts = \array_map('intval', $validProducts);

        foreach ((array) $grid as $productId => $bySlab) {
            $productId = (int) $productId;
            if (!\in_array($productId, $validProducts, true)) {
                continue;
            }
            foreach ((array) $bySlab as $slabId => $raw) {
                $slabId = (int) $slabId;
                if (!\in_array($slabId, $validSlabs, true)) {
                    continue;
                }
                $raw  = \trim((string) $raw);
                $rate = $raw === '' ? null : (float) $raw;
                if ($rate !== null && $rate < 0) {
                    continue;   // never store a negative purchase rate
                }
                $this->repo->setRate($productId, $slabId, $rate, $userId);
                $changed++;
            }
        }

        ActivityLog::log('update', 'brand_purchase_slab_rates', $brandId, [
            'label' => (string) $brand['name'],
            'after' => ['cells_written' => $changed],
            'notes' => 'Slab rate grid saved.',
        ]);

        Flash::success("Rates saved for {$brand['name']}.");
        return redirect("/brands/{$brandId}/slabs");
    }

    /** @return array{0:string, 1:float, 2:float|null} */
    private function slabInput(): array
    {
        $label  = \trim((string) ($_POST['label'] ?? ''));
        $minQty = (float) ($_POST['min_qty'] ?? 0);
        $rawMax = \trim((string) ($_POST['max_qty'] ?? ''));
        // Blank max means "and above" — an unbounded top slab.
        $maxQty = $rawMax === '' ? null : (float) $rawMax;
        return [$label, $minQty, $maxQty];
    }
}
