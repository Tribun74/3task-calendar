<?php
/**
 * 3task Calendar Deactivator
 *
 * Handles plugin deactivation.
 *
 * @package ThreeCal
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivator class.
 */
class ThreeCal_Deactivator {

	/**
	 * Deactivate the plugin
	 */
	public static function deactivate() {
		// Clear scheduled hooks.
		wp_clear_scheduled_hook( 'threecal_daily_notifications' );
		wp_clear_scheduled_hook( 'threecal_cleanup_expired' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
