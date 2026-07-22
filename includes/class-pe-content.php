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
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'rest_gone_after_grace' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
	}

	/**
	 * Mirror the front-end 410 in the REST API: the pe_removed status is
	 * registered public (for the grace-window page), so without this a
	 * removed event would stay readable at /wp/v2/parish_event/<id> forever.
	 * (rest_request_before_callbacks is the hook that may return WP_Error;
	 * rest_prepare_* must return a response object.)
	 *
	 * @param mixed           $response Current response (null when unhandled).
	 * @param array           $handler  Route handler.
	 * @param WP_REST_Request $request  The request.
	 * @return mixed
	 */
	public static function rest_gone_after_grace( $response, $handler, $request ) {
		if ( null !== $response ) {
			return $response;
		}

		$type_obj  = get_post_type_object( PE_CPT::POST_TYPE );
		$rest_base = ( $type_obj && $type_obj->rest_base ) ? $type_obj->rest_base : PE_CPT::POST_TYPE;
		if ( ! preg_match( '#^/wp/v2/' . preg_quote( $rest_base, '#' ) . '/(\d+)$#', $request->get_route(), $m ) ) {
			return $response;
		}

		$post = get_post( (int) $m[1] );
		if (
			$post
			&& PE_CPT::POST_TYPE === $post->post_type
			&& PE_CPT::STATUS_REMOVED === $post->post_status
			// Redirect posts are gone from REST immediately (their content
			// lives at the redirect target); others get the grace window.
			&& ( '' !== get_post_meta( $post->ID, '_pe_redirect_url', true ) || ! self::in_cancelled_grace( $post->ID ) )
			&& ! current_user_can( 'edit_post', $post->ID )
		) {
			return new WP_Error(
				'pe_event_gone',
				__( 'This event is no longer available.', 'parish-events' ),
				array( 'status' => 410 )
			);
		}

		return $response;
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
	 * Removed events: posts delisted by a linked suppression rule redirect
	 * their saved/indexed URLs to the rule's destination permanently; all
	 * other removed events are gone after the grace window.
	 */
	public static function gone_after_grace() {
		if ( ! is_singular( PE_CPT::POST_TYPE ) ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post || PE_CPT::STATUS_REMOVED !== $post->post_status ) {
			return;
		}

		$redirect = get_post_meta( $post->ID, '_pe_redirect_url', true );
		if ( '' !== $redirect ) {
			wp_redirect( esc_url_raw( $redirect ), 301 ); // phpcs:ignore WordPress.Security.SafeRedirect -- admin-configured destination, sanitized on save.
			exit;
		}

		if ( ! self::in_cancelled_grace( $post->ID ) ) {
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

		// "Cancelled" is only ever an explicit admin statement (_pe_cancelled).
		// Mere absence from the feed gets a neutral notice — events leave the
		// feed for many reasons besides cancellation.
		if ( '1' === get_post_meta( $post_id, '_pe_cancelled', true ) ) {
			$html .= '<div class="pe-banner pe-cancelled-banner">' . esc_html__( 'This event has been cancelled.', 'parish-events' ) . '</div>';
		} elseif ( PE_CPT::STATUS_REMOVED === get_post_status( $post_id ) ) {
			$html .= '<div class="pe-banner pe-removed-banner">' . esc_html__( 'This event is no longer listed on the parish calendar. Please contact the parish office with any questions.', 'parish-events' ) . '</div>';
		}

		// Featured video promotes the event from above the details card.
		$embed = pe_video_embed_url( get_post_meta( $post_id, '_pe_video_url', true ) );
		if ( '' !== $embed ) {
			$html .= '<div class="pe-featured-video"><iframe src="' . esc_url( $embed ) . '" title="' . esc_attr( get_the_title( $post_id ) ) . '" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
		}

		$html .= self::render_header_block( $post_id );

		// Registration and add-to-calendar buttons for upcoming, published
		// events. Registration leads: it's the action the visitor came for.
		if ( 'publish' === get_post_status( $post_id ) && ! pe_event_is_past( $post_id ) ) {
			$google = PE_ICS::google_url( $post_id );
			$html  .= '<p class="pe-add-to-calendar">';
			$reg    = get_post_meta( $post_id, '_pe_registration_url', true );
			if ( '' !== $reg ) {
				$html .= '<a class="pe-register-btn" target="_blank" rel="noopener noreferrer" href="' . esc_url( $reg ) . '">' . esc_html__( 'Register / RSVP', 'parish-events' ) . '</a> ';
			}
			$html  .= '<a class="pe-cal-btn" href="' . esc_url( add_query_arg( 'pe_ics', $post_id, home_url( '/' ) ) ) . '">' . esc_html__( 'Add to calendar (.ics)', 'parish-events' ) . '</a>';
			if ( $google ) {
				$html .= ' <a class="pe-cal-btn" target="_blank" rel="noopener noreferrer" href="' . esc_url( $google ) . '">' . esc_html__( 'Google Calendar', 'parish-events' ) . '</a>';
			}
			$html .= '</p>';
		}

		// Flyer image below the details card, linked to the full-size file.
		// Admin-owned like the featured image: renders regardless of override.
		$flyer_id = (int) get_post_meta( $post_id, '_pe_flyer_id', true );
		if ( $flyer_id && wp_attachment_is_image( $flyer_id ) ) {
			$attrs = array( 'loading' => 'lazy' );
			if ( '' === trim( (string) get_post_meta( $flyer_id, '_wp_attachment_image_alt', true ) ) ) {
				/* translators: %s: event title. */
				$attrs['alt'] = sprintf( __( 'Flyer for %s', 'parish-events' ), get_the_title( $post_id ) );
			}
			$html .= '<figure class="pe-event-flyer"><a href="' . esc_url( wp_get_attachment_image_url( $flyer_id, 'full' ) ) . '">'
				. wp_get_attachment_image( $flyer_id, 'large', false, $attrs )
				. '</a></figure>';
		}

		// The linkify + kses pipeline exists for import-owned plain text.
		// Admin-authored content (override on, manually created, or block
		// markup) has already been through the editor's own sanitization and
		// core's content filters — re-ksesing it would strip headings,
		// images, and lists.
		$raw         = get_post_field( 'post_content', $post_id );
		$admin_owned = '1' === get_post_meta( $post_id, '_pe_override', true )
			|| '' === get_post_meta( $post_id, '_pe_uid', true )
			|| has_blocks( $raw );

		if ( $admin_owned ) {
			return $html . $content;
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
			__( 'Cost', 'parish-events' )     => esc_html( get_post_meta( $post_id, '_pe_cost', true ) ),
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
			pe_enqueue_accent();
		}
	}
}
