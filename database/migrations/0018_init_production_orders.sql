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
