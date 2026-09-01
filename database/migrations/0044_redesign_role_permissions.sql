-- Permission storage redesign — role_id-keyed grants + per-user overrides.
--
-- The old role_permissions table from 0042 was keyed by an ENUM(role) and a
-- (resource_key, ability) pair — incompatible with dynamic roles, so it's dropped
-- and recreated as:
--
--   role_permissions       (role_id, permission_key, allowed)
--   user_permission_overrides (user_id, permission_key, allowed)
--
-- permission_key format is 'resource_key.ability', e.g. 'users.view',
-- 'sales_invoices.delete' — flat string keyed off the catalogue in
-- App\Support\MenuPermissionRegistry. The catalogue stays the source of truth
-- for what permissions exist; this table stores who can do what.
--
-- Resolution semantics (App\Support\Permissions):
--   1. admin role short-circuits → allowed.
--   2. role grant (allowed=1) seeds the user's allowed-set.
--   3. user override layered on top: allowed=1 grants extra; allowed=0 denies
--      something the role would otherwise grant.
--
-- We never store explicit deny rows in role_permissions — absence = denied.
-- user_permission_overrides DOES store both because the override needs to
-- distinguish "no opinion" (no row) from "explicit deny" (allowed=0).

DROP TABLE IF EXISTS role_permissions;

CREATE TABLE role_permissions (
    role_id        INT          NOT NULL,
    permission_key VARCHAR(96)  NOT NULL,
    allowed        TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by     INT          DEFAULT NULL,
    PRIMARY KEY (role_id, permission_key),
    KEY idx_rp_perm (permission_key),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_permission_overrides (
    user_id        INT          NOT NULL,
    permission_key VARCHAR(96)  NOT NULL,
    allowed        TINYINT(1)   NOT NULL,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by     INT          DEFAULT NULL,
    PRIMARY KEY (user_id, permission_key),
    KEY idx_upo_perm (permission_key),
    CONSTRAINT fk_upo_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
