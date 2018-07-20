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
</style>

<div class="wrap"><div id="icon-tools" class="icon32"></div>
<h2>Customer Report</h2>
<?php include "admin-submenu-tabs.php"; ?>
<div class="row metabox-holder">
<?php

$next_month = $date = date('m', strtotime('+1 month'));
$next_year = $date = date('Y', strtotime('+1 year'));

// WP_Query arguments
$args = array (
	'post_type'              => array( 'captcore_customer' ),
	'posts_per_page'         => '-1',

);

// The Query
$query = new WP_Query( $args );
$posts = $query->get_posts();

// The Loop
if ( $query->have_posts() ) {
	echo "<div class='websites'>";
	while ( $query->have_posts() ) {
		$query->the_post();
		$month = date('m', strtotime(get_field('billing_date')));
		if ($month < $next_month) {
			$monthyear = $next_year . $month;
		} else {
			$monthyear = date('Y') . $month;
		}
		?>
		<div class="website" data-renewal="<?php echo $monthyear; ?>" data-price="<?php the_field('total_price'); ?>" data-terms="<?php the_field('billing_terms'); ?>">
			<?php the_title(); ?> - $<?php the_field('total_price'); ?> <?php the_field('billing_terms'); ?>
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


// Loop through all monthlys terms and attach to the other groupings
jQuery('.websites .website[data-terms=month]').each(function(){
	renewal = jQuery(this).data('renewal');

	// gather months/years
	months = Object.keys(renewals);

	// loop through each month/years
	for (i in months) {

		// create new website element for each month
		new_cloned_website = jQuery(this).clone();

		// assign new month/year
		jQuery(new_cloned_website).attr('data-renewal', months[i]);

		current_month = months[i];
		if (renewal != current_month) {
			jQuery('.websites .website[data-renewal='+ months[i] +']:last').after(new_cloned_website);
		}
	}
});

// Loop through all quarterly terms and attach to the other groupings
jQuery('.websites .website[data-terms=quarter]').each(function(){
	renewal = jQuery(this).data('renewal');

	renewal_year = parseInt(renewal.toString().substring(0, 4));
	renewal_month = parseInt(renewal.toString().substring(4, 6));

	// form list of months which it will renew
		// gather months
		var renewal_months = []
		for (var i = 0; i < 4; i++) {
			calculated_month = renewal_month + (i * 3);
			if (calculated_month > 12) {
		   		renewal_months.push(renewal_month + (i * 3) - 12);
		   	} else {
		   		renewal_months.push(renewal_month + (i * 3));
		   	}
		}

	var renewal_months = [201512, 201603, 201606, 201609];

	// gather months/years
	months = [201512, 201603, 201606, 201609];

	// loop through each month/years
	for (i in months) {

		// create new website element for each month
		new_cloned_website = jQuery(this).clone();

		// assign new month/year
		jQuery(new_cloned_website).attr('data-renewal', months[i]);

		// lookup renewal
		found_renewal = jQuery.inArray(months[i], renewal_months);

		current_month = months[i];
		if (renewal != current_month) {
			jQuery('.websites').append(new_cloned_website);
		}
	}
});

jQuery(".website").sort(sort_li) // sort elements
                  .appendTo('.websites'); // append again to the list
// sort function callback
function sort_li(a, b){
    return (jQuery(b).data('renewal')) < (jQuery(a).data('renewal')) ? 1 : -1;
}

// print results
for(var key in renewals){


	var total_price = 0;
	jQuery('.websites .website[data-renewal='+ key +']').each(function() {
       price = jQuery(this).data('price');
       if (isNaN(price)) {
         price = 0;
       }
       if(typeof price === 'undefined'){
           price = 0;
       };
       if (price) {
       		total_price = total_price + price;
   		}
    });

    var date            = new Date(key.substring(0, 4), key.substring(4, 6) - 1, 1, 1, 1, 1, 1),
        timestamp       = date.getTime();

    var parsedDate = jQuery.format.date(timestamp, "MMM yyyy");

	jQuery('.websites .website[data-renewal='+ key +']:first').prepend('<div class="heading">'+ parsedDate + ' (' + renewals[key] + ')</div>');
	jQuery('.websites .website[data-renewal='+ key +']:last').after('<div class="total" data-total="'+total_price+'">Total: $'+ total_price +'</div>');

}

var yearly_total = 0;
jQuery('.total').each(function() {
     yearly_total = yearly_total + jQuery(this).data('total');
});

jQuery('#result').html("$"+yearly_total);



</script>
</div>
