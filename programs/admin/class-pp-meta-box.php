<?php
/**
 * "Program Details" meta box: schedule line + link.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_Meta_Box {

	public static function init() {
		add_action( 'add_meta_boxes_' . PP_CPT::POST_TYPE, array( __CLASS__, 'add' ) );
		add_action( 'save_post_' . PP_CPT::POST_TYPE, array( __CLASS__, 'save' ) );
	}

	public static function add() {
		add_meta_box(
			'pp-details',
			__( 'Program Details', 'parish-programs' ),
			array( __CLASS__, 'render' ),
			PP_CPT::POST_TYPE,
			'side',
			'high'
		);
	}

	public static function render( $post ) {
		wp_nonce_field( 'pp_meta_box', 'pp_meta_box_nonce' );
		?>
		<p>
			<label><strong><?php esc_html_e( 'Schedule line', 'parish-programs' ); ?></strong><br>
				<input type="text" class="widefat" name="pp_date_text" value="<?php echo esc_attr( get_post_meta( $post->ID, '_pp_date_text', true ) ); ?>" placeholder="<?php esc_attr_e( 'Tuesdays · Aug 4 – Oct 6 · 6:30 PM', 'parish-programs' ); ?>">
			</label><br>
			<span class="description"><?php esc_html_e( 'Optional; shown in accent color under the title. Free text — days, dates, times, location, whatever fits.', 'parish-programs' ); ?></span>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Link URL', 'parish-programs' ); ?></strong><br>
				<input type="url" class="widefat" name="pp_link_url" value="<?php echo esc_attr( get_post_meta( $post->ID, '_pp_link_url', true ) ); ?>" placeholder="https://…">
			</label><br>
			<span class="description"><?php esc_html_e( 'Where the card\'s button links — a ministry page, registration form, or outside site. Without a URL the card shows no button.', 'parish-programs' ); ?></span>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Link text', 'parish-programs' ); ?></strong><br>
				<input type="text" class="widefat" name="pp_link_text" value="<?php echo esc_attr( get_post_meta( $post->ID, '_pp_link_text', true ) ); ?>" placeholder="<?php esc_attr_e( 'Learn More', 'parish-programs' ); ?>">
			</label><br>
			<span class="description"><?php esc_html_e( 'e.g. Learn More, Register, Sign Up, Join Study. Blank means "Learn More".', 'parish-programs' ); ?></span>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Display order', 'parish-programs' ); ?></strong><br>
				<input type="number" step="1" name="pp_menu_order" value="<?php echo esc_attr( $post->menu_order ); ?>">
			</label><br>
			<span class="description"><?php esc_html_e( 'Cards appear lowest number first; programs with the same number sort alphabetically. Leave gaps (10, 20, 30…) so new programs can slot in between.', 'parish-programs' ); ?></span>
		</p>
		<p class="description"><?php esc_html_e( 'The description is the main editor content, and the image is the featured image.', 'parish-programs' ); ?></p>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['pp_meta_box_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['pp_meta_box_nonce'] ), 'pp_meta_box' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['pp_menu_order'] ) ) {
			$order = (int) $_POST['pp_menu_order'];
			if ( $order !== (int) get_post_field( 'menu_order', $post_id ) ) {
				// Unhook first — wp_update_post re-fires save_post.
				remove_action( 'save_post_' . PP_CPT::POST_TYPE, array( __CLASS__, 'save' ) );
				wp_update_post(
					array(
						'ID'         => $post_id,
						'menu_order' => $order,
					)
				);
				add_action( 'save_post_' . PP_CPT::POST_TYPE, array( __CLASS__, 'save' ) );
			}
		}

		if ( isset( $_POST['pp_date_text'] ) ) {
			$date_text = sanitize_text_field( wp_unslash( $_POST['pp_date_text'] ) );
			if ( '' === $date_text ) {
				delete_post_meta( $post_id, '_pp_date_text' );
			} else {
				update_post_meta( $post_id, '_pp_date_text', $date_text );
			}
		}

		if ( isset( $_POST['pp_link_url'] ) ) {
			$link_url = esc_url_raw( trim( wp_unslash( $_POST['pp_link_url'] ) ), array( 'http', 'https' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			// esc_url_raw happily mangles junk into a "URL" (http://not%20a%20url);
			// wp_http_validate_url rejects what a visitor couldn't actually open.
			if ( '' === $link_url || false === wp_http_validate_url( $link_url ) ) {
				delete_post_meta( $post_id, '_pp_link_url' );
			} else {
				update_post_meta( $post_id, '_pp_link_url', $link_url );
			}
		}

		if ( isset( $_POST['pp_link_text'] ) ) {
			$link_text = sanitize_text_field( wp_unslash( $_POST['pp_link_text'] ) );
			if ( '' === $link_text ) {
				delete_post_meta( $post_id, '_pp_link_text' );
			} else {
				update_post_meta( $post_id, '_pp_link_text', $link_text );
			}
		}
	}
}
