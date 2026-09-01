-- Recurring Operating Expenses (rent, salary, internet bill, etc.)
--
-- A recurring template carries the same fields as a regular expense plus a frequency,
-- start date, optional end date, and a next_run_date that the firing engine advances.
--
-- "Firing" the engine is manual via the "Run Due Recurrences" button (or admin-triggered).
-- It finds all status='active' rows where next_run_date <= today, posts each as a real
-- operating_expenses row (calling the same service), and advances next_run_date by one
-- frequency step. If the new next_run_date overshoots end_date, the row is auto-paused.
--
-- Each fired expense links back via spawned_from_recurring_id so we can show "this expense
-- came from rent template #3, period 2026-04".

CREATE TABLE recurring_expenses (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150)  NOT NULL,                 -- e.g. "Shop rent — landlord A"
    category_id         INT           NOT NULL,
    vendor_name         VARCHAR(150)  DEFAULT NULL,
    amount              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    gst_rate            DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    gst_claimable       TINYINT(1)    NOT NULL DEFAULT 0,
    paid_from_kind      ENUM('bank_account', 'cashier_drawer') NOT NULL,
    paid_from_id        INT           NOT NULL,
    payment_mode        ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') NOT NULL DEFAULT 'cash',
    payment_reference   VARCHAR(100)  DEFAULT NULL,             -- template (e.g. "Rent {month}")
    remarks             VARCHAR(500)  DEFAULT NULL,
    -- Schedule
    frequency           ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') NOT NULL DEFAULT 'monthly',
    interval_count      INT           NOT NULL DEFAULT 1,        -- e.g. every 2 months
    start_date          DATE          NOT NULL,
    end_date            DATE          DEFAULT NULL,             -- null = open-ended
    next_run_date       DATE          NOT NULL,                 -- mutated by the firing engine
    last_run_at         DATETIME      DEFAULT NULL,
    runs_count          INT           NOT NULL DEFAULT 0,
    status              ENUM('active', 'paused') NOT NULL DEFAULT 'active',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT DEFAULT NULL,
    updated_by          INT DEFAULT NULL,
    KEY idx_re_next_run (next_run_date),
    KEY idx_re_status (status),
    KEY idx_re_category (category_id),
    CONSTRAINT fk_re_category FOREIGN KEY (category_id) REFERENCES operating_expense_categories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trace each fired expense back to its template (nullable so manual expenses still work).
ALTER TABLE operating_expenses
    ADD COLUMN spawned_from_recurring_id INT DEFAULT NULL AFTER void_reason,
    ADD KEY idx_oe_spawned (spawned_from_recurring_id),
    ADD CONSTRAINT fk_oe_spawned FOREIGN KEY (spawned_from_recurring_id) REFERENCES recurring_expenses(id)
        ON UPDATE CASCADE ON DELETE SET NULL;
