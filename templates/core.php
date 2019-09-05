<!DOCTYPE html>
<html>
<head>
  <title><?php echo get_field( 'business_name', 'option' ); ?> - Account</title>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
  <meta charset="utf-8">
<?php
// Load favicons and wpApiSettings from normal WordPress header
captaincore_header_content_extracted();

$user       = wp_get_current_user();
$role_check = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles ) + in_array( 'editor', $user->roles );
if ( $role_check ) {
	$current_user  = wp_get_current_user();
	$belongs_to    = get_field( 'partner', "user_{$current_user->ID}" );
	$business_name = get_the_title( $belongs_to[0] );
	$business_link = get_field( 'partner_link', $belongs_to[0] );
}
?>
<link href="https://cdn.jsdelivr.net/npm/vuetify@2.0.10/dist/vuetify.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@mdi/font@3.x/css/materialdesignicons.min.css" rel="stylesheet">
<link href="/wp-content/plugins/captaincore/public/css/captaincore-public-2019-09-04.css" rel="stylesheet">
<?php if ( substr( $_SERVER['SERVER_NAME'], -4) == 'test' ) { ?>
<script src="/wp-content/plugins/captaincore/public/js/vue.js"></script>
<script src="/wp-content/plugins/captaincore/public/js/qs.js"></script>
<script src="/wp-content/plugins/captaincore/public/js/axios.min.js"></script>
<script src="/wp-content/plugins/captaincore/public/js/vuetify.min.js"></script>
<?php } else { ?>
<script src="https://cdn.jsdelivr.net/npm/vue@2.6.10/dist/vue.min.js"></script>
<script src="https://unpkg.com/qs@6.5.2/dist/qs.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vuetify@2.0.10/dist/vuetify.min.js"></script>
<?php } ?>
<script src="https://unpkg.com/lodash@4.16.0"></script>
<script>
lodash = _.noConflict();
</script>
<link href="https://cdn.jsdelivr.net/npm/frappe-charts@1.2.0/dist/frappe-charts.min.css" rel="stylesheet">
<script src="/wp-content/plugins/captaincore/public/js/frappe-charts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/numeral@2.0.6/numeral.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue-upload-component@2.8.20/dist/vue-upload-component.js"></script>
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
</head>
<body>
<div id="app" v-cloak>
	<v-app>
	  <v-app-bar color="blue darken-3" dark app fixed style="left:0px;height:64px;">
	 	 <v-app-bar-nav-icon @click.stop="drawer = !drawer" class="d-md-none d-lg-none d-xl-none"></v-app-bar-nav-icon>
         <v-toolbar-title>
		 <v-row>
		 <v-col>
			<v-list flat color="blue darken-3">
		 	<v-list-item href="#sites" style="padding:0px;" flat class="not-active">
			 	<v-img :src="captaincore_logo" contain max-width="32" max-height="32" v-if="captaincore_logo" class="mr-4"></v-img>
				 {{ captaincore_name }}
			</v-list-item>
			<div id="clipboard" style="position:absolute;opacity:0"></div>
			</v-list>
		 </v-col>
		</a>
		 </v-row>
		</v-toolbar-title>
      </v-app-bar>
	  <v-navigation-drawer v-model="drawer" app mobile-break-point="960" clipped>
      <v-list>
        <v-list-item link href="#sites">
          <v-list-item-icon>
            <v-icon>mdi-wrench</v-icon>
          </v-list-item-icon>
          <v-list-item-content>
            <v-list-item-title>Sites</v-list-item-title>
          </v-list-item-content>
        </v-list-item>
        <v-list-item link href="#dns">
          <v-list-item-icon>
            <v-icon>mdi-library-books</v-icon>
          </v-list-item-icon>
          <v-list-item-content>
            <v-list-item-title>DNS</v-list-item-title>
          </v-list-item-content>
        </v-list-item>
		<v-list-item link href="#cookbook" v-show="role == 'administrator'">
        <v-list-item-icon>
            <v-icon>mdi-book-open-variant</v-icon>
          </v-list-item-icon>
          <v-list-item-content>
            <v-list-item-title>Cookbook</v-list-item-title>
          </v-list-item-content>
        </v-list-item>
        <v-list-item link href="#handbook" v-show="role == 'administrator'">
          <v-list-item-icon>
            <v-icon>mdi-map</v-icon>
          </v-list-item-icon>
          <v-list-item-content>
            <v-list-item-title>Handbook</v-list-item-title>
          </v-list-item-content>
        </v-list-item>
        <v-list-item link :href="billing_link" target="_blank" v-show="billing_link">
          <v-list-item-icon>
            <v-icon>mdi-currency-usd</v-icon>
          </v-list-item-icon>
          <v-list-item-content>
            <v-list-item-title>Billing  <v-icon small>mdi-open-in-new</v-icon></v-list-item-title>
          </v-list-item-content>
        </v-list-item>
        <v-list-item link @click="signOut()">
          <v-list-item-icon>
            <v-icon>mdi-logout</v-icon>
          </v-list-item-icon>
          <v-list-item-content>
            <v-list-item-title>Log Out</v-list-item-title>
          </v-list-item-content>
        </v-list-item>
      </v-list>
	  </v-navigation-drawer>
	  <v-content>
		<v-container fluid style="padding:0px">
		<v-badge overlap left class="static" v-if="runningJobs">
			<span slot="badge">{{ runningJobs }}</span>
			<a @click.stop="view_jobs = true; $vuetify.goTo( '#sites' )"><v-icon large color="grey lighten-1">mdi-cogs</v-icon></a>
			<template>
			  <v-progress-linear :indeterminate="true" class="my-2"></v-progress-linear>
			</template>
		</v-badge>
		<v-dialog v-model="new_plugin.show" max-width="900px">
		<v-card tile>
		<v-toolbar flat dark color="primary">
			<v-btn icon dark @click.native="new_plugin.show = false">
				<v-icon>close</v-icon>
			</v-btn>
			<v-toolbar-title>Add plugin to {{ new_plugin.site_name }}</v-toolbar-title>
			<v-spacer></v-spacer>
		</v-toolbar>
		<v-toolbar color="grey lighten-4" dense light flat>
			<v-tabs
				background-color="transparent"
				v-model="new_plugin.tabs"
				mandatory
			>
				<v-tab>From your computer</v-tab>
				<v-tab>From WordPress.org</v-tab>
			</v-tabs>
			<v-spacer></v-spacer>
		</v-toolbar>
		<v-tabs-items v-model="new_plugin.tabs">
      <v-tab-item key="0">
		<div class="upload-drag pt-4">
		<div class="upload">
			<div v-if="upload.length" class="mx-3">
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
					<div class="text-center">
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
      </v-tab-item>
			<v-tab-item key="1">
				<v-layout justify-center class="pa-3">
				<v-flex xs12 sm3>
				</v-flex>
				<v-flex xs12 sm6>
					<div class="text-center">
						<v-pagination v-if="new_plugin.api.info && new_plugin.api.info.pages > 1" :length="new_plugin.api.info.pages - 1" v-model="new_plugin.page" :total-visible="7" color="blue darken-3" @input="fetchPlugins"></v-pagination>
					</div>
				</v-flex>
				<v-flex xs12 sm3>
					<v-text-field label="Search plugins" light @click:append="new_plugin.search = $event.target.offsetParent.children[0].children[1].value; fetchPlugins()" v-on:keyup.enter="new_plugin.search = $event.target.value; fetchPlugins()" append-icon="search" :loading="new_plugin.loading"></v-text-field>
					<!-- @change.native="new_plugin.search = $event.target.value; fetchPlugins" -->
				</v-flex>
			</v-layout>
			<v-layout row wrap pa-2>
				<v-flex
					v-for="item in new_plugin.api.items"
					:key="item.slug"
					xs4
					pa-2
				>
					<v-card>
					<v-layout style="min-height: 120px;">
					<v-flex xs3 px-2 pt-2>
						<v-img
							:src='item.icons["1x"]'
							contain
						></v-img>
					</v-flex>
					<v-flex xs9 px-2 pt-2>
						<span v-html="item.name"></span>
					</v-flex>
					</v-layout>
						<v-card-actions>
							<v-spacer></v-spacer>
							<div v-if="new_plugin.current_plugins.includes( item.slug )">
							<v-btn small depressed @click="uninstallPlugin( item )">Uninstall</v-btn>
							<v-btn small depressed disabled>Install</v-btn>
							</div>
							<v-btn v-else small depressed @click="installPlugin( item )">Install</v-btn>
						</v-card-actions>
		</v-card>
				</v-flex>
			</v-layout>
      </v-tab-item>
    </v-tabs-items>
		</v-card>
		</v-dialog>
		<v-dialog v-model="new_theme.show" max-width="900px">
		<v-card tile>
		<v-toolbar flat dark color="primary">
			<v-btn icon dark @click.native="new_theme.show = false">
				<v-icon>close</v-icon>
			</v-btn>
			<v-toolbar-title>Add theme to {{ new_theme.site_name }}</v-toolbar-title>
			<v-spacer></v-spacer>
		</v-toolbar>
		<v-toolbar color="grey lighten-4" dense flat>
			<v-tabs
				background-color="transparent"
				v-model="new_theme.tabs"
				mandatory
			>
				<v-tab>From your computer</v-tab>
				<v-tab>From WordPress.org</v-tab>
			</v-tabs>
			<v-spacer></v-spacer>
		</v-toolbar>
		<v-tabs-items v-model="new_theme.tabs">
      <v-tab-item key="0">
		<div class="upload-drag pt-4">
		<div class="upload">
			<div v-if="upload.length" class="mx-3">
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
					<div class="text-center">
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
		</v-tab-item>
			<v-tab-item key="1">
				<v-layout justify-center class="pa-3">
				<v-flex xs12 sm3>
				</v-flex>
				<v-flex xs12 sm6>
					<div class="text-center">
						<v-pagination v-if="new_theme.api.info && new_theme.api.info.pages > 1" :length="new_theme.api.info.pages - 1" v-model="new_theme.page" :total-visible="7" color="blue darken-3" @input="fetchThemes"></v-pagination>
					</div>
				</v-flex>
				<v-flex xs12 sm3>
					<v-text-field label="Search themes" light @click:append="new_theme.search = $event.target.offsetParent.children[0].children[1].value; fetchThemes()" v-on:keyup.enter="new_theme.search = $event.target.value; fetchThemes()" append-icon="search" :loading="new_theme.loading"></v-text-field>
				</v-flex>
			</v-layout>
			<v-layout row wrap pa-2>
				<v-flex
					v-if="new_theme.api.items"
					v-for="item in new_theme.api.items"
					:key="item.slug"
					xs4
					pa-2
				>
					<v-card>
					<v-layout style="min-height: 120px;">
					<v-flex xs3 px-2 pt-2>
						<v-img
							:src='item.screenshot_url'
							contain
						></v-img>
					</v-flex>
					<v-flex xs9 px-2 pt-2>
						<span v-html="item.name"></span>
					</v-flex>
					</v-layout>
						<v-card-actions>
							<v-spacer></v-spacer>
							<div v-if="new_theme.current_themes && new_theme.current_themes.includes( item.slug )">
							<v-btn small depressed @click="uninstallTheme( item )">Uninstall</v-btn>
							<v-btn small depressed disabled>Install</v-btn>
							</div>
							<v-btn v-else small depressed @click="installTheme( item )">Install</v-btn>
						</v-card-actions>
		</v-card>
				</v-flex>
			</v-layout>
      </v-tab-item>
    </v-tabs-items>
		</v-card>
		</v-dialog>
		<v-dialog v-model="bulk_edit.show" max-width="600px">
		<v-card tile>
			<v-toolbar flat dark color="primary">
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
			<v-toolbar flat dark color="primary">
				<v-btn icon dark @click.native="dialog_fathom.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>Configure Fathom for {{ dialog_fathom.site.name }}</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text>
				<v-progress-linear :indeterminate="true" v-if="dialog_fathom.loading"></v-progress-linear>
				<table>
				<tr v-for="tracker in dialog_fathom.environment.fathom">
					<td class="pa-1"><v-text-field v-model="tracker.domain" label="Domain"></v-text-field></td>
					<td class="pa-1"><v-text-field v-model="tracker.code" label="Code"></v-text-field></td>
					<td>
						<v-icon small @click="deleteFathomItem(tracker)">delete</v-icon>
					</td>
				</tr>
				</table>
				<v-flex xs12 class="text-right">
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
            <v-btn color="blue darken-1" text @click="configureFathomClose">Cancel</v-btn>
            <v-btn color="blue darken-1" text @click="configureFathomSave">Save</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
	  <v-dialog v-model="dialog_domain.show" max-width="1200px" persistent>
	  	<v-card tile style="margin:auto;max-width:1200px">
			<v-toolbar flat color="grey lighten-4">
				<v-btn icon @click.native="dialog_domain.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>Modify DNS for {{ dialog_domain.domain.name }}</v-toolbar-title>
				<v-spacer></v-spacer>
				<span v-show="dnsRecords > 0" class="body-2 mr-4">{{ dnsRecords }} records</span>
			</v-toolbar>
			<v-card-text style="max-height: 100%;">
			<v-container>
			<v-layout row wrap>
				<v-flex xs12 pa-2>
					<v-progress-linear :indeterminate="true" v-if="dialog_domain.loading"></v-progress-linear>
					<table class="table-dns" v-else>
						<tr>
							<th width="125">Type</th>
							<th width="200">Name</th>
							<th>Value</th>
							<th width="75">TTL</th>
							<th width="70"></th>
						</tr>
						<tr v-for="(record, index) in dialog_domain.records" :key="record.id" v-bind:class="{ delete: record.delete }">
						<template v-if="record.edit">
							<td>{{ record.type }}</td>
							<td><v-text-field label="Name" :value="record.update.record_name" @change.native="record.update.record_name = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></td>
							<td class="value" v-if="record.type == 'MX'">
								<v-layout v-for="(value, value_index) in record.update.record_value">
									<v-flex xs3><v-text-field label="Level" :value="value.level" @change.native="value.level = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></v-flex>
									<v-flex xs9><v-text-field label="Value" :value="value.value" @change.native="value.value = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'><template v-slot:append-outer><v-btn text small icon color="primary" class="ma-0 pa-0" @click="deleteRecordValue( index, value_index )" :disabled="dialog_domain.saving"><v-icon>mdi-delete</v-icon></v-btn></template></v-text-field></v-flex>
								</v-layout>
								<v-btn icon small color="primary" class="ma-0 mb-3" @click="addRecordValue( index )" v-show="!dialog_domain.loading && !dialog_domain.saving"><v-icon>mdi-plus-box</v-icon></v-btn>
							</td>
							<td class="value" v-else-if="record.type == 'A' || record.type == 'AAAA' || record.type == 'ANAME' || record.type == 'TXT' || record.type == 'SPF'">
								<div v-for="(value, value_index) in record.update.record_value" :key="`value-${index}-${value_index}`">
									<v-text-field label="Value" :value="value.value" @change.native="value.value = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'><template v-slot:append-outer><v-btn text small icon color="primary" class="ma-0 pa-0" @click="deleteRecordValue( index, value_index )" :disabled="dialog_domain.saving"><v-icon>mdi-delete</v-icon></v-btn></template></v-text-field>
								</div>
								<v-btn icon small color="primary" class="ma-0 mb-3" @click="addRecordValue( index )" v-show="!dialog_domain.loading && !dialog_domain.saving"><v-icon>mdi-plus-box</v-icon></v-btn>
							</td>
							<td class="value" v-else-if="record.type == 'SRV'">
								<v-layout v-for="value in record.update.record_value">
									<v-flex xs2><v-text-field label="Priority" :value="value.priority" @change.native="value.priority = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></v-flex>
									<v-flex xs2><v-text-field label="Weight" :value="value.weight" @change.native="value.weight = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></v-flex>
									<v-flex xs2><v-text-field label="Port" :value="value.port" @change.native="value.port = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></v-flex>
									<v-flex xs6><v-text-field label="Value" :value="value.value" @change.native="value.value = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></v-flex>
								</v-layout>
								<v-btn icon small color="primary" class="ma-0 mb-3" @click="addRecordValue( index )" v-show="!dialog_domain.loading && !dialog_domain.saving"><v-icon>mdi-plus-box</v-icon></v-btn>
							</td>
							<td class="value" v-else>
								<v-text-field label="Value" :value="record.update.record_value" @change.native="record.update.record_value = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field>
							</td>
							<td><v-text-field label="TTL" :value="record.update.record_ttl" @change.native="record.update.record_ttl = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></td>
							<td class="text-right">
								<v-btn text small icon color="primary" class="ma-0 pa-0" @click="viewRecord( record.id )" :disabled="dialog_domain.saving"><v-icon>mdi-pencil-box</v-icon></v-btn>
								<v-btn text small icon color="primary" class="ma-0 pa-0" @click="deleteRecord( record.id )" :disabled="dialog_domain.saving"><v-icon>mdi-delete</v-icon></v-btn>
							</td>
						</template>
						<template v-else-if="record.new">
							<td><v-select v-model="record.type" @input="changeRecordType( index )" item-text="name" item-value="value" :items='[{"name":"A","value":"A"},{"name":"AAAA","value":"AAAA"},{"name":"ANAME","value":"ANAME"},{"name":"CNAME","value":"CNAME"},{"name":"HTTP Redirect","value":"HTTPRedirection"},{"name":"MX","value":"MX"},{"name":"SRV","value":"SRV"},{"name":"TXT","value":"TXT"}]' label="Type" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-select></td>
							<td><v-text-field label="Name" :value="record.update.record_name" @change.native="record.update.record_name = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></td>
							<td class="value" v-if="record.type == 'MX'">
								<v-layout v-for="(value, value_index) in record.update.record_value">
									<v-flex xs3><v-text-field label="Level" :value="value.level" @change.native="value.level = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></v-flex>
									<v-flex xs9><v-text-field label="Value" :value="value.value" @change.native="value.value = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'><template v-slot:append-outer><v-btn text small icon color="primary" class="ma-0 pa-0" @click="deleteRecordValue( index, value_index )" :disabled="dialog_domain.saving"><v-icon>mdi-delete</v-icon></v-btn></template></v-text-field></v-flex>
								</v-layout>
								<v-btn icon small color="primary" class="ma-0 mb-3" @click="addRecordValue( index )" v-show="!dialog_domain.loading && !dialog_domain.saving"><v-icon>mdi-plus-box</v-icon></v-btn>
							</td>
							<td class="value" v-else-if="record.type == 'A' || record.type == 'AAAA' || record.type == 'ANAME' || record.type == 'TXT' || record.type == 'SPF'">
								<div v-for="(value, value_index) in record.update.record_value" :key="`value-${index}-${value_index}`">
									<v-text-field label="Value" :value="value.value" @change.native="value.value = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'><template v-slot:append-outer><v-btn text small icon color="primary" class="ma-0 pa-0" @click="deleteRecordValue( index, value_index )" :disabled="dialog_domain.saving"><v-icon>mdi-delete</v-icon></v-btn></template></v-text-field>
								</div>
								<v-btn icon small color="primary" class="ma-0 mb-3" @click="addRecordValue( index )" v-show="!dialog_domain.loading && !dialog_domain.saving"><v-icon>mdi-plus-box</v-icon></v-btn>
							</td>
							<td class="value" v-else-if="record.type == 'SRV'">
								<v-layout v-for="value in record.update.record_value">
									<v-flex xs2><v-text-field label="Priority" :value="value.priority" @change.native="value.priority = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></v-flex>
									<v-flex xs2><v-text-field label="Weight" :value="value.weight" @change.native="value.weight = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></v-flex>
									<v-flex xs2><v-text-field label="Port" :value="value.port" @change.native="value.port = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></v-flex>
									<v-flex xs6><v-text-field label="Value" :value="value.value" @change.native="value.value = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></v-flex>
								</v-layout>
							</td>
							<td class="value" v-else>
								<v-text-field label="Value" :value="record.update.record_value" @change.native="record.update.record_value = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field>
							</td>
							<td><v-text-field label="TTL" :value="record.update.record_ttl" @change.native="record.update.record_ttl = $event.target.value" v-bind:class='{ "v-input--is-disabled": dialog_domain.saving }'></v-text-field></td>
							<td class="text-right" style="padding-top: 20px;">
								<v-btn text small icon color="primary" class="ma-0 pa-0" @click="deleteRecord( index )" :disabled="dialog_domain.saving"><v-icon>mdi-delete</v-icon></v-btn>
							</td>
						</template>
						<template v-else>
							<td>{{ record.type }}</td>
							<td class="name">{{ record.name }}</td>
							<td class="value" v-if="record.type == 'MX'"><div v-for="value in record.value">{{ value.level }} {{ value.value }}</div></td>
							<td class="value" v-else-if="record.type == 'A' || record.type == 'AAAA' || record.type == 'ANAME' || record.type == 'TXT' || record.type == 'SPF'"><div v-for="value in record.value">{{ value.value }}</div></td>
							<td class="value" v-else-if="record.type == 'SRV'"><div v-for="value in record.value">{{ value.priority }} {{ value.weight }} {{ value.port }} {{ value.value }}</div></td>
							<td class="value" v-else>{{ record.value }}</td>
							<td>{{ record.ttl }}</td>
							<td class="text-right">
								<v-btn text small icon color="primary" class="ma-0 pa-0" @click="editRecord( record.id )" :disabled="dialog_domain.saving"><v-icon>mdi-pencil-box</v-icon></v-btn>
								<v-btn text small icon color="primary" class="ma-0 pa-0" @click="deleteCurrentRecord( record.id )" :disabled="dialog_domain.saving"><v-icon>mdi-delete</v-icon></v-btn>
							</td>
						</template>
						</tr>
					</table>
					<v-btn depressed class="ma-0" @click="addRecord()" v-show="!dialog_domain.loading && !dialog_domain.saving">Add Additional Record</v-btn>
				</v-flex>
				<v-flex xs12>
					<v-progress-linear :indeterminate="true" v-show="dialog_domain.saving"></v-progress-linear>
				</v-flex>
				<v-flex xs12 text-right my-3 v-show="!dialog_domain.loading">
					<v-btn color="primary" dark @click="saveDNS()">
						Save Records
					</v-btn>
				</v-flex>
				<v-flex xs12>
					<template v-for="result in dialog_domain.results">
						<v-alert :value="true" type="success" v-show="typeof result.success != 'undefined'">{{ result.success }}</v-alert>
						<v-alert :value="true" type="error" v-show="typeof result.errors != 'undefined'">{{ result.errors }}</v-alert>
					</template>
				</v-flex>
			</v-layout>
			</v-container>
			</v-card-text>
		</v-card>
	  </v-dialog>
	  <v-dialog v-model="new_recipe.show" max-width="800px" v-if="role == 'administrator'">
	  	<v-card tile style="margin:auto;max-width:800px">
			<v-toolbar flat color="grey lighten-4">
				<v-btn icon @click.native="new_recipe.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>New Recipe</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text style="max-height: 100%;">
			<v-container>
			<v-layout row wrap>
				<v-flex xs12 pa-2>
					<v-text-field label="Name" :value="new_recipe.title" @change.native="new_recipe.title = $event.target.value"></v-text-field>
				</v-flex>
				<v-flex xs12 pa-2>
					<v-textarea label="Content" persistent-hint hint="Bash script and WP-CLI commands welcomed." auto-grow :value="new_recipe.content" @change.native="new_recipe.content = $event.target.value"></v-textfield>
				</v-flex>
				<v-flex xs12 pa-2>
					<v-switch label="Public" v-model="new_recipe.public" persistent-hint hint="Public by default. Turning off will make the recipe only viewable and useable by you." :false-value="0" :true-value="1"></v-switch>
				</v-flex>
				<v-flex xs12 text-right pa-0 ma-0>
					<v-btn color="primary" dark @click="addRecipe()">
						Add New Recipe
					</v-btn>
				</v-flex>
			</v-layout>
			</v-container>
			</v-card-text>
		</v-card>
	  </v-dialog>
	  <v-dialog v-model="dialog_cookbook.show" max-width="800px" v-if="role == 'administrator'" persistent>
		<v-card tile style="margin:auto;max-width:800px">
			<v-toolbar flat color="grey lighten-4">
				<v-btn icon @click.native="dialog_cookbook.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>Edit Recipe</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text style="max-height: 100%;">
			<v-container>
			<v-layout row wrap>
				<v-flex xs12 pa-2>
					<v-text-field label="Name" :value="dialog_cookbook.recipe.title" @change.native="dialog_cookbook.recipe.title = $event.target.value"></v-text-field>
				</v-flex>
				<v-flex xs12 pa-2>
					<v-textarea label="Content" persistent-hint hint="Bash script and WP-CLI commands welcomed." auto-grow :value="dialog_cookbook.recipe.content" @change.native="dialog_cookbook.recipe.content = $event.target.value"></v-textfield>
				</v-flex>
				<v-flex xs12 pa-2>
					<v-switch label="Public" v-model="dialog_cookbook.recipe.public" persistent-hint hint="Public by default. Turning off will make the recipe only viewable and useable by you." false-value="0" true-value="1"></v-switch>
				</v-flex>
				<v-flex xs12 text-right pa-0 ma-0>
					<v-btn color="primary" dark @click="updateRecipe()">
						Update Recipe
					</v-btn>
				</v-flex>
			</v-layout>
			</v-container>
			</v-card-text>
		</v-card>
	  </v-dialog>
	  <v-dialog v-model="new_process.show" max-width="800px" v-if="role == 'administrator'">
		<v-card tile style="margin:auto;max-width:800px">
			<v-toolbar flat color="grey lighten-4">
				<v-btn icon @click.native="new_process.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>New Process</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text style="max-height: 100%;">
			<v-container>
			<v-layout row wrap>
				<v-flex xs12 pa-2>
					<v-text-field label="Name" :value="new_process.title" @change.native="new_process.title = $event.target.value"></v-text-field>
				</v-flex>
				<v-flex xs12 sm3 pa-2>
					<v-text-field label="Time Estimate" hint="Example: 15 minutes" persistent-hint :value="new_process.time_estimate" @change.native="new_process.time_estimate = $event.target.value"></v-text-field>
				</v-flex>
				<v-flex xs12 sm3 pa-2>
					<v-select :items='[{"text":"As needed","value":"as-needed"},{"text":"Daily","value":"daily"},{"text":"Weekly","value":"weekly"},{"text":"Monthly","value":"monthly"},{"text":"Yearly","value":"yearly"}]' label="Repeat" :value="new_process.repeat" @change.native="new_process.repeat = $event.target.value"></v-select>
				</v-flex>

				<v-flex xs12 sm3 pa-2>
					<v-text-field label="Repeat Quantity"  hint="Example: 2 or 3 times" persistent-hint :value="new_process.repeat_quantity" @change.native="new_process.repeat_quantity = $event.target.value"></v-text-field>
				</v-flex>

				<v-flex xs12 sm3 pa-2>
					<v-select :items="new_process_roles" label="Role" hide-details v-model="new_process.role"></v-select>
				</v-flex>

				<v-flex xs12 pa-2>
					<v-textarea label="Description" persistent-hint hint="Steps to accomplish this process. Markdown enabled." auto-grow :value="new_process.description" @change.native="new_process.description = $event.target.value"></v-textfield>
				</v-flex>

				<v-flex xs12 text-right pa-0 ma-0>
					<v-btn color="primary" dark @click="addNewProcess()">
						Add New Process
					</v-btn>
				</v-flex>
				</v-flex>
				</v-layout>
			</v-container>
			</v-card-text>
			</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_edit_process.show" persistent max-width="800px" v-if="role == 'administrator'">
		<v-card tile style="margin:auto;max-width:800px">
			<v-toolbar flat color="grey lighten-4">
				<v-btn icon @click.native="dialog_edit_process.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>Edit Process</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text style="max-height: 100%;">
			<v-container>
			<v-layout row wrap>
				<v-flex xs12 pa-2>
					<v-text-field label="Name" :value="dialog_edit_process.process.title" @change.native="dialog_edit_process.process.title = $event.target.value"></v-text-field>
				</v-flex>
				<v-flex xs12 sm3 pa-2>
					<v-text-field label="Time Estimate" hint="Example: 15 minutes" persistent-hint :value="dialog_edit_process.process.time_estimate" @change.native="dialog_edit_process.process.time_estimate = $event.target.value"></v-text-field>
				</v-flex>
				<v-flex xs12 sm3 pa-2>
					<v-select :items='[{"text":"As needed","value":"as-needed"},{"text":"Daily","value":"1-daily"},{"text":"Weekly","value":"2-weekly"},{"text":"Monthly","value":"3-monthly"},{"text":"Yearly","value":"4-yearly"}]' label="Repeat" v-model="dialog_edit_process.process.repeat_value"></v-select>
				</v-flex>

				<v-flex xs12 sm3 pa-2>
					<v-text-field label="Repeat Quantity" hint="Example: 2 or 3 times" persistent-hint :value="dialog_edit_process.process.repeat_quantity" @change.native="dialog_edit_process.process.repeat_quantity = $event.target.value"></v-text-field>
				</v-flex>

				<v-flex xs12 sm3 pa-2>
					<v-select :items="new_process_roles" label="Role" hide-details v-model="dialog_edit_process.process.role_id"></v-select>
				</v-flex>

				<v-flex xs12 pa-2>
					<v-textarea label="Description" persistent-hint hint="Steps to accomplish this process. Markdown enabled." auto-grow :value="dialog_edit_process.process.description_raw" @change.native="dialog_edit_process.process.description_raw = $event.target.value"></v-textfield>
				</v-flex>

				<v-flex xs12 text-right pa-0 ma-0>
					<v-btn color="primary" dark @click="updateProcess()">
						Update Process
					</v-btn>
				</v-flex>
				</v-flex>
				</v-layout>
			</v-container>
			</v-card-text>
			</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_handbook.show" v-if="role == 'administrator'">
			<v-card tile>
			<v-toolbar flat color="grey lighten-4">
				<v-btn icon @click.native="dialog_handbook.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>{{ dialog_handbook.process.title }} <v-chip color="primary" text-color="white" text>{{ dialog_handbook.process.role }}</v-chip></v-toolbar-title>
				<v-spacer></v-spacer>
				<v-toolbar-items>
					<v-btn text @click="editProcess( dialog_handbook.process.id ); dialog_handbook.show = false">Edit</v-btn>
				</v-toolbar-items>
			</v-toolbar>
			<v-card-text style="max-height: 100%;">
				<div class="caption mb-3">
					<v-icon small v-show="dialog_handbook.process.time_estimate != ''" style="padding:0px 5px">mdi-clock-outline</v-icon>{{ dialog_handbook.process.time_estimate }} 
					<v-icon small v-show="dialog_handbook.process.repeat != '' && dialog_handbook.process.repeat != null" style="padding:0px 5px">mdi-calendar-repeat</v-icon>{{ dialog_handbook.process.repeat }} 
					<v-icon small v-show="dialog_handbook.process.repeat_quantity != '' && dialog_handbook.process.repeat_quantity != null" style="padding:0px 5px">mdi-repeat</v-icon>{{ dialog_handbook.process.repeat_quantity }}
				</div>
				<span v-html="dialog_handbook.process.description"></span>
			</v-card-text>
			</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_update_settings.show" max-width="500px">
		<v-card tile>
			<v-toolbar flat dark color="primary">
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
		<v-dialog v-model="dialog_theme_and_plugin_checks.show" width="500">
        <v-card tile>
          <v-toolbar flat dark color="primary">
				<v-btn icon dark @click.native="dialog_theme_and_plugin_checks.show = false">
              <v-icon>close</v-icon>
            </v-btn>
				<v-toolbar-title>Theme & plugin checks for {{ dialog_theme_and_plugin_checks.site.name }}</v-toolbar-title>
            <v-spacer></v-spacer>
          </v-toolbar>
          <v-card-text>

				<p>Enables daily checks to verify a theme/plugin is a certain status (activate/inactive). Will email notify if a check fails.</p>

				<v-switch label="Theme & Plugin Checks" v-model="dialog_theme_and_plugin_checks.theme_and_plugin_checks" false-value="0" true-value="1"></v-switch>
				<v-data-table
					:items='[{ slug: "wordpress-seo", status: "active" },{ slug: "enhanced-e-commerce-for-woocommerce-store", status: "active"}]'
					hide-default-footer
					hide-default-header
					class="elevation-1"
					v-show="dialog_theme_and_plugin_checks.theme_and_plugin_checks == 1"
				>
				<template v-slot:body="{ items }">
				<tbody>
					<tr v-for="item in items">
						<td>
							<v-text-field v-model="item.slug" label="Slug" required></v-text-field>
						</td>
						<td class="text-right">
							<v-select
								:items='["active","inactive","active-network"]'
								box
								label="Status"
								:value="item.status"
							>
							</v-select>
						</td>
						<td class="justify-center layout px-0">
							<v-icon small @click="deleteItem(item)">delete</v-icon>
						</td>
					</tr>
					<tr>
						<td colspan="100%" class="text-right">
							<v-btn @click="deleteItem(props.item)">
								Add new check
							</v-btn>
						</td>
					</tr>
				</tbody>
				</template>
			  </v-data-table>
				<v-progress-linear :indeterminate="true" v-if="dialog_theme_and_plugin_checks.loading"></v-progress-linear>
				<v-btn @click="savethemeAndPluginChecks()">Save Checks</v-btn>
          </v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_new_domain.show" scrollable width="500">
		<v-card>
			<v-toolbar flat dark color="primary">
			<v-btn icon dark @click.native="dialog_new_domain.show = false">
				<v-icon>close</v-icon>
			</v-btn>
			<v-toolbar-title>Add Domain</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text>
				<v-text-field :value="dialog_new_domain.domain.name" @change.native="dialog_new_domain.domain.name = $event.target.value" label="Domain Name" required></v-text-field>
				<v-autocomplete :items="customers" item-text="name" item-value="customer_id" v-model="dialog_new_domain.domain.customer" label="Customer" required></v-autocomplete>
				<v-flex xs12 text-right>
					<v-btn color="primary" dark @click="addDomain()">
						Save Changes
					</v-btn>
				</v-flex>
			</v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_configure_defaults.show" scrollable width="980">
		<v-card>
			<v-toolbar flat dark color="primary">
			<v-btn icon dark @click.native="dialog_configure_defaults.show = false">
				<v-icon>close</v-icon>
			</v-btn>
			<v-toolbar-title>Configure Defaults</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
			<template v-if="dialog_configure_defaults.loading">
				<v-progress-linear :indeterminate="true"></v-progress-linear>
			</template>
			<v-card-text>
				<template v-if="! dialog_configure_defaults.loading">
				<v-select class="mt-5" :items="dialog_configure_defaults.records.map( a => a.account )" label="Account" item-value="id" v-model="dialog_configure_defaults.account" @input="switchConfigureDefaultAccount()">
					<template v-slot:selection="data">
						<span v-html="data.item.name"></span>
					</template>
					<template v-slot:item="data">
						<span v-html="data.item.name"></span>
					</template>
				</v-select>
				<v-alert
					:value="true"
					type="info"
					v-if="dialog_configure_defaults.record.account"
					class="mb-4"
				>
					When new sites are added to the account <strong>{{ dialog_configure_defaults.record.account.name }}</strong> then the following default settings will be applied.  
				</v-alert>
				<v-layout wrap>			
					<v-flex xs6 pr-2><v-text-field :value="dialog_configure_defaults.record.default_email" @change.native="dialog_configure_defaults.record.default_email = $event.target.value" label="Default Email" required></v-text-field></v-flex>
					<v-flex xs6 pl-2><v-autocomplete :items="timezones" label="Default Timezone" v-model="dialog_configure_defaults.record.default_timezone"></v-autocomplete></v-flex>
				</v-layout>
				<v-layout wrap>
					<v-flex><v-autocomplete label="Default Recipes" v-model="dialog_configure_defaults.record.default_recipes" ref="default_recipes" :items="recipes" item-text="title" item-value="recipe_id" multiple chips deletable-chips></v-autocomplete></v-flex>
				</v-layout>

				<span class="body-2">Default Users</span>
				<v-data-table
					:items="dialog_configure_defaults.record.default_users"
					hide-default-header
					hide-default-footer
				>
				<template v-slot:body="{ items }">
				<tbody>
					<tr v-for="(item, index) in items" style="border-bottom: 0px;">
						<td class="pa-1"><v-text-field :value="item.username" @change.native="item.username = $event.target.value" label="Username"></v-text-field></td>
						<td class="pa-1"><v-text-field :value="item.email" @change.native="item.email = $event.target.value" label="Email"></v-text-field></td>
						<td class="pa-1"><v-text-field :value="item.first_name" @change.native="item.first_name = $event.target.value" label="First Name"></v-text-field></td>
						<td class="pa-1"><v-text-field :value="item.last_name" @change.native="item.last_name = $event.target.value" label="Last Name"></v-text-field></td>
						<td class="pa-1" style="width:145px;"><v-select :value="item.role" v-model="item.role" :items="roles" label="Role" item-text="name"></v-select></td>
						<td class="pa-1"><v-btn text small icon color="primary" @click="deleteUserValue( index )"><v-icon small>mdi-delete</v-icon></v-btn></td>
					</tr>
				</tbody>
				</template>
					<template v-slot:footer>
					<tr style="border-top: 0px;">
						<td colspan="5" style="padding:0px;">
							<v-btn depressed small class="ma-0 mb-3" @click="addDefaultsUser()">Add Additional User</v-btn>
						</td>
					</tr>
					</template>
				</v-data-table>

				<v-flex xs12 text-right>
					<v-btn color="primary" dark @click="saveDefaults()">
						Save Changes
					</v-btn>
				</v-flex>
				</template>
			</v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_new_site.show" scrollable>
					<v-card tile>
						<v-toolbar flat dark color="primary">
							<v-btn icon dark @click.native="dialog_new_site.show = false">
								<v-icon>close</v-icon>
							</v-btn>
							<v-toolbar-title>Add Site</v-toolbar-title>
								<v-spacer></v-spacer>
							</v-toolbar>
						<v-card-text>
							<v-container>
							<v-form ref="form">
							<v-layout>
							<v-flex xs4 class="mx-2">
								<v-autocomplete
								:items='[{"name":"WP Engine","value":"wpengine"},{"name":"Kinsta","value":"kinsta"}]'
								item-text="name"
								v-model="dialog_new_site.provider"
								label="Provider"
							></v-autocomplete>
							</v-flex>
							<v-flex xs4 class="mx-2">
								<v-text-field :value="dialog_new_site.domain" @change.native="dialog_new_site.domain = $event.target.value" label="Domain name" required></v-text-field>
							</v-flex>
							<v-flex xs4 class="mx-2">
						    <v-text-field :value="dialog_new_site.site" @change.native="dialog_new_site.site = $event.target.value" label="Site name" required hint="Should match provider site name." persistent-hint></v-text-field>
							</v-flex>
							</v-layout>
							<v-layout>
							<v-flex xs4 class="mx-2">
							<v-autocomplete
								:items="developers"
								v-model="dialog_new_site.shared_with"
								label="Shared With"
								item-text="name"
								return-object
								chips
								multiple
								small-chips
								deletable-chips
							>
							</v-autocomplete>
						</v-flex>
						<v-flex xs4 class="mx-2">
							<v-autocomplete
								:items="customers"
								item-text="name"
								item-value="customer_id"
								v-model="dialog_new_site.customers"
								item-text="name"
								hint="Assign to existing customer. If new leave blank."
								persistent-hint
								chips
								small-chips
								deletable-chips
							>
						</v-autocomplete>
							</v-flex>
						<v-flex xs4 class="mx-2">
						</v-flex>
						</v-layout>
							<v-container grid-list-md text-center>
								<v-layout row wrap>
									<v-flex xs12 style="height:0px">
									<v-btn @click="new_site_preload_staging" text icon center relative color="green" style="top:32px;">
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
										<v-text-field label="Address" :value="key.address" @change.native="key.address = $event.target.value" required  hint="Should match included domain. Example: sitename.kinsta.cloud" persistent-hint></v-text-field>
										<v-text-field label="Home Directory" :value="key.home_directory" @change.native="key.home_directory = $event.target.value" required></v-text-field>
										<v-layout>
										<v-flex xs6 class="mr-1"><v-text-field label="Username" :value="key.username" @change.native="key.username = $event.target.value" required></v-text-field></v-flex>
										<v-flex xs6 class="ml-1"><v-text-field label="Password" :value="key.password" @change.native="key.password = $event.target.value" required></v-text-field></v-flex>
										</v-layout>
										<v-layout>
										<v-flex xs6 class="mr-1"><v-text-field label="Protocol" :value="key.protocol" @change.native="key.protocol = $event.target.value" required></v-text-field></v-flex>
										<v-flex xs6 class="mr-1"><v-text-field label="Port" :value="key.port" @change.native="key.port = $event.target.value" required></v-text-field></v-flex>
										</v-layout>
										<v-layout>
										<v-flex xs6 class="mr-1"><v-text-field label="Database Username" :value="key.database_username" @change.native="key.database_username = $event.target.value" required></v-text-field></v-flex>
										<v-flex xs6 class="mr-1"><v-text-field label="Database Password" :value="key.database_password" @change.native="key.database_password = $event.target.value" required></v-text-field></v-flex>
										</v-layout>
										<v-layout>
											<v-flex xs6 class="mr-1"><v-switch label="Automatic Updates" v-model="key.updates_enabled" false-value="0" true-value="1"></v-switch></v-flex>
											<v-flex xs6 class="mr-1" v-if="typeof key.offload_enabled != 'undefined'">
											<v-switch label="Use Offload" v-model="key.offload_enabled" false-value="0" true-value="1" left></v-switch>
											</v-flex>
										</v-layout>
											<div v-if="key.offload_enabled == 1">
										<v-layout>
											<v-flex xs6 class="mr-1"><v-select label="Offload Provider" :value="key.offload_provider" @change.native="key.offload_provider = $event.target.value" :items='[{ provider:"s3", label: "Amazon S3" },{ provider:"do", label:"Digital Ocean" }]' item-text="label" item-value="provider" clearable></v-select></v-flex>
											<v-flex xs6 class="mr-1"><v-text-field label="Offload Access Key" :value="key.offload_access_key" @change.native="key.offload_access_key = $event.target.value" required></v-text-field></v-flex>
										</v-layout>
										<v-layout>
											<v-flex xs6 class="mr-1"><v-text-field label="Offload Secret Key" :value="key.offload_secret_key" @change.native="key.offload_secret_key = $event.target.value" required></v-text-field></v-flex>
											<v-flex xs6 class="mr-1"><v-text-field label="Offload Bucket" :value="key.offload_bucket" @change.native="key.offload_bucket = $event.target.value" required></v-text-field></v-flex>
										</v-layout>
										<v-layout>
											<v-flex xs6 class="mr-1"><v-text-field label="Offload Path" :value="key.offload_path" @change.native="key.offload_path = $event.target.value" required></v-text-field></v-flex>
										</v-layout>
										</div>
									</div>
								</v-container>
							 </v-card>
							</v-flex>
							<v-flex xs12>
							<v-alert
							:value="true"
							type="error"
							v-for="error in dialog_new_site.errors"
							>
							{{ error }}
							</v-alert>
							</v-flex>
							<v-flex xs12 text-right><v-btn right @click="submitNewSite">Add Site</v-btn></v-flex>
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
					<v-toolbar flat dark color="primary">
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
							<v-btn small text icon @click="removeAddon(index)"><v-icon>delete</v-icon></v-btn>
						</v-flex>
						</v-layout>
						<v-btn small style="margin:0px;" @click="addAddon()">
							Add Addon
						</v-btn>
						<v-layout>
						<v-flex xs12 text-right>
							<v-btn color="primary" dark style="margin:0px;" @click="updatePlan()">
								Save Changes
							</v-btn>
						</v-flex>
						</v-layout>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog
					v-if="role == 'administrator'"
					v-model="dialog_log_history.show"
					scrollable
				>
				<v-card tile>
					<v-toolbar flat dark color="primary">
						<v-btn icon dark @click.native="dialog_log_history.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Log History</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-data-table
						:headers="header_timeline"
						:items="dialog_log_history.logs"
						:items-per-page-options='[50,100,250,{"text":"All","value":-1}]'
						class="timeline"
					>
				<template v-slot:body="{ items }">
				<tbody>
				<tr v-for="item in items">
					<td class="justify-center">{{ item.created_at | pretty_timestamp }}</td>
					<td class="justify-center">{{ item.author }}</td>
					<td class="justify-center">{{ item.title }}</td>
					<td class="justify-center" v-html="item.description"></td>
					<td v-if="role == 'administrator'">
						<v-icon
							small
							class="mr-2"
							@click="dialog_log_history.show = false; editLogEntry(item.websites, item.id)"
						>
							edit
						</v-icon>
						{{ item.websites.map( site => site.name ).join(" ") }}
					</td>
				</tr>
				</tbody>
				</template>
			</v-data-table>
					</v-card-text>
				</v-dialog>
				<v-dialog
					v-if="role == 'administrator'"
					v-model="dialog_new_log_entry.show"
					transition="dialog-bottom-transition"
					scrollable
					persistent
					width="500"
				>
				<v-card tile>
					<v-toolbar flat dark color="primary">
						<v-btn icon dark @click.native="dialog_new_log_entry.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Add a new log entry <span v-if="dialog_new_log_entry.site_name">for {{ dialog_new_log_entry.site_name }}</span></v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-autocomplete
							v-model="dialog_new_log_entry.process"
							:items="processes"
							item-text="title"
							item-value="id"
						>
						<template v-slot:item="data">
							<template v-if="typeof data.item !== 'object'">
								<div v-text="data.item"></div>
							</template>
							<template v-else>
								<div>
									<v-list-item-title v-html="data.item.title"></v-list-item-title>
									<v-list-item-subtitle v-html="data.item.repeat + ' - ' + data.item.role"></v-list-item-subtitle>
								</div>
							</template>
						</template>
						</v-autocomplete>
						<v-autocomplete
							v-model="dialog_new_log_entry.sites"
							:items="sites"
							item-text="name"
							return-object
							chips
							deletable-chips 
							multiple
						>
						</v-autocomplete>
						<v-textarea label="Description" auto-grow :value="dialog_new_log_entry.description" @change.native="dialog_new_log_entry.description = $event.target.value"></v-textarea>
						<v-flex xs12 text-right>
							<v-btn color="primary" dark style="margin:0px;" @click="newLogEntry()">
								Add Log Entry
							</v-btn>
						</v-flex>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog
					v-if="role == 'administrator'"
					v-model="dialog_edit_log_entry.show"
					transition="dialog-bottom-transition"
					scrollable
					width="500"
				>
				<v-card tile>
					<v-toolbar flat dark color="primary">
						<v-btn icon dark @click.native="dialog_edit_log_entry.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Edit log entry <span v-if="dialog_edit_log_entry.site_name">for {{ dialog_edit_log_entry.site_name }}</span></v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-text-field
							v-model="dialog_edit_log_entry.log.created_at"
							label="Date"
						></v-text-field>
						<v-autocomplete
							v-model="dialog_edit_log_entry.log.process_id"
							:items="processes"
							item-text="title"
							item-value="id"
						>
						<template v-slot:item="data">
							<template v-if="typeof data.item !== 'object'">
								<div v-text="data.item"></div>
							</template>
							<template v-else>
								<div>
									<v-list-item-title v-html="data.item.title"></v-list-item-title>
									<v-list-item-subtitle v-html="data.item.repeat + ' - ' + data.item.role"></v-list-item-subtitle>
								</div>
							</template>
						</template>
						</v-autocomplete>
						<v-autocomplete
							v-model="dialog_edit_log_entry.log.websites"
							:items="sites"
							item-text="name"
							return-object
							chips
							deletable-chips 
							multiple
						>
						</v-autocomplete>
						<v-textarea label="Description" auto-grow :value="dialog_edit_log_entry.log.description_raw" @change.native="dialog_edit_log_entry.log.description_raw = $event.target.value"></v-textarea>
						<v-flex xs12 text-right>
							<v-btn color="primary" dark style="margin:0px;" @click="updateLogEntry()">
								Update Log Entry
							</v-btn>
						</v-flex>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog
					v-model="dialog_mailgun.show"
					transition="dialog-bottom-transition"
					scrollable
				>
				<v-card tile>
					<v-toolbar flat dark color="primary">
						<v-btn icon dark @click.native="dialog_mailgun.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Mailgun Logs for {{ dialog_mailgun.site.name }} (Last 30 days)</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-progress-linear :indeterminate="true" v-show="dialog_mailgun.loading"></v-progress-linear>
						<v-data-table
							:headers='[{"text":"Timestamp","value":"timestamp"},{"text":"Description","value":"description"},{"text":"Event","value":"event"}]'
							:items="dialog_mailgun.response"
							:items-per-page="50"
							:footer-props="{ itemsPerPageOptions: [50,150,300,{'text':'All','value':-1}] }"
						>
						<template v-slot:body="{ items }">
						<tbody>
						<tr v-for="item in items" :key="item.event.id">
							<td class="justify-center">{{ item.timestamp }}</td>
							<td class="justify-center">{{ item.description }}</td>
							<td class="justify-center">{{ item.event.event }}</td>
						</tr>
						</tbody>
						</template>
						</v-data-table>
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
					<v-toolbar flat dark color="primary">
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
					v-model="dialog_delete_user.show"
					scrollable
					width="500px"
				>
				<v-card tile>
					<v-toolbar flat dark color="primary">
						<v-btn icon dark @click.native="dialog_delete_user.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Delete user</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-layout row wrap>
						 <v-flex xs12 pa-2>
								<span>To delete <strong>{{ dialog_delete_user.username }}</strong> from <strong>{{ dialog_delete_user.site.name }}</strong> ({{ dialog_delete_user.site.environment_selected }}), please reassign posts to another user.</span>
								<v-autocomplete
									:items="dialog_delete_user.users"
									return-object
									v-model="dialog_delete_user.reassign"
									item-text="user_login"
									label="Reassign posts to"
									chips
									hide-details
									hide-selected
									small-chips
									deletable-chips
								>
								</v-autocomplete><br />
								<v-btn @click="deleteUser()">
									Delete User <strong>&nbsp;{{ dialog_delete_user.username }}</strong>
								</v-btn>
						 </v-flex>
					 </v-layout>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog
					v-model="dialog_launch.show"
					width="500px"
					scrollable
				>
				<v-card tile>
					<v-toolbar flat dark color="primary">
						<v-btn icon dark @click.native="dialog_launch.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Launch Site {{ dialog_launch.site.name }}</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-layout row wrap>
						 <v-flex xs12 pa-2>
								<span>Will turn off search privacy and update development urls to the following live urls.</span><br /><br />
								<v-text-field label="Domain" prefix="https://" :value="dialog_launch.domain" @change.native="dialog_launch.domain = $event.target.value"></v-text-field>
								<v-btn @click="launchSite()">
									Launch Site
								</v-btn>
						 </v-flex>
					 </v-layout>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog
					v-model="dialog_toggle.show"
					transition="dialog-bottom-transition"
					scrollable
				>
				<v-card tile>
					<v-toolbar flat dark color="primary">
						<v-btn icon dark @click.native="dialog_toggle.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Toggle Site {{ dialog_toggle.site_name }}</v-toolbar-title>
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
										<v-btn @click="DeactivateSite(dialog_toggle.site_id)">
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
										<v-btn @click="ActivateSite(dialog_toggle.site_id)">
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
					v-model="dialog_migration.show"
					transition="dialog-bottom-transition"
					scrollable
					width="500"
				>
				<v-card tile>
					<v-toolbar flat dark color="primary">
						<v-btn icon dark @click.native="dialog_migration.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Migrate from backup to {{ dialog_migration.site_name }}</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
						<v-alert :value="true" type="info" color="yellow darken-4">
							Warning {{ dialog_migration.site_name }} will be overwritten with backup. 
						</v-alert>
						<p></p>
						<v-form ref="formSiteMigration">
						<v-text-field :rules="[v => !!v || 'Backup URL is required']" required label="Backup URL" placeholder="https://storage.googleapis.com/..../live-backup.zip" :value="dialog_migration.backup_url" @change.native="dialog_migration.backup_url = $event.target.value"></v-text-field>
						<v-checkbox label="Update URLs" v-model="dialog_migration.update_urls" hint="Will change urls in database to match the existing site." persistent-hint></v-checkbox>
						<p></p>
						<v-btn @click="validateSiteMigration">
							Start Migration
						</v-btn>
						</v-form>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog
					v-model="dialog_copy_site.show"
					scrollable
					width="500"
				>
				<v-card tile>
					<v-toolbar flat dark color="primary">
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
					transition="dialog-bottom-transition"
					scrollable
				>
				<v-card tile>
					<v-toolbar flat dark color="primary">
						<v-btn icon dark @click.native="dialog_edit_site.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Edit Site {{ dialog_edit_site.site.name }}</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-form ref="form">
						<v-layout>
						<v-flex xs4 class="mx-2">
						<v-autocomplete
							:items='[{"name":"WP Engine","value":"wpengine"},{"name":"Kinsta","value":"kinsta"}]'
							item-text="name"
							v-model="dialog_edit_site.site.provider"
							label="Provider"
						></v-autocomplete>
						</v-flex>
						<v-flex xs4 class="mx-2">
							<v-text-field :value="dialog_edit_site.site.name" @change.native="dialog_edit_site.site.name = $event.target.value" label="Domain name" required></v-text-field>
						</v-flex>
						<v-flex xs4 class="mx-2">
							<v-text-field :value="dialog_edit_site.site.site" @change.native="dialog_edit_site.site.site = $event.target.value" label="Site name (not changeable)" disabled></v-text-field>
						</v-flex>
					</v-layout>
			<v-layout>
				<v-flex xs4 class="mx-2">
					<v-autocomplete
						:items="developers"
						v-model="dialog_edit_site.site.shared_with"
						item-text="name"
						return-object
						label="Shared With"
						chips
						multiple
						deletable-chips
					>
					</v-autocomplete>
				</v-flex>
				<v-flex xs4 class="mx-2">
					<v-autocomplete
						:items="customers"
						item-text="name"
						v-model="dialog_edit_site.site.customer"
						return-object
						label="Customer"
						chips
						deletable-chips
					>
					</v-autocomplete>
				</v-flex>
				<v-flex xs4 class="mx-2">
				</v-flex>
				</v-layout>
							<v-container grid-list-md text-center>
								<v-layout row wrap>
									<v-flex xs12 style="height:0px">
									<v-btn @click="edit_site_preload_staging" text icon center relative color="green" style="top:32px;">
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
										<v-text-field label="Home Directory" :value="key.home_directory" @change.native="key.home_directory = $event.target.value" required></v-text-field>
											<v-layout>
											<v-flex xs6 class="mr-1"><v-text-field label="Username" :value="key.username" @change.native="key.username = $event.target.value" required></v-text-field></v-flex>
											<v-flex xs6 class="ml-1"><v-text-field label="Password" :value="key.password" @change.native="key.password = $event.target.value" required></v-text-field></v-flex>
											</v-layout>
											<v-layout>
											<v-flex xs6 class="mr-1"><v-text-field label="Protocol" :value="key.protocol" @change.native="key.protocol = $event.target.value" required></v-text-field></v-flex>
											<v-flex xs6 class="mr-1"><v-text-field label="Port" :value="key.port" @change.native="key.port = $event.target.value" required></v-text-field></v-flex>
											</v-layout>
											<v-layout>
											<v-flex xs6 class="mr-1"><v-text-field label="Database Username" :value="key.database_username" @change.native="key.database_username = $event.target.value" required></v-text-field></v-flex>
											<v-flex xs6 class="mr-1"><v-text-field label="Database Password" :value="key.database_password" @change.native="key.database_password = $event.target.value" required></v-text-field></v-flex>
											</v-layout>
											<v-layout>
												<v-flex xs6 class="mr-1" v-if="typeof key.offload_enabled != 'undefined'">
											<v-switch label="Use Offload" v-model="key.offload_enabled" false-value="0" true-value="1" left></v-switch>
												</v-flex>
											</v-layout>
											<div v-if="key.offload_enabled == 1">
											<v-layout>
												<v-flex xs6 class="mr-1"><v-select label="Offload Provider" :value="key.offload_provider" @change.native="key.offload_provider = $event.target.value" :items='[{ provider:"s3", label: "Amazon S3" },{ provider:"do", label:"Digital Ocean" }]' item-text="label" item-value="provider" clearable></v-select></v-flex>
												<v-flex xs6 class="mr-1"><v-text-field label="Offload Access Key" :value="key.offload_access_key" @change.native="key.offload_access_key = $event.target.value" required></v-text-field></v-flex>
											</v-layout>
											<v-layout>
												<v-flex xs6 class="mr-1"><v-text-field label="Offload Secret Key" :value="key.offload_secret_key" @change.native="key.offload_secret_key = $event.target.value" required></v-text-field></v-flex>
												<v-flex xs6 class="mr-1"><v-text-field label="Offload Bucket" :value="key.offload_bucket" @change.native="key.offload_bucket = $event.target.value" required></v-text-field></v-flex>
											</v-layout>
											<v-layout>
												<v-flex xs6 class="mr-1"><v-text-field label="Offload Path" :value="key.offload_path" @change.native="key.offload_path = $event.target.value" required></v-text-field></v-flex>
											</v-layout>
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
						
						<v-flex xs12 text-right>
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
					transition="dialog-bottom-transition"
					scrollable
					width="500"
				>
				<v-card tile>
					<v-toolbar flat dark color="primary">
						<v-btn icon dark @click.native="dialog_apply_https_urls.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>Apply HTTPS Urls for {{ dialog_apply_https_urls.site_name }}</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-alert :value="true" type="info" color="blue darken-3">
							Domain needs to match current home url. Otherwise server domain mapping will need updated to prevent redirection loop.
						</v-alert>
						<p></p>
						<span>Select url replacement option.</span><br />
						<v-btn @click="applyHttpsUrls( 'apply-https' )">
							Option 1: https://domain.tld
						</v-btn><br />
						<v-btn @click="applyHttpsUrls( 'apply-https-with-www' )">
							Option 2: https://www.domain.tld
						</v-btn>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog
					v-model="dialog_file_diff.show"
					transition="dialog-bottom-transition"
					scrollable
				>
				<v-card>
					<v-toolbar flat dark color="primary">
						<v-btn icon dark @click.native="dialog_file_diff.show = false">
							<v-icon>close</v-icon>
						</v-btn>
						<v-toolbar-title>File diff {{ dialog_file_diff.file_name}}</v-toolbar-title>
						<v-spacer></v-spacer>
						<v-toolbar-items class="hidden-sm-and-down">
							<v-btn text @click="QuicksaveFileRestore()">Restore this file</v-btn>
						</v-toolbar-items>
					</v-toolbar>
					<v-card-text>
						<v-container v-show="dialog_file_diff.loading"><v-progress-linear :indeterminate="true"></v-progress-linear></v-container>
						<v-container id="code_diff" v-html="dialog_file_diff.response" style='font-family:SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;'></v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
			<v-container fluid v-show="loading_page != true" style="padding:0px;">
			<v-card tile v-show="route == 'sites'" flat>
				<v-toolbar color="grey lighten-4" light flat>
					<v-toolbar-title>Sites <small>({{ showingSitesBegin }}-{{ showingSitesEnd }} of {{ filteredSites }})</small></v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-tooltip top>
							<template v-slot:activator="{ on }">
								<v-btn text small @click="configureDefaults" v-on="on"><v-icon dark>mdi-clipboard-check-outline</v-icon></v-btn>
							</template><span>Configure Defaults</span>
						</v-tooltip>
						<v-divider vertical class="mx-1" inset></v-divider>
						<v-tooltip top>
							<template v-slot:activator="{ on }">
								<v-btn text small @click="fetchTimelineLogs" v-bind:class='{ "v-btn--active": view_timeline }' v-on="on"><v-icon dark>mdi-clipboard-text</v-icon></v-btn>
							</template><span>Timeline Logs</span>
						</v-tooltip>
						<v-divider vertical class="mx-1" inset></v-divider>
						<v-tooltip top>
							<template v-slot:activator="{ on }">
								<v-btn text small @click="view_jobs = !view_jobs" v-bind:class='{ "v-btn--active": view_jobs }' v-on="on"><small v-if="runningJobs">({{ runningJobs }})</small><v-icon dark>mdi-cogs</v-icon></v-btn>
							</template><span>Job Activity</span>
						</v-tooltip>
						<v-divider vertical class="mx-1" inset></v-divider>
						<v-tooltip top>
							<template v-slot:activator="{ on }">
								<v-btn text small @click="dialog_bulk.show = !dialog_bulk.show" v-bind:class='{ "v-btn--active": dialog_bulk.show }' v-on="on"><small v-show="selectedSites > 0">({{ selectedSites }})</small><v-icon dark>mdi-settings</v-icon></v-btn>
							</template><span>Bulk Tools</span>
						</v-tooltip>
						<v-divider vertical class="mx-1" inset></v-divider>
						<v-tooltip top>
							<template v-slot:activator="{ on }">
								<v-btn text small @click="advanced_filter = !advanced_filter" v-bind:class='{ "v-btn--active": advanced_filter }' v-on="on"><v-icon dark>mdi-filter</v-icon></v-btn>
							</template><span>Filters</span>
						</v-tooltip>
						<template v-if="role == 'administrator'">
						<v-divider vertical class="mx-1" inset></v-divider>
						<v-tooltip top>
							<template v-slot:activator="{ on }">
								<v-btn text @click="dialog_new_site.show = true" v-on="on">Add Site <v-icon dark>add</v-icon></v-btn>
							</template><span>Add Site</span>
						</v-tooltip>
						</template>
					</v-toolbar-items>
				</v-toolbar>
				<v-card-text class="my-2">
				<div class="row mx-2" id="sites">
					<v-flex xs12 md2>
						<v-select
						:items='[50,100,250]'
						v-model="items_per_page"
						label="Per page"
						dense
						@change="page = 1"
						style="width:70px;display: inline-block;"
					></v-select>
					<v-select 
						:items="select_site_options"
						v-model="site_selected"
						v-show="dialog_bulk.show == true"
						@input="selectSites"
						label="Bulk Toggle"
						dense
						style="width:120px;display: inline-block;"
					></v-select>
					</v-flex>
						<v-flex xs12 md8>
						<div class="text-center">
							<v-pagination v-if="Math.ceil(filteredSites / items_per_page) > 1" :length="Math.ceil(filteredSites / items_per_page)" v-model="page" :total-visible="7" color="blue darken-3"></v-pagination>
						</div>
					</v-flex>
					<v-flex xs12 md2>
						<v-text-field @input="updateSearch" ref="search" label="Search" clearable light append-icon="search"></v-text-field>
					</v-flex>
			</div>
			<v-card v-show="view_timeline == true" class="mb-3">
				<v-toolbar flat dense dark color="primary">
				<v-btn icon dark @click.native="view_timeline = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>Timeline Logs</v-toolbar-title>
				<v-spacer></v-spacer>
				</v-toolbar>
				<v-progress-linear :indeterminate="true" absolute v-show="dialog_timeline.loading"></v-progress-linear>
				<v-card-text>
				<template v-if="!dialog_timeline.loading">
				<v-select :items="timeline_logs.map( a => a.account )" label="Account" item-value="id" v-model="dialog_timeline.account" @input="switchTimelineAccount()">
					<template v-slot:selection="data">
						<span v-html="data.item.name"></span> <small>&nbsp;({{ data.item.website_count }} sites)</small>
					</template>
					<template v-slot:item="data">
						<span v-html="data.item.name"></span> <small>&nbsp;({{ data.item.website_count }} sites)</small>
					</template>
				</v-select>
				<v-data-table
					:headers="header_timeline"
					:items="dialog_timeline.logs"
					:items-per-page-options='[50,100,250,{"text":"All","value":-1}]'
					class="timeline"
				>
				<template v-slot:body="{ items }">
				<tbody>
				<tr v-for="item in items" :key="item.name">
					<td class="justify-center">{{ item.created_at | pretty_timestamp }}</td>
					<td class="justify-center">{{ item.author }}</td>
					<td class="justify-center">{{ item.title }}</td>
					<td class="justify-center py-3" v-html="item.description"></td>
					<td width="170px;">
						{{ item.websites.map( site => site.name ).join(" ") }}
					</td>
				</tr>
				</tbody>
				</template>
				</v-data-table>
				</template>
				</v-card-text>
			</v-card>
			<v-card v-show="view_jobs == true" class="mb-3">
				<v-toolbar flat dense dark color="primary">
				<v-btn icon dark @click.native="view_jobs = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>Job Activity</v-toolbar-title>
				<v-spacer></v-spacer>
				</v-toolbar>
				<v-data-table
					:headers="[{ text: 'Description', value: 'description', width: '300px' },
								{ text: 'Status', value: 'status', width: '115px' },
								{ text: 'Response', value: 'response' }]"
					:items="jobs.slice().reverse()"
					class="elevation-1"
				>
					<template v-slot:body="{ items }">
					<tbody>
 						<tr v-for="item in items" :key="item.name">
						<td>{{ item.description }}</td>
						<td>
							<v-chip v-if="item.status == 'done'" small outlined label color="green">Done</v-chip>
							<v-chip v-else-if="item.status == 'error'" small outlined label color="red">Error</v-chip>
							<div v-else>
								<v-progress-linear :indeterminate="true"></v-progress-linear>
								<v-btn x-small class="ma-1" depressed @click="killCommand(item.job_id)">
									Cancel
								</v-btn>
							</div>
						</td>
						<td>
							<v-card text width="100%" height="80px" id="streamOutput" class="transparent elevation-0" style="overflow: auto;display: flex;flex-direction: column-reverse;">
								<small mv-1><div v-for="s in item.stream">{{ s }}</div></small>
							</v-card>
						</td>
						</tr>
					</tbody>
					</template>
				</v-data-table>
			</v-card>
			<v-card v-show="dialog_bulk.show == true" class="mb-3">
			<v-toolbar flat dark color="primary" dense>
				<v-btn icon dark @click.native="dialog_bulk.show = false">
					<v-icon>close</v-icon>
				</v-btn>
				<v-toolbar-title>Bulk Tools</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
			<div class="grey lighten-4 pb-2">
			<v-layout wrap>
			<v-flex sx12 sm4 px-2>
			<v-layout>
			<v-flex style="width:180px;">
				<v-select
					v-model="dialog_bulk.environment_selected"
					:items='[{"name":"Production Environment","value":"Production"},{"name":"Staging Environment","value":"Staging"}]'
					item-text="name"
					item-value="value"
					@change="triggerEnvironmentUpdate( site.id )"
					light
					style="height:54px;">
				</v-select>
				</v-flex>
				<v-flex>
				<v-tooltip bottom>
					<template v-slot:activator="{ on }">
					<v-btn small icon @click="bulkSyncSites()" style="margin: 12px auto 0 0;" v-on="on">
						<v-icon color="grey">mdi-sync</v-icon>
				</v-btn>
					</template>
					<span>Manual sync website details</span>
				</v-tooltip>
				</v-flex>
			</v-layout>
			</v-flex>
			<v-flex xs12 sm8>
			<v-tabs v-model="dialog_bulk.tabs_management" background-color="grey lighten-4" icons-and-text right show-arrows height="54">
				<v-tab href="#tab-Sites">
					Sites <v-icon>mdi-format-list-bulleted</v-icon>
				</v-tab>
				<v-tab key="Stats" href="#tab-Stats" v-show="role == 'coming-soon'">
					Stats <v-icon>mdi-chart-bar</v-icon>
				</v-tab>
				<v-tab key="Addons" href="#tab-Addons">
					Addons <v-icon>mdi-power-plug</v-icon>
				</v-tab>
				<v-tab key="Users" href="#tab-Users" v-show="role == 'coming-soon'">
					Users <v-icon>mdi-account-multiple</v-icon>
				</v-tab>
				<v-tab key="Updates" href="#tab-Updates" v-show="role == 'coming-soon'">
					Updates <v-icon>mdi-book-open</v-icon>
				</v-tab>
				<v-tab key="Scripts" href="#tab-Scripts">
					Scripts <v-icon>mdi-code-tags</v-icon>
				</v-tab>
				<v-tab key="Backups" href="#tab-Backups" v-show="role == 'coming-soon'">
					Backups <v-icon>mdi-update</v-icon>
				</v-tab>
			</v-tabs>
			</v-flex>
			</v-layout>
			</div>
			<v-tabs-items v-model="dialog_bulk.tabs_management">
			<v-tab-item key="1" value="tab-Sites">
				<v-card flat>
					<v-toolbar color="grey lighten-4" dense light flat>
					<v-toolbar-title>Sites</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn text @click="showLogEntryBulk()" v-if="role == 'administrator'">New Log Entry <v-icon dark small>mdi-checkbox-marked</v-icon></v-btn>
						<v-btn text @click="bulkactionLaunch">Launch sites in browser</v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card-text>
				<v-flex sm12 mb-3>
				<v-chip
					outlined
					close
					class="ma-1"
					v-for="site in sites_selected"
					@click:close="removeFromBulk(site.id)"
				><a :href="site.home_url" target="_blank">{{ site.name }}</a></v-chip>
				</v-flex>
				<v-layout row>
				<v-flex sm12 mx-5>
				<small>
					<strong>Site names: </strong> 
						<span v-for="site in sites_selected" class="ma-1" style="display: inline-block;" v-if="dialog_bulk.environment_selected == 'Production' || dialog_bulk.environment_selected == 'Both'">{{ site.site }} </span>
						<span v-for="site in sites_selected" class="ma-1" style="display: inline-block;" v-if="dialog_bulk.environment_selected == 'Staging' || dialog_bulk.environment_selected == 'Both'">{{ site.site }}-staging </span>
				</small>
				</v-flex>
				</v-card-text>
				</v-card>
			</v-tab-item>
			<v-tab-item key="3" value="tab-Addons">
				<v-card flat>
					<v-toolbar color="grey lighten-4" dense light flat>
					<v-toolbar-title>Addons</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn text @click="addThemeBulk()">Add theme <v-icon dark small>add</v-icon></v-btn>
						<v-btn text @click="addPluginBulk()">Add plugin <v-icon dark small>add</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				</v-card>
			</v-tab-item>
			<v-tab-item key="4" value="tab-Users">
				<v-card flat>
					<v-toolbar color="grey lighten-4" dense light flat>
					<v-toolbar-title>Users</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn text @click="bulkactionLaunch">Add user <v-icon dark small>add</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				</v-card>
			</v-tab-item>
			<v-tab-item key="5" value="tab-Updates">
				<v-card flat>
					<v-toolbar color="grey lighten-4" dense light flat>
					<v-toolbar-title>Updates</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn text @click="bulkactionLaunch">Manual Update <v-icon dark small>add</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				</v-card>
			</v-tab-item>
			<v-tab-item key="6" value="tab-Scripts">
				<v-card flat>
				<v-card-title>
					<v-layout align-start>
					<v-flex xs12 sm8 pr-4>
					<v-subheader id="script_bulk">Custom bash script or WP-CLI commands</v-subheader>
						<v-textarea
							auto-grow
							solo
							label=""
							hide-details
							:value="custom_script" 
							@change.native="custom_script = $event.target.value"
						></v-textarea>
						<v-btn small color="primary" dark @click="runCustomCodeBulk()">Run Custom Code</v-btn>
					</v-flex>
					<v-flex xs12 sm4>
						<v-list dense>
						<v-subheader>Common</v-subheader>
						<v-list-item @click="viewApplyHttpsUrlsBulk()" dense>
						<v-list-item-icon>
							<v-icon>launch</v-icon>
						</v-list-item-icon>
						<v-list-item-content>
							<v-list-item-title>Apply HTTPS Urls</v-list-item-title>
						</v-list-item-content>
						</v-list-item>
						<v-list-item @click="siteDeployBulk()" dense>
						<v-list-item-icon>
							<v-icon>loop</v-icon>
						</v-list-item-icon>
						<v-list-item-content>
							<v-list-item-title>Deploy users/plugins</v-list-item-title>
						</v-list-item-content>
						</v-list-item>
						<v-list-item @click="toggleSiteBulk()" dense>
						<v-list-item-icon>
							<v-icon>mdi-toggle-switch</v-icon>
						</v-list-item-icon>
						<v-list-item-content>
							<v-list-item-title>Toggle Site</v-list-item-title>
						</v-list-item-content>
						</v-list-item>
						<v-subheader v-show="recipes.filter( r => r.public == 1 ).length > 0">Other</v-subheader>
						<v-list-item @click="runRecipeBulk( recipe.recipe_id )" dense v-for="recipe in recipes.filter( r => r.public == 1 )">
						<v-list-item-icon>
							<v-icon>mdi-script-text-outline</v-icon>
						</v-list-item-icon>
						<v-list-item-content>
							<v-list-item-title v-text="recipe.title"></v-list-item-title>
						</v-list-item-content>
						</v-list-item>
						<v-subheader v-show="recipes.filter( r => r.public != 1 ).length > 0">User</v-subheader>
						<v-list-item @click="rloadRecipe( recipe.recipe_id ); $vuetify.goTo( '#script_bulk' );" dense v-for="recipe in recipes.filter( r => r.public != 1 )">
						<v-list-item-icon>
							<v-icon>mdi-script-text-outline</v-icon>
						</v-list-item-icon>
						<v-list-item-content>
							<v-list-item-title v-text="recipe.title"></v-list-item-title>
						</v-list-item-content>
						</v-list-item>
						</v-list>
					</v-flex>
					</v-layout>
					</v-card-title>
				</v-card>
			</v-tab-item>
			<v-tab-item key="7" value="tab-Backups">
				<v-card flat>
					<v-toolbar color="grey lighten-4" dense light flat>
					<v-toolbar-title>Backups</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn text @click="bulkactionLaunch">Manual Check <v-icon dark small>add</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				</v-card>
			</v-tab-item>
			</v-tabs-items>
			</v-card>
			<v-card v-show="advanced_filter == true" class="mb-3">
				<v-toolbar flat dense dark color="primary">
					<v-btn icon dark @click.native="advanced_filter = false">
						<v-icon>close</v-icon>
					</v-btn>
					<v-toolbar-title>Filters</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-card-text>
			<v-layout row>
			<v-flex xs12 ma-1>
			<v-autocomplete
				v-model="applied_site_filter"
				@input="filterSites"
				:items="site_filters"
				ref="applied_site_filter"
				item-text="search"
				item-value="name"
				item-text="title"
				label="Select Theme and/or Plugin"
				class="siteFilter"
				chips
				multiple
				hide-details
				hide-selected
				deletable-chips
			>
				<template v-slot:item="data">
					 <strong>{{ data.item.title }}</strong>&nbsp;<span>({{ data.item.name }})</span>
				</template>
			</v-autocomplete>
			</v-flex>
		</v-layout>
		<v-layout row>
			<v-flex xs6 pa-1>
				 <v-autocomplete
					v-model="applied_site_filter_version"
					v-for="filter in site_filter_version"
					@input="filterSites"
					ref="applied_site_filter_version"
					:items="filter.versions"
					:key="filter.name"
					:label="'Select Version for '+ filter.name"
					item-text="title"
					return-object
					multiple
					chips
					deletable-chips
				 >
				 <template v-slot:item="data">
						<strong>{{ data.item.name }}</strong>&nbsp;<span>({{ data.item.count }})</span>
				 </template>
				</v-autocomplete>
			</v-flex>
			<v-flex xs6 pa-1>
				<v-autocomplete
					v-model="applied_site_filter_status"
					v-for="filter in site_filter_status"
					:items="filter.statuses"
					:key="filter.name"
					:label="'Select Status for '+ filter.name"
					@input="filterSites"
					item-text="title"
					return-object
					chips
					multiple
					deletable-chips
				>
				<template slot="item" slot-scope="data">
					 <strong>{{ data.item.name }}</strong>&nbsp;<span>({{ data.item.count }})</span>
				</template>
				</v-autocomplete>
			</v-flex>
			</v-layout>
			</v-card-text>
            </v-card>
				<div class="text-right" v-show="sites.length > 1">
				<v-btn-toggle v-model="toggle_site_sort" style="box-shadow: none; border-bottom: 1px solid #e0e0e0;" v-bind:class="sort_direction" class="d-none d-md-block">
					<div class="usage ml-1 multisite"><v-btn text small @click.native.stop="toggle_site_sort = 0; sortSites('multisite')">Multisite<v-icon small light>keyboard_arrow_down</v-icon></v-btn></div>
					<div class="usage ml-1 visits"><v-btn text small @click.native.stop="toggle_site_sort = 1; sortSites('visits')">Visits<v-icon small light>keyboard_arrow_down</v-icon></v-btn></div>
					<div class="usage ml-1 storage"><v-btn text small @click.native.stop="toggle_site_sort = 2; sortSites('storage')">Storage <v-icon small light>keyboard_arrow_down</v-icon></v-btn></div>
					<div class="usage ml-1 provider"><v-btn text small @click.native.stop="toggle_site_sort = 3; sortSites('provider')">Provider <v-icon small light>keyboard_arrow_down</v-icon></v-btn></div>
					<div class="usage" style="width: 28px;"></div>
				</v-btn-toggle>
				</div>
				<v-expansion-panels accordion style="margin-top: 20px">
				<v-expansion-panel v-bind:class='{ "toggleSelect": dialog_bulk.show }' popout accordion v-for="site in paginatedSites" :key="site.id" class="site">
					<v-expansion-panel-header>
					<v-layout align-center justify-space-between row>
						<div>
							<v-layout align-center justify-start fill-height font-weight-light subtitle-1>
							<v-switch v-model="site.selected" @click.native.stop @change="site_selected = null" style="position: absolute; left: 10px; top: 17px;" v-show="dialog_bulk.show == true"></v-switch>
								<img :src="site.environments[0].screenshot_small" style="width: 50px; margin-right:1em" class="elevation-1" v-show="site.environments[0].screenshot_small">
							{{ site.name }}
							</v-layout>
						</div>
						<div class="text-right d-none d-md-block">
							<div class="usage multisite"><span v-show="site.subsite_count"><v-icon light size="20">mdi-lan</i></v-icon> {{ site.subsite_count }} sites</span></div>
							<div class="usage visits"><span v-show="site.visits"><v-icon light size="20">mdi-eye</v-icon> {{ site.visits }} <small>yearly</small></span></div>
							<div class="usage storage"><span v-show="site.storage"><v-icon light size="20">mdi-harddisk</v-icon> {{ site.storage }}</span></div>
							<div class="usage provider"><span v-show="site.provider"><v-icon light size="20">mdi-server</v-icon> {{ site.provider | formatProvider }}</span></div>
						</div>
					</v-layout>
					</v-expansion-panel-header>
					<v-expansion-panel-content>
						<v-tabs v-model="site.tabs" background-color="blue darken-3" dark>
							<v-tab :key="1" href="#tab-Site-Management">
								Site Management <v-icon size="24">mdi-settings</v-icon>
							</v-tab>
							<v-tab :key="6" href="#tab-SitePlan" ripple @click="viewUsageBreakdown( site.id )">
								Site Plan <v-icon size="24">mdi-chart-donut</v-icon>
							</v-tab>
							<v-tab :key="7" href="#tab-Sharing" ripple>
								Sharing <v-icon size="24">mdi-account-multiple-plus</v-icon>
							</v-tab>
							<v-tab :key="8" href="#tab-Timeline" ripple @click="fetchTimeline( site.id )">
								Timeline <v-icon size="24">mdi-timeline-text-outline</v-icon>
							</v-tab>
							<v-tab :key="9" href="#tab-Advanced" ripple>
								Advanced <v-icon size="24">mdi-cogs</v-icon>
							</v-tab>
						</v-tabs>
						<v-tabs-items v-model="site.tabs">
							<v-tab-item value="tab-Site-Management">
								<div class="grey lighten-4 pb-2">
								<v-layout wrap>
									<v-flex sx12 sm4 px-2>
									<v-layout>
									<v-flex style="width:180px;">
										<v-select
											v-model="site.environment_selected"
											:items='[{"name":"Production Environment","value":"Production"},{"name":"Staging Environment","value":"Staging"}]'
											item-text="name"
											item-value="value"
											@change="triggerEnvironmentUpdate( site.id )"
											light
											style="height:54px;">
										</v-select>
										</v-flex>
										<v-flex>
										<v-tooltip bottom>
											<template v-slot:activator="{ on }">
											<v-btn small icon @click="syncSite( site.id )" style="margin: 12px auto 0 0;" v-on="on">
												<v-icon color="grey">mdi-sync</v-icon>
											</v-btn>
											</template>
											<span>Manual sync website details</span>
										</v-tooltip>
										</v-flex>
									</v-layout>
									</v-flex>
									<v-flex xs12 sm8>
									<v-tabs v-model="site.tabs_management" background-color="grey lighten-4" icons-and-text right show-arrows height="54">
										<v-tab key="Info" href="#tab-Info">
											Info <v-icon>mdi-library-books</v-icon>
										</v-tab>
										<v-tab key="Stats" href="#tab-Stats" @click="fetchStats( site.id )">
											Stats <v-icon>mdi-chart-bar</v-icon>
										</v-tab>
										<v-tab key="Plugins" href="#tab-Addons">
											Addons <v-icon>mdi-power-plug</v-icon>
										</v-tab>
										<v-tab key="Users" href="#tab-Users" @click="fetchUsers( site.id )">
											Users <v-icon>mdi-account-multiple</v-icon>
										</v-tab>
										<v-tab key="Updates" href="#tab-Updates" @click="fetchUpdateLogs( site.id )">
											Updates <v-icon>mdi-book-open</v-icon>
										</v-tab>
										<v-tab key="Scripts" href="#tab-Scripts">
											Scripts <v-icon>mdi-code-tags</v-icon>
										</v-tab>
										<v-tab key="Backups" href="#tab-Backups" @click="viewQuicksaves( site.id ); viewSnapshots( site.id );">
											Backups <v-icon>mdi-update</v-icon>
										</v-tab>
									</v-tabs>
									</v-flex>
									</v-layout>
								</div>
				<v-tabs-items v-model="site.tabs_management" v-if="site.environments.filter( key => key.environment == site.environment_selected ).length == 1">
					<v-tab-item :key="1" value="tab-Info">
						<v-toolbar color="grey lighten-4" dense light flat>
							<v-toolbar-title>Info</v-toolbar-title>
							<v-spacer></v-spacer>
						</v-toolbar>

						<v-card v-for="key in site.environments" v-show="key.environment == site.environment_selected" flat>
							<v-container fluid>
							<v-layout body-1 px-6 class="row">
								<v-flex xs12 md6 class="py-2">
								<div class="block mt-6">
									<v-img :src="key.screenshot_large" max-width="400" aspect-ratio="1.6" class="elevation-5" v-show="key.screenshot_large" style="margin:auto;"></v-img>
								</div>
								</v-flex>
								<v-flex xs12 md6 class="keys py-2">
								<v-list dense style="padding:0px;max-width:350px;margin: auto;">
									<v-list-item :href="key.link" target="_blank" dense>
									<v-list-item-content>
										<v-list-item-title>Link</v-list-item-title>
										<v-list-item-subtitle v-text="key.link"></v-list-item-subtitle>
									</v-list-item-content>
									<v-list-item-icon>
										<v-icon>mdi-open-in-new</v-icon>
									</v-list-item-icon>
									</v-list-item>
									<v-list-item @click="copyText( key.address )" dense>
									<v-list-item-content>
										<v-list-item-title>Address</v-list-item-title>
										<v-list-item-subtitle v-text="key.address"></v-list-item-subtitle>
									</v-list-item-content>
									<v-list-item-icon>
										<v-icon>mdi-content-copy</v-icon>
									</v-list-item-icon>
									</v-list-item>
									<v-list-item @click="copyText( key.username )" dense>
									<v-list-item-content>
										<v-list-item-title>Username</v-list-item-title>
										<v-list-item-subtitle v-text="key.username"></v-list-item-subtitle>
									</v-list-item-content>
									<v-list-item-icon>
										<v-icon>mdi-content-copy</v-icon>
									</v-list-item-icon>
									</v-list-item>
									<v-list-item @click="copyText( key.password )" dense>
									<v-list-item-content>
										<v-list-item-title>Password</v-list-item-title>
										<v-list-item-subtitle>##########</v-list-item-subtitle>
									</v-list-item-content>
									<v-list-item-icon>
										<v-icon>mdi-content-copy</v-icon>
									</v-list-item-icon>
									</v-list-item>
									<v-list-item @click="copyText( key.protocol )" dense>
									<v-list-item-content>
										<v-list-item-title>Protocol</v-list-item-title>
										<v-list-item-subtitle v-text="key.protocol"></v-list-item-subtitle>
									</v-list-item-content>
									<v-list-item-icon>
										<v-icon>mdi-content-copy</v-icon>
									</v-list-item-icon>
									</v-list-item>
									<v-list-item @click="copyText( key.port )" dense>
									<v-list-item-content>
										<v-list-item-title>Port</v-list-item-title>
										<v-list-item-subtitle v-text="key.port"></v-list-item-subtitle>
									</v-list-item-content>
									<v-list-item-icon>
										<v-icon>mdi-content-copy</v-icon>
									</v-list-item-icon>
									</v-list-item>
								<div v-if="key.database && key.ssh">
									<div v-if="key.database">
										<v-list-item :href="key.database" target="_blank" dense>
										<v-list-item-content>
											<v-list-item-title>Database</v-list-item-title>
											<v-list-item-subtitle v-text="key.database"></v-list-item-subtitle>
										</v-list-item-content>
										<v-list-item-icon>
											<v-icon>mdi-open-in-new</v-icon>
										</v-list-item-icon>
										</v-list-item>
										<v-list-item @click="copyText( key.database_username )" dense>
										<v-list-item-content>
											<v-list-item-title>Database Username</v-list-item-title>
											<v-list-item-subtitle v-text="key.database_username"></v-list-item-subtitle>
										</v-list-item-content>
										<v-list-item-icon>
											<v-icon>mdi-content-copy</v-icon>
										</v-list-item-icon>
										</v-list-item>
										<v-list-item @click="copyText( key.database_password )" dense>
										<v-list-item-content>
											<v-list-item-title>Database Username</v-list-item-title>
											<v-list-item-subtitle>##########</v-list-item-subtitle>
										</v-list-item-content>
										<v-list-item-icon>
											<v-icon>mdi-content-copy</v-icon>
										</v-list-item-icon>
										</v-list-item>
									</div>
									<div v-if="key.ssh">
										<v-list-item @click="copyText( key.ssh )" dense>
										<v-list-item-content>
											<v-list-item-title>SSH Connection</v-list-item-title>
											<v-list-item-subtitle v-text="key.ssh"></v-list-item-subtitle>
										</v-list-item-content>
										<v-list-item-icon>
											<v-icon>mdi-content-copy</v-icon>
										</v-list-item-icon>
										</v-list-item>
									</div>
								</div>
								</v-list>
							</v-flex>
						</v-layout>
						</v-container>
					</v-card>
				</v-tab-item>
				<v-tab-item :key="100" value="tab-Stats">
					<v-card 
						v-for="key in site.environments"
						v-show="key.environment == site.environment_selected"
						flat
					>
					<v-toolbar color="grey lighten-4" dense light flat>
						<v-toolbar-title>Stats</v-toolbar-title>
						<v-spacer></v-spacer>
						<v-toolbar-items v-if="typeof dialog_new_site == 'object'">
							<v-btn text @click="configureFathom( site.id )">Configure Fathom Tracker <v-icon dark small>bar_chart</v-icon></v-btn>
						</v-toolbar-items>
					</v-toolbar>
						<div class="pa-3" v-if="typeof key.stats == 'string' && key.stats != 'Loading'">
							{{ key.stats }}
						</div>
						<v-layout wrap>
						<v-flex xs12>
							<v-progress-linear :indeterminate="true" absolute v-show="key.stats == 'Loading'"></v-progress-linear>
							<div :id="`chart_` + site.id + `_` + key.environment"></div>
							<v-card flat v-if="key.stats.agg">
							<v-card-title class="text-center pa-0">
							<v-layout wrap>
							<v-flex xs6 sm3>
								<span class="text-uppercase caption">Unique Visitors</span><br />
								<span class="display-1 font-weight-thin text-uppercase">{{ key.stats.agg.Visitors | formatk }}</span>
							</v-flex>
							<v-flex xs6 sm3>
								<span class="text-uppercase caption">Pageviews</span><br />
								<span class="display-1 font-weight-thin text-uppercase">{{ key.stats.agg.Pageviews | formatk }}</span>
							</v-flex>
							<v-flex xs6 sm3>
								<span class="text-uppercase caption">Avg Time On Site</span><br />
								<span class="display-1 font-weight-thin text-uppercase">{{ key.stats.agg.AvgDuration | formatTime }}</span>
							</v-flex>
							<v-flex xs6 sm3>
								<span class="text-uppercase caption">Bounce Rate</span><br />
								<span class="display-1 font-weight-thin text-uppercase">{{ key.stats.agg.BounceRate | formatPercentageFixed }}</span>
							</v-flex>
							</v-layout>
							</v-card-title>
							</v-card>
							<v-card flat class="mb-3">
							<v-card-title>
							<v-layout wrap v-show="key.stats.pages">
							<v-flex xs12 sm6 pr-2>
							<v-data-table
								:headers='[{"text":"Top Pages","value":"page",sortable: false, class: "text-truncate"},{"text":"Views","value":"views",sortable: false, "width": 90, align: "right"},{"text":"Uniques","value":"uniques",sortable: false, "width": 98, align: "right"}]'
								:items="key.stats.pages"
								class="elevation-0 table-layout-fixed"
								hide-default-footer
							>
								<template v-slot:body="{ items }">
								<tbody>
									<tr v-for="item in items">
										<td class="text-truncate"><a :href="item.Hostname + item.Pathname" target="_blank" class="text-truncate">{{ item.Pathname }}</a></td>
										<td class="text-right">{{ item.Pageviews | formatk }}</td>
										<td class="text-right">{{ item.Visitors | formatk }}</td>
									</tr>
								</tbody>
								</template>
							</v-data-table>
							</v-flex>
							<v-flex xs12 sm6 pl-2>
							<v-data-table
								:headers='[{"text":"Top Referrers","value":"referrer", sortable: false, align: "truncate"},{"text":"Views", "value":"views" ,sortable: false, "width": 90, align: "right"},{"text":"Uniques","value":"uniques", sortable: false, "width": 98, align: "right"}]'
								:items="key.stats.referrers"
								class="elevation-0 table-layout-fixed"
								hide-default-footer
							>
								<template v-slot:body="{ items }">
								<tbody>
									<tr v-for="item in items">
										<td class="text-truncate"><a :href="item.Hostname + item.Pathname" target="_blank">{{ item.Group || item.Hostname + item.Pathname }}</a></td>
										<td class="text-right">{{ item.Pageviews | formatk }}</td>
										<td class="text-right">{{ item.Visitors | formatk }}</td>
									</tr>
								</tbody>
								</template>
							</v-data-table>
							
							</v-flex>
							</v-layout>
							</v-card-title>
							</v-card>
						</v-flex>
					</v-card>
				</v-tab-item>
				<v-tab-item :key="3" value="tab-Addons">
					<v-card 
						v-for="key in site.environments"
						v-show="key.environment == site.environment_selected"
					flat
					>
					<v-toolbar color="grey lighten-4" dense light flat>
						<v-toolbar-title>Addons <small>(Themes/Plugins)</small></v-toolbar-title>
						<v-spacer></v-spacer>
						<v-toolbar-items>
							<v-btn text @click="bulkEdit(site.id, 'plugins')" v-if="key.plugins_selected.length != 0">Bulk Edit {{ key.plugins_selected.length }} plugins</v-btn>
							<v-btn text @click="bulkEdit(site.id, 'themes')" v-if="key.themes_selected.length != 0">Bulk Edit {{ key.themes_selected.length }} themes</v-btn>
							<v-btn text @click="addTheme(site.id)">Add Theme <v-icon dark small>add</v-icon></v-btn>
							<v-btn text @click="addPlugin(site.id)">Add Plugin <v-icon dark small>add</v-icon></v-btn>
						</v-toolbar-items>
					</v-toolbar>
					<v-card-title v-if="typeof key.themes == 'string'">
					<div>
						Updating themes...
						<v-progress-linear :indeterminate="true"></v-progress-linear>
					</div>
					</v-card-title>
					<div v-else>
					<v-subheader>Themes</v-subheader>
					<v-data-table
						v-model="key.themes_selected"
						:headers="header_themes"
						:items="key.themes"
						:loading="site.loading_themes"
						:items-per-page="-1"
						:footer-props="{ itemsPerPageOptions: [{'text':'All','value':-1}] }"
						item-key="name"
						value="name"
						show-select
						hide-default-footer
						>
						<template v-slot:item.status="{ item }">
							<div v-if="item.status === 'inactive' || item.status === 'parent' || item.status === 'child'">
								<v-switch hide-details v-model="item.status" false-value="inactive" true-value="active" @change="activateTheme(props.item.name, site.id)"></v-switch>
							</div>
							<div v-else>
								{{ item.status }}
							</div>
						</template>
						<template v-slot:item.actions="{ item }" class="text-center px-0">
							<v-btn icon small class="mx-0" @click="deleteTheme(item.name, site.id)">
								<v-icon small color="pink">delete</v-icon>
							</v-btn>
						</template>
					</v-data-table>
				</div>
					<v-card-title v-if="typeof key.plugins == 'string'">
						<div>
							Updating plugins...
							<v-progress-linear :indeterminate="true"></v-progress-linear>
						</div>
					</v-card-title>
					<div v-else>
					<v-subheader>Plugins</v-subheader>
					<v-data-table
						:headers="header_plugins"
						:items="key.plugins.filter(plugin => plugin.status != 'must-use' && plugin.status != 'dropin')"
						:loading="site.loading_plugins"
						:items-per-page="-1"
						:footer-props="{ itemsPerPageOptions: [{'text':'All','value':-1}] }"
						v-model="key.plugins_selected"
						item-key="name"
						value="name"
						show-select
						hide-default-footer
					>
					<template v-slot:item.status="{ item }">
						<div v-if="item.status === 'inactive' || item.status === 'active'">
						<v-switch hide-details v-model="item.status" false-value="inactive" true-value="active" @change="togglePlugin(item.name, item.status, site.id)"></v-switch>
						</div>
						<div v-else>
							{{ item.status }}
						</div>
					</template>
					<template v-slot:item.actions="{ item }" class="text-center px-0">
						<v-btn icon small class="mx-0" @click="deletePlugin(item.name, site.id)" v-if="item.status === 'active' || item.status === 'inactive'">
							<v-icon small color="pink">delete</v-icon>
						</v-btn>
					</template>
					<template v-slot:body.append>
						<tr v-for="plugin in key.plugins.filter(plugin => plugin.status == 'must-use' || plugin.status == 'dropin')">
							<td></td>
							<td>{{ plugin.title }}</td>
							<td>{{ plugin.name }}</td>
							<td>{{ plugin.version }}</td>
							<td>{{ plugin.status }}</td>
							<td class="text-center px-0"></td>
						</tr>
					</template>
					</v-data-table>
				</div>
			</v-tab-item>
			<v-tab-item :key="4" value="tab-Users">
				<v-card 
					v-for="key in site.environments"
					v-show="key.environment == site.environment_selected"
					flat
					>
				<v-toolbar color="grey lighten-4" dense light flat>
					<v-toolbar-title>Users</v-toolbar-title>
					<v-spacer></v-spacer v-show="site.environment_selected == 'Production'">
					<v-toolbar-items>
						<v-btn text @click="bulkEdit(site.id,'users')" v-if="key.users_selected.length != 0">Bulk Edit {{ key.users_selected.length }} users</v-btn>
					</v-toolbar-items>
				</v-toolbar>
					<div v-show="typeof key.users == 'string'">
						<v-progress-linear :indeterminate="true" absolute></v-progress-linear>
						<p></p>
					</div>
					<div v-if="typeof key.users != 'string'">
						<v-data-table
							:headers='header_users'
							:items-per-page="50"
							:footer-props="{ itemsPerPageOptions: [50,100,250,{'text':'All','value':-1}] }"
							:items="key.users"
							item-key="user_login"
							v-model="key.users_selected"
							class="table_users"
							show-select
						>
						<template v-slot:item.roles="{ item }">
							{{ item.roles.split(",").join(" ") }}
						</template>
						<template v-slot:item.actions="{ item }">
							<v-btn small rounded @click="loginSite(site.id, item.user_login)" class="my-2">Login as</v-btn>
							<v-btn icon small class="my-2" @click="deleteUserDialog( item.user_login, site.id)">
								<v-icon small color="pink">delete</v-icon>
							</v-btn>
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
						<v-btn text @click="update(site.id)">Manual update <v-icon dark>mdi-sync</v-icon></v-btn>
						<v-btn text @click="updateSettings(site.id)">Update Settings <v-icon dark>mdi-settings</v-icon></v-btn>
						<!-- <v-btn text @click="themeAndPluginChecks(site.id)">Theme/plugin checks <v-icon dark small>fas fa-calendar-check</v-icon></v-btn> -->
					</v-toolbar-items>
				</v-toolbar>
				<v-card 
					v-for="key in site.environments"
					v-show="key.environment == site.environment_selected" 
					flat
				>
					<div v-show="typeof key.update_logs == 'string'">
						<v-progress-linear :indeterminate="true" absolute></v-progress-linear>
					</div>
					<div v-if="typeof key.update_logs != 'string'">
							<v-data-table
								:headers='header_updatelog'
								:items="key.update_logs"
								class="update_logs"
								:items-per-page-options='[50,100,250,{"text":"All","value":-1}]'
							>
						    <template v-slot:body="{ items }">
							<tbody>
							<tr v-for="item in items">
								<td>{{ item.date | pretty_timestamp }}</td>
								<td>{{ item.type }}</td>
								<td>{{ item.name }}</td>
								<td class="text-right">{{ item.old_version }}</td>
								<td class="text-right">{{ item.new_version }}</td>
								<td>{{ item.status }}</td>
							</tr>
							</tbody>
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
				<v-card flat>
					<v-card-title>
					<v-layout align-start>
					<v-flex xs12 sm8 pr-4>
					<v-subheader id="script_site">Custom bash script or WP-CLI commands</v-subheader>
						<v-textarea
							auto-grow
							solo
							label=""
							hide-details
							:value="custom_script" 
							@change.native="custom_script = $event.target.value"
						></v-textarea>
						<v-btn small color="primary" dark @click="runCustomCode(site.id)">Run Custom Code</v-btn>
					</v-flex>
					<v-flex xs12 sm4>
						<v-list dense>
						<v-subheader>Common</v-subheader>
						<v-list-item @click="viewApplyHttpsUrls(site.id)" dense>
						<v-list-item-icon>
							<v-icon>launch</v-icon>
						</v-list-item-icon>
						<v-list-item-content>
							<v-list-item-title>Apply HTTPS Urls</v-list-item-title>
						</v-list-item-content>
						</v-list-item>
						<v-list-item @click="viewMailgunLogs(site.id)" dense v-if="site.mailgun">
						<v-list-item-icon>
							<v-icon>email</v-icon>
						</v-list-item-icon>
						<v-list-item-content>
							<v-list-item-title>View Mailgun Logs</v-list-item-title>
						</v-list-item-content>
						</v-list-item>
						<v-list-item @click="siteDeploy(site.id)" dense>
						<v-list-item-icon>
							<v-icon>loop</v-icon>
						</v-list-item-icon>
						<v-list-item-content>
							<v-list-item-title>Deploy users/plugins</v-list-item-title>
						</v-list-item-content>
						</v-list-item>
						<v-list-item @click="launchSiteDialog(site.id)" dense>
						<v-list-item-icon>
							<v-icon>mdi-rocket</v-icon>
						</v-list-item-icon>
						<v-list-item-content>
							<v-list-item-title>Launch Site</v-list-item-title>
						</v-list-item-content>
						</v-list-item>
						<v-list-item @click="showSiteMigration(site.id)" dense>
						<v-list-item-icon>
							<v-icon>mdi-truck</v-icon>
						</v-list-item-icon>
						<v-list-item-content>
							<v-list-item-title>Migrate from backup</v-list-item-title>
						</v-list-item-content>
						</v-list-item>
						<v-list-item @click="toggleSite(site.id)" dense>
						<v-list-item-icon>
							<v-icon>mdi-toggle-switch</v-icon>
						</v-list-item-icon>
						<v-list-item-content>
							<v-list-item-title>Toggle Site</v-list-item-title>
						</v-list-item-content>
						</v-list-item>
						<v-subheader v-show="recipes.filter( r => r.public == 1 ).length > 0">Other</v-subheader>
						<v-list-item @click="runRecipe( recipe.recipe_id, site.id )" dense v-for="recipe in recipes.filter( r => r.public == 1 )">
						<v-list-item-icon>
							<v-icon>mdi-script-text-outline</v-icon>
						</v-list-item-icon>
						<v-list-item-content>
							<v-list-item-title v-text="recipe.title"></v-list-item-title>
						</v-list-item-content>
						</v-list-item>
						<v-subheader v-show="recipes.filter( r => r.public != 1 ).length > 0">User</v-subheader>
						<v-list-item @click="loadRecipe( recipe.recipe_id ); $vuetify.goTo( '#script_site' );" dense v-for="recipe in recipes.filter( r => r.public != 1 )">
						<v-list-item-icon>
							<v-icon>mdi-script-text-outline</v-icon>
						</v-list-item-icon>
						<v-list-item-content>
							<v-list-item-title v-text="recipe.title"></v-list-item-title>
						</v-list-item-content>
						</v-list-item>
						</v-list>
					</v-flex>
					</v-layout>
					</v-card-title>
				</v-card>
			</v-tab-item>
			<v-tab-item :key="7" value="tab-Backups">
				<v-toolbar color="grey lighten-4" dense light flat>
					<v-toolbar-title>Backups <small>(Quicksaves & Snapshots)</small></v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-tooltip top>
							<template v-slot:activator="{ on }">
								<v-btn text small @click="promptBackupSnapshot( site.id )" v-on="on"><v-icon dark>mdi-cloud-download</v-icon></v-btn>
							</template><span>Generate and Download Snapshot</span>
						</v-tooltip>
						<v-divider vertical class="mx-1" inset></v-divider>
						<v-tooltip top>
							<template v-slot:activator="{ on }">
							<v-btn text @click="QuicksaveCheck( site.id )" v-on="on"><v-icon dark>mdi-sync</v-icon></v-btn>
							</template><span>Manual check for new Quicksave</span>
						</v-tooltip>
						
					</v-toolbar-items>
				</v-toolbar>
				<v-card 
				v-for="key in site.environments"
				v-show="key.environment == site.environment_selected"
				flat>
					<v-subheader>Quicksaves</v-subheader>
					<div v-if="typeof key.quicksaves == 'string'">
						<v-progress-linear :indeterminate="true" absolute></v-progress-linear>
					</div>
					<div v-else>
					<v-data-table
						:headers="[{text:'Created At',value:'created_at'},{text:'WordPress',value:'core',width:'115px'},{text:'',value:'themes',width:'100px'},{text:'',value:'plugins',width:'100px'}]"
						:items="key.quicksaves"
						item-key="quicksave_id"
						no-data-text="No quicksaves found."
						:ref="'quicksave_table_'+ site.id + '_' + key.environment"
						@click:row="expandQuicksave( $event, site.id, key.environment )"
						single-expand
						show-expand
						class="table-quicksaves"
					>
					<template v-slot:item.created_at="{ item }">
						{{ item.created_at | pretty_timestamp }}
					</template>
					<template v-slot:item.core="{ item }">
						{{ item.core }}
					</template>
					<template v-slot:item.themes="{ item }">
						{{ item.themes.length }} themes
					</template>
					<template v-slot:item.plugins="{ item }">
						{{ item.plugins.length }} plugins
					</template>
					<template v-slot:expanded-item="{ item }">
						<td colspan="5" style="position: relative;background: #fff; padding:0px">
						<v-toolbar color="dark primary" dark dense light class="elevation-0">
							<v-toolbar-title class="body-2">{{ item.git_status }}</v-toolbar-title>
							<v-spacer></v-spacer>
							<v-toolbar-items>
								<v-btn text small @click="QuicksavesRollback( site.id, item)">Rollback Everything</v-btn>
								<v-divider vertical class="mx-1" inset></v-divider>
								<v-btn text small @click="viewQuicksavesChanges( site.id, item)">View Changes</v-btn>
							</v-toolbar-items>
						</v-toolbar>
						<v-card flat v-show="item.view_changes == true" style="table-layout:fixed;margin:0px;overflow: scroll;padding: 0px;position: absolute;background-color: #fff;width: 100%;left: 0;top: 100%;height: 100%;z-index: 3;transform: translateY(-100%);">
							<v-toolbar color="dark primary" dark dense light>
								<v-btn icon dark @click.native="item.view_changes = false">
									<v-icon>close</v-icon>
								</v-btn>
								<v-toolbar-title>List of changes</v-toolbar-title>
								<v-spacer></v-spacer>
							</v-toolbar>
								<v-card-text>
									<v-card-title>
										Files
									</v-card-title>
									<v-spacer></v-spacer>
									<v-layout>
										<v-flex sx12 sm9>
										</v-flex sx12 sm3>
										<v-flex>
										<v-text-field
											v-model="item.search"
											@input="filterFiles( site.id, item.quicksave_id)"
											append-icon="search"
											label="Search"
											single-line
											hide-details
										></v-text-field>
										</v-flex>
									</v-layout>
									<v-data-table no-data-text="" :headers='[{"text":"File","value":"file"}]' :items="item.filtered_files" :loading="item.loading">
										<template v-slot:body="{ items }">
										<tbody>
											<tr v-for="i in items">
												<td>
													<a class="v-menu__activator" @click="QuicksaveFileDiff(item.site_id, item.quicksave_id, item.git_commit, i)">{{ i }}</a>
												</td>
											</tr>
										</tbody>
										</template>
										<v-alert slot="no-results" :value="true" color="error" icon="warning">
											Your search for "{{ item.search }}" found no results.
										</v-alert>
									</v-data-table>
								</v-card-text>
							</v-card>
						<v-card flat>
							<v-data-table
								:headers='[{"text":"Theme","value":"title"},{"text":"Version","value":"version"},{"text":"Status","value":"status"},{"text":"","value":"actions","width":"150px"}]'
								:items="item.themes"
								item-key="name"
								class="quicksave-table"
							>
							<template v-slot:body="{ items }">
							<tbody>
							<tr v-for="theme in items" v-bind:class="{ 'green lighten-5': theme.changed_version || theme.changed_status }">
								<td>{{ theme.title || theme.name }}</td>
								<td v-bind:class="{ 'green lighten-4': theme.changed_version }">{{ theme.version }}</td>
								<td v-bind:class="{ 'green lighten-4': theme.changed_status }">{{ theme.status }}</td>
								<td><v-btn depressed small @click="RollbackQuicksave(item.site_id, item.quicksave_id, 'theme', theme.name)">Rollback</v-btn></td>
							</tr>
							</template>
								<template v-slot:body.append="{ headers }">
								<tr class="red lighten-4 strikethrough" v-for="theme in quicksave.deleted_themes">
								<td>{{ theme.title || theme.name }}</td>
								<td>{{ theme.version }}</td>
								<td>{{ theme.status }}</td>
								<td></td>
								</tr>
								</tbody>
								</template>
							</v-data-table>
							<v-data-table
								:headers='[{"text":"Plugin","value":"plugin"},{"text":"Version","value":"version"},{"text":"Status","value":"status"},{"text":"","value":"actions","width":"150px"}]'
								:items="item.plugins"
								item-key="name"
								class="quicksave-table"
								:items-per-page="25"
								:footer-props="{ itemsPerPageOptions: [25,50,100,{'text':'All','value':-1}] }"
								>
								<template v-slot:body="{ items }">
								<tbody>
								<tr v-for="plugin in items" v-bind:class="[{ 'green lighten-5': plugin.changed_version || plugin.changed_status },{ 'red lighten-4 strikethrough': plugin.deleted }]">
								<td>{{ plugin.title || plugin.name }}</td>
								<td v-bind:class="{ 'green lighten-4': plugin.changed_version }">{{ plugin.version }}</td>
								<td v-bind:class="{ 'green lighten-4': plugin.changed_status }">{{ plugin.status }}</td>
								<td><v-btn depressed small @click="RollbackQuicksave(item.site_id, item.quicksave_id, 'plugin', plugin.name)" v-show="plugin.status != 'must-use' && plugin.status != 'dropin'">Rollback</v-btn></td>
								</tr>
								</template>
								<template v-slot:body.append="{ headers }">
								<tr class="red lighten-4 strikethrough" v-for="plugin in quicksave.deleted_plugins">
								<td>{{ plugin.title || plugin.name }}</td>
								<td>{{ plugin.version }}</td>
								<td>{{ plugin.status }}</td>
								<td></td>
								</tr>
								</tbody>
								</template>
							</v-data-table>

						</v-card>
						</td>
					</template>
					</v-data-table>
					</div>
					<v-subheader>Snapshots</v-subheader>
					<div v-if="typeof key.snapshots == 'string'">
						<v-progress-linear :indeterminate="true" absolute></v-progress-linear>
					</div>
					<div v-else>
					<v-data-table
						:headers="[{text:'Created At',value:'created_at',width:'250px'},{text:'User',value:'user',width:'125px'},{text:'Storage',value:'storage',width:'100px'},{text:'Notes',value:'notes'},{text:'',value:'actions',sortable: false,width:'190px'}]"
						:items="key.snapshots"
						item-key="snapshot_id"
						no-data-text="No snapshots found."
					>
					<template v-slot:item.user="{ item }">
						{{ item.user.name }}
					</template>
					<template v-slot:item.created_at="{ item }">
						{{ item.created_at | pretty_timestamp }}
					</template>
					<template v-slot:item.storage="{ item }">
						{{ item.storage | formatSize }}
					</template>
					<template v-slot:item.actions="{ item }">
					<template v-if="item.token && new Date() < new Date( item.expires_at )">
						<v-tooltip bottom>
							<template v-slot:activator="{ on }">
							<v-btn small icon @click="fetchLink( site.id, item.snapshot_id )" v-on="on">
								<v-icon color="grey">mdi-sync</v-icon>
							</v-btn>
							</template>
							<span>Generate new link. Link valid for 24hrs.</span>
						</v-tooltip>
						<v-btn small rounded :href="`/wp-json/captaincore/v1/site/${site.id}/snapshots/${item.snapshot_id}-${item.token}/${item.snapshot_name.slice(0, -4)}`">Download</v-btn>
					</template>
					<template v-else>
						<v-tooltip bottom>
							<template v-slot:activator="{ on }">
							<v-btn small icon @click="fetchLink( site.id, item.snapshot_id )" v-on="on">
								<v-icon color="grey">mdi-sync</v-icon>
							</v-btn>
							</template>
							<span>Generate new link. Link valid for 24hrs.</span>
						</v-tooltip>
						<v-btn small rounded disabled>Download</v-btn>
					</template>
					</template>
					</v-data-table>
					</div>
					</v-card>
			</v-tab-item>
		</v-tabs-items>
		<v-card text v-if="site.environments.filter( key => key.environment == site.environment_selected ).length == 0">
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
						<v-btn text @click="modifyPlan( site.id )">Modify Plan <v-icon dark small>edit</v-icon></v-btn>
					</v-toolbar-items>
			</v-toolbar>
			<v-card flat>
				<div v-if="typeof site.customer.hosting_plan.visits_limit == 'string'">
				<v-card-text class="body-1">
				<v-layout align-center justify-left row/>
					<div style="padding: 10px 10px 10px 20px;">
						<v-progress-circular :size="50" :value="( site.customer.usage.storage / ( site.customer.hosting_plan.storage_limit * 1024 * 1024 * 1024 ) ) * 100 | formatPercentage" color="primary"><small>{{ ( site.customer.usage.storage / ( site.customer.hosting_plan.storage_limit * 1024 * 1024 * 1024 ) ) * 100 | formatPercentage }}</small></v-progress-circular>
					</div>
					<div style="line-height: 0.85em;">
						Storage <br /><small>{{ site.customer.usage.storage | formatGBs }}GB / {{ site.customer.hosting_plan.storage_limit }}GB</small><br />
					</div>
					<div style="padding: 10px 10px 10px 20px;">
						<v-progress-circular :size="50" :value="( site.customer.usage.visits / site.customer.hosting_plan.visits_limit * 100 ) | formatPercentage" color="primary"><small>{{ ( site.customer.usage.visits / site.customer.hosting_plan.visits_limit ) * 100 | formatPercentage }}</small></v-progress-circular>
					</div>
					<div style="line-height: 0.85em;">
						Visits <br /><small>{{ site.customer.usage.visits | formatLargeNumbers }} / {{ site.customer.hosting_plan.visits_limit | formatLargeNumbers }}</small><br />
					</div>
					<div style="padding: 10px 10px 10px 20px;">
						<v-progress-circular :size="50" :value="( site.customer.usage.sites / site.customer.hosting_plan.sites_limit * 100 ) | formatPercentage" color="blue darken-4"><small>{{ ( site.customer.usage.sites / site.customer.hosting_plan.sites_limit * 100 ) | formatPercentage }}</small></v-progress-circular>
					</div>
					<div  style="line-height: 0.85em;">
						Sites <br /><small>{{ site.customer.usage.sites }} / {{ site.customer.hosting_plan.sites_limit }}</small><br />
					</div>
				</v-layout>
				</v-card-text>
				<v-alert
					:value="true"
					type="info"
					color="primary"
				>
					<strong>{{ site.customer.hosting_plan.name }} Plan</strong> which supports up to {{ site.customer.hosting_plan.visits_limit | formatLargeNumbers }} visits, {{ site.customer.hosting_plan.storage_limit }}GB storage and {{ site.customer.hosting_plan.sites_limit }} sites.
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
				<v-data-table
					:headers='[{"text":"Name","value":"name"},{"text":"Storage","value":"Storage"},{"text":"Visits","value":"visits"}]'
					:items="site.usage_breakdown.sites"
					item-key="name"
					hide-default-footer
				>
				<template v-slot:body="{ items }">
				<tbody>
					<tr v-for="item in items">
						<td>{{ item.name }}</td>
						<td>{{ item.storage }}GB</td>
						<td>{{ item.visits }}</td>
					</tr>
					<tr>
						<td>Totals:</td>
						<td v-for="total in site.usage_breakdown.total" v-html="total"></td>
					</tr>
				</tbody>
				</template>
				</v-data-table>
			</v-card>
		</v-tab-item>
		<v-tab-item :key="7" value="tab-Sharing">
			<v-toolbar color="grey lighten-4" dense light flat>
				<v-toolbar-title>Sharing</v-toolbar-title>
				<v-spacer></v-spacer>
				<v-toolbar-items v-show="role == 'administrator'">
					<v-btn text>Invite</v-btn>
				</v-toolbar-items>
			</v-toolbar>
			<v-layout>
			<v-list disabled>
				<v-subheader>Customer</v-subheader>
				<v-list-item :key="site.customer.customer_id">
					<v-list-item-icon>
						<v-icon>mdi-account</v-icon>
					</v-list-item-icon>
					<v-list-item-content>
						<v-list-item-title>{{ site.customer.name }}</v-list-item-title>
					</v-list-item-content>
				</v-list-item>
				<v-divider inset></v-divider>
				<v-subheader>Shared With</v-subheader>
				<v-list-item v-for="customer in site.shared_with" :key="customer.customer_id">
					<v-list-item-icon>
						<v-icon>mdi-account</v-icon>
					</v-list-item-icon>
					<v-list-item-content>
						<v-list-item-title>{{ customer.name }}</v-list-item-title>
					</v-list-item-content>
				</v-list-item>
			</v-list>
			</v-layout>
	  </v-tab-item>
		<v-tab-item :key="8" value="tab-Timeline">
			<v-toolbar color="grey lighten-4" dense light flat>
				<v-toolbar-title>Timeline</v-toolbar-title>
				<v-spacer></v-spacer>
				<v-toolbar-items v-show="role == 'administrator'">
					<v-btn text @click="showLogEntry(site.id)">New Log Entry <v-icon dark>mdi-checkbox-marked</v-icon></v-btn>
				</v-toolbar-items>
			</v-toolbar>
			<v-card flat>
			<v-data-table
				:headers="header_timeline"
				:items="site.timeline"
				class="timeline"
				hide-default-footer
				>
				<template v-slot:body="{ items }">
					<tbody>
					<tr v-for="item in items">
					<td class="justify-center">{{ item.created_at | pretty_timestamp }}</td>
					<td class="justify-center">{{ item.author }}</td>
					<td class="justify-center">{{ item.title }}</td>
					<td class="justify-center py-3" v-html="item.description"></td>
					<td v-if="role == 'administrator'"><v-icon
            small
            class="mr-2"
            @click="editLogEntry(item.websites, item.id)"
          >
            edit
		  </v-icon></td>
				</tr>
				</tbody>
				</template>
			</v-data-table>
			</v-card>
		</v-tab-item>
		<v-tab-item :key="9" value="tab-Advanced">
			<v-toolbar color="grey lighten-4" dense light flat>
				<v-toolbar-title>Advanced</v-toolbar-title>
				<v-spacer></v-spacer>
				<v-toolbar-items>
					<v-btn text @click="copySite(site.id)">Copy Site <v-icon dark small>file_copy</v-icon></v-btn>
					<v-btn text @click="editSite(site.id)" v-show="role == 'administrator'">Edit Site <v-icon dark small>edit</v-icon></v-btn>
					<v-btn text @click="deleteSite(site.id)" v-show="role == 'administrator'">Remove Site <v-icon dark small>delete</v-icon></v-btn>
				</v-toolbar-items>
			</v-toolbar>
			<v-card flat>
				<v-card-title>
					<div>
						<div v-show="site.provider == 'kinsta'">
						<v-btn left small text @click="PushProductionToStaging( site.id )">
							<v-icon>local_shipping</v-icon> <span>Push Production to Staging</span>
						</v-btn>
						</div>
						<div v-show="site.provider == 'kinsta'">
						<v-btn left small text @click="PushStagingToProduction( site.id )">
							<v-icon class="reverse">local_shipping</v-icon> <span>Push Staging to Production</span>
						</v-btn>
						</div>
					</div>
				</v-card-title>
			</v-card>
		</v-tab-item>
	</v-tabs>
				</v-expansion-panel-content>
			</v-expansion-panel>
			</v-expansion-panels>
				<v-layout justify-center>
				<div class="text-center">
					<v-pagination v-if="Math.ceil(filteredSites / items_per_page) > 1" :length="Math.ceil(filteredSites / items_per_page)" v-model="page" :total-visible="7" color="blue darken-3" class="mt-5"></v-pagination>
				</div>
				</v-layout>
			</v-card-text>
			</v-card>
			<v-card tile v-show="route == 'dns'" flat>
				<v-toolbar color="grey lighten-4" light flat>
					<v-toolbar-title>Domains <small v-show="allDomains > 0">({{ allDomains }})</small></v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn text @click="dialog_new_domain.show = true" v-show="role == 'administrator'">Add Domain <v-icon dark>add</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card-text>
				<v-card class="mb-4 dns_introduction" v-show="route == 'dns'">
					<v-alert
						:value="true"
						type="info"
						style="padding:8px 16px;"
						class="blue darken-3"
					>
					<v-layout wrap align-center justify-center row fill-height>
					<v-flex xs12 md9 px-2 subtitle-1>
						<div v-html="dns_introduction"></div>
					</v-flex>
					<v-flex xs12 md3 px-2 text-center v-show="dns_nameservers != ''">
						<v-chip color="primary" text-color="white">Nameservers</v-chip>
						<div v-html="dns_nameservers"></div>
					</v-flex>
					</v-layout>
					</v-alert>
				</v-card>
				<v-layout justify-center>
				<v-container fluid grid-list-lg>
				<v-layout row wrap>
					<v-flex v-for="domain in domains" :key="domain.id" xs6>
					<v-card>
						<v-card-title primary-title>
						<div>
							<h3 class="headline mb-0">{{ domain.name }}</h3>
						</div>
						</v-card-title>
						<v-card-actions>
						<v-btn text color="primary" @click="modifyDNS( domain )">Modify DNS</v-btn>
						</v-card-actions>
					</v-card>
					</v-flex>
				</v-layout>
				</v-container>
				</v-layout>
				</v-card-text>
			</v-card>
			<v-card tile v-show="route == 'cookbook'" v-if="role == 'administrator'" flat>
				<v-toolbar color="grey lighten-4" light flat>
					<v-toolbar-title>Contains {{ recipes.length }} recipes</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn text @click="new_recipe.show = true">Add recipe <v-icon dark>add</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card-text>
				<v-window v-model="cookbook_step">
				<v-window-item :value="1">
					<v-container fluid grid-list-lg>
						<v-layout row wrap>
						<v-flex xs12 v-for="recipe in recipes">
							<v-card :hover="true" @click="editRecipe( recipe.recipe_id )">
							<v-card-title primary-title class="pt-2">
								<div>
									<span class="title">{{ recipe.title }}</a></span>
								</div>
							</v-card-title>
							</v-card>
						</v-flex>
						</v-layout>
				</v-container>
				</v-window-item>
				</v-card-text>
			</v-card>
			<v-card tile v-show="route == 'handbook'" v-if="role == 'administrator'" flat>
				<v-toolbar color="grey lighten-4" light flat>
					<v-toolbar-title>Contains {{ processes.length }} processes</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn text @click="fetchProcessLogs()">Log history</v-btn>
						<v-divider vertical class="mx-1" inset></v-divider>
						<v-btn text @click="showLogEntryGeneric()">Add log entry <v-icon dark>add</v-icon></v-btn>
						<v-divider vertical class="mx-1" inset></v-divider>
						<v-btn text @click="new_process.show = true">Add process <v-icon dark>add</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card-text style="max-height: 100%;">
					<v-container fluid grid-list-lg>
					<v-layout row wrap>
					<v-flex xs12 v-for="process in processes">
						<v-card :hover="true" @click="viewProcess( process.id )">
						<v-card-title primary-title class="pt-2">
							<div>
								<span class="title">{{ process.title }}</a> <v-chip color="primary" text-color="white" text>{{ process.role }}</v-chip></span>
								<div class="caption">
									<v-icon v-show="process.time_estimate != ''" style="padding:0px 5px">mdi-clock-outline</v-icon>{{ process.time_estimate }} 
									<v-icon v-show="process.repeat != '' && process.repeat != null" style="padding:0px 5px">mdi-calendar-repeat</v-icon>{{ process.repeat }} 
									<v-icon v-show="process.repeat_quantity != '' && process.repeat_quantity != null" style="padding:0px 5px">mdi-repeat</v-icon>{{ process.repeat_quantity }}
								</div>
							</div>
						</v-card-title>
						</v-card>
					</v-flex>
					</v-layout>
					</v-container>
					</v-card-text>
					</v-card>
			</v-container>
			<v-container fluid v-show="loading_page">
				Loading...
			</v-container>
			<v-snackbar
				:timeout="3000"
				:multi-line="true"
				v-model="snackbar.show"
				style="z-index: 9999999;"
			>
				{{ snackbar.message }}
				<v-btn dark text @click.native="snackbar.show = false">Close</v-btn>
			</v-snackbar>
		</template>
		</v-container>
		</v-content>
		<v-footer style="z-index: 9;position: relative;font-size:12px;">
			<v-col class="text-right" cols="12">
				<a href="https://github.com/CaptainCore/captaincore" target="_blank">CaptainCore v{{ captaincore_version }}</a>
			</v-col>
		</v-footer>
	</v-app>
