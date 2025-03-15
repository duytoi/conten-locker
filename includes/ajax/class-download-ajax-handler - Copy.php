<?php
class WCL_Download_Ajax_Handler {
    
    private $download_service;

    public function __construct() {
        $this->download_service = new WCL_Download_Service();
        
        // Register Ajax actions
        add_action('wp_ajax_wcl_handle_download', [$this, 'handle_download_action']);
    }

    public function handle_download_action() {
        check_ajax_referer('wcl_download_nonce', 'nonce');

        $operation = isset($_POST['operation']) ? sanitize_text_field($_POST['operation']) : '';
        $ids = isset($_POST['ids']) ? array_map('intval', (array)$_POST['ids']) : [];

        if (empty($operation) || empty($ids)) {
            wp_send_json_error([
                'message' => __('Invalid parameters', 'wp-content-locker')
            ]);
        }

        try {
            $response = ['success' => false];

            switch ($operation) {
                case 'delete':
                    $deleted = $this->download_service->delete_multiple($ids);
                    $response = [
                        'success' => true,
                        'message' => sprintf(__('%d item(s) deleted successfully', 'wp-content-locker'), $deleted),
                        'affected' => $deleted
                    ];
                    break;

                case 'activate':
                case 'deactivate':
                    $status = ($operation === 'activate') ? 'active' : 'inactive';
                    $updated = $this->download_service->update_status_multiple($ids, $status);
                    $response = [
                        'success' => true,
                        'message' => sprintf(__('%d item(s) %s successfully', 'wp-content-locker'), $updated, $operation . 'd'),
                        'affected' => $updated
                    ];
                    break;

                case 'save':
                    $data = [
                        'title' => sanitize_text_field($_POST['title']),
                        'category_id' => intval($_POST['category_id']),
                        'status' => sanitize_text_field($_POST['status'])
                    ];

                    if (isset($_POST['id'])) {
                        $id = intval($_POST['id']);
                        $result = $this->download_service->update($id, $data);
                    } else {
                        $result = $this->download_service->create($data);
                    }

                    if ($result) {
                        $response = [
                            'success' => true,
                            'message' => isset($_POST['id']) 
                                ? __('Item updated successfully', 'wp-content-locker')
                                : __('Item created successfully', 'wp-content-locker'),
                            'data' => $result
                        ];
                    }
                    break;
            }

            if (!$response['success']) {
                throw new Exception(__('Operation failed', 'wp-content-locker'));
            }

            wp_send_json($response);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
}