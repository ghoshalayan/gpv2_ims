<?php /** @var string $title */ ?>
<?php
// Each section: [icon, title, color, one-line summary, list of "how it works" points]
$sections = [
    [
        'bi-speedometer2', 'Dashboard & Alerts', 'primary',
        'Your daily command centre — KPIs, charts and critical alerts at a glance.',
        [
            'Open <strong>Dashboard</strong> from the sidebar. Cards are grouped into sections (Sales, Purchase, Profit/Loss, Customers, Salesmen, Inventory, Cash &amp; Bank).',
            'The <strong>Profit/Loss</strong> section has a period toggle (Month / Quarter / Half-year / Year) based on your financial-year start month.',
            'The <i class="bi bi-bell"></i> <strong>bell</strong> in the top bar shows live critical alerts — low stock, out of stock and customers over their credit limit.',
            'Each dashboard section is a <em>component</em> you can grant or revoke per role/user under Permissions.',
        ],
    ],
    [
        'bi-box-seam', 'Masters — the foundation', 'info',
        'Set these up first; everything else references them.',
        [
            '<strong>Units</strong>: define your base units (PCS, KG, LTR…) and conversions (e.g. <code>1 LTR = 1000 ML</code>). Stock is always held in the base unit; you can buy/sell in any convertible unit.',
            '<strong>Categories, Sub-categories, Brands</strong>: classify products for reporting and filtering.',
            '<strong>Products</strong>: each product has a base unit, optional low-stock threshold, HSN code and tax rate. Stock value uses <strong>moving-average cost</strong>.',
            'Set up <strong>Customers, Suppliers, Salesmen</strong> with opening balances and (for customers) a credit limit.',
        ],
    ],
    [
        'bi-cart-plus', 'Purchases', 'success',
        'Bring stock in — costs and quantities update automatically.',
        [
            'Create a <strong>Purchase Invoice</strong>, add line items (choose the unit you actually bought in — it converts to base stock).',
            'On <strong>finalize</strong>, stock increases and each product\'s <strong>moving-average cost</strong> is recalculated from landed cost.',
            'Record <strong>Supplier Payments</strong> against invoices; the supplier passbook tracks the running balance.',
            'Use <strong>Purchase Returns</strong> to send stock back and reverse the cost/quantity impact.',
        ],
    ],
    [
        'bi-cart-check', 'Sales', 'warning',
        'Send stock out — profit and commission are computed per line.',
        [
            'Create a <strong>Sales Invoice</strong>, pick products and quantities (any convertible unit), assign a salesman per line if needed.',
            'On <strong>finalize</strong>, stock decreases, <strong>COGS</strong> is taken at the current moving-average cost, and salesman <strong>commission</strong> is recorded.',
            'Tax is applied per line, or at invoice level in discount-then-tax mode — GST splits into CGST/SGST or IGST automatically.',
            'Collect with <strong>Customer Payments</strong>; <strong>Sales Returns</strong> reverse stock, COGS and dues.',
        ],
    ],
    [
        'bi-sliders', 'Inventory adjustments & production', 'secondary',
        'Correct stock and build composite items.',
        [
            '<strong>Stock Adjustments</strong>: increase/decrease quantity with a reason — moving-average cost is preserved or recomputed as appropriate.',
            '<strong>Composite products (BOM)</strong>: define a recipe of component products. A <strong>Production Order</strong> consumes components and produces the finished item.',
            'Open a product\'s <strong>history</strong> to see every movement (purchase, sale, adjustment, production) and how the moving average changed at each step.',
        ],
    ],
    [
        'bi-cash-coin', 'Money — payments, passbook & cash/bank', 'success',
        'Every rupee in and out is tracked and reconcilable.',
        [
            'The <strong>Passbook</strong> shows a running ledger per customer/supplier/salesman, including any legacy opening balances.',
            '<strong>Bank Accounts</strong> &amp; <strong>Cashier Drawer</strong> hold balances; <strong>Inter-account Transfers</strong> move money between them.',
            'Record <strong>Operating Expenses</strong>, <strong>Capital Movements</strong> (capital in / drawings / loans) and <strong>Brand Rebates</strong>.',
            '<strong>Advances</strong> and <strong>Commission Settlements</strong> are applied against future invoices/payouts.',
        ],
    ],
    [
        'bi-graph-up', 'Reports & GST', 'primary',
        'Statutory and management reporting from the same data.',
        [
            '<strong>Reports</strong> cover sales, purchase, stock, profit &amp; loss, balance sheet and cash flow.',
            '<strong>GST Register</strong> and <strong>GSTR filing</strong> summaries (B2B/B2C, HSN) are generated from finalized invoices.',
            'Run the <strong>Integrity Check</strong> to confirm ledgers, stock and cash all reconcile.',
            'Most lists export to CSV/Excel for sharing or filing.',
        ],
    ],
    [
        'bi-shield-lock', 'Roles, permissions & access', 'danger',
        'Control exactly who can see and do what.',
        [
            'Create <strong>Roles</strong> and assign each user a role. The <strong>admin</strong> role is a master switch (full access).',
            '<strong>Role Permissions</strong> are a tree: under each menu → page you toggle abilities — view, create, update, delete, export, finalize, etc.',
            '<strong>User Permissions</strong> let you override the role for a single user (grant or revoke specific abilities).',
            'The sidebar and every action are filtered by these permissions — users only see what they\'re allowed to use.',
        ],
    ],
    [
        'bi-gear', 'Settings & reset', 'dark',
        'Business profile and a guarded full reset.',
        [
            '<strong>Settings</strong> holds the business profile, financial-year start month, tax defaults and document numbering.',
            'A <strong>Hard Reset</strong> wipes all transactional data and re-seeds default roles, users, permissions and units.',
            'A hard reset <strong>requires the admin password</strong> to confirm — it cannot be undone, so back up first.',
        ],
    ],
];
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h4 class="mb-0"><i class="bi bi-mortarboard text-primary"></i> How <?= e(env('APP_NAME')) ?> Works</h4>
    <a href="<?= url('/dashboard') ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-speedometer2"></i> Go to Dashboard
    </a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <p class="mb-2 text-muted">
            A quick tour of the system. The usual flow is:
        </p>
        <div class="d-flex flex-wrap align-items-center gap-2 small">
            <span class="badge text-bg-info"><i class="bi bi-1-circle"></i> Set up masters</span>
            <i class="bi bi-arrow-right text-muted"></i>
            <span class="badge text-bg-success"><i class="bi bi-2-circle"></i> Purchase stock</span>
            <i class="bi bi-arrow-right text-muted"></i>
            <span class="badge text-bg-warning"><i class="bi bi-3-circle"></i> Sell stock</span>
            <i class="bi bi-arrow-right text-muted"></i>
            <span class="badge text-bg-secondary"><i class="bi bi-4-circle"></i> Record payments</span>
            <i class="bi bi-arrow-right text-muted"></i>
            <span class="badge text-bg-primary"><i class="bi bi-5-circle"></i> Review reports &amp; dashboard</span>
        </div>
    </div>
</div>

<div class="accordion shadow-sm" id="helpAccordion">
    <?php foreach ($sections as $i => [$icon, $heading, $color, $summary, $points]): ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>" type="button"
                        data-bs-toggle="collapse" data-bs-target="#help<?= $i ?>"
                        aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="help<?= $i ?>">
                    <span class="badge rounded-pill text-bg-<?= e($color) ?> me-2">
                        <i class="bi <?= e($icon) ?>"></i>
                    </span>
                    <span class="fw-semibold"><?= e($heading) ?></span>
                </button>
            </h2>
            <div id="help<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>"
                 data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p class="text-muted mb-2"><?= $summary /* trusted static markup */ ?></p>
                    <ul class="mb-0">
                        <?php foreach ($points as $p): ?>
                            <li class="mb-1"><?= $p /* trusted static markup */ ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle"></i> Can't see a menu item? You may not have permission for it —
    ask an administrator to grant access under <strong>Roles &amp; Permissions</strong>.
</p>
