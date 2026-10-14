ALTER TABLE wp_medi_gocardless_events 
ADD COLUMN event_created_at datetime NULL 
AFTER resource_id;