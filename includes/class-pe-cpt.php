<?php
/**
 * Custom post type parish_event and custom post status pe_removed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_CPT {

	const POST_TYPE      = 'parish_event';
	const STATUS_REMOVED = 'pe_removed';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'               => __( 'Parish Events', 'parish-events' ),
					'singular_name'      => __( 'Parish Event', 'parish-events' ),
					'add_new_item'       => __( 'Add New Parish Event', 'parish-events' ),
					'edit_item'          => __( 'Edit Parish Event', 'parish-events' ),
					'new_item'           => __( 'New Parish Event', 'parish-events' ),
					'view_item'          => __( 'View Parish Event', 'parish-events' ),
					'search_items'       => __( 'Search Parish Events', 'parish-events' ),
					'not_found'          => __( 'No parish events found', 'parish-events' ),
					'not_found_in_trash' => __( 'No parish events found in Trash', 'parish-events' ),
					'menu_name'          => __( 'Parish Events', 'parish-events' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'rewrite'      => array(
					'slug'       => 'events',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				// Enables the block editor. Event meta stays out of REST via
				// its own show_in_rest flags.
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-calendar-alt',
			)
		);

		// 'public' so the single page still resolves for logged-out visitors
		// during the cancelled grace window (WP_Query blanks non-public
		// statuses on singular requests). Search, archives, feeds, and the
		// plugin's own queries never surface this status, and
		// PE_Content::gone_after_grace() 410s the page once grace expires.
		register_post_status(
			self::STATUS_REMOVED,
			array(
				'label'                     => __( 'Removed upstream', 'parish-events' ),
				'public'                    => true,
				'internal'                  => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of posts. */
				'label_count'               => _n_noop(
					'Removed upstream <span class="count">(%s)</span>',
					'Removed upstream <span class="count">(%s)</span>',
					'parish-events'
				),
			)
		);

		self::register_meta();
	}

	private static function register_meta() {
		$string_keys = array(
			'_pe_uid',
			'_pe_ccb_event_id',
			'_pe_event_date',
			'_pe_start_time',
			'_pe_end_time',
			'_pe_all_day',
			'_pe_event_type',
			'_pe_location_raw',
			'_pe_location',
			'_pe_group_name',
			'_pe_group_ccb_id',
			'_pe_group_type',
			'_pe_grouping_name',
			'_pe_leader_name',
			'_pe_leader_ccb_id',
			'_pe_leader_phone',
			'_pe_leader_email',
			'_pe_import_hash',
			'_pe_field_hashes',
			'_pe_override',
			'_pe_featured',
			'_pe_cancelled',
			'_pe_sync_note',
		);
		foreach ( $string_keys as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => '__return_false',
				)
			);
		}

		// Set by reconcile when a linked suppression rule delists the post;
		// its permalink 301s here for saved/indexed links.
		register_post_meta(
			self::POST_TYPE,
			'_pe_redirect_url',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => '__return_false',
			)
		);

		// Admin-owned like the featured image: imports never write it, and it
		// is editable regardless of the override flag.
		register_post_meta(
			self::POST_TYPE,
			'_pe_video_url',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => '__return_false',
			)
		);

		foreach ( array( '_pe_last_seen', '_pe_removed_at' ) as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => 'integer',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => '__return_false',
				)
			);
		}
	}
}
