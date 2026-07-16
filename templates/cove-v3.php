<?php
/**
 * CaptainCore v3 template ("Cove") — ground-up UI rebuild.
 *
 * Generated from the Claude Design project "Anchor Hosting UI Revamp"
 * (Anchor Home.dc.html) by scratchpad/build-cove-v3.py. The design runs on the
 * Design Components runtime (public/js/v3/support.js + vendored React UMD).
 * Served behind ?ui=v3 — see CaptainCore\Router::load_template().
 *
 * Real data enters through window.CC_BOOT (below); the app hydrates the
 * fleet/accounts/domains lists from the captaincore/v1 REST API on mount and
 * falls back to the design's sample data when a fetch fails.
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
<style>
:root{
  --paper:oklch(1 0 0);--panel:oklch(0.985 0.003 240);--panel-2:oklch(0.965 0.005 240);
  --rule:oklch(0.9 0.008 240);--ink:oklch(0.30 0.014 250);--ink-dim:oklch(0.52 0.012 250);
  --brand:#3b82c4;--brand-ink:color-mix(in oklch,var(--brand) 72%,black);
  --brand-soft:color-mix(in srgb,var(--brand) 12%,transparent);
  --ok:oklch(0.68 0.16 150);--warn:oklch(0.72 0.15 75);--bad:oklch(0.62 0.18 25);
  --ok-soft:color-mix(in srgb,oklch(0.68 0.16 150) 14%,transparent);
  --warn-soft:color-mix(in srgb,oklch(0.78 0.16 80) 18%,transparent);
  --bad-soft:color-mix(in srgb,oklch(0.62 0.18 25) 12%,transparent);
  --shadow:0 16px 40px -24px rgb(20 28 45/.28),0 1px 0 rgb(20 28 45/.04);
  --sans:"Space Grotesk",ui-sans-serif,-apple-system,"Segoe UI",sans-serif;
  --mono:"JetBrains Mono",ui-monospace,"SF Mono",Menlo,Consolas,monospace;
}
:root[data-theme="dark"]{
  --paper:oklch(0.19 0.018 240);--panel:oklch(0.22 0.02 240);--panel-2:oklch(0.26 0.022 240);
  --rule:oklch(0.33 0.022 240);--ink:oklch(0.96 0.005 240);--ink-dim:oklch(0.70 0.01 240);
  --brand-ink:color-mix(in oklch,var(--brand) 55%,white);
  --brand-soft:color-mix(in srgb,var(--brand) 20%,transparent);
  --shadow:0 24px 48px -28px rgb(0 0 0/.72);
}
html,body{margin:0;padding:0;background:var(--paper)}
body{font-family:var(--sans);color:var(--ink)}
a{color:var(--brand-ink);text-decoration:none}
a:hover{color:var(--brand-ink);text-decoration:underline}
*{box-sizing:border-box}
::-webkit-scrollbar{width:10px;height:10px}::-webkit-scrollbar-thumb{background:var(--rule);border-radius:6px;border:2px solid var(--paper)}::-webkit-scrollbar-track{background:transparent}
@keyframes ccpulse{0%,100%{opacity:1}50%{opacity:.35}}
@keyframes ccblink{0%,49%{opacity:1}50%,100%{opacity:0}}
@keyframes ccfade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
</style>
<script>window.CC_BOOT = <?php echo wp_json_encode( $cc_boot ); ?>;</script>
<script src="<?php echo $plugin_url; ?>public/js/v3/react.production.min.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/v3/react-dom.production.min.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/v3/support.js"></script>
</head>
<body>
<x-dc>
<helmet>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --paper:oklch(1 0 0);--panel:oklch(0.985 0.003 240);--panel-2:oklch(0.965 0.005 240);
  --rule:oklch(0.9 0.008 240);--ink:oklch(0.30 0.014 250);--ink-dim:oklch(0.52 0.012 250);
  --brand:#3b82c4;--brand-ink:color-mix(in oklch,var(--brand) 72%,black);
  --brand-soft:color-mix(in srgb,var(--brand) 12%,transparent);
  --ok:oklch(0.68 0.16 150);--warn:oklch(0.72 0.15 75);--bad:oklch(0.62 0.18 25);
  --ok-soft:color-mix(in srgb,oklch(0.68 0.16 150) 14%,transparent);
  --warn-soft:color-mix(in srgb,oklch(0.78 0.16 80) 18%,transparent);
  --bad-soft:color-mix(in srgb,oklch(0.62 0.18 25) 12%,transparent);
  --shadow:0 16px 40px -24px rgb(20 28 45/.28),0 1px 0 rgb(20 28 45/.04);
  --sans:"Space Grotesk",ui-sans-serif,-apple-system,"Segoe UI",sans-serif;
  --mono:"JetBrains Mono",ui-monospace,"SF Mono",Menlo,Consolas,monospace;
}
:root[data-theme="dark"]{
  --paper:oklch(0.19 0.018 240);--panel:oklch(0.22 0.02 240);--panel-2:oklch(0.26 0.022 240);
  --rule:oklch(0.33 0.022 240);--ink:oklch(0.96 0.005 240);--ink-dim:oklch(0.70 0.01 240);
  --brand-ink:color-mix(in oklch,var(--brand) 55%,white);
  --brand-soft:color-mix(in srgb,var(--brand) 20%,transparent);
  --shadow:0 24px 48px -28px rgb(0 0 0/.72);
}
html,body{margin:0;padding:0;background:var(--paper)}
body{font-family:var(--sans);color:var(--ink)}
a{color:var(--brand-ink);text-decoration:none}
a:hover{color:var(--brand-ink);text-decoration:underline}
*{box-sizing:border-box}
::-webkit-scrollbar{width:10px;height:10px}::-webkit-scrollbar-thumb{background:var(--rule);border-radius:6px;border:2px solid var(--paper)}::-webkit-scrollbar-track{background:transparent}
@keyframes ccpulse{0%,100%{opacity:1}50%{opacity:.35}}
@keyframes ccblink{0%,49%{opacity:1}50%,100%{opacity:0}}
@keyframes ccfade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
</style>
</helmet>
<div style="height:100vh;display:flex;flex-direction:column;background:var(--paper);color:var(--ink);font-family:var(--sans);overflow:hidden">

  <!-- ══ Top bar ══ -->
  <header style="flex:none;height:54px;display:flex;align-items:center;gap:16px;padding:0 16px;border-bottom:1px solid var(--rule);background:var(--paper)">
    <div onClick="{{ goHome }}" style="display:flex;align-items:center;gap:9px;cursor:pointer;padding:4px 6px;border-radius:8px" style-hover="background:var(--panel-2)">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--brand)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a2 2 0 100 4 2 2 0 000-4zm0 4v14m0 0c-4.5 0-8-3-8.5-7L2 13m10 8c4.5 0 8-3 8.5-7l1.5-1M7 10h10"></path></svg>
      <span style="font:600 17px var(--sans);letter-spacing:-.01em">Anchor Hosting</span>
    </div>
    <sc-if value="{{ showTopNav }}" hint-placeholder-val="{{ false }}">
      <nav style="display:flex;align-items:center;gap:2px">
        <sc-for list="{{ nav }}" as="n" hint-placeholder-count="4">
          <div onClick="{{ n.go }}" style="padding:6px 11px;border-radius:8px;cursor:pointer;font:500 14px var(--sans);color:{{ n.fg }};background:{{ n.bg }}" style-hover="background:var(--panel-2)">{{ n.label }}</div>
        </sc-for>
      </nav>
    </sc-if>
    <div style="flex:1"></div>
    <div onClick="{{ openPalette }}" style="display:flex;align-items:center;gap:8px;width:300px;max-width:32vw;height:32px;padding:0 10px;border:1px solid var(--rule);border-radius:8px;background:var(--panel);cursor:pointer;color:var(--ink-dim)" style-hover="border-color:var(--brand);color:var(--ink)">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4a7 7 0 100 14 7 7 0 000-14zm10 17-5.2-5.2"></path></svg>
      <span style="font:400 13.5px var(--sans)">Search or run a command…</span>
      <span style="margin-left:auto;font:500 11.5px var(--mono);border:1px solid var(--rule);border-radius:5px;padding:2px 5px;background:var(--paper)">⌘K</span>
    </div>
    <div style="display:flex;align-items:center;gap:6px">
      <div title="Provider activity" style="position:relative;width:32px;height:32px;display:grid;place-items:center;border-radius:8px;cursor:pointer;color:var(--ink-dim)" style-hover="background:var(--panel-2);color:var(--ink)">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9a6 6 0 1112 0c0 5 2 6 2 6H4s2-1 2-6m4 9.5a2 2 0 004 0"></path></svg>
        <span style="position:absolute;top:6px;right:7px;width:7px;height:7px;border-radius:50%;background:var(--bad);border:1.5px solid var(--paper)"></span>
      </div>
      <div onClick="{{ toggleTheme }}" title="Toggle theme" style="width:32px;height:32px;display:grid;place-items:center;border-radius:8px;cursor:pointer;color:var(--ink-dim)" style-hover="background:var(--panel-2);color:var(--ink)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ themeIcon }}"></path></svg>
      </div>
      <div onClick="{{ goProfile }}" title="Profile" style="display:flex;align-items:center;gap:8px;padding:3px 8px 3px 3px;border-radius:99px;cursor:pointer" style-hover="background:var(--panel-2)">
        <span style="width:26px;height:26px;border-radius:50%;background:var(--brand);color:white;display:grid;place-items:center;font:600 12px var(--sans)">{{ userInitials }}</span>
        <span style="font:500 13.5px var(--sans);color:var(--ink-dim)">{{ userName }}</span>
      </div>
    </div>
  </header>

  <div style="flex:1;display:flex;min-height:0">
    <!-- ══ Left rail ══ -->
    <sc-if value="{{ showRail }}" hint-placeholder-val="{{ true }}">
      <nav style="flex:none;width:{{ railWidth }};border-right:1px solid var(--rule);background:var(--panel);display:flex;flex-direction:column;padding:12px 8px 68px;gap:2px;overflow-y:auto">
        <sc-for list="{{ nav }}" as="n" hint-placeholder-count="5">
          <div onClick="{{ n.go }}" title="{{ n.label }}" style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font:500 14.5px var(--sans);color:{{ n.fg }};background:{{ n.bg }};justify-content:{{ railJustify }}" style-hover="background:var(--panel-2)">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="flex:none"><path d="{{ n.icon }}"></path></svg>
            <span style="display:{{ labelDisplay }};white-space:nowrap">{{ n.label }}</span>
          </div>
        </sc-for>
        <sc-if value="{{ showOperate }}" hint-placeholder-val="{{ false }}">
          <div style="margin:14px 10px 4px;font:600 11px var(--sans);letter-spacing:.09em;text-transform:uppercase;color:var(--ink-dim);display:{{ labelDisplay }}">Operate</div>
          <sc-for list="{{ navOperate }}" as="n" hint-placeholder-count="3">
            <div onClick="{{ n.go }}" title="{{ n.label }}" style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font:500 14.5px var(--sans);color:{{ n.fg }};background:{{ n.bg }};justify-content:{{ railJustify }}" style-hover="background:var(--panel-2)">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="flex:none"><path d="{{ n.icon }}"></path></svg>
              <span style="display:{{ labelDisplay }};white-space:nowrap">{{ n.label }}</span>
            </div>
          </sc-for>
        </sc-if>
        <div style="flex:1"></div>
        <sc-for list="{{ navBottom }}" as="n" hint-placeholder-count="1">
          <div onClick="{{ n.go }}" title="{{ n.label }}" style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font:500 14.5px var(--sans);color:{{ n.fg }};background:{{ n.bg }};justify-content:{{ railJustify }}" style-hover="background:var(--panel-2)">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="flex:none"><path d="{{ n.icon }}"></path></svg>
            <span style="display:{{ labelDisplay }};white-space:nowrap">{{ n.label }}</span>
          </div>
        </sc-for>
      </nav>
    </sc-if>

    <!-- ══ Main ══ -->
    <main style="flex:1;overflow-y:auto;min-width:0">

      <sc-if value="{{ showHome }}" hint-placeholder-val="{{ true }}">
        <div style="max-width:1160px;margin:0 auto;padding:32px 32px 120px;animation:ccfade .25s ease">
          <div style="display:flex;align-items:baseline;gap:14px;flex-wrap:wrap">
            <h1 style="margin:0;font:600 29px/1.2 var(--sans);letter-spacing:-.02em">{{ greeting }}</h1>
            <span style="font:400 14px var(--sans);color:var(--ink-dim)">{{ statsLine }}</span>
          </div>

          <!-- Launcher -->
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;margin-top:22px">
            <sc-for list="{{ launcher }}" as="l" hint-placeholder-count="5">
              <div onClick="{{ l.go }}" style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);padding:16px;cursor:pointer;display:flex;flex-direction:column;gap:10px;transition:border-color .12s, box-shadow .12s" style-hover="border-color:var(--brand);box-shadow:var(--shadow)">
                <div style="display:flex;align-items:center;justify-content:space-between">
                  <span style="width:34px;height:34px;border-radius:9px;background:var(--brand-soft);color:var(--brand-ink);display:grid;place-items:center">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ l.icon }}"></path></svg>
                  </span>
                  <span style="font:500 13px var(--mono);color:var(--ink-dim)">{{ l.meta }}</span>
                </div>
                <div>
                  <div style="font:600 15px var(--sans)">{{ l.label }}</div>
                  <div style="font:400 13px/1.45 var(--sans);color:var(--ink-dim);margin-top:2px">{{ l.desc }}</div>
                </div>
              </div>
            </sc-for>
          </div>

          <div style="display:grid;grid-template-columns:1.55fr 1fr;gap:14px;margin-top:14px;align-items:start">
            <div style="display:flex;flex-direction:column;gap:14px;min-width:0">
              <!-- Needs attention -->
              <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                <div style="display:flex;align-items:center;gap:8px;padding:13px 16px;border-bottom:1px solid var(--rule)">
                  <h2 style="margin:0;font:600 14.5px var(--sans)">Needs attention</h2>
                  <span style="font:500 12px var(--mono);background:var(--warn-soft);color:var(--ink);border-radius:99px;padding:2px 8px">{{ attentionCount }}</span>
                </div>
                <sc-for list="{{ attention }}" as="a" hint-placeholder-count="3">
                  <div onClick="{{ a.go }}" style="display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-bottom:1px solid var(--rule);cursor:pointer" style-hover="background:var(--panel)">
                    <span style="flex:none;margin-top:5px;width:8px;height:8px;border-radius:50%;background:{{ a.dot }}"></span>
                    <div style="min-width:0;flex:1">
                      <div style="font:500 14.5px var(--sans)">{{ a.title }}</div>
                      <div style="font:400 13px/1.5 var(--sans);color:var(--ink-dim);margin-top:1px">{{ a.sub }}</div>
                    </div>
                    <span style="flex:none;font:500 12.5px var(--sans);color:var(--brand-ink);align-self:center">{{ a.action }} →</span>
                  </div>
                </sc-for>
              </section>
              <!-- Recent activity -->
              <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-bottom:1px solid var(--rule)">
                  <h2 style="margin:0;font:600 14.5px var(--sans)">Recent activity</h2>
                  <a href="#" style="font:500 12.5px var(--sans)">View all</a>
                </div>
                <sc-for list="{{ activity }}" as="ev" hint-placeholder-count="5">
                  <div style="display:flex;gap:12px;padding:10px 16px;border-bottom:1px solid var(--rule);align-items:baseline">
                    <span style="flex:none;width:44px;font:500 12px var(--mono);color:var(--ink-dim);text-align:right">{{ ev.t }}</span>
                    <span style="font:400 13.5px/1.5 var(--sans);color:var(--ink)">{{ ev.text }}</span>
                  </div>
                </sc-for>
              </section>
            </div>

            <div style="display:flex;flex-direction:column;gap:14px;min-width:0">
              <!-- Running now -->
              <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                <div style="display:flex;align-items:center;gap:8px;padding:13px 16px;border-bottom:1px solid var(--rule)">
                  <span style="width:8px;height:8px;border-radius:50%;background:var(--brand);animation:ccpulse 1.6s ease infinite"></span>
                  <h2 style="margin:0;font:600 14.5px var(--sans)">Running now</h2>
                  <span onClick="{{ openDock }}" style="margin-left:auto;font:500 12.5px var(--sans);color:var(--brand-ink);cursor:pointer">Open console →</span>
                </div>
                <sc-for list="{{ jobs }}" as="j" hint-placeholder-count="3">
                  <div style="padding:11px 16px;border-bottom:1px solid var(--rule)">
                    <div style="display:flex;align-items:center;gap:8px">
                      <span style="font:600 13px var(--mono);color:{{ j.fg }}">{{ j.label }}</span>
                      <span style="font:400 13px var(--sans);color:var(--ink-dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ j.target }}</span>
                      <span style="margin-left:auto;font:500 12px var(--mono);color:var(--ink-dim)">{{ j.right }}</span>
                    </div>
                    <sc-if value="{{ j.running }}" hint-placeholder-val="{{ false }}">
                      <div style="margin-top:8px;height:4px;border-radius:99px;background:var(--panel-2);overflow:hidden">
                        <div style="height:100%;width:{{ j.pct }}%;background:var(--brand);border-radius:99px;transition:width .8s ease"></div>
                      </div>
                    </sc-if>
                  </div>
                </sc-for>
              </section>
              <!-- Pinned sites -->
              <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-bottom:1px solid var(--rule)">
                  <h2 style="margin:0;font:600 14.5px var(--sans)">{{ pinnedTitle }}</h2>
                  <span onClick="{{ goSites }}" style="font:500 12.5px var(--sans);color:var(--brand-ink);cursor:pointer">All sites →</span>
                </div>
                <sc-for list="{{ pinned }}" as="s" hint-placeholder-count="4">
                  <div onClick="{{ s.go }}" style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--rule);cursor:pointer" style-hover="background:var(--panel)">
                    <span style="flex:none;width:26px;height:26px;border-radius:7px;background:var(--panel-2);display:grid;place-items:center;font:600 11px var(--sans);color:var(--ink-dim)">{{ s.mono }}</span>
                    <div style="min-width:0;flex:1">
                      <div style="font:500 14px var(--sans);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ s.name }}</div>
                      <div style="font:400 12px var(--mono);color:var(--ink-dim)">{{ s.sub }}</div>
                    </div>
                    <span style="flex:none;width:7px;height:7px;border-radius:50%;background:{{ s.dot }}" title="{{ s.health }}"></span>
                  </div>
                </sc-for>
              </section>
            </div>
          </div>
        </div>
      </sc-if>

      <sc-if value="{{ showSites }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:1240px;margin:0 auto;padding:28px 32px 120px;animation:ccfade .25s ease">
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <h1 style="margin:0;font:600 26px var(--sans);letter-spacing:-.02em">Sites</h1>
            <span style="font:400 14px var(--sans);color:var(--ink-dim)">{{ sitesCount }}</span>
            <div style="flex:1"></div>
            <div style="display:flex;border:1px solid var(--rule);border-radius:8px;overflow:hidden">
              <span onClick="{{ setViewTable }}" style="padding:6px 12px;cursor:pointer;font:500 13px var(--sans);background:{{ tblBg }};color:{{ tblFg }}">Table</span>
              <span onClick="{{ setViewCards }}" style="padding:6px 12px;cursor:pointer;font:500 13px var(--sans);background:{{ crdBg }};color:{{ crdFg }};border-left:1px solid var(--rule)">Cards</span>
            </div>
            <button onClick="{{ openNewSite }}" style="border:none;background:var(--brand);color:white;font:600 13.5px var(--sans);padding:8px 14px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">+ New site</button>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:16px">
            <span onClick="{{ unassignedToggle }}" style="display:flex;align-items:center;gap:6px;height:32px;padding:0 12px;border:1px solid {{ unBd }};border-radius:99px;background:{{ unBg }};color:{{ unFg }};font:600 11px var(--sans);letter-spacing:.06em;text-transform:uppercase;cursor:pointer;white-space:nowrap">⚠ {{ unassignedLabel }}</span>
            <sc-for list="{{ facets }}" as="fc" hint-placeholder-count="5">
              <div style="position:relative">
                <div onClick="{{ fc.toggle }}" style="display:flex;align-items:center;gap:7px;height:32px;padding:0 12px;border:1px solid {{ fc.bd }};border-radius:99px;background:{{ fc.bg }};cursor:pointer;white-space:nowrap" style-hover="border-color:var(--brand)">
                  <span style="font:600 11px var(--sans);letter-spacing:.06em;text-transform:uppercase;color:{{ fc.fg }}">{{ fc.label }}</span>
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                </div>
                <sc-if value="{{ fc.open }}" hint-placeholder-val="{{ false }}">
                  <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                  <div style="position:absolute;top:36px;left:0;z-index:40;width:250px;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                    <input autofocus value="{{ ddQ }}" onInput="{{ onDdQ }}" placeholder="Filter…" style="width:100%;height:28px;border:1px solid var(--rule);border-radius:6px;background:var(--panel);color:var(--ink);font:400 12px var(--sans);padding:0 8px;outline:none;margin-bottom:4px">
                    <div style="max-height:210px;overflow-y:auto">
                      <sc-for list="{{ fc.opts }}" as="o" hint-placeholder-count="5">
                        <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)">
                          <span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink);flex:none">{{ o.mark }}</span>
                          <span style="font:500 12.5px var(--sans);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ o.label }}</span>
                          <span style="font:500 10.5px var(--mono);color:var(--ink-dim);background:var(--panel-2);border-radius:99px;padding:2px 7px;flex:none">{{ o.badge }}</span>
                        </div>
                      </sc-for>
                    </div>
                  </div>
                </sc-if>
              </div>
            </sc-for>
            <div style="display:flex;border:1px solid var(--rule);border-radius:99px;overflow:hidden">
              <sc-for list="{{ opChips }}" as="c" hint-placeholder-count="2">
                <span onClick="{{ c.go }}" style="padding:6px 11px;cursor:pointer;font:600 11px var(--sans);letter-spacing:.05em;background:{{ c.bg }};color:{{ c.fg }}">{{ c.label }}</span>
              </sc-for>
            </div>
            <div style="flex:1"></div>
            <div style="display:flex;align-items:center;gap:7px;height:34px;padding:0 10px;border:1px solid var(--rule);border-radius:8px;background:var(--paper);width:210px">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2" stroke-linecap="round"><path d="M11 4a7 7 0 100 14 7 7 0 000-14zm10 17-5.2-5.2"></path></svg>
              <input value="{{ q }}" onInput="{{ onQ }}" placeholder="Search…" style="border:none;outline:none;background:transparent;color:var(--ink);font:400 13.5px var(--sans);width:100%">
            </div>
            <sc-if value="{{ hasFilters }}" hint-placeholder-val="{{ false }}">
              <span onClick="{{ clearFilters }}" style="font:500 12.5px var(--sans);color:var(--brand-ink);cursor:pointer">Clear</span>
            </sc-if>
          </div>
          <sc-if value="{{ plugRowShow }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px">
              <span style="font:500 12px var(--mono);color:var(--ink-dim)">{{ plugRowLabel }}:</span>
              <sc-for list="{{ facets2 }}" as="fc" hint-placeholder-count="2">
                <div style="position:relative">
                  <div onClick="{{ fc.toggle }}" style="display:flex;align-items:center;gap:7px;height:30px;padding:0 12px;border:1px solid {{ fc.bd }};border-radius:99px;background:{{ fc.bg }};cursor:pointer;white-space:nowrap" style-hover="border-color:var(--brand)">
                    <span style="font:600 10.5px var(--sans);letter-spacing:.06em;text-transform:uppercase;color:{{ fc.fg }}">{{ fc.label }}</span>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                  </div>
                  <sc-if value="{{ fc.open }}" hint-placeholder-val="{{ false }}">
                    <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                    <div style="position:absolute;top:34px;left:0;z-index:40;width:230px;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                      <div style="max-height:210px;overflow-y:auto">
                        <sc-for list="{{ fc.opts }}" as="o" hint-placeholder-count="4">
                          <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)">
                            <span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink);flex:none">{{ o.mark }}</span>
                            <span style="font:500 12.5px var(--sans);flex:1">{{ o.label }}</span>
                            <span style="font:500 10.5px var(--mono);color:var(--ink-dim);background:var(--panel-2);border-radius:99px;padding:2px 7px;flex:none">{{ o.badge }}</span>
                          </div>
                        </sc-for>
                      </div>
                    </div>
                  </sc-if>
                </div>
              </sc-for>
              <div style="display:flex;border:1px solid var(--rule);border-radius:99px;overflow:hidden">
                <sc-for list="{{ isChips }}" as="c" hint-placeholder-count="2">
                  <span onClick="{{ c.go }}" style="padding:5px 10px;cursor:pointer;font:600 10.5px var(--sans);letter-spacing:.05em;background:{{ c.bg }};color:{{ c.fg }}">{{ c.label }}</span>
                </sc-for>
              </div>
              <span onClick="{{ clearPlugin }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">✕ Remove plugin filter</span>
            </div>
          </sc-if>
          <sc-if value="{{ hasLabels }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap">
              <span style="font:500 12px var(--sans);color:var(--ink-dim)">Labels:</span>
              <sc-for list="{{ labelChips }}" as="c" hint-placeholder-count="2">
                <span onClick="{{ c.go }}" style="display:flex;align-items:center;gap:6px;padding:4px 11px;border-radius:99px;border:1px solid {{ c.bd }};background:{{ c.bg }};color:{{ c.fg }};font:500 12px var(--sans);cursor:pointer">{{ c.label }} <span style="font:600 11px var(--mono)">{{ c.n }}</span></span>
              </sc-for>
            </div>
          </sc-if>
          <sc-if value="{{ hasSel }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:6px;margin-top:12px;padding:8px 14px;border:1px solid var(--brand);background:var(--brand-soft);border-radius:10px;flex-wrap:wrap">
              <span style="font:600 13px var(--sans);margin-right:8px">{{ selCount }} selected</span>
              <sc-for list="{{ bulkActions }}" as="b" hint-placeholder-count="4">
                <span onClick="{{ b.go }}" style="font:500 13px var(--sans);color:var(--brand-ink);cursor:pointer;padding:4px 9px;border-radius:6px" style-hover="background:var(--paper)">{{ b.label }}</span>
              </sc-for>
              <div style="flex:1"></div>
              <span onClick="{{ clearSel }}" style="font:500 12.5px var(--sans);color:var(--ink-dim);cursor:pointer">Clear</span>
            </div>
          </sc-if>
          <sc-if value="{{ viewTable }}" hint-placeholder-val="{{ true }}">
            <div style="margin-top:14px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <div style="display:grid;grid-template-columns:36px 2fr 1fr 1fr 0.8fr 1fr 0.8fr;gap:12px;padding:10px 16px;border-bottom:1px solid var(--rule);font:600 11.5px var(--sans);letter-spacing:.07em;text-transform:uppercase;color:var(--ink-dim);align-items:center">
                <span onClick="{{ toggleAll }}" style="width:16px;height:16px;border:1.5px solid var(--rule);border-radius:4px;display:grid;place-items:center;background:{{ selAllBg }};color:white;font:600 10px var(--sans);cursor:pointer">{{ selAllMark }}</span>
                <span>Site</span><span>Environments</span><span>Provider</span><span>Core</span><span>Theme</span><span>Visits / wk</span>
              </div>
              <sc-for list="{{ listRows }}" as="s" hint-placeholder-count="8">
                <div onClick="{{ s.open }}" style="display:grid;grid-template-columns:36px 2fr 1fr 1fr 0.8fr 1fr 0.8fr;gap:12px;padding:12px 16px;border-bottom:1px solid var(--rule);align-items:center;cursor:pointer;font:400 14px var(--sans)" style-hover="background:var(--panel)">
                  <span onClick="{{ s.toggle }}" style="width:16px;height:16px;border:1.5px solid var(--rule);border-radius:4px;display:grid;place-items:center;background:{{ s.checkBg }};color:white;font:600 10px var(--sans);cursor:pointer">{{ s.check }}</span>
                  <span style="min-width:0"><span style="display:block;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ s.name }}</span><span style="display:block;font:400 12px var(--sans);color:var(--ink-dim)">{{ s.account }}</span></span>
                  <span style="font:400 12.5px var(--mono);color:var(--ink-dim)">{{ s.envs }}</span>
                  <span style="color:var(--ink-dim)">{{ s.provider }}</span>
                  <span style="font:400 13px var(--mono);color:var(--ink-dim)">{{ s.core }}</span>
                  <span style="font:400 12.5px var(--mono);color:var(--ink-dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ s.theme }}</span>
                  <span style="font:400 13px var(--mono);color:var(--ink-dim)">{{ s.visits }}</span>
                </div>
              </sc-for>
            </div>
          </sc-if>
          <sc-if value="{{ viewCards }}" hint-placeholder-val="{{ false }}">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;margin-top:14px">
              <sc-for list="{{ listRows }}" as="s" hint-placeholder-count="6">
                <div onClick="{{ s.open }}" style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);padding:16px;cursor:pointer;display:flex;flex-direction:column;gap:10px" style-hover="border-color:var(--brand);box-shadow:var(--shadow)">
                  <div style="display:flex;align-items:center;gap:8px">
                    <span style="font:600 14.5px var(--sans);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1">{{ s.name }}</span>
                    <span style="width:8px;height:8px;border-radius:50%;background:{{ s.dot }};flex:none" title="{{ s.health }}"></span>
                  </div>
                  <div style="font:400 12.5px var(--sans);color:var(--ink-dim)">{{ s.account }}</div>
                  <div style="font:400 12px var(--mono);color:var(--ink-dim)">{{ s.provider }} · WP {{ s.core }} · {{ s.visits }} visits/wk</div>
                  <div style="display:flex;align-items:center;gap:8px;margin-top:2px">
                    <span style="font:500 11.5px var(--mono);background:var(--panel-2);border-radius:99px;padding:2px 9px;color:var(--ink-dim)">{{ s.envs }}</span>
                    <div style="flex:1"></div>
                    <span onClick="{{ s.openTerm }}" style="font:500 12px var(--sans);color:var(--brand-ink);padding:3px 8px;border:1px solid var(--rule);border-radius:6px" style-hover="border-color:var(--brand)">Terminal</span>
                  </div>
                </div>
              </sc-for>
            </div>
          </sc-if>
        </div>
      </sc-if>

      <sc-if value="{{ showSite }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:1160px;margin:0 auto;padding:24px 32px 120px;animation:ccfade .25s ease">
          <span onClick="{{ backToSites }}" style="font:500 12.5px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--brand-ink)">← Sites</span>
          <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:10px">
            <h1 style="margin:0;font:600 25px var(--sans);letter-spacing:-.02em">{{ dName }}</h1>
            <div style="display:flex;border:1px solid var(--rule);border-radius:8px;overflow:hidden">
              <span onClick="{{ setEnvProd }}" style="padding:5px 12px;cursor:pointer;font:500 12.5px var(--sans);background:{{ pBg }};color:{{ pFg }}">Production</span>
              <span onClick="{{ setEnvStag }}" style="padding:5px 12px;cursor:pointer;font:500 12.5px var(--sans);background:{{ sBg }};color:{{ sFg }};border-left:1px solid var(--rule)">Staging</span>
            </div>
            <div style="flex:1"></div>
            <button onClick="{{ dSync }}" title="Sync site data" style="width:34px;height:34px;display:grid;place-items:center;border:1px solid var(--rule);background:var(--paper);color:var(--ink-dim);border-radius:8px;cursor:pointer" style-hover="border-color:var(--brand);color:var(--ink)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7A9 9 0 005.5 5.5L4 7m0-4v4h4m-4 6a9 9 0 0014.5 5.5L20 17m0 4v-4h-4"></path></svg>
            </button>
            <button onClick="{{ dTerm }}" title="Open terminal (⌃`)" style="width:34px;height:34px;display:grid;place-items:center;border:1px solid var(--rule);background:var(--paper);color:var(--ink-dim);border-radius:8px;cursor:pointer" style-hover="border-color:var(--brand);color:var(--ink)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v14H4zM7.5 9l3 3-3 3M12.5 15H17"></path></svg>
            </button>
            <button style="border:none;background:var(--brand);color:white;font:600 13px var(--sans);padding:8px 14px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">Login to WordPress →</button>
          </div>
          <div style="font:400 13px var(--sans);color:var(--ink-dim);margin-top:6px">{{ dMeta }}</div>
          <div style="display:flex;border-bottom:1px solid var(--rule);margin-top:18px">
            <sc-for list="{{ dTabs }}" as="t" hint-placeholder-count="6">
              <span onClick="{{ t.go }}" style="padding:9px 2px;margin-right:24px;cursor:pointer;font:500 14px var(--sans);color:{{ t.fg }};border-bottom:2px solid {{ t.line }}" style-hover="color:var(--ink)">{{ t.label }}</span>
            </sc-for>
          </div>

          <sc-if value="{{ tabOverview }}" hint-placeholder-val="{{ true }}">
            <div style="display:flex;align-items:stretch;margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ statTiles }}" as="t" hint-placeholder-count="4">
                <div onClick="{{ t.go }}" title="{{ t.tip }}" style="flex:1;padding:11px 16px;border-right:1px solid var(--rule);cursor:pointer" style-hover="background:var(--panel)">
                  <div style="font:500 10.5px var(--sans);color:var(--ink-dim);text-transform:uppercase;letter-spacing:.06em">{{ t.k }}</div>
                  <div style="display:flex;align-items:baseline;gap:6px;margin-top:2px">
                    <span style="font:600 17px var(--sans)">{{ t.v }}</span>
                    <span style="font:500 11.5px var(--sans);color:{{ t.deltaFg }}">{{ t.delta }}</span>
                  </div>
                </div>
              </sc-for>
              <div onClick="{{ openStats }}" title="Open full stats" style="flex:1.3;display:flex;align-items:center;gap:14px;padding:11px 16px;cursor:pointer" style-hover="background:var(--panel)">
                <div style="flex:1;display:flex;align-items:flex-end;gap:3px;height:38px">
                  <sc-for list="{{ visitBars }}" as="b" hint-placeholder-count="14">
                    <div style="flex:1;height:{{ b.h }}%;background:{{ b.bg }};border-radius:2px 2px 0 0"></div>
                  </sc-for>
                </div>
                <span style="font:400 10.5px var(--sans);color:var(--ink-dim);white-space:nowrap">visits · 14d</span>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1.25fr 1fr;gap:14px;margin-top:14px;align-items:start">
              <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Credentials</h2></div>
                <sc-for list="{{ credRows }}" as="c" hint-placeholder-count="7">
                  <div style="display:flex;align-items:center;gap:12px;padding:9px 16px;border-bottom:1px solid var(--rule)">
                    <span style="flex:none;width:110px;font:500 12px var(--sans);color:var(--ink-dim)">{{ c.k }}</span>
                    <span style="flex:1;min-width:0;font:400 12.5px var(--mono);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ c.v }}</span>
                    <span onClick="{{ c.copy }}" style="flex:none;font:500 11.5px var(--sans);color:var(--brand-ink);cursor:pointer;border:1px solid var(--rule);border-radius:6px;padding:2px 8px" style-hover="border-color:var(--brand)">{{ c.mark }}</span>
                  </div>
                </sc-for>
              </section>
              <div style="display:flex;flex-direction:column;gap:14px">
                <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                  <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Environment</h2></div>
                  <sc-for list="{{ envRows }}" as="r" hint-placeholder-count="5">
                    <div style="display:flex;justify-content:space-between;gap:12px;padding:8px 16px;border-bottom:1px solid var(--rule)">
                      <span style="font:500 12.5px var(--sans);color:var(--ink-dim)">{{ r.k }}</span>
                      <span style="font:400 12.5px var(--mono)">{{ r.v }}</span>
                    </div>
                  </sc-for>
                </section>
                <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                  <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Domains</h2><span style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Configure →</span></div>
                  <sc-for list="{{ dDomains }}" as="d" hint-placeholder-count="2">
                    <div style="padding:9px 16px;border-bottom:1px solid var(--rule);font:400 13px var(--mono)">{{ d.name }}</div>
                  </sc-for>
                </section>
                <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                  <div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-bottom:1px solid var(--rule)">
                    <h2 style="margin:0;font:600 14px var(--sans)">Shared with</h2>
                    <span style="font:400 12px var(--sans);color:var(--ink-dim)">accounts with access</span>
                  </div>
                  <sc-for list="{{ sharedRows }}" as="sh" hint-placeholder-count="2">
                    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--rule)">
                      <span onClick="{{ sh.open }}" style="flex:1;min-width:0;cursor:pointer">
                        <span style="display:block;font:500 13px var(--sans);color:var(--brand-ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ sh.name }}</span>
                        <span style="display:block;font:400 11.5px var(--sans);color:var(--ink-dim)">{{ sh.sub }}</span>
                      </span>
                      <span style="font:500 11.5px var(--sans);background:{{ sh.lvlBg }};color:{{ sh.lvlFg }};border-radius:99px;padding:2px 9px;flex:none">{{ sh.level }}</span>
                      <sc-if value="{{ sh.removable }}" hint-placeholder-val="{{ false }}">
                        <span onClick="{{ sh.remove }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer;flex:none" style-hover="color:var(--bad)">✕</span>
                      </sc-if>
                    </div>
                  </sc-for>
                  <div style="display:flex;gap:8px;padding:9px 12px;background:var(--panel)">
                    <input value="{{ shareDraft }}" onInput="{{ onShareDraft }}" placeholder="Account name or email…" style="flex:1;min-width:0;height:30px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12px var(--sans);padding:0 9px;outline:none">
                    <button onClick="{{ doShare }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12px var(--sans);padding:5px 11px;border-radius:7px;cursor:pointer;flex:none" style-hover="border-color:var(--brand)">Share</button>
                  </div>
                </section>
                <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);padding:12px 16px;display:flex;flex-direction:column;gap:8px">
                  <h2 style="margin:0 0 2px;font:600 14px var(--sans)">Actions</h2>
                  <span onClick="{{ pushEnv }}" style="font:500 13px var(--sans);color:var(--brand-ink);cursor:pointer">Push staging → production</span>
                  <span onClick="{{ pullEnv }}" style="font:500 13px var(--sans);color:var(--brand-ink);cursor:pointer">Pull production → staging</span>
                  <span style="font:500 13px var(--sans);color:var(--brand-ink);cursor:pointer">Open phpMyAdmin</span>
                  <span style="font:500 13px var(--sans);color:var(--bad);cursor:pointer;margin-top:4px">Delete site…</span>
                </section>
              </div>
            </div>
          </sc-if>

          <sc-if value="{{ tabStats }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:8px;margin-top:16px;flex-wrap:wrap">
              <div style="position:relative">
                <div onClick="{{ ddToggleStatG }}" style="display:flex;align-items:center;gap:8px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);padding:0 10px;cursor:pointer;min-width:100px" style-hover="border-color:var(--brand)">
                  <span style="font:500 12.5px var(--sans);flex:1">{{ statG }}</span>
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                </div>
                <sc-if value="{{ ddStatGOpen }}" hint-placeholder-val="{{ false }}">
                  <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                  <div style="position:absolute;top:36px;left:0;z-index:40;width:140px;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                    <div style="max-height:176px;overflow-y:auto">
                      <sc-for list="{{ ddStatGOpts }}" as="o" hint-placeholder-count="3">
                        <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)">
                          <span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink)">{{ o.mark }}</span>
                          <span style="font:500 12.5px var(--sans)">{{ o.label }}</span>
                        </div>
                      </sc-for>
                    </div>
                  </div>
                </sc-if>
              </div>
              <div style="position:relative">
                <div onClick="{{ ddToggleStatR }}" style="display:flex;align-items:center;gap:8px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);padding:0 10px;cursor:pointer;min-width:130px" style-hover="border-color:var(--brand)">
                  <span style="font:500 12.5px var(--sans);flex:1">{{ statR }}</span>
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                </div>
                <sc-if value="{{ ddStatROpen }}" hint-placeholder-val="{{ false }}">
                  <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                  <div style="position:absolute;top:36px;left:0;z-index:40;width:150px;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                    <div style="max-height:176px;overflow-y:auto">
                      <sc-for list="{{ ddStatROpts }}" as="o" hint-placeholder-count="4">
                        <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)">
                          <span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink)">{{ o.mark }}</span>
                          <span style="font:500 12.5px var(--sans)">{{ o.label }}</span>
                        </div>
                      </sc-for>
                    </div>
                  </div>
                </sc-if>
              </div>
              <div style="flex:1"></div>
              <span style="font:500 12px var(--sans);color:var(--ink-dim)">Sharing</span>
              <sc-for list="{{ shareChips }}" as="c" hint-placeholder-count="3">
                <span onClick="{{ c.go }}" style="padding:5px 11px;border-radius:99px;border:1px solid {{ c.bd }};background:{{ c.bg }};color:{{ c.fg }};font:500 12.5px var(--sans);cursor:pointer">{{ c.label }}</span>
              </sc-for>
              <sc-if value="{{ statPrivate }}" hint-placeholder-val="{{ false }}">
                <input value="{{ statPw }}" onInput="{{ onStatPw }}" placeholder="password" style="width:110px;height:30px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12px var(--mono);padding:0 8px;outline:none">
              </sc-if>
              <sc-if value="{{ statShared }}" hint-placeholder-val="{{ false }}">
                <span onClick="{{ copyStatLink }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer;border:1px solid var(--rule);border-radius:6px;padding:3px 9px" style-hover="border-color:var(--brand)">{{ statLinkMark }}</span>
              </sc-if>
            </div>
            <div style="display:flex;align-items:stretch;margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ statTilesBig }}" as="t" hint-placeholder-count="4">
                <div style="flex:1;padding:13px 16px;border-right:1px solid var(--rule)">
                  <div style="font:500 10.5px var(--sans);color:var(--ink-dim);text-transform:uppercase;letter-spacing:.06em">{{ t.k }}</div>
                  <div style="display:flex;align-items:baseline;gap:6px;margin-top:2px">
                    <span style="font:600 20px var(--sans)">{{ t.v }}</span>
                    <span style="font:500 11.5px var(--sans);color:{{ t.deltaFg }}">{{ t.delta }}</span>
                  </div>
                </div>
              </sc-for>
            </div>
            <div style="margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);padding:16px">
              <div style="display:flex;align-items:baseline;justify-content:space-between">
                <h2 style="margin:0;font:600 14px var(--sans)">Pageviews</h2>
                <span style="font:400 11.5px var(--sans);color:var(--ink-dim)">{{ statChartLabel }}</span>
              </div>
              <div style="display:flex;align-items:flex-end;gap:3px;height:150px;margin-top:12px">
                <sc-for list="{{ statBars }}" as="b" hint-placeholder-count="28">
                  <div title="{{ b.tip }}" style="flex:1;height:{{ b.h }}%;background:{{ b.bg }};border-radius:2px 2px 0 0"></div>
                </sc-for>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;align-items:start">
              <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Top pages</h2></div>
                <sc-for list="{{ topPages }}" as="p" hint-placeholder-count="5">
                  <div style="display:flex;justify-content:space-between;gap:12px;padding:8px 16px;border-bottom:1px solid var(--rule)">
                    <span style="font:400 12.5px var(--mono);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ p.k }}</span>
                    <span style="font:500 12.5px var(--mono);color:var(--ink-dim)">{{ p.v }}</span>
                  </div>
                </sc-for>
              </section>
              <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Referrers</h2></div>
                <sc-for list="{{ topRefs }}" as="p" hint-placeholder-count="5">
                  <div style="display:flex;justify-content:space-between;gap:12px;padding:8px 16px;border-bottom:1px solid var(--rule)">
                    <span style="font:400 12.5px var(--mono);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ p.k }}</span>
                    <span style="font:500 12.5px var(--mono);color:var(--ink-dim)">{{ p.v }}</span>
                  </div>
                </sc-for>
              </section>
            </div>
            <div style="margin-top:14px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-bottom:1px solid var(--rule)">
                <span style="width:8px;height:8px;border-radius:50%;background:var(--ok)"></span>
                <h2 style="margin:0;font:600 14px var(--sans)">Performance monitor</h2>
                <span style="font:400 12px var(--sans);color:var(--ink-dim)">On · checks every 5 min</span>
              </div>
              <sc-for list="{{ perfRows }}" as="r" hint-placeholder-count="3">
                <div style="display:flex;justify-content:space-between;gap:12px;padding:9px 16px;border-bottom:1px solid var(--rule)">
                  <span style="font:500 12.5px var(--sans);color:var(--ink-dim)">{{ r.k }}</span>
                  <span style="font:400 12.5px var(--mono)">{{ r.v }}</span>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ tabAddons }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:10px;margin-top:16px">
              <div style="display:flex;border:1px solid var(--rule);border-radius:8px;overflow:hidden">
                <span onClick="{{ setAddP }}" style="padding:5px 12px;cursor:pointer;font:500 12.5px var(--sans);background:{{ akpBg }};color:{{ akpFg }}">Plugins</span>
                <span onClick="{{ setAddT }}" style="padding:5px 12px;cursor:pointer;font:500 12.5px var(--sans);background:{{ aktBg }};color:{{ aktFg }};border-left:1px solid var(--rule)">Themes</span>
              </div>
              <div style="flex:1"></div>
              <sc-if value="{{ hasUpdates }}" hint-placeholder-val="{{ false }}">
                <button onClick="{{ doUpdateAll }}" style="border:1px solid var(--warn);background:var(--warn-soft);color:var(--ink);font:500 13px var(--sans);padding:7px 13px;border-radius:8px;cursor:pointer">{{ updateAllLabel }}</button>
              </sc-if>
              <button title="Upload / WordPress.org / Envato" style="border:1px solid var(--rule);background:var(--paper);color:var(--ink);font:500 13px var(--sans);padding:7px 13px;border-radius:8px;cursor:pointer" style-hover="border-color:var(--brand)">+ Add</button>
            </div>
            <div style="margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ addons }}" as="a" hint-placeholder-count="6">
                <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
                  <span style="flex:none;width:8px;height:8px;border-radius:50%;background:{{ a.dot }}" title="{{ a.statusLabel }}"></span>
                  <span style="flex:1;min-width:0"><span style="display:block;font:500 13.5px var(--sans)">{{ a.name }}</span><span style="display:block;font:400 11.5px var(--mono);color:var(--ink-dim)">{{ a.slug }}</span></span>
                  <span style="font:400 12.5px var(--mono);color:var(--ink-dim)">{{ a.v }}</span>
                  <sc-if value="{{ a.upd }}" hint-placeholder-val="{{ false }}">
                    <span style="font:500 12px var(--mono);background:var(--warn-soft);border-radius:99px;padding:2px 9px">→ {{ a.latest }}</span>
                  </sc-if>
                  <sc-if value="{{ a.vulnB }}" hint-placeholder-val="{{ false }}">
                    <span style="font:600 11px var(--sans);letter-spacing:.04em;background:var(--bad-soft);color:var(--bad);border-radius:99px;padding:2px 9px">VULN</span>
                  </sc-if>
                  <div style="flex:none;display:flex;gap:8px;margin-left:8px">
                    <sc-if value="{{ a.upd }}" hint-placeholder-val="{{ false }}">
                      <button onClick="{{ a.doUpdate }}" style="border:none;background:var(--brand);color:white;font:500 12px var(--sans);padding:5px 11px;border-radius:6px;cursor:pointer" style-hover="filter:brightness(1.08)">Update</button>
                    </sc-if>
                    <button onClick="{{ a.doToggle }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--ink-dim);font:500 12px var(--sans);padding:5px 11px;border-radius:6px;cursor:pointer" style-hover="border-color:var(--brand);color:var(--ink)">{{ a.toggleLabel }}</button>
                  </div>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ tabVersions }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:10px;margin-top:16px">
              <span style="font:400 13px var(--sans);color:var(--ink-dim)">Git-commit history — quicksaves and update events on one timeline.</span>
              <div style="flex:1"></div>
              <button onClick="{{ newQuicksave }}" style="border:none;background:var(--brand);color:white;font:600 13px var(--sans);padding:8px 14px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">+ New quicksave</button>
            </div>
            <div style="margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ quicksaves }}" as="qk" hint-placeholder-count="4">
                <div style="border-bottom:1px solid var(--rule)">
                  <div style="display:flex;align-items:center;gap:11px;padding:12px 16px;flex-wrap:wrap">
                    <span style="font:500 12px var(--mono);background:var(--panel-2);border-radius:6px;padding:2px 8px">{{ qk.hash }}</span>
                    <span style="font:500 11.5px var(--sans);background:{{ qk.kindBg }};border-radius:99px;padding:2px 9px">{{ qk.kind }}</span>
                    <span style="font:500 13.5px var(--sans)">{{ qk.desc }}</span>
                    <span style="font:400 12px var(--sans);color:var(--ink-dim)">{{ qk.files }}</span>
                    <div style="flex:1"></div>
                    <span style="font:400 12px var(--mono);color:var(--ink-dim)">{{ qk.when }}</span>
                    <span onClick="{{ qk.openD }}" style="font:500 12.5px var(--sans);color:var(--brand-ink);cursor:pointer;border:1px solid var(--rule);border-radius:6px;padding:3px 10px" style-hover="border-color:var(--brand)">Details</span>
                    <span onClick="{{ qk.doRollback }}" style="font:500 12.5px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Rollback</span>
                  </div>

                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ tabBackups }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:10px;margin-top:16px">
              <span style="font:400 13px var(--sans);color:var(--ink-dim)">Restic · daily at 3:00 AM UTC · direct to Backblaze B2 · point-in-time restore to any date</span>
              <div style="flex:1"></div>
              <button onClick="{{ backupNow }}" style="border:none;background:var(--brand);color:white;font:600 13px var(--sans);padding:8px 14px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">Back up now</button>
            </div>
            <div style="margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ backups }}" as="b" hint-placeholder-count="4">
                <div style="border-bottom:1px solid var(--rule)">
                  <div style="display:flex;align-items:center;gap:12px;padding:12px 16px">
                    <span style="font:500 12px var(--mono);background:var(--panel-2);border-radius:6px;padding:2px 8px">{{ b.id }}</span>
                    <span style="font:500 13.5px var(--sans)">{{ b.when }}</span>
                    <span style="font:400 12.5px var(--mono);color:var(--ink-dim)">{{ b.size }}</span>
                    <span style="font:400 12.5px var(--mono);color:var(--ink-dim)">{{ b.files }}</span>
                    <div style="flex:1"></div>
                    <span onClick="{{ b.openB }}" style="font:500 12.5px var(--sans);color:var(--brand-ink);cursor:pointer;border:1px solid var(--rule);border-radius:6px;padding:3px 10px" style-hover="border-color:var(--brand)">Browse &amp; download</span>
                    <span onClick="{{ b.doRestore }}" style="font:500 12.5px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Restore…</span>
                  </div>

                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ tabSnapshots }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:10px;margin-top:16px;flex-wrap:wrap">
              <span style="font:400 13px var(--sans);color:var(--ink-dim)">On-demand zips for the client — each gets a 24-hour tokenized download link.</span>
              <div style="flex:1"></div>
              <div style="position:relative">
                <div onClick="{{ ddToggleSnap }}" style="display:flex;align-items:center;gap:8px;height:34px;border:1px solid var(--rule);border-radius:8px;background:var(--paper);padding:0 11px;cursor:pointer;min-width:130px" style-hover="border-color:var(--brand)">
                  <span style="font:500 13px var(--sans);flex:1">{{ snapFilter }}</span>
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                </div>
                <sc-if value="{{ ddSnapOpen }}" hint-placeholder-val="{{ false }}">
                  <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                  <div style="position:absolute;top:38px;left:0;z-index:40;width:170px;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                    <input autofocus value="{{ ddQ }}" onInput="{{ onDdQ }}" placeholder="Filter…" style="width:100%;height:28px;border:1px solid var(--rule);border-radius:6px;background:var(--panel);color:var(--ink);font:400 12.5px var(--sans);padding:0 8px;outline:none;margin-bottom:4px">
                    <div style="max-height:176px;overflow-y:auto">
                      <sc-for list="{{ ddSnapOpts }}" as="o" hint-placeholder-count="5">
                        <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)">
                          <span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink)">{{ o.mark }}</span>
                          <span style="font:500 12.5px var(--sans)">{{ o.label }}</span>
                        </div>
                      </sc-for>
                    </div>
                  </div>
                </sc-if>
              </div>
              <button onClick="{{ createSnap }}" style="border:none;background:var(--brand);color:white;font:600 13px var(--sans);padding:8px 14px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">Create snapshot</button>
            </div>
            <div style="margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ snapshots }}" as="sn" hint-placeholder-count="3">
                <div style="display:flex;align-items:center;gap:11px;padding:12px 16px;border-bottom:1px solid var(--rule);flex-wrap:wrap">
                  <span style="font:500 13.5px var(--sans)">{{ sn.name }}</span>
                  <span style="font:500 11.5px var(--mono);background:var(--panel-2);border-radius:6px;padding:2px 8px">{{ sn.id }}</span>
                  <span style="font:500 11.5px var(--sans);background:var(--brand-soft);color:var(--brand-ink);border-radius:99px;padding:2px 9px">{{ sn.filter }}</span>
                  <span style="font:400 12px var(--mono);color:var(--ink-dim)">{{ sn.size }}</span>
                  <span style="font:400 12px var(--mono);color:var(--ink-dim)">{{ sn.when }}</span>
                  <div style="flex:1"></div>
                  <span style="font:500 12px var(--sans);color:{{ sn.expFg }}">{{ sn.expLabel }}</span>
                  <sc-if value="{{ sn.live }}" hint-placeholder-val="{{ false }}">
                    <span onClick="{{ sn.copyLink }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer;border:1px solid var(--rule);border-radius:6px;padding:3px 9px" style-hover="border-color:var(--brand)">{{ sn.mark }}</span>
                  </sc-if>
                  <sc-if value="{{ sn.expired }}" hint-placeholder-val="{{ false }}">
                    <span onClick="{{ sn.regen }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">New 24h link</span>
                  </sc-if>
                  <span onClick="{{ sn.doDl }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Download</span>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ tabTimeline }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:10px;margin-top:16px">
              <span style="font:400 13px var(--sans);color:var(--ink-dim)">Human notes on what happened and why — restores, installs, DNS changes.</span>
              <div style="flex:1"></div>
              <span onClick="{{ exportTl }}" style="font:500 12.5px var(--sans);color:var(--brand-ink);cursor:pointer">Export JSON</span>
            </div>
            <div style="margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <div style="display:flex;gap:10px;padding:10px 16px;background:var(--panel);border-bottom:1px solid var(--rule)">
                <input value="{{ tlDraft }}" onInput="{{ onTlDraft }}" placeholder="Add a timeline entry — what did you do?" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--sans);padding:0 9px;outline:none">
                <button onClick="{{ addTl }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12.5px var(--sans);padding:6px 13px;border-radius:7px;cursor:pointer" style-hover="border-color:var(--brand)">Add entry</button>
              </div>
              <sc-for list="{{ tlRows }}" as="t" hint-placeholder-count="5">
                <div style="border-bottom:1px solid var(--rule)">
                  <sc-if value="{{ t.notEditing }}" hint-placeholder-val="{{ true }}">
                    <div style="display:flex;align-items:center;gap:12px;padding:11px 16px">
                      <span style="flex:none;width:28px;height:28px;border-radius:50%;background:var(--panel-2);color:var(--ink-dim);display:grid;place-items:center;font:600 10.5px var(--sans)">{{ t.init }}</span>
                      <span style="flex:1;min-width:0;font:400 13.5px/1.5 var(--sans)">{{ t.text }}</span>
                      <span style="flex:none;font:400 12px var(--sans);color:var(--ink-dim)">{{ t.who }}</span>
                      <span style="flex:none;font:400 12px var(--mono);color:var(--ink-dim)">{{ t.when }}</span>
                      <span onClick="{{ t.startEdit }}" style="flex:none;font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Edit</span>
                      <span onClick="{{ t.del }}" style="flex:none;font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">✕</span>
                    </div>
                  </sc-if>
                  <sc-if value="{{ t.editing }}" hint-placeholder-val="{{ false }}">
                    <div style="display:flex;align-items:center;gap:10px;padding:9px 16px;background:var(--panel)">
                      <input value="{{ tlEditText }}" onInput="{{ onTlEditText }}" style="flex:1;height:32px;border:1px solid var(--brand);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--sans);padding:0 9px;outline:none">
                      <button onClick="{{ t.doneEdit }}" style="border:none;background:var(--brand);color:white;font:600 12px var(--sans);padding:6px 12px;border-radius:6px;cursor:pointer" style-hover="filter:brightness(1.08)">Done</button>
                      <span onClick="{{ t.cancelEdit }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer">Cancel</span>
                    </div>
                  </sc-if>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ tabUsers }}" hint-placeholder-val="{{ false }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ dUsers }}" as="u" hint-placeholder-count="3">
                <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--rule)">
                  <span style="flex:none;width:30px;height:30px;border-radius:50%;background:var(--panel-2);color:var(--ink-dim);display:grid;place-items:center;font:600 11px var(--sans)">{{ u.init }}</span>
                  <span style="flex:1;min-width:0"><span style="display:block;font:500 13.5px var(--sans)">{{ u.n }}</span><span style="display:block;font:400 12px var(--mono);color:var(--ink-dim)">{{ u.e }}</span></span>
                  <span style="font:500 12px var(--sans);background:var(--panel-2);border-radius:99px;padding:3px 10px;color:var(--ink-dim)">{{ u.role }}</span>
                  <span style="font:400 12px var(--mono);color:var(--ink-dim);width:64px;text-align:right">{{ u.last }}</span>
                  <button onClick="{{ u.magic }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12.5px var(--sans);padding:5px 11px;border-radius:6px;cursor:pointer" style-hover="border-color:var(--brand)">Magic login</button>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ tabLogs }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:8px;margin-top:16px">
              <sc-for list="{{ logChips }}" as="c" hint-placeholder-count="3">
                <span onClick="{{ c.go }}" style="padding:5px 11px;border-radius:99px;border:1px solid {{ c.bd }};background:{{ c.bg }};color:{{ c.fg }};font:500 12px var(--mono);cursor:pointer">{{ c.label }}</span>
              </sc-for>
              <div style="flex:1"></div>
              <span style="font:400 12px var(--sans);color:var(--ink-dim)">{{ logMeta }}</span>
            </div>
            <div style="margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--panel);padding:14px 16px;max-height:420px;overflow-y:auto">
              <sc-for list="{{ logLines }}" as="l" hint-placeholder-count="5">
                <div style="font:400 12.5px/1.8 var(--mono);color:var(--ink-dim);white-space:pre-wrap;word-break:break-all">{{ l.text }}</div>
              </sc-for>
            </div>
          </sc-if>
        </div>
      </sc-if>

      <sc-if value="{{ showDomains }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:1240px;margin:0 auto;padding:28px 32px 120px;animation:ccfade .25s ease">
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <h1 style="margin:0;font:600 26px var(--sans);letter-spacing:-.02em">Domains</h1>
            <span style="font:400 14px var(--sans);color:var(--ink-dim)">{{ domCount }}</span>
            <div style="flex:1"></div>
            <div style="display:flex;align-items:center;gap:7px;height:34px;padding:0 10px;border:1px solid var(--rule);border-radius:8px;background:var(--paper);width:230px">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2" stroke-linecap="round"><path d="M11 4a7 7 0 100 14 7 7 0 000-14zm10 17-5.2-5.2"></path></svg>
              <input value="{{ dq }}" onInput="{{ onDq }}" placeholder="Filter domains…" style="border:none;outline:none;background:transparent;color:var(--ink);font:400 13.5px var(--sans);width:100%">
            </div>
            <button onClick="{{ openNewDomain }}" style="border:none;background:var(--brand);color:white;font:600 13.5px var(--sans);padding:8px 14px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">+ New domain</button>
          </div>
          <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
            <div style="display:grid;grid-template-columns:2fr 1.3fr 1fr 0.7fr 0.9fr;gap:12px;padding:10px 16px;border-bottom:1px solid var(--rule);font:600 11.5px var(--sans);letter-spacing:.07em;text-transform:uppercase;color:var(--ink-dim)">
              <span>Domain</span><span>Registrar</span><span>Expires</span><span>DNS</span><span>Auto-renew</span>
            </div>
            <sc-for list="{{ domRows }}" as="d" hint-placeholder-count="6">
              <div onClick="{{ d.open }}" style="display:grid;grid-template-columns:2fr 1.3fr 1fr 0.7fr 0.9fr;gap:12px;padding:12px 16px;border-bottom:1px solid var(--rule);align-items:center;cursor:pointer;font:400 14px var(--sans)" style-hover="background:var(--panel)">
                <span style="min-width:0"><span style="display:block;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ d.name }}</span><span style="display:block;font:400 12px var(--sans);color:var(--ink-dim)">{{ d.account }}</span></span>
                <span style="color:var(--ink-dim)">{{ d.registrar }}</span>
                <span style="font:400 13px var(--mono);color:{{ d.expFg }}">{{ d.expires }}</span>
                <span style="font:500 12.5px var(--sans);color:{{ d.dnsFg }}">{{ d.dnsLabel }}</span>
                <span style="font:500 12.5px var(--sans);color:{{ d.autoFg }}">{{ d.autoLabel }}</span>
              </div>
            </sc-for>
          </div>
        </div>
      </sc-if>

      <sc-if value="{{ showDomain }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:1160px;margin:0 auto;padding:24px 32px 120px;animation:ccfade .25s ease">
          <span onClick="{{ domBack }}" style="font:500 12.5px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--brand-ink)">← Domains</span>
          <div style="display:flex;align-items:baseline;gap:14px;flex-wrap:wrap;margin-top:10px">
            <h1 style="margin:0;font:600 25px var(--sans);letter-spacing:-.02em">{{ domName }}</h1>
          </div>
          <div style="font:400 13px var(--sans);color:var(--ink-dim);margin-top:6px">{{ domStatus }}</div>
          <div style="display:flex;border-bottom:1px solid var(--rule);margin-top:18px">
            <sc-for list="{{ domTabs }}" as="t" hint-placeholder-count="4">
              <span onClick="{{ t.go }}" style="padding:9px 2px;margin-right:24px;cursor:pointer;font:500 14px var(--sans);color:{{ t.fg }};border-bottom:2px solid {{ t.line }}" style-hover="color:var(--ink)">{{ t.label }}</span>
            </sc-for>
          </div>

          <sc-if value="{{ domTabDns }}" hint-placeholder-val="{{ true }}">
            <div style="display:flex;align-items:center;gap:14px;margin-top:16px">
              <span style="font:400 13px var(--sans);color:var(--ink-dim)">Click a record to edit. Changes are staged locally, then saved to the zone as one bulk update.</span>
              <div style="flex:1"></div>
              <span onClick="{{ openZoneDlg }}" style="font:500 12.5px var(--sans);color:var(--brand-ink);cursor:pointer">Import zone…</span>
              <span onClick="{{ exportZone }}" style="font:500 12.5px var(--sans);color:var(--brand-ink);cursor:pointer">Export BIND</span>
            </div>
            <div style="margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ dnsRows }}" as="r" hint-placeholder-count="7">
                <div style="border-bottom:1px solid var(--rule)">
                  <sc-if value="{{ r.notEditing }}" hint-placeholder-val="{{ true }}">
                    <div onClick="{{ r.startEdit }}" style="display:flex;align-items:center;gap:12px;padding:9px 16px;cursor:pointer" style-hover="background:var(--panel)">
                      <span style="flex:none;width:58px;text-align:center;font:600 11px var(--mono);background:{{ r.bg }};border-radius:6px;padding:3px 0">{{ r.type }}</span>
                      <span style="flex:none;width:150px;font:400 12.5px var(--mono);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.name }}</span>
                      <span style="flex:1;min-width:0;font:400 12.5px var(--mono);color:var(--ink-dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.value }}</span>
                      <span style="flex:none;font:400 11.5px var(--mono);color:var(--ink-dim)">{{ r.ttl }}</span>
                      <span onClick="{{ r.del }}" style="flex:none;font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Delete</span>
                    </div>
                  </sc-if>
                  <sc-if value="{{ r.editing }}" hint-placeholder-val="{{ false }}">
                    <div style="display:flex;align-items:center;gap:10px;padding:8px 16px;background:var(--panel)">
                      <span style="flex:none;width:58px;text-align:center;font:600 11px var(--mono);background:{{ r.bg }};border-radius:6px;padding:3px 0">{{ r.type }}</span>
                      <input value="{{ dnsEN }}" onInput="{{ onDnsEN }}" style="width:150px;height:30px;border:1px solid var(--brand);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none">
                      <input value="{{ dnsEV }}" onInput="{{ onDnsEV }}" style="flex:1;min-width:0;height:30px;border:1px solid var(--brand);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none">
                      <input value="{{ dnsETtl }}" onInput="{{ onDnsETtl }}" style="width:64px;height:30px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12px var(--mono);padding:0 8px;outline:none">
                      <button onClick="{{ dnsEditDone }}" style="border:none;background:var(--brand);color:white;font:600 12px var(--sans);padding:6px 12px;border-radius:6px;cursor:pointer" style-hover="filter:brightness(1.08)">Done</button>
                      <span onClick="{{ dnsEditCancel }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer">Cancel</span>
                    </div>
                  </sc-if>
                </div>
              </sc-for>
              <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:var(--panel)">
                <div style="position:relative">
                  <div onClick="{{ ddToggleDns }}" style="display:flex;align-items:center;gap:8px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);padding:0 10px;cursor:pointer;min-width:88px" style-hover="border-color:var(--brand)">
                    <span style="font:500 12px var(--mono);flex:1">{{ dnsT }}</span>
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                  </div>
                  <sc-if value="{{ ddDnsOpen }}" hint-placeholder-val="{{ false }}">
                    <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                    <div style="position:absolute;bottom:38px;left:0;z-index:40;width:160px;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                      <input autofocus value="{{ ddQ }}" onInput="{{ onDdQ }}" placeholder="Filter types…" style="width:100%;height:28px;border:1px solid var(--rule);border-radius:6px;background:var(--panel);color:var(--ink);font:400 12px var(--mono);padding:0 8px;outline:none;margin-bottom:4px">
                      <div style="max-height:176px;overflow-y:auto">
                        <sc-for list="{{ ddDnsOpts }}" as="o" hint-placeholder-count="5">
                          <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)">
                            <span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink)">{{ o.mark }}</span>
                            <span style="font:500 12px var(--mono)">{{ o.label }}</span>
                          </div>
                        </sc-for>
                      </div>
                    </div>
                  </sc-if>
                </div>
                <input value="{{ dnsN }}" onInput="{{ onDnsN }}" placeholder="name (@ for root)" style="width:150px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none">
                <input value="{{ dnsV }}" onInput="{{ onDnsV }}" placeholder="value" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none">
                <button onClick="{{ addRec }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12.5px var(--sans);padding:6px 13px;border-radius:7px;cursor:pointer" style-hover="border-color:var(--brand)">Add record</button>
              </div>
            </div>
            <sc-if value="{{ dnsDirty }}" hint-placeholder-val="{{ false }}">
              <div style="display:flex;align-items:center;gap:12px;margin-top:12px;padding:9px 14px;border:1px solid var(--warn);background:var(--warn-soft);border-radius:10px">
                <span style="font:500 13px var(--sans)">Unsaved zone changes</span>
                <div style="flex:1"></div>
                <span onClick="{{ discardDns }}" style="font:500 12.5px var(--sans);color:var(--ink-dim);cursor:pointer">Discard</span>
                <button onClick="{{ saveDns }}" style="border:none;background:var(--brand);color:white;font:600 12.5px var(--sans);padding:7px 13px;border-radius:7px;cursor:pointer" style-hover="filter:brightness(1.08)">Save zone</button>
              </div>
            </sc-if>
          </sc-if>

          <sc-if value="{{ domTabReg }}" hint-placeholder-val="{{ false }}">
            <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:14px;margin-top:18px;align-items:start">
              <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Registration</h2></div>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--rule)">
                  <span style="width:130px;font:500 12.5px var(--sans);color:var(--ink-dim)">Registrar</span>
                  <span style="font:400 13px var(--sans)">{{ regRegistrar }}</span>
                </div>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--rule)">
                  <span style="width:130px;font:500 12.5px var(--sans);color:var(--ink-dim)">Expires</span>
                  <span style="font:400 13px var(--mono);color:{{ regExpFg }}">{{ regExpires }}</span>
                  <sc-if value="{{ regWarn }}" hint-placeholder-val="{{ false }}">
                    <button onClick="{{ renewNow }}" style="border:none;background:var(--brand);color:white;font:600 12px var(--sans);padding:5px 11px;border-radius:6px;cursor:pointer" style-hover="filter:brightness(1.08)">Renew now</button>
                  </sc-if>
                </div>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--rule)">
                  <span style="width:130px;font:500 12.5px var(--sans);color:var(--ink-dim)">{{ togAuto.label }}</span>
                  <span onClick="{{ togAuto.flip }}" style="width:34px;height:20px;border-radius:99px;background:{{ togAuto.bg }};display:flex;align-items:center;padding:2px;justify-content:{{ togAuto.just }};cursor:pointer"><span style="width:16px;height:16px;border-radius:50%;background:white"></span></span>
                  <span style="font:400 12.5px var(--sans);color:var(--ink-dim)">{{ togAuto.state }}</span>
                </div>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--rule)">
                  <span style="width:130px;font:500 12.5px var(--sans);color:var(--ink-dim)">{{ togLock.label }}</span>
                  <span onClick="{{ togLock.flip }}" style="width:34px;height:20px;border-radius:99px;background:{{ togLock.bg }};display:flex;align-items:center;padding:2px;justify-content:{{ togLock.just }};cursor:pointer"><span style="width:16px;height:16px;border-radius:50%;background:white"></span></span>
                  <span style="font:400 12.5px var(--sans);color:var(--ink-dim)">{{ togLock.state }}</span>
                </div>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--rule)">
                  <span style="width:130px;font:500 12.5px var(--sans);color:var(--ink-dim)">{{ togPriv.label }}</span>
                  <span onClick="{{ togPriv.flip }}" style="width:34px;height:20px;border-radius:99px;background:{{ togPriv.bg }};display:flex;align-items:center;padding:2px;justify-content:{{ togPriv.just }};cursor:pointer"><span style="width:16px;height:16px;border-radius:50%;background:white"></span></span>
                  <span style="font:400 12.5px var(--sans);color:var(--ink-dim)">{{ togPriv.state }}</span>
                </div>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 16px">
                  <span style="width:130px;font:500 12.5px var(--sans);color:var(--ink-dim)">Auth code</span>
                  <span style="font:400 13px var(--mono)">•••-••••-••••</span>
                  <span onClick="{{ authCopy }}" style="font:500 11.5px var(--sans);color:var(--brand-ink);cursor:pointer;border:1px solid var(--rule);border-radius:6px;padding:2px 8px" style-hover="border-color:var(--brand)">{{ authMark }}</span>
                </div>
              </section>
              <div style="display:flex;flex-direction:column;gap:14px">
                <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                  <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Nameservers</h2><span onClick="{{ openNsvDlg }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Edit</span></div>
                  <sc-for list="{{ nsList }}" as="ns" hint-placeholder-count="3">
                    <div style="padding:9px 16px;border-bottom:1px solid var(--rule);font:400 12.5px var(--mono);color:var(--ink-dim)">{{ ns.n }}</div>
                  </sc-for>
                </section>
                <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                  <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Contacts</h2><span onClick="{{ openCtDlg }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Edit contacts</span></div>
                  <div style="padding:12px 16px">
                    <div style="font:500 13.5px var(--sans)">{{ ctLine1 }}</div>
                    <div style="font:400 12.5px/1.6 var(--sans);color:var(--ink-dim);margin-top:3px">{{ ctLine2 }}<br>{{ ctLine3 }}</div>
                    <div style="font:400 12px var(--sans);color:var(--ink-dim);margin-top:8px">Same contact for all 4 roles (registrant, admin, tech, billing).</div>
                  </div>
                </section>
              </div>
            </div>
          </sc-if>

          <sc-if value="{{ domTabFwd }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:10px;margin-top:16px">
              <span style="font:400 13px var(--sans);color:var(--ink-dim)">Forwards route through Mailgun on the root domain. New addresses verify automatically.</span>
            </div>
            <div style="margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ fwdRows }}" as="f" hint-placeholder-count="3">
                <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
                  <span style="font:400 13px var(--mono)">{{ f.aliasFull }}</span>
                  <span style="color:var(--ink-dim)">→</span>
                  <span style="font:400 13px var(--mono);color:var(--ink-dim)">{{ f.dest }}</span>
                  <div style="flex:1"></div>
                  <span style="font:500 12px var(--sans);color:{{ f.stFg }}">{{ f.status }}</span>
                  <span onClick="{{ f.del }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Delete</span>
                </div>
              </sc-for>
              <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:var(--panel)">
                <input value="{{ fwdAlias }}" onInput="{{ onFwdAlias }}" placeholder="alias (or * for catch-all)" style="width:200px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none">
                <span style="color:var(--ink-dim);font:400 13px var(--sans)">forwards to</span>
                <input value="{{ fwdDest }}" onInput="{{ onFwdDest }}" placeholder="destination@example.com" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none">
                <button onClick="{{ addFwd }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12.5px var(--sans);padding:6px 13px;border-radius:7px;cursor:pointer" style-hover="border-color:var(--brand)">Add forward</button>
              </div>
            </div>
          </sc-if>

          <sc-if value="{{ domTabSnd }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:10px;margin-top:16px">
              <span style="font:400 13px var(--sans);color:var(--ink-dim)">Transactional sending via <span style="font:500 12.5px var(--mono);color:var(--ink)">{{ mgHost }}</span> · {{ mgSupp }}</span>
              <div style="flex:1"></div>
              <button onClick="{{ mgDeploy }}" style="border:none;background:var(--brand);color:white;font:600 13px var(--sans);padding:8px 14px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">Deploy to site (SMTP)</button>
            </div>
            <div style="margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">DNS verification</h2></div>
              <sc-for list="{{ mgRecs }}" as="r" hint-placeholder-count="3">
                <div style="display:flex;align-items:center;gap:12px;padding:9px 16px;border-bottom:1px solid var(--rule)">
                  <span style="flex:none;width:58px;text-align:center;font:600 11px var(--mono);background:var(--panel-2);border-radius:6px;padding:3px 0">{{ r.type }}</span>
                  <span style="flex:none;width:210px;font:400 12px var(--mono);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.host }}</span>
                  <span style="flex:1;min-width:0;font:400 12px var(--mono);color:var(--ink-dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.value }}</span>
                  <span style="flex:none;font:500 12px var(--sans);color:{{ r.stFg }}">{{ r.stLabel }}</span>
                  <sc-if value="{{ r.pending }}" hint-placeholder-val="{{ false }}">
                    <span onClick="{{ r.verify }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Verify</span>
                  </sc-if>
                </div>
              </sc-for>
            </div>
            <div style="margin-top:14px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Recent events</h2><span style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">View all logs →</span></div>
              <sc-for list="{{ mgEvents }}" as="ev" hint-placeholder-count="4">
                <div style="display:flex;gap:12px;padding:9px 16px;border-bottom:1px solid var(--rule);align-items:baseline">
                  <span style="flex:none;width:70px;font:500 11.5px var(--mono);color:var(--ink-dim);text-align:right">{{ ev.t }}</span>
                  <span style="font:400 13px/1.5 var(--sans)">{{ ev.text }}</span>
                </div>
              </sc-for>
            </div>
          </sc-if>
        </div>
      </sc-if>

      <sc-if value="{{ showAccounts }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:1240px;margin:0 auto;padding:28px 32px 120px;animation:ccfade .25s ease">
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <h1 style="margin:0;font:600 26px var(--sans);letter-spacing:-.02em">Accounts</h1>
            <span style="font:400 14px var(--sans);color:var(--ink-dim)">{{ accCount }}</span>
            <div style="flex:1"></div>
            <div style="display:flex;align-items:center;gap:7px;height:34px;padding:0 10px;border:1px solid var(--rule);border-radius:8px;background:var(--paper);width:230px">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2" stroke-linecap="round"><path d="M11 4a7 7 0 100 14 7 7 0 000-14zm10 17-5.2-5.2"></path></svg>
              <input value="{{ aq }}" onInput="{{ onAq }}" placeholder="Filter accounts…" style="border:none;outline:none;background:transparent;color:var(--ink);font:400 13.5px var(--sans);width:100%">
            </div>
            <button style="border:none;background:var(--brand);color:white;font:600 13.5px var(--sans);padding:8px 14px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">+ New account</button>
          </div>
          <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
            <div style="display:grid;grid-template-columns:2fr 0.6fr 0.6fr 0.7fr 1.3fr 1fr;gap:12px;padding:10px 16px;border-bottom:1px solid var(--rule);font:600 11.5px var(--sans);letter-spacing:.07em;text-transform:uppercase;color:var(--ink-dim)">
              <span>Account</span><span>Users</span><span>Sites</span><span>Domains</span><span>Plan</span><span>Billing</span>
            </div>
            <sc-for list="{{ accRows }}" as="a" hint-placeholder-count="6">
              <div onClick="{{ a.open }}" style="display:grid;grid-template-columns:2fr 0.6fr 0.6fr 0.7fr 1.3fr 1fr;gap:12px;padding:12px 16px;border-bottom:1px solid var(--rule);align-items:center;cursor:pointer;font:400 14px var(--sans)" style-hover="background:var(--panel)">
                <span style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ a.name }}</span>
                <span style="font:400 13px var(--mono);color:var(--ink-dim)">{{ a.users }}</span>
                <span style="font:400 13px var(--mono);color:var(--ink-dim)">{{ a.sites }}</span>
                <span style="font:400 13px var(--mono);color:var(--ink-dim)">{{ a.domains }}</span>
                <span style="color:var(--ink-dim);font:400 13px var(--sans)">{{ a.plan }}</span>
                <span style="font:500 12.5px var(--sans);color:{{ a.billFg }}">{{ a.billLabel }}</span>
              </div>
            </sc-for>
          </div>
        </div>
      </sc-if>

      <sc-if value="{{ showAccount }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:1160px;margin:0 auto;padding:24px 32px 120px;animation:ccfade .25s ease">
          <span onClick="{{ accBack }}" style="font:500 12.5px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--brand-ink)">← Accounts</span>
          <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:10px">
            <h1 style="margin:0;font:600 25px var(--sans);letter-spacing:-.02em">{{ accName }}</h1>
            <div style="flex:1"></div>
            <button style="border:none;background:var(--brand);color:white;font:600 13px var(--sans);padding:8px 14px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">Transfer ownership…</button>
          </div>
          <div style="font:400 13px var(--sans);color:var(--ink-dim);margin-top:6px">{{ accMeta }}</div>
          <div style="display:flex;border-bottom:1px solid var(--rule);margin-top:18px">
            <sc-for list="{{ accTabs }}" as="t" hint-placeholder-count="5">
              <span onClick="{{ t.go }}" style="padding:9px 2px;margin-right:24px;cursor:pointer;font:500 14px var(--sans);color:{{ t.fg }};border-bottom:2px solid {{ t.line }}" style-hover="color:var(--ink)">{{ t.label }}</span>
            </sc-for>
          </div>

          <sc-if value="{{ accTabUsers }}" hint-placeholder-val="{{ true }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">People</h2></div>
              <sc-for list="{{ accUsers }}" as="u" hint-placeholder-count="3">
                <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
                  <span style="flex:none;width:30px;height:30px;border-radius:50%;background:var(--panel-2);color:var(--ink-dim);display:grid;place-items:center;font:600 11px var(--sans)">{{ u.init }}</span>
                  <span style="flex:1;min-width:0"><span style="display:block;font:500 13.5px var(--sans)">{{ u.n }}</span><span style="display:block;font:400 12px var(--mono);color:var(--ink-dim)">{{ u.e }}</span></span>
                  <span style="font:500 12px var(--sans);background:{{ u.lvlBg }};color:{{ u.lvlFg }};border-radius:99px;padding:3px 10px">{{ u.level }}</span>
                  <span style="font:400 12px var(--mono);color:var(--ink-dim);width:60px;text-align:right">{{ u.last }}</span>
                  <span onClick="{{ u.switchTo }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Access as</span>
                  <span onClick="{{ u.remove }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Remove</span>
                </div>
              </sc-for>
              <sc-for list="{{ accInvites }}" as="iv" hint-placeholder-count="1">
                <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule);background:var(--panel)">
                  <span style="flex:none;width:30px;height:30px;border-radius:50%;border:1.5px dashed var(--rule);display:grid;place-items:center;font:600 11px var(--sans);color:var(--ink-dim)">?</span>
                  <span style="flex:1;min-width:0;font:400 13px var(--mono);color:var(--ink-dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ iv.e }}</span>
                  <span style="font:500 12px var(--sans);background:var(--panel-2);color:var(--ink-dim);border-radius:99px;padding:3px 10px">{{ iv.level }}</span>
                  <span style="font:400 12px var(--sans);color:var(--ink-dim)">Invited {{ iv.sent }}</span>
                  <span onClick="{{ iv.copyLink }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">{{ iv.mark }}</span>
                  <span onClick="{{ iv.del }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Delete</span>
                </div>
              </sc-for>
              <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:var(--panel)">
                <input value="{{ invEmail }}" onInput="{{ onInvEmail }}" placeholder="email@example.com" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none">
                <div style="position:relative">
                  <div onClick="{{ ddToggleInv }}" style="display:flex;align-items:center;gap:8px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);padding:0 10px;cursor:pointer;min-width:130px" style-hover="border-color:var(--brand)">
                    <span style="font:500 12.5px var(--sans);flex:1">{{ invLevel }}</span>
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                  </div>
                  <sc-if value="{{ ddInvOpen }}" hint-placeholder-val="{{ false }}">
                    <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                    <div style="position:absolute;bottom:36px;left:0;z-index:40;width:160px;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                      <div style="max-height:176px;overflow-y:auto">
                        <sc-for list="{{ ddInvOpts }}" as="o" hint-placeholder-count="3">
                          <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)">
                            <span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink)">{{ o.mark }}</span>
                            <span style="font:500 12.5px var(--sans)">{{ o.label }}</span>
                          </div>
                        </sc-for>
                      </div>
                    </div>
                  </sc-if>
                </div>
                <button onClick="{{ sendInvite }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12.5px var(--sans);padding:6px 13px;border-radius:7px;cursor:pointer" style-hover="border-color:var(--brand)">Invite</button>
              </div>
            </div>
            <div style="margin-top:14px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-bottom:1px solid var(--rule)">
                <h2 style="margin:0;font:600 14px var(--sans)">Trusted devices &amp; locations</h2>
                <span style="font:400 12px var(--sans);color:var(--ink-dim)">— new locations require email verification</span>
              </div>
              <sc-for list="{{ trusted }}" as="td" hint-placeholder-count="2">
                <div style="display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--rule)">
                  <span style="flex:1;min-width:0"><span style="display:block;font:500 13px var(--sans)">{{ td.where }}</span><span style="display:block;font:400 12px var(--sans);color:var(--ink-dim)">{{ td.ua }} · added {{ td.added }}</span></span>
                  <span style="font:400 12px var(--mono);color:var(--ink-dim)">last seen {{ td.last }}</span>
                  <span onClick="{{ td.revoke }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Revoke</span>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ accTabSites }}" hint-placeholder-val="{{ false }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ accSites }}" as="s" hint-placeholder-count="2">
                <div onClick="{{ s.open }}" style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--rule);cursor:pointer" style-hover="background:var(--panel)">
                  <span style="flex:1;font:500 13.5px var(--sans)">{{ s.name }}</span>
                  <span style="font:400 12.5px var(--mono);color:var(--ink-dim)">{{ s.envs }}</span>
                  <span style="font:400 12.5px var(--sans);color:var(--ink-dim)">{{ s.provider }}</span>
                  <span style="display:flex;align-items:center;gap:6px;font:500 12.5px var(--sans);color:var(--ink-dim)"><span style="width:7px;height:7px;border-radius:50%;background:{{ s.dot }}"></span>{{ s.health }}</span>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ accTabDomains }}" hint-placeholder-val="{{ false }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ accDomains }}" as="d" hint-placeholder-count="1">
                <div onClick="{{ d.open }}" style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--rule);cursor:pointer" style-hover="background:var(--panel)">
                  <span style="flex:1;font:500 13.5px var(--sans)">{{ d.name }}</span>
                  <span style="font:400 12.5px var(--sans);color:var(--ink-dim)">{{ d.registrar }}</span>
                  <span style="font:400 12.5px var(--mono);color:{{ d.expFg }}">{{ d.expires }}</span>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ accTabPlan }}" hint-placeholder-val="{{ false }}">
            <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:14px;margin-top:16px;align-items:start">
              <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Usage</h2></div>
                <sc-for list="{{ planUsage }}" as="u" hint-placeholder-count="3">
                  <div style="padding:11px 16px;border-bottom:1px solid var(--rule)">
                    <div style="display:flex;justify-content:space-between;align-items:baseline">
                      <span style="font:500 13px var(--sans)">{{ u.k }}</span>
                      <span style="font:400 12px var(--mono);color:var(--ink-dim)">{{ u.used }}</span>
                    </div>
                    <div style="margin-top:7px;height:6px;border-radius:99px;background:var(--panel-2);overflow:hidden">
                      <div style="height:100%;width:{{ u.pct }}%;background:{{ u.fill }};border-radius:99px"></div>
                    </div>
                  </div>
                </sc-for>
              </section>
              <section style="border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
                <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Plan</h2></div>
                <sc-for list="{{ planRows }}" as="r" hint-placeholder-count="5">
                  <div style="display:flex;justify-content:space-between;gap:12px;padding:9px 16px;border-bottom:1px solid var(--rule)">
                    <span style="font:500 12.5px var(--sans);color:var(--ink-dim)">{{ r.k }}</span>
                    <span style="font:400 12.5px var(--mono);text-align:right">{{ r.v }}</span>
                  </div>
                </sc-for>
                <div style="display:flex;gap:12px;padding:12px 16px">
                  <button onClick="{{ planRequest }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12.5px var(--sans);padding:6px 13px;border-radius:7px;cursor:pointer" style-hover="border-color:var(--brand)">Request changes</button>
                  <span style="flex:1"></span>
                  <span style="font:500 12.5px var(--sans);color:var(--bad);cursor:pointer;align-self:center">Cancel plan…</span>
                </div>
              </section>
            </div>
          </sc-if>

          <sc-if value="{{ accTabActivity }}" hint-placeholder-val="{{ false }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ accActivity }}" as="ev" hint-placeholder-count="5">
                <div style="display:flex;gap:12px;padding:10px 16px;border-bottom:1px solid var(--rule);align-items:baseline">
                  <span style="flex:none;width:56px;font:500 11.5px var(--mono);color:var(--ink-dim);text-align:right">{{ ev.t }}</span>
                  <span style="font:400 13px/1.5 var(--sans)">{{ ev.text }}</span>
                </div>
              </sc-for>
            </div>
          </sc-if>
        </div>
      </sc-if>

      <sc-if value="{{ showBilling }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:1160px;margin:0 auto;padding:28px 32px 120px;animation:ccfade .25s ease">
          <h1 style="margin:0;font:600 26px var(--sans);letter-spacing:-.02em">Billing</h1>
          <div style="display:flex;border-bottom:1px solid var(--rule);margin-top:16px">
            <sc-for list="{{ billTabs }}" as="t" hint-placeholder-count="3">
              <span onClick="{{ t.go }}" style="padding:9px 2px;margin-right:24px;cursor:pointer;font:500 14px var(--sans);color:{{ t.fg }};border-bottom:2px solid {{ t.line }}" style-hover="color:var(--ink)">{{ t.label }}</span>
            </sc-for>
          </div>

          <sc-if value="{{ billTabInv }}" hint-placeholder-val="{{ true }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ invoices }}" as="iv" hint-placeholder-count="3">
                <div style="display:flex;align-items:center;gap:12px;padding:13px 16px;border-bottom:1px solid var(--rule);flex-wrap:wrap">
                  <span style="font:500 13px var(--mono)">{{ iv.id }}</span>
                  <span style="font:400 13.5px var(--sans)">{{ iv.items }}</span>
                  <span style="font:400 12px var(--mono);color:var(--ink-dim)">{{ iv.date }}</span>
                  <div style="flex:1"></div>
                  <span style="font:600 13.5px var(--mono)">{{ iv.amount }}</span>
                  <span style="font:500 12px var(--sans);background:{{ iv.stBg }};color:{{ iv.stFg }};border-radius:99px;padding:3px 10px">{{ iv.status }}</span>
                  <span style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">PDF</span>
                  <sc-if value="{{ iv.canPay }}" hint-placeholder-val="{{ false }}">
                    <button onClick="{{ iv.pay }}" style="border:none;background:var(--brand);color:white;font:600 12px var(--sans);padding:6px 12px;border-radius:6px;cursor:pointer" style-hover="filter:brightness(1.08)">Pay now</button>
                  </sc-if>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ billTabPm }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;gap:10px;margin-top:16px">
              <button style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 13px var(--sans);padding:7px 13px;border-radius:8px;cursor:pointer" style-hover="border-color:var(--brand)">+ Add card</button>
              <button style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 13px var(--sans);padding:7px 13px;border-radius:8px;cursor:pointer" style-hover="border-color:var(--brand)">+ Add bank (ACH)</button>
            </div>
            <div style="margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ payMethods }}" as="pm" hint-placeholder-count="2">
                <div style="display:flex;align-items:center;gap:12px;padding:13px 16px;border-bottom:1px solid var(--rule)">
                  <span style="font:500 13.5px var(--mono)">{{ pm.label }}</span>
                  <span style="font:400 12.5px var(--sans);color:var(--ink-dim)">{{ pm.sub }}</span>
                  <sc-if value="{{ pm.isPrimary }}" hint-placeholder-val="{{ false }}">
                    <span style="font:500 11.5px var(--sans);background:var(--brand-soft);color:var(--brand-ink);border-radius:99px;padding:2px 9px">Primary</span>
                  </sc-if>
                  <div style="flex:1"></div>
                  <sc-if value="{{ pm.canPrimary }}" hint-placeholder-val="{{ false }}">
                    <span onClick="{{ pm.setPrimary }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Set primary</span>
                    <span style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Remove</span>
                  </sc-if>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ billTabAddr }}" hint-placeholder-val="{{ false }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden;max-width:520px">
              <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Billing address</h2><span style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Edit</span></div>
              <div style="padding:14px 16px">
                <div style="font:500 13.5px var(--sans)">Sarah Whitfield · Bloom &amp; Branch LLC</div>
                <div style="font:400 13px/1.6 var(--sans);color:var(--ink-dim);margin-top:3px">412 Larkspur Lane<br>Lancaster, PA 17601 · United States<br>sarah@bloomandbranch.com</div>
              </div>
            </div>
          </sc-if>
        </div>
      </sc-if>

      <sc-if value="{{ showSecurity }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:1240px;margin:0 auto;padding:28px 32px 120px;animation:ccfade .25s ease">
          <h1 style="margin:0;font:600 26px var(--sans);letter-spacing:-.02em">Security</h1>
          <div style="display:flex;border-bottom:1px solid var(--rule);margin-top:16px">
            <sc-for list="{{ secTabs }}" as="t" hint-placeholder-count="3">
              <span onClick="{{ t.go }}" style="padding:9px 2px;margin-right:24px;cursor:pointer;font:500 14px var(--sans);color:{{ t.fg }};border-bottom:2px solid {{ t.line }}" style-hover="color:var(--ink)">{{ t.label }}</span>
            </sc-for>
          </div>

          <sc-if value="{{ secTabVulns }}" hint-placeholder-val="{{ true }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ threats }}" as="t" hint-placeholder-count="3">
                <div style="border-bottom:1px solid var(--rule)">
                  <div onClick="{{ t.toggle }}" style="display:flex;align-items:center;gap:11px;padding:12px 16px;cursor:pointer;flex-wrap:wrap" style-hover="background:var(--panel)">
                    <span style="font:600 11px var(--sans);letter-spacing:.04em;background:{{ t.sevBg }};color:{{ t.sevFg }};border-radius:99px;padding:3px 10px;text-transform:uppercase">{{ t.sev }}</span>
                    <span style="font:500 13.5px var(--sans)">{{ t.name }}</span>
                    <span style="font:400 12px var(--mono);color:var(--ink-dim)">{{ t.cve }}</span>
                    <span style="font:400 12px var(--sans);color:var(--ink-dim)">{{ t.siteCount }} sites</span>
                    <div style="flex:1"></div>
                    <sc-if value="{{ t.patch }}" hint-placeholder-val="{{ false }}">
                      <span style="font:500 11.5px var(--sans);background:var(--brand-soft);color:var(--brand-ink);border-radius:99px;padding:2px 9px">Patch available</span>
                    </sc-if>
                    <span style="font:500 12px var(--sans);background:{{ t.stBg }};border-radius:99px;padding:3px 10px">{{ t.status }}</span>
                  </div>
                  <sc-if value="{{ t.open }}" hint-placeholder-val="{{ false }}">
                    <div style="background:var(--panel);border-top:1px solid var(--rule);padding:14px 16px">
                      <div style="font:400 13px/1.6 var(--sans)">{{ t.findings }}</div>
                      <div style="font:400 12.5px/1.6 var(--sans);color:var(--ink-dim);margin-top:6px">Recommendation: {{ t.rec }}</div>
                      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;align-items:center">
                        <span style="font:500 12px var(--sans);color:var(--ink-dim)">Affected:</span>
                        <sc-for list="{{ t.siteRows }}" as="sr" hint-placeholder-count="3">
                          <span onClick="{{ sr.go }}" style="font:500 12px var(--mono);color:var(--brand-ink);cursor:pointer;background:var(--paper);border:1px solid var(--rule);border-radius:6px;padding:2px 8px" style-hover="border-color:var(--brand)">{{ sr.name }}</span>
                        </sc-for>
                      </div>
                      <div style="margin-top:12px;border-top:1px solid var(--rule);padding-top:10px">
                        <sc-for list="{{ t.notes }}" as="n" hint-placeholder-count="1">
                          <div style="font:400 12.5px/1.6 var(--sans);margin-bottom:4px"><span style="font-weight:500">{{ n.who }}</span> <span style="color:var(--ink-dim)">· {{ n.when }}</span> — {{ n.text }}</div>
                        </sc-for>
                        <div style="display:flex;gap:8px;margin-top:6px">
                          <input value="{{ noteDraft }}" onInput="{{ onNoteDraft }}" placeholder="Add a note…" style="flex:1;height:30px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--sans);padding:0 9px;outline:none">
                          <button onClick="{{ t.addNote }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12px var(--sans);padding:5px 12px;border-radius:7px;cursor:pointer" style-hover="border-color:var(--brand)">Add note</button>
                        </div>
                      </div>
                      <div style="display:flex;gap:14px;margin-top:12px;flex-wrap:wrap">
                        <span onClick="{{ t.openTerm }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Open in terminal (targets prefilled)</span>
                        <sc-if value="{{ t.patch }}" hint-placeholder-val="{{ false }}">
                          <span onClick="{{ t.getPatch }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Download patch</span>
                        </sc-if>
                        <div style="flex:1"></div>
                        <span onClick="{{ t.markInv }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--ink)">Mark investigating</span>
                        <span onClick="{{ t.markRes }}" style="font:500 12px var(--sans);color:var(--ok);cursor:pointer">Mark resolved</span>
                      </div>
                    </div>
                  </sc-if>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ secTabCk }}" hint-placeholder-val="{{ false }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Core checksum failures</h2></div>
              <sc-for list="{{ coreFails }}" as="c" hint-placeholder-count="1">
                <div style="border-bottom:1px solid var(--rule)">
                  <div onClick="{{ c.toggle }}" style="display:flex;align-items:center;gap:11px;padding:12px 16px;cursor:pointer;flex-wrap:wrap" style-hover="background:var(--panel)">
                    <span style="font:500 13.5px var(--sans)">{{ c.site }}</span>
                    <span style="font:500 11.5px var(--mono);background:var(--warn-soft);border-radius:99px;padding:2px 9px">{{ c.mod }} modified</span>
                    <span style="font:500 11.5px var(--mono);background:var(--panel-2);border-radius:99px;padding:2px 9px">{{ c.extra }} extra</span>
                    <div style="flex:1"></div>
                    <span onClick="{{ c.copySSH }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">{{ c.sshMark }}</span>
                    <span onClick="{{ c.repair }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Repair core files</span>
                  </div>
                  <sc-if value="{{ c.open }}" hint-placeholder-val="{{ false }}">
                    <div style="background:var(--panel);border-top:1px solid var(--rule)">
                      <sc-for list="{{ c.files }}" as="f" hint-placeholder-count="3">
                        <div style="padding:7px 16px;border-bottom:1px solid var(--rule);font:400 12.5px var(--mono);color:var(--ink-dim)">{{ f.p }}</div>
                      </sc-for>
                    </div>
                  </sc-if>
                </div>
              </sc-for>
              <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Plugin checksum failures</h2></div>
              <sc-for list="{{ plugFails }}" as="c" hint-placeholder-count="1">
                <div style="border-bottom:1px solid var(--rule)">
                  <div onClick="{{ c.toggle }}" style="display:flex;align-items:center;gap:11px;padding:12px 16px;cursor:pointer;flex-wrap:wrap" style-hover="background:var(--panel)">
                    <span style="font:500 13.5px var(--sans)">{{ c.site }}</span>
                    <span style="font:500 12px var(--mono);background:var(--panel-2);border-radius:6px;padding:2px 8px">{{ c.slug }}</span>
                    <sc-for list="{{ c.chips }}" as="ch" hint-placeholder-count="2">
                      <span style="font:500 11px var(--mono);background:var(--warn-soft);border-radius:99px;padding:2px 8px">{{ ch.f }}</span>
                    </sc-for>
                    <div style="flex:1"></div>
                    <span style="font:500 12px var(--sans);color:var(--brand-ink)">Diff vs wordpress.org</span>
                  </div>
                  <sc-if value="{{ c.open }}" hint-placeholder-val="{{ false }}">
                    <div style="background:var(--panel);border-top:1px solid var(--rule);padding:8px 0">
                      <sc-for list="{{ c.diff }}" as="l" hint-placeholder-count="3">
                        <div style="padding:1px 16px;font:400 12.5px/1.7 var(--mono);color:{{ l.fg }};background:{{ l.bg }};white-space:pre-wrap">{{ l.text }}</div>
                      </sc-for>
                    </div>
                  </sc-if>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ secTabCov }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:stretch;margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ covTiles }}" as="t" hint-placeholder-count="4">
                <div style="flex:1;padding:13px 16px;border-right:1px solid var(--rule)">
                  <div style="font:500 10.5px var(--sans);color:var(--ink-dim);text-transform:uppercase;letter-spacing:.06em">{{ t.k }}</div>
                  <div style="font:600 20px var(--sans);margin-top:2px;color:{{ t.fg }}">{{ t.v }}</div>
                </div>
              </sc-for>
            </div>
            <div style="margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Coverage by component type</h2></div>
              <sc-for list="{{ covBars }}" as="u" hint-placeholder-count="4">
                <div style="padding:11px 16px;border-bottom:1px solid var(--rule)">
                  <div style="display:flex;justify-content:space-between;align-items:baseline">
                    <span style="font:500 13px var(--sans)">{{ u.k }}</span>
                    <span style="font:400 12px var(--mono);color:var(--ink-dim)">{{ u.pct }}%</span>
                  </div>
                  <div style="margin-top:7px;height:6px;border-radius:99px;background:var(--panel-2);overflow:hidden">
                    <div style="height:100%;width:{{ u.pct }}%;background:{{ u.fill }};border-radius:99px"></div>
                  </div>
                </div>
              </sc-for>
              <div style="display:flex;align-items:center;gap:12px;padding:12px 16px">
                <span style="font:400 12.5px var(--sans);color:var(--ink-dim)">9 sites have components without fresh audit hashes.</span>
                <div style="flex:1"></div>
                <button onClick="{{ queueStale }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12.5px var(--sans);padding:6px 13px;border-radius:7px;cursor:pointer" style-hover="border-color:var(--brand)">Queue audits for 9 stale sites</button>
                <button onClick="{{ steerQueue }}" style="border:1px solid var(--warn);background:var(--warn-soft);color:var(--ink);font:500 12.5px var(--sans);padding:6px 13px;border-radius:7px;cursor:pointer">Run update-before-audit steer queue</button>
              </div>
            </div>
          </sc-if>
        </div>
      </sc-if>

      <sc-if value="{{ showAudits }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:1160px;margin:0 auto;padding:28px 32px 120px;animation:ccfade .25s ease">
          <h1 style="margin:0;font:600 26px var(--sans);letter-spacing:-.02em">Site Audits</h1>
          <div style="display:flex;align-items:center;gap:10px;margin-top:16px;padding:12px 16px;border:1px solid var(--rule);border-radius:12px;background:var(--panel);flex-wrap:wrap">
            <div style="position:relative">
              <div onClick="{{ ddToggleAud }}" style="display:flex;align-items:center;gap:8px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);padding:0 10px;cursor:pointer;min-width:200px" style-hover="border-color:var(--brand)">
                <span style="font:500 12.5px var(--mono);flex:1">{{ audSite }}</span>
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
              </div>
              <sc-if value="{{ ddAudOpen }}" hint-placeholder-val="{{ false }}">
                <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                <div style="position:absolute;top:36px;left:0;z-index:40;width:250px;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                  <input autofocus value="{{ ddQ }}" onInput="{{ onDdQ }}" placeholder="Search sites…" style="width:100%;height:28px;border:1px solid var(--rule);border-radius:6px;background:var(--panel);color:var(--ink);font:400 12px var(--mono);padding:0 8px;outline:none;margin-bottom:4px">
                  <div style="max-height:176px;overflow-y:auto">
                    <sc-for list="{{ ddAudOpts }}" as="o" hint-placeholder-count="5">
                      <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)">
                        <span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink)">{{ o.mark }}</span>
                        <span style="font:500 12px var(--mono)">{{ o.label }}</span>
                      </div>
                    </sc-for>
                  </div>
                </div>
              </sc-if>
            </div>
            <sc-for list="{{ audTypeChips }}" as="c" hint-placeholder-count="6">
              <span onClick="{{ c.go }}" style="padding:5px 11px;border-radius:99px;border:1px solid {{ c.bd }};background:{{ c.bg }};color:{{ c.fg }};font:500 12px var(--sans);cursor:pointer">{{ c.label }}</span>
            </sc-for>
            <div style="flex:1"></div>
            <button onClick="{{ requestAudit }}" style="border:none;background:var(--brand);color:white;font:600 13px var(--sans);padding:8px 14px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">Request audit</button>
          </div>
          <div style="margin-top:14px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
            <sc-for list="{{ audRows }}" as="a" hint-placeholder-count="3">
              <div style="display:flex;align-items:center;gap:11px;padding:12px 16px;border-bottom:1px solid var(--rule);flex-wrap:wrap">
                <span style="font:500 13.5px var(--sans)">{{ a.site }}</span>
                <span style="font:400 12px var(--sans);color:var(--ink-dim)">{{ a.env }} · {{ a.types }}</span>
                <span style="font:400 12px var(--mono);color:var(--ink-dim)">{{ a.when }}</span>
                <div style="flex:1"></div>
                <span style="font:400 12px var(--sans);color:var(--ink-dim)">{{ a.findings }}</span>
                <span style="font:500 12px var(--sans);background:{{ a.stBg }};border-radius:99px;padding:3px 10px">{{ a.status }}</span>
                <sc-if value="{{ a.done }}" hint-placeholder-val="{{ false }}">
                  <span style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">View</span>
                  <span onClick="{{ a.togglePub }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">{{ a.pubLabel }}</span>
                </sc-if>
                <sc-if value="{{ a.pub }}" hint-placeholder-val="{{ false }}">
                  <span onClick="{{ a.copyLink }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer;border:1px solid var(--rule);border-radius:6px;padding:2px 8px" style-hover="border-color:var(--brand)">{{ a.mark }}</span>
                </sc-if>
                <sc-if value="{{ a.cancellable }}" hint-placeholder-val="{{ false }}">
                  <span onClick="{{ a.cancel }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Cancel</span>
                </sc-if>
              </div>
            </sc-for>
          </div>
        </div>
      </sc-if>

      <sc-if value="{{ showReports }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:1160px;margin:0 auto;padding:28px 32px 120px;animation:ccfade .25s ease">
          <h1 style="margin:0;font:600 26px var(--sans);letter-spacing:-.02em">Reports</h1>
          <div style="display:flex;align-items:center;gap:10px;margin-top:16px;padding:12px 16px;border:1px solid var(--rule);border-radius:12px;background:var(--panel);flex-wrap:wrap">
            <sc-for list="{{ repModeChips }}" as="c" hint-placeholder-count="2">
              <span onClick="{{ c.go }}" style="padding:5px 12px;border-radius:99px;border:1px solid {{ c.bd }};background:{{ c.bg }};color:{{ c.fg }};font:500 12.5px var(--sans);cursor:pointer">{{ c.label }}</span>
            </sc-for>
            <div style="position:relative">
              <div onClick="{{ ddToggleRepT }}" style="display:flex;align-items:center;gap:8px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);padding:0 10px;cursor:pointer;min-width:210px" style-hover="border-color:var(--brand)">
                <span style="font:500 12.5px var(--sans);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ repTarget }}</span>
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
              </div>
              <sc-if value="{{ ddRepTOpen }}" hint-placeholder-val="{{ false }}">
                <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                <div style="position:absolute;top:36px;left:0;z-index:40;width:250px;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                  <input autofocus value="{{ ddQ }}" onInput="{{ onDdQ }}" placeholder="Search…" style="width:100%;height:28px;border:1px solid var(--rule);border-radius:6px;background:var(--panel);color:var(--ink);font:400 12px var(--sans);padding:0 8px;outline:none;margin-bottom:4px">
                  <div style="max-height:176px;overflow-y:auto">
                    <sc-for list="{{ ddRepTOpts }}" as="o" hint-placeholder-count="5">
                      <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)">
                        <span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink)">{{ o.mark }}</span>
                        <span style="font:500 12.5px var(--sans)">{{ o.label }}</span>
                      </div>
                    </sc-for>
                  </div>
                </div>
              </sc-if>
            </div>
            <div style="position:relative">
              <div onClick="{{ ddToggleRepR }}" style="display:flex;align-items:center;gap:8px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);padding:0 10px;cursor:pointer;min-width:130px" style-hover="border-color:var(--brand)">
                <span style="font:500 12.5px var(--sans);flex:1">{{ repRange }}</span>
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
              </div>
              <sc-if value="{{ ddRepROpen }}" hint-placeholder-val="{{ false }}">
                <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                <div style="position:absolute;top:36px;left:0;z-index:40;width:150px;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                  <div style="max-height:176px;overflow-y:auto">
                    <sc-for list="{{ ddRepROpts }}" as="o" hint-placeholder-count="3">
                      <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)">
                        <span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink)">{{ o.mark }}</span>
                        <span style="font:500 12.5px var(--sans)">{{ o.label }}</span>
                      </div>
                    </sc-for>
                  </div>
                </div>
              </sc-if>
            </div>
            <input value="{{ repEmail }}" onInput="{{ onRepEmail }}" placeholder="recipient@example.com" style="flex:1;min-width:180px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none">
            <button onClick="{{ repPreview }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12.5px var(--sans);padding:6px 13px;border-radius:7px;cursor:pointer" style-hover="border-color:var(--brand)">Preview</button>
            <button onClick="{{ repSend }}" style="border:none;background:var(--brand);color:white;font:600 13px var(--sans);padding:8px 14px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">Send report</button>
          </div>
          <div style="margin-top:14px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
            <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--rule)">
              <h2 style="margin:0;font:600 14px var(--sans)">Scheduled reports</h2>
              <div style="flex:1"></div>
              <div style="position:relative">
                <div onClick="{{ ddToggleRepI }}" style="display:flex;align-items:center;gap:8px;height:30px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);padding:0 10px;cursor:pointer;min-width:110px" style-hover="border-color:var(--brand)">
                  <span style="font:500 12px var(--sans);flex:1">{{ repInt }}</span>
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                </div>
                <sc-if value="{{ ddRepIOpen }}" hint-placeholder-val="{{ false }}">
                  <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                  <div style="position:absolute;top:34px;right:0;z-index:40;width:130px;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                    <div style="max-height:176px;overflow-y:auto">
                      <sc-for list="{{ ddRepIOpts }}" as="o" hint-placeholder-count="3">
                        <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)">
                          <span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink)">{{ o.mark }}</span>
                          <span style="font:500 12px var(--sans)">{{ o.label }}</span>
                        </div>
                      </sc-for>
                    </div>
                  </div>
                </sc-if>
              </div>
              <button onClick="{{ addSchedule }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12px var(--sans);padding:5px 12px;border-radius:7px;cursor:pointer" style-hover="border-color:var(--brand)">+ Schedule current selection</button>
            </div>
            <sc-for list="{{ schedRows }}" as="sr" hint-placeholder-count="2">
              <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
                <span style="font:500 13px var(--sans)">{{ sr.target }}</span>
                <span style="font:500 11.5px var(--sans);background:var(--brand-soft);color:var(--brand-ink);border-radius:99px;padding:2px 9px">{{ sr.interval }}</span>
                <span style="font:400 12px var(--sans);color:var(--ink-dim)">next {{ sr.next }} · {{ sr.recipients }} recipients</span>
                <div style="flex:1"></div>
                <span style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Edit</span>
                <span onClick="{{ sr.del }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Delete</span>
              </div>
            </sc-for>
          </div>
        </div>
      </sc-if>

      <sc-if value="{{ showArchives }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:1160px;margin:0 auto;padding:28px 32px 120px;animation:ccfade .25s ease">
          <div style="display:flex;align-items:baseline;gap:12px">
            <h1 style="margin:0;font:600 26px var(--sans);letter-spacing:-.02em">Archives</h1>
            <span style="font:400 14px var(--sans);color:var(--ink-dim)">{{ archTotal }}</span>
          </div>
          <div style="display:flex;align-items:center;gap:10px;margin-top:16px;padding:12px 16px;border:1px solid var(--rule);border-radius:12px;background:var(--panel)">
            <input value="{{ archUrl }}" onInput="{{ onArchUrl }}" placeholder="https://…/migration.zip — store an archive from a URL" style="flex:1;height:34px;border:1px solid {{ archBd }};border-radius:8px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 10px;outline:none">
            <button onClick="{{ storeArch }}" style="border:none;background:var(--brand);color:white;font:600 13px var(--sans);padding:8px 14px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">Store archive</button>
          </div>
          <sc-if value="{{ archErr }}" hint-placeholder-val="{{ false }}">
            <div style="font:500 12.5px var(--sans);color:var(--bad);margin-top:8px">URL must point to a .zip file.</div>
          </sc-if>
          <div style="margin-top:14px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
            <sc-for list="{{ archRows }}" as="ar" hint-placeholder-count="3">
              <div style="display:flex;align-items:center;gap:12px;padding:13px 16px;border-bottom:1px solid var(--rule);flex-wrap:wrap">
                <span style="font:500 13px var(--mono)">{{ ar.name }}</span>
                <sc-if value="{{ ar.storing }}" hint-placeholder-val="{{ false }}">
                  <span style="display:flex;align-items:center;gap:6px;font:500 12px var(--sans);color:var(--brand-ink)"><span style="width:7px;height:7px;border-radius:50%;background:var(--brand);animation:ccpulse 1.6s ease infinite"></span>Storing — progress in Activity dock</span>
                </sc-if>
                <div style="flex:1"></div>
                <span style="font:400 12.5px var(--mono);color:var(--ink-dim)">{{ ar.size }}</span>
                <span style="font:400 12px var(--mono);color:var(--ink-dim)">{{ ar.mod }}</span>
                <span onClick="{{ ar.share }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer;border:1px solid var(--rule);border-radius:6px;padding:2px 8px" style-hover="border-color:var(--brand)">{{ ar.mark }}</span>
                <span onClick="{{ ar.del }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Delete</span>
              </div>
            </sc-for>
          </div>
          <p style="font:400 12px var(--sans);color:var(--ink-dim);margin-top:10px">Share links are valid for 7 days (Backblaze B2).</p>
        </div>
      </sc-if>

      <sc-if value="{{ showSettings }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:1160px;margin:0 auto;padding:28px 32px 120px;animation:ccfade .25s ease">
          <h1 style="margin:0;font:600 26px var(--sans);letter-spacing:-.02em">Settings</h1>
          <div style="display:flex;border-bottom:1px solid var(--rule);margin-top:16px;flex-wrap:wrap">
            <sc-for list="{{ setTabs }}" as="t" hint-placeholder-count="6">
              <span onClick="{{ t.go }}" style="padding:9px 2px;margin-right:22px;cursor:pointer;font:500 14px var(--sans);color:{{ t.fg }};border-bottom:2px solid {{ t.line }}" style-hover="color:var(--ink)">{{ t.label }}</span>
            </sc-for>
          </div>

          <sc-if value="{{ setTabBrand }}" hint-placeholder-val="{{ true }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden;max-width:640px">
              <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
                <span style="width:130px;font:500 12.5px var(--sans);color:var(--ink-dim)">Portal name</span>
                <input value="{{ brandName }}" onInput="{{ onBrandName }}" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 13px var(--sans);padding:0 9px;outline:none">
              </div>
              <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
                <span style="width:130px;font:500 12.5px var(--sans);color:var(--ink-dim)">Logo</span>
                <div style="width:120px;height:44px;border:1.5px dashed var(--rule);border-radius:8px;display:grid;place-items:center;font:400 11.5px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="border-color:var(--brand)">Drop logo</div>
              </div>
              <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
                <span style="width:130px;font:500 12.5px var(--sans);color:var(--ink-dim)">Theme colors</span>
                <sc-for list="{{ brandSwatches }}" as="sw" hint-placeholder-count="5">
                  <span title="{{ sw.k }}" style="width:26px;height:26px;border-radius:7px;background:{{ sw.c }};border:1px solid var(--rule);cursor:pointer"></span>
                </sc-for>
                <span style="font:400 12px var(--sans);color:var(--ink-dim)">via Tweaks or per-portal config</span>
              </div>
              <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
                <span style="width:130px;font:500 12.5px var(--sans);color:var(--ink-dim)">DNS copy labels</span>
                <span style="font:400 12.5px var(--mono);color:var(--ink-dim)">A → "Anchor Hosting" · CNAME → "Anchor CDN"</span>
                <span style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Edit</span>
              </div>
              <div style="display:flex;gap:12px;padding:12px 16px">
                <button onClick="{{ saveBrand }}" style="border:none;background:var(--brand);color:white;font:600 12.5px var(--sans);padding:7px 14px;border-radius:7px;cursor:pointer" style-hover="filter:brightness(1.08)">{{ brandSaveLabel }}</button>
              </div>
            </div>
          </sc-if>

          <sc-if value="{{ setTabProv }}" hint-placeholder-val="{{ false }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ provRows }}" as="p" hint-placeholder-count="5">
                <div style="display:flex;align-items:center;gap:12px;padding:13px 16px;border-bottom:1px solid var(--rule)">
                  <span style="font:500 13.5px var(--sans);width:130px">{{ p.name }}</span>
                  <span style="display:flex;align-items:center;gap:6px;font:400 12.5px var(--sans);color:var(--ink-dim)"><span style="width:7px;height:7px;border-radius:50%;background:{{ p.dot }}"></span>{{ p.sub }}</span>
                  <div style="flex:1"></div>
                  <span onClick="{{ p.verify }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">{{ p.action }}</span>
                  <sc-if value="{{ p.canImport }}" hint-placeholder-val="{{ false }}">
                    <span onClick="{{ p.doImport }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Import sites…</span>
                  </sc-if>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ setTabDef }}" hint-placeholder-val="{{ false }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden;max-width:640px">
              <sc-for list="{{ defRows }}" as="r" hint-placeholder-count="4">
                <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
                  <span style="width:170px;font:500 12.5px var(--sans);color:var(--ink-dim)">{{ r.k }}</span>
                  <span style="font:400 12.5px var(--mono)">{{ r.v }}</span>
                  <div style="flex:1"></div>
                  <span style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Edit</span>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ setTabKeys }}" hint-placeholder-val="{{ false }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Management key</h2></div>
              <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
                <span style="font:400 12.5px var(--mono);color:var(--ink-dim)">SHA256:kT8mQx…9fLw · used for all site SSH</span>
                <div style="flex:1"></div>
                <span onClick="{{ rotateKey }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Rotate…</span>
              </div>
              <div style="padding:12px 16px;border-bottom:1px solid var(--rule)"><h2 style="margin:0;font:600 14px var(--sans)">Your public keys</h2></div>
              <sc-for list="{{ keyRows }}" as="k" hint-placeholder-count="1">
                <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
                  <span style="font:500 13px var(--sans)">{{ k.name }}</span>
                  <span style="font:400 12px var(--mono);color:var(--ink-dim)">{{ k.fp }}</span>
                  <sc-if value="{{ k.primary }}" hint-placeholder-val="{{ false }}">
                    <span style="font:500 11.5px var(--sans);background:var(--brand-soft);color:var(--brand-ink);border-radius:99px;padding:2px 9px">Primary</span>
                  </sc-if>
                  <div style="flex:1"></div>
                  <span onClick="{{ k.del }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Delete</span>
                </div>
              </sc-for>
              <div style="display:flex;gap:10px;padding:10px 16px;background:var(--panel)">
                <input value="{{ keyDraft }}" onInput="{{ onKeyDraft }}" placeholder="ssh-ed25519 AAAA… name@device" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12px var(--mono);padding:0 9px;outline:none">
                <button onClick="{{ addKey }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12.5px var(--sans);padding:6px 13px;border-radius:7px;cursor:pointer" style-hover="border-color:var(--brand)">Add key</button>
              </div>
            </div>
          </sc-if>

          <sc-if value="{{ setTabCook }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:10px;margin-top:16px">
              <span style="font:400 13px var(--sans);color:var(--ink-dim)">Reusable scripts — run on one site or the whole fleet from the terminal.</span>
              <div style="flex:1"></div>
              <button style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 13px var(--sans);padding:7px 13px;border-radius:8px;cursor:pointer" style-hover="border-color:var(--brand)">+ New recipe</button>
            </div>
            <div style="margin-top:12px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ recipeRows }}" as="r" hint-placeholder-count="4">
                <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--rule)">
                  <span style="font:500 13.5px var(--sans)">{{ r.name }}</span>
                  <span style="font:500 11.5px var(--sans);background:{{ r.visBg }};color:var(--ink-dim);border-radius:99px;padding:2px 9px">{{ r.vis }}</span>
                  <span style="font:400 12px var(--mono);color:var(--ink-dim)">{{ r.runs }} runs</span>
                  <div style="flex:1"></div>
                  <span onClick="{{ r.run }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Run…</span>
                  <span style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--ink)">Edit</span>
                </div>
              </sc-for>
            </div>
          </sc-if>

          <sc-if value="{{ setTabHand }}" hint-placeholder-val="{{ false }}">
            <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
              <sc-for list="{{ handRows }}" as="h" hint-placeholder-count="4">
                <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--rule)">
                  <span style="font:500 13.5px var(--sans)">{{ h.name }}</span>
                  <span style="font:400 12px var(--sans);color:var(--ink-dim)">updated {{ h.updated }}</span>
                  <div style="flex:1"></div>
                  <span style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">View</span>
                  <span style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--ink)">Edit</span>
                </div>
              </sc-for>
            </div>
          </sc-if>
        </div>
      </sc-if>

      <sc-if value="{{ showProfile }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:760px;margin:0 auto;padding:28px 32px 120px;animation:ccfade .25s ease">
          <h1 style="margin:0;font:600 26px var(--sans);letter-spacing:-.02em">Profile</h1>
          <div style="margin-top:16px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
            <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
              <span style="width:130px;font:500 12.5px var(--sans);color:var(--ink-dim)">Display name</span>
              <input value="{{ profName }}" onInput="{{ onProfName }}" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 13px var(--sans);padding:0 9px;outline:none">
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
              <span style="width:130px;font:500 12.5px var(--sans);color:var(--ink-dim)">Email</span>
              <input value="{{ profEmail }}" onInput="{{ onProfEmail }}" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 13px var(--mono);padding:0 9px;outline:none">
            </div>
            <div style="display:flex;gap:12px;padding:12px 16px">
              <button onClick="{{ saveProfile }}" style="border:none;background:var(--brand);color:white;font:600 12.5px var(--sans);padding:7px 14px;border-radius:7px;cursor:pointer" style-hover="filter:brightness(1.08)">{{ profSaveLabel }}</button>
              <span style="font:500 12.5px var(--sans);color:var(--brand-ink);cursor:pointer;align-self:center">Change password…</span>
            </div>
          </div>
          <div style="margin-top:14px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
            <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--rule)">
              <h2 style="margin:0;font:600 14px var(--sans)">Two-factor authentication</h2>
              <span style="font:500 12px var(--sans);background:{{ tfaBg }};border-radius:99px;padding:2px 9px">{{ tfaLabel }}</span>
              <div style="flex:1"></div>
              <sc-if value="{{ tfaOff }}" hint-placeholder-val="{{ false }}">
                <button onClick="{{ tfaStart }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12.5px var(--sans);padding:6px 12px;border-radius:7px;cursor:pointer" style-hover="border-color:var(--brand)">Enable</button>
              </sc-if>
              <sc-if value="{{ tfaOn }}" hint-placeholder-val="{{ false }}">
                <span onClick="{{ tfaDisable }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Deactivate</span>
              </sc-if>
            </div>
            <sc-if value="{{ tfaSetup }}" hint-placeholder-val="{{ false }}">
              <div style="display:flex;align-items:center;gap:16px;padding:14px 16px">
                <div style="width:96px;height:96px;border:1.5px dashed var(--rule);border-radius:10px;display:grid;place-items:center;font:400 11px var(--sans);color:var(--ink-dim);text-align:center">QR code<br>scan in app</div>
                <div style="display:flex;flex-direction:column;gap:8px">
                  <span style="font:400 12.5px var(--sans);color:var(--ink-dim)">Scan with your authenticator, then enter the 6-digit code.</span>
                  <div style="display:flex;gap:8px">
                    <input value="{{ tfaCode }}" onInput="{{ onTfaCode }}" placeholder="000000" style="width:110px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:500 14px var(--mono);padding:0 9px;outline:none;letter-spacing:.15em">
                    <button onClick="{{ tfaActivate }}" style="border:none;background:var(--brand);color:white;font:600 12.5px var(--sans);padding:6px 13px;border-radius:7px;cursor:pointer" style-hover="filter:brightness(1.08)">Activate</button>
                  </div>
                </div>
              </div>
            </sc-if>
          </div>
          <div style="margin-top:14px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
            <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--rule)">
              <h2 style="margin:0;font:600 14px var(--sans)">Application password</h2>
              <span style="font:400 12px var(--sans);color:var(--ink-dim)">for the CaptainCore CLI</span>
              <div style="flex:1"></div>
              <button onClick="{{ genAppPw }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12.5px var(--sans);padding:6px 12px;border-radius:7px;cursor:pointer" style-hover="border-color:var(--brand)">{{ appPwBtn }}</button>
            </div>
            <sc-if value="{{ appPwShown }}" hint-placeholder-val="{{ false }}">
              <div style="display:flex;align-items:center;gap:12px;padding:12px 16px">
                <span style="font:500 13px var(--mono);background:var(--panel-2);border-radius:7px;padding:6px 12px">{{ appPw }}</span>
                <span onClick="{{ copyAppPw }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer;border:1px solid var(--rule);border-radius:6px;padding:3px 9px" style-hover="border-color:var(--brand)">{{ appPwMark }}</span>
                <span style="font:400 12px var(--sans);color:var(--warn)">Shown once — copy it now.</span>
              </div>
            </sc-if>
          </div>
          <div style="margin-top:14px;border:1px solid var(--rule);border-radius:12px;background:var(--paper);overflow:hidden">
            <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--rule)">
              <h2 style="margin:0;font:600 14px var(--sans)">Active sessions</h2>
              <div style="flex:1"></div>
              <span onClick="{{ killOthers }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Sign out all others</span>
            </div>
            <sc-for list="{{ sessRows }}" as="se" hint-placeholder-count="2">
              <div style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--rule)">
                <span style="flex:1;min-width:0"><span style="display:block;font:500 13px var(--sans)">{{ se.where }}</span><span style="display:block;font:400 12px var(--sans);color:var(--ink-dim)">{{ se.ua }} · {{ se.last }}</span></span>
                <sc-if value="{{ se.current }}" hint-placeholder-val="{{ false }}">
                  <span style="font:500 11.5px var(--sans);background:var(--ok-soft);border-radius:99px;padding:2px 9px">This device</span>
                </sc-if>
                <sc-if value="{{ se.killable }}" hint-placeholder-val="{{ false }}">
                  <span onClick="{{ se.kill }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--bad)">Sign out</span>
                </sc-if>
              </div>
            </sc-for>
          </div>
        </div>
      </sc-if>

      <sc-if value="{{ showStub }}" hint-placeholder-val="{{ false }}">
        <div style="max-width:1160px;margin:0 auto;padding:32px;animation:ccfade .25s ease">
          <h1 style="margin:0;font:600 26px var(--sans);letter-spacing:-.02em">{{ stubTitle }}</h1>
          <div style="margin-top:18px;border:1px dashed var(--rule);border-radius:12px;padding:48px;display:grid;place-items:center;gap:8px;text-align:center">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="{{ stubIcon }}"></path></svg>
            <div style="font:500 14px var(--sans);color:var(--ink)">{{ stubTitle }} lands in a later slice</div>
            <div style="font:400 13.5px/1.5 var(--sans);color:var(--ink-dim);max-width:400px">{{ stubDesc }}</div>
          </div>
        </div>
      </sc-if>
    </main>
  </div>

  <!-- ══ Activity dock ══ -->
  <sc-if value="{{ dockOpen }}" hint-placeholder-val="{{ false }}">
    <div style="position:fixed;bottom:16px;{{ dockSide }}:16px;width:620px;max-width:calc(100vw - 32px);height:380px;background:var(--paper);border:1px solid var(--rule);border-radius:14px;box-shadow:var(--shadow);z-index:60;display:flex;flex-direction:column;overflow:hidden;animation:ccfade .18s ease">
      <div style="flex:none;display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--rule)">
        <span style="width:8px;height:8px;border-radius:50%;background:var(--brand);animation:ccpulse 1.6s ease infinite"></span>
        <span style="font:600 14px var(--sans)">Activity</span>
        <span style="font:400 13px var(--sans);color:var(--ink-dim)">{{ runningCount }} running</span>
        <div style="flex:1"></div>
        <span style="font:500 11.5px var(--mono);color:var(--ink-dim);border:1px solid var(--rule);border-radius:5px;padding:2px 6px">⌃`</span>
        <span onClick="{{ closeDock }}" style="cursor:pointer;color:var(--ink-dim);width:24px;height:24px;display:grid;place-items:center;border-radius:6px" style-hover="background:var(--panel-2);color:var(--ink)">✕</span>
      </div>
      <div style="flex:1;display:flex;min-height:0">
        <div style="flex:none;width:210px;border-right:1px solid var(--rule);overflow-y:auto;background:var(--panel)">
          <sc-for list="{{ jobs }}" as="j" hint-placeholder-count="3">
            <div style="padding:10px 12px;border-bottom:1px solid var(--rule)">
              <div style="display:flex;align-items:center;gap:7px">
                <span style="width:6px;height:6px;border-radius:50%;background:{{ j.dot }};flex:none"></span>
                <span style="font:600 12.5px var(--mono);color:{{ j.fg }}">{{ j.label }}</span>
              </div>
              <div style="font:400 12px var(--sans);color:var(--ink-dim);margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ j.target }}</div>
            </div>
          </sc-for>
        </div>
        <div style="flex:1;min-width:0;display:flex;flex-direction:column;background:{{ consoleBg }}">
          <div ref="{{ consoleRef }}" style="flex:1;overflow-y:auto;padding:12px 14px;font:400 13px/1.75 var(--mono);display:flex;flex-direction:column">
            <div style="flex:1 0 0"></div>
            <sc-for list="{{ consoleLines }}" as="ln" hint-placeholder-count="6">
              <div style="color:{{ ln.fg }};white-space:pre-wrap;word-break:break-word">{{ ln.text }}</div>
            </sc-for>
            <span style="display:inline-block;width:7px;height:14px;background:var(--brand);animation:ccblink 1.1s step-end infinite;vertical-align:text-bottom"></span>
          </div>
          <div style="flex:none;display:flex;gap:8px;padding:9px 12px;border-top:1px solid var(--rule)">
            <span style="font:500 13px var(--mono);color:var(--brand-ink)">@3 sites</span>
            <span style="font:400 13px var(--mono);color:var(--ink-dim)">wp plugin update --all …</span>
            <span style="margin-left:auto;font:500 11.5px var(--mono);color:var(--ink-dim)">⌃⏎ run</span>
          </div>
        </div>
      </div>
    </div>
  </sc-if>
  <sc-if value="{{ dockClosed }}" hint-placeholder-val="{{ true }}">
    <div onClick="{{ openDock }}" style="position:fixed;bottom:16px;{{ dockSide }}:16px;display:flex;align-items:center;gap:9px;height:38px;padding:0 14px 0 12px;background:var(--paper);border:1px solid var(--rule);border-radius:99px;box-shadow:var(--shadow);cursor:pointer;z-index:60;max-width:min(480px,calc(100vw - 32px))" style-hover="border-color:var(--brand)">
      <span style="width:8px;height:8px;border-radius:50%;background:var(--brand);animation:ccpulse 1.6s ease infinite;flex:none"></span>
      <span style="font:600 12px var(--sans);flex:none">{{ runningCount }} running</span>
      <span style="font:400 12.5px var(--mono);color:var(--ink-dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ liveTail }}</span>
    </div>
  </sc-if>

  <!-- ══ Quicksave dialog ══ -->
  <sc-if value="{{ qsDialogOpen }}" hint-placeholder-val="{{ false }}">
    <div onClick="{{ closeQsDlg }}" style="position:fixed;inset:0;background:rgb(10 14 22/.45);z-index:70;display:flex;justify-content:center;align-items:flex-start;padding:6vh 20px">
      <div onClick="{{ stopProp }}" style="width:840px;max-width:100%;max-height:84vh;background:var(--paper);border:1px solid var(--rule);border-radius:14px;box-shadow:var(--shadow);display:flex;flex-direction:column;overflow:hidden;animation:ccfade .15s ease">
        <div style="flex:none;display:flex;align-items:center;gap:11px;padding:13px 18px;border-bottom:1px solid var(--rule);flex-wrap:wrap">
          <span style="font:500 12px var(--mono);background:var(--panel-2);border-radius:6px;padding:2px 8px">{{ dlgHash }}</span>
          <span style="font:600 14.5px var(--sans)">{{ dlgDesc }}</span>
          <span style="font:400 12px var(--mono);color:var(--ink-dim)">{{ dlgWhen }}</span>
          <div style="flex:1"></div>
          <span onClick="{{ closeQsDlg }}" style="cursor:pointer;color:var(--ink-dim);width:26px;height:26px;display:grid;place-items:center;border-radius:6px" style-hover="background:var(--panel-2);color:var(--ink)">✕</span>
        </div>
        <div style="flex:none;display:flex;align-items:center;gap:14px;padding:9px 18px;border-bottom:1px solid var(--rule);background:var(--panel);flex-wrap:wrap">
          <span style="font:500 12.5px var(--mono);color:var(--ink-dim)">{{ dlgSummary }}</span>
          <div style="flex:1"></div>
          <span onClick="{{ dlgSandbox }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">Preview in sandbox ↗</span>
          <span onClick="{{ dlgRollback }}" style="font:500 12px var(--sans);color:var(--bad);cursor:pointer">Roll back site to {{ dlgHash }}</span>
        </div>
        <sc-if value="{{ dlgNotDiff }}" hint-placeholder-val="{{ true }}">
          <div style="flex:none;display:flex;padding:0 18px;border-bottom:1px solid var(--rule)">
            <span onClick="{{ setDlgComp }}" style="padding:10px 2px;margin-right:22px;cursor:pointer;font:500 13.5px var(--sans);color:{{ dlgCompFg }};border-bottom:2px solid {{ dlgCompLine }}" style-hover="color:var(--ink)">Components</span>
            <span onClick="{{ setDlgFiles }}" style="padding:10px 2px;cursor:pointer;font:500 13.5px var(--sans);color:{{ dlgFilesFg }};border-bottom:2px solid {{ dlgFilesLine }}" style-hover="color:var(--ink)">Changed files</span>
          </div>
        </sc-if>
        <sc-if value="{{ dlgIsDiff }}" hint-placeholder-val="{{ false }}">
          <div style="flex:none;display:flex;align-items:center;gap:12px;padding:9px 18px;border-bottom:1px solid var(--rule);flex-wrap:wrap">
            <span onClick="{{ backToFiles }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer;flex:none" style-hover="color:var(--brand-ink)">← Changed files</span>
            <span style="font:500 12px var(--mono);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0">{{ dlgFilePath }}</span>
            <div style="flex:1"></div>
            <div style="display:flex;border:1px solid var(--rule);border-radius:7px;overflow:hidden;flex:none">
              <span onClick="{{ setUni }}" style="padding:3px 10px;cursor:pointer;font:500 11.5px var(--sans);background:{{ uniBg }};color:{{ uniFg }}">Unified</span>
              <span onClick="{{ setSplit }}" style="padding:3px 10px;cursor:pointer;font:500 11.5px var(--sans);background:{{ splBg }};color:{{ splFg }};border-left:1px solid var(--rule)">Side-by-side</span>
            </div>
            <button onClick="{{ dlgRestoreFile }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12px var(--sans);padding:5px 12px;border-radius:6px;cursor:pointer;flex:none" style-hover="border-color:var(--brand)">Restore this file</button>
          </div>
        </sc-if>
        <div style="flex:1;overflow-y:auto;min-height:0">
          <sc-if value="{{ dlgIsComp }}" hint-placeholder-val="{{ true }}">
            <div style="padding:8px 18px;border-bottom:1px solid var(--rule);background:var(--panel);font:600 11px var(--sans);letter-spacing:.07em;text-transform:uppercase;color:var(--ink-dim)">Themes</div>
            <sc-for list="{{ dlgThemes }}" as="c" hint-placeholder-count="2">
              <div style="display:flex;align-items:center;gap:11px;padding:9px 18px;border-bottom:1px solid var(--rule);background:{{ c.rowBg }}">
                <span style="flex:1;font:500 13px var(--sans);color:{{ c.nameFg }};text-decoration:{{ c.deco }}">{{ c.name }}</span>
                <sc-if value="{{ c.hasBadge }}" hint-placeholder-val="{{ false }}">
                  <span style="font:600 10.5px var(--sans);letter-spacing:.04em;background:{{ c.badgeBg }};border-radius:99px;padding:2px 9px;text-transform:uppercase">{{ c.badge }}</span>
                </sc-if>
                <sc-if value="{{ c.canView }}" hint-placeholder-val="{{ false }}">
                  <span onClick="{{ c.viewChanges }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">View changes</span>
                </sc-if>
                <span style="width:130px;font:400 12.5px var(--mono);color:{{ c.verFg }};text-decoration:{{ c.deco }}">{{ c.verCell }}</span>
                <span style="width:64px;font:400 12px var(--sans);color:var(--ink-dim);text-decoration:{{ c.deco }}">{{ c.status }}</span>
                <button onClick="{{ c.rollback }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--ink-dim);font:500 11.5px var(--sans);padding:4px 10px;border-radius:6px;cursor:pointer" style-hover="border-color:var(--brand);color:var(--brand-ink)">Rollback</button>
              </div>
            </sc-for>
            <div style="padding:8px 18px;border-bottom:1px solid var(--rule);background:var(--panel);font:600 11px var(--sans);letter-spacing:.07em;text-transform:uppercase;color:var(--ink-dim)">Plugins</div>
            <sc-for list="{{ dlgPlugins }}" as="c" hint-placeholder-count="6">
              <div style="display:flex;align-items:center;gap:11px;padding:9px 18px;border-bottom:1px solid var(--rule);background:{{ c.rowBg }}">
                <span style="flex:1;font:500 13px var(--sans);color:{{ c.nameFg }};text-decoration:{{ c.deco }}">{{ c.name }}</span>
                <sc-if value="{{ c.hasBadge }}" hint-placeholder-val="{{ false }}">
                  <span style="font:600 10.5px var(--sans);letter-spacing:.04em;background:{{ c.badgeBg }};border-radius:99px;padding:2px 9px;text-transform:uppercase">{{ c.badge }}</span>
                </sc-if>
                <sc-if value="{{ c.canView }}" hint-placeholder-val="{{ false }}">
                  <span onClick="{{ c.viewChanges }}" style="font:500 12px var(--sans);color:var(--brand-ink);cursor:pointer">View changes</span>
                </sc-if>
                <span style="width:130px;font:400 12.5px var(--mono);color:{{ c.verFg }};text-decoration:{{ c.deco }}">{{ c.verCell }}</span>
                <span style="width:64px;font:400 12px var(--sans);color:var(--ink-dim);text-decoration:{{ c.deco }}">{{ c.status }}</span>
                <button onClick="{{ c.rollback }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--ink-dim);font:500 11.5px var(--sans);padding:4px 10px;border-radius:6px;cursor:pointer" style-hover="border-color:var(--brand);color:var(--brand-ink)">Rollback</button>
              </div>
            </sc-for>
          </sc-if>
          <sc-if value="{{ dlgIsFiles }}" hint-placeholder-val="{{ false }}">
            <sc-for list="{{ dlgFiles }}" as="f" hint-placeholder-count="7">
              <div onClick="{{ f.pick }}" style="display:flex;align-items:center;gap:11px;padding:8px 18px;border-bottom:1px solid var(--rule);cursor:pointer" style-hover="background:var(--panel)">
                <span style="flex:none;width:22px;text-align:center;font:600 11px var(--mono);background:{{ f.stBg }};color:{{ f.stFg }};border-radius:5px;padding:2px 0">{{ f.st }}</span>
                <span style="flex:1;min-width:0;font:400 12.5px var(--mono);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ f.path }}</span>
                <span style="font:500 11.5px var(--mono);color:var(--ok)">{{ f.addN }}</span>
                <span style="font:500 11.5px var(--mono);color:var(--bad)">{{ f.delN }}</span>
              </div>
            </sc-for>
            <div style="padding:10px 18px;font:400 12px var(--sans);color:var(--ink-dim)">{{ dlgMoreFiles }}</div>
          </sc-if>
          <sc-if value="{{ dlgIsDiff }}" hint-placeholder-val="{{ false }}">
            <div style="padding:8px 0">
              <sc-if value="{{ qsUnified }}" hint-placeholder-val="{{ true }}">
                <sc-for list="{{ dlgDiff }}" as="l" hint-placeholder-count="8">
                  <div style="padding:1px 18px;font:400 12.5px/1.7 var(--mono);color:{{ l.fg }};background:{{ l.bg }};white-space:pre-wrap;word-break:break-word">{{ l.text }}</div>
                </sc-for>
              </sc-if>
              <sc-if value="{{ qsSplit }}" hint-placeholder-val="{{ false }}">
                <sc-for list="{{ dlgSplit }}" as="l" hint-placeholder-count="8">
                  <div style="display:grid;grid-template-columns:1fr 1fr">
                    <div style="padding:1px 14px;font:400 12.5px/1.7 var(--mono);color:{{ l.lfg }};background:{{ l.lbg }};white-space:pre-wrap;word-break:break-word;border-right:1px solid var(--rule)">{{ l.l }}</div>
                    <div style="padding:1px 14px;font:400 12.5px/1.7 var(--mono);color:{{ l.rfg }};background:{{ l.rbg }};white-space:pre-wrap;word-break:break-word">{{ l.r }}</div>
                  </div>
                </sc-for>
              </sc-if>
            </div>
          </sc-if>
        </div>
      </div>
    </div>
  </sc-if>

  <!-- ══ New site dialog ══ -->
  <sc-if value="{{ nsOpen }}" hint-placeholder-val="{{ false }}">
    <div onClick="{{ closeNs }}" style="position:fixed;inset:0;background:rgb(10 14 22/.45);z-index:70;display:flex;justify-content:center;align-items:flex-start;padding:8vh 20px">
      <div onClick="{{ stopProp }}" style="width:620px;max-width:100%;max-height:84vh;background:var(--paper);border:1px solid var(--rule);border-radius:14px;box-shadow:var(--shadow);display:flex;flex-direction:column;overflow:hidden;animation:ccfade .15s ease">
        <div style="flex:none;display:flex;align-items:center;gap:10px;padding:13px 18px;border-bottom:1px solid var(--rule)">
          <span style="font:600 15px var(--sans)">New site</span>
          <div style="flex:1"></div>
          <span onClick="{{ closeNs }}" style="cursor:pointer;color:var(--ink-dim);width:26px;height:26px;display:grid;place-items:center;border-radius:6px" style-hover="background:var(--panel-2);color:var(--ink)">✕</span>
        </div>
        <div style="flex:none;display:flex;gap:8px;padding:12px 18px;border-bottom:1px solid var(--rule);flex-wrap:wrap">
          <sc-for list="{{ nsPaths }}" as="c" hint-placeholder-count="4">
            <span onClick="{{ c.go }}" style="padding:6px 12px;border-radius:99px;border:1px solid {{ c.bd }};background:{{ c.bg }};color:{{ c.fg }};font:500 12.5px var(--sans);cursor:pointer">{{ c.label }}</span>
          </sc-for>
        </div>
        <div style="flex:1;overflow-y:auto;padding:14px 18px;display:flex;flex-direction:column;gap:11px">
          <sc-if value="{{ nsIsRequest }}" hint-placeholder-val="{{ true }}">
            <span style="font:400 12.5px/1.5 var(--sans);color:var(--ink-dim)">Request a site — we provision it, connect billing, and let you know when it's ready.</span>
            <div style="display:flex;align-items:center;gap:12px"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">Site name</span><input value="{{ nsName }}" onInput="{{ onNsName }}" placeholder="mynewsite.com" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none"></div>
            <div style="display:flex;align-items:center;gap:12px"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">Account</span>
              <div style="position:relative;flex:1">
                <div onClick="{{ ddToggleNsAcc }}" style="display:flex;align-items:center;gap:8px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);padding:0 10px;cursor:pointer" style-hover="border-color:var(--brand)"><span style="font:500 12.5px var(--sans);flex:1">{{ nsAcc }}</span><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg></div>
                <sc-if value="{{ ddNsAccOpen }}" hint-placeholder-val="{{ false }}">
                  <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                  <div style="position:absolute;top:36px;left:0;z-index:40;width:100%;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                    <input autofocus value="{{ ddQ }}" onInput="{{ onDdQ }}" placeholder="Search accounts…" style="width:100%;height:28px;border:1px solid var(--rule);border-radius:6px;background:var(--panel);color:var(--ink);font:400 12px var(--sans);padding:0 8px;outline:none;margin-bottom:4px">
                    <div style="max-height:150px;overflow-y:auto">
                      <sc-for list="{{ ddNsAccOpts }}" as="o" hint-placeholder-count="4">
                        <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)"><span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink)">{{ o.mark }}</span><span style="font:500 12.5px var(--sans)">{{ o.label }}</span></div>
                      </sc-for>
                    </div>
                  </div>
                </sc-if>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">Notes</span><input value="{{ nsNotes }}" onInput="{{ onNsNotes }}" placeholder="Anything we should know?" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--sans);padding:0 9px;outline:none"></div>
          </sc-if>
          <sc-if value="{{ nsIsKinsta }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:12px"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">Site name</span><input value="{{ nsName }}" onInput="{{ onNsName }}" placeholder="mynewsite.com" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none"></div>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">Datacenter</span>
              <sc-for list="{{ nsDcChips }}" as="c" hint-placeholder-count="3"><span onClick="{{ c.go }}" style="padding:5px 11px;border-radius:99px;border:1px solid {{ c.bd }};background:{{ c.bg }};color:{{ c.fg }};font:500 12px var(--sans);cursor:pointer">{{ c.label }}</span></sc-for>
            </div>
            <div style="display:flex;align-items:center;gap:12px"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">Clone from</span>
              <div style="position:relative;flex:1">
                <div onClick="{{ ddToggleNsClone }}" style="display:flex;align-items:center;gap:8px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);padding:0 10px;cursor:pointer" style-hover="border-color:var(--brand)"><span style="font:500 12.5px var(--sans);flex:1">{{ nsClone }}</span><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg></div>
                <sc-if value="{{ ddNsCloneOpen }}" hint-placeholder-val="{{ false }}">
                  <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                  <div style="position:absolute;top:36px;left:0;z-index:40;width:100%;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                    <input autofocus value="{{ ddQ }}" onInput="{{ onDdQ }}" placeholder="Search sites…" style="width:100%;height:28px;border:1px solid var(--rule);border-radius:6px;background:var(--panel);color:var(--ink);font:400 12px var(--mono);padding:0 8px;outline:none;margin-bottom:4px">
                    <div style="max-height:150px;overflow-y:auto">
                      <sc-for list="{{ ddNsCloneOpts }}" as="o" hint-placeholder-count="4">
                        <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)"><span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink)">{{ o.mark }}</span><span style="font:500 12px var(--mono)">{{ o.label }}</span></div>
                      </sc-for>
                    </div>
                  </div>
                </sc-if>
              </div>
            </div>
            <span style="font:400 12px var(--sans);color:var(--ink-dim)">Kinsta verifies the deployment and connects the site token automatically.</span>
          </sc-if>
          <sc-if value="{{ nsIsImport }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">Provider</span>
              <sc-for list="{{ nsProvChips }}" as="c" hint-placeholder-count="4"><span onClick="{{ c.go }}" style="padding:5px 11px;border-radius:99px;border:1px solid {{ c.bd }};background:{{ c.bg }};color:{{ c.fg }};font:500 12px var(--sans);cursor:pointer">{{ c.label }}</span></sc-for>
            </div>
            <div style="border:1px solid var(--rule);border-radius:10px;overflow:hidden">
              <sc-for list="{{ nsRemote }}" as="rm" hint-placeholder-count="3">
                <div onClick="{{ rm.toggle }}" style="display:flex;align-items:center;gap:10px;padding:9px 13px;border-bottom:1px solid var(--rule);cursor:pointer" style-hover="background:var(--panel)">
                  <span style="width:15px;height:15px;border:1.5px solid var(--rule);border-radius:4px;display:grid;place-items:center;background:{{ rm.checkBg }};color:white;font:600 9px var(--sans);flex:none">{{ rm.check }}</span>
                  <span style="flex:1;font:400 12.5px var(--mono)">{{ rm.name }}</span>
                  <span style="font:400 11.5px var(--mono);color:var(--ink-dim)">{{ rm.size }}</span>
                </div>
              </sc-for>
            </div>
            <span style="font:500 12.5px var(--sans);color:var(--brand-ink)">{{ nsBilling }}</span>
          </sc-if>
          <sc-if value="{{ nsIsManual }}" hint-placeholder-val="{{ false }}">
            <div style="display:flex;align-items:center;gap:12px"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">Site name</span><input value="{{ nsName }}" onInput="{{ onNsName }}" placeholder="mysite.com" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none"></div>
            <div style="display:flex;align-items:center;gap:12px"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">Server address</span><input value="{{ nsAddr }}" onInput="{{ onNsAddr }}" placeholder="sftp.host.com or IP" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none"></div>
            <div style="display:flex;align-items:center;gap:12px"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">SFTP user</span><input value="{{ nsUser }}" onInput="{{ onNsUser }}" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none"></div>
            <div style="display:flex;align-items:center;gap:12px"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">SFTP password</span><input value="{{ nsPass }}" onInput="{{ onNsPass }}" type="password" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none"></div>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">Environments</span>
              <sc-for list="{{ nsEnvChips }}" as="c" hint-placeholder-count="2"><span onClick="{{ c.go }}" style="padding:5px 11px;border-radius:99px;border:1px solid {{ c.bd }};background:{{ c.bg }};color:{{ c.fg }};font:500 12px var(--sans);cursor:pointer">{{ c.label }}</span></sc-for>
            </div>
            <span style="font:400 12px var(--sans);color:var(--ink-dim)">Offload settings, SSH-key override, and environment variables can be added after connecting.</span>
          </sc-if>
        </div>
        <div style="flex:none;display:flex;align-items:center;gap:12px;padding:12px 18px;border-top:1px solid var(--rule)">
          <div style="flex:1"></div>
          <span onClick="{{ closeNs }}" style="font:500 13px var(--sans);color:var(--ink-dim);cursor:pointer">Cancel</span>
          <button onClick="{{ nsCreate }}" style="border:none;background:var(--brand);color:white;font:600 13px var(--sans);padding:8px 16px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">{{ nsCta }}</button>
        </div>
      </div>
    </div>
  </sc-if>

  <!-- ══ New domain dialog ══ -->
  <sc-if value="{{ ndOpen }}" hint-placeholder-val="{{ false }}">
    <div onClick="{{ closeNd }}" style="position:fixed;inset:0;background:rgb(10 14 22/.45);z-index:70;display:flex;justify-content:center;align-items:flex-start;padding-top:14vh">
      <div onClick="{{ stopProp }}" style="width:480px;max-width:calc(100vw - 40px);background:var(--paper);border:1px solid var(--rule);border-radius:14px;box-shadow:var(--shadow);overflow:visible;animation:ccfade .15s ease">
        <div style="display:flex;align-items:center;gap:10px;padding:13px 18px;border-bottom:1px solid var(--rule)">
          <span style="font:600 15px var(--sans)">New domain</span>
          <div style="flex:1"></div>
          <span onClick="{{ closeNd }}" style="cursor:pointer;color:var(--ink-dim);width:26px;height:26px;display:grid;place-items:center;border-radius:6px" style-hover="background:var(--panel-2);color:var(--ink)">✕</span>
        </div>
        <div style="padding:14px 18px;display:flex;flex-direction:column;gap:11px">
          <div style="display:flex;align-items:center;gap:12px"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">Domain</span><input value="{{ ndName }}" onInput="{{ onNdName }}" placeholder="example.com" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--mono);padding:0 9px;outline:none"></div>
          <div style="display:flex;align-items:center;gap:12px"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">Account</span>
            <div style="position:relative;flex:1">
              <div onClick="{{ ddToggleNdAcc }}" style="display:flex;align-items:center;gap:8px;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);padding:0 10px;cursor:pointer" style-hover="border-color:var(--brand)"><span style="font:500 12.5px var(--sans);flex:1">{{ ndAcc }}</span><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg></div>
              <sc-if value="{{ ddNdAccOpen }}" hint-placeholder-val="{{ false }}">
                <div onClick="{{ ddClose }}" style="position:fixed;inset:0;z-index:39"></div>
                <div style="position:absolute;top:36px;left:0;z-index:40;width:100%;background:var(--paper);border:1px solid var(--rule);border-radius:10px;box-shadow:var(--shadow);padding:4px;animation:ccfade .12s ease">
                  <input autofocus value="{{ ddQ }}" onInput="{{ onDdQ }}" placeholder="Search accounts…" style="width:100%;height:28px;border:1px solid var(--rule);border-radius:6px;background:var(--panel);color:var(--ink);font:400 12px var(--sans);padding:0 8px;outline:none;margin-bottom:4px">
                  <div style="max-height:150px;overflow-y:auto">
                    <sc-for list="{{ ddNdAccOpts }}" as="o" hint-placeholder-count="4">
                      <div onClick="{{ o.pick }}" style="display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:6px;cursor:pointer;background:{{ o.bg }}" style-hover="background:var(--panel-2)"><span style="width:12px;font:600 11px var(--sans);color:var(--brand-ink)">{{ o.mark }}</span><span style="font:500 12.5px var(--sans)">{{ o.label }}</span></div>
                    </sc-for>
                  </div>
                </div>
              </sc-if>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:12px"><span style="width:110px;font:500 12.5px var(--sans);color:var(--ink-dim)">DNS zone</span>
            <span onClick="{{ ndZoneFlip }}" style="width:34px;height:20px;border-radius:99px;background:{{ ndZoneBg }};display:flex;align-items:center;padding:2px;justify-content:{{ ndZoneJust }};cursor:pointer"><span style="width:16px;height:16px;border-radius:50%;background:white"></span></span>
            <span style="font:400 12.5px var(--sans);color:var(--ink-dim)">{{ ndZoneLabel }}</span>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;padding:12px 18px;border-top:1px solid var(--rule)">
          <div style="flex:1"></div>
          <span onClick="{{ closeNd }}" style="font:500 13px var(--sans);color:var(--ink-dim);cursor:pointer">Cancel</span>
          <button onClick="{{ ndCreate }}" style="border:none;background:var(--brand);color:white;font:600 13px var(--sans);padding:8px 16px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">Add domain</button>
        </div>
      </div>
    </div>
  </sc-if>

  <!-- ══ Zone import dialog ══ -->
  <sc-if value="{{ zoneOpen }}" hint-placeholder-val="{{ false }}">
    <div onClick="{{ closeZone }}" style="position:fixed;inset:0;background:rgb(10 14 22/.45);z-index:70;display:flex;justify-content:center;align-items:flex-start;padding:7vh 20px">
      <div onClick="{{ stopProp }}" style="width:720px;max-width:100%;max-height:84vh;background:var(--paper);border:1px solid var(--rule);border-radius:14px;box-shadow:var(--shadow);display:flex;flex-direction:column;overflow:hidden;animation:ccfade .15s ease">
        <div style="flex:none;display:flex;align-items:center;gap:10px;padding:13px 18px;border-bottom:1px solid var(--rule)">
          <span style="font:600 15px var(--sans)">Import zone</span>
          <span style="font:400 12.5px var(--sans);color:var(--ink-dim)">paste a BIND zone file — records are staged, nothing saves until you Save zone</span>
          <div style="flex:1"></div>
          <span onClick="{{ closeZone }}" style="cursor:pointer;color:var(--ink-dim);width:26px;height:26px;display:grid;place-items:center;border-radius:6px" style-hover="background:var(--panel-2);color:var(--ink)">✕</span>
        </div>
        <div style="flex:1;overflow-y:auto;padding:14px 18px;display:flex;flex-direction:column;gap:12px">
          <textarea value="{{ zoneText }}" onInput="{{ onZoneText }}" placeholder="@    3600  IN  A     35.223.94.108&#10;www  3600  IN  CNAME @&#10;@    3600  IN  MX    10 mxa.mailgun.org&#10;@    3600  IN  TXT   &quot;v=spf1 include:mailgun.org ~all&quot;" style="width:100%;min-height:150px;border:1px solid var(--rule);border-radius:9px;background:var(--panel);color:var(--ink);font:400 12.5px/1.7 var(--mono);padding:10px 12px;outline:none;resize:vertical"></textarea>
          <sc-if value="{{ hasZoneRecs }}" hint-placeholder-val="{{ false }}">
            <div style="font:600 12.5px var(--sans)">{{ zoneCount }}</div>
            <div style="border:1px solid var(--rule);border-radius:10px;overflow:hidden">
              <sc-for list="{{ zonePreview }}" as="r" hint-placeholder-count="4">
                <div style="display:flex;align-items:center;gap:12px;padding:7px 13px;border-bottom:1px solid var(--rule)">
                  <span style="flex:none;width:58px;text-align:center;font:600 11px var(--mono);background:{{ r.bg }};border-radius:6px;padding:3px 0">{{ r.type }}</span>
                  <span style="flex:none;width:140px;font:400 12px var(--mono);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.name }}</span>
                  <span style="flex:1;min-width:0;font:400 12px var(--mono);color:var(--ink-dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.value }}</span>
                  <span style="flex:none;font:400 11px var(--mono);color:var(--ink-dim)">{{ r.ttl }}</span>
                </div>
              </sc-for>
            </div>
          </sc-if>
          <sc-if value="{{ zoneEmpty }}" hint-placeholder-val="{{ true }}">
            <span style="font:400 12.5px var(--sans);color:var(--ink-dim)">Parsed records will preview here as you paste.</span>
          </sc-if>
        </div>
        <div style="flex:none;display:flex;align-items:center;gap:12px;padding:12px 18px;border-top:1px solid var(--rule)">
          <span style="font:400 12px var(--sans);color:var(--ink-dim)">Replace is destructive — it stages removal of every existing record.</span>
          <div style="flex:1"></div>
          <sc-if value="{{ hasZoneRecs }}" hint-placeholder-val="{{ false }}">
            <button onClick="{{ zoneAppend }}" style="border:1px solid var(--rule);background:var(--paper);color:var(--brand-ink);font:500 12.5px var(--sans);padding:7px 14px;border-radius:8px;cursor:pointer" style-hover="border-color:var(--brand)">Append to editor</button>
            <button onClick="{{ zoneReplace }}" style="border:1px solid var(--bad);background:var(--bad-soft);color:var(--bad);font:600 12.5px var(--sans);padding:7px 14px;border-radius:8px;cursor:pointer">Replace zone</button>
          </sc-if>
        </div>
      </div>
    </div>
  </sc-if>

  <!-- ══ Nameservers dialog ══ -->
  <sc-if value="{{ nsvOpen }}" hint-placeholder-val="{{ false }}">
    <div onClick="{{ closeNsv }}" style="position:fixed;inset:0;background:rgb(10 14 22/.45);z-index:70;display:flex;justify-content:center;align-items:flex-start;padding-top:16vh">
      <div onClick="{{ stopProp }}" style="width:440px;max-width:calc(100vw - 40px);background:var(--paper);border:1px solid var(--rule);border-radius:14px;box-shadow:var(--shadow);overflow:hidden;animation:ccfade .15s ease">
        <div style="display:flex;align-items:center;gap:10px;padding:13px 18px;border-bottom:1px solid var(--rule)">
          <span style="font:600 15px var(--sans)">Edit nameservers</span>
          <div style="flex:1"></div>
          <span onClick="{{ closeNsv }}" style="cursor:pointer;color:var(--ink-dim);width:26px;height:26px;display:grid;place-items:center;border-radius:6px" style-hover="background:var(--panel-2);color:var(--ink)">✕</span>
        </div>
        <div style="padding:14px 18px">
          <textarea value="{{ nsvText }}" onInput="{{ onNsvText }}" style="width:100%;min-height:96px;border:1px solid var(--rule);border-radius:9px;background:var(--panel);color:var(--ink);font:400 12.5px/1.8 var(--mono);padding:10px 12px;outline:none;resize:vertical"></textarea>
          <div style="font:400 12px var(--sans);color:var(--ink-dim);margin-top:8px">One nameserver per line. Changing away from Constellix disables the DNS editor here.</div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;padding:12px 18px;border-top:1px solid var(--rule)">
          <div style="flex:1"></div>
          <span onClick="{{ closeNsv }}" style="font:500 13px var(--sans);color:var(--ink-dim);cursor:pointer">Cancel</span>
          <button onClick="{{ saveNsv }}" style="border:none;background:var(--brand);color:white;font:600 13px var(--sans);padding:8px 16px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">Save nameservers</button>
        </div>
      </div>
    </div>
  </sc-if>

  <!-- ══ Contacts dialog ══ -->
  <sc-if value="{{ ctOpen }}" hint-placeholder-val="{{ false }}">
    <div onClick="{{ closeCt }}" style="position:fixed;inset:0;background:rgb(10 14 22/.45);z-index:70;display:flex;justify-content:center;align-items:flex-start;padding:9vh 20px">
      <div onClick="{{ stopProp }}" style="width:520px;max-width:100%;max-height:82vh;background:var(--paper);border:1px solid var(--rule);border-radius:14px;box-shadow:var(--shadow);display:flex;flex-direction:column;overflow:hidden;animation:ccfade .15s ease">
        <div style="flex:none;display:flex;align-items:center;gap:10px;padding:13px 18px;border-bottom:1px solid var(--rule)">
          <span style="font:600 15px var(--sans)">Edit contacts</span>
          <span style="font:400 12px var(--sans);color:var(--ink-dim)">applies to all 4 roles</span>
          <div style="flex:1"></div>
          <span onClick="{{ closeCt }}" style="cursor:pointer;color:var(--ink-dim);width:26px;height:26px;display:grid;place-items:center;border-radius:6px" style-hover="background:var(--panel-2);color:var(--ink)">✕</span>
        </div>
        <div style="flex:1;overflow-y:auto;padding:14px 18px;display:flex;flex-direction:column;gap:10px">
          <sc-for list="{{ ctFields }}" as="cf" hint-placeholder-count="7">
            <div style="display:flex;align-items:center;gap:12px">
              <span style="width:100px;font:500 12.5px var(--sans);color:var(--ink-dim);flex:none">{{ cf.label }}</span>
              <input value="{{ cf.v }}" onInput="{{ cf.on }}" style="flex:1;height:32px;border:1px solid var(--rule);border-radius:7px;background:var(--paper);color:var(--ink);font:400 12.5px var(--sans);padding:0 9px;outline:none">
            </div>
          </sc-for>
        </div>
        <div style="flex:none;display:flex;align-items:center;gap:12px;padding:12px 18px;border-top:1px solid var(--rule)">
          <div style="flex:1"></div>
          <span onClick="{{ closeCt }}" style="font:500 13px var(--sans);color:var(--ink-dim);cursor:pointer">Cancel</span>
          <button onClick="{{ saveCt }}" style="border:none;background:var(--brand);color:white;font:600 13px var(--sans);padding:8px 16px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">Save contacts</button>
        </div>
      </div>
    </div>
  </sc-if>

  <!-- ══ Backup browse dialog ══ -->
  <sc-if value="{{ bkDlgOpen }}" hint-placeholder-val="{{ false }}">
    <div onClick="{{ closeBkDlg }}" style="position:fixed;inset:0;background:rgb(10 14 22/.45);z-index:70;display:flex;justify-content:center;align-items:flex-start;padding:6vh 20px">
      <div onClick="{{ stopProp }}" style="width:880px;max-width:100%;height:min(620px,84vh);background:var(--paper);border:1px solid var(--rule);border-radius:14px;box-shadow:var(--shadow);display:flex;flex-direction:column;overflow:hidden;animation:ccfade .15s ease">
        <div style="flex:none;display:flex;align-items:center;gap:11px;padding:13px 18px;border-bottom:1px solid var(--rule);flex-wrap:wrap">
          <span style="font:500 12px var(--mono);background:var(--panel-2);border-radius:6px;padding:2px 8px">{{ bkDlgId }}</span>
          <span style="font:600 14.5px var(--sans)">{{ bkDlgWhen }}</span>
          <span style="font:400 12px var(--mono);color:var(--ink-dim)">{{ bkDlgMeta }}</span>
          <div style="flex:1"></div>
          <span onClick="{{ bkDlgRestore }}" style="font:500 12px var(--sans);color:var(--bad);cursor:pointer">Restore…</span>
          <span onClick="{{ closeBkDlg }}" style="cursor:pointer;color:var(--ink-dim);width:26px;height:26px;display:grid;place-items:center;border-radius:6px" style-hover="background:var(--panel-2);color:var(--ink)">✕</span>
        </div>
        <div style="flex:none;display:flex;align-items:center;gap:9px;padding:8px 18px;border-bottom:1px solid var(--rule);background:var(--brand-soft)">
          <span style="flex:none;width:16px;height:16px;border-radius:50%;background:var(--brand);color:white;display:grid;place-items:center;font:600 10px var(--sans)">i</span>
          <span style="font:400 12.5px/1.5 var(--sans);color:var(--brand-ink)">This backup has too many files to show — uploads are omitted for viewing. Everything is still restorable.</span>
        </div>
        <div style="flex:1;display:flex;min-height:0">
          <div style="flex:none;width:340px;border-right:1px solid var(--rule);overflow-y:auto">
            <sc-for list="{{ bkRows }}" as="f" hint-placeholder-count="8">
              <div onClick="{{ f.rowClick }}" style="display:flex;align-items:center;gap:8px;padding:6px 14px 6px {{ f.pad }};cursor:pointer" style-hover="background:var(--panel)">
                <span style="width:12px;color:var(--ink-dim);font:400 11px var(--sans);flex:none">{{ f.arrow }}</span>
                <span onClick="{{ f.toggleSel }}" style="width:15px;height:15px;border:1.5px solid var(--rule);border-radius:4px;display:grid;place-items:center;background:{{ f.checkBg }};color:white;font:600 9px var(--sans);cursor:pointer;flex:none">{{ f.check }}</span>
                <span style="font:400 12.5px var(--mono);color:{{ f.fg }};overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ f.name }}</span>
                <div style="flex:1"></div>
                <span style="font:400 10.5px var(--mono);color:var(--ink-dim);white-space:nowrap">{{ f.meta }}</span>
              </div>
            </sc-for>
          </div>
          <div style="flex:1;min-width:0;overflow-y:auto;display:flex;flex-direction:column">
            <sc-if value="{{ bkHasSel }}" hint-placeholder-val="{{ false }}">
              <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:24px">
                <div style="font:600 22px var(--sans)">{{ bkSelTitle }}</div>
                <div style="font:400 13px var(--mono);color:var(--ink-dim)">{{ bkSelSize }}</div>
                <button onClick="{{ bkDownload }}" style="margin-top:14px;border:none;background:var(--brand);color:white;font:600 13.5px var(--sans);padding:9px 18px;border-radius:8px;cursor:pointer" style-hover="filter:brightness(1.08)">Download</button>
                <span onClick="{{ cancelSel }}" style="margin-top:6px;font:500 12.5px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--ink)">Cancel selection</span>
                <span style="margin-top:12px;font:400 11.5px var(--sans);color:var(--ink-dim)">Packaged in the background and delivered by email.</span>
              </div>
            </sc-if>
            <sc-if value="{{ bkShowPrev }}" hint-placeholder-val="{{ false }}">
              <div style="display:flex;align-items:center;gap:10px;padding:9px 16px;border-bottom:1px solid var(--rule)">
                <span style="font:500 12px var(--mono);color:var(--ink-dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ bkPrevPath }}</span>
                <div style="flex:1"></div>
                <span onClick="{{ closePrev }}" style="font:500 12px var(--sans);color:var(--ink-dim);cursor:pointer" style-hover="color:var(--ink)">Close</span>
              </div>
              <div style="padding:12px 16px;overflow-y:auto">
                <sc-for list="{{ bkPrevLines }}" as="l" hint-placeholder-count="5">
                  <div style="font:400 12.5px/1.7 var(--mono);white-space:pre-wrap">{{ l.text }}</div>
                </sc-for>
              </div>
            </sc-if>
            <sc-if value="{{ bkShowPlaceholder }}" hint-placeholder-val="{{ true }}">
              <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:24px">
                <div style="font:500 17px var(--sans);color:var(--ink-dim)">Select a file or folder.</div>
                <span onClick="{{ selectAll }}" style="font:500 13px var(--sans);color:var(--brand-ink);cursor:pointer">Select everything</span>
                <span style="font:400 11.5px var(--sans);color:var(--ink-dim);margin-top:8px">Click a file name to preview it · check boxes to build a download.</span>
              </div>
            </sc-if>
          </div>
        </div>
      </div>
    </div>
  </sc-if>

  <!-- ══ Rollback version picker ══ -->
  <sc-if value="{{ rbOpen }}" hint-placeholder-val="{{ false }}">
    <div onClick="{{ closeRb }}" style="position:fixed;inset:0;background:rgb(10 14 22/.35);z-index:75;display:flex;justify-content:center;align-items:flex-start;padding-top:22vh">
      <div onClick="{{ stopProp }}" style="width:420px;max-width:calc(100vw - 40px);background:var(--paper);border:1px solid var(--rule);border-radius:14px;box-shadow:var(--shadow);overflow:hidden;animation:ccfade .15s ease">
        <div style="display:flex;align-items:center;gap:10px;padding:13px 16px;border-bottom:1px solid var(--rule)">
          <span style="font:600 14.5px var(--sans)">{{ rbTitle }}</span>
          <div style="flex:1"></div>
          <span onClick="{{ closeRb }}" style="cursor:pointer;color:var(--ink-dim);width:24px;height:24px;display:grid;place-items:center;border-radius:6px" style-hover="background:var(--panel-2);color:var(--ink)">✕</span>
        </div>
        <div onClick="{{ rbPickThis }}" style="padding:12px 16px;border-bottom:1px solid var(--rule);cursor:pointer" style-hover="background:var(--panel)">
          <div style="font:500 13.5px var(--sans)">This version</div>
          <div style="font:400 12.5px var(--sans);color:var(--ink-dim);margin-top:2px">{{ rbThisSub }}</div>
        </div>
        <div onClick="{{ rbPickPrev }}" style="padding:12px 16px;cursor:pointer" style-hover="background:var(--panel)">
          <div style="font:500 13.5px var(--sans)">Previous version</div>
          <div style="font:400 12.5px var(--sans);color:var(--ink-dim);margin-top:2px">{{ rbPrevSub }}</div>
        </div>
      </div>
    </div>
  </sc-if>

  <!-- ══ Command palette ══ -->
  <sc-if value="{{ paletteOpen }}" hint-placeholder-val="{{ false }}">
    <div onClick="{{ closePalette }}" style="position:fixed;inset:0;background:rgb(10 14 22/.42);z-index:80;display:flex;justify-content:center;padding-top:14vh">
      <div onClick="{{ stopProp }}" style="width:600px;max-width:calc(100vw - 40px);height:fit-content;max-height:420px;background:var(--paper);border:1px solid var(--rule);border-radius:14px;box-shadow:var(--shadow);display:flex;flex-direction:column;overflow:hidden;animation:ccfade .15s ease">
        <div style="display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--rule)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ink-dim)" stroke-width="2" stroke-linecap="round"><path d="M11 4a7 7 0 100 14 7 7 0 000-14zm10 17-5.2-5.2"></path></svg>
          <input autofocus value="{{ palQuery }}" onInput="{{ onPalInput }}" placeholder="Search sites, domains, accounts — or run a command…" style="flex:1;border:none;outline:none;background:transparent;color:var(--ink);font:400 15px var(--sans)">
          <span style="font:500 11.5px var(--mono);color:var(--ink-dim);border:1px solid var(--rule);border-radius:5px;padding:2px 6px">esc</span>
        </div>
        <div style="flex:1;overflow-y:auto;padding:6px">
          <sc-for list="{{ palResults }}" as="r" hint-placeholder-count="6">
            <div onClick="{{ r.run }}" style="display:flex;align-items:center;gap:11px;padding:9px 11px;border-radius:8px;cursor:pointer;background:{{ r.bg }}" style-hover="background:var(--panel-2)">
              <span style="flex:none;width:26px;height:26px;border-radius:7px;background:var(--panel-2);color:var(--ink-dim);display:grid;place-items:center">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ r.icon }}"></path></svg>
              </span>
              <span style="font:500 14px var(--sans)">{{ r.label }}</span>
              <span style="font:400 12.5px var(--sans);color:var(--ink-dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.sub }}</span>
              <span style="margin-left:auto;flex:none;font:500 11px var(--sans);letter-spacing:.06em;text-transform:uppercase;color:var(--ink-dim)">{{ r.kind }}</span>
            </div>
          </sc-for>
        </div>
        <div style="flex:none;display:flex;gap:14px;padding:8px 16px;border-top:1px solid var(--rule);font:400 12px var(--sans);color:var(--ink-dim)">
          <span>↑↓ navigate</span><span>⏎ open</span><span>⌃` console</span>
        </div>
      </div>
    </div>
  </sc-if>
</div>
</x-dc>
<script type="text/x-dc" data-dc-script data-props="{&quot;shellVariant&quot;:{&quot;editor&quot;:&quot;enum&quot;,&quot;default&quot;:&quot;rail&quot;,&quot;options&quot;:[&quot;rail&quot;,&quot;slim&quot;,&quot;topnav&quot;],&quot;tsType&quot;:&quot;string&quot;,&quot;section&quot;:&quot;Shell&quot;},&quot;role&quot;:{&quot;editor&quot;:&quot;enum&quot;,&quot;default&quot;:&quot;operator&quot;,&quot;options&quot;:[&quot;operator&quot;,&quot;customer&quot;],&quot;tsType&quot;:&quot;string&quot;,&quot;section&quot;:&quot;Shell&quot;},&quot;brandColor&quot;:{&quot;editor&quot;:&quot;color&quot;,&quot;default&quot;:&quot;#3b82c4&quot;,&quot;options&quot;:[&quot;#3b82c4&quot;,&quot;#2c3e50&quot;,&quot;#0e9f6e&quot;,&quot;#7c5cff&quot;],&quot;tsType&quot;:&quot;string&quot;,&quot;section&quot;:&quot;Brand&quot;}}">
class Component extends DCLogic {
  state = {
    route: 'home', theme: null, dockOpen: false, paletteOpen: false,
    palQuery: '', palIdx: 0, tick: 0,
    view: 'table', q: '', fProv: 'Any', fHealth: 'All', sel: {},
    fUnassigned: false, fBackup: 'Any', fCore: 'Any', fTheme: 'Any',
    fPlugin: 'Any', fPlugVer: 'Any', fPlugStatus: 'Any', fPlugIs: 'IS', fOp: 'AND', labelsSel: {},
    siteId: null, siteTab: 'overview', env: 'Production', addonKind: 'plugins',
    qsOpen: '', bkOpen: '', logFile: 'error.log', copied: '',
    qsFile: '', diffMode: 'unified', bkDirs: { 'wp-content/': true }, bkPreview: '', bkSel: {},
    qsDialog: '', qsView: 'components', rbComp: '', bkDialog: '', shared: null, shareDraft: '',
    timeline: null, tlDraft: '', tlEdit: 0, tlEditText: '',
    nsOpen: false, nsPath: 'request', nsName: '', nsNotes: '', nsAddr: '', nsUser: '', nsPass: '',
    nsAcc: 'Bloom & Branch Floral', nsDc: 'US Central (Iowa)', nsClone: 'None (fresh install)',
    nsProv: 'Kinsta', nsEnvs: 'Production only', nsImportSel: {},
    ndOpen: false, ndName: '', ndAcc: 'Bloom & Branch Floral', ndZone: true, domList: null,
    dnsEdit: 0, dnsEN: '', dnsEV: '', dnsETtl: '',
    zoneOpen: false, zoneText: '', nsvOpen: false, nsvText: '', nsCustom: null,
    ctOpen: false, contact: null, ctDraft: {},
    snapFilter: 'Everything', dq: '', domainId: null, domTab: 'dns',
    dnsRecs: null, dnsDirty: false, dnsT: 'A', dnsN: '', dnsV: '',
    fwds: null, fwdAlias: '', fwdDest: '', reg: { auto: true, lock: true, priv: true },
    ddOpen: '', ddQ: '',
    aq: '', accountId: null, accTab: 'users', accInvites: null, trusted: null,
    invEmail: '', invLevel: 'Full access', billTab: 'invoices', paid: {}, primaryPm: 0,
    statG: 'Daily', statR: 'Last 28 days', statShare: 'Off', statPw: '',
    secTab: 'vulns', threatOpen: '', threatStatus: {}, tNotes: null, noteDraft: '', ckOpen: '',
    audits: null, audSite: 'bloomandbranch.com', audTypes: { Core: true, Plugins: true }, 
    repMode: 'Site', repTarget: 'bloomandbranch.com', repRange: 'Last month', repInt: 'Monthly',
    repEmail: 'sarah@bloomandbranch.com', schedules: null,
    archList: null, archUrl: '', archErr: false,
    setTab: 'branding', brandName: 'Anchor Hosting', keyDraft: '', sshKeys: null,
    profName: 'Austin Ginder', profEmail: 'austin@anchor.host', tfa: 'off', tfaCode: '', appPw: '', sessions: null,
    jobs: [
      { id: 1, label: 'update-wp', target: '3 sites · steer queue', state: 'running', pct: 64 },
      { id: 2, label: 'backup', target: 'cascadecoffeeroasters.com', state: 'running', pct: 31 },
      { id: 3, label: 'quicksave', target: 'bloomandbranch.com', state: 'done', right: '12m ago' },
      { id: 4, label: 'sync-data', target: 'petersonlaw.com', state: 'done', right: '38m ago' }
    ]
  };

  FLEET = [
    { id: 'bloom', name: 'bloomandbranch.com', provider: 'Kinsta', account: 'Bloom & Branch Floral', core: '6.8.1', visits: '12,400', storage: '2.1 GB', envs: 'Prod · Staging', updates: 2, vuln: 1, owned: true, theme: 'kadence', backup: 'Direct', labels: [], plugins: { gravityforms: { v: '2.9.1', status: 'active' }, woocommerce: { v: '9.8.2', status: 'active' }, 'wordpress-seo': { v: '25.3', status: 'active' } } },
    { id: 'cascade', name: 'cascadecoffeeroasters.com', provider: 'Kinsta', account: 'Cascade Coffee', core: '6.8.0', visits: '8,900', storage: '4.6 GB', envs: 'Prod', updates: 2, vuln: 0, theme: 'kadence', backup: 'Direct', labels: [], plugins: { woocommerce: { v: '9.9.0', status: 'active' }, jetpack: { v: '14.2', status: 'inactive' } } },
    { id: 'peterson', name: 'petersonlaw.com', provider: 'WP Engine', account: 'Peterson Law', core: '6.8.1', visits: '3,100', storage: '1.2 GB', envs: 'Prod · Staging', updates: 5, vuln: 0, theme: 'astra', backup: 'Local', labels: [], plugins: { gravityforms: { v: '2.9.4', status: 'active' }, 'advanced-custom-fields': { v: '6.3.7', status: 'active' } } },
    { id: 'harbor', name: 'harborlightyoga.com', provider: 'Kinsta', account: 'Harbor Light Yoga', core: '6.8.1', visits: '1,700', storage: '860 MB', envs: 'Prod', updates: 3, vuln: 1, owned: true, theme: 'kadence', backup: 'Direct', labels: [], plugins: { gravityforms: { v: '2.9.1', status: 'active' }, jetpack: { v: '13.9', status: 'active' } } },
    { id: 'midwest', name: 'midwestmakersmarket.com', provider: 'Rocket.net', account: 'Midwest Makers', core: '6.8.1', visits: '5,300', storage: '3.4 GB', envs: 'Prod', updates: 0, vuln: 0, theme: 'generatepress', backup: 'Direct', labels: [], plugins: { 'advanced-custom-fields': { v: '6.7.0', status: 'active' }, 'wordpress-seo': { v: '25.3', status: 'active' } } },
    { id: 'wildflower', name: 'thewildflowerpantry.com', provider: 'Kinsta', account: 'Wildflower Pantry', core: '6.8.1', visits: '2,200', storage: '1.8 GB', envs: 'Prod', updates: 0, vuln: 0, owned: true, theme: 'astra', backup: 'Direct', labels: [], plugins: { jetpack: { v: '14.2', status: 'active' } } },
    { id: 'stonebridge', name: 'stonebridgedental.com', provider: 'GridPane', account: 'Stonebridge Dental', core: '6.7.2', visits: '940', storage: '620 MB', envs: 'Prod', updates: 7, vuln: 0, theme: 'twentytwentythree', backup: 'Off', labels: ['down'], unassigned: true, plugins: { 'query-monitor': { v: '3.19.0', status: 'inactive' } } },
    { id: 'lakeside', name: 'lakesideinn.com', provider: 'Kinsta', account: 'Lakeside Inn', core: '6.8.1', visits: '4,100', storage: '2.9 GB', envs: 'Prod · Staging', updates: 0, vuln: 0, owned: true, theme: 'kadence', backup: 'Direct', labels: ['moved'], plugins: { woocommerce: { v: '9.9.0', status: 'active' }, jetpack: { v: '14.2', status: 'active' } } }
  ];
  PLUGINS = [
    { name: 'Gravity Forms', slug: 'gravityforms', v: '2.9.1', latest: '2.9.4', active: true },
    { name: 'WooCommerce', slug: 'woocommerce', v: '9.8.2', latest: '9.9.0', active: true },
    { name: 'Yoast SEO', slug: 'wordpress-seo', v: '25.3', latest: '25.3', active: true },
    { name: 'Kadence Blocks', slug: 'kadence-blocks', v: '3.5.12', latest: '3.5.12', active: true },
    { name: 'WP Rocket', slug: 'wp-rocket', v: '3.18.1', latest: '3.18.1', active: true },
    { name: 'Query Monitor', slug: 'query-monitor', v: '3.19.0', latest: '3.19.0', active: false }
  ];
  THEMES = [
    { name: 'Kadence', slug: 'kadence', v: '1.2.14', latest: '1.2.15', active: true },
    { name: 'Twenty Twenty-Five', slug: 'twentytwentyfive', v: '1.2', latest: '1.2', active: false }
  ];
  QUICKSAVES = [
    { hash: '8f3c21a', kind: 'Update', desc: 'gravityforms 2.9.1 → 2.9.4', files: '129 files changed', when: 'Today · 9:14 AM', summary: '129 files changed · +59,118 −5,763 · WP 6.8.1 · 2 themes · 6 plugins', more: 122 },
    { hash: 'b2e90d4', kind: 'Scheduled', desc: 'Nightly quicksave — no changes', files: '0 files', when: 'Yesterday · 11:02 PM', summary: '0 files changed · WP 6.8.1 · 2 themes · 6 plugins', more: 0 },
    { hash: '77aa1fe', kind: 'Manual', desc: 'Before homepage redesign', files: '12 files changed', when: 'Jul 12 · 2:31 PM', summary: '12 files changed · +1,842 −960 · WP 6.8.1 · 2 themes · 6 plugins', more: 5 },
    { hash: 'c30d88b', kind: 'Update', desc: 'WordPress core 6.8.0 → 6.8.1', files: '41 files changed', when: 'Jul 10 · 3:00 AM', summary: '41 files changed · +12,406 −11,988 · WP 6.8.0 → 6.8.1', more: 34 }
  ];
  QS_COMPONENTS = [
    { kind: 'theme', name: 'Kadence', from: '1.2.14', to: '1.2.14', status: 'active' },
    { kind: 'theme', name: 'Twenty Twenty-Five', from: '1.2', to: '1.2', status: 'inactive' },
    { kind: 'plugin', name: 'CaptainCore Helm', from: '1.0.1', to: '', status: 'active', deleted: true },
    { kind: 'plugin', name: 'Minn Admin', from: '', to: '0.10.0', status: 'active', added: true, viewFile: 'wp-content/plugins/minn-admin/changelog.md' },
    { kind: 'plugin', name: 'Gravity Forms', from: '2.9.1', to: '2.9.4', status: 'active', updated: true, viewFile: 'wp-content/plugins/gravityforms/gravityforms.php' },
    { kind: 'plugin', name: 'WooCommerce', from: '9.8.2', to: '9.8.2', status: 'active' },
    { kind: 'plugin', name: 'Yoast SEO', from: '25.3', to: '25.3', status: 'active' },
    { kind: 'plugin', name: 'advanced-cache.php', from: '', to: '', status: 'dropin' }
  ];
  QS_FILES = [
    { path: 'wp-content/plugins/gravityforms/gravityforms.php', st: 'M', add: 2, del: 2, diff: [
      ['ctx', '@@ -15,7 +15,7 @@'],
      ['ctx', '   * Plugin Name: Gravity Forms'],
      ['del', '-  * Version: 2.9.1'],
      ['add', '+  * Version: 2.9.4'],
      ['ctx', '   * Author: Gravity Forms'],
      ['ctx', '@@ -1102,7 +1102,7 @@'],
      ['del', "-  define( 'GF_MIN_WP_VERSION', '6.4' );"],
      ['add', "+  define( 'GF_MIN_WP_VERSION', '6.5' );"]
    ] },
    { path: 'wp-content/plugins/gravityforms/common.php', st: 'M', add: 4, del: 1, diff: [
      ['ctx', '@@ -1042,6 +1042,9 @@'],
      ['add', "+    if ( ! current_user_can( 'edit_posts' ) ) {"],
      ['add', "+        return new WP_Error( 'forbidden', 'Access denied.' );"],
      ['add', '+    }'],
      ['ctx', '     $form = self::get_form( $form_id );'],
      ['ctx', '@@ -2210,7 +2210,7 @@'],
      ['del', "-        $nonce = wp_create_nonce( 'gf_api' );"],
      ['add', "+        $nonce = wp_create_nonce( 'gf_api_v2' );"]
    ] },
    { path: 'wp-content/plugins/gravityforms/js/gravityforms.min.js', st: 'M', add: 1, del: 1, diff: [
      ['ctx', '(minified asset — 1 chunk changed)'],
      ['del', '-var gf_global_version="2.9.1";'],
      ['add', '+var gf_global_version="2.9.4";']
    ] },
    { path: 'wp-content/plugins/minn-admin/changelog.md', st: 'A', add: 341, del: 0, diff: [
      ['ctx', 'new file mode 100644 · @@ -0,0 +1,341 @@'],
      ['add', '+# Changelog'],
      ['add', '+'],
      ['add', '+## v0.10.0 — July 10, 2026'],
      ['add', '+'],
      ['add', '+The surfaces release. One nav item per job, with every capable plugin layered in behind it: Forms, Email Log, Activity Log, Snippets, Redirects and Backups are first-class views with a provider switcher.'],
      ['add', '+'],
      ['add', '+### Added'],
      ['add', '+* Spam settings page showing who filters comment spam.'],
      ['add', '+* Integrations diagnostics on the System page.']
    ] },
    { path: 'wp-content/plugins/minn-admin/assets/js/app.js', st: 'A', add: 1204, del: 0, diff: [
      ['ctx', 'new file — 1,204 lines'],
      ['add', '+(function () {'],
      ['add', '+  const minn = { surfaces: [], providers: new Map() };'],
      ['add', '+  // …'],
      ['add', '+})();']
    ] },
    { path: 'wp-content/plugins/minn-admin/LICENSE', st: 'A', add: 21, del: 0, diff: [
      ['ctx', 'new file mode 100644'],
      ['add', '+GNU GENERAL PUBLIC LICENSE Version 2'],
      ['add', '+Copyright (c) 2026 Minn']
    ] },
    { path: 'wp-content/plugins/captaincore-helm/helm.php', st: 'D', add: 0, del: 812, diff: [
      ['ctx', 'deleted file mode 100644'],
      ['del', '-<?php echo '<?'; ?>php'],
      ['del', '-/* Plugin Name: CaptainCore Helm */'],
      ['del', '-/* Version: 1.0.1 */']
    ] }
  ];
  BACKUPS = [
    { id: 'a81f03c2', when: 'Today · 3:00 AM', size: '2.1 GB', files: '18,442 files' },
    { id: '59be77d1', when: 'Yesterday · 3:00 AM', size: '2.1 GB', files: '18,438 files' },
    { id: '02c1f9ae', when: 'Jul 13 · 3:00 AM', size: '2.0 GB', files: '18,401 files' },
    { id: 'edd04b7c', when: 'Jul 12 · 3:00 AM', size: '2.0 GB', files: '18,394 files' }
  ];
  BK_TREE = [
    { p: 'wp-config.php', meta: '3.2 KB', file: true, prev: true, cnt: 1, kb: 3.2 },
    { p: 'mysql.sql', meta: '48 MB · database dump', file: true, cnt: 1, kb: 49152 },
    { p: 'wp-admin/', meta: '1,204 files · 38 MB', dir: true, cnt: 1204, kb: 38912, children: [
      { p: 'wp-admin/about.php', meta: '12 KB', file: true, cnt: 1, kb: 12 },
      { p: 'wp-admin/admin-ajax.php', meta: '4 KB', file: true, cnt: 1, kb: 4 }
    ] },
    { p: 'wp-includes/', meta: '2,488 files · 64 MB', dir: true, cnt: 2488, kb: 65536, children: [] },
    { p: 'wp-content/', meta: '17,939 files · 1.9 GB', dir: true, cnt: 17939, kb: 1992294, children: [
      { p: 'wp-content/uploads/', meta: '12,204 files · 1.4 GB', dir: true, cnt: 12204, kb: 1468006, children: [
        { p: 'wp-content/uploads/2026/07/hero-summer.jpg', meta: '1.2 MB', file: true, cnt: 1, kb: 1228 },
        { p: 'wp-content/uploads/2026/07/menu-july.pdf', meta: '420 KB', file: true, cnt: 1, kb: 420 }
      ] },
      { p: 'wp-content/themes/kadence/', meta: '388 files · 12 MB', dir: true, cnt: 388, kb: 12288, children: [
        { p: 'wp-content/themes/kadence/functions.php', meta: '18 KB', file: true, prev: true, cnt: 1, kb: 18 },
        { p: 'wp-content/themes/kadence/style.css', meta: '6 KB', file: true, prev: true, cnt: 1, kb: 6 }
      ] },
      { p: 'wp-content/plugins/', meta: '4,102 files · 96 MB', dir: true, cnt: 4102, kb: 98304, children: [
        { p: 'wp-content/plugins/gravityforms/', meta: '412 files · 18 MB', dir: true, cnt: 412, kb: 18432, children: [] },
        { p: 'wp-content/plugins/woocommerce/', meta: '2,381 files · 54 MB', dir: true, cnt: 2381, kb: 55296, children: [] }
      ] },
      { p: 'wp-content/cache/', meta: 'omitted — still restorable', omitted: true, dir: true }
    ] }
  ];
  PREVIEWS = {
    'wp-config.php': ['<?php echo '<?'; ?>php', "define( 'DB_NAME', 'wp_bloomandbranch' );", "define( 'DB_USER', 'bloom_db' );", "define( 'DB_PASSWORD', '************' );", "define( 'WP_DEBUG', false );", "define( 'WP_MEMORY_LIMIT', '256M' );", "$table_prefix = 'wp_';"],
    'wp-content/themes/kadence/style.css': ['/*', ' Theme Name: Kadence', ' Version: 1.2.14', '*/', ':root { --global-palette1: #2c3e50; }'],
    'wp-content/themes/kadence/functions.php': ['<?php echo '<?'; ?>php', "define( 'KADENCE_VERSION', '1.2.14' );", "require_once get_template_directory() . '/inc/init.php';"],
    default: ['(binary file — no inline preview, use Download)']
  };
  WP_USERS = [
    { n: 'Sarah Whitfield', e: 'sarah@SITE', role: 'Administrator', last: '2h ago' },
    { n: 'Austin Ginder', e: 'austin@anchor.host', role: 'Administrator', last: '1d ago' },
    { n: 'Maya Chen', e: 'maya@SITE', role: 'Editor', last: '6d ago' }
  ];
  LOGS = {
    'error.log': [
      '[16-Jul-2026 09:14:22 UTC] PHP Warning:  Undefined array key "size" in /www/wp-content/plugins/woocommerce/includes/wc-cart-functions.php on line 118',
      '[16-Jul-2026 08:52:10 UTC] PHP Deprecated:  _load_textdomain_just_in_time called incorrectly for domain "kadence".',
      '[16-Jul-2026 07:03:41 UTC] PHP Warning:  Attempt to read property "post_type" on null in /www/wp-includes/post.php on line 1067',
      '[15-Jul-2026 22:18:03 UTC] PHP Fatal error:  Allowed memory size exhausted (tried to allocate 2.6 MB) in /www/wp-includes/class-wpdb.php',
      '[15-Jul-2026 20:44:56 UTC] PHP Warning:  Undefined variable $args in /www/wp-content/themes/kadence/inc/template-tags.php on line 44'
    ],
    'access.log': [
      '203.0.113.4 - - [16/Jul/2026:09:12:01 +0000] "GET / HTTP/2" 200 18442 "-" "Mozilla/5.0"',
      '198.51.100.7 - - [16/Jul/2026:09:11:48 +0000] "POST /wp-cron.php HTTP/2" 200 0 "-" "WordPress/6.8.1"',
      '203.0.113.9 - - [16/Jul/2026:09:11:32 +0000] "GET /shop/ HTTP/2" 200 24810 "-" "Mozilla/5.0"',
      '192.0.2.14 - - [16/Jul/2026:09:10:58 +0000] "GET /wp-login.php HTTP/1.1" 403 146 "-" "python-requests/2.32"'
    ],
    'debug.log': [
      '[16-Jul-2026 09:00:12 UTC] CaptainCore: sync-data completed in 4.2s',
      '[16-Jul-2026 03:00:08 UTC] CaptainCore: restic snapshot a81f03c2 created',
      '[16-Jul-2026 03:00:01 UTC] Cron: captaincore_nightly_quicksave started'
    ]
  };

  SNAPSHOTS = [
    { id: 'snap_4c8aa', name: 'db-only-checkout-bug', when: 'Today · 8:02 AM', size: '48 MB', filter: 'Database', expires: '23h left' },
    { id: 'snap_9f2e1', name: 'pre-redesign-full', when: 'Jul 12 · 2:12 PM', size: '2.4 GB', filter: 'Everything', expires: 'expired' },
    { id: 'snap_77b03', name: 'uploads-june', when: 'Jun 30 · 4:44 PM', size: '1.4 GB', filter: 'Uploads', expires: 'expired' }
  ];
  DOMAINS = [
    { id: 'bloomd', name: 'bloomandbranch.com', account: 'Bloom & Branch Floral', registrar: 'Hover', dns: true, expires: 'Mar 12, 2027', auto: true, owned: true },
    { id: 'harbord', name: 'harborlightyoga.com', account: 'Harbor Light Yoga', registrar: 'Hover', dns: true, expires: 'Jul 28, 2026', auto: false, warn: true, owned: true },
    { id: 'petersond', name: 'petersonlaw.com', account: 'Peterson Law', registrar: 'Spaceship', dns: true, expires: 'Nov 3, 2026', auto: true },
    { id: 'wildflowerd', name: 'thewildflowerpantry.com', account: 'Wildflower Pantry', registrar: 'Hover', dns: true, expires: 'Feb 9, 2027', auto: true, owned: true },
    { id: 'midwestd', name: 'midwestmakersmarket.com', account: 'Midwest Makers', registrar: 'Spaceship', dns: true, expires: 'Sep 17, 2026', auto: true },
    { id: 'cascaded', name: 'cascadecoffeeroasters.com', account: 'Cascade Coffee', registrar: 'External (GoDaddy)', dns: false, expires: '—', auto: null },
    { id: 'lakesided', name: 'lakesideinn.com', account: 'Lakeside Inn', registrar: 'Hover', dns: true, expires: 'Jan 22, 2027', auto: true, owned: true }
  ];
  DNS_RECS = [
    { uid: 1, type: 'A', name: '@', value: '35.223.94.108', ttl: '3600' },
    { uid: 2, type: 'CNAME', name: 'www', value: '@', ttl: '3600' },
    { uid: 3, type: 'MX', name: '@', value: '10 mxa.mailgun.org', ttl: '3600' },
    { uid: 4, type: 'MX', name: '@', value: '10 mxb.mailgun.org', ttl: '3600' },
    { uid: 5, type: 'TXT', name: '@', value: 'v=spf1 include:mailgun.org ~all', ttl: '3600' },
    { uid: 6, type: 'TXT', name: 'krs._domainkey', value: 'k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GN…', ttl: '3600' },
    { uid: 7, type: 'CNAME', name: 'email', value: 'mailgun.org', ttl: '3600' }
  ];
  FWDS = [
    { uid: 1, alias: 'hello', dest: 'sarah.whitfield@gmail.com', status: 'Verified' },
    { uid: 2, alias: 'orders', dest: 'sarah.whitfield@gmail.com', status: 'Verified' },
    { uid: 3, alias: '*', dest: 'sarah.whitfield@gmail.com', status: 'Catch-all' }
  ];
  MG_RECS = [
    { type: 'TXT', host: 'mg', value: 'v=spf1 include:mailgun.org ~all', ok: true },
    { type: 'TXT', host: 'krs._domainkey.mg', value: 'k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GN…', ok: true },
    { type: 'CNAME', host: 'email.mg', value: 'mailgun.org', ok: false }
  ];
  MG_EVENTS = [
    { t: '9:18 AM', text: 'Delivered · Order receipt #4521 → customer@gmail.com' },
    { t: '8:47 AM', text: 'Delivered · Contact form → sarah@bloomandbranch.com' },
    { t: '7:02 AM', text: 'Opened · July newsletter (214 of 1,180 so far)' },
    { t: 'Yesterday', text: 'Bounced · promo@oldclient.net (mailbox full) — suppressed' }
  ];

  ACCOUNTS = [
    { id: 'bloomacc', name: 'Bloom & Branch Floral', users: 3, sites: 2, domains: 1, plan: 'Growth · $68/mo', due: true, owned: true },
    { id: 'petersonacc', name: 'Peterson Law', users: 3, sites: 1, domains: 1, plan: 'Standard · $45/mo' },
    { id: 'cascadeacc', name: 'Cascade Coffee', users: 1, sites: 1, domains: 1, plan: 'Growth · $68/mo' },
    { id: 'harboracc', name: 'Harbor Light Yoga', users: 1, sites: 1, domains: 1, plan: 'Starter · $25/mo', owned: true },
    { id: 'midwestacc', name: 'Midwest Makers', users: 2, sites: 1, domains: 1, plan: 'Standard · $45/mo' },
    { id: 'wildfloweracc', name: 'Wildflower Pantry', users: 1, sites: 1, domains: 1, plan: 'Starter · $25/mo', owned: true },
    { id: 'lakesideacc', name: 'Lakeside Inn', users: 1, sites: 1, domains: 1, plan: 'Growth · $68/mo', owned: true }
  ];
  ACC_USERS = [
    { n: 'Sarah Whitfield', e: 'sarah@bloomandbranch.com', level: 'Owner', last: '2h ago' },
    { n: 'Kara Jimenez', e: 'kara@bloomandbranch.com', level: 'Full access', last: '3d ago' },
    { n: 'Devon Price', e: 'devon@studio-partner.com', level: 'Sites only', last: '2w ago' }
  ];
  TRUSTED = [
    { uid: 1, where: 'Lancaster, PA · Comcast', ua: 'macOS · Safari', added: 'via login + TFA', last: 'today' },
    { uid: 2, where: 'Philadelphia, PA · Verizon', ua: 'iOS · Safari', added: 'via invoice link', last: 'Jul 8' }
  ];
  ACC_ACTIVITY = [
    { t: '2h', text: 'Quicksave created on bloomandbranch.com' },
    { t: '1d', text: 'Kara Jimenez logged in from a trusted location' },
    { t: '3d', text: 'Invoice #4482 issued — $68.00, due Jul 22' },
    { t: 'Jul 10', text: 'bookkeeper@ledgerly.com invited (Domains only)' },
    { t: 'Jul 8', text: 'New location verified for Sarah Whitfield (Philadelphia, PA)' },
    { t: 'Jul 1', text: 'Plan renewed — Growth, $68.00 via Visa ··4242' }
  ];
  INVOICES = [
    { id: '#4482', date: 'Jul 1, 2026', amount: '$68.00', items: 'Growth plan · July', due: true },
    { id: '#4391', date: 'Jun 1, 2026', amount: '$68.00', items: 'Growth plan · June' },
    { id: '#4302', date: 'May 1, 2026', amount: '$83.00', items: 'Growth plan + migration · May' },
    { id: '#4218', date: 'Apr 1, 2026', amount: '$68.00', items: 'Growth plan · April' }
  ];
  PAY_METHODS = [
    { label: 'Visa ··4242', sub: 'Expires 04/28' },
    { label: 'ACH ··6789', sub: 'Chase · verified via micro-deposits' }
  ];

  openAccount(id) {
    this.setState({ route: 'account', accountId: id, accTab: 'users', paletteOpen: false,
      accInvites: [{ uid: 1, e: 'bookkeeper@ledgerly.com', level: 'Domains only', sent: 'Jul 10' }],
      trusted: this.TRUSTED.map(t => ({ ...t })), invEmail: '', invLevel: 'Full access' });
  }

  computeAccounts(s, isOp) {
    const list = isOp ? this.ACCOUNTS : this.ACCOUNTS.filter(a => a.owned);
    const nq = s.aq.trim().toLowerCase();
    const filtered = list.filter(a => !nq || a.name.toLowerCase().includes(nq));
    return {
      accCount: filtered.length + ' accounts',
      aq: s.aq, onAq: e => this.setState({ aq: e.target.value }),
      accRows: filtered.map(a => ({ ...a,
        billLabel: a.due ? 'Invoice due' : 'Current',
        billFg: a.due ? 'var(--warn)' : 'var(--ink-dim)',
        open: () => this.openAccount(a.id) }))
    };
  }

  computeAccount(s) {
    const acc = this.ACCOUNTS.find(a => a.id === s.accountId) || this.ACCOUNTS[0];
    const tabs = [['users', 'Users & access'], ['sites', 'Sites'], ['domains', 'Domains'], ['plan', 'Plan'], ['activity', 'Activity']].map(([id, label]) => ({ label,
      fg: s.accTab === id ? 'var(--brand-ink)' : 'var(--ink-dim)',
      line: s.accTab === id ? 'var(--brand)' : 'transparent',
      go: () => this.setState({ accTab: id }) }));
    const healthOf = x => x.vuln ? ['Vulnerability', 'var(--bad)'] : x.updates ? ['Updates pending', 'var(--warn)'] : ['Healthy', 'var(--ok)'];
    return {
      accName: acc.name,
      accMeta: acc.plan + ' · ' + acc.users + ' users · ' + acc.sites + ' sites · ' + acc.domains + ' domain' + (acc.domains > 1 ? 's' : ''),
      accBack: () => this.setState({ route: 'accounts' }),
      accTabs: tabs,
      accTabUsers: s.accTab === 'users', accTabSites: s.accTab === 'sites', accTabDomains: s.accTab === 'domains',
      accTabPlan: s.accTab === 'plan', accTabActivity: s.accTab === 'activity',
      accUsers: this.ACC_USERS.map(u => ({ ...u,
        init: u.n.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
        lvlBg: u.level === 'Owner' ? 'var(--brand-soft)' : 'var(--panel-2)',
        lvlFg: u.level === 'Owner' ? 'var(--brand-ink)' : 'var(--ink-dim)',
        switchTo: () => this.runJob('switch-to', u.e + ' (reason + duration logged)'),
        remove: () => this.runJob('remove-user', u.e + ' from ' + acc.name) })),
      accInvites: (s.accInvites || []).map(iv => ({ ...iv,
        mark: s.copied === 'inv' + iv.uid ? 'Copied ✓' : 'Copy link',
        copyLink: () => { try { navigator.clipboard.writeText('https://anchor.host/invite/' + iv.uid + '?key=…'); } catch (e) {}
          this.setState({ copied: 'inv' + iv.uid }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        del: () => this.setState(st => ({ accInvites: st.accInvites.filter(x => x.uid !== iv.uid) })) })),
      invEmail: s.invEmail, onInvEmail: e => this.setState({ invEmail: e.target.value }),
      invLevel: s.invLevel,
      ddInvOpen: s.ddOpen === 'inv',
      ddToggleInv: () => this.setState(st => ({ ddOpen: st.ddOpen === 'inv' ? '' : 'inv', ddQ: '' })),
      ddInvOpts: this.ddOpts(['Full access', 'Sites only', 'Domains only'], s.invLevel, 'invLevel'),
      sendInvite: () => { const e = this.state.invEmail.trim(); if (!e) return;
        this.setState(st => ({ accInvites: [...st.accInvites, { uid: Date.now(), e, level: st.invLevel, sent: 'just now' }], invEmail: '' })); },
      trusted: (s.trusted || []).map(td => ({ ...td,
        revoke: () => this.setState(st => ({ trusted: st.trusted.filter(x => x.uid !== td.uid) })) })),
      accSites: this.FLEET.filter(x => x.account === acc.name).map(x => { const [health, dot] = healthOf(x);
        return { ...x, health, dot, open: () => this.openSite(x.id) }; }),
      accDomains: this.DOMAINS.filter(d => d.account === acc.name).map(d => ({ ...d,
        expFg: d.warn ? 'var(--bad)' : 'var(--ink-dim)', open: () => this.openDomain(d.id) })),
      planUsage: [
        { k: 'Sites', used: acc.sites + ' of 3', pct: Math.round(acc.sites / 3 * 100) },
        { k: 'Storage', used: '3.9 of 10 GB', pct: 39 },
        { k: 'Visits / mo', used: '14.1k of 50k', pct: 28 }
      ].map(u => ({ ...u, fill: u.pct >= 80 ? 'var(--warn)' : 'var(--brand)' })),
      planRows: [
        { k: 'Plan', v: acc.plan }, { k: 'Interval', v: 'Monthly' }, { k: 'Next renewal', v: 'Aug 1, 2026' },
        { k: 'Auto-pay', v: 'On · Visa ··4242' }, { k: 'Addons', v: 'Priority support +$10/mo' }, { k: 'Credits', v: '−$15.00' }
      ],
      planRequest: () => this.runJob('plan-request', acc.name + ' — change request sent'),
      accActivity: this.ACC_ACTIVITY
    };
  }

  computeBilling(s) {
    const tabs = [['invoices', 'Invoices'], ['methods', 'Payment methods'], ['address', 'Billing address']].map(([id, label]) => ({ label,
      fg: s.billTab === id ? 'var(--brand-ink)' : 'var(--ink-dim)',
      line: s.billTab === id ? 'var(--brand)' : 'transparent',
      go: () => this.setState({ billTab: id }) }));
    return {
      billTabs: tabs,
      billTabInv: s.billTab === 'invoices', billTabPm: s.billTab === 'methods', billTabAddr: s.billTab === 'address',
      invoices: this.INVOICES.map(iv => { const paid = !iv.due || s.paid[iv.id];
        return { ...iv,
          status: !iv.due ? 'Paid' : s.paid[iv.id] ? 'Paid · just now' : 'Due Jul 22',
          stBg: paid ? 'var(--ok-soft)' : 'var(--warn-soft)', stFg: 'var(--ink)',
          canPay: iv.due && !s.paid[iv.id],
          pay: () => this.setState(st => ({ paid: { ...st.paid, [iv.id]: true } })) }; }),
      payMethods: this.PAY_METHODS.map((pm, i) => ({ ...pm,
        isPrimary: s.primaryPm === i, canPrimary: s.primaryPm !== i,
        setPrimary: () => this.setState({ primaryPm: i }) }))
    };
  }

  THREATS = [
    { id: 't1', sev: 'High', name: 'Gravity Forms ≤ 2.9.3 — auth bypass', cve: 'CVE-2026-3181', patch: true, status: 'New',
      sites: ['bloomandbranch.com', 'harborlightyoga.com', 'petersonlaw.com', 'cascadecoffeeroasters.com', 'lakesideinn.com'],
      findings: 'Authentication bypass in the form-submission REST endpoint allows unauthenticated entry creation. Fixed upstream in 2.9.4.',
      rec: 'Update to 2.9.4 or apply the vendor patch. Exploitation observed in the wild.' },
    { id: 't2', sev: 'Medium', name: 'WooCommerce 9.8.x — order note XSS', cve: 'CVE-2026-2907', patch: false, status: 'Investigating',
      sites: ['bloomandbranch.com', 'cascadecoffeeroasters.com'],
      findings: 'Stored XSS via customer order notes rendered unescaped in wp-admin.',
      rec: 'Update to 9.9.0.' },
    { id: 't3', sev: 'Low', name: 'Query Monitor — info disclosure', cve: '—', patch: false, status: 'Resolved',
      sites: ['stonebridgedental.com'],
      findings: 'Debug output visible to logged-in subscriber-role users.',
      rec: 'Keep deactivated on production.' }
  ];
  T_NOTES_INIT = { t1: [{ who: 'Austin', when: 'Jul 9', text: 'Vendor patch confirmed working on staging.' }] };
  CORE_FAILS = [
    { id: 'cf1', site: 'stonebridgedental.com', mod: 2, extra: 1,
      files: ['wp-includes/pluggable.php — modified', 'wp-admin/index.php — modified', 'wp-content/db-error.php — extra (not in 6.7.2 manifest)'] }
  ];
  PLUG_FAILS = [
    { id: 'pf1', site: 'midwestmakersmarket.com', slug: 'wordpress-seo', chips: ['admin/class-admin.php ~', 'inc/options.php ~'],
      diff: [['ctx', '@@ admin/class-admin.php vs wordpress.org 25.3 @@'], ['del', '-        echo $notice;'], ['add', '+        echo base64_decode( $opt[\'x\'] ); // injected']] }
  ];
  AUDITS_INIT = [
    { id: 'a1', site: 'bloomandbranch.com', env: 'Production', types: 'Full audit', status: 'Published', when: 'Jul 8', findings: '2 medium · 5 low', pub: true },
    { id: 'a2', site: 'petersonlaw.com', env: 'Production', types: 'Plugins + Themes', status: 'Complete', when: 'Jul 11', findings: '1 high · 3 low', pub: false },
    { id: 'a3', site: 'stonebridgedental.com', env: 'Production', types: 'Core checksums', status: 'Running', when: 'today', findings: '—', running: true }
  ];
  SCHED_INIT = [
    { id: 's1', target: 'Bloom & Branch Floral', interval: 'Monthly', next: 'Aug 1', recipients: '2' },
    { id: 's2', target: 'Peterson Law', interval: 'Quarterly', next: 'Oct 1', recipients: '1' }
  ];
  ARCH_INIT = [
    { id: 'ar1', name: 'oldclientsite-migration.zip', size: '1.8 GB', mod: 'Jul 2, 2026' },
    { id: 'ar2', name: 'legacy-multisite-export.zip', size: '6.2 GB', mod: 'May 18, 2026' },
    { id: 'ar3', name: 'photography-portfolio.zip', size: '940 MB', mod: 'Mar 30, 2026' }
  ];
  KEYS_INIT = [{ id: 'k1', name: 'MacBook Pro', fp: 'SHA256:pR2wVd…3kQz', primary: true }];
  TIMELINE_INIT = [
    { uid: 1, text: 'Install Elementor Pro', who: 'Austin Ginder', when: 'Apr 9, 2026 · 4:32 PM' },
    { uid: 2, text: 'Security update: updated gravityforms to 2.9.31', who: 'Austin Ginder', when: 'Apr 5, 2026 · 8:24 AM' },
    { uid: 3, text: 'Restored website from restic snapshot', who: 'Austin Ginder', when: 'Mar 2, 2026 · 6:55 AM' },
    { uid: 4, text: 'Reset file permissions', who: 'Austin Ginder', when: 'Mar 1, 2026 · 12:38 AM' },
    { uid: 5, text: 'Updated A records for new Kinsta Cloudflare integration', who: 'Austin Ginder', when: 'Apr 6, 2021 · 2:50 PM' }
  ];
  SHARED_INIT = [
    { uid: 1, name: 'Bloom & Branch Floral', people: 3, level: 'Owner account', accId: 'bloomacc', owner: true },
    { uid: 2, name: 'Studio Partner LLC', people: 1, level: 'Sites only', accId: null }
  ];
  SESS_INIT = [
    { id: 'se1', where: 'Lancaster, PA', ua: 'macOS · Safari', last: 'active now', current: true },
    { id: 'se2', where: 'Philadelphia, PA', ua: 'iOS · Safari', last: 'Jul 8' }
  ];

  computeSecurity(s) {
    const tabs = [['vulns', 'Vulnerabilities'], ['checksums', 'Checksums'], ['coverage', 'Coverage']].map(([id, label]) => ({ label,
      fg: s.secTab === id ? 'var(--brand-ink)' : 'var(--ink-dim)',
      line: s.secTab === id ? 'var(--brand)' : 'transparent',
      go: () => this.setState({ secTab: id }) }));
    const sevStyle = { High: ['var(--bad-soft)', 'var(--bad)'], Medium: ['var(--warn-soft)', 'var(--ink)'], Low: ['var(--panel-2)', 'var(--ink-dim)'] };
    const stBg = { New: 'var(--bad-soft)', Investigating: 'var(--warn-soft)', Resolved: 'var(--ok-soft)' };
    const notes = s.tNotes || this.T_NOTES_INIT;
    const threats = this.THREATS.map(t => { const status = s.threatStatus[t.id] || t.status; return { ...t, status,
      sevBg: sevStyle[t.sev][0], sevFg: sevStyle[t.sev][1],
      stBg: stBg[status] || 'var(--panel-2)',
      siteCount: t.sites.length,
      open: s.threatOpen === t.id,
      toggle: () => this.setState(st => ({ threatOpen: st.threatOpen === t.id ? '' : t.id, noteDraft: '' })),
      siteRows: t.sites.map(name => { const f = this.FLEET.find(x => x.name === name);
        return { name, go: () => f && this.openSite(f.id) }; }),
      notes: notes[t.id] || [],
      addNote: () => { const txt = this.state.noteDraft.trim(); if (!txt) return;
        this.setState(st => { const cur = st.tNotes || this.T_NOTES_INIT;
          return { tNotes: { ...cur, [t.id]: [...(cur[t.id] || []), { who: 'Austin', when: 'just now', text: txt }] }, noteDraft: '' }; }); },
      openTerm: () => this.setState({ dockOpen: true }),
      getPatch: () => this.runJob('patch-download', t.cve),
      markInv: () => this.setState(st => ({ threatStatus: { ...st.threatStatus, [t.id]: 'Investigating' } })),
      markRes: () => { this.setState(st => ({ threatStatus: { ...st.threatStatus, [t.id]: 'Resolved' } }));
        this.runJob('threat-resolve', t.cve + ' — process log on ' + t.sites.length + ' sites'); } }; });
    return {
      secTabs: tabs, secTabVulns: s.secTab === 'vulns', secTabCk: s.secTab === 'checksums', secTabCov: s.secTab === 'coverage',
      threats, noteDraft: s.noteDraft, onNoteDraft: e => this.setState({ noteDraft: e.target.value }),
      coreFails: this.CORE_FAILS.map(c => ({ ...c, open: s.ckOpen === c.id,
        files: c.files.map(p => ({ p })),
        toggle: () => this.setState(st => ({ ckOpen: st.ckOpen === c.id ? '' : c.id })),
        sshMark: s.copied === 'ssh' + c.id ? 'Copied ✓' : 'Copy SSH',
        copySSH: (e) => { e.stopPropagation(); try { navigator.clipboard.writeText('ssh ' + c.site.split('.')[0] + '@35.223.94.108'); } catch (err) {}
          this.setState({ copied: 'ssh' + c.id }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        repair: (e) => { e.stopPropagation(); this.runJob('checksum-repair', c.site); } })),
      plugFails: this.PLUG_FAILS.map(c => ({ ...c, open: s.ckOpen === c.id,
        chips: c.chips.map(f => ({ f })),
        toggle: () => this.setState(st => ({ ckOpen: st.ckOpen === c.id ? '' : c.id })),
        diff: c.diff.map(([kind, text]) => ({ text,
          fg: kind === 'add' ? 'var(--ok)' : kind === 'del' ? 'var(--bad)' : 'var(--ink-dim)',
          bg: kind === 'add' ? 'var(--ok-soft)' : kind === 'del' ? 'var(--bad-soft)' : 'transparent' })) })),
      covTiles: [
        { k: 'Fleet coverage', v: '87%', fg: 'var(--ink)' },
        { k: 'With fresh hashes', v: '92%', fg: 'var(--ink)' },
        { k: 'Vulns scanned', v: '128 / 128', fg: 'var(--ink)' },
        { k: 'Audits < 30d old', v: '74%', fg: 'var(--warn)' }
      ],
      covBars: [['Core', 100], ['Plugins', 89], ['Themes', 81], ['Must-use / dropins', 64]].map(([k, pct]) => ({ k, pct,
        fill: pct >= 80 ? 'var(--ok)' : pct >= 50 ? 'var(--warn)' : 'var(--bad)' })),
      queueStale: () => this.runJob('audit-queue', '9 stale sites'),
      steerQueue: () => { this.runJob('drift --steer --force', '14 sites · updates before audit'); this.setState({ dockOpen: true }); }
    };
  }

  computeAudits(s) {
    const audits = s.audits || this.AUDITS_INIT;
    const stBg = { Published: 'var(--ok-soft)', Complete: 'var(--brand-soft)', Running: 'var(--warn-soft)', Queued: 'var(--panel-2)' };
    return {
      audSite: s.audSite,
      ddAudOpen: s.ddOpen === 'aud',
      ddToggleAud: () => this.setState(st => ({ ddOpen: st.ddOpen === 'aud' ? '' : 'aud', ddQ: '' })),
      ddAudOpts: this.ddOpts(this.FLEET.map(f => f.name), s.audSite, 'audSite'),
      audTypeChips: ['Core', 'Plugins', 'Themes', 'Users', 'Config', 'Malware'].map(label => { const on = !!s.audTypes[label];
        return { label,
          bg: on ? 'var(--brand-soft)' : 'var(--paper)', fg: on ? 'var(--brand-ink)' : 'var(--ink-dim)', bd: on ? 'var(--brand)' : 'var(--rule)',
          go: () => this.setState(st => ({ audTypes: { ...st.audTypes, [label]: !st.audTypes[label] } })) }; }),
      requestAudit: () => { const types = Object.keys(this.state.audTypes).filter(k => this.state.audTypes[k]);
        if (!types.length) return;
        this.setState(st => ({ audits: [{ id: 'a' + Date.now(), site: st.audSite, env: 'Production', types: types.join(' + '), status: 'Queued', when: 'just now', findings: '—', queued: true }, ...(st.audits || this.AUDITS_INIT)] }));
        this.runJob('site-audit', this.state.audSite); },
      audRows: audits.map(a => ({ ...a,
        stBg: stBg[a.status] || 'var(--panel-2)',
        done: a.status === 'Published' || a.status === 'Complete',
        pubLabel: a.pub ? 'Unpublish' : 'Publish',
        cancellable: a.status === 'Running' || a.status === 'Queued',
        togglePub: () => this.setState(st => ({ audits: (st.audits || this.AUDITS_INIT).map(x => x.id === a.id ? { ...x, pub: !x.pub, status: x.pub ? 'Complete' : 'Published' } : x) })),
        copyLink: () => { try { navigator.clipboard.writeText('https://anchor.host/site-audits/' + a.id + '?key=…'); } catch (e) {}
          this.setState({ copied: 'aud' + a.id }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        mark: s.copied === 'aud' + a.id ? 'Copied ✓' : 'Copy link',
        cancel: () => this.setState(st => ({ audits: (st.audits || this.AUDITS_INIT).filter(x => x.id !== a.id) })) }))
    };
  }

  computeReports(s, isOp) {
    const targets = s.repMode === 'Site' ? this.FLEET.map(f => f.name) : this.ACCOUNTS.map(a => a.name);
    const target = targets.includes(s.repTarget) ? s.repTarget : targets[0];
    const schedules = s.schedules || this.SCHED_INIT;
    return {
      repModeChips: ['Site', 'Account'].map(label => ({ label,
        bg: s.repMode === label ? 'var(--brand-soft)' : 'var(--paper)', fg: s.repMode === label ? 'var(--brand-ink)' : 'var(--ink-dim)', bd: s.repMode === label ? 'var(--brand)' : 'var(--rule)',
        go: () => this.setState({ repMode: label }) })),
      repTarget: target,
      ddRepTOpen: s.ddOpen === 'repT',
      ddToggleRepT: () => this.setState(st => ({ ddOpen: st.ddOpen === 'repT' ? '' : 'repT', ddQ: '' })),
      ddRepTOpts: this.ddOpts(targets, target, 'repTarget'),
      repRange: s.repRange,
      ddRepROpen: s.ddOpen === 'repR',
      ddToggleRepR: () => this.setState(st => ({ ddOpen: st.ddOpen === 'repR' ? '' : 'repR', ddQ: '' })),
      ddRepROpts: this.ddOpts(['Last month', 'Last quarter', 'This year'], s.repRange, 'repRange'),
      repInt: s.repInt,
      ddRepIOpen: s.ddOpen === 'repI',
      ddToggleRepI: () => this.setState(st => ({ ddOpen: st.ddOpen === 'repI' ? '' : 'repI', ddQ: '' })),
      ddRepIOpts: this.ddOpts(['Monthly', 'Quarterly', 'Yearly'], s.repInt, 'repInt'),
      repEmail: s.repEmail, onRepEmail: e => this.setState({ repEmail: e.target.value }),
      repPreview: () => this.runJob('report-preview', target + ' · ' + this.state.repRange),
      repSend: () => this.runJob('report-send', target + ' → ' + this.state.repEmail),
      addSchedule: () => this.setState(st => ({ schedules: [...(st.schedules || this.SCHED_INIT), { id: 's' + Date.now(), target, interval: st.repInt, next: 'Aug 1', recipients: '1' }] })),
      schedRows: schedules.map(sr => ({ ...sr,
        del: () => this.setState(st => ({ schedules: (st.schedules || this.SCHED_INIT).filter(x => x.id !== sr.id) })) }))
    };
  }

  computeArchives(s) {
    const list = s.archList || this.ARCH_INIT;
    return {
      archTotal: list.length + ' archives · 8.9 GB on Backblaze B2',
      archUrl: s.archUrl, onArchUrl: e => this.setState({ archUrl: e.target.value, archErr: false }),
      archBd: s.archErr ? 'var(--bad)' : 'var(--rule)', archErr: s.archErr,
      storeArch: () => { const u = this.state.archUrl.trim();
        if (!u.toLowerCase().endsWith('.zip')) { this.setState({ archErr: true }); return; }
        const name = u.split('/').pop();
        this.setState(st => ({ archList: [{ id: 'ar' + Date.now(), name, size: '—', mod: 'storing', storing: true }, ...(st.archList || this.ARCH_INIT)], archUrl: '' }));
        this.runJob('archive-store', name); },
      archRows: list.map(ar => ({ ...ar,
        mark: s.copied === ar.id ? 'Copied ✓' : 'Share link (7d)',
        share: () => { try { navigator.clipboard.writeText('https://f002.backblazeb2.com/anchor-archives/' + ar.name + '?auth=…'); } catch (e) {}
          this.setState({ copied: ar.id }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        del: () => this.setState(st => ({ archList: (st.archList || this.ARCH_INIT).filter(x => x.id !== ar.id) })) }))
    };
  }

  computeSettings(s) {
    const tabs = [['branding', 'Branding'], ['providers', 'Providers'], ['defaults', 'Site defaults'], ['keys', 'SSH keys'], ['cookbook', 'Cookbook'], ['handbook', 'Handbook']].map(([id, label]) => ({ label,
      fg: s.setTab === id ? 'var(--brand-ink)' : 'var(--ink-dim)',
      line: s.setTab === id ? 'var(--brand)' : 'transparent',
      go: () => this.setState({ setTab: id }) }));
    const keys = s.sshKeys || this.KEYS_INIT;
    return {
      setTabs: tabs,
      setTabBrand: s.setTab === 'branding', setTabProv: s.setTab === 'providers', setTabDef: s.setTab === 'defaults',
      setTabKeys: s.setTab === 'keys', setTabCook: s.setTab === 'cookbook', setTabHand: s.setTab === 'handbook',
      brandName: s.brandName, onBrandName: e => this.setState({ brandName: e.target.value }),
      brandSwatches: [['primary', 'var(--brand)'], ['success', 'var(--ok)'], ['warning', 'var(--warn)'], ['error', 'var(--bad)'], ['ink', 'var(--ink)']].map(([k, c]) => ({ k, c })),
      brandSaveLabel: s.copied === 'brand' ? 'Saved ✓' : 'Save branding',
      saveBrand: () => { this.setState({ copied: 'brand' }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
      provRows: [
        { name: 'Kinsta', sub: 'Connected · 82 sites', dot: 'var(--ok)', action: 'Verify', canImport: true },
        { name: 'WP Engine', sub: 'Connected · 24 sites', dot: 'var(--ok)', action: 'Verify', canImport: true },
        { name: 'Rocket.net', sub: 'Connected · 12 sites', dot: 'var(--ok)', action: 'Verify', canImport: true },
        { name: 'GridPane', sub: 'Token expired — reconnect required', dot: 'var(--bad)', action: 'Reconnect', canImport: false },
        { name: 'Envato', sub: 'Connected · plugin & theme purchases', dot: 'var(--ok)', action: 'Verify', canImport: false }
      ].map(p => ({ ...p,
        verify: () => this.runJob(p.action.toLowerCase() + '-provider', p.name),
        doImport: () => this.runJob('provider-import', p.name + ' — remote sites + billing preview') })),
      defRows: [['Default email', 'support@anchor.host'], ['Timezone', 'America/New_York'], ['Recipes on new site', 'security-baseline · smtp-setup'], ['Default users', 'anchor-admin (Administrator)']].map(([k, v]) => ({ k, v })),
      keyRows: keys.map(k => ({ ...k,
        del: () => this.setState(st => ({ sshKeys: (st.sshKeys || this.KEYS_INIT).filter(x => x.id !== k.id) })) })),
      keyDraft: s.keyDraft, onKeyDraft: e => this.setState({ keyDraft: e.target.value }),
      addKey: () => { const v = this.state.keyDraft.trim(); if (!v.startsWith('ssh-')) return;
        this.setState(st => ({ sshKeys: [...(st.sshKeys || this.KEYS_INIT), { id: 'k' + Date.now(), name: v.split(' ').pop() || 'new key', fp: 'SHA256:' + Math.random().toString(36).slice(2, 8) + '…', primary: false }], keyDraft: '' })); },
      rotateKey: () => this.runJob('rotate-management-key', 'fleet-wide SSH key rotation'),
      recipeRows: [
        { name: 'Install security baseline', vis: 'Public', runs: '142' },
        { name: 'Deploy SMTP via Mailgun', vis: 'Public', runs: '96' },
        { name: 'Clean transients + optimize DB', vis: 'Private', runs: '61' },
        { name: 'Set up Fathom analytics', vis: 'Public', runs: '38' }
      ].map(r => ({ ...r, visBg: r.vis === 'Public' ? 'var(--ok-soft)' : 'var(--panel-2)',
        run: () => { this.runJob('recipe', r.name); this.setState({ dockOpen: true }); } })),
      handRows: [
        { name: 'New site onboarding', updated: 'Jun 12' },
        { name: 'Site migration checklist', updated: 'May 30' },
        { name: 'Incident response — malware', updated: 'Apr 22' },
        { name: 'Offboarding a customer', updated: 'Feb 14' }
      ].map(h => ({ ...h }))
    };
  }

  computeProfile(s) {
    const sessions = s.sessions || this.SESS_INIT;
    return {
      profName: s.profName, onProfName: e => this.setState({ profName: e.target.value }),
      profEmail: s.profEmail, onProfEmail: e => this.setState({ profEmail: e.target.value }),
      profSaveLabel: s.copied === 'prof' ? 'Saved ✓' : 'Save profile',
      saveProfile: () => { this.setState({ copied: 'prof' }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
      tfaOff: s.tfa === 'off', tfaSetup: s.tfa === 'setup', tfaOn: s.tfa === 'on',
      tfaLabel: s.tfa === 'on' ? 'On' : 'Off',
      tfaBg: s.tfa === 'on' ? 'var(--ok-soft)' : 'var(--panel-2)',
      tfaStart: () => this.setState({ tfa: 'setup', tfaCode: '' }),
      tfaCode: s.tfaCode, onTfaCode: e => this.setState({ tfaCode: e.target.value }),
      tfaActivate: () => { if (this.state.tfaCode.trim().length === 6) this.setState({ tfa: 'on' }); },
      tfaDisable: () => this.setState({ tfa: 'off' }),
      appPwShown: !!s.appPw, appPw: s.appPw,
      appPwBtn: s.appPw ? 'Rotate' : 'Generate',
      genAppPw: () => this.setState({ appPw: 'ccpw_' + Math.random().toString(36).slice(2, 10) + Math.random().toString(36).slice(2, 6) }),
      appPwMark: s.copied === 'apppw' ? 'Copied ✓' : 'Copy',
      copyAppPw: () => { try { navigator.clipboard.writeText(this.state.appPw); } catch (e) {}
        this.setState({ copied: 'apppw' }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
      sessRows: sessions.map(se => ({ ...se, killable: !se.current,
        kill: () => this.setState(st => ({ sessions: (st.sessions || this.SESS_INIT).filter(x => x.id !== se.id) })) })),
      killOthers: () => this.setState(st => ({ sessions: (st.sessions || this.SESS_INIT).filter(x => x.current) }))
    };
  }

  runJob(label, target) {
    this.setState(st => ({ jobs: [{ id: Date.now(), label, target, state: 'running', pct: 4 }, ...st.jobs] }));
  }
  openSite(id) {
    this.setState({ route: 'site', siteId: id, siteTab: 'overview', env: 'Production', qsOpen: '', bkOpen: '', paletteOpen: false });
  }

  computeList(s, isOp) {
    const healthOf = x => x.vuln ? ['Vulnerability', 'var(--bad)'] : x.updates ? ['Updates pending', 'var(--warn)'] : ['Healthy', 'var(--ok)'];
    const fleet = isOp ? this.FLEET : this.FLEET.filter(x => x.owned);
    const nq = s.q.trim().toLowerCase();
    const inactive = v => !v || v === 'Any' || v === 'All';
    const cntBy = fn => { const m = {}; fleet.forEach(x => { const k = fn(x); if (k) m[k] = (m[k] || 0) + 1; }); return m; };
    const plugCnt = {}; fleet.forEach(x => Object.keys(x.plugins || {}).forEach(sl => { plugCnt[sl] = (plugCnt[sl] || 0) + 1; }));
    const withPlug = inactive(s.fPlugin) ? [] : fleet.filter(x => (x.plugins || {})[s.fPlugin]);
    const verCnt = {}; const statCnt = {};
    withPlug.forEach(x => { const p = x.plugins[s.fPlugin]; verCnt[p.v] = (verCnt[p.v] || 0) + 1; statCnt[p.status] = (statCnt[p.status] || 0) + 1; });
    const conds = [];
    if (s.fUnassigned) conds.push(x => !!x.unassigned);
    if (!inactive(s.fProv)) conds.push(x => x.provider === s.fProv);
    if (!inactive(s.fBackup)) conds.push(x => x.backup === s.fBackup);
    if (!inactive(s.fCore)) conds.push(x => x.core === s.fCore);
    if (!inactive(s.fTheme)) conds.push(x => x.theme === s.fTheme);
    if (!inactive(s.fPlugin)) conds.push(x => { const p = (x.plugins || {})[s.fPlugin];
      if (!p) return false;
      if (!inactive(s.fPlugVer) && p.v !== s.fPlugVer) return false;
      if (!inactive(s.fPlugStatus)) { const eq = p.status === s.fPlugStatus; if (s.fPlugIs === 'IS' ? !eq : eq) return false; }
      return true; });
    const selLabels = Object.keys(s.labelsSel).filter(k => s.labelsSel[k]);
    const passFacets = x => conds.length === 0 || (s.fOp === 'AND' ? conds.every(c => c(x)) : conds.some(c => c(x)));
    const filtered = fleet.filter(x => (!nq || x.name.includes(nq) || x.account.toLowerCase().includes(nq))
      && passFacets(x)
      && (selLabels.length === 0 || (x.labels || []).some(l => selLabels.includes(l))));
    const facetOpts = (cntMap, cur, key, extraReset) => {
      const items = ['Any', ...Object.keys(cntMap).sort()];
      const nq2 = s.ddQ.trim().toLowerCase();
      return (nq2 ? items.filter(i => i.toLowerCase().includes(nq2)) : items).map(label => ({ label,
        badge: label === 'Any' ? '' : (cntMap[label] || 0) + ' sites',
        mark: label === cur ? '✓' : '',
        bg: label === cur ? 'var(--brand-soft)' : 'transparent',
        pick: () => this.setState({ [key]: label, ddOpen: '', ddQ: '', ...(extraReset || {}) }) }));
    };
    const mkFacet = (id, base, cur, opts) => ({ id,
      label: inactive(cur) ? base : base + ' · ' + cur,
      bg: inactive(cur) ? 'var(--paper)' : 'var(--brand-soft)',
      fg: inactive(cur) ? 'var(--ink-dim)' : 'var(--brand-ink)',
      bd: inactive(cur) ? 'var(--rule)' : 'var(--brand)',
      open: s.ddOpen === id,
      toggle: () => this.setState(st => ({ ddOpen: st.ddOpen === id ? '' : id, ddQ: '' })),
      opts });
    const unassignedCnt = fleet.filter(x => x.unassigned).length;
    const labelCnt = cntBy(x => (x.labels || [])[0]);
    const chip = (label, cur, key) => ({ label,
      bg: cur === label ? 'var(--brand-soft)' : 'var(--paper)',
      fg: cur === label ? 'var(--brand-ink)' : 'var(--ink-dim)',
      bd: cur === label ? 'var(--brand)' : 'var(--rule)',
      go: () => this.setState({ [key]: label }) });
    const selIds = filtered.filter(x => s.sel[x.id]).map(x => x.id);
    const rows = filtered.map(x => { const [health, dot] = healthOf(x); return { ...x, health, dot,
      updLabel: x.updates ? x.updates + ' pending' : '—',
      updBg: x.updates ? 'var(--warn-soft)' : 'transparent',
      updFg: x.updates ? 'var(--ink)' : 'var(--ink-dim)',
      check: s.sel[x.id] ? '✓' : '', checkBg: s.sel[x.id] ? 'var(--brand)' : 'var(--paper)',
      toggle: (e) => { e.stopPropagation(); this.setState(st => ({ sel: { ...st.sel, [x.id]: !st.sel[x.id] } })); },
      open: () => this.openSite(x.id),
      openTerm: (e) => { e.stopPropagation(); this.setState({ dockOpen: true }); } }; });
    const envCount = filtered.reduce((n, x) => n + (x.envs.includes('Staging') ? 2 : 1), 0);
    const allSel = filtered.length > 0 && selIds.length === filtered.length;
    return {
      sitesCount: filtered.length + ' sites · ' + envCount + ' environments',
      q: s.q, onQ: (e) => this.setState({ q: e.target.value }),
      unassignedLabel: unassignedCnt + ' unassigned',
      unBg: s.fUnassigned ? 'var(--warn-soft)' : 'var(--paper)',
      unFg: s.fUnassigned ? 'var(--ink)' : 'var(--ink-dim)',
      unBd: s.fUnassigned ? 'var(--warn)' : 'var(--rule)',
      unassignedToggle: () => this.setState(st => ({ fUnassigned: !st.fUnassigned })),
      facets: [
        mkFacet('fProv', 'Provider', s.fProv, facetOpts(cntBy(x => x.provider), s.fProv, 'fProv')),
        mkFacet('fBackup', 'Backup', s.fBackup, facetOpts(cntBy(x => x.backup), s.fBackup, 'fBackup')),
        mkFacet('fCore', 'Core', s.fCore, facetOpts(cntBy(x => x.core), s.fCore, 'fCore')),
        mkFacet('fTheme', 'Theme', s.fTheme, facetOpts(cntBy(x => x.theme), s.fTheme, 'fTheme')),
        mkFacet('fPlugin', 'Plugin', s.fPlugin, facetOpts(plugCnt, s.fPlugin, 'fPlugin', { fPlugVer: 'Any', fPlugStatus: 'Any' }))
      ],
      opChips: ['AND', 'OR'].map(label => ({ label,
        bg: s.fOp === label ? 'var(--brand-soft)' : 'var(--paper)',
        fg: s.fOp === label ? 'var(--brand-ink)' : 'var(--ink-dim)',
        go: () => this.setState({ fOp: label }) })),
      plugRowShow: !inactive(s.fPlugin),
      plugRowLabel: s.fPlugin,
      facets2: [
        mkFacet('fPlugVer', 'Version', s.fPlugVer, facetOpts(verCnt, s.fPlugVer, 'fPlugVer')),
        mkFacet('fPlugStatus', 'Status', s.fPlugStatus, facetOpts(statCnt, s.fPlugStatus, 'fPlugStatus'))
      ],
      isChips: ['IS', 'IS NOT'].map(label => ({ label,
        bg: s.fPlugIs === label ? 'var(--brand-soft)' : 'var(--paper)',
        fg: s.fPlugIs === label ? 'var(--brand-ink)' : 'var(--ink-dim)',
        go: () => this.setState({ fPlugIs: label }) })),
      clearPlugin: () => this.setState({ fPlugin: 'Any', fPlugVer: 'Any', fPlugStatus: 'Any' }),
      hasLabels: Object.keys(labelCnt).length > 0,
      labelChips: Object.keys(labelCnt).sort().map(label => ({ label, n: labelCnt[label],
        bg: s.labelsSel[label] ? 'var(--warn-soft)' : 'var(--paper)',
        fg: s.labelsSel[label] ? 'var(--ink)' : 'var(--ink-dim)',
        bd: s.labelsSel[label] ? 'var(--warn)' : 'var(--rule)',
        go: () => this.setState(st => ({ labelsSel: { ...st.labelsSel, [label]: !st.labelsSel[label] } })) })),
      hasFilters: !!nq || conds.length > 0 || selLabels.length > 0,
      clearFilters: () => this.setState({ q: '', fProv: 'Any', fUnassigned: false, fBackup: 'Any', fCore: 'Any', fTheme: 'Any', fPlugin: 'Any', fPlugVer: 'Any', fPlugStatus: 'Any', fPlugIs: 'IS', labelsSel: {} }),
      hasSel: selIds.length > 0, selCount: selIds.length,
      clearSel: () => this.setState({ sel: {} }),
      selAllMark: allSel ? '✓' : '', selAllBg: allSel ? 'var(--brand)' : 'var(--paper)',
      toggleAll: () => this.setState({ sel: allSel ? {} : Object.fromEntries(filtered.map(x => [x.id, true])) }),
      bulkActions: ['Sync data', 'Update WP', 'Back up', 'Apply HTTPS', 'Scan errors'].map(label => ({ label,
        go: () => { this.runJob(label.toLowerCase().replace(/ /g, '-'), selIds.length + ' sites'); this.setState({ sel: {}, dockOpen: true }); } })),
      openNewSite: () => this.setState({ nsOpen: true }),
      closeNs: () => this.setState({ nsOpen: false }),
      nsOpen: s.nsOpen,
      nsPaths: [['request', 'Request'], ['kinsta', 'New on Kinsta'], ['import', 'Import from provider'], ['manual', 'Connect manually']].map(([id, label]) => ({ label,
        bg: s.nsPath === id ? 'var(--brand-soft)' : 'var(--paper)', fg: s.nsPath === id ? 'var(--brand-ink)' : 'var(--ink-dim)', bd: s.nsPath === id ? 'var(--brand)' : 'var(--rule)',
        go: () => this.setState({ nsPath: id }) })),
      nsIsRequest: s.nsPath === 'request', nsIsKinsta: s.nsPath === 'kinsta', nsIsImport: s.nsPath === 'import', nsIsManual: s.nsPath === 'manual',
      nsName: s.nsName, onNsName: e => this.setState({ nsName: e.target.value }),
      nsNotes: s.nsNotes, onNsNotes: e => this.setState({ nsNotes: e.target.value }),
      nsAddr: s.nsAddr, onNsAddr: e => this.setState({ nsAddr: e.target.value }),
      nsUser: s.nsUser, onNsUser: e => this.setState({ nsUser: e.target.value }),
      nsPass: s.nsPass, onNsPass: e => this.setState({ nsPass: e.target.value }),
      nsAcc: s.nsAcc,
      ddNsAccOpen: s.ddOpen === 'nsAcc',
      ddToggleNsAcc: () => this.setState(st => ({ ddOpen: st.ddOpen === 'nsAcc' ? '' : 'nsAcc', ddQ: '' })),
      ddNsAccOpts: this.ddOpts(this.ACCOUNTS.map(a => a.name), s.nsAcc, 'nsAcc'),
      nsClone: s.nsClone,
      ddNsCloneOpen: s.ddOpen === 'nsClone',
      ddToggleNsClone: () => this.setState(st => ({ ddOpen: st.ddOpen === 'nsClone' ? '' : 'nsClone', ddQ: '' })),
      ddNsCloneOpts: this.ddOpts(['None (fresh install)', ...this.FLEET.map(f => f.name)], s.nsClone, 'nsClone'),
      nsDcChips: ['US Central (Iowa)', 'US East (S. Carolina)', 'EU West (Belgium)'].map(l => chip(l, s.nsDc, 'nsDc')),
      nsProvChips: ['Kinsta', 'WP Engine', 'Rocket.net', 'GridPane'].map(l => chip(l, s.nsProv, 'nsProv')),
      nsEnvChips: ['Production only', 'Production + Staging'].map(l => chip(l, s.nsEnvs, 'nsEnvs')),
      nsRemote: [['clientsite-alpha.com', '1.2 GB'], ['clientsite-beta.com', '640 MB'], ['legacyshop.net', '3.8 GB']].map(([name, size]) => ({ name, size,
        check: s.nsImportSel[name] ? '✓' : '', checkBg: s.nsImportSel[name] ? 'var(--brand)' : 'var(--paper)',
        toggle: () => this.setState(st => ({ nsImportSel: { ...st.nsImportSel, [name]: !st.nsImportSel[name] } })) })),
      nsBilling: (() => { const n = Object.keys(s.nsImportSel).filter(k => s.nsImportSel[k]).length;
        return n ? 'Billing preview: +$' + n * 45 + '/mo · Standard plan × ' + n : 'Select sites to see a billing preview.'; })(),
      nsCta: { request: 'Request site', kinsta: 'Create on Kinsta',
        import: 'Import ' + Object.keys(s.nsImportSel).filter(k => s.nsImportSel[k]).length + ' sites',
        manual: 'Connect site' }[s.nsPath],
      nsCreate: () => { const st = this.state;
        const sel = Object.keys(st.nsImportSel).filter(k => st.nsImportSel[k]);
        if (st.nsPath === 'request') this.runJob('site-request', (st.nsName || 'new site') + ' · ' + st.nsAcc);
        else if (st.nsPath === 'kinsta') this.runJob('kinsta-new-site', (st.nsName || 'new site') + ' · ' + st.nsDc + (st.nsClone.startsWith('None') ? '' : ' · clone of ' + st.nsClone));
        else if (st.nsPath === 'import') { if (!sel.length) return; this.runJob('provider-import', sel.length + ' sites from ' + st.nsProv); }
        else this.runJob('connect-site', (st.nsName || st.nsAddr || 'new site') + ' · ' + st.nsEnvs);
        this.setState({ nsOpen: false, nsName: '', nsNotes: '', nsImportSel: {}, dockOpen: true }); },
      viewTable: s.view === 'table', viewCards: s.view === 'cards',
      tblBg: s.view === 'table' ? 'var(--brand-soft)' : 'var(--paper)', tblFg: s.view === 'table' ? 'var(--brand-ink)' : 'var(--ink-dim)',
      crdBg: s.view === 'cards' ? 'var(--brand-soft)' : 'var(--paper)', crdFg: s.view === 'cards' ? 'var(--brand-ink)' : 'var(--ink-dim)',
      setViewTable: () => this.setState({ view: 'table' }), setViewCards: () => this.setState({ view: 'cards' }),
      listRows: rows
    };
  }

  parseZone(text) {
    const types = ['A', 'AAAA', 'ANAME', 'CNAME', 'MX', 'TXT', 'SPF', 'SRV', 'HTTP'];
    const recs = [];
    (text || '').split('\n').forEach(line => {
      const l = line.trim();
      if (!l || l.startsWith(';') || l.startsWith('$')) return;
      const toks = l.split(/\s+/);
      let idx = -1;
      for (let i = 1; i < toks.length; i++) { const u = toks[i].toUpperCase(); if (u === 'IN') continue; if (types.includes(u)) { idx = i; break; } }
      if (idx < 0) return;
      const value = toks.slice(idx + 1).join(' ').replace(/^"|"$/g, '');
      if (!value) return;
      const ttlTok = toks.slice(1, idx).find(t => /^\d+$/.test(t));
      recs.push({ type: toks[idx].toUpperCase(), name: toks[0].replace(/\.$/, '') || '@', value, ttl: ttlTok || '3600' });
    });
    return recs;
  }

  ddOpts(list, cur, key) {
    const nq = this.state.ddQ.trim().toLowerCase();
    return (nq ? list.filter(o => o.toLowerCase().includes(nq)) : list).map(label => ({ label,
      mark: label === cur ? '✓' : '',
      bg: label === cur ? 'var(--brand-soft)' : 'transparent',
      pick: () => this.setState({ [key]: label, ddOpen: '', ddQ: '' }) }));
  }

  openDomain(id) {
    this.setState({ route: 'domain', domainId: id, domTab: 'dns', paletteOpen: false,
      dnsRecs: this.DNS_RECS.map(r => ({ ...r })), dnsDirty: false, dnsT: 'A', dnsN: '', dnsV: '',
      fwds: this.FWDS.map(f => ({ ...f })), fwdAlias: '', fwdDest: '',
      reg: { auto: true, lock: true, priv: true } });
  }

  computeDomains(s, isOp) {
    const base = s.domList || this.DOMAINS;
    const list = isOp ? base : base.filter(d => d.owned);
    const nq = s.dq.trim().toLowerCase();
    const filtered = list.filter(d => !nq || d.name.includes(nq) || d.account.toLowerCase().includes(nq));
    return {
      domCount: filtered.length + ' domains',
      dq: s.dq, onDq: e => this.setState({ dq: e.target.value }),
      openNewDomain: () => this.setState({ ndOpen: true }),
      closeNd: () => this.setState({ ndOpen: false }),
      ndOpen: s.ndOpen,
      ndName: s.ndName, onNdName: e => this.setState({ ndName: e.target.value }),
      ndAcc: s.ndAcc,
      ddNdAccOpen: s.ddOpen === 'ndAcc',
      ddToggleNdAcc: () => this.setState(st => ({ ddOpen: st.ddOpen === 'ndAcc' ? '' : 'ndAcc', ddQ: '' })),
      ddNdAccOpts: this.ddOpts(this.ACCOUNTS.map(a => a.name), s.ndAcc, 'ndAcc'),
      ndZoneBg: s.ndZone ? 'var(--brand)' : 'var(--rule)',
      ndZoneJust: s.ndZone ? 'flex-end' : 'flex-start',
      ndZoneLabel: s.ndZone ? 'On — records editable here' : 'Off — DNS stays external',
      ndZoneFlip: () => this.setState(st => ({ ndZone: !st.ndZone })),
      ndCreate: () => { const v = this.state.ndName.trim().toLowerCase(); if (!v) return;
        this.setState(st => ({ domList: [{ id: 'd' + Date.now(), name: v, account: st.ndAcc, registrar: 'Hover', dns: st.ndZone, expires: 'Jul 2027', auto: true, owned: true }, ...(st.domList || this.DOMAINS)], ndOpen: false, ndName: '' }));
        this.runJob('domain-create', v + (this.state.ndZone ? ' + DNS zone' : '')); },
      domRows: filtered.map(d => ({ ...d,
        dnsLabel: d.dns ? 'Active' : '—', dnsFg: d.dns ? 'var(--ok)' : 'var(--ink-dim)',
        expFg: d.warn ? 'var(--bad)' : 'var(--ink)',
        autoLabel: d.auto === null ? '—' : d.auto ? 'On' : 'Off',
        autoFg: d.auto === false ? 'var(--warn)' : 'var(--ink-dim)',
        open: () => this.openDomain(d.id) }))
    };
  }

  computeDomain(s) {
    const domBase = s.domList || this.DOMAINS;
    const d = domBase.find(x => x.id === s.domainId) || domBase[0];
    const tabs = [['dns', 'DNS'], ['registrar', 'Registrar'], ['forwarding', 'Email forwarding'], ['sending', 'Sending']].map(([id, label]) => ({ label,
      fg: s.domTab === id ? 'var(--brand-ink)' : 'var(--ink-dim)',
      line: s.domTab === id ? 'var(--brand)' : 'transparent',
      go: () => this.setState({ domTab: id }) }));
    const typeBg = { A: 'var(--brand-soft)', AAAA: 'var(--brand-soft)', MX: 'var(--warn-soft)', TXT: 'var(--ok-soft)', SPF: 'var(--ok-soft)' };
    const dnsRows = (s.dnsRecs || []).map(r => ({ ...r, bg: typeBg[r.type] || 'var(--panel-2)',
      editing: s.dnsEdit === r.uid, notEditing: s.dnsEdit !== r.uid,
      startEdit: () => this.setState({ dnsEdit: r.uid, dnsEN: r.name, dnsEV: r.value, dnsETtl: r.ttl }),
      del: (e) => { e.stopPropagation(); this.setState(st => ({ dnsRecs: st.dnsRecs.filter(x => x.uid !== r.uid), dnsDirty: true })); } }));
    const zoneRecs = s.zoneOpen ? this.parseZone(s.zoneText) : [];
    const ct = s.contact || { Name: 'Sarah Whitfield', Organization: 'Bloom & Branch LLC', Email: 'sarah@bloomandbranch.com', Phone: '+1 (717) 555-0164', Address: '412 Larkspur Lane', 'City / State': 'Lancaster, PA 17601', Country: 'United States' };
    const mkTog = (key, label) => ({ label,
      bg: s.reg[key] ? 'var(--brand)' : 'var(--rule)',
      just: s.reg[key] ? 'flex-end' : 'flex-start',
      state: s.reg[key] ? 'On' : 'Off',
      flip: () => this.setState(st => ({ reg: { ...st.reg, [key]: !st.reg[key] } })) });
    const fwdRows = (s.fwds || []).map(f => ({ ...f, aliasFull: (f.alias === '*' ? 'anything' : f.alias) + '@' + d.name,
      stFg: f.status === 'Verified' ? 'var(--ok)' : f.status === 'Catch-all' ? 'var(--ink-dim)' : 'var(--warn)',
      del: () => this.setState(st => ({ fwds: st.fwds.filter(x => x.uid !== f.uid) })) }));
    const mgRecs = this.MG_RECS.map(r => ({ ...r, host: r.host + '.' + d.name,
      stLabel: r.ok ? 'Verified' : 'Pending', stFg: r.ok ? 'var(--ok)' : 'var(--warn)', pending: !r.ok,
      verify: () => this.runJob('mailgun-verify', r.host + '.' + d.name) }));
    return {
      domName: d.name,
      domStatus: (d.dns ? 'DNS active · ' : 'DNS inactive · ') + 'Registered via ' + d.registrar + (d.expires !== '—' ? ' · expires ' + d.expires : ''),
      domBack: () => this.setState({ route: 'domains' }),
      domTabs: tabs,
      domTabDns: s.domTab === 'dns', domTabReg: s.domTab === 'registrar', domTabFwd: s.domTab === 'forwarding', domTabSnd: s.domTab === 'sending',
      dnsRows, dnsDirty: s.dnsDirty, dnsT: s.dnsT, dnsN: s.dnsN, dnsV: s.dnsV,
      dnsEN: s.dnsEN, onDnsEN: e => this.setState({ dnsEN: e.target.value }),
      dnsEV: s.dnsEV, onDnsEV: e => this.setState({ dnsEV: e.target.value }),
      dnsETtl: s.dnsETtl, onDnsETtl: e => this.setState({ dnsETtl: e.target.value }),
      dnsEditDone: () => this.setState(st => ({ dnsRecs: st.dnsRecs.map(x => x.uid === st.dnsEdit ? { ...x, name: st.dnsEN.trim() || '@', value: st.dnsEV.trim() || x.value, ttl: st.dnsETtl.trim() || '3600' } : x), dnsEdit: 0, dnsDirty: true })),
      dnsEditCancel: () => this.setState({ dnsEdit: 0 }),
      openZoneDlg: () => this.setState({ zoneOpen: true, zoneText: '' }),
      closeZone: () => this.setState({ zoneOpen: false }),
      zoneOpen: s.zoneOpen,
      zoneText: s.zoneText, onZoneText: e => this.setState({ zoneText: e.target.value }),
      hasZoneRecs: zoneRecs.length > 0, zoneEmpty: zoneRecs.length === 0,
      zoneCount: zoneRecs.length + ' records parsed',
      zonePreview: zoneRecs.map(r => ({ ...r, bg: typeBg[r.type] || 'var(--panel-2)' })),
      zoneAppend: () => this.setState(st => ({ dnsRecs: [...(st.dnsRecs || []), ...this.parseZone(st.zoneText).map((r, i) => ({ ...r, uid: Date.now() + i }))], dnsDirty: true, zoneOpen: false, zoneText: '' })),
      zoneReplace: () => this.setState(st => ({ dnsRecs: this.parseZone(st.zoneText).map((r, i) => ({ ...r, uid: Date.now() + i })), dnsDirty: true, zoneOpen: false, zoneText: '' })),
      openNsvDlg: () => this.setState({ nsvOpen: true, nsvText: (this.state.nsCustom || ['ns11.constellix.com', 'ns21.constellix.com', 'ns31.constellix.com']).join('\n') }),
      closeNsv: () => this.setState({ nsvOpen: false }),
      nsvOpen: s.nsvOpen,
      nsvText: s.nsvText, onNsvText: e => this.setState({ nsvText: e.target.value }),
      saveNsv: () => { const lines = this.state.nsvText.split('\n').map(l => l.trim()).filter(Boolean);
        if (!lines.length) return;
        this.setState({ nsCustom: lines, nsvOpen: false });
        this.runJob('nameservers-update', d.name + ' → ' + lines.length + ' nameservers'); },
      openCtDlg: () => this.setState({ ctOpen: true, ctDraft: { ...ct } }),
      closeCt: () => this.setState({ ctOpen: false }),
      ctOpen: s.ctOpen,
      ctFields: Object.keys(ct).map(label => ({ label, v: (s.ctDraft || {})[label] ?? '',
        on: e => this.setState(st => ({ ctDraft: { ...st.ctDraft, [label]: e.target.value } })) })),
      saveCt: () => { this.setState(st => ({ contact: { ...st.ctDraft }, ctOpen: false }));
        this.runJob('contacts-update', d.name + ' · all 4 roles'); },
      ctLine1: ct.Name + ' · ' + ct.Organization,
      ctLine2: ct.Address + ', ' + ct['City / State'] + ' · ' + ct.Country,
      ctLine3: ct.Email + ' · ' + ct.Phone,
      ddDnsOpen: s.ddOpen === 'dns',
      ddToggleDns: () => this.setState(st => ({ ddOpen: st.ddOpen === 'dns' ? '' : 'dns', ddQ: '' })),
      ddDnsOpts: this.ddOpts(['A', 'AAAA', 'ANAME', 'CNAME', 'MX', 'TXT', 'SPF', 'SRV', 'HTTP'], s.dnsT, 'dnsT'),
      onDnsN: e => this.setState({ dnsN: e.target.value }),
      onDnsV: e => this.setState({ dnsV: e.target.value }),
      addRec: () => { if (!this.state.dnsV.trim()) return;
        this.setState(st => ({ dnsRecs: [...st.dnsRecs, { uid: Date.now(), type: st.dnsT, name: st.dnsN.trim() || '@', value: st.dnsV.trim(), ttl: '3600' }], dnsDirty: true, dnsN: '', dnsV: '' })); },
      saveDns: () => { this.runJob('dns-bulk-save', d.name); this.setState({ dnsDirty: false }); },
      discardDns: () => this.setState({ dnsRecs: this.DNS_RECS.map(r => ({ ...r })), dnsDirty: false }),
      importZone: () => this.runJob('dns-import', 'zone file → ' + d.name),
      exportZone: () => this.runJob('dns-export', d.name + ' → BIND'),
      regRegistrar: d.registrar,
      regExpires: d.expires + (d.warn ? ' · in 12 days' : ''),
      regExpFg: d.warn ? 'var(--bad)' : 'var(--ink)',
      regWarn: !!d.warn,
      renewNow: () => this.runJob('renew-domain', d.name),
      togAuto: mkTog('auto', 'Auto-renew'), togLock: mkTog('lock', 'Transfer lock'), togPriv: mkTog('priv', 'WHOIS privacy'),
      authMark: s.copied === 'auth' ? 'Copied ✓' : 'Copy',
      authCopy: () => { try { navigator.clipboard.writeText('XK7-99Q2-RRB1'); } catch (e) {}
        this.setState({ copied: 'auth' }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
      nsList: (s.nsCustom || ['ns11.constellix.com', 'ns21.constellix.com', 'ns31.constellix.com']).map(n => ({ n })),
      fwdRows, fwdAlias: s.fwdAlias, fwdDest: s.fwdDest,
      onFwdAlias: e => this.setState({ fwdAlias: e.target.value }),
      onFwdDest: e => this.setState({ fwdDest: e.target.value }),
      addFwd: () => { const a = this.state.fwdAlias.trim(), t = this.state.fwdDest.trim(); if (!a || !t) return;
        this.setState(st => ({ fwds: [...st.fwds, { uid: Date.now(), alias: a.replace(/@.*$/, ''), dest: t, status: 'Pending verification' }], fwdAlias: '', fwdDest: '' }));
        this.runJob('verify-forward', a.replace(/@.*$/, '') + '@' + d.name); },
      mgHost: 'mg.' + d.name,
      mgRecs, mgEvents: this.MG_EVENTS,
      mgSupp: '2 bounces · 0 unsubscribes · 0 complaints',
      mgDeploy: () => this.runJob('deploy-mailgun', d.name + ' → SMTP on connected site')
    };
  }

  computeDetail(s) {
    const site = this.FLEET.find(x => x.id === s.siteId) || this.FLEET[0];
    const slug = site.name.split('.')[0];
    const host = s.env === 'Staging' ? 'staging-' + site.name : site.name;
    const segBg = l => s.env === l ? 'var(--brand-soft)' : 'var(--paper)';
    const segFg = l => s.env === l ? 'var(--brand-ink)' : 'var(--ink-dim)';
    const mkCopy = ([k, v]) => ({ k, v, mark: s.copied === k ? 'Copied ✓' : 'Copy',
      copy: () => { try { navigator.clipboard.writeText(v); } catch (e) {}
        this.setState({ copied: k }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); } });
    const credRows = [
      ['Site URL', 'https://' + host], ['WP admin', 'https://' + host + '/wp-admin'],
      ['SFTP', slug + '@sftp.kinsta.com:22'], ['SFTP password', 'uY3!kW8#pQ2v'],
      ['Database', 'wp_' + slug.replace(/-/g, '_')], ['DB password', 'mR7$xT4@nL9c'],
      ['SSH', 'ssh ' + slug + '@35.223.94.108']
    ].map(mkCopy);
    const tabs = [['overview', 'Overview'], ['stats', 'Stats'], ['addons', 'Addons'], ['versions', 'Versions'], ['backups', 'Backups'], ['snapshots', 'Snapshots'], ['users', 'Users'], ['logs', 'Logs'], ['timeline', 'Timeline']]
      .map(([id, label]) => ({ label,
        fg: s.siteTab === id ? 'var(--brand-ink)' : 'var(--ink-dim)',
        line: s.siteTab === id ? 'var(--brand)' : 'transparent',
        go: () => this.setState({ siteTab: id }) }));
    const addonsSrc = s.addonKind === 'plugins' ? this.PLUGINS : this.THEMES;
    const addons = addonsSrc.map(a => { const upd = a.v !== a.latest; return { ...a, upd,
      vulnB: !!(site.vuln && a.slug === 'gravityforms'),
      dot: a.active ? 'var(--ok)' : 'var(--rule)',
      statusLabel: a.active ? 'Active' : 'Inactive',
      toggleLabel: a.active ? 'Deactivate' : 'Activate',
      doToggle: () => this.runJob(a.active ? 'deactivate' : 'activate', a.slug + ' on ' + site.name),
      doUpdate: () => this.runJob('update', a.slug + ' ' + a.v + ' → ' + a.latest + ' on ' + site.name) }; });
    const updCount = this.PLUGINS.concat(this.THEMES).filter(a => a.v !== a.latest).length;
    const qsFiles = this.QS_FILES;
    const curPath = qsFiles.some(f => f.path === s.qsFile) ? s.qsFile : qsFiles[0].path;
    const curFile = qsFiles.find(f => f.path === curPath);
    const mkLine = ([kind, text]) => ({ text,
      fg: kind === 'add' ? 'var(--ok)' : kind === 'del' ? 'var(--bad)' : 'var(--ink-dim)',
      bg: kind === 'add' ? 'var(--ok-soft)' : kind === 'del' ? 'var(--bad-soft)' : 'transparent' });
    const splitRows = curFile.diff.map(([kind, text]) => kind === 'del'
      ? { l: text, r: '', lbg: 'var(--bad-soft)', rbg: 'transparent', lfg: 'var(--bad)', rfg: 'var(--ink-dim)' }
      : kind === 'add' ? { l: '', r: text, lbg: 'transparent', rbg: 'var(--ok-soft)', lfg: 'var(--ink-dim)', rfg: 'var(--ok)' }
      : { l: text, r: text, lbg: 'transparent', rbg: 'transparent', lfg: 'var(--ink-dim)', rfg: 'var(--ink-dim)' });
    const quicksaves = this.QUICKSAVES.map(qk => ({ ...qk,
      kindBg: qk.kind === 'Update' ? 'var(--warn-soft)' : qk.kind === 'Manual' ? 'var(--brand-soft)' : 'var(--panel-2)',
      openD: () => this.setState({ qsDialog: qk.hash, qsView: 'components', qsFile: '' }),
      doRollback: () => this.runJob('rollback', site.name + ' → ' + qk.hash) }));
    const dlgQk = this.QUICKSAVES.find(q => q.hash === s.qsDialog);
    const stMap = { A: ['var(--ok-soft)', 'var(--ok)'], M: ['var(--warn-soft)', 'var(--ink)'], D: ['var(--bad-soft)', 'var(--bad)'] };
    const dlgFiles = qsFiles.map(f => ({ ...f, addN: '+' + f.add, delN: '−' + f.del,
      stBg: stMap[f.st][0], stFg: stMap[f.st][1],
      pick: () => this.setState({ qsFile: f.path, qsView: 'diff' }) }));
    const mkComp = c => ({ ...c,
      rowBg: c.deleted ? 'var(--bad-soft)' : c.added ? 'var(--ok-soft)' : 'transparent',
      deco: c.deleted ? 'line-through' : 'none',
      nameFg: c.deleted ? 'var(--bad)' : 'var(--ink)',
      verCell: c.deleted ? c.from : c.added ? c.to : c.updated ? c.from + ' → ' + c.to : (c.from || '—'),
      verFg: c.updated ? 'var(--ink)' : 'var(--ink-dim)',
      badge: c.added ? 'New' : c.deleted ? 'Deleted' : c.updated ? 'Updated' : '',
      badgeBg: c.added ? 'var(--ok-soft)' : c.deleted ? 'var(--bad-soft)' : 'var(--warn-soft)',
      hasBadge: !!(c.added || c.deleted || c.updated),
      canView: !!c.viewFile,
      viewChanges: () => this.setState({ qsFile: c.viewFile, qsView: 'diff' }),
      rollback: () => this.setState({ rbComp: c.name }) });
    const dlgIdx = this.QUICKSAVES.findIndex(q => q.hash === s.qsDialog);
    const prevQk = dlgIdx >= 0 ? this.QUICKSAVES[dlgIdx + 1] : null;
    const backups = this.BACKUPS.map(b => ({ ...b,
      openB: () => this.setState({ bkDialog: b.id, bkSel: {}, bkPreview: '' }),
      doRestore: () => this.runJob('restore', b.id + ' on ' + site.name) }));
    const bkDlg = this.BACKUPS.find(b => b.id === s.bkDialog);
    const flatAll = [];
    const flattenAll = nodes => nodes.forEach(n => { flatAll.push(n); if (n.children) flattenAll(n.children); });
    flattenAll(this.BK_TREE);
    const bkRows = [];
    const walk = (nodes, depth) => nodes.forEach(n => {
      const open = !!s.bkDirs[n.p];
      bkRows.push({ key: n.p,
        name: n.dir ? n.p.replace(/\/$/, '').split('/').pop() + '/' : n.p.split('/').pop(),
        meta: n.meta,
        pad: (14 + depth * 20) + 'px',
        arrow: n.dir && !n.omitted ? (open ? '▾' : '▸') : '',
        fg: n.omitted ? 'var(--ink-dim)' : 'var(--ink)',
        check: s.bkSel[n.p] ? '✓' : '', checkBg: s.bkSel[n.p] ? 'var(--brand)' : 'var(--paper)',
        toggleSel: (e) => { e.stopPropagation(); if (n.omitted) return;
          const val = !this.state.bkSel[n.p];
          const upd = { [n.p]: val };
          if (n.dir) flatAll.filter(x => x.p !== n.p && x.p.startsWith(n.p)).forEach(x => { upd[x.p] = val; });
          this.setState(st => ({ bkSel: { ...st.bkSel, ...upd } })); },
        rowClick: () => { if (n.dir && !n.omitted) this.setState(st => ({ bkDirs: { ...st.bkDirs, [n.p]: !st.bkDirs[n.p] } }));
          else if (n.prev) this.setState({ bkPreview: n.p }); } });
      if (n.dir && open && n.children) walk(n.children, depth + 1);
    });
    walk(this.BK_TREE, 0);
    const selKeys = Object.keys(s.bkSel).filter(k => s.bkSel[k]);
    const topSel = selKeys.filter(k => !selKeys.some(o => o !== k && o.endsWith('/') && k.startsWith(o)));
    let selCnt = 0, selKb = 0;
    topSel.forEach(p => { const n = flatAll.find(x => x.p === p); if (n) { selCnt += n.cnt || 1; selKb += n.kb || 0; } });
    const fmtKb = kb => kb < 1024 ? Math.round(kb) + ' kB' : kb < 1048576 ? (kb / 1024).toFixed(1) + ' MB' : (kb / 1048576).toFixed(1) + ' GB';
    const dUsers = this.WP_USERS.map(u => ({ ...u, e: u.e.replace('SITE', site.name),
      init: u.n.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
      magic: () => this.runJob('magiclogin', u.e.replace('SITE', site.name)) }));
    const logChips = ['error.log', 'access.log', 'debug.log'].map(f => ({ label: f,
      bg: s.logFile === f ? 'var(--brand-soft)' : 'var(--paper)',
      fg: s.logFile === f ? 'var(--brand-ink)' : 'var(--ink-dim)',
      bd: s.logFile === f ? 'var(--brand)' : 'var(--rule)',
      go: () => this.setState({ logFile: f }) }));
    const logLines = (this.LOGS[s.logFile] || []).map(text => ({ text }));
    return {
      dName: site.name,
      dMeta: site.provider + ' · ' + site.account + ' · WP ' + site.core + ' · ' + site.visits + ' visits/wk · ' + site.storage + ' · ' + s.env,
      pBg: segBg('Production'), pFg: segFg('Production'), sBg: segBg('Staging'), sFg: segFg('Staging'),
      setEnvProd: () => this.setState({ env: 'Production' }), setEnvStag: () => this.setState({ env: 'Staging' }),
      backToSites: () => this.setState({ route: 'sites' }),
      dSync: () => this.runJob('sync-data', site.name),
      dTerm: () => this.setState({ dockOpen: true }),
      pushEnv: () => this.runJob('deploy', 'staging → production on ' + site.name),
      pullEnv: () => this.runJob('deploy', 'production → staging on ' + site.name),
      dTabs: tabs,
      tabOverview: s.siteTab === 'overview', tabStats: s.siteTab === 'stats', tabAddons: s.siteTab === 'addons', tabVersions: s.siteTab === 'versions',
      tabBackups: s.siteTab === 'backups', tabSnapshots: s.siteTab === 'snapshots', tabUsers: s.siteTab === 'users', tabLogs: s.siteTab === 'logs', tabTimeline: s.siteTab === 'timeline',
      credRows,
      statTiles: [
        { k: 'Visits / wk', v: site.visits, delta: '+8%', deltaFg: 'var(--ok)', act: 'stats' },
        { k: 'Backups', v: '1,284', delta: 'nightly + PITR', deltaFg: 'var(--ink-dim)', act: 'backups' },
        { k: 'Versions', v: '412', delta: 'quicksaves + updates', deltaFg: 'var(--ink-dim)', act: 'versions' },
        { k: 'Timeline', v: '86', delta: 'last note 2h ago', deltaFg: 'var(--ink-dim)', act: 'timeline' }
      ].map(t => ({ ...t, tip: 'Open ' + t.act, go: () => this.setState({ siteTab: t.act }) })),
      openStats: () => this.setState({ siteTab: 'stats' }),
      statG: s.statG, statR: s.statR,
      ddStatGOpen: s.ddOpen === 'statG',
      ddToggleStatG: () => this.setState(st => ({ ddOpen: st.ddOpen === 'statG' ? '' : 'statG', ddQ: '' })),
      ddStatGOpts: this.ddOpts(['Daily', 'Weekly', 'Monthly'], s.statG, 'statG'),
      ddStatROpen: s.ddOpen === 'statR',
      ddToggleStatR: () => this.setState(st => ({ ddOpen: st.ddOpen === 'statR' ? '' : 'statR', ddQ: '' })),
      ddStatROpts: this.ddOpts(['Last 7 days', 'Last 28 days', 'Last 90 days', 'This year'], s.statR, 'statR'),
      shareChips: ['Off', 'Private', 'Public'].map(label => ({ label,
        bg: s.statShare === label ? 'var(--brand-soft)' : 'var(--paper)',
        fg: s.statShare === label ? 'var(--brand-ink)' : 'var(--ink-dim)',
        bd: s.statShare === label ? 'var(--brand)' : 'var(--rule)',
        go: () => this.setState({ statShare: label }) })),
      statPrivate: s.statShare === 'Private', statShared: s.statShare !== 'Off',
      statPw: s.statPw, onStatPw: e => this.setState({ statPw: e.target.value }),
      statLinkMark: s.copied === 'statlink' ? 'Copied ✓' : 'Copy share link',
      copyStatLink: () => { try { navigator.clipboard.writeText('https://' + site.name + '/stats?share=…'); } catch (e) {}
        this.setState({ copied: 'statlink' }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
      statTilesBig: [
        { k: 'Visitors', v: '9,120', delta: '+6%', deltaFg: 'var(--ok)' },
        { k: 'Pageviews', v: '24,388', delta: '+8%', deltaFg: 'var(--ok)' },
        { k: 'Avg time on site', v: '1m 42s', delta: '', deltaFg: 'var(--ink-dim)' },
        { k: 'Bounce rate', v: '38%', delta: '−3%', deltaFg: 'var(--ok)' }
      ],
      statChartLabel: s.statG.toLowerCase() + ' · ' + s.statR.toLowerCase() + ' · Fathom',
      statBars: (() => { const n = s.statG === 'Daily' ? 28 : s.statG === 'Weekly' ? 12 : 6;
        return Array.from({ length: n }, (_, i) => { const h = 28 + ((i * 37 + 11) % 58);
          return { h, tip: Math.round(h * 14) + ' views', bg: i === n - 1 ? 'var(--brand)' : 'color-mix(in srgb, var(--brand) 38%, transparent)' }; }); })(),
      topPages: [['/', '4,812'], ['/shop/', '2,391'], ['/about/', '1,204'], ['/blog/summer-arrangements/', '986'], ['/contact/', '743']].map(([k, v]) => ({ k, v })),
      topRefs: [['direct', '4,201'], ['google.com', '3,104'], ['instagram.com', '1,822'], ['facebook.com', '640'], ['pinterest.com', '512']].map(([k, v]) => ({ k, v })),
      perfRows: [['TTFB (p75)', '142 ms'], ['Largest Contentful Paint (p75)', '1.8 s'], ['Checks · last 24h', '288 · all passing']].map(([k, v]) => ({ k, v })),
      visitBars: [35, 42, 38, 55, 48, 60, 52, 45, 66, 58, 72, 64, 80, 74].map((h, i) => ({ h,
        bg: i === 13 ? 'var(--brand)' : 'color-mix(in srgb, var(--brand) 38%, transparent)' })),
      envRows: [['WordPress', site.core], ['PHP', '8.3.8'], ['Storage', site.storage], ['Visits / wk', site.visits], ['Uptime monitor', 'On · 99.98%'], ['Managed updates', site.updates ? site.updates + ' pending' : 'Up to date']].map(([k, v]) => ({ k, v })),
      dDomains: [site.name, 'www.' + site.name].map(name => ({ name })),
      sharedRows: (s.shared || this.SHARED_INIT).map(sh => ({ ...sh,
        sub: sh.pending ? 'invite sent — pending' : sh.people + (sh.people === 1 ? ' person' : ' people'),
        lvlBg: sh.owner ? 'var(--brand-soft)' : 'var(--panel-2)',
        lvlFg: sh.owner ? 'var(--brand-ink)' : 'var(--ink-dim)',
        removable: !sh.owner,
        open: () => { if (sh.accId) this.openAccount(sh.accId); },
        remove: () => this.setState(st => ({ shared: (st.shared || this.SHARED_INIT).filter(x => x.uid !== sh.uid) })) })),
      shareDraft: s.shareDraft, onShareDraft: e => this.setState({ shareDraft: e.target.value }),
      doShare: () => { const v = this.state.shareDraft.trim(); if (!v) return;
        this.setState(st => ({ shared: [...(st.shared || this.SHARED_INIT), { uid: Date.now(), name: v, people: 0, level: 'Sites only', pending: true }], shareDraft: '' }));
        this.runJob('grant-access', v + ' → ' + site.name); },
      akpBg: s.addonKind === 'plugins' ? 'var(--brand-soft)' : 'var(--paper)', akpFg: s.addonKind === 'plugins' ? 'var(--brand-ink)' : 'var(--ink-dim)',
      aktBg: s.addonKind === 'themes' ? 'var(--brand-soft)' : 'var(--paper)', aktFg: s.addonKind === 'themes' ? 'var(--brand-ink)' : 'var(--ink-dim)',
      setAddP: () => this.setState({ addonKind: 'plugins' }), setAddT: () => this.setState({ addonKind: 'themes' }),
      addons, hasUpdates: updCount > 0, updateAllLabel: 'Update all (' + updCount + ')',
      doUpdateAll: () => this.runJob('update-wp', site.name + ' · ' + updCount + ' components'),
      quicksaves, newQuicksave: () => this.runJob('quicksave', site.name),
      qsDialogOpen: !!dlgQk,
      dlgHash: dlgQk ? dlgQk.hash : '', dlgDesc: dlgQk ? dlgQk.desc : '', dlgWhen: dlgQk ? dlgQk.when : '',
      dlgSummary: dlgQk ? dlgQk.summary : '',
      dlgMoreFiles: dlgQk && dlgQk.more > 0 ? '… ' + dlgQk.more + ' more files — search or narrow by component to see the rest.' : '',
      closeQsDlg: () => this.setState({ qsDialog: '' }),
      dlgIsComp: s.qsView === 'components', dlgIsFiles: s.qsView === 'files', dlgIsDiff: s.qsView === 'diff',
      dlgNotDiff: s.qsView !== 'diff',
      dlgCompFg: s.qsView === 'components' ? 'var(--brand-ink)' : 'var(--ink-dim)',
      dlgCompLine: s.qsView === 'components' ? 'var(--brand)' : 'transparent',
      dlgFilesFg: s.qsView === 'files' ? 'var(--brand-ink)' : 'var(--ink-dim)',
      dlgFilesLine: s.qsView === 'files' ? 'var(--brand)' : 'transparent',
      setDlgComp: () => this.setState({ qsView: 'components' }),
      setDlgFiles: () => this.setState({ qsView: 'files' }),
      dlgThemes: this.QS_COMPONENTS.filter(c => c.kind === 'theme').map(mkComp),
      dlgPlugins: this.QS_COMPONENTS.filter(c => c.kind === 'plugin').map(mkComp),
      dlgFiles, dlgFilePath: curPath,
      dlgDiff: curFile.diff.map(mkLine), dlgSplit: splitRows,
      backToFiles: () => this.setState({ qsView: 'files' }),
      dlgSandbox: () => this.runJob('sandbox', 'Playground preview of ' + (dlgQk ? dlgQk.hash : '')),
      dlgRollback: () => this.runJob('rollback', site.name + ' → ' + (dlgQk ? dlgQk.hash : '')),
      dlgRestoreFile: () => this.runJob('restore-file', curPath.split('/').pop() + ' from ' + (dlgQk ? dlgQk.hash : '')),
      rbOpen: !!s.rbComp,
      rbTitle: 'Roll back ' + s.rbComp + '?',
      rbThisSub: dlgQk ? dlgQk.when + ' · ' + dlgQk.hash : '',
      rbPrevSub: prevQk ? prevQk.when + ' · ' + prevQk.hash : 'No earlier quicksave',
      closeRb: () => this.setState({ rbComp: '' }),
      rbPickThis: () => { this.runJob('rollback-component', s.rbComp + ' → version in ' + (dlgQk ? dlgQk.hash : '')); this.setState({ rbComp: '' }); },
      rbPickPrev: () => { if (!prevQk) return; this.runJob('rollback-component', s.rbComp + ' → version in ' + prevQk.hash); this.setState({ rbComp: '' }); },
      qsUnified: s.diffMode === 'unified', qsSplit: s.diffMode === 'split',
      uniBg: s.diffMode === 'unified' ? 'var(--brand-soft)' : 'var(--paper)', uniFg: s.diffMode === 'unified' ? 'var(--brand-ink)' : 'var(--ink-dim)',
      splBg: s.diffMode === 'split' ? 'var(--brand-soft)' : 'var(--paper)', splFg: s.diffMode === 'split' ? 'var(--brand-ink)' : 'var(--ink-dim)',
      setUni: () => this.setState({ diffMode: 'unified' }), setSplit: () => this.setState({ diffMode: 'split' }),
      backups, backupNow: () => this.runJob('backup', site.name),
      bkRows,
      bkDlgOpen: !!bkDlg, bkDlgId: bkDlg ? bkDlg.id : '', bkDlgWhen: bkDlg ? bkDlg.when : '',
      bkDlgMeta: bkDlg ? bkDlg.size + ' · ' + bkDlg.files : '',
      closeBkDlg: () => this.setState({ bkDialog: '', bkPreview: '', bkSel: {} }),
      bkDlgRestore: () => this.runJob('restore', (bkDlg ? bkDlg.id : '') + ' on ' + site.name),
      bkHasSel: selCnt > 0,
      bkSelTitle: selCnt.toLocaleString() + ' items selected',
      bkSelSize: fmtKb(selKb),
      bkDownload: () => { this.runJob('backup-download-notify', selCnt.toLocaleString() + ' items (' + fmtKb(selKb) + ') → austin@anchor.host'); this.setState({ bkSel: {} }); },
      cancelSel: () => this.setState({ bkSel: {} }),
      bkShowPrev: !!s.bkPreview && selCnt === 0,
      bkShowPlaceholder: selCnt === 0 && !s.bkPreview,
      selectAll: () => { const upd = {}; flatAll.forEach(n => { if (!n.omitted) upd[n.p] = true; }); this.setState({ bkSel: upd }); },
      bkPrevPath: s.bkPreview,
      bkPrevLines: (this.PREVIEWS[s.bkPreview] || this.PREVIEWS.default).map(text => ({ text })),
      closePrev: () => this.setState({ bkPreview: '' }),
      snapFilter: s.snapFilter,
      ddSnapOpen: s.ddOpen === 'snap',
      ddToggleSnap: () => this.setState(st => ({ ddOpen: st.ddOpen === 'snap' ? '' : 'snap', ddQ: '' })),
      ddSnapOpts: this.ddOpts(['Everything', 'Database', 'Themes', 'Plugins', 'Uploads'], s.snapFilter, 'snapFilter'),
      createSnap: () => this.runJob('snapshot', this.state.snapFilter + ' · ' + site.name),
      snapshots: this.SNAPSHOTS.map(sn => { const expired = sn.expires === 'expired'; return { ...sn, expired, live: !expired,
        expLabel: expired ? 'Link expired' : 'Link expires in ' + sn.expires,
        expFg: expired ? 'var(--ink-dim)' : 'var(--ok)',
        mark: s.copied === sn.id ? 'Copied ✓' : 'Copy link',
        copyLink: () => { try { navigator.clipboard.writeText('https://' + site.name + '/snapshot/' + sn.id); } catch (e) {}
          this.setState({ copied: sn.id }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        regen: () => this.runJob('snapshot-link', 'new 24h link · ' + sn.name),
        doDl: () => this.runJob('snapshot-download', sn.name) }; }),
      dUsers, logChips, logLines,
      logMeta: logLines.length + ' lines · last 24h',
      tlRows: (s.timeline || this.TIMELINE_INIT).map(t => ({ ...t,
        init: t.who.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
        editing: s.tlEdit === t.uid, notEditing: s.tlEdit !== t.uid,
        startEdit: () => this.setState({ tlEdit: t.uid, tlEditText: t.text }),
        doneEdit: () => this.setState(st => ({ timeline: (st.timeline || this.TIMELINE_INIT).map(x => x.uid === st.tlEdit ? { ...x, text: st.tlEditText.trim() || x.text } : x), tlEdit: 0 })),
        cancelEdit: () => this.setState({ tlEdit: 0 }),
        del: () => this.setState(st => ({ timeline: (st.timeline || this.TIMELINE_INIT).filter(x => x.uid !== t.uid) })) })),
      tlDraft: s.tlDraft, onTlDraft: e => this.setState({ tlDraft: e.target.value }),
      tlEditText: s.tlEditText, onTlEditText: e => this.setState({ tlEditText: e.target.value }),
      addTl: () => { const v = this.state.tlDraft.trim(); if (!v) return;
        this.setState(st => ({ timeline: [{ uid: Date.now(), text: v, who: 'Austin Ginder', when: 'just now' }, ...(st.timeline || this.TIMELINE_INIT)], tlDraft: '' })); },
      exportTl: () => this.runJob('timeline-export', site.name + ' → JSON')
    };
  }

  ICONS = {
    home: 'M3 10.5 12 3l9 7.5V20a1 1 0 01-1 1h-5v-6h-6v6H4a1 1 0 01-1-1z',
    sites: 'M3 12l9 5 9-5M3 7l9 5 9-5-9-5-9 5z',
    domains: 'M12 3a9 9 0 100 18 9 9 0 000-18zM3 12h18M12 3c2.7 2.7 2.7 15.3 0 18-2.7-2.7-2.7-15.3 0-18z',
    accounts: 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M15 3.13a4 4 0 010 7.75',
    billing: 'M2 6.5A1.5 1.5 0 013.5 5h17A1.5 1.5 0 0122 6.5v11a1.5 1.5 0 01-1.5 1.5h-17A1.5 1.5 0 012 17.5zM2 10h20M6 14.5h4',
    security: 'M12 3l8 3v6c0 4.6-3.2 7.9-8 9-4.8-1.1-8-4.4-8-9V6l8-3z',
    audits: 'M9 3h6v4H9zM6 5H5v16h14V5h-1M9 13.5l2 2 4.5-4.5',
    reports: 'M4 20h16M7 16v-5M12 16V7M17 16v-9',
    archives: 'M3 7h18v4H3zM5 11v9a1 1 0 001 1h12a1 1 0 001-1v-9M10 15h4',
    settings: 'M4 7h9M17 7h3M13 4v6M4 17h3M11 17h9M7 14v6',
    terminal: 'M4 5h16v14H4zM7.5 9l3 3-3 3M12.5 15H17',
    search: 'M11 4a7 7 0 100 14 7 7 0 000-14zm10 17-5.2-5.2',
    support: 'M12 3a9 9 0 100 18 9 9 0 000-18zm0 14v.01M9.5 9a2.5 2.5 0 115 0c0 1.5-2.5 2-2.5 3.5',
    site: 'M4 5h16v14H4zM4 9h16M7 7h.01',
    quicksave: 'M12 8v4l3 3M12 3a9 9 0 100 18 9 9 0 000-18z'
  };

  CONSOLE_SCRIPT = [
    ['dim', '$ captaincore update-wp @updates-pending --parallel=3'],
    ['ink', '[bloomandbranch.com] Creating quicksave before update…'],
    ['ok',  '[bloomandbranch.com] ✓ Quicksave 8f3c21a'],
    ['ink', '[bloomandbranch.com] Updating gravityforms 2.9.1 → 2.9.4'],
    ['ok',  '[bloomandbranch.com] ✓ gravityforms updated'],
    ['ink', '[harborlightyoga.com] Updating woocommerce 9.8.2 → 9.9.0'],
    ['ok',  '[harborlightyoga.com] ✓ woocommerce updated'],
    ['ink', '[petersonlaw.com] Updating theme kadence 1.2.14 → 1.2.15'],
    ['ok',  '[petersonlaw.com] ✓ kadence updated'],
    ['ink', '[petersonlaw.com] Verifying checksums…'],
    ['ok',  '[petersonlaw.com] ✓ Checksums clean · 0 modified files']
  ];

  componentDidMount() {
    const saved = localStorage.getItem('captaincore-theme');
    const theme = saved || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    this.setState({ theme });
    this.applyTheme(theme);
    this.applyBrand();
    this.onKey = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); this.setState(s => ({ paletteOpen: !s.paletteOpen, palQuery: '', palIdx: 0 })); }
      else if (e.ctrlKey && e.key === '`') { e.preventDefault(); this.setState(s => ({ dockOpen: !s.dockOpen })); }
      else if (e.key === 'Escape') { if (this.state.rbComp) this.setState({ rbComp: '' }); else this.setState({ paletteOpen: false, qsDialog: '', bkDialog: '', nsOpen: false, ndOpen: false, zoneOpen: false, nsvOpen: false, ctOpen: false }); }
      else if (this.state.paletteOpen && e.key === 'ArrowDown') { e.preventDefault(); this.setState(s => ({ palIdx: Math.min(s.palIdx + 1, this.filteredPal(s.palQuery).length - 1) })); }
      else if (this.state.paletteOpen && e.key === 'ArrowUp') { e.preventDefault(); this.setState(s => ({ palIdx: Math.max(s.palIdx - 1, 0) })); }
      else if (this.state.paletteOpen && e.key === 'Enter') { const r = this.filteredPal(this.state.palQuery)[this.state.palIdx]; if (r) this.runPal(r); }
    };
    window.addEventListener('keydown', this.onKey);
    this.hydrate();
    this.timer = setInterval(() => this.setState(s => ({ tick: s.tick + 1,
      jobs: s.jobs.map(j => j.state === 'running'
        ? (j.pct >= 100 ? { ...j, state: 'done', right: 'just now', pct: 100 } : { ...j, pct: Math.min(100, j.pct + 3 + Math.random() * 7) })
        : j) })), 1800);
  }
  componentWillUnmount() { window.removeEventListener('keydown', this.onKey); clearInterval(this.timer); }
  componentDidUpdate() { this.applyBrand(); if (this._consoleEl) this._consoleEl.scrollTop = this._consoleEl.scrollHeight; }

  applyTheme(t) { document.documentElement.dataset.theme = t; }
  applyBrand() { document.documentElement.style.setProperty('--brand', (window.CC_BOOT && window.CC_BOOT.brandColor) || this.props.brandColor || '#3b82c4'); }

  go(route) { return () => this.setState({ route, paletteOpen: false }); }

  navItem(id, label, icon) {
    const active = this.state.route === id;
    return { id, label, icon: this.ICONS[icon || id],
      fg: active ? 'var(--brand-ink)' : 'var(--ink-dim)',
      bg: active ? 'var(--brand-soft)' : 'transparent',
      go: this.go(id) };
  }

  filteredPal(q) {
    const role = (window.CC_BOOT && window.CC_BOOT.dcRole) || this.props.role || 'operator';
    const items = this._hydrated ? this.realPalItems(role) : [
      { label: 'bloomandbranch.com', sub: 'Kinsta · Production + Staging', kind: 'site', icon: this.ICONS.site, act: 'site', sid: 'bloom' },
      { label: 'harborlightyoga.com', sub: 'Kinsta · Production', kind: 'site', icon: this.ICONS.site, act: 'site', sid: 'harbor' },
      { label: 'petersonlaw.com', sub: 'WP Engine · Production + Staging', kind: 'site', icon: this.ICONS.site, act: 'site', sid: 'peterson' },
      { label: 'cascadecoffeeroasters.com', sub: 'Kinsta · Production', kind: 'site', icon: this.ICONS.site, act: 'site', sid: 'cascade' },
      { label: 'thewildflowerpantry.com', sub: 'DNS active · Hover', kind: 'domain', icon: this.ICONS.domains, act: 'domain', did: 'wildflowerd' },
      { label: 'midwestmakersmarket.com', sub: 'DNS active · Spaceship', kind: 'domain', icon: this.ICONS.domains, act: 'domain', did: 'midwestd' },
      { label: 'Open terminal', sub: 'Streamed console on any site', kind: 'command', icon: this.ICONS.terminal, act: 'dock' },
      { label: 'New quicksave on…', sub: 'Git snapshot of a site', kind: 'command', icon: this.ICONS.quicksave, act: 'dock' },
      { label: 'Go to Billing → Invoices', sub: '', kind: 'command', icon: this.ICONS.billing, act: 'billing' },
      ...(role === 'operator' ? [
        { label: 'Go to Security → Coverage', sub: 'Fleet audit coverage', kind: 'command', icon: this.ICONS.security, act: 'security' },
        { label: 'Bulk tools on filtered sites…', sub: 'sync · deploy defaults · https · backup', kind: 'command', icon: this.ICONS.sites, act: 'sites' }
      ] : [])
    ];
    const needle = q.trim().toLowerCase();
    return (needle ? items.filter(i => (i.label + ' ' + i.sub + ' ' + i.kind).toLowerCase().includes(needle)) : items).slice(0, 8);
  }
  runPal(r) {
    if (r.act === 'dock') this.setState({ dockOpen: true, paletteOpen: false });
    else if (r.act === 'site') this.openSite(r.sid);
    else if (r.act === 'domain') this.openDomain(r.did);
    else this.setState({ route: r.act, paletteOpen: false });
  }


  // ── Real-data hydration (CaptainCore REST; injected via window.CC_BOOT) ──
  hydrate() {
    const boot = window.CC_BOOT;
    if (!boot || !boot.nonce) return;
    const api = (p) => fetch(boot.restRoot + 'captaincore/v1' + p, { headers: { 'X-WP-Nonce': boot.nonce } })
      .then(r => { if (r.status === 401 || r.status === 403) throw new Error('auth'); return r.json(); });
    const fmtStorage = (b) => { const n = parseInt(b, 10) || 0; if (!n) return '\u2014';
      return n >= 1073741824 ? (n / 1073741824).toFixed(1) + ' GB' : Math.round(n / 1048576) + ' MB'; };
    Promise.all([api('/sites/'), api('/accounts/'), api('/domains/')]).then(([sites, accounts, domains]) => {
      const accName = {}; (Array.isArray(accounts) ? accounts : []).forEach(a => { accName[a.account_id] = a.name; });
      this.FLEET = (Array.isArray(sites) ? sites : []).filter(x => !x.removed).map(x => {
        const envs = (x.environments || []).map(e => e.environment === 'Production' ? 'Prod' : e.environment).filter(Boolean).join(' \u00b7 ') || 'Prod';
        const provider = (x.provider || '').replace(/\b[a-z]/g, c => c.toUpperCase());
        return { id: String(x.site_id), name: x.name, provider, account: accName[x.account_id] || '',
          core: x.core || '', visits: x.visits ? Number(x.visits).toLocaleString() : '\u2014',
          storage: fmtStorage(x.storage), envs, updates: 0, vuln: 0, owned: true, theme: '',
          backup: 'Direct',
          labels: (Array.isArray(x.labels) ? x.labels : []).map(l => typeof l === 'string' ? l : (l && (l.type || l.text)) || '').filter(Boolean),
          unassigned: !x.account_id,
          plugins: {}, home_url: x.home_url, screenshot: x.screenshot };
      });
      this.ACCOUNTS = (Array.isArray(accounts) ? accounts : []).map(a => ({ id: String(a.account_id), name: a.name,
        users: (a.metrics && a.metrics.users) || 0, sites: (a.metrics && a.metrics.sites) || 0,
        domains: (a.metrics && a.metrics.domains) || 0, plan: a.plan_name || '', owned: true }));
      this.DOMAINS = (Array.isArray(domains) ? domains : []).map(d => ({ id: String(d.domain_id), name: d.name,
        account: '', registrar: d.provider_id ? 'Registrar' : '\u2014', dns: !!d.remote_id,
        expires: '\u2014', auto: null, owned: true }));
      this._hydrated = true;
      this.setState({ tick: this.state.tick });
    }).catch(err => {
      if (err && err.message === 'auth' && boot.loginUrl) { location.href = boot.loginUrl; return; }
      console.warn('CaptainCore v3 hydrate failed; using design sample data.', err);
    });
  }
  realPinned() {
    const healthOf = x => x.vuln ? ['Vulnerability', 'var(--bad)'] : x.updates ? ['Updates pending', 'var(--warn)'] : ['Healthy', 'var(--ok)'];
    return this.FLEET.slice(0, 4).map(x => { const [health, dot] = healthOf(x);
      return { id: x.id, name: x.name, sub: [x.provider, x.core, x.envs].filter(Boolean).join(' \u00b7 '), health, dot }; });
  }
  realPalItems(role) {
    const sites = this.FLEET.map(x => ({ label: x.name, sub: [x.provider, x.envs].filter(Boolean).join(' \u00b7 '),
      kind: 'site', icon: this.ICONS.site, act: 'site', sid: x.id }));
    const doms = this.DOMAINS.filter(d => d.dns).map(d => ({ label: d.name, sub: 'DNS active',
      kind: 'domain', icon: this.ICONS.domains, act: 'domain', did: d.id }));
    return [...sites, ...doms,
      { label: 'Open terminal', sub: 'Streamed console on any site', kind: 'command', icon: this.ICONS.terminal, act: 'dock' },
      { label: 'Go to Billing \u2192 Invoices', sub: '', kind: 'command', icon: this.ICONS.billing, act: 'billing' },
      ...(role === 'operator' ? [
        { label: 'Go to Security \u2192 Coverage', sub: 'Fleet audit coverage', kind: 'command', icon: this.ICONS.security, act: 'security' },
        { label: 'Bulk tools on filtered sites\u2026', sub: 'sync \u00b7 deploy defaults \u00b7 https \u00b7 backup', kind: 'command', icon: this.ICONS.sites, act: 'sites' }
      ] : [])];
  }
  realStats(running) {
    return this.FLEET.length + ' sites \u00b7 ' + this.DOMAINS.length + ' domains \u00b7 ' + running + ' jobs running';
  }

  renderVals() {
    const role = (window.CC_BOOT && window.CC_BOOT.dcRole) || this.props.role || 'operator';
    const variant = this.props.shellVariant ?? 'rail';
    const s = this.state;
    const isOp = role === 'operator';
    const hour = new Date().getHours();
    const dayPart = hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening';
    const userName = (window.CC_BOOT && window.CC_BOOT.userFirstName) || (isOp ? 'Austin' : 'Kara');

    const primary = isOp
      ? [this.navItem('home', 'Home'), this.navItem('sites', 'Sites'), this.navItem('domains', 'Domains'), this.navItem('accounts', 'Accounts'), this.navItem('billing', 'Billing')]
      : [this.navItem('home', 'Home'), this.navItem('sites', 'Sites'), this.navItem('domains', 'Domains'), this.navItem('billing', 'Billing'), this.navItem('reports', 'Reports')];
    const operate = [this.navItem('security', 'Security'), this.navItem('audits', 'Site Audits', 'audits'), this.navItem('reports', 'Reports'), this.navItem('archives', 'Archives')];

    const launcher = (isOp ? [
      { label: 'Sites', desc: 'Fleet list, filters, bulk tools', meta: this._hydrated ? String(this.FLEET.length) : '128', icon: this.ICONS.sites, act: 'sites' },
      { label: 'Domains & DNS', desc: 'Zones, registrar, email', meta: this._hydrated ? String(this.DOMAINS.length) : '94', icon: this.ICONS.domains, act: 'domains' },
      { label: 'Security', desc: 'Vulnerabilities, checksums, coverage', meta: '2 open', icon: this.ICONS.security, act: 'security' },
      { label: 'Billing', desc: 'Invoices, plans, subscriptions', meta: '$12.4k/mo', icon: this.ICONS.billing, act: 'billing' },
      { label: 'Terminal', desc: 'Run commands across the fleet', meta: '⌃`', icon: this.ICONS.terminal, act: 'dock' }
    ] : [
      { label: 'My sites', desc: 'Backups, updates, stats', meta: this._hydrated ? String(this.FLEET.length) : '4', icon: this.ICONS.sites, act: 'sites' },
      { label: 'Domains', desc: 'DNS and email forwarding', meta: this._hydrated ? String(this.DOMAINS.length) : '6', icon: this.ICONS.domains, act: 'domains' },
      { label: 'Billing', desc: 'Invoices and payment methods', meta: '1 due', icon: this.ICONS.billing, act: 'billing' },
      { label: 'Reports', desc: 'Monthly maintenance summaries', meta: 'June ready', icon: this.ICONS.reports, act: 'reports' },
      { label: 'Get help', desc: 'Invite a teammate or contact us', meta: '', icon: this.ICONS.support, act: 'accounts' }
    ]).map(l => ({ ...l, go: l.act === 'dock' ? () => this.setState({ dockOpen: true }) : this.go(l.act) }));

    const attention = (isOp ? [
      { dot: 'var(--bad)', title: '2 plugin vulnerabilities across 5 sites', sub: 'gravityforms 2.9.1 (high) · woocommerce 9.8.2 (medium)', action: 'Review', act: 'security' },
      { dot: 'var(--warn)', title: '14 sites have updates pending', sub: 'Steer queue ready · last fleet update ran 6 days ago', action: 'Update', act: 'sites' },
      { dot: 'var(--warn)', title: 'harborlightyoga.com expires in 12 days', sub: 'Auto-renew is off at Hover', action: 'Renew', act: 'domains' },
      { dot: 'var(--ink-dim)', title: '3 sites are unassigned to an account', sub: 'Imported from Kinsta on Jul 9', action: 'Assign', act: 'accounts' }
    ] : [
      { dot: 'var(--warn)', title: 'Invoice #4482 is due July 22', sub: '$68.00 · Visa ··4242 on file — pay in one click', action: 'Pay', act: 'billing' },
      { dot: 'var(--ok)', title: 'Your June maintenance report is ready', sub: 'bloomandbranch.com · 9 plugin updates, 99.98% uptime', action: 'View', act: 'reports' },
      { dot: 'var(--ink-dim)', title: 'Backups healthy on all 4 sites', sub: 'Most recent quicksave 2 hours ago', action: 'Details', act: 'sites' }
    ]).map(a => ({ ...a, go: this.go(a.act) }));

    const jobsBase = isOp ? s.jobs : s.jobs.filter(j => !/\d sites/.test(j.target));
    const jobs = jobsBase.map(j => ({ ...j, pct: Math.round(j.pct),
      right: j.state === 'running' ? Math.round(j.pct) + '%' : j.right,
      running: j.state === 'running',
      fg: j.state === 'running' ? 'var(--brand-ink)' : 'var(--ink-dim)',
      dot: j.state === 'running' ? 'var(--brand)' : 'var(--ok)' }));

    const activity = isOp ? [
      { t: '2m', text: 'Quicksave 8f3c21a on bloomandbranch.com — 3 files changed' },
      { t: '18m', text: 'Austin deployed staging → production on petersonlaw.com' },
      { t: '1h', text: 'Mailgun sending verified for thewildflowerpantry.com' },
      { t: '3h', text: 'kara@petersonlaw.com accepted invite to Peterson Law (sites-only)' },
      { t: '5h', text: 'Restic backup completed on 128 sites — 0 failures' },
      { t: '8h', text: 'DNS zone imported for midwestmakersmarket.com (14 records)' }
    ] : [
      { t: '2h', text: 'Quicksave created on bloomandbranch.com — 3 files changed' },
      { t: '6h', text: 'Nightly backup completed on all 4 sites' },
      { t: '1d', text: 'gravityforms updated 2.9.1 → 2.9.4 on bloomandbranch.com' },
      { t: '2d', text: 'June maintenance report sent to 2 recipients' },
      { t: '4d', text: 'DNS record added on harborlightyoga.com (TXT · verification)' }
    ];

    const pinned = (this._hydrated ? this.realPinned() : isOp ? [
      { id: 'bloom', name: 'bloomandbranch.com', sub: 'Kinsta · 6.8.1 · 12.4k visits/wk', health: 'Vulnerability', dot: 'var(--bad)' },
      { id: 'peterson', name: 'petersonlaw.com', sub: 'WP Engine · 6.8.1 · 3.1k visits/wk', health: 'Updates pending', dot: 'var(--warn)' },
      { id: 'cascade', name: 'cascadecoffeeroasters.com', sub: 'Kinsta · 6.8.0 · 8.9k visits/wk', health: 'Healthy', dot: 'var(--ok)' },
      { id: 'harbor', name: 'harborlightyoga.com', sub: 'Kinsta · 6.8.1 · 1.7k visits/wk', health: 'Vulnerability', dot: 'var(--bad)' }
    ] : [
      { id: 'bloom', name: 'bloomandbranch.com', sub: '6.8.1 · backed up 2h ago', health: 'Healthy', dot: 'var(--ok)' },
      { id: 'harbor', name: 'harborlightyoga.com', sub: '6.8.1 · backed up 6h ago', health: 'Updates pending', dot: 'var(--warn)' },
      { id: 'wildflower', name: 'thewildflowerpantry.com', sub: '6.8.1 · backed up 6h ago', health: 'Healthy', dot: 'var(--ok)' },
      { id: 'lakeside', name: 'lakesideinn.com', sub: '6.8.1 · backed up 6h ago', health: 'Healthy', dot: 'var(--ok)' }
    ]).map(p => ({ ...p, mono: p.name.slice(0, 2).toUpperCase(), go: () => this.openSite(p.id) }));

    const listVals = this.computeList(s, isOp);
    const detailVals = this.computeDetail(s);
    const domainsVals = this.computeDomains(s, isOp);
    const domainVals = this.computeDomain(s);
    const accountsVals = this.computeAccounts(s, isOp);
    const accountVals = this.computeAccount(s);
    const billingVals = this.computeBilling(s);
    const securityVals = this.computeSecurity(s);
    const auditsVals = this.computeAudits(s);
    const reportsVals = this.computeReports(s, isOp);
    const archivesVals = this.computeArchives(s);
    const settingsVals = this.computeSettings(s);
    const profileVals = this.computeProfile(s);

    const scriptLen = this.CONSOLE_SCRIPT.length;
    const total = s.tick + 8;
    const consoleLines = [];
    for (let i = Math.max(0, total - 30); i < total; i++) {
      const [k, text] = this.CONSOLE_SCRIPT[i % scriptLen];
      consoleLines.push({ text, fg: k === 'ok' ? 'var(--ok)' : k === 'dim' ? 'var(--ink-dim)' : 'var(--ink)' });
    }
    const liveTail = consoleLines[consoleLines.length - 1].text;

    const palResults = this.filteredPal(s.palQuery).map((r, i) => ({ ...r,
      bg: i === s.palIdx ? 'var(--panel-2)' : 'transparent', run: () => this.runPal(r) }));

    const stub = ['', '', 'home'];

    return {
      userName, userInitials: userName.slice(0, 2).toUpperCase(),
      greeting: `Good ${dayPart}, ${userName}`,
      statsLine: this._hydrated ? this.realStats(jobs.filter(j => j.running).length) : (isOp ? '128 sites · 94 domains · 2 jobs running · fleet coverage 87%' : '4 sites · 6 domains · everything backed up'),
      nav: primary, navOperate: operate.map(n => n), navBottom: [this.navItem('settings', 'Settings')],
      showOperate: isOp && variant !== 'topnav',
      showRail: variant !== 'topnav', showTopNav: variant === 'topnav',
      railWidth: variant === 'slim' ? '56px' : '208px',
      labelDisplay: variant === 'slim' ? 'none' : 'inline',
      railJustify: variant === 'slim' ? 'center' : 'flex-start',
      dockSide: variant === 'topnav' ? 'right' : 'left',
      showHome: s.route === 'home', showSites: s.route === 'sites', showSite: s.route === 'site',
      showDomains: s.route === 'domains', showDomain: s.route === 'domain',
      showAccounts: s.route === 'accounts', showAccount: s.route === 'account', showBilling: s.route === 'billing',
      showSecurity: s.route === 'security', showAudits: s.route === 'audits', showReports: s.route === 'reports',
      showArchives: s.route === 'archives', showSettings: s.route === 'settings', showProfile: s.route === 'profile',
      showStub: !['home', 'sites', 'site', 'domains', 'domain', 'accounts', 'account', 'billing', 'security', 'audits', 'reports', 'archives', 'settings', 'profile'].includes(s.route),
      stubTitle: stub[0], stubDesc: stub[1], stubIcon: this.ICONS[stub[2]],
      launcher, attention, attentionCount: attention.length,
      jobs, activity, pinned, pinnedTitle: isOp ? 'Pinned sites' : 'Your sites',
      ...listVals, ...detailVals, ...domainsVals, ...domainVals,
      ...accountsVals, ...accountVals, ...billingVals,
      ...securityVals, ...auditsVals, ...reportsVals, ...archivesVals, ...settingsVals, ...profileVals,
      goProfile: this.go('profile'),
      consoleRef: (el) => { this._consoleEl = el; if (el) el.scrollTop = el.scrollHeight; },
      runningCount: jobs.filter(j => j.running).length,
      consoleLines, liveTail, consoleBg: 'var(--panel)',
      dockOpen: s.dockOpen, dockClosed: !s.dockOpen,
      paletteOpen: s.paletteOpen, palQuery: s.palQuery, palResults,
      themeIcon: (s.theme === 'dark')
        ? 'M12 4V2m0 20v-2M4 12H2m20 0h-2M5.6 5.6 4.2 4.2m15.6 15.6-1.4-1.4m0-12.8 1.4-1.4M4.2 19.8l1.4-1.4M12 7a5 5 0 100 10 5 5 0 000-10z'
        : 'M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z',
      toggleTheme: () => { const t = this.state.theme === 'dark' ? 'light' : 'dark'; this.setState({ theme: t }); this.applyTheme(t); localStorage.setItem('captaincore-theme', t); },
      goHome: this.go('home'), goSites: this.go('sites'),
      openDock: () => this.setState({ dockOpen: true }),
      closeDock: () => this.setState({ dockOpen: false }),
      openPalette: () => this.setState({ paletteOpen: true, palQuery: '', palIdx: 0 }),
      closePalette: () => this.setState({ paletteOpen: false }),
      ddClose: () => this.setState({ ddOpen: '', ddQ: '' }),
      ddQ: s.ddQ, onDdQ: e => this.setState({ ddQ: e.target.value }),
      stopProp: (e) => e.stopPropagation(),
      onPalInput: (e) => this.setState({ palQuery: e.target.value, palIdx: 0 })
    };
  }
}
</script>
</body>
</html>
