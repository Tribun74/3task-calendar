<?php
/**
 * Plugin Name:       3task Calendar
 * Plugin URI:        https://www.3task.de/3task-calendar-pro/
 * Description:       Event calendar without external services: recurring events, iCal subscription, categories, locations and event schema. Month and list views, German translation included.
 * Version:           1.5.0
 * Author:            3task
 * Author URI:        https://www.3task.de
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       3task-calendar
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Tested up to:      7.1
 *
 * @package ThreeCal
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('THREECAL_VERSION', '1.5.0');
define('THREECAL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('THREECAL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('THREECAL_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('THREECAL_DB_VERSION', '1.0.2');

/**
 * Date format for events: the plugin setting, or the WordPress setting when it is empty.
 *
 * @return string
 */
function threecal_date_format() {
    $settings = (array) get_option('threecal_settings', array());
    return ! empty($settings['date_format']) ? (string) $settings['date_format'] : (string) get_option('date_format', 'F j, Y');
}

/**
 * Time format for events: the plugin setting, or the WordPress setting when it is empty.
 *
 * @return string
 */
function threecal_time_format() {
    $settings = (array) get_option('threecal_settings', array());
    return ! empty($settings['time_format']) ? (string) $settings['time_format'] : (string) get_option('time_format', 'H:i');
}

/**
 * Version string for a plugin asset.
 *
 * Adds the file modification time, so browsers load a changed file right
 * away instead of a cached copy.
 *
 * @param string $relative Path relative to the plugin folder.
 * @return string
 */
