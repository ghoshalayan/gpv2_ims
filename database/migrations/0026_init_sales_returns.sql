-- Module #16 Sales Returns
--
-- Three tables: header (sales_returns), lines (sales_return_lines), and per-line
-- component reversal records for composite lines (sales_return_line_components).
--
-- Core rules:
--   - Stock re-enters at the SNAPSHOTTED cogs_per_unit of the originating sale
--     line (D54 — no cost-basis drift). Composite components reverse using the
--     per-component cogs_per_unit_at_sale snapshot.
--   - Per-line commission is reversed (D62) — negative entry, recorded on the
--     return line so the future commission ledger can pick it up.
--   - Refund flow v1 is "credit_note": reduces customer due on the original
--     invoice and posts a negative sales_payments row for traceability.
--   - Tax math mirrors the original sale's bill_format / tax_treatment.
--   - Final discount + extras are pro-rated by qty_returned / qty_original.
--
-- Status flow: draft -> finalized. Cancelled only on drafts in v1.

CREATE TABLE sales_returns (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    return_number               VARCHAR(100) DEFAULT NULL,                -- assigned at finalize, null on draft
    original_sales_invoice_id   INT          NOT NULL,
    customer_id                 INT          NOT NULL,
    return_date                 DATE         NOT NULL,
    reason                      VARCHAR(500) DEFAULT NULL,
    refund_method               ENUM('credit_note', 'cash')  NOT NULL DEFAULT 'credit_note',
    -- Snapshot of the original invoice's tax shape (so the math is reproducible
    -- even if someone later edits the originating row, which we don't allow today).
    bill_format                 ENUM('per_line_tax', 'discount_then_tax') NOT NULL DEFAULT 'per_line_tax',
    tax_treatment               ENUM('exclusive', 'inclusive', 'no_tax')  NOT NULL DEFAULT 'exclusive',
    is_intra_state              TINYINT(1)   NOT NULL DEFAULT 1,
    invoice_level_tax_rate      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    -- Header totals (computed at finalize).
    subtotal                    DECIMAL(14,2) NOT NULL DEFAULT 0.00,       -- sum of returned line subtotals
    final_discount_share        DECIMAL(14,2) NOT NULL DEFAULT 0.00,       -- prorated share of original final_discount
    extras_share                DECIMAL(14,2) NOT NULL DEFAULT 0.00,       -- prorated share of original extras (excl. extras tax)
    extras_tax_share            DECIMAL(14,2) NOT NULL DEFAULT 0.00,       -- prorated share of original extras tax
    total_tax                   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_sgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_igst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cogs                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,       -- cost of goods coming back into stock
    total_commission_reversed   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    round_off_amount            DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    grand_total                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,       -- amount the customer's due is reduced by
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
    UNIQUE KEY uniq_sales_return_number (return_number),
    KEY idx_sr_invoice (original_sales_invoice_id),
    KEY idx_sr_customer (customer_id),
    KEY idx_sr_status (status),
    KEY idx_sr_date (return_date),
    CONSTRAINT fk_sr_invoice  FOREIGN KEY (original_sales_invoice_id) REFERENCES sales_invoices(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sr_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_return_lines (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    sales_return_id             INT           NOT NULL,
    original_sales_line_id      INT           NOT NULL,
    line_no                     INT           NOT NULL DEFAULT 1,
    -- Snapshot from the originating sale line (so display + math is reproducible).
    product_id                  INT           DEFAULT NULL,
    is_composite_line           TINYINT(1)    NOT NULL DEFAULT 0,
    display_name                VARCHAR(255)  NOT NULL,
    salesman_id                 INT           DEFAULT NULL,
    quantity_returned           DECIMAL(12,3) NOT NULL,
    unit_price                  DECIMAL(12,4) NOT NULL,
    addsub_percent              DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    -- Computed (mirrors line math; tax behaves per the sale's bill_format).
    subtotal                    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_rate                    DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    tax_amount                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cgst_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    sgst_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    igst_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    -- Snapshots from the originating sale line for cost reversal / commission reversal.
    cogs_per_unit               DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    cogs_total                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    commission_rate             DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    commission_reversed         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_srl_return (sales_return_id),
    KEY idx_srl_orig_line (original_sales_line_id),
    KEY idx_srl_product (product_id),
    KEY idx_srl_salesman (salesman_id),
    CONSTRAINT fk_srl_return      FOREIGN KEY (sales_return_id) REFERENCES sales_returns(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_srl_orig_line   FOREIGN KEY (original_sales_line_id) REFERENCES sales_invoice_lines(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_srl_product     FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_srl_salesman    FOREIGN KEY (salesman_id) REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_return_line_components (
    id                              INT AUTO_INCREMENT PRIMARY KEY,
    sales_return_line_id            INT           NOT NULL,
    original_component_id           INT           NOT NULL,                   -- FK to sales_invoice_line_components
    component_product_id            INT           NOT NULL,
    component_name                  VARCHAR(255)  NOT NULL,
    component_unit                  VARCHAR(50)   NOT NULL,
    quantity_returned               DECIMAL(12,3) NOT NULL,                   -- recipe_qty × qty_returned (line)
    cogs_per_unit                   DECIMAL(14,4) NOT NULL DEFAULT 0.0000,    -- snapshot from sales_invoice_line_components.cogs_per_unit
    cogs_total                      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at                      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_srlc_line (sales_return_line_id),
    KEY idx_srlc_component (component_product_id),
    CONSTRAINT fk_srlc_line       FOREIGN KEY (sales_return_line_id)  REFERENCES sales_return_lines(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_srlc_orig_comp  FOREIGN KEY (original_component_id) REFERENCES sales_invoice_line_components(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_srlc_product    FOREIGN KEY (component_product_id)  REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
