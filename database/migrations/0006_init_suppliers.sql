-- Module #7 Suppliers
-- Basic master plus optional GSTIN + state (needed for CGST/SGST vs IGST split per D52).

CREATE TABLE suppliers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    phone           VARCHAR(20)  NOT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    address         TEXT         DEFAULT NULL,
    gstin           VARCHAR(15)  DEFAULT NULL,
    state           VARCHAR(100) DEFAULT NULL,
    remarks         VARCHAR(500) DEFAULT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by      INT          DEFAULT NULL,
    updated_by      INT          DEFAULT NULL,
    UNIQUE KEY uniq_suppliers_name (name),
    KEY idx_suppliers_status (status),
    KEY idx_suppliers_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
