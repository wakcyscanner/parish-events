<?php
/**
 * Settings storage, defaults, and sanitization.
 *
 * Defaults are ported from CONFIG in calendar.js (the standalone app this
 * plugin replaces).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_Settings {

	const OPTION = 'pe_settings';

	public static function defaults() {
		return array(
			'api_base_url'             => 'https://falling-cherry-6eaa.stpacc-account.workers.dev/',
			'schedule'                 => 'twicedaily',
			'suppress_ids'             => array(),
			'suppress_exact'           => array(
				array(
					'title' => 'Mass',
					'url'   => 'https://stpacc.org/about/mass-times',
				),
				array(
					'title' => 'Solemn Mass',
					'url'   => 'https://stpacc.org/about/mass-times',
				),
				array(
					'title' => 'Mass (anticipated)',
					'url'   => 'https://stpacc.org/about/mass-times',
				),
			),
			'suppress_contains'        => array(
				array(
					'contains' => 'baptisms',
					'url'      => '',
				),
			),
			'location_subs_global'     => array(
				'Church (nave)' => 'Church',
				'Confessionals' => 'Church',
			),
			'location_subs_event'      => array(
				array(
					'event_contains' => 'Divine Mercy Chaplet',
					'substitutions'  => array(
						''              => 'Church',
						'Church (nave)' => 'Church',
					),
				),
			),
			'location_addresses'       => array(),
			'location_info'            => array(),
			'alert_emails'             => array(),
			'update_channel'           => 'stable',
			'default_image'            => 'https://stpacc.diocesanweb.org/wp-content/uploads/2025/10/PSX_20200823_093726-2.jpg',
			'cancelled_grace_days'     => 14,
			'delete_data_on_uninstall' => 0,
		);
	}

	public static function get_all() {
		$settings = get_option( self::OPTION, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		return array_merge( self::defaults(), $settings );
	}

	public static function get( $key ) {
		$all = self::get_all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function allowed_schedules() {
		return array(
			'hourly'            => __( 'Hourly', 'parish-events' ),
			'pe_every_6_hours'  => __( 'Every 6 hours', 'parish-events' ),
			'twicedaily'        => __( 'Twice daily', 'parish-events' ),
			'daily'             => __( 'Daily', 'parish-events' ),
		);
	}

	/**
	 * Sanitize the raw settings form input into the stored structure.
	 *
	 * Textarea-based list fields arrive as strings (one entry per line) and
	 * are stored as arrays; see admin/views/settings-page.php for formats.
	 *
	 * @param array $input Raw form values.
	 * @return array Sanitized settings.
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			return self::get_all();
		}

		// On the very first save the option doesn't exist yet, so
		// update_option falls through to add_option and WordPress runs this
		// callback a SECOND time — on the output of the first pass, where
		// the textarea fields are already structured arrays instead of form
		// strings. That pass must be a no-op or the parsers below fatal.
		if ( isset( $input['suppress_exact'] ) && is_array( $input['suppress_exact'] ) ) {
			return array_merge( self::defaults(), $input );
		}

		$out = self::get_all();

		if ( isset( $input['api_base_url'] ) ) {
			$url = esc_url_raw( trim( $input['api_base_url'] ) );
			if ( 0 === strpos( $url, 'https://' ) ) {
				$out['api_base_url'] = $url;
			} else {
				add_settings_error( self::OPTION, 'pe_api_url', __( 'API URL must use https.', 'parish-events' ) );
			}
		}

		if ( isset( $input['schedule'] ) && array_key_exists( $input['schedule'], self::allowed_schedules() ) ) {
			$out['schedule'] = $input['schedule'];
		}

		// Suppression rule lines: "value" or "value | link URL".
		if ( isset( $input['suppress_ids'] ) ) {
			$rules = array();
			foreach ( self::lines( $input['suppress_ids'] ) as $line ) {
				list( $value, $url ) = self::split_rule_line( $line );
				$id                  = absint( $value );
				if ( $id > 0 ) {
					$rules[] = array(
						'id'  => $id,
						'url' => $url,
					);
				}
			}
			$out['suppress_ids'] = $rules;
		}

		if ( isset( $input['suppress_exact'] ) ) {
			$rules = array();
			foreach ( self::lines( $input['suppress_exact'] ) as $line ) {
				list( $value, $url ) = self::split_rule_line( $line );
				if ( '' !== $value ) {
					$rules[] = array(
						'title' => sanitize_text_field( $value ),
						'url'   => $url,
					);
				}
			}
			$out['suppress_exact'] = $rules;
		}

		if ( isset( $input['suppress_contains'] ) ) {
			$rules = array();
			foreach ( self::lines( $input['suppress_contains'] ) as $line ) {
				list( $value, $url ) = self::split_rule_line( $line );
				if ( '' !== $value ) {
					$rules[] = array(
						'contains' => strtolower( sanitize_text_field( $value ) ),
						'url'      => $url,
					);
				}
			}
			$out['suppress_contains'] = $rules;
		}

		if ( isset( $input['location_subs_global'] ) ) {
			$map = array();
			foreach ( self::lines( $input['location_subs_global'] ) as $line ) {
				$parts = array_map( 'trim', explode( '=>', $line, 2 ) );
				if ( 2 === count( $parts ) && '' !== $parts[0] ) {
					$map[ sanitize_text_field( $parts[0] ) ] = sanitize_text_field( $parts[1] );
				}
			}
			$out['location_subs_global'] = $map;
		}

		if ( isset( $input['location_subs_event'] ) ) {
			// Format: EventContains | From | To  (blank From allowed).
			$rules = array();
			foreach ( self::lines( $input['location_subs_event'] ) as $line ) {
				$parts = array_map( 'trim', explode( '|', $line, 3 ) );
				if ( 3 === count( $parts ) && '' !== $parts[0] ) {
					$contains = sanitize_text_field( $parts[0] );
					$from     = sanitize_text_field( $parts[1] );
					$to       = sanitize_text_field( $parts[2] );
					$found    = false;
					foreach ( $rules as &$rule ) {
						if ( $rule['event_contains'] === $contains ) {
							$rule['substitutions'][ $from ] = $to;
							$found                          = true;
							break;
						}
					}
					unset( $rule );
					if ( ! $found ) {
						$rules[] = array(
							'event_contains' => $contains,
							'substitutions'  => array( $from => $to ),
						);
					}
				}
			}
			$out['location_subs_event'] = $rules;
		}

		if ( isset( $input['location_addresses'] ) ) {
			// Format: Location | Street | City | ST | Zip.
			$map = array();
			foreach ( self::lines( $input['location_addresses'] ) as $line ) {
				$parts = array_map( 'trim', explode( '|', $line, 5 ) );
				if ( 5 === count( $parts ) && '' !== $parts[0] ) {
					$map[ sanitize_text_field( $parts[0] ) ] = array(
						'street' => sanitize_text_field( $parts[1] ),
						'city'   => sanitize_text_field( $parts[2] ),
						'region' => sanitize_text_field( $parts[3] ),
						'zip'    => sanitize_text_field( $parts[4] ),
					);
				}
			}
			$out['location_addresses'] = $map;
		}

		if ( isset( $input['location_info'] ) ) {
			// Format: Location | page URL | description. URL may be blank
			// (Location || description); with two parts, an http(s) value is
			// a URL and anything else is a description.
			$map = array();
			foreach ( self::lines( $input['location_info'] ) as $line ) {
				$parts = array_map( 'trim', explode( '|', $line, 3 ) );
				if ( '' === $parts[0] || 1 === count( $parts ) ) {
					continue;
				}
				if ( 2 === count( $parts ) ) {
					$is_url  = (bool) preg_match( '#^https?://#i', $parts[1] );
					$parts[] = $is_url ? '' : $parts[1];
					if ( ! $is_url ) {
						$parts[1] = '';
					}
				}
				$map[ sanitize_text_field( $parts[0] ) ] = array(
					'url'         => esc_url_raw( $parts[1] ),
					'description' => sanitize_text_field( $parts[2] ),
				);
			}
			$out['location_info'] = $map;
		}

		if ( isset( $input['alert_emails'] ) ) {
			$emails = array();
			foreach ( self::lines( $input['alert_emails'] ) as $line ) {
				$email = sanitize_email( $line );
				if ( is_email( $email ) ) {
					$emails[] = $email;
				}
			}
			$out['alert_emails'] = array_values( array_unique( $emails ) );
		}

		// Unchecked checkbox = absent from the POST = stable.
		$out['update_channel'] = ( isset( $input['update_channel'] ) && 'beta' === $input['update_channel'] ) ? 'beta' : 'stable';

		if ( isset( $input['default_image'] ) ) {
			$out['default_image'] = esc_url_raw( trim( $input['default_image'] ) );
		}

		if ( isset( $input['cancelled_grace_days'] ) ) {
			$out['cancelled_grace_days'] = min( 60, absint( $input['cancelled_grace_days'] ) );
		}

		$out['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;

		return $out;
	}

	/**
	 * Split a textarea value into trimmed, non-empty lines.
	 *
	 * @param string $value Raw textarea value.
	 * @return string[]
	 */
	public static function lines( $value ) {
		if ( is_array( $value ) ) {
			// Only string elements can be lines; anything else (e.g. an
			// already-structured rule array) is dropped rather than fataled on.
			$value = array_filter( $value, 'is_string' );
			return array_values( array_filter( array_map( 'trim', $value ), 'strlen' ) );
		}
		$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
		return array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) );
	}

	/**
	 * Split a "value | link URL" rule line. The URL part is optional.
	 *
	 * @param string $line Raw line.
	 * @return array{0:string,1:string} Value and sanitized URL ('' when absent).
	 */
	private static function split_rule_line( $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		$url   = isset( $parts[1] ) ? esc_url_raw( $parts[1] ) : '';
		return array( $parts[0], $url );
	}

	/**
	 * Render an array-valued setting back into textarea lines.
	 *
	 * @param string $key Settings key.
	 * @return string
	 */
	public static function to_textarea( $key ) {
		$value = self::get( $key );
		switch ( $key ) {
			case 'alert_emails':
				return implode( "\n", (array) $value );

			case 'suppress_ids':
			case 'suppress_exact':
			case 'suppress_contains':
				$value_key = array(
					'suppress_ids'      => 'id',
					'suppress_exact'    => 'title',
					'suppress_contains' => 'contains',
				);
				$lines     = array();
				foreach ( (array) $value as $rule ) {
					$lines[] = $rule[ $value_key[ $key ] ] . ( '' !== $rule['url'] ? ' | ' . $rule['url'] : '' );
				}
				return implode( "\n", $lines );

			case 'location_subs_global':
				$lines = array();
				foreach ( (array) $value as $from => $to ) {
					$lines[] = $from . ' => ' . $to;
				}
				return implode( "\n", $lines );

			case 'location_subs_event':
				$lines = array();
				foreach ( (array) $value as $rule ) {
					foreach ( $rule['substitutions'] as $from => $to ) {
						$lines[] = $rule['event_contains'] . ' | ' . $from . ' | ' . $to;
					}
				}
				return implode( "\n", $lines );

			case 'location_addresses':
				$lines = array();
				foreach ( (array) $value as $loc => $addr ) {
					$lines[] = implode( ' | ', array( $loc, $addr['street'], $addr['city'], $addr['region'], $addr['zip'] ) );
				}
				return implode( "\n", $lines );

			case 'location_info':
				$lines = array();
				foreach ( (array) $value as $loc => $info ) {
					$lines[] = rtrim( $loc . ' | ' . $info['url'] . ' | ' . $info['description'], ' |' );
				}
				return implode( "\n", $lines );
		}
		return '';
	}
}
