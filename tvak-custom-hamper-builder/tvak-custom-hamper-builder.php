<?php
/**
 * Plugin Name: TVAK Custom Hamper Builder
 * Plugin URI: https://tvak.in
 * Description: Standalone WooCommerce custom hamper builder for designated TVAK hamper products.
 * Version: 1.0.0
 * Author: TVAK
 * Text Domain: tvak-custom-hamper-builder
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 *
 * @package TVAK_Custom_Hamper_Builder
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('TCHB_PLUGIN_FILE')) {
    return;
}

define('TCHB_VERSION', '1.0.0');
define('TCHB_DB_VERSION', '1.0.0');
define('TCHB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TCHB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TCHB_PLUGIN_FILE', __FILE__);

add_action('before_woocommerce_init', static function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

require_once TCHB_PLUGIN_DIR . 'includes/class-tvak-custom-hamper-db.php';
require_once TCHB_PLUGIN_DIR . 'includes/models/class-tvak-custom-hamper.php';
require_once TCHB_PLUGIN_DIR . 'includes/class-tvak-custom-hamper-woocommerce.php';

register_activation_hook(__FILE__, ['Tvak_Custom_Hamper_DB', 'create_tables']);

add_action('plugins_loaded', static function() {
    if (!class_exists('WooCommerce')) {
        return;
    }

    if (get_option('tchb_db_version') !== TCHB_DB_VERSION) {
        Tvak_Custom_Hamper_DB::create_tables();
    }

    Tvak_Custom_Hamper_WooCommerce::init();
}, 20);
