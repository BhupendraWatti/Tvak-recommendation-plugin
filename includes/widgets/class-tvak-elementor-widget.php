<?php
/**
 * Elementor Custom Kit Builder Widget
 *
 * Provides a custom Elementor widget for embedding the luxury
 * "Build Your Personalized Beauty Kit" experience on any page.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_Elementor_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'tvak_kit_builder';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return __('TVAK Personalized Kit Builder', 'tvak-beauty-kit');
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'eicon-sparkles';
    }

    /**
     * Get widget categories.
     *
     * @return array
     */
    public function get_categories() {
        return ['general', 'woocommerce'];
    }

    /**
     * Register widget controls.
     */
    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Kit Builder Settings', 'tvak-beauty-kit'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'title',
            [
                'label'       => __('Main Title', 'tvak-beauty-kit'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => __('Build Your Personalized Beauty Kit', 'tvak-beauty-kit'),
                'placeholder' => __('Enter title...', 'tvak-beauty-kit'),
            ]
        );

        $this->add_control(
            'subtitle',
            [
                'label'       => __('Subtitle', 'tvak-beauty-kit'),
                'type'        => \Elementor\Controls_Manager::TEXTAREA,
                'default'     => __('Experience a bespoke digital skin consultation tailored to your unique skin profile.', 'tvak-beauty-kit'),
            ]
        );

        $this->add_control(
            'accent_color',
            [
                'label'   => __('Accent Gold Color', 'tvak-beauty-kit'),
                'type'    => \Elementor\Controls_Manager::COLOR,
                'default' => '#D4AF37',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on frontend.
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Enqueue frontend scripts & styles
        wp_enqueue_style('tvak-builder-css', TVAK_PLUGIN_URL . 'assets/css/tvak-builder.css', [], TVAK_VERSION);
        wp_enqueue_script('tvak-builder-js', TVAK_PLUGIN_URL . 'assets/js/tvak-builder.js', ['jquery'], TVAK_VERSION, true);

        wp_localize_script('tvak-builder-js', 'tvak_vars', [
            'api_url'     => esc_url_raw(rest_url('tvak/v1/recommend')),
            'cart_api'    => esc_url_raw(rest_url('tvak/v1/cart/add-kit')),
            'attr_api'    => esc_url_raw(rest_url('tvak/v1/attributes')),
            'ajax_url'    => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'       => wp_create_nonce('wp_rest'),
            'accent_color'=> esc_attr($settings['accent_color']),
        ]);

        ?>
        <div class="tvak-elementor-widget-wrapper">
            <div id="tvak-beauty-kit-app" 
                 data-title="<?php echo esc_attr($settings['title']); ?>" 
                 data-subtitle="<?php echo esc_attr($settings['subtitle']); ?>">
                <div class="tvak-loader-spinner">
                    <div class="tvak-luxury-spinner"></div>
                    <p><?php esc_html_e('Initializing TVAK Digital Consultation...', 'tvak-beauty-kit'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
}
