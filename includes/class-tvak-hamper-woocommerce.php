<?php
/**
 * WooCommerce integration for Custom Hamper Builder.
 *
 * @package TVAK_Beauty_Kit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Hamper_WooCommerce {

    private static $builder_rendered = false;
    private static $allow_shell_cart_add = false;

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('woocommerce_single_product_summary', [__CLASS__, 'render_builder'], 31);
        add_action('woocommerce_after_single_product_summary', [__CLASS__, 'render_builder'], 4);
        add_action('woocommerce_after_single_product', [__CLASS__, 'render_builder'], 4);
        add_filter('woocommerce_is_purchasable', [__CLASS__, 'disable_shell_purchase'], 10, 2);
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'block_direct_hamper_shell_add'], 10, 6);
        add_filter('woocommerce_get_item_data', [__CLASS__, 'display_cart_item_meta'], 11, 2);
        add_action('woocommerce_before_calculate_totals', [__CLASS__, 'apply_hamper_cart_price'], 20);
        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'attach_line_item_meta'], 11, 4);
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'attach_order_meta'], 11, 3);
        add_action('woocommerce_admin_order_data_after_billing_address', [__CLASS__, 'render_admin_order_panel'], 11);
    }

    public static function register_routes(): void {
        register_rest_route('tvak/v1', '/hamper/(?P<product_id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_get_hamper'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('tvak/v1', '/hamper/add', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'handle_add_hamper'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function enqueue_assets(): void {
        if (!is_product()) {
            return;
        }

        $product_id = get_queried_object_id();
        if (!$product_id || !class_exists('Tvak_Hamper') || !Tvak_Hamper::is_hamper_product((int) $product_id)) {
            return;
        }

        wp_enqueue_style('tvak-hamper-css', TVAK_PLUGIN_URL . 'assets/css/tvak-hamper.css', [], TVAK_VERSION);
        wp_enqueue_script('tvak-hamper-js', TVAK_PLUGIN_URL . 'assets/js/tvak-hamper.js', ['jquery'], TVAK_VERSION, true);
        wp_localize_script('tvak-hamper-js', 'tvak_hamper_vars', [
            'config_api'      => esc_url_raw(rest_url('tvak/v1/hamper/' . (int) $product_id)),
            'add_api'         => esc_url_raw(rest_url('tvak/v1/hamper/add')),
            'nonce'           => wp_create_nonce('wp_rest'),
            'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol()) : '',
        ]);
    }

    public static function render_builder(): void {
        global $product;

        if (self::$builder_rendered) {
            return;
        }

        if (!$product && is_product()) {
            $product = function_exists('wc_get_product') ? wc_get_product(get_queried_object_id()) : null;
        }

        if (!$product || !class_exists('Tvak_Hamper') || !Tvak_Hamper::is_hamper_product((int) $product->get_id())) {
            return;
        }

        self::$builder_rendered = true;

        ?>
        <div id="tvak-hamper-builder" class="tvak-hamper-builder" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
            <div class="tvak-hamper-loading">
                <div class="tvak-luxury-spinner"></div>
                <p><?php esc_html_e('Loading your custom hamper...', 'tvak-beauty-kit'); ?></p>
            </div>
        </div>
        <?php
    }

    public static function disable_shell_purchase(bool $purchasable, $product): bool {
        if ($product && class_exists('Tvak_Hamper') && Tvak_Hamper::is_hamper_product((int) $product->get_id())) {
            if (self::$allow_shell_cart_add) {
                return true;
            }
            if (!is_product()) {
                return $purchasable;
            }
            return false;
        }
        return $purchasable;
    }

    public static function block_direct_hamper_shell_add($passed, $product_id, $quantity, $variation_id = 0, $variations = [], $cart_item_data = []): bool {
        if (self::$allow_shell_cart_add) {
            return (bool) $passed;
        }

        if ($product_id && class_exists('Tvak_Hamper') && Tvak_Hamper::is_hamper_product((int) $product_id)) {
            wc_add_notice(__('Please build your hamper before adding it to cart.', 'tvak-beauty-kit'), 'error');
            return false;
        }

        return (bool) $passed;
    }

    public static function handle_get_hamper(WP_REST_Request $request): WP_REST_Response {
        $product_id = (int) $request->get_param('product_id');
        $payload = class_exists('Tvak_Hamper') ? Tvak_Hamper::build_payload_for_product($product_id) : null;

        if (!$payload) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('This product is not an active TVAK hamper.', 'tvak-beauty-kit'),
            ], 404);
        }

        return new WP_REST_Response([
            'success' => true,
            'hamper'  => $payload,
        ], 200);
    }

    public static function handle_add_hamper(WP_REST_Request $request): WP_REST_Response {
        if (is_user_logged_in() && !wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Security check failed.', 'tvak-beauty-kit'),
            ], 403);
        }

        if (class_exists('Tvak_WooCommerce')) {
            Tvak_WooCommerce::ensure_wc_cart_initialized();
        }

        $params = $request->get_json_params() ?: [];
        $result = self::process_add_hamper_to_cart($params);

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    public static function process_add_hamper_to_cart(array $payload): array {
        if (!class_exists('WooCommerce') || !WC()->cart) {
            return ['success' => false, 'message' => __('WooCommerce cart is not available.', 'tvak-beauty-kit')];
        }

        $hamper_product_id = (int) ($payload['hamper_product_id'] ?? 0);
        $submitted_items   = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
        $hamper            = class_exists('Tvak_Hamper') ? Tvak_Hamper::build_payload_for_product($hamper_product_id) : null;

        if (!$hamper) {
            return ['success' => false, 'message' => __('Invalid or inactive hamper product.', 'tvak-beauty-kit')];
        }

        $allowed_items = [];
        foreach ((array) $hamper['items'] as $item) {
            $allowed_items[(int) $item['product_id']] = $item;
        }

        $selected = [];
        foreach ($submitted_items as $raw_item) {
            $product_id = (int) ($raw_item['product_id'] ?? 0);
            if (!$product_id || !isset($allowed_items[$product_id])) {
                return ['success' => false, 'message' => __('The hamper contains an item that is not allowed.', 'tvak-beauty-kit')];
            }

            $definition = $allowed_items[$product_id];
            if (!empty($definition['is_optional']) && empty($hamper['allow_optional_items'])) {
                return ['success' => false, 'message' => __('Optional items are not enabled for this hamper.', 'tvak-beauty-kit')];
            }

            $selected[$product_id] = [
                'definition'   => $definition,
                'variation_id' => (int) ($raw_item['variation_id'] ?? 0),
                'attributes'   => isset($raw_item['attributes']) && is_array($raw_item['attributes']) ? $raw_item['attributes'] : [],
                'quantity'     => max(1, (int) ($raw_item['quantity'] ?? $definition['default_quantity'] ?? 1)),
            ];
        }

        foreach ($allowed_items as $product_id => $definition) {
            if (!empty($definition['is_required']) && !isset($selected[$product_id])) {
                return ['success' => false, 'message' => __('This hamper is missing a required product.', 'tvak-beauty-kit')];
            }
        }

        $selected_count = count($selected);
        if ($selected_count < (int) $hamper['min_items'] || $selected_count > (int) $hamper['max_items']) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Please select between %1$d and %2$d products for this hamper.', 'tvak-beauty-kit'),
                    (int) $hamper['min_items'],
                    (int) $hamper['max_items']
                ),
            ];
        }

        $hamper_cart_id = 'HAMPER-' . date('Ymd') . '-' . wp_generate_password(6, false, false);
        $included_items = [];
        $hamper_total = 0.0;

        foreach ($selected as $product_id => $item) {
            $product = wc_get_product($product_id);
            if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
                return ['success' => false, 'message' => __('One selected hamper product is unavailable.', 'tvak-beauty-kit')];
            }

            $variation_id = (int) $item['variation_id'];
            $attributes = array_map('wc_clean', $item['attributes']);

            if ($product->is_type('variable')) {
                if (!$variation_id) {
                    return ['success' => false, 'message' => sprintf(__('Please choose options for %s.', 'tvak-beauty-kit'), $product->get_name())];
                }

                $variation = wc_get_product($variation_id);
                if (!$variation || (int) $variation->get_parent_id() !== $product_id || !$variation->is_purchasable() || !$variation->is_in_stock()) {
                    return ['success' => false, 'message' => sprintf(__('The selected variation for %s is unavailable.', 'tvak-beauty-kit'), $product->get_name())];
                }
            } else {
                $variation = null;
                $variation_id = 0;
                $attributes = [];
            }

            $quantity = max(1, (int) $item['quantity']);
            $priced_product = $variation_id && !empty($variation) ? $variation : $product;
            $unit_price = (float) wc_get_price_to_display($priced_product);
            $line_total = $unit_price * $quantity;
            $hamper_total += $line_total;

            $included_items[] = [
                'product_id'      => $product_id,
                'variation_id'    => $variation_id,
                'attributes'      => $attributes,
                'quantity'        => $quantity,
                'name'            => sanitize_text_field($product->get_name()),
                'variation_label' => self::get_selected_variation_label($item['definition'], $variation_id),
                'unit_price'      => $unit_price,
                'line_total'      => $line_total,
            ];
        }

        if (!$included_items) {
            return ['success' => false, 'message' => __('No hamper products were added to cart.', 'tvak-beauty-kit')];
        }

        $cart_item_data = [
            '_tvak_is_custom_hamper_item' => true,
            '_tvak_hamper_id'             => (int) $hamper['hamper_id'],
            '_tvak_hamper_cart_id'        => $hamper_cart_id,
            '_tvak_hamper_product_id'     => $hamper_product_id,
            '_tvak_hamper_title'          => sanitize_text_field($hamper['title'] ?? get_the_title($hamper_product_id)),
            '_tvak_hamper_items'          => $included_items,
            '_tvak_hamper_item_count'     => count($included_items),
            '_tvak_hamper_total'          => $hamper_total,
        ];

        self::$allow_shell_cart_add = true;
        try {
            $cart_item_key = WC()->cart->add_to_cart($hamper_product_id, 1, 0, [], $cart_item_data);
        } finally {
            self::$allow_shell_cart_add = false;
        }

        if (!$cart_item_key) {
            return ['success' => false, 'message' => __('The hamper could not be added to cart.', 'tvak-beauty-kit')];
        }

        return [
            'success'      => true,
            'message'      => sprintf(__('Hamper added to your bag with %d selected products.', 'tvak-beauty-kit'), count($included_items)),
            'hamper_id'    => (int) $hamper['hamper_id'],
            'hamper_cart_id' => $hamper_cart_id,
            'added_count'  => 1,
            'included_count' => count($included_items),
            'cart_url'     => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '/cart',
            'checkout_url' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '/checkout',
        ];
    }

    public static function display_cart_item_meta(array $item_data, array $cart_item): array {
        if (!empty($cart_item['_tvak_is_custom_hamper_item'])) {
            $included_items = isset($cart_item['_tvak_hamper_items']) && is_array($cart_item['_tvak_hamper_items']) ? $cart_item['_tvak_hamper_items'] : [];
            $item_count = isset($cart_item['_tvak_hamper_item_count']) ? (int) $cart_item['_tvak_hamper_item_count'] : count($included_items);

            $item_data[] = ['key' => __('Hamper', 'tvak-beauty-kit'), 'value' => esc_html($cart_item['_tvak_hamper_title'] ?? '')];
            $item_data[] = ['key' => __('Included Items', 'tvak-beauty-kit'), 'value' => esc_html(sprintf(_n('%d product selected', '%d products selected', $item_count, 'tvak-beauty-kit'), $item_count))];
            if ($included_items) {
                $item_data[] = ['key' => __('Products Inside', 'tvak-beauty-kit'), 'value' => esc_html(self::format_included_items_summary($included_items))];
            }
            $item_data[] = ['key' => __('Hamper Ref', 'tvak-beauty-kit'), 'value' => esc_html($cart_item['_tvak_hamper_cart_id'] ?? '')];
        }
        return $item_data;
    }

    public static function apply_hamper_cart_price($cart): void {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (!$cart || !method_exists($cart, 'get_cart')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item['_tvak_is_custom_hamper_item']) || empty($cart_item['_tvak_hamper_total']) || empty($cart_item['data'])) {
                continue;
            }

            $cart_item['data']->set_price((float) $cart_item['_tvak_hamper_total']);
        }
    }

    public static function attach_line_item_meta($item, $cart_item_key, $values, $order): void {
        if (!empty($values['_tvak_is_custom_hamper_item'])) {
            $item->add_meta_data('_tvak_is_custom_hamper_item', 'yes');
            $item->add_meta_data('_tvak_hamper_id', $values['_tvak_hamper_id'] ?? '');
            $item->add_meta_data('_tvak_hamper_cart_id', $values['_tvak_hamper_cart_id'] ?? '');
            $item->add_meta_data('_tvak_hamper_product_id', $values['_tvak_hamper_product_id'] ?? '');
            $item->add_meta_data('_tvak_hamper_title', $values['_tvak_hamper_title'] ?? '');
            $item->add_meta_data('_tvak_hamper_item_count', $values['_tvak_hamper_item_count'] ?? '');
            $item->add_meta_data('_tvak_hamper_total', $values['_tvak_hamper_total'] ?? '');
            $item->add_meta_data('_tvak_hamper_items', $values['_tvak_hamper_items'] ?? []);
            $item->add_meta_data(__('Products Inside', 'tvak-beauty-kit'), self::format_included_items_summary($values['_tvak_hamper_items'] ?? []));
        }
    }

    public static function attach_order_meta($order_id, $posted_data, $order): void {
        foreach ($order->get_items() as $item) {
            $cart_id = $item->get_meta('_tvak_hamper_cart_id');
            if ($cart_id) {
                $order->update_meta_data('_tvak_has_custom_hamper', 'yes');
                $order->update_meta_data('_tvak_hamper_cart_id', $cart_id);
                $order->save();
                return;
            }
        }
    }

    public static function render_admin_order_panel($order): void {
        if ($order->get_meta('_tvak_has_custom_hamper') !== 'yes') {
            return;
        }

        ?>
        <div class="order_data_column" style="width: 100%; margin-top: 20px; padding: 15px; background: #fff7ed; border: 1px solid #f59e0b; border-radius: 4px;">
            <h3><?php esc_html_e('TVAK Custom Hamper Order', 'tvak-beauty-kit'); ?></h3>
            <p><strong><?php esc_html_e('Hamper Reference:', 'tvak-beauty-kit'); ?></strong> <code><?php echo esc_html($order->get_meta('_tvak_hamper_cart_id')); ?></code></p>
            <p><em><?php esc_html_e('Pack the products listed inside the hamper line item together in the selected hamper packaging.', 'tvak-beauty-kit'); ?></em></p>
        </div>
        <?php
    }

    private static function get_selected_variation_label(array $definition, int $variation_id): string {
        if (!$variation_id || empty($definition['variations']) || !is_array($definition['variations'])) {
            return '';
        }

        foreach ($definition['variations'] as $variation) {
            if ((int) ($variation['variation_id'] ?? 0) === $variation_id) {
                return sanitize_text_field($variation['label'] ?? '');
            }
        }

        return '';
    }

    private static function format_included_items_summary(array $included_items): string {
        $summary = [];
        foreach ($included_items as $included_item) {
            $name = sanitize_text_field($included_item['name'] ?? '');
            $label = sanitize_text_field($included_item['variation_label'] ?? '');
            $quantity = max(1, (int) ($included_item['quantity'] ?? 1));

            if ($label) {
                $name .= ' - ' . $label;
            }

            if ($name) {
                $summary[] = sprintf('%s x %d', $name, $quantity);
            }
        }

        return implode('; ', $summary);
    }
}
