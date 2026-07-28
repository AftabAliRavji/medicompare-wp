CREATE TABLE IF NOT EXISTS wp_medi_supplier_commission_emails (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id BIGINT UNSIGNED NOT NULL,
    period_from DATE NOT NULL,
    period_to DATE NOT NULL,
    sent_at DATETIME NOT NULL,
    sent_by_admin TINYINT(1) NOT NULL DEFAULT 1,
    auto_sent TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY supplier_id (supplier_id),
    KEY period_range (period_from, period_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
