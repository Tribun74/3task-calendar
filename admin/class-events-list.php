<?php
/**
 * ThreeCal Events List Table
 *
 * Extends WP_List_Table for events listing.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ThreeCal_Events_List extends WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'event',
            'plural' => 'events',
            'ajax' => false
        ));
    }

    /**
     * Get columns
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'title' => __('Title', '3task-calendar'),
            'start_date' => __('Start Date', '3task-calendar'),
            'end_date' => __('End Date', '3task-calendar'),
            'location' => __('Location', '3task-calendar'),
            'categories' => __('Categories', '3task-calendar'),
            'status' => __('Status', '3task-calendar')
        );
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return array(
            'title' => array('title', false),
            'start_date' => array('start_date', true),
            'status' => array('status', false)
        );
    }

    /**
     * Prepare items
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- List table sorting/filtering params.
        $args = array(
            'per_page' => $per_page,
            'page'     => $current_page,
            'orderby'  => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'start_date',
            'order'    => isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC',
        );

        // Status filter.
        if ( ! empty( $_GET['status'] ) ) {
            $args['status'] = sanitize_text_field( wp_unslash( $_GET['status'] ) );
        }

        // Category filter.
        if ( ! empty( $_GET['category'] ) ) {
            $args['category_id'] = absint( $_GET['category'] );
        }

        // Search.
        if ( ! empty( $_GET['s'] ) ) {
            $args['search'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $this->items = ThreeCal_Event::get_all($args);
        $total_items = ThreeCal_Event::count($args);

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));

        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns()
        );
    }

    /**
     * Column checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="events[]" value="%d" />',
            $item->id
        );
    }

    /**
     * Column title
     */
    public function column_title($item) {
        $edit_url = add_query_arg(array(
            'page' => '3task-calendar',
            'tab' => 'events',
            'action' => 'edit',
            'event' => $item->id
        ), admin_url('admin.php'));

        $delete_url = wp_nonce_url(
            add_query_arg(array(
                'page' => '3task-calendar',
                'action' => 'delete',
                'id' => $item->id
            ), admin_url('admin.php')),
            'threecal_delete_' . $item->id
        );

        $actions = array(
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($edit_url),
                __('Edit', '3task-calendar')
            ),
            'delete' => sprintf(
                '<a href="%s" class="threecal-delete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($delete_url),
                esc_js(__('Are you sure you want to delete this event?', '3task-calendar')),
                __('Delete', '3task-calendar')
            )
        );

        $color_indicator = sprintf(
            '<span class="threecal-color-indicator" style="background-color: %s;"></span>',
            esc_attr($item->color)
        );

        return $color_indicator . sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            esc_url($edit_url),
            esc_html($item->title),
            $this->row_actions($actions)
        );
    }

    /**
     * Column start date
     */
    public function column_start_date($item) {
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        $output = date_i18n($date_format, strtotime($item->start_date));

        if (!$item->all_day) {
            $output .= '<br><span class="threecal-time">' . date_i18n($time_format, strtotime($item->start_date)) . '</span>';
        } else {
            $output .= '<br><span class="threecal-all-day">' . __('All day', '3task-calendar') . '</span>';
        }

        return $output;
    }

    /**
     * Column end date
     */
    public function column_end_date($item) {
        if (empty($item->end_date)) {
            return '—';
        }

        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        $output = date_i18n($date_format, strtotime($item->end_date));

        if (!$item->all_day) {
            $output .= '<br><span class="threecal-time">' . date_i18n($time_format, strtotime($item->end_date)) . '</span>';
        }

        return $output;
    }

    /**
     * Column location
     */
    public function column_location($item) {
        if (empty($item->location_id)) {
            return '—';
        }

        $location = ThreeCal_Location::get($item->location_id);

        if (!$location) {
            return '—';
        }

        return esc_html($location->name);
    }

    /**
     * Column categories
     */
    public function column_categories($item) {
        $categories = ThreeCal_Event::get_categories($item->id);

        if (empty($categories)) {
            return '—';
        }

        $output = array();
        foreach ($categories as $cat) {
            $output[] = sprintf(
                '<span class="threecal-category-tag" style="background-color: %s;">%s</span>',
                esc_attr($cat->color),
                esc_html($cat->name)
            );
        }

        return implode(' ', $output);
    }

    /**
     * Column status
     */
    public function column_status($item) {
        $statuses = array(
            'draft' => __('Draft', '3task-calendar'),
            'published' => __('Published', '3task-calendar'),
            'cancelled' => __('Cancelled', '3task-calendar')
        );

        $status_class = 'threecal-status-' . $item->status;
        $status_label = isset($statuses[$item->status]) ? $statuses[$item->status] : $item->status;

        return sprintf(
            '<span class="threecal-status %s">%s</span>',
            esc_attr($status_class),
            esc_html($status_label)
        );
    }

    /**
     * Default column
     */
    public function column_default($item, $column_name) {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '';
    }

    /**
     * Get bulk actions
     */
    public function get_bulk_actions() {
        return array(
            'delete' => __('Delete', '3task-calendar'),
            'publish' => __('Publish', '3task-calendar'),
            'draft' => __('Set to Draft', '3task-calendar')
        );
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action() {
        if ( ! isset( $_POST['events'] ) || empty( $_POST['events'] ) ) {
            return;
        }

        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'bulk-events' ) ) {
            return;
        }

        $events = array_map( 'absint', $_POST['events'] );
        $action = $this->current_action();

        switch ($action) {
            case 'delete':
                if (!current_user_can('delete_threecal_events')) {
                    break;
                }

                foreach ($events as $id) {
                    $event = ThreeCal_Event::get($id);
                    if ($event) {
                        $event->delete();
                    }
                }
                break;

            case 'publish':
            case 'draft':
                if (!current_user_can('edit_threecal_events')) {
                    break;
                }

                foreach ($events as $id) {
                    $event = ThreeCal_Event::get($id);
                    if ($event) {
                        $event->status = $action === 'publish' ? 'published' : 'draft';
                        $event->save();
                    }
                }
                break;
        }
    }

    /**
     * Extra table navigation
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        $categories = ThreeCal_Category::get_all();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter param for display.
        $current_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter param for display.
        $current_category = isset( $_GET['category'] ) ? absint( $_GET['category'] ) : 0;
        ?>
        <div class="alignleft actions">
            <select name="status">
                <option value=""><?php esc_html_e('All Statuses', '3task-calendar'); ?></option>
                <option value="published" <?php selected($current_status, 'published'); ?>><?php esc_html_e('Published', '3task-calendar'); ?></option>
                <option value="draft" <?php selected($current_status, 'draft'); ?>><?php esc_html_e('Draft', '3task-calendar'); ?></option>
                <option value="cancelled" <?php selected($current_status, 'cancelled'); ?>><?php esc_html_e('Cancelled', '3task-calendar'); ?></option>
            </select>

            <?php if (!empty($categories)) : ?>
            <select name="category">
                <option value=""><?php esc_html_e('All Categories', '3task-calendar'); ?></option>
                <?php foreach ($categories as $cat) : ?>
                <option value="<?php echo esc_attr($cat->id); ?>" <?php selected($current_category, $cat->id); ?>>
                    <?php echo esc_html($cat->name); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <?php submit_button(__('Filter', '3task-calendar'), '', 'filter_action', false); ?>
        </div>
        <?php
    }

    /**
     * No items message
     */
    public function no_items() {
        esc_html_e('No events found.', '3task-calendar');
    }
}
