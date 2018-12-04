<?php
if ( class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'markdown' ) ) :
	jetpack_require_lib( 'markdown' );
endif;
?>
<div class="col s12">
			<?php /* <div class="card">
				<div class="card-content row">
				<div class="col s12">
				 <p>Yearly activity report</p>
			 </div>
				</div>
			</div>*/ ?>
				 <?php
				 $user = wp_get_current_user();
				 $role_check = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles) + in_array( 'editor', $user->roles );
				 $partner = get_field('partner', 'user_'. get_current_user_id());
				 if ($partner and $role_check) {

					// Loop through each partner assigned to current user
					foreach ($partner as $partner_id) {

						// Load websites assigned to partner
						$arguments = array(
							'post_type' 			=> 'captcore_website',
							'posts_per_page'	=> '-1',
							'fields'         => 'ids',
							'order'						=> 'asc',
							'orderby'					=> 'title',
							'meta_query'			=> array(
								array(
									'key' => 'partner',
									'value' => '"' . $partner_id . '"',
									'compare' => 'LIKE'
								),
							)
						);

					// Loads websites
					$websites = get_posts( $arguments );

					if ( count( $websites ) == 0 ) {

						// Load websites assigned to partner
						$websites = get_posts(array(
							'post_type' 			=> 'captcore_website',
							'posts_per_page'	=> '-1',
							'fields'					=> 'ids',
							'order'						=> 'asc',
							'orderby'					=> 'title',
							'meta_query'			=> array(
									array(
										'key' => 'customer', // name of custom field
										'value' => '"' . $partner_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
										'compare' => 'LIKE'
									),
							)
						));

					}

					if( $websites ): ?>

						<h3>Account: <?php echo get_the_title($partner_id); ?> <small>(<?php echo count($websites);?> sites)</small></h3>

							<div class="website-group">
							<?php

							$pattern = '("' . implode('"|"', $websites ) . '")';

							$arguments = array(
								'post_type'      => 'captcore_processlog',
								'posts_per_page' => '-1',
								'meta_query'	=> array(
									array(
										'key'	 	=> 'website',
										'value'	  	=> $pattern,
										'compare' 	=> 'REGEXP',
									),
							));

							$process_logs = get_posts($arguments);
							$year = '';
							foreach ($process_logs as $process_log) {

								// filter only for sites within websites
								$website = get_field("website", $process_log->ID );
								//print_r($website);
								//$website = array_intersect_assoc( $website, $websites);

								$description = get_field("description", $process_log->ID );
								$description = WPCom_Markdown::get_instance()->transform(
									$description, array(
										'id'      => false,
										'unslash' => false,
									)
								);
								$process = get_field("process", $process_log->ID );

								$post_year = date( 'Y', strtotime( $process_log->post_date ) );

								if ( $year != $post_year ) {
									$currentyear = true;
									$year        = $post_year;
									if ( $year <> '' ) {
										echo '</ul>';
									}
									echo '<h3>' . $year . '</h3>';
									echo "<ul class='changelog'>";
								} ?>
								<li>
									<div class="changelog-item">
										<div class="title"><?php foreach($website as $website_id) { if ( in_array($website_id, $websites)) echo get_the_title($website_id) ." "; } ?> - <?php echo get_the_title( $process[0] ); ?></div>
										<?php
										if ( $description ) { ?>
									<div class="content show"><i class="fas fa-sticky-note"></i> <?php echo $description; ?></div><?php } ?>
									<div class="author"><i class="far fa-user"></i> <?php echo get_the_author( $process_log->ID ); ?></div>
									<div class="changelog-date"><?php echo date( 'd M', strtotime( $process_log->post_date ) ); ?></div>
									</div>
								</li>
							<?php } ?>
							</ul>
						</div>
				 <?php endif;

					}
				 } ?>

		</div>
