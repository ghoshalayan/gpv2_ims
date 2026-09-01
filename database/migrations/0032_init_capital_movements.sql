-- Capital & Drawings (D26 / D40) — owner / lender money movements that AREN'T expenses.
--
-- Five txn_types cover the common cases:
--   - capital_in   : owner injects money INTO the business        (account credited, equity up)
--   - drawing      : owner takes money OUT for personal use       (account debited, equity down)
--   - loan_in      : business borrows from a lender               (account credited, payable up)
--   - loan_repay   : business pays back a lender                  (account debited, payable down)
--   - other        : misc non-business movement                   (admin picks direction)
--
-- These are NOT operating expenses. Mixing them with expenses overstates costs and breaks the P&L.
--
-- counterparty_name is free-text in v1 (owner name, lender name). A formal Lenders / Partners
-- master is deferred — for now, the loan-balance-by-counterparty report groups by name match.
--
-- Status flow: posted -> voided. Void reverses the balance impact and keeps the row for audit.

CREATE TABLE capital_movements (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    movement_number     VARCHAR(100) NOT NULL,                -- GP-CAP/{FY}/{NNNNN}
    movement_date       DATE         NOT NULL,
    txn_type            ENUM('capital_in', 'drawing', 'loan_in', 'loan_repay', 'other') NOT NULL,
    direction           ENUM('in', 'out') NOT NULL,           -- denormalised but explicit (in = credit account)
    counterparty_name   VARCHAR(150) NOT NULL,                -- owner / lender / partner name
    amount              DECIMAL(14,2) NOT NULL,
    account_kind        ENUM('bank_account', 'cashier_drawer') NOT NULL,
    account_id          INT          NOT NULL,
    mode                ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') NOT NULL DEFAULT 'cash',
    reference           VARCHAR(100) DEFAULT NULL,
    remarks             VARCHAR(500) DEFAULT NULL,
    status              ENUM('posted', 'voided') NOT NULL DEFAULT 'posted',
    voided_at           DATETIME     DEFAULT NULL,
    voided_by           INT          DEFAULT NULL,
    void_reason         VARCHAR(255) DEFAULT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT          DEFAULT NULL,
    updated_by          INT          DEFAULT NULL,
    UNIQUE KEY uniq_capital_number (movement_number),
    KEY idx_cm_date (movement_date),
    KEY idx_cm_type (txn_type),
    KEY idx_cm_counterparty (counterparty_name),
    KEY idx_cm_account (account_kind, account_id),
    KEY idx_cm_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
