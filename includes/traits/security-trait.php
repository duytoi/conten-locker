<?php
namespace WP_Content_Locker\Includes\Traits;

trait Security_Trait {
    /**
     * Allowed mime types for file uploads
     */
    protected $allowed_mime_types = [
        'pdf'  => 'application/pdf',
        'zip'  => 'application/zip',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png'
    ];

    /**
     * Maximum file size in bytes (50MB)
     */
    protected $max_file_size = 52428800; // 50 * 1024 * 1024

    /**
     * Sanitize input based on type
     */
    protected function sanitize_input($data, $type = 'text') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return $this->sanitize_input($item, $type);
            }, $data);
        }

        switch ($type) {
            case 'text':
                return sanitize_text_field($data);
            case 'url':
                return esc_url_raw($data);
            case 'email':
                return sanitize_email($data);
            case 'filename':
                return sanitize_file_name($data);
            case 'key':
                return sanitize_key($data);
            case 'html':
                return wp_kses_post($data);
            case 'textarea':
                return sanitize_textarea_field($data);
            case 'int':
                return intval($data);
            case 'float':
                return floatval($data);
            case 'boolean':
                return (bool) $data;
            default:
                return sanitize_text_field($data);
        }
    }

    /**
     * Generate secure random hash
     */
    protected function generate_secure_hash($length = 32) {
        try {
            return bin2hex(random_bytes($length));
        } catch (\Exception $e) {
            // Fallback if random_bytes fails
            return wp_hash(uniqid(mt_rand(), true), 'nonce');
        }
    }

    /**
     * Verify nonce with error handling
     */
    protected function verify_nonce($nonce, $action) {
        try {
            if (empty($nonce)) {
                throw new \Exception(__('Missing security token', 'wp-content-locker'));
            }

            if (!wp_verify_nonce($nonce, $action)) {
                throw new \Exception(__('Security token expired. Please refresh the page.', 'wp-content-locker'));
            }

            return true;
        } catch (\Exception $e) {
            $this->handle_security_error($e->getMessage());
        }
    }

    /**
     * Check user capabilities with error handling
     */
    protected function check_user_capabilities($capability = 'manage_options') {
        try {
            if (!is_user_logged_in()) {
                throw new \Exception(__('Please log in to continue.', 'wp-content-locker'));
            }

            if (!current_user_can($capability)) {
                throw new \Exception(__('You do not have permission to perform this action.', 'wp-content-locker'));
            }

            return true;
        } catch (\Exception $e) {
            $this->handle_security_error($e->getMessage());
        }
    }

    /**
     * Validate file upload
     */
    protected function validate_file_upload($file) {
        try {
            if (empty($file['tmp_name'])) {
                throw new \Exception(__('No file was uploaded.', 'wp-content-locker'));
            }

            // Check file size
            if ($file['size'] > $this->max_file_size) {
                throw new \Exception(__('File size exceeds the maximum limit.', 'wp-content-locker'));
            }

            // Verify mime type
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($file['tmp_name']);

            if (!in_array($mime_type, $this->allowed_mime_types)) {
                throw new \Exception(__('Invalid file type.', 'wp-content-locker'));
            }

            // Additional security checks
            if (false === wp_verify_nonce($_POST['_wpnonce'], 'file_upload')) {
                throw new \Exception(__('File upload verification failed.', 'wp-content-locker'));
            }

            return true;
        } catch (\Exception $e) {
            $this->handle_security_error($e->getMessage());
        }
    }

    /**
     * Sanitize and validate URL
     */
    protected function validate_url($url) {
        $sanitized_url = esc_url_raw($url);
        
        if (empty($sanitized_url)) {
            throw new \Exception(__('Invalid URL format.', 'wp-content-locker'));
        }

        // Additional URL validation
        if (!wp_http_validate_url($sanitized_url)) {
            throw new \Exception(__('URL validation failed.', 'wp-content-locker'));
        }

        return $sanitized_url;
    }

    /**
     * Handle security errors
     */
    protected function handle_security_error($message) {
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => $message
            ], 403);
        } else {
            wp_die($message, __('Security Error', 'wp-content-locker'), [
                'response' => 403,
                'back_link' => true
            ]);
        }
    }

    /**
     * Secure data encryption
     */
    protected function encrypt_data($data, $key = '') {
        if (empty($key)) {
            $key = wp_salt('auth');
        }
        
        $method = 'aes-256-cbc';
        $ivlen = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivlen);
        
        $encrypted = openssl_encrypt(
            $data,
            $method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return base64_encode($iv . $encrypted);
    }

    /**
     * Secure data decryption
     */
    protected function decrypt_data($encrypted_data, $key = '') {
        if (empty($key)) {
            $key = wp_salt('auth');
        }

        $encrypted_data = base64_decode($encrypted_data);
        $method = 'aes-256-cbc';
        $ivlen = openssl_cipher_iv_length($method);
        $iv = substr($encrypted_data, 0, $ivlen);
        $encrypted = substr($encrypted_data, $ivlen);

        return openssl_decrypt(
            $encrypted,
            $method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }

    /**
     * Get allowed mime types
     */
    public function get_allowed_mime_types() {
        return apply_filters('wcl_allowed_mime_types', $this->allowed_mime_types);
    }

    /**
     * Get maximum file size
     */
    public function get_max_file_size() {
        return apply_filters('wcl_max_file_size', $this->max_file_size);
    }

    /**
     * Validate and sanitize form data
     */
    protected function validate_form_data($data, $rules) {
        $sanitized = [];
        $errors = [];

        foreach ($rules as $field => $rule) {
            if (!isset($data[$field]) && $rule['required']) {
                $errors[$field] = sprintf(
                    __('%s is required.', 'wp-content-locker'),
                    $rule['label']
                );
                continue;
            }

            $value = isset($data[$field]) ? $data[$field] : '';

            // Apply sanitization
            $sanitized[$field] = $this->sanitize_input($value, $rule['type']);

            // Apply validation
            if (isset($rule['validate']) && is_callable($rule['validate'])) {
                $validation_result = call_user_func($rule['validate'], $sanitized[$field]);
                if ($validation_result !== true) {
                    $errors[$field] = $validation_result;
                }
            }
        }

        if (!empty($errors)) {
            throw new \Exception(json_encode($errors));
        }

        return $sanitized;
    }
}