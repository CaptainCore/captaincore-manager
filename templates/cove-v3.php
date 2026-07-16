<?php
/**
 * CaptainCore v3 ("Cove") — ground-up UI rebuild. Served behind ?ui=v3
 * (see CaptainCore\Router::load_template()).
 *
 * Source of truth lives in templates/cove-v3/:
 *   app.html  — the DC template markup (visual design)
 *   app.js    — class Component extends DCLogic (application logic)
 *   data.js   — Component.prototype mixin: REST data layer / hydration
 * Additional mixins concatenate into the same script tag below; order matters
 * (app.js defines Component, mixins extend it).
 *
 * Originally forked from the Claude Design project "Anchor Hosting UI Revamp"
 * (Anchor Home.dc.html, project aa0b3f96). The design project remains the
 * visual reference; this directory is the code.
 *
 * Runtime: public/js/v3/support.js (Design Components runtime) + React 18 UMD.
 * Real data enters via window.CC_BOOT + the data.js hydration mixin; sample
 * data from the design remains as fallback when a fetch fails.
 */

$configurations = ( new CaptainCore\Configurations )->get();
$config_path    = '/' . trim( (string) $configurations->path, '/' );
$config_path    = $config_path === '/' ? '/' : $config_path . '/';

if ( ! is_user_logged_in() ) {
    wp_safe_redirect( home_url( $config_path . 'login' ) );
    exit;
}

$user       = ( new CaptainCore\User )->profile();
$colors     = CaptainCore\Configurations::colors();
$plugin_url = plugin_dir_url( __DIR__ );
$v3_dir     = __DIR__ . '/cove-v3';
$first_name = ! empty( $user->first_name ) ? $user->first_name : strtok( (string) $user->display_name, ' ' );

$cc_boot = [
    'nonce'           => wp_create_nonce( 'wp_rest' ),
    'restRoot'        => esc_url_raw( rest_url() ),
    'role'            => $user->role,
    'dcRole'          => $user->role === 'administrator' ? 'operator' : 'customer',
    'userFirstName'   => $first_name,
    'userDisplayName' => $user->display_name,
    'userEmail'       => $user->email,
    'brandColor'      => ! empty( $colors->primary ) ? $colors->primary : '#3b82c4',
    'name'            => $configurations->name,
    'path'            => $config_path,
    'homeLink'        => home_url(),
    'loginUrl'        => home_url( $config_path . 'login' ),
    'socket'          => captaincore_fetch_socket_address() . '/ws',
];

$v3_scripts = [ 'app.js', 'data.js', 'home.js', 'jobs.js', 'terminal.js', 'site-detail.js', 'stats.js', 'domains.js', 'accounts.js', 'billing.js', 'security.js', 'reports.js', 'settings.js', 'version-recovery.js' ];
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $configurations->name ); ?></title>
<?php captaincore_header_content_extracted(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<script>window.CC_BOOT = <?php echo wp_json_encode( $cc_boot ); ?>;</script>
<script src="<?php echo $plugin_url; ?>public/js/v3/react.production.min.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/v3/react-dom.production.min.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/v3/support.js"></script>
</head>
<body>
<x-dc>
<?php readfile( $v3_dir . '/app.html' ); ?>
</x-dc>
<script type="text/x-dc" data-dc-script data-props="{&quot;shellVariant&quot;:{&quot;editor&quot;:&quot;enum&quot;,&quot;default&quot;:&quot;rail&quot;,&quot;options&quot;:[&quot;rail&quot;,&quot;slim&quot;,&quot;topnav&quot;],&quot;tsType&quot;:&quot;string&quot;,&quot;section&quot;:&quot;Shell&quot;},&quot;role&quot;:{&quot;editor&quot;:&quot;enum&quot;,&quot;default&quot;:&quot;operator&quot;,&quot;options&quot;:[&quot;operator&quot;,&quot;customer&quot;],&quot;tsType&quot;:&quot;string&quot;,&quot;section&quot;:&quot;Shell&quot;},&quot;brandColor&quot;:{&quot;editor&quot;:&quot;color&quot;,&quot;default&quot;:&quot;#3b82c4&quot;,&quot;options&quot;:[&quot;#3b82c4&quot;,&quot;#2c3e50&quot;,&quot;#0e9f6e&quot;,&quot;#7c5cff&quot;],&quot;tsType&quot;:&quot;string&quot;,&quot;section&quot;:&quot;Brand&quot;}}">
<?php foreach ( $v3_scripts as $v3_script ) { readfile( $v3_dir . '/' . $v3_script ); echo "\n"; } ?>
</script>
</body>
</html>
