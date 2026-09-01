-- Sales invoice extras (Delivery, Packing, Loading, etc.)
-- Multi-row. Mode A (discount_then_tax): per-row is_taxable toggle; tax_rate unused.
-- Mode B (per_line_tax): per-row tax_rate; is_taxable unused.
-- Final Discount applies to Mode A only (D-new); invoice_level_tax_rate also Mode A only.

CREATE TABLE sales_invoice_extras (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    sales_invoice_id    INT           NOT NULL,
    label               VARCHAR(100)  NOT NULL,
    amount              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_rate            DECIMAL(5,2)  NOT NULL DEFAULT 0.00,   -- Mode B only
    tax_amount          DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- computed on finalize
    is_taxable          TINYINT(1)    NOT NULL DEFAULT 0,      -- Mode A only
    sort_order          INT           NOT NULL DEFAULT 0,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sie_invoice (sales_invoice_id),
    CONSTRAINT fk_sie_invoice FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snapshot totals on header so reports don't need to re-sum extras.
ALTER TABLE sales_invoices
    ADD COLUMN total_extras       DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_commission,
    ADD COLUMN total_extras_tax   DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_extras;
