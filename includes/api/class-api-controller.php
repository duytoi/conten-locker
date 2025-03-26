<?php
namespace WP_Content_Locker\Includes\Api;

use WP_Content_Locker\Includes\Services\Protection_Service;
use WP_Content_Locker\Includes\Services\Verification_Service;
use WP_Content_Locker\Includes\Services\Tracking_Service;
use WP_Content_Locker\Includes\Services\Security_Service;
use WP_Content_Locker\Includes\Traits\Traffic_Detection;

class API_Controller {
    use Traffic_Detection;

    protected $namespace = 'wp-content-locker/v2';
    protected $security_service;
    protected $protection_service;
    protected $verification_service;
    protected $tracking_service;
    protected $access_logs_table;
    protected $wpdb;
    protected $ga4_measurement_id = 'G-3RJJLMMW03';

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->access_logs_table = $wpdb->prefix . 'wcl_access_logs';
        // Get API version from constant
		//$this->namespace = 'wp-content-locker/' . WP_CONTENT_LOCKER_API_VERSION;
		$this->namespace = 'wp-content-locker/v2';
		 // Đảm bảo register_routes được gọi
        add_action('rest_api_init', array($this, 'register_routes'));
        try {
            // Initialize services
            $this->security_service = new Security_Service();
            $this->protection_service = new Protection_Service();
            $this->verification_service = new Verification_Service();
            $this->tracking_service = new Tracking_Service();
			
			// Add CORS headers for API requests
        add_action('rest_api_init', function() {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', function($value) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
                return $value;
            });
        });
		
            // Initialize hooks
           // add_action('rest_api_init', array($this, 'register_routes'));
            
            wcl_debug_log('API Controller initialized successfully');
        } catch (\Exception $e) {
            wcl_debug_log('API Controller initialization error: ' . $e->getMessage());
        }
    }

    public function register_routes() {
        try {
            wcl_debug_log('Registering API routes...');

            // Verify Traffic endpoint
            register_rest_route(
            $this->namespace,
            '/verify-traffic',
            array(
                'methods' => \WP_REST_Server::CREATABLE, // POST
                'callback' => array($this, 'verify_traffic'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'gtm_data' => array(
                        'required' => true,
                        'type' => 'object'
                    ),
                    'protection_id' => array(
                        'required' => true,
                        'type' => 'string'
                    )
                )
            )
        );
		 // Verify current API version
        if ($this->namespace !== 'wp-content-locker/' . WP_CONTENT_LOCKER_API_VERSION) {
            wcl_debug_log('Warning: API version mismatch. Expected: ' . WP_CONTENT_LOCKER_API_VERSION . ', Got: ' . $this->namespace);
        }
            // Check Countdown Endpoint
            register_rest_route($this->namespace, '/check-countdown', array(
                'methods' => 'POST',
                'callback' => array($this, 'check_countdown'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'token' => array(
                        'required' => true,
                        'type' => 'string'
                    )
                )
            ));

            // Track Event Endpoint
            register_rest_route($this->namespace, '/track-event', array(
                'methods' => 'POST',
                'callback' => array($this, 'track_event'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'event_name' => array(
                        'required' => true,
                        'type' => 'string'
                    ),
                    'event_data' => array(
                        'required' => true,
                        'type' => 'object'
                    )
                )
            ));

            wcl_debug_log('Routes registered successfully');

        } catch (\Exception $e) {
            wcl_debug_log('Route registration error: ' . $e->getMessage());
        }
    }

    public function validate_gtm_data($param) {
        return is_array($param) && 
               isset($param['client_id']) && 
               isset($param['page_url']);
    }

    public function verify_traffic($request) {
    try {
        $params = $request->get_params();
        $gtm_data = $params['gtm_data'];
        $protection_id = $params['protection_id'];

        wcl_debug_log('Verifying traffic with data: ' . print_r($params, true));

        // Đơn giản hóa verification logic
        $verification_data = array(
            'token' => $this->generate_verification_token(),
            'client_id' => isset($gtm_data['client_id']) ? $gtm_data['client_id'] : '',
            'protection_id' => $protection_id,
            'traffic_source' => 'direct',
            'timestamp' => current_time('mysql'),
            'verification_type' => 'basic'
        );

        // Lưu verification data
        $this->store_verification_data($verification_data);

        return rest_ensure_response(array(
            'success' => true,
            'token' => $verification_data['token'],
            'message' => 'Traffic verified successfully'
        ));

    } catch (\Exception $e) {
        wcl_debug_log('Traffic verification error: ' . $e->getMessage());
        return new \WP_Error(
            'verification_error', 
            'Verification failed: ' . $e->getMessage(), 
            array('status' => 500)
        );
    }
}


    protected function verify_traffic_source($gtm_data) {
        $result = array(
            'is_valid' => false,
            'source' => 'unknown',
            'type' => 'none'
        );

        // Check UTM parameters
        if (isset($gtm_data['page_url'])) {
            $url_components = parse_url($gtm_data['page_url']);
            if (isset($url_components['query'])) {
                parse_str($url_components['query'], $query_params);
                if (isset($query_params['utm_source']) && $query_params['utm_source'] === 'google') {
                    $result['is_valid'] = true;
                    $result['source'] = 'google';
                    $result['type'] = 'utm';
                    return $result;
                }
            }
        }

        // Check referrer
        if (isset($gtm_data['referrer'])) {
            $referrer = $gtm_data['referrer'];
            if (strpos($referrer, 'google.') !== false) {
                $result['is_valid'] = true;
                $result['source'] = 'google';
                $result['type'] = 'referrer';
                return $result;
            }
        }

        // Add additional verification methods here
        
        return $result;
    }

    protected function generate_verification_token() {
        return wp_generate_password(32, false);
    }

    protected function store_verification_data($data) {
    try {
        // Lưu vào transient
        set_transient(
            'wcl_verification_' . $data['token'], 
            $data,
            HOUR_IN_SECONDS
        );

        // Log success
        wcl_debug_log('Verification data stored successfully for token: ' . $data['token']);
        
        return true;
    } catch (\Exception $e) {
        wcl_debug_log('Error storing verification data: ' . $e->getMessage());
        return false;
    }
}


    protected function track_verification_event($data) {
        $event_data = array(
            'protection_id' => $data['protection_id'],
            'traffic_source' => $data['traffic_source'],
            'verification_type' => $data['verification_type']
        );

        $this->track_ga4_event('wcl_traffic_verified', $data['client_id'], $event_data);
    }

    public function track_event($request) {
        try {
            $params = $request->get_params();
            
            return $this->tracking_service->track_ga4_event(
                $params['event_name'],
                $params['event_data']['client_id'],
                $params['event_data']
            );
        } catch (\Exception $e) {
            wcl_debug_log('Event tracking error: ' . $e->getMessage());
            return new \WP_Error('tracking_error', $e->getMessage(), array('status' => 500));
        }
    }

    public function check_countdown($request) {
        try {
            $token = $request->get_param('token');
            $verification_data = get_transient('wcl_verification_' . $token);

            if (!$verification_data) {
                return rest_ensure_response(array(
                    'success' => false,
                    'message' => 'Invalid or expired verification token'
                ));
            }

            return rest_ensure_response(array(
                'success' => true,
                'data' => $verification_data
            ));

        } catch (\Exception $e) {
            wcl_debug_log('Countdown check error: ' . $e->getMessage());
            return new \WP_Error('countdown_error', $e->getMessage(), array('status' => 500));
        }
    }
}
