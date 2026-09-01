<?php
/** @var string $kind */
/** @var array  $entity */
/** @var array  $obligations */
/** @var array  $unpaidObligs */
/** @var array  $settlements */
/** @var array  $rollup */
/** @var array  $banks */
/** @var array  $drawers */
?>
<?php $isAdmin = \App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN); ?>
<?php
    $isCustomer = $kind === 'customer';
    $isSupplier = $kind === 'supplier';
    $isSalesman = $kind === 'salesman';

    $obligLabel    = $isSalesman ? 'Old commission entries' : 'Old bills';
    $obligOne      = $isSalesman ? 'Commission entry'        : 'Bill';
    $obligNumberHd = $isSalesman ? 'Commission Ref'          : 'Bill #';
    $settleLabel   = $isCustomer ? 'Old payments received'
                  : ($isSalesman ? 'Old commission payouts'  : 'Old payments made');
    $settleOne     = $isCustomer ? 'Payment received'
                  : ($isSalesman ? 'Settlement'              : 'Payment made');
    $cashSideLabel = $isCustomer ? 'Receive into'            : 'Pay from';
    $cashIcon      = $isCustomer ? 'arrow-down-circle text-success' : 'arrow-up-circle text-danger';
    $kindBadgeColor= ['customer'=>'primary','supplier'=>'warning','salesman'=>'info'][$kind] ?? 'secondary';
    $entityEditUrl = match ($kind) {
        'customer' => '/customers/' . (int) $entity['id'] . '/edit',
        'supplier' => '/suppliers/' . (int) $entity['id'] . '/edit',
        'salesman' => '/salesmen/'  . (int) $entity['id'] . '/edit',
    };

    $totalBilled = (float) ($rollup['total_billed'] ?? 0);
    $totalPaid   = (float) ($rollup['total_paid']   ?? 0);
    $totalDue    = (float) ($rollup['total_due']    ?? 0);
    $unalloc     = (float) ($rollup['unallocated']  ?? 0);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-archive"></i>
        Old records ·
        <a href="<?= url($entityEditUrl) ?>" class="text-decoration-none"><?= e($entity['name']) ?></a>
        <span class="badge text-bg-<?= $kindBadgeColor ?> ms-2"><?= e($kind) ?></span>
    </h1>
    <div>
        <a href="<?= url('/passbook/' . $kind . '/' . (int) $entity['id']) ?>"
           class="btn btn-outline-primary me-2">
            <i class="bi bi-journal-text"></i> Passbook
        </a>
        <a href="<?= url($entityEditUrl) ?>" class="btn btn-outline-secondary me-2">
            <i class="bi bi-pencil"></i> Edit <?= e($kind) ?>
        </a>
        <a href="<?= url('/old-records?kind=' . $kind) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="alert alert-info py-2 small mb-3">
    Old bills + payments here are part of <strong><?= e($entity['name']) ?></strong>'s
    passbook — they carry forward into the v2 ledger automatically.
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Entity Details</strong></div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-4"><strong>Phone:</strong> <?= e($entity['phone'] ?? '—') ?></div>
                    <div class="col-md-4"><strong>Email:</strong> <?= e($entity['email'] ?? '—') ?></div>
                    <div class="col-md-4"><strong>Status:</strong> <?= e($entity['status'] ?? 'active') ?></div>
                    <?php if (!empty($entity['address'])): ?>
                        <div class="col-12"><strong>Address:</strong> <?= e($entity['address']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Old Balance Summary</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th><?= $isSalesman ? 'Total commission' : 'Total billed' ?></th>
                        <td class="text-end">₹<?= number_format($totalBilled, 2) ?></td></tr>
                    <tr><th>Total settled</th>
                        <td class="text-end text-success">₹<?= number_format($totalPaid, 2) ?></td></tr>
                    <tr class="table-light"><th>Outstanding</th>
                        <td class="text-end fw-bold fs-5 <?= $totalDue > 0.001 ? ($isCustomer ? 'text-danger' : 'text-warning') : 'text-muted' ?>">
                            ₹<?= number_format($totalDue, 2) ?>
                        </td></tr>
                    <?php if ($unalloc > 0.001): ?>
                        <tr><th>Unallocated</th>
                            <td class="text-end text-success">₹<?= number_format($unalloc, 2) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Obligations -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong><?= $obligLabel ?></strong>
        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addObligForm">
            <i class="bi bi-plus"></i> Add <?= strtolower($obligOne) ?>
        </button>
    </div>
    <div class="card-body p-0">
        <?php if ($obligations === []): ?>
            <p class="text-center text-muted py-3 mb-0">No <?= strtolower($obligLabel) ?> recorded yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><?= $obligNumberHd ?></th>
                            <th>Date</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Settled</th>
                            <th class="text-end">Due</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($obligations as $o): ?>
                            <?php $stsColor = ['unpaid'=>'danger','partial'=>'warning','paid'=>'success'][$o['payment_status']] ?? 'secondary'; ?>
                            <tr>
                                <td><strong><?= e($o['obligation_number']) ?></strong></td>
                                <td class="text-nowrap"><?= e($o['obligation_date']) ?></td>
                                <td class="text-end">₹<?= number_format((float) $o['obligation_amount'], 2) ?></td>
                                <td class="text-end text-muted">₹<?= number_format((float) $o['paid_amount'], 2) ?></td>
                                <td class="text-end fw-bold <?= (float) $o['due_amount'] > 0.001 ? 'text-danger' : '' ?>">
                                    ₹<?= number_format((float) $o['due_amount'], 2) ?>
                                </td>
                                <td><span class="badge text-bg-<?= $stsColor ?>"><?= e($o['payment_status']) ?></span></td>
                                <td class="small text-muted"><?= e($o['remarks'] ?? '') ?></td>
                                <td class="text-end">
                                    <?php if ($isAdmin): ?>
                                        <form method="post" action="<?= url('/old-records/obligations/' . $o['id'] . '/delete') ?>"
                                              class="d-inline"
                                              onsubmit="return confirm('Delete? Will fail if any settlement is recorded against it.')">
                                            <?= \App\Support\Csrf::field() ?>
                                            <button class="btn btn-sm btn-outline-danger" title="Delete (admin)"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="collapse mb-3" id="addObligForm">
    <div class="card shadow-sm border-primary">
        <div class="card-header bg-white"><strong>Add <?= strtolower($obligOne) ?></strong></div>
        <div class="card-body">
            <form method="post" action="<?= url('/old-records/' . $kind . '/' . (int) $entity['id'] . '/obligations') ?>" novalidate>
                <?= \App\Support\Csrf::field() ?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="obligation_number"><?= $obligNumberHd ?> <span class="text-danger">*</span></label>
                        <input type="text" id="obligation_number" name="obligation_number" maxlength="100" required class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="obligation_date">Date <span class="text-danger">*</span></label>
                        <input type="date" id="obligation_date" name="obligation_date" required class="form-control" value="<?= e(date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="obligation_amount">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" id="obligation_amount" name="obligation_amount" min="0.01" step="0.01" required class="form-control text-end">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="opening_paid_amount">Already settled (₹)</label>
                        <input type="number" id="opening_paid_amount" name="opening_paid_amount" min="0" step="0.01" value="0" class="form-control text-end">
                        <div class="form-text small">opening pre-v2; no account effect</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="oblig_remarks">Remarks</label>
                        <input type="text" id="oblig_remarks" name="remarks" maxlength="500" class="form-control">
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Settlements -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong><?= $settleLabel ?>
            <i class="bi bi-<?= $cashIcon ?> ms-2" title="<?= $isCustomer ? 'Money in' : 'Money out' ?>"></i>
        </strong>
        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addSettleForm">
            <i class="bi bi-plus"></i> Record <?= strtolower($settleOne) ?>
        </button>
    </div>
    <div class="card-body p-0">
        <?php if ($settlements === []): ?>
            <p class="text-center text-muted py-3 mb-0">No <?= strtolower($settleLabel) ?> yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Against <?= strtolower($obligOne) ?></th>
                            <th>Mode</th>
                            <th>Reference</th>
                            <th><?= $cashSideLabel ?></th>
                            <th class="text-end">Amount</th>
                            <th>Remarks</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($settlements as $s): ?>
                            <tr>
                                <td class="text-nowrap"><?= e($s['settlement_date']) ?></td>
                                <td>
                                    <?php if (!empty($s['obligation_number'])): ?>
                                        <span class="font-monospace"><?= e($s['obligation_number']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">— unallocated</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge text-bg-info"><?= e($s['payment_mode']) ?></span></td>
                                <td class="font-monospace small"><?= e($s['payment_reference'] ?? '') ?></td>
                                <td class="small"><?= e($s['account_name'] ?? '') ?></td>
                                <td class="text-end fw-bold"><?= $isCustomer ? '+' : '−' ?>₹<?= number_format((float) $s['amount'], 2) ?></td>
                                <td class="small text-muted"><?= e($s['remarks'] ?? '') ?></td>
                                <td class="text-end">
                                    <?php if ($isAdmin): ?>
                                        <form method="post" action="<?= url('/old-records/settlements/' . $s['id'] . '/delete') ?>"
                                              class="d-inline"
                                              onsubmit="return confirm('Delete this settlement? Account effect will be reversed.')">
                                            <?= \App\Support\Csrf::field() ?>
                                            <button class="btn btn-sm btn-outline-danger" title="Delete (admin)"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="collapse mb-3" id="addSettleForm">
    <div class="card shadow-sm border-success">
        <div class="card-header bg-white"><strong>Record <?= strtolower($settleOne) ?></strong></div>
        <div class="card-body">
            <form method="post" action="<?= url('/old-records/' . $kind . '/' . (int) $entity['id'] . '/settlements') ?>" novalidate>
                <?= \App\Support\Csrf::field() ?>
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label" for="settlement_date">Date <span class="text-danger">*</span></label>
                        <input type="date" id="settlement_date" name="settlement_date" required class="form-control" value="<?= e(date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="obligation_id">Apply to <?= strtolower($obligOne) ?></label>
                        <select id="obligation_id" name="obligation_id" class="form-select">
                            <option value="">— Unallocated (advance) —</option>
                            <?php foreach ($unpaidObligs as $u): ?>
                                <option value="<?= (int) $u['id'] ?>">
                                    <?= e($u['obligation_number']) ?> · due ₹<?= number_format((float) $u['due_amount'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="amount">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" id="amount" name="amount" min="0.01" step="0.01" required class="form-control text-end">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="payment_mode">Mode</label>
                        <select id="payment_mode" name="payment_mode" class="form-select">
                            <?php foreach (['cash','cheque','neft','imps','rtgs','upi','card','other'] as $m): ?>
                                <option value="<?= $m ?>"><?= strtoupper($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="payment_reference">UTR / Cheque #</label>
                        <input type="text" id="payment_reference" name="payment_reference" maxlength="100" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= $cashSideLabel ?> <span class="text-danger">*</span></label>
                        <select name="account" class="form-select" required>
                            <option value="">— Select bank or drawer —</option>
                            <?php if (!empty($banks)): ?>
                                <optgroup label="Bank Accounts">
                                    <?php foreach ($banks as $b): ?>
                                        <option value="bank_account:<?= (int) $b['id'] ?>">
                                            🏦 <?= e($b['name']) ?> (₹<?= number_format((float) $b['current_balance'], 2) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($drawers)): ?>
                                <optgroup label="Cashier Drawers">
                                    <?php foreach ($drawers as $d): ?>
                                        <option value="cashier_drawer:<?= (int) $d['id'] ?>">
                                            💵 <?= e($d['name']) ?> (₹<?= number_format((float) $d['current_balance'], 2) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="settle_remarks">Remarks</label>
                        <input type="text" id="settle_remarks" name="remarks" maxlength="500" class="form-control">
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-success">
                        <i class="bi bi-check-lg"></i> Record <?= $settleOne ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
