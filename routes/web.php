<?php
declare(strict_types=1);

/** @var \App\Router $router */

use App\Controllers\ActivityLogController;
use App\Controllers\AuthController;
use App\Controllers\BankAccountController;
use App\Controllers\BrandController;
use App\Controllers\BrandRebateController;
use App\Controllers\CashierDrawerController;
use App\Controllers\CategoryController;
use App\Controllers\InterAccountTransferController;
use App\Controllers\LegacyRecordController;
use App\Controllers\CapitalMovementController;
use App\Controllers\CommissionSettlementController;
use App\Controllers\CustomerAdvanceController;
use App\Controllers\CustomerPaymentController;
use App\Controllers\IntegrityCheckController;
use App\Controllers\OperatingExpenseCategoryController;
use App\Controllers\OperatingExpenseController;
use App\Controllers\PassbookController;
use App\Controllers\RecurringExpenseController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\DueTermController;
use App\Controllers\EmployeeController;
use App\Controllers\HelpController;
use App\Controllers\HomeController;
use App\Controllers\ProductController;
use App\Controllers\ProductionOrderController;
use App\Controllers\RateChangeController;
use App\Controllers\ProductRecipeController;
use App\Controllers\ProductUnitController;
use App\Controllers\PurchaseInvoiceController;
use App\Controllers\PurchasePaymentController;
use App\Controllers\PurchaseReturnController;
use App\Controllers\ReportsController;
use App\Controllers\RoleController;
use App\Controllers\RolePermissionController;
use App\Controllers\SalesInvoiceController;
use App\Controllers\SalesPaymentController;
use App\Controllers\SalesReturnController;
use App\Controllers\SalesmanController;
use App\Controllers\SettingsController;
use App\Controllers\StockAdjustmentController;
use App\Controllers\SubcategoryController;
use App\Controllers\SupplierAdvanceController;
use App\Controllers\SupplierController;
use App\Controllers\SupplierPaymentController;
use App\Controllers\UnitController;
use App\Controllers\UserController;
use App\Controllers\UserPermissionController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PermissionMiddleware;

$router->addMiddleware(CsrfMiddleware::class);

// Public
$router->get('/',         [HomeController::class, 'index']);
$router->get('/login',    [AuthController::class, 'showLogin']);
$router->post('/login',   [AuthController::class, 'login']);

// ---------------------------------------------------------------------------
// Authorization
//   $auth          → any authenticated user (no specific permission)
//   $can(res, abl) → authenticated AND holds the resource.ability permission
//                    (admin is a master-switch: always allowed). Replaces the old
//                    hard-coded role tiers so custom roles are actually enforced.
//
// Ability convention per verb:
//   index/show/print-list → view      create form + store        → create
//   edit/update/lines/extras/header/footer/toggle/cheque-status/
//     recipe/units/pause-resume/run/update-latest-cost           → update
//   delete/destroy → delete    finalize → finalize   cancel → cancel
//   void → void    refund → refund    print/receipt → print    csv/json → export
// ---------------------------------------------------------------------------
$auth = [AuthMiddleware::class];
$can  = static fn (string $resource, string $ability = 'view'): array =>
    [AuthMiddleware::class, new PermissionMiddleware($resource, $ability)];

$router->get('/logout',    [AuthController::class, 'logout'],     $auth);
$router->get('/help',      [HelpController::class, 'index'],      $auth);
$router->get('/dashboard', [DashboardController::class, 'index'], $can('dashboard', 'view'));
$router->get('/dashboard/data',   [DashboardController::class, 'data'],   $can('dashboard', 'view'));
$router->get('/dashboard/alerts', [DashboardController::class, 'alerts'], $auth);

// Categories
$router->get('/categories',                  [CategoryController::class, 'index'],         $can('categories', 'view'));
$router->get('/categories/create',           [CategoryController::class, 'create'],        $can('categories', 'create'));
$router->post('/categories',                 [CategoryController::class, 'store'],         $can('categories', 'create'));
$router->get('/categories/{id}/edit',        [CategoryController::class, 'edit'],          $can('categories', 'update'));
$router->post('/categories/{id}/update',     [CategoryController::class, 'update'],        $can('categories', 'update'));
$router->post('/categories/{id}/toggle',     [CategoryController::class, 'toggleStatus'],  $can('categories', 'update'));

// Sub-Categories
$router->get('/subcategories',                  [SubcategoryController::class, 'index'],        $can('subcategories', 'view'));
$router->get('/subcategories/create',           [SubcategoryController::class, 'create'],       $can('subcategories', 'create'));
$router->post('/subcategories',                 [SubcategoryController::class, 'store'],        $can('subcategories', 'create'));
$router->get('/subcategories/{id}/edit',        [SubcategoryController::class, 'edit'],         $can('subcategories', 'update'));
$router->post('/subcategories/{id}/update',     [SubcategoryController::class, 'update'],       $can('subcategories', 'update'));
$router->post('/subcategories/{id}/toggle',     [SubcategoryController::class, 'toggleStatus'], $can('subcategories', 'update'));

