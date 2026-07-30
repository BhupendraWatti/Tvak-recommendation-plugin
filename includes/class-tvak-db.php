<?php
/**
 * Database Handler & Schema Installer
 *
 * Handles creation of custom relational tables for TVAK rules, matrices, and logs,
 * as well as seeding default attributes and kit slots.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Tvak_DB {

    /**
     * Current DB Version.
     */
    const DB_VERSION = '1.0.0';

    /**
     * Create or update custom database tables using dbDelta.
     *
     * @return void
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Attribute Registry Table
        $table_attributes = $wpdb->prefix . 'tvak_attribute_registry';
        $sql_attributes = "CREATE TABLE {$table_attributes} (
            attribute_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            attribute_code VARCHAR(64) NOT NULL,
            label VARCHAR(128) NOT NULL,
            category VARCHAR(64) NOT NULL DEFAULT 'dermatological',
            options_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (attribute_id),
            UNIQUE KEY attribute_code (attribute_code),
            KEY category (category)
        ) {$charset_collate};";
        dbDelta($sql_attributes);

        // 2. Kit Slots Table
        $table_slots = $wpdb->prefix . 'tvak_kit_slots';
        $sql_slots = "CREATE TABLE {$table_slots} (
            slot_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slot_code VARCHAR(64) NOT NULL,
            slot_name VARCHAR(128) NOT NULL,
            min_items INT UNSIGNED NOT NULL DEFAULT 1,
            max_items INT UNSIGNED NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (slot_id),
            UNIQUE KEY slot_code (slot_code)
        ) {$charset_collate};";
        dbDelta($sql_slots);

        // 3. Product Rules Table
        $table_rules = $wpdb->prefix . 'tvak_product_rules';
        $sql_rules = "CREATE TABLE {$table_rules} (
            rule_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            slot_id INT UNSIGNED NOT NULL,
            priority_boost DECIMAL(4,2) NOT NULL DEFAULT 0.00,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (rule_id),
            KEY product_id (product_id),
            KEY is_active (is_active),
            KEY slot_id (slot_id)
        ) {$charset_collate};";
        dbDelta($sql_rules);

        // 4. Product Rule Attributes Table
        $table_rule_attrs = $wpdb->prefix . 'tvak_product_rule_attributes';
        $sql_rule_attrs = "CREATE TABLE {$table_rule_attrs} (
            rule_attr_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_id INT UNSIGNED NOT NULL,
            attribute_code VARCHAR(64) NOT NULL,
            weight DECIMAL(4,2) NOT NULL DEFAULT 1.00,
            match_matrix LONGTEXT NULL,
            PRIMARY KEY  (rule_attr_id),
            KEY rule_id (rule_id),
            KEY attribute_code (attribute_code)
        ) {$charset_collate};";
        dbDelta($sql_rule_attrs);

        // 5. Variant Mapping Matrix Table
        $table_variant_map = $wpdb->prefix . 'tvak_variant_mapping_matrix';
        $sql_variant_map = "CREATE TABLE {$table_variant_map} (
            map_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            variation_id BIGINT UNSIGNED NOT NULL,
            attribute_hash VARCHAR(64) NOT NULL,
            criteria_vector LONGTEXT NULL,
            priority INT NOT NULL DEFAULT 0,
            PRIMARY KEY  (map_id),
            KEY product_id (product_id),
            KEY variation_id (variation_id),
            UNIQUE KEY prod_hash (product_id, attribute_hash)
        ) {$charset_collate};";
        dbDelta($sql_variant_map);

        // 6. Recommendation Session Logs Table
        $table_logs = $wpdb->prefix . 'tvak_recommendation_session_logs';
        $sql_logs = "CREATE TABLE {$table_logs} (
            log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_hash VARCHAR(64) NOT NULL,
            input_profile_vector LONGTEXT NULL,
            recommended_kit_payload LONGTEXT NULL,
            converted_order_id BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (log_id),
            KEY session_hash (session_hash),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta($sql_logs);

        update_option('tvak_db_version', self::DB_VERSION);
    }

    /**
     * Seed default attributes and kit slot categories into the database.
     *
     * @return void
     */
    public static function seed_defaults() {
        global $wpdb;

        // Seed Kit Slots
        $table_slots = $wpdb->prefix . 'tvak_kit_slots';
        $default_slots = [
            [
                'slot_code'  => 'cleanser_balm',
                'slot_name'  => 'Cleanser / Purification',
                'min_items'  => 1,
                'max_items'  => 1,
                'sort_order' => 1,
            ],
            [
                'slot_code'  => 'compound_cream',
                'slot_name'  => 'Treatment & Hydration Cream',
                'min_items'  => 1,
                'max_items'  => 1,
                'sort_order' => 2,
            ],
            [
                'slot_code'  => 'bb_cream',
                'slot_name'  => 'Complexion Finish (BB Cream)',
                'min_items'  => 1,
                'max_items'  => 1,
                'sort_order' => 3,
            ],
            [
                'slot_code'  => 'setting_spray',
                'slot_name'  => 'Setting Spray & Finish',
                'min_items'  => 1,
                'max_items'  => 1,
                'sort_order' => 4,
            ],
            [
                'slot_code'  => 'lipstick_accent',
                'slot_name'  => 'Lips & Color Accent',
                'min_items'  => 1,
                'max_items'  => 1,
                'sort_order' => 5,
            ],
            [
                'slot_code'  => 'universal_eyeliner',
                'slot_name'  => 'Eye Contour Accent',
                'min_items'  => 1,
                'max_items'  => 1,
                'sort_order' => 6,
            ],
        ];

        foreach ($default_slots as $slot) {
            $existing = $wpdb->get_var(
                $wpdb->prepare("SELECT slot_id FROM {$table_slots} WHERE slot_code = %s", $slot['slot_code'])
            );
            if (!$existing) {
                $wpdb->insert($table_slots, $slot);
            }
        }

        // Seed Attribute Registry
        $table_attributes = $wpdb->prefix . 'tvak_attribute_registry';
        $default_attributes = [
            [
                'attribute_code' => 'skin_type',
                'label'          => 'Skin Type',
                'category'       => 'dermatological',
                'options_json'   => wp_json_encode([
                    'dry'         => 'Dry',
                    'oily'        => 'Oily',
                    'normal'      => 'Normal',
                    'combination' => 'Combination',
                    'sensitive'   => 'Sensitive',
                ]),
            ],
            [
                'attribute_code' => 'skin_tone',
                'label'          => 'Skin Tone',
                'category'       => 'cosmetic',
                'options_json'   => wp_json_encode([
                    'fair_light'        => 'Fair / Light',
                    'light_medium'      => 'Light – Medium / Medium',
                    'medium_deep'       => 'Medium – Deep',
                    'deep_rich'         => 'Deep & Rich',
                    'very_deep'         => 'Very Deep',
                ]),
            ],
            [
                'attribute_code' => 'skin_concern',
                'label'          => 'Skin Concerns',
                'category'       => 'dermatological',
                'options_json'   => wp_json_encode([
                    'acne'                => 'Acne',
                    'dry_dehydrated'      => 'Dry & Dehydrated',
                    'oily_enlarged_pores' => 'Oily & Enlarged Pores',
                    'sensitive'           => 'Sensitive',
                    'hyperpigmentation'   => 'Hyperpigmentation & Dark Spots',
                    'uneven_texture'      => 'Uneven Texture',
                    'fine_lines_wrinkles' => 'Fine Lines & Wrinkles',
                ]),
            ],
        ];

        foreach ($default_attributes as $attr) {
            $existing = $wpdb->get_var(
                $wpdb->prepare("SELECT attribute_id FROM {$table_attributes} WHERE attribute_code = %s", $attr['attribute_code'])
            );
            if (!$existing) {
                $wpdb->insert($table_attributes, $attr);
            }
        }
    }
}
