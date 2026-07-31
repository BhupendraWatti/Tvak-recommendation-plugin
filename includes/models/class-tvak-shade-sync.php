<?php
/**
 * Bi-Directional Shade & Variation Synchronization Engine
 *
 * Provides real-time, two-way dynamic synchronization between WooCommerce
 * product variations (wp_posts / WC_Product_Variation) and TVAK product shades
 * (wp_tvak_product_shades).
 *
 * @package TVAK_Beauty_Kit
 * @version 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Shade_Sync {

    /**
     * Flag to prevent infinite recursion during bi-directional saving.
     * @var bool
     */
    private static $is_syncing = false;

    /**
     * Initialize WordPress & WooCommerce action hooks.
     */
    public static function init() {
        // Direction A: WooCommerce Variation saved/updated -> Sync to TVAK Product Shades
        add_action('woocommerce_save_product_variation', [__CLASS__, 'sync_wc_variation_to_tvak'], 10, 2);
        add_action('woocommerce_update_product_variation', [__CLASS__, 'sync_wc_variation_to_tvak'], 10, 2);
        add_action('woocommerce_new_product_variation', [__CLASS__, 'sync_wc_variation_to_tvak'], 10, 2);
        add_action('save_post_product_variation', [__CLASS__, 'on_save_post_variation'], 10, 3);

        // Product Lifecycle Hooks: Product Created / Updated / Deleted
        add_action('woocommerce_new_product', [__CLASS__, 'sync_wc_product_lifecycle'], 10, 1);
        add_action('woocommerce_update_product', [__CLASS__, 'sync_wc_product_lifecycle'], 10, 1);
        add_action('save_post_product', [__CLASS__, 'on_save_post_product'], 10, 3);
        add_action('woocommerce_delete_product', [__CLASS__, 'sync_wc_product_deletion'], 10, 1);
        add_action('wp_trash_post', [__CLASS__, 'sync_wc_product_deletion'], 10, 1);

        // WooCommerce Attribute & Term Swatch Hooks
        add_action('created_term', [__CLASS__, 'on_term_modified'], 10, 3);
        add_action('edited_term', [__CLASS__, 'on_term_modified'], 10, 3);
        add_action('woocommerce_attribute_added', [__CLASS__, 'on_attribute_modified'], 10, 2);
        add_action('woocommerce_attribute_updated', [__CLASS__, 'on_attribute_modified'], 10, 3);
    }

    /**
     * Resolve the default hex colour from WP Options — no hardcoded brand value.
     *
     * Priority order:
     *  1. wp_option tvak_default_shade_hex (admin-editable from TVAK Settings)
     *  2. shade_hex column DEFAULT from wp_tvak_product_shades (read once, statically cached)
     *  3. Empty string when no configured fallback exists
     *
     * @return string Valid 3-6 char hex string (with leading #).
     */
    public static function get_default_hex(): string {
        $option = get_option('tvak_default_shade_hex', '');
        if (!empty($option) && preg_match('/^#[0-9A-Fa-f]{3,6}$/', $option)) {
            return $option;
        }

        // DB column default — read once and cache
        static $db_default = null;
        if ($db_default === null) {
            global $wpdb;
            $col = $wpdb->get_row("SHOW COLUMNS FROM {$wpdb->prefix}tvak_product_shades LIKE 'shade_hex'");
            $db_default = ($col && isset($col->Default)) ? (string) $col->Default : '';
        }

        return $db_default;
    }

    /**
     * Resolve which postmeta attribute keys a WC product variation uses.
     *
     * WooCommerce variation attribute meta keys are built as:
     *   - attribute_{local_slug}      for custom/local attributes (is_taxonomy = 0)
     *   - attribute_pa_{tax_slug}     for taxonomy attributes (is_taxonomy = 1, pa_*)
     *
     * Reading the parent's _product_attributes tells us the exact keys in use
     * so we never write phantom keys that break the variation selector.
     *
     * @param int $product_id Parent product post ID.
     * @return string[] Array of meta key strings.
     */
    public static function resolve_attribute_meta_keys(int $product_id): array {
        $product_attrs = get_post_meta($product_id, '_product_attributes', true);

        if (empty($product_attrs) || !is_array($product_attrs)) {
            return ['attribute_color'];
        }

        $keys = [];
        foreach ($product_attrs as $slug => $attr_def) {
            if (empty($attr_def['is_variation'])) {
                continue;
            }
            $clean = sanitize_title($slug);
            if (!empty($attr_def['is_taxonomy'])) {
                // Taxonomy attribute (e.g. pa_color) -> meta key attribute_pa_color
                $keys[] = 'attribute_pa_' . $clean;
            } else {
                // Local/custom attribute (e.g. color) -> meta key attribute_color
                $keys[] = 'attribute_' . $clean;
            }
        }

        return !empty($keys) ? $keys : ['attribute_color'];
    }

    /**
     * Extract HEX color from WooCommerce Swatches termmeta.
     * Supports Emran Ahmed's Swatches, CartFlows, RadiusTheme, VillaTheme, etc.
     *
     * @param int    $term_id  Term ID.
     * @param string $taxonomy Taxonomy slug (e.g. pa_color).
     * @return string HEX color code with leading # or empty string.
     */
    /**
     * Extract HEX color from WooCommerce Swatches termmeta.
     * 100% dynamic — inspects all termmeta keys registered on the term.
     *
     * @param int    $term_id  Term ID.
     * @param string $taxonomy Taxonomy slug (e.g. pa_color).
     * @return string HEX color code with leading # or empty string.
     */
    public static function get_term_swatch_color($term_id, $taxonomy = ''): string {
        if (!$term_id) {
            return '';
        }

        $all_term_meta = get_term_meta($term_id);
        if (is_array($all_term_meta)) {
            foreach ($all_term_meta as $key => $values) {
                if (is_array($values)) {
                    foreach ($values as $val) {
                        if (is_string($val) && preg_match('/^#[0-9A-Fa-f]{3,6}$/', trim($val))) {
                            return trim($val);
                        }
                    }
                } elseif (is_string($values) && preg_match('/^#[0-9A-Fa-f]{3,6}$/', trim($values))) {
                    return trim($values);
                }
            }
        }

        return '';
    }

    /**
     * Extract a HEX color from parent product swatch metadata.
     *
     * Many WooCommerce swatch plugins store local/custom attribute colors as a
     * serialized or JSON array on the parent product rather than termmeta.
     *
     * @param int      $product_id       Parent product ID.
     * @param string   $shade_name       Resolved variation shade label.
     * @param string[] $attribute_values Raw variation attribute values.
     * @return string HEX color code with leading # or empty string.
     */
    public static function get_parent_product_swatch_color(int $product_id, string $shade_name = '', array $attribute_values = []): string {
        if (!$product_id) {
            return '';
        }

        $needles = [];
        foreach (array_merge([$shade_name], $attribute_values) as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            $needles[] = self::normalize_swatch_token($candidate);
            $needles[] = self::normalize_swatch_token(sanitize_title($candidate));
        }
        $needles = array_values(array_unique(array_filter($needles)));

        if (empty($needles)) {
            return '';
        }

        $all_post_meta = get_post_meta($product_id);
        if (!is_array($all_post_meta)) {
            return '';
        }

        foreach ($all_post_meta as $meta_key => $values) {
            foreach ((array) $values as $value) {
                $hex = self::find_matching_hex_in_value($value, $needles, false, (string) $meta_key);
                if (!empty($hex)) {
                    return $hex;
                }
            }
        }

        return '';
    }

    private static function normalize_swatch_token(string $value): string {
        $value = strtolower(trim(wp_strip_all_tags($value)));
        $value = sanitize_title($value);
        return preg_replace('/[^a-z0-9]+/', '', $value);
    }

    private static function normalize_hex(string $value): string {
        $value = trim($value);
        if (preg_match('/^#[0-9A-Fa-f]{3,6}$/', $value)) {
            return $value;
        }
        return '';
    }

    private static function first_hex_in_value($value): string {
        if (is_string($value)) {
            if (preg_match('/#[0-9A-Fa-f]{3,6}/', $value, $matches)) {
                return self::normalize_hex($matches[0]);
            }
            return '';
        }

        if (is_array($value) || is_object($value)) {
            foreach ((array) $value as $child) {
                $hex = self::first_hex_in_value($child);
                if (!empty($hex)) {
                    return $hex;
                }
            }
        }

        return '';
    }

    private static function value_contains_swatch_token($value, array $needles): bool {
        if (is_string($value) || is_numeric($value)) {
            $token = self::normalize_swatch_token((string) $value);
            foreach ($needles as $needle) {
                if ($needle !== '' && $token !== '' && (strpos($token, $needle) !== false || strpos($needle, $token) !== false)) {
                    return true;
                }
            }
            return false;
        }

        if (is_array($value) || is_object($value)) {
            foreach ((array) $value as $child_key => $child_value) {
                if (in_array(self::normalize_swatch_token((string) $child_key), $needles, true) || self::value_contains_swatch_token($child_value, $needles)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function find_matching_hex_in_value($value, array $needles, bool $context_matched = false, string $key = ''): string {
        if ($key !== '' && in_array(self::normalize_swatch_token($key), $needles, true)) {
            $context_matched = true;
        }

        if (is_string($value)) {
            $unserialized = function_exists('maybe_unserialize') ? maybe_unserialize($value) : @unserialize($value);
            if ($unserialized !== $value && (is_array($unserialized) || is_object($unserialized))) {
                return self::find_matching_hex_in_value($unserialized, $needles, $context_matched, $key);
            }

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return self::find_matching_hex_in_value($decoded, $needles, $context_matched, $key);
            }

            if (!$context_matched) {
                $token = self::normalize_swatch_token($value);
                foreach ($needles as $needle) {
                    if ($needle !== '' && (strpos($token, $needle) !== false || strpos($needle, $token) !== false)) {
                        $context_matched = true;
                        break;
                    }
                }
            }

            return $context_matched ? self::first_hex_in_value($value) : '';
        }

        if (is_array($value) || is_object($value)) {
            $array_value = (array) $value;
            $local_match = false;
            foreach ($array_value as $child_key => $child_value) {
                $child_key_match = in_array(self::normalize_swatch_token((string) $child_key), $needles, true);
                $child_value_match = self::value_contains_swatch_token($child_value, $needles);
                $child_context = $context_matched || $child_key_match;
                $local_match = $local_match || $child_key_match || $child_value_match;

                if ($child_context) {
                    $hex = self::first_hex_in_value($child_value);
                    if (!empty($hex)) {
                        return $hex;
                    }
                }

                $hex = self::find_matching_hex_in_value($child_value, $needles, $child_context, (string) $child_key);
                if (!empty($hex)) {
                    return $hex;
                }
            }

            if ($context_matched || $local_match) {
                return self::first_hex_in_value($array_value);
            }
        }

        return $context_matched ? self::first_hex_in_value($value) : '';
    }

    /**
     * Handle WC Variation Save action (Direction A: WC -> TVAK).
     *
     * @param int $variation_id WC Variation Post ID.
     * @param int $i            Variation index (unused, kept for hook compatibility).
     * @return void
     */
    public static function sync_wc_variation_to_tvak($variation_id, $i = 0) {
        if (self::$is_syncing || !$variation_id) {
            return;
        }

        self::$is_syncing = true;

        try {
            $variation_post = get_post($variation_id);
            if (!$variation_post || $variation_post->post_type !== 'product_variation') {
                self::$is_syncing = false;
                return;
            }

            $product_id = $variation_post->post_parent;
            if (!$product_id) {
                self::$is_syncing = false;
                return;
            }

            // Extract shade name dynamically from WC variation attributes
            $shade_name = '';
            if (function_exists('wc_get_product')) {
                $wc_var = wc_get_product($variation_id);
                if ($wc_var && $wc_var->is_type('variation')) {
                    $attrs = $wc_var->get_variation_attributes();
                    $shade_name = implode(' / ', array_filter(array_values($attrs)));
                }
            }

            // Fallback: parse last segment of a standard variation title.
            if (empty($shade_name)) {
                $parts      = explode(' - ', $variation_post->post_title, 2);
                $shade_name = isset($parts[1]) ? trim($parts[1]) : trim(end(explode('-', $variation_post->post_title)));
            }

            if (empty($shade_name)) {
                $shade_name = 'Variation #' . $variation_id;
            }

            // Price, Stock, Image
            $price_raw    = get_post_meta($variation_id, '_price', true);
            $price_val    = ($price_raw !== '' && $price_raw !== false) ? (float) $price_raw : null;
            $stock_status = get_post_meta($variation_id, '_stock_status', true);
            $is_in_stock  = ($stock_status !== 'outofstock') ? 1 : 0;
            $img_id       = get_post_meta($variation_id, '_thumbnail_id', true);
            $image_url    = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : null;
            $sort_order   = max(1, (int) $variation_post->menu_order);
            if ($sort_order === 1 && is_numeric($i)) {
                $sort_order = max(1, (int) $i + 1);
            }

            // 1. Dynamic Postmeta HEX Extraction: inspect all postmeta registered on variation post
            $shade_hex = '';
            $all_post_meta = get_post_meta($variation_id);
            if (is_array($all_post_meta)) {
                foreach ($all_post_meta as $mk => $mval) {
                    if (strpos($mk, 'color') !== false || strpos($mk, 'hex') !== false || strpos($mk, 'swatch') !== false) {
                        $v_str = is_array($mval) ? reset($mval) : $mval;
                        if (is_string($v_str) && preg_match('/^#[0-9A-Fa-f]{3,6}$/', trim($v_str))) {
                            $shade_hex = trim($v_str);
                            break;
                        }
                    }
                }
            }

            // 2. Dynamic Termmeta HEX Extraction: inspect all attribute terms
            if (empty($shade_hex) && function_exists('wc_get_product')) {
                $wc_var = wc_get_product($variation_id);
                if ($wc_var && $wc_var->is_type('variation')) {
                    $v_attrs = $wc_var->get_variation_attributes();
                    foreach ($v_attrs as $tax_key => $term_val) {
                        if (!empty($term_val)) {
                            $tax_name = str_replace('attribute_', '', $tax_key);
                            
                            $term_obj = get_term_by('slug', $term_val, $tax_name);
                            if (!$term_obj) {
                                $term_obj = get_term_by('slug', sanitize_title($term_val), $tax_name);
                            }
                            if (!$term_obj) {
                                $term_obj = get_term_by('name', $term_val, $tax_name);
                            }
                            if (!$term_obj && !empty($shade_name)) {
                                $term_obj = get_term_by('name', $shade_name, $tax_name);
                            }

                            if ($term_obj && !is_wp_error($term_obj)) {
                                $term_hex = self::get_term_swatch_color($term_obj->term_id, $tax_name);
                                if (!empty($term_hex)) {
                                    $shade_hex = $term_hex;
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            // 3. Parent Product Swatch Tab Extraction: local attributes often store
            // serialized swatch maps on the parent product rather than termmeta.
            if (empty($shade_hex)) {
                $attribute_values = [];
                if (function_exists('wc_get_product')) {
                    $wc_var = wc_get_product($variation_id);
                    if ($wc_var && $wc_var->is_type('variation')) {
                        $attribute_values = array_filter(array_values($wc_var->get_variation_attributes()));
                    }
                }
                $shade_hex = self::get_parent_product_swatch_color($product_id, $shade_name, $attribute_values);
            }

            if (empty($shade_hex) && class_exists('Tvak_Variant_Resolver')) {
                $shade_hex = Tvak_Variant_Resolver::get_shade_hex($shade_name, $product_id, $variation_id);
            }

            if (empty($shade_hex)) {
                $shade_hex = self::get_default_hex();
            }

            // Persist to TVAK shades table
            if (class_exists('Tvak_Product_Shade')) {
                Tvak_Product_Shade::save_shade([
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id,
                    'shade_name'   => $shade_name,
                    'shade_hex'    => $shade_hex,
                    'price'        => $price_val,
                    'image_url'    => $image_url,
                    'is_in_stock'  => $is_in_stock,
                    'sort_order'   => $sort_order,
                ]);

                Tvak_Product_Shade::set_product_has_shades($product_id, true);
            }

            if (class_exists('Tvak_Cache')) {
                Tvak_Cache::invalidate_rules_cache();
            }

        } catch (\Exception $e) {
            error_log('Tvak_Shade_Sync Exception (WC -> TVAK): ' . $e->getMessage());
        }

        self::$is_syncing = false;
    }

    /**
     * Fallback listener for save_post_product_variation.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Is update.
     * @return void
     */
    public static function on_save_post_variation($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        self::sync_wc_variation_to_tvak($post_id);
    }

    /**
     * Direction B: TVAK Product Shade saved/updated -> Sync to WooCommerce Variation.
     *
     * Called automatically whenever a shade is saved via Tvak_Product_Shade::save_shade().
     *
     * @param array $shade_data Saved shade array.
     * @return int Resolved or newly created WooCommerce variation ID.
     */
    public static function sync_tvak_shade_to_wc(array $shade_data): int {
        if (self::$is_syncing) {
            return (int) ($shade_data['variation_id'] ?? 0);
        }

        $product_id   = (int) ($shade_data['product_id'] ?? 0);
        $variation_id = !empty($shade_data['variation_id']) ? (int) $shade_data['variation_id'] : 0;
        $shade_name   = sanitize_text_field($shade_data['shade_name'] ?? '');
        // Dynamic fallback — reads WP option or DB column default; no hardcoded PHP value
        $shade_hex    = sanitize_text_field(!empty($shade_data['shade_hex']) ? $shade_data['shade_hex'] : self::get_default_hex());
        $price        = isset($shade_data['price']) && $shade_data['price'] !== '' ? (float) $shade_data['price'] : null;
        $is_in_stock  = isset($shade_data['is_in_stock']) ? (int) $shade_data['is_in_stock'] : 1;
        $image_url    = !empty($shade_data['image_url']) ? esc_url_raw($shade_data['image_url']) : '';
        $sort_order   = isset($shade_data['sort_order']) ? max(0, (int) $shade_data['sort_order']) : 0;

        if (!$product_id || empty($shade_name)) {
            return 0;
        }

        self::$is_syncing = true;

        try {
            $parent_post = get_post($product_id);
            if (!$parent_post || $parent_post->post_type !== 'product') {
                self::$is_syncing = false;
                return $variation_id;
            }

            // Ensure product type is variable
            if (function_exists('wp_set_object_terms') && function_exists('wc_get_product')) {
                $wc_parent = wc_get_product($product_id);
                if ($wc_parent && !$wc_parent->is_type('variable')) {
                    wp_set_object_terms($product_id, 'variable', 'product_type');
                }
            }

            // Determine attribute meta keys for this specific product — fully dynamic
            $attr_keys    = self::resolve_attribute_meta_keys($product_id);
            $attr_meta_in = "'" . implode("','", array_map('esc_sql', array_merge($attr_keys, ['_tvak_shade_name']))) . "'";

            if ($variation_id && get_post($variation_id)) {
                // Update existing variation
                update_post_meta($variation_id, '_tvak_shade_name', $shade_name);
                update_post_meta($variation_id, '_tvak_shade_hex', $shade_hex);
                update_post_meta($variation_id, '_shade_hex', $shade_hex);

                foreach ($attr_keys as $attr_key) {
                    update_post_meta($variation_id, $attr_key, $shade_name);
                }

                wp_update_post([
                    'ID'         => $variation_id,
                    'post_title' => $parent_post->post_title . ' - ' . $shade_name,
                    'post_name'  => sanitize_title($parent_post->post_title . '-' . $shade_name),
                    'menu_order' => $sort_order,
                ]);

                if (!empty($image_url) && function_exists('attachment_url_to_postid')) {
                    $attachment_id = attachment_url_to_postid($image_url);
                    if ($attachment_id) {
                        update_post_meta($variation_id, '_thumbnail_id', $attachment_id);
                    }
                }

                if ($price !== null) {
                    update_post_meta($variation_id, '_regular_price', $price);
                    update_post_meta($variation_id, '_price', $price);
                }

                update_post_meta($variation_id, '_stock_status', $is_in_stock ? 'instock' : 'outofstock');

            } else {
                // Find or create the WC variation post
                global $wpdb;
                $existing_var_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->prefix}posts
                         WHERE post_parent = %d AND post_type = 'product_variation'
                           AND (post_title LIKE %s
                                OR ID IN (
                                    SELECT post_id FROM {$wpdb->prefix}postmeta
                                    WHERE meta_key IN ({$attr_meta_in})
                                      AND LOWER(meta_value) = %s
                                ))
                         LIMIT 1",
                        $product_id,
                        '%' . $wpdb->esc_like($shade_name) . '%',
                        strtolower(trim($shade_name))
                    )
                );

                if ($existing_var_id) {
                    $variation_id = (int) $existing_var_id;
                } else {
                    $variation_post_id = wp_insert_post([
                        'post_title'  => $parent_post->post_title . ' - ' . $shade_name,
                        'post_name'   => sanitize_title($parent_post->post_title . '-' . $shade_name),
                        'post_status' => 'publish',
                        'post_parent' => $product_id,
                        'post_type'   => 'product_variation',
                        'menu_order'  => $sort_order,
                    ]);

                    if ($variation_post_id && !is_wp_error($variation_post_id)) {
                        $variation_id = (int) $variation_post_id;
                    }
                }

                if ($variation_id) {
                    update_post_meta($variation_id, '_tvak_shade_name', $shade_name);
                    update_post_meta($variation_id, '_tvak_shade_hex', $shade_hex);
                    update_post_meta($variation_id, '_shade_hex', $shade_hex);

                    foreach ($attr_keys as $attr_key) {
                        update_post_meta($variation_id, $attr_key, $shade_name);
                    }

                    if (!empty($image_url) && function_exists('attachment_url_to_postid')) {
                        $attachment_id = attachment_url_to_postid($image_url);
                        if ($attachment_id) {
                            update_post_meta($variation_id, '_thumbnail_id', $attachment_id);
                        }
                    }

                    if ($price !== null) {
                        update_post_meta($variation_id, '_regular_price', $price);
                        update_post_meta($variation_id, '_price', $price);
                    } else {
                        $parent_price = get_post_meta($product_id, '_price', true);
                        if (!empty($parent_price)) {
                            update_post_meta($variation_id, '_regular_price', $parent_price);
                            update_post_meta($variation_id, '_price', $parent_price);
                        }
                    }

                    update_post_meta($variation_id, '_stock_status', $is_in_stock ? 'instock' : 'outofstock');
                }
            }

        } catch (\Exception $e) {
            error_log('Tvak_Shade_Sync Exception (TVAK -> WC): ' . $e->getMessage());
        }

        self::$is_syncing = false;

        return $variation_id;
    }

    /**
     * Auto-Heal & Full Catalog Sync (Dynamic DB reconciler).
     *
     * Scans all WooCommerce variable products in the database and imports any
     * missing variations directly into wp_tvak_product_shades.
     *
     * @return int Number of newly imported or updated shade entries.
     */
    public static function auto_sync_catalog(): int {
        global $wpdb;
        $shades_table = $wpdb->prefix . 'tvak_product_shades';
        $synced_count = 0;

        $variable_products = $wpdb->get_results("
            SELECT DISTINCT post_parent AS product_id
            FROM {$wpdb->prefix}posts
            WHERE post_type = 'product_variation'
              AND post_status = 'publish'
              AND post_parent > 0
        ", ARRAY_A);

        if (empty($variable_products)) {
            return 0;
        }

        foreach ($variable_products as $p) {
            $product_id = (int) (is_array($p) ? ($p['product_id'] ?? 0) : ($p->product_id ?? 0));
            if (!$product_id) {
                continue;
            }

            $variations = $wpdb->get_results($wpdb->prepare("
                SELECT ID
                FROM {$wpdb->prefix}posts
                WHERE post_parent = %d
                  AND post_type = 'product_variation'
                  AND post_status = 'publish'
                ORDER BY ID ASC
            ", $product_id), ARRAY_A);

            if (empty($variations)) {
                continue;
            }

            Tvak_Product_Shade::set_product_has_shades($product_id, true);

            foreach ($variations as $idx => $v) {
                $var_id = (int) (is_array($v) ? ($v['ID'] ?? $v['id'] ?? 0) : ($v->ID ?? $v->id ?? 0));
                if (!$var_id) {
                    continue;
                }

                $existing = $wpdb->get_row($wpdb->prepare("
                    SELECT shade_id, shade_hex, sort_order FROM {$shades_table}
                    WHERE product_id = %d AND variation_id = %d
                ", $product_id, $var_id), ARRAY_A);

                $needs_refresh = !$existing;
                if ($existing) {
                    $existing_hex = trim((string) ($existing['shade_hex'] ?? ''));
                    $needs_refresh = (
                        empty($existing_hex) ||
                        strcasecmp($existing_hex, self::get_default_hex()) === 0 ||
                        (int) ($existing['sort_order'] ?? 0) === 0
                    );
                }

                if ($needs_refresh) {
                    self::sync_wc_variation_to_tvak($var_id, $idx);
                    $synced_count++;
                }
            }
        }

        return $synced_count;
    }

    /**
     * Sync WooCommerce Product lifecycle changes (New / Update) to TVAK engine.
     * Automatically registers product rule and kit slot, and auto-syncs shades.
     *
     * @param int $product_id Product Post ID.
     * @return void
     */
    public static function sync_wc_product_lifecycle($product_id) {
        if (self::$is_syncing || !$product_id) {
            return;
        }

        $post = get_post($product_id);
        if (!$post || $post->post_type !== 'product' || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            return;
        }

        self::$is_syncing = true;

        try {
            // Auto-detect & auto-assign kit slot if rule does not exist
            if (class_exists('Tvak_Product_Rule')) {
                $existing_rule = Tvak_Product_Rule::get_by_product_id($product_id);
                if (!$existing_rule) {
                    $slot_id = self::detect_product_kit_slot($product_id);
                    Tvak_Product_Rule::save_rule($product_id, $slot_id, 0.00, 1, []);
                }
            }

            // Sync child variations / shades if product has variations
            self::auto_sync_product_variations($product_id);

            if (class_exists('Tvak_Cache')) {
                Tvak_Cache::invalidate_rules_cache();
            }
        } catch (\Exception $e) {
            error_log('Tvak_Shade_Sync Exception (Product Lifecycle): ' . $e->getMessage());
        }

        self::$is_syncing = false;
    }

    /**
     * Resolve the recommendation slot for a WooCommerce product from categories.
     */
    public static function detect_product_kit_slot(int $product_id): int {
        global $wpdb;
        $table_slots = $wpdb->prefix . 'tvak_kit_slots';

        $terms = function_exists('wp_get_post_terms')
            ? wp_get_post_terms($product_id, ['product_cat'], ['fields' => 'all'])
            : [];

        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $slot_code = 'product_cat_' . sanitize_key($term->slug);
                $slot_id = (int) $wpdb->get_var(
                    $wpdb->prepare("SELECT slot_id FROM {$table_slots} WHERE slot_code = %s", $slot_code)
                );
                if ($slot_id) {
                    return $slot_id;
                }
            }
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT slot_id FROM {$table_slots} WHERE slot_code = %s", 'woocommerce_products')
        );
    }

    /**
     * Auto-sync variations for a specific variable product.
     */
    public static function auto_sync_product_variations(int $product_id) {
        $children = get_posts([
            'post_parent' => $product_id,
            'post_type'   => 'product_variation',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
        ]);

        if (!empty($children)) {
            if (class_exists('Tvak_Product_Shade')) {
                Tvak_Product_Shade::set_product_has_shades($product_id, true);
            }
            foreach ($children as $idx => $child_id) {
                self::sync_wc_variation_to_tvak($child_id, $idx);
            }
        }
    }

    /**
     * Sync WooCommerce Product Deletion.
     */
    public static function sync_wc_product_deletion($post_id) {
        if (!$post_id) {
            return;
        }

        $post_type = get_post_type($post_id);
        if ($post_type !== 'product' && $post_type !== 'product_variation') {
            return;
        }

        if ($post_type === 'product' && class_exists('Tvak_Product_Rule')) {
            Tvak_Product_Rule::delete_rule($post_id);
        }

        if (class_exists('Tvak_Product_Shade')) {
            global $wpdb;
            $table = Tvak_Product_Shade::get_table_name();
            if ($post_type === 'product') {
                $wpdb->delete($table, ['product_id' => $post_id], ['%d']);
            } else {
                $wpdb->delete($table, ['variation_id' => $post_id], ['%d']);
            }
        }

        if (class_exists('Tvak_Cache')) {
            Tvak_Cache::invalidate_rules_cache();
        }
    }

    public static function on_save_post_product($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        self::sync_wc_product_lifecycle($post_id);
    }

    public static function on_term_modified($term_id, $tt_id, $taxonomy) {
        if (strpos($taxonomy, 'pa_') === 0 || in_array($taxonomy, ['product_cat', 'product_tag'], true)) {
            if (class_exists('Tvak_Cache')) {
                Tvak_Cache::invalidate_rules_cache();
            }
        }
    }

    public static function on_attribute_modified() {
        if (class_exists('Tvak_Cache')) {
            Tvak_Cache::invalidate_rules_cache();
        }
    }
}
