<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap bmu-settings-page">
    <h1>Back Me Up - Settings</h1>

    <form id="bmu-settings-form">
        <?php wp_nonce_field('bmu_ajax_nonce', 'bmu_nonce'); ?>

        <div class="bmu-card">
            <h2>Remote Server Configuration</h2>

            <table class="form-table">
                <tr>
                    <th><label for="remote_url">Remote Site URL</label></th>
                    <td>
                        <input type="url" id="remote_url" name="remote_url" value="<?php echo esc_attr($settings['remote_url']); ?>" class="regular-text" placeholder="https://example.com">
                        <p class="description">Full URL of your live website</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="remote_path">Remote WordPress Path</label></th>
                    <td>
                        <input type="text" id="remote_path" name="remote_path" value="<?php echo esc_attr($settings['remote_path']); ?>" class="regular-text" placeholder="/var/www/html">
                        <p class="description">Absolute path to WordPress installation on remote server</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="bmu-card">
            <h2>SSH Connection</h2>

            <table class="form-table">
                <tr>
                    <th><label for="ssh_host">SSH Host</label></th>
                    <td>
                        <input type="text" id="ssh_host" name="ssh_host" value="<?php echo esc_attr($settings['ssh_host']); ?>" class="regular-text" placeholder="example.com">
                    </td>
                </tr>

                <tr>
                    <th><label for="ssh_user">SSH Username</label></th>
                    <td>
                        <input type="text" id="ssh_user" name="ssh_user" value="<?php echo esc_attr($settings['ssh_user']); ?>" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th><label for="ssh_port">SSH Port</label></th>
                    <td>
                        <input type="number" id="ssh_port" name="ssh_port" value="<?php echo esc_attr($settings['ssh_port']); ?>" class="small-text" placeholder="22">
                    </td>
                </tr>

                <tr>
                    <th><label for="ssh_key_path">SSH Key Path</label></th>
                    <td>
                        <input type="text" id="ssh_key_path" name="ssh_key_path" value="<?php echo esc_attr($settings['ssh_key_path']); ?>" class="regular-text" placeholder="~/.ssh/id_rsa">
                        <p class="description">Path to your private SSH key file (leave empty to use password)</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="ssh_password">SSH Password</label></th>
                    <td>
                        <input type="password" id="ssh_password" name="ssh_password" value="<?php echo esc_attr(isset($settings['ssh_password']) ? $settings['ssh_password'] : ''); ?>" class="regular-text" autocomplete="new-password">
                        <p class="description">SSH password (only used if SSH key is not provided)</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="bmu-card">
            <h2>Remote Database Configuration</h2>

            <table class="form-table">
                <tr>
                    <th><label for="db_host">Database Host</label></th>
                    <td>
                        <input type="text" id="db_host" name="db_host" value="<?php echo esc_attr($settings['db_host']); ?>" class="regular-text" placeholder="localhost">
                    </td>
                </tr>

                <tr>
                    <th><label for="db_name">Database Name</label></th>
                    <td>
                        <input type="text" id="db_name" name="db_name" value="<?php echo esc_attr($settings['db_name']); ?>" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th><label for="db_user">Database User</label></th>
                    <td>
                        <input type="text" id="db_user" name="db_user" value="<?php echo esc_attr($settings['db_user']); ?>" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th><label for="db_password">Database Password</label></th>
                    <td>
                        <input type="password" id="db_password" name="db_password" value="<?php echo esc_attr($settings['db_password']); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
        </div>

        <div class="bmu-card">
            <h2>Sync Options</h2>

            <table class="form-table">
                <tr>
                    <th><label for="sync_direction">Default Sync Direction</label></th>
                    <td>
                        <select id="sync_direction" name="sync_direction">
                            <option value="pull" <?php selected($settings['sync_direction'], 'pull'); ?>>Pull (Live → Local)</option>
                            <option value="push" <?php selected($settings['sync_direction'], 'push'); ?>>Push (Local → Live)</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label>Exclude Paths</label></th>
                    <td>
                        <div id="exclude-paths-container">
                            <?php
                            $exclude_paths = !empty($settings['exclude_paths']) ? $settings['exclude_paths'] : array('wp-content/cache', 'wp-content/backup');
                            foreach ($exclude_paths as $index => $path) :
                            ?>
                                <div class="exclude-path-row">
                                    <input type="text" name="exclude_paths[]" value="<?php echo esc_attr($path); ?>" class="regular-text">
                                    <button type="button" class="button remove-exclude-path">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="add-exclude-path" class="button">Add Path</button>
                        <p class="description">Paths to exclude from file sync (relative to WordPress root)</p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary">Save Settings</button>
        </p>

        <div id="bmu-settings-message" style="display: none;"></div>
    </form>
</div>