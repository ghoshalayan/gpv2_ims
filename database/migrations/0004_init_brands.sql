-- Module #5 Brands
-- Replaces the legacy `bill_type` free-string column.
-- Purchase invoices are single-brand; sales invoices may be multi-brand.
-- Products will carry brand_id (added in module #6).
--
-- Seeds the "Generic" fallback brand. Legacy products with blank/unknown
-- bill_type will be migrated under this brand at go-live (per D18).

CREATE TABLE brands (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    description     VARCHAR(255) DEFAULT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by      INT          DEFAULT NULL,
    updated_by      INT          DEFAULT NULL,
    UNIQUE KEY uniq_brands_name (name),
    KEY idx_brands_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System brand. Protected from rename / disable / delete by the controller.
INSERT INTO brands (name, description) VALUES
    ('Generic', 'System fallback brand for un-branded or unclassified products. Do not rename or disable.');
