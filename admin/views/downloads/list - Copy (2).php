<?php
if (!defined('ABSPATH')) exit;

require_once dirname(__FILE__) . '/class-download-list-table.php';
use WP_Content_Locker\Admin\Views\Downloads\Download_List_Table;

// Handle bulk actions
if (isset($_POST['action']) || isset($_POST['action2'])) {
    $action = isset($_POST['action']) && $_POST['action'] != -1 
        ? $_POST['action'] 
        : $_POST['action2'];
    
    if ($action && isset($_POST['download'])) {
        $download_ids = array_map('intval', $_POST['download']);
        
        if (!empty($download_ids)) {
            check_admin_referer('bulk-downloads');
            
            switch ($action) {
                case 'delete':
                    foreach ($download_ids as $id) {
                        wp_delete_post($id, true);
                    }
                    $message = 'deleted_bulk';
                    break;
                    
                case 'activate':
                    foreach ($download_ids as $id) {
                        wp_update_post([
                            'ID' => $id,
                            'post_status' => 'publish'
                        ]);
                    }
                    $message = 'activated';
                    break;
                    
                case 'deactivate':
                    foreach ($download_ids as $id) {
                        wp_update_post([
                            'ID' => $id,
                            'post_status' => 'draft'
                        ]);
                    }
                    $message = 'deactivated';
                    break;
            }
            
            wp_redirect(add_query_arg('message', $message));
            exit;
        }
    }
}

// Handle single actions
if (isset($_GET['action']) && isset($_GET['download_id'])) {
    $download_id = intval($_GET['download_id']);
    
    switch ($_GET['action']) {
        case 'delete':
            check_admin_referer('delete_download_' . $download_id);
            wp_delete_post($download_id, true);
            wp_redirect(add_query_arg('message', 'deleted'));
            exit;
            
        case 'activate':
            check_admin_referer('activate_download_' . $download_id);
            wp_update_post([
                'ID' => $download_id,
                'post_status' => 'publish'
            ]);
            wp_redirect(add_query_arg('message', 'activated'));
            exit;
            
        case 'deactivate':
            check_admin_referer('deactivate_download_' . $download_id);
            wp_update_post([
                'ID' => $download_id,
                'post_status' => 'draft'
            ]);
            wp_redirect(add_query_arg('message', 'deactivated'));
            exit;
    }
}

?>
<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Downloads', 'wp-content-locker'); ?>
    </h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=wcl-downloads&action=add')); ?>" class="page-title-action">
        <?php _e('Add New', 'wp-content-locker'); ?>
    </a>

    <?php
    // Show messages
    if (isset($_GET['message'])) {
        $message = sanitize_text_field($_GET['message']);
        $messages = array(
            'created' => __('Download created successfully.', 'wp-content-locker'),
            'updated' => __('Download updated successfully.', 'wp-content-locker'),
            'deleted' => __('Download deleted successfully.', 'wp-content-locker'),
            'deleted_bulk' => __('Selected downloads deleted successfully.', 'wp-content-locker'),
            'activated' => __('Download(s) activated successfully.', 'wp-content-locker'),
            'deactivated' => __('Download(s) deactivated successfully.', 'wp-content-locker'),
            'error' => __('An error occurred. Please try again.', 'wp-content-locker'),
        );
        
        if (isset($messages[$message])) {
            $class = ($message === 'error') ? 'error' : 'updated';
            echo '<div class="' . esc_attr($class) . ' notice is-dismissible"><p>' . esc_html($messages[$message]) . '</p></div>';
        }
    }

    // Display category filter if exists
    $categories = get_terms([
        'taxonomy' => 'download_category',
        'hide_empty' => false,
    ]);

    if (!is_wp_error($categories) && !empty($categories)): ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="category_filter" id="category-filter">
                    <option value=""><?php _e('All Categories', 'wp-content-locker'); ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo esc_attr($category->term_id); ?>" 
                                <?php selected(isset($_GET['category']) ? $_GET['category'] : '', $category->term_id); ?>>
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Filter', 'wp-content-locker'), 'button', 'filter_action', false); ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="post">
        <?php
        $downloads_list = new Download_List_Table();
        $downloads_list->prepare_items();
        
        // Add search box
        $downloads_list->search_box(
            __('Search Downloads', 'wp-content-locker'),
            'download_search'
        );
        
        // Display the table
        $downloads_list->display();
        ?>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle category filter
    $('#category-filter').on('change', function() {
        var category = $(this).val();
        if (category) {
            var url = new URL(window.location.href);
            url.searchParams.set('category', category);
            window.location.href = url.toString();
        }
    });

    // Handle bulk actions confirmation
    $('form').on('submit', function(e) {
        var action = $('#bulk-action-selector-top').val();
        if (action === 'delete') {
            if (!confirm('<?php _e("Are you sure you want to delete the selected items?", "wp-content-locker"); ?>')) {
                e.preventDefault();
            }
        }
    });

    // Make notices dismissible
    $('.notice.is-dismissible').each(function() {
        var $this = $(this);
        $this.append('<button type="button" class="notice-dismiss">'+
            '<span class="screen-reader-text"><?php _e("Dismiss this notice.", "wp-content-locker"); ?></span>'+
            '</button>'
        );
        
        $('.notice-dismiss', $this).on('click', function(e) {
            e.preventDefault();
            $this.fadeTo(100, 0, function() {
                $this.slideUp(100, function() {
                    $this.remove();
                });
            });
        });
    });
});
</script>