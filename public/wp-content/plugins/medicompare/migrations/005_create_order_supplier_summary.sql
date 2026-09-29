CREATE TABLE `wp_medi_order_supplier_summary` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `suborder_number` varchar(50) DEFAULT NULL,
  `supplier_total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `platform_fee_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `platform_fee_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `supplier_order_status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `supplier_id` (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci