<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Categories', 'wp-content-locker'); ?></h1>
    <a href="#" class="page-title-action add-new-category"><?php _e('Add New', 'wp-content-locker'); ?></a>
    <hr class="wp-header-end">

    <div id="col-container" class="wp-clearfix">
        <div id="col-left">
            <div class="col-wrap">
                <div class="form-wrap">
                    <h2><?php echo empty($_GET['edit']) ? __('Add New Category', 'wp-content-locker') : __('Edit Category', 'wp-content-locker'); ?></h2>
                    <form id="wcl-category-form" method="post">
                        <input type="hidden" id="category-id" name="category_id" value="0">
                        
                        <div class="form-field">
                            <label for="category-name"><?php _e('Name', 'wp-content-locker'); ?></label>
                            <input type="text" id="category-name" name="name" required>
                        </div>

                        <div class="form-field">
                            <label for="category-description"><?php _e('Description', 'wp-content-locker'); ?></label>
                            <textarea id="category-description" name="description"></textarea>
                        </div>

                        <div class="form-field">
                            <label for="category-parent"><?php _e('Parent Category', 'wp-content-locker'); ?></label>
                            <select id="category-parent" name="parent_id">
                                <option value="0"><?php _e('None', 'wp-content-locker'); ?></option>
                                <?php
                                function display_category_options($categories, $level = 0, $selected = 0) {
                                    foreach ($categories as $category) {
                                        $indent = str_repeat('— ', $level);
                                        echo sprintf(
                                            '<option value="%d" %s>%s%s</option>',
                                            $category->id,
                                            selected($selected, $category->id, false),
                                            esc_html($indent),
                                            esc_html($category->name)
                                        );
                                        if (!empty($category->children)) {
                                            display_category_options($category->children, $level + 1, $selected);
                                        }
                                    }
                                }
                                display_category_options($this->get_categories_hierarchical());
                                ?>
                            </select>
                        </div>

                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php _e('Save Category', 'wp-content-locker'); ?>">
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <div id="col-right">
            <div class="col-wrap">
                <table class="wp-list-table widefat fixed striped table-view-list categories">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-name column-primary"><?php _e('Name', 'wp-content-locker'); ?></th>
                            <th scope="col" class="manage-column column-description"><?php _e('Description', 'wp-content-locker'); ?></th>
                            <th scope="col" class="manage-column column-count"><?php _e('Count', 'wp-content-locker'); ?></th>
                            <th scope="col" class="manage-column column-date"><?php _e('Date', 'wp-content-locker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $categories = $this->get_categories_hierarchical();
                        if (empty($categories)) {
                            echo '<tr><td colspan="4">' . __('No categories found.', 'wp-content-locker') . '</td></tr>';
                        } else {
                            $this->render_categories_table($categories);
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>