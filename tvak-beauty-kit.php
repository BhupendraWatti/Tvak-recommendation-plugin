<?php
/**
 * Plugin Name: TVAK Personalized Beauty Recommendation Engine
 * Plugin URI: https://tvak.com/personalized-beauty-kit
 * Description: Intelligent, product-centric recommendation engine for TVAK's "Build Your Personalized Beauty Kit" experience.
 * Version: 1.0.0
 * Author: TVAK Architecture Team
 * Text Domain: tvak-beauty-kit
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 8.9
 *
 * @package TVAK_Beauty_Kit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Plugin Constants
define('TVAK_VERSION', '1.0.0');
define('TVAK_DB_VERSION', '1.0.0');
define('TVAK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TVAK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TVAK_PLUGIN_FILE', __FILE__);
define('TVAK_PLUGIN_BASENAME', plugin_basename(__FILE__));

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
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-attribute.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-product-rule.php';
        require_once TVAK_PLUGIN_DIR . 'includes/models/class-tvak-variant-map.php';

        // Load Engine Classes
        require_once TVAK_PLUGIN_DIR . 'includes/engine/class-tvak-user-profile.php';
        require_once TVAK_PLUGIN_DIR . 'includes/engine/evaluators/interface-tvak-evaluator.php';
        require_once TVAK_PLUGIN_DIR . 'includes/engine/evaluators/class-tvak-product-evaluator.php';
        require_once TVAK_PLUGIN_DIR . 'includes/engine/class-tvak-anti-collision.php';
        require_once TVAK_PLUGIN_DIR . 'includes/engine/class-tvak-variant-resolver.php';
        require_once TVAK_PLUGIN_DIR . 'includes/engine/class-tvak-engine-orchestrator.php';

        // Load Cache & REST API
        require_once TVAK_PLUGIN_DIR . 'includes/class-tvak-cache.php';
        require_once TVAK_PLUGIN_DIR . 'includes/api/class-tvak-rest-api.php';

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

        if (is_admin()) {
            Tvak_Admin::init();
        }
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
        Tvak_DB::create_tables();
        Tvak_DB::seed_defaults();

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
