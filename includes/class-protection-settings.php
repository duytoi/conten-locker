<?php
namespace WP_Content_Locker\Includes;

class Protection_Settings {
    
    private $table_name;
    private $wpdb;
    private $default_settings;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'wcl_protections';
        
        $this->default_settings = array(
            'protection_mode' => 'single', // single or double
            'countdown_time_1' => 30,
            'countdown_time_2' => 30,
            'enable_password_encryption' => true,
            'enable_ga4_integration' => false,
            'ga4_measurement_id' => '',
            'enable_gtm_integration' => false,
            'gtm_container_id' => '',
            'messages' => array(
                'countdown_text' => 'Please wait %s seconds to reveal password',
                'password_placeholder' => 'Enter password to unlock content',
                'success_message' => 'Content unlocked successfully!',
                'error_message' => 'Invalid password, please try again.'
            )
        );

        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings() {
        register_setting(
            'wcl_protection_settings', 
            'wcl_protection_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => $this->default_settings
            )
        );

        add_settings_section(
            'wcl_protection_main',
            __('Protection Settings', 'wp-content-locker'),
            array($this, 'render_section_info'),
            'wcl_protection_settings'
        );

        // Protection Mode
        add_settings_field(
            'protection_mode',
            __('Protection Mode', 'wp-content-locker'),
            array($this, 'render_mode_field'),
            'wcl_protection_settings',
            'wcl_protection_main'
        );

        // Countdown Settings
        add_settings_field(
            'countdown_settings',
            __('Countdown Settings', 'wp-content-locker'),
            array($this, 'render_countdown_fields'),
            'wcl_protection_settings',
            'wcl_protection_main'
        );

        // Security Settings
        add_settings_field(
            'security_settings',
            __('Security Settings', 'wp-content-locker'),
            array($this, 'render_security_fields'),
            'wcl_protection_settings',
            'wcl_protection_main'
        );

        // Integration Settings
        add_settings_field(
            'integration_settings',
            __('Integration Settings', 'wp-content-locker'),
            array($this, 'render_integration_fields'),
            'wcl_protection_settings',
            'wcl_protection_main'
        );

        // Messages Settings
        add_settings_field(
            'message_settings',
            __('Message Settings', 'wp-content-locker'),
            array($this, 'render_message_fields'),
            'wcl_protection_settings',
            'wcl_protection_main'
        );
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Protection Mode
        $sanitized['protection_mode'] = sanitize_text_field($input['protection_mode']);
        
        // Countdown Times
        $sanitized['countdown_time_1'] = absint($input['countdown_time_1']);
        $sanitized['countdown_time_2'] = absint($input['countdown_time_2']);
        
        // Security Settings
        $sanitized['enable_password_encryption'] = isset($input['enable_password_encryption']);
        
        // Integration Settings
        $sanitized['enable_ga4_integration'] = isset($input['enable_ga4_integration']);
        $sanitized['ga4_measurement_id'] = sanitize_text_field($input['ga4_measurement_id']);
        $sanitized['enable_gtm_integration'] = isset($input['enable_gtm_integration']);
        $sanitized['gtm_container_id'] = sanitize_text_field($input['gtm_container_id']);
        
        // Messages
        $sanitized['messages'] = array(
            'countdown_text' => sanitize_text_field($input['messages']['countdown_text']),
            'password_placeholder' => sanitize_text_field($input['messages']['password_placeholder']),
            'success_message' => sanitize_text_field($input['messages']['success_message']),
            'error_message' => sanitize_text_field($input['messages']['error_message'])
        );

        return $sanitized;
    }

    public function get_settings() {
        return get_option('wcl_protection_settings', $this->default_settings);
    }

    public function update_settings($new_settings) {
        return update_option('wcl_protection_settings', $new_settings);
    }

    // Render methods for settings fields
    public function render_section_info() {
        echo '<p>' . __('Configure how content protection works on your site.', 'wp-content-locker') . '</p>';
    }

    public function render_mode_field() {
        $settings = $this->get_settings();
        ?>
        <select name="wcl_protection_settings[protection_mode]">
            <option value="single" <?php selected($settings['protection_mode'], 'single'); ?>>
                <?php _e('Single Password Protection', 'wp-content-locker'); ?>
            </option>
            <option value="double" <?php selected($settings['protection_mode'], 'double'); ?>>
                <?php _e('Double Password Protection', 'wp-content-locker'); ?>
            </option>
        </select>
        <p class="description">
            <?php _e('Choose between single or double password protection mode.', 'wp-content-locker'); ?>
        </p>
        <?php
    }

    public function render_countdown_fields() {
        $settings = $this->get_settings();
        ?>
        <p>
            <label>
                <?php _e('First Countdown Time (seconds):', 'wp-content-locker'); ?>
                <input type="number" 
                       name="wcl_protection_settings[countdown_time_1]" 
                       value="<?php echo esc_attr($settings['countdown_time_1']); ?>"
                       min="5" 
                       max="3600">
            </label>
        </p>
        <p>
            <label>
                <?php _e('Second Countdown Time (seconds):', 'wp-content-locker'); ?>
                <input type="number" 
                       name="wcl_protection_settings[countdown_time_2]" 
                       value="<?php echo esc_attr($settings['countdown_time_2']); ?>"
                       min="5" 
                       max="3600">
            </label>
        </p>
        <?php
    }

    // Continue with other render methods...
}