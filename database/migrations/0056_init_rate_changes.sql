-- CR-4 Rate Management — category / subcategory-wise selling-price changes.
--
-- A "rate change" is a one-time BULK UPDATE of products.default_selling_price across a
-- category / subcategory / brand selection. Products keep owning their own price; this
-- is not an inherited rule, so no pricing read path anywhere else has to change.
--
-- WHY A DEDICATED AUDIT TABLE (and not product_price_history):
-- product_price_history records LANDED COST — it feeds moving-average cost,
-- latest_purchase_landed_cost and the market-value columns on stock reports. Selling
-- prices must never be written there or those figures would be corrupted. Selling-price
-- history therefore gets its own trail here.
--
-- REVERSIBILITY: every affected product's before/after price is stored per line, so a
-- whole batch can be reverted in one action. Revert refuses when a product's current
-- price no longer matches what the batch set (someone edited it since), mirroring the
-- drift-detection that brand-rebate void already uses rather than silently clobbering
-- a newer deliberate price.
--
-- Nothing here touches stock, moving_avg_cost, or any posted document, so no accounting
-- invariant can be affected. Rolling the feature back is dropping these two tables.

CREATE TABLE rate_change_batches (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    batch_number        VARCHAR(100) NOT NULL,                -- GP-RATE/{FY}/{NNNNN}
    change_date         DATE         NOT NULL,

    -- Selection used (all optional; NULL = "not narrowed by this dimension").
    category_id         INT          DEFAULT NULL,
    subcategory_id      INT          DEFAULT NULL,
    brand_id            INT          DEFAULT NULL,

    -- percent    : new = old × (1 + value/100)   (value may be negative)
    -- flat_amount: new = old + value             (value may be negative)
    -- set_price  : new = value                   (value must be >= 0)
    mode                ENUM('percent', 'flat_amount', 'set_price') NOT NULL,
    value               DECIMAL(12,2) NOT NULL,
    round_to_rupee      TINYINT(1)   NOT NULL DEFAULT 0,

    products_affected   INT          NOT NULL DEFAULT 0,
    remarks             VARCHAR(500) DEFAULT NULL,

    status              ENUM('applied', 'reverted') NOT NULL DEFAULT 'applied',
    reverted_at         DATETIME     DEFAULT NULL,
    reverted_by         INT          DEFAULT NULL,

    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          INT          DEFAULT NULL,

    UNIQUE KEY uniq_rate_change_number (batch_number),
    KEY idx_rcb_date (change_date),
    KEY idx_rcb_status (status),
    KEY idx_rcb_category (category_id),
    KEY idx_rcb_subcategory (subcategory_id),
    KEY idx_rcb_brand (brand_id),
    CONSTRAINT fk_rcb_category    FOREIGN KEY (category_id)    REFERENCES categories(id)    ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_rcb_subcategory FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_rcb_brand       FOREIGN KEY (brand_id)       REFERENCES brands(id)        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per product touched. old_price is what revert restores; new_price is what
-- revert checks the product still carries before restoring.
CREATE TABLE rate_change_lines (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    batch_id            INT           NOT NULL,
    product_id          INT           NOT NULL,
    old_price           DECIMAL(12,2) NOT NULL,
    new_price           DECIMAL(12,2) NOT NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rcl_batch (batch_id),
    KEY idx_rcl_product (product_id),
    CONSTRAINT fk_rcl_batch   FOREIGN KEY (batch_id)   REFERENCES rate_change_batches(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rcl_product FOREIGN KEY (product_id) REFERENCES products(id)            ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
