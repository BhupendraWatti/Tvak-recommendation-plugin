<?php
/**
 * Product Rule Model Class
 *
 * Handles CRUD and retrieval of independent product recommendation rules & attribute weight matrices.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Product_Rule {

    /**
     * Get table names.
     */
    private static function get_rule_table() {
        global $wpdb;
        return $wpdb->prefix . 'tvak_product_rules';
    }

    private static function get_attr_table() {
        global $wpdb;
        return $wpdb->prefix . 'tvak_product_rule_attributes';
    }

    /**
     * Get rule for a specific WooCommerce product ID.
     *
     * @param int $product_id Product ID.
     * @return array|null Rule data array with attributes matrix.
     */
    public static function get_by_product_id($product_id) {
        global $wpdb;
        $rule_table = self::get_rule_table();
        $attr_table = self::get_attr_table();

        $rule = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$rule_table} WHERE product_id = %d", $product_id),
            ARRAY_A
        );

        if (!$rule) {
            return null;
        }

        $attributes = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$attr_table} WHERE rule_id = %d", $rule['rule_id']),
            ARRAY_A
        );

        $rule['attribute_rules'] = [];
        if ($attributes) {
            foreach ($attributes as $attr) {
                $attr_code = $attr['attribute_code'];
                $rule['attribute_rules'][$attr_code] = [
                    'weight'       => (float) $attr['weight'],
                    'match_matrix' => !empty($attr['match_matrix']) ? json_decode($attr['match_matrix'], true) : [],
                ];
            }
        }

        return $rule;
    }

    /**
     * Get all active product recommendation rules grouped by Kit Slot ID.
     *
     * @return array Grouped active rules.
     */
    public static function get_all_active_grouped_by_slot() {
        global $wpdb;
        $rule_table = self::get_rule_table();
        $attr_table = self::get_attr_table();

        $rules = $wpdb->get_results(
            "SELECT * FROM {$rule_table} WHERE is_active = 1",
            ARRAY_A
        );

        if (!$rules) {
            return [];
        }

        $grouped = [];
        foreach ($rules as $rule) {
            $slot_id = (int) $rule['slot_id'];
            $rule_id = (int) $rule['rule_id'];

            $attributes = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$attr_table} WHERE rule_id = %d", $rule_id),
                ARRAY_A
            );

            $rule['attribute_rules'] = [];
            if ($attributes) {
                foreach ($attributes as $attr) {
                    $attr_code = $attr['attribute_code'];
                    $rule['attribute_rules'][$attr_code] = [
                        'weight'       => (float) $attr['weight'],
                        'match_matrix' => !empty($attr['match_matrix']) ? json_decode($attr['match_matrix'], true) : [],
                    ];
                }
            }

            if (!isset($grouped[$slot_id])) {
                $grouped[$slot_id] = [];
            }
            $grouped[$slot_id][] = $rule;
        }

        return $grouped;
    }

    /**
     * Save product recommendation rule and attribute weight matrices.
     *
     * @param int   $product_id      WooCommerce Product ID.
     * @param int   $slot_id         Target Kit Slot ID.
     * @param float $priority_boost Priority adjustment score.
     * @param int   $is_active       Active toggle (1 or 0).
     * @param array $attribute_rules Attribute rules array [code => ['weight' => float, 'match_matrix' => array]].
     * @return int Rule ID.
     */
    public static function save_rule($product_id, $slot_id, $priority_boost, $is_active, $attribute_rules) {
        global $wpdb;
        $rule_table = self::get_rule_table();
        $attr_table = self::get_attr_table();

        $existing_rule_id = $wpdb->get_var(
            $wpdb->prepare("SELECT rule_id FROM {$rule_table} WHERE product_id = %d", $product_id)
        );

        if ($existing_rule_id) {
            $wpdb->update(
                $rule_table,
                [
                    'slot_id'        => (int) $slot_id,
                    'priority_boost' => (float) $priority_boost,
                    'is_active'      => (int) $is_active,
                ],
                ['rule_id' => $existing_rule_id],
                ['%d', '%f', '%d'],
                ['%d']
            );
            $rule_id = $existing_rule_id;
        } else {
            $wpdb->insert(
                $rule_table,
                [
                    'product_id'     => (int) $product_id,
                    'slot_id'        => (int) $slot_id,
                    'priority_boost' => (float) $priority_boost,
                    'is_active'      => (int) $is_active,
                ],
                ['%d', '%d', '%f', '%d']
            );
            $rule_id = $wpdb->insert_id;
        }

        // Delete existing attribute rules and re-insert
        $wpdb->delete($attr_table, ['rule_id' => $rule_id], ['%d']);

        if (is_array($attribute_rules)) {
            foreach ($attribute_rules as $attr_code => $attr_data) {
                $weight       = isset($attr_data['weight']) ? (float) $attr_data['weight'] : 1.0;
                $match_matrix = isset($attr_data['match_matrix']) && is_array($attr_data['match_matrix']) ? wp_json_encode($attr_data['match_matrix']) : '{}';

                $wpdb->insert(
                    $attr_table,
                    [
                        'rule_id'        => $rule_id,
                        'attribute_code' => sanitize_key($attr_code),
                        'weight'         => $weight,
                        'match_matrix'   => $match_matrix,
                    ],
                    ['%d', '%s', '%f', '%s']
                );
            }
        }

        return $rule_id;
    }

    /**
     * Delete rule for a product.
     *
     * @param int $product_id Product ID.
     * @return bool
     */
    public static function delete_rule($product_id) {
        global $wpdb;
        $rule_table = self::get_rule_table();
        $attr_table = self::get_attr_table();

        $rule_id = $wpdb->get_var(
            $wpdb->prepare("SELECT rule_id FROM {$rule_table} WHERE product_id = %d", $product_id)
        );

        if ($rule_id) {
            $wpdb->delete($attr_table, ['rule_id' => $rule_id], ['%d']);
            $wpdb->delete($rule_table, ['rule_id' => $rule_id], ['%d']);
            return true;
        }

        return false;
    }
}
