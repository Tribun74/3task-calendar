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
            threecal_asset_version('admin/css/admin.css')
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
            threecal_asset_version('admin/js/admin.js'),
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
            'edit_threecal_events',
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
        $counts = $this->get_tab_counts();

        return array(
            'dashboard'  => array(
                'title' => __( 'Dashboard', '3task-calendar' ),
                'icon'  => 'layout-dashboard',
            ),
            'events'     => array(
                'title' => __( 'Events', '3task-calendar' ),
                'icon'  => 'calendar-event',
                'count' => $counts['events'],
            ),
            'categories' => array(
                'title' => __( 'Categories', '3task-calendar' ),
                'icon'  => 'category',
                'count' => $counts['categories'],
            ),
            'locations'  => array(
                'title' => __( 'Locations', '3task-calendar' ),
                'icon'  => 'map-pin',
                'count' => $counts['locations'],
            ),
            'posts'      => array(
                'title' => __( 'Posts', '3task-calendar' ),
                'icon'  => 'article',
            ),
            'design'     => array(
                'title' => __( 'Design', '3task-calendar' ),
                'icon'  => 'palette',
            ),
            'settings'   => array(
                'title' => __( 'Settings', '3task-calendar' ),
                'icon'  => 'settings',
            ),
            'help'       => array(
                'title' => __( 'Help', '3task-calendar' ),
                'icon'  => 'help-circle',
            ),
        );
    }

    /**
     * Counts for the tab badges.
     *
     * @return array
     */
    private function get_tab_counts() {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Counts from custom tables.
        $events     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}threecal_events WHERE parent_id IS NULL OR parent_id = 0" );
        $categories = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}threecal_categories" );
        $locations  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}threecal_locations" );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return compact( 'events', 'categories', 'locations' );
    }

    /**
     * Render main admin page with tabs.
     */
    public function render_admin_page() {
        $tabs = $this->get_tabs();
        ?>
        <div class="wrap threecal-wrap threecal-admin">
            <h1 class="screen-reader-text"><?php esc_html_e( '3task Calendar', '3task-calendar' ); ?></h1>

            <div class="threecal-admin-header">
                <div class="threecal-admin-header-left">
                    <div class="threecal-admin-icon"><?php ThreeCal_Icons::render( 'calendar-event', 34 ); ?></div>
                    <div class="threecal-admin-title">
                        <span class="threecal-admin-name"><?php esc_html_e( '3task Calendar', '3task-calendar' ); ?></span>
                        <span class="threecal-admin-meta">
                            <span class="threecal-admin-version"><?php echo esc_html( 'v' . THREECAL_VERSION ); ?></span>
                            <span class="threecal-admin-status"><?php esc_html_e( 'Active', '3task-calendar' ); ?></span>
                        </span>
                    </div>
                </div>
                <div class="threecal-admin-header-right">
                    <a class="threecal-admin-header-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=3task-calendar&tab=help' ) ); ?>">
                        <?php ThreeCal_Icons::render( 'book', 16 ); ?>
                        <?php esc_html_e( 'Documentation', '3task-calendar' ); ?>
                    </a>
                    <a class="threecal-admin-header-btn" href="https://wordpress.org/support/plugin/3task-calendar/" target="_blank" rel="noopener">
                        <?php ThreeCal_Icons::render( 'message-circle', 16 ); ?>
                        <?php esc_html_e( 'Support', '3task-calendar' ); ?>
                    </a>
                </div>
            </div>

            <nav class="threecal-admin-tabs" aria-label="<?php esc_attr_e( '3task Calendar', '3task-calendar' ); ?>">
                <?php foreach ( $tabs as $tab_key => $tab ) : ?>
                    <?php $active = ( $this->current_tab === $tab_key ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=3task-calendar&tab=' . $tab_key ) ); ?>"
                       class="threecal-admin-tab<?php echo $active ? ' is-active' : ''; ?>"
                       <?php echo $active ? 'aria-current="page"' : ''; ?>>
                        <?php ThreeCal_Icons::render( $tab['icon'], 18 ); ?>
                        <span><?php echo esc_html( $tab['title'] ); ?></span>
                        <?php if ( ! empty( $tab['count'] ) ) : ?>
                            <span class="threecal-admin-badge"><?php echo esc_html( $tab['count'] ); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="threecal-tab-content threecal-admin-content">
                <?php $this->render_tab_content(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render tab content.
     */
    private function render_tab_content() {
        switch ( $this->current_tab ) {
            case 'events':
                $this->render_events();
                break;
            case 'categories':
                $this->render_categories();
                break;
            case 'locations':
                $this->render_locations();
                break;
            case 'design':
                $this->render_design();
                break;
            case 'posts':
                ThreeCal_Post_Dates::render_settings_page();
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
        $stats    = $this->get_event_stats();
        $counts   = $this->get_tab_counts();
        $upcoming = ThreeCal_Event::get_upcoming( 5 );
        $cats     = ThreeCal_Event::get_categories_for_events( wp_list_pluck( $upcoming, 'id' ) );
        $new_url  = admin_url( 'admin.php?page=3task-calendar&tab=events&action=new' );
        ?>
        <?php if ( 0 === (int) $stats['total_events'] ) : ?>
        <div class="threecal-onboarding">
            <div class="threecal-onboarding-icon"><?php ThreeCal_Icons::render( 'calendar-plus', 28 ); ?></div>
            <div class="threecal-onboarding-text">
                <h2><?php esc_html_e( 'Welcome to 3task Calendar', '3task-calendar' ); ?></h2>
                <p><?php esc_html_e( 'Create and manage events for your WordPress website. Get started by creating your first event!', '3task-calendar' ); ?></p>
            </div>
            <a class="threecal-btn-primary" href="<?php echo esc_url( $new_url ); ?>"><?php ThreeCal_Icons::render( 'plus', 16 ); ?><?php esc_html_e( 'Create Your First Event', '3task-calendar' ); ?></a>
        </div>
        <?php endif; ?>

        <div class="threecal-kpis">
            <div class="threecal-kpi kpi-blue">
                <div class="threecal-kpi-head"><?php ThreeCal_Icons::render( 'calendar-event', 22 ); ?></div>
                <div class="threecal-kpi-value"><?php echo esc_html( $stats['total_events'] ); ?></div>
                <div class="threecal-kpi-label"><?php esc_html_e( 'Total Events', '3task-calendar' ); ?></div>
            </div>
            <div class="threecal-kpi kpi-green">
                <div class="threecal-kpi-head"><?php ThreeCal_Icons::render( 'calendar-check', 22 ); ?></div>
                <div class="threecal-kpi-value"><?php echo esc_html( $stats['published_events'] ); ?></div>
                <div class="threecal-kpi-label"><?php esc_html_e( 'Published Events', '3task-calendar' ); ?></div>
            </div>
            <div class="threecal-kpi kpi-orange">
                <div class="threecal-kpi-head"><?php ThreeCal_Icons::render( 'pencil-plus', 22 ); ?></div>
                <div class="threecal-kpi-value"><?php echo esc_html( $stats['draft_events'] ); ?></div>
                <div class="threecal-kpi-label"><?php esc_html_e( 'Draft Events', '3task-calendar' ); ?></div>
            </div>
            <div class="threecal-kpi kpi-teal">
                <div class="threecal-kpi-head"><?php ThreeCal_Icons::render( 'category', 22 ); ?></div>
                <div class="threecal-kpi-value"><?php echo esc_html( $counts['categories'] ); ?></div>
                <div class="threecal-kpi-label"><?php esc_html_e( 'Categories', '3task-calendar' ); ?></div>
            </div>
        </div>

        <div class="threecal-admin-grid">
            <section class="threecal-panel">
                <h2 class="threecal-panel-title"><?php ThreeCal_Icons::render( 'calendar-stats', 20 ); ?><?php esc_html_e( 'Upcoming Events', '3task-calendar' ); ?></h2>
                <?php if ( empty( $upcoming ) ) : ?>
                    <p class="threecal-muted"><?php esc_html_e( 'No upcoming events.', '3task-calendar' ); ?></p>
                <?php else : ?>
                <ul class="threecal-admin-upcoming">
                    <?php foreach ( $upcoming as $event ) :
                        $color = ThreeCal_Themes::event_color( $event, isset( $cats[ $event->id ] ) ? $cats[ $event->id ] : array() );
                        $start = strtotime( $event->start_date );
                        $edit  = admin_url( 'admin.php?page=3task-calendar&tab=events&action=edit&event=' . ( $event->parent_id ? $event->parent_id : $event->id ) );
                        $from  = is_array( $event->settings ) ? $event->settings : json_decode( (string) $event->settings, true );
                        if ( ! empty( $from['post_id'] ) && get_edit_post_link( (int) $from['post_id'] ) ) {
                            $edit = get_edit_post_link( (int) $from['post_id'], 'raw' );
                        }
                        ?>
                    <li style="<?php echo esc_attr( ThreeCal_Themes::event_style( $color ) ); ?>">
                        <span class="threecal-admin-date">
                            <span><?php echo esc_html( date_i18n( 'M', $start ) ); ?></span>
                            <strong><?php echo esc_html( date_i18n( 'j', $start ) ); ?></strong>
                        </span>
                        <span class="threecal-admin-upcoming-text">
                            <a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $event->title ); ?></a>
                            <span class="threecal-muted">
                                <?php echo esc_html( $event->all_day ? __( 'All day', '3task-calendar' ) : date_i18n( threecal_time_format(), $start ) ); ?>
                                <?php if ( $event->parent_id ) : ?> · <?php esc_html_e( 'Series', '3task-calendar' ); ?><?php endif; ?>
                            </span>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>

            <div class="threecal-admin-side">
                <section class="threecal-panel">
                    <h2 class="threecal-panel-title"><?php ThreeCal_Icons::render( 'layout-dashboard', 20 ); ?><?php esc_html_e( 'Quick Actions', '3task-calendar' ); ?></h2>
                    <div class="threecal-quick">
                        <a href="<?php echo esc_url( $new_url ); ?>"><span class="threecal-quick-icon"><?php ThreeCal_Icons::render( 'calendar-plus', 22 ); ?></span><?php esc_html_e( 'Add New Event', '3task-calendar' ); ?></a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=3task-calendar&tab=categories' ) ); ?>"><span class="threecal-quick-icon"><?php ThreeCal_Icons::render( 'category', 22 ); ?></span><?php esc_html_e( 'Categories', '3task-calendar' ); ?></a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=3task-calendar&tab=design' ) ); ?>"><span class="threecal-quick-icon"><?php ThreeCal_Icons::render( 'palette', 22 ); ?></span><?php esc_html_e( 'Choose design', '3task-calendar' ); ?></a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=3task-calendar&tab=help' ) ); ?>"><span class="threecal-quick-icon"><?php ThreeCal_Icons::render( 'book', 22 ); ?></span><?php esc_html_e( 'Shortcodes', '3task-calendar' ); ?></a>
                    </div>
                </section>

                <section class="threecal-panel threecal-panel-tint">
                    <span class="threecal-pill"><?php esc_html_e( 'Included for free', '3task-calendar' ); ?></span>
                    <ul class="threecal-feature-chips">
                        <li><?php ThreeCal_Icons::render( 'repeat', 16 ); ?><?php esc_html_e( 'Recurring events', '3task-calendar' ); ?></li>
                        <li><?php ThreeCal_Icons::render( 'calendar-plus', 16 ); ?><?php esc_html_e( 'iCal subscription', '3task-calendar' ); ?></li>
                        <li><?php ThreeCal_Icons::render( 'palette', 16 ); ?><?php esc_html_e( '3 designs', '3task-calendar' ); ?></li>
                        <li><?php ThreeCal_Icons::render( 'route', 16 ); ?><?php esc_html_e( 'Route without Google', '3task-calendar' ); ?></li>
                        <li><?php ThreeCal_Icons::render( 'calendar-check', 16 ); ?><?php esc_html_e( 'Event schema (SEO)', '3task-calendar' ); ?></li>
                    </ul>
                </section>
            </div>
        </div>
        <?php
    }

    /**
     * Render design tab: default design and accent color.
     */
    private function render_design() {
        if ( ! current_user_can( 'threecal_settings' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to change the calendar settings.', '3task-calendar' ) . '</p></div>';
            return;
        }

        $themes = ThreeCal_Themes::all();

        if ( isset( $_POST['threecal_save_design'] ) ) {
            check_admin_referer( 'threecal_design' );
            $settings = (array) get_option( 'threecal_settings', array() );
            $theme    = isset( $_POST['default_theme'] ) ? sanitize_key( wp_unslash( $_POST['default_theme'] ) ) : 'default';
            $settings['default_theme'] = isset( $themes[ $theme ] ) ? $theme : 'default';
            $accent   = isset( $_POST['accent_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['accent_color'] ) ) : '';
            $settings['accent_color']  = $accent ? $accent : '';
            update_option( 'threecal_settings', $settings );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved!', '3task-calendar' ) . '</p></div>';
        }

        $current  = ThreeCal_Themes::normalize( '' );
        $settings = (array) get_option( 'threecal_settings', array() );
        $accent   = isset( $settings['accent_color'] ) ? $settings['accent_color'] : '';
        $renderer = new ThreeCal_Calendar_Renderer();
        wp_enqueue_style( 'threecal-public', THREECAL_PLUGIN_URL . 'public/css/threecal.css', array(), threecal_asset_version( 'public/css/threecal.css' ) );
        wp_enqueue_style( 'threecal-mini', THREECAL_PLUGIN_URL . 'public/css/threecal-mini.css', array(), threecal_asset_version( 'public/css/threecal-mini.css' ) );
        ?>
        <form method="post" class="threecal-design-form">
            <?php wp_nonce_field( 'threecal_design' ); ?>
            <section class="threecal-panel">
                <h2 class="threecal-panel-title"><?php ThreeCal_Icons::render( 'palette', 20 ); ?><?php esc_html_e( 'Default design', '3task-calendar' ); ?></h2>
                <p class="threecal-muted"><?php esc_html_e( 'Used by every calendar without its own theme attribute. A single calendar can still use another design, e.g. [threecal theme="dark"].', '3task-calendar' ); ?></p>
                <p class="threecal-muted"><?php esc_html_e( 'Elements without own design, such as the mini calendar in the sidebar, take the design of the calendar on the same page.', '3task-calendar' ); ?></p>
                <div class="threecal-design-grid">
                    <?php foreach ( $themes as $key => $theme ) : ?>
                    <label class="threecal-design-option<?php echo $current === $key ? ' is-selected' : ''; ?>">
                        <input type="radio" name="default_theme" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current, $key ); ?>>
                        <span class="threecal-design-preview">
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the renderer.
                            echo $renderer->render_mini_calendar( array( 'theme' => $key, 'show_nav' => false, 'show_today' => false ) );
                            ?>
                        </span>
                        <span class="threecal-design-name"><?php echo esc_html( $theme['label'] ); ?> <code><?php echo esc_html( $key ); ?></code></span>
                        <span class="threecal-design-desc"><?php echo esc_html( $theme['description'] ); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="threecal-panel">
                <h2 class="threecal-panel-title"><?php ThreeCal_Icons::render( 'palette', 20 ); ?><?php esc_html_e( 'Accent color', '3task-calendar' ); ?></h2>
                <p class="threecal-muted"><?php esc_html_e( 'Buttons, today marker and events without category color use this color. Leave empty to use the color of the design.', '3task-calendar' ); ?></p>
                <input type="text" name="accent_color" class="threecal-color-picker" value="<?php echo esc_attr( $accent ); ?>" data-default-color="">
                <p class="threecal-muted"><?php esc_html_e( 'Events take the color of their first category automatically.', '3task-calendar' ); ?></p>
            </section>

            <p><button type="submit" name="threecal_save_design" class="threecal-btn-primary"><?php esc_html_e( 'Save design', '3task-calendar' ); ?></button></p>
        </form>
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

        // List events (repetitions of a series are managed through their first event).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display filter only.
        $scope  = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'upcoming';
        $scope  = in_array( $scope, array( 'upcoming', 'past', 'all' ), true ) ? $scope : 'upcoming';
        $events = $this->get_events( $scope );

        // Dates that come from posts are kept in the posts, not in this list.
        $from_posts = 0;
        foreach ( $events as $index => $row ) {
            $row_settings = json_decode( (string) $row->settings, true );
            if ( ! empty( $row_settings['post_id'] ) ) {
                $from_posts++;
                unset( $events[ $index ] );
            }
        }
        $events = array_values( $events );
        $cats   = ThreeCal_Event::get_categories_for_events( wp_list_pluck( $events, 'id' ) );
        $rules  = ThreeCal_Event::recurrence_options();
        $scopes = array(
            'upcoming' => __( 'Upcoming', '3task-calendar' ),
            'past'     => __( 'Past', '3task-calendar' ),
            'all'      => __( 'All', '3task-calendar' ),
        );
        $status_labels = array(
            'published' => __( 'Published', '3task-calendar' ),
            'draft'     => __( 'Draft', '3task-calendar' ),
            'cancelled' => __( 'Cancelled', '3task-calendar' ),
        );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Message param is for display only.
        $message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';
        ?>
        <?php if ( $from_posts ) : ?>
        <div class="notice notice-info inline threecal-from-posts-note"><p>
            <?php
            /* translators: %s: number of dates */
            echo esc_html( sprintf( _n( '%s date comes from a post and is edited there:', '%s dates come from posts and are edited there:', $from_posts, '3task-calendar' ), number_format_i18n( $from_posts ) ) );
            foreach ( ThreeCal_Post_Dates::enabled_types() as $type_key => $type_settings ) {
                $type_object = get_post_type_object( $type_key );
                if ( $type_object ) {
                    echo ' <a href="' . esc_url( admin_url( 'edit.php?post_type=' . $type_key . '&orderby=threecal_date&order=asc' ) ) . '">' . esc_html( $type_object->labels->name ) . '</a>';
                }
            }
            ?>
        </p></div>
        <?php endif; ?>
        <?php if ( 'deleted' === $message ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Event deleted.', '3task-calendar' ); ?></p></div>
        <?php elseif ( 'date_remove' === $message ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The date was removed from the series.', '3task-calendar' ); ?></p></div>
        <?php endif; ?>
        <div class="threecal-list-toolbar">
            <div class="threecal-segmented" role="group" aria-label="<?php esc_attr_e( 'Filter', '3task-calendar' ); ?>">
                <?php foreach ( $scopes as $key => $label ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=3task-calendar&tab=events&scope=' . $key ) ); ?>" class="<?php echo $scope === $key ? 'is-active' : ''; ?>"<?php echo $scope === $key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=3task-calendar&tab=events&action=new' ) ); ?>" class="threecal-btn-primary">
                <?php ThreeCal_Icons::render( 'plus', 16 ); ?>
                <?php esc_html_e( 'Add New Event', '3task-calendar' ); ?>
            </a>
        </div>

        <?php if ( empty( $events ) ) : ?>
            <div class="threecal-empty">
                <?php ThreeCal_Icons::render( 'calendar-plus', 40 ); ?>
                <p><?php esc_html_e( 'No events found. Create your first event to get started!', '3task-calendar' ); ?></p>
            </div>
        <?php else : ?>
            <ul class="threecal-event-rows">
                <?php foreach ( $events as $row ) :
                    $event    = new ThreeCal_Event( $row );
                    $ecats    = isset( $cats[ $event->id ] ) ? $cats[ $event->id ] : array();
                    $color    = ThreeCal_Themes::event_color( $event, $ecats );
                    $start    = strtotime( $event->start_date );
                    $edit_url = admin_url( 'admin.php?page=3task-calendar&tab=events&action=edit&event=' . $event->id );
                    $del_url  = wp_nonce_url( admin_url( 'admin.php?page=3task-calendar&tab=events&action=delete&id=' . $event->id ), 'threecal_delete_' . $event->id );
                    ?>
                <li class="threecal-event-row status-<?php echo esc_attr( $event->status ); ?>" style="<?php echo esc_attr( ThreeCal_Themes::event_style( $color ) ); ?>">
                    <span class="threecal-admin-date">
                        <span><?php echo esc_html( date_i18n( 'M', $start ) ); ?></span>
                        <strong><?php echo esc_html( date_i18n( 'j', $start ) ); ?></strong>
                    </span>
                    <span class="threecal-event-row-main">
                        <a class="threecal-event-row-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $event->title ); ?></a>
                        <span class="threecal-event-row-meta">
                            <span><?php ThreeCal_Icons::render( 'clock', 14 ); ?><?php echo esc_html( date_i18n( threecal_date_format(), $start ) . ( $event->all_day ? ' · ' . __( 'All day', '3task-calendar' ) : ' · ' . date_i18n( threecal_time_format(), $start ) ) ); ?></span>
                            <?php if ( ! empty( $event->recurrence_rule ) && isset( $rules[ $event->recurrence_rule ] ) ) : ?>
                            <span class="threecal-series-badge"><?php ThreeCal_Icons::render( 'repeat', 12 ); ?><?php echo esc_html( $rules[ $event->recurrence_rule ] ); ?><?php if ( $event->recurrence_end ) : ?>, <?php echo esc_html( sprintf( /* translators: %s: end date of a series */ __( 'until %s', '3task-calendar' ), date_i18n( threecal_date_format(), strtotime( $event->recurrence_end ) ) ) ); ?><?php endif; ?></span>
                            <?php endif; ?>
                            <?php foreach ( $ecats as $cat ) : ?>
                            <span class="threecal-cat-chip" style="<?php echo esc_attr( ThreeCal_Themes::event_style( sanitize_hex_color( (string) $cat->color ) ) ); ?>"><?php echo esc_html( $cat->name ); ?></span>
                            <?php endforeach; ?>
                        </span>
                    </span>
                    <span class="threecal-status threecal-status-<?php echo esc_attr( $event->status ); ?>"><?php echo esc_html( isset( $status_labels[ $event->status ] ) ? $status_labels[ $event->status ] : $event->status ); ?></span>
                    <span class="threecal-event-row-actions">
                        <a class="threecal-icon-btn" href="<?php echo esc_url( $edit_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: event title */ __( 'Edit %s', '3task-calendar' ), $event->title ) ); ?>"><?php ThreeCal_Icons::render( 'pencil-plus', 18 ); ?></a>
                        <a class="threecal-icon-btn is-danger" href="<?php echo esc_url( $del_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: event title */ __( 'Delete %s', '3task-calendar' ), $event->title ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this event?', '3task-calendar' ) ); ?>');"><?php ThreeCal_Icons::render( 'x', 18 ); ?></a>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
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
        if ( ! current_user_can( 'threecal_settings' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to change the calendar settings.', '3task-calendar' ) . '</p></div>';
            return;
        }

        if ( isset( $_POST['submit'] ) ) {
            check_admin_referer( 'threecal_settings' );

            // Merge with the stored settings so fields that are not part of this form stay untouched.
            $settings = (array) get_option( 'threecal_settings', array() );

            $settings['date_format']              = isset( $_POST['date_format'] ) ? sanitize_text_field( wp_unslash( $_POST['date_format'] ) ) : '';
            $settings['time_format']              = isset( $_POST['time_format'] ) ? sanitize_text_field( wp_unslash( $_POST['time_format'] ) ) : '';
            $settings['week_starts_on']           = ( isset( $_POST['week_starts_on'] ) && 0 === absint( $_POST['week_starts_on'] ) ) ? 0 : 1;
            $settings['show_event_time']          = ! empty( $_POST['show_event_time'] );
            $settings['show_event_location']      = ! empty( $_POST['show_event_location'] );
            $settings['enable_schema']            = ! empty( $_POST['enable_schema'] );
            $settings['delete_data_on_uninstall'] = ! empty( $_POST['delete_data_on_uninstall'] );

            update_option( 'threecal_settings', $settings );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved!', '3task-calendar' ) . '</p></div>';
        }

        $settings = wp_parse_args(
            (array) get_option( 'threecal_settings', array() ),
            array(
                'date_format'              => '',
                'time_format'              => '',
                'week_starts_on'           => 1,
                'show_event_time'          => true,
                'show_event_location'      => true,
                'enable_schema'            => true,
                'delete_data_on_uninstall' => false,
            )
        );
        ?>
        <form method="post">
            <?php wp_nonce_field('threecal_settings'); ?>
            
            <div class="threecal-card">
                <h3><?php esc_html_e( 'General Settings', '3task-calendar' ); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Date Format', '3task-calendar' ); ?></th>
                        <td>
                            <input type="text" name="date_format" value="<?php echo esc_attr($settings['date_format']); ?>" placeholder="<?php echo esc_attr( get_option( 'date_format' ) ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Leave empty to use the date format from the WordPress settings.', '3task-calendar' ); ?> <?php /* translators: %s: example date or time */ printf( esc_html__( 'Currently: %s', '3task-calendar' ), esc_html( date_i18n( threecal_date_format() ) ) ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Time Format', '3task-calendar' ); ?></th>
                        <td>
                            <input type="text" name="time_format" value="<?php echo esc_attr($settings['time_format']); ?>" placeholder="<?php echo esc_attr( get_option( 'time_format' ) ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Leave empty to use the time format from the WordPress settings.', '3task-calendar' ); ?> <?php /* translators: %s: example date or time */ printf( esc_html__( 'Currently: %s', '3task-calendar' ), esc_html( date_i18n( threecal_time_format() ) ) ); ?></p>
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
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Event Schema (SEO)', '3task-calendar' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_schema" value="1" <?php checked($settings['enable_schema']); ?> />
                                <?php esc_html_e( 'Add Schema.org event markup to pages with a calendar.', '3task-calendar' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Uninstall', '3task-calendar' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked($settings['delete_data_on_uninstall']); ?> />
                                <?php esc_html_e( 'Delete all events, categories, locations and settings when the plugin is deleted.', '3task-calendar' ); ?>
                            </label>
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
                            <td><?php esc_html_e( 'Calendar view: month or list', '3task-calendar' ); ?></td>
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
                            <td><?php esc_html_e( 'Number of events per page', '3task-calendar' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>view</code></td>
                            <td>list</td>
                            <td><?php esc_html_e( 'Display style: list, grid, compact or poster', '3task-calendar' ); ?></td>
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
                            <td><?php esc_html_e( 'Number of columns in grid and poster view', '3task-calendar' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Dates from posts -->
            <div class="threecal-card">
                <h2><?php esc_html_e( 'Dates from posts', '3task-calendar' ); ?></h2>
                <p>
                    <?php esc_html_e( 'Set a date while you write a post and the post appears in the calendar. Switch it on for posts, pages or custom post types in the Posts tab, where you can also use a date field you already have.', '3task-calendar' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=3task-calendar&tab=posts' ) ); ?>"><?php esc_html_e( 'Open the Posts tab', '3task-calendar' ); ?></a>
                </p>

                <h4><?php esc_html_e( 'Date of a post with "Add to my calendar"', '3task-calendar' ); ?></h4>
                <code class="threecal-code-block">[threecal_post_date]</code>
                <p class="description"><?php esc_html_e( 'Inside a post it shows the date of that post. With id="123" it shows the date of another post, with theme="accent" another design. The block is called "Date of this post".', '3task-calendar' ); ?></p>

                <h4><?php esc_html_e( 'Posters of the upcoming dates', '3task-calendar' ); ?></h4>
                <code class="threecal-code-block">[threecal_events view="poster" category="1" columns="4"]</code>

                <h4><?php esc_html_e( 'A whole month', '3task-calendar' ); ?></h4>
                <code class="threecal-code-block">[threecal_events view="poster" month="current"]</code>
                <p class="description"><?php esc_html_e( 'month takes current, next or a month such as 2026-10 and shows all dates of that month, past days included. Works with every view.', '3task-calendar' ); ?></p>

                <h4><?php esc_html_e( 'Now showing', '3task-calendar' ); ?></h4>
                <code class="threecal-code-block">[threecal_events view="poster" running="40"]</code>
                <p class="description"><?php esc_html_e( 'Lists the dates that started within the last 40 days, newest first. For example films that are in cinemas now.', '3task-calendar' ); ?></p>
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
                    <?php esc_html_e( 'This is the ideal shortcode for sidebars! Days with events show colored dots. Click a day to see its events.', '3task-calendar' ); ?>
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
                    <li><?php esc_html_e( 'Add a "Text" widget (the "Custom HTML" widget does not run shortcodes)', '3task-calendar' ); ?></li>
                    <li><?php esc_html_e( 'Enter the shortcode in the content area', '3task-calendar' ); ?></li>
                </ol>

                <div class="threecal-help-tip" style="background: #f0f6fc; border-left: 4px solid #3788d8; padding: 12px 15px; margin-top: 15px;">
                    <strong><?php esc_html_e( 'Best Widget Shortcodes:', '3task-calendar' ); ?></strong><br>
                    <code>[threecal_mini]</code>: <?php esc_html_e( 'Compact month view with event markers (recommended!)', '3task-calendar' ); ?><br>
                    <code>[threecal_upcoming limit="3"]</code>: <?php esc_html_e( 'Simple list of upcoming events', '3task-calendar' ); ?>
                </div>
            </div>

            <!-- Features Overview -->
            <div class="threecal-card">
                <h2><?php esc_html_e( 'Features Overview', '3task-calendar' ); ?></h2>

                <div class="threecal-features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 15px;">
                    <div class="feature-item">
                        <span class="dashicons dashicons-calendar-alt" style="color: #3788d8;"></span>
                        <strong><?php esc_html_e( 'Interactive Calendar', '3task-calendar' ); ?></strong>
                        <p><?php esc_html_e( 'Month and list views with navigation', '3task-calendar' ); ?></p>
                    </div>
                    <div class="feature-item">
                        <span class="dashicons dashicons-category" style="color: #3788d8;"></span>
                        <strong><?php esc_html_e( 'Categories', '3task-calendar' ); ?></strong>
                        <p><?php esc_html_e( 'Organize events with color-coded categories', '3task-calendar' ); ?></p>
                    </div>
                    <div class="feature-item">
                        <span class="dashicons dashicons-location" style="color: #3788d8;"></span>
                        <strong><?php esc_html_e( 'Locations', '3task-calendar' ); ?></strong>
                        <p><?php esc_html_e( 'Add venues with their address', '3task-calendar' ); ?></p>
                    </div>
                    <div class="feature-item">
                        <span class="dashicons dashicons-smartphone" style="color: #3788d8;"></span>
                        <strong><?php esc_html_e( 'Responsive Design', '3task-calendar' ); ?></strong>
                        <p><?php esc_html_e( 'Adapts to small screens', '3task-calendar' ); ?></p>
                    </div>
                    <div class="feature-item">
                        <span class="dashicons dashicons-filter" style="color: #3788d8;"></span>
                        <strong><?php esc_html_e( 'Filters', '3task-calendar' ); ?></strong>
                        <p><?php esc_html_e( 'Let visitors filter by category or location', '3task-calendar' ); ?></p>
                    </div>
                    <div class="feature-item">
                        <span class="dashicons dashicons-admin-appearance" style="color: #3788d8;"></span>
                        <strong><?php esc_html_e( 'Customizable', '3task-calendar' ); ?></strong>
                        <p><?php esc_html_e( 'Five themes and several display options', '3task-calendar' ); ?></p>
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
        $total_events     = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}threecal_events WHERE parent_id IS NULL OR parent_id = 0" );
        $published_events = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}threecal_events WHERE status = 'published' AND (parent_id IS NULL OR parent_id = 0)" );
        $draft_events     = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}threecal_events WHERE status = 'draft' AND (parent_id IS NULL OR parent_id = 0)" );
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
    private function get_events( $scope = 'all' ) {
        global $wpdb;

        $now = current_time( 'mysql' );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for events.
        if ( 'upcoming' === $scope ) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}threecal_events
                    WHERE (parent_id IS NULL OR parent_id = 0)
                    AND (COALESCE(end_date, start_date) >= %s OR (recurrence_rule IS NOT NULL AND recurrence_rule <> '' AND COALESCE(recurrence_end, start_date) >= %s))
                    ORDER BY start_date ASC",
                    $now,
                    $now
                )
            );
        } elseif ( 'past' === $scope ) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}threecal_events
                    WHERE (parent_id IS NULL OR parent_id = 0)
                    AND COALESCE(end_date, start_date) < %s
                    AND (recurrence_rule IS NULL OR recurrence_rule = '' OR COALESCE(recurrence_end, start_date) < %s)
                    ORDER BY start_date DESC",
                    $now,
                    $now
                )
            );
        } else {
            $results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}threecal_events WHERE parent_id IS NULL OR parent_id = 0 ORDER BY start_date ASC" );
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

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
        $input  = is_array($input) ? $input : array();
        $themes = ThreeCal_Themes::all();
        $theme  = isset($input['default_theme']) ? sanitize_key($input['default_theme']) : 'default';
        $view   = isset($input['default_view']) ? sanitize_key($input['default_view']) : 'month';
        $accent = isset($input['accent_color']) ? sanitize_hex_color($input['accent_color']) : '';

        // Every key the plugin reads. A key missing here is lost on the next save.
        $sanitized = array(
            'date_format'              => sanitize_text_field($input['date_format'] ?? ''),
            'time_format'              => sanitize_text_field($input['time_format'] ?? ''),
            'week_starts_on'           => (isset($input['week_starts_on']) && 0 === absint($input['week_starts_on'])) ? 0 : 1,
            'default_view'             => in_array($view, array('month', 'list'), true) ? $view : 'month',
            'default_theme'            => isset($themes[$theme]) ? $theme : 'default',
            'accent_color'             => $accent ? $accent : '',
            'show_event_time'          => isset($input['show_event_time']) ? !empty($input['show_event_time']) : true,
            'show_event_location'      => isset($input['show_event_location']) ? !empty($input['show_event_location']) : true,
            'show_event_description'   => isset($input['show_event_description']) ? !empty($input['show_event_description']) : true,
            'events_per_page'          => max(1, absint($input['events_per_page'] ?? 10)),
            'enable_event_popup'       => isset($input['enable_event_popup']) ? !empty($input['enable_event_popup']) : true,
            'enable_schema'            => isset($input['enable_schema']) ? !empty($input['enable_schema']) : true,
            'delete_data_on_uninstall' => !empty($input['delete_data_on_uninstall']),
        );

        /**
         * Filters the sanitized settings, so extensions can keep their own keys.
         *
         * @param array $sanitized Sanitized settings.
         * @param array $input     Raw settings.
         */
        return apply_filters('threecal_sanitize_settings', $sanitized, $input);
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

        // Cancel, restore or remove a single date of a series.
        if ( isset( $_GET['threecal_date_action'], $_GET['series'], $_GET['date'], $_GET['_wpnonce'] ) ) {
            $series_id = absint( $_GET['series'] );
            $nonce     = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
            if ( wp_verify_nonce( $nonce, 'threecal_series_' . $series_id ) ) {
                $this->series_date_action(
                    $series_id,
                    sanitize_key( wp_unslash( $_GET['threecal_date_action'] ) ),
                    sanitize_text_field( wp_unslash( $_GET['date'] ) )
                );
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

        // Remember where a repetition came from, so its date can be excluded from the series.
        $original_parent = (int) $event->parent_id;
        $original_day    = substr( (string) $event->start_date, 0, 10 );

        $event->title          = isset( $_POST['event_title'] ) ? sanitize_text_field( wp_unslash( $_POST['event_title'] ) ) : '';
        $event->description    = isset( $_POST['event_description'] ) ? wp_kses_post( wp_unslash( $_POST['event_description'] ) ) : '';
        $event->start_date     = isset( $_POST['event_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_start_date'] ) ) : '';
        $event->end_date       = isset( $_POST['event_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_end_date'] ) ) : '';
        $event->all_day        = isset( $_POST['event_all_day'] );
        $event->location_id    = isset( $_POST['event_location'] ) ? absint( $_POST['event_location'] ) : 0;
        $event->url            = isset( $_POST['event_url'] ) ? esc_url_raw( wp_unslash( $_POST['event_url'] ) ) : '';
        $event->featured_image = isset( $_POST['event_featured_image'] ) ? absint( $_POST['event_featured_image'] ) : 0;
        $event->color          = isset( $_POST['event_color'] ) ? ( sanitize_hex_color( wp_unslash( $_POST['event_color'] ) ) ?: '#3788d8' ) : '#3788d8';
        $event->status         = isset( $_POST['event_status'] ) ? sanitize_key( wp_unslash( $_POST['event_status'] ) ) : 'published';

        if ( ! in_array( $event->status, array( 'draft', 'published', 'cancelled' ), true ) ) {
            $event->status = 'draft';
        }

        // Normalise dates (datetime-local sends Y-m-d\TH:i) and reject invalid input.
        $event->start_date = $this->normalize_datetime( $event->start_date );
        $event->end_date   = $event->end_date ? $this->normalize_datetime( $event->end_date ) : '';

        if ( '' === $event->title || '' === $event->start_date ) {
            wp_die( esc_html__( 'Please enter a title and a valid start date.', '3task-calendar' ), '', array( 'back_link' => true ) );
        }

        // An end before the start is treated as "no end".
        if ( $event->end_date && $event->end_date < $event->start_date ) {
            $event->end_date = '';
        }

        // Repeat pattern. Editing a single repetition detaches it from its series.
        if ( $event->parent_id ) {
            $event->parent_id       = 0;
            $event->recurrence_rule = '';
            $event->recurrence_end  = '';
        } else {
            $rule = isset( $_POST['event_recurrence'] ) ? sanitize_key( wp_unslash( $_POST['event_recurrence'] ) ) : '';
            $event->recurrence_rule = array_key_exists( $rule, ThreeCal_Event::recurrence_options() ) ? $rule : '';
            $until = isset( $_POST['event_recurrence_end'] ) ? $this->normalize_datetime( sanitize_text_field( wp_unslash( $_POST['event_recurrence_end'] ) ) ) : '';
            $event->recurrence_end = ( $event->recurrence_rule && $until ) ? substr( $until, 0, 10 ) . ' 23:59:59' : '';
        }

        if ( $event->save() ) {
            // Set categories.
            $categories = isset( $_POST['event_categories'] ) ? array_map( 'absint', $_POST['event_categories'] ) : array();
            $event->set_categories( $categories );
            // phpcs:enable WordPress.Security.NonceVerification.Missing

            // Create or update the repetitions of a series.
            $event->regenerate_series();

            // A detached repetition becomes an exception of its series.
            if ( $original_parent && $original_day ) {
                $parent = ThreeCal_Event::get( $original_parent );
                if ( $parent ) {
                    $exdates   = (array) $parent->get_setting( 'exdates', array() );
                    $exdates[] = $original_day;
                    $parent->set_setting( 'exdates', array_values( array_unique( $exdates ) ) );
                    $parent->save();
                }
            }

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
     * Convert a submitted date to Y-m-d H:i:s or return an empty string.
     *
     * @param string $value Submitted value.
     * @return string
     */
    private function normalize_datetime( $value ) {
        $value = trim( str_replace( 'T', ' ', (string) $value ) );
        foreach ( array( 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d' ) as $format ) {
            $date = DateTime::createFromFormat( '!' . $format, $value );
            if ( $date && $date->format( $format ) === $value ) {
                return $date->format( 'Y-m-d H:i:s' );
            }
        }
        return '';
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
                'tab' => 'events',
                'message' => 'deleted'
            ), admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Cancel, restore or remove a single date of a series.
     *
     * @param int    $series_id ID of the series (its first event).
     * @param string $action    cancel, restore or remove.
     * @param string $day       Date (Y-m-d).
     */
    private function series_date_action( $series_id, $action, $day ) {
        if ( ! current_user_can( 'edit_threecal_events' ) ) {
            wp_die( esc_html__( 'Permission denied.', '3task-calendar' ) );
        }

        $series = ThreeCal_Event::get( $series_id );
        if ( ! $series || $series->parent_id || ! $series->recurrence_rule || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) || ! in_array( $action, array( 'cancel', 'restore', 'remove' ), true ) ) {
            wp_die( esc_html__( 'This date could not be changed.', '3task-calendar' ), '', array( 'back_link' => true ) );
        }

        $target = $series->id;
        $split  = null;
        foreach ( $series->get_split_events() as $candidate ) {
            if ( substr( (string) $candidate->start_date, 0, 10 ) === $day ) {
                $split = $candidate;
                break;
            }
        }

        if ( $split ) {
            // A former first date that is a separate event now.
            if ( 'remove' === $action ) {
                $split->delete();
                $ids = array_diff( array_map( 'absint', (array) $series->get_setting( 'split_dates', array() ) ), array( (int) $split->id ) );
                $series->set_setting( 'split_dates', array_values( $ids ) );
                $series->save();
            } else {
                $split->status = ( 'cancel' === $action && 'published' === $series->status ) ? 'cancelled' : $series->status;
                $split->save();
            }
        } elseif ( substr( (string) $series->start_date, 0, 10 ) === $day ) {
            // The first date is the series itself: the next date takes the series over.
            $next = $series->split_off_first_date();
            if ( 'remove' === $action ) {
                $series->delete();
            } elseif ( 'cancel' === $action ) {
                $series->status = 'cancelled';
                $series->save();
            }
            $target = $next ? $next : ( 'remove' === $action ? 0 : $series->id );
        } elseif ( 'remove' === $action ) {
            $series->remove_date( $day );
        } else {
            $series->set_date_cancelled( $day, 'cancel' === $action );
        }

        $args = array(
            'page'    => '3task-calendar',
            'tab'     => 'events',
            'message' => 'date_' . $action,
        );
        if ( $target ) {
            $args['action'] = 'edit';
            $args['event']  = $target;
        }

        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
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

        $location->latitude  = ( isset( $_POST['location_latitude'] ) && '' !== trim( sanitize_text_field( wp_unslash( $_POST['location_latitude'] ) ) ) ) ? floatval( $_POST['location_latitude'] ) : null;
        $location->longitude = ( isset( $_POST['location_longitude'] ) && '' !== trim( sanitize_text_field( wp_unslash( $_POST['location_longitude'] ) ) ) ) ? floatval( $_POST['location_longitude'] ) : null;
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
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions.
            ThreeCal_Category::set_schema_excluded( $category->id, ! empty( $_POST['category_no_schema'] ) );
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
            ThreeCal_Category::set_schema_excluded($id, false);
            wp_safe_redirect( add_query_arg( array(
                'page' => '3task-calendar',
                'tab' => 'categories',
                'message' => 'deleted'
            ), admin_url('admin.php')));
            exit;
        }
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

}
