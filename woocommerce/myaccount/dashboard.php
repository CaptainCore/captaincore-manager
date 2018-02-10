<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/my-account-dashboard.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @author      WooThemes
 * @package     WooCommerce/Templates
 * @version     2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function cmp($a, $b) {
    return strcmp($a->name, $b->name);
}


// Custom filesize function
function anchorhost_human_filesize($size, $precision = 2) {
    $units = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
    $step = 1024;
    $i = 0;
    while (($size / $step) > 0.9) {
        $size = $size / $step;
        $i++;
    }
    return round($size, $precision).$units[$i];
}

?>

<style>
.toggle-buttons a {
	z-index: 9999;
  position: relative;
  color: #000;
  opacity: .4;
  font-weight: normal;
}

.toggle-buttons a.active {
	color:#27c3f3;
	opacity: 1;
	font-weight: bold;
}
.toggle-buttons a:active {
	outline:none;
}
/* entire container, keeps perspective */
.flip-container {
	-webkit-perspective: 1000;
	-moz-perspective: 1000;
	-ms-perspective: 1000;
	perspective: 1000;
	-ms-transform: perspective(1000px);
	-moz-transform: perspective(1000px);
	-moz-transform-style: preserve-3d;
	-ms-transform-style: preserve-3d;
}
	/* flip the pane when clicked */
	.flip-container.toggle .flipper {
		-webkit-transform: rotateY(180deg);
	    -moz-transform: rotateY(180deg);
	    -o-transform: rotateY(180deg);
	    transform: rotateY(180deg);
	}

/* flip speed goes here */
.flipper {
	-webkit-transition: 0.6s;
	-webkit-transform-style: preserve-3d;
	-ms-transition: 0.6s;

	-moz-transition: 0.6s;
	-moz-transform: perspective(1000px);
	-moz-transform-style: preserve-3d;
	-ms-transform-style: preserve-3d;

	transition: 0.6s;
	transform-style: preserve-3d;

	position: relative;
}

.card hr {
	margin: 4px 0;
	background-color: #eaeaea;
}

p.small {
	font-size: 14px;
	margin: 0px;
	padding: 0px;
}

.partner-group .card .btn-floating.btn-large {
	position: absolute;
	bottom: 20px;
	right: 20px;
}

/* hide back of pane during swap */
.production, .staging {
	-webkit-backface-visibility: hidden;
	-moz-backface-visibility: hidden;
	-ms-backface-visibility: hidden;
	backface-visibility: hidden;

    -webkit-transition: 0.6s;
    -webkit-transform-style: preserve-3d;
    -webkit-transform: rotateY(0deg);

    -moz-transition: 0.6s;
    -moz-transform-style: preserve-3d;
    -moz-transform: rotateY(0deg);

    -o-transition: 0.6s;
    -o-transform-style: preserve-3d;
    -o-transform: rotateY(0deg);

    -ms-transition: 0.6s;
    -ms-transform-style: preserve-3d;
    -ms-transform: rotateY(0deg);

    transition: 0.6s;
    transform-style: preserve-3d;
    transform: rotateY(0deg);
}

 .staging {
 	position: absolute;
 	top: 0px;
 	left: 0px;
  width: 100%;
}

.staging p.label {
	position: absolute;
	font-weight: bold;
  left: 10px;
}

/* front pane, placed above back */
.production {
	z-index: 2;
	/* for firefox 31 */
	-webkit-transform: rotateY(0deg);
    -moz-transform: rotateY(0deg);
    -o-transform: rotateY(0deg);
    -ms-transform: rotateY(0deg);
    transform: rotateY(0deg);
}

/* back, initially hidden pane */
.staging {
	-webkit-transform: rotateY(180deg);
    -moz-transform: rotateY(180deg);
    -o-transform: rotateY(180deg);
    transform: rotateY(180deg);
}
.toggle-buttons {
	position: absolute;
	z-index: 1;
	right: 10px;
	top: 0px;
	font-size: 0.8em;
	font-weight: bold;
}

.card-reveal a.btn {
    font-size: .75em;
}

