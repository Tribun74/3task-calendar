<?php
/**
 * 3task Calendar dates from posts
 *
 * Authors set a date while writing a post, for example the cinema release of
 * a film. The plugin creates a normal calendar event from it and keeps it in
 * sync, so month view, lists, categories, colors, the iCal feed and all
 * designs work for posts as well. A date that already exists in a custom
 * field (ACF, Meta Box or any other plugin) can be used instead.
 *
 * @package ThreeCal
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dates from posts.
 */
class ThreeCal_Post_Dates {

	/**
	 * Option with the settings per post type.
	 */
	const OPTION = 'threecal_post_dates';

	/**
	 * Post meta: '' follows the post type, '1' shows the date, '0' hides it.
	 */
	const META_SHOW = '_threecal_show';

	/**
	 * Post meta: start as Y-m-d or Y-m-d H:i.
	 */
	const META_START = '_threecal_start';

	/**
	 * Post meta: optional end as Y-m-d or Y-m-d H:i.
	 */
	const META_END = '_threecal_end';

	/**
	 * Post meta: '0' for a date with time, everything else is all day.
	 */
	const META_ALL_DAY = '_threecal_all_day';

	/**
	 * Post meta: ID of the linked calendar event.
	 */
	const META_EVENT = '_threecal_event_id';

	/**
	 * Post meta: start as Y-m-d H:i:s, used for the sortable admin column.
	 */
	const META_SORT = '_threecal_sort';

	/**
	 * Posts waiting for a sync at the end of the request.
	 *
	 * @var int[]
	 */
	private static $pending = array();

	/**
	 * True while a sync writes, so its own changes do not start another one.
	 *
	 * @var bool
	 */
	private static $syncing = false;

