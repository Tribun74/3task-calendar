<?php
/**
 * 3task Calendar Event Model
 *
 * Handles all event-related database operations.
 *
 * @package ThreeCal
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event class.
 */
class ThreeCal_Event {

	/**
	 * Event properties
	 */
	public $id;
	public $title;
	public $description;
	public $start_date;
	public $end_date;
	public $all_day;
	public $location_id;
	public $url;
	public $featured_image;
	public $color;
	public $status;
	public $recurrence_rule;
	public $recurrence_end;
	public $parent_id;
	public $settings;
	public $created_by;
	public $created_at;
	public $updated_at;

	/**
	 * Table name
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'threecal_events';
	}

	/**
	 * Constructor
	 *
	 * @param object|null $data Event data.
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
		$this->id              = isset( $data->id ) ? absint( $data->id ) : 0;
		$this->title           = isset( $data->title ) ? $data->title : '';
		$this->description     = isset( $data->description ) ? $data->description : '';
		$this->start_date      = isset( $data->start_date ) ? $data->start_date : '';
		$this->end_date        = isset( $data->end_date ) ? $data->end_date : '';
		$this->all_day         = isset( $data->all_day ) ? (bool) $data->all_day : false;
		$this->location_id     = isset( $data->location_id ) ? absint( $data->location_id ) : 0;
		$this->url             = isset( $data->url ) ? $data->url : '';
		$this->featured_image  = isset( $data->featured_image ) ? absint( $data->featured_image ) : 0;
		$this->color           = isset( $data->color ) ? $data->color : '#3788d8';
		$this->status          = isset( $data->status ) ? $data->status : 'draft';
		$this->recurrence_rule = isset( $data->recurrence_rule ) ? $data->recurrence_rule : '';
		$this->recurrence_end  = isset( $data->recurrence_end ) ? $data->recurrence_end : '';
		$this->parent_id       = isset( $data->parent_id ) ? absint( $data->parent_id ) : 0;
		$this->settings        = isset( $data->settings ) ? json_decode( $data->settings, true ) : array();
		$this->created_by      = isset( $data->created_by ) ? absint( $data->created_by ) : 0;
		$this->created_at      = isset( $data->created_at ) ? $data->created_at : '';
		$this->updated_at      = isset( $data->updated_at ) ? $data->updated_at : '';
	}

	/**
	 * Get event by ID
	 *
	 * @param int $id Event ID.
	 * @return ThreeCal_Event|null
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
	 * Get all events with filters
	 *
	 * @param array $args Query arguments.
	 * @return ThreeCal_Event[]
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'       => '',
			'category_id'  => 0,
			'location_id'  => 0,
			'start_after'  => '',
			'start_before' => '',
			'search'       => '',
			'orderby'      => 'start_date',
			'order'        => 'ASC',
			'per_page'     => 0,
			'page'         => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		// Status filter.
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'e.status = %s';
			$values[] = $args['status'];
		}

		// Category filter.
		if ( ! empty( $args['category_id'] ) ) {
			$where[]  = 'e.id IN (SELECT event_id FROM ' . $wpdb->prefix . 'threecal_event_categories WHERE category_id = %d)';
			$values[] = $args['category_id'];
		}

		// Location filter.
		if ( ! empty( $args['location_id'] ) ) {
			$where[]  = 'e.location_id = %d';
			$values[] = $args['location_id'];
		}

		// Date filters.
		if ( ! empty( $args['start_after'] ) ) {
			$where[]  = 'e.start_date >= %s';
			$values[] = $args['start_after'];
		}

		if ( ! empty( $args['start_before'] ) ) {
			$where[]  = 'e.start_date <= %s';
			$values[] = $args['start_before'];
		}

		// Search.
		if ( ! empty( $args['search'] ) ) {
			$where[]     = '(e.title LIKE %s OR e.description LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[]    = $search_term;
			$values[]    = $search_term;
		}

		// Build query - table name and orderby are internally generated/validated.
		$sql = 'SELECT e.* FROM ' . self::table() . ' e WHERE ' . implode( ' AND ', $where );

		// Order - orderby is validated against allowed values.
		$allowed_orderby = array( 'start_date', 'title', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'start_date';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';
		$sql            .= " ORDER BY e.$orderby $order";

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
		$rows   = $wpdb->get_results( $sql );
		$events = array();

		foreach ( $rows as $row ) {
			$events[] = new self( $row );
		}

		return $events;
	}

	/**
	 * Get upcoming events
	 *
	 * @param int $limit       Number of events.
	 * @param int $category_id Category ID filter.
	 * @return ThreeCal_Event[]
	 */
	public static function get_upcoming( $limit = 5, $category_id = 0 ) {
		return self::get_all(
			array(
				'status'      => 'published',
				'start_after' => current_time( 'mysql' ),
				'category_id' => $category_id,
				'orderby'     => 'start_date',
				'order'       => 'ASC',
				'per_page'    => $limit,
			)
		);
	}

