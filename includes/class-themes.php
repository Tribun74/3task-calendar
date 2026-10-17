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
			'site'    => array(
				'label'       => __( 'Theme colors', '3task-calendar' ),
				'description' => __( 'Takes colors and font from your theme (theme.json), so the calendar looks like the rest of your site.', '3task-calendar' ),
				'accent'      => '#4f46e5',
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
		$style  = '--tc-accent:' . $accent . ';--tc-accent-ink:' . self::ink( $accent ) . ';';
		if ( 'site' === $theme ) {
			$site = self::site_vars();
			if ( ! empty( $site['accent'] ) && ! self::accent_from_settings() ) {
				$style = '--tc-accent:' . $site['accent'] . ';--tc-accent-ink:' . self::ink( $site['accent'] ) . ';';
			}
			foreach ( array( 'surface', 'text', 'font' ) as $key ) {
				if ( ! empty( $site[ $key ] ) ) {
					$style .= '--tc-' . $key . ':' . $site[ $key ] . ';';
				}
			}
		}
		return $style;
	}

	/**
	 * Did the user set an accent color in the settings?
	 *
	 * @since 1.7.0
	 * @return bool
	 */
	private static function accent_from_settings() {
		$settings = get_option( 'threecal_settings', array() );
		return ! empty( $settings['accent_color'] ) && (bool) sanitize_hex_color( $settings['accent_color'] );
	}

	/**
	 * Colors and font of the active theme for the design "Theme colors".
	 *
	 * Reads theme.json through WordPress: the accent is the first palette color whose slug
	 * starts with "accent" or "primary", else the link color, else the contrast color. Surface
	 * and text come from the global background and text color, the font from the body font.
	 * Preset variables are resolved against the theme palette so the values work in a style
	 * attribute; a font stays a var() because the theme prints that preset itself.
	 * Themes without theme.json get an empty array and the design falls back to "Clear".
	 *
	 * @since 1.7.0
	 * @return array accent, surface, text, font (hex colors or empty).
	 */
	public static function site_vars() {
		static $vars = null;
		if ( null !== $vars ) {
			return $vars;
		}
		$vars = array();
		if ( ! function_exists( 'wp_theme_has_theme_json' ) || ! wp_theme_has_theme_json() ) {
			return $vars;
		}
		$palette = array();
		$settings = wp_get_global_settings( array( 'color', 'palette' ) );
		foreach ( array( 'theme', 'default', 'custom' ) as $group ) {
			foreach ( (array) ( isset( $settings[ $group ] ) ? $settings[ $group ] : array() ) as $entry ) {
				if ( ! empty( $entry['slug'] ) && ! empty( $entry['color'] ) && sanitize_hex_color( $entry['color'] ) ) {
					$palette[ $entry['slug'] ] = $entry['color'];
				}
			}
		}
		$resolve = function ( $value ) use ( $palette ) {
			$value = trim( (string) $value );
			if ( preg_match( '/^var\(--wp--preset--color--([a-z0-9-]+)\)$/i', $value, $m ) ) {
				return isset( $palette[ $m[1] ] ) ? $palette[ $m[1] ] : '';
			}
			return sanitize_hex_color( $value ) ? $value : '';
		};
		$styles = wp_get_global_styles();
		$vars['surface'] = $resolve( isset( $styles['color']['background'] ) ? $styles['color']['background'] : '' );
		$vars['text']    = $resolve( isset( $styles['color']['text'] ) ? $styles['color']['text'] : '' );
		$accent = '';
		foreach ( $palette as $slug => $color ) {
			if ( 0 === strpos( $slug, 'accent' ) || 0 === strpos( $slug, 'primary' ) ) {
				$accent = $color;
				break;
			}
		}
		if ( ! $accent ) {
			$accent = $resolve( isset( $styles['elements']['link']['color']['text'] ) ? $styles['elements']['link']['color']['text'] : '' );
		}
		if ( ! $accent ) {
			$accent = $resolve( isset( $styles['color']['text'] ) ? $styles['color']['text'] : '' );
		}
		// The accent also colors link text and buttons. A pale accent (Twenty Twenty-Five: yellow on
		// white) would be unreadable, so it needs a contrast of 3:1 against the surface, else the text color.
		if ( $accent && $vars['text'] && self::contrast( $accent, $vars['surface'] ? $vars['surface'] : '#ffffff' ) < 3 ) {
			$accent = $vars['text'];
		}
		$vars['accent'] = $accent;
		$font = isset( $styles['typography']['fontFamily'] ) ? trim( (string) $styles['typography']['fontFamily'] ) : '';
		$vars['font']   = preg_match( '/^var\(--wp--preset--font-family--[a-z0-9-]+\)$/i', $font ) ? $font : '';
		return $vars;
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
	 * Relative luminance of a hex color (WCAG). Since 1.7.0
	 *
	 * @param string $color Hex color.
	 * @return float|null
	 */
	private static function luminance( $color ) {
		$hex = ltrim( (string) sanitize_hex_color( (string) $color ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return null;
		}
		$luminance = 0;
		foreach ( array( 0.2126, 0.7152, 0.0722 ) as $i => $weight ) {
			$c          = hexdec( substr( $hex, $i * 2, 2 ) ) / 255;
			$c          = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
			$luminance += $weight * $c;
		}
		return $luminance;
	}

	/**
	 * Contrast ratio of two hex colors (WCAG), 1 when a color is invalid. Since 1.7.0
	 *
	 * @param string $a Hex color.
	 * @param string $b Hex color.
	 * @return float
	 */
	public static function contrast( $a, $b ) {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		if ( null === $la || null === $lb ) {
			return 1;
		}
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
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