</style>
<script type="text/javascript">

	ajaxurl = "/wp-admin/admin-ajax.php";

	function sort_li(a, b){
	    var va = jQuery(a).data('id').toString().charCodeAt(0);
	    var vb = jQuery(b).data('id').toString().charCodeAt(0);
	    if (va < 'a'.charCodeAt(0)) va += 100; // Add weight if it's a number
	    if (vb < 'a'.charCodeAt(0)) vb += 100; // Add weight if it's a number
	    return vb < va ? 1 : -1;
	}

	jQuery(document).ready(function() {
		jQuery('a.staging-toggle').click(function() {
			jQuery(this).parents('.flip-container').addClass('toggle');
			return false;
		});
		jQuery('a.production-toggle').click(function() {
			jQuery(this).parents('.flip-container').removeClass('toggle');
			return false;
		});
		jQuery(".partner-group").each(function() {
			jQuery(this).children("div.partner").sort(sort_li).appendTo( jQuery(this) );
		});

		jQuery('.modal').modal({
      dismissible: true, // Modal can be dismissed by clicking outside of the modal
      opacity: .5, // Opacity of modal background
      inDuration: 300, // Transition in duration
      outDuration: 200, // Transition out duration
      startingTop: '4%', // Starting top style attribute
      endingTop: '10%', // Ending top style attribute
		});

		jQuery(".modal-content input#submit").click(function(e){

	  	e.preventDefault();

			//
			modal_form = jQuery(this).parents('.modal.open');

			jQuery('.modal.open .modal-content .row').hide();
			jQuery('.modal.open .modal-content .progress').removeClass('hide');

			email_address = modal_form.find('input#email').val();
	  	var data = {
	  		'action': 'anchor_install',
	  		'post_id': modal_form.data('id'),
	      'command': 'snapshot',
				'value'	: email_address
	  	};

			if ( isEmail(email_address) ) {
				// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
				jQuery.post(ajaxurl, data, function(response) {
				  Materialize.toast('Backup snapshot in process. Will email once completed.', 4000);
					modal_form.modal('close');
				});
			} else {
				modal_form.find('.results').html("Please enter a valid email address.");
				jQuery('.modal.open .modal-content .row').show();
				jQuery('.modal.open .modal-content .progress').addClass('hide');
			}

	  });

		jQuery(".card .card-reveal a.redeploy").click(function(e){

			confirm_redeploy = confirm("Redeploy?");

			if(confirm_redeploy) {

				var post_id = jQuery(this).data('post-id');

			  var data = {
			  	'action': 'anchor_install',
			  	'post_id': post_id,
					'command': 'new'
			  };

			  // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			  jQuery.post(ajaxurl, data, function(response) {
					Materialize.toast('Redeploy in progress.', 4000);
			  });

			}

		  e.preventDefault();

		});

		jQuery(".card .card-reveal a.kinsta-deploy-to-staging").click(function(e){

			confirm_deploy = confirm("Kinsta staging site will be overridden. Proceed?");

			if(confirm_deploy) {

				var post_id = jQuery(this).data('post-id');

			  var data = {
			  	'action': 'anchor_install',
			  	'post_id': post_id,
					'command': 'kinsta-deploy-to-staging'
			  };

			  // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			  jQuery.post(ajaxurl, data, function(response) {
			  	Materialize.toast('Kinsta staging site being generated.', 4000);
			  });

			}

		  e.preventDefault();

		});

			jQuery('.right input').click(function() {

				if ( jQuery(this).prop('checked') ) {

					jQuery('.card').show();
					jQuery('.toggle-buttons').show();
					jQuery('.logins.col.s12.m6').show();
					jQuery('.usage-stats.col.s12.m6').css('width',"50%");
					jQuery('a.btn-floating.btn-large.blue.activator').show();
					jQuery('.card.partner.production .usage').each(function() {
						jQuery(this).parents(".card.partner.production").show();
					});
					num = jQuery(".card:visible:not('.staging')").length;
					jQuery('.woocommerce-MyAccount-content > h3').text("Listing " + num + " sites");

				} else {

					jQuery('.card').hide();
					jQuery('.toggle-buttons').hide();
					jQuery('.logins.col.s12.m6').hide();
					jQuery('.usage-stats.col.s12.m6').css('width',"100%");
					jQuery('a.btn-floating.btn-large.blue.activator').hide();
					jQuery('.card.partner.production .usage.over').each(function() {
					  jQuery(this).parents(".card.partner.production").show();
					});

					num = jQuery(".card:visible:not('.staging')").length;
					jQuery('.woocommerce-MyAccount-content > h3').text("Listing " + num + " sites");

				}
			});


	});

	function isEmail(email) {
  	var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
  	return regex.test(email);
	}
