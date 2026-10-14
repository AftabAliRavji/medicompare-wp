ALTER TABLE wp_medi_gocardless_events
ADD COLUMN webhook_payload LONGTEXT NULL
AFTER raw_payload;