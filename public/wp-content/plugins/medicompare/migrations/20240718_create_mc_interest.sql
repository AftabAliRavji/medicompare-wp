CREATE TABLE IF NOT EXISTS wp_mc_interest (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pharmacy_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) NOT NULL,
    contact_number VARCHAR(50) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL
);
