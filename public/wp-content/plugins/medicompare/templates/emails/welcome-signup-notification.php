<?php
/**
 * Welcome Signup Notification Email Template
 * Variables replaced dynamically:
 * {{pharmacy_name}}, {{contact_name}}, {{contact_number}}, {{contact_email}},
 * {{submitted_at}}
 */
?>
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif;">

<h2>New Pharmacy Signup Interest</h2>

<p>A pharmacy has registered interest in joining the MediCompare platform.</p>

<h3>Pharmacy Details</h3>
<ul>
    <li><strong>Pharmacy Name:</strong> {{pharmacy_name}}</li>
    <li><strong>Contact Name:</strong> {{contact_name}}</li>
    <li><strong>Contact Number:</strong> {{contact_number}}</li>
    <li><strong>Contact Email:</strong> {{contact_email}}</li>
    <li><strong>Submitted At:</strong> {{submitted_at}}</li>
</ul>

<p>Please reach out to begin onboarding.</p>

</body>
</html>
