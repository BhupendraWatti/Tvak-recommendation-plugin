<?php
/**
 * Admin Management Controller for TVAK Custom Hamper Builder.
 *
 * @package TVAK_Custom_Hamper_Builder
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Custom_Hamper_Admin {

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu_pages']);
        add_action('admin_post_tvak_save_hamper', [__CLASS__, 'handle_save_hamper']);
    }

    public static function register_menu_pages(): void {
        add_menu_page(
            __('TVAK Custom Hampers', 'tvak-custom-hamper-builder'),
            __('Custom Hampers', 'tvak-custom-hamper-builder'),
            'manage_options',
            'tvak-custom-hampers',
            [__CLASS__, 'render_hampers_page'],
            'dashicons-gift',
            59
        );
    }

    public static function render_hampers_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $wc_products = get_posts([
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft'],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $selected_product_id = isset($_GET['hamper_product_id'])
            ? (int) $_GET['hamper_product_id']
            : (isset($wc_products[0]) ? (int) $wc_products[0]->ID : 0);

        $hamper = ($selected_product_id && class_exists('Tvak_Custom_Hamper'))
            ? Tvak_Custom_Hamper::get_by_product_id($selected_product_id, false)
            : null;

        $existing_items = [];
        if ($hamper) {
            foreach (Tvak_Custom_Hamper::get_items((int) $hamper['hamper_id']) as $item) {
                $existing_items[(int) $item['product_id']] = $item;
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('TVAK Custom Hamper Builder', 'tvak-custom-hamper-builder'); ?></h1>
            <p><?php esc_html_e('Designate a WooCommerce product as a custom hamper shell, then select which catalog products customers can include.', 'tvak-custom-hamper-builder'); ?></p>

            <?php if (isset($_GET['message']) && $_GET['message'] === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Custom hamper configuration saved successfully.', 'tvak-custom-hamper-builder'); ?></p></div>
            <?php endif; ?>

            <div class="tvak-admin-container" style="display: flex; gap: 20px; margin-top: 20px;">
                <div class="tvak-admin-main" style="flex: 1;">
                    <div class="postbox" style="padding: 20px; background: #fff; border-radius: 4px; border: 1px solid #ccc;">
                        <form method="get" action="">
                            <input type="hidden" name="page" value="tvak-custom-hampers" />
                            <label for="hamper_product_id"><strong><?php esc_html_e('Hamper Shell Product:', 'tvak-custom-hamper-builder'); ?></strong></label>
                            <select name="hamper_product_id" id="hamper_product_id" onchange="this.form.submit()" style="min-width: 320px; margin-left: 10px;">
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
                            <input type="hidden" name="action" value="tvak_save_hamper" />
                            <input type="hidden" name="hamper_id" value="<?php echo esc_attr($hamper['hamper_id'] ?? 0); ?>" />
                            <input type="hidden" name="hamper_product_id" value="<?php echo esc_attr($selected_product_id); ?>" />
                            <?php wp_nonce_field('tvak_save_hamper_nonce', 'tvak_nonce'); ?>

                            <div class="postbox" style="padding: 20px; background: #fff; border-radius: 4px; border: 1px solid #ccc; margin-top: 20px;">
                                <h2><?php esc_html_e('Hamper Settings', 'tvak-custom-hamper-builder'); ?>: <em><?php echo esc_html(get_the_title($selected_product_id)); ?></em></h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="title"><?php esc_html_e('Hamper Display Title', 'tvak-custom-hamper-builder'); ?></label></th>
                                        <td><input type="text" name="title" id="title" value="<?php echo esc_attr($hamper['title'] ?? get_the_title($selected_product_id)); ?>" class="regular-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="min_items"><?php esc_html_e('Minimum Products', 'tvak-custom-hamper-builder'); ?></label></th>
                                        <td><input type="number" name="min_items" id="min_items" min="1" max="20" value="<?php echo esc_attr($hamper['min_items'] ?? 2); ?>" class="small-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="max_items"><?php esc_html_e('Maximum Products', 'tvak-custom-hamper-builder'); ?></label></th>
                                        <td><input type="number" name="max_items" id="max_items" min="1" max="20" value="<?php echo esc_attr($hamper['max_items'] ?? 5); ?>" class="small-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Optional Products', 'tvak-custom-hamper-builder'); ?></th>
                                        <td>
                                            <label><input type="checkbox" name="allow_optional_items" value="1" <?php checked($hamper['allow_optional_items'] ?? 1, 1); ?> /> <?php esc_html_e('Label flexible add-on products as optional.', 'tvak-custom-hamper-builder'); ?></label>
                                            <p class="description"><?php esc_html_e('Products checked under Include appear in the frontend builder. Preselected items start in the hamper, Required items cannot be removed, and Optional marks flexible add-on products. Selection is controlled by Maximum Products.', 'tvak-custom-hamper-builder'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Status', 'tvak-custom-hamper-builder'); ?></th>
                                        <td><label><input type="checkbox" name="is_active" value="1" <?php checked($hamper['is_active'] ?? 1, 1); ?> /> <?php esc_html_e('Active Hamper Product', 'tvak-custom-hamper-builder'); ?></label></td>
                                    </tr>
                                </table>

                                <hr style="margin: 20px 0;" />
                                <h3><?php esc_html_e('Assigned Hamper Products', 'tvak-custom-hamper-builder'); ?></h3>
                                <p class="description"><?php esc_html_e('Use Include to show a product in the frontend hamper builder. To let customers choose 4 or 5 products, include at least that many products here and set Maximum Products accordingly.', 'tvak-custom-hamper-builder'); ?></p>

                                <table class="widefat striped">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Include', 'tvak-custom-hamper-builder'); ?></th>
                                            <th><?php esc_html_e('Product', 'tvak-custom-hamper-builder'); ?></th>
                                            <th><?php esc_html_e('Default Qty', 'tvak-custom-hamper-builder'); ?></th>
                                            <th><?php esc_html_e('Preselected', 'tvak-custom-hamper-builder'); ?></th>
                                            <th><?php esc_html_e('Required', 'tvak-custom-hamper-builder'); ?></th>
                                            <th><?php esc_html_e('Optional', 'tvak-custom-hamper-builder'); ?></th>
                                            <th><?php esc_html_e('Order', 'tvak-custom-hamper-builder'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($wc_products as $p) :
                                            if ((int) $p->ID === $selected_product_id) {
                                                continue;
                                            }
                                            $item = $existing_items[(int) $p->ID] ?? [];
                                            $wc_product = function_exists('wc_get_product') ? wc_get_product($p->ID) : null;
                                        ?>
                                            <tr>
                                                <td><input type="checkbox" name="hamper_items[<?php echo esc_attr($p->ID); ?>][enabled]" value="1" <?php checked(!empty($item)); ?> /></td>
                                                <td>
                                                    <strong><?php echo esc_html($p->post_title); ?></strong>
                                                    <br /><small><?php echo esc_html($wc_product ? $wc_product->get_type() : 'product'); ?> | ID: <?php echo esc_html($p->ID); ?></small>
                                                </td>
                                                <td><input type="number" name="hamper_items[<?php echo esc_attr($p->ID); ?>][default_quantity]" min="1" max="20" value="<?php echo esc_attr($item['default_quantity'] ?? 1); ?>" class="small-text" /></td>
                                                <td><input type="checkbox" name="hamper_items[<?php echo esc_attr($p->ID); ?>][is_preselected]" value="1" <?php checked($item['is_preselected'] ?? 1, 1); ?> /></td>
                                                <td><input type="checkbox" name="hamper_items[<?php echo esc_attr($p->ID); ?>][is_required]" value="1" <?php checked($item['is_required'] ?? 0, 1); ?> /></td>
                                                <td><input type="checkbox" name="hamper_items[<?php echo esc_attr($p->ID); ?>][is_optional]" value="1" <?php checked($item['is_optional'] ?? 0, 1); ?> /></td>
                                                <td><input type="number" name="hamper_items[<?php echo esc_attr($p->ID); ?>][sort_order]" min="0" max="999" value="<?php echo esc_attr($item['sort_order'] ?? 0); ?>" class="small-text" /></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <p class="submit" style="margin-top: 20px;">
                                    <input type="submit" class="button button-primary button-large" value="<?php esc_attr_e('Save Custom Hamper', 'tvak-custom-hamper-builder'); ?>" />
                                </p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function handle_save_hamper(): void {
        check_admin_referer('tvak_save_hamper_nonce', 'tvak_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'tvak-custom-hamper-builder'));
        }

        $hamper_product_id = isset($_POST['hamper_product_id']) ? (int) $_POST['hamper_product_id'] : 0;

        if ($hamper_product_id && class_exists('Tvak_Custom_Hamper')) {
            $hamper_id = Tvak_Custom_Hamper::save_hamper([
                'hamper_id'            => (int) ($_POST['hamper_id'] ?? 0),
                'hamper_product_id'    => $hamper_product_id,
                'title'                => sanitize_text_field($_POST['title'] ?? ''),
                'min_items'            => (int) ($_POST['min_items'] ?? 2),
                'max_items'            => (int) ($_POST['max_items'] ?? 5),
                'allow_optional_items' => !empty($_POST['allow_optional_items']) ? 1 : 0,
                'is_active'            => !empty($_POST['is_active']) ? 1 : 0,
            ]);

            Tvak_Custom_Hamper::save_items($hamper_id, $_POST['hamper_items'] ?? []);
        }

        wp_redirect(admin_url('admin.php?page=tvak-custom-hampers&hamper_product_id=' . $hamper_product_id . '&message=saved'));
        exit;
    }
}
