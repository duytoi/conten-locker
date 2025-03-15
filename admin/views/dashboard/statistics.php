<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap wcl-statistics">
    <h1><?php _e('Download Statistics', 'wp-content-locker'); ?></h1>

    <!-- Period Selection -->
    <div class="wcl-period-selection">
        <form method="get">
            <input type="hidden" name="page" value="wcl-statistics">
            <select name="period">
                <option value="7days" <?php selected($period, '7days'); ?>>
                    <?php _e('Last 7 Days', 'wp-content-locker'); ?>
                </option>
                <option value="30days" <?php selected($period, '30days'); ?>>
                    <?php _e('Last 30 Days', 'wp-content-locker'); ?>
                </option>
                <option value="90days" <?php selected($period, '90days'); ?>>
                    <?php _e('Last 90 Days', 'wp-content-locker'); ?>
                </option>
                <option value="all" <?php selected($period, 'all'); ?>>
                    <?php _e('All Time', 'wp-content-locker'); ?>
                </option>
            </select>
            <input type="submit" class="button" value="<?php _e('Apply', 'wp-content-locker'); ?>">
        </form>
    </div>

    <!-- Overview Cards -->
    <div class="wcl-stats-cards">
        <div class="wcl-stat-card">
            <h3><?php _e('Total Downloads', 'wp-content-locker'); ?></h3>
            <div class="wcl-stat-value"><?php echo number_format($stats['downloads']['total_downloads']); ?></div>
            <div class="wcl-stat-label"><?php _e('Downloads', 'wp-content-locker'); ?></div>
        </div>

        <div class="wcl-stat-card">
            <h3><?php _e('Unique Downloads', 'wp-content-locker'); ?></h3>
            <div class="wcl-stat-value"><?php echo number_format($stats['downloads']['unique_downloads']); ?></div>
            <div class="wcl-stat-label"><?php _e('Unique Users', 'wp-content-locker'); ?></div>
        </div>

        <div class="wcl-stat-card">
            <h3><?php _e('Protection Success Rate', 'wp-content-locker'); ?></h3>
            <div class="wcl-stat-value"><?php echo number_format($stats['protections']['success_rate'], 1); ?>%</div>
            <div class="wcl-stat-label"><?php _e('Success Rate', 'wp-content-locker'); ?></div>
        </div>
    </div>

    <!-- Downloads Chart -->
    <div class="wcl-chart-container">
        <h2><?php _e('Download Trends', 'wp-content-locker'); ?></h2>
        <canvas id="downloadsChart"></canvas>
    </div>

    <!-- Top Downloads Table -->
    <div class="wcl-top-downloads">
        <h2><?php _e('Top Downloads', 'wp-content-locker'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Title', 'wp-content-locker'); ?></th>
                    <th><?php _e('Total Downloads', 'wp-content-locker'); ?></th>
                    <th><?php _e('Unique Downloads', 'wp-content-locker'); ?></th>
                    <th><?php _e('Conversion Rate', 'wp-content-locker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['top_downloads'] as $download): ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wcl-downloads&action=edit&id=' . $download['id'])); ?>">
                                <?php echo esc_html($download['title']); ?>
                            </a>
                        </td>
                        <td><?php echo number_format($download['total_downloads']); ?></td>
                        <td><?php echo number_format($download['unique_downloads']); ?></td>
                        <td><?php echo number_format($download['conversion_rate'], 1); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Activity -->
    <div class="wcl-recent-activity">
        <h2><?php _e('Recent Activity', 'wp-content-locker'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Time', 'wp-content-locker'); ?></th>
                    <th><?php _e('Type', 'wp-content-locker'); ?></th>
                    <th><?php _e('Entity', 'wp-content-locker'); ?></th>
                    <th><?php _e('Action', 'wp-content-locker'); ?></th>
                    <th><?php _e('Status', 'wp-content-locker'); ?></th>
                    <th><?php _e('IP Address', 'wp-content-locker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['recent_activity'] as $activity): ?>
                    <tr>
                        <td><?php echo esc_html(human_time_diff(strtotime($activity['timestamp']))); ?></td>
                        <td><?php echo esc_html(ucfirst($activity['type'])); ?></td>
                        <td>
                            <?php if (isset($activity['entity'])): ?>
                                <?php if ($activity['type'] === 'download'): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=wcl-downloads&action=edit&id=' . $activity['entity']['id'])); ?>">
                                        <?php echo esc_html($activity['entity']['title']); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo esc_html($activity['entity']['type']); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(ucfirst($activity['action'])); ?></td>
                        <td>
                            <span class="wcl-status wcl-status-<?php echo esc_attr($activity['status']); ?>">
                                <?php echo esc_html(ucfirst($activity['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($activity['ip_address']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Initialize Charts
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('downloadsChart').getContext('2d');
    const data = <?php echo json_encode($stats['downloads']['daily_stats']); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: Object.keys(data),
            datasets: [
                {
                    label: '<?php _e('Total Downloads', 'wp-content-locker'); ?>',
                    data: Object.values(data).map(stat => stat.total),
                    borderColor: '#007bff',
                    fill: false
                },
                {
                    label: '<?php _e('Unique Downloads', 'wp-content-locker'); ?>',
                    data: Object.values(data).map(stat => stat.unique),
                    borderColor: '#28a745',
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>