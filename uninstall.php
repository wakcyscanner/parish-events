<?php
/**
 * Uninstall handler. Options and cron are always removed; posts are only
 * deleted when the relevant "delete data on uninstall" setting is checked.
 *
 * Covers both features this plugin ships: parish_event (synced calendar) and
 * parish_program (hand-authored programs). They keep separate settings, so the
 * two delete-data checkboxes are honoured independently.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'pe_import_cron' );

$pe_settings = get_option( 'pe_settings', array() );
$pe_delete   = is_array( $pe_settings ) && ! empty( $pe_settings['delete_data_on_uninstall'] );

if ( $pe_delete ) {
	global $wpdb;
	// Direct query: the plugin isn't loaded during uninstall, so the custom
	// pe_removed status isn't registered and WP_Query would skip those posts.
	$pe_posts = $wpdb->get_col(
		$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'parish_event' )
	);
	foreach ( $pe_posts as $pe_post_id ) {
		wp_delete_post( (int) $pe_post_id, true );
	}
}

delete_option( 'pe_settings' );
delete_option( 'pe_run_log' );
delete_option( 'pe_cache_ver' );
delete_option( 'pe_installed_version' );
delete_transient( 'pe_update_check' );
delete_option( 'pe_import_lock' );
delete_option( 'pe_linked_occurrences' );
delete_option( 'pe_fail_streak' );
delete_option( 'pe_alert_active' );

// Fragment-cache transients (they expire on their own, but leave nothing behind).
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_pe\_frag\_%' OR option_name LIKE '\_transient\_timeout\_pe\_frag\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

/*
 * Parish Programs.
 */

$pp_settings = get_option( 'pp_settings', array() );
$pp_delete   = is_array( $pp_settings ) && ! empty( $pp_settings['delete_data_on_uninstall'] );

if ( $pp_delete ) {
	$pp_posts = get_posts(
		array(
			'post_type'      => 'parish_program',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $pp_posts as $pp_post_id ) {
		wp_delete_post( (int) $pp_post_id, true );
	}

	// Program group terms outlive their posts; the taxonomy isn't registered
	// during uninstall, so read them straight from the term tables.
	$pp_term_ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'pp_group' )
	);
	foreach ( $pp_term_ids as $pp_term_id ) {
		wp_delete_term( (int) $pp_term_id, 'pp_group' );
	}
}

delete_option( 'pp_settings' );
delete_option( 'pp_installed_version' );
delete_transient( 'pp_update_check' );
