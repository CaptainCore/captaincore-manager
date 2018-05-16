<?php

/* Template: Manage */

add_action( 'wp_enqueue_scripts', 'my_register_javascript', 100 );

function my_register_javascript() {
	 wp_deregister_style( 'materialize' );
	 wp_deregister_script( 'materialize' );
}

get_header();
$user       = wp_get_current_user();
$role_check = in_array( 'administrator', $user->roles );
if ( $role_check ) {

	add_filter( 'body_class', 'my_body_classes' );
	function my_body_classes( $classes ) {

		$classes[] = 'woocommerce-account';
		return $classes;

	}

?>

<link href='https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Material+Icons' rel="stylesheet">
<link href="https://unpkg.com/vuetify/dist/vuetify.min.css" rel="stylesheet">
<style>

html {
	font-size: 62.5%;
}

[v-cloak] > * {
  display:none;
}
[v-cloak]::before {
  content: "Loading...";
  display: block;
  position: relative;
  left: 0%;
  top: 0%;
	max-width: 1000px;
	margin:auto;
	padding-bottom: 10em;
}
.application.theme--light {
	background-color: #fff;
}

.application .theme--light.btn:not(.btn--icon):not(.btn--flat), .theme--light .btn:not(.btn--icon):not(.btn--flat) {
	padding: 0px;
}

.secondary {
	background: transparent !important;
}

table {
	margin: 0px;
}

.menu__content--select .card {
	margin:0px;
	padding:0px;
}

.card  {
	margin:0px;
	padding:0px;
}
.card .list {
	float:none;
	width:auto;
	margin:0px;
	padding:0px;
}
button {
	padding: 0 16px;
}
button.btn--icon {
	padding:0px;
}
.application .theme--dark.btn, .theme--dark .btn {
	color: #fff !important;
}
span.text-xs-right {
	float:right;
}
.input-group.input-group--selection-controls.switch .input-group--selection-controls__container {
	margin: auto;
	margin-top: 1.5em;
}

.entry-content, .entry-footer, .entry-summary {
	max-width: 1000px;
}
table.table thead tr,
table.table tbody td, table.table tbody th {
	height: auto;
}
</style>
<?php if ( $_SERVER['SERVER_NAME'] == 'dev.anchor' ) { ?>
<script src="https://unpkg.com/vue/dist/vue.js"></script>
<?php } else { ?>
<script src="https://unpkg.com/vue/dist/vue.min.js"></script>
<?php } ?>
<script src="https://unpkg.com/vuetify/dist/vuetify.js"></script>

<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

		<header class="main entry-header " style="">
			<h1 class="entry-title"><?php the_title(); ?></h1>

			<span class="overlay"></span>
		</header>

			<div class="body-wrap">
			<div class="entry-content">
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
<script>

ajaxurl = "/wp-admin/admin-ajax.php";

var sites = [<?php foreach( $websites as $website ) {
	$plugins = get_field( "plugins", $website->ID);
	$themes = get_field( "themes", $website->ID);
	if ($plugins && $themes) {
	?>
{
"id": <?php echo $website->ID; ?>,
"name": "<?php echo get_the_title($website->ID); ?>",
<?php if($plugins) { ?>"plugins": <?php echo $plugins; ?>,<?php } ?>
<?php if($themes) { ?>"themes": <?php echo $themes; ?>,<?php } ?>
"core": "<?php echo get_field( "core", $website->ID ); ?>",
"visible": true,
"selected": false
},<?php } } ?>];
</script>
<!--
<?php foreach( $websites as $website ) {
	$plugins = get_field( "plugins", $website->ID);
	$themes = get_field( "themes", $website->ID);
	if (!$plugins || !$themes) {
		echo $website->ID . " " . $website->post_title . " \r";
	}
} ?>
-->
<div id="app" v-cloak>
	<v-app>
		<v-content>
			Listing {{ visibleSites }} sites
			<template>
			<v-container fluid>
			<v-layout row>
       <v-flex xs12>
         <v-select
           :items="site_filters"
					 item-text="search"
					 item-value="name"
           v-model="applied_site_filter"
					 @input="filterSites"
					 item-text="title"
           label="Select Theme and/or Plugin"
					 chips
					 multiple
           autocomplete
					 deletable-chips
         >
				 <template slot="selection" slot-scope="data">
					<v-chip
						close
						@input="data.parent.selectItem(data.item)"
						:selected="data.selected"
						class="chip--select-multi"
						:key="JSON.stringify(data.item)"
					>
						<strong>{{ data.item.title }}</strong>&nbsp;<span>({{ data.item.name }})</span>
					</v-chip>
				</template>
				<template slot="item" slot-scope="data">
					 <strong>{{ data.item.title }}</strong>&nbsp;<span>({{ data.item.name }})</span>
				</template>
			 	</v-select>
			</v-flex>
		</v-layout>
		<v-layout row>
			<v-flex xs5>
				 <v-select
				 v-model="applied_site_filter_version"
				 v-for="filter in site_filter_version"
					 :items="filter.versions"
					 :key="filter.name"
					 :label="'Select Version for '+ filter.name"
					 @input="filterSites"
					 item-text="title"
					 chips
					 multiple
					 autocomplete
				 >
				 <template slot="selection" slot-scope="data">
					 <v-chip
						 close
						 @input="data.parent.selectItem(data.item)"
						 :selected="data.selected"
						 class="chip--select-multi"
						 :key="JSON.stringify(data.item)"
					 >
						 {{ data.item.name }} ({{ data.item.count }})
					 </v-chip>
				 </template>
				 <template slot="item" slot-scope="data">
						<strong>{{ data.item.name }}</strong>&nbsp;<span>({{ data.item.count }})</span>
				 </template>
			 	</v-select>
			</v-flex>
			<v-flex xs2>
			</v-flex>
				<v-flex xs5>
				<v-select
				v-model="applied_site_filter_status"
				v-for="filter in site_filter_status"
					:items="filter.statuses"
					:key="filter.name"
					:label="'Select Status for '+ filter.name"
					@input="filterSites"
					item-text="title"
					chips
					multiple
					autocomplete
				>
				<template slot="selection" slot-scope="data">
					<v-chip
						close
						@input="data.parent.selectItem(data.item)"
						:selected="data.selected"
						class="chip--select-multi"
						:key="JSON.stringify(data.item)"
					>
						{{ data.item.name }} ({{ data.item.count }})
					</v-chip>
				</template>
				<template slot="item" slot-scope="data">
					 <strong>{{ data.item.name }}</strong>&nbsp;<span>({{ data.item.count }})</span>
				</template>
			 </v-select>
       </v-flex>
			</v-layout>
			</v-container>
			<v-container
        fluid
        style="min-height: 0;"
        grid-list-lg
      >
			<v-layout row>
			<v-flex xs12 sm9 text-xs-right>
			<v-select
          :items="select_site_options"
					v-model="site_selected"
					@input="selectSites"
          label="Select"
					chips
        ></v-select>
				</v-flex>
				<v-flex xs12 sm3 text-xs-right>
					<v-btn @click.stop="dialog = true">Bulk Actions on {{ selectedSites }} sites</v-btn>
				</v-flex>
			</v-layout>
		  <v-layout row wrap v-for="site in sites" :key="site.id" v-show="site.visible">
				<v-flex xs1>
					<v-switch v-model="site.selected" @change="site_selected = null"></v-switch>
				</v-flex>
		    <v-flex xs11>
		      <v-card class="site">
						<v-expansion-panel>
						 <v-expansion-panel-content lazy v-for="(item,i) in 1" :key="i">
							 <div slot="header"><strong>{{ site.name }}</strong> <span class="text-xs-right">{{ site.plugins.length }} Plugins {{ site.themes.length }} Themes - WordPress {{ site.core }}</span></div>
							 <v-card>
								  <v-toolbar-title class="caption" style="margin:0 0 0 2%;">{{ site.themes.length }} Themes</v-toolbar-title>
									<v-data-table
 								    :headers="headers"
 								    :items="site.themes"
 								    class="elevation-1"
										style="margin: 0 2% 2% 2%;"
										hide-actions
 								  >
 								    <template slot="items" slot-scope="props">
 								      <td>{{ props.item.title }}</td>
 								      <td>{{ props.item.name }}</td>
 								      <td>{{ props.item.version }}</td>
 								      <td>{{ props.item.status }}</td>
 											<td class="text-xs-center px-0">
 							          <v-btn small icon class="mx-0" @click="editItem(props.item)">
 							            <v-icon small color="teal">edit</v-icon>
 							          </v-btn>
 							          <v-btn icon class="mx-0" @click="deleteItem(props.item)">
 							            <v-icon small color="pink">delete</v-icon>
 							          </v-btn>
 							        </td>
 								    </template>
 								  </v-data-table>
									<v-toolbar-title class="caption" style="margin:0 0 0 2%;">{{ site.plugins.length }} Plugins</v-toolbar-title>
									<v-data-table
 								    :headers="headers"
 								    :items="site.plugins"
 								    class="elevation-1"
 										style="margin: 0 2% 2% 2%;"
 										hide-actions
 								  >
 								    <template slot="items" slot-scope="props">
 								      <td>{{ props.item.title }}</td>
 								      <td>{{ props.item.name }}</td>
 								      <td>{{ props.item.version }}</td>
 								      <td>{{ props.item.status }}</td>
 											<td class="text-xs-center px-0">
 							          <v-btn icon small class="mx-0" @click="editItem(props.item)">
 							            <v-icon small color="teal">edit</v-icon>
 							          </v-btn>
 							          <v-btn icon small class="mx-0" @click="deleteItem(props.item)">
 							            <v-icon small color="pink">delete</v-icon>
 							          </v-btn>
 							        </td>
 								    </template>
 								  </v-data-table>
									<p></p>
							 </v-card>
						 </v-expansion-panel-content>
					 </v-expansion-panel>
		      </v-card>
		    </v-flex>
		  </v-layout>
			</v-container>
			<v-snackbar
      :timeout="3000"
    	:multi-line="true"
      v-model="bulkaction_response_snackbar"
    >
      {{ bulkaction_response }}
      <v-btn dark flat @click.native="snackbar = false">Close</v-btn>
    </v-snackbar>
			<v-dialog
				fullscreen
        transition="dialog-bottom-transition"
        :overlay="false"
        scrollable
				v-model="dialog">
			 <v-card tile>
				 <v-toolbar card dark color="primary" style="margin-top: 32px">
            <v-btn icon @click.native="dialog = false" dark>
              <v-icon>close</v-icon>
            </v-btn>
            <v-toolbar-title>Bulk Actions on {{ selectedSites }} sites</v-toolbar-title>
            <v-spacer></v-spacer>
          </v-toolbar>
				<v-layout row>
       	<v-flex xs12 style="max-width: 800px;" mx-auto>
				 <v-card-text>
					 <ul>
						 <li>
							 Run a
							 <v-select
								 :items="bulk_actions"
								 item-text="name"
								 item-value="value"
								 v-model="select_bulk_action"
								 label="Script/Command"
								 @input="argumentsForActions"
								 single-line
								 chips
								 multiple
			           autocomplete
							 ></v-select>
							 <v-text-field
				          name="input-1"
									v-model="argument.input"
				          v-for="argument in select_bulk_action_arguments"
									:label="argument.name"
				        ></v-text-field>
						 </li>

					 </ul>
				 </v-card-text>
				 <v-card-actions>
					  <v-btn @click="bulkactionSubmit">submit</v-btn>
					 <v-btn color="primary" flat @click.stop="dialog=false">Close</v-btn>
				 </v-card-actions>
		 	</v-flex>
			</v-layout>
			 </v-card>
		 </v-dialog>
		</template>
		</v-content>
	</v-app>
</div>
<script>

all_themes = [];
all_plugins = [];
sites.forEach(function(site) {

	site.themes.forEach(function(theme) {
		exists = all_themes.some(function (el) {
			return el.name === theme.name;
		});
		if (!exists) {
			all_themes.push({
				name: theme.name,
				title: theme.title,
				type: 'theme'
			});
		}
	});

	site.plugins.forEach(function(plugin) {
		exists = all_plugins.some(function (el) {
			return el.name === plugin.name;
		});
		if (!exists) {
			all_plugins.push({
				name: plugin.name,
				title: plugin.title,
				search: plugin.title + " ("+ plugin.name +")",
				type: 'plugin'
			});
		}
	});

});

all_themes.sort((a, b) => a.name.localeCompare(b.name));
all_plugins.sort((a, b) => a.name.localeCompare(b.name));

all_filters = [{ header: 'Themes' }];
all_filters = all_filters.concat(all_themes);
all_filters.push({ header: 'Plugins' })
all_filters = all_filters.concat(all_plugins);

new Vue({
	el: '#app',
	data: {
		dialog: false,
		site_selected: null,
		site_filters: all_filters,
		site_filter_version: null,
		site_filter_status: null,
  	sites: sites,
		headers: [
			 { text: 'Name', value: 'name' },
			 { text: 'Slug', value: 'slug' },
			 { text: 'Version', value: 'version' },
			 { text: 'Status', value: 'status', width: "100px" },
			 { text: 'Actions', value: 'actions', width: "90px", sortable: false, }
		 ],
		 applied_site_filter: null,
		 applied_site_filter_version: null,
		 applied_site_filter_status: null,
		 select_site_options: [
			 { text: 'All', value: 'all' },
			 { text: 'Visible', value: 'visible' },
			 { text: 'None', value: 'none' }
		 ],
		 select_bulk_action: null,
		 bulk_actions: [
			 { header: "Script" },
			 { name: "Migrate", value: "migrate", arguments: [
					{ name: "Url", value: "url" },
					{ name: "Skip url override", value: "skip-url-override" }
				]},
			 { name: "Apply SSL", value: "applyssl"  },
			 { name: "Apply SSL with www", value: "applysslwithwww" },
			 { name: "Launch", value: "launch" },
			 { header: "Command" },
			 { name: "Backup", value: "backup" },
			 { name: "SSH", value: "ssh", arguments: [
					{ name: "Commands", value: "command" },
					{ name: "Script", value: "script" }
				]},
			 { name: "Sync", value: "sync" },
			 { name: "Activate", value: "activate" },
			 { name: "Deactivate", value: "deactivate" },
			 { name: "Snapshot", value: "snapshot" },
			 { name: "Remove", value: "remove" }
		 ],
		 select_bulk_action_arguments: null,
		 bulkaction_response: null,
		 bulkaction_response_snackbar: false

	},
	computed: {
		visibleSites() {
			return this.sites.filter(site => site.visible).length;
		},
		selectedSites() {
			return this.sites.filter(site => site.selected).length;
		}
	},
	methods: {
		argumentsForActions() {
			arguments = [];
			this.select_bulk_action.forEach(action => {
				this.bulk_actions.filter(bulk_action => bulk_action.value == action).forEach(filtered_action => {
					if ( filtered_action.arguments ) {
						filtered_action.arguments.forEach(argument => arguments.push({ name: argument.name, value: argument.value, command: action }) );
					}
				});
			});
			this.select_bulk_action_arguments = arguments;
		},
		bulkactionSubmit() {

			var data = {
	  		'action': 'captaincore_install',
	  		'post_id': this.sites.filter( site => site.selected ).map( site => site.id ),
	      'command': "manage",
				'value': this.select_bulk_action,
				'arguments': this.select_bulk_action_arguments
	  	};

			var self = this;

  		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
	  	jQuery.post(ajaxurl, data, function(response) {
	      self.bulkaction_response = response;
				self.bulkaction_response_snackbar = true;
				self.dialog = false;
	  	});

		},
		selectSites() {
			if (this.site_selected == "all") {
				this.sites.forEach(site => site.selected = true );
			}
			if (this.site_selected == "visible") {
				this.sites.forEach(site => site.selected = false );
				this.sites.filter(site => site.visible ).forEach(site => site.selected = true );
			}
			if (this.site_selected == "none") {
				this.sites.forEach(site => site.selected = false );
			}
		},
		filterSites() {
			// Filter if select has value
			if ( this.applied_site_filter && this.applied_site_filter != "" ) {

				filterby = this.applied_site_filter;
				filterbyversions = this.applied_site_filter_version;
				filterbystatuses = this.applied_site_filter_status;
				filter_versions = [];
				filter_statuses = [];
				versions = [];
				statuses = [];

				if ( this.applied_site_filter_version && this.applied_site_filter_version != "" ) {

					// Find all themes/plugins which have selcted version
					this.applied_site_filter_version.forEach(filter => {
						if(!versions.includes(filter.slug)) {
							versions.push(filter.slug);
						}
					});

				}

				if ( this.applied_site_filter_status && this.applied_site_filter_status != "" ) {

					// Find all themes/plugins which have selcted version
					this.applied_site_filter_status.forEach(filter => {
						if(!statuses.includes(filter.slug)) {
							statuses.push(filter.slug);
						}
					});

				}

				// loop through sites and set visible true if found in filter
				this.sites.forEach(function(site) {

					exists = false;

					filterby.forEach(function(filter) {

						// Handle filtering items with versions and statuses
						if ( versions.includes(filter) && statuses.includes(filter) ) {
							slug = filter;
							plugin_exists = false;
							theme_exists = false;
							// Apply versions specific for this theme/plugin
							filterbyversions.filter(item => item.slug == slug).forEach(version => {

								if ( theme_exists || plugin_exists ) {
									exists = true;
								} else {
									plugin_exists = site.plugins.some(el => el.name === slug && el.version === version.name);
									theme_exists = site.themes.some(el => el.name === slug && el.version === version.name);
								}

							});

							// Apply status specific for this theme/plugin
							filterbystatuses.filter(item => item.slug == slug).forEach(status => {

								if ( theme_exists || plugin_exists ) {
									exists = true;
								} else {
									plugin_exists = site.plugins.some(el => el.name === slug && el.status === status.name);
									theme_exists = site.themes.some(el => el.name === slug && el.status === status.name);
								}

							});

							if (theme_exists || plugin_exists) {
								exists = true;
							}

						// Handle filtering items with versions
						} else if ( versions.includes(filter) ) {

							slug = filter;
							plugin_exists = false;
							theme_exists = false;
							// Apply versions specific for this theme/plugin
							filterbyversions.filter(item => item.slug == slug).forEach(version => {

								if ( theme_exists || plugin_exists ) {
									exists = true;
								} else {
									plugin_exists = site.plugins.some(el => el.name === slug && el.version === version.name);
									theme_exists = site.themes.some(el => el.name === slug && el.version === version.name);
								}

							});

							if (theme_exists || plugin_exists) {
								exists = true;
							}

						// Handle filtering items with statuses
						} else if ( statuses.includes(filter) ) {

							slug = filter;
							plugin_exists = false;
							theme_exists = false;

							// Apply status specific for this theme/plugin
							filterbystatuses.filter(item => item.slug == slug).forEach(status => {

								if ( theme_exists || plugin_exists ) {
									exists = true;
								} else {
									plugin_exists = site.plugins.some(el => el.name === slug && el.status === status.name);
									theme_exists = site.themes.some(el => el.name === slug && el.status === status.name);
								}

							});

							if (theme_exists || plugin_exists) {
								exists = true;
							}

						// Handle filtering of the themes/plugins
						} else {

							theme_exists = site.themes.some(function (el) {
								return el.name === filter;
							});
							plugin_exists = site.plugins.some(function (el) {
								return el.name === filter;
							});
							if (theme_exists || plugin_exists) {
								exists = true;
							}

						}

					});

					if (exists) {
						// Theme exists so set to visible
						site.visible = true;
					} else {
						// Theme doesn't exists so hide
						site.visible = false;
					}

				});

				// Populate versions for select item
				filterby.forEach(function(filter) {

					var versions = [];

					this.sites.forEach(function(site) {

						site.plugins.filter(item => item.name == filter).forEach(function(plugin) {
							version_count = versions.filter(item => item.name == plugin.version).length;
							if ( version_count == 0 ) {
								versions.push({ name: plugin.version, count: 1, slug: plugin.name });
							} else {
								versions.find(function (item) { return item.name === plugin.version; }).count++;
							}
						});

						site.themes.filter(item => item.name == filter).forEach(function(theme) {
							version_count = versions.filter(item => item.name == theme.version).length;
							if ( version_count == 0 ) {
								versions.push({ name: theme.version, count: 1, slug: theme.name });
							} else {
								versions.find(function (item) { return item.name === theme.version; }).count++;
							}
						});

					});

					filter_versions.push({name: filter, versions: versions });

				});

				this.site_filter_version = filter_versions;

				// Populate statuses for select item
				filterby.forEach(function(filter) {

					var statuses = [];

					this.sites.forEach(function(site) {

						site.plugins.filter(item => item.name == filter).forEach(function(plugin) {
							status_count = statuses.filter(item => item.name == plugin.status).length;
							if ( status_count == 0 ) {
								statuses.push({ name: plugin.status, count: 1, slug: plugin.name });
							} else {
								statuses.find(function (item) { return item.name === plugin.status; }).count++;
							}
						});

						site.themes.filter(item => item.name == filter).forEach(function(theme) {
							status_count = statuses.filter(item => item.name == theme.status).length;
							if ( status_count == 0 ) {
								statuses.push({ name: theme.status, count: 1, slug: theme.name });
							} else {
								statuses.find(function (item) { return item.name === theme.status; }).count++;
							}
						});

					});

					filter_statuses.push({name: filter, statuses: statuses });

				});

				this.site_filter_status = filter_statuses;

			} else {
				this.sites.forEach(function(site) {
					site.visible = true;
				});
			}

		}
	}
});

</script>

</div>
<?php endif; ?>
</div>
</div>
</div>
</article>
</main>
</div>

<?php } else { ?>

	<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">

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
					<h1 class="entry-title"><h1 class="page-title"><?php _e( 'Oops! That page can&rsquo;t be found.', 'swell' ); ?></h1>
					<span class="overlay"></span>
				</header><!-- .entry-header -->

		<div class="body-wrap">
		<div class="entry-content">
			<p><?php _e( 'The page you are looking for could not be found. Try a different address, or search using the form below.', 'swell' ); ?></p>
			<?php get_search_form(); ?>
		</div>
		</div>
		</section><!-- .error-404 -->

	</main><!-- #main -->
</div><!-- #primary -->

<?php } ?>


<?php get_footer(); ?>
