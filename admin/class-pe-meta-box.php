<?php
/**
 * "Parish Event Sync" meta box: override/featured flags + read-only sync info.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_Meta_Box {

	public static function init() {
		add_action( 'add_meta_boxes_' . PE_CPT::POST_TYPE, array( __CLASS__, 'add' ) );
		add_action( 'save_post_' . PE_CPT::POST_TYPE, array( __CLASS__, 'save' ) );
	}

	public static function add() {
		add_meta_box(
			'pe-sync',
			__( 'Parish Event Sync', 'parish-events' ),
			array( __CLASS__, 'render' ),
			PE_CPT::POST_TYPE,
			'side',
			'high'
		);
	}

	public static function render( $post ) {
		wp_nonce_field( 'pe_meta_box', 'pe_meta_box_nonce' );

		$override  = get_post_meta( $post->ID, '_pe_override', true );
		$featured  = get_post_meta( $post->ID, '_pe_featured', true );
		$uid       = get_post_meta( $post->ID, '_pe_uid', true );
		$last_seen = (int) get_post_meta( $post->ID, '_pe_last_seen', true );
		$note      = get_post_meta( $post->ID, '_pe_sync_note', true );
		?>
		<p>
			<label>
				<input type="checkbox" name="pe_override" value="1" <?php checked( $override, '1' ); ?> id="pe-override-toggle">
				<strong><?php esc_html_e( 'Manual override', 'parish-events' ); ?></strong>
			</label><br>
			<span class="description"><?php esc_html_e( 'Imports will not change anything about this event — content, details below, or status. If it disappears from the parish calendar it is flagged for review instead of being removed. Unchecking re-syncs everything from the feed on the next import.', 'parish-events' ); ?></span>
		</p>

		<fieldset id="pe-override-fields" <?php disabled( '1' !== $override ); ?>>
			<legend class="screen-reader-text"><?php esc_html_e( 'Event details (editable with override)', 'parish-events' ); ?></legend>
			<p>
				<label><?php esc_html_e( 'Event date', 'parish-events' ); ?><br>
					<input type="date" name="pe_event_date" value="<?php echo esc_attr( get_post_meta( $post->ID, '_pe_event_date', true ) ); ?>">
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="pe_all_day" value="1" <?php checked( get_post_meta( $post->ID, '_pe_all_day', true ), '1' ); ?>>
					<?php esc_html_e( 'All day', 'parish-events' ); ?>
				</label>
			</p>
			<p>
				<label><?php esc_html_e( 'Start', 'parish-events' ); ?>
					<input type="time" name="pe_start_time" value="<?php echo esc_attr( substr( get_post_meta( $post->ID, '_pe_start_time', true ), 0, 5 ) ); ?>">
				</label>
				<label><?php esc_html_e( 'End', 'parish-events' ); ?>
					<input type="time" name="pe_end_time" value="<?php echo esc_attr( substr( get_post_meta( $post->ID, '_pe_end_time', true ), 0, 5 ) ); ?>">
				</label>
			</p>
			<p>
				<label><?php esc_html_e( 'Location', 'parish-events' ); ?><br>
					<input type="text" class="widefat" name="pe_location" value="<?php echo esc_attr( get_post_meta( $post->ID, '_pe_location', true ) ); ?>">
				</label>
			</p>
			<p>
				<label><?php esc_html_e( 'Group', 'parish-events' ); ?><br>
					<input type="text" class="widefat" name="pe_group_name" value="<?php echo esc_attr( get_post_meta( $post->ID, '_pe_group_name', true ) ); ?>">
				</label>
			</p>
			<p>
				<label><?php esc_html_e( 'Event type', 'parish-events' ); ?><br>
					<input type="text" class="widefat" name="pe_event_type" value="<?php echo esc_attr( get_post_meta( $post->ID, '_pe_event_type', true ) ); ?>">
				</label>
			</p>
		</fieldset>
		<script>
		( function () {
			var toggle = document.getElementById( 'pe-override-toggle' );
			var fields = document.getElementById( 'pe-override-fields' );
			if ( toggle && fields ) {
				toggle.addEventListener( 'change', function () {
					fields.disabled = ! toggle.checked;
				} );
			}
		} )();
		</script>
		<p>
			<label>
				<input type="checkbox" name="pe_featured" value="1" <?php checked( $featured, '1' ); ?>>
				<strong><?php esc_html_e( 'Featured event', 'parish-events' ); ?></strong>
			</label><br>
			<span class="description"><?php esc_html_e( 'Shown in the featured events cards. The featured image can always be set, override or not.', 'parish-events' ); ?></span>
		</p>
		<p>
			<label>
				<input type="checkbox" name="pe_cancelled" value="1" <?php checked( get_post_meta( $post->ID, '_pe_cancelled', true ), '1' ); ?>>
				<strong><?php esc_html_e( 'Mark as cancelled', 'parish-events' ); ?></strong>
			</label><br>
			<span class="description"><?php esc_html_e( 'Shows a "this event has been cancelled" notice on the event page and tells search engines the event is cancelled. An event merely disappearing from the parish calendar feed shows a neutral notice instead — use this only when the event is actually cancelled.', 'parish-events' ); ?></span>
		</p>
		<p>
			<?php $video_url = get_post_meta( $post->ID, '_pe_video_url', true ); ?>
			<label><strong><?php esc_html_e( 'Featured video URL', 'parish-events' ); ?></strong><br>
				<input type="url" class="widefat" name="pe_video_url" value="<?php echo esc_attr( $video_url ); ?>" placeholder="https://youtu.be/…">
			</label><br>
			<span class="description"><?php esc_html_e( 'A YouTube or Vimeo link, shown above the event details on the event page. Like the featured image, imports never change it — override or not.', 'parish-events' ); ?></span>
			<?php if ( '' !== $video_url && '' === pe_video_embed_url( $video_url ) ) : ?>
				<br><span style="color:#b32d2e;"><?php esc_html_e( 'This does not look like a YouTube or Vimeo link, so no video will be shown.', 'parish-events' ); ?></span>
			<?php endif; ?>
		</p>

		<?php if ( $uid ) : ?>
			<hr>
			<p class="pe-sync-info">
				<?php esc_html_e( 'UID:', 'parish-events' ); ?> <code><?php echo esc_html( $uid ); ?></code><br>
				<?php esc_html_e( 'CCB event ID:', 'parish-events' ); ?> <code><?php echo esc_html( get_post_meta( $post->ID, '_pe_ccb_event_id', true ) ); ?></code><br>
				<?php esc_html_e( 'Date:', 'parish-events' ); ?> <?php echo esc_html( get_post_meta( $post->ID, '_pe_event_date', true ) ); ?><br>
				<?php esc_html_e( 'Time:', 'parish-events' ); ?>
				<?php
				if ( '1' === get_post_meta( $post->ID, '_pe_all_day', true ) ) {
					esc_html_e( 'All day', 'parish-events' );
				} else {
					echo esc_html( pe_format_time( get_post_meta( $post->ID, '_pe_start_time', true ) ) . ' – ' . pe_format_time( get_post_meta( $post->ID, '_pe_end_time', true ) ) );
				}
				?>
				<br>
				<?php esc_html_e( 'Location:', 'parish-events' ); ?>
				<?php
				$raw = get_post_meta( $post->ID, '_pe_location_raw', true );
				$loc = get_post_meta( $post->ID, '_pe_location', true );
				echo esc_html( $raw === $loc ? $loc : $raw . ' → ' . $loc );
				?>
				<br>
				<?php esc_html_e( 'Group:', 'parish-events' ); ?> <?php echo esc_html( get_post_meta( $post->ID, '_pe_group_name', true ) ); ?><br>
				<?php esc_html_e( 'Leader:', 'parish-events' ); ?>
				<?php
				// Admin-eyes-only; leader contact info is never rendered on
				// the public site.
				$leader = array_filter(
					array(
						get_post_meta( $post->ID, '_pe_leader_name', true ),
						get_post_meta( $post->ID, '_pe_leader_phone', true ),
						get_post_meta( $post->ID, '_pe_leader_email', true ),
					)
				);
				echo esc_html( $leader ? implode( ', ', $leader ) : '—' );
				?>
				<br>
				<?php esc_html_e( 'Last seen in feed:', 'parish-events' ); ?>
				<?php echo esc_html( $last_seen ? wp_date( 'Y-m-d H:i', $last_seen ) : '—' ); ?>
				<?php if ( $note ) : ?>
					<br><?php esc_html_e( 'Note:', 'parish-events' ); ?> <strong><?php echo esc_html( $note ); ?></strong>
				<?php endif; ?>
			</p>
		<?php else : ?>
			<hr>
			<p class="description"><?php esc_html_e( 'This event was created manually and is not linked to the parish calendar feed. Imports will never modify or remove it.', 'parish-events' ); ?></p>
		<?php endif; ?>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['pe_meta_box_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['pe_meta_box_nonce'] ), 'pe_meta_box' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$was_override = get_post_meta( $post_id, '_pe_override', true );
		$override     = empty( $_POST['pe_override'] ) ? '0' : '1';
		update_post_meta( $post_id, '_pe_override', $override );
		update_post_meta( $post_id, '_pe_featured', empty( $_POST['pe_featured'] ) ? '0' : '1' );
		update_post_meta( $post_id, '_pe_cancelled', empty( $_POST['pe_cancelled'] ) ? '0' : '1' );

		// Checking override on an importer-removed post is a rescue: the
		// admin is taking ownership, so it goes back to published (there is
		// no editor UI for the custom status). Unhook first — wp_update_post
		// re-fires save_post.
		if ( '1' === $override && PE_CPT::STATUS_REMOVED === get_post_status( $post_id ) ) {
			remove_action( 'save_post_' . PE_CPT::POST_TYPE, array( __CLASS__, 'save' ) );
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);
			add_action( 'save_post_' . PE_CPT::POST_TYPE, array( __CLASS__, 'save' ) );
			delete_post_meta( $post_id, '_pe_removed_at' );
		}

		// Saved before the override gate below: the video slot is admin-owned
		// regardless of override status, like the featured image.
		if ( isset( $_POST['pe_video_url'] ) ) {
			$video_url = esc_url_raw( trim( wp_unslash( $_POST['pe_video_url'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( '' === $video_url ) {
				delete_post_meta( $post_id, '_pe_video_url' );
			} else {
				update_post_meta( $post_id, '_pe_video_url', $video_url );
			}
		}

		// Turning override off must force a full re-sync on the next import;
		// otherwise an unchanged feed hash would leave the manual edits in
		// place indefinitely.
		if ( '1' === $was_override && '1' !== $override ) {
			delete_post_meta( $post_id, '_pe_import_hash' );
		}

		if ( '1' !== $override ) {
			return; // Detail fields only apply while overridden.
		}

		if ( isset( $_POST['pe_event_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', wp_unslash( $_POST['pe_event_date'] ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			update_post_meta( $post_id, '_pe_event_date', sanitize_text_field( wp_unslash( $_POST['pe_event_date'] ) ) );
		}

		if ( ! empty( $_POST['pe_all_day'] ) ) {
			update_post_meta( $post_id, '_pe_all_day', '1' );
			update_post_meta( $post_id, '_pe_start_time', '00:00:00' );
			update_post_meta( $post_id, '_pe_end_time', '23:59:59' );
		} else {
			update_post_meta( $post_id, '_pe_all_day', '0' );
			foreach ( array(
				'pe_start_time' => '_pe_start_time',
				'pe_end_time'   => '_pe_end_time',
			) as $field => $meta_key ) {
				if ( isset( $_POST[ $field ] ) && preg_match( '/^\d{2}:\d{2}$/', wp_unslash( $_POST[ $field ] ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) . ':00' );
				}
			}
		}

		foreach ( array(
			'pe_location'   => '_pe_location',
			'pe_group_name' => '_pe_group_name',
			'pe_event_type' => '_pe_event_type',
		) as $field => $meta_key ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}
	}
}
