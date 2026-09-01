<?php
/** @var string $tab */
/** @var string $term */
/** @var string $search */
/** @var array  $rows */
/** @var array  $totals */
?>
<?php
    $isSalesman = $tab === 'salesman';
    // Carry the current view's filters through print / CSV / tag-form redirects.
    $query = \array_filter([
        'tab'  => $tab,
        'term' => $term,
        'q'    => $search,
    ], static fn($v) => $v !== '' && $v !== null);
    $qs = $query === [] ? '' : '?' . http_build_query($query);

    $termLabels = ['' => 'All terms', 'short' => 'Short-term', 'long' => 'Long-term', 'untagged' => 'Untagged'];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-cash-stack"></i> Due List</h1>
    <div class="d-flex gap-2">
        <a href="<?= url('/reports/due-list/print' . $qs) ?>" target="_blank" class="btn btn-outline-primary">
            <i class="bi bi-printer"></i> Print / PDF
        </a>
        <a href="<?= url('/reports/due-list/csv' . $qs) ?>" class="btn btn-outline-success">
            <i class="bi bi-filetype-csv"></i> Export CSV
        </a>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $isSalesman ? '' : 'active' ?>"
           href="<?= url('/reports/due-list?tab=customer') ?>">
            <i class="bi bi-people"></i> Customers
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $isSalesman ? 'active' : '' ?>"
           href="<?= url('/reports/due-list?tab=salesman') ?>">
            <i class="bi bi-person-badge"></i> Salesmen
        </a>
    </li>
</ul>

<form method="get" class="card shadow-sm mb-3">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small" for="q">Search</label>
                <input type="text" id="q" name="q" class="form-control form-control-sm"
                       value="<?= e($search) ?>" placeholder="Name or phone…">
            </div>
            <?php if (!$isSalesman): ?>
                <div class="col-md-3">
                    <label class="form-label small" for="term">Due term</label>
                    <select id="term" name="term" class="form-select form-select-sm">
                        <?php foreach ($termLabels as $value => $label): ?>
                            <option value="<?= e((string) $value) ?>" <?= $term === $value ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Apply</button>
                <a class="btn btn-sm btn-outline-secondary"
                   href="<?= url('/reports/due-list?tab=' . e($tab)) ?>">Reset</a>
            </div>
        </div>
    </div>
</form>

