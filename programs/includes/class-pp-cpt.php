<?php
/**
 * Custom post type parish_program.
 *
 * Programs have no single pages of their own — every card links out to a
 * ministry page or registration site — so the post type is UI-only:
 * visible in wp-admin, invisible to front-end queries, no rewrite rules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_CPT {

	const POST_TYPE = 'parish_program';
	const TAXONOMY  = 'pp_group';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Parish Programs', 'parish-programs' ),
					'singular_name'      => __( 'Parish Program', 'parish-programs' ),
					'add_new_item'       => __( 'Add New Program', 'parish-programs' ),
					'edit_item'          => __( 'Edit Program', 'parish-programs' ),
					'new_item'           => __( 'New Program', 'parish-programs' ),
					'search_items'       => __( 'Search Programs', 'parish-programs' ),
					'not_found'          => __( 'No programs found', 'parish-programs' ),
					'not_found_in_trash' => __( 'No programs found in Trash', 'parish-programs' ),
					'menu_name'          => __( 'Programs', 'parish-programs' ),
					'featured_image'     => __( 'Program image', 'parish-programs' ),
					'set_featured_image' => __( 'Set program image', 'parish-programs' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				// Block editor for the description; program meta stays out of
				// REST via its own show_in_rest flags.
				'show_in_rest'    => true,
				// page-attributes exposes the Order box; the grid and carousel
				// sort by it (lowest first), then title. custom-fields is what
				// makes the REST posts controller expose the registered meta —
				// without it there is no `meta` field in wp/v2 responses at
				// all. The editor's Custom Fields panel stays hidden: it's
				// opt-in, and protected (_pp_*) keys never show there anyway.
				'supports'        => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields' ),
				'menu_icon'       => 'dashicons-groups',
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);

		// Category-style groups ("Upcoming Series Events", "Grow in Catholic
		// Community", …) used to filter displays or render one card section
		// per group. Hierarchical so editors get checkboxes, not a tag box.
		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Program Groups', 'parish-programs' ),
					'singular_name' => __( 'Program Group', 'parish-programs' ),
					'add_new_item'  => __( 'Add New Program Group', 'parish-programs' ),
					'edit_item'     => __( 'Edit Program Group', 'parish-programs' ),
					'search_items'  => __( 'Search Program Groups', 'parish-programs' ),
					'not_found'     => __( 'No program groups found', 'parish-programs' ),
					'menu_name'     => __( 'Groups', 'parish-programs' ),
				),
				'hierarchical'      => true,
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				// Needed for the group checkboxes in the block editor and the
				// group dropdown in the Parish Programs block.
				'show_in_rest'      => true,
				'rewrite'           => false,
			)
		);

		self::register_meta();
	}

	private static function register_meta() {
		// REST-writable by anyone who can edit the post (the keys are
		// underscore-protected, so an auth_callback is required for REST
		// writes). Read exposure is fine: every value is printed publicly on
		// the rendered cards anyway. This is what lets an initial card set be
		// created over the REST API on hosts without CLI access.

		// Free-text schedule line, e.g. "Tuesdays · Aug 4 – Oct 6 · 6:30 PM".
		register_post_meta(
			self::POST_TYPE,
			'_pp_date_text',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_pp_link_url',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_pp_link_text',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
			)
		);
	}

	/**
	 * Meta auth: whoever can edit the program can edit its card fields.
	 *
	 * @param bool   $allowed  Unused default.
	 * @param string $meta_key Meta key being written.
	 * @param int    $post_id  Program post ID.
	 * @return bool
	 */
	public static function can_edit_meta( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}
}
