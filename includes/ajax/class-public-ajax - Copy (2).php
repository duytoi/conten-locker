<?php
class WCL_Public_Ajax extends WCL_Ajax_Handler {
    private $download_service;
    private $protection_service;
    private $access_log_service;
	private $password_manager; // Thêm password manager
	
    protected function init() {
        $this->download_service = new Download_Service();
        $this->protection_service = new Protection_Service();
        $this->access_log_service = new Access_Log_Service();
        $this->nonce_action = 'wcl_public_nonce';
        $this->nonce_name = 'nonce';

        // Public actions
		add_action('wp_ajax_wcl_get_countdown_password', [$this, 'handle_get_countdown_password']);
        add_action('wp_ajax_nopriv_wcl_get_countdown_password', [$this, 'handle_get_countdown_password']);
        
        add_action('wp_ajax_wcl_verify_countdown_password', [$this, 'handle_verify_countdown_password']);
        add_action('wp_ajax_nopriv_wcl_verify_countdown_password', [$this, 'handle_verify_countdown_password']);
		
        add_action('wp_ajax_wcl_unlock_content', [$this, 'handle_unlock_content']);
        add_action('wp_ajax_nopriv_wcl_unlock_content', [$this, 'handle_unlock_content']);
        
        add_action('wp_ajax_wcl_verify_download', [$this, 'handle_verify_download']);
        add_action('wp_ajax_nopriv_wcl_verify_download', [$this, 'handle_verify_download']);
        
        add_action('wp_ajax_wcl_log_download', [$this, 'handle_log_download']);
        add_action('wp_ajax_nopriv_wcl_log_download', [$this, 'handle_log_download']);
		 // Thêm action mới cho mark_countdown_complete
		add_action('wp_ajax_wcl_mark_countdown_complete', [$this, 'handle_mark_countdown_complete']);
		add_action('wp_ajax_nopriv_wcl_mark_countdown_complete', [$this, 'handle_mark_countdown_complete']);
	}
	
