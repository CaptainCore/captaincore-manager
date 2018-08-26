<style>
a.nav-tab {
	position: relative;
}
a.nav-tab span {
	display: none;
	position: absolute;
	left: -27px;
	top: -30px;
	padding: 0px 10px;
	border-radius: 4px;
	z-index: 9999;
	font-size: 0.8em;
	width: 74px;
	text-align: center;
}
a.nav-tab:hover span {
	display: block;

}
a.nav-tab.nav-tab-active span {
	background: #f1f1f1
}

</style>

<?php $screen = get_current_screen(); ?>
<h2 class="nav-tab-wrapper" style="margin-top:30px;">
  <a class="nav-tab dashicons-before dashicons-admin-multisite<?php if( $screen->post_type == "captcore_website" ) { echo " nav-tab-active"; } ?>" href="/wp-admin/edit.php?post_type=captcore_website"><span>Websites</span></a>
  <a class="nav-tab dashicons-before dashicons-groups<?php if( $screen->post_type == "captcore_customer") { echo " nav-tab-active"; } ?>" href="/wp-admin/edit.php?post_type=captcore_customer"><span>Customers</span></a>
	<a class="nav-tab dashicons-before dashicons-admin-users<?php if( $screen->post_type == "captcore_contact") { echo " nav-tab-active"; } ?>" href="/wp-admin/edit.php?post_type=captcore_contact"><span>Contacts</span></a>
	<a class="nav-tab dashicons-before dashicons-welcome-widgets-menus<?php if( $screen->post_type == "captcore_domain") { echo " nav-tab-active"; } ?>" href="/wp-admin/edit.php?post_type=captcore_domain"><span>Domains</span></a>
	<a class="nav-tab dashicons-before dashicons-media-spreadsheet<?php if( $screen->post_type == "captcore_changelog") { echo " nav-tab-active"; } ?>" href="/wp-admin/edit.php?post_type=captcore_changelog"><span>Web Logs</span></a>
	<a class="nav-tab dashicons-before dashicons-controls-repeat<?php if( $screen->post_type == "captcore_process") { echo " nav-tab-active"; } ?>" href="/wp-admin/edit.php?post_type=captcore_process"><span>Processes</span></a>
	<a class="nav-tab dashicons-before dashicons-media-spreadsheet<?php if( $screen->post_type == "captcore_processlog") { echo " nav-tab-active"; } ?>" href="/wp-admin/edit.php?post_type=captcore_processlog"><span>Process Logs</span></a>
	<a class="nav-tab dashicons-before dashicons-building<?php if( $screen->post_type == "captcore_server") { echo " nav-tab-active"; } ?>" href="/wp-admin/edit.php?post_type=captcore_server"><span>Servers</span></a>
	<a class="nav-tab dashicons-before dashicons-backup<?php if( $screen->post_type == "captcore_snapshot") { echo " nav-tab-active"; } ?>" href="/wp-admin/edit.php?post_type=captcore_snapshot"><span>Snapshots</span></a>
</h2>
<script>
jQuery(document).ready(function() {
	jQuery('li#toplevel_page_captaincore').removeClass("wp-not-current-submenu").addClass("wp-has-current-submenu wp-menu-open");
	jQuery('li#toplevel_page_captaincore > a').addClass("wp-has-current-submenu");
	jQuery('.wp-submenu .wp-first-item').addClass("current");
});
</script>
