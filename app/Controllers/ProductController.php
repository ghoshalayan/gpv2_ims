<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BrandRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductCompositionRepository;
use App\Repositories\ProductPriceHistoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SubcategoryRepository;
use App\Services\InventoryService;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\Validator;

class ProductController
{
    public function __construct(
        private ProductRepository              $repo          = new ProductRepository(),
        private BrandRepository                $brands        = new BrandRepository(),
        private CategoryRepository             $categories    = new CategoryRepository(),
        private SubcategoryRepository          $subcategories = new SubcategoryRepository(),
        private ProductPriceHistoryRepository  $priceHistory  = new ProductPriceHistoryRepository(),
        private ProductCompositionRepository   $compositions  = new ProductCompositionRepository(),
        private InventoryService               $inventory     = new InventoryService(),
        private \App\Repositories\UnitRepository        $units        = new \App\Repositories\UnitRepository(),
        private \App\Repositories\ProductUnitRepository $productUnits = new \App\Repositories\ProductUnitRepository(),
    ) {}

    public function index(): string
    {
        $p = \App\Support\Paginator::fromQuery();

        // CR-9 — category, stock state and price band on top of the existing filters.
        $stockState = (string) ($_GET['stock_state'] ?? '');
        if (!\in_array($stockState, ['in_stock', 'low_stock', 'out_of_stock'], true)) {
            $stockState = '';
        }
        // Blank means "no bound", which is different from 0 — so test the raw string.
        $priceMin = \trim((string) ($_GET['price_min'] ?? ''));
        $priceMax = \trim((string) ($_GET['price_max'] ?? ''));

        $filters = [
            'brand_id'       => (int) ($_GET['brand_id'] ?? 0) ?: null,
            'subcategory_id' => (int) ($_GET['subcategory_id'] ?? 0) ?: null,
            'category_id'    => (int) ($_GET['category_id'] ?? 0) ?: null,
            'status'         => (string) ($_GET['status'] ?? ''),
            'stock_state'    => $stockState,
            'price_min'      => $priceMin === '' ? null : (float) $priceMin,
            'price_max'      => $priceMax === '' ? null : (float) $priceMax,
        ];
        $result = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);

        // Drop nulls/blanks so pagination links stay clean.
        $queryFilters = \array_filter($filters, static fn($v) => $v !== null && $v !== '');

