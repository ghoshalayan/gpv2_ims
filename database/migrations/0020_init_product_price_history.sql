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
