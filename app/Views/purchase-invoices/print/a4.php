<?php /** @var array $invoice */ /** @var array $lines */ /** @var array $expenses */ /** @var array $business */ ?>
<?php $isModeA = $invoice['bill_format'] === 'discount_then_tax'; ?>
<?php $fd      = (float) ($invoice['final_discount_amount'] ?? 0); ?>
<?php $taxableLines = (float) $invoice['subtotal'] - $fd; ?>

<div class="sheet sheet-a4">
    <!-- Header -->
    <table style="border-bottom: 2px solid #000; padding-bottom: 8mm; margin-bottom: 6mm;">
        <tr>
            <td style="width: 65%;">
                <div style="font-size: 22pt; font-weight: bold; letter-spacing: 0.5px;">
                    <?= e($business['display_name'] ?? env('APP_NAME')) ?>
                </div>
                <?php if (!empty($business['legal_name']) && $business['legal_name'] !== ($business['display_name'] ?? '')): ?>
                    <div style="font-size: 10pt; color: #555;"><?= e($business['legal_name']) ?></div>
                <?php endif; ?>
                <?php if (!empty($business['address'])): ?>
                    <div style="font-size: 9pt; margin-top: 2mm; white-space: pre-line;"><?= e($business['address']) ?></div>
                <?php endif; ?>
                <div style="font-size: 9pt; margin-top: 1mm;">
                    <?php if (!empty($business['phone'])): ?>Phone: <?= e($business['phone']) ?><?php endif; ?>
                    <?php if (!empty($business['email'])): ?> · <?= e($business['email']) ?><?php endif; ?>
                </div>
                <?php if (!empty($business['gstin'])): ?>
                    <div style="font-size: 9pt; margin-top: 1mm;"><strong>GSTIN:</strong> <?= e($business['gstin']) ?></div>
                <?php endif; ?>
                <?php if (!empty($business['state'])): ?>
                    <div style="font-size: 9pt;"><strong>State:</strong> <?= e($business['state']) ?></div>
                <?php endif; ?>
            </td>
            <td style="width: 35%; text-align: right;">
                <div style="font-size: 18pt; font-weight: bold;">PURCHASE INVOICE</div>
                <div style="margin-top: 3mm; font-size: 10pt;">
                    <strong>Invoice #:</strong> <?= e($invoice['invoice_number']) ?><br>
                    <strong>Date:</strong> <?= e($invoice['invoice_date']) ?><br>
                </div>
            </td>
        </tr>
    </table>

    <!-- Supplier -->
    <table style="margin-bottom: 6mm;">
        <tr>
            <td style="width: 65%; padding: 3mm; border: 1px solid #aaa;">
                <div style="font-size: 9pt; color: #666;">SUPPLIER</div>
                <div style="font-weight: bold; font-size: 11pt;"><?= e($invoice['supplier_name']) ?></div>
                <?php if (!empty($invoice['supplier_state'])): ?>
                    <div style="font-size: 9pt;"><strong>State:</strong> <?= e($invoice['supplier_state']) ?></div>
                <?php endif; ?>
                <div style="font-size: 9pt; margin-top: 1mm;">
                    <strong>Brand on this invoice:</strong> <?= e($invoice['brand_name']) ?>
                </div>
            </td>
            <td style="width: 35%; padding: 3mm; border: 1px solid #aaa; border-left: 0;">
                <div style="font-size: 9pt; color: #666;">GST SCHEME</div>
                <div style="font-size: 10pt;">
                    <?= (int) $invoice['is_intra_state'] === 1 ? 'Intra-state (CGST + SGST)' : 'Inter-state (IGST)' ?>
                </div>
                <div style="font-size: 9pt; margin-top: 2mm;">
                    <strong>Bill Format:</strong> <?= e(str_replace('_', ' ', $invoice['bill_format'])) ?><br>
                    <strong>Tax Treatment:</strong> <?= e($invoice['tax_treatment']) ?><br>
                    <strong>GST Claimable:</strong> <?= (int) $invoice['gst_claimable'] === 1 ? 'Yes' : 'No' ?>
                </div>
            </td>
        </tr>
    </table>

    <!-- Lines -->
    <table style="margin-bottom: 4mm; font-size: 9pt;">
        <thead>
            <tr style="background: #eee; border-bottom: 1px solid #000;">
                <th style="padding: 2mm; text-align: left;">#</th>
                <th style="padding: 2mm; text-align: left;">Product</th>
                <th style="padding: 2mm; text-align: right;">Qty</th>
                <th style="padding: 2mm; text-align: left;">Unit</th>
                <th style="padding: 2mm; text-align: right;">Rate</th>
                <th style="padding: 2mm; text-align: right;">Adj %</th>
                <?php if (!$isModeA): ?>
                    <th style="padding: 2mm; text-align: right;">Tax %</th>
                    <th style="padding: 2mm; text-align: right;">Tax</th>
                <?php endif; ?>
                <th style="padding: 2mm; text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lines as $l): ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 2mm; vertical-align: top;"><?= (int) $l['line_no'] ?></td>
                    <td style="padding: 2mm; vertical-align: top;"><strong><?= e($l['product_name']) ?></strong></td>
                    <td style="padding: 2mm; text-align: right; vertical-align: top;"><?= number_format((float) $l['quantity'], 3) ?></td>
                    <td style="padding: 2mm; vertical-align: top;"><?= e($l['product_unit'] ?? '') ?></td>
                    <td style="padding: 2mm; text-align: right; vertical-align: top;"><?= number_format((float) $l['unit_price'], 2) ?></td>
                    <td style="padding: 2mm; text-align: right; vertical-align: top;"><?= number_format((float) $l['addsub_percent'], 2) ?></td>
                    <?php if (!$isModeA): ?>
                        <td style="padding: 2mm; text-align: right; vertical-align: top;"><?= number_format((float) $l['tax_rate'], 2) ?></td>
                        <td style="padding: 2mm; text-align: right; vertical-align: top;"><?= number_format((float) $l['tax_amount'], 2) ?></td>
                    <?php endif; ?>
                    <td style="padding: 2mm; text-align: right; vertical-align: top;"><?= number_format((float) $l['line_total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($expenses)): ?>
        <!-- Expenses -->
        <table style="margin-top: 2mm; font-size: 9pt;">
            <thead>
                <tr style="background: #f6f6f6; border-bottom: 1px solid #bbb;">
                    <th style="padding: 1.5mm; text-align: left;">Expenses (loading, transport, fees…)</th>
                    <th style="padding: 1.5mm; text-align: right;">Tax %</th>
                    <th style="padding: 1.5mm; text-align: right;">Tax</th>
                    <th style="padding: 1.5mm; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $ex): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 1.5mm;">
                            <?= e($ex['expense_type']) ?>
                            <?php if (!empty($ex['remarks'])): ?>
                                <span style="color:#666; font-size: 8pt;">— <?= e($ex['remarks']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1.5mm; text-align: right;"><?= number_format((float) $ex['tax_rate'], 2) ?></td>
                        <td style="padding: 1.5mm; text-align: right;"><?= number_format((float) $ex['tax_amount'], 2) ?></td>
                        <td style="padding: 1.5mm; text-align: right;"><?= number_format((float) $ex['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Totals -->
    <table style="margin-top: 4mm;">
        <tr>
            <td style="width: 55%; font-size: 9pt; padding: 2mm; vertical-align: top;">
                <strong>Remarks:</strong><br>
                <?= e($invoice['remarks'] ?? '') ?>
            </td>
            <td style="width: 45%; font-size: 10pt;">
                <table style="width: 100%;">
                    <tr><td style="padding: 1mm;">Subtotal <small style="color:#666">(lines)</small></td>
                        <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $invoice['subtotal'], 2) ?></td></tr>

                    <?php if ($isModeA && $fd > 0): ?>
                        <tr style="color: #070;"><td style="padding: 1mm;">− Final Discount</td>
                            <td style="padding: 1mm; text-align: right;">− ₹<?= number_format($fd, 2) ?></td></tr>
                        <tr><td style="padding: 1mm;">= Taxable (lines)</td>
                            <td style="padding: 1mm; text-align: right;">₹<?= number_format($taxableLines, 2) ?></td></tr>
                    <?php endif; ?>

                    <?php if ((float) $invoice['total_cgst'] > 0): ?>
                        <tr><td style="padding: 1mm;">CGST</td>
                            <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $invoice['total_cgst'], 2) ?></td></tr>
                        <tr><td style="padding: 1mm;">SGST</td>
                            <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $invoice['total_sgst'], 2) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $invoice['total_igst'] > 0): ?>
                        <tr><td style="padding: 1mm;">IGST</td>
                            <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $invoice['total_igst'], 2) ?></td></tr>
                    <?php endif; ?>

                    <?php if ((float) $invoice['total_expenses'] > 0): ?>
                        <tr><td style="padding: 1mm;">+ Expenses</td>
                            <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $invoice['total_expenses'], 2) ?></td></tr>
                        <?php if ((float) $invoice['total_expense_tax'] > 0): ?>
                            <tr><td style="padding: 1mm; font-size: 8pt; color:#666;">&nbsp;&nbsp;incl. expense tax</td>
                                <td style="padding: 1mm; text-align: right; font-size: 8pt; color:#666;">₹<?= number_format((float) $invoice['total_expense_tax'], 2) ?></td></tr>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ((float) $invoice['round_off_amount'] !== 0.0): ?>
                        <tr><td style="padding: 1mm;">Round Off</td>
                            <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $invoice['round_off_amount'], 2) ?></td></tr>
                    <?php endif; ?>
                    <tr style="border-top: 2px solid #000; font-weight: bold; font-size: 12pt;">
                        <td style="padding: 2mm 1mm;">GRAND TOTAL</td>
                        <td style="padding: 2mm 1mm; text-align: right;">₹<?= number_format((float) $invoice['grand_total'], 2) ?></td></tr>
                    <?php if ((float) $invoice['paid_amount'] > 0): ?>
                        <tr><td style="padding: 1mm;">Paid</td>
                            <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $invoice['paid_amount'], 2) ?></td></tr>
                        <tr <?= (float) $invoice['due_amount'] > 0 ? 'style="color: #b00; font-weight: bold;"' : '' ?>>
                            <td style="padding: 1mm;">Due</td>
                            <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $invoice['due_amount'], 2) ?></td></tr>
                    <?php endif; ?>
                </table>
            </td>
        </tr>
    </table>

    <!-- Amount in words -->
    <div style="margin-top: 4mm; padding: 2mm 3mm; border: 1px solid #aaa; font-size: 10pt;">
        <strong>Amount in words:</strong>
        <?= e(\App\Support\AmountInWords::inr((float) $invoice['grand_total'])) ?>
    </div>

    <!-- Footer -->
    <div style="margin-top: 14mm; font-size: 9pt;">
        <table>
            <tr>
                <td style="width: 50%; vertical-align: bottom;">
                    <div style="font-size: 8pt; color: #555;">
                        Internal record · E. &amp; O. E. &nbsp; · &nbsp; Subject to local jurisdiction.
                    </div>
                </td>
                <td style="width: 50%; text-align: right; vertical-align: bottom;">
                    <div style="border-top: 1px solid #000; display: inline-block; padding: 1mm 15mm 0 15mm; margin-top: 18mm;">
                        Authorised Signatory
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
