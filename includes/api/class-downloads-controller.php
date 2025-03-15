<?php
class WCL_Downloads_Controller extends WCL_API_Controller {
    protected $rest_base = 'downloads';
    private $download_service;

    public function __construct() {
        parent::__construct();
        $this->download_service = new WCL_Download_Service();
    }

    public function get_items_permissions_check($request) {
        return $this->verify_api_key();
    }

    public function get_items($request) {
        $args = array(
            'limit' => $request['per_page'] ?? 10,
            'offset' => ($request['page'] ?? 1 - 1) * ($request['per_page'] ?? 10),
            'orderby' => $request['orderby'] ?? 'created_at',
            'order' => $request['order'] ?? 'DESC',
        );

        if (!empty($request['search'])) {
            $args['search'] = $request['search'];
        }

        if (!empty($request['category'])) {
            $args['category'] = absint($request['category']);
        }

        $downloads = $this->download_service->get_downloads($args);
        $total = $this->download_service->get_downloads_count($args);

        $response = array();
        foreach ($downloads as $download) {
            $response[] = $this->prepare_item_for_response($download, $request);
        }

        return new WP_REST_Response(
            array(
                'items' => $response,
                'total' => $total,
                'pages' => ceil($total / $args['limit'])
            ),
            200
        );
    }

    public function create_item_permissions_check($request) {
        return $this->verify_api_key() && current_user_can('manage_options');
    }

    public function create_item($request) {
        $download_data = array(
            'title' => sanitize_text_field($request['title']),
            'description' => sanitize_textarea_field($request['description']),
            'status' => sanitize_text_field($request['status']),
            'protection_type' => sanitize_text_field($request['protection_type']),
            'protection_settings' => $request['protection_settings'],
        );

        if (!empty($request['categories'])) {
            $download_data['categories'] = array_map('absint', $request['categories']);
        }

        $download = $this->download_service->create_download($download_data);
        if (!$download) {
            return $this->prepare_error_response(
                'creation_failed',
                __('Failed to create download', 'wp-content-locker')
            );
        }

        return new WP_REST_Response(
            $this->prepare_item_for_response($download, $request),
            201
        );
    }

    public function get_item_permissions_check($request) {
        return $this->verify_api_key();
    }

    public function get_item($request) {
        $download = $this->download_service->get_download($request['id']);
        if (!$download) {
            return $this->prepare_error_response(
                'not_found',
                __('Download not found', 'wp-content-locker'),
                404
            );
        }

        return new WP_REST_Response(
            $this->prepare_item_for_response($download, $request),
            200
        );
    }

    public function update_item_permissions_check($request) {
        return $this->verify_api_key() && current_user_can('manage_options');
    }

    public function update_item($request) {
        $download = $this->download_service->get_download($request['id']);
        if (!$download) {
            return $this->prepare_error_response(
                'not_found',
                __('Download not found', 'wp-content-locker'),
                404
            );
        }

        $download_data = array();
        if (isset($request['title'])) {
            $download_data['title'] = sanitize_text_field($request['title']);
        }
        if (isset($request['description'])) {
            $download_data['description'] = sanitize_textarea_field($request['description']);
        }
        if (isset($request['status'])) {
            $download_data['status'] = sanitize_text_field($request['status']);
        }
        if (isset($request['protection_type'])) {
            $download_data['protection_type'] = sanitize_text_field($request['protection_type']);
        }
        if (isset($request['protection_settings'])) {
            $download_data['protection_settings'] = $request['protection_settings'];
        }
        if (isset($request['categories'])) {
            $download_data['categories'] = array_map('absint', $request['categories']);
        }

        $updated = $this->download_service->update_download($request['id'], $download_data);
        if (!$updated) {
            return $this->prepare_error_response(
                'update_failed',
                __('Failed to update download', 'wp-content-locker')
            );
        }

        return new WP_REST_Response(
            $this->prepare_item_for_response(
                $this->download_service->get_download($request['id']),
                $request
            ),
            200
        );
    }

    public function delete_item_permissions_check($request) {
        return $this->verify_api_key() && current_user_can('manage_options');
    }

    public function delete_item($request) {
        $download = $this->download_service->get_download($request['id']);
        if (!$download) {
            return $this->prepare_error_response(
                'not_found',
                __('Download not found', 'wp-content-locker'),
                404
            );
        }

        $deleted = $this->download_service->delete_download($request['id']);
        if (!$deleted) {
            return $this->prepare_error_response(
                'deletion_failed',
                __('Failed to delete download', 'wp-content-locker')
            );
        }

        return new WP_REST_Response(null, 204);
    }

    protected function prepare_item_for_response($item, $request) {
        return array(
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'status' => $item->status,
            'protection_type' => $item->protection_type,
            'protection_settings' => $item->protection_settings,
            'download_count' => $item->download_count,
            'categories' => $this->get_download_categories($item->id),
            'created_at' => mysql_to_rfc3339($item->created_at),
            'updated_at' => mysql_to_rfc3339($item->updated_at),
        );
    }

    protected function get_download_categories($download_id) {
        return $this->download_service->get_download_categories($download_id);
    }

    public function get_collection_params() {
        return array(
            'page' => array(
                'description' => __('Current page of the collection.', 'wp-content-locker'),
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'description' => __('Maximum number of items to be returned in result set.', 'wp-content-locker'),
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
                'sanitize_callback' => 'absint',
            ),
            'search' => array(
                'description' => __('Limit results to those matching a string.', 'wp-content-locker'),
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'category' => array(
                'description' => __('Limit results to those in a specific category.', 'wp-content-locker'),
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'orderby' => array(
                'description' => __('Sort collection by object attribute.', 'wp-content-locker'),
                'type' => 'string',
                'default' => 'created_at',
                'enum' => array('created_at', 'title', 'download_count'),
            ),
            'order' => array(
                'description' => __('Order sort attribute ascending or descending.', 'wp-content-locker'),
                'type' => 'string',
                'default' => 'DESC',
                'enum' => array('ASC', 'DESC'),
            ),
        );
    }
}