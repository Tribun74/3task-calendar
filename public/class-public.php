<?php
/**
 * ThreeCal Public
 *
 * Handles all frontend functionality.
 */

if (!defined('ABSPATH')) {
    exit;
}

class ThreeCal_Public {

    /**
     * Plugin name
     */
    private $plugin_name;

    /**
     * Version
     */
    private $version;

    /**
     * Settings
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->settings = get_option('threecal_settings', array());
    }

    /**
     * Enqueue public styles
     */
    public function enqueue_styles() {
        wp_register_style(
            'threecal-public',
            THREECAL_PLUGIN_URL . 'public/css/threecal.css',
            array(),
            $this->version
        );

        // Mini calendar styles
        wp_register_style(
            'threecal-mini',
            THREECAL_PLUGIN_URL . 'public/css/threecal-mini.css',
            array(),
            $this->version
        );

        // Dashicons for icons
        wp_enqueue_style('dashicons');
    }

    /**
     * Enqueue public scripts
     */
    public function enqueue_scripts() {
        wp_register_script(
            'threecal-public',
            THREECAL_PLUGIN_URL . 'public/js/threecal.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script('threecal-public', 'threecal_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('3task-calendar/v1/'),
            'nonce' => wp_create_nonce('threecal_nonce'),
            'settings' => array(
                'date_format' => $this->settings['date_format'] ?? get_option('date_format'),
                'time_format' => $this->settings['time_format'] ?? get_option('time_format'),
                'week_starts_on' => $this->settings['week_starts_on'] ?? 1,
                'enable_popup' => $this->settings['enable_event_popup'] ?? true
            ),
            'i18n' => array(
                'loading' => __('Loading...', '3task-calendar'),
                'no_events' => __('No events', '3task-calendar'),
                'more' => __('more', '3task-calendar'),
                'all_day' => __('All day', '3task-calendar'),
                'close' => __('Close', '3task-calendar'),
                'error' => __('An error occurred. Please try again.', '3task-calendar'),
                'more_info' => __('More information', '3task-calendar'),
                'months' => array(
                    __('January', '3task-calendar'),
                    __('February', '3task-calendar'),
                    __('March', '3task-calendar'),
                    __('April', '3task-calendar'),
                    __('May', '3task-calendar'),
                    __('June', '3task-calendar'),
                    __('July', '3task-calendar'),
                    __('August', '3task-calendar'),
                    __('September', '3task-calendar'),
                    __('October', '3task-calendar'),
                    __('November', '3task-calendar'),
                    __('December', '3task-calendar')
                ),
                'weekdays' => array(
                    __('Sun', '3task-calendar'),
                    __('Mon', '3task-calendar'),
                    __('Tue', '3task-calendar'),
                    __('Wed', '3task-calendar'),
                    __('Thu', '3task-calendar'),
                    __('Fri', '3task-calendar'),
                    __('Sat', '3task-calendar')
                ),
                'weekdays_full' => array(
                    __('Sunday', '3task-calendar'),
                    __('Monday', '3task-calendar'),
                    __('Tuesday', '3task-calendar'),
                    __('Wednesday', '3task-calendar'),
                    __('Thursday', '3task-calendar'),
                    __('Friday', '3task-calendar'),
                    __('Saturday', '3task-calendar')
                )
            )
        ));

        // Mini calendar script
        wp_register_script(
            'threecal-mini',
            THREECAL_PLUGIN_URL . 'public/js/threecal-mini.js',
            array(),
            $this->version,
            true
        );

