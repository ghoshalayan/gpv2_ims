<?php /** @var array $orders */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-gear-wide-connected"></i> Production Orders</h1>
    <a href="<?= url('/production-orders/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Production
    </a>
</div>

<div class="alert alert-info py-2 small">
    Production orders consume raw components and produce stock of Irreversible composites
    (e.g. pre-mixed paint). The moving-average landed cost is computed from the components' current
    cost basis per D10. Each line snapshots the recipe used — editing the parent recipe later doesn't
    affect historical production orders.
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($orders === []): ?>
            <div class="text-center text-muted py-4">
                No production orders yet.
                <a href="<?= url('/production-orders/create') ?>">Record the first one</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle data-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Composite</th>
                            <th class="text-end">Qty Produced</th>
                            <th class="text-end">Component Cost</th>
                            <th class="text-end">Landed / Unit</th>
                            <th>Recorded by</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td><strong><?= e($o['order_number']) ?></strong></td>
                                <td class="text-nowrap"><?= e($o['produced_date']) ?></td>
                                <td><?= e($o['product_name']) ?> <span class="text-muted small">(<?= e($o['product_unit']) ?>)</span></td>
                                <td class="text-end"><?= number_format((float) $o['quantity_produced'], 3) ?></td>
                                <td class="text-end">₹<?= number_format((float) $o['total_component_cost'], 2) ?></td>
                                <td class="text-end fw-bold">₹<?= number_format((float) $o['landed_cost_per_unit'], 4) ?></td>
                                <td class="text-muted small"><?= e($o['created_by_name'] ?? '') ?></td>
                                <td class="text-end">
                                    <a href="<?= url('/production-orders/' . $o['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
