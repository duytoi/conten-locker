<?php
namespace WP_Content_Locker\Shortcodes;

class Protected_Download_Shortcode {
    private $download_service;
    private $password_manager;

    public function __construct() {
        $this->download_service = new \WP_Content_Locker\Services\Download_Service();
        $this->password_manager = new \WP_Content_Locker\Includes\Passwords\Password_Manager();
        
        add_shortcode('wcl_protected_download', [$this, 'render_shortcode']);
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'text' => __('Download', 'wp-content-locker'),
            'countdown' => 60, // Seconds
            'class' => '',
            'button_text' => __('Download Now', 'wp-content-locker')
        ], $atts);

        if (empty($atts['id'])) {
            return '';
        }

        // Get download info
        $download = $this->download_service->get_download($atts['id']);
        if (!$download || $download->status !== 'active') {
            return '';
        }

        // Generate unique container ID
        $container_id = 'wcl-download-' . $atts['id'] . '-' . uniqid();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>" 
             class="wcl-protected-download <?php echo esc_attr($atts['class']); ?>"
             data-download-id="<?php echo esc_attr($atts['id']); ?>">
            
            <!-- Step 1: Initial Download Button -->
            <div class="wcl-download-initial">
                <button class="wcl-start-download <?php echo esc_attr($atts['class']); ?>">
                    <?php echo esc_html($atts['text']); ?>
                </button>
            </div>

            <!-- Step 2: Countdown Timer -->
            <div class="wcl-countdown-container" style="display:none;">
                <div class="wcl-countdown" 
                     data-time="<?php echo esc_attr($atts['countdown']); ?>"
                     data-download-id="<?php echo esc_attr($atts['id']); ?>">
                    <div class="wcl-timer">
                        <span class="minutes">00</span>:<span class="seconds">00</span>
                    </div>
                    <div class="wcl-progress-bar"></div>
                </div>
                <div class="wcl-password-container" style="display:none;"></div>
            </div>

            <!-- Step 3: Password Form -->
            <div class="wcl-password-form-container" style="display:none;">
                <form class="wcl-password-form">
                    <div class="wcl-form-group">
                        <input type="password" 
                               name="password" 
                               class="wcl-password-input" 
                               placeholder="<?php esc_attr_e('Enter password', 'wp-content-locker'); ?>"
                               required>
                    </div>
                    <button type="submit" class="wcl-submit-password">
                        <?php echo esc_html($atts['button_text']); ?>
                    </button>
                </form>
                <div class="wcl-message"></div>
            </div>

            <!-- Step 4: Download Link -->
            <div class="wcl-download-link" style="display:none;">
                <a href="#" class="wcl-download-button" target="_blank">
                    <?php echo esc_html($atts['button_text']); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}