<?php
/**
 * ThreeCal Calendar Renderer
 *
 * Handles rendering of calendar views and event displays.
 */

if (!defined('ABSPATH')) {
    exit;
}

class ThreeCal_Calendar_Renderer {

    /**
     * Settings
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('threecal_settings', array());
    }

    /**
     * Render full calendar
     */
    public function render_calendar($args = array()) {
        $defaults = array(
            'view' => 'month',
            'category_id' => 0,
            'location_id' => 0,
            'theme' => 'default',
            'show_filters' => true,
            'show_legend' => true,
            'week_starts_on' => null
        );

        $args = wp_parse_args($args, $defaults);

        // Get week start from settings if not specified
        if ($args['week_starts_on'] === null) {
            $args['week_starts_on'] = isset($this->settings['week_starts_on']) ? $this->settings['week_starts_on'] : 1;
        }

        // Get current month/year.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Calendar navigation params, sanitized with absint().
        $month = isset( $_GET['cc_month'] ) ? absint( $_GET['cc_month'] ) : (int) gmdate( 'n' );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Calendar navigation params, sanitized with absint().
        $year = isset( $_GET['cc_year'] ) ? absint( $_GET['cc_year'] ) : (int) gmdate( 'Y' );

        // Validate month/year
        if ($month < 1 || $month > 12) {
            $month = (int) gmdate('n');
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) gmdate('Y');
        }

        // Get categories for filter/legend
        $categories = ThreeCal_Category::get_all();

        // Generate unique ID for this calendar instance
        $calendar_id = 'threecal-' . wp_rand(1000, 9999);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($calendar_id); ?>"
             class="threecal-wrapper threecal-theme-<?php echo esc_attr($args['theme']); ?>"
             data-view="<?php echo esc_attr($args['view']); ?>"
             data-category="<?php echo esc_attr($args['category_id']); ?>"
             data-location="<?php echo esc_attr($args['location_id']); ?>"
             data-week-starts="<?php echo esc_attr($args['week_starts_on']); ?>">

