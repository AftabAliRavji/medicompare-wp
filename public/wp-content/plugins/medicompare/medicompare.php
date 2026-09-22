<?php
/**
 * Plugin Name: MediCompare
 * Description: Core functionality for the MediCompare platform.
 * Version: 0.2.1
 * Author: Aftab
 * Deployment test - July 18
 */

if (!session_id()) {
    session_start();
}

if (!defined('ABSPATH')) exit;

class MediCompare {

    public function __construct() {

        // Plugin activation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_activation_hook(__FILE__, [$this, 'run_migrations']);

        // ⭐ Auto-create pharmacy pages
        register_activation_hook(__FILE__, [$this, 'create_pharmacy_pages']);

        // ⭐ NEW: Auto-create Welcome Signup page
        register_activation_hook(__FILE__, [$this, 'create_welcome_signup_page']);

        // Make sure timezone is set properly
        register_activation_hook(__FILE__, [$this,'mc_fix_timezone_on_activation']);

        // Load CPTs early
        add_action('init', [$this, 'load_cpts'], 1);

        // Register custom roles
        add_action('init', [$this, 'register_roles'], 2);

        // ⭐ Register product category taxonomy (safe, additive, keeps meta)
        add_action('init', [$this, 'register_product_category_taxonomy'], 3);

        // ⭐ Ensure migration option exists
        add_action('admin_init', function() {
            if (get_option('mc_move_anti_inflammatory_to_generics') === false) {
                add_option('mc_move_anti_inflammatory_to_generics', 'run');
            }
        });

        // ⭐ One-time migration trigger
        add_action('admin_init', function() {
            if (get_option('mc_run_category_migration') !== 'done') {
                $this->migrate_product_categories_to_taxonomy();
                update_option('mc_run_category_migration', 'done');
            }
        });

        // ⭐ One-time move Anti Inflammatory → Generics
        add_action('admin_init', function() {
            if (get_option('mc_move_anti_inflammatory_to_generics') === 'run') {

                $this->move_anti_inflammatory_to_generics();

                update_option('mc_move_anti_inflammatory_to_generics', 'done');
            }
        });

        add_action('admin_init', [$this, 'ensure_search_instructions_page']);

        add_filter('the_content', [$this, 'add_header_to_search_instructions']);

        add_action('wp_footer', [$this, 'render_global_footer']);
        

        // Load admin menu
        require_once plugin_dir_path(__FILE__) . 'includes/class-admin-menu.php';

        // Front-end registration + claim flows
        require_once plugin_dir_path(__FILE__) . 'includes/pharmacy-protect.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-registration.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-claim.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-login.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-portal.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-frontend.php';
        require_once plugin_dir_path(__FILE__) . 'includes/pharmacy-comparison.php';

        // ⭐ NEW: Welcome Signup page template loader
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/welcome-signup.php';

        // Hide theme header/footer for MediCompare pages
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/hide-theme-ui.php';

        // Stripe config + checkout
        require_once plugin_dir_path(__FILE__) . 'includes/stripe-config.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-stripe.php';
        require_once plugin_dir_path(__FILE__) . 'includes/stripe-webhooks.php';

        // Requirements board
        require_once ABSPATH . 'project-req/requirements-board-endpoints.php';


        /**
         * ⭐ TEMPORARY HOMEPAGE REDIRECT (toggle controlled)
         * Works on LocalWP (nginx), InfinityFree (Apache), AWS/GCP (nginx)
         */
        add_action('template_redirect', function () {

            // Only run if toggle is enabled
            if (!get_option('mc_enable_home_redirect')) {
                return;
            }

            // Only redirect homepage
            if (is_front_page()) {
                wp_redirect(home_url('/welcome-signup/'));
                exit;
            }

        });
    }

