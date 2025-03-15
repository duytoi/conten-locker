<?php
namespace WP_Content_Locker\Includes\Services;

class Protection_Service {
    private $wpdb;
    private $table_name;
    private $default_settings;
	private $access_logs_table; // Thêm khai báo này
	
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'wcl_protections';
        $this->access_logs_table = $wpdb->prefix . 'wcl_access_logs';
        
        // Initialize settings using separate method
        $this->init_default_settings();
    }

    private function init_default_settings() {
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
            'gtm_container_id' => '',
            'countdown_enabled' => 1,
            'countdown_duration' => 60
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
     * Check if countdown is active
     */
    public function is_countdown_active($protection_id) {
        $protection = $this->get_protection($protection_id);
        return $protection && 
               $protection->protection_type === 'countdown' && 
               $protection->countdown_enabled;
    }

    /**
     * Start countdown session
     */
    public function start_countdown_session($protection_id) {
        $protection = $this->get_protection($protection_id);
        
        if (!$protection) {
            return false;
        }

        $session_data = [
            'start_time' => time(),
            'mode' => $protection->countdown_mode,
            'first_duration' => $protection->countdown_first,
            'second_duration' => $protection->countdown_second,
            'current_stage' => 'first'
        ];

        set_transient('wcl_countdown_' . $protection_id, $session_data, DAY_IN_SECONDS);
        
        return $session_data;
    }

    /**
     * Get countdown session status
     */
    public function get_countdown_session($protection_id) {
        return get_transient('wcl_countdown_' . $protection_id);
    }

    /**
     * Update countdown progress
     */
    public function update_countdown_progress($protection_id, $stage = 'first', $completed = false) {
        if ($completed) {
            $this->log_countdown_completion($protection_id);
            delete_transient('wcl_countdown_' . $protection_id);
            return true;
        }

        $session = $this->get_countdown_session($protection_id);
        if ($session) {
            $session['current_stage'] = $stage;
            set_transient('wcl_countdown_' . $protection_id, $session, DAY_IN_SECONDS);
        }

        return false;
    }

    /**
     * Log countdown completion
     */
    private function log_countdown_completion($protection_id) {
        return $this->wpdb->insert(
            $this->access_logs_table,
            [
                'protection_id' => $protection_id,
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'status' => 'completed',
                'attempt_count' => 1,
                'accessed_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * Check if countdown is completed
     */
    public function is_countdown_completed($protection_id) {
        $user_ip = $this->get_client_ip();
        
        $completed = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->access_logs_table} 
                WHERE protection_id = %d 
                AND ip_address = %s 
                AND status = 'completed'
                AND accessed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                LIMIT 1",
                $protection_id,
                $user_ip
            )
        );

        return !empty($completed);
    }

    /**
     * Get countdown settings for protection
     */
    public function get_countdown_settings($protection_id) {
        $protection = $this->get_protection($protection_id);
        
        if (!$protection) {
            return null;
        }

        return [
            'mode' => $protection->countdown_mode,
            'first_duration' => (int) $protection->countdown_first,
            'second_duration' => (int) $protection->countdown_second,
            'first_message' => $protection->first_message,
            'second_message' => $protection->second_message,
            'redirect_message' => $protection->redirect_message,
            'enabled' => (bool) $protection->countdown_enabled
        ];
    }

    /**
     * Validate countdown completion
     */
    public function validate_countdown_completion($protection_id, $stage = 'first') {
        $session = $this->get_countdown_session($protection_id);
        
        if (!$session) {
            return false;
        }

        $elapsed_time = time() - $session['start_time'];
        $required_duration = ($stage === 'first') ? 
            $session['first_duration'] : 
            $session['second_duration'];

        return $elapsed_time >= $required_duration;
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
	/**
     * Verify countdown token
     */
    public function verify_countdown_token($protection_id, $token) {
        $stored_token = get_transient('wcl_token_' . $protection_id);
        return $stored_token && hash_equals($stored_token, $token);
    }

    /**
     * Generate countdown token
     */
    public function generate_countdown_token($protection_id) {
        $token = wp_generate_password(32, false);
        set_transient('wcl_token_' . $protection_id, $token, HOUR_IN_SECONDS);
        return $token;
    }

    /**
     * Get countdown status
     */
    public function get_countdown_status($protection_id, $stage = 'first') {
        $session = $this->get_countdown_session($protection_id);
        
        if (!$session) {
            return [
                'completed' => false,
                'remaining_time' => 0,
                'next_stage' => null
            ];
        }

        $current_time = time();
        $elapsed_time = $current_time - $session['start_time'];
        $duration = ($stage === 'first') ? $session['first_duration'] : $session['second_duration'];
        $remaining_time = max(0, $duration - $elapsed_time);
        
        $completed = $remaining_time === 0;
        $next_stage = null;

        if ($completed && $stage === 'first' && $session['mode'] === 'double') {
            $next_stage = 'second';
        }

        return [
            'completed' => $completed,
            'remaining_time' => $remaining_time,
            'next_stage' => $next_stage
        ];
    }

    /**
     * Track countdown event
     */
    public function track_countdown_event($protection_id, $event_type, $data = []) {
        if (!$this->is_tracking_enabled()) {
            return false;
        }

        $tracking_data = array_merge($data, [
            'protection_id' => $protection_id,
            'event_type' => $event_type,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'timestamp' => current_time('mysql')
        ]);

        return $this->wpdb->insert(
            $this->wpdb->prefix . 'wcl_tracking_events',
            $tracking_data,
            ['%d', '%s', '%d', '%s', '%s', '%s']
        );
    }
	
	/**
 * Get protection settings by ID
 * 
 * @param int $id Protection ID
 * @return object|false Protection settings object or false if not found
 */
public function get_protection_settings($id) {
    if (empty($id)) {
        return false;
    }

    $protection = $this->wpdb->get_row(
        $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        )
    );

    if (!$protection) {
        return false;
    }

    // Convert to object with required properties
    return (object) [
        'id' => $protection->id,
        'status' => $protection->status ?? 'inactive',
        'google_traffic_only' => $protection->requires_ga ?? false,
        'ga4_enabled' => $protection->ga4_enabled ?? false,
        'ga4_measurement_id' => $protection->ga4_measurement_id ?? '',
        'countdown_enabled' => $protection->countdown_enabled ?? true,
        'countdown_mode' => $protection->countdown_mode ?? 'single',
        'countdown_first' => $protection->countdown_first ?? 60,
        'countdown_second' => $protection->countdown_second ?? 60,
        'first_message' => $protection->first_message ?? '',
        'second_message' => $protection->second_message ?? '',
        'redirect_message' => $protection->redirect_message ?? ''
    ];
}

    /**
     * Reset countdown session
     */
    public function reset_countdown_session($protection_id) {
        delete_transient('wcl_countdown_' . $protection_id);
        delete_transient('wcl_token_' . $protection_id);
        
        return $this->track_countdown_event($protection_id, 'session_reset');
    }

    /**
     * Get protection analytics
     */
    public function get_protection_analytics($protection_id, $date_range = '7days') {
        $end_date = current_time('mysql');
        
        switch($date_range) {
            case '24hours':
                $start_date = date('Y-m-d H:i:s', strtotime('-24 hours'));
                break;
            case '7days':
                $start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case '30days':
                $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            default:
                $start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
        }

        return [
            'total_views' => $this->get_total_views($protection_id, $start_date, $end_date),
            'completion_rate' => $this->get_completion_rate($protection_id, $start_date, $end_date),
            'average_completion_time' => $this->get_average_completion_time($protection_id, $start_date, $end_date),
            'events' => $this->get_protection_events($protection_id, $start_date, $end_date)
        ];
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        
        return $_SERVER['REMOTE_ADDR'];
    }

    // Analytics helper methods
    private function get_total_views($protection_id, $start_date, $end_date) {
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_address) 
            FROM {$this->access_logs_table} 
            WHERE protection_id = %d 
            AND accessed_at BETWEEN %s AND %s",
            $protection_id,
            $start_date,
            $end_date
        ));
    }

    private function get_completion_rate($protection_id, $start_date, $end_date) {
        $total = $this->get_total_views($protection_id, $start_date, $end_date);
        if (!$total) return 0;

        $completed = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_address) 
            FROM {$this->access_logs_table} 
            WHERE protection_id = %d 
            AND status = 'completed'
            AND accessed_at BETWEEN %s AND %s",
            $protection_id,
            $start_date,
            $end_date
        ));

        return ($completed / $total) * 100;
    }

    private function get_average_completion_time($protection_id, $start_date, $end_date) {
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at))
            FROM {$this->access_logs_table}
            WHERE protection_id = %d
            AND status = 'completed'
            AND accessed_at BETWEEN %s AND %s",
            $protection_id,
            $start_date,
            $end_date
        ));
    }

    private function get_protection_events($protection_id, $start_date, $end_date) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT event_type, COUNT(*) as count
            FROM {$this->wpdb->prefix}wcl_tracking_events
            WHERE protection_id = %d
            AND timestamp BETWEEN %s AND %s
            GROUP BY event_type",
            $protection_id,
            $start_date,
            $end_date
        ));
    }
}