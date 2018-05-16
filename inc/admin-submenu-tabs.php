<?php $screen = get_current_screen(); ?>
<h2 class="nav-tab-wrapper">
  <a class="nav-tab<?php if( $screen->id == "captaincore_page_captaincore_report") { echo " nav-tab-active"; } ?>" href="/wp-admin/admin.php?page=captaincore_report">Report</a>
  <a class="nav-tab<?php if( $screen->id == "admin_page_captaincore_partner") { echo " nav-tab-active"; } ?>" href="/wp-admin/admin.php?page=captaincore_partner">Partners</a>
	<a class="nav-tab<?php if( $screen->id == "admin_page_captaincore_installs") { echo " nav-tab-active"; } ?>" href="/wp-admin/admin.php?page=captaincore_installs">Installs</a>
	<a class="nav-tab<?php if( $screen->id == "admin_page_captaincore_timeline") { echo " nav-tab-active"; } ?>" href="/wp-admin/admin.php?page=captaincore_timeline">Timeline</a>
	<a class="nav-tab<?php if( $screen->id == "admin_page_captaincore_kpi") { echo " nav-tab-active"; } ?>" href="/wp-admin/admin.php?page=captaincore_kpi">KPI</a>
	<a class="nav-tab<?php if( $screen->id == "admin_page_captaincore_quicksaves") { echo " nav-tab-active"; } ?>" href="/wp-admin/admin.php?page=captaincore_quicksaves">Quicksaves</a>
</h2>
