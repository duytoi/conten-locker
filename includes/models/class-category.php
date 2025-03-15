<?php
class WCL_Category extends WCL_Base_Model {
    public $id;
    public $name;
    public $slug;
    public $description;
    public $parent_id;
    public $count;
    public $created_at;
    public $updated_at;

    protected static $table_name = 'wcl_categories';
    protected static $primary_key = 'id';

    /**
     * Validate category data
     */
    public function validate() {
        if (empty($this->name)) {
            throw new Exception(__('Category name is required', 'wp-content-locker'));
        }

        if (empty($this->slug)) {
            $this->slug = sanitize_title($this->name);
        }

        return true;
    }

    /**
     * Create new category
     */
    public function create() {
        $this->validate();

        // Check if slug already exists
        if ($this->slug_exists($this->slug)) {
            $this->slug = $this->generate_unique_slug($this->slug);
        }

        $data = array(
            'name' => sanitize_text_field($this->name),
            'slug' => $this->slug,
            'description' => sanitize_textarea_field($this->description),
            'parent_id' => absint($this->parent_id),
            'count' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $result = $this->wpdb->insert(
            $this->get_table_name(),
            $data,
            array('%s', '%s', '%s', '%d', '%d', '%s', '%s')
        );

        if ($result === false) {
            throw new Exception(__('Failed to create category', 'wp-content-locker'));
        }

        $this->id = $this->wpdb->insert_id;
        return $this->id;
    }

    /**
     * Update existing category
     */
    public function update() {
        $this->validate();

        // Check if slug changed and already exists
        if ($this->slug_exists($this->slug, $this->id)) {
            $this->slug = $this->generate_unique_slug($this->slug);
        }

        $data = array(
            'name' => sanitize_text_field($this->name),
            'slug' => $this->slug,
            'description' => sanitize_textarea_field($this->description),
            'parent_id' => absint($this->parent_id),
            'updated_at' => current_time('mysql')
        );

        $result = $this->wpdb->update(
            $this->get_table_name(),
            $data,
            array('id' => $this->id),
            array('%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );

        if ($result === false) {
            throw new Exception(__('Failed to update category', 'wp-content-locker'));
        }

        return true;
    }

    /**
     * Delete category
     */
    public function delete() {
        // Update children categories to parent_id = 0
        $this->wpdb->update(
            $this->get_table_name(),
            array('parent_id' => 0),
            array('parent_id' => $this->id),
            array('%d'),
            array('%d')
        );

        $result = $this->wpdb->delete(
            $this->get_table_name(),
            array('id' => $this->id),
            array('%d')
        );

        if ($result === false) {
            throw new Exception(__('Failed to delete category', 'wp-content-locker'));
        }

        return true;
    }

    /**
     * Get all categories
     */
    public static function get_all($args = array()) {
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC',
            'parent_id' => null,
            'search' => '',
            'limit' => -1,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);
        $where = array('1=1');
        $params = array();

        if (!is_null($args['parent_id'])) {
            $where[] = 'parent_id = %d';
            $params[] = $args['parent_id'];
        }

        if (!empty($args['search'])) {
            $where[] = '(name LIKE %s OR description LIKE %s)';
            $search_term = '%' . self::get_instance()->wpdb->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $query = "SELECT * FROM " . self::get_instance()->get_table_name() . 
                " WHERE " . implode(' AND ', $where) .
                " ORDER BY {$args['orderby']} {$args['order']}";

        if ($args['limit'] > 0) {
            $query .= " LIMIT %d OFFSET %d";
            $params[] = $args['limit'];
            $params[] = $args['offset'];
        }

        if (!empty($params)) {
            $query = self::get_instance()->wpdb->prepare($query, $params);
        }

        return self::get_instance()->wpdb->get_results($query);
    }

    /**
     * Get category by ID
     */
    public static function get_by_id($id) {
        $result = self::get_instance()->wpdb->get_row(
            self::get_instance()->wpdb->prepare(
                "SELECT * FROM " . self::get_instance()->get_table_name() . " WHERE id = %d",
                $id
            )
        );

        if (!$result) {
            return null;
        }

        $category = new self();
        foreach ($result as $key => $value) {
            $category->$key = $value;
        }

        return $category;
    }

    /**
     * Check if slug exists
     */
    private function slug_exists($slug, $exclude_id = null) {
        $query = "SELECT COUNT(*) FROM " . $this->get_table_name() . " WHERE slug = %s";
        $params = array($slug);

        if ($exclude_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }

        return (int) $this->wpdb->get_var($this->wpdb->prepare($query, $params)) > 0;
    }

    /**
     * Generate unique slug
     */
    private function generate_unique_slug($slug) {
        $original_slug = $slug;
        $counter = 1;

        while ($this->slug_exists($slug, $this->id)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Increment download count
     */
    public function increment_count() {
        return $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE " . $this->get_table_name() . " SET count = count + 1 WHERE id = %d",
                $this->id
            )
        );
    }
}