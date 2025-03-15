<?php
// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
$options = array(
    'wcl_version',
    'wcl_cache_duration',
    'wcl_enable_encryption',
    'wcl_max_attempts',
    'wcl_block_duration',
    'wcl_cleanup_interval',
    'wcl_log_retention',
    'wcl_download_path',
    'wcl_encryption_key'
);

foreach ($options as $option) {
    delete_option($option);
}

// Drop custom tables
global $wpdb;
$tables = array(
    $wpdb->prefix . 'wcl_downloads',
    $wpdb->prefix . 'wcl_protections',
    $wpdb->prefix . 'wcl_access_logs',
    $wpdb->prefix . 'wcl_categories',
    $wpdb->prefix . 'wcl_statistics'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Delete cache directory
$cache_dir = WP_CONTENT_DIR . '/cache/wp-content-locker/';
if (is_dir($cache_dir)) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    WP_Filesystem();
    global $wp_filesystem;
    $wp_filesystem->delete($cache_dir, true);
}

// Clear any scheduled hooks
wp_clear_scheduled_hook('wcl_daily_maintenance');
wp_clear_scheduled_hook('wcl_cleanup_temp_files');