<?php /** @var array $invoice */ /** @var array $lines */ /** @var array $expenses */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-receipt"></i> Invoice <?= e($invoice['invoice_number']) ?>
        <?php $sb = [
            'draft'=>'secondary', 'finalized'=>'success', 'cancelled'=>'warning',
        ]; ?>
        <span class="badge text-bg-<?= $sb[$invoice['status']] ?? 'secondary' ?> ms-2"><?= e($invoice['status']) ?></span>
    </h1>
    <div class="d-flex gap-2">
        <a href="<?= url('/purchase-invoices') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <?php if ($invoice['status'] === 'finalized'): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-printer"></i> Print
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= url('/purchase-invoices/' . $invoice['id'] . '/print/a4') ?>" target="_blank">A4</a></li>
                    <li><a class="dropdown-item" href="<?= url('/purchase-invoices/' . $invoice['id'] . '/print/thermal') ?>" target="_blank">Thermal (80mm)</a></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-md-4"><strong>Supplier:</strong> <?= e($invoice['supplier_name']) ?></div>
                    <div class="col-md-4"><strong>Brand:</strong> <?= e($invoice['brand_name']) ?></div>
                    <div class="col-md-4"><strong>Date:</strong> <?= e($invoice['invoice_date']) ?></div>
                    <div class="col-md-4"><strong>Bill Format:</strong> <?= e(str_replace('_', ' ', $invoice['bill_format'])) ?></div>
                    <div class="col-md-4"><strong>GST Scheme:</strong>
                        <?= (int) $invoice['is_intra_state'] === 1 ? 'Intra-state (CGST+SGST)' : 'Inter-state (IGST)' ?>
                    </div>
                    <div class="col-md-4"><strong>GST Claimable:</strong>
                        <?= (int) $invoice['gst_claimable'] === 1 ? 'Yes' : 'No' ?>
                    </div>
                    <?php if ($invoice['remarks']): ?>
                        <div class="col-12"><strong>Remarks:</strong> <?= e($invoice['remarks']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Totals</strong></div>
            <div class="card-body">
                <?php
                    $isModeA = $invoice['bill_format'] === 'discount_then_tax';
                    $fd      = (float) ($invoice['final_discount_amount'] ?? 0);
                ?>
                <table class="table table-sm mb-0">
                    <tr><th>Subtotal <small class="text-muted">(lines)</small></th>
                        <td class="text-end">₹<?= number_format((float) $invoice['subtotal'], 2) ?></td></tr>

                    <?php if ($isModeA && $fd > 0): ?>
                        <tr class="text-success"><th>− Final Discount</th>
                            <td class="text-end">− ₹<?= number_format($fd, 2) ?></td></tr>
                        <tr><th>= Taxable (lines)</th>
                            <td class="text-end">₹<?= number_format((float) $invoice['subtotal'] - $fd, 2) ?></td></tr>
                    <?php endif; ?>

                    <?php if ((float) $invoice['total_cgst'] > 0 || (float) $invoice['total_sgst'] > 0): ?>
                        <tr><th>CGST</th>    <td class="text-end">₹<?= number_format((float) $invoice['total_cgst'], 2) ?></td></tr>
                        <tr><th>SGST</th>    <td class="text-end">₹<?= number_format((float) $invoice['total_sgst'], 2) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $invoice['total_igst'] > 0): ?>
                        <tr><th>IGST</th>    <td class="text-end">₹<?= number_format((float) $invoice['total_igst'], 2) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $invoice['total_expenses'] > 0): ?>
                        <tr><th>Expenses</th>    <td class="text-end">₹<?= number_format((float) $invoice['total_expenses'], 2) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $invoice['total_expense_tax'] > 0): ?>
                        <tr><th>Expense Tax</th> <td class="text-end">₹<?= number_format((float) $invoice['total_expense_tax'], 2) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $invoice['round_off_amount'] !== 0.0): ?>
                        <tr><th>Round Off</th><td class="text-end">₹<?= number_format((float) $invoice['round_off_amount'], 2) ?></td></tr>
                    <?php endif; ?>
                    <tr class="table-light"><th>Grand Total</th>
                        <td class="text-end fw-bold fs-5">₹<?= number_format((float) $invoice['grand_total'], 2) ?></td></tr>
                    <tr><th>Paid</th>        <td class="text-end">₹<?= number_format((float) $invoice['paid_amount'], 2) ?></td></tr>
                    <tr class="<?= (float) $invoice['due_amount'] > 0 ? 'table-danger' : '' ?>">
                        <th>Due</th>
                        <td class="text-end fw-bold">₹<?= number_format((float) $invoice['due_amount'], 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Lines</strong></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <?php $hideLineTax = $invoice['bill_format'] === 'discount_then_tax'; ?>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">AddSub %</th>
                        <th class="text-end">Subtotal</th>
                        <?php if (!$hideLineTax): ?>
                            <th class="text-end">Tax</th>
                        <?php endif; ?>
                        <th class="text-end">Expense Share</th>
                        <th class="text-end">Landed Cost / Unit</th>
                        <th class="text-end">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $l): ?>
                        <tr>
                            <td><?= (int) $l['line_no'] ?></td>
                            <td><?= e($l['product_name']) ?> <span class="text-muted small">(<?= e($l['product_unit']) ?>)</span></td>
                            <td class="text-end"><?= number_format((float) $l['quantity'], 3) ?></td>
                            <td class="text-end"><?= number_format((float) $l['unit_price'], 2) ?></td>
                            <td class="text-end"><?= number_format((float) $l['addsub_percent'], 2) ?>%</td>
                            <td class="text-end"><?= number_format((float) $l['subtotal'], 2) ?></td>
                            <?php if (!$hideLineTax): ?>
                                <td class="text-end"><?= number_format((float) $l['tax_amount'], 2) ?></td>
                            <?php endif; ?>
                            <td class="text-end"><?= number_format((float) $l['allocated_expense_share'] + (float) $l['allocated_expense_tax_share'], 2) ?></td>
                            <td class="text-end fw-bold">₹<?= number_format((float) $l['landed_cost_per_unit'], 4) ?></td>
                            <td class="text-end fw-bold"><?= number_format((float) $l['line_total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($expenses !== []): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Expenses</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Tax</th>
                            <th class="text-end">Total</th>
                            <th>Allocation</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $ex): ?>
                            <tr>
                                <td><?= e($ex['expense_type']) ?></td>
                                <td class="text-end"><?= number_format((float) $ex['amount'], 2) ?></td>
                                <td class="text-end"><?= number_format((float) $ex['tax_amount'], 2) ?></td>
                                <td class="text-end fw-bold"><?= number_format((float) $ex['total_with_tax'], 2) ?></td>
                                <td><span class="badge text-bg-info"><?= e(str_replace('_', ' ', $ex['allocation_method'])) ?></span></td>
                                <td class="text-muted small"><?= e($ex['remarks'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($invoice['status'] === 'finalized'): ?>
    <div class="d-flex align-items-center gap-3">
        <div class="alert alert-success small mb-0 flex-grow-1">
            Finalized <?= e($invoice['finalized_at']) ?>. Stock and moving-average costs updated.
        </div>
        <a href="<?= url('/purchase-payments/' . $invoice['id']) ?>" class="btn btn-primary">
            <i class="bi bi-cash-coin"></i>
            <?= (float) $invoice['due_amount'] > 0 ? 'Record Payment' : 'View Payments' ?>
        </a>
    </div>
<?php endif; ?>
