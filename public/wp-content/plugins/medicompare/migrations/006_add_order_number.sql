/* ---------------------------------------------------------
   Migration 006 — Add order_number to wp_medi_orders
   Purpose: Introduce human-friendly master order numbering
   Starts at 10001 and increments forever
--------------------------------------------------------- */

-- Only add the column if it does not already exist
ALTER TABLE wp_medi_orders
ADD COLUMN order_number INT UNSIGNED UNIQUE AFTER id;
