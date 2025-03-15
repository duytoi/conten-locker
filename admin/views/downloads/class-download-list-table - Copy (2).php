<?php
namespace WP_Content_Locker\Admin\Views\Downloads;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Download_List_Table extends \WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => 'download',
            'plural'   => 'downloads',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'title' => __('Title', 'wp-content-locker'),
            'category' => __('Category', 'wp-content-locker'),
            'shortcode' => __('Shortcode', 'wp-content-locker'),
            'password' => __('Password', 'wp-content-locker'),
            'status' => __('Status', 'wp-content-locker'),
            'created_at' => __('Created', 'wp-content-locker')
        ];
    }

    public function get_sortable_columns() {
        return [
            'title' => ['title', true],
            'category' => ['category', false],
            'status' => ['status', false],
            'created_at' => ['created_at', true]
        ];
    }

    public function prepare_items() {
    global $wpdb;
    
    // Items per page
    $per_page = $this->get_items_per_page('downloads_per_page', 20);
    $current_page = $this->get_pagenum();

    // Base query - chỉ lấy từ bảng downloads
    $base_query = "FROM {$wpdb->prefix}wcl_downloads d";

    // Where conditions
    $where = ["1=1"]; // Luôn true để dễ thêm điều kiện
    $values = [];

    // Search
    if (!empty($_REQUEST['s'])) {
        $search = '%' . $wpdb->esc_like($_REQUEST['s']) . '%';
        $where[] = 'd.title LIKE %s';
        $values[] = $search;
    }

    // Category filter
    if (!empty($_REQUEST['category'])) {
        $where[] = 'd.category_id = %d';
        $values[] = intval($_REQUEST['category']);
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where);

    // Count total items
    $total_items = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) {$base_query} {$where_clause}",
            $values
        )
    );

    // Order
    $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'created_at';
    $order = isset($_REQUEST['order']) ? strtoupper($_REQUEST['order']) : 'DESC';

    // Validate orderby
    $allowed_orderby = ['title', 'status', 'created_at'];
    if (!in_array($orderby, $allowed_orderby)) {
        $orderby = 'created_at';
    }

    // Validate order
    if (!in_array($order, ['ASC', 'DESC'])) {
        $order = 'DESC';
    }

    // Main query
    $sql = "SELECT d.* 
            {$base_query}
            {$where_clause}
            ORDER BY d.{$orderby} {$order}
            LIMIT %d OFFSET %d";

    // Add limit and offset to values array
    $values[] = $per_page;
    $values[] = ($current_page - 1) * $per_page;

    // Debug query
    // echo $wpdb->prepare($sql, $values);

    $this->items = $wpdb->get_results(
        $wpdb->prepare($sql, $values)
    );

    // Setup pagination
    $this->set_pagination_args([
        'total_items' => $total_items,
        'per_page' => $per_page,
        'total_pages' => ceil($total_items / $per_page)
    ]);

    $this->_column_headers = [
        $this->get_columns(),
        [], // Hidden columns
        $this->get_sortable_columns()
    ];
}

    public function column_default($item, $column_name) {
        switch($column_name) {
            case 'category':
                return !empty($item->category_name) ? esc_html($item->category_name) : '—';
                
            case 'shortcode':
                return sprintf('<code>[wcl_download id="%d"]</code>', $item->id);
                
            case 'password':
                return !empty($item->password) ? 
                    sprintf('<span class="password-status-%s">%s</span>', 
                        esc_attr($item->password_status),
                        esc_html($item->password)
                    ) : '—';
                
            case 'status':
                return $this->get_status_badge($item->status);
                
            case 'created_at':
                return wp_date(get_option('date_format'), strtotime($item->created_at));
                
            default:
                return isset($item->$column_name) ? esc_html($item->$column_name) : '';
        }
    }

    public function column_title($item) {
        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url("admin.php?page=wcl-downloads&action=edit&id={$item->id}")),
                __('Edit', 'wp-content-locker')
            ),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
                wp_nonce_url(
                    admin_url("admin.php?page=wcl-downloads&action=delete&download_id={$item->id}"),
                    'delete_download_' . $item->id
                ),
                __('Are you sure you want to delete this item?', 'wp-content-locker'),
                __('Delete', 'wp-content-locker')
            )
        ];

        return sprintf(
            '<strong><a href="%1$s">%2$s</a></strong> %3$s',
            esc_url(admin_url("admin.php?page=wcl-downloads&action=edit&id={$item->id}")),
            esc_html($item->title),
            $this->row_actions($actions)
        );
    }

    private function get_status_badge($status) {
        $badges = [
            'active' => '<span class="badge badge-success">%s</span>',
            'inactive' => '<span class="badge badge-warning">%s</span>',
            'draft' => '<span class="badge badge-secondary">%s</span>'
        ];

        $labels = [
            'active' => __('Active', 'wp-content-locker'),
            'inactive' => __('Inactive', 'wp-content-locker'),
            'draft' => __('Draft', 'wp-content-locker')
        ];

        return isset($badges[$status]) ? 
            sprintf($badges[$status], $labels[$status]) : 
            esc_html($status);
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="download[]" value="%s" />', 
            $item->id
        );
    }

    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'wp-content-locker'),
            'activate' => __('Activate', 'wp-content-locker'),
            'deactivate' => __('Deactivate', 'wp-content-locker')
        ];
    }
}