<?php

/**
 * Admin interface and menu functionality
 */

class BMU_Admin
{

    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('wp_ajax_bmu_sync', array(__CLASS__, 'ajax_sync'));
        add_action('wp_ajax_bmu_save_settings', array(__CLASS__, 'ajax_save_settings'));
    }

    public static function add_admin_menu()
    {
        add_menu_page(
            'Back Me Up',
            'Back Me Up',
            'manage_options',
            'back-me-up',
            array(__CLASS__, 'render_main_page'),
            'dashicons-update',
            80
        );

        add_submenu_page(
            'back-me-up',
            'Settings',
            'Settings',
            'manage_options',
            'back-me-up-settings',
            array(__CLASS__, 'render_settings_page')
        );

        add_submenu_page(
            'back-me-up',
            'Sync Logs',
            'Sync Logs',
            'manage_options',
            'back-me-up-logs',
            array(__CLASS__, 'render_logs_page')
        );
    }

    public static function enqueue_scripts($hook)
    {
        if (strpos($hook, 'back-me-up') === false) {
            return;
        }

        wp_enqueue_style('bmu-admin', BMU_PLUGIN_URL . 'assets/css/admin.css', array(), BMU_VERSION);
        wp_enqueue_script('bmu-admin', BMU_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), BMU_VERSION, true);

        wp_localize_script('bmu-admin', 'bmuAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bmu_ajax_nonce')
        ));
    }

    public static function render_main_page()
    {
        $settings = BMU_Core::get_settings();
        $last_sync = !empty($settings['last_sync']) ? $settings['last_sync'] : 'Never';
        $backups = BMU_Files::get_backups();

        include BMU_PLUGIN_DIR . 'templates/main-page.php';
    }

    public static function render_settings_page()
    {
        $settings = BMU_Core::get_settings();
        include BMU_PLUGIN_DIR . 'templates/settings-page.php';
    }

    public static function render_logs_page()
    {
        $logs = BMU_Core::get_sync_logs(100);
        include BMU_PLUGIN_DIR . 'templates/logs-page.php';
    }

    public static function ajax_sync()
    {
        check_ajax_referer('bmu_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $direction = isset($_POST['direction']) ? sanitize_text_field($_POST['direction']) : 'pull';

        $results = BMU_Sync::full_sync($direction);

        if ($results['files'] && $results['database']) {
            wp_send_json_success(array(
                'message' => 'Sync completed successfully in ' . $results['time'] . ' seconds',
                'results' => $results
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Sync completed with errors',
                'results' => $results
            ));
        }
    }

    public static function ajax_save_settings()
    {
        check_ajax_referer('bmu_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $settings = array(
            'remote_url' => sanitize_text_field($_POST['remote_url']),
            'remote_path' => sanitize_text_field($_POST['remote_path']),
            'ssh_host' => sanitize_text_field($_POST['ssh_host']),
            'ssh_user' => sanitize_text_field($_POST['ssh_user']),
            'ssh_port' => sanitize_text_field($_POST['ssh_port']),
            'ssh_key_path' => sanitize_text_field($_POST['ssh_key_path']),
            'ssh_password' => sanitize_text_field($_POST['ssh_password']),
            'db_host' => sanitize_text_field($_POST['db_host']),
            'db_name' => sanitize_text_field($_POST['db_name']),
            'db_user' => sanitize_text_field($_POST['db_user']),
            'db_password' => sanitize_text_field($_POST['db_password']),
            'exclude_paths' => array_map('sanitize_text_field', $_POST['exclude_paths']),
            'sync_direction' => sanitize_text_field($_POST['sync_direction'])
        );

        // Preserve last_sync
        $current_settings = BMU_Core::get_settings();
        if (!empty($current_settings['last_sync'])) {
            $settings['last_sync'] = $current_settings['last_sync'];
        }

        BMU_Core::update_settings($settings);

        wp_send_json_success('Settings saved successfully');
    }
}
