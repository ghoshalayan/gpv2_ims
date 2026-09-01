<?php
/**
 * Sidebar nav. Each "Management" group is a Bootstrap collapse.
 * Auto-expands when any of its children's path matches the current request.
 *
 * Items with `path` => link is live. Without `path` => placeholder (module not built yet).
 */

use App\Support\Permissions;

$nav = [
    [
        'section' => 'Core',
        'items' => [
            ['label' => 'Dashboard', 'icon' => 'speedometer2', 'path' => '/dashboard', 'perm' => 'dashboard'],
        ],
    ],
    [
        'section' => 'Menus',
        'items' => [
            [
                'group' => 'User Management', 'icon' => 'person-check', 'id' => 'g_users',
                'children' => [
                    ['label' => 'Users',             'icon' => 'people',       'path' => '/users',             'perm' => 'users'],
                    ['label' => 'Roles',             'icon' => 'tags',         'path' => '/roles',             'perm' => 'roles'],
                    ['label' => 'Role Permissions',  'icon' => 'shield-lock',  'path' => '/role-permissions',  'perm' => 'role_permissions'],
                ],
            ],
            [
                'group' => 'Salesman Management', 'icon' => 'graph-up', 'id' => 'g_salesmen',
                'children' => [
                    ['label' => 'Salesmen',                'icon' => 'people',      'path' => '/salesmen',                       'perm' => 'salesmen'],
                    ['label' => 'Commission Settlements',  'icon' => 'cash-coin',   'path' => '/commission-settlements',         'perm' => 'commission_settlements'],
                    ['label' => 'New Settlement',          'icon' => 'plus-circle', 'path' => '/commission-settlements/create',  'perm' => 'commission_settlements', 'perm_ability' => 'create'],
                ],
            ],
            [
                'group' => 'Customer Management', 'icon' => 'person-badge', 'id' => 'g_customers',
                'children' => [
                    ['label' => 'Customers',         'icon' => 'people',      'path' => '/customers',                'perm' => 'customers'],
                    ['label' => 'Customer Advances', 'icon' => 'piggy-bank',  'path' => '/customer-advances',        'perm' => 'customer_advances'],
                    ['label' => 'New Advance',       'icon' => 'plus-circle', 'path' => '/customer-advances/create', 'perm' => 'customer_advances', 'perm_ability' => 'create'],
                    ['label' => 'Bulk Payment',      'icon' => 'collection',  'path' => '/customer-payments/create', 'perm' => 'customer_payments', 'perm_ability' => 'create'],
                ],
            ],
            [
                'group' => 'Employee Management', 'icon' => 'person-workspace', 'id' => 'g_employees',
                'children' => [
                    ['label' => 'Employees', 'icon' => 'people', 'path' => '/employees', 'perm' => 'employees'],
                ],
            ],
            [
                'group' => 'Supplier Management', 'icon' => 'truck', 'id' => 'g_suppliers',
                'children' => [
                    ['label' => 'Suppliers',         'icon' => 'people',      'path' => '/suppliers',                'perm' => 'suppliers'],
                    ['label' => 'Supplier Advances', 'icon' => 'piggy-bank',  'path' => '/supplier-advances',        'perm' => 'supplier_advances'],
                    ['label' => 'New Advance',       'icon' => 'plus-circle', 'path' => '/supplier-advances/create', 'perm' => 'supplier_advances', 'perm_ability' => 'create'],
                    ['label' => 'Bulk Payment',      'icon' => 'collection',  'path' => '/supplier-payments/create', 'perm' => 'supplier_payments', 'perm_ability' => 'create'],
                ],
            ],
            [
                'group' => 'Product Management', 'icon' => 'clipboard-check', 'id' => 'g_products',
                'children' => [
                    ['label' => 'Categories',         'icon' => 'grid',               'path' => '/categories',              'perm' => 'categories'],
                    ['label' => 'Sub-Categories',     'icon' => 'collection',         'path' => '/subcategories',           'perm' => 'subcategories'],
                    ['label' => 'Brands',             'icon' => 'bookmark-star',      'path' => '/brands',                  'perm' => 'brands'],
                    ['label' => 'Products',           'icon' => 'tag',                'path' => '/products',                'perm' => 'products'],
                    ['label' => 'Production Orders',  'icon' => 'gear-wide-connected','path' => '/production-orders',       'perm' => 'production_orders'],
                    ['label' => 'Units',              'icon' => 'rulers',             'path' => '/units',                   'perm' => 'units'],
                    ['label' => 'Stock Adjustments',  'icon' => 'clipboard-x',        'path' => '/stock-adjustments',       'perm' => 'stock_adjustments'],
                    ['label' => 'New Adjustment',     'icon' => 'plus-circle',        'path' => '/stock-adjustments/create','perm' => 'stock_adjustments', 'perm_ability' => 'create'],
                ],
            ],
            [
                'group' => 'Purchase Management', 'icon' => 'briefcase-fill', 'id' => 'g_purchase',
                'children' => [
                    ['label' => 'All Purchase Orders',   'icon' => 'receipt',   'path' => '/purchase-invoices',         'perm' => 'purchase_invoices'],
                    ['label' => 'Create Purchase Order', 'icon' => 'cart4',     'path' => '/purchase-invoices/create',  'perm' => 'purchase_invoices', 'perm_ability' => 'create'],
                    ['label' => 'Purchase Payments',     'icon' => 'cash-coin', 'path' => '/purchase-payments',         'perm' => 'purchase_payments'],
                ],
            ],
            [
                'group' => 'Purchase Return Management', 'icon' => 'arrow-return-left', 'id' => 'g_preturn',
                'children' => [
                    ['label' => 'All Purchase Returns',   'icon' => 'receipt', 'path' => '/purchase-returns',        'perm' => 'purchase_returns'],
                    ['label' => 'Create Purchase Return', 'icon' => 'cart4',   'path' => '/purchase-returns/create', 'perm' => 'purchase_returns', 'perm_ability' => 'create'],
                ],
            ],
            [
                'group' => 'Sales Management', 'icon' => 'bag', 'id' => 'g_sales',
                'children' => [
                    ['label' => 'All Sales Orders',   'icon' => 'receipt',   'path' => '/sales-invoices',        'perm' => 'sales_invoices'],
                    ['label' => 'Create Sales Order', 'icon' => 'cart4',     'path' => '/sales-invoices/create', 'perm' => 'sales_invoices', 'perm_ability' => 'create'],
                    ['label' => 'Sales Payments',     'icon' => 'cash-coin', 'path' => '/sales-payments',        'perm' => 'sales_payments'],
                ],
            ],
            [
                'group' => 'Sales Return Management', 'icon' => 'arrow-return-right', 'id' => 'g_sreturn',
                'children' => [
                    ['label' => 'All Sales Returns',   'icon' => 'receipt', 'path' => '/sales-returns',        'perm' => 'sales_returns'],
                    ['label' => 'Create Sales Return', 'icon' => 'cart4',   'path' => '/sales-returns/create', 'perm' => 'sales_returns', 'perm_ability' => 'create'],
                ],
            ],
            [
                'group' => 'Cash &amp; Bank', 'icon' => 'wallet2', 'id' => 'g_cashbank',
                'children' => [
                    ['label' => 'Cashier Drawers',     'icon' => 'cash-stack',       'path' => '/cashier-drawers',          'perm' => 'cashier_drawers'],
                    ['label' => 'Bank Accounts',       'icon' => 'bank',             'path' => '/bank-accounts',            'perm' => 'bank_accounts'],
                    ['label' => 'Transfers',           'icon' => 'arrow-left-right', 'path' => '/inter-account-transfers',  'perm' => 'inter_account_transfers'],
                    ['label' => 'Capital &amp; Drawings', 'icon' => 'piggy-bank',    'path' => '/capital-movements',        'perm' => 'capital_movements'],
                ],
            ],
            [
                'group' => 'Operating Expenses', 'icon' => 'receipt-cutoff', 'id' => 'g_expenses',
                'children' => [
                    ['label' => 'All Expenses',         'icon' => 'list-ul',     'path' => '/operating-expenses',           'perm' => 'operating_expenses'],
                    ['label' => 'New Expense',          'icon' => 'plus-circle', 'path' => '/operating-expenses/create',    'perm' => 'operating_expenses', 'perm_ability' => 'create'],
                    ['label' => 'Recurring Expenses',   'icon' => 'arrow-repeat','path' => '/recurring-expenses',           'perm' => 'recurring_expenses'],
                    ['label' => 'Expense Categories',   'icon' => 'tags',        'path' => '/operating-expense-categories', 'perm' => 'operating_expense_categories'],
                ],
            ],
            [
                'group' => 'Brand Rebates', 'icon' => 'gift', 'id' => 'g_rebates',
                'children' => [
                    ['label' => 'All Rebates',   'icon' => 'list-ul',     'path' => '/brand-rebates',         'perm' => 'brand_rebates'],
                    ['label' => 'Record Rebate', 'icon' => 'plus-circle', 'path' => '/brand-rebates/create',  'perm' => 'brand_rebates', 'perm_ability' => 'create'],
                ],
            ],
            [
                'group' => 'Old Records', 'icon' => 'archive', 'id' => 'g_old',
                'children' => [
                    ['label' => 'Customers w/ Old Bills', 'icon' => 'person',    'path' => '/old-records?kind=customer', 'perm' => 'legacy_records'],
                    ['label' => 'Suppliers w/ Old Bills', 'icon' => 'truck',     'path' => '/old-records?kind=supplier', 'perm' => 'legacy_records'],
                    ['label' => 'Salesmen w/ Old Comm.',  'icon' => 'briefcase', 'path' => '/old-records?kind=salesman', 'perm' => 'legacy_records'],
                ],
            ],
        ],
    ],
    [
        'section' => 'Reports',
        'items' => [
            [
                'group' => 'Ledgers', 'icon' => 'journal-text', 'id' => 'g_ledgers',
                'children' => [
                    ['label' => 'Due List',                'icon' => 'cash-stack',              'path' => '/reports/due-list',            'perm' => 'reports_due_list'],
                    ['label' => 'Cash Inflow / Outflow',   'icon' => 'arrow-left-right',        'path' => '/reports/cash-flow',           'perm' => 'reports_cash_flow'],
                    ['label' => 'GST Sales Register',      'icon' => 'file-earmark-spreadsheet','path' => '/reports/gst-sales-register',  'perm' => 'reports_gst_sales_register'],
                    ['label' => 'GST Purchase Register',   'icon' => 'file-earmark-spreadsheet','path' => '/reports/gst-purchase-register','perm' => 'reports_gst_purchase_register'],
                ],
            ],
            [
                'group' => 'Financial Statements', 'icon' => 'file-spreadsheet', 'id' => 'g_finstmt',
                'children' => [
                    ['label' => 'Balance Sheet',         'icon' => 'clipboard-data', 'path' => '/reports/balance-sheet', 'perm' => 'reports_balance_sheet'],
                    ['label' => 'Profit & Loss',         'icon' => 'graph-up',       'path' => '/reports/profit-loss',   'perm' => 'reports_profit_loss'],
                ],
            ],
            [
                'group' => 'GST Filing', 'icon' => 'file-earmark-code', 'id' => 'g_gstfile',
                'children' => [
                    ['label' => 'GSTR-1 (JSON)',  'icon' => 'filetype-json', 'path' => '/reports/gstr1/json',  'perm' => 'reports_gstr1'],
                    ['label' => 'GSTR-3B (JSON)', 'icon' => 'filetype-json', 'path' => '/reports/gstr3b/json', 'perm' => 'reports_gstr3b'],
                ],
            ],
        ],
    ],
    [
        'section' => 'System',
        'items' => [
            ['label' => 'Settings',         'icon' => 'gear',          'path' => '/settings',                 'perm' => 'system_settings'],
            ['label' => 'Integrity Check',  'icon' => 'shield-check',  'path' => '/system/integrity-check',   'perm' => 'integrity_check'],
            ['label' => 'Activity Log',     'icon' => 'clock-history', 'path' => '/activity-log',             'perm' => 'activity_log'],
        ],
    ],
];

