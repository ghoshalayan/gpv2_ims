-- CR-10 (phone half) — customer / supplier phone becomes optional free text.
--
-- CR-10's other asks were already satisfied before this migration:
--   * products save with or without GST details — products.hsn_code is nullable and
--     tax_rate defaults to 0, and neither is in ProductController's required() chain;
--   * invoices support tax_treatment='no_tax' and bill_type='bill_of_supply';
--   * GSTIN and state are already optional on customers and suppliers.
-- What actually blocked quick entry was the phone field: required, and validated
-- against a strict Indian 10-digit pattern.
--
-- WHY THE COLUMN MUST BECOME NULLABLE: dropping `required` alone is not enough. With
-- `phone VARCHAR(20) NOT NULL` plus UNIQUE, a blank phone stores '' — and only ONE row
-- can hold ''. The second customer saved without a phone would fail with a confusing
-- "phone already used" error. NULL is exempt from UNIQUE in MySQL, so storing NULL for
-- blank lets any number of records have no phone while real numbers stay unique.
--
-- WHAT IS DELIBERATELY NOT DONE HERE: the UNIQUE index stays.
--   1. It still expresses the rule the owner set out in migration 0007 — a phone number
--      identifies one entity within a table.
--   2. Dropping it is close to one-way: once duplicate phones exist in live data, it
--      cannot be re-added without manual cleanup.
--   3. CustomerRepository::findByNameAndPhone() auto-links salesman and employee mirror
--      customers by exact name+phone match. Duplicate phones make that lookup ambiguous
--      and could mis-link or duplicate a salesman's mirror record. That linking has to
--      be reworked onto an explicit customer choice BEFORE the index can safely go.
-- So this migration removes the format and presence constraints (the actual complaint)
-- and leaves the uniqueness question to a later, separately-reviewed change.
--
-- Widening NOT NULL -> NULL is non-destructive: every existing row remains valid and no
-- data is touched. It stays reversible for as long as no NULLs have been saved.
--
-- salesmen, employees and users keep a required, validated phone: those are internal
-- records, and the mirror-link logic above depends on them having a real number.

ALTER TABLE customers
    MODIFY COLUMN phone VARCHAR(20) DEFAULT NULL;

ALTER TABLE suppliers
    MODIFY COLUMN phone VARCHAR(20) DEFAULT NULL;
