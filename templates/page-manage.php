<?php

/* Template: Manage */

add_action( 'wp_enqueue_scripts', 'my_register_javascript', 100 );

function my_register_javascript() {
   wp_deregister_style( 'materialize' );
	 wp_deregister_script( 'materialize' );
}

get_header();
$user = wp_get_current_user();
$role_check = in_array( 'administrator', $user->roles);
if ($role_check) { ?>

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

.card .list {
	float:none;
	width:auto;
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

<script type="text/javascript">

	ajaxurl = "/wp-admin/admin-ajax.php";

	function sort_li(a, b){
	    var va = jQuery(a).data('id').toString().charCodeAt(0);
	    var vb = jQuery(b).data('id').toString().charCodeAt(0);
	    if (va < 'a'.charCodeAt(0)) va += 100; // Add weight if it's a number
	    if (vb < 'a'.charCodeAt(0)) vb += 100; // Add weight if it's a number
	    return vb < va ? 1 : -1;
	}


	function isEmail(email) {
  	var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
  	return regex.test(email);

	}
</script>
<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
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
           v-model="applied_site_filter"
					 @input="filterSites"
					 item-text="title"
           label="Select"
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
							 <div slot="header"><strong>{{ site.name }}</strong> <span class="text-xs-right">plugins {{ site.plugins.length }} themes {{ site.themes.length }} version {{ site.core }}</span></div>
							 <v-card>

								 <v-data-table
								    :headers="headers"
								    :items="site.plugins"
								    class="elevation-1"
										hide-actions
										style="margin: 0 2% 2% 2%;"
								  >
								    <template slot="items" slot-scope="props">
								      <td>{{ props.item.title }}</td>
								      <td>{{ props.item.name }}</td>
								      <td>{{ props.item.version }}</td>
								      <td>{{ props.item.status }}</td>
											<td class="justify-center px-0">
							          <v-btn icon class="mx-0" @click="editItem(props.item)">
							            <v-icon color="teal">edit</v-icon>
							          </v-btn>
							          <v-btn icon class="mx-0" @click="deleteItem(props.item)">
							            <v-icon color="pink">delete</v-icon>
							          </v-btn>
							        </td>
								    </template>
								  </v-data-table>
									<v-data-table
 								    :headers="headers"
 								    :items="site.themes"
 								    class="elevation-1"
										hide-actions
 								  >
 								    <template slot="items" slot-scope="props">
 								      <td>{{ props.item.title }}</td>
 								      <td>{{ props.item.name }}</td>
 								      <td>{{ props.item.version }}</td>
 								      <td>{{ props.item.status }}</td>
 											<td class="justify-center layout px-0">
 							          <v-btn icon class="mx-0" @click="editItem(props.item)">
 							            <v-icon color="teal">edit</v-icon>
 							          </v-btn>
 							          <v-btn icon class="mx-0" @click="deleteItem(props.item)">
 							            <v-icon color="pink">delete</v-icon>
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
all_filters = [];
all_filters.push({ header: 'Themes' });
sites.forEach(function(site) {

	site["themes"].forEach(function(theme) {
		exists = all_filters.some(function (el) {
			return el.name === theme.name;
		});
		if (!exists) {
			all_filters.push({
				name: theme.name,
				title: theme.title,
				type: 'theme'
			});
		}
	});

});

all_filters.push({ header: 'Plugins' });
sites.forEach(function(site) {

	site["plugins"].forEach(function(plugin) {
		exists = all_filters.some(function (el) {
			return el.name === plugin.name;
		});
		if (!exists) {
			all_filters.push({
				name: plugin.name,
				title: plugin.title,
				type: 'plugin'
			});
		}
	});

});

new Vue({
	el: '#app',
	data: {
		dialog: false,
		site_filters: all_filters,
  	sites: sites,
		headers: [
			 {
				 text: 'Name',
				 align: 'left',
				 sortable: true,
				 value: 'name'
			 },
			 { text: 'Slug', value: 'slug' },
			 { text: 'Version', value: 'version' },
			 { text: 'Status', value: 'status' },
			 { text: 'Actions', value: 'actions' }
		 ],
		 applied_site_filter: null
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

				// loop through sites and set visible true if found in filter
				this.sites.forEach(function(site) {

					filterby.forEach(function(filter) {

						exists = site[filter.type+"s"].some(function (el) {
							return el.name === filter.name;
						});

					});

					if (exists) {
						// Theme exists so set to visible
						site.visible = true;
					} else {
						// Theme doesn't exists so hide
						site.visible = false;
					}

				});
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
<?php endif;

} ?>

</div>
</div>
</div>
</article>
</main>
</div>
<?php get_footer(); ?>
