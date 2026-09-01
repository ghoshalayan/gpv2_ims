<?php /** @var array $invoice */ /** @var array $lines */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-receipt"></i>
        <?= $invoice['invoice_number'] ? e($invoice['invoice_number']) : 'Sales Invoice draft' ?>
        <?php $sb = ['draft'=>'secondary','finalized'=>'success','cancelled'=>'warning']; ?>
        <span class="badge text-bg-<?= $sb[$invoice['status']] ?? 'secondary' ?> ms-2"><?= e($invoice['status']) ?></span>
        <?php if ($invoice['bill_type']): ?>
            <span class="badge text-bg-<?= $invoice['bill_type'] === 'tax_invoice' ? 'primary' : 'secondary' ?> ms-1">
                <?= e(str_replace('_', ' ', $invoice['bill_type'])) ?>
            </span>
        <?php endif; ?>
    </h1>
    <a href="<?= url('/sales-invoices') ?>" class="btn btn-outline-secondary">
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
                    <div class="col-md-4"><strong>Bill Format:</strong> <?= e(str_replace('_', ' ', $invoice['bill_format'])) ?></div>
                    <div class="col-md-4"><strong>GST Scheme:</strong>
                        <?= (int) $invoice['is_intra_state'] === 1 ? 'Intra-state (CGST+SGST)' : 'Inter-state (IGST)' ?>
                    </div>
                    <?php if ($invoice['header_salesman_name']): ?>
                        <div class="col-md-4"><strong>Header Salesman:</strong> <?= e($invoice['header_salesman_name']) ?></div>
                    <?php endif; ?>
                    <?php if ($invoice['customer_gstin']): ?>
                        <div class="col-md-4"><strong>Customer GSTIN:</strong> <code><?= e($invoice['customer_gstin']) ?></code></div>
                    <?php endif; ?>
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
                    $isModeA        = $invoice['bill_format'] === 'discount_then_tax';
                    $fd             = (float) ($invoice['final_discount_amount'] ?? 0);
                    $totalExtras    = (float) ($invoice['total_extras'] ?? 0);
                    $totalExtrasTax = (float) ($invoice['total_extras_tax'] ?? 0);
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
                        <tr><th>CGST</th> <td class="text-end">₹<?= number_format((float) $invoice['total_cgst'], 2) ?></td></tr>
                        <tr><th>SGST</th> <td class="text-end">₹<?= number_format((float) $invoice['total_sgst'], 2) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $invoice['total_igst'] > 0): ?>
                        <tr><th>IGST</th> <td class="text-end">₹<?= number_format((float) $invoice['total_igst'], 2) ?></td></tr>
                    <?php endif; ?>

                    <?php if ($totalExtras > 0): ?>
                        <tr><th>+ Extras</th>
                            <td class="text-end">₹<?= number_format($totalExtras, 2) ?></td></tr>
                        <?php if ($totalExtrasTax > 0): ?>
                            <tr><th>+ Extras Tax <small class="text-muted">(incl. above)</small></th>
                                <td class="text-end">₹<?= number_format($totalExtrasTax, 2) ?></td></tr>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ((float) $invoice['round_off_amount'] !== 0.0): ?>
                        <tr><th>Round Off</th><td class="text-end">₹<?= number_format((float) $invoice['round_off_amount'], 2) ?></td></tr>
                    <?php endif; ?>
                    <tr class="table-light"><th>Grand Total</th>
                        <td class="text-end fw-bold fs-5">₹<?= number_format((float) $invoice['grand_total'], 2) ?></td></tr>
                    <tr><th>Paid</th> <td class="text-end">₹<?= number_format((float) $invoice['paid_amount'], 2) ?></td></tr>
                    <tr class="<?= (float) $invoice['due_amount'] > 0 ? 'table-danger' : '' ?>">
                        <th>Due</th>
                        <td class="text-end fw-bold">₹<?= number_format((float) $invoice['due_amount'], 2) ?></td>
                    </tr>
                </table>

                <?php if (!empty($extras)): ?>
                    <hr class="my-2">
                    <div class="small text-muted mb-1">Extras detail</div>
                    <table class="table table-sm small mb-0">
                        <?php foreach ($extras as $ex): ?>
                            <tr>
                                <td>
                                    <?= e($ex['label']) ?>
                                    <?php if ((float) $ex['tax_rate'] > 0): ?>
                                        <span class="text-muted">@ <?= number_format((float) $ex['tax_rate'], 2) ?>%</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    ₹<?= number_format((float) $ex['amount'], 2) ?>
                                    <?php if ((float) $ex['tax_amount'] > 0): ?>
                                        <span class="text-muted small"> + ₹<?= number_format((float) $ex['tax_amount'], 2) ?> tax</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>

                <?php if ((float) $invoice['total_cogs'] > 0 || (float) $invoice['total_commission'] > 0): ?>
                    <hr class="my-2">
                    <table class="table table-sm small mb-0">
                        <tr><th class="text-muted">COGS (cost of goods sold)</th>
                            <td class="text-end">₹<?= number_format((float) $invoice['total_cogs'], 2) ?></td></tr>
                        <tr><th class="text-muted">Commission accrued</th>
                            <td class="text-end">₹<?= number_format((float) $invoice['total_commission'], 2) ?></td></tr>
                        <?php $gross = (float) $invoice['subtotal'] - (float) $invoice['total_cogs']; ?>
                        <tr><th class="text-muted">Gross Margin <small>(pre-tax)</small></th>
                            <td class="text-end <?= $gross >= 0 ? 'text-success' : 'text-danger' ?>">
                                ₹<?= number_format($gross, 2) ?>
                            </td></tr>
                    </table>
                <?php endif; ?>
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
                        <th>Display Name<br><small class="text-muted fw-normal">(product / brand)</small></th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">AddSub %</th>
                        <th class="text-end">Subtotal</th>
                        <?php if (!$hideLineTax): ?>
                            <th class="text-end">Tax</th>
                        <?php endif; ?>
                        <th class="text-end">Line Total</th>
                        <th>Salesman</th>
                        <?php if ($invoice['status'] === 'finalized'): ?>
                            <th class="text-end">COGS</th>
                            <th class="text-end">Commission</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $l): ?>
                        <?php $isComposite = (int) $l['is_composite_line'] === 1; $isAdHoc = $isComposite && $l['product_id'] === null; ?>
                        <tr>
                            <td><?= (int) $l['line_no'] ?></td>
                            <td>
                                <strong><?= e($l['display_name']) ?></strong>
                                <?php if ($isComposite): ?>
                                    <span class="badge text-bg-<?= $isAdHoc ? 'warning' : 'info' ?> ms-1">
                                        <i class="bi bi-diagram-3"></i> <?= $isAdHoc ? 'ad-hoc' : 'composite' ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!$isAdHoc): ?>
                                    <div class="small text-muted">
                                        <?= e($l['product_name']) ?> · <?= e($l['brand_name']) ?> · <?= e($l['product_unit']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($isComposite && !empty($lineComponents[(int) $l['id']])): ?>
                                    <?php $showComponents = $isAdHoc || (int) ($l['product_expose_components'] ?? 0) === 1; ?>
                                    <?php if ($showComponents): ?>
                                        <ul class="small text-muted mb-0 ps-3 mt-1" style="list-style:'◦ ';">
                                            <?php foreach ($lineComponents[(int) $l['id']] as $comp): ?>
                                                <li>
                                                    <?= e($comp['component_name']) ?>
                                                    — <?= number_format((float) $comp['quantity_consumed'], 3) ?>
                                                    <?= e($comp['component_unit']) ?>
                                                    <?php if ($invoice['status'] === 'finalized' && (float) $comp['cogs_total'] > 0): ?>
                                                        <span class="text-muted">(cogs ₹<?= number_format((float) $comp['cogs_total'], 2) ?>)</span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <div class="small text-muted"><em>components hidden (expose_components is OFF)</em></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= number_format((float) $l['quantity'], 3) ?></td>
                            <td class="text-end"><?= number_format((float) $l['unit_price'], 2) ?></td>
                            <td class="text-end"><?= number_format((float) $l['addsub_percent'], 2) ?>%</td>
                            <td class="text-end"><?= number_format((float) $l['subtotal'], 2) ?></td>
                            <?php if (!$hideLineTax): ?>
                                <td class="text-end"><?= number_format((float) $l['tax_amount'], 2) ?></td>
                            <?php endif; ?>
                            <td class="text-end fw-bold"><?= number_format((float) $l['line_total'], 2) ?></td>
                            <td>
                                <?php if ($l['salesman_name']): ?>
                                    <span class="badge text-bg-info"><?= e($l['salesman_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">direct</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($invoice['status'] === 'finalized'): ?>
                                <td class="text-end text-muted small">₹<?= number_format((float) $l['cogs_total'], 2) ?></td>
                                <td class="text-end text-muted small">
                                    <?php if ((float) $l['commission_amount'] > 0): ?>
                                        ₹<?= number_format((float) $l['commission_amount'], 2) ?>
                                        <div class="text-muted">@<?= number_format((float) $l['commission_rate'], 2) ?>%</div>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($invoice['status'] === 'finalized'): ?>
    <div class="d-flex align-items-center gap-3">
        <div class="alert alert-success small mb-0 flex-grow-1">
            Finalized <?= e($invoice['finalized_at']) ?>. Stock deducted, COGS &amp; commission snapshotted.
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-printer"></i> Print
            </button>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item" href="<?= url('/sales-invoices/' . $invoice['id'] . '/print/a4') ?>" target="_blank">
                        <i class="bi bi-file-earmark-text"></i> A4 Invoice
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= url('/sales-invoices/' . $invoice['id'] . '/print/thermal') ?>" target="_blank">
                        <i class="bi bi-receipt"></i> Thermal Receipt
                    </a>
                </li>
            </ul>
        </div>
        <a href="<?= url('/sales-payments/' . $invoice['id']) ?>" class="btn btn-primary">
            <i class="bi bi-cash-coin"></i>
            <?= (float) $invoice['due_amount'] > 0 ? 'Receive Payment' : 'View Payments' ?>
        </a>
    </div>
<?php endif; ?>
