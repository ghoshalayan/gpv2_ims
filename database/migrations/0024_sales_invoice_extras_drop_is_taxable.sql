-- Unified extras model: every extra row carries its own tax_rate.
-- The earlier Mode-A-only is_taxable toggle is gone. Drop the column.

ALTER TABLE sales_invoice_extras
    DROP COLUMN is_taxable;
