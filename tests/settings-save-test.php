<?php
/**
 * Manual test: simulate the settings form save exactly as options.php does —
 * every field arrives as a string (textareas with newlines), then the
 * sanitize callback runs and the option is updated (firing update_option_*).
 * Run: wp eval-file wp-content/plugins/parish-events/tests/settings-save-test.php
 */

$form_post = array(
	'api_base_url'             => 'https://falling-cherry-6eaa.stpacc-account.workers.dev/',
	'schedule'                 => 'twicedaily',
	'suppress_ids'             => "5 | https://stpacc.org/about/mass-times\n4090",
	'suppress_exact'           => "Mass | https://stpacc.org/about/mass-times\nSolemn Mass | https://stpacc.org/about/mass-times\nMass (anticipated) | https://stpacc.org/about/mass-times",
	'suppress_contains'        => 'baptisms',
	'location_subs_global'     => "Church (nave) => Church\nConfessionals => Church",
	'location_subs_event'      => "Divine Mercy Chaplet |  | Church\nDivine Mercy Chaplet | Church (nave) | Church",
	'location_addresses'       => 'Parish Hall | 313 N State St | Westerville | OH | 43082',
	'location_info'            => "Church | | Enter through the main doors on State St.\nParish Hall | https://stpacc.org/campus | Behind the church.",
	'alert_emails'             => "one@example.org\ntwo@example.org",
	'default_image'            => 'https://stpacc.diocesanweb.org/wp-content/uploads/2025/10/PSX_20200823_093726-2.jpg',
	'cancelled_grace_days'     => '14',
	'delete_data_on_uninstall' => '1',
);

// Mirror options.php exactly: the registered sanitize filter runs inside
// update_option, and when the option doesn't exist yet, add_option runs it a
// SECOND time on the already-structured value. register_setting only happens
// on admin_init, so wire the filter manually here.
add_filter( 'sanitize_option_' . PE_Settings::OPTION, array( 'PE_Settings', 'sanitize' ) );
delete_option( PE_Settings::OPTION );

echo "first-save path (update_option -> add_option, double sanitize)...\n";
update_option( PE_Settings::OPTION, $form_post );
echo "first save OK\n";

$clean = PE_Settings::get_all();
echo "stored keys: " . implode( ',', array_keys( $clean ) ) . "\n";
echo 'suppress_exact structure: ' . ( isset( $clean['suppress_exact'][0]['title'] ) ? 'OK (' . $clean['suppress_exact'][0]['title'] . ')' : 'BROKEN' ) . "\n";

echo "second save path (option exists, single sanitize)...\n";
update_option( PE_Settings::OPTION, $form_post );
echo "second save OK\n";

// Changed schedule to exercise the reschedule path.
$form_post['schedule'] = 'hourly';
update_option( PE_Settings::OPTION, $form_post );
echo "reschedule path OK\n";

// Render the settings page template's data accessors.
foreach ( array( 'suppress_ids', 'suppress_exact', 'suppress_contains', 'location_subs_global', 'location_subs_event', 'location_addresses', 'location_info', 'alert_emails' ) as $key ) {
	$text = PE_Settings::to_textarea( $key );
	echo "to_textarea($key) OK: " . str_replace( "\n", ' / ', $text ) . "\n";
}
echo "ALL OK\n";
