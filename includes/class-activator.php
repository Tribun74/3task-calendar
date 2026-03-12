<?php
/**
 * 3task Calendar Activator
 *
 * Handles plugin activation: creates database tables and sets default options.
 *
 * @package ThreeCal
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activator class.
 */
class ThreeCal_Activator {

	/**
	 * Activate the plugin
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::add_capabilities();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Set activation flag for welcome notice.
		set_transient( 'threecal_activated', true, 30 );
	}

	/**
	 * Create database tables
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Events table.
		$table_events = $wpdb->prefix . 'threecal_events';
		$sql_events   = "CREATE TABLE $table_events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description longtext,
			start_date datetime NOT NULL,
			end_date datetime DEFAULT NULL,
			all_day tinyint(1) NOT NULL DEFAULT 0,
			location_id bigint(20) unsigned DEFAULT NULL,
			url varchar(500) DEFAULT NULL,
			featured_image bigint(20) unsigned DEFAULT NULL,
			color varchar(7) DEFAULT '#3788d8',
			status varchar(20) NOT NULL DEFAULT 'draft',
			recurrence_rule varchar(255) DEFAULT NULL,
			recurrence_end datetime DEFAULT NULL,
			parent_id bigint(20) unsigned DEFAULT NULL,
			settings longtext DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_start_date (start_date),
			KEY idx_status (status),
			KEY idx_location (location_id),
			KEY idx_parent (parent_id)
		) $charset_collate;";

		// Locations table.
		$table_locations = $wpdb->prefix . 'threecal_locations';
		$sql_locations   = "CREATE TABLE $table_locations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			address varchar(500) DEFAULT NULL,
			city varchar(100) DEFAULT NULL,
			postal_code varchar(20) DEFAULT NULL,
			country varchar(2) DEFAULT 'DE',
			latitude decimal(10,8) DEFAULT NULL,
			longitude decimal(11,8) DEFAULT NULL,
			phone varchar(50) DEFAULT NULL,
			email varchar(100) DEFAULT NULL,
			website varchar(255) DEFAULT NULL,
			description text DEFAULT NULL,
			featured_image bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_city (city),
			KEY idx_country (country)
		) $charset_collate;";

		// Categories table.
		$table_categories = $wpdb->prefix . 'threecal_categories';
		$sql_categories   = "CREATE TABLE $table_categories (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			slug varchar(100) NOT NULL,
			description text DEFAULT NULL,
			color varchar(7) DEFAULT '#3788d8',
			parent_id bigint(20) unsigned DEFAULT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_slug (slug),
			KEY idx_parent (parent_id)
		) $charset_collate;";

		// Event-Category relationship table.
		$table_event_categories = $wpdb->prefix . 'threecal_event_categories';
		$sql_event_categories   = "CREATE TABLE $table_event_categories (
			event_id bigint(20) unsigned NOT NULL,
			category_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY (event_id, category_id),
			KEY idx_category (category_id)
		) $charset_collate;";

		// Subscribers table (for event notifications).
		$table_subscribers = $wpdb->prefix . 'threecal_subscribers';
		$sql_subscribers   = "CREATE TABLE $table_subscribers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(100) NOT NULL,
			name varchar(100) DEFAULT NULL,
			event_id bigint(20) unsigned DEFAULT NULL,
			category_id bigint(20) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			token varchar(64) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_email_event (email, event_id),
			KEY idx_token (token),
			KEY idx_status (status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_events );
		dbDelta( $sql_locations );
		dbDelta( $sql_categories );
		dbDelta( $sql_event_categories );
		dbDelta( $sql_subscribers );

		// Store database version.
		update_option( 'threecal_db_version', THREECAL_DB_VERSION );
	}

	/**
	 * Set default options
	 */
	private static function set_default_options() {
		$defaults = array(
			'threecal_settings' => array(
				// General.
				'date_format'    => get_option( 'date_format' ),
				'time_format'    => get_option( 'time_format' ),
				'week_starts_on' => get_option( 'start_of_week', 1 ),
				'default_view'   => 'month',
				'default_theme'  => 'default',

				// Display.
				'show_event_time'        => true,
				'show_event_location'    => true,
				'show_event_description' => true,
				'events_per_page'        => 10,
				'enable_event_popup'     => true,

				// Google Maps.
				'google_maps_api_key' => '',
				'default_map_zoom'    => 14,
				'default_map_type'    => 'roadmap',

				// Email.
				'enable_notifications'        => true,
				'notification_sender_name'    => get_bloginfo( 'name' ),
				'notification_sender_email'   => get_option( 'admin_email' ),
				'notification_template'       => "Hallo {subscriber_name},\n\nErinnerung: {event_title} findet am {event_date} statt.\n\nOrt: {event_location}\n\nMit freundlichen Grüßen,\n{site_name}",

				// SEO.
				'enable_schema' => true,

				// Advanced.
				'delete_data_on_uninstall' => false,
			),
		);

		foreach ( $defaults as $option => $value ) {
			if ( get_option( $option ) === false ) {
				add_option( $option, $value );
			}
		}
	}

	/**
	 * Add capabilities
	 */
	private static function add_capabilities() {
		$admin  = get_role( 'administrator' );
		$editor = get_role( 'editor' );

		$admin_caps = array(
			'manage_threecal',
			'create_threecal_events',
			'edit_threecal_events',
			'delete_threecal_events',
			'manage_threecal_locations',
			'manage_threecal_categories',
			'threecal_settings',
		);

		$editor_caps = array(
			'create_threecal_events',
			'edit_threecal_events',
			'manage_threecal_locations',
		);

		if ( $admin ) {
			foreach ( $admin_caps as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		if ( $editor ) {
			foreach ( $editor_caps as $cap ) {
				$editor->add_cap( $cap );
			}
		}
	}
}