		/**
 * Handle marking countdown as complete
 */
public function handle_mark_countdown_complete() {
    $this->verify_nonce();
    
    try {
        $protection_id = $this->sanitize_input($_POST['protection_id'], 'int');
        $protection = $this->protection_service->get_protection($protection_id);
        
        if (!$protection) {
            throw new Exception(__('Protection not found', 'wp-content-locker'));
        }

        // Log completion
        $this->access_log_service->log_access([
            'protection_id' => $protection_id,
            'status' => 'success', 
            'method' => 'countdown_complete',
            'ip_address' => $this->security_service->get_client_ip()
        ]);

        wp_send_json_success([
            'show_password' => $protection->requires_password(),
            'next_action' => $protection->get_after_countdown_action()
        ]);

    } catch (Exception $e) {
        $this->log_error('Failed to mark countdown complete', [
            'error' => $e->getMessage(),
            'protection_id' => $protection_id ?? 0
        ]);

        wp_send_json_error([
            'message' => $e->getMessage()
        ]);
    }
}
	/**
     * Xử lý lấy mật khẩu khi countdown kết thúc
     */
    public function handle_get_countdown_password() {
        $this->verify_nonce();
        
        try {
            // Reset mật khẩu hết hạn trước
            $this->password_manager->reset_expired_passwords();

            // Lấy mật khẩu mới
            $password = $this->password_manager->get_password_for_countdown();

            // Log access
            $this->access_log_service->log_access([
                'status' => 'success',
                'method' => 'countdown',
                'ip_address' => $this->security_service->get_client_ip()
            ]);

            wp_send_json_success([
                'password' => $password['password']
            ]);

        } catch (Exception $e) {
            $this->log_error('Failed to get countdown password', [
                'error' => $e->getMessage()
            ]);

            wp_send_json_error([
                'message' => __('Unable to get password. Please try again.', 'wp-content-locker')
            ]);
        }
    }
	 /**
     * Xử lý verify mật khẩu từ countdown
     */
    public function handle_verify_countdown_password() {
        $this->verify_nonce();
        $this->validate_params(['password', 'download_id']);

        try {
            $password = $this->sanitize_input($_POST['password']);
            $download_id = $this->sanitize_input($_POST['download_id'], 'int');

            // Verify password
            $verified = $this->password_manager->verify_countdown_password($password);

            if (!$verified) {
                throw new Exception(__('Invalid or expired password', 'wp-content-locker'));
            }

            // Get download URL if verified
            $download_url = $this->download_service->get_download_url($download_id);

            // Log successful verification
            $this->access_log_service->log_access([
                'download_id' => $download_id,
                'status' => 'success',
                'method' => 'countdown_password',
                'ip_address' => $this->security_service->get_client_ip()
            ]);

            wp_send_json_success([
                'message' => __('Password verified successfully', 'wp-content-locker'),
                'download_url' => $download_url
            ]);

        } catch (Exception $e) {
            // Log failed attempt
            $this->access_log_service->log_access([
                'download_id' => $download_id ?? 0,
                'status' => 'failed',
                'method' => 'countdown_password',
                'ip_address' => $this->security_service->get_client_ip(),
                'error' => $e->getMessage()
            ]);

            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
	 /**
     * Cập nhật verify_download_protection để hỗ trợ countdown password
     */
    private function verify_download_protection($protection) {
    switch ($protection->type) {
        case 'password':
            if (!isset($_POST['password']) || 
                !$this->protection_service->verify_password($protection->id, $_POST['password'])) {
                throw new Exception(__('Invalid password', 'wp-content-locker'));
            }
            break;

        case 'countdown':
            if (!isset($_POST['countdown_completed']) || !$_POST['countdown_completed']) {
                throw new Exception(__('Countdown not completed', 'wp-content-locker'));
            }
            // Verify countdown password if required
            if ($protection->requires_password() && isset($_POST['password'])) {
                if (!$this->password_manager->verify_countdown_password($_POST['password'])) {
                    throw new Exception(__('Invalid countdown password', 'wp-content-locker'));
                }
            }
            break;
    }
}
	
    public function handle_unlock_content() {
        $this->verify_nonce();
        $this->validate_params(['protection_id', 'unlock_method']);

        try {
            $protection_id = $this->sanitize_input($_POST['protection_id'], 'int');
            $unlock_method = $this->sanitize_input($_POST['unlock_method']);

            // Verify protection exists
            $protection = $this->protection_service->get_protection($protection_id);
            if (!$protection) {
                throw new Exception(__('Protection not found', 'wp-content-locker'));
            }

            // Check if protection is active
            if ($protection->status !== 'active') {
                throw new Exception(__('This content is not currently protected', 'wp-content-locker'));
            }

            // Handle different unlock methods
            switch ($unlock_method) {
                case 'password':
                    $this->validate_params(['password']);
                    $password = $this->sanitize_input($_POST['password']);
                    
                    if (!$this->protection_service->verify_password($protection_id, $password)) {
                        throw new Exception(__('Invalid password', 'wp-content-locker'));
                    }
                    break;

                case 'countdown':
                    // Verify countdown completion
                    if (!isset($_POST['countdown_completed']) || !$_POST['countdown_completed']) {
                        throw new Exception(__('Countdown not completed', 'wp-content-locker'));
                    }
                    break;

                default:
                    throw new Exception(__('Invalid unlock method', 'wp-content-locker'));
            }

            // Log successful unlock
            $this->access_log_service->log_access([
                'protection_id' => $protection_id,
                'status' => 'success',
                'method' => $unlock_method,
                'ip_address' => $this->security_service->get_client_ip()
            ]);

            // Get protected content
            $content = $this->protection_service->get_protected_content($protection_id);

            wp_send_json_success([
                'message' => __('Content unlocked successfully', 'wp-content-locker'),
                'content' => $content
            ]);

        } catch (Exception $e) {
            // Log failed attempt
            $this->access_log_service->log_access([
                'protection_id' => $protection_id ?? 0,
                'status' => 'failed',
                'method' => $unlock_method ?? 'unknown',
                'ip_address' => $this->security_service->get_client_ip(),
                'error' => $e->getMessage()
            ]);

            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function handle_verify_download() {
        $this->verify_nonce();
        $this->validate_params(['download_id']);

        try {
            $download_id = $this->sanitize_input($_POST['download_id'], 'int');
            
            // Check if download exists and is active
            $download = $this->download_service->get_download($download_id);
            if (!$download || $download->status !== 'active') {
                throw new Exception(__('Download not available', 'wp-content-locker'));
            }

            // Check protection requirements
            $protection = $this->protection_service->get_download_protection($download_id);
            if ($protection) {
                $this->verify_download_protection($protection);
            }

            wp_send_json_success([
                'message' => __('Download verified successfully', 'wp-content-locker'),
                'download_url' => $this->download_service->get_download_url($download_id)
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    //private function verify_download_protection($protection) {
     //   switch ($protection->type) {
     //       case 'password':
       //         if (!isset($_POST['password']) || 
         //           !$this->protection_service->verify_password($protection->id, $_POST['password'])) {
           //         throw new Exception(__('Invalid password', 'wp-content-locker'));
             //   }
               // break;

            //case 'countdown':
              //  if (!isset($_POST['countdown_completed']) || !$_POST['countdown_completed']) {
                //    throw new Exception(__('Countdown not completed', 'wp-content-locker'));
                //}
               // break;
        //}
    //}

    public function handle_log_download() {
        $this->verify_nonce();
        $this->validate_params(['download_id']);

        try {
            $download_id = $this->sanitize_input($_POST['download_id'], 'int');
            
            $this->access_log_service->log_download([
                'download_id' => $download_id,
                'ip_address' => $this->security_service->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referer' => $_SERVER['HTTP_REFERER'] ?? ''
            ]);

            wp_send_json_success();

        } catch (Exception $e) {
            $this->log_error('Failed to log download', [
                'error' => $e->getMessage(),
                'download_id' => $download_id
            ]);

            wp_send_json_error();
        }
    }
}