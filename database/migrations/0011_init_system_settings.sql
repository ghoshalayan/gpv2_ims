-- Module #11.5 System Settings
--
-- business_profile:     singleton (id=1 always). Used on invoice headers, for determining
--                       intra-state vs inter-state GST (per D52), and for the financial year.
-- system_settings:      key/value table for system-wide toggles.

CREATE TABLE business_profile (
    id              INT PRIMARY KEY,
    legal_name      VARCHAR(250) NOT NULL,
    display_name    VARCHAR(150) NOT NULL,
    gstin           VARCHAR(15)  DEFAULT NULL,
    state           VARCHAR(100) NOT NULL,
    address         TEXT         DEFAULT NULL,
    phone           VARCHAR(20)  DEFAULT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    financial_year_start_month TINYINT NOT NULL DEFAULT 4,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by      INT          DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed a placeholder row so the settings form always has something to load.
INSERT INTO business_profile (id, legal_name, display_name, state, financial_year_start_month)
VALUES (1, 'Your Business Legal Name', 'GP ENTERPRISE', 'West Bengal', 4);

CREATE TABLE system_settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    value         TEXT         NOT NULL,
    description   VARCHAR(255) DEFAULT NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by    INT          DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeded defaults. Values are strings (cast in code where needed).
INSERT INTO system_settings (setting_key, value, description) VALUES
    ('commission_accrues_on', 'invoice_finalization',
        'When commission posts: invoice_finalization | invoice_payment | manual_only'),
    ('enable_round_off', '1',
        'Round invoice grand total to nearest rupee (1 = yes, 0 = no)'),
    ('low_stock_dashboard_widget', '1',
        'Show low-stock items on dashboard (1 = yes, 0 = no)');
