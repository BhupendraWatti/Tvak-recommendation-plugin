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
    const DB_VERSION = '2.1.0';

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
            is_quiz_question TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (attribute_id),
            UNIQUE KEY attribute_code (attribute_code),
            KEY category (category),
            KEY is_quiz_question (is_quiz_question),
            KEY is_active (is_active)
        ) {$charset_collate};";
        dbDelta($sql_master_attr);
        $has_quiz_col = $wpdb->get_var("SHOW COLUMNS FROM {$table_master_attr} LIKE 'is_quiz_question'");
        if (!$has_quiz_col) {
            $wpdb->query("ALTER TABLE {$table_master_attr} ADD is_quiz_question TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_order");
            $wpdb->query("ALTER TABLE {$table_master_attr} ADD KEY is_quiz_question (is_quiz_question)");
        }

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
            shade_hex VARCHAR(32) NOT NULL DEFAULT '',
            price DECIMAL(10,2) NULL,
            image_url VARCHAR(255) NULL,
            is_in_stock TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (shade_id),
            KEY product_id (product_id),
            KEY variation_id (variation_id)
        ) {$charset_collate};";
        dbDelta($sql_shades);
        $wpdb->query("ALTER TABLE {$table_shades} MODIFY shade_hex VARCHAR(32) NOT NULL DEFAULT ''");

        update_option('tvak_db_version', self::DB_VERSION);
    }

    /**
     * Seed default attributes and kit slot categories into the database.
     *
     * @return void
     */
    public static function seed_defaults() {
        global $wpdb;

        self::sync_wc_kit_slots();
        self::seed_core_quiz_attributes();
        self::sync_wc_master_data();

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

        if (class_exists('Tvak_Cache')) {
            Tvak_Cache::invalidate_rules_cache();
        }
    }

    /**
     * Seed and link WooCommerce variation SKUs directly to TVAK Product Shades table.
     * 100% dynamic — auto-discovers live variations from WooCommerce database.
     *
     * @return void
     */
    public static function seed_product_shades(): void {
        if (class_exists('Tvak_Shade_Sync')) {
            Tvak_Shade_Sync::auto_sync_catalog();
        }

        if (class_exists('Tvak_Product_Rule')) {
            Tvak_Product_Rule::auto_reconcile_unmapped_products();
        }
    }

    /**
     * Seed the three shopper-facing diagnostic questions from the recommendation spec.
     *
     * WooCommerce catalog attributes are synced separately and remain background data.
     *
     * @return void
     */
    public static function seed_core_quiz_attributes(): void {
        if (!class_exists('Tvak_Master_Data')) {
            return;
        }

        $quiz_attributes = [
            [
                'attribute_code'   => 'skin_type',
                'label'            => 'Skin Type',
                'category'         => 'quiz_profile',
                'description'      => 'What is your skin type?',
                'input_type'       => 'single_select',
                'sort_order'       => 1,
                'is_quiz_question' => 1,
                'terms'            => [
                    ['term_slug' => 'oily', 'label' => 'Oily', 'sort_order' => 1],
                    ['term_slug' => 'dry', 'label' => 'Dry', 'sort_order' => 2],
                    ['term_slug' => 'combination', 'label' => 'Combination', 'sort_order' => 3],
                    ['term_slug' => 'normal', 'label' => 'Normal', 'sort_order' => 4],
                    ['term_slug' => 'sensitive', 'label' => 'Sensitive', 'sort_order' => 5],
                ],
            ],
            [
                'attribute_code'   => 'skin_tone',
                'label'            => 'Skin Tone',
                'category'         => 'quiz_profile',
                'description'      => 'Which skin tone is closest to yours?',
                'input_type'       => 'single_select',
                'sort_order'       => 2,
                'is_quiz_question' => 1,
                'terms'            => [
                    ['term_slug' => 'fair', 'label' => 'Fair', 'swatch_color' => '#F6E5D7', 'sort_order' => 1],
                    ['term_slug' => 'light', 'label' => 'Light', 'swatch_color' => '#E8CEB8', 'sort_order' => 2],
                    ['term_slug' => 'medium', 'label' => 'Medium', 'swatch_color' => '#C9A382', 'sort_order' => 3],
                    ['term_slug' => 'tan', 'label' => 'Tan', 'swatch_color' => '#B9855D', 'sort_order' => 4],
                    ['term_slug' => 'deep', 'label' => 'Deep', 'swatch_color' => '#6F4A32', 'sort_order' => 5],
                ],
            ],
            [
                'attribute_code'   => 'skin_concern',
                'label'            => 'Skin Concerns',
                'category'         => 'quiz_profile',
                'description'      => 'What would you like to focus on?',
                'input_type'       => 'multi_select',
                'sort_order'       => 3,
                'is_quiz_question' => 1,
                'terms'            => [
                    ['term_slug' => 'acne', 'label' => 'Acne', 'sort_order' => 1],
                    ['term_slug' => 'fine_lines', 'label' => 'Fine Lines', 'sort_order' => 2],
                    ['term_slug' => 'hyperpigmentation', 'label' => 'Hyperpigmentation', 'sort_order' => 3],
                    ['term_slug' => 'dullness', 'label' => 'Dullness', 'sort_order' => 4],
                    ['term_slug' => 'redness', 'label' => 'Redness', 'sort_order' => 5],
                    ['term_slug' => 'large_pores', 'label' => 'Large Pores', 'sort_order' => 6],
                ],
            ],
        ];

        foreach ($quiz_attributes as $attr) {
            Tvak_Master_Data::save_attribute($attr);
            foreach ($attr['terms'] as $term) {
                Tvak_Master_Data::save_term(array_merge($term, [
                    'attribute_code' => $attr['attribute_code'],
                    'description'    => $term['description'] ?? '',
                    'swatch_color'   => $term['swatch_color'] ?? '',
                    'is_active'      => 1,
                ]));
            }
        }

        update_option('tvak_sensitive_profile_terms', ['sensitive', 'redness']);
    }

    /**
     * Create recommendation slots from WooCommerce product categories.
     *
     * @return void
     */
    public static function sync_wc_kit_slots(): void {
        global $wpdb;
        $table_slots = $wpdb->prefix . 'tvak_kit_slots';

        $terms = [];
        if (function_exists('get_terms')) {
            $terms = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]);
        }

        if (!empty($terms) && !is_wp_error($terms)) {
            $order = 1;
            foreach ($terms as $term) {
                $slot_code = 'product_cat_' . sanitize_key($term->slug);
                $existing = $wpdb->get_var(
                    $wpdb->prepare("SELECT slot_id FROM {$table_slots} WHERE slot_code = %s", $slot_code)
                );

                $data = [
                    'slot_code'  => $slot_code,
                    'slot_name'  => $term->name,
                    'min_items'  => 0,
                    'max_items'  => 1,
                    'sort_order' => $order++,
                ];

                if ($existing) {
                    $wpdb->update($table_slots, $data, ['slot_id' => (int) $existing], ['%s', '%s', '%d', '%d', '%d'], ['%d']);
                } else {
                    $wpdb->insert($table_slots, $data, ['%s', '%s', '%d', '%d', '%d']);
                }
            }
        }

        $fallback_exists = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT slot_id FROM {$table_slots} WHERE slot_code = %s", 'woocommerce_products')
        );
        if (!$fallback_exists) {
            $next_order = (int) $wpdb->get_var("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {$table_slots}");
            $wpdb->insert(
                $table_slots,
                [
                    'slot_code'  => 'woocommerce_products',
                    'slot_name'  => __('WooCommerce Products', 'tvak-beauty-kit'),
                    'min_items'  => 0,
                    'max_items'  => 1,
                    'sort_order' => max(1, $next_order),
                ],
                ['%s', '%s', '%d', '%d', '%d']
            );
        }
    }

    /**
     * Mirror WooCommerce global product attributes into TVAK master data.
     *
     * @return void
     */
    public static function sync_wc_master_data(): void {
        if (!function_exists('wc_get_attribute_taxonomies')) {
            return;
        }

        global $wpdb;
        $table_attributes = $wpdb->prefix . 'tvak_attribute_registry';

        $sort_order = 1;
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        if (empty($attribute_taxonomies)) {
            self::sync_wc_local_product_attributes($sort_order);
            return;
        }

        foreach ($attribute_taxonomies as $attribute) {
            $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $attr_code = sanitize_key($taxonomy);
            $label = !empty($attribute->attribute_label) ? $attribute->attribute_label : $attribute->attribute_name;

            if (self::is_quiz_attribute($attr_code)) {
                $sort_order++;
                continue;
            }

            if (class_exists('Tvak_Master_Data')) {
                Tvak_Master_Data::save_attribute([
                    'attribute_code' => $attr_code,
                    'label'          => $label,
                    'category'       => 'woocommerce',
                    'description'    => '',
                    'input_type'     => 'single_select',
                    'sort_order'     => $sort_order,
                    'is_quiz_question' => 0,
                    'is_active'      => 1,
                ]);
            }

            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]);

            $options = [];
            if (!empty($terms) && !is_wp_error($terms)) {
                $term_order = 1;
                foreach ($terms as $term) {
                    $swatch_color = class_exists('Tvak_Shade_Sync')
                        ? Tvak_Shade_Sync::get_term_swatch_color($term->term_id, $taxonomy)
                        : '';
                    $options[$term->slug] = $term->name;

                    if (class_exists('Tvak_Master_Data')) {
                        Tvak_Master_Data::save_term([
                            'attribute_code' => $attr_code,
                            'term_slug'      => $term->slug,
                            'label'          => $term->name,
                            'description'    => $term->description,
                            'swatch_color'   => $swatch_color,
                            'sort_order'     => $term_order++,
                            'is_active'      => 1,
                        ]);
                    }
                }
            }

            $legacy = [
                'attribute_code' => $attr_code,
                'label'          => $label,
                'category'       => 'woocommerce',
                'options_json'   => wp_json_encode($options),
            ];
            $existing = $wpdb->get_var(
                $wpdb->prepare("SELECT attribute_id FROM {$table_attributes} WHERE attribute_code = %s", $attr_code)
            );

            if ($existing) {
                $wpdb->update($table_attributes, $legacy, ['attribute_id' => (int) $existing], ['%s', '%s', '%s', '%s'], ['%d']);
            } else {
                $wpdb->insert($table_attributes, $legacy, ['%s', '%s', '%s', '%s']);
            }

            $sort_order++;
        }

        self::sync_wc_local_product_attributes($sort_order);
    }

    /**
     * Mirror local product attributes typed directly on WooCommerce products.
     *
     * @param int $sort_order Starting order after global attributes.
     * @return void
     */
    public static function sync_wc_local_product_attributes(int $sort_order = 1): void {
        global $wpdb;
        $table_attributes = $wpdb->prefix . 'tvak_attribute_registry';

        $rows = $wpdb->get_results("
            SELECT post_id, meta_value
            FROM {$wpdb->prefix}postmeta
            WHERE meta_key = '_product_attributes'
              AND meta_value != ''
        ", ARRAY_A);

        if (empty($rows)) {
            return;
        }

        $local_attrs = [];
        foreach ($rows as $row) {
            $product_attrs = maybe_unserialize($row['meta_value']);
            if (empty($product_attrs) || !is_array($product_attrs)) {
                continue;
            }

            foreach ($product_attrs as $attr_key => $attr_def) {
                if (!empty($attr_def['is_taxonomy'])) {
                    continue;
                }

                $name = !empty($attr_def['name']) ? (string) $attr_def['name'] : (string) $attr_key;
                $attr_code = sanitize_key($name);
                if ($attr_code === '') {
                    continue;
                }
                if (self::is_quiz_attribute($attr_code)) {
                    continue;
                }

                if (!isset($local_attrs[$attr_code])) {
                    $local_attrs[$attr_code] = [
                        'label' => $name,
                        'terms' => [],
                    ];
                }

                $raw_values = !empty($attr_def['value']) ? explode('|', (string) $attr_def['value']) : [];
                foreach ($raw_values as $raw_value) {
                    $label = trim($raw_value);
                    if ($label === '') {
                        continue;
                    }
                    $local_attrs[$attr_code]['terms'][sanitize_title($label)] = $label;
                }
            }
        }

        foreach ($local_attrs as $attr_code => $attr) {
            if (class_exists('Tvak_Master_Data')) {
                Tvak_Master_Data::save_attribute([
                    'attribute_code' => $attr_code,
                    'label'          => $attr['label'],
                    'category'       => 'woocommerce',
                    'description'    => '',
                    'input_type'     => 'single_select',
                    'sort_order'     => $sort_order,
                    'is_quiz_question' => 0,
                    'is_active'      => 1,
                ]);
            }

            $term_order = 1;
            foreach ($attr['terms'] as $term_slug => $label) {
                if (class_exists('Tvak_Master_Data')) {
                    Tvak_Master_Data::save_term([
                        'attribute_code' => $attr_code,
                        'term_slug'      => $term_slug,
                        'label'          => $label,
                        'description'    => '',
                        'swatch_color'   => '',
                        'sort_order'     => $term_order++,
                        'is_active'      => 1,
                    ]);
                }
            }

            $legacy = [
                'attribute_code' => $attr_code,
                'label'          => $attr['label'],
                'category'       => 'woocommerce',
                'options_json'   => wp_json_encode($attr['terms']),
            ];
            $existing = $wpdb->get_var(
                $wpdb->prepare("SELECT attribute_id FROM {$table_attributes} WHERE attribute_code = %s", $attr_code)
            );

            if ($existing) {
                $wpdb->update($table_attributes, $legacy, ['attribute_id' => (int) $existing], ['%s', '%s', '%s', '%s'], ['%d']);
            } else {
                $wpdb->insert($table_attributes, $legacy, ['%s', '%s', '%s', '%s']);
            }

            $sort_order++;
        }
    }

    /**
     * Check whether an attribute is reserved for shopper-facing quiz questions.
     *
     * @param string $attribute_code Attribute code.
     * @return bool
     */
    private static function is_quiz_attribute(string $attribute_code): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'tvak_master_attributes';

        return (bool) $wpdb->get_var(
            $wpdb->prepare("SELECT attribute_id FROM {$table} WHERE attribute_code = %s AND is_quiz_question = 1 LIMIT 1", sanitize_key($attribute_code))
        );
    }
}
