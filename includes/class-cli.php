<?php
/**
 * 3task Calendar WP-CLI commands
 *
 * Loaded only when WP-CLI runs. Uses the same importer as the admin page.
 *
 * @package ThreeCal
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage 3task Calendar events.
 */
class ThreeCal_CLI {

	/**
	 * Import events from a CSV file.
	 *
	 * Columns (header line, not case-sensitive): import_key, title, start,
	 * end, all_day, url, description, category, location_name, address,
	 * postal_code, city, country, status, organizer_name, organizer_url.
	 * title and start are required.
	 * start is YYYY-MM-DD, YYYY-MM-DD HH:MM or YYYY-MM (only the month known).
	 * A row whose import_key already exists updates that event.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the CSV file (UTF-8, separator ; or ,).
	 *
	 * [--dry-run]
	 * : Check the file and show what would happen, without writing anything.
	 *
	 * [--status=<status>]
	 * : Status of new events. A status column in the file wins.
	 * ---
	 * default: draft
	 * options:
	 *   - draft
	 *   - publish
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp threecal import festivals.csv --dry-run
	 *     wp threecal import festivals.csv --status=publish
	 *
	 * @subcommand import
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function import( $args, $assoc_args ) {
		$file    = isset( $args[0] ) ? (string) $args[0] : '';
		$dry_run = ! empty( $assoc_args['dry-run'] );
		$status  = ( isset( $assoc_args['status'] ) && 'publish' === $assoc_args['status'] ) ? 'publish' : 'draft';

		$parsed = ThreeCal_Importer::read_file( $file );
		if ( is_wp_error( $parsed ) ) {
			WP_CLI::error( $parsed->get_error_message() );
		}
		if ( ! empty( $parsed['unknown'] ) ) {
			/* translators: %s: list of column names */
			WP_CLI::warning( sprintf( __( 'Unknown columns are ignored: %s', '3task-calendar' ), implode( ', ', $parsed['unknown'] ) ) );
		}

		$importer = new ThreeCal_Importer();
		$plan     = $importer->analyze( $parsed, $status );

		$labels = self::action_labels();
		$table  = array();
		foreach ( $plan['rows'] as $item ) {
			$table[] = array(
				'row'     => $item['row'],
				'action'  => $labels[ $item['action'] ],
				'title'   => $item['title'],
				'message' => $item['message'],
			);
		}
		WP_CLI\Utils\format_items( 'table', $table, array( 'row', 'action', 'title', 'message' ) );

		if ( $plan['new_categories'] ) {
			/* translators: %s: list of names */
			WP_CLI::log( sprintf( __( 'New categories: %s', '3task-calendar' ), implode( ', ', $plan['new_categories'] ) ) );
		}
		if ( $plan['new_locations'] ) {
			/* translators: %s: list of names */
			WP_CLI::log( sprintf( __( 'New locations: %s', '3task-calendar' ), implode( ', ', $plan['new_locations'] ) ) );
		}

		if ( $dry_run ) {
			WP_CLI::success(
				sprintf(
					/* translators: 1: new events, 2: updated events, 3: rows with errors */
					__( 'Dry run, nothing was written. Would create %1$s, update %2$s, errors %3$s.', '3task-calendar' ),
					$plan['counts']['new'],
					$plan['counts']['update'],
					$plan['counts']['error']
				)
			);
			return;
		}

		$result = $importer->run( $plan );
		foreach ( $result['messages'] as $message ) {
			WP_CLI::warning( $message );
		}

		$summary = sprintf(
			/* translators: 1: created events, 2: updated events, 3: rows with errors, 4: new categories, 5: new locations */
			__( 'Import done. Created %1$s, updated %2$s, errors %3$s, new categories %4$s, new locations %5$s.', '3task-calendar' ),
			$result['created'],
			$result['updated'],
			$result['skipped'] + $result['failed'],
			$result['categories_created'],
			$result['locations_created']
		);
		if ( $result['skipped'] + $result['failed'] > 0 ) {
			WP_CLI::warning( $summary );
		} else {
			WP_CLI::success( $summary );
		}
	}

	/**
	 * Labels of the planned actions.
	 *
	 * @return array
	 */
	private static function action_labels() {
		return array(
			'new'    => __( 'new', '3task-calendar' ),
			'update' => __( 'update', '3task-calendar' ),
			'error'  => __( 'error', '3task-calendar' ),
		);
	}
}
