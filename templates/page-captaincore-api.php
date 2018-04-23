<?php
/**
 * Template Name: CaptainCore API Endpoint

 *  This is a collection of custom functions meant to received from Anchor's custom server.
 *  Currently it handles the follow:

 * Received views, storage and server then updates ACF records
 * Received backup snapshot info (name, size)

 * Examples:

 * Adding Quicksave
 * https://<captaincore-server>/captaincore-api/<site-domain>/?git_commit=<git-commit>&core=<version>&plugins=<plugin-data>&themes=<theme-data>&token=<token_key>

 * Adding backup snapshot
 * https://<captaincore-server>/captaincore-api/<site-domain>/?archive=anchorhost1.wpengine.com-2016-10-22.tar.gz&storage=235256&token=<token_key>

 * Updating views and storage
 * https://<captaincore-server>/captaincore-api/<site-domain>/?views=9435345&storage=2334242&token=token_key

 * Assigning server
 * https://<captaincore-server>/captaincore-api/<site-domain>/?server=104.197.69.102&token=token_key

 * Load token
 * https://<captaincore-server>/captaincore-api/<site-domain>/?token_key=token_key&token=token_key

 */

$site       = get_query_var( 'callback' );
$date       = $_POST['date'];
$archive    = $_POST['archive'];
$command    = $_POST['command'];
$storage    = $_POST['storage'];
$views      = $_POST['views'];
$email      = $_POST['email'];
$server     = $_POST['server'];
$core       = $_POST['core'];
$plugins    = base64_decode( $_POST['plugins'] );
$themes     = base64_decode( $_POST['themes'] );
$token      = $_POST['token'];
$token_key  = $_POST['token_key'];
$git_commit = $_POST['git_commit'];
$home_url   = $_POST['home_url'];
$git_status = trim( base64_decode( $_POST['git_status'] ) );


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

	// Production deploy to staging
	if ( $command == 'production-to-staging' and $email ) {

		$install_name = get_field( 'install', $site_id );
		$domain_name  = get_the_title( $site_id );
		$url          = 'https://staging-' . get_field( 'install_staging', $site_id ) . '.kinsta.com';

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

		$install_name = get_field( 'install', $site_id );
		$domain_name  = get_the_title( $site_id );
		$url          = 'https://' . get_field( 'install', $site_id ) . '.kinsta.com';

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
		anchor_download_snapshot_email( $snapshot_id );

	}

	// Load Token Key
	if ( isset( $token_key ) ) {

		// defines the ACF keys to use
		$token_id = 'field_52d16819ac39f';

		// update the repeater
		update_field( $token_id, $token_key, $site_id );
		echo "Adding token key. \n";

	}

	// Generate a new CaptainCore quicksave
	if ( $git_commit and $core and $plugins and $themes ) {

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

			// Updates site with latest $plugins, $themes, $core and $home_url
			update_field( 'field_5a9421b004ed3', wp_slash( $plugins ), $site_id );
			update_field( 'field_5a9421b804ed4', wp_slash( $themes ), $site_id );
			update_field( 'field_5a9421bc04ed5', $core, $site_id );
			update_field( 'field_5a944358bf146', $home_url, $site_id );

			// Create post object
			$my_post = array(
				'post_title'  => 'Quicksave',
				'post_type'   => 'captcore_quicksave',
				'post_status' => 'publish',
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
			echo "Adding Quicksave \n";

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
<?php

get_footer();

}
