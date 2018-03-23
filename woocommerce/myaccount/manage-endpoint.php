<?php
$user = wp_get_current_user();
$role_check = in_array( 'administrator', $user->roles);
if ($role_check) { ?>
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

		themes = {};

		jQuery("tr.theme").each(function() {
			theme = jQuery(this);
			name = theme.data("theme-name");
			themes[name] = {
				title: theme.find("td:first").text().trim(),
				version: theme.data("theme-version"),
				status: theme.data("theme-status"),
		 	};
		});

		sorted_themes = Object.keys(themes).sort();
		jQuery(sorted_themes).each(function() {
			theme = this;
			jQuery('.site-filter select optgroup[label=Theme]').append( jQuery("<option />").val(theme).text( themes[theme].title + " (" + theme + ")" ) );
		});

		plugins = {};

		jQuery("tr.plugin").each(function() {
			plugin = jQuery(this);
			name = plugin.data("plugin-name");
			plugins[name] = {
				title: plugin.find("td:first").text().trim(),
				version: plugin.data("plugin-version"),
				status: plugin.data("plugin-status"),
		 	};
		});

		sorted_plugins = Object.keys(plugins).sort();
		jQuery(sorted_plugins).each(function() {
			plugin = this;
			jQuery('.site-filter select optgroup[label=Plugin]').append( jQuery("<option />").val(plugin).text( plugins[plugin].title + " (" + plugin + ")" ) );
		});

		jQuery(".site-filter select").change(function(event) {
			jQuery(".flip-container").hide();
			filter_name = jQuery(".site-filter .filter-name").val();
			filter_status = jQuery(".site-filter .filter-status").val();
			filter_version = jQuery(".site-filter .filter-version").val();
			if (filter_name) {
				filter = jQuery(".site-filter .filter-name :selected").parent().attr('label').toLowerCase();
			}
			filter_search = "tr."+ filter + "[data-"+ filter + "-name='"+filter_name+"']";
			if (filter_status) {
				filter_search = filter_search + "[data-"+ filter + "-status='"+filter_status+"']";
			}
			if (filter_version) {
				filter_search = filter_search + "[data-"+ filter + "-version='"+filter_version+"']";
			}
			sites_found = jQuery( filter_search ).length;
			jQuery("label.right h3").text("Listing "+ sites_found + " sites");
			filter_versions = {};
			filter_statuses = {};
			jQuery( filter_search ).each(function() {
				filter_version = jQuery(this).data( filter + "-version");
				filter_status = jQuery(this).data( filter + "-status");
				if (typeof filter_versions[filter_version] !== 'undefined') {
					filter_versions[filter_version] = filter_versions[filter_version] + 1;
				} else {
					filter_versions[filter_version] = 1;
				}
				if (typeof filter_statuses[filter_status] !== 'undefined') {
					filter_statuses[filter_status] = filter_statuses[filter_status] + 1;
				} else {
					filter_statuses[filter_status] = 1;
				}
				jQuery(this).closest(".flip-container").show();
			});
			filter_name = jQuery(".site-filter .filter-name").val();
			filtered_by_name = jQuery(event.target).hasClass('filter-name');
			filtered_by_status = jQuery(event.target).hasClass('filter-status');
			filtered_by_version = jQuery(event.target).hasClass('filter-version');
			if ( ( filtered_by_name && filter == "plugin" ) || ( filtered_by_name && filter == "theme" ) ) {
				jQuery(".filter-status option:not(':disabled')").remove();
				jQuery(".filter-version option:not(':disabled')").remove();
				sorted_statuses = Object.keys(filter_statuses).sort();
				sorted_versions = Object.keys(filter_versions).sort();
				total_sites = 0;
				jQuery(sorted_statuses).each(function() {
					status = this;
					total_sites = total_sites + filter_statuses[status];
					jQuery('.site-filter .filter-status').append( jQuery("<option />").val(status).text( status + " (" + filter_statuses[status] + ")" ) );
				});
				jQuery('.site-filter .filter-status').append( jQuery("<option />").val("").text( "All (" + total_sites + ")" ) );
				total_sites = 0;
				jQuery(sorted_versions).each(function() {
					version = this;
					total_sites = total_sites + filter_versions[version];
					jQuery('.site-filter .filter-version').append( jQuery("<option />").val(version).text( version + " (" + filter_versions[version] + ")" ) );
				});
				jQuery('.site-filter .filter-version').append( jQuery("<option />").val("").text( "All (" + total_sites + ")" ) );
			}
		});

		jQuery('.filter-select').change(function() {
			if (jQuery(this).val() == "all") {
				jQuery('.site .checkbox-selector').addClass("selected");
				selected_count = jQuery('.site .checkbox-selector.selected').length;
				jQuery(".selected-sites").text("Selected "+ selected_count +" sites");
			}
			if (jQuery(this).val() == "visible") {
				jQuery('.site .checkbox-selector').removeClass("selected");
				jQuery('.site .checkbox-selector:visible').addClass("selected");
				selected_count = jQuery('.site .checkbox-selector.selected').length;
				jQuery(".selected-sites").text("Selected "+ selected_count +" sites");
			}
			if (jQuery(this).val() == "none") {
				jQuery('.site .checkbox-selector').removeClass("selected");
				selected_count = jQuery('.site .checkbox-selector.selected').length;
				jQuery(".selected-sites").text("Selected "+ selected_count +" sites");
			}
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

		jQuery('.site .checkbox-selector').click(function() {
			jQuery(this).toggleClass("selected");
			selected_count = jQuery('.site .checkbox-selector.selected').length;
			jQuery(".selected-sites").text("Selected "+ selected_count +" sites");
		});

		jQuery('.modal').modal({
      dismissible: true, // Modal can be dismissed by clicking outside of the modal
      opacity: .5, // Opacity of modal background
      inDuration: 300, // Transition in duration
      outDuration: 200, // Transition out duration
      startingTop: '4%', // Starting top style attribute
      endingTop: '10%', // Ending top style attribute
		});

		jQuery('ul[data-collapsible="accordion"]').collapsible();

		jQuery('.preloader-wrapper.small.active').hide();
		jQuery('.site-manage').show();


	});

	function isEmail(email) {
  	var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
  	return regex.test(email);
	}
</script>

<?php

	$arguments = array(
		'post_type' 			=> 'captcore_website',
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
	);

// Loads websites
$websites = get_posts( $arguments );

if( $websites ): ?>

<label class="right">
<h3>Listing <?php echo count($websites);?> sites</h3>
</label>
<p>&nbsp;</p>
<div class="site-filter">
	Filter sites by <span class="input-field browser-default">
	<select class="filter-name">
	 <option value="1" disabled selected>Theme/Plugin or Core Version</option>
		<optgroup label="Theme"></optgroup>
    <optgroup label="Plugin"></optgroup>
		<optgroup label="Core"></optgroup>
	</select></span>
	<span class="input-field browser-default">
		<select class="filter-status">
			<option value="1" disabled selected>Status</option>
		</select>
	</span>
	<span class="input-field browser-default">
		<select class="filter-version">
			<option value="1" disabled selected>Version</option>
			<option value="2">active</option>
			<option value="2">inactive</option>
			<option value="2">dropin</option>
			<option value="2">must-use</option>
		</select>
	</span>
	<span class="input-field browser-default alignright">
		<select class="filter-select">
			<option value="1" disabled selected>Select</option>
			<option value="all">All</option>
			<option value="visible">Visible</option>
			<option value="none">None</option>
		</select>
	</span>
</div>
<div class="preloader-wrapper small active"><div class="spinner-layer spinner-blue-only"><div class="circle-clipper left"><div class="circle"></div></div><div class="gap-patch"><div class="circle"></div></div><div class="circle-clipper right"><div class="circle"></div></div></div></div>
<div class="website-group site-manage">

<?php foreach( $websites as $website ):

$customer_id = get_field('customer', $website->ID);

$production_address = get_field('address', $website->ID);
$staging_address = get_field('address_staging', $website->ID);

$timestamp = get_the_time( "M jS Y g:ia", $website->ID);
$themes = json_decode(get_field( "themes", $website->ID));
$plugins = json_decode(get_field( "plugins", $website->ID));
	?>
<div class="flip-container">
<div class="flipper">

<?php if (get_field('address', $website->ID)) { ?>
<div class="card partner production" data-id="<?php echo get_the_title( $website->ID ); ?>">

<div class="card-content">
<?php if (get_field('address_staging', $website->ID)) { ?>
<div class="toggle-buttons">
<a href="#" class="production-toggle active">Production</a> | <a href="#" class="staging-toggle">Staging</a>
</div>
<?php } ?>

<div class="site" data-id="<?php echo $website->ID; ?>">
	<div class="checkbox-selector"></div>
	<ul data-collapsible="accordion">
		<li>
		<div class="collapsible-header">
			<span class="title"><a href="http://<?php echo get_the_title( $website->ID ); ?>" target="_blank"><?php echo get_the_title( $website->ID ); ?></a></span>
		  <span class="timestamp"><?php echo $timestamp; ?></span>
			<span class="badge">WordPress <?php the_field("core", $website->ID); ?> - <?php echo count($plugins); ?> plugins - <?php echo count($themes); ?> themes</span>
		</div>
		<div class="collapsible-body">
					<table class="bordered plugins" id="plugins_<?php echo $website->ID; ?>">
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
							</tr>
							<?php } ?>
						</tbody>
					</table>
					<table class="bordered themes" id="themes_<?php echo $website->ID; ?>">
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

							</tr>
							<?php } ?>
						</tbody>
					</table>

		</div>
	</li>
