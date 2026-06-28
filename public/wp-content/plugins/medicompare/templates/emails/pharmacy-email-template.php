<?php
/**
 * Pharmacy Confirmation Email Template
 * Variables replaced dynamically:
 * {{order_number}}, {{order_date}}, {{pharmacy_name}},
 * {{supplier_breakdown_table}}, {{full_items_table}}, {{grand_total}}
 */
?>
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif;">

<h2>Your Order Has Been Placed — Order {{order_number}}</h2>

<p>Hello {{pharmacy_name}},</p>

<p>Your order has been successfully placed with all suppliers.</p>

<h3>Order Summary</h3>
<ul>
    <li><strong>Order Number:</strong> {{order_number}}</li>
    <li><strong>Order Date:</strong> {{order_date}}</li>
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

<h3>Full Item List</h3>
<table border="1" cellpadding="6" cellspacing="0" width="100%" style="border-collapse: collapse;">
    <thead>
        <tr>
            <th align="left">Product</th>
            <th align="left">Supplier</th>
            <th align="left">Qty</th>
            <th align="left">Unit Price</th>
            <th align="left">Line Total</th>
        </tr>
    </thead>
    <tbody>
        {{full_items_table}}
    </tbody>
</table>

<h3>Grand Total</h3>
<p><strong>£{{grand_total}}</strong></p>

<p>Thank you for using MediCompare.</p>

</body>
</html>
