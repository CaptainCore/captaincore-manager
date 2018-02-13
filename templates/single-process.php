<?php
/**
 * The Template for displaying all single posts.
 *
 * @package swell
 */

if (!is_user_logged_in() ) {
	wp_redirect ( home_url("/my-account/") );
	exit;
} else {
	$get_user_id = get_current_user_id(); // get user ID
    $get_user_data = get_userdata($get_user_id); // get user data
    $get_roles = implode($get_user_data->roles);
    if( "administrator" != $get_roles ){ // check if role name == user role
        wp_redirect ( home_url("/my-account/") );
        exit;
    }
}
acf_form_head();
get_header();
jetpack_require_lib( 'markdown' );

$id = get_the_ID();
?>
<script>
/*! jquery-dateFormat 18-05-2015 */
var DateFormat={};!function(a){var b=["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"],c=["Sun","Mon","Tue","Wed","Thu","Fri","Sat"],d=["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],e=["January","February","March","April","May","June","July","August","September","October","November","December"],f={Jan:"01",Feb:"02",Mar:"03",Apr:"04",May:"05",Jun:"06",Jul:"07",Aug:"08",Sep:"09",Oct:"10",Nov:"11",Dec:"12"},g=/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.?\d{0,3}[Z\-+]?(\d{2}:?\d{2})?/;a.format=function(){function a(a){return b[parseInt(a,10)]||a}function h(a){return c[parseInt(a,10)]||a}function i(a){var b=parseInt(a,10)-1;return d[b]||a}function j(a){var b=parseInt(a,10)-1;return e[b]||a}function k(a){return f[a]||a}function l(a){var b,c,d,e,f,g=a,h="";return-1!==g.indexOf(".")&&(e=g.split("."),g=e[0],h=e[e.length-1]),f=g.split(":"),3===f.length?(b=f[0],c=f[1],d=f[2].replace(/\s.+/,"").replace(/[a-z]/gi,""),g=g.replace(/\s.+/,"").replace(/[a-z]/gi,""),{time:g,hour:b,minute:c,second:d,millis:h}):{time:"",hour:"",minute:"",second:"",millis:""}}function m(a,b){for(var c=b-String(a).length,d=0;c>d;d++)a="0"+a;return a}return{parseDate:function(a){var b,c,d={date:null,year:null,month:null,dayOfMonth:null,dayOfWeek:null,time:null};if("number"==typeof a)return this.parseDate(new Date(a));if("function"==typeof a.getFullYear)d.year=String(a.getFullYear()),d.month=String(a.getMonth()+1),d.dayOfMonth=String(a.getDate()),d.time=l(a.toTimeString()+"."+a.getMilliseconds());else if(-1!=a.search(g))b=a.split(/[T\+-]/),d.year=b[0],d.month=b[1],d.dayOfMonth=b[2],d.time=l(b[3].split(".")[0]);else switch(b=a.split(" "),6===b.length&&isNaN(b[5])&&(b[b.length]="()"),b.length){case 6:d.year=b[5],d.month=k(b[1]),d.dayOfMonth=b[2],d.time=l(b[3]);break;case 2:c=b[0].split("-"),d.year=c[0],d.month=c[1],d.dayOfMonth=c[2],d.time=l(b[1]);break;case 7:case 9:case 10:d.year=b[3],d.month=k(b[1]),d.dayOfMonth=b[2],d.time=l(b[4]);break;case 1:c=b[0].split(""),d.year=c[0]+c[1]+c[2]+c[3],d.month=c[5]+c[6],d.dayOfMonth=c[8]+c[9],d.time=l(c[13]+c[14]+c[15]+c[16]+c[17]+c[18]+c[19]+c[20]);break;default:return null}return d.date=d.time?new Date(d.year,d.month-1,d.dayOfMonth,d.time.hour,d.time.minute,d.time.second,d.time.millis):new Date(d.year,d.month-1,d.dayOfMonth),d.dayOfWeek=String(d.date.getDay()),d},date:function(b,c){try{var d=this.parseDate(b);if(null===d)return b;for(var e,f=d.year,g=d.month,k=d.dayOfMonth,l=d.dayOfWeek,n=d.time,o="",p="",q="",r=!1,s=0;s<c.length;s++){var t=c.charAt(s),u=c.charAt(s+1);if(r)"'"==t?(p+=""===o?"'":o,o="",r=!1):o+=t;else switch(o+=t,q="",o){case"ddd":p+=a(l),o="";break;case"dd":if("d"===u)break;p+=m(k,2),o="";break;case"d":if("d"===u)break;p+=parseInt(k,10),o="";break;case"D":k=1==k||21==k||31==k?parseInt(k,10)+"st":2==k||22==k?parseInt(k,10)+"nd":3==k||23==k?parseInt(k,10)+"rd":parseInt(k,10)+"th",p+=k,o="";break;case"MMMM":p+=j(g),o="";break;case"MMM":if("M"===u)break;p+=i(g),o="";break;case"MM":if("M"===u)break;p+=m(g,2),o="";break;case"M":if("M"===u)break;p+=parseInt(g,10),o="";break;case"y":case"yyy":if("y"===u)break;p+=o,o="";break;case"yy":if("y"===u)break;p+=String(f).slice(-2),o="";break;case"yyyy":p+=f,o="";break;case"HH":p+=m(n.hour,2),o="";break;case"H":if("H"===u)break;p+=parseInt(n.hour,10),o="";break;case"hh":e=0===parseInt(n.hour,10)?12:n.hour<13?n.hour:n.hour-12,p+=m(e,2),o="";break;case"h":if("h"===u)break;e=0===parseInt(n.hour,10)?12:n.hour<13?n.hour:n.hour-12,p+=parseInt(e,10),o="";break;case"mm":p+=m(n.minute,2),o="";break;case"m":if("m"===u)break;p+=n.minute,o="";break;case"ss":p+=m(n.second.substring(0,2),2),o="";break;case"s":if("s"===u)break;p+=n.second,o="";break;case"S":case"SS":if("S"===u)break;p+=o,o="";break;case"SSS":var v="000"+n.millis.substring(0,3);p+=v.substring(v.length-3),o="";break;case"a":p+=n.hour>=12?"PM":"AM",o="";break;case"p":p+=n.hour>=12?"p.m.":"a.m.",o="";break;case"E":p+=h(l),o="";break;case"'":o="",r=!0;break;default:p+=t,o=""}}return p+=q}catch(w){return console&&console.log&&console.log(w),b}},prettyDate:function(a){var b,c,d;return("string"==typeof a||"number"==typeof a)&&(b=new Date(a)),"object"==typeof a&&(b=new Date(a.toString())),c=((new Date).getTime()-b.getTime())/1e3,d=Math.floor(c/86400),isNaN(d)||0>d?void 0:60>c?"just now":120>c?"1 minute ago":3600>c?Math.floor(c/60)+" minutes ago":7200>c?"1 hour ago":86400>c?Math.floor(c/3600)+" hours ago":1===d?"Yesterday":7>d?d+" days ago":31>d?Math.ceil(d/7)+" weeks ago":d>=31?"more than 5 weeks ago":void 0},toBrowserTimeZone:function(a,b){return this.date(new Date(a),b||"MM/dd/yyyy HH:mm:ss")}}}()}(DateFormat),function(a){a.format=DateFormat.format}(jQuery);
var months = {};
<?php echo 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";'; ?>
jQuery(document).ready(function(){

  jQuery('.process-log-update').append('<a href="/company-handbook/" class="close">Back to Company Handbook <i class="fas fa-undo"></i></a>');

  jQuery("#log_process").click(function(e){
	e.preventDefault();
	var data = {
		'action': 'log_process',
		'post_id': <?php echo $id; ?>
	};

	// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
	jQuery.post(ajaxurl, data, function(response) {
		response = JSON.parse(response);
		url = response.redirect_url + "?logged=" + response.process_id;
		top.location.replace(url);
	});
  });


	jQuery('.process-star').each(function() {

		udate = jQuery(this).data("log-date");
		month = new Date(udate * 1000).getUTCMonth() + 1;
		year = new Date(udate * 1000).getUTCFullYear();
		yearmonth = [year] + [month];

		jQuery(this).attr('data-yearmonth', yearmonth);

		if (months.hasOwnProperty(yearmonth)) {
		    months[yearmonth] += 1;
		}
		else {
		    months[yearmonth] = 1;
		}

	});


	// print results
	for(var key in months){

	    var date            = new Date(key.substring(0, 4), key.substring(4, 6) - 1, 1, 1, 1, 1, 1),
	        timestamp       = date.getTime();

	    var parsedDate = jQuery.format.date(timestamp, "MMM yyyy");

	    jQuery('.process-star[data-yearmonth='+ key +']:first').before('<div id="result-'+key+'" class="process-stars">');
	    items = jQuery('.process-star[data-yearmonth='+ key +']').detach();
	    items.appendTo( "#result-"+key );

		jQuery("#result-"+key).prepend('<div class="heading">'+ parsedDate + ' <span>' + months[key] + '</span></div>');

	}


	// calculate and print site count
	jQuery('.process-stars').each(function() {
		site_count = jQuery(this).find('.process-star span.info a.website').length;
	    heading = jQuery(this).find('.heading');
	    if (site_count > 0) {
	    	jQuery( '<span class="site-count">' + site_count + ' sites</span>' ).appendTo( heading );
	    }
	});

});
</script>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
		<?php if (isset($_GET['logged'])) { ?>
		<div class="process-log-update">


			<?php
			$options = array(

				/* (string) Unique identifier for the form. Defaults to 'acf-form' */
				'id' => 'acf-form',

				/* (int|string) The post ID to load data from and save data to. Defaults to the current post ID.
				Can also be set to 'new_post' to create a new post on submit */
				'post_id' => $_GET['logged'],

				/* (array) An array of field group IDs/keys to override the fields displayed in this form */
				//'field_groups' => false,

				/* (array) An array of field IDs/keys to override the fields displayed in this form */
				'fields' => array( 'field_57fc396b04e0a', 'field_57fae6d263704' ),

				/* (boolean) Whether or not to show the post title text field. Defaults to false */
				'post_title' => false,

				/* (boolean) Whether or not to show the post content editor field. Defaults to false */
				'post_content' => false,

				/* (boolean) Whether or not to create a form element. Useful when a adding to an existing form. Defaults to true */
				'form' => true,

				/* (array) An array or HTML attributes for the form element */
				//'form_attributes' => array(),

				/* (string) The URL to be redirected to after the form is submit. Defaults to the current URL with a GET parameter '?updated=true'.
				A special placeholder '%post_url%' will be converted to post's permalink (handy if creating a new post) */
				'return' => strtok($_SERVER["REQUEST_URI"],'?'),

				/* (string) Extra HTML to add before the fields */
				'html_before_fields' => '',

				/* (string) Extra HTML to add after the fields */
				'html_after_fields' => '',

				/* (string) The text displayed on the submit button */
				'submit_value' => __("Update", 'acf'),

				/* (string) A message displayed above the form after being redirected. Can also be set to false for no message */
				'updated_message' => __("Post updated", 'acf'),

				/* (string) Determines where field labels are places in relation to fields. Defaults to 'top'.
				Choices of 'top' (Above fields) or 'left' (Beside fields) */
				'label_placement' => 'top',

				/* (string) Determines where field instructions are places in relation to fields. Defaults to 'label'.
				Choices of 'label' (Below labels) or 'field' (Below fields) */
				'instruction_placement' => 'label',

				/* (string) Determines element used to wrap a field. Defaults to 'div'
				Choices of 'div', 'tr', 'td', 'ul', 'ol', 'dl' */
				'field_el' => 'div',

				/* (string) Whether to use the WP uploader or a basic input for image and file fields. Defaults to 'wp'
				Choices of 'wp' or 'basic'. Added in v5.2.4 */
				'uploader' => 'wp',

				/* (boolean) Whether to include a hidden input field to capture non human form submission. Defaults to true. Added in v5.3.4 */
				#'honeypot' => true

			);
			acf_form( $options );

			$process_log_id = $_GET['logged'];

// 		Unfinished sub item processes
// 		see (single-process_dev)

			if( have_rows('checklist', $process_log_id) ):

				echo "<ul>";

			    while ( have_rows('checklist', $process_log_id) ) : the_row(); $i++; ?>

					<li data-id="<?php echo $i;?>"><?php the_sub_field('item'); ?></li>

			    <?php

			    endwhile;

			    echo "</ul>";

			else :

			    // no rows found

			endif;

			?>
		</div>
		<?php } ?>

		<?php while ( have_posts() ) : the_post(); ?>

			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<?php
				$featured_image = "";
				$c = "";
				if (is_single()) {
					if( has_post_thumbnail() ) {
						$featured_image = wp_get_attachment_image_src( get_post_thumbnail_id($post->ID), 'swell_full_width' );
						$c = "has-background";
					}
				}
				?>

				<header class="main entry-header <?php echo $c; ?>">

					<?php
						/* translators: used between list items, there is a space after the comma */
						$categories_list = get_the_category_list( __( ', ', 'swell' ) );
						if ( $categories_list && swell_categorized_blog() ) :
					?>
					<span class="meta category">
						<?php echo $categories_list; ?>
					</span>
					<?php endif; // End if categories ?>

					<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>

					<hr class="short" />

					<span class="meta date-author">
						<?php swell_posted_on(); swell_posted_by(); ?>
					</span><!-- .entry-meta -->

					<?php
					$field = get_field_object('repeat');
					$value = get_field('repeat');
					$repeat = $field['choices'][ $value ];
					?>
					<div class="process-icons">
					<i class="far fa-clock"></i> <?php the_field('time_estimate'); ?>
					<i class="fas fa-redo"></i> <?php echo $repeat; ?>
					<?php if (get_field('repeat_quantity') and get_field('repeat_quantity') > 1) { ?><i class="fas fa-retweet"></i> <?php the_field('repeat_quantity'); ?> times<?php } ?>
					</div>
						<?php // get_template_part( 'content-post-thumb' ); ?>
						<span class="overlay"></span>
				</header><!-- .entry-header -->

				<div class="body-wrap">
				<div class="entry-content">
					<?php the_content(); ?>
					<a name="activity"></a>
					<hr />
					<div class="activity-log">
					<a href="#" class="alignright button" id="log_process">Log Completion</a>
					<h4>Activity Log</h4>

					<?php

					date_default_timezone_set('New_York');

					$id = get_the_ID();

					/*
					*  Query posts for a relationship value.
					*  This method uses the meta_query LIKE to match the string "123" to the database value a:1:{i:0;s:3:"123";} (serialized array)
					*/

					$process_logs = get_posts(array(
						'post_type' => 'process_log',
					    'posts_per_page'         => '-1',
					    'meta_query' => array(
							array(
								'key' => 'process', // name of custom field
								'value' => '"' . $id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
								'compare' => 'LIKE'
							)
						)
					));
					?>
					<?php if( $process_logs ): ?>

						<?php foreach( $process_logs as $process_log ):

						$desc = get_field('description', $process_log->ID); ?>
							<div class="process-star" data-log-date="<?php echo get_the_date( "U", $process_log->ID ); ?>">
							<div class="tooltip">
							<i class="fas fa-star"></i>
							<span class="info">
								<a href="<?php echo get_edit_post_link( $process_log->ID ); ?>" class="alignright"><i class="fas fa-edit"></i></a>
								<?php echo get_the_author_meta("first_name", $process_log->post_author); ?> completed <br />
								<?php if ($desc) { ?>
								<div class="desc">
									<?php echo WPCom_Markdown::get_instance()->transform( $desc, array('id'=>false,'unslash'=>false)); ?>
								</div>
							<?php } ?>
								<?php if (get_field('website', $process_log->ID)) {
									$website = get_field('website', $process_log->ID);
									foreach( $website as $p ): // variable must NOT be called $post (IMPORTANT) ?>
									<i class="fas fa-link"></i> <a href="<?php echo get_edit_post_link( $p ); ?>" class="website"><?php echo get_the_title( $p ); ?></a><br />
									<?php endforeach;
								} ?>

								<hr />
								<i class="fas fa-calendar"></i>
								<?php echo get_the_date( "M j | g:ia", $process_log->ID ); ?>
							</span></div>
							</div>

						<?php endforeach; ?>
					<?php endif; ?>
					</div>

				</div><!-- .entry-content -->
				</div><!-- .body-wrap -->


			</article><!-- #post-## -->
			<nav class="navigation post-navigation" role="navigation">
				<h1 class="screen-reader-text">Post navigation</h1>
				<div class="nav-links">
					<div class="nav-previous"><a href="<?php echo home_url("/company-handbook/"); ?>" rel="prev">Company Handbook <span class="meta-nav">→</span></a></div>			</div><!-- .nav-links -->
			</nav>

			<?php if ( comments_open() || '0' != get_comments_number() ) : // If comments are open or we have at least one comment, load up the comment template?>
					<div class="comments-wrap">
						<?php comments_template(); ?>
					</div>
			<?php endif; ?>



		<?php endwhile; // end of the loop. ?>

		</main><!-- #main -->
	</div><!-- #primary -->
<?php get_sidebar(); ?>
<?php get_footer(); ?>
