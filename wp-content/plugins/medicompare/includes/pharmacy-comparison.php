<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Pharmacy_Comparison {

    public function __construct() {

        // AJAX: Search
        add_action('wp_ajax_mc_search_products', [$this, 'ajax_search_products']);

        // AJAX: Pending order
        add_action('wp_ajax_mc_add_pending_item', [$this, 'ajax_add_pending_item']);
        add_action('wp_ajax_mc_get_pending_order', [$this, 'ajax_get_pending_order']);
        add_action('wp_ajax_mc_remove_pending_item', [$this, 'ajax_remove_pending_item']);

        // AJAX: Transfer order
        add_action('wp_ajax_mc_transfer_order', [$this, 'ajax_transfer_order']);

        // AJAX: Transferred orders (NEW)
        add_action('wp_ajax_mc_get_transferred_orders', [$this, 'ajax_get_transferred_orders']);
    }

    /* ---------------------------------------------------------
       HELPERS
    --------------------------------------------------------- */

    private function get_current_pharmacy_id() {
        if (!is_user_logged_in()) return null;

        $user = wp_get_current_user();
        if (!in_array('pharmacy_user', $user->roles)) return null;

        $pharmacy_id = get_user_meta($user->ID, '_mc_pharmacy_id', true);
        return $pharmacy_id ?: null;
    }

    private function get_or_create_pending_order($pharmacy_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'medi_pending_orders';

        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE pharmacy_id = %d LIMIT 1",
            $pharmacy_id
        ));

        if ($existing_id) return (int) $existing_id;

        $wpdb->insert($table, [
            'pharmacy_id' => $pharmacy_id,
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        ]);

        return (int) $wpdb->insert_id;
    }

    /* ---------------------------------------------------------
       AJAX: SEARCH PRODUCTS
    --------------------------------------------------------- */

    public function ajax_search_products() {
        check_ajax_referer('mc_comparison_nonce', 'nonce');

        $pharmacy_id = $this->get_current_pharmacy_id();
        if (!$pharmacy_id) wp_send_json_error(['message' => 'Not authorised.']);

        $q = isset($_POST['q']) ? trim(wp_unslash($_POST['q'])) : '';
        if (strlen($q) < 3) wp_send_json_error(['message' => 'Type at least 3 characters.']);

        global $wpdb;

        $supplier_products_table = $wpdb->prefix . 'medi_supplier_products';
        $posts_table             = $wpdb->posts;
        $postmeta_table          = $wpdb->postmeta;

        $sql = "
            SELECT
                sp.id AS supplier_product_id,
                sp.supplier_id,
                sp.product_id,
                sp.price,
                sp.stock,
                p.post_title AS product_name,
                s.post_title AS supplier_name,
                MAX(CASE WHEN pm.meta_key = 'mc_strength' THEN pm.meta_value END) AS strength,
                MAX(CASE WHEN pm.meta_key = 'mc_pack_size' THEN pm.meta_value END) AS pack_size,
                MAX(CASE WHEN pm.meta_key = 'mc_description' THEN pm.meta_value END) AS description
            FROM {$supplier_products_table} sp
            INNER JOIN {$posts_table} p ON p.ID = sp.product_id
            LEFT JOIN {$postmeta_table} pm ON pm.post_id = sp.product_id
            INNER JOIN {$posts_table} s ON s.ID = sp.supplier_id
            WHERE p.post_type = 'mc_product'
              AND p.post_status = 'publish'
              AND (
                    p.post_title LIKE %s
                    OR EXISTS (
                        SELECT 1 FROM {$postmeta_table} pm2
                        WHERE pm2.post_id = sp.product_id
                          AND pm2.meta_key = 'mc_product_code'
                          AND pm2.meta_value LIKE %s
                    )
                  )
            GROUP BY sp.id
            ORDER BY sp.price ASC
            LIMIT 50
        ";

        $like = '%' . $wpdb->esc_like($q) . '%';

        $rows = $wpdb->get_results($wpdb->prepare($sql, $like, $like), ARRAY_A);

        if (!$rows) wp_send_json_success(['html' => '<p>No matching products found.</p>']);

        ob_start();
        ?>
        <table class="mc-search-results-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Details</th>
                    <th>Supplier</th>
                    <th>Unit Price</th>
                    <th>Stock</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr class="mc-search-row"
                    data-supplier-product-id="<?php echo esc_attr($row['supplier_product_id']); ?>"
                    data-product-id="<?php echo esc_attr($row['product_id']); ?>"
                    data-supplier-id="<?php echo esc_attr($row['supplier_id']); ?>"
                    data-unit-price="<?php echo esc_attr($row['price']); ?>">

                    <td><?php echo esc_html($row['product_name']); ?></td>

                    <td>
                        <?php
                        $details = [];
                        if ($row['strength'])   $details[] = esc_html($row['strength']);
                        if ($row['pack_size'])  $details[] = esc_html($row['pack_size']);
                        if ($row['description']) $details[] = esc_html($row['description']);
                        echo implode(' | ', $details);
                        ?>
                    </td>

                    <td><?php echo esc_html($row['supplier_name']); ?></td>
                    <td>£<?php echo number_format((float) $row['price'], 2); ?></td>
                    <td><?php echo (int) $row['stock']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php

        wp_send_json_success(['html' => ob_get_clean()]);
    }
    /* ---------------------------------------------------------
       AJAX: ADD ITEM TO PENDING ORDER
    --------------------------------------------------------- */

    public function ajax_add_pending_item() {
        check_ajax_referer('mc_comparison_nonce', 'nonce');

        $pharmacy_id = $this->get_current_pharmacy_id();
        if (!$pharmacy_id) wp_send_json_error(['message' => 'Not authorised.']);

        $product_id  = (int) ($_POST['product_id'] ?? 0);
        $supplier_id = (int) ($_POST['supplier_id'] ?? 0);
        $unit_price  = (float) ($_POST['unit_price'] ?? 0);
        $quantity    = (int) ($_POST['quantity'] ?? 1);

        if ($product_id <= 0 || $supplier_id <= 0 || $unit_price <= 0 || $quantity <= 0) {
            wp_send_json_error(['message' => 'Invalid item data.']);
        }

        global $wpdb;

        $pending_order_id = $this->get_or_create_pending_order($pharmacy_id);

        $items_table = $wpdb->prefix . 'medi_pending_order_items';

        $wpdb->insert($items_table, [
            'pending_order_id' => $pending_order_id,
            'product_id'       => $product_id,
            'supplier_id'      => $supplier_id,
            'quantity'         => $quantity,
            'unit_price'       => $unit_price,
            'line_total'       => $unit_price * $quantity,
        ]);

        wp_send_json_success(['message' => 'Item added to pending order.']);
    }

    /* ---------------------------------------------------------
       RENDER PENDING ORDER HTML
    --------------------------------------------------------- */

    private function render_pending_order_html($pharmacy_id) {
        global $wpdb;

        $pending_orders_table = $wpdb->prefix . 'medi_pending_orders';
        $pending_items_table  = $wpdb->prefix . 'medi_pending_order_items';
        $posts_table          = $wpdb->posts;

        $pending_order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$pending_orders_table} WHERE pharmacy_id = %d LIMIT 1",
            $pharmacy_id
        ));

        if (!$pending_order_id) {
            return '<p>No pending order. Add items from the search results.</p>';
        }

        $sql = "
            SELECT
                i.id,
                i.product_id,
                i.supplier_id,
                i.quantity,
                i.unit_price,
                i.line_total,
                p.post_title AS product_name,
                s.post_title AS supplier_name
            FROM {$pending_items_table} i
            INNER JOIN {$posts_table} p ON p.ID = i.product_id
            INNER JOIN {$posts_table} s ON s.ID = i.supplier_id
            WHERE i.pending_order_id = %d
            ORDER BY s.post_title ASC, p.post_title ASC
        ";

        $items = $wpdb->get_results($wpdb->prepare($sql, $pending_order_id), ARRAY_A);

        if (!$items) {
            return '<p>No items in pending order.</p>';
        }

        // Group by supplier
        $grouped = [];
        $overall_total = 0;

        foreach ($items as $item) {
            $sid = $item['supplier_id'];

            if (!isset($grouped[$sid])) {
                $grouped[$sid] = [
                    'supplier_name' => $item['supplier_name'],
                    'items'         => [],
                    'supplier_total'=> 0,
                ];
            }

            $grouped[$sid]['items'][] = $item;
            $grouped[$sid]['supplier_total'] += $item['line_total'];
            $overall_total += $item['line_total'];
        }

        ob_start();
        ?>
        <div class="mc-pending-order-wrapper">
            <?php foreach ($grouped as $supplier_id => $data): ?>
                <div class="mc-pending-supplier-block">
                    <h3><?php echo esc_html($data['supplier_name']); ?></h3>

                    <table class="mc-pending-order-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Line Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['items'] as $item): ?>
                            <tr>
                                <td><?php echo esc_html($item['product_name']); ?></td>
                                <td><?php echo (int) $item['quantity']; ?></td>
                                <td>£<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>£<?php echo number_format($item['line_total'], 2); ?></td>
                                <td>
                                    <button class="mc-remove-pending-item"
                                            data-item-id="<?php echo esc_attr($item['id']); ?>">
                                        ✕
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="mc-supplier-total">
                        Supplier total: £<?php echo number_format($data['supplier_total'], 2); ?>
                    </p>
                </div>
            <?php endforeach; ?>

            <p class="mc-overall-total">
                Overall total: £<?php echo number_format($overall_total, 2); ?>
            </p>
        </div>
        <?php

        return ob_get_clean();
    }

    /* ---------------------------------------------------------
       AJAX: GET PENDING ORDER
    --------------------------------------------------------- */

    public function ajax_get_pending_order() {
        check_ajax_referer('mc_comparison_nonce', 'nonce');

        $pharmacy_id = $this->get_current_pharmacy_id();
        if (!$pharmacy_id) wp_send_json_error(['message' => 'Not authorised.']);

        wp_send_json_success([
            'html' => $this->render_pending_order_html($pharmacy_id)
        ]);
    }

    /* ---------------------------------------------------------
       AJAX: REMOVE ITEM FROM PENDING ORDER
    --------------------------------------------------------- */

    public function ajax_remove_pending_item() {
        check_ajax_referer('mc_comparison_nonce', 'nonce');

        $pharmacy_id = $this->get_current_pharmacy_id();
        if (!$pharmacy_id) wp_send_json_error(['message' => 'Not authorised.']);

        $item_id = (int) ($_POST['item_id'] ?? 0);
        if ($item_id <= 0) wp_send_json_error(['message' => 'Invalid item.']);

        global $wpdb;

        $items_table = $wpdb->prefix . 'medi_pending_order_items';
        $pending_orders_table = $wpdb->prefix . 'medi_pending_orders';

        // Ensure item belongs to this pharmacy
        $pending_order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT po.id
             FROM {$pending_orders_table} po
             INNER JOIN {$items_table} i ON i.pending_order_id = po.id
             WHERE po.pharmacy_id = %d AND i.id = %d
             LIMIT 1",
            $pharmacy_id,
            $item_id
        ));

        if (!$pending_order_id) wp_send_json_error(['message' => 'Item not found.']);

        $wpdb->delete($items_table, ['id' => $item_id], ['%d']);

        wp_send_json_success(['message' => 'Item removed.']);
    }
    /* ---------------------------------------------------------
       AJAX: TRANSFER ORDER
    --------------------------------------------------------- */

    public function ajax_transfer_order() {
        check_ajax_referer('mc_comparison_nonce', 'nonce');

        $pharmacy_id = $this->get_current_pharmacy_id();
        if (!$pharmacy_id) {
            wp_send_json_error(['message' => 'Not authorised.']);
        }

        global $wpdb;

        $pending_orders_table     = $wpdb->prefix . 'medi_pending_orders';
        $pending_items_table      = $wpdb->prefix . 'medi_pending_order_items';
        $orders_table             = $wpdb->prefix . 'medi_orders';
        $order_items_table        = $wpdb->prefix . 'medi_order_items';
        $supplier_summary_table   = $wpdb->prefix . 'medi_order_supplier_summary';

        // Get pending order
        $pending_order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$pending_orders_table} WHERE pharmacy_id = %d LIMIT 1",
            $pharmacy_id
        ));

        if (!$pending_order_id) {
            wp_send_json_error(['message' => 'No pending order to transfer.']);
        }

        // Get items
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$pending_items_table} WHERE pending_order_id = %d",
            $pending_order_id
        ), ARRAY_A);

        if (!$items) {
            wp_send_json_error(['message' => 'Pending order is empty.']);
        }

        // Calculate totals
        $overall_total = 0;
        $supplier_totals = [];

        foreach ($items as $item) {
            $overall_total += (float) $item['line_total'];

            $sid = (int) $item['supplier_id'];
            if (!isset($supplier_totals[$sid])) {
                $supplier_totals[$sid] = 0;
            }
            $supplier_totals[$sid] += (float) $item['line_total'];
        }

        // Create final order
        $wpdb->insert($orders_table, [
            'pharmacy_id'  => $pharmacy_id,
            'total_amount' => $overall_total,
            'status'       => 'transferred',
            'created_at'   => current_time('mysql'),
        ]);

        $order_id = (int) $wpdb->insert_id;

        // Move items into final order
        foreach ($items as $item) {
            $wpdb->insert($order_items_table, [
                'order_id'    => $order_id,
                'product_id'  => (int) $item['product_id'],
                'supplier_id' => (int) $item['supplier_id'],
                'quantity'    => (int) $item['quantity'],
                'unit_price'  => (float) $item['unit_price'],
                'line_total'  => (float) $item['line_total'],
                'supplier_cost_price' => null,
                'supplier_profit'     => null,
            ]);
        }

        // Create supplier summaries
        foreach ($supplier_totals as $supplier_id => $supplier_total) {
            $wpdb->insert($supplier_summary_table, [
                'order_id'              => $order_id,
                'supplier_id'           => $supplier_id,
                'supplier_total_amount' => $supplier_total,
                'platform_fee_percent'  => 0.00,
                'platform_fee_amount'   => 0.00,
                'supplier_order_status' => 'pending',
                'created_at'            => current_time('mysql'),
            ]);
        }

        // Clear pending order + items
        $wpdb->delete($pending_items_table, ['pending_order_id' => $pending_order_id]);
        $wpdb->delete($pending_orders_table, ['id' => $pending_order_id]);

        wp_send_json_success(['message' => 'Order transferred successfully.']);
    }
    /* ---------------------------------------------------------
       AJAX: GET TRANSFERRED ORDERS (NEW)
    --------------------------------------------------------- */

    public function ajax_get_transferred_orders() {
        check_ajax_referer('mc_comparison_nonce', 'nonce');

        $pharmacy_id = $this->get_current_pharmacy_id();
        if (!$pharmacy_id) {
            wp_send_json_error(['message' => 'Not authorised.']);
        }

        global $wpdb;

        $orders_table            = $wpdb->prefix . 'medi_orders';
        $supplier_summary_table  = $wpdb->prefix . 'medi_order_supplier_summary';
        $posts_table             = $wpdb->posts;

        // Fetch transferred orders
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT id, total_amount, created_at
             FROM {$orders_table}
             WHERE pharmacy_id = %d
               AND status = 'transferred'
             ORDER BY created_at DESC
             LIMIT 20",
            $pharmacy_id
        ), ARRAY_A);

        if (!$orders) {
            wp_send_json_success(['html' => '<p>No transferred orders yet.</p>']);
        }

        ob_start();
        ?>
        <div class="mc-transferred-orders-wrapper">
            <?php foreach ($orders as $order): ?>
                <div class="mc-transferred-order-card">

                    <h3>Order #<?php echo esc_html($order['id']); ?></h3>

                    <p><strong>Date:</strong>
                        <?php echo esc_html(date('d M Y H:i', strtotime($order['created_at']))); ?>
                    </p>

                    <p><strong>Total:</strong>
                        £<?php echo number_format((float) $order['total_amount'], 2); ?>
                    </p>

                    <details>
                        <summary>Supplier Breakdown</summary>

                        <?php
                        $suppliers = $wpdb->get_results($wpdb->prepare(
                            "SELECT supplier_id, supplier_total_amount
                             FROM {$supplier_summary_table}
                             WHERE order_id = %d",
                            $order['id']
                        ), ARRAY_A);

                        if ($suppliers):
                        ?>
                            <ul>
                                <?php foreach ($suppliers as $s): ?>
                                    <?php
                                    // Get supplier name
                                    $supplier_name = $wpdb->get_var($wpdb->prepare(
                                        "SELECT post_title FROM {$posts_table} WHERE ID = %d",
                                        $s['supplier_id']
                                    ));
                                    ?>
                                    <li>
                                        <?php echo esc_html($supplier_name ?: 'Supplier #' . $s['supplier_id']); ?>
                                        — £<?php echo number_format((float) $s['supplier_total_amount'], 2); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>No supplier breakdown available.</p>
                        <?php endif; ?>

                    </details>

                </div>
            <?php endforeach; ?>
        </div>
        <?php

        wp_send_json_success(['html' => ob_get_clean()]);
    }

} // END CLASS

new MediCompare_Pharmacy_Comparison();
