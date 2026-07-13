<?php
/**
 * Single event page rendering: header block prepended via the_content,
 * cancelled-page grace window, and 410 for long-gone events.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_Content {

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ) );
		add_action( 'template_redirect', array( __CLASS__, 'gone_after_grace' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
	}

	/**
	 * Whether a removed post is still inside its "cancelled" grace window,
	 * during which its page stays reachable so search engines can pick up
	 * the cancellation.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function in_cancelled_grace( $post_id ) {
		$removed_at = (int) get_post_meta( $post_id, '_pe_removed_at', true );
		$grace_days = (int) PE_Settings::get( 'cancelled_grace_days' );
		return $removed_at && ( time() < $removed_at + $grace_days * DAY_IN_SECONDS );
	}

	/**
	 * After the grace window, a removed event's URL is permanently gone.
	 */
	public static function gone_after_grace() {
		if ( ! is_singular( PE_CPT::POST_TYPE ) ) {
			return;
		}
		$post = get_queried_object();
		if ( $post && PE_CPT::STATUS_REMOVED === $post->post_status && ! self::in_cancelled_grace( $post->ID ) ) {
			status_header( 410 );
			nocache_headers();
			include get_404_template();
			exit;
		}
	}

	/**
	 * Prepend the event header block; linkify the public description.
	 *
	 * @param string $content Post content (plain public text from import).
	 * @return string
	 */
	public static function filter_content( $content ) {
		if ( ! is_singular( PE_CPT::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		$html    = '';

		if ( PE_CPT::STATUS_REMOVED === get_post_status( $post_id ) ) {
			$html .= '<div class="pe-cancelled-banner">' . esc_html__( 'This event has been cancelled.', 'parish-events' ) . '</div>';
		}

		$html .= self::render_header_block( $post_id );

		// Add-to-calendar buttons for upcoming, published events.
		if ( 'publish' === get_post_status( $post_id ) && ! pe_event_is_past( $post_id ) ) {
			$google = PE_ICS::google_url( $post_id );
			$html  .= '<p class="pe-add-to-calendar">';
			$html  .= '<a class="pe-cal-btn" href="' . esc_url( add_query_arg( 'pe_ics', $post_id, home_url( '/' ) ) ) . '">' . esc_html__( 'Add to calendar (.ics)', 'parish-events' ) . '</a>';
			if ( $google ) {
				$html .= ' <a class="pe-cal-btn" target="_blank" rel="noopener noreferrer" href="' . esc_url( $google ) . '">' . esc_html__( 'Google Calendar', 'parish-events' ) . '</a>';
			}
			$html .= '</p>';
		}

		$desc = trim( $content );
		if ( '' !== $desc ) {
			$desc = wpautop( make_clickable( $desc ) );
			$desc = wp_kses(
				$desc,
				array(
					'a'  => array(
						'href'   => true,
						'rel'    => true,
						'target' => true,
					),
					'p'  => array(),
					'br' => array(),
				)
			);
			// External links open in a new tab (port of linkifyText behavior).
			// make_clickable emits exactly rel="nofollow"; merge rather than
			// adding a second rel attribute.
			$desc  = str_replace( 'rel="nofollow"', 'target="_blank" rel="nofollow noopener noreferrer"', $desc );
			$html .= '<div class="pe-description">' . $desc . '</div>';
		}

		return $html;
	}

	/**
	 * The date/time/location/group header block.
	 *
	 * @param int $post_id Post ID.
	 * @return string HTML.
	 */
	public static function render_header_block( $post_id ) {
		$date     = get_post_meta( $post_id, '_pe_event_date', true );
		$all_day  = '1' === get_post_meta( $post_id, '_pe_all_day', true );
		$location = get_post_meta( $post_id, '_pe_location', true );
		$group    = get_post_meta( $post_id, '_pe_group_name', true );
		$type     = get_post_meta( $post_id, '_pe_event_type', true );

		if ( ! $date ) {
			return '';
		}

		$date_label = wp_date( 'l, F j, Y', strtotime( $date . 'T12:00:00' ), pe_timezone() );

		if ( $all_day ) {
			$time_label = __( 'All day', 'parish-events' );
		} else {
			$start      = pe_format_time( get_post_meta( $post_id, '_pe_start_time', true ) );
			$end        = pe_format_time( get_post_meta( $post_id, '_pe_end_time', true ) );
			$time_label = $end ? $start . ' – ' . $end : $start;
		}

		// Values are pre-escaped HTML; location may carry a link or a
		// <details> disclosure from the location directory.
		$rows = array(
			__( 'Date', 'parish-events' )     => esc_html( $date_label ),
			__( 'Time', 'parish-events' )     => esc_html( $time_label ),
			__( 'Location', 'parish-events' ) => pe_location_html( $location ),
			__( 'Group', 'parish-events' )    => esc_html( $group ),
			__( 'Type', 'parish-events' )     => esc_html( $type ),
		);

		$html = '<dl class="pe-event-details">';
		foreach ( $rows as $label => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			$html .= '<dt>' . esc_html( $label ) . '</dt><dd>' . $value . '</dd>';
		}
		$html .= '</dl>';

		return $html;
	}

	public static function maybe_enqueue() {
		if ( is_singular( PE_CPT::POST_TYPE ) ) {
			wp_enqueue_style( 'parish-events', PE_PLUGIN_URL . 'assets/css/calendar.css', array(), PE_VERSION );
		}
	}
}
