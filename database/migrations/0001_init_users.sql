-- Module #2 Authentication
-- Creates the users table.
-- Bootstrap admin user is created by database/create_admin.php (not in this migration,
-- to keep credentials out of source control).

CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    phone           VARCHAR(20)  NOT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    address         TEXT         DEFAULT NULL,
    username        VARCHAR(150) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('admin', 'subadmin', 'cashier') NOT NULL DEFAULT 'subadmin',
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME     DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_users_username (username),
    KEY idx_users_role (role),
    KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
