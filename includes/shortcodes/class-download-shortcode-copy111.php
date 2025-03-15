<?php
namespace WP_Content_Locker\Includes\Shortcodes;

use WP_Content_Locker\Includes\Services\Download_Service;
use WP_Content_Locker\Includes\Services\Protection_Service;
use WP_Content_Locker\Includes\Models\Download;

class Download_Shortcode extends Base_Shortcode {
    private $download_service;
    private $protection_service;
	private $password_manager; // Thêm password manager
	
    public function __construct() {
        parent::__construct();
        $this->download_service = new Download_Service();
        $this->protection_service = new Protection_Service();
		$this->password_manager = new \WP_Content_Locker\Includes\Passwords\Password_Manager();
        $this->register_shortcodes_and_actions();
		// Add script enqueue
		//add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    private function register_shortcodes_and_actions() {
        error_log('Registering shortcodes and actions');
        
        add_shortcode('wcl_protected_download', array($this, 'render_shortcode'));
        add_shortcode('wcl_download', array($this, 'render_download'));
        
        add_action('wp_ajax_wcl_verify_download_password', array($this, 'verify_password'));
        add_action('wp_ajax_nopriv_wcl_verify_download_password', array($this, 'verify_password'));
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts(
            array('id' => 0), 
            $atts, 
            'wcl_protected_download'
        );

        $download_id = intval($atts['id']);
        $download = $this->get_download($download_id);

        if (!$download) {
            return $this->render_error(__('Download not found', 'wcl'));
        }

        // Kiểm tra download đã unlock chưa
        if (!$this->protection_service->is_download_unlocked($download_id)) {
            return $this->get_template('protected-download', array(
                'download' => $download,
                'protection_type' => 'password',
                'button_text' => __('Download Now', 'wcl'),
                'nonce' => wp_create_nonce('wcl-verify-password')
            ));
        }

        return $this->get_template('download-button', array(
            'download' => $download,
            'download_url' => $this->get_download_url($download)
        ));
    }

    protected function get_download($id) {
        return $this->download_service->get($id);
    }

    public function verify_password() {
        check_ajax_referer('wcl-verify-password', 'nonce');

        $download_id = intval($_POST['download_id']);
        $password = sanitize_text_field($_POST['password']);
        
        $download = $this->get_download($download_id);
        
        if (!$download) {
            wp_send_json_error(['message' => __('Invalid download', 'wcl')]);
        }

        // Sử dụng Password_Manager để verify
        try {
            if (!$this->password_manager->verify_countdown_password($password)) {
                wp_send_json_error(['message' => __('Invalid password', 'wcl')]);
            }

            // Set cookie để đánh dấu đã unlock
            $this->protection_service->set_download_unlocked($download_id);

            // Trả về button download
            wp_send_json_success([
                'html' => $this->get_template('download-button', array(
                    'download' => $download,
                    'download_url' => $this->get_download_url($download)
                ))
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    private function get_download_url($download) {
        if (!empty($download->url)) {
            return esc_url($download->url);
        }

        return add_query_arg(array(
            'wcl_download' => $download->id,
            'nonce' => wp_create_nonce('wcl_download_' . $download->id)
        ), home_url());
    }

    private function render_error($message) {
        return sprintf('<p class="wcl-error">%s</p>', esc_html($message));
    }
}