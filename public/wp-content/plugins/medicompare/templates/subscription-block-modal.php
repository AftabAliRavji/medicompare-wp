<?php

$user_id = get_current_user_id();

error_log(
    'MODAL USER ID: ' .
    $user_id
);

$pharmacy_id = mc_get_pharmacy_id_by_user(
    $user_id
);

error_log(
    'MODAL PHARMACY ID: ' .
    $pharmacy_id
);

$subscription_amount = get_post_meta(
    $pharmacy_id,
    '_mc_subscription_amount',
    true
);

error_log(
    'MODAL SUBSCRIPTION AMOUNT: ' .
    print_r($subscription_amount, true)
);

?>

<div class="mc-subscription-modal-overlay">
    <div class="mc-subscription-modal">

        <h2>Subscription Required</h2>

        <p>
            Your subscription has expired or your trial has ended.
        </p>

        <div class="mc-subscription-summary">

            <div class="mc-subscription-price">
                £<?php echo number_format(
                    (float) get_post_meta(
                        $pharmacy_id,
                        '_mc_subscription_amount',
                        true
                    ),
                    2
                ); ?>
                <span>per month</span>
            </div>

            <p>
                Payment is collected automatically by Direct Debit.
            </p>

            <p>
                You will be redirected to GoCardless to authorise
                your Direct Debit mandate.
            </p>

        </div>

        <button id="mc-renew-subscription" class="mc-btn-primary">
            Set Up Subscription
        </button>

    </div>
</div>