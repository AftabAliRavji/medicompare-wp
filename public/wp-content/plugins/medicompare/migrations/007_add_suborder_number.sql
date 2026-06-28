/* ---------------------------------------------------------
   Migration 007 — Add suborder_number to wp_medi_order_supplier_summary
   Purpose: Store supplier-specific sub-order references
   Format: {order_number}-{supplier_code}
--------------------------------------------------------- */

-- Only add the column if it does not already exist
ALTER TABLE wp_medi_order_supplier_summary
ADD COLUMN suborder_number VARCHAR(50) AFTER supplier_id;
