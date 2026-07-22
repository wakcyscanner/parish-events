<?php
/**
 * Direct ChMS v1 API access checks. Run with:
 *   npx @wordpress/env run cli -- wp eval-file wp-content/plugins/parish-events/tests/feed-auth-test.php --user=admin
 */

$fail = 0;
function pe_check( $label, $ok ) {
	global $fail;
	if ( ! $ok ) {
		$fail++;
	}
	echo ( $ok ? 'PASS' : 'FAIL' ) . " $label\n";
}

$sample_xml = '<?xml version="1.0"?><ccb_api><request/><response><items><item><date>2026-08-01</date><event_name ccb_id="42">Test Event</event_name><event_description>[public]Hello[/public]</event_description><start_time>09:00:00</start_time><end_time>10:00:00</end_time><event_type>Open To All</event_type><location>Church</location><group_name ccb_id="7">Test Group</group_name><group_type>Ministry</group_type><grouping_name>Adults</grouping_name><leader_name ccb_id="9">Pat Leader</leader_name><leader_phone/><leader_email/></item></items></response></ccb_api>';

$error_xml = '<?xml version="1.0"?><ccb_api><response><errors><error number="10" type="Authentication">Invalid username or password</error></errors></response></ccb_api>';

$settings = get_option( 'pe_settings', array() );
$backup   = $settings;

// --- 1. Direct mode: URL + Basic Auth header --------------------------------
$settings['chms_subdomain'] = 'testchurch';
$settings['chms_username']  = 'apiuser';
$settings['chms_password']  = 'p@ss:w0rd';
update_option( 'pe_settings', $settings );

$captured = array();
$capture  = function ( $pre, $args, $url ) use ( &$captured, $sample_xml ) {
	if ( false !== strpos( $url, 'ccbchurch.com' ) || false !== strpos( $url, 'workers.dev' ) ) {
		$captured[] = array(
			'url'  => $url,
			'auth' => isset( $args['headers']['Authorization'] ) ? $args['headers']['Authorization'] : '',
		);
		return array(
			'headers'  => array(),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => $sample_xml,
		);
	}
	return $pre;
};
add_filter( 'pre_http_request', $capture, 10, 3 );

$body = PE_Feed_Client::fetch( '2026-08-01', '2026-08-31' );
pe_check( 'direct mode used', 1 === count( $captured ) );
pe_check( 'URL targets the church subdomain', 0 === strpos( $captured[0]['url'], 'https://testchurch.ccbchurch.com/api.php' ) );
pe_check( 'URL requests public_calendar_listing', false !== strpos( $captured[0]['url'], 'srv=public_calendar_listing' ) );
pe_check( 'URL carries the date window', false !== strpos( $captured[0]['url'], 'date_start=2026-08-01' ) && false !== strpos( $captured[0]['url'], 'date_end=2026-08-31' ) );
pe_check( 'Basic auth header correct', 'Basic ' . base64_encode( 'apiuser:p@ss:w0rd' ) === $captured[0]['auth'] );

// --- 2. Full ccb_api document parses ------------------------------------------
$rows = PE_Feed_Client::parse( $body );
pe_check( 'ccb_api-wrapped response parses', is_array( $rows ) && 1 === count( $rows ) );
pe_check( 'row fields read correctly', '42' === $rows[0]['ccb_event_id'] && 'Test Event' === $rows[0]['name'] && '7' === $rows[0]['group_ccb_id'] );

// --- 3. ChMS error XML surfaces as an error, and a run fails safely -----------
$err = PE_Feed_Client::parse( $error_xml );
pe_check( 'API error XML returns WP_Error', is_wp_error( $err ) );
pe_check( 'error message carries the API text', false !== strpos( $err->get_error_message(), 'Invalid username or password' ) );

$published_before = (int) wp_count_posts( 'parish_event' )->publish;
$error_feed       = function ( $pre, $args, $url ) use ( $error_xml ) {
	if ( false !== strpos( $url, 'ccbchurch.com' ) ) {
		return array(
			'headers'  => array(),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => $error_xml,
		);
	}
	return $pre;
};
remove_filter( 'pre_http_request', $capture, 10 );
add_filter( 'pre_http_request', $error_feed, 10, 3 );
$run = PE_Importer::run( 'manual' );
remove_filter( 'pre_http_request', $error_feed, 10 );
pe_check( 'auth-failure run is marked failed', ! empty( $run['failed'] ) );
pe_check( 'auth-failure run removed nothing', 0 === $run['removed'] && (int) wp_count_posts( 'parish_event' )->publish === $published_before );

// --- 4. Constants override settings -------------------------------------------
define( 'PE_CHMS_SUBDOMAIN', 'constantchurch' );
$creds = PE_Feed_Client::credentials();
pe_check( 'wp-config constant beats the setting', 'constantchurch' === $creds['subdomain'] );

// --- 5. Legacy mode: no credentials -> custom feed URL, no auth ---------------
// (The subdomain constant is defined above, but username/password constants
// are not and the settings are cleared, so direct mode is off.)
$settings['chms_username'] = '';
$settings['chms_password'] = '';
update_option( 'pe_settings', $settings );
$captured = array();
add_filter( 'pre_http_request', $capture, 10, 3 );
PE_Feed_Client::fetch( '2026-08-01', '2026-08-31' );
remove_filter( 'pre_http_request', $capture, 10 );
pe_check( 'legacy mode falls back to custom feed URL', 1 === count( $captured ) && false !== strpos( $captured[0]['url'], 'workers.dev' ) );
pe_check( 'legacy mode sends no auth header', '' === $captured[0]['auth'] );

// --- 6. Subdomain sanitizing accepts pasted URLs ------------------------------
// A bare field, not the full stored array — settings whose suppress_exact is
// already structured would trip the double-sanitize guard and skip sanitizing.
$sanitized = PE_Settings::sanitize( array( 'chms_subdomain' => 'https://MyChurch.ccbchurch.com/api.php' ) );
pe_check( 'pasted URL reduces to bare subdomain', 'mychurch' === $sanitized['chms_subdomain'] );

// Restore.
update_option( 'pe_settings', $backup );
exit( $fail > 0 ? 1 : 0 );