// Visibility filter — drop hidden leaves, then groups with no leaves, then sections with no items.
$leafVisible = function (array $child): bool {
    if (empty($child['path'])) { return true; } // placeholders always visible
    if (empty($child['perm'])) { return true; } // unmapped leaves stay visible (defensive default)
    $ability = $child['perm_ability'] ?? 'view';
    return Permissions::can((string) $child['perm'], (string) $ability);
};
foreach ($nav as $sIdx => $section) {
    foreach ($section['items'] as $iIdx => $item) {
        if (isset($item['group'])) {
            $kept = [];
            foreach ($item['children'] as $child) {
                if ($leafVisible($child)) { $kept[] = $child; }
            }
            if ($kept === []) {
                unset($nav[$sIdx]['items'][$iIdx]);
            } else {
                $nav[$sIdx]['items'][$iIdx]['children'] = $kept;
            }
        } else {
            if (!$leafVisible($item)) {
                unset($nav[$sIdx]['items'][$iIdx]);
            }
        }
    }
    $nav[$sIdx]['items'] = \array_values($nav[$sIdx]['items']);
    if ($nav[$sIdx]['items'] === []) {
        unset($nav[$sIdx]);
    }
}
$nav = \array_values($nav);

$groupIsActive = function (array $group): bool {
    foreach ($group['children'] as $child) {
        if (!empty($child['path']) && is_active_path($child['path'])) {
            return true;
        }
    }
    return false;
};
?>

