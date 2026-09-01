<?php
/** @var array  $rebates */
/** @var array  $brands */
/** @var array  $suppliers */
/** @var ?int   $brandId */
/** @var ?int   $supplierId */
/** @var string $modeFilter */
/** @var string $statusFilter */
/** @var array  $pg */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-gift"></i> Brand Rebates</h1>
    <a href="<?= url('/brand-rebates/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Post Rebate
    </a>
</div>

<div class="alert alert-info py-2 small">
    Credit notes brands issue when sales/purchase targets are achieved. Four modes:
    <strong>Cash Back</strong> (money in), <strong>Reduce Payable</strong> (offset future bills),
    <strong>Cost Reduction</strong> (recompute landed cost), <strong>Free Stock</strong>
    (extra units valued at cost). Voiding reverses each mode's effects.
</div>

<form method="get" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="f_brand">Brand</label>
                <select id="f_brand" name="brand_id" class="form-select form-select-sm">
                    <option value="">All brands</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= $brandId === (int) $b['id'] ? 'selected' : '' ?>>
                            <?= e($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="f_supplier">Supplier</label>
                <select id="f_supplier" name="supplier_id" class="form-select form-select-sm">
                    <option value="">All suppliers</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $supplierId === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="f_mode">Mode</label>
                <select id="f_mode" name="mode" class="form-select form-select-sm">
                    <option value="">All modes</option>
                    <?php foreach (['cash_back', 'reduce_payable', 'cost_reduction', 'free_stock'] as $m): ?>
                        <option value="<?= $m ?>" <?= $modeFilter === $m ? 'selected' : '' ?>>
                            <?= e(ucwords(str_replace('_', ' ', $m))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Filter</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/brand-rebates') ?>">Clear</a>
            </div>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between">
        <strong>All rebates</strong>
        <div class="btn-group btn-group-sm">
            <a class="btn btn-outline-secondary <?= $statusFilter === '' ? 'active' : '' ?>"
               href="<?= url('/brand-rebates') ?>">All</a>
            <a class="btn btn-outline-success <?= $statusFilter === 'posted' ? 'active' : '' ?>"
               href="<?= url('/brand-rebates?status=posted') ?>">Posted</a>
            <a class="btn btn-outline-warning <?= $statusFilter === 'voided' ? 'active' : '' ?>"
               href="<?= url('/brand-rebates?status=voided') ?>">Voided</a>
        </div>
    </div>
    <div class="card-body">
        <?php $searchPlaceholder = 'Search rebate #, brand, supplier, or credit note…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($rebates === []): ?>
            <div class="text-center text-muted py-4">
                <?php if ($pg['search'] !== ''): ?>
                    No matches for "<?= e($pg['search']) ?>".
                <?php elseif ($brandId || $supplierId || $modeFilter || $statusFilter): ?>
                    No rebates match the current filters.
                <?php else: ?>
                    No rebates yet. <a href="<?= url('/brand-rebates/create') ?>">Post the first one</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Number</th>
                            <th>Date</th>
                            <th>Brand</th>
                            <th>Supplier</th>
                            <th>Mode</th>
                            <th>Credit Note</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $modeColors = [
                                'cash_back'      => 'success',
                                'reduce_payable' => 'primary',
                                'cost_reduction' => 'warning',
                                'free_stock'     => 'info',
                            ];
                        ?>
                        <?php foreach ($rebates as $i => $r): ?>
                            <?php
                                $modeColor = $modeColors[$r['mode']] ?? 'secondary';
                                $stsColor  = $r['status'] === 'posted' ? 'success' : 'secondary';
                            ?>
                            <tr class="<?= $r['status'] !== 'posted' ? 'text-muted' : '' ?>">
                                <td><?= $pg['from'] + $i ?></td>
                                <td>
                                    <a href="<?= url('/brand-rebates/' . $r['id']) ?>">
                                        <strong><?= e($r['rebate_number']) ?></strong>
                                    </a>
                                </td>
                                <td class="text-nowrap"><?= e($r['rebate_date']) ?></td>
                                <td><?= e($r['brand_name']) ?></td>
                                <td><?= e($r['supplier_name']) ?></td>
                                <td><span class="badge text-bg-<?= $modeColor ?>"><?= e(str_replace('_', ' ', $r['mode'])) ?></span></td>
                                <td class="font-monospace small">
                                    <?= e($r['credit_note_number']) ?><br>
                                    <span class="text-muted"><?= e($r['credit_note_date']) ?></span>
                                </td>
                                <td class="text-end fw-bold">₹<?= number_format((float) $r['amount'], 2) ?></td>
                                <td><span class="badge text-bg-<?= $stsColor ?>"><?= e($r['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php require __DIR__ . '/../partials/pagination.php'; ?>
        <?php endif; ?>
    </div>
</div>
