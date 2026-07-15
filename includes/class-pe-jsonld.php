<?php
/**
 * Schema.org Event JSON-LD for single event pages.
 *
 * Emission rules:
 * - published + upcoming/ongoing  -> EventScheduled
 * - published + past              -> nothing (stale Event markup hurts SEO)
 * - removed, inside grace window  -> EventCancelled (Google prefers this
 *   over a vanished page; original startDate is retained)
 * - removed, past grace           -> unreachable (410, see PE_Content)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_JsonLD {

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ) );
	}

	public static function output() {
		if ( ! is_singular( PE_CPT::POST_TYPE ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		$status  = get_post_status( $post_id );

		// EventCancelled is only emitted on an explicit admin cancellation —
		// absence from the feed proves nothing. Removed-but-not-cancelled
		// pages carry no Event markup at all during their grace window.
		if ( '1' === get_post_meta( $post_id, '_pe_cancelled', true ) ) {
			$event_status = 'https://schema.org/EventCancelled';
		} elseif ( 'publish' === $status && ! pe_event_is_past( $post_id ) ) {
			$event_status = 'https://schema.org/EventScheduled';
		} else {
			return;
		}

		$data = self::build( $post_id, $event_status );
		if ( ! $data ) {
			return;
		}

		// JSON_HEX_TAG keeps any feed-supplied "</script>" from terminating
		// this script element; never emit unescaped slashes into script
		// context.
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build the Event structure. Port of generateStructuredData()
	 * (calendar.js:450).
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $event_status schema.org eventStatus URL.
	 * @return array|null
	 */
	public static function build( $post_id, $event_status ) {
		$date = get_post_meta( $post_id, '_pe_event_date', true );
		if ( ! $date ) {
			return null;
		}

		$all_day  = '1' === get_post_meta( $post_id, '_pe_all_day', true );
		$start    = get_post_meta( $post_id, '_pe_start_time', true );
		$end      = get_post_meta( $post_id, '_pe_end_time', true );
		$type     = get_post_meta( $post_id, '_pe_event_type', true );
		$group    = get_post_meta( $post_id, '_pe_group_name', true );
		$location = get_post_meta( $post_id, '_pe_location', true );
		$settings = PE_Settings::get_all();

		try {
			if ( $all_day ) {
				$start_iso = $date;
				$end_iso   = $date;
			} else {
				// DateTimeImmutable in the feed timezone yields the correct
				// -04:00/-05:00 offset across DST transitions.
				$tz        = pe_timezone();
				$start_dt  = new DateTimeImmutable( $date . ' ' . $start, $tz );
				$end_dt    = preg_match( '/^\d{2}:\d{2}:\d{2}$/', (string) $end )
					? new DateTimeImmutable( $date . ' ' . $end, $tz )
					: $start_dt->modify( '+1 hour' );
				$start_iso = $start_dt->format( 'c' );
				$end_iso   = $end_dt->format( 'c' );
			}
		} catch ( Exception $e ) {
			return null;
		}

		$image   = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( ! $image ) {
			$image = $settings['default_image'];
		}

		$excerpt     = trim( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) );
		$description = '' !== $excerpt
			? wp_trim_words( $excerpt, 40 )
			: sprintf( '%s hosted by %s', $type ? $type : 'Event', $group ? $group : 'the parish' );

		$address = isset( $settings['location_addresses'][ $location ] )
			? $settings['location_addresses'][ $location ]
			: array(
				'street' => '313 N State St',
				'city'   => 'Westerville',
				'region' => 'OH',
				'zip'    => '43082',
			);

		return array(
			'@context'            => 'https://schema.org',
			'@type'               => 'Event',
			'name'                => get_the_title( $post_id ),
			'description'         => $description,
			'image'               => $image,
			'startDate'           => $start_iso,
			'endDate'             => $end_iso,
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'eventStatus'         => $event_status,
			'url'                 => get_permalink( $post_id ),
			'isAccessibleForFree' => true,
			'inLanguage'          => 'en',
			'offers'              => array(
				'@type'         => 'Offer',
				'price'         => '0',
				'priceCurrency' => 'USD',
				'availability'  => 'https://schema.org/InStock',
				'url'           => get_permalink( $post_id ),
			),
			'location'            => array(
				'@type'   => 'Place',
				'name'    => $location ? $location : 'St. Paul the Apostle Catholic Church',
				'address' => array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $address['street'],
					'addressLocality' => $address['city'],
					'addressRegion'   => $address['region'],
					'postalCode'      => $address['zip'],
					'addressCountry'  => 'US',
				),
			),
			'organizer'           => array(
				'@type'  => 'Organization',
				'name'   => $group ? $group : 'St. Paul the Apostle Catholic Church',
				'url'    => 'https://stpacc.org/',
				'sameAs' => array(
					'https://www.facebook.com/stpacc/',
					'https://www.instagram.com/stpaulcatholicwesterville/',
					'https://www.youtube.com/c/StPaultheApostleCatholicChurchWesterville',
				),
			),
		);
	}
}
