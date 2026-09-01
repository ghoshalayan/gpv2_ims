<?php /** @var array $recurrings */ /** @var string $today */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-arrow-repeat"></i> Recurring Expenses</h1>
    <div class="d-flex gap-2">
        <form method="post" action="<?= url('/recurring-expenses/run-due') ?>"
              onsubmit="return confirm('Fire all due recurring expenses as of today? Each one will post a new expense and debit its paying account.')">
            <?= \App\Support\Csrf::field() ?>
            <button class="btn btn-outline-success">
                <i class="bi bi-play-fill"></i> Run Due Recurrences
            </button>
        </form>
        <a href="<?= url('/recurring-expenses/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> New Recurring
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($recurrings === []): ?>
            <div class="text-center text-muted py-4">
                No recurring expenses yet.
                <a href="<?= url('/recurring-expenses/create') ?>">Create your first template</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Frequency</th>
                            <th class="text-end">Amount</th>
                            <th>Paid From</th>
                            <th>Next Run</th>
                            <th class="text-end">Runs</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recurrings as $i => $r): ?>
                            <?php
                                $isDue = $r['status'] === 'active' && $r['next_run_date'] <= $today;
                                $rowClass = $r['status'] === 'paused' ? 'text-muted' : ($isDue ? 'table-warning' : '');
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <a href="<?= url('/recurring-expenses/' . $r['id']) ?>">
                                        <strong><?= e($r['name']) ?></strong>
                                    </a>
                                </td>
                                <td><?= e($r['category_name']) ?></td>
                                <td>
                                    every <?= (int) $r['interval_count'] ?> <?= e($r['frequency']) ?>
                                </td>
                                <td class="text-end">₹<?= number_format((float) $r['amount'], 2) ?></td>
                                <td class="small">
                                    <?= e($r['paid_from_name'] ?? '') ?>
                                    <span class="text-muted">(<?= e(str_replace('_', ' ', $r['paid_from_kind'])) ?>)</span>
                                </td>
                                <td class="text-nowrap">
                                    <?= e($r['next_run_date']) ?>
                                    <?php if ($isDue): ?>
                                        <span class="badge text-bg-warning ms-1">due</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-muted"><?= (int) $r['runs_count'] ?></td>
                                <td>
                                    <span class="badge text-bg-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= e($r['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
