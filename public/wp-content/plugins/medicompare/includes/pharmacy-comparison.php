<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Pharmacy_Comparison {

    public function __construct() {

        // AJAX: Search
        add_action('wp_ajax_mc_search_products', [$this, 'ajax_search_products']);
        add_action('wp_ajax_mc_get_product_suppliers', [$this, 'ajax_get_product_suppliers']);

        // AJAX: Pending order
        add_action('wp_ajax_mc_add_pending_item', [$this, 'ajax_add_pending_item']);
        add_action('wp_ajax_mc_get_pending_order', [$this, 'ajax_get_pending_order']);
        add_action('wp_ajax_mc_remove_pending_item', [$this, 'ajax_remove_pending_item']);
        // AJAX: Cancel entire pending order (NEW)
        add_action('wp_ajax_mc_cancel_pending_order', [$this, 'ajax_cancel_pending_order']);


        // NEW: Update qty in pending order
        add_action('wp_ajax_mc_update_pending_qty', [$this, 'ajax_update_pending_qty']);

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
       BUILD FULL PRODUCT LABEL (NAME + PACK SIZE + STRENGTH)
    --------------------------------------------------------- */
    private function mc_get_full_product_label($product_id) {
        $name      = get_the_title($product_id);
        $pack_size = get_post_meta($product_id, 'mc_pack_size', true);
        $strength  = get_post_meta($product_id, 'mc_strength', true);

        $label = $name;

        if ($pack_size || $strength) {
            $label .= ' (';

            if ($pack_size) {
                $label .= $pack_size;
            }

            if ($pack_size && $strength) {
                $label .= ', ';
            }

            if ($strength) {
                $label .= $strength;
            }

            $label .= ')';
        }

        return $label;
    }

    /* ---------------------------------------------------------
       AJAX: SEARCH PRODUCTS (PRODUCT LIST ONLY)
       - prefix match first, then broad match
    --------------------------------------------------------- */
   public function ajax_search_products() {
    check_ajax_referer('mc_comparison_nonce', 'nonce');

    global $wpdb;

    $posts_table    = $wpdb->posts;
    $postmeta_table = $wpdb->postmeta;

    // Optional: force a specific product (from fuzzy click, Option 3)
    $force_product_id = isset($_POST['force_product_id']) ? intval($_POST['force_product_id']) : 0;

    // If a specific product is forced, bypass normal search and return that one row
    if ($force_product_id > 0) {
        $product = $wpdb->get_row(
            $wpdb->prepare("
                SELECT ID, post_title
                FROM {$posts_table}
                WHERE ID = %d
                  AND post_type = 'mc_product'
                  AND post_status = 'publish'
                LIMIT 1
            ", $force_product_id),
            ARRAY_A
        );

        if (!$product) {
            wp_send_json_success(['html' => '<p>No matching products found.</p>']);
        }

        ob_start();
        ?>
        <table class="mc-search-results-table mc-product-list-table">
            <thead>
                <tr>
                    <th>Product</th>
                </tr>
            </thead>
            <tbody>
                <?php $full_label = $this->mc_get_full_product_label($product['ID']); ?>
                <tr class="mc-product-row"
                    data-product-id="<?php echo esc_attr($product['ID']); ?>">
                    <td><?php echo esc_html($full_label); ?></td>
                </tr>
            </tbody>
        </table>
        <?php
        wp_send_json_success(['html' => ob_get_clean()]);
    }

    // Normal search path
    $q = isset($_POST['q']) ? trim(wp_unslash($_POST['q'])) : '';
    if (strlen($q) < 3) {
        wp_send_json_error(['message' => 'Type at least 3 characters.']);
    }

    /* ---------------------------------------------------------
       CLEAN QUERY (remove special chars + normalise units)
    --------------------------------------------------------- */
    $q_clean = strtolower($q);

    // Convert "100g" → "100 g", "200mg" → "200 mg"
    $q_clean = preg_replace('/([0-9]+)([a-zA-Z]+)/', '$1 $2', $q_clean);

    // Remove all non-alphanumeric except spaces
    $q_clean = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $q_clean);

    /* ---------------------------------------------------------
       TOKENISE QUERY (ibu gel 100 → ["ibu","gel","100"])
    --------------------------------------------------------- */
    $tokens = preg_split('/\s+/', $q_clean, -1, PREG_SPLIT_NO_EMPTY);

    if (!$tokens) {
        wp_send_json_success(['html' => '<p>No matching products found.</p>']);
    }

    /* ---------------------------------------------------------
       BUILD TOKEN CONDITIONS
       - Word tokens: prefix OR contains on post_title
       - Numeric tokens: contains on post_title OR any meta_value
    --------------------------------------------------------- */
    $conditions      = [];
    $prepare_values  = [];

    foreach ($tokens as $t) {
        $prefix   = $wpdb->esc_like($t) . '%';
        $contains = '%' . $wpdb->esc_like($t) . '%';

        if (ctype_digit($t)) {
            // Numeric token (e.g. "50") → match title OR any meta_value
            $conditions[] = "
                (
                    LOWER(p.post_title) LIKE %s
                    OR EXISTS (
                        SELECT 1
                        FROM {$postmeta_table} pm
                        WHERE pm.post_id = p.ID
                          AND pm.meta_value LIKE %s
                    )
                )
            ";
            $prepare_values[] = $contains; // title
            $prepare_values[] = $contains; // meta
        } else {
            // Word token → prefix OR contains on title
            $conditions[] = "
                (
                    LOWER(p.post_title) LIKE %s
                    OR LOWER(p.post_title) LIKE %s
                )
            ";
            $prepare_values[] = $prefix;
            $prepare_values[] = $contains;
        }
    }

    $where_tokens = implode(" AND ", $conditions);

    /* ---------------------------------------------------------
       RANKING RULES
       1 = starts with first token
       2 = contains first token
       3 = fallback
    --------------------------------------------------------- */
    $first_token = $tokens[0];

    $prefix_like = $wpdb->esc_like($first_token) . '%';
    $broad_like  = '%' . $wpdb->esc_like($first_token) . '%';

    $sql_products = "
        SELECT
            p.ID AS product_id,
            p.post_title AS product_name,
            CASE
                WHEN LOWER(p.post_title) LIKE %s THEN 1
                WHEN LOWER(p.post_title) LIKE %s THEN 2
                ELSE 3
            END AS relevance_score
        FROM {$posts_table} p
        WHERE p.post_type = 'mc_product'
          AND p.post_status = 'publish'
          AND ( {$where_tokens} )
        ORDER BY relevance_score ASC, p.post_title ASC
        LIMIT 50
    ";

    $prepare = array_merge(
        [ $prefix_like, $broad_like ],
        $prepare_values
    );

    $products = $wpdb->get_results(
        $wpdb->prepare($sql_products, $prepare),
        ARRAY_A
    );

    /* ---------------------------------------------------------
       IF EXACT RESULTS FOUND → RETURN THEM
    --------------------------------------------------------- */
    if ($products) {
        ob_start();
        ?>
        <table class="mc-search-results-table mc-product-list-table">
            <thead>
                <tr>
                    <th>Product</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $row): ?>
                <?php $full_label = $this->mc_get_full_product_label($row['product_id']); ?>
                <tr class="mc-product-row"
                    data-product-id="<?php echo esc_attr($row['product_id']); ?>">
                    <td><?php echo esc_html($full_label); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        wp_send_json_success(['html' => ob_get_clean()]);
    }

    /* ---------------------------------------------------------
       FUZZY FALLBACK (Corrected)
       Only runs when exact results = 0
--------------------------------------------------------- */

$all_products = $wpdb->get_results("
    SELECT ID, post_title
    FROM {$posts_table}
    WHERE post_type = 'mc_product'
      AND post_status = 'publish'
    LIMIT 300
", ARRAY_A);

$distances = [];

foreach ($all_products as $p) {

    // Extract FIRST WORD ONLY (critical fix)
    // Example: "Paracetamol 500 mg tablets" → "paracetamol"
    $first_word = strtolower(
        preg_replace('/[^a-zA-Z]/', '', strtok($p['post_title'], ' '))
    );

    if (!$first_word) continue;

    // Compute Levenshtein distance against the first word
    $distance = levenshtein($q_clean, $first_word);

    // Prefix bonus: improves ranking for near matches
    $prefix_bonus = 0;
    if (strpos($first_word, substr($q_clean, 0, 2)) === 0) {
        $prefix_bonus = -2;
    }

    $distances[] = [
        'id'       => $p['ID'],
        'title'    => $p['post_title'],
        'distance' => $distance + $prefix_bonus
    ];
}

// Sort by corrected distance
usort($distances, function($a, $b) {
    return $a['distance'] - $b['distance'];
});

// Take top 3 suggestions
$suggestions = array_slice($distances, 0, 3);

ob_start();
?>
<p>No exact matches found.</p>
<p>Did you mean:</p>
<ul class="mc-suggestions">
    <?php foreach ($suggestions as $s): ?>
        <?php $label = $this->mc_get_full_product_label($s['id']); ?>
        <li class="mc-suggestion-item"
            data-product-id="<?php echo esc_attr($s['id']); ?>">
            <?php echo esc_html($label); ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php

 wp_send_json_success(['html' => ob_get_clean()]);
 }


    /* ---------------------------------------------------------
       AJAX: GET SUPPLIERS FOR A SELECTED PRODUCT
       - returns comparison table (cheapest first)
    --------------------------------------------------------- */
    public function ajax_get_product_suppliers() {
        check_ajax_referer('mc_comparison_nonce', 'nonce');

        $pharmacy_id = $this->get_current_pharmacy_id();
        if (!$pharmacy_id) wp_send_json_error(['message' => 'Not authorised.']);

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        if ($product_id <= 0) wp_send_json_error(['message' => 'Invalid product.']);

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
              AND sp.product_id = %d
            GROUP BY sp.id
            ORDER BY sp.price ASC
            LIMIT 50
        ";

        $rows = $wpdb->get_results(
            $wpdb->prepare($sql, $product_id),
            ARRAY_A
        );

        if (!$rows) {
            wp_send_json_success(['html' => '<p>No supplier data found for this product.</p>']);
        }

        ob_start();
        ?>
        <table class="mc-search-results-table mc-supplier-comparison-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Description</th>
                    <th>Supplier</th>
                    <th>Unit Price</th>
                    <th>Stock</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>

                <?php
                $name_parts = [];

                if (!empty($row['pack_size'])) {
                    $name_parts[] = $row['pack_size'];
                }
                if (!empty($row['strength'])) {
                    $name_parts[] = $row['strength'];
                }

                $suffix = '';
                if (!empty($name_parts)) {
                    $suffix = ' (' . implode(', ', $name_parts) . ')';
                }

                $full_name = $row['product_name'] . $suffix;
                ?>

                <tr class="mc-supplier-row"
                    data-supplier-product-id="<?php echo esc_attr($row['supplier_product_id']); ?>"
                    data-product-id="<?php echo esc_attr($row['product_id']); ?>"
                    data-supplier-id="<?php echo esc_attr($row['supplier_id']); ?>"
                    data-unit-price="<?php echo esc_attr($row['price']); ?>">

                    <td><?php echo esc_html($full_name); ?></td>
                    <td><?php echo esc_html($row['description']); ?></td>
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
       RENDER PENDING ORDER HTML (WITH EDITABLE QTY + ✔️ UPDATE)
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

            <h3 class="mc-pending-order-title">
                Pending Order #<?php echo $pending_order_id; ?>
            </h3>

            <table class="mc-pending-order-table mc-table-clean">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Supplier</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Supplier Line Total</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($grouped as $supplier_id => $data): ?>

                    <?php foreach ($data['items'] as $item): ?>
                        <?php
                            $full_label = $this->mc_get_full_product_label($item['product_id']);
                        ?>
                        <tr>
                            <td><?php echo esc_html($full_label); ?></td>
                            <td><?php echo esc_html($data['supplier_name']); ?></td>

                            <td>
                                <input 
                                    type="number"
                                    class="mc-edit-qty"
                                    value="<?php echo (int)$item['quantity']; ?>"
                                    min="1"
                                    title="Click to edit quantity"
                                    data-item-id="<?php echo esc_attr($item['id']); ?>"
                                >
                            </td>

                            <td>£<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td>£<?php echo number_format($item['line_total'], 2); ?></td>

                            <td class="mc-actions">
                            <button 
                                type="button"
                                class="mc-update-row"
                                data-item-id="<?php echo esc_attr($item['id']); ?>"
                                title="Update quantity"
                            >
                                ✔️
                            </button>

                            <button 
                                type="button"
                                class="mc-remove-pending-item"
                                data-item-id="<?php echo esc_attr($item['id']); ?>"
                                title="Remove item"
                            >
                                ❌
                            </button>
                        </td>
                        </tr>
                    <?php endforeach; ?>

                    <tr class="mc-supplier-subtotal-row">
                        <td colspan="4" style="text-align:right; font-weight:600;">
                            <?php echo esc_html($data['supplier_name']); ?> Total:
                        </td>
                        <td style="font-weight:600;">
                            £<?php echo number_format($data['supplier_total'], 2); ?>
                        </td>
                        <td></td>
                    </tr>

                <?php endforeach; ?>
                </tbody>
            </table>

            <p class="mc-overall-total">
                <strong>Order Total:</strong>
                £<?php echo number_format($overall_total, 2); ?>
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
       AJAX: UPDATE QTY IN PENDING ORDER (NEW)
    --------------------------------------------------------- */
    public function ajax_update_pending_qty() {
        check_ajax_referer('mc_comparison_nonce', 'nonce');

        $pharmacy_id = $this->get_current_pharmacy_id();
        if (!$pharmacy_id) wp_send_json_error(['message' => 'Not authorised.']);

        $item_id = (int) ($_POST['item_id'] ?? 0);
        $new_qty = (int) ($_POST['qty'] ?? 0);

        if ($item_id <= 0 || $new_qty <= 0) {
            wp_send_json_error(['message' => 'Invalid quantity.']);
        }

        global $wpdb;

        $items_table          = $wpdb->prefix . 'medi_pending_order_items';
        $pending_orders_table = $wpdb->prefix . 'medi_pending_orders';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, po.pharmacy_id
             FROM {$items_table} i
             INNER JOIN {$pending_orders_table} po ON po.id = i.pending_order_id
             WHERE i.id = %d
             LIMIT 1",
            $item_id
        ), ARRAY_A);

        if (!$row || (int)$row['pharmacy_id'] !== (int)$pharmacy_id) {
            wp_send_json_error(['message' => 'Item not found.']);
        }

        $unit_price = (float)$row['unit_price'];
        $line_total = $unit_price * $new_qty;

        $wpdb->update(
            $items_table,
            [
                'quantity'   => $new_qty,
                'line_total' => $line_total,
            ],
            ['id' => $item_id],
            ['%d', '%f'],
            ['%d']
        );

        $html = $this->render_pending_order_html($pharmacy_id);

        wp_send_json_success([
            'message' => 'Quantity updated.',
            'html'    => $html,
        ]);
    }

    /* ---------------------------------------------------------
   AJAX: CANCEL ENTIRE PENDING ORDER (NEW)
--------------------------------------------------------- */
    public function ajax_cancel_pending_order() {

        check_ajax_referer('mc_comparison_nonce', 'nonce');

        $pharmacy_id = $this->get_current_pharmacy_id();
        if (!$pharmacy_id) {
            wp_send_json_error(['message' => 'Not authorised.']);
        }

        global $wpdb;

        $orders_table = $wpdb->prefix . 'medi_pending_orders';
        $items_table  = $wpdb->prefix . 'medi_pending_order_items';

        // Delete all items for this pharmacy
        $wpdb->delete($items_table, ['pharmacy_id' => $pharmacy_id]);

        // Delete the pending order header
        $wpdb->delete($orders_table, ['pharmacy_id' => $pharmacy_id]);

        wp_send_json_success();
    }


    /* ---------------------------------------------------------
       AJAX: TRANSFER ORDER (WITH MASTER ORDER NUMBERING + SUB-ORDER TABLE + AUTO EMAILS)
       + SUPPLIER STOCK DEDUCTION + NEGATIVE STOCK PREVENTION
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
        $suborders_table          = $wpdb->prefix . 'medi_order_suborders';
        $postmeta_table           = $wpdb->postmeta;
        $supplier_products_table  = $wpdb->prefix . 'medi_supplier_products';

        $pending_order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$pending_orders_table} WHERE pharmacy_id = %d LIMIT 1",
            $pharmacy_id
        ));

        if (!$pending_order_id) {
            wp_send_json_error(['message' => 'No pending order to transfer.']);
        }

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$pending_items_table} WHERE pending_order_id = %d",
            $pending_order_id
        ), ARRAY_A);

        if (!$items) {
            wp_send_json_error(['message' => 'Pending order is empty.']);
        }

        // 1) validate supplier stock
        $insufficient = [];

        foreach ($items as $item) {
            $supplier_id = (int) $item['supplier_id'];
            $product_id  = (int) $item['product_id'];
            $qty         = (int) $item['quantity'];

            $current_stock = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT stock FROM {$supplier_products_table}
                     WHERE supplier_id = %d AND product_id = %d",
                    $supplier_id,
                    $product_id
                )
            );

            if ($current_stock === null) {
                $current_stock = 0;
            }

            if ($current_stock < $qty) {
                $product_name = get_the_title($product_id);
                $insufficient[] = sprintf(
                    '%s (Supplier ID %d) — required %d, available %d',
                    $product_name,
                    $supplier_id,
                    $qty,
                    $current_stock
                );
            }
        }

        if (!empty($insufficient)) {
            wp_send_json_error([
                'message' => "Cannot transfer order. Insufficient supplier stock for:\n" . implode("\n", $insufficient)
            ]);
        }

        // 2) normal order creation
        $overall_total   = 0;
        $supplier_totals = [];

        foreach ($items as $item) {
            $overall_total += (float) $item['line_total'];

            $sid = (int) $item['supplier_id'];
            if (!isset($supplier_totals[$sid])) {
                $supplier_totals[$sid] = 0;
            }
            $supplier_totals[$sid] += (float) $item['line_total'];
        }

        $last_order_number = (int) $wpdb->get_var("SELECT MAX(order_number) FROM {$orders_table}");
        $new_order_number  = $last_order_number ? $last_order_number + 1 : 10001;

        $wpdb->insert($orders_table, [
            'pharmacy_id'  => $pharmacy_id,
            'order_number' => $new_order_number,
            'total_amount' => $overall_total,
            'status'       => 'TRANSFERRED',
            'created_at'   => current_time('mysql'),
        ]);

        $order_id = (int) $wpdb->insert_id;

        foreach ($items as $item) {
            $wpdb->insert($order_items_table, [
                'order_id'            => $order_id,
                'product_id'          => (int) $item['product_id'],
                'supplier_id'         => (int) $item['supplier_id'],
                'quantity'            => (int) $item['quantity'],
                'unit_price'          => (float) $item['unit_price'],
                'line_total'          => (float) $item['line_total'],
                'supplier_cost_price' => null,
                'supplier_profit'     => null,
            ]);
        }

        foreach ($supplier_totals as $supplier_id => $supplier_total) {

            $supplier_code = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$postmeta_table}
                 WHERE post_id = %d AND meta_key = %s LIMIT 1",
                $supplier_id,
                'mc_supplier_code'
            ));

            if (!$supplier_code) {
                $supplier_code = 'SUP' . $supplier_id;
            }

            $suborder_number = $new_order_number . '-' . $supplier_code;

            $wpdb->insert($supplier_summary_table, [
                'order_id'              => $order_id,
                'supplier_id'           => $supplier_id,
                'suborder_number'       => $suborder_number,
                'supplier_total_amount' => $supplier_total,
                'platform_fee_percent'  => 0.00,
                'platform_fee_amount'   => 0.00,
                'supplier_order_status' => 'pending',
                'created_at'            => current_time('mysql'),
            ]);

            $wpdb->insert($suborders_table, [
                'order_id'              => $order_id,
                'supplier_id'           => $supplier_id,
                'suborder_number'       => $suborder_number,
                'supplier_order_status' => 'pending',
                'email_sent'            => 0,
                'email_sent_at'         => null,
                'created_at'            => current_time('mysql'),
                'updated_at'            => null,
            ]);
        }

        // 3) deduct supplier stock
        foreach ($items as $item) {
            $supplier_id = (int) $item['supplier_id'];
            $product_id  = (int) $item['product_id'];
            $qty         = (int) $item['quantity'];

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$supplier_products_table}
                     SET stock = GREATEST(stock - %d, 0),
                         last_updated = %s
                     WHERE supplier_id = %d AND product_id = %d",
                    $qty,
                    current_time('mysql'),
                    $supplier_id,
                    $product_id
                )
            );
        }

        // 4) emails + status + cleanup
        require_once plugin_dir_path(__FILE__) . 'email-functions.php';
        $email_engine = new MediCompare_Email_Engine();

        $pharmacy_post = get_post($pharmacy_id);

        $address_line_1 = get_post_meta($pharmacy_id, '_mc_address_line_1', true);
        $address_line_2 = get_post_meta($pharmacy_id, '_mc_address_line_2', true);
        $city           = get_post_meta($pharmacy_id, '_mc_city', true);
        $postcode       = get_post_meta($pharmacy_id, '_mc_postcode', true);

        $full_address = trim(
            $address_line_1 . ' ' .
            $address_line_2 . ', ' .
            $city . ', ' .
            $postcode
        );

        $pharmacy = [
            'id'      => $pharmacy_id,
            'name'    => $pharmacy_post->post_title,
            'email'   => get_post_meta($pharmacy_id, '_mc_email', true),
            'phone'   => get_post_meta($pharmacy_id, '_mc_phone', true),
            'address' => $full_address,
        ];

        $suppliers = $wpdb->get_results($wpdb->prepare(
            "SELECT supplier_id, suborder_number, supplier_total_amount
             FROM {$supplier_summary_table}
             WHERE order_id = %d",
            $order_id
        ), ARRAY_A);

        $items_by_supplier = [];
        foreach ($items as $item) {
            $product_name = get_the_title($item['product_id']);
            $items_by_supplier[$item['supplier_id']][] = [
                'product_id'   => (int) $item['product_id'],
                'product_name' => $product_name,
                'quantity'     => $item['quantity'],
                'unit_price'   => $item['unit_price'],
                'line_total'   => $item['line_total']
            ];
        }

        $email_engine->send_supplier_emails(
            $order_id,
            $new_order_number,
            $pharmacy,
            $suppliers,
            $items_by_supplier
        );

        //IF want to send pharmacy email in future the uncomment
        // $email_engine->send_pharmacy_confirmation(
        //     $new_order_number,
        //     $pharmacy,
        //     $suppliers,
        //     $items
        // );

        $email_engine->send_admin_notification(
            $new_order_number,
            $pharmacy,
            $suppliers
        );

        $wpdb->update(
            $orders_table,
            ['status' => 'SENT'],
            ['id' => $order_id]
        );

        $wpdb->delete($pending_items_table, ['pending_order_id' => $pending_order_id]);
        $wpdb->delete($pending_orders_table, ['id' => $pending_order_id]);

        wp_send_json_success(['message' => 'Order transferred successfully.']);
    }

    /* ---------------------------------------------------------
       AJAX: GET TRANSFERRED ORDERS (WITH STATUS BADGES + UI ENHANCEMENTS)
    --------------------------------------------------------- */
    public function ajax_get_transferred_orders() {
        check_ajax_referer('mc_comparison_nonce', 'nonce');

        $pharmacy_id = $this->get_current_pharmacy_id();
        if (!$pharmacy_id) {
            wp_send_json_error(['message' => 'Not authorised.']);
        }

        global $wpdb;

        $orders_table           = $wpdb->prefix . 'medi_orders';
        $supplier_summary_table = $wpdb->prefix . 'medi_order_supplier_summary';
        $suborders_table        = $wpdb->prefix . 'medi_order_suborders';
        $order_items_table      = $wpdb->prefix . 'medi_order_items';
        $posts_table            = $wpdb->posts;

        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT id, order_number, total_amount, created_at, status
             FROM {$orders_table}
             WHERE pharmacy_id = %d
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

                <div class="mc-transferred-order-card mc-order-collapsed">

                    <div class="mc-order-collapse-header" data-order-toggle>

                        <span>
                            Order #<?php echo esc_html($order['order_number']); ?>
                        </span>

                        <span class="mc-order-status-badge mc-status-<?php echo strtolower($order['status']); ?>">
                            <?php echo esc_html(ucfirst(strtolower($order['status']))); ?>
                        </span>

                        <span class="mc-order-collapse-arrow">▼</span>
                    </div>

                    <div class="mc-order-collapse-content">

                        <p>
                            <strong>Date:</strong>
                            <?php echo esc_html(date('d M Y H:i', strtotime($order['created_at']))); ?>
                        </p>

                        <p>
                            <strong>Total:</strong>
                            £<?php echo number_format((float) $order['total_amount'], 2); ?>
                        </p>

                        <?php
                        $suppliers = $wpdb->get_results($wpdb->prepare(
                            "SELECT supplier_id, suborder_number, supplier_total_amount
                             FROM {$supplier_summary_table}
                             WHERE order_id = %d",
                            $order['id']
                        ), ARRAY_A);
                        ?>

                        <?php if ($suppliers): ?>
                            <div class="mc-transferred-suppliers">

                                <?php foreach ($suppliers as $s): ?>

                                    <?php
                                    $supplier_name = $wpdb->get_var($wpdb->prepare(
                                        "SELECT post_title FROM {$posts_table} WHERE ID = %d",
                                        $s['supplier_id']
                                    ));

                                    $suborder = $wpdb->get_row($wpdb->prepare(
                                        "SELECT supplier_order_status, email_sent, email_sent_at, created_at
                                         FROM {$suborders_table}
                                         WHERE suborder_number = %s
                                         LIMIT 1",
                                        $s['suborder_number']
                                    ), ARRAY_A);

                                    $suborder_status = $suborder ? strtolower($suborder['supplier_order_status']) : 'pending';
                                    $email_sent      = $suborder ? (int) $suborder['email_sent'] : 0;
                                    $email_sent_at   = $suborder && $suborder['email_sent_at']
                                                        ? date('d M Y H:i', strtotime($suborder['email_sent_at']))
                                                        : null;

                                    $items = $wpdb->get_results($wpdb->prepare(
                                        "SELECT oi.product_id, oi.quantity, oi.unit_price, oi.line_total,
                                                p.post_title AS product_name
                                         FROM {$order_items_table} oi
                                         INNER JOIN {$posts_table} p ON p.ID = oi.product_id
                                         WHERE oi.order_id = %d
                                           AND oi.supplier_id = %d
                                         ORDER BY p.post_title ASC",
                                        $order['id'],
                                        $s['supplier_id']
                                    ), ARRAY_A);
                                    ?>

                                    <div class="mc-suborder-block">

                                        <div class="mc-suborder-header">
                                            <div>
                                                <strong><?php echo esc_html($supplier_name ?: 'Supplier #' . $s['supplier_id']); ?></strong><br>
                                                <span class="mc-suborder-ref">
                                                    Sub-order: <?php echo esc_html($s['suborder_number']); ?>
                                                </span>
                                            </div>

                                            <div class="mc-suborder-status">

                                                <span class="mc-suborder-status-badge mc-status-<?php echo esc_attr($suborder_status); ?>">
                                                    <?php echo esc_html(ucfirst($suborder_status)); ?>
                                                </span>

                                                <span class="mc-suborder-total">
                                                    Supplier Total: £<?php echo number_format((float) $s['supplier_total_amount'], 2); ?>
                                                </span>

                                                <span class="mc-email-indicator">
                                                    Email:
                                                    <?php if ($email_sent): ?>
                                                        <strong>Sent</strong>
                                                        <?php if ($email_sent_at): ?>
                                                            (<?php echo esc_html($email_sent_at); ?>)
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <strong>Not Sent</strong>
                                                    <?php endif; ?>
                                                </span>

                                            </div>
                                        </div>

                                        <?php if ($items): ?>
                                            <table class="mc-suborder-table">
                                                <thead>
                                                    <tr>
                                                        <th>Product</th>
                                                        <th>Qty</th>
                                                        <th>Unit Price</th>
                                                        <th>Supplier Line Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($items as $item): ?>
                                                        <?php $full_label = $this->mc_get_full_product_label($item['product_id']); ?>
                                                        <tr>
                                                            <td><?php echo esc_html($full_label); ?></td>
                                                            <td><?php echo (int) $item['quantity']; ?></td>
                                                            <td>£<?php echo number_format((float) $item['unit_price'], 2); ?></td>
                                                            <td>£<?php echo number_format((float) $item['line_total'], 2); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <p>No items found for this supplier.</p>
                                        <?php endif; ?>

                                    </div>

                                <?php endforeach; ?>

                            </div>
                        <?php else: ?>
                            <p>No supplier breakdown available.</p>
                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>
        </div>
        <?php

        wp_send_json_success(['html' => ob_get_clean()]);
    }

} // END CLASS

new MediCompare_Pharmacy_Comparison();
