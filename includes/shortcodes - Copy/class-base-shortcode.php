<?php
abstract class WCL_Base_Shortcode {
    protected $tag;
    protected $defaults = array();

    public function __construct() {
        add_shortcode($this->tag, array($this, 'render'));
    }

    protected function parse_attributes($atts) {
        return shortcode_atts($this->defaults, $atts, $this->tag);
    }

    protected function get_template($template_name, $args = array()) {
        $template_path = WCL_PLUGIN_DIR . 'public/templates/' . $template_name . '.php';
        
        if (!file_exists($template_path)) {
            return '';
        }

        ob_start();
        extract($args);
        include $template_path;
        return ob_get_clean();
    }

    protected function enqueue_assets() {
        // Override in child classes if needed
    }

    abstract public function render($atts, $content = null);
}