<ul class="nav flex-column pb-4">
    <?php foreach ($nav as $section): ?>
        <li class="sidebar-section"><?= $section['section'] ?></li>

        <?php foreach ($section['items'] as $item): ?>
            <?php if (isset($item['group'])): ?>
                <?php $isActive = $groupIsActive($item); ?>
                <li>
                    <a class="nav-link sidebar-link d-flex align-items-center <?= $isActive ? 'active' : '' ?>"
                       data-bs-toggle="collapse" href="#<?= e($item['id']) ?>"
                       aria-expanded="<?= $isActive ? 'true' : 'false' ?>">
                        <span class="me-2"><i class="bi bi-<?= e($item['icon']) ?>"></i></span>
                        <span><?= $item['group'] ?></span>
                        <span class="ms-auto right-icon"><i class="bi bi-chevron-down small"></i></span>
                    </a>
                    <div class="collapse <?= $isActive ? 'show' : '' ?>" id="<?= e($item['id']) ?>">
                        <ul class="nav flex-column">
                            <?php foreach ($item['children'] as $child): ?>
                                <?php if (!empty($child['path'])): ?>
                                    <li>
                                        <a href="<?= url($child['path']) ?>"
                                           class="nav-link ps-4 <?= is_active_path($child['path']) ? 'active' : '' ?>">
                                            <span class="me-2"><i class="bi bi-<?= e($child['icon']) ?>"></i></span>
                                            <?= e($child['label']) ?>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li>
                                        <span class="placeholder-item d-block">
                                            <i class="bi bi-<?= e($child['icon']) ?> me-2"></i><?= e($child['label']) ?>
                                            <span class="badge text-bg-secondary ms-1" style="font-size:.6rem">soon</span>
                                        </span>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
            <?php elseif (!empty($item['path'])): ?>
                <li>
                    <a href="<?= url($item['path']) ?>"
                       class="nav-link <?= is_active_path($item['path']) ? 'active' : '' ?>">
                        <span class="me-2"><i class="bi bi-<?= e($item['icon']) ?>"></i></span>
                        <?= e($item['label']) ?>
                    </a>
                </li>
            <?php else: ?>
                <li>
                    <span class="nav-link text-muted fst-italic" style="font-size:.85rem">
                        <span class="me-2"><i class="bi bi-<?= e($item['icon']) ?>"></i></span>
                        <?= e($item['label']) ?>
                        <span class="badge text-bg-secondary ms-1" style="font-size:.6rem">soon</span>
                    </span>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li><hr class="divider"></li>
    <?php endforeach; ?>
</ul>
