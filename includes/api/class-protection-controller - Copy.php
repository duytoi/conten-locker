<?php
class WCL_Protection_Controller extends WCL_API_Controller {
    protected $rest_base = 'protection';
    private $protection_service;

    public function __construct() {
        parent::__construct();
        $this->protection_service = new WCL_Protection_Service();
    }

    public function register_routes() {
        parent::register_routes();

        // Register verify endpoint
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/verify',
            array(
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'verify_protection'),
                    'permission_callback' => array($this, 'verify_protection_permissions_check'),
                    'args' => array(
                        'download_id' => array(
                            'required' => true,
                            'type' => 'integer',
                            'validate_callback' => function($param) {
                                return is_numeric($param);
                            }
                        ),
                        'protection_data' => array(
                            'required' => true,
                            'type' => 'object',
                        ),
                    ),
                ),
            )
        );
    }

    public function verify_protection_permissions_check($request) {
        return true; // Allow public access for verification
    }

    public function verify_protection($request) {
        $download_id = $request['download_id'];
        $protection_data = $request['protection_data'];

        $result = $this->protection_service->verify_protection(
            $download_id,
            $protection_data
        );

        if (is_wp_error($result)) {
            return $this->prepare_error_response(
                'verification_failed',
                $result->get_error_message()
            );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'download_url' => $result['download_url'],
                'expires_at' => isset($result['expires_at']) ? 
                    mysql_to_rfc3339($result['expires_at']) : null
            ),
            200
        );
    }

    public function get_items($request) {
        $protections = $this->protection_service->get_protections(array(
            'download_id' => $request['download_id'] ?? null
        ));

        $response = array();
        foreach ($protections as $protection) {
            $response[] = $this->prepare_item_for_response($protection, $request);
        }

        return new WP_REST_Response($response, 200);
    }

    protected function prepare_item_for_response($item, $request) {
        return array(
            'id' => $item->id,
            'download_id' => $item->download_id,
            'type' => $item->type,
            'settings' => $item->settings,
            'created_at' => mysql_to_rfc3339($item->created_at),
            'updated_at' => mysql_to_rfc3339($item->updated_at),
        );
    }
}