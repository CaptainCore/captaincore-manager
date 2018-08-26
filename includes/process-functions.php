<?php

// Merges process with destination then removes source
function captaincore_merge_process( $process_id_source, $process_id_destination ) {

	$arguments = array(
		'post_type'      => 'captcore_processlog',
		'posts_per_page' => '-1',
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'     => 'process', // name of custom field
				'value'   => '"' . $process_id_source . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
				'compare' => 'LIKE',
			),
		),
	);

	$source_processlogs = get_posts( $arguments );

	// Assign process logs to new process
	foreach ( $source_processlogs as $source_processlog_id ) {
		update_field( 'process', $process_id_destination, $source_processlog_id );
	}

	// Removed source process
	wp_delete_post( $process_id_source, true );

}
