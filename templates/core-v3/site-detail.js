// CaptainCore v3 — site detail real-data layer (mixin).
// Overrides openSite() to load the real detail bundle and provides the
// real* helpers computeDetail() consults when this._detail matches the open
// site. Endpoints per the v1 contract: /sites/{id}/environments (full env
// records incl. credentials + plugins/themes JSON), /sites/{id}/details
// (site+account+domains+shared_with), /sites/{id}/users (keyed by env name),
// /sites/{id}/{env}/logs [+ /fetch], /sites/{id}/{env}/magiclogin[/{uid}],
// /sites/{id}/{env}/sync/data, /sites/environments/push, /run/code.

Object.assign(Component.prototype, {

  openSite(id) {
    this.setState({ route: 'site', siteId: id, siteTab: 'overview', env: 'Production', qsOpen: '', bkOpen: '', paletteOpen: false, logFile: '' });
    if (this._hydrated) this.loadSiteDetail(id);
  },

  loadSiteDetail(id) {
    if (this._detail && this._detail.siteId === id) return;
    const detail = this._detail = { siteId: id, envs: null, site: null, account: null,
      domains: null, sharedWith: null, users: null, logs: {}, logsLoading: false };
    const bump = () => { if (this._detail === detail) this.setState({ tick: this.state.tick }); };
    this.api('/sites/' + id + '/environments').then(envs => {
      detail.envs = Array.isArray(envs) ? envs : [];
      const cur = detail.envs.some(e => e.environment === this.state.env);
      if (!cur && detail.envs[0]) this.setState({ env: detail.envs[0].environment });
      if (this.state.siteTab === 'stats') setTimeout(() => this.loadStats(), 0);
      bump();
    }).catch(() => { detail.envs = []; bump(); });
    this.api('/sites/' + id + '/details').then(d => {
      detail.site = d && d.site; detail.account = d && d.account;
      detail.domains = (d && d.domains) || []; detail.sharedWith = (d && d.shared_with) || [];
      bump();
    }).catch(() => bump());
    this.api('/sites/' + id + '/users').then(u => { detail.users = u || {}; bump(); }).catch(() => bump());
    this.loadTimeline();
  },

  currentEnv(real, s) {
    if (!real || !real.envs) return null;
    return real.envs.find(e => e.environment === s.env) || real.envs[0] || null;
  },

  setEnv(name) {
    const real = this._detail;
    if (real && real.envs && !real.envs.some(e => e.environment === name)) return;
    this.setState({ env: name, logFile: '' });
    if (real && this.state.siteTab === 'logs') this.loadLogs(name);
    if (real && this.state.siteTab === 'stats') setTimeout(() => this.loadStats(), 0);
  },

  // ── Overview ──────────────────────────────────────────────────
  realCredPairs(real, s) {
    const e = this.currentEnv(real, s);
    if (!e) return [['Loading', '…']];
    const pairs = [
      ['Site URL', e.home_url],
      ['WP admin', e.home_url ? e.home_url.replace(/\/$/, '') + '/wp-admin' : ''],
      ['Address', e.address], ['Username', e.username], ['Password', e.password],
      ['Port', e.port], ['Home directory', e.home_directory],
      ['Database', e.database_name], ['DB user', e.database_username], ['DB password', e.database_password],
      ['SSH', e.ssh]
    ];
    return pairs.filter(([, v]) => v !== undefined && v !== null && String(v) !== '');
  },

  realEnvRows(real, s) {
    const e = this.currentEnv(real, s);
    if (!e) return [['Loading', '…']];
    const det = e.details || {};
    const rows = [
      ['WordPress', e.core || '—'],
      ['PHP', det.php_version || ''],
      ['Storage', this.fmtStorage(e.storage)],
      ['Visits / wk', e.visits ? Number(e.visits).toLocaleString() : '—'],
      ['Uptime monitor', e.monitor_enabled ? 'On' : 'Off'],
      ['Managed updates', e.updates_enabled ? 'On' : 'Off']
    ];
    if (Number(e.subsite_count) > 1) rows.push(['Subsites', String(e.subsite_count)]);
    return rows.filter(([, v]) => v !== '');
  },

  realSync(real, s) {
    const id = real.siteId;
    const name = (real.site && real.site.name) || '';
    this.startJob({
      label: 'sync-data', target: name, command: 'syncSite', siteId: id,
      dispatch: () => this.api('/sites/' + id + '/' + s.env.toLowerCase() + '/sync/data'),
      onFinish: () => { this._detail = null; this.loadSiteDetail(id); }
    });
  },

  realMagicLogin(real, s, user) {
    const who = (user && (user.display_name || user.user_login)) ? ' as ' + (user.display_name || user.user_login) : '';
    const tid = this.toast('Signing in' + who + '…', { kind: 'loading' });
    const path = '/sites/' + real.siteId + '/' + s.env.toLowerCase() + '/magiclogin' + (user && user.ID ? '/' + user.ID : '');
    this.api(path).then(url => {
      if (typeof url === 'string' && url.indexOf('http') === 0) {
        window.open(url.trim());
        this.updateToast(tid, 'Opened WordPress admin', { kind: 'success' });
      } else { console.warn('magiclogin unexpected response', url); this.updateToast(tid, 'Could not sign in', { kind: 'error' }); }
    }).catch(err => { console.warn('magiclogin failed', err); this.updateToast(tid, 'Could not sign in', { kind: 'error' }); });
  },

  realPush(real, direction) {
    if (!real.envs) return;
    const prod = real.envs.find(e => e.environment === 'Production');
    const stag = real.envs.find(e => e.environment === 'Staging');
    if (!prod || !stag) return;
    const source = direction === 'up' ? stag : prod;
    const target = direction === 'up' ? prod : stag;
    const name = (real.site && real.site.name) || '';
    // Push is a provider operation (202 + operation_id), not a token job —
    // the dock entry is resolved by polling /provider-actions/check until the
    // registered action leaves the active list (v1: checkProviderActions).
    const jobId = this.startJob({
      label: 'deploy', target: source.environment.toLowerCase() + ' → ' + target.environment.toLowerCase() + ' on ' + name,
      command: 'push', siteId: real.siteId
    });
    const job = this._jobObjs[jobId];
    this.api('/sites/environments/push', { method: 'POST',
      body: { source_environment_id: source.environment_id, target_environment_id: target.environment_id } })
      .then(res => {
        if (res && res.code) { job.stream.push('Error: ' + (res.message || res.code)); this.finishJob(job, 'error'); return; }
        job.stream.push((res && res.message) || 'Push requested.');
        // provider-actions is admin-gated (role_check) — customers keep the
        // fire-and-forget behavior.
        if (res && res.operation_id && (window.CC_BOOT || {}).dcRole === 'operator') {
          job.stream.push('Tracking provider operation ' + res.operation_id + '…');
          this.trackProviderOp(job, res.operation_id, real.siteId);
        } else {
          this.finishJob(job, 'done');
        }
      })
      .catch(err => { job.stream.push('Error: ' + (err && err.message || err)); this.finishJob(job, 'error'); });
  },

  // Poll /provider-actions/check every 10s until the action registered for
  // operationId is no longer active (started/waiting). "waiting" actions get
  // their follow-up step run via /provider-actions/{id}/run — that is what
  // flips a finished operation to done (v1: runProviderActions).
  trackProviderOp(job, operationId, siteId, attempts) {
    attempts = attempts || 0;
    if (attempts > 90) { // ~15 min safety cap
      job.stream.push('Stopped tracking — the operation is taking unusually long. Check the provider dashboard.');
      this.finishJob(job, 'done');
      return;
    }
    setTimeout(() => {
      this.api('/provider-actions/check').then(list => {
        const actions = Array.isArray(list) ? list : [];
        actions.forEach(a => {
          if (a.status === 'waiting') this.api('/provider-actions/' + a.provider_action_id + '/run').catch(() => {});
        });
        const mine = actions.find(a => String(a.provider_key) === String(operationId));
        if (!mine) {
          job.stream.push('Provider reports the operation finished.');
          this.finishJob(job, 'done');
          // Environments changed on the target — refresh the open detail.
          if (this._detail && this._detail.siteId === siteId) { this._detail = null; this.loadSiteDetail(siteId); }
          return;
        }
        this.patchJob(job.id, st => ({ pct: Math.min(90, (st.pct || 10) + 6) }));
        this.trackProviderOp(job, operationId, siteId, attempts + 1);
      }).catch(() => {
        // Poll failure (auth/network) — end gracefully rather than spin.
        job.stream.push('Could not poll operation status; assuming it completes in the background.');
        this.finishJob(job, 'done');
      });
    }, attempts === 0 ? 8000 : 10000);
  },

  // ── phpMyAdmin (Kinsta / Rocket.net only) ─────────────────────
  realPhpMyAdmin(real, s) {
    const tid = this.toast('Opening phpMyAdmin…', { kind: 'loading' });
    this.api('/sites/' + real.siteId + '/' + s.env.toLowerCase() + '/phpmyadmin').then(url => {
      if (typeof url === 'string' && url.indexOf('http') === 0) {
        window.open(url.trim());
        this.updateToast(tid, 'phpMyAdmin opened', { kind: 'success' });
      } else {
        this.updateToast(tid, 'phpMyAdmin not available', { kind: 'error' });
      }
    }).catch(() => this.updateToast(tid, 'phpMyAdmin not available', { kind: 'error' }));
  },

  // ── Share Access (v1 parity: invite-preview + invite) ─────────
  openShareDialog() {
    const real = this._detail;
    if (!real) return;
    this._sharePreview = null;
    this.setState({ shareDlgOpen: true, shareEmail: (this.state.shareDraft || '').trim(), shareErr: '', shareSending: false, shareLoading: true });
    this.api('/sites/' + real.siteId + '/invite-preview').then(p => {
      if (!p || p.code) throw new Error((p && p.message) || 'preview failed');
      this._sharePreview = p;
      this.setState({ shareLoading: false });
    }).catch(() => { this._sharePreview = null; this.setState({ shareLoading: false }); });
  },

  sendSiteInvite() {
    const real = this._detail;
    if (!real || this.state.shareSending) return;
    const email = this.state.shareEmail.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { this.setState({ shareErr: 'Enter a valid email address.' }); return; }
    this.setState({ shareSending: true, shareErr: '' });
    this.api('/sites/' + real.siteId + '/invite', { method: 'POST', body: { email } }).then(res => {
      if (res && res.code) { this.setState({ shareSending: false, shareErr: res.message || 'Error sending invite.' }); return; }
      this.setState(st => ({ shareSending: false, shareDlgOpen: false, shareDraft: '',
        shared: [...(st.shared || []), { uid: Date.now(), name: email, pending: true }] }));
      this.toast((res && res.message) || 'Invitation sent', { kind: 'success' });
    }).catch(() => this.setState({ shareSending: false, shareErr: 'Error sending invite.' }));
  },

  // Bindings for the Share Access dialog (spread into computeDetail's return).
  computeShareDialog(real, s, site) {
    const p = this._sharePreview;
    const email = (s.shareEmail || '').trim();
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    const siteName = (real && real.site && real.site.name) || site.name;
    let acctLead = '', acctSites = '', acctSitesList = '', acctDomains = '', simpleLine = '';
    if (p && p.has_account_access) {
      acctLead = 'Inviting ' + email + ' will grant them access to the account ' + (p.account_name || '') + '. This includes:';
      const n = p.total_sites || 0;
      acctSites = n + ' Website' + (n === 1 ? '' : 's') + ':';
      acctSitesList = (p.sites_list || []).map(x => x.name).join(', ');
      if (p.total_domains > 0) acctDomains = p.total_domains + ' Domain' + (p.total_domains === 1 ? '' : 's');
    } else if (p) {
      simpleLine = 'Inviting ' + email + ' will grant them access to ' + (p.site_name || siteName);
      if (p.total_sites > 1) simpleLine += ' along with ' + (p.total_sites - 1) + ' other site' + (p.total_sites - 1 === 1 ? '' : 's');
      if (p.total_domains > 0) simpleLine += ' and ' + p.total_domains + ' domain' + (p.total_domains === 1 ? '' : 's');
      simpleLine += '.';
    }
    return {
      shareDlgOpen: s.shareDlgOpen,
      closeShareDlg: () => this.setState({ shareDlgOpen: false }),
      shareDlgTitle: 'Invite a user to manage ' + siteName + '.',
      shareEmail: s.shareEmail,
      onShareEmail: e => this.setState({ shareEmail: e.target.value, shareErr: '' }),
      shareKey: e => { if (e.key === 'Enter') this.sendSiteInvite(); },
      shareLoadingB: s.shareLoading,
      sharePreviewShow: !!(p && valid && !s.shareLoading),
      shareIsAcct: !!(p && p.has_account_access), shareIsSimple: !!(p && !p.has_account_access),
      shAcctLead: acctLead, shAcctSites: acctSites, shAcctSitesList: acctSitesList,
      shAcctDomains: acctDomains, shHasAcctDomains: !!acctDomains,
      shSimpleLine: simpleLine,
      shareErr: s.shareErr, shareHasErr: !!s.shareErr,
      shareSendLabel: s.shareSending ? 'Sending…' : 'Send invite',
      shareCanSend: valid && !s.shareSending,
      shareSendBg: valid && !s.shareSending ? 'var(--brand)' : 'var(--rule)',
      shareSend: () => this.sendSiteInvite()
    };
  },

  // ── Addons ────────────────────────────────────────────────────
  realAddonSrc(real, s) {
    const e = this.currentEnv(real, s);
    if (!e) return [];
    const list = s.addonKind === 'plugins' ? e.plugins : e.themes;
    if (!Array.isArray(list)) return [];
    return list.map(p => ({
      name: p.title || p.plugin || p.name || '',
      slug: p.name || p.slug || '',
      v: p.version || '', latest: p.version || '',
      active: p.status === 'active',
      _status: p.status || ''
    }));
  },

  realToggleAddon(a, real, s) {
    const e = this.currentEnv(real, s);
    if (!e) return;
    const kind = s.addonKind === 'plugins' ? 'plugin' : 'theme';
    const action = kind === 'theme' ? 'activate' : (a.active ? 'deactivate' : 'activate');
    const code = 'wp ' + kind + ' ' + action + ' ' + a.slug + ' --skip-themes --skip-plugins';
    const name = (real.site && real.site.name) || '';
    // Optimistic flip, mirroring v1.
    const list = kind === 'plugin' ? e.plugins : e.themes;
    if (Array.isArray(list)) list.forEach(p => {
      if ((p.name || p.slug) === a.slug) p.status = action === 'activate' ? 'active' : 'inactive';
      else if (kind === 'theme' && action === 'activate' && p.status === 'active') p.status = 'inactive';
    });
    this.startJob({
      label: kind + ' ' + action, target: a.slug + ' on ' + name, command: 'manage',
      siteId: real.siteId, environment: s.env,
      dispatch: () => this.api('/run/code', { method: 'POST', body: { environments: [e.environment_id], code } })
    });
  },

  // ── Users ─────────────────────────────────────────────────────
  realUserRows(real, s) {
    if (!real.users) return [];
    const list = real.users[s.env] || [];
    return list.map(u => {
      const login = u.user_login || '';
      return {
        n: login, e: u.user_email || '',
        role: Array.isArray(u.roles) ? u.roles.join(', ') : String(u.roles || ''),
        last: '',
        init: login.slice(0, 2).toUpperCase(),
        magic: () => this.realMagicLogin(real, s, u)
      };
    });
  },

  // ── Logs ──────────────────────────────────────────────────────
  loadLogs(envName) {
    const real = this._detail;
    if (!real) return;
    const env = (envName || this.state.env).toLowerCase();
    if (real.logs[env] || real.logsLoading) return;
    real.logsLoading = true;
    this.setState({ tick: this.state.tick });
    this.api('/sites/' + real.siteId + '/' + env + '/logs').then(res => {
      const files = (res && res.files ? res.files : []).map(f => f.path || String(f));
      real.logs[env] = { files, content: {} };
      real.logsLoading = false;
      if (files.length) this.pickLogFile(files[0]);
      else this.setState({ tick: this.state.tick });
    }).catch(() => { real.logs[env] = { files: [], content: {} }; real.logsLoading = false; this.setState({ tick: this.state.tick }); });
  },

  pickLogFile(path) {
    const real = this._detail;
    if (!real) return;
    const env = this.state.env.toLowerCase();
    this.setState({ logFile: path });
    const bucket = real.logs[env];
    if (!bucket || bucket.content[path] !== undefined) return;
    bucket.content[path] = null; // loading
    this.api('/sites/' + real.siteId + '/' + env + '/logs/fetch', { method: 'POST', body: { file: path, limit: '1000' } })
      .then(text => { bucket.content[path] = typeof text === 'string' ? text : JSON.stringify(text); this.setState({ tick: this.state.tick }); })
      .catch(() => { bucket.content[path] = '(failed to fetch log)'; this.setState({ tick: this.state.tick }); });
  },

  realLogFiles(real, s) {
    const bucket = real.logs[s.env.toLowerCase()];
    return bucket ? bucket.files : [];
  },

  realLogLines(real, s) {
    const bucket = real.logs[s.env.toLowerCase()];
    if (!bucket) return [{ text: real.logsLoading ? 'Loading log list…' : 'Open this tab to load logs.' }];
    const content = bucket.content[s.logFile];
    if (content === null) return [{ text: 'Loading ' + s.logFile + '…' }];
    if (content === undefined) return [{ text: bucket.files.length ? 'Select a log file.' : 'No log files found.' }];
    return content.split('\n').slice(-1000).map(text => ({ text }));
  }

});
