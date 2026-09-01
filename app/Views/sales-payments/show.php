<?php /** @var array $invoice */ /** @var array $payments */ ?>
<?php $isAdmin = \App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN); ?>
<?php $dueRemaining = (float) $invoice['due_amount']; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-cash-coin"></i> Payments —
        <a href="<?= url('/sales-invoices/' . $invoice['id']) ?>" class="text-decoration-none">
            <?= e($invoice['invoice_number']) ?>
        </a>
    </h1>
    <a href="<?= url('/sales-payments') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-md-4"><strong>Customer:</strong> <?= e($invoice['customer_name']) ?></div>
                    <div class="col-md-4"><strong>Date:</strong> <?= e($invoice['invoice_date']) ?></div>
                    <div class="col-md-4"><strong>Type:</strong>
                        <?php if ($invoice['bill_type'] === 'tax_invoice'): ?>
                            <span class="badge text-bg-primary">Tax Invoice</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Bill of Supply</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4"><strong>Grand Total:</strong> ₹<?= number_format((float) $invoice['grand_total'], 2) ?></div>
                    <div class="col-md-4"><strong>Paid:</strong> ₹<?= number_format((float) $invoice['paid_amount'], 2) ?></div>
                    <div class="col-md-4 <?= $dueRemaining > 0 ? 'text-danger fw-bold' : '' ?>">
                        <strong>Due:</strong> ₹<?= number_format($dueRemaining, 2) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <?php
                    $payBadge = [
                        'unpaid'  => ['danger',  'Unpaid'],
                        'partial' => ['warning', 'Partial'],
                        'paid'    => ['success', 'Paid'],
                    ][$invoice['payment_status']] ?? ['secondary', $invoice['payment_status']];
                ?>
                <span class="badge text-bg-<?= $payBadge[0] ?> fs-6 px-3 py-2"><?= e($payBadge[1]) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Existing payments -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Payments received</strong></div>
    <div class="card-body">
        <?php if ($payments === []): ?>
            <p class="text-muted text-center py-2 mb-0">No payments yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Cash</th>
                            <th class="text-end">Online</th>
                            <th>Online Ref</th>
                            <th class="text-end">Cheque</th>
                            <th>Cheque #</th>
                            <th>Customer's Bank</th>
                            <th>Cheque Status</th>
                            <th class="text-end">Total</th>
                            <th>Remarks</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <?php
                                $chqBadgeMap = [
                                    'pending'   => 'warning',
                                    'cleared'   => 'success',
                                    'bounced'   => 'danger',
                                    'cancelled' => 'secondary',
                                ];
                                $chqColor = $chqBadgeMap[$p['cheque_status']] ?? 'secondary';
                                $hasCheque = (float) $p['cheque_amount'] > 0;
                            ?>
                            <tr>
                                <td class="text-nowrap"><?= e($p['payment_date']) ?></td>
                                <td class="text-end"><?= (float) $p['cash_amount']   > 0 ? '₹' . number_format((float) $p['cash_amount'], 2)   : '<span class="text-muted">—</span>' ?></td>
                                <td class="text-end"><?= (float) $p['online_amount'] > 0 ? '₹' . number_format((float) $p['online_amount'], 2) : '<span class="text-muted">—</span>' ?></td>
                                <td class="text-muted small"><?= e($p['online_reference'] ?? '') ?></td>
                                <td class="text-end"><?= $hasCheque ? '₹' . number_format((float) $p['cheque_amount'], 2) : '<span class="text-muted">—</span>' ?></td>
                                <td class="font-monospace small"><?= e($p['cheque_number'] ?? '') ?></td>
                                <td class="text-muted small"><?= e($p['cheque_bank_name'] ?? '') ?></td>
                                <td>
                                    <?php if ($hasCheque): ?>
                                        <span class="badge text-bg-<?= $chqColor ?>"><?= e($p['cheque_status']) ?></span>
                                        <?php if ($p['cheque_status'] === 'pending'): ?>
                                            <form method="post" action="<?= url('/sales-payments/' . $p['id'] . '/cheque-status') ?>" class="d-inline">
                                                <?= \App\Support\Csrf::field() ?>
                                                <input type="hidden" name="cheque_status" value="cleared">
                                                <button class="btn btn-sm btn-link p-0 ms-1" title="Mark cleared">✓</button>
                                            </form>
                                            <form method="post" action="<?= url('/sales-payments/' . $p['id'] . '/cheque-status') ?>" class="d-inline">
                                                <?= \App\Support\Csrf::field() ?>
                                                <input type="hidden" name="cheque_status" value="bounced">
                                                <button class="btn btn-sm btn-link text-danger p-0 ms-1" title="Mark bounced"
                                                        onclick="return confirm('Mark cheque as bounced? Invoice due will reopen by the cheque amount.')">✗</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold">₹<?= number_format((float) $p['total_amount'], 2) ?></td>
                                <td class="text-muted small"><?= e($p['remarks'] ?? '') ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= url('/sales-payments/' . $p['id'] . '/receipt') ?>" target="_blank"
                                       class="btn btn-sm btn-outline-primary" title="Print receipt">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <?php if ($isAdmin): ?>
                                        <form method="post" action="<?= url('/sales-payments/' . $p['id'] . '/delete') ?>"
                                              class="d-inline"
                                              onsubmit="return confirm('Delete this payment? Invoice due will be recalculated.')">
                                            <?= \App\Support\Csrf::field() ?>
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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

