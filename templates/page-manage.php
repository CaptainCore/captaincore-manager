<?php

/* Template: Manage */

get_header();
$user = wp_get_current_user();
$role_check = in_array( 'administrator', $user->roles);
if ($role_check) {

	add_filter( 'body_class','my_body_classes' );
	function my_body_classes( $classes ) {

	    $classes[] = 'woocommerce-account';
	    return $classes;

	}

	add_action( 'wp_enqueue_scripts', 'my_register_javascript', 100 );

	function my_register_javascript() {
	   wp_deregister_style( 'materialize' );
		 wp_deregister_script( 'materialize' );
	}

	?>

<link href='https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Material+Icons' rel="stylesheet">
<link href="https://unpkg.com/vuetify/dist/vuetify.min.css" rel="stylesheet">
<style>

html {
	font-size: 62.5%;
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

.card .list {
	float:none;
	width:auto;
	margin:0px;
	padding:0px;
}
button {
	padding: 0 16px;
}
span.text-xs-right {
	float:right;
}

.entry-content, .entry-footer, .entry-summary {
	max-width: 1000px;
}
table.table thead tr,
table.table tbody td, table.table tbody th {
	height: auto;
}
</style>
<?php if ( $_SERVER["SERVER_NAME"] == "dev.anchor" ) { ?>
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
"visible": true
},<?php } } ?>];
</script>

<div id="app">
	<v-app>
		<v-content>
			Listing {{ visibleSites }} sites
			<template>
			<v-container fluid>
			<v-layout row>
       <v-flex xs12 sm9>
         <v-select
           :items="site_filters"
					 item-text="title"
					 item-value="name"
           v-model="applied_site_filter"
					 @input="filterSites"
					 item-text="title"
           label="Select Theme and/or Plugin"
					 chips
					 multiple
           autocomplete
         ></v-select>
				 <v-select
				 v-model="applied_site_filter_version"
				 v-for="filter in site_filter_version"
					 :items="filter.versions"
					 :key="filter.name"
					 :label="'Select Version for '+ filter.name"
					 item-text="title"
					 chips
					 multiple
					 autocomplete
				 ></v-select>

       </v-flex>
			 <v-flex xs12 sm3 text-xs-right>
				 <v-btn @click.stop="dialog = true">Bulk Actions</v-btn>
			</v-flex>
			</v-layout>
			</v-container>
			<v-container
        fluid
        style="min-height: 0;"
        grid-list-lg
      >
		  <v-layout row wrap v-for="site in sites" :key="site.id" v-show="site.visible">
		    <v-flex xs12>
		      <v-card class="site">
						<!--<div class="checkbox-selector"></div>-->
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
							 </v-card>
						 </v-expansion-panel-content>
					 </v-expansion-panel>
		      </v-card>
		    </v-flex>
		  </v-layout>
			</v-container>
			<v-dialog v-model="dialog" max-width="500px">
			 <v-card>
				 <v-card-title>
					 Bulk Actions on {{ visibleSites }} sites
				 </v-card-title>
				 <v-card-text>

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
				 </v-card-text>
				 <v-card-actions>
					 <v-btn color="primary" flat @click.stop="dialog=false">Close</v-btn>
				 </v-card-actions>
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

	site["themes"].forEach(function(theme) {
		exists = all_themes.some(function (el) {
			return el.name === theme.name;
		});
		if (!exists) {
			all_themes.push({
				name: theme.name,
				title: theme.title+ " ("+theme.name+")",
				type: 'theme'
			});
		}
	});

	site["plugins"].forEach(function(plugin) {
		exists = all_plugins.some(function (el) {
			return el.name === plugin.name;
		});
		if (!exists) {
			all_plugins.push({
				name: plugin.name,
				title: plugin.title+ " ("+plugin.name+")",
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
		site_filters: all_filters,
		site_filter_version: null,
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

	},
	computed: {
		visibleSites() {
			return this.sites.filter(site => site.visible).length;
		}
	},
	methods: {
		filterSites() {

			// Filter if select has value
			if ( this.applied_site_filter && this.applied_site_filter != "" ) {

				filterby = this.applied_site_filter;
				filter_versions = [];

				// loop through sites and set visible true if found in filter
				this.sites.forEach(function(site) {

					exists = false;

					filterby.forEach(function(filter) {

						theme_exists = site.themes.some(function (el) {
							return el.name === filter;
						});
						plugin_exists = site.plugins.some(function (el) {
							return el.name === filter;
						});
						if (theme_exists || plugin_exists) {
							exists = true;
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

				// Populate versions for select item: site_filter_version
				filterby.forEach(function(filter) {

					var versions = [];

						this.sites.forEach(function(site) {
							site.plugins.filter(plugin => plugin.name == filter).forEach(function(plugin) {
								if (!versions.includes(plugin.version)){
									versions.push(plugin.version);
								}
							});
							site.themes.filter(theme => theme.name == filter).forEach(function(theme) {
								if (!versions.includes(theme.version)){
									versions.push(theme.version);
								}
							});
						});

						filter_versions.push({name: filter, versions: versions });

				});

				this.site_filter_version = filter_versions;

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
