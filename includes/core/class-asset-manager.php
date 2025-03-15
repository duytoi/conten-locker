<?php
namespace WP_Content_Locker\Includes\Core;

class Asset_Manager {
    private $plugin_name;
    private $version;
    private $cache_dir;
    private $cache_url;

    /**
     * Constructor
     */
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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets() {
        // Base CSS files
        $styles = array(
            'public-styles.css',
            'countdown.css',
            'protected-download.css',
            'password-verification.css' // New password verification styles
        );
        
        // Base JS files
        $scripts = array(
            'countdown.js',
            'protection.js',
            'download-handler.js',
            'password-verification.js' // New password verification script
        );

        // Combine and enqueue CSS
        $combined_css = $this->combine_files($styles, 'css');
        wp_enqueue_style(
            $this->plugin_name,
            $combined_css['url'],
            array(),
            $combined_css['version']
        );

        // Combine and enqueue JS
        $combined_js = $this->combine_files($scripts, 'js');
        wp_enqueue_script(
            $this->plugin_name,
            $combined_js['url'],
            array('jquery'),
            $combined_js['version'],
            true
        );

        // Localize script with required data
        wp_localize_script(
            $this->plugin_name,
            'wcl_vars',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcl-nonce'),
                'password_nonce' => wp_create_nonce('wcl_verify_password'),
                'messages' => array(
                    'password_required' => __('Please enter a password.', 'wp-content-locker'),
                    'verifying' => __('Verifying...', 'wp-content-locker'),
                    'error' => __('An error occurred. Please try again.', 'wp-content-locker')
                )
            )
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'wp-content-locker') !== false) {
            // Admin CSS files
            $admin_styles = array(
                'admin-styles.css',
                'admin-downloads.css'
            );
            
            // Admin JS files
            $admin_scripts = array(
                'admin-scripts.js',
                'admin-downloads.js',
                'category-manager.js',
                'admin-downloads-add-form.js'
            );

            // Combine and enqueue admin CSS
            $combined_admin_css = $this->combine_files($admin_styles, 'css', 'admin');
            wp_enqueue_style(
                $this->plugin_name . '-admin',
                $combined_admin_css['url'],
                array(),
                $combined_admin_css['version']
            );

            // Combine and enqueue admin JS
            $combined_admin_js = $this->combine_files($admin_scripts, 'js', 'admin');
            wp_enqueue_script(
                $this->plugin_name . '-admin',
                $combined_admin_js['url'],
                array('jquery'),
                $combined_admin_js['version'],
                true
            );

            // Localize admin script
            wp_localize_script(
                $this->plugin_name . '-admin',
                'wcl_admin',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wcl-admin-nonce'),
                    'messages' => array(
                        'confirm_delete' => __('Are you sure you want to delete this item?', 'wp-content-locker'),
                        'changes_saved' => __('Changes saved successfully.', 'wp-content-locker'),
                        'error' => __('An error occurred.', 'wp-content-locker')
                    )
                )
            );
        }
    }

    /**
     * Combine and optimize files
     */
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
                    $file_content = file_get_contents($file_path);
                    
                    // Process file content
                    if ($type === 'css') {
                        $file_content = $this->process_css($file_content, dirname($file_path));
                    }
                    
                    $content .= $file_content . "\n";
                }
            }

            // Minify content
            $content = $type === 'js' ? 
                $this->minify_js($content) : 
                $this->minify_css($content);

            // Save combined file
            file_put_contents($combined_path, $content);
        }

        return array(
            'url' => $this->cache_url . $combined_filename,
            'version' => filemtime($combined_path)
        );
    }

    /**
     * Process CSS content
     */
    private function process_css($content, $base_path) {
        // Fix relative paths in url()
        return preg_replace_callback(
            '/url\s*\(\s*[\'"]?(?![data:]|[\'"]?(?:https?:)?\/\/)\/?(.+?)[\'"]?\s*\)/i',
            function($matches) use ($base_path) {
                $url = $matches[1];
                $absolute_url = content_url(str_replace(WP_CONTENT_DIR, '', $base_path) . '/' . $url);
                return "url('{$absolute_url}')";
            },
            $content
        );
    }

    /**
     * Minify JavaScript
     */
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

    /**
     * Minify CSS
     */
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

    /**
     * Check if in development mode
     */
    private function is_development() {
        return defined('WP_DEBUG') && WP_DEBUG === true;
    }

    /**
     * Clear asset cache
     */
    public function clear_cache() {
        $files = glob($this->cache_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}