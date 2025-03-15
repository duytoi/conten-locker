<?php
namespace WP_Content_Locker\Admin\Views\Downloads;

if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

use WP_Content_Locker\Includes\Services\Download_Service;

class Download_List_Table extends \WP_List_Table {
    private $download_service;
    private $items_per_page = 20;

    public function __construct() {
        parent::__construct([
            'singular' => 'download',
            'plural'   => 'downloads',
            'ajax'     => false
        ]);
        
        $this->download_service = new Download_Service();
    }

    public function prepare_items() {
        $this->_column_headers = array(
            $this->get_columns(),        // columns
            array(),                     // hidden columns
            $this->get_sortable_columns(), // sortable columns
            'title'                      // primary column
        );

        $this->process_bulk_action();

        $search = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        $args = array(
            'per_page' => $this->items_per_page,
            'paged' => $this->get_pagenum(),
            'orderby' => isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'created_at',
            'order' => isset($_REQUEST['order']) ? $_REQUEST['order'] : 'DESC',
            'search' => $search
        );

        $result = $this->download_service->get_downloads($args);
        
        $this->items = $result['items'];
        
        $this->set_pagination_args([
            'total_items' => $result['total'],
            'per_page'    => $this->items_per_page,
            'total_pages' => ceil($result['total'] / $this->items_per_page)
        ]);
    }

    public function no_items() {
        _e('No downloads found.', 'wp-content-locker');
    }

    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'title'         => __('Title', 'wp-content-locker'),
			'shortcode' => __('Shortcode', 'wp-content-locker'),
            'file_type'     => __('File Type', 'wp-content-locker'),
            'download_count' => __('Downloads', 'wp-content-locker'),
            'status'        => __('Status', 'wp-content-locker'),
            'created_at'    => __('Created', 'wp-content-locker')
        ];
    }
	
	public function column_shortcode($item) {
    if (empty($item->shortcode)) {
        return '—';
    }
    
    return sprintf(
        '<div class="wcl-shortcode-wrap">
            <code>%s</code>
            <button class="button button-small wcl-copy-shortcode" 
                    data-shortcode="%s">
                <span class="dashicons dashicons-clipboard"></span>
            </button>
        </div>',
        esc_html($item->shortcode),
        esc_attr($item->shortcode)
    );
}
	
    protected function get_sortable_columns() {
        return [
            'title'         => ['title', true],
            'file_type'     => ['file_type', true],
            'download_count' => ['download_count', true],
            'status'        => ['status', true],
            'created_at'    => ['created_at', true]
        ];
    }

    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'wp-content-locker')
        ];
    }

    protected function column_default($item, $column_name) {
    if (!$item) return '';
    
    switch ($column_name) {
        case 'file_type':
            return !empty($item->file_type) ? esc_html(strtoupper($item->file_type)) : '';
        case 'download_count':
            return number_format_i18n($item->download_count);
        case 'status':
            return $this->column_status($item);
        case 'created_at':
            return mysql2date(get_option('date_format'), $item->created_at);
        default:
            return print_r($item, true);
    }
}

    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%s" />', 
            $item->id
        );
    }

    protected function column_title($item) {
        if (!$item) return '';

        $edit_link = admin_url(sprintf('admin.php?page=wcl-downloads&action=edit&id=%d', $item->id));
        
        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($edit_link),
                __('Edit', 'wp-content-locker')
            ),
            'delete' => sprintf(
                '<a href="#" class="delete-item" data-id="%d">%s</a>',
                $item->id,
                __('Delete', 'wp-content-locker')
            )
        ];

        return sprintf(
            '<strong><a class="row-title" href="%s">%s</a></strong> %s',
            esc_url($edit_link),
            esc_html($item->title),
            $this->row_actions($actions)
        );
    }

    protected function column_status($item) {
        if (!$item) return '';

        $status_classes = [
            'active' => 'status-active',
            'inactive' => 'status-inactive'
        ];

        $class = isset($status_classes[$item->status]) ? $status_classes[$item->status] : '';
        
        return sprintf(
            '<span class="download-status %s">%s</span>',
            esc_attr($class),
            esc_html(ucfirst($item->status))
        );
    }

    protected function process_bulk_action() {
        if ('delete' === $this->current_action()) {
            if (!isset($_REQUEST['_wpnonce'])) return;

            $nonce = esc_attr($_REQUEST['_wpnonce']);
            if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                wp_die('Security check failed!');
            }

            $ids = isset($_REQUEST['bulk-delete']) ? $_REQUEST['bulk-delete'] : array();
            if (!empty($ids)) {
                try {
                    $count = $this->download_service->delete_multiple($ids);
                    wp_safe_redirect(add_query_arg(
                        array(
                            'page' => 'wcl-downloads',
                            'message' => 'deleted',
                            'count' => $count
                        ),
                        admin_url('admin.php')
                    ));
                    exit;
                } catch (\Exception $e) {
                    wp_die($e->getMessage());
                }
            }
        }
    }

    public function display() {
        $singular = $this->_args['singular'];

        $this->display_tablenav('top');

        $this->screen->render_screen_reader_content('heading_list');
        ?>
        <table class="wp-list-table <?php echo implode(' ', $this->get_table_classes()); ?>">
            <thead>
            <tr>
                <?php $this->print_column_headers(); ?>
            </tr>
            </thead>

            <tbody id="the-list"
                <?php
                if ($singular) {
                    echo " data-wp-lists='list:$singular'";
                }
                ?>
            >
            <?php $this->display_rows_or_placeholder(); ?>
            </tbody>

            <tfoot>
            <tr>
                <?php $this->print_column_headers(false); ?>
            </tr>
            </tfoot>

        </table>
        <?php
        $this->display_tablenav('bottom');
    }
}