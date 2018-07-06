<?php
/**
 * Template Name: CaptainCore API Endpoint
 *
 *  This is a collection of custom functions meant to received from CaptainCore CLI.
 *
 * Received views, storage and server then updates ACF records
 * Received backup snapshot info (name, size)
 *
 * Examples:
 *
 * Adding Quicksave
 * https://<captaincore-server>/captaincore-api/<site-domain>/?git_commit=<git-commit>&core=<version>&plugins=<plugin-data>&themes=<theme-data>&token=<token_key>
 *
 * Adding backup snapshot
 * https://<captaincore-server>/captaincore-api/<site-domain>/?archive=anchorhost1.wpengine.com-2016-10-22.tar.gz&storage=235256&token=<token_key>
 *
 * Updating views and storage
 * https://<captaincore-server>/captaincore-api/<site-domain>/?views=9435345&storage=2334242&token=token_key
 *
 * Assigning server
 * https://<captaincore-server>/captaincore-api/<site-domain>/?server=104.197.69.102&token=token_key
 *
 * Load token
 * https://<captaincore-server>/captaincore-api/<site-domain>/?token_key=token_key&token=token_key
 */

$site      = get_query_var( 'callback' );
$post_data = json_decode( file_get_contents( 'php://input' ) );

$site_source_id      = $post_data->site_source_id;
$site_destination_id = $post_data->site_destination_id;
$date                = $post_data->date;
$archive             = $post_data->archive;
$command             = $post_data->command;
$storage             = $post_data->storage;
$views               = $post_data->views;
$email               = $post_data->email;
$server              = $post_data->server;
$core                = $post_data->core;
$plugins             = base64_decode( $post_data->plugins );
$themes              = base64_decode( $post_data->themes );
$users               = base64_decode( $post_data->users );
$home_url            = $post_data->home_url;
$git_commit          = $post_data->git_commit;
$git_status          = trim( base64_decode( $post_data->git_status ) );
$token               = $post_data->token;
$token_key           = $post_data->token_key;
$data                = $post_data->data;

// Finding matching site by domain name (title)
$args  = array(
	'post_type' => 'captcore_website',
	'title'     => $site,
);
$sites = get_posts( $args );

// Assign site id
if ( count( $sites ) == 1 ) {
	// Assign ID
	$site_id = $sites[0]->ID;
}

