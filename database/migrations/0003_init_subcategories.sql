-- Module #4 Sub-Categories
-- Belong to a category. Name is unique within a category, not globally.

CREATE TABLE subcategories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category_id     INT          NOT NULL,
    name            VARCHAR(150) NOT NULL,
    description     VARCHAR(255) DEFAULT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by      INT          DEFAULT NULL,
    updated_by      INT          DEFAULT NULL,
    UNIQUE KEY uniq_subcategories_category_name (category_id, name),
    KEY idx_subcategories_status (status),
    CONSTRAINT fk_subcategories_category FOREIGN KEY (category_id) REFERENCES categories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
