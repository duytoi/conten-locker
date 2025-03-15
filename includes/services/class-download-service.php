<?php
namespace WP_Content_Locker\Includes\Services;

use WP_Content_Locker\Includes\Traits\Security_Trait;
//use WP_Content_Locker\Models\Download;
use WP_Content_Locker\Includes\Models\Download; // Updated namespace
use Exception;

class Download_Service {
    use Security_Trait;

    private $wpdb;
    private $table_name;
    private $upload_dir;
    private $download_model;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'wcl_downloads';
        $this->upload_dir = wp_upload_dir();
        $this->download_model = new Download();
        $this->allowed_mime_types = [
            'pdf'  => 'application/pdf',
            'zip'  => 'application/zip',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
		// Add verification hook
    add_action('wcl_before_download', [$this, 'verify_download_access']);
    }

	/**
 * Verify download access
 */
public function verify_download_access($download_id) {
    $verification = new Verification_Service();
    $token = $_REQUEST['token'] ?? '';
    
    if (!$verification->is_verified($download_id, $token)) {
        wp_die(__('Access denied. Please verify your download request.', 'wcl'));
    }
}
    /**
     * Get download by ID
     */
    public function get($id) {
    $download_model = new Download();
    return $download_model->find($id);
}

    /**
     * Save download with enhanced security
     */
    public function save_download($data, $files = null) {
        try {
            // Security checks
            $this->check_user_capabilities('manage_options');
            $this->verify_nonce($data['wcl_nonce'] ?? '', 'wcl_download_nonce');

            $wpdb->query('START TRANSACTION');

            // Validate input data
            $this->validate_input_data($data);

            // Prepare save data
            $save_data = $this->prepare_save_data($data, $files);

            // Handle file upload if needed
            if (isset($files['file_upload'])) {
                $save_data = $this->handle_file_upload($files['file_upload'], $save_data);
            }

            // Save download
            $download_id = $this->save_download_data($save_data, $data['download_id'] ?? 0);

            $wpdb->query('COMMIT');
            return $download_id;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    private function validate_input_data($data) {
        if (empty($data['title'])) {
            throw new Exception(__('Title is required', 'wcl'));
        }

        if (!isset($data['source_type'])) {
            throw new Exception(__('Source type is required', 'wcl'));
        }

        if ($data['source_type'] === 'url' && empty($data['url'])) {
            throw new Exception(__('URL is required', 'wcl'));
        }
    }

    private function prepare_save_data($data, $files) {
        return [
            'title' => sanitize_text_field($data['title']),
            'description' => wp_kses_post($data['description']),
            'category_id' => absint($data['category_id']),
            'status' => sanitize_text_field($data['status']),
            'source_type' => sanitize_text_field($data['source_type'])
        ];
    }

    private function handle_file_upload($file, $save_data) {
        if (empty($file['tmp_name'])) {
            return $save_data;
        }

        // Verify file type
        $this->verify_file_mime_type($file);

        // Handle upload
        $upload = $this->upload_file($file);
        
        $save_data['file_path'] = $upload['file'];
        $save_data['url'] = '';

        return $save_data;
    }

    private function verify_file_mime_type($file) {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);

        if (!in_array($mime_type, $this->allowed_mime_types)) {
            throw new Exception(__('Invalid file type', 'wcl'));
        }
    }

    private function upload_file($file) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $upload = wp_handle_upload($file, [
            'test_form' => false,
            'mimes' => $this->allowed_mime_types
        ]);

        if (isset($upload['error'])) {
            throw new Exception($upload['error']);
        }

        return $upload;
    }

    private function save_download_data($save_data, $download_id = 0) {
        if ($download_id > 0) {
            $this->download_model->update($download_id, $save_data);
            return $download_id;
        }

        // For new downloads, get unused password
        $password = $this->get_unused_password();
        $save_data['password_id'] = $password->id;

        // Create new download
        $new_id = $this->download_model->create($save_data);

        // Update password status
        $this->update_password_status($password->id);

        return $new_id;
    }

    private function get_unused_password() {
        $password = $this->wpdb->get_row("
            SELECT * FROM {$this->wpdb->prefix}wcl_passwords 
            WHERE status = 'unused' 
            ORDER BY id ASC 
            LIMIT 1
        ");

        if (!$password) {
            throw new Exception(__('No available passwords found', 'wcl'));
        }

        return $password;
    }

    private function update_password_status($password_id) {
        $updated = $this->wpdb->update(
            $this->wpdb->prefix . 'wcl_passwords',
            ['status' => 'using'],
            ['id' => $password_id]
        );

        if ($updated === false) {
            throw new Exception(__('Failed to update password status', 'wcl'));
        }
    }

    /**
     * Delete multiple downloads
     */
    public function delete_multiple($ids) {
        if (empty($ids) || !is_array($ids)) {
            return 0;
        }

        // Sanitize IDs
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return 0;
        }

        try {
            $this->wpdb->query('START TRANSACTION');

            // Create placeholders for IN clause
            $placeholders = array_fill(0, count($ids), '%d');
            $placeholder_string = implode(',', $placeholders);
            
            // Get files info before deletion
            $downloads = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT id, file_path FROM {$this->table_name} WHERE id IN ($placeholder_string)",
                    $ids
                )
            );

