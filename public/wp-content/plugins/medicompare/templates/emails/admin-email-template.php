<?php
/**
 * Admin Notification Email Template
 * Variables replaced dynamically:
 * {{pharmacy_name}}, {{pharmacy_email}}, {{pharmacy_phone}},
 * {{order_number}}, {{order_date}},
 * {{supplier_breakdown_table}}, {{grand_total}},
 * {{admin_order_link}}, {{admin_pharmacy_link}}
 */
?>
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif;">

<h2>New Pharmacy Order — {{pharmacy_name}}</h2>

<h3>Order Details</h3>
<ul>
    <li><strong>Order Number:</strong> {{order_number}}</li>
    <li><strong>Order Date:</strong> {{order_date}}</li>
</ul>

<h3>Pharmacy Information</h3>
<ul>
    <li><strong>Name:</strong> {{pharmacy_name}}</li>
    <li><strong>Email:</strong> {{pharmacy_email}}</li>
    <li><strong>Phone:</strong> {{pharmacy_phone}}</li>
</ul>

<h3>Supplier Breakdown</h3>
<table border="1" cellpadding="6" cellspacing="0" width="100%" style="border-collapse: collapse;">
    <thead>
        <tr>
            <th align="left">Supplier</th>
            <th align="left">Sub‑Order</th>
            <th align="left">Supplier Total</th>
        </tr>
    </thead>
    <tbody>
        {{supplier_breakdown_table}}
    </tbody>
</table>

<h3>Grand Total</h3>
<p><strong>£{{grand_total}}</strong></p>

<h3>Admin Links</h3>
<ul>
    <li><a href="{{admin_order_link}}">View Order in Admin</a></li>
    <li><a href="{{admin_pharmacy_link}}">View Pharmacy in Admin</a></li>
</ul>

</body>
</html>
