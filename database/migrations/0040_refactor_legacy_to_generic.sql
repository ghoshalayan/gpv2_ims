-- Generalize legacy records: one set of tables for customers, suppliers and salesmen.
-- The customer-only legacy_* tables introduced in 0039 are dropped — there's no production
-- data yet (only smoke-test fixtures), so straight drop-and-recreate is safest.
--
-- Direction of cash on a settlement is implicit from party.kind:
--   customer  → settlement CREDITS the receiving account (we got money)
--   supplier  → settlement DEBITS  the paying account   (we paid)
--   salesman  → settlement DEBITS  the paying account   (we paid commission)
--
-- Service layer enforces the direction. Schema doesn't store it (kind drives it).
--
-- Activity log entry kinds become 'legacy_party', 'legacy_obligation', 'legacy_settlement'.

DROP TABLE IF EXISTS legacy_payments;
DROP TABLE IF EXISTS legacy_bills;
DROP TABLE IF EXISTS legacy_customers;

CREATE TABLE legacy_parties (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    kind         ENUM('customer', 'supplier', 'salesman') NOT NULL,
    name         VARCHAR(255) NOT NULL,
    phone        VARCHAR(50)  DEFAULT NULL,
    email        VARCHAR(150) DEFAULT NULL,
    address      VARCHAR(500) DEFAULT NULL,
    notes        VARCHAR(500) DEFAULT NULL,
    status       ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by   INT NOT NULL,
    updated_by   INT NOT NULL,
    KEY idx_lp_kind   (kind),
    KEY idx_lp_status (status),
    KEY idx_lp_phone  (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legacy_obligations (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    party_id            INT          NOT NULL,
    obligation_number   VARCHAR(100) NOT NULL,           -- bill # / commission ref / as on paper
    obligation_date     DATE         NOT NULL,
    obligation_amount   DECIMAL(14,2) NOT NULL,
    opening_paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,    -- pre-v2 settled portion (immutable)
    paid_amount         DECIMAL(14,2) NOT NULL DEFAULT 0.00,    -- = opening + SUM(legacy_settlements.amount)
    due_amount          DECIMAL(14,2) NOT NULL,
    payment_status      ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
    remarks             VARCHAR(500) DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT NOT NULL,
    updated_by          INT NOT NULL,
    KEY idx_lo_party    (party_id),
    KEY idx_lo_status   (payment_status),
    KEY idx_lo_date     (obligation_date),
    CONSTRAINT fk_lo_party FOREIGN KEY (party_id) REFERENCES legacy_parties(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legacy_settlements (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    party_id          INT          NOT NULL,
    obligation_id     INT          DEFAULT NULL,         -- NULL = unallocated (sits as advance)
    settlement_date   DATE         NOT NULL,
    amount            DECIMAL(14,2) NOT NULL,
    payment_mode      ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') NOT NULL DEFAULT 'cash',
    payment_reference VARCHAR(100) DEFAULT NULL,
    account_kind      ENUM('bank_account', 'cashier_drawer') NOT NULL,
    account_id        INT          NOT NULL,
    remarks           VARCHAR(500) DEFAULT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by        INT NOT NULL,
    KEY idx_ls_party      (party_id),
    KEY idx_ls_obligation (obligation_id),
    KEY idx_ls_account    (account_kind, account_id),
    KEY idx_ls_date       (settlement_date),
    CONSTRAINT fk_ls_party      FOREIGN KEY (party_id)      REFERENCES legacy_parties(id)     ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ls_obligation FOREIGN KEY (obligation_id) REFERENCES legacy_obligations(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
