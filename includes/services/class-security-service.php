<?php
namespace WP_Content_Locker\Includes\Services;

use WP_Content_Locker\Includes\Traits\Security_Trait;

class Security_Service {
    use Security_Trait;

    private $token_expiration = 3600; // 1 hour

    public function generate_access_token($protection_id) {
        $data = [
            'protection_id' => $protection_id,
            'timestamp' => time(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ];

        return $this->encrypt_data(json_encode($data));
    }

    public function validate_token($token) {
        try {
            $data = json_decode($this->decrypt_data($token), true);
            
            if (!$data || 
                !isset($data['timestamp']) || 
                !isset($data['protection_id']) ||
                !isset($data['user_agent'])) {
                return false;
            }

            // Check expiration
            if (time() - $data['timestamp'] > $this->token_expiration) {
                return false;
            }

            // Validate user agent
            if ($data['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
                return false;
            }

            return $data['protection_id'];
        } catch (Exception $e) {
            return false;
        }
    }

    private function encrypt_data($data) {
        $key = wp_salt('auth');
        $method = "AES-256-CBC";
        $iv = random_bytes(16);
        
        $encrypted = openssl_encrypt(
            $data,
            $method,
            $key,
            0,
            $iv
        );

        return base64_encode($iv . $encrypted);
    }

    private function decrypt_data($encrypted_data) {
        $key = wp_salt('auth');
        $method = "AES-256-CBC";
        
        $decoded = base64_decode($encrypted_data);
        $iv = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);

        return openssl_decrypt(
            $encrypted,
            $method,
            $key,
            0,
            $iv
        );
    }
	
	public function verify_google_traffic() {
    // Lưu debug info
    $debug_info = [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
        'gtm_data' => $_COOKIE['wcl_gtm_data'] ?? null,
        'is_google' => false,
        'detection_method' => []
    ];

    // 1. Check referrer
    if ($this->is_google_traffic()) {
        $debug_info['is_google'] = true;
        $debug_info['detection_method'][] = 'referrer';
    }

    // 2. Check GTM data
    if ($this->verify_gtm_traffic()) {
        $debug_info['is_google'] = true;
        $debug_info['detection_method'][] = 'gtm';
    }

    // 3. Check Google bot
    if ($this->is_google_bot()) {
        $debug_info['is_google'] = true;
        $debug_info['detection_method'][] = 'googlebot';
    }

    // Add console debugging
    add_action('wp_footer', function() use ($debug_info) {
        echo "<script>
            console.log('WP Content Locker - Traffic Verification:');
            console.log(" . json_encode($debug_info) . ");
        </script>";
    });

		return $debug_info['is_google'];
	}
}