// Brands
$router->get('/brands',                  [BrandController::class, 'index'],         $can('brands', 'view'));
$router->get('/brands/create',           [BrandController::class, 'create'],        $can('brands', 'create'));
$router->post('/brands',                 [BrandController::class, 'store'],         $can('brands', 'create'));
$router->get('/brands/{id}/edit',        [BrandController::class, 'edit'],          $can('brands', 'update'));
$router->post('/brands/{id}/update',     [BrandController::class, 'update'],        $can('brands', 'update'));
$router->post('/brands/{id}/toggle',     [BrandController::class, 'toggleStatus'],  $can('brands', 'update'));

// Units
$router->get('/units',                     [UnitController::class, 'index'],   $can('units', 'view'));
$router->get('/units/create',              [UnitController::class, 'create'],  $can('units', 'create'));
$router->post('/units',                    [UnitController::class, 'store'],   $can('units', 'create'));
$router->get('/units/{id}/edit',           [UnitController::class, 'edit'],    $can('units', 'update'));
$router->post('/units/{id}/update',        [UnitController::class, 'update'],  $can('units', 'update'));
$router->post('/units/{id}/delete',        [UnitController::class, 'destroy'], $can('units', 'delete'));

// Products
$router->get('/products',                  [ProductController::class, 'index'],         $can('products', 'view'));
$router->get('/products/create',           [ProductController::class, 'create'],        $can('products', 'create'));
$router->post('/products',                 [ProductController::class, 'store'],         $can('products', 'create'));
$router->get('/products/{id}/edit',        [ProductController::class, 'edit'],          $can('products', 'update'));
$router->post('/products/{id}/update',     [ProductController::class, 'update'],        $can('products', 'update'));
$router->post('/products/{id}/toggle',     [ProductController::class, 'toggleStatus'],  $can('products', 'update'));
$router->post('/products/{id}/update-latest-cost', [ProductController::class, 'updateLatestCost'], $can('products', 'update'));

// Per-product unit overrides (managed from product edit page)
$router->post('/products/{id}/units',                          [ProductUnitController::class, 'store'],   $can('products', 'update'));
$router->post('/products/{id}/units/{puId}/update',            [ProductUnitController::class, 'update'],  $can('products', 'update'));
$router->post('/products/{id}/units/{puId}/delete',            [ProductUnitController::class, 'destroy'], $can('products', 'update'));

// Product Recipes (composite products)
$router->get('/products/{id}/recipe',                           [ProductRecipeController::class, 'show'],        $can('products', 'update'));
$router->post('/products/{id}/recipe/notes',                    [ProductRecipeController::class, 'updateNotes'], $can('products', 'update'));
$router->post('/products/{id}/recipe/items',                    [ProductRecipeController::class, 'addItem'],     $can('products', 'update'));
$router->post('/products/{id}/recipe/items/{itemId}/update',    [ProductRecipeController::class, 'updateItem'],  $can('products', 'update'));
$router->post('/products/{id}/recipe/items/{itemId}/delete',    [ProductRecipeController::class, 'deleteItem'],  $can('products', 'update'));

// Production Orders
$router->get('/production-orders',          [ProductionOrderController::class, 'index'],   $can('production_orders', 'view'));
$router->get('/production-orders/create',   [ProductionOrderController::class, 'create'],  $can('production_orders', 'create'));
$router->post('/production-orders',         [ProductionOrderController::class, 'store'],   $can('production_orders', 'create'));
$router->get('/production-orders/{id}',     [ProductionOrderController::class, 'show'],    $can('production_orders', 'view'));

// Suppliers
$router->get('/suppliers',                  [SupplierController::class, 'index'],         $can('suppliers', 'view'));
$router->get('/suppliers/create',           [SupplierController::class, 'create'],        $can('suppliers', 'create'));
$router->post('/suppliers',                 [SupplierController::class, 'store'],         $can('suppliers', 'create'));
$router->get('/suppliers/{id}/edit',        [SupplierController::class, 'edit'],          $can('suppliers', 'update'));
$router->post('/suppliers/{id}/update',     [SupplierController::class, 'update'],        $can('suppliers', 'update'));
$router->post('/suppliers/{id}/toggle',     [SupplierController::class, 'toggleStatus'],  $can('suppliers', 'update'));

// Salesmen
$router->get('/salesmen',                  [SalesmanController::class, 'index'],         $can('salesmen', 'view'));
$router->get('/salesmen/create',           [SalesmanController::class, 'create'],        $can('salesmen', 'create'));
$router->post('/salesmen',                 [SalesmanController::class, 'store'],         $can('salesmen', 'create'));
$router->get('/salesmen/{id}/edit',        [SalesmanController::class, 'edit'],          $can('salesmen', 'update'));
$router->post('/salesmen/{id}/update',     [SalesmanController::class, 'update'],        $can('salesmen', 'update'));
$router->post('/salesmen/{id}/toggle',     [SalesmanController::class, 'toggleStatus'],  $can('salesmen', 'update'));

