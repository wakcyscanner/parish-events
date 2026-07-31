<?php
/**
 * Server-load fixes: ICS feed transient + ETag, fragment-key normalization,
 * importer batch lookups / throttled last-seen writes.
 * Run with:
 *   npx @wordpress/env run cli -- wp eval-file wp-content/plugins/parish-events/tests/ics-cache-test.php --user=admin
 */

$fail = 0;
function pe_check( $label, $ok ) {
	global $fail;
	if ( ! $ok ) {
		$fail++;
	}
	echo ( $ok ? 'PASS' : 'FAIL' ) . " $label\n";
}

function pe_frag_count() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_pe_frag_%'" );
}

// --- Inline fixture: two events, today and today+7, inside the window -------
$pe_tz    = pe_timezone();
$pe_day_a = ( new DateTimeImmutable( 'now', $pe_tz ) )->format( 'Y-m-d' );
$pe_day_b = ( new DateTimeImmutable( 'now', $pe_tz ) )->modify( '+7 days' )->format( 'Y-m-d' );

$pe_fixture = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<items>
  <item>
    <date>{$pe_day_a}</date>
    <event_name ccb_id="990001">Load Test Alpha</event_name>
    <event_description>[public]Alpha description.[/public]</event_description>
    <start_time>09:00:00</start_time>
    <end_time>10:00:00</end_time>
    <event_type>Open To All</event_type>
    <location>Test Room</location>
    <group_name ccb_id="55">Load Test Group</group_name>
    <group_type>Adult Ministry</group_type>
    <grouping_name>Parish Ministry</grouping_name>
    <leader_name ccb_id="1"></leader_name>
    <leader_phone></leader_phone>
    <leader_email></leader_email>
  </item>
  <item>
    <date>{$pe_day_b}</date>
    <event_name ccb_id="990002">Load Test Beta</event_name>
    <event_description></event_description>
    <start_time>18:30:00</start_time>
    <end_time>20:00:00</end_time>
    <event_type>Open To All</event_type>
    <location>Test Hall</location>
    <group_name ccb_id="56">Load Test Group</group_name>
    <group_type>Adult Ministry</group_type>
    <grouping_name>Parish Ministry</grouping_name>
    <leader_name ccb_id="2"></leader_name>
    <leader_phone></leader_phone>
    <leader_email></leader_email>
  </item>
</items>
XML;

// Priority 99: wins over the mu fixture loader if it's installed.
$pe_filter = function () use ( $pe_fixture ) {
	return $pe_fixture;
};
add_filter( 'pe_pre_fetch_feed', $pe_filter, 99 );

// --- 1. Import + second-run efficiency ---------------------------------------
$run1 = PE_Importer::run( 'manual' );
pe_check( 'first run creates both events', 2 === $run1['created'] );

$ids = get_posts(
	array(
		'post_type'      => 'parish_event',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_pe_ccb_event_id',
				'value'   => array( '990001', '990002' ),
				'compare' => 'IN',
			),
		),
	)
);
pe_check( 'both posts findable by ccb id', 2 === count( $ids ) );

$last_seen_before = array();
foreach ( $ids as $id ) {
	$last_seen_before[ $id ] = get_post_meta( $id, '_pe_last_seen', true );
}

$run2 = PE_Importer::run( 'manual' );
pe_check( 'second run: nothing created', 0 === $run2['created'] );
pe_check( 'second run: nothing updated', 0 === $run2['updated'] );
pe_check( 'second run: all rows skipped as unchanged', 2 === $run2['skipped_unchanged'] );

$last_seen_stable = true;
foreach ( $ids as $id ) {
	clean_post_cache( $id );
	if ( get_post_meta( $id, '_pe_last_seen', true ) !== $last_seen_before[ $id ] ) {
		$last_seen_stable = false;
	}
}
pe_check( 'second run: _pe_last_seen writes throttled (values unchanged)', $last_seen_stable );

// --- 2. ICS feed transient ----------------------------------------------------
$body1 = PE_ICS::feed_body();
pe_check( 'feed contains Alpha', false !== strpos( $body1, 'Load Test Alpha' ) );
pe_check( 'feed contains Beta', false !== strpos( $body1, 'Load Test Beta' ) );

