-- Unify legacy parties with live entities.
--
-- Before: legacy_parties was a separate entity table; obligations/settlements
-- referenced legacy_parties.id, and a link FK to customers/suppliers/salesmen
-- was added in 0045.
--
-- After: ONE entity per real-world person (customer/supplier/salesman). Old
-- obligations + settlements attach DIRECTLY to a customer/supplier/salesman.
-- legacy_parties is dropped.
--
-- Migration steps:
--   1. Add nullable customer_id/supplier_id/salesman_id columns to obligations
--      + settlements with FKs (ON DELETE RESTRICT — old data blocks deletion of
--      its live entity, matches existing transactional FK convention).
--   2. Populate from legacy_parties.linked_*_id (no-op if no data exists yet).
--   3. Drop the old party_id FK + column from obligations + settlements.
--   4. Drop legacy_parties.
--
-- IMPORTANT for production: every legacy_party row must be linked
-- (linked_customer_id / linked_supplier_id / linked_salesman_id) BEFORE running
-- this migration. Unlinked legacy parties' obligations/settlements end up with
-- all three id columns NULL, which the application will treat as orphaned.
-- Resolve via the legacy-records UI before applying.

ALTER TABLE legacy_obligations
    ADD COLUMN customer_id INT NULL AFTER id,
    ADD COLUMN supplier_id INT NULL AFTER customer_id,
    ADD COLUMN salesman_id INT NULL AFTER supplier_id,
    ADD KEY idx_lo_customer (customer_id),
    ADD KEY idx_lo_supplier (supplier_id),
    ADD KEY idx_lo_salesman (salesman_id),
    ADD CONSTRAINT fk_lo_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_lo_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_lo_salesman FOREIGN KEY (salesman_id) REFERENCES salesmen(id)  ON UPDATE CASCADE ON DELETE RESTRICT;

UPDATE legacy_obligations lo
JOIN legacy_parties lp ON lp.id = lo.party_id
SET lo.customer_id = lp.linked_customer_id,
    lo.supplier_id = lp.linked_supplier_id,
    lo.salesman_id = lp.linked_salesman_id;

ALTER TABLE legacy_settlements
    ADD COLUMN customer_id INT NULL AFTER id,
    ADD COLUMN supplier_id INT NULL AFTER customer_id,
    ADD COLUMN salesman_id INT NULL AFTER supplier_id,
    ADD KEY idx_ls_customer (customer_id),
    ADD KEY idx_ls_supplier (supplier_id),
    ADD KEY idx_ls_salesman (salesman_id),
    ADD CONSTRAINT fk_ls_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_ls_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_ls_salesman FOREIGN KEY (salesman_id) REFERENCES salesmen(id)  ON UPDATE CASCADE ON DELETE RESTRICT;

UPDATE legacy_settlements ls
JOIN legacy_parties lp ON lp.id = ls.party_id
SET ls.customer_id = lp.linked_customer_id,
    ls.supplier_id = lp.linked_supplier_id,
    ls.salesman_id = lp.linked_salesman_id;

ALTER TABLE legacy_obligations DROP FOREIGN KEY fk_lo_party;
ALTER TABLE legacy_obligations DROP COLUMN party_id;

ALTER TABLE legacy_settlements DROP FOREIGN KEY fk_ls_party;
ALTER TABLE legacy_settlements DROP COLUMN party_id;

DROP TABLE legacy_parties;
