<?php
/** @var array $return */
/** @var array $lines */
/** @var array $lineComponents */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-arrow-counterclockwise"></i>
        <?php if ($return['return_number']): ?>
            <?= e($return['return_number']) ?>
        <?php else: ?>
            Sales Return Draft
        <?php endif; ?>
        <?php
            $sb = ['draft'=>'secondary','finalized'=>'success','cancelled'=>'warning'];
            $sl = ['draft'=>'Draft','finalized'=>'Finalized','cancelled'=>'Cancelled'];
        ?>
        <span class="badge text-bg-<?= $sb[$return['status']] ?? 'secondary' ?> ms-2">
            <?= e($sl[$return['status']] ?? $return['status']) ?>
        </span>
    </h1>
    <div class="d-flex gap-2">
        <a href="<?= url('/sales-returns') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <a href="<?= url('/sales-invoices/' . $return['original_sales_invoice_id']) ?>" class="btn btn-outline-info">
            <i class="bi bi-receipt"></i> View Original Invoice
        </a>
        <?php if ($return['status'] === 'finalized'): ?>
            <a href="<?= url('/sales-returns/' . $return['id'] . '/print/a4') ?>" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-printer"></i> Print Credit Note
            </a>
        <?php endif; ?>
        <?php if ($return['status'] === 'draft'): ?>
            <a href="<?= url('/sales-returns/' . $return['id'] . '/edit') ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Header</strong></div>
            <div class="card-body">
                <div class="row g-2 small">
                    <div class="col-md-4"><strong>Return Date:</strong> <?= e($return['return_date']) ?></div>
                    <div class="col-md-4"><strong>Customer:</strong> <?= e($return['customer_name']) ?></div>
                    <div class="col-md-4"><strong>Original Invoice:</strong> <?= e($return['original_invoice_number']) ?></div>
                    <div class="col-md-4"><strong>Refund:</strong> <?= e(str_replace('_', ' ', $return['refund_method'])) ?></div>
                    <div class="col-md-4"><strong>Bill Format:</strong> <?= e(str_replace('_', ' ', $return['bill_format'])) ?></div>
                    <div class="col-md-4"><strong>Tax Treatment:</strong> <?= e($return['tax_treatment']) ?></div>
                    <?php if ($return['reason']): ?>
                        <div class="col-12"><strong>Reason:</strong> <?= e($return['reason']) ?></div>
                    <?php endif; ?>
                    <?php if ($return['remarks']): ?>
                        <div class="col-12"><strong>Remarks:</strong> <?= e($return['remarks']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Totals</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Subtotal <small class="text-muted">(returned lines)</small></th>
                        <td class="text-end">₹<?= number_format((float) $return['subtotal'], 2) ?></td></tr>

                    <?php if ((float) $return['final_discount_share'] > 0): ?>
                        <tr class="text-success"><th>− Final Discount share</th>
                            <td class="text-end">− ₹<?= number_format((float) $return['final_discount_share'], 2) ?></td></tr>
                    <?php endif; ?>

                    <?php if ((float) $return['total_cgst'] > 0 || (float) $return['total_sgst'] > 0): ?>
                        <tr><th>CGST</th> <td class="text-end">₹<?= number_format((float) $return['total_cgst'], 2) ?></td></tr>
                        <tr><th>SGST</th> <td class="text-end">₹<?= number_format((float) $return['total_sgst'], 2) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $return['total_igst'] > 0): ?>
                        <tr><th>IGST</th> <td class="text-end">₹<?= number_format((float) $return['total_igst'], 2) ?></td></tr>
                    <?php endif; ?>

                    <?php if ((float) $return['extras_share'] > 0): ?>
                        <tr><th>+ Extras share <small class="text-muted">(prorated)</small></th>
                            <td class="text-end">₹<?= number_format((float) $return['extras_share'], 2) ?></td></tr>
                        <?php if ((float) $return['extras_tax_share'] > 0): ?>
                            <tr><th>+ Extras Tax share</th>
                                <td class="text-end">₹<?= number_format((float) $return['extras_tax_share'], 2) ?></td></tr>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ((float) $return['round_off_amount'] !== 0.0): ?>
                        <tr><th>Round Off</th>
                            <td class="text-end">₹<?= number_format((float) $return['round_off_amount'], 2) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) ($return['return_charge_amount'] ?? 0) > 0): ?>
                        <tr class="text-danger"><th>− Restocking fee / fine</th>
                            <td class="text-end">− ₹<?= number_format((float) $return['return_charge_amount'], 2) ?></td></tr>
                        <?php if ((float) ($return['return_charge_tax_amount'] ?? 0) > 0): ?>
                            <tr class="text-danger"><th>− Charge GST</th>
                                <td class="text-end">− ₹<?= number_format((float) $return['return_charge_tax_amount'], 2) ?></td></tr>
                        <?php endif; ?>
                    <?php endif; ?>
                    <tr class="table-light"><th>Grand Total <small class="text-muted">(due reduced by)</small></th>
                        <td class="text-end fw-bold fs-5">₹<?= number_format((float) $return['grand_total'], 2) ?></td></tr>
                </table>

                <?php if ($return['status'] === 'finalized'): ?>
                    <hr class="my-2">
                    <table class="table table-sm small mb-0">
                        <tr><th class="text-muted">COGS reversed (back into stock)</th>
                            <td class="text-end">₹<?= number_format((float) $return['total_cogs'], 2) ?></td></tr>
                        <tr><th class="text-muted">Commission reversed</th>
                            <td class="text-end">₹<?= number_format((float) $return['total_commission_reversed'], 2) ?></td></tr>
                        <?php if ($return['refund_method'] === 'cash' && (float) ($return['cash_refund_amount'] ?? 0) > 0): ?>
                            <?php $rk = $return['refund_account_kind'] === 'bank_account' ? 'bank account' : 'cashier drawer'; ?>
                            <tr class="text-danger">
                                <th>Cash refunded <small class="text-muted">(from <?= e($rk) ?>)</small></th>
                                <td class="text-end fw-bold">₹<?= number_format((float) $return['cash_refund_amount'], 2) ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Returned Lines</strong></div>
    <div class="card-body">
        <?php $hideLineTax = $return['bill_format'] === 'discount_then_tax'; ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th class="text-end">Qty Returned</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">AddSub %</th>
                        <th class="text-end">Subtotal</th>
                        <?php if (!$hideLineTax): ?>
                            <th class="text-end">Tax</th>
                        <?php endif; ?>
                        <th class="text-end">Line Total</th>
                        <th class="text-end">COGS Reversed</th>
                        <th class="text-end">Commission Reversed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $rl): ?>
                        <tr>
                            <td><?= (int) $rl['line_no'] ?></td>
                            <td>
                                <strong><?= e($rl['display_name']) ?></strong>
                                <?php if ((int) $rl['is_composite_line'] === 1): ?>
                                    <span class="badge text-bg-info ms-1"><i class="bi bi-diagram-3"></i> composite</span>
                                    <?php if (!empty($lineComponents[(int) $rl['id']])): ?>
                                        <ul class="small text-muted mb-0 ps-3 mt-1" style="list-style:'◦ ';">
                                            <?php foreach ($lineComponents[(int) $rl['id']] as $comp): ?>
                                                <li>
                                                    <?= e($comp['component_name']) ?>
                                                    — <?= number_format((float) $comp['quantity_returned'], 3) ?>
                                                    <?= e($comp['component_unit']) ?>
                                                    <?php if ((float) $comp['cogs_total'] > 0): ?>
                                                        <span class="text-muted">(cogs ₹<?= number_format((float) $comp['cogs_total'], 2) ?>)</span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= number_format((float) $rl['quantity_returned'], 3) ?></td>
                            <td class="text-end"><?= number_format((float) $rl['unit_price'], 2) ?></td>
                            <td class="text-end"><?= number_format((float) $rl['addsub_percent'], 2) ?>%</td>
                            <td class="text-end"><?= number_format((float) $rl['subtotal'], 2) ?></td>
                            <?php if (!$hideLineTax): ?>
                                <td class="text-end"><?= number_format((float) $rl['tax_amount'], 2) ?></td>
                            <?php endif; ?>
                            <td class="text-end fw-bold"><?= number_format((float) $rl['line_total'], 2) ?></td>
                            <td class="text-end text-muted">₹<?= number_format((float) $rl['cogs_total'], 2) ?></td>
                            <td class="text-end text-muted">₹<?= number_format((float) $rl['commission_reversed'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