            if (!$downloads) {
                $this->wpdb->query('ROLLBACK');
                return 0;
            }

            // Delete physical files
            $deleted_files = 0;
            foreach ($downloads as $download) {
                if (!empty($download->file_path)) {
                    $full_path = $this->get_full_file_path($download->file_path);
                    if (file_exists($full_path) && @unlink($full_path)) {
                        $deleted_files++;
                    }
                }
            }

            // Delete from database
            $result = $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE id IN ($placeholder_string)",
                    $ids
                )
            );

            if ($result === false) {
                $this->wpdb->query('ROLLBACK');
                throw new Exception('Database deletion failed');
            }

            $this->wpdb->query('COMMIT');
            return $result;

        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('WP Content Locker Delete Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get full file path
     */
    private function get_full_file_path($relative_path) {
        return path_join($this->upload_dir['basedir'], $relative_path);
    }

    /**
     * Check if file exists
     */
    private function file_exists($file_path) {
        $full_path = $this->get_full_file_path($file_path);
        return file_exists($full_path);
    }

    /**
     * Get downloads with pagination and search
     */
    public function get_downloads($args = array()) {
        $defaults = array(
            'per_page' => 20,
            'paged' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => ''
        );

        $args = wp_parse_args($args, $defaults);
        $offset = ($args['paged'] - 1) * $args['per_page'];

        $where = array('1=1');
        $values = array();

        if (!empty($args['search'])) {
            $where[] = 'title LIKE %s';
            $values[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }

        $where = implode(' AND ', $where);
        
        // Count query
        $total_query = "SELECT COUNT(*) FROM {$this->table_name}";
        if (!empty($values)) {
            $total_query .= " WHERE {$where}";
            $total = $this->wpdb->get_var($this->wpdb->prepare($total_query, $values));
        } else {
            $total = $this->wpdb->get_var($total_query);
        }

        // Main query
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $query = "SELECT * FROM {$this->table_name}";
        if (!empty($values)) {
            $query .= " WHERE {$where}";
        }
        $query .= " ORDER BY {$orderby} LIMIT %d OFFSET %d";
        
        $values[] = $args['per_page'];
        $values[] = $offset;

        $items = $this->wpdb->get_results($this->wpdb->prepare($query, $values));

        return array(
            'items' => $items,
            'total' => $total,
            'total_pages' => ceil($total / $args['per_page'])
        );
    }

    /**
     * Get statistics
     */
    public function get_statistics() {
        $stats = array(
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'total_downloads' => 0
        );

        $stats['total'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name}"
        );

        $stats['active'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
                'active'
            )
        );

        $stats['inactive'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
                'inactive'
            )
        );

        $stats['total_downloads'] = $this->wpdb->get_var(
            "SELECT SUM(download_count) FROM {$this->table_name}"
        );

        return $stats;
    }

    /**
     * Update download status
     */
    public function update_status($id, $status) {
        return $this->wpdb->update(
            $this->table_name,
            array('status' => $status),
            array('id' => $id),
            array('%s'),
            array('%d')
        ) !== false;
    }

    /**
     * Increment download count
     */
    public function increment_download_count($id) {
        return $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table_name} 
                 SET download_count = download_count + 1 
                 WHERE id = %d",
                $id
            )
        ) !== false;
    }
	/**
 * Create new download
 */
public function create($data) {
    try {
        error_log('Creating new download with data: ' . print_r($data, true));
        // Validate required fields
        if (empty($data['title'])) {
            throw new Exception(__('Title is required', 'wcl'));
        }

        // Prepare data for insertion
        $insert_data = array(
            'title' => sanitize_text_field($data['title']),
            'description' => wp_kses_post($data['description'] ?? ''),
            'file_path' => sanitize_text_field($data['file_path'] ?? ''),
            'file_type' => sanitize_text_field($data['file_type'] ?? ''),
            'file_size' => absint($data['file_size'] ?? 0),
            'url' => esc_url_raw($data['url'] ?? ''),
            'category_id' => absint($data['category_id'] ?? 0),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'is_encrypted' => absint($data['is_encrypted'] ?? 0),
            'expires_at' => isset($data['expires_at']) ? sanitize_text_field($data['expires_at']) : null
        );

        // Insert into database
        $result = $this->wpdb->insert(
            $this->table_name,
            $insert_data,
            array(
                '%s', // title
                '%s', // description
                '%s', // file_path
                '%s', // file_type
                '%d', // file_size
                '%s', // url
                '%d', // category_id
                '%s', // status
                '%d', // is_encrypted
                '%s'  // expires_at
            )
        );

        if ($result === false) {
            throw new Exception($this->wpdb->last_error);
        }

        $new_id = $this->wpdb->insert_id;
        error_log('Successfully created download with ID: ' . $new_id);

        // Update category count
        if (!empty($insert_data['category_id'])) {
            $this->update_category_count($insert_data['category_id']);
        }

        return $new_id;

    } catch (Exception $e) {
        error_log('Error in Download_Service::create: ' . $e->getMessage());
        throw new Exception(__('Failed to create download: ', 'wcl') . $e->getMessage());
    }
}

