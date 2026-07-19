<?php
if (!defined('ABSPATH')) exit;

/**
 * Enqueue CSS for this page only
 */
add_action('wp_enqueue_scripts', function() {

    if (!is_page('welcome-signup')) {
        return;
    }

    wp_enqueue_style(
        'mc-welcome-signup',
        plugin_dir_url(__FILE__) . '../../assets/css/welcome-signup.css',
        [],
        filemtime(plugin_dir_path(__FILE__) . '../../assets/css/welcome-signup.css')
    );
});

/**
 * Shortcode: [mc_welcome_signup]
 */
function mc_welcome_signup_shortcode() {

    $success_message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mc_interest_submit'])) {

        $pharmacy_name  = sanitize_text_field($_POST['pharmacy_name'] ?? '');
        $contact_name   = sanitize_text_field($_POST['contact_name'] ?? '');
        $contact_number = sanitize_text_field($_POST['contact_number'] ?? '');
        $contact_email  = sanitize_email($_POST['contact_email'] ?? '');

        if ($pharmacy_name && $contact_name && $contact_number && $contact_email) {

            global $wpdb;
            $table = $wpdb->prefix . 'mc_interest';

            $wpdb->insert($table, [
                'pharmacy_name'  => $pharmacy_name,
                'contact_name'   => $contact_name,
                'contact_number' => $contact_number,
                'contact_email'  => $contact_email,
                'created_at'     => current_time('mysql'),
            ]);

            if (!class_exists('MediCompare_Email_Engine')) {
                require_once plugin_dir_path(__FILE__) . '../email-functions.php';
            }

            if (class_exists('MediCompare_Email_Engine')) {
                $engine = new MediCompare_Email_Engine();
                if (method_exists($engine, 'send_welcome_signup_notification')) {
                    $engine->send_welcome_signup_notification(
                        $pharmacy_name,
                        $contact_name,
                        $contact_number,
                        $contact_email
                    );
                }
            }

            $success_message = "Thank you — our onboarding team will contact you shortly.";
        }
    }

    ob_start();
    $mc_assets = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/img/';
    $mc_video  = plugin_dir_url(__FILE__) . '../../assets/video/Demo_signUp.mp4';
    $mc_poster = plugin_dir_url(__FILE__) . '../../assets/video/demo-poster.png';
    ?>
    <div class="mc-welcome-container">

        <!-- LOGO -->
        <div class="mc-portal-header">
            <div class="mc-portal-logo">
                <img src="<?php echo $mc_assets . 'logo.png'; ?>" alt="MediCompare">
            </div>
        </div>

        <!-- HERO -->
        <section class="mc-hero">
            <h1>Welcome to MediCompare</h1>
            <p>Your pharmacy’s smarter way to compare suppliers, reduce costs, and streamline ordering.</p>

            <p class="mc-hero-action">
                Please complete the <strong>form on the right</strong> so our onboarding team can follow up with you regarding next steps.
            </p>
        </section>

        <!-- ⭐ VIDEO + FORM SIDE-BY-SIDE -->
        <section class="mc-video-section">

            <div class="mc-video-grid">

                <!-- LEFT: VIDEO -->
                <div class="mc-video-container">
                    <div class="mc-video-placeholder">

                        <video class="mc-video-iframe" controls poster="<?php echo $mc_poster; ?>" id="mcDemoVideo">
                            <source src="<?php echo $mc_video; ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const vid = document.getElementById('mcDemoVideo');
                            if (vid) {
                                vid.addEventListener('ended', function() {
                                    vid.currentTime = 0;   // ⭐ resets to start
                                });
                            }
                        });
                        </script>

                    </div>
                </div>

                <!-- RIGHT: FORM -->
                <div class="mc-video-form">

                    <h2 class="mc-form-heading mc-card-title">Sign Up Interest</h2>

                    <?php if ($success_message): ?>
                        <p class="mc-success"><?php echo esc_html($success_message); ?></p>
                    <?php endif; ?>

                    <div class="mc-form-card">
                        <form method="post" class="mc-interest-form">

                            <div class="mc-form-row">
                                <label>Pharmacy Name</label>
                                <input type="text" name="pharmacy_name" required>
                            </div>

                            <div class="mc-form-row">
                                <label>Contact Name</label>
                                <input type="text" name="contact_name" required>
                            </div>

                            <div class="mc-form-row mc-form-row-half">
                                <div>
                                    <label>Contact Number</label>
                                    <input type="text" name="contact_number" required>
                                </div>
                                <div>
                                    <label>Contact Email</label>
                                    <input type="email" name="contact_email" required>
                                </div>
                            </div>

                            <div class="mc-form-row">
                                <button type="submit" name="mc_interest_submit">Submit Interest</button>
                            </div>

                        </form>
                    </div>

                </div>

            </div>

        </section>

        <!-- FEATURES (unchanged) -->
        <section class="mc-features">
            <h2 class="mc-card-title">What MediCompare Offers</h2>

            <div class="mc-feature-grid">

                <div class="mc-feature-card">
                    <h3>Instant Supplier Comparison</h3>
                    <p>Search products and instantly compare prices, stock levels, and suppliers.</p>
                    <div class="mc-feature-preview">
                        <div class="mc-feature-preview-box">
                            <span class="mc-preview-title">Search & Compare</span>
                            <div class="mc-preview-bar"></div>
                            <div class="mc-preview-lines">
                                <span></span><span></span><span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mc-feature-card">
                    <h3>Smart Ordering</h3>
                    <p>Add items to your pending order and transfer them when ready.</p>
                    <div class="mc-feature-preview">
                        <div class="mc-feature-preview-box">
                            <span class="mc-preview-title">Pending Order List</span>
                            <div class="mc-preview-lines">
                                <span></span><span></span><span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mc-feature-card">
                    <h3>Full Audit Trail</h3>
                    <p>Track transferred orders by date, supplier, and product.</p>
                    <div class="mc-feature-preview">
                        <div class="mc-feature-preview-box">
                            <span class="mc-preview-title">Transferred Orders</span>
                            <div class="mc-preview-table">
                                <span></span><span></span><span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mc-feature-card mc-feature-card-autoheight">
                    <h3>Mobile Friendly</h3>
                    <p>Use MediCompare on any device — desktop, tablet, or mobile.</p>
                    <div class="mc-feature-preview">
                        <div class="mc-feature-preview-box mc-preview-mobile">
                            <div class="mc-mobile-screen"></div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('mc_welcome_signup', 'mc_welcome_signup_shortcode');
