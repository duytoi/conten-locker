<?php
// includes/api/class-protection-controller.php

namespace WP_Content_Locker\Includes\Api;

use WP_Content_Locker\Includes\Models\Protection;

class WCL_Protection_Controller extends WCL_API_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->rest_base = 'protection';
    }

    // CRUD Operations Implementation
    public function get_items($request) {
        try {
            $items = $this->protection_service->get_all_protections();
            
            return $this->prepare_response([
                'success' => true,
                'data' => $items
            ]);
        } catch (\Exception $e) {
            return $this->prepare_error('fetch_error', $e->getMessage());
        }
    }

    public function get_item($request) {
        try {
            $id = $request['id'];
            $item = $this->protection_service->get_protection($id);
            
            if (!$item) {
                return $this->prepare_error('not_found', 'Protection not found', 404);
            }

            return $this->prepare_response([
                'success' => true,
                'data' => $item
            ]);
        } catch (\Exception $e) {
            return $this->prepare_error('fetch_error', $e->getMessage());
        }
    }

    public function create_item($request) {
        try {
            $params = $request->get_params();
            
            $protection = new Protection();
            $protection->set_countdown_mode($params['countdown_mode'] ?? 'single');
            $protection->set_countdown_first($params['countdown_first'] ?? 60);
            $protection->set_countdown_second($params['countdown_second'] ?? 60);
            $protection->set_first_message($params['first_message'] ?? '');
            $protection->set_second_message($params['second_message'] ?? '');
            $protection->set_redirect_message($params['redirect_message'] ?? '');
            $protection->set_requires_ga($params['requires_ga'] ?? 0);

            $created = $this->protection_service->create_protection($protection);

            return $this->prepare_response([
                'success' => true,
                'data' => $created
            ], 201);
        } catch (\Exception $e) {
            return $this->prepare_error('creation_error', $e->getMessage());
        }
    }

    public function update_item($request) {
        try {
            $id = $request['id'];
            $params = $request->get_params();
            
            $protection = new Protection();
            $protection->set_id($id);
            $protection->set_countdown_mode($params['countdown_mode'] ?? 'single');
            $protection->set_countdown_first($params['countdown_first'] ?? 60);
            $protection->set_countdown_second($params['countdown_second'] ?? 60);
            $protection->set_first_message($params['first_message'] ?? '');
            $protection->set_second_message($params['second_message'] ?? '');
            $protection->set_redirect_message($params['redirect_message'] ?? '');
            $protection->set_requires_ga($params['requires_ga'] ?? 0);

            $updated = $this->protection_service->update_protection($protection);

            return $this->prepare_response([
                'success' => true,
                'data' => $updated
            ]);
        } catch (\Exception $e) {
            return $this->prepare_error('update_error', $e->getMessage());
        }
    }

    public function delete_item($request) {
        try {
            $id = $request['id'];
            $deleted = $this->protection_service->delete_protection($id);

            return $this->prepare_response([
                'success' => $deleted
            ]);
        } catch (\Exception $e) {
            return $this->prepare_error('deletion_error', $e->getMessage());
        }
    }

    // Permission Checks
    public function get_items_permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function get_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function create_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function update_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function delete_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    // Additional custom methods for Protection specific functionality
    public function handle_save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wcl'));
        }

        if (!wp_verify_nonce($_POST['wcl_protection_nonce'], 'wcl_protection_settings')) {
            wp_die(__('Invalid nonce specified', 'wcl'));
        }

        $settings = array(
            'countdown_mode' => isset($_POST['countdown_mode']) ? sanitize_text_field($_POST['countdown_mode']) : 'single',
            'countdown_first' => isset($_POST['countdown_first']) ? absint($_POST['countdown_first']) : 60,
            'countdown_second' => isset($_POST['countdown_second']) ? absint($_POST['countdown_second']) : 60,
            'first_message' => isset($_POST['first_message']) ? wp_kses_post($_POST['first_message']) : '',
            'second_message' => isset($_POST['second_message']) ? wp_kses_post($_POST['second_message']) : '',
            'redirect_message' => isset($_POST['redirect_message']) ? wp_kses_post($_POST['redirect_message']) : '',
            'requires_ga' => isset($_POST['requires_ga']) ? 1 : 0
        );

        $updated = $this->protection_service->update_settings($settings);

        wp_redirect(add_query_arg(
            array(
                'page' => 'wcl-protection-settings',
                'updated' => $updated ? 'true' : 'false'
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    public function admin_notices() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wcl-protection-settings') {
            return;
        }

        if (isset($_GET['updated'])) {
            $message = $_GET['updated'] === 'true' 
                ? __('Settings saved successfully!', 'wcl')
                : __('Error saving settings.', 'wcl');
            $class = $_GET['updated'] === 'true' ? 'success' : 'error';
            
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($class),
                esc_html($message)
            );
        }
    }
}