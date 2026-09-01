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
