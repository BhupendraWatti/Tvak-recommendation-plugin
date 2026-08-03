<?php
/**
 * Performance Caching Layer (RAM & WP Object Cache)
 *
 * Manages Redis / WP Object Cache caching for compiled rules, variant hashes,
 * and profile recommendation responses for sub-50ms execution.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Cache {

    /**
     * Cache group name.
     */
    const CACHE_GROUP = 'tvak_recommendation_engine';

    /**
     * Get cached item.
     *
     * @param string $key Cache key.
     * @return mixed|false
     */
    public static function get(string $key) {
        return wp_cache_get($key, self::CACHE_GROUP);
    }

    /**
     * Set cache item.
     *
     * @param string $key    Cache key.
     * @param mixed  $data   Data to cache.
     * @param int    $expire Expiration in seconds (default 3600).
     * @return bool
     */
    public static function set(string $key, $data, int $expire = 3600): bool {
        return wp_cache_set($key, $data, self::CACHE_GROUP, $expire);
    }

    /**
     * Delete cache item.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public static function delete(string $key): bool {
        return wp_cache_delete($key, self::CACHE_GROUP);
    }

    /**
     * Flush all engine caches.
     *
     * @return void
     */
    public static function flush_all() {
        wp_cache_flush();
    }

    /**
     * Invalidate compiled rules cache and purge recommendation session caches when admin saves rules, shades, or master data.
     *
     * @return void
     */
    public static function invalidate_rules_cache() {
        self::delete('active_rules_grouped');
        self::delete('all_attributes');
        self::delete('quiz_config_payload');
        self::flush_recommendation_cache();

        if (function_exists('do_action')) {
            do_action('litespeed_purge_all');
        }
    }

    /**
     * Flush all cached recommendation profile vectors.
     *
     * @return void
     */
    public static function flush_recommendation_cache() {
        wp_cache_flush();
    }
}
