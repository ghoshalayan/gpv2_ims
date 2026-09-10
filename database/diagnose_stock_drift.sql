-- =====================================================================
--  STOCK DRIFT DIAGNOSTIC — read-only, changes nothing
-- =====================================================================
--  For the Integrity Check finding:
--     "Stock: quantity_on_hand vs movement ledger"
--
--  The invariant is:
--     products.quantity_on_hand  ==  SUM(stock_movements.quantity_delta)
--
--  The stored quantity is what the app uses everywhere (stock levels,
--  oversell checks, valuation). The ledger is the audit trail of how it
--  got there. A mismatch means one of them missed an event.
--
--  Run all four queries and send the output back before changing
--  anything. Query 2 in particular usually shows the cause outright.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Every drifted product (not just the two reported).
--    difference = stored - ledger.  Positive => the ledger is missing
--    stock that came IN.  Negative => it is missing stock that went OUT.
-- ---------------------------------------------------------------------
SELECT p.id,
       p.name,
       p.quantity_on_hand                              AS stored_qty,
       COALESCE(SUM(sm.quantity_delta), 0)             AS ledger_qty,
       p.quantity_on_hand - COALESCE(SUM(sm.quantity_delta), 0) AS difference,
       COUNT(sm.id)                                    AS movement_rows,
       SUM(sm.direction = 'opening')                   AS opening_rows,
       p.created_at                                    AS product_created
  FROM products p
  LEFT JOIN stock_movements sm ON sm.product_id = p.id
 GROUP BY p.id, p.name, p.quantity_on_hand, p.created_at
HAVING ABS(p.quantity_on_hand - COALESCE(SUM(sm.quantity_delta), 0)) > 0.001
 ORDER BY ABS(difference) DESC;


-- ---------------------------------------------------------------------
-- 2. THE IMPORTANT ONE — full movement history for the two products
--    named in the Integrity Check. Read `running_total` down the page:
--    the row where it stops matching `balance_after` is where the
--    ledger and the stored quantity parted company.
-- ---------------------------------------------------------------------
SELECT sm.id,
       sm.created_at,
       sm.direction,
       sm.quantity_delta,
       sm.balance_after      AS balance_recorded_at_the_time,
       sm.source,
       sm.source_ref_type,
       sm.source_ref_id,
       sm.notes,
       p.name                AS product
  FROM stock_movements sm
  JOIN products p ON p.id = sm.product_id
 WHERE sm.product_id IN (9, 10)
 ORDER BY sm.product_id, sm.id;


-- ---------------------------------------------------------------------
-- 3. Do those two products have an OPENING row at all, and does the
--    first recorded balance line up with the first delta?
-- ---------------------------------------------------------------------
SELECT p.id,
       p.name,
       p.quantity_on_hand,
       SUM(sm.direction = 'opening')                       AS opening_rows,
       MIN(CASE WHEN sm.direction = 'opening' THEN sm.quantity_delta END) AS opening_qty,
       SUM(CASE WHEN sm.quantity_delta > 0 THEN sm.quantity_delta ELSE 0 END) AS total_in,
       SUM(CASE WHEN sm.quantity_delta < 0 THEN sm.quantity_delta ELSE 0 END) AS total_out,
       MIN(sm.created_at)                                  AS first_movement,
       MAX(sm.created_at)                                  AS last_movement
  FROM products p
  LEFT JOIN stock_movements sm ON sm.product_id = p.id
 WHERE p.id IN (9, 10)
 GROUP BY p.id, p.name, p.quantity_on_hand;


-- ---------------------------------------------------------------------
-- 4. What business documents reference these two products? If the
--    ledger is missing an IN, there is usually a purchase or return
--    here with no matching movement row.
-- ---------------------------------------------------------------------
SELECT 'purchase line' AS doc, pi.invoice_number AS ref, pi.invoice_date AS dt,
       pil.quantity AS qty, pi.status
  FROM purchase_invoice_lines pil
  JOIN purchase_invoices pi ON pi.id = pil.purchase_invoice_id
 WHERE pil.product_id IN (9, 10)
UNION ALL
SELECT 'sales line', si.invoice_number, si.invoice_date, sil.quantity, si.status
  FROM sales_invoice_lines sil
  JOIN sales_invoices si ON si.id = sil.sales_invoice_id
 WHERE sil.product_id IN (9, 10)
UNION ALL
SELECT 'stock adjustment', sa.adjustment_number, sa.adjustment_date, sal.quantity, sa.status
  FROM stock_adjustment_lines sal
  JOIN stock_adjustments sa ON sa.id = sal.stock_adjustment_id
 WHERE sal.product_id IN (9, 10)
 ORDER BY dt, ref;
