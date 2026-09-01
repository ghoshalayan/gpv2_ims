<?php
/** @var array  $customers */
/** @var array  $banks */
/** @var array  $drawers */
/** @var ?array $context */    // ['customer', 'unpaid_invoices', 'available_advances', 'available_advance_total'] when customer picked
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-collection"></i> Bulk Customer Payment</h1>
    <a href="<?= url('/sales-payments') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Sales Payments
    </a>
</div>

<div class="alert alert-info py-2 small">
    Receive a single payment from one customer and split it across all their unpaid invoices in one go.
    System auto-allocates oldest-first; you can edit any per-invoice amount.
    Cheque payments still go via the per-invoice page (single cheque = single invoice).
</div>

<!-- Customer picker (always shown) -->
<form method="get" action="<?= url('/customer-payments/create') ?>" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="customer_picker">Customer <span class="text-danger">*</span></label>
                <select id="customer_picker" name="customer_id" class="form-select" required onchange="this.form.submit()">
                    <option value="">— Pick customer —</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"
                            <?= $context !== null && (int) $context['customer']['id'] === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 text-muted small">
                Pick the customer who's paying. The form below appears with their unpaid invoices.
            </div>
        </div>
    </div>
</form>

<?php if ($context === null): ?>
    <div class="text-center text-muted py-4">Pick a customer to begin.</div>
    <?php return; ?>
<?php endif; ?>

<?php
    $unpaid       = $context['unpaid_invoices'];
    $advances     = $context['available_advances'];
    $advanceTotal = (float) $context['available_advance_total'];
    $totalDue     = 0.0;
    foreach ($unpaid as $inv) { $totalDue += (float) $inv['due_amount']; }
?>

<?php if ($unpaid === []): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> <strong><?= e($context['customer']['name']) ?></strong> has no unpaid invoices.
        <?php if ($advanceTotal > 0): ?>
            (They have ₹<?= number_format($advanceTotal, 2) ?> advance balance available.)
        <?php endif; ?>
    </div>
<?php else: ?>

