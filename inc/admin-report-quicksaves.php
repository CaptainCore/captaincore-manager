<script type="text/javascript">

jQuery( document ).ready(function() {
	var $quicksaves = jQuery(".activity-log");
	$quicksaves.find('div').sort(function(a, b) {
			return +b.dataset.quicksaves - +a.dataset.quicksaves;
	}).appendTo($quicksaves);
});


</script>
<link rel='stylesheet' id='font-awesome-css'  href='https://anchor.host/wp-content/themes/swell-anchorhost/css/font-awesome.min.css' type='text/css' media='all' />

<div class="wrap"><div id="icon-tools" class="icon32"></div>
<h2>Quicksaves Report</h2>

<?php include "admin-submenu-tabs.php"; ?>
<p></p>
<div class="activity-log">
<?php

$today = date('Ymd');

$db_quicksaves = new CaptainCore\quicksaves;


// WP_Query arguments
$websites = get_posts(array(
	  'post_type' 			=> 'captcore_website',
		'posts_per_page'  => '-1'
));

foreach ($websites as $website) {

	$quicksaves = $db_quicksaves->fetch( $website->ID );

	?>

	<div data-quicksaves="<?php echo count($quicksaves); ?>"><?php echo get_field("address", $website->ID); ?> has <?php echo count($quicksaves); ?> Quicksaves</div>

<?php
}

// Restore original Post Data
wp_reset_postdata();
?>

</div>
