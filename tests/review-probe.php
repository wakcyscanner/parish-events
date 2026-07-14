<?php
/**
 * Review probe: prepare a past-grace removed post and an overridden post
 * with rich content, then print their IDs/permalinks for curl checks.
 * Run: wp eval-file wp-content/plugins/parish-events/tests/review-probe.php
 */

$removed = get_posts(
	array(
		'post_type'      => 'parish_event',
		'post_status'    => 'pe_removed',
		'posts_per_page' => 1,
	)
);
if ( $removed ) {
	$rid = $removed[0]->ID;
} else {
	$p   = get_posts(
		array(
			'post_type'      => 'parish_event',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	$rid = $p[0]->ID;
	wp_update_post(
		array(
			'ID'          => $rid,
			'post_status' => 'pe_removed',
		)
	);
}
update_post_meta( $rid, '_pe_removed_at', 1700000000 ); // far past grace
echo 'REMOVED_ID=' . $rid . ' URL=' . get_permalink( $rid ) . "\n";

$pub = get_posts(
	array(
		'post_type'      => 'parish_event',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'ID',
		'order'          => 'DESC',
	)
);
$oid = $pub[0]->ID;
update_post_meta( $oid, '_pe_override', '1' );
wp_update_post(
	array(
		'ID'           => $oid,
		'post_content' => '<h2>Rich Heading</h2><p>Paragraph with <strong>bold</strong>.</p><ul><li>Item one</li></ul>',
	)
);
echo 'OVERRIDE_ID=' . $oid . ' URL=' . get_permalink( $oid ) . "\n";
