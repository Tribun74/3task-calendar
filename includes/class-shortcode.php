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
				'theme'          => 'default',
				'show_filters'   => 'true',
				'show_legend'    => 'true',
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
				'theme'            => 'default',
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

		if ( ! $event || $event->status !== 'published' ) {
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
				'theme'           => 'default',
				'show_past'       => 'false',
				'show_pagination' => 'true',
				'columns'         => 3,
			),
			$atts,
			'threecal_events'
		);

		// Enqueue styles.
		wp_enqueue_style( 'threecal-public' );

		$args = array(
			'status'   => 'published',
			'per_page' => absint( $atts['limit'] ),
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Pagination param, sanitized with absint().
			'page'     => isset( $_GET['tc_page'] ) ? absint( $_GET['tc_page'] ) : 1,
			'orderby'  => 'start_date',
			'order'    => 'ASC',
		);

		if ( absint( $atts['category'] ) > 0 ) {
			$args['category_id'] = absint( $atts['category'] );
		}

		if ( absint( $atts['location'] ) > 0 ) {
			$args['location_id'] = absint( $atts['location'] );
		}

		if ( $atts['show_past'] !== 'true' ) {
			$args['start_after'] = current_time( 'mysql' );
		}

		$events = ThreeCal_Event::get_all( $args );
		$total  = ThreeCal_Event::count( $args );

		$renderer = new ThreeCal_Calendar_Renderer();

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
			)
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
				'theme'         => 'default',
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
				'theme'          => 'default',
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
