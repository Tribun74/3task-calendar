<?php
/**
 * 3task Calendar Location Model
 *
 * Handles all location-related database operations.
 *
 * @package ThreeCal
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Location class.
 */
class ThreeCal_Location {

	/**
	 * Location properties
	 */
	public $id;
	public $name;
	public $address;
	public $city;
	public $postal_code;
	public $country;
	public $latitude;
	public $longitude;
	public $phone;
	public $email;
	public $website;
	public $description;
	public $featured_image;
	public $created_at;
	public $updated_at;

	/**
	 * Table name
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'threecal_locations';
	}

	/**
	 * Constructor
	 *
	 * @param object|null $data Location data.
	 */
	public function __construct( $data = null ) {
		if ( $data ) {
			$this->populate( $data );
		}
	}

	/**
	 * Populate from database row
	 *
	 * @param object $data Database row.
	 */
	private function populate( $data ) {
		$this->id             = isset( $data->id ) ? absint( $data->id ) : 0;
		$this->name           = isset( $data->name ) ? $data->name : '';
		$this->address        = isset( $data->address ) ? $data->address : '';
		$this->city           = isset( $data->city ) ? $data->city : '';
		$this->postal_code    = isset( $data->postal_code ) ? $data->postal_code : '';
		$this->country        = isset( $data->country ) ? $data->country : 'DE';
		$this->latitude       = isset( $data->latitude ) ? floatval( $data->latitude ) : null;
		$this->longitude      = isset( $data->longitude ) ? floatval( $data->longitude ) : null;
		$this->phone          = isset( $data->phone ) ? $data->phone : '';
		$this->email          = isset( $data->email ) ? $data->email : '';
		$this->website        = isset( $data->website ) ? $data->website : '';
		$this->description    = isset( $data->description ) ? $data->description : '';
		$this->featured_image = isset( $data->featured_image ) ? absint( $data->featured_image ) : 0;
		$this->created_at     = isset( $data->created_at ) ? $data->created_at : '';
		$this->updated_at     = isset( $data->updated_at ) ? $data->updated_at : '';
	}

	/**
	 * Get location by ID
	 *
	 * @param int $id Location ID.
	 * @return ThreeCal_Location|null
	 */
	public static function get( $id ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE id = %d',
				$id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! $row ) {
			return null;
		}

