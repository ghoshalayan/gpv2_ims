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
