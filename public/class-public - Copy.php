<?php
namespace WP_Content_Locker\Frontend;

/**
 * The public-facing functionality of the plugin.
 */
class Public_Class {

    /**
     * The ID of this plugin.
     *
     * @var string
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @var string
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        $this->plugin_name = 'wp-content-locker';
        $this->version = WP_CONTENT_LOCKER_VERSION;
        
        // Initialize hooks
        $this->init();
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            WP_CONTENT_LOCKER_PUBLIC_URL . 'css/public.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            WP_CONTENT_LOCKER_PUBLIC_URL . 'js/public.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name,
            'wpContentLocker',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_content_locker_nonce'),
                'messages' => array(
                    'loading' => __('Loading...', 'wp-content-locker'),
                    'error' => __('Error occurred. Please try again.', 'wp-content-locker'),
                    'success' => __('Content unlocked successfully!', 'wp-content-locker')
                )
            )
        );
    }

    /**
     * Initialize the public hooks
     */
    public function init() {
		//GA4 và GTM
		add_action('wp_head', array($this, 'add_tracking_code'));
        // Register shortcodes
        add_shortcode('content_lock', array($this, 'content_lock_shortcode'));
        add_shortcode('download_lock', array($this, 'download_lock_shortcode'));

        // Register AJAX handlers
        add_action('wp_ajax_unlock_content', array($this, 'handle_unlock_content'));
        add_action('wp_ajax_nopriv_unlock_content', array($this, 'handle_unlock_content'));
        
        add_action('wp_ajax_process_download', array($this, 'handle_download'));
        add_action('wp_ajax_nopriv_process_download', array($this, 'handle_download'));

        // Add content filters
        add_filter('the_content', array($this, 'maybe_lock_content'), 99);
    }

    /**
     * Content lock shortcode handler
     *
     * @param array $atts
     * @param string|null $content
     * @return string
     */
	 
    public function content_lock_shortcode($atts = array(), $content = null) {
        if (is_null($content)) {
            return '';
        }

        $defaults = array(
            'type' => 'default',
            'message' => __('This content is locked', 'wp-content-locker'),
            'requirement' => 'login'
        );

        $atts = shortcode_atts($defaults, $atts, 'content_lock');

        ob_start();
        include WP_CONTENT_LOCKER_TEMPLATES_PATH . 'content-lock.php';
        return ob_get_clean();
    }

    /**
     * Download lock shortcode handler
     *
     * @param array $atts
     * @return string
     */
    public function download_lock_shortcode($atts = array()) {
        $defaults = array(
            'file' => '',
            'title' => __('Download File', 'wp-content-locker'),
            'requirement' => 'login'
        );

        $atts = shortcode_atts($defaults, $atts, 'download_lock');

        if (empty($atts['file'])) {
            return '';
        }

        ob_start();
        include WP_CONTENT_LOCKER_TEMPLATES_PATH . 'download-lock.php';
        return ob_get_clean();
    }

    /**
     * Handle content unlock AJAX request
     */
    public function handle_unlock_content() {
        check_ajax_referer('wp_content_locker_nonce', 'nonce');

        $content_id = isset($_POST['content_id']) ? intval($_POST['content_id']) : 0;
        
        if (!$content_id) {
            wp_send_json_error(array(
                'message' => __('Invalid content ID', 'wp-content-locker')
            ));
        }

        // Add your unlock logic here
        $unlocked_content = $this->get_unlocked_content($content_id);

        wp_send_json_success(array(
            'content' => $unlocked_content
        ));
    }

    /**
     * Handle download request
     */
    public function handle_download() {
        check_ajax_referer('wp_content_locker_nonce', 'nonce');

        $file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;

        if (!$file_id) {
            wp_send_json_error(array(
                'message' => __('Invalid file ID', 'wp-content-locker')
            ));
        }

        // Add your download logic here
        $download_url = $this->get_download_url($file_id);

        wp_send_json_success(array(
            'download_url' => $download_url
        ));
    }

    /**
     * Filter content for automatic locking
     *
     * @param string $content
     * @return string
     */
    public function maybe_lock_content($content) {
        // Add your content filtering logic here
        if ($this->should_lock_content()) {
            return $this->get_locked_content_html($content);
        }

        return $content;
    }

    /**
     * Check if content should be locked
     *
     * @return boolean
     */
    private function should_lock_content() {
        // Add your logic here
        return false;
    }

    /**
     * Get locked content HTML
     *
     * @param string $content
     * @return string
     */
    private function get_locked_content_html($content) {
        ob_start();
        include WP_CONTENT_LOCKER_TEMPLATES_PATH . 'locked-content.php';
        return ob_get_clean();
    }

    /**
     * Get unlocked content
     *
     * @param int $content_id
     * @return string
     */
    private function get_unlocked_content($content_id) {
        // Add your content retrieval logic here
        return '';
    }

    /**
     * Get download URL
     *
     * @param int $file_id
     * @return string
     */
    private function get_download_url($file_id) {
        // Add your download URL generation logic here
        return '';
    }
}