<?php
/**
 * ThreeCal Admin
 *
 * Handles all admin functionality.
 *
 * @package ThreeCal
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Class
 *
 * @since 1.0.0
 */
class ThreeCal_Admin {

    /**
     * Plugin name.
     *
     * @var string
     */
    private $plugin_name;

    /**
     * Version.
     *
     * @var string
     */
    private $version;

    /**
     * Current tab.
     *
     * @var string
     */
    private $current_tab = 'dashboard';

    /**
     * Constructor.
     *
     * @param string $plugin_name Plugin name.
     * @param string $version     Version.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reading for tab display
        $this->current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_styles($hook) {
        if (strpos($hook, '3task-calendar') === false) {
            return;
        }

        wp_enqueue_style('wp-color-picker');

        wp_enqueue_style(
            $this->plugin_name . '-admin',
            THREECAL_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            $this->version
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, '3task-calendar') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script('wp-color-picker');

        wp_enqueue_script(
            $this->plugin_name . '-admin',
            THREECAL_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'wp-color-picker'),
            $this->version,
            true
        );

        wp_localize_script($this->plugin_name . '-admin', 'threecal_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'admin_url' => admin_url(),
            'nonce' => wp_create_nonce('threecal_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', '3task-calendar'),
                'confirm_delete_multiple' => __('Are you sure you want to delete the selected items?', '3task-calendar'),
                'select_image' => __('Select Image', '3task-calendar'),
                'use_image' => __('Use Image', '3task-calendar'),
                'saving' => __('Saving...', '3task-calendar'),
                'saved' => __('Saved!', '3task-calendar'),
                'error' => __('An error occurred.', '3task-calendar')
            )
        ));
    }

    /**
     * Add menu pages.
     */
    public function add_menu_pages() {
        add_menu_page(
            __( '3task Calendar', '3task-calendar' ),
            __( '3task Calendar', '3task-calendar' ),
            'edit_posts',
            '3task-calendar',
            array( $this, 'render_admin_page' ),
            'dashicons-calendar-alt',
            26
        );
    }

    /**
     * Get available tabs.
     *
     * @return array Tabs configuration.
     */
    private function get_tabs() {
        return array(
            'dashboard' => array(
                'title' => __( 'Dashboard', '3task-calendar' ),
                'icon'  => 'dashicons-dashboard',
            ),
            'events' => array(
                'title' => __( 'Events', '3task-calendar' ),
                'icon'  => 'dashicons-calendar-alt',
            ),
            'categories' => array(
                'title' => __( 'Categories', '3task-calendar' ),
                'icon'  => 'dashicons-category',
            ),
            'locations' => array(
                'title' => __( 'Locations', '3task-calendar' ),
                'icon'  => 'dashicons-location',
            ),
            'settings' => array(
                'title' => __( 'Settings', '3task-calendar' ),
                'icon'  => 'dashicons-admin-settings',
            ),
            'help' => array(
                'title' => __( 'Help', '3task-calendar' ),
                'icon'  => 'dashicons-editor-help',
            ),
        );
    }

