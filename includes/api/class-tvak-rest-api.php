<?php
/**
 * REST API Controller
 *
 * Exposes high-performance REST API endpoints for customer quiz evaluations,
 * attribute definitions, and engine health checks.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_REST_API {

    /**
     * API Namespace.
     */
    const NAMESPACE = 'tvak/v1';

    /**
     * Register REST API routes.
     */
    public static function register_routes() {
        // Recommend Endpoint
        register_rest_route(self::NAMESPACE, '/recommend', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'handle_recommend'],
            'permission_callback' => '__return_true',
        ]);

        // Attributes Quiz Definition Endpoint
        register_rest_route(self::NAMESPACE, '/attributes', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_get_attributes'],
            'permission_callback' => '__return_true',
        ]);

        // Quiz Dynamic Configuration Endpoint
        register_rest_route(self::NAMESPACE, '/quiz-config', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_get_quiz_config'],
            'permission_callback' => '__return_true',
        ]);

        // Health Check Endpoint
        register_rest_route(self::NAMESPACE, '/health', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_health_check'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle recommendation request.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public static function handle_recommend(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();

        $profile = new Tvak_User_Profile($params);
        $profile_array = $profile->to_array();
        $cache_key = 'rec_' . md5(wp_json_encode($profile_array));

        // 1. Check Cache Hit
        $cached_response = Tvak_Cache::get($cache_key);
        if ($cached_response) {
            $cached_response['cached'] = true;
            return new WP_REST_Response($cached_response, 200);
        }

        // 2. Execute Orchestrator Pipeline
        $orchestrator = new Tvak_Engine_Orchestrator();
        $result = $orchestrator->recommend($profile);

        if (!$result['success']) {
            return new WP_REST_Response($result, 400);
        }

        $result['cached'] = false;

        // 3. Log session BEFORE caching (cached hits won't re-log)
        if (!empty($result['items'])) {
            self::log_session($profile_array, $result);
        }

        // 4. Store Cache (TTL: 600s = 10 mins)
        Tvak_Cache::set($cache_key, $result, 600);

        return new WP_REST_Response($result, 200);
    }

    /**
     * Handle quiz attributes request.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public static function handle_get_attributes(WP_REST_Request $request) {
        $cache_key = 'all_attributes';
        $attributes = Tvak_Cache::get($cache_key);

        if (!$attributes) {
            $attributes = Tvak_Master_Data::get_attributes(true);
            Tvak_Cache::set($cache_key, $attributes, 3600);
        }

        return new WP_REST_Response([
            'success'    => true,
            'attributes' => $attributes,
        ], 200);
    }

    /**
     * Handle dynamic quiz configuration request.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public static function handle_get_quiz_config(WP_REST_Request $request) {
        $cache_key = 'quiz_config_payload';
        $quiz_config = Tvak_Cache::get($cache_key);

        if (!$quiz_config) {
            $attributes = Tvak_Master_Data::get_attributes(true);
            $steps = [];
            $step_num = 1;

            foreach ($attributes as $attr) {
                if (empty($attr['terms'])) {
                    continue;
                }

                $heading = sprintf(
                    __('Step %d: Select your %s', 'tvak-beauty-kit'),
                    $step_num,
                    $attr['label']
                );

                $steps[] = [
                    'step'           => $step_num++,
                    'attribute_code' => $attr['attribute_code'],
                    'label'          => $attr['label'],
                    'heading'        => $heading,
                    'subheading'     => $attr['description'] ?? '',
                    'input_type'     => $attr['input_type'] ?? 'single_select',
                    'terms'          => array_values($attr['terms']),
                ];
            }

            $quiz_config = [
                'total_steps' => count($steps),
                'steps'       => $steps,
            ];

            Tvak_Cache::set($cache_key, $quiz_config, 3600);
        }

        return new WP_REST_Response([
            'success'     => true,
            'quiz_config' => $quiz_config,
        ], 200);
    }

    /**
     * Handle engine health check request.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public static function handle_health_check(WP_REST_Request $request) {
        return new WP_REST_Response([
            'status'            => 'healthy',
            'engine_version'    => TVAK_VERSION,
            'db_version'        => get_option('tvak_db_version', '1.0.0'),
            'woocommerce_active'=> class_exists('WooCommerce'),
            'timestamp'         => current_time('mysql'),
        ], 200);
    }

    /**
     * Log session to wp_tvak_recommendation_session_logs.
     *
     * @param array $profile_array Profile vector.
     * @param array $kit_payload   Generated kit payload.
     * @return void
     */
    private static function log_session(array $profile_array, array $kit_payload) {
        global $wpdb;
        $table = $wpdb->prefix . 'tvak_recommendation_session_logs';

        $session_hash = md5(wp_json_encode($profile_array) . microtime());

        $wpdb->insert(
            $table,
            [
                'session_hash'            => $session_hash,
                'input_profile_vector'    => wp_json_encode($profile_array),
                'recommended_kit_payload' => wp_json_encode($kit_payload),
                'created_at'              => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s']
        );
    }
}

