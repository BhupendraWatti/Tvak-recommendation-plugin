<?php
/**
 * Attribute Model Class
 *
 * Provides CRUD operations for dynamic attributes in wp_tvak_attribute_registry.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Attribute {

    /**
     * Table name.
     *
     * @var string
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'tvak_attribute_registry';
    }

    /**
     * Retrieve all registered attributes.
     *
     * @return array
     */
    public static function get_all() {
        global $wpdb;
        $table = self::get_table_name();
        $results = $wpdb->get_results("SELECT * FROM {$table} ORDER BY category ASC, label ASC", ARRAY_A);

        if (!$results) {
            return [];
        }

        foreach ($results as &$row) {
            $row['options'] = !empty($row['options_json']) ? json_decode($row['options_json'], true) : [];
        }

        return $results;
    }

    /**
     * Retrieve attribute by code.
     *
     * @param string $code Attribute machine code.
     * @return array|null
     */
    public static function get_by_code($code) {
        global $wpdb;
        $table = self::get_table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE attribute_code = %s", $code), ARRAY_A);

        if (!$row) {
            return null;
        }

        $row['options'] = !empty($row['options_json']) ? json_decode($row['options_json'], true) : [];
        return $row;
    }

    /**
     * Save or update an attribute.
     *
     * @param array $data Attribute data.
     * @return int|bool Insertion ID or success boolean.
     */
    public static function save($data) {
        global $wpdb;
        $table = self::get_table_name();

        $attribute_code = sanitize_key($data['attribute_code']);
        $label          = sanitize_text_field($data['label']);
        $category       = sanitize_text_field($data['category'] ?? 'dermatological');
        $options        = is_array($data['options']) ? $data['options'] : [];
        $options_json   = wp_json_encode($options);

        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT attribute_id FROM {$table} WHERE attribute_code = %s", $attribute_code));

        if ($existing_id) {
            $wpdb->update(
                $table,
                [
                    'label'        => $label,
                    'category'     => $category,
                    'options_json' => $options_json,
                ],
                ['attribute_id' => $existing_id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            return $existing_id;
        }

        $wpdb->insert(
            $table,
            [
                'attribute_code' => $attribute_code,
                'label'          => $label,
                'category'       => $category,
                'options_json'   => $options_json,
            ],
            ['%s', '%s', '%s', '%s']
        );

        return $wpdb->insert_id;
    }
}
