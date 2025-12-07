<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap bmu-main-page">
    <h1>
        <span class="dashicons dashicons-backup"></span>
        Back Me Up - Backup & Restore
    </h1>

    <div class="bmu-card">
        <h2>Backup Management</h2>

        <p class="description">
            <strong>Backup Location:</strong> <?php echo esc_html(WP_CONTENT_DIR . '/backups'); ?>
        </p>

        <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
            <button id="bmu-backup-now" class="button button-secondary">
                <span class="dashicons dashicons-backup"></span>
                Backup Now
            </button>
            <?php if (!empty($backups)) : ?>
                <button id="bmu-delete-all-backups" class="button button-link-delete" style="color: #b32d2e;">
                    <span class="dashicons dashicons-trash"></span> Delete All Backups
                </button>
            <?php endif; ?>
        </div>

        <?php if (!empty($backups)) : ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Backup File</th>
                        <th>Size</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($backups, 0, 10) as $backup) : ?>
                        <tr data-backup-file="<?php echo esc_attr($backup['name']); ?>">
                            <td><?php echo esc_html($backup['name']); ?></td>
                            <td><?php echo size_format($backup['size']); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', $backup['date']); ?></td>
                            <td>
                                <a href="<?php echo esc_url(content_url('backups/' . $backup['name'])); ?>" class="button button-small" download>Download</a>
                                <button class="button button-small bmu-restore-backup" data-file="<?php echo esc_attr($backup['name']); ?>">Restore</button>
                                <button class="button button-small bmu-delete-backup" data-file="<?php echo esc_attr($backup['name']); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No backups found. Create a backup to get started.</p>
        <?php endif; ?>
    </div>

    <div class="bmu-card">
        <h2>How It Works</h2>
        <ol>
            <li><strong>Create Backup:</strong> Click "Backup Now" to create a complete backup (files + database) of this WordPress installation</li>
            <li><strong>Download:</strong> Download backup files to transfer between servers</li>
            <li><strong>Upload & Restore:</strong> Upload a backup file to the backups folder (<code><?php echo esc_html(WP_CONTENT_DIR . '/backups'); ?></code>) and it will appear in the list above</li>
            <li><strong>Automatic URL Replacement:</strong> URLs are automatically updated when restoring between different domains</li>
        </ol>

        <div class="notice notice-info inline">
            <p><strong>✓ Cross-Platform:</strong> Works on Windows, Mac, and Linux. No external dependencies required!</p>
        </div>

        <div class="notice notice-warning inline" style="margin-top: 10px;">
            <p><strong>Warning:</strong> Restoring a backup will overwrite all files and database content on this WordPress installation. Always backup before restoring!</p>
        </div>
    </div>
</div>