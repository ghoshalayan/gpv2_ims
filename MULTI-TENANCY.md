# Multi-Tenancy — Design

Design for running several independent businesses from one GP ENTERPRISE IMS codebase.
**Not yet implemented.** This document is for approval before any code is written.

| Decision | |
|---|---|
| Model | **One database per tenant**, one shared codebase |
| Tenant resolution | By hostname, against an explicit allowlist |
| Master data | **Separate and independent** per tenant — see §1 |
| Scale assumed | 2–5 tenants on MilesWeb shared hosting |
| Estimated work | ~1 day, plus provisioning per tenant |

**Verified against a copy of the live database (10 Oct 2026):** production runs
**MariaDB 11.4.13** (not MySQL) on **PHP 8.3.33** (the SOP says 8.2 — documentation drift,
harmless but worth correcting). Its schema is **identical** to what `migrate.php` builds from
the 60 migrations: 58 tables each side, same columns, no manual tables and no missing ones.
That matters here — it means a tenant database provisioned by running the migrations will
match production exactly, with no hand-patching. Current data volume is small (3,574 products,
157 customers, 302 sales invoices, 6,351 stock movements), so a database per tenant costs
very little.

---

## 1. The requirement that drives everything

> The same customer, product, supplier or salesman may exist in more than one tenant, as
> **unrelated records**. "Alpha Traders" in tenant A and in tenant B are two different
> businesses' customers, with their own balances and history. Nothing is shared.

Database-per-tenant satisfies this with **no code or schema change at all**: each tenant has
its own `customers`, `products`, `suppliers` and `salesmen` tables in its own database.

The alternative — one shared schema with a `tenant_id` column — fails this requirement
expensively. Measured against the current codebase:

| | Shared schema | **Database per tenant** |
|---|---|---|
| Tables needing a `tenant_id` column | 60 | 0 |
| SQL statements needing a tenant filter | **853** | **0** |
| UNIQUE constraints to rewrite as composite | **37** | 0 |
| Places the database name is chosen | — | **1** |
| Isolation enforced by | a `WHERE` clause, 853 times | **MySQL** |

Those 37 constraints are the crux. `uniq_customers_name`, `uniq_products_name`,
`uniq_sales_invoice_number` and the rest are currently global. Under a shared table, tenant B
could not have a customer named "Alpha Traders" if tenant A already did, and invoice numbers
would collide outright. Per-database, those same constraints become unique *within a tenant*
— which is exactly the desired behaviour, for free.

**The deciding argument is risk, not effort.** One forgotten `WHERE tenant_id = ?` among 853
statements silently shows one business another's financials. Per-database, that class of bug
is impossible to write — isolation is a property of the connection, not of remembering
something. With no automated test suite in the repo, that difference matters a great deal.

---

## 2. Architecture

```
          tenant-a.srasingerkone.org ─┐
          tenant-b.srasingerkone.org ─┼──►  ONE codebase  ──►  Tenant::current()
          tenant-c.srasingerkone.org ─┘     (public/)              │
                                                                   │ hostname → database
                                              ┌────────────────────┼────────────────────┐
                                              ▼                    ▼                    ▼
                                      srasinge1_gpa        srasinge1_gpb        srasinge1_gpc
                                      (full schema)        (full schema)        (full schema)
```

One copy of the code. One MySQL user with rights over each tenant database. Every tenant
database holds the complete, identical schema — the same 60 migrations.

### 2.1 The tenant registry

An explicit map in `config/tenants.php`. No central database is needed at this scale.

```php
return [
    'gp.srasingerkone.org'    => ['key' => 'gp',    'db' => 'srasinge1_gp',    'label' => 'GP Enterprise'],
    'acme.srasingerkone.org'  => ['key' => 'acme',  'db' => 'srasinge1_acme',  'label' => 'Acme Traders'],
];
```

**The database name is looked up, never derived from the hostname.** Deriving it by string
manipulation would let a crafted `Host` header reach an arbitrary database. An unknown host
must fail closed with a 404 — never fall back to a default tenant.

### 2.2 Resolving the tenant

A new `App\Support\Tenant`:

```php
Tenant::current();      // ['key'=>…, 'db'=>…, 'label'=>…]  — resolved from HTTP_HOST
Tenant::key();          // 'gp'
Tenant::database();     // 'srasinge1_gp'
Tenant::useKey('gp');   // explicit selection, for CLI scripts that have no HTTP_HOST
```

Resolution order: an explicitly selected key (CLI) → `HTTP_HOST` lookup → **hard failure**.

### 2.3 The one code change that matters

`Db::pdo()` currently builds its DSN from `env('DB_NAME')` — and that is the **only** place
in the application where a database name appears. It becomes:

```php
env('DB_NAME')  →  Tenant::database()
```

`DB_HOST`, `DB_USER` and `DB_PASSWORD` stay in `.env` and are shared. `DB_NAME` stops being
used by the app (keep it for the CLI default).

Nothing else changes, because **no SQL in the codebase hard-codes a schema name** — every
one of the 853 statements is schema-relative and automatically follows whichever database the
connection points at.

---

## 3. Session isolation — the one real security trap

Two layers, and the second is easy to miss.

