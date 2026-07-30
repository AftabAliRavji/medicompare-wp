CREATE TABLE IF NOT EXISTS `wp_medi_supplier_payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `invoice_id` BIGINT UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `paid_date` DATE NOT NULL,
    `reference` VARCHAR(255) DEFAULT NULL,
    `method` ENUM('manual','csv','stripe') NOT NULL DEFAULT 'manual',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `supplier_id_idx` (`supplier_id`),
    KEY `invoice_id_idx` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
