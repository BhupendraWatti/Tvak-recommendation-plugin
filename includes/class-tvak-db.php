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
    const DB_VERSION = '1.2.0';

    /**
     * Create or update custom database tables using dbDelta.
     *
     * @return void
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Attribute Registry Table (Legacy Compatibility)
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

        // 2. Master Attributes Table
        $table_master_attr = $wpdb->prefix . 'tvak_master_attributes';
        $sql_master_attr = "CREATE TABLE {$table_master_attr} (
            attribute_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            attribute_code VARCHAR(64) NOT NULL,
            label VARCHAR(128) NOT NULL,
            category VARCHAR(64) NOT NULL DEFAULT 'dermatological',
            description TEXT NULL,
            input_type VARCHAR(32) NOT NULL DEFAULT 'single_select',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (attribute_id),
            UNIQUE KEY attribute_code (attribute_code),
            KEY category (category),
            KEY is_active (is_active)
        ) {$charset_collate};";
        dbDelta($sql_master_attr);

        // 3. Master Terms Table
        $table_master_terms = $wpdb->prefix . 'tvak_master_terms';
        $sql_master_terms = "CREATE TABLE {$table_master_terms} (
            term_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            attribute_code VARCHAR(64) NOT NULL,
            term_slug VARCHAR(64) NOT NULL,
            label VARCHAR(128) NOT NULL,
            description TEXT NULL,
            swatch_color VARCHAR(32) NULL,
            icon_url VARCHAR(255) NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (term_id),
            UNIQUE KEY attr_term (attribute_code, term_slug),
            KEY attribute_code (attribute_code),
            KEY is_active (is_active)
        ) {$charset_collate};";
        dbDelta($sql_master_terms);

        // 4. Kit Slots Table
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

        // 5. Product Rules Table
        $table_rules = $wpdb->prefix . 'tvak_product_rules';
        $sql_rules = "CREATE TABLE {$table_rules} (
            rule_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            slot_id INT UNSIGNED NOT NULL,
            priority_boost DECIMAL(4,2) NOT NULL DEFAULT 0.00,
            min_score_threshold DECIMAL(3,2) NULL DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (rule_id),
            KEY product_id (product_id),
            KEY is_active (is_active),
            KEY slot_id (slot_id)
        ) {$charset_collate};";
        dbDelta($sql_rules);

        // 6. Product Rule Attributes Table
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

        // 7. Variant Mapping Matrix Table
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

        // 8. Recommendation Session Logs Table
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

        // 9. Product Shades Table
        $table_shades = $wpdb->prefix . 'tvak_product_shades';
        $sql_shades = "CREATE TABLE {$table_shades} (
            shade_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            variation_id BIGINT UNSIGNED NULL,
            shade_name VARCHAR(128) NOT NULL,
            shade_hex VARCHAR(32) NOT NULL DEFAULT '#D4AF37',
            price DECIMAL(10,2) NULL,
            image_url VARCHAR(255) NULL,
            is_in_stock TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (shade_id),
            KEY product_id (product_id),
            KEY variation_id (variation_id)
        ) {$charset_collate};";
        dbDelta($sql_shades);

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

        // Seed Master Attributes
        $table_master_attr = $wpdb->prefix . 'tvak_master_attributes';
        $table_master_terms = $wpdb->prefix . 'tvak_master_terms';

        $master_attributes = [
            [
                'attribute_code' => 'skin_type',
                'label'          => 'Skin Type',
                'category'       => 'dermatological',
                'description'    => 'Select your primary skin type',
                'input_type'     => 'single_select',
                'sort_order'     => 1,
                'terms'          => [
                    ['term_slug' => 'dry', 'label' => 'Dry', 'description' => 'Tightness, flaking or dullness', 'sort_order' => 1],
                    ['term_slug' => 'oily', 'label' => 'Oily', 'description' => 'Excess shine & enlarged pores', 'sort_order' => 2],
                    ['term_slug' => 'normal', 'label' => 'Normal', 'description' => 'Well-balanced hydration', 'sort_order' => 3],
                    ['term_slug' => 'combination', 'label' => 'Combination', 'description' => 'Oily T-zone, normal/dry cheeks', 'sort_order' => 4],
                    ['term_slug' => 'sensitive', 'label' => 'Sensitive', 'description' => 'Easily irritated or red', 'sort_order' => 5],
                ],
            ],
            [
                'attribute_code' => 'skin_tone',
                'label'          => 'Skin Tone',
                'category'       => 'cosmetic',
                'description'    => 'Select your skin tone group',
                'input_type'     => 'single_select',
                'sort_order'     => 2,
                'terms'          => [
                    ['term_slug' => 'fair_light', 'label' => 'Fair / Light', 'swatch_color' => '#F6E5D7', 'sort_order' => 1],
                    ['term_slug' => 'light_medium', 'label' => 'Light – Medium', 'swatch_color' => '#E8CEB8', 'sort_order' => 2],
                    ['term_slug' => 'medium_deep', 'label' => 'Medium – Deep', 'swatch_color' => '#C9A382', 'sort_order' => 3],
                    ['term_slug' => 'deep_rich', 'label' => 'Deep & Rich', 'swatch_color' => '#8D5B3A', 'sort_order' => 4],
                    ['term_slug' => 'very_deep', 'label' => 'Very Deep', 'swatch_color' => '#4F301F', 'sort_order' => 5],
                ],
            ],
            [
                'attribute_code' => 'skin_concern',
                'label'          => 'Skin Concerns',
                'category'       => 'dermatological',
                'description'    => 'What are your target skin concerns?',
                'input_type'     => 'multi_select',
                'sort_order'     => 3,
                'terms'          => [
                    ['term_slug' => 'acne', 'label' => 'Acne & Breakouts', 'sort_order' => 1],
                    ['term_slug' => 'dry_dehydrated', 'label' => 'Dry & Dehydrated', 'sort_order' => 2],
                    ['term_slug' => 'oily_enlarged_pores', 'label' => 'Oily & Enlarged Pores', 'sort_order' => 3],
                    ['term_slug' => 'sensitive', 'label' => 'Sensitivity & Redness', 'sort_order' => 4],
                    ['term_slug' => 'hyperpigmentation', 'label' => 'Hyperpigmentation & Dark Spots', 'sort_order' => 5],
                    ['term_slug' => 'uneven_texture', 'label' => 'Uneven Texture', 'sort_order' => 6],
                    ['term_slug' => 'fine_lines_wrinkles', 'label' => 'Fine Lines & Wrinkles', 'sort_order' => 7],
                ],
            ],
        ];

        foreach ($master_attributes as $attr) {
            $attr_code = $attr['attribute_code'];
            $existing_attr = $wpdb->get_var(
                $wpdb->prepare("SELECT attribute_id FROM {$table_master_attr} WHERE attribute_code = %s", $attr_code)
            );

            if (!$existing_attr) {
                $wpdb->insert($table_master_attr, [
                    'attribute_code' => $attr_code,
                    'label'          => $attr['label'],
                    'category'       => $attr['category'],
                    'description'    => $attr['description'],
                    'input_type'     => $attr['input_type'],
                    'sort_order'     => $attr['sort_order'],
                    'is_active'      => 1,
                ]);
            }

            foreach ($attr['terms'] as $term) {
                $term_slug = $term['term_slug'];
                $existing_term = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT term_id FROM {$table_master_terms} WHERE attribute_code = %s AND term_slug = %s",
                        $attr_code,
                        $term_slug
                    )
                );

                if (!$existing_term) {
                    $wpdb->insert($table_master_terms, [
                        'attribute_code' => $attr_code,
                        'term_slug'      => $term_slug,
                        'label'          => $term['label'],
                        'description'    => $term['description'] ?? null,
                        'swatch_color'   => $term['swatch_color'] ?? null,
                        'icon_url'       => $term['icon_url'] ?? null,
                        'sort_order'     => $term['sort_order'] ?? 0,
                        'is_active'      => 1,
                    ]);
                }
            }
        }

        // Seed Legacy Attribute Registry Table for Backward Compatibility
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

        // Run automated seeding & linking for WooCommerce Variations and Product Shades
        self::seed_product_shades();

        // SQL Cleanup for Product Shades (Purge duplicate rows keeping lowest shade_id)
        $table_shades = $wpdb->prefix . 'tvak_product_shades';
        $wpdb->query("
            DELETE t1 FROM {$table_shades} t1
            INNER JOIN {$table_shades} t2 
            WHERE t1.shade_id > t2.shade_id 
              AND t1.product_id = t2.product_id 
              AND LOWER(TRIM(t1.shade_name)) = LOWER(TRIM(t2.shade_name))
        ");
    }

    /**
     * Seed and link WooCommerce variation SKUs directly to TVAK Product Shades table.
     *
     * @return void
     */
    public static function seed_product_shades(): void {
        if (!class_exists('Tvak_Product_Shade')) {
            return;
        }

        global $wpdb;
        $table_shades = $wpdb->prefix . 'tvak_product_shades';

        // Master Shade Seed Dataset mapped directly to WooCommerce variation post IDs
        $shade_seeds = [
            // Product #14: BB Cream – SPF 30
            14 => [
                ['var_id' => 15, 'name' => 'Soft Beige (SH-1044)', 'hex' => '#E5C39E', 'order' => 1],
                ['var_id' => 16, 'name' => 'Biscuit (SH-1047)',    'hex' => '#D5A77B', 'order' => 2],
                ['var_id' => 17, 'name' => 'Bisque (SH-1049)',     'hex' => '#C89466', 'order' => 3],
                ['var_id' => 18, 'name' => 'Honey Warm (SH-1052)', 'hex' => '#B87C4C', 'order' => 4],
                ['var_id' => 19, 'name' => 'Espresso Cocoa (SH-1060)', 'hex' => '#70442A', 'order' => 5],
            ],
            // Product #21: Mousse Liquid Lipstick
            21 => [
                ['var_id' => 22, 'name' => 'Bubblegum June (SH-346)', 'hex' => '#D07765', 'order' => 1],
                ['var_id' => 23, 'name' => 'Cherry Charm (SH-357)',   'hex' => '#A32328', 'order' => 2],
                ['var_id' => 24, 'name' => 'Evening Star (SH-301)',   'hex' => '#A01C24', 'order' => 3],
                ['var_id' => 25, 'name' => 'Mulberry Mood (SH-361)',  'hex' => '#781A22', 'order' => 4],
                ['var_id' => 26, 'name' => 'Brunette (SH-337)',       'hex' => '#F5EBE1', 'order' => 5],
            ],
            // Product #27: Kajal Intense Eyeliner
            27 => [
                ['var_id' => null, 'name' => 'Sapphire',  'hex' => '#1A2F50', 'order' => 1],
                ['var_id' => null, 'name' => 'Onyx',      'hex' => '#0F0F11', 'order' => 2],
                ['var_id' => null, 'name' => 'Espresso',  'hex' => '#3B231A', 'order' => 3],
                ['var_id' => null, 'name' => 'Cinnabar',  'hex' => '#541B19', 'order' => 4],
                ['var_id' => null, 'name' => 'Pewter',    'hex' => '#41474D', 'order' => 5],
            ],
        ];

        foreach ($shade_seeds as $product_id => $shades) {
            Tvak_Product_Shade::set_product_has_shades($product_id, true);

            foreach ($shades as $s) {
                $var_id = $s['var_id'];
                $name   = $s['name'];
                $hex    = $s['hex'];
                $order  = $s['order'];

                // Save to wp_tvak_product_shades
                Tvak_Product_Shade::save_shade([
                    'product_id'   => $product_id,
                    'variation_id' => $var_id,
                    'shade_name'   => $name,
                    'shade_hex'    => $hex,
                    'is_in_stock'  => 1,
                    'sort_order'   => $order,
                ]);

                // Synchronize attributes directly onto WooCommerce variation postmeta if variation post exists
                if ($var_id && function_exists('update_post_meta')) {
                    update_post_meta($var_id, '_tvak_shade_name', $name);
                    update_post_meta($var_id, '_tvak_shade_hex', $hex);
                    update_post_meta($var_id, 'attribute_shade', $name);
                    update_post_meta($var_id, 'attribute_pa_color', $name);
                }
            }

            // Remove self-referential single dummy entries where shade_name matches product title
            $title = get_the_title($product_id);
            if (!empty($title)) {
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$table_shades} WHERE product_id = %d AND LOWER(TRIM(shade_name)) = LOWER(TRIM(%s))",
                        $product_id,
                        $title
                    )
                );
            }
        }
    }
}

