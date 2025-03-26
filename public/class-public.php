<?php
namespace WP_Content_Locker\Frontend;
use WP_Content_Locker\Includes\Services\Tracking_Service;
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
	private $tracking_service;
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

         // Khởi tạo Tracking_Service
        $this->tracking_service = new Tracking_Service();
        
        // Debug log
        error_log('Public_Class tracking_service initialized');
        
        // Add tracking hooks
        add_action('wp_head', array($this, 'add_tracking_code'), 1);
        add_action('wp_body_open', array($this, 'add_gtm_body_tag'), 1);

        // Initialize other hooks
        $this->init();
    }

    /**
     * Initialize the public hooks
     */
    public function init() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
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
     * Register the stylesheets for the public-facing side of the site.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            WP_CONTENT_LOCKER_PUBLIC_URL . 'css/public-styles.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     */
    public function enqueue_scripts() {
		// Enqueue countdown.js
    wp_enqueue_script(
        'wcl-countdown',
        WP_CONTENT_LOCKER_PUBLIC_URL . 'js/countdown.js',
        array('jquery'),
        $this->version,
        true
    );

    // Correct localization for countdown script
    wp_localize_script('wcl-countdown', 'wclCountdown', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_rest'),
        'apiEndpoint' => esc_url_raw(rest_url('wp-content-locker/v2')),
        'siteUrl' => get_site_url(),
        'ga4Enabled' => get_option('wcl_ga4_enabled', false),
        'ga4MeasurementId' => get_option('wcl_ga4_measurement_id', ''),
        'gtmContainerId' => get_option('wcl_gtm_container_id', ''),
        'baseUrl' => parse_url(get_site_url(), PHP_URL_PATH) ?: '',
        'debug' => WP_CONTENT_LOCKER_DEBUG
    ));
        wp_enqueue_script(
            $this->plugin_name,
            WP_CONTENT_LOCKER_PUBLIC_URL . 'js/protected-download.js',
            array('jquery'),
            $this->version,
            true
        );
		wp_enqueue_script(
            $this->plugin_name,
            WP_CONTENT_LOCKER_PUBLIC_URL . 'js/countdown-init.js',
            array('jquery'),
            $this->version,
            true
        );
        wp_localize_script(
            $this->plugin_name,
            'wcl_ajax',
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
     * Add GA4 and GTM tracking code to frontend header
     */
	public function add_tracking_code() {
        try {
            if (!$this->tracking_service || !$this->tracking_service->is_tracking_enabled()) {
                error_log('Tracking not enabled or service not initialized');
                return;
            }

            $settings = $this->tracking_service->get_tracking_settings();
            error_log('Tracking settings loaded: ' . print_r($settings, true));

            // Add GTM
            if (!empty($settings['gtm_container_id'])) {
                ?>
                <!-- Google Tag Manager -->
                <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
                })(window,document,'script','dataLayer','<?php echo esc_js($settings['gtm_container_id']); ?>');</script>
                <!-- End Google Tag Manager -->
                <?php
            }

            // Add GA4
            if (!empty($settings['ga4_measurement_id'])) {
                ?>
                <!-- Google Analytics 4 -->
                <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_js($settings['ga4_measurement_id']); ?>"></script>
                <script>
                    window.dataLayer = window.dataLayer || [];
                    function gtag(){dataLayer.push(arguments);}
                    gtag('js', new Date());
                    gtag('config', '<?php echo esc_js($settings['ga4_measurement_id']); ?>');
                </script>
                <!-- End Google Analytics 4 -->
                <?php
            }
        } catch (\Exception $e) {
            error_log('Error in add_tracking_code: ' . $e->getMessage());
        }
    }

    public function add_gtm_body_tag() {
        try {
            if (!$this->tracking_service || !$this->tracking_service->is_tracking_enabled()) {
                return;
            }

            $settings = $this->tracking_service->get_tracking_settings();
            
            if (!empty($settings['gtm_container_id'])) {
                ?>
                <!-- Google Tag Manager (noscript) -->
                <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($settings['gtm_container_id']); ?>"
                height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
                <!-- End Google Tag Manager (noscript) -->
                <?php
            }
        } catch (\Exception $e) {
            error_log('Error in add_gtm_body_tag: ' . $e->getMessage());
        }
    }

    /**
     * Content lock shortcode handler
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

        $download_url = $this->get_download_url($file_id);

        wp_send_json_success(array(
            'download_url' => $download_url
        ));
    }

    /**
     * Filter content for automatic locking
     */
    public function maybe_lock_content($content) {
        if ($this->should_lock_content()) {
            return $this->get_locked_content_html($content);
        }
        return $content;
    }

    /**
     * Check if content should be locked
     */
    private function should_lock_content() {
        return false; // Implement your logic here
    }

    /**
     * Get locked content HTML
     */
    private function get_locked_content_html($content) {
        ob_start();
        include WP_CONTENT_LOCKER_TEMPLATES_PATH . 'locked-content.php';
        return ob_get_clean();
    }

    /**
     * Get unlocked content
     */
    private function get_unlocked_content($content_id) {
        return ''; // Implement your logic here
    }

    /**
     * Get download URL
     */
    private function get_download_url($file_id) {
        return ''; // Implement your logic here
    }
}
