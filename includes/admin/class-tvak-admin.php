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
        add_action('admin_post_tvak_save_product_shade', [__CLASS__, 'handle_save_product_shade']);
        add_action('admin_post_tvak_delete_product_shade', [__CLASS__, 'handle_delete_product_shade']);
        add_action('admin_post_tvak_toggle_product_has_shades', [__CLASS__, 'handle_toggle_product_has_shades']);
        add_action('admin_post_tvak_save_bundle_discounts', [__CLASS__, 'handle_save_bundle_discounts']);
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
            __('Product Shades Manager', 'tvak-beauty-kit'),
            __('Product Shades', 'tvak-beauty-kit'),
            'manage_options',
            'tvak-shades',
            [__CLASS__, 'render_shades_page']
        );

        add_submenu_page(
            'tvak-engine',
            __('Rule Simulator', 'tvak-beauty-kit'),
            __('Rule Simulator', 'tvak-beauty-kit'),
            'manage_options',
            'tvak-simulator',
            [__CLASS__, 'render_simulator_page']
        );

        add_submenu_page(
            'tvak-engine',
            __('Bundle Discount Settings', 'tvak-beauty-kit'),
            __('Bundle Discount', 'tvak-beauty-kit'),
            'manage_options',
            'tvak-bundle-discount',
            [__CLASS__, 'render_bundle_discount_page']
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

            // Sync legacy tvak_attribute_registry row (label + flat options_json only).
            // We use a direct DB update instead of Tvak_Attribute::save() to avoid
            // the delegation path that re-saves all terms with reset sequential sort_order.
            $master_attr = Tvak_Master_Data::get_attribute_by_code($attr_code, false);
            if ($master_attr) {
                global $wpdb;
                $legacy_table = $wpdb->prefix . 'tvak_attribute_registry';
                $existing_legacy_id = $wpdb->get_var(
                    $wpdb->prepare("SELECT attribute_id FROM {$legacy_table} WHERE attribute_code = %s", $attr_code)
                );
                if ($existing_legacy_id) {
                    $wpdb->update(
                        $legacy_table,
                        [
                            'label'        => $master_attr['label'],
                            'category'     => $master_attr['category'],
                            'options_json' => wp_json_encode($master_attr['options']),
                        ],
                        ['attribute_id' => $existing_legacy_id],
                        ['%s', '%s', '%s'],
                        ['%d']
                    );
                } else {
                    $wpdb->insert(
                        $legacy_table,
                        [
                            'attribute_code' => $attr_code,
                            'label'          => $master_attr['label'],
                            'category'       => $master_attr['category'],
                            'options_json'   => wp_json_encode($master_attr['options']),
                        ],
                        ['%s', '%s', '%s', '%s']
                    );
                }
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
                                <th scope="row"><label for="min_score_threshold"><?php esc_html_e('Minimum Score Threshold', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="number" step="0.05" min="0" max="1" name="min_score_threshold" id="min_score_threshold" value="<?php echo esc_attr($existing_rule['min_score_threshold'] ?? ''); ?>" class="small-text" placeholder="0.20" />
                                    <p class="description">
                                        <?php esc_html_e('Product-specific minimum fit score required to include this item in a kit (overrides global 0.20). Leave blank to use global default.', 'tvak-beauty-kit'); ?>
                                        <br /><strong style="color:#c0392b;"><?php esc_html_e('Clinical tip: Set to 0.50+ for formulas contra-indicated for certain skin types (e.g. mattifying setting spray should not appear for Dry skin profiles).', 'tvak-beauty-kit'); ?></strong>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Fragrance / Alcohol Safety Flags', 'tvak-beauty-kit'); ?></th>
                                <td>
                                    <fieldset>
                                        <legend class="screen-reader-text"><?php esc_html_e('Sensitive skin safety flags', 'tvak-beauty-kit'); ?></legend>
                                        <?php
                                        $fragrance_val = get_post_meta($selected_product_id, '_tvak_contains_fragrance', true);
                                        $alcohol_val   = get_post_meta($selected_product_id, '_tvak_contains_alcohol', true);
                                        ?>
                                        <label><strong><?php esc_html_e('Contains Fragrance:', 'tvak-beauty-kit'); ?></strong></label><br />
                                        <label><input type="radio" name="tvak_contains_fragrance" value="yes" <?php checked($fragrance_val, 'yes'); ?> /> <?php esc_html_e('Yes — disqualifies from Sensitive Skin kits', 'tvak-beauty-kit'); ?></label>&nbsp;&nbsp;
                                        <label><input type="radio" name="tvak_contains_fragrance" value="no" <?php checked($fragrance_val, 'no'); ?> /> <?php esc_html_e('No — fragrance-free / safe', 'tvak-beauty-kit'); ?></label>
                                        <?php if ($fragrance_val === '') : ?>
                                            <span style="color:#c0392b; margin-left: 8px;">⚠ <?php esc_html_e('Not configured — set this flag to activate the sensitive skin safety gate.', 'tvak-beauty-kit'); ?></span>
                                        <?php endif; ?>
                                        <br /><br />
                                        <label><strong><?php esc_html_e('Contains Alcohol:', 'tvak-beauty-kit'); ?></strong></label><br />
                                        <label><input type="radio" name="tvak_contains_alcohol" value="yes" <?php checked($alcohol_val, 'yes'); ?> /> <?php esc_html_e('Yes — disqualifies from Sensitive Skin kits', 'tvak-beauty-kit'); ?></label>&nbsp;&nbsp;
                                        <label><input type="radio" name="tvak_contains_alcohol" value="no" <?php checked($alcohol_val, 'no'); ?> /> <?php esc_html_e('No — alcohol-free / safe', 'tvak-beauty-kit'); ?></label>
                                        <?php if ($alcohol_val === '') : ?>
                                            <span style="color:#c0392b; margin-left: 8px;">⚠ <?php esc_html_e('Not configured — set this flag to activate the sensitive skin safety gate.', 'tvak-beauty-kit'); ?></span>
                                        <?php endif; ?>
                                    </fieldset>
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

        $product_id           = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $slot_id              = isset($_POST['slot_id']) ? (int) $_POST['slot_id'] : 0;
        $priority_boost       = isset($_POST['priority_boost']) ? (float) $_POST['priority_boost'] : 0.0;
        $is_active            = isset($_POST['is_active']) ? 1 : 0;
        $attribute_rules      = $_POST['attribute_rules'] ?? [];
        $min_score_threshold  = (isset($_POST['min_score_threshold']) && $_POST['min_score_threshold'] !== '') ? (float) $_POST['min_score_threshold'] : null;

        if ($product_id && $slot_id) {
            Tvak_Product_Rule::save_rule($product_id, $slot_id, $priority_boost, $is_active, $attribute_rules, $min_score_threshold);
            Tvak_Cache::invalidate_rules_cache();
        }

        // Persist safety flags for sensitive-skin guardrail (Issue #8)
        if ($product_id) {
            $allowed_values = ['yes', 'no'];

            $fragrance_val = sanitize_key($_POST['tvak_contains_fragrance'] ?? '');
            if (in_array($fragrance_val, $allowed_values, true)) {
                update_post_meta($product_id, '_tvak_contains_fragrance', $fragrance_val);
            }

            $alcohol_val = sanitize_key($_POST['tvak_contains_alcohol'] ?? '');
            if (in_array($alcohol_val, $allowed_values, true)) {
                update_post_meta($product_id, '_tvak_contains_alcohol', $alcohol_val);
            }
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

    /**
     * Render Product Shades Manager Page.
     */
    public static function render_shades_page() {
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
        $has_shades          = $selected_product_id ? Tvak_Product_Shade::get_product_has_shades($selected_product_id) : false;
        $shades              = $selected_product_id ? Tvak_Product_Shade::get_shades_by_product($selected_product_id) : [];

        $edit_shade_id = isset($_GET['edit_shade']) ? (int) $_GET['edit_shade'] : 0;
        $edit_shade    = null;
        if ($edit_shade_id) {
            global $wpdb;
            $edit_shade = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$wpdb->prefix}tvak_product_shades WHERE shade_id = %d", $edit_shade_id),
                ARRAY_A
            );
        }

        $variations = [];
        if ($selected_product_id && class_exists('WC_Product')) {
            $p_obj = wc_get_product($selected_product_id);
            if ($p_obj && $p_obj->is_type('variable')) {
                $variations = $p_obj->get_available_variations();
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('TVAK Recommendation Engine - Product Shades Manager', 'tvak-beauty-kit'); ?></h1>
            <p><?php esc_html_e('Enable visual shade variations per product and configure visual hex colors, variation mappings, custom pricing, and stock status.', 'tvak-beauty-kit'); ?></p>

            <?php if (isset($_GET['message'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        if ($_GET['message'] === 'shade_saved') esc_html_e('Product shade saved successfully!', 'tvak-beauty-kit');
                        elseif ($_GET['message'] === 'shade_deleted') esc_html_e('Product shade deleted successfully!', 'tvak-beauty-kit');
                        elseif ($_GET['message'] === 'toggled') esc_html_e('Product shade mode updated!', 'tvak-beauty-kit');
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="postbox" style="padding: 20px; background: #fff; margin-top: 20px;">
                <form method="get" action="">
                    <input type="hidden" name="page" value="tvak-shades" />
                    <label for="product_select"><strong><?php esc_html_e('Select Product to Manage Shades:', 'tvak-beauty-kit'); ?></strong></label>
                    <select name="product_id" id="product_select" onchange="this.form.submit()" style="min-width: 350px; margin-left: 10px;">
                        <?php foreach ($wc_products as $p) : ?>
                            <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($selected_product_id, $p->ID); ?>>
                                <?php echo esc_html($p->post_title); ?> (ID: <?php echo esc_html($p->ID); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($selected_product_id) : ?>
                <div class="postbox" style="padding: 20px; background: #fff; border-left: 4px solid #D4AF37;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="tvak_toggle_product_has_shades" />
                        <input type="hidden" name="product_id" value="<?php echo esc_attr($selected_product_id); ?>" />
                        <?php wp_nonce_field('tvak_toggle_product_has_shades_nonce', 'tvak_nonce'); ?>

                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <h2 style="margin: 0;"><?php esc_html_e('Shade Mode Configuration for:', 'tvak-beauty-kit'); ?> <em><?php echo esc_html(get_the_title($selected_product_id)); ?></em></h2>
                                <p style="margin: 5px 0 0 0; color: #666;"><?php esc_html_e('If enabled, visual color swatches will be displayed on the recommendation card in quiz results.', 'tvak-beauty-kit'); ?></p>
                            </div>
                            <div>
                                <label style="font-size: 16px; font-weight: bold;">
                                    <input type="checkbox" name="has_shades" value="1" <?php checked($has_shades); ?> onchange="this.form.submit()" style="width: 20px; height: 20px; vertical-align: middle;" />
                                    <?php esc_html_e('Enable Product Shades / Variations', 'tvak-beauty-kit'); ?>
                                </label>
                            </div>
                        </div>
                    </form>
                </div>

                <div style="display: flex; gap: 20px; margin-top: 20px;">
                    <!-- Add / Edit Shade Form -->
                    <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px; height: fit-content;">
                        <h2><?php echo $edit_shade ? esc_html__('Edit Product Shade', 'tvak-beauty-kit') : esc_html__('Add New Product Shade', 'tvak-beauty-kit'); ?></h2>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="tvak_save_product_shade" />
                            <input type="hidden" name="product_id" value="<?php echo esc_attr($selected_product_id); ?>" />
                            <?php if ($edit_shade) : ?>
                                <input type="hidden" name="shade_id" value="<?php echo esc_attr($edit_shade['shade_id']); ?>" />
                            <?php endif; ?>
                            <?php wp_nonce_field('tvak_save_product_shade_nonce', 'tvak_nonce'); ?>

                            <table class="form-table" style="width: 100%;">
                                <tr>
                                    <th scope="row"><label for="shade_name"><?php esc_html_e('Shade Name', 'tvak-beauty-kit'); ?></label></th>
                                    <td>
                                        <input type="text" name="shade_name" id="shade_name" value="<?php echo esc_attr($edit_shade['shade_name'] ?? ''); ?>" required placeholder="e.g. Choco Suede, Ivory Velouté, Onyx Black" class="regular-text" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="shade_hex"><?php esc_html_e('Visual Color (HEX)', 'tvak-beauty-kit'); ?></label></th>
                                    <td>
                                        <input type="color" name="shade_hex" id="shade_hex" value="<?php echo esc_attr($edit_shade['shade_hex'] ?? '#D4AF37'); ?>" style="vertical-align: middle; height: 35px; width: 60px;" />
                                        <input type="text" name="shade_hex_text" value="<?php echo esc_attr($edit_shade['shade_hex'] ?? '#D4AF37'); ?>" style="width: 100px; margin-left: 8px;" onchange="document.getElementById('shade_hex').value=this.value" />
                                        <p class="description"><?php esc_html_e('Exact visual shade color rendered on recommendation card swatches.', 'tvak-beauty-kit'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="variation_id"><?php esc_html_e('WooCommerce Variation SKU', 'tvak-beauty-kit'); ?></label></th>
                                    <td>
                                        <select name="variation_id" id="variation_id" style="width: 100%;">
                                            <option value=""><?php esc_html_e('-- Main Product / Unlinked --', 'tvak-beauty-kit'); ?></option>
                                            <?php if (!empty($variations)) : ?>
                                                <?php foreach ($variations as $v) : ?>
                                                    <option value="<?php echo esc_attr($v['variation_id']); ?>" <?php selected($edit_shade['variation_id'] ?? 0, $v['variation_id']); ?>>
                                                        <?php echo esc_html(implode(', ', $v['attributes'])); ?> (ID: <?php echo esc_html($v['variation_id']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="price"><?php esc_html_e('Price Override', 'tvak-beauty-kit'); ?></label></th>
                                    <td>
                                        <input type="number" step="0.01" min="0" name="price" id="price" value="<?php echo esc_attr($edit_shade['price'] ?? ''); ?>" placeholder="e.g. 599.00" class="regular-text" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="sort_order"><?php esc_html_e('Sort Order', 'tvak-beauty-kit'); ?></label></th>
                                    <td>
                                        <input type="number" name="sort_order" id="sort_order" value="<?php echo esc_attr($edit_shade['sort_order'] ?? 1); ?>" class="small-text" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Stock Status', 'tvak-beauty-kit'); ?></th>
                                    <td>
                                        <label><input type="checkbox" name="is_in_stock" value="1" <?php checked($edit_shade['is_in_stock'] ?? 1, 1); ?> /> <?php esc_html_e('In Stock', 'tvak-beauty-kit'); ?></label>
                                    </td>
                                </tr>
                            </table>

                            <p class="submit">
                                <input type="submit" class="button button-primary" value="<?php echo $edit_shade ? esc_attr__('Update Shade', 'tvak-beauty-kit') : esc_attr__('Add Shade', 'tvak-beauty-kit'); ?>" />
                                <?php if ($edit_shade) : ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=tvak-shades&product_id=' . $selected_product_id)); ?>" class="button button-secondary"><?php esc_html_e('Cancel', 'tvak-beauty-kit'); ?></a>
                                <?php endif; ?>
                            </p>
                        </form>
                    </div>

                    <!-- Configured Shades List Table -->
                    <div style="flex: 1.5; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px;">
                        <h2><?php esc_html_e('Configured Shades for this Product', 'tvak-beauty-kit'); ?></h2>

                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Order', 'tvak-beauty-kit'); ?></th>
                                    <th><?php esc_html_e('Visual Swatch', 'tvak-beauty-kit'); ?></th>
                                    <th><?php esc_html_e('Shade Name', 'tvak-beauty-kit'); ?></th>
                                    <th><?php esc_html_e('HEX Code', 'tvak-beauty-kit'); ?></th>
                                    <th><?php esc_html_e('Stock', 'tvak-beauty-kit'); ?></th>
                                    <th><?php esc_html_e('Actions', 'tvak-beauty-kit'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($shades)) : ?>
                                    <?php foreach ($shades as $sh) : ?>
                                        <tr>
                                            <td><?php echo esc_html($sh['sort_order']); ?></td>
                                            <td>
                                                <span style="display: inline-block; width: 28px; height: 28px; background: <?php echo esc_attr($sh['shade_hex']); ?>; border: 2px solid #D4AF37; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.2);"></span>
                                            </td>
                                            <td><strong><?php echo esc_html($sh['shade_name']); ?></strong></td>
                                            <td><code><?php echo esc_html($sh['shade_hex']); ?></code></td>
                                            <td>
                                                <?php if ($sh['is_in_stock']) : ?>
                                                    <span style="color: green; font-weight: bold;">✔ In Stock</span>
                                                <?php else : ?>
                                                    <span style="color: red; font-weight: bold;">✖ Out of Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=tvak-shades&product_id=' . $selected_product_id . '&edit_shade=' . $sh['shade_id'])); ?>" class="button button-small button-secondary"><?php esc_html_e('Edit', 'tvak-beauty-kit'); ?></a>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                                                    <input type="hidden" name="action" value="tvak_delete_product_shade" />
                                                    <input type="hidden" name="shade_id" value="<?php echo esc_attr($sh['shade_id']); ?>" />
                                                    <input type="hidden" name="product_id" value="<?php echo esc_attr($selected_product_id); ?>" />
                                                    <?php wp_nonce_field('tvak_delete_product_shade_nonce', 'tvak_nonce'); ?>
                                                    <input type="submit" class="button button-small button-link-delete" value="<?php esc_attr_e('Delete', 'tvak-beauty-kit'); ?>" onclick="return confirm('Delete this shade?');" />
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr><td colspan="6"><?php esc_html_e('No custom shades configured for this product yet.', 'tvak-beauty-kit'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle Save Product Shade Post.
     */
    public static function handle_save_product_shade() {
        check_admin_referer('tvak_save_product_shade_nonce', 'tvak_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'tvak-beauty-kit'));
        }

        $product_id   = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $shade_id     = isset($_POST['shade_id']) ? (int) $_POST['shade_id'] : 0;
        $shade_name   = sanitize_text_field($_POST['shade_name'] ?? '');
        $shade_hex    = sanitize_text_field($_POST['shade_hex'] ?? ($_POST['shade_hex_text'] ?? '#D4AF37'));
        $variation_id = !empty($_POST['variation_id']) ? (int) $_POST['variation_id'] : null;
        $price        = isset($_POST['price']) && $_POST['price'] !== '' ? (float) $_POST['price'] : null;
        $sort_order   = (int) ($_POST['sort_order'] ?? 1);
        $is_in_stock  = isset($_POST['is_in_stock']) ? 1 : 0;

        if ($product_id && $shade_name) {
            Tvak_Product_Shade::save_shade([
                'shade_id'     => $shade_id,
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'shade_name'   => $shade_name,
                'shade_hex'    => $shade_hex,
                'price'        => $price,
                'is_in_stock'  => $is_in_stock,
                'sort_order'   => $sort_order,
            ]);

            // Ensure product has shades mode is set to enabled
            Tvak_Product_Shade::set_product_has_shades($product_id, true);
            Tvak_Cache::invalidate_rules_cache();
        }

        wp_redirect(admin_url('admin.php?page=tvak-shades&product_id=' . $product_id . '&message=shade_saved'));
        exit;
    }

    /**
     * Handle Delete Product Shade Post.
     */
    public static function handle_delete_product_shade() {
        check_admin_referer('tvak_delete_product_shade_nonce', 'tvak_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'tvak-beauty-kit'));
        }

        $shade_id   = isset($_POST['shade_id']) ? (int) $_POST['shade_id'] : 0;
        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

        if ($shade_id) {
            Tvak_Product_Shade::delete_shade($shade_id);
            Tvak_Cache::invalidate_rules_cache();
        }

        wp_redirect(admin_url('admin.php?page=tvak-shades&product_id=' . $product_id . '&message=shade_deleted'));
        exit;
    }

    /**
     * Handle Toggle Product Has Shades Post.
     */
    public static function handle_toggle_product_has_shades() {
        check_admin_referer('tvak_toggle_product_has_shades_nonce', 'tvak_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'tvak-beauty-kit'));
        }

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $has_shades = isset($_POST['has_shades']) ? true : false;

        if ($product_id) {
            Tvak_Product_Shade::set_product_has_shades($product_id, $has_shades);
            Tvak_Cache::invalidate_rules_cache();
        }

        wp_redirect(admin_url('admin.php?page=tvak-shades&product_id=' . $product_id . '&message=toggled'));
        exit;
    }

    /**
     * Render Bundle Discount Settings Page.
     *
     * Allows admin to configure tiered bundle discount thresholds without
     * touching any code. Settings propagate live to the recommendation engine
     * API response and the frontend kit builder UI.
     */
    public static function render_bundle_discount_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $discounts = get_option('tvak_bundle_discounts', [
            'tier_1_min' => 2, 'tier_1_pct' => 10,
            'tier_2_min' => 3, 'tier_2_pct' => 15,
            'tier_3_min' => 5, 'tier_3_pct' => 20,
        ]);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('TVAK Recommendation Engine – Bundle Discount Settings', 'tvak-beauty-kit'); ?></h1>
            <p><?php esc_html_e('Configure tiered kit bundle discounts. When a customer selects the minimum number of items, the configured discount % is automatically displayed on the kit builder UI. Changes take effect immediately — no code edit required.', 'tvak-beauty-kit'); ?></p>

            <?php if (isset($_GET['message']) && $_GET['message'] === 'saved') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Bundle discount settings saved successfully! The recommendation engine API and frontend UI are now using the updated tiers.', 'tvak-beauty-kit'); ?></p>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 30px; margin-top: 20px;">

                <!-- Settings Form -->
                <div style="flex: 1; background: #fff; padding: 25px; border: 1px solid #ccc; border-radius: 6px; height: fit-content;">
                    <h2 style="margin-top: 0; border-bottom: 2px solid #D4AF37; padding-bottom: 10px;"><?php esc_html_e('Discount Tier Configuration', 'tvak-beauty-kit'); ?></h2>
                    <p style="color: #666; font-size: 13px;"><?php esc_html_e('Set up to 3 tiers. The highest matching tier is applied. Set Discount % to 0 to disable a tier.', 'tvak-beauty-kit'); ?></p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="tvak_save_bundle_discounts" />
                        <?php wp_nonce_field('tvak_save_bundle_discounts_nonce', 'tvak_nonce'); ?>

                        <table class="form-table" style="width: 100%;">

                            <!-- Tier 1 -->
                            <tr>
                                <td colspan="2">
                                    <h3 style="margin: 10px 0 5px; color: #D4AF37;">✦ <?php esc_html_e('Tier 1 — Entry Bundle', 'tvak-beauty-kit'); ?></h3>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tier_1_min"><?php esc_html_e('Minimum Items Selected', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="number" name="tier_1_min" id="tier_1_min" min="1" max="20"
                                           value="<?php echo esc_attr($discounts['tier_1_min'] ?? 2); ?>" class="small-text" />
                                    <span class="description">&nbsp;<?php esc_html_e('items or more in kit', 'tvak-beauty-kit'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tier_1_pct"><?php esc_html_e('Discount Percentage (%)', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="number" name="tier_1_pct" id="tier_1_pct" min="0" max="100"
                                           value="<?php echo esc_attr($discounts['tier_1_pct'] ?? 10); ?>" class="small-text" />
                                    <span class="description">&nbsp;% <?php esc_html_e('off subtotal. Set 0 to disable this tier.', 'tvak-beauty-kit'); ?></span>
                                </td>
                            </tr>

                            <tr><td colspan="2"><hr style="border-color:#eee;" /></td></tr>

                            <!-- Tier 2 -->
                            <tr>
                                <td colspan="2">
                                    <h3 style="margin: 10px 0 5px; color: #D4AF37;">✦✦ <?php esc_html_e('Tier 2 — Mid Bundle', 'tvak-beauty-kit'); ?></h3>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tier_2_min"><?php esc_html_e('Minimum Items Selected', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="number" name="tier_2_min" id="tier_2_min" min="1" max="20"
                                           value="<?php echo esc_attr($discounts['tier_2_min'] ?? 3); ?>" class="small-text" />
                                    <span class="description">&nbsp;<?php esc_html_e('items or more in kit', 'tvak-beauty-kit'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tier_2_pct"><?php esc_html_e('Discount Percentage (%)', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="number" name="tier_2_pct" id="tier_2_pct" min="0" max="100"
                                           value="<?php echo esc_attr($discounts['tier_2_pct'] ?? 15); ?>" class="small-text" />
                                    <span class="description">&nbsp;% <?php esc_html_e('off subtotal. Set 0 to disable this tier.', 'tvak-beauty-kit'); ?></span>
                                </td>
                            </tr>

                            <tr><td colspan="2"><hr style="border-color:#eee;" /></td></tr>

                            <!-- Tier 3 -->
                            <tr>
                                <td colspan="2">
                                    <h3 style="margin: 10px 0 5px; color: #D4AF37;">✦✦✦ <?php esc_html_e('Tier 3 — Full Kit Bundle', 'tvak-beauty-kit'); ?></h3>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tier_3_min"><?php esc_html_e('Minimum Items Selected', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="number" name="tier_3_min" id="tier_3_min" min="1" max="20"
                                           value="<?php echo esc_attr($discounts['tier_3_min'] ?? 5); ?>" class="small-text" />
                                    <span class="description">&nbsp;<?php esc_html_e('items or more in kit', 'tvak-beauty-kit'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tier_3_pct"><?php esc_html_e('Discount Percentage (%)', 'tvak-beauty-kit'); ?></label></th>
                                <td>
                                    <input type="number" name="tier_3_pct" id="tier_3_pct" min="0" max="100"
                                           value="<?php echo esc_attr($discounts['tier_3_pct'] ?? 20); ?>" class="small-text" />
                                    <span class="description">&nbsp;% <?php esc_html_e('off subtotal. Set 0 to disable this tier.', 'tvak-beauty-kit'); ?></span>
                                </td>
                            </tr>

                        </table>

                        <p class="submit">
                            <input type="submit" class="button button-primary button-large"
                                   value="<?php esc_attr_e('Save Bundle Discount Settings', 'tvak-beauty-kit'); ?>" />
                        </p>
                    </form>
                </div>

                <!-- How It Works Panel -->
                <div style="flex: 1; background: #fff; padding: 25px; border: 1px solid #ccc; border-radius: 6px; height: fit-content;">
                    <h2 style="margin-top: 0; border-bottom: 2px solid #D4AF37; padding-bottom: 10px;"><?php esc_html_e('How Bundle Discounts Work', 'tvak-beauty-kit'); ?></h2>

                    <div style="background: #f9f4e8; border-left: 4px solid #D4AF37; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                        <strong><?php esc_html_e('Live Preview — Current Tiers:', 'tvak-beauty-kit'); ?></strong>
                        <ul style="margin: 10px 0 0 15px;">
                            <li><?php printf(
                                esc_html__('Select %d+ items → %d%% discount', 'tvak-beauty-kit'),
                                $discounts['tier_1_min'] ?? 2,
                                $discounts['tier_1_pct'] ?? 10
                            ); ?></li>
                            <li><?php printf(
                                esc_html__('Select %d+ items → %d%% discount', 'tvak-beauty-kit'),
                                $discounts['tier_2_min'] ?? 3,
                                $discounts['tier_2_pct'] ?? 15
                            ); ?></li>
                            <li><?php printf(
                                esc_html__('Select %d+ items → %d%% discount', 'tvak-beauty-kit'),
                                $discounts['tier_3_min'] ?? 5,
                                $discounts['tier_3_pct'] ?? 20
                            ); ?></li>
                        </ul>
                    </div>

                    <h3><?php esc_html_e('How the Engine Applies Discounts', 'tvak-beauty-kit'); ?></h3>
                    <ol>
                        <li style="margin-bottom: 8px;"><?php esc_html_e('Customer takes the quiz and receives kit recommendations.', 'tvak-beauty-kit'); ?></li>
                        <li style="margin-bottom: 8px;"><?php esc_html_e('As they select/deselect items, the highest matching tier is applied automatically.', 'tvak-beauty-kit'); ?></li>
                        <li style="margin-bottom: 8px;"><?php esc_html_e('The discount badge and total price update live on the frontend.', 'tvak-beauty-kit'); ?></li>
                        <li style="margin-bottom: 8px;"><?php esc_html_e('The discounted price is shown visually. The actual WooCommerce cart price reflects the standard product price — apply a WC coupon for backend enforcement if needed.', 'tvak-beauty-kit'); ?></li>
                    </ol>

                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; border-radius: 4px; margin-top: 15px;">
                        <strong><?php esc_html_e('⚠️ Note on Shades for Lipstick & BB Cream:', 'tvak-beauty-kit'); ?></strong>
                        <p style="margin: 8px 0 0;"><?php esc_html_e('If Lipstick or BB Cream are not showing shade swatches in the quiz results, it is because these products are "Simple" products in WooCommerce (no color variations). To enable shade swatches, either:', 'tvak-beauty-kit'); ?></p>
                        <ol style="margin: 8px 0 0 15px;">
                            <li><?php esc_html_e('Go to WooCommerce → Products → Edit the product → Change type to "Variable" and add color variations, OR', 'tvak-beauty-kit'); ?></li>
                            <li><?php esc_html_e('Go to TVAK Engine → Product Shades → Select the product → Enable Shades → Add shade entries manually.', 'tvak-beauty-kit'); ?></li>
                        </ol>
                        <p style="margin: 8px 0 0; color: #666; font-size: 12px;"><?php esc_html_e('Eyeliner shows shades automatically because it is a WooCommerce Variable product with color variations already configured.', 'tvak-beauty-kit'); ?></p>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Handle Save Bundle Discount Settings POST.
     *
     * Validates, sanitizes, and persists tier configuration to the
     * 'tvak_bundle_discounts' WordPress option. Also invalidates the
     * recommendation engine response cache so changes take effect immediately.
     */
    public static function handle_save_bundle_discounts() {
        check_admin_referer('tvak_save_bundle_discounts_nonce', 'tvak_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'tvak-beauty-kit'));
        }

        $tier_1_min = max(1, (int) ($_POST['tier_1_min'] ?? 2));
        $tier_1_pct = min(100, max(0, (int) ($_POST['tier_1_pct'] ?? 10)));
        $tier_2_min = max(1, (int) ($_POST['tier_2_min'] ?? 3));
        $tier_2_pct = min(100, max(0, (int) ($_POST['tier_2_pct'] ?? 15)));
        $tier_3_min = max(1, (int) ($_POST['tier_3_min'] ?? 5));
        $tier_3_pct = min(100, max(0, (int) ($_POST['tier_3_pct'] ?? 20)));

        update_option('tvak_bundle_discounts', [
            'tier_1_min' => $tier_1_min,
            'tier_1_pct' => $tier_1_pct,
            'tier_2_min' => $tier_2_min,
            'tier_2_pct' => $tier_2_pct,
            'tier_3_min' => $tier_3_min,
            'tier_3_pct' => $tier_3_pct,
        ]);

        // Flush recommendation cache so new tiers take effect immediately
        if (class_exists('Tvak_Cache')) {
            Tvak_Cache::invalidate_rules_cache();
        }

        wp_redirect(admin_url('admin.php?page=tvak-bundle-discount&message=saved'));
        exit;
    }
}

