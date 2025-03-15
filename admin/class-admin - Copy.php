<?php
namespace WP_Content_Locker\Admin;

use WP_Content_Locker\Includes\Passwords\Password_Manager;
use WP_Content_Locker\Includes\Passwords\Password_Handler;
use WP_Content_Locker\Includes\Passwords\Password_List_Table;
use WP_Content_Locker\Includes\Services\Protection_Service; // Thêm use statement này
use WP_Content_Locker\Includes\Services\Download_Service;

// Add necessary includes
require_once WP_CONTENT_LOCKER_PATH . 'includes/passwords/class-password-manager.php';
class Admin {
    /**
     * Plugin name
     * @var string
     */
    private $plugin_name;
	private $password_manager;
	private $protection_service; // Thêm property này
	private $download_service;
    /**
     * Plugin version
     * @var string
     */
    private $version;

    /**
     * Initialize the class
     * @param string $plugin_name
     * @param string $version
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->init_hooks();
        $this->register_password_hooks();
		$this->password_manager = new Password_Manager();//moi them
		// Khởi tạo Download Service trong constructor
        $this->download_service = new Download_Service();
        add_action('admin_init', array($this, 'handle_password_actions')); //moi them
		// Load Protection Service
    require_once WP_CONTENT_LOCKER_PATH . 'includes/services/class-protection-service.php';
    $this->protection_service = new Protection_Service();
	// Thêm action handler cho form submission
	add_action('admin_notices', array($this, 'display_admin_notices'));
    add_action('admin_post_wcl_save_protection_settings', array($this, 'handle_protection_settings_save'));
		// Thêm AJAX handlers cho categories
    add_action('wp_ajax_wcl_add_category', array($this, 'ajax_add_category'));
    add_action('wp_ajax_wcl_edit_category', array($this, 'ajax_edit_category'));
    add_action('wp_ajax_wcl_delete_category', array($this, 'ajax_delete_category'));
    add_action('wp_ajax_wcl_get_category', array($this, 'ajax_get_category'));
	 
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
		// Add AJAX handlers cho trang downloads
		add_action('wp_ajax_wcl_delete_downloads', array($this, 'ajax_delete_downloads'));
		// Thêm AJAX handler cho form submission
		add_action('wp_ajax_wcl_save_download', array($this, 'handle_save_download'));
		add_action('wp_ajax_nopriv_wcl_save_download', array($this, 'handle_save_download'));
		add_action('wp_ajax_wcl_password_bulk_action', array($this, 'handle_password_bulk_action'));
    }

    /**
     * Register admin menu items
     */
    public function add_admin_menu() {
        // Main Menu
        add_menu_page(
            __('Content Locker', 'wp-content-locker'),
            __('Content Locker', 'wp-content-locker'),
            'manage_options',
            'wp-content-locker',
            array($this, 'render_dashboard'),
            'dashicons-lock',
            30
        );

        // Add submenus
        $submenus = array(
            array(
                'parent' => 'wp-content-locker',
                'title' => __('Dashboard', 'wp-content-locker'),
                'menu' => __('Dashboard', 'wp-content-locker'),
                'capability' => 'manage_options',
                'slug' => 'wp-content-locker',
                'callback' => array($this, 'render_dashboard')
            ),
            array(
                'parent' => 'wp-content-locker',
                'title' => __('Downloads', 'wp-content-locker'),
                'menu' => __('Downloads', 'wp-content-locker'),
                'capability' => 'manage_options',
                'slug' => 'wcl-downloads',
                'callback' => array($this, 'render_downloads')
            ),
			array(
            'parent' => 'wp-content-locker',
            'title' => __('Add New Download', 'wp-content-locker'),
            'menu' => __('Add New', 'wp-content-locker'),
            'capability' => 'manage_options',
            'slug' => 'wcl-downloads&action=add', // Thêm action=add vào slug
            'callback' => array($this, 'render_downloads')
			),
            array(
                'parent' => 'wp-content-locker',
                'title' => __('Categories', 'wp-content-locker'),
                'menu' => __('Categories', 'wp-content-locker'),
                'capability' => 'manage_options',
                'slug' => 'wcl-categories',
                'callback' => array($this, 'render_categories')
            ),
            array(
                'parent' => 'wp-content-locker',
                'title' => __('Protection Settings', 'wp-content-locker'),
                'menu' => __('Protection Settings', 'wp-content-locker'),
                'capability' => 'manage_options',
                'slug' => 'wcl-protection-settings',
                'callback' => array($this, 'render_protection_settings')
            ),
            array(
                'parent' => 'wp-content-locker',
                'title' => __('Passwords', 'wp-content-locker'),
                'menu' => __('Passwords', 'wp-content-locker'),
                'capability' => 'manage_options',
                'slug' => 'wcl-passwords',
                'callback' => array($this, 'render_passwords')
            ),
            array(
                'parent' => 'wp-content-locker',
                'title' => __('Statistics', 'wp-content-locker'),
                'menu' => __('Statistics', 'wp-content-locker'),
                'capability' => 'manage_options',
                'slug' => 'wcl-statistics',
                'callback' => array($this, 'render_statistics')
            ),
            array(
                'parent' => 'wp-content-locker',
                'title' => __('Security Logs', 'wp-content-locker'),
                'menu' => __('Security Logs', 'wp-content-locker'),
                'capability' => 'manage_options',
                'slug' => 'wcl-security',
                'callback' => array($this, 'render_security')
            ),
            array(
                'parent' => 'wp-content-locker',
                'title' => __('Settings', 'wp-content-locker'),
                'menu' => __('Settings', 'wp-content-locker'),
                'capability' => 'manage_options',
                'slug' => 'wcl-settings',
                'callback' => array($this, 'render_settings')
            )
        );

        foreach ($submenus as $submenu) {
            add_submenu_page(
                $submenu['parent'],
                $submenu['title'],
                $submenu['menu'],
                $submenu['capability'],
                $submenu['slug'],
                $submenu['callback']
            );
        }
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // General Settings
        register_setting('wcl_general_settings', 'wcl_general_options', 
            array('sanitize_callback' => array($this, 'sanitize_general_settings'))
        );

        // Protection Settings
        register_setting('wcl_protection_settings', 'wcl_protection_options',
            array('sanitize_callback' => array($this, 'sanitize_protection_settings'))
        );

        // Advanced Settings
        register_setting('wcl_advanced_settings', 'wcl_advanced_options',
            array('sanitize_callback' => array($this, 'sanitize_advanced_settings'))
        );
    }

