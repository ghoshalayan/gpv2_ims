-- Dynamic Roles
--
-- Replaces the hard-coded users.role ENUM with an FK to a `roles` table so admin
-- can create new roles without a code change.
--
-- Convention:
--   * 'admin' role is the master-switch role (Permissions::can() short-circuits to
--     allowed for any user with role_key='admin'). Marked is_system=1 so the
--     roles UI cannot delete it.
--   * Every other role's access is fully driven by role_permissions + per-user
--     overrides; the role_key string itself carries no privilege.
--
-- Migration steps (must run in order — DDL is auto-commit in MySQL, so partial
-- failure can leave a half-cleaned schema; idempotent guards aren't possible
-- here without significant complexity, so we keep each step minimal):
--   1. Create roles table.
--   2. Seed default roles (admin / subadmin / cashier — same keys as the
--      retired ENUM, so existing user rows can be remapped 1:1).
--   3. Add users.role_id (nullable for the population step).
--   4. Populate role_id from the existing role string.
--   5. Drop the old role column + index.
--   6. Make role_id NOT NULL and add the FK + index.

CREATE TABLE roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    role_key    VARCHAR(40)  NOT NULL,
    label       VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_system   TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order  INT          NOT NULL DEFAULT 100,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_roles_key (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (role_key, label, description, is_system, sort_order) VALUES
    ('admin',    'Administrator',     'Full access. Always allowed (master switch). Cannot be deleted.', 1, 1),
    ('subadmin', 'Sub-Administrator', 'Manages day-to-day operations; cannot reset/delete or change settings.', 0, 10),
    ('cashier',  'Cashier',           'Front-desk POS staff; sales workflow only.', 0, 20);

ALTER TABLE users ADD COLUMN role_id INT NULL AFTER role;

UPDATE users u JOIN roles r ON r.role_key = u.role SET u.role_id = r.id;

ALTER TABLE users DROP KEY idx_users_role;

ALTER TABLE users DROP COLUMN role;

ALTER TABLE users MODIFY role_id INT NOT NULL;

ALTER TABLE users ADD KEY idx_users_role_id (role_id);

ALTER TABLE users ADD CONSTRAINT fk_users_role_id FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE RESTRICT;
