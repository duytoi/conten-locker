<div class="wcl-download-wrapper" data-download-id="<?php echo esc_attr($download->id); ?>">
    <?php 
    // Debug info
    if(WP_DEBUG) {
        echo '<!-- Debug: Download wrapper start -->';
    }
    ?>
    
    <!-- Initial download button -->
    <button type="button" class="wcl-initial-button">
        <span class="dashicons dashicons-download"></span>
        <?php echo esc_html($button_text); ?>
    </button>

    <!-- Password form (hidden by default) -->
    <div class="wcl-password-form-wrapper" style="display:none;">
        <?php 
        if(WP_DEBUG) {
            echo '<!-- Debug: Password form wrapper -->';
        }
        ?>
        <form class="wcl-password-form" method="post">
            <input type="hidden" name="action" value="wcl_verify_download_password">
            <input type="hidden" name="download_id" value="<?php echo esc_attr($download->id); ?>">
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
            
            <div class="wcl-form-group">
                <label for="wcl-password-<?php echo esc_attr($download->id); ?>">
                    <?php _e('Enter password to unlock:', 'wcl'); ?>
                </label>
                <input type="password" 
                       id="wcl-password-<?php echo esc_attr($download->id); ?>"
                       name="password" 
                       class="wcl-password-input"
                       required>
            </div>

            <div class="wcl-message"></div>

            <div class="wcl-form-actions">
                <button type="submit" class="wcl-submit-btn">
                    <?php _e('Verify & Download', 'wcl'); ?>
                </button>
                <button type="button" class="wcl-cancel-btn">
                    <?php _e('Cancel', 'wcl'); ?>
                </button>
            </div>
        </form>
    </div>
    
    <?php 
    if(WP_DEBUG) {
        echo '<!-- Debug: Download wrapper end -->';
    }
    ?>
</div>