    /**
     * Render admin pages
     */
    public function render_dashboard() {
        require_once WP_CONTENT_LOCKER_ADMIN_PATH . 'views/dashboard/main-dashboard.php';
    }

    public function render_downloads() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    
    switch($action) {
        case 'add':
        case 'edit':
            // Get download data if editing
            $download = null;
            $is_edit = ($action === 'edit');
            
            if ($is_edit && isset($_GET['id'])) {
                $download_id = intval($_GET['id']);
                // Load download data
                global $wpdb;
                $download = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wcl_downloads WHERE id = %d",
                    $download_id
                ));
            }

            // Get categories for dropdown
            global $wpdb;
            $categories = $this->get_categories_hierarchical();
            
            // Include the form template
            require_once WP_CONTENT_LOCKER_ADMIN_PATH . 'views/downloads/add-edit.php';
            break;
            
        default:
            require_once WP_CONTENT_LOCKER_ADMIN_PATH . 'views/downloads/list.php';
            break;
    }
}
	public function handle_delete_downloads() {
    check_ajax_referer('wcl_admin_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'wp-content-locker')));
    }

    $ids = isset($_POST['ids']) ? array_map('intval', (array)$_POST['ids']) : array();

    if (empty($ids)) {
        wp_send_json_error(array('message' => __('No items selected.', 'wp-content-locker')));
    }

    try {
        require_once WP_CONTENT_LOCKER_PATH . '/includes/services/class-download-service.php';
        $download_service = new Download_Service();
        $count = $download_service->delete_multiple($ids);
        
        wp_send_json_success(array(
            'message' => sprintf(
                _n(
                    '%s item deleted successfully.',
                    '%s items deleted successfully.',
                    $count,
                    'wp-content-locker'
                ),
                number_format_i18n($count)
            )
        ));
    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}

    public function render_protection_settings() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'wp-content-locker'));
    }

    // Get protection settings
    $settings = $this->protection_service->get_settings();

    // Include protection settings view
    require_once WP_CONTENT_LOCKER_ADMIN_PATH . 'views/settings/protection.php';
}
	/**
 * Handle protection settings form submission
 */
	public function handle_protection_settings_save() {
    // Verify nonce
    if (!isset($_POST['wcl_protection_nonce']) || 
        !wp_verify_nonce($_POST['wcl_protection_nonce'], 'wcl_protection_settings')) {
        wp_die(__('Security check failed', 'wcl'));
    }

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions', 'wcl'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wcl_protections';

    // Debug: Print POST data
    error_log('POST Data: ' . print_r($_POST, true));

    // Sanitize and validate input data
    $data = array(
        'protection_type' => isset($_POST['protection_type']) ? sanitize_text_field($_POST['protection_type']) : '',
        'countdown_mode' => isset($_POST['countdown_mode']) ? sanitize_text_field($_POST['countdown_mode']) : 'single',
        'countdown_first' => isset($_POST['countdown_first']) ? intval($_POST['countdown_first']) : 60,
        'countdown_second' => isset($_POST['countdown_second']) ? intval($_POST['countdown_second']) : 60,
        'first_message' => isset($_POST['first_message']) ? sanitize_textarea_field($_POST['first_message']) : '',
        'second_message' => isset($_POST['second_message']) ? sanitize_textarea_field($_POST['second_message']) : '',
        'redirect_message' => isset($_POST['redirect_message']) ? sanitize_textarea_field($_POST['redirect_message']) : '',
        'requires_ga' => isset($_POST['requires_ga']) ? 1 : 0,
        'password' => isset($_POST['password']) ? sanitize_text_field($_POST['password']) : '',
        'countdown_time' => isset($_POST['countdown_time']) ? intval($_POST['countdown_time']) : 60,
        'max_attempts' => isset($_POST['max_attempts']) ? intval($_POST['max_attempts']) : 3,
        'block_duration' => isset($_POST['block_duration']) ? intval($_POST['block_duration']) : 3600,
        'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'inactive',
		 'ga4_enabled' => isset($_POST['ga4_enabled']) ? 1 : 0,
        'ga4_measurement_id' => sanitize_text_field($_POST['ga4_measurement_id']),
        'gtm_container_id' => sanitize_text_field($_POST['gtm_container_id']),
        'updated_at' => current_time('mysql')
    );

    // Debug: Print prepared data
    error_log('Prepared Data: ' . print_r($data, true));

    $where = array('id' => 1);
    
    // Update database
    $result = $wpdb->update(
        $table_name,
        $data,
        $where,
        array('%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s'),
        array('%d')
    );

    // Debug: Print query and result
    error_log('Last Query: ' . $wpdb->last_query);
    error_log('Update Result: ' . print_r($result, true));

    if ($result !== false) {
        // Store success message in transient
        set_transient('wcl_admin_message', array(
            'type' => 'updated',
            'message' => __('Settings saved successfully.', 'wcl')
        ), 30);
    } else {
        // Store error message in transient
        set_transient('wcl_admin_message', array(
            'type' => 'error',
            'message' => __('Error saving settings.', 'wcl')
        ), 30);
    }

    // Redirect back to settings page
    wp_redirect(add_query_arg(
        array(
            'page' => 'wcl-protection-settings',
            'settings-updated' => ($result !== false ? 'true' : 'false')
        ),
        admin_url('admin.php')
    ));
    exit;
}

