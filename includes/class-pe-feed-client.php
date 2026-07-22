<?php
/**
 * Fetches and parses the CCB XML feed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_Feed_Client {

	/**
	 * The ChMS v1 API credentials. wp-config constants beat settings, so
	 * secrets can stay out of the database on production:
	 *   define( 'PE_CHMS_SUBDOMAIN', 'yourchurch' );
	 *   define( 'PE_CHMS_USERNAME', '...' );
	 *   define( 'PE_CHMS_PASSWORD', '...' );
	 *
	 * @return array{subdomain:string,username:string,password:string}
	 */
	public static function credentials() {
		return array(
			'subdomain' => defined( 'PE_CHMS_SUBDOMAIN' ) ? PE_CHMS_SUBDOMAIN : (string) PE_Settings::get( 'chms_subdomain' ),
			'username'  => defined( 'PE_CHMS_USERNAME' ) ? PE_CHMS_USERNAME : (string) PE_Settings::get( 'chms_username' ),
			'password'  => defined( 'PE_CHMS_PASSWORD' ) ? PE_CHMS_PASSWORD : (string) PE_Settings::get( 'chms_password' ),
		);
	}

	/**
	 * Whether direct ChMS API access is configured (vs. the legacy custom
	 * feed URL / proxy).
	 *
	 * @return bool
	 */
	public static function uses_direct_api() {
		$creds = self::credentials();
		return '' !== $creds['subdomain'] && '' !== $creds['username'] && '' !== $creds['password'];
	}

	/**
	 * Fetch the raw feed body for a date window — directly from the ChMS v1
	 * API (Basic Auth) when credentials are configured, else from the custom
	 * feed URL.
	 *
	 * @param string $start Y-m-d.
	 * @param string $end   Y-m-d.
	 * @return string|WP_Error Raw XML body.
	 */
	public static function fetch( $start, $end ) {
		$args = array(
			'timeout'             => 30,
			'redirection'         => 0,
			'limit_response_size' => 5 * MB_IN_BYTES,
		);

		if ( self::uses_direct_api() ) {
			$creds = self::credentials();
			$url   = add_query_arg(
				array(
					'srv'        => 'public_calendar_listing',
					'date_start' => $start,
					'date_end'   => $end,
				),
				'https://' . $creds['subdomain'] . '.ccbchurch.com/api.php'
			);
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic Auth.
			$args['headers'] = array( 'Authorization' => 'Basic ' . base64_encode( $creds['username'] . ':' . $creds['password'] ) );
		} else {
			$base = (string) PE_Settings::get( 'api_base_url' );
			if ( '' === $base ) {
				return new WP_Error( 'pe_no_source', 'No feed source configured: enter ChMS API credentials or a custom feed URL in Parish Events settings.' );
			}
			$url = add_query_arg(
				array(
					'date_start' => $start,
					'date_end'   => $end,
				),
				$base
			);
		}

		// Test/fixture hook: return a string to bypass the HTTP request.
		$body = apply_filters( 'pe_pre_fetch_feed', null, $url );
		if ( is_string( $body ) ) {
			return $body;
		}

		// No redirects (the endpoint must answer directly — a redirect could
		// point the request somewhere the https-only rule never validated)
		// and a hard response-size cap so a misbehaving origin can't exhaust
		// memory during import.
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'pe_http_error', sprintf( 'Feed returned HTTP %d', $code ) );
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Parse the feed body into normalized event rows.
	 *
	 * Each of event_name, group_name, and leader_name carries its own ccb_id
	 * attribute; the three are independent, numerically overlapping ID
	 * namespaces, so each is read from its specific element.
	 *
	 * @param string $body Raw XML.
	 * @return array|WP_Error List of associative rows, or error on malformed XML.
	 */
	public static function parse( $body ) {
		$previous = libxml_use_internal_errors( true );
		$xml      = simplexml_load_string( $body );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $xml ) {
			return new WP_Error( 'pe_malformed_xml', 'Feed body is not valid XML' );
		}

		// The ChMS API reports failures (bad credentials, unknown service,
		// rate limiting) as HTTP 200 with an <errors> block and no items.
		// Surface the message instead of treating it as an empty feed.
		$items  = $xml->xpath( '//item' );
		$errors = $xml->xpath( '//error' );
		if ( ! $items && $errors ) {
			return new WP_Error( 'pe_api_error', 'ChMS API error: ' . trim( (string) $errors[0] ) );
		}

		$rows = array();
		foreach ( $items as $item ) {
			$rows[] = array(
				'ccb_event_id'  => isset( $item->event_name['ccb_id'] ) ? trim( (string) $item->event_name['ccb_id'] ) : '',
				'name'          => trim( (string) $item->event_name ),
				'date'          => trim( (string) $item->date ),
				'start_time'    => trim( (string) $item->start_time ),
				'end_time'      => trim( (string) $item->end_time ),
				'event_type'    => trim( (string) $item->event_type ),
				'location'      => trim( (string) $item->location ),
				'description'   => (string) $item->event_description,
				'group_name'    => trim( (string) $item->group_name ),
				'group_ccb_id'  => isset( $item->group_name['ccb_id'] ) ? trim( (string) $item->group_name['ccb_id'] ) : '',
				'group_type'    => trim( (string) $item->group_type ),
				'grouping_name' => trim( (string) $item->grouping_name ),
				'leader_name'   => trim( (string) $item->leader_name ),
				'leader_ccb_id' => isset( $item->leader_name['ccb_id'] ) ? trim( (string) $item->leader_name['ccb_id'] ) : '',
				'leader_phone'  => trim( (string) $item->leader_phone ),
				'leader_email'  => trim( (string) $item->leader_email ),
			);
		}

		return $rows;
	}

	/**
	 * Validate a parsed row's identity fields.
	 *
	 * @param array $row Parsed row.
	 * @return true|string True when valid, otherwise a reason string.
	 */
	public static function validate_row( $row ) {
		if ( '' === $row['ccb_event_id'] || ! ctype_digit( $row['ccb_event_id'] ) ) {
			return 'missing or non-numeric event ccb_id';
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $row['date'] ) ) {
			return 'invalid date';
		}
		if ( ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', $row['start_time'] ) || ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', $row['end_time'] ) ) {
			return 'invalid time';
		}
		if ( '' === $row['name'] ) {
			return 'empty event name';
		}
		return true;
	}
}
