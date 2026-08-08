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
     * Fallback match score applied when a user attribute value is not present
     * in the product's match_matrix. Runtime scoring reads the admin option,
     * using this constant only as the default option fallback.
     */
    const DEFAULT_ABSENT_MATCH = 0.20;

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
        $absent_score     = self::get_default_absent_match();

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
                    // For multi-select attributes (e.g. skin_concern), compute the
                    // mean of individual concern scores so that customers selecting
                    // more concerns receive proportionally stronger match signals
                    // than single-concern customers. A matrix key set to 0.0
                    // explicitly means "contra-indicated"; absent key uses the
                    // neutral admin-configured absent-match baseline.
                    $scores = [];
                    foreach ($user_val as $val_item) {
                        $scores[] = isset($matrix[$val_item])
                            ? (float) $matrix[$val_item]
                            : $absent_score;
                    }
                    $match_score = array_sum($scores) / count($scores);
                }
                // empty array: user selected no concerns → match_score stays 0.0
                // (product still scores on other attributes like skin_type, skin_tone)
            } elseif (!is_null($user_val) && $user_val !== '') {
                // Single-value attribute: use explicit matrix value or neutral baseline
                $match_score = isset($matrix[$user_val])
                    ? (float) $matrix[$user_val]
                    : $absent_score;
            }
            // Null/empty user_val (user skipped the step) → match_score stays 0.0

            $weighted_sum += $w * $match_score;
            $total_weight += $w;
        }

        if ($total_weight <= 0.0) {
            return min(1.0, max(0.0, $boost));
        }

        $base_score  = $weighted_sum / $total_weight;
        $final_score = min(1.0, max(0.0, $base_score + $boost));

        return round($final_score, 4);
    }

    /**
     * Get the admin-configured match score for absent matrix keys.
     *
     * @return float
     */
    public static function get_default_absent_match(): float {
        return min(1.0, max(0.0, (float) get_option('tvak_default_absent_match', self::DEFAULT_ABSENT_MATCH)));
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

        $label_map = class_exists('Tvak_Master_Data') ? Tvak_Master_Data::get_terms_label_map() : [];
        $profile_values = $profile->to_array();
        $matched_labels = [];

        foreach (($rule_data['attribute_rules'] ?? []) as $attribute_code => $rule_info) {
            if (empty($profile_values[$attribute_code])) {
                continue;
            }

            $raw_value = $profile_values[$attribute_code];
            $values = is_array($raw_value) ? $raw_value : [$raw_value];
            foreach ($values as $value) {
                $value = sanitize_key($value);
                if ($value === '') {
                    continue;
                }
                $matched_labels[] = $label_map[$attribute_code][$value] ?? ucwords(str_replace(['_', '-'], ' ', $value));
            }
        }

        if (!empty($matched_labels)) {
            return sprintf(
                __('%s was selected based on your profile: %s.', 'tvak-beauty-kit'),
                $title,
                implode(', ', array_unique($matched_labels))
            );
        }

        return sprintf(
            __('%s was selected from your WooCommerce catalog.', 'tvak-beauty-kit'),
            $title
        );
    }
}