// Thêm function để hiển thị thông báo
public function display_admin_notices() {
    $message = get_transient('wcl_admin_message');
    if ($message) {
        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($message['type']),
            esc_html($message['message'])
        );
        delete_transient('wcl_admin_message');
    }
}
/**
 * Helper function to redirect back to protection settings page
 */
private function redirect_back_to_settings() {
    wp_redirect(add_query_arg(
        array(
            'page' => 'wcl-protection-settings',
            'settings-updated' => 'true'
        ),
        admin_url('admin.php')
    ));
    exit;
}

	public function handle_password_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle Update Password
        if (isset($_POST['action']) && $_POST['action'] === 'update_password') {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'update_password')) {
                wp_die('Security check failed');
            }

            $password_id = isset($_POST['password_id']) ? intval($_POST['password_id']) : 0;
            $password_value = isset($_POST['password']) ? sanitize_text_field($_POST['password']) : '';
            $expires_at = isset($_POST['expires_at']) ? sanitize_text_field($_POST['expires_at']) : '';

            try {
                $this->password_manager->update_password($password_id, [
                    'password' => $password_value,
                    'expires_at' => $expires_at
                ]);

                wp_redirect(add_query_arg(
                    array(
                        'page' => 'wcl-passwords',
                        'message' => 'updated'
                    ),
                    admin_url('admin.php')
                ));
                exit;
            } catch (\Exception $e) {
                wp_die($e->getMessage());
            }
        }

        // Handle Delete Password
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['password_id'])) {
            $password_id = intval($_GET['password_id']);
            
            if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_password_' . $password_id)) {
                wp_die('Security check failed');
            }

            try {
                $this->password_manager->delete_password($password_id);
                wp_redirect(add_query_arg(
                    array(
                        'page' => 'wcl-passwords',
                        'message' => 'deleted'
                    ),
                    admin_url('admin.php')
                ));
                exit;
            } catch (\Exception $e) {
                wp_die($e->getMessage());
            }
        }
    }
	public function handle_password_bulk_action() {
    try {
        // Debug
        error_log('Received bulk action request: ' . print_r($_POST, true));

        // Verify nonces
        if (!check_ajax_referer('bulk-passwords', '_wpnonce', false)) {
            error_log('First nonce check failed');
        }
        
        if (!check_ajax_referer('wcl_ajax_nonce', 'wcl_nonce', false)) {
            error_log('Second nonce check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }

        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        $ids = isset($_POST['password_ids']) ? array_map('intval', $_POST['password_ids']) : array();

        error_log('Processing bulk action: ' . $action);
        error_log('Password IDs: ' . print_r($ids, true));

        if (empty($ids)) {
            wp_send_json_error(array('message' => 'No items selected'));
            return;
        }

        $password_manager = new \WP_Content_Locker\Includes\Passwords\Password_Manager();

        $result = false;
        switch ($action) {
            case 'delete':
                $result = $password_manager->delete_passwords($ids);
                break;
            case 'reset':
                $result = $password_manager->reset_password_status($ids);
                break;
        }

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Operation completed successfully',
                'ids' => $ids,
                'action' => $action
            ));
        } else {
            wp_send_json_error(array('message' => 'Operation failed'));
        }

    } catch (Exception $e) {
        error_log('Bulk action error: ' . $e->getMessage());
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}
    public function render_passwords() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-content-locker'));
        }

        // Show Edit Form
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['password_id'])) {
            $password_id = intval($_GET['password_id']);
            
            if (!wp_verify_nonce($_GET['_wpnonce'], 'edit_password_' . $password_id)) {
                wp_die('Security check failed');
            }

            $password = $this->password_manager->get_password($password_id);
            if (!$password) {
                wp_die(__('Password not found', 'wp-content-locker'));
            }

            require_once WP_CONTENT_LOCKER_ADMIN_PATH . 'views/passwords/edit.php';
            return;
        }

        // Show Messages
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $messages = array(
                'deleted' => __('Password deleted successfully.', 'wp-content-locker'),
                'updated' => __('Password updated successfully.', 'wp-content-locker'),
            );
            
            if (isset($messages[$message])) {
                add_settings_error(
                    'wcl_messages',
                    'wcl_message',
                    $messages[$message],
                    'updated'
                );
            }
        }

        // Show Password List
        require_once WP_CONTENT_LOCKER_PATH . 'includes/passwords/class-password-list-table.php';
        $passwords_list = new Password_List_Table();
        $passwords_list->prepare_items();
        require_once WP_CONTENT_LOCKER_ADMIN_PATH . 'views/passwords/manage.php';
    }
	
    public function render_statistics() {
        require_once WP_CONTENT_LOCKER_ADMIN_PATH . 'views/dashboard/statistics.php';
    }

    public function render_security() {
        require_once WP_CONTENT_LOCKER_ADMIN_PATH . 'views/security/logs.php';
    }

    public function render_settings() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        switch($tab) {
            case 'advanced':
                require_once WP_CONTENT_LOCKER_ADMIN_PATH . 'views/settings/advanced.php';
                break;
            case 'protection':
                require_once WP_CONTENT_LOCKER_ADMIN_PATH . 'views/settings/protection.php';
                break;
            default:
                require_once WP_CONTENT_LOCKER_ADMIN_PATH . 'views/settings/general.php';
                break;
        }
    }

    /**
     * Password management hooks and handlers
     */
    private function register_password_hooks() {
    // Handle form submission
    add_action('admin_post_wcl_generate_passwords', array($this, 'handle_generate_passwords'));
    
    // Display notices
    add_action('admin_notices', array($this, 'show_password_notices'));
    
    // Add AJAX handlers if needed
    add_action('wp_ajax_wcl_get_password_stats', array($this, 'get_password_stats'));
}

    public function handle_generate_passwords() {
        // Kiểm tra quyền
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-content-locker'));
        }

        try {
            // Verify nonce
            if (!isset($_POST['wcl_password_nonce']) || 
                !wp_verify_nonce($_POST['wcl_password_nonce'], 'wcl_generate_passwords')) {
                throw new \Exception(__('Security check failed', 'wp-content-locker'));
            }

            // Validate inputs
            $count = isset($_POST['password_count']) ? intval($_POST['password_count']) : 100;
            if ($count < 100 || $count > 2000) {
                throw new \Exception(__('Invalid password count', 'wp-content-locker'));
            }

            $length = isset($_POST['password_length']) ? intval($_POST['password_length']) : 12;
            if ($length < 8 || $length > 32) {
                throw new \Exception(__('Invalid password length', 'wp-content-locker'));
            }

            $expiry_time = isset($_POST['expiry_time']) ? intval($_POST['expiry_time']) : 24;
            if ($expiry_time < 1) {
                throw new \Exception(__('Invalid expiration time', 'wp-content-locker'));
            }

            $expiry_unit = isset($_POST['expiry_unit']) ? sanitize_text_field($_POST['expiry_unit']) : 'hours';
            $password_type = isset($_POST['password_type']) ? sanitize_text_field($_POST['password_type']) : 'alphanumeric';

            // Calculate expiration time in hours
            switch($expiry_unit) {
                case 'days':
                    $expiry_time *= 24;
                    break;
                case 'weeks':
                    $expiry_time *= 24 * 7;
                    break;
                case 'months':
                    $expiry_time *= 24 * 30;
                    break;
            }

            // Initialize Password Manager
            $password_manager = new Password_Manager();
            
            // Generate passwords
            $generated = $password_manager->generate_passwords($count, $length, $expiry_time, $password_type);

            // Redirect with success
            wp_redirect(add_query_arg([
                'page' => 'wcl-passwords',
                'generated' => 'true',
                'count' => $generated
            ], admin_url('admin.php')));
            exit;

        } catch (\Exception $e) {
            wp_redirect(add_query_arg([
                'page' => 'wcl-passwords',
                'error' => urlencode($e->getMessage())
            ], admin_url('admin.php')));
            exit;
        }
    }


    public function show_password_notices() {
        if (isset($_GET['generated']) && $_GET['generated'] === 'true') {
            $count = intval($_GET['count']);
            $message = sprintf(
                __('Successfully generated %d new passwords.', 'wp-content-locker'),
                $count
            );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }

        if (isset($_GET['error'])) {
            $error = sanitize_text_field(urldecode($_GET['error']));
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
        }
    }

    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_styles($hook) {
        if (strpos($hook, 'wp-content-locker') === false) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name . '-admin',
            WP_CONTENT_LOCKER_ADMIN_URL . 'css/admin-styles.css',
            array(),
            $this->version
        );
    }

    public function enqueue_scripts($hook) {
    // Enqueue scripts for all admin pages
    wp_enqueue_script('jquery');
    wp_enqueue_script(
        'wcl-admin-scripts',
        plugin_dir_url(__FILE__) . 'js/admin-scripts.js',
        array('jquery'),
        $this->version,
        true
    );

    // Thêm localize script để định nghĩa wcl_ajax
    wp_localize_script(
        'wcl-admin-scripts',
        'wcl_ajax',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcl_admin_nonce'),
            'messages' => array(
                'error' => __('An error occurred', 'wcl'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'wcl'),
                'confirm_bulk_delete' => __('Are you sure you want to delete these items?', 'wcl'),
                'no_items' => __('Please select items first', 'wcl'),
                'encryption_warning' => __('Enabling encryption may affect performance', 'wcl'),
                'success' => __('Operation completed successfully', 'wcl')
            )
        )
    );

    // Scripts specific to Downloads page
    if (strpos($hook, 'wcl-downloads') !== false) {
        wp_enqueue_script(
            'wcl-admin-downloads-form',
            plugin_dir_url(__FILE__) . 'js/admin-downloads-add-form.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script(
            'wcl-admin-downloads-form',
            'wcl_admin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcl_download_nonce'),
                'translations' => array(
                    'error' => __('An error occurred while saving', 'wcl'),
                    'title_required' => __('Title is required', 'wcl'),
                    'select_file' => __('Please select a file', 'wcl'),
                    'enter_url' => __('Please enter a valid URL', 'wcl'),
                    'file_too_large' => __('File size exceeds maximum limit', 'wcl'),
                    'success' => __('Download saved successfully', 'wcl')
                )
            )
        );
    }

    // Scripts specific to Categories page
    if (strpos($hook, 'wcl-categories') !== false) {
        wp_enqueue_script(
            'wcl-category-manager',
            plugin_dir_url(__FILE__) . 'js/category-manager.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script('wcl-category-manager', 'wcl_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcl_admin_nonce'),
            'i18n' => array(
                'addNewCategory' => __('Add New Category', 'wp-content-locker'),
                'editCategory' => __('Edit Category', 'wp-content-locker'),
                'cannotDeleteCategory' => __('Cannot delete category with existing downloads', 'wp-content-locker'),
                'confirmDelete' => __('Are you sure you want to delete this category?', 'wp-content-locker'),
                'savingChanges' => __('Saving changes...', 'wp-content-locker'),
                'changesSaved' => __('Changes saved successfully.', 'wp-content-locker'),
                'error' => __('An error occurred.', 'wp-content-locker')
            )
        ));
    }

    // Scripts specific to Settings page
    if (strpos($hook, 'wcl-settings') !== false) {
        wp_enqueue_script(
            'wcl-settings-manager',
            plugin_dir_url(__FILE__) . 'js/settings-manager.js',
            array('jquery'),
            $this->version,
            true
        );
    }

    // Add debug information if WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('WCL Admin Scripts loaded for hook: ' . $hook);
        error_log('Current page: ' . $_GET['page']);
    }
}

	public function ajax_delete_downloads() {
        // Verify nonce
        if (!check_ajax_referer('wcl_admin_nonce', '_ajax_nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed', 'wp-content-locker')
            ), 400);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Permission denied', 'wp-content-locker')
            ), 403);
        }

        // Get and validate IDs
        $ids = isset($_POST['ids']) ? array_map('intval', (array)$_POST['ids']) : array();
        
        if (empty($ids)) {
            wp_send_json_error(array(
                'message' => __('No items selected', 'wp-content-locker')
            ), 400);
        }

        try {
            $download_service = new Download_Service();
            $deleted = $download_service->delete_multiple($ids);

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
            ), 500);
        }
    }
	/**
 * Handle AJAX form submission for saving downloads
 */
	public function handle_save_download() {
    try {
        // Enable error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        // Debug logging
        error_log('=== Start handle_save_download ===');
        error_log('POST data: ' . print_r($_POST, true));
        error_log('FILES data: ' . print_r($_FILES, true));

        // Verify nonce first
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'wcl_download_nonce')) {
            throw new Exception('Security check failed');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            throw new Exception('Permission denied');
        }

        // Validate required fields
        if (empty($_POST['title'])) {
            throw new Exception('Title is required');
        }

        // Initialize Download Service if needed
        if (!isset($this->download_service)) {
            require_once WP_CONTENT_LOCKER_PATH . 'includes/services/class-download-service.php';
            $this->download_service = new Download_Service();
        }

        // Prepare basic download data
        $download_data = array(
            'title' => sanitize_text_field($_POST['title']),
            'description' => isset($_POST['description']) ? wp_kses_post($_POST['description']) : '',
            'category_id' => isset($_POST['category_id']) ? absint($_POST['category_id']) : 0,
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active'
        );

        error_log('Prepared download data: ' . print_r($download_data, true));

        // Handle file upload or URL based on source type
        $source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : 'file';
        
        if ($source_type === 'file') {
            if (!empty($_FILES['file_upload']['name'])) {
                error_log('Processing file upload...');
                
                // Basic file validation
                if ($_FILES['file_upload']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('File upload failed with error code: ' . $_FILES['file_upload']['error']);
                }

                // Handle file upload
                $uploaded_file = $this->handle_file_upload($_FILES['file_upload']);
                if (is_wp_error($uploaded_file)) {
                    throw new Exception($uploaded_file->get_error_message());
                }

                $download_data['file_path'] = $uploaded_file;
                $download_data['url'] = '';
                
                error_log('File uploaded successfully: ' . $uploaded_file);
            } elseif (empty($_POST['download_id'])) {
                // Only require file for new downloads
                throw new Exception('Please select a file to upload');
            }
        } else {
            if (empty($_POST['url'])) {
                throw new Exception('Please enter a valid URL');
            }
            $download_data['url'] = esc_url_raw($_POST['url']);
            $download_data['file_path'] = '';
        }

        // Save/Update download
        $download_id = isset($_POST['download_id']) ? absint($_POST['download_id']) : 0;
        
        error_log('Saving download... ID: ' . $download_id);
        
        if ($download_id > 0) {
            $result = $this->download_service->update($download_id, $download_data);
        } else {
            $result = $this->download_service->create($download_data);
        }

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        error_log('Download saved successfully. Result: ' . print_r($result, true));

        wp_send_json_success(array(
            'message' => 'Download saved successfully',
            'redirect' => admin_url('admin.php?page=wcl-downloads'),
            'download_id' => $result
        ));

    } catch (Exception $e) {
        error_log('Error in handle_save_download: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}

private function handle_file_upload($file) {
    try {
        error_log('Starting file upload handling...');
        
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $download_dir = $upload_dir['basedir'] . '/downloads';
        
        if (!file_exists($download_dir)) {
            wp_mkdir_p($download_dir);
        }

        // Setup upload overrides
        $upload_overrides = array(
            'test_form' => false,
            'test_type' => false,
            'mimes' => array(
                'pdf' => 'application/pdf',
                'zip' => 'application/zip',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            )
        );

        // Move uploaded file
        $movefile = wp_handle_upload($file, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            error_log('File upload successful: ' . print_r($movefile, true));
            return str_replace($upload_dir['basedir'] . '/', '', $movefile['file']);
        } else {
            error_log('File upload error: ' . print_r($movefile['error'], true));
            return new WP_Error('upload_error', $movefile['error']);
        }

    } catch (Exception $e) {
        error_log('Exception in handle_file_upload: ' . $e->getMessage());
        return new WP_Error('upload_error', $e->getMessage());
    }
}
    /**
     * Settings sanitization callbacks
     */
    public function sanitize_general_settings($input) {
        $sanitized = array();
        
        $sanitized['download_path'] = sanitize_text_field($input['download_path'] ?? 'downloads');
        
        $valid_methods = array('direct', 'xsendfile', 'redirect');
        $sanitized['download_method'] = in_array($input['download_method'], $valid_methods) 
            ? $input['download_method'] 
            : 'direct';
        
        if (isset($input['allowed_types']) && is_array($input['allowed_types'])) {
            $sanitized['allowed_types'] = array_map('sanitize_text_field', $input['allowed_types']);
        }
        
        $sanitized['enable_encryption'] = isset($input['enable_encryption']) ? 1 : 0;
        $sanitized['force_download'] = isset($input['force_download']) ? 1 : 0;
        $sanitized['verify_nonce'] = isset($input['verify_nonce']) ? 1 : 0;

        return $sanitized;
    }

    public function sanitize_protection_settings($input) {
        $sanitized = array();

        $sanitized['default_protection'] = sanitize_text_field($input['default_protection'] ?? 'none');
        $sanitized['apply_to_new'] = isset($input['apply_to_new']) ? 1 : 0;
        $sanitized['enable_password'] = isset($input['enable_password']) ? 1 : 0;
        
        if (isset($input['default_password'])) {
            $sanitized['default_password'] = sanitize_text_field($input['default_password']);
        }
        
        $sanitized['password_expires'] = isset($input['password_expires']) ? 1 : 0;
        
        if (isset($input['password_expiry_time'])) {
            $sanitized['password_expiry_time'] = absint($input['password_expiry_time']);
        }
        
        $sanitized['password_expiry_unit'] = sanitize_text_field($input['password_expiry_unit'] ?? 'hours');

        return $sanitized;
    }

    public function sanitize_advanced_settings($input) {
        $sanitized = array();
        
        $sanitized['enable_cache'] = isset($input['enable_cache']) ? 1 : 0;
        $sanitized['enable_logging'] = isset($input['enable_logging']) ? 1 : 0;
        
        if(isset($input['cache_expiry'])) {
            $sanitized['cache_expiry'] = absint($input['cache_expiry']);
        }
        
        return $sanitized;
    }
	//phan canh muc admin Categories
	/**
 * Xử lý AJAX cho categories
 */

/**
 * Render trang categories
 */
public function render_categories() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wcl_categories';
    
    // Lấy danh sách categories
    $categories = $wpdb->get_results("
        SELECT c.*, COUNT(d.id) as download_count 
        FROM {$table_name} c 
        LEFT JOIN {$wpdb->prefix}wcl_downloads d ON c.id = d.category_id 
        GROUP BY c.id 
        ORDER BY c.name ASC
    ");
    
    require_once WP_CONTENT_LOCKER_ADMIN_PATH . 'views/downloads/categories.php';
}

/**
 * Thêm category mới
 */
	/**
 * AJAX handler for adding new category
 */
	public function ajax_add_category() {
        check_ajax_referer('wcl_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $parent_id = intval($_POST['parent_id']);

        if (empty($name)) {
            wp_send_json_error(array('message' => 'Category name is required'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wcl_categories';

        // Tạo slug từ name
        $slug = sanitize_title($name);
        $original_slug = $slug;
        $counter = 1;

        // Kiểm tra và tạo slug unique
        while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE slug = %s", $slug))) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'parent_id' => $parent_id,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }

        wp_send_json_success(array(
            'message' => 'Category added successfully',
            'category_id' => $wpdb->insert_id
        ));
    }
/**
 * Cập nhật category
 */
	public function ajax_edit_category() {
        check_ajax_referer('wcl_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $id = intval($_POST['id']);
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $parent_id = intval($_POST['parent_id']);

        if (empty($id) || empty($name)) {
            wp_send_json_error(array('message' => 'Invalid data'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wcl_categories';

        // Tạo slug từ name
        $slug = sanitize_title($name);
        $original_slug = $slug;
        $counter = 1;

        // Kiểm tra và tạo slug unique (trừ category hiện tại)
        while ($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE slug = %s AND id != %d",
            $slug,
            $id
        ))) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        $result = $wpdb->update(
            $table_name,
            array(
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'parent_id' => $parent_id,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }

        wp_send_json_success(array('message' => 'Category updated successfully'));
    }

/**
 * Xóa category
 */

	public function ajax_delete_category() {
        check_ajax_referer('wcl_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (empty($id)) {
            wp_send_json_error(array('message' => 'Invalid category ID'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wcl_categories';

        // Kiểm tra xem category có downloads không
        $downloads_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wcl_downloads WHERE category_id = %d",
            $id
        ));

        if ($downloads_count > 0) {
            wp_send_json_error(array('message' => 'Cannot delete category with downloads'));
        }

        $result = $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }

        wp_send_json_success(array('message' => 'Category deleted successfully'));
    }

/**
 * Lấy thông tin category
 */
	public function ajax_get_category() {
        check_ajax_referer('wcl_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (empty($id)) {
            wp_send_json_error(array('message' => 'Invalid category ID'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wcl_categories';
        
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));

        if (!$category) {
            wp_send_json_error(array('message' => 'Category not found'));
        }

        wp_send_json_success($category);
    }
	
	private function get_categories_hierarchical() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wcl_categories';
    
    // Lấy tất cả categories
    $categories = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
    
    return $this->build_category_tree($categories);
}

// Chuyển function build_category_tree thành method của class
private function build_category_tree($categories, $parent_id = 0, $level = 0) {
    $tree = array();
    
    foreach ($categories as $category) {
        if ($category->parent_id == $parent_id) {
            $category->level = $level;
            $category->children = $this->build_category_tree($categories, $category->id, $level + 1);
            $tree[] = $category;
        }
    }
    
    return $tree;
}
	
	// Function để render category table
	public function render_categories_table($categories, $level = 0) {
    foreach ($categories as $category) {
        $indent = str_repeat('— ', $level);
        ?>
        <tr>
            <td class="category-name column-name">
                <?php echo esc_html($indent . $category->name); ?>
                <div class="row-actions">
                    <span class="edit">
                        <a href="#" class="edit-category" data-id="<?php echo esc_attr($category->id); ?>">
                            <?php _e('Edit', 'wp-content-locker'); ?>
                        </a> |
                    </span>
                    <span class="delete">
                        <a href="#" class="delete-category" data-id="<?php echo esc_attr($category->id); ?>">
                            <?php _e('Delete', 'wp-content-locker'); ?>
                        </a>
                    </span>
                </div>
            </td>
            <td class="description column-description">
                <?php echo esc_html($category->description); ?>
            </td>
            <td class="count column-count">
                <?php echo esc_html($category->count); ?>
            </td>
            <td class="date column-date">
                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($category->created_at))); ?>
            </td>
        </tr>
        <?php
        if (!empty($category->children)) {
            $this->render_categories_table($category->children, $level + 1);
        }
    }
	}
}