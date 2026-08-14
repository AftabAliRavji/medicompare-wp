<?php

use Dompdf\Dompdf;
use Dompdf\Options;


if ( ! defined('ABSPATH') ) {
    exit;
}

/**
 * Check if a commission period has already been sent
 */
function mc_commission_period_already_sent( $supplier_id, $from, $to ) {
    global $wpdb;

    $table = $wpdb->prefix . 'medi_supplier_commission_emails';

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE supplier_id = %d
             AND period_from = %s
             AND period_to = %s
             LIMIT 1",
            $supplier_id,
            $from,
            $to
        )
    );

    return ! empty( $row );
}

/**
 * Check if auto sending for each supplier is enabled or not for the commission emails
 */
function mc_supplier_auto_send_enabled($supplier_id) {
    $value = get_post_meta($supplier_id, 'mc_auto_send_commission_email', true);
    return ($value === 'yes');
}

/**
 * Fetch supplier commission orders
 * (unchanged from your original)
 */
function mc_get_supplier_commission_orders($supplier_id, $from, $to) {
    global $wpdb;

    $orders_table           = $wpdb->prefix . 'medi_orders';
    $supplier_summary_table = $wpdb->prefix . 'medi_order_supplier_summary';

    $where = ["o.status IN ('TRANSFERRED','SENT')"];
    $params = [];

    if ($from) {
        $where[]  = "DATE(o.created_at) >= %s";
        $params[] = $from;
    }

    if ($to) {
        $where[]  = "DATE(o.created_at) <= %s";
        $params[] = $to;
    }

    $where_sql = implode(' AND ', $where);

    $sql = "
        SELECT 
            o.order_number AS master_order,
            CONCAT(o.order_number, '-', pm.meta_value) AS sub_order,
            DATE(o.created_at) AS date,
            oss.supplier_total_amount AS supplier_total,
            CASE 
                WHEN oss.platform_fee_amount > 0 THEN oss.platform_fee_amount
                WHEN oss.platform_fee_percent > 0 THEN (oss.supplier_total_amount * oss.platform_fee_percent / 100)
                ELSE 0
            END AS commission,
            oss.platform_fee_percent AS commission_pct
        FROM {$orders_table} o
        INNER JOIN {$supplier_summary_table} oss
            ON oss.order_id = o.id
        LEFT JOIN {$wpdb->postmeta} pm
            ON pm.post_id = oss.supplier_id AND pm.meta_key = 'mc_supplier_code'
        WHERE oss.supplier_id = %d
        AND {$where_sql}
        ORDER BY o.created_at DESC
    ";

    $params = array_merge([$supplier_id], $params);

    return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
}

/**
 * Build commission summary array (dates + totals + orders)
 */
function mc_generate_commission_summary($supplier_id, $from, $to) {

    $orders = mc_get_supplier_commission_orders($supplier_id, $from, $to);

    if (empty($orders)) {
        return false;
    }

    // Auto date range if missing
    if (empty($from) || empty($to)) {
        $dates = array_column($orders, 'date');
        sort($dates);
        $from = $dates[0];
        $to   = $dates[count($dates) - 1];
    }

    $total_supplier_amount = 0;
    $total_commission      = 0;

    foreach ($orders as $row) {
        $total_supplier_amount += floatval($row['supplier_total']);
        $total_commission      += floatval($row['commission']);
    }

    return [
        'supplier_id'            => $supplier_id,
        'orders'                 => $orders,
        'total_supplier_amount'  => $total_supplier_amount,
        'total_commission'       => $total_commission,
        'date_from'              => $from,
        'date_to'                => $to,
        'pay_date'               => date("Y-m-t", strtotime($from))
    ];
}

/**
 * Helper: get supplier full address (matches your original logic)
 */
