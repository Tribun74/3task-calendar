<?php
/**
 * 3task Calendar Shortcode Handler
 *
 * Registers and handles all shortcodes.
 *
 * @package ThreeCal
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode class.
 */
class ThreeCal_Shortcode {

	/**
	 * Number of event lists rendered in this request. Each list pages and searches with its own
	 * query parameters (tc_page, tc_page2, ...). Public so that tests and themes that render
	 * lists outside the main query can start counting again. Since 1.8.0 (was a static local).
	 *
	 * @var int
	 */
	public static $list_instance = 0;

	/**
	 * Constructor - register shortcodes
	 */
	public function __construct() {
		add_shortcode( 'threecal', array( $this, 'render_calendar' ) );
		add_shortcode( 'threecal_event', array( $this, 'render_single_event' ) );
		add_shortcode( 'threecal_events', array( $this, 'render_event_list' ) );
		add_shortcode( 'threecal_upcoming', array( $this, 'render_upcoming' ) );
		add_shortcode( 'threecal_mini', array( $this, 'render_mini_calendar' ) );
	}

	/**
	 * Render calendar shortcode
	 * [threecal view="month" category="1" theme="default"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_calendar( $atts ) {
		$atts = shortcode_atts(
			array(
				'view'           => 'month',
				'category'       => 0,
				'location'       => 0,
				'theme'          => '',
				'show_filters'   => 'true',
				'show_legend'    => 'true',
				'show_subscribe' => 'true',
				'show_print'     => 'true',
				'show_search'    => 'true',
				'mobile_list'    => 'true',
				'week_starts_on' => '',
			),
			$atts,
			'threecal'
		);

		// Enqueue styles and scripts.
		wp_enqueue_style( 'threecal-public' );
		wp_enqueue_script( 'threecal-public' );

		$renderer = new ThreeCal_Calendar_Renderer();

		return $renderer->render_calendar(
			array(
				'view'           => sanitize_text_field( $atts['view'] ),
				'category_id'    => absint( $atts['category'] ),
				'location_id'    => absint( $atts['location'] ),
				'theme'          => sanitize_text_field( $atts['theme'] ),
				'show_filters'   => $atts['show_filters'] === 'true',
				'show_legend'    => $atts['show_legend'] === 'true',
				'show_subscribe' => $atts['show_subscribe'] === 'true',
				'show_print'     => $atts['show_print'] === 'true',
				'show_search'    => $atts['show_search'] !== 'false',
				'mobile_list'    => $atts['mobile_list'] === 'true',
				'week_starts_on' => $atts['week_starts_on'] !== '' ? absint( $atts['week_starts_on'] ) : null,
			)
		);
	}

	/**
	 * Render single event shortcode
	 * [threecal_event id="123" show_map="true"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_single_event( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'               => 0,
				'theme'            => '',
				'show_map'         => 'true',
				'show_description' => 'true',
			),
			$atts,
			'threecal_event'
		);

		$event_id = absint( $atts['id'] );

		if ( ! $event_id ) {
			return '';
		}

		$event = ThreeCal_Event::get( $event_id );

		if ( ! $event || ! $event->is_visible() ) {
			return '';
		}

		// Enqueue styles.
		wp_enqueue_style( 'threecal-public' );

		$renderer = new ThreeCal_Calendar_Renderer();

		return $renderer->render_single_event(
			$event,
			array(
				'theme'            => sanitize_text_field( $atts['theme'] ),
				'show_map'         => $atts['show_map'] === 'true',
				'show_description' => $atts['show_description'] === 'true',
			)
		);
	}

	/**
	 * Render event list shortcode
	 * [threecal_events category="1" limit="10" view="list"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_event_list( $atts ) {
		$atts = shortcode_atts(
			array(
				'category'        => 0,
				'location'        => 0,
				'limit'           => 10,
				'view'            => 'list',
				'theme'           => '',
				'show_past'       => 'false',
				'show_pagination' => 'true',
				'columns'         => 3,
				'month'           => '',
				'running'         => 0,
				'day'             => '',
				'show_search'     => 'true',
			),
			$atts,
			'threecal_events'
		);

		// Enqueue styles.
		wp_enqueue_style( 'threecal-public' );

		// Every list on a page pages and searches on its own (tc_page, tc_page2, ..., tc_q, tc_q2, ...).
		self::$list_instance++;
		$instance = self::$list_instance;
		$page_param   = 1 === $instance ? 'tc_page' : 'tc_page' . $instance;
		$search_param = 1 === $instance ? 'tc_q' : 'tc_q' . $instance;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Public search term, cleaned by sanitize_search().
		$search_term = isset( $_GET[ $search_param ] ) ? ThreeCal_Event::sanitize_search( wp_unslash( $_GET[ $search_param ] ) ) : '';

		$args = array(
			'status'   => ThreeCal_Event::visible_statuses(),
			'per_page' => absint( $atts['limit'] ),
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Pagination param, sanitized with absint().
			'page'     => isset( $_GET[ $page_param ] ) ? max( 1, absint( $_GET[ $page_param ] ) ) : 1,
			'orderby'  => 'start_date',
			'order'    => 'ASC',
		);

		if ( absint( $atts['category'] ) > 0 ) {
			$args['category_id'] = absint( $atts['category'] );
		}

		if ( absint( $atts['location'] ) > 0 ) {
			$args['location_id'] = absint( $atts['location'] );
		}

		if ( '' !== $search_term ) {
			$args['search'] = $search_term;
		}

		$month   = self::month_range( (string) $atts['month'] );
		$running = absint( $atts['running'] );
		$day     = self::day_value( (string) $atts['day'] );

		if ( '' !== $day ) {
			// One day (since 1.8.0): every event that takes place on it, past days included.
			$args['range_start'] = $day . ' 00:00:00';
			$args['range_end']   = $day . ' 23:59:59';
		} elseif ( $running > 0 ) {
			// "Now showing": dates that started within the last N days, newest first.
			$now                  = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Site time is wanted here.
			$args['start_after']  = gmdate( 'Y-m-d 00:00:00', $now - $running * DAY_IN_SECONDS );
			$args['start_before'] = gmdate( 'Y-m-d H:i:s', $now );
			$args['order']        = 'DESC';
		} elseif ( $month ) {
			// A whole month, past days included.
			$args['start_after']  = $month[0];
			$args['start_before'] = $month[1];
		} elseif ( $atts['show_past'] !== 'true' ) {
			// Running events (started, not yet ended) stay in the list.
			$args['ends_after'] = current_time( 'mysql' );
		}

		$events = ThreeCal_Event::get_all( $args );
		$total  = ThreeCal_Event::count( $args );

		$renderer = new ThreeCal_Calendar_Renderer();

		// The search form carries the other query parameters along and drops term and page.
		$search_form = '';
		$reset_url   = remove_query_arg( array( $search_param, $page_param ) );
		if ( $atts['show_search'] !== 'false' ) {
			$keep = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only read to keep the current address; each value is sanitized in render_search_form().
			foreach ( (array) $_GET as $key => $value ) {
				if ( $key !== $search_param && $key !== $page_param && is_scalar( $value ) ) {
					$keep[ sanitize_key( $key ) ] = $value;
				}
			}
			$search_form = ThreeCal_Calendar_Renderer::render_search_form(
				array(
					'id'     => 'threecal-list-' . $instance,
					'value'  => $search_term,
					'param'  => $search_param,
					'method' => 'get',
					'action' => $reset_url,
					'keep'   => $keep,
				)
			);
		}

		return $renderer->render_event_list(
			$events,
			array(
				'view'            => sanitize_text_field( $atts['view'] ),
				'theme'           => sanitize_text_field( $atts['theme'] ),
				'show_pagination' => $atts['show_pagination'] === 'true',
				'columns'         => absint( $atts['columns'] ),
				'total'           => $total,
				'per_page'        => absint( $atts['limit'] ),
				'current_page'    => $args['page'],
				'page_param'      => $page_param,
				'search_form'     => $search_form,
				'search_term'     => $search_term,
				'reset_url'       => $reset_url,
				'group_days'      => '' !== $search_term,
			)
		);
	}

	/**
	 * A valid day for the "day" attribute.
	 *
	 * @since 1.8.0
	 * @param string $value Day as YYYY-MM-DD.
	 * @return string The day, or an empty string when the value is no real date.
	 */
	private static function day_value( $value ) {
		$value = trim( $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * First and last second of a month for the "month" attribute.
	 *
	 * @param string $value "current", "next" or a month such as "2026-10".
	 * @return array|null Start and end as MySQL dates, or null.
	 */
	private static function month_range( $value ) {
		$value = strtolower( trim( $value ) );
		if ( '' === $value ) {
			return null;
		}

		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Site time is wanted here.
		if ( 'current' === $value ) {
			$year  = (int) gmdate( 'Y', $now );
			$month = (int) gmdate( 'n', $now );
		} elseif ( 'next' === $value ) {
			$year  = (int) gmdate( 'Y', $now );
			$month = (int) gmdate( 'n', $now ) + 1;
			if ( $month > 12 ) {
				$month = 1;
				$year++;
			}
		} elseif ( preg_match( '/^(\d{4})-(\d{1,2})$/', $value, $m ) && (int) $m[2] >= 1 && (int) $m[2] <= 12 ) {
			$year  = (int) $m[1];
			$month = (int) $m[2];
		} else {
			return null;
		}

		$days = (int) gmdate( 't', gmmktime( 0, 0, 0, $month, 1, $year ) );

		return array(
			sprintf( '%04d-%02d-01 00:00:00', $year, $month ),
			sprintf( '%04d-%02d-%02d 23:59:59', $year, $month, $days ),
		);
	}

	/**
	 * Render upcoming events widget/shortcode
	 * [threecal_upcoming limit="5" category="1"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_upcoming( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'         => 5,
				'category'      => 0,
				'theme'         => '',
				'show_date'     => 'true',
				'show_time'     => 'true',
				'show_location' => 'true',
			),
			$atts,
			'threecal_upcoming'
		);

		// Enqueue styles.
		wp_enqueue_style( 'threecal-public' );

		$events = ThreeCal_Event::get_upcoming(
			absint( $atts['limit'] ),
			absint( $atts['category'] )
		);

		$renderer = new ThreeCal_Calendar_Renderer();

		return $renderer->render_upcoming(
			$events,
			array(
				'theme'         => sanitize_text_field( $atts['theme'] ),
				'show_date'     => $atts['show_date'] === 'true',
				'show_time'     => $atts['show_time'] === 'true',
				'show_location' => $atts['show_location'] === 'true',
			)
		);
	}

	/**
	 * Render mini calendar shortcode (compact for sidebar widgets)
	 * [threecal_mini category="1" show_nav="true"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_mini_calendar( $atts ) {
		$atts = shortcode_atts(
			array(
				'category'       => 0,
				'theme'          => '',
				'show_nav'       => 'true',
				'show_today'     => 'true',
				'week_starts_on' => '',
			),
			$atts,
			'threecal_mini'
		);

		// Enqueue styles.
		wp_enqueue_style( 'threecal-public' );

		$renderer = new ThreeCal_Calendar_Renderer();

		return $renderer->render_mini_calendar(
			array(
				'category_id'    => absint( $atts['category'] ),
				'theme'          => sanitize_text_field( $atts['theme'] ),
				'show_nav'       => $atts['show_nav'] === 'true',
				'show_today'     => $atts['show_today'] === 'true',
				'week_starts_on' => $atts['week_starts_on'] !== '' ? absint( $atts['week_starts_on'] ) : null,
			)
		);
	}
}
