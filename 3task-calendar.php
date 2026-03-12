<?php
/**
 * Plugin Name:       3task Calendar
 * Plugin URI:        https://wordpress.org/plugins/3task-calendar/
 * Description:       Professional WordPress Event Calendar with beautiful themes, categories, and modern design. Create and display events with month and list views.
 * Version:           1.2.2
 * Author:            3task
 * Author URI:        https://www.3task.de
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       3task-calendar
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Tested up to:      6.9
 *
 * @package ThreeCal
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('THREECAL_VERSION', '1.2.2');
define('THREECAL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('THREECAL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('THREECAL_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('THREECAL_DB_VERSION', '1.0.0');

/**
 * Main ThreeCal Class
 */
final class ThreeCal {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Admin instance
     */
    public $admin = null;

    /**
     * Public instance
     */
    public $public = null;

    /**
     * Get instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->init_hooks();
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once THREECAL_PLUGIN_DIR . 'includes/class-activator.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-deactivator.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-i18n.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-event.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-location.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-category.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-shortcode.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-calendar-renderer.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-email.php';

        // Admin classes
        if (is_admin()) {
            require_once THREECAL_PLUGIN_DIR . 'admin/class-admin.php';
            require_once THREECAL_PLUGIN_DIR . 'admin/class-events-list.php';
        }

        // Public classes
        require_once THREECAL_PLUGIN_DIR . 'public/class-public.php';
    }

    /**
     * Set locale
     *
     * Note: WordPress 4.6+ automatically loads translations from the languages directory.
     * Manual load_plugin_textdomain() is discouraged by WordPress.org Plugin Guidelines.
     */
    private function set_locale() {
        // WordPress 4.6+ automatically loads translations from:
        // wp-content/languages/plugins/3task-calendar-{locale}.mo
        // No manual loading needed per WordPress.org guidelines.
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin
        if (is_admin()) {
            $this->admin = new ThreeCal_Admin('3task-calendar', THREECAL_VERSION);
            add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_styles'));
            add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_scripts'));
            add_action('admin_menu', array($this->admin, 'add_menu_pages'));
            add_action('admin_init', array($this->admin, 'register_settings'));
            add_action('admin_init', array($this, 'maybe_add_capabilities'));
        }

        // Public
        $this->public = new ThreeCal_Public('3task-calendar', THREECAL_VERSION);
        add_action('wp_enqueue_scripts', array($this->public, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this->public, 'enqueue_scripts'));

        // Shortcodes
        new ThreeCal_Shortcode();

        // Register block
        add_action('init', array($this, 'register_block'));

        // REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Schema.org SEO
        add_action('wp_head', array($this, 'output_schema'));

        // AJAX handlers
        add_action('wp_ajax_threecal_get_events', array($this, 'ajax_get_events'));
        add_action('wp_ajax_nopriv_threecal_get_events', array($this, 'ajax_get_events'));
        add_action('wp_ajax_threecal_get_event_details', array($this, 'ajax_get_event_details'));
        add_action('wp_ajax_nopriv_threecal_get_event_details', array($this, 'ajax_get_event_details'));
    }

