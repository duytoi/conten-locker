<?php if (!defined('ABSPATH')) exit; ?>

<div class="wcl-download-wrapper" data-id="<?php echo esc_attr($download_id); ?>">
    <?php if ($is_protected && !$is_unlocked): ?>
        <div class="wcl-download-protection">
            <?php if ($protection_type === 'password'): ?>
                <form class="wcl-download-password-form" method="post">
                    <?php wp_nonce_field('wcl_unlock_download', 'wcl_nonce'); ?>
                    <input type="hidden" name="download_id" value="<?php echo esc_attr($download_id); ?>">
                    
                    <div class="wcl-form-group">
                        <label for="wcl-download-password-<?php echo esc_attr($download_id); ?>">
                            <?php _e('Enter Password to Download:', 'wp-content-locker'); ?>
                        </label>
                        <input type="password" 
                               id="wcl-download-password-<?php echo esc_attr($download_id); ?>"
                               name="wcl_password"
                               required>
                    </div>

                    <button type="submit" class="wcl-submit-btn">
                        <?php _e('Unlock Download', 'wp-content-locker'); ?>
                    </button>
                </form>

            <?php elseif ($protection_type === 'countdown'): ?>
                <div class="wcl-download-countdown" 
                     data-time="<?php echo esc_attr($countdown_time); ?>">
                    <div class="wcl-countdown-message">
                        <?php _e('Download will be available in:', 'wp-content-locker'); ?>
                    </div>
                    <div class="wcl-countdown-timer">
                        <span class="minutes">00</span>:<span class="seconds">00</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <a href="<?php echo esc_url($download_url); ?>" 
           class="wcl-download-button"
           data-filename="<?php echo esc_attr($filename); ?>"
           <?php echo $force_download ? 'download' : ''; ?>>
            <span class="wcl-button-icon">
                <svg viewBox="0 0 24 24" width="24" height="24">
                    <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                </svg>
            </span>
            <span class="wcl-button-text">
                <?php 
                if (!empty($custom_text)) {
                    echo esc_html($custom_text);
                } else {
                    _e('Download Now', 'wp-content-locker');
                }
                ?>
            </span>
            <?php if (!empty($file_size)): ?>
                <span class="wcl-file-size">(<?php echo esc_html($file_size); ?>)</span>
            <?php endif; ?>
        </a>
    <?php endif; ?>

    <div class="wcl-message" style="display: none;"></div>
</div>