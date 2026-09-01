-- Customer Advances — money received from a customer NOT tied to an existing invoice
-- (or in excess of one, parked as a credit).
--
-- BALANCE INTEGRITY GUARANTEES (enforced by the service layer in one DB transaction):
--
--   1. Receiving an advance:
--        - bank/drawer.current_balance += amount
--        - customer_advances row created with balance = amount, status = 'active'
--      (Net cash effect: +amount to chosen account.)
--
--   2. Applying an advance to an invoice (via sales_payments.from_advance_amount):
--        - customer_advances.balance -= applied_amount  (clamped: applied <= balance)
--        - if balance == 0 → status = 'exhausted'
--        - sales_payments row created with from_advance_amount, no cash/online/cheque
--          (or mixed). Account balance is NOT touched for the from_advance portion —
--          this is a paper credit transfer, not a cash movement.
--        - sales_invoices.paid/due/payment_status recomputed (effectivePaidForInvoice
--          now sums from_advance_amount as well).
--      (Net cash effect: zero from the advance portion.)
--
--   3. Refunding an advance (admin only):
--        - bank/drawer.current_balance -= remaining balance
--        - customer_advances.balance set to 0, status = 'refunded'
--      (Net cash effect: −balance from chosen account.)
--
--   4. Voiding a payment that used an advance (existing delete flow):
--        - account credited back by cash+online+cheque-counted (existing behavior)
--        - customer_advances.balance += from_advance_amount of that payment
--        - if status was 'exhausted', flip back to 'active'
--      (Net cash effect: same as before — restores original state.)
--
--   5. Cheque bounce/cancel on a mixed payment: only the cheque portion's account delta
--      is reversed (existing behavior). Advance portion is untouched (advance was not a
--      conditional credit — it was already-received cash being re-allocated).
--
-- CROSS-CHECK INVARIANT (run as a sanity report later):
--   for any customer C:
--     SUM(advances_received) − SUM(advances_applied) − SUM(advances_refunded)
--                                        ==
--     SUM(active_advances.balance)

CREATE TABLE customer_advances (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    advance_number           VARCHAR(100) NOT NULL,                 -- GP-ADV/{FY}/{NNNNN}
    advance_date             DATE         NOT NULL,
    customer_id              INT          NOT NULL,
    amount                   DECIMAL(14,2) NOT NULL,                -- original amount received (immutable)
    balance                  DECIMAL(14,2) NOT NULL,                -- = amount initially; drops on apply; 0 after refund
    received_into_kind       ENUM('bank_account', 'cashier_drawer') NOT NULL,
    received_into_id         INT          NOT NULL,
    payment_mode             ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') NOT NULL DEFAULT 'cash',
    payment_reference        VARCHAR(100) DEFAULT NULL,
    remarks                  VARCHAR(500) DEFAULT NULL,
    status                   ENUM('active', 'exhausted', 'refunded') NOT NULL DEFAULT 'active',
    refunded_at              DATETIME     DEFAULT NULL,
    refunded_by              INT          DEFAULT NULL,
    refund_reason            VARCHAR(255) DEFAULT NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by               INT          DEFAULT NULL,
    updated_by               INT          DEFAULT NULL,
    UNIQUE KEY uniq_advance_number (advance_number),
    KEY idx_ca_customer (customer_id),
    KEY idx_ca_status (status),
    KEY idx_ca_received (received_into_kind, received_into_id),
    KEY idx_ca_date (advance_date),
    CONSTRAINT fk_ca_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A sales_payment can now include an advance-application portion. NULL/0 means no advance used.
ALTER TABLE sales_payments
    ADD COLUMN from_advance_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER received_into_id,
    ADD COLUMN from_advance_id     INT           DEFAULT NULL          AFTER from_advance_amount,
    ADD KEY idx_sp_from_advance (from_advance_id),
    ADD CONSTRAINT fk_sp_from_advance FOREIGN KEY (from_advance_id) REFERENCES customer_advances(id)
        ON UPDATE CASCADE ON DELETE RESTRICT;
