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
