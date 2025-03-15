<?php
namespace WP_Content_Locker\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class Deactivator {
    /**
     * Plugin deactivation handler
     */
    public static function deactivate() {
        try {
            // Log start of deactivation
            error_log('WP Content Locker: Starting deactivation process');

            // Clear scheduled hooks
            self::clear_scheduled_hooks();

            // Clear cache
            self::clear_cache();

            // Clear temporary data
            self::clear_temp_data();

            // Flush rewrite rules
            flush_rewrite_rules();

            // Log successful deactivation
            error_log('WP Content Locker: Deactivation completed successfully');

        } catch (\Exception $e) {
            // Log error
            error_log('WP Content Locker: Deactivation error - ' . $e->getMessage());
        }
    }

    /**
     * Clear scheduled hooks
     */
    private static function clear_scheduled_hooks() {
        $hooks = array(
            'wcl_daily_maintenance',
            'wcl_cleanup_temp_files',
            'wcl_statistics_update'
        );

        foreach ($hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }

    /**
     * Clear cache
     */
    private static function clear_cache() {
        global $wpdb;
        
        // Clear transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_wcl_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_wcl_%'");

        // Clear cache directory
        $upload_dir = wp_upload_dir();
        $cache_dir = trailingslashit($upload_dir['basedir']) . 'wcl-cache';
        
        if (is_dir($cache_dir)) {
            self::delete_directory($cache_dir);
        }
    }

    /**
     * Clear temporary data
     */
    private static function clear_temp_data() {
        // Clear any temporary options
        delete_option('wcl_temp_data');
        delete_option('wcl_installation_status');
    }

    /**
     * Delete directory and its contents
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dir);
    }
}