<?php
namespace WP_Content_Locker\Includes\Ajax;
use WP_Content_Locker\Includes\Traits\Security_Trait;
use WP_Content_Locker\Includes\Services\Download_Service;
use Exception;

class Download_Ajax_Handler {
	use Security_Trait;
    private $download_service;

    public function __construct() {
        $this->download_service = new Download_Service();
        add_action('wp_ajax_wcl_delete_downloads', array($this, 'handle_delete_downloads'));
		 $this->register_ajax_actions();
    }
	
	 private function register_ajax_actions() {
        add_action('wp_ajax_wcl_save_download', [$this, 'handle_save_download']);
    }
	
	public function handle_save_download() {
        try {
            // Verify nonce and capabilities
            $this->verify_nonce('wcl_download_nonce');
            $this->verify_user_capability('manage_options');

            // Get and sanitize POST data
            $post_data = $this->get_sanitized_post_data();

            // Save download
            $download_id = $this->download_service->save_download($post_data, $_FILES);

            wp_send_json_success([
                'message' => $post_data['download_id'] ? 
                           __('Download updated successfully', 'wcl') : 
                           __('Download created successfully', 'wcl'),
                'download_id' => $download_id
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    private function get_sanitized_post_data() {
        return [
            'download_id' => isset($_POST['download_id']) ? absint($_POST['download_id']) : 0,
            'title' => isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '',
            'description' => isset($_POST['description']) ? wp_kses_post($_POST['description']) : '',
            'category_id' => isset($_POST['category_id']) ? absint($_POST['category_id']) : 0,
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active',
            'source_type' => isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : '',
            'url' => isset($_POST['url']) ? esc_url_raw($_POST['url']) : ''
        ];
    }
	
    public function handle_delete_downloads() {
        try {
            if (!check_ajax_referer('wcl_admin_nonce', 'nonce', false)) {
                throw new Exception(__('Security check failed', 'wp-content-locker'));
            }

            if (!current_user_can('manage_options')) {
                throw new Exception(__('Permission denied', 'wp-content-locker'));
            }

            $download_ids = isset($_POST['downloads']) ? (array) $_POST['downloads'] : array();
            
            if (empty($download_ids)) {
                throw new Exception(__('No items selected', 'wp-content-locker'));
            }

            $deleted = $this->download_service->delete_multiple($download_ids);

            wp_send_json_success(array(
                'message' => sprintf(
                    _n(
                        '%s item deleted successfully', 
                        '%s items deleted successfully', 
                        $deleted, 
                        'wp-content-locker'
                    ),
                    number_format_i18n($deleted)
                ),
                'deleted' => $deleted
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
}