// Customers
$router->get('/customers',                  [CustomerController::class, 'index'],         $can('customers', 'view'));
$router->get('/customers/create',           [CustomerController::class, 'create'],        $can('customers', 'create'));
$router->post('/customers',                 [CustomerController::class, 'store'],         $can('customers', 'create'));
$router->get('/customers/{id}/edit',        [CustomerController::class, 'edit'],          $can('customers', 'update'));
$router->post('/customers/{id}/update',     [CustomerController::class, 'update'],        $can('customers', 'update'));
$router->post('/customers/{id}/toggle',     [CustomerController::class, 'toggleStatus'],  $can('customers', 'update'));

// Employees
$router->get('/employees',                  [EmployeeController::class, 'index'],         $can('employees', 'view'));
$router->get('/employees/create',           [EmployeeController::class, 'create'],        $can('employees', 'create'));
$router->post('/employees',                 [EmployeeController::class, 'store'],         $can('employees', 'create'));
$router->get('/employees/{id}/edit',        [EmployeeController::class, 'edit'],          $can('employees', 'update'));
$router->post('/employees/{id}/update',     [EmployeeController::class, 'update'],        $can('employees', 'update'));
$router->post('/employees/{id}/toggle',     [EmployeeController::class, 'toggleStatus'],  $can('employees', 'update'));

// Users (admin-only via permissions)
$router->get('/users',                  [UserController::class, 'index'],         $can('users', 'view'));
$router->get('/users/create',           [UserController::class, 'create'],        $can('users', 'create'));
$router->post('/users',                 [UserController::class, 'store'],         $can('users', 'create'));
$router->get('/users/{id}/edit',        [UserController::class, 'edit'],          $can('users', 'update'));
$router->post('/users/{id}/update',     [UserController::class, 'update'],        $can('users', 'update'));
$router->post('/users/{id}/toggle',     [UserController::class, 'toggleStatus'],  $can('users', 'update'));

// Per-user permission overrides
$router->get('/users/{id}/permissions',         [UserPermissionController::class, 'edit'],   $can('user_permissions', 'view'));
$router->post('/users/{id}/permissions/update', [UserPermissionController::class, 'update'], $can('user_permissions', 'update'));

// Roles CRUD
$router->get('/roles',                  [RoleController::class, 'index'],   $can('roles', 'view'));
$router->get('/roles/create',           [RoleController::class, 'create'],  $can('roles', 'create'));
$router->post('/roles',                 [RoleController::class, 'store'],   $can('roles', 'create'));
$router->get('/roles/{id}/edit',        [RoleController::class, 'edit'],    $can('roles', 'update'));
$router->post('/roles/{id}/update',     [RoleController::class, 'update'],  $can('roles', 'update'));
$router->post('/roles/{id}/delete',     [RoleController::class, 'destroy'], $can('roles', 'delete'));

// System Settings
$router->get('/settings',             [SettingsController::class, 'show'],     $can('system_settings', 'view'));
$router->post('/settings',            [SettingsController::class, 'update'],   $can('system_settings', 'update'));
$router->post('/settings/hard-reset', [SettingsController::class, 'hardReset'], $can('system_reset', 'execute'));

// Role Permissions (tree-view editor per role)
$router->get('/role-permissions',                  [RolePermissionController::class, 'index'],         $can('role_permissions', 'view'));
$router->get('/role-permissions/{id}/edit',        [RolePermissionController::class, 'edit'],          $can('role_permissions', 'view'));
$router->post('/role-permissions/{id}/update',     [RolePermissionController::class, 'update'],        $can('role_permissions', 'update'));
$router->post('/role-permissions/reset-defaults',  [RolePermissionController::class, 'resetDefaults'], $can('role_permissions', 'update'));

