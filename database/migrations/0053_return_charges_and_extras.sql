-- Return refund composition: exclude original extras by default + optional seller charge/fine.
--
-- Two behavioural changes to both sales_returns and purchase_returns:
--
-- 1. include_original_extras (default 0 = EXCLUDE): the original invoice's extra costs
--    (loading/unloading/freight/expenses) are NO LONGER refunded by default. Set to 1 on
--    a return to add the prorated share back ("re-impose if required").
--
-- 2. A seller's return charge / fine / restocking deduction that REDUCES the refund:
--      return_charge_mode   none | flat | percent
--      return_charge_value  the flat ₹ amount, or the % of returned goods value
--      return_charge_taxable + return_charge_tax_rate  (GST on the fee; defaults ON for
--                           GST bills, editable). GST is exclusive (added on top of base).
--    Computed at finalize:
--      return_charge_amount     = base, ex-GST  (sales → Other Income; purchase → Other Expense)
--      return_charge_tax_amount = GST on the base (shown now; GSTR wiring is a follow-up)
--    The refund/credit grand_total is reduced by (return_charge_amount + return_charge_tax_amount).

ALTER TABLE sales_returns
    ADD COLUMN include_original_extras  TINYINT(1)   NOT NULL DEFAULT 0 AFTER invoice_level_tax_rate,
    ADD COLUMN return_charge_mode       ENUM('none','flat','percent') NOT NULL DEFAULT 'none' AFTER include_original_extras,
    ADD COLUMN return_charge_value      DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER return_charge_mode,
    ADD COLUMN return_charge_taxable    TINYINT(1)   NOT NULL DEFAULT 0 AFTER return_charge_value,
    ADD COLUMN return_charge_tax_rate   DECIMAL(5,2)  NOT NULL DEFAULT 0.00 AFTER return_charge_taxable,
    ADD COLUMN return_charge_amount     DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER return_charge_tax_rate,
    ADD COLUMN return_charge_tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER return_charge_amount;

ALTER TABLE purchase_returns
    ADD COLUMN include_original_extras  TINYINT(1)   NOT NULL DEFAULT 0 AFTER invoice_level_tax_rate,
    ADD COLUMN return_charge_mode       ENUM('none','flat','percent') NOT NULL DEFAULT 'none' AFTER include_original_extras,
    ADD COLUMN return_charge_value      DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER return_charge_mode,
    ADD COLUMN return_charge_taxable    TINYINT(1)   NOT NULL DEFAULT 0 AFTER return_charge_value,
    ADD COLUMN return_charge_tax_rate   DECIMAL(5,2)  NOT NULL DEFAULT 0.00 AFTER return_charge_taxable,
    ADD COLUMN return_charge_amount     DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER return_charge_tax_rate,
    ADD COLUMN return_charge_tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER return_charge_amount;
