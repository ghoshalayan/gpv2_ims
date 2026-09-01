-- Phase 4 spine: Bank Accounts + Cashier Drawers + Inter-account Transfers (D26 / D40 / N31)
--
-- Each master carries its own current_balance, mutated atomically by the transfer service
-- (and, in future, by sales/purchase payment links and operating-expense postings).
-- Universal Passbook (#24) will read these masters as ledger entities; for now the
-- per-master ledger lives implicitly on transfer rows (source = inter_account_transfer).
--
-- Status flow: active / inactive (no soft-deletes; the row is forever, with a flag).
-- inter_account_transfers are append-only — no edits/deletes once posted (v1).

CREATE TABLE bank_accounts (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(100)  NOT NULL,                 -- short label e.g. "HDFC Current"
    bank_name           VARCHAR(100)  DEFAULT NULL,
    account_number      VARCHAR(50)   DEFAULT NULL,
    ifsc                VARCHAR(20)   DEFAULT NULL,
    branch              VARCHAR(100)  DEFAULT NULL,
    opening_balance     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    current_balance     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    remarks             VARCHAR(500)  DEFAULT NULL,
    status              ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT DEFAULT NULL,
    updated_by          INT DEFAULT NULL,
    UNIQUE KEY uniq_bank_account_name (name),
    KEY idx_ba_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cashier_drawers (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(100)  NOT NULL,                 -- e.g. "Main Counter"
    location            VARCHAR(100)  DEFAULT NULL,
    assigned_user_id    INT           DEFAULT NULL,             -- who runs this drawer (optional)
    opening_balance     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    current_balance     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    remarks             VARCHAR(500)  DEFAULT NULL,
    status              ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT DEFAULT NULL,
    updated_by          INT DEFAULT NULL,
    UNIQUE KEY uniq_drawer_name (name),
    KEY idx_cd_status (status),
    KEY idx_cd_user (assigned_user_id),
    CONSTRAINT fk_cd_user FOREIGN KEY (assigned_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One transfer = two endpoints. from_kind/to_kind are 'bank_account' or 'cashier_drawer'.
-- Posting is atomic: from.current_balance -= amount AND to.current_balance += amount.
-- mode = how the cash moved (cash withdrawal/deposit, NEFT, IMPS, internal cash hand-off, etc.)
CREATE TABLE inter_account_transfers (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number     VARCHAR(100)  NOT NULL,
    transfer_date       DATE          NOT NULL,
    from_kind           ENUM('bank_account', 'cashier_drawer') NOT NULL,
    from_id             INT           NOT NULL,
    to_kind             ENUM('bank_account', 'cashier_drawer') NOT NULL,
    to_id               INT           NOT NULL,
    amount              DECIMAL(14,2) NOT NULL,
    mode                ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'other') NOT NULL DEFAULT 'cash',
    reference           VARCHAR(100)  DEFAULT NULL,             -- cheque #, UTR, etc.
    remarks             VARCHAR(500)  DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          INT DEFAULT NULL,
    UNIQUE KEY uniq_transfer_number (transfer_number),
    KEY idx_iat_date (transfer_date),
    KEY idx_iat_from (from_kind, from_id),
    KEY idx_iat_to   (to_kind, to_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
