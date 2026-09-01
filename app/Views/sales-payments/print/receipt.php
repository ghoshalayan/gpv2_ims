<?php /** @var array $payment */ /** @var array $invoice */ /** @var array $business */ ?>
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
    <?php if (!empty($business['phone'])): ?>
        <div style="text-align: center; font-size: 10px;">Ph: <?= e($business['phone']) ?></div>
    <?php endif; ?>

    <div style="border-top: 1px dashed #000; margin: 2mm 0;"></div>

    <div style="text-align: center; font-weight: bold;">PAYMENT RECEIPT</div>
    <div>Recpt #: <?= (int) $payment['id'] ?></div>
    <div>Date  : <?= e($payment['payment_date']) ?></div>
    <div>From  : <?= e($invoice['customer_name']) ?></div>
    <div>Inv # : <?= e($invoice['invoice_number']) ?></div>

    <div style="border-top: 1px dashed #000; margin: 2mm 0;"></div>

    <table style="width: 100%; font-size: 11px;">
        <?php if ((float) $payment['cash_amount'] > 0): ?>
            <tr><td>Cash</td><td style="text-align:right;">₹<?= $fmt($payment['cash_amount']) ?></td></tr>
        <?php endif; ?>
        <?php if ((float) $payment['online_amount'] > 0): ?>
            <tr><td>Online</td><td style="text-align:right;">₹<?= $fmt($payment['online_amount']) ?></td></tr>
            <?php if (!empty($payment['online_reference'])): ?>
                <tr><td colspan="2" style="font-size: 9px; color:#555;">Ref: <?= e($payment['online_reference']) ?></td></tr>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ((float) $payment['cheque_amount'] > 0): ?>
            <tr><td>Cheque (<?= e($payment['cheque_status']) ?>)</td>
                <td style="text-align:right;">₹<?= $fmt($payment['cheque_amount']) ?></td></tr>
            <?php if (!empty($payment['cheque_number'])): ?>
                <tr><td colspan="2" style="font-size: 9px; color:#555;">
                    No. <?= e($payment['cheque_number']) ?>
                    <?php if (!empty($payment['cheque_bank_name'])): ?> · <?= e($payment['cheque_bank_name']) ?><?php endif; ?>
                    <?php if (!empty($payment['cheque_date'])): ?> · <?= e($payment['cheque_date']) ?><?php endif; ?>
                </td></tr>
            <?php endif; ?>
        <?php endif; ?>
        <tr style="border-top: 1px solid #000; font-weight: bold; font-size: 13px;">
            <td style="padding-top: 1mm;">RECEIVED</td>
            <td style="padding-top: 1mm; text-align:right;">₹<?= $fmt($payment['total_amount']) ?></td>
        </tr>
    </table>

    <?php if (!empty($payment['remarks'])): ?>
        <div style="margin-top: 2mm; font-size: 10px;">
            <strong>Remarks:</strong> <?= e($payment['remarks']) ?>
        </div>
    <?php endif; ?>

    <div style="border-top: 1px dashed #000; margin: 2mm 0;"></div>

    <div style="font-size: 10px;">
        <strong>Amount in words:</strong>
        <?= e(\App\Support\AmountInWords::inr((float) $payment['total_amount'])) ?>
    </div>

    <div style="border-top: 1px dashed #000; margin: 2mm 0;"></div>

    <table style="width: 100%; font-size: 10px;">
        <tr>
            <td>Invoice Total:</td>
            <td style="text-align:right;">₹<?= $fmt($invoice['grand_total']) ?></td>
        </tr>
        <tr>
            <td>Total Paid:</td>
            <td style="text-align:right;">₹<?= $fmt($invoice['paid_amount']) ?></td>
        </tr>
        <tr style="font-weight: bold; <?= (float) $invoice['due_amount'] > 0 ? 'color:#b00;' : '' ?>">
            <td>Balance Due:</td>
            <td style="text-align:right;">₹<?= $fmt($invoice['due_amount']) ?></td>
        </tr>
    </table>

    <div style="text-align: center; font-size: 9px; color: #555; margin-top: 3mm;">
        Thank you · Printed: <?= e(date('Y-m-d H:i')) ?>
    </div>
</div>
