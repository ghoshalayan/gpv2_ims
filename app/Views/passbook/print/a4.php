<?php
/** @var string $kind */
/** @var array  $context */
/** @var array  $rows */
/** @var array  $totals */
/** @var string $from */
/** @var string $to */
/** @var float  $computedOpening */
/** @var float  $closingBalance */
/** @var array  $business */
/** @var string $printedAt */
?>
<?php
    $invertBalance = !empty($context['invert_balance']);
    $display       = $invertBalance ? -$closingBalance : $closingBalance;
?>

<div class="no-print">
    <button onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="sheet sheet-a4">
    <table style="border-bottom: 2px solid #000; padding-bottom: 6mm; margin-bottom: 5mm;">
        <tr>
            <td style="width: 60%;">
                <div style="font-size: 20pt; font-weight: bold;">
                    <?= e($business['display_name'] ?? env('APP_NAME')) ?>
                </div>
                <?php if (!empty($business['address'])): ?>
                    <div style="font-size: 9pt; margin-top: 2mm; white-space: pre-line;"><?= e($business['address']) ?></div>
                <?php endif; ?>
                <?php if (!empty($business['phone'])): ?>
                    <div style="font-size: 9pt;">Phone: <?= e($business['phone']) ?></div>
                <?php endif; ?>
                <?php if (!empty($business['gstin'])): ?>
                    <div style="font-size: 9pt;">GSTIN: <?= e($business['gstin']) ?></div>
                <?php endif; ?>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                <div style="font-size: 15pt; font-weight: bold;">STATEMENT OF ACCOUNT</div>
                <div style="font-size: 11pt; margin-top: 2mm; font-weight: bold;">
                    <?= e($context['entity_name'] ?? '') ?>
                </div>
                <?php if (!empty($context['phone'])): ?>
                    <div style="font-size: 9pt; color: #555;"><?= e($context['phone']) ?></div>
                <?php endif; ?>
                <div style="font-size: 9pt; color: #555; margin-top: 2mm;">
                    Period: <?= e($from) ?> to <?= e($to) ?>
                </div>
            </td>
        </tr>
    </table>

    <!-- Summary band -->
    <table style="margin-bottom: 5mm; font-size: 9pt; border: 1px solid #000;">
        <tr>
            <td style="padding: 2.5mm; border-right: 1px solid #ccc;">
                <div style="color: #555;">Opening balance</div>
                <div style="font-size: 11pt; font-weight: bold;"><?= number_format($computedOpening, 2) ?></div>
            </td>
            <td style="padding: 2.5mm; border-right: 1px solid #ccc;">
                <div style="color: #555;">Total <?= e($context['debit_label']) ?></div>
                <div style="font-size: 11pt; font-weight: bold;"><?= number_format((float) $totals['debit'], 2) ?></div>
            </td>
            <td style="padding: 2.5mm; border-right: 1px solid #ccc;">
                <div style="color: #555;">Total <?= e($context['credit_label']) ?></div>
                <div style="font-size: 11pt; font-weight: bold;"><?= number_format((float) $totals['credit'], 2) ?></div>
            </td>
            <td style="padding: 2.5mm; background: #f2f2f2;">
                <div style="color: #555;"><?= e($context['balance_label']) ?></div>
                <div style="font-size: 13pt; font-weight: bold;"><?= number_format($display, 2) ?></div>
                <?php if (!empty($context['positive_means'])): ?>
                    <div style="font-size: 7.5pt; color: #555;">+ = <?= e($context['positive_means']) ?></div>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <?php if ($rows === []): ?>
        <p style="text-align: center; padding: 20mm 0; color: #666;">
            No transactions in this period.
        </p>
    <?php else: ?>
        <table style="font-size: 8.5pt;">
            <thead>
                <tr style="border-bottom: 1.5px solid #000;">
                    <th style="text-align: left;  padding: 2mm 1mm; width: 6%;">#</th>
                    <th style="text-align: left;  padding: 2mm 1mm; width: 12%;">Date</th>
                    <th style="text-align: left;  padding: 2mm 1mm; width: 15%;">Reference</th>
                    <th style="text-align: left;  padding: 2mm 1mm;">Particulars</th>
                    <th style="text-align: right; padding: 2mm 1mm; width: 13%;"><?= e($context['debit_label']) ?></th>
                    <th style="text-align: right; padding: 2mm 1mm; width: 13%;"><?= e($context['credit_label']) ?></th>
                    <th style="text-align: right; padding: 2mm 1mm; width: 14%;">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 0.5px solid #ddd; background: #f7f7f7;">
                    <td colspan="4" style="padding: 1.5mm 1mm;"><em>Opening balance as of <?= e($from) ?></em></td>
                    <td></td>
                    <td></td>
                    <td style="padding: 1.5mm 1mm; text-align: right; font-weight: bold;">
                        <?= number_format($computedOpening, 2) ?>
                    </td>
                </tr>
                <?php foreach ($rows as $i => $r): ?>
                    <?php $bal = $invertBalance ? -$r['running_balance'] : $r['running_balance']; ?>
                    <tr style="border-bottom: 0.5px solid #ddd;">
                        <td style="padding: 1.5mm 1mm;"><?= $i + 1 ?></td>
                        <td style="padding: 1.5mm 1mm;"><?= e($r['txn_date']) ?></td>
                        <td style="padding: 1.5mm 1mm;"><?= e($r['ref_number'] ?? '') ?></td>
                        <td style="padding: 1.5mm 1mm;"><?= e($r['description'] ?? '') ?></td>
                        <td style="padding: 1.5mm 1mm; text-align: right;">
                            <?= (float) $r['debit'] > 0 ? number_format((float) $r['debit'], 2) : '—' ?>
                        </td>
                        <td style="padding: 1.5mm 1mm; text-align: right;">
                            <?= (float) $r['credit'] > 0 ? number_format((float) $r['credit'], 2) : '—' ?>
                        </td>
                        <td style="padding: 1.5mm 1mm; text-align: right; font-weight: bold;">
                            <?= number_format((float) $bal, 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="border-top: 1.5px solid #000; font-weight: bold;">
                    <td colspan="4" style="padding: 2mm 1mm; text-align: right;">Period totals</td>
                    <td style="padding: 2mm 1mm; text-align: right;"><?= number_format((float) $totals['debit'], 2) ?></td>
                    <td style="padding: 2mm 1mm; text-align: right;"><?= number_format((float) $totals['credit'], 2) ?></td>
                    <td style="padding: 2mm 1mm; text-align: right;"><?= number_format($display, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <div style="margin-top: 10mm; font-size: 8pt; color: #777; border-top: 0.5px solid #ccc; padding-top: 2mm;">
        Generated <?= e($printedAt) ?> · <?= e($business['display_name'] ?? env('APP_NAME')) ?>.
        This is a computer-generated statement.
    </div>
</div>
