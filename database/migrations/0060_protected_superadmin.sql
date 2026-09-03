-- Protected "break-glass" administrator account.
--
-- Purpose: guarantee there is always one administrator that cannot be locked out by
-- ordinary in-app administration — no accidental deactivation, no role downgrade, no
-- rename. It exists so a lockout never again requires shell access to recover from.
--
-- WHAT "PROTECTED" MEANS (enforced by UserController):
--   cannot be deactivated          — toggleStatus refuses
--   cannot lose the admin role     — update refuses a role change
--   cannot be renamed              — update refuses a username change
--   CAN still change its password  — deliberately still allowed
--   CAN still edit name/email/etc  — cosmetic fields are unrestricted
--
-- WHY THE PASSWORD MUST STAY CHANGEABLE: an account that can never be disabled AND
-- never have its password changed is an unfixable backdoor. Keeping the password
-- editable means a leaked credential can always be rotated, which is the one control
-- that still works on an account you cannot switch off.
--
-- SECURITY NOTE: this is a permanent, always-enabled administrator. Treat its password
-- like a root password — set a strong one immediately, store it in the vault, and do
-- not use it for day-to-day work. Use a normal admin account for that.
--
-- The flag is a column rather than a hard-coded username so the protection is data, not
-- a magic string, and can be granted to (or removed from) an account later by an
-- operator with database access — which is exactly the level of privilege that should
-- be required to change it.

ALTER TABLE users
    ADD COLUMN is_protected TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

-- Create the account only if that username is free, and only if the admin role exists.
-- The password hash below is bcrypt; the matching temporary password is delivered
-- separately and MUST be changed at first login.
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
