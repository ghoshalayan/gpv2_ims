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
