<?php

/**
 * File sync functionality
 */

class BMU_Files
{

    /**
     * Find rsync executable
     */
    private static function find_rsync()
    {
        $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        $possible_paths = array();

        if ($is_windows) {
            // Windows-specific paths (Cygwin)
            $possible_paths[] = 'C:\\cygwin64\\bin\\rsync.exe';
            $possible_paths[] = 'C:\\cygwin\\bin\\rsync.exe';
        }

        // Unix/Linux/macOS/WSL paths
        $possible_paths[] = '/usr/bin/rsync';
        $possible_paths[] = '/usr/local/bin/rsync';
        $possible_paths[] = '/opt/homebrew/bin/rsync'; // macOS ARM (M1/M2)
        $possible_paths[] = 'rsync'; // Fallback to PATH

        foreach ($possible_paths as $path) {
            if (file_exists($path) || self::command_exists($path)) {
                return $path;
            }
        }

        return false;
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
            if (file_exists($path) || self::command_exists($path)) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Check if command exists (cross-platform)
     */
    private static function command_exists($command)
    {
        $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($is_windows) {
            $test = shell_exec(sprintf("where %s 2>nul", escapeshellarg($command)));
        } else {
            $test = shell_exec(sprintf("which %s 2>/dev/null", escapeshellarg($command)));
        }

        return !empty($test);
    }

    /**
     * Sync files using rsync (requires rsync installed)
     */
    public static function sync_files($direction = 'pull')
    {
        $settings = BMU_Core::get_settings();

        if (empty($settings['ssh_host']) || empty($settings['remote_path'])) {
            BMU_Core::log_sync('files', $direction, 'error', 'SSH settings not configured');
            return false;
        }

        try {
            // Find rsync
            $rsync_path = self::find_rsync();
            if (!$rsync_path) {
                throw new Exception('rsync not found. Please install rsync (Cygwin on Windows)');
            }

            $local_path = ABSPATH;
            $remote_path = rtrim($settings['remote_path'], '/'); // Remove trailing slash
            $ssh_user = $settings['ssh_user'];
            $ssh_host = $settings['ssh_host'];
            $ssh_port = !empty($settings['ssh_port']) ? $settings['ssh_port'] : '22';
            $ssh_password = !empty($settings['ssh_password']) ? $settings['ssh_password'] : '';

            // Build exclude parameters
            $excludes = '';
            if (!empty($settings['exclude_paths'])) {
                foreach ($settings['exclude_paths'] as $exclude) {
                    $excludes .= ' --exclude=' . escapeshellarg($exclude);
                }
            }

            // Build rsync command with proper SSH options
            // Add trailing slash to ensure we sync directory contents, not the directory itself
            $remote_spec = $ssh_user . '@' . $ssh_host . ':' . $remote_path . '/';

            // Convert Windows path to Cygwin format if using Cygwin rsync on Windows
            $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $local_path_for_rsync = $local_path;

            if ($is_windows && strpos($rsync_path, 'cygwin') !== false) {
                // Using Cygwin rsync on Windows - convert to Cygwin path format
                $local_path_for_rsync = str_replace('\\', '/', $local_path);
                if (preg_match('/^([A-Z]):(.*)$/i', $local_path_for_rsync, $matches)) {
                    $local_path_for_rsync = '/cygdrive/' . strtolower($matches[1]) . $matches[2];
                }
            }

            // Find ssh executable
            $ssh_path = self::find_ssh();

            // Build base rsync command with SSH options
            if (!empty($ssh_password) && empty($settings['ssh_key_path'])) {
                // Find sshpass
                $sshpass_path = self::find_sshpass();
                if (!$sshpass_path) {
                    throw new Exception('sshpass not found. Please install sshpass or use SSH key authentication');
                }

                // Build SSH command with all options
                $ssh_opts = sprintf(
                    '-p %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null',
                    $ssh_port
                );

                // Create a temporary password file for sshpass
                $tmp_pass_file = tempnam(sys_get_temp_dir(), 'bmu_pass_');
                file_put_contents($tmp_pass_file, $ssh_password);
                chmod($tmp_pass_file, 0600);

                // Use sshpass for password authentication with -f (file) option
                if ($direction === 'pull') {
                    // Pull from remote to local
                    $command = sprintf(
                        '"%s" -f %s "%s" -avz -e "%s %s" %s %s %s',
                        $sshpass_path,
                        escapeshellarg($tmp_pass_file),
                        $rsync_path,
                        $ssh_path,
                        $ssh_opts,
                        $excludes,
                        $remote_spec,
                        escapeshellarg($local_path_for_rsync)
                    );
                } else {
                    // Push from local to remote
                    $command = sprintf(
                        '"%s" -f %s "%s" -avz -e "%s %s" %s %s %s',
                        $sshpass_path,
                        escapeshellarg($tmp_pass_file),
                        $rsync_path,
                        $ssh_path,
                        $ssh_opts,
                        $excludes,
                        escapeshellarg($local_path_for_rsync),
                        $remote_spec
                    );
                }
            } else {
                // Use key-based authentication
                $ssh_opts = sprintf('-p %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null', $ssh_port);
                if (!empty($settings['ssh_key_path'])) {
                    $ssh_opts .= ' -i ' . escapeshellarg($settings['ssh_key_path']);
                }

                if ($direction === 'pull') {
                    // Pull from remote to local
                    $command = sprintf(
                        '"%s" -avz -e "%s %s" %s %s %s',
                        $rsync_path,
                        $ssh_path,
                        $ssh_opts,
                        $excludes,
                        $remote_spec,
                        escapeshellarg($local_path_for_rsync)
                    );
                } else {
                    // Push from local to remote
                    $command = sprintf(
                        '"%s" -avz -e "%s %s" %s %s %s',
                        $rsync_path,
                        $ssh_path,
                        $ssh_opts,
                        $excludes,
                        escapeshellarg($local_path_for_rsync),
                        $remote_spec
                    );
                }
            }

            $output = array();
            $return_var = 0;
            exec($command . ' 2>&1', $output, $return_var);

            // Clean up temporary password file if it was created
            if (isset($tmp_pass_file) && file_exists($tmp_pass_file)) {
                unlink($tmp_pass_file);
            }

            if ($return_var !== 0) {
                $error_output = implode("\n", $output);

                // Check for common errors and provide helpful messages
                if (strpos($error_output, 'No such file or directory') !== false && strpos($error_output, 'change_dir') !== false) {
                    throw new Exception('Remote directory not found. Please verify the "Remote WordPress Path" in settings. Current path: ' . $remote_path . "\n\nFull error: " . $error_output);
                }

                throw new Exception('File sync failed: ' . $error_output);
            }

            BMU_Core::log_sync('files', $direction, 'success', 'Files synced successfully');
            return true;
        } catch (Exception $e) {
            // Clean up temporary password file on error
            if (isset($tmp_pass_file) && file_exists($tmp_pass_file)) {
                unlink($tmp_pass_file);
            }
            BMU_Core::log_sync('files', $direction, 'error', $e->getMessage());
            return false;
        }
    }

    /**
     * Create a backup of current files and database
     */
    public static function create_backup()
    {
        try {
            $backup_dir = WP_CONTENT_DIR . '/backups';

            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
            }

            $timestamp = date('Y-m-d-H-i-s');
            $backup_file = $backup_dir . '/backup-' . $timestamp . '.zip';
            $db_file = $backup_dir . '/database-' . $timestamp . '.sql';

            // Export database first
            $db_exported = BMU_Database::export_database($db_file);

            if (!$db_exported) {
                BMU_Core::log_sync('backup', 'local', 'warning', 'Database export failed - backup will only contain files');
            }

            $zip = new ZipArchive();
            if ($zip->open($backup_file, ZipArchive::CREATE) !== TRUE) {
                throw new Exception('Could not create backup zip file');
            }

            $settings = BMU_Core::get_settings();
            $exclude_paths = !empty($settings['exclude_paths']) ? $settings['exclude_paths'] : array();

            // Ensure wp-content/backups is excluded to avoid recursive backup
            if (!in_array('wp-content/backups', $exclude_paths)) {
                $exclude_paths[] = 'wp-content/backups';
            }
            self::add_directory_to_zip($zip, ABSPATH, ABSPATH, $exclude_paths);

            // Add database export to zip if successful
            if ($db_exported && file_exists($db_file)) {
                $db_size = filesize($db_file);
                if ($db_size > 0) {
                    $zip->addFile($db_file, 'database-backup.sql');
                } else {
                    BMU_Core::log_sync('backup', 'local', 'warning', 'Database file is empty - skipping');
                }
            }

            $zip->close();

            // Clean up temporary database file
            if (file_exists($db_file)) {
                @unlink($db_file);
            }

            $backup_size = filesize($backup_file);
            $message = 'Backup created: ' . basename($backup_file) . ' (' . size_format($backup_size) . ')';
            if ($db_exported) {
                $message .= ' - includes database';
            } else {
                $message .= ' - files only (database export failed)';
            }

            BMU_Core::log_sync('backup', 'local', 'success', $message);
            return $backup_file;
        } catch (Exception $e) {
            BMU_Core::log_sync('backup', 'local', 'error', $e->getMessage());
            return false;
        }
    }

