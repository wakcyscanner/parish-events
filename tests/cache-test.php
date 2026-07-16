<?php
/**
 * Page-cache integration checks. Run with:
 *   npx @wordpress/env run cli -- wp eval-file wp-content/plugins/parish-events/tests/cache-test.php --user=admin
 */

$fail = 0;
function pe_check( $label, $ok ) {
	global $fail;
	if ( ! $ok ) {
		$fail++;
	}
	echo ( $ok ? 'PASS' : 'FAIL' ) . " $label\n";
}

function pe_reset_queue() {
	$prop = new ReflectionProperty( 'PE_Cache', 'queued' );
	$prop->setAccessible( true );
	$prop->setValue( null, false );
	remove_action( 'shutdown', array( 'PE_Cache', 'purge_page_caches' ) );
}

function pe_queued() {
	return false !== has_action( 'shutdown', array( 'PE_Cache', 'purge_page_caches' ) );
}

// --- Stub the cache plugins' purge APIs -------------------------------------
$GLOBALS['pe_t'] = array();
function rocket_clean_domain() {
	$GLOBALS['pe_t']['rocket_domain'] = ( $GLOBALS['pe_t']['rocket_domain'] ?? 0 ) + 1; }
function rocket_clean_minify() {
	$GLOBALS['pe_t']['rocket_minify'] = ( $GLOBALS['pe_t']['rocket_minify'] ?? 0 ) + 1; }
function rocket_clean_cache_busting() {
	$GLOBALS['pe_t']['rocket_busting'] = ( $GLOBALS['pe_t']['rocket_busting'] ?? 0 ) + 1; }
function w3tc_flush_posts() {
	$GLOBALS['pe_t']['w3tc'] = ( $GLOBALS['pe_t']['w3tc'] ?? 0 ) + 1; }
function wp_cache_clear_cache() {
	$GLOBALS['pe_t']['supercache'] = ( $GLOBALS['pe_t']['supercache'] ?? 0 ) + 1; }
function wpfc_clear_all_cache() {
	$GLOBALS['pe_t']['wpfc'] = ( $GLOBALS['pe_t']['wpfc'] ?? 0 ) + 1; }
function sg_cachepress_purge_cache() {
	$GLOBALS['pe_t']['siteground'] = ( $GLOBALS['pe_t']['siteground'] ?? 0 ) + 1; }
foreach ( array( 'litespeed_purge_all', 'cache_enabler_clear_complete_cache', 'wphb_clear_page_cache', 'breeze_clear_all_cache', 'litespeed_purge_cssjs', 'parish_events_purge_page_cache', 'parish_events_purge_asset_cache' ) as $hook ) {
	add_action(
		$hook,
		function () use ( $hook ) {
			$GLOBALS['pe_t'][ $hook ] = ( $GLOBALS['pe_t'][ $hook ] ?? 0 ) + 1;
		}
	);
}

// --- 1. Direct purge calls hit every supported plugin ------------------------
PE_Cache::purge_page_caches();
foreach ( array( 'rocket_domain', 'w3tc', 'supercache', 'wpfc', 'siteground', 'litespeed_purge_all', 'cache_enabler_clear_complete_cache', 'wphb_clear_page_cache', 'breeze_clear_all_cache', 'parish_events_purge_page_cache' ) as $key ) {
	pe_check( "page purge reaches $key", 1 === ( $GLOBALS['pe_t'][ $key ] ?? 0 ) );
}

PE_Cache::purge_asset_caches();
foreach ( array( 'rocket_minify', 'rocket_busting', 'litespeed_purge_cssjs', 'parish_events_purge_asset_cache' ) as $key ) {
	pe_check( "asset purge reaches $key", 1 === ( $GLOBALS['pe_t'][ $key ] ?? 0 ) );
}

// --- 2. Version change purges pages + assets, exactly once --------------------
update_option( PE_Cache::VERSION_OPTION, '0.0.1' );
PE_Cache::maybe_purge_on_upgrade();
pe_check( 'upgrade syncs stored version', PE_VERSION === get_option( PE_Cache::VERSION_OPTION ) );
pe_check( 'upgrade purges pages', 2 === $GLOBALS['pe_t']['rocket_domain'] );
pe_check( 'upgrade purges assets', 2 === $GLOBALS['pe_t']['rocket_minify'] );
PE_Cache::maybe_purge_on_upgrade();
pe_check( 'same version does not re-purge', 2 === $GLOBALS['pe_t']['rocket_domain'] );

// --- 3. Content changes queue one purge for shutdown -------------------------
pe_reset_queue();
pe_check( 'nothing queued initially', ! pe_queued() );
update_option( 'pe_cache_ver', (int) get_option( 'pe_cache_ver', 0 ) + 1 );
pe_check( 'pe_cache_ver bump queues purge', pe_queued() );

pe_reset_queue();
$settings                = get_option( 'pe_settings', array() );
$settings['_pe_t_probe'] = time();
update_option( 'pe_settings', $settings );
pe_check( 'settings change queues purge', pe_queued() );
unset( $settings['_pe_t_probe'] );
update_option( 'pe_settings', $settings );

// --- 4. Event save queues; back-to-back import is a no-op --------------------
pe_reset_queue();
$posts = get_posts(
	array(
		'post_type'   => 'parish_event',
		'post_status' => 'publish',
		'numberposts' => 1,
	)
);
if ( $posts ) {
	wp_update_post( array( 'ID' => $posts[0]->ID ) );
	pe_check( 'event save queues purge', pe_queued() );
}

$first = PE_Importer::run( 'manual' ); // sync with the live feed
pe_reset_queue();
$second  = PE_Importer::run( 'manual' ); // immediate rerun: nothing to do
$changed = $second['created'] + $second['updated'] + $second['removed'] + $second['restored'];
pe_check( 'rerun import made no changes', 0 === $changed );
pe_check( 'no-op import does not queue purge', ! pe_queued() );

// Leave shutdown clean so the real purge stubs don't fire confusingly.
pe_reset_queue();
exit( $fail > 0 ? 1 : 0 );
