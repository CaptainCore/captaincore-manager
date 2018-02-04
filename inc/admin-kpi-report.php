<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<link rel='stylesheet' id='font-awesome-css'  href='https://anchor.host/wp-content/themes/swell-anchorhost/css/font-awesome.min.css' type='text/css' media='all' />
<style>
.heading {
	margin-right: 1em;
}
.process-star {
	display:inline-block;
	position: relative;
}

.process-star .info p {
    line-height: 1em;
    padding: 8px 0px;
}
.process-star .desc {
    line-height: 1em;
    padding: 8px 0px;
}
.process-star hr {
	margin: 10px 0px;
	padding: 0px;
	opacity: 0.5;
}
.process-star a {
	-webkit-transition: 0.0s ease;
  -moz-transition: 0.0s ease;
  -o-transition: 0.0s ease;
  transition: 0.0s ease;
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

.process-icons i {
	margin-left: 20px;
}
.process-icons i:first-child {
	margin-left: 0px;
}
.process-stars {
	position: relative;
	padding-right: 122px;
	border-bottom: 1px solid #ccc;
	margin: 1em 0;
	padding-bottom: 1em;
	line-height: 1em;
}
.process-stars:last-child {
	border-bottom: 0px;
}
.process-stars .heading {
	text-align: left;
	margin-bottom: .5em;
}
.process-stars .heading span {
	font-size: .6em;
	background-color: rgba(39, 195, 243, 0.79);
	border-radius: 4px;
	color: #fff;
	position: relative;
	top: -3px;
	margin: 0 0 0 5px;
	display: inline-block;
	text-align: center;
	padding: 3px 3px;
	line-height: 1em;
}
.process-stars .heading span.site-count {
    color: #888;
    background: none;
}
.process-role-manager {
	display: inline;
    font-size: 13px;
}
.process-description {
    font-size: 0.8em;
    line-height: 1.2em;
    margin-bottom: 1em;
}
.process-log-update {
	max-width: 980px;
	margin: auto;
	position: absolute;
	z-index: 10;
	left: 0;
	right: 0;
	background-color: #fff;
	padding: 1em 1em 0 1em;
	margin-top: 1em;
	border-radius: 4px;
	border: 1px solid rgba(74, 74, 74, 0.8);
	border-style: solid;
}
.process-log-update .acf-relationship .filters {
	background: #f7f7f7;
}
.process-log-update .acf-relationship .filters .filter {
	line-height: auto;
	height: auto;
	padding: 5px;
}
.process-log-update .acf-relationship .list .acf-rel-label,
.process-log-update .acf-relationship .list .acf-rel-item,
.process-log-update .acf-relationship .list p {
    padding: 0px 7px;
    margin: 0;
    display: block;
    position: relative;
    min-height: 12px;
    font-size: 0.85em;
    line-height: 1.8em;
}
.process-log-update .acf-field textarea {
	height: 64px;
}
.process-log-update .acf-field.acf-field-select.acf-field-588bb7bd3cab6 label {
	display: none;
}

.activity-log i span {
	display: block;
    visibility: hidden;
    opacity: 0;
    transition: visibility 0s, opacity 0.5s ease-in-out;
    position: absolute;
    background: #000;
    border-radius: 10px;
    color: #fff;
    padding: 8.5px 12px;
    font-size: 13px;
		left: -10px;
    top: 23px;
    width: 230px;
    z-index: 1;
    font-family: "proxima-nova-soft",sans-serif;
    font-style: normal;
    font-weight: 400;
}

#plans i span:before,
.block i span:before {
	content: " ";
	width: 0;
	height: 0;
	border-top: 11px solid transparent;
	border-bottom: 11px solid transparent;
	border-right: 11px solid #000;
	top: 4px;
	left: -9px;
	position: absolute;
}

.activity-log i span:before {
	content: " ";
    width: 0;
    height: 0;
    border-left: 11px solid transparent;
    border-bottom: 11px solid #000;
    border-right: 11px solid transparent;
    top: -10px;
    left: 6px;
    position: absolute;
}

#plans i:hover span,
.block i:hover span,
.activity-log i:hover span {
	visibility: visible;
	  opacity: 1;
	display: block;
	-webkit-transition: all 0.2s ease-in-out;
	-moz-transition: all 0.2s ease-in-out;
	-ms-transition: all 0.2s ease-in-out;
	-o-transition: all 0.2s ease-in-out;
	transition: all 0.2s ease-in-out;
}

.activity-log .process-stars {
    float: left;
}

.results > div {
    float: left;
    margin-right: 10px;
		display: none;
}
.results > div:nth-last-child(-n+5) {
	display: block;
}
.results .activity-log-kpi-1:after {
	content: " New Customers";
}
.results .activity-log-kpi-2:after {
	content: " Support Requests";
}
.results .activity-log-kpi-3:after {
	content: " Product Development";
}
.results .heading {
    margin-top: 1em;
    margin-bottom: .2em;
    font-size: 1.2em;
    font-weight: bold;
}
</style>

<script>
/*! jquery-dateFormat 18-05-2015 */
var DateFormat={};!function(a){var b=["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"],c=["Sun","Mon","Tue","Wed","Thu","Fri","Sat"],d=["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],e=["January","February","March","April","May","June","July","August","September","October","November","December"],f={Jan:"01",Feb:"02",Mar:"03",Apr:"04",May:"05",Jun:"06",Jul:"07",Aug:"08",Sep:"09",Oct:"10",Nov:"11",Dec:"12"},g=/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.?\d{0,3}[Z\-+]?(\d{2}:?\d{2})?/;a.format=function(){function a(a){return b[parseInt(a,10)]||a}function h(a){return c[parseInt(a,10)]||a}function i(a){var b=parseInt(a,10)-1;return d[b]||a}function j(a){var b=parseInt(a,10)-1;return e[b]||a}function k(a){return f[a]||a}function l(a){var b,c,d,e,f,g=a,h="";return-1!==g.indexOf(".")&&(e=g.split("."),g=e[0],h=e[e.length-1]),f=g.split(":"),3===f.length?(b=f[0],c=f[1],d=f[2].replace(/\s.+/,"").replace(/[a-z]/gi,""),g=g.replace(/\s.+/,"").replace(/[a-z]/gi,""),{time:g,hour:b,minute:c,second:d,millis:h}):{time:"",hour:"",minute:"",second:"",millis:""}}function m(a,b){for(var c=b-String(a).length,d=0;c>d;d++)a="0"+a;return a}return{parseDate:function(a){var b,c,d={date:null,year:null,month:null,dayOfMonth:null,dayOfWeek:null,time:null};if("number"==typeof a)return this.parseDate(new Date(a));if("function"==typeof a.getFullYear)d.year=String(a.getFullYear()),d.month=String(a.getMonth()+1),d.dayOfMonth=String(a.getDate()),d.time=l(a.toTimeString()+"."+a.getMilliseconds());else if(-1!=a.search(g))b=a.split(/[T\+-]/),d.year=b[0],d.month=b[1],d.dayOfMonth=b[2],d.time=l(b[3].split(".")[0]);else switch(b=a.split(" "),6===b.length&&isNaN(b[5])&&(b[b.length]="()"),b.length){case 6:d.year=b[5],d.month=k(b[1]),d.dayOfMonth=b[2],d.time=l(b[3]);break;case 2:c=b[0].split("-"),d.year=c[0],d.month=c[1],d.dayOfMonth=c[2],d.time=l(b[1]);break;case 7:case 9:case 10:d.year=b[3],d.month=k(b[1]),d.dayOfMonth=b[2],d.time=l(b[4]);break;case 1:c=b[0].split(""),d.year=c[0]+c[1]+c[2]+c[3],d.month=c[5]+c[6],d.dayOfMonth=c[8]+c[9],d.time=l(c[13]+c[14]+c[15]+c[16]+c[17]+c[18]+c[19]+c[20]);break;default:return null}return d.date=d.time?new Date(d.year,d.month-1,d.dayOfMonth,d.time.hour,d.time.minute,d.time.second,d.time.millis):new Date(d.year,d.month-1,d.dayOfMonth),d.dayOfWeek=String(d.date.getDay()),d},date:function(b,c){try{var d=this.parseDate(b);if(null===d)return b;for(var e,f=d.year,g=d.month,k=d.dayOfMonth,l=d.dayOfWeek,n=d.time,o="",p="",q="",r=!1,s=0;s<c.length;s++){var t=c.charAt(s),u=c.charAt(s+1);if(r)"'"==t?(p+=""===o?"'":o,o="",r=!1):o+=t;else switch(o+=t,q="",o){case"ddd":p+=a(l),o="";break;case"dd":if("d"===u)break;p+=m(k,2),o="";break;case"d":if("d"===u)break;p+=parseInt(k,10),o="";break;case"D":k=1==k||21==k||31==k?parseInt(k,10)+"st":2==k||22==k?parseInt(k,10)+"nd":3==k||23==k?parseInt(k,10)+"rd":parseInt(k,10)+"th",p+=k,o="";break;case"MMMM":p+=j(g),o="";break;case"MMM":if("M"===u)break;p+=i(g),o="";break;case"MM":if("M"===u)break;p+=m(g,2),o="";break;case"M":if("M"===u)break;p+=parseInt(g,10),o="";break;case"y":case"yyy":if("y"===u)break;p+=o,o="";break;case"yy":if("y"===u)break;p+=String(f).slice(-2),o="";break;case"yyyy":p+=f,o="";break;case"HH":p+=m(n.hour,2),o="";break;case"H":if("H"===u)break;p+=parseInt(n.hour,10),o="";break;case"hh":e=0===parseInt(n.hour,10)?12:n.hour<13?n.hour:n.hour-12,p+=m(e,2),o="";break;case"h":if("h"===u)break;e=0===parseInt(n.hour,10)?12:n.hour<13?n.hour:n.hour-12,p+=parseInt(e,10),o="";break;case"mm":p+=m(n.minute,2),o="";break;case"m":if("m"===u)break;p+=n.minute,o="";break;case"ss":p+=m(n.second.substring(0,2),2),o="";break;case"s":if("s"===u)break;p+=n.second,o="";break;case"S":case"SS":if("S"===u)break;p+=o,o="";break;case"SSS":var v="000"+n.millis.substring(0,3);p+=v.substring(v.length-3),o="";break;case"a":p+=n.hour>=12?"PM":"AM",o="";break;case"p":p+=n.hour>=12?"p.m.":"a.m.",o="";break;case"E":p+=h(l),o="";break;case"'":o="",r=!0;break;default:p+=t,o=""}}return p+=q}catch(w){return console&&console.log&&console.log(w),b}},prettyDate:function(a){var b,c,d;return("string"==typeof a||"number"==typeof a)&&(b=new Date(a)),"object"==typeof a&&(b=new Date(a.toString())),c=((new Date).getTime()-b.getTime())/1e3,d=Math.floor(c/86400),isNaN(d)||0>d?void 0:60>c?"just now":120>c?"1 minute ago":3600>c?Math.floor(c/60)+" minutes ago":7200>c?"1 hour ago":86400>c?Math.floor(c/3600)+" hours ago":1===d?"Yesterday":7>d?d+" days ago":31>d?Math.ceil(d/7)+" weeks ago":d>=31?"more than 5 weeks ago":void 0},toBrowserTimeZone:function(a,b){return this.date(new Date(a),b||"MM/dd/yyyy HH:mm:ss")}}}()}(DateFormat),function(a){a.format=DateFormat.format}(jQuery);
var months_1 = {};
var months_2 = {};
var months_3 = {};

// Adds pad function for adding zero's to single digital months. Example 3 becomes 03.
Number.prototype.pad = function(size) {
  var s = String(this);
  while (s.length < (size || 2)) {s = "0" + s;}
  return s;
}

jQuery( document ).ready( function () {

	jQuery('.activity-log-kpi-1 .process-star').each(function() {

		udate = jQuery(this).data("log-date");
		month = new Date(udate * 1000).getUTCMonth() + 1;
		year = new Date(udate * 1000).getUTCFullYear();
		yearmonth = [year] + [month.pad("2")];

		jQuery(this).attr('data-yearmonth', yearmonth);

		if (months_1.hasOwnProperty(yearmonth)) {
		    months_1[yearmonth] += 1;
		}
		else {
		    months_1[yearmonth] = 1;
		}

	});

	// print results
	for(var key in months_1){

	    var date            = new Date(key.substring(0, 4), key.substring(4, 6) - 1, 1, 1, 1, 1, 1),
	        timestamp       = date.getTime();

	    var parsedDate = jQuery.format.date(timestamp, "MMM yyyy");

	    jQuery('.activity-log-kpi-1 .process-star[data-yearmonth='+ key +']:first').before('<div id="result-'+key+'" class="process-stars">');
	    items = jQuery('.activity-log-kpi-1 .process-star[data-yearmonth='+ key +']').detach();
	    items.appendTo( ".activity-log-kpi-1 #result-"+key );

		jQuery(".activity-log-kpi-1 #result-"+key).prepend('<div class="heading">'+ parsedDate + ' <span>' + months_1[key] + '</span></div>');
		if ( jQuery(".results #result-"+key+" .activity-log-kpi-1").length == 0 ) {
			jQuery('.results').append("<div id='result-"+key+"'><div class='activity-log-kpi-1'>");
			jQuery(".results #result-"+key+" .activity-log-kpi-1").html( months_1[key] );
		}
		if ( jQuery(".results #result-"+key+" .heading").length == 0 ) {
			jQuery(".results #result-"+key).prepend('<div class="heading">'+ parsedDate + '</div>');
		}

	}

	jQuery('.activity-log-kpi-2 .process-star').each(function() {

		udate = jQuery(this).data("log-date");
		month = new Date(udate * 1000).getUTCMonth() + 1;
		year = new Date(udate * 1000).getUTCFullYear();
		yearmonth = [year] + [month.pad("2")];

		jQuery(this).attr('data-yearmonth', yearmonth);

		if (months_2.hasOwnProperty(yearmonth)) {
				months_2[yearmonth] += 1;
		}
		else {
				months_2[yearmonth] = 1;
		}

	});

	// print results
	for(var key in months_2){

			var date            = new Date(key.substring(0, 4), key.substring(4, 6) - 1, 1, 1, 1, 1, 1),
					timestamp       = date.getTime();

			var parsedDate = jQuery.format.date(timestamp, "MMM yyyy");

			jQuery('.activity-log-kpi-2 .process-star[data-yearmonth='+ key +']:first').before('<div id="result-'+key+'" class="process-stars">');
			items = jQuery('.activity-log-kpi-2 .process-star[data-yearmonth='+ key +']').detach();
			items.appendTo( ".activity-log-kpi-2 #result-"+key );

		jQuery(".activity-log-kpi-2 #result-"+key).prepend('<div class="heading">'+ parsedDate + ' <span>' + months_2[key] + '</span></div>');
		if ( jQuery(".results #result-"+key).length == 0 ) {
			jQuery('.results').append("<div id='result-"+key+"'><div class='activity-log-kpi-2'>");
			jQuery(".results #result-"+key+" .activity-log-kpi-2").html( months_2[key] );
		} else if ( jQuery(".results #result-"+key+" .activity-log-kpi-2").length == 0 ) {
			jQuery('.results #result-'+key).append("<div class='activity-log-kpi-2'>");
			jQuery(".results #result-"+key+" .activity-log-kpi-2").html( months_2[key] );
		}
		if ( jQuery(".results #result-"+key+" .heading").length == 0 ) {
			jQuery(".results #result-"+key).prepend('<div class="heading">'+ parsedDate + '</div>');
		}

	}

	jQuery('.activity-log-kpi-3 .process-star').each(function() {

		udate = jQuery(this).data("log-date");
		month = new Date(udate * 1000).getUTCMonth() + 1;
		year = new Date(udate * 1000).getUTCFullYear();
		yearmonth = [year] + [month.pad("2")];

		jQuery(this).attr('data-yearmonth', yearmonth);

		if (months_3.hasOwnProperty(yearmonth)) {
				months_3[yearmonth] += 1;
		}
		else {
				months_3[yearmonth] = 1;
		}

	});

	// print results
	for(var key in months_3){

			var date            = new Date(key.substring(0, 4), key.substring(4, 6) - 1, 1, 1, 1, 1, 1),
					timestamp       = date.getTime();

			var parsedDate = jQuery.format.date(timestamp, "MMM yyyy");

			jQuery('.activity-log-kpi-3 .process-star[data-yearmonth='+ key +']:first').before('<div id="result-'+key+'" class="process-stars">');
			items = jQuery('.activity-log-kpi-3 .process-star[data-yearmonth='+ key +']').detach();
			items.appendTo( ".activity-log-kpi-3 #result-"+key );

		jQuery(".activity-log-kpi-3 #result-"+key).prepend('<div class="heading">'+ parsedDate + ' <span>' + months_3[key] + '</span></div>');
		if ( jQuery(".results #result-"+key).length == 0 ) {
			jQuery('.results').append("<div id='result-"+key+"'><div class='activity-log-kpi-3'>");
			jQuery(".results #result-"+key+" .activity-log-kpi-3").html( months_3[key] );
		} else if ( jQuery(".results #result-"+key+" .activity-log-kpi-3").length == 0 ) {
			jQuery('.results #result-'+key).append("<div class='activity-log-kpi-3'>");
			jQuery(".results #result-"+key+" .activity-log-kpi-3").html( months_3[key] );
		}
		if ( jQuery(".results #result-"+key+" .heading").length == 0 ) {
			jQuery(".results #result-"+key).prepend('<div class="heading">'+ parsedDate + '</div>');
		}

	}

	// calculate and print site count
	jQuery('.activity-log-kpi-1 .process-stars').each(function() {
		site_count = jQuery(this).find('.process-star span.info a.website').length;
	    heading = jQuery(this).find('.heading');
	    if (site_count > 0) {
	    	jQuery( '<span class="site-count">' + site_count + ' sites</span>' ).appendTo( heading );
	    }
	});

	// calculate and print site count
	jQuery('.activity-log-kpi-2 .process-stars').each(function() {
		site_count = jQuery(this).find('.process-star span.info a.website').length;
	    heading = jQuery(this).find('.heading');
	    if (site_count > 0) {
	    	jQuery( '<span class="site-count">' + site_count + ' sites</span>' ).appendTo( heading );
	    }
	});

	// calculate and print site count
	jQuery('.activity-log-kpi-3 .process-stars').each(function() {
		site_count = jQuery(this).find('.process-star span.info a.website').length;
	    heading = jQuery(this).find('.heading');
	    if (site_count > 0) {
	    	jQuery( '<span class="site-count">' + site_count + ' sites</span>' ).appendTo( heading );
	    }
	});

	// Sort results in order by date
	$results = jQuery('.results > div');
	$results.sort(function(a, b) {
	  var nameA = jQuery(a).attr("id");
	  var nameB = jQuery(b).attr("id");
	  if (nameA < nameB) {
	    return -1;
	  }
	  if (nameA > nameB) {
	    return 1;
	  }

	  // names must be equal
	  return 0;
	});

	jQuery('.results > div').remove();  // Remove current results
	jQuery('.results').append($results);  // Adds sorted results

	google.charts.load('current', {'packages':['bar']});
	google.charts.setOnLoadCallback(drawChart);

});

// Draws chart using Google Chart library: https://developers.google.com/chart/interactive/docs/gallery/barchart
function drawChart() {

	// Create data array for chart to use
	result_data = [['Year', 'New Customers', 'Support Requests', 'Product Development']];
	jQuery('.results > div').each(function() {
		title = jQuery(this).find(".heading").text();
		kpi_1 = jQuery(this).find(".activity-log-kpi-1").text();
		kpi_2 = jQuery(this).find(".activity-log-kpi-2").text();
		kpi_3 = jQuery(this).find(".activity-log-kpi-3").text();
		result_data.push([title,parseInt(kpi_1),parseInt(kpi_2),parseInt(kpi_3)]);
	});

 var data = google.visualization.arrayToDataTable(result_data);

 var options = {
     chart: {
         title: '',
         subtitle: '',
     },
	width: 1200,
	height: 400,
	legend: { position: 'none' },
 };

 var chart = new google.charts.Bar(document.getElementById('columnchart_material'));

 chart.draw(data, google.charts.Bar.convertOptions(options));

}

</script>
<div class="wrap"><div id="icon-tools" class="icon32"></div>
<h2>KPI Report</h2>

<div id="columnchart_material" style="width: 1200px; height: 400px;"></div>

<div class="activity-log">
<?php

$today = date('Ymd');

// WP_Query arguments

$process_id = get_field('kpi_new_customers', 'option');
if ($process_id) { $process_id = $process_id[0]; }
$process_logs = get_posts(array(
	  'post_type' 			=> 'process_log',
		'posts_per_page'  => '-1',
		'meta_query' 			=> array(
				array(
					'key' => 'process', // name of custom field
					'value' => '"' . $process_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
					'compare' => 'LIKE'
				)
			)
)); ?>
<h4>KPI New Customers <small>(Process - <?php echo get_the_title($process_id); ?>)</small></h4>
<?php

 if( $process_logs ): ?>
<div class="activity-log-kpi-1">
	<?php foreach( $process_logs as $process_log ):

	$desc = get_field('description', $process_log->ID); ?>
		<div class="process-star" data-log-date="<?php echo get_the_date( "U", $process_log->ID ); ?>">
		<i class="fa fa-star" aria-hidden="true">
		<span class="info">
			<a href="<?php echo get_edit_post_link( $process_log->ID ); ?>" class="alignright"><i class="fa fa-pencil-square-o" aria-hidden="true"></i></a>
			<?php echo get_the_author_meta("first_name", $process_log->post_author); ?> completed <br />
			<?php if ($desc) { ?>
			<div class="desc">
				<?php echo WPCom_Markdown::get_instance()->transform( $desc, array('id'=>false,'unslash'=>false)); ?>
			</div>
		<?php } ?>
			<?php if (get_field('website', $process_log->ID)) {
				$website = get_field('website', $process_log->ID);
				foreach( $website as $p ): // variable must NOT be called $post (IMPORTANT) ?>
				<i class="fa fa-link" aria-hidden="true"></i> <a href="<?php echo get_edit_post_link( $p ); ?>" class="website"><?php echo get_the_title( $p ); ?></a><br />
				<?php endforeach;
			} ?>

			<hr />
			<i class="fa fa-calendar" aria-hidden="true"></i>
			<?php echo get_the_date( "M j | g:ia", $process_log->ID ); ?>
		</span></i>
		</div>

	<?php endforeach; ?>
<?php endif; ?>
</div>

<?php
$process_role_support_requests_id = get_field('kpi_support_requests', 'option');
$process_role_support_requests = get_term($process_role_support_requests_id);

$process_ids = get_posts( array (
	'post_type'      => array( 'process' ),
	'posts_per_page' => '-1',
	'taxonomy'			 => 'process_role',
	'term' 					 => $process_role_support_requests->slug,
	'order' 				 => 'ASC',
	'orderby' 			 => 'title',
  'fields'         => 'ids', // Only get post IDs
));

foreach ($process_ids as $process_id) {
		$processRelations[] = array(
			'key' => 'process',
			'value' => '"'. $process_id. '"',
			'compare' => 'LIKE'
		);
	}
// The Query

$process_logs_support_requests = get_posts(array(
	  'post_type' 			=> 'process_log',
		'posts_per_page'  => '-1',
		'meta_query' 			=> array(
			  array_merge(array('relation' => 'OR'), $processRelations)
			)
));

?>
<div class="clear"></div>

<h4>KPI Support Requests <small>(Role - <?php echo $process_role_support_requests->name; ?>)</small></h4>
<div class="activity-log-kpi-2">
	<?php

 if( $process_logs_support_requests ): ?>

	<?php foreach( $process_logs_support_requests as $process_log_support_requests ):

	$desc = get_field('description', $process_log_support_requests->ID); ?>
		<div class="process-star" data-log-date="<?php echo get_the_date( "U", $process_log_support_requests->ID ); ?>">
		<i class="fa fa-star" aria-hidden="true">
		<span class="info">
			<a href="<?php echo get_edit_post_link( $process_log_support_requests->ID ); ?>" class="alignright"><i class="fa fa-pencil-square-o" aria-hidden="true"></i></a>
			<?php echo get_the_author_meta("first_name", $process_log_support_requests->post_author); ?> completed <br />
			<?php if ($desc) { ?>
			<div class="desc">
				<?php echo WPCom_Markdown::get_instance()->transform( $desc, array('id'=>false,'unslash'=>false)); ?>
			</div>
		<?php } ?>
			<?php if (get_field('website', $process_log_support_requests->ID)) {
				$website = get_field('website', $process_log_support_requests->ID);
				foreach( $website as $p ): // variable must NOT be called $post (IMPORTANT) ?>
				<i class="fa fa-link" aria-hidden="true"></i> <a href="<?php echo get_edit_post_link( $p ); ?>" class="website"><?php echo get_the_title( $p ); ?></a><br />
				<?php endforeach;
			} ?>

			<hr />
			<i class="fa fa-calendar" aria-hidden="true"></i>
			<?php echo get_the_date( "M j | g:ia", $process_log_support_requests->ID ); ?>
		</span></i>
		</div>

	<?php endforeach; ?>
<?php endif; ?>
</div>
<?php

$process_role_product_development_id = get_field('kpi_product_development', 'option');
$process_role_product_development = get_term($process_role_product_development_id);

$process_ids = get_posts( array (
	'post_type'      => array( 'process' ),
	'posts_per_page' => '-1',
	'taxonomy'			 => 'process_role',
	'term' 					 => $process_role_product_development->slug,
	'order' 				 => 'ASC',
	'orderby' 			 => 'title',
  'fields'        => 'ids', // Only get post IDs
));

$processRelations = [];

foreach ($process_ids as $process_id) {
		$processRelations[] = array(
			'key' => 'process',
			'value' => '"'. $process_id. '"',
			'compare' => 'LIKE'
		);
	}
// The Query

$process_logs_product_development = get_posts(array(
	  'post_type' 			=> 'process_log',
		'posts_per_page'  => '-1',
		'meta_query' 			=> array(
			  array_merge(array('relation' => 'OR'), $processRelations)
			)
));

?>
<div class="clear"></div>

<h4>KPI Product Development <small>(Role - <?php echo $process_role_product_development->name; ?>)</small></h4>
<div class="activity-log-kpi-3">
	<?php

 if( $process_logs_product_development ): ?>

	<?php foreach( $process_logs_product_development as $process_log_product_development ):

	$desc = get_field('description', $process_log_product_development->ID); ?>
		<div class="process-star" data-log-date="<?php echo get_the_date( "U", $process_log_product_development->ID ); ?>">
		<i class="fa fa-star" aria-hidden="true">
		<span class="info">
			<a href="<?php echo get_edit_post_link( $process_log_product_development->ID ); ?>" class="alignright"><i class="fa fa-pencil-square-o" aria-hidden="true"></i></a>
			<?php echo get_the_author_meta("first_name", $process_log_product_development->post_author); ?> completed <br />
			<?php if ($desc) { ?>
			<div class="desc">
				<?php echo WPCom_Markdown::get_instance()->transform( $desc, array('id'=>false,'unslash'=>false)); ?>
			</div>
		<?php } ?>
			<?php if (get_field('website', $process_log_product_development->ID)) {
				$website = get_field('website', $process_log_product_development->ID);
				foreach( $website as $p ): // variable must NOT be called $post (IMPORTANT) ?>
				<i class="fa fa-link" aria-hidden="true"></i> <a href="<?php echo get_edit_post_link( $p ); ?>" class="website"><?php echo get_the_title( $p ); ?></a><br />
				<?php endforeach;
			} ?>

			<hr />
			<i class="fa fa-calendar" aria-hidden="true"></i>
			<?php echo get_the_date( "M j | g:ia", $process_log_product_development->ID ); ?>
		</span></i>
		</div>

	<?php endforeach; ?>
<?php endif; ?>
</div>
<?php

// Restore original Post Data
wp_reset_postdata();
?>

</div>
</div>

<div class="results clear">
	<h3>Totals</h3>
</div>
