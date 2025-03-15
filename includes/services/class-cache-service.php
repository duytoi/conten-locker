<?php
namespace WP_Content_Locker\Services;
class WCL_Cache_Service {
    private $cache_group = 'wp_content_locker';
    private $cache_time = 3600; // 1 hour default

    public function get($key) {
        return wp_cache_get($key, $this->cache_group);
    }

    public function set($key, $value, $expiration = null) {
        $expiration = $expiration ?? $this->cache_time;
        return wp_cache_set($key, $value, $this->cache_group, $expiration);
    }

    public function delete($key) {
        return wp_cache_delete($key, $this->cache_group);
    }

    public function flush() {
        return wp_cache_flush();
    }

    public function get_transient($key) {
        return get_transient($this->get_transient_key($key));
    }

    public function set_transient($key, $value, $expiration = null) {
        $expiration = $expiration ?? $this->cache_time;
        return set_transient($this->get_transient_key($key), $value, $expiration);
    }

    public function delete_transient($key) {
        return delete_transient($this->get_transient_key($key));
    }

    private function get_transient_key($key) {
        return 'wcl_' . md5($key);
    }
}