-- =====================================================================
--  GP ENTERPRISE IMS — CR-1..CR-11 release: POST-DEPLOY VERIFICATION
-- =====================================================================
--  READ-ONLY. Changes nothing. Safe to run any number of times.
--
--  Paste into phpMyAdmin -> (IMS database) -> SQL -> Go, after running
--  release_cr1_cr11_manual.sql.
--
--  Every row must read OK. Any FAIL means that part of the schema did
--  not apply -- restore your backup and re-run the release script.
--
--  Expected: 15 rows, all OK.
-- =====================================================================

SELECT '00 database in use' AS check_item,
       DATABASE() AS detail,
       IF(DATABASE() IS NOT NULL, 'OK', 'FAIL') AS status

UNION ALL SELECT '01 migrations 0055-0059 recorded',
       CONCAT(COUNT(*), ' of 5'),
       IF(COUNT(*) = 5, 'OK', 'FAIL')
  FROM migrations
 WHERE filename IN ('0055_due_term_tagging.sql','0056_init_rate_changes.sql',
                    '0057_init_brand_purchase_slabs.sql',
                    '0058_sales_line_commission_override.sql',
                    '0059_optional_freetext_phone.sql')

UNION ALL SELECT '02 sales_invoices.due_term', 'CR-3',
       IF(COUNT(*) = 1, 'OK', 'FAIL')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales_invoices'
   AND COLUMN_NAME = 'due_term'

UNION ALL SELECT '03 customers.default_due_term', 'CR-3',
       IF(COUNT(*) = 1, 'OK', 'FAIL')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
   AND COLUMN_NAME = 'default_due_term'

UNION ALL SELECT '04 index idx_si_due_term', 'CR-3',
       IF(COUNT(*) > 0, 'OK', 'FAIL')
  FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales_invoices'
   AND INDEX_NAME = 'idx_si_due_term'

UNION ALL SELECT '05 rate_change_batches table', 'CR-4',
       IF(COUNT(*) = 1, 'OK', 'FAIL')
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rate_change_batches'

UNION ALL SELECT '06 rate_change_lines table', 'CR-4',
       IF(COUNT(*) = 1, 'OK', 'FAIL')
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rate_change_lines'

UNION ALL SELECT '07 brand_purchase_slabs table', 'CR-1b',
       IF(COUNT(*) = 1, 'OK', 'FAIL')
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brand_purchase_slabs'

UNION ALL SELECT '08 product_slab_rates table', 'CR-1b',
       IF(COUNT(*) = 1, 'OK', 'FAIL')
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_slab_rates'

UNION ALL SELECT '09 slab feature flag = 0 (OFF)',
       IFNULL((SELECT value FROM system_settings
                WHERE setting_key = 'enable_brand_slab_rates'), 'missing'),
       IF((SELECT value FROM system_settings
            WHERE setting_key = 'enable_brand_slab_rates') = '0', 'OK', 'FAIL')

UNION ALL SELECT '10 sales_invoice_lines.commission_rate_override', 'CR-6',
       IF(COUNT(*) = 1, 'OK', 'FAIL')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales_invoice_lines'
   AND COLUMN_NAME = 'commission_rate_override'

UNION ALL SELECT '11 customers.phone is nullable', 'CR-10',
       IF(COUNT(*) = 1, 'OK', 'FAIL')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
   AND COLUMN_NAME = 'phone' AND IS_NULLABLE = 'YES'

UNION ALL SELECT '12 suppliers.phone is nullable', 'CR-10',
       IF(COUNT(*) = 1, 'OK', 'FAIL')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers'
   AND COLUMN_NAME = 'phone' AND IS_NULLABLE = 'YES'

UNION ALL SELECT '13 customers.phone UNIQUE retained',
       'intentional - see DEPLOYMENT 7',
       IF(COUNT(*) > 0, 'OK', 'FAIL')
  FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
   AND COLUMN_NAME = 'phone' AND NON_UNIQUE = 0

UNION ALL SELECT '14 no existing data was altered',
       CONCAT('finalized invoices: ', (SELECT COUNT(*) FROM sales_invoices
                                        WHERE status = 'finalized')),
       IF((SELECT COUNT(*) FROM sales_invoices WHERE due_term IS NOT NULL) = 0,
          'OK', 'CHECK')

ORDER BY check_item;
