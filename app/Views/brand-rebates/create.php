<?php
/** @var array  $brands */
/** @var array  $suppliers */
/** @var array  $banks */
/** @var array  $drawers */
/** @var int    $preBrand */
/** @var int    $preSupplier */
/** @var string $preMode */
/** @var array  $brandProducts */
/** @var array  $brandInvoices */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-gift"></i> Post Brand Rebate</h1>
    <a href="<?= url('/brand-rebates') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-info py-2 small">
    <strong>Step 1:</strong> Pick the brand + mode below. The form will reveal the
    mode-specific fields after the page reloads. <strong>Step 2:</strong> Fill in details and post.
    The brand-issued credit note number + date are required for every rebate.
</div>

<!-- Step 1: brand + mode (page-reload picker) -->
<form method="get" action="<?= url('/brand-rebates/create') ?>" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="pick_brand">Brand <span class="text-danger">*</span></label>
                <select id="pick_brand" name="brand_id" class="form-select" required onchange="this.form.submit()">
                    <option value="">— Pick brand —</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= $preBrand === (int) $b['id'] ? 'selected' : '' ?>>
                            <?= e($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="pick_supplier">Supplier <span class="text-danger">*</span></label>
                <select id="pick_supplier" name="supplier_id" class="form-select" required onchange="this.form.submit()">
                    <option value="">— Pick supplier —</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $preSupplier === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="pick_mode">Mode <span class="text-danger">*</span></label>
                <select id="pick_mode" name="mode" class="form-select" required onchange="this.form.submit()">
                    <option value="">— Pick mode —</option>
                    <?php foreach ([
                        'cash_back'      => 'Cash Back (money in)',
                        'reduce_payable' => 'Reduce Payable (offset future bills)',
                        'cost_reduction' => 'Cost Reduction (recompute landed cost)',
                        'free_stock'     => 'Free Stock (extra units)',
                    ] as $m => $label): ?>
                        <option value="<?= $m ?>" <?= $preMode === $m ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 text-muted small">
                Pick brand, supplier and mode to reveal the rest of the form.
            </div>
        </div>
    </div>
</form>

<?php if ($preBrand <= 0 || $preSupplier <= 0 || $preMode === ''): ?>
    <div class="text-center text-muted py-4">Pick brand, supplier and mode to begin.</div>
    <?php return; ?>
<?php endif; ?>

<?php $old = static fn(string $k, $d = '') => e(old($k, $d)); ?>

<!-- Step 2: mode-specific form -->
<form method="post" action="<?= url('/brand-rebates') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <input type="hidden" name="brand_id"    value="<?= (int) $preBrand ?>">
    <input type="hidden" name="supplier_id" value="<?= (int) $preSupplier ?>">
    <input type="hidden" name="mode"        value="<?= e($preMode) ?>">

    <!-- Common fields -->
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Credit note (from brand)</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="rebate_date">Rebate Date <span class="text-danger">*</span></label>
                    <input type="date" id="rebate_date" name="rebate_date" required class="form-control"
                           value="<?= $old('rebate_date', date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="credit_note_number">Credit Note # <span class="text-danger">*</span></label>
                    <input type="text" id="credit_note_number" name="credit_note_number" maxlength="100" required
                           class="form-control" value="<?= $old('credit_note_number') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="credit_note_date">Credit Note Date <span class="text-danger">*</span></label>
                    <input type="date" id="credit_note_date" name="credit_note_date" required class="form-control"
                           value="<?= $old('credit_note_date', date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="amount">Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" id="amount" name="amount" min="0.01" step="0.01" required
                           class="form-control text-end" value="<?= $old('amount') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="remarks">Remarks</label>
                    <input type="text" id="remarks" name="remarks" maxlength="500" class="form-control"
                           value="<?= $old('remarks') ?>">
                </div>
            </div>
        </div>
    </div>

    <?php if ($preMode === 'cash_back'): ?>
        <div class="card shadow-sm mb-3 border-success">
            <div class="card-header bg-white"><strong>Mode: Cash Back</strong>
                <span class="text-muted small ms-2">Money is received from brand into chosen account</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Receive Into <span class="text-danger">*</span></label>
                        <select name="received_into_kind_id" class="form-select" required
                                onchange="(function(el){
                                    const v=el.value; const kind=v.split(':')[0]; const id=v.split(':')[1];
                                    document.getElementById('rcv_kind').value = kind || '';
                                    document.getElementById('rcv_id').value   = id   || '';
                                })(this)">
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
                        <input type="hidden" id="rcv_kind" name="received_into_kind" value="">
                        <input type="hidden" id="rcv_id"   name="received_into_id"   value="">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="payment_mode">Payment Mode</label>
                        <select id="payment_mode" name="payment_mode" class="form-select">
                            <?php foreach (['cash','neft','imps','rtgs','upi','cheque','other'] as $m): ?>
                                <option value="<?= $m ?>"><?= strtoupper($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="payment_reference">UTR / Cheque #</label>
                        <input type="text" id="payment_reference" name="payment_reference" maxlength="100"
                               class="form-control" value="<?= $old('payment_reference') ?>">
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($preMode === 'reduce_payable'): ?>
        <div class="card shadow-sm mb-3 border-primary">
            <div class="card-header bg-white"><strong>Mode: Reduce Payable</strong>
                <span class="text-muted small ms-2">Auto-creates a non-cash supplier advance — apply against future bills</span>
            </div>
            <div class="card-body small">
                <p class="mb-1">A supplier advance for ₹<span id="advance_preview">0.00</span> will be created with
                    <code>payment_mode = 'rebate'</code> (no cash leg).</p>
                <p class="mb-0 text-muted">
                    Use the "Apply From Advance" panel on the next purchase-payment page (or the bulk-supplier-payment form)
                    to redeem this credit. Voiding the rebate also voids the advance — provided it hasn't been applied yet.
                </p>
            </div>
        </div>

    <?php elseif ($preMode === 'cost_reduction'): ?>
        <div class="card shadow-sm mb-3 border-warning">
            <div class="card-header bg-white"><strong>Mode: Cost Reduction</strong>
                <span class="text-muted small ms-2">Reduces moving-avg cost on on-hand stock; spillover crosses to income</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="target_invoice_id">Scope to a specific purchase invoice (optional)</label>
                        <select id="target_invoice_id" name="target_invoice_id" class="form-select">
                            <option value="">All on-hand stock for this brand (default)</option>
                            <?php foreach ($brandInvoices as $inv): ?>
                                <option value="<?= (int) $inv['id'] ?>">
                                    <?= e($inv['invoice_number']) ?> · <?= e($inv['invoice_date']) ?>
                                    · <?= e($inv['supplier_name']) ?>
                                    · ₹<?= number_format((float) $inv['grand_total'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">
                            If chosen, the rebate distributes only across products on that bill (limited to current on-hand
                            quantities). Else it spreads across all on-hand stock for the brand by value share.
                        </div>
                    </div>
                </div>
                <hr>
                <div class="small">
                    <strong>On-hand snapshot for this brand:</strong>
                    <?php
                        $totalOnHandValue = 0.0;
                        $cands = [];
                        foreach ($brandProducts as $p) {
                            $qty = (int) $p['quantity_on_hand'];
                            $avg = (float) $p['moving_avg_cost'];
                            if ($qty > 0 && $avg > 0) {
                                $totalOnHandValue += $qty * $avg;
                                $cands[] = $p;
                            }
                        }
                    ?>
                    <?php if ($cands === []): ?>
                        <div class="alert alert-warning py-2 small mt-2 mb-0">
                            No on-hand stock available — this rebate would entirely spill to "Other Income".
                            Consider Cash Back mode instead.
                        </div>
                    <?php else: ?>
                        <span class="text-muted">total on-hand value</span>
                        <strong>₹<?= number_format($totalOnHandValue, 2) ?></strong>
                        across <?= count($cands) ?> product(s).
                        <details class="mt-2">
                            <summary class="text-muted small">Show breakdown</summary>
                            <table class="table table-sm mt-2 mb-0">
                                <thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Moving Avg</th><th class="text-end">Value</th></tr></thead>
                                <tbody>
                                    <?php foreach ($cands as $p): ?>
                                        <tr>
                                            <td><?= e($p['name']) ?></td>
                                            <td class="text-end"><?= (int) $p['quantity_on_hand'] ?> <?= e($p['unit']) ?></td>
                                            <td class="text-end">₹<?= number_format((float) $p['moving_avg_cost'], 4) ?></td>
                                            <td class="text-end">₹<?= number_format((int) $p['quantity_on_hand'] * (float) $p['moving_avg_cost'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </details>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($preMode === 'free_stock'): ?>
        <div class="card shadow-sm mb-3 border-info">
            <div class="card-header bg-white"><strong>Mode: Free Stock</strong>
                <span class="text-muted small ms-2">Extra units valued at cost; preserves moving-avg</span>
            </div>
            <div class="card-body">
                <?php if ($brandProducts === []): ?>
                    <div class="alert alert-warning small py-2 mb-0">
                        No active products under this brand. Create at least one before posting a free-stock rebate.
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label" for="free_product_id">Free Product <span class="text-danger">*</span></label>
                            <select id="free_product_id" name="free_product_id" class="form-select" required
                                    onchange="(function(s){
                                        const opt = s.options[s.selectedIndex];
                                        const avg = parseFloat(opt?.dataset.avg || '0') || 0;
                                        const valEl = document.getElementById('free_value_per_unit');
                                        if (valEl && (!valEl.value || parseFloat(valEl.value) === 0)) {
                                            valEl.value = avg.toFixed(4);
                                        }
                                        recalcDerived();
                                    })(this)">
                                <option value="">— Pick product —</option>
                                <?php foreach ($brandProducts as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>"
                                            data-avg="<?= e($p['moving_avg_cost']) ?>"
                                            data-unit="<?= e($p['unit']) ?>">
                                        <?= e($p['name']) ?>
                                        · stock <?= (int) $p['quantity_on_hand'] ?> <?= e($p['unit']) ?>
                                        · avg ₹<?= number_format((float) $p['moving_avg_cost'], 4) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="free_quantity">Free Quantity <span class="text-danger">*</span></label>
                            <input type="number" id="free_quantity" name="free_quantity" min="1" step="1" required
                                   class="form-control text-end" value="<?= $old('free_quantity') ?>"
                                   oninput="recalcDerived()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="free_value_per_unit">Value per Unit (₹)
                                <small class="text-muted">defaults to moving-avg</small>
                            </label>
                            <input type="number" id="free_value_per_unit" name="free_value_per_unit" min="0" step="0.0001"
                                   class="form-control text-end" value="<?= $old('free_value_per_unit') ?>"
                                   oninput="recalcDerived()">
                        </div>
                        <div class="col-12 small">
                            <span class="text-muted">qty × value/unit =</span>
                            <strong>₹<span id="derived_total">0.00</span></strong>
                            — must match the rebate amount above (within ₹1 rounding).
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Post Rebate</button>
        <a href="<?= url('/brand-rebates') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    (function () {
        const amtEl = document.getElementById('amount');
        const advPrev = document.getElementById('advance_preview');
        function refreshAdvancePreview() {
            if (!advPrev) return;
            const v = parseFloat(amtEl?.value || '0') || 0;
            advPrev.textContent = v.toFixed(2);
        }
        amtEl?.addEventListener('input', refreshAdvancePreview);
        refreshAdvancePreview();

        window.recalcDerived = function () {
            const q = parseFloat(document.getElementById('free_quantity')?.value || '0') || 0;
            const v = parseFloat(document.getElementById('free_value_per_unit')?.value || '0') || 0;
            const out = document.getElementById('derived_total');
            if (out) out.textContent = (q * v).toFixed(2);
        };
        recalcDerived();
    })();
</script>
