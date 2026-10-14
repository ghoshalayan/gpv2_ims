-- =====================================================================
--  Credential-based sign-in: make usernames unique across ALL businesses
-- =====================================================================
--  The login screen no longer asks which business you are signing in to.
--  The username decides it. For that to be unambiguous, a username can
--  belong to only one business.
--
--  The conversion made usernames unique per business - (tenant_id,
--  username) - which would let two businesses both have an "admin" and
--  leave the sign-in with no way to choose between them. This replaces
--  that with a plain unique index on username.
--
--  Run the two statements BELOW ONE AT A TIME. phpMyAdmin has been
--  running only part of multi-statement scripts in this environment.
--
--  >>> Find & Replace srasinge1_gp if your database is named differently.
-- =====================================================================


-- ---------------------------------------------------------------------
-- STEP 1 - Check for collisions. Run this FIRST, on its own.
--          It must return ZERO ROWS.
--
--          Any row means two businesses share a username. Rename one of
--          them before running step 2, or step 2 will fail.
-- ---------------------------------------------------------------------
SELECT username,
       COUNT(*)                        AS used_by_businesses,
       GROUP_CONCAT(tenant_id ORDER BY tenant_id) AS tenant_ids
  FROM srasinge1_gp.users__data
 GROUP BY username
HAVING COUNT(*) > 1;


-- ---------------------------------------------------------------------
-- STEP 2 - Only once step 1 returned nothing. Run this on its own.
-- ---------------------------------------------------------------------
ALTER TABLE srasinge1_gp.users__data
    DROP INDEX uniq_users_username,
    ADD UNIQUE KEY uniq_users_username (username);


-- ---------------------------------------------------------------------
-- STEP 3 - Confirm. Expect one row: uniq_users_username, column
--          username, seq 1 - and NO row showing tenant_id.
-- ---------------------------------------------------------------------
SELECT index_name, seq_in_index, column_name
  FROM information_schema.statistics
 WHERE table_schema = 'srasinge1_gp'
   AND table_name   = 'users__data'
   AND index_name   = 'uniq_users_username'
 ORDER BY seq_in_index;