</div>
<script>

function titleCase(string) {
	return string.charAt(0).toUpperCase() + string.slice(1);
}

const monthNames = ["January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December"
];

function groupmonth(value, index, array) {
	d = new Date(value['Date']);
	key = (d.getFullYear()-1970)*12 + d.getMonth();
	name = monthNames[d.getMonth()] + " " + d.getFullYear();
	bymonth[key]=bymonth[key]||{Name: "",Visitors: value['Visitors'], Pageviews: value['Pageviews']};
    bymonth[key]={Name: name, Visitors: bymonth[key].Visitors + value['Visitors'], Pageviews: bymonth[key].Pageviews + value['Pageviews']}
}

// Redirect to login page if not logged in.
if ( typeof wpApiSettings == "undefined" ) {
	window.location = "/my-account/"
}

new Vue({
	el: '#app',
	vuetify: new Vuetify(),
	data: {
		captaincore_version: "0.6",
		captaincore_logo: "<?php echo get_field( 'business_logo', 'option' ); ?>",
		captaincore_name: "<?php echo get_field( 'business_name', 'option' ); ?>",
		drawer: null,
		billing_link: "<?php echo get_field( 'billing_link', 'option' ); ?>",
		loading_page: true,
		expanded: [],
		modules: { dns: <?php if ( defined( "CONSTELLIX_API_KEY" ) and defined( "CONSTELLIX_SECRET_KEY" ) ) { echo "true"; } else { echo "false"; } ?> },
		dialog_bulk: { show: false, tabs_management: "tab-Sites", environment_selected: "Production" },
		dialog_delete_user: { show: false, site: {}, users: [], username: "", reassign: {} },
		dialog_apply_https_urls: { show: false, site_id: "", site_name: "", sites: [] },
		dialog_copy_site: { show: false, site: {}, options: [], destination: "" },
		dialog_edit_site: { show: false, site: {}, loading: false },
		dialog_new_domain: { show: false, domain: { name: "", customer: "" } },
		dialog_configure_defaults: { show: false, loading: false, record: {}, records: [], account: "" },
		dialog_timeline: { show: false, loading: false, logs: [], pagination: {}, selected_account: "", account: { default_email: "", default_plugins: [], default_timezone: "", default_users: [], name: "", id: ""} },
		dialog_domain: { show: false, domain: {}, records: [], results: [], loading: true, saving: false },
		dialog_backup_snapshot: { show: false, site: {}, email: "<?php echo $current_user->user_email; ?>", current_user_email: "<?php echo $current_user->user_email; ?>", filter_toggle: true, filter_options: [] },
		dialog_file_diff: { show: false, response: "", loading: false, file_name: "" },
		dialog_mailgun: { show: false, site: {}, response: [], loading: false },
		dialog_modify_plan: { show: false, site: {}, hosting_plan: {}, hosting_addons: [], selected_plan: "", customer_name: "" },
		dialog_launch: { show: false, site: {}, domain: "" },
		dialog_toggle: { show: false, site_name: "", site_id: "" },
		dialog_migration: { show: false, sites: [], site_name: "", site_id: "", update_urls: true, backup_url: "" },
		dialog_theme_and_plugin_checks: { show: false, site: {}, loading: false },
		dialog_update_settings: { show: false, site_id: null, loading: false },
		dialog_fathom: { show: false, site: {}, environment: {}, loading: false, editItem: false, editedItem: {}, editedIndex: -1 },
		timeline_logs: [],
		route: window.location.hash.substring(1),
		page: 1,
		socket: "<?php echo captaincore_fetch_socket_address() . "/ws"; ?>",
		timezones: <?php echo json_encode( timezone_identifiers_list() ); ?>,
		jobs: [],
		custom_script: "",
		recipes: 
		<?php
			$db_recipes = new CaptainCore\recipes();
			$recipes = $db_recipes->fetch_recipes("title","ASC");
			echo json_encode( $recipes );
		?>,
		processes: 
			<?php

			// WP_Query arguments
			$args = array(
				'post_type'      => array( 'captcore_process' ),
				'posts_per_page' => '-1',
				'order'          => 'ASC',
				'orderby'        => 'title',
			);

			// The Query
			$all_processes = get_posts( $args );
			$repeat_field  = get_field_object( 'field_57f791d6363f4' );
			$processes     = array();

			foreach ( $all_processes as $process ) {

				$repeat_value = get_field( 'repeat', $process->ID );
				if ( is_array( $repeat_field ) && isset( $repeat_field['choices'][ $repeat_value ] ) ) {
				$repeat       = $repeat_field['choices'][ $repeat_value ];
				} else {
					$repeat = "";
				}
				$role         = get_the_terms( $process->ID, 'process_role' );
					if ( ! empty( $role ) && ! is_wp_error( $role ) ) {
					   $role = join( ' ', wp_list_pluck( $role, 'name' ) );
				}

				$processes[] = (object) [
							'id'              => $process->ID,
							'title'           => get_the_title( $process->ID ),
							'created_at'      => $process->post_date,
							'time_estimate'   => get_field( 'time_estimate', $process->ID ),
							'repeat'          => $repeat,
							'repeat_quantity' => get_field( 'repeat_quantity', $process->ID ),
							'role'            => $role,
				];
			}
					echo json_encode( $processes );
			?>
		,
		current_user_email: "<?php echo $current_user->user_email; ?>",
		hosting_plans: 
		<?php
			$hosting_plans   = get_field( 'hosting_plans', 'option' );
			$hosting_plans[] = array(
				'name'          => 'Custom',
				'visits_limit'  => '',
				'storage_limit' => '',
				'sites_limit'   => '',
				'price'         => '',
			);
		echo json_encode( $hosting_plans );
		?>
		,
		<?php if ( current_user_can( 'administrator' ) ) { ?>
		role: "administrator",
		dialog_new_log_entry: { show: false, sites: [], site_name: "", process: "", description: "" },
		dialog_edit_log_entry: { show: false, site_name: "", log: {} },
		dialog_log_history: { show: false, logs: [], pagination: {} },
		dialog_cookbook: { show: false, recipe: {}, content: "" },
		dialog_handbook: { show: false, process: {} },
		new_recipe: { show: false, title: "", content: "", public: 1 },
		new_process: { show: false, title: "", time_estimate: "", repeat: "as-needed", repeat_quantity: "", role: "", description: "" },
		dialog_edit_process: { show: false, process: {} },
		new_process_roles: 
			<?php
			$roles     = get_terms(
				'process_role',
				array(
					'hide_empty' => false,
					'parent'     => 0,
				)
			);
			$new_roles = array();
			foreach ( $roles as $role ) {
				$new_roles[] = (object) [
					'text'  => $role->name,
					'value' => $role->term_id,
				];
			}
			echo json_encode( $new_roles );
			?>
		,
		cookbook_step: 1,
		dialog_new_site: {
			provider: "kinsta",
			show: false,
			site: "",
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
		shared_with: [],
		header_timeline: [
			{"text":"Date","value":"date","sortable":false,"width":"220"},
			{"text":"Done by","value":"done-by","sortable":false,"width":"135"},
			{"text":"Name","value":"name","sortable":false,"width":"165"},
			{"text":"Notes","value":"notes","sortable":false},
			{"text":"","value":"","sortable":false},
		],
		<?php } else { ?>
		role: "",
		dialog_new_site: false,
		customers: [],
		shared_with: [],
		header_timeline: [
			{"text":"Date","value":"date","sortable":false,"width":"220"},
			{"text":"Done by","value":"done-by","sortable":false,"width":"135"},
			{"text":"Name","value":"name","sortable":false,"width":"165"},
			{"text":"Notes","value":"notes","sortable":false},
		],<?php } ?>
		domains: [],
		dns_introduction: <?php $Parsedown = new Parsedown(); echo json_encode( $Parsedown->text( get_field( "dns_introduction", "option" ) ) ); ?>,
		dns_nameservers: <?php echo json_encode( $Parsedown->text( get_field( "dns_nameservers", "option" ) ) ); ?>,
		roles: [{ name: "Subscriber", value: "subscriber" },{ name: "Contributor", value: "contributor" },{ name: "Author", value: "author" },{ name: "Editor", value: "editor" },{ name: "Administrator", value: "administrator" }],
		new_plugin: { show: false, sites: [], site_name: "", environment_selected: "", loading: false, tabs: null, page: 1, search: "", api: {} },
		new_theme: { show: false, sites: [], site_name: "", environment_selected: "", loading: false, tabs: null, page: 1, search: "", api: {} },
		bulk_edit: { show: false, site_id: null, type: null, items: [] },
		upload: [],
		view_jobs: false,
		view_timeline: false,
		search: null,
		advanced_filter: false,
		items_per_page: 50,
		business_name: "<?php echo $business_name; ?>",
		business_link: "<?php echo $business_link; ?>",
		site_selected: null,
		site_filters: [],
		site_filter_version: null,
		site_filter_status: null,
		sort_direction: "asc",
		toggle_site_sort: null,
		toggle_site_counter: { key: "", count: 0 },
		sites: [],
		header_themes: [
			{ text: 'Name', value: 'title' },
			{ text: 'Slug', value: 'name' },
			{ text: 'Version', value: 'version' },
			{ text: 'Status', value: 'status', width: "100px" },
			{ text: 'Actions', value: 'actions', width: "90px", sortable: false }
		],
		header_plugins: [
			{ text: 'Name', value: 'title' },
			{ text: 'Slug', value: 'name' },
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
			{ text: 'Login', value: 'user_login' },
			{ text: 'Display Name', value: 'display_name' },
			{ text: 'Email', value: 'user_email' },
			{ text: 'Role(s)', value: 'roles' },
			{ text: 'Actions', value: 'actions', sortable: false }
		],
		applied_site_filter: [],
		applied_site_filter_logic: [],
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
	watch: {
		applied_site_filter (val) {
			setTimeout( () => this.$refs.applied_site_filter.isMenuActive = false, 50)
		},
		selected_default_recipes (val) {
			setTimeout( () => this.$refs.default_recipes.isMenuActive = false, 50)
		},
		route() {
			this.triggerRoute()
		}
    },
	filters: {
		formatTime: function ( value ) {
			var sec_num = parseInt(value, 10); // don't forget the second param
			var hours   = Math.floor(sec_num / 3600);
			var minutes = Math.floor((sec_num - (hours * 3600)) / 60);
			var seconds = sec_num - (hours * 3600) - (minutes * 60);

			if (hours   < 10) {hours   = "0"+hours;}
			if (minutes < 10) {minutes = "0"+minutes;}
			if (seconds < 10) {seconds = "0"+seconds;}
			return minutes + ':' + seconds;
		},
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
		formatk: function (num) {
			if (num < 9999 ) {
				return numeral(num).format('0,0');
			}
			if (num < 99999 ) {
				return numeral(num).format('0.0a');
			}
			if (num < 999999 ) {
				return numeral(num).format('0a');
			}
			return numeral(num).format('0.00a');
		},
		formatPercentage: function (percentage) {
			return Math.max(percentage, 0.1).toFixed(0);
		},
		formatPercentageFixed: function (percentage) {
			return (Math.max(percentage, 0.1) * 100 ).toFixed(2) + '%';
		},
		pretty_timestamp: function (date) {
			// takes in '2018-06-18 19:44:47' then returns "Monday, Jun 18, 2018, 7:44 PM"
			formatted_date = new Date(date).toLocaleTimeString("en-us", pretty_timestamp_options);
			return formatted_date;
		}
	},
	mounted() {
		window.onhashchange = () => {
			this.route = window.location.hash.substring(1)
		}
		axios.get(
				'/wp-json/captaincore/v1/customers', {
					headers: {'X-WP-Nonce':wpApiSettings.nonce}
				})
				.then(response => {
					this.customers = response.data;
				});
		this.triggerRoute()
	},
	computed: {
		paginatedSites() {
			const start = this.page * this.items_per_page - this.items_per_page;
			const end = start + this.items_per_page;
			return this.sites.filter( site => site.filtered ).slice(start, end);
		},
		selected_default_recipes() {
			if ( typeof this.dialog_configure_defaults.record.default_recipes == 'undefined' ) {
				return "";
			} else {
				return this.dialog_configure_defaults.record.default_recipes;
			}
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
		dnsRecords() {
			count = 0;
			this.dialog_domain.records.forEach( r => {
				if ( r.update.record_status == 'new-record' ) {
					return
				}
				if ( typeof r.value === 'string' ) {
					count = count + 1;
				}
				if ( typeof r.value === 'object' ) {
					count = count + r.value.length
				}
			})
			return count;
		},
		allDomains() {
			return Object.keys( this.domains ).length;
		}
	},
	methods: {
		triggerRoute() {
			if ( this.route == "dns" ) {
				if ( this.allDomains == 0 ) {
					this.loading_page = true;
				}
				this.fetchDomains()
			}
			if ( this.route == "cookbook" ) {
				this.loading_page = false;
			}
			if ( this.route == "handbook" ) {
				this.loading_page = false;
			}
			if ( this.route == "sites" ) {
				if ( this.filteredSites == 0 ) {
					this.loading_page = true;
				}
				this.fetchSites()
			}
			if ( this.route == "" ) {
				if ( this.filteredSites == 0 ) {
					this.loading_page = true;
				}
				this.route = "sites";
			}
		},
		signOut() {
        	axios.post( '/wp-json/captaincore/v1/login/', {
                command: "signOut" 
            })
            .then( response => {
                window.location = "/";
			})
		},
		copyText( value ) {
			var clipboard = document.getElementById("clipboard");
			var x = document.createElement("input");
			x.setAttribute("type", "text");
			x.setAttribute("value", value );
			clipboard.innerHTML = x.outerHTML;
			clipboard.children[0].focus()
			clipboard.children[0].select()
			document.execCommand("copy");
		},
		triggerEnvironmentUpdate( site_id ){
			site = this.sites.filter(site => site.id == site_id)[0];

			// Trigger fetchStats()
			if ( site.tabs == "tab-Site-Management" && site.tabs_management == "tab-Stats" ) {
				this.fetchStats( site_id );
			}
		},
		removeFilter (item) {
			const index = this.applied_site_filter.indexOf(item.name)
			if (index >= 0) { 
				this.applied_site_filter.splice(index, 1);
				this.filterSites();
			}
		},
		compare(key, order='asc') {
			return function(a, b) {
				//if(!a.hasOwnProperty(key) || !b.hasOwnProperty(key)) {
				//	// property doesn't exist on either object
				//	return 0;
				//}
				if ( key == 'name' ) {
					varA = a.name || "";
					varB = b.name || "";
				}
				if ( key == 'multisite' ) {
					varA = parseInt(a.subsite_count) || 0;
					varB = parseInt(b.subsite_count) || 0;
				}
				if ( key == 'visits' ) {
					varA = parseInt(a[key].replace(/\,/g,'')) || 0;
					varB = parseInt(b[key].replace(/\,/g,'')) || 0;
				}
				if ( key == 'storage' ) {
					varA = parseInt(a.storage_raw) || 0;
					varB = parseInt(b.storage_raw) || 0;
				}
				if ( key == 'provider' ) {
					varA = a.provider || "";
					varB = b.provider || "";
				}
				let comparison = 0;
				if (varA > varB) {
					comparison = 1;
				} else if (varA < varB) {
					comparison = -1;
				}
				return (
					(order == 'desc') ? (comparison * -1) : comparison
				);
			};
		},
		configureDefaults() {

			this.dialog_configure_defaults.show = true
			if ( this.dialog_configure_defaults.account == "" ) {
				this.dialog_configure_defaults.loading = true;
			}
			// Prep AJAX request
			var data = {
				'action': 'captaincore_local',
				'command': "fetchDefaults",
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_configure_defaults.records = response.data;
					if ( this.dialog_configure_defaults.account == "" ) {
						this.dialog_configure_defaults.account = JSON.parse(JSON.stringify(this.dialog_configure_defaults.records[0].account.id));
						this.switchConfigureDefaultAccount();
					}
					this.dialog_configure_defaults.loading = false;
				})
				.catch(error => {
					console.log(error.response)
			});
		},
		saveDefaults() {
			this.dialog_configure_defaults.loading = true;
			// Prep AJAX request
			var data = {
				'action': 'captaincore_local',
				'command': "saveDefaults",
				'value': this.dialog_configure_defaults.record
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					console.log( response.data )
					this.dialog_configure_defaults.show = false;
					this.dialog_configure_defaults.loading = false;
				})
				.catch(error => {
					console.log(error.response)
			});
		},
		sortSites( key ) {
			if ( this.toggle_site_counter.key == key ) {
				this.toggle_site_counter.count++;
				this.sort_direction = "asc";
			} else {
				this.toggle_site_counter.key = key;
				this.toggle_site_counter.count = 1;
				this.sort_direction = "desc";
			}
			// Reset sort to default on 3rd click
			if ( this.toggle_site_counter.count == 3 ) {
				this.sites = this.sites.sort( this.compare( "name", this.sort_direction ) );
				this.toggle_site_counter = { key: "", count: 0 };
				this.toggle_site_sort = null;
				this.sort_direction = "desc";
				return
			}
			// Order these
			this.sites = this.sites.sort( this.compare( key, this.sort_direction ) );
		},
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
					if ( response.data.includes("http") ) {
					window.open( response.data );
					self.jobs.filter(job => job.job_id == job_id)[0].status = "done";
					} else {
						self.jobs.filter(job => job.job_id == job_id)[0].status = "error";
						self.snackbar.message = description + " failed.";
						self.snackbar.show = true;
					}
					
				})
				.catch(error => {
					self.jobs.filter(job => job.job_id == job_id)[0].status = "error";
					self.snackbar.message = description + " failed.";
					self.snackbar.show = true;
					console.log(error.response)
			});
		},
		inputFile (newFile, oldFile) {

			if (newFile && oldFile) {
				// Uploaded successfully
				if (newFile.success && !oldFile.success) {
					new_response = JSON.parse( newFile.response );
					if ( new_response.response == "Error" ) {

						if ( this.new_theme.show ) {
							this.new_theme.show = false;
							this.snackbar.message = "Installing theme failed.";
							this.snackbar.show = true;
							description = "Installing theme '" + newFile.name + "' to " + this.new_theme.site_name;

							// Adds new job
							job_id = Math.round((new Date()).getTime());
							this.jobs.push({"job_id": job_id,"description": description, "status": "error", stream: []});
						}

						if ( this.new_plugin.show ) {
							this.new_plugin.show = false;
							this.snackbar.message = "Installing plugin failed.";
							this.snackbar.show = true;
							description = "Installing plugin '" + newFile.name + "' to " + this.new_plugin.site_name;
							
							// Adds new job
							job_id = Math.round((new Date()).getTime());
							this.jobs.push({"job_id": job_id,"description": description, "status": "error", stream: []});
						}

					}
					if ( new_response.response == "Success" && new_response.url ) {

						if ( this.new_plugin.show ) {
							this.new_plugin.show = false;

							this.upload = [];

							// run wp cli with new plugin url and site
							site_ids = this.new_plugin.sites.map( s => s.id );

							// Adds new job
							job_id = Math.round((new Date()).getTime());
							description = "Installing plugin '" + newFile.name + "' to " + this.new_plugin.site_name;
							this.jobs.push({"job_id": job_id, "site_id": site_ids, "description": description, "status": "queued", stream: [], "command": "manage"});

							// Builds WP-CLI
							wp_cli = "wp plugin install '" + new_response.url + "' --force --activate"

							// Prep AJAX request
							var data = {
								'action': 'captaincore_install',
								'post_id': site_ids,
								'command': "manage",
								'value': "ssh",
								'background': true,
								'environment': this.new_plugin.environment_selected,
								'arguments': { "name":"Commands","value":"command","command":"ssh","input": wp_cli }
							};

							// Housecleaning
							this.new_plugin.sites = [];
							this.new_plugin.site_name = "";
							this.new_plugin.environment_selected = "";
						}
						if ( this.new_theme.show ) {
							this.new_theme.show = false;
							this.upload = [];

							// run wp cli with new plugin url and site
							site_ids = this.new_theme.sites.map( s => s.id );

							// Adds new job
							job_id = Math.round((new Date()).getTime());
							description = "Installing theme '" + newFile.name + "' to " + this.new_theme.site_name;
							this.jobs.push({"job_id": job_id, "site_id": site_ids, "description": description, "status": "queued", stream: [], "command": "manage"});

							// Builds WP-CLI
							wp_cli = "wp theme install '" + new_response.url + "' --force"

							// Prep AJAX request
							var data = {
								'action': 'captaincore_install',
								'post_id': site_ids,
								'command': "manage",
								'value': "ssh",
								'background': true,
								'environment': this.new_theme.environment_selected,
								'arguments': { "name":"Commands","value":"command","command":"ssh","input": wp_cli }
							};

							// Housecleaning
							this.new_theme.sites = [];
							this.new_theme.site_name = "";
							this.new_theme.environment_selected = "";
						}

						self = this;
						axios.post( ajaxurl, Qs.stringify( data ) )
							.then( response => {
								self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
								self.runCommand( response.data );
							})
							.catch(error => {
								console.log( error.response )
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
			// Copy production protocol to staging field
			this.dialog_new_site.environments[1].protocol = this.dialog_new_site.environments[0].protocol;
			// Copy production home directory to staging field
			this.dialog_new_site.environments[1].home_directory = this.dialog_new_site.environments[0].home_directory;
			// Copy production database info to staging fields
			this.dialog_new_site.environments[1].database_username = this.dialog_new_site.environments[0].database_username;
			this.dialog_new_site.environments[1].database_password = this.dialog_new_site.environments[0].database_password;
		},
		edit_site_preload_staging() {
			// Copy production address to staging field
			this.dialog_edit_site.site.environments[1].address = this.dialog_edit_site.site.environments[0].address;

			if ( this.dialog_edit_site.site.provider == "kinsta" ) {
				// Copy production username to staging field
				this.dialog_edit_site.site.environments[1].username = this.dialog_edit_site.site.environments[0].username;
				// Copy production password to staging field (If Kinsta address)
				this.dialog_edit_site.site.environments[1].password = this.dialog_edit_site.site.environments[0].password;
			} else {
				// Copy production username to staging field with staging suffix
				this.dialog_edit_site.site.environments[1].username = this.dialog_edit_site.site.environments[0].username + "-staging";
			}

			// Copy production port to staging field
			this.dialog_edit_site.site.environments[1].port = this.dialog_edit_site.site.environments[0].port;
			// Copy production protocol to staging field
			this.dialog_edit_site.site.environments[1].protocol = this.dialog_edit_site.site.environments[0].protocol;
			// Copy production home directory to staging field
			this.dialog_edit_site.site.environments[1].home_directory = this.dialog_edit_site.site.environments[0].home_directory;
			// Copy production database info to staging fields
			this.dialog_edit_site.site.environments[1].database_username = this.dialog_edit_site.site.environments[0].database_username;
			this.dialog_edit_site.site.environments[1].database_password = this.dialog_edit_site.site.environments[0].database_password;
		},
		submitNewSite() {

			var data = {
				'action': 'captaincore_ajax',
				'command': "newSite",
				'value': this.dialog_new_site
			};

			self = this;
			site_name = this.dialog_new_site.domain;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Read JSON response
					var response = response.data;

					// If error then response
					if ( response.errors.length > 0 ) {
						self.dialog_new_site.errors = response.errors;
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
						self.jobs.push({"job_id": job_id,"description": description, "status": "running", stream: []});

						// Run prep immediately after site added.
						var data = {
							'action': 'captaincore_install',
							'command': "update",
							'post_id': response.site_id
						};
						axios.post( ajaxurl, Qs.stringify( data ) )
							.then( r => {
								self.jobs.filter(job => job.job_id == job_id)[0].job_id = r.data;
								self.runCommand( r.data );
							});
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

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {

					var response = response.data

					// If error then response
					if ( response.response.includes("Error:") ) {

						self.dialog_edit_site.errors = [ response.response ];
						console.log(response.response);
						return;
					}

					if ( response.response = "Successfully updated site" ) {
						self.dialog_edit_site.show = false;
						
						self.fetchSiteInfo( response.site_id );

						// Start job
						description = "Updating " + site_name;
						job_id = Math.round((new Date()).getTime());
						self.jobs.push({"job_id": job_id,"description": description, "status": "running", stream: []});

						// Run prep immediately after site added.
						var data = {
							'action': 'captaincore_install',
							'command': "update",
							'post_id': response.site_id
						};
						axios.post( ajaxurl, Qs.stringify( data ) )
							.then( r => {
								self.jobs.filter(job => job.job_id == job_id)[0].job_id = r.data;
								self.runCommand( r.data );
								self.dialog_edit_site = { show: false, loading: false, site: {} };
							});
					}
				});
		},
		syncSite( site_id, environment ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			if ( Array.isArray( site_id ) ) { 
				environment = this.dialog_bulk.environment_selected;
				site_name = site_id.length + " sites";
			} else {
				environment = site.environment_selected
				site_name = site.name;
			}

			var data = {
				action: 'captaincore_install',
				post_id: site_id,
				command: 'sync-data',
				environment: environment
			};

			self = this;
			description = "Syncing " + site_name + " info";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({ "job_id": job_id, "description": description, "status": "queued", stream: [], "command": "syncSite", "site_id": site_id });

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with responsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
				})
				.catch( error => console.log( error ) );

		},
		bulkSyncSites() {

			should_proceed = confirm("Sync " + this.selectedSites + " sites for " + this.dialog_bulk.environment_selected.toLowerCase() + " environments info?");

			if ( ! should_proceed ) {
				return;
			}

			site_ids = this.sites_selected.map( site => site.id );
			site_names = this.sites_selected.length + " sites";

			var data = {
				action: 'captaincore_install',
				post_id: site_ids,
				command: 'sync-data',
				environment: this.dialog_bulk.environment_selected
			};

			self = this;
			description = "Syncing " + site_names + " site info";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({ "job_id": job_id, "description": description, "status": "queued", stream: [], "command": "syncSite" });

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
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

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					response.data.forEach( site => {
						lookup = self.sites.filter(s => s.id == site.id).length;
						if (lookup == 1 ) {
							// Update existing site info
							site_update = self.sites.filter(s => s.id == site.id)[0];
							// Look through keys and update
							Object.keys(site).forEach(function(key) {
								// Skip updating environment_selected and tabs_management
								if ( key == "environment_selected" || key == "tabs" || key == "tabs_management" ) {
									return;
								}
							site_update[key] = site[key];
							});
						}
						if (lookup != 1 ) { 
							// Add new site info
							self.sites.push(site);
						}
					});
				});
		},
		fetchMissing() {
			if ( this.allDomains == 0 && this.modules.dns ) {
				this.fetchDomains()
			}
			if ( this.filteredSites == 0 ) {
				this.fetchSites()
			}
		},
		fetchDomains() {
			axios.get(
				'/wp-json/captaincore/v1/domains', {
					headers: {'X-WP-Nonce':wpApiSettings.nonce}
				})
				.then(response => {
					this.domains = response.data;
					this.loading_page = false;
					setTimeout(this.fetchMissing, 3000)
				});
		},
		fetchSites() {
			axios.get(
				'/wp-json/captaincore/v1/sites', {
					headers: {'X-WP-Nonce':wpApiSettings.nonce}
				})
				.then(response => {

					// Populate existing sites
					if ( this.sites.length > 0 ) {
						preserve_keys = ['environment_selected','filtered','selected','tabs','tabs_management']
						response.data.forEach( r => {
							site_check = this.sites.filter( s => s.id == r.id);
							// Update site
							if ( site_check.length == 1 ) {
								site = site_check[0];
								Object.keys( site_check[0] ).forEach( k => { 
									if ( ! preserve_keys.includes( k ) ) { 
										site[k] = r[k];
									}
								})
							}
							// Add site
							if ( site_check.length == 0 ) {
								this.sites.push( r )
							}
						})
					}
					
					// Populate sites
					if ( this.sites.length == 0 ) {
						this.sites = response.data;
					}

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
					this.loading_page = false;
					setTimeout(this.fetchMissing, 1000)
			});
		},
		fetchStats( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			environment = site.environments.filter( e => e.environment == site.environment_selected )[0];
			environment.stats = "Loading";

			var data = {
				action: 'captaincore_ajax',
				post_id: site_id,
				command: 'fetchStats',
				environment: site.environment_selected
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {

					if ( response.data.Error ) {
						environment.stats = response.data.Error 
						return;
					}

					chart_id = "chart_" + site.id + "_" + site.environment_selected;
					chart_dom = document.getElementById( chart_id );		
					chart_dom.innerHTML = ""

					environment.stats = response.data
					
					bymonth={};
					environment.stats.stats.map( groupmonth );

					k = Object.keys( bymonth );
					names = Object.keys( bymonth ).map( k => bymonth[k].Name );
					pageviews = Object.keys( bymonth ).map( k => bymonth[k].Pageviews );
					visitors = Object.keys( bymonth ).map( k => bymonth[k].Visitors );
					
					// Generate chart
					environment.chart = new frappe.Chart( "#" + chart_id, {
						data: {
							labels: names,
							datasets: [
								{
									name: "Pageviews",
									values: pageviews,
								},
								{
									name: "Visitors",
									values: visitors,
								},
							],
						},
						type: "bar",
						height: 270,
						colors: ["light-blue", "#1564c0"],
						axisOptions: {
							xAxisMode: "tick",
							xIsSeries: 1
						},
						barOptions: {
							spaceRatio: 0.1,
							stacked: 1
						},
						showLegend: 0,
						
						});
					
				})
				.catch( error => console.log( error ) );

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

				axios.post( ajaxurl, Qs.stringify( data ) )
					.then( response => {

						response = response.data

						// Loop through environments and assign users
						Object.keys(response).forEach( key => {
							site.environments.filter( e => e.environment == key )[0].users = response[key];
							if ( response[key] == null ) {
								site.environments.filter( e => e.environment == key )[0].users = [];
							}
						});
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

				axios.post( ajaxurl, Qs.stringify( data ) )
					.then( response => {
						response = response.data
						// Loop through environments and assign users
						Object.keys(response).forEach( key => {
							site.environments.filter( e => e.environment == key )[0].update_logs = response[key];
							if ( response[key] == null ) {
								site.environments.filter( e => e.environment == key )[0].update_logs = [];
							}
						});
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
		switchTimelineAccount() {
			account_id = this.dialog_timeline.account
			this.dialog_timeline.logs = this.timeline_logs.filter( a => a.account.id == account_id )[0].logs
		},
		switchConfigureDefaultAccount() {
			account_id = this.dialog_configure_defaults.account
			this.dialog_configure_defaults.record = this.dialog_configure_defaults.records.filter( a => a.account.id == account_id )[0]
		},
		bulkEdit ( site_id, type ) {
			this.bulk_edit.show = true;
			site = this.sites.filter(site => site.id == site_id)[0];
			this.bulk_edit.site_id = site_id;
			this.bulk_edit.site_name = site.name;
			this.bulk_edit.items = site.environments.filter( e => e.environment == site.environment_selected )[0][ type.toLowerCase() + "_selected" ];
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
			this.jobs.push({"job_id": job_id, "site_id": site_id, "description": description, "status": "queued", stream: [], "command": "manage"});

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
				'environment': site.environment_selected,
				'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
				});

		},
		fetchLink( site_id, snapshot_id ) {
			site = this.sites.filter(site => site.id == site_id )[0];
			snapshot = site.environments.filter( e => e.environment == site.environment_selected )[0].snapshots.filter( s => s.snapshot_id == snapshot_id )[0];

			var data = {
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': 'fetchLink',
				'environment': site.environment_selected,
				'value': snapshot_id
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					snapshot.token = response.data.token;
					snapshot.expires_at = response.data.expires_at;
				})
				.catch( error => console.log( error ) );
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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			var data = {
				'action': 'captaincore_install',
				'post_id': post_id,
				'command': 'snapshot',
				'environment': environment,
				'value': this.dialog_backup_snapshot.email,
				'notes': "User requested full snapshot"
			};

			if ( this.dialog_backup_snapshot.filter_toggle === false ) {
				data.filters = this.dialog_backup_snapshot.filter_options
				description = this.dialog_backup_snapshot.filter_options.join(", ").replace(/,([^,]*)$/,' and$1');
				data.notes = "User requested snapshot containing " + description;
			}
			
			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

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
					self.runCommand( response.data )
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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
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

			site_id = this.dialog_apply_https_urls.site_id
			site_name = this.dialog_apply_https_urls.site_name

			if ( Array.isArray( site_id ) ) { 
				environment = this.dialog_bulk.environment_selected;
			} else {
				environment = site.environment_selected
			}

			should_proceed = confirm("Will apply ssl urls to '"+site_name+"'. Proceed?");

			if ( ! should_proceed ) {
				return;
			}

			// Start job
			description = "Applying HTTPS urls to " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			var data = {
				'action': 'captaincore_install',
				'environment': environment,
				'post_id': site_id,
				'command': command,
			};

			self = this;

			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
					self.dialog_apply_https_urls.site_id = "";
					self.dialog_apply_https_urls.site_name = "";
					self.dialog_apply_https_urls.show = false;
					self.snackbar.message = "Applying HTTPS Urls";
					self.snackbar.show = true;
				});
		},
		fetchTimelineLogs() {
			this.view_timeline = !this.view_timeline;
			this.dialog_timeline.loading = true;

			var data = {
				action: 'captaincore_local',
				command: 'fetchTimelineLogs',
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.timeline_logs = response.data;
					this.dialog_timeline.account = JSON.parse(JSON.stringify(this.timeline_logs[0].account.id));
					this.switchTimelineAccount();
					this.dialog_timeline.loading = false;
				})
				.catch( error => console.log( error ) );
		},
		fetchProcessLogs() {
			this.dialog_log_history.show = true;

			var data = {
				action: 'captaincore_ajax',
				command: 'fetchProcessLogs',
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.dialog_log_history.logs = response.data;
				})
				.catch( error => console.log( error ) );
		},
		showLogEntry( site_id ){
			site = this.sites.filter(site => site.id == site_id )[0];
			this.dialog_new_log_entry.show = true;
			this.dialog_new_log_entry.sites = [];
			this.dialog_new_log_entry.sites.push( site );
			this.dialog_new_log_entry.site_name = site.name;
		},
		showLogEntryBulk() {
			this.dialog_new_log_entry.show = true;
			this.dialog_new_log_entry.sites = this.sites_selected;
			this.dialog_new_log_entry.site_name = this.sites_selected.length + " sites";
		},
		showLogEntryGeneric() {
			this.dialog_new_log_entry.show = true;
			this.dialog_new_log_entry.sites = [];
		},
		newLogEntry() {
			site_ids = this.dialog_new_log_entry.sites.map( s => s.id);

			var data = {
				action: 'captaincore_ajax',
				post_id: site_ids,
				process_id: this.dialog_new_log_entry.process,
				command: 'newLogEntry',
				value: this.dialog_new_log_entry.description
			};

			this.dialog_new_log_entry.show = false;
			this.dialog_new_log_entry.sites = [];

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					Object.keys(response.data).forEach( site_id => {
						self.sites.filter( site => site.id == site_id )[0].timeline = response.data[site_id]
					});
					self.dialog_new_log_entry.sites = []
					this.dialog_new_log_entry.site_name = "";
					self.dialog_new_log_entry.description = "";
					self.dialog_new_log_entry.process = "";
				})
				.catch( error => console.log( error ) );
		},
		updateLogEntry() {
			site_id = this.dialog_edit_log_entry.log.websites.map( s => s.id );

			var data = {
				action: 'captaincore_ajax',
				command: 'updateLogEntry',
				post_id: site_id,
				log: this.dialog_edit_log_entry.log,
			};

			this.dialog_edit_log_entry.show = false;
			this.dialog_edit_log_entry.sites = [];

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					Object.keys(response.data).forEach( site_id => {
						self.sites.filter( site => site.id == site_id )[0].timeline = response.data[site_id];
					});
					self.dialog_edit_log_entry.log = {};
				})
				.catch( error => console.log( error ) );
		},
		editLogEntry( site_id, log_id ) {

			// If not assigned that's fine but at least assign as string.
			if ( site_id == "" ) {
				site_id = "Not found";
			}

			if ( typeof site_id == "object" ) {
				site_id = site_id[0].id;
			}
			
			site = this.sites.filter(site => site.id == site_id )[0];

			var data = {
				action: 'captaincore_ajax',
				post_id: site_id,
				command: 'fetchProcessLog',
				value: log_id,
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.dialog_edit_log_entry.log = response.data;
					self.dialog_edit_log_entry.show = true;
					if ( typeof site !== "undefined" ) {
					self.dialog_edit_log_entry.site = site;
					} else {
						self.dialog_edit_log_entry.site = {};
					}
				})
				.catch( error => console.log( error ) );

		},
		viewProcess( process_id ) {

			process = this.processes.filter( process => process.id == process_id )[0];
			this.dialog_handbook.process = process;
			this.dialog_handbook.process.description = "Loading...";
			this.dialog_handbook.show = true;

			var data = {
				action: 'captaincore_ajax',
				post_id: process_id,
				command: 'fetchProcess',
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.dialog_handbook.process = response.data;
				})
				.catch( error => console.log( error ) );

		},
		editProcess( process_id ) {
			process = this.processes.filter( process => process.id == process_id )[0];

			var data = {
				action: 'captaincore_ajax',
				post_id: process_id,
				command: 'fetchProcess',
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.dialog_edit_process.process = response.data;
					self.dialog_edit_process.show = true;
				})
				.catch( error => console.log( error ) );
		},
		updateProcess() {
			var data = {
				action: 'captaincore_ajax',
				command: 'updateProcess',
				value: this.dialog_edit_process.process
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Remove existing item
					self.processes = self.processes.filter( process => process.id != response.data.id );
					// Add new item
					self.processes.push( response.data )
					// Sort processes
					self.processes.sort((a,b) => (a.title > b.title) ? 1 : ((b.title > a.title) ? -1 : 0));
					self.dialog_edit_process.process = { show: false, title: "", time_estimate: "", repeat: "as-needed", repeat_quantity: "", role: "", description: "" };
					self.dialog_edit_process.show = false;
					self.viewProcess( response.data.id );
				})
				.catch( error => console.log( error ) );
		},
		addNewProcess() {

			var data = {
				action: 'captaincore_ajax',
				command: 'newProcess',
				value: this.new_process
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.processes.unshift( response.data );
					self.new_process = { show: false, title: "", time_estimate: "", repeat: "as-needed", repeat_quantity: "", role: "", description: "" };
				})
				.catch( error => console.log( error ) );

		},
		editRecipe( recipe_id ) {
			recipe = this.recipes.filter( recipe => recipe.recipe_id == recipe_id )[0];
			this.dialog_cookbook.recipe = recipe;
			this.dialog_cookbook.show = true;
		},
		loadRecipe( recipe_id ) {
			recipe = this.recipes.filter( recipe => recipe.recipe_id == recipe_id )[0];
			this.snackbar.message = "Recipe '"+ recipe.title +"' loaded.";
			this.snackbar.show = true;
			this.custom_script = recipe.content;
		},
		runRecipe( recipe_id, site_id ) {
			recipe = this.recipes.filter( recipe => recipe.recipe_id == recipe_id )[0];
			site = this.sites.filter(site => site.id == site_id )[0];

			should_proceed = confirm("Run recipe '"+ recipe.title +"' on " + site.name + "?");

			if ( ! should_proceed ) {
				return;
			}

			var data = {
				action: 'captaincore_install',
				post_id: site.id,
				command: 'recipe',
				environment: site.environment_selected,
				value: recipe_id
			};

			description = "Run recipe '"+ recipe.title +"' on '" + site.name + "'";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", "command": "recipe", stream: []});

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
				})
				.catch( error => console.log( error ) );
		},
		runRecipeBulk( recipe_id ){

			sites = this.sites_selected;
			site_ids = sites.map( s => s.id );
			recipe = this.recipes.filter( recipe => recipe.recipe_id == recipe_id )[0];

			should_proceed = confirm("Run recipe '"+ recipe.title +"' on " +  sites.length + " sites?");

			if ( ! should_proceed ) {
				return;
			}

			var data = {
				action: 'captaincore_install',
				post_id: site_ids,
				command: 'recipe',
				environment: this.dialog_bulk.environment_selected,
				value: recipe_id
			};

			description = "Run recipe '"+ recipe.title +"' on '" + sites.length + "'";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", "command": "recipe", stream: []});

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
				})
				.catch( error => console.log( error ) );

		},
		updateRecipe() {
			var data = {
				action: 'captaincore_ajax',
				command: 'updateRecipe',
				value: this.dialog_cookbook.recipe
			};
			self = this;
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.dialog_cookbook.show = false;
					self.recipes = response.data;
				})
				.catch( error => console.log( error ) );
		},
		addRecipe() {
			var data = {
				action: 'captaincore_ajax',
				command: 'newRecipe',
				value: this.new_recipe
			};
			self = this;
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.new_recipe = { show: false, title: "", content: "" };
					self.recipes = response.data;
					self.new_recipe = { title: "", content: "" };
				})
				.catch( error => console.log( error ) );
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
		launchSiteDialog( site_id ) {
			site = this.sites.filter( site => site.id == site_id )[0];
			this.dialog_launch.site = site
			this.dialog_launch.show = true
		},
		launchSite() {

			if ( this.dialog_launch.domain == "" ) {
				this.snackbar.message = "Domain is required. Launch cancelled.";
				this.snackbar.show = true;
				return
			}

			site = this.dialog_launch.site

			var data = {
				action: 'captaincore_install',
				post_id: site.id,
				command: 'launch',
				value: this.dialog_launch.domain
			};

			description = "Lauching site '" + site.name + "'";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", "command": "manage", stream: []});

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.dialog_launch.site = {};
					self.dialog_launch.domain = "";
					self.dialog_launch.show = false;
					self.runCommand( response.data )
				})
				.catch( error => console.log( error ) );

		},
		toggleSite( site_id ) {
			site = this.sites.filter( site => site.id == site_id )[0];
			this.dialog_toggle.show = true;
			this.dialog_toggle.site_id = site.id;
			this.dialog_toggle.site_name = site.name;
			this.dialog_toggle.business_name = this.business_name;
			this.dialog_toggle.business_link = this.business_link;
		},
		toggleSiteBulk() {
			sites = this.sites_selected
			site_ids = this.sites_selected.map( s => s.id )
			site_name = sites.length + " sites";
			this.dialog_toggle.show = true;
			this.dialog_toggle.site_id = site_ids;
			this.dialog_toggle.site_name = site_name;
			this.dialog_toggle.business_name = this.business_name;
			this.dialog_toggle.business_link = this.business_link;
		},
		showSiteMigration( site_id ){
			site = this.sites.filter(site => site.id == site_id)[0];
			this.dialog_migration.sites.push( site );
			this.dialog_migration.show = true;
			this.dialog_migration.site_id = site.id
			this.dialog_migration.site_name = site.name;
		},
		validateSiteMigration() {
			if ( this.$refs.formSiteMigration.validate() ) {
				this.siteMigration( this.dialog_migration.site_id );
			}	
		},
		siteMigration( site_id ) {
			site = this.sites.filter(site => site.id == site_id)[0];
			site_name = site.name;

			should_proceed = confirm("Migrate from backup url? This will overwrite the existing site at " + site_name + ".");
			description = "Migrating backup to '" + site_name + "'";

			if ( ! should_proceed ) {
				return;
			}

			var data = {
				action: 'captaincore_install',
				post_id: site_id,
				command: 'migrate',
				value: this.dialog_migration.backup_url,
				update_urls: this.dialog_migration.update_urls,
				environment: site.environment_selected
			};

			self = this;
			description = "Migrating backup to '" + site_name + "'";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", "command": "migrate", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
			.then( response => {
				self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
				self.runCommand( response.data )
				self.snackbar.message = "Migration backup to " + site_name;
				self.snackbar.show = true;
				self.dialog_migration.show = false;
				self.dialog_migration.sites = [];
				self.dialog_migration.backup_url = "";
				self.dialog_migration.update_urls = "";
			})
			.catch( error => console.log( error ) );

		},
		DeactivateSite( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			site_name = this.dialog_toggle.site_name;

			if ( Array.isArray( site_id ) ) { 
				environment = this.dialog_bulk.environment_selected;
			} else {
				environment = site.environment_selected
			}

			var data = {
				action: 'captaincore_install',
				post_id: site_id,
				command: 'deactivate',
				environment: environment,
				name: this.dialog_toggle.business_name,
				link: this.dialog_toggle.business_link
			};

			self = this;
			description = "Deactivating '" + site_name + "'";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
					self.snackbar.message = "Deactivating " + site_name;
					self.snackbar.show = true;
					self.dialog_toggle.show = false;
					self.dialog_toggle.site_id = "";
					self.dialog_toggle.site_name = "";
					self.dialog_toggle.business_name = "";
					self.dialog_toggle.business_link = "";
				})
				.catch( error => console.log( error ) );

		},
		ActivateSite( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			site_name = this.dialog_toggle.site_name;

			if ( Array.isArray( site_id ) ) { 
				environment = this.dialog_bulk.environment_selected;
			} else {
				environment = site.environment_selected
			}

			var data = {
				action: 'captaincore_install',
				post_id: site_id,
				environment: environment,
				command: 'activate'
			};

			self = this;
			description = "Activating '" + site_name + "'";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
					self.snackbar.message = "Activating " + site_name;
					self.snackbar.show = true;
					self.dialog_toggle.show = false;
					self.dialog_toggle.site_id = "";
					self.dialog_toggle.site_name = "";
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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
					self.snackbar.message = description;
					self.snackbar.show = true;
				})
				.catch( error => console.log( error ) );

		},
		siteDeployBulk(){

			sites = this.sites_selected;
			site_ids = sites.map( s => s.id );
			should_proceed = confirm("Deploy users and plugins " + sites.length + " sites?");
			description = "Deploying users and plugins on '" + sites.length + " sites'";

			if ( ! should_proceed ) {
				return;
			}

			var data = {
				action: 'captaincore_install',
				environment: this.dialog_bulk.environment_selected,
				post_id: site_ids,
				command: 'deploy-defaults'
			};

			self = this;

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id ,"site_id": site_ids, "command": "manage", "description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
					self.snackbar.message = description;
					self.snackbar.show = true;
				})
				.catch( error => console.log( error ) );

		},
		runCustomCode( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			should_proceed = confirm("Deploy custom code on "+site.name+"?");

			if ( ! should_proceed ) {
				return;
			}

			var data = {
				action: 'captaincore_install',
				environment: site.environment_selected,
				post_id: site_id,
				command: 'run',
				value: this.custom_script,
				background: true
			};

			self = this;
			description = "Deploying custom code on '" + site.name  +"'";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id, "description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
					self.snackbar.message = description;
					self.snackbar.show = true;
					self.custom_script = "";
				})
				.catch( error => console.log( error ) );

		},
		runCustomCodeBulk(){

			sites = this.sites_selected;
			site_ids = sites.map( s => s.id );
			should_proceed = confirm("Deploy custom code on "+ sites.length +" sites?");

			if ( ! should_proceed ) {
				return;
			}

			wp_cli = this.custom_script;

			var data = {
				action: 'captaincore_install',
				environment: this.dialog_bulk.environment_selected,
				post_id: site_ids,
				command: 'run',
				value: this.custom_script,
				background: true
			};

			self = this;
			description = "Deploying custom code on '" + sites.length + " sites'";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id, "description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
					self.snackbar.message = description;
					self.snackbar.show = true;
					self.custom_script = "";
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
		fetchTimeline( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];

			var data = {
				action: 'captaincore_ajax',
				post_id: site.id,
				command: 'timeline'
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					site.timeline = response.data;
				})
				.catch( error => console.log( error ) );

		},
		addDefaultsUser() {
			this.dialog_configure_defaults.record.default_users.push({ email: "", first_name: "", last_name: "", role: "administrator", username: "" })
		},
		addDomain() {
			this.dialog_new_domain.loading = true;

			var data = {
				action: 'captaincore_ajax',
				command: 'addDomain',
				value: this.dialog_new_domain.domain
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_new_domain.loading = false;
					this.dialog_new_domain = { show: false, domain: { name: "", customer: "" } };
					this.snackbar.message = response.data;
					this.snackbar.show = true;
				})
				.catch( error => {
					this.snackbar.message = error;
					this.snackbar.show = true;
					this.dialog_new_domain.loading = false;
				});
		},
		addRecord() {
			timestamp = new Date().getTime();
			this.dialog_domain.records.push({ id: "new_" + timestamp, edit: false, delete: false, new: true, ttl: "1800", type: "A", value: [{"value": ""}], update: {"record_id": "new_" + timestamp, "record_type": "A", "record_name": "", "record_value": [{"value": ""}], "record_ttl": "1800", "record_status": "new-record" } });
		},
		addRecordValue( index ) {
			record = this.dialog_domain.records[index];
			if ( record.type == "A" || record.type == "AAAA" || record.type == "ANAME" || record.type == "TXT" || record.type == "SPF" ) {
				record.update.record_value.push({ value: "" });
			}
			if ( record.type == "MX" ) {
				record.update.record_value.push({ level: "", value: "" });
			}
			if ( record.type == "SRV" ) {
				record.update.record_value.push({ priority: 0, weight: 0, port: 443, value: "" });
			}
		},
		viewRecord( record_id ){
			record = this.dialog_domain.records.filter( r => r.id == record_id )[0];
			record.edit = false
			record.delete = false
		},
		editRecord( record_id ){
			record = this.dialog_domain.records.filter( r => r.id == record_id )[0];
			record.edit = true
			record.delete = false
		},
		changeRecordType( index ) {
			record = this.dialog_domain.records.filter( (r, i) => i == index )[0];
			if ( record.type == "A" || record.type == "AAAA" || record.type == "ANAME" || record.type == "TXT" || record.type == "SPF" ) {
				record.update.record_value = [{ value: "" }];
			}
			if ( record.type == "MX" ) {
				record.update.record_value = [{ level: "", value: "" }];
			}
			if ( record.type == "SRV" ) {
				record.update.record_value = [{ priority: 0, weight: 0, port: 443, value: "" }];
			}
			if ( record.type == "CNAME" || record.type == "HTTPRedirection" ) {
				record.update.record_value = "";
			}
		},
		deleteUserValue( delete_index ) {
			this.dialog_configure_defaults.record.default_users = this.dialog_configure_defaults.record.default_users.filter( (u, index) => index != delete_index );
		},
		deleteRecordValue( index, value_index ) {
			this.dialog_domain.records[index].update.record_value.splice( value_index, 1 );
		},
		deleteCurrentRecord( record_id ){
			record = this.dialog_domain.records.filter( r => r.id == record_id )[0];
			record.edit = false
			record.delete = !record.delete
		},
		deleteRecord( index ){
			this.dialog_domain.records.splice( index, 1 )
		},
		modifyDNS( domain ) {
			this.dialog_domain = { show: false, domain: {}, records: [], loading: true, saving: false };
			self = this;
			axios.get(
				'/wp-json/captaincore/v1/domain/' + domain.id, {
					headers: {'X-WP-Nonce':wpApiSettings.nonce}
				})
				.then(response => {
					if ( typeof response.data == "string" ) {
						self.snackbar.message = response.data;
						self.snackbar.show = true;
						this.dialog_domain = { show: false, domain: {}, records: [], loading: false, saving: false };
						return
					}

					if ( typeof response.data.errors == 'object' ) {
						self.snackbar.message = response.data.errors.join(" ");
						self.snackbar.show = true;
						this.dialog_domain = { show: false, domain: {}, records: [], loading: false, saving: false };
						return
					}

					// Prep records with 
					response.data.forEach( r => {
						if ( r.type == "A" || r.type == "AAAA" ) {
							new_value = [];
							r.value.forEach( v => {
								new_value.push({ "value": v });
							});
							r.value = new_value;
						}
						r.update = {
							"record_id": JSON.parse(JSON.stringify(r.id)),
							"record_type": JSON.parse(JSON.stringify(r.type)),
							"record_name": JSON.parse(JSON.stringify(r.name)),
							"record_value": JSON.parse(JSON.stringify(r.value)),
							"record_ttl": JSON.parse(JSON.stringify(r.ttl)),
							"record_status": "edit-record"
						};
						r.edit = false;
						r.delete = false;
					});
					timestamp = new Date().getTime();
					response.data.push({ id: "new_" + timestamp, edit: false, delete: false, new: true, ttl: "1800", type: "A", value: [{"value": ""}], update: {"record_id": "new_" + timestamp, "record_type": "A", "record_name": "", "record_value": [{"value": ""}], "record_ttl": "1800", "record_status": "new-record" } });
					this.dialog_domain.records = response.data;
					this.dialog_domain.loading = false;
				});
			this.dialog_domain.domain = domain;
			this.dialog_domain.show = true;
			
		},
		saveDNS() {

			this.dialog_domain.saving = true;
			domain_id = this.dialog_domain.domain.id;
			record_updates = [];

			this.dialog_domain.records.forEach( record => {
				// Format value for API
				if ( record.type != "CNAME" && record.type != "HTTPRedirection" ) {
					record_value = [];
					record.update.record_value.forEach( v => {
						if ( v.value == "" ) {
							return
						}
						
						v.value = v.value.trim();
						record_value.push( v );
						
					});
				}

				if ( record.type == "CNAME" ) {
					// Check for value ending in period. If not add one.
					record_value = record.update.record_value.trim();
					if ( record_value.substr(record_value.length - 1) != "." ) {
						record_value = record_value + ".";
					}
				}

				if ( record.type == "MX" ) {
					// Check for value ending in period. If not add one.
					record.update.record_value.forEach( v => {
						v.value = v.value.trim();
						if ( v.value.substr(v.value.length - 1) != "." ) {
							v.value = v.value + ".";
						}
					})
				}

				if ( record.type == "TXT" ) {
					// Check for value wrapped in quotes. If not add them.
					record.update.record_value.forEach( v => {
						v.value = v.value.trim();
						if ( v.value.substr(0,1) != '"' ) {
							v.value = '"' + v.value;
						}
						if ( v.value.substr(v.value.length - 1) != '"' ) {
							v.value = v.value + '"';
						}
					})
				}

				if ( record.type == "HTTPRedirection" ) {
					record_value = record.update.record_value.trim();
				}

				// Clean out empty values
				if ( record.update.record_type == "A" && record_value.length == 0 ) {
					return;
				}
				
				// Clean out empty values
				if ( record.update.record_type == "CNAME" && record.update.record_value == "" ) {
					return;
				}

				// Prepares new records
				if ( record.new ) {
					record.update.record_type = record.type;
				}
				
				// Prepares new & modified records
				if ( record.edit || record.new ) {
					record.update.record_value = record_value;
					record_updates.push( record.update );
				}

				// Prepares records to be removed
				if ( record.delete ) {
					record_updates.push({
						"record_id": record.id,
						"record_type": record.type,
						"record_name": record.name,
						"record_value": record_value,
						"record_ttl": record.ttl,
						"record_status": "remove-record"
					});
				}
			});
			
			if ( record_updates.length == 0 ) {
				this.snackbar.message = "No record changes found.";
				this.snackbar.show = true;
				this.dialog_domain.saving = false;
				return;
			}

			var data = {
				'action': 'captaincore_dns',
				'domain_key': domain_id,
				'record_updates': record_updates
			};

			self = this;
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.dialog_domain.results = response.data;
					self.reflectDNS();
					
					// If no errors found then fetch new details
					// self.modifyDNS( self.dialog_domain.domain );
				})
				.catch( error => {
					self.snackbar.message = error;
					self.snackbar.show = true;
					self.dialog_domain.saving = false;
					//self.dialog_domain.results = response.data;
				});
		},
		reflectDNS() {
			this.dialog_domain.results.forEach( result => {

				if ( result.success && result.success == "Record  updated successfully" ) {
					record = this.dialog_domain.records.filter( r => r.id == result.record_id )[0];
					record.edit = false;
					record.name = JSON.parse(JSON.stringify( record.update.record_name ));
					record.value = JSON.parse(JSON.stringify( record.update.record_value ));
					record.ttl = JSON.parse(JSON.stringify( record.update.record_ttl ));
				}

				if ( result.success && result.success == "Record  deleted successfully" ) {
					this.dialog_domain.records = this.dialog_domain.records.filter( record => result.record_id != record.id );
				}

				// Add new record
				if ( typeof result.success == 'undefined' && typeof result.errors == 'undefined' && result.id != "" ) {

					result.success = "Record added successfully";

					// Removed existing new recording matching type, name, value and ttl.
					this.dialog_domain.records = this.dialog_domain.records.filter( r => r.update.record_status != "new-record" && r.update.record_name != result.name )

					if ( result.type == "A" || result.type == "AAAA" || result.type == "SPF" ) {
						record_value = [];
						result.value.forEach( r => {
							record_value.push({ value: r });
						});
					} else {
						record_value = result.value;
					}

					result.new = false
					result.edit = false
					result.delete = false
					result.value = JSON.parse(JSON.stringify(record_value))
					result.update = {
						"record_id": JSON.parse(JSON.stringify(result.id)),
						"record_type": JSON.parse(JSON.stringify(result.type)),
						"record_name": JSON.parse(JSON.stringify(result.name)),
						"record_value": JSON.parse(JSON.stringify(record_value)),
						"record_ttl": JSON.parse(JSON.stringify(result.ttl)),
						"record_status": "edit-record"
					}

					// Add new record
					this.dialog_domain.records.push( result );

					// Sort new results
					this.dialog_domain.records.sort(function (record1, record2) {

						// Sort by types
						// If the first item has a higher number, move it down
						// If the first item has a lower number, move it up
						if (record1.type < record2.type) return -1;
						if (record1.type > record2.type) return 1;

						// If the votes number is the same between both items, sort alphabetically
						// If the first item comes first in the alphabet, move it up
						// Otherwise move it down
						if (record1.name > record2.name) return 1;
						if (record1.name < record2.name) return -1;

					});
				}

				this.dialog_domain.saving = false;

			});
		},
		modifyPlan( site_id ) {
			site = this.sites.filter(site => site.id == site_id)[0];
			this.dialog_modify_plan.site = site;
			this.dialog_modify_plan.hosting_addons = site.customer.hosting_addons;
			this.dialog_modify_plan.hosting_plan = Object.assign({}, site.customer.hosting_plan)

			// Adds commas
			if ( this.dialog_modify_plan.hosting_plan.visits_limit != null ) {
				this.dialog_modify_plan.hosting_plan.visits_limit = this.dialog_modify_plan.hosting_plan.visits_limit.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
			}

			this.dialog_modify_plan.selected_plan = site.customer.hosting_plan.name;
			this.dialog_modify_plan.customer_name = site.customer.name;
			this.dialog_modify_plan.show = true;
		},
		updatePlan() {
			site_id = this.dialog_modify_plan.site.id;
			site = this.sites.filter(site => site.id == site_id)[0];
			hosting_plan = Object.assign({}, this.dialog_modify_plan.hosting_plan)
			hosting_addons = Object.assign({}, this.dialog_modify_plan.hosting_addons)

			// Remove commas
			hosting_plan.visits_limit = hosting_plan.visits_limit.replace(/,/g, '')
			site.customer.hosting_plan = hosting_plan
			this.dialog_modify_plan.show = false;
			
			// New job for progress tracking
			job_id = Math.round((new Date()).getTime());
			description = "Updating Plan for " + site.customer.name;
			this.jobs.push({"job_id": job_id,"description": description, "status": "done"});

			// Prep AJAX request
			var data = {
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': "updatePlan",
				'value': { "hosting_plan": hosting_plan, "addons": hosting_addons },
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
					self.snackbar.message = description;
					self.snackbar.show = true;
				})
				.catch( error => console.log( error ) );
		},
		viewApplyHttpsUrls( site_id ) {
			site = this.sites.filter(site => site.id == site_id)[0];
			this.dialog_apply_https_urls.show = true;
			this.dialog_apply_https_urls.site_id = site_id
			this.dialog_apply_https_urls.site_name = site.name;
		},
		viewApplyHttpsUrlsBulk() {
			this.dialog_apply_https_urls.show = true;
			this.dialog_apply_https_urls.site_id = this.sites_selected.map( s => s.id );
			this.dialog_apply_https_urls.site_name = this.sites_selected.length + " sites";
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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

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
					self.runCommand( response.data );
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

			// Start job
			description = "Quicksave rollback all themes/plugins on " + site.name + " to " + date + ".";
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			var data = {
				'action': 'captaincore_install',
				'post_id': quicksave.site_id,
				'quicksave_id': quicksave.quicksave_id,
				'command': 'quicksave_rollback',
				'environment': site.environment_selected,
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
			  .then( response => {
					quicksave.loading = false;
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
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
		expandQuicksave( item, site_id, environment ) {
			table_name = "quicksave_table_" + site_id + "_" + environment;
			if ( typeof this.$refs[table_name][0].expansion[item.quicksave_id] == 'boolean' ) {
				this.$refs[table_name][0].expansion = ""
			} else {
				this.$refs[table_name][0].expansion = { [item.quicksave_id] : true }
			}
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
		viewSnapshots( site_id ) {

			site = this.sites.filter(site => site.id == site_id)[0];
			axios.get(
				'/wp-json/captaincore/v1/site/'+site_id+'/snapshots', {
					headers: {'X-WP-Nonce':wpApiSettings.nonce}
				})
				.then(response => { 
						site.environments[0].snapshots = response.data.Production
						site.environments[1].snapshots = response.data.Staging				
				});

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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

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

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					site.loading_themes = false;
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

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

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					environment = site.environments.filter( e => e.environment == site.environment_selected )[0]
					updated_themes = environment.themes.filter(theme => theme.name != theme_name);
					environment.themes = updated_themes;
					site.loading_themes = false;
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
			});

		},
		addPlugin ( site_id ){
			site = this.sites.filter(site => site.id == site_id)[0]
			this.new_plugin.show = true;
			this.new_plugin.sites.push( site );
			this.new_plugin.site_name = site.name;
			this.new_plugin.current_plugins = site.environments.filter( e => e.environment == site.environment_selected )[0].plugins.map( p => p.name );
			this.new_plugin.environment_selected = site.environment_selected;
			this.fetchPlugins();
		},
		addPluginBulk() {
			this.new_plugin.show = true;
			this.new_plugin.sites = this.sites_selected;
			this.new_plugin.site_name = this.new_plugin.sites.length + " sites";
			this.new_plugin.current_plugins = [];
			this.new_plugin.environment_selected = this.dialog_bulk.environment_selected;
			this.fetchPlugins();
		},
		installPlugin ( plugin ) {

			if ( this.new_plugin.sites.length ==  1 ) {
				site_id = this.new_plugin.sites[0].id;
				environment_selected = this.new_plugin.sites[0].environment_selected
			} else {
				site_id = this.new_plugin.sites.map( s => s.id )
				environment_selected = this.new_plugin.environment_selected
			}

			site_name = this.new_plugin.site_name;

			should_proceed = confirm("Proceed with installing plugin " + plugin.name + " on " + site_name + "?");

			if ( ! should_proceed ) {
				return;
			}

			// Enable loading progress
			description = "Installing plugin '" +plugin.name + "' to " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"site_id": site_id, "environment": environment_selected, "description": description, "status": "queued", "command": "manage", stream: []});

			// WP ClI command to send
			wpcli = "wp plugin install " + plugin.download_link + " --force";

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'command': "manage",
				'value': "ssh",
				'background': true,
				'environment': environment_selected,
				'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.new_plugin.show = false
					self.snackbar.message = description
					self.snackbar.show = true
					self.new_plugin.api.items = []
					self.new_plugin.api.info = {}
					self.new_plugin.loading = false;

					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
				})
				.catch(error => {
					console.log(error.response)
					self.new_plugin.show = true
				});

		},
		uninstallPlugin ( plugin ) {

			if ( this.new_plugin.sites.length ==  1 ) {
				site_id = this.new_plugin.sites[0].id;
				environment_selected = this.new_plugin.sites[0].environment_selected
			} else {
				site_id = this.new_plugin.sites.map( s => s.id )
				environment_selected = this.new_plugin.environment_selected
			}

			site_name = this.new_plugin.site_name;

			should_proceed = confirm("Proceed with uninstalling plugin " + plugin.name + " from " + site_name + "?");

			if ( ! should_proceed ) {
				return;
			}

			// Enable loading progress
			description = "Uninstalling plugin '" +plugin.name + "' from " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"site_id": site_id, "environment": environment_selected, "description": description, "status": "queued", "command": "manage", stream: []});

			// WP ClI command to send
			wpcli = "wp plugin delete " + plugin.slug;

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'command': "manage",
				'value': "ssh",
				'background': true,
				'environment': environment_selected,
				'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.new_plugin.show = false
					self.snackbar.message = description
					self.snackbar.show = true
					self.new_plugin.api.items = []
					self.new_plugin.api.info = {}
					self.new_plugin.loading = false;

					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
				})
				.catch(error => {
					console.log(error.response)
					self.new_plugin.show = true
				});

		},
		fetchPlugins() {
			this.new_plugin.loading = true;
			site_id = this.new_plugin.sites[0].id
			search = this.new_plugin.search
			var data = {
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': "fetchPlugins",
				'page': this.new_plugin.page
			};
			if ( search ) {
				data.value = search;
			}
			self = this;
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.new_plugin.api.items = response.data.plugins
					self.new_plugin.api.info = response.data.info
					self.new_plugin.loading = false;
				})
				.catch(error => {
					console.log(error.response)
					self.new_plugin.loading = false;
				});
		},
		addTheme ( site_id ) {
			site = this.sites.filter(site => site.id == site_id)[0]
			this.new_theme.show = true;
			this.new_theme.sites.push( site );
			this.new_theme.site_name = site.name;
			this.new_theme.current_themes = site.environments.filter( e => e.environment == site.environment_selected )[0].themes.map( p => p.name );
			this.new_theme.environment_selected = site.environment_selected;
			this.fetchThemes();
		},
		addThemeBulk() {
			this.new_theme.show = true;
			this.new_theme.sites = this.sites_selected;
			this.new_theme.site_name = this.new_theme.sites.length + " sites";
			this.new_theme.environment_selected = this.dialog_bulk.environment_selected;
			this.fetchThemes();
		},
		installTheme ( theme ) {

			if ( this.new_theme.sites.length ==  1 ) {
				site_id = this.new_theme.sites[0].id;
				environment_selected = this.new_theme.sites[0].environment_selected
			} else {
				site_id = this.new_theme.sites.map( s => s.id )
				environment_selected = this.new_theme.environment_selected
			}

			site_name = this.new_theme.site_name;

			should_proceed = confirm("Proceed with installing theme " + theme.name + " on " + site_name + "?");

			if ( ! should_proceed ) {
				return;
			}

			// Enable loading progress
			description = "Installing theme '" + theme.name + "' to " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"site_id": site_id, "environment": environment_selected, "description": description, "status": "queued", "command": "manage", stream: []});

			// WP ClI command to send
			wpcli = "wp theme install " + theme.slug + " --force";

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'command': "manage",
				'value': "ssh",
				'background': true,
				'environment': environment_selected,
				'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.new_theme.show = false
					self.snackbar.message = description
					self.snackbar.show = true
					self.new_theme.api.items = []
					self.new_theme.api.info = {}
					self.new_theme.loading = false;

					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
				})
				.catch(error => {
					console.log(error.response)
					self.new_theme.show = true
				});

		},
		uninstallTheme ( theme ) {

			if ( this.new_theme.sites.length ==  1 ) {
				site_id = this.new_theme.sites[0].id;
				environment_selected = this.new_theme.sites[0].environment_selected
			} else {
				site_id = this.new_theme.sites.map( s => s.id )
				environment_selected = this.new_theme.environment_selected
			}

			site_name = this.new_theme.site_name;

			should_proceed = confirm("Proceed with uninstalling theme " + theme.name + " from " + site_name + "?");

			if ( ! should_proceed ) {
				return;
			}

			// Enable loading progress
			description = "Uninstalling theme '" + theme.name + "' from " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"site_id": site_id, "environment" : environment_selected, "description": description, "status": "queued", "command": "manage", stream: []});

			// WP ClI command to send
			wpcli = "wp theme delete " + theme.slug;

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'command': "manage",
				'value': "ssh",
				'background': true,
				'environment': environment_selected,
				'arguments': { "name":"Commands","value":"command","command":"ssh","input": wpcli }
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.new_theme.show = false
					self.snackbar.message = description
					self.snackbar.show = true
					self.new_theme.api.items = []
					self.new_theme.api.info = {}
					self.new_theme.loading = false;

					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
				})
				.catch(error => {
					console.log(error.response)
					self.new_theme.show = true
				});

		},
		fetchThemes() {
			this.new_theme.loading = true;
			site_id = this.new_theme.sites[0].id
			search = this.new_theme.search
			var data = {
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': "fetchThemes",
				'page': this.new_theme.page
			};
			if ( search ) {
				data.value = search;
			}
			self = this;
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.new_theme.api.items = response.data.themes
					self.new_theme.api.info = response.data.info
					self.new_theme.loading = false;
				})
				.catch(error => {
					console.log(error.response)
					self.new_theme.loading = false;
				});
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
			this.jobs.push({"job_id": job_id, "description": description, "status": "queued", stream: [], conn: {}});

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

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
				self.sites.filter(site => site.id == site_id)[0].loading_plugins = false;
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
				})
				.catch(error => {
					console.log(error.response)
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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

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

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					environment = site.environments.filter( e => e.environment == site.environment_selected )[0]
					updated_plugins = environment.plugins.filter(plugin => plugin.name != plugin_name);
					environment.plugins = updated_plugins;
					self.sites.filter(site => site.id == site_id)[0].loading_plugins = false;

					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: [],"command":"update-wp"});

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'environment': site.environment_selected,
				'command': "update-wp",
				'background': true
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
				});

		},
		themeAndPluginChecks( site_id ) {
			site = this.sites.filter(site => site.id == site_id)[0];
			this.dialog_theme_and_plugin_checks.site = site;
			this.dialog_theme_and_plugin_checks.show = true;
		},
		killCommand( job_id ) {
			job = this.jobs.filter(job => job.job_id == job_id)[0]
			job.conn.send( '{ "token" : "'+ job.job_id +'", "action" : "kill" }' );
			//job.conn.close();
			job.status = "error"
		},
		runCommand( job_id ) {

			job = this.jobs.filter(job => job.job_id == job_id)[0]
			self = this;
			// console.log( "Start: select token " + job_id + " found job " + job.job_id )

			job.conn = new WebSocket( this.socket );
			job.conn.onopen = () => job.conn.send( '{ "token" : "'+ job.job_id +'", "action" : "start" }' );
			
			job.conn.onmessage = (session) => self.writeSocket( job_id, session );
			job.conn.onclose = () => {
				job = self.jobs.filter(job => job.job_id == job_id)[0]
				last_output_index = job.stream.length - 1;
				last_output = job.stream[last_output_index];

				if ( last_output == "Finished.") {
					job.status = "done"
				} else {
					job.status = "error"
				}
				
				if ( job.command == "syncSite" ) {
					self.fetchSiteInfo( job.site_id );
				}

				if ( job.command == "manage" && job.environment ) {
					self.syncSite( job.site_id, job.environment );
				}

				if ( job.command == "manage" && !job.environment ) {
					self.syncSite( job.site_id );
				}

				if ( job.command == "saveUpdateSettings" ){
					// to do
				}

				if ( job.command == "update-wp" ){
					// to do
					site.update_logs = [];
					self.fetchUpdateLogs( site_id );
				}

				// console.log( "Done: select token " + job_id + " found job " + job.job_id )
			}
		},
		writeSocket( job_id, session ) {
			job = self.jobs.filter(job => job.job_id == job_id)[0]
			job.stream.push( session.data )
		},
		configureFathom( site_id ) {
			site = this.sites.filter(site => site.id == site_id)[0];
			this.dialog_fathom.site = site
			this.dialog_fathom.environment = site.environments.filter( e => e.environment == site.environment_selected )[0];
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
          		Object.assign(this.dialog_fathom.environment.fathom[this.dialog_fathom.editedIndex], this.dialog_fathom.editedItem)
			} else {
				this.dialog_fathom.environment.fathom.push(this.dialog_fathom.editedItem)
			}
			this.configureFathomClose()
		},
		newFathomItem(){
			this.dialog_fathom.environment.fathom.push({ "code": "", "domain" : "" })
		},
		deleteFathomItem (item) {
			const index = this.dialog_fathom.environment.fathom.indexOf(item)
			confirm('Are you sure you want to delete this item?') && this.dialog_fathom.environment.fathom.splice(index, 1)
		},
		saveFathomConfigurations() {
			site = this.dialog_fathom.site;
			environment = this.dialog_fathom.environment;
			site_id = site.id;
			should_proceed = confirm("Apply new Fathom tracker for " + site.name + "?");

			if ( ! should_proceed ) {
				return;
			}

			// New job for progress tracking
			job_id = Math.round((new Date()).getTime());
			description = "Updating Fathom tracker on " + site.name;
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			// Prep AJAX request
			var data = {
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': "updateFathom",
				'environment': site.environment_selected,
				'value': environment.fathom,
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// close dialog
					self.dialog_fathom.site = {};
					self.dialog_fathom.show = false;
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: [], "command":"saveUpdateSettings"});

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

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
				});

		},
		deleteUserDialog( username, site_id ){

			site = this.sites.filter(site => site.id == site_id)[0];
			environment = site.environments.filter( e => e.environment == site.environment_selected )[0];

			this.dialog_delete_user.username = username
			this.dialog_delete_user.site = site
			this.dialog_delete_user.show = true
			this.dialog_delete_user.users = environment.users.filter( u => u.user_login != username )
			
		},
		deleteUser() {

			if ( this.dialog_delete_user.reassign.ID == undefined ) {
				this.snackbar.message = "Can't remove user without reassign content to another user.";
				this.snackbar.show = true;
				return;
			}

			username = this.dialog_delete_user.username
			site = this.dialog_delete_user.site
			environment = site.environments.filter( e => e.environment == site.environment_selected )[0];
			should_proceed = confirm("Are you sure you want to delete user " + username + "?");

			if ( ! should_proceed ) {
				return;
			}
			site_id = site.id
			site_name = site.name;
			description = "Delete user '" + username + "' from " + site_name + " (" + site.environment_selected + ")";
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"site_id":site_id,"command":"manage","description": description, "status": "queued", stream: []});

			// WP ClI command to send
			wpcli = "wp user delete " + username + " --reassign=" + this.dialog_delete_user.reassign.ID;

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

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					environment.users = environment.users.filter(user => user.username != username);
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
					self.dialog_delete_user.show = false
					self.dialog_delete_user.site = {}
					self.dialog_delete_user.reassign = {}
					self.dialog_delete_user.username = ""
					self.dialog_delete_user.users = []
				});

		},
		bulkactionLaunch() {
			if ( this.dialog_bulk.environment_selected == "Production" || this.dialog_bulk.environment_selected == "Both" ) {
				this.sites_selected.forEach(site => window.open(site.environments[0].home_url));
			}
			if ( this.dialog_bulk.environment_selected == "Staging" || this.dialog_bulk.environment_selected == "Both" ) {
				this.sites_selected.forEach(site => { 
				if ( site.environments[1].home_url ) {
						window.open( site.environments[1].home_url );
				}
				});
			}
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
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: [], "command": "manage"});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
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
		updateSearch: lodash.debounce(function (e) {
			this.search = e;
			this.filterSites();
		}, 300),
		filterSites() {

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

					// Populate title with format "version (count)"
					versions.forEach( v => {
						v.title = v.name + " (" + v.count + ")";
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

					// Populate title with format "status (count)"
					statuses.forEach( s => {
						s.title = s.name + " (" + s.count + ")";
					});

					filter_statuses.push({name: filter, statuses: statuses });

				});

				this.site_filter_status = filter_statuses;

				} // end filterby

				}

				// Neither filter is set so set all sites to filtered true.
				if ( this.applied_site_filter.length == 0 && !this.search ) {

					this.site_filter_status = [];
					this.site_filter_version = [];

					this.sites.forEach(function(site) {
						site.filtered = true;
					});

				}

				this.page = 1;

		}
	}
});

</script>
</body>
</html>