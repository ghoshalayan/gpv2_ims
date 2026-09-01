<?php
/** @var array $user */
/** @var array<int,array{key:string,perm:string,method:string,urgent:bool}> $components */
$keys = array_column($components, 'key');

// Section header meta (icon + accent) — used for the section title rendered
// before data arrives. KPI cards inside are built client-side from /dashboard/data.
$meta = [
    'sales'       => ['title' => 'Sales',          'icon' => 'cart-check',       'color' => 'primary'],
    'purchase'    => ['title' => 'Purchase',        'icon' => 'truck',            'color' => 'warning'],
    'profit_loss' => ['title' => 'Profit & Loss',   'icon' => 'cash-stack',       'color' => 'success'],
    'customers'   => ['title' => 'Customers',        'icon' => 'people',           'color' => 'info'],
    'salesmen'    => ['title' => 'Salesmen',         'icon' => 'graph-up-arrow',   'color' => 'secondary'],
    'inventory'   => ['title' => 'Inventory',        'icon' => 'box-seam',         'color' => 'info'],
    'cash_bank'   => ['title' => 'Cash & Bank',      'icon' => 'wallet2',          'color' => 'success'],
];
?>
<style>
    .dash-greeting { background: linear-gradient(100deg,#1a1d20 0%,#2b3035 55%,#0d6efd 200%); color:#fff; }
    .kpi-card { border:0; transition: transform .12s ease, box-shadow .12s ease; }
    .kpi-card:hover { transform: translateY(-2px); box-shadow:0 .5rem 1rem rgba(0,0,0,.1)!important; }
    .kpi-card .card-body { padding:.75rem .9rem; }
    .kpi-icon { width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex:0 0 auto; }
    .kpi-value { font-size:1.25rem;font-weight:700;line-height:1.15;word-break:break-word; }
    .kpi-sub { font-size:.72rem; }
    .kpi-label { font-size:.68rem;text-transform:uppercase;letter-spacing:.3px;color:#6c757d;font-weight:600; }

    .section-head { display:flex;align-items:center;gap:.5rem;margin:1.25rem 0 .6rem; }
    .section-head .sec-icon { width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center; }
    .section-head h2 { font-size:1rem;margin:0;font-weight:700; }
    .section-head .sec-rule { flex:1;height:1px;background:linear-gradient(90deg,#dee2e6,transparent); }

    .dash-component { opacity:0; transform:translateY(8px); animation:dashIn .45s ease forwards; }
    @keyframes dashIn { to { opacity:1; transform:none; } }
    .skel { background:linear-gradient(90deg,#e9ecef 25%,#f4f6f8 37%,#e9ecef 63%); background-size:400% 100%;
            animation:skel 1.2s ease-in-out infinite; border-radius:6px; }
    @keyframes skel { 0%{background-position:100% 0} 100%{background-position:0 0} }
    .skel-line { height:11px;margin:.4rem 0; } .skel-num { height:22px;width:65%;margin:.3rem 0; }

    .alert-strip .alert { border-left:4px solid; }
    .pulse-dot { display:inline-block;width:8px;height:8px;border-radius:50%;background:#dc3545;margin-right:5px;
                 animation:pulse 1.4s infinite; } @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(220,53,69,.5)}70%{box-shadow:0 0 0 6px rgba(220,53,69,0)}}
    .pl-period .btn.active { font-weight:700; }
</style>

<div class="card shadow-sm mb-3 dash-greeting">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
        <div>
            <h1 class="h5 mb-1">Welcome, <?= e($user['name']) ?></h1>
            <div class="small opacity-75">
                <i class="bi bi-calendar3"></i> <?= e(date('l, d M Y')) ?>
                &middot; <span class="badge text-bg-light text-dark"><?= e($user['role_label'] ?? $user['role']) ?></span>
            </div>
        </div>
        <button class="btn btn-sm btn-light" id="dashRefresh"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
</div>

<?php if ($components === []): ?>
    <div class="alert alert-info">No dashboard sections are assigned to your role. Ask an administrator to grant
        <em>Dashboard · …</em> permissions under Role Permissions.</div>
<?php else: ?>

<?php if (in_array('alerts', $keys, true)): ?>
    <div class="dash-component alert-strip mb-2" data-key="alerts" style="animation-delay:0s">
        <div class="component-body">
            <div class="card shadow-sm"><div class="card-body">
                <div class="skel skel-line" style="width:30%"></div>
                <div class="skel skel-line" style="width:80%"></div>
            </div></div>
        </div>
    </div>
<?php endif; ?>

<?php
$idx = 0;
foreach ($components as $c):
    if ($c['key'] === 'alerts') { continue; }
    $m = $meta[$c['key']] ?? ['title' => ucfirst($c['key']), 'icon' => 'grid', 'color' => 'secondary'];
    $idx++;
?>
    <section class="dash-component" data-key="<?= e($c['key']) ?>" style="animation-delay:<?= number_format($idx * 0.05, 2) ?>s">
        <div class="section-head">
            <span class="sec-icon text-bg-<?= e($m['color']) ?>"><i class="bi bi-<?= e($m['icon']) ?>"></i></span>
            <h2><?= e($m['title']) ?></h2>
            <?php if ($c['key'] === 'profit_loss'): ?>
                <div class="btn-group btn-group-sm pl-period ms-2" role="group">
                    <?php foreach (['month'=>'Month','quarter'=>'Quarter','half'=>'Half','year'=>'Year'] as $pk=>$pl): ?>
                        <button type="button" class="btn btn-outline-secondary<?= $pk==='month'?' active':'' ?>" data-pl="<?= $pk ?>"><?= $pl ?></button>
                    <?php endforeach; ?>
                </div>
                <span class="text-muted small ms-2" id="pl_range"></span>
            <?php endif; ?>
            <span class="sec-rule"></span>
        </div>
        <div class="row g-2 section-grid">
            <?php for ($s = 0; $s < 6; $s++): ?>
                <div class="col-6 col-md-4 col-xl-3 col-xxl-2">
                    <div class="card kpi-card shadow-sm h-100"><div class="card-body">
                        <div class="skel skel-line" style="width:50%"></div>
                        <div class="skel skel-num"></div>
                    </div></div>
                </div>
            <?php endfor; ?>
        </div>
        <div class="row g-2 section-extra mt-1"></div>
    </section>
<?php endforeach; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const BASE = '<?= url('/dashboard/data') ?>';
    const inr  = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const inr0 = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', {maximumFractionDigits: 0});
    const num  = (n) => Number(n || 0).toLocaleString('en-IN');
    const esc  = (s) => { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
    let plPeriod = 'month';

    function mom(pct) {
        if (pct === null || pct === undefined) return '';
        const up = pct >= 0;
        return '<span class="badge text-bg-' + (up ? 'success' : 'danger') + '">' + (up ? '▲' : '▼') + ' ' + Math.abs(pct) + '%</span>';
    }

    // One KPI card. tone optional ('text-danger'/'text-success') for the value.
    function kpi(c) {
        const sub = c.sub ? '<div class="kpi-sub text-muted mt-1">' + c.sub + '</div>' : '';
        return '<div class="col-6 col-md-4 col-xl-3 col-xxl-2"><div class="card kpi-card shadow-sm h-100"><div class="card-body">'
            + '<div class="d-flex justify-content-between align-items-start">'
            + '<div class="kpi-label">' + esc(c.label) + '</div>'
            + (c.icon ? '<span class="kpi-icon text-bg-' + (c.color || 'light') + '"><i class="bi bi-' + c.icon + '"></i></span>' : '')
            + '</div>'
            + '<div class="kpi-value ' + (c.tone || '') + '">' + c.value + '</div>'
            + sub + '</div></div></div>';
    }

    // Section card-builders → arrays of kpi() definitions.
    const SECTIONS = {
        sales: (d) => [
            {label:'Today', value:inr(d.today_amount), sub:num(d.today_count)+' invoice(s)', icon:'cart-check', color:'primary'},
            {label:'This Month', value:inr(d.month_amount), sub:mom(d.mom_pct)+' vs last', icon:'calendar-month', color:'primary'},
            {label:'This Month Invoices', value:num(d.month_count), icon:'receipt', color:'secondary'},
            {label:'Avg Invoice', value:inr(d.avg_invoice), icon:'calculator', color:'info'},
            {label:'Last Month', value:inr(d.last_month), icon:'calendar2', color:'secondary'},
            {label:'This FY', value:inr(d.fy_amount), icon:'graph-up', color:'success'},
            {label:'Receivable', value:inr(d.receivable), tone:'text-danger', icon:'hourglass-split', color:'danger'},
            {label:'Unpaid (mo)', value:num(d.month_unpaid), icon:'exclamation-circle', color:'warning'},
            {label:'Returns (mo)', value:inr(d.returns_month), icon:'arrow-return-left', color:'warning'},
            d.top_customer ? {label:'Top Customer (mo)', value:inr0(d.top_customer.amount), sub:esc(d.top_customer.name), icon:'star', color:'primary'} : null,
        ],
        purchase: (d) => [
            {label:'This Month', value:inr(d.month_amount), sub:mom(d.mom_pct)+' vs last', icon:'truck', color:'warning'},
            {label:'This Month Bills', value:num(d.month_count), icon:'receipt', color:'secondary'},
            {label:'Avg Bill', value:inr(d.avg_bill), icon:'calculator', color:'info'},
            {label:'Last Month', value:inr(d.last_month), icon:'calendar2', color:'secondary'},
            {label:'This FY', value:inr(d.fy_amount), icon:'graph-up', color:'success'},
            {label:'Payable', value:inr(d.payable), tone:'text-danger', icon:'hourglass-split', color:'danger'},
            {label:'Unpaid Bills', value:num(d.month_unpaid), icon:'exclamation-circle', color:'warning'},
            {label:'Returns (mo)', value:inr(d.returns_month), icon:'arrow-return-right', color:'warning'},
            d.top_supplier ? {label:'Top Supplier (mo)', value:inr0(d.top_supplier.amount), sub:esc(d.top_supplier.name), icon:'star', color:'warning'} : null,
        ],
        profit_loss: (d) => {
            const cls = Number(d.net_profit||0) >= 0 ? 'text-success' : 'text-danger';
            return [
                {label:'Revenue', value:inr(d.revenue), icon:'cash', color:'success'},
                {label:'COGS', value:inr(d.cogs), tone:'text-muted', icon:'box-seam', color:'secondary'},
                {label:'Gross Profit', value:inr(d.gross_profit), icon:'graph-up', color:'success'},
                {label:'Gross Margin', value:(d.gross_margin_pct||0)+'%', icon:'percent', color:'info'},
                {label:'Other Income', value:inr(d.other_income), icon:'gift', color:'info'},
                {label:'Operating Exp.', value:inr(d.opex), tone:'text-danger', icon:'receipt-cutoff', color:'warning'},
                {label:'Net Profit', value:inr(d.net_profit), tone:cls, icon:'cash-stack', color:(cls==='text-success'?'success':'danger')},
                {label:'Net Margin', value:(d.net_margin_pct||0)+'%', tone:cls, icon:'percent', color:'info'},
            ];
        },
        customers: (d) => [
            {label:'Active', value:num(d.total_active), icon:'people', color:'info'},
            {label:'New This Month', value:num(d.new_month), icon:'person-plus', color:'success'},
            {label:'Inactive', value:num(d.total_inactive), icon:'person-dash', color:'secondary'},
            {label:'With Dues', value:num(d.with_dues), icon:'hourglass-split', color:'warning'},
            {label:'Over Credit Limit', value:num(d.over_limit), tone:'text-danger', icon:'credit-card', color:'danger'},
            {label:'Total Receivable', value:inr(d.receivable), tone:'text-danger', icon:'cash-coin', color:'danger'},
            {label:'Avg Outstanding', value:inr(d.avg_outstanding), icon:'calculator', color:'info'},
            d.top_debtor ? {label:'Top Debtor', value:inr0(d.top_debtor.amount), sub:esc(d.top_debtor.name), icon:'exclamation-triangle', color:'danger'} : null,
        ],
        salesmen: (d) => [
            {label:'Active', value:num(d.total_active), icon:'people', color:'secondary'},
            {label:'Sold This Month', value:num(d.with_sales_month), icon:'person-check', color:'success'},
            {label:'Commission Outstanding', value:inr(d.commission_outstanding), tone:'text-danger', icon:'hourglass-split', color:'danger'},
            {label:'Commission Accrued (FY)', value:inr(d.commission_accrued_fy), icon:'graph-up', color:'info'},
            {label:'Commission Settled (FY)', value:inr(d.commission_settled_fy), icon:'check2-circle', color:'success'},
            d.top ? {label:'Top Salesman (mo)', value:inr0(d.top.amount), sub:esc(d.top.name), icon:'star', color:'primary'} : null,
        ],
        inventory: (d) => [
            {label:'Stock Value', value:inr(d.stock_value), icon:'box-seam', color:'info'},
            {label:'Active SKUs', value:num(d.sku_count), icon:'upc-scan', color:'secondary'},
            {label:'Low Stock', value:num(d.low_stock), tone:(d.low_stock>0?'text-warning':''), icon:'exclamation-circle', color:'warning'},
            {label:'Out of Stock', value:num(d.out_of_stock), tone:(d.out_of_stock>0?'text-danger':''), icon:'x-octagon', color:'danger'},
            {label:'Composite Items', value:num(d.composite_count), icon:'diagram-3', color:'info'},
            {label:'Adjustments (mo)', value:num(d.adj_month), icon:'clipboard-x', color:'secondary'},
            d.top_value ? {label:'Highest-Value Item', value:inr0(d.top_value.amount), sub:esc(d.top_value.name), icon:'gem', color:'info'} : null,
        ],
        cash_bank: (d) => [
            {label:'Total Liquidity', value:inr(d.total), icon:'wallet2', color:'success'},
            {label:'Bank Balance', value:inr(d.bank), icon:'bank', color:'primary'},
            {label:'Cash in Drawers', value:inr(d.cash), icon:'cash-stack', color:'success'},
            {label:'Bank Accounts', value:num(d.bank_accounts), icon:'bank2', color:'secondary'},
            {label:'Drawers', value:num(d.drawers), icon:'cash', color:'secondary'},
            {label:'Inflow (mo)', value:inr(d.inflow_month), tone:'text-success', icon:'arrow-down-circle', color:'success'},
            {label:'Outflow (mo)', value:inr(d.outflow_month), tone:'text-danger', icon:'arrow-up-circle', color:'danger'},
        ],
    };

    // ---- charts + tables ----
    const PALETTE = ['#0d6efd','#198754','#ffc107','#dc3545','#0dcaf0','#6610f2','#fd7e14','#20c997'];
    const charts = {};                     // live Chart instances, destroyed on reload
    const hasChart = typeof Chart !== 'undefined';

    function chartCard(title, canvasId, cls) {
        return '<div class="' + (cls || 'col-12 col-lg-6') + '"><div class="card shadow-sm h-100"><div class="card-body">'
            + '<div class="kpi-label mb-2">' + esc(title) + '</div>'
            + (hasChart ? '<div style="position:relative;height:240px"><canvas id="' + canvasId + '"></canvas></div>'
                        : '<div class="text-muted small">Chart library unavailable.</div>')
            + '</div></div></div>';
    }
    function tableCard(title, headers, rows, cls) {
        const head = headers.map(h => '<th class="' + (h.end ? 'text-end' : '') + ' kpi-sub">' + esc(h.label) + '</th>').join('');
        let body = rows.map(r => '<tr>' + r.map((cell, i) =>
            '<td class="' + (headers[i] && headers[i].end ? 'text-end' : '') + (i===0?' text-truncate" style="max-width:180px':'') + '">'
            + (cell.raw ? cell.raw : esc(cell)) + '</td>').join('') + '</tr>').join('');
        if (!body) body = '<tr><td colspan="' + headers.length + '" class="text-muted small">No data.</td></tr>';
        return '<div class="' + (cls || 'col-12 col-lg-6') + '"><div class="card shadow-sm h-100"><div class="card-body">'
            + '<div class="kpi-label mb-2">' + esc(title) + '</div>'
            + '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr>' + head
            + '</tr></thead><tbody>' + body + '</tbody></table></div></div></div></div>';
    }
    function mkChart(id, config) {
        if (!hasChart) return;
        const el = document.getElementById(id);
        if (!el) return;
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(el, config);
    }
    const moneyTick = { ticks: { callback: (v) => '₹' + Number(v).toLocaleString('en-IN', {notation:'compact', maximumFractionDigits:1}) } };

    // Each returns {html, draw()} — draw() runs after html is in the DOM (charts need a live canvas).
    const EXTRAS = {
        sales(d) {
            const t = d.trend || {labels:[],sales:[],purchase:[]};
            const ps = d.payment_split || {collected:0, outstanding:0};
            const tc = d.top_customers || [];
            const html =
                chartCard('Sales vs Purchase — last 12 months', 'ch_sales_trend', 'col-12 col-lg-8')
                + chartCard('Collected vs Outstanding', 'ch_pay_split', 'col-12 col-lg-4')
                + tableCard('Top 5 Customers (FY)', [{label:'Customer'},{label:'Sales',end:true},{label:'Outstanding',end:true}],
                    tc.map(x => [x.name, {raw:inr0(x.sales)}, {raw:'<span class="text-danger">'+inr0(x.due)+'</span>'}]), 'col-12 col-lg-6');
            return { html, draw() {
                mkChart('ch_sales_trend', { type:'bar', data:{ labels:t.labels, datasets:[
                    {label:'Sales', data:t.sales, backgroundColor:'#0d6efd'},
                    {label:'Purchase', data:t.purchase, backgroundColor:'#fd7e14'} ]},
                    options:{ maintainAspectRatio:false, scales:{y:moneyTick}, plugins:{legend:{position:'bottom'}} } });
                mkChart('ch_pay_split', { type:'doughnut', data:{ labels:['Collected','Outstanding'],
                    datasets:[{ data:[ps.collected, ps.outstanding], backgroundColor:['#198754','#dc3545'] }] },
                    options:{ maintainAspectRatio:false, plugins:{legend:{position:'bottom'}} } });
            }};
        },
        purchase(d) {
            const ts = d.top_suppliers || [];
            return { html: tableCard('Top 5 Suppliers (FY)',
                [{label:'Supplier'},{label:'Purchases',end:true},{label:'Payable',end:true}],
                ts.map(x => [x.name, {raw:inr0(x.purchases)}, {raw:'<span class="text-danger">'+inr0(x.due)+'</span>'}]), 'col-12 col-lg-6'),
                draw(){} };
        },
        profit_loss(d) {
            return { html: chartCard('P&L Composition · ' + esc(d.period_label||''), 'ch_pl', 'col-12 col-lg-6'),
                draw() {
                    mkChart('ch_pl', { type:'bar', data:{ labels:['Revenue','COGS','Gross','Opex','Net'],
                        datasets:[{ data:[d.revenue, d.cogs, d.gross_profit, d.opex, d.net_profit],
                            backgroundColor:['#198754','#6c757d','#0d6efd','#ffc107', (Number(d.net_profit)>=0?'#20c997':'#dc3545')] }] },
                        options:{ maintainAspectRatio:false, scales:{y:moneyTick}, plugins:{legend:{display:false}} } });
                }};
        },
        customers(d) {
            const td = d.top_debtors || [];
            return { html: tableCard('Top 5 Debtors', [{label:'Customer'},{label:'Outstanding',end:true}],
                td.map(x => [x.name, {raw:'<span class="text-danger">'+inr0(x.due)+'</span>'}]), 'col-12 col-lg-6'),
                draw(){} };
        },
        salesmen(d) {
            const tl = d.top_list || [];
            const html =
                chartCard('Top Salesmen — this month', 'ch_salesmen', 'col-12 col-lg-6')
                + tableCard('Top 5 Salesmen (this month)', [{label:'Salesman'},{label:'Sales',end:true},{label:'Commission',end:true}],
                    tl.map(x => [x.name, {raw:inr0(x.sales)}, {raw:inr0(x.commission)}]), 'col-12 col-lg-6');
            return { html, draw() {
                mkChart('ch_salesmen', { type:'bar', data:{ labels:tl.map(x=>x.name),
                    datasets:[{ label:'Sales', data:tl.map(x=>x.sales), backgroundColor:'#6610f2' }] },
                    options:{ indexAxis:'y', maintainAspectRatio:false, scales:{x:moneyTick}, plugins:{legend:{display:false}} } });
            }};
        },
        inventory(d) {
            const bb = d.by_brand || [];
            const tp = d.top_products || [];
            const html =
                chartCard('Stock Value by Brand', 'ch_brand', 'col-12 col-lg-4')
                + tableCard('Top 5 Products by Value', [{label:'Product'},{label:'On hand',end:true},{label:'Value',end:true}],
                    tp.map(x => [x.name, {raw:num(x.qty)+' '+esc(x.unit)}, {raw:inr0(x.value)}]), 'col-12 col-lg-8');
            return { html, draw() {
                mkChart('ch_brand', { type:'doughnut', data:{ labels:bb.map(x=>x.name),
                    datasets:[{ data:bb.map(x=>x.value), backgroundColor:PALETTE }] },
                    options:{ maintainAspectRatio:false, plugins:{legend:{position:'right'}} } });
            }};
        },
    };

    function renderAlerts(d) {
        if (!d || d.total === 0) {
            return '<div class="card shadow-sm border-success"><div class="card-body py-2 d-flex align-items-center">'
                + '<i class="bi bi-shield-check text-success fs-5 me-2"></i><strong>All clear</strong>'
                + '<span class="text-muted ms-2 small">No low-stock or credit-limit alerts.</span></div></div>';
        }
        let h = '';
        if (d.low_stock_count) {
            h += '<a href="<?= url('/products') ?>" class="alert alert-warning py-2 mb-2 d-block text-decoration-none">'
                + '<span class="pulse-dot"></span><strong>' + d.low_stock_count + '</strong> low-stock item(s)'
                + (d.out_of_stock ? ', <strong>' + d.out_of_stock + '</strong> out of stock' : '') + ' — '
                + d.low_stock.slice(0,5).map(x => esc(x.name)+' ('+num(x.quantity_on_hand)+' '+esc(x.unit)+')').join(', ')
                + (d.low_stock_count > 5 ? '…' : '') + '</a>';
        }
        if (d.credit_breach_count) {
            h += '<a href="<?= url('/customers') ?>" class="alert alert-danger py-2 mb-0 d-block text-decoration-none">'
                + '<span class="pulse-dot"></span><strong>' + d.credit_breach_count + '</strong> customer(s) over credit limit — '
                + d.credit_breach.slice(0,5).map(x => esc(x.name)+' ('+inr0(x.outstanding)+'/'+inr0(x.credit_limit)+')').join(', ')
                + (d.credit_breach_count > 5 ? '…' : '') + '</a>';
        }
        return h;
    }

    function render(payload) {
        const data = (payload && payload.data) || {};
        document.querySelectorAll('.dash-component').forEach(function (el) {
            const key = el.dataset.key;
            if (key === 'alerts') {
                const body = el.querySelector('.component-body');
                if (body) body.innerHTML = renderAlerts(data.alerts);
                return;
            }
            const grid = el.querySelector('.section-grid');
            if (!grid || !SECTIONS[key] || !data[key]) return;
            const cards = SECTIONS[key](data[key]).filter(Boolean);
            grid.innerHTML = cards.map(kpi).join('');

            // charts + tables for this section
            const extraEl = el.querySelector('.section-extra');
            if (extraEl && EXTRAS[key]) {
                const ex = EXTRAS[key](data[key]);
                extraEl.innerHTML = ex.html || '';
                if (typeof ex.draw === 'function') ex.draw();
            }
        });
        // P&L range label
        if (data.profit_loss) {
            const r = document.getElementById('pl_range');
            if (r) r.textContent = data.profit_loss.from + ' → ' + data.profit_loss.to;
        }
        document.querySelectorAll('.pl-period [data-pl]').forEach(function (b) {
            b.classList.toggle('active', b.dataset.pl === plPeriod);
            b.onclick = function () { plPeriod = b.dataset.pl; load(); };
        });
    }

    function load() {
        fetch(BASE + '?pl_period=' + encodeURIComponent(plPeriod), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(r => r.json()).then(render)
            .catch(() => document.querySelectorAll('.section-grid,.component-body').forEach(b => {
                b.innerHTML = '<div class="text-danger small p-2"><i class="bi bi-exclamation-triangle"></i> Failed to load.</div>';
            }));
    }

    document.addEventListener('DOMContentLoaded', load);
    const rb = document.getElementById('dashRefresh');
    if (rb) rb.addEventListener('click', load);
})();
</script>
