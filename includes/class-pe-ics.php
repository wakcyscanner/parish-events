<?php
/**
 * iCalendar output: per-event .ics downloads (?pe_ics=<post_id>), a
 * subscribable feed of all published upcoming events (?pe_ics=feed), and the
 * Google Calendar link builder.
 *
 * The feed contains event posts only — suppressed series (Mass etc.) are
 * intentionally absent, matching the website: subscribers get the parish
 * events, and Mass times live on their own page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_ICS {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 5 );
	}

	public static function maybe_serve() {
		if ( ! isset( $_GET['pe_ics'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$what = sanitize_key( wp_unslash( $_GET['pe_ics'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'feed' === $what ) {
			self::serve_feed();
		}

		$post_id = absint( $what );
		if ( $post_id && PE_CPT::POST_TYPE === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id ) ) {
			self::serve_single( $post_id );
		}
	}

	private static function serve_single( $post_id ) {
		$vevent = self::vevent( $post_id );
		if ( '' === $vevent ) {
			return;
		}
		self::send( self::wrap( $vevent ), sanitize_title( get_post_field( 'post_name', $post_id ) ) . '.ics' );
	}

	private static function serve_feed() {
		$window = pe_import_window();
		$query  = new WP_Query(
			array(
				'post_type'              => PE_CPT::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_pe_event_date',
						'value'   => array( pe_today(), $window['end'] ),
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
				),
			)
		);

		$events = '';
		foreach ( $query->posts as $post ) {
			$events .= self::vevent( $post->ID );
		}

		self::send( self::wrap( $events, get_bloginfo( 'name' ) . ' Events' ), 'parish-events.ics' );
	}

	/**
	 * Build one VEVENT block.
	 *
	 * @param int $post_id Event post ID.
	 * @return string CRLF-terminated VEVENT lines, or ''.
	 */
	public static function vevent( $post_id ) {
		$date = get_post_meta( $post_id, '_pe_event_date', true );
		if ( ! $date ) {
			return '';
		}

		$uid     = get_post_meta( $post_id, '_pe_uid', true );
		$all_day = '1' === get_post_meta( $post_id, '_pe_all_day', true );

		$lines   = array( 'BEGIN:VEVENT' );
		$lines[] = 'UID:' . ( $uid ? $uid : 'post-' . $post_id ) . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
		$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z', (int) get_post_timestamp( $post_id, 'modified' ) );

		if ( $all_day ) {
			$end     = new DateTimeImmutable( $date, pe_timezone() );
			$lines[] = 'DTSTART;VALUE=DATE:' . str_replace( '-', '', $date );
			$lines[] = 'DTEND;VALUE=DATE:' . $end->modify( '+1 day' )->format( 'Ymd' );
		} else {
			$times = self::utc_times( $post_id, $date );
			if ( null === $times ) {
				return '';
			}
			$lines[] = 'DTSTART:' . $times['start'];
			$lines[] = 'DTEND:' . $times['end'];
		}

		$lines[] = 'SUMMARY:' . self::escape( self::plain_title( $post_id ) );

		$location = get_post_meta( $post_id, '_pe_location', true );
		if ( $location ) {
			$lines[] = 'LOCATION:' . self::escape( $location );
		}

		$description = trim( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) );
		if ( '' !== $description ) {
			$lines[] = 'DESCRIPTION:' . self::escape( $description );
		}

		$lines[] = 'URL:' . esc_url_raw( get_permalink( $post_id ) );
		$lines[] = 'END:VEVENT';

		return implode( "\r\n", array_map( array( __CLASS__, 'fold' ), $lines ) ) . "\r\n";
	}

	/**
	 * Event start/end as UTC iCal timestamps (handles DST via the feed TZ).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $date    Y-m-d.
	 * @return array{start:string,end:string}|null
	 */
	private static function utc_times( $post_id, $date ) {
		$start = get_post_meta( $post_id, '_pe_start_time', true );
		$end   = get_post_meta( $post_id, '_pe_end_time', true );
		try {
			$tz       = pe_timezone();
			$utc      = new DateTimeZone( 'UTC' );
			$start_dt = new DateTimeImmutable( $date . ' ' . $start, $tz );
			$end_dt   = preg_match( '/^\d{2}:\d{2}:\d{2}$/', (string) $end )
				? new DateTimeImmutable( $date . ' ' . $end, $tz )
				: $start_dt->modify( '+1 hour' );
			return array(
				'start' => $start_dt->setTimezone( $utc )->format( 'Ymd\THis\Z' ),
				'end'   => $end_dt->setTimezone( $utc )->format( 'Ymd\THis\Z' ),
			);
		} catch ( Exception $e ) {
			return null;
		}
	}

	private static function wrap( $events, $calendar_name = '' ) {
		$head = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Parish Events//' . wp_parse_url( home_url(), PHP_URL_HOST ) . '//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
		);
		if ( '' !== $calendar_name ) {
			$head[] = self::fold( 'X-WR-CALNAME:' . self::escape( $calendar_name ) );
			$head[] = 'X-WR-TIMEZONE:' . pe_timezone()->getName();
		}
		return implode( "\r\n", $head ) . "\r\n" . $events . "END:VCALENDAR\r\n";
	}

	private static function send( $body, $filename ) {
		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCal body, escaped per RFC 5545.
		exit;
	}

	/**
	 * The post title as plain text — get_the_title() output carries
	 * texturized HTML entities (&#8211; etc.) that must not reach iCal or
	 * URL parameters.
	 */
	private static function plain_title( $post_id ) {
		return html_entity_decode( get_the_title( $post_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Escape text per RFC 5545 (backslash, semicolon, comma, newline).
	 */
	private static function escape( $text ) {
		return str_replace(
			array( '\\', ';', ',', "\r\n", "\n" ),
			array( '\\\\', '\;', '\,', '\n', '\n' ),
			(string) $text
		);
	}

	/**
	 * Fold long content lines at 73 octets (RFC 5545 continuation lines).
	 */
	public static function fold( $line ) {
		if ( strlen( $line ) <= 73 ) {
			return $line;
		}
		$out = array();
		while ( strlen( $line ) > 73 ) {
			// Break on a byte boundary that doesn't split a UTF-8 sequence.
			$cut = 73;
			while ( $cut > 60 && "\x80" === ( substr( $line, $cut, 1 ) & "\xC0" ) ) {
				$cut--;
			}
			$out[] = substr( $line, 0, $cut );
			$line  = ' ' . substr( $line, $cut );
		}
		$out[] = $line;
		return implode( "\r\n", $out );
	}

	/**
	 * The subscribable feed URL, in webcal:// form for calendar apps.
	 */
	public static function subscribe_url() {
		return preg_replace( '#^https?://#', 'webcal://', home_url( '/?pe_ics=feed' ) );
	}

	/**
	 * Google Calendar "add event" link.
	 *
	 * @param int $post_id Event post ID.
	 * @return string URL, or '' when the event has no date.
	 */
	public static function google_url( $post_id ) {
		$date = get_post_meta( $post_id, '_pe_event_date', true );
		if ( ! $date ) {
			return '';
		}

		if ( '1' === get_post_meta( $post_id, '_pe_all_day', true ) ) {
			$end   = new DateTimeImmutable( $date, pe_timezone() );
			$dates = str_replace( '-', '', $date ) . '/' . $end->modify( '+1 day' )->format( 'Ymd' );
		} else {
			$times = self::utc_times( $post_id, $date );
			if ( null === $times ) {
				return '';
			}
			$dates = $times['start'] . '/' . $times['end'];
		}

		$args = array(
			'action'   => 'TEMPLATE',
			'text'     => self::plain_title( $post_id ),
			'dates'    => $dates,
			'details'  => get_permalink( $post_id ),
			'location' => get_post_meta( $post_id, '_pe_location', true ),
		);

		return add_query_arg( array_map( 'rawurlencode', array_filter( $args ) ), 'https://calendar.google.com/calendar/render' );
	}
}
