<?php
/**
 * Cron scheduling for the import job.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_Cron {

	const HOOK = 'pe_import_cron';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'update_option_' . PE_Settings::OPTION, array( __CLASS__, 'maybe_reschedule' ), 10, 2 );
	}

	public static function add_schedules( $schedules ) {
		$schedules['pe_every_6_hours'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 6 hours', 'parish-events' ),
		);
		return $schedules;
	}

	public static function run() {
		PE_Importer::run( 'cron' );
	}

	public static function schedule() {
		// On activation this runs before init() has wired hooks, so make sure
		// the custom interval exists before scheduling with it.
		if ( ! has_filter( 'cron_schedules', array( __CLASS__, 'add_schedules' ) ) ) {
			add_filter( 'cron_schedules', array( __CLASS__, 'add_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, PE_Settings::get( 'schedule' ), self::HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Reschedule the cron when the frequency setting changes.
	 *
	 * @param array $old Old settings.
	 * @param array $new New settings.
	 */
	public static function maybe_reschedule( $old, $new ) {
		$old_schedule = is_array( $old ) && isset( $old['schedule'] ) ? $old['schedule'] : '';
		$new_schedule = is_array( $new ) && isset( $new['schedule'] ) ? $new['schedule'] : '';
		if ( $old_schedule !== $new_schedule ) {
			self::unschedule();
			self::schedule();
		}
	}
}
