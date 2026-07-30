<?php
/**
 * Variant Resolver & WooCommerce Stock Availability Engine
 *
 * Resolves variation IDs via O(1) hash lookups and validates WooCommerce stock.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Variant_Resolver {

    /**
     * Resolve target variation SKU/ID with stock verification.
     *
     * @param int               $product_id WooCommerce Product ID.
     * @param Tvak_User_Profile $profile    Customer profile.
     * @return array Array containing resolved variation_id, is_in_stock, and shade_name.
     */
    public static function resolve(int $product_id, Tvak_User_Profile $profile): array {
        $variation_id = Tvak_Variant_Map::resolve_variation($product_id, $profile->to_array());

        if (!$variation_id) {
            $variation_id = $product_id; // Default to main product ID if no variation mapped
        }

        $is_in_stock = true;
        $shade_name  = '';

        if (function_exists('wc_get_product')) {
            $wc_product = wc_get_product($variation_id);
            if ($wc_product) {
                $is_in_stock = $wc_product->is_in_stock();
                if ($wc_product->is_type('variation')) {
                    $attributes = $wc_product->get_variation_attributes();
                    $shade_name = implode(' / ', array_filter($attributes));
                } else {
                    $shade_name = $wc_product->get_name();
                }
            }
        }

        // Stock fallback logic: if resolved shade is out of stock, attempt fallback to default available variation
        if (!$is_in_stock && function_exists('wc_get_product')) {
            $parent_obj = wc_get_product($product_id);
            if ($parent_obj && $parent_obj->is_type('variable')) {
                $children = $parent_obj->get_children();
                foreach ($children as $child_id) {
                    $child_obj = wc_get_product($child_id);
                    if ($child_obj && $child_obj->is_in_stock()) {
                        $variation_id = $child_id;
                        $is_in_stock  = true;
                        $shade_name   = implode(' / ', array_filter($child_obj->get_variation_attributes()));
                        break;
                    }
                }
            }
        }

        return [
            'variation_id' => (int) $variation_id,
            'is_in_stock'  => (bool) $is_in_stock,
            'shade_name'   => (string) $shade_name,
        ];
    }
}
