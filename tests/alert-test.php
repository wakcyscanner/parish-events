<?php
/**
 * Manual test: failure alert streak + recovery.
 * Run: wp eval-file wp-content/plugins/parish-events/tests/alert-test.php
 */

$captured = array();
add_filter(
	'pre_wp_mail',
	function ( $short_circuit, $atts ) use ( &$captured ) {
		$captured[] = $atts['subject'];
		return true; // Don't actually send.
	},
	10,
	2
);

$settings                 = get_option( 'pe_settings', array() );
$settings['alert_emails'] = array( 'alerts@example.test' );
update_option( 'pe_settings', $settings );

delete_option( 'pe_fail_streak' );
delete_option( 'pe_alert_active' );

update_option( 'pe_test_mode', 'garbage' );
for ( $i = 1; $i <= 4; $i++ ) {
	PE_Importer::run( 'manual' );
	echo "after fail $i: streak=" . get_option( 'pe_fail_streak' ) . ' alert_active=' . get_option( 'pe_alert_active', 0 ) . ' mails=' . count( $captured ) . "\n";
}

delete_option( 'pe_test_mode' );
PE_Importer::run( 'manual' );
echo 'after recovery: streak=' . get_option( 'pe_fail_streak' ) . ' alert_active=' . get_option( 'pe_alert_active', 0 ) . ' mails=' . count( $captured ) . "\n";
foreach ( $captured as $subject ) {
	echo "mail: $subject\n";
}
