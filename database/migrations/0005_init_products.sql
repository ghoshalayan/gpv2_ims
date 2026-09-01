-- Module #6 Products
--
-- Master record for each item the business buys/sells.
-- `quantity_on_hand` and `moving_avg_cost` are maintained by transactional services
-- (purchase, sale, return, write-off) — never edited directly through the product form
-- after creation. The create form accepts an "opening stock" so go-live is possible.
--
-- Cost basis follows D10 (moving average on landed cost). At opening, opening_avg_cost
-- is the per-unit landed cost the user knows from prior books.

CREATE TABLE products (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(150)  NOT NULL,
    brand_id                INT           NOT NULL,
    subcategory_id          INT           NOT NULL,
    unit                    VARCHAR(50)   NOT NULL DEFAULT 'pcs',
    hsn_code                VARCHAR(20)   DEFAULT NULL,
    tax_rate                DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    default_selling_price   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    quantity_on_hand        INT           NOT NULL DEFAULT 0,
    moving_avg_cost         DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    low_stock_threshold     INT           NOT NULL DEFAULT 0,
    description             VARCHAR(500)  DEFAULT NULL,
    status                  ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by              INT           DEFAULT NULL,
    updated_by              INT           DEFAULT NULL,
    UNIQUE KEY uniq_products_name (name),
    KEY idx_products_brand (brand_id),
    KEY idx_products_subcategory (subcategory_id),
    KEY idx_products_status (status),
    KEY idx_products_low_stock (quantity_on_hand, low_stock_threshold),
    CONSTRAINT fk_products_brand
        FOREIGN KEY (brand_id) REFERENCES brands(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_products_subcategory
        FOREIGN KEY (subcategory_id) REFERENCES subcategories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
