<?php
/**
 * 3task Calendar Category Model
 *
 * Handles all category-related database operations.
 *
 * @package ThreeCal
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Category class.
 */
class ThreeCal_Category {

	/**
	 * Category properties
	 */
	public $id;
	public $name;
	public $slug;
	public $description;
	public $color;
	public $parent_id;
	public $sort_order;
	public $created_at;

	/**
	 * Table name
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'threecal_categories';
	}

	/**
	 * Constructor
	 *
	 * @param object|null $data Category data.
	 */
	public function __construct( $data = null ) {
		if ( $data ) {
			$this->populate( $data );
		}
	}

	/**
	 * Populate from database row
	 *
	 * @param object $data Database row.
	 */
	private function populate( $data ) {
		$this->id          = isset( $data->id ) ? absint( $data->id ) : 0;
		$this->name        = isset( $data->name ) ? $data->name : '';
		$this->slug        = isset( $data->slug ) ? $data->slug : '';
		$this->description = isset( $data->description ) ? $data->description : '';
		$this->color       = isset( $data->color ) ? $data->color : '#3788d8';
		$this->parent_id   = isset( $data->parent_id ) ? absint( $data->parent_id ) : 0;
		$this->sort_order  = isset( $data->sort_order ) ? absint( $data->sort_order ) : 0;
		$this->created_at  = isset( $data->created_at ) ? $data->created_at : '';
	}

	/**
	 * Get category by ID
	 *
	 * @param int $id Category ID.
	 * @return ThreeCal_Category|null
	 */
	public static function get( $id ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE id = %d',
				$id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! $row ) {
			return null;
		}

		return new self( $row );
	}

	/**
	 * Get category by slug
	 *
	 * @param string $slug Category slug.
	 * @return ThreeCal_Category|null
	 */
	public static function get_by_slug( $slug ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE slug = %s',
				$slug
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! $row ) {
			return null;
		}

		return new self( $row );
	}

	/**
	 * Get all categories
	 *
	 * @param array $args Query arguments.
	 * @return ThreeCal_Category[]
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'parent_id' => null,
			'orderby'   => 'sort_order',
			'order'     => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		// Parent filter.
		if ( $args['parent_id'] !== null ) {
			if ( $args['parent_id'] === 0 ) {
				$where[] = '(parent_id IS NULL OR parent_id = 0)';
			} else {
				$where[]  = 'parent_id = %d';
				$values[] = $args['parent_id'];
			}
		}

		// Build query - table name and orderby are internally generated/validated.
		$sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where );

		// Order - orderby is validated against allowed values.
		$allowed_orderby = array( 'name', 'sort_order', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'sort_order';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';
		$sql            .= " ORDER BY $orderby $order, name ASC";

		// Execute.
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built with prepared placeholders and validated orderby.
			$sql = $wpdb->prepare( $sql, $values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is properly prepared above with validated orderby.
		$rows       = $wpdb->get_results( $sql );
		$categories = array();

		foreach ( $rows as $row ) {
			$categories[] = new self( $row );
		}

		return $categories;
	}

	/**
	 * Get hierarchical categories (for select dropdowns)
	 *
	 * @param int $parent_id Parent ID.
	 * @param int $level     Current level.
	 * @return ThreeCal_Category[]
	 */
	public static function get_hierarchical( $parent_id = 0, $level = 0 ) {
		$categories = self::get_all( array( 'parent_id' => $parent_id ) );
		$result     = array();

		foreach ( $categories as $category ) {
			$category->level = $level;
			$result[]        = $category;

			// Get children.
			$children = self::get_hierarchical( $category->id, $level + 1 );
			$result   = array_merge( $result, $children );
		}

		return $result;
	}

	/**
	 * Get categories count
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is generated internally.
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
	}

	/**
	 * Save category (insert or update)
	 *
	 * @return bool
	 */
	public function save() {
		global $wpdb;

		// Generate slug if empty.
		if ( empty( $this->slug ) ) {
			$this->slug = $this->generate_slug( $this->name );
		}

		$data = array(
			'name'        => $this->name,
			'slug'        => $this->slug,
			'description' => $this->description,
			'color'       => $this->color,
			'parent_id'   => $this->parent_id ? $this->parent_id : null,
			'sort_order'  => $this->sort_order,
		);

		$format = array( '%s', '%s', '%s', '%s', '%d', '%d' );

		if ( $this->id > 0 ) {
			// Update.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for categories.
			$result = $wpdb->update(
				self::table(),
				$data,
				array( 'id' => $this->id ),
				$format,
				array( '%d' )
			);

			return $result !== false;
		} else {
			// Insert.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for categories.
			$result = $wpdb->insert( self::table(), $data, $format );

			if ( $result ) {
				$this->id = $wpdb->insert_id;
				return true;
			}

			return false;
		}
	}

	/**
	 * Delete category
	 *
	 * @return bool
	 */
	public function delete() {
		global $wpdb;

		if ( ! $this->id ) {
			return false;
		}

		// Update children to have no parent.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for categories.
		$wpdb->update(
			self::table(),
			array( 'parent_id' => null ),
			array( 'parent_id' => $this->id ),
			array( '%d' ),
			array( '%d' )
		);

		// Delete event-category relationships.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for event-category relations.
		$wpdb->delete(
			$wpdb->prefix . 'threecal_event_categories',
			array( 'category_id' => $this->id ),
			array( '%d' )
		);

		// Delete category.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for categories.
		return $wpdb->delete(
			self::table(),
			array( 'id' => $this->id ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Generate unique slug
	 *
	 * @param string $name Category name.
	 * @return string
	 */
	private function generate_slug( $name ) {
		$slug          = sanitize_title( $name );
		$original_slug = $slug;
		$counter       = 1;

		while ( $this->slug_exists( $slug, $this->id ) ) {
			$slug = $original_slug . '-' . $counter;
			$counter++;
		}

		return $slug;
	}

	/**
	 * Check if slug exists
	 *
	 * @param string $slug       Slug to check.
	 * @param int    $exclude_id ID to exclude.
	 * @return bool
	 */
	private function slug_exists( $slug, $exclude_id = 0 ) {
		global $wpdb;

		// Table name is generated internally via self::table(), query uses prepare().
		$sql    = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE slug = %s';
		$values = array( $slug );

		if ( $exclude_id > 0 ) {
			$sql     .= ' AND id != %d';
			$values[] = $exclude_id;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built dynamically with prepare() and validated table name.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) ) > 0;
	}

	/**
	 * Get events in this category
	 *
	 * @param array $args Query arguments.
	 * @return ThreeCal_Event[]
	 */
	public function get_events( $args = array() ) {
		$args['category_id'] = $this->id;
		return ThreeCal_Event::get_all( $args );
	}

	/**
	 * Get event count
	 *
	 * @return int
	 */
	public function get_event_count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table prefix used.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . $wpdb->prefix . "threecal_event_categories WHERE category_id = %d",
				$this->id
			)
		);
	}

	/**
	 * Get parent category
	 *
	 * @return ThreeCal_Category|null
	 */
	public function get_parent() {
		if ( ! $this->parent_id ) {
			return null;
		}

		return self::get( $this->parent_id );
	}

	/**
	 * Get child categories
	 *
	 * @return ThreeCal_Category[]
	 */
	public function get_children() {
		return self::get_all( array( 'parent_id' => $this->id ) );
	}
}
