-- =====================================================================
--  NEW TENANT — complete IMS schema in one paste
-- =====================================================================
--  Builds an EMPTY, ready-to-use business database: all 61 migrations in
--  order, then the rows that record them as applied, so a later
--  migrate.php over SSH reports "Nothing to migrate" instead of trying
--  to re-run them.
--
--  ------------------------------------------------------------------
--  HOW TO USE
--    1. Panel -> MySQL -> create an EMPTY database for the new business,
--       e.g.  <prefix>_acme.  Grant the SAME MySQL user full rights.
--    2. phpMyAdmin -> select that NEW EMPTY database.
--    3. SQL tab -> paste this whole file -> Go.
--    4. Register it in the superadmin console (Add tenant), or add a row
--       to the control database's `tenants` table.
--
--  >>> CHECK THE DATABASE SELECTOR says the NEW database before you press
--  >>> Go. Running this against a database that already holds business
--  >>> data will fail on the first existing table - which is the intended
--  >>> safety behaviour, but do not rely on it.
--  ------------------------------------------------------------------
--
--  WHAT YOU GET
--    * the full 58-table schema, completely empty of business data
--    * the seeded defaults every install needs: the 'Generic' brand,
--      the role set (admin / subadmin / cashier) and their permissions,
--      the unit list, and a placeholder business profile
--    * one protected administrator, username 'owner'
--
--  FIRST LOGIN for the new business:
--      username: owner
--      password: the same temporary password as your other tenant
--  >>> Change it immediately, and set the real business details under
--  >>> Settings -> Business Profile.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 0001_init_users.sql
-- ---------------------------------------------------------------------
-- Module #2 Authentication
-- Creates the users table.
-- Bootstrap admin user is created by database/create_admin.php (not in this migration,
-- to keep credentials out of source control).

CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    phone           VARCHAR(20)  NOT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    address         TEXT         DEFAULT NULL,
    username        VARCHAR(150) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('admin', 'subadmin', 'cashier') NOT NULL DEFAULT 'subadmin',
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME     DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_users_username (username),
    KEY idx_users_role (role),
    KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0002_init_categories.sql
