<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Catalog of permission-controlled resources (sidebar leaves + system pages)
 * and the abilities each one supports. Source of truth for the role-permissions UI.
 *
 * Each resource entry has:
 *   - label:     human display name (shown in the matrix UI)
 *   - abilities: subset of self::ALL_ABILITIES the resource supports
 *   - section:   sidebar section heading ('Core', 'Menus', 'Reports', 'System')
 *   - group:     sidebar group label, or null if rendered as a top-level item
 *
 * Sidebar rendering: a leaf is hidden when Permissions::can($key, 'view') is false.
 * A group is hidden when all its children are hidden. A section is hidden when
 * all of its items are hidden.
 *
 * Admin role: always allowed (master switch in Permissions::can). Rows for
 * admin are not seeded into role_permissions, and the matrix UI hides the admin
 * column. This prevents accidental self-lockout via a bad save.
 */
final class MenuPermissionRegistry
{
    /** Standard ability vocabulary. Resources opt into a subset. */
    public const ALL_ABILITIES = [
        'view',
        'create',
        'update',
        'delete',
        'finalize',
        'cancel',
        'void',
        'refund',
        'print',
        'export',
        'import',
        'execute',
    ];

    /** Friendly column labels in the matrix UI. */
    public const ABILITY_LABELS = [
        'view'     => 'View',
        'create'   => 'Create',
        'update'   => 'Update',
        'delete'   => 'Delete',
        'finalize' => 'Finalize',
        'cancel'   => 'Cancel',
        'void'     => 'Void',
        'refund'   => 'Refund',
        'print'    => 'Print',
        'export'   => 'Export',
        'import'   => 'Import',
        'execute'  => 'Execute',
    ];

