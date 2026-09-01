<?php
/** @var array $return */
/** @var array $returnLines */
/** @var array $eligibleLines */
/** @var array $banks */
/** @var array $drawers */
$curAccount = ($return['refund_account_kind'] ?? null) && ($return['refund_account_id'] ?? null)
    ? $return['refund_account_kind'] . ':' . (int) $return['refund_account_id']
    : '';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-arrow-counterclockwise"></i> Edit Purchase Return
        <span class="badge text-bg-secondary ms-2">Draft</span>
    </h1>
    <div class="d-flex gap-2">
        <a href="<?= url('/purchase-returns') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <form method="post" action="<?= url('/purchase-returns/' . $return['id'] . '/cancel') ?>"
              onsubmit="return confirm('Cancel this draft return? It will be kept with status=cancelled for audit history.')">
            <?= \App\Support\Csrf::field() ?>
            <button class="btn btn-outline-warning"><i class="bi bi-x-circle"></i> Cancel Draft</button>
        </form>
        <?php if (\App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN)): ?>
            <form method="post" action="<?= url('/purchase-returns/' . $return['id'] . '/delete') ?>"
                  onsubmit="return confirm('PERMANENTLY DELETE this draft? Lines will be removed. This cannot be undone.')">
                <?= \App\Support\Csrf::field() ?>
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete Draft</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ============== HEADER ============== -->
<form method="post" action="<?= url('/purchase-returns/' . $return['id'] . '/update-header') ?>" class="mb-3">
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Header</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Original Invoice</label>
                    <input type="text" class="form-control" readonly
                           value="<?= e($return['original_invoice_number']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Supplier</label>
                    <input type="text" class="form-control" readonly
                           value="<?= e($return['supplier_name']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Return Date</label>
                    <input type="date" name="return_date" class="form-control"
                           value="<?= e($return['return_date']) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Refund</label>
                    <select name="refund_method" id="refund_method" class="form-select no-select2">
                        <option value="credit_note" <?= $return['refund_method'] === 'credit_note' ? 'selected' : '' ?>>
                            Credit Note
                        </option>
                        <option value="cash" <?= $return['refund_method'] === 'cash' ? 'selected' : '' ?>>
                            Cash refund
                        </option>
                    </select>
                </div>
                <div class="col-md-3 d-none" id="refund_account_wrap">
                    <label class="form-label" for="refund_account">Refund Into <span class="text-danger">*</span></label>
                    <select name="refund_account" id="refund_account" class="form-select no-select2">
                        <option value="">— Select bank / drawer —</option>
                        <?php if ($banks !== []): ?>
                            <optgroup label="Bank Accounts">
                                <?php foreach ($banks as $b): ?>
                                    <option value="bank_account:<?= (int) $b['id'] ?>"
                                        <?= $curAccount === 'bank_account:' . (int) $b['id'] ? 'selected' : '' ?>>
                                        <?= e($b['name']) ?><?= $b['bank_name'] ? ' · ' . e($b['bank_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if ($drawers !== []): ?>
                            <optgroup label="Cashier Drawers">
                                <?php foreach ($drawers as $d): ?>
                                    <option value="cashier_drawer:<?= (int) $d['id'] ?>"
                                        <?= $curAccount === 'cashier_drawer:' . (int) $d['id'] ? 'selected' : '' ?>>
                                        <?= e($d['name']) ?><?= $d['location'] ? ' · ' . e($d['location']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tax Mode</label>
                    <input type="text" class="form-control text-muted" readonly
                           value="<?= e(str_replace('_', ' ', $return['bill_format'])) ?> · <?= e($return['tax_treatment']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Reason</label>
                    <input type="text" name="reason" maxlength="500" class="form-control"
                           value="<?= e($return['reason'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Remarks</label>
                    <input type="text" name="remarks" maxlength="500" class="form-control"
                           value="<?= e($return['remarks'] ?? '') ?>">
                </div>
                <div class="col-12"><hr class="my-1"><span class="small text-muted fw-semibold">Refund composition</span></div>
                <?php
                    $isGstBill = ($return['tax_treatment'] ?? '') !== 'no_tax';
                    $chgMode    = $return['return_charge_mode'] ?? 'none';
                    $chgTaxable = (int) ($return['return_charge_taxable'] ?? 0) === 1
                                  || ($chgMode === 'none' && $isGstBill);
                    $chgRate    = (float) ($return['return_charge_tax_rate'] ?? 0) > 0
                                  ? (float) $return['return_charge_tax_rate']
                                  : ($isGstBill ? (float) ($return['invoice_level_tax_rate'] ?? 0) : 0.0);
                ?>
                <div class="col-md-3">
                    <label class="form-label d-block">Original extras</label>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="include_original_extras"
                               name="include_original_extras" value="1"
                               <?= (int) ($return['include_original_extras'] ?? 0) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="include_original_extras">
                            Refund loading/unloading/freight
                        </label>
                    </div>
                    <div class="form-text">Off = not refunded (default).</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="return_charge_mode">Supplier charge / fine</label>
                    <select name="return_charge_mode" id="return_charge_mode" class="form-select no-select2">
                        <option value="none"    <?= $chgMode === 'none'    ? 'selected' : '' ?>>None</option>
                        <option value="flat"    <?= $chgMode === 'flat'    ? 'selected' : '' ?>>Flat &#8377;</option>
                        <option value="percent" <?= $chgMode === 'percent' ? 'selected' : '' ?>>% of goods value</option>
                    </select>
                </div>
                <div class="col-md-2 charge-field d-none">
                    <label class="form-label" for="return_charge_value">Amount / %</label>
                    <input type="number" min="0" step="0.01" name="return_charge_value" id="return_charge_value"
                           class="form-control" value="<?= e((string) ($return['return_charge_value'] ?? '')) ?>">
                </div>
                <div class="col-md-4 charge-field d-none">
                    <label class="form-label d-block">Charge GST</label>
                    <div class="input-group">
                        <div class="input-group-text">
                            <input type="checkbox" class="form-check-input mt-0" id="return_charge_taxable"
                                   name="return_charge_taxable" value="1" <?= $chgTaxable ? 'checked' : '' ?>>
                        </div>
                        <input type="number" min="0" step="0.01" name="return_charge_tax_rate" id="return_charge_tax_rate"
                               class="form-control" placeholder="GST %"
                               value="<?= e($chgRate > 0 ? (string) $chgRate : '') ?>">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Reduces the refund. Default GST for GST bills; uncheck to skip.</div>
                </div>
                <div class="col-12">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Save Header</button>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- ============== PICKED RETURN LINES ============== -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Lines on this return</strong></div>
    <div class="card-body">
        <?php if ($returnLines === []): ?>
            <div class="text-muted small">No lines yet. Pick from the original invoice's eligible lines below.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-end">Qty Returned</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">AddSub %</th>
                            <th class="text-end">Tax %</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($returnLines as $rl): ?>
                            <tr>
                                <td><?= (int) $rl['line_no'] ?></td>
                                <td>
                                    <strong><?= e($rl['product_name']) ?></strong>
                                    <span class="text-muted small">(<?= e($rl['product_unit']) ?>)</span>
                                </td>
                                <td class="text-end"><?= number_format((float) $rl['quantity_returned'], 3) ?></td>
                                <td class="text-end"><?= number_format((float) $rl['unit_price'], 2) ?></td>
                                <td class="text-end"><?= number_format((float) $rl['addsub_percent'], 2) ?>%</td>
                                <td class="text-end"><?= number_format((float) $rl['tax_rate'], 2) ?></td>
                                <td class="text-end">
                                    <form method="post"
                                          action="<?= url('/purchase-returns/' . $return['id'] . '/lines/' . $rl['id'] . '/delete') ?>"
                                          class="d-inline"
                                          onsubmit="return confirm('Remove this line from the return?')">
                                        <?= \App\Support\Csrf::field() ?>
                                        <button class="btn btn-sm btn-outline-danger" title="Remove">
                                            <i class="bi bi-trash"></i>
                                        </button>
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

<!-- ============== ELIGIBLE LINES FROM ORIGINAL INVOICE ============== -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white">
        <strong>Eligible lines from <?= e($return['original_invoice_number']) ?></strong>
        <span class="text-muted small ms-2">— remaining qty = original − all prior returns (drafts + finalized)</span>
    </div>
    <div class="card-body">
        <?php $anyEligible = false; ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th class="text-end">Original Qty</th>
                        <th class="text-end">Returned earlier</th>
                        <th class="text-end">Remaining</th>
                        <th class="text-end">Unit Price</th>
                        <th>Add Qty to Return</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eligibleLines as $row): ?>
                        <?php $ol = $row['orig']; $remaining = (float) $row['remaining']; ?>
                        <tr class="<?= $remaining <= 0 ? 'text-muted' : '' ?>">
                            <td><?= (int) $ol['line_no'] ?></td>
                            <td>
                                <strong><?= e($ol['product_name']) ?></strong>
                                <span class="text-muted small">(<?= e($ol['product_unit']) ?>)</span>
                                <?php if ($row['on_draft'] !== null): ?>
                                    <span class="badge text-bg-success ms-1">on this draft: <?= number_format((float) $row['on_draft']['quantity_returned'], 3) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= number_format((float) $ol['quantity'], 3) ?></td>
                            <td class="text-end"><?= number_format((float) $row['already_returned'], 3) ?></td>
                            <td class="text-end fw-bold <?= $remaining > 0 ? 'text-success' : '' ?>">
                                <?= number_format($remaining, 3) ?>
                            </td>
                            <td class="text-end"><?= number_format((float) $ol['unit_price'], 2) ?></td>
                            <td>
                                <?php if ($remaining > 0): $anyEligible = true; ?>
                                    <form method="post" action="<?= url('/purchase-returns/' . $return['id'] . '/lines') ?>"
                                          class="d-flex gap-2 align-items-center">
                                        <?= \App\Support\Csrf::field() ?>
                                        <input type="hidden" name="original_purchase_line_id" value="<?= (int) $ol['id'] ?>">
                                        <input type="number" name="quantity_returned" min="0.001" step="0.001"
                                               max="<?= e((string) $remaining) ?>"
                                               class="form-control form-control-sm" style="width: 9rem;"
                                               placeholder="qty" required>
                                        <button class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-plus-lg"></i> Add
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">fully returned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$anyEligible && $returnLines === []): ?>
            <div class="alert alert-warning small mt-2 mb-0">
                Nothing eligible to return — every line on the original invoice is fully returned across prior returns.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============== FINALIZE ============== -->
<div class="card shadow-sm">
    <div class="card-body small d-flex justify-content-between align-items-center">
        <div>
            <strong>Note:</strong> on finalize, stock goes OUT at the current moving-avg cost (D10),
            and the supplier's payable on the original invoice is reduced by the return's grand total
            (clamped at zero). With <strong>Cash refund</strong>, any already-paid excess
            (return total − remaining payable) is credited to the chosen bank/drawer.
            Insufficient stock will block finalize.
        </div>
        <form method="post" action="<?= url('/purchase-returns/' . $return['id'] . '/finalize') ?>"
              onsubmit="return confirm('Finalize this return? Stock and supplier payable will be updated. This cannot be undone in v1.')">
            <?= \App\Support\Csrf::field() ?>
            <button class="btn btn-success" <?= $returnLines === [] ? 'disabled' : '' ?>>
                <i class="bi bi-lock-fill"></i> Finalize Return
            </button>
            <?php if ($returnLines === []): ?>
                <div class="form-text text-end">Add at least one line to enable.</div>
            <?php endif; ?>
        </form>
    </div>
</div>

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
        }
        method.addEventListener('change', sync);
        sync();
    })();

    (function () {
        var cm = document.getElementById('return_charge_mode');
        if (!cm) return;
        function syncCharge() {
            var on = cm.value !== 'none';
            document.querySelectorAll('.charge-field').forEach(function (el) { el.classList.toggle('d-none', !on); });
        }
        cm.addEventListener('change', syncCharge);
        syncCharge();
    })();
</script>
