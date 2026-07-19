<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'mc_interest';

$rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
?>

<div class="wrap">
    <h1>Signup Leads</h1>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>Pharmacy Name</th>
                <th>Contact Name</th>
                <th>Contact Number</th>
                <th>Contact Email</th>
                <th>Submitted At</th>
            </tr>
        </thead>

        <tbody>
            <?php if ($rows): ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo esc_html($r->pharmacy_name); ?></td>
                        <td><?php echo esc_html($r->contact_name); ?></td>
                        <td><?php echo esc_html($r->contact_number); ?></td>
                        <td><?php echo esc_html($r->contact_email); ?></td>
                        <td><?php echo esc_html($r->created_at); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">No leads found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