**Layer 1 — the cookie. Already correct.** `bootstrap.php` calls
`session_set_cookie_params()` without a `domain`, so PHP issues a **host-only** cookie:
tenant A's cookie is never sent to tenant B's subdomain. This must stay that way — setting a
cookie domain of `.srasingerkone.org` would silently break tenant isolation.

**Layer 2 — the session store. Needs fixing.** PHP session files live in one shared directory
on the server. A session created on tenant A exists in that same store, so a session ID
copied onto tenant B's hostname would load tenant A's authenticated user — a cross-tenant
authentication bypass.

The fix is to stamp the tenant into the session and verify it on every request:

```php
// in bootstrap.php, immediately after session_start()
if (!isset($_SESSION['_tenant'])) {
    $_SESSION['_tenant'] = Tenant::key();
} elseif ($_SESSION['_tenant'] !== Tenant::key()) {
    // Session belongs to a different tenant — discard it rather than trust it.
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['_tenant'] = Tenant::key();
}
```

Also give each tenant its own session name (`gpv2_session_<key>`), so the two never contend
in the browser.

**Users are per-tenant.** The `users` table lives in each tenant database, so an account in
one tenant does not exist in another. `superadmin` in tenant A is a different account from
`superadmin` in tenant B, with its own password. There is no cross-tenant login.

---

## 4. Operations

### 4.1 Migrations

`migrate.php` currently runs against whatever `.env` points at. It gains a tenant argument:

```bash
php database/migrate.php --tenant=gp     # one tenant
php database/migrate.php --all-tenants   # every tenant in config/tenants.php
```

`--all-tenants` must report per tenant and **stop on the first failure**, so a half-migrated
estate is visible rather than silent. The same applies to `seed_permissions.php` and
`create_admin.php`.

> **This is the weak point while SSH is unavailable.** Provisioning a tenant and applying
> future releases both need migrations run against each database. Without a shell that is a
> manual SQL paste per tenant. Workable for 2–5; it is the main reason to get SSH restored.

### 4.2 Backups

The current single `mysqldump` becomes one per tenant. **Each tenant's dump is that
business's entire records** — they should be stored and handled separately, not merged.

### 4.3 Logs

`storage/logs/app.log` is shared. Log lines should be prefixed with the tenant key so an
error can be traced to a business. Low priority, but cheap to do at the same time.

---

## 5. Provisioning a new tenant

1. **Panel:** create the MySQL database (e.g. `srasinge1_acme`); grant the existing app user
   full rights on it.
2. **Panel:** create the subdomain (`acme.srasingerkone.org`) with its document root pointing
   at the **same** `ims.srasingerkone.org/public` directory. Issue SSL for it.
3. **Code:** add the hostname → database row to `config/tenants.php`.
4. **Schema:** `php database/migrate.php --tenant=acme` (or paste the migrations in
   phpMyAdmin against the new database while SSH is unavailable).
5. **Admin:** `php database/create_admin.php --tenant=acme …`, or the superadmin SQL script
   pointed at that database.
6. **Verify:** log in on the new subdomain, run **System → Integrity Check**, and confirm the
   business profile and settings are that tenant's own.

> **Check your MilesWeb plan's MySQL database limit before committing to this.** If the plan
> caps databases below your tenant count, the architecture is still right but the hosting is
> not — that is a VPS conversation, which would also restore SSH.

---

## 6. What does not change

Worth stating plainly, because it is the whole point of choosing this model:

- All **853 SQL statements** — untouched.
- All **37 UNIQUE constraints** — untouched, and now correctly scoped per tenant.
- All **59 repositories, 28 services, 49 controllers** — untouched.
- `business_profile` stays a singleton (`id = 1`) — correctly, since there is one business
  per database.
- The **12 integrity invariants** keep working per tenant with no change; each tenant's books
  are checked against that tenant's own data.
- The static caches (`Settings::$cache`, `Permissions::$allowed`) need no tenant awareness —
  PHP is shared-nothing, so they are rebuilt per request from the connected tenant database.

---

## 7. Risks and limits

| Risk | Mitigation |
|---|---|
| **Cross-tenant session reuse** | Tenant stamp in the session, §3. The most important item here. |
| Hostname spoofing via `Host` header | Explicit allowlist lookup; unknown host → 404, never a default tenant |
| Cookie domain widened later | Keep the cookie host-only; call it out in the deploy checklist |
| A release migrated to some tenants but not all | `--all-tenants` stops on first failure and reports per tenant |
| Database quota on shared hosting | Confirm the plan limit before adding tenants |
| Backups multiply | One dump per tenant, stored separately |
| **No cross-tenant reporting** | Accepted. Separate databases cannot be joined. If a combined view is ever needed it is a separate reporting job, not a schema change. |

---

## 8. Sequencing

**Do not start this until the current release is finished.** Outstanding right now:

1. The 58-file release bundle is not yet uploaded.
2. The stock-ledger drift on products #9 and #10 is not yet repaired.
3. The post-deploy verification in `DEPLOYMENT.md` §6 has not been run.

Debugging a half-deployed single-tenant system is far easier than debugging a half-deployed
multi-tenant one. Finish, verify, then convert.

When ready, the order is: implement `Tenant` + the `Db::pdo()` change + the session guard on
the existing single tenant first (it should behave identically, which is the proof the change
is safe) — then provision the second tenant.
