<?php
/**
 * List-table customizations for the parish_event screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_List_Table {

	public static function init() {
		add_filter( 'manage_' . PE_CPT::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . PE_CPT::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-' . PE_CPT::POST_TYPE . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'order_and_filter' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
		add_action( 'admin_footer-post.php', array( __CLASS__, 'status_dropdown_script' ) );
	}

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['pe_event_date'] = __( 'Event Date', 'parish-events' );
				$new['pe_time']       = __( 'Time', 'parish-events' );
				$new['pe_location']   = __( 'Location', 'parish-events' );
				$new['pe_uid']        = __( 'UID', 'parish-events' );
				$new['pe_sync']       = __( 'Sync', 'parish-events' );
				$new['pe_flags']      = __( 'Flags', 'parish-events' );
			}
		}
		unset( $new['date'] );
		return $new;
	}

	public static function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'pe_event_date':
				$date = get_post_meta( $post_id, '_pe_event_date', true );
				echo $date ? esc_html( wp_date( 'D, M j, Y', strtotime( $date . 'T12:00:00' ) ) ) : '&mdash;';
				break;

			case 'pe_time':
				if ( '1' === get_post_meta( $post_id, '_pe_all_day', true ) ) {
					esc_html_e( 'All day', 'parish-events' );
				} else {
					echo esc_html( pe_format_time( get_post_meta( $post_id, '_pe_start_time', true ) ) );
				}
				break;

			case 'pe_location':
				echo esc_html( get_post_meta( $post_id, '_pe_location', true ) );
				break;

			case 'pe_uid':
				echo '<code>' . esc_html( get_post_meta( $post_id, '_pe_uid', true ) ) . '</code>';
				break;

			case 'pe_sync':
				if ( PE_CPT::STATUS_REMOVED === get_post_status( $post_id ) ) {
					echo '<span class="pe-sync pe-sync-removed">' . esc_html__( 'Removed', 'parish-events' ) . '</span>';
				} elseif ( 'missing_upstream' === get_post_meta( $post_id, '_pe_sync_note', true ) ) {
					echo '<span class="pe-sync pe-sync-warning dashicons-before dashicons-warning">' . esc_html__( 'Missing upstream', 'parish-events' ) . '</span>';
				} elseif ( '1' === get_post_meta( $post_id, '_pe_override', true ) ) {
					echo '<span class="pe-sync pe-sync-override">' . esc_html__( 'Overridden', 'parish-events' ) . '</span>';
				} else {
					echo '<span class="pe-sync pe-sync-ok">' . esc_html__( 'OK', 'parish-events' ) . '</span>';
				}
				break;

			case 'pe_flags':
				if ( '1' === get_post_meta( $post_id, '_pe_featured', true ) ) {
					echo '<span class="dashicons dashicons-star-filled" title="' . esc_attr__( 'Featured', 'parish-events' ) . '"></span>';
				}
				if ( '' !== get_post_meta( $post_id, '_pe_video_url', true ) ) {
					echo '<span class="dashicons dashicons-video-alt3" title="' . esc_attr__( 'Featured video', 'parish-events' ) . '"></span>';
				}
				if ( '1' === get_post_meta( $post_id, '_pe_cancelled', true ) ) {
					echo '<span class="dashicons dashicons-dismiss" title="' . esc_attr__( 'Cancelled', 'parish-events' ) . '"></span>';
				}
				if ( '1' === get_post_meta( $post_id, '_pe_override', true ) ) {
					echo '<span class="dashicons dashicons-lock" title="' . esc_attr__( 'Manual override', 'parish-events' ) . '"></span>';
				}
				break;
		}
	}

	public static function sortable_columns( $columns ) {
		$columns['pe_event_date'] = 'pe_event_date';
		return $columns;
	}

	/**
	 * Default-sort the admin list by event date and support the
	 * pe_sync_note filter link used by the review notice.
	 *
	 * @param WP_Query $query Main admin query.
	 */
	public static function order_and_filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || PE_CPT::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( 'pe_event_date' === $orderby || '' === $orderby ) {
			$query->set( 'meta_key', '_pe_event_date' );
			$query->set( 'orderby', 'meta_value' );
			if ( '' === $orderby && '' === $query->get( 'order' ) ) {
				$query->set( 'order', 'ASC' );
			}
		}

		// meta_query (not meta_key) so it can coexist with the sort clause.
		if ( isset( $_GET['pe_sync_note'] ) && 'missing_upstream' === $_GET['pe_sync_note'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$meta_query   = (array) $query->get( 'meta_query' );
			$meta_query[] = array(
				'key'   => '_pe_sync_note',
				'value' => 'missing_upstream',
			);
			$query->set( 'meta_query', $meta_query );
		}
	}

	public static function post_states( $states, $post ) {
		if ( PE_CPT::POST_TYPE === $post->post_type && PE_CPT::STATUS_REMOVED === $post->post_status ) {
			$states['pe_removed'] = __( 'Removed upstream', 'parish-events' );
		}
		return $states;
	}

	/**
	 * WordPress doesn't render custom statuses in the classic editor's Status
	 * dropdown; inject the option so admins can set/restore it manually.
	 */
	public static function status_dropdown_script() {
		global $post;
		if ( ! $post || PE_CPT::POST_TYPE !== $post->post_type ) {
			return;
		}
		$selected = PE_CPT::STATUS_REMOVED === $post->post_status;
		?>
		<script>
		( function () {
			var select = document.getElementById( 'post_status' );
			if ( ! select ) {
				return;
			}
			var option = document.createElement( 'option' );
			option.value = <?php echo wp_json_encode( PE_CPT::STATUS_REMOVED ); ?>;
			option.textContent = <?php echo wp_json_encode( __( 'Removed upstream', 'parish-events' ) ); ?>;
			<?php if ( $selected ) : ?>
			option.selected = true;
			var display = document.getElementById( 'post-status-display' );
			if ( display ) {
				display.textContent = option.textContent;
			}
			<?php endif; ?>
			select.appendChild( option );
		} )();
		</script>
		<?php
	}
}
