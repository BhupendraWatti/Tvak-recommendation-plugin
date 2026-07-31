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
     * Calibrated Shade Hex Color Mapping Matrix
     *
     * Maps shade names and keywords to luxury cosmetic hex colors.
     *
     * @param string $shade_name Raw shade string or variation attribute.
     * @return string Hex color code.
     */
    public static function get_shade_hex(string $shade_name, int $product_id = 0, int $variation_id = 0): string {
        $shade_name_trimmed = trim($shade_name);

        // 1. Check custom database table wp_tvak_product_shades for saved shade_hex
        if ($product_id && class_exists('Tvak_Product_Shade')) {
            global $wpdb;
            $table = Tvak_Product_Shade::get_table_name();
            $hex   = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT shade_hex FROM {$table} WHERE product_id = %d AND LOWER(TRIM(shade_name)) = %s AND shade_hex IS NOT NULL AND shade_hex != '' LIMIT 1",
                    $product_id,
                    strtolower($shade_name_trimmed)
                )
            );
            if (!empty($hex)) {
                return $hex;
            }
        }

        // 2. Check variation meta in WooCommerce (_shade_hex, _swatch_color, _wcvs_swatch_color)
        if ($variation_id) {
            $v_hex = get_post_meta($variation_id, '_shade_hex', true)
                ?: get_post_meta($variation_id, '_swatch_color', true)
                ?: get_post_meta($variation_id, '_wcvs_swatch_color', true);

            if (!empty($v_hex)) {
                return $v_hex;
            }

            // Check termmeta for variation taxonomy terms (e.g. pa_color)
            if (class_exists('Tvak_Shade_Sync') && function_exists('wc_get_product')) {
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
                                $term_hex = Tvak_Shade_Sync::get_term_swatch_color($term_obj->term_id, $tax_name);
                                if (!empty($term_hex)) {
                                    return $term_hex;
                                }
                            }
                        }
                    }

                    $parent_id  = $product_id ?: (int) $wc_var->get_parent_id();
                    $parent_hex = Tvak_Shade_Sync::get_parent_product_swatch_color($parent_id, $shade_name, array_filter(array_values($v_attrs)));
                    if (!empty($parent_hex)) {
                        return $parent_hex;
                    }
                }
            }
        }

        // 3. Fallback default hex from Tvak_Shade_Sync or emergency fallback
        if (class_exists('Tvak_Shade_Sync')) {
            return Tvak_Shade_Sync::get_default_hex();
        }

        return '#D4AF37';
    }

    /**
     * SVG Luxury Product Placeholder Image (Fallback when no product image uploaded)
     *
     * @return string Data URI SVG image.
     */
    public static function get_fallback_image_url(): string {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">'
             . '<rect width="100%" height="100%" fill="#16161A"/>'
             . '<circle cx="100" cy="100" r="70" stroke="#D4AF37" stroke-width="1.5" fill="none" opacity="0.4"/>'
             . '<path d="M100 45 L115 80 L155 85 L125 112 L132 152 L100 133 L68 152 L75 112 L45 85 L85 80 Z" fill="none" stroke="#D4AF37" stroke-width="2"/>'
             . '<text x="100" y="175" fill="#D4AF37" font-size="12" font-family="sans-serif" text-anchor="middle" letter-spacing="2">TVAK LUXURY</text>'
             . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Resolve target variation SKU/ID with stock verification, price, image, and available shades.
     *
     * @param int               $product_id WooCommerce Product ID.
     * @param Tvak_User_Profile $profile    Customer profile.
     * @param string            $slot_code  Slot category identifier (e.g. bb_cream, lipstick_accent).
     * @return array Array containing resolved variation_id, is_in_stock, shade_name, price, price_formatted, image_url, and all_shades.
     */
    public static function resolve(int $product_id, Tvak_User_Profile $profile, string $slot_code = ''): array {
        $variation_id = Tvak_Variant_Map::resolve_variation($product_id, $profile->to_array());

        if (!$variation_id) {
            $variation_id = $product_id; // Default to main product ID if no variation mapped
        }

        $is_in_stock     = true;
        $shade_name      = '';
        $price           = 49.00; // Default fallback price
        $price_formatted = '$49.00';
        $image_url       = self::get_fallback_image_url();
        $all_shades      = [];

        if (function_exists('wc_get_product')) {
            $wc_product = wc_get_product($variation_id);
            if ($wc_product) {
                $is_in_stock = $wc_product->is_in_stock();
                $raw_price   = $wc_product->get_price();
                if ($raw_price !== '' && $raw_price !== false) {
                    $price           = (float) $raw_price;
                    $price_formatted = wc_price($price);
                }

                if ($wc_product->is_type('variation')) {
                    $attributes = $wc_product->get_variation_attributes();
                    $shade_name = implode(' / ', array_filter($attributes));
                } else {
                    $shade_name = $wc_product->get_name();
                }

                // Image resolution
                $img_id = $wc_product->get_image_id();
                if (!$img_id && $wc_product->is_type('variation')) {
                    $parent_obj = wc_get_product($wc_product->get_parent_id());
                    if ($parent_obj) {
                        $img_id = $parent_obj->get_image_id();
                    }
                }
                if ($img_id) {
                    $src = wp_get_attachment_image_url($img_id, 'medium');
                    if ($src) {
                        $image_url = $src;
                    }
                }
            }
        }

        // Stock fallback logic: if resolved shade is out of stock, attempt fallback to the
        // best tone-proximity-matching in-stock variation, not just the first available.
        if (!$is_in_stock && function_exists('wc_get_product')) {
            $parent_obj = wc_get_product($product_id);
            if ($parent_obj && $parent_obj->is_type('variable')) {
                $children           = $parent_obj->get_children();
                $profile_array      = $profile->to_array();
                $best_fallback_id   = null;
                $best_match_score   = -1;

                foreach ($children as $child_id) {
                    $child_obj = wc_get_product($child_id);
                    if (!$child_obj || !$child_obj->is_in_stock()) {
                        continue;
                    }

                    // Score this in-stock child against the customer profile.
                    // Tvak_Variant_Map::resolve_variation() returns this child's ID
                    // if it matches the profile (exact or partial).  We use the
                    // partial-match counting logic: count how many profile attributes
                    // align with the child's stored criteria_vector.
                    global $wpdb;
                    $map_table = $wpdb->prefix . 'tvak_variant_mapping_matrix';
                    $criteria_json = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT criteria_vector FROM {$map_table} WHERE product_id = %d AND variation_id = %d LIMIT 1",
                            $product_id, $child_id
                        )
                    );

                    $child_match_score = 0;
                    if ($criteria_json) {
                        $criteria_vector = json_decode($criteria_json, true) ?: [];
                        foreach ($criteria_vector as $k => $v) {
                            if (isset($profile_array[$k]) && $profile_array[$k] === $v) {
                                $child_match_score++;
                            }
                        }
                    }

                    // Prefer the child with the highest attribute-match count.
                    // On tie, prefer the child with the lower ID (insertion-order stability).
                    if ($child_match_score > $best_match_score ||
                        ($child_match_score === $best_match_score && $best_fallback_id === null)) {
                        $best_match_score = $child_match_score;
                        $best_fallback_id = $child_id;
                    }
                }

                if ($best_fallback_id !== null) {
                    $fb_obj = wc_get_product($best_fallback_id);
                    if ($fb_obj) {
                        $variation_id = $best_fallback_id;
                        $is_in_stock  = true;
                        $shade_name   = implode(' / ', array_filter($fb_obj->get_variation_attributes()));
                        $raw_price    = $fb_obj->get_price();
                        if ($raw_price !== '' && $raw_price !== false) {
                            $price           = (float) $raw_price;
                            $price_formatted = wc_price($price);
                        }
                    }
                }
                // If no in-stock fallback found, leave $is_in_stock = false so the
                // UI can display an "Out of Stock / Notify me" indicator.
            }
        }

        // 1. Custom DB shades configured via Product Shades Manager (Highest priority: custom admin shades)
        if (class_exists('Tvak_Product_Shade')) {
            $db_shades  = Tvak_Product_Shade::get_shades_by_product($product_id);
            $has_shades = Tvak_Product_Shade::get_product_has_shades($product_id) || !empty($db_shades);

            if (!empty($db_shades)) {
                // Filter out self-referential dummy rows where shade_name matches parent product title
                $parent_title_lower = function_exists('wc_get_product') && wc_get_product($product_id) ? strtolower(trim(wc_get_product($product_id)->get_name())) : '';
                
                foreach ($db_shades as $idx => $db_s) {
                    $s_name_lower = strtolower(trim($db_s['shade_name']));
                    if (count($db_shades) > 1 && !empty($parent_title_lower) && $s_name_lower === $parent_title_lower) {
                        continue; // Skip single parent-title dummy fallback when true shades exist
                    }

                    $s_var_id = !empty($db_s['variation_id']) ? (int) $db_s['variation_id'] : ($product_id * 100 + $idx + 1);
                    $s_price  = !empty($db_s['price']) ? (float) $db_s['price'] : $price;
                    $s_price_fmt = function_exists('wc_price') ? wc_price($s_price) : ('$' . number_format($s_price, 2));

                    $all_shades[] = [
                        'variation_id'    => $s_var_id,
                        'shade_name'      => $db_s['shade_name'],
                        'hex_color'       => !empty($db_s['shade_hex']) ? $db_s['shade_hex'] : self::get_shade_hex($db_s['shade_name'], $product_id, $s_var_id),
                        'price'           => $s_price,
                        'price_formatted' => $s_price_fmt,
                        'image_url'       => !empty($db_s['image_url']) ? $db_s['image_url'] : $image_url,
                        'is_in_stock'     => (bool) ($db_s['is_in_stock'] ?? 1),
                        'is_selected'     => ($idx === 0),
                    ];
                }
            }
        }

        // 2. WooCommerce Variable Product Auto-Discovery Pipeline (if no custom DB shades populated)
        if (empty($all_shades) && function_exists('wc_get_product')) {
            $parent_obj = wc_get_product($product_id);
            if ($parent_obj && $parent_obj->is_type('variable')) {
                $has_shades = true;
                $children   = $parent_obj->get_children();
                foreach ($children as $idx => $child_id) {
                    $child_obj = wc_get_product($child_id);
                    if ($child_obj) {
                        $s_attributes = $child_obj->get_variation_attributes();
                        $s_name       = implode(' / ', array_filter($s_attributes));

                        // Only add variation if it has explicit attribute names
                        if (!empty($s_name)) {
                            $s_stock      = $child_obj->is_in_stock();
                            $s_price_raw  = $child_obj->get_price();
                            $s_price      = ($s_price_raw !== '' && $s_price_raw !== false) ? (float) $s_price_raw : $price;
                            $s_price_fmt  = function_exists('wc_price') ? wc_price($s_price) : ('$' . number_format($s_price, 2));

                            $s_img_id = $child_obj->get_image_id() ?: $parent_obj->get_image_id();
                            $s_img    = $s_img_id ? wp_get_attachment_image_url($s_img_id, 'medium') : $image_url;
                            $s_hex    = self::get_shade_hex($s_name, $product_id, (int) $child_id);

                            $all_shades[] = [
                                'variation_id'    => (int) $child_id,
                                'shade_name'      => $s_name,
                                'hex_color'       => $s_hex,
                                'price'           => $s_price,
                                'price_formatted' => $s_price_fmt,
                                'image_url'       => $s_img ?: $image_url,
                                'is_in_stock'     => (bool) $s_stock,
                                'is_selected'     => ($child_id == $variation_id || $idx === 0),
                            ];
                        }
                    }
                }
            }
        }

        // 3. Fallback: If no WooCommerce variations or DB shades are configured, disable shade swatches
        if (empty($all_shades)) {
            $has_shades = false;
        }

        // Strict Array Deduplication by Normalized Shade Name
        $unique_shades  = [];
        $seen_names     = [];
        foreach ($all_shades as $sh_item) {
            $norm_key = strtolower(trim($sh_item['shade_name']));
            if (!in_array($norm_key, $seen_names, true)) {
                $seen_names[]    = $norm_key;
                $unique_shades[] = $sh_item;
            }
        }
        $all_shades = $unique_shades;

        // If shades exist: honour customer's shade preference before defaulting to position 0.
        // This resolves Issue #5: the preference passed in the REST payload is now applied.
        $preferred_var_id = $profile->get_preferred_shade($product_id);
        $primary_shade_idx = 0;

        if ($preferred_var_id && !empty($all_shades)) {
            foreach ($all_shades as $sh_idx => $sh_item) {
                if ((int) $sh_item['variation_id'] === $preferred_var_id) {
                    // Reset is_selected flags so exactly one shade is selected
                    foreach ($all_shades as &$sh_ref) {
                        $sh_ref['is_selected'] = false;
                    }
                    unset($sh_ref);
                    $all_shades[$sh_idx]['is_selected'] = true;
                    $primary_shade_idx                  = $sh_idx;
                    break;
                }
            }
        }

        // Select primary shade (preferred or first)
        if (!empty($all_shades[$primary_shade_idx])) {
            $shade_name      = $all_shades[$primary_shade_idx]['shade_name'];
            $variation_id    = $all_shades[$primary_shade_idx]['variation_id'];
            $price           = $all_shades[$primary_shade_idx]['price'];
            $price_formatted = $all_shades[$primary_shade_idx]['price_formatted'];
            $image_url       = $all_shades[$primary_shade_idx]['image_url'];
            $is_in_stock     = $all_shades[$primary_shade_idx]['is_in_stock'];
        }

        // Determine resolved primary shade hex color
        $shade_hex = !empty($all_shades[$primary_shade_idx]['hex_color'])
            ? $all_shades[$primary_shade_idx]['hex_color']
            : self::get_shade_hex($shade_name);

        return [
            'variation_id'    => (int) $variation_id,
            'is_in_stock'     => (bool) $is_in_stock,
            'has_shades'      => (bool) $has_shades,
            'shade_name'      => (string) $shade_name,
            'shade_hex'       => (string) $shade_hex,
            'price'           => (float) $price,
            'price_formatted' => (string) $price_formatted,
            'image_url'       => (string) $image_url,
            'all_shades'      => (array) $all_shades,
        ];
    }
}
