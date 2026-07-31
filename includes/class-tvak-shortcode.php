<?php
/**
 * Shortcode Integration Handler
 *
 * Provides [tvak_beauty_kit] shortcode so the kit builder app can be embedded anywhere.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Shortcode {

    /**
     * Register shortcode.
     */
    public static function init() {
        add_shortcode('tvak_beauty_kit', [__CLASS__, 'render_shortcode']);
    }

    /**
     * Render shortcode output.
     *
     * @param array $atts Attributes.
     * @return string HTML output.
     */
    public static function render_shortcode($atts = []) {
        $atts = shortcode_atts([
            'title'    => __('Build Your Personalized Beauty Kit', 'tvak-beauty-kit'),
            'subtitle' => __('Experience a bespoke digital skin consultation tailored to your unique skin profile.', 'tvak-beauty-kit'),
        ], $atts, 'tvak_beauty_kit');

        // Enqueue frontend scripts & styles
        wp_enqueue_style('tvak-builder-css', TVAK_PLUGIN_URL . 'assets/css/tvak-builder.css', [], TVAK_VERSION);
        wp_enqueue_script('tvak-builder-js', TVAK_PLUGIN_URL . 'assets/js/tvak-builder.js', ['jquery'], TVAK_VERSION, true);

        wp_localize_script('tvak-builder-js', 'tvak_vars', [
            'api_url'         => esc_url_raw(rest_url('tvak/v1/recommend')),
            'cart_api'        => esc_url_raw(rest_url('tvak/v1/cart/add-kit')),
            'attr_api'        => esc_url_raw(rest_url('tvak/v1/attributes')),
            'ajax_url'        => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'           => wp_create_nonce('wp_rest'),
            // Dynamic accent colour from WP option — configurable in TVAK Settings
            'accent_color'    => class_exists('Tvak_Shade_Sync')
                                    ? Tvak_Shade_Sync::get_default_hex()
                                    : (get_option('tvak_default_shade_hex') ?: '#D4AF37'),
            // Live WooCommerce currency data — JS uses these for price formatting
            'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol()) : '₹',
            'currency_code'   => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'INR',
        ]);


        ob_start();
        ?>
        <div class="tvak-shortcode-wrapper">
            <div id="tvak-beauty-kit-app" 
                 data-title="<?php echo esc_attr($atts['title']); ?>" 
                 data-subtitle="<?php echo esc_attr($atts['subtitle']); ?>">
                <div class="tvak-loader-spinner">
                    <div class="tvak-luxury-spinner"></div>
                    <p><?php esc_html_e('Initializing TVAK Digital Consultation...', 'tvak-beauty-kit'); ?></p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
