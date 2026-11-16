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
    this.setState({ eaOpen: true, eaWhere: this.envCanKinstaStaging(real) ? 'kinsta' : 'ours', eaName: this.envNextName(real), eaServer: '', eaFrom: 'Production', eaCron: false, eaBusy: false, eaLinking: false, eaLinkMsg: '' });
  },

  // A Kinsta site with only production gets Staging from Kinsta itself (the
  // same build as Deploy → Pull production → staging): Kinsta copies one of
  // its environments to another faster than a restore onto our servers.
  envCanKinstaStaging(real) {
    const site = (real && real.site) || {};
    return site.provider === 'kinsta' && !!site.provider_site_id
      && !!(real.envs && real.envs.some(e => e.environment === 'Production'))
      && !(real.envs || []).some(e => String(e.environment).toLowerCase() === 'staging');
  },

  submitAddEnv() {
    const real = this._detail, s = this.state;
    if (!real || s.eaBusy) return;
    if (s.eaWhere === 'kinsta' && this.envCanKinstaStaging(real)) {
      this.setState({ eaOpen: false });
      this.realPullToNewStaging(real);
      return;
    }
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

  // ── Deploy between environments ──────────────────────────────
  // The Manager decides how (captaincore_environment_deploy_plan); the same
  // rule here explains it before anything runs.
  envDeployPlan(real, from, to) {
    if (!real || !from || !to || from === to) return '';
    const host = e => (e && e.host) || (real.site && real.site.provider) || '';
    const f = (real.envs || []).find(e => e.environment === from), t = (real.envs || []).find(e => e.environment === to);
    if (!f || !t) return '';
    // A plain site has no WordPress to copy, and copying WordPress onto one
    // would replace its pages: the Manager refuses both (deploy_plan).
    if (this.envIsPlain(f) || this.envIsPlain(t)) return 'plain';
    if (host(t) === 'captaincore') return host(f) === 'captaincore' && String(f.server_id || '') === String(t.server_id || '') ? 'node' : 'backup';
    if (host(f) === host(t) && host(t) === (real.site && real.site.provider) && host(t) !== 'captaincore') return 'provider';
    return 'none';
  },

  openEnvDeploy() {
    const real = this._detail;
    if (!real || !real.envs || real.envs.length < 2) return;
    const names = real.envs.map(e => e.environment);
    const to = names.find(n => n !== 'Production' && (real.envs.find(e => e.environment === n) || {}).host === 'captaincore') || names[1];
    this.setState({ edOpen: true, edFrom: 'Production', edTo: to, edWhat: 'both', edBusy: false });
  },

  submitEnvDeploy() {
    const real = this._detail, s = this.state;
    if (!real || s.edBusy) return;
    const plan = this.envDeployPlan(real, s.edFrom, s.edTo);
    if (!plan || plan === 'none' || plan === 'plain') return;
    const name = (real.site && real.site.name) || '';
    const label = s.edFrom.toLowerCase() + ' → ' + s.edTo.toLowerCase() + ' on ' + name;
    if (plan === 'provider') {
      const f = real.envs.find(e => e.environment === s.edFrom), t = real.envs.find(e => e.environment === s.edTo);
      this.setState({ edOpen: false });
      this.realPushTo(real, f.environment_id, t.environment_id, label, { siteId: real.siteId, environment: t.environment, name });
      return;
    }
    this.setState({ edBusy: true });
    this.startJob({ label: 'deploy', target: label, command: plan === 'node' ? 'host deploy' : 'host refresh', siteId: real.siteId, environment: s.edTo, expand: true,
      dispatch: () => this.api('/sites/' + real.siteId + '/environments/deploy', { method: 'POST', body: { from: s.edFrom, to: s.edTo, what: plan === 'node' ? s.edWhat : 'both' } })
        .then(res => { if (!res || res.code) throw new Error((res && res.message) || 'Could not start the deploy'); return res; }),
      onFinish: () => { this._detail = null; this.loadSiteDetail(real.siteId); } });
    this.setState({ edOpen: false, edBusy: false });
  },

  envDeployVals(s) {
    const real = this._detail;
    const show = this.envIsOp() && !!(real && real.envs && real.envs.length > 1);
    const base = { envDeployShow: show, envDeployOpen: () => this.openEnvDeploy() };
    if (!s.edOpen || !show) return { ...base, edOpen: false };
    const plan = this.envDeployPlan(real, s.edFrom, s.edTo);
    const provider = (real.site && real.site.provider) || '';
    const where = e => e.host === 'captaincore' ? 'on our servers' : (e.host || provider);
    const chip = on => ({ bd: on ? 'var(--brand)' : 'var(--rule)', bg: on ? 'var(--brand-soft)' : 'var(--paper)', mark: on ? '✓' : '' });
    const pick = (key, name) => () => this.setState(st => {
      const next = { [key]: name };
      if (key === 'edFrom' && st.edTo === name) next.edTo = (real.envs.find(e => e.environment !== name) || {}).environment;
      if (key === 'edTo' && st.edFrom === name) next.edFrom = (real.envs.find(e => e.environment !== name) || {}).environment;
      return next;
    });
    const f = s.edFrom || '', t = s.edTo || '';
    const notes = {
      node: 'Copies ' + f + ' onto ' + t + ' on the same server. A local backup of ' + t + ' is taken first, and ' + t + ' keeps its own address and search engine setting.',
      backup: 'Replaces ' + t + ' with ' + f + '\'s latest backup (files and database). A local backup of ' + t + ' is taken first, and ' + t + ' keeps its own address and settings: back up ' + f + ' first for a fresher copy.',
      provider: ucfirstSafe(provider) + ' pushes ' + f + ' to ' + t + ', the same as a push in its dashboard. It can take several minutes.',
      none: 'Copying from our servers to ' + ucfirstSafe(provider) + ' is not possible yet.',
      plain: (this.envIsPlain(real.envs.find(e => e.environment === f)) ? f : t) + ' is a plain site (HTML files, no WordPress), so there is nothing to copy between it and WordPress. Upload its files over SFTP instead.'
    };
    function ucfirstSafe(x) { x = String(x || 'the host'); return x.charAt(0).toUpperCase() + x.slice(1); }
    return {
      ...base, edOpen: true, edIllo: this.illo('container'),
      edTitle: 'Deploy between environments',
      edFroms: real.envs.map(e => ({ label: e.environment, desc: where(e), ...chip(f === e.environment), pick: pick('edFrom', e.environment) })),
      edTos: real.envs.map(e => ({ label: e.environment, desc: where(e), ...chip(t === e.environment), pick: pick('edTo', e.environment) })),
      edShowWhat: plan === 'node',
      edWhats: [['both', 'Files and database'], ['files', 'Files'], ['database', 'Database']].map(([v, l]) => ({ label: l, ...chip((s.edWhat || 'both') === v), pick: () => this.setState({ edWhat: v }) })),
      edNote: notes[plan] || '', edWarn: plan === 'none' || plan === 'plain',
      edCanGo: !!plan && plan !== 'none' && plan !== 'plain',
      edGoLabel: plan === 'backup' ? 'Replace ' + t : 'Deploy to ' + t,
      edClose: () => this.setState({ edOpen: false }),
      edGo: () => this.submitEnvDeploy()
    };
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
    const canKinsta = this.envCanKinstaStaging(real);
    const where = canKinsta && s.eaWhere === 'kinsta' ? 'kinsta' : 'ours';
    return {
      ...base, eaOpen: true, eaIllo: this.illo('container'),
      eaHasWhere: canKinsta, eaKinsta: where === 'kinsta', eaOurs: where === 'ours',
      eaWheres: canKinsta ? [
        { label: 'Staging at Kinsta', desc: 'Kinsta copies production into a new Staging environment. Usually the fastest way.', ...chip(where === 'kinsta'), pick: () => this.setState({ eaWhere: 'kinsta' }) },
        { label: 'On our servers', desc: 'Any name, built from the latest backup of an environment, on one of our servers.', ...chip(where === 'ours'), pick: () => this.setState({ eaWhere: 'ours' }) }
      ] : [],
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
      eaSubmitLabel: where === 'kinsta' ? 'Create staging' : s.eaBusy ? 'Adding…' : 'Add environment',
      eaClose: () => this.setState({ eaOpen: false }),
      eaSubmit: () => this.submitAddEnv()
    };
  }

});
