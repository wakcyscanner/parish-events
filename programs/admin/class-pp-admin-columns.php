<?php
/**
 * Programs list table: image, schedule, link, and order columns.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_Admin_Columns {

	public static function init() {
		add_filter( 'manage_' . PP_CPT::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . PP_CPT::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render' ), 10, 2 );
		add_filter( 'manage_edit-' . PP_CPT::POST_TYPE . '_sortable_columns', array( __CLASS__, 'sortable' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'group_filter' ) );
	}

	/**
	 * Filter-by-group dropdown above the programs list table.
	 *
	 * @param string $post_type Current list table post type.
	 */
	public static function group_filter( $post_type ) {
		if ( PP_CPT::POST_TYPE !== $post_type ) {
			return;
		}
		wp_dropdown_categories(
			array(
				'taxonomy'        => PP_CPT::TAXONOMY,
				'name'            => PP_CPT::TAXONOMY,
				'value_field'     => 'slug',
				'show_option_all' => __( 'All groups', 'parish-programs' ),
				'hide_empty'      => false,
				'hierarchical'    => true,
				'selected'        => isset( $_GET[ PP_CPT::TAXONOMY ] ) ? sanitize_text_field( wp_unslash( $_GET[ PP_CPT::TAXONOMY ] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			)
		);
	}

	public static function columns( $columns ) {
		$ordered = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$ordered['pp_image'] = __( 'Image', 'parish-programs' );
			}
			$ordered[ $key ] = $label;
			if ( 'title' === $key ) {
				$ordered['pp_schedule'] = __( 'Schedule', 'parish-programs' );
				$ordered['pp_link']     = __( 'Link', 'parish-programs' );
				$ordered['pp_order']    = __( 'Order', 'parish-programs' );
			}
		}
		return $ordered;
	}

	public static function render( $column, $post_id ) {
		switch ( $column ) {
			case 'pp_image':
				echo get_the_post_thumbnail( $post_id, array( 60, 60 ) );
				break;
			case 'pp_schedule':
				echo esc_html( get_post_meta( $post_id, '_pp_date_text', true ) );
				break;
			case 'pp_link':
				$url = get_post_meta( $post_id, '_pp_link_url', true );
				if ( $url ) {
					$text = get_post_meta( $post_id, '_pp_link_text', true );
					echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( '' !== trim( (string) $text ) ? $text : __( 'Learn More', 'parish-programs' ) ) . '</a>';
				} else {
					echo '&mdash;';
				}
				break;
			case 'pp_order':
				$post = get_post( $post_id );
				echo esc_html( $post ? (string) $post->menu_order : '' );
				break;
		}
	}

	public static function sortable( $columns ) {
		$columns['pp_order'] = 'menu_order';
		return $columns;
	}
}