// Verifies valid token and site exists with a period
if ( substr_count( $site, '.' ) > 0 and $token == CAPTAINCORE_CLI_TOKEN ) {

	// No website found. Generate a new record.
	if ( count( $sites ) == 0 ) {
		// Create post object
		$my_post = array(
			'post_title'  => $site,
			'post_type'   => 'captcore_website',
			'post_status' => 'publish',
			'post_author' => 2,
		);

		// Insert the post into the database
		$site_id = wp_insert_post( $my_post );

	}

	// Copy site
	if ( $command == 'copy' and $email ) {

		$site_source      = get_the_title( $site_source_id );
		$site_destination = get_the_title( $site_destination_id );

		// Send out completed email notice
		$to      = $email;
		$subject = "Anchor Hosting - Copy site ($site_source) to ($site_destination) completed";
		$body    = "Completed copying $site_source to $site_destination.<br /><br /><a href=\"http://$site_destination\">$site_destination</a>";
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );

		echo 'copy-site email sent';

	}

	// Production deploy to staging
	if ( $command == 'production-to-staging' and $email ) {

		$site_name   = get_field( 'site', $site_id );
		$domain_name = get_the_title( $site_id );
		$url         = 'https://staging-' . get_field( 'site_staging', $site_id ) . '.kinsta.com';

		// Send out completed email notice
		$to      = $email;
		$subject = "Anchor Hosting - Deploy to Staging ($domain_name)";
		$body    = 'Deploy to staging completed for ' . $domain_name . '.<br /><br /><a href="' . $url . '">' . $url . '</a>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );

		echo 'production-to-staging email sent';

	}

	// Kinsta staging deploy to production
	if ( $command == 'staging-to-production' and $email ) {

		$site_name   = get_field( 'site', $site_id );
		$domain_name = get_the_title( $site_id );
		$url         = 'https://' . get_field( 'site', $site_id ) . '.kinsta.com';

		// Send out completed email notice
		$to      = $email;
		$subject = "Anchor Hosting - Deploy to Production ($domain_name)";
		$body    = 'Deploy to production completed for ' . $domain_name . '.<br /><br /><a href="' . $url . '">' . $domain_name . '</a>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );

		echo 'staging-to-production email sent';

	}

	// Generate a new snapshot.
	if ( $archive and $storage ) {

		// Create post object
		$my_post = array(
			'post_title'  => 'Snapshot',
			'post_type'   => 'captcore_snapshot',
			'post_status' => 'publish',
		);

		// Insert the post into the database
		$snapshot_id = wp_insert_post( $my_post );

		update_field( 'field_580b7cf4f2790', $archive, $snapshot_id );
		update_field( 'field_580b9776f2791', $storage, $snapshot_id );
		update_field( 'field_580b9784f2792', $site_id, $snapshot_id );
		update_field( 'field_59aecbd173318', $email, $snapshot_id );

		// Adds snapshot ID to title
		$my_post = array(
			'ID'         => $snapshot_id,
			'post_title' => 'Snapshot ' . $snapshot_id,
		);

		wp_update_post( $my_post );

		// Send out snapshot email
		captaincore_download_snapshot_email( $snapshot_id );

	}

	// Load Token Key
	if ( $command == 'token' and isset( $token_key ) ) {

		// defines the ACF keys to use
		$token_id = 'field_52d16819ac39f';

		// update the repeater
		update_field( $token_id, $token_key, $site_id );
		echo "Adding token key. \n";

	}

	// Sync site data
	if ( $command == 'sync-data' and $core and $plugins and $themes and $users ) {

		// Updates site with latest $plugins, $themes, $core, $home_url and $users
		update_field( 'field_5a9421b004ed3', wp_slash( $plugins ), $site_id );
		update_field( 'field_5a9421b804ed4', wp_slash( $themes ), $site_id );
		update_field( 'field_5b2a900c85a77', wp_slash( $users ), $site_id );
		update_field( 'field_5a9421bc04ed5', $core, $site_id );
		update_field( 'field_5a944358bf146', $home_url, $site_id );

		echo '{"response":"Completed sync-data for ' . $site_id . '"}';

	}

	// Imports update log
	if ( $command == 'import-update-log' ) {

		$json        = $data;
		$json_decode = json_decode( $json );

		foreach ( $json_decode as $row ) {

			// Format for mysql timestamp format. Changes "2018-06-20-091520" to "2018-06-20 09:15:20"
			$date_formatted = substr_replace( $row->date, ' ', 10, 1 );
			$date_formatted = substr_replace( $date_formatted, ':', 13, 0 );
			$date_formatted = substr_replace( $date_formatted, ':', 16, 0 );
			$update_log     = json_encode( $row->updates );

			$new_update_log = array(
				'site_id'     => $site_id,
				'update_type' => $row->type,
				'update_log'  => $update_log,
				'created_at'  => $date_formatted,
			);

			$new_update_log_check = array(
				'site_id'     => $site_id,
				'created_at'  => $date_formatted,
			);

			// Add new update log if not added.
			if ( $update_log->valid_check( $new_update_log_check ) ) {
				$update_log->insert( $new_update_log );
			}

		}

	}

	// Generate a new CaptainCore quicksave
	if ( $command == 'quicksave' ) {

		// Check if Git Commit already entered for this Site ID
		$args = array(
			'post_type'      => 'captcore_quicksave',
			'posts_per_page' => '-1',
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'git_commit', // name of custom field
					'value'   => $git_commmit, // matches exaclty "123", not just 123. This prevents a match for "1234"
					'compare' => '=',
				),
				array(
					'key'     => 'website', // name of custom field
					'value'   => '"' . $site_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
					'compare' => 'LIKE',
				),
			),
		);

		$quicksave_duplicate_search = get_posts( $args );

		if ( count( $quicksave_duplicate_search ) == 0 ) {

			// Puts unix timestamp into int varible
			$date      = intval( $date );
			$timetamp  = new DateTime( "@$date" );
			$post_date = $timetamp->format( 'Y-m-d H:i:s' );

			// Updates site with latest $plugins, $themes, $core and $home_url
			update_field( 'field_5a9421b004ed3', wp_slash( $plugins ), $site_id );
			update_field( 'field_5a9421b804ed4', wp_slash( $themes ), $site_id );
			update_field( 'field_5b2a900c85a77', wp_slash( $users ), $site_id );
			update_field( 'field_5a9421bc04ed5', $core, $site_id );
			update_field( 'field_5a944358bf146', $home_url, $site_id );

			// Create post object
			$my_post = array(
				'post_title'    => 'Quicksave',
				'post_type'     => 'captcore_quicksave',
				'post_date_gmt' => $post_date,
				'post_status'   => 'publish',
			);

			// Insert the post into the database
			$quicksave_id = wp_insert_post( $my_post );

			update_field( 'field_59badaa96686f', $site_id, $quicksave_id );
			update_field( 'field_5a7dc6919ed81', $git_commit, $quicksave_id );
			update_field( 'field_5a7f0a55a5086', $git_status, $quicksave_id );
			update_field( 'field_59bae8d2ec7cc', $core, $quicksave_id );
			update_field( 'field_59badadc66871', wp_slash( $plugins ), $quicksave_id );
			update_field( 'field_59badab866870', wp_slash( $themes ), $quicksave_id );

			// Adds snapshot ID to title
			$my_post = array(
				'ID'         => $quicksave_id,
				'post_title' => 'Quicksave ' . $quicksave_id,
			);

			wp_update_post( $my_post );
			echo '{"response":"Completed adding Quicksave ' . $quicksave_id . ' for ' . $site_id . '"}';

		}
	}

	// Updates views and storage usage
	if ( isset( $views ) and isset( $storage ) ) {
		update_field( 'field_57e0b2b17eb2a', $storage, $site_id );
		update_field( 'field_57e0b2c07eb2b', $views, $site_id );
		do_action( 'acf/save_post', $site_id ); // Runs ACF save post hooks
	}

	if ( $server ) {
		echo 'Server assign';
		// args
		$args = array(
			'numberposts' => 1,
			'post_type'   => 'captcore_server',
			'meta_key'    => 'address',
			'meta_value'  => $server,
		);

		// query
		$the_query = new WP_Query( $args );

		if ( $the_query->have_posts() ) :

			while ( $the_query->have_posts() ) :
				$the_query->the_post();

				$server_id = get_the_ID();

				update_field( 'field_5803aaa489114', $server_id, $site_id );

			endwhile;

		endif;

	}

} else {

	get_header();  ?>
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

			<header class="main entry-header <?php echo $c; ?>" style="<?php echo 'background-image: url(' . $featured_image[0] . ');'; ?>">
				<h1><?php _e( 'Nothing to see here', 'anchorhost' ); ?></h1>

			</header><!-- .entry-header -->

			<div class="body-wrap">
			<div class="entry-content">
					<p><?php _e( 'Not sure where you were trying to go.', 'anchorhost' ); ?></p>
					<p>Lets <a href="<?php echo get_option( 'home' ); ?>/">start from the beginning</a>.</p>
				</div><!-- .page-content -->
			</div><!-- .error-404 -->

			</article>

		</div><!-- #content -->
	</div><!-- #primary -->
	<div style="display:none"><?php echo "${site} ${token}"; ?></div>
<?php

get_footer();

}
