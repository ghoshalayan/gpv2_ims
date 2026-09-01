-- Module #15 Sales Payments
--
-- Mirror of purchase_payments on the sales side. Split-tender payment against a
-- finalized sales invoice. Cheque lifecycle identical (pending / cleared /
-- bounced / cancelled). SalesPaymentService keeps sales_invoices.paid_amount /
-- due_amount / payment_status in sync on every mutation.

CREATE TABLE sales_payments (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    sales_invoice_id        INT            NOT NULL,
    payment_date            DATE           NOT NULL,
    cash_amount             DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    online_amount           DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    online_reference        VARCHAR(100)   DEFAULT NULL,
    cheque_amount           DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    cheque_number           VARCHAR(50)    DEFAULT NULL,
    cheque_date             DATE           DEFAULT NULL,
    cheque_bank_name        VARCHAR(150)   DEFAULT NULL,
    cheque_status           ENUM('pending', 'cleared', 'bounced', 'cancelled')
                            NOT NULL DEFAULT 'pending',
    total_amount            DECIMAL(12,2)  NOT NULL,
    remarks                 VARCHAR(500)   DEFAULT NULL,
    created_at              TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by              INT            DEFAULT NULL,
    updated_by              INT            DEFAULT NULL,
    KEY idx_sp_invoice (sales_invoice_id),
    KEY idx_sp_date (payment_date),
    KEY idx_sp_cheque_status (cheque_status),
    CONSTRAINT fk_sp_invoice FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