<!-- Linked-salesman commission hint (only when this customer is linked to a salesman) -->
<?php if (!empty($linkedSalesman) && $dueRemaining > 0.0): ?>
    <?php $applicable = min((float) $linkedCommission, $dueRemaining); ?>
    <div class="alert alert-info py-2 small d-flex align-items-center justify-content-between">
        <div>
            <i class="bi bi-link-45deg"></i>
            This customer is linked to salesman <strong><?= e($linkedSalesman['name']) ?></strong>.
            <?php if ($linkedCommission > 0.0001): ?>
                Their outstanding commission is
                <strong>₹<?= number_format((float) $linkedCommission, 2) ?></strong>
                — up to <strong>₹<?= number_format($applicable, 2) ?></strong> can be applied to this bill.
            <?php else: ?>
                No commission outstanding right now.
            <?php endif; ?>
        </div>
        <?php if ($linkedCommission > 0.0001): ?>
            <a href="<?= url('/commission-settlements/create?salesman_id=' . $linkedSalesman['id']) ?>"
               class="btn btn-sm btn-outline-primary text-nowrap">
                <i class="bi bi-cash-coin"></i> Settle &amp; apply
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Add payment -->
<?php if ($dueRemaining > 0.0): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <strong>Receive a new payment</strong>
            <span class="ms-2 text-muted small">Remaining due: ₹<?= number_format($dueRemaining, 2) ?></span>
        </div>
        <div class="card-body">
            <form method="post" action="<?= url('/sales-payments/' . $invoice['id']) ?>" novalidate>
                <?= \App\Support\Csrf::field() ?>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="payment_date">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" id="payment_date" name="payment_date" class="form-control"
                               value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="received_into">Received Into <span class="text-danger">*</span></label>
                        <select id="received_into" name="received_into" class="form-select" required>
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
                    <div class="col-md-4 text-end">
                        <div class="small text-muted">Total</div>
                        <div class="h4 mb-0" id="total_display">₹0.00</div>
                    </div>
                </div>

                <hr>

                <div class="row g-3">
                    <div class="col-lg-3">
                        <div class="card border-success">
                            <div class="card-body">
                                <h6 class="card-title"><i class="bi bi-cash"></i> Cash</h6>
                                <label class="form-label" for="cash_amount">Amount (₹)</label>
                                <input type="number" id="cash_amount" name="cash_amount" class="form-control amount-input"
                                       min="0" step="0.01" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card border-primary">
                            <div class="card-body">
                                <h6 class="card-title"><i class="bi bi-bank"></i> Online / NEFT / UPI</h6>
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label" for="online_amount">Amount (₹)</label>
                                        <input type="number" id="online_amount" name="online_amount" class="form-control amount-input"
                                               min="0" step="0.01" value="0">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="online_reference">UTR / Ref #</label>
                                        <input type="text" id="online_reference" name="online_reference" maxlength="100" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card border-warning">
                            <div class="card-body">
                                <h6 class="card-title"><i class="bi bi-card-text"></i> Cheque (received from customer)</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label" for="cheque_amount">Amount (₹)</label>
                                        <input type="number" id="cheque_amount" name="cheque_amount" class="form-control amount-input"
                                               min="0" step="0.01" value="0">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="cheque_number">Cheque #</label>
                                        <input type="text" id="cheque_number" name="cheque_number" maxlength="50" class="form-control">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="cheque_date">Cheque Date</label>
                                        <input type="date" id="cheque_date" name="cheque_date" class="form-control">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="cheque_bank_name">Customer's Bank</label>
                                        <input type="text" id="cheque_bank_name" name="cheque_bank_name" maxlength="150" class="form-control">
                                    </div>
                                </div>
                                <div class="form-text mt-2">Cheque starts as <strong>pending</strong>. Flip to cleared or bounced from the table above once you know.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($availableAdvances)): ?>
                    <div class="card border-info mt-3">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="bi bi-piggy-bank"></i> Apply from customer advance
                                <span class="text-muted small ms-2">(₹<?= number_format($availableAdvanceTotal, 2) ?> available)</span>
                            </h6>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-7">
                                    <label class="form-label small" for="from_advance_id">Pick advance</label>
                                    <select id="from_advance_id" name="from_advance_id" class="form-select form-select-sm">
                                        <option value="" data-balance="0">— None —</option>
                                        <?php foreach ($availableAdvances as $adv): ?>
                                            <option value="<?= (int) $adv['id'] ?>" data-balance="<?= e($adv['balance']) ?>">
                                                <?= e($adv['advance_number']) ?> · <?= e($adv['advance_date']) ?>
                                                · balance ₹<?= number_format((float) $adv['balance'], 2) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small" for="from_advance_amount">Apply amount (₹)</label>
                                    <input type="number" id="from_advance_amount" name="from_advance_amount"
                                           min="0" step="0.01" value="0" class="form-control form-control-sm">
                                    <div class="form-text small" id="advance_balance_text"></div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <button type="button" id="advance_max_btn" class="btn btn-sm btn-outline-info">
                                        Max
                                    </button>
                                </div>
                            </div>
                            <div class="form-text small mt-2">
                                No cash moves when applying from advance — it's a paper credit transfer
                                that reduces both the customer's advance balance AND this invoice's due.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mt-3">
                    <label class="form-label" for="remarks">Remarks</label>
                    <input type="text" id="remarks" name="remarks" maxlength="500" class="form-control">
                </div>

                <div class="mt-3 d-flex gap-2 align-items-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Receive Payment
                    </button>
                    <span class="text-muted small">
                        Over-payment is blocked — total will be validated against the remaining due.
                    </span>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> This invoice is fully paid.
    </div>
