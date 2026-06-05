<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Pharmacy_Verification_Page {

    public function __construct() {
        add_action('admin_post_mc_verify_pharmacy', [$this, 'verify_pharmacy']);
        add_action('admin_post_mc_reject_pharmacy', [$this, 'reject_pharmacy']);
    }

    public function render() {

        $pending = get_posts([
            'post_type'      => 'mc_pharmacy',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => '_mc_status',
            'meta_value'     => 'pending_verification'
        ]);

        echo '<div class="wrap"><h1>Pending Pharmacy Verification</h1>';

        if (isset($_GET['verified'])) {
            echo '<div class="notice notice-success"><p>Pharmacy verified successfully.</p></div>';
        }

        if (isset($_GET['rejected'])) {
            echo '<div class="notice notice-warning"><p>Pharmacy rejected.</p></div>';
        }

        if (empty($pending)) {
            echo '<p>No pharmacies awaiting verification.</p></div>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>
                <th>Pharmacy</th>
                <th>Email</th>
                <th>GPhC</th>
                <th>City</th>
                <th>Actions</th>
              </tr></thead><tbody>';

        foreach ($pending as $post) {
            $email = get_post_meta($post->ID, '_mc_email', true);
            $gphc  = get_post_meta($post->ID, '_mc_gphc_number', true);
            $city  = get_post_meta($post->ID, '_mc_city', true);

            $verify_url = admin_url('admin-post.php?action=mc_verify_pharmacy&post_id=' . $post->ID);
            $reject_url = admin_url('admin-post.php?action=mc_reject_pharmacy&post_id=' . $post->ID);

            echo "<tr>
                    <td>{$post->post_title}</td>
                    <td>{$email}</td>
                    <td>{$gphc}</td>
                    <td>{$city}</td>
                    <td>
                        <a href='{$verify_url}' class='button button-primary'>Verify</a>
                        <a href='{$reject_url}' class='button button-secondary'>Reject</a>
                    </td>
                  </tr>";
        }

        echo '</tbody></table></div>';
    }

    public function verify_pharmacy() {

        if (!current_user_can('manage_options')) wp_die('Not allowed.');

        $post_id = intval($_GET['post_id']);

        update_post_meta($post_id, '_mc_status', 'active');
        update_post_meta($post_id, '_mc_trial_start', time());
        update_post_meta($post_id, '_mc_trial_end', strtotime('+30 days'));

        $email = get_post_meta($post_id, '_mc_email', true);
        $this->send_welcome_email($email);

        wp_redirect(admin_url('admin.php?page=medicompare-pharmacy-verification&verified=1'));
        exit;
    }

    public function reject_pharmacy() {

        if (!current_user_can('manage_options')) wp_die('Not allowed.');

        $post_id = intval($_GET['post_id']);

        update_post_meta($post_id, '_mc_status', 'suspended');

        wp_redirect(admin_url('admin.php?page=medicompare-pharmacy-verification&rejected=1'));
        exit;
    }

    private function send_welcome_email($email) {

        $subject = "Your MediCompare Account is Now Active";
        $message  = "Hello,\n\n";
        $message .= "Your pharmacy account has now been verified and activated.\n\n";
        $message .= "You may now log in and begin using MediCompare.\n\n";
        $message .= "Regards,\nMediCompare Team";

        wp_mail($email, $subject, $message);
    }
}

// Instantiate once so actions are registered on all admin requests
global $mc_pharmacy_verification_page;
$mc_pharmacy_verification_page = new MediCompare_Pharmacy_Verification_Page();

function mc_render_pharmacy_verification_page() {
    global $mc_pharmacy_verification_page;
    $mc_pharmacy_verification_page->render();
}
