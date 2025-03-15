<?php
namespace WP_Content_Locker\Includes\Models;
abstract class Base_Model {
    protected $wpdb;
    protected $table_name;
    protected $primary_key = 'id';
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . $this->get_table_name();
    }

    abstract protected function get_table_name();

    protected function prepare_query($query, $values) {
        return $this->wpdb->prepare($query, $values);
    }

    public function find($id) {
        $query = $this->prepare_query(
            "SELECT * FROM {$this->table_name} WHERE {$this->primary_key} = %d",
            [$id]
        );
        return $this->wpdb->get_row($query);
    }

    public function create($data) {
        $result = $this->wpdb->insert($this->table_name, $data);
        if ($result === false) {
            throw new Exception($this->wpdb->last_error);
        }
        return $this->wpdb->insert_id;
    }

    public function update($id, $data) {
        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            [$this->primary_key => $id]
        );
        if ($result === false) {
            throw new Exception($this->wpdb->last_error);
        }
        return $result;
    }

    public function delete($id) {
        return $this->wpdb->delete(
            $this->table_name,
            [$this->primary_key => $id]
        );
    }

    protected function sanitize_data($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = array_map('sanitize_text_field', $value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        return $sanitized;
    }
}