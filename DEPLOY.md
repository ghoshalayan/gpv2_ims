# Deploying GP ENTERPRISE to MilesWeb (cPanel)

Production deployment guide for the PHP 8.2 IMS on MilesWeb shared hosting.

## What you're deploying

- **No build step, no Composer.** Hand-rolled MVC, zero external dependencies.
  Deployment = upload files + create `.env` + run two CLI scripts. There is no
  `composer install` / `npm build`.
- **`public/` is the web root.** Only `public/index.php` and `public/.htaccess`
  should be web-accessible. `app/`, `routes/`, `database/`, `storage/`, and
  `.env` MUST sit outside any web root so they can't be downloaded.
- **Paths are portable.** `index.php` uses relative `__DIR__` requires and the
  base URL is auto-detected, so the app adapts to whatever folder/subdomain it
  runs from — no path edits needed when you use the layout below.

## Prerequisites on the host

| Requirement | Where in cPanel | Notes |
|---|---|---|
| PHP **8.2** | Software → *Select PHP Version* | Code uses enums + promoted constructors (needs 8.1+). |
| Extensions: `pdo_mysql`, `mbstring`, `json`, `session`, `openssl` | Software → *Select PHP Version* → Extensions | All standard; usually on by default. |
| MySQL 5.7+ / MariaDB 10+ | Databases → *MySQL® Databases* | Schema is plain InnoDB; no exotic features. |
| SSH / Terminal | Advanced → *Terminal* (or your `.ssh`) | Needed once, to run migrations + create admin. |

---

## Recommended layout (app code above web root)

Serve the app from a **subdomain** whose document root points straight at
`public/`. cPanel lets you set the document root freely for a subdomain, so the
rest of the code stays outside the web root automatically.

```
/home/<cpaneluser>/
└── gpv2_ims/                  ← upload the WHOLE project here
    ├── app/  routes/  database/  storage/  config/
    ├── .env                   ← secrets — physically outside the web root
    └── public/                ← subdomain document root points HERE
        ├── index.php
        └── .htaccess
```

> **Using the primary domain instead?** cPanel locks the primary domain's
> document root to `public_html`. Two options:
> 1. *(simplest)* make a subdomain like `ims.srasingerkone.org` as above; or
> 2. upload `app/ routes/ database/ storage/ config/ .env` to
>    `/home/<user>/gpv2_ims/` and copy the **contents of `public/`** into
>    `public_html/`, then edit the three `require` paths in
>    `public_html/index.php` (`/../app/...` → `/../gpv2_ims/app/...`, same for
>    `routes/web.php` and the 500 view). The subdomain route is less fiddly.

---

## Step-by-step

### 1. Set PHP 8.2
Software → **Select PHP Version** → choose **8.2** for the account/domain.

### 2. Create the subdomain
Domains → **Domains / Subdomains** → Create `ims.srasingerkone.org`.
Set its **Document Root** to:
```
/home/<cpaneluser>/gpv2_ims/public
```
(Replace `<cpaneluser>` with your real account name.)

### 3. Create the database + user
Databases → **MySQL® Databases**:
- Create DB → e.g. `gpv2ims` → it becomes `<cpaneluser>_gpv2ims`.
- Create User → e.g. `gpv2` → it becomes `<cpaneluser>_gpv2` → strong password.
- **Add User To Database** → grant **ALL PRIVILEGES**.
- Note the final prefixed names + password for the `.env`.

### 4. Upload the project
Files → **File Manager** (or `scp`/git over SSH):
- Upload to `/home/<cpaneluser>/gpv2_ims/`.
- Do **not** upload `.git/`, `node_modules/`, `tests/` (optional), or your local
  dev `.env`.
- If you uploaded a zip, extract it in place.

### 5. Create the production `.env`
Copy `.env.production.example` to `/home/<cpaneluser>/gpv2_ims/.env` and fill in
the DB names from step 3:
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ims.srasingerkone.org
DB_HOST=localhost
DB_NAME=<cpaneluser>_gpv2ims
DB_USER=<cpaneluser>_gpv2
DB_PASSWORD=...
```
`APP_DEBUG=false` turns off `display_errors`, so no stack traces leak.

### 6. Permissions
- Directories `755`, files `644`.
- `storage/logs/` must be writable by PHP → `755` (or `775` if the log doesn't
  appear).
- `.env` → `600`.

### 7. Run migrations + create the admin (SSH/Terminal)
```bash
cd ~/gpv2_ims
php database/migrate.php
php database/create_admin.php --username=admin --password='STRONG_PASS' --name="Administrator" --email=you@domain.com
```
`migrate.php` applies all migrations and is safe to re-run (already-applied ones
are skipped). `create_admin.php` is idempotent.

**If the default `php` CLI is the wrong version** (you'll see syntax errors), use
the versioned binary — on cPanel/EasyApache that's:
```bash
/opt/cpanel/ea-php82/root/usr/bin/php database/migrate.php
/opt/cpanel/ea-php82/root/usr/bin/php database/create_admin.php --username=admin --password='STRONG_PASS'
```

### 8. Enable HTTPS
SSL/TLS Status → run **AutoSSL** for the subdomain (free Let's Encrypt). Then
force HTTPS — either cPanel's "Force HTTPS Redirect" toggle on the domain, or add
this to the top of `public/.htaccess` (just after `RewriteEngine On`):
```apache
# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```
The app already marks the session cookie `Secure` automatically once the request
is served over HTTPS (see `app/bootstrap.php`).

### 9. Verify
1. Visit `https://ims.srasingerkone.org` → login page renders.
2. Log in with the admin from step 7 → dashboard + sidebar load.
3. Smoke-test: create a Unit, a Product, and a draft Purchase Invoice.
4. Confirm `app/` is **not** web-reachable: `https://ims.srasingerkone.org/../app/bootstrap.php`
   and `.../.env` should 403/404, not download.

---

## Updating later (re-deploy)

1. **Back up first** — Databases → phpMyAdmin → Export, and zip `gpv2_ims/`
   (you're already taking `public_html` zips — keep doing that).
2. Upload changed files (overwrite). `.env` is untouched (it's git-ignored).
3. If there are new migrations: `php database/migrate.php` again — only the new
   ones apply.

---

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| **500 on every page** | DB credentials wrong in `.env`, or `storage/logs` not writable. Temporarily set `APP_DEBUG=true` to see the trace, then revert. |
| **"Access denied for user…"** | `.env` `DB_USER`/`DB_PASSWORD`/`DB_NAME` don't match the prefixed cPanel names. Re-check step 3. |
| **404 on every URL except `/index.php`** | `mod_rewrite` / `.htaccess` not active. Confirm `public/.htaccess` uploaded and the subdomain docroot is `…/public`. |
| **Login works then immediately logs out** | Site served over HTTP while cookie is `Secure`. Finish step 8 (HTTPS) — the cookie is HTTPS-only by design in production. |
| **Migrations: syntax error / unexpected token** | CLI `php` is an older version. Use the `/opt/cpanel/ea-php82/...` binary (step 7). |
| **`app/` files download as text** | Document root is pointing at `gpv2_ims/` instead of `gpv2_ims/public/`. Fix the subdomain docroot. |
| **Blank page, no error** | `APP_DEBUG=false` + a fatal. Check `storage/logs/app.log`, or flip debug on briefly. |
