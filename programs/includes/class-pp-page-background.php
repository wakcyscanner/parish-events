<?php
/**
 * Per-page "celestial" background: the navy gradient with drifting golden
 * glows from the legacy Come to Me page, as a checkbox on any Page.
 *
 * The theme's header and footer are untouched; the gradient sits behind the
 * content area. Themes that paint their content wrappers white will cover
 * it — background.css clears common wrapper selectors, and site-specific
 * ones can be added via the pp_celestial_bg_css filter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_Page_Background {

	const META_KEY = '_pp_celestial_bg';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes_page', array( __CLASS__, 'add_meta_box' ) );
			add_action( 'save_post_page', array( __CLASS__, 'save' ) );
		}
	}

	public static function register_meta() {
		register_post_meta(
			'page',
			self::META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => '__return_false',
			)
		);
	}

	private static function is_active() {
		return is_page() && '1' === get_post_meta( get_queried_object_id(), self::META_KEY, true );
	}

	public static function enqueue() {
		if ( ! self::is_active() ) {
			return;
		}
		wp_enqueue_style(
			'pp-background',
			PP_PLUGIN_URL . 'assets/css/background.css',
			array(),
			PP_VERSION
		);

		$extra = apply_filters( 'pp_celestial_bg_css', '' );
		if ( '' !== $extra ) {
			wp_add_inline_style( 'pp-background', $extra );
		}
	}

	public static function body_class( $classes ) {
		if ( self::is_active() ) {
			$classes[] = 'pp-celestial-bg';
		}
		return $classes;
	}

	public static function add_meta_box() {
		add_meta_box(
			'pp-page-background',
			__( 'Parish Programs', 'parish-programs' ),
			array( __CLASS__, 'render_meta_box' ),
			'page',
			'side'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'pp_page_bg', 'pp_page_bg_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="pp_celestial_bg" value="1" <?php checked( get_post_meta( $post->ID, self::META_KEY, true ), '1' ); ?>>
				<strong><?php esc_html_e( 'Celestial background', 'parish-programs' ); ?></strong>
			</label><br>
			<span class="description"><?php esc_html_e( 'Give this page the Come to Me look: a deep navy gradient with softly drifting golden glows behind the content.', 'parish-programs' ); ?></span>
		</p>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['pp_page_bg_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['pp_page_bg_nonce'] ), 'pp_page_bg' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( empty( $_POST['pp_celestial_bg'] ) ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, '1' );
		}
	}
}
