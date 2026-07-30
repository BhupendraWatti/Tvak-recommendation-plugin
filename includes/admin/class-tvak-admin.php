<?php
/**
 * Admin Management Controller & Interface
 *
 * Handles WordPress Dashboard menus, rule configuration forms, variant mapping UI,
 * and the Rule Simulator tool.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Admin {

    /**
     * Initialize admin hooks.
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu_pages']);
        add_action('admin_post_tvak_save_product_rule', [__CLASS__, 'handle_save_product_rule']);
        add_action('admin_post_tvak_save_variant_mapping', [__CLASS__, 'handle_save_variant_mapping']);
        add_action('admin_post_tvak_delete_variant_mapping', [__CLASS__, 'handle_delete_variant_mapping']);
    }

    /**
     * Register Admin Top-Level Menu and Submenus.
     */
    public static function register_menu_pages() {
        add_menu_page(
            __('TVAK Beauty Engine', 'tvak-beauty-kit'),
            __('TVAK Engine', 'tvak-beauty-kit'),
            'manage_options',
            'tvak-engine',
            [__CLASS__, 'render_product_rules_page'],
            'dashicons-sparkles',
            58
        );

        add_submenu_page(
            'tvak-engine',
            __('Product Rules', 'tvak-beauty-kit'),
            __('Product Rules', 'tvak-beauty-kit'),
            'manage_options',
            'tvak-engine',
            [__CLASS__, 'render_product_rules_page']
        );

        add_submenu_page(
            'tvak-engine',
            __('Variant Matrix', 'tvak-beauty-kit'),
            __('Variant Matrix', 'tvak-beauty-kit'),
            'manage_options',
            'tvak-variant-matrix',
            [__CLASS__, 'render_variant_matrix_page']
        );

        add_submenu_page(
            'tvak-engine',
            __('Rule Simulator', 'tvak-beauty-kit'),
            __('Rule Simulator', 'tvak-beauty-kit'),
            'manage_options',
            'tvak-simulator',
            [__CLASS__, 'render_simulator_page']
        );
    }

    /**
     * Render Product Rules Page.
     */
    public static function render_product_rules_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $slots = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}tvak_kit_slots ORDER BY sort_order ASC", ARRAY_A);
        $attributes = Tvak_Attribute::get_all();

        // Get all WooCommerce products
        $wc_products = get_posts([
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $selected_product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : (isset($wc_products[0]) ? $wc_products[0]->ID : 0);
        $existing_rule = $selected_product_id ? Tvak_Product_Rule::get_by_product_id($selected_product_id) : null;

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('TVAK Recommendation Engine - Product Rules', 'tvak-beauty-kit'); ?></h1>
            <p><?php esc_html_e('Configure independent evaluation weights, kit category slotting, and priority boost per product.', 'tvak-beauty-kit'); ?></p>

            <?php if (isset($_GET['message']) && $_GET['message'] === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Product recommendation rule saved successfully!', 'tvak-beauty-kit'); ?></p></div>
            <?php endif; ?>

            <div class="postbox" style="padding: 20px; margin-top: 20px; background: #fff;">
                <form method="get" action="">
                    <input type="hidden" name="page" value="tvak-engine" />
                    <label for="product_select"><strong><?php esc_html_e('Select Product to Configure:', 'tvak-beauty-kit'); ?></strong></label>
                    <select name="product_id" id="product_select" onchange="this.form.submit()" style="min-width: 300px; margin-left: 10px;">
                        <?php foreach ($wc_products as $p) : ?>
                            <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($selected_product_id, $p->ID); ?>>
                                <?php echo esc_html($p->post_title); ?> (ID: <?php echo esc_html($p->ID); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($selected_product_id) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="tvak_save_product_rule" />
                    <input type="hidden" name="product_id" value="<?php echo esc_attr($selected_product_id); ?>" />
                    <?php wp_nonce_field('tvak_save_product_rule_nonce', 'tvak_nonce'); ?>

                    <div class="postbox" style="padding: 20px; background: #fff;">
                        <h2><?php esc_html_e('Rule Settings for:', 'tvak-beauty-kit'); ?> <em><?php echo esc_html(get_the_title($selected_product_id)); ?></em></h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="slot_id"><?php esc_html_e('Kit Category Slot', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <select name="slot_id" id="slot_id" required>
                                        <option value=""><?php esc_html_e('-- Select Kit Slot --', 'tvak-beauty-kit'); ?></option>
                                        <?php foreach ($slots as $s) : ?>
                                            <option value="<?php echo esc_attr($s['slot_id']); ?>" <?php selected($existing_rule['slot_id'] ?? 0, $s['slot_id']); ?>>
                                                <?php echo esc_html($s['slot_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="priority_boost"><?php esc_html_e('Priority Baseline Boost B(P)', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="number" step="0.05" min="0" max="1" name="priority_boost" id="priority_boost" value="<?php echo esc_attr($existing_rule['priority_boost'] ?? '0.00'); ?>" class="small-text" />
                                    <p class="description"><?php esc_html_e('Constant score boost (e.g. 1.00 for Universal Eyeliner, 0.00 for normal items).', 'tvak-beauty-kit'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Rule Status', 'tvak-beauty-kit'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="is_active" value="1" <?php checked($existing_rule['is_active'] ?? 1, 1); ?> /> <?php esc_html_e('Active in Recommendation Engine', 'tvak-beauty-kit'); ?></label>
                                </td>
                            </tr>
                        </table>

                        <hr />
                        <h3><?php esc_html_e('Attribute Evaluation Weights & Match Matrices', 'tvak-beauty-kit'); ?></h3>

                        <?php foreach ($attributes as $attr) :
                            $attr_code = $attr['attribute_code'];
                            $rule_attr = $existing_rule['attribute_rules'][$attr_code] ?? ['weight' => 1.00, 'match_matrix' => []];
                            $options   = $attr['options'];
                        ?>
                            <div style="background: #f9f9f9; border: 1px solid #e5e5e5; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                                <h4><?php echo esc_html($attr['label']); ?> (<code><?php echo esc_html($attr_code); ?></code>)</h4>
                                <label><strong><?php esc_html_e('Weight W:', 'tvak-beauty-kit'); ?></strong>
                                    <input type="number" step="0.05" min="0" max="1" name="attribute_rules[<?php echo esc_attr($attr_code); ?>][weight]" value="<?php echo esc_attr($rule_attr['weight']); ?>" class="small-text" />
                                </label>

                                <div style="margin-top: 10px;">
                                    <strong><?php esc_html_e('Match Score Matrix (0.00 = Unsuitable, 1.00 = Perfect Fit):', 'tvak-beauty-kit'); ?></strong>
                                    <table class="widefat striped" style="margin-top: 5px; max-width: 600px;">
                                        <thead>
                                            <tr><th><?php esc_html_e('Option Value', 'tvak-beauty-kit'); ?></th><th><?php esc_html_e('Match Score M', 'tvak-beauty-kit'); ?></th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($options as $opt_key => $opt_label) :
                                                $score_val = $rule_attr['match_matrix'][$opt_key] ?? '1.00';
                                            ?>
                                                <tr>
                                                    <td><?php echo esc_html($opt_label); ?> (<code><?php echo esc_html($opt_key); ?></code>)</td>
                                                    <td>
                                                        <input type="number" step="0.05" min="0" max="1" name="attribute_rules[<?php echo esc_attr($attr_code); ?>][match_matrix][<?php echo esc_attr($opt_key); ?>]" value="<?php echo esc_attr($score_val); ?>" class="small-text" />
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <p class="submit">
                            <input type="submit" class="button button-primary button-large" value="<?php esc_attr_e('Save Product Rule', 'tvak-beauty-kit'); ?>" />
                        </p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle Save Product Rule.
     */
    public static function handle_save_product_rule() {
        check_admin_referer('tvak_save_product_rule_nonce', 'tvak_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'tvak-beauty-kit'));
        }

        $product_id      = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $slot_id         = isset($_POST['slot_id']) ? (int) $_POST['slot_id'] : 0;
        $priority_boost = isset($_POST['priority_boost']) ? (float) $_POST['priority_boost'] : 0.0;
        $is_active       = isset($_POST['is_active']) ? 1 : 0;
        $attribute_rules = $_POST['attribute_rules'] ?? [];

        if ($product_id && $slot_id) {
            Tvak_Product_Rule::save_rule($product_id, $slot_id, $priority_boost, $is_active, $attribute_rules);
            Tvak_Cache::invalidate_rules_cache();
        }

        wp_redirect(admin_url('admin.php?page=tvak-engine&product_id=' . $product_id . '&message=saved'));
        exit;
    }

    /**
     * Render Variant Matrix Page.
     */
    public static function render_variant_matrix_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $wc_products = get_posts([
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $selected_product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : (isset($wc_products[0]) ? $wc_products[0]->ID : 0);
        $variations          = [];

        if ($selected_product_id && class_exists('WC_Product')) {
            $product_obj = wc_get_product($selected_product_id);
            if ($product_obj && $product_obj->is_type('variable')) {
                $variations = $product_obj->get_available_variations();
            }
        }

        $existing_mappings = $selected_product_id ? Tvak_Variant_Map::get_mappings_for_product($selected_product_id) : [];
        $attributes        = Tvak_Attribute::get_all();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('TVAK Recommendation Engine - Variant Matrix', 'tvak-beauty-kit'); ?></h1>
            <p><?php esc_html_e('Map profile attribute combinations directly to WooCommerce product variations.', 'tvak-beauty-kit'); ?></p>

            <div class="postbox" style="padding: 20px; background: #fff;">
                <form method="get" action="">
                    <input type="hidden" name="page" value="tvak-variant-matrix" />
                    <label for="product_select"><strong><?php esc_html_e('Select Variable Product:', 'tvak-beauty-kit'); ?></strong></label>
                    <select name="product_id" id="product_select" onchange="this.form.submit()" style="min-width: 300px; margin-left: 10px;">
                        <?php foreach ($wc_products as $p) : ?>
                            <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($selected_product_id, $p->ID); ?>>
                                <?php echo esc_html($p->post_title); ?> (ID: <?php echo esc_html($p->ID); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($selected_product_id) : ?>
                <div class="postbox" style="padding: 20px; background: #fff;">
                    <h2><?php esc_html_e('Add New Variant Mapping', 'tvak-beauty-kit'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="tvak_save_variant_mapping" />
                        <input type="hidden" name="product_id" value="<?php echo esc_attr($selected_product_id); ?>" />
                        <?php wp_nonce_field('tvak_save_variant_mapping_nonce', 'tvak_nonce'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="variation_id"><?php esc_html_e('Target Variation SKU / Shade', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <select name="variation_id" id="variation_id" required>
                                        <option value=""><?php esc_html_e('-- Select Variation --', 'tvak-beauty-kit'); ?></option>
                                        <?php if (!empty($variations)) : ?>
                                            <?php foreach ($variations as $v) : ?>
                                                <option value="<?php echo esc_attr($v['variation_id']); ?>">
                                                    <?php echo esc_html(implode(', ', $v['attributes'])); ?> (SKU: <?php echo esc_html($v['sku'] ?: $v['variation_id']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <option value="<?php echo esc_attr($selected_product_id); ?>"><?php esc_html_e('Simple Product / Main SKU', 'tvak-beauty-kit'); ?></option>
                                        <?php endif; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php foreach ($attributes as $attr) : ?>
                                <tr>
                                    <th scope="row"><?php echo esc_html($attr['label']); ?></th>
                                    <td>
                                        <select name="criteria[<?php echo esc_attr($attr['attribute_code']); ?>]">
                                            <option value=""><?php esc_html_e('-- Any / Ignore --', 'tvak-beauty-kit'); ?></option>
                                            <?php foreach ($attr['options'] as $opt_key => $opt_label) : ?>
                                                <option value="<?php echo esc_attr($opt_key); ?>"><?php echo esc_html($opt_label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <th scope="row"><label for="priority"><?php esc_html_e('Priority Rank', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="number" name="priority" id="priority" value="10" class="small-text" />
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php esc_attr_e('Add Variation Mapping', 'tvak-beauty-kit'); ?>" />
                        </p>
                    </form>

                    <hr />
                    <h2><?php esc_html_e('Existing Variant Mappings', 'tvak-beauty-kit'); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Map ID', 'tvak-beauty-kit'); ?></th>
                                <th><?php esc_html_e('Variation ID', 'tvak-beauty-kit'); ?></th>
                                <th><?php esc_html_e('Criteria Vector', 'tvak-beauty-kit'); ?></th>
                                <th><?php esc_html_e('Attribute Hash', 'tvak-beauty-kit'); ?></th>
                                <th><?php esc_html_e('Priority', 'tvak-beauty-kit'); ?></th>
                                <th><?php esc_html_e('Actions', 'tvak-beauty-kit'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($existing_mappings)) : ?>
                                <?php foreach ($existing_mappings as $map) : ?>
                                    <tr>
                                        <td><?php echo esc_html($map['map_id']); ?></td>
                                        <td><strong><?php echo esc_html($map['variation_id']); ?></strong></td>
                                        <td><code><?php echo esc_html(wp_json_encode($map['criteria'])); ?></code></td>
                                        <td><code><?php echo esc_html(substr($map['attribute_hash'], 0, 10)); ?>...</code></td>
                                        <td><?php echo esc_html($map['priority']); ?></td>
                                        <td>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                                <input type="hidden" name="action" value="tvak_delete_variant_mapping" />
                                                <input type="hidden" name="map_id" value="<?php echo esc_attr($map['map_id']); ?>" />
                                                <input type="hidden" name="product_id" value="<?php echo esc_attr($selected_product_id); ?>" />
                                                <?php wp_nonce_field('tvak_delete_variant_mapping_nonce', 'tvak_nonce'); ?>
                                                <input type="submit" class="button button-secondary button-small" value="<?php esc_attr_e('Delete', 'tvak-beauty-kit'); ?>" onclick="return confirm('Delete mapping?');" />
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr><td colspan="6"><?php esc_html_e('No variant mappings defined yet.', 'tvak-beauty-kit'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle Save Variant Mapping.
     */
    public static function handle_save_variant_mapping() {
        check_admin_referer('tvak_save_variant_mapping_nonce', 'tvak_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'tvak-beauty-kit'));
        }

        $product_id   = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $variation_id = isset($_POST['variation_id']) ? (int) $_POST['variation_id'] : 0;
        $priority     = isset($_POST['priority']) ? (int) $_POST['priority'] : 0;
        $raw_criteria = $_POST['criteria'] ?? [];

        $criteria = array_filter($raw_criteria, function ($v) {
            return !empty($v);
        });

        if ($product_id && $variation_id && !empty($criteria)) {
            Tvak_Variant_Map::save_mapping($product_id, $variation_id, $criteria, $priority);
        }

        wp_redirect(admin_url('admin.php?page=tvak-variant-matrix&product_id=' . $product_id));
        exit;
    }

    /**
     * Handle Delete Variant Mapping.
     */
    public static function handle_delete_variant_mapping() {
        check_admin_referer('tvak_delete_variant_mapping_nonce', 'tvak_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'tvak-beauty-kit'));
        }

        $map_id     = isset($_POST['map_id']) ? (int) $_POST['map_id'] : 0;
        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

        if ($map_id) {
            Tvak_Variant_Map::delete_mapping($map_id);
        }

        wp_redirect(admin_url('admin.php?page=tvak-variant-matrix&product_id=' . $product_id));
        exit;
    }

    /**
     * Render Rule Simulator Page.
     */
    public static function render_simulator_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $attributes = Tvak_Attribute::get_all();
        $simulation_result = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tvak_simulate_nonce'])) {
            check_admin_referer('tvak_simulate_nonce_action', 'tvak_simulate_nonce');

            $mock_profile = [
                'skin_type'    => sanitize_key($_POST['skin_type'] ?? 'dry'),
                'skin_tone'    => sanitize_key($_POST['skin_tone'] ?? 'fair_light'),
                'skin_concern' => array_map('sanitize_key', $_POST['skin_concern'] ?? []),
            ];

            // Run mock evaluation using Model 2 rules logic
            $grouped_rules = Tvak_Product_Rule::get_all_active_grouped_by_slot();
            $sim_items = [];

            foreach ($grouped_rules as $slot_id => $rules) {
                $top_product = null;
                $max_score = -1;

                foreach ($rules as $r) {
                    $pid = (int) $r['product_id'];
                    $boost = (float) $r['priority_boost'];
                    $attrs = $r['attribute_rules'];

                    $weighted_sum = 0;
                    $total_weight = 0;

                    foreach ($attrs as $code => $rule_data) {
                        $w = $rule_data['weight'];
                        $matrix = $rule_data['match_matrix'];

                        $user_val = $mock_profile[$code] ?? null;
                        $match_val = 0;

                        if (is_array($user_val)) {
                            $matches = [];
                            foreach ($user_val as $val_item) {
                                $matches[] = isset($matrix[$val_item]) ? (float) $matrix[$val_item] : 0.0;
                            }
                            $match_val = !empty($matches) ? max($matches) : 0.0;
                        } elseif ($user_val) {
                            $match_val = isset($matrix[$user_val]) ? (float) $matrix[$user_val] : 0.0;
                        }

                        $weighted_sum += $w * $match_val;
                        $total_weight += $w;
                    }

                    $final_score = $total_weight > 0 ? min(1.0, ($weighted_sum / $total_weight) + $boost) : $boost;

                    // Sensitive skin override check
                    if (($mock_profile['skin_type'] === 'sensitive' || in_array('sensitive', $mock_profile['skin_concern'])) && get_post_meta($pid, '_tvak_contains_fragrance', true) === 'yes') {
                        $final_score = 0.0;
                    }

                    if ($final_score > $max_score) {
                        $max_score = $final_score;
                        $top_product = [
                            'product_id'   => $pid,
                            'title'        => get_the_title($pid),
                            'score'        => round($final_score * 100, 1),
                            'variation_id' => Tvak_Variant_Map::resolve_variation($pid, $mock_profile),
                        ];
                    }
                }

                if ($top_product) {
                    $sim_items[] = $top_product;
                }
            }

            $simulation_result = [
                'profile' => $mock_profile,
                'items'   => $sim_items,
            ];
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('TVAK Recommendation Engine - Rule Simulator Bench', 'tvak-beauty-kit'); ?></h1>
            <p><?php esc_html_e('Input mock customer profile vectors to simulate scoring, slotting, and variant resolution in real time.', 'tvak-beauty-kit'); ?></p>

            <div style="display: flex; gap: 20px;">
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px;">
                    <h2><?php esc_html_e('Mock Customer Profile Vector', 'tvak-beauty-kit'); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('tvak_simulate_nonce_action', 'tvak_simulate_nonce'); ?>

                        <?php foreach ($attributes as $attr) : ?>
                            <p>
                                <strong><?php echo esc_html($attr['label']); ?>:</strong><br />
                                <?php if ($attr['attribute_code'] === 'skin_concern') : ?>
                                    <?php foreach ($attr['options'] as $k => $v) : ?>
                                        <label><input type="checkbox" name="skin_concern[]" value="<?php echo esc_attr($k); ?>" /> <?php echo esc_html($v); ?></label><br />
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <select name="<?php echo esc_attr($attr['attribute_code']); ?>" style="width: 100%;">
                                        <?php foreach ($attr['options'] as $k => $v) : ?>
                                            <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </p>
                        <?php endforeach; ?>

                        <p><input type="submit" class="button button-primary button-large" value="<?php esc_attr_e('Run Recommendation Engine Simulation', 'tvak-beauty-kit'); ?>" /></p>
                    </form>
                </div>

                <div style="flex: 1.5; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px;">
                    <h2><?php esc_html_e('Simulation Evaluation Results', 'tvak-beauty-kit'); ?></h2>
                    <?php if ($simulation_result) : ?>
                        <div style="background: #e7f5ea; border: 1px solid #4ab866; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                            <strong><?php esc_html_e('Evaluated Profile:', 'tvak-beauty-kit'); ?></strong>
                            <code><?php echo esc_html(wp_json_encode($simulation_result['profile'])); ?></code>
                        </div>

                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Product', 'tvak-beauty-kit'); ?></th>
                                    <th><?php esc_html_e('Fit Score', 'tvak-beauty-kit'); ?></th>
                                    <th><?php esc_html_e('Resolved Variation ID', 'tvak-beauty-kit'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($simulation_result['items'] as $item) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($item['title']); ?></strong> (ID: <?php echo esc_html($item['product_id']); ?>)</td>
                                        <td><span class="badge" style="background:#2271b1; color:#fff; padding:3px 8px; border-radius:10px;"><?php echo esc_html($item['score']); ?>%</span></td>
                                        <td><code><?php echo esc_html($item['variation_id'] ?: 'Main Product / Simple'); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><em><?php esc_html_e('Select profile options on the left and click Run Simulation to inspect generated recommendations.', 'tvak-beauty-kit'); ?></em></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
