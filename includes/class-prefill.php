<?php
/**
 * 3task Calendar: fill the event form from a website.
 *
 * The user enters the address of the event's website and clicks "Fill in from this
 * website". The server fetches that one page, reads its event markup (schema.org Event
 * as JSON-LD) or, if there is none, the Open Graph tags, and the form fields are filled
 * in. Nothing is fetched without that click, and only the address the user entered.
 *
 * @package ThreeCal
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prefill class.
 */
class ThreeCal_Prefill {

	/**
	 * Largest page that is read (bytes).
	 */
	const MAX_BYTES = 1572864;

	/**
	 * Register the AJAX handler (logged-in users only).
	 */
	public static function init() {
		add_action( 'wp_ajax_threecal_prefill_from_url', array( __CLASS__, 'ajax' ) );
	}

	/**
	 * Empty result with every key present.
	 *
	 * @return array
	 */
	private static function leer() {
		return array(
			'title'       => '',
			'start'       => '',
			'end'         => '',
			'all_day'     => false,
			'description' => '',
			'image'       => '',
			'url'         => '',
			'location'    => array( 'name' => '', 'address' => '', 'postal_code' => '', 'city' => '', 'country' => '' ),
			'organizer'   => array( 'name' => '', 'url' => '' ),
			'source'      => '',
		);
	}

	/**
	 * Read event data from a page.
	 *
	 * @param string $html The page.
	 * @param string $url  Address of the page (used when the markup names no URL).
	 * @return array Fields (see leer()) or an empty array when the page holds nothing usable.
	 */
	public static function parse( $html, $url ) {
		$html  = (string) $html;
		$out   = self::leer();
		$event = self::find_event( $html );

		if ( $event ) {
			$out['source']      = 'schema';
			$out['title']       = self::text( self::first( isset( $event['name'] ) ? $event['name'] : '' ) );
			$out['description'] = self::description( isset( $event['description'] ) ? $event['description'] : '' );
			$out['image']       = self::image( isset( $event['image'] ) ? $event['image'] : '' );
			$out['url']         = self::url( isset( $event['url'] ) ? self::first( $event['url'] ) : '' );
			$start              = self::date( isset( $event['startDate'] ) ? self::first( $event['startDate'] ) : '', false );
			$end                = self::date( isset( $event['endDate'] ) ? self::first( $event['endDate'] ) : '', true );
			$out['start']       = $start['value'];
			$out['end']         = $end['value'];
			$out['all_day']     = $start['all_day'];
			$out['location']    = self::location( isset( $event['location'] ) ? $event['location'] : '' );
			$out['organizer']   = self::organizer( isset( $event['organizer'] ) ? $event['organizer'] : '' );
		} else {
			$out['source']      = 'meta';
			$out['title']       = self::text( self::meta( $html, 'og:title' ) );
			$out['description'] = self::description( self::meta( $html, 'og:description' ) );
			$out['image']       = self::url( self::meta( $html, 'og:image' ) );
			if ( '' === $out['title'] && preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
				$out['title'] = self::text( $m[1] );
			}
		}
		if ( '' === $out['url'] ) {
			$out['url'] = self::url( $url );
		}
		if ( '' === $out['title'] && '' === $out['start'] && '' === $out['description'] ) {
			return array();
		}
		return $out;
	}

	/**
	 * First schema.org Event on the page: the next upcoming one if there are several.
	 *
	 * @param string $html The page.
	 * @return array|null
	 */
	private static function find_event( $html ) {
		if ( ! preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $blocks ) ) {
			return null;
		}
		$events = array();
		foreach ( $blocks[1] as $block ) {
			$block = trim( preg_replace( '#^\s*<!\[CDATA\[|\]\]>\s*$#', '', trim( $block ) ) );
			$data  = json_decode( $block, true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			self::collect_events( $data, $events, 0 );
		}
		if ( ! $events ) {
			return null;
		}
		$now  = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- compared with the site's local dates below.
		$best = null;
		foreach ( $events as $event ) {
			$start = isset( $event['startDate'] ) ? strtotime( (string) self::first( $event['startDate'] ) ) : false;
			if ( false !== $start && $start >= $now - DAY_IN_SECONDS && ( null === $best || $start < $best[0] ) ) {
				$best = array( $start, $event );
			}
		}
		return $best ? $best[1] : $events[0];
	}

