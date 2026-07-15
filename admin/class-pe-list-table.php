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
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filter_controls' ) );
		add_filter( 'months_dropdown_results', array( __CLASS__, 'remove_core_months_dropdown' ), 10, 2 );
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
				if ( 0 !== (int) get_post_meta( $post_id, '_pe_flyer_id', true ) ) {
					echo '<span class="dashicons dashicons-format-image" title="' . esc_attr__( 'Event flyer', 'parish-events' ) . '"></span>';
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
	 * Distinct event months (YYYY-MM) present in the data, oldest first.
	 *
	 * @return string[]
	 */
	private static function event_months() {
		global $wpdb;
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT LEFT( pm.meta_value, 7 )
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_pe_event_date'
				   AND pm.meta_value <> ''
				   AND p.post_type = %s
				 ORDER BY 1 ASC",
				PE_CPT::POST_TYPE
			)
		);
	}

	/**
	 * Distinct group names present in the data, alphabetical.
	 *
	 * @return string[]
	 */
	private static function event_groups() {
		global $wpdb;
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_pe_group_name'
				   AND pm.meta_value <> ''
				   AND p.post_type = %s
				 ORDER BY pm.meta_value ASC",
				PE_CPT::POST_TYPE
			)
		);
	}

	/**
	 * Core's "All dates" dropdown filters by publish date, which is
	 * meaningless for imported events — the event-month filter replaces it.
	 *
	 * @param object[] $months    Month objects for the dropdown.
	 * @param string   $post_type Current list-table post type.
	 * @return object[]
	 */
	public static function remove_core_months_dropdown( $months, $post_type ) {
		return PE_CPT::POST_TYPE === $post_type ? array() : $months;
	}

	/**
	 * Month / group / timeframe dropdowns in the list-table filter bar.
	 *
	 * @param string $post_type Current list-table post type.
	 */
	public static function filter_controls( $post_type ) {
		if ( PE_CPT::POST_TYPE !== $post_type ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters, matches core behavior.
		$month = isset( $_GET['pe_month'] ) ? sanitize_text_field( wp_unslash( $_GET['pe_month'] ) ) : '';
		$group = isset( $_GET['pe_group'] ) ? sanitize_text_field( wp_unslash( $_GET['pe_group'] ) ) : '';
		$when  = isset( $_GET['pe_when'] ) ? sanitize_key( $_GET['pe_when'] ) : '';
		// phpcs:enable

		echo '<select name="pe_month">';
		echo '<option value="">' . esc_html__( 'All event months', 'parish-events' ) . '</option>';
		foreach ( self::event_months() as $m ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $m ),
				selected( $month, $m, false ),
				esc_html( wp_date( 'F Y', strtotime( $m . '-01T12:00:00' ), pe_timezone() ) )
			);
		}
		echo '</select>';

		echo '<select name="pe_group">';
		echo '<option value="">' . esc_html__( 'All groups', 'parish-events' ) . '</option>';
		foreach ( self::event_groups() as $g ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $g ),
				selected( $group, $g, false ),
				esc_html( $g )
			);
		}
		echo '</select>';

		$timeframes = array(
			''         => __( 'All dates', 'parish-events' ),
			'upcoming' => __( 'Upcoming only', 'parish-events' ),
			'past'     => __( 'Past only', 'parish-events' ),
		);
		echo '<select name="pe_when">';
		foreach ( $timeframes as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $when, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
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

		// pe_removed is registered public (required for the front-end grace
		// window), and WP_Query folds every public status into the admin "All"
		// list — ignoring show_in_admin_all_list. Pin the status list so
		// removed events only appear under their own status view.
		if ( '' === $query->get( 'post_status' ) ) {
			$query->set( 'post_status', array_values( get_post_stati( array( 'show_in_admin_all_list' => true ) ) ) );
		}

		$orderby = $query->get( 'orderby' );
		if ( 'pe_event_date' === $orderby || '' === $orderby ) {
			$query->set( 'meta_key', '_pe_event_date' );
			$query->set( 'orderby', 'meta_value' );
			if ( '' === $orderby && '' === $query->get( 'order' ) ) {
				$query->set( 'order', 'ASC' );
			}
		}

		// meta_query (not meta_key) so the filters can coexist with the sort
		// clause and with each other.
		$meta_query = (array) $query->get( 'meta_query' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters, matches core behavior.
		if ( isset( $_GET['pe_sync_note'] ) && 'missing_upstream' === $_GET['pe_sync_note'] ) {
			$meta_query[] = array(
				'key'   => '_pe_sync_note',
				'value' => 'missing_upstream',
			);
		}

		$month = isset( $_GET['pe_month'] ) ? sanitize_text_field( wp_unslash( $_GET['pe_month'] ) ) : '';
		if ( preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			$meta_query[] = array(
				'key'     => '_pe_event_date',
				'value'   => array( $month . '-01', $month . '-31' ),
				'compare' => 'BETWEEN',
			);
		}

		$group = isset( $_GET['pe_group'] ) ? sanitize_text_field( wp_unslash( $_GET['pe_group'] ) ) : '';
		if ( '' !== $group ) {
			$meta_query[] = array(
				'key'   => '_pe_group_name',
				'value' => $group,
			);
		}

		$when = isset( $_GET['pe_when'] ) ? sanitize_key( $_GET['pe_when'] ) : '';
		if ( 'upcoming' === $when || 'past' === $when ) {
			$meta_query[] = array(
				'key'     => '_pe_event_date',
				'value'   => pe_today(),
				'compare' => 'upcoming' === $when ? '>=' : '<',
			);
		}
		// phpcs:enable

		if ( count( $meta_query ) > 0 ) {
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
