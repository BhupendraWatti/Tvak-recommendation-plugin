<?php
/**
 * Master Data Model Class
 *
 * Manages database operations for master attributes and master terms in wp_tvak_master_attributes
 * and wp_tvak_master_terms, establishing a dynamic single source of truth across the plugin.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Master_Data {

    /**
     * Get Master Attributes table name.
     *
     * @return string
     */
    public static function get_attr_table() {
        global $wpdb;
        return $wpdb->prefix . 'tvak_master_attributes';
    }

    /**
     * Get Master Terms table name.
     *
     * @return string
     */
    public static function get_terms_table() {
        global $wpdb;
        return $wpdb->prefix . 'tvak_master_terms';
    }

    /**
     * Retrieve all master attributes with their terms.
     *
     * @param bool $active_only If true, only return active attributes and terms.
     * @return array
     */
    public static function get_attributes(bool $active_only = true): array {
        global $wpdb;
        $attr_table  = self::get_attr_table();
        $terms_table = self::get_terms_table();

        $where_attr  = $active_only ? "WHERE is_active = 1" : "";
        $where_terms = $active_only ? "WHERE is_active = 1" : "";

        $attributes = $wpdb->get_results(
            "SELECT * FROM {$attr_table} {$where_attr} ORDER BY sort_order ASC, attribute_id ASC",
            ARRAY_A
        );

        if (!$attributes) {
            return [];
        }

        $all_terms = $wpdb->get_results(
            "SELECT * FROM {$terms_table} {$where_terms} ORDER BY sort_order ASC, term_id ASC",
            ARRAY_A
        );

        $terms_by_attr = [];
        if (!empty($all_terms)) {
            foreach ($all_terms as $t) {
                $code = $t['attribute_code'];
                if (!isset($terms_by_attr[$code])) {
                    $terms_by_attr[$code] = [];
                }
                $terms_by_attr[$code][] = $t;
            }
        }

        foreach ($attributes as &$attr) {
            $code = $attr['attribute_code'];
            $attr['terms'] = $terms_by_attr[$code] ?? [];
            
            // Generate legacy options format array for backward compatibility
            $options = [];
            foreach ($attr['terms'] as $t) {
                $options[$t['term_slug']] = $t['label'];
            }
            $attr['options'] = $options;
        }

        return $attributes;
    }

    /**
     * Retrieve only shopper-facing quiz attributes.
     *
     * @param bool $active_only If true, only return active attributes and terms.
     * @return array
     */
    public static function get_quiz_attributes(bool $active_only = true): array {
        global $wpdb;
        $attr_table = self::get_attr_table();

        $where = $active_only ? "WHERE is_active = 1 AND is_quiz_question = 1" : "WHERE is_quiz_question = 1";
        $attributes = $wpdb->get_results(
            "SELECT attribute_code FROM {$attr_table} {$where} ORDER BY sort_order ASC, attribute_id ASC",
            ARRAY_A
        );

        if (empty($attributes)) {
            return [];
        }

        $quiz_attributes = [];
        foreach ($attributes as $attr) {
            $full_attr = self::get_attribute_by_code($attr['attribute_code'], $active_only);
            if ($full_attr) {
                $quiz_attributes[] = $full_attr;
            }
        }

        return $quiz_attributes;
    }

    /**
     * Retrieve master attribute by code with terms.
     *
     * @param string $code Attribute machine key.
     * @param bool   $active_only Filter active terms only.
     * @return array|null
     */
    public static function get_attribute_by_code(string $code, bool $active_only = true) {
        global $wpdb;
        $attr_table  = self::get_attr_table();
        $terms_table = self::get_terms_table();

        $attr = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$attr_table} WHERE attribute_code = %s", $code),
            ARRAY_A
        );

        if (!$attr) {
            return null;
        }

        $where_terms = $active_only ? "AND is_active = 1" : "";
        $terms = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$terms_table} WHERE attribute_code = %s {$where_terms} ORDER BY sort_order ASC", $code),
            ARRAY_A
        );

        $attr['terms'] = $terms ?: [];
        $options = [];
        foreach ($attr['terms'] as $t) {
            $options[$t['term_slug']] = $t['label'];
        }
        $attr['options'] = $options;

        return $attr;
    }

    /**
     * Save or update a Master Attribute.
     *
     * @param array $data Attribute dataset.
     * @return int Attribute ID.
     */
    public static function save_attribute(array $data): int {
        global $wpdb;
        $table = self::get_attr_table();

        $code        = sanitize_key($data['attribute_code']);
        $label       = sanitize_text_field($data['label']);
        $category    = sanitize_text_field($data['category'] ?? 'dermatological');
        $description = isset($data['description']) ? sanitize_text_field($data['description']) : null;
        $input_type  = sanitize_text_field($data['input_type'] ?? 'single_select');
        $sort_order  = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $is_quiz_question = isset($data['is_quiz_question']) ? (int) $data['is_quiz_question'] : 0;
        $is_active   = isset($data['is_active']) ? (int) $data['is_active'] : 1;

        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT attribute_id FROM {$table} WHERE attribute_code = %s", $code));

        if ($existing_id) {
            $wpdb->update(
                $table,
                [
                    'label'       => $label,
                    'category'    => $category,
                    'description' => $description,
                    'input_type'  => $input_type,
                    'sort_order'  => $sort_order,
                    'is_quiz_question' => $is_quiz_question,
                    'is_active'   => $is_active,
                ],
                ['attribute_id' => $existing_id],
                ['%s', '%s', '%s', '%s', '%d', '%d', '%d'],
                ['%d']
            );
            return (int) $existing_id;
        }

        $wpdb->insert(
            $table,
            [
                'attribute_code' => $code,
                'label'          => $label,
                'category'       => $category,
                'description'    => $description,
                'input_type'     => $input_type,
                'sort_order'     => $sort_order,
                'is_quiz_question' => $is_quiz_question,
                'is_active'      => $is_active,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Save or update a Master Term.
     *
     * @param array $data Term dataset.
     * @return int Term ID.
     */
    public static function save_term(array $data): int {
        global $wpdb;
        $table = self::get_terms_table();

        $attr_code    = sanitize_key($data['attribute_code']);
        $term_slug    = sanitize_key($data['term_slug']);
        $label        = sanitize_text_field($data['label']);
        $description  = !empty($data['description']) ? sanitize_text_field($data['description']) : null;
        $swatch_color = !empty($data['swatch_color']) ? sanitize_text_field($data['swatch_color']) : null;
        $icon_url     = !empty($data['icon_url']) ? esc_url_raw($data['icon_url']) : null;
        $sort_order   = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $is_active    = isset($data['is_active']) ? (int) $data['is_active'] : 1;

        $existing_id = $wpdb->get_var(
            $wpdb->prepare("SELECT term_id FROM {$table} WHERE attribute_code = %s AND term_slug = %s", $attr_code, $term_slug)
        );

        if ($existing_id) {
            $wpdb->update(
                $table,
                [
                    'label'        => $label,
                    'description'  => $description,
                    'swatch_color' => $swatch_color,
                    'icon_url'     => $icon_url,
                    'sort_order'   => $sort_order,
                    'is_active'    => $is_active,
                ],
                ['term_id' => $existing_id],
                ['%s', '%s', '%s', '%s', '%d', '%d'],
                ['%d']
            );
            return (int) $existing_id;
        }

        $wpdb->insert(
            $table,
            [
                'attribute_code' => $attr_code,
                'term_slug'      => $term_slug,
                'label'          => $label,
                'description'    => $description,
                'swatch_color'   => $swatch_color,
                'icon_url'       => $icon_url,
                'sort_order'     => $sort_order,
                'is_active'      => $is_active,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Delete a term by ID.
     *
     * @param int $term_id Term ID.
     * @return bool
     */
    public static function delete_term(int $term_id): bool {
        global $wpdb;
        $table = self::get_terms_table();
        return (bool) $wpdb->delete($table, ['term_id' => $term_id], ['%d']);
    }

    /**
     * Delete an attribute and all its terms.
     *
     * @param string $code Attribute code.
     * @return bool
     */
    public static function delete_attribute(string $code): bool {
        global $wpdb;
        $attr_table  = self::get_attr_table();
        $terms_table = self::get_terms_table();

        $wpdb->delete($terms_table, ['attribute_code' => $code], ['%s']);
        return (bool) $wpdb->delete($attr_table, ['attribute_code' => $code], ['%s']);
    }

    /**
     * Retrieve term label map for fast display resolution across the engine.
     *
     * @return array Map of [attribute_code][term_slug] => label
     */
    public static function get_terms_label_map(): array {
        global $wpdb;
        $terms_table = self::get_terms_table();
        $results = $wpdb->get_results("SELECT attribute_code, term_slug, label FROM {$terms_table} WHERE is_active = 1", ARRAY_A);

        $map = [];
        if (!empty($results)) {
            foreach ($results as $r) {
                $code = $r['attribute_code'];
                $slug = $r['term_slug'];
                if (!isset($map[$code])) {
                    $map[$code] = [];
                }
                $map[$code][$slug] = $r['label'];
            }
        }

        return $map;
    }
}
