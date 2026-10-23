<?php
/**
 * CaptainCore — the approve page for the Claude connector (McpAuth::authorize).
 * Same look as core-login.php. $view comes from McpAuth::render(): either
 * an error message, or the consent details plus the form fields.
 */

$configurations = ( new CaptainCore\Configurations )->get();
$colors         = CaptainCore\Configurations::colors();
$brand          = ! empty( $colors->primary ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $colors->primary ) ? $colors->primary : '#3b82c4';
$name           = ! empty( $configurations->name ) ? $configurations->name : 'CaptainCore';
$plugin_url     = plugin_dir_url( __DIR__ );
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( $view['title'] ); ?> · <?php echo esc_html( $name ); ?></title>
<script>
try {
	var stored = localStorage.getItem('captaincore-theme');
	var paint = stored;
	if (stored !== 'light' && stored !== 'dark') {
		paint = (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
	}
	document.documentElement.setAttribute('data-theme', paint);
} catch (e) {}
</script>
<style>
@font-face {
  font-family: 'Hanken Grotesk'; font-style: normal; font-weight: 100 900; font-display: swap;
  src: url('<?php echo esc_url( $plugin_url ); ?>public/fonts/hanken-grotesk.woff2') format('woff2');
}
:root{
  --paper:#ffffff;--rule:#e7e7ea;--ink:#1a1a1f;--ink-dim:#5e5e69;
  --brand:<?php echo $brand; ?>;--brand-ink:color-mix(in oklch,var(--brand) 72%,black);
  --brand-soft:color-mix(in srgb,var(--brand) 10%,transparent);
  --bad:#d05757;--bad-soft:rgba(228,107,107,0.12);
  --warn:#9a6a12;--warn-soft:rgba(230,170,60,0.14);
  --shadow:0 12px 40px rgba(30,30,50,0.14);
  --sans:"Hanken Grotesk",ui-sans-serif,-apple-system,"Segoe UI",sans-serif;
  --canvas:#f6f6f7;
}
[data-theme="dark"]{
  --paper:#151518;--rule:#242429;--ink:#ececed;--ink-dim:#9d9da7;
  --brand-ink:color-mix(in oklch,var(--brand) 55%,white);
  --brand-soft:color-mix(in srgb,var(--brand) 15%,transparent);
  --bad:#e46b6b;--warn:#e2b25a;--warn-soft:rgba(230,170,60,0.12);
  --shadow:0 12px 40px rgba(0,0,0,0.5);
  --canvas:#0b0b0d;
}
html,body{margin:0;padding:0;background:var(--canvas);min-height:100%}
body{font-family:var(--sans);color:var(--ink);display:flex;flex-direction:column;min-height:100dvh}
.ca-wrap{flex:1;display:grid;place-items:center;padding:28px 16px}
.ca-card{width:420px;max-width:100%;background:var(--paper);border:1px solid var(--rule);border-radius:14px;box-shadow:var(--shadow);padding:30px 30px 26px;box-sizing:border-box}
.ca-brand{display:flex;align-items:center;gap:10px;margin-bottom:22px}
.ca-brand svg{color:var(--brand-ink);flex:none}
.ca-brand span{font:700 17px var(--sans);letter-spacing:-.01em}
.ca-h{font:700 21px var(--sans);letter-spacing:-.01em;margin:0 0 6px}
.ca-sub{font:400 13.5px/1.5 var(--sans);color:var(--ink-dim);margin:0 0 18px}
.ca-who{font:400 13px/1.5 var(--sans);color:var(--ink-dim);border:1px solid var(--rule);border-radius:9px;padding:10px 12px;margin:0 0 16px}
.ca-who strong{color:var(--ink);font-weight:600}
.ca-who a{color:var(--brand-ink);text-decoration:none}
.ca-who a:hover{text-decoration:underline}
.ca-list{margin:0 0 18px;padding:0 0 0 18px;font:400 13.5px/1.55 var(--sans)}
.ca-list li{margin:0 0 6px}
.ca-note{font:500 12.5px/1.5 var(--sans);border-radius:9px;padding:9px 12px;margin:0 0 16px;background:var(--warn-soft);color:var(--warn)}
.ca-alert{font:500 13px/1.5 var(--sans);border-radius:9px;padding:10px 12px;background:var(--bad-soft);color:var(--bad)}
.ca-actions{display:flex;gap:10px}
.ca-btn{flex:1;box-sizing:border-box;border:1px solid var(--rule);background:var(--paper);color:var(--ink);font:600 14px var(--sans);height:40px;border-radius:9px;cursor:pointer}
.ca-btn.primary{border:none;background:var(--brand);color:#fff}
.ca-btn:hover{filter:brightness(1.06)}
.ca-foot{text-align:center;padding:0 0 22px;font:400 12px var(--sans);color:var(--ink-dim)}
</style>
</head>
<body>
<div class="ca-wrap">
  <div class="ca-card">
    <div class="ca-brand">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4.5" r="2"></circle><path d="M12 6.5V21"></path><path d="M7.5 10h9"></path><path d="M4 14.5a8 8 0 0 0 16 0"></path><path d="M4 14.5h2.6M20 14.5h-2.6"></path></svg>
      <span><?php echo esc_html( $name ); ?></span>
    </div>

<?php if ( ! empty( $view['error'] ) ) : ?>
    <h1 class="ca-h">Can't connect</h1>
    <div class="ca-alert" role="alert"><?php echo esc_html( $view['error'] ); ?></div>
<?php else : ?>
    <h1 class="ca-h"><?php echo esc_html( $view['title'] ); ?></h1>
    <p class="ca-sub"><?php echo esc_html( $view['client_label'] ); ?> is asking to work on your sites. It will send you back to <strong><?php echo esc_html( $view['redirect_host'] ); ?></strong>.</p>

    <div class="ca-who">
      Signed in as <strong><?php echo esc_html( $view['user_name'] ); ?></strong> (<?php echo esc_html( $view['user_email'] ); ?>).
      <a href="<?php echo esc_url( $view['switch_url'] ); ?>">Not you?</a>
    </div>

    <ul class="ca-list">
      <li>See the <?php echo $view['is_admin'] ? 'sites in the whole fleet' : esc_html( sprintf( _n( '%s site', '%s sites', $view['site_count'] ), number_format_i18n( $view['site_count'] ) ) ) . ' you can manage'; ?>.</li>
      <li>Run WP-CLI and shell commands on them as you, including changes to content, plugins and settings.</li>
      <li>Every command is recorded in your account's activity log.</li>
      <li>Disconnect any time by revoking "<?php echo esc_html( $view['client_label'] ); ?> connector" under Profile → Application passwords.</li>
    </ul>

<?php if ( $view['is_admin'] ) : ?>
    <div class="ca-note">You are an administrator, so this connection can reach every site <?php echo esc_html( $name ); ?> manages.</div>
<?php endif; ?>
<?php if ( $view['loopback'] ) : ?>
    <div class="ca-note">This request comes from Claude Code on your own computer. Approve it only if you just started it there yourself.</div>
<?php endif; ?>

    <form method="post" action="<?php echo esc_url( $view['action'] ); ?>" class="ca-actions">
      <input type="hidden" name="request" value="<?php echo esc_attr( $view['request_id'] ); ?>">
      <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $view['nonce'] ); ?>">
      <button class="ca-btn" type="submit" name="decision" value="deny">Cancel</button>
      <button class="ca-btn primary" type="submit" name="decision" value="allow">Allow</button>
    </form>
<?php endif; ?>
  </div>
</div>
<div class="ca-foot"><?php echo esc_html( $name ); ?></div>
</body>
</html>
