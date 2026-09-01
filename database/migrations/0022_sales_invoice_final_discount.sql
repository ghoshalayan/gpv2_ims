-- Flat-amount discount applied AFTER subtotal + tax (round-off-style).
-- Typical Indian retail usage: "Total 3150, final discount 50, pay 3100".
-- Works with any bill_format / tax_treatment.

ALTER TABLE sales_invoices
    ADD COLUMN final_discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total_commission;
