<?php /** @var array $invoices */ ?>
<?php /** @var array $banks */ /** @var array $drawers */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-arrow-counterclockwise"></i> New Sales Return</h1>
    <a href="<?= url('/sales-returns') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-info py-2 small">
    Step 1 — pick the originating sales invoice. Step 2 (next page) — tick lines + qty to return,
    then finalize. Stock returns at the snapshotted COGS, and per-line commission is reversed.
    The return value first reduces the customer's due on this invoice; choose
    <strong>Cash refund</strong> to also pay back any already-collected excess from a bank/drawer.
</div>

<form method="post" action="<?= url('/sales-returns') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Header</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="original_sales_invoice_id">
                        Original Sales Invoice <span class="text-danger">*</span>
                    </label>
                    <select id="original_sales_invoice_id" name="original_sales_invoice_id" class="form-select" required>
                        <option value="">— Select finalized invoice —</option>
                        <?php foreach ($invoices as $inv): ?>
                            <option value="<?= (int) $inv['id'] ?>">
                                <?= e($inv['invoice_number']) ?> · <?= e($inv['customer_name']) ?>
                                · <?= e($inv['invoice_date']) ?>
                                · ₹<?= number_format((float) $inv['grand_total'], 2) ?>
                                <?php if ((float) $inv['due_amount'] > 0): ?>
                                    · due ₹<?= number_format((float) $inv['due_amount'], 2) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Most-recent 200 finalized invoices are listed.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="return_date">Return Date <span class="text-danger">*</span></label>
                    <input type="date" id="return_date" name="return_date"
                           class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="refund_method">Refund Method</label>
                    <select id="refund_method" name="refund_method" class="form-select no-select2">
                        <option value="credit_note" selected>Credit Note (reduces customer due)</option>
                        <option value="cash">Cash refund (already-collected excess)</option>
                    </select>
                </div>
                <div class="col-md-6 d-none" id="refund_account_wrap">
                    <label class="form-label" for="refund_account">Refund From <span class="text-danger">*</span></label>
                    <select id="refund_account" name="refund_account" class="form-select no-select2">
                        <option value="">— Select bank / drawer —</option>
                        <?php if ($banks !== []): ?>
                            <optgroup label="Bank Accounts">
                                <?php foreach ($banks as $b): ?>
                                    <option value="bank_account:<?= (int) $b['id'] ?>">
                                        <?= e($b['name']) ?><?= $b['bank_name'] ? ' · ' . e($b['bank_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if ($drawers !== []): ?>
                            <optgroup label="Cashier Drawers">
                                <?php foreach ($drawers as $d): ?>
                                    <option value="cashier_drawer:<?= (int) $d['id'] ?>">
                                        <?= e($d['name']) ?><?= $d['location'] ? ' · ' . e($d['location']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                    <div class="form-text">
                        Refunded cash = return total − whatever still nets against the invoice's due.
                        Computed at finalize.
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="reason">Reason</label>
                    <textarea id="reason" name="reason" rows="2" maxlength="500"
                              class="form-control" placeholder="e.g. damaged in transit, customer changed mind"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Create Draft &amp; Pick Lines
        </button>
        <a href="<?= url('/sales-returns') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    (function () {
        var method = document.getElementById('refund_method');
        var wrap   = document.getElementById('refund_account_wrap');
        var acc    = document.getElementById('refund_account');
        if (!method || !wrap || !acc) return;
        function sync() {
            var isCash = method.value === 'cash';
            wrap.classList.toggle('d-none', !isCash);
            acc.required = isCash;
            if (!isCash) acc.value = '';
        }
        method.addEventListener('change', sync);
        sync();
    })();
</script>
