-- Module #9 Customers
--
-- Creates the customers table, adds the reciprocal FK on salesmen.linked_customer_id
-- (deferred from migration 0008), and retroactively creates mirror customer records
-- for any salesmen that already exist (per D29/D38).

CREATE TABLE customers (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    name                  VARCHAR(150) NOT NULL,
    phone                 VARCHAR(20)  NOT NULL,
    email                 VARCHAR(150) DEFAULT NULL,
    address               TEXT         DEFAULT NULL,
    gstin                 VARCHAR(15)  DEFAULT NULL,
    state                 VARCHAR(100) DEFAULT NULL,
    default_salesman_id   INT          DEFAULT NULL,
    credit_limit          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    linked_salesman_id    INT          DEFAULT NULL,
    remarks               VARCHAR(500) DEFAULT NULL,
    status                ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by            INT          DEFAULT NULL,
    updated_by            INT          DEFAULT NULL,
    UNIQUE KEY uniq_customers_name (name),
    UNIQUE KEY uniq_customers_phone (phone),
    KEY idx_customers_status (status),
    KEY idx_customers_default_salesman (default_salesman_id),
    KEY idx_customers_linked_salesman (linked_salesman_id),
    CONSTRAINT fk_customers_default_salesman
        FOREIGN KEY (default_salesman_id) REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_customers_linked_salesman
        FOREIGN KEY (linked_salesman_id) REFERENCES salesmen(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reciprocal FK — salesmen.linked_customer_id → customers.id
ALTER TABLE salesmen
    ADD CONSTRAINT fk_salesmen_linked_customer
    FOREIGN KEY (linked_customer_id) REFERENCES customers(id)
    ON UPDATE CASCADE ON DELETE SET NULL;

-- Retroactive back-fill: mirror every salesman into a customer record.
INSERT INTO customers (name, phone, email, address, state, linked_salesman_id, status, created_by, updated_by)
SELECT name, phone, email, address, state, id, status, created_by, updated_by
FROM salesmen
WHERE linked_customer_id IS NULL;

-- Wire salesmen back to their mirror customer.
UPDATE salesmen s
JOIN customers c ON c.linked_salesman_id = s.id
SET s.linked_customer_id = c.id
WHERE s.linked_customer_id IS NULL;
