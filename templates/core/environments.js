// CaptainCore v3 — a site's environments (mixin): Add environment.
// A site can have any number of environments. Operators add one on one of
// our servers (POST /sites/{id}/environments-on-server: a copy of another
// environment's latest backup, built by the `host site create
// <id> --environment=<name>` job it returns), whatever hosts the site
// itself; a Kinsta site's premium environments are linked from Kinsta
// (POST /sites/{id}/remote-sync, which adds the ones not tracked yet).

Object.assign(Component.prototype, {

  envIsOp() {
    return ((window.CC_BOOT && window.CC_BOOT.dcRole) || 'operator') === 'operator';
  },

  // The next name not taken: Development, then Dev2, Dev3…
  envNextName(real) {
    const taken = new Set((real.envs || []).map(e => String(e.environment).toLowerCase()));
    if (!taken.has('development')) return 'Development';
    for (let i = 2; i < 100; i++) if (!taken.has('dev' + i)) return 'Dev' + i;
    return '';
  },

  openAddEnv() {
    const real = this._detail;
    if (!real || !real.envs) return;
    this.loadServers();
    this.setState({ eaOpen: true, eaName: this.envNextName(real), eaServer: '', eaFrom: 'Production', eaCron: false, eaBusy: false, eaLinking: false, eaLinkMsg: '' });
  },

  submitAddEnv() {
    const real = this._detail, s = this.state;
    if (!real || s.eaBusy) return;
    const name = (s.eaName || '').trim();
    if (!/^[A-Za-z][A-Za-z0-9]{0,19}$/.test(name)) { this.toast('An environment name is one word of letters and digits, starting with a letter', { kind: 'error' }); return; }
    if ((real.envs || []).some(e => String(e.environment).toLowerCase() === name.toLowerCase())) { this.toast('This site already has a ' + name + ' environment', { kind: 'error' }); return; }
    const servers = this.rsServers();
    const server = servers.find(x => String(x.server_id) === String(s.eaServer)) || servers[0];
    if (!server) { this.toast(this._srv ? 'No active server to add it to' : 'Still loading the servers, try again in a moment', { kind: this._srv ? 'error' : 'info' }); return; }
    const siteName = (real.site && real.site.name) || '';
    this.setState({ eaBusy: true });
    const tid = this.toast('Adding ' + name + ' to ' + siteName + '…', { kind: 'loading' });
    this.api('/sites/' + real.siteId + '/environments-on-server', { method: 'POST',
      body: { name, server_id: Number(server.server_id), from: s.eaFrom || 'Production', backup_id: 'latest', cron: !!s.eaCron } })
      .then(res => {
        if (!res || res.code) throw new Error((res && res.message) || 'Could not add the environment');
        this.updateToast(tid, res.environment + ' added on ' + server.name + '; restoring ' + (s.eaFrom || 'Production') + '\'s latest backup into it', { kind: 'success', timeout: 6000 });
        this.setState({ eaOpen: false, eaBusy: false });
        const siteId = real.siteId, envName = res.environment;
        this.startJob({ label: 'environment', target: envName + ' for ' + siteName + ' on ' + server.name, command: 'host site create', siteId,
          environment: envName, expand: true, dispatch: () => Promise.resolve(res),
          onFinish: () => { this._detail = null; this.loadSiteDetail(siteId); this.setState({ env: envName }); } });
        // The new row exists already (being built): show its tab now.
        this._detail = null; this.loadSiteDetail(siteId);
      })
      .catch(e => { this.setState({ eaBusy: false }); this.updateToast(tid, (e && e.message) || 'Could not add the environment', { kind: 'error', timeout: 8000 }); });
  },

  // Kinsta: link premium environments not tracked yet (a real remote sync).
  linkHostEnvs() {
    const real = this._detail;
    if (!real || this.state.eaLinking) return;
    this.setState({ eaLinking: true, eaLinkMsg: '' });
    this.api('/sites/' + real.siteId + '/remote-sync', { method: 'POST', body: {} })
      .then(res => {
        const added = ((res && res.changes) || []).filter(c => c.field === 'linked').map(c => c.environment);
        const msg = added.length ? 'Linked ' + added.join(', ') + '.' : (res && res.status === 'error' ? (res.message || 'Kinsta did not answer.') : 'Nothing new at Kinsta: every environment there is linked.');
        if (Array.isArray(res && res.environments) && this._detail === real) real.envs = res.environments;
        this.setState({ eaLinking: false, eaLinkMsg: msg });
        if (added.length) { this._detail = null; this.loadSiteDetail(real.siteId); }
      })
      .catch(e => this.setState({ eaLinking: false, eaLinkMsg: (e && e.message) || 'Could not reach Kinsta.' }));
  },

  envAddVals(s) {
    const real = this._detail;
    const show = this.envIsOp() && !!(real && real.envs && real.site);
    const base = { envAddShow: show, envAddOpenDlg: () => this.openAddEnv() };
    if (!s.eaOpen || !show) return { ...base, eaOpen: false };
    const servers = this.rsServers();
    const picked = servers.find(x => String(x.server_id) === String(s.eaServer)) || servers[0];
    const provider = String(real.site.provider || '');
    const chip = (on) => ({ bd: on ? 'var(--brand)' : 'var(--rule)', bg: on ? 'var(--brand-soft)' : 'var(--paper)', mark: on ? '✓' : '' });
    return {
      ...base, eaOpen: true, eaIllo: this.illo('container'),
      eaTitle: 'Add an environment to ' + (real.site.name || 'this site'),
      eaName: s.eaName || '', onEaName: e => this.setState({ eaName: e.target.value }),
      eaNameHint: 'One word: Development, Testing, Dev2. Gets its own temporary address, container, SSH port and database.',
      eaLoading: !this._srv, eaNoServers: !!this._srv && servers.length === 0,
      eaServers: servers.map(x => ({ label: x.name, desc: (x.sites || 0) + ' sites · ' + (x.address || ''), ...chip(picked && picked.server_id === x.server_id),
        pick: () => this.setState({ eaServer: x.server_id }) })),
      eaFroms: (real.envs || []).filter(e => e.environment).map(e => ({ label: e.environment, desc: e.host === 'captaincore' ? 'on our servers' : (e.host || provider),
        ...chip((s.eaFrom || 'Production') === e.environment), pick: () => this.setState({ eaFrom: e.environment }) })),
      eaCronMark: s.eaCron ? '✓' : '', eaCronBg: s.eaCron ? 'var(--brand)' : 'var(--paper)',
      eaCronToggle: () => this.setState({ eaCron: !s.eaCron }),
      eaIsKinsta: provider === 'kinsta' && !!real.site.provider_site_id,
      eaLinkLabel: s.eaLinking ? 'Checking Kinsta…' : 'Check Kinsta for environments',
      eaLink: () => this.linkHostEnvs(), eaLinkMsg: s.eaLinkMsg || '', eaHasLinkMsg: !!s.eaLinkMsg,
      eaSubmitLabel: s.eaBusy ? 'Adding…' : 'Add environment',
      eaClose: () => this.setState({ eaOpen: false }),
      eaSubmit: () => this.submitAddEnv()
    };
  }

});
