<?php
/**
 * Custom Hamper Builder model.
 *
 * Stores designated hamper shell products and their allowed WooCommerce items.
 *
 * @package TVAK_Custom_Hamper_Builder
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Custom_Hamper {

    public static function hampers_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'tvak_hampers';
    }

    public static function items_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'tvak_hamper_items';
    }

    public static function is_hamper_product(int $product_id): bool {
        if (get_post_meta($product_id, '_tvak_is_hamper_product', true) === 'yes'
            && (bool) self::get_by_product_id($product_id, true)
        ) {
            return true;
        }

        return self::is_dynamic_hamper_candidate($product_id) && count(self::get_dynamic_item_ids($product_id)) >= 2;
    }

    public static function get_by_product_id(int $product_id, bool $active_only = false): ?array {
        global $wpdb;
        $table = self::hampers_table();

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        if (!$table_exists || !$product_id) {
            return null;
        }

        $sql = "SELECT * FROM {$table} WHERE hamper_product_id = %d";
        if ($active_only) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' LIMIT 1';

        $hamper = $wpdb->get_row($wpdb->prepare($sql, $product_id), ARRAY_A);
        return $hamper ?: null;
    }

    public static function get_by_id(int $hamper_id, bool $active_only = false): ?array {
        global $wpdb;
        $table = self::hampers_table();

        $sql = "SELECT * FROM {$table} WHERE hamper_id = %d";
        if ($active_only) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' LIMIT 1';

        $hamper = $wpdb->get_row($wpdb->prepare($sql, $hamper_id), ARRAY_A);
        return $hamper ?: null;
    }

    public static function get_all(): array {
        global $wpdb;
        $table = self::hampers_table();

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        if (!$table_exists) {
            return [];
        }

        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY updated_at DESC, hamper_id DESC", ARRAY_A) ?: [];
    }

    public static function save_hamper(array $data): int {
        global $wpdb;
        $table = self::hampers_table();

        $hamper_id          = isset($data['hamper_id']) ? (int) $data['hamper_id'] : 0;
        $hamper_product_id  = (int) ($data['hamper_product_id'] ?? 0);
        $min_items          = max(1, (int) ($data['min_items'] ?? 2));
        $max_items          = max($min_items, (int) ($data['max_items'] ?? 5));
        $allow_optional     = !empty($data['allow_optional_items']) ? 1 : 0;
        $is_active          = !empty($data['is_active']) ? 1 : 0;
        $title              = sanitize_text_field($data['title'] ?? get_the_title($hamper_product_id));

        if (!$hamper_product_id) {
            return 0;
        }

        $existing_id = $hamper_id ?: (int) $wpdb->get_var(
            $wpdb->prepare("SELECT hamper_id FROM {$table} WHERE hamper_product_id = %d", $hamper_product_id)
        );

        $payload = [
            'hamper_product_id'    => $hamper_product_id,
            'title'                => $title,
            'min_items'            => $min_items,
            'max_items'            => $max_items,
            'allow_optional_items' => $allow_optional,
            'is_active'            => $is_active,
            'updated_at'           => current_time('mysql'),
        ];

        if ($existing_id) {
            $wpdb->update(
                $table,
                $payload,
                ['hamper_id' => $existing_id],
                ['%d', '%s', '%d', '%d', '%d', '%d', '%s'],
                ['%d']
            );
            $hamper_id = $existing_id;
        } else {
            $payload['created_at'] = current_time('mysql');
            $wpdb->insert(
                $table,
                $payload,
                ['%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s']
            );
            $hamper_id = (int) $wpdb->insert_id;
        }

        update_post_meta($hamper_product_id, '_tvak_is_hamper_product', 'yes');
        update_post_meta($hamper_product_id, '_tvak_hamper_id', $hamper_id);

        return $hamper_id;
    }

    public static function save_items(int $hamper_id, array $items): void {
        global $wpdb;
        $table = self::items_table();

        if (!$hamper_id) {
            return;
        }

        $wpdb->delete($table, ['hamper_id' => $hamper_id], ['%d']);

        foreach ($items as $product_id => $item) {
            if (empty($item['enabled'])) {
                continue;
            }

            $product_id = (int) $product_id;
            if (!$product_id || !get_post($product_id)) {
                continue;
            }

            $is_required = !empty($item['is_required']) ? 1 : 0;
            $is_optional = !empty($item['is_optional']) ? 1 : 0;

            $wpdb->insert(
                $table,
                [
                    'hamper_id'        => $hamper_id,
                    'product_id'       => $product_id,
                    'default_quantity' => max(1, (int) ($item['default_quantity'] ?? 1)),
                    'is_required'      => $is_required,
                    'is_preselected'   => $is_required ? 1 : (!empty($item['is_preselected']) ? 1 : 0),
                    'is_optional'      => $is_optional,
                    'sort_order'       => max(0, (int) ($item['sort_order'] ?? 0)),
                    'created_at'       => current_time('mysql'),
                ],
                ['%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s']
            );
        }
    }

    public static function get_items(int $hamper_id): array {
        global $wpdb;
        $table = self::items_table();

        if (!$hamper_id) {
            return [];
        }

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE hamper_id = %d ORDER BY sort_order ASC, hamper_item_id ASC", $hamper_id),
            ARRAY_A
        ) ?: [];
    }

    public static function build_payload_for_product(int $hamper_product_id): ?array {
        $hamper = self::get_by_product_id($hamper_product_id, true);
        if (!$hamper) {
            return self::build_dynamic_payload_for_product($hamper_product_id);
        }

        $items = [];
        foreach (self::get_items((int) $hamper['hamper_id']) as $item) {
            if (!empty($item['is_optional']) && empty($hamper['allow_optional_items'])) {
                continue;
            }

            $product = function_exists('wc_get_product') ? wc_get_product((int) $item['product_id']) : null;
            if (!$product || !$product->is_purchasable()) {
                continue;
            }

            $items[] = self::build_item_payload(
                $product,
                max(1, (int) $item['default_quantity']),
                (bool) $item['is_required'],
                (bool) $item['is_preselected'],
                (bool) $item['is_optional']
            );
        }

        return [
            'hamper_id'            => (int) $hamper['hamper_id'],
            'hamper_product_id'    => (int) $hamper['hamper_product_id'],
            'title'                => $hamper['title'],
            'min_items'            => (int) $hamper['min_items'],
            'max_items'            => (int) $hamper['max_items'],
            'allow_optional_items' => (bool) $hamper['allow_optional_items'],
            'items'                => $items,
        ];
    }

    private static function build_dynamic_payload_for_product(int $hamper_product_id): ?array {
        if (!self::is_dynamic_hamper_candidate($hamper_product_id)) {
            return null;
        }

        $item_ids = self::get_dynamic_item_ids($hamper_product_id);
        if (count($item_ids) < 2) {
            return null;
        }

        $max_items = min(6, max(2, count($item_ids)));
        $items = [];

        foreach (array_slice($item_ids, 0, $max_items) as $product_id) {
            $product = function_exists('wc_get_product') ? wc_get_product((int) $product_id) : null;
            if (!$product || !$product->is_purchasable()) {
                continue;
            }

            $items[] = self::build_item_payload($product, 1, false, true, false);
        }

        if (count($items) < 2) {
            return null;
        }

        return [
            'hamper_id'            => 0,
            'hamper_product_id'    => $hamper_product_id,
            'title'                => get_the_title($hamper_product_id),
            'min_items'            => 2,
            'max_items'            => $max_items,
            'allow_optional_items' => true,
            'items'                => $items,
        ];
    }

    private static function build_item_payload($product, int $default_quantity, bool $is_required, bool $is_preselected, bool $is_optional): array {
        $image_id = $product->get_image_id();

        return [
            'product_id'        => (int) $product->get_id(),
            'name'              => $product->get_name(),
            'type'              => $product->get_type(),
            'price'             => (float) wc_get_price_to_display($product),
            'price_html'        => $product->get_price_html(),
            'description'       => wp_strip_all_tags($product->get_short_description()),
            'image_url'         => $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : wc_placeholder_img_src(),
            'default_quantity'  => max(1, $default_quantity),
            'is_required'       => $is_required,
            'is_preselected'    => $is_preselected,
            'is_optional'       => $is_optional,
            'is_in_stock'       => $product->is_in_stock(),
            'variations'        => self::get_variation_options($product),
        ];
    }

    private static function is_dynamic_hamper_candidate(int $product_id): bool {
        if (!$product_id || get_post_status($product_id) !== 'publish') {
            return false;
        }

        $haystack = strtolower((string) get_the_title($product_id));
        $terms = get_the_terms($product_id, 'product_cat');
        if (is_array($terms)) {
            foreach ($terms as $term) {
                $haystack .= ' ' . strtolower($term->name . ' ' . $term->slug);
            }
        }

        $tags = get_the_terms($product_id, 'product_tag');
        if (is_array($tags)) {
            foreach ($tags as $term) {
                $haystack .= ' ' . strtolower($term->name . ' ' . $term->slug);
            }
        }

        return strpos($haystack, 'hamper') !== false;
    }

    private static function get_dynamic_item_ids(int $hamper_product_id): array {
        $product = function_exists('wc_get_product') ? wc_get_product($hamper_product_id) : null;
        if (!$product) {
            return [];
        }

        $item_ids = array_merge(
            array_map('intval', (array) $product->get_cross_sell_ids()),
            array_map('intval', (array) $product->get_upsell_ids())
        );

        if (function_exists('wc_get_related_products')) {
            $item_ids = array_merge($item_ids, wc_get_related_products($hamper_product_id, 8, [$hamper_product_id]));
        }

        $item_ids = apply_filters('tvak_hamper_dynamic_item_ids', $item_ids, $hamper_product_id);
        $item_ids = array_values(array_unique(array_filter(array_map('intval', (array) $item_ids))));

        return array_values(array_filter($item_ids, static function($item_id) use ($hamper_product_id) {
            if ($item_id === $hamper_product_id || get_post_status($item_id) !== 'publish') {
                return false;
            }

            $item_product = function_exists('wc_get_product') ? wc_get_product($item_id) : null;
            return $item_product && $item_product->is_purchasable();
        }));
    }

    private static function get_variation_options($product): array {
        $shade_options = self::get_tvak_shade_options($product);
        if (!empty($shade_options)) {
            return $shade_options;
        }

        if (!$product || !$product->is_type('variable')) {
            return [];
        }

        $options = [];
        foreach ($product->get_available_variations() as $variation) {
            $variation_id = (int) ($variation['variation_id'] ?? 0);
            $variation_obj = $variation_id ? wc_get_product($variation_id) : null;
            if (!$variation_obj || !$variation_obj->is_purchasable()) {
                continue;
            }

            $label_parts = [];
            foreach (($variation['attributes'] ?? []) as $attribute_name => $value) {
                if ($value !== '') {
                    $taxonomy = str_replace('attribute_', '', (string) $attribute_name);
                    $term = taxonomy_exists($taxonomy) ? get_term_by('slug', (string) $value, $taxonomy) : false;
                    $label_parts[] = $term && !is_wp_error($term) ? $term->name : ucwords(str_replace(['-', '_'], ' ', (string) $value));
                }
            }

            $options[] = [
                'variation_id' => $variation_id,
                'attributes'   => $variation['attributes'] ?? [],
                'label'        => $label_parts ? implode(' / ', $label_parts) : $variation_obj->get_name(),
                'price'        => (float) wc_get_price_to_display($variation_obj),
                'price_html'   => $variation_obj->get_price_html(),
                'is_in_stock'  => $variation_obj->is_in_stock(),
            ];
        }

        return $options;
    }

    private static function get_tvak_shade_options($product): array {
        if (!$product || !self::product_has_shades((int) $product->get_id())) {
            return [];
        }

        $options = [];
        $by_variation_id = [];
        $default_hex = strtolower(self::get_default_hex());
        foreach (self::get_shades_by_product((int) $product->get_id(), true) as $shade) {
            $variation_id = !empty($shade['variation_id']) ? (int) $shade['variation_id'] : 0;
            if (!$variation_id) {
                continue;
            }

            $variation_obj = $variation_id ? wc_get_product($variation_id) : null;
            if ($variation_id && (!$variation_obj || !$variation_obj->is_purchasable())) {
                continue;
            }

            $price_source = $variation_obj ?: $product;
            $option = [
                'variation_id' => $variation_id,
                'attributes'   => $variation_obj ? $variation_obj->get_variation_attributes() : [],
                'label'        => $shade['shade_name'],
                'hex'          => $shade['shade_hex'],
                'price'        => (float) wc_get_price_to_display($price_source),
                'price_html'   => $price_source->get_price_html(),
                'is_in_stock'  => !empty($shade['is_in_stock']) && (!$variation_obj || $variation_obj->is_in_stock()),
            ];

            if (!isset($by_variation_id[$variation_id])
                || self::should_replace_shade_option($by_variation_id[$variation_id], $option, $default_hex)
            ) {
                $by_variation_id[$variation_id] = $option;
            }
        }

        foreach ($by_variation_id as $option) {
            $options[] = $option;
        }

        return $options;
    }

    private static function should_replace_shade_option(array $existing, array $candidate, string $default_hex): bool {
        $existing_hex = strtolower((string) ($existing['hex'] ?? ''));
        $candidate_hex = strtolower((string) ($candidate['hex'] ?? ''));
        $existing_label = trim((string) ($existing['label'] ?? ''));
        $candidate_label = trim((string) ($candidate['label'] ?? ''));

        if ($candidate_label !== '' && strlen($candidate_label) < strlen($existing_label)) {
            return true;
        }

        if ($default_hex && $existing_hex === $default_hex && $candidate_hex !== $default_hex) {
            return true;
        }

        if ($existing_hex === $candidate_hex) {
            return $candidate_label !== '' && strlen($candidate_label) < strlen($existing_label);
        }

        return false;
    }

    private static function shades_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'tvak_product_shades';
    }

    private static function product_has_shades(int $product_id): bool {
        $meta = get_post_meta($product_id, '_tvak_has_shades', true);
        if ($meta === 'yes') {
            return true;
        }

        if ($meta === 'no') {
            return false;
        }

        return !empty(self::get_shades_by_product($product_id));
    }

    private static function get_shades_by_product(int $product_id, bool $active_only = false): array {
        global $wpdb;
        $table = self::shades_table();

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return [];
        }

        $where = $active_only ? 'AND is_in_stock = 1' : '';
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE product_id = %d {$where} ORDER BY sort_order ASC, shade_id ASC", $product_id),
            ARRAY_A
        );

        $unique_shades = [];
        $seen_names = [];
        foreach ((array) $results as $shade) {
            $name = strtolower(trim((string) ($shade['shade_name'] ?? '')));
            if ($name === '' || in_array($name, $seen_names, true)) {
                continue;
            }

            $seen_names[] = $name;
            $unique_shades[] = $shade;
        }

        return $unique_shades;
    }

    private static function get_default_hex(): string {
        $option = get_option('tvak_default_shade_hex', '');
        if (!empty($option) && preg_match('/^#[0-9A-Fa-f]{3,6}$/', (string) $option)) {
            return (string) $option;
        }

        static $db_default = null;
        if ($db_default === null) {
            global $wpdb;
            $column = $wpdb->get_row("SHOW COLUMNS FROM {$wpdb->prefix}tvak_product_shades LIKE 'shade_hex'");
            $db_default = ($column && isset($column->Default)) ? (string) $column->Default : '';
        }

        return $db_default;
    }
}
