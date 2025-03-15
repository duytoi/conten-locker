<?php
namespace WP_Content_Locker\Core;

class Activator {
    public static function activate() {
        // Create database tables
        self::create_database_tables();
        
        // Setup other components
        self::create_cache_directories();
        self::set_default_options();
        self::schedule_maintenance_tasks();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    private static function create_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $tables = [
	"{$wpdb->prefix}wcl_downloads" => 
        "CREATE TABLE {$wpdb->prefix}wcl_downloads (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            file_path text NOT NULL,
            file_type varchar(50),
            file_size bigint(20),
            url text NOT NULL,
            category_id bigint(20),
            download_count int DEFAULT 0,
            is_encrypted tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            expires_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_category (category_id),
            KEY idx_created (created_at)
        ) $charset_collate;",
		"{$wpdb->prefix}wcl_protections" => 
        "CREATE TABLE {$wpdb->prefix}wcl_protections (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			content_id bigint(20) NOT NULL,
			protection_type varchar(50),
			countdown_mode enum('single', 'double') DEFAULT 'single',
			countdown_first int(11) DEFAULT 60,
			countdown_second int(11) DEFAULT 60,
			first_message text,
			second_message text,
			redirect_message text,
			requires_ga tinyint(1) DEFAULT 0,
			password varchar(255),
			countdown_time int DEFAULT 60,
			max_attempts int DEFAULT 3,
			block_duration int DEFAULT 3600,
			countdown_enabled tinyint(1) DEFAULT 0,
			countdown_duration int(11) DEFAULT 0,
			status varchar(20),
			ga4_measurement_id varchar(255),          /* Thêm field GA4 Measurement ID */
			gtm_container_id varchar(255),            /* Thêm field GTM Container ID */
			ga4_enabled tinyint(1) DEFAULT 0,         /* Thêm field bật/tắt GA4 */
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_content (content_id),
			KEY idx_type (protection_type)
		) $charset_collate;",
		"{$wpdb->prefix}wcl_access_logs" =>
        "CREATE TABLE {$wpdb->prefix}wcl_access_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            protection_id bigint(20) NOT NULL,
            user_id bigint(20),
            ip_address varchar(45),
            user_agent text,
            status varchar(20),
            attempt_count int DEFAULT 1,
            accessed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_protection (protection_id),
            KEY idx_ip (ip_address),
            KEY idx_accessed (accessed_at)
        ) $charset_collate;",
		"{$wpdb->prefix}wcl_categories" =>
        "CREATE TABLE {$wpdb->prefix}wcl_categories (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            parent_id bigint(20) DEFAULT 0,
			count bigint(20) NOT NULL DEFAULT '0',
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_slug (slug),
            KEY idx_parent (parent_id)
        ) $charset_collate;",
		"{$wpdb->prefix}wcl_statistics" =>
        "CREATE TABLE {$wpdb->prefix}wcl_statistics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            entity_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            referer varchar(255) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'success',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            meta text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY type_entity (type, entity_id),
            KEY created_at (created_at)
        ) $charset_collate;",
		"{$wpdb->prefix}wcl_passwords" =>
		"CREATE TABLE {$wpdb->prefix}wcl_passwords (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			password varchar(255) NOT NULL,
			salt varchar(255) NOT NULL,
			plain_password varchar(255) NOT NULL, 
			status enum('unused','using','used') DEFAULT 'unused',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			used_at datetime DEFAULT NULL,
			used_by bigint(20) DEFAULT NULL,
			download_id bigint(20) DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) $charset_collate;"
    ];

        foreach ($tables as $table_name => $sql) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
                dbDelta($sql);
            }
        }
    }

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
                file_put_contents($dir . '.htaccess', "Order deny,allow\nDeny from all");
                file_put_contents($dir . 'index.php', "<?php\n// Silence is golden.");
            }
        }
    }

    private static function set_default_options() {
        $default_options = array(
            'wcl_version' => WP_CONTENT_LOCKER_VERSION,
            'wcl_cache_duration' => 3600,
            'wcl_enable_encryption' => true,
            'wcl_max_attempts' => 3,
            'wcl_block_duration' => 3600,
            'wcl_cleanup_interval' => 'daily',
            'wcl_log_retention' => 30,
            'wcl_download_path' => 'downloads',
            'wcl_encryption_key' => wp_generate_password(32, true, true)
        );

        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }

    private static function schedule_maintenance_tasks() {
        if (!wp_next_scheduled('wcl_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'wcl_daily_maintenance');
        }

        if (!wp_next_scheduled('wcl_cleanup_temp_files')) {
            wp_schedule_event(time(), 'hourly', 'wcl_cleanup_temp_files');
        }
    }
}