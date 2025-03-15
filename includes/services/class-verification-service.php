<?php
namespace WP_Content_Locker\Includes\Services;

defined('ABSPATH') || exit;

class Verification_Service {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'init_session']);
        add_action('wp_ajax_wcl_verify_download', [$this, 'verify_download_request']);
        add_action('wp_ajax_nopriv_wcl_verify_download', [$this, 'verify_download_request']);
    }

    /**
     * Initialize session
     */
    public function init_session() {
        if (!session_id()) {
            session_start();
        }
    }

    /**
     * Verify download request
     */
    public function verify_download_request() {
        check_ajax_referer('wcl_verify_download', 'nonce');

        $download_id = isset($_POST['download_id']) ? absint($_POST['download_id']) : 0;
        
        if (!$download_id) {
            wp_send_json_error(['message' => __('Invalid download ID', 'wcl')]);
        }

        // Verify request
        $is_valid = $this->validate_request($download_id);
        
        if (is_wp_error($is_valid)) {
            wp_send_json_error(['message' => $is_valid->get_error_message()]);
        }

        // Set verification token
        $token = $this->set_verification_token($download_id);

        wp_send_json_success([
            'token' => $token,
            'message' => __('Verification successful', 'wcl')
        ]);
    }

    /**
     * Validate request
     */
    private function validate_request($download_id) {
        // Check bot
        if ($this->is_bot()) {
            return new \WP_Error('bot_detected', __('Bot traffic detected', 'wcl'));
        }

        // Validate download
        $download_service = new Download_Service();
        $download = $download_service->get_download($download_id);
        
        if (!$download) {
            return new \WP_Error('invalid_download', __('Invalid download', 'wcl'));
        }

        return true;
    }

    /**
     * Check if request is from bot
     */
    private function is_bot() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (empty($user_agent)) {
            return true;
        }

        $bot_signs = ['bot', 'spider', 'crawler'];
        foreach ($bot_signs as $sign) {
            if (stripos($user_agent, $sign) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set verification token
     */
    private function set_verification_token($download_id) {
        $token = wp_generate_password(32, false);
        $_SESSION['wcl_verified_' . $download_id] = $token;
        return $token;
    }

    /**
     * Check if download is verified
     */
    public function is_verified($download_id, $token = '') {
        if (empty($token)) {
            return false;
        }
        
        return $_SESSION['wcl_verified_' . $download_id] === $token;
    }
}