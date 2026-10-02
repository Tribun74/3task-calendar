<?php
/**
 * 3task Calendar icons
 *
 * Inline SVG icons from Tabler Icons (https://tabler.io/icons, MIT license).
 * Inline SVG needs no icon font and no external request.
 *
 * @package ThreeCal
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icons class.
 */
class ThreeCal_Icons {

	/**
	 * Path data per icon (24x24 viewBox, stroke icons).
	 *
	 * @return array
	 */
	private static function paths() {
		return array(
			'chevron-left'     => array( 'M15 6l-6 6l6 6' ),
			'chevron-right'    => array( 'M9 6l6 6l-6 6' ),
			'calendar-event'   => array( 'M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2l0 -12', 'M16 3l0 4', 'M8 3l0 4', 'M4 11l16 0', 'M8 15h2v2h-2l0 -2' ),
			'calendar-month'   => array( 'M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12', 'M16 3v4', 'M8 3v4', 'M4 11h16', 'M8 14v4', 'M12 14v4', 'M16 14v4' ),
			'calendar-plus'    => array( 'M12.5 21h-6.5a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v5', 'M16 3v4', 'M8 3v4', 'M4 11h16', 'M16 19h6', 'M19 16v6' ),
			'calendar-check'   => array( 'M11.5 21h-5.5a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v6', 'M16 3v4', 'M8 3v4', 'M4 11h16', 'M15 19l2 2l4 -4' ),
			'calendar-stats'   => array( 'M11.795 21h-6.795a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v4', 'M18 14v4h4', 'M14 18a4 4 0 1 0 8 0a4 4 0 1 0 -8 0', 'M15 3v4', 'M7 3v4', 'M3 11h16' ),
			'clock'            => array( 'M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0', 'M12 7v5l3 3' ),
			'map-pin'          => array( 'M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0', 'M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0' ),
			'route'            => array( 'M3 19a2 2 0 1 0 4 0a2 2 0 0 0 -4 0', 'M19 7a2 2 0 1 0 0 -4a2 2 0 0 0 0 4', 'M11 19h5.5a3.5 3.5 0 0 0 0 -7h-8a3.5 3.5 0 0 1 0 -7h4.5' ),
			'rss'              => array( 'M4 19a1 1 0 1 0 2 0a1 1 0 1 0 -2 0', 'M4 4a16 16 0 0 1 16 16', 'M4 11a9 9 0 0 1 9 9' ),
			'external-link'    => array( 'M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6', 'M11 13l9 -9', 'M15 4h5v5' ),
			'x'                => array( 'M18 6l-12 12', 'M6 6l12 12' ),
			'list-details'     => array( 'M13 5h8', 'M13 9h5', 'M13 15h8', 'M13 19h5', 'M3 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -4', 'M3 15a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -4' ),
			'layout-dashboard' => array( 'M5 4h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1', 'M5 16h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1', 'M15 12h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1', 'M15 4h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1' ),
			'category'         => array( 'M4 4h6v6h-6l0 -6', 'M14 4h6v6h-6l0 -6', 'M4 14h6v6h-6l0 -6', 'M14 17a3 3 0 1 0 6 0a3 3 0 1 0 -6 0' ),
			'settings'         => array( 'M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065', 'M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0' ),
			'help-circle'      => array( 'M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0', 'M12 16v.01', 'M12 13a2 2 0 0 0 .914 -3.782a1.98 1.98 0 0 0 -2.414 .483' ),
			'palette'          => array( 'M12 21a9 9 0 0 1 0 -18c4.97 0 9 3.582 9 8c0 1.06 -.474 2.078 -1.318 2.828c-.844 .75 -1.989 1.172 -3.182 1.172h-2.5a2 2 0 0 0 -1 3.75a1.3 1.3 0 0 1 -1 2.25', 'M7.5 10.5a1 1 0 1 0 2 0a1 1 0 1 0 -2 0', 'M11.5 7.5a1 1 0 1 0 2 0a1 1 0 1 0 -2 0', 'M15.5 10.5a1 1 0 1 0 2 0a1 1 0 1 0 -2 0' ),
			'plus'             => array( 'M12 5l0 14', 'M5 12l14 0' ),
			'article'          => array( 'M3 6a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2l0 -12', 'M7 8h10', 'M7 12h10', 'M7 16h10' ),
			'clock-exclamation' => array( 'M20.986 12.502a9 9 0 1 0 -5.973 7.98', 'M12 7v5l3 3', 'M19 16v3', 'M19 22v.01' ),
			'repeat'           => array( 'M4 12v-3a3 3 0 0 1 3 -3h13m-3 -3l3 3l-3 3', 'M20 12v3a3 3 0 0 1 -3 3h-13m3 3l-3 -3l3 -3' ),
			'pencil-plus'      => array( 'M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4', 'M13.5 6.5l4 4', 'M16 19h6', 'M19 16v6' ),
			'book'             => array( 'M3 19a9 9 0 0 1 9 0a9 9 0 0 1 9 0', 'M3 6a9 9 0 0 1 9 0a9 9 0 0 1 9 0', 'M3 6l0 13', 'M12 6l0 13', 'M21 6l0 13' ),
			'message-circle'   => array( 'M3 20l1.3 -3.9c-2.324 -3.437 -1.426 -7.872 2.1 -10.374c3.526 -2.501 8.59 -2.296 11.845 .48c3.255 2.777 3.695 7.266 1.029 10.501c-2.666 3.235 -7.615 4.215 -11.574 2.293l-4.7 1' ),
			'download'         => array( 'M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2', 'M7 11l5 5l5 -5', 'M12 4l0 12' ),
		);
	}

	/**
	 * Return an inline SVG icon.
	 *
	 * @param string $name  Icon name.
	 * @param int    $size  Width and height in px.
	 * @param string $class Extra CSS class.
	 * @return string SVG markup (safe, built from fixed data).
	 */
	public static function svg( $name, $size = 18, $class = '' ) {
		$paths = self::paths();
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		$out = '<svg class="threecal-icon ' . esc_attr( trim( 'threecal-icon-' . $name . ' ' . $class ) ) . '" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="' . absint( $size ) . '" height="' . absint( $size ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">';
		foreach ( $paths[ $name ] as $d ) {
			$out .= '<path d="' . esc_attr( $d ) . '"/>';
		}
		return $out . '</svg>';
	}

	/**
	 * Allowed tags for wp_kses() when printing icons.
	 *
	 * @return array
	 */
	public static function kses() {
		return array(
			'svg'  => array(
				'class'           => true,
				'aria-hidden'     => true,
				'focusable'       => true,
				'xmlns'           => true,
				'width'           => true,
				'height'          => true,
				'viewbox'         => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
			'path' => array( 'd' => true ),
		);
	}

	/**
	 * Print an icon.
	 *
	 * @param string $name Icon name.
	 * @param int    $size Size in px.
	 */
	public static function render( $name, $size = 18 ) {
		echo wp_kses( self::svg( $name, $size ), self::kses() );
	}

	/**
	 * Icons used by the frontend script.
	 *
	 * @return array
	 */
	public static function for_js() {
		$names = array( 'calendar-event', 'clock', 'map-pin', 'route', 'calendar-plus', 'external-link' );
		$icons = array();
		foreach ( $names as $name ) {
			$icons[ $name ] = self::svg( $name, 18 );
		}
		return $icons;
	}
}
