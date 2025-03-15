<?php
namespace WP_Content_Locker\Includes\Shortcodes;

use WP_Content_Locker\Includes\Services\Download_Service;
use WP_Content_Locker\Includes\Services\Protection_Service;
use WP_Content_Locker\Includes\Models\Download;

class Download_Shortcode extends Base_Shortcode {
    private $download_service;
    private $protection_service;
    private $password_manager;

    public function __construct() {
        parent::__construct();
        $this->download_service = new Download_Service();
        $this->protection_service = new Protection_Service();
        $this->password_manager = new \WP_Content_Locker\Includes\Passwords\Password_Manager();
        $this->register_shortcodes_and_actions();
    }

    private function register_shortcodes_and_actions() {
        error_log('Registering shortcodes and actions');
        
        // Original shortcodes
        add_shortcode('wcl_protected_download', array($this, 'render_shortcode'));
        add_shortcode('wcl_download', array($this, 'render_download'));
        
        // Countdown shortcode
        add_shortcode('wcl_countdown_download', array($this, 'render_countdown_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_wcl_verify_download_password', array($this, 'verify_password'));
        add_action('wp_ajax_nopriv_wcl_verify_download_password', array($this, 'verify_password'));
        add_action('wp_ajax_wcl_verify_countdown_password', array($this, 'verify_countdown_password'));
        add_action('wp_ajax_nopriv_wcl_verify_countdown_password', array($this, 'verify_countdown_password'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_countdown_assets'));
    }

    /**
     * Original render_shortcode for protected downloads
     */
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

        // Check if already unlocked
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

    /**
     * Enqueue countdown assets
     */
    public function enqueue_countdown_assets() {
        wp_enqueue_style(
            'wcl-countdown-styles',
            WP_CONTENT_LOCKER_PUBLIC_URL . 'css/countdown.css',
            [],
            WP_CONTENT_LOCKER_VERSION
        );

        wp_enqueue_script(
            'wcl-countdown',
            WP_CONTENT_LOCKER_PUBLIC_URL . 'js/countdown.js',
            ['jquery'],
            WP_CONTENT_LOCKER_VERSION,
            true
        );

        wp_localize_script('wcl-countdown', 'wcl_countdown_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcl-countdown-nonce'),
            'messages' => [
                'countdown_complete' => __('Countdown complete! Enter password to download', 'wcl'),
                'invalid_password' => __('Invalid password. Please try again.', 'wcl'),
                'download_ready' => __('Download ready!', 'wcl')
            ]
        ]);
    }

    /**
     * Original verify_password for protected downloads
     */
    public function verify_password() {
        check_ajax_referer('wcl-verify-password', 'nonce');

        $download_id = intval($_POST['download_id']);
        $password = sanitize_text_field($_POST['password']);
        
        $download = $this->get_download($download_id);
        
        if (!$download) {
            wp_send_json_error(['message' => __('Invalid download', 'wcl')]);
        }

        try {
            if (!$this->password_manager->verify_countdown_password($password)) {
                wp_send_json_error(['message' => __('Invalid password', 'wcl')]);
            }

            $this->protection_service->set_download_unlocked($download_id);

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

    /**
     * Get download object
     */
    protected function get_download($id) {
        return $this->download_service->get($id);
    }

    /**
     * Get download URL
     */
    private function get_download_url($download) {
        if (!empty($download->url)) {
            return esc_url($download->url);
        }

        return add_query_arg(array(
            'wcl_download' => $download->id,
            'nonce' => wp_create_nonce('wcl_download_' . $download->id)
        ), home_url());
    }

    /**
     * Render error message
     */
    private function render_error($message) {
        return sprintf('<p class="wcl-error">%s</p>', esc_html($message));
    }
}