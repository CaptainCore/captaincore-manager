<?php
/**
 * @package swell
 * Template Name: Company Handbook
 */

if (!is_user_logged_in() ) {
	wp_redirect ( home_url("/my-account/") );
	exit;
} else {
	$get_user_id = get_current_user_id(); // get user ID
    $get_user_data = get_userdata($get_user_id); // get user data
    $get_roles = implode($get_user_data->roles);
    if( "administrator" != $get_roles ){ // check if role name == user role
        wp_redirect ( home_url("/my-account/") );
        exit;
    }
}

get_header(); 
jetpack_require_lib( 'markdown' );

?>

<script>

jQuery(document).ready(function(){ 

	// Generate this week and last week.
	thisweekday = "<?php echo date( "N" ); ?>";
	thisyearweek = "<?php echo date( "Y-W" ); ?>";
	lastyearweek = "<?php echo date( "Y-W", strtotime('-7 days') ); ?>";
	if (jQuery('.process-calendar .process-week-view[data-yearweek='+ thisyearweek +']').length == 0) {
		jQuery('.process-calendar').append( '<strong>This Week</strong><div class="process-week-view" data-yearweek='+ thisyearweek +'><div class="process-day"><span>Sun</span></div><div class="process-day"><span>Mon</span></div><div class="process-day"><span>Tue</span></div><div class="process-day"><span>Wed</span></div><div class="process-day"><span>Thur</span></div><div class="process-day"><span>Fri</span></div><div class="process-day"><span>Sat</span></div></div>' );
	}
	if (jQuery('.process-calendar .process-week-view[data-yearweek='+ lastyearweek +']').length == 0) {
		jQuery('.process-calendar').append( '<strong>Last Week</strong><div class="process-week-view" data-yearweek='+ lastyearweek +'><div class="process-day"><span>Sun</span></div><div class="process-day"><span>Mon</span></div><div class="process-day"><span>Tue</span></div><div class="process-day"><span>Wed</span></div><div class="process-day"><span>Thur</span></div><div class="process-day"><span>Fri</span></div><div class="process-day"><span>Sat</span></div></div>' );
	}

	jQuery('.activity-log-unsorted .process-star').each(function() {

		weekday = jQuery(this).data("weekday") + 1;
		yearweek = jQuery(this).data("yearweek");

	    items = jQuery(this).detach();

	    // Check if parent item exists, else generate it. 
	    if (jQuery('.process-calendar .process-week-view[data-yearweek='+ yearweek +']').length == 0) {
	    	jQuery('.process-calendar').append( '<div class="process-week-view" data-yearweek='+ yearweek +'><div class="process-day"><span>Sun</span></div><div class="process-day"><span>Mon</span></div><div class="process-day"><span>Tue</span></div><div class="process-day"><span>Wed</span></div><div class="process-day"><span>Thur</span></div><div class="process-day"><span>Fri</span></div><div class="process-day"><span>Sat</span></div></div>' );
	    }


	    // Attached to week with unique key: 2010-01 
	    items.appendTo( '.process-calendar .process-week-view[data-yearweek='+ yearweek +' ] .process-day:nth-child('+weekday+')');

		//jQuery("#result-"+key).prepend('<div class="heading">'+ parsedDate + ' (' + months[key] + ')</div>');

	});

});
</script>

