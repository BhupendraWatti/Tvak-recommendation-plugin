<?php
/**
 * Standalone database installer for TVAK Custom Hamper Builder.
 *
 * @package TVAK_Custom_Hamper_Builder
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Custom_Hamper_DB {

    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

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

        $table_hampers = $wpdb->prefix . 'tvak_hampers';
        $sql_hampers = "CREATE TABLE {$table_hampers} (
            hamper_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            hamper_product_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            min_items INT UNSIGNED NOT NULL DEFAULT 2,
            max_items INT UNSIGNED NOT NULL DEFAULT 5,
            allow_optional_items TINYINT(1) NOT NULL DEFAULT 1,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (hamper_id),
            UNIQUE KEY hamper_product_id (hamper_product_id),
            KEY is_active (is_active)
        ) {$charset_collate};";
        dbDelta($sql_hampers);

        $table_hamper_items = $wpdb->prefix . 'tvak_hamper_items';
        $sql_hamper_items = "CREATE TABLE {$table_hamper_items} (
            hamper_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            hamper_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            default_quantity INT UNSIGNED NOT NULL DEFAULT 1,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            is_preselected TINYINT(1) NOT NULL DEFAULT 1,
            is_optional TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (hamper_item_id),
            UNIQUE KEY hamper_product (hamper_id, product_id),
            KEY hamper_id (hamper_id),
            KEY product_id (product_id)
        ) {$charset_collate};";
        dbDelta($sql_hamper_items);

        update_option('tchb_db_version', TCHB_DB_VERSION);
    }
}
