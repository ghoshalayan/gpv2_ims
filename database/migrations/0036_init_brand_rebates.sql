-- Brand Rebates — money/credit a brand grants us when a sales/purchase target is hit.
-- Source-of-truth document is the brand-issued credit note (number + date REQUIRED).
--
-- Four modes (D22 + D39 + 2026-04-26 user clarification):
--   1. cash_back        — brand pays us cash. Bank/drawer credited; P&L "Brand Rebate Income".
--   2. reduce_payable   — credit note offsets future purchase bills. Auto-creates a non-cash
--                         supplier_advance (payment_mode='rebate', paid_from_kind=NULL) which
--                         the existing apply-from-advance flow consumes against next bill(s).
--   3. cost_reduction   — retroactive landed-cost drop on on-hand stock for the brand.
--                         min(rebate, on_hand_value) reduces inventory carrying value;
--                         spillover (rebate − on_hand_value, if positive) crosses to income.
--                         Tracked per-product in brand_rebate_cost_adjustments so void can reverse.
--   4. free_stock       — brand sends us X units of one product. Stock-in at value_per_unit
--                         (admin-entered, defaults to current moving-avg). Rebate income is
--                         implicit in the value-at-cost basis (AS-2 compliant; preserves
--                         moving-avg).
--
-- BALANCE INTEGRITY (verified in IntegrityCheckService):
--   - cash_back rebates received_into account → +amount on bank/drawer ledger
--   - reduce_payable → exactly one linked supplier_advance row exists with same amount
--   - cost_reduction → SUM(brand_rebate_cost_adjustments.amount_applied) per rebate
--                      ≤ rebate.amount; remainder = rebate.income_spillover
--   - free_stock → product stock += free_quantity; product moving-avg unchanged
--                  if value_per_unit == previous moving-avg (admin choice)

CREATE TABLE brand_rebates (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    rebate_number            VARCHAR(100) NOT NULL,                 -- GP-RBT/{FY}/{NNNNN}
    rebate_date              DATE         NOT NULL,
    brand_id                 INT          NOT NULL,
    supplier_id              INT          NOT NULL,                 -- who granted/issued
    mode                     ENUM('cash_back', 'reduce_payable', 'cost_reduction', 'free_stock') NOT NULL,
    amount                   DECIMAL(14,2) NOT NULL,                -- gross rebate value (= credit note amount)

    credit_note_number       VARCHAR(100) NOT NULL,                 -- brand's credit note ref
    credit_note_date         DATE         NOT NULL,

    -- mode=cash_back
    received_into_kind       ENUM('bank_account', 'cashier_drawer') DEFAULT NULL,
    received_into_id         INT          DEFAULT NULL,
    payment_mode             ENUM('cash', 'neft', 'imps', 'rtgs', 'upi', 'cheque', 'other') DEFAULT NULL,
    payment_reference        VARCHAR(100) DEFAULT NULL,             -- UTR / cheque #

    -- mode=reduce_payable: link to the non-cash supplier_advance we auto-create
    linked_advance_id        INT          DEFAULT NULL,

    -- mode=cost_reduction: optional invoice scoping (else applies to all on-hand stock for brand)
    target_invoice_id        INT          DEFAULT NULL,
    income_spillover         DECIMAL(14,2) NOT NULL DEFAULT 0.00,   -- rebate − on_hand_value when positive

    -- mode=free_stock
    free_product_id          INT          DEFAULT NULL,
    free_quantity            DECIMAL(14,3) DEFAULT NULL,
    free_value_per_unit      DECIMAL(14,2) DEFAULT NULL,

    remarks                  VARCHAR(500) DEFAULT NULL,
    status                   ENUM('posted', 'voided') NOT NULL DEFAULT 'posted',
    voided_at                DATETIME     DEFAULT NULL,
    voided_by                INT          DEFAULT NULL,
    void_reason              VARCHAR(255) DEFAULT NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by               INT          NOT NULL,

    UNIQUE KEY uniq_rebate_number (rebate_number),
    KEY idx_rbt_brand    (brand_id),
    KEY idx_rbt_supplier (supplier_id),
    KEY idx_rbt_mode     (mode),
    KEY idx_rbt_date     (rebate_date),
    KEY idx_rbt_status   (status),
    CONSTRAINT fk_rbt_brand    FOREIGN KEY (brand_id)         REFERENCES brands(id)             ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rbt_supplier FOREIGN KEY (supplier_id)      REFERENCES suppliers(id)          ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rbt_inv      FOREIGN KEY (target_invoice_id) REFERENCES purchase_invoices(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rbt_prod     FOREIGN KEY (free_product_id)   REFERENCES products(id)          ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rbt_adv      FOREIGN KEY (linked_advance_id) REFERENCES supplier_advances(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-product cost-reduction trace (one row per affected product). Lets void reverse the
-- exact moving-avg delta posted at rebate time.
CREATE TABLE brand_rebate_cost_adjustments (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    rebate_id                INT          NOT NULL,
    product_id               INT          NOT NULL,
    on_hand_qty_at_post      DECIMAL(14,3) NOT NULL,                -- snapshot
    moving_avg_before        DECIMAL(14,4) NOT NULL,                -- snapshot
    amount_applied           DECIMAL(14,2) NOT NULL,                -- portion of rebate applied to this product
    moving_avg_delta         DECIMAL(14,4) NOT NULL,                -- amount_applied / on_hand_qty (per-unit reduction)
    moving_avg_after         DECIMAL(14,4) NOT NULL,                -- == moving_avg_before − moving_avg_delta
    created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rca_rebate  (rebate_id),
    KEY idx_rca_product (product_id),
    CONSTRAINT fk_rca_rebate  FOREIGN KEY (rebate_id)  REFERENCES brand_rebates(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rca_product FOREIGN KEY (product_id) REFERENCES products(id)      ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Allow non-cash supplier_advances. The reduce_payable rebate creates one with
-- paid_from_kind/id NULL and payment_mode='rebate' so it shows up in the existing
-- apply-from-advance UI without polluting the cash-payout flows.
ALTER TABLE supplier_advances
    MODIFY COLUMN paid_from_kind ENUM('bank_account', 'cashier_drawer') DEFAULT NULL,
    MODIFY COLUMN paid_from_id   INT DEFAULT NULL,
    MODIFY COLUMN payment_mode   ENUM('cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other', 'rebate')
                                 NOT NULL DEFAULT 'cash';
