<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Admin_Dashboard_Widget {

    public static function render_inline_widget() {
        global $wpdb;

        // Pending pharmacy verifications (mc_pharmacy with _mc_status = pending_verification)
        $pending_count = (int) (new WP_Query([
            'post_type'      => 'mc_pharmacy',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_mc_status',
                    'value' => 'pending_verification'
                ]
            ]
        ]))->found_posts;

        // Transferred orders (all orders that have left pending state)
        $orders_table = $wpdb->prefix . 'medi_orders';
        $transferred_count = (int) $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$orders_table}
            WHERE status IN ('TRANSFERRED','SENT')
        ");

        // Admin URLs
        $pending_url    = admin_url('admin.php?page=medicompare-pharmacy-verification');
        $orders_url     = admin_url('admin.php?page=medicompare-transferred-orders');
        ?>

        <div class="mc-admin-dashboard-cards">

            <a href="<?php echo esc_url($pending_url); ?>" class="mc-admin-card mc-admin-card-warning">
                <div class="mc-admin-card-label">Pending Pharmacy Verifications</div>
                <div class="mc-admin-card-value"><?php echo esc_html($pending_count); ?></div>
                <div class="mc-admin-card-footer">View pending verifications →</div>
            </a>

            <a href="<?php echo esc_url($orders_url); ?>" class="mc-admin-card mc-admin-card-primary">
                <div class="mc-admin-card-label">Transferred Orders</div>
                <div class="mc-admin-card-value"><?php echo esc_html($transferred_count); ?></div>
                <div class="mc-admin-card-footer">View transferred orders →</div>
            </a>

        </div>

        <?php
    }
}