		return new self( $row );
	}

	/**
	 * Get all locations
	 *
	 * @param array $args Query arguments.
	 * @return ThreeCal_Location[]
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'search'   => '',
			'city'     => '',
			'country'  => '',
			'orderby'  => 'name',
			'order'    => 'ASC',
			'per_page' => 0,
			'page'     => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		// Search.
		if ( ! empty( $args['search'] ) ) {
			$where[]     = '(name LIKE %s OR address LIKE %s OR city LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[]    = $search_term;
			$values[]    = $search_term;
			$values[]    = $search_term;
		}

		// City filter.
		if ( ! empty( $args['city'] ) ) {
			$where[]  = 'city = %s';
			$values[] = $args['city'];
		}

		// Country filter.
		if ( ! empty( $args['country'] ) ) {
			$where[]  = 'country = %s';
			$values[] = $args['country'];
		}

		// Build query - table name and orderby are internally generated/validated.
		$sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where );

		// Order - orderby is validated against allowed values.
		$allowed_orderby = array( 'name', 'city', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'name';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';
		$sql            .= " ORDER BY $orderby $order";

		// Pagination.
		if ( $args['per_page'] > 0 ) {
			$offset = ( $args['page'] - 1 ) * $args['per_page'];
			$sql   .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['per_page'], $offset );
		}

		// Execute.
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built with prepared placeholders and validated orderby.
			$sql = $wpdb->prepare( $sql, $values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is properly prepared above with validated orderby.
		$rows      = $wpdb->get_results( $sql );
		$locations = array();

		foreach ( $rows as $row ) {
			$locations[] = new self( $row );
		}

		return $locations;
	}

	/**
	 * Get locations count
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally.
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
	}

	/**
	 * Save location (insert or update)
	 *
	 * @return bool
	 */
	public function save() {
		global $wpdb;

		$data = array(
			'name'           => $this->name,
			'address'        => $this->address,
			'city'           => $this->city,
			'postal_code'    => $this->postal_code,
			'country'        => $this->country,
			'latitude'       => $this->latitude,
			'longitude'      => $this->longitude,
			'phone'          => $this->phone,
			'email'          => $this->email,
			'website'        => $this->website,
			'description'    => $this->description,
			'featured_image' => $this->featured_image ? $this->featured_image : null,
		);

		$format = array( '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%d' );

		if ( $this->id > 0 ) {
			// Update.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for locations.
			$result = $wpdb->update(
				self::table(),
				$data,
				array( 'id' => $this->id ),
				$format,
				array( '%d' )
			);

			return $result !== false;
		} else {
			// Insert.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for locations.
			$result = $wpdb->insert( self::table(), $data, $format );

			if ( $result ) {
				$this->id = $wpdb->insert_id;
				return true;
			}

			return false;
		}
	}

	/**
	 * Delete location
	 *
	 * @return bool
	 */
	public function delete() {
		global $wpdb;

		if ( ! $this->id ) {
			return false;
		}

		// Set location_id to NULL for related events.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for events.
		$wpdb->update(
			$wpdb->prefix . 'threecal_events',
			array( 'location_id' => null ),
			array( 'location_id' => $this->id ),
			array( '%d' ),
			array( '%d' )
		);

		// Delete location.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for locations.
		return $wpdb->delete(
			self::table(),
			array( 'id' => $this->id ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Get events at this location
	 *
	 * @param array $args Query arguments.
	 * @return ThreeCal_Event[]
	 */
	public function get_events( $args = array() ) {
		$args['location_id'] = $this->id;
		return ThreeCal_Event::get_all( $args );
	}

	/**
	 * Get full address string
	 *
	 * @return string
	 */
	public function get_full_address() {
		$parts = array_filter(
			array(
				$this->address,
				$this->postal_code . ' ' . $this->city,
				$this->get_country_name(),
			)
		);

		return implode( ', ', $parts );
	}

	/**
	 * Get country name from code
	 *
	 * @return string
	 */
	public function get_country_name() {
		$countries = self::get_countries();
		return isset( $countries[ $this->country ] ) ? $countries[ $this->country ] : $this->country;
	}

	/**
	 * Get list of countries
	 *
	 * @return array
	 */
	public static function get_countries() {
		return array(
			'DE' => __( 'Germany', '3task-calendar' ),
			'AT' => __( 'Austria', '3task-calendar' ),
			'CH' => __( 'Switzerland', '3task-calendar' ),
			'BE' => __( 'Belgium', '3task-calendar' ),
			'NL' => __( 'Netherlands', '3task-calendar' ),
			'LU' => __( 'Luxembourg', '3task-calendar' ),
			'FR' => __( 'France', '3task-calendar' ),
			'IT' => __( 'Italy', '3task-calendar' ),
			'ES' => __( 'Spain', '3task-calendar' ),
			'PT' => __( 'Portugal', '3task-calendar' ),
			'GB' => __( 'United Kingdom', '3task-calendar' ),
			'IE' => __( 'Ireland', '3task-calendar' ),
			'DK' => __( 'Denmark', '3task-calendar' ),
			'SE' => __( 'Sweden', '3task-calendar' ),
			'NO' => __( 'Norway', '3task-calendar' ),
			'FI' => __( 'Finland', '3task-calendar' ),
			'PL' => __( 'Poland', '3task-calendar' ),
			'CZ' => __( 'Czech Republic', '3task-calendar' ),
			'SK' => __( 'Slovakia', '3task-calendar' ),
			'HU' => __( 'Hungary', '3task-calendar' ),
			'US' => __( 'United States', '3task-calendar' ),
			'CA' => __( 'Canada', '3task-calendar' ),
		);
	}

	/**
	 * Geocode address using Google Maps API
	 *
	 * @return bool
	 */
	public function geocode() {
		$settings = get_option( 'threecal_settings', array() );
		$api_key  = isset( $settings['google_maps_api_key'] ) ? $settings['google_maps_api_key'] : '';

		if ( empty( $api_key ) ) {
			return false;
		}

		$address = $this->get_full_address();

		if ( empty( $address ) ) {
			return false;
		}

		$url = add_query_arg(
			array(
				'address' => urlencode( $address ),
				'key'     => $api_key,
			),
			'https://maps.googleapis.com/maps/api/geocode/json'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['status'] ) && $data['status'] === 'OK' && ! empty( $data['results'][0] ) ) {
			$location        = $data['results'][0]['geometry']['location'];
			$this->latitude  = $location['lat'];
			$this->longitude = $location['lng'];
			return true;
		}

		return false;
	}
}
