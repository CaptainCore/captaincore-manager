<?php
/**
 * CaptainCore v3 — ground-up UI rebuild. Served behind ?ui=v3
 * (see CaptainCore\Router::load_template()).
 *
 * Source of truth lives in templates/core-v3/:
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
$v3_dir     = __DIR__ . '/core-v3';
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
    // Profile state is server-rendered by User::profile() (same as v1) so the
    // Profile screen needs no extra fetch for its initial state.
    'tfaEnabled'      => ! empty( $user->tfa_enabled ),
    'appPassword'     => isset( $user->application_password ) ? $user->application_password : null,
    'sessions'        => isset( $user->sessions ) ? $user->sessions : [],
    // WooCommerce add-payment-method page (fallback if Stripe Elements can't load).
    'addPaymentUrl'   => ( function_exists( 'wc_get_endpoint_url' ) && function_exists( 'wc_get_page_permalink' ) )
        ? wc_get_endpoint_url( 'add-payment-method', '', wc_get_page_permalink( 'myaccount' ) ) : '',
    // Stripe publishable key — the SPA embeds Stripe Elements to add cards.
    'stripeKey'       => class_exists( 'WC_Gateway_Stripe' ) ? ( new WC_Gateway_Stripe )->publishable_key : '',
    // Zip-upload endpoint for the Add plugin/theme dialog (admin-gated in upload.php).
    'uploadUrl'       => $plugin_url . 'upload.php',
];

// Intercom chat support — customers only, v1 parity. Identity verification rides
// user_hash (HMAC of email with the server-side secret; the key itself must never
// reach the client). Admins never load the widget.
$intercom_embed_id = isset( $configurations->intercom_embed_id ) ? (string) $configurations->intercom_embed_id : '';
$load_intercom     = $user->role !== 'administrator' && $intercom_embed_id !== ''
	&& ! empty( $user->email ) && ! empty( $user->login ) && ! empty( $user->registered );

// Switched-session escape (User Switching): surface the back-link in the shell —
// the plugin's own link lives in the admin bar this SPA never renders. NOTE:
// switch_back_url() returns an HTML-escaped nonce URL; decode or the nonce breaks.
if ( class_exists( 'user_switching' ) ) {
    $old_user = user_switching::get_old_user();
    if ( $old_user ) {
        $switch_back = user_switching::switch_back_url( $old_user );
        $cc_boot['switchBackUrl']   = html_entity_decode( add_query_arg( 'redirect_to', urlencode( home_url( $config_path . '?ui=v3' ) ), $switch_back ) );
        $cc_boot['switchBackLabel'] = 'Switch back to ' . $old_user->display_name;
    }
}

$v3_scripts = [ 'app.js', 'data.js', 'router.js', 'toast.js', 'home.js', 'users.js', 'jobs.js', 'terminal.js', 'site-detail.js', 'addons.js', 'stats.js', 'domains.js', 'accounts.js', 'billing.js', 'security.js', 'reports.js', 'settings.js', 'archives.js', 'profile.js', 'sites-filters.js', 'version-recovery.js' ];
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $configurations->name ); ?></title>
<?php captaincore_header_content_extracted(); ?>
<style>
/* Bundled variable fonts (Minn Admin design language) — no external font requests. */
@font-face {
  font-family: 'Hanken Grotesk'; font-style: normal; font-weight: 100 900; font-display: swap;
  src: url('<?php echo $plugin_url; ?>public/fonts/hanken-grotesk.woff2') format('woff2');
}
@font-face {
  font-family: 'JetBrains Mono'; font-style: normal; font-weight: 100 800; font-display: swap;
  src: url('<?php echo $plugin_url; ?>public/fonts/jetbrains-mono.woff2') format('woff2');
}
</style>
<script>window.CC_BOOT = <?php echo wp_json_encode( $cc_boot ); ?>;</script>
<?php if ( ! empty( $cc_boot['stripeKey'] ) ) : ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>
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
<?php if ( $load_intercom ) : ?>
<script>
window.intercomSettings = {
	app_id: <?php echo wp_json_encode( $intercom_embed_id ); ?>,
	name: <?php echo wp_json_encode( $user->display_name ); ?>,
	email: <?php echo wp_json_encode( $user->email ); ?>,
	created_at: <?php echo wp_json_encode( $user->registered ); ?>,
	user_hash: <?php echo wp_json_encode( $user->hash ); ?>
};
(function(){var w=window;var ic=w.Intercom;if(typeof ic==="function"){ic('reattach_activator');ic('update',w.intercomSettings);}else{var d=document;var i=function(){i.c(arguments);};i.q=[];i.c=function(args){i.q.push(args);};w.Intercom=i;var l=function(){var s=d.createElement('script');s.type='text/javascript';s.async=true;s.src='https://widget.intercom.io/widget/<?php echo esc_js( $intercom_embed_id ); ?>';var x=d.getElementsByTagName('script')[0];x.parentNode.insertBefore(s,x);};if(document.readyState==='complete'){l();}else if(w.attachEvent){w.attachEvent('onload',l);}else{w.addEventListener('load',l,false);}}})();
</script>
<?php endif; ?>
</body>
</html>
