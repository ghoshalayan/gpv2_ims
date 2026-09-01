<?php /** @var array $transfers */ /** @var array $pg */ /** @var array $filters */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-arrow-left-right"></i> Inter-account Transfers</h1>
    <a href="<?= url('/inter-account-transfers/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Transfer
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php $searchPlaceholder = 'Search transfer # or reference…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($transfers === []): ?>
            <div class="text-center text-muted py-4">
                <?php if ($pg['search'] !== ''): ?>
                    No matches for "<?= e($pg['search']) ?>".
                <?php elseif (array_filter($filters)): ?>
                    No transfers in the selected date range.
                <?php else: ?>
                    No transfers yet.
                    <a href="<?= url('/inter-account-transfers/create') ?>">Post the first one</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Transfer #</th>
                            <th>Date</th>
                            <th>From</th>
                            <th>→ To</th>
                            <th>Mode</th>
                            <th>Reference</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfers as $i => $t): ?>
                            <tr>
                                <td><?= $pg['from'] + $i ?></td>
                                <td>
                                    <a href="<?= url('/inter-account-transfers/' . $t['id']) ?>">
                                        <strong><?= e($t['transfer_number']) ?></strong>
                                    </a>
                                </td>
                                <td class="text-nowrap"><?= e($t['transfer_date']) ?></td>
                                <td>
                                    <?= e($t['from_name']) ?>
                                    <span class="text-muted small">(<?= e(str_replace('_', ' ', $t['from_kind'])) ?>)</span>
                                </td>
                                <td>
                                    <?= e($t['to_name']) ?>
                                    <span class="text-muted small">(<?= e(str_replace('_', ' ', $t['to_kind'])) ?>)</span>
                                </td>
                                <td><span class="badge text-bg-info"><?= e($t['mode']) ?></span></td>
                                <td class="text-muted small"><?= e($t['reference'] ?? '') ?></td>
                                <td class="text-end fw-bold">₹<?= number_format((float) $t['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php require __DIR__ . '/../partials/pagination.php'; ?>
        <?php endif; ?>
    </div>
</div>
