<?php

/**
 * Shortcode: [mc_pharmacy_portal]
 * Renders the main Pharmacy landing page with:
 * - Hero section
 * - Login form
 * - Register CTA
 * - Auto-redirect if logged in
 */

add_shortcode('mc_pharmacy_portal', function () {

    // If logged in → redirect to dashboard
    if (is_user_logged_in()) {
        wp_redirect('/pharmacy/dashboard/');
        exit;
    }

    ob_start();
    ?>

    <div class="mc-portal">

        <section class="hero">
            <h1>MedicCompare Pharmacy Portal</h1>
            <p>Manage orders, compare prices, streamline your workflow.</p>
        </section>

        <section class="login-section">
            <h2>Login to your pharmacy account</h2>

            <?php
            // Reuse your existing login shortcode
            echo do_shortcode('[mc_pharmacy_login]');
            ?>

            <p class="register-cta">
                New pharmacy?
                <a href="/pharmacy/pharmacy-registration/">Register here</a>
            </p>
        </section>

        <section class="features">
            <h2>Why join MedicCompare?</h2>
            <ul>
                <li>Compare medicine prices instantly</li>
                <li>Manage orders from one dashboard</li>
                <li>Automated notifications</li>
                <li>Fast onboarding</li>
            </ul>
        </section>

    </div>

    <?php
    return ob_get_clean();
});
