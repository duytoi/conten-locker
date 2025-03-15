<?php
class WCL_Protection_Shortcode extends WCL_Base_Shortcode {
    protected $tag = 'wcl_protect';
    protected $protection_service;

    protected $defaults = array(
        'type' => 'password',
        'password' => '',
        'countdown' => '60',
        'preview' => '',
        'reset' => 'no',
        'class' => ''
    );

    public function __construct() {
        parent::__construct();
        $this->protection_service = new WCL_Protection_Service();
    }

    public function render($atts, $content = null) {
        if (empty($content)) {
            return '';
        }

        $this->enqueue_assets();
        $attributes = $this->parse_attributes($atts);

        // Create or get protection
        $protection_data = array(
            'type' => $attributes['type'],
            'settings' => $this->get_protection_settings($attributes)
        );

        $protection = $this->protection_service->create_content_protection($protection_data);

        // Check if already unlocked
        if ($this->protection_service->is_unlocked($protection->id)) {
            return do_shortcode($content);
        }

        // Prepare template variables
        $template_args = array(
            'protection_id' => $protection->id,
            'protection_type' => $protection->type,
            'preview_content' => $attributes['preview'],
            'protected_content' => $content,
            'custom_class' => $attributes['class']
        );

        if ($protection->type === 'countdown') {
            $template_args['countdown_time'] = intval($attributes['countdown']);
            $template_args['reset_on_leave'] = $attributes['reset'] === 'yes';
        }

        return $this->get_template('protected-content', $template_args);
    }

    protected function get_protection_settings($attributes) {
        $settings = array();

        switch ($attributes['type']) {
            case 'password':
                if (empty($attributes['password'])) {
                    return new WP_Error('invalid_password', __('Password is required for password protection', 'wp-content-locker'));
                }
                $settings['password'] = $attributes['password'];
                break;

            case 'countdown':
                $countdown = intval($attributes['countdown']);
                if ($countdown < 1) {
                    return new WP_Error('invalid_countdown', __('Countdown must be at least 1 second', 'wp-content-locker'));
                }
                $settings['countdown'] = $countdown;
                $settings['reset_on_leave'] = $attributes['reset'] === 'yes';
                break;

            default:
                return new WP_Error('invalid_type', __('Invalid protection type', 'wp-content-locker'));
        }

        return $settings;
    }

    protected function enqueue_assets() {
        wp_enqueue_style('wcl-public-styles');
        wp_enqueue_script('wcl-protection');
        wp_enqueue_script('wcl-countdown');
    }
}