-- ---------------------------------------------------------------------
-- Module #3 Categories
-- Top-level product grouping. Sub-categories (module #4) FK to this.

CREATE TABLE categories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    description     VARCHAR(255) DEFAULT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by      INT          DEFAULT NULL,
    updated_by      INT          DEFAULT NULL,
    UNIQUE KEY uniq_categories_name (name),
    KEY idx_categories_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0003_init_subcategories.sql
-- ---------------------------------------------------------------------
-- Module #4 Sub-Categories
-- Belong to a category. Name is unique within a category, not globally.

CREATE TABLE subcategories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category_id     INT          NOT NULL,
    name            VARCHAR(150) NOT NULL,
    description     VARCHAR(255) DEFAULT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by      INT          DEFAULT NULL,
    updated_by      INT          DEFAULT NULL,
    UNIQUE KEY uniq_subcategories_category_name (category_id, name),
    KEY idx_subcategories_status (status),
    CONSTRAINT fk_subcategories_category FOREIGN KEY (category_id) REFERENCES categories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0004_init_brands.sql
-- ---------------------------------------------------------------------
-- Module #5 Brands
-- Replaces the legacy `bill_type` free-string column.
-- Purchase invoices are single-brand; sales invoices may be multi-brand.
-- Products will carry brand_id (added in module #6).
--
-- Seeds the "Generic" fallback brand. Legacy products with blank/unknown
-- bill_type will be migrated under this brand at go-live (per D18).

CREATE TABLE brands (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    description     VARCHAR(255) DEFAULT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by      INT          DEFAULT NULL,
    updated_by      INT          DEFAULT NULL,
    UNIQUE KEY uniq_brands_name (name),
    KEY idx_brands_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System brand. Protected from rename / disable / delete by the controller.
INSERT INTO brands (name, description) VALUES
    ('Generic', 'System fallback brand for un-branded or unclassified products. Do not rename or disable.');

-- ---------------------------------------------------------------------
-- 0005_init_products.sql
-- ---------------------------------------------------------------------
-- Module #6 Products
--
-- Master record for each item the business buys/sells.
-- `quantity_on_hand` and `moving_avg_cost` are maintained by transactional services
-- (purchase, sale, return, write-off) — never edited directly through the product form
-- after creation. The create form accepts an "opening stock" so go-live is possible.
--
-- Cost basis follows D10 (moving average on landed cost). At opening, opening_avg_cost
-- is the per-unit landed cost the user knows from prior books.

CREATE TABLE products (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(150)  NOT NULL,
    brand_id                INT           NOT NULL,
    subcategory_id          INT           NOT NULL,
    unit                    VARCHAR(50)   NOT NULL DEFAULT 'pcs',
    hsn_code                VARCHAR(20)   DEFAULT NULL,
    tax_rate                DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    default_selling_price   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    quantity_on_hand        INT           NOT NULL DEFAULT 0,
    moving_avg_cost         DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    low_stock_threshold     INT           NOT NULL DEFAULT 0,
    description             VARCHAR(500)  DEFAULT NULL,
    status                  ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by              INT           DEFAULT NULL,
    updated_by              INT           DEFAULT NULL,
    UNIQUE KEY uniq_products_name (name),
    KEY idx_products_brand (brand_id),
    KEY idx_products_subcategory (subcategory_id),
    KEY idx_products_status (status),
    KEY idx_products_low_stock (quantity_on_hand, low_stock_threshold),
    CONSTRAINT fk_products_brand
        FOREIGN KEY (brand_id) REFERENCES brands(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_products_subcategory
        FOREIGN KEY (subcategory_id) REFERENCES subcategories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0006_init_suppliers.sql
-- ---------------------------------------------------------------------
-- Module #7 Suppliers
-- Basic master plus optional GSTIN + state (needed for CGST/SGST vs IGST split per D52).

CREATE TABLE suppliers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    phone           VARCHAR(20)  NOT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    address         TEXT         DEFAULT NULL,
    gstin           VARCHAR(15)  DEFAULT NULL,
    state           VARCHAR(100) DEFAULT NULL,
    remarks         VARCHAR(500) DEFAULT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by      INT          DEFAULT NULL,
    updated_by      INT          DEFAULT NULL,
    UNIQUE KEY uniq_suppliers_name (name),
    KEY idx_suppliers_status (status),
    KEY idx_suppliers_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0007_enforce_phone_uniqueness.sql
-- ---------------------------------------------------------------------
-- Enforce phone uniqueness across existing counterparty tables.
-- Per user rule: a phone number identifies one entity within each table.
-- Safe to run now since no business data has been entered.

ALTER TABLE users
    ADD UNIQUE KEY uniq_users_phone (phone);

ALTER TABLE suppliers
    ADD UNIQUE KEY uniq_suppliers_phone (phone);

-- ---------------------------------------------------------------------
-- 0008_init_salesmen.sql
-- ---------------------------------------------------------------------
-- Module #8 Salesmen
--
-- Replaces legacy `decorator_tbl`. Each salesman has a default commission rate (%)
-- that can be overridden per sale line.
--
-- Per D29 a salesman is optionally linked to a customer record (so they can also buy
-- on credit, settled against commission balance). The linked_customer_id FK is added
-- in a later migration once the customers table exists (module #9).

CREATE TABLE salesmen (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    phone               VARCHAR(20)  NOT NULL,
    email               VARCHAR(150) DEFAULT NULL,
    address             TEXT         DEFAULT NULL,
    state               VARCHAR(100) DEFAULT NULL,
    gstin               VARCHAR(15)  DEFAULT NULL,
    commission_rate     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    linked_customer_id  INT          DEFAULT NULL,
    remarks             VARCHAR(500) DEFAULT NULL,
    status              ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT          DEFAULT NULL,
    updated_by          INT          DEFAULT NULL,
    UNIQUE KEY uniq_salesmen_name (name),
    UNIQUE KEY uniq_salesmen_phone (phone),
    KEY idx_salesmen_status (status),
    KEY idx_salesmen_linked_customer (linked_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0009_init_customers.sql
-- ---------------------------------------------------------------------
-- Module #9 Customers
--
-- Creates the customers table, adds the reciprocal FK on salesmen.linked_customer_id
-- (deferred from migration 0008), and retroactively creates mirror customer records
-- for any salesmen that already exist (per D29/D38).

CREATE TABLE customers (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    name                  VARCHAR(150) NOT NULL,
    phone                 VARCHAR(20)  NOT NULL,
    email                 VARCHAR(150) DEFAULT NULL,
    address               TEXT         DEFAULT NULL,
    gstin                 VARCHAR(15)  DEFAULT NULL,
    state                 VARCHAR(100) DEFAULT NULL,
    default_salesman_id   INT          DEFAULT NULL,
    credit_limit          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    linked_salesman_id    INT          DEFAULT NULL,
    remarks               VARCHAR(500) DEFAULT NULL,
    status                ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by            INT          DEFAULT NULL,
    updated_by            INT          DEFAULT NULL,
    UNIQUE KEY uniq_customers_name (name),
    UNIQUE KEY uniq_customers_phone (phone),
    KEY idx_customers_status (status),
    KEY idx_customers_default_salesman (default_salesman_id),
    KEY idx_customers_linked_salesman (linked_salesman_id),
    CONSTRAINT fk_customers_default_salesman
        FOREIGN KEY (default_salesman_id) REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_customers_linked_salesman
        FOREIGN KEY (linked_salesman_id) REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reciprocal FK — salesmen.linked_customer_id → customers.id
ALTER TABLE salesmen
    ADD CONSTRAINT fk_salesmen_linked_customer
    FOREIGN KEY (linked_customer_id) REFERENCES customers(id)
    ON UPDATE CASCADE ON DELETE SET NULL;

-- Retroactive back-fill: mirror every salesman into a customer record.
INSERT INTO customers (name, phone, email, address, state, linked_salesman_id, status, created_by, updated_by)
SELECT name, phone, email, address, state, id, status, created_by, updated_by
FROM salesmen
WHERE linked_customer_id IS NULL;

-- Wire salesmen back to their mirror customer.
UPDATE salesmen s
JOIN customers c ON c.linked_salesman_id = s.id
SET s.linked_customer_id = c.id
WHERE s.linked_customer_id IS NULL;

-- ---------------------------------------------------------------------
-- 0010_init_employees.sql
-- ---------------------------------------------------------------------
-- Module #10 Employees
--
-- Master record for staff. Per D41/D44:
--   - Optional `linked_user_id` for employees who also log into the system
--   - `linked_customer_id` so they can buy on credit, settled against salary
--   - Status = active / exited (different from generic active/inactive)
--   - exit_date captured when marked exited
--
-- Adds the reciprocal `linked_employee_id` on customers (mirrors linked_salesman_id).

CREATE TABLE employees (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    name                        VARCHAR(150) NOT NULL,
    phone                       VARCHAR(20)  NOT NULL,
    email                       VARCHAR(150) DEFAULT NULL,
    address                     TEXT         DEFAULT NULL,
    state                       VARCHAR(100) DEFAULT NULL,
    designation                 VARCHAR(150) NOT NULL,
    joining_date                DATE         NOT NULL,
    exit_date                   DATE         DEFAULT NULL,
    salary_amount               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    id_proof_type               VARCHAR(50)  DEFAULT NULL,
    id_proof_number             VARCHAR(50)  DEFAULT NULL,
    pan                         VARCHAR(10)  DEFAULT NULL,
    aadhaar                     VARCHAR(12)  DEFAULT NULL,
    bank_account_number         VARCHAR(30)  DEFAULT NULL,
    bank_ifsc                   VARCHAR(11)  DEFAULT NULL,
    bank_branch                 VARCHAR(150) DEFAULT NULL,
    bank_account_holder_name    VARCHAR(150) DEFAULT NULL,
    linked_user_id              INT          DEFAULT NULL,
    linked_customer_id          INT          DEFAULT NULL,
    remarks                     VARCHAR(500) DEFAULT NULL,
    status                      ENUM('active', 'exited') NOT NULL DEFAULT 'active',
    created_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by                  INT          DEFAULT NULL,
    updated_by                  INT          DEFAULT NULL,
    UNIQUE KEY uniq_employees_name (name),
    UNIQUE KEY uniq_employees_phone (phone),
    KEY idx_employees_status (status),
    KEY idx_employees_designation (designation),
    KEY idx_employees_linked_user (linked_user_id),
    KEY idx_employees_linked_customer (linked_customer_id),
    CONSTRAINT fk_employees_linked_user
        FOREIGN KEY (linked_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_employees_linked_customer
        FOREIGN KEY (linked_customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reciprocal link on customers
ALTER TABLE customers
    ADD COLUMN linked_employee_id INT DEFAULT NULL AFTER linked_salesman_id;

ALTER TABLE customers
    ADD KEY idx_customers_linked_employee (linked_employee_id);

ALTER TABLE customers
    ADD CONSTRAINT fk_customers_linked_employee
    FOREIGN KEY (linked_employee_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- 0011_init_system_settings.sql
-- ---------------------------------------------------------------------
-- Module #11.5 System Settings
--
-- business_profile:     singleton (id=1 always). Used on invoice headers, for determining
--                       intra-state vs inter-state GST (per D52), and for the financial year.
-- system_settings:      key/value table for system-wide toggles.

CREATE TABLE business_profile (
    id              INT PRIMARY KEY,
    legal_name      VARCHAR(250) NOT NULL,
    display_name    VARCHAR(150) NOT NULL,
    gstin           VARCHAR(15)  DEFAULT NULL,
    state           VARCHAR(100) NOT NULL,
    address         TEXT         DEFAULT NULL,
    phone           VARCHAR(20)  DEFAULT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    financial_year_start_month TINYINT NOT NULL DEFAULT 4,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by      INT          DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed a placeholder row so the settings form always has something to load.
INSERT INTO business_profile (id, legal_name, display_name, state, financial_year_start_month)
VALUES (1, 'Your Business Legal Name', 'GP ENTERPRISE', 'West Bengal', 4);

CREATE TABLE system_settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    value         TEXT         NOT NULL,
    description   VARCHAR(255) DEFAULT NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by    INT          DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeded defaults. Values are strings (cast in code where needed).
INSERT INTO system_settings (setting_key, value, description) VALUES
    ('commission_accrues_on', 'invoice_finalization',
        'When commission posts: invoice_finalization | invoice_payment | manual_only'),
    ('enable_round_off', '1',
        'Round invoice grand total to nearest rupee (1 = yes, 0 = no)'),
    ('low_stock_dashboard_widget', '1',
        'Show low-stock items on dashboard (1 = yes, 0 = no)');

-- ---------------------------------------------------------------------
-- 0012_init_purchase_invoices.sql
-- ---------------------------------------------------------------------
-- Module #12 Purchase Invoices
--
-- Three tables: header (purchase_invoices), lines (purchase_invoice_lines),
-- and expenses (purchase_invoice_expenses). Header enforces single-brand per invoice (D14).
-- Landed cost (D10/D11) is computed at finalize and stored per line.
--
-- Status flow: draft -> finalized. Cancelled only works on drafts in v1.
-- (Reverse-and-reapply on finalized is a deferred v2 feature.)

CREATE TABLE purchase_invoices (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number              VARCHAR(100) NOT NULL,
    supplier_id                 INT          NOT NULL,
    brand_id                    INT          NOT NULL,
    invoice_date                DATE         NOT NULL,
    bill_format                 ENUM('per_line_tax', 'discount_then_tax') NOT NULL DEFAULT 'per_line_tax',
    tax_treatment               ENUM('exclusive', 'inclusive', 'no_tax')  NOT NULL DEFAULT 'exclusive',
    gst_claimable               TINYINT(1)   NOT NULL DEFAULT 1,
    invoice_level_tax_rate      DECIMAL(5,2) NOT NULL DEFAULT 0.00,  -- used only in discount_then_tax mode
    is_intra_state              TINYINT(1)   NOT NULL DEFAULT 1,
    subtotal                    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_tax                   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_sgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_igst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_expenses              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_expense_tax           DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    round_off_amount            DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    grand_total                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    paid_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    due_amount                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    payment_status              ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
    remarks                     VARCHAR(500) DEFAULT NULL,
    status                      ENUM('draft', 'finalized', 'cancelled') NOT NULL DEFAULT 'draft',
    finalized_at                DATETIME     DEFAULT NULL,
    finalized_by                INT          DEFAULT NULL,
    cancelled_at                DATETIME     DEFAULT NULL,
    cancelled_by                INT          DEFAULT NULL,
    created_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by                  INT          DEFAULT NULL,
    updated_by                  INT          DEFAULT NULL,
    UNIQUE KEY uniq_purchase_invoice_supplier_number (supplier_id, invoice_number),
    KEY idx_purchase_invoices_supplier (supplier_id),
    KEY idx_purchase_invoices_brand (brand_id),
    KEY idx_purchase_invoices_status (status),
    KEY idx_purchase_invoices_date (invoice_date),
    KEY idx_purchase_invoices_payment (payment_status),
    CONSTRAINT fk_purchase_invoices_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_purchase_invoices_brand    FOREIGN KEY (brand_id) REFERENCES brands(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_invoice_lines (
    id                              INT AUTO_INCREMENT PRIMARY KEY,
    purchase_invoice_id             INT           NOT NULL,
    line_no                         INT           NOT NULL DEFAULT 1,
    product_id                      INT           NOT NULL,
    quantity                        DECIMAL(12,3) NOT NULL,
    unit_price                      DECIMAL(12,4) NOT NULL,
    addsub_percent                  DECIMAL(6,2)  NOT NULL DEFAULT 0.00,   -- D1: negative = discount
    subtotal                        DECIMAL(14,2) NOT NULL DEFAULT 0.00,   -- qty * price * (1 + addsub/100)
    tax_rate                        DECIMAL(5,2)  NOT NULL DEFAULT 0.00,   -- used in per_line_tax mode
    tax_amount                      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cgst_amount                     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    sgst_amount                     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    igst_amount                     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total                      DECIMAL(14,2) NOT NULL DEFAULT 0.00,   -- subtotal + tax_amount
    allocated_expense_share         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    allocated_expense_tax_share     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    landed_cost_per_unit            DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    created_at                      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pil_invoice (purchase_invoice_id),
    KEY idx_pil_product (product_id),
    CONSTRAINT fk_pil_invoice FOREIGN KEY (purchase_invoice_id) REFERENCES purchase_invoices(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_pil_product FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_invoice_expenses (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    purchase_invoice_id     INT           NOT NULL,
    expense_type            VARCHAR(100)  NOT NULL,
    amount                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_rate                DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    tax_amount              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_with_tax          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    allocation_method       ENUM('by_value', 'by_quantity', 'equal_split') NOT NULL DEFAULT 'by_value',
    remarks                 VARCHAR(255)  DEFAULT NULL,
    created_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pie_invoice (purchase_invoice_id),
    CONSTRAINT fk_pie_invoice FOREIGN KEY (purchase_invoice_id) REFERENCES purchase_invoices(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0013_init_purchase_payments.sql
-- ---------------------------------------------------------------------
-- Module #13 Purchase Payments
--
-- Split-tender payment against a finalized purchase invoice. A single payment
-- row can carry cash + online + cheque portions simultaneously (per D34).
--
-- Bank-account linkage and cashier-drawer linkage are deferred to Phase 4 when
-- the Cash Book / Bank Book modules are built. For now we capture the amounts
-- and, for cheques, the minimum lifecycle status. Payment totals on the parent
-- purchase_invoices.paid_amount / due_amount / payment_status are kept in sync
-- by PurchasePaymentService on every insert / delete / cheque-status change.

CREATE TABLE purchase_payments (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    purchase_invoice_id     INT            NOT NULL,
    payment_date            DATE           NOT NULL,
    cash_amount             DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    online_amount           DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    online_reference        VARCHAR(100)   DEFAULT NULL,
    cheque_amount           DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    cheque_number           VARCHAR(50)    DEFAULT NULL,
    cheque_date             DATE           DEFAULT NULL,
    cheque_bank_name        VARCHAR(150)   DEFAULT NULL,
    cheque_status           ENUM('pending', 'cleared', 'bounced', 'cancelled')
                            NOT NULL DEFAULT 'pending',
    total_amount            DECIMAL(12,2)  NOT NULL,
    remarks                 VARCHAR(500)   DEFAULT NULL,
    created_at              TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by              INT            DEFAULT NULL,
    updated_by              INT            DEFAULT NULL,
    KEY idx_pp_invoice (purchase_invoice_id),
    KEY idx_pp_date (payment_date),
    KEY idx_pp_cheque_status (cheque_status),
    CONSTRAINT fk_pp_invoice FOREIGN KEY (purchase_invoice_id) REFERENCES purchase_invoices(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0014_add_latest_purchase_price_to_products.sql
-- ---------------------------------------------------------------------
-- Track each product's latest purchase landed cost + when it was last seen.
-- Updated by InventoryService on every purchase stock-in event.
-- Used for current-market-value calculations and (later) composite product pricing.

ALTER TABLE products
    ADD COLUMN latest_purchase_landed_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER moving_avg_cost,
    ADD COLUMN latest_purchase_at DATETIME DEFAULT NULL AFTER latest_purchase_landed_cost;

-- ---------------------------------------------------------------------
-- 0015_init_sales_invoices.sql
-- ---------------------------------------------------------------------
-- Module #14 Sales Invoices
--
-- Two tables: header (sales_invoices) and lines (sales_invoice_lines).
-- Sales invoices are MULTI-brand (unlike purchases which are single-brand per D14).
-- Per-line salesman attribution per D58. COGS snapshot per line per D54 — for
-- sales-return cost basis. Commission snapshot per line per D62 — for ledger.
--
-- Sales invoice numbers are auto-generated at finalize time in the format
-- "SI/{FY}/{NNNNN}" where FY is the fiscal year derived from invoice_date and
-- business_profile.financial_year_start_month. Drafts have empty invoice_number.

CREATE TABLE sales_invoices (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number              VARCHAR(100) NOT NULL DEFAULT '',
    customer_id                 INT          NOT NULL,
    invoice_date                DATE         NOT NULL,
    bill_format                 ENUM('per_line_tax', 'discount_then_tax') NOT NULL DEFAULT 'per_line_tax',
    tax_treatment               ENUM('exclusive', 'inclusive', 'no_tax')  NOT NULL DEFAULT 'exclusive',
    invoice_level_tax_rate      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    is_intra_state              TINYINT(1)   NOT NULL DEFAULT 1,
    bill_type                   ENUM('tax_invoice', 'bill_of_supply') NOT NULL DEFAULT 'tax_invoice',
    header_salesman_id          INT          DEFAULT NULL,
    subtotal                    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_tax                   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_sgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_igst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cogs                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_commission            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    round_off_amount            DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    grand_total                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    paid_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    due_amount                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    payment_status              ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
    remarks                     VARCHAR(500) DEFAULT NULL,
    status                      ENUM('draft', 'finalized', 'cancelled') NOT NULL DEFAULT 'draft',
    finalized_at                DATETIME     DEFAULT NULL,
    finalized_by                INT          DEFAULT NULL,
    cancelled_at                DATETIME     DEFAULT NULL,
    cancelled_by                INT          DEFAULT NULL,
    created_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by                  INT          DEFAULT NULL,
    updated_by                  INT          DEFAULT NULL,
    UNIQUE KEY uniq_sales_invoice_number (invoice_number),
    KEY idx_si_customer (customer_id),
    KEY idx_si_status (status),
    KEY idx_si_payment (payment_status),
    KEY idx_si_date (invoice_date),
    KEY idx_si_header_salesman (header_salesman_id),
    CONSTRAINT fk_si_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_si_header_salesman FOREIGN KEY (header_salesman_id) REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_invoice_lines (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    sales_invoice_id        INT           NOT NULL,
    line_no                 INT           NOT NULL DEFAULT 1,
    product_id              INT           NOT NULL,
    display_name            VARCHAR(255)  NOT NULL,            -- D63: defaults to product.name, overridable per line
    salesman_id             INT           DEFAULT NULL,         -- D58 per-line attribution (null = direct sale)
    quantity                DECIMAL(12,3) NOT NULL,
    unit_price              DECIMAL(12,4) NOT NULL,
    addsub_percent          DECIMAL(6,2)  NOT NULL DEFAULT 0.00, -- D1 signed (sales convention positive = commission)
    subtotal                DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_rate                DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    tax_amount              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cgst_amount             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    sgst_amount             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    igst_amount             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cogs_per_unit           DECIMAL(14,4) NOT NULL DEFAULT 0.0000,  -- D54: snapshot moving_avg at finalize time
    cogs_total              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    commission_rate         DECIMAL(5,2)  NOT NULL DEFAULT 0.00,    -- snapshot of salesman.commission_rate at finalize
    commission_amount       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sil_invoice (sales_invoice_id),
    KEY idx_sil_product (product_id),
    KEY idx_sil_salesman (salesman_id),
    CONSTRAINT fk_sil_invoice  FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_sil_product  FOREIGN KEY (product_id)       REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sil_salesman FOREIGN KEY (salesman_id)      REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0016_init_sales_payments.sql
-- ---------------------------------------------------------------------
-- Module #15 Sales Payments
--
-- Mirror of purchase_payments on the sales side. Split-tender payment against a
-- finalized sales invoice. Cheque lifecycle identical (pending / cleared /
-- bounced / cancelled). SalesPaymentService keeps sales_invoices.paid_amount /
-- due_amount / payment_status in sync on every mutation.

CREATE TABLE sales_payments (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    sales_invoice_id        INT            NOT NULL,
    payment_date            DATE           NOT NULL,
    cash_amount             DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    online_amount           DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    online_reference        VARCHAR(100)   DEFAULT NULL,
    cheque_amount           DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    cheque_number           VARCHAR(50)    DEFAULT NULL,
    cheque_date             DATE           DEFAULT NULL,
    cheque_bank_name        VARCHAR(150)   DEFAULT NULL,
    cheque_status           ENUM('pending', 'cleared', 'bounced', 'cancelled')
                            NOT NULL DEFAULT 'pending',
    total_amount            DECIMAL(12,2)  NOT NULL,
    remarks                 VARCHAR(500)   DEFAULT NULL,
    created_at              TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by              INT            DEFAULT NULL,
    updated_by              INT            DEFAULT NULL,
    KEY idx_sp_invoice (sales_invoice_id),
    KEY idx_sp_date (payment_date),
    KEY idx_sp_cheque_status (cheque_status),
    CONSTRAINT fk_sp_invoice FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0017_init_product_compositions.sql
-- ---------------------------------------------------------------------
-- Module #14.5 Turn 1 — Product compositions (BOM / recipes)
--
-- Adds per-product composite flags + two new tables for recipes.
--
-- Flags on products (all default 0):
--   is_composite        — 1 = this product is a composite (has a recipe)
--   is_irreversible     — 1 = physical composite (needs Production Orders; has own stock)
--                         0 = virtual composite (no own stock; sale deducts components)
--   expose_components   — 1 = print fine-print component listing on the bill  (D68)
--
-- Recipe storage:
--   product_compositions       — one row per composite product (header)
--   product_composition_items  — many rows per composition (components × quantities)
--
-- Validation rule: a composite product cannot reference itself as a component.
-- Enforced at the application layer by ProductCompositionRepository.

ALTER TABLE products
    ADD COLUMN is_composite      TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN is_irreversible   TINYINT(1) NOT NULL DEFAULT 0 AFTER is_composite,
    ADD COLUMN expose_components TINYINT(1) NOT NULL DEFAULT 0 AFTER is_irreversible;

ALTER TABLE products
    ADD KEY idx_products_is_composite (is_composite);

CREATE TABLE product_compositions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT          NOT NULL,
    notes           VARCHAR(500) DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by      INT          DEFAULT NULL,
    updated_by      INT          DEFAULT NULL,
    UNIQUE KEY uniq_pc_product (product_id),
    CONSTRAINT fk_pc_product FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_composition_items (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    composition_id          INT           NOT NULL,
    component_product_id    INT           NOT NULL,
    quantity                DECIMAL(12,3) NOT NULL,
    notes                   VARCHAR(255)  DEFAULT NULL,
    created_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pci_composition (composition_id),
    KEY idx_pci_component (component_product_id),
    CONSTRAINT fk_pci_composition FOREIGN KEY (composition_id) REFERENCES product_compositions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_pci_component   FOREIGN KEY (component_product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0018_init_production_orders.sql
-- ---------------------------------------------------------------------
-- Module #14.5 Turn 2 — Production Orders
--
-- Atomic events that consume components (stock-out) and produce composite stock
-- (stock-in at computed moving-avg landed cost, per D10/D67).
--
-- No draft/cancel states in v1 — production is immutable once recorded. To fix a
-- mistake, record a correcting production or a write-off (module coming later).
--
-- Order numbering: PR/{FY}/{NNNNN}, analogous to sales invoice numbering.

CREATE TABLE production_orders (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    order_number            VARCHAR(100)  NOT NULL DEFAULT '',
    product_id              INT           NOT NULL,
    produced_date           DATE          NOT NULL,
    quantity_produced       DECIMAL(12,3) NOT NULL,
    total_component_cost    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    landed_cost_per_unit    DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    notes                   VARCHAR(500)  DEFAULT NULL,
    created_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by              INT           DEFAULT NULL,
    updated_by              INT           DEFAULT NULL,
    UNIQUE KEY uniq_po_order_number (order_number),
    KEY idx_po_product (product_id),
    KEY idx_po_date (produced_date),
    CONSTRAINT fk_po_product FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snapshot of the recipe at production time. Frozen — editing the parent recipe
-- later does not change historical production orders (D67 frozen-recipe rule).
CREATE TABLE production_order_lines (
    id                              INT AUTO_INCREMENT PRIMARY KEY,
    production_order_id             INT           NOT NULL,
    component_product_id            INT           NOT NULL,
    component_name                  VARCHAR(255)  NOT NULL,
    component_unit                  VARCHAR(50)   NOT NULL,
    recipe_quantity                 DECIMAL(12,3) NOT NULL,   -- per 1 unit of composite
    quantity_consumed               DECIMAL(12,3) NOT NULL,   -- recipe_quantity × order.quantity_produced
    moving_avg_cost_at_production   DECIMAL(14,4) NOT NULL,
    line_cost                       DECIMAL(14,2) NOT NULL,
    created_at                      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pol_order (production_order_id),
    KEY idx_pol_component (component_product_id),
    CONSTRAINT fk_pol_order     FOREIGN KEY (production_order_id) REFERENCES production_orders(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_pol_component FOREIGN KEY (component_product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0019_sales_lines_composite_support.sql
-- ---------------------------------------------------------------------
-- Module #14.5 Turn 3 — Composite-aware sales
--
-- A sales line can now be:
--   (a) a simple product          — product_id set, is_composite_line=0
--   (b) a saved Irreversible      — product_id set, is_composite_line=0 (behaves like simple: own stock)
--   (c) a saved Reversible        — product_id set, is_composite_line=1 (expand recipe, deduct components)
--   (d) an ad-hoc composite       — product_id NULL, is_composite_line=1 (inline components)
--
-- Component snapshots for (c) and (d) live in sales_invoice_line_components.
-- The recipe is snapshotted at line-add time; component COGS is filled in at finalize.

ALTER TABLE sales_invoice_lines
    MODIFY product_id INT DEFAULT NULL;

ALTER TABLE sales_invoice_lines
    ADD COLUMN is_composite_line TINYINT(1) NOT NULL DEFAULT 0 AFTER product_id;

CREATE TABLE sales_invoice_line_components (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    sales_invoice_line_id   INT           NOT NULL,
    component_product_id    INT           NOT NULL,
    component_name          VARCHAR(255)  NOT NULL,
    component_unit          VARCHAR(50)   NOT NULL,
    recipe_quantity         DECIMAL(12,3) NOT NULL,
    quantity_consumed       DECIMAL(12,3) NOT NULL,
    cogs_per_unit           DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    cogs_total              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_silc_line (sales_invoice_line_id),
    KEY idx_silc_component (component_product_id),
    CONSTRAINT fk_silc_line      FOREIGN KEY (sales_invoice_line_id) REFERENCES sales_invoice_lines(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_silc_component FOREIGN KEY (component_product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0020_init_product_price_history.sql
-- ---------------------------------------------------------------------
-- Time-series log of every product latest-landed-cost change.
--
-- Rows are inserted by:
--   - ProductController::store          when an opening Latest Cost is given (source = 'opening')
--   - InventoryService::applyStockIn    on purchase / production / sales-return stock-in (source = 'purchase'/'production'/'return')
--   - InventoryService::setLatestPrice  on manual admin update          (source = 'manual')
--
-- source_ref_type + source_ref_id are polymorphic pointers to the triggering
-- event (e.g. purchase_invoice_line id, production_order id). Both are optional.

CREATE TABLE product_price_history (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    product_id          INT           NOT NULL,
    landed_cost         DECIMAL(14,4) NOT NULL,
    source              ENUM('opening', 'purchase', 'production', 'return', 'manual', 'other') NOT NULL DEFAULT 'other',
    source_ref_type     VARCHAR(50)   DEFAULT NULL,
    source_ref_id       INT           DEFAULT NULL,
    notes               VARCHAR(500)  DEFAULT NULL,
    effective_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          INT           DEFAULT NULL,
    KEY idx_pph_product (product_id),
    KEY idx_pph_effective (effective_at),
    KEY idx_pph_source (source),
    CONSTRAINT fk_pph_product FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0021_sales_invoice_number_nullable.sql
-- ---------------------------------------------------------------------
-- Draft sales invoices have no invoice number yet — it is auto-assigned at finalize.
-- Previously we stored '' for drafts, which violates the UNIQUE constraint after
-- the second draft. Making the column nullable lets multiple drafts coexist
-- (MySQL allows multiple NULL values in a UNIQUE index).
--
-- Same latent issue on production_orders.order_number (insert '' then UPDATE to the
-- real PR number — fine normally but collides under concurrent inserts). Applying
-- the same nullable fix preemptively.

ALTER TABLE sales_invoices MODIFY invoice_number VARCHAR(100) DEFAULT NULL;
UPDATE sales_invoices SET invoice_number = NULL WHERE invoice_number = '';

ALTER TABLE production_orders MODIFY order_number VARCHAR(100) DEFAULT NULL;
UPDATE production_orders SET order_number = NULL WHERE order_number = '';

-- ---------------------------------------------------------------------
-- 0022_sales_invoice_final_discount.sql
-- ---------------------------------------------------------------------
-- Flat-amount discount applied AFTER subtotal + tax (round-off-style).
-- Typical Indian retail usage: "Total 3150, final discount 50, pay 3100".
-- Works with any bill_format / tax_treatment.

ALTER TABLE sales_invoices
    ADD COLUMN final_discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total_commission;

-- ---------------------------------------------------------------------
-- 0023_sales_invoice_extras.sql
-- ---------------------------------------------------------------------
-- Sales invoice extras (Delivery, Packing, Loading, etc.)
-- Multi-row. Mode A (discount_then_tax): per-row is_taxable toggle; tax_rate unused.
-- Mode B (per_line_tax): per-row tax_rate; is_taxable unused.
-- Final Discount applies to Mode A only (D-new); invoice_level_tax_rate also Mode A only.

CREATE TABLE sales_invoice_extras (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    sales_invoice_id    INT           NOT NULL,
    label               VARCHAR(100)  NOT NULL,
    amount              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_rate            DECIMAL(5,2)  NOT NULL DEFAULT 0.00,   -- Mode B only
    tax_amount          DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- computed on finalize
    is_taxable          TINYINT(1)    NOT NULL DEFAULT 0,      -- Mode A only
    sort_order          INT           NOT NULL DEFAULT 0,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sie_invoice (sales_invoice_id),
    CONSTRAINT fk_sie_invoice FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snapshot totals on header so reports don't need to re-sum extras.
ALTER TABLE sales_invoices
    ADD COLUMN total_extras       DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_commission,
    ADD COLUMN total_extras_tax   DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_extras;

-- ---------------------------------------------------------------------
-- 0024_sales_invoice_extras_drop_is_taxable.sql
-- ---------------------------------------------------------------------
-- Unified extras model: every extra row carries its own tax_rate.
-- The earlier Mode-A-only is_taxable toggle is gone. Drop the column.

ALTER TABLE sales_invoice_extras
    DROP COLUMN is_taxable;

-- ---------------------------------------------------------------------
-- 0025_purchase_invoice_final_discount.sql
-- ---------------------------------------------------------------------
-- Mirror of the sales-side final_discount on purchase invoices.
-- Applies in Mode A (discount_then_tax) only. Reduces the lines' taxable base before
-- invoice-level tax; expenses remain independent (their own tax, landed-cost allocated).

ALTER TABLE purchase_invoices
    ADD COLUMN final_discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER invoice_level_tax_rate;

-- ---------------------------------------------------------------------
-- 0026_init_sales_returns.sql
-- ---------------------------------------------------------------------
-- Module #16 Sales Returns
--
-- Three tables: header (sales_returns), lines (sales_return_lines), and per-line
-- component reversal records for composite lines (sales_return_line_components).
--
-- Core rules:
--   - Stock re-enters at the SNAPSHOTTED cogs_per_unit of the originating sale
--     line (D54 — no cost-basis drift). Composite components reverse using the
--     per-component cogs_per_unit_at_sale snapshot.
--   - Per-line commission is reversed (D62) — negative entry, recorded on the
--     return line so the future commission ledger can pick it up.
--   - Refund flow v1 is "credit_note": reduces customer due on the original
--     invoice and posts a negative sales_payments row for traceability.
--   - Tax math mirrors the original sale's bill_format / tax_treatment.
--   - Final discount + extras are pro-rated by qty_returned / qty_original.
--
-- Status flow: draft -> finalized. Cancelled only on drafts in v1.

CREATE TABLE sales_returns (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    return_number               VARCHAR(100) DEFAULT NULL,                -- assigned at finalize, null on draft
    original_sales_invoice_id   INT          NOT NULL,
    customer_id                 INT          NOT NULL,
    return_date                 DATE         NOT NULL,
    reason                      VARCHAR(500) DEFAULT NULL,
    refund_method               ENUM('credit_note', 'cash')  NOT NULL DEFAULT 'credit_note',
    -- Snapshot of the original invoice's tax shape (so the math is reproducible
    -- even if someone later edits the originating row, which we don't allow today).
    bill_format                 ENUM('per_line_tax', 'discount_then_tax') NOT NULL DEFAULT 'per_line_tax',
    tax_treatment               ENUM('exclusive', 'inclusive', 'no_tax')  NOT NULL DEFAULT 'exclusive',
    is_intra_state              TINYINT(1)   NOT NULL DEFAULT 1,
    invoice_level_tax_rate      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    -- Header totals (computed at finalize).
    subtotal                    DECIMAL(14,2) NOT NULL DEFAULT 0.00,       -- sum of returned line subtotals
    final_discount_share        DECIMAL(14,2) NOT NULL DEFAULT 0.00,       -- prorated share of original final_discount
    extras_share                DECIMAL(14,2) NOT NULL DEFAULT 0.00,       -- prorated share of original extras (excl. extras tax)
    extras_tax_share            DECIMAL(14,2) NOT NULL DEFAULT 0.00,       -- prorated share of original extras tax
    total_tax                   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_sgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_igst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cogs                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,       -- cost of goods coming back into stock
    total_commission_reversed   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    round_off_amount            DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    grand_total                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,       -- amount the customer's due is reduced by
    remarks                     VARCHAR(500) DEFAULT NULL,
    status                      ENUM('draft', 'finalized', 'cancelled') NOT NULL DEFAULT 'draft',
    finalized_at                DATETIME     DEFAULT NULL,
    finalized_by                INT          DEFAULT NULL,
    cancelled_at                DATETIME     DEFAULT NULL,
    cancelled_by                INT          DEFAULT NULL,
    created_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by                  INT          DEFAULT NULL,
    updated_by                  INT          DEFAULT NULL,
    UNIQUE KEY uniq_sales_return_number (return_number),
    KEY idx_sr_invoice (original_sales_invoice_id),
    KEY idx_sr_customer (customer_id),
    KEY idx_sr_status (status),
    KEY idx_sr_date (return_date),
    CONSTRAINT fk_sr_invoice  FOREIGN KEY (original_sales_invoice_id) REFERENCES sales_invoices(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sr_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_return_lines (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    sales_return_id             INT           NOT NULL,
    original_sales_line_id      INT           NOT NULL,
    line_no                     INT           NOT NULL DEFAULT 1,
    -- Snapshot from the originating sale line (so display + math is reproducible).
    product_id                  INT           DEFAULT NULL,
    is_composite_line           TINYINT(1)    NOT NULL DEFAULT 0,
    display_name                VARCHAR(255)  NOT NULL,
    salesman_id                 INT           DEFAULT NULL,
    quantity_returned           DECIMAL(12,3) NOT NULL,
    unit_price                  DECIMAL(12,4) NOT NULL,
    addsub_percent              DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    -- Computed (mirrors line math; tax behaves per the sale's bill_format).
    subtotal                    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_rate                    DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    tax_amount                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cgst_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    sgst_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    igst_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    -- Snapshots from the originating sale line for cost reversal / commission reversal.
    cogs_per_unit               DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    cogs_total                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    commission_rate             DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    commission_reversed         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_srl_return (sales_return_id),
    KEY idx_srl_orig_line (original_sales_line_id),
    KEY idx_srl_product (product_id),
    KEY idx_srl_salesman (salesman_id),
    CONSTRAINT fk_srl_return      FOREIGN KEY (sales_return_id) REFERENCES sales_returns(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_srl_orig_line   FOREIGN KEY (original_sales_line_id) REFERENCES sales_invoice_lines(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_srl_product     FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_srl_salesman    FOREIGN KEY (salesman_id) REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_return_line_components (
    id                              INT AUTO_INCREMENT PRIMARY KEY,
    sales_return_line_id            INT           NOT NULL,
    original_component_id           INT           NOT NULL,                   -- FK to sales_invoice_line_components
    component_product_id            INT           NOT NULL,
    component_name                  VARCHAR(255)  NOT NULL,
    component_unit                  VARCHAR(50)   NOT NULL,
    quantity_returned               DECIMAL(12,3) NOT NULL,                   -- recipe_qty × qty_returned (line)
    cogs_per_unit                   DECIMAL(14,4) NOT NULL DEFAULT 0.0000,    -- snapshot from sales_invoice_line_components.cogs_per_unit
    cogs_total                      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at                      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_srlc_line (sales_return_line_id),
    KEY idx_srlc_component (component_product_id),
    CONSTRAINT fk_srlc_line       FOREIGN KEY (sales_return_line_id)  REFERENCES sales_return_lines(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_srlc_orig_comp  FOREIGN KEY (original_component_id) REFERENCES sales_invoice_line_components(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_srlc_product    FOREIGN KEY (component_product_id)  REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0027_init_purchase_returns.sql
-- ---------------------------------------------------------------------
-- Module #14 Purchase Returns
--
-- Two tables: header (purchase_returns) and lines (purchase_return_lines).
-- No composite reversal (composites are a sales/production concept) and no commission.
--
-- Core rules:
--   - Stock-OUT at CURRENT moving_avg cost (D10) — uses InventoryService::applyStockOut.
--     This is asymmetric vs sales-return (which uses a snapshotted cost) and is the plan-locked
--     behavior: "Sale, purchase-return, write-off use current moving avg as COGS/cost-out."
--   - Tax math mirrors the originating purchase invoice's bill_format / tax_treatment.
--   - Final discount + expenses are pro-rated by qty_returned / qty_original (sum across lines).
--   - Refund flow v1 = "credit_note": reduces supplier's due on the original invoice.
--     Cash refund + universal-ledger postings deferred (see futurescope.md).
--
-- Status flow: draft -> finalized. Cancelled only on drafts in v1.

CREATE TABLE purchase_returns (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    return_number               VARCHAR(100) DEFAULT NULL,                -- assigned at finalize
    original_purchase_invoice_id INT         NOT NULL,
    supplier_id                 INT          NOT NULL,
    return_date                 DATE         NOT NULL,
    reason                      VARCHAR(500) DEFAULT NULL,
    refund_method               ENUM('credit_note', 'cash')  NOT NULL DEFAULT 'credit_note',
    -- Snapshot of the originating invoice's tax shape so the math is reproducible.
    bill_format                 ENUM('per_line_tax', 'discount_then_tax') NOT NULL DEFAULT 'per_line_tax',
    tax_treatment               ENUM('exclusive', 'inclusive', 'no_tax')  NOT NULL DEFAULT 'exclusive',
    is_intra_state              TINYINT(1)   NOT NULL DEFAULT 1,
    invoice_level_tax_rate      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    gst_claimable               TINYINT(1)   NOT NULL DEFAULT 1,
    -- Header totals (computed at finalize).
    subtotal                    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    final_discount_share        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expenses_share              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expenses_tax_share          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_tax                   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_sgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_igst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_stock_out_value       DECIMAL(14,2) NOT NULL DEFAULT 0.00,      -- qty × current moving_avg (cost flowing out)
    round_off_amount            DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    grand_total                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,      -- amount supplier's payable is reduced by
    remarks                     VARCHAR(500) DEFAULT NULL,
    status                      ENUM('draft', 'finalized', 'cancelled') NOT NULL DEFAULT 'draft',
    finalized_at                DATETIME     DEFAULT NULL,
    finalized_by                INT          DEFAULT NULL,
    cancelled_at                DATETIME     DEFAULT NULL,
    cancelled_by                INT          DEFAULT NULL,
    created_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by                  INT          DEFAULT NULL,
    updated_by                  INT          DEFAULT NULL,
    UNIQUE KEY uniq_purchase_return_number (return_number),
    KEY idx_pr_invoice (original_purchase_invoice_id),
    KEY idx_pr_supplier (supplier_id),
    KEY idx_pr_status (status),
    KEY idx_pr_date (return_date),
    CONSTRAINT fk_pr_invoice  FOREIGN KEY (original_purchase_invoice_id) REFERENCES purchase_invoices(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pr_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_return_lines (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    purchase_return_id          INT           NOT NULL,
    original_purchase_line_id   INT           NOT NULL,
    line_no                     INT           NOT NULL DEFAULT 1,
    -- Snapshot from the originating purchase line.
    product_id                  INT           NOT NULL,
    quantity_returned           DECIMAL(12,3) NOT NULL,
    unit_price                  DECIMAL(12,4) NOT NULL,
    addsub_percent              DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    -- Computed (mirrors line math).
    subtotal                    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_rate                    DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    tax_amount                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cgst_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    sgst_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    igst_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    -- Cost flowing out, captured at finalize-time using current moving_avg (D10).
    stock_out_cost_per_unit     DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    stock_out_cost_total        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_prl_return (purchase_return_id),
    KEY idx_prl_orig_line (original_purchase_line_id),
    KEY idx_prl_product (product_id),
    CONSTRAINT fk_prl_return    FOREIGN KEY (purchase_return_id) REFERENCES purchase_returns(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_prl_orig_line FOREIGN KEY (original_purchase_line_id) REFERENCES purchase_invoice_lines(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prl_product   FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0028_init_bank_accounts_drawers_transfers.sql
-- ---------------------------------------------------------------------
-- Phase 4 spine: Bank Accounts + Cashier Drawers + Inter-account Transfers (D26 / D40 / N31)
--
-- Each master carries its own current_balance, mutated atomically by the transfer service
-- (and, in future, by sales/purchase payment links and operating-expense postings).
-- Universal Passbook (#24) will read these masters as ledger entities; for now the
-- per-master ledger lives implicitly on transfer rows (source = inter_account_transfer).
--
-- Status flow: active / inactive (no soft-deletes; the row is forever, with a flag).
-- inter_account_transfers are append-only — no edits/deletes once posted (v1).

CREATE TABLE bank_accounts (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(100)  NOT NULL,                 -- short label e.g. "HDFC Current"
    bank_name           VARCHAR(100)  DEFAULT NULL,
    account_number      VARCHAR(50)   DEFAULT NULL,
    ifsc                VARCHAR(20)   DEFAULT NULL,
    branch              VARCHAR(100)  DEFAULT NULL,
    opening_balance     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    current_balance     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    remarks             VARCHAR(500)  DEFAULT NULL,
    status              ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT DEFAULT NULL,
    updated_by          INT DEFAULT NULL,
    UNIQUE KEY uniq_bank_account_name (name),
    KEY idx_ba_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cashier_drawers (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(100)  NOT NULL,                 -- e.g. "Main Counter"
    location            VARCHAR(100)  DEFAULT NULL,
    assigned_user_id    INT           DEFAULT NULL,             -- who runs this drawer (optional)
    opening_balance     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    current_balance     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    remarks             VARCHAR(500)  DEFAULT NULL,
    status              ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT DEFAULT NULL,
    updated_by          INT DEFAULT NULL,
    UNIQUE KEY uniq_drawer_name (name),
    KEY idx_cd_status (status),
    KEY idx_cd_user (assigned_user_id),
    CONSTRAINT fk_cd_user FOREIGN KEY (assigned_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One transfer = two endpoints. from_kind/to_kind are 'bank_account' or 'cashier_drawer'.
-- Posting is atomic: from.current_balance -= amount AND to.current_balance += amount.
-- mode = how the cash moved (cash withdrawal/deposit, NEFT, IMPS, internal cash hand-off, etc.)
CREATE TABLE inter_account_transfers (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number     VARCHAR(100)  NOT NULL,
    transfer_date       DATE          NOT NULL,
    from_kind           ENUM('bank_account', 'cashier_drawer') NOT NULL,
    from_id             INT           NOT NULL,
    to_kind             ENUM('bank_account', 'cashier_drawer') NOT NULL,
    to_id               INT           NOT NULL,
    amount              DECIMAL(14,2) NOT NULL,
    mode                ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'other') NOT NULL DEFAULT 'cash',
    reference           VARCHAR(100)  DEFAULT NULL,             -- cheque #, UTR, etc.
    remarks             VARCHAR(500)  DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          INT DEFAULT NULL,
    UNIQUE KEY uniq_transfer_number (transfer_number),
    KEY idx_iat_date (transfer_date),
    KEY idx_iat_from (from_kind, from_id),
    KEY idx_iat_to   (to_kind, to_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0029_init_operating_expenses.sql
-- ---------------------------------------------------------------------
-- Module #19 Operating Expenses
--
-- One-shot expense entries (rent, salary, utilities, repairs, etc.). Each expense:
--   - is paid AT THE TIME OF POSTING from a bank account or cashier drawer
--   - debits that paying-account's current_balance atomically
--   - optionally captures GST (input credit if registered + claimable, else folds into expense)
--
-- Status flow: posted -> voided. Voiding (admin only) writes a reverse balance delta on the
-- paying account; the row stays visible with status='voided' for audit history.
--
-- Editing finalized expenses is NOT allowed in v1 (immutable). Mistakes are corrected via
-- void + repost. (Same convention as sales/purchase invoices.)

CREATE TABLE operating_expense_categories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)  NOT NULL,
    description     VARCHAR(255)  DEFAULT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by      INT DEFAULT NULL,
    updated_by      INT DEFAULT NULL,
    UNIQUE KEY uniq_oec_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE operating_expenses (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    expense_number      VARCHAR(100) NOT NULL,                   -- GP-EXP/{FY}/{NNNNN}
    expense_date        DATE         NOT NULL,
    category_id         INT          NOT NULL,
    vendor_name         VARCHAR(150) DEFAULT NULL,               -- free text (rent landlord, utility provider, etc.)
    amount              DECIMAL(14,2) NOT NULL DEFAULT 0.00,     -- pre-tax expense amount
    gst_rate            DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    gst_amount          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    gst_claimable       TINYINT(1)    NOT NULL DEFAULT 0,        -- 1 = goes to input register (D3-style)
    total_with_gst      DECIMAL(14,2) NOT NULL DEFAULT 0.00,     -- amount the paying account is debited by
    paid_from_kind      ENUM('bank_account', 'cashier_drawer') NOT NULL,
    paid_from_id        INT          NOT NULL,
    payment_mode        ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') NOT NULL DEFAULT 'cash',
    payment_reference   VARCHAR(100) DEFAULT NULL,               -- UTR / cheque # / receipt #
    remarks             VARCHAR(500) DEFAULT NULL,
    status              ENUM('posted', 'voided') NOT NULL DEFAULT 'posted',
    voided_at           DATETIME     DEFAULT NULL,
    voided_by           INT          DEFAULT NULL,
    void_reason         VARCHAR(255) DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT          DEFAULT NULL,
    updated_by          INT          DEFAULT NULL,
    UNIQUE KEY uniq_expense_number (expense_number),
    KEY idx_oe_date (expense_date),
    KEY idx_oe_category (category_id),
    KEY idx_oe_paid_from (paid_from_kind, paid_from_id),
    KEY idx_oe_status (status),
    CONSTRAINT fk_oe_category FOREIGN KEY (category_id) REFERENCES operating_expense_categories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed a sensible starter set of categories. Admin can disable/rename later.
INSERT INTO operating_expense_categories (name, description) VALUES
    ('Rent',            'Shop / office rent'),
    ('Utilities',       'Electricity, water, internet, phone'),
    ('Salary',          'Employee wages and salaries'),
    ('Office Supplies', 'Stationery, consumables, printing'),
    ('Travel',          'Conveyance, fuel, tolls'),
    ('Repairs',         'Maintenance, repairs, AMC'),
    ('Bank Charges',    'Bank fees, transfer charges, card processing'),
    ('Misc',            'Other operating expenses');

-- ---------------------------------------------------------------------
-- 0030_init_recurring_expenses.sql
-- ---------------------------------------------------------------------
-- Recurring Operating Expenses (rent, salary, internet bill, etc.)
--
-- A recurring template carries the same fields as a regular expense plus a frequency,
-- start date, optional end date, and a next_run_date that the firing engine advances.
--
-- "Firing" the engine is manual via the "Run Due Recurrences" button (or admin-triggered).
-- It finds all status='active' rows where next_run_date <= today, posts each as a real
-- operating_expenses row (calling the same service), and advances next_run_date by one
-- frequency step. If the new next_run_date overshoots end_date, the row is auto-paused.
--
-- Each fired expense links back via spawned_from_recurring_id so we can show "this expense
-- came from rent template #3, period 2026-04".

CREATE TABLE recurring_expenses (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150)  NOT NULL,                 -- e.g. "Shop rent — landlord A"
    category_id         INT           NOT NULL,
    vendor_name         VARCHAR(150)  DEFAULT NULL,
    amount              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    gst_rate            DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    gst_claimable       TINYINT(1)    NOT NULL DEFAULT 0,
    paid_from_kind      ENUM('bank_account', 'cashier_drawer') NOT NULL,
    paid_from_id        INT           NOT NULL,
    payment_mode        ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') NOT NULL DEFAULT 'cash',
    payment_reference   VARCHAR(100)  DEFAULT NULL,             -- template (e.g. "Rent {month}")
    remarks             VARCHAR(500)  DEFAULT NULL,
    -- Schedule
    frequency           ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') NOT NULL DEFAULT 'monthly',
    interval_count      INT           NOT NULL DEFAULT 1,        -- e.g. every 2 months
    start_date          DATE          NOT NULL,
    end_date            DATE          DEFAULT NULL,             -- null = open-ended
    next_run_date       DATE          NOT NULL,                 -- mutated by the firing engine
    last_run_at         DATETIME      DEFAULT NULL,
    runs_count          INT           NOT NULL DEFAULT 0,
    status              ENUM('active', 'paused') NOT NULL DEFAULT 'active',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT DEFAULT NULL,
    updated_by          INT DEFAULT NULL,
    KEY idx_re_next_run (next_run_date),
    KEY idx_re_status (status),
    KEY idx_re_category (category_id),
    CONSTRAINT fk_re_category FOREIGN KEY (category_id) REFERENCES operating_expense_categories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trace each fired expense back to its template (nullable so manual expenses still work).
ALTER TABLE operating_expenses
    ADD COLUMN spawned_from_recurring_id INT DEFAULT NULL AFTER void_reason,
    ADD KEY idx_oe_spawned (spawned_from_recurring_id),
    ADD CONSTRAINT fk_oe_spawned FOREIGN KEY (spawned_from_recurring_id) REFERENCES recurring_expenses(id)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- 0031_payments_link_account.sql
-- ---------------------------------------------------------------------
-- Wire sales/purchase payments to a specific bank account or cashier drawer.
--
-- A payment row now carries the account that received (sales) or paid (purchase) the cash.
-- The payment service mutates that account's current_balance atomically on:
--   - insert (credit on sales / debit on purchase by effective_amount)
--   - cheque status change (pending↔bounced/cancelled flips the cheque portion in/out)
--   - delete (reverse whatever's currently counted)
--
-- "effective_amount" follows the existing convention: cash + online +
--   cheque (counted only when status IN ('pending', 'cleared')).
--
-- Columns are NULLABLE for backward compatibility — pre-existing payment rows have NULL
-- and don't post to any account. Going forward, the UI requires a selection.

ALTER TABLE sales_payments
    ADD COLUMN received_into_kind ENUM('bank_account', 'cashier_drawer') NULL AFTER cheque_status,
    ADD COLUMN received_into_id   INT NULL AFTER received_into_kind,
    ADD KEY idx_sp_account (received_into_kind, received_into_id);

ALTER TABLE purchase_payments
    ADD COLUMN paid_from_kind ENUM('bank_account', 'cashier_drawer') NULL AFTER cheque_status,
    ADD COLUMN paid_from_id   INT NULL AFTER paid_from_kind,
    ADD KEY idx_pp_account (paid_from_kind, paid_from_id);

-- ---------------------------------------------------------------------
-- 0032_init_capital_movements.sql
-- ---------------------------------------------------------------------
-- Capital & Drawings (D26 / D40) — owner / lender money movements that AREN'T expenses.
--
-- Five txn_types cover the common cases:
--   - capital_in   : owner injects money INTO the business        (account credited, equity up)
--   - drawing      : owner takes money OUT for personal use       (account debited, equity down)
--   - loan_in      : business borrows from a lender               (account credited, payable up)
--   - loan_repay   : business pays back a lender                  (account debited, payable down)
--   - other        : misc non-business movement                   (admin picks direction)
--
-- These are NOT operating expenses. Mixing them with expenses overstates costs and breaks the P&L.
--
-- counterparty_name is free-text in v1 (owner name, lender name). A formal Lenders / Partners
-- master is deferred — for now, the loan-balance-by-counterparty report groups by name match.
--
-- Status flow: posted -> voided. Void reverses the balance impact and keeps the row for audit.

CREATE TABLE capital_movements (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    movement_number     VARCHAR(100) NOT NULL,                -- GP-CAP/{FY}/{NNNNN}
    movement_date       DATE         NOT NULL,
    txn_type            ENUM('capital_in', 'drawing', 'loan_in', 'loan_repay', 'other') NOT NULL,
    direction           ENUM('in', 'out') NOT NULL,           -- denormalised but explicit (in = credit account)
    counterparty_name   VARCHAR(150) NOT NULL,                -- owner / lender / partner name
    amount              DECIMAL(14,2) NOT NULL,
    account_kind        ENUM('bank_account', 'cashier_drawer') NOT NULL,
    account_id          INT          NOT NULL,
    mode                ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') NOT NULL DEFAULT 'cash',
    reference           VARCHAR(100) DEFAULT NULL,
    remarks             VARCHAR(500) DEFAULT NULL,
    status              ENUM('posted', 'voided') NOT NULL DEFAULT 'posted',
    voided_at           DATETIME     DEFAULT NULL,
    voided_by           INT          DEFAULT NULL,
    void_reason         VARCHAR(255) DEFAULT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT          DEFAULT NULL,
    updated_by          INT          DEFAULT NULL,
    UNIQUE KEY uniq_capital_number (movement_number),
    KEY idx_cm_date (movement_date),
    KEY idx_cm_type (txn_type),
    KEY idx_cm_counterparty (counterparty_name),
    KEY idx_cm_account (account_kind, account_id),
    KEY idx_cm_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0033_init_commission_settlements.sql
-- ---------------------------------------------------------------------
-- Module #22 Salesman Commission Settlement (D21).
--
-- A salesman's outstanding commission (sum of sales_invoice_lines.commission_amount minus
-- sales_return_lines.commission_reversed for finalized invoices/returns) can be settled in
-- two ways:
--
--   - cash : debit a bank account or cashier drawer by the cash amount.
--   - goods: stock-OUT a product at its CURRENT moving_avg_cost (NOT selling price).
--            total_value = qty × moving_avg_at_settle. Commission ledger debited by this value.
--
-- v1 lets each settlement row settle ONE mode (one cash payout OR one goods item).
-- Multi-product goods or split cash+goods settlements can be done via multiple rows.
--
-- Status flow: posted → voided. Void reverses the original effect (cash credited back,
-- or goods stock-back-IN at the snapshotted moving_avg).

CREATE TABLE salesman_commission_settlements (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    settlement_number        VARCHAR(100) NOT NULL,                 -- GP-COMM/{FY}/{NNNNN}
    settlement_date          DATE         NOT NULL,
    salesman_id              INT          NOT NULL,
    payout_mode              ENUM('cash', 'goods') NOT NULL,
    -- Cash-mode fields
    cash_amount              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    paid_from_kind           ENUM('bank_account', 'cashier_drawer') DEFAULT NULL,
    paid_from_id             INT          DEFAULT NULL,
    payment_mode             ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') DEFAULT NULL,
    payment_reference        VARCHAR(100) DEFAULT NULL,
    -- Goods-mode fields
    goods_product_id         INT          DEFAULT NULL,
    goods_quantity           DECIMAL(12,3) DEFAULT NULL,
    goods_unit_cost          DECIMAL(14,4) DEFAULT NULL,            -- snapshot of product.moving_avg_cost at settle time
    goods_total_value        DECIMAL(14,2) DEFAULT NULL,            -- = qty × goods_unit_cost
    -- Header
    total_settled_amount     DECIMAL(14,2) NOT NULL,                -- cash_amount OR goods_total_value (one of them)
    remarks                  VARCHAR(500) DEFAULT NULL,
    status                   ENUM('posted', 'voided') NOT NULL DEFAULT 'posted',
    voided_at                DATETIME     DEFAULT NULL,
    voided_by                INT          DEFAULT NULL,
    void_reason              VARCHAR(255) DEFAULT NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by               INT          DEFAULT NULL,
    updated_by               INT          DEFAULT NULL,
    UNIQUE KEY uniq_commission_settlement_number (settlement_number),
    KEY idx_scs_salesman (salesman_id),
    KEY idx_scs_date (settlement_date),
    KEY idx_scs_status (status),
    KEY idx_scs_paid_from (paid_from_kind, paid_from_id),
    KEY idx_scs_goods_product (goods_product_id),
    CONSTRAINT fk_scs_salesman FOREIGN KEY (salesman_id) REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_scs_product  FOREIGN KEY (goods_product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0034_init_customer_advances.sql
-- ---------------------------------------------------------------------
-- Customer Advances — money received from a customer NOT tied to an existing invoice
-- (or in excess of one, parked as a credit).
--
-- BALANCE INTEGRITY GUARANTEES (enforced by the service layer in one DB transaction):
--
--   1. Receiving an advance:
--        - bank/drawer.current_balance += amount
--        - customer_advances row created with balance = amount, status = 'active'
--      (Net cash effect: +amount to chosen account.)
--
--   2. Applying an advance to an invoice (via sales_payments.from_advance_amount):
--        - customer_advances.balance -= applied_amount  (clamped: applied <= balance)
--        - if balance == 0 → status = 'exhausted'
--        - sales_payments row created with from_advance_amount, no cash/online/cheque
--          (or mixed). Account balance is NOT touched for the from_advance portion —
--          this is a paper credit transfer, not a cash movement.
--        - sales_invoices.paid/due/payment_status recomputed (effectivePaidForInvoice
--          now sums from_advance_amount as well).
--      (Net cash effect: zero from the advance portion.)
--
--   3. Refunding an advance (admin only):
--        - bank/drawer.current_balance -= remaining balance
--        - customer_advances.balance set to 0, status = 'refunded'
--      (Net cash effect: −balance from chosen account.)
--
--   4. Voiding a payment that used an advance (existing delete flow):
--        - account credited back by cash+online+cheque-counted (existing behavior)
--        - customer_advances.balance += from_advance_amount of that payment
--        - if status was 'exhausted', flip back to 'active'
--      (Net cash effect: same as before — restores original state.)
--
--   5. Cheque bounce/cancel on a mixed payment: only the cheque portion's account delta
--      is reversed (existing behavior). Advance portion is untouched (advance was not a
--      conditional credit — it was already-received cash being re-allocated).
--
-- CROSS-CHECK INVARIANT (run as a sanity report later):
--   for any customer C:
--     SUM(advances_received) − SUM(advances_applied) − SUM(advances_refunded)
--                                        ==
--     SUM(active_advances.balance)

CREATE TABLE customer_advances (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    advance_number           VARCHAR(100) NOT NULL,                 -- GP-ADV/{FY}/{NNNNN}
    advance_date             DATE         NOT NULL,
    customer_id              INT          NOT NULL,
    amount                   DECIMAL(14,2) NOT NULL,                -- original amount received (immutable)
    balance                  DECIMAL(14,2) NOT NULL,                -- = amount initially; drops on apply; 0 after refund
    received_into_kind       ENUM('bank_account', 'cashier_drawer') NOT NULL,
    received_into_id         INT          NOT NULL,
    payment_mode             ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') NOT NULL DEFAULT 'cash',
    payment_reference        VARCHAR(100) DEFAULT NULL,
    remarks                  VARCHAR(500) DEFAULT NULL,
    status                   ENUM('active', 'exhausted', 'refunded') NOT NULL DEFAULT 'active',
    refunded_at              DATETIME     DEFAULT NULL,
    refunded_by              INT          DEFAULT NULL,
    refund_reason            VARCHAR(255) DEFAULT NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by               INT          DEFAULT NULL,
    updated_by               INT          DEFAULT NULL,
    UNIQUE KEY uniq_advance_number (advance_number),
    KEY idx_ca_customer (customer_id),
    KEY idx_ca_status (status),
    KEY idx_ca_received (received_into_kind, received_into_id),
    KEY idx_ca_date (advance_date),
    CONSTRAINT fk_ca_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A sales_payment can now include an advance-application portion. NULL/0 means no advance used.
ALTER TABLE sales_payments
    ADD COLUMN from_advance_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER received_into_id,
    ADD COLUMN from_advance_id     INT           DEFAULT NULL          AFTER from_advance_amount,
    ADD KEY idx_sp_from_advance (from_advance_id),
    ADD CONSTRAINT fk_sp_from_advance FOREIGN KEY (from_advance_id) REFERENCES customer_advances(id)
        ON UPDATE CASCADE ON DELETE RESTRICT;

-- ---------------------------------------------------------------------
-- 0035_init_supplier_advances.sql
-- ---------------------------------------------------------------------
-- Supplier Advances — mirror of customer_advances, direction inverted.
-- We PAY the supplier ahead (or in excess of an invoice) creating a credit balance
-- with them. Future purchase payments can draw from this credit instead of new cash.
--
-- BALANCE INTEGRITY GUARANTEES (enforced by the service layer in one DB transaction):
--
--   1. Pay an advance (we pay supplier):
--        - bank/drawer.current_balance −= amount   (we paid OUT)
--        - supplier_advances row created with balance = amount, status = 'active'
--      (Net cash effect: −amount on chosen account.)
--
--   2. Apply a supplier advance to a purchase invoice (paper credit transfer):
--        - supplier_advances.balance −= applied_amount  (clamped: applied <= balance)
--        - if balance == 0 → status = 'exhausted'
--        - purchase_payments row created with from_advance_amount; account NOT touched
--          for the from_advance portion (no fresh cash movement).
--        - purchase_invoices.paid/due/payment_status recomputed.
--      (Net cash effect: zero from the advance portion.)
--
--   3. Refund a supplier advance (supplier returns the unused balance to us):
--        - bank/drawer.current_balance += remaining balance   (we got cash back)
--        - supplier_advances.balance set to 0, status = 'refunded'
--      (Net cash effect: +remaining on chosen account.)
--
--   4. Voiding a payment that drew from an advance — same as customer side:
--      account is uncredited / advance balance restored.
--
-- Cross-check invariant (verified in IntegrityCheckService):
--   for any supplier S:
--     SUM(advance_amount) − SUM(applied) − SUM(refund_amount)
--                                ==
--     SUM(active_advances.balance)

CREATE TABLE supplier_advances (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    advance_number           VARCHAR(100) NOT NULL,                 -- GP-SADV/{FY}/{NNNNN}
    advance_date             DATE         NOT NULL,
    supplier_id              INT          NOT NULL,
    amount                   DECIMAL(14,2) NOT NULL,                -- original amount paid
    balance                  DECIMAL(14,2) NOT NULL,                -- = amount initially; drops on apply; 0 after refund
    paid_from_kind           ENUM('bank_account', 'cashier_drawer') NOT NULL,
    paid_from_id             INT          NOT NULL,
    payment_mode             ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') NOT NULL DEFAULT 'cash',
    payment_reference        VARCHAR(100) DEFAULT NULL,
    remarks                  VARCHAR(500) DEFAULT NULL,
    status                   ENUM('active', 'exhausted', 'refunded') NOT NULL DEFAULT 'active',
    refunded_at              DATETIME     DEFAULT NULL,
    refunded_by              INT          DEFAULT NULL,
    refund_reason            VARCHAR(255) DEFAULT NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by               INT          DEFAULT NULL,
    updated_by               INT          DEFAULT NULL,
    UNIQUE KEY uniq_supplier_advance_number (advance_number),
    KEY idx_sa_supplier (supplier_id),
    KEY idx_sa_status (status),
    KEY idx_sa_paid_from (paid_from_kind, paid_from_id),
    KEY idx_sa_date (advance_date),
    CONSTRAINT fk_sa_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE purchase_payments
    ADD COLUMN from_advance_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER paid_from_id,
    ADD COLUMN from_advance_id     INT           DEFAULT NULL          AFTER from_advance_amount,
    ADD KEY idx_pp_from_advance (from_advance_id),
    ADD CONSTRAINT fk_pp_from_advance FOREIGN KEY (from_advance_id) REFERENCES supplier_advances(id)
        ON UPDATE CASCADE ON DELETE RESTRICT;

-- ---------------------------------------------------------------------
-- 0036_init_brand_rebates.sql
-- ---------------------------------------------------------------------
-- Brand Rebates — money/credit a brand grants us when a sales/purchase target is hit.
-- Source-of-truth document is the brand-issued credit note (number + date REQUIRED).
--
-- Four modes (D22 + D39 + 2026-04-26 user clarification):
--   1. cash_back        — brand pays us cash. Bank/drawer credited; P&L "Brand Rebate Income".
--   2. reduce_payable   — credit note offsets future purchase bills. Auto-creates a non-cash
--                         supplier_advance (payment_mode='rebate', paid_from_kind=NULL) which
--                         the existing apply-from-advance flow consumes against next bill(s).
--   3. cost_reduction   — retroactive landed-cost drop on on-hand stock for the brand.
--                         min(rebate, on_hand_value) reduces inventory carrying value;
--                         spillover (rebate − on_hand_value, if positive) crosses to income.
--                         Tracked per-product in brand_rebate_cost_adjustments so void can reverse.
--   4. free_stock       — brand sends us X units of one product. Stock-in at value_per_unit
--                         (admin-entered, defaults to current moving-avg). Rebate income is
--                         implicit in the value-at-cost basis (AS-2 compliant; preserves
--                         moving-avg).
--
-- BALANCE INTEGRITY (verified in IntegrityCheckService):
--   - cash_back rebates received_into account → +amount on bank/drawer ledger
--   - reduce_payable → exactly one linked supplier_advance row exists with same amount
--   - cost_reduction → SUM(brand_rebate_cost_adjustments.amount_applied) per rebate
--                      ≤ rebate.amount; remainder = rebate.income_spillover
--   - free_stock → product stock += free_quantity; product moving-avg unchanged
--                  if value_per_unit == previous moving-avg (admin choice)

CREATE TABLE brand_rebates (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    rebate_number            VARCHAR(100) NOT NULL,                 -- GP-RBT/{FY}/{NNNNN}
    rebate_date              DATE         NOT NULL,
    brand_id                 INT          NOT NULL,
    supplier_id              INT          NOT NULL,                 -- who granted/issued
    mode                     ENUM('cash_back', 'reduce_payable', 'cost_reduction', 'free_stock') NOT NULL,
    amount                   DECIMAL(14,2) NOT NULL,                -- gross rebate value (= credit note amount)

    credit_note_number       VARCHAR(100) NOT NULL,                 -- brand's credit note ref
    credit_note_date         DATE         NOT NULL,

    -- mode=cash_back
    received_into_kind       ENUM('bank_account', 'cashier_drawer') DEFAULT NULL,
    received_into_id         INT          DEFAULT NULL,
    payment_mode             ENUM('cash', 'neft', 'imps', 'rtgs', 'upi', 'cheque', 'other') DEFAULT NULL,
    payment_reference        VARCHAR(100) DEFAULT NULL,             -- UTR / cheque #

    -- mode=reduce_payable: link to the non-cash supplier_advance we auto-create
    linked_advance_id        INT          DEFAULT NULL,

    -- mode=cost_reduction: optional invoice scoping (else applies to all on-hand stock for brand)
    target_invoice_id        INT          DEFAULT NULL,
    income_spillover         DECIMAL(14,2) NOT NULL DEFAULT 0.00,   -- rebate − on_hand_value when positive

    -- mode=free_stock
    free_product_id          INT          DEFAULT NULL,
    free_quantity            DECIMAL(14,3) DEFAULT NULL,
    free_value_per_unit      DECIMAL(14,2) DEFAULT NULL,

    remarks                  VARCHAR(500) DEFAULT NULL,
    status                   ENUM('posted', 'voided') NOT NULL DEFAULT 'posted',
    voided_at                DATETIME     DEFAULT NULL,
    voided_by                INT          DEFAULT NULL,
    void_reason              VARCHAR(255) DEFAULT NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by               INT          NOT NULL,

    UNIQUE KEY uniq_rebate_number (rebate_number),
    KEY idx_rbt_brand    (brand_id),
    KEY idx_rbt_supplier (supplier_id),
    KEY idx_rbt_mode     (mode),
    KEY idx_rbt_date     (rebate_date),
    KEY idx_rbt_status   (status),
    CONSTRAINT fk_rbt_brand    FOREIGN KEY (brand_id)         REFERENCES brands(id)             ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rbt_supplier FOREIGN KEY (supplier_id)      REFERENCES suppliers(id)          ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rbt_inv      FOREIGN KEY (target_invoice_id) REFERENCES purchase_invoices(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rbt_prod     FOREIGN KEY (free_product_id)   REFERENCES products(id)          ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rbt_adv      FOREIGN KEY (linked_advance_id) REFERENCES supplier_advances(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-product cost-reduction trace (one row per affected product). Lets void reverse the
-- exact moving-avg delta posted at rebate time.
CREATE TABLE brand_rebate_cost_adjustments (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    rebate_id                INT          NOT NULL,
    product_id               INT          NOT NULL,
    on_hand_qty_at_post      DECIMAL(14,3) NOT NULL,                -- snapshot
    moving_avg_before        DECIMAL(14,4) NOT NULL,                -- snapshot
    amount_applied           DECIMAL(14,2) NOT NULL,                -- portion of rebate applied to this product
    moving_avg_delta         DECIMAL(14,4) NOT NULL,                -- amount_applied / on_hand_qty (per-unit reduction)
    moving_avg_after         DECIMAL(14,4) NOT NULL,                -- == moving_avg_before − moving_avg_delta
    created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rca_rebate  (rebate_id),
    KEY idx_rca_product (product_id),
    CONSTRAINT fk_rca_rebate  FOREIGN KEY (rebate_id)  REFERENCES brand_rebates(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rca_product FOREIGN KEY (product_id) REFERENCES products(id)      ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Allow non-cash supplier_advances. The reduce_payable rebate creates one with
-- paid_from_kind/id NULL and payment_mode='rebate' so it shows up in the existing
-- apply-from-advance UI without polluting the cash-payout flows.
ALTER TABLE supplier_advances
    MODIFY COLUMN paid_from_kind ENUM('bank_account', 'cashier_drawer') DEFAULT NULL,
    MODIFY COLUMN paid_from_id   INT DEFAULT NULL,
    MODIFY COLUMN payment_mode   ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other', 'rebate')
                                 NOT NULL DEFAULT 'cash';

-- ---------------------------------------------------------------------
-- 0037_init_stock_adjustments.sql
-- ---------------------------------------------------------------------
-- Stock Adjustments — write-offs and count corrections (D15 + D6 oversell-block).
--
-- Use cases:
--   damage, expiry, theft → direction='out' only (stock leaves at current moving-avg cost)
--   count_correction → 'out' for missing stock, 'in' for found stock (split into two adjustments)
--   other → either direction
--
-- Cost basis snapshotting:
--   line.cost_at_adjustment is captured at post time. Out lines stamp the current
--   moving-avg cost (what the stock was costed at when it left). In lines (count
--   corrections finding stock) require admin-entered cost — system can't infer cost
--   for stock it didn't track.
--
-- BALANCE INTEGRITY (verified in IntegrityCheckService):
--   per line: quantity × cost_at_adjustment ≈ line_value (rounding tolerance ₹0.01)
--   header:   total_value = SUM(lines.line_value)
--
-- Voiding rules:
--   out adjustment → applyStockIn at snapshot cost (reintroduces units at their original
--                    cost basis; uses source='other' so latest_purchase_landed_cost is untouched)
--   in adjustment  → applyStockOut at CURRENT moving-avg (consumes the found units back out;
--                    refused if quantity_on_hand has since dropped below the line qty)

CREATE TABLE stock_adjustments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    adjustment_number   VARCHAR(100) NOT NULL,             -- GP-ADJ/{FY}/{NNNNN}
    adjustment_date     DATE         NOT NULL,
    reason              ENUM('damage', 'expiry', 'theft', 'count_correction', 'other') NOT NULL,
    direction           ENUM('out', 'in') NOT NULL,
    total_value         DECIMAL(14,2) NOT NULL,            -- sum of line values
    remarks             VARCHAR(500) DEFAULT NULL,
    status              ENUM('posted', 'voided') NOT NULL DEFAULT 'posted',
    voided_at           DATETIME     DEFAULT NULL,
    voided_by           INT          DEFAULT NULL,
    void_reason         VARCHAR(255) DEFAULT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT          NOT NULL,
    UNIQUE KEY uniq_adj_number (adjustment_number),
    KEY idx_adj_date     (adjustment_date),
    KEY idx_adj_reason   (reason),
    KEY idx_adj_status   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_adjustment_lines (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    stock_adjustment_id      INT          NOT NULL,
    line_no                  INT          NOT NULL,
    product_id               INT          NOT NULL,
    quantity                 DECIMAL(14,3) NOT NULL,       -- always positive; sign comes from header.direction
    cost_at_adjustment       DECIMAL(14,4) NOT NULL,       -- snapshot at post time
    line_value               DECIMAL(14,2) NOT NULL,       -- = quantity × cost_at_adjustment
    notes                    VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uniq_adj_line (stock_adjustment_id, line_no),
    KEY idx_adjl_product (product_id),
    CONSTRAINT fk_adjl_header   FOREIGN KEY (stock_adjustment_id) REFERENCES stock_adjustments(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_adjl_product  FOREIGN KEY (product_id)          REFERENCES products(id)          ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0038_init_activity_log.sql
-- ---------------------------------------------------------------------
-- System Activity Log (D48 + D56) — append-only audit trail for transactional records.
--
-- v1 captures: create / update / delete / void / refund / finalize / cancel / restore / login / logout.
-- Page views are NOT logged. Each row snapshots:
--   * actor (user_id + user_name at the time, since the user may be renamed/deactivated later)
--   * entity (kind + id + human label so the row is readable even if the entity is later deleted)
--   * before_json / after_json — optional, populated for `update` and `void`/`refund` where state matters
--   * ip_address — when available
--
-- Retention: keep forever until admin manually purges (D56). The purge action itself logs an entry.
-- CSV/JSON export before purge is a separate flow (deferred to a follow-up step).
--
-- Append-only: the only mutation expected is INSERT. No UPDATE/DELETE in normal flow.

CREATE TABLE activity_log (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    created_at    DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    user_id       INT          DEFAULT NULL,                 -- NULL for anonymous (failed login pre-auth)
    user_name     VARCHAR(150) DEFAULT NULL,                 -- snapshot of user.name at log time
    action        ENUM('create','update','delete','void','refund','finalize','cancel','restore','login','logout','purge') NOT NULL,
    entity_kind   VARCHAR(64)  NOT NULL,                     -- 'sales_invoice', 'brand_rebate', etc.
    entity_id     INT          DEFAULT NULL,                 -- NULL for non-row events (login)
    entity_label  VARCHAR(255) DEFAULT NULL,                 -- snapshot label (invoice_number, advance_number, etc.)
    ip_address    VARCHAR(45)  DEFAULT NULL,                 -- IPv4 or IPv6
    before_json   LONGTEXT     DEFAULT NULL,
    after_json    LONGTEXT     DEFAULT NULL,
    notes         VARCHAR(500) DEFAULT NULL,
    KEY idx_al_created (created_at),
    KEY idx_al_user    (user_id),
    KEY idx_al_kind    (entity_kind),
    KEY idx_al_entity  (entity_kind, entity_id),
    KEY idx_al_action  (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0039_init_legacy_records.sql
-- ---------------------------------------------------------------------
-- Legacy Records (Old Records Management) — pre-v2 customers + their historical bills/payments.
--
-- Why a parallel mini-system: at go-live there are customers from the old paper / v1 system
-- with outstanding balances and ongoing collection. Putting them in the regular customers/
-- sales_invoices flow would require fake invoice lines, fake brands, fake products. This
-- legacy module gives admins a focused interface: enter the customer, enter their old bill
-- (just bill_number + amount, no line items), record payments as they come in.
--
-- Direction: payments received into a real bank/drawer DO credit that account. Pre-v2
-- already-paid amounts are baked into bill.paid_amount at creation (no account effect).
--
-- BALANCE INTEGRITY (verified in IntegrityCheckService):
--   legacy_bill.paid_amount == legacy_bill.opening_paid_amount + SUM(legacy_payments.amount WHERE bill_id = legacy_bill.id)
--   legacy_bill.due_amount  == bill_amount - paid_amount   (recomputed on every change)

CREATE TABLE legacy_customers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(255) NOT NULL,
    phone        VARCHAR(50)  DEFAULT NULL,
    email        VARCHAR(150) DEFAULT NULL,
    address      VARCHAR(500) DEFAULT NULL,
    notes        VARCHAR(500) DEFAULT NULL,
    status       ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by   INT NOT NULL,
    updated_by   INT NOT NULL,
    KEY idx_lc_phone  (phone),
    KEY idx_lc_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legacy_bills (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    legacy_customer_id  INT          NOT NULL,
    bill_number         VARCHAR(100) NOT NULL,           -- as on the old paper bill
    bill_date           DATE         NOT NULL,
    bill_amount         DECIMAL(14,2) NOT NULL,
    opening_paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,    -- pre-v2 prepayment baked in at create; immutable
    paid_amount         DECIMAL(14,2) NOT NULL DEFAULT 0.00,    -- = opening_paid_amount + SUM(legacy_payments.amount)
    due_amount          DECIMAL(14,2) NOT NULL,
    payment_status      ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
    remarks             VARCHAR(500) DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT NOT NULL,
    updated_by          INT NOT NULL,
    KEY idx_lb_customer (legacy_customer_id),
    KEY idx_lb_status   (payment_status),
    KEY idx_lb_date     (bill_date),
    CONSTRAINT fk_lb_customer FOREIGN KEY (legacy_customer_id) REFERENCES legacy_customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legacy_payments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    legacy_customer_id  INT          NOT NULL,
    bill_id             INT          DEFAULT NULL,             -- NULL = unallocated (sits as customer credit)
    payment_date        DATE         NOT NULL,
    amount              DECIMAL(14,2) NOT NULL,
    payment_mode        ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') NOT NULL DEFAULT 'cash',
    payment_reference   VARCHAR(100) DEFAULT NULL,
    received_into_kind  ENUM('bank_account', 'cashier_drawer') NOT NULL,
    received_into_id    INT          NOT NULL,
    remarks             VARCHAR(500) DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          INT NOT NULL,
    KEY idx_lp_customer (legacy_customer_id),
    KEY idx_lp_bill     (bill_id),
    KEY idx_lp_account  (received_into_kind, received_into_id),
    KEY idx_lp_date     (payment_date),
    CONSTRAINT fk_lp_customer FOREIGN KEY (legacy_customer_id) REFERENCES legacy_customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_lp_bill     FOREIGN KEY (bill_id)            REFERENCES legacy_bills(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0040_refactor_legacy_to_generic.sql
-- ---------------------------------------------------------------------
-- Generalize legacy records: one set of tables for customers, suppliers and salesmen.
-- The customer-only legacy_* tables introduced in 0039 are dropped — there's no production
-- data yet (only smoke-test fixtures), so straight drop-and-recreate is safest.
--
-- Direction of cash on a settlement is implicit from party.kind:
--   customer  → settlement CREDITS the receiving account (we got money)
--   supplier  → settlement DEBITS  the paying account   (we paid)
--   salesman  → settlement DEBITS  the paying account   (we paid commission)
--
-- Service layer enforces the direction. Schema doesn't store it (kind drives it).
--
-- Activity log entry kinds become 'legacy_party', 'legacy_obligation', 'legacy_settlement'.

DROP TABLE IF EXISTS legacy_payments;
DROP TABLE IF EXISTS legacy_bills;
DROP TABLE IF EXISTS legacy_customers;

CREATE TABLE legacy_parties (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    kind         ENUM('customer', 'supplier', 'salesman') NOT NULL,
    name         VARCHAR(255) NOT NULL,
    phone        VARCHAR(50)  DEFAULT NULL,
    email        VARCHAR(150) DEFAULT NULL,
    address      VARCHAR(500) DEFAULT NULL,
    notes        VARCHAR(500) DEFAULT NULL,
    status       ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by   INT NOT NULL,
    updated_by   INT NOT NULL,
    KEY idx_lp_kind   (kind),
    KEY idx_lp_status (status),
    KEY idx_lp_phone  (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legacy_obligations (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    party_id            INT          NOT NULL,
    obligation_number   VARCHAR(100) NOT NULL,           -- bill # / commission ref / as on paper
    obligation_date     DATE         NOT NULL,
    obligation_amount   DECIMAL(14,2) NOT NULL,
    opening_paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,    -- pre-v2 settled portion (immutable)
    paid_amount         DECIMAL(14,2) NOT NULL DEFAULT 0.00,    -- = opening + SUM(legacy_settlements.amount)
    due_amount          DECIMAL(14,2) NOT NULL,
    payment_status      ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
    remarks             VARCHAR(500) DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT NOT NULL,
    updated_by          INT NOT NULL,
    KEY idx_lo_party    (party_id),
    KEY idx_lo_status   (payment_status),
    KEY idx_lo_date     (obligation_date),
    CONSTRAINT fk_lo_party FOREIGN KEY (party_id) REFERENCES legacy_parties(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legacy_settlements (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    party_id          INT          NOT NULL,
    obligation_id     INT          DEFAULT NULL,         -- NULL = unallocated (sits as advance)
    settlement_date   DATE         NOT NULL,
    amount            DECIMAL(14,2) NOT NULL,
    payment_mode      ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') NOT NULL DEFAULT 'cash',
    payment_reference VARCHAR(100) DEFAULT NULL,
    account_kind      ENUM('bank_account', 'cashier_drawer') NOT NULL,
    account_id        INT          NOT NULL,
    remarks           VARCHAR(500) DEFAULT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by        INT NOT NULL,
    KEY idx_ls_party      (party_id),
    KEY idx_ls_obligation (obligation_id),
    KEY idx_ls_account    (account_kind, account_id),
    KEY idx_ls_date       (settlement_date),
    CONSTRAINT fk_ls_party      FOREIGN KEY (party_id)      REFERENCES legacy_parties(id)     ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ls_obligation FOREIGN KEY (obligation_id) REFERENCES legacy_obligations(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0041_brand_rebates_free_stock_avg_snapshot.sql
-- ---------------------------------------------------------------------
-- Snapshot moving_avg before/after a free_stock rebate post, so void can deterministically
-- reverse the avg shift. Mirrors the brand_rebate_cost_adjustments table's pre/after pattern
-- but stored inline since there's only one product per free_stock rebate.
--
-- NULL on existing posted rebates = pre-fix data; void falls back to legacy behavior
-- (stockOut only, leaves avg shifted). New posts always populate these.

ALTER TABLE brand_rebates
    ADD COLUMN free_stock_avg_before DECIMAL(14,4) DEFAULT NULL AFTER free_value_per_unit,
    ADD COLUMN free_stock_avg_after  DECIMAL(14,4) DEFAULT NULL AFTER free_stock_avg_before;

-- ---------------------------------------------------------------------
-- 0042_init_role_permissions.sql
-- ---------------------------------------------------------------------
-- Role-Menu Permissions
--
-- Per-role, per-resource, per-ability allow/deny grid. The set of resources and
-- the abilities each one supports come from App\Support\MenuPermissionRegistry
-- (the "catalog" — code is the source of truth; this table stores the toggles).
--
-- Convention: rows are stored ONLY for non-admin roles. Admin is treated as
-- always-allowed at the application layer (master switch — prevents accidental
-- self-lockout via a bad save). The role-permission UI also hides admin from
-- editable columns.
--
-- Seeded by RolePermissionService::seedDefaults() — invoked from
-- create_admin.php on first install AND from SystemResetService after a hard
-- reset wipes data. The seeder is idempotent (INSERT IGNORE).

CREATE TABLE role_permissions (
    role         ENUM('admin','subadmin','cashier') NOT NULL,
    resource_key VARCHAR(64)  NOT NULL,
    ability      VARCHAR(32)  NOT NULL,
    allowed      TINYINT(1)   NOT NULL DEFAULT 0,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by   INT          DEFAULT NULL,
    PRIMARY KEY (role, resource_key, ability),
    KEY idx_rp_role (role),
    KEY idx_rp_resource (resource_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0043_init_roles_table.sql
-- ---------------------------------------------------------------------
-- Dynamic Roles
--
-- Replaces the hard-coded users.role ENUM with an FK to a `roles` table so admin
-- can create new roles without a code change.
--
-- Convention:
--   * 'admin' role is the master-switch role (Permissions::can() short-circuits to
--     allowed for any user with role_key='admin'). Marked is_system=1 so the
--     roles UI cannot delete it.
--   * Every other role's access is fully driven by role_permissions + per-user
--     overrides; the role_key string itself carries no privilege.
--
-- Migration steps (must run in order — DDL is auto-commit in MySQL, so partial
-- failure can leave a half-cleaned schema; idempotent guards aren't possible
-- here without significant complexity, so we keep each step minimal):
--   1. Create roles table.
--   2. Seed default roles (admin / subadmin / cashier — same keys as the
--      retired ENUM, so existing user rows can be remapped 1:1).
--   3. Add users.role_id (nullable for the population step).
--   4. Populate role_id from the existing role string.
--   5. Drop the old role column + index.
--   6. Make role_id NOT NULL and add the FK + index.

CREATE TABLE roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    role_key    VARCHAR(40)  NOT NULL,
    label       VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_system   TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order  INT          NOT NULL DEFAULT 100,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_roles_key (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (role_key, label, description, is_system, sort_order) VALUES
    ('admin',    'Administrator',     'Full access. Always allowed (master switch). Cannot be deleted.', 1, 1),
    ('subadmin', 'Sub-Administrator', 'Manages day-to-day operations; cannot reset/delete or change settings.', 0, 10),
    ('cashier',  'Cashier',           'Front-desk POS staff; sales workflow only.', 0, 20);

ALTER TABLE users ADD COLUMN role_id INT NULL AFTER role;

UPDATE users u JOIN roles r ON r.role_key = u.role SET u.role_id = r.id;

ALTER TABLE users DROP KEY idx_users_role;

ALTER TABLE users DROP COLUMN role;

ALTER TABLE users MODIFY role_id INT NOT NULL;

ALTER TABLE users ADD KEY idx_users_role_id (role_id);

ALTER TABLE users ADD CONSTRAINT fk_users_role_id FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE RESTRICT;

-- ---------------------------------------------------------------------
-- 0044_redesign_role_permissions.sql
-- ---------------------------------------------------------------------
-- Permission storage redesign — role_id-keyed grants + per-user overrides.
--
-- The old role_permissions table from 0042 was keyed by an ENUM(role) and a
-- (resource_key, ability) pair — incompatible with dynamic roles, so it's dropped
-- and recreated as:
--
--   role_permissions       (role_id, permission_key, allowed)
--   user_permission_overrides (user_id, permission_key, allowed)
--
-- permission_key format is 'resource_key.ability', e.g. 'users.view',
-- 'sales_invoices.delete' — flat string keyed off the catalogue in
-- App\Support\MenuPermissionRegistry. The catalogue stays the source of truth
-- for what permissions exist; this table stores who can do what.
--
-- Resolution semantics (App\Support\Permissions):
--   1. admin role short-circuits → allowed.
--   2. role grant (allowed=1) seeds the user's allowed-set.
--   3. user override layered on top: allowed=1 grants extra; allowed=0 denies
--      something the role would otherwise grant.
--
-- We never store explicit deny rows in role_permissions — absence = denied.
-- user_permission_overrides DOES store both because the override needs to
-- distinguish "no opinion" (no row) from "explicit deny" (allowed=0).

DROP TABLE IF EXISTS role_permissions;

CREATE TABLE role_permissions (
    role_id        INT          NOT NULL,
    permission_key VARCHAR(96)  NOT NULL,
    allowed        TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by     INT          DEFAULT NULL,
    PRIMARY KEY (role_id, permission_key),
    KEY idx_rp_perm (permission_key),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_permission_overrides (
    user_id        INT          NOT NULL,
    permission_key VARCHAR(96)  NOT NULL,
    allowed        TINYINT(1)   NOT NULL,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by     INT          DEFAULT NULL,
    PRIMARY KEY (user_id, permission_key),
    KEY idx_upo_perm (permission_key),
    CONSTRAINT fk_upo_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0045_link_legacy_to_live.sql
-- ---------------------------------------------------------------------
-- Link legacy parties to current customers / suppliers / salesmen.
--
-- A legacy party may optionally be linked to its v2 counterpart so the legacy
-- obligations + settlements appear in the live entity's Universal Passbook
-- (carry-forward into the v2 ledger).
--
-- Three nullable FK columns instead of a polymorphic (kind, id) pair so that:
--   * MySQL can enforce ON DELETE SET NULL per target table (legacy data survives
--     even if the live entity is deleted — only the link is dropped).
--   * Application layer enforces the "exactly one column matching legacy_parties.kind"
--     invariant; schema would need triggers to do that and we keep DDL simple.
--
-- Many-to-one allowed: multiple legacy_parties can link to the same live customer
-- (covers cases where the same person had aliases in pre-v2 records).
--
-- Live → Legacy lookup uses: SELECT * FROM legacy_parties WHERE linked_<kind>_id = ?
-- Both sides are indexed.

ALTER TABLE legacy_parties
    ADD COLUMN linked_customer_id INT NULL AFTER kind,
    ADD COLUMN linked_supplier_id INT NULL AFTER linked_customer_id,
    ADD COLUMN linked_salesman_id INT NULL AFTER linked_supplier_id,
    ADD KEY idx_lp_link_customer (linked_customer_id),
    ADD KEY idx_lp_link_supplier (linked_supplier_id),
    ADD KEY idx_lp_link_salesman (linked_salesman_id),
    ADD CONSTRAINT fk_lp_link_customer
        FOREIGN KEY (linked_customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    ADD CONSTRAINT fk_lp_link_supplier
        FOREIGN KEY (linked_supplier_id) REFERENCES suppliers(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    ADD CONSTRAINT fk_lp_link_salesman
        FOREIGN KEY (linked_salesman_id) REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- 0046_unify_legacy_with_live_entities.sql
-- ---------------------------------------------------------------------
-- Unify legacy parties with live entities.
--
-- Before: legacy_parties was a separate entity table; obligations/settlements
-- referenced legacy_parties.id, and a link FK to customers/suppliers/salesmen
-- was added in 0045.
--
-- After: ONE entity per real-world person (customer/supplier/salesman). Old
-- obligations + settlements attach DIRECTLY to a customer/supplier/salesman.
-- legacy_parties is dropped.
--
-- Migration steps:
--   1. Add nullable customer_id/supplier_id/salesman_id columns to obligations
--      + settlements with FKs (ON DELETE RESTRICT — old data blocks deletion of
--      its live entity, matches existing transactional FK convention).
--   2. Populate from legacy_parties.linked_*_id (no-op if no data exists yet).
--   3. Drop the old party_id FK + column from obligations + settlements.
--   4. Drop legacy_parties.
--
-- IMPORTANT for production: every legacy_party row must be linked
-- (linked_customer_id / linked_supplier_id / linked_salesman_id) BEFORE running
-- this migration. Unlinked legacy parties' obligations/settlements end up with
-- all three id columns NULL, which the application will treat as orphaned.
-- Resolve via the legacy-records UI before applying.

ALTER TABLE legacy_obligations
    ADD COLUMN customer_id INT NULL AFTER id,
    ADD COLUMN supplier_id INT NULL AFTER customer_id,
    ADD COLUMN salesman_id INT NULL AFTER supplier_id,
    ADD KEY idx_lo_customer (customer_id),
    ADD KEY idx_lo_supplier (supplier_id),
    ADD KEY idx_lo_salesman (salesman_id),
    ADD CONSTRAINT fk_lo_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_lo_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_lo_salesman FOREIGN KEY (salesman_id) REFERENCES salesmen(id)  ON UPDATE CASCADE ON DELETE RESTRICT;

UPDATE legacy_obligations lo
JOIN legacy_parties lp ON lp.id = lo.party_id
SET lo.customer_id = lp.linked_customer_id,
    lo.supplier_id = lp.linked_supplier_id,
    lo.salesman_id = lp.linked_salesman_id;

ALTER TABLE legacy_settlements
    ADD COLUMN customer_id INT NULL AFTER id,
    ADD COLUMN supplier_id INT NULL AFTER customer_id,
    ADD COLUMN salesman_id INT NULL AFTER supplier_id,
    ADD KEY idx_ls_customer (customer_id),
    ADD KEY idx_ls_supplier (supplier_id),
    ADD KEY idx_ls_salesman (salesman_id),
    ADD CONSTRAINT fk_ls_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_ls_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_ls_salesman FOREIGN KEY (salesman_id) REFERENCES salesmen(id)  ON UPDATE CASCADE ON DELETE RESTRICT;

UPDATE legacy_settlements ls
JOIN legacy_parties lp ON lp.id = ls.party_id
SET ls.customer_id = lp.linked_customer_id,
    ls.supplier_id = lp.linked_supplier_id,
    ls.salesman_id = lp.linked_salesman_id;

ALTER TABLE legacy_obligations DROP FOREIGN KEY fk_lo_party;
ALTER TABLE legacy_obligations DROP COLUMN party_id;

ALTER TABLE legacy_settlements DROP FOREIGN KEY fk_ls_party;
ALTER TABLE legacy_settlements DROP COLUMN party_id;

DROP TABLE legacy_parties;

-- ---------------------------------------------------------------------
-- 0047_product_price_history_avg_snapshot.sql
-- ---------------------------------------------------------------------
-- Snapshot columns on product_price_history so the user can see HOW each
-- moving-avg change happened: prior state → event → new state.
--
-- Why all six columns: a row that records "moving_avg_cost is now X" tells you
-- the result, but not the math. With these columns the UI can render
--   prior_qty=100 @ avg=₹40   +   event_qty=50 @ ₹50   →   new_qty=150 @ avg=₹43.33
-- without re-deriving anything.
--
-- All six are nullable so old rows survive the migration; the view shows "—"
-- for unsnapshotted rows. A separate PHP back-fill script
-- (database/backfill_product_price_history.php) walks the existing data and
-- populates them retroactively where the source row provides enough context.
--
-- Going forward, InventoryService::applyStockIn / applyStockOut populate them
-- on every event (purchase, return, adjustment, free rebate, manual change).

ALTER TABLE product_price_history
    ADD COLUMN prior_qty       DECIMAL(14,3) DEFAULT NULL AFTER landed_cost,
    ADD COLUMN prior_avg       DECIMAL(14,4) DEFAULT NULL AFTER prior_qty,
    ADD COLUMN event_qty       DECIMAL(14,3) DEFAULT NULL AFTER prior_avg,
    ADD COLUMN event_unit_cost DECIMAL(14,4) DEFAULT NULL AFTER event_qty,
    ADD COLUMN new_qty         DECIMAL(14,3) DEFAULT NULL AFTER event_unit_cost,
    ADD COLUMN new_avg         DECIMAL(14,4) DEFAULT NULL AFTER new_qty;

-- ---------------------------------------------------------------------
-- 0048_init_units_and_product_units.sql
-- ---------------------------------------------------------------------
-- Unit management — global master + per-product overrides.
--
-- Two-tier model:
--
--   1. units (global)
--      Reusable, business-wide units organised into "families" (count, mass,
--      volume, ...). Within a family, one unit is the BASE and others convert
--      to it via factor_to_base — e.g. base ML, then LTR with factor=1000.
--
--   2. product_units (per-product overrides)
--      A product can ALSO accept packaging-style units that are specific to it
--      (case = 12 bottles, carton = 6 packs). These never make sense globally
--      because each product packs differently; they're stored per (product, unit)
--      with their own factor_to_product_base. The unit row in `units` is still
--      shared (so 'CASE' exists once globally), but its conversion factor for
--      THIS product is what's stored in product_units.
--
-- Stock is always tracked in the product's BASE unit (products.unit_id). Other
-- units are buy/sell-time conveniences and convert at the product's base on the
-- way in/out.
--
-- We DON'T touch products.unit (varchar) here — that's done in 0049 where we add
-- unit_id FK + back-fill from the existing unit string. Splitting the migrations
-- lets us seed the master before any FK is added so the back-fill has targets.

CREATE TABLE units (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    code              VARCHAR(20)  NOT NULL,
    name              VARCHAR(100) NOT NULL,
    family            ENUM('count', 'mass', 'volume', 'length', 'pack', 'other') NOT NULL DEFAULT 'count',
    -- Conversion: 1 of THIS unit = factor_to_base × base_unit. base_unit_id self-FKs.
    base_unit_id      INT          DEFAULT NULL,
    factor_to_base    DECIMAL(20,8) NOT NULL DEFAULT 1.00000000,
    decimals          TINYINT      NOT NULL DEFAULT 3,        -- display precision in qty fields
    is_active         TINYINT(1)   NOT NULL DEFAULT 1,
    description       VARCHAR(255) DEFAULT NULL,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by        INT          DEFAULT NULL,
    updated_by        INT          DEFAULT NULL,
    UNIQUE KEY uniq_units_code (code),
    KEY idx_units_family (family),
    KEY idx_units_base   (base_unit_id),
    CONSTRAINT fk_units_base FOREIGN KEY (base_unit_id) REFERENCES units(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the common units. Pattern: insert the base first (NULL base_unit_id), then
-- the converted ones referencing it.
INSERT INTO units (code, name, family, base_unit_id, factor_to_base, decimals) VALUES
    ('PCS',   'Pieces',          'count',  NULL, 1,    0),
    ('DOZEN', 'Dozen',           'count',  NULL, 1,    0),       -- factor patched below
    ('KG',    'Kilogram',        'mass',   NULL, 1,    3),
    ('G',     'Gram',            'mass',   NULL, 1,    3),       -- factor patched below
    ('LTR',   'Litre',           'volume', NULL, 1,    3),
    ('ML',    'Millilitre',      'volume', NULL, 1,    3),       -- factor patched below
    ('M',     'Metre',           'length', NULL, 1,    3),
    ('CM',    'Centimetre',      'length', NULL, 1,    2),       -- factor patched below
    ('CASE',  'Case (packaging)','pack',   NULL, 1,    0),
    ('BOX',   'Box (packaging)', 'pack',   NULL, 1,    0),
    ('PACK',  'Pack (packaging)','pack',   NULL, 1,    0);

-- Wire conversions: each non-base unit's base_unit_id + factor_to_base.
UPDATE units SET base_unit_id = (SELECT id FROM (SELECT id FROM units WHERE code='PCS') x), factor_to_base = 12       WHERE code = 'DOZEN';
UPDATE units SET base_unit_id = (SELECT id FROM (SELECT id FROM units WHERE code='KG')  x), factor_to_base = 0.001    WHERE code = 'G';
UPDATE units SET base_unit_id = (SELECT id FROM (SELECT id FROM units WHERE code='LTR') x), factor_to_base = 0.001    WHERE code = 'ML';
UPDATE units SET base_unit_id = (SELECT id FROM (SELECT id FROM units WHERE code='M')   x), factor_to_base = 0.01     WHERE code = 'CM';
-- Bases reference themselves so every unit has a base_unit_id.
UPDATE units SET base_unit_id = id WHERE base_unit_id IS NULL;

CREATE TABLE product_units (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    product_id                  INT          NOT NULL,
    unit_id                     INT          NOT NULL,
    -- 1 of unit_id = factor_to_product_base × product's base_unit (set in products.unit_id).
    -- Even when the unit is "compatible" globally (LTR vs ML), we still cache the
    -- effective factor here so a transaction can read just one row.
    factor_to_product_base      DECIMAL(20,8) NOT NULL DEFAULT 1.00000000,
    is_purchase                 TINYINT(1)   NOT NULL DEFAULT 1,
    is_sale                     TINYINT(1)   NOT NULL DEFAULT 1,
    is_default_purchase         TINYINT(1)   NOT NULL DEFAULT 0,
    is_default_sale             TINYINT(1)   NOT NULL DEFAULT 0,
    notes                       VARCHAR(255) DEFAULT NULL,
    created_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pu (product_id, unit_id),
    KEY idx_pu_unit (unit_id),
    CONSTRAINT fk_pu_product FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_pu_unit FOREIGN KEY (unit_id) REFERENCES units(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0049_products_unit_id_fk.sql
-- ---------------------------------------------------------------------
-- Add products.unit_id FK pointing at units.id, back-filling from the existing
-- products.unit (varchar) column. We KEEP the varchar column for now as a soft
-- backup so reports/printouts that read it still work — it can be dropped in a
-- later migration once every reader has switched to JOINing units.
--
-- Back-fill rules:
--   - Match products.unit (case-insensitive) to units.code first; if not found,
--     try to match against units.name; if still not found, fall back to PCS.
--
-- All existing seeded products have plausible string units (pcs, kg, ltr, ...).

ALTER TABLE products
    ADD COLUMN unit_id INT NULL AFTER unit,
    ADD KEY idx_products_unit_id (unit_id),
    ADD CONSTRAINT fk_products_unit_id FOREIGN KEY (unit_id) REFERENCES units(id)
        ON UPDATE CASCADE ON DELETE RESTRICT;

-- Try matching by code (case-insensitive).
UPDATE products p
JOIN units u ON UPPER(u.code) = UPPER(p.unit)
SET p.unit_id = u.id
WHERE p.unit_id IS NULL;

-- Then by name.
UPDATE products p
JOIN units u ON UPPER(u.name) = UPPER(p.unit)
SET p.unit_id = u.id
WHERE p.unit_id IS NULL;

-- Anything still null falls back to PCS.
UPDATE products
SET unit_id = (SELECT id FROM units WHERE code = 'PCS' LIMIT 1)
WHERE unit_id IS NULL;

-- Lock the column NOT NULL now that everything's populated.
ALTER TABLE products MODIFY unit_id INT NOT NULL;

-- ---------------------------------------------------------------------
-- 0050_decimal_stock_and_line_entry_units.sql
-- ---------------------------------------------------------------------
-- Decimal stock + per-line entry units.
--
-- Part A — fix a latent bug: invoice/adjustment line quantities are DECIMAL(.,3)
-- but products.quantity_on_hand was INT, so any fractional stock movement (e.g.
-- 0.5 kg, 1.5 ltr) was silently truncated when applied to on-hand. Widen the
-- stock columns to DECIMAL(14,3) so fractional bases (kg, ltr, m) track correctly.
-- Existing integer values are preserved exactly.
--
-- Part B — record the unit a line was ENTERED in (e.g. "2 CASE") alongside the
-- stored base quantity (e.g. 24 PCS). The stored `quantity`/`unit_price` stay in
-- the product's BASE stock unit so every downstream calc (subtotal, tax, COGS,
-- moving-avg, stock) is unchanged; entry_unit_id/entry_quantity are display +
-- audit only and are NULL when the base unit was used directly.

-- Part A
ALTER TABLE products
    MODIFY quantity_on_hand    DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    MODIFY low_stock_threshold DECIMAL(14,3) NOT NULL DEFAULT 0.000;

-- Part B
ALTER TABLE purchase_invoice_lines
    ADD COLUMN entry_unit_id  INT           DEFAULT NULL AFTER quantity,
    ADD COLUMN entry_quantity DECIMAL(14,3) DEFAULT NULL AFTER entry_unit_id,
    ADD KEY idx_pil_entry_unit (entry_unit_id),
    ADD CONSTRAINT fk_pil_entry_unit FOREIGN KEY (entry_unit_id)
        REFERENCES units(id) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE sales_invoice_lines
    ADD COLUMN entry_unit_id  INT           DEFAULT NULL AFTER quantity,
    ADD COLUMN entry_quantity DECIMAL(14,3) DEFAULT NULL AFTER entry_unit_id,
    ADD KEY idx_sil_entry_unit (entry_unit_id),
    ADD CONSTRAINT fk_sil_entry_unit FOREIGN KEY (entry_unit_id)
        REFERENCES units(id) ON UPDATE CASCADE ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- 0051_purchase_return_cash_refund.sql
-- ---------------------------------------------------------------------
-- Purchase-return CASH refunds.
--
-- v1 only supported credit_note (reduce supplier payable). This adds a real cash
-- refund leg: when goods are returned but the original invoice was already (partly)
-- paid, the supplier refunds the already-paid excess in cash/bank.
--
-- Policy (chosen): NET AGAINST PAYABLE FIRST. At finalize the return's grand_total
-- first reduces the invoice's outstanding due (unchanged behaviour); only the surplus
-- (grand_total − outstanding_due, i.e. what was already paid) is refunded as cash and
-- credited to the chosen account. The account is picked per-return on the header.
--
-- cash_refund_amount is the actual cash credited at finalize (0 for credit_note or
-- when the return fully nets against an open payable). It is the single source the
-- cash-flow report and integrity reconciliation read — crediting an account without
-- this row would break the per-account balance invariant.

ALTER TABLE purchase_returns
    ADD COLUMN refund_account_kind ENUM('bank_account','cashier_drawer') DEFAULT NULL AFTER refund_method,
    ADD COLUMN refund_account_id   INT           DEFAULT NULL AFTER refund_account_kind,
    ADD COLUMN cash_refund_amount  DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER refund_account_id,
    ADD KEY idx_pr_refund_account (refund_account_kind, refund_account_id);

-- ---------------------------------------------------------------------
-- 0052_sales_return_cash_refund.sql
-- ---------------------------------------------------------------------
-- Sales-return CASH refunds (mirror of 0051 for purchases).
--
-- v1 only supported credit_note (reduce the customer's receivable). This adds a real
-- cash refund leg: when goods are returned but the customer had already (partly) paid,
-- we refund the already-collected excess in cash/bank — money OUT of a chosen account.
--
-- Policy (same as purchases): NET AGAINST RECEIVABLE FIRST. At finalize the return's
-- grand_total first reduces the invoice's outstanding due (unchanged behaviour); only
-- the surplus (grand_total − outstanding_due, i.e. what the customer had already paid)
-- is refunded as cash and DEBITED from the chosen account. Account is picked per-return.
--
-- cash_refund_amount is the actual cash debited at finalize (0 for credit_note or when
-- the return fully nets against an open receivable). It is the single source the cash-flow
-- report and integrity reconciliation read.

ALTER TABLE sales_returns
    ADD COLUMN refund_account_kind ENUM('bank_account','cashier_drawer') DEFAULT NULL AFTER refund_method,
    ADD COLUMN refund_account_id   INT           DEFAULT NULL AFTER refund_account_kind,
    ADD COLUMN cash_refund_amount  DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER refund_account_id,
    ADD KEY idx_sr_refund_account (refund_account_kind, refund_account_id);

-- ---------------------------------------------------------------------
-- 0053_return_charges_and_extras.sql
-- ---------------------------------------------------------------------
-- Return refund composition: exclude original extras by default + optional seller charge/fine.
--
-- Two behavioural changes to both sales_returns and purchase_returns:
--
-- 1. include_original_extras (default 0 = EXCLUDE): the original invoice's extra costs
--    (loading/unloading/freight/expenses) are NO LONGER refunded by default. Set to 1 on
--    a return to add the prorated share back ("re-impose if required").
--
-- 2. A seller's return charge / fine / restocking deduction that REDUCES the refund:
--      return_charge_mode   none | flat | percent
--      return_charge_value  the flat ₹ amount, or the % of returned goods value
--      return_charge_taxable + return_charge_tax_rate  (GST on the fee; defaults ON for
--                           GST bills, editable). GST is exclusive (added on top of base).
--    Computed at finalize:
--      return_charge_amount     = base, ex-GST  (sales → Other Income; purchase → Other Expense)
--      return_charge_tax_amount = GST on the base (shown now; GSTR wiring is a follow-up)
--    The refund/credit grand_total is reduced by (return_charge_amount + return_charge_tax_amount).

ALTER TABLE sales_returns
    ADD COLUMN include_original_extras  TINYINT(1)   NOT NULL DEFAULT 0 AFTER invoice_level_tax_rate,
    ADD COLUMN return_charge_mode       ENUM('none','flat','percent') NOT NULL DEFAULT 'none' AFTER include_original_extras,
    ADD COLUMN return_charge_value      DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER return_charge_mode,
    ADD COLUMN return_charge_taxable    TINYINT(1)   NOT NULL DEFAULT 0 AFTER return_charge_value,
    ADD COLUMN return_charge_tax_rate   DECIMAL(5,2)  NOT NULL DEFAULT 0.00 AFTER return_charge_taxable,
    ADD COLUMN return_charge_amount     DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER return_charge_tax_rate,
    ADD COLUMN return_charge_tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER return_charge_amount;

ALTER TABLE purchase_returns
    ADD COLUMN include_original_extras  TINYINT(1)   NOT NULL DEFAULT 0 AFTER invoice_level_tax_rate,
    ADD COLUMN return_charge_mode       ENUM('none','flat','percent') NOT NULL DEFAULT 'none' AFTER include_original_extras,
    ADD COLUMN return_charge_value      DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER return_charge_mode,
    ADD COLUMN return_charge_taxable    TINYINT(1)   NOT NULL DEFAULT 0 AFTER return_charge_value,
    ADD COLUMN return_charge_tax_rate   DECIMAL(5,2)  NOT NULL DEFAULT 0.00 AFTER return_charge_taxable,
    ADD COLUMN return_charge_amount     DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER return_charge_tax_rate,
    ADD COLUMN return_charge_tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER return_charge_amount;

-- ---------------------------------------------------------------------
-- 0054_init_stock_movements.sql
-- ---------------------------------------------------------------------
-- Perpetual-inventory stock ledger.
--
-- Until now products.quantity_on_hand was a mutable running total with NO movement
-- ledger behind it (applyStockIn wrote a price-history row, but applyStockOut wrote
-- nothing) — so stock could not be reconciled the way every money account can.
--
-- This table records ONE signed row per stock event. InventoryService::applyStockIn /
-- applyStockOut (the only two places that mutate quantity_on_hand) each append a row.
-- Invariant: products.quantity_on_hand == SUM(stock_movements.quantity_delta) per product.
--
-- quantity_delta is signed: + for 'in'/'opening', − for 'out'. balance_after snapshots
-- the resulting on-hand for fast reporting. source/source_ref_* tag the originating event.

CREATE TABLE stock_movements (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT           NOT NULL,
    direction       ENUM('in','out','opening') NOT NULL,
    quantity_delta  DECIMAL(14,3) NOT NULL,                 -- signed
    unit_cost       DECIMAL(14,4) NOT NULL DEFAULT 0.0000,  -- landed/avg cost at the event
    balance_after   DECIMAL(14,3) DEFAULT NULL,             -- on-hand after this event
    source          VARCHAR(40)   NOT NULL DEFAULT 'other', -- purchase/sale/return/adjustment/production/rebate/opening/...
    source_ref_type VARCHAR(60)   DEFAULT NULL,
    source_ref_id   BIGINT        DEFAULT NULL,
    notes           VARCHAR(255)  DEFAULT NULL,
    created_by      INT           DEFAULT NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sm_product (product_id),
    KEY idx_sm_source (source_ref_type, source_ref_id),
    KEY idx_sm_date (created_at),
    CONSTRAINT fk_sm_product FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opening snapshot: seed one row per existing product = its current on-hand, so the
-- ledger starts reconciled (SUM(delta) == quantity_on_hand) without historical movements.
INSERT INTO stock_movements
    (product_id, direction, quantity_delta, unit_cost, balance_after, source, notes)
SELECT id, 'opening', quantity_on_hand, moving_avg_cost, quantity_on_hand, 'opening',
       'Opening snapshot at stock-ledger introduction'
FROM products;

-- ---------------------------------------------------------------------
-- 0055_due_term_tagging.sql
-- ---------------------------------------------------------------------
-- CR-3 Due List: Short-term / Long-term tagging.
--
-- The owner classifies dues MANUALLY (chosen over ageing-based or credit-terms-based
-- classification). Two levels, resolved at report time:
--
--   1. sales_invoices.due_term      — explicit per-invoice tag. Wins when set.
--   2. customers.default_due_term   — the customer's default. Applies to every invoice
--                                     of theirs that carries no explicit tag, and to
--                                     that customer's legacy_obligations rows.
--   3. NULL at both levels          — the due lands in the "Untagged" bucket. It is
--                                     still counted in the Total column; it is simply
--                                     not attributed to short or long.
--
-- Both columns are NULLABLE with no default, so:
--   - existing rows need NO backfill and keep behaving exactly as before,
--   - nothing is silently misclassified (untagged is a visible state, not a guess),
--   - rolling back is `DROP COLUMN` on two columns no financial code path reads.
--
-- due_term is a NON-FINANCIAL attribute. It never participates in any total, tax, or
-- balance computation, which is why it is editable on a finalized invoice even though
-- finalized invoices are otherwise immutable.

ALTER TABLE sales_invoices
    ADD COLUMN due_term ENUM('short', 'long') DEFAULT NULL AFTER payment_status;

ALTER TABLE customers
    ADD COLUMN default_due_term ENUM('short', 'long') DEFAULT NULL AFTER credit_limit;

-- Report filters group by term over the open-due set, so index the pair that the
-- due-list WHERE clause actually uses.
ALTER TABLE sales_invoices
    ADD KEY idx_si_due_term (due_term, status, due_amount);

-- ---------------------------------------------------------------------
-- 0056_init_rate_changes.sql
-- ---------------------------------------------------------------------
-- CR-4 Rate Management — category / subcategory-wise selling-price changes.
--
-- A "rate change" is a one-time BULK UPDATE of products.default_selling_price across a
-- category / subcategory / brand selection. Products keep owning their own price; this
-- is not an inherited rule, so no pricing read path anywhere else has to change.
--
-- WHY A DEDICATED AUDIT TABLE (and not product_price_history):
-- product_price_history records LANDED COST — it feeds moving-average cost,
-- latest_purchase_landed_cost and the market-value columns on stock reports. Selling
-- prices must never be written there or those figures would be corrupted. Selling-price
-- history therefore gets its own trail here.
--
-- REVERSIBILITY: every affected product's before/after price is stored per line, so a
-- whole batch can be reverted in one action. Revert refuses when a product's current
-- price no longer matches what the batch set (someone edited it since), mirroring the
-- drift-detection that brand-rebate void already uses rather than silently clobbering
-- a newer deliberate price.
--
-- Nothing here touches stock, moving_avg_cost, or any posted document, so no accounting
-- invariant can be affected. Rolling the feature back is dropping these two tables.

CREATE TABLE rate_change_batches (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    batch_number        VARCHAR(100) NOT NULL,                -- GP-RATE/{FY}/{NNNNN}
    change_date         DATE         NOT NULL,

    -- Selection used (all optional; NULL = "not narrowed by this dimension").
    category_id         INT          DEFAULT NULL,
    subcategory_id      INT          DEFAULT NULL,
    brand_id            INT          DEFAULT NULL,

    -- percent    : new = old × (1 + value/100)   (value may be negative)
    -- flat_amount: new = old + value             (value may be negative)
    -- set_price  : new = value                   (value must be >= 0)
    mode                ENUM('percent', 'flat_amount', 'set_price') NOT NULL,
    value               DECIMAL(12,2) NOT NULL,
    round_to_rupee      TINYINT(1)   NOT NULL DEFAULT 0,

    products_affected   INT          NOT NULL DEFAULT 0,
    remarks             VARCHAR(500) DEFAULT NULL,

    status              ENUM('applied', 'reverted') NOT NULL DEFAULT 'applied',
    reverted_at         DATETIME     DEFAULT NULL,
    reverted_by         INT          DEFAULT NULL,

    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          INT          DEFAULT NULL,

    UNIQUE KEY uniq_rate_change_number (batch_number),
    KEY idx_rcb_date (change_date),
    KEY idx_rcb_status (status),
    KEY idx_rcb_category (category_id),
    KEY idx_rcb_subcategory (subcategory_id),
    KEY idx_rcb_brand (brand_id),
    CONSTRAINT fk_rcb_category    FOREIGN KEY (category_id)    REFERENCES categories(id)    ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_rcb_subcategory FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_rcb_brand       FOREIGN KEY (brand_id)       REFERENCES brands(id)        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per product touched. old_price is what revert restores; new_price is what
-- revert checks the product still carries before restoring.
CREATE TABLE rate_change_lines (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    batch_id            INT           NOT NULL,
    product_id          INT           NOT NULL,
    old_price           DECIMAL(12,2) NOT NULL,
    new_price           DECIMAL(12,2) NOT NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rcl_batch (batch_id),
    KEY idx_rcl_product (product_id),
    CONSTRAINT fk_rcl_batch   FOREIGN KEY (batch_id)   REFERENCES rate_change_batches(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rcl_product FOREIGN KEY (product_id) REFERENCES products(id)            ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 0057_init_brand_purchase_slabs.sql
-- ---------------------------------------------------------------------
-- CR-1b Brand-wise purchase rate slabs ("under / above" quantity thresholds).
--
-- CR-1's first half — "purchase entries should be entered brand-wise" — was already
-- true before this migration: purchase_invoices.brand_id is NOT NULL, single-brand per
-- invoice is enforced (D14), and the line-entry product list is already filtered to the
-- invoice's brand. What was missing is the automatic rate.
--
-- SHAPE: thresholds are defined ONCE PER BRAND; the rate is per product within a slab.
-- A brand typically negotiates the same break points ("under 50 / 50 and above") across
-- its whole range while each item has its own price, so this avoids re-entering the same
-- thresholds on every product.
--
--   brand_purchase_slabs   Berger: [0, 50)  "Under 50"
--                          Berger: [50, ∞)  "50 and above"
--   product_slab_rates     Silk 1L  × "Under 50"      → 148.00
--                          Silk 1L  × "50 and above"  → 142.00
--
-- max_qty NULL means unbounded — the "and above" slab. Ranges are half-open
-- [min_qty, max_qty) so adjacent slabs meet without overlapping or leaving a gap.
--
-- ROLLBACK / SAFETY (this is why the feature is safe to ship to a live system):
--   * Gated by the system setting `enable_brand_slab_rates`, seeded to '0' (off).
--   * Slabs only PREFILL the unit_price field on the purchase line form. They never
--     lock it, never post-process a saved line, and PurchaseInvoiceService's finalize
--     math is not touched at all — the slab merely decides what number starts in an
--     existing field, which the user can overwrite.
--   * Therefore nothing already posted can change, and turning the flag off restores
--     today's behaviour exactly. Dropping these two tables removes the feature; no
--     other table references them.

CREATE TABLE brand_purchase_slabs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    brand_id        INT           NOT NULL,
    label           VARCHAR(100)  NOT NULL,              -- e.g. 'Under 50', '50 and above'
    min_qty         DECIMAL(14,3) NOT NULL DEFAULT 0.000, -- inclusive lower bound
    max_qty         DECIMAL(14,3) DEFAULT NULL,           -- exclusive upper bound; NULL = unbounded
    sort_order      INT           NOT NULL DEFAULT 0,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by      INT           DEFAULT NULL,
    updated_by      INT           DEFAULT NULL,
    KEY idx_bps_brand (brand_id),
    KEY idx_bps_lookup (brand_id, status, min_qty),
    CONSTRAINT fk_bps_brand FOREIGN KEY (brand_id) REFERENCES brands(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-product rate inside one slab. A product with no row for a slab simply has no
-- suggestion at that quantity — the form is left alone rather than guessing.
CREATE TABLE product_slab_rates (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT           NOT NULL,
    slab_id         INT           NOT NULL,
    rate            DECIMAL(12,4) NOT NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by      INT           DEFAULT NULL,
    UNIQUE KEY uniq_psr_product_slab (product_id, slab_id),
    KEY idx_psr_slab (slab_id),
    CONSTRAINT fk_psr_product FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_psr_slab    FOREIGN KEY (slab_id)    REFERENCES brand_purchase_slabs(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Off by default: uploading this code changes nothing until the setting is switched on.
INSERT INTO system_settings (setting_key, value, description) VALUES
    ('enable_brand_slab_rates', '0',
     'Auto-suggest purchase rates from brand quantity slabs (1 = yes, 0 = no). Suggestion only — the price stays editable.');

-- ---------------------------------------------------------------------
-- 0058_sales_line_commission_override.sql
-- ---------------------------------------------------------------------
-- CR-6 Sell-wise commission — a per-line commission rate override.
--
-- Commission was already snapshotted PER LINE (sales_invoice_lines.commission_rate +
-- commission_amount, with per-line salesman attribution per D58). What was missing is
-- the ability to set the rate for a particular sale: the rate could only come from the
-- salesman master record at finalize time.
--
-- RESOLUTION AT FINALIZE:
--   commission_rate = COALESCE(commission_rate_override, salesmen.commission_rate)
--
-- NULL — the default for every existing row and every new line where nobody sets an
-- override — means "behave exactly as before". That is the rollback property: this
-- column changes nothing on its own, and no historical invoice is affected. It is also
-- why no back-fill is needed.
--
-- SALES RETURNS NEED NO CHANGE: SalesReturnService already reverses commission using
-- the rate SNAPSHOTTED on the originating sale line (sales_return_lines.commission_rate
-- is copied from sales_invoice_lines.commission_rate), so an overridden rate flows into
-- the reversal automatically and a fully-returned line still nets to zero commission.
--
-- The column is only read at finalize; drafts carry it untouched. Rolling back is
-- dropping one nullable column that no other code path depends on.

ALTER TABLE sales_invoice_lines
    ADD COLUMN commission_rate_override DECIMAL(5,2) DEFAULT NULL AFTER commission_rate;

-- ---------------------------------------------------------------------
-- 0059_optional_freetext_phone.sql
-- ---------------------------------------------------------------------
-- CR-10 (phone half) — customer / supplier phone becomes optional free text.
--
-- CR-10's other asks were already satisfied before this migration:
--   * products save with or without GST details — products.hsn_code is nullable and
--     tax_rate defaults to 0, and neither is in ProductController's required() chain;
--   * invoices support tax_treatment='no_tax' and bill_type='bill_of_supply';
--   * GSTIN and state are already optional on customers and suppliers.
-- What actually blocked quick entry was the phone field: required, and validated
-- against a strict Indian 10-digit pattern.
--
-- WHY THE COLUMN MUST BECOME NULLABLE: dropping `required` alone is not enough. With
-- `phone VARCHAR(20) NOT NULL` plus UNIQUE, a blank phone stores '' — and only ONE row
-- can hold ''. The second customer saved without a phone would fail with a confusing
-- "phone already used" error. NULL is exempt from UNIQUE in MySQL, so storing NULL for
-- blank lets any number of records have no phone while real numbers stay unique.
--
-- WHAT IS DELIBERATELY NOT DONE HERE: the UNIQUE index stays.
--   1. It still expresses the rule the owner set out in migration 0007 — a phone number
--      identifies one entity within a table.
--   2. Dropping it is close to one-way: once duplicate phones exist in live data, it
--      cannot be re-added without manual cleanup.
--   3. CustomerRepository::findByNameAndPhone() auto-links salesman and employee mirror
--      customers by exact name+phone match. Duplicate phones make that lookup ambiguous
--      and could mis-link or duplicate a salesman's mirror record. That linking has to
--      be reworked onto an explicit customer choice BEFORE the index can safely go.
-- So this migration removes the format and presence constraints (the actual complaint)
-- and leaves the uniqueness question to a later, separately-reviewed change.
--
-- Widening NOT NULL -> NULL is non-destructive: every existing row remains valid and no
-- data is touched. It stays reversible for as long as no NULLs have been saved.
--
-- salesmen, employees and users keep a required, validated phone: those are internal
-- records, and the mirror-link logic above depends on them having a real number.

ALTER TABLE customers
    MODIFY COLUMN phone VARCHAR(20) DEFAULT NULL;

ALTER TABLE suppliers
    MODIFY COLUMN phone VARCHAR(20) DEFAULT NULL;

-- ---------------------------------------------------------------------
-- 0060_protected_superadmin.sql
-- ---------------------------------------------------------------------
-- Protected "break-glass" administrator account.
--
-- Purpose: guarantee there is always one administrator that cannot be locked out by
-- ordinary in-app administration — no accidental deactivation, no role downgrade, no
-- rename. It exists so a lockout never again requires shell access to recover from.
--
-- WHAT "PROTECTED" MEANS (enforced by UserController):
--   cannot be deactivated          — toggleStatus refuses
--   cannot lose the admin role     — update refuses a role change
--   cannot be renamed              — update refuses a username change
--   CAN still change its password  — deliberately still allowed
--   CAN still edit name/email/etc  — cosmetic fields are unrestricted
--
-- WHY THE PASSWORD MUST STAY CHANGEABLE: an account that can never be disabled AND
-- never have its password changed is an unfixable backdoor. Keeping the password
-- editable means a leaked credential can always be rotated, which is the one control
-- that still works on an account you cannot switch off.
--
-- SECURITY NOTE: this is a permanent, always-enabled administrator. Treat its password
-- like a root password — set a strong one immediately, store it in the vault, and do
-- not use it for day-to-day work. Use a normal admin account for that.
--
-- The flag is a column rather than a hard-coded username so the protection is data, not
-- a magic string, and can be granted to (or removed from) an account later by an
-- operator with database access — which is exactly the level of privilege that should
-- be required to change it.

ALTER TABLE users
    ADD COLUMN is_protected TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

-- Create the account only if that username is free, and only if the admin role exists.
-- The password hash below is bcrypt; the matching temporary password is delivered
-- separately and MUST be changed at first login.
INSERT INTO users (name, phone, username, password_hash, role_id, status, is_protected)
SELECT 'Super Administrator',
       'SA-0000000000',
       'superadmin',
       '$2y$10$GGCgTPjuwX3lnJKIQOZj..adxWNd8yIQPRDNrVvRLLPJq36zUXr46',
       r.id,
       'active',
       1
  FROM roles r
 WHERE r.role_key = 'admin'
   AND NOT EXISTS (SELECT 1 FROM users u WHERE u.username = 'superadmin')
 LIMIT 1;

-- ---------------------------------------------------------------------
-- 0061_rename_tenant_protected_admin.sql
-- ---------------------------------------------------------------------
-- Rename the tenant-level protected account from 'superadmin' to 'owner'.
--
-- WHY: migration 0060 created a protected break-glass admin called 'superadmin'
-- inside the (then single) database. Under multi-tenancy that migration runs for
-- EVERY tenant, so the name ends up meaning three different things at once:
--
--   control database  -> superadmin  : sits ABOVE every tenant, manages them
--   tenant A database -> superadmin  : a protected admin of tenant A only
--   tenant B database -> superadmin  : a protected admin of tenant B only
--
-- Three unrelated accounts sharing one username is a support problem waiting to
-- happen: signing in to a business with the control-plane password fails in a way
-- that looks like a broken password rather than the wrong account.
--
-- The requirement is that tenant admins sit UNDER the superadmin, so the name
-- 'superadmin' is reserved for the control plane and the per-tenant account is
-- renamed to 'owner' — which is what it actually is: the business's own
-- protected administrator.
--
-- WHAT DOES NOT CHANGE: the account keeps its id, its password, its admin role
-- and its protection (cannot be deactivated, renamed through the UI, or have its
-- role changed; its password can still be rotated). Only the username changes.
--
-- SAFE AND IDEMPOTENT: renames only a protected account still called
-- 'superadmin', and only when the name 'owner' is free in that database. Running
-- it twice does nothing the second time.

UPDATE users
   SET username = 'owner'
 WHERE username = 'superadmin'
   AND is_protected = 1
   AND NOT EXISTS (SELECT 1 FROM (SELECT username FROM users) AS u WHERE u.username = 'owner');

-- ---------------------------------------------------------------------
-- Record every migration as applied.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (filename) VALUES
    ('0001_init_users.sql'),
    ('0002_init_categories.sql'),
    ('0003_init_subcategories.sql'),
    ('0004_init_brands.sql'),
    ('0005_init_products.sql'),
    ('0006_init_suppliers.sql'),
    ('0007_enforce_phone_uniqueness.sql'),
    ('0008_init_salesmen.sql'),
    ('0009_init_customers.sql'),
    ('0010_init_employees.sql'),
    ('0011_init_system_settings.sql'),
    ('0012_init_purchase_invoices.sql'),
    ('0013_init_purchase_payments.sql'),
    ('0014_add_latest_purchase_price_to_products.sql'),
    ('0015_init_sales_invoices.sql'),
    ('0016_init_sales_payments.sql'),
    ('0017_init_product_compositions.sql'),
    ('0018_init_production_orders.sql'),
    ('0019_sales_lines_composite_support.sql'),
    ('0020_init_product_price_history.sql'),
    ('0021_sales_invoice_number_nullable.sql'),
    ('0022_sales_invoice_final_discount.sql'),
    ('0023_sales_invoice_extras.sql'),
    ('0024_sales_invoice_extras_drop_is_taxable.sql'),
    ('0025_purchase_invoice_final_discount.sql'),
    ('0026_init_sales_returns.sql'),
    ('0027_init_purchase_returns.sql'),
    ('0028_init_bank_accounts_drawers_transfers.sql'),
    ('0029_init_operating_expenses.sql'),
    ('0030_init_recurring_expenses.sql'),
    ('0031_payments_link_account.sql'),
    ('0032_init_capital_movements.sql'),
    ('0033_init_commission_settlements.sql'),
    ('0034_init_customer_advances.sql'),
    ('0035_init_supplier_advances.sql'),
    ('0036_init_brand_rebates.sql'),
    ('0037_init_stock_adjustments.sql'),
    ('0038_init_activity_log.sql'),
    ('0039_init_legacy_records.sql'),
    ('0040_refactor_legacy_to_generic.sql'),
    ('0041_brand_rebates_free_stock_avg_snapshot.sql'),
    ('0042_init_role_permissions.sql'),
    ('0043_init_roles_table.sql'),
    ('0044_redesign_role_permissions.sql'),
    ('0045_link_legacy_to_live.sql'),
    ('0046_unify_legacy_with_live_entities.sql'),
    ('0047_product_price_history_avg_snapshot.sql'),
    ('0048_init_units_and_product_units.sql'),
    ('0049_products_unit_id_fk.sql'),
    ('0050_decimal_stock_and_line_entry_units.sql'),
    ('0051_purchase_return_cash_refund.sql'),
    ('0052_sales_return_cash_refund.sql'),
    ('0053_return_charges_and_extras.sql'),
    ('0054_init_stock_movements.sql'),
    ('0055_due_term_tagging.sql'),
    ('0056_init_rate_changes.sql'),
    ('0057_init_brand_purchase_slabs.sql'),
    ('0058_sales_line_commission_override.sql'),
    ('0059_optional_freetext_phone.sql'),
    ('0060_protected_superadmin.sql'),
    ('0061_rename_tenant_protected_admin.sql');

-- ---------------------------------------------------------------------
-- Default permissions for the non-admin roles.
--
-- The migrations create the roles but not their grants - normally
-- `php database/seed_permissions.php` fills these in, which needs a shell.
-- Baked in here instead, so a new tenant's subadmin and cashier users work
-- immediately rather than signing in to an empty sidebar.
--
-- The 'admin' role needs no rows: it is a master switch, always allowed.
-- Role ids are looked up by role_key so they never have to be guessed.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_key) VALUES
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'dashboard.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'dashboard_alerts.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'dashboard_sales.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'dashboard_purchase.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'dashboard_profit_loss.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'dashboard_customers.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'dashboard_salesmen.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'dashboard_inventory.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'dashboard_cash_bank.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'categories.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'categories.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'categories.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'subcategories.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'subcategories.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'subcategories.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'brands.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'brands.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'brands.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'units.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'units.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'units.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'products.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'products.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'products.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'production_orders.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'production_orders.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'suppliers.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'suppliers.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'suppliers.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'salesmen.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'salesmen.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'salesmen.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'customers.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'customers.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'customers.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'employees.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'employees.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'employees.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_invoices.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_invoices.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_invoices.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_invoices.finalize'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_invoices.cancel'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_invoices.print'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_returns.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_returns.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_returns.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_returns.finalize'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_returns.cancel'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_returns.print'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_payments.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_payments.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_payments.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'purchase_payments.print'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_invoices.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_invoices.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_invoices.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_invoices.finalize'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_invoices.cancel'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_invoices.print'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_returns.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_returns.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_returns.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_returns.finalize'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_returns.cancel'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_returns.print'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_payments.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_payments.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_payments.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'sales_payments.print'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'bank_accounts.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'bank_accounts.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'bank_accounts.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'cashier_drawers.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'cashier_drawers.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'cashier_drawers.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'inter_account_transfers.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'inter_account_transfers.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'operating_expense_categories.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'operating_expense_categories.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'operating_expense_categories.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'operating_expenses.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'operating_expenses.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'operating_expenses.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'operating_expenses.print'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'recurring_expenses.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'recurring_expenses.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'recurring_expenses.update'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'capital_movements.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'capital_movements.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'passbook.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'commission_settlements.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'commission_settlements.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'customer_advances.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'customer_advances.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'customer_payments.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'customer_payments.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'supplier_advances.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'supplier_advances.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'supplier_payments.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'supplier_payments.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'brand_rebates.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'brand_rebates.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'stock_adjustments.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'stock_adjustments.create'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'legacy_records.view'),
    ((SELECT id FROM roles WHERE role_key = 'subadmin'), 'legacy_records.create'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'dashboard.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'dashboard_alerts.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'dashboard_sales.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'dashboard_inventory.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'categories.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'subcategories.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'brands.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'units.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'products.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'production_orders.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'suppliers.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'salesmen.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'customers.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'employees.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'purchase_invoices.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'purchase_invoices.print'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'purchase_returns.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'purchase_returns.print'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'purchase_payments.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'purchase_payments.print'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_invoices.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_invoices.create'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_invoices.update'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_invoices.finalize'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_invoices.print'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_returns.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_returns.create'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_returns.update'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_returns.finalize'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_returns.print'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_payments.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_payments.create'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_payments.update'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'sales_payments.print'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'bank_accounts.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'cashier_drawers.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'inter_account_transfers.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'operating_expense_categories.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'operating_expenses.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'operating_expenses.print'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'recurring_expenses.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'capital_movements.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'passbook.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'commission_settlements.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'customer_advances.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'customer_advances.create'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'customer_payments.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'customer_payments.create'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'supplier_advances.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'supplier_advances.create'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'supplier_payments.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'supplier_payments.create'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'brand_rebates.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'brand_rebates.create'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'stock_adjustments.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'legacy_records.view'),
    ((SELECT id FROM roles WHERE role_key = 'cashier'), 'legacy_records.create');

-- ---------------------------------------------------------------------
-- CONFIRM. Expect: 61 migrations, 58 tables, 1 protected 'owner'.
-- ---------------------------------------------------------------------
SELECT (SELECT COUNT(*) FROM migrations)                                   AS migrations_recorded,
       (SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE')    AS tables_created,
       (SELECT COUNT(*) FROM users WHERE is_protected = 1)                 AS protected_admins,
       (SELECT username FROM users WHERE is_protected = 1 LIMIT 1)         AS admin_username,
       (SELECT COUNT(*) FROM roles)                                        AS roles_seeded;
