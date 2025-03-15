<?php if (!defined('ABSPATH')) exit; ?>

<div class="wcl-countdown" 
     data-time="<?php echo esc_attr($time); ?>"
     data-reset="<?php echo esc_attr($reset_on_leave); ?>"
     data-complete-action="<?php echo esc_attr($complete_action); ?>"
     data-target="<?php echo esc_attr($target_id); ?>">
    
    <div class="wcl-countdown-display">
        <div class="wcl-countdown-segment">
            <span class="minutes">00</span>
            <label><?php _e('Minutes', 'wp-content-locker'); ?></label>
        </div>
        <div class="wcl-countdown-separator">:</div>
        <div class="wcl-countdown-segment">
            <span class="seconds">00</span>
            <label><?php _e('Seconds', 'wp-content-locker'); ?></label>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="wcl-countdown-message">
            <?php echo wp_kses_post($message); ?>
        </div>
    <?php endif; ?>

    <div class="wcl-countdown-progress">
        <div class="wcl-progress-bar" style="width: 0%"></div>
    </div>
</div>