// Purchase Invoices
$router->get('/purchase-invoices',                               [PurchaseInvoiceController::class, 'index'],         $can('purchase_invoices', 'view'));
$router->get('/purchase-invoices/create',                        [PurchaseInvoiceController::class, 'create'],        $can('purchase_invoices', 'create'));
$router->post('/purchase-invoices',                              [PurchaseInvoiceController::class, 'store'],         $can('purchase_invoices', 'create'));
$router->get('/purchase-invoices/{id}',                          [PurchaseInvoiceController::class, 'show'],          $can('purchase_invoices', 'view'));
$router->get('/purchase-invoices/{id}/edit',                     [PurchaseInvoiceController::class, 'edit'],          $can('purchase_invoices', 'update'));
$router->post('/purchase-invoices/{id}/update-header',           [PurchaseInvoiceController::class, 'updateHeader'],  $can('purchase_invoices', 'update'));
$router->post('/purchase-invoices/{id}/update-footer',           [PurchaseInvoiceController::class, 'updateFooter'],  $can('purchase_invoices', 'update'));
$router->post('/purchase-invoices/{id}/lines',                   [PurchaseInvoiceController::class, 'addLine'],       $can('purchase_invoices', 'update'));
$router->post('/purchase-invoices/{id}/lines/{lineId}/update',   [PurchaseInvoiceController::class, 'updateLine'],    $can('purchase_invoices', 'update'));
$router->post('/purchase-invoices/{id}/lines/{lineId}/delete',   [PurchaseInvoiceController::class, 'deleteLine'],    $can('purchase_invoices', 'update'));
$router->post('/purchase-invoices/{id}/expenses',                [PurchaseInvoiceController::class, 'addExpense'],    $can('purchase_invoices', 'update'));
$router->post('/purchase-invoices/{id}/expenses/{expenseId}/delete', [PurchaseInvoiceController::class, 'deleteExpense'], $can('purchase_invoices', 'update'));
$router->post('/purchase-invoices/{id}/finalize',                [PurchaseInvoiceController::class, 'finalize'],      $can('purchase_invoices', 'finalize'));
$router->post('/purchase-invoices/{id}/cancel',                  [PurchaseInvoiceController::class, 'cancel'],        $can('purchase_invoices', 'cancel'));
$router->post('/purchase-invoices/{id}/delete',                  [PurchaseInvoiceController::class, 'destroy'],       $can('purchase_invoices', 'delete'));
$router->get('/purchase-invoices/{id}/print/a4',                 [PurchaseInvoiceController::class, 'printA4'],       $can('purchase_invoices', 'print'));
$router->get('/purchase-invoices/{id}/print/thermal',            [PurchaseInvoiceController::class, 'printThermal'],  $can('purchase_invoices', 'print'));

// Purchase Returns
$router->get('/purchase-returns',                              [PurchaseReturnController::class, 'index'],         $can('purchase_returns', 'view'));
$router->get('/purchase-returns/create',                       [PurchaseReturnController::class, 'create'],        $can('purchase_returns', 'create'));
$router->post('/purchase-returns',                             [PurchaseReturnController::class, 'store'],         $can('purchase_returns', 'create'));
$router->get('/purchase-returns/{id}',                         [PurchaseReturnController::class, 'show'],          $can('purchase_returns', 'view'));
$router->get('/purchase-returns/{id}/edit',                    [PurchaseReturnController::class, 'edit'],          $can('purchase_returns', 'update'));
$router->post('/purchase-returns/{id}/update-header',          [PurchaseReturnController::class, 'updateHeader'],  $can('purchase_returns', 'update'));
$router->post('/purchase-returns/{id}/lines',                  [PurchaseReturnController::class, 'addLine'],       $can('purchase_returns', 'update'));
$router->post('/purchase-returns/{id}/lines/{lineId}/delete',  [PurchaseReturnController::class, 'deleteLine'],    $can('purchase_returns', 'update'));
$router->post('/purchase-returns/{id}/finalize',               [PurchaseReturnController::class, 'finalize'],      $can('purchase_returns', 'finalize'));
$router->post('/purchase-returns/{id}/cancel',                 [PurchaseReturnController::class, 'cancel'],        $can('purchase_returns', 'cancel'));
$router->post('/purchase-returns/{id}/delete',                 [PurchaseReturnController::class, 'destroy'],       $can('purchase_returns', 'delete'));
$router->get('/purchase-returns/{id}/print/a4',                [PurchaseReturnController::class, 'printA4'],       $can('purchase_returns', 'print'));

// Purchase Payments
$router->get('/purchase-payments',                                [PurchasePaymentController::class, 'index'],              $can('purchase_payments', 'view'));
$router->get('/purchase-payments/{id}',                           [PurchasePaymentController::class, 'show'],               $can('purchase_payments', 'view'));
$router->post('/purchase-payments/{id}',                          [PurchasePaymentController::class, 'store'],              $can('purchase_payments', 'create'));
$router->post('/purchase-payments/{paymentId}/cheque-status',     [PurchasePaymentController::class, 'updateChequeStatus'], $can('purchase_payments', 'update'));
$router->post('/purchase-payments/{paymentId}/delete',            [PurchasePaymentController::class, 'destroy'],            $can('purchase_payments', 'delete'));
$router->get('/purchase-payments/{paymentId}/receipt',            [PurchasePaymentController::class, 'printReceipt'],       $can('purchase_payments', 'print'));

