<?php
class Protection_Model extends Base_Model {
    use Security_Trait;

    protected function get_table_name() {
        return 'wcl_protections';
    }

    public function create_protection($content_id, $type, $password = null) {
        $data = [
            'content_id' => $content_id,
            'protection_type' => $this->sanitize_input($type),
            'password' => $password ? wp_hash_password($password) : null,
            'status' => 'active',
            'created_at' => current_time('mysql')
        ];
        return $this->create($data);
    }

    public function verify_password($protection_id, $password) {
        $protection = $this->find($protection_id);
        if (!$protection) {
            return false;
        }
        return wp_check_password($password, $protection->password);
    }

    public function is_content_protected($content_id) {
        $query = $this->prepare_query(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE content_id = %d AND status = 'active'",
            [$content_id]
        );
        return (bool) $this->wpdb->get_var($query);
    }
}