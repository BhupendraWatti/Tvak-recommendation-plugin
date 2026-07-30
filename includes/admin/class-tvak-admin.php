<?php
/**
 * Admin Management Controller & Interface
 *
 * Handles WordPress Dashboard menus, rule configuration forms, variant mapping UI,
 * Master Data management, and the Rule Simulator tool.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.1.0
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
        add_action('admin_post_tvak_save_master_attribute', [__CLASS__, 'handle_save_master_attribute']);
        add_action('admin_post_tvak_save_master_term', [__CLASS__, 'handle_save_master_term']);
        add_action('admin_post_tvak_delete_master_term', [__CLASS__, 'handle_delete_master_term']);
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
            __('Master Data Manager', 'tvak-beauty-kit'),
            __('Master Data', 'tvak-beauty-kit'),
            'manage_options',
            'tvak-master-data',
            [__CLASS__, 'render_master_data_page']
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
     * Render Master Data Management Page.
     */
    public static function render_master_data_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $attributes = Tvak_Master_Data::get_attributes(false);
        $edit_term_id = isset($_GET['edit_term']) ? (int) $_GET['edit_term'] : 0;
        $edit_term = null;

        if ($edit_term_id) {
            global $wpdb;
            $edit_term = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$wpdb->prefix}tvak_master_terms WHERE term_id = %d", $edit_term_id),
                ARRAY_A
            );
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('TVAK Master Data Manager', 'tvak-beauty-kit'); ?></h1>
            <p><?php esc_html_e('Manage dynamic skin types, skin tones, skin concerns, and custom attributes. These values act as the single source of truth across the Quiz UI, Engine Evaluator, Variant Resolver, and Admin Simulator.', 'tvak-beauty-kit'); ?></p>

            <?php if (isset($_GET['message'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        if ($_GET['message'] === 'attr_saved') esc_html_e('Master attribute saved successfully!', 'tvak-beauty-kit');
                        elseif ($_GET['message'] === 'term_saved') esc_html_e('Master term value saved successfully!', 'tvak-beauty-kit');
                        elseif ($_GET['message'] === 'term_deleted') esc_html_e('Master term value deleted successfully!', 'tvak-beauty-kit');
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- Left Column: Add / Edit Term Form -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px; height: fit-content;">
                    <h2><?php echo $edit_term ? esc_html__('Edit Master Option Value', 'tvak-beauty-kit') : esc_html__('Add New Master Option Value', 'tvak-beauty-kit'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="tvak_save_master_term" />
                        <?php wp_nonce_field('tvak_save_master_term_nonce', 'tvak_nonce'); ?>

                        <table class="form-table" style="width: 100%;">
                            <tr>
                                <th scope="row"><label for="attribute_code"><?php esc_html_e('Target Master Attribute', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <select name="attribute_code" id="attribute_code" required style="width: 100%;">
                                        <?php foreach ($attributes as $attr) : ?>
                                            <option value="<?php echo esc_attr($attr['attribute_code']); ?>" <?php selected($edit_term['attribute_code'] ?? '', $attr['attribute_code']); ?>>
                                                <?php echo esc_html($attr['label']); ?> (<code><?php echo esc_html($attr['attribute_code']); ?></code>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="term_slug"><?php esc_html_e('Option Machine Slug', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="text" name="term_slug" id="term_slug" value="<?php echo esc_attr($edit_term['term_slug'] ?? ''); ?>" required placeholder="e.g. rosacea, golden_tan" class="regular-text" <?php echo $edit_term ? 'readonly' : ''; ?> />
                                    <p class="description"><?php esc_html_e('Immutable machine key used internally by the engine.', 'tvak-beauty-kit'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="label"><?php esc_html_e('Display Label', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="text" name="label" id="label" value="<?php echo esc_attr($edit_term['label'] ?? ''); ?>" required placeholder="e.g. Rosacea & Redness" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="description"><?php esc_html_e('Sub-text Description', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="text" name="description" id="description" value="<?php echo esc_attr($edit_term['description'] ?? ''); ?>" placeholder="e.g. Easily irritated or prone to flushes" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="swatch_color"><?php esc_html_e('Swatch Color (HEX)', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="color" name="swatch_color" id="swatch_color" value="<?php echo esc_attr($edit_term['swatch_color'] ?? '#E8CEB8'); ?>" />
                                    <span class="description"><?php esc_html_e('Used for Skin Tone swatches in quiz card UI.', 'tvak-beauty-kit'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="sort_order"><?php esc_html_e('Sort Order', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="number" name="sort_order" id="sort_order" value="<?php echo esc_attr($edit_term['sort_order'] ?? 1); ?>" class="small-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Status', 'tvak-beauty-kit'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="is_active" value="1" <?php checked($edit_term['is_active'] ?? 1, 1); ?> /> <?php esc_html_e('Active in Engine & Quiz UI', 'tvak-beauty-kit'); ?></label>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php echo $edit_term ? esc_attr__('Update Master Option', 'tvak-beauty-kit') : esc_attr__('Save New Master Option', 'tvak-beauty-kit'); ?>" />
                            <?php if ($edit_term) : ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=tvak-master-data')); ?>" class="button button-secondary"><?php esc_html_e('Cancel Edit', 'tvak-beauty-kit'); ?></a>
                            <?php endif; ?>
                        </p>
                    </form>

                    <hr style="margin: 25px 0;" />

                    <h2><?php esc_html_e('Add New Master Attribute Group', 'tvak-beauty-kit'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="tvak_save_master_attribute" />
                        <?php wp_nonce_field('tvak_save_master_attribute_nonce', 'tvak_nonce'); ?>

                        <p>
                            <label><strong><?php esc_html_e('Attribute Code:', 'tvak-beauty-kit'); ?></strong></label><br />
                            <input type="text" name="attribute_code" placeholder="e.g. age_group" required class="regular-text" />
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Attribute Label:', 'tvak-beauty-kit'); ?></strong></label><br />
                            <input type="text" name="label" placeholder="e.g. Age Bracket" required class="regular-text" />
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Quiz Input Type:', 'tvak-beauty-kit'); ?></strong></label><br />
                            <select name="input_type">
                                <option value="single_select"><?php esc_html_e('Single Select (Radio Card)', 'tvak-beauty-kit'); ?></option>
                                <option value="multi_select"><?php esc_html_e('Multi Select (Checkbox Card)', 'tvak-beauty-kit'); ?></option>
                            </select>
                        </p>
                        <p><input type="submit" class="button button-secondary" value="<?php esc_attr_e('Create Master Attribute Group', 'tvak-beauty-kit'); ?>" /></p>
                    </form>
                </div>

                <!-- Right Column: Registered Master Data List -->
                <div style="flex: 1.5; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px;">
                    <h2><?php esc_html_e('Registered Master Attributes & Option Values', 'tvak-beauty-kit'); ?></h2>

                    <?php foreach ($attributes as $attr) : ?>
                        <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #D4AF37; padding-bottom: 8px; margin-bottom: 12px;">
                                <div>
                                    <h3 style="margin: 0; display: inline-block;"><?php echo esc_html($attr['label']); ?></h3>
                                    <code style="margin-left: 8px;">code: <?php echo esc_html($attr['attribute_code']); ?></code>
                                </div>
                                <span class="badge" style="background: #2271b1; color: #fff; padding: 2px 10px; border-radius: 12px; font-size: 11px;">
                                    <?php echo esc_html(strtoupper($attr['input_type'])); ?>
                                </span>
                            </div>

                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Order', 'tvak-beauty-kit'); ?></th>
                                        <th><?php esc_html_e('Slug', 'tvak-beauty-kit'); ?></th>
                                        <th><?php esc_html_e('Label', 'tvak-beauty-kit'); ?></th>
                                        <th><?php esc_html_e('Swatch', 'tvak-beauty-kit'); ?></th>
                                        <th><?php esc_html_e('Status', 'tvak-beauty-kit'); ?></th>
                                        <th><?php esc_html_e('Actions', 'tvak-beauty-kit'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($attr['terms'])) : ?>
                                        <?php foreach ($attr['terms'] as $t) : ?>
                                            <tr>
                                                <td><?php echo esc_html($t['sort_order']); ?></td>
                                                <td><code><?php echo esc_html($t['term_slug']); ?></code></td>
                                                <td>
                                                    <strong><?php echo esc_html($t['label']); ?></strong>
                                                    <?php if (!empty($t['description'])) : ?>
                                                        <br /><small style="color: #666;"><?php echo esc_html($t['description']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($t['swatch_color'])) : ?>
                                                        <span style="display: inline-block; width: 20px; height: 20px; background: <?php echo esc_attr($t['swatch_color']); ?>; border: 1px solid #ccc; border-radius: 50%; vertical-align: middle;"></span>
                                                    <?php else : ?>
                                                        <span style="color: #bbb;">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($t['is_active']) : ?>
                                                        <span style="color: green; font-weight: bold;">✔ Active</span>
                                                    <?php else : ?>
                                                        <span style="color: red;">✖ Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=tvak-master-data&edit_term=' . $t['term_id'])); ?>" class="button button-small button-secondary"><?php esc_html_e('Edit', 'tvak-beauty-kit'); ?></a>
                                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                                                        <input type="hidden" name="action" value="tvak_delete_master_term" />
                                                        <input type="hidden" name="term_id" value="<?php echo esc_attr($t['term_id']); ?>" />
                                                        <?php wp_nonce_field('tvak_delete_master_term_nonce', 'tvak_nonce'); ?>
                                                        <input type="submit" class="button button-small button-link-delete" value="<?php esc_attr_e('Delete', 'tvak-beauty-kit'); ?>" onclick="return confirm('Delete this master term?');" />
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <tr><td colspan="6"><?php esc_html_e('No terms defined for this attribute group yet.', 'tvak-beauty-kit'); ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle Save Master Attribute Post.
     */
    public static function handle_save_master_attribute() {
        check_admin_referer('tvak_save_master_attribute_nonce', 'tvak_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'tvak-beauty-kit'));
        }

        $code = sanitize_key($_POST['attribute_code'] ?? '');
        $label = sanitize_text_field($_POST['label'] ?? '');
        $input_type = sanitize_text_field($_POST['input_type'] ?? 'single_select');

        if ($code && $label) {
            Tvak_Master_Data::save_attribute([
                'attribute_code' => $code,
                'label'          => $label,
                'input_type'     => $input_type,
                'is_active'      => 1,
            ]);
            Tvak_Cache::invalidate_rules_cache();
        }

        wp_redirect(admin_url('admin.php?page=tvak-master-data&message=attr_saved'));
        exit;
    }

    /**
     * Handle Save Master Term Post.
     */
    public static function handle_save_master_term() {
        check_admin_referer('tvak_save_master_term_nonce', 'tvak_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'tvak-beauty-kit'));
        }

        $attr_code    = sanitize_key($_POST['attribute_code'] ?? '');
        $term_slug    = sanitize_key($_POST['term_slug'] ?? '');
        $label        = sanitize_text_field($_POST['label'] ?? '');
        $description  = sanitize_text_field($_POST['description'] ?? '');
        $swatch_color = sanitize_text_field($_POST['swatch_color'] ?? '');
        $sort_order   = (int) ($_POST['sort_order'] ?? 1);
        $is_active    = isset($_POST['is_active']) ? 1 : 0;

        if ($attr_code && $term_slug && $label) {
            Tvak_Master_Data::save_term([
                'attribute_code' => $attr_code,
                'term_slug'      => $term_slug,
                'label'          => $label,
                'description'    => $description,
                'swatch_color'   => $swatch_color,
                'sort_order'     => $sort_order,
                'is_active'      => $is_active,
            ]);

            // Sync with legacy attribute table
            $master_attr = Tvak_Master_Data::get_attribute_by_code($attr_code, false);
            if ($master_attr) {
                Tvak_Attribute::save([
                    'attribute_code' => $attr_code,
                    'label'          => $master_attr['label'],
                    'category'       => $master_attr['category'],
                    'options'        => $master_attr['options'],
                ]);
            }

            Tvak_Cache::invalidate_rules_cache();
        }

        wp_redirect(admin_url('admin.php?page=tvak-master-data&message=term_saved'));
        exit;
    }

    /**
     * Handle Delete Master Term Post.
     */
    public static function handle_delete_master_term() {
        check_admin_referer('tvak_delete_master_term_nonce', 'tvak_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'tvak-beauty-kit'));
        }

        $term_id = (int) ($_POST['term_id'] ?? 0);
        if ($term_id) {
            Tvak_Master_Data::delete_term($term_id);
            Tvak_Cache::invalidate_rules_cache();
        }

        wp_redirect(admin_url('admin.php?page=tvak-master-data&message=term_deleted'));
        exit;
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
        $attributes = Tvak_Master_Data::get_attributes(true);

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
                            $terms     = $attr['terms'] ?? [];
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
                                            <?php foreach ($terms as $term) :
                                                $opt_key   = $term['term_slug'];
                                                $opt_label = $term['label'];
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
        $attributes        = Tvak_Master_Data::get_attributes(true);

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
                                            <?php foreach ($attr['terms'] as $term) : ?>
                                                <option value="<?php echo esc_attr($term['term_slug']); ?>"><?php echo esc_html($term['label']); ?></option>
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

        $attributes = Tvak_Master_Data::get_attributes(true);
        $simulation_result = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tvak_simulate_nonce'])) {
            check_admin_referer('tvak_simulate_nonce_action', 'tvak_simulate_nonce');

            $mock_profile_raw = [];
            foreach ($attributes as $attr) {
                $code = $attr['attribute_code'];
                if (isset($_POST[$code])) {
                    if (is_array($_POST[$code])) {
                        $mock_profile_raw[$code] = array_map('sanitize_key', $_POST[$code]);
                    } else {
                        $mock_profile_raw[$code] = sanitize_key($_POST[$code]);
                    }
                }
            }

            $profile_obj = new Tvak_User_Profile($mock_profile_raw);
            $mock_profile = $profile_obj->to_array();

            $orchestrator = new Tvak_Engine_Orchestrator();
            $eval_result  = $orchestrator->recommend($profile_obj);

            $sim_items = [];
            if (!empty($eval_result['items'])) {
                foreach ($eval_result['items'] as $item) {
                    $sim_items[] = [
                        'product_id'   => $item['product_id'],
                        'title'        => $item['title'],
                        'score'        => $item['score_pct'],
                        'variation_id' => $item['variation_id'],
                        'shade_name'   => $item['shade_name'],
                        'rationale'    => $item['rationale'],
                    ];
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
                                <?php if ($attr['input_type'] === 'multi_select') : ?>
                                    <?php foreach ($attr['terms'] as $term) : ?>
                                        <label><input type="checkbox" name="<?php echo esc_attr($attr['attribute_code']); ?>[]" value="<?php echo esc_attr($term['term_slug']); ?>" /> <?php echo esc_html($term['label']); ?></label><br />
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <select name="<?php echo esc_attr($attr['attribute_code']); ?>" style="width: 100%;">
                                        <?php foreach ($attr['terms'] as $term) : ?>
                                            <option value="<?php echo esc_attr($term['term_slug']); ?>"><?php echo esc_html($term['label']); ?></option>
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
                            <strong><?php esc_html_e('Evaluated Profile Vector:', 'tvak-beauty-kit'); ?></strong>
                            <code><?php echo esc_html(wp_json_encode($simulation_result['profile'])); ?></code>
                        </div>

                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Product', 'tvak-beauty-kit'); ?></th>
                                    <th><?php esc_html_e('Fit Score', 'tvak-beauty-kit'); ?></th>
                                    <th><?php esc_html_e('Resolved Shade / SKU', 'tvak-beauty-kit'); ?></th>
                                    <th><?php esc_html_e('Rationale', 'tvak-beauty-kit'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($simulation_result['items'] as $item) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($item['title']); ?></strong> (ID: <?php echo esc_html($item['product_id']); ?>)</td>
                                        <td><span class="badge" style="background:#2271b1; color:#fff; padding:3px 8px; border-radius:10px;"><?php echo esc_html($item['score']); ?>%</span></td>
                                        <td>
                                            <code><?php echo esc_html($item['variation_id'] ?: 'Main Product'); ?></code>
                                            <?php if (!empty($item['shade_name'])) : ?>
                                                <br /><small><?php echo esc_html($item['shade_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?php echo esc_html($item['rationale']); ?></small></td>
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
