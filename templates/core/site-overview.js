// ── Site page: environment switcher + health cards ─────────────────────────
// The header's environment switcher is one colored pill ("Production ·
// Kinsta ▾") that opens a menu of every environment with where it runs, its
// WordPress and PHP, and Add an environment. The Overview opens with five
// health cards that answer "is anything wrong?" before anything else:
//   Uptime       the uptime monitor (open outage from monitor_incidents via
//                /sites/{id}/environments `uptime`), or that it is off
//   Last backup  newest restic snapshot (the backups list the History tab
//                loads; loadOverviewCounts prefetches it), plus the backup
//                check's `backup_health` from the environment details
//   Updates      managed updates on/off and the newest update run
//                (update-logs, kept as real.lastUpdate by loadQuicksaves)
//   WordPress    the environment's WordPress, PHP and storage
//   Visits       visits per week with the 14-day bars
// Each card opens the tab with the detail. Design mode (no real data) keeps
// sample values so the template renders.
Object.assign(Component.prototype, {

  ENV_DOTS: { Production: 'var(--ok)', Staging: 'var(--warn)' },

  envDot(name) {
    return this.ENV_DOTS[name] || 'var(--brand)';
  },

  envHostLabel(env, real) {
    const host = (env && env.host) || (real && real.site && real.site.provider) || '';
    if (host === 'captaincore') return (window.CC_BOOT && window.CC_BOOT.name) || 'Our servers';
    return ({ kinsta: 'Kinsta', rocketdotnet: 'Rocket.net', wpengine: 'WP Engine' })[host] || (host ? host.charAt(0).toUpperCase() + host.slice(1) : '');
  },

  agoText(ms) {
    if (!ms) return '';
    const m = Math.max(0, Math.round((Date.now() - ms) / 60000));
    if (m < 1) return 'just now';
    if (m < 60) return m + (m === 1 ? ' minute ago' : ' minutes ago');
    const h = Math.round(m / 60);
    if (h < 36) return h + (h === 1 ? ' hour ago' : ' hours ago');
    const d = Math.round(h / 24);
    return d + (d === 1 ? ' day ago' : ' days ago');
  },

  siteHeadVals(real, s, site, curEnv) {
    const envs = real && real.envs ? real.envs : null;
    const name = s.env || 'Production';
    const dot = this.envDot(name);
    const where = this.envHostLabel(curEnv, real) || site.provider || '';
    const facts = e => {
      const php = e && ((e.details && e.details.php_version) || e.php_version);
      return [e && e.core ? 'WordPress ' + e.core : '', php ? 'PHP ' + php : '', e && e.storage ? this.fmtStorage(e.storage) : ''].filter(Boolean).join(' · ');
    };
    const open = s.ddOpen === 'env';
    const thumb = curEnv && curEnv.screenshots && curEnv.screenshots.small ? curEnv.screenshots.small : '';
    const items = (envs || [{ environment: 'Production', core: site.core }]).map(e => {
      const cur = e.environment === name;
      return { name: e.environment, dot: this.envDot(e.environment), where: this.envHostLabel(e, real) || site.provider || '',
        sub: facts(e) || 'Not synced yet', rowBg: cur ? 'var(--panel-2)' : 'transparent', mark: cur ? '✓' : '',
        pick: () => { this.setState({ ddOpen: '' }); if (!cur) this.setEnv(e.environment); } };
    });
    return {
      envSwName: name, envSwWhere: where, envSwHasWhere: !!where,
      envSwStyle: 'border-color:color-mix(in srgb,' + dot + ' 45%,var(--rule));background:color-mix(in srgb,' + dot + ' 10%,var(--paper));color:color-mix(in oklch,' + dot + ' 62%,var(--ink))',
      envSwDot: dot, envSwOpen: open, envSwItems: items, envSwCount: items.length > 1 ? items.length + ' environments' : '',
      envSwToggle: () => this.setState(st => ({ ddOpen: st.ddOpen === 'env' ? '' : 'env', ddQ: '' })),
      dThumb: thumb, dHasThumb: this.thumbReady(thumb),
      ...this.headLinkVals(curEnv),
      // The account is an icon (its name in the tooltip) so a long name never
      // crowds the header; it opens the account when there is one to open.
      dAcctIcon: !!site.account, dAcctTitle: site.account ? 'Account: ' + site.account : '',
      dAcctCursor: site.accountId ? 'pointer' : 'default',
      ...this.healthCardVals(real, s, site, curEnv)
    };
  },

  // The environment's address and WordPress admin sit in the header under the
  // name (they follow the environment switcher): each opens in a new tab, and
  // the address has a copy button.
  // A plain site on our servers: HTML files, no WordPress or database. Its
  // pages leave out everything that reads or changes WordPress.
  envIsPlain(env) {
    return !!(env && env.details && typeof env.details === 'object' && env.details.start === 'plain');
  },

  headLinkVals(curEnv) {
    const url = curEnv && curEnv.home_url ? String(curEnv.home_url) : '';
    const plain = this.envIsPlain(curEnv);
    const host = url.replace(/^https?:\/\//i, '').replace(/\/$/, '');
    const admin = url && !plain ? url.replace(/\/$/, '') + '/wp-admin' : '';
    return { dHasUrl: !!url, dUrlHost: host, dUrlTitle: url, dHasAdmin: !!admin, dAdminTitle: 'Open ' + admin,
      dUrlOpen: () => { if (url) this.safeOpen(url); },
      dAdminOpen: () => { if (admin) this.safeOpen(admin); },
      dUrlCopy: () => { try { navigator.clipboard.writeText(url); } catch (e) {} this.toast('Copied ' + host, { kind: 'success', timeout: 1800 }); } };
  },

  // Connect card: SFTP / SSH /
  // Database tabs over the Credentials rows (credRows from app.js, each row
  // keeps its copy, reveal and open handlers). Unknown keys go under SFTP.
  connectVals(credRows, s, real) {
    // Site URL and WP admin live in the header (headLinkVals).
    const LINKS = ['Site URL', 'WP admin'], SSH = ['SSH'], DB = ['Database', 'DB user', 'DB password'];
    const rows = credRows || [];
    const groups = {
      SFTP: rows.filter(r => !LINKS.includes(r.k) && !SSH.includes(r.k) && !DB.includes(r.k)),
      SSH: rows.filter(r => SSH.includes(r.k)),
      Database: rows.filter(r => DB.includes(r.k))
    };
    const names = Object.keys(groups).filter(t => groups[t].length);
    const tab = names.includes(s.connTab) ? s.connTab : (names[0] || 'SFTP');
    // Our servers sign in with SSH keys only (managed under SSH keys, site-settings.js)
    // and reach the database through an SSH tunnel; there is no phpMyAdmin.
    const ours = !!(this.siteSettingsHost && this.siteSettingsHost(real, s) === 'captaincore');
    const notes = ours
      ? { SFTP: 'Sign in with an SSH key: our servers do not use passwords. Works in FileZilla, Transmit and other SFTP apps.',
          SSH: 'Connect from a terminal with your SSH key.',
          Database: 'Connect through SSH: in TablePlus, Sequel Ace or a similar app pick an SSH connection with the SFTP address, username, port and your SSH key, then database host 127.0.0.1, port 3306. Changes are live, so take a backup first.' }
      : { SFTP: 'For any SFTP app, such as FileZilla or Transmit.', SSH: 'Connect from a terminal.', Database: 'Changes here are live. Take a backup first.' };
    return {
      connKeysShow: ours && tab !== 'Database',
      connTabs: names.map(t => ({ label: t, pressed: t === tab ? 'true' : 'false',
        bg: t === tab ? 'var(--paper)' : 'transparent', fg: t === tab ? 'var(--ink)' : 'var(--ink-dim)',
        sh: t === tab ? '0 1px 2px rgba(0,0,0,.12)' : 'none', pick: () => this.setState({ connTab: t }) })),
      connHasTabs: names.length > 0,
      connRows: groups[tab] || [], connNote: notes[tab] || '',
      connIsSftp: tab === 'SFTP', connIsDb: tab === 'Database'
    };
  },

  // Domains card: the first five, then "Show all".
  overviewDomainVals(list, s) {
    const all = list || [];
    const open = !!s.domAll || all.length <= 6;
    return { dDomains: open ? all : all.slice(0, 5), dDomainCount: all.length > 1 ? all.length + ' on this site' : '',
      hasDomMore: all.length > 6, dDomMore: open ? 'Show fewer' : 'Show all ' + all.length,
      domToggle: () => this.setState(st => ({ domAll: !st.domAll })) };
  },

  // The screenshot shows only once it has loaded, so a missing file never
  // leaves a broken image in the header (an onError attribute in the template
  // would run as inline script before the app binds it).
  thumbReady(url) {
    if (!url) return false;
    const st = this._thumbs = this._thumbs || {};
    if (st[url] === undefined) {
      st[url] = 'loading';
      const im = new Image();
      im.onload = () => { st[url] = 'ok'; this.setState({}); };
      im.onerror = () => { st[url] = 'bad'; };
      im.src = url;
    }
    return st[url] === 'ok';
  },

  healthCardVals(real, s, site, curEnv) {
    const OK = 'var(--ok)', WARN = 'var(--warn)', BAD = 'var(--bad)', DIM = 'var(--ink-dim)';
    const card = (k, v, sub, tone, go) => ({ k, v, sub, dot: tone,
      bd: tone === BAD ? 'color-mix(in srgb,var(--bad) 55%,var(--rule))' : tone === WARN ? 'color-mix(in srgb,var(--warn) 50%,var(--rule))' : 'var(--rule)',
      subFg: tone === BAD ? 'var(--bad)' : tone === WARN ? 'color-mix(in srgb,var(--warn) 70%,var(--ink))' : 'var(--ink-dim)',
      go: go ? () => this.goSiteTab(go) : () => {}, cursor: go ? 'pointer' : 'default', hasBars: false });
    if (!real) {
      return { hCards: [card('Uptime', 'Up', 'Uptime monitor on', OK), card('Last backup', '6 hours ago', '30 kept', OK, 'backups'),
        card('Updates', 'Ran 2 days ago', '3 plugins updated', OK, 'versions'), card('WordPress', site.core || '6.9', 'PHP 8.3', DIM, 'plugins'),
        { ...card('Visits', site.visits + ' / wk', 'Last 14 days', DIM, 'stats'), hasBars: true }] };
    }
    if (!real.envs) return { hCards: ['Uptime', 'Last backup', 'Updates', 'WordPress', 'Visits'].map(k => card(k, '…', ' ', DIM)) };
    const e = curEnv || {};
    const d = (e.details && typeof e.details === 'object') ? e.details : {};
    // Uptime
    const up = e.uptime || {};
    let uptime;
    if (up.down) {
      const since = this.parseTs(up.since) * 1000;
      uptime = card('Uptime', 'Down', (since ? 'Since ' + this.agoText(since).replace(/ ago$/, '') : 'Not answering') + (up.code ? ' · HTTP ' + up.code : ''), BAD);
    } else if (up.monitored || Number(e.monitor_enabled)) {
      uptime = card('Uptime', 'Up', 'Uptime monitor on', OK);
    } else {
      uptime = card('Uptime', 'Not watched', 'Uptime monitor off', DIM);
    }
    // Last backup
    let backup;
    if (Array.isArray(real.backups) && real.backupsEnv === s.env) {
      let newest = 0;
      real.backups.forEach(b => { const t = this.parseTs(b._raw && b._raw.time) * 1000; if (t > newest) newest = t; });
      const n = real.backups.length;
      // Environments on our own servers keep local daily snapshots (Hosting →
      // Local backups) rather than restic backups in Backblaze.
      if (!n && e.host === 'captaincore') backup = card('Backups', 'Daily, on the server', 'Kept 14 days', OK, (window.CC_BOOT || {}).dcRole === 'operator' ? 'hosting' : null);
      // The list can come back empty while the environment records backups
      // (the list could not be read): show the recorded count, not "none".
      else if (!n && Number(d.backup_count) > 0) backup = card('Backups', Number(d.backup_count).toLocaleString() + ' kept', 'Open to see the latest', DIM, 'backups');
      else if (!n) backup = card('Last backup', 'None yet', 'No backups for this environment', WARN, 'backups');
      else {
        const stale = Date.now() - newest > 48 * 3600 * 1000;
        backup = card('Last backup', this.agoText(newest) || 'Unknown', n.toLocaleString() + ' kept' + (real.backupsSince ? ' since ' + real.backupsSince : ''), stale ? WARN : OK, 'backups');
      }
    } else {
      const n = Number(d.backup_count) || 0;
      backup = card('Last backup', '…', n ? n.toLocaleString() + ' kept' : 'Checking backups', DIM, 'backups');
    }
    const issues = Array.isArray(d.backup_health_issues) ? d.backup_health_issues.filter(Boolean) : [];
    if (d.backup_health && d.backup_health !== 'ok') {
      const first = issues[0];
      backup = { ...card('Last backup', backup.v, 'Backup check: ' + (typeof first === 'string' ? first : (first && (first.message || first.issue)) || d.backup_health), WARN, 'backups') };
    }
    // Updates
    let updates;
    if (!Number(e.updates_enabled)) updates = card('Updates', 'Off', 'Automatic updates are off', DIM, 'versions');
    else if (real.qs === null || real.qs === undefined || real.qsEnv !== s.env) updates = card('Updates', '…', 'Automatic updates on', DIM, 'versions');
    else if (!real.lastUpdate) updates = card('Updates', 'On', 'No runs recorded yet', OK, 'versions');
    else {
      const l = real.lastUpdate, t = Number(l.created_at) * 1000;
      const changed = (Number(l.plugins_changed) || 0) + (Number(l.themes_changed) || 0);
      const failed = l.status === 'failed';
      updates = card('Updates', 'Ran ' + this.agoText(t), failed ? 'The last run failed' : changed ? changed + (changed === 1 ? ' update' : ' updates') + ' applied' : 'Nothing needed updating',
        failed ? WARN : Date.now() - t > 14 * 86400 * 1000 ? WARN : OK, 'versions');
    }
    // WordPress
    const php = d.php_version || e.php_version;
    const wp = card('WordPress', e.core || site.core || '—', [php ? 'PHP ' + php : '', e.storage ? this.fmtStorage(e.storage) : (s.env === 'Production' ? site.storage : '')].filter(Boolean).join(' · ') || ' ', DIM, 'plugins');
    // Visits
    // The 14-day bars are the site's (production) traffic, so other
    // environments show their own weekly count only.
    const prod = s.env === 'Production';
    const raw = e.visits != null && e.visits !== '' && Number(e.visits) > 0 ? Number(e.visits).toLocaleString() : (prod && site.visits && site.visits !== '\u2014' ? site.visits : '');
    const vis = raw ? { ...card('Visits', raw + ' / wk', prod ? 'Last 14 days' : 'This environment', DIM, 'stats'), hasBars: prod }
      : card('Visits', '—', 'No visit data yet', DIM, 'stats');
    // A plain site has no WordPress to update or report on: what it is, and the
    // space its files use (the server reports usage with each health check).
    if (this.envIsPlain(e)) {
      const op = (window.CC_BOOT || {}).dcRole === 'operator';
      const u = d.usage && d.usage.files && Number(d.usage.files.limit) > 0 ? d.usage.files : null;
      const storage = u
        ? card('Storage', this.fmtBytes(Number(u.used)), 'of ' + this.fmtBytes(Number(u.limit)) + ' for files', Number(u.used) / Number(u.limit) > 0.85 ? WARN : DIM, op ? 'hosting' : null)
        : card('Storage', e.storage ? this.fmtStorage(e.storage) : '—', 'Files on the server', DIM, op ? 'hosting' : null);
      return { hCards: [uptime, backup, card('Site', 'Plain HTML', 'No WordPress or database', DIM, op ? 'hosting' : null), storage, vis] };
    }
    const em = this.emailCardVals(d, card, OK, WARN, BAD, DIM);
    return Object.assign({ hCards: em.card ? [uptime, backup, updates, wp, em.card, vis] : [uptime, backup, updates, wp, vis] }, em.vals);
  },

  // ── Email ────────────────────────────────────────────────────
  // How this environment sends mail (details.email_route from EmailRoute,
  // details.email the raw probe, details.email_route_changes the log). The
  // card opens a panel under the cards with the route, its issues, what
  // changed and each mail plugin's settings (secrets already redacted).
  EMAIL_PRIORITY: ['stale-password', 'test-mode', 'mail-blocked'],

  emailCardVals(d, card, OK, WARN, BAD, DIM) {
    const r = d && d.email_route;
    if (!r || typeof r !== 'object') return { card: null, vals: { emOpen: false } };
    const issues = Array.isArray(r.issues) ? r.issues : [];
    const bad = issues.some(i => this.EMAIL_PRIORITY.includes(i.key));
    const tone = bad ? BAD : issues.length ? WARN : r.status === 'anchor' ? OK : DIM;
    const log = r.log && typeof r.log === 'object' ? r.log : null;
    const SHORT = { 'stale-password': 'Old password, sends failing', 'test-mode': 'Test mode: not delivering', 'mail-blocked': 'Mail is switched off', failing: 'Last send failed', 'zone-unused': 'Anchor Mailgun zone not used', 'zone-unverified': 'Mailgun zone not verified' };
    const sub = issues.length ? (SHORT[issues[0].key] || issues[0].label) + (issues.length > 1 ? ' +' + (issues.length - 1) : '')
      : [r.plugin_name, r.domain, log ? (Number(log.sent) || 0) + ' sent this week' : ''].filter(Boolean).join(' · ') || (r.status === 'php_mail' ? 'No mail plugin' : ' ');
    const open = !!this.state.emOpen;
    const c = { ...card('Email', r.label || 'Unknown', sub, tone), go: () => this.setState({ emOpen: !open }), cursor: 'pointer' };
    if (!open) return { card: c, vals: { emOpen: false } };
    const yes = v => v ? 'Yes' : 'No';
    const rows = [
      ['Sends through', r.label + (r.status === 'anchor' ? ' (our account)' : '')],
      ['Plugin', r.plugin_name || 'None'],
      ['Sending domain', r.domain || '—'],
      ['From', r.from_email || '—'],
      ...(r.status === 'anchor' ? [['Mailgun zone', (r.zone_state || 'unknown')], ['Password', ({ current: 'Current', stale: 'Not the one we last set', unknown: 'Not compared' })[r.secret] || r.secret]] : []),
      ...(r.zone && r.status !== 'anchor' ? [['Anchor Mailgun zone for this domain', r.zone + ' (not used)']] : []),
      ['Test mode', yes(r.test_mode)],
      ...(r.blocker ? [['Mail switched off by', r.blocker]] : []),
      ...((r.interceptors || []).length ? [['Must-use plugins touching mail', r.interceptors.join(', ')]] : []),
      ...((r.conflicts || []).length ? [['Also active', r.conflicts.join(', ')]] : []),
      ...((r.unconfigured || []).length ? [['Installed, not set up', r.unconfigured.join(', ')]] : []),
      ...(log ? [['Last 7 days', (Number(log.sent) || 0) + ' sent · ' + (Number(log.failed) || 0) + ' failed' + (Number(log.sandboxed) ? ' · ' + log.sandboxed + ' held by test mode' : '')],
        ['Last delivered', log.last_sent ? log.last_sent + ' UTC' : 'Never'], ['Last failure', log.last_failed ? log.last_failed + ' UTC' : 'None']] : []),
      ['Checked', d.email_route_at ? this.agoText(Number(d.email_route_at) * 1000) : '—']
    ].map(([k, v]) => ({ k, v: String(v) }));
    const changes = (Array.isArray(d.email_route_changes) ? d.email_route_changes : []).slice().reverse()
      .map(x => ({ when: x.at ? new Date(Number(x.at) * 1000).toLocaleString() : '', from: x.from || '', to: x.to || '' }));
    const mailers = ((d.email && Array.isArray(d.email.mailers)) ? d.email.mailers : []).map(m => ({
      name: (m.name || m.plugin) + (d.email.effective === m.plugin ? ' · sends' : m.configured ? ' · set up' : ' · not set up'),
      json: JSON.stringify(m.settings || {}, null, 2) }));
    return { card: c, vals: {
      emOpen: true, emClose: () => this.setState({ emOpen: false }), emTitle: 'Email: ' + (r.label || 'Unknown'), emTone: tone,
      emIssues: issues.map(i => ({ label: i.label, tone: this.EMAIL_PRIORITY.includes(i.key) ? BAD : WARN })), emHasIssues: issues.length > 0,
      emRows: rows, emChanges: changes, emHasChanges: changes.length > 0, emMailers: mailers, emHasMailers: mailers.length > 0 } };
  }
});
