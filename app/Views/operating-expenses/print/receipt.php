<?php /** @var array $expense */ /** @var array $business */ ?>
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

    <div style="text-align: center; font-weight: bold;">EXPENSE VOUCHER</div>
    <div>Vchr # : <?= e($expense['expense_number']) ?></div>
    <div>Date   : <?= e($expense['expense_date']) ?></div>
    <div>Cat    : <?= e($expense['category_name']) ?></div>
    <?php if (!empty($expense['vendor_name'])): ?>
        <div>Payee  : <?= e($expense['vendor_name']) ?></div>
    <?php endif; ?>
    <div>From   : <?= e($expense['paid_from_name']) ?>
        <span style="font-size: 9px; color:#555;">(<?= e(str_replace('_', ' ', $expense['paid_from_kind'])) ?>)</span>
    </div>
    <div>Mode   : <?= strtoupper(e($expense['payment_mode'])) ?>
        <?php if (!empty($expense['payment_reference'])): ?>
            <span style="font-size: 9px; color:#555;">· Ref: <?= e($expense['payment_reference']) ?></span>
        <?php endif; ?>
    </div>

    <div style="border-top: 1px dashed #000; margin: 2mm 0;"></div>

    <table style="width: 100%; font-size: 11px;">
        <tr><td>Amount</td><td style="text-align:right;">₹<?= $fmt($expense['amount']) ?></td></tr>
        <?php if ((float) $expense['gst_amount'] > 0): ?>
            <tr><td>GST @ <?= $fmt($expense['gst_rate']) ?>%</td>
                <td style="text-align:right;">₹<?= $fmt($expense['gst_amount']) ?></td></tr>
            <tr><td style="font-size: 9px; color:#555;">&nbsp;&nbsp;input claimable?</td>
                <td style="text-align:right; font-size: 9px; color:#555;"><?= (int) $expense['gst_claimable'] === 1 ? 'Yes' : 'No' ?></td></tr>
        <?php endif; ?>
        <tr style="border-top: 1px solid #000; font-weight: bold; font-size: 13px;">
            <td style="padding-top: 1mm;">PAID</td>
            <td style="padding-top: 1mm; text-align:right;">₹<?= $fmt($expense['total_with_gst']) ?></td>
        </tr>
    </table>

    <?php if (!empty($expense['remarks'])): ?>
        <div style="margin-top: 2mm; font-size: 10px;">
            <strong>Remarks:</strong> <?= e($expense['remarks']) ?>
        </div>
    <?php endif; ?>

    <div style="border-top: 1px dashed #000; margin: 2mm 0;"></div>

    <div style="font-size: 10px;">
        <strong>Amount in words:</strong>
        <?= e(\App\Support\AmountInWords::inr((float) $expense['total_with_gst'])) ?>
    </div>

    <div style="border-top: 1px dashed #000; margin: 2mm 0;"></div>
    <div style="text-align: center; font-size: 9px; color: #555;">
        Internal record · Printed: <?= e(date('Y-m-d H:i')) ?>
    </div>
</div>
