<?php
class WCL_Admin_Ajax extends WCL_Ajax_Handler {
    private $download_service;
    private $protection_service;

    protected function init() {
        $this->download_service = new Download_Service();
        $this->protection_service = new Protection_Service();
        $this->nonce_action = 'wcl_admin_nonce';
        $this->nonce_name = 'nonce';

        // Download management
        add_action('wp_ajax_wcl_save_download', [$this, 'handle_save_download']);
        add_action('wp_ajax_wcl_delete_download', [$this, 'handle_delete_download']);
        add_action('wp_ajax_wcl_bulk_action_downloads', [$this, 'handle_bulk_action_downloads']);

        // Category management
        add_action('wp_ajax_wcl_add_category', [$this, 'handle_add_category']);
        add_action('wp_ajax_wcl_edit_category', [$this, 'handle_edit_category']);
        add_action('wp_ajax_wcl_delete_category', [$this, 'handle_delete_category']);

        // Protection management
        add_action('wp_ajax_wcl_update_protection', [$this, 'handle_update_protection']);
    }
	//save GA4 và GTM
	public function handle_save_settings() {
    check_ajax_referer('wcl_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $settings = array(
        'ga4_enabled' => isset($_POST['ga4_enabled']) ? 1 : 0,
        'ga4_measurement_id' => sanitize_text_field($_POST['ga4_measurement_id']),
        'gtm_container_id' => sanitize_text_field($_POST['gtm_container_id'])
        // Other settings...
    );

    $result = $this->protection_service->update_settings($settings);

    if ($result) {
        wp_send_json_success('Settings updated successfully');
    } else {
        wp_send_json_error('Failed to update settings');
    }
}
    public function handle_save_download() {
        $this->verify_nonce();
        $this->verify_capability('manage_options');

        try {
            $download_data = [
                'title' => $this->sanitize_input($_POST['title']),
                'description' => $this->sanitize_input($_POST['description'], 'textarea'),
                'category_id' => $this->sanitize_input($_POST['category_id'], 'int'),
                'status' => $this->sanitize_input($_POST['status']),
                'protection_types' => isset($_POST['protection_types']) ? 
                                    $this->sanitize_input($_POST['protection_types'], 'array') : 
                                    []
            ];

            if (isset($_POST['download_id']) && !empty($_POST['download_id'])) {
                $download_id = $this->sanitize_input($_POST['download_id'], 'int');
                $result = $this->download_service->update_download(
                    $download_id,
                    $download_data,
                    $_FILES['download_file'] ?? null
                );
            } else {
                $result = $this->download_service->create_download(
                    $download_data,
                    $_FILES['download_file'] ?? null
                );
            }

            wp_send_json_success([
                'message' => __('Download saved successfully', 'wp-content-locker'),
                'download_id' => $result
            ]);

        } catch (Exception $e) {
            $this->log_error('Failed to save download', [
                'error' => $e->getMessage(),
                'data' => $download_data
            ]);

            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function handle_delete_download() {
        $this->verify_nonce();
        $this->verify_capability('manage_options');
        $this->validate_params(['download_id']);

        try {
            $download_id = $this->sanitize_input($_POST['download_id'], 'int');
            $this->download_service->delete_download($download_id);

            wp_send_json_success([
                'message' => __('Download deleted successfully', 'wp-content-locker')
            ]);

        } catch (Exception $e) {
            $this->log_error('Failed to delete download', [
                'error' => $e->getMessage(),
                'download_id' => $download_id
            ]);

            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function handle_bulk_action_downloads() {
        $this->verify_nonce();
        $this->verify_capability('manage_options');
        $this->validate_params(['action', 'download_ids']);

        try {
            $action = $this->sanitize_input($_POST['action']);
            $download_ids = $this->sanitize_input($_POST['download_ids'], 'array');

            switch ($action) {
                case 'delete':
                    foreach ($download_ids as $id) {
                        $this->download_service->delete_download($id);
                    }
                    $message = __('Downloads deleted successfully', 'wp-content-locker');
                    break;

                case 'activate':
                case 'deactivate':
                    $status = ($action === 'activate') ? 'active' : 'inactive';
                    foreach ($download_ids as $id) {
                        $this->download_service->update_download($id, ['status' => $status]);
                    }
                    $message = __('Downloads updated successfully', 'wp-content-locker');
                    break;

                default:
                    throw new Exception(__('Invalid bulk action', 'wp-content-locker'));
            }

            wp_send_json_success([
                'message' => $message
            ]);

        } catch (Exception $e) {
            $this->log_error('Failed to process bulk action', [
                'error' => $e->getMessage(),
                'action' => $action,
                'download_ids' => $download_ids
            ]);

            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function handle_update_protection() {
        $this->verify_nonce();
        $this->verify_capability('manage_options');
        $this->validate_params(['protection_id', 'settings']);

        try {
            $protection_id = $this->sanitize_input($_POST['protection_id'], 'int');
            $settings = json_decode(stripslashes($_POST['settings']), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(__('Invalid protection settings format', 'wp-content-locker'));
            }

            $this->protection_service->update_protection($protection_id, $settings);

            wp_send_json_success([
                'message' => __('Protection settings updated successfully', 'wp-content-locker')
            ]);

        } catch (Exception $e) {
            $this->log_error('Failed to update protection settings', [
                'error' => $e->getMessage(),
                'protection_id' => $protection_id,
                'settings' => $settings
            ]);

            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
	
	public function register_ajax_actions() {
    add_action('wp_ajax_wcl_add_category', array($this, 'handle_add_category'));
    add_action('wp_ajax_wcl_delete_category', array($this, 'handle_delete_category'));
    add_action('wp_ajax_wcl_generate_slug', array($this, 'handle_generate_slug'));
}

public function handle_add_category() {
    check_ajax_referer('wcl_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array(
            'error' => __('You do not have permission to perform this action.', 'wp-content-locker')
        ));
    }

    parse_str($_POST['formData'], $form_data);
    
    $category_data = array(
        'name' => sanitize_text_field($form_data['name']),
        'slug' => sanitize_title($form_data['slug']),
        'description' => sanitize_textarea_field($form_data['description']),
        'parent_id' => !empty($form_data['parent_id']) ? absint($form_data['parent_id']) : null
    );

    $result = $this->category_service->create_category($category_data);

    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error(array(
            'error' => __('Failed to create category.', 'wp-content-locker')
        ));
    }
}

public function handle_delete_category() {
    check_ajax_referer('wcl_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array(
            'error' => __('You do not have permission to perform this action.', 'wp-content-locker')
        ));
    }

    $category_id = absint($_POST['category_id']);
    
    if ($this->category_service->delete_category($category_id)) {
        wp_send_json_success();
    } else {
        wp_send_json_error(array(
            'error' => __('Failed to delete category.', 'wp-content-locker')
        ));
    }
}

public function handle_generate_slug() {
    check_ajax_referer('wcl_admin_nonce', 'nonce');

    $name = sanitize_text_field($_POST['name']);
    $slug = sanitize_title($name);

    wp_send_json_success(array('slug' => $slug));
}
}