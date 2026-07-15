<?php
/**
 * Flyer slot checks. Run with:
 *   npx @wordpress/env run cli -- wp eval-file wp-content/plugins/parish-events/tests/flyer-test.php --user=admin
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

// A published, non-overridden, upcoming event.
$posts = get_posts(
	array(
		'post_type'   => 'parish_event',
		'post_status' => 'publish',
		'numberposts' => 1,
		'meta_query'  => array(
			array(
				'key'     => '_pe_event_date',
				'value'   => pe_today(),
				'compare' => '>=',
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
echo "post: $post_id (" . get_the_title( $post_id ) . ", " . get_permalink( $post_id ) . ")\n";

// Build a real image attachment.
$img = imagecreatetruecolor( 600, 800 );
imagefill( $img, 0, 0, imagecolorallocate( $img, 220, 235, 250 ) );
imagestring( $img, 5, 200, 390, 'TEST FLYER', imagecolorallocate( $img, 20, 40, 90 ) );
ob_start();
imagepng( $img );
$bits = wp_upload_bits( 'pe-test-flyer.png', null, ob_get_clean() );
if ( ! empty( $bits['error'] ) ) {
	echo "FAIL upload: {$bits['error']}\n";
	exit( 1 );
}
$att_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'pe-test-flyer',
		'post_status'    => 'inherit',
	),
	$bits['file']
);
require_once ABSPATH . 'wp-admin/includes/image.php';
wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $bits['file'] ) );
echo "attachment: $att_id\n";

// 1. Meta-box save stores the flyer with override OFF.
$_POST = array(
	'pe_meta_box_nonce' => wp_create_nonce( 'pe_meta_box' ),
	'pe_flyer_id'       => (string) $att_id,
);
PE_Meta_Box::save( $post_id );
pe_check( 'save stores flyer without override', (int) get_post_meta( $post_id, '_pe_flyer_id', true ) === $att_id );
pe_check( 'save did not flip override on', '1' !== get_post_meta( $post_id, '_pe_override', true ) );

// 2. Import leaves the flyer alone (importer never writes _pe_flyer_id).
$before = get_post_meta( $post_id, '_pe_flyer_id', true );
PE_Importer::run( 'manual' );
pe_check( 'import run leaves flyer meta intact', get_post_meta( $post_id, '_pe_flyer_id', true ) === $before );
pe_check( 'import run left post published', 'publish' === get_post_status( $post_id ) );

// 3. Front-end render: figure below details, linked to full size, in JSON-LD.
// Render via a real singular main query so the the_content guards
// (is_singular + in_the_loop + is_main_query) all pass in CLI.
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
pe_check( 'figure.pe-event-flyer rendered', false !== strpos( $out, 'pe-event-flyer' ) );
pe_check( 'flyer links to full-size file', false !== strpos( $out, 'pe-test-flyer' ) );
pe_check( 'flyer rendered after details card', strpos( $out, 'pe-event-flyer' ) > strpos( $out, 'pe-event-details' ) );
pe_check( 'fallback alt text present', false !== strpos( $out, 'Flyer for' ) );

$jsonld = PE_JSONLD::build( $post_id, 'https://schema.org/EventScheduled' );
pe_check( 'JSON-LD image includes flyer', is_array( $jsonld['image'] ) && count( array_filter( $jsonld['image'], fn( $u ) => false !== strpos( $u, 'pe-test-flyer' ) ) ) === 1 );

// 4. Invalid / cleared values remove the meta.
$_POST['pe_flyer_id'] = '999999999';
PE_Meta_Box::save( $post_id );
pe_check( 'nonexistent attachment id clears flyer', '' === get_post_meta( $post_id, '_pe_flyer_id', true ) );

$_POST['pe_flyer_id'] = (string) $att_id;
PE_Meta_Box::save( $post_id );
$_POST['pe_flyer_id'] = '';
PE_Meta_Box::save( $post_id );
pe_check( 'empty value clears flyer', '' === get_post_meta( $post_id, '_pe_flyer_id', true ) );

// Cleanup.
wp_delete_attachment( $att_id, true );
delete_post_meta( $post_id, '_pe_flyer_id' );
$_POST = array();

exit( $fail > 0 ? 1 : 0 );
