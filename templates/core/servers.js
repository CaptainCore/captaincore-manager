// CaptainCore v3 — Servers (mixin). Operator-only surface for the servers
// CaptainCore runs itself (hosting nodes), as opposed to sites at a host.
//   GET    /servers        [{server_id,name,role,status,address,…,provider,sites}]
//   POST   /servers        { server: {…} }   → the saved row
//   PUT    /servers/{id}   { server: {…} }   → the saved row
//   DELETE /servers/{id}
// Provider connections for the "Rented from" chips come from GET /providers.

Object.assign(Component.prototype, {

  SRV_ROLES: [['node', 'Node'], ['standby', 'Standby'], ['spike', 'Spike']],
  SRV_STATUSES: [['provisioning', 'Provisioning'], ['active', 'Active'], ['draining', 'Draining'], ['maintenance', 'Maintenance'], ['retired', 'Retired']],
  // Provider types that rent out servers or host sites; other connections
  // (DNS, email, analytics) never appear as "Rented from".
  SRV_PROVIDER_TYPES: ['ovh', 'vultr', 'oracle', 'hetzner', 'interserver', 'kinsta', 'rocketdotnet', 'gridpane', 'wpengine'],

  loadServers(force) {
    if (this._srvLoading || (this._srv && !force)) return;
    this._srvLoading = true;
    Promise.allSettled([this.api('/servers'), this.api('/providers'), this.api('/public-keys')]).then(([srv, prov, keys]) => {
      this._srvLoading = false;
      this._srv = {
        list: srv.status === 'fulfilled' && Array.isArray(srv.value) ? srv.value : [],
        providers: prov.status === 'fulfilled' && Array.isArray(prov.value) ? prov.value : [],
        keys: keys.status === 'fulfilled' && keys.value && Array.isArray(keys.value.keys) ? keys.value : { keys: [], environments: [] },
        failed: srv.status !== 'fulfilled' || !Array.isArray(srv.value)
      };
      this.setState({});
    });
  },

  srvStatusColor(status) {
    return ({ active: 'var(--ok)', provisioning: 'var(--brand)', draining: 'var(--warn)', maintenance: 'var(--warn)', retired: 'var(--ink-dim)' })[status] || 'var(--ink-dim)';
  },

  srvAgo(when) {
    if (!when) return '';
    const t = Date.parse(String(when).replace(' ', 'T'));
    if (!t) return String(when);
    const m = Math.max(0, Math.round((Date.now() - t) / 60000));
    return m < 1 ? 'just now' : m < 60 ? m + ' min ago' : m < 1440 ? Math.round(m / 60) + ' h ago' : Math.round(m / 1440) + ' d ago';
  },

  openServerDialog(row) {
    const r = row || {};
    const sp = r.specs || {};
    this.setState({ srvDlgOpen: true, srvEditId: r.server_id || null, srvForm: {
      name: r.name || '', role: r.role || 'node', status: r.status || 'provisioning',
      provider_id: r.provider_id || 0, provider_server_id: r.provider_server_id || '',
      address: r.address || '', address_v6: r.address_v6 || '',
      ssh_user: r.ssh_user || 'root', ssh_port: String(r.ssh_port || 22),
      location: r.location || '', cpu: sp.cpu || '', ram_gb: sp.ram_gb ? String(sp.ram_gb) : '', disk: sp.disk || '',
      max_sites: r.max_sites ? String(r.max_sites) : '', monthly_cost: r.monthly_cost ? String(r.monthly_cost) : '',
      notes: r.notes || ''
    } });
  },

  saveServer() {
    const f = this.state.srvForm || {};
    if (!(f.name || '').trim()) { this.toast('Server name is required', { kind: 'error' }); return; }
    const server = {
      name: f.name.trim(), role: f.role, status: f.status,
      provider_id: Number(f.provider_id) || 0, provider_server_id: (f.provider_server_id || '').trim(),
      address: (f.address || '').trim(), address_v6: (f.address_v6 || '').trim(),
      ssh_user: (f.ssh_user || '').trim() || 'root', ssh_port: Number(f.ssh_port) || 22,
      location: (f.location || '').trim(),
      specs: { cpu: (f.cpu || '').trim(), ram_gb: Number(f.ram_gb) || 0, disk: (f.disk || '').trim() },
      max_sites: Number(f.max_sites) || 0, monthly_cost: Number(f.monthly_cost) || 0,
      notes: f.notes || ''
    };
    const id = this.state.srvEditId;
    const tid = this.toast(id ? 'Saving server…' : 'Adding server…', { kind: 'loading' });
    const req = id ? this.api('/servers/' + id, { method: 'PUT', body: { server } }) : this.api('/servers', { method: 'POST', body: { server } });
    req.then(res => {
      if (!res || res.code) { this.updateToast(tid, (res && res.message) || 'Could not save the server', { kind: 'error' }); return; }
      this.updateToast(tid, id ? 'Server saved' : 'Server added', { kind: 'success' });
      this.setState({ srvDlgOpen: false });
      this.loadServers(true);
    }).catch(e => this.updateToast(tid, (e && e.message) || 'Could not save the server', { kind: 'error' }));
  },

  async deleteServer() {
    const id = this.state.srvEditId;
    const f = this.state.srvForm || {};
    if (!id || !(await this.uiConfirm('Remove ' + (f.name || 'this server') + ' from CaptainCore? The machine itself is not touched.', { label: 'Remove server' }))) return;
    const tid = this.toast('Removing server…', { kind: 'loading' });
    this.api('/servers/' + id, { method: 'DELETE' })
      .then(() => { this.updateToast(tid, 'Server removed', { kind: 'success' }); this.setState({ srvDlgOpen: false }); this.loadServers(true); })
      .catch(() => this.updateToast(tid, 'Could not remove the server', { kind: 'error' }));
  },

  // Right-click (or ⋯) menu on a server card.
  serverCtxEntries(r, ssh) {
    const setStatus = status => {
      const tid = this.toast('Marking ' + r.name + ' ' + status + '…', { kind: 'loading' });
      this.api('/servers/' + r.server_id, { method: 'PUT', body: { server: { status } } })
        .then(res => { if (!res || res.code) throw new Error((res && res.message) || 'failed');
          this.updateToast(tid, r.name + ' is ' + status, { kind: 'success' }); this.loadServers(true); })
        .catch(e => this.updateToast(tid, (e && e.message) || 'Could not change the status', { kind: 'error' }));
    };
    const statusEntries = this.SRV_STATUSES.filter(([v]) => v !== r.status && v !== 'retired')
      .map(([v, label]) => ({ label: 'Mark ' + label.toLowerCase(), act: () => setStatus(v) }));
    return [
      { label: 'View details', act: () => this.openServer(r.server_id) },
      { label: 'Add site…', act: () => this.addSiteToServer(r) },
      { label: 'Show sites', act: () => { this.setState({ q: '', fProv: 'Any', fServer: r.name, fNet: 'Any', fCore: 'Any', fBackup: 'Any', fUnassigned: false, fRemoved: false }); this.go('sites')(); } },
      { label: 'Edit server…', act: () => this.openServerDialog(r) },
      ...(ssh ? [{ label: 'Copy SSH command', act: () => this.ctxCopy(ssh, 'SSH command') }] : []),
      ...(r.address ? [{ label: 'Copy IPv4', act: () => this.ctxCopy(r.address, 'IPv4') }] : []),
      ...statusEntries,
      { label: 'Remove server…', danger: true, act: () => { this.openServerDialog(r); setTimeout(() => this.deleteServer(), 0); } }
    ];
  },

  // Placing a site on a node needs the provisioning the first site is
  // being built with by hand (container, volumes, edge route). Until that
  // is scripted this says so instead of pretending.
  addSiteToServer(r) {
    this.toast('Adding a site to ' + r.name + ' is the next slice: it will run the same steps the first site is set up with by hand.', { kind: 'info', timeout: 7000 });
  },

  // ── Team SSH keys: replicated by `captaincore ssh-keys deploy` into a
  // managed block of authorized_keys on every site on our servers. Adding
  // or removing one redeploys right away (the Manager starts the job).
  addTeamKey() {
    const title = (this.state.tkTitle || '').trim();
    const key = (this.state.tkKey || '').trim();
    if (!key) { this.toast('Paste a public key (the .pub file)', { kind: 'error' }); return; }
    const tid = this.toast('Adding key…', { kind: 'loading' });
    this.api('/public-keys', { method: 'POST', body: { title, public_key: key } }).then(res => {
      if (!res || res.code) { this.updateToast(tid, (res && res.message) || 'Could not add the key', { kind: 'error' }); return; }
      this.updateToast(tid, res.token ? 'Key added, deploying to your servers' : 'Key added. The CLI server did not answer, so deploy later', { kind: res.token ? 'success' : 'info' });
      this.setState({ tkTitle: '', tkKey: '', tkOpen: false });
      this.loadServers(true); setTimeout(() => this.loadServers(true), 8000);
    }).catch(e => this.updateToast(tid, (e && e.message) || 'Could not add the key', { kind: 'error' }));
  },

  async removeTeamKey(k) {
    if (!(await this.uiConfirm('Remove ' + k.title + '? It is taken off every site on your servers.', { label: 'Remove key' }))) return;
    const tid = this.toast('Removing key…', { kind: 'loading' });
    this.api('/public-keys/' + k.public_key_id, { method: 'DELETE' })
      .then(res => { if (!res || res.code) throw new Error((res && res.message) || 'failed');
        this.updateToast(tid, 'Key removed, deploying to your servers', { kind: 'success' });
        this.loadServers(true); setTimeout(() => this.loadServers(true), 8000); })
      .catch(e => this.updateToast(tid, (e && e.message) || 'Could not remove the key', { kind: 'error' }));
  },

  deployTeamKeys() {
    const tid = this.toast('Deploying keys…', { kind: 'loading' });
    this.api('/public-keys/deploy', { method: 'POST' })
      .then(res => { if (!res || !res.token) throw new Error('The CLI server did not answer');
        this.updateToast(tid, 'Deploy started', { kind: 'success' }); setTimeout(() => this.loadServers(true), 8000); })
      .catch(e => this.updateToast(tid, (e && e.message) || 'Could not start the deploy', { kind: 'error' }));
  },

  teamKeyVals(s) {
    const data = (this._srv && this._srv.keys) || { keys: [], environments: [] };
    const envs = data.environments || [];
    const current = envs.filter(e => e.current).length;
    const stale = envs.filter(e => !e.current);
    return {
      tkRows: data.keys.map(k => ({ title: k.title, fp: k.fingerprint, type: k.key_type,
        meta: [k.added_by, k.created_at ? 'added ' + this.srvAgo(k.created_at) : ''].filter(Boolean).join(' · '),
        remove: () => this.removeTeamKey(k) })),
      tkEmpty: data.keys.length === 0,
      tkStatus: envs.length ? 'Current on ' + current + ' of ' + envs.length + (envs.length === 1 ? ' site' : ' sites') : 'No sites on your servers yet',
      tkStatusFg: envs.length && current < envs.length ? 'var(--warn)' : 'var(--ink-dim)',
      tkStale: stale.length ? 'Waiting: ' + stale.slice(0, 6).map(e => e.site + (e.environment !== 'Production' ? '-' + e.environment.toLowerCase() : '')).join(', ') + (stale.length > 6 ? ' and ' + (stale.length - 6) + ' more' : '') : '',
      tkHasStale: stale.length > 0,
      tkOpen: !!s.tkOpen, tkClosed: !s.tkOpen,
      openTk: () => this.setState({ tkOpen: true }), closeTk: () => this.setState({ tkOpen: false }),
      tkTitle: s.tkTitle || '', onTkTitle: e => this.setState({ tkTitle: e.target.value }),
      tkKey: s.tkKey || '', onTkKey: e => this.setState({ tkKey: e.target.value }),
      addTeamKey: () => this.addTeamKey(), deployTeamKeys: () => this.deployTeamKeys()
    };
  },

  computeServers(s) {
    if (s.route === 'servers' && !this._srv && !this._srvLoading) setTimeout(() => this.loadServers(), 0);
    const srv = this._srv;
    const list = srv ? srv.list : [];
    const sites = list.reduce((n, r) => n + (r.sites || 0), 0);
    const capacity = list.filter(r => r.status !== 'retired').reduce((n, r) => n + (r.max_sites || 0), 0);
    const cost = list.filter(r => r.status !== 'retired').reduce((n, r) => n + (r.monthly_cost || 0), 0);
    const money = n => '$' + (Math.round(n * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 });
    const copy = (text, key) => {
      try { navigator.clipboard.writeText(text); } catch (e) {}
      this.setState({ copied: key }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400);
    };
    const rows = list.map(r => {
      const sp = r.specs || {};
      const det = r.details || {};
      const specs = [sp.cpu, sp.ram_gb ? sp.ram_gb + ' GB RAM' : '', sp.disk].filter(Boolean).join(' · ');
      const pct = r.max_sites ? Math.min(100, Math.round((r.sites || 0) / r.max_sites * 100)) : 0;
      const ssh = r.address ? 'ssh ' + (r.ssh_user || 'root') + '@' + r.address + (r.ssh_port && r.ssh_port !== 22 ? ' -p ' + r.ssh_port : '') : '';
      const label = (this.SRV_STATUSES.find(x => x[0] === r.status) || [0, r.status])[1];
      const role = (this.SRV_ROLES.find(x => x[0] === r.role) || [0, r.role])[1];
      const where = [r.provider ? r.provider.name : '', r.location].filter(Boolean).join(' · ') || 'No provider set';
      return {
        id: r.server_id, name: r.name, statusLabel: label, roleLabel: role,
        dot: this.srvStatusColor(r.status), where, specs: specs || 'No specs recorded', providerRef: r.provider_server_id || '',
        hasProviderRef: !!r.provider_server_id,
        address: r.address || 'No IP', hasAddress: !!r.address, v6: r.address_v6 || '', hasV6: !!r.address_v6,
        sitesLabel: r.max_sites ? (r.sites || 0) + ' of ' + r.max_sites + ' sites' : (r.sites || 0) + ' sites',
        barW: pct + '%', barShow: !!r.max_sites,
        cost: r.monthly_cost ? money(r.monthly_cost) + '/mo' : '',
        seen: det.last_seen ? 'Checked ' + this.srvAgo(det.last_seen) + (det.cpu != null ? ' · CPU ' + det.cpu + '%' : '') + (det.pool_cap !== undefined && det.pool_cap !== '' ? ' · disk ' + String(det.pool_cap).replace(/%?$/, '%') : '') + (det.reboot_required ? ' · reboot required' : '') + (det.updates_pending ? ' · ' + det.updates_pending + ' updates pending' : '') : 'Not checked yet · open the server to check it',
        os: det.os || '', hasOs: !!det.os,
        notes: r.notes || '', hasNotes: !!r.notes,
        sshLabel: s.copied === 'srv-ssh-' + r.server_id ? 'Copied ✓' : 'Copy SSH', hasSsh: !!ssh,
        copySsh: () => copy(ssh, 'srv-ssh-' + r.server_id),
        // Opens Sites with only this server's sites (the Server filter).
        openSites: () => { this.setState({ q: '', fProv: 'Any', fServer: r.name, fNet: 'Any', fCore: 'Any', fBackup: 'Any', fUnassigned: false, fRemoved: false }); this.go('sites')(); },
        sitesTitle: 'Show these sites in Sites',
        edit: () => this.openServerDialog(r),
        open: () => this.openServer(r.server_id),
        ctx: e => this.openCtxMenu(e, this.serverCtxEntries(r, ssh))
      };
    });
    const summary = srv ? [list.length === 1 ? '1 server' : list.length + ' servers',
      capacity ? sites + ' of ' + capacity + ' sites placed' : sites + ' sites placed', cost ? money(cost) + '/mo' : ''].filter(Boolean).join(' · ') : 'Loading…';
    return {
      srvSummary: summary,
      srvRows: rows,
      srvEmpty: !!srv && !srv.failed && list.length === 0,
      srvFailed: !!srv && srv.failed,
      srvLoading: !srv,
      addServer: () => this.openServerDialog(null),
      ...this.teamKeyVals(s),
      ...this.serverDialogVals(s)
    };
  },

  serverDialogVals(s) {
    const f = s.srvForm || {};
    const set = (key, val) => this.setState(st => ({ srvForm: { ...(st.srvForm || {}), [key]: val } }));
    const field = key => ({ val: f[key] == null ? '' : f[key], on: e => set(key, e.target.value) });
    const chips = (opts, cur, key) => opts.map(([val, label]) => ({ label,
      fg: String(cur) === String(val) ? 'var(--brand-ink)' : 'var(--ink-dim)',
      bg: String(cur) === String(val) ? 'var(--brand-soft)' : 'transparent',
      bd: String(cur) === String(val) ? 'var(--brand)' : 'var(--rule)',
      pick: () => set(key, val) }));
    const providers = ((this._srv && this._srv.providers) || []).filter(p => this.SRV_PROVIDER_TYPES.includes(String(p.provider || '').toLowerCase()));
    const vals = {
      srvDlgOpen: !!s.srvDlgOpen, srvDlgEditing: !!s.srvEditId,
      srvDlgTitle: s.srvEditId ? 'Edit server' : 'Add server',
      srvRoleChips: chips(this.SRV_ROLES, f.role, 'role'),
      srvStatusChips: chips(this.SRV_STATUSES, f.status, 'status'),
      srvProviderChips: chips([[0, 'None'], ...providers.map(p => [p.provider_id, p.name || p.provider])], f.provider_id || 0, 'provider_id'),
      srvNoHostProviders: providers.length === 0,
      closeServer: () => this.setState({ srvDlgOpen: false }),
      saveServer: () => this.saveServer(),
      deleteServer: () => this.deleteServer()
    };
    ['name', 'provider_server_id', 'address', 'address_v6', 'ssh_user', 'ssh_port', 'location', 'cpu', 'ram_gb', 'disk', 'max_sites', 'monthly_cost', 'notes'].forEach(k => {
      const fl = field(k);
      vals['srvF_' + k] = fl.val;
      vals['srvOn_' + k] = fl.on;
    });
    return vals;
  }
});

