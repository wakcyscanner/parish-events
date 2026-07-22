<?php
/**
 * Plugin Name:       Parish Events
 * Plugin URI:        https://github.com/wakcyscanner/stpacc-calendar
 * Description:       Imports parish calendar events from the CCB feed into a custom post type with scheduled sync, manual overrides, structured data, and display shortcodes.
 * Version:           1.1.0-beta.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            St. Paul the Apostle Catholic Church
 * License:           GPL-2.0-or-later
 * Text Domain:       parish-events
 * Update URI:        https://github.com/wakcyscanner/parish-events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PE_VERSION', '1.1.0-beta.2' );
define( 'PE_PLUGIN_FILE', __FILE__ );
define( 'PE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PE_PLUGIN_DIR . 'includes/pe-functions.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-settings.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-cpt.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-feed-client.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-importer.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-cron.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-content.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-jsonld.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-meta-tags.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-ics.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-shortcodes.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-cache.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-updater.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-widget.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-cli.php';
require_once PE_PLUGIN_DIR . 'includes/class-pe-plugin.php';

if ( is_admin() ) {
	require_once PE_PLUGIN_DIR . 'admin/class-pe-admin.php';
	require_once PE_PLUGIN_DIR . 'admin/class-pe-list-table.php';
	require_once PE_PLUGIN_DIR . 'admin/class-pe-meta-box.php';
}

register_activation_hook( __FILE__, array( 'PE_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PE_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'PE_Plugin', 'instance' ) );
