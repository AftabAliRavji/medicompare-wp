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
           3. Subscription Overview
        --------------------------------------------------------- */

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
        $pending_url           = admin_url('admin.php?page=medicompare-pharmacy-verification');
        $orders_url            = admin_url('admin.php?page=medicompare-transferred-orders');
        $pharmacy_list_url     = admin_url('edit.php?post_type=mc_pharmacy');
        $commission_report_url = admin_url('admin.php?page=medicompare-reports&report_type=supplier_commission');

        /* ---------------------------------------------------------
           4. Supplier Commission + Order Value Overview
        --------------------------------------------------------- */

        $supplier_summary_table = $wpdb->prefix . 'medi_order_supplier_summary';

        // Get all suppliers
        $suppliers = $wpdb->get_results("
            SELECT ID, post_title
            FROM {$wpdb->posts}
            WHERE post_type = 'mc_supplier'
              AND post_status = 'publish'
        ");

        $labels = [];
        $commission_totals = [];
        $order_totals = [];

        foreach ($suppliers as $supplier) {

            $supplier_id = (int) $supplier->ID;

            // Commission total (only SENT)
            $commission_total = (float) $wpdb->get_var($wpdb->prepare("
                SELECT SUM(platform_fee_amount)
                FROM {$supplier_summary_table}
                WHERE supplier_id = %d
                  AND supplier_order_status = 'sent'
            ", $supplier_id));

            // Order value total (only SENT)
            $order_total = (float) $wpdb->get_var($wpdb->prepare("
                SELECT SUM(supplier_total_amount)
                FROM {$supplier_summary_table}
                WHERE supplier_id = %d
                  AND supplier_order_status = 'sent'
            ", $supplier_id));

            $labels[] = $supplier->post_title;
            $commission_totals[] = $commission_total ?: 0;
            $order_totals[] = $order_total ?: 0;
        }

        /* ---------------------------------------------------------
           5. Commission Email Overview
        --------------------------------------------------------- */

        $email_table = $wpdb->prefix . 'medi_supplier_commission_emails';

        $email_rows = [];

        foreach ($suppliers as $supplier) {

            $supplier_id = $supplier->ID;

            $count_sent = (int) $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$email_table}
                WHERE supplier_id = %d
            ", $supplier_id));

            $last_sent = $wpdb->get_var($wpdb->prepare("
                SELECT MAX(sent_at) FROM {$email_table}
                WHERE supplier_id = %d
            ", $supplier_id));

            $email_rows[] = [
                'name' => $supplier->post_title,
                'count' => $count_sent,
                'last' => $last_sent ? $last_sent : '—'
            ];
        }

        ?>

        <!-- FIRST ROW: 3 CARDS -->
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

        <!-- SECOND ROW: 2 CARDS -->
        <div class="mc-admin-dashboard-cards">

            <!-- Supplier Commission Overview -->
            <a href="<?php echo esc_url($commission_report_url); ?>" class="mc-admin-card mc-admin-card-primary">
                <div class="mc-admin-card-label">Supplier Commission Overview</div>

                <div style="overflow-x:auto; padding-bottom:10px;">
                    <canvas id="mc-supplier-commission-chart"
                        height="160"
                        style="min-width:220px; width:<?php echo max(220, count($suppliers) * 120); ?>px;">
                    </canvas>
                </div>

                <div class="mc-admin-card-footer">View supplier commission report →</div>
            </a>

            <!-- Commission Email Overview -->
            <div class="mc-admin-card mc-admin-card-green">

                <div class="mc-admin-card-label">Commission Email Overview</div>

                <div style="max-height:180px; overflow-y:auto; padding-right:10px;">

                    <?php foreach ($email_rows as $row): ?>
                        <div class="mc-admin-card-subtext">
                            <?php echo esc_html($row['name']); ?>:
                            <?php echo esc_html($row['count']); ?> sent —
                            Last: <?php echo esc_html($row['last']); ?>
                        </div>
                    <?php endforeach; ?>

                </div>

            </div>

        </div>

        <!-- Chart.js Graphs -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {

                /* -------------------------
                   Subscription Chart
                ------------------------- */
                const canvasSub = document.getElementById('mc-subscription-chart');
                if (canvasSub) {
                    const ctxSub = canvasSub.getContext('2d');

                    new Chart(ctxSub, {
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
                                    ticks: { precision: 0 }
                                }
                            }
                        }
                    });
                }

                /* -------------------------
                   Supplier Commission Chart
                ------------------------- */
                const canvasComm = document.getElementById('mc-supplier-commission-chart');
                if (canvasComm) {
                    const ctxComm = canvasComm.getContext('2d');

                    const commissionData = <?php echo json_encode($commission_totals); ?>;
                    const orderData      = <?php echo json_encode($order_totals); ?>;

                    new Chart(ctxComm, {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($labels); ?>,
                            datasets: [
                                {
                                    label: 'Commission (£)',
                                    data: commissionData,
                                    backgroundColor: '#4CAF50',
                                    yAxisID: 'commissionAxis'
                                },
                                {
                                    label: 'Total Sales (£)',
                                    data: orderData,
                                    backgroundColor: '#2196F3',
                                    yAxisID: 'orderAxis'
                                }
                            ]
                        },
                        options: {
                            responsive: false,
                            plugins: {
                                legend: { display: true }
                            },
                            scales: {
                                commissionAxis: {
                                    type: 'linear',
                                    position: 'left',
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Commission (£)'
                                    },
                                    max: Math.ceil(Math.max(...commissionData) * 1.3)
                                },
                                orderAxis: {
                                    type: 'linear',
                                    position: 'right',
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Total Sales (£)'
                                    },
                                    max: Math.ceil(Math.max(...orderData) * 1.3),
                                    grid: {
                                        drawOnChartArea: false
                                    }
                                }
                            }
                        }
                    });
                }

            });
        </script>

<?php
    }
}
