<?php if (!defined('ABSPATH')) exit; ?>

<div class="wcl-download-button-wrapper">
    <?php if (isset($download) && isset($download_url)): ?>
        <a href="<?php echo esc_url($download_url); ?>" 
           class="wcl-download-button" 
           data-id="<?php echo esc_attr($download->id); ?>">
            <span class="wcl-button-icon dashicons dashicons-download"></span>
            <?php echo esc_html($download->title); ?>
        </a>
    <?php else: ?>
        <p class="wcl-error">Download information missing</p>
    <?php endif; ?>
</div>