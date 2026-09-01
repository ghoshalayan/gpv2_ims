-- Link legacy parties to current customers / suppliers / salesmen.
--
-- A legacy party may optionally be linked to its v2 counterpart so the legacy
-- obligations + settlements appear in the live entity's Universal Passbook
-- (carry-forward into the v2 ledger).
--
-- Three nullable FK columns instead of a polymorphic (kind, id) pair so that:
--   * MySQL can enforce ON DELETE SET NULL per target table (legacy data survives
--     even if the live entity is deleted — only the link is dropped).
--   * Application layer enforces the "exactly one column matching legacy_parties.kind"
--     invariant; schema would need triggers to do that and we keep DDL simple.
--
-- Many-to-one allowed: multiple legacy_parties can link to the same live customer
-- (covers cases where the same person had aliases in pre-v2 records).
--
-- Live → Legacy lookup uses: SELECT * FROM legacy_parties WHERE linked_<kind>_id = ?
-- Both sides are indexed.

ALTER TABLE legacy_parties
    ADD COLUMN linked_customer_id INT NULL AFTER kind,
    ADD COLUMN linked_supplier_id INT NULL AFTER linked_customer_id,
    ADD COLUMN linked_salesman_id INT NULL AFTER linked_supplier_id,
    ADD KEY idx_lp_link_customer (linked_customer_id),
    ADD KEY idx_lp_link_supplier (linked_supplier_id),
    ADD KEY idx_lp_link_salesman (linked_salesman_id),
    ADD CONSTRAINT fk_lp_link_customer
        FOREIGN KEY (linked_customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    ADD CONSTRAINT fk_lp_link_supplier
        FOREIGN KEY (linked_supplier_id) REFERENCES suppliers(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    ADD CONSTRAINT fk_lp_link_salesman
        FOREIGN KEY (linked_salesman_id) REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE SET NULL;
