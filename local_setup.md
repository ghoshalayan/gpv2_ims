# Local Development Setup (Windows + XAMPP)

End-to-end setup for running **GPv2 IMS** on a fresh Windows + XAMPP machine. Takes ~5 minutes.

## Prerequisites

| Requirement | Tested with | Notes |
|---|---|---|
| OS | Windows 10/11 | |
| XAMPP | 8.2.x | bundles Apache + MySQL/MariaDB + PHP 8.2 |
| PHP | 8.2+ | comes with XAMPP at `C:\xampp\php\php.exe` |
| MySQL | 5.7+ / MariaDB 10+ | comes with XAMPP |
| Apache modules | `mod_rewrite` enabled | enabled by default in XAMPP |

Verify XAMPP install:
```
c:\xampp\php\php.exe -v
c:\xampp\mysql\bin\mysql.exe --version
```

## Project layout on disk

```
c:\gp\gp\
├── gpv1_ims\         legacy code (reference only)
├── gpv2_ims\         the new app — this guide
├── plan.md
├── modules_plan.md
└── pending_tasks.md
```

---

## Step 1 — Create the empty database

Open **http://localhost/phpmyadmin**:
1. Click **New** in the left sidebar.
2. Database name: `gpv2_ims`
3. Collation: `utf8mb4_unicode_ci`
4. Click **Create**.

Or via CLI:
```
c:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE gpv2_ims CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

The database stays empty for now — modules add their own tables via migrations.

## Step 2 — Configure `.env`

Copy the template:
```
copy c:\gp\gp\gpv2_ims\.env.example c:\gp\gp\gpv2_ims\.env
```

Open `c:\gp\gp\gpv2_ims\.env` and verify these values:

```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=gpv2_ims
DB_USER=root
DB_PASSWORD=
```

**About `DB_PASSWORD`:**
- Default XAMPP installs have **no root password** — leave the value empty (nothing after `=`, no quotes, no spaces).
- If you set a MySQL root password during install, put it on this line instead.
- Whatever credentials work in **phpMyAdmin** are what go here.

`.env` is git-ignored (see `.gitignore`). Never commit it.

## Step 3 — Configure Apache alias

Edit `C:\xampp\apache\conf\extra\httpd-vhosts.conf` (create the file if it doesn't exist; it's loaded by `httpd.conf` via `Include conf/extra/httpd-vhosts.conf`) and add:

```apache
Alias /gpv2 "C:/gp/gp/gpv2_ims/public"
<Directory "C:/gp/gp/gpv2_ims/public">
    AllowOverride All
    Require all granted
</Directory>
```

Forward slashes in the path are intentional and Apache-correct.

Verify `mod_rewrite` is enabled in `C:\xampp\apache\conf\httpd.conf` — search for the line and ensure it is **not** commented out:
```
LoadModule rewrite_module modules/mod_rewrite.so
```

Restart Apache from the **XAMPP Control Panel** (Apache → Stop → Start).

## Step 4 — Verify

Open **http://localhost/gpv2** in your browser. You should see:

- Heading: **"GPv2 IMS booted successfully"**
- App env: `local`
- PHP version: `8.2.x`
- **Database: Connected — 0 table(s)** (green badge)
- Session ID and CSRF token printed

If you see all of the above, the foundation is working.

## Step 5 — Run migrations

Apply all pending SQL migrations from `database/migrations/`:

```
cd c:\gp\gp\gpv2_ims
c:\xampp\php\php.exe database\migrate.php
```

Expected output on a fresh DB:
```
Applying 0001_init_users.sql ... OK
Done. 1 applied.
```

Re-running the script is safe — already-applied migrations are skipped:
```
Nothing to migrate. (1 already applied)
```

The runner tracks applied migrations in a `migrations` table it auto-creates on first run.

## Step 6 — Bootstrap the first admin user

Create the initial admin account:

```
c:\xampp\php\php.exe database\create_admin.php
```

Expected output:
```
Admin user created.
  username: admin
  password: admin123

*** SECURITY: change this password immediately after first login. ***
```

The script is idempotent — running it again reports "User 'admin' already exists. Skipping."

**Custom credentials:** pass `--username`, `--password`, `--name`, `--phone`, `--email`:
```
c:\xampp\php\php.exe database\create_admin.php --username=kalidas --password=MyP@ssw0rd --name="Kalidas Pathak" --phone=9999999999 --email=kalidas@example.com
```

## Step 7 — Verify the login flow

1. Open **http://localhost/gpv2** — boot page should show DB badge with **1 table(s)**.
2. Click **Login** in the navbar (top-right).
3. Sign in with the credentials from Step 6 (default: `admin` / `admin123`).
4. You're redirected to **/dashboard** with a welcome card and your role badge.
5. Click your name in the navbar → **Logout**. Returns to /login with a flash message.
6. Try visiting http://localhost/gpv2/dashboard while logged out — you're redirected to /login with "Please log in to continue."

If all six work, **authentication is fully wired**. You're ready for module #3 (Categories).

---

## Troubleshooting

### "Database: Not reachable — Access denied for user 'root'@'localhost' (using password: YES)"

The `.env` `DB_PASSWORD` doesn't match what MySQL expects.
- For default XAMPP: clear `DB_PASSWORD=` (no value after `=`).
- If you've set a password: put the correct value.
- Test the credentials in phpMyAdmin to confirm.

No Apache restart needed after editing `.env` — it's read on every request.

### "Database: Not reachable — Unknown database 'gpv2_ims'"

The database wasn't created. Run **Step 1** above.

### Browser shows raw PHP code instead of rendered HTML

Apache isn't running PHP. Check XAMPP Control Panel: Apache must be green. Restart it.

### 404 on every URL except `/gpv2/index.php`

`mod_rewrite` isn't active or `AllowOverride All` is missing.
- Check `httpd.conf` — `LoadModule rewrite_module modules/mod_rewrite.so` must not be commented.
- Check the `<Directory>` block in `httpd-vhosts.conf` — `AllowOverride All` is required for `.htaccess` to take effect.
- Restart Apache.

### "Fatal error: Uncaught Error: Call to undefined method ..."

You're on outdated code. Pull latest, restart Apache (PHP doesn't need a restart, but file changes are picked up immediately).

### Port 80 already in use (Apache won't start)

Skype, IIS, or another web server is holding port 80. Either stop the other service, or change Apache's port in `httpd.conf` (`Listen 80` → `Listen 8080`) and access the app at `http://localhost:8080/gpv2`.

---

## Optional — Accessing the legacy app (gpv1)

If you also need the legacy app accessible during the migration period, add a second alias:

```apache
Alias /gpv1 "C:/gp/gp/gpv1_ims"
<Directory "C:/gp/gp/gpv1_ims">
    AllowOverride All
    Require all granted
</Directory>
```

Restart Apache. Legacy app will be at `http://localhost/gpv1/index1.php` (and other entry-point files inside the module folders).

---

## What you should have at the end

- `c:\gp\gp\gpv2_ims\.env` — exists, with working DB credentials
- `gpv2_ims` database — created, empty
- Apache alias for `/gpv2` — added, Apache restarted
- `http://localhost/gpv2` — shows the success page with green DB badge

You are now ready to build modules. Next: **Authentication (module #2)**.
