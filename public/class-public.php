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
                /* translators: Date in the list view. Keep {day} and {month}, they are replaced with the day number and the month name. */
                'list_date' => __('{month} {day}', '3task-calendar'),
                'cancelled' => __('Cancelled', '3task-calendar'),
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