// Sales Invoices
$router->get('/sales-invoices',                              [SalesInvoiceController::class, 'index'],         $can('sales_invoices', 'view'));
$router->get('/sales-invoices/create',                       [SalesInvoiceController::class, 'create'],        $can('sales_invoices', 'create'));
$router->post('/sales-invoices',                             [SalesInvoiceController::class, 'store'],         $can('sales_invoices', 'create'));
$router->get('/sales-invoices/{id}',                         [SalesInvoiceController::class, 'show'],          $can('sales_invoices', 'view'));
$router->get('/sales-invoices/{id}/edit',                    [SalesInvoiceController::class, 'edit'],          $can('sales_invoices', 'update'));
$router->post('/sales-invoices/{id}/update-header',          [SalesInvoiceController::class, 'updateHeader'],  $can('sales_invoices', 'update'));
$router->post('/sales-invoices/{id}/lines',                  [SalesInvoiceController::class, 'addLine'],       $can('sales_invoices', 'update'));
$router->post('/sales-invoices/{id}/ad-hoc-lines',           [SalesInvoiceController::class, 'addAdHocLine'],  $can('sales_invoices', 'update'));
$router->post('/sales-invoices/{id}/lines/{lineId}/update',  [SalesInvoiceController::class, 'updateLine'],    $can('sales_invoices', 'update'));
$router->post('/sales-invoices/{id}/lines/{lineId}/delete',  [SalesInvoiceController::class, 'deleteLine'],    $can('sales_invoices', 'update'));
$router->post('/sales-invoices/{id}/update-footer',          [SalesInvoiceController::class, 'updateFooter'],  $can('sales_invoices', 'update'));
$router->post('/sales-invoices/{id}/extras',                 [SalesInvoiceController::class, 'addExtra'],      $can('sales_invoices', 'update'));
$router->post('/sales-invoices/{id}/extras/{extraId}/update',[SalesInvoiceController::class, 'updateExtra'],   $can('sales_invoices', 'update'));
$router->post('/sales-invoices/{id}/extras/{extraId}/delete',[SalesInvoiceController::class, 'deleteExtra'],   $can('sales_invoices', 'update'));
$router->post('/sales-invoices/{id}/finalize',               [SalesInvoiceController::class, 'finalize'],      $can('sales_invoices', 'finalize'));
$router->post('/sales-invoices/{id}/cancel',                 [SalesInvoiceController::class, 'cancel'],        $can('sales_invoices', 'cancel'));
$router->post('/sales-invoices/{id}/delete',                 [SalesInvoiceController::class, 'destroy'],       $can('sales_invoices', 'delete'));
$router->get('/sales-invoices/{id}/print/a4',                [SalesInvoiceController::class, 'printA4'],       $can('sales_invoices', 'print'));
$router->get('/sales-invoices/{id}/print/thermal',           [SalesInvoiceController::class, 'printThermal'],  $can('sales_invoices', 'print'));

// Sales Returns
$router->get('/sales-returns',                              [SalesReturnController::class, 'index'],         $can('sales_returns', 'view'));
$router->get('/sales-returns/create',                       [SalesReturnController::class, 'create'],        $can('sales_returns', 'create'));
$router->post('/sales-returns',                             [SalesReturnController::class, 'store'],         $can('sales_returns', 'create'));
$router->get('/sales-returns/{id}',                         [SalesReturnController::class, 'show'],          $can('sales_returns', 'view'));
$router->get('/sales-returns/{id}/edit',                    [SalesReturnController::class, 'edit'],          $can('sales_returns', 'update'));
$router->post('/sales-returns/{id}/update-header',          [SalesReturnController::class, 'updateHeader'],  $can('sales_returns', 'update'));
$router->post('/sales-returns/{id}/lines',                  [SalesReturnController::class, 'addLine'],       $can('sales_returns', 'update'));
$router->post('/sales-returns/{id}/lines/{lineId}/delete',  [SalesReturnController::class, 'deleteLine'],    $can('sales_returns', 'update'));
$router->post('/sales-returns/{id}/finalize',               [SalesReturnController::class, 'finalize'],      $can('sales_returns', 'finalize'));
$router->post('/sales-returns/{id}/cancel',                 [SalesReturnController::class, 'cancel'],        $can('sales_returns', 'cancel'));
$router->get('/sales-returns/{id}/print/a4',                [SalesReturnController::class, 'printA4'],       $can('sales_returns', 'print'));

// Sales Payments
$router->get('/sales-payments',                            [SalesPaymentController::class, 'index'],              $can('sales_payments', 'view'));
$router->get('/sales-payments/{id}',                       [SalesPaymentController::class, 'show'],               $can('sales_payments', 'view'));
$router->post('/sales-payments/{id}',                      [SalesPaymentController::class, 'store'],              $can('sales_payments', 'create'));
$router->post('/sales-payments/{paymentId}/cheque-status', [SalesPaymentController::class, 'updateChequeStatus'], $can('sales_payments', 'update'));
$router->post('/sales-payments/{paymentId}/delete',        [SalesPaymentController::class, 'destroy'],            $can('sales_payments', 'delete'));
$router->get('/sales-payments/{paymentId}/receipt',        [SalesPaymentController::class, 'printReceipt'],       $can('sales_payments', 'print'));

