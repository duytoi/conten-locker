<?php
namespace WP_Content_Locker\Includes\Api;

use WP_Content_Locker\Includes\Services\Protection_Service;
use WP_Content_Locker\Includes\Services\Verification_Service;
use WP_Content_Locker\Includes\Services\Tracking_Service;
use WP_Content_Locker\Includes\Services\Security_Service;
use WP_Content_Locker\Includes\Traits\Traffic_Detection;

class API_Controller {
    use Traffic_Detection;

    protected $namespace = 'wp-content-locker/v1';
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
        
        try {
            $this->init_services();
            $this->init_hooks();
            wcl_debug_log('API Controller initialized successfully');
        } catch (\Exception $e) {
            wcl_debug_log('API Controller initialization error: ' . $e->getMessage());
        }
    }

    private function init_services() {
        $this->security_service = new Security_Service();
        $this->protection_service = new Protection_Service();
        $this->verification_service = new Verification_Service();
        $this->tracking_service = new Tracking_Service();
    }

    private function init_hooks() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    public function register_routes() {
        try {
            wcl_debug_log('Registering API routes...');

            // Verify Traffic Endpoint
            register_rest_route($this->namespace, '/verify-traffic', array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array($this, 'verify_traffic'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'gtm_data' => array(
                        'required' => true,
                        'type' => 'object',
                    ),
                    'protection_id' => array(
                        'required' => true,
                        'type' => 'string',
                    )
                )
            ));

            // Check Countdown Endpoint
            register_rest_route($this->namespace, '/check-countdown', array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array($this, 'check_countdown'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'token' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                    'protection_id' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                    'stage' => array(
                        'required' => true,
                        'type' => 'string',
                        'enum' => array('first', 'second')
                    )
                )
            ));

            // Track Event Endpoint
            register_rest_route($this->namespace, '/track-event', array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array($this, 'track_event'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'event_name' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                    'client_id' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                    'event_params' => array(
                        'type' => 'object',
                        'default' => array()
                    )
                )
            ));

            // Test Endpoint
            register_rest_route($this->namespace, '/test', array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => function() {
                    return rest_ensure_response(array(
                        'status' => 'ok',
                        'message' => 'API is working'
                    ));
                },
                'permission_callback' => '__return_true'
            ));

            wcl_debug_log('Routes registered successfully');

        } catch (\Exception $e) {
            wcl_debug_log('Route registration error: ' . $e->getMessage());
        }
    }

    public function verify_traffic($request) {
        try {
            $params = $request->get_params();
            $gtm_data = $params['gtm_data'];
            $protection_id = $params['protection_id'];

            wcl_debug_log('Verifying traffic for protection ID: ' . $protection_id);
            wcl_debug_log('GTM Data: ' . print_r($gtm_data, true));

            // Check Google traffic
            $is_google = $this->verify_google_source($gtm_data);

            if ($is_google) {
                // Generate token
                $token = wp_generate_password(32, false);
                
                // Store verification data
                $verification_data = array(
                    'token' => $token,
                    'client_id' => $gtm_data['client_id'],
                    'protection_id' => $protection_id,
                    'timestamp' => current_time('mysql')
                );
                
                set_transient('wcl_verification_' . $token, $verification_data, HOUR_IN_SECONDS);

                // Track event
                $this->track_ga4_event('wcl_traffic_verified', $gtm_data['client_id'], array(
                    'protection_id' => $protection_id,
                    'is_google' => true
                ));

                return rest_ensure_response(array(
                    'success' => true,
                    'is_google' => true,
                    'token' => $token,
                    'countdown_required' => true,
                    'countdown_settings' => $this->get_countdown_settings($protection_id)
                ));
            }

            return rest_ensure_response(array(
                'success' => false,
                'is_google' => false,
                'message' => 'Invalid traffic source'
            ));

        } catch (\Exception $e) {
            wcl_debug_log('Traffic verification error: ' . $e->getMessage());
            return new \WP_Error('verification_error', $e->getMessage(), array('status' => 500));
        }
    }

    public function check_countdown($request) {
        try {
            $params = $request->get_params();
            $token = $params['token'];
            $protection_id = $params['protection_id'];
            $stage = $params['stage'];

            // Verify token
            $verification_data = get_transient('wcl_verification_' . $token);
            if (!$verification_data || $verification_data['protection_id'] !== $protection_id) {
                return new \WP_Error('invalid_token', 'Invalid verification token');
            }

            // Get protection settings
            $settings = $this->get_countdown_settings($protection_id);
            
            // Track countdown stage
            $this->track_ga4_event('wcl_countdown_' . $stage, $verification_data['client_id'], array(
                'protection_id' => $protection_id,
                'stage' => $stage
            ));

            return rest_ensure_response(array(
                'success' => true,
                'countdown_complete' => true,
                'password' => $this->generate_access_password($protection_id, $token)
            ));

        } catch (\Exception $e) {
            wcl_debug_log('Countdown check error: ' . $e->getMessage());
            return new \WP_Error('countdown_error', $e->getMessage(), array('status' => 500));
        }
    }

    public function track_event($request) {
        try {
            $params = $request->get_params();
            
            $tracked = $this->track_ga4_event(
                $params['event_name'],
                $params['client_id'],
                $params['event_params'] ?? array()
            );

            return rest_ensure_response(array(
                'success' => $tracked
            ));

        } catch (\Exception $e) {
            wcl_debug_log('Event tracking error: ' . $e->getMessage());
            return new \WP_Error('tracking_error', $e->getMessage(), array('status' => 500));
        }
    }

    private function verify_google_source($gtm_data) {
        $traffic_source = $gtm_data['traffic_source'] ?? '';
        $referrer = $gtm_data['referrer'] ?? '';

        if ($traffic_source === 'google' || strpos($referrer, 'google.') !== false) {
            return true;
        }

        if (isset($gtm_data['page_url'])) {
            parse_str(parse_url($gtm_data['page_url'], PHP_URL_QUERY), $query_params);
            if (isset($query_params['utm_source']) && $query_params['utm_source'] === 'google') {
                return true;
            }
        }

        return false;
    }

    private function get_countdown_settings($protection_id) {
        return array(
            'first_stage' => array(
                'duration' => 60,
                'message' => 'Please wait for the first countdown'
            ),
            'second_stage' => array(
                'duration' => 45,
                'message' => 'Please complete the second countdown'
            )
        );
    }

    private function generate_access_password($protection_id, $token) {
        return wp_generate_password(12, false);
    }

    private function track_ga4_event($event_name, $client_id, $params = array()) {
        if ($this->tracking_service) {
            return $this->tracking_service->track_ga4_event($event_name, $client_id, $params);
        }
        return false;
    }
}