<?php 
// Thêm vào đầu file main-dashboard.php
if (WP_DEBUG) {
    error_log('Statistics data: ' . print_r($statistics, true));
}
if (!defined('ABSPATH')) exit;

// Initialize stats array with default values
$stats = isset($statistics) ? $statistics : array(
    'total_downloads' => 0,
    'active_protections' => 0,
    'recent_activities' => array()
);
// Gán biến $stats với giá trị mặc định nếu key không tồn tại
$stats = array(
    'total_downloads' => isset($statistics['total_downloads']) ? $statistics['total_downloads'] : 0,
    'active_protections' => isset($statistics['active_protections']) ? $statistics['active_protections'] : 0,
    'recent_activities' => isset($statistics['recent_activities']) ? $statistics['recent_activities'] : array()
);
?>

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
                <?php if (!empty($stats['recent_activities'])): ?>
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
                            <td><?php if (isset($object->details)) {
    echo $object->details;
} else {
    echo 'No details available';
}//echo esc_html($activity->details); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4"><?php _e('No recent activities found.', 'wp-content-locker'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.wcl-dashboard {
    margin: 20px;
}

.wcl-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.wcl-stat-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    border-radius: 4px;
    text-align: center;
}

.wcl-stat-box h3 {
    margin: 0 0 10px;
    color: #23282d;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #2271b1;
}

.wcl-recent-activities {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    border-radius: 4px;
}

.wcl-recent-activities h2 {
    margin-top: 0;
}

.wcl-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.wcl-status-success {
    background-color: #dff0d8;
    color: #3c763d;
}

.wcl-status-failed {
    background-color: #f2dede;
    color: #a94442;
}

.wcl-status-pending {
    background-color: #fcf8e3;
    color: #8a6d3b;
}

@media screen and (max-width: 782px) {
    .wcl-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .wcl-recent-activities {
        overflow-x: auto;
    }
}
</style>