// Bank Accounts
$router->get('/bank-accounts',                       [BankAccountController::class, 'index'],         $can('bank_accounts', 'view'));
$router->get('/bank-accounts/create',                [BankAccountController::class, 'create'],        $can('bank_accounts', 'create'));
$router->post('/bank-accounts',                      [BankAccountController::class, 'store'],         $can('bank_accounts', 'create'));
$router->get('/bank-accounts/{id}',                  [BankAccountController::class, 'show'],          $can('bank_accounts', 'view'));
$router->get('/bank-accounts/{id}/edit',             [BankAccountController::class, 'edit'],          $can('bank_accounts', 'update'));
$router->post('/bank-accounts/{id}/update',          [BankAccountController::class, 'update'],        $can('bank_accounts', 'update'));
$router->post('/bank-accounts/{id}/toggle',          [BankAccountController::class, 'toggleStatus'],  $can('bank_accounts', 'update'));

// Cashier Drawers
$router->get('/cashier-drawers',                     [CashierDrawerController::class, 'index'],         $can('cashier_drawers', 'view'));
$router->get('/cashier-drawers/create',              [CashierDrawerController::class, 'create'],        $can('cashier_drawers', 'create'));
$router->post('/cashier-drawers',                    [CashierDrawerController::class, 'store'],         $can('cashier_drawers', 'create'));
$router->get('/cashier-drawers/{id}',                [CashierDrawerController::class, 'show'],          $can('cashier_drawers', 'view'));
$router->get('/cashier-drawers/{id}/edit',           [CashierDrawerController::class, 'edit'],          $can('cashier_drawers', 'update'));
$router->post('/cashier-drawers/{id}/update',        [CashierDrawerController::class, 'update'],        $can('cashier_drawers', 'update'));
$router->post('/cashier-drawers/{id}/toggle',        [CashierDrawerController::class, 'toggleStatus'],  $can('cashier_drawers', 'update'));

// Inter-account Transfers
$router->get('/inter-account-transfers',             [InterAccountTransferController::class, 'index'],  $can('inter_account_transfers', 'view'));
$router->get('/inter-account-transfers/create',      [InterAccountTransferController::class, 'create'], $can('inter_account_transfers', 'create'));
$router->post('/inter-account-transfers',            [InterAccountTransferController::class, 'store'],  $can('inter_account_transfers', 'create'));
$router->get('/inter-account-transfers/{id}',        [InterAccountTransferController::class, 'show'],   $can('inter_account_transfers', 'view'));

// Operating Expense Categories
$router->get('/operating-expense-categories',                  [OperatingExpenseCategoryController::class, 'index'],        $can('operating_expense_categories', 'view'));
$router->get('/operating-expense-categories/create',           [OperatingExpenseCategoryController::class, 'create'],       $can('operating_expense_categories', 'create'));
$router->post('/operating-expense-categories',                 [OperatingExpenseCategoryController::class, 'store'],        $can('operating_expense_categories', 'create'));
$router->get('/operating-expense-categories/{id}/edit',        [OperatingExpenseCategoryController::class, 'edit'],         $can('operating_expense_categories', 'update'));
$router->post('/operating-expense-categories/{id}/update',     [OperatingExpenseCategoryController::class, 'update'],       $can('operating_expense_categories', 'update'));
$router->post('/operating-expense-categories/{id}/toggle',     [OperatingExpenseCategoryController::class, 'toggleStatus'], $can('operating_expense_categories', 'update'));

// Operating Expenses
$router->get('/operating-expenses',                            [OperatingExpenseController::class, 'index'],     $can('operating_expenses', 'view'));
$router->get('/operating-expenses/create',                     [OperatingExpenseController::class, 'create'],    $can('operating_expenses', 'create'));
$router->post('/operating-expenses',                           [OperatingExpenseController::class, 'store'],     $can('operating_expenses', 'create'));
$router->get('/operating-expenses/{id}',                       [OperatingExpenseController::class, 'show'],      $can('operating_expenses', 'view'));
$router->post('/operating-expenses/{id}/void',                 [OperatingExpenseController::class, 'void'],      $can('operating_expenses', 'void'));
$router->get('/operating-expenses/{id}/edit',                  [OperatingExpenseController::class, 'edit'],      $can('operating_expenses', 'update'));
$router->post('/operating-expenses/{id}/update',               [OperatingExpenseController::class, 'update'],    $can('operating_expenses', 'update'));
$router->get('/operating-expenses/{id}/receipt',               [OperatingExpenseController::class, 'printReceipt'], $can('operating_expenses', 'print'));

