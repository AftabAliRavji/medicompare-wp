<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode: [mc_privacy_policy]
 */
function mc_privacy_policy_shortcode() {

    $logo_url = plugin_dir_url(dirname(__FILE__, 3)) .
        'assets/img/welcome-page/logo.png';

    $cookie_policy_url = home_url('/cookie-policy/');

    ob_start();
    ?>

    <div class="mc-static-page">

        <div class="mc-static-page-inner">

            <div class="mc-static-page-header mc-portal-header">

                <!--<a
                    href="<?php echo esc_url(home_url('/')); ?>"
                    class="mc-static-page-logo-link"
                    aria-label="Return to the Source Med Pharma homepage"
                >-->
                    <img
                        src="<?php echo esc_url($logo_url); ?>"
                        alt="Source Med Pharma"
                        class="mc-static-page-logo"
                    >
                </a>

            </div>

            <main class="mc-static-page-content">

                <h1 class="mc-portal-title">Privacy Policy</h1>

                <p class="mc-static-page-introduction">
                    This Privacy Policy explains how Source Med Pharma Ltd collects,
                    uses and protects your personal information.
                </p>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">1. Who we are</h2>

                    <p>
                        Source Med Pharma Ltd (“Source Med Pharma”, “SMP”, “we”, “us”
                        or “our”) respects your privacy.
                    </p>

                    <p>
                        Source Med Pharma Ltd is a company registered in England and
                        Wales under company number 17464268, with its registered office
                        at 15 Catherine Close, Peterborough, PE2 7FD.
                    </p>

                    <p>
                        For questions about your personal information, please contact us at:
                    </p>

                    <p>
                        Email:
                        <a href="mailto:support@sourcemdpharma.co.uk">
                            support@sourcemdpharma.co.uk
                        </a>
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">2. What information we collect</h2>

                    <p>When you register your interest, we may collect:</p>

                    <ul>
                        <li>your name;</li>
                        <li>pharmacy name;</li>
                        <li>pharmacy postcode;</li>
                        <li>email address;</li>
                        <li>telephone number; and</li>
                        <li>any other information you choose to provide.</li>
                    </ul>

                    <p>
                        We may also collect basic technical information when you use our
                        website, such as your IP address and information about how you use
                        the website.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">3. How we use your information</h2>

                    <p>We use the information you provide to:</p>

                    <ul>
                        <li>process your registration of interest;</li>
                        <li>contact you about Source Med Pharma and our planned services;</li>
                        <li>respond to enquiries;</li>
                        <li>understand interest in our services; and</li>
                        <li>maintain the security and operation of our website.</li>
                    </ul>

                    <p>
                        We will not use your information for purposes that are incompatible
                        with those described above without providing further information
                        where required.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">4. Who we share your information with</h2>

                    <p>
                        We may use trusted third-party service providers to help us operate
                        our website and manage registrations of interest.
                    </p>

                    <p>
                        We may also disclose information where required by law or where
                        necessary to protect our legal rights.
                    </p>

                    <p>We do not sell your personal information to third parties.</p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">5. How long we keep your information</h2>

                    <p>
                        We will keep your information only for as long as reasonably
                        necessary to manage your registration of interest and for legitimate
                        business, legal or regulatory purposes.
                    </p>

                    <p>
                        When information is no longer required, we will securely delete or
                        anonymise it where appropriate.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">6. Your rights</h2>

                    <p>Under UK data protection law, you may have rights to:</p>

                    <ul>
                        <li>request access to the information we hold about you;</li>
                        <li>ask us to correct inaccurate information;</li>
                        <li>ask us to delete your information in certain circumstances;</li>
                        <li>ask us to restrict how we use your information; and</li>
                        <li>object to certain types of processing.</li>
                    </ul>

                    <p>
                        If we rely on your consent for any particular use of your
                        information, you can withdraw that consent at any time.
                    </p>

                    <p>To exercise your rights, contact us at:</p>

                    <p>
                        Email:
                        <a href="mailto:support@sourcemdpharma.co.uk">
                            support@sourcemdpharma.co.uk
                        </a>
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">7. Cookies</h2>

                    <p>
                        Our website may use cookies and similar technologies to help it
                        operate and, where applicable, understand how visitors use the
                        website.
                    </p>

                    <p>
                        Further information will be provided in our
                        <a href="<?php echo esc_url($cookie_policy_url); ?>">
                            Cookie Policy
                        </a>
                        where required.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">8. Changes to this Privacy Policy</h2>

                    <p>
                        We may update this Privacy Policy from time to time as our website
                        and services develop.
                    </p>

                    <p>The latest version will always be available on our website.</p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">9. Contact us</h2>

                    <address class="mc-static-page-address">
                        <strong>Source Med Pharma Ltd</strong><br>
                        Email:
                        <a href="mailto:support@sourcemdpharma.co.uk">
                            support@sourcemdpharma.co.uk
                        </a>
                    </address>
                </section>

            </main>

        </div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode(
    'mc_privacy_policy',
    'mc_privacy_policy_shortcode'
);
