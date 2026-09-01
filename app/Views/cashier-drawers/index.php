<?php /** @var array $drawers */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-cash-stack"></i> Cashier Drawers</h1>
    <a href="<?= url('/cashier-drawers/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Drawer
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($drawers === []): ?>
            <div class="text-center text-muted py-4">
                No drawers yet. <a href="<?= url('/cashier-drawers/create') ?>">Add the first one</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Location</th>
                            <th>Assigned To</th>
                            <th class="text-end">Opening</th>
                            <th class="text-end">Current</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drawers as $i => $d): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><a href="<?= url('/cashier-drawers/' . $d['id']) ?>"><strong><?= e($d['name']) ?></strong></a></td>
                                <td><?= e($d['location'] ?? '') ?></td>
                                <td><?= e($d['assigned_user_name'] ?? '') ?: '<span class="text-muted small">unassigned</span>' ?></td>
                                <td class="text-end text-muted">₹<?= number_format((float) $d['opening_balance'], 2) ?></td>
                                <td class="text-end fw-bold <?= (float) $d['current_balance'] < 0 ? 'text-danger' : '' ?>">
                                    ₹<?= number_format((float) $d['current_balance'], 2) ?>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?= $d['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= e($d['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= url('/passbook/cashier-drawer/' . $d['id']) ?>"
                                       class="btn btn-sm btn-outline-info" title="Passbook">
                                        <i class="bi bi-journal-text"></i>
                                    </a>
                                    <a href="<?= url('/cashier-drawers/' . $d['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="post" action="<?= url('/cashier-drawers/' . $d['id'] . '/toggle') ?>" class="d-inline"
                                          onsubmit="return confirm('Toggle status for <?= e($d['name']) ?>?')">
                                        <?= \App\Support\Csrf::field() ?>
                                        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-power"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
