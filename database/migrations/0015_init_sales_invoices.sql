-- Module #14 Sales Invoices
--
-- Two tables: header (sales_invoices) and lines (sales_invoice_lines).
-- Sales invoices are MULTI-brand (unlike purchases which are single-brand per D14).
-- Per-line salesman attribution per D58. COGS snapshot per line per D54 — for
-- sales-return cost basis. Commission snapshot per line per D62 — for ledger.
--
-- Sales invoice numbers are auto-generated at finalize time in the format
-- "SI/{FY}/{NNNNN}" where FY is the fiscal year derived from invoice_date and
-- business_profile.financial_year_start_month. Drafts have empty invoice_number.

CREATE TABLE sales_invoices (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number              VARCHAR(100) NOT NULL DEFAULT '',
    customer_id                 INT          NOT NULL,
    invoice_date                DATE         NOT NULL,
    bill_format                 ENUM('per_line_tax', 'discount_then_tax') NOT NULL DEFAULT 'per_line_tax',
    tax_treatment               ENUM('exclusive', 'inclusive', 'no_tax')  NOT NULL DEFAULT 'exclusive',
    invoice_level_tax_rate      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    is_intra_state              TINYINT(1)   NOT NULL DEFAULT 1,
    bill_type                   ENUM('tax_invoice', 'bill_of_supply') NOT NULL DEFAULT 'tax_invoice',
    header_salesman_id          INT          DEFAULT NULL,
    subtotal                    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_tax                   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_sgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_igst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cogs                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_commission            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    round_off_amount            DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    grand_total                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    paid_amount                 DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    due_amount                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    payment_status              ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
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
    UNIQUE KEY uniq_sales_invoice_number (invoice_number),
    KEY idx_si_customer (customer_id),
    KEY idx_si_status (status),
    KEY idx_si_payment (payment_status),
    KEY idx_si_date (invoice_date),
    KEY idx_si_header_salesman (header_salesman_id),
    CONSTRAINT fk_si_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_si_header_salesman FOREIGN KEY (header_salesman_id) REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_invoice_lines (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    sales_invoice_id        INT           NOT NULL,
    line_no                 INT           NOT NULL DEFAULT 1,
    product_id              INT           NOT NULL,
    display_name            VARCHAR(255)  NOT NULL,            -- D63: defaults to product.name, overridable per line
    salesman_id             INT           DEFAULT NULL,         -- D58 per-line attribution (null = direct sale)
    quantity                DECIMAL(12,3) NOT NULL,
    unit_price              DECIMAL(12,4) NOT NULL,
    addsub_percent          DECIMAL(6,2)  NOT NULL DEFAULT 0.00, -- D1 signed (sales convention positive = commission)
    subtotal                DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_rate                DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    tax_amount              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cgst_amount             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    sgst_amount             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    igst_amount             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cogs_per_unit           DECIMAL(14,4) NOT NULL DEFAULT 0.0000,  -- D54: snapshot moving_avg at finalize time
    cogs_total              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    commission_rate         DECIMAL(5,2)  NOT NULL DEFAULT 0.00,    -- snapshot of salesman.commission_rate at finalize
    commission_amount       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sil_invoice (sales_invoice_id),
    KEY idx_sil_product (product_id),
    KEY idx_sil_salesman (salesman_id),
    CONSTRAINT fk_sil_invoice  FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_sil_product  FOREIGN KEY (product_id)       REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sil_salesman FOREIGN KEY (salesman_id)      REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
