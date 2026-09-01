-- Module #14 Purchase Returns
--
-- Two tables: header (purchase_returns) and lines (purchase_return_lines).
-- No composite reversal (composites are a sales/production concept) and no commission.
--
-- Core rules:
--   - Stock-OUT at CURRENT moving_avg cost (D10) — uses InventoryService::applyStockOut.
--     This is asymmetric vs sales-return (which uses a snapshotted cost) and is the plan-locked
--     behavior: "Sale, purchase-return, write-off use current moving avg as COGS/cost-out."
--   - Tax math mirrors the originating purchase invoice's bill_format / tax_treatment.
--   - Final discount + expenses are pro-rated by qty_returned / qty_original (sum across lines).
--   - Refund flow v1 = "credit_note": reduces supplier's due on the original invoice.
--     Cash refund + universal-ledger postings deferred (see futurescope.md).
--
-- Status flow: draft -> finalized. Cancelled only on drafts in v1.

CREATE TABLE purchase_returns (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    return_number               VARCHAR(100) DEFAULT NULL,                -- assigned at finalize
    original_purchase_invoice_id INT         NOT NULL,
    supplier_id                 INT          NOT NULL,
    return_date                 DATE         NOT NULL,
    reason                      VARCHAR(500) DEFAULT NULL,
    refund_method               ENUM('credit_note', 'cash')  NOT NULL DEFAULT 'credit_note',
    -- Snapshot of the originating invoice's tax shape so the math is reproducible.
    bill_format                 ENUM('per_line_tax', 'discount_then_tax') NOT NULL DEFAULT 'per_line_tax',
    tax_treatment               ENUM('exclusive', 'inclusive', 'no_tax')  NOT NULL DEFAULT 'exclusive',
    is_intra_state              TINYINT(1)   NOT NULL DEFAULT 1,
    invoice_level_tax_rate      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    gst_claimable               TINYINT(1)   NOT NULL DEFAULT 1,
    -- Header totals (computed at finalize).
    subtotal                    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    final_discount_share        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expenses_share              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expenses_tax_share          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_tax                   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_sgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_igst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_stock_out_value       DECIMAL(14,2) NOT NULL DEFAULT 0.00,      -- qty × current moving_avg (cost flowing out)
    round_off_amount            DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    grand_total                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,      -- amount supplier's payable is reduced by
    remarks                     VARCHAR(500) DEFAULT NULL,
    status                      ENUM('draft', 'finalized', 'cancelled') NOT NULL DEFAULT 'draft',
    finalized_at                DATETIME     DEFAULT NULL,
    finalized_by                INT          DEFAULT NULL,
    cancelled_at                DATETIME     DEFAULT NULL,
    cancelled_by                INT          DEFAULT NULL,
    created_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by                  INT          DEFAULT NULL,
    updated_by                  INT          DEFAULT NULL,
    UNIQUE KEY uniq_purchase_return_number (return_number),
    KEY idx_pr_invoice (original_purchase_invoice_id),
    KEY idx_pr_supplier (supplier_id),
    KEY idx_pr_status (status),
    KEY idx_pr_date (return_date),
    CONSTRAINT fk_pr_invoice  FOREIGN KEY (original_purchase_invoice_id) REFERENCES purchase_invoices(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pr_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_return_lines (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    purchase_return_id          INT           NOT NULL,
    original_purchase_line_id   INT           NOT NULL,
    line_no                     INT           NOT NULL DEFAULT 1,
    -- Snapshot from the originating purchase line.
    product_id                  INT           NOT NULL,
    quantity_returned           DECIMAL(12,3) NOT NULL,
    unit_price                  DECIMAL(12,4) NOT NULL,
    addsub_percent              DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    -- Computed (mirrors line math).
    subtotal                    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_rate                    DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    tax_amount                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cgst_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    sgst_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    igst_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    -- Cost flowing out, captured at finalize-time using current moving_avg (D10).
    stock_out_cost_per_unit     DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    stock_out_cost_total        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_prl_return (purchase_return_id),
    KEY idx_prl_orig_line (original_purchase_line_id),
    KEY idx_prl_product (product_id),
    CONSTRAINT fk_prl_return    FOREIGN KEY (purchase_return_id) REFERENCES purchase_returns(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_prl_orig_line FOREIGN KEY (original_purchase_line_id) REFERENCES purchase_invoice_lines(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prl_product   FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
