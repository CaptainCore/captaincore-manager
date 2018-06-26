<?php

// Display Health page

get_header();
$user       = wp_get_current_user();
$role_check = in_array( 'administrator', $user->roles );
if ( $role_check ) {

?>

<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<link href="https://unpkg.com/vuetify/dist/vuetify.min.css" rel="stylesheet">
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

.example-drag label.btn {
  margin-bottom: 0;
  margin-right: 1rem;
}
.example-drag .drop-active {
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
.example-drag .drop-active h3 {
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
.content-area ul.expansion-panel {
	margin:0px;
	list-style: none;
}
</style>
<?php if ( $_SERVER['SERVER_NAME'] == 'anchor.test' ) { ?>
<script src="https://unpkg.com/vue/dist/vue.js"></script>
<?php } else { ?>
<script src="https://unpkg.com/vue/dist/vue.min.js"></script>
<?php } ?>
<script src="https://unpkg.com/vuetify/dist/vuetify.js"></script>

<?php } ?>

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
	//$production_address = get_field('address', $website->ID);
	//$staging_address = get_field('address_staging', $website->ID);
	?>
{ id: <?php echo $website->ID; ?>,
name: "<?php echo get_the_title( $website->ID ); ?>" },
<?php
	$count++;
}
?>
];
</script>
<div id="app" v-cloak>
	<v-app>
		<v-content>
		<template>
		  <v-expansion-panel expand>
		    <v-expansion-panel-content v-for="(item,i) in 5" :key="i" :value="item === 2">
		      <div slot="header">Item</div>
		      <v-card>
		        <v-card-text class="grey lighten-3">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</v-card-text>
		      </v-card>
		    </v-expansion-panel-content>
		  </v-expansion-panel>
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
		dialog: false,
		page: 1,
		jobs: [],
		add_site: false,
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
		pretty_timestamp: function (date) {
			// takes in '2018-06-18-194447' and converts to '2018-06-18 19:44:47' then returns "Monday, Jun 18, 2018, 7:44 PM"
			date = date.substring(0,10) + " " + date.substring(11,13) + ":" + date.substring(13,15) + ":" + date.substring(15,17);
			formatted_date = new Date(date).toLocaleTimeString("en-us", pretty_timestamp_options);
			return formatted_date;
		}
	},
});

</script>
<?php endif;
