<?php
/**
 * Product Shade Model Class
 *
 * Manages database CRUD operations for product-level shade and color variations
 * stored in wp_tvak_product_shades.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Product_Shade {

    /**
     * Get table name for product shades.
     *
     * @return string
     */
    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'tvak_product_shades';
    }

    /**
     * Retrieve all active shades configured for a product.
     *
     * @param int  $product_id Product ID.
     * @param bool $active_only Filter active stock/visible shades.
     * @return array List of shade arrays.
     */
    public static function get_shades_by_product(int $product_id, bool $active_only = false): array {
        global $wpdb;
        $table = self::get_table_name();

        // Ensure table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
        if (!$table_exists) {
            return [];
        }

        $where = $active_only ? "AND is_in_stock = 1" : "";
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE product_id = %d {$where} ORDER BY sort_order ASC, shade_id ASC", $product_id),
            ARRAY_A
        );

        $unique_shades = [];
        $seen_names    = [];
        if (!empty($results)) {
            foreach ($results as $sh) {
                $norm_name = strtolower(trim($sh['shade_name']));
                if (!in_array($norm_name, $seen_names, true)) {
                    $seen_names[]    = $norm_name;
                    $unique_shades[] = $sh;
                }
            }
        }

        return $unique_shades;
    }

    /**
     * Save or update a product shade entry.
     *
     * @param array $data Shade dataset.
     * @return int Shade ID.
     */
    public static function save_shade(array $data): int {
        global $wpdb;
        $table = self::get_table_name();

        $shade_id     = isset($data['shade_id']) ? (int) $data['shade_id'] : 0;
        $product_id   = (int) ($data['product_id'] ?? 0);
        $variation_id = !empty($data['variation_id']) ? (int) $data['variation_id'] : null;
        $shade_name   = sanitize_text_field($data['shade_name'] ?? '');
        $shade_hex    = sanitize_text_field($data['shade_hex'] ?? '#D4AF37');
        $price        = isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null;
        $image_url    = !empty($data['image_url']) ? esc_url_raw($data['image_url']) : null;
        $is_in_stock  = isset($data['is_in_stock']) ? (int) $data['is_in_stock'] : 1;
        $sort_order   = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;

        if (!$product_id || empty($shade_name)) {
            return 0;
        }

        // Check if row already exists for this product + shade_name to prevent duplicate inserts
        if (!$shade_id) {
            $existing_id = $wpdb->get_var(
                $wpdb->prepare("SELECT shade_id FROM {$table} WHERE product_id = %d AND LOWER(shade_name) = %s", $product_id, strtolower(trim($shade_name)))
            );
            if ($existing_id) {
                $shade_id = (int) $existing_id;
            }
        }

        if ($shade_id) {
            $wpdb->update(
                $table,
                [
                    'variation_id' => $variation_id,
                    'shade_name'   => $shade_name,
                    'shade_hex'    => $shade_hex,
                    'price'        => $price,
                    'image_url'    => $image_url,
                    'is_in_stock'  => $is_in_stock,
                    'sort_order'   => $sort_order,
                ],
                ['shade_id' => $shade_id],
                ['%d', '%s', '%s', '%f', '%s', '%d', '%d'],
                ['%d']
            );
            return $shade_id;
        }

        $wpdb->insert(
            $table,
            [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'shade_name'   => $shade_name,
                'shade_hex'    => $shade_hex,
                'price'        => $price,
                'image_url'    => $image_url,
                'is_in_stock'  => $is_in_stock,
                'sort_order'   => $sort_order,
            ],
            ['%d', '%d', '%s', '%s', '%f', '%s', '%d', '%d']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Delete a product shade entry.
     *
     * @param int $shade_id Shade ID.
     * @return bool
     */
    public static function delete_shade(int $shade_id): bool {
        global $wpdb;
        $table = self::get_table_name();
        return (bool) $wpdb->delete($table, ['shade_id' => $shade_id], ['%d']);
    }

    /**
     * Set whether a product has shades enabled.
     *
     * @param int  $product_id Product ID.
     * @param bool $has_shades Status.
     * @return void
     */
    public static function set_product_has_shades(int $product_id, bool $has_shades) {
        update_post_meta($product_id, '_tvak_has_shades', $has_shades ? 'yes' : 'no');
    }

    /**
     * Check whether a product has shades enabled.
     *
     * @param int $product_id Product ID.
     * @return bool
     */
    public static function get_product_has_shades(int $product_id): bool {
        $meta = get_post_meta($product_id, '_tvak_has_shades', true);
        if ($meta === 'yes') {
            return true;
        }
        if ($meta === 'no') {
            return false;
        }
        // Fallback: check if shades table has entries for this product
        $shades = self::get_shades_by_product($product_id);
        return !empty($shades);
    }
}
