<?php
namespace WP_Content_Locker\Includes\Models;

class Download extends Base_Model {
    protected $table = 'wcl_downloads';
     protected $fields; // Khai báo property ở đây
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
			'is_protected' => 0,
			'password' => '',
			'shortcode' => '',        // Lưu shortcode
			'password_id' => null,    // ID của password được assign
			'protection_type' => 'password', // password hoặc countdown
			'download_count' => 0,
            'status' => 'active',
            'expires_at' => null,
            'created_at' => null,
            'updated_at' => null
        ];
    }

    /**
     * Get download protection status
     * @return array
     */
    public function get_protection_status() {
        return array(
            'is_protected' => $this->is_protected(),
            'protection_type' => $this->protection_type ?? 'password',
            'has_password' => !empty($this->password)
        );
    }
	// Thêm method generate và get shortcode
		public function generate_shortcode() {
			return sprintf('[wcl_protected_download id="%d"]', $this->id);
		}

		public function get_shortcode() {
			return $this->shortcode ?: $this->generate_shortcode();
		}
    // Implement abstract method từ Base_Model
    protected function get_table_name() {
        return $this->table;
    }

    // Override method create từ Base_Model
    public function create($data) {
        $data = $this->prepare_data($data);
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        
        return parent::create($data);
    }

    // Override method update từ Base_Model
    public function update($id, $data) {
        $data = $this->prepare_data($data);
        $data['updated_at'] = current_time('mysql');
        
        return parent::update($id, $data);
    }

    // Chuẩn bị data trước khi lưu
    protected function prepare_data($data) {
        // Sanitize basic fields
        $prepared = [];
        foreach ($this->fields as $field => $default) {
            if (isset($data[$field])) {
                $prepared[$field] = $this->sanitize_field($field, $data[$field]);
            }
        }

        // Xử lý file size và type nếu có file_path
        if (!empty($prepared['file_path']) && file_exists($prepared['file_path'])) {
            $prepared['file_size'] = filesize($prepared['file_path']);
            $prepared['file_type'] = wp_check_filetype(basename($prepared['file_path']))['type'];
        }

        return $prepared;
    }

    // Sanitize từng trường dữ liệu
    protected function sanitize_field($field, $value) {
        switch ($field) {
            case 'title':
                return sanitize_text_field($value);
            case 'description':
                return wp_kses_post($value);
            case 'url':
                return esc_url_raw($value);
            case 'file_path':
                return sanitize_text_field($value);
            case 'status':
                return in_array($value, ['active', 'inactive', 'expired']) ? $value : 'inactive';
            case 'category_id':
                return absint($value);
            case 'download_count':
                return absint($value);
            case 'is_encrypted':
                return (bool)$value;
            case 'expires_at':
                return $value ? sanitize_text_field($value) : null;
            default:
                return sanitize_text_field($value);
        }
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

        // URL validation
        if (!empty($data['url'])) {
            if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
                $errors[] = __('Invalid URL format', 'wcl');
            }
            // Kiểm tra URL có thể truy cập được
            $response = wp_remote_head($data['url']);
            if (is_wp_error($response)) {
                $errors[] = __('URL is not accessible', 'wcl');
            }
        }

        // File validation
        if (!empty($data['file_path'])) {
            if (!file_exists($data['file_path'])) {
                $errors[] = __('File does not exist', 'wcl');
            }
        }

        // Category validation
        if (!empty($data['category_id'])) {
            $category_exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}wcl_categories WHERE id = %d",
                $data['category_id']
            ));
            if (!$category_exists) {
                $errors[] = __('Selected category does not exist', 'wcl');
            }
        }

        // Date validation
        if (!empty($data['expires_at'])) {
            $expires_at = strtotime($data['expires_at']);
            if ($expires_at === false) {
                $errors[] = __('Invalid expiration date format', 'wcl');
            } elseif ($expires_at < time()) {
                $errors[] = __('Expiration date cannot be in the past', 'wcl');
            }
        }

        // Status validation
        $valid_statuses = ['active', 'inactive', 'expired'];
        if (!empty($data['status']) && !in_array($data['status'], $valid_statuses)) {
            $errors[] = __('Invalid status', 'wcl');
        }
        
        return empty($errors) ? true : $errors;
    }

    public function is_expired() {
        if (empty($this->expires_at)) {
            return false;
        }
        return strtotime($this->expires_at) < time();
    }

    public function increment_download_count() {
        return $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET download_count = download_count + 1, 
                 updated_at = %s 
             WHERE id = %d",
            current_time('mysql'),
            $this->id
        ));
    }

    // Thêm method lấy downloads theo category
    public function get_by_category($category_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE category_id = %d AND status = 'active'
             ORDER BY created_at DESC",
            $category_id
        ));
    }

    // Thêm method search downloads
    public function search($keyword) {
        $like = '%' . $this->wpdb->esc_like($keyword) . '%';
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE (title LIKE %s OR description LIKE %s) 
             AND status = 'active'
             ORDER BY created_at DESC",
            $like,
            $like
        ));
    }
	
	// Thêm method kiểm tra protection
	public function is_protected() {
		return (bool)$this->is_protected;
	}

	public function verify_password($input_password) {
		return $this->is_protected && $this->password === $input_password;
	}
}