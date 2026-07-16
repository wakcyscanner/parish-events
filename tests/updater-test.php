<?php
/**
 * GitHub self-update checks. Run with:
 *   npx @wordpress/env run cli -- wp eval-file wp-content/plugins/parish-events/tests/updater-test.php --user=admin
 */

$fail = 0;
function pe_check( $label, $ok ) {
	global $fail;
	if ( ! $ok ) {
		$fail++;
	}
	echo ( $ok ? 'PASS' : 'FAIL' ) . " $label\n";
}

$basename = plugin_basename( PE_PLUGIN_FILE );

function pe_mock_release( $tag, $with_asset = true ) {
	$body = array(
		'tag_name'     => $tag,
		'html_url'     => 'https://github.com/wakcyscanner/parish-events/releases/tag/' . $tag,
		'published_at' => '2026-07-16T00:00:00Z',
		'body'         => "Test notes for $tag",
		'assets'       => $with_asset ? array(
			array(
				'name'                 => 'parish-events-' . ltrim( $tag, 'v' ) . '.zip',
				'browser_download_url' => 'https://github.com/wakcyscanner/parish-events/releases/download/' . $tag . '/parish-events-' . ltrim( $tag, 'v' ) . '.zip',
			),
		) : array(),
	);
	return array(
		'headers'  => array(),
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'body'     => wp_json_encode( $body ),
	);
}

// --- 1. parse_release unit checks --------------------------------------------
$rel = PE_Updater::parse_release( json_decode( pe_mock_release( 'v9.9.9' )['body'], true ) );
pe_check( 'parse: version stripped of v', '9.9.9' === $rel['version'] );
pe_check( 'parse: package is the zip asset', false !== strpos( $rel['package'], 'parish-events-9.9.9.zip' ) );
pe_check( 'parse: release without zip asset rejected', null === PE_Updater::parse_release( json_decode( pe_mock_release( 'v9.9.9', false )['body'], true ) ) );
pe_check( 'parse: garbage rejected', null === PE_Updater::parse_release( 'not-an-array' ) );
pe_check( 'parse: non-version tag rejected', null === PE_Updater::parse_release( array( 'tag_name' => 'latest' ) ) );

// --- 2. Newer release flows through WP's real update machinery ---------------
$mock = static function ( $pre, $args, $url ) {
	if ( false !== strpos( $url, 'api.github.com/repos/' . PE_Updater::REPO . '/releases/latest' ) ) {
		return pe_mock_release( 'v9.9.9' );
	}
	return $pre;
};
add_filter( 'pre_http_request', $mock, 10, 3 );
delete_transient( PE_Updater::CACHE_KEY );
delete_site_transient( 'update_plugins' );
wp_update_plugins();
$updates = get_site_transient( 'update_plugins' );
$entry   = isset( $updates->response[ $basename ] ) ? $updates->response[ $basename ] : null;
pe_check( 'newer release appears as available update', null !== $entry );
pe_check( 'update has version 9.9.9', $entry && '9.9.9' === $entry->version );
pe_check( 'update package is the release zip', $entry && false !== strpos( $entry->package, 'parish-events-9.9.9.zip' ) );

// plugins_api details modal.
if ( ! function_exists( 'plugins_api' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
}
$info = plugins_api( 'plugin_information', array( 'slug' => dirname( $basename ) ) );
pe_check( 'details modal served from release', ! is_wp_error( $info ) && '9.9.9' === $info->version );
pe_check( 'details changelog carries release notes', ! is_wp_error( $info ) && false !== strpos( $info->sections['changelog'], 'Test notes for v9.9.9' ) );
remove_filter( 'pre_http_request', $mock, 10 );

// --- 3. Same/older release offers nothing -------------------------------------
$mock_old = static function ( $pre, $args, $url ) {
	if ( false !== strpos( $url, 'api.github.com/repos/' . PE_Updater::REPO . '/releases/latest' ) ) {
		return pe_mock_release( 'v0.0.1' );
	}
	return $pre;
};
add_filter( 'pre_http_request', $mock_old, 10, 3 );
delete_transient( PE_Updater::CACHE_KEY );
delete_site_transient( 'update_plugins' );
wp_update_plugins();
$updates = get_site_transient( 'update_plugins' );
pe_check( 'older release offers no update', ! isset( $updates->response[ $basename ] ) );
remove_filter( 'pre_http_request', $mock_old, 10 );

// --- 4. API failure caches null briefly and offers nothing --------------------
$mock_fail = static function ( $pre, $args, $url ) {
	if ( false !== strpos( $url, 'api.github.com' ) ) {
		return new WP_Error( 'pe_test', 'unreachable' );
	}
	return $pre;
};
add_filter( 'pre_http_request', $mock_fail, 10, 3 );
delete_transient( PE_Updater::CACHE_KEY );
pe_check( 'API failure returns null', null === PE_Updater::latest_release() );
$cached = get_transient( PE_Updater::CACHE_KEY );
pe_check( 'API failure result cached (no hammering)', is_array( $cached ) && null === $cached['release'] );
remove_filter( 'pre_http_request', $mock_fail, 10 );

// --- 5. Live API: real latest release parses ----------------------------------
delete_transient( PE_Updater::CACHE_KEY );
$live = PE_Updater::latest_release();
if ( null === $live ) {
	echo "SKIP live API check (unreachable or rate-limited)\n";
} else {
	pe_check( 'live: latest release parses to a version', (bool) preg_match( '/^\d+\.\d+\.\d+$/', $live['version'] ) );
	pe_check( 'live: package downloadable url', 0 === strpos( $live['package'], 'https://github.com/' . PE_Updater::REPO . '/releases/download/' ) );
}

// Cleanup.
delete_transient( PE_Updater::CACHE_KEY );
delete_site_transient( 'update_plugins' );
exit( $fail > 0 ? 1 : 0 );
