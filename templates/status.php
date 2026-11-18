<?php
/**
 * The public status page (CaptainCore\StatusPage), plugin default.
 *
 * A theme replaces this by shipping captaincore/status.php. Either one gets
 * $captaincore_status from StatusPage::view(): ['view' => page | incident |
 * missing | unsubscribe, 'name', 'url', 'feed_url', 'status' => [indicator,
 * description], …]. All *_html values are already safe HTML; everything else
 * is plain text and must be escaped.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$v      = $GLOBALS['captaincore_status'];
$config = CaptainCore\Configurations::get();
$brand  = preg_match( '/^#[0-9a-f]{3,8}$/i', (string) ( $config->colors->primary ?? '' ) ) ? $config->colors->primary : '#123E8C';
$title  = $v['view'] === 'incident' ? $v['incident']['title'] . ' · ' . $v['name'] . ' status' : $v['name'] . ' status';

if ( ! function_exists( 'captaincore_status_incident_html' ) ) {
	function captaincore_status_incident_html( $i, $full = false ) {
		ob_start(); ?>
		<article class="inc inc-<?php echo esc_attr( $i['impact'] ); ?>">
			<h3><a href="<?php echo esc_url( $i['url'] ); ?>"><?php echo esc_html( $i['title'] ); ?></a></h3>
			<?php if ( $i['postmortem_html'] ) : ?>
				<div class="pm"><div class="lbl">Postmortem <span><?php echo esc_html( $i['postmortem_local'] ); ?></span></div><?php echo $i['postmortem_html']; ?></div>
			<?php endif; ?>
			<?php foreach ( $full ? $i['updates'] : array_slice( $i['updates'], 0, 1 ) as $u ) : ?>
				<div class="upd"><div class="lbl"><?php echo esc_html( $u['status_label'] ); ?> <span><?php echo esc_html( $u['created_local'] ); ?></span></div><?php echo $u['body_html']; ?></div>
			<?php endforeach; ?>
			<?php if ( ! $full && count( $i['updates'] ) > 1 ) : ?>
				<a class="more" href="<?php echo esc_url( $i['url'] ); ?>"><?php echo count( $i['updates'] ); ?> updates · <?php echo esc_html( $i['duration'] ); ?> →</a>
			<?php endif; ?>
		</article>
		<?php return ob_get_clean();
	}
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $title ); ?></title>
<link rel="alternate" type="application/atom+xml" title="<?php echo esc_attr( $v['name'] ); ?> status" href="<?php echo esc_url( $v['feed_url'] ); ?>">
<style>
:root{--brand:<?php echo esc_html( $brand ); ?>;--bg:#f6f7f9;--paper:#fff;--ink:#15181d;--dim:#5d6470;--rule:#e3e7ee;--ok:#1f8f55;--minor:#c48a00;--major:#d9620b;--critical:#c62828;--maint:#5b5bd6}
@media (prefers-color-scheme:dark){:root{--bg:#0f1115;--paper:#171a20;--ink:#eef0f3;--dim:#9aa3b0;--rule:#272c35;--ok:#3ccf83;--minor:#e6b434;--major:#f08a3c;--critical:#f05454;--maint:#8f8ff0}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
a{color:var(--brand)}@media (prefers-color-scheme:dark){a{color:color-mix(in srgb,var(--brand) 55%,#fff)}}
.wrap{max-width:780px;margin:0 auto;padding:40px 16px 80px}
header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:28px}
header .name{font-weight:800;font-size:20px;color:var(--ink);text-decoration:none}header .sub{font-size:14px}
.banner{display:flex;align-items:center;gap:12px;padding:18px 20px;border-radius:12px;color:#fff;font-weight:700;font-size:18px;background:var(--ok)}
.banner.minor{background:var(--minor)}.banner.major{background:var(--major)}.banner.critical{background:var(--critical)}.banner.maintenance{background:var(--maint)}
h2{font-size:13px;letter-spacing:.07em;text-transform:uppercase;color:var(--dim);margin:40px 0 12px}
.inc{background:var(--paper);border:1px solid var(--rule);border-left:4px solid var(--rule);border-radius:10px;padding:16px 20px;margin-bottom:12px}
.inc-minor{border-left-color:var(--minor)}.inc-major{border-left-color:var(--major)}.inc-critical{border-left-color:var(--critical)}.inc-maintenance{border-left-color:var(--maint)}
.inc h3{margin:0 0 8px;font-size:17px}.inc h3 a{color:var(--ink);text-decoration:none}
.lbl{font-weight:700;font-size:14px}.lbl span{font-weight:400;color:var(--dim);margin-left:6px}
.upd,.pm{margin-top:10px}.upd p,.pm p{margin:4px 0 0;color:var(--dim)}.pm{padding:12px 14px;background:var(--bg);border-radius:8px}
.more{display:inline-block;margin-top:8px;font-size:14px;text-decoration:none}
.month{font-weight:700;margin:28px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--rule)}
.empty{color:var(--dim);font-size:15px;margin:0 0 8px}
.pager{display:flex;justify-content:space-between;margin-top:28px;font-size:15px}
.box{background:var(--paper);border:1px solid var(--rule);border-radius:12px;padding:24px}
button{background:var(--brand);color:#fff;border:0;border-radius:8px;padding:10px 18px;font:600 15px inherit;cursor:pointer}
footer{margin-top:48px;font-size:14px;color:var(--dim);display:flex;gap:16px;flex-wrap:wrap}
</style>
</head>
<body>
<div class="wrap">
	<header>
		<a class="name" href="<?php echo esc_url( $v['url'] ); ?>"><?php echo esc_html( $v['name'] ); ?> status</a>
		<a class="sub" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?> →</a>
	</header>

<?php if ( $v['view'] === 'page' ) : ?>
	<div class="banner <?php echo esc_attr( $v['status']['indicator'] ); ?>"><?php echo esc_html( $v['status']['description'] ); ?></div>

	<?php if ( $v['active'] ) : ?>
		<h2>Active</h2>
		<?php foreach ( $v['active'] as $i ) { echo captaincore_status_incident_html( $i, true ); } ?>
	<?php endif; ?>

	<?php if ( $v['upcoming'] ) : ?>
		<h2>Scheduled maintenance</h2>
		<?php foreach ( $v['upcoming'] as $i ) { echo captaincore_status_incident_html( $i, true ); } ?>
	<?php endif; ?>

	<h2>Past incidents</h2>
	<?php
	$month = '';
	foreach ( $v['history'] as $i ) {
		$m = wp_date( 'F Y', strtotime( $i['started_at'] ) );
		if ( $m !== $month ) {
			echo '<div class="month">' . esc_html( $m ) . '</div>';
			$month = $m;
		}
		echo captaincore_status_incident_html( $i );
	}
	if ( ! $v['history'] ) {
		echo '<p class="empty">No incidents reported.</p>';
	}
	?>
	<div class="pager">
		<span><?php if ( $v['page'] > 1 ) : ?><a href="<?php echo esc_url( $v['page'] === 2 ? $v['url'] : $v['url'] . 'page/' . ( $v['page'] - 1 ) . '/' ); ?>">← Newer</a><?php endif; ?></span>
		<span><?php if ( $v['page'] < $v['pages'] ) : ?><a href="<?php echo esc_url( $v['url'] . 'page/' . ( $v['page'] + 1 ) . '/' ); ?>">Older →</a><?php endif; ?></span>
	</div>

<?php elseif ( $v['view'] === 'incident' ) : ?>
	<p><a href="<?php echo esc_url( $v['url'] ); ?>">← All incidents</a></p>
	<?php echo captaincore_status_incident_html( $v['incident'], true ); ?>
	<p class="empty">
		<?php echo esc_html( $v['incident']['impact_label'] ); ?> ·
		<?php echo esc_html( $v['incident']['kind'] === 'maintenance' && $v['incident']['scheduled_for_local'] ? 'Scheduled ' . $v['incident']['scheduled_for_local'] : 'Started ' . $v['incident']['started_local'] ); ?>
		<?php if ( $v['incident']['resolved_local'] ) { echo ' · Resolved ' . esc_html( $v['incident']['resolved_local'] ); } ?>
	</p>

<?php elseif ( $v['view'] === 'unsubscribe' ) : ?>
	<div class="box">
		<?php if ( ! $v['valid'] ) : ?>
			<p>This unsubscribe link is not valid. Signed-in customers can turn status alerts off in their profile.</p>
		<?php elseif ( $v['active'] ) : ?>
			<p>Stop status alert emails to <strong><?php echo esc_html( $v['email'] ); ?></strong>?</p>
			<form method="post" action="<?php echo esc_url( $v['action'] ); ?>"><button type="submit">Unsubscribe</button></form>
		<?php else : ?>
			<p><strong><?php echo esc_html( $v['email'] ); ?></strong> will no longer get status alert emails.</p>
			<form method="post" action="<?php echo esc_url( $v['action'] ); ?>"><input type="hidden" name="resubscribe" value="1"><button type="submit">Subscribe again</button></form>
		<?php endif; ?>
	</div>

<?php else : ?>
	<div class="box"><p>That incident could not be found. <a href="<?php echo esc_url( $v['url'] ); ?>">See all incidents</a>.</p></div>
<?php endif; ?>

	<footer>
		<a href="<?php echo esc_url( $v['feed_url'] ); ?>">Atom feed</a>
		<span>Customers can turn on email alerts in their account profile.</span>
	</footer>
</div>
</body>
</html>
