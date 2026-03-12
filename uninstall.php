<?php
/**
 * 3task Calendar Uninstall
 *
 * Removes all plugin data when uninstalled.
 *
 * @package ThreeCal
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if user wants to delete data.
$threecal_settings    = get_option( 'threecal_settings', array() );
$threecal_delete_data = isset( $threecal_settings['delete_data_on_uninstall'] ) && $threecal_settings['delete_data_on_uninstall'];

if ( $threecal_delete_data ) {
	global $wpdb;

	// Delete database tables.
	$threecal_tables = array(
		$wpdb->prefix . 'threecal_events',
		$wpdb->prefix . 'threecal_locations',
		$wpdb->prefix . 'threecal_categories',
		$wpdb->prefix . 'threecal_event_categories',
		$wpdb->prefix . 'threecal_subscribers',
	);

	foreach ( $threecal_tables as $threecal_table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internally generated, not user input.
		$wpdb->query( "DROP TABLE IF EXISTS $threecal_table" );
	}

	// Delete options.
	delete_option( 'threecal_settings' );
	delete_option( 'threecal_db_version' );

	// Delete transients.
	delete_transient( 'threecal_activated' );

	// Remove capabilities.
	$threecal_roles = array( 'administrator', 'editor' );
	$threecal_caps  = array(
		'manage_threecal',
		'create_threecal_events',
		'edit_threecal_events',
		'delete_threecal_events',
		'manage_threecal_locations',
		'manage_threecal_categories',
		'threecal_settings',
	);

	foreach ( $threecal_roles as $threecal_role_name ) {
		$threecal_role = get_role( $threecal_role_name );
		if ( $threecal_role ) {
			foreach ( $threecal_caps as $threecal_cap ) {
				$threecal_role->remove_cap( $threecal_cap );
			}
		}
	}

	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'threecal_daily_notifications' );
	wp_clear_scheduled_hook( 'threecal_cleanup_expired' );
}