    private static function add_directory_to_zip($zip, $dir, $base_dir, $exclude_paths = array())
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($base_dir));

                // Check if file should be excluded
                $should_exclude = false;
                foreach ($exclude_paths as $exclude) {
                    if (strpos($relative_path, $exclude) !== false) {
                        $should_exclude = true;
                        break;
                    }
                }

                if (!$should_exclude) {
                    $zip->addFile($file_path, $relative_path);
                }
            }
        }
    }

    /**
     * Get list of available backups
     */
    public static function get_backups()
    {
        $backup_dir = WP_CONTENT_DIR . '/backups';

        if (!file_exists($backup_dir)) {
            return array();
        }

        $backups = array();
        $files = scandir($backup_dir);

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                $backups[] = array(
                    'name' => $file,
                    'path' => $backup_dir . '/' . $file,
                    'size' => filesize($backup_dir . '/' . $file),
                    'date' => filemtime($backup_dir . '/' . $file)
                );
            }
        }

        usort($backups, function ($a, $b) {
            return $b['date'] - $a['date'];
        });

        return $backups;
    }

    private static function find_ssh()
    {
        $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        $possible_paths = array();

        if ($is_windows) {
            // Windows paths (Cygwin)
            $possible_paths[] = 'C:\\cygwin64\\bin\\ssh.exe';
            $possible_paths[] = 'C:\\cygwin\\bin\\ssh.exe';
        }

        // Unix/Linux/macOS/WSL paths
        $possible_paths[] = '/usr/bin/ssh';
        $possible_paths[] = '/usr/local/bin/ssh';
        $possible_paths[] = '/opt/homebrew/bin/ssh'; // macOS ARM
        $possible_paths[] = 'ssh'; // fallback to PATH

        foreach ($possible_paths as $path) {
            if (file_exists($path) || $path === 'ssh') {
                return $path;
            }
        }

        return 'ssh'; // fallback
    }
}
