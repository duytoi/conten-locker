<?php
class WCL_Statistics_Model extends WCL_Base_Model {
    protected $table_name = 'wcl_statistics';

    public function __construct() {
        parent::__construct();
    }

    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->get_table_name()} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            entity_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            referer varchar(255) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'success',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            meta text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY type_entity (type, entity_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        $this->maybe_create_table($sql);
    }

    public function log_event($data) {
        $defaults = array(
            'type' => '',
            'entity_id' => 0,
            'action' => '',
            'user_id' => get_current_user_id(),
            'ip_address' => '',
            'user_agent' => '',
            'referer' => '',
            'status' => 'success',
            'meta' => null
        );

        $data = wp_parse_args($data, $defaults);

        if (is_array($data['meta'])) {
            $data['meta'] = json_encode($data['meta']);
        }

        return $this->insert($data);
    }

    public function get_download_stats($download_id, $period = '30days') {
        $where = $this->wpdb->prepare(
            "type = 'download' AND entity_id = %d",
            $download_id
        );

        switch ($period) {
            case '7days':
                $date_limit = '7 DAYS';
                break;
            case '30days':
                $date_limit = '30 DAYS';
                break;
            case '90days':
                $date_limit = '90 DAYS';
                break;
            case 'all':
                $date_limit = false;
                break;
            default:
                $date_limit = '30 DAYS';
        }

        if ($date_limit) {
            $where .= $this->wpdb->prepare(
                " AND created_at >= DATE_SUB(NOW(), INTERVAL %s)",
                $date_limit
            );
        }

        return $this->wpdb->get_results(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                COUNT(DISTINCT ip_address) as unique_downloads
            FROM {$this->get_table_name()}
            WHERE {$where}
            GROUP BY DATE(created_at)
            ORDER BY date ASC"
        );
    }

    public function get_protection_stats($protection_id, $period = '30days') {
        // Similar to get_download_stats but for protection attempts
        $where = $this->wpdb->prepare(
            "type = 'protection' AND entity_id = %d",
            $protection_id
        );

        // Add date limit
        if ($period !== 'all') {
            $interval = str_replace('days', ' DAYS', $period);
            $where .= $this->wpdb->prepare(
                " AND created_at >= DATE_SUB(NOW(), INTERVAL %s)",
                $interval
            );
        }

        return $this->wpdb->get_results(
            "SELECT 
                DATE(created_at) as date,
                status,
                COUNT(*) as total
            FROM {$this->get_table_name()}
            WHERE {$where}
            GROUP BY DATE(created_at), status
            ORDER BY date ASC"
        );
    }

    public function get_top_downloads($limit = 10, $period = '30days') {
        $where = "type = 'download'";

        if ($period !== 'all') {
            $interval = str_replace('days', ' DAYS', $period);
            $where .= $this->wpdb->prepare(
                " AND created_at >= DATE_SUB(NOW(), INTERVAL %s)",
                $interval
            );
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT 
                    entity_id,
                    COUNT(*) as total_downloads,
                    COUNT(DISTINCT ip_address) as unique_downloads
                FROM {$this->get_table_name()}
                WHERE {$where}
                GROUP BY entity_id
                ORDER BY total_downloads DESC
                LIMIT %d",
                $limit
            )
        );
    }

    public function get_protection_effectiveness($period = '30days') {
        $where = "type = 'protection'";

        if ($period !== 'all') {
            $interval = str_replace('days', ' DAYS', $period);
            $where .= $this->wpdb->prepare(
                " AND created_at >= DATE_SUB(NOW(), INTERVAL %s)",
                $interval
            );
        }

        return $this->wpdb->get_results(
            "SELECT 
                entity_id,
                status,
                COUNT(*) as total_attempts,
                COUNT(DISTINCT ip_address) as unique_attempts
            FROM {$this->get_table_name()}
            WHERE {$where}
            GROUP BY entity_id, status"
        );
    }
}