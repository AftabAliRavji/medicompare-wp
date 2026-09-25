<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode: [mc_cookie_policy]
 */
function mc_cookie_policy_shortcode() {

    $logo_url = plugin_dir_url(dirname(__FILE__, 3)) .
        'assets/img/welcome-page/logo.png';

    $privacy_policy_url = home_url('/privacy-policy/');
    $terms_url          = home_url('/terms-of-use/');

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

                <h1 class="mc-portal-title">Cookie Policy</h1>

                <p class="mc-static-page-introduction">
                    This Cookie Policy explains how Source Med Pharma Ltd uses cookies
                    and similar technologies when you visit our website.
                </p>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">1. About this Cookie Policy</h2>

                    <p>
                        This Cookie Policy explains how Source Med Pharma Ltd
                        (“Source Med Pharma”, “SMP”, “we”, “us” or “our”) uses cookies
                        and similar technologies when you visit our website.
                    </p>

                    <p>
                        This policy should be read alongside our
                        <a href="<?php echo esc_url($privacy_policy_url); ?>">
                            Privacy Policy
                        </a>
                        and
                        <a href="<?php echo esc_url($terms_url); ?>">
                            Terms of Use
                        </a>.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">2. What are cookies?</h2>

                    <p>
                        Cookies are small files that may be placed on your device when
                        you visit a website. Similar technologies may also be used to
                        collect information about how a website is accessed and used.
                    </p>

                    <p>
                        We use cookies and similar technologies only where permitted by
                        applicable law.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">3. How we use analytics</h2>

                    <p>
                        We use analytics technology to collect statistical information
                        about how our website is used, so that we can understand overall
                        visitor activity and improve our website and services.
                    </p>

                    <p>The information collected may include:</p>

                    <ul>
                        <li>the number of visitors and visits;</li>
                        <li>pages visited and general navigation;</li>
                        <li>interactions with the website;</li>
                        <li>how visitors arrived at our website;</li>
                        <li>device, browser and operating system information; and</li>
                        <li>approximate geographic information, where appropriate.</li>
                    </ul>

                    <p>
                        The analytics information is used for statistical purposes only
                        and is intended to provide aggregate information about website
                        usage.
                    </p>

                    <p>
                        We do not use analytics to identify, track or profile individual
                        visitors.
                    </p>

                    <p>
                        We do not intentionally send personal information submitted
                        through our registration form, such as names, email addresses,
                        telephone numbers or pharmacy details, to our analytics system.
                    </p>

                    <p>
                        We do not use analytics for advertising, remarketing or
                        personalised advertising.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">4. Opting out of analytics</h2>

                    <p>
                        If you do not want your visit to be included in our website
                        analytics, you can contact us at
                        <a href="mailto:support@sourcemedpharma.co.uk">
                            support@sourcemedpharma.co.uk
                        </a>
                        and ask to opt out of analytics.
                    </p>

                    <p>
                        We will take your request into account when calculating our
                        website statistics.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">5. Other cookies and technologies</h2>

                    <p>
                        Our website may use cookies and similar technologies that are
                        necessary for the operation, security and functionality of the
                        website.
                    </p>

                    <p>These may include technologies required to:</p>

                    <ul>
                        <li>operate the website;</li>
                        <li>maintain website security;</li>
                        <li>enable forms and other essential functionality; and</li>
                        <li>remember necessary preferences.</li>
                    </ul>

                    <p>
                        We will provide further information where required if the types
                        of cookies or technologies we use change.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">6. Third-party services</h2>

                    <p>
                        We may use third-party services to help us operate our website
                        and understand its overall use.
                    </p>

                    <p>
                        Where third-party services are used for analytics, they are
                        intended to support the statistical purposes described in this
                        Cookie Policy.
                    </p>

                    <p>
                        We do not intentionally provide third-party analytics services
                        with personal information submitted through our registration
                        forms.
                    </p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">7. Changes to this Cookie Policy</h2>

                    <p>
                        We may update this Cookie Policy from time to time as our website
                        and services develop.
                    </p>

                    <p>The latest version will always be available on our website.</p>
                </section>

                <section class="mc-static-page-section">
                    <h2 class="mc-card-title">8. Contact</h2>

                    <address class="mc-static-page-address">
                        <strong>Source Med Pharma Ltd</strong><br>
                        Email:
                        <a href="mailto:support@sourcemedpharma.co.uk">
                            support@sourcemedpharma.co.uk
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
    'mc_cookie_policy',
    'mc_cookie_policy_shortcode'
);
