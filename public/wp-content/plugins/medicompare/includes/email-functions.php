<?php
/**
 * MediCompare Email Sending Engine
 */

if (!defined('ABSPATH')) exit;

class MediCompare_Email_Engine {

    private $template_path;

    public function __construct() {
        $this->template_path = plugin_dir_path(__FILE__) . '../templates/emails/';
    }

    /* ---------------------------------------------------------
       FULL PRODUCT LABEL (NAME + PACK SIZE + STRENGTH)
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
       LOAD TEMPLATE FILE (SAFE — OUTPUT BUFFERING)
    --------------------------------------------------------- */
    private function load_template($filename) {
        $file = $this->template_path . $filename;
        if (!file_exists($file)) return '';

        ob_start();
        include $file;
        return ob_get_clean();
    }

    /* ---------------------------------------------------------
       REPLACE PLACEHOLDERS
    --------------------------------------------------------- */
    private function fill_template($template, $vars) {
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }

    /* ---------------------------------------------------------
   SEND SUPPLIER EMAILS (UPDATED WITH STATUS FLOW)
--------------------------------------------------------- */
public function send_supplier_emails($order_id, $order_number, $pharmacy, $suppliers, $items_by_supplier) {
    global $wpdb;

    $suborders_table = $wpdb->prefix . 'medi_order_suborders';

    // Load supplier email template
    $template = $this->load_template('supplier-email-template.php');

    foreach ($suppliers as $supplier) {

        $supplier_id      = $supplier['supplier_id'];
        $suborder_number  = $supplier['suborder_number'];
        $supplier_total   = number_format((float)$supplier['supplier_total_amount'], 2);

        // Correct supplier email meta key
        $supplier_email = get_post_meta($supplier_id, 'mc_supplier_email', true);
        if (!$supplier_email) continue;

        /* ---------------------------------------------------------
           BUILD ITEMS TABLE
        --------------------------------------------------------- */
        $rows = '';
        foreach ($items_by_supplier[$supplier_id] as $item) {

            $full_label = $this->mc_get_full_product_label($item['product_id']);

            $rows .= '<tr>
                <td>' . esc_html($full_label) . '</td>
                <td>' . (int)$item['quantity'] . '</td>
                <td>£' . number_format((float)$item['unit_price'], 2) . '</td>
                <td>£' . number_format((float)$item['line_total'], 2) . '</td>
            </tr>';
        }

        /* ---------------------------------------------------------
           FILL TEMPLATE
        --------------------------------------------------------- */
        $body = $this->fill_template($template, [
            'suborder_number' => $suborder_number,
            'order_number'    => $order_number,
            'order_date'      => date('d M Y H:i'),
            'pharmacy_name'   => $pharmacy['name'],
            'pharmacy_address'=> $pharmacy['address'],
            'pharmacy_email'  => $pharmacy['email'],
            'pharmacy_phone'  => $pharmacy['phone'],
            'items_table'     => $rows,
            'supplier_total'  => $supplier_total
        ]);

        /* ---------------------------------------------------------
           SEND EMAIL
        --------------------------------------------------------- */
        $sent = wp_mail(
            $supplier_email,
            "New Order — Sub‑Order {$suborder_number}",
            $body,
            ['Content-Type: text/html; charset=UTF-8']
        );

        /* ---------------------------------------------------------
           UPDATE SUB-ORDER STATUS IF EMAIL SENT
        --------------------------------------------------------- */
        if ($sent) {
            $wpdb->update(
                $suborders_table,
                [
                    'supplier_order_status' => 'sent',          // NEW
                    'email_sent'            => 1,
                    'email_sent_at'         => current_time('mysql'),
                    'updated_at'            => current_time('mysql')
                ],
                ['suborder_number' => $suborder_number]
            );
        }
    }
  }