    /** @return array<string, array{label:string, abilities:array<int,string>, section:string, group:?string}> */
    public static function resources(): array
    {
        return [
            // Core
            'dashboard' => [
                'label' => 'Dashboard', 'abilities' => ['view'],
                'section' => 'Core', 'group' => null,
            ],
            // Dashboard components — each card is permission-gated so it can be
            // assigned to roles/users. Gating a component hides it on the dashboard
            // AND excludes its KPIs from the /dashboard/data payload.
            'dashboard_alerts' => [
                'label' => 'Dashboard · Critical Alerts', 'abilities' => ['view'],
                'section' => 'Core', 'group' => 'Dashboard Components',
            ],
            'dashboard_sales' => [
                'label' => 'Dashboard · Sales KPIs', 'abilities' => ['view'],
                'section' => 'Core', 'group' => 'Dashboard Components',
            ],
            'dashboard_purchase' => [
                'label' => 'Dashboard · Purchase KPIs', 'abilities' => ['view'],
                'section' => 'Core', 'group' => 'Dashboard Components',
            ],
            'dashboard_profit_loss' => [
                'label' => 'Dashboard · Profit & Loss', 'abilities' => ['view'],
                'section' => 'Core', 'group' => 'Dashboard Components',
            ],
            'dashboard_customers' => [
                'label' => 'Dashboard · Customer KPIs', 'abilities' => ['view'],
                'section' => 'Core', 'group' => 'Dashboard Components',
            ],
            'dashboard_salesmen' => [
                'label' => 'Dashboard · Salesman KPIs', 'abilities' => ['view'],
                'section' => 'Core', 'group' => 'Dashboard Components',
            ],
            'dashboard_inventory' => [
                'label' => 'Dashboard · Inventory KPIs', 'abilities' => ['view'],
                'section' => 'Core', 'group' => 'Dashboard Components',
            ],
            'dashboard_cash_bank' => [
                'label' => 'Dashboard · Cash & Bank', 'abilities' => ['view'],
                'section' => 'Core', 'group' => 'Dashboard Components',
            ],

            // User Management
            'users' => [
                'label' => 'Users', 'abilities' => ['view','create','update','delete'],
                'section' => 'Menus', 'group' => 'User Management',
            ],
            'roles' => [
                'label' => 'Roles', 'abilities' => ['view','create','update','delete'],
                'section' => 'Menus', 'group' => 'User Management',
            ],
            'role_permissions' => [
                'label' => 'Role Permissions', 'abilities' => ['view','update'],
                'section' => 'Menus', 'group' => 'User Management',
            ],
            'user_permissions' => [
                'label' => 'User Permission Overrides', 'abilities' => ['view','update'],
                'section' => 'Menus', 'group' => 'User Management',
            ],

            // Salesman Management
            'salesmen' => [
                'label' => 'Salesmen', 'abilities' => ['view','create','update','delete'],
                'section' => 'Menus', 'group' => 'Salesman Management',
            ],
            'commission_settlements' => [
                'label' => 'Commission Settlements', 'abilities' => ['view','create','void'],
                'section' => 'Menus', 'group' => 'Salesman Management',
            ],

            // Customer Management
            'customers' => [
                'label' => 'Customers', 'abilities' => ['view','create','update','delete','export'],
                'section' => 'Menus', 'group' => 'Customer Management',
            ],
            'customer_advances' => [
                'label' => 'Customer Advances', 'abilities' => ['view','create','refund'],
                'section' => 'Menus', 'group' => 'Customer Management',
            ],
            'customer_payments' => [
                'label' => 'Customer Bulk Payments', 'abilities' => ['view','create'],
                'section' => 'Menus', 'group' => 'Customer Management',
            ],

            // Employee Management
            'employees' => [
                'label' => 'Employees', 'abilities' => ['view','create','update','delete'],
                'section' => 'Menus', 'group' => 'Employee Management',
            ],

            // Supplier Management
            'suppliers' => [
                'label' => 'Suppliers', 'abilities' => ['view','create','update','delete','export'],
                'section' => 'Menus', 'group' => 'Supplier Management',
            ],
            'supplier_advances' => [
                'label' => 'Supplier Advances', 'abilities' => ['view','create','refund'],
                'section' => 'Menus', 'group' => 'Supplier Management',
            ],
            'supplier_payments' => [
                'label' => 'Supplier Bulk Payments', 'abilities' => ['view','create'],
                'section' => 'Menus', 'group' => 'Supplier Management',
            ],

            // Product Management
            'categories' => [
                'label' => 'Categories', 'abilities' => ['view','create','update','delete'],
                'section' => 'Menus', 'group' => 'Product Management',
            ],
            'subcategories' => [
                'label' => 'Sub-Categories', 'abilities' => ['view','create','update','delete'],
                'section' => 'Menus', 'group' => 'Product Management',
            ],
            'brands' => [
                'label' => 'Brands', 'abilities' => ['view','create','update','delete'],
                'section' => 'Menus', 'group' => 'Product Management',
            ],
            'products' => [
                'label' => 'Products', 'abilities' => ['view','create','update','delete','import','export'],
                'section' => 'Menus', 'group' => 'Product Management',
            ],
            'production_orders' => [
                'label' => 'Production Orders', 'abilities' => ['view','create'],
                'section' => 'Menus', 'group' => 'Product Management',
            ],
            'units' => [
                'label' => 'Units', 'abilities' => ['view','create','update','delete'],
                'section' => 'Menus', 'group' => 'Product Management',
            ],
            'stock_adjustments' => [
                'label' => 'Stock Adjustments', 'abilities' => ['view','create','void'],
                'section' => 'Menus', 'group' => 'Product Management',
            ],

            // Purchase Management
            'purchase_invoices' => [
                'label' => 'Purchase Invoices',
                'abilities' => ['view','create','update','delete','finalize','cancel','print','export'],
                'section' => 'Menus', 'group' => 'Purchase Management',
            ],
            'purchase_payments' => [
                'label' => 'Purchase Payments', 'abilities' => ['view','create','update','delete','print'],
                'section' => 'Menus', 'group' => 'Purchase Management',
            ],

            // Purchase Return Management
            'purchase_returns' => [
                'label' => 'Purchase Returns',
                'abilities' => ['view','create','update','delete','finalize','cancel','print'],
                'section' => 'Menus', 'group' => 'Purchase Return Management',
            ],

            // Sales Management
            'sales_invoices' => [
                'label' => 'Sales Invoices',
                'abilities' => ['view','create','update','delete','finalize','cancel','print','export'],
                'section' => 'Menus', 'group' => 'Sales Management',
            ],
            'sales_payments' => [
                'label' => 'Sales Payments', 'abilities' => ['view','create','update','delete','print'],
                'section' => 'Menus', 'group' => 'Sales Management',
            ],

            // Sales Return Management
            'sales_returns' => [
                'label' => 'Sales Returns',
                'abilities' => ['view','create','update','finalize','cancel','print'],
                'section' => 'Menus', 'group' => 'Sales Return Management',
            ],

            // Cash & Bank
            'bank_accounts' => [
                'label' => 'Bank Accounts', 'abilities' => ['view','create','update','delete'],
                'section' => 'Menus', 'group' => 'Cash & Bank',
            ],
            'cashier_drawers' => [
                'label' => 'Cashier Drawers', 'abilities' => ['view','create','update','delete'],
                'section' => 'Menus', 'group' => 'Cash & Bank',
            ],
            'inter_account_transfers' => [
                'label' => 'Inter-account Transfers', 'abilities' => ['view','create'],
                'section' => 'Menus', 'group' => 'Cash & Bank',
            ],
            'capital_movements' => [
                'label' => 'Capital & Drawings', 'abilities' => ['view','create','void'],
                'section' => 'Menus', 'group' => 'Cash & Bank',
            ],
            // Universal passbook (per-entity ledger). No sidebar leaf of its own —
            // reached from customer/supplier/salesman/account pages — but gating it
            // here lets admins control who can open the financial ledger view.
            'passbook' => [
                'label' => 'Passbook (entity ledger)', 'abilities' => ['view'],
                'section' => 'Menus', 'group' => 'Cash & Bank',
            ],

            // Operating Expenses
            'operating_expenses' => [
                'label' => 'Operating Expenses', 'abilities' => ['view','create','update','void','print'],
                'section' => 'Menus', 'group' => 'Operating Expenses',
            ],
            'recurring_expenses' => [
                'label' => 'Recurring Expenses', 'abilities' => ['view','create','update','delete'],
                'section' => 'Menus', 'group' => 'Operating Expenses',
            ],
            'operating_expense_categories' => [
                'label' => 'Expense Categories', 'abilities' => ['view','create','update','delete'],
                'section' => 'Menus', 'group' => 'Operating Expenses',
            ],

            // Brand Rebates
            'brand_rebates' => [
                'label' => 'Brand Rebates', 'abilities' => ['view','create','void'],
                'section' => 'Menus', 'group' => 'Brand Rebates',
            ],

            // Old Records
            'legacy_records' => [
                'label' => 'Legacy Records', 'abilities' => ['view','create','update','delete'],
                'section' => 'Menus', 'group' => 'Old Records Management',
            ],

            // Reports — Ledgers
            'reports_due_list' => [
                // 'update' gates the CR-3 short/long-term tagging actions, which are the
                // only writes on this report.
                'label' => 'Due List', 'abilities' => ['view','export','print','update'],
                'section' => 'Reports', 'group' => 'Ledgers',
            ],
            'reports_cash_flow' => [
                'label' => 'Cash Inflow / Outflow', 'abilities' => ['view','export'],
                'section' => 'Reports', 'group' => 'Ledgers',
            ],
            'reports_gst_sales_register' => [
                'label' => 'GST Sales Register', 'abilities' => ['view','export'],
                'section' => 'Reports', 'group' => 'Ledgers',
            ],
            'reports_gst_purchase_register' => [
                'label' => 'GST Purchase Register', 'abilities' => ['view','export'],
                'section' => 'Reports', 'group' => 'Ledgers',
            ],
            // Reports — Financial Statements
            'reports_balance_sheet' => [
                'label' => 'Balance Sheet', 'abilities' => ['view'],
                'section' => 'Reports', 'group' => 'Financial Statements',
            ],
            'reports_profit_loss' => [
                'label' => 'Profit & Loss', 'abilities' => ['view'],
                'section' => 'Reports', 'group' => 'Financial Statements',
            ],
            // Reports — GST Filing
            'reports_gstr1' => [
                'label' => 'GSTR-1 (JSON)', 'abilities' => ['view','export'],
                'section' => 'Reports', 'group' => 'GST Filing',
            ],
            'reports_gstr3b' => [
                'label' => 'GSTR-3B (JSON)', 'abilities' => ['view','export'],
                'section' => 'Reports', 'group' => 'GST Filing',
            ],

            // System
            'system_settings' => [
                'label' => 'Settings', 'abilities' => ['view','update'],
                'section' => 'System', 'group' => null,
            ],
            'integrity_check' => [
                'label' => 'Integrity Check', 'abilities' => ['view'],
                'section' => 'System', 'group' => null,
            ],
            'activity_log' => [
                'label' => 'Activity Log', 'abilities' => ['view','export'],
                'section' => 'System', 'group' => null,
            ],
            'system_reset' => [
                'label' => 'System Hard Reset', 'abilities' => ['execute'],
                'section' => 'System', 'group' => null,
            ],
        ];
    }

