<div class="wrap">
    <h1><?php _e('Edit Password', 'wp-content-locker'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('update_password'); ?>
        <input type="hidden" name="action" value="update_password">
        <input type="hidden" name="password_id" value="<?php echo esc_attr($password['id']); ?>">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="password"><?php _e('Password', 'wp-content-locker'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="password" 
                           name="password" 
                           value="<?php echo esc_attr($password['password']); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="expires_at"><?php _e('Expires At', 'wp-content-locker'); ?></label>
                </th>
                <td>
                    <input type="datetime-local" 
                           id="expires_at" 
                           name="expires_at" 
                           value="<?php echo esc_attr(date('Y-m-d\TH:i', strtotime($password['expires_at']))); ?>" 
                           class="regular-text">
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" 
                   name="submit" 
                   id="submit" 
                   class="button button-primary" 
                   value="<?php _e('Update Password', 'wp-content-locker'); ?>">
            
            <a href="<?php echo esc_url(admin_url('admin.php?page=wcl-passwords')); ?>" 
               class="button button-secondary">
                <?php _e('Cancel', 'wp-content-locker'); ?>
            </a>
        </p>
    </form>
</div>