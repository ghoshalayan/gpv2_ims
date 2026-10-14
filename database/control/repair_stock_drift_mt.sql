-- =====================================================================
--  REPAIR: stock ledger drift  (multi-tenant version)
-- =====================================================================
--  Fixes the Integrity Check finding
--     "Stock: quantity_on_hand vs movement ledger"
--
--  WHY THE OLD repair_stock_drift.sql DOES NOTHING NOW
--  It reads `products` and `stock_movements`, which are now per-business
--  VIEWS. In phpMyAdmin no business is bound, so the views are empty and
--  the old script "succeeds" while touching zero rows.
--
--  CAUSE: products created with an opening quantity got quantity_on_hand
--  but no matching 'opening' row in the ledger. The stored quantity is
--  correct - each product's first sale recorded the balance it left, which
--  proves the opening amount. This ADDS the missing opening row and never
--  changes quantity_on_hand.
--
--  HOW TO RUN: paste the WHOLE file and press Go.
--
--  SAFE BY CONSTRUCTION
--    * It only touches the business named on the SET line (1 = GP Enterprise).
--    * If phpMyAdmin skips the SET line, @app_tenant is NULL, the INSERT
--      matches nothing, and nothing is written. It cannot stamp rows to the
--      wrong business or leave them unstamped.
--    * Safe to re-run: once balanced, nothing drifts, so nothing is inserted.
--
--  >>> Take a database export first. <<<
--  Find & Replace srasinge1_gp if your database is named differently.
-- =====================================================================

SET @app_tenant = 1;

INSERT INTO `srasinge1_gp`.`stock_movements__data`
       (product_id, direction, quantity_delta, unit_cost, balance_after,
        source, notes, created_at)
SELECT d.id,
       'opening',
       d.missing_opening,
       d.moving_avg_cost,
       d.missing_opening,          -- the opening is the first entry, so the balance after it IS the opening
       'opening',
       'Repair: opening stock was not recorded in the ledger when the product was created',
       d.created_at                -- dated at creation so it sorts before the first sale
  FROM (
        SELECT p.id,
               p.moving_avg_cost,
               p.created_at,
               p.quantity_on_hand - COALESCE(SUM(sm.quantity_delta), 0) AS missing_opening
          FROM `srasinge1_gp`.`products__data` p
          LEFT JOIN `srasinge1_gp`.`stock_movements__data` sm ON sm.product_id = p.id
         WHERE p.tenant_id = @app_tenant
         GROUP BY p.id, p.quantity_on_hand, p.moving_avg_cost, p.created_at
        HAVING p.quantity_on_hand - COALESCE(SUM(sm.quantity_delta), 0) > 0.001
           AND SUM(sm.direction = 'opening') IS NOT TRUE   -- only products with NO opening row
       ) AS d;

-- AFTER: must return ZERO rows.
SELECT p.tenant_id, p.id, p.name,
       p.quantity_on_hand                       AS stored_qty,
       COALESCE(SUM(sm.quantity_delta), 0)      AS ledger_qty
  FROM `srasinge1_gp`.`products__data` p
  LEFT JOIN `srasinge1_gp`.`stock_movements__data` sm ON sm.product_id = p.id
 GROUP BY p.tenant_id, p.id, p.name, p.quantity_on_hand
HAVING ABS(p.quantity_on_hand - COALESCE(SUM(sm.quantity_delta), 0)) > 0.001;
