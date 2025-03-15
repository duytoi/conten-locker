<?php
/**
 * Plugin Name: WP Content Locker
 * Plugin URI: https://yourwebsite.com/wp-content-locker
 * Description: Advanced content protection and download management system
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}
# Thêm vào đầu file wp-content-locker.php
if (!defined('WP_CONTENT_LOCKER_REST_DEBUG')) {
    define('WP_CONTENT_LOCKER_REST_DEBUG', false);
}
// Thêm filter để debug REST API
add_filter('rest_pre_dispatch', function($result, $server, $request) {
    if (WP_CONTENT_LOCKER_REST_DEBUG) {
        wcl_debug_log('REST Request: ' . $request->get_route());
        wcl_debug_log('REST Method: ' . $request->get_method());
        wcl_debug_log('REST Params: ' . json_encode($request->get_params()));
    }
    return $result;
}, 10, 3);
// Debug Mode
if (!defined('WP_CONTENT_LOCKER_DEBUG')) {
    define('WP_CONTENT_LOCKER_DEBUG', true);
}

function wcl_debug_log($message) {
    if (WP_CONTENT_LOCKER_DEBUG) {
        error_log('WCL Debug: ' . $message);
    }
}

// Autoloader
spl_autoload_register(function ($class) {
    $base_dir = plugin_dir_path(__FILE__);
    $prefix = 'WP_Content_Locker\\';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $relative_path = str_replace('\\', '/', strtolower($relative_class));
    $class_name = basename($relative_path);
    $dir_path = dirname($relative_path);
    
    if (strpos($relative_class, 'Traits\\') !== false) {
        $file = $base_dir . 'includes/traits/' . str_replace('_', '-', strtolower($class_name)) . '-trait.php';
    } else {
        $file = $base_dir . $dir_path . '/class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
    }

    wcl_debug_log('Looking for file: ' . $file);

    if (file_exists($file)) {
        wcl_debug_log('Found and loading file: ' . $file);
        require_once $file;
        return true;
    }

    $alt_file = $base_dir . $dir_path . '/' . str_replace('_', '-', strtolower($class_name)) . '.php';
    if (file_exists($alt_file)) {
        wcl_debug_log('Found and loading alternative file: ' . $alt_file);
        require_once $alt_file;
        return true;
    }

    wcl_debug_log('File not found: ' . $file);
    return false;
});

// Define constants
define('WP_CONTENT_LOCKER_VERSION', '1.0.0');
define('WP_CONTENT_LOCKER_FILE', __FILE__);
define('WP_CONTENT_LOCKER_PATH', plugin_dir_path(__FILE__));
define('WP_CONTENT_LOCKER_URL', plugin_dir_url(__FILE__));
define('WP_CONTENT_LOCKER_ADMIN_PATH', WP_CONTENT_LOCKER_PATH . 'admin/');
define('WP_CONTENT_LOCKER_ADMIN_URL', WP_CONTENT_LOCKER_URL . 'admin/');
define('WP_CONTENT_LOCKER_INCLUDES_PATH', WP_CONTENT_LOCKER_PATH . 'includes/');
define('WP_CONTENT_LOCKER_PUBLIC_PATH', WP_CONTENT_LOCKER_PATH . 'public/');
define('WP_CONTENT_LOCKER_CACHE_DIR', WP_CONTENT_DIR . '/cache/wp-content-locker/');
define('WP_CONTENT_LOCKER_PUBLIC_URL', WP_CONTENT_LOCKER_URL . 'public/');

// Required files
require_once WP_CONTENT_LOCKER_INCLUDES_PATH . 'core/class-loader.php';
require_once WP_CONTENT_LOCKER_INCLUDES_PATH . 'core/class-i18n.php';
require_once WP_CONTENT_LOCKER_INCLUDES_PATH . 'core/class-activator.php';
require_once WP_CONTENT_LOCKER_INCLUDES_PATH . 'core/class-deactivator.php';
require_once WP_CONTENT_LOCKER_INCLUDES_PATH . 'api/class-api-controller.php';
require_once WP_CONTENT_LOCKER_INCLUDES_PATH . 'services/class-protection-service.php';
require_once WP_CONTENT_LOCKER_INCLUDES_PATH . 'services/class-tracking-service.php';
require_once WP_CONTENT_LOCKER_INCLUDES_PATH . 'services/class-download-service.php';
require_once WP_CONTENT_LOCKER_INCLUDES_PATH . 'shortcodes/class-base-shortcode.php';
require_once WP_CONTENT_LOCKER_INCLUDES_PATH . 'shortcodes/class-download-shortcode.php';
require_once WP_CONTENT_LOCKER_INCLUDES_PATH . 'shortcodes/class-countdown-shortcode.php';
require_once WP_CONTENT_LOCKER_PUBLIC_PATH . 'class-public.php';
require_once WP_CONTENT_LOCKER_ADMIN_PATH . 'class-admin.php';

use WP_Content_Locker\Core\Loader;
use WP_Content_Locker\Core\I18n;
use WP_Content_Locker\Core\Activator;
use WP_Content_Locker\Core\Deactivator;
use WP_Content_Locker\Admin\Admin;
use WP_Content_Locker\Includes\Services\Tracking_Service;
use WP_Content_Locker\Frontend\Public_Class;


class WP_Content_Locker {
    protected static $instance = null;
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $admin;
    protected $public;
    protected $tracking_service;
    protected $api_controller;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->plugin_name = 'wp-content-locker';
        $this->version = WP_CONTENT_LOCKER_VERSION;
        
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->init_components();
    }

	//Đăng ký API
	private function init_api() {
    try {
        // Đảm bảo API Controller được khởi tạo sau khi WordPress đã sẵn sàng
        add_action('rest_api_init', function() {
            if (!$this->api_controller) {
                $this->api_controller = new WP_Content_Locker\Includes\Api\API_Controller();
            }
            wcl_debug_log('REST API initialized on rest_api_init hook');
        });
    } catch (Exception $e) {
        wcl_debug_log('Error initializing API: ' . $e->getMessage());
    }
}

    private function load_dependencies() {
        try {
            $this->loader = new Loader();
            $this->tracking_service = new Tracking_Service();

            // Initialize API Controller early
			$this->api_controller = new WP_Content_Locker\Includes\Api\API_Controller();
			wcl_debug_log('API Controller initialized in load_dependencies');

            // Initialize Public Class
			$this->public = new Public_Class($this->tracking_service);
			wcl_debug_log('Public_Class initialized successfully');

			if (is_admin()) {
				$this->admin = new Admin($this->plugin_name, $this->version);
			}

			// Initialize API routes
			$this->init_api();

			} catch (Exception $e) {
				wcl_debug_log('Error in load_dependencies: ' . $e->getMessage());
			}
    }

    private function init_components() {
        try {
            new WP_Content_Locker\Includes\Shortcodes\Download_Shortcode();
            new WP_Content_Locker\Includes\Shortcodes\Countdown_Shortcode();
            wcl_debug_log('Components initialized successfully');
        } catch (Exception $e) {
            wcl_debug_log('Error initializing components: ' . $e->getMessage());
        }
    }

    private function set_locale() {
        $plugin_i18n = new I18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks() {
        if (is_admin()) {
            // Admin specific hooks
        }
    }

    private function define_public_hooks() {
        if ($this->public) {
            $this->loader->add_action('wp_head', $this->public, 'add_tracking_code', 1);
            $this->loader->add_action('wp_body_open', $this->public, 'add_gtm_body_tag', 1);
        }

        // Register REST API routes
        if ($this->api_controller) {
            $this->loader->add_action('rest_api_init', $this->api_controller, 'register_routes');
            wcl_debug_log('REST API routes registration hook added');
        }
    }

    public function run() {
        try {
            $this->loader->run();
            wcl_debug_log('WP Content Locker running successfully');
        } catch (Exception $e) {
            wcl_debug_log('Error running WP Content Locker: ' . $e->getMessage());
        }
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }

    public function get_loader() {
        return $this->loader;
    }
}

// Activation/Deactivation hooks
register_activation_hook(__FILE__, function() {
    Activator::activate();
    flush_rewrite_rules();
    wcl_debug_log('Plugin activated and rewrite rules flushed');
});

register_deactivation_hook(__FILE__, [Deactivator::class, 'deactivate']);

// Initialize plugin
function run_wp_content_locker() {
    try {
        wcl_debug_log('Initializing WP Content Locker...');
        $plugin = WP_Content_Locker::get_instance();
        $plugin->run();
        wcl_debug_log('WP Content Locker initialized successfully');
    } catch (Exception $e) {
        wcl_debug_log('Error initializing WP Content Locker: ' . $e->getMessage());
    }
}

// Run plugin on init with high priority
add_action('init', 'run_wp_content_locker', 1);

// Ensure proper permalink structure
add_action('init', function() {
    if (get_option('wcl_flush_rewrite_rules', false)) {
        flush_rewrite_rules();
        delete_option('wcl_flush_rewrite_rules');
        wcl_debug_log('Rewrite rules flushed on init');
    }
}, 20);

// Debug hook
add_action('wp_head', function() {
    wcl_debug_log('wp_head hook fired');
}, 0);

// Ensure wp-json prefix
add_filter('rest_url_prefix', function($prefix) {
    return 'wp-json';
});
add_action('wp_enqueue_scripts', function() {
    wp_localize_script('wp-content-locker', 'wclCountdown', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wcl_nonce'),
        'apiEndpoint' => rest_url('wp-content-locker/v1'),
        'ga4MeasurementId' => get_option('wcl_ga4_measurement_id', '')
    ));
});
// Thêm vào file wp-content-locker.php
add_action('init', function() {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
}, 1);
add_action('admin_init', function() {
    if (get_option('permalink_structure') === '') {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>WP Content Locker requires pretty permalinks to be enabled. Please go to Settings -> Permalinks and choose any option other than "Plain".</p></div>';
        });
    }
});