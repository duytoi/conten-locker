<?php
namespace WP_Content_Locker\Core;
class WCL_Asset_Manager {
    private $plugin_name;
    private $version;
    private $cache_dir;
    private $cache_url;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->cache_dir = WP_CONTENT_DIR . '/cache/wp-content-locker/';
        $this->cache_url = content_url('cache/wp-content-locker/');
        
        // Ensure cache directory exists
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }

        // Register hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_optimized_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_optimized_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function enqueue_optimized_styles() {
        // Define public styles
        $styles = array(
            'public-styles.css',
            'countdown.css'
        );
        
        $combined_css = $this->combine_files($styles, 'css');
        wp_enqueue_style(
            $this->plugin_name,
            $combined_css['url'],
            array(),
            $combined_css['version']
        );
    }

    public function enqueue_optimized_scripts() {
        // Define public scripts
        $scripts = array(
            'countdown.js',
            'protection.js',
            'download-handler.js'
        );
        
        $combined_js = $this->combine_files($scripts, 'js');
        wp_enqueue_script(
            $this->plugin_name,
            $combined_js['url'],
            array('jquery'),
            $combined_js['version'],
            true
        );

        // Localize script
        wp_localize_script(
            $this->plugin_name,
            'wclParams',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcl-nonce')
            )
        );
    }

    public function enqueue_admin_assets($hook) {
        // Admin specific assets
        if (strpos($hook, 'wp-content-locker') !== false) {
            $admin_styles = array(
                'admin-styles.css'
            );
            
            $admin_scripts = array(
                'admin-scripts.js',
                'category-manager.js'
            );

            $combined_admin_css = $this->combine_files($admin_styles, 'css', 'admin');
            $combined_admin_js = $this->combine_files($admin_scripts, 'js', 'admin');

            wp_enqueue_style(
                $this->plugin_name . '-admin',
                $combined_admin_css['url'],
                array(),
                $combined_admin_css['version']
            );

            wp_enqueue_script(
                $this->plugin_name . '-admin',
                $combined_admin_js['url'],
                array('jquery'),
                $combined_admin_js['version'],
                true
            );
        }
    }

    private function combine_files($files, $type, $context = 'public') {
        $hash = md5(serialize($files) . $this->version);
        $combined_filename = "combined-{$context}-{$type}-{$hash}.{$type}";
        $combined_path = $this->cache_dir . $combined_filename;

        // Check if cached file exists and is valid
        if (!file_exists($combined_path) || $this->is_development()) {
            $content = '';
            foreach ($files as $file) {
                $file_path = WP_CONTENT_LOCKER_PLUGIN_DIR . 
                    ($context === 'admin' ? 'admin/' : 'public/') . 
                    "{$type}/{$file}";
                
                if (file_exists($file_path)) {
                    $content .= file_get_contents($file_path) . "\n";
                }
            }

            // Minify content based on type
            $content = $type === 'js' ? 
                $this->minify_js($content) : 
                $this->minify_css($content);

            file_put_contents($combined_path, $content);
        }

        return array(
            'url' => $this->cache_url . $combined_filename,
            'version' => filemtime($combined_path)
        );
    }

    private function minify_js($content) {
        if ($this->is_development()) {
            return $content;
        }

        // Basic JS minification
        $content = preg_replace('/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', '', $content);
        $content = preg_replace('/\s*([{}|:;,])\s+/', '$1', $content);
        $content = preg_replace('/\s\s+/', ' ', $content);
        return trim($content);
    }

    private function minify_css($content) {
        if ($this->is_development()) {
            return $content;
        }

        // Basic CSS minification
        $content = preg_replace('/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', '', $content);
        $content = str_replace(array("\r\n", "\r", "\n", "\t"), '', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        return trim($content);
    }

    private function is_development() {
        return defined('WP_DEBUG') && WP_DEBUG === true;
    }

    public function clear_cache() {
        $files = glob($this->cache_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}