// Recurring Expenses
$router->get('/recurring-expenses',                            [RecurringExpenseController::class, 'index'],       $can('recurring_expenses', 'view'));
$router->get('/recurring-expenses/create',                     [RecurringExpenseController::class, 'create'],      $can('recurring_expenses', 'create'));
$router->post('/recurring-expenses',                           [RecurringExpenseController::class, 'store'],       $can('recurring_expenses', 'create'));
$router->get('/recurring-expenses/{id}',                       [RecurringExpenseController::class, 'show'],        $can('recurring_expenses', 'view'));
$router->get('/recurring-expenses/{id}/edit',                  [RecurringExpenseController::class, 'edit'],        $can('recurring_expenses', 'update'));
$router->post('/recurring-expenses/{id}/update',               [RecurringExpenseController::class, 'update'],      $can('recurring_expenses', 'update'));
$router->post('/recurring-expenses/{id}/pause-resume',         [RecurringExpenseController::class, 'pauseResume'], $can('recurring_expenses', 'update'));
$router->post('/recurring-expenses/{id}/run-one',              [RecurringExpenseController::class, 'runOne'],      $can('recurring_expenses', 'update'));
$router->post('/recurring-expenses/{id}/delete',               [RecurringExpenseController::class, 'delete'],      $can('recurring_expenses', 'delete'));
$router->post('/recurring-expenses/run-due',                   [RecurringExpenseController::class, 'runDue'],      $can('recurring_expenses', 'update'));

// Capital Movements
$router->get('/capital-movements',                             [CapitalMovementController::class, 'index'],     $can('capital_movements', 'view'));
$router->get('/capital-movements/create',                      [CapitalMovementController::class, 'create'],    $can('capital_movements', 'create'));
$router->post('/capital-movements',                            [CapitalMovementController::class, 'store'],     $can('capital_movements', 'create'));
$router->get('/capital-movements/{id}',                        [CapitalMovementController::class, 'show'],      $can('capital_movements', 'view'));
$router->post('/capital-movements/{id}/void',                  [CapitalMovementController::class, 'void'],      $can('capital_movements', 'void'));

// Rate Management (CR-4) - bulk selling-price changes, category/subcategory-wise.
// index is a GET preview and writes nothing; only apply/revert change prices.
$router->get('/rate-changes',                                  [RateChangeController::class, 'index'],          $can('rate_changes', 'view'));
$router->post('/rate-changes/apply',                           [RateChangeController::class, 'apply'],          $can('rate_changes', 'create'));
$router->get('/rate-changes/{id}',                             [RateChangeController::class, 'show'],           $can('rate_changes', 'view'));
$router->post('/rate-changes/{id}/revert',                     [RateChangeController::class, 'revert'],         $can('rate_changes', 'delete'));

// Universal Passbook
$router->get('/passbook/{kind}/{id}',                          [PassbookController::class, 'show'],             $can('passbook', 'view'));
// CR-5: printable statement (browser "Save as PDF"); the WhatsApp share is a wa.me
// link built in the controller, so it needs no route of its own.
$router->get('/passbook/{kind}/{id}/print',                    [PassbookController::class, 'printA4'],          $can('passbook', 'print'));

// Salesman Commission Settlement
$router->get('/commission-settlements',                        [CommissionSettlementController::class, 'index'],  $can('commission_settlements', 'view'));
$router->get('/commission-settlements/create',                 [CommissionSettlementController::class, 'create'], $can('commission_settlements', 'create'));
$router->post('/commission-settlements',                       [CommissionSettlementController::class, 'store'],  $can('commission_settlements', 'create'));
$router->get('/commission-settlements/{id}',                   [CommissionSettlementController::class, 'show'],   $can('commission_settlements', 'view'));
$router->post('/commission-settlements/{id}/void',             [CommissionSettlementController::class, 'void'],   $can('commission_settlements', 'void'));

// Customer Advances
$router->get('/customer-advances',                             [CustomerAdvanceController::class, 'index'],     $can('customer_advances', 'view'));
$router->get('/customer-advances/create',                      [CustomerAdvanceController::class, 'create'],    $can('customer_advances', 'create'));
$router->post('/customer-advances',                            [CustomerAdvanceController::class, 'store'],     $can('customer_advances', 'create'));
$router->get('/customer-advances/{id}',                        [CustomerAdvanceController::class, 'show'],      $can('customer_advances', 'view'));
$router->post('/customer-advances/{id}/refund',                [CustomerAdvanceController::class, 'refund'],    $can('customer_advances', 'refund'));

// Bulk Customer Payment
$router->get('/customer-payments/create',                      [CustomerPaymentController::class, 'create'],    $can('customer_payments', 'create'));
$router->post('/customer-payments',                            [CustomerPaymentController::class, 'store'],     $can('customer_payments', 'create'));

// Supplier Advances
$router->get('/supplier-advances',                             [SupplierAdvanceController::class, 'index'],     $can('supplier_advances', 'view'));
$router->get('/supplier-advances/create',                      [SupplierAdvanceController::class, 'create'],    $can('supplier_advances', 'create'));
$router->post('/supplier-advances',                            [SupplierAdvanceController::class, 'store'],     $can('supplier_advances', 'create'));
$router->get('/supplier-advances/{id}',                        [SupplierAdvanceController::class, 'show'],      $can('supplier_advances', 'view'));
$router->post('/supplier-advances/{id}/refund',                [SupplierAdvanceController::class, 'refund'],    $can('supplier_advances', 'refund'));