// Unpublish one event WITHOUT bumping pe_cache_ver (direct DB write): a
// cached feed must not notice — proof the transient is serving.
global $wpdb;
$victim = (int) $ids[0];
$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $victim ) );
clean_post_cache( $victim );
$body2 = PE_ICS::feed_body();
pe_check( 'feed served from transient (unchanged after silent edit)', $body1 === $body2 );

// Bumping pe_cache_ver (what every real content change does) rebuilds.
update_option( 'pe_cache_ver', (int) get_option( 'pe_cache_ver', 0 ) + 1 );
$body3 = PE_ICS::feed_body();
pe_check( 'cache_ver bump rebuilds the feed', $body2 !== $body3 );
pe_check( 'rebuilt feed dropped the unpublished event', false === strpos( $body3, get_the_title( $victim ) ) );

$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $victim ) );
clean_post_cache( $victim );
update_option( 'pe_cache_ver', (int) get_option( 'pe_cache_ver', 0 ) + 1 );

// --- 3. ETag matching ---------------------------------------------------------
$etag_matches = new ReflectionMethod( 'PE_ICS', 'etag_matches' );
$etag_matches->setAccessible( true );
$etag = '"' . md5( $body3 ) . '"';

$_SERVER['HTTP_IF_NONE_MATCH'] = $etag;
pe_check( 'ETag: exact match', true === $etag_matches->invoke( null, $etag ) );
$_SERVER['HTTP_IF_NONE_MATCH'] = 'W/' . $etag . ', "other"';
pe_check( 'ETag: weak + list match', true === $etag_matches->invoke( null, $etag ) );
$_SERVER['HTTP_IF_NONE_MATCH'] = '"stale"';
pe_check( 'ETag: mismatch', false === $etag_matches->invoke( null, $etag ) );
unset( $_SERVER['HTTP_IF_NONE_MATCH'] );

// --- 4. Fragment-key normalization -------------------------------------------
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pe_frag_%' OR option_name LIKE '_transient_timeout_pe_frag_%'" );

$_GET = array( 'pe_view' => 'month' );
$baseline   = PE_Shortcodes::calendar( array() );
$base_count = pe_frag_count();

$_GET = array(
	'pe_view'  => 'month',
	'pe_month' => '1999-01',
);
$past = PE_Shortcodes::calendar( array() );
pe_check( 'ancient pe_month clamps onto the current-month fragment', $past === $baseline && pe_frag_count() === $base_count );

$_GET = array(
	'pe_view'  => 'month',
	'pe_month' => '2099-12',
);
PE_Shortcodes::calendar( array() );
$after_future = pe_frag_count();

$_GET = array(
	'pe_view'  => 'month',
	'pe_month' => '2098-11',
);
PE_Shortcodes::calendar( array() );
pe_check( 'all far-future pe_month values share one clamped fragment', pe_frag_count() === $after_future );

$_GET = array(
	'pe_view'  => 'month',
	'pe_group' => 'No Such Group Xyz',
);
$bogus_group = PE_Shortcodes::calendar( array() );
pe_check( 'unknown pe_group shares the unfiltered fragment', $bogus_group === $baseline && pe_frag_count() === $after_future );

$_GET = array(
	'pe_view'  => 'month',
	'pe_group' => 'Load Test Group',
);
$real_group = PE_Shortcodes::calendar( array() );
pe_check( 'a real group still gets its own filtered fragment', $real_group !== $baseline );
$_GET = array();

// --- Cleanup ------------------------------------------------------------------
remove_filter( 'pe_pre_fetch_feed', $pe_filter, 99 );
foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}

// My fixture import reconciled any previously seeded window events to
// pe_removed. Re-run without my filter: with the mu fixture loader present
// this restores them from sample-feed.xml; without a feed source it's a
// no-op (fetch fails before reconcile).
PE_Importer::run( 'manual' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pe_frag_%' OR option_name LIKE '_transient_timeout_pe_frag_%' OR option_name LIKE '_transient_pe_ics_feed_%' OR option_name LIKE '_transient_timeout_pe_ics_feed_%'" );

echo $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n";
exit( $fail ? 1 : 0 );
