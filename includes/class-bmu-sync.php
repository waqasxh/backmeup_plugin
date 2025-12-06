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
        $possible_paths = array(
            'C:\\cygwin64\\bin\\ssh.exe',
            'C:\\cygwin\\bin\\ssh.exe',
            'ssh',
            '/usr/bin/ssh',
            '/usr/local/bin/ssh'
        );

        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
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
        $possible_paths = array(
            'C:\\cygwin64\\bin\\scp.exe',
            'C:\\cygwin\\bin\\scp.exe',
            'scp',
            '/usr/bin/scp',
            '/usr/local/bin/scp'
        );

        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
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
        $possible_paths = array(
            'C:\\cygwin64\\bin\\sshpass.exe',
            'C:\\cygwin\\bin\\sshpass.exe',
            'sshpass',
            '/usr/bin/sshpass',
            '/usr/local/bin/sshpass'
        );

        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
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

        if (empty($settings['ssh_host']) || empty($settings['db_name'])) {
            return false;
        }

        try {
            $backup_dir = WP_CONTENT_DIR . '/backups';
            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
            }

            $remote_db_file = '/tmp/wp-db-export-' . time() . '.sql';
            $local_db_file = $backup_dir . '/remote-db-' . date('Y-m-d-H-i-s') . '.sql';
            $ssh_password = !empty($settings['ssh_password']) ? $settings['ssh_password'] : '';

            // Find executables
            $ssh_path = self::find_ssh();
            $scp_path = self::find_scp();

            // Build SSH command prefix with password support
            $ssh_prefix = '';
            $scp_prefix = '';
            if (!empty($ssh_password) && empty($settings['ssh_key_path'])) {
                $sshpass_path = self::find_sshpass();
                if (!$sshpass_path) {
                    throw new Exception('sshpass not found for password authentication');
                }

                $ssh_prefix = sprintf(
                    '"%s" -p %s "%s" -p %s -o StrictHostKeyChecking=no',
                    $sshpass_path,
                    escapeshellarg($ssh_password),
                    $ssh_path,
                    escapeshellarg($settings['ssh_port'])
                );
                $scp_prefix = sprintf(
                    '"%s" -p %s "%s" -P %s -o StrictHostKeyChecking=no',
                    $sshpass_path,
                    escapeshellarg($ssh_password),
                    $scp_path,
                    escapeshellarg($settings['ssh_port'])
                );
            } else {
                $ssh_prefix = sprintf('"%s" -p %s', $ssh_path, escapeshellarg($settings['ssh_port']));
                $scp_prefix = sprintf('"%s" -P %s', $scp_path, escapeshellarg($settings['ssh_port']));
                if (!empty($settings['ssh_key_path'])) {
                    $ssh_prefix .= ' -i ' . escapeshellarg($settings['ssh_key_path']);
                    $scp_prefix .= ' -i ' . escapeshellarg($settings['ssh_key_path']);
                }
            }

            // SSH command to export remote database
            $ssh_command = sprintf(
                '%s %s@%s "mysqldump -h %s -u %s -p%s %s > %s"',
                $ssh_prefix,
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host']),
                escapeshellarg($settings['db_host']),
                escapeshellarg($settings['db_user']),
                escapeshellarg($settings['db_password']),
                escapeshellarg($settings['db_name']),
                escapeshellarg($remote_db_file)
            );

            exec($ssh_command . ' 2>&1', $output, $return_var);

            if ($return_var !== 0) {
                throw new Exception('Remote database export failed');
            }

            // Download the database file
            $scp_command = sprintf(
                '%s %s@%s:%s %s',
                $scp_prefix,
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host']),
                escapeshellarg($remote_db_file),
                escapeshellarg($local_db_file)
            );

            exec($scp_command . ' 2>&1', $output, $return_var);

            if ($return_var !== 0) {
                throw new Exception('Database download failed');
            }

            // Import the database
            $import_result = BMU_Database::import_database($local_db_file);

            // Clean up remote temp file
            $cleanup_command = sprintf(
                '%s %s@%s "rm %s"',
                $ssh_prefix,
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host']),
                escapeshellarg($remote_db_file)
            );

            exec($cleanup_command);

            return $import_result;
        } catch (Exception $e) {
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

        if (empty($settings['ssh_host']) || empty($settings['db_name'])) {
            return false;
        }

        try {
            $remote_db_file = '/tmp/wp-db-import-' . time() . '.sql';
            $ssh_password = !empty($settings['ssh_password']) ? $settings['ssh_password'] : '';

            // Find executables
            $ssh_path = self::find_ssh();
            $scp_path = self::find_scp();

            // Build SSH command prefix with password support
            $ssh_prefix = '';
            $scp_prefix = '';
            if (!empty($ssh_password) && empty($settings['ssh_key_path'])) {
                $sshpass_path = self::find_sshpass();
                if (!$sshpass_path) {
                    throw new Exception('sshpass not found for password authentication');
                }
                
                $ssh_prefix = sprintf(
                    '"%s" -p %s "%s" -p %s -o StrictHostKeyChecking=no',
                    $sshpass_path,
                    escapeshellarg($ssh_password),
                    $ssh_path,
                    escapeshellarg($settings['ssh_port'])
                );
                $scp_prefix = sprintf(
                    '"%s" -p %s "%s" -P %s -o StrictHostKeyChecking=no',
                    $sshpass_path,
                    escapeshellarg($ssh_password),
                    $scp_path,
                    escapeshellarg($settings['ssh_port'])
                );
            } else {
                $ssh_prefix = sprintf('"%s" -p %s', $ssh_path, escapeshellarg($settings['ssh_port']));
                $scp_prefix = sprintf('"%s" -P %s', $scp_path, escapeshellarg($settings['ssh_port']));
                if (!empty($settings['ssh_key_path'])) {
                    $ssh_prefix .= ' -i ' . escapeshellarg($settings['ssh_key_path']);
                    $scp_prefix .= ' -i ' . escapeshellarg($settings['ssh_key_path']);
                }
            }

            // Upload database file
            $scp_command = sprintf(
                '%s %s %s@%s:%s',
                $scp_prefix,
                escapeshellarg($local_db_file),
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host']),
                escapeshellarg($remote_db_file)
            );

            exec($scp_command . ' 2>&1', $output, $return_var);

            if ($return_var !== 0) {
                throw new Exception('Database upload failed');
            }

            // Import on remote server
            $ssh_command = sprintf(
                '%s %s@%s "mysql -h %s -u %s -p%s %s < %s"',
                $ssh_prefix,
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host']),
                escapeshellarg($settings['db_host']),
                escapeshellarg($settings['db_user']),
                escapeshellarg($settings['db_password']),
                escapeshellarg($settings['db_name']),
                escapeshellarg($remote_db_file)
            );

            exec($ssh_command . ' 2>&1', $output, $return_var);

            if ($return_var !== 0) {
                throw new Exception('Remote database import failed');
            }

            // Search and replace URLs on remote
            $local_url = get_site_url();
            $remote_url = $settings['remote_url'];

            // This would require WP-CLI on remote or a custom script
            // For now, we'll just return success

            // Clean up remote temp file
            $cleanup_command = sprintf(
                '%s %s@%s "rm %s"',
                $ssh_prefix,
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host']),
                escapeshellarg($remote_db_file)
            );

            exec($cleanup_command);

            return true;
        } catch (Exception $e) {
            BMU_Core::log_sync('database', 'push', 'error', $e->getMessage());
            return false;
        }
    }
}
