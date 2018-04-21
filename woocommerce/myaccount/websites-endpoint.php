<?php
global $wp_query;

if ( $wp_query->query_vars["websites"] ) {

	$website_id = $wp_query->query_vars["websites"];

	if ( anchor_verify_permissions( $website_id ) ) {
	//  Display single website page ?>

	<script type="text/javascript">

		ajaxurl = "/wp-admin/admin-ajax.php";

		Array.prototype.clean = function(deleteValue) {
			for (var i = 0; i < this.length; i++) {
				if (this[i] == deleteValue) {
					this.splice(i, 1);
					i--;
				}
			}
			return this;
		};

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

			jQuery('.quicksave_manual_check a').click(function(e) {
				e.preventDefault();
				var post_id = jQuery(this).data('post-id');

				var data = {
					'action': 'anchor_install',
					'post_id': post_id,
					'command': 'quick_backup'
				};

				// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
				jQuery.post(ajaxurl, data, function(response) {
					Materialize.toast('Checking for file changes. If any found a new Quicksave will be added shortly.', 4000);
				});

			});

			jQuery('.quicksave_rollback').click(function(e) {
				e.preventDefault();

				quicksave = jQuery(this).parents('.quicksave');
				quicksave_date = jQuery(quicksave).find('span.timestamp').text();

				confirm_rollback = confirm("Rollback all themes and plugins to version as of " + quicksave_date);

				if(confirm_rollback) {

					jQuery(quicksave).find(".git_status").html( '<p></p><div class="preloader-wrapper small active"><div class="spinner-layer spinner-blue-only"><div class="circle-clipper left"><div class="circle"></div></div><div class="gap-patch"><div class="circle"></div></div><div class="circle-clipper right"><div class="circle"></div></div></div></div><p></p>' );

					var data = {
						'action': 'anchor_install',
						'post_id': quicksave.data('id'),
						'command': 'quicksave_rollback',
					};

					jQuery.post(ajaxurl, data, function(response) {
						Materialize.toast('Rollback in process.', 4000);
						jQuery(quicksave).find(".git_status").html( '' );
					});

				}

			});

			jQuery('.view_quicksave_changes').click(function(e) {
				e.preventDefault();
				jQuery(this).parent().addClass("activator").trigger("click");
				quicksave = jQuery(this).parents('.quicksave');
				jQuery(quicksave).find(".card-reveal .response").html( '<p></p><div class="preloader-wrapper small active"><div class="spinner-layer spinner-blue-only"><div class="circle-clipper left"><div class="circle"></div></div><div class="gap-patch"><div class="circle"></div></div><div class="circle-clipper right"><div class="circle"></div></div></div></div><p></p>' );

				var data = {
					'action': 'anchor_install',
					'post_id': quicksave.data('id'),
					'command': 'view_quicksave_changes',
					'value'	: quicksave.data("git_commit")
				};

				jQuery.post(ajaxurl, data, function(response) {
//							var response = `M	plugins/captaincore/.revision
//M	plugins/captaincore/captaincore.php
//A	plugins/captaincore/inc/admin-report-quicksaves.php
//M	plugins/captaincore/inc/admin-submenu-tabs.php`;
					var files = response.split("\n");
					files.clean("");
					if (files.length > 0) {
						jQuery(quicksave).find(".card-reveal .response").html( "" );
						i = 0;
						jQuery(files).each(function() {
							file_array = files[i].split("\t");
							file_status = file_array[0];
							file_name = file_array[1];
							jQuery(quicksave).find(".card-reveal .response").append("<a class='file modal-trigger' href='#file_"+i+"'><span class='file_status'>"+file_status+"</span><span class='file_name'>"+file_name+"</span></div>");
							jQuery(".website-group").append(`<div id="file_`+i+`" class="modal file_diff">
<div class="modal-content">
	<h4>`+file_name+` <a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>
	<p></p>
</div>
</div>`);
							//

							i++;
						});

					}
				});

			});

			jQuery(".response").on( "click", ".file.modal-trigger", function(e) {
				//jQuery(".website-group").find(".modal.file_diff").modal();
				e.preventDefault();

				file_name = jQuery( this ).find("span.file_name").text().trim();
				quicksave = jQuery( this ).parents('.quicksave');
				modal_id = jQuery( this ).attr("href");
				jQuery( modal_id ).modal();
				jQuery( modal_id ).find("p").html( '<p></p><div class="preloader-wrapper small active"><div class="spinner-layer spinner-blue-only"><div class="circle-clipper left"><div class="circle"></div></div><div class="gap-patch"><div class="circle"></div></div><div class="circle-clipper right"><div class="circle"></div></div></div></div><p></p>' );
				jQuery( modal_id ).modal('open');
				var data = {
					'action': 'anchor_install',
					'post_id': quicksave.data('id'),
					'command': 'quicksave_file_diff',
					'value'	: file_name,
				};

				jQuery.post(ajaxurl, data, function(response) {
//							response=`diff --git a/plugins/captaincore/inc/admin-submenu-tabs.php b/plugins/captaincore/inc/admin-submenu-tabs.php
//index 7a7da39..f42cf84 100644
//--- a/plugins/captaincore/inc/admin-submenu-tabs.php
//+++ b/plugins/captaincore/inc/admin-submenu-tabs.php
//@@ -5,5 +5,4 @@
// 	<a class="nav-tab" href="/wp-admin/admin.php?page=anchor_installs">Installs</a>
// 	<a class="nav-tab" href="/wp-admin/admin.php?page=anchor_timeline">Timeline</a>
// 	<a class="nav-tab" href="/wp-admin/admin.php?page=anchor_kpi">KPI</a>
//-	<a class="nav-tab" href="/wp-admin/admin.php?page=anchor_quicksaves">Quicksaves</a>
// </h2>`;
					file_diff = response.split("\n");
					jQuery( modal_id ).find("p").html( "" );
					i = 0;
					jQuery(file_diff).each(function() {
						diff_code = document.createElement( "div" );
						jQuery(diff_code).addClass("code").text( file_diff[i] );
						if( file_diff[i][0] == "-" ) {
							jQuery(diff_code).addClass("remove");
						}
						if ( file_diff[i][0] == "+" ) {
							jQuery(diff_code).addClass("add");
						}
						jQuery( modal_id ).find("p").append( diff_code );
						i++;
					});

					//jQuery( modal_id ).find("p").text( diff_code );
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
						'action': 'anchor_install',
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

			jQuery(".snapshot .modal-content input#submit").click(function(e){

				e.preventDefault();

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
						jQuery('.modal.open .modal-content .row').show();
						jQuery('.modal.open .modal-content .progress').addClass('hide');
						modal_form.modal('close');
					});
				} else {
					modal_form.find('.results').html("Please enter a valid email address.");
					jQuery('.modal.open .modal-content .row').show();
					jQuery('.modal.open .modal-content .progress').addClass('hide');
				}

			});

			jQuery(".push_to_staging .modal-content input#submit").click(function(e){

				e.preventDefault();

				modal_form = jQuery(this).parents('.modal.open');

				jQuery('.modal.open .modal-content .row').hide();
				jQuery('.modal.open .modal-content .progress').removeClass('hide');

				email_address = modal_form.find('input#email').val();

				if ( isEmail(email_address) ) {

					confirm_deploy = confirm("Staging site will be overridden. Proceed?");

					if(confirm_deploy) {

						var post_id = jQuery(this).data('post-id');

						var data = {
							'action': 'anchor_install',
							'post_id': modal_form.data('id'),
							'command': 'production-to-staging',
							'value'	: email_address
						};

						// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
						jQuery.post(ajaxurl, data, function(response) {
							Materialize.toast('Production push to staging in process. Will email once completed.', 4000);
							jQuery('.modal.open .modal-content .row').show();
							jQuery('.modal.open .modal-content .progress').addClass('hide');
							modal_form.modal('close');
						});

					} else {
						jQuery('.modal.open .modal-content .row').show();
						jQuery('.modal.open .modal-content .progress').addClass('hide');
					}

				} else {
					modal_form.find('.results').html("Please enter a valid email address.");
					jQuery('.modal.open .modal-content .row').show();
					jQuery('.modal.open .modal-content .progress').addClass('hide');
				}

			});

			jQuery(".push_to_production .modal-content input#submit").click(function(e){

				e.preventDefault();

				modal_form = jQuery(this).parents('.modal.open');

				jQuery('.modal.open .modal-content .row').hide();
				jQuery('.modal.open .modal-content .progress').removeClass('hide');

				email_address = modal_form.find('input#email').val();

				if ( isEmail(email_address) ) {

					confirm_deploy = confirm("Production site will be overridden. Proceed?");

					if(confirm_deploy) {

						var post_id = jQuery(this).data('post-id');

						var data = {
							'action': 'anchor_install',
							'post_id': modal_form.data('id'),
							'command': 'staging-to-production',
							'value'	: email_address
						};

						// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
						jQuery.post(ajaxurl, data, function(response) {
							Materialize.toast('Staging push to production in process. Will email once completed.', 4000);
							jQuery('.modal.open .modal-content .row').show();
							jQuery('.modal.open .modal-content .progress').addClass('hide');
							modal_form.modal('close');
						});

					} else {
						jQuery('.modal.open .modal-content .row').show();
						jQuery('.modal.open .modal-content .progress').addClass('hide');
					}

				} else {
					modal_form.find('.results').html("Please enter a valid email address.");
					jQuery('.modal.open .modal-content .row').show();
					jQuery('.modal.open .modal-content .progress').addClass('hide');
				}

			});

			jQuery(".redeploy").click(function(e){

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

	<div class="website-group">
	<?php

		$customer_id = get_field('customer', $website_id);
		$hosting_plan = get_field('hosting_plan', $customer_id[0]);
		$addons = get_field('addons', $customer_id[0]);
		$storage = get_field('storage', $customer_id[0]);
		$views = get_field('views', $customer_id[0]);
		$website_storage = get_field('storage', $website_id);
		$website_views = get_field('views', $website_id);
		$mailgun = get_field('mailgun', $website_id);

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

		$production_address = get_field('address', $website_id);
		$staging_address = get_field('address_staging', $website_id);
		$server = get_field('server', $website_id);
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
							'post_type' => 'captcore_website',
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

<div class="card partner production" data-id="<?php echo get_the_title( $website_id ); ?>">

<div class="card-content">
<div class="row">
<span class="card-title grey-text text-darken-4 "><a href="http://<?php echo get_the_title( $website_id ); ?>" target="_blank"><?php echo get_the_title( $website_id ); ?></a></span>
	<div class="col s12 m12">
		<a href="#snapshot<?php echo $website_id; ?>" class="waves-effect waves-light modal-trigger large"><i class="material-icons left">cloud</i>Download Backup Snapshot</a> <br />
		<a class="waves-effect waves-light large redeploy" data-post-id="<?php echo $website_id; ?>"><i class="material-icons left">loop</i>Redeploy users/plugins</a> <br />
		<a href="#quicksave<?php echo $website_id; ?>" class="waves-effect waves-light modal-quicksave modal-trigger large"><i class="material-icons left">settings_backup_restore</i>Quicksaves (Plugins & Themes)</a><br />
		<?php if( defined('ANCHOR_DEV_MODE') ) { ?>
			<!-- <a href="#install-premium-plugin<?php echo $website_id; ?>" class="waves-effect waves-light modal-trigger large"><i class="material-icons left">add</i>Install premium plugin</a> <br />-->
		<?php } ?>
		<?php
		if( strpos($production_address, ".kinsta.com") ):  ?>
			<a href="#push_to_staging<?php echo $website_id; ?>" class="waves-effect waves-light modal-trigger large" data-post-id="<?php echo $website_id; ?>"><i class="material-icons left">local_shipping</i>Push Production to Staging</a><br />
			<a href="#push_to_production<?php echo $website_id; ?>" class="waves-effect waves-light modal-trigger large" data-post-id="<?php echo $website_id; ?>"><i class="material-icons reverse left">local_shipping</i>Push Staging to Production</a><br />
		<?php endif ?>
		<?php if ($views != $website_views or $storage != $website_storage) { ?>
			<a href="#view_usage_breakdown_<?php echo $customer_id[0]; ?>" class="waves-effect waves-light large modal-trigger"><i class="material-icons left">chrome_reader_mode</i>View Usage Breakdown</a><br />
		<?php } ?>
		<?php if ($mailgun) { ?>
			<a href="#mailgun_logs_<?php echo $website_id; ?>" class="waves-effect waves-light large modal-trigger"><i class="material-icons left">email</i>View Mailgun Logs</a>
		<?php } ?>
	</div>

</div>
</div>

</div>

</div> <!-- end .flipper -->
</div> <!-- end .flip-container -->

<a href="/my-account/" class="blue right btn">View All Websites</a>

<div id="snapshot<?php echo $website_id; ?>" class="modal snapshot" data-id="<?php echo $website_id; ?>">
<div class="modal-content">

<h4>Download Snapshot <small>(<?php echo get_the_title( $website_id ); ?>)</small><a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>
<div class="progress hide">
		<div class="indeterminate"></div>
</div>
<div class="row">

	<div class="input-field col s12">
		<label for="email">Email Address</label><br />
		<input id="email" type="email" class="validate" value="<?php $current_user = wp_get_current_user(); echo $current_user->user_email; ?>">
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

<?php if( strpos($production_address, ".kinsta.com") ):  ?>
<div id="push_to_staging<?php echo $website_id; ?>" class="modal push_to_staging" data-id="<?php echo $website_id; ?>">
<div class="modal-content">

<h4>Push Production to Staging <small>(<?php echo get_the_title( $website_id ); ?>)</small><a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>
<div class="progress hide">
		<div class="indeterminate"></div>
</div>
<div class="row">

	<div class="input-field col s12">
		<label for="email">Email Address</label><br />
		<input id="email" type="email" class="validate" value="<?php $current_user = wp_get_current_user(); echo $current_user->user_email; ?>">
	</div>
</div>
<div class="row">
	<div class="input-field col s12">
		<span class="results red-text text-darken-4"></span>
	</div>
</div>
<div class="row">
	<div class="input-field col s12">
		<input id="submit" value="Proceed" type="submit">
	</div>
</div>
</div>
</div>

<div id="push_to_production<?php echo $website_id; ?>" class="modal push_to_production" data-id="<?php echo $website_id; ?>">
<div class="modal-content">

<h4>Push Staging to Production <small>(<?php echo get_the_title( $website_id ); ?>)</small><a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>
<div class="progress hide">
		<div class="indeterminate"></div>
</div>
<div class="row">

	<div class="input-field col s12">
		<label for="email">Email Address</label><br />
		<input id="email" type="email" class="validate" value="<?php $current_user = wp_get_current_user(); echo $current_user->user_email; ?>">
	</div>
</div>
<div class="row">
	<div class="input-field col s12">
		<span class="results red-text text-darken-4"></span>
	</div>
</div>
<div class="row">
	<div class="input-field col s12">
		<input id="submit" value="Proceed" type="submit">
	</div>
</div>
</div>
</div>
<?php endif; ?>

<?php if ($mailgun) { ?>
<div id="mailgun_logs_<?php echo $website_id; ?>" class="modal bottom-sheet" data-id="<?php echo $website_id; ?>">
<div class="modal-content">
<h4>Mailgun Logs <small>(last 30 days)</small> <a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>

<div class="row">
<ul class="collapsible" data-collapsible="accordion">
<?php $mailgun_events = mailgun_events( $mailgun );
if ( $mailgun_events->paging ) {
	// TO DO add paging
	// print_r($mailgun_events->paging);
}
foreach($mailgun_events->items as $mailgun_event ) {


	if ($mailgun_event->envelope) {
		$mailgun_description = $mailgun_event->event. ": ". $mailgun_event->envelope->sender. " -> ". $mailgun_event->recipient;
	} else {
		$mailgun_description = $mailgun_event->event. ": ". $mailgun_event->recipient;
	}
	?>

	<li class="mailgun_logs">
		<div class="collapsible-header">
			<span class="material-icons">event_note</span> <span class="timestamp"><?php echo date('M jS Y g:ia', $mailgun_event->timestamp); ?></span>
			<span class="badge"><?php echo $mailgun_description; ?></span>
		</div>
		<div class="collapsible-body">
			<div class="card">
				<div class="card-content">
					<pre><?php echo json_encode($mailgun_event, JSON_PRETTY_PRINT); ?></pre>
				</div>

			</div>

		</div>
	</li>

<?php }	?>
</ul>
</div>
</div>
</div>
<?php } ?>

<div id="quicksave<?php echo $website_id; ?>" class="modal bottom-sheet quicksaves" data-id="<?php echo $website_id; ?>">
<div class="modal-content">

<h4>
	Quicksaves <small>(<?php echo get_the_title( $website_id ); ?>)</small><a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a>
	<p class="quicksave_manual_check"><a href="" data-post-id="<?php echo $website_id; ?>">Manually check for changes.</a></p>
</h4>
<div class="progress hide">
		<div class="indeterminate"></div>
</div>
<div class="row">
		<?php
		$quicksaves_for_website = get_posts(array(
			'post_type' => 'captcore_quicksave',
			'posts_per_page' => '-1',
			'meta_query' => array(
				array(
					'key' => 'website', // name of custom field
					'value' => '"' . $website_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
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
			$git_status = get_field( "git_status", $quicksave->ID );
			$git_commit = get_field( "git_commit", $quicksave->ID );
			?>
			<li class="quicksave" data-id="<?php echo $quicksave->ID; ?>" data-git_commit="<?php echo $git_commit; ?>">
				<div class="collapsible-header">
					<span class="material-icons">settings_backup_restore</span> <span class="timestamp"><?php echo $timestamp; ?></span>
					<span class="badge"><?php echo $git_status; ?></span>
					<span class="badge">WordPress <?php the_field("core", $quicksave->ID); ?> - <?php echo count($plugins); ?> plugins - <?php echo count($themes); ?> themes</span>
				</div>
				<div class="collapsible-body">
					<div class="card">
						<div class="card-content">
							<div class="action-buttons">
								<a class="quicksave_rollback blue btn">Entire Quicksave Rollback</a>
								<a class="view_quicksave_changes blue btn">View Changes</a>
							</div>
							<div class="git_status"></div>
							<table class="bordered plugins" id="plugins_<?php echo $website_id; ?>">
								<thead>
									<tr>
											<th>Plugin</th>
											<th>Version</th>
											<th>Status</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach( $plugins as $plugin ) { ?>
									<tr class="plugin" data-plugin-name="<?php echo $plugin->name; ?>" data-plugin-version="<?php echo $plugin->version; ?>" data-plugin-status="<?php echo $plugin->status; ?>">
										<td><?php if ($plugin->title) { echo $plugin->title; } else { echo $plugin->name; } ?></td>
										<td><span><?php echo $plugin->version; ?></span></td>
										<td><span><?php echo $plugin->status; ?></span></td>
										<td><a href="#rollback" class="rollback" data-plugin-name="<?php echo $plugin->name; ?>">Rollback</a></td>
									</tr>
									<?php } ?>
								</tbody>
							</table>
							<table class="bordered themes" id="themes_<?php echo $website_id; ?>">
								<thead>
									<tr>
											<th>Theme</th>
											<th>Version</th>
											<th>Status</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach( $themes as $theme ) { ?>
									<tr class="theme" data-theme-name="<?php echo $theme->name; ?>" data-theme-version="<?php echo $theme->version; ?>" data-theme-status="<?php echo $theme->status; ?>">
										<td><?php if ($theme->title) { echo $theme->title; } else { echo $theme->name; } ?></td>
										<td><span><?php echo $theme->version; ?></span></td>
										<td><span><?php echo $theme->status; ?></span></td>
										<td><a href="#rollback" class="rollback" data-theme-name="<?php echo $theme->name; ?>">Rollback</a></td>
									</tr>
									<?php } ?>
								</tbody>
							</table>
						</div>
						<div class="card-reveal">
							<span class="card-title grey-text text-darken-4"><i class="material-icons right">close</i></span>
							<div class="response"></div>
						</div>
					</div>

				</div>
			</li><?php } ?>
	</ul>
<?php } ?>

</div>

</div>
</div>

<div id="install-premium-plugin<?php echo $website_id; ?>" class="modal" data-id="<?php echo $website_id; ?>">
<div class="modal-content">

<h4>Install Premium Plugin <small><?php echo get_the_title( $website_id ); ?></small><a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>

<div class="row">
	<div class="input-field col s12">
		<input id="submit" value="Download" type="submit">
	</div>
</div>

</div>
</div>

</div>
<?php
}

}	else { ?>
Website not found
<a href="<?php echo get_site_url(null,'/my-account/'); ?>" class="alignright button">View All Websites</a>
<?php
}
