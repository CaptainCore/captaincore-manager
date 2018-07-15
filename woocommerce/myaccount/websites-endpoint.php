<?php
global $wp_query;

if ( $wp_query->query_vars['websites'] ) {

	$website_id = $wp_query->query_vars['websites'];
	$current_user = wp_get_current_user();

	if ( captaincore_verify_permissions( $website_id ) ) {
		// Display single website page ?>

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

			jQuery('.collapsible').collapsible();

			var backupStartDate = new Date("<?php echo get_field( 'backup_start_date', $website_id ); ?> 0:00");
			var currentTime = new Date();

			jQuery('.datepicker').datepicker({
				minDate: backupStartDate,
				maxDate: currentTime
			});

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
				onOpenEnd: function() { // Callback for Modal open.
					quicksave_highlight_changed( this );
				}
			});

			jQuery('.quicksave_manual_check a').click(function(e) {
				e.preventDefault();
				var post_id = jQuery(this).data('post-id');

				var data = {
					'action': 'captaincore_install',
					'post_id': post_id,
					'command': 'quick_backup'
				};

				// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
				jQuery.post(ajaxurl, data, function(response) {
					M.toast({html: 'Checking for file changes. If any found a new Quicksave will be added shortly.'});
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
						'action': 'captaincore_install',
						'post_id': quicksave.data('id'),
						'command': 'quicksave_rollback',
					};

					jQuery.post(ajaxurl, data, function(response) {
						M.toast({html: 'Rollback in process.'});
						jQuery(quicksave).find(".git_status").html( '' ); // Clears loading
					});

				}

			});

			jQuery('.view_quicksave_changes').click(function(e) {
				e.preventDefault();
				jQuery(this).parent().addClass("activator").trigger("click");
				quicksave = jQuery(this).parents('.quicksave');
				quicksave_date = jQuery( quicksave ).find(".timestamp").text();
				jQuery(quicksave).find(".card-reveal .response").html( '<p></p><div class="preloader-wrapper small active"><div class="spinner-layer spinner-blue-only"><div class="circle-clipper left"><div class="circle"></div></div><div class="gap-patch"><div class="circle"></div></div><div class="circle-clipper right"><div class="circle"></div></div></div></div><p></p>' );

				var data = {
					'action': 'captaincore_install',
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

							jQuery(quicksave).find(".card-reveal .response").append("<a class='file modal-trigger' href='#file_"+i+"_quicksave_"+quicksave.data('id')+"' data-file-name='"+file_name+"'><span class='file_status'>"+file_status+"</span><span class='file_name'>"+file_name+"</span></div>");
							jQuery(".website-group").append(`<div id="file_`+i+`_quicksave_`+quicksave.data('id')+`" class="modal file_diff">
<div class="modal-content">
	<h4>`+file_name+` <a href="#" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a><a href="#" class="btn blue quicksave-restore-file right" data-file-name="`+file_name+`"  data-quicksave-date="`+quicksave_date+`" data-quicksave-id="`+quicksave.data('id')+`">Restore this file</a></h4>
	<p></p>
</div>
</div>`);

							i++;
						});

					}
				});

			});

			jQuery(".website-group").on( "click", ".file_diff .quicksave-restore-file", function(e) {

				e.preventDefault();
				modal_id = jQuery( this ).parents(".modal.open");
				file_name = jQuery( this ).data("file-name");
				quicksave_id = jQuery( this ).data("quicksave-id");
				quicksave_date = jQuery( this ).data("quicksave-date");
				var data = {
					'action': 'captaincore_install',
					'post_id': quicksave_id,
					'command': 'quicksave_file_restore',
					'value'	: file_name,
				};

				confirm_file_rollback = confirm("Rollback file " + file_name + " as of " + quicksave_date);

				if(confirm_file_rollback) {
					jQuery.post(ajaxurl, data, function(response) {
							M.toast({html: 'File restore in process. Will email once completed.'});
							jQuery( modal_id ).modal('close');
					});
				}

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
					'action': 'captaincore_install',
					'post_id': quicksave.data('id'),
					'command': 'quicksave_file_diff',
					'commit': quicksave.data('git_commit'),
					'value'	: file_name,
				};

				jQuery.post(ajaxurl, data, function(response) {
//							response=`diff --git a/plugins/captaincore/inc/admin-submenu-tabs.php b/plugins/captaincore/inc/admin-submenu-tabs.php
//index 7a7da39..f42cf84 100644
//--- a/plugins/captaincore/inc/admin-submenu-tabs.php
//+++ b/plugins/captaincore/inc/admin-submenu-tabs.php
//@@ -5,5 +5,4 @@
// 	<a class="nav-tab" href="/wp-admin/admin.php?page=captaincore_installs">Installs</a>
// 	<a class="nav-tab" href="/wp-admin/admin.php?page=captaincore_timeline">Timeline</a>
// 	<a class="nav-tab" href="/wp-admin/admin.php?page=captaincore_kpi">KPI</a>
//-	<a class="nav-tab" href="/wp-admin/admin.php?page=captaincore_quicksaves">Quicksaves</a>
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
						'action': 'captaincore_install',
						'post_id': quicksave.data('id'),
						'command': 'rollback',
						'value'	: addon_name,
						'addon_type': addon_type,
					};

					jQuery.post(ajaxurl, data, function(response) {
						M.toast({html: 'Rollback in progress.' + response });
						jQuery(quicksave).find(".git_status").html( "" ); // Clears loading
					});

				}

			});

			jQuery(".snapshot .modal-content button.btn").click(function(e){

				e.preventDefault();

				modal_form = jQuery(this).parents('.modal.open');

				jQuery('.modal.open .modal-content .row').hide();
				jQuery('.modal.open .modal-content .progress').removeClass('hide');

				email_address = modal_form.find('input#email').val();
				snapshot_version = modal_form.find('input#snapshot_version').val();
				var data = {
					'action': 'captaincore_install',
					'post_id': modal_form.data('id'),
					'command': 'snapshot',
					'value'	: email_address
				};

				if (snapshot_version) {
					data.date = snapshot_version;
				}

				if ( isEmail(email_address) ) {
					// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
					jQuery.post(ajaxurl, data, function(response) {
						M.toast({html: 'Backup snapshot in process. Will email once completed.'});
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

			jQuery(".toggle .modal-content a.deactivate").click(function(e){

				e.preventDefault();

				modal_form = jQuery(this).parents('.modal.open');

				confirm_toggle = confirm("Will deactivate website. Proceed?");

					if(confirm_toggle) {

						var post_id = jQuery(this).data('post-id');
						var name = jQuery('input#name').val();
						var link = jQuery('input#link').val();

						var data = {
							'action': 'captaincore_install',
							'post_id': modal_form.data('id'),
							'command': 'deactivate',
							'name': name,
							'link': link
						};

						// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
						jQuery.post(ajaxurl, data, function(response) {
							M.toast({html: 'Deactivating site.'});
							jQuery('.modal.open .modal-content .row').show();
							jQuery('.modal.open .modal-content .progress').addClass('hide');
							modal_form.modal('close');
						});

					} else {
						jQuery('.modal.open .modal-content .row').show();
						jQuery('.modal.open .modal-content .progress').addClass('hide');
					}

			});

			jQuery(".toggle .modal-content a.activate").click(function(e){

				e.preventDefault();

				modal_form = jQuery(this).parents('.modal.open');

				confirm_toggle = confirm("Will activate website. Proceed?");

					if(confirm_toggle) {

						var post_id = jQuery(this).data('post-id');

						var data = {
							'action': 'captaincore_install',
							'post_id': modal_form.data('id'),
							'command': 'activate',
						};

						// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
						jQuery.post(ajaxurl, data, function(response) {
							M.toast({html: 'Activating site.'});
							jQuery('.modal.open .modal-content .row').show();
							jQuery('.modal.open .modal-content .progress').addClass('hide');
							modal_form.modal('close');
						});

					} else {
						jQuery('.modal.open .modal-content .row').show();
						jQuery('.modal.open .modal-content .progress').addClass('hide');
					}

			});

			jQuery(".apply_ssl .modal-content button").click(function(e){

				e.preventDefault();

				modal_form = jQuery(this).parents('.modal.open');

				confirm_applyssl = confirm("Will apply ssl urls. Proceed?");
				command = jQuery(this).val();

					if(confirm_applyssl) {

						var post_id = jQuery(this).data('post-id');

						var data = {
							'action': 'captaincore_install',
							'post_id': modal_form.data('id'),
							'command': command,
						};

						// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
						jQuery.post(ajaxurl, data, function(response) {
							M.toast({html: 'Apply SSL in progress.'});
							jQuery('.modal.open .modal-content .row').show();
							jQuery('.modal.open .modal-content .progress').addClass('hide');
							modal_form.modal('close');
						});

					} else {
						jQuery('.modal.open .modal-content .row').show();
						jQuery('.modal.open .modal-content .progress').addClass('hide');
					}

			});

			jQuery(".copy_site .modal-content .start_copy").click(function(e){

					e.preventDefault();

					modal_form = jQuery(this).parents('.modal.open');
					confirm_copy_site = confirm("Will start site copy. Proceed?");

					if(confirm_copy_site) {

						var post_id = jQuery(this).data('post-id');
						var site = jQuery("#autocomplete-input").val().split(" ")[0];

						var data = {
							'action': 'captaincore_install',
							'post_id': modal_form.data('id'),
							'command': 'copy',
							'value': site
						};

						// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
						jQuery.post(ajaxurl, data, function(response) {
							M.toast({html: 'Site Copy in progress.'});
							jQuery('.modal.open .modal-content .row').show();
							jQuery('.modal.open .modal-content .progress').addClass('hide');
							modal_form.modal('close');
						});

					}

			});

			jQuery(".push_to_staging .modal-content a.btn").click(function(e){

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
							'action': 'captaincore_install',
							'post_id': modal_form.data('id'),
							'command': 'production-to-staging',
							'value'	: email_address
						};

						// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
						jQuery.post(ajaxurl, data, function(response) {
							M.toast({html: 'Production push to staging in process. Will email once completed.'});
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

			jQuery(".push_to_production .modal-content a.btn").click(function(e){

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
							'action': 'captaincore_install',
							'post_id': modal_form.data('id'),
							'command': 'staging-to-production',
							'value'	: email_address
						};

						// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
						jQuery.post(ajaxurl, data, function(response) {
							M.toast({html: 'Staging push to production in process. Will email once completed.'});
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
						'action': 'captaincore_install',
						'post_id': post_id,
						'command': 'new'
					};

					// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
					jQuery.post(ajaxurl, data, function(response) {
						M.toast({html: 'Redeploy in progress.'});
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

				options = {
					data: {
					<?php
						// Loads websites
						$websites = captaincore_fetch_sites();
						foreach ( $websites as $website ) :
							$site = get_field( 'site', $website->ID );
							echo '"' . $site . ' (' . get_the_title( $website->ID ) . ')": null,';
						endforeach;
						?>
					},
				};

				jQuery('.autocomplete').autocomplete(options);

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

		$customer_id     = get_field( 'customer', $website_id );
		$hosting_plan    = get_field( 'hosting_plan', $customer_id[0] );
		$addons          = get_field( 'addons', $customer_id[0] );
		$storage         = get_field( 'storage', $customer_id[0] );
		$views           = get_field( 'views', $customer_id[0] );
		$website_storage = get_field( 'storage', $website_id );
		$website_views   = get_field( 'views', $website_id );
		$mailgun         = get_field( 'mailgun', $website_id );
		$domain          = preg_replace( '#^https?://#', '', get_field( 'home_url', $website_id ) );

	if ( $hosting_plan == 'basic' ) {
		$views_plan_limit = '100000';
	}
	if ( $hosting_plan == 'standard' ) {
		$views_plan_limit = '500000';
	}
	if ( $hosting_plan == 'professional' ) {
		$views_plan_limit = '1000000';
	}
	if ( $hosting_plan == 'business' ) {
		$views_plan_limit = '2000000';
	}
	if ( isset( $views ) ) {
		$views_percent = round( $views / $views_plan_limit * 100, 0 );
	}

		$storage_gbs = round( $storage / 1024 / 1024 / 1024, 1 );
		$storage_cap = '10';
	if ( $addons ) {
		foreach ( $addons as $item ) {
			// Evaluate if contains word storage
			if ( stripos( $item['name'], 'storage' ) !== false ) {
				// Found storage addon, now extract number and add to cap.
				$extracted_gbs = filter_var( $item['name'], FILTER_SANITIZE_NUMBER_INT );
				$storage_cap   = $storage_cap + $extracted_gbs;
			}
		}
	}

		$storage_percent = round( $storage_gbs / $storage_cap * 100, 0 );

		$production_address = get_field( 'address', $website_id );
		$staging_address    = get_field( 'address_staging', $website_id );
		$server             = get_field( 'server', $website_id );
	if ( $server and $server[0] ) {
		$provider = get_field( 'provider', $server[0] );

		// vars
		$provider_object = get_field_object( 'provider', $server[0] );
		$provider_label  = $provider_object['choices'][ $provider ];

		$server_name    = get_field( 'name', $server[0] );
		$server_address = get_field( 'address', $server[0] );
	}
		?>
		<?php if ( $views != $website_views or $storage != $website_storage ) { ?>
			<!-- Modal Structure -->
			<div id="view_usage_breakdown_<?php echo $customer_id[0]; ?>" class="modal bottom-sheet">
				<div class="modal-content">
					<h4>Usage Breakdown <small>(<?php echo get_the_title( $customer_id[0] ); ?>)</small> <a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>
					<div class="row">
						<div class="card">
							<div class="card-content">
					<?php

						/*
						*  Query posts for a relationship value.
						*  This method uses the meta_query LIKE to match the string "123" to the database value a:1:{i:0;s:3:"123";} (serialized array)
						*/

						$websites_for_customer = get_posts(
							array(
								'post_type'      => 'captcore_website',
								'posts_per_page' => '-1',
								'order'          => 'ASC',
								'orderby'        => 'title',
								'meta_query' => array(
									'relation' => 'AND',
									array(
										'key'     => 'status', // name of custom field
										'value'   => 'active', // matches exaclty "123", not just 123. This prevents a match for "1234"
										'compare' => '=',
									),
									array(
										'key'     => 'customer', // name of custom field
										'value'   => '"' . $customer_id[0] . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
										'compare' => 'LIKE',
									),
									array(
										'key'     => 'address',
										'compare' => 'EXISTS',
									),
									array(
										'key'     => 'address',
										'value'   => '',
										'compare' => '!=',
									),
								),
							)
						);
						?>
						<?php if ( $websites_for_customer ) : ?>
							<table class="usage highlight">
								<tr>
									<th>Site</th>
									<th>Storage</th>
									<th>Views</th>
								</tr>
							<?php
							foreach ( $websites_for_customer as $website_for_customer ) :
								$website_for_customer_storage = get_field( 'storage', $website_for_customer->ID );
								$website_for_customer_views   = get_field( 'views', $website_for_customer->ID );
									?>
								<tr>
									<td><?php echo get_the_title( $website_for_customer->ID ); ?></td>
									<td>
									<?php
									if ( $website_for_customer_storage ) {
										echo '<i class="fas fa-hdd"></i> ' . round( $website_for_customer_storage / 1024 / 1024 / 1024, 1 ) . 'GB'; }
?>
</td>
									<td>
									<?php
									if ( $website_for_customer_views ) {
										echo '<i class="fas fa-eye"></i> ' . number_format( $website_for_customer_views ) . ' views'; }
?>
</td>
								</tr>
							<?php endforeach; ?>
							<tr>
								<td colspan="3"><hr /></td>
							</tr>
							<tr>
								<td>Total</td>
								<td><?php if ( $storage_gbs != 0 ) { ?>
									<div class="usage<?php	if ( $storage_percent > 100 ) {	echo ' over'; }?>">
											<?php echo $storage_percent; ?>% storage<br />
											<strong><?php echo $storage_gbs; ?>GB/<?php echo $storage_cap; ?>GB</strong>
									</div>
							  <?php } ?></td>
								<td>
								<?php if ( $views != 0 ) { ?>
								<div class="usage<?php if ( $views_percent > 100 ) { echo ' over'; } ?>">
										<?php echo $views_percent; ?>% traffic<br />
										<strong><?php echo number_format( $views ); ?></strong> <small>Yearly Estimate</small>
								</div>
							<?php } ?></td>
						  </tr>
						  </table>

						<?php endif; ?>
					</div>
				</div>
			  </div>
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
		<a href="#apply_ssl<?php echo $website_id; ?>" class="waves-effect waves-light large modal-trigger"><i class="material-icons left">launch</i>Apply HTTPS Urls</a><br />
		<a href="#copy_site<?php echo $website_id; ?>" class="waves-effect waves-light large modal-trigger"><i class="fas fa-clone"></i>Copy Site</a><br />
		<a href="#snapshot<?php echo $website_id; ?>" class="waves-effect waves-light modal-trigger large"><i class="material-icons left">cloud</i>Download Backup Snapshot</a> <br />
		<?php if ( $mailgun ) { ?>
			<a href="#mailgun_logs_<?php echo $website_id; ?>" class="waves-effect waves-light large modal-trigger"><i class="material-icons left">email</i>View Mailgun Logs</a> <br />
		<?php } ?>
		<a href="#quicksave<?php echo $website_id; ?>" class="waves-effect waves-light modal-quicksave modal-trigger large"><i class="material-icons left">settings_backup_restore</i>Quicksaves (Plugins & Themes)</a><br />
		<a class="waves-effect waves-light large redeploy" data-post-id="<?php echo $website_id; ?>"><i class="material-icons left">loop</i>Redeploy users/plugins</a> <br />
		<?php
		if ( strpos( $production_address, '.kinsta.com' ) ) :
		?>
			<a href="#push_to_staging<?php echo $website_id; ?>" class="waves-effect waves-light modal-trigger large" data-post-id="<?php echo $website_id; ?>"><i class="material-icons left">local_shipping</i>Push Production to Staging</a><br />
			<a href="#push_to_production<?php echo $website_id; ?>" class="waves-effect waves-light modal-trigger large" data-post-id="<?php echo $website_id; ?>"><i class="material-icons reverse left">local_shipping</i>Push Staging to Production</a><br />
		<?php endif ?>
		<a href="#toggle_site<?php echo $website_id; ?>" class="waves-effect waves-light large modal-trigger"><i class="fas fa-toggle-on"></i>Toggle Site</a><br />
		<?php if ( $views != $website_views or $storage != $website_storage ) { ?>
			<a href="#view_usage_breakdown_<?php echo $customer_id[0]; ?>" class="waves-effect waves-light large modal-trigger"><i class="material-icons left">chrome_reader_mode</i>View Usage Breakdown</a><br />
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
		<label for="email">Email Address</label>
		<input id="email" type="email" class="validate" value="<?php echo $current_user->user_email; ?>">
	</div>
</div>
<?php if (get_field("backup_start_date", $website_id)) { ?>
<div class="row">
	<div class="input-field col s12">
		<label for="datepicker">Older version (Optional)</label>
		<input id="snapshot_version" type="text" class="datepicker">
	</div>
</div>
<?php } ?>

<div class="row">
	<div class="input-field col s12">
		<span class="results red-text text-darken-4"></span>
	</div>
</div>
<div class="row">
	<div class="input-field col s12">

		<button style="color:#fff !important;" id="submit" class="btn blue" value="Download" type="submit">Download</button>
	</div>
</div>
</div>
</div>

<div id="apply_ssl<?php echo $website_id; ?>" class="modal bottom-sheet apply_ssl" data-id="<?php echo $website_id; ?>">
<div class="modal-content">

<h4>Apply SSL <small>(<?php echo get_the_title( $website_id ); ?>)</small><a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>
<div class="progress hide">
		<div class="indeterminate"></div>
</div>
<div class="row">

	<div class="card-panel">
		<span style="font-size:16px;"><i class="material-icons">announcement</i> Domain needs to match current home url which is <strong><?php echo get_field( 'home_url', $website_id ); ?></strong>. Otherwise server domain mapping will need updated to prevent redirection loop.</span>
	</div>
	<p></p>
	<p>Select url replacement option.</p>
	<p><button class="btn blue<?php
	if ( $domain != get_the_title( $website_id ) ) {
		echo ' disabled'; }
?>" style="color:#fff !important;" value="applyssl"><b>Option 1</b>: https://<?php echo get_the_title( $website_id ); ?></button></p>
	<p><button class="btn blue<?php
	if ( $domain != 'www.' . get_the_title( $website_id ) ) {
		echo ' disabled'; }
?>" style="color:#fff !important;" value="applysslwithwww"><b>Option 2</b>: https://www.<?php echo get_the_title( $website_id ); ?></button></p>

</div>
</div>
</div>

<div id="toggle_site<?php echo $website_id; ?>" class="modal bottom-sheet toggle" data-id="<?php echo $website_id; ?>">
	<?php
	$belongs_to = get_field("partner", "user_{$current_user->ID}");
	$business_name = get_the_title( $belongs_to[0] );
	$business_link = get_field( "partner_link", $belongs_to[0] );

	?>
<div class="modal-content">

<h4>Toggle Site <small>(<?php echo get_the_title( $website_id ); ?>)</small><a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>
<div class="row">

	<div class="col s12 m6">
	<div class="card-panel">
		<p class="card-title">Deactivate Site</p>
		<p>Will apply deactivate message with the following link back to the site owner.</p>
			<div class="input-field col s12">
				<label for="name">Business name</label>
				<input id="name" type="text" class="validate" value="<?php echo $business_name; ?>">
			</div>
			<div class="input-field col s12">
				<label for="link">Business link</label>
				<input id="link" type="text" class="validate" value="<?php echo $business_link; ?>">
			</div>
		<p><a href="#" class="btn blue deactivate">Deactivate Site</a></p>
	</div>
	</div>

	<div class="col s12 m6">
	<div class="card-panel">
		<p class="card-title">Activate Site</p>

		<p><a href="#" class="btn blue activate">Activate Site</a></p>
	</div>
	</div>

</div>
</div>
</div>

<div id="copy_site<?php echo $website_id; ?>" class="modal copy_site bottom-sheet" data-id="<?php echo $website_id; ?>">
<div class="modal-content">
  <h4>Copy Site <small>(<?php echo get_the_title( $website_id ); ?>)</small><a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>
		<div class="card">
		<div class="card-content">

			<div class="input-field col s12">
				<input type="text" id="autocomplete-input" class="autocomplete">
				<label for="autocomplete-input">Select destination site</label>
				<a class="start_copy blue btn">Start Copy</a>
		</div>

	  </div>
	  </div>
	</div>
</div>
</div>

<?php if ( strpos( $production_address, '.kinsta.com' ) ) : ?>
<div id="push_to_staging<?php echo $website_id; ?>" class="modal push_to_staging" data-id="<?php echo $website_id; ?>">
<div class="modal-content">

<h4>Push Production to Staging <small>(<?php echo get_the_title( $website_id ); ?>)</small><a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>
<div class="progress hide">
		<div class="indeterminate"></div>
</div>
<div class="row">

	<div class="input-field col s12">
		<label for="email">Email Address</label><br />
		<input id="email" type="email" class="validate" value="
		<?php
		$current_user = wp_get_current_user();
		echo $current_user->user_email;
?>
">
	</div>
</div>
<div class="row">
	<div class="input-field col s12">
		<span class="results red-text text-darken-4"></span>
	</div>
</div>
<div class="row">
	<div class="input-field col s12">
		<a href="#" class="btn blue">Proceed</a>
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
		<input id="email" type="email" class="validate" value="<?php echo $current_user->user_email; ?>">
	</div>
</div>
<div class="row">
	<div class="input-field col s12">
		<span class="results red-text text-darken-4"></span>
	</div>
</div>
<div class="row">
	<div class="input-field col s12">
		<a href="#" class="btn blue">Proceed</a>
	</div>
</div>
</div>
</div>
<?php endif; ?>

<?php if ( $mailgun ) { ?>
<div id="mailgun_logs_<?php echo $website_id; ?>" class="modal bottom-sheet" data-id="<?php echo $website_id; ?>">
<div class="modal-content">
<h4>Mailgun Logs <small>(last 30 days)</small> <a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a></h4>

<div class="row">
<ul class="collapsible" data-collapsible="accordion">
<?php
$mailgun_events = mailgun_events( $mailgun );
if ( $mailgun_events->paging ) {
	// TO DO add paging
	// print_r($mailgun_events->paging);
}
foreach ( $mailgun_events->items as $mailgun_event ) {


	if ( $mailgun_event->envelope ) {
		$mailgun_description = $mailgun_event->event . ': ' . $mailgun_event->envelope->sender . ' -> ' . $mailgun_event->recipient;
	} else {
		$mailgun_description = $mailgun_event->event . ': ' . $mailgun_event->recipient;
	}
	?>

	<li class="mailgun_logs">
		<div class="collapsible-header">
			<span class="material-icons">event_note</span> <span class="timestamp"><?php echo date( 'M jS Y g:ia', $mailgun_event->timestamp ); ?></span>
			<span class="badge"><?php echo $mailgun_description; ?></span>
		</div>
		<div class="collapsible-body">
			<div class="card">
				<div class="card-content">
					<pre><?php echo json_encode( $mailgun_event, JSON_PRETTY_PRINT ); ?></pre>
				</div>

			</div>

		</div>
	</li>

<?php } ?>
</ul>
</div>
</div>
</div>
<?php } ?>

<div id="quicksave<?php echo $website_id; ?>" class="modal bottom-sheet quicksaves" data-id="<?php echo $website_id; ?>">
<div class="modal-content">

<h4>
	Quicksaves <small>(<?php echo get_the_title( $website_id ); ?>)</small><a href="#!" class="modal-action modal-close grey-text text-darken-4"><i class="material-icons right">close</i></a>
	<span class="action-buttons quicksave_manual_check"><a href="#" data-post-id="<?php echo $website_id; ?>" class="btn blue ">Manually check for changes.</a></span>
</h4>
<div class="progress hide">
		<div class="indeterminate"></div>
</div>
<div class="row">
		<?php
		$db_quicksaves = new CaptainCore\quicksaves;
		$quicksaves_for_website = $db_quicksaves->fetch( $website_id );
		if ( $quicksaves_for_website ) {
		?>
		<ul class="collapsible" data-collapsible="accordion">
		<?php

		foreach ( $quicksaves_for_website as $quicksave ) {

			// Format for mysql timestamp format. Changes "1530817828" to "2018-06-20 09:15:20"
			$date = new DateTime( $quicksave->created_at );  // convert UNIX timestamp to PHP DateTime
			$timestamp  = $date->format('M jS Y g:ia');
			$plugins    = json_decode( $quicksave->plugins );
			$themes     = json_decode( $quicksave->themes );
			$core       = $quicksave->core;
			$git_status = $quicksave->git_status;
			$git_commit = $quicksave->git_commit;
			?>
			<li class="quicksave" data-id="<?php echo $website_id; ?>" data-git_commit="<?php echo $git_commit; ?>">
				<div class="collapsible-header">
					<span class="material-icons">settings_backup_restore</span> <span class="timestamp"><?php echo $timestamp; ?></span>
					<span class="badge"><?php echo $git_status; ?></span>
					<span class="badge">WordPress <?php echo $core; ?> - <?php echo count( $plugins ); ?> plugins - <?php echo count( $themes ); ?> themes</span>
				</div>
				<div class="collapsible-body">
					<div class="card">
						<div class="card-content">
							<div class="action-buttons">
								<a class="quicksave_rollback blue btn">Entire Quicksave Rollback</a>
								<a class="view_quicksave_changes blue btn">View Changes</a>
							</div>
							<div class="git_status"></div>
							<table class="highlight themes" id="themes_<?php echo $website_id; ?>">
								<thead>
									<tr>
											<th>Theme</th>
											<th>Version</th>
											<th>Status</th>
											<th></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $themes as $theme ) { ?>
									<tr class="theme" data-theme-name="<?php echo $theme->name; ?>" data-theme-version="<?php echo $theme->version; ?>" data-theme-status="<?php echo $theme->status; ?>">
										<td>
										<?php
										if ( $theme->title ) {
											echo $theme->title;
										} else {
											echo $theme->name; }
?>
</td>
										<td><span><?php echo $theme->version; ?></span></td>
										<td><span><?php echo $theme->status; ?></span></td>
										<td><a href="#rollback" class="btn blue rollback" data-theme-name="<?php echo $theme->name; ?>">Rollback</a></td>
									</tr>
									<?php } ?>
								</tbody>
							</table>
							<table class="highlight plugins" id="plugins_<?php echo $website_id; ?>">
								<thead>
									<tr>
											<th>Plugin</th>
											<th>Version</th>
											<th>Status</th>
											<th></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $plugins as $plugin ) { ?>
									<tr class="plugin" data-plugin-name="<?php echo $plugin->name; ?>" data-plugin-version="<?php echo $plugin->version; ?>" data-plugin-status="<?php echo $plugin->status; ?>">
										<td>
										<?php
										if ( $plugin->title ) {
											echo $plugin->title;
										} else {
											echo $plugin->name; }
?>
</td>
										<td><span><?php echo $plugin->version; ?></span></td>
										<td><span><?php echo $plugin->status; ?></span></td>
										<td><a href="#rollback" class="btn blue rollback" data-plugin-name="<?php echo $plugin->name; ?>">Rollback</a></td>
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
} else {
?>
Website not found
<a href="<?php echo get_site_url( null, '/my-account/' ); ?>" class="alignright button">View All Websites</a>
<?php
}
