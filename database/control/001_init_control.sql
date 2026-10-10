-- =====================================================================
--  CONTROL DATABASE — multi-tenancy control plane
-- =====================================================================
--  Creates the small database that sits ABOVE the tenants. It holds two
--  things and nothing else:
--
--     tenants      which businesses exist, and which database each uses
--     superadmins  the accounts that can manage tenants and enter them
--
--  No business data ever lives here. Each tenant keeps its own complete
--  copy of the 58-table IMS schema in its own database, so two tenants
--  can have the same customer, product, supplier or salesman as entirely
--  unrelated records.
--
--  WHY THE SUPERADMIN LIVES HERE: tenant admins are subordinate to the
--  superadmin, so the superadmin cannot live inside any one tenant's
--  database — it would then be a peer of that tenant's own admins and
--  invisible to the others.
--
--  ------------------------------------------------------------------
--  HOW TO RUN (phpMyAdmin, no SSH needed)
--
--   1. Panel -> MySQL -> create a database, e.g.  <prefix>_control
--      Grant the SAME MySQL user you already use full rights on it.
--   2. phpMyAdmin -> select that NEW database (not the live one!)
--   3. SQL tab -> paste this whole file -> Go
--   4. Edit step 3 below first if your live database is not
--      'srasinge1_gp'.
--
--  >>> Check the database selector says the CONTROL database before you
--  >>> press Go. Running this against the live business database would
--  >>> add two stray tables there.
--  ------------------------------------------------------------------
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Which businesses exist, and where each one's data lives.
-- ---------------------------------------------------------------------
CREATE TABLE tenants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tenant_key      VARCHAR(40)  NOT NULL,   -- short url-safe handle, e.g. 'gp'
    label           VARCHAR(150) NOT NULL,   -- shown in the tenant picker
    database_name   VARCHAR(64)  NOT NULL,   -- the MySQL database holding this tenant's data
    status          ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    remarks         VARCHAR(500) DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_tenant_key (tenant_key),
    UNIQUE KEY uniq_tenant_database (database_name),
    KEY idx_tenant_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. The accounts that sit above all tenants.
--
--    Separate from any tenant's `users` table on purpose: a superadmin is
--    not a user of any one business. Tenant admins remain in their own
--    tenant database and cannot see or reach this table.
--
--    is_protected works as it does for tenant users: the account cannot
--    be deactivated or renamed, but its password CAN still be changed.
--    An account that can be neither disabled nor rotated is an unfixable
--    backdoor.
-- ---------------------------------------------------------------------
CREATE TABLE superadmins (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    username        VARCHAR(150) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    is_protected    TINYINT(1)   NOT NULL DEFAULT 0,
    last_login_at   DATETIME     DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_superadmin_username (username),
    KEY idx_superadmin_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. Audit trail for superadmin actions.
--
--    Entering a tenant means reading another business's complete books,
--    so it is recorded. Each tenant's own activity_log stays inside that
--    tenant and is unaffected.
-- ---------------------------------------------------------------------
CREATE TABLE superadmin_activity (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    superadmin_id   INT          DEFAULT NULL,
    username        VARCHAR(150) DEFAULT NULL,   -- kept even if the account is later removed
    action          VARCHAR(40)  NOT NULL,       -- login / login_failed / enter_tenant / leave_tenant / create_tenant / ...
    tenant_id       INT          DEFAULT NULL,
    tenant_key      VARCHAR(40)  DEFAULT NULL,
    ip_address      VARCHAR(45)  DEFAULT NULL,
    notes           VARCHAR(500) DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sa_act_admin (superadmin_id),
    KEY idx_sa_act_action (action),
    KEY idx_sa_act_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 4. Register the existing live database as the first tenant.
--
--    *** CHANGE 'srasinge1_gp' BELOW IF YOUR LIVE DATABASE IS NAMED
--    *** DIFFERENTLY. Everything else can stay as-is.
--
--    Nothing is copied or moved — this row simply tells the application
--    that the business you already run is tenant 'gp'.
-- ---------------------------------------------------------------------
INSERT INTO tenants (tenant_key, label, database_name, status, remarks) VALUES
    ('gp', 'GP Enterprise', 'srasinge1_gp', 'active',
     'The original single-tenant database, registered as the first tenant.');


-- ---------------------------------------------------------------------
-- 5. The superadmin account.
--
--    Same username and password as the one already created inside the
--    tenant database, so nothing new has to be remembered. They are
--    separate accounts in separate databases; this one sits above all
--    tenants.
--
--    >>> CHANGE THIS PASSWORD AFTER FIRST LOGIN. <<<
-- ---------------------------------------------------------------------
INSERT INTO superadmins (name, username, password_hash, status, is_protected) VALUES
    ('Super Administrator', 'superadmin',
     '$2y$10$GGCgTPjuwX3lnJKIQOZj..adxWNd8yIQPRDNrVvRLLPJq36zUXr46',
     'active', 1);


-- ---------------------------------------------------------------------
-- 6. CONFIRM — expect one tenant row and one superadmin row.
-- ---------------------------------------------------------------------
SELECT 'tenant' AS kind, tenant_key AS id_or_key, label AS name,
       database_name AS detail, status
  FROM tenants
UNION ALL
SELECT 'superadmin', username, name,
       IF(is_protected = 1, 'protected', 'not protected'), status
  FROM superadmins;
