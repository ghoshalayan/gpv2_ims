# SOP — Deploying & Operating GP ENTERPRISE IMS on MilesWeb

**Standard Operating Procedure** for installing, updating, and maintaining the
GP ENTERPRISE Inventory Management System on MilesWeb shared hosting.

> MilesWeb on this account uses a **non-cPanel control panel** + **SSH** (via
> PuTTY). All server-side work is done over SSH; the web panel is only used for
> PHP version, the subdomain, the MySQL database, and SSL.

> **⚙️ Before first use, fill these in** (the only values not already in this doc;
> keep passwords out of this file — store them in the password vault):
> - **SSH host/IP + port** for PuTTY → §4
> - **MySQL `DB_NAME` / `DB_USER`** (the prefixed names you created) → §1, §7, §8
> - **HTTPS status** for `ims.srasingerkone.org` (issued + forced?) → §11

---

## 1. Architecture at a glance

| Item | Value |
|---|---|
| App | GP ENTERPRISE IMS — PHP 8.2, hand-rolled MVC, **no Composer / no build step** |
| Live URL | `https://ims.srasingerkone.org` |
| Subdomain document root | `~/ims.srasingerkone.org/public` |
| App/project root | `~/ims.srasingerkone.org` (holds `app/ routes/ database/ storage/ .env`) |
| Home directory | `/var/www/9ab1565c-6e90-484b-a8ba-ef24fdb33a19` |
| SSH user | `srasinge1` |
| Server hostname (shell prompt) | `zenith` |
| PHP | 8.2 (set in panel) |
| Database | MySQL/MariaDB — `‹DB_NAME›` / user `‹DB_USER›` |
| Primary domain (DO NOT TOUCH) | `srasingerkone.org` → `~/public_html` — a **separate existing app** |

**Golden rule:** `~/public_html` is another live website. The IMS lives **only**
under `~/ims.srasingerkone.org`. Never modify `public_html` when working on the IMS.

---

## 2. Canonical server layout

```
/var/www/9ab1565c-.../                 ← account home ($HOME)
├── public_html/                       ← PRIMARY DOMAIN — separate app, leave alone
└── ims.srasingerkone.org/             ← IMS PROJECT ROOT (not web-served)
    ├── app/        routes/  database/  config/  storage/
    ├── .env                           ← secrets (chmod 600) — ABOVE the web root
    ├── .env.production.example
    └── public/                        ← SUBDOMAIN DOCUMENT ROOT (web-served)
        ├── index.php
        └── .htaccess
```

**Why `public/` is the docroot:** only `index.php` + `.htaccess` are web-reachable;
`app/`, `database/`, and `.env` sit one level above the web root and cannot be
downloaded. This is the security boundary — never point the docroot at the project
root.

---

## 3. Access & credentials

Store all of these in a **password manager** (e.g. Bitwarden/1Password) — **not** in
plaintext files in the project. (The local `adminCred.md` / `sshCred.md` files were
convenient during setup; move their contents into the vault and delete the plaintext.)

| Credential | Used for | Stored in |
|---|---|---|
| MilesWeb panel login | PHP version, subdomain, DB, SSL | vault |
| SSH (PuTTY): host/IP, port, user `srasinge1`, password or key | server shell | vault |
| MySQL: `‹DB_NAME›` / `‹DB_USER›` / password | app `.env` | vault + server `.env` |
| App admin login (`admin` / …) | logging into the IMS | vault |

---

## 4. Connecting via PuTTY (SSH)

1. Open **PuTTY**.
2. **Host Name:** `‹server host or IP from MilesWeb panel›`  **Port:** `‹22 or custom›`
3. Connection type: **SSH** → **Open**.
4. Log in as `srasinge1` with the SSH password (or load the saved key).
5. You land in `$HOME`. Go to the app:
   ```bash
   cd ~/ims.srasingerkone.org
   ```

> Tip: save the session in PuTTY as "MilesWeb-IMS" so you don't re-enter the host each time.

---

## 5. First-time deployment (reference — already done once)

### 5.1 Panel tasks (one-time)
1. **PHP version** → set the account/subdomain to **PHP 8.2**.
2. **Subdomain** → create `ims.srasingerkone.org`.
3. **Document Root** → set to `ims.srasingerkone.org/public`.
4. **MySQL** → create database `‹DB_NAME›` + user `‹DB_USER›`, grant **ALL PRIVILEGES**, note the password.
5. **SSL** → issue/AutoSSL for `ims.srasingerkone.org`, then force HTTPS.

