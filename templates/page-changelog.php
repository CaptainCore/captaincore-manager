<?php
/**
 * Template Name: Changelog
 */

get_header(); 

if ( is_user_logged_in() ) {

?>
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

				<header class="main entry-header <?php echo $c; ?>" style="<?php echo 'background-image: url('.$featured_image[0].');' ?>">			
					<h1><?php echo $website; ?></h1>

					<span class="overlay"></span>
				</header><!-- .entry-header -->

				<div class="body-wrap">
				<div class="entry-content">
				<?php 
				$args = array (
					'post_type'              => 'changelog',
					'posts_per_page' 		 => "-1"
				);

				$query = new WP_Query( $args );

				// The Loop
				if ( $query->have_posts() ) {
					while ( $query->have_posts() ) { ?>
						<hr >
						<?php
						$query->the_post();

						$changelog_id = $post->ID;

						$terms = get_the_terms( $post->ID, 'changelog_website' );

						if ( $terms && ! is_wp_error( $terms ) ) : 
						 
						    $draught_links = array();
						 
						    foreach ( $terms as $term ) {
						        echo $term->name;
						        if ( $term->name == "Global") {
						        	echo "<br />- Assigned to Global";
						        	update_field("field_57df3ebfbfee3", "1", $changelog_id); 
						        } else {
						        	echo "Lookup up website and assigning";
						        	$lookup_args = array (
										'post_type'         => array( 'website' ),
										's'          		=> $term->name,
										'posts_per_page'    => '1',
									);

									// The Query
									$query_lookup = new WP_Query( $lookup_args );

									// The Loop
									if ( $query_lookup->have_posts() ) {
										while ( $query_lookup->have_posts() ) {
											$query_lookup->the_post();
											$lookup_id = get_the_ID();
										}
									} else {
										// no posts found
									}
 
									echo $lookup_id;
						        	update_field("field_57d89e9168500", array($lookup_id), $changelog_id); 
						        }
						    }
						                         
						  
						endif; 

						
						?>

						<?php the_content(); ?>
						
				<?php	}
				} else {
					// no posts found
				}

				$result_count = $query->post_count;

				// Restore original Post Data
				wp_reset_postdata(); ?>

		
			</div><!-- .entry-content -->
			<footer class="entry-footer">

			</footer><!-- .entry-footer -->
			</div>
			

		</main><!-- #main -->
	</div><!-- #primary -->

<?php }

get_footer(); ?>