    /* ---------------------------------------------------------
       SEND PHARMACY CONFIRMATION EMAIL
    --------------------------------------------------------- */
    public function send_pharmacy_confirmation($order_number, $pharmacy, $suppliers, $all_items) {

        if (empty($pharmacy['email'])) return;

        $template = $this->load_template('pharmacy-email-template.php');

        // Supplier breakdown table
        $supplier_rows = '';
        foreach ($suppliers as $s) {
            $supplier_name = get_the_title($s['supplier_id']);
            $supplier_rows .= '<tr>
                <td>' . esc_html($supplier_name) . '</td>
                <td>' . esc_html($s['suborder_number']) . '</td>
                <td>£' . number_format((float)$s['supplier_total_amount'], 2) . '</td>
            </tr>';
        }

        // Full item list
        $item_rows = '';
        foreach ($all_items as $item) {

            $full_label   = $this->mc_get_full_product_label($item['product_id']);
            $supplier_name = get_the_title($item['supplier_id']);

            $item_rows .= '<tr>
                <td>' . esc_html($full_label) . '</td>
                <td>' . esc_html($supplier_name) . '</td>
                <td>' . (int)$item['quantity'] . '</td>
                <td>£' . number_format((float)$item['unit_price'], 2) . '</td>
                <td>£' . number_format((float)$item['line_total'], 2) . '</td>
            </tr>';
        }

        // Grand total
        $grand_total = array_sum(array_column($suppliers, 'supplier_total_amount'));
        $grand_total = number_format((float)$grand_total, 2);

        // Fill template
        $body = $this->fill_template($template, [
            'order_number'            => $order_number,
            'order_date'              => date('d M Y H:i'),
            'pharmacy_name'           => $pharmacy['name'],
            'supplier_breakdown_table'=> $supplier_rows,
            'full_items_table'        => $item_rows,
            'grand_total'             => $grand_total
        ]);

        wp_mail(
            $pharmacy['email'],
            "Order Confirmation — Order {$order_number}",
            $body,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }

    /* ---------------------------------------------------------
       SEND ADMIN NOTIFICATION EMAIL
    --------------------------------------------------------- */
    public function send_admin_notification($order_number, $pharmacy, $suppliers) {

        $template = $this->load_template('admin-email-template.php');

        // Supplier breakdown
        $rows = '';
        foreach ($suppliers as $s) {
            $supplier_name = get_the_title($s['supplier_id']);
            $rows .= '<tr>
                <td>' . esc_html($supplier_name) . '</td>
                <td>' . esc_html($s['suborder_number']) . '</td>
                <td>£' . number_format((float)$s['supplier_total_amount'], 2) . '</td>
            </tr>';
        }

        $grand_total = array_sum(array_column($suppliers, 'supplier_total_amount'));
        $grand_total = number_format((float)$grand_total, 2);

        $body = $this->fill_template($template, [
            'pharmacy_name'        => $pharmacy['name'],
            'pharmacy_email'       => $pharmacy['email'],
            'pharmacy_phone'       => $pharmacy['phone'],
            'order_number'         => $order_number,
            'order_date'           => date('d M Y H:i'),
            'supplier_breakdown_table' => $rows,
            'grand_total'          => $grand_total,
            'admin_order_link'     => admin_url("admin.php?page=medi_order&id={$order_number}"),
            'admin_pharmacy_link'  => admin_url("post.php?post={$pharmacy['id']}&action=edit")
        ]);

        wp_mail(
            get_option('admin_email'),
            "New Pharmacy Order — {$pharmacy['name']} — Order {$order_number}",
            $body,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }

    /* ---------------------------------------------------------
       SEND WELCOME SIGN UP NOTIFICATION EMAIL to admin
    --------------------------------------------------------- */

    public function send_welcome_signup_notification($pharmacy_name, $contact_name, $contact_number, $contact_email) {

        // Load template
        $template = $this->load_template('welcome-signup-notification.php');

        // Fill placeholders
        $body = $this->fill_template($template, [
            'pharmacy_name'  => $pharmacy_name,
            'contact_name'   => $contact_name,
            'contact_number' => $contact_number,
            'contact_email'  => $contact_email,
            'submitted_at'   => date('d M Y H:i')
        ]);

        // Send to admin email
        wp_mail(
            get_option('admin_email'),
            "New Pharmacy Signup Interest — {$pharmacy_name}",
            $body,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }

}
