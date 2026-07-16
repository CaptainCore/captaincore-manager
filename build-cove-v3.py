#!/usr/bin/env python3
"""Assemble templates/cove-v3.php from the Claude Design export (Anchor Home.dc.html).

Reads the full design file, applies surgical patches that let the design consume
real CaptainCore data via a server-injected window.CC_BOOT, and wraps it in the
WordPress PHP shell. Rerunnable: re-fetch the design, rerun this, get a fresh template.
"""
import re, sys

SRC = '/private/tmp/claude-501/-Users-austin-Cove-Sites-anchor-localhost-public/1dbd78d2-16fb-43b3-9b59-3a3869d693a1/scratchpad/design/anchor-home-full.dc.html'
OUT = '/Users/austin/Cove/Sites/anchor.localhost/public/wp-content/plugins/captaincore-manager/templates/cove-v3.php'

src = open(SRC).read()

def patch(text, old, new, count=1, label=''):
    found = text.count(old)
    if found != count:
        print(f'PATCH FAIL [{label}]: expected {count} occurrence(s), found {found}: {old[:80]!r}')
        sys.exit(1)
    return text.replace(old, new)

# ── extract pieces ──────────────────────────────────────────────
m = re.search(r'(<x-dc>.*</x-dc>)', src, re.S)
assert m, 'x-dc block not found'
xdc = m.group(1)

m = re.search(r'(<script type="text/x-dc"[^>]*>.*</script>)\s*</body>', src, re.S)
assert m, 'dc script block not found'
dcscript = m.group(1)

m = re.search(r'<helmet>\s*(.*?)\s*</helmet>', src, re.S)
assert m, 'helmet not found'
helmet_inner = m.group(1)

# ── patch the logic script ──────────────────────────────────────
# 1. brand color from server config
dcscript = patch(dcscript,
    "document.documentElement.style.setProperty('--brand', this.props.brandColor ?? '#3b82c4');",
    "document.documentElement.style.setProperty('--brand', (window.CC_BOOT && window.CC_BOOT.brandColor) || this.props.brandColor || '#3b82c4');",
    1, 'brand')

# 2. role from server (operator = administrator)
dcscript = patch(dcscript,
    "const role = this.props.role ?? 'operator';",
    "const role = (window.CC_BOOT && window.CC_BOOT.dcRole) || this.props.role || 'operator';",
    2, 'role')

# 3. real first name
dcscript = patch(dcscript,
    "const userName = isOp ? 'Austin' : 'Kara';",
    "const userName = (window.CC_BOOT && window.CC_BOOT.userFirstName) || (isOp ? 'Austin' : 'Kara');",
    1, 'userName')

# 4. kick off hydration on mount
dcscript = patch(dcscript,
    "window.addEventListener('keydown', this.onKey);",
    "window.addEventListener('keydown', this.onKey);\n    this.hydrate();",
    1, 'mount-hydrate')

# 5. hydration + real-data helpers, inserted before renderVals()
HYDRATE = """
  // ── Real-data hydration (CaptainCore REST; injected via window.CC_BOOT) ──
  hydrate() {
    const boot = window.CC_BOOT;
    if (!boot || !boot.nonce) return;
    const api = (p) => fetch(boot.restRoot + 'captaincore/v1' + p, { headers: { 'X-WP-Nonce': boot.nonce } })
      .then(r => { if (r.status === 401 || r.status === 403) throw new Error('auth'); return r.json(); });
    const fmtStorage = (b) => { const n = parseInt(b, 10) || 0; if (!n) return '\\u2014';
      return n >= 1073741824 ? (n / 1073741824).toFixed(1) + ' GB' : Math.round(n / 1048576) + ' MB'; };
    Promise.all([api('/sites/'), api('/accounts/'), api('/domains/')]).then(([sites, accounts, domains]) => {
      const accName = {}; (Array.isArray(accounts) ? accounts : []).forEach(a => { accName[a.account_id] = a.name; });
      this.FLEET = (Array.isArray(sites) ? sites : []).filter(x => !x.removed).map(x => {
        const envs = (x.environments || []).map(e => e.environment === 'Production' ? 'Prod' : e.environment).filter(Boolean).join(' \\u00b7 ') || 'Prod';
        const provider = (x.provider || '').replace(/\\b[a-z]/g, c => c.toUpperCase());
        return { id: String(x.site_id), name: x.name, provider, account: accName[x.account_id] || '',
          core: x.core || '', visits: x.visits ? Number(x.visits).toLocaleString() : '\\u2014',
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
        account: '', registrar: d.provider_id ? 'Registrar' : '\\u2014', dns: !!d.remote_id,
        expires: '\\u2014', auto: null, owned: true }));
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
      return { id: x.id, name: x.name, sub: [x.provider, x.core, x.envs].filter(Boolean).join(' \\u00b7 '), health, dot }; });
  }
  realPalItems(role) {
    const sites = this.FLEET.map(x => ({ label: x.name, sub: [x.provider, x.envs].filter(Boolean).join(' \\u00b7 '),
      kind: 'site', icon: this.ICONS.site, act: 'site', sid: x.id }));
    const doms = this.DOMAINS.filter(d => d.dns).map(d => ({ label: d.name, sub: 'DNS active',
      kind: 'domain', icon: this.ICONS.domains, act: 'domain', did: d.id }));
    return [...sites, ...doms,
      { label: 'Open terminal', sub: 'Streamed console on any site', kind: 'command', icon: this.ICONS.terminal, act: 'dock' },
      { label: 'Go to Billing \\u2192 Invoices', sub: '', kind: 'command', icon: this.ICONS.billing, act: 'billing' },
      ...(role === 'operator' ? [
        { label: 'Go to Security \\u2192 Coverage', sub: 'Fleet audit coverage', kind: 'command', icon: this.ICONS.security, act: 'security' },
        { label: 'Bulk tools on filtered sites\\u2026', sub: 'sync \\u00b7 deploy defaults \\u00b7 https \\u00b7 backup', kind: 'command', icon: this.ICONS.sites, act: 'sites' }
      ] : [])];
  }
  realStats(running) {
    return this.FLEET.length + ' sites \\u00b7 ' + this.DOMAINS.length + ' domains \\u00b7 ' + running + ' jobs running';
  }

"""
dcscript = patch(dcscript, '  renderVals() {', HYDRATE + '  renderVals() {', 1, 'insert-helpers')

