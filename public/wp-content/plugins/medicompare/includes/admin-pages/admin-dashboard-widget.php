<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Admin_Dashboard_Widget {

    public static function render_inline_widget() {
        global $wpdb;

        /* ---------------------------------------------------------
           1. Pending pharmacy verifications
        --------------------------------------------------------- */
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

        /* ---------------------------------------------------------
           2. Transferred orders
        --------------------------------------------------------- */
        $orders_table = $wpdb->prefix . 'medi_orders';
        $transferred_count = (int) $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$orders_table}
            WHERE status IN ('TRANSFERRED','SENT')
        ");

        /* ---------------------------------------------------------
           3. Subscription Overview (NEW)
        --------------------------------------------------------- */

        // Get all published pharmacies
        $pharmacies = $wpdb->get_results("
            SELECT ID 
            FROM {$wpdb->posts}
            WHERE post_type = 'mc_pharmacy'
              AND post_status = 'publish'
        ");

        $trial    = 0;
        $active   = 0;
        $expired  = 0;
        $past_due = 0;
        $canceled = 0;

        foreach ($pharmacies as $p) {

            $status     = get_post_meta($p->ID, '_mc_subscription_status', true);
            $trial_end  = (int) get_post_meta($p->ID, '_mc_trial_end', true);
            $next_billing = (int) get_post_meta($p->ID, '_mc_next_billing_date', true);

            $now = time();

            if ($status === 'trial') {
                if ($trial_end > $now) {
                    $trial++;
                } else {
                    $expired++;
                }
            }
            elseif ($status === 'active') {

                if ($trial_end > $now) {
                    $trial++;
                }
                elseif ($next_billing > $now || $next_billing === 0) {
                    $active++;
                }
                else {
                    $past_due++;
                }
            }
            elseif ($status === 'expired') {
                $expired++;
            }
            elseif ($status === 'past_due') {
                $past_due++;
            }
            elseif ($status === 'canceled') {
                $canceled++;
            }
        }

        // Admin URLs
        $pending_url        = admin_url('admin.php?page=medicompare-pharmacy-verification');
        $orders_url         = admin_url('admin.php?page=medicompare-transferred-orders');
        $pharmacy_list_url  = admin_url('edit.php?post_type=mc_pharmacy');
        ?>

        <div class="mc-admin-dashboard-cards">

            <!-- Pending Verifications -->
            <a href="<?php echo esc_url($pending_url); ?>" class="mc-admin-card mc-admin-card-warning">
                <div class="mc-admin-card-label">Pending Pharmacy Verifications</div>
                <div class="mc-admin-card-value"><?php echo esc_html($pending_count); ?></div>
                <div class="mc-admin-card-footer">View pending verifications →</div>
            </a>

            <!-- Transferred Orders -->
            <a href="<?php echo esc_url($orders_url); ?>" class="mc-admin-card mc-admin-card-primary">
                <div class="mc-admin-card-label">Transferred Orders</div>
                <div class="mc-admin-card-value"><?php echo esc_html($transferred_count); ?></div>
                <div class="mc-admin-card-footer">View transferred orders →</div>
            </a>

            <!-- Subscription Overview -->
            <a href="<?php echo esc_url($pharmacy_list_url); ?>" class="mc-admin-card mc-admin-card-green">
                <div class="mc-admin-card-label">Pharmacy Subscription Overview</div>

                <canvas id="mc-subscription-chart" width="220" height="120"></canvas>

                <div class="mc-admin-card-footer">View all pharmacies →</div>
            </a>

        </div>

        <!-- Chart.js Graph -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {

                const canvas = document.getElementById('mc-subscription-chart');
                if (!canvas) return;

                const ctx = canvas.getContext('2d');

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Trial', 'Active', 'Expired', 'Past Due', 'Canceled'],
                        datasets: [{
                            label: 'Pharmacies',
                            data: [
                                <?php echo (int) $trial; ?>,
                                <?php echo (int) $active; ?>,
                                <?php echo (int) $expired; ?>,
                                <?php echo (int) $past_due; ?>,
                                <?php echo (int) $canceled; ?>
                            ],
                            backgroundColor: [
                                '#4CAF50',
                                '#2196F3',
                                '#F44336',
                                '#FF9800',
                                '#9C27B0'
                            ]
                        }]
                    },
                    options: {
                        responsive: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });

            });
        </script>

        <?php
    }
}