// Bulk Supplier Payment
$router->get('/supplier-payments/create',                      [SupplierPaymentController::class, 'create'],    $can('supplier_payments', 'create'));
$router->post('/supplier-payments',                            [SupplierPaymentController::class, 'store'],     $can('supplier_payments', 'create'));

// Brand Rebates
$router->get('/brand-rebates',                                 [BrandRebateController::class, 'index'],         $can('brand_rebates', 'view'));
$router->get('/brand-rebates/create',                          [BrandRebateController::class, 'create'],        $can('brand_rebates', 'create'));
$router->post('/brand-rebates',                                [BrandRebateController::class, 'store'],         $can('brand_rebates', 'create'));
$router->get('/brand-rebates/{id}',                            [BrandRebateController::class, 'show'],          $can('brand_rebates', 'view'));
$router->post('/brand-rebates/{id}/void',                      [BrandRebateController::class, 'void'],          $can('brand_rebates', 'void'));

// Stock Adjustments
$router->get('/stock-adjustments',                             [StockAdjustmentController::class, 'index'],     $can('stock_adjustments', 'view'));
$router->get('/stock-adjustments/create',                      [StockAdjustmentController::class, 'create'],    $can('stock_adjustments', 'create'));
$router->post('/stock-adjustments',                            [StockAdjustmentController::class, 'store'],     $can('stock_adjustments', 'create'));
$router->get('/stock-adjustments/{id}',                        [StockAdjustmentController::class, 'show'],      $can('stock_adjustments', 'view'));
$router->post('/stock-adjustments/{id}/void',                  [StockAdjustmentController::class, 'void'],      $can('stock_adjustments', 'void'));

// Old Records — pre-v2 obligations + settlements attached to live customer/supplier/salesman
$router->get('/old-records',                                          [LegacyRecordController::class, 'index'],            $can('legacy_records', 'view'));
$router->get('/old-records/{kind}/{id}',                              [LegacyRecordController::class, 'show'],             $can('legacy_records', 'view'));
$router->post('/old-records/{kind}/{id}/obligations',                 [LegacyRecordController::class, 'storeObligation'],  $can('legacy_records', 'create'));
$router->post('/old-records/{kind}/{id}/settlements',                 [LegacyRecordController::class, 'storeSettlement'],  $can('legacy_records', 'create'));
$router->post('/old-records/obligations/{obligationId}/delete',       [LegacyRecordController::class, 'deleteObligation'], $can('legacy_records', 'delete'));
$router->post('/old-records/settlements/{settlementId}/delete',       [LegacyRecordController::class, 'deleteSettlement'], $can('legacy_records', 'delete'));

// System Integrity Check
$router->get('/system/integrity-check', [IntegrityCheckController::class, 'show'], $can('integrity_check', 'view'));

// System Activity Log
$router->get('/activity-log',           [ActivityLogController::class, 'index'],   $can('activity_log', 'view'));

// Reports
// Due List (CR-2 / CR-3 / CR-11). Read-only report + the two due-term tag writes.
$router->get('/reports/due-list',                   [ReportsController::class, 'dueListReport'],          $can('reports_due_list', 'view'));
$router->get('/reports/due-list/print',             [ReportsController::class, 'dueListPrint'],           $can('reports_due_list', 'print'));
$router->get('/reports/due-list/csv',               [ReportsController::class, 'dueListCsv'],             $can('reports_due_list', 'export'));
$router->post('/due-list/invoice/{id}/term',        [DueTermController::class, 'updateInvoice'],          $can('reports_due_list', 'update'));
$router->post('/due-list/customer/{id}/term',       [DueTermController::class, 'updateCustomer'],         $can('reports_due_list', 'update'));

$router->get('/reports/cash-flow',                  [ReportsController::class, 'cashFlow'],               $can('reports_cash_flow', 'view'));
$router->get('/reports/balance-sheet',              [ReportsController::class, 'balanceSheet'],           $can('reports_balance_sheet', 'view'));
$router->get('/reports/profit-loss',                [ReportsController::class, 'profitLoss'],             $can('reports_profit_loss', 'view'));
$router->get('/reports/gst-sales-register',         [ReportsController::class, 'gstSalesRegister'],       $can('reports_gst_sales_register', 'view'));
$router->get('/reports/gst-sales-register/csv',     [ReportsController::class, 'gstSalesRegisterCsv'],    $can('reports_gst_sales_register', 'export'));
$router->get('/reports/gst-purchase-register',      [ReportsController::class, 'gstPurchaseRegister'],    $can('reports_gst_purchase_register', 'view'));
$router->get('/reports/gst-purchase-register/csv',  [ReportsController::class, 'gstPurchaseRegisterCsv'], $can('reports_gst_purchase_register', 'export'));
$router->get('/reports/gstr1/json',                 [ReportsController::class, 'gstr1Json'],              $can('reports_gstr1', 'export'));
$router->get('/reports/gstr3b/json',                [ReportsController::class, 'gstr3bJson'],             $can('reports_gstr3b', 'export'));
