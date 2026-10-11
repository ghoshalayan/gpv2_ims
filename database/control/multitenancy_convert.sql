-- =====================================================================
--  MULTI-TENANCY — single-database conversion
-- =====================================================================
--  Converts this database from one business to many, keeping ALL existing
--  data as tenant 1.
--
--  HOW IT WORKS
--  Each business table gains a tenant_id, is renamed to <table>__data, and
--  a VIEW with the original name takes its place showing only the current
--  tenant's rows. Every one of the app's 869 queries keeps using the
--  original name, so all of them are filtered automatically — isolation is
--  enforced by the database, not by a WHERE clause somebody has to
--  remember to write in 869 places.
--
--  IT FAILS CLOSED. With no tenant selected the views return no rows and
--  writes are refused outright, rather than silently landing in tenant 1.
--
--  >>> PREREQUISITE: run precheck_multitenancy.sql FIRST. <<<
--  This needs CREATE ROUTINE, CREATE VIEW and TRIGGER privileges, which
--  some shared-hosting plans withhold. If the precheck fails, stop —
--  nothing below will work.
--
--  >>> TAKE A FULL DATABASE EXPORT FIRST. <<< This rewrites every
--  business table in place. The export is the only way back.
--
--  Generated from the live schema: 4 shared tables,54 tenant-scoped.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Control tables. Shared across tenants, so they carry no tenant_id.
-- ---------------------------------------------------------------------
CREATE TABLE tenants (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tenant_key    VARCHAR(40)  NOT NULL,
    label         VARCHAR(150) NOT NULL,
    status        ENUM('active','suspended') NOT NULL DEFAULT 'active',
    remarks       VARCHAR(500) DEFAULT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_tenant_key (tenant_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE superadmins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    username      VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email         VARCHAR(150) DEFAULT NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    is_protected  TINYINT(1)   NOT NULL DEFAULT 0,
    last_login_at DATETIME     DEFAULT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_superadmin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE superadmin_activity (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    superadmin_id INT          DEFAULT NULL,
    username      VARCHAR(150) DEFAULT NULL,
    action        VARCHAR(40)  NOT NULL,
    tenant_id     INT          DEFAULT NULL,
    tenant_key    VARCHAR(40)  DEFAULT NULL,
    ip_address    VARCHAR(45)  DEFAULT NULL,
    notes         VARCHAR(500) DEFAULT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sa_act_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Your existing business becomes tenant 1 — which is why every tenant_id
-- column added below defaults to 1.
INSERT INTO tenants (id, tenant_key, label, remarks) VALUES
    (1, 'gp', 'GP Enterprise', 'The original business, kept as tenant 1.');

-- Same temporary password as the 'owner' account. CHANGE IT AFTER LOGIN.
INSERT INTO superadmins (name, username, password_hash, is_protected) VALUES
    ('Super Administrator', 'superadmin', '$2y$10$GGCgTPjuwX3lnJKIQOZj..adxWNd8yIQPRDNrVvRLLPJq36zUXr46', 1);

-- ---------------------------------------------------------------------
-- 2. The current tenant, readable from inside a view.
--    MySQL/MariaDB forbid a session variable in a view definition, so it
--    is wrapped in a function. The app sets @app_tenant on every
--    connection; outside the app it is NULL, so the views show nothing.
-- ---------------------------------------------------------------------
DROP FUNCTION IF EXISTS app_tenant;
CREATE FUNCTION app_tenant() RETURNS INT DETERMINISTIC NO SQL RETURN @app_tenant;

-- ---------------------------------------------------------------------
-- 3. Convert each business table.
-- ---------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;

-- activity_log
ALTER TABLE `activity_log` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_activity_log_tn` (tenant_id);
RENAME TABLE `activity_log` TO `activity_log__data`;
CREATE VIEW `activity_log` AS SELECT * FROM `activity_log__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `activity_log__stamp` BEFORE INSERT ON `activity_log__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- bank_accounts
ALTER TABLE `bank_accounts` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_bank_accounts_tn` (tenant_id);
ALTER TABLE `bank_accounts` DROP INDEX `uniq_bank_account_name`, ADD UNIQUE KEY `uniq_bank_account_name` (tenant_id, `name`);
RENAME TABLE `bank_accounts` TO `bank_accounts__data`;
CREATE VIEW `bank_accounts` AS SELECT * FROM `bank_accounts__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `bank_accounts__stamp` BEFORE INSERT ON `bank_accounts__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- brand_purchase_slabs
ALTER TABLE `brand_purchase_slabs` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_brand_purchase_slabs_tn` (tenant_id);
RENAME TABLE `brand_purchase_slabs` TO `brand_purchase_slabs__data`;
CREATE VIEW `brand_purchase_slabs` AS SELECT * FROM `brand_purchase_slabs__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `brand_purchase_slabs__stamp` BEFORE INSERT ON `brand_purchase_slabs__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- brand_rebate_cost_adjustments
ALTER TABLE `brand_rebate_cost_adjustments` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_brand_rebate_cost_adjustments_tn` (tenant_id);
RENAME TABLE `brand_rebate_cost_adjustments` TO `brand_rebate_cost_adjustments__data`;
CREATE VIEW `brand_rebate_cost_adjustments` AS SELECT * FROM `brand_rebate_cost_adjustments__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `brand_rebate_cost_adjustments__stamp` BEFORE INSERT ON `brand_rebate_cost_adjustments__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- brand_rebates
ALTER TABLE `brand_rebates` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_brand_rebates_tn` (tenant_id);
ALTER TABLE `brand_rebates` DROP INDEX `uniq_rebate_number`, ADD UNIQUE KEY `uniq_rebate_number` (tenant_id, `rebate_number`);
RENAME TABLE `brand_rebates` TO `brand_rebates__data`;
CREATE VIEW `brand_rebates` AS SELECT * FROM `brand_rebates__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `brand_rebates__stamp` BEFORE INSERT ON `brand_rebates__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- brands
ALTER TABLE `brands` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_brands_tn` (tenant_id);
ALTER TABLE `brands` DROP INDEX `uniq_brands_name`, ADD UNIQUE KEY `uniq_brands_name` (tenant_id, `name`);
RENAME TABLE `brands` TO `brands__data`;
CREATE VIEW `brands` AS SELECT * FROM `brands__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `brands__stamp` BEFORE INSERT ON `brands__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- business_profile
ALTER TABLE `business_profile` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_business_profile_tn` (tenant_id);
-- PK has no AUTO_INCREMENT, so it is only unique within one business.
ALTER TABLE `business_profile` DROP PRIMARY KEY, ADD PRIMARY KEY (tenant_id, `id`);
RENAME TABLE `business_profile` TO `business_profile__data`;
CREATE VIEW `business_profile` AS SELECT * FROM `business_profile__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `business_profile__stamp` BEFORE INSERT ON `business_profile__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- capital_movements
ALTER TABLE `capital_movements` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_capital_movements_tn` (tenant_id);
ALTER TABLE `capital_movements` DROP INDEX `uniq_capital_number`, ADD UNIQUE KEY `uniq_capital_number` (tenant_id, `movement_number`);
RENAME TABLE `capital_movements` TO `capital_movements__data`;
CREATE VIEW `capital_movements` AS SELECT * FROM `capital_movements__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `capital_movements__stamp` BEFORE INSERT ON `capital_movements__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- cashier_drawers
ALTER TABLE `cashier_drawers` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_cashier_drawers_tn` (tenant_id);
ALTER TABLE `cashier_drawers` DROP INDEX `uniq_drawer_name`, ADD UNIQUE KEY `uniq_drawer_name` (tenant_id, `name`);
RENAME TABLE `cashier_drawers` TO `cashier_drawers__data`;
CREATE VIEW `cashier_drawers` AS SELECT * FROM `cashier_drawers__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `cashier_drawers__stamp` BEFORE INSERT ON `cashier_drawers__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- categories
ALTER TABLE `categories` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_categories_tn` (tenant_id);
ALTER TABLE `categories` DROP INDEX `uniq_categories_name`, ADD UNIQUE KEY `uniq_categories_name` (tenant_id, `name`);
RENAME TABLE `categories` TO `categories__data`;
CREATE VIEW `categories` AS SELECT * FROM `categories__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `categories__stamp` BEFORE INSERT ON `categories__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- customer_advances
ALTER TABLE `customer_advances` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_customer_advances_tn` (tenant_id);
ALTER TABLE `customer_advances` DROP INDEX `uniq_advance_number`, ADD UNIQUE KEY `uniq_advance_number` (tenant_id, `advance_number`);
RENAME TABLE `customer_advances` TO `customer_advances__data`;
CREATE VIEW `customer_advances` AS SELECT * FROM `customer_advances__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `customer_advances__stamp` BEFORE INSERT ON `customer_advances__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- customers
ALTER TABLE `customers` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_customers_tn` (tenant_id);
ALTER TABLE `customers` DROP INDEX `uniq_customers_name`, ADD UNIQUE KEY `uniq_customers_name` (tenant_id, `name`);
ALTER TABLE `customers` DROP INDEX `uniq_customers_phone`, ADD UNIQUE KEY `uniq_customers_phone` (tenant_id, `phone`);
RENAME TABLE `customers` TO `customers__data`;
CREATE VIEW `customers` AS SELECT * FROM `customers__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `customers__stamp` BEFORE INSERT ON `customers__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- employees
ALTER TABLE `employees` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_employees_tn` (tenant_id);
ALTER TABLE `employees` DROP INDEX `uniq_employees_name`, ADD UNIQUE KEY `uniq_employees_name` (tenant_id, `name`);
ALTER TABLE `employees` DROP INDEX `uniq_employees_phone`, ADD UNIQUE KEY `uniq_employees_phone` (tenant_id, `phone`);
RENAME TABLE `employees` TO `employees__data`;
CREATE VIEW `employees` AS SELECT * FROM `employees__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `employees__stamp` BEFORE INSERT ON `employees__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- inter_account_transfers
ALTER TABLE `inter_account_transfers` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_inter_account_transfers_tn` (tenant_id);
ALTER TABLE `inter_account_transfers` DROP INDEX `uniq_transfer_number`, ADD UNIQUE KEY `uniq_transfer_number` (tenant_id, `transfer_number`);
RENAME TABLE `inter_account_transfers` TO `inter_account_transfers__data`;
CREATE VIEW `inter_account_transfers` AS SELECT * FROM `inter_account_transfers__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `inter_account_transfers__stamp` BEFORE INSERT ON `inter_account_transfers__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- legacy_obligations
ALTER TABLE `legacy_obligations` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_legacy_obligations_tn` (tenant_id);
RENAME TABLE `legacy_obligations` TO `legacy_obligations__data`;
CREATE VIEW `legacy_obligations` AS SELECT * FROM `legacy_obligations__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `legacy_obligations__stamp` BEFORE INSERT ON `legacy_obligations__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- legacy_settlements
ALTER TABLE `legacy_settlements` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_legacy_settlements_tn` (tenant_id);
RENAME TABLE `legacy_settlements` TO `legacy_settlements__data`;
CREATE VIEW `legacy_settlements` AS SELECT * FROM `legacy_settlements__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `legacy_settlements__stamp` BEFORE INSERT ON `legacy_settlements__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- operating_expense_categories
ALTER TABLE `operating_expense_categories` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_operating_expense_categories_tn` (tenant_id);
ALTER TABLE `operating_expense_categories` DROP INDEX `uniq_oec_name`, ADD UNIQUE KEY `uniq_oec_name` (tenant_id, `name`);
RENAME TABLE `operating_expense_categories` TO `operating_expense_categories__data`;
CREATE VIEW `operating_expense_categories` AS SELECT * FROM `operating_expense_categories__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `operating_expense_categories__stamp` BEFORE INSERT ON `operating_expense_categories__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- operating_expenses
ALTER TABLE `operating_expenses` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_operating_expenses_tn` (tenant_id);
ALTER TABLE `operating_expenses` DROP INDEX `uniq_expense_number`, ADD UNIQUE KEY `uniq_expense_number` (tenant_id, `expense_number`);
RENAME TABLE `operating_expenses` TO `operating_expenses__data`;
CREATE VIEW `operating_expenses` AS SELECT * FROM `operating_expenses__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `operating_expenses__stamp` BEFORE INSERT ON `operating_expenses__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- product_composition_items
ALTER TABLE `product_composition_items` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_product_composition_items_tn` (tenant_id);
RENAME TABLE `product_composition_items` TO `product_composition_items__data`;
CREATE VIEW `product_composition_items` AS SELECT * FROM `product_composition_items__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `product_composition_items__stamp` BEFORE INSERT ON `product_composition_items__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- product_compositions
ALTER TABLE `product_compositions` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_product_compositions_tn` (tenant_id);
ALTER TABLE `product_compositions` ADD KEY `idx_uniq_pc_product_fk` (`product_id`);
ALTER TABLE `product_compositions` DROP INDEX `uniq_pc_product`, ADD UNIQUE KEY `uniq_pc_product` (tenant_id, `product_id`);
RENAME TABLE `product_compositions` TO `product_compositions__data`;
CREATE VIEW `product_compositions` AS SELECT * FROM `product_compositions__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `product_compositions__stamp` BEFORE INSERT ON `product_compositions__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- product_price_history
ALTER TABLE `product_price_history` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_product_price_history_tn` (tenant_id);
RENAME TABLE `product_price_history` TO `product_price_history__data`;
CREATE VIEW `product_price_history` AS SELECT * FROM `product_price_history__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `product_price_history__stamp` BEFORE INSERT ON `product_price_history__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- product_slab_rates
ALTER TABLE `product_slab_rates` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_product_slab_rates_tn` (tenant_id);
ALTER TABLE `product_slab_rates` ADD KEY `idx_uniq_psr_product_slab_fk` (`product_id`);
ALTER TABLE `product_slab_rates` DROP INDEX `uniq_psr_product_slab`, ADD UNIQUE KEY `uniq_psr_product_slab` (tenant_id, `product_id`, `slab_id`);
RENAME TABLE `product_slab_rates` TO `product_slab_rates__data`;
CREATE VIEW `product_slab_rates` AS SELECT * FROM `product_slab_rates__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `product_slab_rates__stamp` BEFORE INSERT ON `product_slab_rates__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- product_units
ALTER TABLE `product_units` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_product_units_tn` (tenant_id);
ALTER TABLE `product_units` ADD KEY `idx_uniq_pu_fk` (`product_id`);
ALTER TABLE `product_units` DROP INDEX `uniq_pu`, ADD UNIQUE KEY `uniq_pu` (tenant_id, `product_id`, `unit_id`);
RENAME TABLE `product_units` TO `product_units__data`;
CREATE VIEW `product_units` AS SELECT * FROM `product_units__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `product_units__stamp` BEFORE INSERT ON `product_units__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- production_order_lines
ALTER TABLE `production_order_lines` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_production_order_lines_tn` (tenant_id);
RENAME TABLE `production_order_lines` TO `production_order_lines__data`;
CREATE VIEW `production_order_lines` AS SELECT * FROM `production_order_lines__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `production_order_lines__stamp` BEFORE INSERT ON `production_order_lines__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- production_orders
ALTER TABLE `production_orders` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_production_orders_tn` (tenant_id);
ALTER TABLE `production_orders` DROP INDEX `uniq_po_order_number`, ADD UNIQUE KEY `uniq_po_order_number` (tenant_id, `order_number`);
RENAME TABLE `production_orders` TO `production_orders__data`;
CREATE VIEW `production_orders` AS SELECT * FROM `production_orders__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `production_orders__stamp` BEFORE INSERT ON `production_orders__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- products
ALTER TABLE `products` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_products_tn` (tenant_id);
ALTER TABLE `products` DROP INDEX `uniq_products_name`, ADD UNIQUE KEY `uniq_products_name` (tenant_id, `name`);
RENAME TABLE `products` TO `products__data`;
CREATE VIEW `products` AS SELECT * FROM `products__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `products__stamp` BEFORE INSERT ON `products__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- purchase_invoice_expenses
ALTER TABLE `purchase_invoice_expenses` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_purchase_invoice_expenses_tn` (tenant_id);
RENAME TABLE `purchase_invoice_expenses` TO `purchase_invoice_expenses__data`;
CREATE VIEW `purchase_invoice_expenses` AS SELECT * FROM `purchase_invoice_expenses__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `purchase_invoice_expenses__stamp` BEFORE INSERT ON `purchase_invoice_expenses__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- purchase_invoice_lines
ALTER TABLE `purchase_invoice_lines` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_purchase_invoice_lines_tn` (tenant_id);
RENAME TABLE `purchase_invoice_lines` TO `purchase_invoice_lines__data`;
CREATE VIEW `purchase_invoice_lines` AS SELECT * FROM `purchase_invoice_lines__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `purchase_invoice_lines__stamp` BEFORE INSERT ON `purchase_invoice_lines__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- purchase_invoices
ALTER TABLE `purchase_invoices` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_purchase_invoices_tn` (tenant_id);
ALTER TABLE `purchase_invoices` ADD KEY `idx_uniq_purchase_invoice_supplier_number_fk` (`supplier_id`);
ALTER TABLE `purchase_invoices` DROP INDEX `uniq_purchase_invoice_supplier_number`, ADD UNIQUE KEY `uniq_purchase_invoice_supplier_number` (tenant_id, `supplier_id`, `invoice_number`);
RENAME TABLE `purchase_invoices` TO `purchase_invoices__data`;
CREATE VIEW `purchase_invoices` AS SELECT * FROM `purchase_invoices__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `purchase_invoices__stamp` BEFORE INSERT ON `purchase_invoices__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- purchase_payments
ALTER TABLE `purchase_payments` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_purchase_payments_tn` (tenant_id);
RENAME TABLE `purchase_payments` TO `purchase_payments__data`;
CREATE VIEW `purchase_payments` AS SELECT * FROM `purchase_payments__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `purchase_payments__stamp` BEFORE INSERT ON `purchase_payments__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- purchase_return_lines
ALTER TABLE `purchase_return_lines` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_purchase_return_lines_tn` (tenant_id);
RENAME TABLE `purchase_return_lines` TO `purchase_return_lines__data`;
CREATE VIEW `purchase_return_lines` AS SELECT * FROM `purchase_return_lines__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `purchase_return_lines__stamp` BEFORE INSERT ON `purchase_return_lines__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- purchase_returns
ALTER TABLE `purchase_returns` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_purchase_returns_tn` (tenant_id);
ALTER TABLE `purchase_returns` DROP INDEX `uniq_purchase_return_number`, ADD UNIQUE KEY `uniq_purchase_return_number` (tenant_id, `return_number`);
RENAME TABLE `purchase_returns` TO `purchase_returns__data`;
CREATE VIEW `purchase_returns` AS SELECT * FROM `purchase_returns__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `purchase_returns__stamp` BEFORE INSERT ON `purchase_returns__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- rate_change_batches
ALTER TABLE `rate_change_batches` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_rate_change_batches_tn` (tenant_id);
ALTER TABLE `rate_change_batches` DROP INDEX `uniq_rate_change_number`, ADD UNIQUE KEY `uniq_rate_change_number` (tenant_id, `batch_number`);
RENAME TABLE `rate_change_batches` TO `rate_change_batches__data`;
CREATE VIEW `rate_change_batches` AS SELECT * FROM `rate_change_batches__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `rate_change_batches__stamp` BEFORE INSERT ON `rate_change_batches__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- rate_change_lines
ALTER TABLE `rate_change_lines` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_rate_change_lines_tn` (tenant_id);
RENAME TABLE `rate_change_lines` TO `rate_change_lines__data`;
CREATE VIEW `rate_change_lines` AS SELECT * FROM `rate_change_lines__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `rate_change_lines__stamp` BEFORE INSERT ON `rate_change_lines__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- recurring_expenses
ALTER TABLE `recurring_expenses` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_recurring_expenses_tn` (tenant_id);
RENAME TABLE `recurring_expenses` TO `recurring_expenses__data`;
CREATE VIEW `recurring_expenses` AS SELECT * FROM `recurring_expenses__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `recurring_expenses__stamp` BEFORE INSERT ON `recurring_expenses__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- sales_invoice_extras
ALTER TABLE `sales_invoice_extras` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_sales_invoice_extras_tn` (tenant_id);
RENAME TABLE `sales_invoice_extras` TO `sales_invoice_extras__data`;
CREATE VIEW `sales_invoice_extras` AS SELECT * FROM `sales_invoice_extras__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `sales_invoice_extras__stamp` BEFORE INSERT ON `sales_invoice_extras__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- sales_invoice_line_components
ALTER TABLE `sales_invoice_line_components` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_sales_invoice_line_components_tn` (tenant_id);
RENAME TABLE `sales_invoice_line_components` TO `sales_invoice_line_components__data`;
CREATE VIEW `sales_invoice_line_components` AS SELECT * FROM `sales_invoice_line_components__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `sales_invoice_line_components__stamp` BEFORE INSERT ON `sales_invoice_line_components__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- sales_invoice_lines
ALTER TABLE `sales_invoice_lines` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_sales_invoice_lines_tn` (tenant_id);
RENAME TABLE `sales_invoice_lines` TO `sales_invoice_lines__data`;
CREATE VIEW `sales_invoice_lines` AS SELECT * FROM `sales_invoice_lines__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `sales_invoice_lines__stamp` BEFORE INSERT ON `sales_invoice_lines__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- sales_invoices
ALTER TABLE `sales_invoices` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_sales_invoices_tn` (tenant_id);
ALTER TABLE `sales_invoices` DROP INDEX `uniq_sales_invoice_number`, ADD UNIQUE KEY `uniq_sales_invoice_number` (tenant_id, `invoice_number`);
RENAME TABLE `sales_invoices` TO `sales_invoices__data`;
CREATE VIEW `sales_invoices` AS SELECT * FROM `sales_invoices__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `sales_invoices__stamp` BEFORE INSERT ON `sales_invoices__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- sales_payments
ALTER TABLE `sales_payments` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_sales_payments_tn` (tenant_id);
RENAME TABLE `sales_payments` TO `sales_payments__data`;
CREATE VIEW `sales_payments` AS SELECT * FROM `sales_payments__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `sales_payments__stamp` BEFORE INSERT ON `sales_payments__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- sales_return_line_components
ALTER TABLE `sales_return_line_components` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_sales_return_line_components_tn` (tenant_id);
RENAME TABLE `sales_return_line_components` TO `sales_return_line_components__data`;
CREATE VIEW `sales_return_line_components` AS SELECT * FROM `sales_return_line_components__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `sales_return_line_components__stamp` BEFORE INSERT ON `sales_return_line_components__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- sales_return_lines
ALTER TABLE `sales_return_lines` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_sales_return_lines_tn` (tenant_id);
RENAME TABLE `sales_return_lines` TO `sales_return_lines__data`;
CREATE VIEW `sales_return_lines` AS SELECT * FROM `sales_return_lines__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `sales_return_lines__stamp` BEFORE INSERT ON `sales_return_lines__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- sales_returns
ALTER TABLE `sales_returns` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_sales_returns_tn` (tenant_id);
ALTER TABLE `sales_returns` DROP INDEX `uniq_sales_return_number`, ADD UNIQUE KEY `uniq_sales_return_number` (tenant_id, `return_number`);
RENAME TABLE `sales_returns` TO `sales_returns__data`;
CREATE VIEW `sales_returns` AS SELECT * FROM `sales_returns__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `sales_returns__stamp` BEFORE INSERT ON `sales_returns__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- salesman_commission_settlements
ALTER TABLE `salesman_commission_settlements` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_salesman_commission_settlements_tn` (tenant_id);
ALTER TABLE `salesman_commission_settlements` DROP INDEX `uniq_commission_settlement_number`, ADD UNIQUE KEY `uniq_commission_settlement_number` (tenant_id, `settlement_number`);
RENAME TABLE `salesman_commission_settlements` TO `salesman_commission_settlements__data`;
CREATE VIEW `salesman_commission_settlements` AS SELECT * FROM `salesman_commission_settlements__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `salesman_commission_settlements__stamp` BEFORE INSERT ON `salesman_commission_settlements__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- salesmen
ALTER TABLE `salesmen` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_salesmen_tn` (tenant_id);
ALTER TABLE `salesmen` DROP INDEX `uniq_salesmen_name`, ADD UNIQUE KEY `uniq_salesmen_name` (tenant_id, `name`);
ALTER TABLE `salesmen` DROP INDEX `uniq_salesmen_phone`, ADD UNIQUE KEY `uniq_salesmen_phone` (tenant_id, `phone`);
RENAME TABLE `salesmen` TO `salesmen__data`;
CREATE VIEW `salesmen` AS SELECT * FROM `salesmen__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `salesmen__stamp` BEFORE INSERT ON `salesmen__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- stock_adjustment_lines
ALTER TABLE `stock_adjustment_lines` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_stock_adjustment_lines_tn` (tenant_id);
ALTER TABLE `stock_adjustment_lines` ADD KEY `idx_uniq_adj_line_fk` (`stock_adjustment_id`);
ALTER TABLE `stock_adjustment_lines` DROP INDEX `uniq_adj_line`, ADD UNIQUE KEY `uniq_adj_line` (tenant_id, `stock_adjustment_id`, `line_no`);
RENAME TABLE `stock_adjustment_lines` TO `stock_adjustment_lines__data`;
CREATE VIEW `stock_adjustment_lines` AS SELECT * FROM `stock_adjustment_lines__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `stock_adjustment_lines__stamp` BEFORE INSERT ON `stock_adjustment_lines__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- stock_adjustments
ALTER TABLE `stock_adjustments` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_stock_adjustments_tn` (tenant_id);
ALTER TABLE `stock_adjustments` DROP INDEX `uniq_adj_number`, ADD UNIQUE KEY `uniq_adj_number` (tenant_id, `adjustment_number`);
RENAME TABLE `stock_adjustments` TO `stock_adjustments__data`;
CREATE VIEW `stock_adjustments` AS SELECT * FROM `stock_adjustments__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `stock_adjustments__stamp` BEFORE INSERT ON `stock_adjustments__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- stock_movements
ALTER TABLE `stock_movements` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_stock_movements_tn` (tenant_id);
RENAME TABLE `stock_movements` TO `stock_movements__data`;
CREATE VIEW `stock_movements` AS SELECT * FROM `stock_movements__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `stock_movements__stamp` BEFORE INSERT ON `stock_movements__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- subcategories
ALTER TABLE `subcategories` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_subcategories_tn` (tenant_id);
ALTER TABLE `subcategories` ADD KEY `idx_uniq_subcategories_category_name_fk` (`category_id`);
ALTER TABLE `subcategories` DROP INDEX `uniq_subcategories_category_name`, ADD UNIQUE KEY `uniq_subcategories_category_name` (tenant_id, `category_id`, `name`);
RENAME TABLE `subcategories` TO `subcategories__data`;
CREATE VIEW `subcategories` AS SELECT * FROM `subcategories__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `subcategories__stamp` BEFORE INSERT ON `subcategories__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- supplier_advances
ALTER TABLE `supplier_advances` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_supplier_advances_tn` (tenant_id);
ALTER TABLE `supplier_advances` DROP INDEX `uniq_supplier_advance_number`, ADD UNIQUE KEY `uniq_supplier_advance_number` (tenant_id, `advance_number`);
RENAME TABLE `supplier_advances` TO `supplier_advances__data`;
CREATE VIEW `supplier_advances` AS SELECT * FROM `supplier_advances__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `supplier_advances__stamp` BEFORE INSERT ON `supplier_advances__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- suppliers
ALTER TABLE `suppliers` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_suppliers_tn` (tenant_id);
ALTER TABLE `suppliers` DROP INDEX `uniq_suppliers_name`, ADD UNIQUE KEY `uniq_suppliers_name` (tenant_id, `name`);
ALTER TABLE `suppliers` DROP INDEX `uniq_suppliers_phone`, ADD UNIQUE KEY `uniq_suppliers_phone` (tenant_id, `phone`);
RENAME TABLE `suppliers` TO `suppliers__data`;
CREATE VIEW `suppliers` AS SELECT * FROM `suppliers__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `suppliers__stamp` BEFORE INSERT ON `suppliers__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- system_settings
ALTER TABLE `system_settings` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_system_settings_tn` (tenant_id);
-- PK has no AUTO_INCREMENT, so it is only unique within one business.
ALTER TABLE `system_settings` DROP PRIMARY KEY, ADD PRIMARY KEY (tenant_id, `setting_key`);
RENAME TABLE `system_settings` TO `system_settings__data`;
CREATE VIEW `system_settings` AS SELECT * FROM `system_settings__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `system_settings__stamp` BEFORE INSERT ON `system_settings__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- user_permission_overrides
ALTER TABLE `user_permission_overrides` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_user_permission_overrides_tn` (tenant_id);
RENAME TABLE `user_permission_overrides` TO `user_permission_overrides__data`;
CREATE VIEW `user_permission_overrides` AS SELECT * FROM `user_permission_overrides__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `user_permission_overrides__stamp` BEFORE INSERT ON `user_permission_overrides__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

-- users
ALTER TABLE `users` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_users_tn` (tenant_id);
ALTER TABLE `users` DROP INDEX `uniq_users_phone`, ADD UNIQUE KEY `uniq_users_phone` (tenant_id, `phone`);
ALTER TABLE `users` DROP INDEX `uniq_users_username`, ADD UNIQUE KEY `uniq_users_username` (tenant_id, `username`);
RENAME TABLE `users` TO `users__data`;
CREATE VIEW `users` AS SELECT * FROM `users__data` WHERE tenant_id = app_tenant();
CREATE TRIGGER `users__stamp` BEFORE INSERT ON `users__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 4. CONFIRM. Expect 54 views, 54 triggers, 1 tenant, 1 superadmin,
--    and your existing rows all visible to tenant 1.
-- ---------------------------------------------------------------------
SET @app_tenant = 1;
SELECT (SELECT COUNT(*) FROM information_schema.views    WHERE table_schema  = DATABASE()) AS views_created,
       (SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE()) AS triggers_created,
       (SELECT COUNT(*) FROM tenants)        AS tenants,
       (SELECT COUNT(*) FROM superadmins)    AS superadmins,
       (SELECT COUNT(*) FROM products)       AS products_seen_by_tenant_1,
       (SELECT COUNT(*) FROM customers)      AS customers_seen_by_tenant_1,
       (SELECT COUNT(*) FROM sales_invoices) AS invoices_seen_by_tenant_1;
