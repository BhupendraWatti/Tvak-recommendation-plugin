<?php
/**
 * Plugin Name: TVAK Personalized Beauty Recommendation Engine
 * Plugin URI: https://tvak.com/personalized-beauty-kit
 * Description: Intelligent, product-centric recommendation engine for TVAK's "Build Your Personalized Beauty Kit" experience.
 * Version: 2.1.2
 * Author: TVAK Architecture Team
 * Text Domain: tvak-beauty-kit
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.9.4
 *
 * @package TVAK_Beauty_Kit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Plugin Constants
define('TVAK_VERSION', '2.1.2');
define('TVAK_DB_VERSION', '2.1.0');
define('TVAK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TVAK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TVAK_PLUGIN_FILE', __FILE__);
define('TVAK_PLUGIN_BASENAME', plugin_basename(__FILE__));

add_action('before_woocommerce_init', static function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/**
 * Main TVAK Beauty Kit Plugin Singleton Class
 */
final class Tvak_Beauty_Kit {

    /**
     * Singleton instance.
     *
     * @var Tvak_Beauty_Kit|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Tvak_Beauty_Kit
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private Constructor.
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load core plugin files and class dependencies.
     */
    private function load_dependencies() {
        require_once TVAK_PLUGIN_DIR . 'includes/class-tvak-db.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-master-data.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-attribute.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-product-rule.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-variant-map.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-product-shade.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-shade-sync.php';

        // Load Engine Classes
        require_once TVAK_PLUGIN_DIR . 'includes/engine/class-tvak-user-profile.php';
        require_once TVAK_PLUGIN_DIR . 'includes/engine/evaluators/interface-tvak-evaluator.php';
        require_once TVAK_PLUGIN_DIR . 'includes/engine/evaluators/class-tvak-product-evaluator.php';
        require_once TVAK_PLUGIN_DIR . 'includes/engine/class-tvak-anti-collision.php';
        require_once TVAK_PLUGIN_DIR . 'includes/engine/class-tvak-variant-resolver.php';
        require_once TVAK_PLUGIN_DIR . 'includes/engine/class-tvak-engine-orchestrator.php';

        // Load Cache & REST API & WooCommerce Integration
        require_once TVAK_PLUGIN_DIR . 'includes/class-tvak-cache.php';
        require_once TVAK_PLUGIN_DIR . 'includes/api/class-tvak-rest-api.php';
        require_once TVAK_PLUGIN_DIR . 'includes/class-tvak-woocommerce.php';

        // Load Shortcode & Elementor Widget
        require_once TVAK_PLUGIN_DIR . 'includes/class-tvak-shortcode.php';

        if (is_admin()) {
            require_once TVAK_PLUGIN_DIR . 'includes/admin/class-tvak-admin.php';
        }
    }

    /**
     * Initialize WordPress hooks and actions.
     */
    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
        add_action('rest_api_init', ['Tvak_REST_API', 'register_routes']);

        Tvak_Shortcode::init();
        Tvak_WooCommerce::init();
        Tvak_Shade_Sync::init();

        // Elementor Widget Registration Hook
        add_action('elementor/widgets/register', [$this, 'register_elementor_widget']);

        if (is_admin()) {
            Tvak_Admin::init();
        }
    }

    /**
     * Register Elementor Custom Widget.
     *
     * @param \Elementor\Widgets_Manager $widgets_manager Manager.
     */
    public function register_elementor_widget($widgets_manager) {
        require_once TVAK_PLUGIN_DIR . 'includes/widgets/class-tvak-elementor-widget.php';
        $widgets_manager->register(new Tvak_Elementor_Widget());
    }

    /**
     * Executed when all plugins are loaded.
     */
    public function on_plugins_loaded() {
        load_plugin_textdomain('tvak-beauty-kit', false, dirname(TVAK_PLUGIN_BASENAME) . '/languages');

        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
        }

        // Automatic DB upgrade & seed check: run create_tables() and seed_defaults() if version changed or tables empty
        $installed_db_version = get_option('tvak_db_version', '0.0.0');
        global $wpdb;
        $master_table = $wpdb->prefix . 'tvak_master_attributes';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $master_table)) === $master_table;
        $is_empty     = $table_exists ? ( (int) $wpdb->get_var("SELECT COUNT(*) FROM {$master_table}") === 0 ) : true;

        if (version_compare($installed_db_version, TVAK_DB_VERSION, '<') || !$table_exists || $is_empty) {
            Tvak_DB::create_tables();
            Tvak_DB::seed_defaults();
        }
    }

    /**
     * Admin notice if WooCommerce is not active.
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('TVAK Personalized Beauty Recommendation Engine requires WooCommerce to be installed and active.', 'tvak-beauty-kit');
        echo '</p></div>';
    }

    /**
     * Plugin Activation Callback.
     */
    public static function activate() {
        require_once TVAK_PLUGIN_DIR . 'includes/class-tvak-db.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-master-data.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-attribute.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-product-rule.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-variant-map.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-product-shade.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-shade-sync.php';

        Tvak_DB::create_tables();
        Tvak_DB::seed_defaults();

        if (class_exists('Tvak_Product_Rule')) {
            Tvak_Product_Rule::auto_reconcile_unmapped_products();
        }
        if (class_exists('Tvak_Shade_Sync')) {
            Tvak_Shade_Sync::auto_sync_catalog();
        }

        // Clear rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin Deactivation Callback.
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }
}

// Register Activation & Deactivation Hooks
register_activation_hook(__FILE__, ['Tvak_Beauty_Kit', 'activate']);
register_deactivation_hook(__FILE__, ['Tvak_Beauty_Kit', 'deactivate']);

/**
 * Initialize TVAK Plugin Instance.
 *
 * @return Tvak_Beauty_Kit
 */
function tvak_beauty_kit() {
    return Tvak_Beauty_Kit::get_instance();
}

// Boot Plugin
tvak_beauty_kit();
