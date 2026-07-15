<?php
/**
 * Manual test: featured video URL parsing + import immunity.
 * Run: wp eval-file wp-content/plugins/parish-events/tests/video-test.php
 */

$cases = array(
	'https://www.youtube.com/watch?v=dQw4w9WgXcQ'      => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
	'https://youtu.be/dQw4w9WgXcQ?t=42'                => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
	'https://www.youtube.com/shorts/dQw4w9WgXcQ'       => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
	'https://m.youtube.com/watch?v=dQw4w9WgXcQ'        => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
	'https://www.youtube.com/embed/dQw4w9WgXcQ'        => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
	'https://vimeo.com/123456789'                      => 'https://player.vimeo.com/video/123456789?dnt=1',
	'https://player.vimeo.com/video/123456789'         => 'https://player.vimeo.com/video/123456789?dnt=1',
	'https://vimeo.com/123456789?share=copy'           => 'https://player.vimeo.com/video/123456789?dnt=1',
	'https://example.com/watch?v=dQw4w9WgXcQ'          => '',
	'https://evil.com/youtube.com/watch?v=x'           => '',
	'javascript:alert(1)'                              => '',
	'https://vimeo.com/about'                          => '',
	''                                                 => '',
);

$pass = 0;
foreach ( $cases as $in => $expected ) {
	$got = pe_video_embed_url( $in );
	if ( $got === $expected ) {
		$pass++;
	} else {
		echo "FAIL: [$in]\n  expected [$expected]\n  got      [$got]\n";
	}
}
echo "parser: $pass/" . count( $cases ) . " cases pass\n";

// Import immunity: set a video on a post, run an import, confirm it survives.
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
update_post_meta( $id, '_pe_video_url', 'https://youtu.be/dQw4w9WgXcQ' );
delete_post_meta( $id, '_pe_import_hash' ); // force a full field rewrite
PE_Importer::run( 'manual' );
echo 'after full-rewrite import, video url: ' . get_post_meta( $id, '_pe_video_url', true ) . "\n";
echo 'PERMALINK=' . get_permalink( $id ) . "\n";