    /**
     * Register Gutenberg block
     */
    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        wp_register_script(
            'threecal-block-editor',
            THREECAL_PLUGIN_URL . 'blocks/calendar-block/index.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render'),
            THREECAL_VERSION,
            true
        );

        wp_register_style(
            'threecal-block-editor',
            THREECAL_PLUGIN_URL . 'blocks/calendar-block/editor.css',
            array(),
            THREECAL_VERSION
        );

        register_block_type('3task-calendar/calendar', array(
            'editor_script' => 'threecal-block-editor',
            'editor_style' => 'threecal-block-editor',
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'view' => array(
                    'type' => 'string',
                    'default' => 'month'
                ),
                'category' => array(
                    'type' => 'number',
                    'default' => 0
                ),
                'theme' => array(
                    'type' => 'string',
                    'default' => 'default'
                )
            )
        ));
    }

    /**
     * Render block callback
     */
    public function render_block($attributes) {
        $view = isset($attributes['view']) ? $attributes['view'] : 'month';
        $category = isset($attributes['category']) ? absint($attributes['category']) : 0;
        $theme = isset($attributes['theme']) ? $attributes['theme'] : 'default';

        $shortcode = '[threecal view="' . esc_attr($view) . '"';
        if ($category > 0) {
            $shortcode .= ' category="' . $category . '"';
        }
        $shortcode .= ' theme="' . esc_attr($theme) . '"]';

        return do_shortcode($shortcode);
    }

    /**
     * Output Schema.org markup for events
     */
    public function output_schema() {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        // Find calendar shortcodes in content
        if (has_shortcode($post->post_content, 'threecal') ||
            has_shortcode($post->post_content, 'threecal_event')) {

            // Get upcoming events for schema
            $events = ThreeCal_Event::get_upcoming(5);

            foreach ($events as $event) {
                $this->output_event_schema($event);
            }
        }
    }

    /**
     * Output individual event schema
     */
    private function output_event_schema($event) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->title,
            'description' => wp_strip_all_tags($event->description),
            'startDate' => $event->start_date,
            'endDate' => $event->end_date ?: $event->start_date,
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode'
        );

        // Add location if available
        if ($event->location_id) {
            $location = ThreeCal_Location::get($event->location_id);
            if ($location) {
                $schema['location'] = array(
                    '@type' => 'Place',
                    'name' => $location->name,
                    'address' => array(
                        '@type' => 'PostalAddress',
                        'streetAddress' => $location->address,
                        'addressLocality' => $location->city,
                        'postalCode' => $location->postal_code,
                        'addressCountry' => $location->country
                    )
                );

                if ($location->latitude && $location->longitude) {
                    $schema['location']['geo'] = array(
                        '@type' => 'GeoCoordinates',
                        'latitude' => $location->latitude,
                        'longitude' => $location->longitude
                    );
                }
            }
        }

        // Add organizer
        $schema['organizer'] = array(
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'url' => home_url()
        );

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('3task-calendar/v1', '/events', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_events'),
            'permission_callback' => '__return_true',
            'args' => array(
                'start' => array(
                    'required' => false,
                    'type' => 'string'
                ),
                'end' => array(
                    'required' => false,
                    'type' => 'string'
                ),
                'category' => array(
                    'required' => false,
                    'type' => 'integer'
                )
            )
        ));

        register_rest_route('3task-calendar/v1', '/events/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_event'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * REST callback: Get events
     */
    public function rest_get_events($request) {
        $start = $request->get_param('start');
        $end = $request->get_param('end');
        $category = $request->get_param('category');

        $args = array(
            'status' => 'published'
        );

        if ($start) {
            $args['start_after'] = $start;
        }
        if ($end) {
            $args['start_before'] = $end;
        }
        if ($category) {
            $args['category_id'] = $category;
        }

        $events = ThreeCal_Event::get_all($args);
        $data = array();

        foreach ($events as $event) {
            $data[] = $this->format_event_for_api($event);
        }

        return rest_ensure_response($data);
    }

    /**
     * REST callback: Get single event
     */
    public function rest_get_event($request) {
        $id = $request->get_param('id');
        $event = ThreeCal_Event::get($id);

        if (!$event) {
            return new WP_Error('not_found', __('Event not found', '3task-calendar'), array('status' => 404));
        }

        return rest_ensure_response($this->format_event_for_api($event));
    }

    /**
     * Format event for API response
     */
    private function format_event_for_api($event) {
        $location = null;
        if ($event->location_id) {
            $loc = ThreeCal_Location::get($event->location_id);
            if ($loc) {
                $location = array(
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'address' => $loc->address,
                    'city' => $loc->city,
                    'latitude' => $loc->latitude,
                    'longitude' => $loc->longitude
                );
            }
        }

        $categories = ThreeCal_Event::get_categories($event->id);

        return array(
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'start' => $event->start_date,
            'end' => $event->end_date,
            'allDay' => (bool) $event->all_day,
            'location' => $location,
            'categories' => $categories,
            'color' => $event->color,
            'url' => $event->url,
            'featured_image' => $event->featured_image ? wp_get_attachment_url($event->featured_image) : null
        );
    }

    /**
     * AJAX handler: Get events for calendar
     */
    public function ajax_get_events() {
        check_ajax_referer('threecal_nonce', 'nonce');

        // Support both start/end and month/year parameters.
        $start    = isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : '';
        $end      = isset( $_POST['end'] ) ? sanitize_text_field( wp_unslash( $_POST['end'] ) ) : '';
        $month    = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : 0;
        $year     = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : 0;
        $category = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
        $location = isset( $_POST['location'] ) ? absint( $_POST['location'] ) : 0;

        // If month/year provided, calculate start/end.
        if ( $month > 0 && $year > 0 ) {
            $start = gmdate( 'Y-m-d 00:00:00', mktime( 0, 0, 0, $month, 1, $year ) );
            $end   = gmdate( 'Y-m-t 23:59:59', mktime( 0, 0, 0, $month, 1, $year ) );
        }

        $args = array(
            'status' => 'published',
            'start_after' => $start,
            'start_before' => $end
        );

        if ($category > 0) {
            $args['category_id'] = $category;
        }

        if ($location > 0) {
            $args['location_id'] = $location;
        }

        $events = ThreeCal_Event::get_all($args);
        $data = array();

        $time_format = get_option( 'time_format', 'g:i a' );

        foreach ($events as $event) {
            $event_data = $this->format_event_for_api($event);

            // Add day number and formatted time for calendar views.
            $event_data['day'] = (int) gmdate( 'j', strtotime( $event->start_date ) );
            if ( $event->all_day ) {
                $event_data['time'] = __( 'All day', '3task-calendar' );
            } else {
                $event_data['time'] = date_i18n( $time_format, strtotime( $event->start_date ) );
            }

            $data[] = $event_data;
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX handler: Get single event details for modal
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

        // Get location
        $location = null;
        if ($event->location_id) {
            $loc = ThreeCal_Location::get($event->location_id);
            if ($loc) {
                $location = array(
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'address' => $loc->get_full_address()
                );
            }
        }

        // Get categories
        $categories = ThreeCal_Event::get_categories($event->id);
        $cat_data = array();
        foreach ($categories as $cat) {
            $cat_data[] = array(
                'id' => $cat->id,
                'name' => $cat->name,
                'color' => $cat->color
            );
        }

        // Format dates
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        $data = array(
            'id' => $event->id,
            'title' => $event->title,
            'description' => wpautop($event->description),
            'start_date' => date_i18n($date_format, strtotime($event->start_date)),
            'end_date' => $event->end_date ? date_i18n($date_format, strtotime($event->end_date)) : null,
            'start_time' => !$event->all_day ? date_i18n($time_format, strtotime($event->start_date)) : null,
            'end_time' => (!$event->all_day && $event->end_date) ? date_i18n($time_format, strtotime($event->end_date)) : null,
            'all_day' => (bool) $event->all_day,
            'location' => $location,
            'categories' => $cat_data,
            'color' => $event->color,
            'url' => $event->url,
            'featured_image' => $event->featured_image ? wp_get_attachment_url($event->featured_image) : null
        );

        wp_send_json_success($data);
    }

    /**
     * Ensure capabilities are set (fallback if activation failed)
     */
    public function maybe_add_capabilities() {
        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap('manage_threecal')) {
            ThreeCal_Activator::activate();
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialize
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
}

/**
 * Activation hook
 */
function threecal_activate() {
    require_once THREECAL_PLUGIN_DIR . 'includes/class-activator.php';
    ThreeCal_Activator::activate();
}
register_activation_hook(__FILE__, 'threecal_activate');

/**
 * Deactivation hook
 */
function threecal_deactivate() {
    require_once THREECAL_PLUGIN_DIR . 'includes/class-deactivator.php';
    ThreeCal_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'threecal_deactivate');

/**
 * Initialize plugin
 */
function threecal() {
    return ThreeCal::instance();
}

// Start plugin
add_action('plugins_loaded', 'threecal');
