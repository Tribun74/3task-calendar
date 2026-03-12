<?php
/**
 * 3task Calendar Internationalization
 *
 * Handles internationalization functionality.
 * Note: Since WordPress 4.6+, translations are automatically loaded from
 * wp-content/languages/plugins/ directory. Manual loading is no longer needed.
 *
 * @package ThreeCal
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * i18n Class
 *
 * @since 1.0.0
 */
class ThreeCal_i18n {

	/**
	 * Initialize i18n functionality
	 *
	 * WordPress 4.6+ automatically loads translations from the languages directory.
	 * This method is kept for compatibility but does not need to load textdomain manually.
	 *
	 * @since 1.0.0
	 */
	public function load_plugin_textdomain() {
		// WordPress 4.6+ automatically loads translations from:
		// wp-content/languages/plugins/3task-calendar-{locale}.mo
		// No manual loading needed.
	}
}