<?php endif; ?>

<script>
    (function () {
        const inputs = document.querySelectorAll('.amount-input');
        const totalDisplay = document.getElementById('total_display');
        const advAmt = document.getElementById('from_advance_amount');
        const advSel = document.getElementById('from_advance_id');
        const advBalText = document.getElementById('advance_balance_text');
        const advMaxBtn  = document.getElementById('advance_max_btn');

        function getAdvanceBalance() {
            const opt = advSel?.options[advSel?.selectedIndex];
            return parseFloat(opt?.dataset.balance || '0') || 0;
        }
        function refreshTotal() {
            let total = 0;
            inputs.forEach(i => total += parseFloat(i.value || '0') || 0);
            total += parseFloat(advAmt?.value || '0') || 0;
            totalDisplay.textContent = '₹' + total.toFixed(2);
        }
        function refreshAdvanceText() {
            if (!advBalText) return;
            const bal = getAdvanceBalance();
            advBalText.textContent = bal > 0 ? 'Available: ₹' + bal.toFixed(2) : '';
        }

        inputs.forEach(i => i.addEventListener('input', refreshTotal));
        advAmt?.addEventListener('input', refreshTotal);
        advSel?.addEventListener('change', () => {
            // Reset apply amount when picker changes; user re-enters or hits Max.
            if (advAmt) advAmt.value = 0;
            refreshAdvanceText();
            refreshTotal();
        });
        advMaxBtn?.addEventListener('click', () => {
            if (!advAmt) return;
            advAmt.value = getAdvanceBalance().toFixed(2);
            refreshTotal();
        });

        refreshAdvanceText();
        refreshTotal();
    })();
</script>
