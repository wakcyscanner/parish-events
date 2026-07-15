<?php
/**
 * Manual test: removed != cancelled; explicit cancel flag; override rescue.
 * Run: wp eval-file wp-content/plugins/parish-events/tests/cancelled-test.php
 */

$posts = get_posts(
	array(
		'post_type'      => 'parish_event',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	)
);
$id = $posts[0]->ID;

// 1. Removed-in-grace: neutral banner, no Event JSON-LD, no Cancelled title.
wp_update_post(
	array(
		'ID'          => $id,
		'post_status' => 'pe_removed',
	)
);
update_post_meta( $id, '_pe_removed_at', time() );
delete_post_meta( $id, '_pe_cancelled' );
echo 'URL=' . get_permalink( $id ) . "\n";
echo "state1: removed-in-grace, not cancelled\n";

// 2. Explicitly cancelled.
update_post_meta( $id, '_pe_cancelled', '1' );
echo "state2 ready: cancelled flag set\n";

// 3. Override rescue: simulate the meta box save on the removed post.
// (Admin classes aren't loaded in CLI context.)
require_once PE_PLUGIN_DIR . 'admin/class-pe-meta-box.php';
update_post_meta( $id, '_pe_cancelled', '0' );
$_POST = array(
	'pe_meta_box_nonce' => wp_create_nonce( 'pe_meta_box' ),
	'pe_override'       => '1',
	'pe_featured'       => '',
	'pe_cancelled'      => '',
	'pe_video_url'      => '',
);
PE_Meta_Box::save( $id );
clean_post_cache( $id );
echo 'state3: after override-rescue save, status=' . get_post_status( $id ) . ' removed_at=[' . get_post_meta( $id, '_pe_removed_at', true ) . "]\n";

// Restore.
update_post_meta( $id, '_pe_override', '0' );
delete_post_meta( $id, '_pe_import_hash' );
echo "done\n";
