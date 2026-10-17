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
        // Icons are inline SVG, no icon font needed.
        wp_register_style(
            'threecal-public',
            THREECAL_PLUGIN_URL . 'public/css/threecal.css',
            array(),
            threecal_asset_version('public/css/threecal.css')
        );

        // Mini calendar styles
        wp_register_style(
            'threecal-mini',
            THREECAL_PLUGIN_URL . 'public/css/threecal-mini.css',
            array(),
            threecal_asset_version('public/css/threecal-mini.css')
        );
    }

    /**
     * Enqueue public scripts
     */
    public function enqueue_scripts() {
        wp_register_script(
            'threecal-public',
            THREECAL_PLUGIN_URL . 'public/js/threecal.js',
            array('jquery'),
            threecal_asset_version('public/js/threecal.js'),
            true
        );

        wp_localize_script('threecal-public', 'threecal_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'icons' => ThreeCal_Icons::for_js(),
            'rest_url' => rest_url('3task-calendar/v1/'),
            'settings' => array(
                'date_format' => threecal_date_format(),
                'time_format' => threecal_time_format(),
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
                'add_to_calendar' => __('Add to my calendar', '3task-calendar'),
                'plan_route' => __('Plan route (OpenStreetMap)', '3task-calendar'),
                'range_to' => _x('to', 'between two dates or times', '3task-calendar'),
                /* translators: Date in the list view. Keep {day} and {month}, they are replaced with the day number and the month name. */
                'list_date' => __('{month} {day}', '3task-calendar'),
                'cancelled' => __('Cancelled', '3task-calendar'),
                'month_tba' => __('This month, date to be announced', '3task-calendar'),
                // Search and day filter (since 1.8.0)
                'reset_search' => __('Reset search', '3task-calendar'),
                'all_events' => __('All events', '3task-calendar'),
                /* translators: {date} is replaced with the date, for example "Friday, October 24, 2026". */
                'events_on' => __('Events on {date}', '3task-calendar'),
                /* translators: {date} is replaced with the date. */
                'no_events_on' => __('No events on {date}', '3task-calendar'),
                /* translators: {term} is replaced with the search term. */
                'no_events_for' => __('No events for “{term}”', '3task-calendar'),
                /* translators: {term} is replaced with the search term. */
                'one_event_for' => __('One event for “{term}”', '3task-calendar'),
                /* translators: {count} is the number of events, {term} the search term. */
                'events_for' => __('{count} events for “{term}”', '3task-calendar'),
                /* translators: Date with year in headings of search results and day lists. Keep {day}, {month} and {year}, they are replaced with the day number, the month name and the year. */
                'list_date_year' => __('{month} {day}, {year}', '3task-calendar'),
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
            threecal_asset_version('public/js/threecal-mini.js'),
            true
        );
    }
}
