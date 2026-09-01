<?php
/** @var string $from */
/** @var string $to */
/** @var array  $pl */
/** @var array  $business */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-graph-up"></i> Profit &amp; Loss</h1>
    <div class="text-muted small">
        <strong><?= e($business['legal_name'] ?? '—') ?></strong><br>
        <?= e($from) ?> to <?= e($to) ?>
    </div>
</div>

<form method="get" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="from">From</label>
                <input type="date" id="from" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="to">To</label>
                <input type="date" id="to" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Apply</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/reports/profit-loss') ?>">Reset (FY)</a>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center">
        <div class="text-muted small">Net Revenue</div>
        <div class="h4 mb-0">₹<?= number_format($pl['revenue']['net'], 2) ?></div>
        <div class="small text-muted">excl. tax · less returns</div>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center">
        <div class="text-muted small">Gross Profit</div>
        <div class="h4 mb-0 <?= $pl['gross_profit'] < 0 ? 'text-danger' : '' ?>">₹<?= number_format($pl['gross_profit'], 2) ?></div>
        <div class="small text-muted"><?= $pl['gross_margin_pct'] ?>% margin</div>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center">
        <div class="text-muted small">Operating Expenses</div>
        <div class="h4 mb-0 text-danger">₹<?= number_format($pl['operating_expenses']['total'], 2) ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100 bg-<?= $pl['net_profit'] >= 0 ? 'success' : 'danger' ?> text-white"><div class="card-body text-center">
        <div class="small">Net Profit</div>
        <div class="h3 mb-0 fw-bold"><?= $pl['net_profit'] >= 0 ? '+' : '' ?>₹<?= number_format($pl['net_profit'], 2) ?></div>
        <div class="small"><?= $pl['net_margin_pct'] ?>% margin</div>
    </div></div></div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Item</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <!-- Revenue -->
                <tr class="table-light"><th colspan="3">Revenue</th></tr>
                <tr>
                    <td class="ps-4">Sales (gross, excl. tax)</td>
                    <td class="text-end">₹<?= number_format($pl['revenue']['sales_gross'], 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4">Less: sales returns</td>
                    <td class="text-end text-danger">−₹<?= number_format($pl['revenue']['sales_returns'], 2) ?></td>
                    <td></td>
                </tr>
                <tr class="fw-bold">
                    <td class="ps-3">Net Revenue</td>
                    <td></td>
                    <td class="text-end">₹<?= number_format($pl['revenue']['net'], 2) ?></td>
                </tr>

                <!-- COGS -->
                <tr class="table-light"><th colspan="3">Cost of Goods Sold</th></tr>
                <tr>
                    <td class="ps-4">Cost of sales (cogs at moving-avg)</td>
                    <td class="text-end">₹<?= number_format($pl['cogs']['cost_of_sales'], 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4">Less: returned stock cost (re-entered)</td>
                    <td class="text-end text-success">−₹<?= number_format($pl['cogs']['return_cost'], 2) ?></td>
                    <td></td>
                </tr>
                <tr class="fw-bold">
                    <td class="ps-3">Net COGS</td>
                    <td></td>
                    <td class="text-end">₹<?= number_format($pl['cogs']['net'], 2) ?></td>
                </tr>

                <!-- Gross profit -->
                <tr class="table-info fw-bold">
                    <td>Gross Profit</td>
                    <td></td>
                    <td class="text-end">₹<?= number_format($pl['gross_profit'], 2) ?></td>
                </tr>

                <!-- Other income -->
                <tr class="table-light"><th colspan="3">Other Income</th></tr>
                <tr>
                    <td class="ps-4">Brand rebate — cash back</td>
                    <td class="text-end">₹<?= number_format($pl['other_income']['brand_rebate_cash_back'], 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4">Brand rebate — reduce supplier payable</td>
                    <td class="text-end">₹<?= number_format($pl['other_income']['brand_rebate_reduce_payable'], 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4">Brand rebate — cost-reduction spillover</td>
                    <td class="text-end">₹<?= number_format($pl['other_income']['brand_rebate_cost_spillover'], 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4">Brand rebate — free stock value</td>
                    <td class="text-end">₹<?= number_format($pl['other_income']['brand_rebate_free_stock'], 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4">Sales-return restocking fee / fine</td>
                    <td class="text-end">₹<?= number_format($pl['other_income']['sales_return_charge'] ?? 0, 2) ?></td>
                    <td></td>
                </tr>
                <tr class="fw-bold">
                    <td class="ps-3">Total Other Income</td>
                    <td></td>
                    <td class="text-end">₹<?= number_format($pl['other_income']['total'], 2) ?></td>
                </tr>

                <!-- Other expenses -->
                <tr class="table-light"><th colspan="3">Other Expenses</th></tr>
                <tr>
                    <td class="ps-4">Purchase-return charge / fine (supplier deduction)</td>
                    <td class="text-end">₹<?= number_format($pl['other_expenses']['purchase_return_charge'] ?? 0, 2) ?></td>
                    <td></td>
                </tr>
                <tr class="fw-bold">
                    <td class="ps-3">Total Other Expenses</td>
                    <td></td>
                    <td class="text-end">₹<?= number_format($pl['other_expenses']['total'] ?? 0, 2) ?></td>
                </tr>

                <!-- Operating expenses -->
                <tr class="table-light"><th colspan="3">Operating Expenses</th></tr>
                <tr>
                    <td class="ps-4">Operating expenses (rent, salary, etc.)</td>
                    <td class="text-end">₹<?= number_format($pl['operating_expenses']['operating_expenses'], 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4">Stock write-offs (damage / expiry / theft)</td>
                    <td class="text-end">₹<?= number_format($pl['operating_expenses']['stock_writeoff'], 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4">Less: stock recovery (count-correction in)</td>
                    <td class="text-end text-success">−₹<?= number_format($pl['operating_expenses']['stock_recovery'], 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4">Salesman commission (cash mode)</td>
                    <td class="text-end">₹<?= number_format($pl['operating_expenses']['commission_cash'], 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4">Salesman commission (goods mode, at cost)</td>
                    <td class="text-end">₹<?= number_format($pl['operating_expenses']['commission_goods'] ?? 0, 2) ?></td>
                    <td></td>
                </tr>
                <tr class="fw-bold">
                    <td class="ps-3">Total Operating Expenses</td>
                    <td></td>
                    <td class="text-end">₹<?= number_format($pl['operating_expenses']['total'], 2) ?></td>
                </tr>

                <!-- Net profit -->
                <tr class="table-<?= $pl['net_profit'] >= 0 ? 'success' : 'danger' ?> fw-bold">
                    <td>Net Profit / (Loss)</td>
                    <td></td>
                    <td class="text-end fs-5"><?= $pl['net_profit'] >= 0 ? '+' : '' ?>₹<?= number_format($pl['net_profit'], 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">
        Tax collected (info only, not in P&amp;L): ₹<?= number_format($pl['revenue']['tax_collected_info'], 2) ?>.
        GST is a pass-through liability tracked on the
        <a href="<?= url('/reports/balance-sheet') ?>">Balance Sheet</a>.
    </div>
</div>
