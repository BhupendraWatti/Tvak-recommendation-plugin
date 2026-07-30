<?php
/**
 * Variant Mapping Model Class
 *
 * Handles direct hash mapping of customer profile attribute vectors to WooCommerce Variation IDs.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Variant_Map {

    /**
     * Get variant map table.
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'tvak_variant_mapping_matrix';
    }

    /**
     * Compute deterministic hash for an attribute vector combination for a given product.
     *
     * @param int   $product_id Product ID.
     * @param array $criteria   Key-value pairs (e.g. ['skin_tone' => 'fair_light', 'undertone' => 'cool']).
     * @return string MD5 hash string.
     */
    public static function compute_hash($product_id, $criteria) {
        ksort($criteria);
        return md5((int) $product_id . '_' . wp_json_encode($criteria));
    }

    /**
     * Resolve target variation ID for a given product and user profile vector.
     *
     * @param int   $product_id Product ID.
     * @param array $criteria   Target attributes criteria.
     * @return int|null Variation ID or null if unmapped.
     */
    public static function resolve_variation($product_id, $criteria) {
        global $wpdb;
        $table = self::get_table_name();

        $hash = self::compute_hash($product_id, $criteria);

        $variation_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT variation_id FROM {$table} WHERE product_id = %d AND attribute_hash = %s ORDER BY priority DESC LIMIT 1",
                $product_id,
                $hash
            )
        );

        if ($variation_id) {
            return (int) $variation_id;
        }

        // Fallback: search for partial match if exact hash match fails
        $mappings = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE product_id = %d ORDER BY priority DESC", $product_id),
            ARRAY_A
        );

        if (!$mappings) {
            return null;
        }

        $best_match_id = null;
        $max_match_score = -1;

        foreach ($mappings as $map) {
            $mapped_vector = !empty($map['criteria_vector']) ? json_decode($map['criteria_vector'], true) : [];
            if (empty($mapped_vector)) {
                continue;
            }

            $matches = 0;
            foreach ($mapped_vector as $k => $v) {
                if (isset($criteria[$k]) && $criteria[$k] === $v) {
                    $matches++;
                }
            }

            if ($matches > $max_match_score) {
                $max_match_score = $matches;
                $best_match_id   = (int) $map['variation_id'];
            }
        }

        return $best_match_id;
    }

    /**
     * Get all variant mappings for a product.
     *
     * @param int $product_id Product ID.
     * @return array Mappings list.
     */
    public static function get_mappings_for_product($product_id) {
        global $wpdb;
        $table = self::get_table_name();

        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE product_id = %d ORDER BY priority DESC, map_id ASC", $product_id),
            ARRAY_A
        );

        if (!$results) {
            return [];
        }

        foreach ($results as &$row) {
            $row['criteria'] = !empty($row['criteria_vector']) ? json_decode($row['criteria_vector'], true) : [];
        }

        return $results;
    }

    /**
     * Save a variant mapping entry.
     *
     * @param int   $product_id   Product ID.
     * @param int   $variation_id Variation ID.
     * @param array $criteria     Target criteria vector.
     * @param int   $priority     Priority rank.
     * @return int Mapping ID.
     */
    public static function save_mapping($product_id, $variation_id, $criteria, $priority = 0) {
        global $wpdb;
        $table = self::get_table_name();

        $hash = self::compute_hash($product_id, $criteria);
        $criteria_vector = wp_json_encode($criteria);

        $existing_id = $wpdb->get_var(
            $wpdb->prepare("SELECT map_id FROM {$table} WHERE product_id = %d AND attribute_hash = %s", $product_id, $hash)
        );

        if ($existing_id) {
            $wpdb->update(
                $table,
                [
                    'variation_id'    => (int) $variation_id,
                    'criteria_vector' => $criteria_vector,
                    'priority'        => (int) $priority,
                ],
                ['map_id' => $existing_id],
                ['%d', '%s', '%d'],
                ['%d']
            );
            return $existing_id;
        }

        $wpdb->insert(
            $table,
            [
                'product_id'      => (int) $product_id,
                'variation_id'    => (int) $variation_id,
                'attribute_hash'  => $hash,
                'criteria_vector' => $criteria_vector,
                'priority'        => (int) $priority,
            ],
            ['%d', '%d', '%s', '%s', '%d']
        );

        return $wpdb->insert_id;
    }

    /**
     * Delete a mapping.
     *
     * @param int $map_id Mapping ID.
     * @return bool
     */
    public static function delete_mapping($map_id) {
        global $wpdb;
        $table = self::get_table_name();
        return (bool) $wpdb->delete($table, ['map_id' => (int) $map_id], ['%d']);
    }
}
