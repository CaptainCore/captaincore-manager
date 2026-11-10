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
      { label: 'Add site…', act: () => this.addSiteToServer(r) },
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
        seen: det.last_seen ? 'Checked ' + this.srvAgo(det.last_seen) : 'Not checked yet',
        os: det.os || '', hasOs: !!det.os,
        notes: r.notes || '', hasNotes: !!r.notes,
        sshLabel: s.copied === 'srv-ssh-' + r.server_id ? 'Copied ✓' : 'Copy SSH', hasSsh: !!ssh,
        copySsh: () => copy(ssh, 'srv-ssh-' + r.server_id),
        edit: () => this.openServerDialog(r),
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
