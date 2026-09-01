<?php /** @var array $accounts */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-bank"></i> Bank Accounts</h1>
    <a href="<?= url('/bank-accounts/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Bank Account
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($accounts === []): ?>
            <div class="text-center text-muted py-4">
                No bank accounts yet. <a href="<?= url('/bank-accounts/create') ?>">Add the first one</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Bank</th>
                            <th>Account #</th>
                            <th>IFSC</th>
                            <th class="text-end">Opening</th>
                            <th class="text-end">Current</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $i => $a): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><a href="<?= url('/bank-accounts/' . $a['id']) ?>"><strong><?= e($a['name']) ?></strong></a></td>
                                <td><?= e($a['bank_name'] ?? '') ?></td>
                                <td class="font-monospace small"><?= e($a['account_number'] ?? '') ?></td>
                                <td class="font-monospace small"><?= e($a['ifsc'] ?? '') ?></td>
                                <td class="text-end text-muted">₹<?= number_format((float) $a['opening_balance'], 2) ?></td>
                                <td class="text-end fw-bold <?= (float) $a['current_balance'] < 0 ? 'text-danger' : '' ?>">
                                    ₹<?= number_format((float) $a['current_balance'], 2) ?>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?= $a['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= e($a['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= url('/passbook/bank-account/' . $a['id']) ?>"
                                       class="btn btn-sm btn-outline-info" title="Passbook">
                                        <i class="bi bi-journal-text"></i>
                                    </a>
                                    <a href="<?= url('/bank-accounts/' . $a['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="post" action="<?= url('/bank-accounts/' . $a['id'] . '/toggle') ?>" class="d-inline"
                                          onsubmit="return confirm('Toggle status for <?= e($a['name']) ?>?')">
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
