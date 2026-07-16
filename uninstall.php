<?php
/**
 * Uninstall handler. Options and cron are always removed; event posts are
 * only deleted when the "delete data on uninstall" setting is checked.
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
