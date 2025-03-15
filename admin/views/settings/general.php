<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors(); ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=wcl-settings&tab=general" class="nav-tab nav-tab-active"><?php _e('General', 'wp-content-locker'); ?></a>
        <a href="?page=wcl-settings&tab=protection" class="nav-tab"><?php _e('Protection', 'wp-content-locker'); ?></a>
        <a href="?page=wcl-settings&tab=advanced" class="nav-tab"><?php _e('Advanced', 'wp-content-locker'); ?></a>
    </h2>

    <form method="post" action="options.php">
        <?php
        settings_fields('wcl_general_settings');
        $options = get_option('wcl_general_options', array(
            'download_path' => 'downloads',
            'download_method' => 'direct',
            'allowed_types' => array('pdf', 'zip', 'doc', 'docx'),
            'enable_encryption' => 0,
            'force_download' => 1,
            'verify_nonce' => 1
        ));
        ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="wcl_download_path">
                        <?php _e('Download Path', 'wp-content-locker'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           id="wcl_download_path" 
                           name="wcl_general_options[download_path]" 
                           value="<?php echo esc_attr($options['download_path']); ?>"
                           class="regular-text">
                    <p class="description">
                        <?php _e('Relative path from wp-content directory where downloads will be stored.', 'wp-content-locker'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wcl_download_method">
                        <?php _e('Download Method', 'wp-content-locker'); ?>
                    </label>
                </th>
                <td>
                    <select id="wcl_download_method" 
                            name="wcl_general_options[download_method]">
                        <option value="direct" <?php selected($options['download_method'], 'direct'); ?>>
                            <?php _e('Direct Download', 'wp-content-locker'); ?>
                        </option>
                        <option value="xsendfile" <?php selected($options['download_method'], 'xsendfile'); ?>>
                            <?php _e('X-Sendfile', 'wp-content-locker'); ?>
                        </option>
                        <option value="redirect" <?php selected($options['download_method'], 'redirect'); ?>>
                            <?php _e('Redirect', 'wp-content-locker'); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('File Types', 'wp-content-locker'); ?></th>
                <td>
                    <fieldset>
                        <?php
                        $common_types = array(
                            'pdf' => __('PDF Documents', 'wp-content-locker'),
                            'zip' => __('ZIP Archives', 'wp-content-locker'),
                            'doc,docx' => __('Word Documents', 'wp-content-locker'),
                            'xls,xlsx' => __('Excel Spreadsheets', 'wp-content-locker'),
                            'jpg,jpeg,png' => __('Images', 'wp-content-locker'),
                            'mp3,wav' => __('Audio Files', 'wp-content-locker'),
                            'mp4,mov' => __('Video Files', 'wp-content-locker')
                        );

                        foreach ($common_types as $type => $label) :
                            $types = explode(',', $type);
                            $checked = count(array_intersect($types, (array)$options['allowed_types'])) > 0;
                        ?>
                            <label>
                                <input type="checkbox" 
                                       name="wcl_general_options[allowed_types][]" 
                                       value="<?php echo esc_attr($type); ?>"
                                       <?php checked($checked); ?>>
                                <?php echo esc_html($label); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Security Settings', 'wp-content-locker'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="wcl_general_options[enable_encryption]" 
                                   value="1"
                                   <?php checked(!empty($options['enable_encryption'])); ?>>
                            <?php _e('Enable file encryption', 'wp-content-locker'); ?>
                        </label><br>

                        <label>
                            <input type="checkbox" 
                                   name="wcl_general_options[force_download]" 
                                   value="1"
                                   <?php checked(!empty($options['force_download'])); ?>>
                            <?php _e('Force download (instead of opening in browser)', 'wp-content-locker'); ?>
                        </label><br>

                        <label>
                            <input type="checkbox" 
                                   name="wcl_general_options[verify_nonce]" 
                                   value="1"
                                   <?php checked(!empty($options['verify_nonce'])); ?>>
                            <?php _e('Verify download nonce', 'wp-content-locker'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>