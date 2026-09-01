<?php /** @var array $order */ /** @var array $lines */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-gear-wide-connected"></i> Production <?= e($order['order_number']) ?>
    </h1>
    <a href="<?= url('/production-orders') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-md-4"><strong>Composite:</strong> <?= e($order['product_name']) ?> <span class="text-muted">(<?= e($order['product_unit']) ?>)</span></div>
                    <div class="col-md-4"><strong>Qty Produced:</strong> <?= number_format((float) $order['quantity_produced'], 3) ?> <?= e($order['product_unit']) ?></div>
                    <div class="col-md-4"><strong>Date:</strong> <?= e($order['produced_date']) ?></div>
                    <div class="col-md-4"><strong>Recorded:</strong> <?= e($order['created_at']) ?></div>
                    <?php if ($order['created_by_name']): ?>
                        <div class="col-md-4"><strong>By:</strong> <?= e($order['created_by_name']) ?></div>
                    <?php endif; ?>
                    <?php if ($order['notes']): ?>
                        <div class="col-12"><strong>Notes:</strong> <?= e($order['notes']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Cost Summary</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Total component cost</th>
                        <td class="text-end fw-bold">₹<?= number_format((float) $order['total_component_cost'], 2) ?></td></tr>
                    <tr><th>Quantity produced</th>
                        <td class="text-end"><?= number_format((float) $order['quantity_produced'], 3) ?></td></tr>
                    <tr class="table-light"><th>Landed cost per unit</th>
                        <td class="text-end fw-bold fs-5">₹<?= number_format((float) $order['landed_cost_per_unit'], 4) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Component Consumption (recipe snapshot)</strong></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Component</th>
                        <th class="text-end">Per Unit</th>
                        <th class="text-end">Consumed</th>
                        <th class="text-end">Avg Cost at Production</th>
                        <th class="text-end">Line Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $l): ?>
                        <tr>
                            <td>
                                <strong><?= e($l['component_name']) ?></strong>
                                <span class="text-muted small">(<?= e($l['component_unit']) ?>)</span>
                            </td>
                            <td class="text-end"><?= number_format((float) $l['recipe_quantity'], 3) ?></td>
                            <td class="text-end fw-bold"><?= number_format((float) $l['quantity_consumed'], 3) ?></td>
                            <td class="text-end">₹<?= number_format((float) $l['moving_avg_cost_at_production'], 4) ?></td>
                            <td class="text-end fw-bold">₹<?= number_format((float) $l['line_cost'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-success small">
    <i class="bi bi-check-circle"></i>
    Components deducted from stock. Composite stock increased by <?= number_format((float) $order['quantity_produced'], 3) ?> <?= e($order['product_unit']) ?>
    at a moving-avg landed cost of ₹<?= number_format((float) $order['landed_cost_per_unit'], 4) ?> per unit.
</div>
