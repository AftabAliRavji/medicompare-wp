CREATE TABLE `wp_medi_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_number` int unsigned DEFAULT NULL,
  `pharmacy_id` bigint unsigned NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `pharmacy_id` (`pharmacy_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci