<?php
/**
 * 3task Calendar iCalendar export
 *
 * Serves a single event as .ics download ("add to my calendar") and a feed
 * that calendar apps can subscribe to. Everything is generated locally, no
 * external service is involved.
 *
 * @package ThreeCal
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ICS class.
 */
class ThreeCal_ICS {

	/**
	 * Number of days in the past that the feed still contains.
	 */
	const FEED_PAST_DAYS = 30;

	/**
	 * Maximum number of events in the feed.
	 */
	const FEED_LIMIT = 500;

	/**
	 * Constructor - register hooks
	 */
	public function __construct() {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve' ), 1 );
	}

	/**
	 * Register query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[] = 'threecal_ics';
		$vars[] = 'threecal_feed';
		$vars[] = 'threecal_cat';
		return $vars;
	}

	/**
	 * URL of the .ics download for one event.
	 *
	 * @param int $event_id Event ID.
	 * @return string
	 */
	public static function event_url( $event_id ) {
		return add_query_arg( 'threecal_ics', absint( $event_id ), home_url( '/' ) );
	}

	/**
	 * URL of the subscription feed.
	 *
	 * @param int  $category_id Category filter (0 = all).
	 * @param bool $webcal      Use the webcal:// scheme so calendar apps open it directly.
	 * @return string
	 */
	public static function feed_url( $category_id = 0, $webcal = false ) {
		$args = array( 'threecal_feed' => 1 );
		if ( $category_id ) {
			$args['threecal_cat'] = absint( $category_id );
		}
		$url = add_query_arg( $args, home_url( '/' ) );
		if ( $webcal ) {
			$url = preg_replace( '#^https?://#i', 'webcal://', $url );
		}
		return $url;
	}

