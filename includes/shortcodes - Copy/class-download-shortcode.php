<?php
class WCL_Download_Shortcode extends WCL_Base_Shortcode {
    protected $tag = 'wcl_download';
    protected $download_service;
    protected $protection_service;

    protected $defaults = array(
        'id' => 0,
        'style' => 'default',
        'text' => '',
        'show_size' => 'yes',
        'force_download' => 'yes',
        'class' => ''
    );

    public function __construct() {
        parent::__construct();
        $this->download_service = new WCL_Download_Service();
        $this->protection_service = new WCL_Protection_Service();
    }

    public function render($atts, $content = null) {
        $this->enqueue_assets();
        $attributes = $this->parse_attributes($atts);
        
        // Validate download ID
        if (empty($attributes['id'])) {
            return $this->render_error(__('Download ID is required', 'wp-content-locker'));
        }

        // Get download
        $download = $this->download_service->get_download($attributes['id']);
        if (!$download) {
            return $this->render_error(__('Download not found', 'wp-content-locker'));
        }

        // Check if download is active
        if ($download->status !== 'active') {
            return $this->render_error(__('This download is not currently available', 'wp-content-locker'));
        }

        // Get protection details if any
        $protection = $this->protection_service->get_download_protection($download->id);
        $is_protected = !empty($protection);
        $is_unlocked = $is_protected ? $this->protection_service->is_unlocked($protection->id) : true;

        // Prepare template variables
        $template_args = array(
            'download_id' => $download->id,
            'download_url' => $this->download_service->get_download_url($download->id),
            'filename' => $download->filename,
            'custom_text' => !empty($attributes['text']) ? $attributes['text'] : $download->title,
            'file_size' => $attributes['show_size'] === 'yes' ? $download->file_size : '',
            'force_download' => $attributes['force_download'] === 'yes',
            'custom_class' => $attributes['class'],
            'style' => $attributes['style'],
            'is_protected' => $is_protected,
            'is_unlocked' => $is_unlocked
        );

        if ($is_protected && !$is_unlocked) {
            $template_args = array_merge($template_args, array(
                'protection_type' => $protection->type,
                'protection_settings' => $protection->settings
            ));
        }

        return $this->get_template('download-button', $template_args);
    }

    protected function enqueue_assets() {
        wp_enqueue_style('wcl-public-styles');
        wp_enqueue_script('wcl-download-handler');
    }

    protected function render_error($message) {
        if (current_user_can('manage_options')) {
            return sprintf(
                '<div class="wcl-error">%s</div>',
                esc_html($message)
            );
        }
        return '';
    }
}