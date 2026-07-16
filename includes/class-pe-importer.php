<?php
/**
 * Sync engine: fetch, upsert, reconcile.
 *
 * Safety invariants:
 * - Reconcile is unreachable when the fetch errored, the XML was malformed,
 *   or the feed contained zero items — a bad fetch can never unpublish
 *   anything.
 * - Reconcile only touches posts whose event date falls inside the fetched
 *   window, so past events are never mass-removed by falling out of view.
 * - Re-running against an unchanged feed is a no-op (hash short-circuit).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_Importer {

	const LOCK_OPTION   = 'pe_import_lock';
	const LOCK_MAX_AGE  = 900; // 15 minutes.
	const RUN_LOG_LIMIT = 20;

	/**
	 * Run a full import cycle.
	 *
	 * @param string $trigger 'cron' or 'manual'.
	 * @return array Run summary.
	 */
	public static function run( $trigger ) {
		$run = array(
			'time'               => time(),
			'trigger'            => $trigger,
			'created'            => 0,
			'updated'            => 0,
			'skipped_unchanged'  => 0,
			'skipped_suppressed' => 0,
			'removed'            => 0,
			'restored'           => 0,
			'flagged'            => 0,
			'errors'             => array(),
			'duration_ms'        => 0,
		);
		$start_ts = microtime( true );

		// add_option is atomic (INSERT ... fails on duplicate key), unlike
		// transients, so concurrent runs can't both take the lock.
		if ( ! add_option( self::LOCK_OPTION, time(), '', 'no' ) ) {
			$held = (int) get_option( self::LOCK_OPTION );
			if ( time() - $held < self::LOCK_MAX_AGE ) {
				$run['errors'][] = 'Skipped: another import is running.';
				return self::finish( $run, $start_ts, false );
			}
			update_option( self::LOCK_OPTION, time() ); // Steal a stale lock.
		}

		try {
			$window = pe_import_window();
			$body   = PE_Feed_Client::fetch( $window['start'], $window['end'] );
			if ( is_wp_error( $body ) ) {
				$run['errors'][] = 'Fetch failed: ' . $body->get_error_message();
				$run['failed']   = true;
				return self::finish( $run, $start_ts );
			}

			$rows = PE_Feed_Client::parse( $body );
			if ( is_wp_error( $rows ) ) {
				$run['errors'][] = 'Parse failed: ' . $rows->get_error_message();
				$run['failed']   = true;
				return self::finish( $run, $start_ts );
			}

			if ( 0 === count( $rows ) ) {
				$run['errors'][] = 'Feed returned zero events — treated as suspect; nothing changed.';
				$run['failed']   = true;
				return self::finish( $run, $start_ts );
			}

			$settings = PE_Settings::get_all();
			$seen     = array();
			$linked   = array();

			foreach ( $rows as $row ) {
				$valid = PE_Feed_Client::validate_row( $row );
				if ( true !== $valid ) {
					$run['errors'][] = sprintf( 'Skipped row (%s): %s %s', $valid, $row['name'], $row['date'] );
					continue;
				}

				// Defensive clamp: the worker already scopes by date, but a
				// row outside the window must not widen the reconcile scope.
				if ( $row['date'] < $window['start'] || $row['date'] > $window['end'] ) {
					continue;
				}

				$uid = $row['ccb_event_id'] . ':' . $row['date'];
				if ( isset( $seen[ $uid ] ) ) {
					$run['errors'][] = 'Duplicate uid in feed: ' . $uid;
					continue;
				}

				$rule = pe_suppression_rule( $row['ccb_event_id'], $row['name'], $settings );
				if ( null !== $rule ) {
					// Not added to $seen: if a post exists from before the
					// suppression rule, reconcile transitions it to removed.
					// The occurrence still shows on calendar displays as a
					// "linked occurrence" pointing at the rule's URL. Keyed
					// by date+name+time so duplicate series in the source
					// (e.g. two "Mass (anticipated)" ccb_ids in the same
					// slot) collapse to one display row.
					$run['skipped_suppressed']++;
					$linked_key            = $row['date'] . '|' . strtolower( $row['name'] ) . '|' . $row['start_time'];
					$linked[ $linked_key ] = array(
						'date'       => $row['date'],
						'name'       => $row['name'],
						'start_time' => $row['start_time'],
						'end_time'   => $row['end_time'],
						'all_day'    => ( '00:00:00' === $row['start_time'] && '23:59:59' === $row['end_time'] ) ? '1' : '0',
						'location'   => pe_apply_location_substitution( $row['location'], $row['name'], $settings ),
						'group'      => $row['group_name'],
						'url'        => $rule['url'],
					);
					continue;
				}

				$seen[ $uid ] = true;
				self::upsert( $row, $uid, $settings, $run );
			}

			self::reconcile( $window, $seen, $run );

			// Replace the linked-occurrence list wholesale. Like reconcile,
			// this line is only reachable after a successful non-empty fetch,
			// so a bad run can never blank the calendar's Mass rows.
			$linked = array_values( $linked );
			usort(
				$linked,
				static function ( $a, $b ) {
					return strcmp( $a['date'] . $a['start_time'], $b['date'] . $b['start_time'] );
				}
			);
			$linked_changed = update_option( 'pe_linked_occurrences', $linked, false );

			// Invalidate shortcode fragment caches — and, via PE_Cache,
			// full-page caches — but only when the run actually changed
			// something, so routine no-op imports don't purge the site cache.
			if ( $linked_changed || $run['created'] || $run['updated'] || $run['removed'] || $run['restored'] ) {
				update_option( 'pe_cache_ver', (int) get_option( 'pe_cache_ver', 0 ) + 1 );
			}
		} finally {
			delete_option( self::LOCK_OPTION );
		}

		return self::finish( $run, $start_ts );
	}

	/**
	 * Create or update the post for one feed row.
	 *
	 * @param array  $row      Validated feed row.
	 * @param string $uid      "{ccb_event_id}:{date}".
	 * @param array  $settings Full settings.
	 * @param array  $run      Run summary (by reference).
	 */
	private static function upsert( $row, $uid, $settings, &$run ) {
		$public_desc = pe_extract_public_description( $row['description'] );
		$location    = pe_apply_location_substitution( $row['location'], $row['name'], $settings );
		$all_day     = ( '00:00:00' === $row['start_time'] && '23:59:59' === $row['end_time'] ) ? '1' : '0';

		// The raw description (internal notes) is never persisted — only its
		// hash participates in change detection.
		$fields = array(
			'name'          => $row['name'],
			'date'          => $row['date'],
			'start_time'    => $row['start_time'],
			'end_time'      => $row['end_time'],
			'all_day'       => $all_day,
			'event_type'    => $row['event_type'],
			'location_raw'  => $row['location'],
			'location'      => $location,
			'group_name'    => $row['group_name'],
			'group_ccb_id'  => $row['group_ccb_id'],
			'group_type'    => $row['group_type'],
			'grouping_name' => $row['grouping_name'],
			'leader_name'   => $row['leader_name'],
			'leader_ccb_id' => $row['leader_ccb_id'],
			'leader_phone'  => $row['leader_phone'],
			'leader_email'  => $row['leader_email'],
			'description'   => hash( 'sha256', $row['description'] ),
		);
		$import_hash  = hash( 'sha256', wp_json_encode( $fields ) );
		$field_hashes = array();
		foreach ( $fields as $key => $value ) {
			$field_hashes[ $key ] = hash( 'sha256', (string) $value );
		}

		$post_id = self::find_post_by_uid( $uid );

		if ( $post_id ) {
			update_post_meta( $post_id, '_pe_last_seen', time() );

			// The event is present upstream again; a stale review flag from a
			// prior run no longer applies.
			if ( 'missing_upstream' === get_post_meta( $post_id, '_pe_sync_note', true ) ) {
				delete_post_meta( $post_id, '_pe_sync_note' );
			}

			// Overridden posts are entirely admin-owned: no field writes and
			// no status changes (an overridden post is never auto-removed, so
			// pe_removed here means the admin set it deliberately).
			if ( '1' === get_post_meta( $post_id, '_pe_override', true ) ) {
				return;
			}

			if ( PE_CPT::STATUS_REMOVED === get_post_status( $post_id ) ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'publish',
					)
				);
				delete_post_meta( $post_id, '_pe_removed_at' );
				delete_post_meta( $post_id, '_pe_redirect_url' );
				update_post_meta( $post_id, '_pe_sync_note', 'restored' );
				$run['restored']++;
			}

			if ( $import_hash === get_post_meta( $post_id, '_pe_import_hash', true ) ) {
				$run['skipped_unchanged']++;
				return;
			}

			// Slug intentionally untouched: published URLs never churn on
			// upstream title edits.
			$result = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_title'   => $row['name'],
					'post_content' => $public_desc,
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				$run['errors'][] = 'Update failed for ' . $uid . ': ' . $result->get_error_message();
				return;
			}
			if ( 'restored' !== get_post_meta( $post_id, '_pe_sync_note', true ) ) {
				delete_post_meta( $post_id, '_pe_sync_note' );
			}
			$run['updated']++;
		} else {
			// post_date stays "now": a future post_date with status publish
			// would silently become a scheduled post. Event dates live in meta.
			$post_id = wp_insert_post(
				array(
					'post_type'    => PE_CPT::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $row['name'],
					'post_name'    => pe_build_slug( $row['name'], $row['date'] ),
					'post_content' => $public_desc,
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				$run['errors'][] = 'Insert failed for ' . $uid . ': ' . $post_id->get_error_message();
				return;
			}
			update_post_meta( $post_id, '_pe_uid', $uid );
			update_post_meta( $post_id, '_pe_last_seen', time() );
			$run['created']++;
		}

		// Import never touches the featured image; it is admin-owned.
		update_post_meta( $post_id, '_pe_ccb_event_id', $row['ccb_event_id'] );
		update_post_meta( $post_id, '_pe_event_date', $row['date'] );
		update_post_meta( $post_id, '_pe_start_time', $row['start_time'] );
		update_post_meta( $post_id, '_pe_end_time', $row['end_time'] );
		update_post_meta( $post_id, '_pe_all_day', $all_day );
		update_post_meta( $post_id, '_pe_event_type', $row['event_type'] );
		update_post_meta( $post_id, '_pe_location_raw', $row['location'] );
		update_post_meta( $post_id, '_pe_location', $location );
		update_post_meta( $post_id, '_pe_group_name', $row['group_name'] );
		update_post_meta( $post_id, '_pe_group_ccb_id', $row['group_ccb_id'] );
		update_post_meta( $post_id, '_pe_group_type', $row['group_type'] );
		update_post_meta( $post_id, '_pe_grouping_name', $row['grouping_name'] );
		update_post_meta( $post_id, '_pe_leader_name', $row['leader_name'] );
		update_post_meta( $post_id, '_pe_leader_ccb_id', $row['leader_ccb_id'] );
		update_post_meta( $post_id, '_pe_leader_phone', $row['leader_phone'] );
		update_post_meta( $post_id, '_pe_leader_email', $row['leader_email'] );
		update_post_meta( $post_id, '_pe_import_hash', $import_hash );
		update_post_meta( $post_id, '_pe_field_hashes', wp_json_encode( $field_hashes ) );
	}

	/**
	 * Transition published events that vanished from the feed to pe_removed.
	 *
	 * Only posts dated inside the fetched window are candidates; only reached
	 * after a successful, non-empty fetch.
	 *
	 * @param array $window {start, end} Y-m-d.
	 * @param array $seen   uid => true for every non-suppressed feed row.
	 * @param array $run    Run summary (by reference).
	 */
	private static function reconcile( $window, $seen, &$run ) {
		$query = new WP_Query(
			array(
				'post_type'              => PE_CPT::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_pe_event_date',
						'value'   => array( $window['start'], $window['end'] ),
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
				),
			)
		);

		foreach ( $query->posts as $post_id ) {
			$uid = get_post_meta( $post_id, '_pe_uid', true );
			if ( '' === $uid || isset( $seen[ $uid ] ) ) {
				continue;
			}

			if ( '1' === get_post_meta( $post_id, '_pe_override', true ) ) {
				// Overridden posts are admin-owned: flag for review instead
				// of unpublishing.
				update_post_meta( $post_id, '_pe_sync_note', 'missing_upstream' );
				$run['flagged']++;
				continue;
			}

			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => PE_CPT::STATUS_REMOVED,
				)
			);
			update_post_meta( $post_id, '_pe_removed_at', time() );

			// A post delisted because a suppression rule with a link URL now
			// covers its series (e.g. Mass moved to the mass-times page)
			// keeps working for saved/indexed links: its permalink 301s to
			// the rule's URL instead of showing a notice and later a 410.
			$rule = pe_suppression_rule(
				get_post_meta( $post_id, '_pe_ccb_event_id', true ),
				get_the_title( $post_id ),
				PE_Settings::get_all()
			);
			if ( null !== $rule && '' !== $rule['url'] ) {
				update_post_meta( $post_id, '_pe_redirect_url', $rule['url'] );
			} else {
				delete_post_meta( $post_id, '_pe_redirect_url' );
			}

			$run['removed']++;
		}
	}

	/**
	 * Find an event post (any status, including pe_removed) by its uid.
	 *
	 * @param string $uid "{ccb_event_id}:{date}".
	 * @return int Post ID, or 0.
	 */
	private static function find_post_by_uid( $uid ) {
		$query = new WP_Query(
			array(
				'post_type'              => PE_CPT::POST_TYPE,
				'post_status'            => array_merge( array_keys( get_post_stati() ) ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_key'               => '_pe_uid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => $uid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return $query->posts ? (int) $query->posts[0] : 0;
	}

	/**
	 * Finalize the run summary and prepend it to the run log.
	 *
	 * @param array $run      Run summary.
	 * @param float $start_ts microtime(true) at run start.
	 * @param bool  $log      Whether to record the run (lock-skips aren't recorded).
	 * @return array
	 */
	private static function finish( $run, $start_ts, $log = true ) {
		$run['duration_ms'] = (int) round( ( microtime( true ) - $start_ts ) * 1000 );

		if ( $log ) {
			$entries = get_option( 'pe_run_log', array() );
			if ( ! is_array( $entries ) ) {
				$entries = array();
			}
			array_unshift( $entries, $run );
			update_option( 'pe_run_log', array_slice( $entries, 0, self::RUN_LOG_LIMIT ), false );

			self::maybe_alert( $run );
		}

		return $run;
	}

	/**
	 * Email the configured addresses after ALERT_AFTER consecutive run-level
	 * failures, and again once the feed recovers. Row-level warnings
	 * (duplicate uids, skipped rows) never trigger alerts.
	 *
	 * @param array $run Finished run summary.
	 */
	const ALERT_AFTER = 3;

	private static function maybe_alert( $run ) {
		$emails = (array) PE_Settings::get( 'alert_emails' );
		if ( ! $emails ) {
			return;
		}

		$failed = ! empty( $run['failed'] );
		$streak = $failed ? (int) get_option( 'pe_fail_streak', 0 ) + 1 : 0;
		update_option( 'pe_fail_streak', $streak, false );

		$site = get_bloginfo( 'name' );

		if ( $failed && self::ALERT_AFTER === $streak && ! get_option( 'pe_alert_active' ) ) {
			update_option( 'pe_alert_active', 1, false );
			wp_mail(
				$emails,
				sprintf( '[%s] Parish events import is failing', $site ),
				sprintf(
					"The parish events import has failed %d times in a row.\n\nLatest error:\n%s\n\nThe published calendar is unchanged (stale, not broken) until the feed recovers. Check the feed URL in Parish Events → Settings, and the run log there for details:\n%s",
					$streak,
					implode( "\n", $run['errors'] ),
					admin_url( 'edit.php?post_type=' . PE_CPT::POST_TYPE . '&page=pe-settings' )
				)
			);
		}

		if ( ! $failed && get_option( 'pe_alert_active' ) ) {
			delete_option( 'pe_alert_active' );
			wp_mail(
				$emails,
				sprintf( '[%s] Parish events import recovered', $site ),
				sprintf(
					"The parish events import succeeded again.\n\n%s",
					self::summarize_run( $run )
				)
			);
		}
	}

	/**
	 * Human-readable one-line summary of a run.
	 *
	 * @param array $run Run summary.
	 * @return string
	 */
	public static function summarize_run( $run ) {
		$text = sprintf(
			/* translators: run counts */
			__( '%1$d created, %2$d updated, %3$d unchanged, %4$d suppressed, %5$d removed, %6$d restored, %7$d flagged.', 'parish-events' ),
			$run['created'],
			$run['updated'],
			$run['skipped_unchanged'],
			$run['skipped_suppressed'],
			$run['removed'],
			$run['restored'],
			$run['flagged']
		);
		if ( ! empty( $run['errors'] ) ) {
			$text .= ' ' . __( 'Errors:', 'parish-events' ) . ' ' . implode( '; ', $run['errors'] );
		}
		return $text;
	}
}
