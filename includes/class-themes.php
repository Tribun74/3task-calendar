<?php
/**
 * 3task Calendar designs
 *
 * The free plugin ships three designs. Other plugins can add more through the
 * "threecal_themes" filter; each entry may name its own stylesheet handle.
 *
 * @package ThreeCal
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Themes class.
 */
class ThreeCal_Themes {

	/**
	 * Default color for events without own color (old default value).
	 */
	const LEGACY_EVENT_COLOR = '#3788d8';

	/**
	 * Design of the first calendar on the current page that set one explicitly.
	 *
	 * @var string
	 */
	private static $page_theme = '';

	/**
	 * Available designs.
	 *
	 * @return array Map of key to array( label, description, accent, style ).
	 */
	public static function all() {
		$themes = array(
			'default' => array(
				'label'       => __( 'Clear', '3task-calendar' ),
				'description' => __( 'Light and calm, with soft event chips in the category colors.', '3task-calendar' ),
				'accent'      => '#4f46e5',
				'style'       => '',
			),
			'accent'  => array(
				'label'       => __( 'Accent', '3task-calendar' ),
				'description' => __( 'Bold color header and solid event pills that stand out.', '3task-calendar' ),
				'accent'      => '#2563eb',
				'style'       => '',
			),
			'dark'    => array(
				'label'       => __( 'Night', '3task-calendar' ),
				'description' => __( 'Dark surface with glowing accents for dark websites.', '3task-calendar' ),
				'accent'      => '#38bdf8',
				'style'       => '',
			),
		);

		/**
		 * Filter the available calendar designs.
		 *
		 * @param array $themes Map of key to array( label, description, accent, style ).
		 */
		return (array) apply_filters( 'threecal_themes', $themes );
	}

	/**
	 * Map a requested design to an existing one.
	 *
	 * Designs before 1.3.0 (minimal, boxed, gradient, glassmorphism) are
	 * mapped to the closest new design. An empty value uses the default
	 * design from the settings.
	 *
	 * @param string $theme Requested design.
	 * @return string
	 */
	public static function normalize( $theme ) {
		$theme  = sanitize_key( (string) $theme );
		$themes = self::all();

		$legacy = array(
			'minimal'       => 'default',
			'boxed'         => 'default',
			'gradient'      => 'accent',
			'glassmorphism' => 'dark',
		);
		if ( isset( $legacy[ $theme ] ) && ! isset( $themes[ $theme ] ) ) {
			$theme = $legacy[ $theme ];
		}

		// Elements without own design follow the first calendar on the page that set one.
		if ( '' === $theme && '' !== self::$page_theme ) {
			return self::$page_theme;
		}

		if ( '' === $theme || ! isset( $themes[ $theme ] ) ) {
			$settings = get_option( 'threecal_settings', array() );
			$fallback = isset( $settings['default_theme'] ) ? sanitize_key( $settings['default_theme'] ) : 'default';
			if ( isset( $legacy[ $fallback ] ) && ! isset( $themes[ $fallback ] ) ) {
				$fallback = $legacy[ $fallback ];
			}
			$theme = isset( $themes[ $fallback ] ) ? $fallback : 'default';
		}

		return $theme;
	}

	/**
	 * Remember an explicitly chosen design for the rest of the page.
	 *
	 * @param string $theme Requested design (raw attribute value).
	 */
	public static function remember_page_theme( $theme ) {
		if ( '' !== self::$page_theme || '' === trim( (string) $theme ) ) {
			return;
		}
		$normalized = self::normalize( $theme );
		if ( isset( self::all()[ $normalized ] ) ) {
			self::$page_theme = $normalized;
		}
	}

	/**
	 * Accent color for a design (setting overrides the design default).
	 *
	 * @param string $theme Design key.
	 * @return string Hex color.
	 */
	public static function accent( $theme ) {
		$settings = get_option( 'threecal_settings', array() );
		if ( ! empty( $settings['accent_color'] ) && sanitize_hex_color( $settings['accent_color'] ) ) {
			return $settings['accent_color'];
		}
		$themes = self::all();
		return isset( $themes[ $theme ]['accent'] ) ? $themes[ $theme ]['accent'] : '#4f46e5';
	}

	/**
	 * Inline style with the design variables for a wrapper element.
	 *
	 * @param string $theme Design key.
	 * @return string
	 */
	public static function wrapper_style( $theme ) {
		$accent = self::accent( $theme );
		return '--tc-accent:' . $accent . ';--tc-accent-ink:' . self::ink( $accent ) . ';';
	}

	/**
	 * Readable text color on a colored background.
	 *
	 * White stays as long as it reaches a contrast of 3:1 (WCAG for bold text
	 * and controls), so the usual colors look as before. Light colors such as
	 * yellow get dark text.
	 *
	 * @param string $color Hex color.
	 * @return string Hex color.
	 */
	public static function ink( $color ) {
		$hex = ltrim( (string) sanitize_hex_color( (string) $color ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return '#ffffff';
		}
		$luminance = 0;
		foreach ( array( 0.2126, 0.7152, 0.0722 ) as $i => $weight ) {
			$c          = hexdec( substr( $hex, $i * 2, 2 ) ) / 255;
			$c          = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
			$luminance += $weight * $c;
		}
		return ( 1.05 / ( $luminance + 0.05 ) ) >= 3 ? '#ffffff' : '#0f172a';
	}

	/**
	 * Enqueue the extra stylesheet of a design added by another plugin.
	 *
	 * @param string $theme Design key.
	 */
	public static function enqueue( $theme ) {
		$themes = self::all();
		if ( ! empty( $themes[ $theme ]['style'] ) ) {
			wp_enqueue_style( $themes[ $theme ]['style'] );
		}
	}

	/**
	 * Color to show for an event.
	 *
	 * An event keeps its own color. Events with the old default color use the
	 * color of their first category, otherwise the design accent (empty string).
	 *
	 * @param ThreeCal_Event $event      Event.
	 * @param array          $categories Category rows of the event.
	 * @return string Hex color or empty string.
	 */
	public static function event_color( $event, $categories = array() ) {
		$color = sanitize_hex_color( (string) $event->color );
		if ( $color && strtolower( $color ) !== self::LEGACY_EVENT_COLOR ) {
			return $color;
		}
		foreach ( (array) $categories as $cat ) {
			$cat_color = sanitize_hex_color( (string) $cat->color );
			if ( $cat_color ) {
				return $cat_color;
			}
		}
		return '';
	}

	/**
	 * Inline style for an event element.
	 *
	 * @param string $color Hex color or empty.
	 * @return string
	 */
	public static function event_style( $color ) {
		return $color ? '--tc-ev:' . $color . ';--tc-ev-ink:' . self::ink( $color ) . ';' : '';
	}
}
