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

    // Invoice sequence
    $sequence = intval(get_post_meta($supplier_id, 'mc_invoice_sequence', true));
    $sequence++;
    update_post_meta($supplier_id, 'mc_invoice_sequence', $sequence);

    $sequence_str = str_pad($sequence, 4, '0', STR_PAD_LEFT);

    $from_date = new DateTime($summary['date_from']);
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

    // Logo URL (same as your original)
    $plugin_root = dirname(__FILE__, 1);
    $logo_url = plugins_url('assets/img/logo.png', $plugin_root);

    $orders = $summary['orders'];
    $total_supplier_amount = $summary['total_supplier_amount'];
    $total_commission      = $summary['total_commission'];
    $from                  = $summary['date_from'];
    $to                    = $summary['date_to'];
    $pay_date              = $summary['pay_date'];

    // ORIGINAL HTML STRUCTURE (copied from your handler)
    ob_start();
    ?>
    <html>
    <head>
        <style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ccc; padding: 6px; }
            th { background: #f5f5f5; }
        </style>
    </head>
    <body>

    <img src="<?php echo $logo_url; ?>" style="max-height:60px;"><br><br>

    <h2>Invoice: <?php echo $invoice_number; ?></h2>
    <p><strong>Period:</strong> <?php echo $from; ?> to <?php echo $to; ?></p>

    <h3>Supplier Details</h3>
    <p><strong>Supplier:</strong> <?php echo $supplier_name; ?></p>
    <p><strong>Manager:</strong> <?php echo $supplier_manager; ?></p>
    <p><strong>Address:</strong> <?php echo $supplier_address; ?></p>
    <p><strong>Email:</strong> <?php echo $supplier_email; ?></p>
    <p><strong>Phone:</strong> <?php echo $supplier_phone; ?></p>

    <h3>Summary</h3>
    <table>
        <tr><th>Total Supplier Amount</th><td>£<?php echo number_format($total_supplier_amount, 2); ?></td></tr>
        <tr><th>Total Commission to be paid to MediCompare</th><td>£<?php echo number_format($total_commission, 2); ?></td></tr>
    </table>

    <h3>Payable To</h3>
    <table>
        <tr><th>Payment Due Date</th><td><?php echo esc_html($pay_date); ?></td></tr>
        <tr><th>Account Name</th><td><?php echo esc_html($bank_acc_name); ?></td></tr>
        <tr><th>Bank</th><td><?php echo esc_html($bank_name); ?></td></tr>
        <tr><th>Account Number</th><td><?php echo esc_html($bank_acc_number); ?></td></tr>
        <tr><th>Sort Code</th><td><?php echo esc_html($bank_sort_code); ?></td></tr>
    </table>

    <h3>Order Breakdown</h3>
    <table>
        <tr>
            <th>Master Order #</th>
            <th>Sub Order #</th>
            <th>Date</th>
            <th>Supplier Total</th>
            <th>Commission</th>
            <th>Commission %</th>
        </tr>

        <?php foreach ($orders as $row): ?>
            <tr>
                <td><?php echo $row['master_order']; ?></td>
                <td><?php echo $row['sub_order']; ?></td>
                <td><?php echo $row['date']; ?></td>
                <td>£<?php echo number_format($row['supplier_total'], 2); ?></td>
                <td>£<?php echo number_format($row['commission'], 2); ?></td>
                <td><?php echo $row['commission_pct']; ?>%</td>
            </tr>
        <?php endforeach; ?>
    </table>

    <br><br>
    <p style="text-align:center; font-size:10px;">
        Thank you for working with MediCompare. This invoice has been generated based on inputs provided.
    </p>

    </body>
    </html>
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

    ob_start();
    ?>
    <div style="font-family: Arial, sans-serif; font-size: 14px; color:#333;">

        <img src="<?php echo $logo_url; ?>" style="max-height:60px; margin-bottom:20px;">

        <h2 style="margin-bottom:10px;">Supplier Commission Report</h2>
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