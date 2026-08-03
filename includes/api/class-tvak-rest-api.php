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
            'args'                => [
                'preferred_shades' => [
                    'type'              => 'object',
                    'required'          => false,
                    'default'           => [],
                    'validate_callback' => static function ($val) {
                        if (!is_array($val)) {
                            return true; // Will be coerced to [] in UserProfile
                        }
                        // Each entry must be a product_id (positive int) => variation_id (positive int)
                        foreach ($val as $prod_id => $var_id) {
                            if (!ctype_digit((string) $prod_id) || !ctype_digit((string) $var_id) || (int) $prod_id < 1 || (int) $var_id < 1) {
                                return new WP_Error(
                                    'invalid_preferred_shades',
                                    __('preferred_shades must be an object of product_id => variation_id integer pairs.', 'tvak-beauty-kit')
                                );
                            }
                        }
                        // Limit map size to prevent abuse
                        if (count($val) > 20) {
                            return new WP_Error(
                                'too_many_preferred_shades',
                                __('preferred_shades may not contain more than 20 entries.', 'tvak-beauty-kit')
                            );
                        }
                        return true;
                    },
                    'sanitize_callback' => static function ($val) {
                        if (!is_array($val)) {
                            return [];
                        }
                        $clean = [];
                        foreach ($val as $prod_id => $var_id) {
                            $prod_id_int = (int) $prod_id;
                            $var_id_int  = (int) $var_id;
                            if ($prod_id_int > 0 && $var_id_int > 0) {
                                $clean[$prod_id_int] = $var_id_int;
                            }
                        }
                        return $clean;
                    },
                ],
                'nocache' => [
                    'type'    => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        // Attributes Quiz Definition Endpoint
        register_rest_route(self::NAMESPACE, '/attributes', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_get_attributes'],
            'permission_callback' => '__return_true',
            'args'                => [],
        ]);

        // Quiz Dynamic Configuration Endpoint
        register_rest_route(self::NAMESPACE, '/quiz-config', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_get_quiz_config'],
            'permission_callback' => '__return_true',
            'args'                => [],
        ]);

        // Health Check Endpoint
        register_rest_route(self::NAMESPACE, '/health', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_health_check'],
            'permission_callback' => '__return_true',
            'args'                => [],
        ]);
    }


    /**
     * Handle recommendation request.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public static function handle_recommend(WP_REST_Request $request) {
        // Use get_params() (not get_json_params()) so WP REST sanitize_callbacks
        // declared in the args schema are applied before data reaches the engine.
        $params = $request->get_params();

        $profile       = new Tvak_User_Profile($params);
        $profile_array = $profile->to_array();
        $cache_version = defined('TVAK_VERSION') ? TVAK_VERSION : '1';
        $cache_key     = 'rec_' . $cache_version . '_' . md5(wp_json_encode($profile_array));

        // 1. Check Cache Hit (Bypass cache if nocache param is truthy or cached schema is stale)
        $no_cache = !empty($params['nocache']);
        if (!$no_cache) {
            $cached_response = Tvak_Cache::get($cache_key);
            if ($cached_response && !empty($cached_response['items'])) {
                $first_item = $cached_response['items'][0];
                $has_valid_schema = isset($first_item['image_url']) && isset($first_item['price']) && isset($first_item['has_shades']);
                // Ensure cached items with has_shades=true actually contain populated all_shades array
                $has_shades_valid = true;
                foreach ($cached_response['items'] as $c_item) {
                    if (!empty($c_item['has_shades']) && empty($c_item['all_shades'])) {
                        $has_shades_valid = false;
                        break;
                    }
                }

                if ($has_valid_schema && $has_shades_valid) {
                    $cached_response['cached'] = true;
                    return new WP_REST_Response($cached_response, 200);
                }
            }
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
            $attributes = Tvak_Master_Data::get_quiz_attributes(true);
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
            $attributes = Tvak_Master_Data::get_quiz_attributes(true);
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
        global $wpdb;

        $installed_db_version = get_option('tvak_db_version', '1.0.0');
        $expected_db_version  = defined('Tvak_DB::DB_VERSION') ? Tvak_DB::DB_VERSION : '1.2.0';
        $needs_migration      = version_compare($installed_db_version, $expected_db_version, '<');

        // Quick rule and slot counts for ops visibility
        $rules_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tvak_product_rules WHERE is_active = 1");
        $slots_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tvak_kit_slots");

        $status = ($needs_migration || !class_exists('WooCommerce')) ? 'degraded' : 'healthy';

        return new WP_REST_Response([
            'status'             => $status,
            'engine_version'     => defined('TVAK_VERSION') ? TVAK_VERSION : 'unknown',
            'db_version'         => $installed_db_version,
            'db_version_latest'  => $expected_db_version,
            'needs_migration'    => $needs_migration,
            'woocommerce_active' => class_exists('WooCommerce'),
            'active_rules'       => $rules_count,
            'kit_slots'          => $slots_count,
            'timestamp'          => current_time('mysql'),
        ], $needs_migration ? 503 : 200);
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
