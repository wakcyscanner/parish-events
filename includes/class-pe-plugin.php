<?php
/**
 * Bootstrap: wires all plugin hooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_Plugin {

	/** @var PE_Plugin|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		PE_CPT::init();
		PE_Cron::init();
		PE_Content::init();
		PE_JsonLD::init();
		PE_Meta_Tags::init();
		PE_ICS::init();
		PE_Shortcodes::init();
		PE_Cache::init();
		add_action( 'widgets_init', array( 'PE_Upcoming_Widget', 'register' ) );

		if ( is_admin() ) {
			PE_Admin::init();
			PE_List_Table::init();
			PE_Meta_Box::init();
		}
	}

	public static function activate() {
		PE_CPT::register();
		PE_Cron::schedule();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		PE_Cron::unschedule();
		flush_rewrite_rules();
	}
}
