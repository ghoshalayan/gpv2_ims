-- Decimal stock + per-line entry units.
--
-- Part A — fix a latent bug: invoice/adjustment line quantities are DECIMAL(.,3)
-- but products.quantity_on_hand was INT, so any fractional stock movement (e.g.
-- 0.5 kg, 1.5 ltr) was silently truncated when applied to on-hand. Widen the
-- stock columns to DECIMAL(14,3) so fractional bases (kg, ltr, m) track correctly.
-- Existing integer values are preserved exactly.
--
-- Part B — record the unit a line was ENTERED in (e.g. "2 CASE") alongside the
-- stored base quantity (e.g. 24 PCS). The stored `quantity`/`unit_price` stay in
-- the product's BASE stock unit so every downstream calc (subtotal, tax, COGS,
-- moving-avg, stock) is unchanged; entry_unit_id/entry_quantity are display +
-- audit only and are NULL when the base unit was used directly.

-- Part A
ALTER TABLE products
    MODIFY quantity_on_hand    DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    MODIFY low_stock_threshold DECIMAL(14,3) NOT NULL DEFAULT 0.000;

-- Part B
ALTER TABLE purchase_invoice_lines
    ADD COLUMN entry_unit_id  INT           DEFAULT NULL AFTER quantity,
    ADD COLUMN entry_quantity DECIMAL(14,3) DEFAULT NULL AFTER entry_unit_id,
    ADD KEY idx_pil_entry_unit (entry_unit_id),
    ADD CONSTRAINT fk_pil_entry_unit FOREIGN KEY (entry_unit_id)
        REFERENCES units(id) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE sales_invoice_lines
    ADD COLUMN entry_unit_id  INT           DEFAULT NULL AFTER quantity,
    ADD COLUMN entry_quantity DECIMAL(14,3) DEFAULT NULL AFTER entry_unit_id,
    ADD KEY idx_sil_entry_unit (entry_unit_id),
    ADD CONSTRAINT fk_sil_entry_unit FOREIGN KEY (entry_unit_id)
        REFERENCES units(id) ON UPDATE CASCADE ON DELETE SET NULL;
