-- Module #10 Employees
--
-- Master record for staff. Per D41/D44:
--   - Optional `linked_user_id` for employees who also log into the system
--   - `linked_customer_id` so they can buy on credit, settled against salary
--   - Status = active / exited (different from generic active/inactive)
--   - exit_date captured when marked exited
--
-- Adds the reciprocal `linked_employee_id` on customers (mirrors linked_salesman_id).

CREATE TABLE employees (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    name                        VARCHAR(150) NOT NULL,
    phone                       VARCHAR(20)  NOT NULL,
    email                       VARCHAR(150) DEFAULT NULL,
    address                     TEXT         DEFAULT NULL,
    state                       VARCHAR(100) DEFAULT NULL,
    designation                 VARCHAR(150) NOT NULL,
    joining_date                DATE         NOT NULL,
    exit_date                   DATE         DEFAULT NULL,
    salary_amount               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    id_proof_type               VARCHAR(50)  DEFAULT NULL,
    id_proof_number             VARCHAR(50)  DEFAULT NULL,
    pan                         VARCHAR(10)  DEFAULT NULL,
    aadhaar                     VARCHAR(12)  DEFAULT NULL,
    bank_account_number         VARCHAR(30)  DEFAULT NULL,
    bank_ifsc                   VARCHAR(11)  DEFAULT NULL,
    bank_branch                 VARCHAR(150) DEFAULT NULL,
    bank_account_holder_name    VARCHAR(150) DEFAULT NULL,
    linked_user_id              INT          DEFAULT NULL,
    linked_customer_id          INT          DEFAULT NULL,
    remarks                     VARCHAR(500) DEFAULT NULL,
    status                      ENUM('active', 'exited') NOT NULL DEFAULT 'active',
    created_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by                  INT          DEFAULT NULL,
    updated_by                  INT          DEFAULT NULL,
    UNIQUE KEY uniq_employees_name (name),
    UNIQUE KEY uniq_employees_phone (phone),
    KEY idx_employees_status (status),
    KEY idx_employees_designation (designation),
    KEY idx_employees_linked_user (linked_user_id),
    KEY idx_employees_linked_customer (linked_customer_id),
    CONSTRAINT fk_employees_linked_user
        FOREIGN KEY (linked_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_employees_linked_customer
        FOREIGN KEY (linked_customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reciprocal link on customers
ALTER TABLE customers
    ADD COLUMN linked_employee_id INT DEFAULT NULL AFTER linked_salesman_id;

ALTER TABLE customers
    ADD KEY idx_customers_linked_employee (linked_employee_id);

ALTER TABLE customers
    ADD CONSTRAINT fk_customers_linked_employee
    FOREIGN KEY (linked_employee_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE SET NULL;
