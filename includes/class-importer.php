<?php
/**
 * 3task Calendar CSV import
 *
 * Reads events from a CSV file. The admin page and the WP-CLI command use
 * the same steps: read the file, check every row (nothing is written), then
 * run the import. A row with an import_key updates the event that has this
 * key, so the same file can be imported again without duplicates.
 *
 * @package ThreeCal
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV importer.
 */
class ThreeCal_Importer {

	/**
	 * Largest file that is read (2 MB).
	 */
	const MAX_BYTES = 2097152;

	/**
	 * Most rows per file.
	 */
	const MAX_ROWS = 2000;

	/**
	 * Color of categories created by the import.
	 */
	const DEFAULT_COLOR = '#3788d8';

	/**
	 * Whether missing categories may be created.
	 *
	 * @var bool
	 */
	private $create_categories = true;

	/**
	 * Whether missing locations may be created.
	 *
	 * @var bool
	 */
	private $create_locations = true;

	/**
	 * Categories by lower-case name (loaded on first use).
	 *
	 * @var array|null
	 */
	private $categories = null;

	/**
	 * Locations by lower-case "name|city" (loaded on first use).
	 *
	 * @var array|null
	 */
	private $locations = null;

	/**
	 * Constructor.
	 *
	 * @param array $args create_categories and create_locations (both true by default).
	 */
	public function __construct( $args = array() ) {
		$args                    = wp_parse_args(
			$args,
			array(
				'create_categories' => true,
				'create_locations'  => true,
			)
		);
		$this->create_categories = (bool) $args['create_categories'];
		$this->create_locations  = (bool) $args['create_locations'];
	}

	/**
	 * Columns the import knows, in the order of the sample file.
	 *
	 * @return string[]
	 */
	public static function columns() {
		return array( 'import_key', 'title', 'start', 'end', 'all_day', 'url', 'description', 'category', 'location_name', 'address', 'postal_code', 'city', 'country', 'status', 'organizer_name', 'organizer_url' );
	}

	/* ---------------------------------------------------------------------
	 * Reading
	 * ------------------------------------------------------------------ */