### 5.2 Upload the code
- Upload the project so it lands at `~/ims.srasingerkone.org/` with `public/` inside it
  (i.e. `~/ims.srasingerkone.org/public/index.php` must exist).
- **Do not** upload: `.git/`, `node_modules/`, `tests/` (optional), or your local dev `.env`.
- If files end up nested in an extra wrapper folder, flatten them:
  ```bash
  cd ~/ims.srasingerkone.org
  shopt -s dotglob && mv <wrapper>/* ./ && shopt -u dotglob
  rmdir <wrapper>
  ```

### 5.3 Configure + install (SSH)
```bash
cd ~/ims.srasingerkone.org
cp .env.production.example .env
nano .env          # set DB_NAME/DB_USER/DB_PASSWORD, APP_URL=https://ims.srasingerkone.org,
                   # APP_ENV=production, APP_DEBUG=false
chmod 600 .env
chmod -R 755 storage

php -v                         # confirm 8.1+
php database/migrate.php       # apply all migrations
php database/create_admin.php --username=admin --password='‹admin password — from vault›' --email=gpsuman@domain.com
```

### 5.4 Verify
1. `https://ims.srasingerkone.org` → login page loads.
2. Log in as admin → dashboard + sidebar render.
3. Confirm source is not exposed: visiting `https://ims.srasingerkone.org/../.env` or
   `/app/bootstrap.php` must **not** download anything (403/404).

---

## 6. Deploying an update (routine)

> Use this every time you ship code changes.

```bash
# 0. ALWAYS back up first (see §8)

# 1. Upload changed files into ~/ims.srasingerkone.org (overwrite).
#    .env is NOT overwritten (it's git-ignored and host-specific).

# 2. Apply any new migrations (safe to run repeatedly — applied ones are skipped):
cd ~/ims.srasingerkone.org
php database/migrate.php

# 3. Smoke-test the live URL: log in, open a couple of pages.
```

**Notes**
- No Composer/npm step — updates are pure file upload + (optional) migrations.
- If you added a data back-fill script (like `database/backfill_*.php`), run it once after `migrate.php`.
- Hard-refresh the browser (Ctrl-F5) if CSS/JS looks stale.

---

## 7. Database operations

**Run migrations**
```bash
cd ~/ims.srasingerkone.org && php database/migrate.php
```

**Create the first admin** (idempotent — skips if the user exists)
```bash
php database/create_admin.php --username=admin --password='‹admin password — from vault›' --email=gpsuman@domain.com
```