        return view('products/index', [
            'title'      => 'Products',
            'products'   => $result['rows'],
            'brands'     => $this->brands->listActive(),
            'categories' => $this->categories->listActive(),
            'filters'    => $filters,
            'pg'         => $p->buildContext($result['total'], url('/products'), $queryFilters + ['q' => $p->search]),
        ]);
    }

    public function create(): string
    {
        $brands     = $this->brands->listActive();
        $categories = $this->categories->listActive();
        if ($brands === [] || $categories === []) {
            Flash::warning('Create at least one active Brand and one active Category (with a Sub-Category) before adding Products.');
            return redirect($brands === [] ? '/brands/create' : '/categories/create');
        }
        return view('products/create', [
            'title'                  => 'New Product',
            'brands'                 => $brands,
            'categories'             => $categories,
            'subcategoriesByCategory' => $this->groupSubcategoriesByCategory($this->subcategories->listActive()),
            'componentCandidates'    => $this->componentCandidates(),
            'unitsList'              => $this->units->listActive(),
        ]);
    }

    /** Active products suitable as recipe components (all-active any brand/category). */
    private function componentCandidates(): array
    {
        return \App\Support\Db::pdo()->query(
            "SELECT p.id, p.name, p.unit, p.moving_avg_cost, p.latest_purchase_landed_cost,
                    p.quantity_on_hand, b.name AS brand_name, p.is_composite
             FROM products p
             JOIN brands b ON b.id = p.brand_id
             WHERE p.status = 'active' AND b.status = 'active'
             ORDER BY p.name"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function store(): string
    {
        [$data, $errorRedirect] = $this->validateInput($_POST, '/products/create', isCreate: true);
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($data['name'])) {
            flash_old($_POST);
            Flash::error("A product named '{$data['name']}' already exists.");
            return redirect('/products/create');
        }

        // Parse inline recipe (only when is_composite = 1). Each item: component_product_id + quantity.
        $recipeItems = [];
        if (($data['is_composite'] ?? 0) === 1) {
            $raw = $_POST['recipe'] ?? [];
            if (\is_array($raw)) {
                $seen = [];
                foreach ($raw as $item) {
                    $cid = (int)   ($item['component_product_id'] ?? 0);
                    $q   = (float) ($item['quantity']             ?? 0);
                    if ($cid <= 0 || $q <= 0) { continue; }
                    if (isset($seen[$cid])) {
                        flash_old($_POST);
                        Flash::error('Duplicate component in recipe — combine quantities instead.');
                        return redirect('/products/create');
                    }
                    $seen[$cid] = true;
                    $recipeItems[] = ['component_product_id' => $cid, 'quantity' => $q];
                }
            }
            if ($recipeItems === []) {
                flash_old($_POST);
                Flash::error('A composite product needs at least one recipe component.');
                return redirect('/products/create');
            }
        }

        $userId = (int) Auth::id();

        try {
            $productId = \App\Support\Db::transaction(function () use ($data, $userId, $recipeItems) {
                $productId = $this->repo->insert($data, $userId);

                // Opening price-history entry if a latest cost was captured.
                if (($data['opening_latest_cost'] ?? 0) > 0) {
                    $this->priceHistory->insert(
                        $productId,
                        (float) $data['opening_latest_cost'],
                        'opening',
                        null, null,
                        ($data['opening_quantity'] ?? 0) > 0
                            ? 'Opening stock cost at product creation'
                            : 'Opening cost benchmark (no opening stock)',
                        $userId
                    );
                }

                // Composite recipe — transactional so product + recipe save together (or not at all).
                if ($recipeItems !== []) {
                    $compositionId = $this->compositions->ensureForProduct($productId, $userId);
                    foreach ($recipeItems as $it) {
                        // New product can't be its own component by id (it didn't exist); skip extra cycle BFS.
                        $this->compositions->addItem(
                            $compositionId,
                            (int) $it['component_product_id'],
                            (float) $it['quantity'],
                            null
                        );
                    }
                }
                return $productId;
            });
        } catch (\Throwable $e) {
            Flash::error('Could not create product: ' . $e->getMessage());
            return redirect('/products/create');
        }

        $msg = "Product '{$data['name']}' created.";
        if ($recipeItems !== []) {
            $msg .= ' Recipe saved with ' . \count($recipeItems) . ' component' . (\count($recipeItems) === 1 ? '' : 's') . '.';
        }
        Flash::success($msg);
        return redirect('/products');
    }

    public function edit(array $params): string
    {
        $product = $this->requireProduct((int) $params['id']);
        if (\is_string($product)) {
            return $product;
        }

        // For composites, back-calculate per-unit costs from current recipe components.
        $derivedCosts = null;
        if ((int) ($product['is_composite'] ?? 0) === 1) {
            $composition = $this->compositions->findByProduct((int) $product['id']);
            if ($composition !== null) {
                $items = $this->compositions->itemsByComposition((int) $composition['id']);
                $movingAvgSum = 0.0;
                $latestSum    = 0.0;
                foreach ($items as $it) {
                    $movingAvgSum += (float) $it['quantity'] * (float) $it['moving_avg_cost'];
                    $latestSum    += (float) $it['quantity'] * (float) $it['latest_purchase_landed_cost'];
                }
                $derivedCosts = [
                    'moving_avg'      => \round($movingAvgSum, 2),
                    'latest'          => \round($latestSum, 2),
                    'component_count' => \count($items),
                ];
            }
        }

        // Paginated history (moving-avg breakdown). Search keys 'q'/'page'/'per_page'
        // come via the standard Paginator; the URL stays at /products/{id}/edit.
        $p = \App\Support\Paginator::fromQuery();
        $hist = $this->priceHistory->paginate(
            (int) $product['id'], $p->page, $p->perPage, $p->search
        );

        return view('products/edit', [
            'title'                   => 'Edit Product',
            'product'                 => $product,
            'brands'                  => $this->brands->listActive(),
            'categories'              => $this->categories->listActiveOrIncluding((int) $product['category_id']),
            'subcategoriesByCategory' => $this->groupSubcategoriesByCategory(
                $this->subcategoriesForEdit((int) $product['subcategory_id'])
            ),
            'priceHistory'            => $hist['rows'],
            'historyPg'               => $p->buildContext(
                $hist['total'],
                url('/products/' . (int) $product['id'] . '/edit'),
                ['q' => $p->search]
            ),
            'derivedCosts'            => $derivedCosts,
            'unitsList'               => $this->units->listActive(),
            'productUnits'            => $this->productUnits->byProduct((int) $product['id']),
        ]);
    }

    public function updateLatestCost(array $params): string
    {
        $id = (int) $params['id'];
        $product = $this->requireProduct($id);
        if (\is_string($product)) {
            return $product;
        }
        $newCost = (float) ($_POST['latest_cost'] ?? -1);
        $notes   = \trim((string) ($_POST['notes'] ?? '')) ?: null;
        if ($newCost < 0) {
            Flash::error('Latest cost cannot be negative.');
            return redirect("/products/{$id}/edit");
        }
        try {
            $this->inventory->setLatestPrice($id, $newCost, (int) Auth::id(), $notes);
            Flash::success(sprintf(
                "Latest cost for '%s' updated to ₹%0.4f.",
                $product['name'],
                $newCost
            ));
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }
        return redirect("/products/{$id}/edit");
    }

    public function update(array $params): string
    {
        $id      = (int) $params['id'];
        $product = $this->requireProduct($id);
        if (\is_string($product)) {
            return $product;
        }

        [$data, $errorRedirect] = $this->validateInput($_POST, "/products/{$id}/edit", isCreate: false);
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($data['name'], $id)) {
            flash_old($_POST);
            Flash::error("Another product named '{$data['name']}' already exists.");
            return redirect("/products/{$id}/edit");
        }

        $this->repo->update($id, $data, (int) Auth::id());
        Flash::success("Product '{$data['name']}' updated.");
        return redirect('/products');
    }

    public function toggleStatus(array $params): string
    {
        $id      = (int) $params['id'];
        $product = $this->requireProduct($id);
        if (\is_string($product)) {
            return $product;
        }
        $newStatus = $product['status'] === 'active' ? 'inactive' : 'active';
        $this->repo->setStatus($id, $newStatus, (int) Auth::id());
        Flash::success("Product '{$product['name']}' is now {$newStatus}.");
        return redirect('/products');
    }

    /** @return array{0:array, 1:?string} */
    private function validateInput(array $input, string $redirectTo, bool $isCreate): array
    {
        // Resolve the stock unit from the units master. Prefer the posted unit_id;
        // fall back to matching the legacy free-text 'unit' against units.code; else PCS.
        // Both unit_id (FK, NOT NULL) and unit (varchar, kept for back-compat / printouts)
        // are set from the resolved row so they never drift.
        $unitId  = (int) ($input['unit_id'] ?? 0);
        $unitRow = $unitId > 0 ? $this->units->find($unitId) : null;
        if ($unitRow === null) {
            $code    = \strtoupper(\trim((string) ($input['unit'] ?? '')));
            $unitRow = ($code !== '' ? $this->units->findByCode($code) : null)
                    ?? $this->units->findByCode('PCS');
        }

        $data = [
            'name'                  => \trim((string) ($input['name'] ?? '')),
            'brand_id'              => (int) ($input['brand_id'] ?? 0),
            'category_id'           => (int) ($input['category_id'] ?? 0),
            'subcategory_id'        => (int) ($input['subcategory_id'] ?? 0),
            'unit_id'               => $unitRow !== null ? (int) $unitRow['id'] : 0,
            'unit'                  => $unitRow !== null ? (string) $unitRow['code'] : 'PCS',
            'hsn_code'              => \trim((string) ($input['hsn_code'] ?? '')) ?: null,
            'tax_rate'              => (float) ($input['tax_rate'] ?? 0),
            'default_selling_price' => (float) ($input['default_selling_price'] ?? 0),
            'low_stock_threshold'   => (int)   ($input['low_stock_threshold'] ?? 0),
            'description'           => \trim((string) ($input['description'] ?? '')) ?: null,
            'is_composite'          => !empty($input['is_composite']) ? 1 : 0,
            'is_irreversible'       => !empty($input['is_irreversible']) ? 1 : 0,
            'expose_components'     => !empty($input['expose_components']) ? 1 : 0,
        ];
        // A non-composite product can't carry the composite sub-flags.
        if ($data['is_composite'] === 0) {
            $data['is_irreversible']   = 0;
            $data['expose_components'] = 0;
        }
        if ($isCreate) {
            $data['opening_quantity']    = (int)   ($input['opening_quantity']    ?? 0);
            $data['opening_avg_cost']    = (float) ($input['opening_avg_cost']    ?? 0);
            // Latest purchase cost — user may leave blank to default to opening cost.
            $latestRaw = \trim((string) ($input['opening_latest_cost'] ?? ''));
            $data['opening_latest_cost'] = $latestRaw === ''
                ? $data['opening_avg_cost']
                : (float) $latestRaw;
        }

        $v = Validator::make([
                'name'           => $data['name'],
                'brand_id'       => (string) $data['brand_id'],
                'subcategory_id' => (string) $data['subcategory_id'],
                'unit'           => $data['unit'],
            ])
            ->required('name')
            ->max('name', 150)
            ->required('brand_id', 'Brand is required.')
            ->required('subcategory_id', 'Sub-Category is required.')
            ->required('unit')
            ->max('unit', 50);

        $errors = [];
        if ($data['unit_id'] <= 0) {
            $errors[] = 'Could not resolve a unit. Create at least one Unit (Product Management → Units) first.';
        }
        if ($data['tax_rate'] < 0 || $data['tax_rate'] > 100) {
            $errors[] = 'Tax rate must be between 0 and 100.';
        }
        if ($data['default_selling_price'] < 0) {
            $errors[] = 'Selling price cannot be negative.';
        }
        if ($data['low_stock_threshold'] < 0) {
            $errors[] = 'Low-stock threshold cannot be negative.';
        }
        if ($isCreate) {
            if ($data['opening_quantity'] < 0) {
                $errors[] = 'Opening quantity cannot be negative.';
            }
            if ($data['opening_avg_cost'] < 0) {
                $errors[] = 'Opening cost cannot be negative.';
            }
            if ($data['opening_latest_cost'] < 0) {
                $errors[] = 'Latest purchase cost cannot be negative.';
            }
            if ($data['opening_quantity'] > 0 && $data['opening_avg_cost'] <= 0) {
                $errors[] = 'Opening cost is required when opening quantity is greater than 0.';
            }
        }

        if ($v->fails() || $errors !== []) {
            flash_old($input);
            Flash::error($v->firstError() ?? $errors[0]);
            return [$data, redirect($redirectTo)];
        }

        // Cross-FK check: subcategory must belong to the chosen category and be active.
        $allowedSubs = $this->subcategories->listActiveByCategory($data['category_id']);
        $allowedIds  = \array_column($allowedSubs, 'id');
        if (!\in_array($data['subcategory_id'], $allowedIds, true)) {
            // On edit it's OK if the existing subcategory is inactive, as long as the user
            // didn't change it. We accept it here without re-validating.
            if ($isCreate) {
                flash_old($input);
                Flash::error('Selected sub-category does not belong to the chosen category, or is inactive.');
                return [$data, redirect($redirectTo)];
            }
        }

        return [$data, null];
    }

    /** Group subcategories by category_id for the JSON map embedded in the form. */
    private function groupSubcategoriesByCategory(array $subs): array
    {
        $grouped = [];
        foreach ($subs as $s) {
            $grouped[(int) $s['category_id']][] = ['id' => (int) $s['id'], 'name' => $s['name']];
        }
        return $grouped;
    }

    /**
     * For edit: include the currently-selected subcategory even if it (or its parent) is now inactive.
     */
    private function subcategoriesForEdit(int $currentSubId): array
    {
        $list = $this->subcategories->listActive();
        foreach ($list as $s) {
            if ((int) $s['id'] === $currentSubId) {
                return $list;
            }
        }
        $current = $this->subcategories->find($currentSubId);
        if ($current !== null) {
            $list[] = [
                'id'          => (int) $current['id'],
                'name'        => $current['name'] . ' (inactive)',
                'category_id' => (int) $current['category_id'],
            ];
        }
        return $list;
    }

    private function requireProduct(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Product not found.');
            return redirect('/products');
        }
        return $row;
    }
}