    public function register_product_category_taxonomy() {

        error_log("TAXONOMY FIRED");

        register_taxonomy(
            'mc_product_category',
            'mc_product',
            [
                'label' => 'Product Categories',
                'hierarchical' => true,
                'show_ui' => true,
                'show_admin_column' => true,
                'rewrite' => ['slug' => 'product-category'],
            ]
        );

        $terms = [
            'Concession Lines',
            'Generics',
            'Category A',
            'Category M',
            'OTC',
            'POM',
            'Topical',
            'Analgesic'
        ];

        foreach ($terms as $term) {
            if (!term_exists($term, 'mc_product_category')) {
                wp_insert_term($term, 'mc_product_category');
            }
        }
    }

   /**
    * ⭐ Migrate existing mc_category meta → taxonomy terms
    */
    public function migrate_product_categories_to_taxonomy() {

        // Get all products
        $products = get_posts([
            'post_type'      => 'mc_product',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids'
        ]);

        foreach ($products as $post_id) {

            $meta_category = get_post_meta($post_id, 'mc_category', true);

            if (!$meta_category) {
                continue;
            }

            // Ensure term exists
            if (!term_exists($meta_category, 'mc_product_category')) {
                wp_insert_term($meta_category, 'mc_product_category');
            }

            // Assign taxonomy term
            wp_set_object_terms($post_id, $meta_category, 'mc_product_category', true);
        }
    }