</ul>

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
<a href="https://staging-<?php the_field('install_staging', $website->ID); ?>.kinsta.com" target="_blank">staging-<?php the_field('install_staging', $website->ID); ?>.kinsta.com</a>
<?php } else { ?>
<a href="https://<?php the_field('install_staging', $website->ID); ?>.staging.wpengine.com" target="_blank"><?php the_field('install_staging', $website->ID); ?>.staging.wpengine.com</a>
<?php } ?></span>
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

<?php endforeach; ?>
</div>
<?php endif;

} ?>

<div class="selected-sites">Selected 0 sites</div>
<div class="selected-action">
<ul>
	<li>
		Run a
		<select>
			<option disabled selected>Script/Command</option>
			<optgroup label="Script">
				<option>Migrate</option>
				<option>Apply SSL</option>
				<option>Apply SSL with www</option>
				<option>Launch</option>
			</optgroup>
			<optgroup label="Command">
				<option>Backup</option>
				<option>Sync</option>
				<option>Activate/Deactivate</option>
				<option>Snapshot</option>
				<option>Remove</option>
			</optgroup>
		</select>
	</li>
	<li>
		<select>
			<option disabled selected>Action</option>
			<option>Activate</option>
			<option>Deactivate</option>
			<option>Install</option>
			<option>Delete</option>
		</select>
		on
		<select>
			<option disabled selected>Plugin/Theme</option>
			<option>Plugin</option>
			<option>Theme</option>
		</select>

</ul>
</div>