function mc_get_supplier_address($supplier_id) {

    $address_single = trim(get_post_meta($supplier_id, 'mc_supplier_address', true));

    $addr1     = trim(get_post_meta($supplier_id, 'mc_supplier_address_1', true));
    $addr2     = trim(get_post_meta($supplier_id, 'mc_supplier_address_2', true));
    $city      = trim(get_post_meta($supplier_id, 'mc_supplier_city', true));
    $county    = trim(get_post_meta($supplier_id, 'mc_supplier_county', true));
    $postcode  = trim(get_post_meta($supplier_id, 'mc_supplier_postcode', true));
    $country   = trim(get_post_meta($supplier_id, 'mc_supplier_country', true));

    $address_parts = array_filter([$addr1, $addr2, $city, $county, $postcode, $country]);

    if (!empty($address_single)) {
        return $address_single;
    } elseif (!empty($address_parts)) {
        return implode(', ', $address_parts);
    }

    return 'Address not available';
}

    /**
     * Generate PDF (same HTML structure as your original handler)
     * Returns file path for scheduler/email; admin.php can still stream it.
     */
    function mc_generate_commission_pdf($summary) {
        global $wpdb;

        $supplier_id     = $summary['supplier_id'];
        $supplier_post   = get_post($supplier_id);
        $supplier_name   = $supplier_post->post_title;
        $supplier_code   = $supplier_post->post_name;
        $supplier_email  = get_post_meta($supplier_id, 'mc_supplier_email', true);
        $supplier_phone  = get_post_meta($supplier_id, 'mc_supplier_phone', true);
        $supplier_manager= get_post_meta($supplier_id, 'mc_supplier_manager', true);
        $supplier_address= mc_get_supplier_address($supplier_id);

        // Bank details
        $bank_acc_name   = get_option('mc_bank_account_name', 'mediCompare');
        $bank_name       = get_option('mc_bank_name', 'HSBC');
        $bank_acc_number = get_option('mc_bank_account_number', 'xxxxxxx');
        $bank_sort_code  = get_option('mc_bank_sort_code', 'xx-xx-xx');

        $from = $summary['date_from'];
        $to   = $summary['date_to'];

        /**
         * ⭐ DUPLICATE INVOICE PREVENTION
         */
        $existing = mc_get_existing_invoice($supplier_id, $from, $to);

        if ($existing) {

            // Reuse existing invoice
            $invoice_number = $existing['invoice_reference'];
            $pdf_filename   = $existing['pdf_filename'];

        } else {

            // Generate new invoice number
            $sequence = intval(get_post_meta($supplier_id, 'mc_invoice_sequence', true));
            $sequence++;
            update_post_meta($supplier_id, 'mc_invoice_sequence', $sequence);

            $sequence_str = str_pad($sequence, 4, '0', STR_PAD_LEFT);

            $from_date = new DateTime($from);
            $year  = $from_date->format('Y');
            $month = $from_date->format('m');
            $day   = $from_date->format('d');

            $invoice_number = sprintf(
                '%s_INV-%s-%s-%s-%s',
                strtoupper($supplier_code),
                $sequence_str,
                $year,
                $month,
                $day
            );

            $pdf_filename = sprintf(
                'invoice_%s_INV-%s-%s-%s-%s.pdf',
                strtoupper($supplier_code),
                $sequence_str,
                $year,
                $month,
                $day
            );

            // Store invoice in DB
            $wpdb->insert(
                $wpdb->prefix . 'medi_supplier_invoices',
                [
                    'supplier_id'           => $supplier_id,
                    'period_from'           => $from,
                    'period_to'             => $to,
                    'invoice_reference'     => $invoice_number,
                    'total_commission'      => $summary['total_commission'],
                    'total_supplier_amount' => $summary['total_supplier_amount'],
                    'orders_json'           => json_encode($summary['orders']),
                    'pdf_filename'          => $pdf_filename,
                    'generated_at'          => current_time('mysql'),
                ]
            );
        }

        // Add invoice reference to summary
        $summary['invoice_reference'] = $invoice_number;

        // Logo URL
        $plugin_root = dirname(__FILE__, 1);
        $logo_url = plugins_url('assets/img/logo.png', $plugin_root);

        $orders = $summary['orders'];
        $total_supplier_amount = $summary['total_supplier_amount'];
        $total_commission      = $summary['total_commission'];
        $pay_date              = $summary['pay_date'];

        // HTML generation (unchanged)
        ob_start();
        ?>
        <!-- your existing HTML unchanged -->
        <?php
        $html = ob_get_clean();

        // DOMPDF
        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $upload_dir = wp_upload_dir();
        $path = $upload_dir['basedir'] . '/' . $pdf_filename;

        file_put_contents($path, $dompdf->output());

        return $path;
    }


    /**
     * Build email HTML (same structure as your original mc_email_supplier_report)
     */
    function mc_generate_commission_email_html($summary) {

        $supplier_id     = $summary['supplier_id'];
        $supplier_post   = get_post($supplier_id);
        $supplier_name   = $supplier_post->post_title;
        $supplier_address= mc_get_supplier_address($supplier_id);

        // Bank details
        $bank_acc_name   = get_option('mc_bank_account_name', 'mediCompare');
        $bank_name       = get_option('mc_bank_name', 'HSBC');
        $bank_acc_number = get_option('mc_bank_account_number', 'xxxxxxx');
        $bank_sort_code  = get_option('mc_bank_sort_code', 'xx-xx-xx');

        // Logo URL
        $plugin_root = dirname(__FILE__, 1);
        $logo_url = plugins_url('assets/img/logo.png', $plugin_root);

        $orders = $summary['orders'];
        $total_supplier_amount = $summary['total_supplier_amount'];
        $total_commission      = $summary['total_commission'];
        $from                  = $summary['date_from'];
        $to                    = $summary['date_to'];
        $pay_date              = $summary['pay_date'];

        /** ⭐ NEW: Invoice Reference */
        $invoice_reference = $summary['invoice_reference'] ?? 'N/A';

        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; font-size: 14px; color:#333;">

            <img src="<?php echo $logo_url; ?>" style="max-height:60px; margin-bottom:20px;">

            <h2 style="margin-bottom:10px;">Supplier Commission Report</h2>

            <!-- ⭐ NEW: Invoice Reference -->
            <p><strong>Invoice Reference:</strong> <?php echo esc_html($invoice_reference); ?></p>

            <p><strong>Period:</strong> <?php echo $from; ?> to <?php echo $to; ?></p>

            <h3 style="margin-top:25px;">Supplier Details</h3>
            <p><strong>Name:</strong> <?php echo esc_html($supplier_name); ?></p>
            <p><strong>Address:</strong> <?php echo esc_html($supplier_address); ?></p>

            <h3 style="margin-top:25px;">Summary</h3>
            <table cellpadding="6" cellspacing="0" width="100%" style="border-collapse: collapse;">
                <tr>
                    <th style="background:#f5f5f5; border:1px solid #ccc; text-align:left;">Total Supplier Amount</th>
                    <td style="border:1px solid #ccc;">£<?php echo number_format($total_supplier_amount, 2); ?></td>
                </tr>
                <tr>
                    <th style="background:#f5f5f5; border:1px solid #ccc; text-align:left;">Total Commission Payable to MediCompare</th>
                    <td style="border:1px solid #ccc;">£<?php echo number_format($total_commission, 2); ?></td>
                </tr>
            </table>

            <h3 style="margin-top:25px;">Payable To</h3>
            <table cellpadding="6" cellspacing="0" width="100%" style="border-collapse: collapse;">
                <tr>
                    <th style="background:#f5f5f5; border:1px solid #ccc; text-align:left;">Payment Due Date</th>
                    <td style="border:1px solid #ccc;"><?php echo esc_html($pay_date); ?></td>
                </tr>
                <tr>
                    <th style="background:#f5f5f5; border:1px solid #ccc; text-align:left;">Account Name</th>
                    <td style="border:1px solid #ccc;"><?php echo esc_html($bank_acc_name); ?></td>
                </tr>
                <tr>
                    <th style="background:#f5f5f5; border:1px solid #ccc; text-align:left;">Bank</th>
                    <td style="border:1px solid #ccc;"><?php echo esc_html($bank_name); ?></td>
                </tr>
                <tr>
                    <th style="background:#f5f5f5; border:1px solid #ccc; text-align:left;">Account Number</th>
                    <td style="border:1px solid #ccc;"><?php echo esc_html($bank_acc_number); ?></td>
                </tr>
                <tr>
                    <th style="background:#f5f5f5; border:1px solid #ccc; text-align:left;">Sort Code</th>
                    <td style="border:1px solid #ccc;"><?php echo esc_html($bank_sort_code); ?></td>
                </tr>
            </table>

            <h3 style="margin-top:25px;">Order Breakdown</h3>
            <table cellpadding="6" cellspacing="0" width="100%" style="border-collapse: collapse;">
                <tr>
                    <th style="background:#f5f5f5; border:1px solid #ccc;">Master Order #</th>
                    <th style="background:#f5f5f5; border:1px solid #ccc;">Sub Order #</th>
                    <th style="background:#f5f5f5; border:1px solid #ccc;">Date</th>
                    <th style="background:#f5f5f5; border:1px solid #ccc;">Supplier Total</th>
                    <th style="background:#f5f5f5; border:1px solid #ccc;">Commission</th>
                    <th style="background:#f5f5f5; border:1px solid #ccc;">Commission %</th>
                </tr>
                <?php foreach ($orders as $row): ?>
                    <tr>
                        <td style="border:1px solid #ccc;"><?php echo $row['master_order']; ?></td>
                        <td style="border:1px solid #ccc;"><?php echo $row['sub_order']; ?></td>
                        <td style="border:1px solid #ccc;"><?php echo $row['date']; ?></td>
                        <td style="border:1px solid #ccc;">£<?php echo number_format($row['supplier_total'], 2); ?></td>
                        <td style="border:1px solid #ccc;">£<?php echo number_format($row['commission'], 2); ?></td>
                        <td style="border:1px solid #ccc;"><?php echo $row['commission_pct']; ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <p style="font-size:12px; margin-top:20px;">
                Thank you for working with MediCompare.
            </p>

        </div>
        <?php
        return ob_get_clean();
    }

