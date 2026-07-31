<?php
/**
 * WP-CLI: one-time import from the legacy Come to Me page's programs.json.
 *
 *   wp parish-programs import --from=https://cometome.stpacc.org/data/programs.json
 *   wp parish-programs import --from=/path/to/programs.json --update
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_CLI {

	public static function init() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'parish-programs', __CLASS__ );
		}
	}

	/**
	 * Import programs from a JSON file or URL.
	 *
	 * The JSON must have the legacy shape: { "programs": [ { "title", "date",
	 * "description", "image", "imageAlt", "linkUrl", "linkText" }, ... ] }.
	 * Image URLs are sideloaded into the media library and set as the
	 * featured image. Display order follows array order.
	 *
	 * ## OPTIONS
	 *
	 * --from=<source>
	 * : URL or file path of the JSON.
	 *
	 * [--update]
	 * : Update programs that already exist (matched by title). Default: skip them.
	 *
	 * @param array $args       Positional args (unused).
	 * @param array $assoc_args Named args.
	 */
	public function import( $args, $assoc_args ) {
		$from   = isset( $assoc_args['from'] ) ? (string) $assoc_args['from'] : '';
		$update = ! empty( $assoc_args['update'] );

		if ( '' === $from ) {
			WP_CLI::error( 'The --from=<url-or-path> option is required.' );
		}

		$programs = $this->load_programs( $from );
		WP_CLI::log( sprintf( 'Loaded %d programs from %s', count( $programs ), $from ) );

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$created = 0;
		$updated = 0;
		$skipped = 0;

		foreach ( array_values( $programs ) as $i => $program ) {
			$title = isset( $program['title'] ) ? trim( (string) $program['title'] ) : '';
			if ( '' === $title ) {
				WP_CLI::warning( sprintf( 'Entry %d has no title — skipped.', $i ) );
				$skipped++;
				continue;
			}

			$existing = $this->find_by_title( $title );
			if ( $existing && ! $update ) {
				WP_CLI::log( sprintf( 'Exists, skipped: %s (use --update to overwrite)', $title ) );
				$skipped++;
				continue;
			}

			$postarr = array(
				'post_type'    => PP_CPT::POST_TYPE,
				'post_title'   => $title,
				'post_content' => isset( $program['description'] ) ? trim( (string) $program['description'] ) : '',
				'post_status'  => 'publish',
				// Gaps of 10 leave room to slot programs in between later.
				'menu_order'   => ( $i + 1 ) * 10,
			);

			if ( $existing ) {
				$postarr['ID'] = $existing;
				$post_id       = wp_update_post( $postarr, true );
			} else {
				$post_id = wp_insert_post( $postarr, true );
			}

			if ( is_wp_error( $post_id ) ) {
				WP_CLI::warning( sprintf( '%s: %s', $title, $post_id->get_error_message() ) );
				$skipped++;
				continue;
			}

			update_post_meta( $post_id, '_pp_date_text', isset( $program['date'] ) ? sanitize_text_field( (string) $program['date'] ) : '' );
			update_post_meta( $post_id, '_pp_link_url', isset( $program['linkUrl'] ) ? esc_url_raw( (string) $program['linkUrl'] ) : '' );
			update_post_meta( $post_id, '_pp_link_text', isset( $program['linkText'] ) ? sanitize_text_field( (string) $program['linkText'] ) : '' );

			$this->maybe_sideload_image( $post_id, $program, $title );

			if ( $existing ) {
				WP_CLI::log( 'Updated: ' . $title );
				$updated++;
			} else {
				WP_CLI::log( 'Created: ' . $title );
				$created++;
			}
		}

		WP_CLI::success( sprintf( '%d created, %d updated, %d skipped.', $created, $updated, $skipped ) );
	}

	/**
	 * @param string $from URL or file path.
	 * @return array Program entries.
	 */
	private function load_programs( $from ) {
		if ( preg_match( '#^https?://#i', $from ) ) {
			$response = wp_remote_get( $from, array( 'timeout' => 30 ) );
			if ( is_wp_error( $response ) ) {
				WP_CLI::error( 'Fetch failed: ' . $response->get_error_message() );
			}
			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				WP_CLI::error( 'Fetch failed: HTTP ' . wp_remote_retrieve_response_code( $response ) );
			}
			$body = wp_remote_retrieve_body( $response );
		} else {
			if ( ! file_exists( $from ) ) {
				WP_CLI::error( 'File not found: ' . $from );
			}
			$body = file_get_contents( $from ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['programs'] ) || ! is_array( $data['programs'] ) ) {
			WP_CLI::error( 'The JSON has no "programs" array.' );
		}

		return $data['programs'];
	}

	/**
	 * Existing program post ID matched by exact title, any status.
	 *
	 * @param string $title Program title.
	 * @return int 0 when none.
	 */
	private function find_by_title( $title ) {
		$found = get_posts(
			array(
				'post_type'      => PP_CPT::POST_TYPE,
				'post_status'    => 'any',
				'title'          => $title,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Sideload the entry's image as the featured image unless the post
	 * already has one (re-running the import must not duplicate media).
	 *
	 * @param int    $post_id Program post ID.
	 * @param array  $program Import entry.
	 * @param string $title   Program title, for logging.
	 */
	private function maybe_sideload_image( $post_id, $program, $title ) {
		$image = isset( $program['image'] ) ? trim( (string) $program['image'] ) : '';
		if ( '' === $image || has_post_thumbnail( $post_id ) ) {
			return;
		}
		if ( ! preg_match( '#^https?://#i', $image ) ) {
			WP_CLI::warning( sprintf( '%s: image "%s" is not an absolute URL — skipped.', $title, $image ) );
			return;
		}

		$attachment_id = media_sideload_image( $image, $post_id, $title, 'id' );
		if ( is_wp_error( $attachment_id ) ) {
			WP_CLI::warning( sprintf( '%s: image sideload failed (%s)', $title, $attachment_id->get_error_message() ) );
			return;
		}

		set_post_thumbnail( $post_id, $attachment_id );
		if ( ! empty( $program['imageAlt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $program['imageAlt'] ) );
		}
	}
}