	/**
	 * Walk JSON-LD and collect nodes whose type ends in "Event".
	 *
	 * @param mixed $node   Array or scalar.
	 * @param array $events Collected events (by reference).
	 * @param int   $depth  Recursion depth.
	 */
	private static function collect_events( $node, &$events, $depth ) {
		if ( $depth > 6 || ! is_array( $node ) ) {
			return;
		}
		if ( isset( $node['@type'] ) ) {
			foreach ( (array) $node['@type'] as $type ) {
				if ( is_string( $type ) && preg_match( '/Event$/', $type ) ) {
					$events[] = $node;
					return;
				}
			}
		}
		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				self::collect_events( $value, $events, $depth + 1 );
			}
		}
	}

	/**
	 * First element of a list, or the value itself.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function first( $value ) {
		if ( is_array( $value ) && isset( $value[0] ) ) {
			return $value[0];
		}
		return $value;
	}

	/**
	 * Plain text without tags and entities.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function text( $value ) {
		if ( is_array( $value ) ) {
			$value = self::first( $value );
		}
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Description: plain text, at most 80 words.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function description( $value ) {
		$text = self::text( $value );
		return '' === $text ? '' : wp_trim_words( $text, 80, ' …' );
	}

	/**
	 * A valid http(s) URL or empty string.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function url( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$url = esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
		return $url ? $url : '';
	}

	/**
	 * Image URL from a string, a list or an ImageObject.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function image( $value ) {
		$value = self::first( $value );
		if ( is_array( $value ) ) {
			$value = isset( $value['url'] ) ? $value['url'] : ( isset( $value['contentUrl'] ) ? $value['contentUrl'] : '' );
		}
		return self::url( $value );
	}

	/**
	 * Date in site time as "Y-m-d H:i"; a date without time is an all-day date.
	 *
	 * @param mixed $value  ISO 8601 date or date-time.
	 * @param bool  $is_end True for the end: an all-day end becomes 23:59.
	 * @return array value, all_day
	 */
	private static function date( $value, $is_end ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value ) {
			return array( 'value' => '', 'all_day' => false );
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return array( 'value' => $value . ( $is_end ? ' 23:59' : ' 00:00' ), 'all_day' => true );
		}
		try {
			$dt = new DateTimeImmutable( $value, wp_timezone() );
		} catch ( Exception $e ) {
			return array( 'value' => '', 'all_day' => false );
		}
		return array( 'value' => $dt->setTimezone( wp_timezone() )->format( 'Y-m-d H:i' ), 'all_day' => false );
	}

	/**
	 * Location from a Place node or a plain string.
	 *
	 * @param mixed $value Value.
	 * @return array name, address, postal_code, city, country
	 */
	private static function location( $value ) {
		$out   = array( 'name' => '', 'address' => '', 'postal_code' => '', 'city' => '', 'country' => '' );
		$value = self::first( $value );
		if ( is_scalar( $value ) ) {
			$out['name'] = self::text( $value );
			return $out;
		}
		if ( ! is_array( $value ) ) {
			return $out;
		}
		$out['name'] = self::text( isset( $value['name'] ) ? $value['name'] : '' );
		$address     = isset( $value['address'] ) ? self::first( $value['address'] ) : '';
		if ( is_array( $address ) ) {
			$out['address']     = self::text( isset( $address['streetAddress'] ) ? $address['streetAddress'] : '' );
			$out['postal_code'] = self::text( isset( $address['postalCode'] ) ? $address['postalCode'] : '' );
			$out['city']        = self::text( isset( $address['addressLocality'] ) ? $address['addressLocality'] : '' );
			$country            = isset( $address['addressCountry'] ) ? $address['addressCountry'] : '';
			if ( is_array( $country ) ) {
				$country = isset( $country['name'] ) ? $country['name'] : '';
			}
			$country        = strtoupper( self::text( $country ) );
			$out['country'] = preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '';
		} elseif ( is_scalar( $address ) && '' !== trim( (string) $address ) ) {
			$out['address'] = self::text( $address );
		}
		return $out;
	}

	/**
	 * Organizer from an Organization or Person node.
	 *
	 * @param mixed $value Value.
	 * @return array name, url
	 */
	private static function organizer( $value ) {
		$value = self::first( $value );
		if ( is_scalar( $value ) ) {
			return array( 'name' => self::text( $value ), 'url' => '' );
		}
		if ( ! is_array( $value ) ) {
			return array( 'name' => '', 'url' => '' );
		}
		return array(
			'name' => self::text( isset( $value['name'] ) ? $value['name'] : '' ),
			'url'  => self::url( isset( $value['url'] ) ? self::first( $value['url'] ) : '' ),
		);
	}

	/**
	 * Content of a meta tag by property or name.
	 *
	 * @param string $html The page.
	 * @param string $key  For example og:title.
	 * @return string
	 */
	private static function meta( $html, $key ) {
		$k = preg_quote( $key, '#' );
		if ( preg_match( '#<meta[^>]+(?:property|name)=["\']' . $k . '["\'][^>]*content=["\']([^"\']*)["\']#i', $html, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '#<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']' . $k . '["\']#i', $html, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Labels of the fields, for the summary in the form.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'title'       => __( 'Title', '3task-calendar' ),
			'start'       => __( 'Start', '3task-calendar' ),
			'end'         => __( 'End', '3task-calendar' ),
			'description' => __( 'Description', '3task-calendar' ),
			'location'    => __( 'Location', '3task-calendar' ),
			'organizer'   => __( 'Organizer', '3task-calendar' ),
			'image'       => __( 'Image', '3task-calendar' ),
		);
	}

	/**
	 * AJAX: fetch the entered address and return the fields for the form.
	 */
	public static function ajax() {
		check_ajax_referer( 'threecal_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_threecal_events' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '3task-calendar' ) ) );
		}
		$url = isset( $_POST['url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['url'] ) ), array( 'http', 'https' ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw sanitizes.
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a full address starting with http:// or https://.', '3task-calendar' ) ) );
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 3,
				'limit_response_size' => self::MAX_BYTES,
				'user-agent'          => '3task Calendar/' . THREECAL_VERSION . ' (' . home_url( '/' ) . ')',
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => __( 'The website could not be read.', '3task-calendar' ) . ' ' . $response->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$type = (string) wp_remote_retrieve_header( $response, 'content-type' );
		if ( 200 !== $code ) {
			/* translators: %d: HTTP status code */
			wp_send_json_error( array( 'message' => sprintf( __( 'The website answered with status %d.', '3task-calendar' ), $code ) ) );
		}
		if ( false === stripos( $type, 'html' ) && false === stripos( $type, 'xml' ) ) {
			wp_send_json_error( array( 'message' => __( 'That address is not a web page.', '3task-calendar' ) ) );
		}
		$data = self::parse( (string) wp_remote_retrieve_body( $response ), $url );
		if ( ! $data ) {
			wp_send_json_error( array( 'message' => __( 'Nothing usable found on that page. Please fill in the fields yourself.', '3task-calendar' ) ) );
		}
		$data['location_id'] = 0;
		if ( '' !== $data['location']['name'] ) {
			foreach ( ThreeCal_Location::get_all() as $loc ) {
				if ( 0 === strcasecmp( $loc->name, $data['location']['name'] ) && ( '' === $data['location']['city'] || '' === (string) $loc->city || 0 === strcasecmp( (string) $loc->city, $data['location']['city'] ) ) ) {
					$data['location_id'] = (int) $loc->id;
					break;
				}
			}
		}
		$fields = array();
		foreach ( array( 'title', 'start', 'end', 'description', 'image' ) as $key ) {
			if ( '' !== $data[ $key ] ) {
				$fields[] = $key;
			}
		}
		if ( '' !== $data['location']['name'] ) {
			$fields[] = 'location';
		}
		if ( '' !== $data['organizer']['name'] || '' !== $data['organizer']['url'] ) {
			$fields[] = 'organizer';
		}
		$data['fields'] = $fields;
		$data['labels'] = self::labels();
		wp_send_json_success( $data );
	}
}
