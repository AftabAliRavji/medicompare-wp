⭐⭐Step 1 is optional based on stability of orders
⭐1.1 Clear pending orders
DELETE FROM wp_medi_pending_order_items;
DELETE FROM wp_medi_pending_orders;

⭐1.2 Clear transferred orders
DELETE FROM wp_medi_order_items;
DELETE FROM wp_medi_order_supplier_summary;
DELETE FROM wp_medi_order_suborders;
DELETE FROM wp_medi_orders;

⭐⭐STEP 2 — Create One New Pending Order (Detailed Test Step)
⭐2.1.1 — Check All Suppliers Have Required Fields
SELECT ID, post_title
FROM wp_posts
WHERE post_type = 'mc_supplier'
  AND post_status = 'publish';

STEP 2.1 - Combined supplier check (email + code)
  SELECT 
    p.ID AS supplier_id,
    p.post_title AS supplier_name,
    email.meta_value AS supplier_email,
    code.meta_value AS supplier_code
FROM wp_posts p
LEFT JOIN wp_postmeta email 
       ON email.post_id = p.ID 
      AND email.meta_key = 'mc_supplier_email'
LEFT JOIN wp_postmeta code 
       ON code.post_id = p.ID 
      AND code.meta_key = 'mc_supplier_code'
WHERE p.post_type = 'mc_supplier'
  AND p.post_status = 'publish';

STEP 2.1.2 — Pharmacy Combined Check (Email + Phone + Address)
 STEP 2.1.2.1 — Check pharmacy CPT exists
  SELECT ID, post_title
FROM wp_posts
WHERE post_type = 'mc_pharmacy'
  AND post_status = 'publish';

STEP 2.1.2.2 — Check pharmacy email
 SELECT post_id AS pharmacy_id,
       meta_value AS pharmacy_email
FROM wp_postmeta
WHERE meta_key = 'mc_pharmacy_email';

STEP 2.1.2.3 — Check pharmacy phone
 SELECT post_id AS pharmacy_id,
       meta_value AS pharmacy_phone
FROM wp_postmeta
WHERE meta_key = 'mc_pharmacy_phone';

STEP 2.1.2.4 — Check pharmacy address
 SELECT post_id AS pharmacy_id,
       meta_value AS pharmacy_address
FROM wp_postmeta
WHERE meta_key = 'mc_pharmacy_address';


⭐STEP 2.1.2.5 — Combined Pharmacy Check (Email + Phone + Address)
SELECT 
    p.ID AS pharmacy_id,
    p.post_title AS pharmacy_name,

    email.meta_value        AS pharmacy_email,
    phone.meta_value        AS pharmacy_phone,
    line1.meta_value        AS address_line_1,
    line2.meta_value        AS address_line_2,
    city.meta_value         AS city,
    postcode.meta_value     AS postcode,
    gphc.meta_value         AS gphc_number,
    contact.meta_value      AS contact_name,
    status.meta_value       AS pharmacy_status

FROM wp_posts p

LEFT JOIN wp_postmeta email 
       ON email.post_id = p.ID 
      AND email.meta_key = '_mc_email'

LEFT JOIN wp_postmeta phone 
       ON phone.post_id = p.ID 
      AND phone.meta_key = '_mc_phone'

LEFT JOIN wp_postmeta line1 
       ON line1.post_id = p.ID 
      AND line1.meta_key = '_mc_address_line_1'

LEFT JOIN wp_postmeta line2 
       ON line2.post_id = p.ID 
      AND line2.meta_key = '_mc_address_line_2'

LEFT JOIN wp_postmeta city 
       ON city.post_id = p.ID 
      AND city.meta_key = '_mc_city'

LEFT JOIN wp_postmeta postcode 
       ON postcode.post_id = p.ID 
      AND postcode.meta_key = '_mc_postcode'

LEFT JOIN wp_postmeta gphc 
       ON gphc.post_id = p.ID 
      AND gphc.meta_key = '_mc_gphc_number'

LEFT JOIN wp_postmeta contact 
       ON contact.post_id = p.ID 
      AND contact.meta_key = '_mc_contact_name'

LEFT JOIN wp_postmeta status 
       ON status.post_id = p.ID 
      AND status.meta_key = '_mc_status'

WHERE p.post_type = 'mc_pharmacy'
  AND p.post_status = 'publish';


⭐⭐STEP 2.2 — Create Pending Order (SQL + UI Verification)
⭐ 2.2.1 — Create a Pending Order (UI)
✔ Go to the pharmacy ordering screen
(Your custom WordPress UI where the pharmacy searches products and adds them.)

