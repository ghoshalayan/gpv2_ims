-- =====================================================================
--  MULTI-TENANCY — post-conversion verification
-- =====================================================================
--  Run this AFTER multitenancy_convert.sql, in phpMyAdmin, against the
--  live database.
--
--  Why it exists: phpMyAdmin stops at the first statement that errors and
--  reports only that one. A conversion can therefore end up HALF applied —
--  some tables converted, the rest still plain — and the summary counts at
--  the end of the convert script would not tell you which. This checks
--  every table individually and names anything that is wrong.
--
--  Section 0 pins the database with USE, so it does not matter what
--  phpMyAdmin has selected. Change the name there if yours differs.
--
--  Query 5 is the one that matters: it must return NO ROWS.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 0. PIN THE DATABASE.
--
--    This USE statement is what makes the rest of the script work no
--    matter what phpMyAdmin has selected in its sidebar. Without it, a
--    query like "FROM tenants" is looked up in whatever schema happens to
--    be current - and if that is information_schema you get:
--        #1109 - Unknown table 'tenants' in information_schema
--
--    >>> If your database is not named srasinge1_gp, change it on the
--        next line. It is the ONLY place the name appears. <<<
-- ---------------------------------------------------------------------
USE `srasinge1_gp`;

-- Confirm. This must print your database name, not information_schema.
SELECT DATABASE() AS i_am_in_this_database;

-- ---------------------------------------------------------------------
-- 1. Totals. Expect 54 views, 54 triggers, 1 tenant, 1 superadmin.
-- ---------------------------------------------------------------------
SET @app_tenant = 1;

SELECT 'views'      AS object, COUNT(*) AS found, 54 AS expected
  FROM information_schema.views    WHERE table_schema   = DATABASE()
UNION ALL
SELECT 'triggers',  COUNT(*), 54
  FROM information_schema.triggers WHERE trigger_schema = DATABASE()
UNION ALL
SELECT 'base __data tables', COUNT(*), 54
  FROM information_schema.tables
 WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
   AND table_name LIKE '%!_!_data' ESCAPE '!'
UNION ALL
SELECT 'tenants',      COUNT(*), 1 FROM tenants
UNION ALL
SELECT 'superadmins',  COUNT(*), 1 FROM superadmins;

-- ---------------------------------------------------------------------
-- 2. The stored function the views depend on. Without it every view is
--    broken, which is the single most likely way a shared host fails.
-- ---------------------------------------------------------------------
SELECT COUNT(*) AS app_tenant_function_exists
  FROM information_schema.routines
 WHERE routine_schema = DATABASE() AND routine_name = 'app_tenant';

SELECT app_tenant() AS should_return_1;

-- ---------------------------------------------------------------------
-- 3. Your data, as tenant 1 sees it. Compare against what you know the
--    business had before the conversion.
-- ---------------------------------------------------------------------
SELECT (SELECT COUNT(*) FROM products)        AS products,
       (SELECT COUNT(*) FROM customers)       AS customers,
       (SELECT COUNT(*) FROM suppliers)       AS suppliers,
       (SELECT COUNT(*) FROM sales_invoices)  AS sales_invoices,
       (SELECT COUNT(*) FROM users)           AS users;

-- ---------------------------------------------------------------------
-- 4. Fails closed? With no tenant bound, every view must be empty.
--    Expect 0, 0, 0.
-- ---------------------------------------------------------------------
SET @app_tenant = NULL;
SELECT (SELECT COUNT(*) FROM products)       AS products_must_be_0,
       (SELECT COUNT(*) FROM customers)      AS customers_must_be_0,
       (SELECT COUNT(*) FROM sales_invoices) AS invoices_must_be_0;
SET @app_tenant = 1;

-- ---------------------------------------------------------------------
-- 5. THE IMPORTANT ONE — every table checked individually.
--    Any row returned here is a table the conversion did not finish.
--    EXPECT ZERO ROWS.
-- ---------------------------------------------------------------------
SELECT d.table_name AS problem_table,
       CASE
         WHEN v.table_name IS NULL THEN 'converted but its VIEW is missing'
         WHEN t.trigger_name IS NULL THEN 'converted but its INSERT TRIGGER is missing'
         WHEN c.column_name IS NULL THEN 'converted but has no tenant_id column'
       END AS problem
  FROM information_schema.tables d
  LEFT JOIN information_schema.views v
         ON v.table_schema = DATABASE()
        AND v.table_name   = LEFT(d.table_name, CHAR_LENGTH(d.table_name) - 6)
  LEFT JOIN information_schema.triggers t
         ON t.trigger_schema = DATABASE()
        AND t.event_object_table = d.table_name
  LEFT JOIN information_schema.columns c
         ON c.table_schema = DATABASE()
        AND c.table_name   = d.table_name
        AND c.column_name  = 'tenant_id'
 WHERE d.table_schema = DATABASE()
   AND d.table_type   = 'BASE TABLE'
   AND d.table_name LIKE '%!_!_data' ESCAPE '!'
   AND (v.table_name IS NULL OR t.trigger_name IS NULL OR c.column_name IS NULL)

UNION ALL

-- Business tables left behind entirely: still plain tables, no view.
SELECT b.table_name,
       'NOT CONVERTED - still a plain table'
  FROM information_schema.tables b
 WHERE b.table_schema = DATABASE()
   AND b.table_type   = 'BASE TABLE'
   AND b.table_name NOT LIKE '%!_!_data' ESCAPE '!'
   AND b.table_name NOT IN ('migrations','roles','role_permissions','units',
                            'tenants','superadmins','superadmin_activity');
