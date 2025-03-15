<?php
namespace WP_Content_Locker\Includes\Services;

class Protection_Service {
    private $wpdb;
    private $table_name;
    private $default_settings;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'wcl_protections';
        $this->default_settings = [
            'countdown_mode' => 'single',
            'countdown_first' => 60,
            'countdown_second' => 60,
            'first_message' => __('Please wait for the countdown to complete', 'wcl'),
            'second_message' => __('Please complete the second countdown', 'wcl'),
            'redirect_message' => __('Click any link to continue', 'wcl'),
            'requires_ga' => 0,
            'ga4_enabled' => 0,
            'ga4_measurement_id' => '',
            'gtm_container_id' => ''
        ];
    }

    /**
     * Get protection settings
     */
    public function get_settings() {
        return get_option('wcl_protection_settings', $this->default_settings);
    }

    /**
     * Update protection settings
     */
    public function update_settings($settings) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        $sanitized = $this->sanitize_settings($settings);
        return update_option('wcl_protection_settings', $sanitized);
    }

    /**
     * Get specific protection
     */
    public function get_protection($id) {
        $data = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
        return $data ? new WCL_Protection($data) : null;
    }

    /**
     * Save protection
     */
    public function save_protection(WCL_Protection $protection) {
        $data = $protection->to_array();
        $format = [
            '%d', // content_id
            '%s', // protection_type
            '%s', // countdown_mode
            '%d', // countdown_first
            '%d', // countdown_second
            '%s', // first_message
            '%s', // second_message
            '%s', // redirect_message
            '%d', // requires_ga
            '%d', // ga4_enabled
            '%s', // ga4_measurement_id
            '%s', // gtm_container_id
            '%s'  // status
        ];

        if ($protection->get_id()) {
            return $this->wpdb->update(
                $this->table_name,
                $data,
                ['id' => $protection->get_id()],
                $format,
                ['%d']
            );
        } else {
            $this->wpdb->insert($this->table_name, $data, $format);
            $protection->set_id($this->wpdb->insert_id);
            return $protection;
        }
    }
	
	public function get_content_url($protection_id) {
    $protection = $this->get_protection($protection_id);
    
    if (!$protection) {
        return home_url();
    }

    if (!empty($protection->redirect_url)) {
        return esc_url($protection->redirect_url);
    }

    return get_permalink($protection->post_id);
}
    /**
     * Check if download is unlocked
     */
    public function is_download_unlocked($download_id) {
        $cookie_name = 'wcl_download_' . $download_id;
        return isset($_COOKIE[$cookie_name]);
    }

    /**
     * Verify download password
     */
    public function verify_download_password($download_id, $password) {
        $download = $this->get_download_with_password($download_id);
        
        if (!$download) {
            return [
                'success' => false,
                'message' => __('Download not found', 'wcl')
            ];
        }

        if ($download->password !== $password) {
            return [
                'success' => false,
                'message' => __('Invalid password', 'wcl')
            ];
        }

        // Set cookie for 24 hours
        $this->set_download_cookie($download_id, $password);

        return [
            'success' => true,
            'download' => $download
        ];
    }

    /**
     * Get download with password
     */
    private function get_download_with_password($download_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}wcl_downloads WHERE id = %d",
                $download_id
            )
        );
    }

    /**
     * Set download cookie
     */
    private function set_download_cookie($download_id, $password) {
        setcookie(
            'wcl_download_' . $download_id,
            wp_hash($password),
            [
                'expires' => time() + DAY_IN_SECONDS,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }

    /**
     * Clear download cookie
     */
    public function clear_download_cookie($download_id) {
        if (isset($_COOKIE['wcl_download_' . $download_id])) {
            setcookie(
                'wcl_download_' . $download_id,
                '',
                [
                    'expires' => time() - 3600,
                    'path' => COOKIEPATH,
                    'domain' => COOKIE_DOMAIN,
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]
            );
        }
    }

    /**
     * Get tracking settings
     */
    public function get_tracking_settings() {
        $settings = $this->get_settings();
        return [
            'ga4_enabled' => (bool) $settings['ga4_enabled'],
            'ga4_measurement_id' => $settings['ga4_measurement_id'],
            'gtm_container_id' => $settings['gtm_container_id']
        ];
    }

    /**
     * Check if tracking is enabled
     */
    public function is_tracking_enabled() {
        $settings = $this->get_settings();
        return !empty($settings['ga4_enabled']) && 
               (!empty($settings['ga4_measurement_id']) || !empty($settings['gtm_container_id']));
    }

    /**
     * Sanitize settings
     */
    private function sanitize_settings($settings) {
        return [
            'countdown_mode' => in_array($settings['countdown_mode'], ['single', 'double']) 
                ? $settings['countdown_mode'] 
                : 'single',
            'countdown_first' => min(max(intval($settings['countdown_first']), 10), 3600),
            'countdown_second' => min(max(intval($settings['countdown_second']), 10), 3600),
            'first_message' => wp_kses_post($settings['first_message']),
            'second_message' => wp_kses_post($settings['second_message']),
            'redirect_message' => wp_kses_post($settings['redirect_message']),
            'requires_ga' => isset($settings['requires_ga']) ? 1 : 0,
            'ga4_enabled' => isset($settings['ga4_enabled']) ? 1 : 0,
            'ga4_measurement_id' => sanitize_text_field($settings['ga4_measurement_id']),
            'gtm_container_id' => sanitize_text_field($settings['gtm_container_id'])
        ];
    }

    /**
     * Get download protection
     */
    public function get_download_protection($download_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE content_id = %d 
                AND protection_type = 'download'
                AND status = 'active'
                LIMIT 1",
                $download_id
            )
        );
    }

    /**
     * Check if download is protected
     */
    public function is_download_protected($download_id) {
        $protection = $this->get_download_protection($download_id);
        return !empty($protection);
    }
}