<?php

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();

$current_url = $_SERVER['REQUEST_URI'];

$is_dashboard    = strpos($current_url, '/dashboard') !== false;
$is_details      = strpos($current_url, '/edit-details') !== false;
$is_subscription = strpos($current_url, '/subscription') !== false;
$is_orders       = strpos($current_url, '/orders') !== false;
$is_search       = strpos($current_url, '/search') !== false;

?>

<div class="mc-portal-shell">

    <div class="mc-portal-header">


        <div class="mc-portal-logo">

            <a href="<?php echo esc_url(site_url('/pharmacy/login/')); ?>">
                <img
                    src="<?php echo esc_url($mc_assets . 'logo.png'); ?>"
                    alt="Source Med Pharma"
                >
            </a>

        </div>

        <nav class="mc-portal-nav">
    <a
        href="<?php echo esc_url(site_url('/pharmacy/dashboard/')); ?>"
        class="mc-nav-item <?php echo $is_dashboard ? 'active' : ''; ?>"
    >
        <span class="mc-nav-icon">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
        <path d="M3 10.5L12 3L21 10.5"></path>
        <path d="M5 9.5V21H19V9.5"></path>
    </svg>
</span>

<span class="mc-nav-text">Dashboard</span>
    </a>

    <a
        href="<?php echo esc_url(site_url('/pharmacy/search/')); ?>"
        class="mc-nav-item <?php echo $is_search ? 'active' : ''; ?>"
    >
        <span class="mc-nav-icon">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
        <circle cx="11" cy="11" r="7"></circle>
        <path d="M20 20L16.5 16.5"></path>
    </svg>
</span>

<span class="mc-nav-text">Search</span>
    </a>

    <a
        href="<?php echo esc_url(site_url('/pharmacy/edit-details/')); ?>"
        class="mc-nav-item <?php echo $is_details ? 'active' : ''; ?>"
    >
        <span class="mc-nav-icon">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
        <rect x="4" y="3" width="16" height="18" rx="2"></rect>
        <path d="M8 8H16"></path>
        <path d="M8 12H16"></path>
    </svg>
</span>

<span class="mc-nav-text">Your Details</span>
    </a>

    <a
        href="<?php echo esc_url(site_url('/pharmacy/subscription/')); ?>"
        class="mc-nav-item <?php echo $is_subscription ? 'active' : ''; ?>"
    >
        <span class="mc-nav-icon">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
        <rect x="5" y="4" width="14" height="16" rx="2"></rect>
        <path d="M9 8H15"></path>
        <path d="M9 12H15"></path>
    </svg>
</span>

<span class="mc-nav-text">Subscription</span>
    </a>

    <a
        href="<?php echo esc_url(site_url('/pharmacy/orders/')); ?>"
        class="mc-nav-item <?php echo $is_orders ? 'active' : ''; ?>"
    >
        <span class="mc-nav-icon">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
        <path d="M12 2V6"></path>
        <path d="M12 18V22"></path>
        <path d="M4.9 4.9L7.7 7.7"></path>
        <path d="M16.3 16.3L19.1 19.1"></path>
        <circle cx="12" cy="12" r="6"></circle>
    </svg>
</span>

<span class="mc-nav-text">Orders</span>
    </a>

    <a
        href="javascript:void(0);"
        onclick="mcOpenSupportModal();"
        class="mc-nav-item"
    >
        <span class="mc-nav-icon">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
        <circle cx="12" cy="12" r="9"></circle>
        <path d="M12 17H12.01"></path>
        <path d="M9.5 9A2.5 2.5 0 0 1 14.5 9C14.5 11 12 11.5 12 13"></path>
    </svg>
</span>

<span class="mc-nav-text">Support</span>
    </a>

</nav>

        <?php if (
                is_user_logged_in() &&
                in_array( 'pharmacy_user', wp_get_current_user()->roles )
            ) : ?>
            <div class="mc-portal-user">
            
                <span class="mc-profile-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M4 20C4 16.5 7.5 14 12 14C16.5 14 20 16.5 20 20"></path>
                    </svg>
                </span>
                <span class="mc-portal-welcome">
                    <span class="mc-welcome-label">Welcome,</span>
                    <span class="mc-welcome-email">
                        <?php echo esc_html($current_user->user_email); ?>
                    </span>
                </span>

                <?php
                            $badge_class = 'mc-topbar-badge mc-subscription-badge mc-sub-' . strtolower($subscription_state['status']);
                        ?>
                        <span class="<?php echo esc_attr($badge_class); ?>">
                            <?php echo esc_html($subscription_label); ?>
                        </span>

                <a href="<?php echo wp_logout_url(site_url('/pharmacy/login/')); ?>" 
                        class="mc-topbar-btn mc-logout-btn">
                            Logout
                </a>
                
            </div>
             <?php endif; ?>

    </div>

</div>