<?php
class WCL_Category_Service {
    private $category_model;
    private $download_model;

    public function __construct() {
        $this->category_model = new WCL_Category();
        $this->download_model = new WCL_Download();
    }

    public function create_category($data) {
        try {
            $category = new WCL_Category();
            $category->name = sanitize_text_field($data['name']);
            $category->description = sanitize_textarea_field($data['description']);
            $category->parent_id = !empty($data['parent_id']) ? absint($data['parent_id']) : null;
            $category->created_at = current_time('mysql');
            $category->updated_at = current_time('mysql');

            if ($category->save()) {
                return $category;
            }
            return false;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function update_category($id, $data) {
        try {
            $category = $this->get_category($id);
            if (!$category) {
                return false;
            }

            $category->name = sanitize_text_field($data['name']);
            $category->description = sanitize_textarea_field($data['description']);
            $category->parent_id = !empty($data['parent_id']) ? absint($data['parent_id']) : null;
            $category->updated_at = current_time('mysql');

            return $category->save();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function delete_category($id) {
        try {
            $category = $this->get_category($id);
            if (!$category) {
                return false;
            }

            // Move downloads to uncategorized or parent category
            $this->reassign_downloads($id, $category->parent_id);

            // Update child categories
            $this->update_child_categories($id, $category->parent_id);

            return $category->delete();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function get_category($id) {
        return WCL_Category::find($id);
    }

    public function get_categories($args = array()) {
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC',
            'parent_id' => null,
            'search' => '',
            'limit' => -1,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);
        $where = array();
        $params = array();

        if (isset($args['parent_id'])) {
            $where[] = 'parent_id ' . (is_null($args['parent_id']) ? 'IS NULL' : '= %d');
            if (!is_null($args['parent_id'])) {
                $params[] = $args['parent_id'];
            }
        }

        if (!empty($args['search'])) {
            $where[] = '(name LIKE %s OR description LIKE %s)';
            $params[] = '%' . $args['search'] . '%';
            $params[] = '%' . $args['search'] . '%';
        }

        return WCL_Category::find_all($where, $params, $args);
    }

    public function get_category_tree() {
        $categories = $this->get_categories();
        return $this->build_tree($categories);
    }

    private function build_tree($categories, $parent_id = null) {
        $tree = array();
        
        foreach ($categories as $category) {
            if ($category->parent_id === $parent_id) {
                $children = $this->build_tree($categories, $category->id);
                if ($children) {
                    $category->children = $children;
                }
                $tree[] = $category;
            }
        }
        
        return $tree;
    }

    private function reassign_downloads($category_id, $new_category_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcl_download_categories';

        return $wpdb->update(
            $table_name,
            array('category_id' => $new_category_id),
            array('category_id' => $category_id),
            array('%d'),
            array('%d')
        );
    }

    private function update_child_categories($category_id, $new_parent_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcl_categories';

        return $wpdb->update(
            $table_name,
            array('parent_id' => $new_parent_id),
            array('parent_id' => $category_id),
            array('%d'),
            array('%d')
        );
    }

    public function update_category_count($category_id) {
        global $wpdb;
        $downloads_table = $wpdb->prefix . 'wcl_download_categories';
        $category = $this->get_category($category_id);

        if ($category) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $downloads_table WHERE category_id = %d",
                $category_id
            ));

            $category->count = $count;
            return $category->save();
        }

        return false;
    }
}