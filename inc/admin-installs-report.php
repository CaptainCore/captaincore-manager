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
<div class="wrap"><div id="icon-tools" class="icon32"></div>
<h2>Partners Install Report</h2>
<?php

// WP_Query arguments
$args = array (
	'post_type'              => array( 'customer' ),
	'posts_per_page'         => '-1',
	'meta_query' => array(
		array(
			'key' => 'partner', // name of custom field
			'value' => true, // matches exaclty "123", not just 123. This prevents a match for "1234"
			'compare' => 'LIKE'
		)
	)

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
		?>
		<div class="partner">
			<?php the_title(); ?> - ID# <?php echo get_the_ID(); ?>
			<?php 
			/*
			*  Query posts for a relationship value.
			*  This method uses the meta_query LIKE to match the string "123" to the database value a:1:{i:0;s:3:"123";} (serialized array)
			*/

			$websites = get_posts(array(
				'post_type' => 'website',
			    'posts_per_page'         => '-1',
			    'meta_query' => array(
		    	  'relation' => 'AND', // Optional, defaults to "AND"
					array(
						'key' => 'partner', // name of custom field
						'value' => '"' . $id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE'
					),
					array(
						'key' => 'status', // name of custom field
						'value' => 'active', // matches exaclty "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE'
					),
				)
			));
			$customer_count = 0;
			?>
			<?php if( $websites ): ?>
				
				<?php 

				// New array to collect customers IDs
				$customer_ids = array(); 

				// Loop through websites
				echo "<ul class='website-list'><li>";
				foreach( $websites as $website ): 
					$domain = get_the_title( $website->ID );
					$customer_id = get_field('customer', $website->ID);

					if (get_field('install',$website->ID)) {
						the_field('install', $website->ID ); ?> <?php					
					}

				endforeach; 
				echo "</li></ul>";

				echo "[";

				$i = 1;

				foreach( $websites as $website ): 

					if (get_field('install',$website->ID)) {

						if ($i < count($websites)) {
							echo '"'.get_the_title($website->ID).'",';
						} else {
							echo '"'.get_the_title($website->ID).'"';
						}

					}
					
					$i++;
					 
				endforeach; 

				echo "]<p></p>";

				echo "<pre>";

				foreach( $websites as $website ): 


					if (get_field('install',$website->ID)) {

					$install = get_field('install',$website->ID);
					$domain = get_the_title($website->ID);
					$address = get_field('address',$website->ID);
					$username = get_field('username',$website->ID);
					$password = get_field('password',$website->ID);
					$protocol = get_field('protocol',$website->ID);
					$port = get_field('port',$website->ID);
					$token = "***REMOVED***";

					echo "php Sites/backup.anchor.host/api/new.php install=$install domain=$domain username=$username password=".rawurlencode(base64_encode($password))." address=$address protocol=$protocol port=$port preloadusers=$preloadusers token=$token skip=true
";
					}
				endforeach; 

				echo "</pre>";
				
				
			// End partner loop
			endif; ?>
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
<div id="result"></div>

<script>

var today = new Date();

// current year
year = today.getFullYear();

// next month
month = ("0" + (today.getMonth() + 1)).slice(-2);

var renewals = {},
    renewal;

// loop through and build renewals object to store upcoming months
for (var i = 1; i < 13; i++) {
   loop_month = ("0" + (i)).slice(-2);;
   next_year = year + 1;
   if (parseInt(month) < i) {
   	renewals[year + loop_month] = 0;
   } else {
   	renewals[next_year + loop_month] = 0;
   }
}

// Totals up the renewals
jQuery('.websites .website[data-renewal]').each(function(i, el){
    renewal = jQuery(el).data('renewal');
    price = jQuery(el).data('price');
    if (renewals.hasOwnProperty(renewal)) {
        renewals[renewal] += 1;
    }
    else {
        renewals[renewal] = 1;
    }
});

/*! jquery-dateFormat 18-05-2015 */
var DateFormat={};!function(a){var b=["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"],c=["Sun","Mon","Tue","Wed","Thu","Fri","Sat"],d=["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],e=["January","February","March","April","May","June","July","August","September","October","November","December"],f={Jan:"01",Feb:"02",Mar:"03",Apr:"04",May:"05",Jun:"06",Jul:"07",Aug:"08",Sep:"09",Oct:"10",Nov:"11",Dec:"12"},g=/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.?\d{0,3}[Z\-+]?(\d{2}:?\d{2})?/;a.format=function(){function a(a){return b[parseInt(a,10)]||a}function h(a){return c[parseInt(a,10)]||a}function i(a){var b=parseInt(a,10)-1;return d[b]||a}function j(a){var b=parseInt(a,10)-1;return e[b]||a}function k(a){return f[a]||a}function l(a){var b,c,d,e,f,g=a,h="";return-1!==g.indexOf(".")&&(e=g.split("."),g=e[0],h=e[e.length-1]),f=g.split(":"),3===f.length?(b=f[0],c=f[1],d=f[2].replace(/\s.+/,"").replace(/[a-z]/gi,""),g=g.replace(/\s.+/,"").replace(/[a-z]/gi,""),{time:g,hour:b,minute:c,second:d,millis:h}):{time:"",hour:"",minute:"",second:"",millis:""}}function m(a,b){for(var c=b-String(a).length,d=0;c>d;d++)a="0"+a;return a}return{parseDate:function(a){var b,c,d={date:null,year:null,month:null,dayOfMonth:null,dayOfWeek:null,time:null};if("number"==typeof a)return this.parseDate(new Date(a));if("function"==typeof a.getFullYear)d.year=String(a.getFullYear()),d.month=String(a.getMonth()+1),d.dayOfMonth=String(a.getDate()),d.time=l(a.toTimeString()+"."+a.getMilliseconds());else if(-1!=a.search(g))b=a.split(/[T\+-]/),d.year=b[0],d.month=b[1],d.dayOfMonth=b[2],d.time=l(b[3].split(".")[0]);else switch(b=a.split(" "),6===b.length&&isNaN(b[5])&&(b[b.length]="()"),b.length){case 6:d.year=b[5],d.month=k(b[1]),d.dayOfMonth=b[2],d.time=l(b[3]);break;case 2:c=b[0].split("-"),d.year=c[0],d.month=c[1],d.dayOfMonth=c[2],d.time=l(b[1]);break;case 7:case 9:case 10:d.year=b[3],d.month=k(b[1]),d.dayOfMonth=b[2],d.time=l(b[4]);break;case 1:c=b[0].split(""),d.year=c[0]+c[1]+c[2]+c[3],d.month=c[5]+c[6],d.dayOfMonth=c[8]+c[9],d.time=l(c[13]+c[14]+c[15]+c[16]+c[17]+c[18]+c[19]+c[20]);break;default:return null}return d.date=d.time?new Date(d.year,d.month-1,d.dayOfMonth,d.time.hour,d.time.minute,d.time.second,d.time.millis):new Date(d.year,d.month-1,d.dayOfMonth),d.dayOfWeek=String(d.date.getDay()),d},date:function(b,c){try{var d=this.parseDate(b);if(null===d)return b;for(var e,f=d.year,g=d.month,k=d.dayOfMonth,l=d.dayOfWeek,n=d.time,o="",p="",q="",r=!1,s=0;s<c.length;s++){var t=c.charAt(s),u=c.charAt(s+1);if(r)"'"==t?(p+=""===o?"'":o,o="",r=!1):o+=t;else switch(o+=t,q="",o){case"ddd":p+=a(l),o="";break;case"dd":if("d"===u)break;p+=m(k,2),o="";break;case"d":if("d"===u)break;p+=parseInt(k,10),o="";break;case"D":k=1==k||21==k||31==k?parseInt(k,10)+"st":2==k||22==k?parseInt(k,10)+"nd":3==k||23==k?parseInt(k,10)+"rd":parseInt(k,10)+"th",p+=k,o="";break;case"MMMM":p+=j(g),o="";break;case"MMM":if("M"===u)break;p+=i(g),o="";break;case"MM":if("M"===u)break;p+=m(g,2),o="";break;case"M":if("M"===u)break;p+=parseInt(g,10),o="";break;case"y":case"yyy":if("y"===u)break;p+=o,o="";break;case"yy":if("y"===u)break;p+=String(f).slice(-2),o="";break;case"yyyy":p+=f,o="";break;case"HH":p+=m(n.hour,2),o="";break;case"H":if("H"===u)break;p+=parseInt(n.hour,10),o="";break;case"hh":e=0===parseInt(n.hour,10)?12:n.hour<13?n.hour:n.hour-12,p+=m(e,2),o="";break;case"h":if("h"===u)break;e=0===parseInt(n.hour,10)?12:n.hour<13?n.hour:n.hour-12,p+=parseInt(e,10),o="";break;case"mm":p+=m(n.minute,2),o="";break;case"m":if("m"===u)break;p+=n.minute,o="";break;case"ss":p+=m(n.second.substring(0,2),2),o="";break;case"s":if("s"===u)break;p+=n.second,o="";break;case"S":case"SS":if("S"===u)break;p+=o,o="";break;case"SSS":var v="000"+n.millis.substring(0,3);p+=v.substring(v.length-3),o="";break;case"a":p+=n.hour>=12?"PM":"AM",o="";break;case"p":p+=n.hour>=12?"p.m.":"a.m.",o="";break;case"E":p+=h(l),o="";break;case"'":o="",r=!0;break;default:p+=t,o=""}}return p+=q}catch(w){return console&&console.log&&console.log(w),b}},prettyDate:function(a){var b,c,d;return("string"==typeof a||"number"==typeof a)&&(b=new Date(a)),"object"==typeof a&&(b=new Date(a.toString())),c=((new Date).getTime()-b.getTime())/1e3,d=Math.floor(c/86400),isNaN(d)||0>d?void 0:60>c?"just now":120>c?"1 minute ago":3600>c?Math.floor(c/60)+" minutes ago":7200>c?"1 hour ago":86400>c?Math.floor(c/3600)+" hours ago":1===d?"Yesterday":7>d?d+" days ago":31>d?Math.ceil(d/7)+" weeks ago":d>=31?"more than 5 weeks ago":void 0},toBrowserTimeZone:function(a,b){return this.date(new Date(a),b||"MM/dd/yyyy HH:mm:ss")}}}()}(DateFormat),function(a){a.format=DateFormat.format}(jQuery);


jQuery(".website").sort(sort_li) // sort elements
                  .appendTo('.websites'); // append again to the list
// sort function callback
function sort_li(a, b){
    return (jQuery(b).data('renewal')) < (jQuery(a).data('renewal')) ? 1 : -1;    
}

</script>
