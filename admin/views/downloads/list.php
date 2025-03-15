<?php
namespace WP_Content_Locker\Admin\Views\Downloads;

if (!defined('ABSPATH')) exit;

// Đảm bảo các file cần thiết được include
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

require_once dirname(__FILE__) . '/class-download-list-table.php';
require_once WP_CONTENT_LOCKER_PATH . '/includes/services/class-download-service.php';

use WP_Content_Locker\Includes\Services\Download_Service;

// Khởi tạo service
$download_service = new Download_Service();

try {
    // Khởi tạo list table
    $list_table = new Download_List_Table($download_service);
    $list_table->prepare_items();

    
	// Add necessary scripts and styles
wp_enqueue_script('wcl-admin-downloads', plugin_dir_url(WP_CONTENT_LOCKER_FILE) . 'admin/js/admin-downloads.js', array('jquery'), '1.0.0', true);
wp_enqueue_style('wcl-admin-downloads', plugin_dir_url(WP_CONTENT_LOCKER_FILE) . 'admin/css/admin-downloads.css');

// Add localized script
wp_localize_script('wcl-admin-downloads', 'wcl_admin', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('wcl_admin_nonce'),
    'list_url' => admin_url('admin.php?page=wcl-downloads'),
    'messages' => array(
        'confirm_delete' => __('Are you sure you want to delete selected items?', 'wp-content-locker'),
        'no_items' => __('Please select items to delete.', 'wp-content-locker'),
        'deleting' => __('Deleting...', 'wp-content-locker'),
        'error' => __('An error occurred while processing your request.', 'wp-content-locker'),
        'delete_success' => __('Items deleted successfully.', 'wp-content-locker')
    )
));
    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Downloads', 'wp-content-locker'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wcl-downloads&action=add')); ?>" class="page-title-action">
            <?php _e('Add New', 'wp-content-locker'); ?>
        </a>
        <hr class="wp-header-end">

        <?php
        if (isset($_GET['message'])) {
            $message = '';
            $type = 'success';

            switch ($_GET['message']) {
                case 'deleted':
                    $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
                    $message = sprintf(
                        _n(
                            '%s item deleted successfully.',
                            '%s items deleted successfully.',
                            $count,
                            'wp-content-locker'
                        ),
                        number_format_i18n($count)
                    );
                    break;
            }

            if ($message) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr($type),
                    esc_html($message)
                );
            }
        }
        ?>

        <form id="downloads-filter" method="post">
            <?php
            $list_table->search_box(__('Search Downloads', 'wp-content-locker'), 'download');
            $list_table->display();
            ?>
        </form>
    </div>

    <div id="ajax-response"></div>

<?php
} catch (Exception $e) {
    ?>
    <div class="wrap">
        <div class="notice notice-error">
            <p><?php echo esc_html($e->getMessage()); ?></p>
        </div>
    </div>
    <?php
}