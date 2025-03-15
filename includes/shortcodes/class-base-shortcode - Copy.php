<?php
namespace WP_Content_Locker\Includes\Shortcodes;

abstract class Base_Shortcode {
		protected $plugin_name;
		protected $version;

    public function __construct() {
        $this->plugin_name = 'wp-content-locker';
        $this->$version;
		 add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
   

    public function enqueue_assets() {
        wp_enqueue_style(
            'wcl-public',
            WP_CONTENT_LOCKER_URL . 'public/css/public-styles.css',
            array(),
            WP_CONTENT_LOCKER_VERSION
        );

        wp_enqueue_script(
            'wcl-public',
            WP_CONTENT_LOCKER_URL . 'public/js/download-handler.js',
            array('jquery'),
            WP_CONTENT_LOCKER_VERSION,
            true
        );

        wp_localize_script('wcl-public', 'wclParams', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcl-public-nonce')
        ));
    }

    protected function get_template($template_name, $args = array()) {
        if ($args && is_array($args)) {
            extract($args);
        }

        $template = WP_CONTENT_LOCKER_PATH . 'public/templates/' . $template_name . '.php';

        if (!file_exists($template)) {
            return '';
        }

        ob_start();
        include $template;
        return ob_get_clean();
    }
}