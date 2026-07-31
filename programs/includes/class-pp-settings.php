<?php
/**
 * Settings screen under Programs → Settings. One option: pp_settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function url() {
		return admin_url( 'edit.php?post_type=' . PP_CPT::POST_TYPE . '&page=pp-settings' );
	}

	public static function add_page() {
		add_submenu_page(
			'edit.php?post_type=' . PP_CPT::POST_TYPE,
			__( 'Parish Programs Settings', 'parish-programs' ),
			__( 'Settings', 'parish-programs' ),
			'manage_options',
			'pp-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register() {
		register_setting(
			'pp_settings_group',
			'pp_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/**
	 * @param mixed $input Raw form input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		// Same rule as the meta box link: esc_url_raw mangles junk into a
		// "URL"; wp_http_validate_url rejects what a visitor couldn't open.
		$more_url = isset( $input['homepage_more_url'] ) ? esc_url_raw( trim( (string) $input['homepage_more_url'] ), array( 'http', 'https' ) ) : '';
		if ( '' !== $more_url && false === wp_http_validate_url( $more_url ) ) {
			$more_url = '';
		}

		return array(
			'homepage_enabled'         => empty( $input['homepage_enabled'] ) ? '0' : '1',
			'homepage_selector'        => isset( $input['homepage_selector'] ) ? sanitize_text_field( $input['homepage_selector'] ) : '',
			'homepage_position'        => in_array( isset( $input['homepage_position'] ) ? $input['homepage_position'] : '', array( 'before', 'after', 'prepend', 'append' ), true ) ? $input['homepage_position'] : 'after',
			'homepage_layout'          => ( isset( $input['homepage_layout'] ) && 'grid' === $input['homepage_layout'] ) ? 'grid' : 'carousel',
			'homepage_count'           => isset( $input['homepage_count'] ) ? max( 0, min( 24, (int) $input['homepage_count'] ) ) : 6,
			'homepage_heading'         => isset( $input['homepage_heading'] ) ? sanitize_text_field( $input['homepage_heading'] ) : '',
			'homepage_group'           => isset( $input['homepage_group'] ) ? sanitize_title( $input['homepage_group'] ) : '',
			'homepage_more_url'        => $more_url,
			'homepage_more_text'       => isset( $input['homepage_more_text'] ) ? sanitize_text_field( $input['homepage_more_text'] ) : '',
			'delete_data_on_uninstall' => empty( $input['delete_data_on_uninstall'] ) ? '0' : '1',
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = pp_get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Parish Programs Settings', 'parish-programs' ); ?></h1>

			<p><?php esc_html_e( 'Programs can be placed on any page with the "Parish Programs" block or the [parish_programs] shortcode. The settings below only control the automatic homepage injection, for themes whose homepage template does not accept blocks.', 'parish-programs' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'pp_settings_group' ); ?>

				<h2><?php esc_html_e( 'Homepage injection', 'parish-programs' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show on homepage', 'parish-programs' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="pp_settings[homepage_enabled]" value="1" <?php checked( $settings['homepage_enabled'], '1' ); ?>>
								<?php esc_html_e( 'Inject the programs display into the homepage', 'parish-programs' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp-selector"><?php esc_html_e( 'Target CSS selector', 'parish-programs' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="pp-selector" name="pp_settings[homepage_selector]" value="<?php echo esc_attr( $settings['homepage_selector'] ); ?>" placeholder=".home-section-news">
							<p class="description"><?php esc_html_e( 'The homepage element to anchor on, e.g. ".hero-banner" or "#main .row:nth-child(2)". Find it with your browser\'s Inspect tool. If nothing matches, the display is simply not shown — the homepage is never broken.', 'parish-programs' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp-position"><?php esc_html_e( 'Placement', 'parish-programs' ); ?></label></th>
						<td>
							<select id="pp-position" name="pp_settings[homepage_position]">
								<option value="before" <?php selected( $settings['homepage_position'], 'before' ); ?>><?php esc_html_e( 'Before the target element', 'parish-programs' ); ?></option>
								<option value="after" <?php selected( $settings['homepage_position'], 'after' ); ?>><?php esc_html_e( 'After the target element', 'parish-programs' ); ?></option>
								<option value="prepend" <?php selected( $settings['homepage_position'], 'prepend' ); ?>><?php esc_html_e( 'Inside the target, at the top', 'parish-programs' ); ?></option>
								<option value="append" <?php selected( $settings['homepage_position'], 'append' ); ?>><?php esc_html_e( 'Inside the target, at the bottom', 'parish-programs' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp-layout"><?php esc_html_e( 'Layout', 'parish-programs' ); ?></label></th>
						<td>
							<select id="pp-layout" name="pp_settings[homepage_layout]">
								<option value="carousel" <?php selected( $settings['homepage_layout'], 'carousel' ); ?>><?php esc_html_e( 'Carousel', 'parish-programs' ); ?></option>
								<option value="grid" <?php selected( $settings['homepage_layout'], 'grid' ); ?>><?php esc_html_e( 'Grid', 'parish-programs' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp-count"><?php esc_html_e( 'Number of programs', 'parish-programs' ); ?></label></th>
						<td>
							<input type="number" id="pp-count" name="pp_settings[homepage_count]" value="<?php echo esc_attr( $settings['homepage_count'] ); ?>" min="0" max="24" step="1">
							<p class="description"><?php esc_html_e( '0 shows all published programs.', 'parish-programs' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp-group"><?php esc_html_e( 'Program group', 'parish-programs' ); ?></label></th>
						<td>
							<select id="pp-group" name="pp_settings[homepage_group]">
								<option value="" <?php selected( $settings['homepage_group'], '' ); ?>><?php esc_html_e( 'All groups', 'parish-programs' ); ?></option>
								<?php foreach ( get_terms( array( 'taxonomy' => PP_CPT::TAXONOMY, 'hide_empty' => false ) ) as $pp_term ) : ?>
									<option value="<?php echo esc_attr( $pp_term->slug ); ?>" <?php selected( $settings['homepage_group'], $pp_term->slug ); ?>><?php echo esc_html( $pp_term->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Only show programs in this group on the homepage.', 'parish-programs' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp-heading"><?php esc_html_e( 'Heading', 'parish-programs' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="pp-heading" name="pp_settings[homepage_heading]" value="<?php echo esc_attr( $settings['homepage_heading'] ); ?>" placeholder="<?php esc_attr_e( 'Grow in Faith', 'parish-programs' ); ?>">
							<p class="description"><?php esc_html_e( 'Optional heading shown above the display. Leave blank for none.', 'parish-programs' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp-more-url"><?php esc_html_e( 'Link below the cards', 'parish-programs' ); ?></label></th>
						<td>
							<input type="url" class="regular-text code" id="pp-more-url" name="pp_settings[homepage_more_url]" value="<?php echo esc_attr( $settings['homepage_more_url'] ); ?>" placeholder="https://…">
							<input type="text" class="regular-text" id="pp-more-text" name="pp_settings[homepage_more_text]" value="<?php echo esc_attr( $settings['homepage_more_text'] ); ?>" placeholder="<?php esc_attr_e( 'See all programs', 'parish-programs' ); ?>">
							<p class="description"><?php esc_html_e( 'Optional centered link shown under the display — e.g. to the full programs page. Leave the URL blank for none; blank text reads "See all programs".', 'parish-programs' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Uninstall', 'parish-programs' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Delete data on uninstall', 'parish-programs' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="pp_settings[delete_data_on_uninstall]" value="1" <?php checked( $settings['delete_data_on_uninstall'], '1' ); ?>>
								<?php esc_html_e( 'Also delete all program posts when the plugin is uninstalled', 'parish-programs' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
