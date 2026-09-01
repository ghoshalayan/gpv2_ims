-- Mirror of the sales-side final_discount on purchase invoices.
-- Applies in Mode A (discount_then_tax) only. Reduces the lines' taxable base before
-- invoice-level tax; expenses remain independent (their own tax, landed-cost allocated).

ALTER TABLE purchase_invoices
    ADD COLUMN final_discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER invoice_level_tax_rate;
