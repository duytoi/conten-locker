<?php
class Access_Log_Service {
    use Security_Trait;

    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'wcl_access_logs';
    }

    public function log_access($protection_id, $status = 'success') {
        $data = [
            'protection_id' => $protection_id,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $this->sanitize_input($_SERVER['HTTP_USER_AGENT']),
            'status' => $status,
            'accessed_at' => current_time('mysql')
        ];
        
        return $this->wpdb->insert($this->table_name, $data);
    }

    public function check_rate_limit($ip, $timeframe = 3600, $limit = 10) {
        $query = $this->prepare_query(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE ip_address = %s 
            AND accessed_at >= DATE_SUB(NOW(), INTERVAL %d SECOND)",
            [$ip, $timeframe]
        );
        
        $count = $this->wpdb->get_var($query);
        return $count < $limit;
    }

    private function get_client_ip() {
        $ip_headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = filter_var($_SERVER[$header], FILTER_VALIDATE_IP);
                if ($ip) return $ip;
            }
        }
        return '0.0.0.0';
    }
}