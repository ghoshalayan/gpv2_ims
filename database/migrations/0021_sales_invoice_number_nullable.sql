-- Draft sales invoices have no invoice number yet — it is auto-assigned at finalize.
-- Previously we stored '' for drafts, which violates the UNIQUE constraint after
-- the second draft. Making the column nullable lets multiple drafts coexist
-- (MySQL allows multiple NULL values in a UNIQUE index).
--
-- Same latent issue on production_orders.order_number (insert '' then UPDATE to the
-- real PR number — fine normally but collides under concurrent inserts). Applying
-- the same nullable fix preemptively.

ALTER TABLE sales_invoices MODIFY invoice_number VARCHAR(100) DEFAULT NULL;
UPDATE sales_invoices SET invoice_number = NULL WHERE invoice_number = '';

ALTER TABLE production_orders MODIFY order_number VARCHAR(100) DEFAULT NULL;
UPDATE production_orders SET order_number = NULL WHERE order_number = '';
