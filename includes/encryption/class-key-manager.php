<?php
namespace WP_Content_Locker\Includes\Encryption;
class Key_Manager { //sửa WCL_Key_Manager thành Key_Manager
    private $key_option = 'wcl_encryption_key';
    private $iv_option = 'wcl_encryption_iv';
    private $key_length = 32; // 256 bits
    private $iv_length = 16; // 128 bits

    public function __construct() {
        $this->maybe_generate_keys();
    }

    private function maybe_generate_keys() {
        if (!$this->get_encryption_key()) {
            $this->generate_new_keys();
        }
    }

    public function generate_new_keys() {
        $key = $this->generate_random_key($this->key_length);
        $iv = $this->generate_random_key($this->iv_length);

        $this->save_keys($key, $iv);
    }

    private function generate_random_key($length) {
        try {
            return random_bytes($length);
        } catch (Exception $e) {
            // Fallback to less secure method if random_bytes fails
            $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $key = '';
            for ($i = 0; $i < $length; $i++) {
                $key .= $chars[random_int(0, strlen($chars) - 1)];
            }
            return $key;
        }
    }

    private function save_keys($key, $iv) {
        update_option($this->key_option, base64_encode($key), true);
        update_option($this->iv_option, base64_encode($iv), true);
    }

    public function get_encryption_key() {
        $key = get_option($this->key_option);
        return $key ? base64_decode($key) : null;
    }

    public function get_encryption_iv() {
        $iv = get_option($this->iv_option);
        return $iv ? base64_decode($iv) : null;
    }

    public function rotate_keys() {
        $this->generate_new_keys();
        // TODO: Implement re-encryption of existing files with new keys
    }

    public function export_keys() {
        return array(
            'key' => $this->get_encryption_key(),
            'iv' => $this->get_encryption_iv()
        );
    }

    public function import_keys($key, $iv) {
        if (strlen($key) !== $this->key_length || strlen($iv) !== $this->iv_length) {
            throw new Exception('Invalid key length');
        }
        $this->save_keys($key, $iv);
    }
}