-- Legacy Records (Old Records Management) — pre-v2 customers + their historical bills/payments.
--
-- Why a parallel mini-system: at go-live there are customers from the old paper / v1 system
-- with outstanding balances and ongoing collection. Putting them in the regular customers/
-- sales_invoices flow would require fake invoice lines, fake brands, fake products. This
-- legacy module gives admins a focused interface: enter the customer, enter their old bill
-- (just bill_number + amount, no line items), record payments as they come in.
--
-- Direction: payments received into a real bank/drawer DO credit that account. Pre-v2
-- already-paid amounts are baked into bill.paid_amount at creation (no account effect).
--
-- BALANCE INTEGRITY (verified in IntegrityCheckService):
--   legacy_bill.paid_amount == legacy_bill.opening_paid_amount + SUM(legacy_payments.amount WHERE bill_id = legacy_bill.id)
--   legacy_bill.due_amount  == bill_amount - paid_amount   (recomputed on every change)

CREATE TABLE legacy_customers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
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
    KEY idx_lc_phone  (phone),
    KEY idx_lc_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legacy_bills (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    legacy_customer_id  INT          NOT NULL,
    bill_number         VARCHAR(100) NOT NULL,           -- as on the old paper bill
    bill_date           DATE         NOT NULL,
    bill_amount         DECIMAL(14,2) NOT NULL,
    opening_paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,    -- pre-v2 prepayment baked in at create; immutable
    paid_amount         DECIMAL(14,2) NOT NULL DEFAULT 0.00,    -- = opening_paid_amount + SUM(legacy_payments.amount)
    due_amount          DECIMAL(14,2) NOT NULL,
    payment_status      ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
    remarks             VARCHAR(500) DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT NOT NULL,
    updated_by          INT NOT NULL,
    KEY idx_lb_customer (legacy_customer_id),
    KEY idx_lb_status   (payment_status),
    KEY idx_lb_date     (bill_date),
    CONSTRAINT fk_lb_customer FOREIGN KEY (legacy_customer_id) REFERENCES legacy_customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legacy_payments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    legacy_customer_id  INT          NOT NULL,
    bill_id             INT          DEFAULT NULL,             -- NULL = unallocated (sits as customer credit)
    payment_date        DATE         NOT NULL,
    amount              DECIMAL(14,2) NOT NULL,
    payment_mode        ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') NOT NULL DEFAULT 'cash',
    payment_reference   VARCHAR(100) DEFAULT NULL,
    received_into_kind  ENUM('bank_account', 'cashier_drawer') NOT NULL,
    received_into_id    INT          NOT NULL,
    remarks             VARCHAR(500) DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          INT NOT NULL,
    KEY idx_lp_customer (legacy_customer_id),
    KEY idx_lp_bill     (bill_id),
    KEY idx_lp_account  (received_into_kind, received_into_id),
    KEY idx_lp_date     (payment_date),
    CONSTRAINT fk_lp_customer FOREIGN KEY (legacy_customer_id) REFERENCES legacy_customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_lp_bill     FOREIGN KEY (bill_id)            REFERENCES legacy_bills(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