	/**
	 * Get events count
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		$sql = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built with prepared placeholders.
			$sql = $wpdb->prepare( $sql, $values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is properly prepared above.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Save event (insert or update)
	 *
	 * @return bool
	 */
	public function save() {
		global $wpdb;

		$data = array(
			'title'           => $this->title,
			'description'     => $this->description,
			'start_date'      => $this->start_date,
			'end_date'        => $this->end_date ? $this->end_date : null,
			'all_day'         => $this->all_day ? 1 : 0,
			'location_id'     => $this->location_id ? $this->location_id : null,
			'url'             => $this->url,
			'featured_image'  => $this->featured_image ? $this->featured_image : null,
			'color'           => $this->color,
			'status'          => $this->status,
			'recurrence_rule' => $this->recurrence_rule ? $this->recurrence_rule : null,
			'recurrence_end'  => $this->recurrence_end ? $this->recurrence_end : null,
			'parent_id'       => $this->parent_id ? $this->parent_id : null,
			'settings'        => is_array( $this->settings ) ? wp_json_encode( $this->settings ) : $this->settings,
		);

		$format = array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s' );

		if ( $this->id > 0 ) {
			// Update.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for events.
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
			$data['created_by'] = get_current_user_id();
			$format[]           = '%d';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for events.
			$result = $wpdb->insert( self::table(), $data, $format );

			if ( $result ) {
				$this->id = $wpdb->insert_id;
				return true;
			}

			return false;
		}
	}

	/**
	 * Delete event
	 *
	 * @return bool
	 */
	public function delete() {
		global $wpdb;

		if ( ! $this->id ) {
			return false;
		}

		// Delete category relationships.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for event-category relations.
		$wpdb->delete(
			$wpdb->prefix . 'threecal_event_categories',
			array( 'event_id' => $this->id ),
			array( '%d' )
		);

		// Delete child events (recurring).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for events.
		$wpdb->delete(
			self::table(),
			array( 'parent_id' => $this->id ),
			array( '%d' )
		);

		// Delete event.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for events.
		return $wpdb->delete(
			self::table(),
			array( 'id' => $this->id ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Get categories for this event
	 *
	 * @param int $event_id Event ID.
	 * @return array
	 */
	public static function get_categories( $event_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table prefix used, query prepared.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.* FROM " . $wpdb->prefix . "threecal_categories c
				INNER JOIN " . $wpdb->prefix . "threecal_event_categories ec ON c.id = ec.category_id
				WHERE ec.event_id = %d
				ORDER BY c.name",
				$event_id
			)
		);
	}

	/**
	 * Set categories for this event
	 *
	 * @param array $category_ids Category IDs.
	 * @return bool
	 */
	public function set_categories( $category_ids ) {
		global $wpdb;

		if ( ! $this->id ) {
			return false;
		}

		// Clear existing.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for event-category relations.
		$wpdb->delete(
			$wpdb->prefix . 'threecal_event_categories',
			array( 'event_id' => $this->id ),
			array( '%d' )
		);

		// Add new.
		if ( ! empty( $category_ids ) ) {
			foreach ( (array) $category_ids as $cat_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for event-category relations.
				$wpdb->insert(
					$wpdb->prefix . 'threecal_event_categories',
					array(
						'event_id'    => $this->id,
						'category_id' => absint( $cat_id ),
					),
					array( '%d', '%d' )
				);
			}
		}

		return true;
	}

	/**
	 * Get setting
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		if ( ! is_array( $this->settings ) ) {
			return $default;
		}

		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Set setting
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 */
	public function set_setting( $key, $value ) {
		if ( ! is_array( $this->settings ) ) {
			$this->settings = array();
		}

		$this->settings[ $key ] = $value;
	}

	/**
	 * Duplicate event
	 *
	 * @return ThreeCal_Event|null
	 */
	public function duplicate() {
		$new_event                 = new self();
		$new_event->title          = $this->title . ' ' . __( '(Copy)', '3task-calendar' );
		$new_event->description    = $this->description;
		$new_event->start_date     = $this->start_date;
		$new_event->end_date       = $this->end_date;
		$new_event->all_day        = $this->all_day;
		$new_event->location_id    = $this->location_id;
		$new_event->url            = $this->url;
		$new_event->featured_image = $this->featured_image;
		$new_event->color          = $this->color;
		$new_event->status         = 'draft';
		$new_event->settings       = $this->settings;

		if ( $new_event->save() ) {
			// Copy categories.
			$categories = self::get_categories( $this->id );
			if ( ! empty( $categories ) ) {
				$cat_ids = array_map(
					function ( $c ) {
						return $c->id;
					},
					$categories
				);
				$new_event->set_categories( $cat_ids );
			}

			return $new_event;
		}

		return null;
	}
}
