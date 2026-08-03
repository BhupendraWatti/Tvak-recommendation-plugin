<?php
/**
 * Must-use loader for the standalone TVAK Custom Hamper Builder plugin.
 *
 * @package TVAK_Custom_Hamper_Builder
 */

if (!defined('ABSPATH')) {
    exit;
}

$tchb_plugin = WP_PLUGIN_DIR . '/tvak-custom-hamper-builder/tvak-custom-hamper-builder.php';
if (file_exists($tchb_plugin)) {
    require_once $tchb_plugin;
}
