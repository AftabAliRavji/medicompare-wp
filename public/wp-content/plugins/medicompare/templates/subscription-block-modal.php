<?php

$user_id = get_current_user_id();

$pharmacy_id = mc_get_pharmacy_id_by_user(
    $user_id
);

$pharmacy_name = get_the_title(
    $pharmacy_id
);

$subscription_amount = get_post_meta(
    $pharmacy_id,
    '_mc_subscription_amount',
    true
);

$subscription_status = get_post_meta(
    $pharmacy_id,
    '_mc_subscription_status',
    true
);

$restricted = get_post_meta(
    $pharmacy_id,
    '_mc_account_restricted',
    true
);

$is_restricted = ($restricted === 'yes');

?>

<div class="mc-subscription-modal-overlay">
    <div class="mc-subscription-modal">

        <?php if ($is_restricted): ?>

            <h2>Subscription Issue Detected</h2>

            <p style="color:#c62828;font-weight:bold;">
                Access to your account has been temporarily restricted.
            </p>

            <p>
                We have detected an issue with your SourceMed Pharma
                subscription and access has been restricted whilst the
                issue is reviewed.
            </p>

            <p>
                Please contact our support team for assistance.
            </p>

            <p>
                <strong>Email:</strong><br>
                support@sourcemedpharma.com
            </p>

            <p>
                <strong>Reference:</strong><br>
                Pharmacy ID: <?php echo (int) $pharmacy_id; ?><br>
                Pharmacy Name: <?php echo esc_html($pharmacy_name); ?>
            </p>

            <p style="margin-top:20px;">
                <a
                    href="<?php echo esc_url(
                        wp_logout_url(
                            site_url('/pharmacy/login/')
                        )
                    ); ?>"
                    class="mc-topbar-btn mc-logout-btn">
                    Logout
                </a>
            </p>


        <?php else: ?>

            <h2>Subscription Required</h2>

            <p style="color:red;font-weight:bold;">
                Your subscription has expired or your trial has ended.
            </p>

            <div class="mc-subscription-summary">

                <div class="mc-subscription-price">
                    <span>Subscription:</span>
                    £<?php echo number_format(
                        (float) $subscription_amount,
                        2
                    ); ?>
                    <span>per month</span>
                </div>

                <p>
                    Please click below and you will be redirected to
                    GoCardless to authorise your Direct Debit mandate.
                </p>

            </div>

            <button
                id="mc-renew-subscription"
                class="mc-btn-primary"
            >
                Set Up Subscription
            </button>

        <?php endif; ?>

    </div>
</div>