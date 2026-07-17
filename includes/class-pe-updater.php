<?php
/**
 * Self-updates from GitHub Releases.
 *
 * The plugin header's Update URI points at the GitHub repo, which makes
 * WordPress fire the update_plugins_github.com filter during its regular
 * update checks (and keeps wordpress.org from ever serving a same-named
 * plugin as an update). We answer with the latest release's zip asset when
 * its tag is newer than the installed version, so updates appear on the
 * Plugins screen and install like any other plugin update.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_Updater {

	const REPO      = 'wakcyscanner/parish-events';
	const CACHE_KEY = 'pe_update_check';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 10, 3 );
	}

	/**
	 * Answer WordPress's update check for this plugin.
	 *
	 * The latest release is reported even when it isn't newer: core does the
	 * version comparison and files it under response (update available) or
	 * no_update (current). The no_update entry matters — without it the
	 * Plugins screen treats the plugin as unmanaged and hides the
	 * enable-auto-updates control.
	 *
	 * @param array|false $update      Existing update data (false when none).
	 * @param array       $plugin_data Parsed plugin headers.
	 * @param string      $plugin_file Plugin basename being checked.
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( PE_PLUGIN_FILE ) !== $plugin_file ) {
			return $update;
		}

		$release = self::latest_release();
		if ( null === $release ) {
			return $update;
		}

		return array(
			'id'      => 'github.com/' . self::REPO,
			'slug'    => dirname( plugin_basename( PE_PLUGIN_FILE ) ),
			'version' => $release['version'],
			'url'     => $release['url'],
			'package' => $release['package'],
		);
	}

	/**
	 * Back the "View details" link on the Plugins screen with release info.
	 *
	 * @param false|object|array $result Existing result.
	 * @param string             $action API action.
	 * @param object             $args   Request args.
	 * @return false|object
	 */
	public static function details( $result, $action, $args ) {
		$slug = dirname( plugin_basename( PE_PLUGIN_FILE ) );
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $slug !== $args->slug ) {
			return $result;
		}

		$release = self::latest_release();
		if ( null === $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Parish Events',
			'slug'          => $slug,
			'version'       => $release['version'],
			'homepage'      => 'https://github.com/' . self::REPO,
			'download_link' => $release['package'],
			'last_updated'  => $release['date'],
			'sections'      => array(
				'description' => esc_html__( 'Imports parish calendar events from a CCB XML feed into a custom post type with scheduled sync, manual overrides, structured data, and display shortcodes.', 'parish-events' ),
				'changelog'   => wpautop( esc_html( $release['notes'] ) ),
			),
		);
	}

	/**
	 * Latest GitHub release with an installable zip asset, cached for a few
	 * hours. Returns null when there is no usable release (or the API is
	 * unreachable — failures are cached briefly so a GitHub outage can't
	 * slow every admin page load).
	 *
	 * @return array|null {version, package, url, notes, date}
	 */
	public static function latest_release() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && array_key_exists( 'release', $cached ) ) {
			return $cached['release'];
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::CACHE_KEY, array( 'release' => null ), HOUR_IN_SECONDS );
			return null;
		}

		$release = self::parse_release( json_decode( wp_remote_retrieve_body( $response ), true ) );
		set_transient( self::CACHE_KEY, array( 'release' => $release ), self::CACHE_TTL );
		return $release;
	}

	/**
	 * Extract the fields we need from a GitHub release payload.
	 *
	 * @param mixed $body Decoded API response.
	 * @return array|null
	 */
	public static function parse_release( $body ) {
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return null;
		}

		$version = ltrim( (string) $body['tag_name'], 'vV' );
		if ( ! preg_match( '/^\d+(\.\d+)+/', $version ) ) {
			return null;
		}

		$package = '';
		foreach ( (array) ( isset( $body['assets'] ) ? $body['assets'] : array() ) as $asset ) {
			if (
				is_array( $asset )
				&& isset( $asset['name'], $asset['browser_download_url'] )
				&& preg_match( '/^parish-events-[\w.-]+\.zip$/', (string) $asset['name'] )
			) {
				$package = (string) $asset['browser_download_url'];
				break;
			}
		}
		if ( '' === $package ) {
			return null; // A release without a built zip is not installable.
		}

		return array(
			'version' => $version,
			'package' => $package,
			'url'     => isset( $body['html_url'] ) ? (string) $body['html_url'] : 'https://github.com/' . self::REPO . '/releases',
			'notes'   => isset( $body['body'] ) ? (string) $body['body'] : '',
			'date'    => isset( $body['published_at'] ) ? (string) $body['published_at'] : '',
		);
	}
}
