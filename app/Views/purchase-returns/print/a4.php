<?php /** @var array $return */ /** @var array $lines */ /** @var array $business */ ?>
<?php $isModeA = $return['bill_format'] === 'discount_then_tax'; ?>
<?php $fd = (float) ($return['final_discount_share'] ?? 0); ?>

<div class="sheet sheet-a4">
    <!-- Header -->
    <table style="border-bottom: 2px solid #000; padding-bottom: 8mm; margin-bottom: 6mm;">
        <tr>
            <td style="width: 65%;">
                <div style="font-size: 22pt; font-weight: bold; letter-spacing: 0.5px;">
                    <?= e($business['display_name'] ?? env('APP_NAME')) ?>
                </div>
                <?php if (!empty($business['address'])): ?>
                    <div style="font-size: 9pt; margin-top: 2mm; white-space: pre-line;"><?= e($business['address']) ?></div>
                <?php endif; ?>
                <?php if (!empty($business['gstin'])): ?>
                    <div style="font-size: 9pt; margin-top: 1mm;"><strong>GSTIN:</strong> <?= e($business['gstin']) ?></div>
                <?php endif; ?>
            </td>
            <td style="width: 35%; text-align: right;">
                <div style="font-size: 18pt; font-weight: bold;">PURCHASE RETURN / DEBIT NOTE</div>
                <div style="margin-top: 3mm; font-size: 10pt;">
                    <strong>Return #:</strong> <?= e($return['return_number']) ?><br>
                    <strong>Date:</strong> <?= e($return['return_date']) ?><br>
                    <strong>Against Invoice:</strong> <?= e($return['original_invoice_number']) ?>
                </div>
            </td>
        </tr>
    </table>

    <!-- Supplier -->
    <table style="margin-bottom: 6mm;">
        <tr>
            <td style="width: 65%; padding: 3mm; border: 1px solid #aaa;">
                <div style="font-size: 9pt; color: #666;">SUPPLIER</div>
                <div style="font-weight: bold; font-size: 11pt;"><?= e($return['supplier_name']) ?></div>
                <?php if (!empty($return['supplier_phone'])): ?>
                    <div style="font-size: 9pt;">Phone: <?= e($return['supplier_phone']) ?></div>
                <?php endif; ?>
                <?php if (!empty($return['supplier_gstin'])): ?>
                    <div style="font-size: 9pt;"><strong>GSTIN:</strong> <?= e($return['supplier_gstin']) ?></div>
                <?php endif; ?>
                <?php if (!empty($return['supplier_address'])): ?>
                    <div style="font-size: 9pt; white-space: pre-line;"><?= e($return['supplier_address']) ?></div>
                <?php endif; ?>
            </td>
            <td style="width: 35%; padding: 3mm; border: 1px solid #aaa; border-left: 0;">
                <div style="font-size: 9pt; color: #666;">REFUND</div>
                <div style="font-size: 10pt;">
                    <?= e(str_replace('_', ' ', $return['refund_method'])) ?>
                </div>
                <?php if (!empty($return['reason'])): ?>
                    <div style="font-size: 9pt; margin-top: 2mm;"><strong>Reason:</strong> <?= e($return['reason']) ?></div>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- Returned lines -->
    <table style="margin-bottom: 4mm; font-size: 9pt;">
        <thead>
            <tr style="background: #eee; border-bottom: 1px solid #000;">
                <th style="padding: 2mm; text-align: left;">#</th>
                <th style="padding: 2mm; text-align: left;">Product</th>
                <th style="padding: 2mm; text-align: left;">Unit</th>
                <th style="padding: 2mm; text-align: right;">Qty Returned</th>
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
            <?php foreach ($lines as $rl): ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 2mm; vertical-align: top;"><?= (int) $rl['line_no'] ?></td>
                    <td style="padding: 2mm; vertical-align: top;"><strong><?= e($rl['product_name']) ?></strong></td>
                    <td style="padding: 2mm; vertical-align: top;"><?= e($rl['product_unit']) ?></td>
                    <td style="padding: 2mm; text-align: right; vertical-align: top;"><?= number_format((float) $rl['quantity_returned'], 3) ?></td>
                    <td style="padding: 2mm; text-align: right; vertical-align: top;"><?= number_format((float) $rl['unit_price'], 2) ?></td>
                    <td style="padding: 2mm; text-align: right; vertical-align: top;"><?= number_format((float) $rl['addsub_percent'], 2) ?></td>
                    <?php if (!$isModeA): ?>
                        <td style="padding: 2mm; text-align: right; vertical-align: top;"><?= number_format((float) $rl['tax_rate'], 2) ?></td>
                        <td style="padding: 2mm; text-align: right; vertical-align: top;"><?= number_format((float) $rl['tax_amount'], 2) ?></td>
                    <?php endif; ?>
                    <td style="padding: 2mm; text-align: right; vertical-align: top;"><?= number_format((float) $rl['line_total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <table style="margin-top: 4mm;">
        <tr>
            <td style="width: 55%; font-size: 9pt; padding: 2mm; vertical-align: top;">
                <strong>Remarks:</strong><br>
                <?= e($return['remarks'] ?? '') ?>
            </td>
            <td style="width: 45%; font-size: 10pt;">
                <table style="width: 100%;">
                    <tr><td style="padding: 1mm;">Subtotal <small style="color:#666">(returned)</small></td>
                        <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $return['subtotal'], 2) ?></td></tr>

                    <?php if ($fd > 0): ?>
                        <tr style="color:#070;"><td style="padding: 1mm;">− Final Discount share</td>
                            <td style="padding: 1mm; text-align: right;">− ₹<?= number_format($fd, 2) ?></td></tr>
                    <?php endif; ?>

                    <?php if ((float) $return['total_cgst'] > 0): ?>
                        <tr><td style="padding: 1mm;">CGST</td>
                            <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $return['total_cgst'], 2) ?></td></tr>
                        <tr><td style="padding: 1mm;">SGST</td>
                            <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $return['total_sgst'], 2) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $return['total_igst'] > 0): ?>
                        <tr><td style="padding: 1mm;">IGST</td>
                            <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $return['total_igst'], 2) ?></td></tr>
                    <?php endif; ?>

                    <?php if ((float) $return['expenses_share'] > 0): ?>
                        <tr><td style="padding: 1mm;">+ Expenses share</td>
                            <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $return['expenses_share'], 2) ?></td></tr>
                    <?php endif; ?>

                    <?php if ((float) ($return['return_charge_amount'] ?? 0) > 0): ?>
                        <tr><td style="padding: 1mm;">− Supplier charge / fine</td>
                            <td style="padding: 1mm; text-align: right;">− ₹<?= number_format((float) $return['return_charge_amount'], 2) ?></td></tr>
                        <?php if ((float) ($return['return_charge_tax_amount'] ?? 0) > 0): ?>
                            <tr><td style="padding: 1mm;">− Charge GST</td>
                                <td style="padding: 1mm; text-align: right;">− ₹<?= number_format((float) $return['return_charge_tax_amount'], 2) ?></td></tr>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ((float) $return['round_off_amount'] !== 0.0): ?>
                        <tr><td style="padding: 1mm;">Round Off</td>
                            <td style="padding: 1mm; text-align: right;">₹<?= number_format((float) $return['round_off_amount'], 2) ?></td></tr>
                    <?php endif; ?>
                    <tr style="border-top: 2px solid #000; font-weight: bold; font-size: 12pt;">
                        <td style="padding: 2mm 1mm;">DEBIT TOTAL</td>
                        <td style="padding: 2mm 1mm; text-align: right;">₹<?= number_format((float) $return['grand_total'], 2) ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Amount in words -->
    <div style="margin-top: 4mm; padding: 2mm 3mm; border: 1px solid #aaa; font-size: 10pt;">
        <strong>Amount in words:</strong>
        <?= e(\App\Support\AmountInWords::inr((float) $return['grand_total'])) ?>
    </div>

    <!-- Footer -->
    <div style="margin-top: 14mm; font-size: 9pt;">
        <table>
            <tr>
                <td style="width: 50%; vertical-align: bottom;">
                    <div style="font-size: 8pt; color: #555;">
                        Issued to supplier · E. &amp; O. E.
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
