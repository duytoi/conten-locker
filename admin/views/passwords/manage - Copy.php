<?php
if (!defined('ABSPATH')) exit;
use WP_Content_Locker\Includes\Passwords\Password_List_Table;

// Required files
require_once WP_CONTENT_LOCKER_PATH . 'includes/passwords/class-password-manager.php';
require_once WP_CONTENT_LOCKER_PATH . 'includes/passwords/class-password-list-table.php';
// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'wp-content-locker'));
}

try {
    // Initialize Password Manager and List Table
    $password_manager = new \WP_Content_Locker\Includes\Passwords\Password_Manager();
    $passwords_list = new \WP_Content_Locker\Includes\Passwords\Password_List_Table();
    
    // Process bulk actions if any
    if (isset($_POST['action']) && isset($_POST['passwords'])) {
        $action = sanitize_text_field($_POST['action']);
        $ids = array_map('intval', $_POST['passwords']);
        
        switch($action) {
            case 'delete':
                $password_manager->delete_passwords($ids);
                break;
            case 'reset':
                $password_manager->reset_password_status($ids);
                break;
        }
    }
    
    // Prepare items for display
    $passwords_list->prepare_items();
    
    // Get statistics
    $stats = $password_manager->get_password_statistics();
    
} catch (Exception $e) {
    add_settings_error(
        'wcl_password_messages',
        'wcl_error',
        $e->getMessage(),
        'error'
    );
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Password Management', 'wp-content-locker'); ?></h1>
    
    <?php if (!isset($_GET['action']) || $_GET['action'] !== 'generate'): ?>
        <a href="<?php echo esc_url(add_query_arg('action', 'generate')); ?>" class="page-title-action">
            <?php _e('Generate Passwords', 'wp-content-locker'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('action', 'export')); ?>" class="page-title-action">
            <?php _e('Export Passwords', 'wp-content-locker'); ?>
        </a>
    <?php endif; ?>

    <hr class="wp-header-end">

    <?php 
    // Show admin notices
    settings_errors('wcl_password_messages'); 
    ?>

    <!-- Statistics Cards -->
    <?php if (!isset($_GET['action']) || $_GET['action'] === 'list'): ?>
		<div class="wcl-stats-cards">
    <div class="wcl-stat-card">
        <h3><?php _e('Total Passwords', 'wp-content-locker'); ?></h3>
        <span class="stat-number"><?php echo isset($stats['total']) ? esc_html($stats['total']) : '0'; ?></span>
    </div>
    <div class="wcl-stat-card">
        <h3><?php _e('Unused', 'wp-content-locker'); ?></h3>
        <span class="stat-number"><?php echo isset($stats['unused']) ? esc_html($stats['unused']) : '0'; ?></span>
    </div>
    <div class="wcl-stat-card">
        <h3><?php _e('Used', 'wp-content-locker'); ?></h3>
        <span class="stat-number"><?php echo isset($stats['used']) ? esc_html($stats['used']) : '0'; ?></span>
    </div>
    <div class="wcl-stat-card">
        <h3><?php _e('Expired', 'wp-content-locker'); ?></h3>
        <span class="stat-number"><?php echo isset($stats['expired']) ? esc_html($stats['expired']) : '0'; ?></span>
    </div>
</div>
    <?php endif; ?>

    <?php if (isset($_GET['action']) && $_GET['action'] === 'generate'): ?>
        <div class="card">
            <h2><?php _e('Generate New Passwords', 'wp-content-locker'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="generate-passwords-form">
    <?php wp_nonce_field('wcl_generate_passwords', 'wcl_password_nonce'); ?>
    <input type="hidden" name="action" value="wcl_generate_passwords">
    
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">
                <label for="password_count"><?php _e('Number of Passwords', 'wp-content-locker'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="password_count" 
                       name="password_count" 
                       min="100" 
                       max="2000" 
                       value="100" 
                       class="small-text"
                       required>
                <p class="description">
                    <?php _e('Generate between 100 and 2000 passwords', 'wp-content-locker'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="password_length"><?php _e('Password Length', 'wp-content-locker'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="password_length" 
                       name="password_length" 
                       min="8" 
                       max="32" 
                       value="12" 
                       class="small-text"
                       required>
                <p class="description">
                    <?php _e('Password length between 8 and 32 characters', 'wp-content-locker'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="expiry_time"><?php _e('Expiration', 'wp-content-locker'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="expiry_time" 
                       name="expiry_time" 
                       min="1" 
                       value="24" 
                       class="small-text"
                       required>
                <select name="expiry_unit" id="expiry_unit">
                    <option value="hours"><?php _e('Hours', 'wp-content-locker'); ?></option>
                    <option value="days"><?php _e('Days', 'wp-content-locker'); ?></option>
                    <option value="weeks"><?php _e('Weeks', 'wp-content-locker'); ?></option>
                    <option value="months"><?php _e('Months', 'wp-content-locker'); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="password_type"><?php _e('Password Type', 'wp-content-locker'); ?></label>
            </th>
            <td>
                <select name="password_type" id="password_type">
                    <option value="alphanumeric"><?php _e('Alphanumeric', 'wp-content-locker'); ?></option>
                    <option value="numeric"><?php _e('Numeric Only', 'wp-content-locker'); ?></option>
                    <option value="special"><?php _e('Include Special Characters', 'wp-content-locker'); ?></option>
                </select>
            </td>
        </tr>
    </table>

    <?php submit_button(__('Generate Passwords', 'wp-content-locker')); ?>
</form>
        </div>
    <?php else: ?>
        <!-- Password List Table -->
        <form method="post">
		 <?php wp_nonce_field('bulk-passwords','bulk_action_nonce'); ?>
            <div class="tablenav top">
                <?php $passwords_list->search_box(__('Search Passwords', 'wp-content-locker'), 'password_search'); ?>
            </div>
            
            <?php $passwords_list->display(); ?>
        </form>
    <?php endif; ?>
</div>

<style type="text/css">
.wcl-stats-cards {
    display: flex;
    gap: 20px;
    margin: 20px 0;
}

.wcl-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 15px;
    border-radius: 4px;
    flex: 1;
    text-align: center;
}

.wcl-stat-card h3 {
    margin: 0 0 10px 0;
    color: #23282d;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}
#wcl-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wcl-loading-content {
    background: white;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    text-align: center;
}

.wcl-loading-content .spinner {
    float: none;
    margin: 0 auto 10px;
    visibility: visible;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    const $form = $('#generate-passwords-form');
    const $submitButton = $form.find('input[type="submit"]');
    
    $form.on('submit', function(e) {
        // Prevent double submission
        if ($submitButton.prop('disabled')) {
            e.preventDefault();
            return;
        }
        
        // Basic validation
        const count = parseInt($('#password_count').val());
        const length = parseInt($('#password_length').val());
        const expiryTime = parseInt($('#expiry_time').val());
        
        if (count < 100 || count > 2000) {
            alert('<?php _e("Please enter a valid number of passwords (100-2000)", "wp-content-locker"); ?>');
            e.preventDefault();
            return;
        }
        
        if (length < 8 || length > 32) {
            alert('<?php _e("Please enter a valid password length (8-32)", "wp-content-locker"); ?>');
            e.preventDefault();
            return;
        }
        
        if (expiryTime < 1) {
            alert('<?php _e("Please enter a valid expiration time", "wp-content-locker"); ?>');
            e.preventDefault();
            return;
        }

        // Show loading overlay
        const $overlay = $('<div id="wcl-loading-overlay">' +
            '<div class="wcl-loading-content">' +
            '<span class="spinner is-active"></span>' +
            '<p><?php _e("Generating passwords, please wait...", "wp-content-locker"); ?></p>' +
            '</div></div>');

        $('body').append($overlay);
        $submitButton.prop('disabled', true);
    });
});
</script>