**Reset a locked-out admin password** (when you can't log in to use the UI)
```bash
cd ~/ims.srasingerkone.org
php -r 'require "app/bootstrap.php";
        $h = password_hash("‹NEWPASS›", PASSWORD_BCRYPT);
        App\Support\Db::pdo()->prepare("UPDATE users SET password_hash=? WHERE username=?")
            ->execute([$h, "admin"]);
        echo "password reset\n";'
```

**In-app hard reset** (wipes all data, re-seeds defaults + roles + one admin) —
Settings → Danger Zone → Hard Reset (requires admin password). Use only intentionally;
**take a backup first**.

---

## 8. Backups

**All business data lives in MySQL.** The app stores nothing important on disk —
no file uploads, no generated files (the only disk write is a CSV streamed to the
browser). The code itself lives in the local repo and is reproducible. So:

### 8.1 Database — CRITICAL, before every change
This is the only backup that protects irreplaceable data.
```bash
mkdir -p ~/backups   # once
mysqldump -u '‹DB_USER›' -p '‹DB_NAME›' > ~/backups/ims_db_$(date +%F_%H%M).sql
```
*(If a `~/.my.cnf` holds the creds, drop the `-p`.)* Download dumps off-server
periodically (PuTTY `pscp` or the panel file manager) and keep at least the last 3.

### 8.2 Files — OPTIONAL, for faster recovery only
Skipping this loses nothing irreplaceable (code = local repo, `.env` = recreatable
from `.env.production.example` + vault, `storage/` = just logs). Its only benefit is
restoring a zip instead of re-uploading + re-doing the docroot/layout setup.
```bash
zip -r ~/backups/ims_files_$(date +%F_%H%M).zip ~/ims.srasingerkone.org -x '*/storage/logs/*'
```
The one server-only file worth preserving is `.env` — copy it somewhere safe after
any change to its values.

---

## 9. Rollback

1. Restore files: unzip the last good `ims_files_*.zip` over `~/ims.srasingerkone.org`.
2. Restore DB:
   ```bash
   mysql -u '‹DB_USER›' -p '‹DB_NAME›' < ~/backups/ims_db_‹timestamp›.sql
   ```
3. Re-test the live URL.

> Migrations are forward-only — rolling back schema means restoring the DB dump from
> before the migration ran. This is why §8 (backup first) is mandatory.

---

## 10. Logs & health

- App log: `~/ims.srasingerkone.org/storage/logs/app.log`
  ```bash
  tail -n 100 ~/ims.srasingerkone.org/storage/logs/app.log
  ```
- To see a fatal error temporarily, set `APP_DEBUG=true` in `.env`, reproduce, then
  **set it back to `false`** (never leave debug on in production).
- In-app audit trail: **Activity Log** (admin menu) — every create/update/delete/void.
- In-app integrity check: **System → Integrity Check** — verifies balance invariants.

---

## 11. Security checklist (verify after each deploy)

- [ ] `.env` is `chmod 600` and lives **above** the web root (`~/ims.srasingerkone.org/.env`).
- [ ] `APP_DEBUG=false` and `APP_ENV=production` in `.env`.
- [ ] Document root is `…/ims.srasingerkone.org/public` (not the project root).
- [ ] HTTPS works and is forced; login succeeds (session cookie is auto-`Secure` on HTTPS).
- [ ] Visiting `/.env` or `/app/...` over the web returns 403/404, not a download.
- [ ] Default admin password (`admin123` or the setup one) has been changed.
- [ ] Plaintext credential files removed from the project; secrets in the vault.

---

## 12. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| **500 on every page** | DB creds wrong in `.env`, or `storage/logs` not writable. Temporarily `APP_DEBUG=true`, check `storage/logs/app.log`, then revert. |
| **"Access denied for user…"** | `.env` `DB_NAME/DB_USER/DB_PASSWORD` don't match the panel DB. Re-check. |
| **404 on every URL except `/index.php`** | `.htaccess`/rewrite not active, or docroot not at `…/public`. |
| **Login works then immediately logs out** | Served over HTTP while cookie is `Secure`. Finish/repair HTTPS. |
| **Migration: syntax error / unexpected token** | CLI `php` is an older version. Find the 8.2 binary: `which php8.2` or `ls /usr/local/lsws/lsphp82/bin/php` / `ls /opt/*/php*/bin/php`, and call it explicitly. |
| **Source files download as text** | Docroot points at the project root instead of `…/public`. Repoint it. |
| **Blank page, no error** | Fatal with debug off. Check `storage/logs/app.log`. |

---

## 13. Host-specific notes (MilesWeb, this account)

- **No cPanel** — the panel is MilesWeb's own. Subdomain, PHP version, MySQL, and SSL
  are done in that panel; everything else over SSH.
- **Home is `/var/www/<uuid>/`**, not `/home/<user>/` — so any guide that says
  `/home/...` should be read as `~` (your `$HOME`).
- **No Composer on the box is fine** — the app has zero PHP dependencies.
- **PHP CLI vs web PHP** can differ; if `php` on the shell is the wrong version, locate
  the 8.2 binary (see troubleshooting) and use its full path for `migrate.php` /
  `create_admin.php`.
- **Two apps share this account** (`public_html` = other site, `ims.srasingerkone.org` =
  IMS). They use **separate databases** — never point the IMS `.env` at the other app's DB.

---

## 14. Quick command reference

```bash
# connect, then:
cd ~/ims.srasingerkone.org

php database/migrate.php                       # apply migrations
php database/create_admin.php --username=admin --password='‹from vault›' --email=gpsuman@domain.com
tail -n 100 storage/logs/app.log               # view recent errors
mysqldump -u '‹DB_USER›' -p '‹DB_NAME›' > ~/backups/ims_db_$(date +%F_%H%M).sql   # backup DB
zip -r ~/backups/ims_files_$(date +%F_%H%M).zip ~/ims.srasingerkone.org -x '*/storage/logs/*'  # backup files
```
