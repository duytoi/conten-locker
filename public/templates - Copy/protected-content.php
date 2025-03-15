<?php if (!defined('ABSPATH')) exit; ?>

<div class="wcl-protected-content" data-id="<?php echo esc_attr($protection_id); ?>">
    <?php if (!empty($preview_content)): ?>
        <div class="wcl-preview-content">
            <?php echo wp_kses_post($preview_content); ?>
        </div>
    <?php endif; ?>

    <div class="wcl-protection-wrapper">
        <?php if ($protection_type === 'password'): ?>
            <div class="wcl-password-protection">
                <form class="wcl-password-form" method="post">
                    <?php wp_nonce_field('wcl_unlock_content', 'wcl_nonce'); ?>
                    <input type="hidden" name="protection_id" value="<?php echo esc_attr($protection_id); ?>">
                    
                    <div class="wcl-form-group">
                        <label for="wcl-password-<?php echo esc_attr($protection_id); ?>">
                            <?php _e('Enter Password to Unlock:', 'wp-content-locker'); ?>
                        </label>
                        <input type="password" 
                               id="wcl-password-<?php echo esc_attr($protection_id); ?>"
                               name="wcl_password"
                               required>
                    </div>

                    <?php if ($this->settings->get_setting('enable_captcha')): ?>
                        <div class="wcl-captcha-wrapper">
                            <?php $this->render_captcha(); ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="wcl-submit-btn">
                        <?php _e('Unlock Content', 'wp-content-locker'); ?>
                    </button>

                    <div class="wcl-message" style="display: none;"></div>
                </form>
            </div>

        <?php elseif ($protection_type === 'countdown'): ?>
            <div class="wcl-countdown-protection" 
                 data-time="<?php echo esc_attr($countdown_time); ?>"
                 data-reset="<?php echo esc_attr($reset_on_leave); ?>">
                <div class="wcl-countdown-message">
                    <?php _e('Content will unlock in:', 'wp-content-locker'); ?>
                </div>
                <div class="wcl-countdown-timer">
                    <span class="minutes">00</span>:<span class="seconds">00</span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="wcl-protected-content-inner" style="display: none;">
        <?php echo wp_kses_post($protected_content); ?>
    </div>
</div>