	/**
	 * Serve .ics content when requested.
	 */
	public function maybe_serve() {
		$event_id = absint( get_query_var( 'threecal_ics' ) );
		$feed     = absint( get_query_var( 'threecal_feed' ) );

		if ( $event_id ) {
			$event = ThreeCal_Event::get( $event_id );
			if ( ! $event || ! in_array( $event->status, array( 'published', 'cancelled' ), true ) ) {
				status_header( 404 );
				nocache_headers();
				exit;
			}
			$this->send( $this->build_calendar( array( $event ), $event->title ), sanitize_title( $event->title ) ? sanitize_title( $event->title ) : 'event-' . $event->id, 'attachment' );
		}

		if ( $feed ) {
			$category = absint( get_query_var( 'threecal_cat' ) );
			$events   = ThreeCal_Event::get_all(
				array(
					'status'      => '',
					'category_id' => $category,
					'ends_after'  => gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - self::FEED_PAST_DAYS * DAY_IN_SECONDS ),
					'orderby'     => 'start_date',
					'order'       => 'ASC',
					'per_page'    => self::FEED_LIMIT,
				)
			);
			$events = array_filter(
				$events,
				function ( $event ) {
					return in_array( $event->status, array( 'published', 'cancelled' ), true );
				}
			);
			$this->send( $this->build_calendar( $events, get_bloginfo( 'name' ) ), 'calendar', 'inline' );
		}
	}

	/**
	 * Send the calendar and stop.
	 *
	 * @param string $ics         Calendar content.
	 * @param string $filename    File name without extension.
	 * @param string $disposition attachment or inline.
	 */
	private function send( $ics, $filename, $disposition ) {
		$filename = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $filename ) );
		if ( '' === $filename ) {
			$filename = 'calendar';
		}

		status_header( 200 );
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: ' . ( 'inline' === $disposition ? 'inline' : 'attachment' ) . '; filename="' . $filename . '.ics"' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'X-Robots-Tag: noindex' );
		echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCalendar text, every value is escaped in escape_text().
		exit;
	}

	/**
	 * Build a VCALENDAR document.
	 *
	 * @param ThreeCal_Event[] $events Events.
	 * @param string           $name   Calendar name.
	 * @return string
	 */
	public function build_calendar( $events, $name ) {
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//3task//3task Calendar ' . THREECAL_VERSION . '//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . $this->escape_text( $name ),
			'X-WR-TIMEZONE:' . $this->escape_text( wp_timezone_string() ),
			'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
			'X-PUBLISHED-TTL:PT6H',
		);

		foreach ( $events as $event ) {
			$lines = array_merge( $lines, $this->build_event( $event ) );
		}

		$lines[] = 'END:VCALENDAR';

		$out = '';
		foreach ( $lines as $line ) {
			$out .= $this->fold( $line ) . "\r\n";
		}
		return $out;
	}

	/**
	 * Build the VEVENT lines for one event.
	 *
	 * @param ThreeCal_Event $event Event.
	 * @return string[]
	 */
	private function build_event( $event ) {
		$host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$lines = array(
			'BEGIN:VEVENT',
			'UID:threecal-' . absint( $event->id ) . '@' . $this->escape_text( $host ? $host : 'localhost' ),
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z', strtotime( $event->updated_at ? $event->updated_at . ' UTC' : 'now' ) ),
		);

		$start = $this->to_datetime( $event->start_date );
		$end   = $event->end_date ? $this->to_datetime( $event->end_date ) : null;

		if ( $start ) {
			if ( $event->all_day ) {
				// All-day: DTEND is the day after the last day (exclusive).
				$last    = $end ? $end : $start;
				$lines[] = 'DTSTART;VALUE=DATE:' . $start->format( 'Ymd' );
				$lines[] = 'DTEND;VALUE=DATE:' . $last->modify( '+1 day' )->format( 'Ymd' );
			} else {
				$utc     = new DateTimeZone( 'UTC' );
				$lines[] = 'DTSTART:' . $start->setTimezone( $utc )->format( 'Ymd\THis\Z' );
				$end_dt  = $end ? $end : $start->modify( '+1 hour' );
				$lines[] = 'DTEND:' . $end_dt->setTimezone( $utc )->format( 'Ymd\THis\Z' );
			}
		}

		$lines[] = 'SUMMARY:' . $this->escape_text( $event->title );

		$description = trim( html_entity_decode( wp_strip_all_tags( (string) $event->description ), ENT_QUOTES, 'UTF-8' ) );
		if ( '' !== $description ) {
			$lines[] = 'DESCRIPTION:' . $this->escape_text( $description );
		}

		if ( $event->location_id ) {
			$location = ThreeCal_Location::get( $event->location_id );
			if ( $location ) {
				$address = trim( $location->name . ', ' . $location->get_full_address(), ', ' );
				$lines[] = 'LOCATION:' . $this->escape_text( $address );
				if ( $location->has_coordinates() ) {
					$lines[] = 'GEO:' . (float) $location->latitude . ';' . (float) $location->longitude;
				}
			}
		}

		if ( $event->url && wp_http_validate_url( $event->url ) ) {
			$lines[] = 'URL:' . $this->escape_text( esc_url_raw( $event->url ) );
		}

		$categories = ThreeCal_Event::get_categories( $event->id );
		if ( ! empty( $categories ) ) {
			$names   = array_map(
				function ( $cat ) {
					return $this->escape_text( $cat->name );
				},
				$categories
			);
			$lines[] = 'CATEGORIES:' . implode( ',', $names );
		}

		$lines[] = 'STATUS:' . ( 'cancelled' === $event->status ? 'CANCELLED' : 'CONFIRMED' );
		$lines[] = 'END:VEVENT';

		return $lines;
	}

	/**
	 * Parse a stored local date in the site time zone.
	 *
	 * @param string $value Y-m-d H:i:s.
	 * @return DateTimeImmutable|null
	 */
	private function to_datetime( $value ) {
		if ( empty( $value ) ) {
			return null;
		}
		try {
			return new DateTimeImmutable( $value, wp_timezone() );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Escape a TEXT value (RFC 5545 3.3.11). Line breaks become \n, so no
	 * property can be injected through event data.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public function escape_text( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$text = str_replace( '\\', '\\\\', $text );
		$text = str_replace( array( ';', ',' ), array( '\\;', '\\,' ), $text );
		return str_replace( "\n", '\\n', $text );
	}

	/**
	 * Fold a content line at 75 octets without splitting UTF-8 characters.
	 *
	 * @param string $line Content line.
	 * @return string
	 */
	private function fold( $line ) {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}

		$out     = '';
		$current = '';
		$limit   = 75;
		$chars   = preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY );

		foreach ( $chars as $char ) {
			if ( strlen( $current ) + strlen( $char ) > $limit ) {
				$out    .= $current . "\r\n ";
				$current = '';
				$limit   = 74; // Continuation lines start with a space.
			}
			$current .= $char;
		}

		return $out . $current;
	}
}
