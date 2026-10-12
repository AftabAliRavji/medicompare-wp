CREATE TABLE wp_medi_gocardless_events (

    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    pharmacy_id BIGINT UNSIGNED DEFAULT NULL,

    event_id VARCHAR(64) NOT NULL,

    resource_type VARCHAR(50) NOT NULL,

    action_name VARCHAR(50) NOT NULL,

    resource_id VARCHAR(100) DEFAULT NULL,

    raw_payload LONGTEXT,

    created_at DATETIME NOT NULL,

    PRIMARY KEY (id),

    KEY idx_pharmacy_id (pharmacy_id),

    KEY idx_event_id (event_id),

    KEY idx_resource_type (resource_type),

    KEY idx_action (action_name)

);