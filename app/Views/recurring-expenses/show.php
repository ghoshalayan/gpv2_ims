<?php /** @var array $row */ /** @var array $spawned */ ?>
<?php $isAdmin = \App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-arrow-repeat"></i> <?= e($row['name']) ?>
        <span class="badge text-bg-<?= $row['status'] === 'active' ? 'success' : 'secondary' ?> ms-2">
            <?= e($row['status']) ?>
        </span>
    </h1>
    <div class="d-flex gap-2">
        <a href="<?= url('/recurring-expenses') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        <a href="<?= url('/recurring-expenses/' . $row['id'] . '/edit') ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <?php if ($row['status'] === 'active'): ?>
            <form method="post" action="<?= url('/recurring-expenses/' . $row['id'] . '/run-one') ?>"
                  onsubmit="return confirm('Fire this template now? An expense will be posted for next-run-date <?= e($row['next_run_date']) ?>.')">
                <?= \App\Support\Csrf::field() ?>
                <button class="btn btn-success"><i class="bi bi-play-fill"></i> Run Now</button>
            </form>
        <?php endif; ?>
        <form method="post" action="<?= url('/recurring-expenses/' . $row['id'] . '/pause-resume') ?>">
            <?= \App\Support\Csrf::field() ?>
            <button class="btn btn-outline-warning">
                <?php if ($row['status'] === 'active'): ?>
                    <i class="bi bi-pause-fill"></i> Pause
                <?php else: ?>
                    <i class="bi bi-play-fill"></i> Resume
                <?php endif; ?>
            </button>
        </form>
        <?php if ($isAdmin): ?>
            <form method="post" action="<?= url('/recurring-expenses/' . $row['id'] . '/delete') ?>"
                  onsubmit="return confirm('Delete this template? Past spawned expenses are kept.')">
                <?= \App\Support\Csrf::field() ?>
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Template</strong></div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-4"><strong>Category:</strong> <?= e($row['category_name']) ?></div>
                    <div class="col-md-4"><strong>Vendor:</strong> <?= e($row['vendor_name'] ?? '—') ?></div>
                    <div class="col-md-4"><strong>Amount:</strong> ₹<?= number_format((float) $row['amount'], 2) ?>
                        <?php if ((float) $row['gst_rate'] > 0): ?>
                            + GST <?= number_format((float) $row['gst_rate'], 2) ?>%
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4"><strong>Paid From:</strong>
                        <a href="<?= url(($row['paid_from_kind'] === 'bank_account' ? '/bank-accounts/' : '/cashier-drawers/') . $row['paid_from_id']) ?>">
                            <?= e($row['paid_from_name']) ?>
                        </a>
                        <span class="text-muted">(<?= e(str_replace('_', ' ', $row['paid_from_kind'])) ?>)</span>
                    </div>
                    <div class="col-md-4"><strong>Mode:</strong>
                        <span class="badge text-bg-info"><?= e($row['payment_mode']) ?></span>
                    </div>
                    <div class="col-md-4"><strong>Reference template:</strong>
                        <span class="font-monospace"><?= e($row['payment_reference'] ?? '—') ?></span>
                    </div>
                    <?php if ($row['remarks']): ?>
                        <div class="col-12"><strong>Remarks:</strong> <?= e($row['remarks']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Schedule</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Frequency</th><td>every <?= (int) $row['interval_count'] ?> <?= e($row['frequency']) ?></td></tr>
                    <tr><th>Start</th><td><?= e($row['start_date']) ?></td></tr>
                    <tr><th>End</th><td><?= e($row['end_date'] ?? 'open-ended') ?></td></tr>
                    <tr class="table-light"><th>Next Run</th><td class="fw-bold"><?= e($row['next_run_date']) ?></td></tr>
                    <tr><th>Last Ran</th><td><?= e($row['last_run_at'] ?? 'never') ?></td></tr>
                    <tr><th>Total Runs</th><td><?= (int) $row['runs_count'] ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white"><strong>Spawned expenses</strong></div>
    <div class="card-body">
        <?php if ($spawned === []): ?>
            <div class="text-muted small">No expenses fired from this template yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Expense #</th>
                            <th>Date</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($spawned as $sp): ?>
                            <tr>
                                <td><?= e($sp['expense_number']) ?></td>
                                <td><?= e($sp['expense_date']) ?></td>
                                <td class="text-end">₹<?= number_format((float) $sp['total_with_gst'], 2) ?></td>
                                <td>
                                    <span class="badge text-bg-<?= $sp['status'] === 'posted' ? 'success' : 'secondary' ?>">
                                        <?= e($sp['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('/operating-expenses/' . $sp['id']) ?>" class="btn btn-sm btn-outline-secondary">
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
