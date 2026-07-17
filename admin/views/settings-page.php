<?php
/**
 * Settings page view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pe_settings = PE_Settings::get_all();
$pe_run_log  = get_option( 'pe_run_log', array() );
?>
<div class="wrap pe-settings">
	<h1><?php esc_html_e( 'Parish Events Settings', 'parish-events' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'pe_settings_group' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="pe_api_base_url"><?php esc_html_e( 'API base URL', 'parish-events' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" id="pe_api_base_url"
						name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[api_base_url]"
						value="<?php echo esc_attr( $pe_settings['api_base_url'] ); ?>" required>
					<p class="description"><?php esc_html_e( 'The Cloudflare worker (or other) endpoint returning the CCB XML feed. Must be https.', 'parish-events' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe_schedule"><?php esc_html_e( 'Import schedule', 'parish-events' ); ?></label></th>
				<td>
					<select id="pe_schedule" name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[schedule]">
						<?php foreach ( PE_Settings::allowed_schedules() as $pe_value => $pe_label ) : ?>
							<option value="<?php echo esc_attr( $pe_value ); ?>" <?php selected( $pe_settings['schedule'], $pe_value ); ?>>
								<?php echo esc_html( $pe_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'WordPress cron only fires on site visits. For reliable imports on a low-traffic site, ask your host to add a real cron job hitting wp-cron.php every 15 minutes (and set DISABLE_WP_CRON).', 'parish-events' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe_suppress_ids"><?php esc_html_e( 'Suppress by CCB event ID', 'parish-events' ); ?></label></th>
				<td>
					<textarea id="pe_suppress_ids" class="small-text code" rows="5" style="width:20em"
						name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[suppress_ids]"><?php echo esc_textarea( PE_Settings::to_textarea( 'suppress_ids' ) ); ?></textarea>
					<p class="description"><?php echo esc_html( 'One rule per line: ID | link URL   (URL optional). These series never create posts, but still appear on the calendar linking to the URL — e.g. the Mass times page. Existing posts for a newly-suppressed series are delisted and their old links redirect to the URL. The ID is shown in each event\'s "Parish Event Sync" box.' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe_suppress_exact"><?php esc_html_e( 'Suppress by exact title', 'parish-events' ); ?></label></th>
				<td>
					<textarea id="pe_suppress_exact" class="large-text code" rows="4"
						name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[suppress_exact]"><?php echo esc_textarea( PE_Settings::to_textarea( 'suppress_exact' ) ); ?></textarea>
					<p class="description"><?php echo esc_html( 'One rule per line: Title | link URL   (URL optional; exact case-insensitive title match). Catches recreated events that get a new CCB ID. Occurrences still show on the calendar linking to the URL.' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe_suppress_contains"><?php esc_html_e( 'Suppress when title contains', 'parish-events' ); ?></label></th>
				<td>
					<textarea id="pe_suppress_contains" class="large-text code" rows="3"
						name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[suppress_contains]"><?php echo esc_textarea( PE_Settings::to_textarea( 'suppress_contains' ) ); ?></textarea>
					<p class="description"><?php echo esc_html( 'One rule per line: keyword | link URL   (URL optional; keyword matched case-insensitively anywhere in the event name). Without a URL the occurrence shows on the calendar as unlinked text.' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe_location_subs_global"><?php esc_html_e( 'Location substitutions (global)', 'parish-events' ); ?></label></th>
				<td>
					<textarea id="pe_location_subs_global" class="large-text code" rows="3"
						name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[location_subs_global]"><?php echo esc_textarea( PE_Settings::to_textarea( 'location_subs_global' ) ); ?></textarea>
					<p class="description"><?php echo esc_html( 'One rule per line: From => To    e.g.  Church (nave) => Church' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe_location_subs_event"><?php esc_html_e( 'Location substitutions (per event)', 'parish-events' ); ?></label></th>
				<td>
					<textarea id="pe_location_subs_event" class="large-text code" rows="3"
						name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[location_subs_event]"><?php echo esc_textarea( PE_Settings::to_textarea( 'location_subs_event' ) ); ?></textarea>
					<p class="description"><?php echo esc_html( 'One rule per line: EventNameContains | From | To    (leave From blank to substitute an empty location)' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe_location_addresses"><?php esc_html_e( 'Location street addresses', 'parish-events' ); ?></label></th>
				<td>
					<textarea id="pe_location_addresses" class="large-text code" rows="3"
						name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[location_addresses]"><?php echo esc_textarea( PE_Settings::to_textarea( 'location_addresses' ) ); ?></textarea>
					<p class="description"><?php echo esc_html( 'Used in search engine structured data for off-site locations. One per line: Location | Street | City | ST | Zip. Unlisted locations use the parish address.' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe_location_info"><?php esc_html_e( 'Location directory', 'parish-events' ); ?></label></th>
				<td>
					<textarea id="pe_location_info" class="large-text code" rows="4"
						name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[location_info]"><?php echo esc_textarea( PE_Settings::to_textarea( 'location_info' ) ); ?></textarea>
					<p class="description"><?php echo esc_html( 'One per line: Location | page URL | description. With a description, visitors see an expandable "where is this" note (the URL becomes a "More about this location" link inside it). With only a URL, the location name links straight to that page. Examples:  St. Peter Room | | Lower level, first door on the right past the elevator.   Parish Hall | https://stpacc.org/campus | Behind the church, enter from the north lot.' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe_default_image"><?php esc_html_e( 'Default event image URL', 'parish-events' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" id="pe_default_image"
						name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[default_image]"
						value="<?php echo esc_attr( $pe_settings['default_image'] ); ?>">
					<p class="description"><?php esc_html_e( 'Used in structured data and featured cards when an event has no featured image.', 'parish-events' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe_alert_emails"><?php esc_html_e( 'Import failure alerts', 'parish-events' ); ?></label></th>
				<td>
					<textarea id="pe_alert_emails" class="regular-text code" rows="3"
						name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[alert_emails]"><?php echo esc_textarea( PE_Settings::to_textarea( 'alert_emails' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One email address per line. After 3 consecutive failed imports these addresses get an alert (and a follow-up when the feed recovers). Leave empty to disable.', 'parish-events' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Update channel', 'parish-events' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[update_channel]" value="beta" <?php checked( 'beta', $pe_settings['update_channel'] ); ?>>
						<?php esc_html_e( 'Receive beta (pre-release) updates', 'parish-events' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'For staging sites: offers pre-release versions from the beta channel as plugin updates. Leave unchecked on production — it then only ever sees stable releases.', 'parish-events' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe_cancelled_grace_days"><?php esc_html_e( 'Cancelled-page grace period (days)', 'parish-events' ); ?></label></th>
				<td>
					<input type="number" min="0" max="60" class="small-text" id="pe_cancelled_grace_days"
						name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[cancelled_grace_days]"
						value="<?php echo esc_attr( $pe_settings['cancelled_grace_days'] ); ?>">
					<p class="description"><?php esc_html_e( 'How long a removed event\'s page stays reachable with a "no longer listed" notice (a soft landing for saved and indexed links) before returning 410 Gone.', 'parish-events' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Uninstall', 'parish-events' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( PE_Settings::OPTION ); ?>[delete_data_on_uninstall]" value="1" <?php checked( $pe_settings['delete_data_on_uninstall'], 1 ); ?>>
						<?php esc_html_e( 'Delete all parish event posts and settings when the plugin is uninstalled', 'parish-events' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Import', 'parish-events' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="pe_run_import">
		<?php wp_nonce_field( 'pe_run_import' ); ?>
		<?php submit_button( __( 'Run import now', 'parish-events' ), 'secondary', 'submit', false ); ?>
	</form>

	<h2><?php esc_html_e( 'Recent import runs', 'parish-events' ); ?></h2>
	<?php if ( empty( $pe_run_log ) ) : ?>
		<p><?php esc_html_e( 'No imports have run yet.', 'parish-events' ); ?></p>
	<?php else : ?>
		<table class="widefat striped pe-run-log">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'parish-events' ); ?></th>
					<th><?php esc_html_e( 'Trigger', 'parish-events' ); ?></th>
					<th><?php esc_html_e( 'Created', 'parish-events' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'parish-events' ); ?></th>
					<th><?php esc_html_e( 'Unchanged', 'parish-events' ); ?></th>
					<th><?php esc_html_e( 'Suppressed', 'parish-events' ); ?></th>
					<th><?php esc_html_e( 'Removed', 'parish-events' ); ?></th>
					<th><?php esc_html_e( 'Restored', 'parish-events' ); ?></th>
					<th><?php esc_html_e( 'Flagged', 'parish-events' ); ?></th>
					<th><?php esc_html_e( 'Duration', 'parish-events' ); ?></th>
					<th><?php esc_html_e( 'Errors', 'parish-events' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pe_run_log as $pe_run ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $pe_run['time'] ) ); ?></td>
						<td><?php echo esc_html( $pe_run['trigger'] ); ?></td>
						<td><?php echo esc_html( $pe_run['created'] ); ?></td>
						<td><?php echo esc_html( $pe_run['updated'] ); ?></td>
						<td><?php echo esc_html( $pe_run['skipped_unchanged'] ); ?></td>
						<td><?php echo esc_html( $pe_run['skipped_suppressed'] ); ?></td>
						<td><?php echo esc_html( $pe_run['removed'] ); ?></td>
						<td><?php echo esc_html( $pe_run['restored'] ); ?></td>
						<td><?php echo esc_html( $pe_run['flagged'] ); ?></td>
						<td><?php echo esc_html( $pe_run['duration_ms'] . ' ms' ); ?></td>
						<td>
							<?php if ( ! empty( $pe_run['errors'] ) ) : ?>
								<span class="pe-error"><?php echo esc_html( implode( '; ', $pe_run['errors'] ) ); ?></span>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
