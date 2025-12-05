<?php

/**
 * Database sync functionality
 */

class BMU_Database
{

    /**
     * Export current database to SQL file
     */
    public static function export_database($filepath)
    {
        try {
            $output = array();
            $return_var = 0;

            $mysqldump_path = self::find_mysqldump();

            if (!$mysqldump_path) {
                throw new Exception('mysqldump not found. Please configure the path manually.');
            }

            $command = sprintf(
                '%s --host=%s --user=%s --password=%s %s > %s',
                escapeshellarg($mysqldump_path),
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASSWORD),
                escapeshellarg(DB_NAME),
                escapeshellarg($filepath)
            );

            exec($command, $output, $return_var);

            if ($return_var !== 0) {
                throw new Exception('Database export failed: ' . implode("\n", $output));
            }

            return true;
        } catch (Exception $e) {
            BMU_Core::log_sync('database', 'export', 'error', $e->getMessage());
            return false;
        }
    }

    /**
     * Import SQL file to database
     */
    public static function import_database($filepath)
    {
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
                '%s --host=%s --user=%s --password=%s %s < %s',
                escapeshellarg($mysql_path),
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASSWORD),
                escapeshellarg(DB_NAME),
                escapeshellarg($filepath)
            );

            exec($command, $output, $return_var);

            if ($return_var !== 0) {
                throw new Exception('Database import failed: ' . implode("\n", $output));
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
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $where = 'where';
        } else {
            $where = 'which';
        }

        $output = shell_exec("$where $command 2>&1");
        return !empty($output);
    }
}