    public static function exists(string $key): bool
    {
        return isset(self::resources()[$key]);
    }

    /** @return array<int, string> */
    public static function abilitiesFor(string $key): array
    {
        return self::resources()[$key]['abilities'] ?? [];
    }

    /**
     * Flat list of every valid permission key in the form 'resource.ability'.
     * Source of truth for whether a posted permission exists — service code
     * uses this set to discard typos / stale keys before writing the DB.
     *
     * @return array<int, string>
     */
    public static function flatPermissions(): array
    {
        $out = [];
        foreach (self::resources() as $key => $r) {
            foreach ($r['abilities'] as $a) {
                $out[] = $key . '.' . $a;
            }
        }
        return $out;
    }

    public static function flatPermissionSet(): array
    {
        return \array_flip(self::flatPermissions());
    }

    /**
     * Tree representation suitable for the permission-editor UI.
     * Layout: [section][group][resource_key] = resource entry. Top-level items
     * (those without a sidebar group) are bucketed under group key '__top__'.
     *
     * @return array<string, array<string, array<string, array{label:string, abilities:array<int,string>, section:string, group:?string}>>>
     */
    public static function tree(): array
    {
        $tree = [];
        foreach (self::resources() as $key => $r) {
            $section = $r['section'];
            $group   = $r['group'] ?? '__top__';
            $tree[$section][$group][$key] = $r;
        }
        return $tree;
    }

