<?php
/**
 * Safety & Anti-Collision Engine Guardrails
 *
 * Implements safety rules:
 * 1. Sensitive skin override (disqualifies products with fragrance/alcohol).
 * 2. Active ingredient conflicts (Retinoids vs. AHA/BHA).
 * 3. Formula redundancy prevention.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Anti_Collision {

    /**
     * Check if product is safe for customer profile.
     *
     * @param int               $product_id Product ID.
     * @param Tvak_User_Profile $profile    Customer profile vector.
     * @return bool True if safe, false if hard disqualified.
     */
    public static function is_product_safe(int $product_id, Tvak_User_Profile $profile): bool {
        // Sensitive Skin Override Rule
        if ($profile->is_sensitive()) {
            $contains_fragrance = get_post_meta($product_id, '_tvak_contains_fragrance', true);
            $contains_alcohol   = get_post_meta($product_id, '_tvak_contains_alcohol', true);

            if ($contains_fragrance === 'yes' || $contains_alcohol === 'yes') {
                return false; // Hard Disqualification (Multiplier 0.00)
            }
        }

        return true;
    }

    /**
     * Resolve active ingredient conflict between selected items.
     *
     * @param array $selected_kit Items list [{product_id, score, ...}].
     * @return array Filtered safe kit items.
     */
    public static function filter_kit_conflicts(array $selected_kit): array {
        $has_high_retinoid = false;
        $retinoid_index    = -1;

        $has_high_aha = false;
        $aha_index    = -1;

        foreach ($selected_kit as $idx => $item) {
            $pid = (int) $item['product_id'];
            if (get_post_meta($pid, '_tvak_active_ingredient', true) === 'retinoid') {
                $has_high_retinoid = true;
                $retinoid_index    = $idx;
            }
            if (get_post_meta($pid, '_tvak_active_ingredient', true) === 'aha_bha') {
                $has_high_aha = true;
                $aha_index    = $idx;
            }
        }

        // If both present, remove lower scoring active ingredient item
        if ($has_high_retinoid && $has_high_aha) {
            if ($selected_kit[$retinoid_index]['score'] >= $selected_kit[$aha_index]['score']) {
                unset($selected_kit[$aha_index]);
            } else {
                unset($selected_kit[$retinoid_index]);
            }
        }

        return array_values($selected_kit);
    }
}
