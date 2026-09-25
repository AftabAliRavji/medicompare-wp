<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode: [mc_terms_of_use]
 */
function mc_terms_of_use_shortcode() {

    $logo_url = plugin_dir_url(dirname(__FILE__, 3)) .
        'assets/img/welcome-page/logo.png';

    $privacy_url = home_url('/privacy-policy/');

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

                <h1 class="mc-portal-title">Terms of Use</h1>

                <p class="mc-static-page-introduction">
                    Please read these Terms of Use carefully before using this website.
                </p>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">1. About these Terms</h2>

                    <p>
                        These Terms of Use apply to your use of the Source Med Pharma
                        website and registration of interest in our services.
                    </p>

                    <p>
                        The website is operated by Source Med Pharma Ltd, a company
                        registered in England and Wales under company number 17464268,
                        with its registered office at 15 Catherine Close, Peterborough,
                        PE2 7FD (“Source Med Pharma”, “we”, “us” or “our”).
                    </p>

                    <p>
                        Source Med Pharma is developing services specifically for UK
                        pharmacies.
                    </p>

                    <p>
                        By using this website or submitting a registration of interest,
                        you agree to these Terms of Use. If you do not agree to these
                        Terms, you should not use the website or submit a registration
                        of interest.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">2. Registration of Interest</h2>

                    <p>
                        The registration form on this website allows UK pharmacies to
                        register their interest in Source Med Pharma and receive further
                        information about our services.
                    </p>

                    <p>Submitting your details:</p>

                    <ul>
                        <li>does not create a contract for the provision of any products or services;</li>
                        <li>does not guarantee access to the Source Med Pharma platform;</li>
                        <li>does not constitute an order or purchase; and</li>
                        <li>does not create any obligation for you to use Source Med Pharma.</li>
                    </ul>

                    <p>
                        We may contact you using the information you provide in connection
                        with your registration of interest.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">3. Information Provided</h2>

                    <p>
                        We aim to provide useful and accurate information about Source Med
                        Pharma and our planned services.
                    </p>

                    <p>
                        However, information on this website is provided for general
                        information purposes and may change as our services develop.
                    </p>

                    <p>
                        We do not guarantee that information on the website will always be
                        complete, accurate or up to date.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">4. Website Use</h2>

                    <p>You agree to use this website lawfully and responsibly.</p>
                    <p>You must not:</p>

                    <ul>
                        <li>use the website for any unlawful purpose;</li>
                        <li>knowingly introduce malicious software or harmful material;</li>
                        <li>attempt to gain unauthorised access to the website or our systems; or</li>
                        <li>interfere with the operation or security of the website.</li>
                    </ul>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">5. Intellectual Property</h2>

                    <p>
                        Unless otherwise stated, the content, branding, graphics, design,
                        text and other materials on this website belong to Source Med
                        Pharma Ltd or are used with permission.
                    </p>

                    <p>
                        You may view and use the website for your own legitimate business purposes.
                    </p>

                    <p>
                        You must not reproduce or commercially exploit our content without
                        our prior written permission.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">6. Privacy</h2>

                    <p>
                        We collect and use personal information submitted through the
                        registration form in accordance with our Privacy Policy.
                    </p>

                    <p>
                        <a href="<?php echo esc_url($privacy_url); ?>">
                            View our Privacy Policy
                        </a>.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">7. Changes to the Website</h2>

                    <p>We may change, update or remove content from the website at any time.</p>

                    <p>
                        We may also change the nature, timing or availability of our planned services.
                    </p>

                    <p>
                        Registering your interest does not guarantee that any particular
                        service or feature will be launched or made available.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">8. Website Availability</h2>

                    <p>
                        We will make reasonable efforts to keep the website available but
                        do not guarantee that it will always be available or operate
                        without interruption.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">9. Liability</h2>

                    <p>
                        Nothing in these Terms excludes or limits liability that cannot
                        lawfully be excluded or limited.
                    </p>

                    <p>
                        To the extent permitted by law, Source Med Pharma Ltd is not
                        responsible for losses arising from your use of, or inability to
                        use, this website or from reliance on information provided on the website.
                    </p>

                    <p>
                        Nothing in these Terms affects any statutory rights that cannot
                        lawfully be excluded.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">10. Governing Law</h2>

                    <p>These Terms are governed by the laws of England and Wales.</p>

                    <p>
                        The courts of England and Wales will have jurisdiction over
                        disputes arising in connection with these Terms, subject to any
                        mandatory rights that apply.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">11. Contact</h2>

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
    'mc_terms_of_use',
    'mc_terms_of_use_shortcode'
);