    /**
     * Render main admin page with tabs.
     */
    public function render_admin_page() {
        $tabs = $this->get_tabs();
        ?>
        <div class="wrap threecal-wrap">
            <!-- Header -->
            <div class="threecal-header">
                <div class="threecal-header-left">
                    <span class="dashicons dashicons-calendar-alt threecal-logo-icon"></span>
                    <h1 class="threecal-admin-title"><?php esc_html_e( '3task Calendar', '3task-calendar' ); ?></h1>
                    <span class="threecal-version"><?php echo esc_html( 'v' . THREECAL_VERSION ); ?></span>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <nav class="threecal-tabs nav-tab-wrapper">
                <?php foreach ( $tabs as $tab_key => $tab ) : ?>
                    <?php
                    $active = ( $this->current_tab === $tab_key ) ? 'nav-tab-active' : '';
                    $url    = admin_url( 'admin.php?page=3task-calendar&tab=' . $tab_key );
                    $class  = isset( $tab['class'] ) ? $tab['class'] : '';
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo esc_attr( $active . ' ' . $class ); ?>">
                        <?php echo esc_html( $tab['title'] ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Content -->
            <div class="threecal-tab-content">
                <?php $this->render_tab_content(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get upgrade URL.
     *
     * @return string Upgrade URL.
     */
    public function get_upgrade_url() {
        return 'https://3task.de/threecal-pro/';
    }

    /**
     * Render tab content.
     */
    private function render_tab_content() {
        switch ( $this->current_tab ) {
            case 'dashboard':
                $this->render_dashboard();
                break;
            case 'events':
                $this->render_events();
                break;
            case 'categories':
                $this->render_categories();
                break;
            case 'locations':
                $this->render_locations();
                break;
            case 'settings':
                $this->render_settings();
                break;
            case 'help':
                $this->render_help();
                break;
            default:
                $this->render_dashboard();
        }
    }

    /**
     * Render dashboard tab.
     */
    private function render_dashboard() {
        $stats = $this->get_event_stats();
        ?>
        <div class="threecal-stats-grid">
            <div class="threecal-stat-card">
                <div class="stat-label"><?php esc_html_e( 'Total Events', '3task-calendar' ); ?></div>
                <div class="stat-value"><?php echo esc_html( $stats['total_events'] ); ?></div>
            </div>
            <div class="threecal-stat-card success">
                <div class="stat-label"><?php esc_html_e( 'Published Events', '3task-calendar' ); ?></div>
                <div class="stat-value"><?php echo esc_html( $stats['published_events'] ); ?></div>
            </div>
            <div class="threecal-stat-card warning">
                <div class="stat-label"><?php esc_html_e( 'Draft Events', '3task-calendar' ); ?></div>
                <div class="stat-value"><?php echo esc_html( $stats['draft_events'] ); ?></div>
            </div>
            <div class="threecal-stat-card">
                <div class="stat-label"><?php esc_html_e( 'Categories', '3task-calendar' ); ?></div>
                <div class="stat-value"><?php echo esc_html( $stats['total_categories'] ); ?></div>
            </div>
        </div>

        <div class="threecal-card">
            <h3><?php esc_html_e( 'Welcome to 3task Calendar', '3task-calendar' ); ?></h3>
            <p><?php esc_html_e( 'Create and manage events for your WordPress website. Get started by creating your first event!', '3task-calendar' ); ?></p>

            <div style="margin-top: 20px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=3task-calendar&tab=events&action=new' ) ); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e( 'Create Your First Event', '3task-calendar' ); ?>
                </a>
            </div>
        </div>

        <!-- Mini Calendar -->
        <div class="threecal-card">
            <h3><?php esc_html_e( 'Upcoming Events', '3task-calendar' ); ?></h3>
            <?php $this->render_mini_calendar(); ?>
        </div>
        <?php
    }

    /**
     * Render events tab
     */
    private function render_events() {
        // Check if editing or creating an event.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reading for navigation, no data modification.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['event'] ) ) {
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            $this->render_edit_event();
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reading for navigation, no data modification.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'new' ) {
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            $this->render_new_event();
            return;
        }

        // List all events
        $events = $this->get_events();
        ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2><?php esc_html_e( 'All Events', '3task-calendar' ); ?></h2>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=3task-calendar&tab=events&action=new' ) ); ?>" class="button button-primary">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php esc_html_e( 'Add New Event', '3task-calendar' ); ?>
            </a>
        </div>

        <?php if (empty($events)): ?>
            <div class="threecal-card threecal-empty-state">
                <div class="dashicons dashicons-calendar-alt"></div>
                <p><?php esc_html_e( 'No events found. Create your first event to get started!', '3task-calendar' ); ?></p>
            </div>
        <?php else: ?>
            <div class="threecal-events-grid">
                <?php foreach ($events as $event): ?>
                    <div class="threecal-card threecal-event-card">
                        <div class="event-date">
                            <span class="dashicons dashicons-clock"></span>
                            <?php echo esc_html( wp_date( 'M j, Y', strtotime( $event->start_date ) ) ); ?>
                        </div>
                        <div class="event-title"><?php echo esc_html($event->title); ?></div>
                        <div class="event-description"><?php echo esc_html(substr($event->description, 0, 100)) . '...'; ?></div>
                        <div style="margin-top: 16px; display: flex; gap: 8px;">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=3task-calendar&tab=events&action=edit&event=' . $event->id ) ); ?>" class="button button-small">
                                <?php esc_html_e( 'Edit', '3task-calendar' ); ?>
                            </a>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=3task-calendar&action=delete&id=' . $event->id ), 'threecal_delete_' . $event->id ) ); ?>"
                               class="button button-small action-btn danger threecal-delete"
                               onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this event?', '3task-calendar' ); ?>');">
                                <?php esc_html_e( 'Delete', '3task-calendar' ); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render categories tab
     */
    private function render_categories() {
        // Use the categories view which has the form built-in
        include THREECAL_PLUGIN_DIR . 'admin/views/categories.php';
    }

    /**
     * Render locations tab
     */
    private function render_locations() {
        // Use the locations view which has the form built-in
        include THREECAL_PLUGIN_DIR . 'admin/views/locations.php';
    }

    /**
     * Render settings tab
     */
    private function render_settings() {
        if ( isset( $_POST['submit'] ) ) {
            check_admin_referer( 'threecal_settings' );

            $settings = array(
                'date_format'         => isset( $_POST['date_format'] ) ? sanitize_text_field( wp_unslash( $_POST['date_format'] ) ) : get_option( 'date_format' ),
                'time_format'         => isset( $_POST['time_format'] ) ? sanitize_text_field( wp_unslash( $_POST['time_format'] ) ) : get_option( 'time_format' ),
                'week_starts_on'      => isset( $_POST['week_starts_on'] ) ? absint( $_POST['week_starts_on'] ) : 1,
                'default_view'        => isset( $_POST['default_view'] ) ? sanitize_text_field( wp_unslash( $_POST['default_view'] ) ) : 'month',
                'show_event_time'     => ! empty( $_POST['show_event_time'] ),
                'show_event_location' => ! empty( $_POST['show_event_location'] ),
                'events_per_page'     => isset( $_POST['events_per_page'] ) ? absint( $_POST['events_per_page'] ) : 10,
            );
            
            update_option( 'threecal_settings', $settings );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved!', '3task-calendar' ) . '</p></div>';
        }

        $settings = get_option('threecal_settings', array(
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
            'week_starts_on' => 1,
            'default_view' => 'month',
            'show_event_time' => true,
            'show_event_location' => true,
            'events_per_page' => 10
        ));
        ?>
        <form method="post">
            <?php wp_nonce_field('threecal_settings'); ?>
            
            <div class="threecal-card">
                <h3><?php esc_html_e( 'General Settings', '3task-calendar' ); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Date Format', '3task-calendar' ); ?></th>
                        <td>
                            <input type="text" name="date_format" value="<?php echo esc_attr($settings['date_format']); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Date format for displaying events.', '3task-calendar' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Time Format', '3task-calendar' ); ?></th>
                        <td>
                            <input type="text" name="time_format" value="<?php echo esc_attr($settings['time_format']); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Time format for displaying events.', '3task-calendar' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Week Starts On', '3task-calendar' ); ?></th>
                        <td>
                            <select name="week_starts_on">
                                <option value="0" <?php selected($settings['week_starts_on'], 0); ?>><?php esc_html_e( 'Sunday', '3task-calendar' ); ?></option>
                                <option value="1" <?php selected($settings['week_starts_on'], 1); ?>><?php esc_html_e( 'Monday', '3task-calendar' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show Event Time', '3task-calendar' ); ?></th>
                        <td>
                            <input type="checkbox" name="show_event_time" value="1" <?php checked($settings['show_event_time']); ?> />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show Event Location', '3task-calendar' ); ?></th>
                        <td>
                            <input type="checkbox" name="show_event_location" value="1" <?php checked($settings['show_event_location']); ?> />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </div>
        </form>
        <?php
    }

    /**
     * Render help tab
     */
    private function render_help() {
        ?>
        <div class="threecal-help-page">
            <!-- Quick Start -->
            <div class="threecal-card">
                <h2><?php esc_html_e( 'Quick Start Guide', '3task-calendar' ); ?></h2>
                <p><?php esc_html_e( 'Follow these steps to display your calendar on any page or widget:', '3task-calendar' ); ?></p>
                <ol>
                    <li><?php esc_html_e( 'Create categories for your events (optional but recommended)', '3task-calendar' ); ?></li>
                    <li><?php esc_html_e( 'Add locations where events take place (optional)', '3task-calendar' ); ?></li>
                    <li><?php esc_html_e( 'Create your first event with title, date, and description', '3task-calendar' ); ?></li>
                    <li><?php esc_html_e( 'Use a shortcode to display the calendar on any page, post, or widget', '3task-calendar' ); ?></li>
                </ol>
            </div>

            <!-- Main Calendar Shortcode -->
            <div class="threecal-card">
                <h2><?php esc_html_e( 'Calendar Shortcode', '3task-calendar' ); ?></h2>
                <p><?php esc_html_e( 'Display an interactive calendar with all your events.', '3task-calendar' ); ?></p>

                <h4><?php esc_html_e( 'Basic Usage', '3task-calendar' ); ?></h4>
                <code class="threecal-code-block">[threecal]</code>

                <h4><?php esc_html_e( 'All Parameters', '3task-calendar' ); ?></h4>
                <code class="threecal-code-block">[threecal view="month" category="1" location="2" theme="default" show_filters="true" show_legend="true" week_starts_on="1"]</code>

                <table class="widefat striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Parameter', '3task-calendar' ); ?></th>
                            <th><?php esc_html_e( 'Default', '3task-calendar' ); ?></th>
                            <th><?php esc_html_e( 'Description', '3task-calendar' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>view</code></td>
                            <td>month</td>
                            <td><?php esc_html_e( 'Calendar view: month, week, or day', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>category</code></td>
                            <td>0</td>
                            <td><?php esc_html_e( 'Filter by category ID (0 = show all)', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>location</code></td>
                            <td>0</td>
                            <td><?php esc_html_e( 'Filter by location ID (0 = show all)', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>theme</code></td>
                            <td>default</td>
                            <td><?php esc_html_e( 'Visual theme for the calendar', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_filters</code></td>
                            <td>true</td>
                            <td><?php esc_html_e( 'Show category/location filter dropdowns', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_legend</code></td>
                            <td>true</td>
                            <td><?php esc_html_e( 'Show color legend for categories', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>week_starts_on</code></td>
                            <td><?php esc_html_e( '(from settings)', '3task-calendar' ); ?></td>
                            <td><?php esc_html_e( '0 = Sunday, 1 = Monday', '3task-calendar' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Event List Shortcode -->
            <div class="threecal-card">
                <h2><?php esc_html_e( 'Event List Shortcode', '3task-calendar' ); ?></h2>
                <p><?php esc_html_e( 'Display events as a list or grid.', '3task-calendar' ); ?></p>

                <h4><?php esc_html_e( 'Basic Usage', '3task-calendar' ); ?></h4>
                <code class="threecal-code-block">[threecal_events]</code>

                <h4><?php esc_html_e( 'All Parameters', '3task-calendar' ); ?></h4>
                <code class="threecal-code-block">[threecal_events category="1" location="2" limit="10" view="list" show_past="false" show_pagination="true" columns="3"]</code>

                <table class="widefat striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Parameter', '3task-calendar' ); ?></th>
                            <th><?php esc_html_e( 'Default', '3task-calendar' ); ?></th>
                            <th><?php esc_html_e( 'Description', '3task-calendar' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>category</code></td>
                            <td>0</td>
                            <td><?php esc_html_e( 'Filter by category ID', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>location</code></td>
                            <td>0</td>
                            <td><?php esc_html_e( 'Filter by location ID', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>limit</code></td>
                            <td>10</td>
                            <td><?php esc_html_e( 'Number of events to display', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>view</code></td>
                            <td>list</td>
                            <td><?php esc_html_e( 'Display style: list or grid', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_past</code></td>
                            <td>false</td>
                            <td><?php esc_html_e( 'Include past events', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_pagination</code></td>
                            <td>true</td>
                            <td><?php esc_html_e( 'Show pagination controls', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>columns</code></td>
                            <td>3</td>
                            <td><?php esc_html_e( 'Number of columns in grid view', '3task-calendar' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Upcoming Events Shortcode -->
            <div class="threecal-card">
                <h2><?php esc_html_e( 'Upcoming Events Shortcode', '3task-calendar' ); ?></h2>
                <p><?php esc_html_e( 'Perfect for sidebars and widgets. Shows a compact list of upcoming events.', '3task-calendar' ); ?></p>

                <h4><?php esc_html_e( 'Basic Usage', '3task-calendar' ); ?></h4>
                <code class="threecal-code-block">[threecal_upcoming]</code>

                <h4><?php esc_html_e( 'All Parameters', '3task-calendar' ); ?></h4>
                <code class="threecal-code-block">[threecal_upcoming limit="5" category="1" show_date="true" show_time="true" show_location="true"]</code>

                <table class="widefat striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Parameter', '3task-calendar' ); ?></th>
                            <th><?php esc_html_e( 'Default', '3task-calendar' ); ?></th>
                            <th><?php esc_html_e( 'Description', '3task-calendar' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>limit</code></td>
                            <td>5</td>
                            <td><?php esc_html_e( 'Number of events to show', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>category</code></td>
                            <td>0</td>
                            <td><?php esc_html_e( 'Filter by category ID', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_date</code></td>
                            <td>true</td>
                            <td><?php esc_html_e( 'Display event date', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_time</code></td>
                            <td>true</td>
                            <td><?php esc_html_e( 'Display event time', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_location</code></td>
                            <td>true</td>
                            <td><?php esc_html_e( 'Display event location', '3task-calendar' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Single Event Shortcode -->
            <div class="threecal-card">
                <h2><?php esc_html_e( 'Single Event Shortcode', '3task-calendar' ); ?></h2>
                <p><?php esc_html_e( 'Display a specific event by its ID.', '3task-calendar' ); ?></p>

                <h4><?php esc_html_e( 'Usage', '3task-calendar' ); ?></h4>
                <code class="threecal-code-block">[threecal_event id="123" show_map="true" show_description="true"]</code>

                <table class="widefat striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Parameter', '3task-calendar' ); ?></th>
                            <th><?php esc_html_e( 'Default', '3task-calendar' ); ?></th>
                            <th><?php esc_html_e( 'Description', '3task-calendar' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>id</code></td>
                            <td><?php esc_html_e( '(required)', '3task-calendar' ); ?></td>
                            <td><?php esc_html_e( 'The event ID to display', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_map</code></td>
                            <td>true</td>
                            <td><?php esc_html_e( 'Show location map (requires Google Maps API key)', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_description</code></td>
                            <td>true</td>
                            <td><?php esc_html_e( 'Show full event description', '3task-calendar' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mini Calendar Shortcode -->
            <div class="threecal-card">
                <h2><?php esc_html_e( 'Mini Calendar Shortcode', '3task-calendar' ); ?></h2>
                <p><?php esc_html_e( 'Perfect for sidebar widgets! A compact month view with event markers.', '3task-calendar' ); ?></p>

                <h4><?php esc_html_e( 'Basic Usage', '3task-calendar' ); ?></h4>
                <code class="threecal-code-block">[threecal_mini]</code>

                <h4><?php esc_html_e( 'All Parameters', '3task-calendar' ); ?></h4>
                <code class="threecal-code-block">[threecal_mini category="1" show_nav="true" show_today="true" week_starts_on="1"]</code>

                <table class="widefat striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Parameter', '3task-calendar' ); ?></th>
                            <th><?php esc_html_e( 'Default', '3task-calendar' ); ?></th>
                            <th><?php esc_html_e( 'Description', '3task-calendar' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>category</code></td>
                            <td>0</td>
                            <td><?php esc_html_e( 'Filter by category ID (0 = show all)', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_nav</code></td>
                            <td>true</td>
                            <td><?php esc_html_e( 'Show previous/next month navigation', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_today</code></td>
                            <td>true</td>
                            <td><?php esc_html_e( 'Show "Today" link at the bottom', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>week_starts_on</code></td>
                            <td><?php esc_html_e( '(from settings)', '3task-calendar' ); ?></td>
                            <td><?php esc_html_e( '0 = Sunday, 1 = Monday', '3task-calendar' ); ?></td>
                        </tr>
                    </tbody>
                </table>

                <div class="threecal-help-tip" style="background: #d4edda; border-left: 4px solid #28a745; padding: 12px 15px; margin-top: 15px;">
                    <strong><?php esc_html_e( 'Widget Tip:', '3task-calendar' ); ?></strong>
                    <?php esc_html_e( 'This is the ideal shortcode for sidebars! Days with events show colored dots. Hover over them to see event titles.', '3task-calendar' ); ?>
                </div>
            </div>

            <!-- Widget Usage -->
            <div class="threecal-card">
                <h2><?php esc_html_e( 'Using in Widgets', '3task-calendar' ); ?></h2>
                <p><?php esc_html_e( 'All shortcodes work in widgets! Here are the best options for sidebars:', '3task-calendar' ); ?></p>

                <h4><?php esc_html_e( 'Block Editor (Recommended)', '3task-calendar' ); ?></h4>
                <ol>
                    <li><?php esc_html_e( 'Go to Appearance > Widgets', '3task-calendar' ); ?></li>
                    <li><?php esc_html_e( 'Add a "Shortcode" block to your widget area', '3task-calendar' ); ?></li>
                    <li><?php esc_html_e( 'Enter the shortcode, e.g.:', '3task-calendar' ); ?> <code>[threecal_upcoming limit="3"]</code></li>
                </ol>

                <h4><?php esc_html_e( 'Classic Widgets', '3task-calendar' ); ?></h4>
                <ol>
                    <li><?php esc_html_e( 'Add a "Text" or "Custom HTML" widget', '3task-calendar' ); ?></li>
                    <li><?php esc_html_e( 'Enter the shortcode in the content area', '3task-calendar' ); ?></li>
                </ol>

                <div class="threecal-help-tip" style="background: #f0f6fc; border-left: 4px solid #3788d8; padding: 12px 15px; margin-top: 15px;">
                    <strong><?php esc_html_e( 'Best Widget Shortcodes:', '3task-calendar' ); ?></strong><br>
                    <code>[threecal_mini]</code> - <?php esc_html_e( 'Compact month view with event markers (recommended!)', '3task-calendar' ); ?><br>
                    <code>[threecal_upcoming limit="3"]</code> - <?php esc_html_e( 'Simple list of upcoming events', '3task-calendar' ); ?>
                </div>
            </div>

            <!-- Features Overview -->
            <div class="threecal-card">
                <h2><?php esc_html_e( 'Features Overview', '3task-calendar' ); ?></h2>

                <div class="threecal-features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 15px;">
                    <div class="feature-item">
                        <span class="dashicons dashicons-calendar-alt" style="color: #3788d8;"></span>
                        <strong><?php esc_html_e( 'Interactive Calendar', '3task-calendar' ); ?></strong>
                        <p><?php esc_html_e( 'Month, week, and day views with navigation', '3task-calendar' ); ?></p>
                    </div>
                    <div class="feature-item">
                        <span class="dashicons dashicons-category" style="color: #3788d8;"></span>
                        <strong><?php esc_html_e( 'Categories', '3task-calendar' ); ?></strong>
                        <p><?php esc_html_e( 'Organize events with color-coded categories', '3task-calendar' ); ?></p>
                    </div>
                    <div class="feature-item">
                        <span class="dashicons dashicons-location" style="color: #3788d8;"></span>
                        <strong><?php esc_html_e( 'Locations', '3task-calendar' ); ?></strong>
                        <p><?php esc_html_e( 'Add venues with address and map integration', '3task-calendar' ); ?></p>
                    </div>
                    <div class="feature-item">
                        <span class="dashicons dashicons-smartphone" style="color: #3788d8;"></span>
                        <strong><?php esc_html_e( 'Responsive Design', '3task-calendar' ); ?></strong>
                        <p><?php esc_html_e( 'Works perfectly on all devices', '3task-calendar' ); ?></p>
                    </div>
                    <div class="feature-item">
                        <span class="dashicons dashicons-filter" style="color: #3788d8;"></span>
                        <strong><?php esc_html_e( 'Filters', '3task-calendar' ); ?></strong>
                        <p><?php esc_html_e( 'Let visitors filter by category or location', '3task-calendar' ); ?></p>
                    </div>
                    <div class="feature-item">
                        <span class="dashicons dashicons-admin-appearance" style="color: #3788d8;"></span>
                        <strong><?php esc_html_e( 'Customizable', '3task-calendar' ); ?></strong>
                        <p><?php esc_html_e( 'Multiple themes and display options', '3task-calendar' ); ?></p>
                    </div>
                </div>
            </div>

            <!-- Getting Category/Location IDs -->
            <div class="threecal-card">
                <h2><?php esc_html_e( 'Finding Category and Location IDs', '3task-calendar' ); ?></h2>
                <p><?php esc_html_e( 'To filter events by category or location, you need their IDs:', '3task-calendar' ); ?></p>
                <ol>
                    <li><?php esc_html_e( 'Go to the Categories or Locations tab', '3task-calendar' ); ?></li>
                    <li><?php esc_html_e( 'Click "Edit" on the item you want', '3task-calendar' ); ?></li>
                    <li><?php esc_html_e( 'Look at the URL - the number after "edit=" is the ID', '3task-calendar' ); ?></li>
                </ol>
                <p><em><?php esc_html_e( 'Example: admin.php?page=3task-calendar&tab=categories&edit=5 means the ID is 5', '3task-calendar' ); ?></em></p>
            </div>

            <!-- Support -->
            <div class="threecal-card">
                <h2><?php esc_html_e( 'Need More Help?', '3task-calendar' ); ?></h2>
                <p>
                    <?php
                    printf(
                        /* translators: %s: Support forum URL */
                        esc_html__( 'Visit our %s for questions and feature requests.', '3task-calendar' ),
                        '<a href="https://wordpress.org/support/plugin/3task-calendar/" target="_blank">' . esc_html__( 'support forum', '3task-calendar' ) . '</a>'
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Get event statistics
     */
    private function get_event_stats() {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard stats, custom tables.
        $total_events     = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}threecal_events" );
        $published_events = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}threecal_events WHERE status = 'published'" );
        $draft_events     = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}threecal_events WHERE status = 'draft'" );
        $total_categories = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}threecal_categories" );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return array(
            'total_events'     => $total_events ? $total_events : 0,
            'published_events' => $published_events ? $published_events : 0,
            'draft_events'     => $draft_events ? $draft_events : 0,
            'total_categories' => $total_categories ? $total_categories : 0,
        );
    }

    /**
     * Get events
     */
    private function get_events() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for events.
        $results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}threecal_events ORDER BY start_date ASC" );

        return $results ? $results : array();
    }

    /**
     * Get categories
     */
    private function get_categories() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for categories.
        $results = $wpdb->get_results( "
            SELECT c.*, COUNT(e.id) as event_count
            FROM {$wpdb->prefix}threecal_categories c
            LEFT JOIN {$wpdb->prefix}threecal_events e ON c.id = e.category_id
            GROUP BY c.id
            ORDER BY c.name ASC
        " );

        return $results ? $results : array();
    }

    /**
     * Get locations
     */
    private function get_locations() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for locations.
        $results = $wpdb->get_results( "
            SELECT * FROM {$wpdb->prefix}threecal_locations
            ORDER BY name ASC
        " );

        return $results ? $results : array();
    }

    /**
     * Render mini calendar
     */
    private function render_mini_calendar() {
        $current_date = current_time('Y-m-d');
        $events = $this->get_upcoming_events(5);
        
        if ( empty( $events ) ) {
            echo '<p>' . esc_html__( 'No upcoming events.', '3task-calendar' ) . '</p>';
            return;
        }
        
        echo '<div class="threecal-mini-calendar">';
        foreach ($events as $event) {
            echo '<div class="mini-event" style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;">';
            echo '<strong>' . esc_html($event->title) . '</strong><br>';
            echo '<span style="color: #6b7280; font-size: 0.9rem;">' . esc_html( wp_date( 'M j, Y', strtotime( $event->start_date ) ) ) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Get upcoming events
     */
    private function get_upcoming_events( $limit = 5 ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for events.
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}threecal_events
            WHERE start_date >= %s AND status = 'published'
            ORDER BY start_date ASC
            LIMIT %d",
            current_time( 'Y-m-d' ),
            $limit
        ) );

        return $results ? $results : array();
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('threecal_settings', 'threecal_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));

        // Handle form submissions
        $this->handle_form_submissions();
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        // General
        $sanitized['date_format'] = sanitize_text_field($input['date_format'] ?? get_option('date_format'));
        $sanitized['time_format'] = sanitize_text_field($input['time_format'] ?? get_option('time_format'));
        $sanitized['week_starts_on'] = absint($input['week_starts_on'] ?? 1);
        $sanitized['default_view'] = sanitize_text_field($input['default_view'] ?? 'month');
        $sanitized['default_theme'] = sanitize_text_field($input['default_theme'] ?? 'default');

        // Display
        $sanitized['show_event_time'] = !empty($input['show_event_time']);
        $sanitized['show_event_location'] = !empty($input['show_event_location']);
        $sanitized['show_event_description'] = !empty($input['show_event_description']);
        $sanitized['events_per_page'] = absint($input['events_per_page'] ?? 10);
        $sanitized['enable_event_popup'] = !empty($input['enable_event_popup']);

        // Google Maps
        $sanitized['google_maps_api_key'] = sanitize_text_field($input['google_maps_api_key'] ?? '');
        $sanitized['default_map_zoom'] = absint($input['default_map_zoom'] ?? 14);
        $sanitized['default_map_type'] = sanitize_text_field($input['default_map_type'] ?? 'roadmap');

        // Email
        $sanitized['enable_notifications'] = !empty($input['enable_notifications']);
        $sanitized['notification_sender_name'] = sanitize_text_field($input['notification_sender_name'] ?? '');
        $sanitized['notification_sender_email'] = sanitize_email($input['notification_sender_email'] ?? '');
        $sanitized['notification_template'] = wp_kses_post($input['notification_template'] ?? '');

        // SEO
        $sanitized['enable_schema'] = !empty($input['enable_schema']);

        // Advanced
        $sanitized['delete_data_on_uninstall'] = !empty($input['delete_data_on_uninstall']);

        return $sanitized;
    }

    /**
     * Handle form submissions
     */
    private function handle_form_submissions() {
        // Save event.
        if ( isset( $_POST['threecal_save_event'] ) && check_admin_referer( 'threecal_save_event' ) ) {
            $this->save_event();
        }

        // Delete event.
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
            $id    = absint( $_GET['id'] );
            if ( wp_verify_nonce( $nonce, 'threecal_delete_' . $id ) ) {
                $this->delete_event( $id );
            }
        }

        // Save location.
        if ( isset( $_POST['threecal_save_location'] ) && check_admin_referer( 'threecal_save_location' ) ) {
            $this->save_location();
        }

        // Delete location.
        if ( isset( $_GET['action'] ) && 'delete_location' === $_GET['action'] && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
            $id    = absint( $_GET['id'] );
            if ( wp_verify_nonce( $nonce, 'threecal_delete_location_' . $id ) ) {
                $this->delete_location( $id );
            }
        }

        // Save category.
        if ( isset( $_POST['threecal_save_category'] ) && check_admin_referer( 'threecal_save_category' ) ) {
            $this->save_category();
        }

        // Delete category.
        if ( isset( $_GET['action'] ) && 'delete_category' === $_GET['action'] && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
            $id    = absint( $_GET['id'] );
            if ( wp_verify_nonce( $nonce, 'threecal_delete_category_' . $id ) ) {
                $this->delete_category( $id );
            }
        }
    }

    /**
     * Save event
     */
    private function save_event() {
        if ( ! current_user_can( 'create_threecal_events' ) && ! current_user_can( 'edit_threecal_events' ) ) {
            wp_die( esc_html__( 'Permission denied.', '3task-calendar' ) );
        }

        // Nonce is already verified in handle_form_submissions() via check_admin_referer().
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions.
        $event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;

        if ( $event_id > 0 ) {
            $event = ThreeCal_Event::get( $event_id );
            if ( ! $event ) {
                wp_die( esc_html__( 'Event not found.', '3task-calendar' ) );
            }
        } else {
            $event = new ThreeCal_Event();
        }

        $event->title          = isset( $_POST['event_title'] ) ? sanitize_text_field( wp_unslash( $_POST['event_title'] ) ) : '';
        $event->description    = isset( $_POST['event_description'] ) ? wp_kses_post( wp_unslash( $_POST['event_description'] ) ) : '';
        $event->start_date     = isset( $_POST['event_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_start_date'] ) ) : '';
        $event->end_date       = isset( $_POST['event_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_end_date'] ) ) : '';
        $event->all_day        = isset( $_POST['event_all_day'] );
        $event->location_id    = isset( $_POST['event_location'] ) ? absint( $_POST['event_location'] ) : 0;
        $event->url            = isset( $_POST['event_url'] ) ? esc_url_raw( wp_unslash( $_POST['event_url'] ) ) : '';
        $event->featured_image = isset( $_POST['event_featured_image'] ) ? absint( $_POST['event_featured_image'] ) : 0;
        $event->color          = isset( $_POST['event_color'] ) ? ( sanitize_hex_color( wp_unslash( $_POST['event_color'] ) ) ?: '#3788d8' ) : '#3788d8';
        $event->status         = isset( $_POST['event_status'] ) ? sanitize_text_field( wp_unslash( $_POST['event_status'] ) ) : 'publish';

        if ( $event->save() ) {
            // Set categories.
            $categories = isset( $_POST['event_categories'] ) ? array_map( 'absint', $_POST['event_categories'] ) : array();
            $event->set_categories( $categories );
            // phpcs:enable WordPress.Security.NonceVerification.Missing

            // Redirect
            $redirect = add_query_arg(array(
                'page' => '3task-calendar',
                'tab' => 'events',
                'action' => 'edit',
                'event' => $event->id,
                'message' => 'saved'
            ), admin_url('admin.php'));

            wp_safe_redirect( $redirect );
            exit;
        } else {
            wp_die( esc_html__( 'Error saving event.', '3task-calendar' ) );
        }
    }

    /**
     * Delete event
     */
    private function delete_event($id) {
        if (!current_user_can('delete_threecal_events')) {
            wp_die( esc_html__( 'Permission denied.', '3task-calendar' ) );
        }

        $event = ThreeCal_Event::get($id);
        if ($event && $event->delete()) {
            wp_safe_redirect( add_query_arg( array(
                'page' => '3task-calendar',
                'message' => 'deleted'
            ), admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Save location
     */
    private function save_location() {
        if ( ! current_user_can( 'manage_threecal_locations' ) ) {
            wp_die( esc_html__( 'Permission denied.', '3task-calendar' ) );
        }

        // Nonce is already verified in handle_form_submissions() via check_admin_referer().
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions.
        $location_id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;

        if ( $location_id > 0 ) {
            $location = ThreeCal_Location::get( $location_id );
            if ( ! $location ) {
                wp_die( esc_html__( 'Location not found.', '3task-calendar' ) );
            }
        } else {
            $location = new ThreeCal_Location();
        }

        $location->name           = isset( $_POST['location_name'] ) ? sanitize_text_field( wp_unslash( $_POST['location_name'] ) ) : '';
        $location->address        = isset( $_POST['location_address'] ) ? sanitize_text_field( wp_unslash( $_POST['location_address'] ) ) : '';
        $location->city           = isset( $_POST['location_city'] ) ? sanitize_text_field( wp_unslash( $_POST['location_city'] ) ) : '';
        $location->postal_code    = isset( $_POST['location_postal_code'] ) ? sanitize_text_field( wp_unslash( $_POST['location_postal_code'] ) ) : '';
        $location->country        = isset( $_POST['location_country'] ) ? sanitize_text_field( wp_unslash( $_POST['location_country'] ) ) : 'DE';
        $location->phone          = isset( $_POST['location_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['location_phone'] ) ) : '';
        $location->email          = isset( $_POST['location_email'] ) ? sanitize_email( wp_unslash( $_POST['location_email'] ) ) : '';
        $location->website        = isset( $_POST['location_website'] ) ? esc_url_raw( wp_unslash( $_POST['location_website'] ) ) : '';
        $location->description    = isset( $_POST['location_description'] ) ? wp_kses_post( wp_unslash( $_POST['location_description'] ) ) : '';
        $location->featured_image = isset( $_POST['location_featured_image'] ) ? absint( $_POST['location_featured_image'] ) : 0;

        // Geocode if API key is set.
        $do_geocode = isset( $_POST['location_geocode'] ) ? sanitize_text_field( wp_unslash( $_POST['location_geocode'] ) ) : '';
        if ( $do_geocode ) {
            $location->geocode();
        } else {
            $location->latitude  = isset( $_POST['location_latitude'] ) ? floatval( $_POST['location_latitude'] ) : null;
            $location->longitude = isset( $_POST['location_longitude'] ) ? floatval( $_POST['location_longitude'] ) : null;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( $location->save() ) {
            wp_safe_redirect( add_query_arg( array(
                'page'    => '3task-calendar',
                'tab'     => 'locations',
                'message' => 'saved',
            ), admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    /**
     * Delete location
     */
    private function delete_location($id) {
        if (!current_user_can('manage_threecal_locations')) {
            wp_die( esc_html__( 'Permission denied.', '3task-calendar' ) );
        }

        $location = ThreeCal_Location::get($id);
        if ($location && $location->delete()) {
            wp_safe_redirect( add_query_arg( array(
                'page' => '3task-calendar',
                'tab' => 'locations',
                'message' => 'deleted'
            ), admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Save category
     */
    private function save_category() {
        if ( ! current_user_can( 'manage_threecal_categories' ) ) {
            wp_die( esc_html__( 'Permission denied.', '3task-calendar' ) );
        }

        // Nonce is already verified in handle_form_submissions() via check_admin_referer().
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions.
        $category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;

        if ( $category_id > 0 ) {
            $category = ThreeCal_Category::get( $category_id );
            if ( ! $category ) {
                wp_die( esc_html__( 'Category not found.', '3task-calendar' ) );
            }
        } else {
            $category = new ThreeCal_Category();
        }

        $category->name        = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
        $category->slug        = isset( $_POST['category_slug'] ) ? sanitize_title( wp_unslash( $_POST['category_slug'] ) ) : '';
        $category->description = isset( $_POST['category_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['category_description'] ) ) : '';
        $category->color       = isset( $_POST['category_color'] ) ? ( sanitize_hex_color( wp_unslash( $_POST['category_color'] ) ) ?: '#3788d8' ) : '#3788d8';
        $category->parent_id   = isset( $_POST['category_parent'] ) ? absint( $_POST['category_parent'] ) : 0;
        $category->sort_order  = isset( $_POST['category_sort_order'] ) ? absint( $_POST['category_sort_order'] ) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( $category->save() ) {
            wp_safe_redirect( add_query_arg( array(
                'page'    => '3task-calendar',
                'tab'     => 'categories',
                'message' => 'saved',
            ), admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    /**
     * Delete category
     */
    private function delete_category($id) {
        if (!current_user_can('manage_threecal_categories')) {
            wp_die( esc_html__( 'Permission denied.', '3task-calendar' ) );
        }

        $category = ThreeCal_Category::get($id);
        if ($category && $category->delete()) {
            wp_safe_redirect( add_query_arg( array(
                'page' => '3task-calendar',
                'tab' => 'categories',
                'message' => 'deleted'
            ), admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Render events page
     */
    public function render_events_page() {
        include THREECAL_PLUGIN_DIR . 'admin/views/events-list.php';
    }

    /**
     * Render edit event form (inline in events tab)
     */
    private function render_edit_event() {
        include THREECAL_PLUGIN_DIR . 'admin/views/event-edit.php';
    }

    /**
     * Render new event form (inline in events tab)
     */
    private function render_new_event() {
        include THREECAL_PLUGIN_DIR . 'admin/views/event-edit.php';
    }

    /**
     * Render edit event page
     */
    public function render_edit_event_page() {
        include THREECAL_PLUGIN_DIR . 'admin/views/event-edit.php';
    }

    /**
     * Render locations page
     */
    public function render_locations_page() {
        include THREECAL_PLUGIN_DIR . 'admin/views/locations.php';
    }

    /**
     * Render categories page
     */
    public function render_categories_page() {
        include THREECAL_PLUGIN_DIR . 'admin/views/categories.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include THREECAL_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
