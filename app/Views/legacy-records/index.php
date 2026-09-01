<?php
/** @var string $kind */
/** @var array  $entities */
/** @var array  $pg */
?>
<?php
    $kindLabel = match ($kind) {
        'customer' => 'Customers',
        'supplier' => 'Suppliers',
        'salesman' => 'Salesmen',
        default    => 'Records',
    };
    $singular = match ($kind) {
        'customer' => 'customer',
        'supplier' => 'supplier',
        'salesman' => 'salesman',
        default    => 'entity',
    };
    $createUrl = match ($kind) {
        'customer' => '/customers/create',
        'supplier' => '/suppliers/create',
        'salesman' => '/salesmen/create',
        default    => '/customers/create',
    };
    $kindIcons = ['customer' => 'person', 'supplier' => 'truck', 'salesman' => 'briefcase'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-archive"></i>
        Old Records · <?= e($kindLabel) ?>
    </h1>
    <a href="<?= url($createUrl) ?>" class="btn btn-outline-primary">
        <i class="bi bi-plus-lg"></i> New <?= ucfirst($singular) ?>
    </a>
</div>

<div class="alert alert-info py-2 small">
    Pre-v2 bills, commissions and payments live as data <strong>on each <?= $singular ?></strong>
    — there's no separate "legacy" entity any more. This page lists every <?= $singular ?> that
    has any old records attached. Click a name to manage its old records.
</div>

<form method="get" class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="f_kind">Kind</label>
                <select id="f_kind" name="kind" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach (['customer'=>'Customers','supplier'=>'Suppliers','salesman'=>'Salesmen'] as $k=>$lab): ?>
                        <option value="<?= $k ?>" <?= $kind === $k ? 'selected' : '' ?>><?= $lab ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body">
        <?php $searchPlaceholder = "Search {$singular} name or phone…"; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($entities === []): ?>
            <div class="text-center text-muted py-4">
                <?php if ($pg['search'] !== ''): ?>
                    No <?= e($singular) ?> matches "<?= e($pg['search']) ?>" with old records.
                <?php else: ?>
                    No <?= e($singular) ?> has old records yet.<br>
                    Open a <?= e($singular) ?> page and click <em>"Add old bill"</em> to record one.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th class="text-center">Bills</th>
                            <th class="text-end">Total Billed</th>
                            <th class="text-end">Outstanding</th>
                            <th class="text-end">Unallocated</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entities as $i => $e): ?>
                            <tr class="<?= $e['status'] !== 'active' ? 'text-muted' : '' ?>">
                                <td><?= $pg['from'] + $i ?></td>
                                <td>
                                    <a href="<?= url('/old-records/' . $kind . '/' . (int) $e['id']) ?>">
                                        <strong><?= e($e['name']) ?></strong>
                                    </a>
                                </td>
                                <td><?= e($e['phone'] ?? '') ?></td>
                                <td class="text-center"><?= (int) $e['bill_count'] ?></td>
                                <td class="text-end">₹<?= number_format((float) $e['total_billed'], 2) ?></td>
                                <td class="text-end fw-bold <?= (float) $e['total_due'] > 0.001 ? 'text-danger' : 'text-muted' ?>">
                                    ₹<?= number_format((float) $e['total_due'], 2) ?>
                                </td>
                                <td class="text-end <?= (float) $e['unallocated'] > 0.001 ? 'text-success' : 'text-muted' ?>">
                                    ₹<?= number_format((float) $e['unallocated'], 2) ?>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?= $e['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= e($e['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= url('/passbook/' . $kind . '/' . (int) $e['id']) ?>"
                                       class="btn btn-sm btn-outline-primary" title="Passbook">
                                        <i class="bi bi-journal-text"></i>
                                    </a>
                                    <a href="<?= url('/old-records/' . $kind . '/' . (int) $e['id']) ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Manage old records">
                                        <i class="bi bi-archive"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php require __DIR__ . '/../partials/pagination.php'; ?>
        <?php endif; ?>
    </div>
</div>
