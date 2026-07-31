<?php
/**
 * Card grid / carousel rendering, shared by the shortcode, the block, and the
 * homepage injection.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_Render {

	/**
	 * Published programs in display order (Order box ascending, then title).
	 *
	 * @param int    $count Max programs; 0 for all.
	 * @param string $group Program-group slug(s), comma-separated; '' for all.
	 * @return WP_Post[]
	 */
	public static function query( $count = 0, $group = '' ) {
		$args = array(
			'post_type'      => PP_CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $count > 0 ? (int) $count : -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'  => true,
		);

		$slugs = array_filter( array_map( 'trim', explode( ',', (string) $group ) ) );
		if ( $slugs ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => PP_CPT::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $slugs,
				),
			);
		}

		return get_posts( $args );
	}

	/**
	 * Render one card section per program group, using each group's name as
	 * the section heading. Groups with no published programs are skipped.
	 *
	 * @param array $args {groups: 'all'|csv slugs (display order), layout, count, align}
	 * @return string
	 */
	public static function render_groups( $args = array() ) {
		$args = wp_parse_args( $args, array( 'groups' => 'all' ) );

		if ( 'all' === trim( $args['groups'] ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => PP_CPT::TAXONOMY,
					'hide_empty' => true,
				)
			);
			$terms = is_wp_error( $terms ) ? array() : $terms;
		} else {
			$terms = array();
			foreach ( array_filter( array_map( 'trim', explode( ',', $args['groups'] ) ) ) as $slug ) {
				$term = get_term_by( 'slug', $slug, PP_CPT::TAXONOMY );
				if ( $term ) {
					$terms[] = $term;
				}
			}
		}

		$html = '';
		foreach ( $terms as $term ) {
			$section = self::render(
				array(
					'layout'  => isset( $args['layout'] ) ? $args['layout'] : 'grid',
					'count'   => isset( $args['count'] ) ? (int) $args['count'] : 0,
					'align'   => isset( $args['align'] ) ? $args['align'] : '',
					'heading' => $term->name,
					'group'   => $term->slug,
				)
			);
			if ( '' !== $section ) {
				$html .= $section;
			}
		}

		return $html;
	}

	/**
	 * Render the programs display.
	 *
	 * @param array $args {layout: grid|carousel, count: int, heading: string, align: ''|wide|full, group: slug csv}
	 * @return string HTML, empty when there are no published programs.
	 */
	public static function render( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'layout'  => 'grid',
				'count'   => 0,
				'heading' => '',
				'align'   => '',
				'group'   => '',
			)
		);

		$layout = 'carousel' === $args['layout'] ? 'carousel' : 'grid';

		// The block's Wide/Full alignment — without the class on this wrapper
		// the theme's default (narrow) content width applies and the grid
		// drops below three cards per row on desktop.
		$classes = 'pp-programs pp-layout-' . $layout;
		if ( in_array( $args['align'], array( 'wide', 'full' ), true ) ) {
			$classes .= ' align' . $args['align'];
		}

		$programs = self::query( (int) $args['count'], $args['group'] );

		if ( ! $programs ) {
			return '';
		}

		wp_enqueue_style( 'parish-programs' );
		if ( 'carousel' === $layout ) {
			wp_enqueue_script( 'pp-carousel' );
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $classes ); ?>">
			<?php if ( '' !== trim( $args['heading'] ) ) : ?>
				<h2 class="pp-heading"><?php echo esc_html( $args['heading'] ); ?></h2>
			<?php endif; ?>
			<?php if ( 'carousel' === $layout ) : ?>
				<div class="pp-carousel">
					<button type="button" class="pp-arrow pp-arrow-prev pp-hidden" aria-label="<?php esc_attr_e( 'Previous', 'parish-programs' ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><polyline points="15 18 9 12 15 6"></polyline></svg>
					</button>
					<div class="pp-viewport">
						<div class="pp-track">
							<?php foreach ( $programs as $program ) : ?>
								<?php self::card( $program, true ); ?>
							<?php endforeach; ?>
						</div>
					</div>
					<button type="button" class="pp-arrow pp-arrow-next" aria-label="<?php esc_attr_e( 'Next', 'parish-programs' ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><polyline points="9 6 15 12 9 18"></polyline></svg>
					</button>
				</div>
			<?php else : ?>
				<div class="pp-grid">
					<?php foreach ( $programs as $program ) : ?>
						<?php self::card( $program, false ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render one program card. Carousel cards get a fixed height with a
	 * clipped, expandable description; grid cards show everything.
	 *
	 * @param WP_Post $program     Program post.
	 * @param bool    $is_carousel Carousel context.
	 */
	private static function card( $program, $is_carousel ) {
		$date_text = get_post_meta( $program->ID, '_pp_date_text', true );
		$link_url  = get_post_meta( $program->ID, '_pp_link_url', true );
		$link_text = get_post_meta( $program->ID, '_pp_link_text', true );
		if ( '' === trim( $link_text ) ) {
			$link_text = __( 'Learn More', 'parish-programs' );
		}
		?>
		<div class="pp-card">
			<?php if ( has_post_thumbnail( $program ) ) : ?>
				<div class="pp-card-image">
					<?php echo get_the_post_thumbnail( $program, 'medium_large', array( 'loading' => 'lazy' ) ); ?>
				</div>
			<?php endif; ?>
			<div class="pp-card-content">
				<h3><?php echo esc_html( get_the_title( $program ) ); ?></h3>
				<?php if ( '' !== trim( (string) $date_text ) ) : ?>
					<p class="pp-card-date"><?php echo esc_html( $date_text ); ?></p>
				<?php endif; ?>
				<?php if ( $is_carousel ) : ?>
					<div class="pp-card-desc-wrap">
						<div class="pp-card-desc"><?php echo wp_kses_post( wpautop( $program->post_content ) ); ?></div>
						<div class="pp-card-fade" aria-hidden="true"></div>
					</div>
					<button type="button" class="pp-card-toggle"><?php esc_html_e( 'Read more', 'parish-programs' ); ?></button>
				<?php else : ?>
					<div class="pp-card-desc"><?php echo wp_kses_post( wpautop( $program->post_content ) ); ?></div>
				<?php endif; ?>
				<?php if ( '' !== trim( (string) $link_url ) ) : ?>
					<a href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener" class="pp-card-link"
						aria-label="<?php echo esc_attr( $link_text . ' - ' . get_the_title( $program ) ); ?>"><?php echo esc_html( $link_text ); ?> &rarr;</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
