<?php
namespace WP_Content_Locker\Includes\Passwords;

use WP_Content_Locker\Includes\Services\Protection_Service;

class Password_Handler {
    private $wpdb;
    private $password_manager;
    private $protection_service;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'wcl_passwords';
        $this->password_manager = new Password_Manager();
        $this->protection_service = new Protection_Service();

        // Add AJAX handlers
        add_action('wp_ajax_wcl_verify_password', array($this, 'handle_password_verification'));
        add_action('wp_ajax_nopriv_wcl_verify_password', array($this, 'handle_password_verification'));
        add_action('wp_ajax_wcl_check_countdown', array($this, 'handle_countdown_check'));
        add_action('wp_ajax_nopriv_wcl_check_countdown', array($this, 'handle_countdown_check'));
    }

    /**
     * Handle password generation request
     */
    public function handle_generate_request() {
        try {
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Unauthorized access', 'wcl'));
            }

            check_admin_referer('wcl_generate_passwords', 'wcl_password_nonce');

            $params = $this->validate_generation_params($_POST);
            
            // Generate passwords with protection settings
            $result = $this->password_manager->generate_passwords(
                $params['count'],
                $params['length'],
                $params['expires'],
                [
                    'protection_type' => 'countdown',
                    'countdown_enabled' => 1,
                    'countdown_duration' => $params['countdown_duration'] ?? 60
                ]
            );

            wp_redirect(add_query_arg(
                array(
                    'page' => 'wcl-passwords',
                    'generated' => $result ? 'success' : 'error',
                    'count' => $params['count']
                ),
                admin_url('admin.php')
            ));
            exit;

        } catch (\Exception $e) {
            wp_die($e->getMessage());
        }
    }

    /**
     * Handle password verification with countdown integration
     */
    public function handle_password_verification() {
        check_ajax_referer('wcl_verify_password', 'nonce');

        try {
            $password = isset($_POST['password']) ? sanitize_text_field($_POST['password']) : '';
            $protection_id = isset($_POST['protection_id']) ? intval($_POST['protection_id']) : 0;

            if (empty($password) || empty($protection_id)) {
                throw new \Exception(__('Invalid request parameters', 'wcl'));
            }

            // Verify password
            $result = $this->password_manager->verify_password($password);
            
            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            // Get protection details
            $protection = $this->protection_service->get_protection($protection_id);
            
            if (!$protection) {
                throw new \Exception(__('Protection not found', 'wcl'));
            }

            // Start countdown session if enabled
            if ($protection->countdown_enabled) {
                $countdown_session = $this->protection_service->start_countdown_session($protection_id);
                
                wp_send_json_success([
                    'message' => __('Password verified. Please complete the countdown.', 'wcl'),
                    'countdown' => true,
                    'session' => $countdown_session,
                    'settings' => $this->protection_service->get_countdown_settings($protection_id)
                ]);
            }

            // If no countdown, proceed with direct access
            $redirect_url = $this->protection_service->get_content_url($protection_id);
            
            wp_send_json_success([
                'message' => __('Access granted', 'wcl'),
                'redirect_url' => $redirect_url
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle countdown check
     */
    public function handle_countdown_check() {
        check_ajax_referer('wcl_countdown_check', 'nonce');

        try {
            $protection_id = isset($_POST['protection_id']) ? intval($_POST['protection_id']) : 0;
            $stage = isset($_POST['stage']) ? sanitize_text_field($_POST['stage']) : 'first';

            if (!$protection_id) {
                throw new \Exception(__('Invalid protection ID', 'wcl'));
            }

            // Validate countdown completion
            if (!$this->protection_service->validate_countdown_completion($protection_id, $stage)) {
                throw new \Exception(__('Countdown not completed', 'wcl'));
            }

            // Update countdown progress
            $completed = $stage === 'second' || 
                        $this->protection_service->get_countdown_settings($protection_id)['mode'] === 'single';

            $this->protection_service->update_countdown_progress($protection_id, $stage, $completed);

            if ($completed) {
                $redirect_url = $this->protection_service->get_content_url($protection_id);
                wp_send_json_success([
                    'message' => __('Countdown completed', 'wcl'),
                    'completed' => true,
                    'redirect_url' => $redirect_url
                ]);
            }

            wp_send_json_success([
                'message' => __('Stage completed', 'wcl'),
                'completed' => false,
                'next_stage' => 'second'
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validate password generation parameters
     */
    private function validate_generation_params($params) {
        $defaults = [
            'password_count' => 10,
            'password_length' => 8,
            'password_expires' => 24,
            'expiry_unit' => 'hours',
            'countdown_duration' => 60
        ];

        $validated = [];
        
        $validated['count'] = filter_var($params['password_count'] ?? $defaults['password_count'], 
            FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);

        $validated['length'] = filter_var($params['password_length'] ?? $defaults['password_length'],
            FILTER_VALIDATE_INT, ['options' => ['min_range' => 6, 'max_range' => 32]]);

        $validated['expires'] = filter_var($params['password_expires'] ?? $defaults['password_expires'],
            FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $validated['countdown_duration'] = filter_var($params['countdown_duration'] ?? $defaults['countdown_duration'],
            FILTER_VALIDATE_INT, ['options' => ['min_range' => 10, 'max_range' => 3600]]);

        if (isset($params['expiry_unit']) && in_array($params['expiry_unit'], ['hours', 'days'])) {
            $validated['expires'] *= ($params['expiry_unit'] === 'days') ? 24 : 1;
        }

        return $validated;
    }
}