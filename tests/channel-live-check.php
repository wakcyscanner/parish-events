<?php
// Live check: what each channel sees from the real GitHub releases.
$settings = get_option( 'pe_settings', array() );

foreach ( array( 'stable', 'beta' ) as $channel ) {
	$settings['update_channel'] = $channel;
	update_option( 'pe_settings', $settings );
	delete_transient( PE_Updater::CACHE_KEY );
	$rel = PE_Updater::latest_release();
	echo "$channel channel sees: " . ( $rel ? $rel['version'] : 'nothing' ) . "\n";
}

$settings['update_channel'] = 'stable';
update_option( 'pe_settings', $settings );
delete_transient( PE_Updater::CACHE_KEY );
