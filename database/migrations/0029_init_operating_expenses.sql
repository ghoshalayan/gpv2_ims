-- Module #19 Operating Expenses
--
-- One-shot expense entries (rent, salary, utilities, repairs, etc.). Each expense:
--   - is paid AT THE TIME OF POSTING from a bank account or cashier drawer
--   - debits that paying-account's current_balance atomically
--   - optionally captures GST (input credit if registered + claimable, else folds into expense)
--
-- Status flow: posted -> voided. Voiding (admin only) writes a reverse balance delta on the
-- paying account; the row stays visible with status='voided' for audit history.
--
-- Editing finalized expenses is NOT allowed in v1 (immutable). Mistakes are corrected via
-- void + repost. (Same convention as sales/purchase invoices.)

CREATE TABLE operating_expense_categories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)  NOT NULL,
    description     VARCHAR(255)  DEFAULT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by      INT DEFAULT NULL,
    updated_by      INT DEFAULT NULL,
    UNIQUE KEY uniq_oec_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE operating_expenses (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    expense_number      VARCHAR(100) NOT NULL,                   -- GP-EXP/{FY}/{NNNNN}
    expense_date        DATE         NOT NULL,
    category_id         INT          NOT NULL,
    vendor_name         VARCHAR(150) DEFAULT NULL,               -- free text (rent landlord, utility provider, etc.)
    amount              DECIMAL(14,2) NOT NULL DEFAULT 0.00,     -- pre-tax expense amount
    gst_rate            DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    gst_amount          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    gst_claimable       TINYINT(1)    NOT NULL DEFAULT 0,        -- 1 = goes to input register (D3-style)
    total_with_gst      DECIMAL(14,2) NOT NULL DEFAULT 0.00,     -- amount the paying account is debited by
    paid_from_kind      ENUM('bank_account', 'cashier_drawer') NOT NULL,
    paid_from_id        INT          NOT NULL,
    payment_mode        ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') NOT NULL DEFAULT 'cash',
    payment_reference   VARCHAR(100) DEFAULT NULL,               -- UTR / cheque # / receipt #
    remarks             VARCHAR(500) DEFAULT NULL,
    status              ENUM('posted', 'voided') NOT NULL DEFAULT 'posted',
    voided_at           DATETIME     DEFAULT NULL,
    voided_by           INT          DEFAULT NULL,
    void_reason         VARCHAR(255) DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT          DEFAULT NULL,
    updated_by          INT          DEFAULT NULL,
    UNIQUE KEY uniq_expense_number (expense_number),
    KEY idx_oe_date (expense_date),
    KEY idx_oe_category (category_id),
    KEY idx_oe_paid_from (paid_from_kind, paid_from_id),
    KEY idx_oe_status (status),
    CONSTRAINT fk_oe_category FOREIGN KEY (category_id) REFERENCES operating_expense_categories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed a sensible starter set of categories. Admin can disable/rename later.
INSERT INTO operating_expense_categories (name, description) VALUES
    ('Rent',            'Shop / office rent'),
    ('Utilities',       'Electricity, water, internet, phone'),
    ('Salary',          'Employee wages and salaries'),
    ('Office Supplies', 'Stationery, consumables, printing'),
    ('Travel',          'Conveyance, fuel, tolls'),
    ('Repairs',         'Maintenance, repairs, AMC'),
    ('Bank Charges',    'Bank fees, transfer charges, card processing'),
    ('Misc',            'Other operating expenses');
