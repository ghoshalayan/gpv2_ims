-- =====================================================================
--  ADMIN PASSWORD RESET — via phpMyAdmin, no SSH required
-- =====================================================================
--  SOP §7 resets a locked-out admin over SSH. This does the same thing
--  from the panel, for when SSH is unavailable.
--
--  The hash below is a real bcrypt hash produced by
--      password_hash('<temp password>', PASSWORD_BCRYPT)
--  which is exactly what AuthService::attempt() checks with
--  password_verify(). The temporary password it corresponds to is given
--  to you separately — it is deliberately NOT written in this file.
--
--  >>> CHANGE THE PASSWORD IN THE APP IMMEDIATELY AFTER LOGGING IN. <<<
--  A temporary password that travelled through a chat window must not
--  stay in use. Users -> your account -> set a new password.
--
--  RUN ONLY THE ONE BLOCK YOU NEED. Read §A first to see what is wrong
--  before changing anything.
-- =====================================================================


-- ---------------------------------------------------------------------
-- A. LOOK FIRST — which account, and what is actually blocking it?
--    (diagnose_login.sql has the fuller version of this)
-- ---------------------------------------------------------------------
SELECT u.id, u.username, u.name,
       IFNULL(r.role_key,'(none)') AS role_key,
       u.status
  FROM users u
  LEFT JOIN roles r ON r.id = u.role_id
 ORDER BY (IFNULL(r.role_key,'') = 'admin') DESC, u.id;


-- ---------------------------------------------------------------------
-- B. The account exists and is an admin, but the password is unknown.
--    Replace 'admin' with the username from §A if it differs.
-- ---------------------------------------------------------------------
UPDATE users
   SET password_hash = '$2y$10$kwIoWU61wap4P1P3iyIaTe/Cvj3eXb1lfynsqxhHCkSFrrnMDf7f.'
 WHERE username = 'admin'
 LIMIT 1;
-- Expect: "1 row affected". If it says 0, the username does not match —
-- re-read §A and use the exact value shown there.


-- ---------------------------------------------------------------------
-- C. The admin account exists but is INACTIVE (login_state said BLOCKED).
-- ---------------------------------------------------------------------
-- UPDATE users SET status = 'active' WHERE username = 'admin' LIMIT 1;


-- ---------------------------------------------------------------------
-- D. The account exists but has no role, or the wrong one, so it is not
--    treated as an administrator.
-- ---------------------------------------------------------------------
-- UPDATE users
--    SET role_id = (SELECT id FROM roles WHERE role_key = 'admin')
--  WHERE username = 'admin' LIMIT 1;


-- ---------------------------------------------------------------------
-- E. LAST RESORT — there is no admin account at all (§A returned none,
--    or diagnose_login.sql reported active_admins = 0).
--    Creates a fresh one. Change the username/name/phone if you like;
--    phone must be unique among users.
-- ---------------------------------------------------------------------
-- INSERT INTO users (name, phone, username, password_hash, role_id, status)
-- SELECT 'Recovery Admin', '9999999999', 'recovery',
--        '$2y$10$kwIoWU61wap4P1P3iyIaTe/Cvj3eXb1lfynsqxhHCkSFrrnMDf7f.',
--        r.id, 'active'
--   FROM roles r
--  WHERE r.role_key = 'admin'
--  LIMIT 1;


-- ---------------------------------------------------------------------
-- F. CONFIRM — run after whichever block you used.
--    hash_state must read 'bcrypt OK' and login_state 'can log in'.
-- ---------------------------------------------------------------------
SELECT u.username,
       IFNULL(r.role_key,'(none)')                   AS role_key,
       u.status,
       IF(u.status='active','can log in','BLOCKED')  AS login_state,
       IF(LEFT(u.password_hash,4) IN ('$2y$','$2a$','$2b$'),
          'bcrypt OK','NOT A BCRYPT HASH')           AS hash_state,
       LENGTH(u.password_hash)                       AS hash_len
  FROM users u
  LEFT JOIN roles r ON r.id = u.role_id
 WHERE IFNULL(r.role_key,'') = 'admin';
