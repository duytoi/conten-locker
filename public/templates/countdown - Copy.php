<div class="wcl-download-wrapper" id="wcl-download-<?php echo $download->id; ?>">
    <div class="wcl-download-locked">
        <h3><?php echo esc_html($download->title); ?></h3>
        
        <?php if (!empty($download->description)): ?>
            <div class="wcl-download-description">
                <?php echo wpautop($download->description); ?>
            </div>
        <?php endif; ?>

        <div class="wcl-countdown" data-time="<?php echo esc_attr($countdown_time); ?>">
            <span class="wcl-countdown-text">
                <?php 
                printf(
                    __('Please wait %s seconds', 'wcl'),
                    '<span class="wcl-countdown-value">' . $countdown_time . '</span>'
                );
                ?>
            </span>
        </div>

        <div class="wcl-password-form" style="display: none;">
            <input type="text" class="wcl-password-input" 
                   placeholder="<?php _e('Enter password', 'wcl'); ?>">
            <button class="wcl-submit-password" 
                    data-download-id="<?php echo $download->id; ?>">
                <?php _e('Unlock Download', 'wcl'); ?>
            </button>
            <div class="wcl-message"></div>
        </div>
    </div>
</div>