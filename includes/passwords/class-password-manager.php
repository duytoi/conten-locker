<?php
namespace WP_Content_Locker\Includes\Passwords;

class Password_Manager {
    private $wpdb;
    private $table_name;

    // Constants
    const STATUS_UNUSED = 'unused';
    const STATUS_USING = 'using';
    const STATUS_USED = 'used';
    
    const MIN_LENGTH = 8;
    const MAX_LENGTH = 32;
    const MIN_COUNT = 100;
    const MAX_COUNT = 2000;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'wcl_passwords';
    }

    public function get_password_statistics() {
        $stats = [
            'total' => 0,
            'unused' => 0,
            'used' => 0,
            'expired' => 0
        ];

        $results = $this->wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'unused' THEN 1 ELSE 0 END) as unused,
                SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used,
                SUM(CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END) as expired
            FROM {$this->table_name}",
            ARRAY_A
        );

        if ($results) {
            $stats = array_map('intval', $results);
        }

        return $stats;
    }
	/**
     * Mã hóa mật khẩu
     */
    private function encrypt_password($password) {
    if (empty($password)) {
        throw new \Exception('Password cannot be empty');
    }

    try {
        // Tạo key mã hóa nếu chưa có
        $encryption_key = get_option('wcl_encryption_key');
        if (!$encryption_key || strlen($encryption_key) != 64) { // Key phải có độ dài 64 ký tự hex
            // Tạo key mới 32 bytes (64 ký tự hex)
            $raw_key = random_bytes(32);
            $encryption_key = bin2hex($raw_key);
            update_option('wcl_encryption_key', $encryption_key);
        }

        // Tạo IV ngẫu nhiên 16 bytes
        $iv = random_bytes(16);
        
        // Mã hóa mật khẩu
        $encrypted = openssl_encrypt(
            $password,
            'AES-256-CBC',
            pack('H*', $encryption_key), // Chuyển hex string sang binary an toàn
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new \Exception('Encryption failed');
        }

        // Kết hợp IV và dữ liệu đã mã hóa
        $combined = $iv . $encrypted;
        
        // Mã hóa base64 để lưu vào database
        $result = base64_encode($combined);
        
        error_log("Password length: " . strlen($password));
        error_log("Encrypted length: " . strlen($result));
        
        return $result;

    } catch (\Exception $e) {
        error_log('Password encryption error: ' . $e->getMessage());
        throw new \Exception('Failed to encrypt password: ' . $e->getMessage());
    }
}

    /**
     * Giải mã mật khẩu
     */
    private function decrypt_password($encrypted_data) {
        if (empty($encrypted_data)) {
            throw new \Exception('Encrypted data cannot be empty');
        }

        try {
            $encryption_key = get_option('wcl_encryption_key');
            if (!$encryption_key) {
                throw new \Exception('Encryption key not found');
            }

            // Giải mã base64
            $decoded = base64_decode($encrypted_data);
            
            // Tách IV và dữ liệu đã mã hóa
            $iv = substr($decoded, 0, 16);
            $encrypted = substr($decoded, 16);

            // Giải mã
            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                hex2bin($encryption_key),
                0,
                $iv
            );

            if ($decrypted === false) {
                throw new \Exception('Decryption failed');
            }

            return $decrypted;

        } catch (\Exception $e) {
            error_log('Password decryption error: ' . $e->getMessage());
            throw new \Exception('Failed to decrypt password');
        }
    }

    /**
     * Tạo mật khẩu ngẫu nhiên
     */
    
	public function generate_passwords($count, $length, $expires_in, $type = 'alphanumeric') {
    try {
        // Validate input parameters
        $count = min(max($count, self::MIN_COUNT), self::MAX_COUNT);
        $length = min(max($length, self::MIN_LENGTH), self::MAX_LENGTH);
        
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        $passwords = [];
        $success_count = 0;
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_in} hours"));
        
        // Generate passwords
        for ($i = 0; $i < $count; $i++) {
            try {
                // Generate and encrypt password
                $plain_password = $this->generate_single_password($length, $type);
                if (empty($plain_password)) {
                    throw new \Exception('Generated password is empty');
                }

                $encrypted_password = $this->encrypt_password($plain_password);
                if (empty($encrypted_password)) {
                    throw new \Exception('Encrypted password is empty');
                }

                // Debug log
                error_log("Generated password length: " . strlen($plain_password));
                error_log("Encrypted password length: " . strlen($encrypted_password));
                
                // Verify data before insert
                $insert_data = [
                    'password' => $encrypted_password,
                    'status' => self::STATUS_UNUSED,
                    'created_at' => current_time('mysql'),
                    'expires_at' => $expires_at
                ];

                // Check if any value is null
                foreach ($insert_data as $key => $value) {
                    if (is_null($value)) {
                        throw new \Exception("$key is null");
                    }
                }

                $result = $this->wpdb->insert(
                    $this->table_name,
                    $insert_data,
                    ['%s', '%s', '%s', '%s']
                );
                
                if ($result === false) {
                    throw new \Exception($this->wpdb->last_error);
                }
                
                $success_count++;
                
            } catch (\Exception $e) {
                error_log("Error generating password #{$i}: " . $e->getMessage());
                continue;
            }
        }
        
        // Final commit
        $this->wpdb->query('COMMIT');
        return $success_count;
        
    } catch (\Exception $e) {
        // Rollback on error
        $this->wpdb->query('ROLLBACK');
        error_log("Password generation error: " . $e->getMessage());
        throw $e;
    }
}

    private function generate_single_password($length, $type) {
        // Base numeric characters
        $chars = '0123456789';
        
        // Add alphabetic characters for alphanumeric and special
        if ($type === 'alphanumeric' || $type === 'special') {
            $chars .= 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        
        // Add special characters
        if ($type === 'special') {
            $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
        }
        
        $password = '';
        $chars_length = strlen($chars);
        
        // Generate password using cryptographically secure random numbers
        try {
            for ($i = 0; $i < $length; $i++) {
                $password .= $chars[random_int(0, $chars_length - 1)];
            }
        } catch (\Exception $e) {
            // Fallback to less secure method if random_int fails
            for ($i = 0; $i < $length; $i++) {
                $password .= $chars[mt_rand(0, $chars_length - 1)];
            }
        }
        
        return $password;
    }

    public function delete_passwords($ids) {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        return $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)",
                $ids
            )
        );
    }

    public function reset_password_status($ids) {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        return $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table_name} 
                SET status = 'unused', 
                    used_at = NULL,
                    used_by = NULL
                WHERE id IN ($placeholders)",
                $ids
            )
        );
    }

    /**
     * Delete a single password
     * 
     * @param int $password_id
     * @return bool
     * @throws \Exception
     */
    public function delete_password($password_id) {
        // Verify password exists
        $password = $this->get_password($password_id);
        if (!$password) {
            throw new \Exception(__('Password not found.', 'wp-content-locker'));
        }

        return $this->delete_passwords([$password_id]) > 0;
    }

    /**
     * Get a single password by ID
     * 
     * @param int $password_id
     * @return array|null
     */
    /**
     * Cập nhật method get_password để giải mã khi lấy ra
     */
    public function get_password($password_id) {
        $password = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $password_id
            ),
            ARRAY_A
        );

        if ($password) {
            // Giải mã mật khẩu trước khi trả về
            $password['password'] = $this->decrypt_password($password['password']);
        }

        return $password;
    }

    /**
     * Update a password
     * 
     * @param int $password_id
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function update_password($password_id, $data) {
        // Verify password exists
        $password = $this->get_password($password_id);
        if (!$password) {
            throw new \Exception(__('Password not found.', 'wp-content-locker'));
        }

        // Sanitize data
        $allowed_fields = ['password', 'status', 'expires_at'];
        $update_data = array_intersect_key($data, array_flip($allowed_fields));

        if (empty($update_data)) {
            throw new \Exception(__('No valid fields to update.', 'wp-content-locker'));
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $password_id],
            null,
            ['%d']
        );

        if ($result === false) {
            throw new \Exception(__('Failed to update password.', 'wp-content-locker'));
        }

        return true;
    }
	
	// ... các properties và constants giữ nguyên ...

    /**
     * Lấy mật khẩu chưa sử dụng cho countdown
     */
    public function get_password_for_countdown() {
        try {
            $password = $this->wpdb->get_row(
                "SELECT id, password 
                FROM {$this->table_name} 
                WHERE status = 'unused' 
                AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY RAND() 
                LIMIT 1"
            );

            if (!$password) {
                throw new \Exception(__('No available passwords', 'wcl'));
            }

            // Cập nhật trạng thái sang using
            $this->wpdb->update(
                $this->table_name,
                [
                    'status' => self::STATUS_USING,
                    'used_at' => current_time('mysql')
                ],
                ['id' => $password->id]
            );

            return [
                'id' => $password->id,
                'password' => $this->decrypt_password($password->password)
            ];

        } catch (\Exception $e) {
            error_log('Error getting password for countdown: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Xác thực mật khẩu đã hiển thị từ countdown
     */
    public function verify_countdown_password($password_input) {
        try {
            // Tìm mật khẩu đang trong trạng thái using
            $stored = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT id, password 
                FROM {$this->table_name} 
                WHERE status = 'using'
                AND (expires_at IS NULL OR expires_at > NOW())
                AND password = %s",
                $this->encrypt_password($password_input)
            ));

            if (!$stored) {
                return false;
            }

            // Cập nhật trạng thái sang used
            $this->wpdb->update(
                $this->table_name,
                [
                    'status' => self::STATUS_USED,
                    'used_by' => get_current_user_id()
                ],
                ['id' => $stored->id]
            );

            return true;

        } catch (\Exception $e) {
            error_log('Password verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset mật khẩu về trạng thái unused nếu quá thời gian
     */
    public function reset_expired_passwords() {
        $timeout = apply_filters('wcl_password_timeout', 30); // 30 phút

        return $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table_name} 
            SET status = 'unused', 
                used_at = NULL, 
                used_by = NULL 
            WHERE status = 'using' 
            AND used_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)",
            $timeout
        ));
    }
}