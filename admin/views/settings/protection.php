<?php
// admin/views/settings/protection.php

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Get current settings from database
global $wpdb;
$table_name = $wpdb->prefix . 'wcl_protections';
$settings = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 1", ARRAY_A);

// If no settings found, use defaults
if (!$settings) {
    $settings = array(
        'protection_type' => 'countdown',
        'countdown_mode' => 'single',
        'countdown_first' => 60,
        'countdown_second' => 60,
        'first_message' => __('Please wait for the countdown to complete', 'wcl'),
        'second_message' => __('Please complete the second countdown', 'wcl'),
        'redirect_message' => __('Click any link to continue', 'wcl'),
        'requires_ga' => 0,
        'password' => '',
        'countdown_time' => 60,
        'max_attempts' => 3,
        'block_duration' => 3600,
        'status' => 'active',
		'ga4_enabled' => 0,
        'ga4_measurement_id' => '',
        'gtm_container_id' => ''
    );

    // Insert default settings
    $wpdb->insert(
        $table_name,
        $settings,
        array('%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s')
    );
}

// Rest of your form HTML remains the same, just make sure all field names match database columns
?>

<div class="wrap">
    <h1><?php echo esc_html__('Protection Settings', 'wcl'); ?></h1>

    <?php settings_errors(); ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wcl-settings-form">
		<input type="hidden" name="action" value="wcl_save_protection_settings">
			<?php wp_nonce_field('wcl_protection_settings', 'wcl_protection_nonce'); ?>

        <table class="form-table">
            <!-- Protection Type -->
            <tr>
                <th scope="row"><?php echo esc_html__('Protection Type', 'wcl'); ?></th>
                <td>
                    <select name="protection_type" id="protection_type">
                        <option value="countdown" <?php selected($settings['protection_type'], 'countdown'); ?>>
                            <?php echo esc_html__('Countdown Timer', 'wcl'); ?>
                        </option>
                        <option value="password" <?php selected($settings['protection_type'], 'password'); ?>>
                            <?php echo esc_html__('Password Protection', 'wcl'); ?>
                        </option>
                        <option value="both" <?php selected($settings['protection_type'], 'both'); ?>>
                            <?php echo esc_html__('Both (Countdown + Password)', 'wcl'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php echo esc_html__('Choose how you want to protect your content', 'wcl'); ?>
                    </p>
                </td>
            </tr>

            <!-- Countdown Settings Section -->
            <tr class="countdown-settings <?php echo ($settings['protection_type'] === 'password') ? 'hidden' : ''; ?>">
                <th scope="row"><?php echo esc_html__('Countdown Mode', 'wcl'); ?></th>
                <td>
                    <select name="countdown_mode" id="countdown_mode">
                        <option value="single" <?php selected($settings['countdown_mode'], 'single'); ?>>
                            <?php echo esc_html__('Single Countdown', 'wcl'); ?>
                        </option>
                        <option value="double" <?php selected($settings['countdown_mode'], 'double'); ?>>
                            <?php echo esc_html__('Double Countdown', 'wcl'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php echo esc_html__('Choose between single or double countdown protection', 'wcl'); ?>
                    </p>
                </td>
            </tr>

            <!-- First Countdown -->
            <tr class="countdown-settings <?php echo ($settings['protection_type'] === 'password') ? 'hidden' : ''; ?>">
                <th scope="row"><?php echo esc_html__('First Countdown (seconds)', 'wcl'); ?></th>
                <td>
                    <input type="number" 
                           name="countdown_first" 
                           id="countdown_first"
                           value="<?php echo esc_attr($settings['countdown_first']); ?>" 
                           min="10" 
                           max="3600"
                           step="1">
                    <p class="description">
                        <?php echo esc_html__('Duration for the first countdown (10-3600 seconds)', 'wcl'); ?>
                    </p>
                </td>
            </tr>

            <!-- Second Countdown -->
            <tr class="countdown-settings double-countdown <?php echo ($settings['countdown_mode'] !== 'double' || $settings['protection_type'] === 'password') ? 'hidden' : ''; ?>">
                <th scope="row"><?php echo esc_html__('Second Countdown (seconds)', 'wcl'); ?></th>
                <td>
                    <input type="number" 
                           name="countdown_second" 
                           id="countdown_second"
                           value="<?php echo esc_attr($settings['countdown_second']); ?>" 
                           min="10" 
                           max="3600"
                           step="1">
                    <p class="description">
                        <?php echo esc_html__('Duration for the second countdown (10-3600 seconds)', 'wcl'); ?>
                    </p>
                </td>
            </tr>

            <!-- Messages Section -->
            <tr class="countdown-settings <?php echo ($settings['protection_type'] === 'password') ? 'hidden' : ''; ?>">
                <th scope="row"><?php echo esc_html__('First Message', 'wcl'); ?></th>
                <td>
                    <textarea name="first_message" 
                              id="first_message" 
                              rows="3" 
                              cols="50"><?php echo esc_textarea($settings['first_message']); ?></textarea>
                    <p class="description">
                        <?php echo esc_html__('Message shown during first countdown', 'wcl'); ?>
                    </p>
                </td>
            </tr>

            <tr class="countdown-settings double-countdown <?php echo ($settings['countdown_mode'] !== 'double' || $settings['protection_type'] === 'password') ? 'hidden' : ''; ?>">
                <th scope="row"><?php echo esc_html__('Second Message', 'wcl'); ?></th>
                <td>
                    <textarea name="second_message" 
                              id="second_message" 
                              rows="3" 
                              cols="50"><?php echo esc_textarea($settings['second_message']); ?></textarea>
                    <p class="description">
                        <?php echo esc_html__('Message shown during second countdown', 'wcl'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php echo esc_html__('Redirect Message', 'wcl'); ?></th>
                <td>
                    <textarea name="redirect_message" 
                              id="redirect_message" 
                              rows="3" 
                              cols="50"><?php echo esc_textarea($settings['redirect_message']); ?></textarea>
                    <p class="description">
                        <?php echo esc_html__('Message shown after protection requirements are met', 'wcl'); ?>
                    </p>
                </td>
            </tr>
			
            <!-- Traffic Source -->
            <tr>
                <th scope="row"><?php echo esc_html__('Traffic Source', 'wcl'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="requires_ga" 
                               value="1" 
                               <?php checked($settings['requires_ga'], 1); ?>>
                        <?php echo esc_html__('Only apply protection for traffic from Google', 'wcl'); ?>
                    </label>
                    <p class="description">
                        <?php echo esc_html__('If checked, protection will only be applied to visitors coming from Google', 'wcl'); ?>
                    </p>
                </td>
            </tr>
			
			<!-- Google Analytics Integration -->
<tr>
    <th scope="row"><?php echo esc_html__('Google Analytics Settings', 'wcl'); ?></th>
    <td>
        <fieldset>
            <label>
                <input type="checkbox" 
                       name="ga4_enabled" 
                       value="1" 
                       <?php checked($settings['ga4_enabled'], 1); ?>>
                <?php echo esc_html__('Enable GA4 Integration', 'wcl'); ?>
            </label>
            <br><br>
            
            <label><?php echo esc_html__('GA4 Measurement ID', 'wcl'); ?></label>
            <input type="text" 
                   name="ga4_measurement_id" 
                   value="<?php echo esc_attr($settings['ga4_measurement_id']); ?>"
                   placeholder="G-XXXXXXXXXX"
                   class="regular-text">
            <p class="description">
                <?php echo esc_html__('Enter your GA4 Measurement ID (format: G-XXXXXXXXXX)', 'wcl'); ?>
            </p>
            
            <br>
            <label><?php echo esc_html__('GTM Container ID', 'wcl'); ?></label>
            <input type="text" 
                   name="gtm_container_id" 
                   value="<?php echo esc_attr($settings['gtm_container_id']); ?>"
                   placeholder="GTM-XXXXXXX"
                   class="regular-text">
            <p class="description">
                <?php echo esc_html__('Enter your GTM Container ID (format: GTM-XXXXXXX)', 'wcl'); ?>
            </p>
        </fieldset>
    </td>
</tr>			
			
            <!-- Protection Status -->
            <tr>
                <th scope="row"><?php echo esc_html__('Protection Status', 'wcl'); ?></th>
                <td>
                    <select name="status" id="status">
                        <option value="active" <?php selected($settings['status'], 'active'); ?>>
                            <?php echo esc_html__('Active', 'wcl'); ?>
                        </option>
                        <option value="inactive" <?php selected($settings['status'], 'inactive'); ?>>
                            <?php echo esc_html__('Inactive', 'wcl'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php echo esc_html__('Enable or disable content protection globally', 'wcl'); ?>
                    </p>
                </td>
            </tr>
			<!-- Trong trang Settings -->
<tr class="password-settings">
    <th scope="row"><?php _e('Password Management', 'wcl'); ?></th>
    <td>
        <p>
            <?php 
            $available = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcl_passwords WHERE status = 'unused'");
            printf(__('Available passwords: %d', 'wcl'), $available); 
            ?>
        </p>
        
        <?php if ($available < 100): ?>
            <div class="notice notice-warning">
                <p><?php _e('Running low on available passwords.', 'wcl'); ?></p>
            </div>
        <?php endif; ?>
        
        <a href="<?php echo admin_url('admin.php?page=wcl-passwords'); ?>" class="button">
            <?php _e('Manage Passwords', 'wcl'); ?>
        </a>
    </td>
</tr>
        </table>

        <?php submit_button(__('Save Changes', 'wcl')); ?>
    </form>
	<?php
// Hiển thị thông báo lỗi/thành công
settings_errors('wcl_messages');
?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle protection type changes
    $('#protection_type').on('change', function() {
        var type = $(this).val();
        if (type === 'password') {
            $('.countdown-settings').addClass('hidden');
        } else {
            $('.countdown-settings').removeClass('hidden');
            $('#countdown_mode').trigger('change');
        }
    });

    // Handle countdown mode changes
    $('#countdown_mode').on('change', function() {
        var mode = $(this).val();
        if (mode === 'double') {
            $('.double-countdown').removeClass('hidden');
        } else {
            $('.double-countdown').addClass('hidden');
        }
    });

    // Form validation
    $('.wcl-settings-form').on('submit', function(e) {
        var protectionType = $('#protection_type').val();
        
        if (protectionType !== 'password') {
            var firstCount = parseInt($('#countdown_first').val());
            if (firstCount < 10 || firstCount > 3600) {
                alert('First countdown must be between 10 and 3600 seconds');
                e.preventDefault();
                return false;
            }

            if ($('#countdown_mode').val() === 'double') {
                var secondCount = parseInt($('#countdown_second').val());
                if (secondCount < 10 || secondCount > 3600) {
                    alert('Second countdown must be between 10 and 3600 seconds');
                    e.preventDefault();
                    return false;
                }
            }
        }
    });
});
</script>

<style type="text/css">
.hidden {
    display: none;
}
.form-table th {
    width: 250px;
}
.form-table td {
    padding: 15px 10px;
}
.form-table textarea {
    width: 100%;
    max-width: 500px;
}
.description {
    margin-top: 5px;
    color: #666;
}
/* Thêm vào phần style hiện có */
.wcl-preview-panel {
    margin: 20px 0;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.preview-container {
    margin-top: 15px;
}

.message-preview {
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.message-preview h4 {
    margin: 0 0 10px 0;
    color: #666;
}

.wcl-password-stats {
    margin-bottom: 15px;
}

.wcl-warning {
    color: #dc3545;
    padding: 10px;
    background: #fff3cd;
    border: 1px solid #ffeeba;
    border-radius: 4px;
    margin: 10px 0;
}

.wcl-password-options {
    margin: 15px 0;
}

.wcl-password-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.password-length {
    width: 120px;
}

.password-count {
    width: 80px;
}
</style>