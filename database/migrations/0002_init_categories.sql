-- Module #3 Categories
-- Top-level product grouping. Sub-categories (module #4) FK to this.

CREATE TABLE categories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    description     VARCHAR(255) DEFAULT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by      INT          DEFAULT NULL,
    updated_by      INT          DEFAULT NULL,
    UNIQUE KEY uniq_categories_name (name),
    KEY idx_categories_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
