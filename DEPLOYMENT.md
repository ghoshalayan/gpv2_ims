# DEPLOYMENT — CR-1 … CR-11 release

Release runbook for the change-request batch built on top of the tree tagged
`prod-baseline`. **This is not a general install guide.** For host mechanics — SSH, panel
tasks, backups, rollback, the security checklist — follow
[SOP-milesweb-deployment.md](SOP-milesweb-deployment.md); `DEPLOY.md` covers the
first-time install only. This document covers only what is specific to *this* release.

| | |
|---|---|
| Target | `https://ims.srasingerkone.org` (MilesWeb, PHP 8.2, no Composer) |
| Baseline tag | `prod-baseline` — the tree currently running in production |
| Change surface | 23 new files, 24 modified, 5 new migrations (git also reports `README.md` as modified — unrelated, see §3) |
| Schema changes | Additive only: new tables + nullable columns. No `DROP`. One `MODIFY` that widens `NOT NULL` to `NULL`. |
| Downtime | None required |
| Est. time | ~20 min including verification |

---

## 1. What is in this release

| CR | Change | Notes |
|---|---|---|
| CR-2, CR-3, CR-11 | **Due List** — customers & salesmen, Commission/Cash/Total, short/long-term tagging, print/PDF, CSV | New report; did not exist before |
| CR-5 | **Passbook print + WhatsApp share** | The passbook itself already existed |
| CR-9 | **Product search** — category, stock state, price band; wider text match | Extends existing filters |
| CR-4 | **Rate Management** — bulk selling-price change, category/subcategory-wise, with preview and revert | New screen |
| CR-1b | **Brand purchase rate slabs** — auto-suggest a rate from the quantity | **Ships OFF**; see §5 |
| CR-6 | **Per-sale commission override** + **Returned Salesman Account** | Touches the sales finalize path |
| CR-10 | **Optional free-text phone** on customers & suppliers | Partial by design; see §7 |

**Already present before this release — no code shipped, nothing to verify beyond a
spot-check:** CR-7 (round-off, complete on all four document types), CR-1a (purchases
already brand-scoped), CR-8 discount handling (both sides).

**Not in this release:** CR-8's *sale-side* rebate — see §7.

---

## 2. Pre-flight

> **The database backup is the only rollback for schema changes. Do not skip it.**

```bash
ssh srasinge1@<host>
cd ~/ims.srasingerkone.org

# 1. DB backup — MANDATORY (SOP §8.1)
mkdir -p ~/backups
mysqldump -u '<DB_USER>' -p '<DB_NAME>' > ~/backups/ims_db_$(date +%F_%H%M).sql

# 2. File backup — optional, but makes rollback a single unzip (SOP §8.2)
zip -r ~/backups/ims_files_$(date +%F_%H%M).zip ~/ims.srasingerkone.org -x '*/storage/logs/*'

# 3. Confirm the backup is non-empty before going further
ls -lh ~/backups/ | tail -3
```

Also confirm the CLI PHP is 8.2 (`php -v`). If it is not, locate the 8.2 binary and use
its full path for every command below — see SOP §12.

---

## 3. Upload

Upload these into `~/ims.srasingerkone.org`, preserving paths. **Do not upload `.env`**
(host-specific), and do not touch `~/public_html` — that is a separate live site (SOP §2).

**New files (23)**

```
app/Controllers/BrandSlabController.php
app/Controllers/DueTermController.php
app/Controllers/RateChangeController.php
app/Repositories/BrandSlabRepository.php
app/Repositories/DueListRepository.php
app/Repositories/RateChangeRepository.php
app/Repositories/ReturnedSalesmanRepository.php
app/Services/RateChangeService.php
app/Services/SlabRateService.php
app/Support/Whatsapp.php
app/Views/brand-slabs/index.php
app/Views/passbook/print/a4.php
app/Views/rate-changes/index.php
app/Views/rate-changes/show.php
app/Views/reports/due-list.php
app/Views/reports/due-list-print.php
app/Views/reports/returned-salesman.php
database/seed_permissions.php
database/migrations/0055_due_term_tagging.sql
database/migrations/0056_init_rate_changes.sql
database/migrations/0057_init_brand_purchase_slabs.sql
database/migrations/0058_sales_line_commission_override.sql
database/migrations/0059_optional_freetext_phone.sql
```

**Modified files (24)**

