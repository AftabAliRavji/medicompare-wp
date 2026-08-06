CREATE TABLE IF NOT EXISTS `wp_medi_reference_prices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM('drug_tariff', 'clawback', 'concession') NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `display` ENUM('yes', 'no') NOT NULL DEFAULT 'yes',
    `last_updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_product_id` (`product_id`),
    INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
