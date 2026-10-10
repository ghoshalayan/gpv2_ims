-- Rename the tenant-level protected account from 'superadmin' to 'owner'.
--
-- WHY: migration 0060 created a protected break-glass admin called 'superadmin'
-- inside the (then single) database. Under multi-tenancy that migration runs for
-- EVERY tenant, so the name ends up meaning three different things at once:
--
--   control database  -> superadmin  : sits ABOVE every tenant, manages them
--   tenant A database -> superadmin  : a protected admin of tenant A only
--   tenant B database -> superadmin  : a protected admin of tenant B only
--
-- Three unrelated accounts sharing one username is a support problem waiting to
-- happen: signing in to a business with the control-plane password fails in a way
-- that looks like a broken password rather than the wrong account.
--
-- The requirement is that tenant admins sit UNDER the superadmin, so the name
-- 'superadmin' is reserved for the control plane and the per-tenant account is
-- renamed to 'owner' — which is what it actually is: the business's own
-- protected administrator.
--
-- WHAT DOES NOT CHANGE: the account keeps its id, its password, its admin role
-- and its protection (cannot be deactivated, renamed through the UI, or have its
-- role changed; its password can still be rotated). Only the username changes.
--
-- SAFE AND IDEMPOTENT: renames only a protected account still called
-- 'superadmin', and only when the name 'owner' is free in that database. Running
-- it twice does nothing the second time.

UPDATE users
   SET username = 'owner'
 WHERE username = 'superadmin'
   AND is_protected = 1
   AND NOT EXISTS (SELECT 1 FROM (SELECT username FROM users) AS u WHERE u.username = 'owner');
