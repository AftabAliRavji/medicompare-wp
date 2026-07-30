CREATE TABLE IF NOT EXISTS `wp_medi_supplier_invoices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `period_from` DATE NOT NULL,
    `period_to` DATE NOT NULL,
    `invoice_reference` VARCHAR(255) NOT NULL,
    `total_commission` DECIMAL(10,2) NOT NULL,
    `total_supplier_amount` DECIMAL(10,2) NOT NULL,
    `orders_json` LONGTEXT NOT NULL,
    `pdf_filename` VARCHAR(255) DEFAULT NULL,
    `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `supplier_id_idx` (`supplier_id`),
    KEY `invoice_ref_idx` (`invoice_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
