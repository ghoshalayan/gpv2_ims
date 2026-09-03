-- =====================================================================
--  LOGIN DIAGNOSTIC — read-only, changes nothing
-- =====================================================================
--  Paste into phpMyAdmin -> (IMS database) -> SQL -> Go.
--
--  Login fails for exactly three reasons (AuthService::attempt):
--     1. the username does not exist  (exact match, case-sensitivity
--        depends on the column collation)
--     2. status is not 'active'
--     3. the password does not verify
--
--  This tells you which of 1 and 2 apply. It deliberately does NOT
--  output password_hash — there is no need to move that around, and it
--  should not end up in a chat window or a screenshot.
--
--  NOTE: there is no 'superadmin' role in this system. Roles live in the
--  `roles` table (role_key: admin / subadmin / cashier) and users join to
--  it via users.role_id. The bootstrap account is usually username
--  'admin' with role_key 'admin'.
-- =====================================================================

-- 1. Every account, with the two things that can block a login.
SELECT u.id,
       u.username,
       u.name,
       IFNULL(r.role_key, '(no role assigned)')      AS role_key,
       u.status,
       IF(u.status = 'active', 'can log in',
          'BLOCKED - status is not active')          AS login_state,
       LENGTH(u.password_hash)                       AS hash_len,
       LEFT(u.password_hash, 4)                      AS hash_prefix,
       IF(LEFT(u.password_hash, 4) IN ('$2y$', '$2a$', '$2b$'),
          'bcrypt OK', 'NOT A BCRYPT HASH')          AS hash_state,
       u.last_login_at
  FROM users u
  LEFT JOIN roles r ON r.id = u.role_id
 ORDER BY (IFNULL(r.role_key,'') = 'admin') DESC, u.id;

-- 2. Is there at least one usable admin?
SELECT COUNT(*) AS active_admins,
       IF(COUNT(*) > 0, 'OK',
          'NO ACTIVE ADMIN - see reset_admin_password.sql') AS status
  FROM users u
  JOIN roles r ON r.id = u.role_id
 WHERE r.role_key = 'admin' AND u.status = 'active';

-- 3. Recent failed attempts, if the activity log is recording them.
--    'label' holds the username that was typed — useful for spotting a typo
--    or a username that simply does not exist.
SELECT created_at, action, entity_kind, entity_label AS username_tried, notes
  FROM activity_log
 WHERE action = 'login'
 ORDER BY id DESC
 LIMIT 10;
