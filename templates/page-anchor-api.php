<?php
/**
 * Template Name: Anchor API Endpoint

 *	This is a collection of custom functions meant to received from Anchor's custom server.
 *  Currently it handles the follow:

	* Received views, storage and server then updates ACF records
	* Received backup snapshot info (name, size)

	* Examples:

	* Adding Quicksave
	* https://anchor.host/anchor-api/anchor.host/?git_commit=<git-commit>&core=<version>&plugins=<plugin-data>&themes=<theme-data>&token=token_key

	* Adding backup snapshot
	* https://anchor.host/anchor-api/anchor.host/?archive=anchorhost1.wpengine.com-2016-10-22.tar.gz&storage=235256&token=token_key

	* Updating views and storage
	* https://anchor.host/anchor-api/anchor.host/?views=9435345&storage=2334242&token=token_key

	* Assigning server
	* https://anchor.host/anchor-api/anchor.host/?server=104.197.69.102&token=token_key

	* Load token
	* https://anchor.host/anchor-api/anchor.host/?token_key=token_key&token=token_key

 */

$site = get_query_var('callback');
$date = $_GET['date'];
$archive = $_GET['archive'];
$storage = $_GET['storage'];
$views = $_GET['views'];
$email = $_GET['email'];
$server = $_GET['server'];
$core = $_GET['core'];
$plugins = $_GET['plugins'];
$themes = $_GET['themes'];
$token = $_GET['token'];
$token_key = $_GET['token_key'];
$git_commit = $_GET['git_commit'];

$args = array (
	'post_type'        => 'website',
	's'         			 => $site,
	'posts_per_page' 	 => 1
);

$query = new WP_Query( $args );

// The Loop
if ( $query->have_posts() ) {
	while ( $query->have_posts() ) {
		$query->the_post();
		// do something
		$site_id = get_the_id();
	}
} else {
	// no posts found
}

$result_count = $query->post_count;

// Verifies valid token and site exists with a period
if (substr_count($site, ".") > 0 and $token == CAPTAINCORE_CLI_TOKEN ) {

	// No website found. Generate a new record.
	if ($result_count == 0) {
		// Create post object
		$my_post = array(
		  'post_title'    => $site,
		  'post_type'     => 'website',
		  'post_status'   => 'publish',
		  'post_author'   => 2,
		);

		// Insert the post into the database
		$site_id = wp_insert_post( $my_post );
	}

	// Generate a new snapshot.
	if ($archive and $storage) {

		// Create post object
		$my_post = array(
		  'post_title'    => "Snapshot",
		  'post_type'     => 'snapshot',
		  'post_status'   => 'publish'
		);

		// Insert the post into the database
		$snapshot_id = wp_insert_post( $my_post );

		update_field("field_580b7cf4f2790", $archive, $snapshot_id);
		update_field("field_580b9776f2791", $storage, $snapshot_id);
		update_field("field_580b9784f2792", $site_id, $snapshot_id);
		update_field("field_59aecbd173318", $email, $snapshot_id);


		// Adds snapshot ID to title
		$my_post = array(
		  'ID'			  => $snapshot_id,
		  'post_title'    => "Snapshot ". $snapshot_id,
		);

		wp_update_post($my_post);

		// Send out snapshot email
		anchor_download_snapshot_email( $snapshot_id );

	}

	// Load Token Key
	if (isset($token_key)) {

		// defines the ACF keys to use
		$token_id = "field_52d16819ac39f";

		// update the repeater
		update_field($token_id,$token_key,$site_id);
		echo "Adding token key. \n";

	}

	// Generate a new CaptainCore quicksave
	if ($git_commit and $core and $plugins and $themes) {

		// Create post object
		$my_post = array(
		  'post_title'    => "Quicksave",
		  'post_type'     => 'cc_quicksave',
		  'post_status'   => 'publish'
		);

		// Insert the post into the database
		$snapshot_id = wp_insert_post( $my_post );

		update_field("field_59badaa96686f", $site_id, $snapshot_id);
		update_field("field_5a7dc6919ed81", $git_commit, $snapshot_id);
		update_field("field_59bae8d2ec7cc", $core, $snapshot_id);
		update_field("field_59badadc66871", $plugins, $snapshot_id);
		update_field("field_59badab866870", $themes, $snapshot_id);


		// Adds snapshot ID to title
		$my_post = array(
		  'ID'			  => $snapshot_id,
		  'post_title'    => "Quicksave ". $snapshot_id,
		);

		wp_update_post($my_post);

	}

	// Updates views and storage usage
	if (isset($views) and isset($storage)) {
		update_field("field_57e0b2b17eb2a", $storage, $site_id);
		update_field("field_57e0b2c07eb2b", $views, $site_id);
		do_action('acf/save_post', $site_id); // Runs ACF save post hooks
	}

	if ($server) {
		echo "Server assign";
		// args
		$args = array(
			'numberposts'	=> 1,
			'post_type'		=> 'server',
			'meta_key'		=> 'address',
			'meta_value'	=> $server
		);

		// query
		$the_query = new WP_Query( $args );

		if( $the_query->have_posts() ):

			while( $the_query->have_posts() ) : $the_query->the_post();

				$server_id = get_the_ID();

				update_field("field_5803aaa489114", $server_id, $site_id);

			endwhile;

		endif;

	}

} else {

get_header();  ?>
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

			<header class="main entry-header <?php echo $c; ?>" style="<?php echo 'background-image: url('.$featured_image[0].');' ?>">
				<h1><?php _e( 'Nothing to see here', 'anchorhost' ); ?></h1>

			</header><!-- .entry-header -->

			<div class="body-wrap">
			<div class="entry-content">
					<p><?php _e( 'Not sure where you were trying to go.', 'anchorhost' ); ?></p>
					<p>Lets <a href="<?php echo get_option('home'); ?>/">start from the beginning</a>.</p>


				</div><!-- .page-content -->
			</div><!-- .error-404 -->

			</article>

		</div><!-- #content -->
	</div><!-- #primary -->
<?php

get_footer();
}
 ?>
