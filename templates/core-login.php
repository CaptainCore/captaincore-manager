<?php
/**
 * CaptainCore — standalone login page for the current UI (templates/core.php).
 * Served by Router::load_template() for the `login` route. No React/DC runtime:
 * one form, vanilla JS, the same Minn design tokens + fonts as the app shell.
 *
 * Backend contract (templates/core-legacy.php parity, POST /captaincore/v1/login/):
 *   {command:"signIn", login:{user_login, user_password, tfa_code?}}
 *     → {message:"Logged in."}                                  success → redirect
 *     → {info:"Enter one time password."}                       reveal TFA field
 *     → {errors:"One time password is invalid."}                keep TFA field
 *     → {info:"We sent a verification email to finish signing in."}  untrusted location
 *     → {errors:"Login failed."}
 *   {command:"reset", login:{user_login}} → true (reset email via Mailer)
 */

$configurations = ( new CaptainCore\Configurations )->get();
$config_path    = '/' . trim( (string) $configurations->path, '/' );
$config_path    = $config_path === '/' ? '/' : $config_path . '/';

// Already signed in → straight into the app.
if ( is_user_logged_in() ) {
    wp_safe_redirect( home_url( $config_path ) );
    exit;
}

$colors     = CaptainCore\Configurations::colors();
$brand      = ! empty( $colors->primary ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $colors->primary ) ? $colors->primary : '#3b82c4';
$name       = ! empty( $configurations->name ) ? $configurations->name : 'CaptainCore';
$plugin_url = plugin_dir_url( __DIR__ );
$rest_login = esc_url_raw( rest_url( 'captaincore/v1/login/' ) );
$app_home   = home_url( $config_path );
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?php echo esc_html( $name ); ?></title>
<script>
try {
	var stored = localStorage.getItem('captaincore-theme');
	if (!stored) { localStorage.setItem('captaincore-theme', 'system'); stored = 'system'; }
	var paint = stored;
	if (stored !== 'light' && stored !== 'dark') {
		paint = (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
	}
	document.documentElement.setAttribute('data-theme', paint);
} catch (e) {}
</script>
<?php captaincore_header_content_extracted(); ?>
<style>
@font-face {
  font-family: 'Hanken Grotesk'; font-style: normal; font-weight: 100 900; font-display: swap;
  src: url('<?php echo $plugin_url; ?>public/fonts/hanken-grotesk.woff2') format('woff2');
}
@font-face {
  font-family: 'JetBrains Mono'; font-style: normal; font-weight: 100 800; font-display: swap;
  src: url('<?php echo $plugin_url; ?>public/fonts/jetbrains-mono.woff2') format('woff2');
}
/* Minn tokens — the app shell's palette (templates/core-v3/app.html helmet). */
:root{
  --paper:#ffffff;--panel:#ffffff;--panel-2:#eeeef1;
  --rule:#e7e7ea;--ink:#1a1a1f;--ink-dim:#5e5e69;
  --brand:<?php echo $brand; ?>;--brand-ink:color-mix(in oklch,var(--brand) 72%,black);
  --brand-soft:color-mix(in srgb,var(--brand) 10%,transparent);
  --ok:#3a9e6f;--bad:#d05757;
  --ok-soft:rgba(70,184,129,0.13);--bad-soft:rgba(228,107,107,0.12);
  --ring:color-mix(in srgb,var(--brand) 30%,transparent);
  --shadow:0 12px 40px rgba(30,30,50,0.14);
  --sans:"Hanken Grotesk",ui-sans-serif,-apple-system,"Segoe UI",sans-serif;
  --mono:"JetBrains Mono",ui-monospace,"SF Mono",Menlo,Consolas,monospace;
  --canvas:#f6f6f7;
}
:root[data-theme="dark"]{
  --paper:#151518;--panel:#101013;--panel-2:#202027;
  --rule:#242429;--ink:#ececed;--ink-dim:#9d9da7;
  --brand-ink:color-mix(in oklch,var(--brand) 55%,white);
  --brand-soft:color-mix(in srgb,var(--brand) 15%,transparent);
  --ok:#46b881;--bad:#e46b6b;
  --shadow:0 12px 40px rgba(0,0,0,0.5);
  --canvas:#0b0b0d;
}
html,body{margin:0;padding:0;background:var(--canvas);height:100%}
body{font-family:var(--sans);color:var(--ink);display:flex;flex-direction:column;min-height:100dvh}
.cl-wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:24px}
.cl-card{width:380px;max-width:100%;background:var(--paper);border:1px solid var(--rule);border-radius:14px;box-shadow:var(--shadow);padding:30px 30px 26px;box-sizing:border-box;animation:clfade .25s ease}
@keyframes clfade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.cl-brand{display:flex;align-items:center;gap:10px;margin-bottom:22px}
.cl-brand svg{color:var(--brand-ink);flex:none}
.cl-brand span{font:700 17px var(--sans);letter-spacing:-.01em}
.cl-h{font:700 21px var(--sans);letter-spacing:-.01em;margin:0 0 4px}
.cl-sub{font:400 13px var(--sans);color:var(--ink-dim);margin:0 0 20px}
.cl-label{display:block;font:500 12.5px var(--sans);color:var(--ink-dim);margin:0 0 5px}
.cl-input{width:100%;box-sizing:border-box;height:38px;border:1px solid var(--rule);border-radius:9px;background:var(--paper);color:var(--ink);font:400 14px var(--sans);padding:0 11px;outline:none;margin-bottom:14px}
.cl-input:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--ring)}
.cl-input:disabled{opacity:.55}
.cl-input.mono{font:400 15px var(--mono);letter-spacing:.35em;text-align:center}
.cl-btn{width:100%;box-sizing:border-box;border:none;background:var(--brand);color:#fff;font:600 14px var(--sans);height:40px;border-radius:9px;cursor:pointer;margin-top:2px}
.cl-btn:hover{filter:brightness(1.08)}
.cl-btn:disabled{opacity:.6;cursor:default}
.cl-alert{font:500 12.5px/1.5 var(--sans);border-radius:9px;padding:9px 12px;margin:0 0 14px;display:none}
.cl-alert.bad{display:block;background:var(--bad-soft);color:var(--bad)}
.cl-alert.info{display:block;background:var(--brand-soft);color:var(--brand-ink)}
.cl-alert.ok{display:block;background:var(--ok-soft);color:var(--ok)}
.cl-links{display:flex;justify-content:center;margin-top:16px}
.cl-links a{font:500 12.5px var(--sans);color:var(--brand-ink);text-decoration:none;cursor:pointer}
.cl-links a:hover{text-decoration:underline}
.cl-tfa{display:none}
.cl-tfa.show{display:block}
.cl-theme{position:fixed;top:14px;right:14px;width:34px;height:34px;display:grid;place-items:center;border:1px solid var(--rule);border-radius:9px;background:var(--paper);color:var(--ink-dim);cursor:pointer}
.cl-theme:hover{color:var(--ink);border-color:var(--brand)}
.cl-foot{text-align:center;padding:0 0 22px;font:400 12px var(--sans);color:var(--ink-dim)}
</style>
</head>
<body>
<button class="cl-theme" id="cl-theme" title="Toggle theme" aria-label="Toggle theme">
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor" stroke="none"></path></svg>
</button>
<div class="cl-wrap">
  <div class="cl-card">
    <div class="cl-brand">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4.5" r="2"></circle><path d="M12 6.5V21"></path><path d="M7.5 10h9"></path><path d="M4 14.5a8 8 0 0 0 16 0"></path><path d="M4 14.5h2.6M20 14.5h-2.6"></path></svg>
      <span><?php echo esc_html( $name ); ?></span>
    </div>

    <form id="cl-login">
      <h1 class="cl-h">Sign in</h1>
      <p class="cl-sub">Manage your sites, domains and billing.</p>
      <div class="cl-alert" id="cl-alert" role="alert"></div>
      <label class="cl-label" for="cl-user">Username or email</label>
      <input class="cl-input" id="cl-user" name="username" autocomplete="username" autofocus required>
      <label class="cl-label" for="cl-pass">Password</label>
      <input class="cl-input" id="cl-pass" name="password" type="password" autocomplete="current-password" required>
      <div class="cl-tfa" id="cl-tfa">
        <label class="cl-label" for="cl-code">One-time password</label>
        <input class="cl-input mono" id="cl-code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••">
      </div>
      <button class="cl-btn" id="cl-go" type="submit">Sign in</button>
      <div class="cl-links"><a id="cl-to-reset">Lost your password?</a></div>
    </form>

    <form id="cl-reset" style="display:none">
      <h1 class="cl-h">Reset password</h1>
      <p class="cl-sub">We'll email you a link to choose a new one.</p>
      <div class="cl-alert" id="cl-ralert" role="alert"></div>
      <label class="cl-label" for="cl-ruser">Username or email</label>
      <input class="cl-input" id="cl-ruser" autocomplete="username">
      <button class="cl-btn" id="cl-rgo" type="submit">Send reset link</button>
      <div class="cl-links"><a id="cl-to-login">Back to sign in</a></div>
    </form>
  </div>
</div>
<div class="cl-foot"><?php echo esc_html( $name ); ?></div>
<script>
(function () {
	var API = <?php echo wp_json_encode( $rest_login ); ?>;
	var HOME = <?php echo wp_json_encode( $app_home ); ?>;
	var $ = function (id) { return document.getElementById(id); };

	// Theme toggle — same localStorage key as the app, so the choice carries in.
	$('cl-theme').addEventListener('click', function () {
		var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
		var next = cur === 'dark' ? 'light' : 'dark';
		document.documentElement.setAttribute('data-theme', next);
		try { localStorage.setItem('captaincore-theme', next); } catch (e) {}
	});

	function alertBox(el, kind, text) {
		el.className = 'cl-alert' + (kind ? ' ' + kind : '');
		el.textContent = text || '';
	}
	function busy(on) {
		['cl-user', 'cl-pass', 'cl-code', 'cl-go'].forEach(function (id) { $(id).disabled = on; });
		$('cl-go').textContent = on ? 'Signing in…' : 'Sign in';
	}

	$('cl-to-reset').addEventListener('click', function () {
		$('cl-login').style.display = 'none'; $('cl-reset').style.display = 'block';
		$('cl-ruser').value = $('cl-user').value; $('cl-ruser').focus();
	});
	$('cl-to-login').addEventListener('click', function () {
		$('cl-reset').style.display = 'none'; $('cl-login').style.display = 'block';
		$('cl-user').focus();
	});

	$('cl-login').addEventListener('submit', function (e) {
		e.preventDefault();
		var body = { command: 'signIn', login: {
			user_login: $('cl-user').value.trim(),
			user_password: $('cl-pass').value,
			tfa_code: $('cl-code').value.trim()
		} };
		if (!body.login.user_login || !body.login.user_password) return;
		busy(true); alertBox($('cl-alert'), '', '');
		fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				res = res || {};
				if (!res.errors && !res.info) { window.location = HOME; return; }
				busy(false);
				if (res.info === 'Enter one time password.' || res.errors === 'One time password is invalid.') {
					$('cl-tfa').classList.add('show');
					$('cl-code').focus();
				}
				if (res.errors) alertBox($('cl-alert'), 'bad', res.errors);
				else alertBox($('cl-alert'), 'info', res.info);
			})
			.catch(function () { busy(false); alertBox($('cl-alert'), 'bad', 'Could not reach the server. Try again.'); });
	});

	$('cl-reset').addEventListener('submit', function (e) {
		e.preventDefault();
		var user = $('cl-ruser').value.trim();
		if (!user) return;
		$('cl-rgo').disabled = true; $('cl-rgo').textContent = 'Sending…';
		alertBox($('cl-ralert'), '', '');
		fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ command: 'reset', login: { user_login: user } }) })
			// The endpoint answers `true` for a real account and an EMPTY body
			// for an unknown one — read text, never parse, so both render the
			// same non-enumerating copy.
			.then(function (r) { if (!r.ok) throw 0; return r.text(); })
			.then(function () {
				$('cl-rgo').disabled = false; $('cl-rgo').textContent = 'Send reset link';
				alertBox($('cl-ralert'), 'ok', 'If that account exists, a reset link is on its way.');
			})
			.catch(function () {
				$('cl-rgo').disabled = false; $('cl-rgo').textContent = 'Send reset link';
				alertBox($('cl-ralert'), 'bad', 'Could not reach the server. Try again.');
			});
	});
})();
</script>
</body>
</html>
