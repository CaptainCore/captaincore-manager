<?php

$user       = wp_get_current_user();
$role_check = in_array( 'administrator', $user->roles );
if ( $role_check ) {

	add_filter( 'body_class', 'my_body_classes' );
	function my_body_classes( $classes ) {

		$classes[] = 'woocommerce-account';
		return $classes;

	}

?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/vuetify/1.0.19/vuetify.min.css" rel="stylesheet">
<style>

html {
	font-size: 62.5%;
}

.application .site .theme--dark.icon, .site .theme--dark .icon {
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

.upload-drag label.btn {
  margin-bottom: 0;
  margin-right: 1rem;
}
.upload-drag label.btn.btn-primary.file-uploads.file-uploads-html5.file-uploads-drop {
    display: none;
}
.upload-drag .drop-active {
  top: 0;
  bottom: 0;
  right: 0;
  left: 0;
  position: fixed;
  z-index: 9999;
  opacity: .6;
  text-align: center;
  background: #000;
}
.upload-drag .drop-active h3 {
  margin: -.5em 0 0;
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  -webkit-transform: translateY(-50%);
  -ms-transform: translateY(-50%);
  transform: translateY(-50%);
  font-size: 40px;
  color: #fff;
  padding: 0;
}
</style>
<?php if ( $_SERVER['SERVER_NAME'] == 'anchor.test' ) { ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.5.16/vue.js"></script>
<?php } else { ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.5.16/vue.min.js"></script>
<?php } ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vuetify/1.0.19/vuetify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue-upload-component@2.8.9/dist/vue-upload-component.js"></script>

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

Vue.component('file-upload', VueUploadComponent);

var sites = [
<?php
$count = 0;
foreach ( $websites as $website ) {
	$plugins = get_field( 'plugins', $website->ID );
	$themes = get_field( 'themes', $website->ID );
	$users = get_field( 'users', $website->ID );
	$customer = get_field( 'customer', $website->ID );
	$shared_with = get_field( 'partner', $website->ID );
	$storage = get_field('storage', $website->ID);
	$views = get_field('views', $website->ID);
	$production_address = get_field('address', $website->ID);
	$staging_address = get_field('address_staging', $website->ID);
	?>
{ id: <?php echo $website->ID; ?>,
name: "<?php echo get_the_title( $website->ID ); ?>",
<?php if ($customer && $customer[0]) {
?>customer: [{ customer_id: "<?php echo $customer[0]; ?>", name: "<?php echo get_the_title( $customer[0] ); ?>"}],<?php } ?>
<?php if ($shared_with) {
?>shared_with: [<?php foreach ($shared_with as $customer_id) { ?>{ customer_id: "<?php echo $customer_id; ?>", name: "<?php echo get_the_title( $customer_id ); ?>"},<?php } ?>],<?php } ?>
<?php if ( $plugins && $plugins != "" ) {
?>plugins: <?php echo $plugins; ?>,<?php } else { ?>plugins: [],<?php } ?>
<?php if ( $themes && $themes != "" ) {
?>themes: <?php echo $themes; ?>,<?php } else { ?>themes: [],<?php } ?>
core: "<?php echo get_field( 'core', $website->ID ); ?>",
keys: [
	{ key_id: 1, "link":"http://<?php echo get_the_title( $website->ID ); ?>","environment": "Production", "address": "<?php the_field('address', $website->ID); ?>","username":"<?php the_field('username', $website->ID); ?>","password":"<?php the_field('password', $website->ID); ?>","protocol":"<?php the_field('protocol', $website->ID); ?>","port":"<?php the_field('port', $website->ID); ?>",<?php if ( strpos($production_address, ".kinsta.com") ) { ?>"ssh":"ssh <?php the_field('username', $website->ID); ?>@<?php echo $production_address; ?> -p <?php the_field('port', $website->ID); ?>",<?php } if ( strpos($production_address, ".kinsta.com") and get_field('database_username', $website->ID) ) { ?>"database": "https://mysqleditor-<?php the_field('database_username', $website->ID); ?>.kinsta.com","database_username": "<?php the_field('database_username', $website->ID); ?>","database_password": "<?php the_field('database_password', $website->ID); ?>",<?php } ?>},
	<?php if (get_field('address_staging', $website->ID)) { ?>{ key_id: 2, "link":"<?php if (strpos( get_field('address_staging', $website->ID), ".kinsta.com") ) { echo "https://staging-". get_field('site_staging', $website->ID).".kinsta.com"; } else { echo "https://". get_field('site_staging', $website->ID). ".staging.wpengine.com"; } ?>","environment": "Staging", "address": "<?php the_field('address_staging', $website->ID); ?>","username":"<?php the_field('username_staging', $website->ID); ?>","password":"<?php the_field('password_staging', $website->ID); ?>","protocol":"<?php the_field('protocol_staging', $website->ID); ?>","port":"<?php the_field('port_staging', $website->ID); ?>"},<?php } ?>
],
<?php if ( $users and substr($users, 0, 1) === "[" ) {
?>
users: <?php echo $users; ?>,<?php } else { ?>users: [],<?php } ?>
update_logs: [],
loading_themes: false,
loading_plugins: false,
pagination: {
	sortBy: 'roles'
},
<?php
if ( $count <= 49 ) {
?>
visible: true,
<?php } else { ?>
visible: false,
<?php } ?>
filtered: true,
selected: false },
<?php
	$count++;
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
		v-model="new_plugin.show"
		max-width="500px"
		>
		<v-card tile>
			<v-toolbar card dark color="primary">
				<v-btn icon dark @click.native="new_plugin.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>Add plugin to {{ new_plugin.site_name }}</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text>

		<div class="upload-drag">
    <div class="upload">
      <div v-if="upload.length">
        <div v-for="(file, index) in upload" :key="file.id">
          <span>{{file.name}}</span> -
          <span>{{file.size | formatSize}}</span> -
          <span v-if="file.error">{{file.error}}</span>
          <span v-else-if="file.success">success</span>
          <span v-else-if="file.active">active
						<v-progress-linear v-model="file.progress"></v-progress-linear>
					</span>
          <span v-else></span>
        </div>
      </div>
      <div v-else>

          <div class="text-xs-center">
            <h4>Drop files anywhere to upload<br/>or</h4>
            <label for="file" class="btn btn-lg btn-primary" style="padding: 0px 8px;">Select Files</label>
          </div>

      </div>

			<div v-show="$refs.upload && $refs.upload.dropActive" class="drop-active">
    		<h3>Drop files to upload</h3>
      </div>

			<div class="upload-drag-btn">
				<file-upload class="btn btn-primary" @input-file="inputFile" post-action="/wp-content/plugins/captaincore/upload.php" :drop="true" v-model="upload" ref="upload"></file-upload>
			</div>
		</div>
	</div>
</v-card-text>
</v-card>
		</v-dialog>
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

              <v-list-tile avatar v-for="job in jobs.slice().reverse()" key="job.job_id">
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

			<v-dialog
				v-model="add_site"
				fullscreen
				hide-overlay
				transition="dialog-bottom-transition"
				scrollable
			>
	        <v-card tile>
	          <v-toolbar card dark color="primary">
	            <v-btn icon dark @click.native="add_site = false">
	              <v-icon>close</v-icon>
	            </v-btn>
	            <v-toolbar-title>Add Site</v-toolbar-title>
	            <v-spacer></v-spacer>
	          </v-toolbar>
	          <v-card-text>

							<v-container>
							<v-form ref="form">
								<v-text-field :value="new_site.domain" @change.native="new_site.domain = $event.target.value" label="Domain name" required></v-text-field>
						    <v-text-field :value="new_site.site" @change.native="new_site.site = $event.target.value" label="Site name" required></v-text-field>
								<v-container grid-list-md text-xs-center>
									<v-layout row wrap>
										<v-flex xs12 style="height:0px">
										<v-btn @click="new_site_preload_staging" flat icon center relative color="green" style="top:32px;">
				              <v-icon>cached</v-icon>
				            </v-btn>
										</v-flex>
										<v-flex xs6 v-for="key in new_site.keys" :key="key.index">
										<v-card class="bordered body-1" style="margin:2em;">
										<div style="position: absolute;top: -20px;left: 20px;">
											<v-btn depressed disabled right style="background-color: rgb(229, 229, 229)!important; color: #000 !important; left: -11px; top: 0px; height: 24px;">
												{{ key.environment }} Environment
											</v-btn>
										</div>
										<v-container fluid>
										<div row>

											<v-text-field label="Address" :value="key.address" @change.native="key.address = $event.target.value" required></v-text-field>
											<v-text-field label="Username" :value="key.username" @change.native="key.username = $event.target.value" required></v-text-field>
											<v-text-field label="Password" :value="key.password" @change.native="key.password = $event.target.value" required></v-text-field>
											<v-text-field label="Protocol" :value="key.protocol" @change.native="key.protocol = $event.target.value" required></v-text-field>
											<v-text-field label="Port" :value="key.port" @change.native="key.port = $event.target.value" required></v-text-field>
											<v-text-field label="Home Directory" :value="key.homedir" @change.native="key.homedir = $event.target.value" required></v-text-field>
											<v-text-field label="Database Username" :value="key.database_username" @change.native="key.database_username = $event.target.value" required></v-text-field>
											<v-text-field label="Database Password" :value="key.database_password" @change.native="key.database_password = $event.target.value" required></v-text-field>
											<div v-if="typeof key.use_s3 != 'undefined'">
												<v-switch label="Use S3" v-model="key.use_s3" left></v-switch>
												<div v-if="key.use_s3">
													<v-text-field label="s3 Access Key" :value="key.s3_access_key" @change.native="key.s3_access_key = $event.target.value" required></v-text-field>
													<v-text-field label="s3 Secret Key" :value="key.s3_secret_key" @change.native="key.s3_secret_key = $event.target.value" required></v-text-field>
													<v-text-field label="s3 Bucket" :value="key.s3_bucket" @change.native="key.s3_bucket = $event.target.value" required></v-text-field>
													<v-text-field label="s3 Path" :value="key.s3_path" @change.native="key.s3_path = $event.target.value" required></v-text-field>
												</div>
											</div>
										</div>
								 </v-container>
							 </v-card>
							</v-flex>
							<v-flex xs12 text-xs-right><v-btn right @click="submitNewSite">Add Site</v-btn></v-flex>
						 </v-layout>
					 </v-container>
						  </v-form>
						</v-container>
	          </v-card-text>
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
					<v-pagination v-if="Math.ceil(filteredSites / items_per_page) > 1" :length="Math.ceil(filteredSites / items_per_page)" v-model="page" @input="paginationUpdate( page )" :total-visible="7" color="blue darken-3"></v-pagination>
				</div>
				</v-layout>
				</v-container>
			</template>
			<div class="text-xs-right" v-if="typeof new_site == 'object'">
			<v-btn small dark color="blue darken-3" @click="add_site = true">Add Site
				<v-icon dark>add</v-icon>
			</v-btn>
			</div>
		  <v-layout row wrap v-for="site in sites" :key="site.id" v-show="site.visible">
				<v-flex xs1 v-if="advanced_filter == true">
					<v-switch v-model="site.selected" @change="site_selected = null"></v-switch>
				</v-flex>
				<v-flex v-bind:class="{ xs11: advanced_filter }">
					<v-card class="site">
						<v-expansion-panel>
						<v-expansion-panel-content lazy>
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
								<v-tab :key="4" ripple @click="fetchUsers( site.id )">
									Users <v-icon>fas fa-users</v-icon>
								</v-tab>
								<v-tab :key="5" ripple @click="fetchUpdateLogs( site.id )">
									Updates <v-icon>fas fa-book-open</v-icon>
								</v-tab>
								<v-tab :key="6" ripple>
									Sharing <v-icon>fas fa-user-lock</v-icon>
								</v-tab>
								<v-tab-item :key="1">
									<v-card v-for="key in site.keys" class="bordered">
										<div style="position: absolute; top: -20px; left: 20px;">
										<v-btn
											depressed
											disabled
											right
											style="background-color: rgb(229, 229, 229)!important; color: #000 !important; left: -11px; top: 0px; height: 24px;"
										>{{ key.environment }} Environment</v-btn></div>
										<v-container fluid>
										<div><h3 class="headline mb-0" style="margin-top:10px;"><a :href="key.link" target="_blank">{{ key.link }}</a></h3></div>
										<div row>

											<div><span class="caption">Address</span> {{ key.address }}</div>
											<div><span class="caption">Username</span> {{ key.username }}</div>
											<div><span class="caption">Password</span> <div class="pass-mask">##########</div><div class="pass-reveal">{{ key.password }}</div></div>
											<div><span class="caption">Protocol</span> {{ key.protocol }}</div>
											<div><span class="caption">Port</span> {{ key.port }}</div>

										 <div v-if="key.database && key.ssh">
											 <div v-if="key.database">
											 <hr />
											 <div><span class="caption">Database</span> <a :href="key.database" target="_blank">{{ key.database }}</a></div>
											 <div><span class="caption">Database Username</span> {{ key.database_username }}</a></div>
											 <div><span class="caption">Database Password</span> {{ key.database_password }}</a></div>
											 </div>
											 <hr />
											 <div v-if="key.ssh">{{ key.ssh }}</div>
										 </div>

									 </div>
								 </v-container>
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
				<div class="text-xs-right" style="margin:2% 2% 0 0;">
					<v-btn small dark color="blue darken-3" @click="addPlugin(site.id)">Add Plugin
						<v-icon dark>add</v-icon>
					</v-btn>
				</div>
					 <v-toolbar-title class="caption" style="margin:0 0 0 2%;">{{ site.plugins.length }} Plugins</v-toolbar-title>
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
				<v-card-title v-if="site.users.length == 0">
					<div>
						Fetching users...
					  <v-progress-linear :indeterminate="true"></v-progress-linear>
					</div>
				</v-card-title>
				<v-card-title v-else>
					<div>
						<v-data-table
							:headers='header_users'
							:pagination.sync="site.pagination"
							:rows-per-page-items='[50,100,250,{"text":"All","value":-1}]'
							:items="site.users"
							class="elevation-1"
						>
					    <template slot="items" slot-scope="props">
					      <td>{{ props.item.user_login }}</td>
								<td>{{ props.item.display_name }}</td>
								<td>{{ props.item.user_email }}</td>
								<td>{{ props.item.roles }}</td>
								<td><v-btn small round @click="loginSite(site.id, props.item.user_login)">Login as</v-btn></td>
					    </template>
					  </v-data-table>
					</div>
				</v-card-title>
			</v-card>
		</v-tab-item>
		<v-tab-item :key="5">
			<v-card>
				<v-card-title v-if="site.update_logs.length == 0">
					<div>
						Fetching update logs...
					  <v-progress-linear :indeterminate="true"></v-progress-linear>
					</div>
				</v-card-title>
				<v-card-title v-else-if="typeof site.update_logs == 'string'">
					{{ site.update_logs }}
				</v-card-title>
				<v-card-title v-else>
					<div>
						<v-data-table
							:headers='header_updatelog'
							:items="site.update_logs"
							hide-actions
							class="elevation-1"
						>
					    <template slot="items" slot-scope="props">
					      <td>{{ props.item.date | pretty_timestamp }}</td>
					      <td>{{ props.item.type }}</td>
								<td>{{ props.item.name }}</td>
								<td class="text-xs-right">{{ props.item.old_version }}</td>
								<td class="text-xs-right">{{ props.item.new_version }}</td>
								<td>{{ props.item.status }}</td>
					    </template>
					  </v-data-table>
					</div>
				</v-card-title>
			</v-card>
	  </v-tab-item>
		<v-tab-item :key="6">
			<v-card>
				<v-card-title>
					<div>
						<v-list two-line subheader>
	          <v-subheader inset>Customer</v-subheader>
	          <v-list-tile v-for="customer in site.customer" :key="customer.customer_id" avatar @click="">
	            <v-list-tile-avatar>
	              <v-icon>fas fa-user</v-icon>
	            </v-list-tile-avatar>
	            <v-list-tile-content>
	              <v-list-tile-title>{{ customer.name }}</v-list-tile-title>
	              <v-list-tile-sub-title></v-list-tile-sub-title>
	            </v-list-tile-content>
	            <v-list-tile-action>
	              <v-btn icon ripple>
	                <v-icon color="grey lighten-1">info</v-icon>
	              </v-btn>
	            </v-list-tile-action>
	          </v-list-tile>
	          <v-divider inset></v-divider>
	          <v-subheader inset>Shared With</v-subheader>
	          <v-list-tile v-for="customer in site.shared_with" :key="customer.customer_id" avatar @click="">
	            <v-list-tile-avatar>
	              <v-icon>fas fa-user</v-icon>
	            </v-list-tile-avatar>
	            <v-list-tile-content>
	              <v-list-tile-title>{{ customer.name }}</v-list-tile-title>
	              <v-list-tile-sub-title></v-list-tile-sub-title>
	            </v-list-tile-content>
	            <v-list-tile-action>
	              <v-btn icon ripple>
	                <v-icon color="grey lighten-1">info</v-icon>
	              </v-btn>
	            </v-list-tile-action>
	          </v-list-tile>
	        </v-list>
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
					<v-pagination v-if="Math.ceil(filteredSites / items_per_page) > 1" :length="Math.ceil(filteredSites / items_per_page)" v-model="page" @input="paginationUpdate( page )" :total-visible="7" color="blue darken-3"></v-pagination>
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

function tryParseJSON (jsonString){
try {
	var o = JSON.parse(jsonString);

	// Handle non-exception-throwing cases:
	// Neither JSON.parse(false) or JSON.parse(1234) throw errors, hence the type-checking,
	// but... JSON.parse(null) returns null, and typeof null === "object",
	// so we must check for that, too. Thankfully, null is falsey, so this suffices:
	if (o && typeof o === "object") {
		return o;
	}
}
catch (e) { }

return false;
};

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
				search: theme.title + " ("+ theme.name +")",
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
		add_site: false,
		<?php if ( current_user_can('administrator') ) { ?>
		new_site: {
			domain: "",
			updates_enabled: true,
			shared_with: [],
			keys: [
				{"environment": "Production", "address": "","username":"","password":"","protocol":"sftp","port":"2222","homedir":"","use_s3": false,"s3_access_key":"","s3_secret_key":"","s3_bucket":"","s3_path":"","database_username":"","database_password":"" },
				{"environment": "Staging", "address": "","username":"","password":"","protocol":"sftp","port":"2222","homedir":"","database_username":"","database_password":"" }
			],
		},<?php } else { ?>
		new_site: false,<?php } ?>
		new_plugin: { show: false, site_id: null},
		upload: [],
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
			 { text: 'Actions', value: 'actions', width: "90px", sortable: false }
		 ],
		 header_updatelog: [
 			 { text: 'Date', value: 'date' },
 			 { text: 'Type', value: 'type' },
 			 { text: 'Name', value: 'name' },
 			 { text: 'Old Version', value: 'old_version' },
 			 { text: 'New Version', value: 'new_version' },
			 { text: 'Status', value: 'status' }
 		 ],
		 header_users: [
			 { text: 'Login', value: 'login' },
			 { text: 'Display Name', value: 'display_name' },
			 { text: 'Email', value: 'user_email' },
			 { text: 'Role(s)', value: 'roles' },
			 { text: 'Actions', value: 'actions' }
		 ],
		 applied_site_filter: null,
		 applied_site_filter_version: null,
		 applied_site_filter_status: null,
		 select_site_options: [
			 { text: 'All', value: 'all' },
			 { text: 'Filtered', value: 'filtered' },
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
		formatSize: function (fileSizeInBytes) {
    var i = -1;
    var byteUnits = [' kB', ' MB', ' GB', ' TB', 'PB', 'EB', 'ZB', 'YB'];
    do {
        fileSizeInBytes = fileSizeInBytes / 1024;
        i++;
    } while (fileSizeInBytes > 1024);

    return Math.max(fileSizeInBytes, 0.1).toFixed(1) + byteUnits[i];
		},
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
		loginSite(site_id, username) {

			site = this.sites.filter(site => site.id == site_id)[0];

			// Adds new job
			job_id = Math.round((new Date()).getTime());
			description = "Login as '" + username + "' to " + site.name;
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			// Prep AJAX request
			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'command': "login",
				'value': username,
				'background': true
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {

				// Updates job id with reponsed background job id
				self.jobs.filter(job => job.job_id == job_id)[0].job_id = response;

				// Check if completed in 2 seconds
				setTimeout(function(){
					self.loginSitesRetry(site_id, response);
				}, 2000);

			});

		},
		loginSitesRetry( site_id, job_id ) {

		site = this.sites.filter(site => site.id == site_id)[0];
		job = this.jobs.filter(job => job.job_id == job_id)[0];
		self = this;

		var data = {
			'action': 'captaincore_install',
			'post_id': site_id,
			'command': "job-fetch",
			'job_id': job_id
		};

		jQuery.post(ajaxurl, data, function(response) {

		 json_array = response.split('\n');

		 if ( tryParseJSON(json_array[2]) ) {

			 if ( JSON.parse(json_array[2]).response == "Command finished" ) {

					 // Opens site
					 window.open(json_array[0]);
					 job.status = "done";

			 } else {
				 console.log("Retrying");
			 }

		 } else {
			 // Check if completed in 5 seconds
			 setTimeout(function() {
				 self.loginSitesRetry(site_id, job_id);
			 }, 5000);
		 }

		});
		},

		inputFile (newFile, oldFile) {

			if (newFile && oldFile) {
			// Uploaded successfully
        if (newFile.success && !oldFile.success) {
					new_response = JSON.parse( newFile.response );
					if (  new_response.response == "Success" && new_response.url ) {

						this.new_plugin.show = false;
						this.upload = [];

						// run wp cli with new plugin url and site
						site_id = this.new_plugin.site_id;
						site_name = this.new_plugin.site_name;

						// Adds new job
						job_id = Math.round((new Date()).getTime());
						description = "Installing plugin '" + newFile.name + "' to " + site_name;
						this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

						// Builds WP-CLI
						wpcli = "wp plugin install " + new_response.url + " --force --activate"

						// Prep AJAX request
						var data = {
							'action': 'captaincore_install',
							'post_id': site_id,
							'command': "manage",
							'value': "ssh",
							'background': true,
							'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
						};

						// Housecleaning
						this.new_plugin.site_id = null;
						this.new_plugin.site_name = null;

						self = this;

						jQuery.post(ajaxurl, data, function(response) {

							console.log( response );

							self.jobs.filter(job => job.job_id == job_id)[0].status = "done";

						});

					}

        }

			}

      // Automatically activate upload
      if (Boolean(newFile) !== Boolean(oldFile) || oldFile.error !== newFile.error) {
        if (!this.$refs.upload.active) {
          this.$refs.upload.active = true;
        }
      }
    },
		new_site_preload_staging() {

			// Copy production address to staging field
			this.new_site.keys[1].address = this.new_site.keys[0].address;

			if ( this.new_site.keys[0].address.includes(".kinsta.com") ) {
				// Copy production username to staging field
				this.new_site.keys[1].username = this.new_site.keys[0].username;
				// Copy production password to staging field (If Kinsta address)
				this.new_site.keys[1].password = this.new_site.keys[0].password;
			} else {
				// Copy production username to staging field with staging suffix
				this.new_site.keys[1].username = this.new_site.keys[0].username + "-staging";
			}

			// Copy production port to staging field
			this.new_site.keys[1].port = this.new_site.keys[0].port;
			// Copy production home directory to staging field
			this.new_site.keys[1].homedir = this.new_site.keys[0].homedir;
			// Copy production database info to staging fields
			this.new_site.keys[1].database_username = this.new_site.keys[0].database_username;
			this.new_site.keys[1].database_password = this.new_site.keys[0].database_password;
		},
		submitNewSite() {
			this.$emit('submit-new-site');
		},
		fetchUsers( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			users_count = site.users.length;

			// Fetch updates if none exists
			if ( users_count == 0 ) {

				job_id = Math.round((new Date()).getTime());

				description = "Fetching users for " + site.name;
				this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

				var data = {
					'action': 'captaincore_install',
					'post_id': site_id,
					'command': "users-fetch",
				};

				self = this;

				jQuery.post(ajaxurl, data, function(response) {

					// Expect Job ID and update temp Job in queue for tracking purposes
					new_job_id = response;
					description = "Fetching users for " + site.name;
					self.jobs.filter(job => job.job_id == job_id )[0].job_id = new_job_id;

					// Check if completed in 2 seconds
					setTimeout(function(){
          	self.fetchUsersRetry(site_id, new_job_id);
          }, 2000);
				});
			}
		},
		fetchUsersRetry( site_id, job_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			job = this.jobs.filter(job => job.job_id == job_id)[0];
			self = this;

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'command': "job-fetch",
				'job_id': job_id
			};

			jQuery.post(ajaxurl, data, function(response) {

			 json_array = response.split('\n');
			 last_item = json_array.length - 1;

			 if ( tryParseJSON(json_array[1]) ) {

				 if ( JSON.parse(json_array[1]).response == "Command finished" ) {

					 var json = JSON.parse(json_array[0]);
					 content_type = typeof json;

					 if ( content_type == "object" ) {

						 // Add to site.users
						 site.users = json;
						 job.status = "done";
					 }

				 } else {
					 console.log(response);
				 }

			 } else {
				 // Check if completed in 5 seconds
				 setTimeout(function() {
					 self.fetchUsersRetry(site_id, job_id);
				 }, 5000);
			 }

			});
		},
		fetchUpdateLogs( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			update_logs_count = site.update_logs.length;

			// Fetch updates if none exists
			if ( update_logs_count == 0 ) {

				var data = {
					'action': 'captaincore_install',
					'post_id': site_id,
					'command': "update-fetch",
				};

				jQuery.post(ajaxurl, data, function(response) {

					if (tryParseJSON(response)) {

					var json = JSON.parse(response);
					content_type = typeof json;

					if ( content_type == "object" ) {

					// Formats response to readable format by table
					update_items = [];
					json.forEach(logs => {
						logs.updates.forEach( update => {
							update.type = logs.type;
							update.date = logs.date;
							update_items.push( update );
						});
					});

					// Add to site.update_logs
					site.update_logs = update_items;
				}

				} else {
					site.update_logs = response.replace("[31mError:[39m ","Error: ");
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
			this.sites.forEach( site => {
				if ( site.filtered ) {
					count++;
				}
				if ( site.filtered && count > count_begin && count <= count_end ) {
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
		addPlugin ( site_id ){
			this.new_plugin.show = true;
			this.new_plugin.site_id = site_id;
			this.new_plugin.site_name = this.sites.filter(site => site.id == site_id)[0].name;
		},
		activateTheme (theme_name, site_id) {

			site = this.sites.filter(site => site.id == site_id)[0];

			// Enable loading progress
			site.loading_themes = true;
			site.themes.filter(theme => theme.name != theme_name).forEach( theme => theme.status = "inactive" );

			// Start job
			site_name = site.name;
			description = "Activating theme '" + theme_name + "' on " + site_name;
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
				site.loading_themes = false;
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
			if (this.site_selected == "filtered") {
				this.sites.forEach(site => site.selected = false );
				this.sites.filter(site => site.filtered ).forEach(site => site.selected = true );
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

<?php endif; ?>


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