            <?php if ($args['show_filters'] && !empty($categories)) : ?>
            <div class="threecal-filters">
                <select class="threecal-category-filter" aria-label="<?php esc_attr_e('Filter by category', '3task-calendar'); ?>">
                    <option value="0"><?php esc_html_e('All Categories', '3task-calendar'); ?></option>
                    <?php foreach ($categories as $cat) : ?>
                    <option value="<?php echo esc_attr($cat->id); ?>" <?php selected($args['category_id'], $cat->id); ?>>
                        <?php echo esc_html($cat->name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <div class="threecal-view-switcher">
                    <button type="button" class="threecal-view-btn <?php echo $args['view'] === 'month' ? 'active' : ''; ?>" data-view="month">
                        <?php esc_html_e('Month', '3task-calendar'); ?>
                    </button>
                    <button type="button" class="threecal-view-btn <?php echo $args['view'] === 'list' ? 'active' : ''; ?>" data-view="list">
                        <?php esc_html_e('List', '3task-calendar'); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="threecal-header">
                <button type="button" class="threecal-nav threecal-prev" aria-label="<?php esc_attr_e('Previous month', '3task-calendar'); ?>">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </button>

                <h2 class="threecal-title">
                    <?php echo esc_html($this->get_month_name($month) . ' ' . $year); ?>
                </h2>

                <button type="button" class="threecal-nav threecal-next" aria-label="<?php esc_attr_e('Next month', '3task-calendar'); ?>">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>

                <button type="button" class="threecal-today" aria-label="<?php esc_attr_e('Today', '3task-calendar'); ?>">
                    <?php esc_html_e('Today', '3task-calendar'); ?>
                </button>
            </div>

            <div class="threecal-calendar" data-month="<?php echo esc_attr($month); ?>" data-year="<?php echo esc_attr($year); ?>">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped within render_month_view method
                echo $this->render_month_view( $month, $year, $args );
                ?>
            </div>

            <?php if ($args['show_legend'] && !empty($categories)) : ?>
            <div class="threecal-legend">
                <?php foreach ($categories as $cat) : ?>
                <div class="threecal-legend-item">
                    <span class="threecal-legend-color" style="background-color: <?php echo esc_attr($cat->color); ?>;"></span>
                    <span class="threecal-legend-label"><?php echo esc_html($cat->name); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Event popup modal -->
            <div class="threecal-modal" style="display: none;">
                <div class="threecal-modal-content">
                    <button type="button" class="threecal-modal-close" aria-label="<?php esc_attr_e('Close', '3task-calendar'); ?>">&times;</button>
                    <div class="threecal-modal-body"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render month view grid
     */
    public function render_month_view($month, $year, $args = array()) {
        $week_starts_on = isset($args['week_starts_on']) ? $args['week_starts_on'] : 1;

        // Get first day of month
        $first_day = mktime(0, 0, 0, $month, 1, $year);
        $days_in_month = (int) gmdate('t', $first_day);
        $first_weekday = (int) gmdate('w', $first_day);

        // Adjust for week start
        $first_weekday = ($first_weekday - $week_starts_on + 7) % 7;

        // Get events for this month
        $start_date = gmdate('Y-m-d 00:00:00', $first_day);
        $end_date = gmdate('Y-m-t 23:59:59', $first_day);

        $event_args = array(
            'status' => 'published',
            'start_after' => $start_date,
            'start_before' => $end_date
        );

        if (!empty($args['category_id'])) {
            $event_args['category_id'] = $args['category_id'];
        }

        if (!empty($args['location_id'])) {
            $event_args['location_id'] = $args['location_id'];
        }

        $events = ThreeCal_Event::get_all($event_args);

        // Group events by day
        $events_by_day = array();
        foreach ($events as $event) {
            $day = (int) gmdate('j', strtotime($event->start_date));
            if (!isset($events_by_day[$day])) {
                $events_by_day[$day] = array();
            }
            $events_by_day[$day][] = $event;
        }

        ob_start();
        ?>
        <table class="threecal-month-grid" role="grid">
            <thead>
                <tr>
                    <?php for ($i = 0; $i < 7; $i++) : ?>
                    <th scope="col"><?php echo esc_html($this->get_weekday_name(($i + $week_starts_on) % 7)); ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $day = 1;
                $today = gmdate('Y-m-d');

                // Calculate number of weeks
                $total_cells = $first_weekday + $days_in_month;
                $weeks = ceil($total_cells / 7);

                for ($week = 0; $week < $weeks; $week++) :
                ?>
                <tr>
                    <?php for ($weekday = 0; $weekday < 7; $weekday++) :
                        $cell_index = $week * 7 + $weekday;

                        if ($cell_index < $first_weekday || $day > $days_in_month) :
                            // Empty cell
                            ?>
                            <td class="threecal-day threecal-day-empty"></td>
                            <?php
                        else :
                            $current_date = gmdate('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
                            $is_today = $current_date === $today;
                            $has_events = isset($events_by_day[$day]);
                            $day_events = $has_events ? $events_by_day[$day] : array();
                            ?>
                            <td class="threecal-day<?php echo $is_today ? ' threecal-today' : ''; ?><?php echo $has_events ? ' threecal-has-events' : ''; ?>"
                                data-date="<?php echo esc_attr($current_date); ?>">
                                <div class="threecal-day-header">
                                    <span class="threecal-day-number"><?php echo esc_html($day); ?></span>
                                </div>
                                <?php if ($has_events) : ?>
                                <div class="threecal-day-events">
                                    <?php foreach (array_slice($day_events, 0, 3) as $event) : ?>
                                    <a href="#" class="threecal-event-dot"
                                       data-event-id="<?php echo esc_attr($event->id); ?>"
                                       style="background-color: <?php echo esc_attr($event->color); ?>;"
                                       title="<?php echo esc_attr($event->title); ?>">
                                        <span class="threecal-event-title"><?php echo esc_html($event->title); ?></span>
                                    </a>
                                    <?php endforeach; ?>
                                    <?php if (count($day_events) > 3) : ?>
                                    <a href="#" class="threecal-more-events" data-date="<?php echo esc_attr($current_date); ?>">
                                        +<?php echo count($day_events) - 3; ?> <?php esc_html_e('more', '3task-calendar'); ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <?php
                            $day++;
                        endif;
                    endfor; ?>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render event list
     */
    public function render_event_list($events, $args = array()) {
        $defaults = array(
            'view' => 'list',
            'theme' => 'default',
            'show_pagination' => true,
            'columns' => 3,
            'total' => 0,
            'per_page' => 10,
            'current_page' => 1
        );

        $args = wp_parse_args($args, $defaults);

        if (empty($events)) {
            return '<p class="threecal-no-events">' . esc_html__('No events found.', '3task-calendar') . '</p>';
        }

        ob_start();
        ?>
        <div class="threecal-event-list threecal-view-<?php echo esc_attr($args['view']); ?> threecal-theme-<?php echo esc_attr($args['theme']); ?>">
            <?php if ($args['view'] === 'grid') : ?>
            <div class="threecal-grid threecal-grid-<?php echo esc_attr($args['columns']); ?>">
            <?php endif; ?>

            <?php foreach ( $events as $event ) : ?>
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped within render_event_card method
                echo $this->render_event_card( $event, $args['view'] );
                ?>
            <?php endforeach; ?>

            <?php if ($args['view'] === 'grid') : ?>
            </div>
            <?php endif; ?>

            <?php if ( $args['show_pagination'] && $args['total'] > $args['per_page'] ) : ?>
            <div class="threecal-pagination">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped within render_pagination method
                echo $this->render_pagination( $args['total'], $args['per_page'], $args['current_page'] );
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render single event card
     */
    public function render_event_card($event, $view = 'list') {
        $location = null;
        if ($event->location_id) {
            $location = ThreeCal_Location::get($event->location_id);
        }

        $date_format = isset($this->settings['date_format']) ? $this->settings['date_format'] : get_option('date_format');
        $time_format = isset($this->settings['time_format']) ? $this->settings['time_format'] : get_option('time_format');

        ob_start();
        ?>
        <article class="threecal-event-card" data-event-id="<?php echo esc_attr($event->id); ?>">
            <?php if ($event->featured_image) : ?>
            <div class="threecal-event-image">
                <?php echo wp_get_attachment_image($event->featured_image, 'medium'); ?>
            </div>
            <?php endif; ?>

            <div class="threecal-event-content">
                <h3 class="threecal-event-title">
                    <?php if ($event->url) : ?>
                    <a href="<?php echo esc_url($event->url); ?>"><?php echo esc_html($event->title); ?></a>
                    <?php else : ?>
                    <?php echo esc_html($event->title); ?>
                    <?php endif; ?>
                </h3>

                <div class="threecal-event-meta">
                    <div class="threecal-event-date">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php
                        echo esc_html(date_i18n($date_format, strtotime($event->start_date)));

                        if (!$event->all_day) {
                            echo ' ' . esc_html(date_i18n($time_format, strtotime($event->start_date)));
                        }

                        if ($event->end_date && $event->end_date !== $event->start_date) {
                            echo ' - ';
                            if (gmdate('Y-m-d', strtotime($event->start_date)) !== gmdate('Y-m-d', strtotime($event->end_date))) {
                                echo esc_html(date_i18n($date_format, strtotime($event->end_date)));
                            }
                            if (!$event->all_day) {
                                echo ' ' . esc_html(date_i18n($time_format, strtotime($event->end_date)));
                            }
                        }
                        ?>
                    </div>

                    <?php if ($location) : ?>
                    <div class="threecal-event-location">
                        <span class="dashicons dashicons-location"></span>
                        <?php echo esc_html($location->name); ?>
                        <?php if ($location->city) : ?>
                        <span class="threecal-event-city">(<?php echo esc_html($location->city); ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ( $view !== 'compact' && ! empty( $event->description ) ) : ?>
                <div class="threecal-event-excerpt">
                    <?php echo esc_html( wp_trim_words( wp_strip_all_tags( $event->description ), 20 ) ); ?>
                </div>
                <?php endif; ?>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }

    /**
     * Render single event detail
     */
    public function render_single_event($event, $args = array()) {
        $defaults = array(
            'theme' => 'default',
            'show_map' => true,
            'show_description' => true
        );

        $args = wp_parse_args($args, $defaults);

        $location = null;
        if ($event->location_id) {
            $location = ThreeCal_Location::get($event->location_id);
        }

        $categories = ThreeCal_Event::get_categories($event->id);

        $date_format = isset($this->settings['date_format']) ? $this->settings['date_format'] : get_option('date_format');
        $time_format = isset($this->settings['time_format']) ? $this->settings['time_format'] : get_option('time_format');

        ob_start();
        ?>
        <div class="threecal-single-event threecal-theme-<?php echo esc_attr($args['theme']); ?>">
            <?php if ($event->featured_image) : ?>
            <div class="threecal-event-featured-image">
                <?php echo wp_get_attachment_image($event->featured_image, 'large'); ?>
            </div>
            <?php endif; ?>

            <header class="threecal-event-header">
                <h2 class="threecal-event-title"><?php echo esc_html($event->title); ?></h2>

                <?php if (!empty($categories)) : ?>
                <div class="threecal-event-categories">
                    <?php foreach ($categories as $cat) : ?>
                    <span class="threecal-category-tag" style="background-color: <?php echo esc_attr($cat->color); ?>;">
                        <?php echo esc_html($cat->name); ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </header>

            <div class="threecal-event-details">
                <div class="threecal-detail-row">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <div>
                        <strong><?php esc_html_e('Date', '3task-calendar'); ?></strong><br>
                        <?php
                        echo esc_html(date_i18n($date_format, strtotime($event->start_date)));

                        if ($event->end_date && gmdate('Y-m-d', strtotime($event->start_date)) !== gmdate('Y-m-d', strtotime($event->end_date))) {
                            echo ' - ' . esc_html(date_i18n($date_format, strtotime($event->end_date)));
                        }
                        ?>
                    </div>
                </div>

                <?php if (!$event->all_day) : ?>
                <div class="threecal-detail-row">
                    <span class="dashicons dashicons-clock"></span>
                    <div>
                        <strong><?php esc_html_e('Time', '3task-calendar'); ?></strong><br>
                        <?php
                        echo esc_html(date_i18n($time_format, strtotime($event->start_date)));

                        if ($event->end_date) {
                            echo ' - ' . esc_html(date_i18n($time_format, strtotime($event->end_date)));
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($location) : ?>
                <div class="threecal-detail-row">
                    <span class="dashicons dashicons-location"></span>
                    <div>
                        <strong><?php echo esc_html($location->name); ?></strong><br>
                        <?php echo esc_html($location->get_full_address()); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($event->url) : ?>
                <div class="threecal-detail-row">
                    <span class="dashicons dashicons-admin-links"></span>
                    <div>
                        <a href="<?php echo esc_url($event->url); ?>" target="_blank" rel="noopener">
                            <?php esc_html_e('More Information', '3task-calendar'); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($args['show_description'] && !empty($event->description)) : ?>
            <div class="threecal-event-description">
                <?php echo wp_kses_post(wpautop($event->description)); ?>
            </div>
            <?php endif; ?>

            <?php if ($args['show_map'] && $location && $location->latitude && $location->longitude) : ?>
            <div class="threecal-event-map"
                 data-lat="<?php echo esc_attr($location->latitude); ?>"
                 data-lng="<?php echo esc_attr($location->longitude); ?>"
                 data-title="<?php echo esc_attr($location->name); ?>">
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render upcoming events widget
     */
    public function render_upcoming($events, $args = array()) {
        $defaults = array(
            'theme' => 'default',
            'show_date' => true,
            'show_time' => true,
            'show_location' => true
        );

        $args = wp_parse_args($args, $defaults);

        $date_format = isset($this->settings['date_format']) ? $this->settings['date_format'] : get_option('date_format');
        $time_format = isset($this->settings['time_format']) ? $this->settings['time_format'] : get_option('time_format');

        if (empty($events)) {
            return '<p class="threecal-no-events">' . esc_html__('No upcoming events.', '3task-calendar') . '</p>';
        }

        ob_start();
        ?>
        <ul class="threecal-upcoming threecal-theme-<?php echo esc_attr($args['theme']); ?>">
            <?php foreach ($events as $event) :
                $location = null;
                if ($args['show_location'] && $event->location_id) {
                    $location = ThreeCal_Location::get($event->location_id);
                }
            ?>
            <li class="threecal-upcoming-item">
                <div class="threecal-upcoming-color" style="background-color: <?php echo esc_attr($event->color); ?>;"></div>
                <div class="threecal-upcoming-content">
                    <span class="threecal-upcoming-title"><?php echo esc_html($event->title); ?></span>

                    <?php if ($args['show_date']) : ?>
                    <span class="threecal-upcoming-date">
                        <?php echo esc_html(date_i18n($date_format, strtotime($event->start_date))); ?>
                        <?php if ($args['show_time'] && !$event->all_day) : ?>
                        <span class="threecal-upcoming-time">
                            <?php echo esc_html(date_i18n($time_format, strtotime($event->start_date))); ?>
                        </span>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>

                    <?php if ($location) : ?>
                    <span class="threecal-upcoming-location"><?php echo esc_html($location->name); ?></span>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return ob_get_clean();
    }

    /**
     * Render mini calendar (compact for sidebar widgets)
     */
    public function render_mini_calendar($args = array()) {
        $defaults = array(
            'category_id' => 0,
            'theme' => 'default',
            'show_nav' => true,
            'show_today' => true,
            'week_starts_on' => null
        );

        $args = wp_parse_args($args, $defaults);

        // Get week start from settings if not specified
        if ($args['week_starts_on'] === null) {
            $args['week_starts_on'] = isset($this->settings['week_starts_on']) ? $this->settings['week_starts_on'] : 1;
        }

        // Get current month/year from URL or use current date.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Calendar navigation params, sanitized with absint().
        $month = isset($_GET['cc_mini_month']) ? absint($_GET['cc_mini_month']) : (int) gmdate('n');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Calendar navigation params, sanitized with absint().
        $year = isset($_GET['cc_mini_year']) ? absint($_GET['cc_mini_year']) : (int) gmdate('Y');

        // Validate month/year
        if ($month < 1 || $month > 12) {
            $month = (int) gmdate('n');
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) gmdate('Y');
        }

        $week_starts_on = $args['week_starts_on'];
        $date_format = isset($this->settings['date_format']) ? $this->settings['date_format'] : get_option('date_format');
        $time_format = isset($this->settings['time_format']) ? $this->settings['time_format'] : get_option('time_format');

        // Get first day of month
        $first_day = mktime(0, 0, 0, $month, 1, $year);
        $days_in_month = (int) gmdate('t', $first_day);
        $first_weekday = (int) gmdate('w', $first_day);

        // Adjust for week start
        $first_weekday = ($first_weekday - $week_starts_on + 7) % 7;

        // Get events for this month
        $start_date = gmdate('Y-m-d 00:00:00', $first_day);
        $end_date = gmdate('Y-m-t 23:59:59', $first_day);

        $event_args = array(
            'status' => 'published',
            'start_after' => $start_date,
            'start_before' => $end_date
        );

        if (!empty($args['category_id'])) {
            $event_args['category_id'] = $args['category_id'];
        }

        $events = ThreeCal_Event::get_all($event_args);

        // Group events by day with full event data for popup
        $events_by_day = array();
        $events_data_by_day = array();
        foreach ($events as $event) {
            $day = (int) gmdate('j', strtotime($event->start_date));
            if (!isset($events_by_day[$day])) {
                $events_by_day[$day] = array();
                $events_data_by_day[$day] = array();
            }
            $events_by_day[$day][] = $event;

            // Prepare event data for JSON
            $event_time = $event->all_day ? __('All day', '3task-calendar') : date_i18n($time_format, strtotime($event->start_date));
            $events_data_by_day[$day][] = array(
                'id' => $event->id,
                'title' => $event->title,
                'time' => $event_time,
                'color' => $event->color,
                'url' => $event->url ? $event->url : ''
            );
        }

        // Calculate navigation URLs
        $prev_month = $month - 1;
        $prev_year = $year;
        if ($prev_month < 1) {
            $prev_month = 12;
            $prev_year--;
        }

        $next_month = $month + 1;
        $next_year = $year;
        if ($next_month > 12) {
            $next_month = 1;
            $next_year++;
        }

        $current_url = remove_query_arg(array('cc_mini_month', 'cc_mini_year'));
        $prev_url = add_query_arg(array('cc_mini_month' => $prev_month, 'cc_mini_year' => $prev_year), $current_url);
        $next_url = add_query_arg(array('cc_mini_month' => $next_month, 'cc_mini_year' => $next_year), $current_url);
        $today_url = remove_query_arg(array('cc_mini_month', 'cc_mini_year'), $current_url);

        // Generate unique ID
        $calendar_id = 'threecal-mini-' . wp_rand(1000, 9999);

        ob_start();
        ?>
        <?php
        // Enqueue mini calendar CSS and JS properly.
        wp_enqueue_style( 'threecal-mini' );
        wp_enqueue_script( 'threecal-mini' );
        ?>
        <div id="<?php echo esc_attr($calendar_id); ?>" class="threecal-mini-wrapper threecal-theme-<?php echo esc_attr($args['theme']); ?>">
            <?php if ($args['show_nav']) : ?>
            <div class="threecal-mini-header">
                <a href="<?php echo esc_url($prev_url); ?>" class="threecal-mini-nav threecal-mini-prev" aria-label="<?php esc_attr_e('Previous month', '3task-calendar'); ?>">&lsaquo;</a>
                <span class="threecal-mini-title"><?php echo esc_html($this->get_month_name($month) . ' ' . $year); ?></span>
                <a href="<?php echo esc_url($next_url); ?>" class="threecal-mini-nav threecal-mini-next" aria-label="<?php esc_attr_e('Next month', '3task-calendar'); ?>">&rsaquo;</a>
            </div>
            <?php else : ?>
            <div class="threecal-mini-header">
                <span class="threecal-mini-title"><?php echo esc_html($this->get_month_name($month) . ' ' . $year); ?></span>
            </div>
            <?php endif; ?>

            <table class="threecal-mini-grid" role="grid">
                <thead>
                    <tr>
                        <?php for ($i = 0; $i < 7; $i++) : ?>
                        <th scope="col"><?php echo esc_html($this->get_weekday_initial(($i + $week_starts_on) % 7)); ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $day = 1;
                    $today = gmdate('Y-m-d');
                    $total_cells = $first_weekday + $days_in_month;
                    $weeks = ceil($total_cells / 7);

                    for ($week = 0; $week < $weeks; $week++) :
                    ?>
                    <tr>
                        <?php for ($weekday = 0; $weekday < 7; $weekday++) :
                            $cell_index = $week * 7 + $weekday;

                            if ($cell_index < $first_weekday || $day > $days_in_month) : ?>
                                <td class="threecal-mini-day threecal-mini-empty"></td>
                            <?php else :
                                $current_date = gmdate('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
                                $formatted_date = date_i18n($date_format, mktime(0, 0, 0, $month, $day, $year));
                                $is_today = $current_date === $today;
                                $has_events = isset($events_by_day[$day]);
                                $day_events = $has_events ? $events_by_day[$day] : array();
                                $day_events_data = $has_events ? $events_data_by_day[$day] : array();
                                ?>
                                <td class="threecal-mini-day<?php echo $is_today ? ' threecal-mini-today' : ''; ?><?php echo $has_events ? ' threecal-mini-has-events' : ''; ?>"
                                    data-date="<?php echo esc_attr($current_date); ?>"
                                    <?php if ($has_events) : ?>
                                    data-date-formatted="<?php echo esc_attr($formatted_date); ?>"
                                    data-events="<?php echo esc_attr(wp_json_encode($day_events_data)); ?>"
                                    <?php endif; ?>>
                                    <span class="threecal-mini-number"><?php echo esc_html($day); ?></span>
                                    <?php if ($has_events) : ?>
                                    <span class="threecal-mini-dot" style="background-color: <?php echo esc_attr($day_events[0]->color); ?>;"></span>
                                    <?php endif; ?>
                                </td>
                                <?php
                                $day++;
                            endif;
                        endfor; ?>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>

            <?php if ($args['show_today']) : ?>
            <div class="threecal-mini-footer">
                <a href="<?php echo esc_url($today_url); ?>" class="threecal-mini-today-link"><?php esc_html_e('Today', '3task-calendar'); ?></a>
            </div>
            <?php endif; ?>

            <!-- Event Popup -->
            <div class="threecal-mini-popup" style="display: none;">
                <div class="threecal-mini-popup-header">
                    <span class="threecal-mini-popup-date"></span>
                    <button type="button" class="threecal-mini-popup-close" aria-label="<?php esc_attr_e('Close', '3task-calendar'); ?>">&times;</button>
                </div>
                <div class="threecal-mini-popup-events"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render pagination
     */
    private function render_pagination($total, $per_page, $current_page) {
        $total_pages = ceil($total / $per_page);

        if ($total_pages <= 1) {
            return '';
        }

        $output = '<nav class="threecal-nav-pagination" aria-label="' . esc_attr__('Event navigation', '3task-calendar') . '">';

        // Previous
        if ($current_page > 1) {
            $output .= '<a href="' . esc_url(add_query_arg('cc_page', $current_page - 1)) . '" class="threecal-page-prev">';
            $output .= '&laquo; ' . esc_html__('Previous', '3task-calendar');
            $output .= '</a>';
        }

        // Page numbers
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i === $current_page) {
                $output .= '<span class="threecal-page-current">' . $i . '</span>';
            } else {
                $output .= '<a href="' . esc_url(add_query_arg('cc_page', $i)) . '">' . $i . '</a>';
            }
        }

        // Next
        if ($current_page < $total_pages) {
            $output .= '<a href="' . esc_url(add_query_arg('cc_page', $current_page + 1)) . '" class="threecal-page-next">';
            $output .= esc_html__('Next', '3task-calendar') . ' &raquo;';
            $output .= '</a>';
        }

        $output .= '</nav>';

        return $output;
    }

    /**
     * Get month name
     */
    private function get_month_name($month) {
        $months = array(
            1 => __('January', '3task-calendar'),
            2 => __('February', '3task-calendar'),
            3 => __('March', '3task-calendar'),
            4 => __('April', '3task-calendar'),
            5 => __('May', '3task-calendar'),
            6 => __('June', '3task-calendar'),
            7 => __('July', '3task-calendar'),
            8 => __('August', '3task-calendar'),
            9 => __('September', '3task-calendar'),
            10 => __('October', '3task-calendar'),
            11 => __('November', '3task-calendar'),
            12 => __('December', '3task-calendar')
        );

        return isset($months[$month]) ? $months[$month] : '';
    }

    /**
     * Get weekday name (short)
     */
    private function get_weekday_name($day) {
        $days = array(
            0 => __('Sun', '3task-calendar'),
            1 => __('Mon', '3task-calendar'),
            2 => __('Tue', '3task-calendar'),
            3 => __('Wed', '3task-calendar'),
            4 => __('Thu', '3task-calendar'),
            5 => __('Fri', '3task-calendar'),
            6 => __('Sat', '3task-calendar')
        );

        return isset($days[$day]) ? $days[$day] : '';
    }

    /**
     * Get weekday initial (single letter for mini calendar)
     */
    private function get_weekday_initial($day) {
        $days = array(
            /* translators: Single letter abbreviation for Sunday */
            0 => _x('S', 'Sunday initial', '3task-calendar'),
            /* translators: Single letter abbreviation for Monday */
            1 => _x('M', 'Monday initial', '3task-calendar'),
            /* translators: Single letter abbreviation for Tuesday */
            2 => _x('T', 'Tuesday initial', '3task-calendar'),
            /* translators: Single letter abbreviation for Wednesday */
            3 => _x('W', 'Wednesday initial', '3task-calendar'),
            /* translators: Single letter abbreviation for Thursday */
            4 => _x('T', 'Thursday initial', '3task-calendar'),
            /* translators: Single letter abbreviation for Friday */
            5 => _x('F', 'Friday initial', '3task-calendar'),
            /* translators: Single letter abbreviation for Saturday */
            6 => _x('S', 'Saturday initial', '3task-calendar')
        );

        return isset($days[$day]) ? $days[$day] : '';
    }
}
