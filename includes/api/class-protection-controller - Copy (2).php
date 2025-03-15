<?php
namespace WP_Content_Locker\Includes\API;

use WP_Content_Locker\Includes\Services\Protection_Service;

class Protection_Controller {
    private $protection_service;
    private $namespace = 'wp-content-locker/v1';
    private $rest_base = 'protection';

    public function __construct() {
        $this->protection_service = new Protection_Service();
        $this->register_routes();
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_protection_settings'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                ),
                array(
                    'methods' => \WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'update_protection_settings'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                    'args' => $this->get_endpoint_args_for_item_schema(true),
                ),
            )
        );

        // Route for handling password verification
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/verify',
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array($this, 'verify_password'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'protection_id' => array(
                        'required' => true,
                        'type' => 'integer',
                    ),
                    'password' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                ),
            )
        );

        // Route for getting countdown status
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/countdown/(?P<id>\d+)',
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array($this, 'get_countdown_status'),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Check admin permissions
     */
    public function check_admin_permissions() {
        return current_user_can('manage_options');
    }

    /**
     * Get protection settings
     */
    public function get_protection_settings(\WP_REST_Request $request) {
        try {
            $settings = $this->protection_service->get_settings();
            return new \WP_REST_Response($settings, 200);
        } catch (\Exception $e) {
            return new \WP_Error(
                'wcl_protection_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Update protection settings
     */
    public function update_protection_settings(\WP_REST_Request $request) {
        try {
            $settings = $request->get_params();
            $updated = $this->protection_service->update_settings($settings);

            if (!$updated) {
                throw new \Exception('Failed to update settings');
            }

            return new \WP_REST_Response(
                array(
                    'message' => 'Settings updated successfully',
                    'settings' => $settings
                ),
                200
            );
        } catch (\Exception $e) {
            return new \WP_Error(
                'wcl_protection_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Verify password
     */
    public function verify_password(\WP_REST_Request $request) {
        try {
            $protection_id = $request->get_param('protection_id');
            $password = $request->get_param('password');

            // Get user data for logging
            $user_data = array(
                'ip' => $this->get_client_ip(),
                'user_agent' => $request->get_header('User-Agent'),
                'user_id' => get_current_user_id()
            );

            $result = $this->protection_service->verify_access(
                $protection_id,
                $password,
                $user_data
            );

            if (!$result) {
                return new \WP_Error(
                    'invalid_password',
                    'Invalid password',
                    array('status' => 403)
                );
            }

            return new \WP_REST_Response(
                array(
                    'message' => 'Password verified successfully',
                    'success' => true
                ),
                200
            );
        } catch (\Exception $e) {
            return new \WP_Error(
                'wcl_protection_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get countdown status
     */
    public function get_countdown_status(\WP_REST_Request $request) {
        try {
            $protection_id = $request->get_param('id');
            $protection = $this->protection_service->get_protection_rule($protection_id);

            if (!$protection) {
                return new \WP_Error(
                    'not_found',
                    'Protection rule not found',
                    array('status' => 404)
                );
            }

            return new \WP_REST_Response(
                array(
                    'countdown_time' => $protection->countdown_time,
                    'protection_type' => $protection->protection_type,
                    'status' => $protection->status
                ),
                200
            );
        } catch (\Exception $e) {
            return new \WP_Error(
                'wcl_protection_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }

    /**
     * Get endpoint arguments for schema
     */
    private function get_endpoint_args_for_item_schema($method = \WP_REST_Server::CREATABLE) {
        $schema = array(
            'protection_mode' => array(
                'type' => 'string',
                'enum' => array('single', 'double'),
                'required' => true,
            ),
            'countdown_time_1' => array(
                'type' => 'integer',
                'minimum' => 5,
                'maximum' => 3600,
                'required' => true,
            ),
            'countdown_time_2' => array(
                'type' => 'integer',
                'minimum' => 5,
                'maximum' => 3600,
                'required' => true,
            ),
            'enable_encryption' => array(
                'type' => 'boolean',
            ),
            'enable_ga4' => array(
                'type' => 'boolean',
            ),
            'ga4_measurement_id' => array(
                'type' => 'string',
            ),
            'enable_gtm' => array(
                'type' => 'boolean',
            ),
            'gtm_container_id' => array(
                'type' => 'string',
            ),
            'custom_messages' => array(
                'type' => 'object',
                'properties' => array(
                    'countdown' => array('type' => 'string'),
                    'password_prompt' => array('type' => 'string'),
                    'success' => array('type' => 'string'),
                    'error' => array('type' => 'string'),
                ),
            ),
        );

        return $schema;
    }
}