	/**
	 * Read a CSV file from disk.
	 *
	 * @param string $path File path.
	 * @return array|WP_Error See read_string().
	 */
	public static function read_file( $path ) {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'threecal_import_unreadable', __( 'The file could not be read.', '3task-calendar' ) );
		}
		$size = filesize( $path );
		if ( false === $size || $size > self::MAX_BYTES ) {
			/* translators: %s: maximum file size, for example "2 MB" */
			return new WP_Error( 'threecal_import_size', sprintf( __( 'The file is larger than %s.', '3task-calendar' ), size_format( self::MAX_BYTES ) ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file (upload or WP-CLI argument), no remote URL.
		$content = file_get_contents( $path );
		if ( false === $content ) {
			return new WP_Error( 'threecal_import_unreadable', __( 'The file could not be read.', '3task-calendar' ) );
		}
		return self::read_string( $content );
	}

	/**
	 * Split CSV text into rows.
	 *
	 * UTF-8 with or without byte order mark. The separator (; or ,) is taken
	 * from the header line. Column names are not case-sensitive.
	 *
	 * @param string $content CSV text.
	 * @return array|WP_Error Array with header, rows (row number => cells by column), unknown columns and delimiter.
	 */
	public static function read_string( $content ) {
		$content = (string) $content;
		if ( 0 === strpos( $content, "\xEF\xBB\xBF" ) ) {
			$content = substr( $content, 3 );
		}
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'threecal_import_empty', __( 'The file is empty.', '3task-calendar' ) );
		}
		if ( ! preg_match( '//u', $content ) ) {
			return new WP_Error( 'threecal_import_encoding', __( 'The file is not UTF-8 encoded. Save it as "CSV UTF-8" and try again.', '3task-calendar' ) );
		}

		// Separator: whichever appears more often in the header line.
		$first_line = strtok( $content, "\n" );
		$delimiter  = substr_count( (string) $first_line, ';' ) >= substr_count( (string) $first_line, ',' ) ? ';' : ',';

		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream for fgetcsv().
		fwrite( $handle, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- In-memory stream.
		rewind( $handle );

		$header = fgetcsv( $handle, 0, $delimiter, '"', '' );
		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
			return new WP_Error( 'threecal_import_header', __( 'The first line must contain the column names.', '3task-calendar' ) );
		}
		$header = array_map(
			function ( $name ) {
				return strtolower( trim( (string) $name ) );
			},
			$header
		);

		$missing = array_diff( array( 'title', 'start' ), $header );
		if ( $missing ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
			/* translators: %s: list of column names */
			return new WP_Error( 'threecal_import_columns', sprintf( __( 'Required columns are missing: %s', '3task-calendar' ), implode( ', ', $missing ) ) );
		}

		$rows   = array();
		$number = 1;
		while ( false !== ( $cells = fgetcsv( $handle, 0, $delimiter, '"', '' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Standard fgetcsv() loop.
			$number++;
			if ( ! is_array( $cells ) || '' === trim( implode( '', array_map( 'strval', $cells ) ) ) ) {
				continue;
			}
			if ( count( $rows ) >= self::MAX_ROWS ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
				/* translators: %s: maximum number of rows */
				return new WP_Error( 'threecal_import_rows', sprintf( __( 'The file has more than %s rows. Please split it.', '3task-calendar' ), number_format_i18n( self::MAX_ROWS ) ) );
			}
			$row = array();
			foreach ( $header as $index => $name ) {
				if ( '' !== $name ) {
					$row[ $name ] = isset( $cells[ $index ] ) ? trim( (string) $cells[ $index ] ) : '';
				}
			}
			$rows[ $number ] = $row;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.

		return array(
			'header'    => array_values( array_filter( $header ) ),
			'unknown'   => array_values( array_diff( array_filter( $header ), self::columns() ) ),
			'rows'      => $rows,
			'delimiter' => $delimiter,
		);
	}

	/* ---------------------------------------------------------------------
	 * Checking (writes nothing)
	 * ------------------------------------------------------------------ */

	/**
	 * Check every row and decide what the import would do.
	 *
	 * @param array  $parsed         Result of read_string().
	 * @param string $default_status Status of new events: draft or publish.
	 * @return array Plan with rows, counts, columns and the default status.
	 */
	public function analyze( $parsed, $default_status = 'draft' ) {
		$default_status = 'publish' === $default_status ? 'published' : 'draft';
		$columns        = $parsed['header'];
		$plan           = array(
			'columns'        => $columns,
			'unknown'        => isset( $parsed['unknown'] ) ? $parsed['unknown'] : array(),
			'default_status' => $default_status,
			'rows'           => array(),
			'counts'         => array(
				'new'    => 0,
				'update' => 0,
				'error'  => 0,
			),
			'new_categories' => array(),
			'new_locations'  => array(),
		);
		$seen_keys      = array();

		foreach ( $parsed['rows'] as $number => $cells ) {
			$data = $this->normalize_row( $cells );
			$item = array(
				'row'      => (int) $number,
				'title'    => isset( $cells['title'] ) ? sanitize_text_field( $cells['title'] ) : '',
				'action'   => 'error',
				'message'  => '',
				'event_id' => 0,
				'data'     => null,
			);

			if ( is_wp_error( $data ) ) {
				$item['message'] = $data->get_error_message();
			} elseif ( '' !== $data['import_key'] && isset( $seen_keys[ $data['import_key'] ] ) ) {
				/* translators: %s: row number */
				$item['message'] = sprintf( __( 'The import_key was already used in row %s.', '3task-calendar' ), $seen_keys[ $data['import_key'] ] );
			} else {
				$problem = $this->check_relations( $data, $plan );
				if ( $problem ) {
					$item['message'] = $problem;
				} else {
					$existing = '' !== $data['import_key'] ? ThreeCal_Event::find_by_import_key( $data['import_key'] ) : null;
					if ( $existing && ThreeCal_Post_Dates::post_of( $existing ) ) {
						$item['message'] = __( 'This import_key belongs to a date from a post. Edit it in the post.', '3task-calendar' );
					} else {
						$item['action']   = $existing ? 'update' : 'new';
						$item['event_id'] = $existing ? (int) $existing->id : 0;
						$item['data']     = $data;
						if ( '' === $data['import_key'] ) {
							$item['message'] = __( 'Without import_key the event is created again on every import.', '3task-calendar' );
						}
					}
				}
				if ( '' !== $data['import_key'] ) {
					$seen_keys[ $data['import_key'] ] = (int) $number;
				}
			}

			$plan['counts'][ $item['action'] ]++;
			$plan['rows'][] = $item;
		}

		$plan['new_categories'] = array_values( array_unique( $plan['new_categories'] ) );
		$plan['new_locations']  = array_values( array_unique( $plan['new_locations'] ) );

		return $plan;
	}

	/**
	 * Check and clean one row.
	 *
	 * @param array $cells Cells by column name.
	 * @return array|WP_Error Clean values.
	 */
	public function normalize_row( $cells ) {
		$get = function ( $key ) use ( $cells ) {
			return isset( $cells[ $key ] ) ? trim( (string) $cells[ $key ] ) : '';
		};

		$title = sanitize_text_field( $get( 'title' ) );
		if ( '' === $title ) {
			return new WP_Error( 'title', __( 'The title is missing.', '3task-calendar' ) );
		}

		$key = sanitize_text_field( $get( 'import_key' ) );
		if ( strlen( $key ) > 191 ) {
			return new WP_Error( 'import_key', __( 'The import_key is longer than 191 characters.', '3task-calendar' ) );
		}

		// all_day: 1/0, empty = from the start value.
		$all_day_raw = strtolower( $get( 'all_day' ) );
		$all_day     = null;
		if ( '' !== $all_day_raw ) {
			if ( in_array( $all_day_raw, array( '1', 'yes', 'true', 'ja', 'x' ), true ) ) {
				$all_day = true;
			} elseif ( in_array( $all_day_raw, array( '0', 'no', 'false', 'nein' ), true ) ) {
				$all_day = false;
			} else {
				return new WP_Error( 'all_day', __( 'all_day must be 1 or 0.', '3task-calendar' ) );
			}
		}

		// Start: a day, a day with time, or only the month.
		$start_raw = $get( 'start' );
		$precision = 'day';
		$start     = '';
		$has_time  = false;
		if ( preg_match( '/^\d{4}-\d{1,2}$/', $start_raw ) ) {
			$start     = ThreeCal_Event::parse_month( $start_raw );
			$precision = 'month';
		} else {
			$parsed = self::parse_datetime( $start_raw );
			if ( $parsed ) {
				list( $start, $has_time ) = $parsed;
			}
		}
		if ( '' === $start ) {
			return new WP_Error( 'start', __( 'start must be YYYY-MM-DD, YYYY-MM-DD HH:MM or YYYY-MM (only the month).', '3task-calendar' ) );
		}

		$end_raw = $get( 'end' );
		$end     = '';
		if ( 'month' === $precision ) {
			if ( '' !== $end_raw ) {
				return new WP_Error( 'end', __( 'An event with only the month (YYYY-MM) cannot have an end.', '3task-calendar' ) );
			}
			if ( false === $all_day ) {
				return new WP_Error( 'all_day', __( 'An event with only the month (YYYY-MM) is always all day.', '3task-calendar' ) );
			}
			$all_day = true;
		} else {
			if ( null === $all_day ) {
				$all_day = ! $has_time;
			}
			if ( '' !== $end_raw ) {
				$parsed_end = self::parse_datetime( $end_raw );
				if ( ! $parsed_end ) {
					return new WP_Error( 'end', __( 'end must be YYYY-MM-DD or YYYY-MM-DD HH:MM.', '3task-calendar' ) );
				}
				$end = $parsed_end[0];
				if ( $end < $start ) {
					return new WP_Error( 'end', __( 'The end is before the start.', '3task-calendar' ) );
				}
			}
		}

		$url = $get( 'url' );
		if ( '' !== $url ) {
			$url = esc_url_raw( $url, array( 'http', 'https' ) );
			if ( '' === $url ) {
				return new WP_Error( 'url', __( 'url must start with http:// or https://.', '3task-calendar' ) );
			}
		}

		$organizer_url = $get( 'organizer_url' );
		if ( '' !== $organizer_url ) {
			$organizer_url = esc_url_raw( $organizer_url, array( 'http', 'https' ) );
			if ( '' === $organizer_url ) {
				return new WP_Error( 'organizer_url', __( 'organizer_url must start with http:// or https://.', '3task-calendar' ) );
			}
		}

		$status_raw = strtolower( $get( 'status' ) );
		$status     = '';
		if ( '' !== $status_raw ) {
			$map = array(
				'draft'          => 'draft',
				'entwurf'        => 'draft',
				'publish'        => 'published',
				'published'      => 'published',
				'veröffentlicht' => 'published',
				'cancelled'      => 'cancelled',
				'canceled'       => 'cancelled',
				'abgesagt'       => 'cancelled',
			);
			if ( ! isset( $map[ $status_raw ] ) ) {
				return new WP_Error( 'status', __( 'status must be draft, publish or cancelled.', '3task-calendar' ) );
			}
			$status = $map[ $status_raw ];
		}

		$categories = array();
		foreach ( explode( '|', $get( 'category' ) ) as $name ) {
			$name = sanitize_text_field( $name );
			if ( '' !== $name ) {
				$categories[ self::lower( $name ) ] = $name;
			}
		}

		$country = strtoupper( sanitize_text_field( $get( 'country' ) ) );
		if ( '' !== $country && ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
			return new WP_Error( 'country', __( 'country must be a two-letter code such as DE, AT or CH.', '3task-calendar' ) );
		}

		// Lengths of the database columns.
		$limits = array(
			'title'          => 255,
			'url'            => 500,
			'location_name'  => 255,
			'organizer_name' => 255,
			'address'        => 500,
			'postal_code'    => 20,
			'city'           => 100,
		);
		foreach ( $limits as $column => $limit ) {
			$value = 'title' === $column ? $title : ( 'url' === $column ? $url : sanitize_text_field( $get( $column ) ) );
			if ( self::length( $value ) > $limit ) {
				/* translators: 1: column name, 2: number of characters */
				return new WP_Error( $column, sprintf( __( '%1$s is longer than %2$s characters.', '3task-calendar' ), $column, number_format_i18n( $limit ) ) );
			}
		}
		foreach ( $categories as $name ) {
			if ( self::length( $name ) > 100 ) {
				/* translators: 1: column name, 2: number of characters */
				return new WP_Error( 'category', sprintf( __( '%1$s is longer than %2$s characters.', '3task-calendar' ), 'category', number_format_i18n( 100 ) ) );
			}
		}

		return array(
			'import_key'     => $key,
			'title'          => $title,
			'precision'      => $precision,
			'start'          => $start,
			'end'            => $end,
			'all_day'        => (bool) $all_day,
			'url'            => $url,
			'description'    => wp_kses_post( $get( 'description' ) ),
			'categories'     => array_values( $categories ),
			'location_name'  => sanitize_text_field( $get( 'location_name' ) ),
			'address'        => sanitize_text_field( $get( 'address' ) ),
			'postal_code'    => sanitize_text_field( $get( 'postal_code' ) ),
			'city'           => sanitize_text_field( $get( 'city' ) ),
			'country'        => $country,
			'status'         => $status,
			'organizer_name' => sanitize_text_field( $get( 'organizer_name' ) ),
			'organizer_url'  => $organizer_url,
		);
	}

	/**
	 * Read YYYY-MM-DD, YYYY-MM-DD HH:MM or YYYY-MM-DD HH:MM:SS.
	 *
	 * @param string $value Value.
	 * @return array|null Array( 'Y-m-d H:i:s', has time ) or null.
	 */
	private static function parse_datetime( $value ) {
		$value = trim( str_replace( 'T', ' ', (string) $value ) );
		foreach ( array( 'Y-m-d H:i:s' => true, 'Y-m-d H:i' => true, 'Y-m-d' => false ) as $format => $has_time ) {
			$date = DateTime::createFromFormat( '!' . $format, $value );
			if ( $date && $date->format( $format ) === $value ) {
				$year = (int) $date->format( 'Y' );
				if ( $year < 1900 || $year > 2100 ) {
					return null;
				}
				return array( $date->format( 'Y-m-d H:i:s' ), $has_time );
			}
		}
		return null;
	}

	/**
	 * Check categories and location of a row, and note what would be created.
	 *
	 * @param array $data Clean row.
	 * @param array $plan Plan, receives the names of new categories and locations.
	 * @return string Error message, empty when the row is fine.
	 */
	private function check_relations( $data, &$plan ) {
		$this->load_relations();

		foreach ( $data['categories'] as $name ) {
			if ( ! isset( $this->categories[ self::lower( $name ) ] ) ) {
				if ( ! $this->create_categories ) {
					/* translators: %s: category name */
					return sprintf( __( 'The category "%s" does not exist and you may not create categories.', '3task-calendar' ), $name );
				}
				$plan['new_categories'][] = $name;
			}
		}

		if ( '' !== $data['location_name'] && ! isset( $this->locations[ self::location_key( $data['location_name'], $data['city'] ) ] ) ) {
			if ( ! $this->create_locations ) {
				/* translators: %s: location name */
				return sprintf( __( 'The location "%s" does not exist and you may not create locations.', '3task-calendar' ), $data['location_name'] );
			}
			$plan['new_locations'][] = '' !== $data['city'] ? $data['location_name'] . ', ' . $data['city'] : $data['location_name'];
		}

		return '';
	}

	/* ---------------------------------------------------------------------
	 * Import
	 * ------------------------------------------------------------------ */

	/**
	 * Create and update the events of a checked plan.
	 *
	 * The import key is looked up again, so a plan that was checked a while
	 * ago still updates the right event.
	 *
	 * @param array $plan Result of analyze().
	 * @return array Counts (created, updated, failed, skipped) and messages.
	 */
	public function run( $plan ) {
		$result = array(
			'created'            => 0,
			'updated'            => 0,
			'failed'             => 0,
			'skipped'            => 0,
			'categories_created' => 0,
			'locations_created'  => 0,
			'messages'           => array(),
		);
		$columns = array_flip( (array) $plan['columns'] );

		foreach ( $plan['rows'] as $item ) {
			if ( 'error' === $item['action'] || empty( $item['data'] ) ) {
				$result['skipped']++;
				continue;
			}
			$data  = $item['data'];
			$event = '' !== $data['import_key'] ? ThreeCal_Event::find_by_import_key( $data['import_key'] ) : null;
			$new   = ! $event;
			if ( $new ) {
				$event           = new ThreeCal_Event();
				$event->color    = self::DEFAULT_COLOR;
				$event->settings = array();
				$event->status   = '' !== $data['status'] ? $data['status'] : $plan['default_status'];
			} elseif ( '' !== $data['status'] ) {
				// An update keeps the status unless the file sets one.
				$event->status = $data['status'];
			}

			$had_series = ! empty( $event->recurrence_rule );

			$event->title      = $data['title'];
			$event->start_date = $data['start'];
			$event->end_date   = $data['end'];
			$event->all_day    = $data['all_day'];

			// Columns that are not in the file keep their values on update.
			if ( $new || isset( $columns['url'] ) ) {
				$event->url = $data['url'];
			}
			if ( $new || isset( $columns['description'] ) ) {
				$event->description = $data['description'];
			}
			if ( $new || isset( $columns['location_name'] ) ) {
				$event->location_id = $this->location_id( $data, $result );
			}
			$organizer = $event->get_organizer();
			$event->set_organizer(
				isset( $columns['organizer_name'] ) ? $data['organizer_name'] : $organizer['name'],
				isset( $columns['organizer_url'] ) ? $data['organizer_url'] : $organizer['url']
			);
			if ( '' !== $data['import_key'] ) {
				$event->set_setting( ThreeCal_Event::IMPORT_KEY, $data['import_key'] );
			}
			$event->set_date_precision( $data['precision'] );

			if ( ! $event->save() ) {
				$result['failed']++;
				/* translators: 1: row number, 2: event title */
				$result['messages'][] = sprintf( __( 'Row %1$s (%2$s) could not be saved.', '3task-calendar' ), $item['row'], $data['title'] );
				continue;
			}

			if ( $new || isset( $columns['category'] ) ) {
				$event->set_categories( $this->category_ids( $data['categories'], $result ) );
			}
			if ( $had_series ) {
				// The repetitions follow the new start, or disappear for a month-only event.
				$event->regenerate_series();
			}

			$result[ $new ? 'created' : 'updated' ]++;
		}

		/**
		 * Fires after a CSV import.
		 *
		 * @param array $result Counts and messages.
		 * @param array $plan   The checked plan.
		 */
		do_action( 'threecal_import_done', $result, $plan );

		return $result;
	}

	/**
	 * Location of a row: an existing one with the same name and city, or a new one.
	 *
	 * @param array $data   Clean row.
	 * @param array $result Result, counts new locations.
	 * @return int Location ID or 0.
	 */
	private function location_id( $data, &$result ) {
		if ( '' === $data['location_name'] ) {
			return 0;
		}
		$this->load_relations();
		$key = self::location_key( $data['location_name'], $data['city'] );
		if ( isset( $this->locations[ $key ] ) ) {
			return (int) $this->locations[ $key ];
		}
		if ( ! $this->create_locations ) {
			return 0;
		}

		$location              = new ThreeCal_Location();
		$location->name        = $data['location_name'];
		$location->address     = $data['address'];
		$location->postal_code = $data['postal_code'];
		$location->city        = $data['city'];
		$location->country     = '' !== $data['country'] ? $data['country'] : 'DE';
		$location->latitude    = null;
		$location->longitude   = null;
		if ( ! $location->save() ) {
			return 0;
		}
		$this->locations[ $key ] = (int) $location->id;
		$result['locations_created']++;
		return (int) $location->id;
	}

	/**
	 * Category IDs for a list of names; missing categories are created.
	 *
	 * @param string[] $names  Category names.
	 * @param array    $result Result, counts new categories.
	 * @return int[]
	 */
	private function category_ids( $names, &$result ) {
		$this->load_relations();
		$ids = array();
		foreach ( (array) $names as $name ) {
			$key = self::lower( $name );
			if ( ! isset( $this->categories[ $key ] ) ) {
				if ( ! $this->create_categories ) {
					continue;
				}
				$category        = new ThreeCal_Category();
				$category->name  = $name;
				$category->color = self::DEFAULT_COLOR;
				if ( ! $category->save() ) {
					continue;
				}
				$this->categories[ $key ] = (int) $category->id;
				$result['categories_created']++;
			}
			$ids[] = (int) $this->categories[ $key ];
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Load the existing categories and locations once.
	 */
	private function load_relations() {
		if ( null === $this->categories ) {
			$this->categories = array();
			foreach ( ThreeCal_Category::get_all() as $category ) {
				$this->categories[ self::lower( $category->name ) ] = (int) $category->id;
			}
		}
		if ( null === $this->locations ) {
			$this->locations = array();
			foreach ( ThreeCal_Location::get_all() as $location ) {
				$key = self::location_key( $location->name, $location->city );
				if ( ! isset( $this->locations[ $key ] ) ) {
					$this->locations[ $key ] = (int) $location->id;
				}
			}
		}
	}

	/**
	 * Key that finds a location again: name and city, not case-sensitive.
	 *
	 * @param string $name Location name.
	 * @param string $city City.
	 * @return string
	 */
	private static function location_key( $name, $city ) {
		return self::lower( trim( (string) $name ) ) . '|' . self::lower( trim( (string) $city ) );
	}

	/**
	 * Number of characters, also for umlauts.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
	}

	/**
	 * Lower case, also for umlauts.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
	}

	/* ---------------------------------------------------------------------
	 * Sample file
	 * ------------------------------------------------------------------ */

	/**
	 * Sample CSV with all columns (UTF-8 with byte order mark, separator ;).
	 *
	 * @return string
	 */
	public static function sample_csv() {
		$rows = array(
			self::columns(),
			array( 'festival-2027-summer', 'Summer Film Festival', '2027-07', '', '', 'https://example.com/festival', 'Programme to follow.', 'Festival|Open air', 'Riverside Park', 'Park Road 1', '56626', 'Andernach', 'DE', '', 'Riverside Film Club', 'https://example.com/film-club' ),
			array( 'premiere-2027-05-14', 'Premiere at the cinema', '2027-05-14 20:00', '2027-05-14 22:30', '0', '', 'With guests from the film.', 'Premiere', 'City Cinema', 'Main Street 5', '56626', 'Andernach', 'DE', 'publish', '', '' ),
			array( 'market-2027-06-05', 'Book market', '2027-06-05', '2027-06-06', '1', '', '', 'Market', 'Market Square', '', '56626', 'Andernach', 'DE', '', '', '' ),
		);

		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream for fputcsv().
		foreach ( $rows as $row ) {
			fputcsv( $handle, $row, ';', '"', '' );
		}
		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.

		return "\xEF\xBB\xBF" . $csv;
	}
}
