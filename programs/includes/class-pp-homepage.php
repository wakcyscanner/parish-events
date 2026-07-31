<?php
/**
 * Homepage injection for rigid theme templates.
 *
 * The Celine homepage template offers no block or widget area, so the
 * carousel is rendered (hidden) in wp_footer and a few lines of JS move it
 * to the position configured in settings — a CSS selector plus
 * before/after/inside placement. If the selector matches nothing (say, a
 * theme update renamed the class), the markup simply stays hidden: the
 * homepage degrades to what it was, never to broken layout.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_Homepage {

	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'inject' ), 5 );
	}

	public static function inject() {
		if ( ! is_front_page() ) {
			return;
		}

		$settings = pp_get_settings();
		if ( '1' !== $settings['homepage_enabled'] || '' === trim( $settings['homepage_selector'] ) ) {
			return;
		}

		$html = PP_Render::render(
			array(
				'layout'  => $settings['homepage_layout'],
				'count'   => (int) $settings['homepage_count'],
				'heading' => $settings['homepage_heading'],
				'group'   => $settings['homepage_group'],
			)
		);

		if ( '' === $html ) {
			return;
		}

		$position = in_array( $settings['homepage_position'], array( 'before', 'after', 'prepend', 'append' ), true )
			? $settings['homepage_position']
			: 'after';

		// Rendered in the footer, revealed in place. Server-rendered so the
		// content is crawlable and cached along with the page.
		echo '<div id="pp-homepage-programs" hidden>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- PP_Render escapes its output.
		?>
		<script>
		( function () {
			var wrap   = document.getElementById( 'pp-homepage-programs' );
			var target = document.querySelector( <?php echo wp_json_encode( $settings['homepage_selector'] ); ?> );
			if ( ! wrap || ! target ) {
				return;
			}
			switch ( <?php echo wp_json_encode( $position ); ?> ) {
				case 'before':
					target.parentNode.insertBefore( wrap, target );
					break;
				case 'prepend':
					target.insertBefore( wrap, target.firstChild );
					break;
				case 'append':
					target.appendChild( wrap );
					break;
				default:
					target.parentNode.insertBefore( wrap, target.nextSibling );
			}
			wrap.removeAttribute( 'hidden' );
		} )();
		</script>
		<?php
	}
}
