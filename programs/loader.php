<?php
/**
 * Parish Programs feature loader.
 *
 * Programs are the hand-authored companion to the synced calendar: a program
 * is a set of events packaged together for promotion ("Financial Peace
 * University", "That Man is You!"), each card linking out to a ministry or
 * registration page.
 *
 * They live in their own post type rather than in parish_event because the two
 * have opposite ownership. parish_event is feed-owned — the importer reconciles
 * it against CCB every run and marks rows pe_removed when they vanish upstream.
 * parish_program is editor-owned, has no upstream, and no single pages of its
 * own. Hand-authored rows in the synced post type would be reconciled away.
 *
 * The PP_ classes keep their prefix and file layout from the standalone
 * parish-programs plugin so this stays a move rather than a rewrite. The three
 * PP_ constants below are mapped onto the host plugin's, which is why none of
 * the moved files needed editing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Programs ship as part of this plugin, so they share its version (asset
// ?ver= strings and the cache upgrade check) and live under programs/.
define( 'PP_VERSION', PE_VERSION );
define( 'PP_PLUGIN_DIR', PE_PLUGIN_DIR . 'programs/' );
define( 'PP_PLUGIN_URL', PE_PLUGIN_URL . 'programs/' );

require_once PP_PLUGIN_DIR . 'includes/class-pp-cpt.php';
require_once PP_PLUGIN_DIR . 'includes/class-pp-render.php';
require_once PP_PLUGIN_DIR . 'includes/class-pp-display.php';
require_once PP_PLUGIN_DIR . 'includes/class-pp-homepage.php';
require_once PP_PLUGIN_DIR . 'includes/class-pp-page-background.php';
require_once PP_PLUGIN_DIR . 'includes/class-pp-settings.php';
require_once PP_PLUGIN_DIR . 'includes/class-pp-cache.php';
require_once PP_PLUGIN_DIR . 'includes/class-pp-cli.php';

if ( is_admin() ) {
	require_once PP_PLUGIN_DIR . 'admin/class-pp-meta-box.php';
	require_once PP_PLUGIN_DIR . 'admin/class-pp-admin-columns.php';
}

/**
 * Get program settings merged with defaults.
 *
 * Separate from pe_settings: different screen, different feature, and keeping
 * them apart means a programs change can't corrupt calendar configuration.
 *
 * @return array
 */
function pp_get_settings() {
	$defaults = array(
		'homepage_enabled'         => '0',
		'homepage_selector'        => '',
		'homepage_position'        => 'after',
		'homepage_layout'          => 'carousel',
		'homepage_count'           => 6,
		'homepage_heading'         => '',
		'homepage_group'           => '',
		'homepage_more_url'        => '',
		'homepage_more_text'       => '',
		'delete_data_on_uninstall' => '0',
	);

	$settings = get_option( 'pp_settings', array() );

	return wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );
}

/**
 * Wire the programs feature. Called from PE_Plugin's constructor so both
 * features boot at the same point in the request.
 */
function pp_bootstrap() {
	PP_CPT::init();
	PP_Display::init();
	PP_Homepage::init();
	PP_Page_Background::init();
	PP_Settings::init();
	PP_Cache::init();
	PP_CLI::init();

	if ( is_admin() ) {
		PP_Meta_Box::init();
		PP_Admin_Columns::init();
	}
}
