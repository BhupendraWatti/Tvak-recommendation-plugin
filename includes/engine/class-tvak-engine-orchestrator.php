<?php
/**
 * Master Engine Orchestrator Pipeline
 *
 * Coordinates profile vector evaluation, independent product scoring, slotting,
 * variant resolution, and kit payload construction.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-tvak-user-profile.php';
require_once __DIR__ . '/evaluators/class-tvak-product-evaluator.php';
require_once __DIR__ . '/class-tvak-anti-collision.php';
require_once __DIR__ . '/class-tvak-variant-resolver.php';

class Tvak_Engine_Orchestrator {

    /**
     * Evaluator instance.
     * @var Tvak_Evaluator_Interface
     */
    private $evaluator;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->evaluator = new Tvak_Product_Evaluator();
    }

    /**
     * Run recommendation pipeline for customer profile.
     *
     * @param Tvak_User_Profile $profile Customer profile vector.
     * @return array Formatted Kit Payload array.
     */
    public function recommend(Tvak_User_Profile $profile): array {
        $grouped_rules = Tvak_Product_Rule::get_all_active_grouped_by_slot();

        if (empty($grouped_rules)) {
            return [
                'success' => false,
                'message' => __('No active recommendation rules found.', 'tvak-beauty-kit'),
                'items'   => [],
            ];
        }

        global $wpdb;
        $slots_table = $wpdb->prefix . 'tvak_kit_slots';
        $slots_info  = $wpdb->get_results("SELECT * FROM {$slots_table} ORDER BY sort_order ASC", ARRAY_A);

        $slots_by_id = [];
        if ($slots_info) {
            foreach ($slots_info as $s) {
                $slots_by_id[(int) $s['slot_id']] = $s;
            }
        }

        $raw_kit_items = [];

        foreach ($grouped_rules as $slot_id => $rules) {
            $slot_meta   = $slots_by_id[$slot_id] ?? ['slot_code' => 'slot_' . $slot_id, 'slot_name' => 'Category Slot ' . $slot_id];
            $candidates  = [];

            foreach ($rules as $rule) {
                $product_id = (int) $rule['product_id'];

                // Safety guardrail check
                if (!Tvak_Anti_Collision::is_product_safe($product_id, $profile)) {
                    continue;
                }

                $score = $this->evaluator->evaluate($profile, $rule);

                // Exclude items below per-rule minimum threshold (falls back to global 0.20)
                $min_threshold = isset($rule['min_score_threshold']) && $rule['min_score_threshold'] !== null
                    ? (float) $rule['min_score_threshold']
                    : 0.20;
                if ($score < $min_threshold) {
                    continue;
                }

                $candidates[] = [
                    'product_id' => $product_id,
                    'rule'       => $rule,
                    'score'      => $score,
                    'slot_meta'  => $slot_meta,
                ];
            }

            if (empty($candidates)) {
                continue;
            }

            // Sort candidates by score descending; use product_id as deterministic tiebreaker
            usort($candidates, function ($a, $b) {
                if ($b['score'] === $a['score']) {
                    return $a['product_id'] <=> $b['product_id'];
                }
                return $b['score'] <=> $a['score'];
            });

            // Select top product per slot
            $top_candidate = $candidates[0];
            $pid           = $top_candidate['product_id'];

            // Resolve Variation & Stock Status with slot category hint
            $variant_info  = Tvak_Variant_Resolver::resolve($pid, $profile, $slot_meta['slot_code']);

            $raw_kit_items[] = [
                'product_id'      => $pid,
                'variation_id'    => $variant_info['variation_id'],
                'has_shades'      => (bool) ($variant_info['has_shades'] ?? false),
                'shade_name'      => $variant_info['shade_name'],
                'shade_hex'       => $variant_info['shade_hex'] ?? '',
                'is_in_stock'     => $variant_info['is_in_stock'],
                'price'           => $variant_info['price'] ?? 0.0,
                'price_formatted' => $variant_info['price_formatted'] ?? (function_exists('wc_price') ? wc_price(0) : '0.00'),
                'image_url'       => $variant_info['image_url'],
                'all_shades'      => $variant_info['all_shades'] ?? [],
                'score'           => $top_candidate['score'],
                'score_pct'       => round($top_candidate['score'] * 100, 1),
                'slot_code'       => $slot_meta['slot_code'],
                'slot_name'       => $slot_meta['slot_name'],
                'title'           => get_the_title($pid) ?: $slot_meta['slot_name'],
                'rationale'       => $this->evaluator->get_rationale($profile, $top_candidate['rule'], $top_candidate['score']),
            ];
        }

        // Apply active ingredient conflict filter across kit
        $final_items = Tvak_Anti_Collision::filter_kit_conflicts($raw_kit_items);

        // Compute Backend Kit Tiered Bundle Discount (admin-configurable via TVAK Engine → Bundle Discount)
        $discount_options = get_option('tvak_bundle_discounts', [
            'tier_1_min' => 2, 'tier_1_pct' => 10,
            'tier_2_min' => 3, 'tier_2_pct' => 15,
            'tier_3_min' => 5, 'tier_3_pct' => 20,
        ]);

        $t1_min = (int) ($discount_options['tier_1_min'] ?? 2);
        $t1_pct = (int) ($discount_options['tier_1_pct'] ?? 10);
        $t2_min = (int) ($discount_options['tier_2_min'] ?? 3);
        $t2_pct = (int) ($discount_options['tier_2_pct'] ?? 15);
        $t3_min = (int) ($discount_options['tier_3_min'] ?? 5);
        $t3_pct = (int) ($discount_options['tier_3_pct'] ?? 20);

        $item_count = count($final_items);

        return [
            'success'             => true,
            'kit_id'              => 'KIT-' . date('Ymd') . '-' . strtoupper(substr(md5(wp_json_encode($profile->to_array())), 0, 6)),
            'profile'             => $profile->to_array(),
            'total'               => $item_count,
            'discount_thresholds' => [
                'tier_1' => ['min_items' => $t1_min, 'pct' => $t1_pct],
                'tier_2' => ['min_items' => $t2_min, 'pct' => $t2_pct],
                'tier_3' => ['min_items' => $t3_min, 'pct' => $t3_pct],
            ],
            'items'               => $final_items,
        ];
    }
}
