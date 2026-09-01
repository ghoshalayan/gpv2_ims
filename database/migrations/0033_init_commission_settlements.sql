-- Module #22 Salesman Commission Settlement (D21).
--
-- A salesman's outstanding commission (sum of sales_invoice_lines.commission_amount minus
-- sales_return_lines.commission_reversed for finalized invoices/returns) can be settled in
-- two ways:
--
--   - cash : debit a bank account or cashier drawer by the cash amount.
--   - goods: stock-OUT a product at its CURRENT moving_avg_cost (NOT selling price).
--            total_value = qty × moving_avg_at_settle. Commission ledger debited by this value.
--
-- v1 lets each settlement row settle ONE mode (one cash payout OR one goods item).
-- Multi-product goods or split cash+goods settlements can be done via multiple rows.
--
-- Status flow: posted → voided. Void reverses the original effect (cash credited back,
-- or goods stock-back-IN at the snapshotted moving_avg).

CREATE TABLE salesman_commission_settlements (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    settlement_number        VARCHAR(100) NOT NULL,                 -- GP-COMM/{FY}/{NNNNN}
    settlement_date          DATE         NOT NULL,
    salesman_id              INT          NOT NULL,
    payout_mode              ENUM('cash', 'goods') NOT NULL,
    -- Cash-mode fields
    cash_amount              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    paid_from_kind           ENUM('bank_account', 'cashier_drawer') DEFAULT NULL,
    paid_from_id             INT          DEFAULT NULL,
    payment_mode             ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other') DEFAULT NULL,
    payment_reference        VARCHAR(100) DEFAULT NULL,
    -- Goods-mode fields
    goods_product_id         INT          DEFAULT NULL,
    goods_quantity           DECIMAL(12,3) DEFAULT NULL,
    goods_unit_cost          DECIMAL(14,4) DEFAULT NULL,            -- snapshot of product.moving_avg_cost at settle time
    goods_total_value        DECIMAL(14,2) DEFAULT NULL,            -- = qty × goods_unit_cost
    -- Header
    total_settled_amount     DECIMAL(14,2) NOT NULL,                -- cash_amount OR goods_total_value (one of them)
    remarks                  VARCHAR(500) DEFAULT NULL,
    status                   ENUM('posted', 'voided') NOT NULL DEFAULT 'posted',
    voided_at                DATETIME     DEFAULT NULL,
    voided_by                INT          DEFAULT NULL,
    void_reason              VARCHAR(255) DEFAULT NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by               INT          DEFAULT NULL,
    updated_by               INT          DEFAULT NULL,
    UNIQUE KEY uniq_commission_settlement_number (settlement_number),
    KEY idx_scs_salesman (salesman_id),
    KEY idx_scs_date (settlement_date),
    KEY idx_scs_status (status),
    KEY idx_scs_paid_from (paid_from_kind, paid_from_id),
    KEY idx_scs_goods_product (goods_product_id),
    CONSTRAINT fk_scs_salesman FOREIGN KEY (salesman_id) REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_scs_product  FOREIGN KEY (goods_product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
