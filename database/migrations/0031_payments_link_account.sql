-- Wire sales/purchase payments to a specific bank account or cashier drawer.
--
-- A payment row now carries the account that received (sales) or paid (purchase) the cash.
-- The payment service mutates that account's current_balance atomically on:
--   - insert (credit on sales / debit on purchase by effective_amount)
--   - cheque status change (pending↔bounced/cancelled flips the cheque portion in/out)
--   - delete (reverse whatever's currently counted)
--
-- "effective_amount" follows the existing convention: cash + online +
--   cheque (counted only when status IN ('pending', 'cleared')).
--
-- Columns are NULLABLE for backward compatibility — pre-existing payment rows have NULL
-- and don't post to any account. Going forward, the UI requires a selection.

ALTER TABLE sales_payments
    ADD COLUMN received_into_kind ENUM('bank_account', 'cashier_drawer') NULL AFTER cheque_status,
    ADD COLUMN received_into_id   INT NULL AFTER received_into_kind,
    ADD KEY idx_sp_account (received_into_kind, received_into_id);

ALTER TABLE purchase_payments
    ADD COLUMN paid_from_kind ENUM('bank_account', 'cashier_drawer') NULL AFTER cheque_status,
    ADD COLUMN paid_from_id   INT NULL AFTER paid_from_kind,
    ADD KEY idx_pp_account (paid_from_kind, paid_from_id);
