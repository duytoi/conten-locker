<?php
namespace WP_Content_Locker\Includes\Shortcodes;

abstract class Base_Shortcode {
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->plugin_name = 'wp-content-locker';
        $this->version = WP_CONTENT_LOCKER_VERSION; // Sửa lại dòng này
    }

    public function enqueue_assets() {
        // Debug log
        error_log('Enqueuing assets from Base_Shortcode');
        
        wp_enqueue_style(
            'wcl-public',
            WP_CONTENT_LOCKER_URL . 'public/css/public-styles.css',
            array(),
            $this->version
        );

        wp_enqueue_script(
            'wcl-public',
            WP_CONTENT_LOCKER_URL . 'public/js/download-handler.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script('wcl-public', 'wclParams', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcl-public-nonce')
        ));
    }

    protected function get_template($template_name, $args = array()) {
        // Debug log
        error_log('Loading template: ' . $template_name);
        error_log('Template args: ' . print_r($args, true));

        if ($args && is_array($args)) {
            extract($args);
        }

        $template = WP_CONTENT_LOCKER_PATH . 'public/templates/' . $template_name . '.php';

        // Check template exists
        if (!file_exists($template)) {
            error_log('Template not found: ' . $template);
            return '';
        }

        ob_start();
        include $template;
        return ob_get_clean();
    }
}