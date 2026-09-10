-- =====================================================================
--  REPAIR: stock ledger drift on products created with opening stock
-- =====================================================================
--  Fixes the Integrity Check finding
--     "Stock: quantity_on_hand vs movement ledger"
--
--  CAUSE (a bug in the code, now fixed for future products):
--  Creating a product with an opening quantity wrote quantity_on_hand
--  straight onto the product row but never wrote the matching row into
--  stock_movements. So the ledger has been behind the stored quantity by
--  exactly the opening amount ever since, for every product created with
--  opening stock after the ledger was introduced (migration 0054).
--
--  WHICH NUMBER IS RIGHT? The STORED quantity. It is what the app has
--  been using all along for stock levels, oversell checks and valuation,
--  and it is what your physical stock was counted into. The ledger is
--  simply missing its first entry. This script therefore ADDS the
--  missing opening row -- it never changes quantity_on_hand.
--
--  BEFORE RUNNING: take a database export (phpMyAdmin -> Export).
--  Run diagnose_stock_drift.sql first and keep the output.
--
--  SAFE TO RE-RUN: yes. After the first run nothing drifts, so the
--  INSERT matches no rows and does nothing.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. BEFORE — what is about to be corrected. Keep this output.
-- ---------------------------------------------------------------------
SELECT p.id,
       p.name,
       p.quantity_on_hand                                       AS stored_qty,
       COALESCE(SUM(sm.quantity_delta), 0)                      AS ledger_qty,
       p.quantity_on_hand - COALESCE(SUM(sm.quantity_delta), 0) AS missing_opening
  FROM products p
  LEFT JOIN stock_movements sm ON sm.product_id = p.id
 GROUP BY p.id, p.name, p.quantity_on_hand
HAVING ABS(p.quantity_on_hand - COALESCE(SUM(sm.quantity_delta), 0)) > 0.001
 ORDER BY p.id;


-- ---------------------------------------------------------------------
-- 2. THE REPAIR — one back-dated 'opening' row per drifted product,
--    for exactly the missing amount.
--
--    Only products that are actually out of balance are touched. The
--    unit cost is taken from the product's own moving average, so stock
--    valuation is unaffected.
-- ---------------------------------------------------------------------
INSERT INTO stock_movements
       (product_id, direction, quantity_delta, unit_cost, balance_after,
        source, notes, created_at)
SELECT d.id,
       'opening',
       d.missing_opening,
       d.moving_avg_cost,
       d.quantity_on_hand,
       'opening',
       'Repair: opening stock was not recorded in the ledger when the product was created',
       d.created_at
  FROM (
        SELECT p.id,
               p.quantity_on_hand,
               p.moving_avg_cost,
               p.created_at,
               p.quantity_on_hand - COALESCE(SUM(sm.quantity_delta), 0) AS missing_opening
          FROM products p
          LEFT JOIN stock_movements sm ON sm.product_id = p.id
         GROUP BY p.id, p.quantity_on_hand, p.moving_avg_cost, p.created_at
        HAVING ABS(p.quantity_on_hand - COALESCE(SUM(sm.quantity_delta), 0)) > 0.001
       ) AS d;


-- ---------------------------------------------------------------------
-- 3. AFTER — must return ZERO rows. If anything is still listed, stop
--    and report it; do not run this script again.
-- ---------------------------------------------------------------------
SELECT p.id,
       p.name,
       p.quantity_on_hand                                       AS stored_qty,
       COALESCE(SUM(sm.quantity_delta), 0)                      AS ledger_qty,
       p.quantity_on_hand - COALESCE(SUM(sm.quantity_delta), 0) AS still_drifted
  FROM products p
  LEFT JOIN stock_movements sm ON sm.product_id = p.id
 GROUP BY p.id, p.name, p.quantity_on_hand
HAVING ABS(p.quantity_on_hand - COALESCE(SUM(sm.quantity_delta), 0)) > 0.001
 ORDER BY p.id;
