<?php /** @var array $invoice */ /** @var array $lines */ /** @var array $expenses */ /** @var array $business */ ?>
<?php $isModeA = $invoice['bill_format'] === 'discount_then_tax'; ?>
<?php $fd = (float) ($invoice['final_discount_amount'] ?? 0); ?>
<?php $fmt = fn($n) => number_format((float) $n, 2); ?>

<div class="sheet sheet-thermal">
    <div style="text-align: center; font-weight: bold; font-size: 14px;">
        <?= e($business['display_name'] ?? env('APP_NAME')) ?>
    </div>
    <?php if (!empty($business['address'])): ?>
        <div style="text-align: center; font-size: 10px; white-space: pre-line;">
            <?= e($business['address']) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($business['gstin'])): ?>
        <div style="text-align: center; font-size: 10px;">GSTIN: <?= e($business['gstin']) ?></div>
    <?php endif; ?>

    <div style="border-top: 1px dashed #000; margin: 2mm 0;"></div>

    <div style="text-align: center; font-weight: bold;">PURCHASE INVOICE</div>
    <div>Inv #: <?= e($invoice['invoice_number']) ?></div>
    <div>Date : <?= e($invoice['invoice_date']) ?></div>
    <div>Suppl: <?= e($invoice['supplier_name']) ?></div>
    <div>Brand: <?= e($invoice['brand_name']) ?></div>

    <div style="border-top: 1px dashed #000; margin: 2mm 0;"></div>

    <table style="width: 100%; font-size: 11px;">
        <?php foreach ($lines as $l): ?>
            <tr><td colspan="3" style="font-weight: bold;"><?= e($l['product_name']) ?></td></tr>
            <tr>
                <td style="padding-left: 3mm;"><?= number_format((float) $l['quantity'], 3) ?> × ₹<?= $fmt($l['unit_price']) ?></td>
                <td style="text-align: right;">
                    <?php if (!$isModeA && (float) $l['tax_amount'] > 0): ?>
                        <span style="font-size: 9px; color: #333;">+₹<?= $fmt($l['tax_amount']) ?></span>
                    <?php endif; ?>
                </td>
                <td style="text-align: right; font-weight: bold;">₹<?= $fmt($l['line_total']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <?php if (!empty($expenses)): ?>
        <div style="border-top: 1px dashed #000; margin: 2mm 0;"></div>
        <table style="width: 100%; font-size: 11px;">
            <?php foreach ($expenses as $ex): ?>
                <tr>
                    <td><?= e($ex['expense_type']) ?>
                        <?php if ((float) $ex['tax_rate'] > 0): ?>
                            <span style="font-size: 9px; color:#555;">(@<?= $fmt($ex['tax_rate']) ?>%)</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">₹<?= $fmt($ex['amount']) ?></td>
                </tr>
                <?php if ((float) $ex['tax_amount'] > 0): ?>
                    <tr>
                        <td style="padding-left: 4mm; font-size: 9px; color:#555;">+ tax</td>
                        <td style="text-align:right; font-size: 9px; color:#555;">₹<?= $fmt($ex['tax_amount']) ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <div style="border-top: 1px dashed #000; margin: 2mm 0;"></div>

    <table style="width: 100%; font-size: 11px;">
        <tr><td>Subtotal</td><td style="text-align:right;">₹<?= $fmt($invoice['subtotal']) ?></td></tr>
        <?php if ($isModeA && $fd > 0): ?>
            <tr><td>− Discount</td><td style="text-align:right;">− ₹<?= $fmt($fd) ?></td></tr>
            <tr><td>= Taxable</td><td style="text-align:right;">₹<?= $fmt((float) $invoice['subtotal'] - $fd) ?></td></tr>
        <?php endif; ?>
        <?php if ((float) $invoice['total_cgst'] > 0): ?>
            <tr><td>CGST</td><td style="text-align:right;">₹<?= $fmt($invoice['total_cgst']) ?></td></tr>
            <tr><td>SGST</td><td style="text-align:right;">₹<?= $fmt($invoice['total_sgst']) ?></td></tr>
        <?php endif; ?>
        <?php if ((float) $invoice['total_igst'] > 0): ?>
            <tr><td>IGST</td><td style="text-align:right;">₹<?= $fmt($invoice['total_igst']) ?></td></tr>
        <?php endif; ?>
        <?php if ((float) $invoice['total_expenses'] > 0): ?>
            <tr><td>+ Expenses</td><td style="text-align:right;">₹<?= $fmt($invoice['total_expenses']) ?></td></tr>
        <?php endif; ?>
        <?php if ((float) $invoice['round_off_amount'] !== 0.0): ?>
            <tr><td>Round Off</td><td style="text-align:right;">₹<?= $fmt($invoice['round_off_amount']) ?></td></tr>
        <?php endif; ?>
        <tr style="border-top: 1px solid #000; font-weight: bold; font-size: 13px;">
            <td style="padding-top: 1mm;">TOTAL</td>
            <td style="padding-top: 1mm; text-align:right;">₹<?= $fmt($invoice['grand_total']) ?></td>
        </tr>
        <?php if ((float) $invoice['paid_amount'] > 0): ?>
            <tr><td>Paid</td><td style="text-align:right;">₹<?= $fmt($invoice['paid_amount']) ?></td></tr>
            <?php if ((float) $invoice['due_amount'] > 0): ?>
                <tr style="font-weight: bold;">
                    <td>Due</td><td style="text-align:right;">₹<?= $fmt($invoice['due_amount']) ?></td>
                </tr>
            <?php endif; ?>
        <?php endif; ?>
    </table>

    <div style="border-top: 1px dashed #000; margin: 2mm 0;"></div>
    <div style="text-align: center; font-size: 9px; color: #555;">
        Internal record · Printed: <?= e(date('Y-m-d H:i')) ?>
    </div>
</div>
