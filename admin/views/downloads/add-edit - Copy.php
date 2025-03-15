<?php
if (!defined('ABSPATH')) exit;

use WP_Content_Locker\Includes\Models\Download;
use WP_Content_Locker\Includes\Services\Download_Service;

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'wcl'));
}

// Initialize services
$download_service = new Download_Service();
$download_model = new Download();

// Get download data for edit
$download_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$is_edit = $download_id > 0;

$download = null;
if ($is_edit) {
    $download = $download_model->find($download_id);
    if (!$download) {
        wp_die(__('Download not found', 'wcl'));
    }
}

// Prepare form data
$form_data = $is_edit ? (array)$download : [
    'title' => '',
    'description' => '',
    'file_path' => '',
    'url' => '',
    'category_id' => 0,
    'status' => 'active'
];

// Get categories
global $wpdb;
$categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wcl_categories ORDER BY name ASC");

// Add necessary scripts and styles
//wp_enqueue_script('wcl-admin-downloads', plugin_dir_url(WP_CONTENT_LOCKER_FILE) . 'admin/js/admin-downloads-add-form.js', array('jquery'), '1.0.0', true);
wp_enqueue_style('wcl-admin-downloads', plugin_dir_url(WP_CONTENT_LOCKER_FILE) . 'admin/css/admin-downloads.css');
// Add necessary scripts and styles
wp_enqueue_script(
    'wcl-admin-downloads-form',
    plugin_dir_url(WP_CONTENT_LOCKER_FILE) . 'admin/js/admin-downloads-add-form.js',
    array('jquery'),
    '1.0.0',
    true
);

wp_localize_script(
    'wcl-admin-downloads-form',
    'wcl_admin',
    array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wcl_download_nonce'),
        'translations' => array(
            'error' => __('An error occurred while saving', 'wcl'),
            'title_required' => __('Title is required', 'wcl'),
            'select_file' => __('Please select a file', 'wcl'),
            'enter_url' => __('Please enter a valid URL', 'wcl'),
            'file_too_large' => __('File size exceeds maximum limit of 50MB', 'wcl'),
            'success' => __('Download saved successfully', 'wcl')
        )
    )
);

wp_enqueue_style('wcl-admin-downloads', plugin_dir_url(WP_CONTENT_LOCKER_FILE) . 'admin/css/admin-downloads.css');
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $is_edit ? __('Edit Download', 'wcl') : __('Add New Download', 'wcl'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=wcl-downloads'); ?>" class="page-title-action">
        <?php _e('Back to Downloads', 'wcl'); ?>
    </a>
    
    <hr class="wp-header-end">

    <!-- Messages container -->
    <div id="wcl-messages"></div>

    <form id="wcl-download-form" method="post" enctype="multipart/form-data" 
          data-existing-file="<?php echo !empty($form_data['file_path']); ?>">
        
        <?php wp_nonce_field('wcl_download_nonce', 'wcl_nonce'); ?>
        <input type="hidden" name="download_id" value="<?php echo $download_id; ?>">

        <table class="form-table" role="presentation">
            <!-- Title Field -->
            <tr>
                <th scope="row">
                    <label for="title"><?php _e('Title', 'wcl'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input name="title" type="text" id="title" 
                           value="<?php echo esc_attr($form_data['title']); ?>" 
                           class="regular-text" required>
                </td>
            </tr>

            <!-- Category Field -->
            <tr>
                <th scope="row">
                    <label for="category_id"><?php _e('Category', 'wcl'); ?></label>
                </th>
                <td>
                    <select name="category_id" id="category_id">
                        <option value=""><?php _e('Select Category', 'wcl'); ?></option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category->id; ?>" 
                                    <?php selected($form_data['category_id'], $category->id); ?>>
                                <?php echo esc_html($category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <!-- Description Field -->
            <tr>
                <th scope="row">
                    <label for="description"><?php _e('Description', 'wcl'); ?></label>
                </th>
                <td>
                    <?php
                    wp_editor(
                        $form_data['description'],
                        'description',
                        [
                            'textarea_name' => 'description',
                            'textarea_rows' => 5,
                            'media_buttons' => true,
                            'editor_class' => 'required',
                            'teeny' => true
                        ]
                    );
                    ?>
                </td>
            </tr>

            <!-- Download Source Field -->
<tr>
    <th scope="row">
        <label><?php _e('Download Source', 'wcl'); ?> <span class="required">*</span></label>
    </th>
    <td>
        <fieldset>
            <legend class="screen-reader-text">
                <span><?php _e('Download Source', 'wcl'); ?></span>
            </legend>
            <p class="source-type-selector">
                <label class="source-type-option">
                    <input type="radio" name="source_type" value="file" 
                           <?php checked(empty($form_data['url'])); ?>>
                    <span><?php _e('Upload File', 'wcl'); ?></span>
                </label>
                <br>
                <label class="source-type-option">
                    <input type="radio" name="source_type" value="url" 
                           <?php checked(!empty($form_data['url'])); ?>>
                    <span><?php _e('External URL', 'wcl'); ?></span>
                </label>
            </p>
        </fieldset>
    </td>
</tr>

<!-- File Upload Field -->
<tr id="file_upload_row" class="source-row file-source">
    <th scope="row">
        <label for="file_upload"><?php _e('File Upload', 'wcl'); ?></label>
    </th>
    <td>
        <div class="file-upload-container">
            <input type="file" name="file_upload" id="file_upload" 
                   accept="<?php echo esc_attr(implode(',', $download_service->get_allowed_mime_types())); ?>"
                   style="display: none;">
            <button type="button" class="button" id="select_file">
                <?php _e('Select File', 'wcl'); ?>
            </button>
            <span class="selected-file-name"></span>
            <?php if (!empty($form_data['file_path'])): ?>
                <div class="existing-file">
                    <p class="description">
                        <?php _e('Current file:', 'wcl'); ?> 
                        <strong><?php echo esc_html(basename($form_data['file_path'])); ?></strong>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <p class="description">
            <?php _e('Allowed file types:', 'wcl'); ?> 
            <?php echo esc_html(implode(', ', array_keys($download_service->get_allowed_mime_types()))); ?>
        </p>
    </td>
</tr>

            <!-- URL Field -->
            <tr id="url_row" class="source-row">
                <th scope="row">
                    <label for="url"><?php _e('URL', 'wcl'); ?></label>
                </th>
                <td>
                    <input name="url" type="url" id="url" 
                           value="<?php echo esc_url($form_data['url']); ?>" 
                           class="regular-text" 
                           placeholder="https://">
                    <p class="description">
                        <?php _e('Enter the full URL to the download file', 'wcl'); ?>
                    </p>
                </td>
            </tr>

            <!-- Status Field -->
            <tr>
                <th scope="row">
                    <label for="status"><?php _e('Status', 'wcl'); ?></label>
                </th>
                <td>
                    <select name="status" id="status">
                        <option value="active" <?php selected($form_data['status'], 'active'); ?>>
                            <?php _e('Active', 'wcl'); ?>
                        </option>
                        <option value="inactive" <?php selected($form_data['status'], 'inactive'); ?>>
                            <?php _e('Inactive', 'wcl'); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>

        <div class="submit-container">
            <?php submit_button(__('Save Changes', 'wcl'), 'primary', 'submit', false); ?>
            <span class="spinner"></span>
        </div>
    </form>
</div>

<?php
// Add specific styles for the form
add_action('admin_head', function() {
?>

<?php
});