<?php
/**
 * Manual test: override protects all fields; unchecking re-syncs.
 * Run: wp eval-file wp-content/plugins/parish-events/tests/override-test.php
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
$id    = $posts[0]->ID;

update_post_meta( $id, '_pe_override', '1' );
update_post_meta( $id, '_pe_location', 'TEST ROOM' );
wp_update_post(
	array(
		'ID'         => $id,
		'post_title' => 'MY CUSTOM TITLE',
	)
);

PE_Importer::run( 'manual' );
echo 'with-override: title=' . get_the_title( $id ) . ' loc=' . get_post_meta( $id, '_pe_location', true ) . "\n";

// What the meta box save handler does when override is unchecked.
update_post_meta( $id, '_pe_override', '0' );
delete_post_meta( $id, '_pe_import_hash' );

PE_Importer::run( 'manual' );
echo 'after-uncheck: title=' . get_the_title( $id ) . ' loc=' . get_post_meta( $id, '_pe_location', true ) . "\n";
