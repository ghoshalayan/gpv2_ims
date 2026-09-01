<?php
/** @var string $from */
/** @var string $to */
/** @var string $accountKind */
/** @var int    $accountId */
/** @var array  $reconciles */
/** @var bool   $hasMismatch */
/** @var array  $events */
/** @var array  $inByCat */
/** @var array  $outByCat */
/** @var float  $inTotal */
/** @var float  $outTotal */
/** @var float  $periodNet */
?>
<?php
    $catLabels = [
        'sales'            => 'Sales receipts',
        'customer_advance' => 'Customer advances',
        'purchase'         => 'Purchase payments',
        'supplier_advance' => 'Supplier advances',
        'transfer'         => 'Inter-account transfers',
        'capital'          => 'Capital / loans',
        'expense'          => 'Operating expenses',
        'commission'       => 'Commission settlements',
        'rebate'           => 'Brand rebates',
        'legacy'           => 'Legacy settlements',
    ];
    $cellMoney = static function (float $v, string $color = ''): string {
        $cls = $color !== '' ? ' text-' . $color : '';
        return '<span class="text-end' . $cls . '">₹' . number_format($v, 2) . '</span>';
    };
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-cash-stack"></i> Cash Inflow / Outflow</h1>
</div>

<!-- Reconciliation panel — lifetime cross-validation -->
<div class="card shadow-sm mb-3 <?= $hasMismatch ? 'border-danger' : 'border-success' ?>">
    <div class="card-header bg-<?= $hasMismatch ? 'danger' : 'success' ?> text-white">
        <strong><i class="bi bi-shield-check"></i> Reconciliation — Lifetime</strong>
        <span class="ms-2 small">
            <?= $hasMismatch
                ? '⚠ Mismatch detected — diff ≠ 0 on at least one account'
                : '✓ All accounts balanced (every transaction accounted for)' ?>
        </span>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Account</th>
                    <th class="text-end">Opening</th>
                    <th class="text-end">+ Inflows</th>
                    <th class="text-end">− Outflows</th>
                    <th class="text-end">= Computed</th>
                    <th class="text-end">Actual</th>
                    <th class="text-end">Diff</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $sumIn = $sumOut = $sumOpen = $sumExp = $sumAct = 0.0;
                    foreach ($reconciles as $r):
                        $diffOk = abs((float) $r['diff']) < 0.01;
                        $sumIn   += (float) $r['inflow'];
                        $sumOut  += (float) $r['outflow'];
                        $sumOpen += (float) $r['opening'];
                        $sumExp  += (float) $r['expected'];
                        $sumAct  += (float) $r['actual'];
                ?>
                    <tr>
                        <td>
                            <i class="bi bi-<?= $r['account_kind'] === 'bank_account' ? 'bank' : 'cash-stack' ?>"></i>
                            <strong><?= e($r['account_name']) ?></strong>
                            <span class="text-muted small">(<?= e(str_replace('_', ' ', $r['account_kind'])) ?>)</span>
                            <?php if ($r['status'] !== 'active'): ?>
                                <span class="badge text-bg-secondary">inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">₹<?= number_format((float) $r['opening'], 2) ?></td>
                        <td class="text-end text-success">₹<?= number_format((float) $r['inflow'], 2) ?></td>
                        <td class="text-end text-danger">₹<?= number_format((float) $r['outflow'], 2) ?></td>
                        <td class="text-end fw-bold">₹<?= number_format((float) $r['expected'], 2) ?></td>
                        <td class="text-end">₹<?= number_format((float) $r['actual'], 2) ?></td>
                        <td class="text-end fw-bold <?= $diffOk ? 'text-success' : 'text-danger' ?>">
                            <?= $diffOk ? '✓ ₹0.00' : '⚠ ₹' . number_format((float) $r['diff'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th>TOTAL across all accounts</th>
                    <th class="text-end">₹<?= number_format($sumOpen, 2) ?></th>
                    <th class="text-end text-success">₹<?= number_format($sumIn, 2) ?></th>
                    <th class="text-end text-danger">₹<?= number_format($sumOut, 2) ?></th>
                    <th class="text-end">₹<?= number_format($sumExp, 2) ?></th>
                    <th class="text-end">₹<?= number_format($sumAct, 2) ?></th>
                    <th class="text-end <?= abs($sumAct - $sumExp) < 0.01 ? 'text-success' : 'text-danger' ?>">
                        <?= abs($sumAct - $sumExp) < 0.01 ? '✓ ₹0.00' : '⚠ ₹' . number_format($sumAct - $sumExp, 2) ?>
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="card-footer small text-muted">
        Same source set as <a href="<?= url('/system/integrity-check') ?>">System Integrity Check</a>:
        opening_balance + (sales receipts, customer/supplier advances, transfers, capital, expenses,
        commission settlements, brand rebates, legacy settlements) = current_balance.
        Diff column shows residual drift if any.
    </div>
</div>

<!-- Period filter -->
<form method="get" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="from">From</label>
                <input type="date" id="from" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="to">To</label>
                <input type="date" id="to" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Account scope</label>
                <select name="account_kind_id" class="form-select form-select-sm"
                        onchange="(function(s){
                            const v=s.value; const k=v.split(':')[0]; const id=v.split(':')[1];
                            document.getElementById('ak').value = k || '';
                            document.getElementById('aid').value = id || '';
                        })(this); this.form.submit()">
                    <option value="">All accounts</option>
                    <?php foreach ($reconciles as $r): ?>
                        <?php $val = $r['account_kind'] . ':' . $r['account_id']; ?>
                        <option value="<?= $val ?>"
                            <?= $accountKind === $r['account_kind'] && $accountId === (int) $r['account_id'] ? 'selected' : '' ?>>
                            <?= e($r['account_name']) ?> (<?= e(str_replace('_', ' ', $r['account_kind'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="ak"  name="account_kind" value="<?= e($accountKind) ?>">
                <input type="hidden" id="aid" name="account_id"   value="<?= e((string) ($accountId ?: '')) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Apply</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/reports/cash-flow') ?>">Reset (FY)</a>
            </div>
        </div>
    </div>
</form>

<!-- Period summary by category -->
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100 border-success">
            <div class="card-header bg-white">
                <strong class="text-success"><i class="bi bi-arrow-down-circle"></i> Inflows</strong>
                <span class="float-end fs-5 fw-bold text-success">₹<?= number_format($inTotal, 2) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if ($inByCat === []): ?>
                    <p class="text-center text-muted py-3 mb-0">No inflows in this period.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <tbody>
                            <?php foreach ($inByCat as $cat => $amt): ?>
                                <tr>
                                    <td><?= e($catLabels[$cat] ?? $cat) ?></td>
                                    <td class="text-end fw-bold">₹<?= number_format($amt, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100 border-danger">
            <div class="card-header bg-white">
                <strong class="text-danger"><i class="bi bi-arrow-up-circle"></i> Outflows</strong>
                <span class="float-end fs-5 fw-bold text-danger">₹<?= number_format($outTotal, 2) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if ($outByCat === []): ?>
                    <p class="text-center text-muted py-3 mb-0">No outflows in this period.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <tbody>
                            <?php foreach ($outByCat as $cat => $amt): ?>
                                <tr>
                                    <td><?= e($catLabels[$cat] ?? $cat) ?></td>
                                    <td class="text-end fw-bold">₹<?= number_format($amt, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm bg-light">
            <div class="card-body py-2 text-center">
                <span class="text-muted">Net cash flow for the period</span>
                <span class="ms-3 fs-4 fw-bold <?= $periodNet >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $periodNet >= 0 ? '+' : '' ?>₹<?= number_format($periodNet, 2) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Full event list (paginated). Period totals above reflect ALL matching events; this table shows the current page. -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>All events <span class="text-muted small">(<?= number_format($pg['total']) ?> total)</span></strong>
        <span class="small text-muted"><?= e($from) ?> → <?= e($to) ?></span>
    </div>
    <div class="card-body">
        <?php $searchPlaceholder = 'Search source #, party, or description…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($events === []): ?>
            <p class="text-center text-muted py-3 mb-0">
                <?= $pg['search'] !== '' ? 'No matches for "' . e($pg['search']) . '".' : 'No cash events in this period.' ?>
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Source</th>
                            <th>Party</th>
                            <th>Account</th>
                            <th class="text-end">Inflow</th>
                            <th class="text-end">Outflow</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $e): ?>
                            <?php
                                $isIn = $e['direction'] === 'in';
                                $catColor = [
                                    'sales' => 'success', 'customer_advance' => 'success',
                                    'purchase' => 'danger', 'supplier_advance' => 'danger',
                                    'transfer' => 'secondary', 'capital' => 'info',
                                    'expense' => 'warning', 'commission' => 'warning',
                                    'rebate' => 'success', 'legacy' => 'dark',
                                ][$e['category']] ?? 'secondary';
                            ?>
                            <tr>
                                <td class="text-nowrap small"><?= e($e['event_date']) ?></td>
                                <td><span class="badge text-bg-<?= $catColor ?>"><?= e($e['category']) ?></span></td>
                                <td class="font-monospace small"><?= e($e['source_number']) ?></td>
                                <td class="small"><?= e($e['party_name'] ?? '—') ?></td>
                                <td class="small"><?= e($e['account_name'] ?? '—') ?></td>
                                <td class="text-end <?= $isIn ? 'text-success fw-bold' : 'text-muted' ?>">
                                    <?= $isIn ? '+₹' . number_format((float) $e['amount'], 2) : '' ?>
                                </td>
                                <td class="text-end <?= !$isIn ? 'text-danger fw-bold' : 'text-muted' ?>">
                                    <?= !$isIn ? '−₹' . number_format((float) $e['amount'], 2) : '' ?>
                                </td>
                                <td class="small text-muted"><?= e($e['narration'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="5" class="text-end">Period totals</th>
                            <th class="text-end text-success">+₹<?= number_format($inTotal, 2) ?></th>
                            <th class="text-end text-danger">−₹<?= number_format($outTotal, 2) ?></th>
                            <th class="small">Net <?= $periodNet >= 0 ? '+' : '' ?>₹<?= number_format($periodNet, 2) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php require __DIR__ . '/../partials/pagination.php'; ?>
        <?php endif; ?>
    </div>
</div>
