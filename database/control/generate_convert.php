<?php
/** Generates the single-database multi-tenancy conversion SQL from the live schema. */
require 'C:/gp/gp/gpv2_ims/app/bootstrap.php';

$p  = App\Support\Db::pdo();
$db = $p->query("SELECT DATABASE()")->fetchColumn();

$shared = ['migrations','roles','role_permissions','units','tenants','superadmins','superadmin_activity'];
$tables = $p->query("SELECT table_name t FROM information_schema.tables
  WHERE table_schema='$db' AND table_type='BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
$scoped = array_values(array_diff($tables, $shared));

$uni = [];
foreach ($p->query("SELECT table_name t, index_name i, GROUP_CONCAT(column_name ORDER BY seq_in_index) c
  FROM information_schema.statistics WHERE table_schema='$db' AND non_unique=0 AND index_name<>'PRIMARY'
  GROUP BY t,i")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (in_array($r['t'], $scoped, true)) { $uni[$r['t']][$r['i']] = $r['c']; }
}

// Columns on which this table has an OUTGOING foreign key. A unique index whose
// first column is one of these is the index that constraint relies on, and MySQL
// refuses to drop it (error 1553) unless another index can take over first.
$fkCols = [];
foreach ($p->query("SELECT table_name t, column_name c FROM information_schema.key_column_usage
  WHERE table_schema='$db' AND referenced_table_name IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $fkCols[$r['t']][$r['c']] = true;
}

// A table whose PRIMARY KEY contains no AUTO_INCREMENT column has keys that are
// only unique within one business — business_profile is pinned at id = 1, and
// system_settings is keyed by setting_key. Those collide the moment a second
// business exists, so tenant_id has to join the primary key. Tables with an
// AUTO_INCREMENT id are already globally unique and are left alone.
$pkFix = [];
foreach ($scoped as $t) {
    $ai = $p->query("SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema='$db' AND table_name='$t' AND extra LIKE '%auto_increment%'")->fetchColumn();
    if ($ai) { continue; }
    $pk = $p->query("SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics
        WHERE table_schema='$db' AND table_name='$t' AND index_name='PRIMARY'")->fetchColumn();
    if (!$pk) { continue; }
    // A PK column that is a foreign key to an AUTO_INCREMENT id is already globally
    // unique, so that key cannot collide between businesses and is left alone.
    // (user_permission_overrides is keyed on user_id, which is such a column.)
    $inheritsUnique = false;
    foreach (explode(',', $pk) as $col) {
        if (isset($fkCols[$t][$col])) { $inheritsUnique = true; break; }
    }
    if (!$inheritsUnique) { $pkFix[$t] = $pk; }
}

$HASH = '$2y$10$GGCgTPjuwX3lnJKIQOZj..adxWNd8yIQPRDNrVvRLLPJq36zUXr46';
$nShared = count(array_intersect($tables, $shared));

$h = <<<SQL
-- =====================================================================
--  MULTI-TENANCY — single-database conversion
-- =====================================================================
--  Converts this database from one business to many, keeping ALL existing
--  data as tenant 1.
--
--  HOW IT WORKS
--  Each business table gains a tenant_id, is renamed to <table>__data, and
--  a VIEW with the original name takes its place showing only the current
--  tenant's rows. Every one of the app's 869 queries keeps using the
--  original name, so all of them are filtered automatically — isolation is
--  enforced by the database, not by a WHERE clause somebody has to
--  remember to write in 869 places.
--
--  IT FAILS CLOSED. With no tenant selected the views return no rows and
--  writes are refused outright, rather than silently landing in tenant 1.
--
--  >>> PREREQUISITE: run precheck_multitenancy.sql FIRST. <<<
--  This needs CREATE ROUTINE, CREATE VIEW and TRIGGER privileges, which
--  some shared-hosting plans withhold. If the precheck fails, stop —
--  nothing below will work.
--
--  >>> TAKE A FULL DATABASE EXPORT FIRST. <<< This rewrites every
--  business table in place. The export is the only way back.
--
--  Generated from the live schema: {$nShared} shared tables,
SQL;
$h .= count($scoped) . " tenant-scoped.\n-- =====================================================================\n";

$o = [$h];
$o[] = "-- ---------------------------------------------------------------------";
$o[] = "-- 1. Control tables. Shared across tenants, so they carry no tenant_id.";
$o[] = "-- ---------------------------------------------------------------------";
$o[] = "CREATE TABLE tenants (";
$o[] = "    id            INT AUTO_INCREMENT PRIMARY KEY,";
$o[] = "    tenant_key    VARCHAR(40)  NOT NULL,";
$o[] = "    label         VARCHAR(150) NOT NULL,";
$o[] = "    status        ENUM('active','suspended') NOT NULL DEFAULT 'active',";
$o[] = "    remarks       VARCHAR(500) DEFAULT NULL,";
$o[] = "    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,";
$o[] = "    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,";
$o[] = "    UNIQUE KEY uniq_tenant_key (tenant_key)";
$o[] = ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
$o[] = "";
$o[] = "CREATE TABLE superadmins (";
$o[] = "    id            INT AUTO_INCREMENT PRIMARY KEY,";
$o[] = "    name          VARCHAR(150) NOT NULL,";
$o[] = "    username      VARCHAR(150) NOT NULL,";
$o[] = "    password_hash VARCHAR(255) NOT NULL,";
$o[] = "    email         VARCHAR(150) DEFAULT NULL,";
$o[] = "    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',";
$o[] = "    is_protected  TINYINT(1)   NOT NULL DEFAULT 0,";
$o[] = "    last_login_at DATETIME     DEFAULT NULL,";
$o[] = "    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,";
$o[] = "    UNIQUE KEY uniq_superadmin_username (username)";
$o[] = ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
$o[] = "";
$o[] = "CREATE TABLE superadmin_activity (";
$o[] = "    id            INT AUTO_INCREMENT PRIMARY KEY,";
$o[] = "    superadmin_id INT          DEFAULT NULL,";
$o[] = "    username      VARCHAR(150) DEFAULT NULL,";
$o[] = "    action        VARCHAR(40)  NOT NULL,";
$o[] = "    tenant_id     INT          DEFAULT NULL,";
$o[] = "    tenant_key    VARCHAR(40)  DEFAULT NULL,";
$o[] = "    ip_address    VARCHAR(45)  DEFAULT NULL,";
$o[] = "    notes         VARCHAR(500) DEFAULT NULL,";
$o[] = "    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,";
$o[] = "    KEY idx_sa_act_created (created_at)";
$o[] = ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
$o[] = "";
$o[] = "-- Your existing business becomes tenant 1 — which is why every tenant_id";
$o[] = "-- column added below defaults to 1.";
$o[] = "INSERT INTO tenants (id, tenant_key, label, remarks) VALUES";
$o[] = "    (1, 'gp', 'GP Enterprise', 'The original business, kept as tenant 1.');";
$o[] = "";
$o[] = "-- Same temporary password as the 'owner' account. CHANGE IT AFTER LOGIN.";
$o[] = "INSERT INTO superadmins (name, username, password_hash, is_protected) VALUES";
$o[] = "    ('Super Administrator', 'superadmin', '{$HASH}', 1);";
$o[] = "";
$o[] = "-- ---------------------------------------------------------------------";
$o[] = "-- 2. The current tenant, readable from inside a view.";
$o[] = "--    MySQL/MariaDB forbid a session variable in a view definition, so it";
$o[] = "--    is wrapped in a function. The app sets @app_tenant on every";
$o[] = "--    connection; outside the app it is NULL, so the views show nothing.";
$o[] = "-- ---------------------------------------------------------------------";
$o[] = "DROP FUNCTION IF EXISTS app_tenant;";
$o[] = "CREATE FUNCTION app_tenant() RETURNS INT DETERMINISTIC NO SQL RETURN @app_tenant;";
$o[] = "";
$o[] = "-- ---------------------------------------------------------------------";
$o[] = "-- 3. Convert each business table.";
$o[] = "-- ---------------------------------------------------------------------";
$o[] = "SET FOREIGN_KEY_CHECKS = 0;";
$o[] = "";
foreach ($scoped as $t) {
    $o[] = "-- {$t}";
    $o[] = "ALTER TABLE `{$t}` ADD COLUMN tenant_id INT NOT NULL DEFAULT 1, ADD KEY `idx_{$t}_tn` (tenant_id);";
    foreach ($uni[$t] ?? [] as $i => $c) {
        $parts = explode(',', $c);
        $cols  = implode(', ', array_map(fn($x) => "`{$x}`", $parts));
        // Prepending tenant_id moves the FK column out of first position, which would
        // leave the constraint unindexed. Give it a plain index to fall back on first.
        if (isset($fkCols[$t][$parts[0]])) {
            $o[] = "ALTER TABLE `{$t}` ADD KEY `idx_{$i}_fk` (`{$parts[0]}`);";
        }
        $o[] = "ALTER TABLE `{$t}` DROP INDEX `{$i}`, ADD UNIQUE KEY `{$i}` (tenant_id, {$cols});";
    }
    if (isset($pkFix[$t])) {
        $pkc = implode(', ', array_map(fn($x) => "`{$x}`", explode(',', $pkFix[$t])));
        $o[] = "-- PK has no AUTO_INCREMENT, so it is only unique within one business.";
        $o[] = "ALTER TABLE `{$t}` DROP PRIMARY KEY, ADD PRIMARY KEY (tenant_id, {$pkc});";
    }
    $o[] = "RENAME TABLE `{$t}` TO `{$t}__data`;";
    $o[] = "CREATE VIEW `{$t}` AS SELECT * FROM `{$t}__data` WHERE tenant_id = app_tenant();";
    $o[] = "CREATE TRIGGER `{$t}__stamp` BEFORE INSERT ON `{$t}__data` FOR EACH ROW SET NEW.tenant_id = @app_tenant;";
    $o[] = "";
}
$o[] = "SET FOREIGN_KEY_CHECKS = 1;";
$o[] = "";
$o[] = "-- ---------------------------------------------------------------------";
$o[] = "-- 4. CONFIRM. Expect " . count($scoped) . " views, " . count($scoped) . " triggers, 1 tenant, 1 superadmin,";
$o[] = "--    and your existing rows all visible to tenant 1.";
$o[] = "-- ---------------------------------------------------------------------";
$o[] = "SET @app_tenant = 1;";
$o[] = "SELECT (SELECT COUNT(*) FROM information_schema.views    WHERE table_schema  = DATABASE()) AS views_created,";
$o[] = "       (SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE()) AS triggers_created,";
$o[] = "       (SELECT COUNT(*) FROM tenants)        AS tenants,";
$o[] = "       (SELECT COUNT(*) FROM superadmins)    AS superadmins,";
$o[] = "       (SELECT COUNT(*) FROM products)       AS products_seen_by_tenant_1,";
$o[] = "       (SELECT COUNT(*) FROM customers)      AS customers_seen_by_tenant_1,";
$o[] = "       (SELECT COUNT(*) FROM sales_invoices) AS invoices_seen_by_tenant_1;";

$sql = implode("\n", $o) . "\n";
file_put_contents('C:/gp/gp/gpv2_ims/database/control/multitenancy_convert.sql', $sql);
printf("wrote multitenancy_convert.sql — %s bytes, %d scoped tables, %d unique keys rewritten\n",
    number_format(strlen($sql)), count($scoped), array_sum(array_map('count', $uni)));
