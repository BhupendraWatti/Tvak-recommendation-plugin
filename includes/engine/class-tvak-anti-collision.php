<?php
/**
 * Safety & Anti-Collision Engine Guardrails
 *
 * Implements safety rules:
 * 1. Sensitive skin override (disqualifies products with fragrance/alcohol).
 * 2. Active ingredient conflicts (Retinoids vs. AHA/BHA and admin-configurable pairs).
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

            // Hard disqualify if explicitly flagged as containing irritants
            if ($contains_fragrance === 'yes' || $contains_alcohol === 'yes') {
                return false;
            }

            // Safety gap detection: if neither flag has been configured for this product,
            // log a debug notice so admins know the safety gate is incomplete for this item.
            // The product still PASSES here (open default) to avoid breaking unconfigured
            // installations. Admins should set _tvak_contains_fragrance = 'no' explicitly
            // to clear this warning.
            if ($contains_fragrance === '' && $contains_alcohol === '') {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log(sprintf(
                        '[TVAK Safety] Product #%d has no safety flags configured (_tvak_contains_fragrance / _tvak_contains_alcohol). Configure via TVAK → Product Rules to satisfy sensitive skin guardrails.',
                        $product_id
                    ));
                }
            }
        }

        return true;
    }

    /**
     * Resolve active ingredient conflict between selected items.
     *
     * Conflict pairs are admin-configurable via the WP option 'tvak_ingredient_conflict_rules'.
     * Each pair is an array [ingredient_a, ingredient_b]. When both ingredients are present
     * in the kit, the lower-scoring product is removed.
     *
     * Default pair (always applied): ['retinoid', 'aha_bha']
     *
     * @param array $selected_kit Items list [{product_id, score, ...}].
     * @return array Filtered safe kit items.
     */
    public static function filter_kit_conflicts(array $selected_kit): array {
        if (empty($selected_kit)) {
            return $selected_kit;
        }

        // Load admin-configurable conflict pairs; default to the base Retinoid ↔ AHA/BHA pair
        $conflict_pairs = get_option('tvak_ingredient_conflict_rules', [
            ['retinoid', 'aha_bha'],
        ]);

        // Ensure the default pair is always present regardless of option corruption
        $has_default = false;
        foreach ($conflict_pairs as $pair) {
            if (in_array('retinoid', $pair, true) && in_array('aha_bha', $pair, true)) {
                $has_default = true;
                break;
            }
        }
        if (!$has_default) {
            $conflict_pairs[] = ['retinoid', 'aha_bha'];
        }

        // Build a quick product_id → {index, score, ingredient} map
        $ingredient_map = [];
        foreach ($selected_kit as $idx => $item) {
            $pid        = (int) $item['product_id'];
            $ingredient = get_post_meta($pid, '_tvak_active_ingredient', true);
            if (!empty($ingredient)) {
                $ingredient_map[$idx] = [
                    'ingredient' => $ingredient,
                    'score'      => (float) ($item['score'] ?? 0.0),
                    'product_id' => $pid,
                ];
            }
        }

        $items_to_remove = [];

        foreach ($conflict_pairs as $pair) {
            if (count($pair) < 2) {
                continue;
            }

            $ing_a = $pair[0];
            $ing_b = $pair[1];

            $match_a = null;
            $match_b = null;

            foreach ($ingredient_map as $idx => $info) {
                if ($info['ingredient'] === $ing_a && $match_a === null) {
                    $match_a = ['idx' => $idx, 'score' => $info['score']];
                }
                if ($info['ingredient'] === $ing_b && $match_b === null) {
                    $match_b = ['idx' => $idx, 'score' => $info['score']];
                }
            }

            // Both conflicting ingredients present — remove the lower-scoring one
            if ($match_a !== null && $match_b !== null) {
                $remove_idx = ($match_a['score'] >= $match_b['score']) ? $match_b['idx'] : $match_a['idx'];
                $items_to_remove[$remove_idx] = true;
            }
        }

        foreach (array_keys($items_to_remove) as $remove_idx) {
            unset($selected_kit[$remove_idx]);
        }

        return array_values($selected_kit);
    }
}

