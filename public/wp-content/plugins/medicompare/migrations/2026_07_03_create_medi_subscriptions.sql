CREATE TABLE IF NOT EXISTS wp_medi_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    stripe_customer VARCHAR(255),
    stripe_subscription VARCHAR(255),
    stripe_price VARCHAR(255),
    renewal_date DATETIME,
    status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
