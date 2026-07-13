<?php
/**
 * Settings page, Run Now handler, and admin notices.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_pe_run_import', array( __CLASS__, 'handle_run_now' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . PE_CPT::POST_TYPE,
			__( 'Parish Events Settings', 'parish-events' ),
			__( 'Settings', 'parish-events' ),
			'manage_options',
			'pe-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'pe_settings_group',
			PE_Settings::OPTION,
			array( 'sanitize_callback' => array( 'PE_Settings', 'sanitize' ) )
		);
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'parish-events' ) );
		}
		require PE_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	public static function handle_run_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run the import.', 'parish-events' ) );
		}
		check_admin_referer( 'pe_run_import' );

		PE_Importer::run( 'manual' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => PE_CPT::POST_TYPE,
					'page'      => 'pe-settings',
					'pe_ran'    => '1',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	public static function notices() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Run Now result summary.
		if ( isset( $_GET['pe_ran'] ) && isset( $_GET['page'] ) && 'pe-settings' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$log = get_option( 'pe_run_log', array() );
			if ( ! empty( $log[0] ) ) {
				$run = $log[0];
				printf(
					'<div class="notice notice-%s is-dismissible"><p><strong>%s</strong> %s</p></div>',
					empty( $run['errors'] ) ? 'success' : 'error',
					esc_html__( 'Import finished.', 'parish-events' ),
					esc_html( PE_Importer::summarize_run( $run ) )
				);
			}
		}

		// Overridden posts that vanished upstream need a human decision.
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->id, array( 'edit-' . PE_CPT::POST_TYPE, 'dashboard' ), true ) ) {
			$flagged = get_posts(
				array(
					'post_type'      => PE_CPT::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => '_pe_sync_note', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => 'missing_upstream', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'no_found_rows'  => true,
				)
			);
			if ( $flagged ) {
				$url = add_query_arg(
					array(
						'post_type'    => PE_CPT::POST_TYPE,
						'pe_sync_note' => 'missing_upstream',
					),
					admin_url( 'edit.php' )
				);
				printf(
					'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
					esc_html__( 'Some overridden parish events no longer appear in the upstream calendar.', 'parish-events' ),
					esc_url( $url ),
					esc_html__( 'Review them', 'parish-events' )
				);
			}
		}
	}

	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( $screen && ( PE_CPT::POST_TYPE === $screen->post_type || false !== strpos( $hook, 'pe-settings' ) ) ) {
			wp_enqueue_style( 'pe-admin', PE_PLUGIN_URL . 'assets/css/admin.css', array(), PE_VERSION );
		}
	}
}
