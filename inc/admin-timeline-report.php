<style>
.heading {
	margin-top: 2em;
	font-weight: bold;
	font-size: 1.4em;
}
.total {
	font-weight: bold;
	padding: 10px 0px;
	display: block;
}
.websites li {
	margin-bottom: 0px;
}
.websites ul {
	margin-left: 10px;
}
.websites li p {
	line-height: 1em;
}
</style>
<script>

var chart_data = [];
var chart_data_formatted = [];

var chart_data_year = [];
var chart_data_year_formatted = [];

  jQuery( document ).ready( function () {

    // Generate array to build monthly chart
    jQuery('.websites .customer').each(function() {
      var start_date = new Date( jQuery(this).data('start') );
      var end_date = new Date( jQuery(this).data('end') );
      var year = start_date.getFullYear();
      var month = ('0' + (start_date.getMonth()+1)).slice(-2);
      var key = year+"-"+month;


      if ( start_date != "Invalid Date" ) {
        if ( chart_data[key] == undefined ) {
          chart_data[key] = 0;
        } else {
          chart_data[key] = chart_data[key] + 1;
        }
      }

      if ( end_date != "Invalid Date") {
        if ( chart_data[key] == undefined ) {
          chart_data[key] = -1;
        } else {
          chart_data[key] = chart_data[key] - 1;
        }
      }

    });

    chart_data_keys = Object.keys( chart_data ).sort();

    // Loop through key
    for (var key in chart_data_keys) {
      var count = 0;
      var previous_count = chart_data[ chart_data_keys[key - 1 ] ];
      if (isNaN(previous_count) ) {

      } else {
        count = chart_data[ chart_data_keys[key] ] + parseInt( previous_count );
        chart_data[ chart_data_keys[key] ] = chart_data[ chart_data_keys[key] ] + parseInt(previous_count);
      }

      chart_data_formatted.push( [ new Date( chart_data_keys[key] ), count ] );
    }

    // Generate array to build yearly chart
    jQuery('.websites .customer').each(function() {
      var start_date = new Date( jQuery(this).data('start') );
      var end_date = new Date( jQuery(this).data('end') );
      var year = start_date.getFullYear();
      var month = ('0' + (start_date.getMonth()+1)).slice(-2);
      var key = year;

      if ( start_date != "Invalid Date" ) {
        if ( chart_data_year[key] == undefined ) {
          //chart_data_year[key] = 0;
        } else {
          chart_data_year[key] = chart_data_year[key] + 1;
        }
      }

      if ( end_date != "Invalid Date") {
        if ( chart_data_year[key] == undefined ) {
          chart_data_year[key] = -1;
        } else {
          chart_data_year[key] = chart_data_year[key] - 1;
        }
      }

    });

    chart_data_year_keys = Object.keys( chart_data_year ).sort();

    // Loop through key
    for (var key in chart_data_year_keys) {

      var count = 0;
      var previous_count = chart_data_year[ chart_data_year_keys[key - 1 ] ];
      if (isNaN(previous_count) ) {

      } else {
        count = chart_data_year[ chart_data_year_keys[key] ] + parseInt( previous_count );
        chart_data_year[ chart_data_year_keys[key] ] = chart_data_year[ chart_data_year_keys[key] ] + parseInt(previous_count);
      }

      chart_data_year_formatted.push( [ new Date( chart_data_year_keys[key], 0, 1 ), chart_data_year_keys[key], count ] );
    }

    google.charts.load('current', {'packages':['bar']});
    google.charts.setOnLoadCallback(drawChart);

    function drawChart() {

    var data = new google.visualization.DataTable();
    data.addColumn('date', 'Time of Day');
    data.addColumn('number', 'Customers');

    data.addRows(chart_data_formatted);

    var data_year = new google.visualization.DataTable();
    data_year.addColumn('date', 'Year');
		data_year.addColumn({type: 'string', role: 'tooltip'});
    data_year.addColumn('number', 'Customers');

    data_year.addRows(chart_data_year_formatted);

    var options = {
      title: '',
      height: 450,
			legend: false
    };

    var chart = new google.charts.Bar(document.getElementById('chart_div'));
    var chart_year = new google.charts.Bar(document.getElementById('chart_div_year'));

    chart.draw(data, google.charts.Bar.convertOptions(options));
    chart_year.draw(data_year, google.charts.Bar.convertOptions(options));
    }

  });

</script>
<div class="wrap"><div id="icon-tools" class="icon32"></div>
<h2>Customer Timeline</h2>
<?php include "admin-submenu-tabs.php"; ?>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

<div id="chart_div"></div>
<div id="result"></div>

<div id="chart_div_year"></div>
<div id="result_year"></div>

<?php

$today = date('Ymd');

// WP_Query arguments
$args = array (
	'post_type'              => array( 'captcore_customer' ),
	'posts_per_page'         => '-1',
  'meta_query' => array(
	     array(
	        'key'		=> 'billing_date',
	        'compare'	=> '<=',
	        'value'		=> $today,
	    ),
      array(
         'key'		=> 'total_price',
         'compare'	=> '>',
         'value'		=> "0",
     )
    ),
);

// The Query
$query = new WP_Query( $args );
$posts = $query->get_posts();

// The Loop
if ( $query->have_posts() ) {
	echo "<div class='websites'>";
	while ( $query->have_posts() ) {
		$query->the_post();
		$id = get_the_ID();
    $status = get_field('status');

    if ($status == "cancelled") {
      // Guess close date based on related websites
      $close_date = get_the_modified_date('m/d/y');
    } else {
      $close_date = "";
    }
		?>
		<div class="customer" data-start="<?php the_field('billing_date'); ?>" data-end="<?php echo $close_date; ?>" data-status="<?php the_field('status'); ?>">
			<?php the_title(); ?> - ID# <?php echo get_the_ID(); ?>

		</div>

		<?php
	}
	echo "</div>";
} else {
	// no posts found
}

// Restore original Post Data
wp_reset_postdata();
?>
</div>
