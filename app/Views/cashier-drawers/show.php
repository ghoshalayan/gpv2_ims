<?php /** @var array $drawer */ /** @var array $transfers */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-cash-stack"></i> <?= e($drawer['name']) ?>
        <span class="badge text-bg-<?= $drawer['status'] === 'active' ? 'success' : 'secondary' ?> ms-2">
            <?= e($drawer['status']) ?>
        </span>
    </h1>
    <div class="d-flex gap-2">
        <a href="<?= url('/cashier-drawers') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        <a href="<?= url('/passbook/cashier-drawer/' . $drawer['id']) ?>" class="btn btn-outline-info">
            <i class="bi bi-journal-text"></i> Passbook
        </a>
        <a href="<?= url('/cashier-drawers/' . $drawer['id'] . '/edit') ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <a href="<?= url('/inter-account-transfers/create?from_kind=cashier_drawer&from_id=' . $drawer['id']) ?>" class="btn btn-primary">
            <i class="bi bi-arrow-left-right"></i> New Transfer
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Drawer details</strong></div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-6"><strong>Location:</strong> <?= e($drawer['location'] ?? '') ?></div>
                    <div class="col-md-6"><strong>Assigned To:</strong> <?= e($drawer['assigned_user_name'] ?? '') ?: '<span class="text-muted">unassigned</span>' ?></div>
                    <?php if ($drawer['remarks']): ?>
                        <div class="col-12"><strong>Remarks:</strong> <?= e($drawer['remarks']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Balance</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Opening Balance</th><td class="text-end">₹<?= number_format((float) $drawer['opening_balance'], 2) ?></td></tr>
                    <tr class="table-light"><th>Current Balance</th>
                        <td class="text-end fw-bold fs-5 <?= (float) $drawer['current_balance'] < 0 ? 'text-danger' : '' ?>">
                            ₹<?= number_format((float) $drawer['current_balance'], 2) ?>
                        </td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white"><strong>Transfer history</strong></div>
    <div class="card-body">
        <?php if ($transfers === []): ?>
            <div class="text-muted small">No transfers touching this drawer yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transfer #</th>
                            <th>Direction</th>
                            <th>Counterparty</th>
                            <th>Mode</th>
                            <th>Reference</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfers as $t): ?>
                            <?php $isOut = (int) $t['from_id'] === (int) $drawer['id'] && $t['from_kind'] === 'cashier_drawer'; ?>
                            <tr>
                                <td class="text-nowrap"><?= e($t['transfer_date']) ?></td>
                                <td><?= e($t['transfer_number']) ?></td>
                                <td>
                                    <?php if ($isOut): ?>
                                        <span class="badge text-bg-danger">OUT</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-success">IN</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $isOut ? e($t['to_name']) : e($t['from_name']) ?>
                                    <span class="text-muted small">(<?= e(str_replace('_', ' ', $isOut ? $t['to_kind'] : $t['from_kind'])) ?>)</span>
                                </td>
                                <td><span class="badge text-bg-info"><?= e($t['mode']) ?></span></td>
                                <td class="text-muted small"><?= e($t['reference'] ?? '') ?></td>
                                <td class="text-end fw-bold <?= $isOut ? 'text-danger' : 'text-success' ?>">
                                    <?= $isOut ? '−' : '+' ?> ₹<?= number_format((float) $t['amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
