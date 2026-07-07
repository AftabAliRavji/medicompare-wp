CREATE TABLE IF NOT EXISTS `wp_medi_billing_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `pharmacy_id` BIGINT UNSIGNED NOT NULL,
    `subscription_id` BIGINT UNSIGNED NULL,

    `stripe_invoice_id` VARCHAR(255) NULL,
    `stripe_payment_intent` VARCHAR(255) NULL,
    `stripe_charge_id` VARCHAR(255) NULL,

    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'gbp',

    `status` VARCHAR(50) NOT NULL,
    -- paid, failed, refunded, open, void

    `invoice_url` TEXT NULL,

    `created_at` DATETIME NOT NULL,
    `paid_at` DATETIME NULL,
    `failed_at` DATETIME NULL,
    `refunded_at` DATETIME NULL,

    `retry_count` INT UNSIGNED NOT NULL DEFAULT 0,

    PRIMARY KEY (`id`),
    KEY `pharmacy_id` (`pharmacy_id`),
    KEY `subscription_id` (`subscription_id`),
    KEY `stripe_invoice_id` (`stripe_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
