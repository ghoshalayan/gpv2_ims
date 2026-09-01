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
