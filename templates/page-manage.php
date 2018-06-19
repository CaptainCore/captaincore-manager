<?php

/* Template: Manage */

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

<!--<link href='https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Material+Icons' rel="stylesheet">-->
<link href="https://unpkg.com/vuetify/dist/vuetify.min.css" rel="stylesheet">
<style>

html {
	font-size: 62.5%;
}

.application .theme--dark.icon, .theme--dark .icon {
	font-size: 1em;
	padding-left: 0.3em;
}

.dialog__content__active {
	z-index: 999999 !important;
}

.expansion-panel__body .card.bordered {
	margin: 2em;
	padding: 0px;
	box-shadow: 0 2px 1px -1px rgba(0,0,0,.2), 0 1px 1px 0 rgba(0,0,0,.14), 0 1px 3px 0 rgba(0,0,0,.12);
}
.expansion-panel__body .card.bordered .pass-mask {
	display: inline-block;
}
.expansion-panel__body .card.bordered .pass-reveal {
	display: none;
}
.expansion-panel__body .card.bordered:hover .pass-mask {
	display: none;
}
.expansion-panel__body .card.bordered:hover .pass-reveal {
	display: inline-block;
}

.static.badge {
	position: fixed;
  top: 23%;
  right: 0px;
  background: white;
  z-index: 99999;
  padding: 1em 1em .5em 1em;
  box-shadow: 0 3px 1px -2px rgba(0,0,0,.2), 0 2px 2px 0 rgba(0,0,0,.14), 0 1px 5px 0 rgba(0,0,0,.12);
}

.application .theme--light.input-group input, .application .theme--light.input-group textarea, .theme--light .input-group input, .theme--light .input-group textarea {
	background: none;
	border: none;
}

.content-area ul.pagination {
	display: inline-flex;
	margin: 0px;
}

.alignright.input-group {
	width: auto;
}

a.tabs__item:hover {
	color:inherit;
}

