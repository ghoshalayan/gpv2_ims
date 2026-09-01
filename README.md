# GPv2 IMS

Revamped Inventory Management System. Built fresh; legacy reference at [../gpv1_ims/](../gpv1_ims/).

Spec: [../plan.md](../plan.md), [../modules_plan.md](../modules_plan.md), [../pending_tasks.md](../pending_tasks.md).

## Tech

- PHP 8.2+
- MySQL 5.7+ / MariaDB
- Apache (XAMPP) with mod_rewrite
- Hand-rolled router + autoloader (zero Composer dependencies for now)
- Bootstrap 5 + jQuery + DataTables (CDN-loaded)

## First-time setup (XAMPP, Windows)

1. **Copy environment file**
   ```
   cp .env.example .env
   ```
   Edit `.env` and set your MySQL credentials. The DB itself doesn't need to exist yet — the home page works without it.

2. **Add an Apache alias** (one-time)
   Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf` (or `httpd.conf`) and add:
   ```apache
   Alias /gpv2 "C:/gp/gp/gpv2_ims/public"
   <Directory "C:/gp/gp/gpv2_ims/public">
       AllowOverride All
       Require all granted
   </Directory>
   ```
   Make sure `mod_rewrite` is enabled in `httpd.conf` (uncomment `LoadModule rewrite_module modules/mod_rewrite.so`).

   Restart Apache from the XAMPP control panel.

3. **Open the app**
   Visit http://localhost/gpv2 — you should see the "GPv2 IMS booted successfully" page.

## Directory layout

```
gpv2_ims/
├── app/
│   ├── Controllers/        Thin HTTP entry points
│   ├── Services/           Business logic (transactional, testable)
│   ├── Repositories/       SQL only
│   ├── Models/             Plain DTOs
│   ├── Middleware/         CSRF / Auth / RBAC
│   ├── Support/            Db, Csrf, Flash, Env, helpers
│   ├── Views/              PHP templates
│   ├── Router.php          Hand-rolled router
│   └── bootstrap.php       Autoload + env + session
├── config/                 Reserved for typed config files
├── database/
│   ├── migrations/         Numbered SQL migrations (one per change)
│   └── seeds/              Seed data
├── public/                 The ONLY web-accessible folder
│   ├── index.php           Front controller
│   └── .htaccess
├── routes/web.php          Route table
├── storage/logs/           App logs
├── tests/                  Unit + Feature tests
├── .env.example
└── README.md
```

## Conventions (from `../modules_plan.md`)

- Tables: `snake_case_plural`, no `_tbl` suffix (`customers`, `purchase_invoices`)
- PK: always `id`. FK: `<entity>_id`.
- Money: `*_amount`, `DECIMAL(12,2)`. Quantity: `quantity`, `INT`.
- Booleans: `is_*`, `TINYINT(1)`. Status: explicit `ENUM`.
- URLs: kebab-case plural — `/customers`, `/purchase-invoices/{id}/finalize`
- Controllers: `PascalCaseController` with `index/create/store/edit/update/destroy/finalize` actions.
- Always use `e()` for output. Always include `Csrf::field()` in POST forms.

## URL impact note (legacy app)

After the reorganization, the **legacy app's URLs changed** from `localhost/.../<module>/...` to `localhost/<oldroot>/gpv1_ims/<module>/...`.
If anyone has bookmarks or external links to the legacy app, they'll need updating — or add a separate Apache alias for `/gpv1` pointing to `C:/gp/gp/gpv1_ims/`.
