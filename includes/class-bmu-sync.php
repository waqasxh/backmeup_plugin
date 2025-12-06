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
     * Public wrapper for find_ssh
     */
    public static function find_ssh_public()
    {
        return self::find_ssh();
    }

    /**
     * Public wrapper for find_sshpass
     */
    public static function find_sshpass_public()
    {
        return self::find_sshpass();
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
            // Step 1: Create backup before sync (only if enabled for pull)
            $settings = BMU_Core::get_settings();
            $should_backup = ($direction === 'push') || (!empty($settings['backup_before_pull']));

            if ($should_backup) {
                BMU_Files::create_backup();
            } else {
                BMU_Core::log_sync('backup', $direction, 'info', 'Backup skipped (backup_before_pull disabled)');
            }

            if ($direction === 'pull') {
                // Pull from live to local

                // Sync files first
                $results['files'] = BMU_Files::sync_files('pull');

                // Export remote database and download
                $results['database'] = self::pull_database();

                // Search and replace URLs
                if ($results['database']) {
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
     * Pull database from remote server (tries direct connection first, falls back to SSH)
     */
    private static function pull_database()
    {
        $settings = BMU_Core::get_settings();

        if (empty($settings['db_name'])) {
            return false;
        }

        try {
            $backup_dir = WP_CONTENT_DIR . '/backups';
            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
            }

            $local_db_file = $backup_dir . '/remote-db-' . date('Y-m-d-H-i-s') . '.sql';

            // Try direct database connection first (only if enabled)
            if (!empty($settings['use_direct_db'])) {
                BMU_Core::log_sync('database', 'pull', 'info', 'Attempting direct database connection from local machine...');
                BMU_Core::log_sync('database', 'pull', 'info', 'NOTE: This will fail with IONOS/GoDaddy - uncheck "Use Direct DB" in settings');

                $result = BMU_Database::export_database(
                    $local_db_file,
                    $settings['db_host'],
                    $settings['db_user'],
                    $settings['db_password'],
                    $settings['db_name']
                );

                // Check if direct connection succeeded (file exists and has reasonable size > 100 bytes)
                if ($result && file_exists($local_db_file) && filesize($local_db_file) > 100) {
                    BMU_Core::log_sync('database', 'pull', 'info', 'Database exported via direct connection: ' . filesize($local_db_file) . ' bytes');

                    // Import the database to local
                    $import_result = BMU_Database::import_database($local_db_file);

                    if (!$import_result) {
                        throw new Exception('Database import to local failed');
                    }

                    BMU_Core::log_sync('database', 'pull', 'success', 'Database imported successfully via direct connection');
                    return $import_result;
                }

                // Direct connection failed or file too small, clean up and fall back to SSH method
                if (file_exists($local_db_file)) {
                    unlink($local_db_file);
                }

                BMU_Core::log_sync('database', 'pull', 'info', 'Direct connection failed, using SSH method...');
            } else {
                BMU_Core::log_sync('database', 'pull', 'info', 'Direct DB disabled, using SSH method...');
            }

            if (empty($settings['ssh_host'])) {
                throw new Exception('SSH host not configured for fallback');
            }

            $remote_db_file = '/tmp/wp-db-export-' . time() . '.sql';

            // Find executables
            $ssh_path = self::find_ssh();
            $scp_path = self::find_scp();
            $sshpass_path = self::find_sshpass();

            if (!$sshpass_path) {
                throw new Exception('sshpass not found for password authentication');
            }

            // Create temp password file
            $tmp_pass_file = tempnam(sys_get_temp_dir(), 'bmu_pass_');
            file_put_contents($tmp_pass_file, $settings['ssh_password']);
            chmod($tmp_pass_file, 0600);

            // Convert Windows path to Cygwin format if using Cygwin
            $pass_file_arg = $tmp_pass_file;
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && strpos($sshpass_path, 'cygwin') !== false) {
                $pass_file_arg = preg_replace('/^([A-Z]):/i', '/cygdrive/$1', str_replace('\\', '/', $tmp_pass_file));
                $pass_file_arg = strtolower($pass_file_arg);
            }

            $ssh_prefix = sprintf(
                '"%s" -f %s "%s" -p %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null',
                $sshpass_path,
                escapeshellarg($pass_file_arg),
                $ssh_path,
                escapeshellarg($settings['ssh_port'])
            );

            // Export database on remote server
            BMU_Core::log_sync('database', 'pull', 'info', 'Exporting database on remote server via SSH...');

            // Use standard mysqldump path (most common location)
            $mysqldump_cmd = 'mysqldump';
            BMU_Core::log_sync('database', 'pull', 'info', 'Using mysqldump command');

            // First, test database connection and show credentials being used
            BMU_Core::log_sync('database', 'pull', 'info', 'Testing connection with: host=' . $settings['db_host'] . ', user=' . $settings['db_user'] . ', db=' . $settings['db_name']);

            $test_command = sprintf(
                '%s %s@%s "mysql -h %s -u %s -p\'%s\' %s -e \'SELECT 1\' 2>&1"',
                $ssh_prefix,
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host']),
                escapeshellarg($settings['db_host']),
                escapeshellarg($settings['db_user']),
                str_replace("'", "'\\\\'", $settings['db_password']),
                escapeshellarg($settings['db_name'])
            );

            $test_output = array();
            exec($test_command, $test_output, $test_return);
            $test_output_str = implode("\n", $test_output);

            if ($test_return !== 0) {
                BMU_Core::log_sync('database', 'pull', 'error', 'Database connection test failed: ' . $test_output_str);
                throw new Exception('Cannot connect to remote database: ' . $test_output_str);
            }

            BMU_Core::log_sync('database', 'pull', 'success', 'Database connection test successful');

            // Now export the database on remote server
            // Note: Don't escape the filename for the redirect - it needs to be literal
            $ssh_command = sprintf(
                '%s %s@%s "cd /tmp && %s -h %s -u %s -p\'%s\' %s > %s 2>&1 && test -s %s && echo EXPORT_SUCCESS || echo EXPORT_FAILED"',
                $ssh_prefix,
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host']),
                $mysqldump_cmd,
                escapeshellarg($settings['db_host']),
                escapeshellarg($settings['db_user']),
                str_replace("'", "'\\\\'", $settings['db_password']),
                escapeshellarg($settings['db_name']),
                basename($remote_db_file),
                basename($remote_db_file)
            );

            $output = array();
            exec($ssh_command, $output, $return_var);
            $output_str = implode("\n", $output);

            // Check for success marker (should be the last line)
            $last_line = trim(end($output));

            if ($last_line !== 'EXPORT_SUCCESS') {
                // Log the full output to see what went wrong
                BMU_Core::log_sync('database', 'pull', 'error', 'mysqldump failed. Error output: ' . $output_str);
                throw new Exception('Remote database export failed: ' . $output_str);
            }

            BMU_Core::log_sync('database', 'pull', 'success', 'Database exported on remote server');

            // Download the database file using SCP
            $local_db_file_arg = $local_db_file;
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && strpos($scp_path, 'cygwin') !== false) {
                $local_db_file_arg = preg_replace('/^([A-Z]):/i', '/cygdrive/$1', str_replace('\\', '/', $local_db_file));
                $local_db_file_arg = strtolower($local_db_file_arg);
            }

            $scp_prefix = sprintf(
                '"%s" -f %s "%s" -q -P %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR',
                $sshpass_path,
                escapeshellarg($pass_file_arg),
                $scp_path,
                escapeshellarg($settings['ssh_port'])
            );

            $scp_command = sprintf(
                '%s %s@%s:%s %s',
                $scp_prefix,
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host']),
                escapeshellarg($remote_db_file),
                escapeshellarg($local_db_file_arg)
            );

            BMU_Core::log_sync('database', 'pull', 'info', 'Downloading database file from remote server...');
            BMU_Core::log_sync('database', 'pull', 'info', 'Remote file: ' . $remote_db_file . ', Local file: ' . $local_db_file);

            $output = array();
            exec($scp_command . ' 2>&1', $output, $return_var);
            $scp_output = implode("\n", $output);

            if ($return_var !== 0) {
                BMU_Core::log_sync('database', 'pull', 'error', 'SCP command failed with code ' . $return_var . ': ' . $scp_output);
                throw new Exception('Database download failed (SCP error code ' . $return_var . '): ' . $scp_output);
            }

            if (!file_exists($local_db_file)) {
                BMU_Core::log_sync('database', 'pull', 'error', 'Local file not created after SCP. Output: ' . $scp_output);
                throw new Exception('Database download failed: Local file not created after transfer');
            }

            $file_size = filesize($local_db_file);
            if ($file_size < 100) {
                BMU_Core::log_sync('database', 'pull', 'error', 'Downloaded file is too small (' . $file_size . ' bytes)');
                throw new Exception('Database download failed: File size too small (' . $file_size . ' bytes)');
            }

            BMU_Core::log_sync('database', 'pull', 'info', 'Database downloaded via SSH: ' . $file_size . ' bytes');

            // Import the database to local
            $import_result = BMU_Database::import_database($local_db_file);

            if (!$import_result) {
                throw new Exception('Database import to local failed');
            }

            // Clean up remote temp file
            $cleanup_command = sprintf(
                '%s %s@%s "rm -f %s"',
                $ssh_prefix,
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host']),
                escapeshellarg($remote_db_file)
            );
            exec($cleanup_command);

            // Clean up temp password file
            if (file_exists($tmp_pass_file)) {
                unlink($tmp_pass_file);
            }

            BMU_Core::log_sync('database', 'pull', 'success', 'Database imported successfully via SSH');

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
     * Push database to remote server (tries direct connection first, falls back to SSH)
     */
    private static function push_database($local_db_file)
    {
        $settings = BMU_Core::get_settings();

        if (empty($settings['db_name'])) {
            return false;
        }

        try {
            // Try direct database connection first (only if enabled)
            if (!empty($settings['use_direct_db'])) {
                BMU_Core::log_sync('database', 'push', 'info', 'Attempting direct database import...');

                $result = BMU_Database::import_database(
                    $local_db_file,
                    $settings['db_host'],
                    $settings['db_user'],
                    $settings['db_password'],
                    $settings['db_name']
                );

                if ($result) {
                    BMU_Core::log_sync('database', 'push', 'success', 'Database imported to remote via direct connection');
                    return true;
                }

                // Direct connection failed, fall back to SSH method
                BMU_Core::log_sync('database', 'push', 'info', 'Direct connection failed, using SSH method...');
            } else {
                BMU_Core::log_sync('database', 'push', 'info', 'Direct DB disabled, using SSH method...');
            }

            if (empty($settings['ssh_host'])) {
                throw new Exception('SSH host not configured for fallback');
            }

            $remote_db_file = '/tmp/wp-db-import-' . time() . '.sql';

            // Find executables
            $ssh_path = self::find_ssh();
            $scp_path = self::find_scp();
            $sshpass_path = self::find_sshpass();

            if (!$sshpass_path) {
                throw new Exception('sshpass not found for password authentication');
            }

            // Create temp password file
            $tmp_pass_file = tempnam(sys_get_temp_dir(), 'bmu_pass_');
            file_put_contents($tmp_pass_file, $settings['ssh_password']);
            chmod($tmp_pass_file, 0600);

            // Convert Windows path to Cygwin format if using Cygwin
            $pass_file_arg = $tmp_pass_file;
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && strpos($sshpass_path, 'cygwin') !== false) {
                $pass_file_arg = preg_replace('/^([A-Z]):/i', '/cygdrive/$1', str_replace('\\', '/', $tmp_pass_file));
                $pass_file_arg = strtolower($pass_file_arg);
            }

            $ssh_prefix = sprintf(
                '"%s" -f %s "%s" -q -p %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR',
                $sshpass_path,
                escapeshellarg($pass_file_arg),
                $ssh_path,
                escapeshellarg($settings['ssh_port'])
            );

            $scp_prefix = sprintf(
                '"%s" -f %s "%s" -q -P %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR',
                $sshpass_path,
                escapeshellarg($pass_file_arg),
                $scp_path,
                escapeshellarg($settings['ssh_port'])
            );

            // Upload database file using SCP
            BMU_Core::log_sync('database', 'push', 'info', 'Uploading database to remote server...');

            $local_db_file_arg = $local_db_file;
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && strpos($scp_path, 'cygwin') !== false) {
                $local_db_file_arg = preg_replace('/^([A-Z]):/i', '/cygdrive/$1', str_replace('\\', '/', $local_db_file));
                $local_db_file_arg = strtolower($local_db_file_arg);
            }

            $scp_command = sprintf(
                '%s %s %s@%s:%s',
                $scp_prefix,
                escapeshellarg($local_db_file_arg),
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host']),
                escapeshellarg($remote_db_file)
            );

            $output = array();
            exec($scp_command . ' 2>/dev/null', $output, $return_var);

            if ($return_var !== 0) {
                throw new Exception('Database upload failed. Check SSH connection and remote path permissions.');
            }

            BMU_Core::log_sync('database', 'push', 'info', 'Database uploaded, importing on remote server...');

            // Import database on remote server
            $ssh_command = sprintf(
                '%s %s@%s "mysql -h %s -u %s -p\'%s\' %s < %s 2>&1 && echo IMPORT_SUCCESS || echo IMPORT_FAILED"',
                $ssh_prefix,
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host']),
                escapeshellarg($settings['db_host']),
                escapeshellarg($settings['db_user']),
                str_replace("'", "'\\\\'", $settings['db_password']),
                escapeshellarg($settings['db_name']),
                escapeshellarg($remote_db_file)
            );

            $output = array();
            exec($ssh_command . ' 2>/dev/null', $output, $return_var);
            $output_str = implode("\n", $output);
            $last_line = trim(end($output));

            if ($last_line !== 'IMPORT_SUCCESS') {
                BMU_Core::log_sync('database', 'push', 'error', 'mysql import failed. Output: ' . $output_str);
                throw new Exception('Remote database import failed. Check if database credentials are correct.');
            }

            // Clean up remote temp file
            $cleanup_command = sprintf(
                '%s %s@%s "rm -f %s"',
                $ssh_prefix,
                escapeshellarg($settings['ssh_user']),
                escapeshellarg($settings['ssh_host']),
                escapeshellarg($remote_db_file)
            );
            exec($cleanup_command);

            // Clean up temp password file
            if (file_exists($tmp_pass_file)) {
                unlink($tmp_pass_file);
            }

            BMU_Core::log_sync('database', 'push', 'success', 'Database imported to remote successfully via SSH');

            return true;
        } catch (Exception $e) {
            // Clean up temp password file on error
            if (isset($tmp_pass_file) && file_exists($tmp_pass_file)) {
                unlink($tmp_pass_file);
            }
            BMU_Core::log_sync('database', 'push', 'error', $e->getMessage());
            return false;
        }
    }
}
