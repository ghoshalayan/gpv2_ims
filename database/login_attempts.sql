-- =====================================================================
--  RECENT LOGIN ATTEMPTS — read-only, changes nothing
-- =====================================================================
--  Shows exactly which usernames have been typed and whether each attempt
--  succeeded, so a wrong username or a post-login problem is obvious.
--
--  How AuthController records them:
--    FAILED  -> entity_kind = 'auth', entity_label = the username TYPED
--    SUCCESS -> entity_kind = 'user', entity_label = the account's NAME
--
--  So if you see FAILED rows with a "typed" value that is not an existing
--  username, that is the whole problem - use the exact username from
--  diagnose_login.sql.
--
--  If you see SUCCESS rows but the browser still bounces you back to the
--  login page, authentication is fine and the issue is the session /
--  cookie (see the note at the bottom).
-- =====================================================================

SELECT created_at,
       CASE WHEN entity_kind = 'auth' THEN 'FAILED' ELSE 'SUCCESS' END AS result,
       CASE WHEN entity_kind = 'auth'
            THEN CONCAT('typed username: ', IFNULL(entity_label, '(blank)'))
            ELSE CONCAT('logged in as: ',  IFNULL(entity_label, '?'))
       END                                                             AS detail,
       ip_address,
       notes
  FROM activity_log
 WHERE action = 'login'
 ORDER BY id DESC
 LIMIT 25;

-- Count of failures per typed username (last 200 login events).
SELECT entity_label AS typed_username,
       COUNT(*)     AS failed_attempts,
       MAX(created_at) AS most_recent,
       IF(EXISTS (SELECT 1 FROM users u WHERE u.username = a.entity_label),
          'username EXISTS - so it is the password',
          'NO SUCH USERNAME - this is the problem') AS diagnosis
  FROM activity_log a
 WHERE action = 'login' AND entity_kind = 'auth'
 GROUP BY entity_label
 ORDER BY most_recent DESC;

-- =====================================================================
--  If attempts show SUCCESS but you are still returned to the login page,
--  authentication is NOT the problem. The usual cause on this host is the
--  session cookie: it is marked Secure over HTTPS, so a page served over
--  plain HTTP will drop it and bounce you straight back.
--  See SOP §12 "Login works then immediately logs out" - reach the site
--  as https://ims.srasingerkone.org (not http://, not the raw IP).
-- =====================================================================
