<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap bmu-settings-page">
    <h1>Back Me Up - Settings</h1>

    <form id="bmu-settings-form">
        <?php wp_nonce_field('bmu_ajax_nonce', 'bmu_nonce'); ?>

        <div class="bmu-card">
            <h2>Backup Options</h2>

            <table class="form-table">
                <tr>
                    <th><label>Exclude Paths</label></th>
                    <td>
                        <div id="exclude-paths-container">
                            <?php
                            $exclude_paths = !empty($settings['exclude_paths']) ? $settings['exclude_paths'] : array('wp-content/cache', 'wp-content/backups');
                            foreach ($exclude_paths as $index => $path) :
                            ?>
                                <div class="exclude-path-row">
                                    <input type="text" name="exclude_paths[]" value="<?php echo esc_attr($path); ?>" class="regular-text">
                                    <button type="button" class="button remove-exclude-path">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="add-exclude-path" class="button">Add Path</button>
                        <p class="description">Paths to exclude from backups (relative to WordPress root)</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="bmu-card">
            <h2>About This Plugin</h2>
            <p><strong>Back Me Up</strong> - Simple, cross-platform WordPress backup and restore solution.</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>✓ Works on Windows, Mac, and Linux</li>
                <li>✓ No external dependencies (rsync, SSH, etc.)</li>
                <li>✓ Pure PHP implementation</li>
                <li>✓ Automatic URL replacement when restoring</li>
                <li>✓ Complete backups (files + database)</li>
            </ul>
            <p><strong>Workflow:</strong></p>
            <ol style="margin-left: 20px;">
                <li>Create backup on live server → Download backup file</li>
                <li>Upload backup file to local server → Restore backup</li>
                <li>URLs automatically updated during restore</li>
            </ol>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary">Save Settings</button>
        </p>

        <div id="bmu-settings-message" style="display: none;"></div>
    </form>
</div>