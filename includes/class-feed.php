<?php
/**
 * 3task Calendar: public JSON feed of upcoming events.
 *
 * GET /wp-json/threecal/v1/events?from=YYYY-MM-DD&to=YYYY-MM-DD&category=slug-or-id&limit=50&search=word&day=YYYY-MM-DD
 * A stable, documented feed for AI search engines, apps and other websites: ISO 8601 dates
 * with time zone, plain-text descriptions, place, organizer, categories and the calendar
 * file of every event. Pages with a calendar announce it with a <link rel="alternate">.
 *
 * @package ThreeCal
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feed class.
 */
class ThreeCal_Feed {

	/**
	 * Most events in one answer.
	 */
	const MAX_LIMIT = 200;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
		add_action( 'wp_head', array( __CLASS__, 'alternate_link' ), 2 );
	}

	/**
	 * Address of the feed.
	 *
	 * @return string
	 */
	public static function url() {
		return rest_url( 'threecal/v1/events' );
	}

	/**
	 * Register the route.
	 */
	public static function register() {
		register_rest_route(
			'threecal/v1',
			'/events',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'respond' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'from'     => array(
						'description'       => 'First day (YYYY-MM-DD), default today. Events still running on that day are included.',
						'type'              => 'string',
						'validate_callback' => array( __CLASS__, 'validate_date' ),
					),
					'to'       => array(
						'description'       => 'Last day (YYYY-MM-DD).',
						'type'              => 'string',
						'validate_callback' => array( __CLASS__, 'validate_date' ),
					),
					'category' => array(
						'description'       => 'Category slug or ID.',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'    => array(
						'description'       => 'How many events, default 50, at most 200.',
						'type'              => 'integer',
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
					'search'   => array(
						'description'       => 'Word or part of a word in title, description, location name or city; at most 80 characters. Since 1.8.0.',
						'type'              => 'string',
						'sanitize_callback' => array( 'ThreeCal_Event', 'sanitize_search' ),
					),
					'day'      => array(
						'description'       => 'One day (YYYY-MM-DD): every event that takes place on that day, past days included. Replaces from and to. Since 1.8.0.',
						'type'              => 'string',
						'validate_callback' => array( __CLASS__, 'validate_date' ),
					),
				),
			)
		);
	}

	/**
	 * YYYY-MM-DD or empty.
	 *
	 * @param mixed $value Parameter.
	 * @return true|WP_Error
	 */
	public static function validate_date( $value ) {
		if ( null === $value || '' === $value ) {
			return true;
		}
		if ( is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) && false !== strtotime( $value ) ) {
			return true;
		}
		return new WP_Error( 'rest_invalid_param', __( 'Dates must be written as YYYY-MM-DD.', '3task-calendar' ), array( 'status' => 400 ) );
	}

	/**
	 * Answer the request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function respond( $request ) {
		$from     = (string) $request->get_param( 'from' );
		$to       = (string) $request->get_param( 'to' );
		$category = (string) $request->get_param( 'category' );
		$search   = ThreeCal_Event::sanitize_search( $request->get_param( 'search' ) );
		$day      = (string) $request->get_param( 'day' );
		$limit    = min( self::MAX_LIMIT, max( 1, (int) $request->get_param( 'limit' ) ) );

		$args = array(
			'status'   => ThreeCal_Event::visible_statuses(),
			'orderby'  => 'start_date',
			'order'    => 'ASC',
			'per_page' => $limit,
		);
		if ( '' !== $day ) {
			// Every event that touches that day, also in the past.
			$args['range_start'] = $day . ' 00:00:00';
			$args['range_end']   = $day . ' 23:59:59';
		} else {
			$args['ends_after'] = ( '' !== $from ? $from : current_time( 'Y-m-d' ) ) . ' 00:00:00';
			if ( '' !== $to ) {
				$args['start_before'] = $to . ' 23:59:59';
			}
		}
		if ( '' !== $search ) {
			$args['search'] = $search;
		}
		if ( '' !== $category ) {
			$cat = ctype_digit( $category ) ? ThreeCal_Category::get( (int) $category ) : ThreeCal_Category::get_by_slug( $category );
			$args['category_id'] = ( $cat && ! empty( $cat->id ) ) ? (int) $cat->id : -1; // -1 finds nothing: unknown category gives an empty list, not everything.
		}

		$events = -1 === ( isset( $args['category_id'] ) ? $args['category_id'] : 0 ) ? array() : ThreeCal_Event::get_all( $args );
		$cats   = $events ? ThreeCal_Event::get_categories_for_events( wp_list_pluck( $events, 'id' ) ) : array();
		$list   = array();
		foreach ( $events as $event ) {
			$list[] = self::format( $event, isset( $cats[ $event->id ] ) ? $cats[ $event->id ] : array() );
		}

		$body = array(
			'name'      => get_bloginfo( 'name' ),
			'url'       => home_url( '/' ),
			'generated' => wp_date( DATE_ATOM ),
			'count'     => count( $list ),
			'events'    => $list,
		);
		$response = new WP_REST_Response( $body, 200 );
		$response->header( 'Cache-Control', 'public, max-age=900' );
		return $response;
	}

	/**
	 * One event for the feed.
	 *
	 * @param ThreeCal_Event $event      Event.
	 * @param array          $categories Category rows.
	 * @return array
	 */
	public static function format( $event, $categories ) {
		$month_only = $event->is_month_only();
		$location   = null;
		if ( $event->location_id ) {
			$loc = ThreeCal_Location::get( $event->location_id );
			if ( $loc ) {
				$location = array(
					'name'        => (string) $loc->name,
					'address'     => (string) $loc->address,
					'postal_code' => (string) $loc->postal_code,
					'city'        => (string) $loc->city,
					'country'     => (string) $loc->country,
				);
			}
		}
		$organizer = $event->get_organizer();
		if ( '' === $organizer['name'] && '' === $organizer['url'] ) {
			$organizer = null;
		}
		$description = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $event->description ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		return array(
			'id'          => (int) $event->id,
			'title'       => (string) $event->title,
			'start'       => $month_only ? substr( (string) $event->start_date, 0, 7 ) : self::iso( $event->start_date, $event->all_day ),
			'end'         => ( $month_only || ! $event->end_date ) ? null : self::iso( $event->end_date, $event->all_day ),
			'all_day'     => (bool) $event->all_day,
			'month_only'  => $month_only,
			'status'      => 'cancelled' === $event->status ? 'cancelled' : 'scheduled',
			'url'         => $event->url ? esc_url_raw( $event->url ) : null,
			'description' => '' === $description ? null : $description,
			'image'       => $event->featured_image ? ( wp_get_attachment_url( $event->featured_image ) ?: null ) : null,
			'location'    => $location,
			'organizer'   => $organizer,
			'categories'  => array_values( array_map( function ( $cat ) {
				return array( 'id' => (int) $cat->id, 'name' => (string) $cat->name, 'slug' => (string) $cat->slug );
			}, (array) $categories ) ),
			'ics'         => $month_only ? null : ThreeCal_ICS::event_url( $event->id ),
		);
	}

	/**
	 * Stored local date as ISO 8601; all-day dates as plain date.
	 *
	 * @param string $value   Y-m-d H:i:s in site time.
	 * @param bool   $all_day All-day event.
	 * @return string
	 */
	private static function iso( $value, $all_day ) {
		if ( $all_day ) {
			return substr( (string) $value, 0, 10 );
		}
		try {
			$dt = new DateTimeImmutable( (string) $value, wp_timezone() );
			return $dt->format( DATE_ATOM );
		} catch ( Exception $e ) {
			return (string) $value;
		}
	}

	/**
	 * <link rel="alternate"> on pages that show a calendar.
	 */
	public static function alternate_link() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post || ! threecal()->page_has_calendar( $post ) ) {
			return;
		}
		echo '<link rel="alternate" type="application/json" title="' . esc_attr__( 'Events (JSON)', '3task-calendar' ) . '" href="' . esc_url( self::url() ) . '">' . "\n";
	}
}
