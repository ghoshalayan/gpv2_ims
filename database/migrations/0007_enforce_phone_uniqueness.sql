-- Enforce phone uniqueness across existing counterparty tables.
-- Per user rule: a phone number identifies one entity within each table.
-- Safe to run now since no business data has been entered.

ALTER TABLE users
    ADD UNIQUE KEY uniq_users_phone (phone);

ALTER TABLE suppliers
    ADD UNIQUE KEY uniq_suppliers_phone (phone);
