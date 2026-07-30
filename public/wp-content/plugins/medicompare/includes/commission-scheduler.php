<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Commission_Scheduler {

    const CRON_HOOK = 'medi_compare_commission_scheduler_event';

    public static function init() {
        add_action('init', [__CLASS__, 'register_cron']);
        add_action(self::CRON_HOOK, [__CLASS__, 'run_scheduler']);
    }

    public static function register_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 3600, 'weekly', self::CRON_HOOK);
        }
    }

    public static function run_scheduler() {

        error_log("Scheduler triggered");

        global $wpdb;

        error_log("Scheduler: fetching suppliers...");

        $suppliers = $wpdb->get_results("
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm
                ON pm.post_id = p.ID
               AND pm.meta_key = 'mc_auto_send_commission_email'
               AND pm.meta_value = 'yes'
            WHERE p.post_type = 'mc_supplier'
              AND p.post_status = 'publish'
        ");

        error_log("Scheduler: suppliers found = " . count($suppliers));

        if (empty($suppliers)) {
            error_log("Scheduler: NO suppliers found with auto-send enabled.");
            return;
        }

        $date_to   = date('Y-m-d');
        $date_from = date('Y-m-d', strtotime('-7 days'));

        error_log("Scheduler: date_from = {$date_from}, date_to = {$date_to}");

        foreach ($suppliers as $supplier) {

            error_log("Scheduler: supplier candidate = " . $supplier->ID);

            // Check auto-send flag explicitly
            $auto_flag = get_post_meta($supplier->ID, '_mc_auto_send_commission', true);
            error_log("Scheduler: supplier {$supplier->ID} auto-send flag = " . $auto_flag);

            // Check duplicate send
            if (mc_has_sent_for_period($supplier->ID, $date_from, $date_to)) {
                error_log("Scheduler SKIPPED supplier {$supplier->ID} - already sent for this period");
                continue;
            }

            // Generate summary
            $summary = mc_generate_commission_summary($supplier->ID, $date_from, $date_to);

            if (!$summary) {
                error_log("Scheduler: supplier {$supplier->ID} has NO orders in period");
                continue;
            }

            error_log("Scheduler: summary generated for supplier {$supplier->ID}");

            // Generate PDF
            //$pdf_path = mc_generate_commission_pdf($summary);
            //error_log("Scheduler: PDF generated for supplier {$supplier->ID} at {$pdf_path}");

            // Generate email HTML
            $email_html = mc_generate_commission_email_html($summary);
            error_log("Scheduler: email HTML generated for supplier {$supplier->ID}");

            // Send email and download pdf
            //mc_send_commission_email($supplier->ID, $summary, $email_html, $pdf_path);

            //send only email
            mc_send_commission_email($supplier->ID, $summary, $email_html);
            error_log("Scheduler SENT report for supplier {$supplier->ID}");
        }
    }
}

MediCompare_Commission_Scheduler::init();

add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['weekly'])) {
        $schedules['weekly'] = [
            'interval' => 7 * 24 * 60 * 60,
            'display'  => __('Once Weekly', 'medicompare')
        ];
    }
    return $schedules;
});
