-- Purchase-return CASH refunds.
--
-- v1 only supported credit_note (reduce supplier payable). This adds a real cash
-- refund leg: when goods are returned but the original invoice was already (partly)
-- paid, the supplier refunds the already-paid excess in cash/bank.
--
-- Policy (chosen): NET AGAINST PAYABLE FIRST. At finalize the return's grand_total
-- first reduces the invoice's outstanding due (unchanged behaviour); only the surplus
-- (grand_total − outstanding_due, i.e. what was already paid) is refunded as cash and
-- credited to the chosen account. The account is picked per-return on the header.
--
-- cash_refund_amount is the actual cash credited at finalize (0 for credit_note or
-- when the return fully nets against an open payable). It is the single source the
-- cash-flow report and integrity reconciliation read — crediting an account without
-- this row would break the per-account balance invariant.

ALTER TABLE purchase_returns
    ADD COLUMN refund_account_kind ENUM('bank_account','cashier_drawer') DEFAULT NULL AFTER refund_method,
    ADD COLUMN refund_account_id   INT           DEFAULT NULL AFTER refund_account_kind,
    ADD COLUMN cash_refund_amount  DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER refund_account_id,
    ADD KEY idx_pr_refund_account (refund_account_kind, refund_account_id);
