<?php
namespace WP_Content_Locker\Includes\Encryption;
class Encryption_Service {// đổi tên class WCL_Encryption_Service sang Encryption_Service
    private $key_manager;
    private $cipher = 'aes-256-cbc';
    private $temp_dir;

    public function __construct() {
        $this->key_manager = new Key_Manager();
        $this->temp_dir = WP_CONTENT_DIR . '/wcl-temp';
        $this->ensure_temp_directory();
    }

    private function ensure_temp_directory() {
        if (!file_exists($this->temp_dir)) {
            wp_mkdir_p($this->temp_dir);
            $this->protect_directory($this->temp_dir);
        }
    }

    private function protect_directory($dir) {
        // Create .htaccess to prevent direct access
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all");
        }

        // Create index.php to prevent directory listing
        $index = $dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php // Silence is golden");
        }
    }

    public function encrypt_file($source_path, $destination_path = null) {
        if (!file_exists($source_path)) {
            throw new Exception('Source file does not exist');
        }

        if (!$destination_path) {
            $destination_path = $source_path . '.encrypted';
        }

        $key = $this->key_manager->get_encryption_key();
        $iv = $this->key_manager->get_encryption_iv();

        if (!$key || !$iv) {
            throw new Exception('Encryption keys not available');
        }

        $input = fopen($source_path, 'rb');
        $output = fopen($destination_path, 'wb');

        // Write IV at the beginning of the file
        fwrite($output, $iv);

        // Initialize encryption
        $stream = stream_get_contents($input);
        $encrypted = openssl_encrypt(
            $stream,
            $this->cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new Exception('Encryption failed: ' . openssl_error_string());
        }

        // Write encrypted data
        fwrite($output, $encrypted);

        fclose($input);
        fclose($output);

        return $destination_path;
    }

    public function decrypt_file($source_path, $destination_path = null) {
        if (!file_exists($source_path)) {
            throw new Exception('Source file does not exist');
        }

        if (!$destination_path) {
            $destination_path = $this->temp_dir . '/' . uniqid('wcl_') . '_' . 
                              basename(str_replace('.encrypted', '', $source_path));
        }

        $key = $this->key_manager->get_encryption_key();
        
        $input = fopen($source_path, 'rb');
        $output = fopen($destination_path, 'wb');

        // Read IV from the beginning of the file
        $iv = fread($input, 16);
        
        // Read encrypted data
        $encrypted = stream_get_contents($input);

        // Decrypt data
        $decrypted = openssl_decrypt(
            $encrypted,
            $this->cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new Exception('Decryption failed: ' . openssl_error_string());
        }

        // Write decrypted data
        fwrite($output, $decrypted);

        fclose($input);
        fclose($output);

        // Schedule cleanup of temporary file
        $this->schedule_cleanup($destination_path);

        return $destination_path;
    }

    private function schedule_cleanup($file_path) {
        // Schedule file deletion after 1 hour
        wp_schedule_single_event(
            time() + HOUR_IN_SECONDS,
            'wcl_cleanup_temp_file',
            array($file_path)
        );
    }

    public function cleanup_temp_file($file_path) {
        if (file_exists($file_path) && strpos($file_path, $this->temp_dir) === 0) {
            unlink($file_path);
        }
    }

    public function verify_file_integrity($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        // Read first 16 bytes (IV)
        $handle = fopen($file_path, 'rb');
        $iv = fread($handle, 16);
        fclose($handle);

        // Verify IV length
        if (strlen($iv) !== 16) {
            return false;
        }

        return true;
    }

    public function get_file_info($file_path) {
        if (!$this->verify_file_integrity($file_path)) {
            throw new Exception('Invalid or corrupted encrypted file');
        }

        $stat = stat($file_path);
        return array(
            'size' => $stat['size'] - 16, // Subtract IV size
            'encrypted_at' => $stat['mtime'],
            'is_encrypted' => true
        );
    }
}