/**
 * Send commission email + log (supplier + admin copy)
 */
function mc_send_commission_email($supplier_id, $summary, $email_html, $pdf_path = null) {
    global $wpdb;

    $supplier_post  = get_post($supplier_id);
    $supplier_name  = $supplier_post->post_title;
    $supplier_email = get_post_meta($supplier_id, 'mc_supplier_email', true);

    if (!$supplier_email) {
        return false;
    }

    $subject = 'Supplier Commission Report for ' . $supplier_name . ' — ' . $summary['date_from'] . ' to ' . $summary['date_to'];

    $headers = ['Content-Type: text/html; charset=UTF-8',
                'From: MediCompare <no-reply@medicompare.local>'];

    // Send to supplier
    wp_mail(
        $supplier_email,
        $subject,
        $email_html,
        $headers,
        $pdf_path ? [$pdf_path] : []
    );

    // Send copy to admin
    $admin_email = get_option('admin_email');

    wp_mail(
        $admin_email,
        $subject . ' (Copy)',
        $email_html,
        $headers,
        $pdf_path ? [$pdf_path] : []
    );

    // Log into medi_supplier_commission_emails
    $wpdb->insert(
        $wpdb->prefix . 'medi_supplier_commission_emails',
        [
            'supplier_id'   => $supplier_id,
            'period_from'   => $summary['date_from'],
            'period_to'     => $summary['date_to'],
            'sent_at'       => current_time('mysql'),
            'sent_by_admin' => is_admin() ? 1 : 0,
            'auto_sent'     => is_admin() ? 0 : 1
        ],
        ['%d', '%s', '%s', '%s', '%d', '%d']
    );

    return true;
}

/**
 * This helper function is to allow the scheduler code and also manual 
 * to check if the email for commission report has already been sent
 */
function mc_has_sent_for_period($supplier_id, $from, $to) {
    global $wpdb;

    $table = $wpdb->prefix . 'medi_supplier_commission_emails';

    $count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) 
        FROM {$table}
        WHERE supplier_id = %d
          AND period_from = %s
          AND period_to = %s
    ", $supplier_id, $from, $to));

    return ($count > 0);
}

/**
 * Get payment summary for an invoice.
 */
function mc_get_invoice_payment_summary($invoice_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'medi_supplier_payments';

    $sql = "
        SELECT 
            SUM(amount) AS total_paid,
            MAX(paid_date) AS last_paid_date,
            MAX(method) AS last_method
        FROM {$table}
        WHERE invoice_id = %d
    ";

    $row = $wpdb->get_row($wpdb->prepare($sql, $invoice_id), ARRAY_A);

    if (!$row || $row['total_paid'] === null) {
        return [
            'total_paid'    => 0.00,
            'last_paid_date'=> null,
            'last_method'   => null,
        ];
    }

    return [
        'total_paid'    => (float)$row['total_paid'],
        'last_paid_date'=> $row['last_paid_date'],
        'last_method'   => $row['last_method'],
    ];
}

/**
 * Allows for the form submission to happen for payment of commission
 */