# 6. real stats line
dcscript = patch(dcscript,
    "statsLine: isOp ? '128 sites · 94 domains · 2 jobs running · fleet coverage 87%' : '4 sites · 6 domains · everything backed up',",
    "statsLine: this._hydrated ? this.realStats(jobs.filter(j => j.running).length) : (isOp ? '128 sites · 94 domains · 2 jobs running · fleet coverage 87%' : '4 sites · 6 domains · everything backed up'),",
    1, 'statsLine')

# 7. pinned strip from real fleet
dcscript = patch(dcscript,
    'const pinned = (isOp ? [',
    'const pinned = (this._hydrated ? this.realPinned() : isOp ? [',
    1, 'pinned')

# 8. palette items from real fleet
dcscript = patch(dcscript,
    "const items = [\n      { label: 'bloomandbranch.com'",
    "const items = this._hydrated ? this.realPalItems(role) : [\n      { label: 'bloomandbranch.com'",
    1, 'palette')

# 9. launcher counts
dcscript = patch(dcscript, "meta: '128'", "meta: this._hydrated ? String(this.FLEET.length) : '128'", 1, 'meta-sites')
dcscript = patch(dcscript, "meta: '94'", "meta: this._hydrated ? String(this.DOMAINS.length) : '94'", 1, 'meta-domains')
dcscript = patch(dcscript, "{ label: 'My sites', desc: 'Backups, updates, stats', meta: '4',",
                 "{ label: 'My sites', desc: 'Backups, updates, stats', meta: this._hydrated ? String(this.FLEET.length) : '4',", 1, 'meta-mysites')
dcscript = patch(dcscript, "{ label: 'Domains', desc: 'DNS and email forwarding', meta: '6',",
                 "{ label: 'Domains', desc: 'DNS and email forwarding', meta: this._hydrated ? String(this.DOMAINS.length) : '6',", 1, 'meta-mydomains')

# ── escape PHP-open sequences in design-derived content ─────────
def php_safe(text):
    return text.replace('<?', "<?php echo '<?'; ?>")

xdc = php_safe(xdc)
dcscript = php_safe(dcscript)
helmet_head = php_safe(helmet_inner)

# ── assemble the PHP template ───────────────────────────────────
php = """<?php
/**
 * CaptainCore v3 template ("Cove") — ground-up UI rebuild.
 *
 * Generated from the Claude Design project "Anchor Hosting UI Revamp"
 * (Anchor Home.dc.html) by scratchpad/build-cove-v3.py. The design runs on the
 * Design Components runtime (public/js/v3/support.js + vendored React UMD).
 * Served behind ?ui=v3 — see CaptainCore\\Router::load_template().
 *
 * Real data enters through window.CC_BOOT (below); the app hydrates the
 * fleet/accounts/domains lists from the captaincore/v1 REST API on mount and
 * falls back to the design's sample data when a fetch fails.
 */

$configurations = ( new CaptainCore\\Configurations )->get();
$config_path    = '/' . trim( (string) $configurations->path, '/' );
$config_path    = $config_path === '/' ? '/' : $config_path . '/';

if ( ! is_user_logged_in() ) {
    wp_safe_redirect( home_url( $config_path . 'login' ) );
    exit;
}

$user       = ( new CaptainCore\\User )->profile();
$colors     = CaptainCore\\Configurations::colors();
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
__HELMET__
<script>window.CC_BOOT = <?php echo wp_json_encode( $cc_boot ); ?>;</script>
<script src="<?php echo $plugin_url; ?>public/js/v3/react.production.min.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/v3/react-dom.production.min.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/v3/support.js"></script>
</head>
<body>
__XDC__
__DCSCRIPT__
</body>
</html>
"""
php = php.replace('__HELMET__', helmet_head).replace('__XDC__', xdc).replace('__DCSCRIPT__', dcscript)
open(OUT, 'w').write(php)
print('wrote', OUT, len(php), 'bytes')
