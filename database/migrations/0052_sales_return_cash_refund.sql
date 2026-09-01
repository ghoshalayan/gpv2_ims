-- Sales-return CASH refunds (mirror of 0051 for purchases).
--
-- v1 only supported credit_note (reduce the customer's receivable). This adds a real
-- cash refund leg: when goods are returned but the customer had already (partly) paid,
-- we refund the already-collected excess in cash/bank — money OUT of a chosen account.
--
-- Policy (same as purchases): NET AGAINST RECEIVABLE FIRST. At finalize the return's
-- grand_total first reduces the invoice's outstanding due (unchanged behaviour); only
-- the surplus (grand_total − outstanding_due, i.e. what the customer had already paid)
-- is refunded as cash and DEBITED from the chosen account. Account is picked per-return.
--
-- cash_refund_amount is the actual cash debited at finalize (0 for credit_note or when
-- the return fully nets against an open receivable). It is the single source the cash-flow
-- report and integrity reconciliation read.

ALTER TABLE sales_returns
    ADD COLUMN refund_account_kind ENUM('bank_account','cashier_drawer') DEFAULT NULL AFTER refund_method,
    ADD COLUMN refund_account_id   INT           DEFAULT NULL AFTER refund_account_kind,
    ADD COLUMN cash_refund_amount  DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER refund_account_id,
    ADD KEY idx_sr_refund_account (refund_account_kind, refund_account_id);