<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">

		<?php if (is_user_logged_in()) { ?>

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			
			<?php 
			$featured_image = "";
			$c = ""; 
			if (is_page()) {
				if( has_post_thumbnail() ) { 
					$featured_image = wp_get_attachment_image_src( get_post_thumbnail_id($post->ID), 'swell_full_width' ); 
					$c = "";		
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
				<?php the_content(); the_post(); ?>

				<?php 


			    $process_logs = get_posts(array(
			    	'post_type' 		=> 'process_log',
			        'posts_per_page'    => '-1',
			        
			    	));
			    ?>
				
				<div class="company-handbook process-calendar activity-log">

			    </div>
			    
			    
			    <div class="activity-log-unsorted">
			    <?php if( $process_logs ): ?>
			    	
			    	<?php foreach( $process_logs as $process_log ): 

			    		$process = get_field('process', $process_log->ID);
			    		$process_id = $process[0];
			    		$weekday = get_the_date( "w", $process_log->ID );
			    		$year = get_the_date( "Y", $process_log->ID );
			    		$week = get_the_date( "W", $process_log->ID );
			    		$week_sunday = str_pad($week + 1, 2, '0', STR_PAD_LEFT);
			    		// Fix for new years
			    		if ($week_sunday == "53") {
			    			$week_sunday = "01";
			    		}
			    		$desc = get_field('description', $process_log->ID);
			    	?>
			    		<div class="process-star" data-weekday="<?php echo $weekday; ?>" data-yearweek="<?php if ($weekday == 0) { 

			    			echo "$year-$week_sunday";
			    		} else {
			    			echo "$year-$week";
			    		 } ?>">
			    		<i class="fa fa-star" aria-hidden="true">
			    		<span class="info">
			    			<a href="<?php echo get_edit_post_link( $process_log->ID ); ?>" class="alignright"><i class="fa fa-pencil-square-o" aria-hidden="true"></i></a>
			    			<?php echo get_the_author_meta("first_name", $process_log->post_author); ?> completed <br />
			    			<i class="fa fa-link" aria-hidden="true"></i> <a href="<?php echo get_permalink( $process_id ); ?>"><?php echo get_the_title( $process_id ); ?></a><br />
			    			<?php if ($desc) { ?>
								<div class="desc"> 
									<?php echo WPCom_Markdown::get_instance()->transform( $desc, array('id'=>false,'unslash'=>false)); ?>
								</div>
							<?php } ?>
			    			<?php if (get_field('website', $process_log->ID)) { 
			    				$website = get_field('website', $process_log->ID);
			    				foreach( $website as $p ): // variable must NOT be called $post (IMPORTANT) ?>
			    				<i class="fa fa-link" aria-hidden="true"></i> <a href="<?php echo get_edit_post_link( $p ); ?>"><?php echo get_the_title( $p ); ?></a><br />
			    				<?php endforeach;  
			    			} ?>

			    			<hr />
			    			<i class="fa fa-calendar" aria-hidden="true"></i>
			    			<?php echo get_the_date( "M j | g:ia", $process_log->ID ); ?>
			    		</span></i>
			    		</div>
			    			
			    	<?php endforeach; ?>
			    <?php endif; 

				?>
				</div>
				<div class="nav-links">				
				<div class="nav-previous alignright"><a href="<?php echo home_url("/company-handbook/"); ?>" rel="prev">Company Handbook <span class="meta-nav">→</span></a></div>			</div><!-- .nav-links -->
				

			</div>
			</div><!-- .entry-content -->
			<footer class="entry-footer">
				<?php edit_post_link( __( 'Edit', 'swell' ), '<span class="edit-link">', '</span>' ); ?>
			</footer><!-- .entry-footer -->
			</div>
			
		</article><!-- #post-## -->


		<?php } else { ?>
		<section class="error-404 not-found">
			<?php
			$featured_image = "";
			$c = "";

				$blog_page_id = get_option( 'page_for_posts' );
				$blog_page = get_post( $blog_page_id );
				if( has_post_thumbnail( $blog_page_id ) ) {
					$featured_image = wp_get_attachment_image_src( get_post_thumbnail_id( $blog_page_id ), 'swell_full_width' );
					$c = "has-background";
				}?>
				<header class="main entry-header <?php echo $c; ?>" style="<?php echo $featured_image ? 'background-image: url(' . esc_url( $featured_image[0] ) . ');' : '' ?>">
					<h1 class="entry-title"><h1 class="page-title"><?php _e( 'Login Required', 'swell' ); ?></h1>
					<span class="overlay"></span>
				</header><!-- .entry-header -->
		
		<div class="body-wrap">
		<div class="entry-content">
			<?php echo do_shortcode("[woocommerce_my_account]"); ?>			
		</div>
		</div>
		</section><!-- .error-404 -->

		<?php } ?>

	</main><!-- #main -->
</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>