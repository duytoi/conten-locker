<?php
class WCL_Categories_Controller extends WCL_API_Controller {
    protected $rest_base = 'categories';
    private $category_service;

    public function __construct() {
        parent::__construct();
        $this->category_service = new WCL_Category_Service();
    }

    public function get_items_permissions_check($request) {
        return $this->verify_api_key();
    }

    public function get_items($request) {
        $args = array(
            'orderby' => $request['orderby'] ?? 'name',
            'order' => $request['order'] ?? 'ASC',
            'search' => $request['search'] ?? '',
        );

        if (isset($request['parent'])) {
            $args['parent_id'] = absint($request['parent']);
        }

        $categories = $this->category_service->get_categories($args);
        
        $response = array();
        foreach ($categories as $category) {
            $response[] = $this->prepare_item_for_response($category, $request);
        }

        return new WP_REST_Response($response, 200);
    }

    public function create_item_permissions_check($request) {
        return $this->verify_api_key() && current_user_can('manage_options');
    }

    public function create_item($request) {
        $category_data = array(
            'name' => sanitize_text_field($request['name']),
            'description' => sanitize_textarea_field($request['description']),
            'parent_id' => !empty($request['parent_id']) ? absint($request['parent_id']) : null
        );

        $category = $this->category_service->create_category($category_data);
        if (!$category) {
            return $this->prepare_error_response(
                'creation_failed',
                __('Failed to create category', 'wp-content-locker')
            );
        }

        return new WP_REST_Response(
            $this->prepare_item_for_response($category, $request),
            201
        );
    }

    public function get_item($request) {
        $category = $this->category_service->get_category($request['id']);
        if (!$category) {
            return $this->prepare_error_response(
                'not_found',
                __('Category not found', 'wp-content-locker'),
                404
            );
        }

        return new WP_REST_Response(
            $this->prepare_item_for_response($category, $request),
            200
        );
    }

    public function update_item($request) {
        $category = $this->category_service->get_category($request['id']);
        if (!$category) {
            return $this->prepare_error_response(
                'not_found',
                __('Category not found', 'wp-content-locker'),
                404
            );
        }

        $category_data = array();
        if (isset($request['name'])) {
            $category_data['name'] = sanitize_text_field($request['name']);
        }
        if (isset($request['description'])) {
            $category_data['description'] = sanitize_textarea_field($request['description']);
        }
        if (isset($request['parent_id'])) {
            $category_data['parent_id'] = absint($request['parent_id']);
        }

        $updated = $this->category_service->update_category($request['id'], $category_data);
        if (!$updated) {
            return $this->prepare_error_response(
                'update_failed',
                __('Failed to update category', 'wp-content-locker')
            );
        }

        return new WP_REST_Response(
            $this->prepare_item_for_response(
                $this->category_service->get_category($request['id']),
                $request
            ),
            200
        );
    }

    public function delete_item($request) {
        $category = $this->category_service->get_category($request['id']);
        if (!$category) {
            return $this->prepare_error_response(
                'not_found',
                __('Category not found', 'wp-content-locker'),
                404
            );
        }

        $deleted = $this->category_service->delete_category($request['id']);
        if (!$deleted) {
            return $this->prepare_error_response(
                'deletion_failed',
                __('Failed to delete category', 'wp-content-locker')
            );
        }

        return new WP_REST_Response(null, 204);
    }

    protected function prepare_item_for_response($item, $request) {
        return array(
            'id' => $item->id,
            'name' => $item->name,
            'slug' => $item->slug,
            'description' => $item->description,
            'parent_id' => $item->parent_id,
            'count' => $item->count,
            'created_at' => mysql_to_rfc3339($item->created_at),
            'updated_at' => mysql_to_rfc3339($item->updated_at),
        );
    }

    public function get_collection_params() {
        return array(
            'search' => array(
                'description' => __('Limit results to those matching a string.', 'wp-content-locker'),
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'parent' => array(
                'description' => __('Limit results to those of particular parent ID.', 'wp-content-locker'),
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'orderby' => array(
                'description' => __('Sort collection by object attribute.', 'wp-content-locker'),
                'type' => 'string',
                'default' => 'name',
                'enum' => array('name', 'created_at', 'count'),
            ),
            'order' => array(
                'description' => __('Order sort attribute ascending or descending.', 'wp-content-locker'),
                'type' => 'string',
                'default' => 'ASC',
                'enum' => array('ASC', 'DESC'),
            ),
        );
    }
}