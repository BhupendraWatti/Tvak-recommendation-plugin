<?php
/**
 * Evaluator Interface Contract
 *
 * Defines contract for independent product evaluators.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

interface Tvak_Evaluator_Interface {

    /**
     * Calculate fit score S(P, U) in range [0.0, 1.0].
     *
     * @param Tvak_User_Profile $profile   Customer profile.
     * @param array             $rule_data Product rule metadata.
     * @return float Calculated score.
     */
    public function evaluate(Tvak_User_Profile $profile, array $rule_data): float;

    /**
     * Generate human-readable rationale explanation for selection.
     *
     * @param Tvak_User_Profile $profile   Customer profile.
     * @param array             $rule_data Product rule metadata.
     * @param float             $score     Evaluated score.
     * @return string Rationale paragraph.
     */
    public function get_rationale(Tvak_User_Profile $profile, array $rule_data, float $score): string;
}
