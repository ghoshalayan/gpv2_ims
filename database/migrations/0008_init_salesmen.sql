-- Module #8 Salesmen
--
-- Replaces legacy `decorator_tbl`. Each salesman has a default commission rate (%)
-- that can be overridden per sale line.
--
-- Per D29 a salesman is optionally linked to a customer record (so they can also buy
-- on credit, settled against commission balance). The linked_customer_id FK is added
-- in a later migration once the customers table exists (module #9).

CREATE TABLE salesmen (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    phone               VARCHAR(20)  NOT NULL,
    email               VARCHAR(150) DEFAULT NULL,
    address             TEXT         DEFAULT NULL,
    state               VARCHAR(100) DEFAULT NULL,
    gstin               VARCHAR(15)  DEFAULT NULL,
    commission_rate     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    linked_customer_id  INT          DEFAULT NULL,
    remarks             VARCHAR(500) DEFAULT NULL,
    status              ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT          DEFAULT NULL,
    updated_by          INT          DEFAULT NULL,
    UNIQUE KEY uniq_salesmen_name (name),
    UNIQUE KEY uniq_salesmen_phone (phone),
    KEY idx_salesmen_status (status),
    KEY idx_salesmen_linked_customer (linked_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
