<?php
/** @var array $salesmen */
/** @var array $banks */
/** @var array $drawers */
/** @var array $products */
/** @var array $outstanding */
/** @var int   $preSalesman */
/** @var array $linkedMap */    // [salesmanId => ['customer_id', 'customer_name', 'unpaid_invoices' => [...]]]
?>
<?php
$outstandingMap = [];
foreach ($outstanding as $o) {
    $net = (float) $o['earned'] - (float) $o['reversed'] - (float) $o['settled'];
    $outstandingMap[(int) $o['salesman_id']] = $net;
}
// Encode for client JS so the form can swap the linked-customer panel on salesman change.
$linkedJson = \json_encode($linkedMap, JSON_UNESCAPED_UNICODE);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> Settle Commission</h1>
    <a href="<?= url('/commission-settlements') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-info py-2 small">
    Settle a salesman's outstanding commission as <strong>cash</strong> (debits a paying account)
    or <strong>goods</strong> (stocks out a product at <em>current moving-avg cost</em>, never
    selling price). Per D21 — goods value applied against the commission balance equals
    qty × landed cost.
</div>

<form method="post" action="<?= url('/commission-settlements') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Settlement</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="settlement_date">Date <span class="text-danger">*</span></label>
                    <input type="date" id="settlement_date" name="settlement_date" required
                           class="form-control" value="<?= e(old('settlement_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="salesman_id">Salesman <span class="text-danger">*</span></label>
                    <select id="salesman_id" name="salesman_id" class="form-select" required>
                        <option value="">— Select —</option>
                        <?php foreach ($salesmen as $sm): ?>
                            <?php $net = $outstandingMap[(int) $sm['id']] ?? 0.0; ?>
                            <option value="<?= (int) $sm['id'] ?>"
                                data-outstanding="<?= e((string) $net) ?>"
                                <?= (int) $preSalesman === (int) $sm['id'] || (int) old('salesman_id') === (int) $sm['id'] ? 'selected' : '' ?>>
                                <?= e($sm['name']) ?>
                                <?= $net > 0.0001 ? '· outstanding ₹' . number_format($net, 2) : ($net < -0.0001 ? '· OVER-PAID ₹' . number_format(-$net, 2) : '· settled') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text small" id="outstanding_text"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payout Mode <span class="text-danger">*</span></label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="payout_mode" id="mode_cash" value="cash"
                               <?= old('payout_mode', 'cash') === 'cash' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-success" for="mode_cash">
                            <i class="bi bi-cash"></i> Cash
                        </label>
                        <input type="radio" class="btn-check" name="payout_mode" id="mode_goods" value="goods"
                               <?= old('payout_mode') === 'goods' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-info" for="mode_goods">
                            <i class="bi bi-box-seam"></i> Goods
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cash mode -->
    <div class="card shadow-sm mb-3" id="cash_panel">
        <div class="card-header bg-white"><strong>Cash payout</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="cash_amount">Cash Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" id="cash_amount" name="cash_amount" min="0.01" step="0.01"
                           class="form-control" value="<?= e(old('cash_amount')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Paid From <span class="text-danger">*</span></label>
                    <select name="paid_from" class="form-select">
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
                    <label class="form-label">Mode</label>
                    <select name="payment_mode" class="form-select">
                        <?php foreach (['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other'] as $m): ?>
                            <option value="<?= $m ?>" <?= old('payment_mode') === $m ? 'selected' : '' ?>>
                                <?= strtoupper($m) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Reference</label>
                    <input type="text" name="payment_reference" maxlength="100"
                           class="form-control" value="<?= e(old('payment_reference')) ?>"
                           placeholder="UTR / cheque #">
                </div>
            </div>
        </div>
    </div>

    <!-- Apply-to-bill (only when salesman has linked_customer with unpaid invoices, cash mode only) -->
    <div class="card shadow-sm mb-3 border-info" id="apply_panel" style="display:none">
        <div class="card-header bg-info-subtle">
            <strong><i class="bi bi-link-45deg"></i> Apply to linked customer's bill</strong>
            <span class="text-muted small ms-2">— optional one-click cross-link</span>
        </div>
        <div class="card-body">
            <div class="form-check form-switch mb-2">
                <input type="hidden" name="apply_to_bill" value="0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="apply_to_bill" name="apply_to_bill" value="1">
                <label class="form-check-label" for="apply_to_bill">
                    Apply this payout to <strong id="linked_customer_name">…</strong>'s outstanding bill
                </label>
            </div>
            <div class="form-text small mb-2">
                Posts <em>two</em> rows: a commission settlement (debits the drawer) and a sales payment
                (credits the same drawer). Net cash movement is zero — pure paper transfer.
            </div>
            <div id="apply_invoice_wrap" style="display:none">
                <label class="form-label" for="apply_to_invoice_id">Apply to invoice</label>
                <select name="apply_to_invoice_id" id="apply_to_invoice_id" class="form-select"></select>
                <div class="form-text small">
                    Cash amount above must be ≤ invoice's remaining due. Pick an invoice or split into multiple settlements.
                </div>
            </div>
        </div>
    </div>

    <!-- Goods mode -->
    <div class="card shadow-sm mb-3" id="goods_panel" style="display:none">
        <div class="card-header bg-white"><strong>Goods payout</strong>
            <span class="text-muted small ms-2">(stock-out at current moving-avg cost)</span>
        </div>
        <div class="card-body">
            <?php if (empty($products)): ?>
                <div class="alert alert-warning small mb-0">
                    No products with stock available. Settle in cash, or wait for purchase to land.
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Product</label>
                        <select name="goods_product_id" id="goods_product_id" class="form-select">
                            <option value="">— Select product —</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"
                                        data-stock="<?= e($p['quantity_on_hand']) ?>"
                                        data-cost="<?= e($p['moving_avg_cost']) ?>"
                                        data-unit="<?= e($p['unit']) ?>"
                                        data-brand="<?= e($p['brand_name']) ?>">
                                    <?= e($p['name']) ?> (<?= e($p['brand_name']) ?> · stock <?= number_format((float) $p['quantity_on_hand'], 3) ?> <?= e($p['unit']) ?> @ ₹<?= number_format((float) $p['moving_avg_cost'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="goods_quantity" id="goods_quantity" min="0.001" step="0.001"
                               class="form-control" value="<?= e(old('goods_quantity')) ?>">
                        <div class="form-text small" id="stock_text"></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total Value</label>
                        <input type="text" id="goods_total_preview" class="form-control" readonly value="₹0.00">
                        <div class="form-text small" id="cost_text"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <label class="form-label" for="remarks">Remarks</label>
            <textarea id="remarks" name="remarks" rows="2" maxlength="500" class="form-control"><?= e(old('remarks')) ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Post Settlement</button>
        <a href="<?= url('/commission-settlements') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    window.commissionLinkedMap = <?= $linkedJson ?: '{}' ?>;

    (function () {
        const cashPanel  = document.getElementById('cash_panel');
        const goodsPanel = document.getElementById('goods_panel');
        const applyPanel = document.getElementById('apply_panel');
        const radios     = document.querySelectorAll('input[name="payout_mode"]');

        const sm = document.getElementById('salesman_id');
        const outText = document.getElementById('outstanding_text');
        const linkedNameEl = document.getElementById('linked_customer_name');
        const applyToggle  = document.getElementById('apply_to_bill');
        const applyWrap    = document.getElementById('apply_invoice_wrap');
        const invoiceSel   = document.getElementById('apply_to_invoice_id');

        function currentMode() {
            return document.querySelector('input[name="payout_mode"]:checked')?.value || 'cash';
        }
        function refreshPanels() {
            const mode = currentMode();
            cashPanel.style.display  = mode === 'cash'  ? '' : 'none';
            goodsPanel.style.display = mode === 'goods' ? '' : 'none';
            // Apply panel: only show when cash mode AND linked customer with unpaid invoices.
            const linked = window.commissionLinkedMap[parseInt(sm.value || '0')];
            const hasInvoices = linked && linked.unpaid_invoices && linked.unpaid_invoices.length > 0;
            const showApply = mode === 'cash' && hasInvoices;
            applyPanel.style.display = showApply ? '' : 'none';
            if (showApply) {
                linkedNameEl.textContent = linked.customer_name;
                invoiceSel.innerHTML = linked.unpaid_invoices.map(inv =>
                    '<option value="' + inv.id + '" data-due="' + inv.due_amount + '">' +
                    'Inv ' + inv.invoice_number + ' · ' + inv.invoice_date +
                    ' · due ₹' + parseFloat(inv.due_amount).toFixed(2) +
                    '</option>'
                ).join('');
            } else {
                applyToggle.checked = false;
                applyWrap.style.display = 'none';
            }
        }
        function refreshOutstanding() {
            const opt = sm.options[sm.selectedIndex];
            const out = parseFloat(opt?.dataset.outstanding || '0');
            outText.textContent = isFinite(out) && out !== 0
                ? 'Outstanding: ₹' + out.toFixed(2)
                : '';
        }

        radios.forEach(r => r.addEventListener('change', refreshPanels));
        sm.addEventListener('change', () => { refreshOutstanding(); refreshPanels(); });
        applyToggle.addEventListener('change', () => {
            applyWrap.style.display = applyToggle.checked ? '' : 'none';
        });
        refreshPanels();
        refreshOutstanding();

        const prod = document.getElementById('goods_product_id');
        const qty  = document.getElementById('goods_quantity');
        const totalEl = document.getElementById('goods_total_preview');
        const stockText = document.getElementById('stock_text');
        const costText  = document.getElementById('cost_text');
        function refreshGoods() {
            if (!prod) return;
            const opt = prod.options[prod.selectedIndex];
            const stock = parseFloat(opt?.dataset.stock || '0');
            const cost  = parseFloat(opt?.dataset.cost  || '0');
            const unit  = opt?.dataset.unit || '';
            const q = parseFloat(qty.value || '0');
            stockText.textContent = stock > 0 ? 'Available: ' + stock + ' ' + unit : '';
            costText.textContent  = cost  > 0 ? '@ ₹' + cost.toFixed(2) + ' / ' + unit : '';
            totalEl.value = '₹' + (q * cost).toFixed(2);
        }
        prod?.addEventListener('change', refreshGoods);
        qty?.addEventListener('input',  refreshGoods);
        refreshGoods();
    })();
</script>
