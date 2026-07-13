<?php
/**
 * Display shortcodes: [parish_events_calendar] and [parish_events_featured].
 *
 * All rendering is server-side HTML — crawlable, works without JS, no
 * FullCalendar/CDN dependency. Fragments are transient-cached and keyed on
 * pe_cache_ver, which every import bumps.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_Shortcodes {

	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	public static function init() {
		add_shortcode( 'parish_events_calendar', array( __CLASS__, 'calendar' ) );
		add_shortcode( 'parish_events_featured', array( __CLASS__, 'featured' ) );
		add_shortcode( 'parish_events_upcoming', array( __CLASS__, 'upcoming' ) );
		add_shortcode( 'parish_events_subscribe', array( __CLASS__, 'subscribe' ) );
		add_action( 'save_post_' . PE_CPT::POST_TYPE, array( __CLASS__, 'bump_cache' ) );
	}

	public static function bump_cache() {
		update_option( 'pe_cache_ver', (int) get_option( 'pe_cache_ver', 0 ) + 1 );
	}

	private static function enqueue() {
		wp_enqueue_style( 'parish-events', PE_PLUGIN_URL . 'assets/css/calendar.css', array(), PE_VERSION );
	}

	/**
	 * Query published events in a date range, ordered by date then start time.
	 *
	 * @param string $from  Y-m-d inclusive.
	 * @param string $to    Y-m-d inclusive.
	 * @param string $group Optional exact group-name filter.
	 * @return WP_Post[]
	 */
	private static function query_events( $from, $to, $group = '' ) {
		$meta_query = array(
			'event_date' => array(
				'key'     => '_pe_event_date',
				'value'   => array( $from, $to ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
			'start_time' => array(
				'key'  => '_pe_start_time',
				'type' => 'CHAR',
			),
		);
		if ( '' !== $group ) {
			$meta_query['group'] = array(
				'key'   => '_pe_group_name',
				'value' => $group,
			);
		}

		$query = new WP_Query(
			array(
				'post_type'              => PE_CPT::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'orderby'                => array(
					'event_date' => 'ASC',
					'start_time' => 'ASC',
				),
			)
		);
		return $query->posts;
	}

	/**
	 * Unified display items in a date range: event posts plus linked
	 * occurrences (suppressed series like Mass, shown without a post of
	 * their own). Sorted by date, all-day first, then start time.
	 *
	 * @param string $from  Y-m-d inclusive.
	 * @param string $to    Y-m-d inclusive.
	 * @param string $group Optional exact group-name filter.
	 * @return array[] Items: {date, name, start_time, all_day, location, group, url}.
	 */
	private static function event_items( $from, $to, $group = '' ) {
		$items = array();

		foreach ( self::query_events( $from, $to, $group ) as $post ) {
			$items[] = array(
				'date'       => get_post_meta( $post->ID, '_pe_event_date', true ),
				'name'       => get_the_title( $post ),
				'start_time' => get_post_meta( $post->ID, '_pe_start_time', true ),
				'all_day'    => get_post_meta( $post->ID, '_pe_all_day', true ),
				'location'   => get_post_meta( $post->ID, '_pe_location', true ),
				'group'      => get_post_meta( $post->ID, '_pe_group_name', true ),
				'url'        => get_permalink( $post ),
				'linked'     => false,
			);
		}

		foreach ( (array) get_option( 'pe_linked_occurrences', array() ) as $occ ) {
			if ( $occ['date'] < $from || $occ['date'] > $to ) {
				continue;
			}
			if ( '' !== $group && $occ['group'] !== $group ) {
				continue;
			}
			$items[] = array(
				'date'       => $occ['date'],
				'name'       => $occ['name'],
				'start_time' => $occ['start_time'],
				'all_day'    => $occ['all_day'],
				'location'   => $occ['location'],
				'group'      => $occ['group'],
				'url'        => $occ['url'],
				'linked'     => true,
			);
		}

		usort(
			$items,
			static function ( $a, $b ) {
				if ( $a['date'] !== $b['date'] ) {
					return strcmp( $a['date'], $b['date'] );
				}
				if ( $a['all_day'] !== $b['all_day'] ) {
					return '1' === $a['all_day'] ? -1 : 1;
				}
				return strcmp( $a['start_time'], $b['start_time'] );
			}
		);

		return $items;
	}

	/**
	 * Distinct group names among upcoming events (for the filter), including
	 * linked occurrences.
	 *
	 * @return string[]
	 */
	private static function group_names() {
		$window = pe_import_window();
		$groups = array();
		foreach ( self::event_items( pe_today(), $window['end'] ) as $item ) {
			if ( '' !== $item['group'] ) {
				$groups[ $item['group'] ] = true;
			}
		}
		$groups = array_keys( $groups );
		sort( $groups, SORT_NATURAL | SORT_FLAG_CASE );
		return $groups;
	}

	/**
	 * An item's title as a link (post permalink or a linked-occurrence URL)
	 * or plain text when the occurrence has no destination.
	 *
	 * @param array $item Display item.
	 * @return string HTML.
	 */
	private static function item_title_html( $item ) {
		if ( '' !== $item['url'] ) {
			return '<a class="pe-event-title" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
		}
		return '<span class="pe-event-title">' . esc_html( $item['name'] ) . '</span>';
	}

	/**
	 * [parish_events_calendar view="list|month" months="2" group="" show_filter="1" show_toggle="1"]
	 */
	public static function calendar( $atts ) {
		$atts = shortcode_atts(
			array(
				'view'        => 'list',
				'months'      => '2',
				'group'       => '',
				'show_filter' => '1',
				'show_toggle' => '1',
			),
			$atts,
			'parish_events_calendar'
		);

		// GET params override shortcode defaults (plain-link navigation).
		$view  = isset( $_GET['pe_view'] ) ? sanitize_key( $_GET['pe_view'] ) : $atts['view']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view  = in_array( $view, array( 'list', 'month' ), true ) ? $view : 'list';
		$group = isset( $_GET['pe_group'] ) ? sanitize_text_field( wp_unslash( $_GET['pe_group'] ) ) : $atts['group']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$month = isset( $_GET['pe_month'] ) ? sanitize_text_field( wp_unslash( $_GET['pe_month'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			$month = substr( pe_today(), 0, 7 );
		}

		// get_the_ID() in the key: nav links embed the current page's URL, so
		// two pages using this shortcode must not share a fragment.
		$cache_key = 'pe_frag_' . md5(
			wp_json_encode( array( 'cal', get_the_ID(), $atts, $view, $group, $month, pe_today(), get_option( 'pe_cache_ver', 0 ) ) )
		);
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			self::enqueue();
			return $cached;
		}

		$window = pe_import_window();
		$months = min( 3, max( 1, (int) $atts['months'] ) );

		// Clamp the requested month to [current month, window end month].
		$current_month = substr( pe_today(), 0, 7 );
		$end_month     = substr( $window['end'], 0, 7 );
		if ( $month < $current_month ) {
			$month = $current_month;
		}
		if ( $month > $end_month ) {
			$month = $end_month;
		}

		$html = '<div class="pe-calendar">';

		if ( '1' === $atts['show_toggle'] || '1' === $atts['show_filter'] ) {
			$html .= self::render_controls( $view, $group, $atts );
		}

		if ( 'month' === $view ) {
			$html .= self::render_month_grid( $month, $group, $current_month, $end_month );
		} else {
			$to = min(
				$window['end'],
				( new DateTimeImmutable( pe_today(), pe_timezone() ) )->modify( '+' . $months . ' months' )->format( 'Y-m-d' )
			);
			$html .= self::render_list( pe_today(), $to, $group );
		}

		$html .= '</div>';

		set_transient( $cache_key, $html, self::CACHE_TTL );
		self::enqueue();
		return $html;
	}

	/**
	 * [parish_events_subscribe label="..."] — webcal subscribe button.
	 */
	public static function subscribe( $atts ) {
		$atts = shortcode_atts(
			array( 'label' => __( 'Subscribe to calendar', 'parish-events' ) ),
			$atts,
			'parish_events_subscribe'
		);
		self::enqueue();
		return '<a class="pe-subscribe" href="' . esc_url( PE_ICS::subscribe_url() ) . '" title="' . esc_attr__( 'Opens in your calendar app and stays up to date automatically', 'parish-events' ) . '">' . esc_html( $atts['label'] ) . '</a>';
	}

	/**
	 * [parish_events_upcoming count="5" show_location="0"] — compact list of
	 * the next event posts (linked Mass occurrences are not included).
	 */
	public static function upcoming( $atts ) {
		$atts = shortcode_atts(
			array(
				'count'         => '5',
				'show_location' => '0',
			),
			$atts,
			'parish_events_upcoming'
		);

		$cache_key = 'pe_frag_' . md5(
			wp_json_encode( array( 'upcoming', $atts, pe_today(), get_option( 'pe_cache_ver', 0 ) ) )
		);
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			self::enqueue();
			return $cached;
		}

		$window = pe_import_window();
		$count  = min( 12, max( 1, (int) $atts['count'] ) );
		$posts  = array_slice( self::query_events( pe_today(), $window['end'] ), 0, $count );

		$html = '';
		if ( $posts ) {
			$html = '<ul class="pe-upcoming">';
			foreach ( $posts as $post ) {
				$date    = get_post_meta( $post->ID, '_pe_event_date', true );
				$all_day = '1' === get_post_meta( $post->ID, '_pe_all_day', true );
				$when    = wp_date( 'D, M j', strtotime( $date . 'T12:00:00' ), pe_timezone() );
				if ( ! $all_day ) {
					$when .= ' · ' . pe_format_time( get_post_meta( $post->ID, '_pe_start_time', true ) );
				}
				$html .= '<li><span class="pe-upcoming-when">' . esc_html( $when ) . '</span> ';
				$html .= '<a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>';
				if ( '1' === $atts['show_location'] ) {
					$location = get_post_meta( $post->ID, '_pe_location', true );
					if ( $location ) {
						$html .= ' <span class="pe-upcoming-loc">' . esc_html( $location ) . '</span>';
					}
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
		}

		set_transient( $cache_key, $html, self::CACHE_TTL );
		self::enqueue();
		return $html;
	}

	private static function render_controls( $view, $group, $atts ) {
		$base = remove_query_arg( array( 'pe_view', 'pe_group', 'pe_month' ) );
		$html = '<div class="pe-controls">';

		if ( '1' === $atts['show_toggle'] ) {
			$html .= '<nav class="pe-view-toggle">';
			foreach ( array(
				'list'  => __( 'List', 'parish-events' ),
				'month' => __( 'Month', 'parish-events' ),
			) as $key => $label ) {
				$url   = add_query_arg( array_filter( array( 'pe_view' => $key, 'pe_group' => $group ) ), $base );
				$class = $key === $view ? 'pe-toggle-active' : '';
				$html .= sprintf( '<a class="%s" href="%s">%s</a>', esc_attr( $class ), esc_url( $url ), esc_html( $label ) );
			}
			$html .= '</nav>';
		}

		$html .= '<a class="pe-subscribe" href="' . esc_url( PE_ICS::subscribe_url() ) . '" title="' . esc_attr__( 'Opens in your calendar app and stays up to date automatically', 'parish-events' ) . '">&#128197; ' . esc_html__( 'Subscribe', 'parish-events' ) . '</a>';

		if ( '1' === $atts['show_filter'] ) {
			$groups = self::group_names();
			if ( $groups ) {
				$html .= '<form class="pe-group-filter" method="get" action="">';
				$html .= '<input type="hidden" name="pe_view" value="' . esc_attr( $view ) . '">';
				$html .= '<label>' . esc_html__( 'Group:', 'parish-events' ) . ' <select name="pe_group" onchange="this.form.submit()">';
				$html .= '<option value="">' . esc_html__( 'All groups', 'parish-events' ) . '</option>';
				foreach ( $groups as $name ) {
					$html .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $name ), selected( $group, $name, false ), esc_html( $name ) );
				}
				$html .= '</select></label>';
				$html .= '<noscript><button type="submit">' . esc_html__( 'Filter', 'parish-events' ) . '</button></noscript>';
				$html .= '</form>';
			}
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * List view: date headings with time – title – location rows
	 * (mirrors the old bulletin layout).
	 */
	private static function render_list( $from, $to, $group ) {
		$items = self::event_items( $from, $to, $group );
		if ( ! $items ) {
			return '<p class="pe-empty">' . esc_html__( 'No upcoming events.', 'parish-events' ) . '</p>';
		}

		$by_date = array();
		foreach ( $items as $item ) {
			$by_date[ $item['date'] ][] = $item;
		}

		$html = '';
		foreach ( $by_date as $date => $day_items ) {
			$html .= '<h3 class="pe-date-heading">' . esc_html( wp_date( 'l, F j', strtotime( $date . 'T12:00:00' ), pe_timezone() ) ) . '</h3>';
			$html .= '<ul class="pe-day-list">';

			foreach ( $day_items as $item ) {
				$time = '1' === $item['all_day'] ? __( 'All day', 'parish-events' ) : pe_format_time( $item['start_time'] );

				$html .= '<li class="pe-event-row">';
				$html .= '<span class="pe-event-time">' . esc_html( $time ) . '</span> ';
				$html .= self::item_title_html( $item );
				if ( $item['location'] ) {
					$html .= ' <span class="pe-event-location">' . pe_location_html( $item['location'] ) . '</span>';
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
		}

		return $html;
	}

	/**
	 * Month view: server-rendered grid with prev/next links clamped to the
	 * data window.
	 */
	private static function render_month_grid( $month, $group, $min_month, $max_month ) {
		$tz    = pe_timezone();
		$first = new DateTimeImmutable( $month . '-01', $tz );
		$last  = $first->modify( 'last day of this month' );

		$items   = self::event_items( $first->format( 'Y-m-d' ), $last->format( 'Y-m-d' ), $group );
		$by_date = array();
		foreach ( $items as $item ) {
			// Month view only: same-named linked occurrences (the several
			// daily Masses) collapse to one pill per day — they all point at
			// the same destination page. The list view shows each time.
			if ( $item['linked'] ) {
				$dedup_key = strtolower( $item['name'] );
				if ( isset( $by_date[ $item['date'] ]['linked:' . $dedup_key ] ) ) {
					continue;
				}
				$by_date[ $item['date'] ][ 'linked:' . $dedup_key ] = $item;
			} else {
				$by_date[ $item['date'] ][] = $item;
			}
		}

		$base = remove_query_arg( array( 'pe_month' ) );
		$html = '<div class="pe-month-nav">';

		$prev = $first->modify( '-1 month' )->format( 'Y-m' );
		$next = $first->modify( '+1 month' )->format( 'Y-m' );
		if ( $prev >= $min_month ) {
			$html .= '<a class="pe-month-prev" href="' . esc_url( add_query_arg( 'pe_month', $prev, $base ) ) . '">&larr; ' . esc_html__( 'Previous', 'parish-events' ) . '</a>';
		} else {
			$html .= '<span class="pe-month-prev pe-disabled">&larr; ' . esc_html__( 'Previous', 'parish-events' ) . '</span>';
		}
		$html .= '<span class="pe-month-title">' . esc_html( wp_date( 'F Y', $first->getTimestamp(), $tz ) ) . '</span>';
		if ( $next <= $max_month ) {
			$html .= '<a class="pe-month-next" href="' . esc_url( add_query_arg( 'pe_month', $next, $base ) ) . '">' . esc_html__( 'Next', 'parish-events' ) . ' &rarr;</a>';
		} else {
			$html .= '<span class="pe-month-next pe-disabled">' . esc_html__( 'Next', 'parish-events' ) . ' &rarr;</span>';
		}
		$html .= '</div>';

		$html .= '<table class="pe-month-grid"><thead><tr>';
		foreach ( array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ) as $dow ) {
			$html .= '<th>' . esc_html( $dow ) . '</th>';
		}
		$html .= '</tr></thead><tbody><tr>';

		$lead = (int) $first->format( 'w' );
		for ( $i = 0; $i < $lead; $i++ ) {
			$html .= '<td class="pe-empty-cell"></td>';
		}

		$today = pe_today();
		$cell  = $lead;
		for ( $day = $first; $day <= $last; $day = $day->modify( '+1 day' ) ) {
			$date  = $day->format( 'Y-m-d' );
			$class = 'pe-day-cell';
			if ( $date === $today ) {
				$class .= ' pe-today';
			}
			if ( $date < $today ) {
				$class .= ' pe-past';
			}
			$html .= '<td class="' . esc_attr( $class ) . '"><span class="pe-day-number">' . esc_html( $day->format( 'j' ) ) . '</span>';
			if ( ! empty( $by_date[ $date ] ) ) {
				$html .= '<ul class="pe-cell-events">';
				foreach ( $by_date[ $date ] as $item ) {
					// title attr carries the full name; CSS clamps the
					// visible text to two lines.
					if ( '' !== $item['url'] ) {
						$html .= '<li><a href="' . esc_url( $item['url'] ) . '" title="' . esc_attr( $item['name'] ) . '">' . esc_html( $item['name'] ) . '</a></li>';
					} else {
						$html .= '<li><span title="' . esc_attr( $item['name'] ) . '">' . esc_html( $item['name'] ) . '</span></li>';
					}
				}
				$html .= '</ul>';
			}
			$html .= '</td>';

			$cell++;
			if ( 0 === $cell % 7 && $day < $last ) {
				$html .= '</tr><tr>';
			}
		}

		while ( 0 !== $cell % 7 ) {
			$html .= '<td class="pe-empty-cell"></td>';
			$cell++;
		}
		$html .= '</tr></tbody></table>';

		return $html;
	}

	/**
	 * [parish_events_featured count="3" order="date|title|rand" columns="3" show_excerpt="1"]
	 */
	public static function featured( $atts ) {
		$atts = shortcode_atts(
			array(
				'count'        => '3',
				'order'        => 'date',
				'columns'      => '3',
				'show_excerpt' => '1',
			),
			$atts,
			'parish_events_featured'
		);

		$cache_key = 'pe_frag_' . md5(
			wp_json_encode( array( 'featured', $atts, pe_today(), get_option( 'pe_cache_ver', 0 ) ) )
		);
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			self::enqueue();
			return $cached;
		}

		$args = array(
			'post_type'              => PE_CPT::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => min( 12, max( 1, (int) $atts['count'] ) ),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'featured'   => array(
					'key'   => '_pe_featured',
					'value' => '1',
				),
				// Past events drop out of the cards automatically.
				'event_date' => array(
					'key'     => '_pe_event_date',
					'value'   => pe_today(),
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		);

		switch ( $atts['order'] ) {
			case 'title':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			case 'rand':
				$args['orderby'] = 'rand';
				break;
			default:
				$args['orderby'] = array( 'event_date' => 'ASC' );
		}

		$query = new WP_Query( $args );
		if ( ! $query->posts ) {
			return '';
		}

		$columns = min( 4, max( 1, (int) $atts['columns'] ) );
		$default = PE_Settings::get( 'default_image' );

		$html = '<div class="pe-featured-cards pe-columns-' . esc_attr( $columns ) . '">';
		foreach ( $query->posts as $post ) {
			$permalink = get_permalink( $post );
			$image     = get_the_post_thumbnail_url( $post, 'medium_large' );
			if ( ! $image ) {
				$image = $default;
			}
			$date    = get_post_meta( $post->ID, '_pe_event_date', true );
			$all_day = '1' === get_post_meta( $post->ID, '_pe_all_day', true );
			$when    = wp_date( 'D, M j', strtotime( $date . 'T12:00:00' ), pe_timezone() );
			if ( ! $all_day ) {
				$when .= ' · ' . pe_format_time( get_post_meta( $post->ID, '_pe_start_time', true ) );
			}
			$location = get_post_meta( $post->ID, '_pe_location', true );

			$html .= '<article class="pe-card">';
			if ( $image ) {
				$html .= '<a class="pe-card-image" href="' . esc_url( $permalink ) . '"><img src="' . esc_url( $image ) . '" alt="" loading="lazy"></a>';
			}
			$html .= '<div class="pe-card-body">';
			$html .= '<h3 class="pe-card-title"><a href="' . esc_url( $permalink ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3>';
			$html .= '<p class="pe-card-meta">' . esc_html( $when );
			if ( $location ) {
				$html .= ' · ' . esc_html( $location );
			}
			$html .= '</p>';
			if ( '1' === $atts['show_excerpt'] && '' !== trim( $post->post_content ) ) {
				$html .= '<p class="pe-card-excerpt">' . esc_html( wp_trim_words( wp_strip_all_tags( $post->post_content ), 24 ) ) . '</p>';
			}
			$html .= '</div></article>';
		}
		$html .= '</div>';

		// Random ordering must not be frozen by the cache.
		if ( 'rand' !== $atts['order'] ) {
			set_transient( $cache_key, $html, self::CACHE_TTL );
		}
		self::enqueue();
		return $html;
	}
}
