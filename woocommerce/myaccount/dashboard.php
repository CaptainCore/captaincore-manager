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

?>

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
		jQuery(".website-group").each(function() {
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

		jQuery('.modal.quicksaves').modal({
			ready: function(modal, trigger) { // Callback for Modal open. Modal and trigger parameters available.
				quicksave_highlight_changed( modal );
			}
		});

		jQuery('.view_quicksave_changes').click(function(e) {
			e.preventDefault();
			jQuery(this).hide();
			quicksave = jQuery(this).parents('.quicksave');
			jQuery(quicksave).find(".git_status").html( '<p></p><div class="preloader-wrapper small active"><div class="spinner-layer spinner-blue-only"><div class="circle-clipper left"><div class="circle"></div></div><div class="gap-patch"><div class="circle"></div></div><div class="circle-clipper right"><div class="circle"></div></div></div></div><p></p>' );

			var data = {
	  		'action': 'captaincore_install',
	  		'post_id': quicksave.data('id'),
	      'command': 'view_quicksave_changes',
				'value'	: quicksave.data("git_commit")
	  	};

			jQuery.post(ajaxurl, data, function(response) {
				jQuery(quicksave).find(".git_status").html( "<pre>" + response + "</pre>" );
			});

		});

		jQuery('.rollback').click(function(e) {
			e.preventDefault();

			quicksave = jQuery(this).parents('.quicksave');
			quicksave_date = jQuery(quicksave).find('span.timestamp').text();
			plugin = jQuery(this).data("plugin-name");
			theme = jQuery(this).data("theme-name");

			if ( theme ) {
				addon_type = "theme";
				addon_name = theme;
			}
			if ( plugin ) {
				addon_type = "plugin";
				addon_name = plugin;
			}

			confirm_rollback = confirm("Rollback "+ addon_type + " " + addon_name +" to version as of " + quicksave_date);

			if(confirm_rollback) {

				jQuery(quicksave).find(".git_status").html( '<p></p><div class="preloader-wrapper small active"><div class="spinner-layer spinner-blue-only"><div class="circle-clipper left"><div class="circle"></div></div><div class="gap-patch"><div class="circle"></div></div><div class="circle-clipper right"><div class="circle"></div></div></div></div><p></p>' );

				var data = {
		  		'action': 'captaincore_install',
		  		'post_id': quicksave.data('id'),
		      'command': 'rollback',
					'value'	: addon_name,
					'addon_type': addon_type,
		  	};

				jQuery.post(ajaxurl, data, function(response) {
					jQuery(quicksave).find(".git_status").html( "<pre>" + response + "</pre>" );
				});

			}

		});

		jQuery(".modal-content input#submit").click(function(e){

	  	e.preventDefault();

			modal_form = jQuery(this).parents('.modal.open');

			jQuery('.modal.open .modal-content .row').hide();
			jQuery('.modal.open .modal-content .progress').removeClass('hide');

			email_address = modal_form.find('input#email').val();
	  	var data = {
	  		'action': 'captaincore_install',
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
			  	'action': 'captaincore_install',
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

		jQuery(".card .card-reveal a.production-to-staging").click(function(e){

			confirm_deploy = confirm("Staging site will be overridden. Proceed?");

			if(confirm_deploy) {

				var post_id = jQuery(this).data('post-id');

			  var data = {
			  	'action': 'captaincore_install',
			  	'post_id': post_id,
					'command': 'production-to-staging'
			  };

			  // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			  jQuery.post(ajaxurl, data, function(response) {
			  	Materialize.toast('Staging site being generated.', 4000);
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

	function quicksave_highlight_changed( modal ) {
		jQuery('li.quicksave:visible').each(function() {

		current_quicksave = jQuery(this);
		previous_quicksave = jQuery(this).next();

			// Verify we are not on the last item
			if ( jQuery(previous_quicksave).hasClass('quicksave') ) {

				// Process plugins
				jQuery(this).find('.plugin').each(function() {

					plugin_name = jQuery(this).data("plugin-name");
					plugin_previous = jQuery(previous_quicksave).find(".plugin[data-plugin-name='"+plugin_name+"']");

					if ( jQuery(this).data("plugin-version") != jQuery(plugin_previous).data("plugin-version") ) {
						jQuery( this ).addClass("version-changed");
					}
					if ( jQuery(this).data("plugin-status") != jQuery(plugin_previous).data("plugin-status") ) {
						jQuery( this ).addClass("status-changed");
					}

				});

				// Process plugin removals
				jQuery(previous_quicksave).find('.plugin').each(function() {

					plugin_name = jQuery(this).data("plugin-name");
					plugin_current_exists = jQuery(current_quicksave).find(".plugin[data-plugin-name='"+plugin_name+"']").length;

					if ( plugin_current_exists == 0 ) {
						plugin_removed = jQuery(this).clone().addClass("removed");
						jQuery(current_quicksave).find('table.plugins').append( plugin_removed );
					}

				});

				// Process themes
				jQuery(this).find('.theme').each(function() {
					theme_name = jQuery(this).data("theme-name");
					theme_previous = jQuery(previous_quicksave).find(".theme[data-theme-name='"+theme_name+"']");

					if ( jQuery(this).data("theme-version") != jQuery(theme_previous).data("theme-version") ) {
						jQuery( this ).addClass("version-changed");
					}
					if ( jQuery(this).data("theme-status") != jQuery(theme_previous).data("theme-status") ) {
						jQuery( this ).addClass("status-changed");
					}

				});

				// Process theme removals
				jQuery(previous_quicksave).find('.theme').each(function() {

					theme_name = jQuery(this).data("theme-name");
					theme_current_exists = jQuery(current_quicksave).find(".theme[data-theme-name='"+theme_name+"']").length;

					if ( theme_current_exists == 0 ) {
						theme_removed = jQuery(this).clone().addClass("removed");
						jQuery(current_quicksave).find('table.themes').append( theme_removed );
					}

				});
			}

		});
	}

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

		// Load websites assigned to partner
		$arguments = array(
			'post_type' 			=> 'captcore_website',
			'posts_per_page'	=> '-1',
			'order'						=> 'asc',
			'orderby'					=> 'title',
			'meta_query'			=> array(
				'relation'			=> 'AND',
				array(
					'key' => 'partner',
					'value' => '"' . $partner_id . '"',
					'compare' => 'LIKE'
				),
				array(
					'key'	  	=> 'status',
					'value'	  	=> 'closed',
					'compare' 	=> '!=',
				),
				array(
					'key'		=> 'address',
					'compare'	=>	'EXISTS',
				),
				array(
					'key'		=> 'address',
					'value'	  	=> '',
					'compare'	=>	'!=',
				),
			)
		);

		if ( in_array( 'administrator', $user->roles) ) {

			// Load all websites for administrators
			$arguments["meta_query"] = array(
					array(
						'key'	  	=> 'status',
						'value'	  	=> 'closed',
						'compare' 	=> '!=',
					),
					array(
						'key'		=> 'address',
						'compare'	=>	'EXISTS',
					),
					array(
						'key'		=> 'address',
						'value'	  	=> '',
						'compare'	=>	'!=',
					),
			);

		}

	// Loads websites
	$websites = get_posts( $arguments );

	if ( count( $websites ) == 0 ) {

		// Load websites assigned to partner
		$websites = get_posts(array(
			'post_type' 			=> 'captcore_website',
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
					array(
						'key'		=> 'address',
						'compare'	=>	'EXISTS',
					),
					array(
						'key'		=> 'address',
						'value'	  	=> '',
						'compare'	=>	'!=',
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
			<div class="website-group">
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
			<div class="flip-container">
			<div class="flipper">

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
				Password: <span class="pass-fake">****************</span><span class="pass"><?php the_field('password', $website->ID); ?></span><br />
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

			 <a href="/my-account/websites/<?php echo $website->ID; ?>" class="blue right btn">Advanced Options</a>
  </div>
	</div>
</div>

<?php if (get_field('address_staging', $website->ID)) { ?>
<div class="card partner staging" data-id="<?php echo get_the_title( $website->ID ); ?>">

	<div class="card-content">
		<div class="toggle-buttons">
			<a href="#" class="production-toggle">Production</a> | <a href="#" class="staging-toggle active">Staging</a>
		</div>
		<span class="card-title activator grey-text text-darken-4"><?php if (strpos( get_field('address_staging', $website->ID), ".kinsta.com") ) { ?>
		<a href="https://staging-<?php the_field('site_staging', $website->ID); ?>.kinsta.com" target="_blank">staging-<?php the_field('site_staging', $website->ID); ?>.kinsta.com</a>
		<?php } else { ?>
		<a href="https://<?php the_field('site_staging', $website->ID); ?>.staging.wpengine.com" target="_blank"><?php the_field('site_staging', $website->ID); ?>.staging.wpengine.com</a>
		<?php } ?></span>
			<div class="logins">
				Address: <?php the_field('address_staging', $website->ID); ?><br />
				Username: <?php the_field('username_staging', $website->ID); ?><br />
				Password: <span class="pass-fake">****************</span><span class="pass"><?php the_field('password_staging', $website->ID); ?></span><br />
				Protocol: <?php the_field('protocol_staging', $website->ID); ?><br />
				Port: <?php the_field('port_staging', $website->ID); ?>
				<?php if ( strpos($production_address, ".kinsta.com") and get_field('database_username_staging', $website->ID) ) { ?>
					<hr />
				<p class="small">
					<a href="https://mysqleditor-staging-<?php the_field('database_username_staging', $website->ID); ?>.kinsta.com/" target="_blank">https://mysqleditor-staging-<?php the_field('database_username_staging', $website->ID); ?>.kinsta.com/</a><br />
					Database username: <?php the_field('database_username_staging', $website->ID); ?> <br />Database password: <?php the_field('database_password_staging', $website->ID); ?><br />
				</p>
				<?php } ?>
				<?php if (strpos($staging_address, ".kinsta.com") ) { ?>
					<hr /><small>ssh <?php the_field('username_staging', $website->ID); ?>@<?php echo $staging_address; ?> -p <?php the_field('port_staging', $website->ID); ?></small>
				<?php } ?>
				</div>
			</div>
	</div>
 <?php
$provider = "";
} ?>

	</div> <!-- end .flipper -->
</div> <!-- end .flip-container -->

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