	/**
	 * Meta keys that start a sync when they change (built on first use).
	 *
	 * @var array|null
	 */
	private static $watch = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ), 20 );
		add_action( 'wp_after_insert_post', array( $this, 'after_insert_post' ), 20, 2 );
		add_action( 'transition_post_status', array( $this, 'status_changed' ), 10, 3 );
		add_action( 'added_post_meta', array( $this, 'meta_changed' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'meta_changed' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'meta_changed' ), 10, 3 );
		add_action( 'trashed_post', array( $this, 'queue' ) );
		add_action( 'untrashed_post', array( $this, 'queue' ) );
		add_action( 'before_delete_post', array( $this, 'before_delete_post' ) );
		add_action( 'shutdown', array( $this, 'flush' ) );

		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_panel' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'register_columns' ) );
		add_filter( 'posts_clauses', array( $this, 'sort_by_date' ), 10, 2 );
		add_action( 'wp_ajax_threecal_post_dates_sync', array( $this, 'ajax_sync' ) );

		add_shortcode( 'threecal_post_date', array( $this, 'shortcode' ) );
		add_filter( 'the_content', array( $this, 'auto_box' ), 20 );
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	/**
	 * All settings.
	 *
	 * @return array
	 */
	public static function settings() {
		$settings = (array) get_option( self::OPTION, array() );
		return array(
			'types'     => isset( $settings['types'] ) && is_array( $settings['types'] ) ? $settings['types'] : array(),
			'postponed' => isset( $settings['postponed'] ) ? (bool) $settings['postponed'] : true,
		);
	}

	/**
	 * Settings of a post type, or null when dates are off for it.
	 *
	 * @param string $post_type Post type.
	 * @return array|null
	 */
	public static function type_settings( $post_type ) {
		$types = self::settings()['types'];
		if ( empty( $types[ $post_type ]['enabled'] ) ) {
			return null;
		}
		return wp_parse_args(
			$types[ $post_type ],
			array(
				'enabled'  => true,
				'label'    => '',
				'category' => 0,
				'source'   => '',
				'box'      => 'none',
			)
		);
	}

	/**
	 * Post types with dates switched on.
	 *
	 * @return array Settings keyed by post type.
	 */
	public static function enabled_types() {
		$enabled = array();
		foreach ( array_keys( self::settings()['types'] ) as $post_type ) {
			$settings = self::type_settings( $post_type );
			if ( $settings && post_type_exists( $post_type ) ) {
				$enabled[ $post_type ] = $settings;
			}
		}
		return $enabled;
	}

	/**
	 * Post types that can carry a date.
	 *
	 * @return WP_Post_Type[]
	 */
	public static function available_types() {
		$types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);
		unset( $types['attachment'] );
		return $types;
	}

	/**
	 * Label of the date field of a post type.
	 *
	 * @param array|null $settings Post type settings.
	 * @return string
	 */
	public static function label( $settings ) {
		return ( $settings && '' !== $settings['label'] ) ? $settings['label'] : __( 'Event date', '3task-calendar' );
	}

	/**
	 * Clean the submitted settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$types = array();

		foreach ( array_keys( self::available_types() ) as $post_type ) {
			$row = isset( $input['types'][ $post_type ] ) ? (array) $input['types'][ $post_type ] : array();
			if ( empty( $row['enabled'] ) ) {
				continue;
			}

			$source = isset( $row['source'] ) ? (string) $row['source'] : '';
			if ( '__custom' === $source ) {
				$source = isset( $row['source_custom'] ) ? (string) $row['source_custom'] : '';
			}
			$source = self::clean_key( $source );

			$box = isset( $row['box'] ) ? sanitize_key( $row['box'] ) : 'none';

			$types[ $post_type ] = array(
				'enabled'  => true,
				'label'    => isset( $row['label'] ) ? mb_substr( sanitize_text_field( $row['label'] ), 0, 60 ) : '',
				'category' => isset( $row['category'] ) ? absint( $row['category'] ) : 0,
				'source'   => $source,
				'box'      => in_array( $box, array( 'none', 'before', 'after' ), true ) ? $box : 'none',
			);
		}

		return array(
			'types'     => $types,
			'postponed' => ! empty( $input['postponed'] ),
		);
	}

	/**
	 * Allowed characters of a meta key.
	 *
	 * @param string $key Meta key.
	 * @return string
	 */
	private static function clean_key( $key ) {
		$key = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key );
		// Our own keys are not a source.
		return 0 === strpos( $key, '_threecal_' ) ? '' : $key;
	}

	/* ---------------------------------------------------------------------
	 * Dates
	 * ------------------------------------------------------------------ */

	/**
	 * Read a date in one of the usual formats.
	 *
	 * Accepts Y-m-d, Y-m-d H:i(:s), the ISO form with T, Ymd (ACF date
	 * picker), d.m.Y (with time) and Unix timestamps.
	 *
	 * @param mixed $value Raw value.
	 * @return array|null Array( 'Y-m-d H:i:s', has time ) or null.
	 */
	public static function parse_date( $value ) {
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		// Unix timestamp; midnight counts as a date without time.
		if ( preg_match( '/^\d{9,10}$/', $value ) ) {
			$local = wp_date( 'Y-m-d H:i:s', (int) $value );
			if ( ! $local ) {
				return null;
			}
			return self::valid_year( $local ) ? array( $local, '00:00:00' !== substr( $local, 11 ) ) : null;
		}

		$formats = array(
			'Y-m-d H:i:s' => true,
			'Y-m-d H:i'   => true,
			'Y-m-d\TH:i:s' => true,
			'Y-m-d\TH:i'  => true,
			'Y-m-d'       => false,
			'Ymd'         => false,
			'd.m.Y H:i'   => true,
			'd.m.Y'       => false,
			'j.n.Y'       => false,
		);
		foreach ( $formats as $format => $has_time ) {
			$date = DateTime::createFromFormat( '!' . $format, $value );
			if ( $date && $date->format( $format ) === $value ) {
				$out = $date->format( 'Y-m-d H:i:s' );
				return self::valid_year( $out ) ? array( $out, $has_time ) : null;
			}
		}

		// ISO date with time zone, for example 2026-10-01T20:00:00+02:00.
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:?\d{2}|Z)$/', $value ) ) {
			$timestamp = strtotime( $value );
			if ( $timestamp ) {
				$local = wp_date( 'Y-m-d H:i:s', $timestamp );
				return self::valid_year( $local ) ? array( $local, true ) : null;
			}
		}

		return null;
	}

	/**
	 * Only plausible years.
	 *
	 * @param string $date Y-m-d H:i:s.
	 * @return bool
	 */
	private static function valid_year( $date ) {
		$year = (int) substr( $date, 0, 4 );
		return $year >= 1900 && $year <= 2100;
	}

	/**
	 * The date a post should have in the calendar.
	 *
	 * @param WP_Post $post     Post.
	 * @param array   $settings Post type settings.
	 * @return array|null Array with start, end and all_day, or null.
	 */
	public static function values( $post, $settings ) {
		$show = (string) get_post_meta( $post->ID, self::META_SHOW, true );

		if ( '' !== $settings['source'] ) {
			// Date from an existing field: on unless switched off in the post.
			if ( '0' === $show ) {
				return null;
			}
			$parsed = self::parse_date( get_post_meta( $post->ID, $settings['source'], true ) );
			if ( ! $parsed ) {
				return null;
			}
			return array(
				'start'   => $parsed[1] ? $parsed[0] : substr( $parsed[0], 0, 10 ) . ' 00:00:00',
				'end'     => '',
				'all_day' => ! $parsed[1],
			);
		}

		// Own field: only when switched on in the post.
		if ( '1' !== $show ) {
			return null;
		}
		$all_day = '0' !== (string) get_post_meta( $post->ID, self::META_ALL_DAY, true );
		$start   = self::parse_date( get_post_meta( $post->ID, self::META_START, true ) );
		if ( ! $start ) {
			return null;
		}
		$start_value = $all_day ? substr( $start[0], 0, 10 ) . ' 00:00:00' : $start[0];

		$end       = self::parse_date( get_post_meta( $post->ID, self::META_END, true ) );
		$end_value = '';
		if ( $end ) {
			$end_value = $all_day ? substr( $end[0], 0, 10 ) . ' 23:59:59' : $end[0];
			if ( $end_value <= $start_value ) {
				$end_value = '';
			}
		}

		return array(
			'start'   => $start_value,
			'end'     => $end_value,
			'all_day' => $all_day,
		);
	}

	/* ---------------------------------------------------------------------
	 * Sync
	 * ------------------------------------------------------------------ */

	/**
	 * Create, update or remove the calendar event of a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function sync( $post_id ) {
		$post_id = (int) $post_id;
		unset( self::$pending[ $post_id ] );

		$post = get_post( $post_id );
		if ( ! $post || 'revision' === $post->post_type || 'auto-draft' === $post->post_status ) {
			return;
		}

		self::$syncing = true;

		$event_id = (int) get_post_meta( $post_id, self::META_EVENT, true );
		$event    = $event_id ? ThreeCal_Event::get( $event_id ) : null;
		if ( $event && (int) $event->get_setting( 'post_id' ) !== $post_id ) {
			$event = null;
		}

		$settings = self::type_settings( $post->post_type );
		$values   = $settings ? self::values( $post, $settings ) : null;

		if ( ! $values ) {
			if ( $event ) {
				$event->delete();
			}
			delete_post_meta( $post_id, self::META_EVENT );
			delete_post_meta( $post_id, self::META_SORT );
			self::$syncing = false;
			return;
		}

		$is_new = ! $event;
		if ( $is_new ) {
			$event = new ThreeCal_Event();
		}

		$live_before = ! $is_new && 'published' === $event->status;
		$old_day     = substr( (string) $event->start_date, 0, 10 );
		$new_day     = substr( $values['start'], 0, 10 );

		$event->title           = html_entity_decode( wp_strip_all_tags( $post->post_title ), ENT_QUOTES, 'UTF-8' );
		$event->description     = self::excerpt( $post );
		$event->start_date      = $values['start'];
		$event->end_date        = $values['end'];
		$event->all_day         = $values['all_day'];
		$event->url             = get_permalink( $post );
		$event->featured_image  = (int) get_post_thumbnail_id( $post );
		$event->color           = '';
		$event->status          = 'publish' === $post->post_status ? 'published' : 'draft';
		$event->recurrence_rule = '';
		$event->recurrence_end  = '';
		$event->parent_id       = 0;

		// A published date that moves is shown as postponed, until it moves back.
		if ( $live_before && 'published' === $event->status && $old_day && $old_day !== $new_day ) {
			$event->set_setting( 'previous_start', $old_day );
		}
		if ( $new_day === (string) $event->get_setting( 'previous_start', '' ) ) {
			$event->set_setting( 'previous_start', '' );
		}
		$event->set_setting( 'post_id', $post_id );
		$event->set_setting( 'post_type', $post->post_type );

		if ( $event->save() ) {
			$category = (int) $settings['category'];
			$event->set_categories( ( $category && ThreeCal_Category::get( $category ) ) ? array( $category ) : array() );
			update_post_meta( $post_id, self::META_EVENT, (int) $event->id );
			update_post_meta( $post_id, self::META_SORT, $event->start_date );
		}

		self::$syncing = false;
	}

	/**
	 * Short description from the excerpt or the start of the post.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function excerpt( $post ) {
		$text = '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : strip_shortcodes( $post->post_content );
		$text = wp_strip_all_tags( excerpt_remove_blocks( $text ) );
		return wp_trim_words( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ), 40, '…' );
	}

	/**
	 * Remember a post for a sync at the end of the request.
	 *
	 * @param int $post_id Post ID.
	 */
	public function queue( $post_id ) {
		self::$pending[ (int) $post_id ] = true;
	}

	/**
	 * Sync all remembered posts.
	 */
	public function flush() {
		foreach ( array_keys( self::$pending ) as $post_id ) {
			self::sync( $post_id );
		}
	}

	/**
	 * After a post was saved (block editor, classic editor, quick edit, REST).
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function after_insert_post( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( self::type_settings( $post->post_type ) || get_post_meta( $post_id, self::META_EVENT, true ) ) {
			self::sync( $post_id );
		}
	}

	/**
	 * Scheduled posts that go live do not always pass through the editor.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public function status_changed( $new_status, $old_status, $post ) {
		if ( $new_status !== $old_status && self::type_settings( $post->post_type ) ) {
			$this->queue( $post->ID );
		}
	}

	/**
	 * A watched field changed without the post being saved, for example by an import.
	 *
	 * @param int|array $meta_id   Meta ID(s).
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 */
	public function meta_changed( $meta_id, $object_id, $meta_key ) {
		if ( self::$syncing ) {
			return;
		}
		if ( null === self::$watch ) {
			self::$watch = array();
			foreach ( self::enabled_types() as $settings ) {
				foreach ( array( self::META_SHOW, self::META_START, self::META_END, self::META_ALL_DAY, '_thumbnail_id', $settings['source'] ) as $key ) {
					if ( '' !== $key ) {
						self::$watch[ $key ] = true;
					}
				}
			}
		}
		// Cheap check first: most meta changes on a site have nothing to do with us.
		if ( ! isset( self::$watch[ $meta_key ] ) ) {
			return;
		}
		$settings = self::type_settings( get_post_type( $object_id ) );
		if ( $settings && ( '_thumbnail_id' !== $meta_key || get_post_meta( $object_id, self::META_EVENT, true ) ) ) {
			$this->queue( $object_id );
		}
	}

	/**
	 * A deleted post takes its calendar event with it.
	 *
	 * @param int $post_id Post ID.
	 */
	public function before_delete_post( $post_id ) {
		$event_id = (int) get_post_meta( $post_id, self::META_EVENT, true );
		if ( ! $event_id ) {
			return;
		}
		$event = ThreeCal_Event::get( $event_id );
		if ( $event && (int) $event->get_setting( 'post_id' ) === (int) $post_id ) {
			$event->delete();
		}
		unset( self::$pending[ (int) $post_id ] );
	}

	/**
	 * Post the date belongs to, for an event that comes from a post.
	 *
	 * @param ThreeCal_Event $event Event.
	 * @return int Post ID or 0.
	 */
	public static function post_of( $event ) {
		return $event ? (int) $event->get_setting( 'post_id', 0 ) : 0;
	}

	/**
	 * "Postponed, previously …" for a date that moved, empty otherwise.
	 *
	 * @param ThreeCal_Event $event Event.
	 * @return string Plain text.
	 */
	public static function postponed_text( $event ) {
		if ( ! $event || ! self::settings()['postponed'] ) {
			return '';
		}
		$previous = (string) $event->get_setting( 'previous_start', '' );
		if ( '' === $previous || substr( (string) $event->end_date ?: (string) $event->start_date, 0, 10 ) < current_time( 'Y-m-d' ) ) {
			return '';
		}
		/* translators: %s: the date before it was moved */
		return sprintf( __( 'Postponed, previously %s', '3task-calendar' ), date_i18n( get_option( 'date_format' ), strtotime( $previous ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Editor
	 * ------------------------------------------------------------------ */

	/**
	 * Register the post meta for the block editor and the date block.
	 */
	public function register() {
		foreach ( array_keys( self::enabled_types() ) as $post_type ) {
			// The block editor saves post meta only for types with custom fields support.
			add_post_type_support( $post_type, 'custom-fields' );

			foreach ( array( self::META_SHOW, self::META_START, self::META_END, self::META_ALL_DAY ) as $key ) {
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'default'           => '',
						'show_in_rest'      => true,
						'sanitize_callback' => array( __CLASS__, 'sanitize_meta' ),
						'auth_callback'     => array( __CLASS__, 'can_edit' ),
					)
				);
			}
		}

		$this->register_block();
	}

	/**
	 * Only users who may edit the post may change its date.
	 *
	 * @param bool   $allowed  Default.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 * @return bool
	 */
	public static function can_edit( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Clean a date meta value.
	 *
	 * @param mixed  $value    Value.
	 * @param string $meta_key Meta key.
	 * @return string
	 */
	public static function sanitize_meta( $value, $meta_key = '' ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( self::META_SHOW === $meta_key || self::META_ALL_DAY === $meta_key ) {
			return in_array( $value, array( '0', '1' ), true ) ? $value : '';
		}
		$parsed = self::parse_date( $value );
		if ( ! $parsed ) {
			return '';
		}
		return $parsed[1] ? substr( $parsed[0], 0, 16 ) : substr( $parsed[0], 0, 10 );
	}

	/**
	 * Script for the panel in the block editor.
	 */
	public function enqueue_panel() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || empty( $screen->post_type ) ) {
			return;
		}
		$settings = self::type_settings( $screen->post_type );
		if ( ! $settings ) {
			return;
		}

		wp_enqueue_script(
			'threecal-post-dates-panel',
			THREECAL_PLUGIN_URL . 'admin/js/post-dates-panel.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
			threecal_asset_version( 'admin/js/post-dates-panel.js' ),
			true
		);
		wp_set_script_translations( 'threecal-post-dates-panel', '3task-calendar', THREECAL_PLUGIN_DIR . 'languages' );

		$post     = get_post();
		$mapped   = '';
		$category = $settings['category'] ? ThreeCal_Category::get( (int) $settings['category'] ) : null;
		if ( $post && '' !== $settings['source'] ) {
			$parsed = self::parse_date( get_post_meta( $post->ID, $settings['source'], true ) );
			if ( $parsed ) {
				$mapped = date_i18n( get_option( 'date_format' ), strtotime( $parsed[0] ) );
				if ( $parsed[1] ) {
					$mapped .= ' · ' . date_i18n( get_option( 'time_format' ), strtotime( $parsed[0] ) );
				}
			}
		}
		$event = ( $post && get_post_meta( $post->ID, self::META_EVENT, true ) ) ? ThreeCal_Event::get( (int) get_post_meta( $post->ID, self::META_EVENT, true ) ) : null;

		wp_localize_script(
			'threecal-post-dates-panel',
			'threecalPostDates',
			array(
				'postType'  => $screen->post_type,
				'label'     => self::label( $settings ),
				'source'    => $settings['source'],
				'mapped'    => $mapped,
				'category'  => $category ? $category->name : '',
				'postponed' => $event ? self::postponed_text( $event ) : '',
			)
		);
	}

	/**
	 * Box for the classic editor (hidden in the block editor, which has the panel).
	 *
	 * @param string  $post_type Post type.
	 * @param WP_Post $post      Post.
	 */
	public function add_meta_box( $post_type, $post ) {
		$settings = self::type_settings( $post_type );
		if ( ! $settings ) {
			return;
		}
		add_meta_box(
			'threecal-post-date',
			self::label( $settings ),
			array( $this, 'render_meta_box' ),
			$post_type,
			'side',
			'default',
			array( '__back_compat_meta_box' => true )
		);
	}

	/**
	 * Fields of the classic editor box.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_meta_box( $post ) {
		$settings = self::type_settings( $post->post_type );
		$show     = (string) get_post_meta( $post->ID, self::META_SHOW, true );
		wp_nonce_field( 'threecal_post_date', 'threecal_post_date_nonce' );

		if ( '' !== $settings['source'] ) {
			$parsed = self::parse_date( get_post_meta( $post->ID, $settings['source'], true ) );
			echo '<p>';
			if ( $parsed ) {
				echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $parsed[0] ) ) );
			} else {
				esc_html_e( 'No date yet.', '3task-calendar' );
			}
			echo '<br><span class="description">';
			/* translators: %s: name of the custom field */
			echo esc_html( sprintf( __( 'Taken from the field %s.', '3task-calendar' ), $settings['source'] ) );
			echo '</span></p>';
			echo '<input type="hidden" name="threecal_mode" value="source">';
			echo '<label><input type="checkbox" name="threecal_hide" value="1" ' . checked( '0', $show, false ) . '> ' . esc_html__( 'Do not show in the calendar', '3task-calendar' ) . '</label>';
			return;
		}

		$start   = (string) get_post_meta( $post->ID, self::META_START, true );
		$end     = (string) get_post_meta( $post->ID, self::META_END, true );
		$all_day = '0' !== (string) get_post_meta( $post->ID, self::META_ALL_DAY, true );
		?>
		<input type="hidden" name="threecal_mode" value="own">
		<p><label><input type="checkbox" name="threecal_show" value="1" <?php checked( '1', $show ); ?>> <?php esc_html_e( 'Show in the calendar', '3task-calendar' ); ?></label></p>
		<p>
			<label for="threecal_start_date"><?php esc_html_e( 'Date', '3task-calendar' ); ?></label><br>
			<input type="date" id="threecal_start_date" name="threecal_start_date" value="<?php echo esc_attr( substr( $start, 0, 10 ) ); ?>">
			<input type="time" name="threecal_start_time" value="<?php echo esc_attr( (string) substr( $start, 11, 5 ) ); ?>" aria-label="<?php esc_attr_e( 'Time', '3task-calendar' ); ?>">
		</p>
		<p><label><input type="checkbox" name="threecal_all_day" value="1" <?php checked( $all_day ); ?>> <?php esc_html_e( 'All day', '3task-calendar' ); ?></label></p>
		<p>
			<label for="threecal_end_date"><?php esc_html_e( 'End (optional)', '3task-calendar' ); ?></label><br>
			<input type="date" id="threecal_end_date" name="threecal_end_date" value="<?php echo esc_attr( substr( $end, 0, 10 ) ); ?>">
			<input type="time" name="threecal_end_time" value="<?php echo esc_attr( (string) substr( $end, 11, 5 ) ); ?>" aria-label="<?php esc_attr_e( 'End time', '3task-calendar' ); ?>">
		</p>
		<?php
	}

	/**
	 * Save the classic editor box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['threecal_post_date_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['threecal_post_date_nonce'] ) ), 'threecal_post_date' ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! self::type_settings( $post->post_type ) ) {
			return;
		}

		$mode = isset( $_POST['threecal_mode'] ) ? sanitize_key( wp_unslash( $_POST['threecal_mode'] ) ) : '';
		if ( 'source' === $mode ) {
			update_post_meta( $post_id, self::META_SHOW, empty( $_POST['threecal_hide'] ) ? '' : '0' );
			return;
		}

		$all_day = ! empty( $_POST['threecal_all_day'] );
		$date    = isset( $_POST['threecal_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['threecal_start_date'] ) ) : '';
		$time    = isset( $_POST['threecal_start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['threecal_start_time'] ) ) : '';
		$e_date  = isset( $_POST['threecal_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['threecal_end_date'] ) ) : '';
		$e_time  = isset( $_POST['threecal_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['threecal_end_time'] ) ) : '';

		update_post_meta( $post_id, self::META_SHOW, empty( $_POST['threecal_show'] ) ? '0' : '1' );
		update_post_meta( $post_id, self::META_ALL_DAY, $all_day ? '1' : '0' );
		update_post_meta( $post_id, self::META_START, self::sanitize_meta( ( $all_day || '' === $time ) ? $date : $date . ' ' . $time ) );
		update_post_meta( $post_id, self::META_END, self::sanitize_meta( ( $all_day || '' === $e_time ) ? $e_date : $e_date . ' ' . $e_time ) );
	}

	/* ---------------------------------------------------------------------
	 * Admin column
	 * ------------------------------------------------------------------ */

	/**
	 * Add the date column to the post lists.
	 */
	public function register_columns() {
		foreach ( array_keys( self::enabled_types() ) as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'sortable_column' ) );
		}
	}

	/**
	 * Column header.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$settings = self::type_settings( get_current_screen() ? get_current_screen()->post_type : '' );
		$new      = array();
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$new['threecal_date'] = self::label( $settings );
			}
			$new[ $key ] = $label;
		}
		if ( ! isset( $new['threecal_date'] ) ) {
			$new['threecal_date'] = self::label( $settings );
		}
		return $new;
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'threecal_date' !== $column ) {
			return;
		}
		$sort = (string) get_post_meta( $post_id, self::META_SORT, true );
		if ( '' === $sort ) {
			echo '<span aria-hidden="true">&#8212;</span>';
			return;
		}
		$event = ThreeCal_Event::get( (int) get_post_meta( $post_id, self::META_EVENT, true ) );
		$time  = strtotime( $sort );
		echo esc_html( date_i18n( get_option( 'date_format' ), $time ) );
		if ( $event && ! $event->all_day ) {
			echo '<br>' . esc_html( date_i18n( get_option( 'time_format' ), $time ) );
		}
		$postponed = self::postponed_text( $event );
		if ( $postponed ) {
			echo '<br><em>' . esc_html( $postponed ) . '</em>';
		}
	}

	/**
	 * Make the column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function sortable_column( $columns ) {
		$columns['threecal_date'] = 'threecal_date';
		return $columns;
	}

	/**
	 * Sort the post list by date. Posts with a date come first, the others
	 * follow, in both directions.
	 *
	 * A meta query with "EXISTS or NOT EXISTS" is not used on purpose: it
	 * joins the meta table in a way that gives posts without a date a random
	 * sort value.
	 *
	 * @param array    $clauses Query clauses.
	 * @param WP_Query $query   Query.
	 * @return array
	 */
	public function sort_by_date( $clauses, $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'threecal_date' !== $query->get( 'orderby' ) ) {
			return $clauses;
		}
		global $wpdb;
		$order = 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ? 'DESC' : 'ASC';

		$clauses['join']   .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS threecal_sort ON ( {$wpdb->posts}.ID = threecal_sort.post_id AND threecal_sort.meta_key = %s )", self::META_SORT );
		$clauses['orderby'] = "( threecal_sort.meta_value IS NULL ) ASC, threecal_sort.meta_value {$order}, {$wpdb->posts}.post_date DESC";
		return $clauses;
	}

	/* ---------------------------------------------------------------------
	 * Date box in the post
	 * ------------------------------------------------------------------ */

	/**
	 * Register the block "Date of this post".
	 */
	private function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		wp_register_script(
			'threecal-post-date-block',
			THREECAL_PLUGIN_URL . 'blocks/post-date-block/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render', 'wp-data' ),
			threecal_asset_version( 'blocks/post-date-block/index.js' ),
			true
		);
		wp_set_script_translations( 'threecal-post-date-block', '3task-calendar', THREECAL_PLUGIN_DIR . 'languages' );

		register_block_type(
			'threecal/post-date',
			array(
				'api_version'     => 3,
				'editor_script'   => 'threecal-post-date-block',
				'editor_style'    => 'threecal-public',
				'render_callback' => array( $this, 'render_block' ),
				'uses_context'    => array( 'postId' ),
				'attributes'      => array(
					'theme' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'        => array( 'html' => false ),
			)
		);
	}

	/**
	 * Block output.
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public function render_block( $attributes, $content = '', $block = null ) {
		$post_id = ( $block && isset( $block->context['postId'] ) ) ? (int) $block->context['postId'] : (int) get_the_ID();
		return self::box( $post_id, isset( $attributes['theme'] ) ? $attributes['theme'] : '' );
	}

	/**
	 * Shortcode [threecal_post_date id="" theme=""].
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'theme' => '',
			),
			$atts,
			'threecal_post_date'
		);
		return self::box( absint( $atts['id'] ) ? absint( $atts['id'] ) : (int) get_the_ID(), $atts['theme'] );
	}

	/**
	 * Automatic box at the start or end of a post.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function auto_box( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post     = get_post();
		$settings = $post ? self::type_settings( $post->post_type ) : null;
		if ( ! $settings || 'none' === $settings['box'] ) {
			return $content;
		}
		if ( has_block( 'threecal/post-date', $post ) || has_shortcode( $post->post_content, 'threecal_post_date' ) ) {
			return $content;
		}
		$box = self::box( $post->ID );
		return 'before' === $settings['box'] ? $box . $content : $content . $box;
	}

	/**
	 * Box with the date of a post and "Add to my calendar".
	 *
	 * @param int    $post_id Post ID.
	 * @param string $theme   Design.
	 * @return string
	 */
	public static function box( $post_id, $theme = '' ) {
		$event_id = (int) get_post_meta( $post_id, self::META_EVENT, true );
		$event    = $event_id ? ThreeCal_Event::get( $event_id ) : null;
		if ( ! $event || ! $event->is_visible() ) {
			return '';
		}

		wp_enqueue_style( 'threecal-public' );
		$theme    = ThreeCal_Themes::normalize( $theme );
		$settings = self::type_settings( get_post_type( $post_id ) );
		$start    = strtotime( $event->start_date );
		$when     = date_i18n( 'l', $start ) . ', ' . date_i18n( get_option( 'date_format' ), $start );
		if ( ! $event->all_day ) {
			$when .= ' · ' . date_i18n( get_option( 'time_format' ), $start );
		}
		$postponed = self::postponed_text( $event );
		$cancelled = 'cancelled' === $event->status;

		ob_start();
		?>
		<div class="threecal-post-date threecal-theme-<?php echo esc_attr( $theme ); ?><?php echo $cancelled ? ' threecal-is-cancelled' : ''; ?>" style="<?php echo esc_attr( ThreeCal_Themes::wrapper_style( $theme ) ); ?>">
			<span class="threecal-post-date-icon" aria-hidden="true"><?php ThreeCal_Icons::render( 'calendar-event', 26 ); ?></span>
			<span class="threecal-post-date-text">
				<span class="threecal-post-date-label"><?php echo esc_html( self::label( $settings ) ); ?></span>
				<span class="threecal-post-date-value"><?php echo ThreeCal_Calendar_Renderer::cancelled_badge( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in cancelled_badge(). ?><?php echo esc_html( $when ); ?></span>
				<?php if ( $postponed ) : ?>
				<span class="threecal-postponed"><?php echo esc_html( $postponed ); ?></span>
				<?php endif; ?>
			</span>
			<?php if ( ! $cancelled ) : ?>
			<a class="threecal-post-date-ics" href="<?php echo esc_url( ThreeCal_ICS::event_url( $event->id ) ); ?>" download>
				<?php ThreeCal_Icons::render( 'calendar-plus', 18 ); ?>
				<span><?php esc_html_e( 'Add to my calendar', '3task-calendar' ); ?></span>
			</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------------
	 * Existing fields and bulk sync
	 * ------------------------------------------------------------------ */

	/**
	 * Custom fields of a post type that hold dates, most used first.
	 *
	 * @param string $post_type Post type.
	 * @return array List of array( key, count, sample ).
	 */
	public static function detect_fields( $post_type ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin settings screen, read once.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_key, COUNT(*) AS n, MAX(pm.meta_value) AS sample
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status NOT IN ('trash', 'auto-draft')
				AND pm.meta_value <> ''
				AND ( pm.meta_value LIKE %s OR pm.meta_value LIKE %s OR pm.meta_value LIKE %s OR ( LENGTH(pm.meta_value) = 8 AND pm.meta_value BETWEEN %s AND %s ) )
				GROUP BY pm.meta_key
				ORDER BY n DESC
				LIMIT 60",
				$post_type,
				'____-__-__%',
				'__.__.____%',
				'_.__.____%',
				'19000101',
				'21001231'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$skip   = array( '_edit_lock', '_edit_last', '_wp_old_date', '_wp_old_slug' );
		$fields = array();
		foreach ( (array) $rows as $row ) {
			$key = (string) $row->meta_key;
			if ( in_array( $key, $skip, true ) || 0 === strpos( $key, '_threecal_' ) || 0 === strpos( $key, '_oembed' ) || 0 === strpos( $key, '_transient' ) ) {
				continue;
			}
			if ( ! self::parse_date( $row->sample ) ) {
				continue;
			}
			$fields[] = array(
				'key'    => $key,
				'count'  => (int) $row->n,
				'sample' => (string) $row->sample,
			);
		}
		return $fields;
	}

	/**
	 * Number of posts of a type that are in the calendar.
	 *
	 * @param string $post_type Post type.
	 * @return int
	 */
	public static function linked_count( $post_type ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count for the settings screen.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key = %s", $post_type, self::META_EVENT ) );
	}

	/**
	 * Sync the posts of a type in steps (settings screen, "Apply to existing posts").
	 */
	public function ajax_sync() {
		check_ajax_referer( 'threecal_post_dates', 'nonce' );
		if ( ! current_user_can( 'threecal_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '3task-calendar' ) ), 403 );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$offset    = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		if ( ! post_type_exists( $post_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown post type.', '3task-calendar' ) ) );
		}

		$statuses = array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' );
		$counts   = wp_count_posts( $post_type );
		$total    = 0;
		foreach ( $statuses as $status ) {
			$total += isset( $counts->$status ) ? (int) $counts->$status : 0;
		}

		$step = 50;
		$ids  = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => $statuses,
				'fields'           => 'ids',
				'posts_per_page'   => $step,
				'offset'           => $offset,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
			)
		);
		foreach ( $ids as $id ) {
			self::sync( $id );
		}

		wp_send_json_success(
			array(
				'done'     => min( $total, $offset + count( $ids ) ),
				'total'    => $total,
				'finished' => count( $ids ) < $step,
				'linked'   => self::linked_count( $post_type ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Settings screen (tab "Posts")
	 * ------------------------------------------------------------------ */

	/**
	 * Settings screen: which post types get a date, field label, category, source.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'threecal_settings' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to change the calendar settings.', '3task-calendar' ) . '</p></div>';
			return;
		}

		$resync = array();
		if ( isset( $_POST['threecal_save_post_dates'] ) ) {
			check_admin_referer( 'threecal_post_dates_save' );
			$before = self::settings();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned field by field in sanitize().
			$after = self::sanitize( isset( $_POST['threecal_post_dates'] ) ? wp_unslash( $_POST['threecal_post_dates'] ) : array() );
			update_option( self::OPTION, $after, false );
			self::$watch = null;

			// Post types whose posts need a sync: switched on or off, other field or other category.
			foreach ( array_unique( array_merge( array_keys( $before['types'] ), array_keys( $after['types'] ) ) ) as $post_type ) {
				$old = isset( $before['types'][ $post_type ] ) ? $before['types'][ $post_type ] : null;
				$new = isset( $after['types'][ $post_type ] ) ? $after['types'][ $post_type ] : null;
				if ( ! $old || ! $new || $old['source'] !== $new['source'] || (int) $old['category'] !== (int) $new['category'] ) {
					if ( $new || self::linked_count( $post_type ) ) {
						$resync[] = $post_type;
					}
				}
			}

			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved!', '3task-calendar' );
			if ( $resync ) {
				echo ' ' . esc_html__( 'The posts are being checked now, please keep this page open for a moment.', '3task-calendar' );
			}
			echo '</p></div>';
		}

		$settings   = self::settings();
		$categories = ThreeCal_Category::get_all();
		$types      = self::available_types();

		wp_enqueue_script( 'threecal-post-dates-settings', THREECAL_PLUGIN_URL . 'admin/js/post-dates-settings.js', array(), threecal_asset_version( 'admin/js/post-dates-settings.js' ), true );

		$i18n = array(
			/* translators: 1: posts done, 2: all posts */
			'progress' => __( '%1$s of %2$s posts checked', '3task-calendar' ),
			'done'     => __( 'Done.', '3task-calendar' ),
			'error'    => __( 'That did not work. Please reload the page and try again.', '3task-calendar' ),
		);
		?>
		<div class="threecal-post-dates-settings"
			data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'threecal_post_dates' ) ); ?>"
			data-resync="<?php echo esc_attr( implode( ',', $resync ) ); ?>"
			data-i18n="<?php echo esc_attr( wp_json_encode( $i18n ) ); ?>">

			<div class="threecal-card">
				<h3><?php esc_html_e( 'Dates from posts', '3task-calendar' ); ?></h3>
				<p><?php esc_html_e( 'Set a date while you write a post, for example the cinema release of a film, a release date or a match day. The post then appears in the calendar by itself and stays in sync: title, link, image and date come from the post, a draft or a deleted post disappears from the calendar.', '3task-calendar' ); ?></p>
				<p class="description"><?php esc_html_e( 'The date can also come from a field you already use, for example from Advanced Custom Fields or another plugin. Then nothing has to be entered twice.', '3task-calendar' ); ?></p>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'threecal_post_dates_save' ); ?>

				<?php
				foreach ( $types as $post_type => $object ) :
					$row     = isset( $settings['types'][ $post_type ] ) ? $settings['types'][ $post_type ] : array();
					$on      = ! empty( $row['enabled'] );
					$label   = isset( $row['label'] ) ? $row['label'] : '';
					$cat     = isset( $row['category'] ) ? (int) $row['category'] : 0;
					$source  = isset( $row['source'] ) ? $row['source'] : '';
					$box     = isset( $row['box'] ) ? $row['box'] : 'none';
					$fields  = self::detect_fields( $post_type );
					$keys    = wp_list_pluck( $fields, 'key' );
					$custom  = ( '' !== $source && ! in_array( $source, $keys, true ) );
					$name    = 'threecal_post_dates[types][' . $post_type . ']';
					$id_base = 'threecal-pd-' . $post_type;
					?>
				<div class="threecal-card threecal-post-type-card<?php echo $on ? ' is-on' : ''; ?>">
					<h3><?php echo esc_html( $object->labels->name ); ?></h3>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Date in the editor', '3task-calendar' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $on ); ?>>
									<?php
									/* translators: %s: post type name, for example "Posts" */
									echo esc_html( sprintf( __( 'Offer a date for %s', '3task-calendar' ), $object->labels->name ) );
									?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $id_base . '-label' ); ?>"><?php esc_html_e( 'Name of the field', '3task-calendar' ); ?></label></th>
							<td>
								<input type="text" class="regular-text" id="<?php echo esc_attr( $id_base . '-label' ); ?>" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Event date', '3task-calendar' ); ?>" maxlength="60">
								<p class="description"><?php esc_html_e( 'For example "Cinema release", "Release date" or "Match day". Appears in the editor, in the post list and in the box in the post.', '3task-calendar' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $id_base . '-cat' ); ?>"><?php esc_html_e( 'Calendar category', '3task-calendar' ); ?></label></th>
							<td>
								<select id="<?php echo esc_attr( $id_base . '-cat' ); ?>" name="<?php echo esc_attr( $name ); ?>[category]">
									<option value="0"><?php esc_html_e( 'No category', '3task-calendar' ); ?></option>
									<?php foreach ( $categories as $category ) : ?>
									<option value="<?php echo esc_attr( $category->id ); ?>" <?php selected( $cat, (int) $category->id ); ?>><?php echo esc_html( $category->name ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Gives the dates their color and lets you show them in a calendar of their own. Release dates are not events for Google: tick "Not an event for search engines" in this category.', '3task-calendar' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $id_base . '-source' ); ?>"><?php esc_html_e( 'Date comes from', '3task-calendar' ); ?></label></th>
							<td>
								<select id="<?php echo esc_attr( $id_base . '-source' ); ?>" class="threecal-source-select" name="<?php echo esc_attr( $name ); ?>[source]">
									<option value="" <?php selected( $source, '' ); ?>><?php esc_html_e( 'Its own field in the editor', '3task-calendar' ); ?></option>
									<?php foreach ( $fields as $field ) : ?>
									<option value="<?php echo esc_attr( $field['key'] ); ?>" <?php selected( $source, $field['key'] ); ?>>
										<?php
										/* translators: 1: name of the custom field, 2: number of posts, 3: example value */
										echo esc_html( sprintf( _n( '%1$s (%2$s post, e.g. %3$s)', '%1$s (%2$s posts, e.g. %3$s)', $field['count'], '3task-calendar' ), $field['key'], number_format_i18n( $field['count'] ), $field['sample'] ) );
										?>
									</option>
									<?php endforeach; ?>
									<option value="__custom" <?php selected( $custom ); ?>><?php esc_html_e( 'Another field …', '3task-calendar' ); ?></option>
								</select>
								<input type="text" class="regular-text threecal-source-custom" name="<?php echo esc_attr( $name ); ?>[source_custom]" value="<?php echo esc_attr( $custom ? $source : '' ); ?>" placeholder="<?php esc_attr_e( 'Name of the custom field', '3task-calendar' ); ?>" aria-label="<?php esc_attr_e( 'Name of the custom field', '3task-calendar' ); ?>" <?php echo $custom ? '' : 'hidden'; ?>>
								<p class="description"><?php esc_html_e( 'With an existing field every post with a date is in the calendar, a switch in the editor takes a single post out. Dates such as 2026-10-01, 20261001 (ACF), 01.10.2026 and Unix timestamps are read. The list shows the fields found with their number and an example, so the right one is easy to spot.', '3task-calendar' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $id_base . '-box' ); ?>"><?php esc_html_e( 'Box in the post', '3task-calendar' ); ?></label></th>
							<td>
								<select id="<?php echo esc_attr( $id_base . '-box' ); ?>" name="<?php echo esc_attr( $name ); ?>[box]">
									<option value="none" <?php selected( $box, 'none' ); ?>><?php esc_html_e( 'Only where I add the block or shortcode', '3task-calendar' ); ?></option>
									<option value="before" <?php selected( $box, 'before' ); ?>><?php esc_html_e( 'Automatically at the start of the post', '3task-calendar' ); ?></option>
									<option value="after" <?php selected( $box, 'after' ); ?>><?php esc_html_e( 'Automatically at the end of the post', '3task-calendar' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Shows the date with an "Add to my calendar" button. Block "Date of this post" or shortcode [threecal_post_date].', '3task-calendar' ); ?></p>
							</td>
						</tr>
						<?php if ( $on || self::linked_count( $post_type ) ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'In the calendar', '3task-calendar' ); ?></th>
							<td>
								<p>
									<?php
									$linked = self::linked_count( $post_type );
									echo '<strong class="threecal-linked-count" data-type="' . esc_attr( $post_type ) . '">' . esc_html( number_format_i18n( $linked ) ) . '</strong> ' . esc_html( 1 === $linked ? $object->labels->singular_name : $object->labels->name );
									?>
								</p>
								<p>
									<button type="button" class="button threecal-sync-btn" data-type="<?php echo esc_attr( $post_type ); ?>"><?php esc_html_e( 'Apply to existing posts', '3task-calendar' ); ?></button>
									<span class="threecal-sync-progress" data-type="<?php echo esc_attr( $post_type ); ?>" role="status"></span>
								</p>
								<p class="description"><?php esc_html_e( 'Goes through all posts once and puts every date into the calendar. Takes a few seconds on large sites.', '3task-calendar' ); ?></p>
							</td>
						</tr>
						<?php endif; ?>
					</table>
				</div>
				<?php endforeach; ?>

				<div class="threecal-card">
					<h3><?php esc_html_e( 'Postponed dates', '3task-calendar' ); ?></h3>
					<label>
						<input type="checkbox" name="threecal_post_dates[postponed]" value="1" <?php checked( $settings['postponed'] ); ?>>
						<?php esc_html_e( 'Show "Postponed, previously …" when the date of a published post moves', '3task-calendar' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Release dates often move. Calendar subscribers get the new date automatically either way.', '3task-calendar' ); ?></p>
				</div>

				<div class="threecal-card">
					<h3><?php esc_html_e( 'Showing the dates', '3task-calendar' ); ?></h3>
					<p><?php esc_html_e( 'Dates from posts appear in every calendar. A calendar or list of their own uses the category:', '3task-calendar' ); ?></p>
					<code class="threecal-code-block">[threecal category="1"]</code>
					<code class="threecal-code-block">[threecal_events view="poster" category="1" columns="4"]</code>
					<code class="threecal-code-block">[threecal_upcoming category="1" limit="5"]</code>
					<p class="description"><?php esc_html_e( 'Replace 1 with the ID of the category, it is shown in the Categories tab. The poster view shows the post images as large tiles, for example for "This week at the cinema".', '3task-calendar' ); ?></p>
				</div>

				<p class="submit">
					<button type="submit" name="threecal_save_post_dates" class="button button-primary"><?php esc_html_e( 'Save Settings', '3task-calendar' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}
}
