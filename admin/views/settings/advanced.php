<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h2 class="nav-tab-wrapper">
        <a href="?page=wcl-settings&tab=general" class="nav-tab"><?php _e('General', 'wp-content-locker'); ?></a>
        <a href="?page=wcl-settings&tab=protection" class="nav-tab"><?php _e('Protection', 'wp-content-locker'); ?></a>
        <a href="?page=wcl-settings&tab=advanced" class="nav-tab nav-tab-active"><?php _e('Advanced', 'wp-content-locker'); ?></a>
    </h2>

    <form method="post" action="options.php">
        <?php
        settings_fields('wcl_advanced_settings');
        $options = get_option('wcl_advanced_options', array());
        ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php _e('Cache Settings', 'wp-content-locker'); ?>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <?php _e('Cache Settings', 'wp-content-locker'); ?>
                        </legend>
                        <label>
                            <input type="checkbox" 
                                   name="wcl_advanced_options[enable_cache]" 
                                   value="1"
                                   <?php checked(isset($options['enable_cache'])); ?>>
                            <?php _e('Enable download cache', 'wp-content-locker'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Cache downloaded files for faster delivery.', 'wp-content-locker'); ?>
                        </p>

                        <br>
                        <label for="wcl_cache_expiry">
                            <?php _e('Cache Expiry:', 'wp-content-locker'); ?>
                        </label>
                        <select id="wcl_cache_expiry" 
                                name="wcl_advanced_options[cache_expiry]">
                            <option value="3600" <?php selected($options['cache_expiry'] ?? '3600', '3600'); ?>>
                                <?php _e('1 Hour', 'wp-content-locker'); ?>
                            </option>
                            <option value="86400" <?php selected($options['cache_expiry'] ?? '3600', '86400'); ?>>
                                <?php _e('24 Hours', 'wp-content-locker'); ?>
                            </option>
                            <option value="604800" <?php selected($options['cache_expiry'] ?? '3600', '604800'); ?>>
                                <?php _e('1 Week', 'wp-content-locker'); ?>
                            </option>
                        </select>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('Logging', 'wp-content-locker'); ?>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <?php _e('Logging Settings', 'wp-content-locker'); ?>
                        </legend>
                        <label>
                            <input type="checkbox" 
                                   name="wcl_advanced_options[enable_logging]" 
                                   value="1"
                                   <?php checked(isset($options['enable_logging'])); ?>>
                            <?php _e('Enable download logging', 'wp-content-locker'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Log download attempts and protection events.', 'wp-content-locker'); ?>
                        </p>

                        <br>
                        <label for="wcl_log_retention">
                            <?php _e('Log Retention:', 'wp-content-locker'); ?>
                        </label>
                        <select id="wcl_log_retention" 
                                name="wcl_advanced_options[log_retention]">
                            <option value="30" <?php selected($options['log_retention'] ?? '30', '30'); ?>>
                                <?php _e('30 Days', 'wp-content-locker'); ?>
                            </option>
                            <option value="90" <?php selected($options['log_retention'] ?? '30', '90'); ?>>
                                <?php _e('90 Days', 'wp-content-locker'); ?>
                            </option>
                            <option value="180" <?php selected($options['log_retention'] ?? '30', '180'); ?>>
                                <?php _e('180 Days', 'wp-content-locker'); ?>
                            </option>
                            <option value="365" <?php selected($options['log_retention'] ?? '30', '365'); ?>>
                                <?php _e('1 Year', 'wp-content-locker'); ?>
                            </option>
                        </select>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('API Settings', 'wp-content-locker'); ?>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <?php _e('API Settings', 'wp-content-locker'); ?>
                        </legend>
                        <label>
                            <input type="checkbox" 
                                   name="wcl_advanced_options[enable_api]" 
                                   value="1"
                                   <?php checked(isset($options['enable_api'])); ?>>
                            <?php _e('Enable REST API', 'wp-content-locker'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Allow external applications to interact with downloads via REST API.', 'wp-content-locker'); ?>
                        </p>

                        <?php if (isset($options['enable_api'])): ?>
                            <br>
                            <label for="wcl_api_key">
                                <?php _e('API Key:', 'wp-content-locker'); ?>
                            </label>
                            <input type="text" 
                                   id="wcl_api_key" 
                                   name="wcl_advanced_options[api_key]" 
                                   value="<?php echo esc_attr($options['api_key'] ?? ''); ?>"
                                   class="regular-text">
                            <button type="button" 
                                    class="button" 
                                    id="wcl-generate-api-key">
                                <?php _e('Generate New Key', 'wp-content-locker'); ?>
                            </button>
                        <?php endif; ?>
                    </fieldset>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>