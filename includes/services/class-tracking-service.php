<?php
namespace WP_Content_Locker\Includes\Services;

class Tracking_Service {
    public function get_tracking_settings() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcl_protections';
        return $wpdb->get_row("SELECT * FROM {$table_name} WHERE id = 1", ARRAY_A);
    }

    public function is_tracking_enabled() {
        $settings = $this->get_tracking_settings();
        return ($settings && ($settings['ga4_enabled'] === '1' || $settings['ga4_enabled'] === 1));
    }
}