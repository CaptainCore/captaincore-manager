<?php

$user       = wp_get_current_user();
$role_check = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles ) + in_array( 'editor', $user->roles );
if ( $role_check ) {

	add_filter( 'body_class', 'my_body_classes' );
	function my_body_classes( $classes ) {

		$classes[] = 'woocommerce-account';
		return $classes;

	}

?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/vuetify/1.1.7/vuetify.min.css" rel="stylesheet">
<style>

html {
	font-size: 62.5%;
}

.table_users tbody tr td:nth-child(6) {
	 width:200px;
	 display:block;
}

.usage {
	font-size: 13px;
	margin: 0 4px;
}
.v-tabs__container--icons-and-text {
	height: auto;
}
.v-tabs__container--fixed-tabs .v-tabs__div, .v-tabs__container--icons-and-text .v-tabs__div {
	min-width: 0px;
}

.theme--dark .theme--light .v-select__selections {
	color: rgb(22, 101, 192);
	padding-left: 6px;
}

.theme--dark .theme--light .v-icon {
	color: rgba(0,0,0,.54);
}

.application.theme--light a {
	color: inherit;
}

.v-expansion-panel__header {
	line-height: 0.8em;
}

.quicksave-table table {
	width: auto;
}

.quicksave-table table tr:hover button.v-btn--flat:before {
	background-color: currentColor;
}

.v-expansion-panel__body {
	position: relative;
}

.application .site .theme--dark.icon, .site .theme--dark .v-icon {
	font-size: 1em;
	padding-left: 0.3em;
}

.v-dialog__content--active {
	z-index: 999999 !important;
}

li.v-expansion-panel__container {
    list-style: none;
}

.v-card hr {
	margin: 4px 0;
	background-color: #eaeaea;
}
.v-btn__content span {
    padding: 0 0 0 6px;
}
.v-toolbar__items i.v-icon.theme--dark {
    margin-left: 2%;
}
table.v-datatable.v-table.v-datatable--select-all thead tr th:nth-child(1),
table.v-datatable.v-table.v-datatable--select-all tbody tr td:nth-child(1) {
	width: 42px;
	padding: 0 0 0px 22px;
}
.v-expansion-panel__body .v-card.bordered {
	margin: 2em;
	padding: 0px;
	box-shadow: 0 2px 1px -1px rgba(0,0,0,.2), 0 1px 1px 0 rgba(0,0,0,.14), 0 1px 3px 0 rgba(0,0,0,.12);
}
.v-expansion-panel__body .v-card .pass-mask {
	display: inline-block;
}
.v-expansion-panel__body .v-card .pass-reveal {
	display: none;
}
.v-expansion-panel__body .v-card:hover .pass-mask {
	display: none;
}
.v-expansion-panel__body .v-card:hover .pass-reveal {
	display: inline-block;
}

.static.v-badge {
	position: fixed;
  top: 23%;
  right: 0px;
  background: white;
  z-index: 99999;
  padding: 1em 1em .5em 1em;
  box-shadow: 0 3px 1px -2px rgba(0,0,0,.2), 0 2px 2px 0 rgba(0,0,0,.14), 0 1px 5px 0 rgba(0,0,0,.12);
}

.v-select.v-text-field input, .v-input input, .v-text-field input {
	background: none;
	border: none;
}

.content-area ul.v-pagination {
	display: inline-flex;
	margin: 0px;
}

.alignright.input-group {
	width: auto;
}

a.v-tabs__item:hover {
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

.application .theme--light.v-input:not(.v-input--is-disabled) input, .application .theme--light.v-input:not(.v-input--is-disabled) textarea, .theme--light .v-input:not(.v-input--is-disabled) input, .theme--light .v-input:not(.v-input--is-disabled) textarea {
	border-radius: 0px;
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
.application .theme--dark.v-btn, .theme--dark .v-btn {
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

.application .theme--light.v-pagination__item--active, .theme--light button.v-pagination__item--active {
	color: #fff !important;
}

body button.v-pagination__item:hover {
    box-shadow: 0 3px 1px -2px rgba(0,0,0,.2), 0 2px 2px 0 rgba(0,0,0,.14), 0 1px 5px 0 rgba(0,0,0,.12);
	}

table.v-table thead tr,
table.v-table thead th,
table.v-table tbody td,
table.v-table tbody th,
table.v-table tfoot td {
	vertical-align: middle;
}
table.v-table tfoot td {
	font-weight: 400;
	font-size: 13px;
}
div.update_logs table tr td:nth-child(1) {
	white-space: nowrap;
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/vuetify/1.1.1/vuetify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue-upload-component@2.8.9/dist/vue-upload-component.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>

<?php

// Loads websites
$websites = captaincore_fetch_sites();
$customers = captaincore_fetch_customers();

if ( $websites ) :
?>
<script>

ajaxurl = "/wp-admin/admin-ajax.php";

var pretty_timestamp_options = {
    weekday: "short", year: "numeric", month: "short",
    day: "numeric", hour: "2-digit", minute: "2-digit"
};
// Example: new Date("2018-06-18 19:44:47").toLocaleTimeString("en-us", options);
// Returns: "Monday, Jun 18, 2018, 7:44 PM"

Vue.component('file-upload', VueUploadComponent);

var sites = [
<?php
$count = 0;
foreach ( $websites as $website ) {
	$plugins = trim( get_field( 'plugins', $website->ID ) );
	$themes = trim( get_field( 'themes', $website->ID ) );
	$customer = get_field( 'customer', $website->ID );
	$shared_with = get_field( 'partner', $website->ID );
	$storage = get_field('storage', $website->ID);
	$views = get_field('views', $website->ID);
	$exclude_themes = get_field('exclude_themes', $website->ID);
	$exclude_plugins = get_field('exclude_plugins', $website->ID);
	$updates_enabled = get_post_meta( $website->ID, 'updates_enabled');
	$production_address = get_field('address', $website->ID);
	$staging_address = get_field('address_staging', $website->ID);
	$home_url = get_field('home_url', $website->ID);
	$storage = get_field('storage', $website->ID);
	$views = get_field('views', $website->ID);
	if ($storage) {
		$storage_gbs = round($storage / 1024 / 1024 / 1024, 1);
		$storage_gbs = $storage_gbs ."GB";
	}

	?>
{ id: <?php echo $website->ID; ?>,
name: "<?php echo get_the_title( $website->ID ); ?>",
<?php if ($customer && $customer[0]) {
?>customer: [{ customer_id: "customer_id_<?php echo $customer[0]; ?>", name: "<?php echo get_post_field('post_title', $customer[0], 'raw');; ?>"}],<?php } ?>
<?php if ($shared_with) {
?>shared_with: [<?php foreach ($shared_with as $customer_id) { ?>{ customer_id: "shared_id_<?php echo $customer_id; ?>", name: "<?php echo get_post_field('post_title', $customer_id, 'raw'); ?>"},<?php } ?>],<?php } ?>
<?php if ( $plugins && $plugins != "" ) {
?>plugins: <?php echo $plugins; ?>,<?php } else { ?>plugins: [],<?php } ?>
<?php if ( $themes && $themes != "" ) {
?>themes: <?php echo $themes; ?>,<?php } else { ?>themes: [],<?php } ?>
core: "<?php echo get_field( 'core', $website->ID ); ?>",
keys: [
	{ key_id: 1, "link":"http://<?php echo get_the_title( $website->ID ); ?>","environment": "Production", "address": "<?php the_field('address', $website->ID); ?>","username":"<?php the_field('username', $website->ID); ?>","password":"<?php the_field('password', $website->ID); ?>","protocol":"<?php the_field('protocol', $website->ID); ?>","port":"<?php the_field('port', $website->ID); ?>",<?php if ( strpos($production_address, ".kinsta.com") ) { ?>"ssh":"ssh <?php the_field('username', $website->ID); ?>@<?php echo $production_address; ?> -p <?php the_field('port', $website->ID); ?>",<?php } if ( strpos($production_address, ".kinsta.com") and get_field('database_username', $website->ID) ) { ?>"database": "https://mysqleditor-<?php the_field('database_username', $website->ID); ?>.kinsta.com","database_username": "<?php the_field('database_username', $website->ID); ?>","database_password": "<?php the_field('database_password', $website->ID); ?>",<?php } ?>},
	<?php if (get_field('address_staging', $website->ID)) { ?>{ key_id: 2, "link":"<?php if (strpos( get_field('address_staging', $website->ID), ".kinsta.com") ) { echo "https://staging-". get_field('site_staging', $website->ID).".kinsta.com"; } else { echo "https://". get_field('site_staging', $website->ID). ".staging.wpengine.com"; } ?>","environment": "Staging", "address": "<?php the_field('address_staging', $website->ID); ?>","username":"<?php the_field('username_staging', $website->ID); ?>","password":"<?php the_field('password_staging', $website->ID); ?>","protocol":"<?php the_field('protocol_staging', $website->ID); ?>","port":"<?php the_field('port_staging', $website->ID); ?>"},<?php } ?>
],
<?php if ( $home_url ) { ?>home_url: "<?php echo $home_url; ?>",<?php } else { ?>home_url: "",<?php } ?>
users: [],
<?php if ( strpos( $production_address, '.wpengine.com' ) !== false ) { ?>server: "WP Engine",<?php } ?>
<?php if ( strpos( $production_address, '.kinsta.com' ) !== false ) { ?>server: "Kinsta",<?php } ?>
<?php if ( strpos( $production_address, '.wpengine.com' ) == false && strpos( $production_address, '.kinsta.com' ) == false ) { ?>server: "",<?php } ?>
<?php if ($views != 0) { ?>views: "<?php echo number_format($views); ?>"<?php } else { ?>views: ""<?php } ?>,
storage: "<?php echo $storage_gbs; ?>",
update_logs: [],
<?php if ( $exclude_themes ) {
$exclude_themes = explode(",", $exclude_themes);
$exclude_themes_formatted = '"' . implode ( '", "', $exclude_themes ) . '"';
?>
exclude_themes: [<?php echo $exclude_themes_formatted; ?>],<?php } else { ?>exclude_themes: [],<?php } ?>
<?php if ( $exclude_plugins ) {
$exclude_plugins = explode(",", $exclude_plugins);
$exclude_plugins_formatted = '"' . implode ( '", "', $exclude_plugins ) . '"';
?>
exclude_plugins: [<?php echo $exclude_plugins_formatted; ?>],<?php } else { ?>exclude_plugins: [],<?php } ?>
updates_enabled: <?php if ($updates_enabled && $updates_enabled[0] == "1" ) { echo '"1"'; } else { echo '"0"'; } ?>,
loading_themes: false,
loading_plugins: false,
themes_selected: [],
plugins_selected: [],
users_selected: [],
environment_selected: "Production",
tabs: 'tab-Site-Management',
tabs_management: 'tab-Keys',
pagination: {
	sortBy: 'roles'
},
update_logs_pagination: {
	sortBy: 'date',
	descending: true
},
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
		<v-dialog v-model="new_plugin.show" max-width="500px">
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
				<file-upload class="btn btn-primary" @input-file="inputFile" post-action="/wp-content/plugins/captaincore-gui/upload.php" :drop="true" v-model="upload" ref="upload"></file-upload>
			</div>
		</div>
		</div>
		</v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="new_theme.show" max-width="500px">
		<v-card tile>
			<v-toolbar card dark color="primary">
				<v-btn icon dark @click.native="new_theme.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>Add theme to {{ new_theme.site_name }}</v-toolbar-title>
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
				<file-upload class="btn btn-primary" @input-file="inputFile" post-action="/wp-content/plugins/captaincore-gui/upload.php" :drop="true" v-model="upload" ref="upload"></file-upload>
			</div>

		</div>
		</div>
		</v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="bulk_edit.show" max-width="600px">
		<v-card tile>
			<v-toolbar card dark color="primary">
				<v-btn icon dark @click.native="bulk_edit.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>Bulk edit on {{ bulk_edit.site_name }}</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
		<v-card-text>
			<h3>Bulk edit {{ bulk_edit.items.length }} {{ bulk_edit.type }}</h3>
			<v-btn v-if="bulk_edit.type == 'plugins'" @click="bulkEditExecute('activate')">Activate</v-btn> <v-btn v-if="bulk_edit.type == 'plugins'" @click="bulkEditExecute('deactivate')">Deactivate</v-btn> <v-btn v-if="bulk_edit.type == 'plugins'" @click="bulkEditExecute('toggle')">Toggle</v-btn> <v-btn @click="bulkEditExecute('delete')">Delete</v-btn>
		</v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="update_settings.show" max-width="500px">
		<v-card tile>
			<v-toolbar card dark color="primary">
				<v-btn icon dark @click.native="update_settings.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>Update settings for {{ update_settings.site_name }}</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text>

				<v-switch label="Automatic Updates" v-model="update_settings.updates_enabled" false-value="0" true-value="1"></v-switch>

				<v-select
					:items="update_settings.plugins"
					item-text="title"
					item-value="name"
					v-model="update_settings.exclude_plugins"
					label="Excluded Plugins"
					multiple
					chips
					persistent-hint
				></v-select>
				<v-select
					:items="update_settings.themes"
					item-text="title"
					item-value="name"
					v-model="update_settings.exclude_themes"
					label="Excluded Themes"
					multiple
					chips
					persistent-hint
				></v-select>

				<v-progress-linear :indeterminate="true" v-if="update_settings.loading"></v-progress-linear>

				<v-btn @click="saveUpdateSettings()">Save Update Settings</v-btn>

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
								<v-select
								:items="customers"
								item-text="name"
								item-value="customer_id"
								v-model="new_site.customers"
								item-text="name"
								label="Customer"
								chips
								multiple
								autocomplete
								small-chips
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
									<strong>{{ data.item.name }}</strong>
								</v-chip>
							</template>
							<template slot="item" slot-scope="data">
								<strong>{{ data.item.name }}</strong>
							</template>
							</v-select>
							<v-select
							:items="developers"
							item-text="name"
							item-value="customer_id"
							v-model="new_site.shared_with"
							item-text="name"
							label="Shared With"
							chips
							multiple
							autocomplete
							small-chips
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
								<strong>{{ data.item.name }}</strong>
							</v-chip>
						</template>
						<template slot="item" slot-scope="data">
							<strong>{{ data.item.name }}</strong>
						</template>
						</v-select>
						<v-switch label="Automatic Updates" v-model="new_site.updates_enabled" false-value="0" true-value="1"></v-switch>
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
											<v-text-field label="Site Name" :value="key.site" @change.native="key.site = $event.target.value" required></v-text-field>
											<v-text-field label="Address" :value="key.address" @change.native="key.address = $event.target.value" required></v-text-field>
											<v-text-field label="Username" :value="key.username" @change.native="key.username = $event.target.value" required></v-text-field>
											<v-text-field label="Password" :value="key.password" @change.native="key.password = $event.target.value" required></v-text-field>
											<v-text-field label="Protocol" :value="key.protocol" @change.native="key.protocol = $event.target.value" required></v-text-field>
											<v-text-field label="Port" :value="key.port" @change.native="key.port = $event.target.value" required></v-text-field>
											<v-text-field label="Home Directory" :value="key.homedir" @change.native="key.homedir = $event.target.value" required></v-text-field>
											<v-text-field label="Database Username" :value="key.database_username" @change.native="key.database_username = $event.target.value" required></v-text-field>
											<v-text-field label="Database Password" :value="key.database_password" @change.native="key.database_password = $event.target.value" required></v-text-field>
											<div v-if="typeof key.use_s3 != 'undefined'">
												<v-switch label="Use S3" v-model="key.use_s3" false-value="0" true-value="1" left></v-switch>
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
							<v-alert
							:value="true"
							type="error"
							v-for="error in new_site.errors"
							>
							{{ error }}
							</v-alert>
							<v-flex xs12 text-xs-right><v-btn right @click="submitNewSite">Add Site</v-btn></v-flex>
						 </v-layout>
					 </v-container>
						  </v-form>
						</v-container>
	          </v-card-text>
	        </v-card>
	      </v-dialog>
				<v-dialog
					v-model="dialog_apply_https_urls.show"
					fullscreen
					hide-overlay
					transition="dialog-bottom-transition"
					scrollable
				>
				<v-card tile>
					<v-toolbar card dark color="primary">
						<v-btn icon dark @click.native="dialog_apply_https_urls.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Apply HTTPS Urls for {{ dialog_apply_https_urls.site.name }}</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-alert :value="true" type="info" color="blue darken-3">
							Domain needs to match current home url which is <strong>{{ dialog_apply_https_urls.site.home_url }}</strong>. Otherwise server domain mapping will need updated to prevent redirection loop.
						</v-alert>
						<p></p>
						<span>Select url replacement option.</span><br />
						<v-btn @click="applyHttpsUrls( 'apply-https' )">
							Option 1: https://{{ dialog_apply_https_urls.site.name }}
						</v-btn><br />
						<v-btn @click="applyHttpsUrls( 'apply-https-with-www' )">
							Option 2: https://www.{{ dialog_apply_https_urls.site.name }}
						</v-btn>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog
					v-model="quicksave_dialog.show"
					fullscreen
					hide-overlay
					transition="dialog-bottom-transition"
					scrollable
				>
	        <v-card tile>
	          <v-toolbar card dark color="primary">
	            <v-btn icon dark @click.native="quicksave_dialog.show = false">
	              <v-icon>close</v-icon>
	            </v-btn>
	            <v-toolbar-title>Quicksaves for {{ quicksave_dialog.site_name }}</v-toolbar-title>
	            <v-spacer></v-spacer>
	          </v-toolbar>
	          <v-card-text>
						<v-container>
							<v-expansion-panel>
							  <v-expansion-panel-content v-for="quicksave in quicksave_dialog.quicksaves" lazy style="position:relative;">
							    <div slot="header" class="text-md-center"><span class="alignleft"><v-icon>settings_backup_restore</v-icon> {{ quicksave.created_at | pretty_timestamp }}</span><span class="body-1">{{ quicksave.git_status }}</span><span class="alignright body-1">WordPress {{ quicksave.core }} - {{ quicksave.plugins.length }} Plugins - {{ quicksave.themes.length }} Themes</span></div>
									<v-toolbar color="dark primary" dark dense light>
										<v-toolbar-title></v-toolbar-title>
										<v-spacer></v-spacer>
										<v-toolbar-items>
											<v-btn flat>Entire Quicksave Rollback</v-btn>
											<v-btn flat @click="viewQuicksavesChanges(quicksave_dialog.site_id, quicksave)">View Changes</v-btn>
										</v-toolbar-items>
									</v-toolbar>
									<v-card v-show="quicksave.view_changes == true" style="table-layout:fixed;margin:0px;overflow: scroll;padding: 0px;position: absolute;background-color: #fff;width: 100%;left: 0;top: 100%;height: 100%;z-index: 3;transform: translateY(-100%);">
										<v-toolbar color="dark primary" dark dense light>
											<v-btn icon dark @click.native="quicksave.view_changes = false">
					              <v-icon>close</v-icon>
					            </v-btn>
											<v-toolbar-title>List of changes</v-toolbar-title>
											<v-spacer></v-spacer>
										</v-toolbar>
										<v-card-text>
											<v-card-title>
									      Files
									      <v-spacer></v-spacer>
									      <v-text-field
									        v-model="quicksave_dialog.search"
													@input="filterFiles"
									        append-icon="search"
									        label="Search"
									        single-line
									        hide-details
									      ></v-text-field>
									    </v-card-title>
											<v-data-table hide-actions :headers='[{"text":"File","value":"file"}]' :items="quicksave.view_files">
												<template slot="items" slot-scope="props">
												 <td>
													 <a class="v-menu__activator"> {{ props.item }} </a>
												 </td>
											 </template>
											 <v-alert slot="no-results" :value="true" color="error" icon="warning">
													Your search for "{{ quicksave_dialog.search }}" found no results.
												</v-alert>
											</v-data-table>
										</v-card-text>
									</v-card>
							    <v-card>
											<v-data-table
												:headers='[{"text":"Theme","value":"theme"},{"text":"Version","value":"version"},{"text":"Status","value":"status"},{"text":"","value":"actions","width":"150px"}]'
												:items="quicksave.themes"
												item-key="name"
												class="quicksave-table"
												hide-actions
											 >
											 <template slot="items" slot-scope="props">
												<td>{{ props.item.title }}</td>
												<td>{{ props.item.version }}</td>
												<td>{{ props.item.status }}</td>
												<td><v-btn flat small>Rollback</v-btn></td>
											 </template>
											</v-data-table>

											<v-data-table
												:headers='[{"text":"Plugin","value":"plugin"},{"text":"Version","value":"version"},{"text":"Status","value":"status"},{"text":"","value":"actions","width":"150px"}]'
												:items="quicksave.plugins"
												item-key="name"
												class="quicksave-table"
												hide-actions
											 >
											 <template slot="items" slot-scope="props">
												<td>{{ props.item.title }}</td>
												<td>{{ props.item.version }}</td>
												<td>{{ props.item.status }}</td>
												<td><v-btn flat small>Rollback</v-btn></td>
											 </template>
											</v-data-table>
							    </v-card>
							  </v-expansion-panel-content>
							</v-expansion-panel>
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
						@change="page = 1"
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
			<v-text-field
				v-model="search"
				label="Search sites by name"
				light
				@input="filterSites"
				></v-text-field>
			<v-layout row v-if="advanced_filter == true">
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
			small-chips
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
		<v-layout row v-if="advanced_filter == true">
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

			<v-layout justify-center>
				<div class="text-xs-center">
					<v-pagination v-if="Math.ceil(filteredSites / items_per_page) > 1" :length="Math.ceil(filteredSites / items_per_page)" v-model="page" :total-visible="7" color="blue darken-3"></v-pagination>
				</div>
			</v-layout>

			<div class="text-xs-right" v-if="typeof new_site == 'object'">
			<v-btn small dark color="blue darken-3" @click="add_site = true">Add Site
				<v-icon dark>add</v-icon>
			</v-btn>
			</div>
		  <v-layout row wrap v-for="site in paginatedSites" :key="site.id" style="padding: 0px;margin:20px 0px;">
				<v-flex xs1 v-if="advanced_filter == true">
					<v-switch v-model="site.selected" @change="site_selected = null" style="margin-top: 10px;margin-bottom: 0px;height: 30px;"></v-switch>
				</v-flex>
				<v-flex v-bind:class="{ xs11: advanced_filter }">
					<v-card class="site">
						<v-expansion-panel>
						<v-expansion-panel-content lazy>
							<div slot="header">
								<v-layout align-center justify-space-between row>
									<div>
										<strong>{{ site.name }}</strong>
									</div>
									<div class="text-xs-right">
										<span v-show="site.server" class="usage"><v-icon small light>fas fa-server</v-icon> {{ site.server }}</span>
										<span v-show="site.views" class="usage"><v-icon small light>fas fa-eye</v-icon> {{ site.views }} <small>yearly</small></span>
										<span v-show="site.storage" class="usage"><v-icon small light>fas fa-hdd</v-icon> {{ site.storage }}</span>
									</div>
								</v-layout>
							</div>
							<v-tabs v-model="site.tabs" color="blue darken-3"
				 		 dark
				 		 >
								<v-tab :key="1" href="#tab-Site-Management">
								Site Management<v-icon>fas fa-cog</v-icon>
								</v-tab>
								<v-tab :key="6" href="#tab-Sharing" ripple>
									Sharing <v-icon>fas fa-user-lock</v-icon>
								</v-tab>
								<v-tab :key="7" href="#tab-Advanced" ripple>
									Advanced <v-icon>fas fa-cogs</v-icon>
								</v-tab>
							</v-tabs>
						<v-tabs-items v-model="site.tabs">

							<v-tab-item id="tab-Site-Management">

								<v-tabs v-model="site.tabs_management" color="grey lighten-4" right icons-and-text>
									<v-select
										v-model="site.environment_selected"
										:items='[{"name":"Production Environment","value":"Production"},{"name":"Staging Environment","value":"Staging"}]'
										item-text="name"
										item-value="value"
										light
										style="max-width: 230px; margin: 0px auto 0px 16px; top: 8px;"></v-select>

									<v-tab key="Keys" href="#tab-Keys">
									  Keys <v-icon small style="margin-left:7px;">fas fa-key</v-icon>
									</v-tab>
									<v-tab key="Themes" href="#tab-Themes">
									  Themes <v-icon small style="margin-left:7px;">fas fa-paint-brush</v-icon>
									</v-tab>
									<v-tab key="Plugins" href="#tab-Plugins">
									  Plugins <v-icon small style="margin-left:7px;">fas fa-plug</v-icon>
									</v-tab>
									<v-tab key="Users" href="#tab-Users" @click="fetchUsers( site.id )">
									  Users <v-icon small style="margin-left:7px;">fas fa-users</v-icon>
									</v-tab>
									<v-tab key="Updates" href="#tab-Updates" @click="fetchUpdateLogs( site.id )">
									  Updates <v-icon small style="margin-left:7px;">fas fa-book-open</v-icon>
									</v-tab>
									<v-tab key="Scripts" href="#tab-Scripts">
										Scripts <v-icon small style="margin-left:7px;">fas fa-code</v-icon>
									</v-tab>
									<v-tab key="Backups" href="#tab-Backups">
										Backups <v-icon small style="margin-left:7px;">fas fa-hdd</v-icon>
									</v-tab>
								</v-tabs>
								<v-tabs-items v-model="site.tabs_management" v-if="site.keys.filter( key => key.environment == site.environment_selected ).length == 1">
									<v-tab-item :key="1" id="tab-Keys">
										<v-toolbar color="grey lighten-4" dense light>
											<v-toolbar-title>Keys</v-toolbar-title>

											<!--<div style="margin-left: 20px;">
											<v-btn
												depressed
												disabled
												right
												style="background-color: rgb(229, 229, 229)!important; color: #000 !important; left: -11px; top: 0px; height: 24px;"
											>{{ site.environment_selected }} Environment</v-btn></div>-->
											<v-spacer></v-spacer>
										</v-toolbar>

										<v-card v-for="key in site.keys" v-show="key.environment == site.environment_selected">

											<v-container fluid style="padding-top: 10px;">
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
								<v-tab-item :key="2" id="tab-Themes">
									<v-toolbar color="grey lighten-4" dense light>
										<v-toolbar-title>Themes</v-toolbar-title>
										<v-spacer></v-spacer>
										<v-toolbar-items>
											<v-btn flat @click="bulkEdit(site.id,'themes')" v-if="site.themes_selected.length != 0">Bulk Edit {{ site.themes_selected.length }} themes</v-btn>
											<v-btn flat @click="addTheme(site.id)">Add Theme <v-icon dark small>add</v-icon></v-btn>
										</v-toolbar-items>
									</v-toolbar>
									<v-data-table
										:headers="headers"
										:items="site.themes"
										:loading="site.loading_themes"
										v-model="site.themes_selected"
										item-key="name"
										value="name"
										class="elevation-1"
										select-all
										hide-actions
										>
									 <template slot="items" slot-scope="props">
										 <td>
						         <v-checkbox
						           v-model="props.selected"
						           primary
						           hide-details
						         ></v-checkbox>
						 				</td>
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
							</v-tab-item>
			<v-tab-item :key="3" id="tab-Plugins">
				<v-toolbar color="grey lighten-4" dense light>
					<v-toolbar-title>Plugins</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn flat @click="bulkEdit(site.id, 'plugins')" v-if="site.plugins_selected.length != 0">Bulk Edit {{ site.plugins_selected.length }} plugins</v-btn>
						<v-btn flat @click="addPlugin(site.id)">Add Plugin <v-icon dark small>add</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-data-table
					:headers="headers"
					:items="site.plugins.filter(plugin => plugin.status != 'must-use' && plugin.status != 'dropin')"
					:loading="site.loading_plugins"
					:rows-per-page-items='[50,100,250,{"text":"All","value":-1}]'
					v-model="site.plugins_selected"
					item-key="name"
					value="name"
					class="elevation-1"
					select-all
					hide-actions
				 >
				 <template slot="items" slot-scope="props">
					<td>
	        <v-checkbox
	          v-model="props.selected"
	          primary
	          hide-details
	        ></v-checkbox>
					</td>
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
				 <template slot="footer" v-for="plugin in site.plugins.filter(plugin => plugin.status == 'must-use' || plugin.status == 'dropin')">
					<tr>
						<td></td>
						<td>{{ plugin.title }}</td>
						<td>{{ plugin.name }}</td>
						<td>{{ plugin.version }}</td>
						<td>{{ plugin.status }}</td>
						<td class="text-xs-center px-0"></td>
					</tr>
				 </template>
				</v-data-table>
		  </v-tab-item>
			<v-tab-item :key="4" id="tab-Users">
				<v-toolbar color="grey lighten-4" dense light>
					<v-toolbar-title>Users</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn flat @click="bulkEdit(site.id,'users')" v-if="site.users_selected.length != 0">Bulk Edit {{ site.users_selected.length }} users</v-btn>
						<v-btn flat @click="addPlugin(site.id)">Add User <v-icon dark small>add</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card>
					<v-card-title v-if="site.users.length == 0">
						<div>
							Fetching users...
						  <v-progress-linear :indeterminate="true"></v-progress-linear>
						</div>
					</v-card-title>
					<div v-else>
						<v-data-table
							:headers='header_users'
							:pagination.sync="site.pagination"
							:rows-per-page-items='[50,100,250,{"text":"All","value":-1}]'
							:items="site.users"
							item-key="user_login"
							v-model="site.users_selected"
							class="elevation-1 table_users"
							select-all
						>
					    <template slot="items" slot-scope="props">
								<td>
				        <v-checkbox
				          v-model="props.selected"
				          primary
				          hide-details
				        ></v-checkbox>
								</td>
					      <td>{{ props.item.user_login }}</td>
								<td>{{ props.item.display_name }}</td>
								<td>{{ props.item.user_email }}</td>
								<td>{{ props.item.roles }}</td>
								<td>
									<v-btn small round @click="loginSite(site.id, props.item.user_login)">Login as</v-btn>
									<v-btn icon class="mx-0" @click="deleteUser(props.item.user_login, site.id)">
										<v-icon small color="pink">delete</v-icon>
									</v-btn>
								</td>
					    </template>
					  </v-data-table>
					</div>
				</v-card>
			</v-tab-item>
			<v-tab-item :key="5" id="tab-Updates">
				<v-toolbar color="grey lighten-4" dense light>
					<v-toolbar-title>Update Logs</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn flat @click="update(site.id)">Manually update</v-btn>
						<v-btn flat @click="updateSettings(site.id)">Update Settings <v-icon dark small>fas fa-cog</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
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
					<div v-else>
							<v-data-table
								:headers='header_updatelog'
								:items="site.update_logs"
								:pagination.sync="site.update_logs_pagination"
								class="elevation-1 update_logs"
								:rows-per-page-items='[50,100,250,{"text":"All","value":-1}]'
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
				</v-card>
			</v-tab-item>
			<v-tab-item :key="6" id="tab-Scripts">
				<v-toolbar color="grey lighten-4" dense light>
					<v-toolbar-title>Scripts</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-card>
					<v-card-title>
						<div>
							<v-btn small flat @click="viewApplyHttpsUrls(site.id)">
								<v-icon>launch</v-icon> <span>Apply HTTPS Urls</span></v-btn><br />
							<v-btn small flat>
								<v-icon>email</v-icon> <span>View Mailgun Logs</span></v-btn><br />
							<v-btn small flat>
								<v-icon>loop</v-icon> <span>Redeploy users/plugins</span></v-btn><br />
							<v-btn small flat>
								<v-icon>fas fa-toggle-on</v-icon><span>Toggle Site</span></v-btn><br />
						</div>
					</v-card-title>
				</v-card>
			</v-tab-item>
			<v-tab-item :key="7" id="tab-Backups">
				<v-toolbar color="grey lighten-4" dense light>
					<v-toolbar-title>Backups</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-card>
					<v-card-title>
						<div>
							<v-btn small flat>
								<v-icon>cloud</v-icon> <span>Download Backup Snapshot</span>
							</v-btn><br />
							<v-btn small flat @click="viewQuicksaves(site.id)">
								<v-icon>settings_backup_restore</v-icon> <span>Quicksaves (Plugins & Themes)</span>
							</v-btn><br />
						</div>
						</v-card-title>
					</v-card>
			</v-tab-item>
		</v-tabs-items>
		<v-card v-if="site.keys.filter( key => key.environment == site.environment_selected ).length == 0">

			<v-container fluid>
			 <div><span>{{ site.environment_selected }} environment not created.</span></div>
		 </v-container>

		</v-card>

		</v-tab-item>
		<v-tab-item :key="6" id="tab-Sharing">
			<v-toolbar color="grey lighten-4" dense light>
				<v-toolbar-title>Sharing</v-toolbar-title>
				<v-spacer></v-spacer>
				<v-toolbar-items>
					<v-btn flat>Invite</v-btn>
				</v-toolbar-items>
			</v-toolbar>
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
		<v-tab-item :key="7" id="tab-Advanced">
			<v-toolbar color="grey lighten-4" dense light>
				<v-toolbar-title>Advanced</v-toolbar-title>
				<v-spacer></v-spacer>
				<v-toolbar-items>
					<v-btn flat @click="bulkEdit(site.id,'users')" v-if="site.users_selected.length != 0">Bulk Edit {{ site.users_selected.length }} users</v-btn>
					<v-btn flat @click="submitNewSite">Copy Site <v-icon dark small>file_copy</v-icon></v-btn>
					<v-btn flat @click="submitNewSite">Edit Site <v-icon dark small>edit</v-icon></v-btn>
					<v-btn flat @click="submitNewSite">Remove Site <v-icon dark small>delete</v-icon></v-btn>
				</v-toolbar-items>
			</v-toolbar>
			<v-card>
				<v-card-title>
					<div>
						<v-btn small flat>
							<v-icon>local_shipping</v-icon> <span>Push Production to Staging</span></v-btn><br />
						<v-btn small flat>
							<v-icon class="reverse">local_shipping</v-icon> <span>Push Staging to Production</span></v-btn><br />
						<v-btn small flat>
							<v-icon>chrome_reader_mode</v-icon>
							<span>View Usage Breakdown</span>
						</v-btn><br />
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
					<v-pagination v-if="Math.ceil(filteredSites / items_per_page) > 1" :length="Math.ceil(filteredSites / items_per_page)" v-model="page" :total-visible="7" color="blue darken-3"></v-pagination>
				</div>
				</v-layout>
				</v-container>
			</template>
			</v-container>
			<v-snackbar
				:timeout="3000"
				:multi-line="true"
				v-model="snackbar.show"
			>
				{{ snackbar.message }}
				<v-btn dark flat @click.native="snackbar.show = false">Close</v-btn>
			</v-snackbar>
			<v-dialog
				fullscreen
				transition="dialog-bottom-transition"
				:overlay="false"
				scrollable
				v-model="dialog">
			 <v-card tile style="overflow-y: scroll;">
				 <v-toolbar card dark color="primary">
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
					 <v-chip
						color="green"
						outline
						close
						v-for="site in sites_selected"
						@input="removeFromBulk(site.id)"
						><a :href="site.home_url" target="_blank" style="color:#4caf50;">{{ site.name }}</a></v-chip>
				 </v-card-text>
				 <v-card-actions>
					 <v-btn @click="bulkactionLaunch">Launch sites in browser</v-btn>
					  <v-btn @click="bulkactionSubmit">submit</v-btn>
					 <v-btn color="primary" flat @click.stop="dialog=false">Close</v-btn>
				 </v-card-actions>
				 <v-spacer></v-spacer>
				 <p></p>
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
		dialog_apply_https_urls: { show: false, site: {} },
		page: 1,
		jobs: [],
		add_site: false,
		<?php if ( current_user_can('administrator') ) { ?>
		new_site: {
			domain: "",
			errors: [],
			updates_enabled: "1",
			shared_with: [],
			customers: [],
			keys: [
				{"environment": "Production", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","homedir":"","use_s3": false,"s3_access_key":"","s3_secret_key":"","s3_bucket":"","s3_path":"","database_username":"","database_password":"" },
				{"environment": "Staging", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","homedir":"","database_username":"","database_password":"" }
			],
		},
		customers: [
			<?php foreach ($customers as $customer) { ?>
			{customer_id: "<?php echo $customer->ID; ?>", name: "<?php echo $customer->post_title; ?>", developer: <?php if( get_field('partner', $customer->ID ) ) { echo "true"; } else { echo "false"; } ?> },
			<?php } ?>
		],
		shared_with: [

		],
		<?php } else { ?>
		new_site: false,
		customers: [],
		shared_with: [],<?php } ?>
		new_plugin: { show: false, site_id: null},
		new_theme: { show: false, site_id: null},
		quicksave_dialog: { show: false, site_id: null, quicksaves: [], search: "" },
		bulk_edit: { show: false, site_id: null, type: null, items: [] },
		update_settings: { show: false, site_id: null, loading: false},
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
			{ text: 'Status', value: 'status', width: "100px" },
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
			{ text: 'Actions', value: 'actions', sortable: false }
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
		 snackbar: [
			 { show: false },
			 { message: "" }
		 ]
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
			// takes in '2018-06-18 19:44:47' then returns "Monday, Jun 18, 2018, 7:44 PM"
			formatted_date = new Date(date).toLocaleTimeString("en-us", pretty_timestamp_options);
			return formatted_date;
		}
	},
	computed: {
		paginatedSites() {
			const start = this.page * this.items_per_page - this.items_per_page;
			const end = start + this.items_per_page;
			return this.sites.filter( site => site.filtered ).slice(start, end);
		},
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
			return this.paginatedSites.length;
		},
		selectedSites() {
			return this.sites.filter(site => site.selected).length;
		},
		sites_selected() {
			return this.sites.filter( site => site.selected );
		},
		filteredSites() {
			return this.sites.filter(site => site.filtered).length;
		},
		allSites() {
			return this.sites.length;
		},
		developers() {
			return this.customers.filter(customer => customer.developer );
		},
		filteredFiles() {
			return this.quicksave_dialog.view_files.filter( file => file.filtered );
		},
	},
	methods: {
		removeFromBulk( site_id ) {
			this.sites.filter(site => site.id == site_id)[0].selected = false;
		},
		loginSite(site_id, username) {

			site = this.sites.filter(site => site.id == site_id)[0];

			// Adds new job
			job_id = Math.round((new Date()).getTime());
			description = "Login as '" + username + "' to " + site.name;
			this.jobs.push({"job_id": job_id,"description": description, "status": "running", "command":"login"});

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
					self.jobRetry(site_id, response);
				}, 2000);

			});

		},
		jobRetry( site_id, job_id ) {

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

				index = 0;
				repeat = true;

				// collect responses seperated by lines
				response_array = response.split('\n');

				// Loop through lines looking for valid JSON
				response_array.forEach(line => {

					if ( tryParseJSON(line) ) {

						line_parsed = JSON.parse(line);

						if ( line_parsed.response == "Command finished" ) {

							previous_index = index - 1;

							if ( job.command == "usersFetch") {
								if ( tryParseJSON( response_array[previous_index] ) ) {
									// Add to site.users
									site.users =  JSON.parse( response_array[previous_index] );
								}
							}

							if ( job.command == "login") {
								// Opens site
								window.open(response_array[previous_index]);
							}

							if ( job.command == "saveUpdateSettings" ){
								// to do
							}

							if ( job.command == "update-wp" ){
								// to do
								site.update_logs = [];
								self.fetchUpdateLogs( site_id );
							}

							job.status = "done";
							repeat = false;

						}

					}

					index++;

				});

				if ( repeat ) {
					// Check if completed in 5 seconds
					setTimeout(function() {
						self.jobRetry(site_id, job_id);
					}, 5000);
				}

			});
		},
		inputFile (newFile, oldFile) {

			if (newFile && oldFile) {
				// Uploaded successfully
				if (newFile.success && !oldFile.success) {
					new_response = JSON.parse( newFile.response );
					if ( new_response.response == "Success" && new_response.url ) {

						if ( this.new_plugin.show ) {
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
							wpcli = "wp plugin install '" + new_response.url + "' --force --activate"

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
						}
						if ( this.new_theme.show ) {
							this.new_theme.show = false;
							this.upload = [];

							// run wp cli with new plugin url and site
							site_id = this.new_theme.site_id;
							site_name = this.new_theme.site_name;

							// Adds new job
							job_id = Math.round((new Date()).getTime());
							description = "Installing theme '" + newFile.name + "' to " + site_name;
							this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

							// Builds WP-CLI
							wpcli = "wp theme install '" + new_response.url + "' --force"

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
							this.new_theme.site_id = null;
							this.new_theme.site_name = null;
						}

						self = this;

						jQuery.post(ajaxurl, data, function(response) {

							// TO DO, update job ID to respsonse (trackable one from server)
							//  start a repeat check to see when it's completed. Do no mark it done here.

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

			// Copy production site name to staging field
			this.new_site.keys[1].site = this.new_site.keys[0].site;

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

			var data = {
				'action': 'captaincore_ajax',
				'command': "newSite",
				'value': this.new_site
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {

				if (tryParseJSON(response)) {
					var response = JSON.parse(response);

					// If error then response
					if ( response.response.includes("Error:") ) {

						self.new_site.errors = [ response.response ];
						console.log(response.response);
						return;
					}

					if ( response.response = "Successfully added new site" ) {
						self.add_site = false;
						self.new_site = {
							domain: "",
							errors: [],
							updates_enabled: "1",
							shared_with: [],
							customers: [],
							keys: [
								{"environment": "Production", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","homedir":"","use_s3": false,
								"s3_access_key":"","s3_secret_key":"","s3_bucket":"","s3_path":"","database_username":"","database_password":"" },
								{"environment": "Staging", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","homedir":"","database_username":"","database_password":"" }
							],
						}
						self.fetchSiteInfo( response.site_id );

						// Run prep immediately after site added.
						var data = {
							'action': 'captaincore_install',
							'command': "update",
							'post_id': response.site_id
						};
						jQuery.post(ajaxurl, data, function(response) {
							console.log(response);
						});

					}
				}
			});
		},
		fetchSiteInfo( site_id ) {

			var data = {
				'action': 'captaincore_ajax',
				'command': "fetch-site",
				'post_id': site_id
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {

				if (tryParseJSON(response)) {
					var site = JSON.parse(response);
					lookup = self.sites.filter(site => site.id == site_id).length;
					if (lookup == 1 ) {
						// Update existing site info
						site_update = self.sites.filter(site => site.id == site_id)[0];
						// Look through keys and update
						Object.keys(site).forEach(function(key) {
						  site_update[key] = site[key];
						});
					} else {
						// Patch in default settings
						site.loading_themes = false;
						site.loading_plugins = false;
						site.themes_selected = [];
						site.plugins_selected = [];
						site.users_selected = [];
						site.tabs = 'tab-Keys';
						site.pagination = {
							sortBy: 'roles'
						};
						site.update_logs_pagination = {
							sortBy: 'date',
							descending: true
						};
						site.filtered = true;
						site.selected = false;

						// Add new site info
						self.sites.push(site);
					}
				}
			});
		},
		fetchUsers( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			users_count = site.users.length;

			// Fetch updates if none exists
			if ( users_count == 0 ) {

				var data = {
					'action': 'captaincore_ajax',
					'post_id': site_id,
					'command': "fetch-users",
				};

				self = this;

				jQuery.post(ajaxurl, data, function(response) {

					if (tryParseJSON(response)) {
						// Add to site.update_logs
						site.users = JSON.parse(response);
					}

					if ( site.users.length == 0 ) {
						site.users = "Error: Did not find any users.";
					}

				});
			}
		},
		fetchUpdateLogs( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			update_logs_count = site.update_logs.length;

			// Fetch updates if none exists
			if ( update_logs_count == 0 ) {

				var data = {
					'action': 'captaincore_ajax',
					'post_id': site_id,
					'command': "fetch-update-logs",
				};

				jQuery.post(ajaxurl, data, function(response) {

					if (tryParseJSON(response)) {
						// Add to site.update_logs
						site.update_logs = JSON.parse(response);
					}

					if ( site.update_logs.length == 0 ) {
						site.update_logs = "Error: Did not find any update logs.";
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
		bulkEdit ( site_id, type ) {
			this.bulk_edit.show = true;
			site = this.sites.filter(site => site.id == site_id)[0];
			this.bulk_edit.site_id = site_id;
			this.bulk_edit.site_name = site.name;
			this.bulk_edit.items = site[ type.toLowerCase() + "_selected" ];
			this.bulk_edit.type = type;
		},
		bulkEditExecute ( action ) {
			site_id = this.bulk_edit.site_id;
			object_singular = this.bulk_edit.type.slice(0, -1);
			items = this.bulk_edit.items.map(item => item.name).join(" ");
			if ( object_singular == "user" ) {
				items = this.bulk_edit.items.map(item => item.user_login).join(" ");
			}

			// Start job
			site_name = this.bulk_edit.site_name;
			description = "Bulk action '" + action + " " + this.bulk_edit.type + "' on " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			// WP ClI command to send
			wpcli = "wp " + object_singular + " " + action + " " + items;

			this.bulk_edit.show = false;

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'command': "manage",
				'value': "ssh",
				'background': true,
				'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {
				self.jobs.filter(job => job.job_id == job_id)[0].status = "done";
			});

		},
		applyHttpsUrls( command ) {
			confirm_command = confirm("Will apply ssl urls. Proceed?");

				if(confirm_command) {

					var post_id = this.dialog_apply_https_urls.site.id;

					var data = {
						'action': 'captaincore_install',
						'post_id': post_id,
						'command': command,
					};

					self = this;

					// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
					jQuery.post(ajaxurl, data, function(response) {

						self.dialog_apply_https_urls.site = "";
						self.dialog_apply_https_urls.show = false;
						self.snackbar.message = "Applying HTTPS Urls";
						self.snackbar.show = true;

					});


				}
		},
		viewApplyHttpsUrls( site_id ) {
			site = this.sites.filter(site => site.id == site_id)[0];
			this.dialog_apply_https_urls.show = true;
			this.dialog_apply_https_urls.site = site;
		},
		viewQuicksavesChanges( site_id, quicksave ) {

			quicksave.view_changes = true;

			var data = new URLSearchParams();
			data.append('action', 'captaincore_install');
			data.append('post_id', site_id);
			data.append('command', 'view_quicksave_changes');
			data.append('value', quicksave.git_commit);

			axios.post( ajaxurl, data)
			  .then( response => {
					quicksave.view_files = response.data.split("\n");
				})
			  .catch( error => console.log(error) );
		},
		viewQuicksaves ( site_id ) {
			site = this.sites.filter(site => site.id == site_id)[0];
			this.quicksave_dialog.show = true;
			this.quicksave_dialog.site_id = site_id;
			this.quicksave_dialog.site_name = site.name;

			axios.get(
				'/wp-json/captaincore/v1/site/'+site_id+'/quicksaves', {
					headers: {'X-WP-Nonce':wpApiSettings.nonce}
				})
				.then(response => this.quicksave_dialog.quicksaves = response.data );

		},
		addTheme ( site_id ){
			this.new_theme.show = true;
			this.new_theme.site_id = site_id;
			this.new_theme.site_name = this.sites.filter(site => site.id == site_id)[0].name;
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
				'post_id': site_id,
				'command': "manage",
				'value': "ssh",
				'background': true,
				'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
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
					'post_id': site_id,
					'command': "manage",
					'value': "ssh",
					'background': true,
					'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
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
		addPlugin ( site_id ){
			this.new_plugin.show = true;
			this.new_plugin.site_id = site_id;
			this.new_plugin.site_name = this.sites.filter(site => site.id == site_id)[0].name;
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
				'post_id': site_id,
				'command': "manage",
				'value': "ssh",
				'background': true,
				'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
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
					'post_id': site_id,
					'command': "manage",
					'value': "ssh",
					'background': true,
					'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
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
		update( site_id ) {
			site = this.sites.filter(site => site.id == site_id)[0];

			should_update = confirm("Apply all plugin/theme updates for " + site.name + "?");
			if (should_update) {

				// New job for progress tracking
				job_id = Math.round((new Date()).getTime());
				description = "Updating themes/plugins on " + site.name;
				this.jobs.push({"job_id": job_id,"description": description, "status": "running","command":"update-wp"});

				var data = {
					'action': 'captaincore_install',
					'post_id': site_id,
					'command': "update-wp",
					'background': true
				};

				self = this;

				jQuery.post(ajaxurl, data, function(response) {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response;

					// Check if completed in 2 seconds
					setTimeout(function() {
						self.jobRetry(site_id, response);
					}, 2000);
				});
			}
		},
		updateSettings( site_id ) {
			this.update_settings.show = true;
			this.update_settings.site_id = site_id;
			site = this.sites.filter(site => site.id == site_id)[0];
			this.update_settings.site_name = site.name;
			this.update_settings.exclude_plugins = site.exclude_plugins;
			this.update_settings.exclude_themes = site.exclude_themes;
			this.update_settings.updates_enabled = site.updates_enabled;
			this.update_settings.plugins = site.plugins;
			this.update_settings.themes = site.themes;
		},
		saveUpdateSettings() {
			this.update_settings.loading = true;
			site_id = this.update_settings.site_id;
			site = this.sites.filter(site => site.id == site_id)[0];
			self = this;

			site = this.sites.filter(site => site.id == site_id)[0];

			// Adds new job
			job_id = Math.round((new Date()).getTime());
			description = "Saving update settings for " + site.name;
			this.jobs.push({"job_id": job_id,"description": description, "status": "running", "command":"saveUpdateSettings"});

			// Prep AJAX request
			var data = {
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': "updateSettings",
				'value': { "exclude_plugins": this.update_settings.exclude_plugins, "exclude_themes": this.update_settings.exclude_themes, "updates_enabled": this.update_settings.updates_enabled }
			};

			site.exclude_plugins = self.update_settings.exclude_plugins;
			site.exclude_themes = self.update_settings.exclude_themes;
			site.updates_enabled = self.update_settings.updates_enabled;
			self.update_settings.show = false;
			self.update_settings.loading = false;

			jQuery.post(ajaxurl, data, function(response) {

				// Updates job id with reponsed background job id
				self.jobs.filter(job => job.job_id == job_id)[0].job_id = response;

				// Check if completed in 2 seconds
				setTimeout(function() {
					self.jobRetry(site_id, response);
				}, 2000);

			});


		},
		deleteUser (username, site_id) {
			should_delete = confirm("Are you sure you want to delete user " + username + "?");
			if (should_delete) {

				site_name = this.sites.filter(site => site.id == site_id)[0].name;
				description = "Delete user '" + username + "' from " + site_name;
				job_id = Math.round((new Date()).getTime());
				this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

				// WP ClI command to send
				wpcli = "wp user delete " + username;

				var data = {
					'action': 'captaincore_install',
					'post_id': site_id,
					'command': "manage",
					'value': "ssh",
					'background': true,
					'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
				};

				self = this;

				jQuery.post(ajaxurl, data, function(response) {
					updated_users = self.sites.filter(site => site.id == site_id)[0].users.filter(user => user.username != username);
					self.sites.filter(site => site.id == site_id)[0].users = updated_users;
					self.jobs.filter(job => job.job_id == job_id)[0].status = "done";
				});
			}
		},
		bulkactionLaunch() {
				this.sites_selected.forEach(site => window.open(site.home_url));
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
				self.snackbar.message = response;
				self.snackbar.show = true;
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
				this.paginatedSites.forEach(site => site.selected = true );
			}
			if (this.site_selected == "none") {
				this.sites.forEach(site => site.selected = false );
			}
		},
		filterFiles() {


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

			this.page = 1;

		}
	}
});


jQuery( document ).ready(function() {
	jQuery('.toggle_woocommerce_my_account a:visible').click();
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
