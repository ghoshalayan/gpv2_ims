-- Role-Menu Permissions
--
-- Per-role, per-resource, per-ability allow/deny grid. The set of resources and
-- the abilities each one supports come from App\Support\MenuPermissionRegistry
-- (the "catalog" — code is the source of truth; this table stores the toggles).
--
-- Convention: rows are stored ONLY for non-admin roles. Admin is treated as
-- always-allowed at the application layer (master switch — prevents accidental
-- self-lockout via a bad save). The role-permission UI also hides admin from
-- editable columns.
--
-- Seeded by RolePermissionService::seedDefaults() — invoked from
-- create_admin.php on first install AND from SystemResetService after a hard
-- reset wipes data. The seeder is idempotent (INSERT IGNORE).

CREATE TABLE role_permissions (
    role         ENUM('admin','subadmin','cashier') NOT NULL,
    resource_key VARCHAR(64)  NOT NULL,
    ability      VARCHAR(32)  NOT NULL,
    allowed      TINYINT(1)   NOT NULL DEFAULT 0,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by   INT          DEFAULT NULL,
    PRIMARY KEY (role, resource_key, ability),
    KEY idx_rp_role (role),
    KEY idx_rp_resource (resource_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
