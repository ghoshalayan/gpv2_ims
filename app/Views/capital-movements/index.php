<?php
/** @var array $movements */
/** @var string $filter */
/** @var array $loans */
/** @var array $ownerSummary */
/** @var array $pg */
?>
<?php
$typeLabels = [
    'capital_in' => ['Capital injection',  'success'],
    'drawing'    => ['Drawing',            'warning'],
    'loan_in'    => ['Loan received',      'info'],
    'loan_repay' => ['Loan repayment',     'secondary'],
    'other'      => ['Other',              'dark'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-bank"></i> Capital &amp; Drawings</h1>
    <a href="<?= url('/capital-movements/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Movement
    </a>
</div>

<div class="alert alert-info py-2 small">
    <strong>Capital movements are NOT operating expenses.</strong>
    Owner injections add to equity (don't hit the P&amp;L). Drawings reduce equity (also not on P&amp;L).
    Loans create a payable. For day-to-day operating costs (rent, salaries, utilities), use
    <a href="<?= url('/operating-expenses') ?>">Operating Expenses</a>.
</div>

<div class="row g-3 mb-3">
    <?php if (!empty($loans)): ?>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Outstanding loans</strong>
                    <span class="text-muted small ms-2">(loan_in − loan_repay, grouped by lender)</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr>
                            <th>Lender</th><th class="text-end">Borrowed</th>
                            <th class="text-end">Repaid</th><th class="text-end">Outstanding</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($loans as $L): ?>
                                <tr>
                                    <td><?= e($L['counterparty_name']) ?></td>
                                    <td class="text-end text-muted">₹<?= number_format((float) $L['borrowed'], 2) ?></td>
                                    <td class="text-end text-muted">₹<?= number_format((float) $L['repaid'], 2) ?></td>
                                    <td class="text-end fw-bold text-danger">₹<?= number_format((float) $L['outstanding'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($ownerSummary)): ?>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Owner / partner capital</strong>
                    <span class="text-muted small ms-2">(capital_in − drawings)</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr>
                            <th>Owner</th><th class="text-end">Injected</th>
                            <th class="text-end">Drawn</th><th class="text-end">Net Equity</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($ownerSummary as $O): ?>
                                <tr>
                                    <td><?= e($O['counterparty_name']) ?></td>
                                    <td class="text-end text-muted">₹<?= number_format((float) $O['injected'], 2) ?></td>
                                    <td class="text-end text-muted">₹<?= number_format((float) $O['drawn'], 2) ?></td>
                                    <td class="text-end fw-bold <?= (float) $O['net'] < 0 ? 'text-danger' : 'text-success' ?>">
                                        ₹<?= number_format((float) $O['net'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between">
        <strong>All movements</strong>
        <div class="btn-group btn-group-sm">
            <a class="btn btn-outline-secondary <?= $filter === '' ? 'active' : '' ?>"
               href="<?= url('/capital-movements') ?>">All</a>
            <?php foreach ($typeLabels as $key => [$lbl, $color]): ?>
                <a class="btn btn-outline-<?= $color ?> <?= $filter === $key ? 'active' : '' ?>"
                   href="<?= url('/capital-movements?type=' . $key) ?>">
                    <?= e($lbl) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-body">
        <?php $searchPlaceholder = 'Search movement # or counterparty…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($movements === []): ?>
            <div class="text-center text-muted py-4">
                <?php if ($pg['search'] !== ''): ?>
                    No matches for "<?= e($pg['search']) ?>".
                <?php elseif ($filter): ?>
                    No movements (for this filter).
                <?php else: ?>
                    No movements yet.
                    <a href="<?= url('/capital-movements/create') ?>">Post the first one</a>.
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
                            <th>Type</th>
                            <th>Counterparty</th>
                            <th>Account</th>
                            <th>Mode</th>
                            <th class="text-end">In</th>
                            <th class="text-end">Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $i => $m): ?>
                            <?php [$lbl, $color] = $typeLabels[$m['txn_type']] ?? ['?', 'secondary']; ?>
                            <tr class="<?= $m['status'] === 'voided' ? 'text-muted' : '' ?>">
                                <td><?= $pg['from'] + $i ?></td>
                                <td>
                                    <a href="<?= url('/capital-movements/' . $m['id']) ?>">
                                        <strong><?= e($m['movement_number']) ?></strong>
                                    </a>
                                </td>
                                <td class="text-nowrap"><?= e($m['movement_date']) ?></td>
                                <td><span class="badge text-bg-<?= $color ?>"><?= e($lbl) ?></span></td>
                                <td><?= e($m['counterparty_name']) ?></td>
                                <td>
                                    <?= e($m['account_name'] ?? '') ?>
                                    <span class="text-muted small">(<?= e(str_replace('_', ' ', $m['account_kind'])) ?>)</span>
                                </td>
                                <td><span class="badge text-bg-info"><?= e($m['mode']) ?></span></td>
                                <td class="text-end <?= $m['direction'] === 'in' ? 'text-success fw-bold' : 'text-muted' ?>">
                                    <?= $m['direction'] === 'in' ? '+ ₹' . number_format((float) $m['amount'], 2) : '—' ?>
                                </td>
                                <td class="text-end <?= $m['direction'] === 'out' ? 'text-danger fw-bold' : 'text-muted' ?>">
                                    <?= $m['direction'] === 'out' ? '− ₹' . number_format((float) $m['amount'], 2) : '—' ?>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?= $m['status'] === 'posted' ? 'success' : 'secondary' ?>">
                                        <?= e($m['status']) ?>
                                    </span>
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
