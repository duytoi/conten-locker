<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Download Categories', 'wp-content-locker'); ?></h1>
    <a href="#" class="page-title-action" id="add-category"><?php _e('Add New Category', 'wp-content-locker'); ?></a>
    <hr class="wp-header-end">

    <div id="col-container" class="wp-clearfix">
        <div id="col-left">
            <div class="col-wrap">
                <div class="form-wrap">
                    <h2><?php _e('Add New Category', 'wp-content-locker'); ?></h2>
                    <form id="add-category-form" method="post">
                        <div class="form-field">
                            <label for="category-name"><?php _e('Name', 'wp-content-locker'); ?></label>
                            <input type="text" id="category-name" name="name" required>
                            <p><?php _e('The name is how it appears on your site.', 'wp-content-locker'); ?></p>
                        </div>

                        <div class="form-field">
                            <label for="category-slug"><?php _e('Slug', 'wp-content-locker'); ?></label>
                            <input type="text" id="category-slug" name="slug">
                            <p><?php _e('The "slug" is the URL-friendly version of the name.', 'wp-content-locker'); ?></p>
                        </div>

                        <div class="form-field">
                            <label for="category-parent"><?php _e('Parent Category', 'wp-content-locker'); ?></label>
                            <select id="category-parent" name="parent_id">
                                <option value=""><?php _e('None', 'wp-content-locker'); ?></option>
                                <?php
                                $categories = $this->category_service->get_category_tree();
                                foreach ($categories as $category) {
                                    echo '<option value="' . esc_attr($category->id) . '">' . 
                                         esc_html($category->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="category-description"><?php _e('Description', 'wp-content-locker'); ?></label>
                            <textarea id="category-description" name="description"></textarea>
                            <p><?php _e('The description is not prominent by default.', 'wp-content-locker'); ?></p>
                        </div>

                        <?php wp_nonce_field('wcl_add_category', 'wcl_category_nonce'); ?>
                        <input type="hidden" name="action" value="wcl_add_category">
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php _e('Add New Category', 'wp-content-locker'); ?>">
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <div id="col-right">
            <div class="col-wrap">
                <table class="wp-list-table widefat fixed striped categories">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-name column-primary">
                                <?php _e('Name', 'wp-content-locker'); ?>
                            </th>
                            <th scope="col" class="manage-column column-description">
                                <?php _e('Description', 'wp-content-locker'); ?>
                            </th>
                            <th scope="col" class="manage-column column-slug">
                                <?php _e('Slug', 'wp-content-locker'); ?>
                            </th>
                            <th scope="col" class="manage-column column-count">
                                <?php _e('Count', 'wp-content-locker'); ?>
                            </th>
                        </tr>
                    </thead>

                    <tbody id="the-list">
                        <?php
                        $categories = $this->category_service->get_categories();
                        if (empty($categories)) {
                            echo '<tr class="no-items"><td class="colspanchange" colspan="4">';
                            _e('No categories found.', 'wp-content-locker');
                            echo '</td></tr>';
                        } else {
                            foreach ($categories as $category) {
                                $edit_link = add_query_arg(
                                    array(
                                        'action' => 'edit',
                                        'id' => $category->id
                                    )
                                );
                                ?>
                                <tr>
                                    <td class="name column-name has-row-actions column-primary">
                                        <strong>
                                            <a href="<?php echo esc_url($edit_link); ?>">
                                                <?php echo esc_html($category->name); ?>
                                            </a>
                                        </strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo esc_url($edit_link); ?>">
                                                    <?php _e('Edit', 'wp-content-locker'); ?>
                                                </a> |
                                            </span>
                                            <span class="delete">
                                                <a href="#" class="delete-category" 
                                                   data-id="<?php echo esc_attr($category->id); ?>">
                                                    <?php _e('Delete', 'wp-content-locker'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="description column-description">
                                        <?php echo esc_html($category->description); ?>
                                    </td>
                                    <td class="slug column-slug">
                                        <?php echo esc_html($category->slug); ?>
                                    </td>
                                    <td class="count column-count">
                                        <?php echo esc_html($category->count); ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>