        // Google Maps API if key exists.
        $maps_key = $this->settings['google_maps_api_key'] ?? '';
        if ( ! empty( $maps_key ) ) {
            wp_register_script(
                'google-maps',
                'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $maps_key ),
                array(),
                '3.0', // Google Maps JavaScript API version.
                true
            );
        }
    }

    /**
     * Register AJAX handlers
     */
    public function register_ajax_handlers() {
        // Handle unsubscribe requests - token is unique and serves as verification.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token-based authentication, no nonce needed.
        if ( isset( $_GET['threecal_unsubscribe'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $token = sanitize_text_field( wp_unslash( $_GET['threecal_unsubscribe'] ) );
            if ( ThreeCal_Email::unsubscribe( $token ) ) {
                add_action(
                    'wp_enqueue_scripts',
                    function () {
                        wp_enqueue_script( 'jquery' );
                        $message = esc_js( __( 'You have been unsubscribed successfully.', '3task-calendar' ) );
                        wp_add_inline_script( 'jquery', 'alert("' . $message . '");' );
                    }
                );
            }
        }
    }

    /**
     * Get events for a specific month (AJAX)
     */
    public function ajax_get_month_events() {
        check_ajax_referer( 'threecal_nonce', 'nonce' );

        $month    = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : (int) gmdate( 'n' );
        $year     = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : (int) gmdate( 'Y' );
        $category = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
        $location = isset( $_POST['location'] ) ? absint( $_POST['location'] ) : 0;

        // Calculate date range.
        $start_date = gmdate( 'Y-m-01 00:00:00', mktime( 0, 0, 0, $month, 1, $year ) );
        $end_date   = gmdate( 'Y-m-t 23:59:59', mktime( 0, 0, 0, $month, 1, $year ) );

        $args = array(
            'status' => 'published',
            'start_after' => $start_date,
            'start_before' => $end_date
        );

        if ($category > 0) {
            $args['category_id'] = $category;
        }

        if ($location > 0) {
            $args['location_id'] = $location;
        }

        $events = ThreeCal_Event::get_all($args);
        $data = array();

        foreach ($events as $event) {
            $location_data = null;
            if ($event->location_id) {
                $loc = ThreeCal_Location::get($event->location_id);
                if ($loc) {
                    $location_data = array(
                        'name' => $loc->name,
                        'address' => $loc->get_full_address()
                    );
                }
            }

            $data[] = array(
                'id'          => $event->id,
                'title'       => $event->title,
                'description' => wp_trim_words( wp_strip_all_tags( $event->description ), 30 ),
                'start'       => $event->start_date,
                'end'         => $event->end_date,
                'allDay'      => (bool) $event->all_day,
                'color'       => $event->color,
                'url'         => $event->url,
                'location'    => $location_data,
                'day'         => (int) gmdate( 'j', strtotime( $event->start_date ) ),
            );
        }

        wp_send_json_success($data);
    }

    /**
     * Get single event details (AJAX)
     */
    public function ajax_get_event_details() {
        check_ajax_referer('threecal_nonce', 'nonce');

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Invalid event ID', '3task-calendar')));
        }

        $event = ThreeCal_Event::get($event_id);

        if (!$event || $event->status !== 'published') {
            wp_send_json_error(array('message' => __('Event not found', '3task-calendar')));
        }

        $location = null;
        if ($event->location_id) {
            $loc = ThreeCal_Location::get($event->location_id);
            if ($loc) {
                $location = array(
                    'name' => $loc->name,
                    'address' => $loc->get_full_address(),
                    'latitude' => $loc->latitude,
                    'longitude' => $loc->longitude
                );
            }
        }

        $categories = ThreeCal_Event::get_categories($event->id);
        $category_data = array();
        foreach ($categories as $cat) {
            $category_data[] = array(
                'name' => $cat->name,
                'color' => $cat->color
            );
        }

        $date_format = $this->settings['date_format'] ?? get_option('date_format');
        $time_format = $this->settings['time_format'] ?? get_option('time_format');

        $data = array(
            'id' => $event->id,
            'title' => $event->title,
            'description' => wp_kses_post(wpautop($event->description)),
            'start_date' => date_i18n($date_format, strtotime($event->start_date)),
            'start_time' => $event->all_day ? __('All day', '3task-calendar') : date_i18n($time_format, strtotime($event->start_date)),
            'end_date' => $event->end_date ? date_i18n($date_format, strtotime($event->end_date)) : null,
            'end_time' => $event->end_date && !$event->all_day ? date_i18n($time_format, strtotime($event->end_date)) : null,
            'all_day' => (bool) $event->all_day,
            'color' => $event->color,
            'url' => $event->url,
            'featured_image' => $event->featured_image ? wp_get_attachment_url($event->featured_image) : null,
            'location' => $location,
            'categories' => $category_data
        );

        wp_send_json_success($data);
    }
}