✔ Add 3–5 products
Requirements:

At least 2 different suppliers

Quantities > 0

Prices look correct

✔ Click Add to Order for each product
You should now see the Pending Order panel appear.

⭐ 2.2.2 — Verify Pending Order (UI)
In the Pending Order panel, verify:

✔ Product list
Product names correct

Supplier names correct

Quantities correct

Unit prices correct

Line totals correct

✔ Supplier totals
Each supplier block should show:

Supplier name

Supplier total (sum of their items)

✔ Grand total
Should equal the sum of all supplier totals.

⭐ 2.2.3 — Verify Pending Order Exists (SQL)
SELECT *
FROM wp_medi_pending_orders
ORDER BY id DESC
LIMIT 1;

⭐ 2.2.4 — Verify Pending Order Items (SQL)
SELECT *
FROM wp_medi_pending_order_items
WHERE pending_order_id = YOUR_PENDING_ORDER_ID
ORDER BY supplier_id, product_id;

⭐ 2.2.5 — PASS / FAIL Criteria
✔ PASS if:
UI shows correct pending order
SQL shows correct pending order
SQL shows correct items
Supplier totals match
Grand total matches

❌ FAIL if:
No pending order row
Items missing
Wrong supplier_id
Wrong totals

⭐⭐ STEP 3 — Transfer Order (SQL + UI + Email Verification)
⭐ STEP 3.1 — Trigger Transfer Order (UI)
✔ Go to the pharmacy ordering screen
You should see your pending order from Step 2.

✔ Click Transfer Pending Order
This triggers:

Master order creation

Sub‑order creation

Supplier summary creation

Items moved

Emails sent

Pending order cleared

✔ Expected UI result
The pending order panel disappears.
The transferred orders panel now shows one new order.

⭐⭐ STEP 3.2 — Verify Master Order Created (SQL)
⭐ 3.2.1 — Get the Most Recent Master Order
SELECT *
FROM wp_medi_orders
ORDER BY id DESC
LIMIT 1;

⭐ 3.2.2 — Verify Order Number Increment Logic
SELECT order_number
FROM wp_medi_orders
ORDER BY order_number DESC
LIMIT 5;

⭐ 3.2.3 — Verify Total Amount Matches Pending Order
SELECT total_amount
FROM wp_medi_orders
WHERE id = YOUR_ORDER_ID; (replace id from previous script)

⭐ 3.2.4 — PASS / FAIL Criteria
✔ PASS if:
A new row exists in wp_medi_orders
order_number is correct (10001+)
total_amount matches pending order
status = transferred
created_at is correct

❌ FAIL if:
No row created
Wrong totals
Wrong order_number
Wrong status

⭐⭐ STEP 3.3 — Verify Items Moved (SQL)
⭐ 3.3.1 — Identify the Master Order ID
From Step 3.2, you already have:
id (internal PK)
order_number (10001+)
We will use the internal ID for joins.
Let’s call it:
MASTER_ORDER_ID = X

⭐ 3.3.2 — Verify Items in wp_medi_order_items (replace master_order_id with real number e.g. 4)
SELECT 
    i.*,
    p.post_title AS product_name,
    s.post_title AS supplier_name
FROM wp_medi_order_items i
LEFT JOIN wp_posts p ON p.ID = i.product_id
LEFT JOIN wp_posts s ON s.ID = i.supplier_id
WHERE i.order_id = MASTER_ORDER_ID
ORDER BY i.supplier_id, i.product_id;

⭐ 3.3.3 — Verify Supplier Totals (wp_medi_order_supplier_summary) (again replace e.g. 4)
SELECT *
FROM wp_medi_order_supplier_summary
WHERE order_id = MASTER_ORDER_ID
ORDER BY supplier_id;

⭐ 3.3.4 — Verify Sub‑Orders (wp_medi_order_suborders) (again replace with real e.g. 4)
SELECT *
FROM wp_medi_order_suborders
WHERE order_id = MASTER_ORDER_ID
ORDER BY supplier_id;

⭐⭐ STEP 3.4 — Verify Pending Order Cleared (SQL)
⭐ 3.4.1 — Check Pending Order Header Table
SELECT *
FROM wp_medi_pending_orders;

⭐ 3.4.2 — Check Pending Order Items Table
SELECT *
FROM wp_medi_pending_order_items;

⭐ STEP 3.5 — Verify Transferred Orders Panel (UI)
This is where we visually confirm:
Supplier blocks
Sub‑order numbers
Status badges
Supplier totals
Email sent indicators
Item lists per supplier