.pagination span.pagination__more {
	margin: .3rem;
	border: 0px;
	padding: 0px;
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

table.table .input-group--selection-controls {
	top: 10px;
	position: relative;
}

table.table .input-group.input-group--selection-controls.switch .input-group--selection-controls__container {
	margin:0px;
}

.application .theme--light.pagination__item--active, body .theme--light button.pagination__item--active {
	color: #fff !important;
}

body button.pagination__item:hover {
    box-shadow: 0 3px 1px -2px rgba(0,0,0,.2), 0 2px 2px 0 rgba(0,0,0,.14), 0 1px 5px 0 rgba(0,0,0,.12);
	}

.entry-content, .entry-footer, .entry-summary {
	max-width: 1000px;
}
table.table thead tr,
table.table tbody td, table.table tbody th {
	height: auto;
}
</style>
<?php if ( $_SERVER['SERVER_NAME'] == 'anchor.test' ) { ?>
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
		'post_type'      => 'captcore_website',
		'posts_per_page' => '-1',
		'order'          => 'asc',
		'orderby'        => 'title',
		'meta_query'     => array(
			array(
				'key'     => 'status',
				'value'   => 'closed',
				'compare' => '!=',
			),
		),
	);

// Loads websites
$websites = get_posts( $arguments );

if ( $websites ) :
?>
<script>

ajaxurl = "/wp-admin/admin-ajax.php";

var pretty_timestamp_options = {
    weekday: "long", year: "numeric", month: "short",
    day: "numeric", hour: "2-digit", minute: "2-digit"
};
// Example: new Date("2018-06-18 19:44:47").toLocaleTimeString("en-us", options);
// Returns: "Monday, Jun 18, 2018, 7:44 PM"

var sites = [
<?php
$count = 0;
foreach ( $websites as $website ) {
	$plugins = get_field( 'plugins', $website->ID );
	$themes  = get_field( 'themes', $website->ID );
	if ( $plugins && $themes ) {
	?>
{
"id": <?php echo $website->ID; ?>,
"name": "<?php echo get_the_title( $website->ID ); ?>",
<?php
if ( $plugins ) {
?>
"plugins": <?php echo $plugins; ?>,<?php } ?>
<?php
if ( $themes ) {
?>
"themes": <?php echo $themes; ?>,<?php } ?>
"core": "<?php echo get_field( 'core', $website->ID ); ?>",
"keys": [
	{"link":"http://<?php echo get_the_title( $website->ID ); ?>","environment": "Production", "address": "<?php the_field('address', $website->ID); ?>","username":"<?php the_field('username', $website->ID); ?>","password":"<?php the_field('password', $website->ID); ?>","protocol":"<?php the_field('protocol', $website->ID); ?>","port":"<?php the_field('port', $website->ID); ?>"},
	<?php if (get_field('address_staging', $website->ID)) { ?>{"link":"<?php if (strpos( get_field('address_staging', $website->ID), ".kinsta.com") ) { echo "https://staging-". get_field('site_staging', $website->ID).".kinsta.com"; } else { echo "https://". get_field('site_staging', $website->ID). ".staging.wpengine.com"; } ?>","environment": "Staging", "address": "<?php the_field('address_staging', $website->ID); ?>","username":"<?php the_field('username_staging', $website->ID); ?>","password":"<?php the_field('password_staging', $website->ID); ?>","protocol":"<?php the_field('protocol_staging', $website->ID); ?>","port":"<?php the_field('port_staging', $website->ID); ?>"},<?php } ?>
],
"update_logs": [],
"loading_themes": false,
"loading_plugins": false,
<?php
if ( $count <= 49 ) {
?>
"visible": true,
<?php } else { ?>
"visible": false,
<?php } ?>
"filtered": true,
"selected": false
},
<?php
	$count++;
	}
}
?>
];
</script>
<div id="app" v-cloak>
	<v-app>
		<v-content>
		<v-badge overlap left class="static" v-if="runningJobs">
			<span slot="badge">{{ runningJobs }}</span>
			<a @click.stop="view_jobs = true"><v-icon large color="grey lighten-1">fas fa-cogs</v-icon></a>
			<template>
			  <v-progress-linear :indeterminate="true"></v-progress-linear>
			</template>
		</v-badge>
		<v-dialog
			v-model="view_jobs"
			fullscreen
			hide-overlay
			transition="dialog-bottom-transition"
			scrollable
		>
        <v-card tile>
          <v-toolbar card dark color="primary">
            <v-btn icon dark @click.native="view_jobs = false">
              <v-icon>close</v-icon>
            </v-btn>
            <v-toolbar-title>Running Jobs</v-toolbar-title>
            <v-spacer></v-spacer>
          </v-toolbar>
          <v-card-text>

            <v-list three-line subheader>

              <v-list-tile avatar v-for="job in jobs.slice().reverse()">
                <v-list-tile-content>
                  <v-list-tile-title>{{ job.description }}</v-list-tile-title>
									<v-chip v-if="job.status == 'done'" outline label color="green">Sucess</v-chip>
									<template v-else>
										<div style="width:200px;">
									  <v-progress-linear :indeterminate="true"></v-progress-linear>
										</div>
									</template>

                </v-list-tile-content>
              </v-list-tile>

            </v-list>
            <v-divider></v-divider>

          </v-card-text>

          <div style="flex: 1 1 auto;"></div>
        </v-card>
      </v-dialog>

			<v-container fluid>
			<v-layout row wrap>
      <v-flex xs4>
				Sites per page <v-select
	          :items='[50,100,250]'
	          v-model="items_per_page"
	          label=""
						dense
						@change="paginationUpdate( page )"
						style="display:inline-table;width:100px;"
						width="80"
	        ></v-select>
			</v-flex>
			<v-flex xs4 text-md-center>
				<v-card-text>{{ showingSitesBegin }}-{{ showingSitesEnd }} sites of {{ filteredSites }}</v-card-text>
			</v-flex>
			<v-flex xs4 text-md-right>
				<v-switch inline-block v-model="advanced_filter" class="alignright"></v-switch>
				<v-card-title v-if="advanced_filter == false" class="alignright caption" style="float:right;padding: 16px 0px;">Basic Filter</v-card-title>
				<v-card-title v-if="advanced_filter == true" class="alignright caption" style="float:right;padding: 16px 0px;">Advanced Filter</v-card-title>
			</v-flex>
			</v-layout>
			</v-container>
			<template>
			<v-container fluid v-if="advanced_filter == true">
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
			<v-layout row v-if="advanced_filter == true">
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
			<v-text-field
				v-model="search"
				label="Search sites by name"
				light
				@input="filterSites"
				></v-text-field>
			<template>
				<v-container>
				<v-layout justify-center>
				<div class="text-xs-center">
					<v-pagination v-if="Math.ceil(filteredSites / items_per_page) > 1" :length="Math.ceil(filteredSites / items_per_page)" v-model="page" @input="paginationUpdate( page )" :total-visible="7"></v-pagination>
				</div>
				</v-layout>
				</v-container>
			</template>
		  <v-layout row wrap v-for="site in sites" :key="site.id" v-show="site.visible">
				<v-flex xs1 v-if="advanced_filter == true">
					<v-switch v-model="site.selected" @change="site_selected = null"></v-switch>
				</v-flex>
			<v-flex v-bind:class="{ xs11: advanced_filter }">
			  <v-card class="site">
						<v-expansion-panel>
						 <v-expansion-panel-content lazy v-for="(item,i) in 1" :key="i">
							 <div slot="header"><strong>{{ site.name }}</strong> <span class="text-xs-right">{{ site.plugins.length }} Plugins {{ site.themes.length }} Themes - WordPress {{ site.core }}</span></div>
							 <v-tabs color="blue darken-3" dark>
	  <v-tab :key="1" ripple>
		Keys <v-icon>fas fa-key</v-icon>
	  </v-tab>
		<v-tab :key="2" ripple>
		Themes <v-icon>fas fa-paint-brush</v-icon>
	  </v-tab>
		<v-tab :key="3" ripple>
		Plugins <v-icon>fas fa-plug</v-icon>
	  </v-tab>
		<v-tab :key="4" ripple @click="fetchUpdateLogs( site.id )">
		Logs <v-icon>fas fa-book-open</v-icon>
		</v-tab>
		<v-tab-item :key="1">
			<v-card v-for="key in site.keys" class="bordered">
			<v-card-title>
				<div>
					<v-btn
						depressed
						disabled
						style="background-color: rgb(229, 229, 229)!important;color: #000 !important;left: -11px;top: -5px;"
								>{{ key.environment }} Environment</v-btn>
					<div>	<h3 class="headline mb-0"><a :href="key.link" target="_blank">{{ key.link }}</a></h3></div>
					<div><span class="caption">Address</span> {{ key.address }}</div>
					<div><span class="caption">Username</span> {{ key.username }}</div>
					<div><span class="caption">Password</span> <div class="pass-mask">##########</div><div class="pass-reveal">{{ key.password }}</div></div>
					<div><span class="caption">Protocol</span> {{ key.protocol }}</div>
					<div><span class="caption">Port</span> {{ key.port }}</div>
				 </div>
			 </v-card-title>
		 </v-card>
		</v-tab-item>
		<v-tab-item :key="2">
			<v-card>
				<v-toolbar-title class="caption" style="margin:2% 0 0 2%;">{{ site.themes.length }} Themes</v-toolbar-title>
					<v-data-table
						:headers="headers"
						:items="site.themes"
						class="elevation-1"
						style="margin: 0 2% 2% 2%;"
						:loading="site.loading_themes"
						hide-actions
					>
						 <template slot="items" slot-scope="props">
							 <td>{{ props.item.title }}</td>
							 <td>{{ props.item.name }}</td>
							 <td>{{ props.item.version }}</td>
							 <td>
								 <div v-if="props.item.status === 'inactive' || props.item.status === 'parent' || props.item.status === 'child'">
									<v-switch left :label="props.item.status" v-model="props.item.status" false-value="inactive" true-value="active" @change="activateTheme(props.item.name, site.id)"></v-switch>
 								</div>
 								<div v-else>
 									{{ props.item.status }}
 								</div>
							 </td>
							 <td class="text-xs-center px-0">
								 <v-btn icon class="mx-0" @click="deleteTheme(props.item.name, site.id)">
									 <v-icon small color="pink">delete</v-icon>
								 </v-btn>
							 </td>
						 </template>
					 </v-data-table>
					 <p></p>
				</v-card>
	  </v-tab-item>
		<v-tab-item :key="3">
			<v-card>
					 <v-toolbar-title class="caption" style="margin:2% 0 0 2%;">{{ site.plugins.length }} Plugins</v-toolbar-title>
					 <v-data-table
						 :headers="headers"
						 :items="site.plugins"
						 class="elevation-1"
						 style="margin: 0 2% 2% 2%;"
						 :loading="site.loading_plugins"
						 hide-actions
					 >
						 <template slot="items" slot-scope="props">
							<td>{{ props.item.title }}</td>
							<td>{{ props.item.name }}</td>
							<td>{{ props.item.version }}</td>
							<td>
								<div v-if="props.item.status === 'active' || props.item.status === 'inactive'">
									<v-switch v-model="props.item.status" false-value="inactive" true-value="active" @change="togglePlugin(props.item.name, props.item.status, site.id)"></v-switch>
								</div>
								<div v-else>
									{{ props.item.status }}
								</div>
							</td>
							<td class="text-xs-center px-0">
								 <v-btn icon class="mx-0" @click="deletePlugin(props.item.name, site.id)" v-if="props.item.status === 'active' || props.item.status === 'inactive'">
									 <v-icon small color="pink">delete</v-icon>
								 </v-btn>
							 </td>
						 </template>
					 </v-data-table>
					 <p></p>
				</v-card>
	  </v-tab-item>
		<v-tab-item :key="4">
			<v-card>
				<v-card-title v-if="site.update_logs.length == 0">
					<div>
						Fetching update logs...
					  <v-progress-linear :indeterminate="true"></v-progress-linear>
					</div>
				</v-card-title>
				<v-card-title v-else>
					<div>
						<v-data-table
							:headers='[{"text":"Date"},{"text":"Description"},{"text":"Name"},{"text":"Version"},{"text":"Updated Version"},{"text":"Status"}]'
							:items="site.update_logs"
							hide-actions
							class="elevation-1"
						>
					    <template slot="items" slot-scope="props">
					      <td>{{ props.item.date | pretty_timestamp }}</td>
					      <td>{{ props.item.type }}</td>
								<td>{{ props.item.name }}</td>
								<td class="text-xs-right">{{ props.item.version }}</td>
								<td class="text-xs-right">{{ props.item.update_version }}</td>
								<td>{{ props.item.status }}</td>
					    </template>
					  </v-data-table>

					</div>
				</v-card-title>
			</v-card>
	  </v-tab-item>
	</v-tabs>

						 </v-expansion-panel-content>
					 </v-expansion-panel>
			  </v-card>
			</v-flex>
		  </v-layout>
			<template>
				<v-container>
				<v-layout justify-center>
				<div class="text-xs-center">
					<v-pagination v-if="Math.ceil(filteredSites / items_per_page) > 1" :length="Math.ceil(filteredSites / items_per_page)" v-model="page" @input="paginationUpdate( page )" :total-visible="7"></v-pagination>
				</div>
				</v-layout>
				</v-container>
			</template>
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

function titleCase(string) {
	return string.charAt(0).toUpperCase() + string.slice(1);
}

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
		page: 1,
		jobs: [],
		view_jobs: false,
		search: null,
		advanced_filter: false,
		items_per_page: 50,
		site_selected: null,
		site_filters: all_filters,
		site_filter_version: null,
		site_filter_status: null,
	  sites: sites,
		headers: [
			 { text: 'Name', value: 'name' },
			 { text: 'Slug', value: 'slug' },
			 { text: 'Version', value: 'version' },
			 { text: 'Status', value: 'status', width: "140px" },
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
	filters: {
		pretty_timestamp: function (date) {
			// takes in '2018-06-18-194447' and converts to '2018-06-18 19:44:47' then returns "Monday, Jun 18, 2018, 7:44 PM"
			date = date.substring(0,10) + " " + date.substring(11,13) + ":" + date.substring(13,15) + ":" + date.substring(15,17);
			formatted_date = new Date(date).toLocaleTimeString("en-us", pretty_timestamp_options);
			return formatted_date;
		}
	},
	computed: {
		runningJobs() {
			return this.jobs.filter(job => job.status != 'done').length;;
		},
		showingSitesBegin() {
			return this.page * this.items_per_page - this.items_per_page;
		},
		showingSitesEnd() {
			total = this.page * this.items_per_page;
			if (total > this.filteredSites) {
				total = this.filteredSites;
			}
			return total;
		},
		visibleSites() {
			return this.sites.filter(site => site.visible).length;
		},
		selectedSites() {
			return this.sites.filter(site => site.selected).length;
		},
		filteredSites() {
			return this.sites.filter(site => site.filtered).length;
		},
		allSites() {
			return this.sites.length;
		}
	},
	methods: {
		fetchUpdateLogs( site_id ) {
			site = this.sites.filter(site => site.id == site_id)[0];
			update_logs_count = site.update_logs.length;
			if ( update_logs_count == 0 ) {

				var data = {
					'action': 'captaincore_install',
					'post_id': site_id,
					'command': "update-fetch",
				};

				self = this;

				jQuery.post(ajaxurl, data, function(response) {

					console.log( response );

					// Formats response to readable format by table
					if ( Array.isArray(response) ) {
						update_items = [];
						response.forEach(logs => {
							logs.updates.forEach( update => {
								update.type = logs.type;
								update.date = logs.date;
								update_items.push( update );
							});
						});

						// Add to site.update_logs
						self.sites.filter(site => site.id == site_id)[0].update_logs = update_items;

					}

				});
			}
		},
		paginationUpdate( page ) {

			// Updates pagination with first 50 of sites visible
			this.page = page;
			count = 0;
			count_begin = page * this.items_per_page - this.items_per_page;
			count_end = page * this.items_per_page;
			this.sites.forEach(function(site) {
				if ( site.filtered == true ) {
					count++;
				}
				if ( site.filtered == true && count > count_begin && count <= count_end ) {
					site.visible = true;
				} else {
					site.visible = false;
				}
			});
		},
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
		activateTheme (theme_name, site_id) {

			// Enable loading progress
			this.sites.filter(site => site.id == site_id)[0].loading_themes = true;
			this.sites.filter(site => site.id == site_id)[0].themes.filter(theme => theme.name != theme_name).forEach( theme => theme.status = "inactive" );

			// Start job
			site_name = this.sites.filter(site => site.id == site_id)[0].name;
			description = "Activating theme '" +theme_name + "' on " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			// WP ClI command to send
			wpcli = "wp theme activate " + theme_name;

			var data = {
				'action': 'captaincore_install',
				'post_id': [ site_id ],
				'command': "manage",
				'value': ["ssh"],
				'arguments': [{ "name":"Commands","value":"command","command":"ssh","input": wpcli }]
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {
				self.sites.filter(site => site.id == site_id)[0].loading_themes = false;
				self.jobs.filter(job => job.job_id == job_id)[0].status = "done";
			});
		},
		deleteTheme (theme_name, site_id) {
			should_delete = confirm("Are you sure you want to delete theme " + theme_name + "?");
			if (should_delete) {

				// Enable loading progress
				this.sites.filter(site => site.id == site_id)[0].loading_themes = true;
				site_name = this.sites.filter(site => site.id == site_id)[0].name;
				description = "Removing theme '" +theme_name + "' from " + site_name;
				job_id = Math.round((new Date()).getTime());
				this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

				// WP ClI command to send
				wpcli = "wp theme delete " + theme_name;

				var data = {
					'action': 'captaincore_install',
					'post_id': [ site_id ],
					'command': "manage",
					'value': ["ssh"],
					'arguments': [{ "name":"Commands","value":"command","command":"ssh","input": wpcli }]
				};

				self = this;

				jQuery.post(ajaxurl, data, function(response) {
					updated_themes = self.sites.filter(site => site.id == site_id)[0].themes.filter(theme => theme.name != theme_name);
					self.sites.filter(site => site.id == site_id)[0].themes = updated_themes;
					self.sites.filter(site => site.id == site_id)[0].loading_themes = false;
					self.jobs.filter(job => job.job_id == job_id)[0].status = "done";
				});
			}
		},
		togglePlugin (plugin_name, plugin_status, site_id) {

			// Enable loading progress
			this.sites.filter(site => site.id == site_id)[0].loading_plugins = true;
			site_name = this.sites.filter(site => site.id == site_id)[0].name;

			if (plugin_status == "inactive") {
				action = "deactivate";
			}
			if (plugin_status == "active") {
				action = "activate";
			}

			description = titleCase(action) + " plugin '" + plugin_name + "' from " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			// WP ClI command to send
			wpcli = "wp plugin " + action + " " + plugin_name;

			var data = {
				'action': 'captaincore_install',
				'post_id': [ site_id ],
				'command': "manage",
				'value': ["ssh"],
				'arguments': [{ "name":"Commands","value":"command","command":"ssh","input": wpcli }]
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {
				self.sites.filter(site => site.id == site_id)[0].loading_plugins = false;
				self.jobs.filter(job => job.job_id == job_id)[0].status = "done";
			});
		},
		deletePlugin (plugin_name, site_id) {
			should_delete = confirm("Are you sure you want to delete plugin " + plugin_name + "?");
			if (should_delete) {

				// Enable loading progress
				this.sites.filter(site => site.id == site_id)[0].loading_plugins = true;

				site_name = this.sites.filter(site => site.id == site_id)[0].name;
				description = "Delete plugin '" + plugin_name + "' from " + site_name;
				job_id = Math.round((new Date()).getTime());
				this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

				// WP ClI command to send
				wpcli = "wp plugin delete " + plugin_name;

				var data = {
					'action': 'captaincore_install',
					'post_id': [ site_id ],
					'command': "manage",
					'value': ["ssh"],
					'arguments': [{ "name":"Commands","value":"command","command":"ssh","input": wpcli }]
				};

				self = this;

				jQuery.post(ajaxurl, data, function(response) {
					updated_plugins = self.sites.filter(site => site.id == site_id)[0].plugins.filter(plugin => plugin.name != plugin_name);
					self.sites.filter(site => site.id == site_id)[0].plugins = updated_plugins;
					self.sites.filter(site => site.id == site_id)[0].loading_plugins = false;
					self.jobs.filter(job => job.job_id == job_id)[0].status = "done";
				});
			}
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
			if ( this.applied_site_filter || this.search ) {

				search = this.search;
				filterby = this.applied_site_filter;
				filterbyversions = this.applied_site_filter_version;
				filterbystatuses = this.applied_site_filter_status;
				filter_versions = [];
				filter_statuses = [];
				versions = [];
				statuses = [];

				if ( this.applied_site_filter_version && this.applied_site_filter_version != "" ) {

					// Find all themes/plugins which have selected version
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

				// loop through sites and set filtered true if found in filter
				this.sites.forEach(function(site) {

					exists = false;

					if ( filterby ) {

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

					}

					//else {
					if ( this.applied_site_filter === null || this.applied_site_filter == "" ) {
						// No filters are enabled so enable all sites
						exists = true;
					}

					// If search by name then check for a partial matches
					if ( this.search && this.search != "" ) {
						if ( site.name.includes( this.search.toLowerCase() ) ) {
							exists = true;
						} else {
							exists = false;
						}
					}

					if (exists) {
						// Site filtered exists so set to visible
						site.filtered = true;
					} else {
						// Site filtered doesn't exists so hide
						site.filtered = false;
					}

				});

				if ( filterby ) {

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

				} // end filterby

			}

			// Neither filter is set so set all sites to filtered true.
			if ( !this.applied_site_filter && !this.search ) {

				this.sites.forEach(function(site) {
					site.filtered = true;
				});

			}

			this.paginationUpdate( 1 );

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
