-- pharmacy_supplier_accounts.sql

CREATE TABLE IF NOT EXISTS `wp_medi_pharmacy_supplier_accounts` (

    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    pharmacy_id BIGINT UNSIGNED NOT NULL,

    supplier_id BIGINT UNSIGNED NOT NULL,

    account_id VARCHAR(100) NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY pharmacy_supplier_unique (
        pharmacy_id,
        supplier_id
    ),

    KEY idx_pharmacy_id (
        pharmacy_id
    ),

    KEY idx_supplier_id (
        supplier_id
    ),

    KEY idx_account_id (
        account_id
    )

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;