    /**
     * ⭐ Move all Anti Inflammatory products → Generics
     */
    public function move_anti_inflammatory_to_generics() {

        // Accept common variations
        $variants = [
            'Anti Inflammatory',
            'Anti-Inflammatory',
            'anti inflammatory',
            'anti-inflammatory',
            'AntiInflammatory'
        ];

        // Ensure Generics term exists
        if (!term_exists('Generics', 'mc_product_category')) {
            wp_insert_term('Generics', 'mc_product_category');
        }

        // Fetch all products matching any variant
        $products = get_posts([
            'post_type'      => 'mc_product',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'mc_category',
                    'value'   => $variants,
                    'compare' => 'IN'
                ]
            ]
        ]);

        if (empty($products)) {
            error_log("No Anti Inflammatory products found.");
            return;
        }

        foreach ($products as $post_id) {

            // Update meta
            update_post_meta($post_id, 'mc_category', 'Generics');

            // Update taxonomy
            wp_set_object_terms($post_id, 'Generics', 'mc_product_category', false);
        }

        error_log("Moved " . count($products) . " Anti Inflammatory products → Generics.");
    }

    /**
     * ⭐ Auto-create Search Instructions page if missing
     */
    public function ensure_search_instructions_page() {

        $page = get_page_by_path('search-instructions');

        if (!$page) {
            wp_insert_post([
                'post_title'   => 'Search Instructions',
                'post_name'    => 'search-instructions',
                'post_content' => '[mc_search_instructions]',
                'post_status'  => 'publish',
                'post_type'    => 'page'
            ]);
        }
    }

   /**
     * ⭐ Add pharmacy header to Search Instructions page content
     */
    public function add_header_to_search_instructions($content) {

        if (!is_page('search-instructions')) {
            return $content;
        }

        // Load header template output into a buffer
        ob_start();

        $mc_assets = plugin_dir_url(__FILE__) . 'assets/img/';
        include plugin_dir_path(__FILE__) . 'templates/header-pharmacy.php';

        $header_html = ob_get_clean();

        // Prepend header to page content
        return $header_html . $content;
    }

    /**
    * ⭐ Global Footer Output
    */
    public function render_global_footer() {

        if (is_admin()) return;
        // Hide on welcome signup page
        if (is_page('welcome-signup')) return;
        

        // Include global support modal
        include WP_PLUGIN_DIR . '/medicompare/templates/support-modal.php';

        echo '<div style="text-align:center; padding:20px; margin-top:40px; 
                        font-size:14px; color:#666;">
                <hr style="margin-bottom:20px;">
                <strong>sourcemedpharma</strong> &nbsp;|&nbsp; 
                Version 0.2.1 &nbsp;|&nbsp; 
                © ' . date('Y') . ' sourcemedpharma Ltd
                <br>
                <a href="/search-instructions/">Search Instructions</a> &nbsp;|&nbsp;
                <a href="/privacy-policy/">Privacy Policy</a> &nbsp;|&nbsp;
                <a href="/terms/">Terms & Conditions</a> &nbsp;|&nbsp;
                <a href="javascript:void(0);" onclick="mcOpenSupportModal();">Contact Support</a>
            </div>';
    }


    /**
     * ⭐ Auto-create Welcome Signup Page
     */
    public function create_welcome_signup_page() {

        $existing = get_page_by_path('welcome-signup');
        if ($existing) return;

        wp_insert_post([
            'post_title'   => 'Welcome to MediCompare',
            'post_name'    => 'welcome-signup',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[mc_welcome_signup]'
        ]);
    }

    /**
     * Making sure timezone is set to europe/london 
     */
    function mc_fix_timezone_on_activation() {
        update_option('timezone_string', 'Europe/London');
        delete_option('gmt_offset');
    }

    /**
     * ⭐ Auto-create pharmacy parent + child pages safely
     */
    public function create_pharmacy_pages() {

        $parent = get_page_by_path('pharmacy');

        if (!$parent) {
            $parent_id = wp_insert_post([
                'post_title'   => 'Pharmacy',
                'post_name'    => 'pharmacy',
                'post_content' => '[mc_pharmacy_portal]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]);
        } else {
            $parent_id = $parent->ID;

            if (strpos($parent->post_content, '[mc_pharmacy_portal]') === false) {
                wp_update_post([
                    'ID'           => $parent_id,
                    'post_content' => '[mc_pharmacy_portal]'
                ]);
            }
        }

        $pages = [
            'pharmacy-registration' => [
                'title'   => 'Pharmacy Registration',
                'content' => '[mc_pharmacy_registration]'
            ],
            'edit-details' => [
                'title'   => 'Edit Pharmacy Details',
                'content' => '[mc_pharmacy_edit_details]'
            ],
            'dashboard' => [
                'title'   => 'Pharmacy Dashboard',
                'content' => '[mc_pharmacy_dashboard]'
            ],
            'login' => [
                'title'   => 'Pharmacy Login',
                'content' => '[mc_pharmacy_login]'
            ],
            'search' => [
                'title'   => 'Search Products',
                'content' => '[mc_pharmacy_search]'
            ],
            'orders' => [
                'title'   => 'Pharmacy Orders',
                'content' => '[mc_pharmacy_orders]'
            ],
            'subscription' => [
                'title'   => 'Subscription History',
                'content' => '[mc_pharmacy_subscription]'
            ],
        ];

        foreach ($pages as $slug => $page) {

            $existing = get_page_by_path('pharmacy/' . $slug);

            if ($existing) continue;

            wp_insert_post([
                'post_title'   => $page['title'],
                'post_name'    => $slug,
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_parent'  => $parent_id
            ]);
        }
    }

    /**
     * Register the pharmacy_user role
     */
    public function register_roles() {
        add_role(
            'pharmacy_user',
            'Pharmacy User',
            [
                'read' => true,
            ]
        );
    }

    public function load_cpts() {
        error_log("MediCompare: load_cpts() fired");

        require_once plugin_dir_path(__FILE__) . 'post-types/class-supplier-cpt.php';
        require_once plugin_dir_path(__FILE__) . 'post-types/class-product-cpt.php';
        require_once plugin_dir_path(__FILE__) . 'post-types/class-pharmacy-cpt.php';
    }

    public function activate() {
        global $wpdb;

        $table   = $wpdb->prefix . 'medi_import_logs';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id BIGINT UNSIGNED NOT NULL,
            supplier_id BIGINT UNSIGNED DEFAULT NULL,
            filename VARCHAR(255) NOT NULL,
            inserted INT DEFAULT 0,
            updated INT DEFAULT 0,
            errors LONGTEXT NULL,
            ip VARCHAR(100) NULL,
            ua VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function run_migrations() {
        global $wpdb;

        $migration_dir = plugin_dir_path(__FILE__) . 'migrations/';

        foreach (glob($migration_dir . '*.sql') as $file) {
            $sql = file_get_contents($file);
            $wpdb->query($sql);
        }
    }

}

new MediCompare();