// ── Server detail (/account/servers/<id>) ─────────────────────────────────
// GET /servers/{id}/stats?range=1h|24h|7d → { now, system, containers,
// history, server } from the node (CLI `node stats`). Refreshes every 30 s
// while open. Charts are inline SVG drawn into refs (srvChart), one series in
// --chart-1 or two in --chart-1/--chart-2 (validated: blue/orange pass every
// palette check on both surfaces), with a crosshair + tooltip listing every
// series at the hovered time; values are inserted with textContent.
Object.assign(Component.prototype, {

  openServer(id) {
    this.setState({ route: 'server', serverId: String(id), srvRange: this.state.srvRange || '1h' });
    this._srvStats = null;
    this.loadServerStats();
  },

  loadServerStats() {
    const id = this.state.serverId, range = this.state.srvRange || '1h';
    if (!id) return;
    const key = id + '|' + range;
    clearTimeout(this._srvStatsTimer);
    this._srvStatsLoading = key;
    this.setState({});
    this.api('/servers/' + id + '/stats?range=' + range).then(res => {
      if (this._srvStatsLoading !== key) return;
      this._srvStatsLoading = null;
      this._srvStats = (!res || res.code) ? { key, error: (res && res.message) || 'Could not read the server' } : { key, data: res, at: Date.now(), v: Date.now() };
      this.setState({});
      if (this.state.route === 'server' && String(this.state.serverId) === String(id)) this._srvStatsTimer = setTimeout(() => this.loadServerStats(), 30000);
    }).catch(() => { this._srvStatsLoading = null; this._srvStats = { key, error: 'Could not read the server' }; this.setState({}); });
  },

  fmtBytes(n, digits) {
    if (n == null || isNaN(n)) return '—';
    const u = ['B', 'KB', 'MB', 'GB', 'TB']; let i = 0; n = Number(n);
    while (n >= 1000 && i < u.length - 1) { n /= 1000; i++; }
    return (i === 0 ? Math.round(n) : n.toFixed(digits == null ? (n < 10 ? 1 : 0) : digits)) + ' ' + u[i];
  },

  fmtDuration(s) {
    s = Math.max(0, Math.floor(s || 0));
    const d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
    return d ? d + 'd ' + h + 'h' : h ? h + 'h ' + m + 'm' : m + 'm';
  },

  // Draw one time-series chart into el. spec: { series: [{ name, values, color }],
  // max, fmt, stacked }. Points come from history (t = unix seconds).
  srvChart(el, spec, version) {
    if (!el || el._ccV === version) return;
    el._ccV = version;
    while (el.firstChild) el.removeChild(el.firstChild);
    const NS = 'http://www.w3.org/2000/svg';
    const pts = spec.t;
    if (!pts.length) { const p = document.createElement('div'); p.style.cssText = 'height:150px;display:grid;place-items:center;font:400 var(--fs-125) var(--sans);color:var(--ink-dim)'; p.textContent = 'Collecting: the first samples arrive within a minute or two.'; el.appendChild(p); return; }
    const W = 560, H = 150, L = 44, R = 10, T = 8, B = 22;
    const t0 = pts[0], t1 = pts[pts.length - 1] === t0 ? t0 + 60 : pts[pts.length - 1];
    const top = spec.stacked ? spec.series.reduce((a, s) => a.map((v, i) => v + (s.values[i] || 0)), pts.map(() => 0)) : null;
    let max = spec.max || Math.max(1, ...spec.series.flatMap(s => s.values), ...(top || []));
    if (!spec.max) { const mag = Math.pow(10, Math.floor(Math.log10(max))); max = Math.ceil(max / mag * 1.15) * mag; }
    const x = t => L + (t - t0) / (t1 - t0) * (W - L - R), y = v => T + (1 - Math.min(v, max) / max) * (H - T - B);
    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', `0 0 ${W} ${H}`); svg.setAttribute('width', '100%'); svg.style.display = 'block'; svg.style.overflow = 'visible';
    const mk = (tag, attrs) => { const n = document.createElementNS(NS, tag); for (const k in attrs) n.setAttribute(k, attrs[k]); return n; };
    // Recessive grid + y labels (0, half, max).
    [0, 0.5, 1].forEach(f => {
      svg.appendChild(mk('line', { x1: L, x2: W - R, y1: y(max * f), y2: y(max * f), stroke: 'var(--rule)', 'stroke-width': 1 }));
      const tx = mk('text', { x: L - 6, y: y(max * f) + 4, 'text-anchor': 'end', fill: 'var(--ink-dim)', 'font-size': 10, 'font-family': 'var(--sans)' });
      tx.textContent = spec.fmt(max * f, true); svg.appendChild(tx);
    });
    // X labels: start and end.
    const tl = d => new Date(d * 1000).toLocaleString(undefined, (t1 - t0) > 86400 * 1.5 ? { month: 'short', day: 'numeric' } : { hour: 'numeric', minute: '2-digit' });
    [[t0, 'start'], [t1, 'end']].forEach(([t, a]) => { const tx = mk('text', { x: x(t), y: H - 6, 'text-anchor': a, fill: 'var(--ink-dim)', 'font-size': 10, 'font-family': 'var(--sans)' }); tx.textContent = tl(t); svg.appendChild(tx); });
    // Series: stacked areas (2px surface gap via stroke) or 2px lines.
    let base = pts.map(() => 0);
    spec.series.forEach(s => {
      const upper = spec.stacked ? s.values.map((v, i) => base[i] + (v || 0)) : s.values;
      const line = pts.map((t, i) => `${i ? 'L' : 'M'}${x(t).toFixed(1)},${y(upper[i] || 0).toFixed(1)}`).join('');
      if (spec.stacked || spec.series.length === 1) {
        const lower = spec.stacked ? base : pts.map(() => 0);
        const area = line + pts.slice().reverse().map((t, j) => `L${x(t).toFixed(1)},${y(lower[pts.length - 1 - j]).toFixed(1)}`).join('') + 'Z';
        svg.appendChild(mk('path', { d: area, fill: s.color, 'fill-opacity': spec.stacked ? 0.85 : 0.14, stroke: spec.stacked ? 'var(--paper)' : 'none', 'stroke-width': spec.stacked ? 2 : 0 }));
      }
      if (!spec.stacked) svg.appendChild(mk('path', { d: line, fill: 'none', stroke: s.color, 'stroke-width': 2, 'stroke-linejoin': 'round' }));
      if (spec.stacked) base = upper;
    });
    // Crosshair + tooltip.
    const cross = mk('line', { y1: T, y2: H - B, stroke: 'var(--ink-dim)', 'stroke-width': 1, opacity: 0 });
    svg.appendChild(cross);
    const hit = mk('rect', { x: L, y: 0, width: W - L - R, height: H, fill: 'transparent' });
    svg.appendChild(hit);
    el.style.position = 'relative';
    el.appendChild(svg);
    const tip = document.createElement('div');
    tip.style.cssText = 'position:absolute;pointer-events:none;opacity:0;background:var(--paper);border:1px solid var(--rule);border-radius:8px;box-shadow:var(--shadow);padding:6px 9px;font:400 var(--fs-12) var(--sans);color:var(--ink);white-space:nowrap;z-index:5';
    el.appendChild(tip);
    const show = ev => {
      const r = svg.getBoundingClientRect(), sx = (ev.clientX - r.left) / r.width * W;
      const tt = t0 + (sx - L) / (W - L - R) * (t1 - t0);
      let i = 0; for (let k = 1; k < pts.length; k++) if (Math.abs(pts[k] - tt) < Math.abs(pts[i] - tt)) i = k;
      cross.setAttribute('x1', x(pts[i])); cross.setAttribute('x2', x(pts[i])); cross.setAttribute('opacity', 0.6);
      while (tip.firstChild) tip.removeChild(tip.firstChild);
      const h = document.createElement('div'); h.style.cssText = 'color:var(--ink-dim);margin-bottom:3px'; h.textContent = new Date(pts[i] * 1000).toLocaleString(); tip.appendChild(h);
      spec.series.forEach(s => {
        const row = document.createElement('div'); row.style.cssText = 'display:flex;align-items:center;gap:7px';
        const key = document.createElement('span'); key.style.cssText = `width:12px;height:2px;background:${s.color};border-radius:1px`;
        const v = document.createElement('b'); v.style.cssText = 'font-weight:600'; v.textContent = spec.fmt(s.values[i] || 0);
        const n = document.createElement('span'); n.style.cssText = 'color:var(--ink-dim)'; n.textContent = s.name;
        row.appendChild(key); row.appendChild(v); row.appendChild(n); tip.appendChild(row);
      });
      tip.style.opacity = 1;
      const px = x(pts[i]) / W * r.width;
      tip.style.left = Math.min(Math.max(0, px + 10), r.width - tip.offsetWidth) + 'px'; tip.style.top = '4px';
    };
    hit.addEventListener('pointermove', show);
    hit.addEventListener('pointerleave', () => { tip.style.opacity = 0; cross.setAttribute('opacity', 0); });
  },

  loadServerLog(source) {
    const id = this.state.serverId;
    this.setState({ srvLogSrc: source });
    this._srvLog = { source, loading: true, text: '' };
    this.setState({});
    this.api('/servers/' + id + '/logs?source=' + encodeURIComponent(source) + '&lines=300').then(res => {
      if (!this._srvLog || this._srvLog.source !== source) return;
      this._srvLog = { source, text: (!res || res.code) ? ((res && res.message) || 'Could not read the log') : (res.text || '(empty)') };
      this.setState({});
      const el = this._srvLogEl; if (el) setTimeout(() => { el.scrollTop = el.scrollHeight; }, 0);
    }).catch(() => { this._srvLog = { source, text: 'Could not read the log' }; this.setState({}); });
  },

  serverLogVals(d) {
    const sources = [['edge', 'Edge (Caddy)'], ['ssh', 'SSH logins'], ['backups', 'Local backups'], ['updates', 'Automatic updates'], ['kernel', 'Kernel warnings'], ['incus', 'Incus']];
    (d.containers || []).forEach(c => { sources.push(['ct:' + c.name + ':php', c.name + ' · PHP']); sources.push(['ct:' + c.name + ':database', c.name + ' · MariaDB']); });
    const cur = this.state.srvLogSrc || '';
    const log = this._srvLog;
    return {
      sdLogSrcs: sources.map(([v, l]) => ({ label: l, fg: cur === v ? 'var(--brand-ink)' : 'var(--ink-dim)', bg: cur === v ? 'var(--brand-soft)' : 'transparent',
        bd: cur === v ? 'var(--brand)' : 'var(--rule)', pick: () => this.loadServerLog(v) })),
      sdLogShow: !!log, sdLogText: log ? (log.loading ? 'Reading…' : log.text) : '',
      sdLogRef: el => { this._srvLogEl = el; },
      sdLogRefresh: () => { if (cur) this.loadServerLog(cur); },
      sdLogHint: !log
    };
  },

  computeServerDetail(s) {
    if (s.route !== 'server') return { showServer: false };
    const st = this._srvStats, row = st && st.data ? st.data.server : ((this._srv && this._srv.list) || []).find(r => String(r.server_id) === String(s.serverId));
    const range = s.srvRange || '1h';
    const base = {
      showServer: true,
      sdName: row ? row.name : 'Server', sdBack: () => { clearTimeout(this._srvStatsTimer); this.setState({ route: 'servers' }); this.loadServers(true); },
      sdRanges: [['1h', 'Hour'], ['24h', 'Day'], ['7d', 'Week']].map(([v, l]) => ({ label: l,
        fg: range === v ? 'var(--ink)' : 'var(--ink-dim)', bg: range === v ? 'var(--panel-2)' : 'transparent',
        go: () => { this.setState({ srvRange: v }); setTimeout(() => this.loadServerStats(), 0); } })),
      sdRefresh: () => this.loadServerStats(),
      sdLoading: !st || !!this._srvStatsLoading && !(st && st.data),
      sdUpdating: !!this._srvStatsLoading && !!(st && st.data),
      sdErr: st && st.error ? st.error : '', sdHasErr: !!(st && st.error)
    };
    if (!st || !st.data) return { ...base, sdReady: false };
    const d = st.data, sys = d.system, now = d.now, h = d.history || [];
    const memUsed = r => Math.max(0, r.mem_total - r.mem_avail - (r.arc || 0));
    const C1 = 'var(--chart-1)', C2 = 'var(--chart-2)';
    const pct = v => (Math.round(v * 10) / 10) + '%';
    const t = h.map(r => r.t);
    const v = st.v;
    const pool = sys.pool || {};
    const status = (ok, good, bad) => ({ label: ok ? good : bad, fg: ok ? 'var(--ok)' : 'var(--warn)', icon: ok ? '✓' : '!' });
    const reboot = status(!sys.reboot_required, 'No reboot needed', 'Reboot required');
    const upd = status(!sys.updates_pending, 'Up to date', sys.updates_pending + ' updates pending');
    const poolH = status(pool.health === 'ONLINE', 'Pool healthy', 'Pool ' + (pool.health || 'unknown'));
    const timers = sys.timers || {};
    const lastCpu = h.length ? h[h.length - 1].cpu : now.cpu;
    return { ...base, sdReady: true, ...this.serverLogVals(d),
      sdSub: [row && row.address, sys.os, 'up ' + this.fmtDuration(sys.uptime_s)].filter(Boolean).join(' · '),
      sdUpdated: 'Updated ' + new Date(st.at).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', second: '2-digit' }),
      sdTiles: [
        { k: 'CPU', v: lastCpu == null ? '—' : pct(lastCpu), sub: sys.cpus + ' threads · load ' + (now.load != null ? now.load.toFixed(2) : '—') },
        { k: 'Memory in use', v: this.fmtBytes(memUsed(now)), sub: 'of ' + this.fmtBytes(now.mem_total) + ' · ZFS cache ' + this.fmtBytes(now.arc) },
        { k: 'Disk', v: this.fmtBytes(pool.alloc), sub: 'of ' + this.fmtBytes(pool.size) + ' (' + String(pool.cap || 0).replace(/%?$/, '%') + ') on ' + (pool.name || 'pool') },
        { k: 'Network', v: this.fmtBytes(h.length ? h[h.length - 1].rx_bps : 0) + '/s in', sub: this.fmtBytes(h.length ? h[h.length - 1].tx_bps : 0) + '/s out' },
        { k: 'Containers', v: String((d.containers || []).length), sub: (d.containers || []).filter(c => c.status === 'Running').length + ' running' }
      ],
      sdChartCpu: el => this.srvChart(el, { t, series: [{ name: 'CPU', values: h.map(r => r.cpu), color: C1 }], max: 100, fmt: x => Math.round(x) + '%' }, v),
      sdChartMem: el => this.srvChart(el, { t, stacked: true, max: now.mem_total, fmt: (x, ax) => this.fmtBytes(x, ax ? 0 : 1),
        series: [{ name: 'In use', values: h.map(memUsed), color: C1 }, { name: 'ZFS cache', values: h.map(r => r.arc || 0), color: C2 }] }, v),
      sdChartNet: el => this.srvChart(el, { t, fmt: (x, ax) => this.fmtBytes(x, ax ? 0 : 1) + '/s',
        series: [{ name: 'In', values: h.map(r => r.rx_bps || 0), color: C1 }, { name: 'Out', values: h.map(r => r.tx_bps || 0), color: C2 }] }, v),
      sdChartLoad: el => this.srvChart(el, { t, fmt: x => x.toFixed(x < 10 ? 2 : 0), series: [{ name: 'Load (1 min)', values: h.map(r => r.load), color: C1 }] }, v),
      sdCts: (d.containers || []).map(c => {
        const memPct = c.mem_limit ? Math.min(100, c.mem / c.mem_limit * 100) : 0;
        return { name: c.name, openCt: () => this.openContainer(s.serverId, c.name), site: c.site ? c.site.name + (c.site.environment && c.site.environment !== 'Production' ? ' · ' + c.site.environment.toLowerCase() : '') : 'Not linked to a site',
          siteFg: c.site ? 'var(--brand-ink)' : 'var(--ink-dim)', open: () => { if (c.site) { this.setState({ env: c.site.environment || 'Production' }); this.openSite(String(c.site.site_id)); } },
          status: c.status, statusFg: c.status === 'Running' ? 'var(--ok)' : 'var(--warn)',
          cpu: c.cpu == null ? '—' : (c.cpu < 0.1 ? '<0.1%' : c.cpu.toFixed(1) + '%'),
          mem: this.fmtBytes(c.mem) + (c.mem_limit ? ' / ' + (Math.round(c.mem_limit / 107374182.4) / 10) + ' GB' : ''), memW: memPct.toFixed(0) + '%',
          memBar: memPct > 85 ? 'var(--warn)' : C1,
          disk: this.fmtBytes(c.disk) + (c.snapshots_bytes ? ' + ' + this.fmtBytes(c.snapshots_bytes) + ' backups' : ''),
          ip: c.ip || '—', ssh: c.ssh_port || '—' };
      }),
      sdSys: [
        ['CPU', sys.cpu_model + ' · ' + sys.cpus + ' threads'], ['OS', sys.os], ['Kernel', sys.kernel],
        ['Incus', sys.incus], ['ZFS', sys.zfs + ' · cache capped at ' + (sys.arc_max / 1073741824).toFixed(0) + ' GiB'],
        ['Pool', (pool.name || '') + ' · ' + (pool.health || '') + ' · ' + (pool.frag || '0') + '% fragmented'],
        ['System disk', this.fmtBytes(sys.root_fs && sys.root_fs.free) + ' free of ' + this.fmtBytes(sys.root_fs && sys.root_fs.size)],
        ['Local backups', (timers['cc-backups-daily.timer'] === 'active' ? 'Daily timer on' : 'Daily timer off') + (sys.backups_last_run ? ' · last run ' + sys.backups_last_run : ' · not run yet')],
        ['Metrics', timers['cc-metrics.timer'] === 'active' ? 'Sampled every minute, kept 14 days' : 'Collector off'],
        ['Security updates', timers['unattended-upgrades.service'] === 'active' ? 'Installed automatically' : 'Automatic updates off']
      ].map(([k, val]) => ({ k, v: val || '—' })),
      sdStatus: [poolH, upd, reboot]
    };
  }
});
