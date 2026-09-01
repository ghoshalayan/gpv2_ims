-- Stock Adjustments — write-offs and count corrections (D15 + D6 oversell-block).
--
-- Use cases:
--   damage, expiry, theft → direction='out' only (stock leaves at current moving-avg cost)
--   count_correction → 'out' for missing stock, 'in' for found stock (split into two adjustments)
--   other → either direction
--
-- Cost basis snapshotting:
--   line.cost_at_adjustment is captured at post time. Out lines stamp the current
--   moving-avg cost (what the stock was costed at when it left). In lines (count
--   corrections finding stock) require admin-entered cost — system can't infer cost
--   for stock it didn't track.
--
-- BALANCE INTEGRITY (verified in IntegrityCheckService):
--   per line: quantity × cost_at_adjustment ≈ line_value (rounding tolerance ₹0.01)
--   header:   total_value = SUM(lines.line_value)
--
-- Voiding rules:
--   out adjustment → applyStockIn at snapshot cost (reintroduces units at their original
--                    cost basis; uses source='other' so latest_purchase_landed_cost is untouched)
--   in adjustment  → applyStockOut at CURRENT moving-avg (consumes the found units back out;
--                    refused if quantity_on_hand has since dropped below the line qty)

CREATE TABLE stock_adjustments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    adjustment_number   VARCHAR(100) NOT NULL,             -- GP-ADJ/{FY}/{NNNNN}
    adjustment_date     DATE         NOT NULL,
    reason              ENUM('damage', 'expiry', 'theft', 'count_correction', 'other') NOT NULL,
    direction           ENUM('out', 'in') NOT NULL,
    total_value         DECIMAL(14,2) NOT NULL,            -- sum of line values
    remarks             VARCHAR(500) DEFAULT NULL,
    status              ENUM('posted', 'voided') NOT NULL DEFAULT 'posted',
    voided_at           DATETIME     DEFAULT NULL,
    voided_by           INT          DEFAULT NULL,
    void_reason         VARCHAR(255) DEFAULT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT          NOT NULL,
    UNIQUE KEY uniq_adj_number (adjustment_number),
    KEY idx_adj_date     (adjustment_date),
    KEY idx_adj_reason   (reason),
    KEY idx_adj_status   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_adjustment_lines (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    stock_adjustment_id      INT          NOT NULL,
    line_no                  INT          NOT NULL,
    product_id               INT          NOT NULL,
    quantity                 DECIMAL(14,3) NOT NULL,       -- always positive; sign comes from header.direction
    cost_at_adjustment       DECIMAL(14,4) NOT NULL,       -- snapshot at post time
    line_value               DECIMAL(14,2) NOT NULL,       -- = quantity × cost_at_adjustment
    notes                    VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uniq_adj_line (stock_adjustment_id, line_no),
    KEY idx_adjl_product (product_id),
    CONSTRAINT fk_adjl_header   FOREIGN KEY (stock_adjustment_id) REFERENCES stock_adjustments(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_adjl_product  FOREIGN KEY (product_id)          REFERENCES products(id)          ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
