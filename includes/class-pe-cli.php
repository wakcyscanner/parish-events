<?php
/**
 * WP-CLI commands: `wp parish-events import` and `wp parish-events status`.
 *
 * A host cron job can run `wp parish-events import` directly instead of
 * relying on visit-driven WP-Cron.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class PE_CLI {

	/**
	 * Run a calendar import now.
	 *
	 * Exits non-zero when the run failed (fetch/parse error or suspect empty
	 * feed), so cron wrappers can detect failure.
	 *
	 * ## EXAMPLES
	 *
	 *     wp parish-events import
	 *
	 * @when after_wp_load
	 */
	public function import() {
		$run = PE_Importer::run( 'cli' );
		WP_CLI::log( PE_Importer::summarize_run( $run ) );
		if ( ! empty( $run['failed'] ) ) {
			WP_CLI::error( 'Import failed.' );
		}
		WP_CLI::success( sprintf( 'Import finished in %d ms.', $run['duration_ms'] ) );
	}

	/**
	 * Show recent import runs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp parish-events status
	 *
	 * @when after_wp_load
	 */
	public function status() {
		$log = get_option( 'pe_run_log', array() );
		if ( empty( $log ) ) {
			WP_CLI::log( 'No imports have run yet.' );
			return;
		}
		$rows = array();
		foreach ( $log as $run ) {
			$rows[] = array(
				'time'       => wp_date( 'Y-m-d H:i:s', $run['time'] ),
				'trigger'    => $run['trigger'],
				'created'    => $run['created'],
				'updated'    => $run['updated'],
				'removed'    => $run['removed'],
				'restored'   => $run['restored'],
				'flagged'    => $run['flagged'],
				'suppressed' => $run['skipped_suppressed'],
				'errors'     => implode( '; ', $run['errors'] ),
			);
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'time', 'trigger', 'created', 'updated', 'removed', 'restored', 'flagged', 'suppressed', 'errors' ) );
	}
}

WP_CLI::add_command( 'parish-events', 'PE_CLI' );
