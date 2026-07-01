<?php

/**
 * Shortcode: [mc_pharmacy_portal]
 * Professional SaaS-style Pharmacy Portal
 * - Logo header
 * - Left: Feature cards + onboarding steps
 * - Right: Login card
 * - Auto-redirect if logged in
 * - Admin-safe preview
 */

add_shortcode('mc_pharmacy_portal', function () {

    if (is_admin()) {
        return '<div class="mc-admin-preview">Pharmacy Portal Preview</div>';
    }

    if (is_user_logged_in()) {
        wp_redirect('/pharmacy/search/');
        exit;
    }

    ob_start();

    // Base plugin asset URL
    $mc_assets = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/img/';
    include dirname(__FILE__, 3) . '/templates/header-pharmacy.php';
    ?>


    <!-- MAIN TWO-COLUMN LAYOUT -->
    <div class="mc-portal-container">

        <!-- LEFT SIDE: FEATURE CARDS + ONBOARDING -->
        <div class="mc-portal-left">

            <h1 class="mc-portal-title">MediCompare Pharmacy Portal</h1>
            <p class="mc-portal-subtitle">A modern platform built for independent pharmacies.</p>

            <div class="mc-feature-row">
                <div class="mc-feature-card">
                    <img src="<?php echo $mc_assets . 'icon-search.svg'; ?>" class="mc-feature-icon">
                    <h3>Instant Price Comparison</h3>
                    <p>Search thousands of medicines and compare supplier prices in seconds.</p>
                </div>

                <div class="mc-feature-card">
                    <img src="<?php echo $mc_assets . 'icon-orders.svg'; ?>" class="mc-feature-icon">
                    <h3>Unified Order Management</h3>
                    <p>Track, manage, and fulfil orders from one clean dashboard.</p>
                </div>
            </div>


            <!-- FAST ONBOARDING + 3-STEP ONBOARDING SIDE-BY-SIDE -->
            <div class="mc-onboarding-row">

                <!-- LEFT: Fast Onboarding Card -->
                <div class="mc-feature-card mc-feature-onboarding">
                    <img src="<?php echo $mc_assets . 'icon-onboarding.svg'; ?>" class="mc-feature-icon">
                    <h3>Fast Onboarding</h3>
                </div>

                <!-- ARROW -->
                <div class="mc-onboarding-arrow">→</div>

                <!-- RIGHT: 3-Step Onboarding -->
                <div class="mc-onboarding">

                    <p class="mc-onboarding-intro">
                        A simple 3-step process to get your pharmacy verified and onboarded.
                    </p>

                    <div class="mc-onboarding-step">
                        <span class="mc-step-number">1</span>
                        <h4>Register Your Pharmacy</h4>
                        <p>Create your pharmacy profile with essential business details. Once submitted, your account enters a <strong>pending review</strong> state while verification checks are carried out.</p>
                    </div>

                    <div class="mc-onboarding-step">
                        <span class="mc-step-number">2</span>
                        <h4>Verification & Approval</h4>
                        <p>After verification is complete, you’ll receive an email confirming your pharmacy has been approved. You can then log in and access the full MedicCompare portal.</p>
                    </div>

                    <div class="mc-onboarding-step">
                        <span class="mc-step-number">3</span>
                        <h4>Start Your Free 30‑Day Trial</h4>
                        <p>Explore all features — price comparison, order management, supplier insights — completely free for 30 days. After the trial, a <strong>monthly subscription</strong> applies. For full details, see the upcoming <a href="/pharmacy/pharmacy-guidelines/">Pharmacy Guidelines</a> page.</p>
                    </div>

                </div>

            </div>

        </div>

        <!-- RIGHT SIDE: LOGIN CARD -->
        <div class="mc-portal-right">
            <div class="mc-portal-card">

                <h2 class="mc-card-title">Pharmacy Login</h2>

                <?php
                echo do_shortcode('[mc_pharmacy_login]');
                ?>

            </div>
        </div>

    </div>

    <?php
    return ob_get_clean();
});
