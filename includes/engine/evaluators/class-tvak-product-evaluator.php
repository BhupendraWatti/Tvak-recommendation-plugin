<?php
/**
 * Core Product Evaluator Implementation
 *
 * Implements weighted scoring math:
 * S(P, U) = min(1.0, (sum(Wa * Ma) / sum(Wa)) + B(P))
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/interface-tvak-evaluator.php';

class Tvak_Product_Evaluator implements Tvak_Evaluator_Interface {

    /**
     * Evaluate fit score S(P, U).
     *
     * @param Tvak_User_Profile $profile   Customer profile vector.
     * @param array             $rule_data Product rule metadata.
     * @return float
     */
    public function evaluate(Tvak_User_Profile $profile, array $rule_data): float {
        $boost            = (float) ($rule_data['priority_boost'] ?? 0.0);
        $attribute_rules  = $rule_data['attribute_rules'] ?? [];

        if (empty($attribute_rules)) {
            return min(1.0, max(0.0, $boost));
        }

        $weighted_sum = 0.0;
        $total_weight = 0.0;

        foreach ($attribute_rules as $code => $rule_info) {
            $w = (float) ($rule_info['weight'] ?? 1.0);
            if ($w <= 0.0) {
                continue;
            }

            $matrix    = $rule_info['match_matrix'] ?? [];
            $user_val  = $profile->get_attribute_value($code);
            $match_score = 0.0;

            if (is_array($user_val)) {
                if (!empty($user_val)) {
                    $scores = [];
                    foreach ($user_val as $val_item) {
                        $scores[] = isset($matrix[$val_item]) ? (float) $matrix[$val_item] : 0.0;
                    }
                    $match_score = !empty($scores) ? max($scores) : 0.0;
                }
            } elseif (!is_null($user_val) && $user_val !== '') {
                $match_score = isset($matrix[$user_val]) ? (float) $matrix[$user_val] : 0.0;
            }

            $weighted_sum += $w * $match_score;
            $total_weight += $w;
        }

        if ($total_weight <= 0.0) {
            return min(1.0, max(0.0, $boost));
        }

        $base_score = $weighted_sum / $total_weight;
        $final_score = min(1.0, max(0.0, $base_score + $boost));

        return round($final_score, 4);
    }

    /**
     * Generate human-readable rationale explanation for product recommendation.
     *
     * @param Tvak_User_Profile $profile   Customer profile.
     * @param array             $rule_data Rule metadata.
     * @param float             $score     Score.
     * @return string
     */
    public function get_rationale(Tvak_User_Profile $profile, array $rule_data, float $score): string {
        $product_id = (int) ($rule_data['product_id'] ?? 0);
        $title      = get_the_title($product_id);
        $type       = ucfirst($profile->get_skin_type());
        $concerns   = implode(', ', array_map('ucwords', str_replace('_', ' ', $profile->get_skin_concerns())));

        if (!empty($concerns)) {
            return sprintf(
                __('%s was specially selected for your %s skin profile to directly target %s with optimal formula balance.', 'tvak-beauty-kit'),
                $title,
                $type,
                $concerns
            );
        }

        return sprintf(
            __('%s is harmonized with your %s skin type and %s skin tone for a radiant, balanced finish.', 'tvak-beauty-kit'),
            $title,
            $type,
            ucwords(str_replace('_', ' ', $profile->get_skin_tone()))
        );
    }
}