/**
 * Update existing download
 */
public function update($id, $data) {
    try {
        error_log('Updating download ID ' . $id . ' with data: ' . print_r($data, true));

        // Validate ID
        if (empty($id)) {
            throw new Exception(__('Invalid download ID', 'wcl'));
        }

        // Get old category ID for updating counts
        $old_category_id = null;
        if (isset($data['category_id'])) {
            $old_download = $this->get($id);
            if ($old_download) {
                $old_category_id = $old_download->category_id;
            }
        }

        // Prepare update data
        $update_data = array();

        // Map fields with their sanitization
        $fields_map = array(
            'title' => array('sanitize_text_field', '%s'),
            'description' => array('wp_kses_post', '%s'),
            'file_path' => array('sanitize_text_field', '%s'),
            'file_type' => array('sanitize_text_field', '%s'),
            'file_size' => array('absint', '%d'),
            'url' => array('esc_url_raw', '%s'),
            'category_id' => array('absint', '%d'),
            'status' => array('sanitize_text_field', '%s'),
            'is_encrypted' => array('absint', '%d'),
            'expires_at' => array('sanitize_text_field', '%s')
        );

        $formats = array();
        foreach ($fields_map as $field => $config) {
            if (isset($data[$field])) {
                $sanitize_callback = $config[0];
                $update_data[$field] = $sanitize_callback($data[$field]);
                $formats[] = $config[1];
            }
        }

        // Update database
        if (!empty($update_data)) {
            $result = $this->wpdb->update(
                $this->table_name,
                $update_data,
                array('id' => $id),
                $formats,
                array('%d')
            );

            if ($result === false) {
                throw new Exception($this->wpdb->last_error);
            }

            // Update category counts if category changed
            if (isset($data['category_id']) && $old_category_id != $data['category_id']) {
                if ($old_category_id) {
                    $this->update_category_count($old_category_id);
                }
                $this->update_category_count($data['category_id']);
            }
        }

        error_log('Successfully updated download ID: ' . $id);
        return $id;

    } catch (Exception $e) {
        error_log('Error in Download_Service::update: ' . $e->getMessage());
        throw new Exception(__('Failed to update download: ', 'wcl') . $e->getMessage());
    }
}

/**
 * Update category count
 */
private function update_category_count($category_id) {
    if (empty($category_id)) return;

    $count = $this->wpdb->get_var($this->wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->table_name} WHERE category_id = %d AND status = 'active'",
        $category_id
    ));

    $this->wpdb->update(
        $this->wpdb->prefix . 'wcl_categories',
        array('count' => $count),
        array('id' => $category_id),
        array('%d'),
        array('%d')
    );
}

	/**
 * Process download request

public function process_download_request($download_id) {
    $download_model = new Download();
    $download = $download_model->find($download_id);
    
    if ($download && $download->status === 'active') {
        if (!empty($download->file_path)) {
            $this->serve_file($download);
        } elseif (!empty($download->url)) {
            wp_redirect($download->url);
            exit;
        }
    }
    
    wp_die(__('Download not found or inactive', 'wcl'));
}
 */
 
 /**
 * Process download request
 */
public function process_download_request($download_id) {
    try {
        // 1. Verify traffic first
        $verification = new Verification_Service();
        $token = $_REQUEST['token'] ?? '';
        
        if (!$verification->is_verified($download_id, $token)) {
            wp_die(__('Download verification failed. Please try again.', 'wcl'));
        }

        // 2. Get download
        $download_model = new Download();
        $download = $download_model->find($download_id);
        
        if (!$download || $download->status !== 'active') {
            wp_die(__('Download not found or inactive', 'wcl'));
        }

        // 3. Process download
        if (!empty($download->file_path)) {
            // Track download
            $this->increment_download_count($download_id);
            
            // Serve file
            $this->serve_file($download);
        } elseif (!empty($download->url)) {
            // Track download
            $this->increment_download_count($download_id);
            
            // Redirect to URL
            wp_redirect($download->url);
            exit;
        }

        wp_die(__('Invalid download source', 'wcl'));

    } catch (Exception $e) {
        error_log('Download Process Error: ' . $e->getMessage());
        wp_die(__('An error occurred while processing your download', 'wcl'));
    }
}
/**
 * Serve file download
 */
private function serve_file($download) {
    $file_path = WP_CONTENT_DIR . '/uploads/' . $download->file_path;
    
    if (file_exists($file_path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }
    
    wp_die(__('File not found', 'wcl'));
}
}