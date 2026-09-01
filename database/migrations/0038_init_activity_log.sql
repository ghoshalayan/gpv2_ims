-- System Activity Log (D48 + D56) — append-only audit trail for transactional records.
--
-- v1 captures: create / update / delete / void / refund / finalize / cancel / restore / login / logout.
-- Page views are NOT logged. Each row snapshots:
--   * actor (user_id + user_name at the time, since the user may be renamed/deactivated later)
--   * entity (kind + id + human label so the row is readable even if the entity is later deleted)
--   * before_json / after_json — optional, populated for `update` and `void`/`refund` where state matters
--   * ip_address — when available
--
-- Retention: keep forever until admin manually purges (D56). The purge action itself logs an entry.
-- CSV/JSON export before purge is a separate flow (deferred to a follow-up step).
--
-- Append-only: the only mutation expected is INSERT. No UPDATE/DELETE in normal flow.

CREATE TABLE activity_log (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    created_at    DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    user_id       INT          DEFAULT NULL,                 -- NULL for anonymous (failed login pre-auth)
    user_name     VARCHAR(150) DEFAULT NULL,                 -- snapshot of user.name at log time
    action        ENUM('create','update','delete','void','refund','finalize','cancel','restore','login','logout','purge') NOT NULL,
    entity_kind   VARCHAR(64)  NOT NULL,                     -- 'sales_invoice', 'brand_rebate', etc.
    entity_id     INT          DEFAULT NULL,                 -- NULL for non-row events (login)
    entity_label  VARCHAR(255) DEFAULT NULL,                 -- snapshot label (invoice_number, advance_number, etc.)
    ip_address    VARCHAR(45)  DEFAULT NULL,                 -- IPv4 or IPv6
    before_json   LONGTEXT     DEFAULT NULL,
    after_json    LONGTEXT     DEFAULT NULL,
    notes         VARCHAR(500) DEFAULT NULL,
    KEY idx_al_created (created_at),
    KEY idx_al_user    (user_id),
    KEY idx_al_kind    (entity_kind),
    KEY idx_al_entity  (entity_kind, entity_id),
    KEY idx_al_action  (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