function threecal_asset_version($relative) {
    $file = THREECAL_PLUGIN_DIR . ltrim($relative, '/');
    return file_exists($file) ? THREECAL_VERSION . '.' . filemtime($file) : THREECAL_VERSION;
}

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
        require_once THREECAL_PLUGIN_DIR . 'includes/class-event.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-location.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-category.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-shortcode.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-calendar-renderer.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-ics.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-icons.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-themes.php';
        require_once THREECAL_PLUGIN_DIR . 'includes/class-post-dates.php';

        // Admin classes
        if (is_admin()) {
            require_once THREECAL_PLUGIN_DIR . 'admin/class-admin.php';
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
        // Language packs from translate.wordpress.org load automatically.
        // The bundled German files are only a fallback when no language pack exists.
        add_action('init', array($this, 'load_bundled_translations'), 0);
    }

    /**
     * Load the bundled German translation when no language pack is installed.
     */
    public function load_bundled_translations() {
        $locale = determine_locale();
        if (0 !== strpos($locale, 'de_')) {
            return;
        }

        $pack = WP_LANG_DIR . '/plugins/3task-calendar-' . $locale . '.mo';
        if (file_exists($pack)) {
            return;
        }

        $bundled = THREECAL_PLUGIN_DIR . 'languages/3task-calendar-' . $locale . '.mo';
        if (!file_exists($bundled)) {
            // de_AT, de_CH and others fall back to de_DE (formal variants to de_DE_formal).
            $fallback = (false !== strpos($locale, '_formal')) ? 'de_DE_formal' : 'de_DE';
            $bundled = THREECAL_PLUGIN_DIR . 'languages/3task-calendar-' . $fallback . '.mo';
        }

        if (file_exists($bundled)) {
            load_textdomain('3task-calendar', $bundled);
        }
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

        // Database upgrades after plugin updates (activation hook does not run on update).
        add_action('init', array($this, 'maybe_upgrade_database'), 5);

        // Public
        $this->public = new ThreeCal_Public('3task-calendar', THREECAL_VERSION);
        add_action('wp_enqueue_scripts', array($this->public, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this->public, 'enqueue_scripts'));

        // Shortcodes
        new ThreeCal_Shortcode();

        // iCalendar download and subscription feed
        new ThreeCal_ICS();
        new ThreeCal_Post_Dates();

        // Register block
        add_action('init', array($this, 'register_block'));
        add_action('enqueue_block_editor_assets', array($this, 'block_editor_data'));

        // Pages saved with the old block name (before 1.3.0) render with the new one.
        add_filter('the_content', array(__CLASS__, 'migrate_block_name'), 8);

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
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render'),
            threecal_asset_version('blocks/calendar-block/index.js'),
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('threecal-block-editor', '3task-calendar', THREECAL_PLUGIN_DIR . 'languages');
        }

        // The calendar stylesheet also styles the live preview in the editor.
        if (!wp_style_is('threecal-public', 'registered')) {
            wp_register_style('threecal-public', THREECAL_PLUGIN_URL . 'public/css/threecal.css', array(), threecal_asset_version('public/css/threecal.css'));
        }

        wp_register_style(
            'threecal-block-editor',
            THREECAL_PLUGIN_URL . 'blocks/calendar-block/editor.css',
            array(),
            threecal_asset_version('blocks/calendar-block/editor.css')
        );

        register_block_type('threecal/calendar', array(
            'api_version' => 3,
            'editor_script' => 'threecal-block-editor',
            'editor_style' => array('threecal-block-editor', 'threecal-public'),
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
                    'default' => ''
                ),
                'align' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'className' => array(
                    'type' => 'string',
                    'default' => ''
                )
            ),
            'supports' => array(
                'align' => array('wide', 'full'),
                'html' => false
            )
        ));
    }

    /**
     * Rename the old block "3task-calendar/calendar" to "threecal/calendar".
     *
     * Block names must start with a letter, so WordPress never parsed the old
     * name and the block rendered nothing. Runs before do_blocks().
     *
     * @param string $content Post content.
     * @return string
     */
    public static function migrate_block_name($content) {
        if (!is_string($content) || false === strpos($content, 'wp:3task-calendar/calendar')) {
            return $content;
        }
        return str_replace(
            array('<!-- wp:3task-calendar/calendar ', '<!-- /wp:3task-calendar/calendar -->'),
            array('<!-- wp:threecal/calendar ', '<!-- /wp:threecal/calendar -->'),
            $content
        );
    }

    /**
     * Designs and categories for the block settings (editor only).
     */
    public function block_editor_data() {
        // Designs and categories for the block settings.
        $block_themes = array();
        foreach (ThreeCal_Themes::all() as $key => $theme) {
            $block_themes[] = array('value' => $key, 'label' => $theme['label']);
        }
        $block_categories = array();
        foreach (ThreeCal_Category::get_all() as $cat) {
            $block_categories[] = array('value' => (int) $cat->id, 'label' => $cat->name);
        }
        $block_data = 'window.threecalBlockData = ' . wp_json_encode(array('themes' => $block_themes, 'categories' => $block_categories)) . ';';
        wp_add_inline_script('threecal-block-editor', $block_data, 'before');
        wp_add_inline_script('threecal-post-date-block', $block_data, 'before');

    }

    /**
     * Render block callback
     */
    public function render_block($attributes) {
        $view = isset($attributes['view']) ? $attributes['view'] : 'month';
        $category = isset($attributes['category']) ? absint($attributes['category']) : 0;
        $theme = isset($attributes['theme']) ? ThreeCal_Themes::normalize($attributes['theme']) : '';

        // Poster view: upcoming dates as tiles with their images.
        $shortcode = 'poster' === $view ? '[threecal_events view="poster" columns="4" limit="12"' : '[threecal view="' . esc_attr($view) . '"';
        if ($category > 0) {
            $shortcode .= ' category="' . $category . '"';
        }
        $shortcode .= $theme ? ' theme="' . esc_attr($theme) . '"]' : ']';

        $classes = array('wp-block-threecal-calendar');
        if (!empty($attributes['align']) && in_array($attributes['align'], array('wide', 'full'), true)) {
            $classes[] = 'align' . $attributes['align'];
        }
        if (!empty($attributes['className'])) {
            $classes[] = $attributes['className'];
        }

        return '<div class="' . esc_attr(implode(' ', array_map('sanitize_html_class', $classes))) . '">' . do_shortcode($shortcode) . '</div>';
    }

    /**
     * Output Schema.org markup for events
     *
     * Runs on singular pages that contain the calendar shortcode or block and
     * respects the category of the first calendar on the page.
     */
    public function output_schema() {
        if (!is_singular()) {
            return;
        }

        $settings = get_option('threecal_settings', array());
        if (isset($settings['enable_schema']) && !$settings['enable_schema']) {
            return;
        }

        $post = get_post();
        if (!$post) {
            return;
        }

        $category = $this->find_calendar_category($post->post_content);
        if (null === $category) {
            return;
        }

        $events = ThreeCal_Event::get_upcoming(5, $category);
        if (empty($events)) {
            return;
        }

        $page_url = get_permalink($post);
        foreach ($events as $event) {
            $this->output_event_schema($event, $page_url, $post);
        }
    }

    /**
     * Find the category of the first calendar on a page.
     *
     * @param string $content Post content.
     * @return int|null Category ID (0 = all) or null when the page has no calendar.
     */
    private function find_calendar_category($content) {
        if (function_exists('has_block') && (has_block('threecal/calendar', $content) || false !== strpos($content, '<!-- wp:3task-calendar/calendar'))) {
            foreach (parse_blocks(self::migrate_block_name($content)) as $block) {
                $found = $this->find_block_category($block);
                if (null !== $found) {
                    return $found;
                }
            }
            return 0;
        }

        $tags = array('threecal', 'threecal_events', 'threecal_upcoming', 'threecal_mini');
        foreach ($tags as $tag) {
            if (has_shortcode($content, $tag)) {
                $pattern = get_shortcode_regex(array($tag));
                if (preg_match('/' . $pattern . '/s', $content, $match)) {
                    $atts = shortcode_parse_atts($match[3]);
                    return (is_array($atts) && !empty($atts['category'])) ? absint($atts['category']) : 0;
                }
                return 0;
            }
        }

        return null;
    }

    /**
     * Recursively find the calendar block and return its category.
     *
     * @param array $block Parsed block.
     * @return int|null
     */
    private function find_block_category($block) {
        if (isset($block['blockName']) && 'threecal/calendar' === $block['blockName']) {
            return isset($block['attrs']['category']) ? absint($block['attrs']['category']) : 0;
        }
        if (!empty($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as $inner) {
                $found = $this->find_block_category($inner);
                if (null !== $found) {
                    return $found;
                }
            }
        }
        return null;
    }

    /**
     * Format a stored local date as ISO 8601 with the site time zone.
     *
     * @param string $mysql_date Date in Y-m-d H:i:s (site time).
     * @param bool   $all_day    Return only the date part.
     * @return string
     */
    public static function iso_date($mysql_date, $all_day = false) {
        if (empty($mysql_date)) {
            return '';
        }
        try {
            $date = new DateTimeImmutable($mysql_date, wp_timezone());
        } catch (Exception $e) {
            return '';
        }
        return $all_day ? $date->format('Y-m-d') : $date->format(DATE_ATOM);
    }

    /**
     * Output individual event schema
     */
    private function output_event_schema($event, $page_url = '', $page_post = null) {
        // Google requires a real place and does not allow office hours or other
        // non-events as Event markup: no location or an excluded category, no markup.
        $location = $event->location_id ? ThreeCal_Location::get($event->location_id) : null;
        if (!$location) {
            return;
        }
        $no_schema = ThreeCal_Category::no_schema_ids();
        if ($no_schema) {
            foreach (ThreeCal_Event::get_categories($event->id) as $cat) {
                if (in_array((int) $cat->id, $no_schema, true)) {
                    return;
                }
            }
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->title,
            'startDate' => self::iso_date($event->start_date, $event->all_day),
            'endDate' => self::iso_date($event->end_date ?: $event->start_date, $event->all_day),
            'eventStatus' => 'cancelled' === $event->status ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode'
        );

        $description = trim(wp_strip_all_tags($event->description));
        if ('' !== $description) {
            $schema['description'] = $description;
        }

        if ($event->url) {
            $schema['url'] = $event->url;
        } elseif ($page_url) {
            $schema['url'] = $page_url;
        }

        // Image: the event's own, else the image of the page with the calendar, else the site icon or logo.
        $image = $event->featured_image ? wp_get_attachment_url($event->featured_image) : '';
        if (!$image && $page_post && has_post_thumbnail($page_post)) {
            $image = get_the_post_thumbnail_url($page_post, 'full');
        }
        if (!$image && function_exists('get_site_icon_url')) {
            $image = get_site_icon_url(512);
        }
        if (!$image && get_theme_mod('custom_logo')) {
            $image = wp_get_attachment_image_url((int) get_theme_mod('custom_logo'), 'full');
        }
        if ($image) {
            $schema['image'] = $image;
        }

        // Location
        $schema['location'] = array(
            '@type' => 'Place',
            'name' => $location->name,
            'address' => array_filter(array(
                '@type' => 'PostalAddress',
                'streetAddress' => $location->address,
                'addressLocality' => $location->city,
                'postalCode' => $location->postal_code,
                'addressCountry' => $location->country
            ))
        );

        if ($location->has_coordinates()) {
            $schema['location']['geo'] = array(
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude
            );
        }

        // Add organizer
        $schema['organizer'] = array(
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'url' => home_url('/')
        );

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>' . "\n";
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
                    'type' => 'string',
                    'validate_callback' => array($this, 'rest_validate_date')
                ),
                'end' => array(
                    'required' => false,
                    'type' => 'string',
                    'validate_callback' => array($this, 'rest_validate_date')
                ),
                'category' => array(
                    'required' => false,
                    'type' => 'integer',
                    'minimum' => 0,
                    'sanitize_callback' => 'absint'
                ),
                'per_page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 100,
                    'minimum' => 1,
                    'maximum' => 100,
                    'sanitize_callback' => 'absint'
                ),
                'page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                    'sanitize_callback' => 'absint'
                )
            )
        ));

        register_rest_route('3task-calendar/v1', '/events/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_event'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                )
            )
        ));
    }

    /**
     * Validate a date parameter (Y-m-d or Y-m-d H:i:s).
     */
    public function rest_validate_date($value) {
        if ('' === $value || null === $value) {
            return true;
        }
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', (string) $value);
    }

    /**
     * REST callback: Get events (events overlapping start/end)
     */
    public function rest_get_events($request) {
        $start = $request->get_param('start');
        $end = $request->get_param('end');
        $category = (int) $request->get_param('category');
        $per_page = min(100, max(1, (int) $request->get_param('per_page')));
        $page = max(1, (int) $request->get_param('page'));

        $args = array(
            'status' => ThreeCal_Event::visible_statuses(),
            'per_page' => $per_page,
            'page' => $page
        );

        if ($start) {
            $args['range_start'] = strlen($start) === 10 ? $start . ' 00:00:00' : $start;
        }
        if ($end) {
            $args['range_end'] = strlen($end) === 10 ? $end . ' 23:59:59' : $end;
        }
        if ($category) {
            $args['category_id'] = $category;
        }

        $events = ThreeCal_Event::get_all($args);
        $total = ThreeCal_Event::count($args);
        $categories = ThreeCal_Event::get_categories_for_events(wp_list_pluck($events, 'id'));

        $data = array();
        foreach ($events as $event) {
            $data[] = $this->format_event_for_api($event, isset($categories[$event->id]) ? $categories[$event->id] : array());
        }

        $response = rest_ensure_response($data);
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) (int) ceil($total / $per_page));

        return $response;
    }

    /**
     * REST callback: Get single event (published only, unless the user may edit events)
     */
    public function rest_get_event($request) {
        $id = absint($request->get_param('id'));
        $event = ThreeCal_Event::get($id);

        if (!$event || (!$event->is_visible() && !current_user_can('edit_threecal_events'))) {
            return new WP_Error('not_found', __('Event not found', '3task-calendar'), array('status' => 404));
        }

        return rest_ensure_response($this->format_event_for_api($event));
    }

    /**
     * Format event for API response
     *
     * @param ThreeCal_Event $event      Event.
     * @param array|null     $categories Preloaded categories (null = load now).
     */
    private function format_event_for_api($event, $categories = null) {
        $location = null;
        if ($event->location_id) {
            $loc = ThreeCal_Location::get($event->location_id);
            if ($loc) {
                $location = array(
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'address' => $loc->address,
                    'city' => $loc->city,
                    'latitude' => $loc->has_coordinates() ? $loc->latitude : null,
                    'longitude' => $loc->has_coordinates() ? $loc->longitude : null
                );
            }
        }

        if (null === $categories) {
            $categories = ThreeCal_Event::get_categories($event->id);
        }

        $cat_data = array();
        foreach ((array) $categories as $cat) {
            $cat_data[] = array(
                'id' => (int) $cat->id,
                'name' => $cat->name,
                'color' => $cat->color
            );
        }

        return array(
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'start' => $event->start_date,
            'end' => $event->end_date,
            'allDay' => (bool) $event->all_day,
            'cancelled' => 'cancelled' === $event->status,
            'location' => $location,
            'categories' => $cat_data,
            'color' => ThreeCal_Themes::event_color($event, $categories),
            'url' => $event->url,
            'featured_image' => $event->featured_image ? wp_get_attachment_url($event->featured_image) : null
        );
    }

    /**
     * AJAX handler: Get events for calendar
     *
     * Public, read-only and limited to published events. No nonce on purpose:
     * pages with the calendar are often served from a page cache, and a cached
     * nonce expires after 12 to 24 hours, which would break month navigation.
     */
    public function ajax_get_events() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Public read-only endpoint, see docblock.
        $month    = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : 0;
        $year     = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : 0;
        $category = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
        $location = isset( $_POST['location'] ) ? absint( $_POST['location'] ) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( $month < 1 || $month > 12 || $year < 2000 || $year > 2100 ) {
            $month = (int) current_time( 'n' );
            $year  = (int) current_time( 'Y' );
        }

        $range_start = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
        $range_end   = gmdate( 'Y-m-t 23:59:59', strtotime( sprintf( '%04d-%02d-01 00:00:00 UTC', $year, $month ) ) );

        $args = array(
            'status'      => ThreeCal_Event::visible_statuses(),
            'range_start' => $range_start,
            'range_end'   => $range_end,
        );

        if ($category > 0) {
            $args['category_id'] = $category;
        }

        if ($location > 0) {
            $args['location_id'] = $location;
        }

        $events = ThreeCal_Event::get_all($args);
        $categories = ThreeCal_Event::get_categories_for_events(wp_list_pluck($events, 'id'));
        $data = array();

        $settings    = get_option( 'threecal_settings', array() );
        $time_format = threecal_time_format();

        foreach ($events as $event) {
            $event_data = $this->format_event_for_api($event, isset($categories[$event->id]) ? $categories[$event->id] : array());

            $days = $event->get_days_in_month( $month, $year );
            $event_data['days'] = $days;
            $event_data['postponed'] = ThreeCal_Post_Dates::postponed_text($event);
            $event_data['day']  = $days ? $days[0] : (int) gmdate( 'j', strtotime( $event->start_date ) );
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
     *
     * Public and read-only (published events only), no nonce for the same
     * page cache reason as ajax_get_events().
     */
    public function ajax_get_event_details() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public read-only endpoint, see docblock.
        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Invalid event ID', '3task-calendar')));
        }

        $event = ThreeCal_Event::get($event_id);

        if (!$event || !$event->is_visible()) {
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
                    'address' => $loc->get_full_address(),
                    'route_url' => $loc->get_route_url()
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
        $settings    = get_option('threecal_settings', array());
        $date_format = threecal_date_format();
        $time_format = threecal_time_format();

        $same_day = !$event->end_date || substr($event->start_date, 0, 10) === substr($event->end_date, 0, 10);

        $data = array(
            'id' => $event->id,
            'title' => $event->title,
            'description' => wp_kses_post(wpautop($event->description)),
            'start_date' => date_i18n($date_format, strtotime($event->start_date)),
            'end_date' => $same_day ? null : date_i18n($date_format, strtotime($event->end_date)),
            'start_time' => !$event->all_day ? date_i18n($time_format, strtotime($event->start_date)) : null,
            'end_time' => (!$event->all_day && $event->end_date) ? date_i18n($time_format, strtotime($event->end_date)) : null,
            'all_day' => (bool) $event->all_day,
            'cancelled' => 'cancelled' === $event->status,
            'location' => $location,
            'categories' => $cat_data,
            'color' => $event->color,
            'url' => $event->url ? esc_url_raw($event->url) : '',
            'ics_url' => ThreeCal_ICS::event_url($event->id),
            'postponed' => ThreeCal_Post_Dates::postponed_text($event),
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
            ThreeCal_Activator::add_capabilities();
        }
    }

    /**
     * Create or update database tables after a plugin update.
     */
    public function maybe_upgrade_database() {
        if (get_option('threecal_db_version') === THREECAL_DB_VERSION) {
            return;
        }
        ThreeCal_Activator::create_tables();

        // Up to 1.4.0 the activation copied the WordPress date and time format into the plugin
        // settings. An unchanged copy now follows the WordPress setting again.
        $settings = get_option('threecal_settings');
        if (is_array($settings)) {
            $changed = false;
            foreach (array('date_format', 'time_format') as $key) {
                if (isset($settings[$key]) && '' !== $settings[$key] && $settings[$key] === get_option($key)) {
                    $settings[$key] = '';
                    $changed        = true;
                }
            }
            if ($changed) {
                update_option('threecal_settings', $settings);
            }
        }

        update_option('threecal_db_version', THREECAL_DB_VERSION);
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
