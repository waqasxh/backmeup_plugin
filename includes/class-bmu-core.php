<?php

/**
 * Core functionality for BackMeUp plugin
 */

class BMU_Core
{

    public static function activate()
    {
        // Create options table entry for plugin settings
        if (!get_option('bmu_settings')) {
            add_option('bmu_settings', array(
                'remote_url' => '',
                'remote_path' => '',
                'ssh_host' => 'access-5018470946.webspace-host.com',
                'ssh_user' => 'a282748',
                'ssh_port' => '22',
                'ssh_key_path' => '',
                'ssh_password' => '',
                'db_host' => '',
                'db_name' => '',
                'db_user' => '',
                'db_password' => '',
                'exclude_paths' => array(
                    'wp-config.php',
                    '.htaccess',
                    'wp-content/cache',
                    'wp-content/backup',
                    'wp-content/backups',
                    'wp-content/uploads/wc-logs'
                ),
                'last_sync' => '',
                'sync_direction' => 'pull' // pull or push
            ));
        }

        // Create sync log table
        global $wpdb;
        $table_name = $wpdb->prefix . 'bmu_sync_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sync_type varchar(50) NOT NULL,
            direction varchar(10) NOT NULL,
            status varchar(20) NOT NULL,
            message text,
            started_at datetime NOT NULL,
            completed_at datetime,
            PRIMARY KEY (id),
            KEY status (status),
            KEY sync_type (sync_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function deactivate()
    {
        // Clean up any scheduled tasks if we add cron jobs later
        wp_clear_scheduled_hook('bmu_auto_sync');
    }

    /**
     * Update existing settings with new default exclude paths
     */
    public static function update_exclude_paths()
    {
        $settings = get_option('bmu_settings');
        if ($settings && isset($settings['exclude_paths'])) {
            // Define critical paths that must be excluded
            $critical_excludes = array('wp-config.php', '.htaccess', 'wp-content/backups');

            // Merge critical excludes with existing ones (avoiding duplicates)
            $current_excludes = $settings['exclude_paths'];
            foreach ($critical_excludes as $path) {
                if (!in_array($path, $current_excludes)) {
                    $current_excludes[] = $path;
                }
            }

            $settings['exclude_paths'] = $current_excludes;
            update_option('bmu_settings', $settings);
        }
    }

    public static function get_settings()
    {
        return get_option('bmu_settings', array());
    }

    public static function update_settings($settings)
    {
        return update_option('bmu_settings', $settings);
    }

    public static function log_sync($type, $direction, $status, $message = '')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bmu_sync_log';

        $wpdb->insert(
            $table_name,
            array(
                'sync_type' => $type,
                'direction' => $direction,
                'status' => $status,
                'message' => $message,
                'started_at' => current_time('mysql'),
                'completed_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    public static function get_sync_logs($limit = 50)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bmu_sync_log';

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name ORDER BY id DESC LIMIT %d", $limit)
        );
    }
}
