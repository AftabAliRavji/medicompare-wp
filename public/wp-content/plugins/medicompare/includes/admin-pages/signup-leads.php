<?php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$table = $wpdb->prefix . 'mc_interest';
$deleted_success = false;

// Handle Delete Request
if (
    isset($_GET['delete_lead']) &&
    isset($_GET['_wpnonce']) &&
    current_user_can('manage_options')
) {
    $lead_id = intval($_GET['delete_lead']);

    // Verify nonce matches the action name used in the delete link
    if (wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'delete_signup_lead_' . $lead_id)) {
        
        $wpdb->delete(
            $table,
            ['id' => $lead_id],
            ['%d']
        );

        $deleted_success = true;
    }
}

// Fetch records AFTER deletion so the table instantly shows updated data
$rows = $wpdb->get_results(
    "SELECT * FROM {$table} ORDER BY created_at DESC"
);
?>

<div class="wrap">

    <h1>Signup Leads</h1>

    <?php if ($deleted_success): ?>
        <div class="notice notice-success is-dismissible"><p>Lead deleted successfully.</p></div>
    <?php endif; ?>

    <p>
        <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=mc_export_signup' ) ); ?>" class="button button-primary">Download CSV</a>
    </p>

    <table class="widefat fixed striped">

        <thead>
            <tr>
                <th>Pharmacy Name</th>
                <th>Pharmacy Postcode</th>
                <th>Contact Name</th>
                <th>Contact Number</th>
                <th>Contact Email</th>
                <th>Submitted At</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>

            <?php if ($rows): ?>

                <?php foreach ($rows as $r): ?>

                    <tr>
                        <td><?php echo esc_html($r->pharmacy_name); ?></td>
                        <td><?php echo esc_html($r->pharmacy_postcode); ?></td>
                        <td><?php echo esc_html($r->contact_name); ?></td>
                        <td><?php echo esc_html($r->contact_number); ?></td>
                        <td><?php echo esc_html($r->contact_email); ?></td>
                        <td><?php echo esc_html($r->created_at); ?></td>
                        <td>
                            <?php 
                            // Build delete URL with action name matching wp_verify_nonce
                            $delete_url = wp_nonce_url(
                                admin_url('admin.php?page=medicompare-signup-leads&delete_lead=' . intval($r->id)),
                                'delete_signup_lead_' . intval($r->id)
                            );
                            ?>
                            <a href="<?php echo esc_url($delete_url); ?>" 
                               onclick="return confirm('Are you sure you want to delete this lead?');" 
                               style="color: #a00;">
                                Delete
                            </a>
                        </td>
                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="7">No leads found.</td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>