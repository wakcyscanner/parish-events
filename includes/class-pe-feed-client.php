<?php
/**
 * Fetches and parses the CCB XML feed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_Feed_Client {

	/**
	 * Fetch the raw feed body for a date window.
	 *
	 * @param string $start Y-m-d.
	 * @param string $end   Y-m-d.
	 * @return string|WP_Error Raw XML body.
	 */
	public static function fetch( $start, $end ) {
		$url = add_query_arg(
			array(
				'date_start' => $start,
				'date_end'   => $end,
			),
			PE_Settings::get( 'api_base_url' )
		);

		// Test/fixture hook: return a string to bypass the HTTP request.
		$body = apply_filters( 'pe_pre_fetch_feed', null, $url );
		if ( is_string( $body ) ) {
			return $body;
		}

		// No redirects (the endpoint must answer directly — a redirect could
		// point the request somewhere the https-only rule never validated)
		// and a hard response-size cap so a misbehaving origin can't exhaust
		// memory during import.
		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => 30,
				'redirection'         => 0,
				'limit_response_size' => 5 * MB_IN_BYTES,
			)
		);
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

		$rows = array();
		foreach ( $xml->xpath( '//item' ) as $item ) {
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
