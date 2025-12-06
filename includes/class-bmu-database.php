<?php

/**
 * Database sync functionality
 */

class BMU_Database
{

    /**
     * Export database to SQL file
     * 
     * @param string $filepath Path to save the SQL file
     * @param string $host Database host (optional, defaults to local)
     * @param string $user Database user (optional, defaults to local)
     * @param string $password Database password (optional, defaults to local)
     * @param string $name Database name (optional, defaults to local)
     */
    public static function export_database($filepath, $host = null, $user = null, $password = null, $name = null)
    {
        // Use provided credentials or fall back to WordPress constants
        $host = $host ?: DB_HOST;
        $user = $user ?: DB_USER;
        $password = $password ?: DB_PASSWORD;
        $name = $name ?: DB_NAME;

        try {
            // Try mysqldump first
            $mysqldump_path = self::find_mysqldump();

            if ($mysqldump_path) {
                $output = array();
                $return_var = 0;

                // Try mysqldump directly without pre-test (connection errors will be caught)
                BMU_Core::log_sync('database', 'export', 'info', 'Attempting mysqldump export...');

                $command = sprintf(
                    '%s --host=%s --user=%s --password=%s %s > %s 2>&1',
                    escapeshellarg($mysqldump_path),
                    escapeshellarg($host),
                    escapeshellarg($user),
                    escapeshellarg($password),
                    escapeshellarg($name),
                    escapeshellarg($filepath)
                );

                exec($command, $output, $return_var);

                if ($return_var === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                    BMU_Core::log_sync('database', 'export', 'success', 'Database exported via mysqldump: ' . filesize($filepath) . ' bytes');
                    return true;
                }

                // Log the error if mysqldump failed
                if ($return_var !== 0) {
                    // Read the file to get error message if it was written there
                    $file_content = file_exists($filepath) ? file_get_contents($filepath) : '';
                    BMU_Core::log_sync('database', 'export', 'info', 'mysqldump failed, trying PHP fallback. Error: ' . $file_content);
                }
            }

            // Fallback to PHP-based export if mysqldump fails
            BMU_Core::log_sync('database', 'export', 'info', 'Using PHP-based database export...');
            return self::php_export_database($filepath, $host, $user, $password, $name);
        } catch (Exception $e) {
            BMU_Core::log_sync('database', 'export', 'error', $e->getMessage());
            return false;
        }
    }

