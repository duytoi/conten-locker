<?php
namespace WP_Content_Locker\Includes\Models;
class Download extends Base_Model {
    protected $table = 'wcl_downloads';
    
    public function __construct() {
        parent::__construct();
        $this->fields = [
            'title' => '',
            'description' => '',
            'file_path' => '',
            'file_type' => '',
            'file_size' => 0,
            'url' => '',
            'category_id' => 0,
            'download_count' => 0,
            'is_encrypted' => 0,
            'status' => 'active',
            'expires_at' => null,
        ];
    }

    public function validate($data) {
        $errors = [];
        
        // Required fields
        if (empty($data['title'])) {
            $errors[] = __('Title is required', 'wcl');
        }
        
        if (empty($data['file_path']) && empty($data['url'])) {
            $errors[] = __('Either file or URL is required', 'wcl');
        }

        // Validate URL if provided
        if (!empty($data['url']) && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $errors[] = __('Invalid URL format', 'wcl');
        }

        // Validate category if provided
        if (!empty($data['category_id'])) {
            $category_exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}wcl_categories WHERE id = %d",
                $data['category_id']
            ));
            if (!$category_exists) {
                $errors[] = __('Selected category does not exist', 'wcl');
            }
        }

        // Validate expiration date
        if (!empty($data['expires_at'])) {
            $expires_at = strtotime($data['expires_at']);
            if ($expires_at === false || $expires_at < time()) {
                $errors[] = __('Invalid expiration date', 'wcl');
            }
        }

        // Validate status
        $valid_statuses = ['active', 'inactive', 'expired'];
        if (!empty($data['status']) && !in_array($data['status'], $valid_statuses)) {
            $errors[] = __('Invalid status', 'wcl');
        }
        
        return empty($errors) ? true : $errors;
    }

    public function get_shortcode() {
        return sprintf('[wcl_download id="%d"]', $this->id);
    }

    public function is_expired() {
        if (empty($this->expires_at)) {
            return false;
        }
        return strtotime($this->expires_at) < time();
    }

    public function increment_download_count() {
        return $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->wpdb->prefix}{$this->table} 
             SET download_count = download_count + 1 
             WHERE id = %d",
            $this->id
        ));
    }
}