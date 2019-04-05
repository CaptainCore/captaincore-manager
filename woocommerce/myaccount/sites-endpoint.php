<?php

$user       = wp_get_current_user();
$role_check = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles ) + in_array( 'editor', $user->roles );
if ( $role_check ) {

	add_filter( 'body_class', 'my_body_classes' );
	function my_body_classes( $classes ) {

		$classes[] = 'woocommerce-account';
		return $classes;

	}

	$current_user = wp_get_current_user();
	$belongs_to = get_field("partner", "user_{$current_user->ID}");
	$business_name = get_the_title( $belongs_to[0] );
	$business_link = get_field( "partner_link", $belongs_to[0] );

?>

<link href="https://cdn.jsdelivr.net/npm/vuetify@1.5.4/dist/vuetify.min.css" rel="stylesheet">
<style>

html {
	font-size: 62.5%;
}

.table_users tbody tr td:nth-child(6) {
	 width:200px;
	 display:block;
}

.strikethrough {
	text-decoration: line-through;
}

.usage {
	font-size: 13px;
	margin: 0 4px;
}
.v-input {
	margin-top: 0px;
}
.v-tabs__container--icons-and-text {
	height: 54px;
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

table.v-table tbody td, table.v-table tbody th {
	border: 0px;
}

.quicksave-table table {
	width: auto;
}

.quicksave-table table.v-table tbody td, .quicksave-table table.v-table tbody th {
	height:40px;
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
	border:0px;
}
.v-btn--active, .v-btn:focus, .v-btn:hover {
	background: none;
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
<script src="https://cdn.jsdelivr.net/npm/vue@2.6.8/dist/vue.js"></script>
<script src="https://anchor.test/wp-content/plugins/captaincore-gui/public/js/qs.js"></script>
<script src="https://anchor.test/wp-content/plugins/captaincore-gui/public/js/axios.min.js"></script>
<?php } else { ?>
<script src="https://cdn.jsdelivr.net/npm/vue@2.6.8/dist/vue.min.js"></script>
<script src="https://unpkg.com/qs@6.5.2/dist/qs.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<?php } ?>
<script src="https://cdn.jsdelivr.net/npm/vuetify@1.5.4/dist/vuetify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue-upload-component@2.8.9/dist/vue-upload-component.js"></script>
<script>

ajaxurl = "/wp-admin/admin-ajax.php";

var pretty_timestamp_options = {
    weekday: "short", year: "numeric", month: "short",
    day: "numeric", hour: "2-digit", minute: "2-digit"
};
// Example: new Date("2018-06-18 19:44:47").toLocaleTimeString("en-us", options);
// Returns: "Monday, Jun 18, 2018, 7:44 PM"

Vue.component('file-upload', VueUploadComponent);
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
		<v-dialog v-model="dialog_fathom.show" max-width="500px">
		<v-card tile>
			<v-toolbar card dark color="primary">
				<v-btn icon dark @click.native="dialog_fathom.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>Configure Fathom for {{ dialog_fathom.site.name }}</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text>

				<v-progress-linear :indeterminate="true" v-if="dialog_fathom.loading"></v-progress-linear>
				
				<table>
				<tr v-for="tracker in dialog_fathom.site.fathom">
					<td><v-text-field v-model="tracker.domain" label="Domain"></v-text-field></td>
					<td><v-text-field v-model="tracker.code" label="Code"></v-text-field></td>
					<td>
						<v-icon small @click="deleteFathomItem(tracker)">delete</v-icon>
					</td>
				</tr>
				</table>
				<v-flex xs12 class="text-xs-right">
				<v-btn fab small @click="newFathomItem">
					<v-icon dark>add</v-icon>
				</v-btn>
				</v-flex>
				<v-flex xs12>
					<v-btn  color="primary" dark @click="saveFathomConfigurations()">Save Fathom configurations</v-btn>
				</v-flex>
		</v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_fathom.editItem" max-width="500px">
        <v-card>
          <v-card-title>
            <span class="headline">Edit Item</span>
          </v-card-title>

          <v-card-text>
            <v-container grid-list-md>
              <v-layout wrap>
                <v-flex xs12 sm6 md4>
                  <v-text-field v-model="dialog_fathom.editedItem.domain" label="Domain"></v-text-field>
                </v-flex>
                <v-flex xs12 sm6 md4>
                  <v-text-field v-model="dialog_fathom.editedItem.code" label="Code"></v-text-field>
                </v-flex>
              </v-layout>
            </v-container>
          </v-card-text>

          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn color="blue darken-1" flat @click="configureFathomClose">Cancel</v-btn>
            <v-btn color="blue darken-1" flat @click="configureFathomSave">Save</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
		<v-dialog v-model="dialog_update_settings.show" max-width="500px">
		<v-card tile>
			<v-toolbar card dark color="primary">
				<v-btn icon dark @click.native="dialog_update_settings.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>Update settings for {{ dialog_update_settings.site_name }}</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text>

				<v-switch label="Automatic Updates" v-model="dialog_update_settings.updates_enabled" :false-value="0" :true-value="1"></v-switch>

				<v-select
					:items="dialog_update_settings.plugins"
					item-text="title"
					item-value="name"
					v-model="dialog_update_settings.exclude_plugins"
					label="Excluded Plugins"
					multiple
					chips
					persistent-hint
				></v-select>
				<v-select
					:items="dialog_update_settings.themes"
					item-text="title"
					item-value="name"
					v-model="dialog_update_settings.exclude_themes"
					label="Excluded Themes"
					multiple
					chips
					persistent-hint
				></v-select>

				<v-progress-linear :indeterminate="true" v-if="dialog_update_settings.loading"></v-progress-linear>

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
					<v-chip v-else-if="job.status == 'error'" outline label color="red">Error</v-chip>
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
				v-model="dialog_new_site.show"
				fullscreen
				hide-overlay
				transition="dialog-bottom-transition"
				scrollable
			>
					<v-card tile>
						<v-toolbar card dark color="primary">
							<v-btn icon dark @click.native="dialog_new_site.show = false">
								<v-icon>close</v-icon>
							</v-btn>
							<v-toolbar-title>Add Site</v-toolbar-title>
								<v-spacer></v-spacer>
							</v-toolbar>
						<v-card-text>
							<v-container>
							<v-form ref="form">
								<v-autocomplete
								:items='[{"name":"WP Engine","value":"wpengine"},{"name":"Kinsta","value":"kinsta"}]'
								item-text="name"
								v-model="dialog_new_site.provider"
								label="Provider"
							></v-autocomplete>
								<v-text-field :value="dialog_new_site.domain" @change.native="dialog_new_site.domain = $event.target.value" label="Domain name" required></v-text-field>
						    <v-text-field :value="dialog_new_site.site" @change.native="dialog_new_site.site = $event.target.value" label="Site name" required></v-text-field>
								<v-autocomplete
								:items="customers"
								item-text="name"
								item-value="customer_id"
								v-model="dialog_new_site.customers"
								item-text="name"
								label="Customer"
								chips
								multiple
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
							</v-autocomplete>
							<v-autocomplete
							:items="developers"
							v-model="dialog_new_site.shared_with"
							label="Shared With"
							item-text="name"
							:return-object="true"
							chips
							multiple
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
						</v-autocomplete>
						
								<v-container grid-list-md text-xs-center>
									<v-layout row wrap>
										<v-flex xs12 style="height:0px">
										<v-btn @click="new_site_preload_staging" flat icon center relative color="green" style="top:32px;">
											<v-icon>cached</v-icon>
										</v-btn>
										</v-flex>
										<v-flex xs6 v-for="key in dialog_new_site.environments" :key="key.index">
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
											<v-text-field label="Home Directory" :value="key.home_directory" @change.native="key.home_directory = $event.target.value" required></v-text-field>
											<v-text-field label="Database Username" :value="key.database_username" @change.native="key.database_username = $event.target.value" required></v-text-field>
											<v-text-field label="Database Password" :value="key.database_password" @change.native="key.database_password = $event.target.value" required></v-text-field>
											<v-switch label="Automatic Updates" v-model="key.updates_enabled" false-value="0" true-value="1"></v-switch>
											<div v-if="typeof key.offload_enabled != 'undefined'">
												<v-switch label="Use Offload" v-model="key.offload_enabled" false-value="0" true-value="1" left></v-switch>
												<div v-if="key.offload_enabled == 1">
													<v-select label="Offload Provider" :value="key.offload_provider" @change.native="key.offload_provider = $event.target.value" :items='[{ provider:"s3", label: "Amazon S3" },{ provider:"do", label:"Digital Ocean" }]' item-text="label" item-value="provider" clearable></v-select>
													<v-text-field label="Offload Access Key" :value="key.offload_access_key" @change.native="key.offload_access_key = $event.target.value" required></v-text-field>
													<v-text-field label="Offload Secret Key" :value="key.offload_secret_key" @change.native="key.offload_secret_key = $event.target.value" required></v-text-field>
													<v-text-field label="Offload Bucket" :value="key.offload_bucket" @change.native="key.offload_bucket = $event.target.value" required></v-text-field>
													<v-text-field label="Offload Path" :value="key.offload_path" @change.native="key.offload_path = $event.target.value" required></v-text-field>
												</div>
											</div>
										</div>
								 </v-container>
							 </v-card>
							</v-flex>
							<v-alert
							:value="true"
							type="error"
							v-for="error in dialog_new_site.errors"
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
					v-model="dialog_modify_plan.show"
					transition="dialog-bottom-transition"
					width="500"
				>
				<v-card tile>
					<v-toolbar card dark color="primary">
						<v-btn icon dark @click.native="dialog_modify_plan.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Modify plan for {{ dialog_modify_plan.customer_name }}</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
						<v-layout row wrap>
						<v-flex xs12>
						<v-select
							@change="loadHostingPlan()"
							v-model="dialog_modify_plan.selected_plan"
							label="Plan Name"
							:items="hosting_plans.map( plan => plan.name )"
							:value="dialog_modify_plan.hosting_plan.name"
						></v-select>
						</v-flex>
						</v-layout>
						<v-layout v-if="typeof dialog_modify_plan.hosting_plan.name == 'string' && dialog_modify_plan.hosting_plan.name == 'Custom'" row wrap>
							<v-flex xs3 pa-1><v-text-field label="Storage (GBs)" :value="dialog_modify_plan.hosting_plan.storage_limit" @change.native="dialog_modify_plan.hosting_plan.storage_limit = $event.target.value"></v-text-field></v-flex>
							<v-flex xs3 pa-1><v-text-field label="Visits" :value="dialog_modify_plan.hosting_plan.visits_limit" @change.native="dialog_modify_plan.hosting_plan.visits_limit = $event.target.value"></v-text-field></v-flex>
							<v-flex xs3 pa-1><v-text-field label="Sites" :value="dialog_modify_plan.hosting_plan.sites_limit" @change.native="dialog_modify_plan.hosting_plan.sites_limit = $event.target.value"></v-text-field></v-flex>
							<v-flex xs3 pa-1><v-text-field label="Price" :value="dialog_modify_plan.hosting_plan.price" @change.native="dialog_modify_plan.hosting_plan.price = $event.target.value"></v-text-field></v-flex>
						</v-layout>
						<v-layout v-else row wrap>
							<v-flex xs3 pa-1><v-text-field label="Storage (GBs)" :value="dialog_modify_plan.hosting_plan.storage_limit" disabled></v-text-field></v-flex>
							<v-flex xs3 pa-1><v-text-field label="Visits" :value="dialog_modify_plan.hosting_plan.visits_limit" disabled></v-text-field></v-flex>
							<v-flex xs3 pa-1><v-text-field label="Sites" :value="dialog_modify_plan.hosting_plan.sites_limit" disabled ></v-text-field></v-flex>
							<v-flex xs3 pa-1><v-text-field label="Price" :value="dialog_modify_plan.hosting_plan.price" disabled ></v-text-field></v-flex>
						</v-layout>
						<h3 class="title" v-show="typeof dialog_modify_plan.hosting_addons == 'object' && dialog_modify_plan.hosting_addons" style="margin-top: 1em;">Addons</h3>
						<v-layout row wrap v-for="(addon, index) in dialog_modify_plan.hosting_addons">
						<v-flex xs7 pa-1>
							<v-textarea auto-grow rows="1" label="Name" :value="addon.name" @change.native="addon.name = $event.target.value">
						</v-flex>
						<v-flex xs2 pa-1>
							<v-text-field label="Quantity" :value="addon.quantity" @change.native="addon.quantity = $event.target.value">
						</v-flex>
						<v-flex xs2 pa-1>
							<v-text-field label="Price" :value="addon.price" @change.native="addon.price = $event.target.value">
						</v-flex>
						<v-flex xs1>
							<v-btn small flat icon @click="removeAddon(index)"><v-icon>delete</v-icon></v-btn>
						</v-flex>
						</v-layout>
						<v-btn small style="margin:0px;" @click="addAddon()">
							Add Addon
						</v-btn>
						<v-layout>
						<v-flex xs12 text-xs-right>
							<v-btn color="primary" dark style="margin:0px;" @click="updatePlan()">
								Save Changes
							</v-btn>
						</v-flex>
						</v-layout>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog
					v-model="dialog_mailgun.show"
					fullscreen
					hide-overlay
					transition="dialog-bottom-transition"
					scrollable
				>
				<v-card tile>
					<v-toolbar card dark color="primary">
						<v-btn icon dark @click.native="dialog_mailgun.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Mailgun Logs for {{ dialog_mailgun.site.name }} (Last 30 days)</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-progress-linear :indeterminate="true" v-show="dialog_mailgun.loading"></v-progress-linear>
						<v-expansion-panel>
						<v-expansion-panel-content
							v-for="event in dialog_mailgun.response"
							>
							<div slot="header"><v-icon>event_note</v-icon> {{ event.timestamp }} <small style="padding-left:40px;">{{ event.description }}</small></div>
							<v-card>
								<v-card-text><pre>{{ event.event }}</pre></v-card-text>
							</v-card>
							</v-expansion-panel-content>
						</v-expansion-panel>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog
					v-model="dialog_backup_snapshot.show"
					width="500"
					transition="dialog-bottom-transition"
				>
				<v-card tile>
					<v-toolbar card dark color="primary">
						<v-btn icon dark @click.native="dialog_backup_snapshot.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Download Snapshot {{ dialog_backup_snapshot.site.name }} </v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-text-field name="Email" v-model="dialog_backup_snapshot.email"></v-text-field>
					
							<v-switch v-model="dialog_backup_snapshot.filter_toggle" label="Everything"></v-switch>
							<div v-show="dialog_backup_snapshot.filter_toggle === false">
								<v-checkbox small hide-details v-model="dialog_backup_snapshot.filter_options" label="Database" value="database"></v-checkbox>
 								<v-checkbox small hide-details v-model="dialog_backup_snapshot.filter_options" label="Themes" value="themes"></v-checkbox>
								<v-checkbox small hide-details v-model="dialog_backup_snapshot.filter_options" label="Plugins" value="plugins"></v-checkbox>
								<v-checkbox small hide-details v-model="dialog_backup_snapshot.filter_options" label="Uploads" value="uploads"></v-checkbox>
								<v-checkbox small hide-details v-model="dialog_backup_snapshot.filter_options" label="Everything Else" value="everything-else"></v-checkbox>
								<v-spacer><br /></v-spacer>
							</div>
						<v-btn @click="downloadBackupSnapshot()">
							Download Snapshot
						</v-btn>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog
					v-model="dialog_toggle.show"
					fullscreen
					hide-overlay
					transition="dialog-bottom-transition"
					scrollable
				>
				<v-card tile>
					<v-toolbar card dark color="primary">
						<v-btn icon dark @click.native="dialog_toggle.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Toggle Site {{ dialog_toggle.site.name }}</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-layout row wrap>
						 <v-flex xs6 pa-2>
							 <v-card>
								 <v-card-title primary-title>
									<div>
										<h3 class="headline mb-0">Deactivate Site</h3>
									</div>
								  </v-card-title>
									<v-card-text>
										<p>Will apply deactivate message with the following link back to the site owner.</p>
										<v-text-field label="Business Name" :value="dialog_toggle.business_name"></v-text-field>
										<v-text-field label="Business Link" :value="dialog_toggle.business_link"></v-text-field>
										<v-btn @click="DeactivateSite(dialog_toggle.site.id)">
											Deactivate Site
										</v-btn>
									</v-card-text>
							 </v-card>
						 </v-flex>
						 <v-flex xs6 pa-2>
							 <v-card>
								 <v-card-title primary-title>
									<div>
										<h3 class="headline mb-0">Activate Site</h3>
									</div>
								  </v-card-title>
									<v-card-text>
										<v-btn @click="ActivateSite(dialog_toggle.site.id)">
											Activate Site
										</v-btn>
									</v-card-text>
							 </v-card>
						 </v-flex>
					 </v-layout>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog
					v-model="dialog_copy_site.show"
					fullscreen
					hide-overlay
					transition="dialog-bottom-transition"
					scrollable
				>
				<v-card tile>
					<v-toolbar card dark color="primary">
						<v-btn icon dark @click.native="dialog_copy_site.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Copy Site {{ dialog_copy_site.site.name }} to </v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-autocomplete
						:items="dialog_copy_site.options"
						v-model="dialog_copy_site.destination"
						label="Select Destination Site"
						item-text="name"
						item-value="id"
						chips
						small-chips
						deletable-chips
						></v-autocomplete>
						<v-btn @click="startCopySite()">
							Copy Site
						</v-btn>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog
					v-model="dialog_edit_site.show"
					fullscreen
					hide-overlay
					transition="dialog-bottom-transition"
					scrollable
				>
				<v-card tile>
					<v-toolbar card dark color="primary">
						<v-btn icon dark @click.native="dialog_edit_site.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Edit Site {{ dialog_edit_site.site.name }}</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-form ref="form">
						<v-autocomplete
							:items='[{"name":"WP Engine","value":"wpengine"},{"name":"Kinsta","value":"kinsta"}]'
							item-text="name"
							v-model="dialog_edit_site.site.provider"
							label="Provider"
						></v-autocomplete>
							<v-text-field :value="dialog_edit_site.site.name" @change.native="dialog_edit_site.site.name = $event.target.value" label="Domain name" required></v-text-field>
							<v-text-field :value="dialog_edit_site.site.site" @change.native="dialog_edit_site.site.site = $event.target.value" label="Site name (not changeable)" disabled></v-text-field>
							<v-autocomplete
							:items="customers"
							item-text="name"
							v-model="dialog_edit_site.site.customer"
							:return-object="true"
							label="Customer"
							chips
							multiple
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
						</v-autocomplete>
						<v-autocomplete
						:items="developers"
						v-model="dialog_edit_site.site.shared_with"
						item-text="name"
						:return-object="true"
						label="Shared With"
						chips
						multiple
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
					</v-autocomplete>
							<v-container grid-list-md text-xs-center>
								<v-layout row wrap>
									<v-flex xs12 style="height:0px">
									<v-btn @click="new_site_preload_staging" flat icon center relative color="green" style="top:32px;">
										<v-icon>cached</v-icon>
									</v-btn>
									</v-flex>
									<v-flex xs6 v-for="key in dialog_edit_site.site.environments" :key="key.index">
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
										<v-text-field label="Home Directory" :value="key.home_directory" @change.native="key.home_directory = $event.target.value" required></v-text-field>
										<v-text-field label="Database Username" :value="key.database_username" @change.native="key.database_username = $event.target.value" required></v-text-field>
										<v-text-field label="Database Password" :value="key.database_password" @change.native="key.database_password = $event.target.value" required></v-text-field>
										<div v-if="typeof key.offload_enabled != 'undefined'">
											<v-switch label="Use Offload" v-model="key.offload_enabled" false-value="0" true-value="1" left></v-switch>
											<div v-if="key.offload_enabled == '1'">
												<v-text-field label="Offload Access Key" :value="key.offload_access_key" @change.native="key.offload_access_key = $event.target.value" required></v-text-field>
												<v-text-field label="Offload Secret Key" :value="key.offload_secret_key" @change.native="key.offload_secret_key = $event.target.value" required></v-text-field>
												<v-text-field label="Offload Bucket" :value="key.offload_bucket" @change.native="key.offload_bucket = $event.target.value" required></v-text-field>
												<v-text-field label="Offload Path" :value="key.offload_path" @change.native="key.offload_path = $event.target.value" required></v-text-field>
											</div>
										</div>
									</div>
							 </v-container>
						 </v-card>
						</v-flex>
						<v-alert
						:value="true"
						type="error"
						v-for="error in dialog_edit_site.errors"
						>
						{{ error }}
						</v-alert>
						
						<v-flex xs12 text-xs-right>
							<v-btn right @click="submitEditSite">
								Save Changes
							</v-btn>
							<v-progress-linear :indeterminate="true" v-show="dialog_edit_site.loading"></v-progress-linear>
							
						</v-flex>
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
					v-model="dialog_file_diff.show"
					fullscreen
					hide-overlay
					transition="dialog-bottom-transition"
					scrollable
				>
				<v-card tile>
					<v-toolbar card dark color="primary">
						<v-btn icon dark @click.native="dialog_file_diff.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>File diff {{ dialog_file_diff.file_name}}</v-toolbar-title>
						<v-spacer></v-spacer>
						<v-toolbar-items class="hidden-sm-and-down">
							<v-btn flat @click="QuicksaveFileRestore()">Restore this file</v-btn>
						</v-toolbar-items>
					</v-toolbar>
					<v-card-text>
						<v-container v-show="dialog_file_diff.loading"><v-progress-linear :indeterminate="true"></v-progress-linear></v-container>
						<v-container v-html="dialog_file_diff.response" style='font-family:SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;'></v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
			<v-container fluid v-show="loading_sites != true">
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
				<v-switch inline-block v-model="advanced_filter" class="alignright" style="float:right;padding: 12px 0px;"></v-switch>
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
			<v-autocomplete
			:items="site_filters"
			item-text="search"
			item-value="name"
			v-model="applied_site_filter"
			@input="filterSites"
			item-text="title"
			label="Select Theme and/or Plugin"
			chips
			multiple
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
			</v-autocomplete>
			</v-flex>
		</v-layout>
		<v-layout row v-if="advanced_filter == true">
			<v-flex xs5>
				 <v-autocomplete
				 v-model="applied_site_filter_version"
				 v-for="filter in site_filter_version"
					 :items="filter.versions"
					 :key="filter.name"
					 :label="'Select Version for '+ filter.name"
					 @input="filterSites"
					 item-text="title"
					 chips
					 multiple
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
				</v-autocomplete>
			</v-flex>
			<v-flex xs2>
			</v-flex>
			<v-flex xs5>
				<v-autocomplete
				v-model="applied_site_filter_status"
				v-for="filter in site_filter_status"
					:items="filter.statuses"
					:key="filter.name"
					:label="'Select Status for '+ filter.name"
					@input="filterSites"
					item-text="title"
					chips
					multiple
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
				</v-autocomplete>
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

			<div class="text-xs-right" v-if="typeof dialog_new_site == 'object'">
			<v-btn small dark color="blue darken-3" @click="dialog_new_site.show = true">Add Site
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
									    <span v-show="site.subsite_count" class="usage"><v-icon small light>fas fa-network-wired</i></v-icon> Multisite - {{ site.subsite_count }} sites</span>
										<span v-show="site.provider" class="usage"><v-icon small light>fas fa-server</v-icon> {{ site.provider | formatProvider }}</span>
										<span v-show="site.visits" class="usage"><v-icon small light>fas fa-eye</v-icon> {{ site.visits }} <small>yearly</small></span>
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
								<v-tab :key="6" href="#tab-SitePlan" ripple @click="viewUsageBreakdown( site.id )">
									Site Plan <v-icon>far fa-list-alt</v-icon>
								</v-tab>
								<v-tab :key="7" href="#tab-Sharing" ripple>
									Sharing <v-icon>fas fa-user-lock</v-icon>
								</v-tab>
								<v-tab :key="9" href="#tab-Advanced" ripple>
									Advanced <v-icon>fas fa-cogs</v-icon>
								</v-tab>
							</v-tabs>
						<v-tabs-items v-model="site.tabs">

							<v-tab-item value="tab-Site-Management">

								<v-tabs v-model="site.tabs_management" color="grey lighten-4" right icons-and-text>
									<v-select
										v-model="site.environment_selected"
										:items='[{"name":"Production Environment","value":"Production"},{"name":"Staging Environment","value":"Staging"}]'
										item-text="name"
										item-value="value"
										light
										style="max-width: 204px; margin: 0px 1em 0px 16px; top: 0px;">
									</v-select>
									<v-btn small icon @click="syncSite( site.id )" style="margin: 14px auto 0 0;">
										<v-icon small color="grey">fas fa-sync</v-icon>
									</v-btn>
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
									<v-tab key="Backups" href="#tab-Backups" @click="viewQuicksaves( site.id )">
										Backups <v-icon small style="margin-left:7px;">fas fa-hdd</v-icon>
									</v-tab>
								</v-tabs>
								<v-tabs-items v-model="site.tabs_management" v-if="site.environments.filter( key => key.environment == site.environment_selected ).length == 1">
									<v-tab-item :key="1" value="tab-Keys">
										<v-toolbar color="grey lighten-4" dense light flat>
											<v-toolbar-title>Keys</v-toolbar-title>
											<v-spacer></v-spacer>
										</v-toolbar>

										<v-card v-for="key in site.environments" v-show="key.environment == site.environment_selected">

											<v-container fluid style="padding-top: 10px;">
											<v-layout align-start justify-space-between/>
											<div row>
											<div><h3 class="headline mb-0" style="margin-top:10px;"><a :href="key.link" target="_blank">{{ key.link }}</a></h3></div>
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
												 <div><span class="caption">Database Password</span> <div class="pass-mask">##########</div><div class="pass-reveal">{{ key.database_password }}</div></div>
												 </div>
												 <hr />
												 <div v-if="key.ssh">{{ key.ssh }}</div>
											 </div>

										 </div>
										</v-layout>
										 
									 </v-container>
								 </v-card>
								</v-tab-item>
								<v-tab-item :key="2" value="tab-Themes">
									<v-toolbar color="grey lighten-4" dense light flat>
										<v-toolbar-title>Themes</v-toolbar-title>
										<v-spacer></v-spacer>
										<v-toolbar-items>
											<v-btn flat @click="bulkEdit(site.id,'themes')" v-if="site.themes_selected.length != 0">Bulk Edit {{ site.themes_selected.length }} themes</v-btn>
											<v-btn flat @click="addTheme(site.id)">Add Theme <v-icon dark small>add</v-icon></v-btn>
										</v-toolbar-items>
									</v-toolbar>
									<v-card 
									v-for="key in site.environments"
									v-show="key.environment == site.environment_selected"
									>
									<v-card-title v-if="typeof key.themes == 'string'">
										<div>
											Updating themes...
											<v-progress-linear :indeterminate="true"></v-progress-linear>
										</div>
									</v-card-title>
									<div v-else>
									<v-data-table
										v-model="site.themes_selected"
										:headers="headers"
										:items="key.themes"
										:loading="site.loading_themes"
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
												<v-switch hide-details v-model="props.item.status" false-value="inactive" true-value="active" @change="activateTheme(props.item.name, site.id)"></v-switch>
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
								</div>
							</v-tab-item>
			<v-tab-item :key="3" value="tab-Plugins">
				<v-toolbar color="grey lighten-4" dense light flat>
					<v-toolbar-title>Plugins</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn flat @click="bulkEdit(site.id, 'plugins')" v-if="site.plugins_selected.length != 0">Bulk Edit {{ site.plugins_selected.length }} plugins</v-btn>
						<v-btn flat @click="addPlugin(site.id)">Add Plugin <v-icon dark small>add</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card 
					v-for="key in site.environments"
					v-show="key.environment == site.environment_selected"
				>
				<v-card-title v-if="typeof key.plugins == 'string'">
					<div>
						Updating plugins...
						<v-progress-linear :indeterminate="true"></v-progress-linear>
					</div>
				</v-card-title>
				<div v-else>
				<v-data-table
					:headers="headers"
					:items="key.plugins.filter(plugin => plugin.status != 'must-use' && plugin.status != 'dropin')"
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
							<v-switch hide-details v-model="props.item.status" false-value="inactive" true-value="active" @change="togglePlugin(props.item.name, props.item.status, site.id)"></v-switch>
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
				 <template slot="footer" v-for="plugin in key.plugins.filter(plugin => plugin.status == 'must-use' || plugin.status == 'dropin')">
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
			 </div>
		  </v-tab-item>
			<v-tab-item :key="4" value="tab-Users">
				<v-toolbar color="grey lighten-4" dense light flat>
					<v-toolbar-title>Users</v-toolbar-title>
					<v-spacer></v-spacer v-show="site.environment_selected == 'Production'">
					<v-toolbar-items>
						<v-btn flat @click="bulkEdit(site.id,'users')" v-if="site.users_selected.length != 0">Bulk Edit {{ site.users_selected.length }} users</v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card 
					v-for="key in site.environments"
					v-show="key.environment == site.environment_selected"
					>
					<v-card-title v-if="typeof key.users == 'string'">
						<div>
							Updating users...
						  <v-progress-linear :indeterminate="true"></v-progress-linear>
						</div>
					</v-card-title>
					<div v-else>
						<v-data-table
							:headers='header_users'
							:pagination.sync="site.pagination"
							:rows-per-page-items='[50,100,250,{"text":"All","value":-1}]'
							:items="key.users"
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
								<td>{{ props.item.roles.split(",").join(" ") }}</td>
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
			<v-tab-item :key="5" value="tab-Updates">
				<v-toolbar color="grey lighten-4" dense light flat>
					<v-toolbar-title>Update Logs</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn flat @click="update(site.id)">Manual update <v-icon dark small>fas fa-sync-alt</v-icon></v-btn>
						<v-btn flat @click="updateSettings(site.id)">Update Settings <v-icon dark small>fas fa-cog</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card 
				  v-for="key in site.environments"
					v-show="key.environment == site.environment_selected" >
					<v-card-title v-if="typeof key.update_logs == 'string'">
						<div>
							Fetching update logs...
						  <v-progress-linear :indeterminate="true"></v-progress-linear>
						</div>
					</v-card-title>
					<div v-else>
							<v-data-table
								:headers='header_updatelog'
								:items="key.update_logs"
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
			<v-tab-item :key="6" value="tab-Scripts">
				<v-toolbar color="grey lighten-4" dense light flat>
					<v-toolbar-title>Scripts</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-card>
					<v-card-title>
						<div>
							<div><v-btn small flat @click="viewApplyHttpsUrls(site.id)">
								<v-icon>launch</v-icon> <span>Apply HTTPS Urls</span>
							</v-btn></div>
							<div v-show="site.mailgun"><v-btn small flat @click="viewMailgunLogs(site.id)" >
								<v-icon>email</v-icon> <span>View Mailgun Logs</span>
							</v-btn></div>
							<div><v-btn small flat @click="siteDeploy(site.id)">
								<v-icon>loop</v-icon> <span>Deploy users/plugins</span>
							</v-btn></div>
							<div><v-btn small flat @click="toggleSite(site.id)">
								<v-icon>fas fa-toggle-on</v-icon><span>Toggle Site</span>
							</v-btn></div>
						</div>
					</v-card-title>
				</v-card>
			</v-tab-item>
			<v-tab-item :key="7" value="tab-Backups">
				<v-toolbar color="grey lighten-4" dense light flat>
					<v-toolbar-title>Quicksaves</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn flat @click="promptBackupSnapshot( site.id )">Download Backup Snapshot <v-icon dark small>fas fa-cloud-download-alt</v-icon></v-btn>
						<v-btn flat @click="QuicksaveCheck( site.id )">Manual Check <v-icon dark small>fas fa-sync-alt</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
						<v-card 
						v-for="key in site.environments"
						v-show="key.environment == site.environment_selected">
							<v-card-title v-if="typeof key.quicksaves == 'string'">
								<div>
									Fetching quicksaves...
									<v-progress-linear :indeterminate="true"></v-progress-linear>
								</div>
							</v-card-title>
							<v-card-title v-else-if="key.quicksaves.length == 0">
								<div>
									No quicksaves found.
								</div>
							</v-card-title>
							<div v-else>
							<v-expansion-panel v-model="key.quicksave_panel">
							  <v-expansion-panel-content v-for="quicksave in key.quicksaves" lazy style="position:relative;">
							    <div slot="header">
									<v-layout align-center justify-space-between row>
						<div>
										<v-layout align-center justify-start fill-height/>
											<v-icon>settings_backup_restore</v-icon> {{ quicksave.created_at | pretty_timestamp }}</span>
										</v-layout>
									</div>
									<div class="body-1">{{ quicksave.git_status }}</div>
									<div class="body-1 text-xs-right">
										WordPress {{ quicksave.core }} - {{ quicksave.plugins.length }} Plugins - {{ quicksave.themes.length }} Themes
									</div>
								</v-layout>
						</div>
									<v-toolbar color="dark primary" dark dense light>
										<v-toolbar-title></v-toolbar-title>
										<v-spacer></v-spacer>
										<v-toolbar-items>
											<v-btn flat @click="QuicksavesRollback( site.id, quicksave)">Entire Quicksave Rollback</v-btn>
											<v-btn flat @click="viewQuicksavesChanges( site.id, quicksave)">View Changes</v-btn>
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
									        v-model="quicksave.search"
													@input="filterFiles( site.id, quicksave.quicksave_id)"
									        append-icon="search"
									        label="Search"
									        single-line
									        hide-details
									      ></v-text-field>
						</v-card-title>
											<v-data-table hide-actions no-data-text="" :headers='[{"text":"File","value":"file"}]' :items="quicksave.filtered_files" :loading="quicksave.loading">
												<template slot="items" slot-scope="props">
												 <td>
													 <a class="v-menu__activator" @click="QuicksaveFileDiff(quicksave.site_id, quicksave.quicksave_id, quicksave.git_commit, props.item)"> {{ props.item }} </a>
												 </td>
											 </template>
											 <v-alert slot="no-results" :value="true" color="error" icon="warning">
													Your search for "{{ quicksave.search }}" found no results.
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
											 <tr v-bind:class="{ 'green lighten-5': props.item.changed_version || props.item.changed_status }">
												<td>{{ props.item.title }}</td>
												<td v-bind:class="{ 'green lighten-4': props.item.changed_version }">{{ props.item.version }}</td>
												<td v-bind:class="{ 'green lighten-4': props.item.changed_status }">{{ props.item.status }}</td>
												<td><v-btn flat small @click="RollbackQuicksave(quicksave.site_id, quicksave.quicksave_id, 'theme', props.item.name)">Rollback</v-btn></td>
										  </tr>
											 </template>
											 <template slot="footer">
											 <tr class="red lighten-4 strikethrough" v-for="theme in quicksave.deleted_themes">
												<td>{{ theme.title || theme.name }}</td>
												<td>{{ theme.version }}</td>
												<td>{{ theme.status }}</td>
												<td></td>
											 </tr>
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
											 <tr v-bind:class="[{ 'green lighten-5': props.item.changed_version || props.item.changed_status },{ 'red lighten-4 strikethrough': props.item.deleted }]">
												<td>{{ props.item.title || props.item.name }}</td>
												<td v-bind:class="{ 'green lighten-4': props.item.changed_version }">{{ props.item.version }}</td>
												<td v-bind:class="{ 'green lighten-4': props.item.changed_status }">{{ props.item.status }}</td>
												<td><v-btn flat small @click="RollbackQuicksave(quicksave.site_id, quicksave.quicksave_id, 'plugin', props.item.name)">Rollback</v-btn></td>
											 </tr>
											 </template>
											 <template slot="footer">
											 <tr class="red lighten-4 strikethrough" v-for="plugin in quicksave.deleted_plugins">
												<td>{{ plugin.title || plugin.name }}</td>
												<td>{{ plugin.version }}</td>
												<td>{{ plugin.status }}</td>
												<td></td>
											 </tr>
											 </template>

											</v-data-table>
							    </v-card>
							  </v-expansion-panel-content>
							</v-expansion-panel>
							</div>
					</v-card>
			</v-tab-item>
		</v-tabs-items>
		<v-card v-if="site.environments.filter( key => key.environment == site.environment_selected ).length == 0">
			<v-container fluid>
			 <div><span>{{ site.environment_selected }} environment not created.</span></div>
		 </v-container>
		</v-card>
		</v-tab-item>
		<v-tab-item :key="6" value="tab-SitePlan">
			<v-toolbar color="grey lighten-4" dense light flat>
				<v-toolbar-title>Site Plan</v-toolbar-title>
				<v-spacer></v-spacer>
					<v-toolbar-items v-show="role == 'administrator'">
						<v-btn flat @click="modifyPlan( site.id )">Modify Plan <v-icon dark small>edit</v-icon></v-btn>
					</v-toolbar-items>
			</v-toolbar>
			<v-card>
				<div v-for="customer in site.customer">
				<div v-if="typeof customer.hosting_plan.visits_limit == 'string'">
				<v-card-text>
				<v-layout align-center justify-left row/>
					<div style="padding: 10px 10px 10px 20px;">
						<v-progress-circular :size="50" :value="( customer.usage.storage / ( customer.hosting_plan.storage_limit * 1024 * 1024 * 1024 ) ) * 100 | formatPercentage" color="primary"><small>{{ ( customer.usage.storage / ( customer.hosting_plan.storage_limit * 1024 * 1024 * 1024 ) ) * 100 | formatPercentage }}</small></v-progress-circular>
					</div>
					<div style="line-height: 0.85em;">
						Storage <br /><small>{{ customer.usage.storage | formatGBs }}GB / {{ customer.hosting_plan.storage_limit }}GB</small><br />
					</div>
					<div style="padding: 10px 10px 10px 20px;">
						<v-progress-circular :size="50" :value="( customer.usage.visits / customer.hosting_plan.visits_limit * 100 ) | formatPercentage" color="primary"><small>{{ ( customer.usage.visits / customer.hosting_plan.visits_limit ) * 100 | formatPercentage }}</small></v-progress-circular>
					</div>
					<div style="line-height: 0.85em;">
						Visits <br /><small>{{ customer.usage.visits | formatLargeNumbers }} / {{ customer.hosting_plan.visits_limit | formatLargeNumbers }}</small><br />
					</div>
					<div style="padding: 10px 10px 10px 20px;">
						<v-progress-circular :size="50" :value="( customer.usage.sites / customer.hosting_plan.sites_limit * 100 ) | formatPercentage" color="blue darken-4"><small>{{ ( customer.usage.sites / customer.hosting_plan.sites_limit * 100 ) | formatPercentage }}</small></v-progress-circular>
					</div>
					<div  style="line-height: 0.85em;">
						Sites <br /><small>{{ customer.usage.sites }} / {{ customer.hosting_plan.sites_limit }}</small><br />
					</div>
				</v-layout>
				</v-card-text>
				<v-alert
					:value="true"
					type="info"
					color="primary"
				>
					<strong>{{ customer.hosting_plan.name }} Plan</strong> which supports up to {{ customer.hosting_plan.visits_limit | formatLargeNumbers }} visits, {{ customer.hosting_plan.storage_limit }}GB storage and {{ customer.hosting_plan.sites_limit }} sites.
				</v-alert>
				</div>
				<div v-else>
				<v-alert
					:value="true"
					type="info"
					color="primary"
				>
					Development mode, no plan selected.
				</v-alert>
				</div>
				</div>
				<v-data-table
					:headers='[{"text":"Name","value":"name"},{"text":"Storage","value":"Storage"},{"text":"Visits","value":"visits"}]'
					:items="site.usage_breakdown.sites"
					item-key="name"
					hide-actions
				>
					<template slot="items" slot-scope="props">
						<td>{{ props.item.name }}</td>
						<td>{{ props.item.storage }}GB</td>
						<td>{{ props.item.visits }}</td>
					</template>
					<template slot="footer">
						<tr>
							<td>Totals:</td>
							<td v-for="total in site.usage_breakdown.total" v-html="total"></td>
						</tr>
					</template>
				</v-data-table>
			</v-card>
		</v-tab-item>
		<v-tab-item :key="7" value="tab-Sharing">
			<v-toolbar color="grey lighten-4" dense light flat>
				<v-toolbar-title>Sharing</v-toolbar-title>
				<v-spacer></v-spacer>
				<v-toolbar-items v-show="role == 'administrator'">
					<v-btn flat>Invite</v-btn>
				</v-toolbar-items>
			</v-toolbar>
			<v-layout>
				<v-list subheader>
						<v-subheader inset>Customer</v-subheader>
						<v-list-tile v-for="customer in site.customer" :key="customer.customer_id" avatar @click="">
							<v-list-tile-avatar>
								<v-icon>fas fa-user</v-icon>
							</v-list-tile-avatar>
							<v-list-tile-content>
	              <v-list-tile-title>{{ customer.name }}</v-list-tile-title>
	            </v-list-tile-content>
	          </v-list-tile>
	          <v-divider inset></v-divider>
	          <v-subheader inset>Shared With</v-subheader>
	          <v-list-tile v-for="customer in site.shared_with" :key="customer.customer_id" avatar @click="">
	            <v-list-tile-avatar>
	              <v-icon>fas fa-user</v-icon>
	            </v-list-tile-avatar>
	            <v-list-tile-content>
	              <v-list-tile-title>{{ customer.name }}</v-list-tile-title>
	            </v-list-tile-content>
	          </v-list-tile>
	        </v-list>
		</v-layout>
	  </v-tab-item>
		<v-tab-item :key="9" value="tab-Advanced">
			<v-toolbar color="grey lighten-4" dense light flat>
				<v-toolbar-title>Advanced</v-toolbar-title>
				<v-spacer></v-spacer>
				<v-toolbar-items>
					<v-btn flat @click="copySite(site.id)">Copy Site <v-icon dark small>file_copy</v-icon></v-btn>
					<v-btn flat @click="editSite(site.id)" v-show="role == 'administrator'">Edit Site <v-icon dark small>edit</v-icon></v-btn>
					<v-btn flat @click="deleteSite(site.id)" v-show="role == 'administrator'">Remove Site <v-icon dark small>delete</v-icon></v-btn>
				</v-toolbar-items>
			</v-toolbar>
			<v-card>
				<v-card-title>
					<div>
						<div v-show="site.provider == 'kinsta'">
						<v-btn left small flat @click="PushProductionToStaging( site.id )">
							<v-icon>local_shipping</v-icon> <span>Push Production to Staging</span>
						</v-btn>
						</div>
						<div v-show="site.provider == 'kinsta'">
						<v-btn left small flat @click="PushStagingToProduction( site.id )">
							<v-icon class="reverse">local_shipping</v-icon> <span>Push Staging to Production</span>
						</v-btn>
						</div>
						<div v-if="typeof dialog_new_site == 'object'">
						<v-btn left small flat @click="configureFathom( site.id )">
							<v-icon>bar_chart</v-icon>
							<span>Configure Fathom Tracker</span>
						</v-btn>
						</div>
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
			<v-container fluid v-show="loading_sites">
				Loading...
			</v-container>
			<v-snackbar
				:timeout="3000"
				:multi-line="true"
				v-model="snackbar.show"
				style="z-index: 9999999;"
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
							 <v-autocomplete
								 :items="bulk_actions"
								 item-text="name"
								 item-value="value"
								 v-model="select_bulk_action"
								 label="Script/Command"
								 @input="argumentsForActions"
								 single-line
								 chips
								 multiple
							 ></v-autocomplete>
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

new Vue({
	el: '#app',
	data: {
		loading_sites: true,
		dialog: false,
		dialog_apply_https_urls: { show: false, site: {} },
		dialog_copy_site: { show: false, site: {}, options: [], destination: "" },
		dialog_edit_site: { show: false, site: {}, loading: false },
		dialog_backup_snapshot: { show: false, site: {}, email: "<?php echo $current_user->user_email; ?>", current_user_email: "<?php echo $current_user->user_email; ?>", filter_toggle: true, filter_options: [] },
		dialog_file_diff: { show: false, response: "", loading: false, file_name: "" },
		dialog_mailgun: { show: false, site: {}, response: "", loading: false },
		dialog_modify_plan: { show: false, site: {}, hosting_plan: {}, hosting_addons: [], selected_plan: "", customer_name: "" },
		dialog_toggle: { show: false, site: {} },
		dialog_theme_and_plugin_checks: { show: false, site: {}, loading: false },
		dialog_update_settings: { show: false, site_id: null, loading: false },
		dialog_fathom: { show: false, site: {}, loading: false, editItem: false, editedItem: {}, editedIndex: -1 },
		page: 1,
		jobs: [],
		current_user_email: "<?php echo $current_user->user_email; ?>",
		hosting_plans: <?php 
			$hosting_plans = get_field( "hosting_plans", "option" );
			$hosting_plans[] = array("name" => "Custom", "visits_limit" => "", "storage_limit" => "", "sites_limit" => "", "price" => "" );
		echo json_encode($hosting_plans); ?>,
		<?php if ( current_user_can('administrator') ) { ?>
		role: "administrator",
		dialog_new_site: {
			provider: "kinsta",
			show: false,
			domain: "",
			errors: [],
			shared_with: [],
			customers: [],
			environments: [
				{"environment": "Production", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","home_directory":"","database_username":"","database_password":"",updates_enabled: "1","offload_enabled": false,"offload_provider":"","offload_access_key":"","offload_secret_key":"","offload_bucket":"","offload_path":"" },
				{"environment": "Staging", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","home_directory":"","database_username":"","database_password":"",updates_enabled: "1","offload_enabled": false,"offload_provider":"","offload_access_key":"","offload_secret_key":"","offload_bucket":"","offload_path":"" }
			],
		},
		customers: [],
		shared_with: [

		],
		<?php } else { ?>
		role: "",
		dialog_new_site: false,
		customers: [],
		shared_with: [],<?php } ?>
		new_plugin: { show: false, site_id: null},
		new_theme: { show: false, site_id: null},
		bulk_edit: { show: false, site_id: null, type: null, items: [] },
		upload: [],
		view_jobs: false,
		search: null,
		advanced_filter: false,
		items_per_page: 50,
		business_name: "<?php echo $business_name; ?>",
		business_link: "<?php echo $business_link; ?>",
		site_selected: null,
		site_filters: [],
		site_filter_version: null,
		site_filter_status: null,
		sites: [],
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
		applied_site_filter: [],
		applied_site_filter_version: [],
		applied_site_filter_status: [],
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
		 snackbar: { show: false, message: "" }
	},
	filters: {
		formatProvider: function (value) {
			if (value == 'wpengine') {
				return "WP Engine"
			}
			if (value == 'kinsta') {
				return "Kinsta"
			}
		},
		formatSize: function (fileSizeInBytes) {
    var i = -1;
    var byteUnits = [' kB', ' MB', ' GB', ' TB', 'PB', 'EB', 'ZB', 'YB'];
    do {
        fileSizeInBytes = fileSizeInBytes / 1024;
        i++;
    } while (fileSizeInBytes > 1024);

    return Math.max(fileSizeInBytes, 0.1).toFixed(1) + byteUnits[i];
		},
		formatGBs: function (fileSizeInBytes) {
			fileSizeInBytes = fileSizeInBytes / 1024 / 1024 / 1024;
			return Math.max(fileSizeInBytes, 0.1).toFixed(2);
		},
		formatLargeNumbers: function (number) {
			if ( isNaN(number) || number == null ) {
				return null;
			} else {
				return number.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,');
			}
		},
		formatPercentage: function (percentage) {
			return Math.max(percentage, 0.1).toFixed(0);
		},
		pretty_timestamp: function (date) {
			// takes in '2018-06-18 19:44:47' then returns "Monday, Jun 18, 2018, 7:44 PM"
			formatted_date = new Date(date).toLocaleTimeString("en-us", pretty_timestamp_options);
			return formatted_date;
		}
	},
	mounted() {
		axios.get(
				'/wp-json/captaincore/v1/customers', {
					headers: {'X-WP-Nonce':wpApiSettings.nonce}
				})
				.then(response => {
					this.customers = response.data;
				});
		axios.get(
				'/wp-json/captaincore/v1/sites', {
					headers: {'X-WP-Nonce':wpApiSettings.nonce}
				})
				.then(response => {
					this.sites = response.data;

					all_themes = [];
					all_plugins = [];

					this.sites.forEach(site => {
						site.environments.forEach(environment => {
							environment.themes.forEach(theme => {
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

						environment.plugins.forEach(plugin => {
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
					});

					all_themes.sort((a, b) => a.name.toString().localeCompare(b.name));
					all_plugins.sort((a, b) => a.name.toString().localeCompare(b.name));

					all_filters = [{ header: 'Themes' }];
					all_filters = all_filters.concat(all_themes);
					all_filters.push({ header: 'Plugins' })
					all_filters = all_filters.concat(all_plugins);
					this.site_filters = all_filters;
					this.loading_sites = false;
			});
	},
	computed: {
		paginatedSites() {
			const start = this.page * this.items_per_page - this.items_per_page;
			const end = start + this.items_per_page;
			return this.sites.filter( site => site.filtered ).slice(start, end);
		},
		runningJobs() {
			return this.jobs.filter(job => job.status != 'done' && job.status != 'error' ).length;
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
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': "fetch-one-time-login",
				'value': username,
				'environment': site.environment_selected
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					window.open( response.data );
					self.jobs.filter(job => job.job_id == job_id)[0].status = "done";
				})
				.catch(error => {
					self.jobs.filter(job => job.job_id == job_id)[0].status = "error";
					self.snackbar.message = description + " failed.";
					self.snackbar.show = true;
					console.log(error.response)
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

						if ( line_parsed.status == "Completed" ) {

							previous_index = index - 1;

							if ( job.command == "syncSite" ) {
								self.fetchSiteInfo( site_id );
							}

							if ( job.command == "manage" ) {
								self.syncSite( site_id );
							}

							if ( job.command == "usersFetch" ) {
								if ( tryParseJSON( response_array[previous_index] ) ) {
									// Add to site.users
									site.users =  JSON.parse( response_array[previous_index] );
								}
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
							site = this.sites.filter(site => site.id == site_id)[0];

							// Adds new job
							job_id = Math.round((new Date()).getTime());
							description = "Installing plugin '" + newFile.name + "' to " + site.name;
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
								'environment': site.environment_selected,
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
							site = this.sites.filter(site => site.id == site_id)[0];

							// Adds new job
							job_id = Math.round((new Date()).getTime());
							description = "Installing theme '" + newFile.name + "' to " + site.name;
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
								'environment': site.environment_selected,
								'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
							};

							// Housecleaning
							this.new_theme.site_id = null;
							this.new_theme.site_name = null;
						}

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
			this.dialog_new_site.environments[1].address = this.dialog_new_site.environments[0].address;

			if ( this.dialog_new_site.provider == "kinsta" ) {
				// Copy production username to staging field
				this.dialog_new_site.environments[1].username = this.dialog_new_site.environments[0].username;
				// Copy production password to staging field (If Kinsta address)
				this.dialog_new_site.environments[1].password = this.dialog_new_site.environments[0].password;
			} else {
				// Copy production username to staging field with staging suffix
				this.dialog_new_site.environments[1].username = this.dialog_new_site.environments[0].username + "-staging";
			}

			// Copy production port to staging field
			this.dialog_new_site.environments[1].port = this.dialog_new_site.environments[0].port;
			// Copy production home directory to staging field
			this.dialog_new_site.environments[1].home_directory = this.dialog_new_site.environments[0].home_directory;
			// Copy production database info to staging fields
			this.dialog_new_site.environments[1].database_username = this.dialog_new_site.environments[0].database_username;
			this.dialog_new_site.environments[1].database_password = this.dialog_new_site.environments[0].database_password;
		},
		submitNewSite() {

			var data = {
				'action': 'captaincore_ajax',
				'command': "newSite",
				'value': this.dialog_new_site
			};

			self = this;
			site_name = this.dialog_new_site.domain;

			jQuery.post(ajaxurl, data, function(response) {

				if (tryParseJSON(response)) {
					var response = JSON.parse(response);

					// If error then response
					if ( response.response.includes("Error:") ) {

						self.dialog_new_site.errors = [ response.response ];
						console.log(response.response);
						return;
					}

					if ( response.response = "Successfully added new site" ) {
						self.dialog_new_site = {
							provider: "kinsta",
							show: false,
							domain: "",
							site: "",
							errors: [],
							shared_with: [],
							customers: [],
							environments: [
								{"environment": "Production", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","home_directory":"","database_username":"","database_password":"",updates_enabled: "1","offload_enabled": false,"offload_provider":"","offload_access_key":"","offload_secret_key":"","offload_bucket":"","offload_path":"" },
								{"environment": "Staging", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","home_directory":"","database_username":"","database_password":"",updates_enabled: "1","offload_enabled": false,"offload_provider":"","offload_access_key":"","offload_secret_key":"","offload_bucket":"","offload_path":"" }
							],
						}
						self.fetchSiteInfo( response.site_id );
						site_id = response.site_id;
						
						// Start job
						description = "Adding " + site_name;
						job_id = Math.round((new Date()).getTime());
						self.jobs.push({"job_id": job_id,"description": description, "status": "running"});

						// Run prep immediately after site added.
						var data = {
							'action': 'captaincore_install',
							'command': "update",
							'post_id': response.site_id
						};

						jQuery.post(ajaxurl, data, function(response) {
							// Updates job id with reponsed background job id
							self.jobs.filter(job => job.job_id == job_id)[0].job_id = response;

							// Check if completed in 2 seconds
							setTimeout(function() {
								self.jobRetry(site_id, response);
							}, 2000);
						});

					}
				}
			});
		},
		submitEditSite() {

			this.dialog_edit_site.loading = true;

			var data = {
				'action': 'captaincore_ajax',
				'command': "editSite",
				'value': this.dialog_edit_site.site
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {

				if (tryParseJSON(response)) {
					var response = JSON.parse(response);

					// If error then response
					if ( response.response.includes("Error:") ) {

						self.dialog_edit_site.errors = [ response.response ];
						console.log(response.response);
						return;
					}

					if ( response.response = "Successfully updated site" ) {
						self.dialog_edit_site.show = false;
						
						self.fetchSiteInfo( response.site_id );

						// Run prep immediately after site added.
						var data = {
							'action': 'captaincore_install',
							'command': "update",
							'post_id': response.site_id
						};
						jQuery.post(ajaxurl, data, function(response) {
							self.dialog_edit_site = { show: false, loading: false, site: {} };
						});

					}
				}
			});
		},
		syncSite( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];

			var data = {
				action: 'captaincore_install',
				post_id: site_id,
				command: 'sync-data',
				environment: site.environment_selected
			};

			self = this;
			description = "Syncing " + site.name + " site info";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({ "job_id": job_id, "description": description, "status": "running", "command": "syncSite" });

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;

					// Check if completed in 2 seconds
					setTimeout(function() {
						self.jobRetry(site_id, response.data);
					}, 2000);

				})
				.catch( error => console.log( error ) );

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

							// Skip updating environment_selected and tabs_management
							if ( key == "environment_selected" || key == "tabs" || key == "tabs_management" ) {
								return;
							}

						  site_update[key] = site[key];
						});
					} else {
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
						response = JSON.parse(response)

						// Loop through environments and assign users
						Object.keys(response).forEach( key => {
							site.environments.filter( e => e.environment == key )[0].users = response[key];
							if ( response[key] == null ) {
								site.environments.filter( e => e.environment == key )[0].users = [];
					}
						});
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
						response = JSON.parse(response)

						// Loop through environments and assign users
						Object.keys(response).forEach( key => {
							site.environments.filter( e => e.environment == key )[0].update_logs = response[key];
							if ( response[key] == null ) {
								site.environments.filter( e => e.environment == key )[0].update_logs = [];
					}
						});
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
			site = this.sites.filter(site => site.id == site_id )[0];
			object_type = this.bulk_edit.type;
			object_singular = this.bulk_edit.type.slice(0, -1);
			items = this.bulk_edit.items.map(item => item.name).join(" ");
			if ( object_singular == "user" ) {
				items = this.bulk_edit.items.map(item => item.user_login).join(" ");
			}

			// Start job
			site_name = this.bulk_edit.site_name;
			description = "Bulk action '" + action + " " + this.bulk_edit.type + "' on " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id, "description": description, "status": "running", "command": "manage"});

			// WP ClI command to send
			wpcli = "wp " + object_singular + " " + action + " " + items;

			// Set to loading.
			site.environments[0][ object_type ] = "Updating";
			if (site.environments[1] ) {
				site.environments[1][ object_type ] = "Updating";
			}

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
				// Updates job id with reponsed background job id
				self.jobs.filter(job => job.job_id == job_id)[0].job_id = response;

				// Check if completed in 2 seconds
				setTimeout(function() {
					self.jobRetry(site_id, response);
				}, 2000);
			});

		},
		promptBackupSnapshot( site_id ) {
			site = this.sites.filter(site => site.id == site_id )[0];
			this.dialog_backup_snapshot.show = true;
			this.dialog_backup_snapshot.site = site;
		},
		downloadBackupSnapshot( site_id ) {

			var post_id = this.dialog_backup_snapshot.site.id;
			var site_name = this.dialog_backup_snapshot.site.name;
			var environment = this.dialog_backup_snapshot.site.environment_selected;

			// Start job
			description = "Downloading snapshot for " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			var data = {
				'action': 'captaincore_install',
				'post_id': post_id,
				'command': 'snapshot',
				'environment': environment,
				'value': this.dialog_backup_snapshot.email
			};

			if ( this.dialog_backup_snapshot.filter_toggle === false ) {
				data.filters = this.dialog_backup_snapshot.filter_options
			}

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;

					// Check if completed in 2 seconds
					setTimeout(function() {
						self.jobRetry(post_id, response.data);
					}, 2000);
					
					self.snackbar.message = "Generating snapshot for "+ self.dialog_backup_snapshot.site.name + ".";
					self.snackbar.show = true;
					self.dialog_backup_snapshot.site = {};
					self.dialog_backup_snapshot.show = false;
					self.dialog_backup_snapshot.email = self.dialog_backup_snapshot.current_user_email;
				})
				.catch( error => console.log( error ) );

		},
		copySite( site_id ) {
			site = this.sites.filter(site => site.id == site_id )[0];
			site_name = site.name;
			this.dialog_copy_site.show = true;
			this.dialog_copy_site.site = site;
			this.dialog_copy_site.options = this.sites.map(site => {
				option = { name: site.name, id: site.id };
				return option;
			}).filter(option => option.name != site_name );

			this.sites.map(site => site.name).filter(site => site != site_name );
		},
		editSite( site_id ) {
			site = this.sites.filter(site => site.id == site_id )[0];
			site_name = site.name;
			this.dialog_edit_site.show = true;
			this.dialog_edit_site.site = site;
		},
		deleteSite( site_id ) {
			site = this.sites.filter(site => site.id == site_id )[0];
			site_name = site.name;
			should_proceed = confirm("Delete site " + site_name + "?");

			if ( ! should_proceed ) {
				return;
			}

			// Start job
			description = "Removing site " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			var data = {
				'action': 'captaincore_ajax',
				'command': 'deleteSite',
				'post_id': site.id
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;

					// Check if completed in 2 seconds
					setTimeout(function() {
						self.jobRetry(site_id, response.data);
					}, 2000);
					
					// Remove item
					self.sites = self.sites.filter( site => site.id != site_id )
					self.snackbar.message = "Removing site "+ site_name + ".";
					
				})
				.catch( error => console.log( error ) );

		},
		startCopySite() {

			site_name = this.dialog_copy_site.site.name;
			destination_id = this.dialog_copy_site.destination;
			site_name_destination = this.sites.filter(site => site.id == destination_id)[0].name;
			should_proceed = confirm("Copy site " + site_name + " to " + site_name_destination);

			if ( ! should_proceed ) {
				return;
			}

			var post_id = this.dialog_copy_site.site.id;

			var data = {
				'action': 'captaincore_install',
				'post_id': post_id,
				'command': 'copy',
				'value': this.dialog_copy_site.destination
			};

			self = this;

			// Start job
			description = "Coping "+ site_name + " to " + site_name_destination;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;

					// Check if completed in 2 seconds
					setTimeout(function() { self.jobRetry(post_id, response.data); }, 2000);

					self.dialog_copy_site.site = {};
					self.dialog_copy_site.show = false;
					this.dialog_copy_site.destination = "";
					this.dialog_copy_site.options = [];
					self.snackbar.message = description;
					self.snackbar.show = true;
				})
				.catch( error => console.log( error ) );

		},
		applyHttpsUrls( command ) {

			should_proceed = confirm("Will apply ssl urls. Proceed?");

			if ( ! should_proceed ) {
				return;
			}

			var post_id = this.dialog_apply_https_urls.site.id;
			site = this.sites.filter(site => site.id == post_id )[0];

			// Start job
			description = "Applying HTTPS urls for " + site.name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			var data = {
				'action': 'captaincore_install',
				'environment': site.environment_selected,
				'post_id': post_id,
				'command': command,
			};

			self = this;

			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajaxurl, data, function(response) {
				// Updates job id with reponsed background job id
				self.jobs.filter(job => job.job_id == job_id)[0].job_id = response;

				// Check if completed in 2 seconds
				setTimeout(function() {
					self.jobRetry(post_id, response);
				}, 2000);

				self.dialog_apply_https_urls.site = "";
				self.dialog_apply_https_urls.show = false;
				self.snackbar.message = "Applying HTTPS Urls";
				self.snackbar.show = true;

			});

		},
		viewMailgunLogs( site_id ) {

			site = this.sites.filter(site => site.id == site_id )[0];
			this.dialog_mailgun.loading = true;
			this.dialog_mailgun.show = true;
			this.dialog_mailgun.site = site;

			var data = {
				action: 'captaincore_ajax',
				post_id: site_id,
				command: 'mailgun'
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.dialog_mailgun.loading = false;
					self.dialog_mailgun.response = response.data;
				})
				.catch( error => console.log( error ) );

		},
		toggleSite( site_id ) {

			site = this.sites.filter(site => site.id == site_id )[0];
			this.dialog_toggle.show = true;
			this.dialog_toggle.site = site;
			this.dialog_toggle.business_name = this.business_name;
			this.dialog_toggle.business_link = this.business_link;

		},
		DeactivateSite( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			site_name = this.dialog_toggle.site.name;

			var data = {
				action: 'captaincore_install',
				post_id: site_id,
				command: 'deactivate',
				environment: site.environment_selected,
				name: this.dialog_toggle.business_name,
				link: this.dialog_toggle.business_link
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.snackbar.message = "Deactivating " + site_name;
					self.snackbar.show = true;
					self.dialog_toggle.show = false;
					self.dialog_toggle.site = {};
					self.dialog_toggle.business_name = "";
					self.dialog_toggle.business_link = "";
				})
				.catch( error => console.log( error ) );

		},
		ActivateSite( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			site_name = this.dialog_toggle.site.name;

			var data = {
				action: 'captaincore_install',
				post_id: site_id,
				environment: site.environment_selected,
				command: 'activate'
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.snackbar.message = "Activating " + site_name;
					self.snackbar.show = true;
					self.dialog_toggle.show = false;
					self.dialog_toggle.site = {};
					self.dialog_toggle.business_name = "";
					self.dialog_toggle.business_link = "";
				})
				.catch( error => console.log( error ) );

		},
		siteDeploy( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			should_proceed = confirm("Deploy users and plugins " + site.name + "?");
			description = "Deploy users and plugins on '" + site.name + "'";

			if ( ! should_proceed ) {
				return;
			}

			var data = {
				action: 'captaincore_install',
				environment: site.environment_selected,
				post_id: site_id,
				command: 'new'
			};

			self = this;

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;

					// Check if completed in 2 seconds
					setTimeout(function() {
						self.jobRetry( site_id, response.data );
					}, 2000);

					self.snackbar.message = description;
					self.snackbar.show = true;
				})
				.catch( error => console.log( error ) );

		},
		viewUsageBreakdown( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];

			var data = {
				action: 'captaincore_ajax',
				post_id: site.id,
				command: 'usage-breakdown'
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					site.usage_breakdown = response.data;
				})
				.catch( error => console.log( error ) );

		},
		modifyPlan( site_id ) {
			site = this.sites.filter(site => site.id == site_id)[0];
			this.dialog_modify_plan.site = site;
			customer = site.customer[0];
			this.dialog_modify_plan.hosting_addons = customer.hosting_addons;
			this.dialog_modify_plan.hosting_plan = Object.assign({}, customer.hosting_plan)

			// Adds commas
			if ( this.dialog_modify_plan.hosting_plan.visits_limit != null ) {
				this.dialog_modify_plan.hosting_plan.visits_limit = this.dialog_modify_plan.hosting_plan.visits_limit.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
			}

			this.dialog_modify_plan.selected_plan = customer.hosting_plan.name;
			this.dialog_modify_plan.customer_name = customer.name;
			this.dialog_modify_plan.show = true;
		},
		updatePlan() {
			site_id = this.dialog_modify_plan.site.id;
			site = this.sites.filter(site => site.id == site_id)[0];
			customer = site.customer[0];
			hosting_plan = Object.assign({}, this.dialog_modify_plan.hosting_plan)
			hosting_addons = Object.assign({}, this.dialog_modify_plan.hosting_addons)

			// Remove commas
			hosting_plan.visits_limit = hosting_plan.visits_limit.replace(/,/g, '')
			customer.hosting_plan = hosting_plan
			this.dialog_modify_plan.show = false;
			
			// New job for progress tracking
			job_id = Math.round((new Date()).getTime());
			description = "Updating Plan for " + customer.name;
			this.jobs.push({"job_id": job_id,"description": description, "status": "done"});

			// Prep AJAX request
			var data = {
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': "updatePlan",
				'value': { "hosting_plan": hosting_plan, "addons": hosting_addons },
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {
				
				// Reset dialog
				self.dialog_modify_plan = { show: false, site: {}, hosting_plan: {}, hosting_addons: [], selected_plan: "", customer_name: "" };

				// Updates job id with reponsed background job id
				self.jobs.filter(job => job.job_id == job_id)[0].status = "done";

				// Fetch new usage breakdown
				self.viewUsageBreakdown( site_id )
				self.fetchSiteInfo( site_id )

			});

		},
		addAddon() {
			this.dialog_modify_plan.hosting_addons.push({ "name": "", "quantity": "", "price": "" });
		},
		removeAddon( remove_item ) {
			this.dialog_modify_plan.hosting_addons = this.dialog_modify_plan.hosting_addons.filter( (item, index) => index != remove_item );
		},
		loadHostingPlan() {
			selected_plan = this.dialog_modify_plan.selected_plan
			hosting_plan = this.hosting_plans.filter( plan => plan.name == selected_plan )[0]
			if ( typeof hosting_plan != "undefined" ) {
				this.dialog_modify_plan.hosting_plan = hosting_plan
			}
		},
		PushProductionToStaging( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			should_proceed = confirm("Push production site " + site.name + " to staging site?");
			description = "Pushing production site '" + site.name + "' to staging";

			if ( ! should_proceed ) {
				return;
			}

			var data = {
				action: 'captaincore_install',
				post_id: site.id,
				command: 'production-to-staging',
				value: this.current_user_email
			};

			self = this;

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;

					// Check if completed in 2 seconds
					setTimeout(function() {
						self.jobRetry(site_id, response.data);
					}, 2000);
					self.snackbar.message = description;
					self.snackbar.show = true;
				})
				.catch( error => console.log( error ) );
		},
		PushStagingToProduction( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			should_proceed = confirm("Push staging site " + site.name + " to production site?");
			description = "Pushing staging site '" + site.name + "' to production";

			if ( ! should_proceed ) {
				return;
			}

			var data = {
				action: 'captaincore_install',
				post_id: site.id,
				command: 'staging-to-production',
				value: this.current_user_email
			};

			self = this;

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;

					// Check if completed in 2 seconds
					setTimeout(function() {
						self.jobRetry(site_id, response.data);
					}, 2000);
					self.snackbar.message = description;
					self.snackbar.show = true;
				})
				.catch( error => console.log( error ) );
		},
		viewApplyHttpsUrls( site_id ) {
			site = this.sites.filter(site => site.id == site_id)[0];
			this.dialog_apply_https_urls.show = true;
			this.dialog_apply_https_urls.site = site;
		},
		RollbackQuicksave( site_id, quicksave_id, addon_type, addon_name ){

			site = this.sites.filter(site => site.id == site_id)[0];
			environment = site.environments.filter( e => e.environment == site.environment_selected )[0];
			quicksave = environment.quicksaves.filter( quicksave => quicksave.quicksave_id == quicksave_id )[0];
			date = this.$options.filters.pretty_timestamp(quicksave.created_at);
			description = "Rollback "+ addon_type + " " + addon_name +" to version as of " + date + " on " + site.name ;
			should_proceed = confirm( description + "?");

			if ( ! should_proceed ) {
				return;
			}

			site = this.sites.filter(site => site.id == site_id)[0];

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'environment': site.environment_selected,
				'quicksave_id': quicksave_id,
				'command': 'rollback',
				'value'	: addon_name,
				'addon_type': addon_type,
			};

			self = this;

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;

					// Check if completed in 2 seconds
					setTimeout(function() {
						self.jobRetry(site_id, response.data);
					}, 2000);

					self.snackbar.message = "Rollback in progress.";
					self.snackbar.show = true;
				})
				.catch( error => console.log( error ) );

		},
		QuicksaveFileRestore() {

			date = this.$options.filters.pretty_timestamp(this.dialog_file_diff.quicksave.created_at);
			should_proceed = confirm("Rollback file " + this.dialog_file_diff.file_name  + " as of " + date);

			if ( ! should_proceed ) {
				return;
			}

			site_id = this.dialog_file_diff.quicksave.site_id
			site = this.sites.filter(site => site.id == site_id)[0];

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'environment': site.environment_selected,
				'quicksave_id': this.dialog_file_diff.quicksave.quicksave_id,
				'command': 'quicksave_file_restore',
				'value'	: this.dialog_file_diff.file_name,
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.snackbar.message = "File restore in process. Will email once completed.";
					self.snackbar.show = true;
					self.dialog_file_diff.show = false;
				})
				.catch( error => console.log( error ) );

		},
		QuicksaveFileDiff( site_id, quicksave_id, git_commit, file_name ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			environment = site.environments.filter( e => e.environment == site.environment_selected )[0];
			file_name = file_name.split("	")[1];
			this.dialog_file_diff.response = "";
			this.dialog_file_diff.file_name = file_name;
			this.dialog_file_diff.loading = true;
			this.dialog_file_diff.quicksave = environment.quicksaves.filter(quicksave => quicksave.quicksave_id == quicksave_id)[0];
			this.dialog_file_diff.show = true;

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'environment': site.environment_selected,
				'quicksave_id': quicksave_id,
				'command': 'quicksave_file_diff',
				'commit': git_commit,
				'value'	: file_name,
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					html = [];
					response.data.split('\n').forEach(line => {
						applied_css="";
						if ( line[0] == "-" ) {
							applied_css=" class='red lighten-4'";
						}
						if ( line[0] == "+" ) {
							applied_css=" class='green lighten-5'";
						}
						html.push("<div"+applied_css+">" + line + "</div>");
					});
					self.dialog_file_diff.response = html.join('\n');
					self.dialog_file_diff.loading = false;
				})
				.catch( error => console.log( error ) );

		},
		QuicksaveCheck( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			should_proceed = confirm("Run a manual check for new files on " + site.name + "?");

			if ( ! should_proceed ) {
				return;
			}

			// Start job
			site_name = site.name;
			description = "Checking for file changes on " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'command': 'quick_backup',
				'environment': site.environment_selected,
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;

					// Check if completed in 2 seconds
					setTimeout(function() {
						self.jobRetry(site_id, response.data);
					}, 2000);

					self.snackbar.message = "Quicksave in process.";
					self.snackbar.show = true;
					
				})
				.catch( error => console.log( error ) );

		},
		QuicksavesRollback( site_id, quicksave ) {

			date = this.$options.filters.pretty_timestamp(quicksave.created_at);
			site = this.sites.filter(site => site.id == site_id)[0];
			should_proceed = confirm("Will rollback all themes/plugins on " + site.name + " to " + date + ". Proceed?");

			if ( ! should_proceed ) {
				return;
			}

			var data = {
				'action': 'captaincore_install',
				'post_id': quicksave.site_id,
				'quicksave_id': quicksave.quicksave_id,
				'command': 'quicksave_rollback',
				'environment': site.environment_selected,
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
			  .then( response => {
					quicksave.loading = false;
					self.snackbar.message = "Rollback in process.";
					self.snackbar.show = true;
				})
			  .catch( error => console.log( error ) );

		},
		viewQuicksavesChanges( site_id, quicksave ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			quicksave.view_changes = true;

			var data = {
				action: 'captaincore_install',
				post_id: site_id,
				command: 'view_quicksave_changes',
				environment: site.environment_selected,
				value: quicksave.git_commit
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
			  .then( response => {
					// Remove empty last row
					quicksave.view_files = response.data.trim().split("\n");
					quicksave.filtered_files = response.data.trim().split("\n");
					quicksave.loading = false;
				})
			  .catch( error => console.log( error ) );
		},
		viewQuicksaves( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			axios.get(
				'/wp-json/captaincore/v1/site/'+site_id+'/quicksaves', {
					headers: {'X-WP-Nonce':wpApiSettings.nonce}
				})
				.then(response => { 
						site.environments[0].quicksaves = response.data.Production
						site.environments[1].quicksaves = response.data.Staging				
				});

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
				'environment': site.environment_selected,
				'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {
				site.loading_themes = false;
				// Updates job id with reponsed background job id
				self.jobs.filter(job => job.job_id == job_id)[0].job_id = response;

				// Check if completed in 2 seconds
				setTimeout(function() {
					self.jobRetry(site_id, response);
				}, 2000);
			});
		},
		deleteTheme (theme_name, site_id) {

			should_proceed = confirm("Are you sure you want to delete theme " + theme_name + "?");

			if ( ! should_proceed ) {
				return;
			}

			site = this.sites.filter(site => site.id == site_id)[0];

			// Enable loading progress
			site.loading_themes = true;
			description = "Removing theme '" +theme_name + "' from " + site.name;
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
				'environment': site.environment_selected,
				'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {
				environment = site.environments.filter( e => e.environment == site.environment_selected )[0]
				updated_themes = environment.themes.filter(theme => theme.name != theme_name);
				environment.themes = updated_themes;
				site.loading_themes = false;
				// Updates job id with reponsed background job id
				self.jobs.filter(job => job.job_id == job_id)[0].job_id = response;

				// Check if completed in 2 seconds
				setTimeout(function() {
					self.jobRetry(site_id, response);
				}, 2000);
			});

		},
		addPlugin ( site_id ){
			this.new_plugin.show = true;
			this.new_plugin.site_id = site_id;
			this.new_plugin.site_name = this.sites.filter(site => site.id == site_id)[0].name;
		},
		togglePlugin (plugin_name, plugin_status, site_id) {

			site = this.sites.filter(site => site.id == site_id)[0];

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
				'environment': site.environment_selected,
				'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {
				self.sites.filter(site => site.id == site_id)[0].loading_plugins = false;
				
				// Updates job id with reponsed background job id
				self.jobs.filter(job => job.job_id == job_id)[0].job_id = response;

				// Check if completed in 2 seconds
				setTimeout(function() {
					self.jobRetry(site_id, response);
				}, 2000);
			});
		},
		deletePlugin (plugin_name, site_id) {

			should_proceed = confirm("Are you sure you want to delete plugin " + plugin_name + "?");

			if ( ! should_proceed ) {
				return;
			}

			site = this.sites.filter(site => site.id == site_id)[0];

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
				'environment': site.environment_selected,
				'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {

				environment = site.environments.filter( e => e.environment == site.environment_selected )[0]
				updated_plugins = environment.plugins.filter(plugin => plugin.name != plugin_name);
				environment.plugins = updated_plugins;
				self.sites.filter(site => site.id == site_id)[0].loading_plugins = false;

				// Updates job id with reponsed background job id
				self.jobs.filter(job => job.job_id == job_id)[0].job_id = response;

				// Check if completed in 2 seconds
				setTimeout(function() {
					self.jobRetry(site_id, response);
				}, 2000);
			});
		},
		update( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			should_proceed = confirm("Apply all plugin/theme updates for " + site.name + "?");

			if ( ! should_proceed ) {
				return;
			}

			// New job for progress tracking
			job_id = Math.round((new Date()).getTime());
			description = "Updating themes/plugins on " + site.name;
			this.jobs.push({"job_id": job_id,"description": description, "status": "running","command":"update-wp"});

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'environment': site.environment_selected,
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

		},
		themeAndPluginChecks( site_id ) {
			site = this.sites.filter(site => site.id == site_id)[0];
			this.dialog_theme_and_plugin_checks.site = site;
			this.dialog_theme_and_plugin_checks.show = true;
		},
		configureFathom( site_id ) {
			this.dialog_fathom.site = this.sites.filter(site => site.id == site_id)[0];
			this.dialog_fathom.show = true;
		},
		configureFathomClose() {
			this.dialog_fathom.editItem = false;
			setTimeout(() => {
				this.dialog_fathom.editedItem = {}
				this.dialog_fathom.editedIndex = -1
			}, 300)
		},
		configureFathomSave() {
			if (this.dialog_fathom.editedIndex > -1) {
          		Object.assign(this.dialog_fathom.site.fathom[this.dialog_fathom.editedIndex], this.dialog_fathom.editedItem)
			} else {
				this.dialog_fathom.site.fathom.push(this.dialog_fathom.editedItem)
			}
			this.configureFathomClose()
		},
		newFathomItem(){
			this.dialog_fathom.site.fathom.push({ "code": "", "domain" : "" })
		},
		deleteFathomItem (item) {
			const index = this.dialog_fathom.site.fathom.indexOf(item)
			confirm('Are you sure you want to delete this item?') && this.dialog_fathom.site.fathom.splice(index, 1)
		},
		saveFathomConfigurations() {
			site = this.dialog_fathom.site;
			site_id = site.id;
			should_proceed = confirm("Apply new Fathom tracker for " + site.name + "?");

			if ( ! should_proceed ) {
				return;
			}

			// New job for progress tracking
			job_id = Math.round((new Date()).getTime());
			description = "Updating Fathom tracker on " + site.name;
			this.jobs.push({"job_id": job_id,"description": description, "status": "running"});

			// Prep AJAX request
			var data = {
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': "updateFathom",
				'value': site.fathom,
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {
				// close dialog
				self.dialog_fathom.site = {};
				self.dialog_fathom.show = false;

				// Updates job id with reponsed background job id
				self.jobs.filter(job => job.job_id == job_id)[0].job_id = response;

				// Check if completed in 2 seconds
				setTimeout(function() {
					self.jobRetry(site_id, response);
				}, 2000);
			});
		},
		updateSettings( site_id ) {
			this.dialog_update_settings.show = true;
			this.dialog_update_settings.site_id = site_id;
			site = this.sites.filter(site => site.id == site_id)[0];
			environment = site.environments.filter( e => e.environment == site.environment_selected )[0];
			this.dialog_update_settings.site_name = site.name;
			this.dialog_update_settings.exclude_plugins = environment.updates_exclude_plugins;
			this.dialog_update_settings.exclude_themes = environment.updates_exclude_themes;
			this.dialog_update_settings.updates_enabled = environment.updates_enabled;
			this.dialog_update_settings.plugins = environment.plugins;
			this.dialog_update_settings.themes = environment.themes;
		},
		saveUpdateSettings() {
			this.dialog_update_settings.loading = true;
			site_id = this.dialog_update_settings.site_id;
			site = this.sites.filter(site => site.id == site_id)[0];
			self = this;

			// Adds new job
			job_id = Math.round((new Date()).getTime());
			description = "Saving update settings for " + site.name + " (" + site.environment_selected + ")";
			this.jobs.push({"job_id": job_id,"description": description, "status": "running", "command":"saveUpdateSettings"});

			// Prep AJAX request
			var data = {
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': "updateSettings",
				'environment': site.environment_selected,
				'value': { 
					"exclude_plugins": this.dialog_update_settings.exclude_plugins, 
					"exclude_themes": this.dialog_update_settings.exclude_themes, 
					"updates_enabled": this.dialog_update_settings.updates_enabled
					}
			};

			environment = site.environments.filter( e => e.environment == site.environment_selected )[0];

			environment.exclude_plugins = self.dialog_update_settings.exclude_plugins;
			environment.exclude_themes = self.dialog_update_settings.exclude_themes;
			environment.updates_enabled = self.dialog_update_settings.updates_enabled;

			self.dialog_update_settings.show = false;
			self.dialog_update_settings.loading = false;

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

			site = this.sites.filter(site => site.id == site_id)[0];
			should_proceed = confirm("Are you sure you want to delete user " + username + "?");

			if ( ! should_proceed ) {
				return;
			}

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
				'environment': site.environment_selected,
				'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
			};

			self = this;

			jQuery.post(ajaxurl, data, function(response) {
				updated_users = self.sites.filter(site => site.id == site_id)[0].users.filter(user => user.username != username);
				self.sites.filter(site => site.id == site_id)[0].users = updated_users;

				// Updates job id with reponsed background job id
				self.jobs.filter(job => job.job_id == job_id)[0].job_id = response;

				// Check if completed in 2 seconds
				setTimeout(function() {
					self.jobRetry(site_id, response);
				}, 2000);
			});

		},
		bulkactionLaunch() {
				this.sites_selected.forEach(site => window.open(site.home_url));
		},
		bulkactionSubmit() {

			site_ids = this.sites.filter( site => site.selected ).map( site => site.id );
			site_names = this.sites.filter( site => site.selected ).map( site => site.name );

			var data = {
			  'action': 'captaincore_install',
				'post_id': site_ids,
				'command': "manage",
				'background': true,
				'value': this.select_bulk_action,
				'arguments': this.select_bulk_action_arguments
		  };

			var self = this;

			description = "Running bulk " + this.select_bulk_action + " on " + site_names.join(" ");
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "running", "command": "manage"});

			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajaxurl, data, function(response) {
				// Updates job id with reponsed background job id
				self.jobs.filter(job => job.job_id == job_id)[0].job_id = response;

				// Check if completed in 2 seconds
				setTimeout(function() {
					self.jobRetry(site_ids, response);
				}, 2000);
				self.snackbar.message = description;
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
		filterFiles( site_id, quicksave_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			environment = site.environments.filter( e => e.environment == site.environment_selected )[0];

			quicksave = environment.quicksaves.filter( quicksave => quicksave.quicksave_id == quicksave_id )[0];
			search = quicksave.search;
			quicksave.filtered_files = quicksave.view_files.filter( file => file.includes( search ) );

		},
		filterSites() {
			// Filter if select has value
			if ( this.applied_site_filter.length > 0 || this.search ) {

				search = this.search;
				filterby = this.applied_site_filter;
				filterbyversions = this.applied_site_filter_version;
				filterbystatuses = this.applied_site_filter_status;
				filter_versions = [];
				filter_statuses = [];
				versions = [];
				statuses = [];
				sites = this.sites;

				if ( this.applied_site_filter_version.length > 0 ) {

					// Find all themes/plugins which have selected version
					this.applied_site_filter_version.forEach(filter => {
						if(!versions.includes(filter.slug)) {
							versions.push(filter.slug);
						}
					});

				}

				if ( this.applied_site_filter_status.length > 0 ) {

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
									plugin_exists = site.environments[0].plugins.some(el => el.name === slug && el.version === version.name);
									theme_exists = site.environments[0].themes.some(el => el.name === slug && el.version === version.name);
								}

							});

							// Apply status specific for this theme/plugin
							filterbystatuses.filter(item => item.slug == slug).forEach(status => {

								if ( theme_exists || plugin_exists ) {
									exists = true;
								} else {
									plugin_exists = site.environments[0].plugins.some(el => el.name === slug && el.status === status.name);
									theme_exists = site.environments[0].themes.some(el => el.name === slug && el.status === status.name);
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
									plugin_exists = site.environments[0].plugins.some(el => el.name === slug && el.version === version.name);
									theme_exists = site.environments[0].themes.some(el => el.name === slug && el.version === version.name);
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
									plugin_exists = site.environments[0].plugins.some(el => el.name === slug && el.status === status.name);
									theme_exists = site.environments[0].themes.some(el => el.name === slug && el.status === status.name);
								}

							});

							if (theme_exists || plugin_exists) {
								exists = true;
							}

						// Handle filtering of the themes/plugins
						} else {

							theme_exists = site.environments[0].themes.some(function (el) {
								return el.name === filter;
							});
							plugin_exists = site.environments[0].plugins.some(function (el) {
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

					sites.forEach(function(site) {

						site.environments[0].plugins.filter(item => item.name == filter).forEach(function(plugin) {
							version_count = versions.filter(item => item.name == plugin.version).length;
							if ( version_count == 0 ) {
								versions.push({ name: plugin.version, count: 1, slug: plugin.name });
							} else {
								versions.find(function (item) { return item.name === plugin.version; }).count++;
							}
						});

						site.environments[0].themes.filter(item => item.name == filter).forEach(function(theme) {
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

						site.environments[0].plugins.filter(item => item.name == filter).forEach(function(plugin) {
							status_count = statuses.filter(item => item.name == plugin.status).length;
							if ( status_count == 0 ) {
								statuses.push({ name: plugin.status, count: 1, slug: plugin.name });
							} else {
								statuses.find(function (item) { return item.name === plugin.status; }).count++;
							}
						});

						site.environments[0].themes.filter(item => item.name == filter).forEach(function(theme) {
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
			if ( this.applied_site_filter.length == 0 && !this.search ) {

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
