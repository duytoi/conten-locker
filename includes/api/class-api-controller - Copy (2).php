<?php
// includes/api/class-api-controller.php

namespace WP_Content_Locker\Includes\Api;

use WP_Content_Locker\Includes\Services\Protection_Service;
use WP_Content_Locker\Includes\Services\Verification_Service;
use WP_Content_Locker\Includes\Services\Tracking_Service;
use WP_Content_Locker\Includes\Services\Security_Service;
use WP_Content_Locker\Includes\Traits\Traffic_Detection;

abstract class WCL_API_Controller {
    use Traffic_Detection;

    protected $namespace = 'wp-content-locker/v1';
    protected $rest_base;
    protected $security_service;
    protected $protection_service;
    protected $verification_service;
    protected $tracking_service;

    public function __construct() {
        $this->security_service = new Security_Service();
        $this->protection_service = new Protection_Service();
        $this->verification_service = new Verification_Service();
        $this->tracking_service = new Tracking_Service();

        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        // Base REST API Routes
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_items'),
                    'permission_callback' => array($this, 'get_items_permissions_check'),
                    'args' => $this->get_collection_params(),
                ),
                array(
                    'methods' => \WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'create_item'),
                    'permission_callback' => array($this, 'create_item_permissions_check'),
                    'args' => $this->get_endpoint_args_for_item_schema(\WP_REST_Server::CREATABLE),
                ),
            )
        );

        // Single Item Routes
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            array(
                array(
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_item'),
                    'permission_callback' => array($this, 'get_item_permissions_check'),
                    'args' => array(
                        'id' => array(
                            'validate_callback' => function($param) {
                                return is_numeric($param);
                            }
                        ),
                    ),
                ),
                array(
                    'methods' => \WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'update_item'),
                    'permission_callback' => array($this, 'update_item_permissions_check'),
                    'args' => $this->get_endpoint_args_for_item_schema(\WP_REST_Server::EDITABLE),
                ),
                array(
                    'methods' => \WP_REST_Server::DELETABLE,
                    'callback' => array($this, 'delete_item'),
                    'permission_callback' => array($this, 'delete_item_permissions_check'),
                ),
            )
        );

        // Protection Specific Routes
        register_rest_route($this->namespace, '/verify-traffic', array(
            'methods' => 'POST',
            'callback' => array($this, 'verify_traffic'),
            'permission_callback' => array($this, 'verify_request'),
            'args' => array(
                'protection_id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
                'client_id' => array(
                    'required' => true
                )
            )
        ));

        register_rest_route($this->namespace, '/check-countdown', array(
            'methods' => 'POST',
            'callback' => array($this, 'check_countdown'),
            'permission_callback' => array($this, 'verify_request'),
            'args' => array(
                'protection_id' => array(
                    'required' => true
                ),
                'stage' => array(
                    'required' => true,
                    'enum' => array('first', 'second')
                ),
                'token' => array(
                    'required' => true
                )
            )
        ));

        register_rest_route($this->namespace, '/track-event', array(
            'methods' => 'POST',
            'callback' => array($this, 'track_event'),
            'permission_callback' => array($this, 'verify_request'),
            'args' => array(
                'event_name' => array(
                    'required' => true
                ),
                'client_id' => array(
                    'required' => true
                ),
                'event_params' => array(
                    'type' => 'object',
                    'default' => array()
                )
            )
        ));
    }

    protected function verify_request($request) {
        // 1. Verify API Key
        if (!$this->verify_api_key()) {
            return false;
        }

        // 2. Verify Nonce for authenticated requests
        if (is_user_logged_in()) {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return false;
            }
        }

        // 3. Additional Security Checks
        $ip = $this->security_service->get_client_ip();
        if ($this->security_service->is_ip_blocked($ip)) {
            return false;
        }

        return true;
    }

    protected function verify_api_key() {
        $api_key = get_option('wcl_api_key');
        if (empty($api_key)) {
            return false;
        }

        $request_api_key = isset($_SERVER['HTTP_X_WCL_API_KEY']) ? 
            $_SERVER['HTTP_X_WCL_API_KEY'] : '';

        return hash_equals($api_key, $request_api_key);
    }
	//Check Google traffic
	register_rest_route($this->namespace, '/verify-gtm-traffic', array(
		'methods' => 'POST',
		'callback' => array($this, 'verify_gtm_traffic'),
		'permission_callback' => '__return_true',
		'args' => array(
			'gtm_data' => array(
				'required' => true,
					'type' => 'object'
				)
			)
	));

    public function verify_traffic($request) {
        try {
            $params = $request->get_params();
            $protection_id = $params['protection_id'];
            $client_id = $params['client_id'];

            // Check Google traffic
            if (!$this->is_google_traffic()) {
                return $this->prepare_error('invalid_traffic', 'Invalid traffic source', 403);
            }

            // Get protection settings
            $protection = $this->protection_service->get_protection_settings($protection_id);
            if (!$protection) {
                return $this->prepare_error('not_found', 'Protection not found', 404);
            }

            // Generate verification token
            $token = $this->verification_service->generate_token($protection_id);

            return $this->prepare_response(array(
                'success' => true,
                'token' => $token,
                'countdown_config' => array(
                    'mode' => $protection['countdown_mode'],
                    'first_duration' => $protection['countdown_first'],
                    'second_duration' => $protection['countdown_second']
                )
            ));

        } catch (\Exception $e) {
            return $this->prepare_error('server_error', $e->getMessage(), 500);
        }
    }

    public function check_countdown($request) {
        try {
            $params = $request->get_params();
            $protection_id = $params['protection_id'];
            $stage = $params['stage'];
            $token = $params['token'];

            // Verify token
            if (!$this->verification_service->verify_token($protection_id, $token)) {
                return $this->prepare_error('invalid_token', 'Invalid verification token', 401);
            }

            $status = $this->protection_service->get_countdown_status($protection_id, $stage);

            return $this->prepare_response(array(
                'success' => true,
                'completed' => $status['completed'],
                'remaining_time' => $status['remaining_time'],
                'next_stage' => $status['next_stage'] ?? null
            ));

        } catch (\Exception $e) {
            return $this->prepare_error('server_error', $e->getMessage(), 500);
        }
    }

    public function track_event($request) {
        try {
            $params = $request->get_params();
            
            $tracked = $this->tracking_service->track_ga4_event(
                $params['event_name'],
                $params['client_id'],
                $params['event_params']
            );

            return $this->prepare_response(array(
                'success' => $tracked
            ));

        } catch (\Exception $e) {
            return $this->prepare_error('tracking_error', $e->getMessage(), 500);
        }
    }

    protected function prepare_response($data, $status = 200) {
        return new \WP_REST_Response($data, $status);
    }

    protected function prepare_error($code, $message, $status = 400) {
        return new \WP_Error(
            'wcl_' . $code,
            $message,
            array('status' => $status)
        );
    }

    protected function prepare_response_for_collection($response) {
        if (!($response instanceof \WP_REST_Response)) {
            return $response;
        }

        $data = (array) $response->get_data();
        $server = rest_get_server();
        $links = $server->get_compact_response_links($response);

        if (!empty($links)) {
            $data['_links'] = $links;
        }

        return $data;
    }

    // Abstract methods that must be implemented by child classes
    abstract protected function get_items($request);
    abstract protected function get_item($request);
    abstract protected function create_item($request);
    abstract protected function update_item($request);
    abstract protected function delete_item($request);
    
    abstract protected function get_items_permissions_check($request);
    abstract protected function get_item_permissions_check($request);
    abstract protected function create_item_permissions_check($request);
    abstract protected function update_item_permissions_check($request);
    abstract protected function delete_item_permissions_check($request);
}