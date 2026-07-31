<?php
/**
 * Page-cache integration.
 *
 * Full-page caching plugins (WP Rocket and derivatives such as AccelerateWP,
 * LiteSpeed Cache, W3 Total Cache, WP Super Cache, WP Fastest Cache,
 * SiteGround Speed Optimizer, Cache Enabler, Hummingbird, Breeze, WP Engine)
 * hold whole HTML pages that contain the rendered program cards. This class
 * asks whichever of those is active to drop its cache when program content
 * or display settings change, and additionally drops minified/aggregated
 * asset caches when the plugin version changes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_Cache {

	const VERSION_OPTION = 'pp_installed_version';

	/** @var bool Whether a purge is already queued for shutdown. */
	private static $queued = false;

	public static function init() {
		add_action( 'save_post_' . PP_CPT::POST_TYPE, array( __CLASS__, 'queue_purge' ) );
		add_action( 'deleted_post', array( __CLASS__, 'on_delete' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'on_status_change' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_status_change' ) );

		// Display settings (homepage injection, layout) change rendered pages.
		add_action( 'update_option_pp_settings', array( __CLASS__, 'queue_purge' ) );

		// Renaming, creating, or deleting a program group changes section
		// headings and groups="all" displays.
		add_action( 'created_' . PP_CPT::TAXONOMY, array( __CLASS__, 'queue_purge' ) );
		add_action( 'edited_' . PP_CPT::TAXONOMY, array( __CLASS__, 'queue_purge' ) );
		add_action( 'delete_' . PP_CPT::TAXONOMY, array( __CLASS__, 'queue_purge' ) );

		// A new plugin version means new CSS/JS; cached pages reference the
		// old ?ver= URLs and minifiers cache bundles built from them.
		add_action( 'init', array( __CLASS__, 'maybe_purge_on_upgrade' ), 20 );
	}

	/**
	 * @param int          $post_id Deleted post ID.
	 * @param WP_Post|null $post    Deleted post object (WP 5.5+).
	 */
	public static function on_delete( $post_id, $post = null ) {
		if ( $post && PP_CPT::POST_TYPE === $post->post_type ) {
			self::queue_purge();
		}
	}

	/**
	 * @param int $post_id Trashed/untrashed post ID.
	 */
	public static function on_status_change( $post_id ) {
		if ( PP_CPT::POST_TYPE === get_post_type( $post_id ) ) {
			self::queue_purge();
		}
	}

	/**
	 * Queue a single page-cache purge for the end of this request; the static
	 * flag collapses bulk edits and imports into one purge.
	 */
	public static function queue_purge() {
		if ( self::$queued ) {
			return;
		}
		self::$queued = true;
		add_action( 'shutdown', array( __CLASS__, 'purge_page_caches' ) );
	}

	/**
	 * Purge everything once per plugin upgrade, however the files got there
	 * (zip upload, deploy, FTP) — activation hooks don't fire on all of them.
	 */
	public static function maybe_purge_on_upgrade() {
		if ( PP_VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}
		update_option( self::VERSION_OPTION, PP_VERSION );
		self::purge_page_caches();
		self::purge_asset_caches();
	}

	/**
	 * Ask the active caching plugin(s) to drop their page caches. Guarded
	 * calls only — absent plugins cost one function_exists each, and the
	 * do_action calls are no-ops unless the plugin registered a listener.
	 */
	public static function purge_page_caches() {
		// WP Rocket and white-label derivatives (AccelerateWP).
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// LiteSpeed Cache.
		do_action( 'litespeed_purge_all' );

		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_posts' ) ) {
			w3tc_flush_posts();
		} elseif ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// WP Super Cache.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// WP Fastest Cache.
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache();
		}

		// SiteGround Speed Optimizer.
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		// Cache Enabler.
		do_action( 'cache_enabler_clear_complete_cache' );

		// Hummingbird.
		do_action( 'wphb_clear_page_cache' );

		// Breeze (Cloudways).
		do_action( 'breeze_clear_all_cache' );

		// WP Engine platform cache.
		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
			WpeCommon::purge_varnish_cache();
		}

		// Site-specific setups can hook their own purge here.
		do_action( 'parish_programs_purge_page_cache' );
	}

	/**
	 * Drop minified/aggregated CSS+JS caches. Only needed when the plugin's
	 * own assets change, i.e. on version change — content edits reuse the
	 * same bundles.
	 */
	public static function purge_asset_caches() {
		// WP Rocket / AccelerateWP minified bundles and cache-busting copies.
		if ( function_exists( 'rocket_clean_minify' ) ) {
			rocket_clean_minify();
		}
		if ( function_exists( 'rocket_clean_cache_busting' ) ) {
			rocket_clean_cache_busting();
		}

		// Autoptimize aggregated CSS/JS.
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			autoptimizeCache::clearall();
		}

		// LiteSpeed generated CSS/JS.
		do_action( 'litespeed_purge_cssjs' );

		do_action( 'parish_programs_purge_asset_cache' );
	}
}
