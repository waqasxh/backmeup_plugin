<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap bmu-logs-page">
    <h1>Sync Logs</h1>

    <div class="bmu-card">
        <?php if (!empty($logs)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Direction</th>
                        <th>Status</th>
                        <th>Message</th>
                        <th>Started</th>
                        <th>Completed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html($log->id); ?></td>
                            <td><?php echo esc_html($log->sync_type); ?></td>
                            <td>
                                <?php if ($log->direction === 'pull') : ?>
                                    <span class="dashicons dashicons-download"></span> Pull
                                <?php else : ?>
                                    <span class="dashicons dashicons-upload"></span> Push
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="bmu-status bmu-status-<?php echo esc_attr($log->status); ?>">
                                    <?php echo esc_html($log->status); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log->message); ?></td>
                            <td><?php echo esc_html($log->started_at); ?></td>
                            <td><?php echo esc_html($log->completed_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No sync logs found.</p>
        <?php endif; ?>
    </div>
</div>