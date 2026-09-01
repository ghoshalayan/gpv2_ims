-- CR-6 Sell-wise commission — a per-line commission rate override.
--
-- Commission was already snapshotted PER LINE (sales_invoice_lines.commission_rate +
-- commission_amount, with per-line salesman attribution per D58). What was missing is
-- the ability to set the rate for a particular sale: the rate could only come from the
-- salesman master record at finalize time.
--
-- RESOLUTION AT FINALIZE:
--   commission_rate = COALESCE(commission_rate_override, salesmen.commission_rate)
--
-- NULL — the default for every existing row and every new line where nobody sets an
-- override — means "behave exactly as before". That is the rollback property: this
-- column changes nothing on its own, and no historical invoice is affected. It is also
-- why no back-fill is needed.
--
-- SALES RETURNS NEED NO CHANGE: SalesReturnService already reverses commission using
-- the rate SNAPSHOTTED on the originating sale line (sales_return_lines.commission_rate
-- is copied from sales_invoice_lines.commission_rate), so an overridden rate flows into
-- the reversal automatically and a fully-returned line still nets to zero commission.
--
-- The column is only read at finalize; drafts carry it untouched. Rolling back is
-- dropping one nullable column that no other code path depends on.

ALTER TABLE sales_invoice_lines
    ADD COLUMN commission_rate_override DECIMAL(5,2) DEFAULT NULL AFTER commission_rate;
