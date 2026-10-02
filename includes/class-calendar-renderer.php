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
     * Badge in front of the title of a cancelled event.
     *
     * @param ThreeCal_Event $event Event.
     * @return string Escaped HTML, empty for events that take place.
     */
    public static function cancelled_badge($event) {
        if (!$event || 'cancelled' !== $event->status) {
            return '';
        }
        return '<span class="threecal-cancelled-badge">' . esc_html__('Cancelled', '3task-calendar') . '</span> ';
    }

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
     * Date format from the plugin settings or WordPress.
     */
    private function date_format() {
        return !empty($this->settings['date_format']) ? $this->settings['date_format'] : get_option('date_format');
    }

    /**
     * Time format from the plugin settings or WordPress.
     */
    private function time_format() {
        return !empty($this->settings['time_format']) ? $this->settings['time_format'] : get_option('time_format');
    }

    /**
     * Render full calendar
     */
    public function render_calendar($args = array()) {
        $defaults = array(
            'view' => 'month',
            'category_id' => 0,
            'location_id' => 0,
            'theme' => '',
            'show_filters' => true,
            'show_legend' => true,
            'show_subscribe' => true,
            'mobile_list' => true,
            'week_starts_on' => null
        );

        $args = wp_parse_args($args, $defaults);
        ThreeCal_Themes::remember_page_theme($args['theme']);
        $args['theme'] = ThreeCal_Themes::normalize($args['theme']);
        $args['view'] = in_array($args['view'], array('month', 'list'), true) ? $args['view'] : 'month';
        ThreeCal_Themes::enqueue($args['theme']);

        // Get week start from settings if not specified
        if ($args['week_starts_on'] === null) {
            $args['week_starts_on'] = isset($this->settings['week_starts_on']) ? (int) $this->settings['week_starts_on'] : 1;
        }

        // Get current month/year.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Calendar navigation params, sanitized with absint().
        $month = isset( $_GET['cc_month'] ) ? absint( $_GET['cc_month'] ) : (int) current_time( 'n' );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Calendar navigation params, sanitized with absint().
        $year = isset( $_GET['cc_year'] ) ? absint( $_GET['cc_year'] ) : (int) current_time( 'Y' );

        // Validate month/year
        if ($month < 1 || $month > 12) {
            $month = (int) current_time('n');
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) current_time('Y');
        }

        // Get categories for filter/legend
        $categories = ThreeCal_Category::get_all();

        // Generate unique ID for this calendar instance
        $calendar_id = 'threecal-' . wp_rand(1000, 9999);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($calendar_id); ?>"
             class="threecal-wrapper threecal-theme-<?php echo esc_attr($args['theme']); ?>"
             style="<?php echo esc_attr(ThreeCal_Themes::wrapper_style($args['theme'])); ?>"
             data-view="<?php echo esc_attr($args['view']); ?>"
             data-category="<?php echo esc_attr($args['category_id']); ?>"
             data-location="<?php echo esc_attr($args['location_id']); ?>"
             data-week-starts="<?php echo esc_attr($args['week_starts_on']); ?>"
             data-mobile-list="<?php echo $args['mobile_list'] ? '1' : '0'; ?>">

            <div class="threecal-toolbar">
                <div class="threecal-title" role="heading" aria-level="2" aria-live="polite">
                    <span class="threecal-title-month"><?php echo esc_html($this->get_month_name($month)); ?></span>
                    <span class="threecal-title-year"><?php echo esc_html($year); ?></span>
                </div>

                <div class="threecal-controls">
                    <div class="threecal-navgroup" role="group" aria-label="<?php esc_attr_e('Change month', '3task-calendar'); ?>">
                        <button type="button" class="threecal-nav threecal-prev" aria-label="<?php esc_attr_e('Previous month', '3task-calendar'); ?>">
                            <?php ThreeCal_Icons::render('chevron-left', 18); ?>
                        </button>
                        <button type="button" class="threecal-today-btn"><?php esc_html_e('Today', '3task-calendar'); ?></button>
                        <button type="button" class="threecal-nav threecal-next" aria-label="<?php esc_attr_e('Next month', '3task-calendar'); ?>">
                            <?php ThreeCal_Icons::render('chevron-right', 18); ?>
                        </button>
                    </div>

                    <div class="threecal-view-switcher" role="group" aria-label="<?php esc_attr_e('View', '3task-calendar'); ?>">
                        <button type="button" class="threecal-view-btn <?php echo $args['view'] === 'month' ? 'active' : ''; ?>" data-view="month" aria-pressed="<?php echo $args['view'] === 'month' ? 'true' : 'false'; ?>">
                            <?php ThreeCal_Icons::render('calendar-month', 16); ?>
                            <span><?php esc_html_e('Month', '3task-calendar'); ?></span>
                        </button>
                        <button type="button" class="threecal-view-btn <?php echo $args['view'] === 'list' ? 'active' : ''; ?>" data-view="list" aria-pressed="<?php echo $args['view'] === 'list' ? 'true' : 'false'; ?>">
                            <?php ThreeCal_Icons::render('list-details', 16); ?>
                            <span><?php esc_html_e('List', '3task-calendar'); ?></span>
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($args['show_filters'] && count($categories) > 1) : ?>
            <div class="threecal-filters">
                <label class="screen-reader-text" for="<?php echo esc_attr($calendar_id); ?>-filter"><?php esc_html_e('Filter by category', '3task-calendar'); ?></label>
                <select id="<?php echo esc_attr($calendar_id); ?>-filter" class="threecal-category-filter">
                    <option value="0"><?php esc_html_e('All Categories', '3task-calendar'); ?></option>
                    <?php foreach ($categories as $cat) : ?>
                    <option value="<?php echo esc_attr($cat->id); ?>" <?php selected((int) $args['category_id'], (int) $cat->id); ?>>
                        <?php echo esc_html($cat->name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="threecal-calendar" data-month="<?php echo esc_attr($month); ?>" data-year="<?php echo esc_attr($year); ?>">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped within render_month_view method
                echo $this->render_month_view( $month, $year, $args );
                ?>
            </div>

            <?php if (($args['show_legend'] && !empty($categories)) || $args['show_subscribe']) : ?>
            <div class="threecal-footer">
                <?php if ($args['show_legend'] && !empty($categories)) : ?>
                <ul class="threecal-legend">
                    <?php foreach ($categories as $cat) : ?>
                    <li class="threecal-legend-item" style="<?php echo esc_attr(ThreeCal_Themes::event_style(sanitize_hex_color((string) $cat->color))); ?>">
                        <span class="threecal-legend-color" aria-hidden="true"></span>
                        <span class="threecal-legend-label"><?php echo esc_html($cat->name); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <?php if ($args['show_subscribe']) : ?>
                <details class="threecal-subscribe">
                    <summary class="threecal-subscribe-link">
                        <?php ThreeCal_Icons::render('rss', 16); ?>
                        <?php esc_html_e('Subscribe to this calendar', '3task-calendar'); ?>
                    </summary>
                    <div class="threecal-subscribe-panel">
                        <a class="threecal-subscribe-open" href="<?php echo esc_url(ThreeCal_ICS::feed_url($args['category_id'], true), array('webcal', 'http', 'https')); ?>">
                            <?php ThreeCal_Icons::render('calendar-plus', 16); ?>
                            <?php esc_html_e('Open in calendar app', '3task-calendar'); ?>
                        </a>
                        <span class="threecal-subscribe-hint"><?php esc_html_e('Or copy this address into Google Calendar, Outlook or Thunderbird:', '3task-calendar'); ?></span>
                        <code class="threecal-subscribe-url"><?php echo esc_html(ThreeCal_ICS::feed_url($args['category_id'])); ?></code>
                    </div>
                </details>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Event popup modal -->
            <div class="threecal-modal" style="display: none;">
                <div class="threecal-modal-content" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($calendar_id); ?>-dialog-title">
                    <button type="button" class="threecal-modal-close" aria-label="<?php esc_attr_e('Close', '3task-calendar'); ?>"><?php ThreeCal_Icons::render('x', 20); ?></button>
                    <div class="threecal-modal-body" aria-live="polite"></div>
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
        $week_starts_on = isset($args['week_starts_on']) ? (int) $args['week_starts_on'] : 1;

        // Get first day of month
        $first_day = mktime(0, 0, 0, $month, 1, $year);
        $days_in_month = (int) gmdate('t', $first_day);
        $first_weekday = (int) gmdate('w', $first_day);

        // Adjust for week start
        $first_weekday = ($first_weekday - $week_starts_on + 7) % 7;

        // Get events for this month
        $event_args = array(
            'status' => ThreeCal_Event::visible_statuses(),
            'range_start' => gmdate('Y-m-d 00:00:00', $first_day),
            'range_end' => gmdate('Y-m-t 23:59:59', $first_day)
        );

        if (!empty($args['category_id'])) {
            $event_args['category_id'] = $args['category_id'];
        }

        if (!empty($args['location_id'])) {
            $event_args['location_id'] = $args['location_id'];
        }

        $events = ThreeCal_Event::get_all($event_args);
        $categories = ThreeCal_Event::get_categories_for_events(wp_list_pluck($events, 'id'));
        $time_format = $this->time_format();

        // Group events by every day they cover (multi-day events appear on each day)
        $events_by_day = array();
        foreach ($events as $event) {
            foreach ($event->get_days_in_month($month, $year) as $day) {
                $events_by_day[$day][] = array(
                    'event' => $event,
                    'color' => ThreeCal_Themes::event_color($event, isset($categories[$event->id]) ? $categories[$event->id] : array()),
                    'starts_here' => substr($event->start_date, 0, 10) === sprintf('%04d-%02d-%02d', $year, $month, $day),
                );
            }
        }

        $today = current_time('Y-m-d');
        $total_cells = $first_weekday + $days_in_month;
        $weeks = (int) ceil($total_cells / 7);
        $day = 1;

        ob_start();
        ?>
        <table class="threecal-month-grid">
            <thead>
                <tr>
                    <?php for ($i = 0; $i < 7; $i++) : ?>
                    <th scope="col"><?php echo esc_html($this->get_weekday_name(($i + $week_starts_on) % 7)); ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($week = 0; $week < $weeks; $week++) : ?>
                <tr>
                    <?php for ($weekday = 0; $weekday < 7; $weekday++) :
                        $cell_index = $week * 7 + $weekday;
                        $real_weekday = ($weekday + $week_starts_on) % 7;
                        $weekend = (0 === $real_weekday || 6 === $real_weekday) ? ' threecal-weekend' : '';

                        if ($cell_index < $first_weekday || $day > $days_in_month) : ?>
                            <td class="threecal-day threecal-day-empty<?php echo esc_attr($weekend); ?>"></td>
                        <?php else :
                            $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $is_today = $current_date === $today;
                            $day_events = isset($events_by_day[$day]) ? $events_by_day[$day] : array();
                            $has_events = !empty($day_events);
                            ?>
                            <td class="threecal-day<?php echo esc_attr($weekend); ?><?php echo $is_today ? ' threecal-is-today' : ''; ?><?php echo $has_events ? ' threecal-has-events' : ''; ?>"
                                data-date="<?php echo esc_attr($current_date); ?>">
                                <div class="threecal-day-header">
                                    <span class="threecal-day-number"<?php echo $is_today ? ' aria-current="date"' : ''; ?>><?php echo esc_html($day); ?></span>
                                </div>
                                <?php if ($has_events) : ?>
                                <div class="threecal-day-events">
                                    <?php foreach ($day_events as $index => $item) :
                                        $event = $item['event'];
                                        $show_time = !$event->all_day && $item['starts_here'];
                                        ?>
                                    <a href="#" role="button" aria-haspopup="dialog"
                                       class="threecal-event-dot<?php echo $index >= 3 ? ' threecal-event-extra' : ''; ?><?php echo $item['starts_here'] ? '' : ' threecal-event-continues'; ?><?php echo 'cancelled' === $event->status ? ' threecal-is-cancelled' : ''; ?>"
                                       data-event-id="<?php echo esc_attr($event->id); ?>"
                                       style="<?php echo esc_attr(ThreeCal_Themes::event_style($item['color'])); ?><?php echo $index >= 3 ? 'display:none;' : ''; ?>"
                                       title="<?php echo esc_attr(('cancelled' === $event->status ? __('Cancelled', '3task-calendar') . ': ' : '') . $event->title); ?>">
                                        <?php if ($show_time) : ?>
                                        <span class="threecal-event-time"><?php echo esc_html(date_i18n($time_format, strtotime($event->start_date))); ?></span>
                                        <?php endif; ?>
                                        <?php echo self::cancelled_badge($event); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in cancelled_badge(). ?>
                                        <span class="threecal-event-title"><?php echo esc_html($event->title); ?></span>
                                    </a>
                                    <?php endforeach; ?>
                                    <?php if (count($day_events) > 3) : ?>
                                    <a href="#" class="threecal-more-events" data-date="<?php echo esc_attr($current_date); ?>" aria-expanded="false">
                                        +<?php echo esc_html(count($day_events) - 3); ?> <?php esc_html_e('more', '3task-calendar'); ?>
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
            'theme' => '',
            'show_pagination' => true,
            'columns' => 3,
            'total' => 0,
            'per_page' => 10,
            'current_page' => 1,
            'page_param' => 'tc_page'
        );

        $args = wp_parse_args($args, $defaults);
        $args['theme'] = ThreeCal_Themes::normalize($args['theme']);
        $args['view'] = in_array($args['view'], array('list', 'grid', 'compact', 'poster'), true) ? $args['view'] : 'list';
        $args['columns'] = min(6, max(1, (int) $args['columns']));
        ThreeCal_Themes::enqueue($args['theme']);

        if (empty($events)) {
            return '<p class="threecal-no-events threecal-theme-' . esc_attr($args['theme']) . '">' . esc_html__('No events found.', '3task-calendar') . '</p>';
        }

        $categories = ThreeCal_Event::get_categories_for_events(wp_list_pluck($events, 'id'));

        ob_start();
        ?>
        <div class="threecal-event-list threecal-view-<?php echo esc_attr($args['view']); ?> threecal-theme-<?php echo esc_attr($args['theme']); ?>"
             style="<?php echo esc_attr(ThreeCal_Themes::wrapper_style($args['theme'])); ?>">
            <div class="threecal-cards<?php echo $args['view'] === 'grid' ? ' threecal-grid threecal-grid-' . esc_attr($args['columns']) : ''; ?><?php echo $args['view'] === 'poster' ? ' threecal-poster-grid' : ''; ?>"<?php echo $args['view'] === 'poster' ? ' style="--tc-cols:' . esc_attr($args['columns']) . ';"' : ''; ?>>
                <?php foreach ( $events as $event ) : ?>
                    <?php
                    $event_cats = isset( $categories[ $event->id ] ) ? $categories[ $event->id ] : array();
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped within the card methods.
                    echo 'poster' === $args['view'] ? $this->render_poster_card( $event, $event_cats ) : $this->render_event_card( $event, $args['view'], $event_cats );
                    ?>
                <?php endforeach; ?>
            </div>

            <?php if ( $args['show_pagination'] && $args['total'] > $args['per_page'] ) : ?>
            <div class="threecal-pagination">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped within render_pagination method
                echo $this->render_pagination( $args['total'], $args['per_page'], $args['current_page'], $args['page_param'] );
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Poster tile: large image (the post image for dates from posts), date and title.
     *
     * @param ThreeCal_Event $event      Event.
     * @param array          $categories Category rows of the event.
     * @return string
     */
    public function render_poster_card($event, $categories = array()) {
        $color = ThreeCal_Themes::event_color($event, $categories);
        $start = strtotime($event->start_date);
        $when = date_i18n('D', $start) . ', ' . date_i18n($this->date_format(), $start);
        if (!$event->all_day) {
            $when .= ' · ' . date_i18n($this->time_format(), $start);
        }
        $postponed = ThreeCal_Post_Dates::postponed_text($event);
        $cancelled = 'cancelled' === $event->status;
        $link = $event->url;

        ob_start();
        ?>
        <article class="threecal-poster-card<?php echo $cancelled ? ' threecal-is-cancelled' : ''; ?>" style="<?php echo esc_attr(ThreeCal_Themes::event_style($color)); ?>">
            <?php if ($link) : ?>
            <a class="threecal-poster-media" href="<?php echo esc_url($link); ?>" tabindex="-1" aria-hidden="true">
            <?php else : ?>
            <div class="threecal-poster-media">
            <?php endif; ?>
                <?php if ($event->featured_image) : ?>
                    <?php echo wp_get_attachment_image($event->featured_image, 'medium_large', false, array('loading' => 'lazy', 'alt' => '')); ?>
                <?php else : ?>
                    <span class="threecal-poster-placeholder"><?php ThreeCal_Icons::render('calendar-event', 44); ?></span>
                <?php endif; ?>
                <span class="threecal-poster-date">
                    <span class="threecal-poster-day"><?php echo esc_html(date_i18n('j', $start)); ?></span>
                    <span class="threecal-poster-month"><?php echo esc_html(date_i18n('M', $start)); ?></span>
                </span>
            <?php echo $link ? '</a>' : '</div>'; ?>

            <div class="threecal-poster-body">
                <h3 class="threecal-poster-title">
                    <?php echo self::cancelled_badge($event); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in cancelled_badge(). ?>
                    <?php if ($link) : ?>
                    <a href="<?php echo esc_url($link); ?>"><?php echo esc_html($event->title); ?></a>
                    <?php else : ?>
                    <?php echo esc_html($event->title); ?>
                    <?php endif; ?>
                </h3>
                <p class="threecal-poster-when"><?php echo esc_html($when); ?></p>
                <?php if ($postponed) : ?>
                <p class="threecal-postponed"><?php ThreeCal_Icons::render('clock-exclamation', 15); ?><?php echo esc_html($postponed); ?></p>
                <?php endif; ?>
                <?php if (!$cancelled) : ?>
                <a class="threecal-event-ics" href="<?php echo esc_url(ThreeCal_ICS::event_url($event->id)); ?>" download>
                    <?php ThreeCal_Icons::render('calendar-plus', 16); ?>
                    <?php esc_html_e('Add to my calendar', '3task-calendar'); ?>
                </a>
                <?php endif; ?>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }

    /**
     * Render single event card
     */
    public function render_event_card($event, $view = 'list', $categories = null) {
        $location = $event->location_id ? ThreeCal_Location::get($event->location_id) : null;
        if (null === $categories) {
            $categories = ThreeCal_Event::get_categories($event->id);
        }
        $color = ThreeCal_Themes::event_color($event, $categories);
        $date_format = $this->date_format();
        $time_format = $this->time_format();
        $start = strtotime($event->start_date);

        ob_start();
        ?>
        <article class="threecal-event-card<?php echo 'cancelled' === $event->status ? ' threecal-is-cancelled' : ''; ?>" data-event-id="<?php echo esc_attr($event->id); ?>" style="<?php echo esc_attr(ThreeCal_Themes::event_style($color)); ?>">
            <div class="threecal-date-badge" aria-hidden="true">
                <span class="threecal-date-badge-month"><?php echo esc_html(date_i18n('M', $start)); ?></span>
                <span class="threecal-date-badge-day"><?php echo esc_html(date_i18n('j', $start)); ?></span>
                <span class="threecal-date-badge-weekday"><?php echo esc_html(date_i18n('D', $start)); ?></span>
            </div>

            <div class="threecal-event-content">
                <?php if ($event->featured_image && 'compact' !== $view) : ?>
                <div class="threecal-event-image">
                    <?php echo wp_get_attachment_image($event->featured_image, 'medium_large', false, array('loading' => 'lazy')); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($categories) && 'compact' !== $view) : ?>
                <div class="threecal-event-categories">
                    <?php foreach ($categories as $cat) : ?>
                    <span class="threecal-category-tag" style="<?php echo esc_attr(ThreeCal_Themes::event_style(sanitize_hex_color((string) $cat->color))); ?>"><?php echo esc_html($cat->name); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <h3 class="threecal-event-title">
                    <?php echo self::cancelled_badge($event); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in cancelled_badge(). ?>
                    <?php if ($event->url) : ?>
                    <a href="<?php echo esc_url($event->url); ?>"><?php echo esc_html($event->title); ?></a>
                    <?php else : ?>
                    <?php echo esc_html($event->title); ?>
                    <?php endif; ?>
                </h3>

                <div class="threecal-event-meta">
                    <span class="threecal-event-date">
                        <?php ThreeCal_Icons::render('clock', 16); ?>
                        <span>
                        <?php
                        echo esc_html(date_i18n($date_format, $start));
                        if (!$event->all_day) {
                            echo ' · ' . esc_html(date_i18n($time_format, $start));
                        }
                        if ($event->end_date && $event->end_date !== $event->start_date) {
                            $end = strtotime($event->end_date);
                            echo ' – ';
                            if (gmdate('Y-m-d', $start) !== gmdate('Y-m-d', $end)) {
                                echo esc_html(date_i18n($date_format, $end));
                                if (!$event->all_day) {
                                    echo ' · ';
                                }
                            }
                            if (!$event->all_day) {
                                echo esc_html(date_i18n($time_format, $end));
                            }
                        }
                        if ($event->all_day) {
                            echo ' · ' . esc_html__('All day', '3task-calendar');
                        }
                        ?>
                        </span>
                    </span>

                    <?php if ($location) : ?>
                    <span class="threecal-event-location">
                        <?php ThreeCal_Icons::render('map-pin', 16); ?>
                        <span><?php echo esc_html($location->name); ?><?php if ($location->city) : ?>, <?php echo esc_html($location->city); ?><?php endif; ?></span>
                    </span>
                    <?php endif; ?>
                </div>

                <?php $postponed = ThreeCal_Post_Dates::postponed_text( $event ); ?>
                <?php if ( $postponed ) : ?>
                <p class="threecal-postponed"><?php ThreeCal_Icons::render( 'clock-exclamation', 15 ); ?><?php echo esc_html( $postponed ); ?></p>
                <?php endif; ?>

                <?php if ( $view !== 'compact' && ! empty( $event->description ) ) : ?>
                <p class="threecal-event-excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $event->description ), 24 ) ); ?></p>
                <?php endif; ?>

                <a class="threecal-event-ics" href="<?php echo esc_url( ThreeCal_ICS::event_url( $event->id ) ); ?>" download>
                    <?php ThreeCal_Icons::render('calendar-plus', 16); ?>
                    <?php esc_html_e( 'Add to my calendar', '3task-calendar' ); ?>
                </a>
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
            'theme' => '',
            'show_map' => true,
            'show_description' => true
        );

        $args = wp_parse_args($args, $defaults);
        $args['theme'] = ThreeCal_Themes::normalize($args['theme']);
        ThreeCal_Themes::enqueue($args['theme']);

        $location = $event->location_id ? ThreeCal_Location::get($event->location_id) : null;
        $categories = ThreeCal_Event::get_categories($event->id);
        $color = ThreeCal_Themes::event_color($event, $categories);
        $date_format = $this->date_format();
        $time_format = $this->time_format();

        ob_start();
        ?>
        <div class="threecal-single-event threecal-theme-<?php echo esc_attr($args['theme']); ?><?php echo 'cancelled' === $event->status ? ' threecal-is-cancelled' : ''; ?>"
             style="<?php echo esc_attr(ThreeCal_Themes::wrapper_style($args['theme']) . ThreeCal_Themes::event_style($color)); ?>">
            <?php if ($event->featured_image) : ?>
            <div class="threecal-event-featured-image">
                <?php echo wp_get_attachment_image($event->featured_image, 'large'); ?>
            </div>
            <?php endif; ?>

            <header class="threecal-event-header">
                <?php if (!empty($categories)) : ?>
                <div class="threecal-event-categories">
                    <?php foreach ($categories as $cat) : ?>
                    <span class="threecal-category-tag" style="<?php echo esc_attr(ThreeCal_Themes::event_style(sanitize_hex_color((string) $cat->color))); ?>"><?php echo esc_html($cat->name); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <h2 class="threecal-event-title"><?php echo self::cancelled_badge($event); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in cancelled_badge(). ?><?php echo esc_html($event->title); ?></h2>
            </header>

            <div class="threecal-event-details">
                <div class="threecal-detail-row">
                    <?php ThreeCal_Icons::render('calendar-event', 20); ?>
                    <div>
                        <strong><?php esc_html_e('Date', '3task-calendar'); ?></strong><br>
                        <?php
                        echo esc_html(date_i18n($date_format, strtotime($event->start_date)));
                        if ($event->end_date && gmdate('Y-m-d', strtotime($event->start_date)) !== gmdate('Y-m-d', strtotime($event->end_date))) {
                            echo ' – ' . esc_html(date_i18n($date_format, strtotime($event->end_date)));
                        }
                        ?>
                    </div>
                </div>

                <?php if (!$event->all_day) : ?>
                <div class="threecal-detail-row">
                    <?php ThreeCal_Icons::render('clock', 20); ?>
                    <div>
                        <strong><?php esc_html_e('Time', '3task-calendar'); ?></strong><br>
                        <?php
                        echo esc_html(date_i18n($time_format, strtotime($event->start_date)));
                        if ($event->end_date) {
                            echo ' – ' . esc_html(date_i18n($time_format, strtotime($event->end_date)));
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($location) : ?>
                <div class="threecal-detail-row">
                    <?php ThreeCal_Icons::render('map-pin', 20); ?>
                    <div>
                        <strong><?php echo esc_html($location->name); ?></strong><br>
                        <?php echo esc_html($location->get_full_address()); ?>
                        <?php if ($location->get_route_url()) : ?>
                        <br><a href="<?php echo esc_url($location->get_route_url()); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Plan route (OpenStreetMap)', '3task-calendar'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($args['show_description'] && !empty($event->description)) : ?>
            <div class="threecal-event-description">
                <?php echo wp_kses_post(wpautop($event->description)); ?>
            </div>
            <?php endif; ?>

            <div class="threecal-event-actions">
                <a class="threecal-button" href="<?php echo esc_url(ThreeCal_ICS::event_url($event->id)); ?>" download>
                    <?php ThreeCal_Icons::render('calendar-plus', 18); ?>
                    <?php esc_html_e('Add to my calendar', '3task-calendar'); ?>
                </a>
                <?php if ($event->url) : ?>
                <a class="threecal-button threecal-button-ghost" href="<?php echo esc_url($event->url); ?>" target="_blank" rel="noopener">
                    <?php ThreeCal_Icons::render('external-link', 18); ?>
                    <?php esc_html_e('More Information', '3task-calendar'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render upcoming events widget
     */
    public function render_upcoming($events, $args = array()) {
        $defaults = array(
            'theme' => '',
            'show_date' => true,
            'show_time' => true,
            'show_location' => true
        );

        $args = wp_parse_args($args, $defaults);
        $args['theme'] = ThreeCal_Themes::normalize($args['theme']);
        ThreeCal_Themes::enqueue($args['theme']);

        $date_format = $this->date_format();
        $time_format = $this->time_format();

        if (empty($events)) {
            return '<p class="threecal-no-events threecal-theme-' . esc_attr($args['theme']) . '">' . esc_html__('No upcoming events.', '3task-calendar') . '</p>';
        }

        $categories = ThreeCal_Event::get_categories_for_events(wp_list_pluck($events, 'id'));

        ob_start();
        ?>
        <ul class="threecal-upcoming threecal-theme-<?php echo esc_attr($args['theme']); ?>" style="<?php echo esc_attr(ThreeCal_Themes::wrapper_style($args['theme'])); ?>">
            <?php foreach ($events as $event) :
                $location = ($args['show_location'] && $event->location_id) ? ThreeCal_Location::get($event->location_id) : null;
                $color = ThreeCal_Themes::event_color($event, isset($categories[$event->id]) ? $categories[$event->id] : array());
                $start = strtotime($event->start_date);
            ?>
            <li class="threecal-upcoming-item<?php echo 'cancelled' === $event->status ? ' threecal-is-cancelled' : ''; ?>" style="<?php echo esc_attr(ThreeCal_Themes::event_style($color)); ?>">
                <?php if ($args['show_date']) : ?>
                <div class="threecal-date-badge threecal-date-badge-small" aria-hidden="true">
                    <span class="threecal-date-badge-month"><?php echo esc_html(date_i18n('M', $start)); ?></span>
                    <span class="threecal-date-badge-day"><?php echo esc_html(date_i18n('j', $start)); ?></span>
                </div>
                <?php endif; ?>
                <div class="threecal-upcoming-content">
                    <span class="threecal-upcoming-title"><?php echo self::cancelled_badge($event); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in cancelled_badge(). ?><?php if ($event->url) : ?><a href="<?php echo esc_url($event->url); ?>"><?php echo esc_html($event->title); ?></a><?php else : ?><?php echo esc_html($event->title); ?><?php endif; ?></span>

                    <?php if ($args['show_date']) : ?>
                    <span class="threecal-upcoming-date">
                        <?php echo esc_html(date_i18n($date_format, $start)); ?>
                        <?php if ($args['show_time'] && !$event->all_day) : ?>
                        <span class="threecal-upcoming-time"> · <?php echo esc_html(date_i18n($time_format, $start)); ?></span>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>

                    <?php $postponed = ThreeCal_Post_Dates::postponed_text($event); ?>
                    <?php if ($postponed) : ?>
                    <span class="threecal-postponed"><?php echo esc_html($postponed); ?></span>
                    <?php endif; ?>

                    <?php if ($location) : ?>
                    <span class="threecal-upcoming-location"><?php ThreeCal_Icons::render('map-pin', 14); ?><?php echo esc_html($location->name); ?></span>
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
            'theme' => '',
            'show_nav' => true,
            'show_today' => true,
            'week_starts_on' => null
        );

        $args = wp_parse_args($args, $defaults);
        $args['theme'] = ThreeCal_Themes::normalize($args['theme']);
        ThreeCal_Themes::enqueue($args['theme']);

        // Get week start from settings if not specified
        if ($args['week_starts_on'] === null) {
            $args['week_starts_on'] = isset($this->settings['week_starts_on']) ? (int) $this->settings['week_starts_on'] : 1;
        }

        // Get current month/year from URL or use current date.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Calendar navigation params, sanitized with absint().
        $month = isset($_GET['cc_mini_month']) ? absint($_GET['cc_mini_month']) : (int) current_time('n');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Calendar navigation params, sanitized with absint().
        $year = isset($_GET['cc_mini_year']) ? absint($_GET['cc_mini_year']) : (int) current_time('Y');

        // Validate month/year
        if ($month < 1 || $month > 12) {
            $month = (int) current_time('n');
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) current_time('Y');
        }

        $week_starts_on = (int) $args['week_starts_on'];
        $date_format = $this->date_format();
        $time_format = $this->time_format();

        // Get first day of month
        $first_day = mktime(0, 0, 0, $month, 1, $year);
        $days_in_month = (int) gmdate('t', $first_day);
        $first_weekday = ((int) gmdate('w', $first_day) - $week_starts_on + 7) % 7;

        $event_args = array(
            'status' => ThreeCal_Event::visible_statuses(),
            'range_start' => gmdate('Y-m-d 00:00:00', $first_day),
            'range_end' => gmdate('Y-m-t 23:59:59', $first_day)
        );

        if (!empty($args['category_id'])) {
            $event_args['category_id'] = $args['category_id'];
        }

        $events = ThreeCal_Event::get_all($event_args);
        $categories = ThreeCal_Event::get_categories_for_events(wp_list_pluck($events, 'id'));

        // Group events by every day they cover, with event data for the popup
        $events_data_by_day = array();
        foreach ($events as $event) {
            $color = ThreeCal_Themes::event_color($event, isset($categories[$event->id]) ? $categories[$event->id] : array());
            $event_time = $event->all_day ? __('All day', '3task-calendar') : date_i18n($time_format, strtotime($event->start_date));
            foreach ($event->get_days_in_month($month, $year) as $day) {
                $events_data_by_day[$day][] = array(
                    'id' => $event->id,
                    'title' => $event->title,
                    'time' => $event_time,
                    'color' => $color,
                    'url' => $event->url ? esc_url_raw($event->url) : ''
                );
            }
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

        $calendar_id = 'threecal-mini-' . wp_rand(1000, 9999);
        $today = current_time('Y-m-d');

        // Enqueue mini calendar CSS and JS.
        wp_enqueue_style( 'threecal-mini' );
        wp_enqueue_script( 'threecal-mini' );

        ob_start();
        ?>
        <div id="<?php echo esc_attr($calendar_id); ?>" class="threecal-mini-wrapper threecal-theme-<?php echo esc_attr($args['theme']); ?>" style="<?php echo esc_attr(ThreeCal_Themes::wrapper_style($args['theme'])); ?>">
            <div class="threecal-mini-header">
                <?php if ($args['show_nav']) : ?>
                <a href="<?php echo esc_url($prev_url); ?>" class="threecal-mini-nav threecal-mini-prev" aria-label="<?php esc_attr_e('Previous month', '3task-calendar'); ?>"><?php ThreeCal_Icons::render('chevron-left', 16); ?></a>
                <?php endif; ?>
                <span class="threecal-mini-title"><?php echo esc_html($this->get_month_name($month) . ' ' . $year); ?></span>
                <?php if ($args['show_nav']) : ?>
                <a href="<?php echo esc_url($next_url); ?>" class="threecal-mini-nav threecal-mini-next" aria-label="<?php esc_attr_e('Next month', '3task-calendar'); ?>"><?php ThreeCal_Icons::render('chevron-right', 16); ?></a>
                <?php endif; ?>
            </div>

            <table class="threecal-mini-grid">
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
                    $weeks = (int) ceil(($first_weekday + $days_in_month) / 7);

                    for ($week = 0; $week < $weeks; $week++) :
                    ?>
                    <tr>
                        <?php for ($weekday = 0; $weekday < 7; $weekday++) :
                            $cell_index = $week * 7 + $weekday;

                            if ($cell_index < $first_weekday || $day > $days_in_month) : ?>
                                <td class="threecal-mini-day threecal-mini-empty"></td>
                            <?php else :
                                $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                $formatted_date = date_i18n($date_format, mktime(0, 0, 0, $month, $day, $year));
                                $is_today = $current_date === $today;
                                $day_events_data = isset($events_data_by_day[$day]) ? $events_data_by_day[$day] : array();
                                $has_events = !empty($day_events_data);
                                ?>
                                <td class="threecal-mini-day<?php echo $is_today ? ' threecal-mini-today' : ''; ?><?php echo $has_events ? ' threecal-mini-has-events' : ''; ?>"
                                    data-date="<?php echo esc_attr($current_date); ?>"
                                    <?php if ($has_events) : ?>
                                    tabindex="0" role="button"
                                    aria-label="<?php echo esc_attr($formatted_date); ?>"
                                    data-date-formatted="<?php echo esc_attr($formatted_date); ?>"
                                    data-events="<?php echo esc_attr(wp_json_encode($day_events_data)); ?>"
                                    <?php endif; ?>>
                                    <span class="threecal-mini-number"><?php echo esc_html($day); ?></span>
                                    <?php if ($has_events) : ?>
                                    <span class="threecal-mini-dot" style="<?php echo esc_attr(ThreeCal_Themes::event_style($day_events_data[0]['color'])); ?>"></span>
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

            <div class="threecal-mini-popup" style="display: none;">
                <div class="threecal-mini-popup-header">
                    <span class="threecal-mini-popup-date"></span>
                    <button type="button" class="threecal-mini-popup-close" aria-label="<?php esc_attr_e('Close', '3task-calendar'); ?>"><?php ThreeCal_Icons::render('x', 16); ?></button>
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
    private function render_pagination($total, $per_page, $current_page, $param = 'tc_page') {
        $param = sanitize_key($param);
        $total_pages = (int) ceil($total / max(1, $per_page));
        $current_page = (int) $current_page;

        if ($total_pages <= 1) {
            return '';
        }

        $output = '<nav class="threecal-nav-pagination" aria-label="' . esc_attr__('Event navigation', '3task-calendar') . '">';

        if ($current_page > 1) {
            $output .= '<a href="' . esc_url(add_query_arg($param, $current_page - 1)) . '" class="threecal-page-prev">';
            $output .= '&laquo; ' . esc_html__('Previous', '3task-calendar');
            $output .= '</a>';
        }

        // First, last and the pages around the current one; gaps become an ellipsis.
        $gap = false;
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i !== 1 && $i !== $total_pages && abs($i - $current_page) > 1) {
                if (!$gap) {
                    $output .= '<span class="threecal-page-gap" aria-hidden="true">&hellip;</span>';
                    $gap = true;
                }
                continue;
            }
            $gap = false;
            if ($i === $current_page) {
                $output .= '<span class="threecal-page-current" aria-current="page">' . $i . '</span>';
            } else {
                $output .= '<a href="' . esc_url(add_query_arg($param, $i)) . '">' . $i . '</a>';
            }
        }

        if ($current_page < $total_pages) {
            $output .= '<a href="' . esc_url(add_query_arg($param, $current_page + 1)) . '" class="threecal-page-next">';
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