</script>

<p>
	<?php
		echo sprintf( esc_attr__( 'Hello %s%s%s (not %2$s? %sSign out%s)', 'woocommerce' ), '<strong>', esc_html( $current_user->display_name ), '</strong>', '<a href="' . esc_url( wc_get_endpoint_url( 'customer-logout', '', wc_get_page_permalink( 'myaccount' ) ) ) . '">', '</a>' );
	?>
</p>
<?php
$user = wp_get_current_user();
$role_check = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles) + in_array( 'editor', $user->roles );
$partner = get_field('partner', 'user_'. get_current_user_id());
if ($partner and $role_check) {

	// Loop through each partner assigned to current user
	foreach ($partner as $partner_id) {

		if ( in_array( 'administrator', $user->roles) ) {

			// Load all websites for administrators
			$websites = get_posts(array(
				'post_type' 			=> 'website',
				'posts_per_page'	=> '-1',
				'order'						=> 'asc',
				'orderby'					=> 'title',
				'meta_query'			=> array(
						array(
							'key'	  	=> 'status',
							'value'	  	=> 'closed',
							'compare' 	=> '!=',
						),
				)
			));

		} else {

			// Load websites assigned to partner
			$websites = get_posts(array(
				'post_type' 			=> 'website',
	      'posts_per_page'	=> '-1',
				'order'						=> 'asc',
				'orderby'					=> 'title',
	      'meta_query'			=> array(
		      'relation'			=> 'AND',
						array(
							'key' => 'partner', // name of custom field
							'value' => '"' . $partner_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
							'compare' => 'LIKE'
						),
						array(
							'key'	  	=> 'status',
							'value'	  	=> 'closed',
							'compare' 	=> '!=',
						),
				)
			));

		}

	if ( count( $websites ) == 0 ) {

		// Load websites assigned to partner
		$websites = get_posts(array(
			'post_type' 			=> 'website',
			'posts_per_page'	=> '-1',
			'order'						=> 'asc',
			'orderby'					=> 'title',
			'meta_query'			=> array(
				'relation'			=> 'AND',
					array(
						'key' => 'customer', // name of custom field
						'value' => '"' . $partner_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE'
					),
					array(
						'key'	  	=> 'status',
						'value'	  	=> 'closed',
						'compare' 	=> '!=',
					),
			)
		));

	}

	if( $websites ): ?>
	<?php if ( in_array( 'administrator', $user->roles) ) { ?>
		<label class="right">
			<input type="checkbox" checked="checked">
			All sites
		</label>
		<h3>Listing <?php echo count($websites);?> sites</h3>
	<?php } else { ?>
		<h3>Account: <?php echo get_the_title($partner_id); ?> <small>(<?php echo count($websites);?> sites)</small></h3>
	<?php } ?>
			<div class="partner-group">
			<?php foreach( $websites as $website ):

				$customer_id = get_field('customer', $website->ID);
				$hosting_plan = get_field('hosting_plan', $customer_id[0]);
				$addons = get_field('addons', $customer_id[0]);
				$storage = get_field('storage', $customer_id[0]);
				$views = get_field('views', $customer_id[0]);
				$website_storage = get_field('storage', $website->ID);
				$website_views = get_field('views', $website->ID);

				if ($hosting_plan == "basic") {
					$views_plan_limit = "100000";
				}
				if ($hosting_plan == "standard") {
					$views_plan_limit = "500000";
				}
				if ($hosting_plan == "professional") {
					$views_plan_limit = "1000000";
				}
				if ($hosting_plan == "business") {
					$views_plan_limit = "2000000";
				}
				if (isset($views)) {
					$views_percent = round( $views / $views_plan_limit * 100, 0 );
				}

				$storage_gbs = round($storage / 1024 / 1024 / 1024, 1);
				$storage_cap = "10";
				if ($addons) {
					foreach($addons as $item) {
						// Evaluate if contains word storage
						if (stripos($item["name"], "storage") !== FALSE) {
							// Found storage addon, now extract number and add to cap.
							$extracted_gbs = filter_var($item["name"], FILTER_SANITIZE_NUMBER_INT);
							$storage_cap = $storage_cap + $extracted_gbs;
						}
					}
				}

				$storage_percent = round($storage_gbs / $storage_cap * 100, 0);

				$production_address = get_field('address', $website->ID);
				$staging_address = get_field('address_staging', $website->ID);
				$server = get_field('server', $website->ID);
				if ($server and $server[0]) {
					$provider = get_field('provider', $server[0]);

					// vars
					$provider_object = get_field_object('provider', $server[0]);
					$provider_label = $provider_object['choices'][ $provider ];

					$server_name = get_field('name', $server[0]);
					$server_address = get_field('address', $server[0]);
				}	?>
				<?php if ($views != $website_views or $storage != $website_storage) { ?>

					<!-- Modal Structure -->
					<div id="view_usage_breakdown_<?php echo $customer_id[0]; ?>" class="modal modal-fixed-footer">
						<div class="modal-content">
							<h4>Usage Breakdown for <?php echo get_the_title($customer_id[0]); ?></h4>
							<?php

								/*
								*  Query posts for a relationship value.
								*  This method uses the meta_query LIKE to match the string "123" to the database value a:1:{i:0;s:3:"123";} (serialized array)
								*/

								$websites_for_customer = get_posts(array(
									'post_type' => 'website',
					        'posts_per_page'         => '-1',
					        'meta_query' => array(
										'relation'		=> 'AND',
				 						array(
				 	    				'key' => 'status', // name of custom field
				 	    				'value' => 'active', // matches exaclty "123", not just 123. This prevents a match for "1234"
				 	    				'compare' => '='
				 	    			),
										array(
											'key' => 'customer', // name of custom field
											'value' => '"' . $customer_id[0] . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
											'compare' => 'LIKE'
										)
									)
								));
								?>
								<?php if( $websites_for_customer ): ?>
									<ul>
									<?php foreach( $websites_for_customer as $website_for_customer ):
										$website_for_customer_storage = get_field('storage', $website_for_customer->ID);
										$website_for_customer_views = get_field('views', $website_for_customer->ID);
											?>
										<li>
												<?php echo get_the_title( $website_for_customer->ID ); ?> -
												<?php if($website_for_customer_storage) { echo '<i class="fas fa-hdd"></i> '.round($website_for_customer_storage / 1024 / 1024 / 1024, 1). "GB"; } ?>
												<?php if($website_for_customer_views) { echo '<i class="fas fa-eye"></i> '. number_format($website_for_customer_views). " views"; } ?>
										</li>
									<?php endforeach; ?>
									</ul>
									<?php if ($storage_gbs != 0) { ?>
									<div class="usage<?php if ($storage_percent > 100) { echo " over"; } ?>">
											<i class="fas fa-hdd"></i>
											<?php echo $storage_percent; ?>% storage
											<strong><?php echo $storage_gbs; ?>GB/<?php echo $storage_cap; ?>GB</strong>
									</div>
									<?php } ?>
									<?php if ($views != 0) { ?>
								 	<div class="usage<?php if ($views_percent > 100) { echo " over"; } ?>">
										<i class="fas fa-eye"></i>
											<?php echo $views_percent; ?>% traffic
											<strong><?php echo number_format($views); ?></strong> <small>Yearly Estimate</small>
									</div>
									<?php } ?>
								<?php endif; ?>
						</div>
						<div class="modal-footer">
							<a href="#!" class="modal-action modal-close waves-effect btn-flat ">Close</a>
						</div>
					</div>

				<?php } ?>
			<div class="flip-container">
			<div class="flipper">

	<?php if (get_field('address', $website->ID)) { ?>
		<div class="card partner production" data-id="<?php echo get_the_title( $website->ID ); ?>">

	<div class="card-content">
		<div class="row">
		<?php if (get_field('address_staging', $website->ID)) { ?>
		<div class="toggle-buttons">
			<a href="#" class="production-toggle active">Production</a> | <a href="#" class="staging-toggle">Staging</a>
		</div>
		<?php } ?>

		<span class="card-title grey-text text-darken-4 "><a href="http://<?php echo get_the_title( $website->ID ); ?>" target="_blank"><?php echo get_the_title( $website->ID ); ?></a></span>
			<div class="logins col s12 m6">
				Address: <?php the_field('address', $website->ID); ?><br />
				Username: <?php the_field('username', $website->ID); ?><br />
				Password: <?php the_field('password', $website->ID); ?><br />
				Protocol: <?php the_field('protocol', $website->ID); ?><br />
				Port: <?php the_field('port', $website->ID); ?>
			</div>
			<div class="usage-stats col s12 m6">
				<?php if ($provider) { ?>
			 	<div class="usage">
					<i class="fas fa-server"></i> <?php echo $provider_label; ?> <strong><?php //echo $server_address ?></strong> <small><?php //echo $server_name ?></small>
				</div>
				<?php } ?>
				<?php if ($storage_gbs != 0) { ?>
				<div class="usage<?php if ($storage_percent > 100) { echo " over"; } ?>">
						<i class="fas fa-hdd"></i>
						<?php echo $storage_percent; ?>% storage
						<strong><?php echo $storage_gbs; ?>GB/<?php echo $storage_cap; ?>GB</strong>
				</div>
				<?php } ?>
				<?php if ($views != 0) { ?>
			 	<div class="usage<?php if ($views_percent > 100) { echo " over"; } ?>">
					<i class="fas fa-eye"></i>
						<?php echo $views_percent; ?>% traffic
						<strong><?php echo number_format($views); ?></strong> <small>Yearly Estimate</small>
				</div>
				<?php } ?>
				<?php if ($views != $website_views or $storage != $website_storage) { ?>
					<p><small>Plans covers multiple sites, <a href="#view_usage_breakdown_<?php echo $customer_id[0]; ?>" class="modal-trigger">view usage breakdown</a>.</small></p>
				<?php } ?>
			</div>
			<div class="col s12">
				<?php if ( strpos($production_address, ".kinsta.com") and get_field('database_username', $website->ID) ) { ?>
					<hr />
				<p class="small">
					<a href="https://mysqleditor-<?php the_field('database_username', $website->ID); ?>.kinsta.com/" target="_blank">https://mysqleditor-<?php the_field('database_username', $website->ID); ?>.kinsta.com/</a><br />
					Database username: <?php the_field('database_username', $website->ID); ?> <br />Database password: <?php the_field('database_password', $website->ID); ?><br />
				</p>
				<?php } ?>
				<?php if (strpos($production_address, ".kinsta.com") ) { ?>
					<hr /><small>ssh <?php the_field('username', $website->ID); ?>@<?php echo $production_address; ?> -p <?php the_field('port', $website->ID); ?></small>
				<?php } ?>
			</div>

			 <a class="btn-floating btn-large blue activator">
				 <i class="large material-icons">menu</i>
			 </a>
  </div>
	</div>
	<div class="card-reveal">
		<span class="card-title grey-text text-darken-4"><?php echo get_the_title( $website->ID ); ?><i class="material-icons right">close</i></span>
		<p></p>
		<div class="row">
      <div class="input-field col s12">
				<a href="#snapshot<?php echo $website->ID; ?>" class="waves-effect waves-light modal-trigger large"><i class="material-icons left">cloud</i>Download backup snapshot</a> <br />
				<a class="waves-effect waves-light large redeploy" data-post-id="<?php echo $website->ID; ?>"><i class="material-icons left">loop</i>Redeploy users/plugins</a> <br />
				<a href="#quicksave<?php echo $website->ID; ?>" class="waves-effect waves-light modal-trigger large"><i class="material-icons left">chrome_reader_mode</i>Quicksaves (Plugins & Themes)</a><br />
				<?php if( defined('ANCHOR_DEV_MODE') ) { ?>
					<!-- <a href="#install-premium-plugin<?php echo $website->ID; ?>" class="waves-effect waves-light modal-trigger large"><i class="material-icons left">add</i>Install premium plugin</a> <br />-->
				<?php } ?>
				<?php
				if( strpos($production_address, ".kinsta.com") ):  ?>
					<a class="waves-effect waves-light large kinsta-deploy-to-staging" data-post-id="<?php echo $website->ID; ?>"><i class="material-icons left">chrome_reader_mode</i>Kinsta: Push Production to Staging</a>
				<?php endif ?>

			</div>
		</div>
	</div>
</div>

	<?php if (get_field('address_staging', $website->ID)) { ?>
	<div class="card partner staging" data-id="<?php echo get_the_title( $website->ID ); ?>">

	<div class="card-content">
		<div class="toggle-buttons">
			<a href="#" class="production-toggle">Production</a> | <a href="#" class="staging-toggle active">Staging</a>
		</div>
		<span class="card-title activator grey-text text-darken-4"><?php if ($provider == "kinsta") { ?>
		<a href="http://staging-<?php the_field('install_staging', $website->ID); ?>.kinsta.com" target="_blank">staging-<?php the_field('install_staging', $website->ID); ?>.kinsta.com</a>
		<?php } else { ?>
		<a href="http://<?php the_field('install_staging', $website->ID); ?>.staging.wpengine.com" target="_blank"><?php the_field('install_staging', $website->ID); ?>.staging.wpengine.com</a>
		<?php } ?></span>
			<div class="logins">
				Address: <?php the_field('address_staging', $website->ID); ?><br />
				Username: <?php the_field('username_staging', $website->ID); ?><br />
				Password: <?php the_field('password_staging', $website->ID); ?><br />
				Protocol: <?php the_field('protocol_staging', $website->ID); ?><br />
				Port: <?php the_field('port_staging', $website->ID); ?>
				<?php if (strpos($staging_address, ".kinsta.com") ) { ?>
					<br /><small>ssh <?php the_field('username_staging', $website->ID); ?>@<?php echo $staging_address; ?> -p <?php the_field('port_staging', $website->ID); ?></small>
				<?php } ?>
				</div>
			</div>
	</div>

 <?php
$provider = "";
} ?>

	<?php } else { ?>
		<div class="card">
    <div class="card-content">
      <span class="card-title grey-text text-darken-4"><?php echo get_the_title( $website->ID ); ?> - Part of a multisite network</span>
    </div>
  </div>
	<?php $provider = "";
} ?>

	</div> <!-- end .flipper -->
</div> <!-- end .flip-container -->

<div id="snapshot<?php echo $website->ID; ?>" class="modal" data-id="<?php echo $website->ID; ?>">
	<div class="modal-content">

		<h4>Download Snapshot <small>(<?php echo get_the_title( $website->ID ); ?>)</small><a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>
		<div class="progress hide">
				<div class="indeterminate"></div>
		</div>
		<div class="row">

      <div class="input-field col s12">
				<label for="email">Email Address</label><br />
        <input id="email" type="email" class="validate">
      </div>
    </div>
		<div class="row">
      <div class="input-field col s12">
				<span class="results red-text text-darken-4"></span>
			</div>
		</div>
		<div class="row">
      <div class="input-field col s12">

        <input id="submit" value="Download" type="submit">
      </div>
    </div>
	</div>
</div>

<div id="quicksave<?php echo $website->ID; ?>" class="modal bottom-sheet quicksaves" data-id="<?php echo $website->ID; ?>">
	<div class="modal-content">

		<h4>Quicksaves <small>(<?php echo get_the_title( $website->ID ); ?>)</small><a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>
		<div class="progress hide">
				<div class="indeterminate"></div>
		</div>
		<div class="row">
				<?php
				$quicksaves_for_website = get_posts(array(
					'post_type' => 'cc_quicksave',
					'posts_per_page' => '-1',
					'meta_query' => array(
						array(
							'key' => 'website', // name of custom field
							'value' => '"' . $website->ID . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
							'compare' => 'LIKE'
						)
					)
				));
				if ($quicksaves_for_website) { ?>
				<ul class="collapsible" data-collapsible="accordion">
				<?php

				foreach( $quicksaves_for_website as $quicksave ) {

					$timestamp = get_the_time( "M jS Y g:ia", $quicksave->ID);
					$plugins = json_decode(get_field( "plugins", $quicksave->ID));
					$themes = json_decode(get_field( "themes", $quicksave->ID));
					//usort($plugins, "cmp");

					?>
					<li>
				    <div class="collapsible-header">
				      <span class="material-icons">settings_backup_restore</span> <?php echo $timestamp; ?>
							<span class="badge">WordPress <?php the_field("core", $quicksave->ID); ?> - <?php echo count($plugins); ?> plugins - <?php echo count($themes); ?> themes</span>
				    </div>
				    <div class="collapsible-body">
							<table class="bordered" id="plugins_<?php echo $website->ID; ?>">
	              <thead>
	                <tr>
	                    <th>Plugin</th>
	                    <th>Version</th>
											<th>Status</th>
	                </tr>
	              </thead>
	              <tbody>
									<?php foreach( $plugins as $plugin ) { ?>
	                <tr>
	                  <td><?php if ($plugin->title) { echo $plugin->title; } else { echo $plugin->name; } ?></td>
	                  <td><?php echo $plugin->version; ?></td>
										<td><?php echo $plugin->status; ?></td>
	                </tr>
									<?php } ?>
	              </tbody>
	            </table>
							<table class="bordered" id="themes_<?php echo $website->ID; ?>">
	              <thead>
	                <tr>
	                    <th>Theme</th>
	                    <th>Version</th>
											<th>Status</th>
	                </tr>
	              </thead>
	              <tbody>
									<?php foreach( $themes as $theme ) { ?>
	                <tr>
	                  <td><?php if ($theme->title) { echo $theme->title; } else { echo $theme->name; } ?></td>
	                  <td><?php echo $theme->version; ?></td>
										<td><?php echo $theme->status; ?></td>
	                </tr>
									<?php } ?>
	              </tbody>
	            </table>
						</div>
				  </li><?php } ?>
			</ul>
		<?php } ?>

    </div>

	</div>
</div>

<div id="install-premium-plugin<?php echo $website->ID; ?>" class="modal" data-id="<?php echo $website->ID; ?>">
	<div class="modal-content">

		<h4>Install Premium Plugin <small><?php echo get_the_title( $website->ID ); ?></small><a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>

		<div class="row">
      <div class="input-field col s12">
        <input id="submit" value="Download" type="submit">
      </div>
    </div>

	</div>
</div>

<?php endforeach; ?>
		</div>
<?php endif;
	// Skips looping through partners if logged in as an Administrator
	if ( in_array( 'administrator', $user->roles) ) { break; }
 }
} ?>
<p>
	<?php
		echo sprintf( esc_attr__( 'From your account dashboard you can view your %1$srecent orders%2$s, manage your %3$sshipping and billing addresses%2$s and %4$sedit your password and account details%2$s.', 'woocommerce' ), '<a href="' . esc_url( wc_get_endpoint_url( 'orders' ) ) . '">', '</a>', '<a href="' . esc_url( wc_get_endpoint_url( 'edit-address' ) ) . '">', '<a href="' . esc_url( wc_get_endpoint_url( 'edit-account' ) ) . '">' );
	?>
</p>

<?php
	/**
	 * My Account dashboard.
	 *
	 * @since 2.6.0
	 */
	do_action( 'woocommerce_account_dashboard' );

	/**
	 * Deprecated woocommerce_before_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_before_my_account' );

	/**
	 * Deprecated woocommerce_after_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_after_my_account' );