<?php if ($isSalesman): ?>

    <div class="alert alert-info py-2 small mb-3">
        <strong>Commission</strong> is what we owe the salesman (earned − reversed − settled).
        <strong>Cash</strong> is what the salesman owes us on their own customer account.
        <strong>Total</strong> nets the two: a positive figure means we owe them, a negative
        figure means they owe us.
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body text-center">
            <div class="text-muted small">Commission owed to salesmen</div>
            <div class="h4 mb-0">₹<?= number_format((float) $totals['commission_due'], 2) ?></div>
        </div></div></div>
        <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body text-center">
            <div class="text-muted small">Cash owed by salesmen</div>
            <div class="h4 mb-0">₹<?= number_format((float) $totals['cash_due'], 2) ?></div>
        </div></div></div>
        <div class="col-md-4"><div class="card shadow-sm h-100 bg-light"><div class="card-body text-center">
            <div class="text-muted small">Net position</div>
            <div class="h4 mb-0 fw-bold">₹<?= number_format((float) $totals['total_due'], 2) ?></div>
            <div class="small text-muted">
                <?= ((float) $totals['total_due']) >= 0 ? 'we owe salesmen' : 'salesmen owe us' ?>
            </div>
        </div></div></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Salesman dues</strong></div>
        <div class="card-body">
            <?php if ($rows === []): ?>
                <p class="text-center text-muted py-3 mb-0">
                    <?= $search !== '' ? 'No matches for "' . e($search) . '".' : 'No salesman carries an outstanding balance.' ?>
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Salesman</th>
                                <th>Phone</th>
                                <th class="text-end">Commission</th>
                                <th class="text-end">Cash</th>
                                <th class="text-end">Total</th>
                                <th>Direction</th>
                                <th class="text-end">Passbook</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php $net = (float) $r['total_due']; ?>
                                <tr>
                                    <td>
                                        <strong><?= e($r['name']) ?></strong>
                                        <?php if ($r['status'] !== 'active'): ?>
                                            <span class="badge bg-secondary">inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-nowrap"><?= e($r['phone']) ?></td>
                                    <td class="text-end">₹<?= number_format((float) $r['commission_due'], 2) ?></td>
                                    <td class="text-end">₹<?= number_format((float) $r['cash_due'], 2) ?></td>
                                    <td class="text-end fw-bold <?= $net < 0 ? 'text-danger' : '' ?>">
                                        ₹<?= number_format($net, 2) ?>
                                    </td>
                                    <td class="small">
                                        <?php if (abs($net) < 0.01): ?>
                                            <span class="text-muted">settled</span>
                                        <?php elseif ($net > 0): ?>
                                            <span class="badge bg-warning text-dark">we owe</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">owes us</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="<?= url('/passbook/salesman/' . (int) $r['id']) ?>">
                                            <i class="bi bi-journal-text"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">Totals (<?= count($rows) ?> salesmen)</th>
                                <th class="text-end">₹<?= number_format((float) $totals['commission_due'], 2) ?></th>
                                <th class="text-end">₹<?= number_format((float) $totals['cash_due'], 2) ?></th>
                                <th class="text-end">₹<?= number_format((float) $totals['total_due'], 2) ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center">
            <div class="text-muted small">Short-term</div>
            <div class="h4 mb-0">₹<?= number_format((float) $totals['short_due'], 2) ?></div>
        </div></div></div>
        <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center">
            <div class="text-muted small">Long-term</div>
            <div class="h4 mb-0">₹<?= number_format((float) $totals['long_due'], 2) ?></div>
        </div></div></div>
        <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center">
            <div class="text-muted small">Untagged</div>
            <div class="h4 mb-0">₹<?= number_format((float) $totals['untagged_due'], 2) ?></div>
        </div></div></div>
        <div class="col-md-3"><div class="card shadow-sm h-100 bg-light"><div class="card-body text-center">
            <div class="text-muted small">Total receivable</div>
            <div class="h4 mb-0 fw-bold">₹<?= number_format((float) $totals['total_due'], 2) ?></div>
            <div class="small text-muted">
                incl. ₹<?= number_format((float) $totals['legacy_due'], 2) ?> legacy
            </div>
        </div></div></div>
    </div>

    <?php if ((float) $totals['advance_credit'] > 0): ?>
        <div class="alert alert-secondary py-2 small">
            Customers also hold <strong>₹<?= number_format((float) $totals['advance_credit'], 2) ?></strong>
            in unapplied advances. These are <em>not</em> netted off the totals above, matching the
            Balance Sheet, which carries advances as a separate liability.
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Customer dues</strong>
            <span class="small text-muted">Set a customer's term to classify all their untagged invoices at once.</span>
        </div>
        <div class="card-body">
            <?php if ($rows === []): ?>
                <p class="text-center text-muted py-3 mb-0">
                    <?php if ($search !== '' || $term !== ''): ?>
                        No customers match the current filters.
                    <?php else: ?>
                        No customer currently owes anything.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th class="text-end">Bills</th>
                                <th class="text-end">Short-term</th>
                                <th class="text-end">Long-term</th>
                                <th class="text-end">Untagged</th>
                                <th class="text-end">Total Due</th>
                                <th>Oldest</th>
                                <th style="min-width: 9rem;">Default term</th>
                                <th class="text-end">Passbook</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($r['name']) ?></strong>
                                        <?php if ($r['status'] !== 'active'): ?>
                                            <span class="badge bg-secondary">inactive</span>
                                        <?php endif; ?>
                                        <?php if ((float) $r['legacy_due'] > 0): ?>
                                            <div class="small text-muted">
                                                incl. ₹<?= number_format((float) $r['legacy_due'], 2) ?> legacy
                                            </div>
                                        <?php endif; ?>
                                        <?php if ((float) $r['advance_credit'] > 0): ?>
                                            <div class="small text-success">
                                                ₹<?= number_format((float) $r['advance_credit'], 2) ?> advance on hand
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-nowrap"><?= e($r['phone']) ?></td>
                                    <td class="text-end"><?= (int) $r['invoice_count'] + (int) $r['legacy_count'] ?></td>
                                    <td class="text-end"><?= ((float) $r['short_due']) > 0 ? '₹' . number_format((float) $r['short_due'], 2) : '<span class="text-muted">—</span>' ?></td>
                                    <td class="text-end"><?= ((float) $r['long_due']) > 0 ? '₹' . number_format((float) $r['long_due'], 2) : '<span class="text-muted">—</span>' ?></td>
                                    <td class="text-end"><?= ((float) $r['untagged_due']) > 0 ? '₹' . number_format((float) $r['untagged_due'], 2) : '<span class="text-muted">—</span>' ?></td>
                                    <td class="text-end fw-bold">₹<?= number_format((float) $r['total_due'], 2) ?></td>
                                    <td class="small text-nowrap"><?= e($r['oldest_due_date'] ?? '—') ?></td>
                                    <td>
                                        <form method="post"
                                              action="<?= url('/due-list/customer/' . (int) $r['id'] . '/term') ?>">
                                            <?= \App\Support\Csrf::field() ?>
                                            <input type="hidden" name="tab"  value="<?= e($tab) ?>">
                                            <input type="hidden" name="term" value="<?= e($term) ?>">
                                            <input type="hidden" name="q"    value="<?= e($search) ?>">
                                            <select name="due_term" class="form-select form-select-sm no-select2"
                                                    onchange="this.form.submit()">
                                                <option value=""      <?= $r['default_due_term'] === null    ? 'selected' : '' ?>>— none —</option>
                                                <option value="short" <?= $r['default_due_term'] === 'short' ? 'selected' : '' ?>>Short-term</option>
                                                <option value="long"  <?= $r['default_due_term'] === 'long'  ? 'selected' : '' ?>>Long-term</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="<?= url('/passbook/customer/' . (int) $r['id']) ?>">
                                            <i class="bi bi-journal-text"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3" class="text-end">Totals (<?= count($rows) ?> customers)</th>
                                <th class="text-end">₹<?= number_format((float) $totals['short_due'], 2) ?></th>
                                <th class="text-end">₹<?= number_format((float) $totals['long_due'], 2) ?></th>
                                <th class="text-end">₹<?= number_format((float) $totals['untagged_due'], 2) ?></th>
                                <th class="text-end">₹<?= number_format((float) $totals['total_due'], 2) ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>
