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
        $possible_paths = array(
            'C:\\cygwin64\\bin\\rsync.exe',
            'C:\\cygwin\\bin\\rsync.exe',
            'rsync', // For systems where it's in PATH
            '/usr/bin/rsync',
            '/usr/local/bin/rsync'
        );

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
        $possible_paths = array(
            'C:\\cygwin64\\bin\\sshpass.exe',
            'C:\\cygwin\\bin\\sshpass.exe',
            'sshpass',
            '/usr/bin/sshpass',
            '/usr/local/bin/sshpass'
        );

        foreach ($possible_paths as $path) {
            if (file_exists($path) || self::command_exists($path)) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Check if command exists
     */
    private static function command_exists($command)
    {
        $test = shell_exec(sprintf("which %s 2>/dev/null", escapeshellarg($command)));
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
            $remote_path = $settings['remote_path'];
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

            // Build SSH command with password support using sshpass if password is provided
            $ssh_cmd = '';
            $tmp_pass_file = null;

            if (!empty($ssh_password) && empty($settings['ssh_key_path'])) {
                // Find sshpass
                $sshpass_path = self::find_sshpass();
                if (!$sshpass_path) {
                    throw new Exception('sshpass not found. Please install sshpass or use SSH key authentication');
                }

                // Create temporary password file for sshpass
                $tmp_pass_file = tempnam(sys_get_temp_dir(), 'bmu_ssh_');
                file_put_contents($tmp_pass_file, $ssh_password);
                chmod($tmp_pass_file, 0600);

                // Convert paths to forward slashes for Cygwin compatibility
                $sshpass_path = str_replace('\\', '/', $sshpass_path);
                $tmp_pass_file = str_replace('\\', '/', $tmp_pass_file);

                // Use sshpass for password authentication with temp file
                // Use single quotes within the command to avoid issues with spaces
                $ssh_cmd = sprintf(
                    "'%s' -f '%s' ssh -p %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null",
                    $sshpass_path,
                    $tmp_pass_file,
                    $ssh_port
                );
            } else {
                // Use key-based authentication
                $ssh_cmd = sprintf('ssh -p %s -o StrictHostKeyChecking=no', $ssh_port);
                if (!empty($settings['ssh_key_path'])) {
                    $ssh_key_unix = str_replace('\\', '/', $settings['ssh_key_path']);
                    $ssh_cmd .= ' -i ' . $ssh_key_unix;
                }
            }

            // Convert local path for Cygwin if on Windows
            $local_path_for_rsync = $local_path;
            if (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Z]:\\\\/i', $local_path)) {
                // Convert Windows path to Cygwin format: C:\path\to -> /cygdrive/c/path/to
                $drive = strtolower(substr($local_path, 0, 1));
                $path = str_replace('\\', '/', substr($local_path, 3));
                $local_path_for_rsync = '/cygdrive/' . $drive . '/' . $path;
            }

            // Convert rsync path to forward slashes for Cygwin
            $rsync_path_unix = str_replace('\\', '/', $rsync_path);

            // Build rsync command
            if ($direction === 'pull') {
                // Pull from remote to local
                $remote_spec = $ssh_user . '@' . $ssh_host . ':' . $remote_path . '/';
                $command = sprintf(
                    '%s -avz -e "%s" %s "%s" "%s"',
                    $rsync_path_unix,
                    $ssh_cmd,
                    $excludes,
                    $remote_spec,
                    $local_path_for_rsync
                );
            } else {
                // Push from local to remote
                $remote_spec = $ssh_user . '@' . $ssh_host . ':' . $remote_path . '/';
                $command = sprintf(
                    '%s -avz -e "%s" %s "%s" "%s"',
                    $rsync_path_unix,
                    $ssh_cmd,
                    $excludes,
                    $local_path_for_rsync,
                    $remote_spec
                );
            }

            $output = array();
            $return_var = 0;
            exec($command . ' 2>&1', $output, $return_var);

            // Clean up temporary password file
            if ($tmp_pass_file && file_exists($tmp_pass_file)) {
                unlink($tmp_pass_file);
            }

            if ($return_var !== 0) {
                throw new Exception('File sync failed: ' . implode("\n", $output));
            }

            BMU_Core::log_sync('files', $direction, 'success', 'Files synced successfully');
            return true;
        } catch (Exception $e) {
            // Clean up temporary password file on error
            if (isset($tmp_pass_file) && $tmp_pass_file && file_exists($tmp_pass_file)) {
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

            // Add files to zip (skip wp-content/backups to avoid recursive backup)
            $exclude_paths[] = 'wp-content/backups';
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
}
