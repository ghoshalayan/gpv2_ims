<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BrandRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\RateChangeRepository;
use App\Repositories\SubcategoryRepository;
use App\Services\RateChangeService;
use App\Support\Auth;
use App\Support\Flash;

/**
 * CR-4 — Rate Management. Bulk selling-price changes, category / subcategory-wise.
 *
 * Flow is always preview → confirm → apply. The preview is a GET so it can be reloaded,
 * shared and adjusted freely without writing anything; only the explicit confirm POST
 * changes prices, and it recomputes from current data rather than trusting the form.
 */
class RateChangeController
{
    public function __construct(
        private RateChangeService    $service       = new RateChangeService(),
        private RateChangeRepository $repo          = new RateChangeRepository(),
        private CategoryRepository   $categories    = new CategoryRepository(),
        private SubcategoryRepository $subcategories = new SubcategoryRepository(),
        private BrandRepository      $brands        = new BrandRepository(),
    ) {}

    /** The form, plus a live preview whenever a mode+value have been supplied. */
    public function index(): string
    {
        $filters = $this->filtersFromQuery();
        $mode    = (string) ($_GET['mode'] ?? '');
        $rawVal  = \trim((string) ($_GET['value'] ?? ''));
        $round   = !empty($_GET['round_to_rupee']);

        $preview = null;
        $error   = null;

        // Only preview once the user has actually asked for one.
        if ($mode !== '' && $rawVal !== '') {
            try {
                $preview = $this->service->preview($filters, $mode, (float) $rawVal, $round);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $p = \App\Support\Paginator::fromQuery();

        return view('rate-changes/index', [
            'title'         => 'Rate Management',
            'categories'    => $this->categories->listActive(),
            // listActive() carries category_id, so the UI can narrow the sub-category
            // list to the chosen category.
            'subcategories' => $this->subcategories->listActive(),
            'brands'        => $this->brands->listActive(),
            'filters'       => $filters,
            'mode'          => $mode,
            'value'         => $rawVal,
            'roundToRupee'  => $round,
            'preview'       => $preview,
            'previewError'  => $error,
            'remarks'       => (string) ($_GET['remarks'] ?? ''),
            'batches'       => $this->repo->paginateBatches($p->page, 10)['rows'],
        ]);
    }

    /** POST — apply the change. */
    public function apply(): string
    {
        $filters = $this->filtersFromPost();
        $mode    = (string) ($_POST['mode'] ?? '');
        $value   = (float) ($_POST['value'] ?? 0);
        $round   = !empty($_POST['round_to_rupee']);
        $remarks = \trim((string) ($_POST['remarks'] ?? '')) ?: null;

        try {
            $result = $this->service->apply($filters, $mode, $value, $round, $remarks, (int) Auth::id());
            Flash::success(\sprintf(
                'Rate change %s applied — %d product(s) re-priced. You can revert this batch if needed.',
                $result['batch_number'],
                $result['affected']
            ));
        } catch (\Throwable $e) {
            Flash::error('Could not apply the rate change: ' . $e->getMessage());
            return redirect($this->backToPreview($filters, $mode, $value, $round));
        }

        return redirect('/rate-changes');
    }

    /** POST — restore every product in a batch to its pre-change price. */
    public function revert(array $params): string
    {
        $id = (int) $params['id'];
        try {
            $n = $this->service->revert($id, (int) Auth::id());
            Flash::success("Rate change reverted — {$n} product price(s) restored.");
        } catch (\Throwable $e) {
            Flash::error('Could not revert: ' . $e->getMessage());
        }
        return redirect('/rate-changes');
    }

    /** Detail view: every product the batch touched, before and after. */
    public function show(array $params): string
    {
        $id    = (int) $params['id'];
        $batch = $this->repo->findBatch($id);
        if ($batch === null) {
            Flash::error('Rate change batch not found.');
            return redirect('/rate-changes');
        }
        return view('rate-changes/show', [
            'title' => 'Rate Change ' . $batch['batch_number'],
            'batch' => $batch,
            'lines' => $this->repo->linesForBatch($id),
        ]);
    }

    /** @return array{category_id:int|null, subcategory_id:int|null, brand_id:int|null} */
    private function filtersFromQuery(): array
    {
        return [
            'category_id'    => (int) ($_GET['category_id'] ?? 0) ?: null,
            'subcategory_id' => (int) ($_GET['subcategory_id'] ?? 0) ?: null,
            'brand_id'       => (int) ($_GET['brand_id'] ?? 0) ?: null,
        ];
    }

    private function filtersFromPost(): array
    {
        return [
            'category_id'    => (int) ($_POST['category_id'] ?? 0) ?: null,
            'subcategory_id' => (int) ($_POST['subcategory_id'] ?? 0) ?: null,
            'brand_id'       => (int) ($_POST['brand_id'] ?? 0) ?: null,
        ];
    }

    /** Send the user back to the same preview so a failed apply loses no input. */
    private function backToPreview(array $filters, string $mode, float $value, bool $round): string
    {
        $q = \array_filter([
            'category_id'    => $filters['category_id'],
            'subcategory_id' => $filters['subcategory_id'],
            'brand_id'       => $filters['brand_id'],
            'mode'           => $mode,
            'value'          => $value,
            'round_to_rupee' => $round ? 1 : null,
        ], static fn($v) => $v !== null && $v !== '');
        return '/rate-changes' . ($q === [] ? '' : '?' . \http_build_query($q));
    }
}
