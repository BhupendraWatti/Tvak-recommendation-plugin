<?php
/**
 * Bi-Directional Shade & Variation Synchronization Engine
 *
 * Provides real-time, two-way dynamic synchronization between WooCommerce
 * product variations (wp_posts / WC_Product_Variation) and TVAK product shades
 * (wp_tvak_product_shades).
 *
 * @package TVAK_Beauty_Kit
 * @version 1.3.0
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

        // Direction A Fallback: Save post hook for product_variation
        add_action('save_post_product_variation', [__CLASS__, 'on_save_post_variation'], 10, 3);
    }

    /**
     * Handle WC Variation Save action.
     *
     * @param int $variation_id WC Variation Post ID.
     * @param int $i            Variation index.
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

            // Extract shade name from variation attributes or title
            $shade_name = '';
            if (function_exists('wc_get_product')) {
                $wc_var = wc_get_product($variation_id);
                if ($wc_var && $wc_var->is_type('variation')) {
                    $attributes = $wc_var->get_variation_attributes();
                    $shade_name = implode(' / ', array_filter($attributes));
                }
            }

            if (empty($shade_name)) {
                $title_parts = explode('-', $variation_post->post_title);
                $shade_name  = trim(end($title_parts));
            }

            if (empty($shade_name)) {
                $shade_name = 'Variation #' . $variation_id;
            }

            // Extract Price, Stock Status, Image
            $price = get_post_meta($variation_id, '_price', true);
            $price_val = ($price !== '' && $price !== false) ? (float) $price : null;

            $stock_status = get_post_meta($variation_id, '_stock_status', true);
            $is_in_stock  = ($stock_status !== 'outofstock') ? 1 : 0;

            $img_id    = get_post_meta($variation_id, '_thumbnail_id', true);
            $image_url = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : null;

            // Extract or generate Shade Hex code
            $shade_hex = get_post_meta($variation_id, '_shade_hex', true)
                ?: get_post_meta($variation_id, '_swatch_color', true);

            if (empty($shade_hex) && class_exists('Tvak_Variant_Resolver')) {
                $shade_hex = Tvak_Variant_Resolver::get_shade_hex($shade_name, $product_id, $variation_id);
            }

            if (empty($shade_hex)) {
                $shade_hex = '#D4AF37';
            }

            // Save or update TVAK Product Shade row
            if (class_exists('Tvak_Product_Shade')) {
                Tvak_Product_Shade::save_shade([
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id,
                    'shade_name'   => $shade_name,
                    'shade_hex'    => $shade_hex,
                    'price'        => $price_val,
                    'image_url'    => $image_url,
                    'is_in_stock'  => $is_in_stock,
                ]);

                Tvak_Product_Shade::set_product_has_shades($product_id, true);
            }

            // Invalidate rules cache so engine picks up changes immediately
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
     * @param array $shade_data Saved shade array containing product_id, variation_id, shade_name, shade_hex, price, is_in_stock.
     * @return int Resolved or newly created WooCommerce variation ID.
     */
    public static function sync_tvak_shade_to_wc(array $shade_data): int {
        if (self::$is_syncing) {
            return (int) ($shade_data['variation_id'] ?? 0);
        }

        $product_id   = (int) ($shade_data['product_id'] ?? 0);
        $variation_id = !empty($shade_data['variation_id']) ? (int) $shade_data['variation_id'] : 0;
        $shade_name   = sanitize_text_field($shade_data['shade_name'] ?? '');
        $shade_hex    = sanitize_text_field($shade_data['shade_hex'] ?? '#D4AF37');
        $price        = isset($shade_data['price']) && $shade_data['price'] !== '' ? (float) $shade_data['price'] : null;
        $is_in_stock  = isset($shade_data['is_in_stock']) ? (int) $shade_data['is_in_stock'] : 1;

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

            // Ensure parent WooCommerce product is set to variable type if shades exist
            if (function_exists('wp_set_object_terms') && function_exists('wc_get_product')) {
                $wc_parent = wc_get_product($product_id);
                if ($wc_parent && !$wc_parent->is_type('variable')) {
                    wp_set_object_terms($product_id, 'variable', 'product_type');
                }
            }

            // Verify or Create WooCommerce Variation Post
            if ($variation_id && get_post($variation_id)) {
                // Existing variation: update meta
                update_post_meta($variation_id, '_tvak_shade_name', $shade_name);
                update_post_meta($variation_id, '_tvak_shade_hex', $shade_hex);
                update_post_meta($variation_id, '_shade_hex', $shade_hex);
                update_post_meta($variation_id, 'attribute_color', $shade_name);
                update_post_meta($variation_id, 'attribute_pa_color', $shade_name);

                if ($price !== null) {
                    update_post_meta($variation_id, '_regular_price', $price);
                    update_post_meta($variation_id, '_price', $price);
                }

                update_post_meta($variation_id, '_stock_status', $is_in_stock ? 'instock' : 'outofstock');

            } else {
                // Check if a WooCommerce variation post with this title/attribute already exists
                global $wpdb;
                $existing_var_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->prefix}posts WHERE post_parent = %d AND post_type = 'product_variation' AND (post_title LIKE %s OR ID IN (SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key IN ('attribute_color','attribute_pa_color','_tvak_shade_name') AND LOWER(meta_value) = %s)) LIMIT 1",
                        $product_id,
                        '%' . $wpdb->esc_like($shade_name) . '%',
                        strtolower(trim($shade_name))
                    )
                );

                if ($existing_var_id) {
                    $variation_id = (int) $existing_var_id;
                } else {
                    // Create new WooCommerce Product Variation post
                    $variation_post_id = wp_insert_post([
                        'post_title'   => $parent_post->post_title . ' - ' . $shade_name,
                        'post_name'    => sanitize_title($parent_post->post_title . '-' . $shade_name),
                        'post_status'  => 'publish',
                        'post_parent'  => $product_id,
                        'post_type'    => 'product_variation',
                        'menu_order'   => 0,
                    ]);

                    if ($variation_post_id && !is_wp_error($variation_post_id)) {
                        $variation_id = (int) $variation_post_id;
                    }
                }

                if ($variation_id) {
                    // Set WC Variation Attributes and Meta
                    update_post_meta($variation_id, '_tvak_shade_name', $shade_name);
                    update_post_meta($variation_id, '_tvak_shade_hex', $shade_hex);
                    update_post_meta($variation_id, '_shade_hex', $shade_hex);
                    update_post_meta($variation_id, 'attribute_color', $shade_name);
                    update_post_meta($variation_id, 'attribute_pa_color', $shade_name);

                    if ($price !== null) {
                        update_post_meta($variation_id, '_regular_price', $price);
                        update_post_meta($variation_id, '_price', $price);
                    } else {
                        // Inherit parent product price if available
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

        // Fetch all parent WooCommerce products that have children variations
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

        foreach ($variable_products as $vp) {
            $product_id = (int) $vp['product_id'];

            // Fetch all variation children for this product
            $variations = $wpdb->get_results($wpdb->prepare("
                SELECT ID, post_title 
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
                $var_id = (int) $v['ID'];

                // Check if already present in wp_tvak_product_shades
                $existing = $wpdb->get_var($wpdb->prepare("
                    SELECT shade_id FROM {$shades_table} 
                    WHERE product_id = %d AND variation_id = %d
                ", $product_id, $var_id));

                if (!$existing) {
                    // Trigger sync to populate wp_tvak_product_shades
                    self::sync_wc_variation_to_tvak($var_id, $idx);
                    $synced_count++;
                }
            }
        }

        return $synced_count;
    }
}
