<?php
abstract class WCL_Ajax_Handler {
    protected $security_service;
    protected $nonce_action;
    protected $nonce_name;
    protected $response_handler; // Thêm response handler
    protected $logger; // Thêm logger service

    public function __construct() {
        $this->security_service = new Security_Service();
        $this->response_handler = new WCL_Ajax_Response(); // Thêm
        $this->logger = new WCL_Logger(); // Thêm
        $this->init();
    }

    abstract protected function init();

    // Thêm method xử lý rate limiting
    protected function check_rate_limit($key, $limit = 60, $period = 3600) {
        $attempts = get_transient('wcl_rate_limit_' . $key);
        if ($attempts && $attempts >= $limit) {
            wp_send_json_error([
                'message' => __('Too many attempts. Please try again later.', 'wp-content-locker'),
                'code' => 'rate_limit_exceeded'
            ]);
        }
        
        if (!$attempts) {
            set_transient('wcl_rate_limit_' . $key, 1, $period);
        } else {
            set_transient('wcl_rate_limit_' . $key, $attempts + 1, $period);
        }
        
        return true;
    }

    // Thêm method xử lý response chuẩn hóa
    protected function send_success($data = null, $message = '') {
        return $this->response_handler->success($data, $message);
    }

    protected function send_error($message = '', $code = '') {
        return $this->response_handler->error($message, $code);
    }

    // Cải thiện verify_nonce với nhiều options hơn
    protected function verify_nonce($action = null, $nonce_field = 'nonce') {
        $nonce = isset($_REQUEST[$nonce_field]) ? $_REQUEST[$nonce_field] : '';
        $action = $action ?? $this->nonce_action;

        if (!wp_verify_nonce($nonce, $action)) {
            $this->log_error('Invalid nonce', [
                'action' => $action,
                'ip' => $this->get_client_ip()
            ]);
            wp_send_json_error([
                'message' => __('Security check failed', 'wp-content-locker'),
                'code' => 'invalid_nonce'
            ]);
        }
        return true;
    }

    // Thêm method lấy IP client
    protected function get_client_ip() {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    // Cải thiện validate_params với type checking
    protected function validate_params($required_params = []) {
        foreach ($required_params as $param => $rules) {
            if (is_numeric($param)) {
                $param = $rules;
                $rules = ['required' => true];
            }

            if (!isset($_POST[$param]) || 
                ($rules['required'] && empty($_POST[$param]))) {
                wp_send_json_error([
                    'message' => sprintf(
                        __('Missing required parameter: %s', 'wp-content-locker'),
                        $param
                    ),
                    'code' => 'missing_parameter'
                ]);
            }

            if (isset($rules['type'])) {
                $value = $_POST[$param];
                switch ($rules['type']) {
                    case 'email':
                        if (!is_email($value)) {
                            wp_send_json_error([
                                'message' => __('Invalid email format', 'wp-content-locker'),
                                'code' => 'invalid_email'
                            ]);
                        }
                        break;
                    case 'number':
                        if (!is_numeric($value)) {
                            wp_send_json_error([
                                'message' => __('Invalid number format', 'wp-content-locker'),
                                'code' => 'invalid_number'
                            ]);
                        }
                        break;
                }
            }
        }
        return true;
    }

    // Cải thiện log_error với nhiều context hơn
    protected function log_error($message, $context = []) {
        $default_context = [
            'time' => current_time('mysql'),
            'ip' => $this->get_client_ip(),
            'user_id' => get_current_user_id(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? ''
        ];

        $context = array_merge($default_context, $context);

        $this->logger->error($message, $context);
    }

    // Thêm method xử lý cleanup
    public function __destruct() {
        // Cleanup code here if needed
    }
}