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
        add_action('wp_ajax_bmu_save_settings', array(__CLASS__, 'ajax_save_settings'));
        add_action('wp_ajax_bmu_delete_backup', array(__CLASS__, 'ajax_delete_backup'));
        add_action('wp_ajax_bmu_delete_all_backups', array(__CLASS__, 'ajax_delete_all_backups'));
        add_action('wp_ajax_bmu_clear_logs', array(__CLASS__, 'ajax_clear_logs'));
        add_action('wp_ajax_bmu_restore_backup', array(__CLASS__, 'ajax_restore_backup'));
        add_action('wp_ajax_bmu_backup_now', array(__CLASS__, 'ajax_backup_now'));
    }

    public static function add_admin_menu()
    {
        add_menu_page(
            'Back Me Up',
            'Back Me Up',
            'manage_options',
            'back-me-up',
            array(__CLASS__, 'render_main_page'),
            'dashicons-backup',
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
            'Logs',
            'Logs',
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

        // Clean exclude paths to remove duplicates and empty entries
        $exclude_paths = array_map('sanitize_text_field', $_POST['exclude_paths']);
        $exclude_paths = array_filter($exclude_paths); // Remove empty values
        $exclude_paths = array_unique($exclude_paths); // Remove duplicates
        $exclude_paths = array_values($exclude_paths); // Re-index array

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
            'exclude_paths' => $exclude_paths,
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

    public static function ajax_delete_backup()
    {
        check_ajax_referer('bmu_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $file = isset($_POST['file']) ? sanitize_file_name($_POST['file']) : '';

        if (empty($file)) {
            wp_send_json_error('No file specified');
            return;
        }

        $backup_dir = WP_CONTENT_DIR . '/backups';
        $file_path = $backup_dir . '/' . $file;

        // Security check - ensure file is in backup directory
        if (realpath($file_path) === false || strpos(realpath($file_path), realpath($backup_dir)) !== 0) {
            wp_send_json_error('Invalid file path');
            return;
        }

        if (file_exists($file_path) && unlink($file_path)) {
            wp_send_json_success('Backup deleted successfully');
        } else {
            wp_send_json_error('Failed to delete backup');
        }
    }

    public static function ajax_delete_all_backups()
    {
        check_ajax_referer('bmu_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $backup_dir = WP_CONTENT_DIR . '/backups';

        if (!file_exists($backup_dir)) {
            wp_send_json_success('No backups to delete');
            return;
        }

        $files = glob($backup_dir . '/*.zip');
        $deleted = 0;

        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                $deleted++;
            }
        }

        wp_send_json_success("Deleted $deleted backup file(s)");
    }

    public static function ajax_clear_logs()
    {
        check_ajax_referer('bmu_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bmu_sync_log';

        $deleted = $wpdb->query("DELETE FROM $table_name");

        if ($deleted !== false) {
            wp_send_json_success("Cleared $deleted log entries");
        } else {
            wp_send_json_error('Failed to clear logs');
        }
    }

    public static function ajax_restore_backup()
    {
        check_ajax_referer('bmu_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $backup_file = sanitize_text_field($_POST['backup_file']);
        $backup_dir = WP_CONTENT_DIR . '/backups';
        $full_path = $backup_dir . '/' . basename($backup_file);

        if (!file_exists($full_path)) {
            wp_send_json_error('Backup file not found');
            return;
        }

        try {
            $result = BMU_Files::restore_backup($full_path);
            if ($result) {
                wp_send_json_success('Backup restored successfully. Please refresh the page.');
            } else {
                wp_send_json_error('Backup restore failed');
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public static function ajax_backup_now()
    {
        check_ajax_referer('bmu_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        try {
            $backup_file = BMU_Files::create_backup();
            if ($backup_file) {
                wp_send_json_success(array(
                    'message' => 'Backup created successfully',
                    'file' => basename($backup_file)
                ));
            } else {
                wp_send_json_error('Failed to create backup');
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public static function ajax_test_ssh()
    {
        check_ajax_referer('bmu_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $settings = BMU_Core::get_settings();

        if (empty($settings['ssh_host']) || empty($settings['ssh_user'])) {
            wp_send_json_error('SSH host and username are required');
            return;
        }

        try {
            // Find SSH and sshpass
            $ssh_path = BMU_Sync::find_ssh_public();
            $sshpass_path = BMU_Sync::find_sshpass_public();

            if (!$sshpass_path) {
                wp_send_json_error('sshpass not found. Please install Cygwin with sshpass package.');
                return;
            }

            // Create temp password file
            $tmp_pass_file = tempnam(sys_get_temp_dir(), 'bmu_pass_');
            file_put_contents($tmp_pass_file, $settings['ssh_password']);
            chmod($tmp_pass_file, 0600);

            // Convert Windows path to Cygwin format if needed
            $pass_file_arg = $tmp_pass_file;
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && strpos($sshpass_path, 'cygwin') !== false) {
                $pass_file_arg = preg_replace('/^([A-Z]):/i', '/cygdrive/$1', str_replace('\\', '/', $tmp_pass_file));
                $pass_file_arg = strtolower($pass_file_arg);
            }

            // Test SSH connection
            $test_command = sprintf(
                '"%s" -f %s "%s" -p %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=10 %s@%s "echo SSH_CONNECTION_SUCCESS"',
                $sshpass_path,
                escapeshellarg($pass_file_arg),
                $ssh_path,
                escapeshellarg($settings['ssh_port']),
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host'])
            );

            $output = array();
            exec($test_command . ' 2>&1', $output, $return_var);

            // Clean up temp password file
            if (file_exists($tmp_pass_file)) {
                unlink($tmp_pass_file);
            }

            $output_str = implode("\n", $output);

            if ($return_var === 0 && strpos($output_str, 'SSH_CONNECTION_SUCCESS') !== false) {
                wp_send_json_success('SSH connection successful!');
            } else {
                wp_send_json_error('SSH connection failed: ' . $output_str);
            }
        } catch (Exception $e) {
            // Clean up on error
            if (isset($tmp_pass_file) && file_exists($tmp_pass_file)) {
                unlink($tmp_pass_file);
            }
            wp_send_json_error($e->getMessage());
        }
    }

    public static function ajax_test_db()
    {
        check_ajax_referer('bmu_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $settings = BMU_Core::get_settings();

        if (empty($settings['db_host']) || empty($settings['db_name']) || empty($settings['db_user'])) {
            wp_send_json_error('Database host, name, and user are required');
            return;
        }

        try {
            // Try direct database connection
            $wpdb_test = new wpdb(
                $settings['db_user'],
                $settings['db_password'],
                $settings['db_name'],
                $settings['db_host']
            );

            if (!empty($wpdb_test->error)) {
                wp_send_json_error('Direct database connection failed: ' . $wpdb_test->error . ' (Will use SSH fallback during sync)');
                return;
            }

            // Test a simple query
            $result = $wpdb_test->get_var('SELECT 1');

            if ($result == 1) {
                // Auto-enable direct DB connection on success
                $settings['use_direct_db'] = true;
                BMU_Core::update_settings($settings);

                wp_send_json_success('Direct database connection successful! Direct DB connection has been enabled.');
            } else {
                wp_send_json_error('Database connection failed: Could not execute test query (Will use SSH fallback during sync)');
            }
        } catch (Exception $e) {
            wp_send_json_error('Database connection failed: ' . $e->getMessage() . ' (Will use SSH fallback during sync)');
        }
    }
}
