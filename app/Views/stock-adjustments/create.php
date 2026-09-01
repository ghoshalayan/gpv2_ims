<?php
/** @var array $products */     // [{id, name, unit}, ...]
/** @var array $stockSnapshot */ // [productId => ['qty' => int, 'avg' => float]]
?>
<?php $old = static fn(string $k, $d = '') => e(old($k, $d)); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-clipboard-x"></i> Post Stock Adjustment</h1>
    <a href="<?= url('/stock-adjustments') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/stock-adjustments') ?>" novalidate id="adjForm">
    <?= \App\Support\Csrf::field() ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Header</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="adjustment_date">Date <span class="text-danger">*</span></label>
                    <input type="date" id="adjustment_date" name="adjustment_date" required class="form-control"
                           value="<?= $old('adjustment_date', date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="reason">Reason <span class="text-danger">*</span></label>
                    <select id="reason" name="reason" class="form-select" required onchange="onReasonChange()">
                        <option value="">— Pick reason —</option>
                        <?php foreach ([
                            'damage'           => 'Damage',
                            'expiry'           => 'Expiry',
                            'theft'            => 'Theft / Shrinkage',
                            'count_correction' => 'Count Correction',
                            'other'            => 'Other',
                        ] as $key => $label): ?>
                            <option value="<?= $key ?>" <?= old('reason') === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="direction">Direction <span class="text-danger">*</span></label>
                    <select id="direction" name="direction" class="form-select" required onchange="onDirectionChange()">
                        <option value="out" <?= old('direction', 'out') === 'out' ? 'selected' : '' ?>>Out — stock leaves</option>
                        <option value="in"  <?= old('direction') === 'in'  ? 'selected' : '' ?>>In — stock found</option>
                    </select>
                    <div class="form-text small">
                        Damage / Expiry / Theft must be Out. Count correction can be either.
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="remarks">Remarks</label>
                    <input type="text" id="remarks" name="remarks" maxlength="500" class="form-control"
                           value="<?= $old('remarks') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Lines</strong>
            <div class="d-flex gap-2 align-items-center">
                <span class="text-muted small">Total Value: <strong id="totalValue">₹0.00</strong></span>
                <button type="button" id="addLineBtn" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus"></i> Add line
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm align-middle mb-0" id="linesTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:35px">#</th>
                        <th>Product</th>
                        <th class="text-end" style="width:120px">On hand</th>
                        <th class="text-end" style="width:130px">Quantity <span class="text-danger">*</span></th>
                        <th class="text-end" style="width:160px">Cost / unit (₹) <span class="text-danger">*</span></th>
                        <th class="text-end" style="width:130px">Line Value</th>
                        <th>Notes</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="linesBody"></tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Post Adjustment</button>
        <a href="<?= url('/stock-adjustments') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    const STOCK = <?= json_encode($stockSnapshot, JSON_UNESCAPED_SLASHES) ?>;
    const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_SLASHES) ?>;

    let lineIdx = 0;

    function buildProductOptions() {
        let html = '<option value="">— Pick product —</option>';
        PRODUCTS.forEach(p => {
            const snap = STOCK[p.id] || {qty: 0, avg: 0};
            html += `<option value="${p.id}" data-qty="${snap.qty}" data-avg="${snap.avg.toFixed(4)}" data-unit="${p.unit}">${escapeHtml(p.name)}</option>`;
        });
        return html;
    }

    function addLine() {
        lineIdx++;
        const i = lineIdx;
        const tr = document.createElement('tr');
        tr.dataset.idx = i;
        tr.innerHTML = `
            <td class="text-muted small line-num">${document.querySelectorAll('#linesBody tr').length + 1}</td>
            <td>
                <select name="lines[${i}][product_id]" class="form-select form-select-sm prod-pick" onchange="onProductPick(${i})">
                    ${buildProductOptions()}
                </select>
            </td>
            <td class="text-end small text-muted onhand-cell">—</td>
            <td><input type="number" name="lines[${i}][quantity]" min="0" step="1" value="" class="form-control form-control-sm text-end qty-in" oninput="recalcLine(${i})"></td>
            <td><input type="number" name="lines[${i}][cost]" min="0" step="0.0001" value="" class="form-control form-control-sm text-end cost-in" oninput="recalcLine(${i})"></td>
            <td class="text-end small fw-bold line-val">₹0.00</td>
            <td><input type="text" name="lines[${i}][notes]" maxlength="255" class="form-control form-control-sm"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(${i})"><i class="bi bi-x"></i></button></td>
        `;
        document.getElementById('linesBody').appendChild(tr);
    }

    function removeLine(i) {
        const tr = document.querySelector(`#linesBody tr[data-idx="${i}"]`);
        if (tr) tr.remove();
        renumber();
        recalcTotal();
    }

    function renumber() {
        document.querySelectorAll('#linesBody tr').forEach((tr, idx) => {
            const cell = tr.querySelector('.line-num');
            if (cell) cell.textContent = idx + 1;
        });
    }

    function onProductPick(i) {
        const tr = document.querySelector(`#linesBody tr[data-idx="${i}"]`);
        if (!tr) return;
        const sel = tr.querySelector('.prod-pick');
        const opt = sel.options[sel.selectedIndex];
        const qty = parseInt(opt?.dataset.qty || '0', 10) || 0;
        const avg = parseFloat(opt?.dataset.avg || '0') || 0;
        const unit = opt?.dataset.unit || '';
        tr.querySelector('.onhand-cell').textContent = `${qty} ${unit}`;
        // Auto-fill cost: 'out' → moving-avg; 'in' → leave blank for admin entry
        const dir = document.getElementById('direction').value;
        const costEl = tr.querySelector('.cost-in');
        if (dir === 'out') {
            costEl.value = avg.toFixed(4);
            costEl.readOnly = true;
        } else {
            costEl.value = '';
            costEl.readOnly = false;
        }
        recalcLine(i);
    }

    function recalcLine(i) {
        const tr = document.querySelector(`#linesBody tr[data-idx="${i}"]`);
        if (!tr) return;
        const q = parseFloat(tr.querySelector('.qty-in')?.value || '0') || 0;
        const c = parseFloat(tr.querySelector('.cost-in')?.value || '0') || 0;
        tr.querySelector('.line-val').textContent = '₹' + (q * c).toFixed(2);
        recalcTotal();
    }

    function recalcTotal() {
        let t = 0;
        document.querySelectorAll('#linesBody tr').forEach(tr => {
            const q = parseFloat(tr.querySelector('.qty-in')?.value || '0') || 0;
            const c = parseFloat(tr.querySelector('.cost-in')?.value || '0') || 0;
            t += q * c;
        });
        document.getElementById('totalValue').textContent = '₹' + t.toFixed(2);
    }

    function onReasonChange() {
        const reason = document.getElementById('reason').value;
        const dirSel = document.getElementById('direction');
        // Force out for damage/expiry/theft
        if (['damage', 'expiry', 'theft'].includes(reason)) {
            dirSel.value = 'out';
            dirSel.disabled = true;
        } else {
            dirSel.disabled = false;
        }
        onDirectionChange();
    }

    function onDirectionChange() {
        // Refresh per-line cost editability based on direction.
        document.querySelectorAll('#linesBody tr').forEach(tr => {
            const i = parseInt(tr.dataset.idx, 10);
            onProductPick(i);
        });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    document.getElementById('addLineBtn').addEventListener('click', addLine);

    // Start with one blank line
    addLine();
    onReasonChange();
</script>
