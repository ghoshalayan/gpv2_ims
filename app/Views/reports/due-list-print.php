<?php
/** @var string $tab */
/** @var string $term */
/** @var string $search */
/** @var array  $rows */
/** @var array  $totals */
/** @var array  $business */
/** @var string $printedAt */
?>
<?php
    $isSalesman = $tab === 'salesman';
    $termLabels = ['short' => 'Short-term only', 'long' => 'Long-term only', 'untagged' => 'Untagged only'];
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
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                <div style="font-size: 15pt; font-weight: bold;">DUE LIST</div>
                <div style="font-size: 10pt; margin-top: 1mm;">
                    <?= $isSalesman ? 'Salesmen' : 'Customers' ?>
                </div>
                <div style="font-size: 9pt; color: #555; margin-top: 2mm;">
                    As on <?= e($printedAt) ?>
                </div>
                <?php if (!$isSalesman && isset($termLabels[$term])): ?>
                    <div style="font-size: 9pt; color: #555;">Filter: <?= e($termLabels[$term]) ?></div>
                <?php endif; ?>
                <?php if ($search !== ''): ?>
                    <div style="font-size: 9pt; color: #555;">Search: "<?= e($search) ?>"</div>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <?php if ($rows === []): ?>
        <p style="text-align: center; padding: 20mm 0; color: #666;">
            No outstanding dues match these filters.
        </p>
    <?php elseif ($isSalesman): ?>

        <table style="font-size: 9pt;">
            <thead>
                <tr style="border-bottom: 1.5px solid #000;">
                    <th style="text-align: left; padding: 2mm 1mm;">#</th>
                    <th style="text-align: left; padding: 2mm 1mm;">Salesman</th>
                    <th style="text-align: left; padding: 2mm 1mm;">Phone</th>
                    <th style="text-align: right; padding: 2mm 1mm;">Commission</th>
                    <th style="text-align: right; padding: 2mm 1mm;">Cash</th>
                    <th style="text-align: right; padding: 2mm 1mm;">Total</th>
                    <th style="text-align: left; padding: 2mm 1mm;">Direction</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <?php $net = (float) $r['total_due']; ?>
                    <tr style="border-bottom: 0.5px solid #ddd;">
                        <td style="padding: 1.5mm 1mm;"><?= $i + 1 ?></td>
                        <td style="padding: 1.5mm 1mm;"><?= e($r['name']) ?></td>
                        <td style="padding: 1.5mm 1mm;"><?= e($r['phone']) ?></td>
                        <td style="padding: 1.5mm 1mm; text-align: right;"><?= number_format((float) $r['commission_due'], 2) ?></td>
                        <td style="padding: 1.5mm 1mm; text-align: right;"><?= number_format((float) $r['cash_due'], 2) ?></td>
                        <td style="padding: 1.5mm 1mm; text-align: right; font-weight: bold;"><?= number_format($net, 2) ?></td>
                        <td style="padding: 1.5mm 1mm;">
                            <?php if (abs($net) < 0.01): ?>settled
                            <?php elseif ($net > 0): ?>we owe
                            <?php else: ?>owes us
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="border-top: 1.5px solid #000; font-weight: bold;">
                    <td colspan="3" style="padding: 2mm 1mm; text-align: right;">
                        Totals (<?= count($rows) ?> salesmen)
                    </td>
                    <td style="padding: 2mm 1mm; text-align: right;"><?= number_format((float) $totals['commission_due'], 2) ?></td>
                    <td style="padding: 2mm 1mm; text-align: right;"><?= number_format((float) $totals['cash_due'], 2) ?></td>
                    <td style="padding: 2mm 1mm; text-align: right;"><?= number_format((float) $totals['total_due'], 2) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <p style="font-size: 8pt; color: #555; margin-top: 4mm;">
            Commission = what the business owes the salesman (earned − reversed − settled).
            Cash = what the salesman owes the business on their own customer account.
            Total nets the two; a negative figure means the salesman owes the business.
        </p>

    <?php else: ?>

        <table style="font-size: 9pt;">
            <thead>
                <tr style="border-bottom: 1.5px solid #000;">
                    <th style="text-align: left; padding: 2mm 1mm;">#</th>
                    <th style="text-align: left; padding: 2mm 1mm;">Customer</th>
                    <th style="text-align: left; padding: 2mm 1mm;">Phone</th>
                    <th style="text-align: right; padding: 2mm 1mm;">Bills</th>
                    <th style="text-align: right; padding: 2mm 1mm;">Short</th>
                    <th style="text-align: right; padding: 2mm 1mm;">Long</th>
                    <th style="text-align: right; padding: 2mm 1mm;">Untagged</th>
                    <th style="text-align: right; padding: 2mm 1mm;">Total Due</th>
                    <th style="text-align: left; padding: 2mm 1mm;">Oldest</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr style="border-bottom: 0.5px solid #ddd;">
                        <td style="padding: 1.5mm 1mm;"><?= $i + 1 ?></td>
                        <td style="padding: 1.5mm 1mm;"><?= e($r['name']) ?></td>
                        <td style="padding: 1.5mm 1mm;"><?= e($r['phone']) ?></td>
                        <td style="padding: 1.5mm 1mm; text-align: right;"><?= (int) $r['invoice_count'] + (int) $r['legacy_count'] ?></td>
                        <td style="padding: 1.5mm 1mm; text-align: right;"><?= ((float) $r['short_due']) > 0 ? number_format((float) $r['short_due'], 2) : '—' ?></td>
                        <td style="padding: 1.5mm 1mm; text-align: right;"><?= ((float) $r['long_due']) > 0 ? number_format((float) $r['long_due'], 2) : '—' ?></td>
                        <td style="padding: 1.5mm 1mm; text-align: right;"><?= ((float) $r['untagged_due']) > 0 ? number_format((float) $r['untagged_due'], 2) : '—' ?></td>
                        <td style="padding: 1.5mm 1mm; text-align: right; font-weight: bold;"><?= number_format((float) $r['total_due'], 2) ?></td>
                        <td style="padding: 1.5mm 1mm;"><?= e($r['oldest_due_date'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="border-top: 1.5px solid #000; font-weight: bold;">
                    <td colspan="4" style="padding: 2mm 1mm; text-align: right;">
                        Totals (<?= count($rows) ?> customers)
                    </td>
                    <td style="padding: 2mm 1mm; text-align: right;"><?= number_format((float) $totals['short_due'], 2) ?></td>
                    <td style="padding: 2mm 1mm; text-align: right;"><?= number_format((float) $totals['long_due'], 2) ?></td>
                    <td style="padding: 2mm 1mm; text-align: right;"><?= number_format((float) $totals['untagged_due'], 2) ?></td>
                    <td style="padding: 2mm 1mm; text-align: right;"><?= number_format((float) $totals['total_due'], 2) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <p style="font-size: 8pt; color: #555; margin-top: 4mm;">
            Total Due includes ₹<?= number_format((float) $totals['legacy_due'], 2) ?> of legacy
            (pre-v2) obligations.
            <?php if ((float) $totals['advance_credit'] > 0): ?>
                Customers separately hold ₹<?= number_format((float) $totals['advance_credit'], 2) ?>
                in unapplied advances, which are not netted off these figures.
            <?php endif; ?>
        </p>

    <?php endif; ?>

    <div style="margin-top: 10mm; font-size: 8pt; color: #777; border-top: 0.5px solid #ccc; padding-top: 2mm;">
        Generated <?= e($printedAt) ?> · <?= e($business['display_name'] ?? env('APP_NAME')) ?>
    </div>
</div>
