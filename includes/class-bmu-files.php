<?php

/**
 * File sync functionality
 */

class BMU_Files
{

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
            if (!empty($ssh_password) && empty($settings['ssh_key_path'])) {
                // Use sshpass for password authentication
                $ssh_cmd = sprintf(
                    'sshpass -p %s ssh -p %s -o StrictHostKeyChecking=no',
                    escapeshellarg($ssh_password),
                    escapeshellarg($ssh_port)
                );
            } else {
                // Use key-based authentication
                $ssh_cmd = sprintf('ssh -p %s', escapeshellarg($ssh_port));
                if (!empty($settings['ssh_key_path'])) {
                    $ssh_cmd .= ' -i ' . escapeshellarg($settings['ssh_key_path']);
                }
            }

            // Build rsync command
            if ($direction === 'pull') {
                // Pull from remote to local
                $command = sprintf(
                    'rsync -avz -e "%s" %s %s@%s:%s/ %s',
                    $ssh_cmd,
                    $excludes,
                    escapeshellarg($ssh_user),
                    escapeshellarg($ssh_host),
                    escapeshellarg($remote_path),
                    escapeshellarg($local_path)
                );
            } else {
                // Push from local to remote
                $command = sprintf(
                    'rsync -avz -e "%s" %s %s %s@%s:%s/',
                    $ssh_cmd,
                    $excludes,
                    escapeshellarg($local_path),
                    escapeshellarg($ssh_user),
                    escapeshellarg($ssh_host),
                    escapeshellarg($remote_path)
                );
            }

            $output = array();
            $return_var = 0;
            exec($command . ' 2>&1', $output, $return_var);

            if ($return_var !== 0) {
                throw new Exception('File sync failed: ' . implode("\n", $output));
            }

            BMU_Core::log_sync('files', $direction, 'success', 'Files synced successfully');
            return true;
        } catch (Exception $e) {
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

            $zip = new ZipArchive();
            if ($zip->open($backup_file, ZipArchive::CREATE) !== TRUE) {
                throw new Exception('Could not create backup zip file');
            }

            $settings = BMU_Core::get_settings();
            $exclude_paths = !empty($settings['exclude_paths']) ? $settings['exclude_paths'] : array();

            // Add files to zip
            self::add_directory_to_zip($zip, ABSPATH, ABSPATH, $exclude_paths);

            // Add database export to zip if successful
            if ($db_exported && file_exists($db_file)) {
                $zip->addFile($db_file, 'database-backup.sql');
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
