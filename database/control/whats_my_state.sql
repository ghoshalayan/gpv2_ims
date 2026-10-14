-- =====================================================================
--  ONE QUERY. Tells us exactly where the conversion got to.
-- =====================================================================
--  Paste this WHOLE file into phpMyAdmin's SQL tab and press Go.
--
--  It does not care which database is selected in the sidebar. Every
--  table name below is written out in full, so there is nothing to click
--  first and nothing that can resolve to the wrong schema.
--
--  It is a single SELECT, so phpMyAdmin cannot run "part" of it.
--
--  >>> If your database is not named srasinge1_gp, use Find & Replace to
--      change every srasinge1_gp below to your name, then run it. <<<
-- =====================================================================

SELECT
    'srasinge1_gp'                                        AS checking_database,

    -- Does the database exist at all / is the name right?
    (SELECT COUNT(*) FROM information_schema.schemata
      WHERE schema_name = 'srasinge1_gp')                 AS database_exists,

    -- How far did multitenancy_convert.sql get?
    (SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = 'srasinge1_gp'
        AND table_name   = 'tenants')                     AS has_tenants_table,

    (SELECT COUNT(*) FROM information_schema.routines
      WHERE routine_schema = 'srasinge1_gp'
        AND routine_name   = 'app_tenant')                AS has_app_tenant_function,

    (SELECT COUNT(*) FROM information_schema.views
      WHERE table_schema = 'srasinge1_gp')                AS views_found_of_54,

    (SELECT COUNT(*) FROM information_schema.triggers
      WHERE trigger_schema = 'srasinge1_gp')              AS triggers_found_of_54,

    (SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = 'srasinge1_gp'
        AND table_type   = 'BASE TABLE'
        AND table_name LIKE '%!_!_data' ESCAPE '!')       AS renamed_tables_of_54,

    -- Is the ORIGINAL data still sitting in plain, unconverted tables?
    (SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = 'srasinge1_gp'
        AND table_type   = 'BASE TABLE'
        AND table_name NOT LIKE '%!_!_data' ESCAPE '!')   AS plain_tables_remaining,

    -- Your data is here either way; this counts rows without needing views.
    (SELECT table_rows FROM information_schema.tables
      WHERE table_schema = 'srasinge1_gp'
        AND table_name IN ('products','products__data')
      ORDER BY table_name LIMIT 1)                        AS approx_product_rows,

    -- The verdict, in words.
    CASE
      WHEN (SELECT COUNT(*) FROM information_schema.schemata
             WHERE schema_name = 'srasinge1_gp') = 0
        THEN 'WRONG DATABASE NAME - edit this file and re-run'
      WHEN (SELECT COUNT(*) FROM information_schema.views
             WHERE table_schema = 'srasinge1_gp') = 0
        THEN 'NOT CONVERTED - convert script never ran. Data untouched.'
      WHEN (SELECT COUNT(*) FROM information_schema.views
             WHERE table_schema = 'srasinge1_gp') = 54
       AND (SELECT COUNT(*) FROM information_schema.triggers
             WHERE trigger_schema = 'srasinge1_gp') = 54
        THEN 'FULLY CONVERTED - upload the code and sign in.'
      ELSE 'PARTIALLY CONVERTED - stop and send me this row.'
    END                                                   AS verdict;