<form method="post" action="<?= url('/customer-payments') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <input type="hidden" name="customer_id" value="<?= (int) $context['customer']['id'] ?>">

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small">Total outstanding</div>
                    <div class="h5 mb-0">₹<?= number_format($totalDue, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small">Open invoices</div>
                    <div class="h5 mb-0"><?= count($unpaid) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small">Available advance</div>
                    <div class="h5 mb-0 text-success">₹<?= number_format($advanceTotal, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100 bg-light">
                <div class="card-body text-center">
                    <div class="text-muted small">Payment total</div>
                    <div class="h4 mb-0 fw-bold" id="totalPayment">₹0.00</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Receive payment</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="payment_date">Payment Date <span class="text-danger">*</span></label>
                    <input type="date" id="payment_date" name="payment_date" required
                           class="form-control" value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="col-md-9">
                    <label class="form-label">Receiving Account <small class="text-muted">(required if cash or online > 0)</small></label>
                    <select name="received_into" class="form-select">
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

                <div class="col-md-3">
                    <label class="form-label" for="cash_amount"><i class="bi bi-cash"></i> Cash (₹)</label>
                    <input type="number" id="cash_amount" name="cash_amount" min="0" step="0.01" value="0"
                           class="form-control payment-amount">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="online_amount"><i class="bi bi-bank"></i> Online (₹)</label>
                    <input type="number" id="online_amount" name="online_amount" min="0" step="0.01" value="0"
                           class="form-control payment-amount">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="online_reference">Online Reference / UTR</label>
                    <input type="text" id="online_reference" name="online_reference" maxlength="100" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Apply From Advance</label>
                    <?php if ($advances === []): ?>
                        <input type="text" class="form-control" readonly value="No advance available">
                        <input type="hidden" name="from_advance_amount" value="0">
                        <input type="hidden" name="from_advance_id" value="">
                    <?php else: ?>
                        <select name="from_advance_id" id="from_advance_id" class="form-select form-select-sm">
                            <option value="" data-balance="0">— None —</option>
                            <?php foreach ($advances as $a): ?>
                                <option value="<?= (int) $a['id'] ?>" data-balance="<?= e($a['balance']) ?>">
                                    <?= e($a['advance_number']) ?> · ₹<?= number_format((float) $a['balance'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="input-group input-group-sm mt-1">
                            <input type="number" id="from_advance_amount" name="from_advance_amount"
                                   min="0" step="0.01" value="0" class="form-control payment-amount" placeholder="amount">
                            <button type="button" id="advanceMaxBtn" class="btn btn-outline-info">Max</button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label" for="remarks">Remarks (carried to all generated payment rows)</label>
                    <input type="text" id="remarks" name="remarks" maxlength="500" class="form-control">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Allocation</strong>
            <div class="d-flex gap-2 align-items-center">
                <span class="text-muted small">
                    To allocate: <strong id="toAllocate">₹0.00</strong>
                    · Allocated: <strong id="allocated">₹0.00</strong>
                    · Remaining:
                </span>
                <span id="unallocatedBadge" class="badge text-bg-secondary fs-6">₹0.00</span>
                <button type="button" id="autofillBtn" class="btn btn-sm btn-outline-primary">
                    Auto-fill oldest first
                </button>
                <button type="button" id="clearBtn" class="btn btn-sm btn-outline-secondary">Clear</button>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th class="text-end">Grand Total</th>
                        <th class="text-end">Due</th>
                        <th class="text-end" style="width:200px">Apply (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unpaid as $inv): ?>
                        <tr>
                            <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                            <td class="text-nowrap"><?= e($inv['invoice_date']) ?></td>
                            <td class="text-end text-muted">₹<?= number_format((float) $inv['grand_total'], 2) ?></td>
                            <td class="text-end fw-bold text-danger" data-due="<?= e($inv['due_amount']) ?>">
                                ₹<?= number_format((float) $inv['due_amount'], 2) ?>
                            </td>
                            <td class="text-end">
                                <input type="number" min="0" step="0.01" value="0"
                                       max="<?= e($inv['due_amount']) ?>"
                                       class="form-control form-control-sm text-end alloc-input"
                                       data-due="<?= e($inv['due_amount']) ?>"
                                       name="allocations[<?= (int) $inv['id'] ?>]">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Post Bulk Payment</button>
        <a href="<?= url('/customer-payments/create?customer_id=' . $context['customer']['id']) ?>"
           class="btn btn-outline-secondary">Reset</a>
    </div>
</form>

<script>
    (function () {
        const cashEl    = document.getElementById('cash_amount');
        const onlineEl  = document.getElementById('online_amount');
        const advAmtEl  = document.getElementById('from_advance_amount');
        const advSelEl  = document.getElementById('from_advance_id');
        const advMaxBtn = document.getElementById('advanceMaxBtn');
        const totalEl   = document.getElementById('totalPayment');
        const allocInputs = document.querySelectorAll('.alloc-input');
        const toAllocateEl = document.getElementById('toAllocate');
        const allocatedEl  = document.getElementById('allocated');
        const remBadge     = document.getElementById('unallocatedBadge');
        const autofillBtn  = document.getElementById('autofillBtn');
        const clearBtn     = document.getElementById('clearBtn');

        function num(el) { return parseFloat(el?.value || '0') || 0; }
        function totalPayment() {
            return num(cashEl) + num(onlineEl) + num(advAmtEl);
        }
        function totalAllocated() {
            let s = 0;
            allocInputs.forEach(i => s += num(i));
            return s;
        }
        function refresh() {
            const t = totalPayment();
            const a = totalAllocated();
            const r = t - a;
            totalEl.textContent = '₹' + t.toFixed(2);
            toAllocateEl.textContent = '₹' + t.toFixed(2);
            allocatedEl.textContent = '₹' + a.toFixed(2);
            remBadge.textContent = '₹' + r.toFixed(2);
            remBadge.className = 'badge fs-6 ' + (
                Math.abs(r) < 0.01 ? 'text-bg-success' :
                r > 0              ? 'text-bg-warning' :
                                     'text-bg-danger'
            );
        }
        function autofill() {
            let remaining = totalPayment();
            allocInputs.forEach(input => {
                const due = parseFloat(input.dataset.due || '0') || 0;
                const apply = Math.max(0, Math.min(remaining, due));
                input.value = apply.toFixed(2);
                remaining = Math.max(0, remaining - apply);
            });
            refresh();
        }
        function clearAll() {
            allocInputs.forEach(i => i.value = 0);
            refresh();
        }

        [cashEl, onlineEl, advAmtEl].forEach(el => el?.addEventListener('input', refresh));
        allocInputs.forEach(el => el.addEventListener('input', refresh));
        autofillBtn?.addEventListener('click', autofill);
        clearBtn?.addEventListener('click', clearAll);

        if (advSelEl) {
            advSelEl.addEventListener('change', () => { if (advAmtEl) advAmtEl.value = 0; refresh(); });
            advMaxBtn?.addEventListener('click', () => {
                if (!advAmtEl) return;
                const opt = advSelEl.options[advSelEl.selectedIndex];
                const bal = parseFloat(opt?.dataset.balance || '0') || 0;
                advAmtEl.value = bal.toFixed(2);
                refresh();
            });
        }

        refresh();
    })();
</script>

<?php endif; ?>
