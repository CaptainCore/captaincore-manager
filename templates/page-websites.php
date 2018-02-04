<?php
/**
 * @package swell
 * Template Name: Websites
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<?php while ( have_posts() ) : the_post(); ?>

				
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					
					<?php 
					$featured_image = "";
					$c = ""; 
					if (is_page()) {
						if( has_post_thumbnail() ) { 
							$featured_image = wp_get_attachment_image_src( get_post_thumbnail_id($post->ID), 'swell_full_width' ); 
							$c = "has-background";		
						}
					} 
					?>

					<header class="main entry-header <?php echo $c; ?>" style="<?php echo 'background-image: url('.$featured_image[0].');' ?>">			
						<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
						
						<?php if( $post->post_excerpt ) { ?>
							<hr class="short" />
						<span class="meta">			
							<?php echo $post->post_excerpt; ?>		
						</span>	
						<?php } ?>	
						<span class="overlay"></span>
					</header><!-- .entry-header -->

					<div class="body-wrap">
					<div class="entry-content">
						<?php the_content(); ?>
						<?php $links = get_field("links");
						if ($links) { ?>
						<div class="websites">
						<?php while(has_sub_field('links')) { 
							
							$name = get_sub_field('name');
							$link = get_sub_field('link');
							$link_name = get_sub_field('link_name');
							$image_id = get_sub_field('logo');
							$image = wp_get_attachment_image_src( $image_id, "portfolio-thumb-square" );
							$tags = get_sub_field('tags');
							?>
							<a class="link" href="<?php echo $link; ?>" target="_blank">

								<span class="link-logo"><img src="<?php echo $image[0]; ?>"></span>
									
								<span class="name"><?php echo $name; ?></span>
								<span class="website"><?php echo $link_name; ?></span>
								<span class="tags">
									<?php 
									$tag_list = explode(', ', $tags); //split string into array seperated by ', '
									foreach($tag_list as $tag) { ?>
									<span class="tag"><?php echo $tag; ?></span>
									<?php } ?>
								</span>
							</a>

							<?php } ?>
						</div>
						<?php } ?>
						<?php
							wp_link_pages( array(
								'before' => '<div class="page-links">' . __( 'Pages:', 'swell' ),
								'after'  => '</div>',
							) );
						?>
					</div><!-- .entry-content -->
					<footer class="entry-footer">
						<?php edit_post_link( __( 'Edit', 'swell' ), '<span class="edit-link">', '</span>' ); ?>
					</footer><!-- .entry-footer -->
					</div>
					
				<?php if ( comments_open() || '0' != get_comments_number() ) : // If comments are open or we have at least one comment, load up the comment template?>
						<div class="comments-wrap">
							<?php comments_template(); ?>
						</div>
				<?php endif; ?>

			<?php endwhile; // end of the loop. ?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>