    /**
     * Default permission grants per seeded role_key. Used by
     * RolePermissionService::seedDefaults() on first install and after a hard
     * reset. Returns a flat list of permission keys ('resource.ability') per
     * role_key — the seeder writes one role_permissions row per entry.
     *
     * Note: 'admin' is intentionally absent — the master-switch makes any stored
     * row redundant (and a stored "deny" row would be ignored anyway).
     *
     * Roles created at runtime via the Roles UI start with NO permissions; admin
     * grants them via the tree-view editor.
     *
     * @return array<string, array<int, string>> ['subadmin' => ['users.view', ...], ...]
     */
    public static function defaultGrantsByRoleKey(): array
    {
        // These EXPLICIT allow-lists mirror the legacy route tiers 1:1 so enabling
        // route-level enforcement changes nobody's access on day one:
        //   $staff  (all roles)        → granted to BOTH subadmin + cashier
        //   $mgmt   (admin + subadmin) → granted to subadmin only
        //   $admin  (admin only)       → granted to NEITHER (admin = master-switch)
        // The admin can retune any of this afterwards via the Role Permissions tree.

        // subadmin = $staff ∪ $mgmt abilities (everything except admin-only).
        $subadmin = self::keysFromMap([
            'dashboard'                    => ['view'],
            'dashboard_alerts'             => ['view'],
            'dashboard_sales'              => ['view'],
            'dashboard_purchase'           => ['view'],
            'dashboard_profit_loss'        => ['view'],
            'dashboard_customers'          => ['view'],
            'dashboard_salesmen'           => ['view'],
            'dashboard_inventory'          => ['view'],
            'dashboard_cash_bank'          => ['view'],
            'categories'                   => ['view','create','update'],
            'subcategories'                => ['view','create','update'],
            'brands'                       => ['view','create','update'],
            'units'                        => ['view','create','update'],
            'products'                     => ['view','create','update'],
            'production_orders'            => ['view','create'],
            'suppliers'                    => ['view','create','update'],
            'salesmen'                     => ['view','create','update'],
            'customers'                    => ['view','create','update'],
            'employees'                    => ['view','create','update'],
            'purchase_invoices'            => ['view','create','update','finalize','cancel','print'],
            'purchase_returns'             => ['view','create','update','finalize','cancel','print'],
            'purchase_payments'            => ['view','create','update','print'],
            'sales_invoices'               => ['view','create','update','finalize','cancel','print'],
            'sales_returns'                => ['view','create','update','finalize','cancel','print'],
            'sales_payments'               => ['view','create','update','print'],
            'bank_accounts'                => ['view','create','update'],
            'cashier_drawers'              => ['view','create','update'],
            'inter_account_transfers'      => ['view','create'],
            'operating_expense_categories' => ['view','create','update'],
            'operating_expenses'           => ['view','create','update','print'],
            'recurring_expenses'           => ['view','create','update'],
            'capital_movements'            => ['view','create'],
            'passbook'                     => ['view'],
            'commission_settlements'       => ['view','create'],
            'customer_advances'            => ['view','create'],
            'customer_payments'            => ['view','create'],
            'supplier_advances'            => ['view','create'],
            'supplier_payments'            => ['view','create'],
            'brand_rebates'                => ['view','create'],
            'stock_adjustments'            => ['view','create'],
            'legacy_records'               => ['view','create'],
        ]);

        // cashier = $staff abilities only (views, print, sales workflow).
        $cashier = self::keysFromMap([
            'dashboard'                    => ['view'],
            'dashboard_alerts'             => ['view'],
            'dashboard_sales'              => ['view'],
            'dashboard_inventory'          => ['view'],
            'categories'                   => ['view'],
            'subcategories'                => ['view'],
            'brands'                       => ['view'],
            'units'                        => ['view'],
            'products'                     => ['view'],
            'production_orders'            => ['view'],
            'suppliers'                    => ['view'],
            'salesmen'                     => ['view'],
            'customers'                    => ['view'],
            'employees'                    => ['view'],
            'purchase_invoices'            => ['view','print'],
            'purchase_returns'             => ['view','print'],
            'purchase_payments'            => ['view','print'],
            'sales_invoices'               => ['view','create','update','finalize','print'],
            'sales_returns'                => ['view','create','update','finalize','print'],
            'sales_payments'               => ['view','create','update','print'],
            'bank_accounts'                => ['view'],
            'cashier_drawers'              => ['view'],
            'inter_account_transfers'      => ['view'],
            'operating_expense_categories' => ['view'],
            'operating_expenses'           => ['view','print'],
            'recurring_expenses'           => ['view'],
            'capital_movements'            => ['view'],
            'passbook'                     => ['view'],
            'commission_settlements'       => ['view'],
            'customer_advances'            => ['view','create'],
            'customer_payments'            => ['view','create'],
            'supplier_advances'            => ['view','create'],
            'supplier_payments'            => ['view','create'],
            'brand_rebates'                => ['view','create'],
            'stock_adjustments'            => ['view'],
            'legacy_records'               => ['view','create'],
        ]);

        // Defensive: keep only keys the catalogue actually defines (guards typos).
        $valid    = self::flatPermissionSet();
        $subadmin = \array_values(\array_filter($subadmin, fn ($k) => isset($valid[$k])));
        $cashier  = \array_values(\array_filter($cashier,  fn ($k) => isset($valid[$k])));

        return [
            'subadmin' => $subadmin,
            'cashier'  => $cashier,
        ];
    }

    /**
     * Convert ['resource' => ['ability1','ability2'], ...] into a flat list
     * of 'resource.ability' permission keys.
     *
     * @return array<int, string>
     */
    private static function keysFromMap(array $map): array
    {
        $out = [];
        foreach ($map as $resource => $abilities) {
            foreach ($abilities as $ability) {
                $out[] = $resource . '.' . $ability;
            }
        }
        return $out;
    }
}
