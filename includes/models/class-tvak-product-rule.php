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
        $cache_key = 'active_rules_grouped';
        $cached    = class_exists('Tvak_Cache') ? Tvak_Cache::get($cache_key) : false;
        if ($cached !== false && $cached !== null) {
            return $cached;
        }

        // Auto-discover any newly added WooCommerce products that lack a rule
        self::auto_reconcile_unmapped_products();

        global $wpdb;
        $rule_table = self::get_rule_table();
        $attr_table = self::get_attr_table();

        // Single JOIN query — eliminates N+1 pattern
        $rows = $wpdb->get_results(
            "SELECT r.*, a.attribute_code, a.weight, a.match_matrix
             FROM {$rule_table} r
             LEFT JOIN {$attr_table} a ON a.rule_id = r.rule_id
             WHERE r.is_active = 1
             ORDER BY r.slot_id ASC, r.rule_id ASC",
            ARRAY_A
        );

        if (!$rows) {
            return [];
        }

        $rules_map = [];
        $grouped   = [];

        foreach ($rows as $row) {
            $rule_id = (int) $row['rule_id'];
            $slot_id = (int) $row['slot_id'];

            if (!isset($rules_map[$rule_id])) {
                $rules_map[$rule_id] = [
                    'rule_id'               => $rule_id,
                    'product_id'            => (int) $row['product_id'],
                    'slot_id'               => $slot_id,
                    'priority_boost'        => (float) $row['priority_boost'],
                    'min_score_threshold'   => isset($row['min_score_threshold']) && $row['min_score_threshold'] !== null ? (float) $row['min_score_threshold'] : null,
                    'is_active'             => (int) $row['is_active'],
                    'attribute_rules'       => [],
                ];
            }

            if (!empty($row['attribute_code'])) {
                $rules_map[$rule_id]['attribute_rules'][$row['attribute_code']] = [
                    'weight'       => (float) $row['weight'],
                    'match_matrix' => !empty($row['match_matrix']) ? json_decode($row['match_matrix'], true) : [],
                ];
            }
        }

        foreach ($rules_map as $rule_id => $rule) {
            $slot_id = $rule['slot_id'];
            if (!isset($grouped[$slot_id])) {
                $grouped[$slot_id] = [];
            }
            $grouped[$slot_id][] = $rule;
        }

        if (class_exists('Tvak_Cache')) {
            Tvak_Cache::set($cache_key, $grouped, 300);
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
    public static function save_rule($product_id, $slot_id, $priority_boost, $is_active, $attribute_rules, $min_score_threshold = null) {
        global $wpdb;
        $rule_table = self::get_rule_table();
        $attr_table = self::get_attr_table();

        $existing_rule_id = $wpdb->get_var(
            $wpdb->prepare("SELECT rule_id FROM {$rule_table} WHERE product_id = %d", $product_id)
        );

        // Resolve min_score_threshold — null means use global 0.20 default; any numeric value is product-specific
        $resolved_threshold = ($min_score_threshold !== null && $min_score_threshold !== '') ? (float) $min_score_threshold : null;

        if ($existing_rule_id) {
            if ($resolved_threshold !== null) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$rule_table} SET slot_id = %d, priority_boost = %f, is_active = %d, min_score_threshold = %f WHERE rule_id = %d",
                        (int) $slot_id, (float) $priority_boost, (int) $is_active, $resolved_threshold, (int) $existing_rule_id
                    )
                );
            } else {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$rule_table} SET slot_id = %d, priority_boost = %f, is_active = %d, min_score_threshold = NULL WHERE rule_id = %d",
                        (int) $slot_id, (float) $priority_boost, (int) $is_active, (int) $existing_rule_id
                    )
                );
            }
            $rule_id = $existing_rule_id;
        } else {
            $insert_data    = [
                'product_id'     => (int) $product_id,
                'slot_id'        => (int) $slot_id,
                'priority_boost' => (float) $priority_boost,
                'is_active'      => (int) $is_active,
            ];
            $insert_formats = ['%d', '%d', '%f', '%d'];

            if ($resolved_threshold !== null) {
                $insert_data['min_score_threshold'] = $resolved_threshold;
                $insert_formats[]                   = '%f';
            }

            $wpdb->insert($rule_table, $insert_data, $insert_formats);
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

    /**
     * Auto-reconcile unmapped WooCommerce products.
     * Finds published products in WooCommerce that lack a TVAK recommendation rule,
     * auto-detects their kit slot, and inserts an active rule.
     *
     * @return int Number of newly reconciled products.
     */
    public static function auto_reconcile_unmapped_products(): int {
        global $wpdb;
        $rule_table = self::get_rule_table();

        $unmapped_ids = $wpdb->get_col("
            SELECT p.ID FROM {$wpdb->prefix}posts p
            WHERE p.post_type = 'product'
              AND p.post_status = 'publish'
              AND p.ID NOT IN (SELECT product_id FROM {$rule_table})
        ");

        if (empty($unmapped_ids)) {
            return 0;
        }

        $reconciled = 0;
        foreach ($unmapped_ids as $pid) {
            $product_id = (int) $pid;
            $slot_id    = class_exists('Tvak_Shade_Sync') ? Tvak_Shade_Sync::detect_product_kit_slot($product_id) : 0;
            if (!$slot_id && class_exists('Tvak_DB')) {
                Tvak_DB::sync_wc_kit_slots();
                $slot_id = class_exists('Tvak_Shade_Sync') ? Tvak_Shade_Sync::detect_product_kit_slot($product_id) : 0;
            }
            if (!$slot_id) {
                continue;
            }
            
            self::save_rule($product_id, $slot_id, 1.00, 1, [], 0.00);

            if (class_exists('Tvak_Shade_Sync')) {
                Tvak_Shade_Sync::auto_sync_product_variations($product_id);
            }

            $reconciled++;
        }

        return $reconciled;
    }
}
