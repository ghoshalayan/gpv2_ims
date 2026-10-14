-- One statement. Counts your real rows straight from the base tables, so it
-- needs no @app_tenant and no database selected in the sidebar.
-- Find & Replace srasinge1_gp if your database is named differently.
SELECT
    (SELECT COUNT(*) FROM srasinge1_gp.products__data)       AS products,
    (SELECT COUNT(*) FROM srasinge1_gp.customers__data)      AS customers,
    (SELECT COUNT(*) FROM srasinge1_gp.suppliers__data)      AS suppliers,
    (SELECT COUNT(*) FROM srasinge1_gp.sales_invoices__data) AS sales_invoices,
    (SELECT COUNT(*) FROM srasinge1_gp.users__data)          AS users,
    (SELECT COUNT(*) FROM srasinge1_gp.products__data
      WHERE tenant_id = 1)                                   AS products_tagged_tenant_1,
    (SELECT COUNT(*) FROM srasinge1_gp.products__data
      WHERE tenant_id IS NULL OR tenant_id <> 1)             AS products_mistagged_must_be_0;
