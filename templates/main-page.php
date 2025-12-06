<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap bmu-main-page">
    <h1>
        <span class="dashicons dashicons-update"></span>
        BackMeUp - WordPress Sync
    </h1>

    <div class="bmu-card">
        <h2>Quick Sync</h2>

        <div class="bmu-sync-status">
            <p><strong>Last Sync:</strong> <?php echo esc_html($last_sync); ?></p>
            <p><strong>Environment:</strong> <?php echo defined('WP_LOCAL_DEV') ? 'Local' : 'Live'; ?></p>
        </div>

        <div class="bmu-sync-buttons">
            <button id="bmu-sync-pull" class="button button-primary button-large">
                <span class="dashicons dashicons-download"></span>
                Pull from Live
            </button>

            <button id="bmu-sync-push" class="button button-secondary button-large">
                <span class="dashicons dashicons-upload"></span>
                Push to Live
            </button>
        </div>

        <div id="bmu-sync-progress" style="display: none;">
            <div class="bmu-progress-bar">
                <div class="bmu-progress-fill"></div>
            </div>
            <p id="bmu-sync-message">Syncing...</p>
        </div>

        <div id="bmu-sync-result" style="display: none;"></div>
    </div>

    <div class="bmu-card">
        <h2>Recent Backups</h2>

        <p class="description">
            <strong>Backup Location:</strong> <?php echo esc_html(str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR . '/backups')); ?>
        </p>

        <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
            <button id="bmu-create-backup" class="button button-secondary">
                <span class="dashicons dashicons-database-export"></span> Create Backup Now
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
                                <button class="button button-small button-primary bmu-restore-backup" data-file="<?php echo esc_attr($backup['name']); ?>">
                                    <span class="dashicons dashicons-database-import"></span> Restore
                                </button>
                                <a href="<?php echo esc_url(content_url('backups/' . $backup['name'])); ?>" class="button button-small" download>Download</a>
                                <button class="button button-small bmu-delete-backup" data-file="<?php echo esc_attr($backup['name']); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No backups found. Backups are created automatically before each sync.</p>
        <?php endif; ?>
    </div>

    <div class="bmu-card">
        <h2>Quick Start Guide</h2>
        <ol>
            <li>Configure your <a href="<?php echo admin_url('admin.php?page=back-me-up-settings'); ?>">connection settings</a> first</li>
            <li>Ensure SSH access is configured with key-based authentication</li>
            <li>Install rsync on both local and remote servers</li>
            <li>Click "Pull from Live" to sync live site to local</li>
            <li>Click "Push to Live" to sync local changes to live site</li>
        </ol>

        <div class="notice notice-warning inline">
            <p><strong>Warning:</strong> Always create a backup before syncing. Syncing will overwrite files and database on the target environment.</p>
        </div>
    </div>
</div>