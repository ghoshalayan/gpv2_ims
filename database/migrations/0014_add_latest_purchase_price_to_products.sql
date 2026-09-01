-- Track each product's latest purchase landed cost + when it was last seen.
-- Updated by InventoryService on every purchase stock-in event.
-- Used for current-market-value calculations and (later) composite product pricing.

ALTER TABLE products
    ADD COLUMN latest_purchase_landed_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER moving_avg_cost,
    ADD COLUMN latest_purchase_at DATETIME DEFAULT NULL AFTER latest_purchase_landed_cost;
