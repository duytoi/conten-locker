<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap wcl-dashboard">
    <h1><?php _e('Content Locker Dashboard', 'wp-content-locker'); ?></h1>

    <div class="wcl-stats-grid">
        <div class="wcl-stat-box">
            <h3><?php _e('Total Downloads', 'wp-content-locker'); ?></h3>
            <div class="stat-number"><?php echo esc_html($stats['total_downloads']); ?></div>
        </div>

        <div class="wcl-stat-box">
            <h3><?php _e('Active Protections', 'wp-content-locker'); ?></h3>
            <div class="stat-number"><?php echo esc_html($stats['active_protections']); ?></div>
        </div>
    </div>

    <div class="wcl-recent-activities">
        <h2><?php _e('Recent Activities', 'wp-content-locker'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Time', 'wp-content-locker'); ?></th>
                    <th><?php _e('IP Address', 'wp-content-locker'); ?></th>
                    <th><?php _e('Status', 'wp-content-locker'); ?></th>
                    <th><?php _e('Details', 'wp-content-locker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['recent_activities'] as $activity): ?>
                    <tr>
                        <td><?php echo esc_html(
                            human_time_diff(
                                strtotime($activity->accessed_at),
                                current_time('timestamp')
                            ) . ' ago'
                        ); ?></td>
                        <td><?php echo esc_html($activity->ip_address); ?></td>
                        <td>
                            <span class="wcl-status wcl-status-<?php echo esc_attr($activity->status); ?>">
                                <?php echo esc_html(ucfirst($activity->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($activity->details); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>