<?php
/**
 * Test fixture loader. NOT loaded by the plugin itself.
 *
 * Copy into wp-env's mu-plugins directory (or load via `wp eval-file`) to make
 * imports read from tests/fixtures/ instead of the live worker.
 *
 * Control via options:
 *   wp option update pe_test_fixture sample-feed.xml   # which fixture (default shown)
 *   wp option update pe_test_exclude "10048,9632"      # strip these event ccb_ids (simulates upstream deletion)
 *   wp option update pe_test_mode garbage              # return invalid XML (fail-fast test)
 *   wp option update pe_test_mode empty                # return a feed with zero items
 *   wp option delete pe_test_mode                      # back to normal fixture
 *
 * Date tokens %%M0%% / %%M1%% / %%M2%% in fixtures are replaced with the
 * current, next, and after-next month (YYYY-MM) so rows always land inside
 * the live import window.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'pe_pre_fetch_feed',
	function ( $body, $url ) {
		$mode = get_option( 'pe_test_mode', '' );
		if ( 'garbage' === $mode ) {
			return 'this is not xml <<<';
		}
		if ( 'empty' === $mode ) {
			return '<?xml version="1.0"?><items></items>';
		}

		$fixture = basename( get_option( 'pe_test_fixture', 'sample-feed.xml' ) );
		$path    = WP_PLUGIN_DIR . '/parish-events/tests/fixtures/' . $fixture;
		if ( ! file_exists( $path ) ) {
			return $body;
		}

		$xml = file_get_contents( $path );

		$now = new DateTimeImmutable( 'now', pe_timezone() );
		$xml = str_replace(
			array( '%%M0%%', '%%M1%%', '%%M2%%' ),
			array(
				$now->format( 'Y-m' ),
				$now->modify( 'first day of next month' )->format( 'Y-m' ),
				$now->modify( 'first day of this month' )->modify( '+2 months' )->format( 'Y-m' ),
			),
			$xml
		);

		$exclude = array_filter( array_map( 'trim', explode( ',', get_option( 'pe_test_exclude', '' ) ) ) );
		if ( $exclude ) {
			$doc = simplexml_load_string( $xml );
			foreach ( $doc->xpath( '//item' ) as $item ) {
				if ( in_array( (string) $item->event_name['ccb_id'], $exclude, true ) ) {
					$dom = dom_import_simplexml( $item );
					$dom->parentNode->removeChild( $dom );
				}
			}
			$xml = $doc->asXML();
		}

		return $xml;
	},
	10,
	2
);