```
routes/web.php
app/Controllers/CustomerController.php
app/Controllers/PassbookController.php
app/Controllers/ProductController.php
app/Controllers/PurchaseInvoiceController.php
app/Controllers/ReportsController.php
app/Controllers/SalesInvoiceController.php
app/Controllers/SettingsController.php
app/Controllers/SupplierController.php
app/Repositories/CustomerRepository.php
app/Repositories/ProductRepository.php
app/Repositories/SalesInvoiceLineRepository.php
app/Repositories/SupplierRepository.php
app/Services/SalesInvoiceService.php
app/Support/MenuPermissionRegistry.php
app/Views/brands/index.php
app/Views/customers/_form.php
app/Views/partials/sidebar.php
app/Views/passbook/show.php
app/Views/products/index.php
app/Views/purchase-invoices/edit.php
app/Views/sales-invoices/edit.php
app/Views/settings/index.php
app/Views/suppliers/_form.php
```

Both lists are reproducible from the repo:

```bash
git diff prod-baseline --name-only --diff-filter=A   # new
git diff prod-baseline --name-only --diff-filter=M   # modified
```

> `README.md` also appears in the modified list. It is **unrelated to this release** and
> currently holds stray UTF-16 bytes from an earlier commit, which is why git reports it
> as binary. Skip it, or fix the encoding separately — it has no effect on the app.

---

## 4. Apply schema and permissions

```bash
cd ~/ims.srasingerkone.org

# Migrations — idempotent; already-applied files are skipped (SOP §7)
php database/migrate.php

# New permission keys for non-admin roles — ADDITIVE, safe to re-run
php database/seed_permissions.php
```

Expected: `migrate.php` applies exactly **0055 … 0059** and nothing else.

> **Never run `database/reseed_permissions.php` on production.** It DELETEs every row and
> re-seeds from defaults, wiping custom role grants. `seed_permissions.php` only inserts
> the keys a role is missing and never revokes anything.

Four new permission keys are introduced. Admin is a master switch and sees them
immediately; other roles get their defaults from the seeder, and you can adjust them under
**Role Permissions**:

| Key | Abilities | Screen |
|---|---|---|
| `reports_due_list` | view, export, print, update | Reports → Due List (`update` = due-term tagging) |
| `reports_returned_salesman` | view | Reports → Returned Salesman A/c |
| `rate_changes` | view, create, delete | Product Management → Rate Management (`delete` = revert) |
| `brand_slabs` | view, create, update, delete | Brands → Slabs |

### What the migrations do

| Migration | Effect | Reversible? |
|---|---|---|
| `0055_due_term_tagging` | Adds nullable `sales_invoices.due_term` and `customers.default_due_term`, plus one index | Yes — drop 2 columns |
| `0056_init_rate_changes` | Creates `rate_change_batches`, `rate_change_lines` | Yes — drop 2 tables |
| `0057_init_brand_purchase_slabs` | Creates `brand_purchase_slabs`, `product_slab_rates`; seeds `enable_brand_slab_rates` = `'0'` | Yes — drop 2 tables |
| `0058_sales_line_commission_override` | Adds nullable `sales_invoice_lines.commission_rate_override` | Yes — drop 1 column |
| `0059_optional_freetext_phone` | `customers.phone` / `suppliers.phone` become nullable | Yes, **but only while no NULLs have been saved** |

No migration back-fills or rewrites existing rows. Every new column is nullable, and NULL
means "behave exactly as before".

---

## 5. Feature flag — CR-1b ships OFF

`enable_brand_slab_rates` is seeded to `'0'`. Uploading this release changes **nothing**
about purchase entry until you switch it on.

To adopt slab rates:

1. **Brands → (a brand) → Slabs** — define the quantity bands, e.g. `Under 50` (from 0, up
   to 50) and `50 and above` (from 50, up to blank). Ranges are half-open, so adjacent
   bands meet with no gap and no overlap; overlapping definitions are rejected.
2. Fill in the per-product rate grid. A blank cell means "no suggestion at that quantity".
3. **Settings → Slab purchase rates** — switch on.

The slab only **prefills** the price field on a purchase line and names the band it came
from. It never locks the price, never rewrites a saved line, and the purchase finalize math
is untouched. Turning the flag off restores current behaviour exactly and keeps the
configuration.

---

## 6. Verification

Run in order. Stop and roll back (§8) if any step fails.

**A. System health**

1. Log in — dashboard and sidebar render.
2. **System → Integrity Check** — all **12** invariants pass.
3. `tail -n 50 storage/logs/app.log` — no new errors.
4. Hard-refresh (Ctrl-F5) if the sidebar looks stale.

**B. Nothing existing broke** — the important part, since these paths handle money:

5. Create and finalize a **sales invoice**; confirm totals, round-off and commission are
   what you would have expected before this release.
6. Create and finalize a **purchase invoice** (flag still off — the price field must behave
   exactly as it does today).
7. Record a **payment**; confirm the invoice due updates.
8. Re-run **Integrity Check**.

**C. New features**

9. **Reports → Due List**, Customers tab. Cross-check the grand total against **Reports →
   Balance Sheet** receivables (`customer_receivables` + `legacy_customer_recv`). They are
   built from the same base and must agree.
