/* ---------------------------------------------------------
   Migration 008 — Create wp_medi_order_suborders
   Purpose: Store supplier-specific sub-orders with status,
            email tracking, and timestamps.
--------------------------------------------------------- */

CREATE TABLE IF NOT EXISTS wp_medi_order_suborders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    suborder_number VARCHAR(50) NOT NULL,
    supplier_order_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    email_sent TINYINT(1) NOT NULL DEFAULT 0,
    email_sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_order_id (order_id),
    KEY idx_supplier_id (supplier_id),
    UNIQUE KEY uq_suborder_number (suborder_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
