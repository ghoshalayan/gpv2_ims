-- Module #12 Purchase Invoices
--
-- Three tables: header (purchase_invoices), lines (purchase_invoice_lines),
-- and expenses (purchase_invoice_expenses). Header enforces single-brand per invoice (D14).
-- Landed cost (D10/D11) is computed at finalize and stored per line.
--
-- Status flow: draft -> finalized. Cancelled only works on drafts in v1.
-- (Reverse-and-reapply on finalized is a deferred v2 feature.)

CREATE TABLE purchase_invoices (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number              VARCHAR(100) NOT NULL,
    supplier_id                 INT          NOT NULL,
    brand_id                    INT          NOT NULL,
    invoice_date                DATE         NOT NULL,
    bill_format                 ENUM('per_line_tax', 'discount_then_tax') NOT NULL DEFAULT 'per_line_tax',
    tax_treatment               ENUM('exclusive', 'inclusive', 'no_tax')  NOT NULL DEFAULT 'exclusive',
    gst_claimable               TINYINT(1)   NOT NULL DEFAULT 1,
    invoice_level_tax_rate      DECIMAL(5,2) NOT NULL DEFAULT 0.00,  -- used only in discount_then_tax mode
    is_intra_state              TINYINT(1)   NOT NULL DEFAULT 1,
    subtotal                    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_tax                   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_sgst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_igst                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_expenses              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_expense_tax           DECIMAL(14,2) NOT NULL DEFAULT 0.00,
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
    UNIQUE KEY uniq_purchase_invoice_supplier_number (supplier_id, invoice_number),
    KEY idx_purchase_invoices_supplier (supplier_id),
    KEY idx_purchase_invoices_brand (brand_id),
    KEY idx_purchase_invoices_status (status),
    KEY idx_purchase_invoices_date (invoice_date),
    KEY idx_purchase_invoices_payment (payment_status),
    CONSTRAINT fk_purchase_invoices_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_purchase_invoices_brand    FOREIGN KEY (brand_id) REFERENCES brands(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_invoice_lines (
    id                              INT AUTO_INCREMENT PRIMARY KEY,
    purchase_invoice_id             INT           NOT NULL,
    line_no                         INT           NOT NULL DEFAULT 1,
    product_id                      INT           NOT NULL,
    quantity                        DECIMAL(12,3) NOT NULL,
    unit_price                      DECIMAL(12,4) NOT NULL,
    addsub_percent                  DECIMAL(6,2)  NOT NULL DEFAULT 0.00,   -- D1: negative = discount
    subtotal                        DECIMAL(14,2) NOT NULL DEFAULT 0.00,   -- qty * price * (1 + addsub/100)
    tax_rate                        DECIMAL(5,2)  NOT NULL DEFAULT 0.00,   -- used in per_line_tax mode
    tax_amount                      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cgst_amount                     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    sgst_amount                     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    igst_amount                     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total                      DECIMAL(14,2) NOT NULL DEFAULT 0.00,   -- subtotal + tax_amount
    allocated_expense_share         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    allocated_expense_tax_share     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    landed_cost_per_unit            DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    created_at                      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pil_invoice (purchase_invoice_id),
    KEY idx_pil_product (product_id),
    CONSTRAINT fk_pil_invoice FOREIGN KEY (purchase_invoice_id) REFERENCES purchase_invoices(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_pil_product FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_invoice_expenses (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    purchase_invoice_id     INT           NOT NULL,
    expense_type            VARCHAR(100)  NOT NULL,
    amount                  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_rate                DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    tax_amount              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_with_tax          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    allocation_method       ENUM('by_value', 'by_quantity', 'equal_split') NOT NULL DEFAULT 'by_value',
    remarks                 VARCHAR(255)  DEFAULT NULL,
    created_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pie_invoice (purchase_invoice_id),
    CONSTRAINT fk_pie_invoice FOREIGN KEY (purchase_invoice_id) REFERENCES purchase_invoices(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
