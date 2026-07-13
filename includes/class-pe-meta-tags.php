<?php
/**
 * Meta description, Open Graph, and Twitter card tags for single event pages.
 * Port of updateMetaTags() (calendar.js:527).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_Meta_Tags {

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 5 );
	}

	public static function output() {
		if ( ! is_singular( PE_CPT::POST_TYPE ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		$date    = get_post_meta( $post_id, '_pe_event_date', true );
		if ( ! $date ) {
			return;
		}

		$date_label = wp_date( 'l, F j, Y', strtotime( $date . 'T12:00:00' ), pe_timezone() );
		$all_day    = '1' === get_post_meta( $post_id, '_pe_all_day', true );
		$time_label = $all_day ? __( 'All Day', 'parish-events' ) : pe_format_time( get_post_meta( $post_id, '_pe_start_time', true ) );
		$location   = get_post_meta( $post_id, '_pe_location', true );
		$type       = get_post_meta( $post_id, '_pe_event_type', true );

		$title = get_the_title( $post_id ) . ' - ' . $date_label;
		if ( PE_CPT::STATUS_REMOVED === get_post_status( $post_id ) ) {
			$title = __( 'Cancelled:', 'parish-events' ) . ' ' . $title;
		}

		// Prefer the public description; fall back to the formatted line the
		// old calendar used.
		$content     = trim( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) );
		$description = '' !== $content
			? wp_trim_words( $content, 30 )
			: sprintf(
				'%s on %s at %s.%s',
				$type ? $type : 'Event',
				$date_label,
				$time_label,
				$location ? ' ' . $location : ''
			);

		$image = get_the_post_thumbnail_url( $post_id, 'large' );
		if ( ! $image ) {
			$image = PE_Settings::get( 'default_image' );
		}

		$tags = array(
			array( 'name', 'description', $description ),
			array( 'property', 'og:title', $title ),
			array( 'property', 'og:description', $description ),
			array( 'property', 'og:type', 'event' ),
			array( 'property', 'og:url', get_permalink( $post_id ) ),
			array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
			array( 'name', 'twitter:card', 'summary' ),
			array( 'name', 'twitter:title', $title ),
			array( 'name', 'twitter:description', $description ),
		);
		if ( $image ) {
			$tags[] = array( 'property', 'og:image', $image );
			$tags[] = array( 'name', 'twitter:image', $image );
		}

		echo "\n";
		foreach ( $tags as $tag ) {
			printf(
				'<meta %s="%s" content="%s">' . "\n",
				esc_attr( $tag[0] ) === 'property' ? 'property' : 'name',
				esc_attr( $tag[1] ),
				esc_attr( $tag[2] )
			);
		}
	}
}
