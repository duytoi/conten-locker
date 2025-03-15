<?php
namespace WP_Content_Locker\Includes\Traits;

/**
 * Traffic Detection Trait
 */
trait Traffic_Detection {
	protected $access_logs_table;  // Khai báo property trong trait

    protected function init_traffic_detection() {
        global $wpdb;
        $this->access_logs_table = $wpdb->prefix . 'wcl_access_logs';
    }
    /**
     * List of Google domains
     * @var array
     */
    protected $google_domains = [
        'google.com', 'google.ad', 'google.ae', 'google.com.af', 'google.com.ag',
        'google.com.ai', 'google.al', 'google.am', 'google.co.ao', 'google.com.ar',
        'google.as', 'google.at', 'google.com.au', 'google.az', 'google.ba',
        'google.com.bd', 'google.be', 'google.bf', 'google.bg', 'google.com.bh',
        'google.bi', 'google.bj', 'google.com.bn', 'google.com.bo', 'google.com.br',
        // Add more Google domains as needed
    ];

    /**
     * Check if traffic is from Google
     *
     * @return boolean
     */
    protected function is_google_traffic() {
        if (!isset($_SERVER['HTTP_REFERER'])) {
            return false;
        }

        $referrer = strtolower($_SERVER['HTTP_REFERER']);
        
        foreach ($this->google_domains as $domain) {
            if (strpos($referrer, $domain) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set traffic source cookie
     *
     * @return void
     */
    protected function set_traffic_cookie() {
        if (!isset($_COOKIE['wcl_traffic_source'])) {
            $source = $this->is_google_traffic() ? 'google' : 'direct';
            $expire = time() + DAY_IN_SECONDS;
            
            setcookie('wcl_traffic_source', $source, [
                'expires' => $expire,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }

    /**
     * Get traffic source
     *
     * @return string
     */
    protected function get_traffic_source() {
        if (isset($_COOKIE['wcl_traffic_source'])) {
            return sanitize_text_field($_COOKIE['wcl_traffic_source']);
        }

        $this->set_traffic_cookie();
        return $this->is_google_traffic() ? 'google' : 'direct';
    }

    /**
     * Check if content should be shown
     *
     * @return boolean
     */
    protected function should_show_content() {
        $traffic_source = $this->get_traffic_source();
        return $traffic_source === 'google';
    }
}