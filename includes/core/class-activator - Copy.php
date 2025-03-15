<?php
namespace WP_Content_Locker\Core;

class WP_Content_Locker_Activator {
    /**
     * Activate the plugin
     */
    public static function activate() {
        self::create_database_tables();
        self::create_cache_directories();
        self::clear_all_caches();
        self::set_default_options();
        self::schedule_maintenance_tasks();
    }

    /**
     * Create necessary database tables
     */
    private static function create_database_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Downloads table
        $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wcl_downloads` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `description` text,
            `file_path` text NOT NULL,
            `file_type` varchar(50),
            `file_size` bigint(20),
            `url` text NOT NULL,
            `category_id` bigint(20),
            `download_count` int DEFAULT 0,
            `is_encrypted` tinyint(1) DEFAULT 0,
            `status` varchar(20) DEFAULT 'active',
            `expires_at` datetime NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_status` (`status`),
            KEY `idx_category` (`category_id`),
            KEY `idx_created` (`created_at`)
        ) $charset_collate;";
        dbDelta($sql);

        // Protections table
        $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wcl_protections` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `content_id` bigint(20) NOT NULL,
            `protection_type` varchar(50),
            `password` varchar(255),
            `countdown_time` int DEFAULT 60,
            `max_attempts` int DEFAULT 3,
            `block_duration` int DEFAULT 3600,
            `status` varchar(20),
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_content` (`content_id`),
            KEY `idx_type` (`protection_type`)
        ) $charset_collate;";
        dbDelta($sql);

        // Access logs table
        $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wcl_access_logs` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `protection_id` bigint(20) NOT NULL,
            `user_id` bigint(20),
            `ip_address` varchar(45),
            `user_agent` text,
            `status` varchar(20),
            `attempt_count` int DEFAULT 1,
            `accessed_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_protection` (`protection_id`),
            KEY `idx_ip` (`ip_address`),
            KEY `idx_accessed` (`accessed_at`)
        ) $charset_collate;";
        dbDelta($sql);

        // Categories table
        $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wcl_categories` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `slug` varchar(255) NOT NULL,
            `description` text,
            `parent_id` bigint(20) DEFAULT 0,
            `status` varchar(20) DEFAULT 'active',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_slug` (`slug`),
            KEY `idx_parent` (`parent_id`)
        ) $charset_collate;";
        dbDelta($sql);
    }

    /**
     * Create cache directories
     */
    private static function create_cache_directories() {
        $directories = array(
            WP_CONTENT_LOCKER_CACHE_DIR,
            WP_CONTENT_LOCKER_CACHE_DIR . 'assets/',
            WP_CONTENT_LOCKER_CACHE_DIR . 'downloads/',
            WP_CONTENT_LOCKER_CACHE_DIR . 'temp/'
        );

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }

            // Create .htaccess to protect cache directory
            if (!file_exists($dir . '.htaccess')) {
                file_put_contents($dir . '.htaccess', 
                    "Order deny,allow\n" .
                    "Deny from all\n"
                );
            }

            // Create index.php to prevent directory listing
            if (!file_exists($dir . 'index.php')) {
                file_put_contents($dir . 'index.php', 
                    "<?php\n" .
                    "// Silence is golden.\n"
                );
            }
        }
    }

    /**
     * Clear all caches
     */
    private static function clear_all_caches() {
        // Clear plugin cache
        self::clear_directory(WP_CONTENT_LOCKER_CACHE_DIR . 'assets/');
        self::clear_directory(WP_CONTENT_LOCKER_CACHE_DIR . 'downloads/');
        self::clear_directory(WP_CONTENT_LOCKER_CACHE_DIR . 'temp/');

        // Clear WordPress transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_wcl_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_wcl_%'");

        // Clear object cache if available
        if (wp_using_ext_object_cache()) {
            wp_cache_flush();
        }
    }

    /**
     * Set default options
     */
    private static function set_default_options() {
        $default_options = array(
            'wcl_version' => WP_CONTENT_LOCKER_VERSION,
            'wcl_cache_duration' => 3600,
            'wcl_enable_encryption' => true,
            'wcl_max_attempts' => 3,
            'wcl_block_duration' => 3600,
            'wcl_cleanup_interval' => 'daily',
            'wcl_log_retention' => 30 // days
        );

        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }

    /**
     * Schedule maintenance tasks
     */
    private static function schedule_maintenance_tasks() {
        if (!wp_next_scheduled('wcl_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'wcl_daily_maintenance');
        }

        if (!wp_next_scheduled('wcl_cleanup_temp_files')) {
            wp_schedule_event(time(), 'hourly', 'wcl_cleanup_temp_files');
        }
    }

    /**
     * Helper function to clear directory
     */
    private static function clear_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Update plugin version
     */
    public static function update_version() {
        $installed_version = get_option('wcl_version');
        
        if ($installed_version !== WP_CONTENT_LOCKER_VERSION) {
            self::clear_all_caches();
            update_option('wcl_version', WP_CONTENT_LOCKER_VERSION);
        }
    }
}