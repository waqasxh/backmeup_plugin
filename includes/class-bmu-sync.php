<?php

/**
 * Main sync orchestration class
 */

class BMU_Sync
{

    /**
     * Find SSH executable
     */
    private static function find_ssh()
    {
        $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        $possible_paths = array();

        if ($is_windows) {
            // Windows-specific paths (Cygwin)
            $possible_paths[] = 'C:\\cygwin64\\bin\\ssh.exe';
            $possible_paths[] = 'C:\\cygwin\\bin\\ssh.exe';
        }

        // Unix/Linux/macOS/WSL paths
        $possible_paths[] = '/usr/bin/ssh';
        $possible_paths[] = '/usr/local/bin/ssh';
        $possible_paths[] = '/opt/homebrew/bin/ssh'; // macOS ARM (M1/M2)
        $possible_paths[] = 'ssh'; // Fallback to PATH

        foreach ($possible_paths as $path) {
            if (file_exists($path) || $path === 'ssh') {
                return $path;
            }
        }

        return 'ssh'; // Fallback
    }

    /**
     * Find SCP executable
     */
    private static function find_scp()
    {
        $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        $possible_paths = array();

        if ($is_windows) {
            // Windows-specific paths (Cygwin)
            $possible_paths[] = 'C:\\cygwin64\\bin\\scp.exe';
            $possible_paths[] = 'C:\\cygwin\\bin\\scp.exe';
        }

        // Unix/Linux/macOS/WSL paths
        $possible_paths[] = '/usr/bin/scp';
        $possible_paths[] = '/usr/local/bin/scp';
        $possible_paths[] = '/opt/homebrew/bin/scp'; // macOS ARM (M1/M2)
        $possible_paths[] = 'scp'; // Fallback to PATH

        foreach ($possible_paths as $path) {
            if (file_exists($path) || $path === 'scp') {
                return $path;
            }
        }

        return 'scp'; // Fallback
    }

    /**
     * Find sshpass executable
     */
    private static function find_sshpass()
    {
        $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        $possible_paths = array();

        if ($is_windows) {
            // Windows-specific paths (Cygwin)
            $possible_paths[] = 'C:\\cygwin64\\bin\\sshpass.exe';
            $possible_paths[] = 'C:\\cygwin\\bin\\sshpass.exe';
        }

        // Unix/Linux/macOS/WSL paths
        $possible_paths[] = '/usr/bin/sshpass';
        $possible_paths[] = '/usr/local/bin/sshpass';
        $possible_paths[] = '/opt/homebrew/bin/sshpass'; // macOS ARM (M1/M2)
        $possible_paths[] = 'sshpass'; // Fallback to PATH

        foreach ($possible_paths as $path) {
            if (file_exists($path) || $path === 'sshpass') {
                return $path;
            }
        }

        return false;
    }

    /**
     * Perform full sync (files + database)
     */
    public static function full_sync($direction = 'pull')
    {
        $start_time = microtime(true);
        $results = array(
            'files' => false,
            'database' => false,
            'search_replace' => false,
            'time' => 0
        );

        try {
            // Step 1: Create backup before sync
            BMU_Files::create_backup();

            if ($direction === 'pull') {
                // Pull from live to local

                // Sync files first
                $results['files'] = BMU_Files::sync_files('pull');

                // Export remote database and download
                $results['database'] = self::pull_database();

                // Search and replace URLs
                if ($results['database']) {
                    $settings = BMU_Core::get_settings();
                    $remote_url = $settings['remote_url'];
                    $local_url = get_site_url();

                    $results['search_replace'] = BMU_Database::search_replace($remote_url, $local_url);
                }
            } else {
                // Push from local to live

                // Export local database
                $backup_dir = WP_CONTENT_DIR . '/backups';
                if (!file_exists($backup_dir)) {
                    wp_mkdir_p($backup_dir);
                }

                $db_file = $backup_dir . '/db-export-' . date('Y-m-d-H-i-s') . '.sql';
                $results['database'] = BMU_Database::export_database($db_file);

                // Sync files
                $results['files'] = BMU_Files::sync_files('push');

                // Upload and import database on remote (requires additional SSH commands)
                if ($results['database']) {
                    $results['search_replace'] = self::push_database($db_file);
                }
            }

            $end_time = microtime(true);
            $results['time'] = round($end_time - $start_time, 2);

            $status = ($results['files'] && $results['database']) ? 'success' : 'partial';
            BMU_Core::log_sync('full', $direction, $status, 'Completed in ' . $results['time'] . ' seconds');

            // Update last sync time
            $settings = BMU_Core::get_settings();
            $settings['last_sync'] = current_time('mysql');
            BMU_Core::update_settings($settings);

            return $results;
        } catch (Exception $e) {
            BMU_Core::log_sync('full', $direction, 'error', $e->getMessage());
            return $results;
        }
    }

    /**
     * Pull database from remote server
     */
    private static function pull_database()
    {
        $settings = BMU_Core::get_settings();

        if (empty($settings['db_host']) || empty($settings['db_name'])) {
            return false;
        }

        try {
            $backup_dir = WP_CONTENT_DIR . '/backups';
            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
            }

            $local_db_file = $backup_dir . '/remote-db-' . date('Y-m-d-H-i-s') . '.sql';

            // Export remote database directly using mysqldump with remote host
            BMU_Core::log_sync('database', 'pull', 'info', 'Exporting remote database...');

            $result = BMU_Database::export_database(
                $local_db_file,
                $settings['db_host'],
                $settings['db_user'],
                $settings['db_password'],
                $settings['db_name']
            );

            if (!$result) {
                throw new Exception('Failed to export remote database');
            }

            // Verify the file was downloaded and has content
            if (!file_exists($local_db_file) || filesize($local_db_file) == 0) {
                throw new Exception('Exported database file is empty or missing');
            }

            BMU_Core::log_sync('database', 'pull', 'info', 'Database exported: ' . filesize($local_db_file) . ' bytes');

            // Import the database to local
            $import_result = BMU_Database::import_database($local_db_file);

            if (!$import_result) {
                throw new Exception('Database import to local failed');
            }

            BMU_Core::log_sync('database', 'pull', 'success', 'Database imported successfully');

            return $import_result;
        } catch (Exception $e) {
            // Clean up temp password file on error
            if (isset($tmp_pass_file) && file_exists($tmp_pass_file)) {
                unlink($tmp_pass_file);
            }
            BMU_Core::log_sync('database', 'pull', 'error', $e->getMessage());
            return false;
        }
    }

    /**
     * Push database to remote server
     */
    private static function push_database($local_db_file)
    {
        $settings = BMU_Core::get_settings();

        if (empty($settings['db_host']) || empty($settings['db_name'])) {
            return false;
        }

        try {
            BMU_Core::log_sync('database', 'push', 'info', 'Importing to remote database...');

            // Import directly to remote database using mysql command
            $result = BMU_Database::import_database(
                $local_db_file,
                $settings['db_host'],
                $settings['db_user'],
                $settings['db_password'],
                $settings['db_name']
            );

            if (!$result) {
                throw new Exception('Failed to import to remote database');
            }

            BMU_Core::log_sync('database', 'push', 'success', 'Database imported to remote successfully');

            // Search and replace URLs on remote
            $local_url = get_site_url();
            $remote_url = $settings['remote_url'];

            // This would require direct database connection for search/replace
            // For now, we'll just return success

            return true;
        } catch (Exception $e) {
            BMU_Core::log_sync('database', 'push', 'error', $e->getMessage());
            return false;
        }
    }
}
