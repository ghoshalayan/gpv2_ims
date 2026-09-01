-- CR-3 Due List: Short-term / Long-term tagging.
--
-- The owner classifies dues MANUALLY (chosen over ageing-based or credit-terms-based
-- classification). Two levels, resolved at report time:
--
--   1. sales_invoices.due_term      — explicit per-invoice tag. Wins when set.
--   2. customers.default_due_term   — the customer's default. Applies to every invoice
--                                     of theirs that carries no explicit tag, and to
--                                     that customer's legacy_obligations rows.
--   3. NULL at both levels          — the due lands in the "Untagged" bucket. It is
--                                     still counted in the Total column; it is simply
--                                     not attributed to short or long.
--
-- Both columns are NULLABLE with no default, so:
--   - existing rows need NO backfill and keep behaving exactly as before,
--   - nothing is silently misclassified (untagged is a visible state, not a guess),
--   - rolling back is `DROP COLUMN` on two columns no financial code path reads.
--
-- due_term is a NON-FINANCIAL attribute. It never participates in any total, tax, or
-- balance computation, which is why it is editable on a finalized invoice even though
-- finalized invoices are otherwise immutable.

ALTER TABLE sales_invoices
    ADD COLUMN due_term ENUM('short', 'long') DEFAULT NULL AFTER payment_status;

ALTER TABLE customers
    ADD COLUMN default_due_term ENUM('short', 'long') DEFAULT NULL AFTER credit_limit;

-- Report filters group by term over the open-due set, so index the pair that the
-- due-list WHERE clause actually uses.
ALTER TABLE sales_invoices
    ADD KEY idx_si_due_term (due_term, status, due_amount);