function mc_add_supplier_payment_action() {

    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $invoice_id  = intval($_POST['invoice_id'] ?? 0);
    $amount      = floatval($_POST['amount'] ?? 0);
    $paid_date   = sanitize_text_field($_POST['paid_date'] ?? '');
    $reference   = sanitize_text_field($_POST['reference'] ?? '');

    if (!$supplier_id || !$invoice_id || $amount <= 0 || !$paid_date) {
        wp_redirect(add_query_arg('mc_payment_error', '1', wp_get_referer()));
        exit;
    }

    mc_add_supplier_payment(
        $supplier_id,
        $invoice_id,
        $amount,
        $paid_date,
        $reference,
        'manual'
    );

    wp_redirect(add_query_arg('mc_payment_saved', '1', wp_get_referer()));
    exit;
}

/**
 * inserts the payment record for commission paid via  scheduler, manual, CSV, Stripe later
 */
function mc_add_supplier_payment($supplier_id, $invoice_id, $amount, $paid_date, $reference = '', $method = 'manual') {
    global $wpdb;

    $table = $wpdb->prefix . 'medi_supplier_payments';

    $wpdb->insert(
        $table,
        [
            'supplier_id' => $supplier_id,
            'invoice_id'  => $invoice_id,
            'amount'      => $amount,
            'paid_date'   => $paid_date,
            'reference'   => $reference,
            'method'      => $method,
            'created_at'  => current_time('mysql'),
        ],
        [
            '%d','%d','%f','%s','%s','%s','%s'
        ]
    );
}

    /**
    * Commission PAID Report (admin page).
    */
    function mc_report_commission_paid() {
        global $wpdb;

        $invoices_table = $wpdb->prefix . 'medi_supplier_invoices';
        $payments_table = $wpdb->prefix . 'medi_supplier_payments';

        // Fetch all invoices with supplier info
        $sql = "
            SELECT 
                i.id,
                i.supplier_id,
                i.period_from,
                i.period_to,
                i.invoice_reference,
                i.total_commission,
                i.total_supplier_amount,
                i.generated_at,
                s.post_title AS supplier_name
            FROM {$invoices_table} i
            LEFT JOIN {$wpdb->posts} s
                ON s.ID = i.supplier_id
            ORDER BY i.generated_at DESC
        ";

        $invoices = $wpdb->get_results($sql, ARRAY_A);

        echo '<div class="wrap">';
        echo '<h1>Commission PAID Report</h1>';

        // Manual payment form
        echo '<h2>Add Manual Payment</h2>';

        // Supplier dropdown
        $suppliers = [];
        foreach ($invoices as $inv) {
            $suppliers[$inv['supplier_id']] = $inv['supplier_name'] ?: ('Supplier #' . $inv['supplier_id']);
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="mc_add_supplier_payment_action">';

        echo '<table class="form-table"><tbody>';

        echo '<tr><th scope="row"><label for="supplier_id">Supplier</label></th><td>';
        echo '<select name="supplier_id" id="supplier_id">';
        foreach ($suppliers as $sid => $sname) {
            echo '<option value="' . intval($sid) . '">' . esc_html($sname) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="invoice_id">Invoice</label></th><td>';
        echo '<select name="invoice_id" id="invoice_id">';
        foreach ($invoices as $inv) {
            echo '<option value="' . intval($inv['id']) . '">';
            echo esc_html($inv['invoice_reference']) . ' (' . esc_html($inv['period_from']) . ' → ' . esc_html($inv['period_to']) . ')';
            echo '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="amount">Amount Paid (£)</label></th><td>';
        echo '<input type="text" name="amount" id="amount" value="" class="regular-text">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="paid_date">Paid Date</label></th><td>';
        echo '<input type="date" name="paid_date" id="paid_date" value="' . esc_attr(date('Y-m-d')) . '">';
        echo '</td></tr>';

        /**
         * ⭐ UPDATED PAYMENT REFERENCE FIELD
         */
        $first_invoice_ref = isset($invoices[0]['invoice_reference'])
            ? $invoices[0]['invoice_reference']
            : 'INV-REFERENCE';

        echo '<tr><th scope="row"><label for="reference">Payment Reference</label></th><td>';
        echo '<input type="text" name="reference" id="reference" value="" class="regular-text"
                placeholder="e.g. BANK-TRX-88372 or ' . esc_attr($first_invoice_ref) . '">';
        echo '<p class="description">
                Suggested: use the invoice number 
                <strong>' . esc_html($first_invoice_ref) . '</strong> 
                or your bank transaction reference.
            </p>';
        echo '</td></tr>';

        echo '</tbody></table>';

        submit_button('Save Payment');

        echo '</form>';

        /**
         * ⭐ NEW — COMING SOON SECTIONS
         */
        echo '<div class="mc-coming-soon-box" style="margin-top:30px; padding:20px; border:1px solid #ddd; background:#fafafa;">';

        echo '<h2 style="margin-top:0;">Additional Payment Options</h2>';

        echo '<div style="margin-top:20px;">
                <h3>Update Payments via CSV <span style="color:#999;">(Coming Soon)</span></h3>
                <p class="mc-muted">
                    You will be able to upload a CSV file containing supplier payments.
                    The system will automatically match invoices and update outstanding balances.
                </p>
            </div>';

        echo '<div style="margin-top:30px;">
                <h3>Pay Supplier via Stripe <span style="color:#999;">(Coming Soon)</span></h3>
                <p class="mc-muted">
                    A secure Stripe payment screen will allow you to pay suppliers directly
                    and automatically log the payment against the correct invoice.
                </p>
            </div>';

        echo '</div>';

        // Summary table
        echo '<h2>Invoice Summary</h2>';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>
                <th>Supplier</th>
                <th>Invoice Ref</th>
                <th>Period</th>
                <th>Total Commission (£)</th>
                <th>Total Paid (£)</th>
                <th>Outstanding (£)</th>
                <th>Last Payment Date</th>
                <th>Method</th>
            </tr></thead><tbody>';

        foreach ($invoices as $inv) {
            $summary = mc_get_invoice_payment_summary($inv['id']);

            $total_commission = (float)$inv['total_commission'];
            $total_paid       = (float)$summary['total_paid'];
            $outstanding      = $total_commission - $total_paid;

            echo '<tr>';
            echo '<td>' . esc_html($inv['supplier_name'] ?: ('Supplier #' . $inv['supplier_id'])) . '</td>';
            echo '<td>' . esc_html($inv['invoice_reference']) . '</td>';
            echo '<td>' . esc_html($inv['period_from']) . ' → ' . esc_html($inv['period_to']) . '</td>';
            echo '<td>£' . number_format($total_commission, 2) . '</td>';
            echo '<td>£' . number_format($total_paid, 2) . '</td>';
            echo '<td>£' . number_format($outstanding, 2) . '</td>';
            echo '<td>' . ($summary['last_paid_date'] ? esc_html($summary['last_paid_date']) : '—') . '</td>';
            echo '<td>' . ($summary['last_method'] ? esc_html($summary['last_method']) : '—') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '</div>';
    }

    /**
     * if invoice already exists then re-use and not generate a new one.
     */
    function mc_get_existing_invoice($supplier_id, $from, $to) {
        global $wpdb;

        $table = $wpdb->prefix . 'medi_supplier_invoices';

        $sql = "
            SELECT *
            FROM {$table}
            WHERE supplier_id = %d
            AND period_from = %s
            AND period_to = %s
            LIMIT 1
        ";

        return $wpdb->get_row(
            $wpdb->prepare($sql, $supplier_id, $from, $to),
            ARRAY_A
        );
    }

    /**
     * Build full product label in correct order:
     *   Name + Strength + Form + (Pack Size)
     *
     * Example:
     *   Omeprazole 20mg Capsules (28)
     */
    function mc_get_full_product_label($product_id) {

        $name       = get_the_title($product_id);
        $strength   = get_post_meta($product_id, 'mc_strength', true);
        $pack_size  = get_post_meta($product_id, 'mc_pack_size', true);
        $category   = get_post_meta($product_id, 'mc_category', true);

        /* ---------------------------------------------------------
        STEP 1 — Detect form (Capsules, Tablets, Inhaler, etc.)
        --------------------------------------------------------- */
        $form = '';

        // Detect form from category
        if ($category) {
            if (preg_match('/tablet/i', $category)) {
                $form = 'Tablets';
            } elseif (preg_match('/capsule/i', $category)) {
                $form = 'Capsules';
            } elseif (preg_match('/inhaler/i', $category)) {
                $form = 'Inhaler';
            } elseif (preg_match('/gel/i', $category)) {
                $form = 'Gel';
            } elseif (preg_match('/cream/i', $category)) {
                $form = 'Cream';
            }
        }

        // If still empty, detect form from product title
        if (!$form) {
            if (preg_match('/tablet/i', $name)) {
                $form = 'Tablets';
            } elseif (preg_match('/capsule/i', $name)) {
                $form = 'Capsules';
            } elseif (preg_match('/inhaler/i', $name)) {
                $form = 'Inhaler';
            } elseif (preg_match('/gel/i', $name)) {
                $form = 'Gel';
            } elseif (preg_match('/cream/i', $name)) {
                $form = 'Cream';
            }
        }

        /* ---------------------------------------------------------
        STEP 2 — Strip form from product title
        e.g. "Omeprazole Capsules" → "Omeprazole"
        --------------------------------------------------------- */
        if ($form) {
            // Remove singular or plural form words
            $name = preg_replace('/\b' . preg_quote($form, '/') . '\b/i', '', $name);
            $name = preg_replace('/\b' . preg_quote(rtrim($form, 's'), '/') . 's?\b/i', '', $name);
            $name = trim($name);
        }

        /* ---------------------------------------------------------
        STEP 3 — Build final label
        --------------------------------------------------------- */
        $label = $name;

        if ($strength) {
            $label .= ' ' . $strength;
        }

        if ($form) {
            $label .= ' ' . $form;
        }

        if ($pack_size) {
            $label .= ' (' . $pack_size . ')';
        }

        return trim($label);
    }

    /* ---------------------------------------------------------
   NORMALISE ANY NAME (tariff or product)
--------------------------------------------------------- */
function mc_normalise_name($name) {

    $name = strtolower($name);

    // Remove punctuation
    $name = str_replace(['-', '/', '(', ')', ',', '.'], ' ', $name);

    // Remove noise words
    $noise = [
        'solution for injection', 'prefilled', 'pre filled', 'disposable devices',
        'gastro resistant', 'gastro-resistant', 'sugar free', 'ear spray',
        'oral powder', 'sachets', 'injection', 'inhaler'
    ];
    foreach ($noise as $n) {
        $name = str_replace($n, '', $name);
    }

    // Remove units spacing
    $name = str_replace([' mg ', ' ml ', ' g ', ' mcg '], ' ', $name);

    // Remove multiple spaces
    $name = preg_replace('/\s+/', ' ', $name);

    return trim($name);
}


/* ---------------------------------------------------------
   BUILD FULL PRODUCT LABEL FOR MATCHING
--------------------------------------------------------- */
function mc_build_label_for_matching($product_id) {

    $name       = get_the_title($product_id);
    $strength   = get_post_meta($product_id, 'mc_strength', true);
    $pack_size  = get_post_meta($product_id, 'mc_pack_size', true);
    $category   = get_post_meta($product_id, 'mc_category', true);

    // Detect form
    $form = '';
    if ($category) {
        if (preg_match('/tablet/i', $category)) $form = 'tablets';
        elseif (preg_match('/capsule/i', $category)) $form = 'capsules';
        elseif (preg_match('/inhaler/i', $category)) $form = 'inhaler';
        elseif (preg_match('/gel/i', $category)) $form = 'gel';
        elseif (preg_match('/cream/i', $category)) $form = 'cream';
    }

    if (!$form) {
        if (preg_match('/tablet/i', $name)) $form = 'tablets';
        elseif (preg_match('/capsule/i', $name)) $form = 'capsules';
        elseif (preg_match('/inhaler/i', $name)) $form = 'inhaler';
        elseif (preg_match('/gel/i', $name)) $form = 'gel';
        elseif (preg_match('/cream/i', $name)) $form = 'cream';
    }

    // Strip form from name
    if ($form) {
        $name = preg_replace('/\b' . preg_quote($form, '/') . '\b/i', '', $name);
        $name = trim($name);
    }

    // Build label
    $label = $name;

    if ($strength) $label .= ' ' . $strength;
    if ($form)     $label .= ' ' . $form;

    return strtolower(trim($label));
}

    /**
     * MATCH PRODUCT ID USING FUZZY LOGIC + PACK SIZE + FORM
     */
    function mc_match_product_id($drug_name, $pack_size = null, $form = null)
    {
        global $wpdb;

        $tariffNorm = mc_normalise_name($drug_name);

        $products = $wpdb->get_results("
            SELECT ID
            FROM {$wpdb->posts}
            WHERE post_type = 'mc_product'
            AND post_status = 'publish'
        ");

        $best_id = 0;
        $best_score = PHP_INT_MAX;

        foreach ($products as $p) {

            $label = mc_build_label_for_matching($p->ID);
            $labelNorm = mc_normalise_name($label);

            // Base score: Levenshtein distance
            $score = levenshtein($tariffNorm, $labelNorm);

            // Token overlap bonus
            $tariffTokens = explode(' ', $tariffNorm);
            $labelTokens  = explode(' ', $labelNorm);
            $overlap = count(array_intersect($tariffTokens, $labelTokens));
            $score -= ($overlap * 3);

            // PACK SIZE MATCH BONUS
            $product_pack = get_post_meta($p->ID, 'mc_pack_size', true);
            if ($product_pack && $pack_size && intval($product_pack) === intval($pack_size)) {
                $score -= 8; // strong bonus
            }

            // FORM MATCH BONUS
            $product_category = strtolower(get_post_meta($p->ID, 'mc_category', true));
            $formNorm = strtolower($form);

            if ($formNorm && $product_category && strpos($product_category, $formNorm) !== false) {
                $score -= 5;
            }

            if ($score < $best_score) {
                $best_score = $score;
                $best_id = $p->ID;
            }
        }

        return $best_id ?: 0;
    }


    /**
     * Fetch Drug Tariff Part VIIIA rows from HTML
     * Auto-discovers A–Z product pages and extracts table rows.
     */
    function mc_fetch_drug_tariff_rows_from_html($root_url)
    {
        $rows = [];

        // 1. Fetch root page
        $response = wp_remote_get($root_url);
        if (is_wp_error($response)) {
            throw new Exception('Failed to fetch Drug Tariff root page.');
        }

        $html = wp_remote_retrieve_body($response);

        // Parse HTML
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        /**
         * 2. Find all Part VIIIA products A–Z links
         * These appear as clickable items in the left navigation.
         */
        $links = $xpath->query("//a[contains(., 'Part VIIIA products')]");

        $product_urls = [];
        foreach ($links as $a) {
            $href = $a->getAttribute('href');
            if (!$href) continue;

            // Convert relative → absolute
            if (strpos($href, 'http') !== 0) {
                $href = 'https://www.drugtariff.nhsbsa.nhs.uk' . $href;
            }

            $product_urls[] = $href;
        }

        /**
         * 3. Loop through each A–Z page
         */
        foreach ($product_urls as $url) {

            $resp = wp_remote_get($url);
            if (is_wp_error($resp)) continue;

            $page_html = wp_remote_retrieve_body($resp);

            $page_dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $page_dom->loadHTML($page_html);
            libxml_clear_errors();

            $page_xpath = new DOMXPath($page_dom);

            /**
             * 4. Extract table rows
             * The NHS Drug Tariff uses a simple <table> structure.
             * We target all <tr> inside any <table>.
             */
            $trs = $page_xpath->query("//table//tr");

            foreach ($trs as $tr) {

                $tds = $tr->getElementsByTagName('td');

                // Skip header or malformed rows
                if ($tds->length < 4) continue;

                $drug_name   = trim($tds->item(0)->textContent);
                $quantity    = trim($tds->item(1)->textContent);
                $basic_price = trim($tds->item(2)->textContent);
                $category    = trim($tds->item(3)->textContent);

                // Manufacturer / brand (optional)
                $brand = ($tds->length > 4)
                    ? trim($tds->item(4)->textContent)
                    : '';

                // Skip empty rows
                if ($drug_name === '') continue;

                // Convert price to integer (pence)
                $basic_price = intval($basic_price);

                $rows[] = [
                    'drug_name'   => $drug_name,
                    'quantity'    => $quantity,
                    'basic_price' => $basic_price,
                    'category'    => strtoupper($category),
                    'brand'       => $brand,
                ];
            }
        }

        return $rows;
    }

    /**
    * Fetch Drug Tariff Part VIIIA rows using DITA map + topic crawler (A → Z)
    */
    function mc_fetch_drug_tariff_rows_from_dita()
    {
        $rows = [];

        // Hard-coded A–Z topic IDs from the DITA2MAP output
        $topic_ids = [
            'DC00912579', // A
            'DC00912493', // B
            'DC00912609', // C
            'DC00912780', // D
            'DC00912127', // E
            'DC00912213', // F
            'DC00912866', // G
            'DC00912652', // H
            'DC00912508', // I
            'DC00912435', // J
            'DC00912214', // K
            'DC00912747', // L
            'DC00912330', // M
            'DC00912556', // N
            'DC00912854', // O
            'DC00912710', // P
            'DC00912375', // Q
            'DC00912094', // R
            'DC00912823', // S
            'DC00912372', // T
            'DC00912711', // U
            'DC00912837', // V
            'DC00912570', // W
            'DC00912331', // X
            'DC00912867', // Y
            'DC00912257', // Z
        ];

        // DEBUG: log topic IDs being used
        file_put_contents(WP_CONTENT_DIR . '/debug_dita_topic_ids.txt', print_r($topic_ids, true));

        foreach ($topic_ids as $topic_id) {

            $topic_url =
                "https://www.drugtariff.nhsbsa.nhs.uk/NotusCloudApi/resources/dita/topic/00912915-DC/" .
                $topic_id .
                "?format=html&metadata=external&resolveMaps=true&resolveTopics=false";

            // DEBUG: log each topic URL
            file_put_contents(WP_CONTENT_DIR . '/debug_dita_last_topic_url.txt', $topic_url);

            $topic_response = wp_remote_get($topic_url);

            if (is_wp_error($topic_response)) {
                file_put_contents(
                    WP_CONTENT_DIR . '/debug_dita_errors.txt',
                    "ERROR FETCHING $topic_url\n" . print_r($topic_response, true) . "\n\n",
                    FILE_APPEND
                );
                continue;
            }

            $topic_html = wp_remote_retrieve_body($topic_response);

            // DEBUG: dump raw HTML for each topic
            file_put_contents(
                WP_CONTENT_DIR . "/debug_dita_topic_$topic_id.html",
                $topic_html
            );

            $topic_dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $topic_dom->loadHTML($topic_html);
            libxml_clear_errors();

            $topic_xpath = new DOMXPath($topic_dom);

            // Extract rows
            $trs = $topic_xpath->query("//table[contains(@class,'table')]//tr");

            // DEBUG: log number of rows found
            file_put_contents(
                WP_CONTENT_DIR . '/debug_dita_row_counts.txt',
                "Topic $topic_id → " . $trs->length . " rows\n",
                FILE_APPEND
            );

            foreach ($trs as $tr) {

                $tds = $tr->getElementsByTagName("td");
                if ($tds->length < 8) continue;

                $drug_name   = trim($tds->item(1)->textContent);
                $quantity    = trim($tds->item(3)->textContent);
                $basic_price = trim($tds->item(5)->textContent);
                $category    = trim($tds->item(7)->textContent);

                $brand = ($tds->length > 8)
                    ? trim($tds->item(8)->textContent)
                    : "";

                if ($drug_name === "") continue;

                $rows[] = [
                    'drug_name'   => $drug_name,
                    'quantity'    => $quantity,
                    'basic_price' => intval($basic_price),
                    'category'    => strtoupper($category),
                    'brand'       => $brand,
                ];
            }
        }

        // DEBUG: final rows count
        file_put_contents(
            WP_CONTENT_DIR . '/debug_dita_final_rows.txt',
            "TOTAL ROWS: " . count($rows)
        );

        return $rows;
    }

        /**
     * Parse Drug Tariff Part VIIIA CSV file
     * CSV Columns:
     * Medicine, Pack size, Form, VMP, VMPP, Category, Basic Price
     */
    function mc_fetch_drug_tariff_rows_from_csv($csv_path)
    {
        $rows = [];

        if (!file_exists($csv_path)) {
            return [];
        }

        $handle = fopen($csv_path, 'r');
        if (!$handle) {
            return [];
        }

        $lineNumber = 0;

        while (($data = fgetcsv($handle)) !== false) {

            $lineNumber++;

            // Skip first 2 lines (title + blank)
            if ($lineNumber <= 2) continue;

            // Skip header row
            if ($lineNumber === 3) continue;

            // Ensure minimum columns
            // Medicine, Pack size, Form, VMP, VMPP, Category, Basic Price
            if (count($data) < 7) continue;

            $medicine    = trim($data[0]);
            $pack_size   = trim($data[1]);
            $form        = trim($data[2]);
            $vmp_code    = trim($data[3]);   // <-- NEW
            $vmpp_code   = trim($data[4]);   // <-- NEW
            $category    = trim($data[5]);
            $basic_price = intval(trim($data[6])); // pence

            if ($medicine === '') continue;

            // Extract category letter (A/C/M)
            $category_letter = '';
            if (preg_match('/Category\s+([ACM])/i', $category, $m)) {
                $category_letter = strtoupper($m[1]);
            }

            $rows[] = [
                'drug_name'   => $medicine,
                'pack_size'   => $pack_size,
                'form'        => $form,
                'vmp_code'    => $vmp_code,    // <-- NEW
                'vmpp_code'   => $vmpp_code,   // <-- NEW
                'category'    => $category_letter,
                'basic_price' => $basic_price,
            ];
        }

        fclose($handle);

        return $rows;
    }


        /**
     * Perform detailed matching and return full product info + score.
     */
    function mc_match_product_detailed($drug_name, $pack_size, $form, $csv_vmp = '', $csv_vmpp = '')
    {
        // Strict match now includes DM+D matching
        $product_id = mc_match_product_strict($drug_name, $pack_size, $form, $csv_vmp, $csv_vmpp);

        if ($product_id > 0) {
            return [
                'product_id' => $product_id,
                'name'       => get_the_title($product_id),
                'strength'   => get_post_meta($product_id, 'mc_strength', true),
                'form'       => get_post_meta($product_id, 'mc_category', true),
                'pack_size'  => get_post_meta($product_id, 'mc_pack_size', true),
                'code'       => get_post_meta($product_id, 'mc_product_code', true),
                'score'      => 0
            ];
        }

        return [
            'product_id' => 0,
            'name'       => '',
            'strength'   => '',
            'form'       => '',
            'pack_size'  => '',
            'code'       => '',
            'score'      => 999
        ];
    }

    function mc_match_product_strict($csv_name, $csv_pack, $csv_form, $csv_vmp = '', $csv_vmpp = '')
    {
        global $wpdb;

        /**
         * 1. DM+D MATCHING FIRST
         */

        // Try VMPP (strongest)
        if (!empty($csv_vmpp)) {
            $product_id = mc_find_product_by_vmpp($csv_vmpp);
            if ($product_id > 0) {
                return $product_id;
            }
        }

        // Try VMP (fallback)
        if (!empty($csv_vmp)) {
            $product_id = mc_find_product_by_vmp($csv_vmp);
            if ($product_id > 0) {
                return $product_id;
            }
        }

        /**
         * 2. FALLBACK TO EXISTING STRICT MATCHING
         */

        // Normalise CSV
        $csv_norm = strtolower($csv_name);

        // Extract ingredient
        $csv_ai = strtolower(strtok($csv_norm, ' '));

        // Extract strength
        preg_match('/(\d+mg|\d+g|\d+ml)/', $csv_norm, $strength_match);
        $csv_strength = strtolower($strength_match[1] ?? '');

        // Extract pack size
        $csv_pack = intval($csv_pack);

        // Extract base form
        $csv_form_norm = strtolower($csv_form);

        // Strict formulation modifiers
        $modifiers = [
            'dispersible',
            'gastro-resistant',
            'enteric-coated',
            'effervescent',
            'chewable',
            'orodispersible',
            'modified-release',
            'slow-release',
            'prolonged-release',
            'delayed-release'
        ];

        // CSV modifier
        $csv_modifier = '';
        foreach ($modifiers as $m) {
            if (strpos($csv_norm, $m) !== false) {
                $csv_modifier = $m;
                break;
            }
        }

        // Fetch all MC products
        $products = $wpdb->get_results("
            SELECT ID, post_title
            FROM {$wpdb->posts}
            WHERE post_type = 'mc_product'
            AND post_status = 'publish'
        ");

        foreach ($products as $p) {

            $name       = strtolower($p->post_title);
            $strength   = strtolower(get_post_meta($p->ID, 'mc_strength', true));
            $pack       = intval(get_post_meta($p->ID, 'mc_pack_size', true));
            $desc       = strtolower(get_post_meta($p->ID, 'mc_description', true));

            // Ingredient must match
            if (strpos($name, $csv_ai) === false) continue;

            // Strength must match
            if ($csv_strength !== $strength) continue;

            // Pack size must match
            if ($csv_pack !== $pack) continue;

            // Base form must match
            if (strpos($name, $csv_form_norm) === false) continue;

            // MC modifier detection
            $mc_modifier = '';
            foreach ($modifiers as $m) {
                if (strpos($name, $m) !== false || strpos($desc, $m) !== false) {
                    $mc_modifier = $m;
                    break;
                }
            }

            // STRICT modifier rules
            if ($csv_modifier && !$mc_modifier) continue;
            if ($mc_modifier && !$csv_modifier) continue;
            if ($csv_modifier && $mc_modifier && $csv_modifier !== $mc_modifier) continue;

            // FULL STRICT MATCH
            return $p->ID;
        }

        return 0;
    }



    /**
     * Find product by DM+D VMPP code.
     */
    function mc_find_product_by_vmpp($vmpp_code)
    {
        global $wpdb;

        if (empty($vmpp_code)) return 0;

        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id 
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'mc_dmd_vmpp'
            AND meta_value = %s
            LIMIT 1",
            $vmpp_code
        ));

        return intval($product_id);
    }

    /**
     * Find product by DM+D VMP code.
     */
    function mc_find_product_by_vmp($vmp_code)
    {
        global $wpdb;

        if (empty($vmp_code)) return 0;

        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id 
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'mc_dmd_vmp'
            AND meta_value = %s
            LIMIT 1",
            $vmp_code
        ));

        return intval($product_id);
    }

    /*------------------------------------------------
    GET IMAP CONFIGURATION BASED ON ENVIRONMENT
    -------------------------------------------------*/
    function mc_get_imap_config() {
        switch (MC_ENV) {
            case 'development':
                return [
                    'host' => MC_IMAP_HOST_LOCAL,
                    'port' => MC_IMAP_PORT_LOCAL,
                    'user' => MC_IMAP_USER_LOCAL,
                    'pass' => MC_IMAP_PASS_LOCAL,
                ];
            case 'test':
                return [
                    'host' => MC_IMAP_HOST_TEST,
                    'port' => MC_IMAP_PORT_TEST,
                    'user' => MC_IMAP_USER_TEST,
                    'pass' => MC_IMAP_PASS_TEST,
                ];
            case 'production':
            default:
                return [
                    'host' => MC_IMAP_HOST_PROD,
                    'port' => MC_IMAP_PORT_PROD,
                    'user' => MC_IMAP_USER_PROD,
                    'pass' => MC_IMAP_PASS_PROD,
                ];
        }
    }

        /**
     * Parse HTML table from Concession email
     * Expected columns:
     *  - Medicine
     *  - Pack Size
     *  - Price concession (£x.xx)
     */
    function mc_parse_concession_email_html($html) {

        $rows = [];

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Find the first table
        $table = $xpath->query('//table')->item(0);
        if (!$table) return [];

        foreach ($table->getElementsByTagName('tr') as $tr) {

            $cells = $tr->getElementsByTagName('td');
            if ($cells->length < 3) continue;

            $drug_name = trim($cells->item(0)->textContent);
            $pack_size = trim($cells->item(1)->textContent);
            $price_raw = trim($cells->item(2)->textContent);

            if ($drug_name === '') continue;

            // Remove £ symbol
            $price_clean = str_replace(['£', ' '], '', $price_raw);

            // Convert to decimal (float)
            $price_decimal = floatval($price_clean);

            $rows[] = [
                'drug_name' => $drug_name,
                'pack_size' => intval($pack_size),
                'form'      => '',
                'price'     => $price_decimal   // already in pounds
            ];
        }

        return $rows;
    }






















