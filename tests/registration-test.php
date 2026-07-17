<?php
/**
 * Registration link + cost field checks. Run with:
 *   npx @wordpress/env run cli -- wp eval-file wp-content/plugins/parish-events/tests/registration-test.php --user=admin
 */

if ( ! class_exists( 'PE_Meta_Box' ) ) {
	require_once WP_PLUGIN_DIR . '/parish-events/admin/class-pe-meta-box.php';
}

$fail = 0;
function pe_check( $label, $ok ) {
	global $fail;
	if ( ! $ok ) {
		$fail++;
	}
	echo ( $ok ? 'PASS' : 'FAIL' ) . " $label\n";
}

function pe_render( $post_id ) {
	$GLOBALS['wp_the_query'] = new WP_Query(
		array(
			'p'         => $post_id,
			'post_type' => 'parish_event',
		)
	);
	$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
	$out                     = '';
	while ( have_posts() ) {
		the_post();
		ob_start();
		the_content();
		$out = ob_get_clean();
	}
	wp_reset_query();
	return $out;
}

// An upcoming, non-overridden published event.
$posts = get_posts(
	array(
		'post_type'   => 'parish_event',
		'post_status' => 'publish',
		'numberposts' => 1,
		'meta_query'  => array(
			array(
				'key'     => '_pe_event_date',
				'value'   => pe_today(),
				'compare' => '>',
			),
			array(
				'key'     => '_pe_override',
				'value'   => '1',
				'compare' => '!=',
			),
		),
	)
);
if ( ! $posts ) {
	echo "FAIL no test post available\n";
	exit( 1 );
}
$post_id = $posts[0]->ID;
echo "post: $post_id\n";

// 1. Save both fields with override OFF.
$_POST = array(
	'pe_meta_box_nonce'   => wp_create_nonce( 'pe_meta_box' ),
	'pe_registration_url' => 'https://example.org/signup?e=42',
	'pe_cost'             => '$25 per family',
);
PE_Meta_Box::save( $post_id );
pe_check( 'registration url saved without override', 'https://example.org/signup?e=42' === get_post_meta( $post_id, '_pe_registration_url', true ) );
pe_check( 'cost saved without override', '$25 per family' === get_post_meta( $post_id, '_pe_cost', true ) );
pe_check( 'override untouched', '1' !== get_post_meta( $post_id, '_pe_override', true ) );

// 2. Render: button + cost row.
$out = pe_render( $post_id );
pe_check( 'Register / RSVP button rendered', false !== strpos( $out, 'pe-register-btn' ) && false !== strpos( $out, 'https://example.org/signup?e=42' ) );
pe_check( 'button opens in new tab', false !== strpos( $out, 'noopener' ) );
pe_check( 'cost row rendered in details', false !== strpos( $out, '<dt>Cost</dt>' ) && false !== strpos( $out, '$25 per family' ) );

// 3. JSON-LD offer variants.
$ld = PE_JSONLD::build( $post_id, 'https://schema.org/EventScheduled' );
pe_check( 'offer url is the registration link', 'https://example.org/signup?e=42' === $ld['offers']['url'] );
pe_check( 'price parsed from cost text', '25' === $ld['offers']['price'] && 'USD' === $ld['offers']['priceCurrency'] );
pe_check( 'priced event not marked free', false === $ld['isAccessibleForFree'] );

update_post_meta( $post_id, '_pe_cost', 'Free-will offering' );
$ld = PE_JSONLD::build( $post_id, 'https://schema.org/EventScheduled' );
pe_check( 'free-will offering marked free, price 0', true === $ld['isAccessibleForFree'] && '0' === $ld['offers']['price'] );

update_post_meta( $post_id, '_pe_cost', 'Suggested donation' );
$ld = PE_JSONLD::build( $post_id, 'https://schema.org/EventScheduled' );
pe_check( 'unparseable cost omits price', ! isset( $ld['offers']['price'] ) && false === $ld['isAccessibleForFree'] );

delete_post_meta( $post_id, '_pe_cost' );
$ld = PE_JSONLD::build( $post_id, 'https://schema.org/EventScheduled' );
pe_check( 'no cost: free with price 0 (previous behavior)', true === $ld['isAccessibleForFree'] && '0' === $ld['offers']['price'] );

delete_post_meta( $post_id, '_pe_registration_url' );
$ld = PE_JSONLD::build( $post_id, 'https://schema.org/EventScheduled' );
pe_check( 'no registration link: offer url is permalink', get_permalink( $post_id ) === $ld['offers']['url'] );
update_post_meta( $post_id, '_pe_registration_url', 'https://example.org/signup?e=42' );

// 4. Import leaves both fields alone.
update_post_meta( $post_id, '_pe_cost', '$10' );
PE_Importer::run( 'manual' );
pe_check( 'import leaves registration url', 'https://example.org/signup?e=42' === get_post_meta( $post_id, '_pe_registration_url', true ) );
pe_check( 'import leaves cost', '$10' === get_post_meta( $post_id, '_pe_cost', true ) );

// 5. Past events hide the button (details row still shows).
$past = get_posts(
	array(
		'post_type'   => 'parish_event',
		'post_status' => 'publish',
		'numberposts' => 1,
		'meta_query'  => array(
			array(
				'key'     => '_pe_event_date',
				'value'   => pe_today(),
				'compare' => '<',
			),
		),
	)
);
if ( $past ) {
	update_post_meta( $past[0]->ID, '_pe_registration_url', 'https://example.org/too-late' );
	$out_past = pe_render( $past[0]->ID );
	pe_check( 'past event shows no registration button', false === strpos( $out_past, 'pe-register-btn' ) );
	delete_post_meta( $past[0]->ID, '_pe_registration_url' );
}

// 6. Clearing and invalid values.
$_POST['pe_registration_url'] = 'not a url';
$_POST['pe_cost']             = '';
PE_Meta_Box::save( $post_id );
pe_check( 'invalid url cleared', '' === get_post_meta( $post_id, '_pe_registration_url', true ) );
pe_check( 'empty cost cleared', '' === get_post_meta( $post_id, '_pe_cost', true ) );

$_POST = array();
exit( $fail > 0 ? 1 : 0 );
