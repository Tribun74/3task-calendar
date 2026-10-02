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
	 * Statuses shown to visitors. Cancelled events stay visible and are marked
	 * as cancelled, so nobody turns up for an event that was called off.
	 *
	 * @return string[]
	 */
	public static function visible_statuses() {
		return array( 'published', 'cancelled' );
	}

	/**
	 * Whether visitors may see this event.
	 *
	 * @return bool
	 */
	public function is_visible() {
		return in_array( $this->status, self::visible_statuses(), true );
	}

	/**
	 * Build the WHERE clause shared by get_all() and count().
	 *
	 * Date arguments:
	 * - start_after / start_before: filter on the start date only.
	 * - range_start / range_end: events that overlap the range, so a multi-day
	 *   event that started before the range is still included.
	 * - ends_after: events that have not ended yet (running or upcoming).
	 *
	 * @param array $args Parsed query arguments.
	 * @return array Array with the SQL string and the placeholder values.
	 */
	private static function build_where( $args ) {
		global $wpdb;

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$statuses = array_values( array_filter( array_map( 'sanitize_key', (array) $args['status'] ) ) );
			if ( empty( $statuses ) ) {
				$statuses = array( 'published' );
			}
			$where[]  = 'e.status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
			$values   = array_merge( $values, $statuses );
		}

		if ( ! empty( $args['category_id'] ) ) {
			$where[]  = 'e.id IN (SELECT event_id FROM ' . $wpdb->prefix . 'threecal_event_categories WHERE category_id = %d)';
			$values[] = $args['category_id'];
		}

		if ( ! empty( $args['category_ids'] ) ) {
			$ids = array_values( array_filter( array_map( 'absint', (array) $args['category_ids'] ) ) );
			if ( ! empty( $ids ) ) {
				$where[] = 'e.id IN (SELECT event_id FROM ' . $wpdb->prefix . 'threecal_event_categories WHERE category_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . '))';
				$values  = array_merge( $values, $ids );
			}
		}

		if ( ! empty( $args['location_id'] ) ) {
			$where[]  = 'e.location_id = %d';
			$values[] = $args['location_id'];
		}

		if ( ! empty( $args['start_after'] ) ) {
			$where[]  = 'e.start_date >= %s';
			$values[] = $args['start_after'];
		}

		if ( ! empty( $args['start_before'] ) ) {
			$where[]  = 'e.start_date <= %s';
			$values[] = $args['start_before'];
		}

		if ( ! empty( $args['range_end'] ) ) {
			$where[]  = 'e.start_date <= %s';
			$values[] = $args['range_end'];
		}

		if ( ! empty( $args['range_start'] ) ) {
			$where[]  = 'COALESCE(e.end_date, e.start_date) >= %s';
			$values[] = $args['range_start'];
		}

		if ( ! empty( $args['ends_after'] ) ) {
			$where[]  = 'COALESCE(e.end_date, e.start_date) >= %s';
			$values[] = $args['ends_after'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]     = '(e.title LIKE %s OR e.description LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[]    = $search_term;
			$values[]    = $search_term;
		}

		return array( implode( ' AND ', $where ), $values );
	}

	/**
	 * Default query arguments.
	 *
	 * @return array
	 */
	private static function query_defaults() {
		return array(
			'status'       => '',
			'category_id'  => 0,
			'category_ids' => array(),
			'location_id'  => 0,
			'start_after'  => '',
			'start_before' => '',
			'range_start'  => '',
			'range_end'    => '',
			'ends_after'   => '',
			'search'       => '',
			'orderby'      => 'start_date',
			'order'        => 'ASC',
			'per_page'     => 0,
			'page'         => 1,
		);
	}

	/**
	 * Get all events with filters
	 *
	 * @param array $args Query arguments.
	 * @return ThreeCal_Event[]
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, self::query_defaults() );

		list( $where, $values ) = self::build_where( $args );

		// Build query - table name and orderby are internally generated/validated.
		$sql = 'SELECT e.* FROM ' . self::table() . ' e WHERE ' . $where;

		// Order - orderby is validated against allowed values.
		$allowed_orderby = array( 'start_date', 'title', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'start_date';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';
		$sql            .= " ORDER BY e.$orderby $order, e.id ASC";

		// Pagination.
		$per_page = absint( $args['per_page'] );
		if ( $per_page > 0 ) {
			$page   = max( 1, absint( $args['page'] ) );
			$offset = ( $page - 1 ) * $per_page;
			$sql   .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );
		}

		// Execute.
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built with prepared placeholders and validated orderby.
			$sql = $wpdb->prepare( $sql, $values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is properly prepared above with validated orderby.
		$rows   = $wpdb->get_results( $sql );
		$events = array();

		foreach ( (array) $rows as $row ) {
			$events[] = new self( $row );
		}

		return $events;
	}

	/**
	 * Get upcoming events (including events that are running right now)
	 *
	 * @param int $limit       Number of events.
	 * @param int $category_id Category ID filter.
	 * @return ThreeCal_Event[]
	 */
	public static function get_upcoming( $limit = 5, $category_id = 0 ) {
		return self::get_all(
			array(
				'status'      => self::visible_statuses(),
				'ends_after'  => current_time( 'mysql' ),
				'category_id' => $category_id,
				'orderby'     => 'start_date',
				'order'       => 'ASC',
				'per_page'    => $limit,
			)
		);
	}

	/**
	 * Get events count, using the same filters as get_all()
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, self::query_defaults() );

		list( $where, $values ) = self::build_where( $args );

		$sql = 'SELECT COUNT(*) FROM ' . self::table() . ' e WHERE ' . $where;

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built with prepared placeholders.
			$sql = $wpdb->prepare( $sql, $values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is properly prepared above.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Days of a given month that this event covers.
	 *
	 * @param int $month Month (1-12).
	 * @param int $year  Year.
	 * @return int[] Day numbers within the month.
	 */
	public function get_days_in_month( $month, $year ) {
		$month_start = sprintf( '%04d-%02d-01', $year, $month );
		$month_end   = gmdate( 'Y-m-t', strtotime( $month_start . ' 00:00:00 UTC' ) );

		$start = substr( (string) $this->start_date, 0, 10 );
		$end   = $this->end_date ? substr( (string) $this->end_date, 0, 10 ) : $start;
		if ( $end < $start ) {
			$end = $start;
		}

		$from = max( $start, $month_start );
		$to   = min( $end, $month_end );
		if ( $from > $to ) {
			return array();
		}

		$days = array();
		for ( $d = (int) substr( $from, 8, 2 ); $d <= (int) substr( $to, 8, 2 ); $d++ ) {
			$days[] = $d;
		}

		return $days;
	}

	/**
	 * Load categories for many events with one query.
	 *
	 * @param int[] $event_ids Event IDs.
	 * @return array Map of event ID to array of category rows.
	 */
	public static function get_categories_for_events( $event_ids ) {
		global $wpdb;

		$event_ids = array_values( array_filter( array_map( 'absint', (array) $event_ids ) ) );
		$map       = array_fill_keys( $event_ids, array() );

		if ( empty( $event_ids ) ) {
			return $map;
		}

		$placeholders = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Placeholders are generated for each ID.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ec.event_id, c.* FROM ' . $wpdb->prefix . 'threecal_categories c
				INNER JOIN ' . $wpdb->prefix . 'threecal_event_categories ec ON c.id = ec.category_id
				WHERE ec.event_id IN (' . $placeholders . ')
				ORDER BY c.name',
				$event_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		foreach ( (array) $rows as $row ) {
			$map[ (int) $row->event_id ][] = $row;
		}

		return $map;
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

			if ( false !== $result ) {
				/**
				 * Fires after an event has been saved.
				 *
				 * @param ThreeCal_Event $event  The event.
				 * @param bool           $is_new True when the event was just created.
				 */
				do_action( 'threecal_event_saved', $this, false );
			}

			return $result !== false;
		} else {
			// Insert.
			$data['created_by'] = get_current_user_id();
			$format[]           = '%d';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for events.
			$result = $wpdb->insert( self::table(), $data, $format );

			if ( $result ) {
				$this->id = $wpdb->insert_id;
				/** This action is documented in includes/class-event.php */
				do_action( 'threecal_event_saved', $this, true );
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

		// Delete category relationships of the repetitions, then the repetitions.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom tables, table names are internal.
		$wpdb->query( $wpdb->prepare( 'DELETE ec FROM ' . $wpdb->prefix . 'threecal_event_categories ec INNER JOIN ' . self::table() . ' e ON e.id = ec.event_id WHERE e.parent_id = %d', $this->id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for events.
		$wpdb->delete(
			self::table(),
			array( 'parent_id' => $this->id ),
			array( '%d' )
		);

		// Delete event.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for events.
		$deleted = $wpdb->delete(
			self::table(),
			array( 'id' => $this->id ),
			array( '%d' )
		) !== false;

		if ( $deleted ) {
			/**
			 * Fires after an event and its repetitions have been deleted.
			 *
			 * @param int            $event_id ID of the deleted event.
			 * @param ThreeCal_Event $event    The deleted event (data still available).
			 */
			do_action( 'threecal_event_deleted', (int) $this->id, $this );
		}

		return $deleted;
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
	 * Supported repeat patterns.
	 *
	 * @return array Map of rule key to label.
	 */
	public static function recurrence_options() {
		return array(
			''         => __( 'Does not repeat', '3task-calendar' ),
			'daily'    => __( 'Daily', '3task-calendar' ),
			'weekly'   => __( 'Weekly', '3task-calendar' ),
			'biweekly' => __( 'Every two weeks', '3task-calendar' ),
			'monthly'  => __( 'Monthly (same day of the month)', '3task-calendar' ),
			'yearly'   => __( 'Yearly', '3task-calendar' ),
		);
	}

	/**
	 * Maximum number of generated occurrences per series.
	 */
	const MAX_OCCURRENCES = 200;

	/**
	 * Start dates of all repetitions after the first one.
	 *
	 * Dates are calculated as wall-clock time, so a weekly event at 18:00
	 * stays at 18:00 across daylight saving changes. Monthly events on the
	 * 29th to 31st move to the last day of shorter months.
	 *
	 * @return string[] Start dates (Y-m-d H:i:s), without the first one.
	 */
	public function get_occurrence_starts() {
		$rule = (string) $this->recurrence_rule;
		if ( ! array_key_exists( $rule, self::recurrence_options() ) || '' === $rule || empty( $this->start_date ) ) {
			return array();
		}

		$utc   = new DateTimeZone( 'UTC' );
		$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $this->start_date, $utc );
		if ( ! $start ) {
			return array();
		}

		// Without an end date the series runs for one year; never longer than five years.
		$limit = $start->modify( '+5 years' );
		$until = $this->recurrence_end ? DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', substr( $this->recurrence_end, 0, 10 ) . ' 23:59:59', $utc ) : $start->modify( '+1 year' );
		if ( ! $until || $until > $limit ) {
			$until = $limit;
		}

		$day   = (int) $start->format( 'j' );
		$month = (int) $start->format( 'n' );
		$year  = (int) $start->format( 'Y' );
		$time  = $start->format( 'H:i:s' );

		// Dates that were detached from the series and edited on their own.
		$skip = array_flip( (array) $this->get_setting( 'exdates', array() ) );

		$starts = array();
		for ( $n = 1; $n <= self::MAX_OCCURRENCES; $n++ ) {
			switch ( $rule ) {
				case 'daily':
					$next = $start->modify( '+' . $n . ' days' );
					break;
				case 'weekly':
					$next = $start->modify( '+' . ( 7 * $n ) . ' days' );
					break;
				case 'biweekly':
					$next = $start->modify( '+' . ( 14 * $n ) . ' days' );
					break;
				case 'monthly':
					$m      = $month + $n;
					$y      = $year + intdiv( $m - 1, 12 );
					$m      = ( ( $m - 1 ) % 12 ) + 1;
					$last   = (int) gmdate( 't', gmmktime( 0, 0, 0, $m, 1, $y ) );
					$next   = DateTimeImmutable::createFromFormat( 'Y-n-j H:i:s', $y . '-' . $m . '-' . min( $day, $last ) . ' ' . $time, $utc );
					break;
				case 'yearly':
					$y    = $year + $n;
					$last = (int) gmdate( 't', gmmktime( 0, 0, 0, $month, 1, $y ) );
					$next = DateTimeImmutable::createFromFormat( 'Y-n-j H:i:s', $y . '-' . $month . '-' . min( $day, $last ) . ' ' . $time, $utc );
					break;
				default:
					return $starts;
			}

			if ( ! $next || $next > $until ) {
				break;
			}
			if ( isset( $skip[ $next->format( 'Y-m-d' ) ] ) ) {
				continue;
			}
			$starts[] = $next->format( 'Y-m-d H:i:s' );
		}

		return $starts;
	}

	/**
	 * Rebuild the repetitions of this series.
	 *
	 * Deletes all child events and creates them again from the rule, with the
	 * same duration, details and categories as this event.
	 *
	 * @return int Number of created repetitions.
	 */
	public function regenerate_series() {
		global $wpdb;

		if ( ! $this->id || $this->parent_id ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table name is internal.
		$children = $wpdb->get_col(
			$wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE parent_id = %d', $this->id )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		foreach ( $children as $child_id ) {
			$wpdb->delete( $wpdb->prefix . 'threecal_event_categories', array( 'event_id' => (int) $child_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		}
		$wpdb->delete( self::table(), array( 'parent_id' => $this->id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.

		$starts = $this->get_occurrence_starts();
		if ( empty( $starts ) ) {
			return 0;
		}

		$duration = $this->end_date ? strtotime( $this->end_date . ' UTC' ) - strtotime( $this->start_date . ' UTC' ) : null;
		$cat_ids  = wp_list_pluck( self::get_categories( $this->id ), 'id' );
		$created  = 0;

		// Single dates that were called off stay cancelled when the series is saved again.
		$cancelled = array_flip( (array) $this->get_setting( 'cancelled_dates', array() ) );

		foreach ( $starts as $start ) {
			$child                  = new self();
			$child->title           = $this->title;
			$child->description     = $this->description;
			$child->start_date      = $start;
			$child->end_date        = null !== $duration ? gmdate( 'Y-m-d H:i:s', strtotime( $start . ' UTC' ) + $duration ) : '';
			$child->all_day         = $this->all_day;
			$child->location_id     = $this->location_id;
			$child->url             = $this->url;
			$child->featured_image  = $this->featured_image;
			$child->color           = $this->color;
			$child->status          = ( isset( $cancelled[ substr( $start, 0, 10 ) ] ) && 'published' === $this->status ) ? 'cancelled' : $this->status;
			$child->parent_id       = $this->id;
			$child->settings        = array();

			if ( $child->save() ) {
				$child->set_categories( $cat_ids );
				$created++;
			}
		}

		return $created;
	}

	/**
	 * Repetitions of this series, oldest first.
	 *
	 * @return ThreeCal_Event[]
	 */
	public function get_series_events() {
		global $wpdb;

		if ( ! $this->id || $this->parent_id ) {
			return array();
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table name is internal.
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE parent_id = %d ORDER BY start_date ASC', $this->id )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$events = array();
		foreach ( (array) $rows as $row ) {
			$events[] = new self( $row );
		}
		return $events;
	}

	/**
	 * Dates that were split off from this series (former first dates).
	 *
	 * @return ThreeCal_Event[]
	 */
	public function get_split_events() {
		$events = array();
		foreach ( (array) $this->get_setting( 'split_dates', array() ) as $id ) {
			$event = self::get( absint( $id ) );
			if ( $event && ! $event->parent_id ) {
				$events[] = $event;
			}
		}
		return $events;
	}

	/**
	 * Call off a single date of this series, or take it back.
	 *
	 * The date is remembered in the series, so it stays cancelled when the
	 * series is saved and its repetitions are created again.
	 *
	 * @param string $day       Date (Y-m-d) of a repetition.
	 * @param bool   $cancelled True to cancel, false to restore.
	 * @return bool
	 */
	public function set_date_cancelled( $day, $cancelled ) {
		global $wpdb;

		if ( ! $this->id || $this->parent_id ) {
			return false;
		}

		$days = (array) $this->get_setting( 'cancelled_dates', array() );
		$days = $cancelled ? array_merge( $days, array( $day ) ) : array_diff( $days, array( $day ) );
		$this->set_setting( 'cancelled_dates', array_values( array_unique( $days ) ) );
		$this->save();

		$status = ( $cancelled && 'published' === $this->status ) ? 'cancelled' : $this->status;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table name is internal.
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET status = %s WHERE parent_id = %d AND start_date LIKE %s', $status, $this->id, $wpdb->esc_like( $day ) . '%' ) );

		return true;
	}

	/**
	 * Take a single date out of this series for good.
	 *
	 * @param string $day Date (Y-m-d) of a repetition.
	 * @return bool
	 */
	public function remove_date( $day ) {
		if ( ! $this->id || $this->parent_id ) {
			return false;
		}

		$exdates   = (array) $this->get_setting( 'exdates', array() );
		$exdates[] = $day;
		$this->set_setting( 'exdates', array_values( array_unique( $exdates ) ) );
		$this->save();

		foreach ( $this->get_series_events() as $child ) {
			if ( substr( (string) $child->start_date, 0, 10 ) === $day ) {
				$child->delete();
			}
		}

		return true;
	}

	/**
	 * Hand the series over to its second date, so the first date can be
	 * cancelled or removed on its own.
	 *
	 * The first date is the series itself. Afterwards this event is a single
	 * event, and the next repetition carries the rule and the exceptions.
	 *
	 * @return int ID of the series after the change (0 if there are no more dates).
	 */
	public function split_off_first_date() {
		global $wpdb;

		if ( ! $this->id || $this->parent_id ) {
			return 0;
		}

		$children = $this->get_series_events();
		$next     = $children ? $children[0] : null;

		if ( $next ) {
			$next->parent_id       = 0;
			$next->recurrence_rule = $this->recurrence_rule;
			$next->recurrence_end  = $this->recurrence_end;
			$next->settings        = $this->settings;
			$next->status          = $this->status;

			// Remember the dates split off from the series, so they can still be restored from its list.
			$split   = (array) $this->get_setting( 'split_dates', array() );
			$split[] = (int) $this->id;
			$next->set_setting( 'split_dates', array_values( array_unique( array_map( 'absint', $split ) ) ) );
			$next->save();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table name is internal.
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET parent_id = %d WHERE parent_id = %d', $next->id, $this->id ) );
		}

		$this->recurrence_rule = '';
		$this->recurrence_end  = '';
		$this->set_setting( 'exdates', array() );
		$this->set_setting( 'cancelled_dates', array() );
		$this->set_setting( 'split_dates', array() );
		$this->save();

		return $next ? (int) $next->id : 0;
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
