-- Snapshot moving_avg before/after a free_stock rebate post, so void can deterministically
-- reverse the avg shift. Mirrors the brand_rebate_cost_adjustments table's pre/after pattern
-- but stored inline since there's only one product per free_stock rebate.
--
-- NULL on existing posted rebates = pre-fix data; void falls back to legacy behavior
-- (stockOut only, leaves avg shifted). New posts always populate these.

ALTER TABLE brand_rebates
    ADD COLUMN free_stock_avg_before DECIMAL(14,4) DEFAULT NULL AFTER free_value_per_unit,
    ADD COLUMN free_stock_avg_after  DECIMAL(14,4) DEFAULT NULL AFTER free_stock_avg_before;
