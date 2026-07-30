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

                // Exclude items below minimum threshold (0.20)
                if ($score < 0.20) {
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

            // Sort candidates by score descending
            usort($candidates, function ($a, $b) {
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
                'shade_name'      => $variant_info['shade_name'],
                'shade_hex'       => $variant_info['shade_hex'] ?? '#D4AF37',
                'is_in_stock'     => $variant_info['is_in_stock'],
                'price'           => $variant_info['price'] ?? 49.00,
                'price_formatted' => $variant_info['price_formatted'] ?? '$49.00',
                'image_url'       => $variant_info['image_url'],
                'all_shades'      => $variant_info['all_shades'] ?? [],
                'score'           => $top_candidate['score'],
                'score_pct'       => round($top_candidate['score'] * 100, 1),
                'slot_code'       => $slot_meta['slot_code'],
                'slot_name'       => $slot_meta['slot_name'],
                'title'           => get_the_title($pid) ?: ('TVAK Bespoke ' . $slot_meta['slot_name']),
                'rationale'       => $this->evaluator->get_rationale($profile, $top_candidate['rule'], $top_candidate['score']),
            ];
        }

        // Apply active ingredient conflict filter across kit
        $final_items = Tvak_Anti_Collision::filter_kit_conflicts($raw_kit_items);

        // Compute Backend Kit Tiered Bundle Discount
        $item_count = count($final_items);
        $discount_pct = 0;
        if ($item_count >= 5) {
            $discount_pct = 20; // 20% off for 5+ items
        } elseif ($item_count >= 3) {
            $discount_pct = 15; // 15% off for 3-4 items
        } elseif ($item_count >= 2) {
            $discount_pct = 10; // 10% off for 2 items
        }

        return [
            'success'            => true,
            'kit_id'             => 'KIT-' . date('Ymd') . '-' . strtoupper(substr(md5(wp_json_encode($profile->to_array())), 0, 6)),
            'profile'            => $profile->to_array(),
            'total'              => $item_count,
            'discount_thresholds'=> [
                '2_items' => 10,
                '3_items' => 15,
                '5_items' => 20,
            ],
            'items'              => $final_items,
        ];
    }
}
