-- Module #13 Purchase Payments
--
-- Split-tender payment against a finalized purchase invoice. A single payment
-- row can carry cash + online + cheque portions simultaneously (per D34).
--
-- Bank-account linkage and cashier-drawer linkage are deferred to Phase 4 when
-- the Cash Book / Bank Book modules are built. For now we capture the amounts
-- and, for cheques, the minimum lifecycle status. Payment totals on the parent
-- purchase_invoices.paid_amount / due_amount / payment_status are kept in sync
-- by PurchasePaymentService on every insert / delete / cheque-status change.

CREATE TABLE purchase_payments (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    purchase_invoice_id     INT            NOT NULL,
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
    KEY idx_pp_invoice (purchase_invoice_id),
    KEY idx_pp_date (payment_date),
    KEY idx_pp_cheque_status (cheque_status),
    CONSTRAINT fk_pp_invoice FOREIGN KEY (purchase_invoice_id) REFERENCES purchase_invoices(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
