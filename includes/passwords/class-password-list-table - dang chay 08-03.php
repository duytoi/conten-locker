<?php
namespace WP_Content_Locker\Includes\Passwords;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Password_List_Table extends \WP_List_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'password',
            'plural'   => 'passwords',
            'ajax'     => false
        ]);
    }

    /**
     * Define table columns
     */
    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'password'    => __('Password', 'wp-content-locker'),
            'status'      => __('Status', 'wp-content-locker'),
            'created_at'  => __('Created', 'wp-content-locker'),
            'used_at'     => __('Used At', 'wp-content-locker'),
            'used_by'     => __('Used By', 'wp-content-locker'),
            'download_id' => __('Download', 'wp-content-locker'),
            'expires_at'  => __('Expires', 'wp-content-locker')
        ];
    }

    /**
     * Define sortable columns
     */
    public function get_sortable_columns() {
        return [
            'created_at'  => ['created_at', true],
            'status'      => ['status', false],
            'used_at'     => ['used_at', false],
            'expires_at'  => ['expires_at', false]
        ];
    }

    /**
     * Prepare table items
     */
    public function prepare_items() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wcl_passwords';

    // Items per page
    $per_page = $this->get_items_per_page('passwords_per_page', 20);
    $current_page = $this->get_pagenum();

    // Build query conditions
    $where = [];
    $values = [];

    // Handle search
    if (!empty($_REQUEST['s'])) {
        $search = wp_unslash(trim($_REQUEST['s']));
        $where[] = 'p.password LIKE %s';
        $values[] = '%' . $wpdb->esc_like($search) . '%';
    }

    // Handle status filter
    if (!empty($_REQUEST['filter_status'])) {
        $status = sanitize_text_field($_REQUEST['filter_status']);
        $where[] = 'p.status = %s';
        $values[] = $status;
    }

    // Build WHERE clause
    $where_clause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

    // Count total items
    $count_query = "SELECT COUNT(*) FROM $table_name p $where_clause";
    if (!empty($values)) {
        $total_items = $wpdb->get_var($wpdb->prepare($count_query, $values));
    } else {
        $total_items = $wpdb->get_var($count_query);
    }

    // Order parameters
    $valid_orderby = ['created_at', 'status', 'used_at', 'expires_at'];
    $orderby = isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], $valid_orderby) 
        ? $_REQUEST['orderby'] 
        : 'created_at';
    
    $order = isset($_REQUEST['order']) && in_array(strtoupper($_REQUEST['order']), ['ASC', 'DESC'])
        ? strtoupper($_REQUEST['order'])
        : 'DESC';

    // Build the main query
    $query = "SELECT p.*, 
            COALESCE(u.display_name, '') as user_name,
            COALESCE(d.title, '') as download_title
        FROM $table_name p
        LEFT JOIN {$wpdb->users} u ON p.used_by = u.ID
        LEFT JOIN {$wpdb->prefix}wcl_downloads d ON p.download_id = d.id
        $where_clause";

    // Add ordering
    $query .= " ORDER BY p.$orderby $order";

    // Add limit
    $query .= " LIMIT %d OFFSET %d";

    // Add pagination values to the existing values array
    $values[] = $per_page;
    $values[] = ($current_page - 1) * $per_page;

    // Execute the final query
    $this->items = $wpdb->get_results(
        $wpdb->prepare($query, $values),
        ARRAY_A
    );

    // Set up pagination
    $this->set_pagination_args([
        'total_items' => $total_items,
        'per_page'    => $per_page,
        'total_pages' => ceil($total_items / $per_page)
    ]);

    // Set up columns
    $this->_column_headers = [
        $this->get_columns(),
        [], // Hidden columns
        $this->get_sortable_columns()
    ];
}

    /**
     * Render column content
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'password':
                return sprintf(
                    '<strong>%s</strong>
                     <div class="row-actions">
                        <span class="edit"><a href="%s">Edit</a> | </span>
                        <span class="delete"><a href="%s" class="submitdelete">Delete</a></span>
                     </div>',
                    esc_html($item['password']),
                    esc_url(admin_url('admin.php?page=wcl-passwords&action=edit&id=' . $item['id'])),
                    esc_url(wp_nonce_url(admin_url('admin.php?page=wcl-passwords&action=delete&id=' . $item['id']), 'delete_password_' . $item['id']))
                );
            
            case 'status':
                return $this->get_status_badge($item['status']);
            
            case 'created_at':
            case 'used_at':
            case 'expires_at':
                return !empty($item[$column_name]) 
                    ? wp_date(
                        get_option('date_format') . ' ' . get_option('time_format'), 
                        strtotime($item[$column_name])
                    ) 
                    : '—';
            
            case 'used_by':
                if (!empty($item['user_name'])) {
                    return sprintf(
                        '<a href="%s">%s</a>',
                        esc_url(admin_url('user-edit.php?user_id=' . $item['user_id'])),
                        esc_html($item['user_name'])
                    );
                }
                return '—';
            
            case 'download_id':
                if (!empty($item['download_title'])) {
                    return sprintf(
                        '<a href="%s">%s</a>',
                        esc_url(admin_url('admin.php?page=wcl-downloads&action=edit&id=' . $item['download_id'])),
                        esc_html($item['download_title'])
                    );
                }
                return '—';
            
            default:
                return print_r($item, true);
        }
    }

	//Xử lý xóa sửa đơn lẻ password
	public function column_password($item) {
    $actions = array(
        'edit'      => sprintf(
            '<a href="%s">%s</a>',
            esc_url(add_query_arg(
                array(
                    'page' => 'wcl-passwords',
                    'action' => 'edit',
                    'password_id' => $item['id'],
                    '_wpnonce' => wp_create_nonce('edit_password_' . $item['id'])
                ),
                admin_url('admin.php')
            )),
            __('Edit', 'wp-content-locker')
        ),
        'delete'    => sprintf(
            '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
            esc_url(add_query_arg(
                array(
                    'page' => 'wcl-passwords',
                    'action' => 'delete',
                    'password_id' => $item['id'],
                    '_wpnonce' => wp_create_nonce('delete_password_' . $item['id'])
                ),
                admin_url('admin.php')
            )),
            __('Are you sure you want to delete this password?', 'wp-content-locker'),
            __('Delete', 'wp-content-locker')
        ),
    );

    return sprintf('%s %s',
        $item['password'],
        $this->row_actions($actions)
    );
}
    /**
     * Get status badge HTML
     */
    private function get_status_badge($status) {
        $badges = [
            'unused' => '<span class="badge badge-success">%s</span>',
            'using'  => '<span class="badge badge-warning">%s</span>',
            'used'   => '<span class="badge badge-secondary">%s</span>'
        ];

        $status_labels = [
            'unused' => __('Unused', 'wp-content-locker'),
            'using'  => __('In Use', 'wp-content-locker'),
            'used'   => __('Used', 'wp-content-locker')
        ];

        return isset($badges[$status]) 
            ? sprintf($badges[$status], $status_labels[$status]) 
            : esc_html($status);
    }

    /**
     * Checkbox column
     */
    public function column_cb($item) {
    return sprintf(
        '<input type="checkbox" name="passwords[]" value="%s" />', 
        $item['id']
    );
}

    /**
     * Bulk actions
     */
    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'wp-content-locker'),
            'reset'  => __('Reset Status', 'wp-content-locker'),
            'export' => __('Export', 'wp-content-locker')
        ];
    }
	public function current_action() {
    if (isset($_POST['action']) && -1 != $_POST['action']) {
        return $_POST['action'];
    }
    
    if (isset($_POST['action2']) && -1 != $_POST['action2']) {
        return $_POST['action2'];
    }
    
    return false;
}
    /**
     * Extra table navigation
     */
    protected function extra_tablenav($which) {
        if ($which === 'top'): ?>
            <div class="alignleft actions">
                <select name="filter_status">
                    <option value=""><?php _e('All statuses', 'wp-content-locker'); ?></option>
                    <option value="unused" <?php selected(isset($_GET['filter_status']) ? $_GET['filter_status'] : '', 'unused'); ?>>
                        <?php _e('Unused', 'wp-content-locker'); ?>
                    </option>
                    <option value="using" <?php selected(isset($_GET['filter_status']) ? $_GET['filter_status'] : '', 'using'); ?>>
                        <?php _e('In Use', 'wp-content-locker'); ?>
                    </option>
                    <option value="used" <?php selected(isset($_GET['filter_status']) ? $_GET['filter_status'] : '', 'used'); ?>>
                        <?php _e('Used', 'wp-content-locker'); ?>
                    </option>
                </select>
                <?php 
                submit_button(
                    __('Filter', 'wp-content-locker'), 
                    '', 
                    'filter_action', 
                    false
                ); 
                ?>
            </div>
        <?php endif;
    }
}