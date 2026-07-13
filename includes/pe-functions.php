<?php
/**
 * Pure helper functions, ported from calendar.js.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The timezone all feed times are expressed in. The feed emits naive
 * America/New_York times regardless of the site's timezone setting.
 *
 * @return DateTimeZone
 */
function pe_timezone() {
	$tz = apply_filters( 'pe_timezone', 'America/New_York' );
	return new DateTimeZone( $tz );
}

/**
 * Extract the visitor-visible text from a CCB event description.
 *
 * Parish staff wrap public text in [public]...[/public] inside the CCB
 * description field; everything outside the tags is internal notes and must
 * never be published or persisted. Port of extractPublicDescription()
 * (calendar.js:812).
 *
 * @param string $raw Raw event_description (inner HTML-encoded layer intact).
 * @return string Plain text, or '' when no tagged sections exist.
 */
function pe_extract_public_description( $raw ) {
	if ( '' === trim( (string) $raw ) ) {
		return '';
	}

	// The XML layer is already decoded by SimpleXML; this decodes the
	// HTML-encoded layer inside the description value.
	$decoded = html_entity_decode( (string) $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	if ( ! preg_match_all( '#\[public\](.*?)\[/public\]#is', $decoded, $matches ) ) {
		return '';
	}

	$text = implode( "\n", array_map( 'trim', $matches[1] ) );

	// Block-level closers become newlines before tags are stripped.
	$text = preg_replace( '#<br\s*/?\s*>#i', "\n", $text );
	$text = preg_replace( '#</(p|div|li)>#i', "\n", $text );
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = preg_replace( "/\n{3,}/", "\n\n", $text );

	return trim( $text );
}

/**
 * Apply location substitution rules. Port of applyLocationSubstitution()
 * (calendar.js:786): global map first, then the first matching
 * event-specific rule wins.
 *
 * @param string $location   Raw location from the feed.
 * @param string $event_name Event name (for event-specific rules).
 * @param array  $settings   Full settings array.
 * @return string
 */
function pe_apply_location_substitution( $location, $event_name, $settings ) {
	$location = trim( (string) $location );

	if ( isset( $settings['location_subs_global'][ $location ] ) ) {
		$location = $settings['location_subs_global'][ $location ];
	}

	foreach ( (array) $settings['location_subs_event'] as $rule ) {
		if ( false !== stripos( $event_name, $rule['event_contains'] ) ) {
			if ( array_key_exists( $location, $rule['substitutions'] ) ) {
				$location = $rule['substitutions'][ $location ];
				break;
			}
		}
	}

	return $location;
}

/**
 * Find the suppression rule matching an event, if any.
 *
 * Suppressed events never get their own post. They still appear on the
 * calendar displays as "linked occurrences": when the rule carries a URL
 * (e.g. the Mass times page) the occurrence links there; with no URL it
 * renders as plain unlinked text.
 *
 * @param string $ccb_id   Event ccb_id.
 * @param string $name     Event name.
 * @param array  $settings Full settings array.
 * @return array|null array{url:string} when suppressed, null otherwise.
 */
function pe_suppression_rule( $ccb_id, $name, $settings ) {
	foreach ( (array) $settings['suppress_ids'] as $rule ) {
		if ( (int) $ccb_id === (int) $rule['id'] ) {
			return array( 'url' => $rule['url'] );
		}
	}

	foreach ( (array) $settings['suppress_exact'] as $rule ) {
		if ( 0 === strcasecmp( trim( $name ), trim( $rule['title'] ) ) ) {
			return array( 'url' => $rule['url'] );
		}
	}

	$lower = strtolower( $name );
	foreach ( (array) $settings['suppress_contains'] as $rule ) {
		if ( '' !== $rule['contains'] && false !== strpos( $lower, $rule['contains'] ) ) {
			return array( 'url' => $rule['url'] );
		}
	}

	return null;
}

/**
 * Render a location name with its directory affordance, if configured.
 *
 * The settings' location directory maps a location name to a page URL and/or
 * a short "where is this" description. A URL renders the name as a link; a
 * description renders a native <details> disclosure (no JS needed) with the
 * text — and the link inside it when both are set.
 *
 * @param string $location Location name.
 * @return string Escaped HTML ('' for an empty location).
 */
function pe_location_html( $location ) {
	$location = trim( (string) $location );
	if ( '' === $location ) {
		return '';
	}

	$info = null;
	foreach ( (array) PE_Settings::get( 'location_info' ) as $name => $entry ) {
		if ( 0 === strcasecmp( $name, $location ) ) {
			$info = $entry;
			break;
		}
	}

	if ( null === $info ) {
		return '<span class="pe-location-name">' . esc_html( $location ) . '</span>';
	}

	if ( '' !== $info['description'] ) {
		$html  = '<details class="pe-location-info"><summary>' . esc_html( $location ) . '</summary><div class="pe-location-detail">';
		$html .= '<p>' . esc_html( $info['description'] ) . '</p>';
		if ( '' !== $info['url'] ) {
			$html .= '<p><a href="' . esc_url( $info['url'] ) . '">' . esc_html__( 'More about this location', 'parish-events' ) . ' &rarr;</a></p>';
		}
		$html .= '</div></details>';
		return $html;
	}

	if ( '' !== $info['url'] ) {
		return '<a class="pe-location-link" href="' . esc_url( $info['url'] ) . '">' . esc_html( $location ) . '</a>';
	}

	return '<span class="pe-location-name">' . esc_html( $location ) . '</span>';
}

/**
 * Format HH:MM:SS as a human-readable time. Port of formatTime()
 * (calendar.js:917).
 *
 * @param string $time HH:MM:SS.
 * @return string e.g. "8:15 AM".
 */
function pe_format_time( $time ) {
	if ( ! preg_match( '/^(\d{2}):(\d{2})/', (string) $time, $m ) ) {
		return '';
	}
	$hour = (int) $m[1];
	$min  = $m[2];

	if ( 0 === $hour ) {
		return '12:' . $min . ' AM';
	}
	if ( 12 === $hour ) {
		return '12:' . $min . ' PM';
	}
	if ( $hour > 12 ) {
		return ( $hour - 12 ) . ':' . $min . ' PM';
	}
	return $hour . ':' . $min . ' AM';
}

/**
 * Build the immutable slug for a new event post.
 *
 * @param string $name Event name.
 * @param string $date Y-m-d.
 * @return string e.g. "mass-of-remembrance-2026-08-15".
 */
function pe_build_slug( $name, $date ) {
	return sanitize_title( $name ) . '-' . $date;
}

/**
 * Whether an event occurrence is entirely in the past.
 *
 * @param int $post_id Event post ID.
 * @return bool
 */
function pe_event_is_past( $post_id ) {
	$date = get_post_meta( $post_id, '_pe_event_date', true );
	if ( ! $date ) {
		return false;
	}
	$end = get_post_meta( $post_id, '_pe_end_time', true );
	if ( ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', (string) $end ) ) {
		$end = '23:59:59';
	}

	try {
		$ends = new DateTimeImmutable( $date . ' ' . $end, pe_timezone() );
	} catch ( Exception $e ) {
		return false;
	}
	return $ends->getTimestamp() < time();
}

/**
 * Today's date (Y-m-d) in the feed timezone.
 *
 * @return string
 */
function pe_today() {
	$now = new DateTimeImmutable( 'now', pe_timezone() );
	return $now->format( 'Y-m-d' );
}

/**
 * The import/display window: first day of the current month through the last
 * day of the month after next, in the feed timezone.
 *
 * @return array{start:string,end:string} Y-m-d bounds, inclusive.
 */
function pe_import_window() {
	$now = new DateTimeImmutable( 'now', pe_timezone() );
	$first = $now->modify( 'first day of this month' );
	return array(
		'start' => $first->format( 'Y-m-d' ),
		'end'   => $first->modify( '+3 months' )->modify( '-1 day' )->format( 'Y-m-d' ),
	);
}
