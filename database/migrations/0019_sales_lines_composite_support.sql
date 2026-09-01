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
