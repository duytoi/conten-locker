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
                $password = $this->generate_single_password($length, $type);
                
                $result = $this->wpdb->insert(
                    $this->table_name,
                    [
                        'password' => $password,
                        'status' => self::STATUS_UNUSED,
                        'created_at' => current_time('mysql'),
                        'expires_at' => $expires_at
                    ],
                    ['%s', '%s', '%s', '%s']
                );
                
                if ($result === false) {
                    throw new \Exception($this->wpdb->last_error);
                }
                
                $success_count++;
                
                // Commit every 100 insertions to prevent large transactions
                if ($success_count % 100 === 0) {
                    $this->wpdb->query('COMMIT');
                    $this->wpdb->query('START TRANSACTION');
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
    public function get_password($password_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $password_id
            ),
            ARRAY_A
        );
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
}