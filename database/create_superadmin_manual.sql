-- =====================================================================
--  CREATE THE PROTECTED SUPERADMIN — via phpMyAdmin, no SSH required
-- =====================================================================
--  This is migration 0060 packaged for a SQL console, plus the row that
--  records it in the `migrations` table so a later migrate.php skips it.
--
--  Creates username 'superadmin' as a permanently-enabled administrator
--  that ordinary in-app administration cannot lock you out of.
--
--  IMPORTANT — the immutability is enforced by APPLICATION CODE, in
--  UserController. Running this SQL alone creates the account and the
--  flag, but the guards only take effect once the updated
--  UserController.php / UserRepository.php / users/index.php are
--  uploaded. Until then the account works, it is simply not yet
--  protected from being edited in the UI.
--
--  >>> CHANGE THE PASSWORD IMMEDIATELY AFTER FIRST LOGIN. <<<
--  Users -> Super Administrator -> set a new password. The temporary one
--  was delivered over chat and must not remain in use.
--
--  Run once. Both statements are written to be safe if re-run.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Add the protection flag. If this errors with "Duplicate column
--    name 'is_protected'", the column already exists -- skip to step 2.
-- ---------------------------------------------------------------------
ALTER TABLE users
    ADD COLUMN is_protected TINYINT(1) NOT NULL DEFAULT 0 AFTER status;


-- ---------------------------------------------------------------------
-- 2. Create the account. Does nothing if 'superadmin' already exists.
-- ---------------------------------------------------------------------
INSERT INTO users (name, phone, username, password_hash, role_id, status, is_protected)
SELECT 'Super Administrator',
       'SA-0000000000',
       'superadmin',
       '$2y$10$GGCgTPjuwX3lnJKIQOZj..adxWNd8yIQPRDNrVvRLLPJq36zUXr46',
       r.id,
       'active',
       1
  FROM roles r
 WHERE r.role_key = 'admin'
   AND NOT EXISTS (SELECT 1 FROM users u WHERE u.username = 'superadmin')
 LIMIT 1;


-- ---------------------------------------------------------------------
-- 3. Record the migration so migrate.php does not try to re-run it.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO migrations (filename) VALUES ('0060_protected_superadmin.sql');


-- ---------------------------------------------------------------------
-- 4. CONFIRM — expect one row: superadmin / admin / active / protected=1
--    and hash_state 'bcrypt OK'.
-- ---------------------------------------------------------------------
SELECT u.username,
       u.name,
       r.role_key,
       u.status,
       u.is_protected,
       IF(LEFT(u.password_hash,4) IN ('$2y$','$2a$','$2b$'),
          'bcrypt OK','NOT A BCRYPT HASH') AS hash_state
  FROM users u
  JOIN roles r ON r.id = u.role_id
 WHERE u.username = 'superadmin';
