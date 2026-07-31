<?php
/**
 * Page-cache triggers for programs.
 *
 * The purge itself lives in PE_Cache, which already knows how to ask every
 * supported caching plugin to drop its caches. This class only says *when*
 * program content has changed. Both features' triggers converge on the same
 * queue, so a request that touches events and programs still purges once.
 *
 * The upgrade purge is PE_Cache's too: programs ship at the plugin version, so
 * there is no separate version to detect.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_Cache {

	public static function init() {
		add_action( 'save_post_' . PP_CPT::POST_TYPE, array( 'PE_Cache', 'queue_purge' ) );
		add_action( 'deleted_post', array( __CLASS__, 'on_delete' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'on_status_change' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_status_change' ) );

		// Display settings (homepage injection, layout) change rendered pages.
		// add_option too: the first save on a fresh site goes through it.
		add_action( 'update_option_pp_settings', array( 'PE_Cache', 'queue_purge' ) );
		add_action( 'add_option_pp_settings', array( 'PE_Cache', 'queue_purge' ) );

		// Renaming, creating, or deleting a program group changes section
		// headings and groups="all" displays.
		add_action( 'created_' . PP_CPT::TAXONOMY, array( 'PE_Cache', 'queue_purge' ) );
		add_action( 'edited_' . PP_CPT::TAXONOMY, array( 'PE_Cache', 'queue_purge' ) );
		add_action( 'delete_' . PP_CPT::TAXONOMY, array( 'PE_Cache', 'queue_purge' ) );
	}

	/**
	 * @param int          $post_id Deleted post ID.
	 * @param WP_Post|null $post    Deleted post object (WP 5.5+).
	 */
	public static function on_delete( $post_id, $post = null ) {
		if ( $post && PP_CPT::POST_TYPE === $post->post_type ) {
			PE_Cache::queue_purge();
		}
	}

	/**
	 * @param int $post_id Trashed/untrashed post ID.
	 */
	public static function on_status_change( $post_id ) {
		if ( PP_CPT::POST_TYPE === get_post_type( $post_id ) ) {
			PE_Cache::queue_purge();
		}
	}
}
