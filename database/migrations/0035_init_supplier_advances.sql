-- Supplier Advances — mirror of customer_advances, direction inverted.
-- We PAY the supplier ahead (or in excess of an invoice) creating a credit balance
-- with them. Future purchase payments can draw from this credit instead of new cash.
--
-- BALANCE INTEGRITY GUARANTEES (enforced by the service layer in one DB transaction):
--
--   1. Pay an advance (we pay supplier):
--        - bank/drawer.current_balance −= amount   (we paid OUT)
--        - supplier_advances row created with balance = amount, status = 'active'
--      (Net cash effect: −amount on chosen account.)
--
--   2. Apply a supplier advance to a purchase invoice (paper credit transfer):
--        - supplier_advances.balance −= applied_amount  (clamped: applied <= balance)
--        - if balance == 0 → status = 'exhausted'
--        - purchase_payments row created with from_advance_amount; account NOT touched
--          for the from_advance portion (no fresh cash movement).
--        - purchase_invoices.paid/due/payment_status recomputed.
--      (Net cash effect: zero from the advance portion.)
--
--   3. Refund a supplier advance (supplier returns the unused balance to us):
--        - bank/drawer.current_balance += remaining balance   (we got cash back)
--        - supplier_advances.balance set to 0, status = 'refunded'
--      (Net cash effect: +remaining on chosen account.)
--
--   4. Voiding a payment that drew from an advance — same as customer side:
--      account is uncredited / advance balance restored.
--
-- Cross-check invariant (verified in IntegrityCheckService):
--   for any supplier S:
--     SUM(advance_amount) − SUM(applied) − SUM(refund_amount)
--                                ==
--     SUM(active_advances.balance)

CREATE TABLE supplier_advances (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    advance_number           VARCHAR(100) NOT NULL,                 -- GP-SADV/{FY}/{NNNNN}
    advance_date             DATE         NOT NULL,
    supplier_id              INT          NOT NULL,
    amount                   DECIMAL(14,2) NOT NULL,                -- original amount paid
    balance                  DECIMAL(14,2) NOT NULL,                -- = amount initially; drops on apply; 0 after refund
    paid_from_kind           ENUM('bank_account', 'cashier_drawer') NOT NULL,
    paid_from_id             INT          NOT NULL,
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
    UNIQUE KEY uniq_supplier_advance_number (advance_number),
    KEY idx_sa_supplier (supplier_id),
    KEY idx_sa_status (status),
    KEY idx_sa_paid_from (paid_from_kind, paid_from_id),
    KEY idx_sa_date (advance_date),
    CONSTRAINT fk_sa_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE purchase_payments
    ADD COLUMN from_advance_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER paid_from_id,
    ADD COLUMN from_advance_id     INT           DEFAULT NULL          AFTER from_advance_amount,
    ADD KEY idx_pp_from_advance (from_advance_id),
    ADD CONSTRAINT fk_pp_from_advance FOREIGN KEY (from_advance_id) REFERENCES supplier_advances(id)
        ON UPDATE CASCADE ON DELETE RESTRICT;
