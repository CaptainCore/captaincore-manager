<?php $screen = get_current_screen(); ?>
<h2 class="nav-tab-wrapper">
  <a class="nav-tab<?php if( $screen->id == "captaincore_page_anchor_report") { echo " nav-tab-active"; } ?>" href="/wp-admin/admin.php?page=anchor_report">Report</a>
  <a class="nav-tab<?php if( $screen->id == "admin_page_anchor_partner") { echo " nav-tab-active"; } ?>" href="/wp-admin/admin.php?page=anchor_partner">Partners</a>
	<a class="nav-tab<?php if( $screen->id == "admin_page_anchor_installs") { echo " nav-tab-active"; } ?>" href="/wp-admin/admin.php?page=anchor_installs">Installs</a>
	<a class="nav-tab<?php if( $screen->id == "admin_page_anchor_timeline") { echo " nav-tab-active"; } ?>" href="/wp-admin/admin.php?page=anchor_timeline">Timeline</a>
	<a class="nav-tab<?php if( $screen->id == "admin_page_anchor_kpi") { echo " nav-tab-active"; } ?>" href="/wp-admin/admin.php?page=anchor_kpi">KPI</a>
</h2>
