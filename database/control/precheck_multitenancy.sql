-- =====================================================================
--  PRECHECK — run this BEFORE multitenancy_convert.sql
-- =====================================================================
--  Read-only apart from three temporary objects it creates and drops
--  again. It answers one question: does this hosting account allow the
--  three things single-database multi-tenancy depends on?
--
--     CREATE ROUTINE   a function the views call to read the current tenant
--     CREATE VIEW      the per-table views that do the filtering
--     TRIGGER          stamps the tenant on every insert
--
--  Shared hosting sometimes withholds CREATE ROUTINE, and some servers
--  block function creation entirely when binary logging is on
--  (log_bin_trust_function_creators = OFF).
--
--  >>> IF ANY LINE BELOW SAYS "BLOCKED", STOP. <<<
--  Do not run the conversion. Tell me which one failed and we will use
--  separate databases per business instead, which needs none of these
--  privileges and is already built and tested.
--
--  Run it against your LIVE database — it changes nothing that lasts.
-- =====================================================================


-- ---------------------------------------------------------------------
-- PIN THE DATABASE.
--   phpMyAdmin runs your SQL against whatever is selected in its sidebar.
--   If that is information_schema (or nothing), unqualified table names
--   resolve to the wrong schema and you get:
--       #1109 - Unknown table 'tenants' in information_schema
--   This USE line removes that whole class of mistake.
--
--   >>> If your database is not named srasinge1_gp, change it here.
--       It is the ONLY place the name appears. <<<
-- ---------------------------------------------------------------------
USE `srasinge1_gp`;

-- 1. Can we create a stored function?
DROP FUNCTION IF EXISTS mt_precheck_fn;
CREATE FUNCTION mt_precheck_fn() RETURNS INT DETERMINISTIC NO SQL RETURN @app_tenant;

-- 2. Can a view call it? (this is the step MySQL refuses if you try to put
--    the session variable in the view directly)
DROP VIEW IF EXISTS mt_precheck_view;
CREATE VIEW mt_precheck_view AS SELECT mt_precheck_fn() AS current_tenant;

-- 3. Does it actually read the session variable?
SET @app_tenant = 42;
SELECT 'function + view'  AS capability,
       current_tenant     AS value_seen,
       IF(current_tenant = 42, 'OK', 'BLOCKED') AS status
  FROM mt_precheck_view;

-- 4. Can we create a trigger?
DROP TABLE IF EXISTS mt_precheck_tbl;
CREATE TABLE mt_precheck_tbl (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL);
CREATE TRIGGER mt_precheck_trg BEFORE INSERT ON mt_precheck_tbl
    FOR EACH ROW SET NEW.tenant_id = @app_tenant;
INSERT INTO mt_precheck_tbl () VALUES ();

SELECT 'trigger'  AS capability,
       tenant_id  AS value_seen,
       IF(tenant_id = 42, 'OK', 'BLOCKED') AS status
  FROM mt_precheck_tbl;

-- 5. Server details, useful if anything needs diagnosing.
SELECT VERSION()                        AS server_version,
       DATABASE()                       AS current_database,
       @@sql_mode                       AS sql_mode,
       @@log_bin_trust_function_creators AS trust_function_creators;

-- 6. Clean up — nothing from the precheck is left behind.
DROP TRIGGER IF EXISTS mt_precheck_trg;
DROP TABLE    IF EXISTS mt_precheck_tbl;
DROP VIEW     IF EXISTS mt_precheck_view;
DROP FUNCTION IF EXISTS mt_precheck_fn;
