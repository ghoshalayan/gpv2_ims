-- =====================================================================
--  CROSS-TENANT INTEGRITY AUDIT
-- =====================================================================
--  Checks that no business can see, or point at, another business's data.
--
--  ONE statement. Paste the whole thing and press Go. It does not care
--  which database phpMyAdmin has selected - every name is written out.
--
--  >>> EXPECT ZERO ROWS. Any row returned is a real problem. <<<
--
--  Find & Replace srasinge1_gp if your database is named differently.
--
--  Generated from the live foreign keys: 82 references between
--  54 tenant-scoped tables.
-- =====================================================================

SELECT 'unstamped row' AS problem, 'activity_log__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`activity_log__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'bank_accounts__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`bank_accounts__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'brand_purchase_slabs__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`brand_purchase_slabs__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'brand_rebate_cost_adjustments__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`brand_rebate_cost_adjustments__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'brand_rebates__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`brand_rebates__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'brands__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`brands__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'business_profile__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`business_profile__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'capital_movements__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`capital_movements__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'cashier_drawers__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`cashier_drawers__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'categories__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`categories__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'customer_advances__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`customer_advances__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'customers__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`customers__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'employees__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`employees__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'inter_account_transfers__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`inter_account_transfers__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'legacy_obligations__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`legacy_obligations__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'legacy_settlements__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`legacy_settlements__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'operating_expense_categories__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`operating_expense_categories__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'operating_expenses__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`operating_expenses__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'product_composition_items__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`product_composition_items__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'product_compositions__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`product_compositions__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'product_price_history__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`product_price_history__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'product_slab_rates__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`product_slab_rates__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'product_units__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`product_units__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'production_order_lines__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`production_order_lines__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'production_orders__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`production_orders__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'products__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`products__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'purchase_invoice_expenses__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`purchase_invoice_expenses__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'purchase_invoice_lines__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`purchase_invoice_lines__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'purchase_invoices__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`purchase_invoices__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'purchase_payments__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`purchase_payments__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'purchase_return_lines__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`purchase_return_lines__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'purchase_returns__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`purchase_returns__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'rate_change_batches__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`rate_change_batches__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'rate_change_lines__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`rate_change_lines__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'recurring_expenses__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`recurring_expenses__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'sales_invoice_extras__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`sales_invoice_extras__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'sales_invoice_line_components__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`sales_invoice_line_components__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'sales_invoice_lines__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`sales_invoice_lines__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'sales_invoices__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`sales_invoices__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'sales_payments__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`sales_payments__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'sales_return_line_components__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`sales_return_line_components__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'sales_return_lines__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`sales_return_lines__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'sales_returns__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`sales_returns__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'salesman_commission_settlements__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`salesman_commission_settlements__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'salesmen__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`salesmen__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'stock_adjustment_lines__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`stock_adjustment_lines__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'stock_adjustments__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`stock_adjustments__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'stock_movements__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`stock_movements__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'subcategories__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`subcategories__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'supplier_advances__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`supplier_advances__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'suppliers__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`suppliers__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'system_settings__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`system_settings__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'user_permission_overrides__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`user_permission_overrides__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'unstamped row' AS problem, 'users__data' AS detail, COUNT(*) AS rows_affected
  FROM `srasinge1_gp`.`users__data` WHERE tenant_id IS NULL OR tenant_id = 0
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'activity_log__data', COUNT(*)
  FROM `srasinge1_gp`.`activity_log__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'bank_accounts__data', COUNT(*)
  FROM `srasinge1_gp`.`bank_accounts__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'brand_purchase_slabs__data', COUNT(*)
  FROM `srasinge1_gp`.`brand_purchase_slabs__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'brand_rebate_cost_adjustments__data', COUNT(*)
  FROM `srasinge1_gp`.`brand_rebate_cost_adjustments__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'brand_rebates__data', COUNT(*)
  FROM `srasinge1_gp`.`brand_rebates__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'brands__data', COUNT(*)
  FROM `srasinge1_gp`.`brands__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'business_profile__data', COUNT(*)
  FROM `srasinge1_gp`.`business_profile__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'capital_movements__data', COUNT(*)
  FROM `srasinge1_gp`.`capital_movements__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'cashier_drawers__data', COUNT(*)
  FROM `srasinge1_gp`.`cashier_drawers__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'categories__data', COUNT(*)
  FROM `srasinge1_gp`.`categories__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'customer_advances__data', COUNT(*)
  FROM `srasinge1_gp`.`customer_advances__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'customers__data', COUNT(*)
  FROM `srasinge1_gp`.`customers__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'employees__data', COUNT(*)
  FROM `srasinge1_gp`.`employees__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'inter_account_transfers__data', COUNT(*)
  FROM `srasinge1_gp`.`inter_account_transfers__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'legacy_obligations__data', COUNT(*)
  FROM `srasinge1_gp`.`legacy_obligations__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'legacy_settlements__data', COUNT(*)
  FROM `srasinge1_gp`.`legacy_settlements__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'operating_expense_categories__data', COUNT(*)
  FROM `srasinge1_gp`.`operating_expense_categories__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'operating_expenses__data', COUNT(*)
  FROM `srasinge1_gp`.`operating_expenses__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'product_composition_items__data', COUNT(*)
  FROM `srasinge1_gp`.`product_composition_items__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'product_compositions__data', COUNT(*)
  FROM `srasinge1_gp`.`product_compositions__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'product_price_history__data', COUNT(*)
  FROM `srasinge1_gp`.`product_price_history__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'product_slab_rates__data', COUNT(*)
  FROM `srasinge1_gp`.`product_slab_rates__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'product_units__data', COUNT(*)
  FROM `srasinge1_gp`.`product_units__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'production_order_lines__data', COUNT(*)
  FROM `srasinge1_gp`.`production_order_lines__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'production_orders__data', COUNT(*)
  FROM `srasinge1_gp`.`production_orders__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'products__data', COUNT(*)
  FROM `srasinge1_gp`.`products__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'purchase_invoice_expenses__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_invoice_expenses__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'purchase_invoice_lines__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_invoice_lines__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'purchase_invoices__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_invoices__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'purchase_payments__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_payments__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'purchase_return_lines__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_return_lines__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'purchase_returns__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_returns__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'rate_change_batches__data', COUNT(*)
  FROM `srasinge1_gp`.`rate_change_batches__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'rate_change_lines__data', COUNT(*)
  FROM `srasinge1_gp`.`rate_change_lines__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'recurring_expenses__data', COUNT(*)
  FROM `srasinge1_gp`.`recurring_expenses__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'sales_invoice_extras__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_invoice_extras__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'sales_invoice_line_components__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_invoice_line_components__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'sales_invoice_lines__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_invoice_lines__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'sales_invoices__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_invoices__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'sales_payments__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_payments__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'sales_return_line_components__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_return_line_components__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'sales_return_lines__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_return_lines__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'sales_returns__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_returns__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'salesman_commission_settlements__data', COUNT(*)
  FROM `srasinge1_gp`.`salesman_commission_settlements__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'salesmen__data', COUNT(*)
  FROM `srasinge1_gp`.`salesmen__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'stock_adjustment_lines__data', COUNT(*)
  FROM `srasinge1_gp`.`stock_adjustment_lines__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'stock_adjustments__data', COUNT(*)
  FROM `srasinge1_gp`.`stock_adjustments__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'stock_movements__data', COUNT(*)
  FROM `srasinge1_gp`.`stock_movements__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'subcategories__data', COUNT(*)
  FROM `srasinge1_gp`.`subcategories__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'supplier_advances__data', COUNT(*)
  FROM `srasinge1_gp`.`supplier_advances__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'suppliers__data', COUNT(*)
  FROM `srasinge1_gp`.`suppliers__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'system_settings__data', COUNT(*)
  FROM `srasinge1_gp`.`system_settings__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'user_permission_overrides__data', COUNT(*)
  FROM `srasinge1_gp`.`user_permission_overrides__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'tenant_id has no such business', 'users__data', COUNT(*)
  FROM `srasinge1_gp`.`users__data` x
  LEFT JOIN `srasinge1_gp`.`tenants` tn ON tn.id = x.tenant_id
 WHERE tn.id IS NULL
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'brand_purchase_slabs__data.brand_id -> brands__data', COUNT(*)
  FROM `srasinge1_gp`.`brand_purchase_slabs__data` a
  JOIN `srasinge1_gp`.`brands__data` b ON b.`id` = a.`brand_id`
 WHERE a.`brand_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'brand_rebate_cost_adjustments__data.product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`brand_rebate_cost_adjustments__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`product_id`
 WHERE a.`product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'brand_rebate_cost_adjustments__data.rebate_id -> brand_rebates__data', COUNT(*)
  FROM `srasinge1_gp`.`brand_rebate_cost_adjustments__data` a
  JOIN `srasinge1_gp`.`brand_rebates__data` b ON b.`id` = a.`rebate_id`
 WHERE a.`rebate_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'brand_rebates__data.linked_advance_id -> supplier_advances__data', COUNT(*)
  FROM `srasinge1_gp`.`brand_rebates__data` a
  JOIN `srasinge1_gp`.`supplier_advances__data` b ON b.`id` = a.`linked_advance_id`
 WHERE a.`linked_advance_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'brand_rebates__data.brand_id -> brands__data', COUNT(*)
  FROM `srasinge1_gp`.`brand_rebates__data` a
  JOIN `srasinge1_gp`.`brands__data` b ON b.`id` = a.`brand_id`
 WHERE a.`brand_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'brand_rebates__data.target_invoice_id -> purchase_invoices__data', COUNT(*)
  FROM `srasinge1_gp`.`brand_rebates__data` a
  JOIN `srasinge1_gp`.`purchase_invoices__data` b ON b.`id` = a.`target_invoice_id`
 WHERE a.`target_invoice_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'brand_rebates__data.free_product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`brand_rebates__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`free_product_id`
 WHERE a.`free_product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'brand_rebates__data.supplier_id -> suppliers__data', COUNT(*)
  FROM `srasinge1_gp`.`brand_rebates__data` a
  JOIN `srasinge1_gp`.`suppliers__data` b ON b.`id` = a.`supplier_id`
 WHERE a.`supplier_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'cashier_drawers__data.assigned_user_id -> users__data', COUNT(*)
  FROM `srasinge1_gp`.`cashier_drawers__data` a
  JOIN `srasinge1_gp`.`users__data` b ON b.`id` = a.`assigned_user_id`
 WHERE a.`assigned_user_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'customer_advances__data.customer_id -> customers__data', COUNT(*)
  FROM `srasinge1_gp`.`customer_advances__data` a
  JOIN `srasinge1_gp`.`customers__data` b ON b.`id` = a.`customer_id`
 WHERE a.`customer_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'customers__data.default_salesman_id -> salesmen__data', COUNT(*)
  FROM `srasinge1_gp`.`customers__data` a
  JOIN `srasinge1_gp`.`salesmen__data` b ON b.`id` = a.`default_salesman_id`
 WHERE a.`default_salesman_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'customers__data.linked_employee_id -> employees__data', COUNT(*)
  FROM `srasinge1_gp`.`customers__data` a
  JOIN `srasinge1_gp`.`employees__data` b ON b.`id` = a.`linked_employee_id`
 WHERE a.`linked_employee_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'customers__data.linked_salesman_id -> salesmen__data', COUNT(*)
  FROM `srasinge1_gp`.`customers__data` a
  JOIN `srasinge1_gp`.`salesmen__data` b ON b.`id` = a.`linked_salesman_id`
 WHERE a.`linked_salesman_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'employees__data.linked_customer_id -> customers__data', COUNT(*)
  FROM `srasinge1_gp`.`employees__data` a
  JOIN `srasinge1_gp`.`customers__data` b ON b.`id` = a.`linked_customer_id`
 WHERE a.`linked_customer_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'employees__data.linked_user_id -> users__data', COUNT(*)
  FROM `srasinge1_gp`.`employees__data` a
  JOIN `srasinge1_gp`.`users__data` b ON b.`id` = a.`linked_user_id`
 WHERE a.`linked_user_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'legacy_obligations__data.customer_id -> customers__data', COUNT(*)
  FROM `srasinge1_gp`.`legacy_obligations__data` a
  JOIN `srasinge1_gp`.`customers__data` b ON b.`id` = a.`customer_id`
 WHERE a.`customer_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'legacy_obligations__data.salesman_id -> salesmen__data', COUNT(*)
  FROM `srasinge1_gp`.`legacy_obligations__data` a
  JOIN `srasinge1_gp`.`salesmen__data` b ON b.`id` = a.`salesman_id`
 WHERE a.`salesman_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'legacy_obligations__data.supplier_id -> suppliers__data', COUNT(*)
  FROM `srasinge1_gp`.`legacy_obligations__data` a
  JOIN `srasinge1_gp`.`suppliers__data` b ON b.`id` = a.`supplier_id`
 WHERE a.`supplier_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'legacy_settlements__data.customer_id -> customers__data', COUNT(*)
  FROM `srasinge1_gp`.`legacy_settlements__data` a
  JOIN `srasinge1_gp`.`customers__data` b ON b.`id` = a.`customer_id`
 WHERE a.`customer_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'legacy_settlements__data.obligation_id -> legacy_obligations__data', COUNT(*)
  FROM `srasinge1_gp`.`legacy_settlements__data` a
  JOIN `srasinge1_gp`.`legacy_obligations__data` b ON b.`id` = a.`obligation_id`
 WHERE a.`obligation_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'legacy_settlements__data.salesman_id -> salesmen__data', COUNT(*)
  FROM `srasinge1_gp`.`legacy_settlements__data` a
  JOIN `srasinge1_gp`.`salesmen__data` b ON b.`id` = a.`salesman_id`
 WHERE a.`salesman_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'legacy_settlements__data.supplier_id -> suppliers__data', COUNT(*)
  FROM `srasinge1_gp`.`legacy_settlements__data` a
  JOIN `srasinge1_gp`.`suppliers__data` b ON b.`id` = a.`supplier_id`
 WHERE a.`supplier_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'operating_expenses__data.category_id -> operating_expense_categories__data', COUNT(*)
  FROM `srasinge1_gp`.`operating_expenses__data` a
  JOIN `srasinge1_gp`.`operating_expense_categories__data` b ON b.`id` = a.`category_id`
 WHERE a.`category_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'operating_expenses__data.spawned_from_recurring_id -> recurring_expenses__data', COUNT(*)
  FROM `srasinge1_gp`.`operating_expenses__data` a
  JOIN `srasinge1_gp`.`recurring_expenses__data` b ON b.`id` = a.`spawned_from_recurring_id`
 WHERE a.`spawned_from_recurring_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'product_composition_items__data.component_product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`product_composition_items__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`component_product_id`
 WHERE a.`component_product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'product_composition_items__data.composition_id -> product_compositions__data', COUNT(*)
  FROM `srasinge1_gp`.`product_composition_items__data` a
  JOIN `srasinge1_gp`.`product_compositions__data` b ON b.`id` = a.`composition_id`
 WHERE a.`composition_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'product_compositions__data.product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`product_compositions__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`product_id`
 WHERE a.`product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'product_price_history__data.product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`product_price_history__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`product_id`
 WHERE a.`product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'product_slab_rates__data.product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`product_slab_rates__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`product_id`
 WHERE a.`product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'product_slab_rates__data.slab_id -> brand_purchase_slabs__data', COUNT(*)
  FROM `srasinge1_gp`.`product_slab_rates__data` a
  JOIN `srasinge1_gp`.`brand_purchase_slabs__data` b ON b.`id` = a.`slab_id`
 WHERE a.`slab_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'product_units__data.product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`product_units__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`product_id`
 WHERE a.`product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'production_order_lines__data.component_product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`production_order_lines__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`component_product_id`
 WHERE a.`component_product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'production_order_lines__data.production_order_id -> production_orders__data', COUNT(*)
  FROM `srasinge1_gp`.`production_order_lines__data` a
  JOIN `srasinge1_gp`.`production_orders__data` b ON b.`id` = a.`production_order_id`
 WHERE a.`production_order_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'production_orders__data.product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`production_orders__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`product_id`
 WHERE a.`product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'products__data.brand_id -> brands__data', COUNT(*)
  FROM `srasinge1_gp`.`products__data` a
  JOIN `srasinge1_gp`.`brands__data` b ON b.`id` = a.`brand_id`
 WHERE a.`brand_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'products__data.subcategory_id -> subcategories__data', COUNT(*)
  FROM `srasinge1_gp`.`products__data` a
  JOIN `srasinge1_gp`.`subcategories__data` b ON b.`id` = a.`subcategory_id`
 WHERE a.`subcategory_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'purchase_invoice_expenses__data.purchase_invoice_id -> purchase_invoices__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_invoice_expenses__data` a
  JOIN `srasinge1_gp`.`purchase_invoices__data` b ON b.`id` = a.`purchase_invoice_id`
 WHERE a.`purchase_invoice_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'purchase_invoice_lines__data.purchase_invoice_id -> purchase_invoices__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_invoice_lines__data` a
  JOIN `srasinge1_gp`.`purchase_invoices__data` b ON b.`id` = a.`purchase_invoice_id`
 WHERE a.`purchase_invoice_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'purchase_invoice_lines__data.product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_invoice_lines__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`product_id`
 WHERE a.`product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'purchase_invoices__data.brand_id -> brands__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_invoices__data` a
  JOIN `srasinge1_gp`.`brands__data` b ON b.`id` = a.`brand_id`
 WHERE a.`brand_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'purchase_invoices__data.supplier_id -> suppliers__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_invoices__data` a
  JOIN `srasinge1_gp`.`suppliers__data` b ON b.`id` = a.`supplier_id`
 WHERE a.`supplier_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'purchase_payments__data.from_advance_id -> supplier_advances__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_payments__data` a
  JOIN `srasinge1_gp`.`supplier_advances__data` b ON b.`id` = a.`from_advance_id`
 WHERE a.`from_advance_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'purchase_payments__data.purchase_invoice_id -> purchase_invoices__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_payments__data` a
  JOIN `srasinge1_gp`.`purchase_invoices__data` b ON b.`id` = a.`purchase_invoice_id`
 WHERE a.`purchase_invoice_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'purchase_return_lines__data.original_purchase_line_id -> purchase_invoice_lines__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_return_lines__data` a
  JOIN `srasinge1_gp`.`purchase_invoice_lines__data` b ON b.`id` = a.`original_purchase_line_id`
 WHERE a.`original_purchase_line_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'purchase_return_lines__data.product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_return_lines__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`product_id`
 WHERE a.`product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'purchase_return_lines__data.purchase_return_id -> purchase_returns__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_return_lines__data` a
  JOIN `srasinge1_gp`.`purchase_returns__data` b ON b.`id` = a.`purchase_return_id`
 WHERE a.`purchase_return_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'purchase_returns__data.original_purchase_invoice_id -> purchase_invoices__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_returns__data` a
  JOIN `srasinge1_gp`.`purchase_invoices__data` b ON b.`id` = a.`original_purchase_invoice_id`
 WHERE a.`original_purchase_invoice_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'purchase_returns__data.supplier_id -> suppliers__data', COUNT(*)
  FROM `srasinge1_gp`.`purchase_returns__data` a
  JOIN `srasinge1_gp`.`suppliers__data` b ON b.`id` = a.`supplier_id`
 WHERE a.`supplier_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'rate_change_batches__data.brand_id -> brands__data', COUNT(*)
  FROM `srasinge1_gp`.`rate_change_batches__data` a
  JOIN `srasinge1_gp`.`brands__data` b ON b.`id` = a.`brand_id`
 WHERE a.`brand_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'rate_change_batches__data.category_id -> categories__data', COUNT(*)
  FROM `srasinge1_gp`.`rate_change_batches__data` a
  JOIN `srasinge1_gp`.`categories__data` b ON b.`id` = a.`category_id`
 WHERE a.`category_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'rate_change_batches__data.subcategory_id -> subcategories__data', COUNT(*)
  FROM `srasinge1_gp`.`rate_change_batches__data` a
  JOIN `srasinge1_gp`.`subcategories__data` b ON b.`id` = a.`subcategory_id`
 WHERE a.`subcategory_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'rate_change_lines__data.batch_id -> rate_change_batches__data', COUNT(*)
  FROM `srasinge1_gp`.`rate_change_lines__data` a
  JOIN `srasinge1_gp`.`rate_change_batches__data` b ON b.`id` = a.`batch_id`
 WHERE a.`batch_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'rate_change_lines__data.product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`rate_change_lines__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`product_id`
 WHERE a.`product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'recurring_expenses__data.category_id -> operating_expense_categories__data', COUNT(*)
  FROM `srasinge1_gp`.`recurring_expenses__data` a
  JOIN `srasinge1_gp`.`operating_expense_categories__data` b ON b.`id` = a.`category_id`
 WHERE a.`category_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_invoice_extras__data.sales_invoice_id -> sales_invoices__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_invoice_extras__data` a
  JOIN `srasinge1_gp`.`sales_invoices__data` b ON b.`id` = a.`sales_invoice_id`
 WHERE a.`sales_invoice_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_invoice_line_components__data.component_product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_invoice_line_components__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`component_product_id`
 WHERE a.`component_product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_invoice_line_components__data.sales_invoice_line_id -> sales_invoice_lines__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_invoice_line_components__data` a
  JOIN `srasinge1_gp`.`sales_invoice_lines__data` b ON b.`id` = a.`sales_invoice_line_id`
 WHERE a.`sales_invoice_line_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_invoice_lines__data.sales_invoice_id -> sales_invoices__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_invoice_lines__data` a
  JOIN `srasinge1_gp`.`sales_invoices__data` b ON b.`id` = a.`sales_invoice_id`
 WHERE a.`sales_invoice_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_invoice_lines__data.product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_invoice_lines__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`product_id`
 WHERE a.`product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_invoice_lines__data.salesman_id -> salesmen__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_invoice_lines__data` a
  JOIN `srasinge1_gp`.`salesmen__data` b ON b.`id` = a.`salesman_id`
 WHERE a.`salesman_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_invoices__data.customer_id -> customers__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_invoices__data` a
  JOIN `srasinge1_gp`.`customers__data` b ON b.`id` = a.`customer_id`
 WHERE a.`customer_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_invoices__data.header_salesman_id -> salesmen__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_invoices__data` a
  JOIN `srasinge1_gp`.`salesmen__data` b ON b.`id` = a.`header_salesman_id`
 WHERE a.`header_salesman_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_payments__data.from_advance_id -> customer_advances__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_payments__data` a
  JOIN `srasinge1_gp`.`customer_advances__data` b ON b.`id` = a.`from_advance_id`
 WHERE a.`from_advance_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_payments__data.sales_invoice_id -> sales_invoices__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_payments__data` a
  JOIN `srasinge1_gp`.`sales_invoices__data` b ON b.`id` = a.`sales_invoice_id`
 WHERE a.`sales_invoice_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_return_line_components__data.sales_return_line_id -> sales_return_lines__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_return_line_components__data` a
  JOIN `srasinge1_gp`.`sales_return_lines__data` b ON b.`id` = a.`sales_return_line_id`
 WHERE a.`sales_return_line_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_return_line_components__data.original_component_id -> sales_invoice_line_components__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_return_line_components__data` a
  JOIN `srasinge1_gp`.`sales_invoice_line_components__data` b ON b.`id` = a.`original_component_id`
 WHERE a.`original_component_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_return_line_components__data.component_product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_return_line_components__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`component_product_id`
 WHERE a.`component_product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_return_lines__data.original_sales_line_id -> sales_invoice_lines__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_return_lines__data` a
  JOIN `srasinge1_gp`.`sales_invoice_lines__data` b ON b.`id` = a.`original_sales_line_id`
 WHERE a.`original_sales_line_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_return_lines__data.product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_return_lines__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`product_id`
 WHERE a.`product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_return_lines__data.sales_return_id -> sales_returns__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_return_lines__data` a
  JOIN `srasinge1_gp`.`sales_returns__data` b ON b.`id` = a.`sales_return_id`
 WHERE a.`sales_return_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_return_lines__data.salesman_id -> salesmen__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_return_lines__data` a
  JOIN `srasinge1_gp`.`salesmen__data` b ON b.`id` = a.`salesman_id`
 WHERE a.`salesman_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_returns__data.customer_id -> customers__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_returns__data` a
  JOIN `srasinge1_gp`.`customers__data` b ON b.`id` = a.`customer_id`
 WHERE a.`customer_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'sales_returns__data.original_sales_invoice_id -> sales_invoices__data', COUNT(*)
  FROM `srasinge1_gp`.`sales_returns__data` a
  JOIN `srasinge1_gp`.`sales_invoices__data` b ON b.`id` = a.`original_sales_invoice_id`
 WHERE a.`original_sales_invoice_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'salesman_commission_settlements__data.goods_product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`salesman_commission_settlements__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`goods_product_id`
 WHERE a.`goods_product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'salesman_commission_settlements__data.salesman_id -> salesmen__data', COUNT(*)
  FROM `srasinge1_gp`.`salesman_commission_settlements__data` a
  JOIN `srasinge1_gp`.`salesmen__data` b ON b.`id` = a.`salesman_id`
 WHERE a.`salesman_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'salesmen__data.linked_customer_id -> customers__data', COUNT(*)
  FROM `srasinge1_gp`.`salesmen__data` a
  JOIN `srasinge1_gp`.`customers__data` b ON b.`id` = a.`linked_customer_id`
 WHERE a.`linked_customer_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'stock_adjustment_lines__data.stock_adjustment_id -> stock_adjustments__data', COUNT(*)
  FROM `srasinge1_gp`.`stock_adjustment_lines__data` a
  JOIN `srasinge1_gp`.`stock_adjustments__data` b ON b.`id` = a.`stock_adjustment_id`
 WHERE a.`stock_adjustment_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'stock_adjustment_lines__data.product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`stock_adjustment_lines__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`product_id`
 WHERE a.`product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'stock_movements__data.product_id -> products__data', COUNT(*)
  FROM `srasinge1_gp`.`stock_movements__data` a
  JOIN `srasinge1_gp`.`products__data` b ON b.`id` = a.`product_id`
 WHERE a.`product_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'subcategories__data.category_id -> categories__data', COUNT(*)
  FROM `srasinge1_gp`.`subcategories__data` a
  JOIN `srasinge1_gp`.`categories__data` b ON b.`id` = a.`category_id`
 WHERE a.`category_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'supplier_advances__data.supplier_id -> suppliers__data', COUNT(*)
  FROM `srasinge1_gp`.`supplier_advances__data` a
  JOIN `srasinge1_gp`.`suppliers__data` b ON b.`id` = a.`supplier_id`
 WHERE a.`supplier_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0

UNION ALL

SELECT 'CROSS-TENANT REFERENCE', 'user_permission_overrides__data.user_id -> users__data', COUNT(*)
  FROM `srasinge1_gp`.`user_permission_overrides__data` a
  JOIN `srasinge1_gp`.`users__data` b ON b.`id` = a.`user_id`
 WHERE a.`user_id` IS NOT NULL AND b.tenant_id <> a.tenant_id
 HAVING COUNT(*) > 0;
