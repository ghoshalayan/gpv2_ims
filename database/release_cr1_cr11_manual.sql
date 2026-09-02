-- =====================================================================
--  GP ENTERPRISE IMS — CR-1..CR-11 release, SSH-FREE SCHEMA UPGRADE
-- =====================================================================
--  Run this ONCE in phpMyAdmin (or any SQL console) against the LIVE
--  database, when you cannot reach a shell to run:
--      php database/migrate.php
--
--  It contains migrations 0055-0059 verbatim, followed by the rows that
--  record them in the `migrations` tracking table -- so a later
--  migrate.php over SSH sees them as already applied and skips them.
--
--  BEFORE RUNNING: take a full database export (phpMyAdmin -> Export).
--  That backup is the ONLY rollback for these schema changes.
--
--  SAFE TO RE-RUN? NO. Executing it twice will error on the first
--  duplicate object (that is intentional -- it fails loudly rather than
--  corrupting anything). If it half-applies, restore the export.
--
--  Every statement is additive: new tables, new NULLable columns, and
--  one widening of NOT NULL -> NULL. Nothing is dropped and no existing
--  row is rewritten.
-- =====================================================================

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
-- Record these migrations as applied, so migrate.php skips them later.
-- INSERT IGNORE: harmless if a row somehow already exists.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO migrations (filename) VALUES
    ('0055_due_term_tagging.sql'),
    ('0056_init_rate_changes.sql'),
    ('0057_init_brand_purchase_slabs.sql'),
    ('0058_sales_line_commission_override.sql'),
    ('0059_optional_freetext_phone.sql');

-- ---------------------------------------------------------------------
-- Verify: should list all five, and enable_brand_slab_rates should be 0.
-- ---------------------------------------------------------------------
SELECT filename, applied_at FROM migrations
 WHERE filename >= '0055' ORDER BY filename;

SELECT setting_key, value FROM system_settings
 WHERE setting_key = 'enable_brand_slab_rates';
