<?php

/**
 * Plugin Name: Back Me Up - WP Sync
 * Plugin URI: https://a2zsystems.com
 * Description: One-click solution to sync WordPress installation between local and live environments
 * Version: 1.0.0
 * Author: A2Z Systems
 * Author URI: https://a2zsystems.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: back-me-up
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BMU_VERSION', '1.0.0');
define('BMU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BMU_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BMU_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once BMU_PLUGIN_DIR . 'includes/class-bmu-core.php';
require_once BMU_PLUGIN_DIR . 'includes/class-bmu-database.php';
require_once BMU_PLUGIN_DIR . 'includes/class-bmu-files.php';
require_once BMU_PLUGIN_DIR . 'includes/class-bmu-admin.php';
require_once BMU_PLUGIN_DIR . 'includes/class-bmu-sync.php';

// Initialize the plugin
function bmu_init()
{
    BMU_Admin::init();
}
add_action('plugins_loaded', 'bmu_init');

// Activation hook
register_activation_hook(__FILE__, array('BMU_Core', 'activate'));

// Deactivation hook
register_deactivation_hook(__FILE__, array('BMU_Core', 'deactivate'));