10. Salesmen tab — Commission / Cash / Total. Cross-check one salesman against their
    **Passbook**; the commission figure must match.
11. Tag an invoice short/long-term; confirm it moves between buckets and the total is
    unchanged. Then Print and Export CSV.
12. **Passbook → a customer** — Print/PDF opens; the WhatsApp button appears only when the
    customer has a phone.
13. **Products** — filter by category, stock state and price band.
14. **Rate Management** — preview a small change, check the affected list, apply, then
    **revert** it and confirm prices return.
15. **Reports → Returned Salesman A/c** — figures reconcile with the salesman passbook.
16. **Sales invoice line** — set a per-line Commission %, finalize, confirm the snapshot
    used the override. Leaving it blank must still use the salesman's own rate.
17. **Customers** — save one with a blank phone and one with a free-text phone
    (e.g. `033-2345 6789`). Both must save.

**D. Security** — the SOP §11 checklist, especially `APP_DEBUG=false` and `.env` at
`chmod 600`.

---

## 7. Known limitations shipped deliberately

- **CR-8's sale-side rebate is NOT built.** `brand_rebates` models only rebates the
  business *receives* from a supplier/brand (`supplier_id NOT NULL`). A rebate *given to a
  customer* does not exist. Discount handling is complete on both sides; rebate is
  purchase-side only. Confirm whether this is in scope before treating CR-8 as closed.
- **Phone uniqueness is retained.** CR-10 made phone optional and free-text, but the UNIQUE
  index stays. Dropping it is close to one-way — once duplicates exist they cannot be
  undone without manual cleanup — and `CustomerRepository::findByNameAndPhone()` auto-links
  salesman/employee mirror customers by exact name+phone, which duplicates make ambiguous.
  That linking must be reworked onto an explicit customer choice first.
- **Salesmen, employees and users keep a required, validated phone**, because the
  mirror-link logic above depends on a real number.
- **No automated test suite in the repo.** `tests/Unit` and `tests/Feature` are still
  empty. This release was verified by 218 assertions plus the 12 integrity invariants
  against a local copy of the schema, but those scripts are not committed — so verification
  on the server is the manual checklist in §6.

---

## 8. Rollback

Migrations are forward-only (SOP §9). Choose the lightest option that fixes the problem.

**Tier 1 — a new feature misbehaves.** Nothing to undo: switch `enable_brand_slab_rates`
off (CR-1b), or revoke the relevant permission key so the screen disappears. Existing data
is untouched.

**Tier 2 — a code bug, schema is fine.** Restore the previous files only:

```bash
cd ~
unzip -o backups/ims_files_<timestamp>.zip
```

The new columns and tables simply go unused — each is nullable or standalone, so the old
code runs against the new schema without error.

**Tier 3 — schema or data problem.** Full restore:

```bash
unzip -o backups/ims_files_<timestamp>.zip
mysql -u '<DB_USER>' -p '<DB_NAME>' < ~/backups/ims_db_<timestamp>.sql
```

Then re-test the live URL.

> Note on Tier 2: `0059` widened `customers.phone` / `suppliers.phone` to nullable. Once a
> record has been saved with a blank phone, reverting that column to `NOT NULL` requires
> filling those rows first. Restore the DB dump (Tier 3) if you need the old constraint
> back.

**Manual DDL reversal**, if you would rather not restore the whole dump — take a fresh
backup first:

```sql
ALTER TABLE sales_invoice_lines DROP COLUMN commission_rate_override;   -- 0058
DROP TABLE IF EXISTS product_slab_rates, brand_purchase_slabs;          -- 0057
DROP TABLE IF EXISTS rate_change_lines, rate_change_batches;            -- 0056
ALTER TABLE sales_invoices DROP INDEX idx_si_due_term,
                           DROP COLUMN due_term;                        -- 0055
ALTER TABLE customers      DROP COLUMN default_due_term;                -- 0055
DELETE FROM system_settings WHERE setting_key = 'enable_brand_slab_rates';
DELETE FROM migrations WHERE filename IN (
  '0055_due_term_tagging.sql', '0056_init_rate_changes.sql',
  '0057_init_brand_purchase_slabs.sql', '0058_sales_line_commission_override.sql'
);
```

`0059` is deliberately omitted above — reverse it via the dump, per the note.

---

## 9. Post-deploy

- Tag the deployed commit so the next release has a baseline:
  `git tag prod-$(date +%Y%m%d)` then move `prod-baseline` forward.
- Keep at least the last 3 DB dumps and pull them off-server periodically (SOP §8.1).
- Owner action carried over from SOP §3: move `sshCred.md` and `GPadminCred.md` into the
  password vault and delete the plaintext copies. They sit outside `gpv2_ims/` and are not
  uploaded, but they should not persist on disk.
