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
            'cb'            => '<input type="checkbox" />',
            'title'         => __('Title', 'wp-content-locker'),
            'category'      => __('Category', 'wp-content-locker'),
            'download_count'=> __('Downloads', 'wp-content-locker'),
            'protection'    => __('Protection', 'wp-content-locker'),
            'status'        => __('Status', 'wp-content-locker'),
            'created_at'    => __('Created', 'wp-content-locker')
        ];
    }

    public function get_sortable_columns() {
        return [
            'title'         => ['title', true],
            'download_count'=> ['download_count', false],
            'status'        => ['status', false],
            'created_at'    => ['created_at', true]
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcl_downloads';

        // Items per page
        $per_page = $this->get_items_per_page('downloads_per_page', 20);
        $current_page = $this->get_pagenum();

        // Build query conditions
        $where = [];
        $values = [];

        // Handle search
        if (!empty($_REQUEST['s'])) {
            $search = wp_unslash(trim($_REQUEST['s']));
            $where[] = 'd.title LIKE %s';
            $values[] = '%' . $wpdb->esc_like($search) . '%';
        }

        // Handle category filter
        if (!empty($_REQUEST['filter_category'])) {
            $category_id = intval($_REQUEST['filter_category']);
            $where[] = 'd.category_id = %d';
            $values[] = $category_id;
        }

        // Build WHERE clause
        $where_clause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

        // Count total items
        $count_query = "SELECT COUNT(*) FROM $table_name d $where_clause";
        $total_items = !empty($values) 
            ? $wpdb->get_var($wpdb->prepare($count_query, $values))
            : $wpdb->get_var($count_query);

        // Order parameters
        $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'created_at';
        $order = isset($_REQUEST['order']) ? strtoupper($_REQUEST['order']) : 'DESC';

        // Main query
        $query = "SELECT d.*, 
                c.name as category_name,
                COALESCE(p.protection_type, 'none') as protection_type
            FROM $table_name d
            LEFT JOIN {$wpdb->prefix}wcl_categories c ON d.category_id = c.id
            LEFT JOIN {$wpdb->prefix}wcl_protections p ON d.id = p.content_id
            $where_clause
            ORDER BY d.$orderby $order
            LIMIT %d OFFSET %d";

        // Add pagination values
        array_push($values, $per_page, ($current_page - 1) * $per_page);

        // Get items
        $this->items = $wpdb->get_results($wpdb->prepare($query, $values));

        // Setup pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
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
            case 'created_at':
                return wp_date(get_option('date_format'), strtotime($item->created_at));
            
            case 'download_count':
                return number_format_i18n($item->download_count);
            
            case 'category':
                return $item->category_name ? esc_html($item->category_name) : '—';
            
            case 'protection':
                return $this->get_protection_badge($item->protection_type);
            
            case 'status':
                return $this->get_status_badge($item->status);
            
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
                wp_nonce_url(admin_url("admin.php?page=wcl-downloads&action=delete&id={$item->id}"), 
                'delete_download_' . $item->id),
                __('Are you sure you want to delete this download?', 'wp-content-locker'),
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

    private function get_protection_badge($type) {
        $badges = [
            'none' => '<span class="badge badge-secondary">%s</span>',
            'password' => '<span class="badge badge-primary">%s</span>',
            'countdown' => '<span class="badge badge-info">%s</span>',
            'dual' => '<span class="badge badge-warning">%s</span>'
        ];

        $labels = [
            'none' => __('None', 'wp-content-locker'),
            'password' => __('Password', 'wp-content-locker'),
            'countdown' => __('Countdown', 'wp-content-locker'),
            'dual' => __('Dual', 'wp-content-locker')
        ];

        return isset($badges[$type]) 
            ? sprintf($badges[$type], $labels[$type]) 
            : esc_html($type);
    }

    private function get_status_badge($status) {
        $badges = [
            'active' => '<span class="badge badge-success">%s</span>',
            'inactive' => '<span class="badge badge-danger">%s</span>',
            'expired' => '<span class="badge badge-warning">%s</span>'
        ];

        $labels = [
            'active' => __('Active', 'wp-content-locker'),
            'inactive' => __('Inactive', 'wp-content-locker'),
            'expired' => __('Expired', 'wp-content-locker')
        ];

        return isset($badges[$status]) 
            ? sprintf($badges[$status], $labels[$status]) 
            : esc_html($status);
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="downloads[]" value="%s" />', 
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

    protected function extra_tablenav($which) {
        if ($which === 'top') {
            global $wpdb;
            $categories = $wpdb->get_results("
                SELECT id, name 
                FROM {$wpdb->prefix}wcl_categories 
                WHERE status = 'active' 
                ORDER BY name ASC
            ");
            ?>
            <div class="alignleft actions">
                <select name="filter_category">
                    <option value=""><?php _e('All Categories', 'wp-content-locker'); ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo esc_attr($category->id); ?>" 
                                <?php selected(isset($_REQUEST['filter_category']) ? $_REQUEST['filter_category'] : '', $category->id); ?>>
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Filter', 'wp-content-locker'), '', 'filter_action', false); ?>
            </div>
            <?php
        }
    }
}