    /**
     * PHP-based database export (fallback method)
     */
    private static function php_export_database($filepath, $host = null, $user = null, $password = null, $name = null)
    {
        // Use provided credentials or fall back to WordPress constants
        $host = $host ?: DB_HOST;
        $user = $user ?: DB_USER;
        $password = $password ?: DB_PASSWORD;
        $name = $name ?: DB_NAME;

        // Connect to the database
        $wpdb_remote = new wpdb($user, $password, $name, $host);

        if (!empty($wpdb_remote->error)) {
            BMU_Core::log_sync('database', 'export', 'error', 'Database connection failed: ' . $wpdb_remote->error);
            return false;
        }

        try {
            $handle = fopen($filepath, 'w');
            if (!$handle) {
                throw new Exception('Could not open file for writing');
            }

            // Write header
            fwrite($handle, "-- WordPress Database Backup\n");
            fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
            fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
            fwrite($handle, "SET time_zone = \"+00:00\";\n\n");

            // Get all tables
            $tables = $wpdb_remote->get_results('SHOW TABLES', ARRAY_N);

            foreach ($tables as $table) {
                $table_name = $table[0];

                // Drop table statement
                fwrite($handle, "\n-- Table: $table_name\n");
                fwrite($handle, "DROP TABLE IF EXISTS `$table_name`;\n");

                // Create table statement
                $create_table = $wpdb_remote->get_row("SHOW CREATE TABLE `$table_name`", ARRAY_N);
                fwrite($handle, $create_table[1] . ";\n\n");

                // Insert data
                $rows = $wpdb_remote->get_results("SELECT * FROM `$table_name`", ARRAY_A);

                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $values = array();
                        foreach ($row as $value) {
                            if (is_null($value)) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = "'" . $wpdb_remote->_real_escape($value) . "'";
                            }
                        }
                        $insert = "INSERT INTO `$table_name` VALUES (" . implode(', ', $values) . ");\n";
                        fwrite($handle, $insert);
                    }
                    fwrite($handle, "\n");
                }
            }

            fclose($handle);

            $file_size = filesize($filepath);
            BMU_Core::log_sync('database', 'export', 'success', 'Database exported via PHP: ' . $file_size . ' bytes from ' . count($tables) . ' tables');

            return true;
        } catch (Exception $e) {
            if (isset($handle)) {
                fclose($handle);
            }
            BMU_Core::log_sync('database', 'export', 'error', 'PHP export failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Import SQL file to database
     * 
     * @param string $filepath Path to the SQL file
     * @param string $host Database host (optional, defaults to local)
     * @param string $user Database user (optional, defaults to local)
     * @param string $password Database password (optional, defaults to local)
     * @param string $name Database name (optional, defaults to local)
     */
    public static function import_database($filepath, $host = null, $user = null, $password = null, $name = null)
    {
        // Use provided credentials or fall back to WordPress constants
        $host = $host ?: DB_HOST;
        $user = $user ?: DB_USER;
        $password = $password ?: DB_PASSWORD;
        $name = $name ?: DB_NAME;

        try {
            if (!file_exists($filepath)) {
                throw new Exception('SQL file not found: ' . $filepath);
            }

            $mysql_path = self::find_mysql();

            if (!$mysql_path) {
                throw new Exception('mysql not found. Please configure the path manually.');
            }

            $output = array();
            $return_var = 0;

            $command = sprintf(
                '%s --host=%s --user=%s --password=%s %s < %s 2>&1',
                escapeshellarg($mysql_path),
                escapeshellarg($host),
                escapeshellarg($user),
                escapeshellarg($password),
                escapeshellarg($name),
                escapeshellarg($filepath)
            );

            exec($command, $output, $return_var);

            if ($return_var !== 0) {
                $error_output = implode("\n", $output);
                BMU_Core::log_sync('database', 'import', 'error', 'mysql command failed: ' . $error_output);
                throw new Exception('Database import failed: ' . $error_output);
            }

            return true;
        } catch (Exception $e) {
            BMU_Core::log_sync('database', 'import', 'error', $e->getMessage());
            return false;
        }
    }

    /**
     * Search and replace URLs in database
     */
    public static function search_replace($search, $replace)
    {
        global $wpdb;

        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);

        foreach ($tables as $table) {
            $table_name = $table[0];

            // Get all columns
            $columns = $wpdb->get_results("DESCRIBE $table_name", ARRAY_A);

            foreach ($columns as $column) {
                $column_name = $column['Field'];
                $column_type = $column['Type'];

                // Only process text columns
                if (strpos($column_type, 'text') !== false || strpos($column_type, 'varchar') !== false) {
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE $table_name SET $column_name = REPLACE($column_name, %s, %s)",
                            $search,
                            $replace
                        )
                    );
                }
            }
        }

        return true;
    }

    private static function find_mysqldump()
    {
        $possible_paths = array(
            'mysqldump',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            'C:\\Program Files\\MariaDB 12.1\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.27\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe'
        );

        foreach ($possible_paths as $path) {
            if (self::command_exists($path)) {
                return $path;
            }
        }

        return false;
    }

    private static function find_mysql()
    {
        $possible_paths = array(
            'mysql',
            '/usr/bin/mysql',
            '/usr/local/bin/mysql',
            '/usr/local/mysql/bin/mysql',
            'C:\\Program Files\\MariaDB 12.1\\bin\\mysql.exe',
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.27\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe'
        );

        foreach ($possible_paths as $path) {
            if (self::command_exists($path)) {
                return $path;
            }
        }

        return false;
    }

    private static function command_exists($command)
    {
        // For file paths, check if file exists directly
        if (file_exists($command)) {
            return true;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $where = 'where';
        } else {
            $where = 'which';
        }

        $output = shell_exec("$where " . escapeshellarg($command) . " 2>&1");
        return !empty($output);
    }
}
