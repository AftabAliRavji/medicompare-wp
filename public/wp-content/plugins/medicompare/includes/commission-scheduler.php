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
        global $wpdb;

        $suppliers = $wpdb->get_results("
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm
                ON pm.post_id = p.ID
               AND pm.meta_key = '_mc_auto_send_commission'
               AND pm.meta_value = 'yes'
            WHERE p.post_type = 'mc_supplier'
              AND p.post_status = 'publish'
        ");

        if (empty($suppliers)) return;

        $date_to   = current_time('mysql');
        $date_from = date('Y-m-d 00:00:00', strtotime('-7 days'));

        foreach ($suppliers as $supplier) {

            $summary = mc_generate_commission_summary($supplier->ID, $date_from, $date_to);
            if (!$summary) continue;

            $pdf_path   = mc_generate_commission_pdf($summary);
            $email_html = mc_generate_commission_email_html($summary);

            mc_send_commission_email($supplier->ID, $summary, $email_html, $pdf_path);
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
