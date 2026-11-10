// CaptainCore v3 — Container page (mixin), /account/servers/<id>/<container>.
// Operators only. One site container on one of our servers: how a request is
// served (edge → the container's programs), each program (WordPress on
// FrankenPHP, every app, MariaDB) with its status and restart, the apps
// editor, the generated web server rules (read-only: each rule names where it
// is edited), this container's CPU and memory, and its logs.
//   GET  /servers/{id}/containers/{ct}?range=1h|24h|7d → { container, units, versions,
//        listening, db, traffic, caddyfile, edge_caddyfile, history, server, site, hosting }
//   POST /servers/{id}/containers/{ct}/restart  { what: php|database|cron|app-<name>|container }
//   Apps save through PUT /sites/{site}/hosting (a new hosting revision + apply).

Object.assign(Component.prototype, {

  openContainer(serverId, ct) {
    clearTimeout(this._srvStatsTimer);
    this.setState({ route: 'container', serverId: String(serverId), ctName: String(ct), ctRange: this.state.ctRange || '1h',
      ctRules: 'edge', ctAppEdit: null });
    this._ct = null; this._ctLog = null; this._ctAppDraft = null;
    this.loadContainer();
  },

  loadContainer() {
    const id = this.state.serverId, ct = this.state.ctName, range = this.state.ctRange || '1h';
    if (!id || !ct) return;
    const key = id + '|' + ct + '|' + range;
    clearTimeout(this._ctTimer);
    this._ctLoading = key;
    this.setState({});
    this.api('/servers/' + id + '/containers/' + encodeURIComponent(ct) + '?range=' + range).then(res => {
      if (this._ctLoading !== key) return;
      this._ctLoading = null;
      this._ct = (!res || res.code) ? { key, error: (res && res.message) || 'Could not read the container' } : { key, data: res, at: Date.now(), v: Date.now() };
      this.setState({});
      if (this.state.route === 'container' && this.state.ctName === ct) this._ctTimer = setTimeout(() => this.loadContainer(), 30000);
    }).catch(() => { this._ctLoading = null; this._ct = { key, error: 'Could not read the container' }; this.setState({}); });
  },

  containerRestart(what, label) {
    const id = this.state.serverId, ct = this.state.ctName;
    const tid = this.toast('Restarting ' + label + '…', { kind: 'loading' });
    this.api('/servers/' + id + '/containers/' + encodeURIComponent(ct) + '/restart', { method: 'POST', body: { what } }).then(res => {
      if (!res || res.code) { this.updateToast(tid, (res && res.message) || 'Restart failed', { kind: 'error', timeout: 8000 }); return; }
      const ok = res.health === '200';
      this.updateToast(tid, 'Restarted ' + label + (ok ? '. The site answers 200.' : '. The site answers ' + (res.health || 'nothing') + '.'), { kind: ok ? 'success' : 'error', timeout: 7000 });
      this.loadContainer();
    }).catch(() => this.updateToast(tid, 'Restart failed', { kind: 'error' }));
  },

  // Journal lines as people read them: local time, then the message. The web
  // server logs JSON, so those lines become "LEVEL message [logger] key=value".
  // Anything that is not a journal line passes through untouched.
  fmtLog(text) {
    return String(text || '').split('\n').map(line => {
      const m = line.match(/^(\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\S*)\s+\S+\s+[^:\s]+?(?:\[\d+\])?:\s?(.*)$/);
      if (!m) return line;
      const at = new Date(m[1]);
      if (isNaN(at)) return line;
      const two = n => String(n).padStart(2, '0');
      const when = two(at.getMonth() + 1) + '-' + two(at.getDate()) + ' ' + two(at.getHours()) + ':' + two(at.getMinutes()) + ':' + two(at.getSeconds());
      let msg = m[2];
      if (msg[0] === '{') {
        try {
          const j = JSON.parse(msg);
          if (j && typeof j === 'object' && j.msg !== undefined) {
            const { ts, level, logger, msg: text0, ...rest } = j;
            const extra = Object.entries(rest).map(([k, v]) => k + '=' + (v !== null && typeof v === 'object' ? JSON.stringify(v) : v)).join(' ');
            msg = (level ? String(level).toUpperCase().padEnd(5) + ' ' : '') + text0 + (logger ? '  [' + logger + ']' : '') + (extra ? '  ' + extra : '');
          }
        } catch (e) { /* not JSON after all */ }
      }
      return when + '  ' + msg;
    }).join('\n');
  },

  // A Logs button on a program: the viewer opens at once (the node takes a
  // couple of seconds to answer) instead of waiting, then jumping down the page.
  openContainerLog(source) {
    this.setState({ cxLogOpen: true });
    this.loadContainerLog(source);
  },

  loadContainerLog(source) {
    const id = this.state.serverId;
    this.setState({ ctLogSrc: source });
    this._ctLog = { source, loading: true, text: '' };
    this.setState({});
    this.api('/servers/' + id + '/logs?source=' + encodeURIComponent(source) + '&lines=300').then(res => {
      if (!this._ctLog || this._ctLog.source !== source) return;
      const bad = !res || res.code;
      this._ctLog = { source, error: bad, text: bad ? ((res && res.message) || 'Could not read the log') : (res.text || ''), at: Date.now() };
      this.setState({});
      setTimeout(() => { [this._ctLogEl, this._ctLogDlgEl].forEach(el => { if (el) el.scrollTop = el.scrollHeight; }); }, 0);
    }).catch(() => { this._ctLog = { source, error: true, text: 'Could not read the log' }; this.setState({}); });
  },

  // Save the apps list as a new hosting revision (the rest of the config as is).
  containerSaveApps(apps, note) {
    const d = this._ct && this._ct.data;
    if (!d || !d.site || !d.hosting) return Promise.resolve(false);
    const config = { ...d.hosting.config, apps };
    const tid = this.toast('Saving apps…', { kind: 'loading' });
    return this.api('/sites/' + d.site.site_id + '/hosting', { method: 'PUT', body: { environment: d.site.environment, config, note } }).then(res => {
      if (!res || res.code) { this.updateToast(tid, (res && res.message) || 'Could not save', { kind: 'error', timeout: 8000 }); return false; }
      this.updateToast(tid, res.token ? 'Saved as revision ' + res.revision + ': applying to ' + ((res.server && res.server.name) || 'the server') : 'Saved, but the CLI server did not answer. Apply it from the Hosting tab.', { kind: res.token ? 'success' : 'info', timeout: 6000 });
      // The apply takes a few seconds; read the container again after it.
      [6000, 15000].forEach(ms => setTimeout(() => { if (this.state.route === 'container') this.loadContainer(); }, ms));
      return true;
    }).catch(() => { this.updateToast(tid, 'Could not save', { kind: 'error' }); return false; });
  },

  containerAppForm(app) {
    const a = app || {};
    const env = a.env && typeof a.env === 'object' ? Object.keys(a.env).map(k => k + '=' + a.env[k]).join('\n') : '';
    return { name: a.name || '', command: a.command || '', port: String(a.port || ''), route: a.route || 'path',
      path: a.path || '/', strip_prefix: !!a.strip_prefix, hostname: a.hostname || '', env, enabled: a.enabled !== false, memory: String(a.memory_mb || 256) };
  },

  containerAppFromForm(f) {
    const env = {};
    String(f.env || '').split('\n').map(l => l.trim()).filter(Boolean).forEach(l => {
      const i = l.indexOf('='); if (i > 0) env[l.slice(0, i).trim()] = l.slice(i + 1).trim();
    });
    const app = { name: f.name.trim(), command: f.command.trim(), port: Number(f.port) || 0, route: f.route, enabled: f.enabled, env, memory_mb: Number(f.memory) || 256 };
    if (f.route === 'hostname') app.hostname = f.hostname.trim().toLowerCase();
    else { app.path = '/' + f.path.trim().replace(/^\/+|\/+$/g, ''); app.strip_prefix = !!f.strip_prefix; }
    return app;
  },

  computeContainer(s) {
    if (s.route !== 'container') return { showContainer: false };
    const st = this._ct, range = s.ctRange || '1h';
    const base = {
      showContainer: true, cxName: s.ctName, cxIllo: this.illo('container'),
      cxIcoFlow: this.icon('route'), cxIcoProg: this.icon('apps'), cxIcoRules: this.icon('code'), cxIcoUsage: this.icon('cpu'), cxIcoLogs: this.icon('logs'),
      cxIcoLogBtn: this.icon('logs'), cxIcoRestart: this.icon('restart'), cxIcoEdit: this.icon('edit'), cxIcoPlus: this.icon('plus'), cxIcoExt: this.icon('external'), cxIcoArrow: this.icon('arrow'),
      cxServerName: (st && st.data && st.data.server && st.data.server.name) || 'Server',
      cxBack: () => { clearTimeout(this._ctTimer); this.openServer(s.serverId); },
      cxRanges: [['1h', 'Hour'], ['24h', 'Day'], ['7d', 'Week']].map(([v, l]) => ({ label: l,
        fg: range === v ? 'var(--ink)' : 'var(--ink-dim)', bg: range === v ? 'var(--panel-2)' : 'transparent',
        go: () => { this.setState({ ctRange: v }); setTimeout(() => this.loadContainer(), 0); } })),
      cxRefresh: () => this.loadContainer(),
      cxLoading: !st || (!!this._ctLoading && !(st && st.data)),
      cxUpdating: !!this._ctLoading && !!(st && st.data),
      cxErr: st && st.error ? st.error : '', cxHasErr: !!(st && st.error)
    };
    if (!st || !st.data) return { ...base, cxReady: false };

    const d = st.data, c = d.container || {}, site = d.site, host = d.hosting, cfg = (host && host.config) || {};
    const units = d.units || [], unit = role => units.find(u => u.role === role) || null;
    const appUnit = name => units.find(u => u.role === 'app' && u.app === name) || null;
    const listening = d.listening || [];
    const C1 = 'var(--chart-1)';
    const gb = b => { const v = b / 1073741824; return (Math.round(v * 10) / 10) + ' GB'; };
    const since = t => { const ms = Date.parse(String(t || '').replace(/ UTC$/, 'Z').replace(/^\w+ /, '').replace(' ', 'T')); return isNaN(ms) ? 0 : (Date.now() - ms) / 1000; };
    const dot = u => {
      if (!u) return { fg: 'var(--ink-dim)', label: 'Not running' };
      if (u.active === 'active') return { fg: 'var(--ok)', label: u.sub === 'running' || u.sub === 'waiting' ? 'Running' : u.sub };
      if (u.active === 'activating') return { fg: 'var(--warn)', label: 'Starting' };
      if (u.active === 'failed') return { fg: 'var(--bad)', label: 'Failed' };
      return { fg: 'var(--warn)', label: u.active || 'Stopped' };
    };
    const up = u => u && u.active === 'active' && u.since ? 'up ' + this.fmtDuration(since(u.since)) : '';
    const restarts = u => u && u.restarts ? u.restarts + (u.restarts === 1 ? ' restart' : ' restarts') : '';
    const php = cfg.php || {}, tr = d.traffic || {}, codes = tr.codes || {};
    const versions = d.versions || {};
    const fp = (versions.frankenphp || '').match(/FrankenPHP (v[\d.]+)/), cad = (versions.frankenphp || '').match(/Caddy (v[\d.]+)/);
    const maria = (versions.mariadb || '').match(/(\d+\.\d+\.\d+)-MariaDB/);
    const hostingOpen = () => { if (!site) return; clearTimeout(this._ctTimer); this.setState({ env: site.environment || 'Production' }); this.openSite(String(site.site_id)); this.goSiteTab('hosting'); };
    const logBtn = src => () => this.openContainerLog('ct:' + s.ctName + ':' + src);

    // ── How a request is served ──
    const domains = cfg.domains || [];
    const primary = (domains.find(x => x.primary) || domains[0] || {}).name || '';
    const pp = cfg.password_protect || {};
    const edgeRules = [
      domains.length + (domains.length === 1 ? ' domain' : ' domains') + (primary ? ' · ' + primary : ''),
      'TLS' + (cfg.force_https ? ' · HTTPS forced' : ''),
      cfg.redirect_to_primary && domains.length > 1 ? 'Other domains → primary' : '',
      cfg.suspended ? 'Suspended: holding page (503)' : '',
      cfg.noindex !== false ? 'Hidden from search engines' : '',
      (cfg.ip_deny || []).length ? (cfg.ip_deny || []).length + ' IPs denied' : '',
      (cfg.redirects || []).length ? (cfg.redirects || []).length + ' redirects' : '',
      pp.enabled ? 'Password protected' : ''
    ].filter(Boolean).map(t => ({ t }));
    const apps = (cfg.apps || []);
    const progName = cmd => String(cmd || '').trim().split(/\s+/)[0].replace(/^\.\//, '') || 'app';
    const wpUnit = unit('php'), dbUnit = unit('database');
    const flowRoutes = apps.filter(a => a.enabled !== false && a.route === 'path').map(a => {
      const u = appUnit(a.name), dt = dot(u);
      return { match: a.path + (a.strip_prefix ? ' (prefix removed)' : ''), name: a.name, sub: progName(a.command) + ' · :' + a.port, fg: dt.fg, label: dt.label, app: true, illo: this.illo(this.appIllo(a)) };
    });
    const wpDot = dot(wpUnit);
    flowRoutes.push({ match: flowRoutes.length ? 'everything else' : '/*', name: 'WordPress', sub: 'FrankenPHP · :80 · ' + (php.workers || 2) + '–' + (php.max_workers || 4) + ' PHP threads', fg: wpDot.fg, label: wpDot.label, app: false, illo: this.illo('wordpress') });
    const hostApps = apps.filter(a => a.enabled !== false && a.route === 'hostname').map(a => {
      const u = appUnit(a.name), dt = dot(u);
      return { host: a.hostname, name: a.name, sub: progName(a.command) + ' · :' + a.port, fg: dt.fg, label: dt.label, illo: this.illo(this.appIllo(a)) };
    });
    const dbDot = dot(dbUnit);

    // ── Programs ──
    const editing = s.ctAppEdit;
    const programs = [];
    programs.push({ illo: this.illo('wordpress'), hue: 'var(--brand)', title: 'WordPress', kind: 'FrankenPHP ' + (fp ? fp[1] : '') + ' · PHP ' + (versions.php || php.version || ''),
      fg: wpDot.fg, label: wpDot.label, meta: [up(wpUnit), wpUnit && wpUnit.memory != null ? this.fmtBytes(wpUnit.memory) : '', restarts(wpUnit)].filter(Boolean).join(' · '),
      lines: [
        { k: 'PHP threads', v: (php.workers || 2) + ' to ' + (php.max_workers || 4) + ' · ' + (php.memory_limit || 256) + ' MB each' },
        { k: 'Traffic', v: tr.requests != null ? (tr.per_min + ' requests/min over the last ' + Math.round((tr.window_s || 900) / 60) + ' min') : 'No requests yet' },
        { k: 'Responses', v: tr.requests ? ['2xx', '3xx', '4xx', '5xx'].map(k => (codes[k] || 0) + ' ' + k).join(' · ') + (tr.p95_ms != null ? ' · p95 ' + tr.p95_ms + ' ms' : '') : '—' }
      ],
      logs: logBtn('php'), restart: () => this.uiConfirm('Restart PHP? Requests wait a second or two while FrankenPHP starts again.', { label: 'Restart' }).then(ok => ok && this.containerRestart('php', 'PHP')),
      canEdit: false, edit: () => {}, hasEditor: false });
    apps.forEach((a, i) => {
      const u = appUnit(a.name), dt = a.enabled === false ? { fg: 'var(--ink-dim)', label: 'Turned off' } : dot(u);
      const listens = listening.includes(String(a.port));
      programs.push({ illo: this.illo(this.appIllo(a)), hue: 'var(--acc-domains)', title: a.name, kind: 'App · ' + progName(a.command),
        fg: dt.fg, label: dt.label, meta: [up(u), u && u.memory != null ? this.fmtBytes(u.memory) : '', restarts(u)].filter(Boolean).join(' · '),
        lines: [
          { k: 'Route', v: a.route === 'hostname' ? a.hostname + ' → :' + a.port : a.path + (a.path === '/' ? '' : '/*') + ' → :' + a.port + (a.strip_prefix ? ' (prefix removed)' : '') },
          { k: 'Command', v: a.command, mono: true },
          { k: 'Port', v: a.enabled === false ? 'Off' : (listens ? ':' + a.port + ' listening' : ':' + a.port + ' not listening yet') },
          { k: 'Memory', v: (u && u.memory != null ? this.fmtBytes(u.memory) : '—') + ' of a ' + (a.memory_mb || 256) + ' MB cap' }
        ],
        logs: logBtn('app-' + a.name), restart: () => this.uiConfirm('Restart ' + a.name + '? Its connections drop and reconnect.', { label: 'Restart' }).then(ok => ok && this.containerRestart('app-' + a.name, a.name)),
        canEdit: true, edit: () => { this._ctAppDraft = this.containerAppForm(a); this.setState({ ctAppEdit: i }); }, hasEditor: editing === i });
    });
    programs.push({ illo: this.illo('database'), hue: 'var(--acc-reports)', title: 'MariaDB', kind: 'MariaDB ' + (maria ? maria[1] : ''),
      fg: dbDot.fg, label: dbDot.label, meta: [up(dbUnit), dbUnit && dbUnit.memory != null ? this.fmtBytes(dbUnit.memory) : '', restarts(dbUnit)].filter(Boolean).join(' · '),
      lines: [
        { k: 'Database', v: (d.db && d.db.bytes != null ? this.fmtBytes(d.db.bytes) : '—') + ' · wordpress' },
        { k: 'Connections', v: d.db && d.db.connections != null ? String(d.db.connections) : '—' },
        { k: 'Reachable', v: 'Only inside this container (127.0.0.1:3306)' }
      ],
      logs: logBtn('database'), restart: () => this.uiConfirm('Restart MariaDB? The site shows database errors for a few seconds.', { label: 'Restart' }).then(ok => ok && this.containerRestart('database', 'MariaDB')),
      canEdit: false, edit: () => {}, hasEditor: false });
    programs.forEach(p => { p.hasMeta = !!p.meta; p.lines.forEach(l => { l.font = l.mono ? 'var(--mono)' : 'var(--sans)'; }); });

    const sshU = unit('ssh'), timerU = unit('cron-timer'), cronU = unit('cron');
    const background = [
      { k: 'SSH', ico: this.icon('key'), v: (dot(sshU).label) + ' · as site on port ' + (c.ssh_port || '—'), fg: dot(sshU).fg, logs: logBtn('ssh') },
      { k: 'WP-Cron', ico: this.icon('clock'), v: (timerU && timerU.active === 'active' ? 'Every minute' : 'Timer off') + (cronU && cronU.last_exit ? ' · last ran ' + (since(cronU.last_exit) < 60 ? 'just now' : this.fmtDuration(since(cronU.last_exit)) + ' ago') + (cronU.result && cronU.result !== 'success' ? ' (' + cronU.result + ')' : '') : ''), fg: dot(timerU).fg, logs: logBtn('cron') }
    ];

    // ── App editor ──
    const f = this._ctAppDraft;
    const isNew = editing === 'new';
    const setF = (k, v) => { this._ctAppDraft = { ...this._ctAppDraft, [k]: v }; this.setState({}); };
    const editorVals = f ? {
      cxEdTitle: isNew ? 'Add an app' : 'Edit ' + (apps[editing] ? apps[editing].name : 'app'),
      cxEdName: f.name, cxEdNameOn: e => setF('name', e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '')),
      cxEdCmd: f.command, cxEdCmdOn: e => setF('command', e.target.value),
      cxEdPort: f.port, cxEdPortOn: e => setF('port', e.target.value.replace(/[^0-9]/g, '')),
      cxEdIsPath: f.route !== 'hostname', cxEdIsHost: f.route === 'hostname',
      cxEdRoutes: [['path', 'A path of the site'], ['hostname', 'Its own hostname']].map(([v, l]) => ({ label: l,
        fg: f.route === v ? 'var(--ink)' : 'var(--ink-dim)', bg: f.route === v ? 'var(--panel-2)' : 'transparent', go: () => setF('route', v) })),
      cxEdPath: f.path, cxEdPathOn: e => setF('path', e.target.value),
      cxEdStripMark: f.strip_prefix ? '✓' : '', cxEdStrip: () => setF('strip_prefix', !f.strip_prefix),
      cxEdHost: f.hostname, cxEdHostOn: e => setF('hostname', e.target.value),
      cxEdMem: f.memory, cxEdMemOn: e => setF('memory', e.target.value.replace(/[^0-9]/g, '')),
      cxEdMemMax: 'Up to ' + Math.max(64, (cfg.memory_mb || 1024) - 512) + ' MB (the site has ' + (cfg.memory_mb || 1024) + ' MB; 512 MB stay for PHP and MariaDB)',
      cxEdEnvOn: e => { this._ctAppDraft = { ...this._ctAppDraft, env: e.target.value }; },
      cxEdEnvRef: el => { if (el && document.activeElement !== el && el.value !== f.env) el.value = f.env; },
      cxEdEnabledMark: f.enabled ? '✓' : '', cxEdEnabled: () => setF('enabled', !f.enabled),
      cxEdCancel: () => { this._ctAppDraft = null; this.setState({ ctAppEdit: null }); },
      cxEdCanRemove: !isNew,
      cxEdRemove: () => this.uiConfirm('Remove ' + apps[editing].name + '? Its service stops and its route is removed. Files in ~/apps/' + apps[editing].name + ' stay.', { label: 'Remove' }).then(ok => {
        if (!ok) return;
        const next = apps.filter((_, j) => j !== editing);
        this.containerSaveApps(next, 'Removed app ' + apps[editing].name).then(saved => { if (saved) { this._ctAppDraft = null; this.setState({ ctAppEdit: null }); } });
      }),
      cxEdSave: () => {
        const app = this.containerAppFromForm(this._ctAppDraft || f);
        if (!/^[a-z][a-z0-9-]{0,19}$/.test(app.name)) { this.toast('Name: lowercase letters, digits and dashes, starting with a letter', { kind: 'error' }); return; }
        if (!app.command) { this.toast('Enter the command that starts it', { kind: 'error' }); return; }
        if (!(app.port >= 3000 && app.port <= 3999)) { this.toast('Port must be between 3000 and 3999', { kind: 'error' }); return; }
        const memMax = Math.max(64, (cfg.memory_mb || 1024) - 512);
        if (!(app.memory_mb >= 32 && app.memory_mb <= memMax)) { this.toast('Memory must be between 32 and ' + memMax + ' MB', { kind: 'error' }); return; }
        const next = apps.map(x => ({ ...x }));
        if (isNew) next.push(app); else next[editing] = app;
        this.containerSaveApps(next, (isNew ? 'Added app ' : 'Changed app ') + app.name).then(saved => { if (saved) { this._ctAppDraft = null; this.setState({ ctAppEdit: null }); } });
      },
      cxEdHint: f.route === 'hostname'
        ? 'Point the hostname at this server first (an A record to ' + ((d.server && d.server.address) || 'its IP') + '); the edge gets its certificate on apply.'
        : 'The edge sends ' + ('/' + String(f.path || '').replace(/^\/+|\/+$/g, '')) + ' and everything under it to the app, ahead of redirects and password protection.'
    } : {};

    // ── Web server rules ──
    const rulesTab = s.ctRules === 'container' ? 'container' : 'edge';
    const text = rulesTab === 'edge' ? (d.edge_caddyfile || '(no edge block)') : (d.caddyfile || '(not running)');
    const where = [];
    const has = re => re.test(text);
    if (rulesTab === 'edge') {
      where.push({ rule: 'Site addresses (domains)', place: 'Hosting · Domains', go: hostingOpen });
      if (has(/X-Robots-Tag/)) where.push({ rule: 'header X-Robots-Tag', place: 'Hosting · Tools', go: hostingOpen });
      if (has(/@cc_deny/)) where.push({ rule: 'remote_ip … respond 403', place: 'Hosting · Access', go: hostingOpen });
      if (has(/@cc_app_|reverse_proxy [\d.]+:3\d{3}/)) where.push({ rule: 'App routes (@cc_app_…)', place: 'Apps on this page', go: () => { const el = document.getElementById('cx-programs'); if (el) el.scrollIntoView({ behavior: 'smooth' }); } });
      if (has(/\bredir\b/)) where.push({ rule: 'redir', place: 'Hosting · Redirects', go: hostingOpen });
      if (has(/basicauth/)) where.push({ rule: 'basicauth', place: 'Hosting · Access', go: hostingOpen });
      where.push({ rule: 'reverse_proxy …:80', place: 'Always: the container\'s WordPress', go: null });
    } else {
      where.push({ rule: 'num_threads / max_threads', place: 'Hosting · PHP (threads)', go: hostingOpen });
      where.push({ rule: 'trusted_proxies, log, php_server', place: 'Fixed for every site', go: null });
    }
    where.forEach(w => { w.link = !!w.go; w.plain = !w.go; w.go = w.go || (() => {}); });

    // ── Charts ──
    const h = d.history || [], t = h.map(r => r.t), v = st.v;
    const memMax = c.mem_limit || Math.max(1, ...h.map(r => r.mem));

    // ── Logs ──
    const logSrcs = [['php', 'WordPress (PHP)'], ['database', 'MariaDB'], ['ssh', 'SSH'], ['cron', 'WP-Cron']].concat(apps.map(a => ['app-' + a.name, a.name]));
    const curLog = s.ctLogSrc || '', log = this._ctLog;

    const lim = c.limits || {};
    const status = c.status || '';
    return { ...base, cxReady: true, ...editorVals,
      cxStatus: status, cxStatusFg: status === 'Running' ? 'var(--ok)' : 'var(--warn)',
      cxSub: [c.ip, c.ssh_port ? 'SSH ' + c.ssh_port : '', c.started_at && status === 'Running' ? 'up ' + this.fmtDuration(since(c.started_at)) : ''].filter(Boolean).join(' · '),
      cxSite: site ? site.name + (site.environment && site.environment !== 'Production' ? ' · ' + site.environment.toLowerCase() : '') : 'Not linked to a site',
      cxHasSite: !!site, cxNoSite: !site,
      cxSiteOpen: () => { if (site) { clearTimeout(this._ctTimer); this.setState({ env: site.environment || 'Production' }); this.openSite(String(site.site_id)); } },
      cxHome: site && site.home_url ? site.home_url : '', cxHasHome: !!(site && site.home_url),
      cxHosting: hostingOpen,
      cxImage: [versions.os, fp ? 'FrankenPHP ' + fp[1] : '', cad ? 'Caddy ' + cad[1] : '', versions.php ? 'PHP ' + versions.php : '', maria ? 'MariaDB ' + maria[1] : ''].filter(Boolean).join(' · ') || c.image || '',
      cxFacts: [
        { k: 'Memory', ico: this.icon('memory'), h: 'var(--brand)', pct: c.mem_limit ? Math.min(100, c.mem / c.mem_limit * 100).toFixed(0) + '%' : '0%', v: this.fmtBytes(c.mem) + ' of ' + (c.mem_limit ? gb(c.mem_limit) : '—') },
        { k: 'Disk', ico: this.icon('disk'), h: 'var(--acc-reports)', pct: c.disk_quota ? Math.min(100, c.disk / c.disk_quota * 100).toFixed(0) + '%' : '0%', v: this.fmtBytes(c.disk) + (c.disk_quota ? ' of ' + gb(c.disk_quota) : '') + (c.snapshots_bytes ? ' + ' + this.fmtBytes(c.snapshots_bytes) + ' backups' : '') },
        { k: 'CPU', ico: this.icon('cpu'), h: 'var(--acc-domains)', pct: c.cpu == null ? '0%' : Math.min(100, c.cpu * (lim['limits.cpu'] ? (c.host_cpus || 16) / Number(lim['limits.cpu']) : 1)).toFixed(0) + '%', v: c.cpu == null ? '—' : (c.cpu < 0.1 ? '<0.1%' : c.cpu.toFixed(1) + '%') + ' of the server' + (lim['limits.cpu'] ? ' · capped at ' + lim['limits.cpu'] + ' cores' : lim['limits.cpu.allowance'] ? ' · share ' + lim['limits.cpu.allowance'] : ' · no cap') },
        { k: 'Isolation', ico: this.icon('shield'), h: 'var(--acc-billing)', pct: Math.round([lim['security.idmap.isolated'] === 'true', lim['security.port_isolation'] === 'true', !!lim['limits.processes']].filter(Boolean).length / 3 * 100) + '%', v: [lim['security.idmap.isolated'] === 'true' ? 'own user IDs' : 'shared user IDs', lim['security.port_isolation'] === 'true' ? 'no container-to-container traffic' : 'containers can talk', lim['limits.processes'] ? lim['limits.processes'] + ' processes max' : 'no process limit'].join(' · ') }
      ],
      cxRestartPhp: () => this.uiConfirm('Restart PHP? Requests wait a second or two while FrankenPHP starts again.', { label: 'Restart' }).then(ok => ok && this.containerRestart('php', 'PHP')),
      cxRestartCt: () => this.uiConfirm('Restart the container? The site, its apps and its database are down for about ten seconds.', { label: 'Restart container', danger: true }).then(ok => ok && this.containerRestart('container', 'the container')),
      cxUpdated: 'Updated ' + new Date(st.at).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', second: '2-digit' }),
      // Flow
      cxVisitorIllo: this.illo('visitor'), cxEdgeIllo: this.illo('edge'), cxDbIllo: this.illo('database'),
      cxEdgeRules: edgeRules, cxEdgeHost: d.server ? 'Caddy on ' + d.server.name : 'Edge',
      cxFlowRoutes: flowRoutes, cxHostApps: hostApps, cxHasHostApps: hostApps.length > 0,
      cxDbFg: dbDot.fg, cxDbLabel: dbDot.label, cxDbSize: d.db && d.db.bytes != null ? this.fmtBytes(d.db.bytes) : '',
      // Programs
      cxPrograms: programs, cxBackground: background,
      cxAddApp: () => { this._ctAppDraft = this.containerAppForm({ port: 3000 + apps.length + 1, route: 'path', path: '/' }); this.setState({ ctAppEdit: 'new' }); },
      cxCanAddApp: !!site && apps.length < 5 && editing == null,
      cxEditingNew: editing === 'new' && !!f, cxEditing: editing != null && !!f,
      // Rules
      cxRulesTabs: [['edge', 'Edge'], ['container', 'Container']].map(([k, l]) => ({ label: l,
        fg: rulesTab === k ? 'var(--ink)' : 'var(--ink-dim)', bg: rulesTab === k ? 'var(--panel-2)' : 'transparent', go: () => this.setState({ ctRules: k }) })),
      cxRulesText: text, cxRulesWhere: where,
      cxRulesNote: rulesTab === 'edge'
        ? '/etc/caddy/sites/' + s.ctName + '.caddyfile on ' + ((d.server && d.server.name) || 'the server') + ', written by every apply. Hand edits are overwritten, so change the settings instead:'
        : '/etc/frankenphp/Caddyfile inside the container, written by every apply:',
      // Charts
      cxChartCpu: el => this.srvChart(el, { t, series: [{ name: 'CPU (share of server)', values: h.map(r => r.cpu), color: C1 }], fmt: x => (x < 10 ? x.toFixed(1) : Math.round(x)) + '%' }, v),
      cxChartMem: el => this.srvChart(el, { t, max: memMax, series: [{ name: 'Memory', values: h.map(r => r.mem), color: C1 }], fmt: x => x >= 1073741824 * 0.95 ? (Math.round(x / 107374182.4) / 10) + ' GB' : Math.round(x / 1048576) + ' MB' }, v),
      cxMemNote: c.mem_limit ? 'Scale is the ' + gb(c.mem_limit) + ' limit' : '',
      // Logs
      cxLogSrcs: logSrcs.map(([k, l]) => { const src = 'ct:' + s.ctName + ':' + k; return { label: l,
        fg: curLog === src ? 'var(--brand-ink)' : 'var(--ink-dim)', bg: curLog === src ? 'var(--brand-soft)' : 'transparent', bd: curLog === src ? 'var(--brand)' : 'var(--rule)',
        pick: () => this.loadContainerLog(src) }; }),
      cxLogShow: !!log, cxLogHint: !log,
      cxLogText: !log ? '' : log.loading ? 'Reading…' : log.error ? log.text : !log.text ? 'Nothing logged yet.' : (s.cxLogRaw ? log.text : this.fmtLog(log.text)),
      cxLogRef: el => { this._ctLogEl = el; }, cxLogRefresh: () => { if (curLog) this.loadContainerLog(curLog); },
      // The viewer the Logs buttons open
      cxLogOpen: !!s.cxLogOpen, cxLogClose: () => this.setState({ cxLogOpen: false }),
      cxLogLoading: !!(log && log.loading), cxLogLoaded: !!(log && !log.loading),
      cxLogWait: 'Reading ' + ((logSrcs.find(([k]) => 'ct:' + s.ctName + ':' + k === curLog) || [])[1] || 'the log') + ' from the server. This takes two or three seconds.',
      cxLogTitle: ((logSrcs.find(([k]) => 'ct:' + s.ctName + ':' + k === curLog) || [])[1] || 'Logs') + ' · ' + (s.ctName || ''),
      cxLogMeta: !log || log.loading ? 'Reading the last 300 lines from the server…' : log.error ? '' : (log.text ? log.text.split('\n').length + ' lines, newest last · your local time' : ''),
      cxLogDlgRef: el => { this._ctLogDlgEl = el; },
      cxLogRawLabel: s.cxLogRaw ? 'Readable' : 'Raw', cxLogToggleRaw: () => { this.setState({ cxLogRaw: !s.cxLogRaw }); setTimeout(() => { const el = this._ctLogDlgEl; if (el) el.scrollTop = el.scrollHeight; }, 0); }
    };
  }
});
