<?php
namespace WP_Content_Locker\Includes\Shortcodes;

use WP_Content_Locker\Includes\Services\Protection_Service;
use WP_Content_Locker\Includes\Services\Security_Service;

class Countdown_Shortcode extends Base_Shortcode {
    
    private $protection_service;
    private $security_service;
    
    public function __construct() {
        $this->protection_service = new Protection_Service();
        $this->security_service = new Security_Service();
        
        add_shortcode('wcl_countdown', array($this, 'render_countdown'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue necessary assets
     */
    public function enqueue_assets() {
        // Sửa từ WCL_PLUGIN_URL thành WP_CONTENT_LOCKER_PUBLIC_URL
        wp_enqueue_style(
            'wcl-countdown-style',
            WP_CONTENT_LOCKER_PUBLIC_URL . 'css/countdown.css',
            array(),
            WP_CONTENT_LOCKER_VERSION  // Sửa WCL_VERSION thành WP_CONTENT_LOCKER_VERSION
        );

        wp_enqueue_script(
            'wcl-countdown',
            WP_CONTENT_LOCKER_PUBLIC_URL . 'js/countdown.js',
            array('jquery'),
            WP_CONTENT_LOCKER_VERSION,  // Sửa WCL_VERSION thành WP_CONTENT_LOCKER_VERSION
            true
        );

        // Localize script
        wp_localize_script('wcl-countdown', 'wclCountdown', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcl-countdown')
        ));
    }

    /**
     * Render countdown shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_countdown($atts) {
		  error_log('Countdown shortcode called with: ' . print_r($atts, true));
        // Extract shortcode attributes
        $atts = shortcode_atts(array(
            'id' => 0,
            'mode' => 'single', // single or double
            'first_time' => 60,
            'second_time' => 60,
            'first_message' => __('Please wait for the countdown to complete', 'wcl'),
            'second_message' => __('Please complete the second countdown', 'wcl'),
            'redirect_message' => __('Click any link to continue', 'wcl'),
            'success_message' => __('Here is your password:', 'wcl')
        ), $atts);

           // Get protection settings
		$protection = $this->protection_service->get_protection_settings($atts['id']);
			if (!$protection || $protection->status !== 'active') {
				return '';
		}
		// Debug information
    $debug_info = [
        'protection_id' => $atts['id'],
        'google_traffic_only' => !empty($protection->google_traffic_only),
        'traffic_verification' => null
    ];
		// Check Google traffic requirement
    if (!empty($protection->google_traffic_only)) {
        $debug_info['traffic_verification'] = $this->security_service->verify_google_traffic();
        
        if (!$debug_info['traffic_verification']) {
            // Add debug console output
            add_action('wp_footer', function() use ($debug_info) {
                echo "<script>
                    console.log('WP Content Locker - Protection Debug:');
                    console.log(" . json_encode($debug_info) . ");
                    console.warn('Access Denied: Traffic source is not from Google');
                </script>";
            });
            return '';
        }
    }

    // Add success debug output
    add_action('wp_footer', function() use ($debug_info) {
        echo "<script>
            console.log('WP Content Locker - Protection Debug:');
            console.log(" . json_encode($debug_info) . ");
            console.info('Access Granted: Protection requirements met');
        </script>";
    });

        // Merge protection settings with shortcode attributes
        $settings = array_merge($atts, array(
            'protection_id' => $protection->id,
            'ga4_enabled' => $protection->ga4_enabled,
            'ga4_measurement_id' => $protection->ga4_measurement_id
        ));

        // Get template path
         $template_path = WP_CONTENT_LOCKER_PATH . 'public/templates/countdown.php';
        
        // Start output buffering
        ob_start();
        
        // Include template if exists
        if (file_exists($template_path)) {
            include $template_path;
        }
        
        // Return buffered content
        return ob_get_clean();
    }

    /**
     * AJAX handler for countdown completion
     */
    public function handle_countdown_completion() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcl-countdown')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
        }

        // Get protection ID
        $protection_id = intval($_POST['protection_id']);
        
        // Get password
        $password = $this->protection_service->get_password($protection_id);
        
        if ($password) {
            // Track password assignment if GA4 is enabled
            if ($this->protection_service->is_ga4_enabled($protection_id)) {
                $this->track_password_assignment($protection_id);
            }
            
            wp_send_json_success(array(
                'password' => $password
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('No password available', 'wcl')
            ));
        }
    }

    /**
     * Track password assignment in GA4
     */
    private function track_password_assignment($protection_id) {
        $protection = $this->protection_service->get_protection_settings($protection_id);
        
        if ($protection && $protection->ga4_enabled) {
            // Add GA4 tracking code here
            $tracking_data = array(
                'event_name' => 'password_assigned',
                'protection_id' => $protection_id
            );
            
            // You would implement actual GA4 tracking here
        }
    }
}

// Initialize shortcode
new Countdown_Shortcode();