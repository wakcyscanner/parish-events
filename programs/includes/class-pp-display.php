<?php
/**
 * Front-end registration: assets, [parish_programs] shortcode, and the
 * "Parish Programs" block.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_Display {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_shortcode( 'parish_programs', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Register (not enqueue) assets; PP_Render enqueues them only when
	 * something actually renders.
	 */
	public static function register_assets() {
		wp_register_style(
			'parish-programs',
			PP_PLUGIN_URL . 'assets/css/programs.css',
			array(),
			PP_VERSION
		);
		wp_register_script(
			'pp-carousel',
			PP_PLUGIN_URL . 'assets/js/carousel.js',
			array(),
			PP_VERSION,
			true
		);
		wp_register_script(
			'pp-programs-editor',
			PP_PLUGIN_URL . 'blocks/programs/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render', 'wp-data', 'wp-core-data' ),
			PP_VERSION,
			true
		);
	}

	public static function register_block() {
		register_block_type(
			PP_PLUGIN_DIR . 'blocks/programs',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		return PP_Render::render(
			array(
				'layout'  => isset( $attributes['layout'] ) ? $attributes['layout'] : 'grid',
				'count'   => isset( $attributes['count'] ) ? (int) $attributes['count'] : 0,
				'heading' => isset( $attributes['heading'] ) ? $attributes['heading'] : '',
				'align'   => isset( $attributes['align'] ) ? $attributes['align'] : '',
				'group'   => isset( $attributes['group'] ) ? $attributes['group'] : '',
			)
		);
	}

	/**
	 * [parish_programs layout="grid|carousel" count="0" heading="" group=""]
	 * renders one section, optionally filtered to a program group.
	 *
	 * [parish_programs groups="upcoming-series-events,grow-in-catholic-community"]
	 * renders one section per group, in the listed order, with each group's
	 * name as its heading. groups="all" renders every non-empty group.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'layout'  => 'grid',
				'count'   => 0,
				'heading' => '',
				'align'   => '',
				'group'   => '',
				'groups'  => '',
			),
			$atts,
			'parish_programs'
		);

		if ( '' !== trim( $atts['groups'] ) ) {
			return PP_Render::render_groups( $atts );
		}

		return PP_Render::render( $atts );
	}
}
