<?php
/**
 * "Upcoming Parish Events" widget — a thin wrapper around the
 * [parish_events_upcoming] shortcode so it can live in widget areas
 * (including the block-based widget editor via the Legacy Widget block).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PE_Upcoming_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'pe_upcoming_events',
			__( 'Upcoming Parish Events', 'parish-events' ),
			array( 'description' => __( 'A compact list of the next parish events.', 'parish-events' ) )
		);
	}

	public static function register() {
		register_widget( __CLASS__ );
	}

	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Upcoming Events', 'parish-events' );
		$count = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title, $instance, $this->id_base ) ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo do_shortcode( '[parish_events_upcoming count="' . $count . '"]' );
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Upcoming Events', 'parish-events' );
		$count = isset( $instance['count'] ) ? absint( $instance['count'] ) : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'parish-events' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Number of events:', 'parish-events' ); ?></label>
			<input id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" min="1" max="12" value="<?php echo esc_attr( $count ); ?>">
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title' => sanitize_text_field( $new_instance['title'] ),
			'count' => min( 12, max( 1, absint( $new_instance['count'] ) ) ),
		);
	}
}
