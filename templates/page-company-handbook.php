<?php
/**
 * @package swell
 * Template Name: Company Handbook
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( home_url( '/my-account/' ) );
	exit;
} else {
	$get_user_id   = get_current_user_id(); // get user ID
	$get_user_data = get_userdata( $get_user_id ); // get user data
	$get_roles     = $get_user_data->roles;
	if ( ! in_array( "administrator", $get_roles ) ) { // check if role name == user role
		wp_redirect( home_url( '/my-account/' ) );
		exit;
	}
}

get_header();
jetpack_require_lib( 'markdown' );

?>

<script>

jQuery(document).ready(function(){

	// Generate this week and last week.
	thisweekday = "<?php echo date( 'N' ); ?>";
	thisyearweek = "<?php echo date( 'Y-W' ); ?>";
	lastyearweek = "<?php echo date( 'Y-W', strtotime( '-7 days' ) ); ?>";
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

		<?php if ( is_user_logged_in() ) { ?>

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

			<?php
			$featured_image = '';
			$c              = '';
			if ( is_page() ) {
				if ( has_post_thumbnail() ) {
					$featured_image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'swell_full_width' );
					$c              = '';
				}
			}
			?>

			<header class="main entry-header <?php echo $c; ?>" style="<?php echo 'background-image: url(' . $featured_image[0] . ');'; ?>">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>

				<?php if ( $post->post_excerpt ) { ?>
					<hr class="short" />
				<span class="meta">
					<?php echo $post->post_excerpt; ?>
				</span>
				<?php } ?>
				<span class="overlay"></span>
			</header><!-- .entry-header -->

			<div class="body-wrap">
			<div class="entry-content">
				<?php
				the_content();
				the_post();
				?>

				<div class="company-handbook">
				<?php
				// Gets every "category" (term) in this taxonomy to get the respective posts
				$terms = get_terms( 'process_role' );

				foreach ( $terms as $term ) :
				?>

					<h3 class='process'><?php echo $term->name; ?>
					<?php
					$manager = get_field( 'manager', 'process_role_' . $term->term_id );

					if ( $manager ) {
					?>
					<div class="process-role-manager">
						<i class="fas fa-key"></i>
						<?php echo $manager['user_firstname']; ?> <?php echo $manager['user_lastname']; ?>
					</div>
					<?php } ?>
					</h3>

					<div class="process-description">
					<?php echo $term->description; ?>
					</div>
					<ul>

					<?php

					// WP_Query arguments
					$args = array(
						'post_type'      => array( 'captcore_process' ),
						'posts_per_page' => '-1',
						'taxonomy'       => 'process_role',
						'term'           => $term->slug,
						'order'          => 'ASC',
						'orderby'        => 'title',
					);

					// The Query
					$posts = new WP_Query( $args );

					if ( $posts->have_posts() ) :
						while ( $posts->have_posts() ) :
							$posts->the_post();
							$field  = get_field_object( 'repeat' );
							$value  = get_field( 'repeat' );
							$repeat = $field['choices'][ $value ];
							?>
						 <li>
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							<small>
								<i class="far fa-clock"></i> <?php the_field( 'time_estimate' ); ?>
								<i class="fas fa-redo"></i> <?php echo $repeat; ?>
							<?php
							if ( get_field( 'repeat_quantity' ) and get_field( 'repeat_quantity' ) > 1 ) {
?>
<i class="fas fa-retweet"></i> <?php the_field( 'repeat_quantity' ); ?> times<?php } ?>
							</small>
						</li>
						<?php
						endwhile;
					endif;
					wp_reset_postdata();

					echo '</ul>';

				endforeach;
				wp_reset_postdata();

				$process_logs = get_posts(
					array(
						'post_type'      => 'captcore_processlog',
						'posts_per_page' => '-1',
						'date_query'     => array(
							'column' => 'post_date',
							'after'  => '- 14 days',
						),

					)
				);
				?>

				<h3>Activity</h3>

				<div class="process-calendar activity-log">

				</div>

				<div class="activity-log-unsorted">
				<?php if ( $process_logs ) : ?>

					<?php
					foreach ( $process_logs as $process_log ) :

						$process     = get_field( 'process', $process_log->ID );
						$process_id  = $process[0];
						$weekday     = get_the_date( 'w', $process_log->ID );
						$year        = get_the_date( 'Y', $process_log->ID );
						$week        = get_the_date( 'W', $process_log->ID );
						$week_sunday = str_pad( $week + 1, 2, '0', STR_PAD_LEFT );
						// Fix for new years
						if ( $week_sunday == '53' ) {
							$week_sunday = '01';
						}
						if ( $weekday == 0 ) {
							$yearweek = "$year-$week_sunday";
						} else {
							$yearweek = "$year-$week";
						}
						$desc = get_field( 'description', $process_log->ID );
					?>
						<div class="process-star" data-weekday="<?php echo $weekday; ?>" data-yearweek="<?php echo $yearweek; ?>">
							<div class="tooltip">
							<i class="fas fa-star"></i>
						<span class="info">
							<a href="<?php echo get_edit_post_link( $process_log->ID ); ?>" class="alignright"><i class="fas fa-edit"></i></a>
							<?php echo get_the_author_meta( 'first_name', $process_log->post_author ); ?> completed <br />
							<i class="fas fa-link"></i> <a href="<?php echo get_permalink( $process_id ); ?>"><?php echo get_the_title( $process_id ); ?></a><br />
							<?php if ( $desc ) { ?>
								<div class="desc">
									<?php
									echo WPCom_Markdown::get_instance()->transform(
										$desc, array(
											'id'      => false,
											'unslash' => false,
										)
									);
									?>
								</div>
							<?php } ?>
							<?php
							if ( get_field( 'website', $process_log->ID ) ) {
								$website = get_field( 'website', $process_log->ID );
								foreach ( $website as $p ) : // variable must NOT be called $post (IMPORTANT)
								?>
								<i class="fas fa-link"></i> <a href="<?php echo get_edit_post_link( $p ); ?>"><?php echo get_the_title( $p ); ?></a><br />
								<?php
								endforeach;
							}
							?>
							<hr />
							<i class="fas fa-calendar-alt"></i>
							<?php echo get_the_date( 'M j | g:ia', $process_log->ID ); ?>
						</span></div>
						</div>

					<?php endforeach; ?>
				<?php endif; ?>
				</div>
				<a href="<?php echo home_url( '/company-handbook/activities/' ); ?>" class="alignright">View all activities</a>
				<div class="clear"></div>

				<h3>My Started Processes</h3>
				<?php

				 wp_reset_postdata();

				$process_logs = get_posts(
					array(
						'post_type'      => 'captcore_processlog',
						'posts_per_page' => '-1',
						'author'         => get_current_user_id(),
						'meta_key'       => 'status',
						'meta_value'     => 'started',

					)
				);

				if ( $process_logs ) :
				?>

					<div class="started-processes">

					<?php
					foreach ( $process_logs as $process_log ) :

						$process    = get_field( 'process', $process_log->ID );
						$process_id = $process[0];
						$weekday    = get_the_date( 'w', $process_log->ID );
						$year       = get_the_date( 'Y', $process_log->ID );
						$week       = get_the_date( 'W', $process_log->ID );

						$week_sunday = str_pad( $week + 1, 2, '0', STR_PAD_LEFT );
						// Fix for new years
						if ( $week_sunday == '53' ) {
							$week_sunday = '01';
						}
						if ( $weekday == 0 ) {
							$yearweek = "$year-$week_sunday";
						} else {
							$yearweek = "$year-$week";
						}
						$desc = get_field( 'description', $process_log->ID );
					?>
						<div class="process-star" data-post-id="<?php echo $process_log->ID; ?>" data-weekday="<?php echo $weekday; ?>" data-yearweek="<?php echo $yearweek; ?>">
						<span class="info">
							<i class="fas fa-calendar-alt"></i> <?php echo get_the_date( 'M j | g:ia', $process_log->ID ); ?>
							<a href="#" class="process-log-completed alignright"><i class="fas fa-check"></i></a>
							<a href="<?php echo get_edit_post_link( $process_log->ID ); ?>" class="alignright"><i class="fas fa-edit"></i></a>
							<i class="fas fa-link"></i> <a href="<?php echo get_permalink( $process_id ); ?>"><?php echo get_the_title( $process_id ); ?></a>
							<?php if ( $desc ) { ?>
								<div class="desc">
									<?php
									echo WPCom_Markdown::get_instance()->transform(
										$desc, array(
											'id'      => false,
											'unslash' => false,
										)
									);
									?>
								</div>
							<?php } ?>
							<?php
							if ( get_field( 'website', $process_log->ID ) ) {
								$website = get_field( 'website', $process_log->ID );
								foreach ( $website as $p ) : // variable must NOT be called $post (IMPORTANT)
								?>
								<i class="fas fa-link"></i> <a href="<?php echo get_edit_post_link( $p ); ?>"><?php echo get_the_title( $p ); ?></a>
								<?php
								endforeach;
							}
							?>
						</span>
						</div>

					<?php endforeach; ?>
					</div>
				<?php endif; ?>

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
			$featured_image = '';
			$c              = '';

				$blog_page_id = get_option( 'page_for_posts' );
				$blog_page    = get_post( $blog_page_id );
			if ( has_post_thumbnail( $blog_page_id ) ) {
				$featured_image = wp_get_attachment_image_src( get_post_thumbnail_id( $blog_page_id ), 'swell_full_width' );
				$c              = 'has-background';
			}
				?>
				<header class="main entry-header <?php echo $c; ?>" style="<?php echo $featured_image ? 'background-image: url(' . esc_url( $featured_image[0] ) . ');' : ''; ?>">
					<h1 class="entry-title"><h1 class="page-title"><?php _e( 'Login Required', 'swell' ); ?></h1>
					<span class="overlay"></span>
				</header><!-- .entry-header -->

		<div class="body-wrap">
		<div class="entry-content">
			<?php echo do_shortcode( '[woocommerce_my_account]' ); ?>
		</div>
		</div>
		</section><!-- .error-404 -->

		<?php } ?>

	</main><!-- #main -->
</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
