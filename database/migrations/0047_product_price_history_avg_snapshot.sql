-- Snapshot columns on product_price_history so the user can see HOW each
-- moving-avg change happened: prior state → event → new state.
--
-- Why all six columns: a row that records "moving_avg_cost is now X" tells you
-- the result, but not the math. With these columns the UI can render
--   prior_qty=100 @ avg=₹40   +   event_qty=50 @ ₹50   →   new_qty=150 @ avg=₹43.33
-- without re-deriving anything.
--
-- All six are nullable so old rows survive the migration; the view shows "—"
-- for unsnapshotted rows. A separate PHP back-fill script
-- (database/backfill_product_price_history.php) walks the existing data and
-- populates them retroactively where the source row provides enough context.
--
-- Going forward, InventoryService::applyStockIn / applyStockOut populate them
-- on every event (purchase, return, adjustment, free rebate, manual change).

ALTER TABLE product_price_history
    ADD COLUMN prior_qty       DECIMAL(14,3) DEFAULT NULL AFTER landed_cost,
    ADD COLUMN prior_avg       DECIMAL(14,4) DEFAULT NULL AFTER prior_qty,
    ADD COLUMN event_qty       DECIMAL(14,3) DEFAULT NULL AFTER prior_avg,
    ADD COLUMN event_unit_cost DECIMAL(14,4) DEFAULT NULL AFTER event_qty,
    ADD COLUMN new_qty         DECIMAL(14,3) DEFAULT NULL AFTER event_unit_cost,
    ADD COLUMN new_avg         DECIMAL(14,4) DEFAULT NULL AFTER new_qty;
