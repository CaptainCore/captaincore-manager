// CaptainCore v3 — site system tools (mixin). v1 parity with core.php's
// Scripts tab "System Tools" grid, rehomed into the Overview Actions card.
//
// Two backends, both already existing:
//   POST /sites/bulk-tools { tool, environments:[env_id], params }
//        → deploy-defaults · apply-https (params.www) · launch (params.domain)
//   POST /sites/cli        { post_id, environment, command, … }
//        → reset-permissions · migrate (value + update_urls)
//        → activate / deactivate (maintenance, carries the visitor-facing copy
//          that bulk-tools' deactivate can't take: subject/status/action)
// Both return a job token (Run::task returns the string, background_task an
// object), which startJob's dispatch already accepts either way.
//
// No role gate: v1 shows the Scripts tab to every role and the routes are
// site-scoped (captaincore_verify_permissions per environment), so a customer
// can only ever act on their own sites.

Object.assign(Component.prototype, {

  toolEnvId(real, s) {
    const e = this.currentEnv(real, s);
    return e && e.environment_id;
  },

  toolSiteName(real, s) {
    const e = this.currentEnv(real, s);
    const url = (e && e.home_url) || (real.site && real.site.name) || '';
    return String(url).replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, '');
  },

  // Shared dispatch: one job, streamed into the dock like every other action.
  runTool({ label, real, s, dispatch, onFinish }) {
    return this.startJob({
      label, target: this.toolSiteName(real, s), command: 'manage',
      siteId: real.siteId, environment: s.env, dispatch, onFinish
    });
  },

  bulkTool(tool, real, s, params) {
    const envId = this.toolEnvId(real, s);
    if (!envId) return Promise.reject(new Error('No environment selected.'));
    return this.api('/sites/bulk-tools', { method: 'POST',
      body: { tool, environments: [envId], params: params || {} } });
  },

  cliTool(body, real, s) {
    return this.api('/sites/cli', { method: 'POST',
      body: Object.assign({ post_id: real.siteId, environment: s.env }, body) });
  },

  // ── One-click tools (confirm only) ───────────────────────────
  async toolDeployDefaults(real, s) {
    if (!(await this.uiConfirm('Deploy defaults on ' + this.toolSiteName(real, s) + '?', { label: 'Deploy defaults' }))) return;
    this.runTool({ label: 'deploy-defaults', real, s,
      dispatch: () => this.bulkTool('deploy-defaults', real, s),
      onFinish: () => { this._detail = null; this.loadSiteDetail(real.siteId); } });
  },

  async toolResetPermissions(real, s) {
    if (!(await this.uiConfirm('Reset file permissions to defaults on ' + this.toolSiteName(real, s) + '?', { label: 'Reset permissions' }))) return;
    this.runTool({ label: 'reset-permissions', real, s,
      dispatch: () => this.cliTool({ command: 'reset-permissions' }, real, s) });
  },

  // ── Dialog-backed tools ──────────────────────────────────────
  openToolDialog(kind, real, s) {
    const patch = { toolDlg: kind };
    if (kind === 'maintenance') {
      // Business name/link default from branding. CC_BOOT already carries the
      // configured portal name + home link, so this needs no fetch — but a
      // loaded Settings config (this._set.cfg) is fresher, so prefer it.
      const boot = window.CC_BOOT || {};
      const cfg = (this._set && this._set.cfg) || {};
      patch.mtName = cfg.name || boot.name || '';
      patch.mtLink = cfg.url || boot.homeLink || '';
      patch.mtSubject = 'Website Inactive';
      patch.mtStatus = 'This website is currently unavailable.';
      patch.mtAction = 'Site owners may contact';
    }
    if (kind === 'launch') patch.lnDomain = '';
    if (kind === 'migrate') { patch.mgUrl = ''; patch.mgUpdateUrls = true; }
    if (kind === 'https') patch.httpsWww = false;
    this.setState(patch);
  },

  closeToolDialog() { this.setState({ toolDlg: '' }); },

  toolApplyHttps(real, s) {
    const www = !!this.state.httpsWww;
    this.setState({ toolDlg: '' });
    this.runTool({ label: 'apply-https', real, s,
      dispatch: () => this.bulkTool('apply-https', real, s, www ? { www: true } : {}) });
  },

  toolLaunch(real, s) {
    const domain = (this.state.lnDomain || '').trim().replace(/^https?:\/\//, '').replace(/\/$/, '');
    if (!domain) return;
    this.setState({ toolDlg: '' });
    this.runTool({ label: 'launch', real, s,
      dispatch: () => this.bulkTool('launch', real, s, { domain }),
      onFinish: () => { this._detail = null; this.loadSiteDetail(real.siteId); } });
  },

  async toolMigrate(real, s) {
    const url = (this.state.mgUrl || '').trim();
    if (!url) return;
    if (!(await this.uiConfirm('Migrate from backup URL? This OVERWRITES the existing site at ' + this.toolSiteName(real, s) + '.', { label: 'Migrate and overwrite', danger: true }))) return;
    const updateUrls = !!this.state.mgUpdateUrls;
    this.setState({ toolDlg: '' });
    this.runTool({ label: 'migrate', real, s,
      // update_urls is compared to the STRING "true" server-side.
      dispatch: () => this.cliTool({ command: 'migrate', value: url, update_urls: updateUrls ? 'true' : 'false' }, real, s),
      onFinish: () => { this._detail = null; this.loadSiteDetail(real.siteId); } });
  },

  async toolMaintenance(real, s, enable) {
    const st = this.state;
    const name = this.toolSiteName(real, s);
    if (!(await this.uiConfirm(enable ? 'Put ' + name + ' into maintenance mode? Visitors will see the notice below.'
      : 'Restore ' + name + ' and remove the maintenance notice?', { label: enable ? 'Enable maintenance' : 'Restore site' }))) return;
    this.setState({ toolDlg: '' });
    this.runTool({ label: enable ? 'maintenance-on' : 'maintenance-off', real, s,
      dispatch: () => this.cliTool(enable
        ? { command: 'deactivate', name: st.mtName, link: st.mtLink,
            subject: st.mtSubject, status_msg: st.mtStatus, action_text: st.mtAction }
        : { command: 'activate' }, real, s) });
  },

  // ── Scheduled scripts (Activity → Scheduled) ─────────────────
  // No fetch: Site::environments() already attaches `scheduled_scripts` to
  // every environment (author, author_avatar, run_at epoch, code, script_id),
  // so the list rides the /sites/{id}/environments call v3 already makes.
  openEditScript(sc) {
    const d = new Date((Number(sc.run_at) || 0) * 1000);
    const pad = n => String(n).padStart(2, '0');
    this.setState({ esId: sc.script_id, esCode: sc.code || '', esBusy: false, esErr: '',
      esDate: d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()),
      esTime: pad(d.getHours()) + ':' + pad(d.getMinutes()) });
  },

  saveEditScript(real) {
    const s = this.state;
    if (!s.esId) return;
    this.setState({ esBusy: true, esErr: '' });
    // Same contract as /scripts/schedule — the server re-parses date+time in
    // the given zone. NOTE the update route is POST, not PUT.
    this.api('/scripts/' + s.esId, { method: 'POST', body: { code: s.esCode,
      run_at: { date: s.esDate, time: s.esTime, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone } } })
      .then(() => {
        this.setState({ esId: 0, esBusy: false });
        this.toast('Scheduled script updated.', { kind: 'success' });
        if (real) { const id = real.siteId; this._detail = null; this.loadSiteDetail(id); }
      })
      .catch(() => this.setState({ esBusy: false, esErr: 'Could not save. Try again.' }));
  },

  async deleteScript(real, sc) {
    if (!(await this.uiConfirm('Cancel this scheduled script?', { label: 'Cancel script', danger: true }))) return;
    this.api('/scripts/' + sc.script_id, { method: 'DELETE' })
      .then(() => {
        this.toast('Scheduled script cancelled.', { kind: 'success' });
        this.setState({ esId: 0 });
        if (real) { const id = real.siteId; this._detail = null; this.loadSiteDetail(id); }
      })
      .catch(() => this.toast('Could not cancel the script.', { kind: 'error' }));
  },

  computeScheduled(real, s) {
    const e = real ? this.currentEnv(real, s) : null;
    const list = (e && Array.isArray(e.scheduled_scripts)) ? e.scheduled_scripts : [];
    const rows = list.slice().sort((a, b) => (Number(a.run_at) || 0) - (Number(b.run_at) || 0)).map(sc => {
      const when = new Date((Number(sc.run_at) || 0) * 1000);
      const code = String(sc.code || '');
      const first = code.split('\n')[0];
      return {
        code: first.length > 90 ? first.slice(0, 90) + '…' : first,
        multi: code.indexOf('\n') !== -1 ? '+' + (code.split('\n').length - 1) + ' more lines' : '',
        multiShow: code.indexOf('\n') !== -1,
        author: sc.author || '', avatar: sc.author_avatar || '',
        when: when.toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }),
        edit: () => this.openEditScript(sc),
        del: (ev) => { ev.stopPropagation(); this.deleteScript(real, sc); }
      };
    });
    const editing = s.esId ? list.find(x => String(x.script_id) === String(s.esId)) : null;
    return {
      // `ss` prefix, NOT `sched` — reports.js already owns schedRows/schedEmpty
      // for scheduled REPORTS and spreads after computeDetail, so those names
      // get silently clobbered here (the collision trap in STATUS.md).
      ssRows: rows,
      ssEmpty: !!real && rows.length === 0,
      ssCount: rows.length ? rows.length + (rows.length === 1 ? ' scheduled script' : ' scheduled scripts') : '',
      // Edit dialog
      esOpen: !!editing,
      esCode: s.esCode || '', onEsCode: ev => this.setState({ esCode: ev.target.value }),
      esDate: s.esDate || '', onEsDate: ev => this.setState({ esDate: ev.target.value }),
      esTime: s.esTime || '', onEsTime: ev => this.setState({ esTime: ev.target.value }),
      esErr: s.esErr || '', esErrShow: !!s.esErr,
      esLabel: s.esBusy ? 'Saving…' : 'Save changes',
      esZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      esClose: () => this.setState({ esId: 0 }),
      esSave: () => this.saveEditScript(real),
      esDelete: () => { if (editing) this.deleteScript(real, editing); }
    };
  },

  // Spread into computeDetail. `real` is null in design mode — the tools then
  // render but no-op, same convention as the rest of the detail slices.
  computeTools(real, s) {
    const on = fn => () => { if (real) fn(); };
    const tools = [
      { k: 'deploy',  label: 'Deploy defaults',  desc: 'Apply standard config & plugins',
        icon: 'M21 12a9 9 0 1 1-6.2-8.6 M21 3v6h-6', go: on(() => this.toolDeployDefaults(real, s)) },
      { k: 'migrate', label: 'Migrate backup…',  desc: 'Import from an external URL',
        icon: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4 M7 10l5 5 5-5 M12 15V3', go: on(() => this.openToolDialog('migrate', real, s)) },
      { k: 'https',   label: 'Apply HTTPS…',     desc: 'Search & replace http:// → https://',
        icon: 'M19 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2Z M7 11V7a5 5 0 0 1 10 0v4', go: on(() => this.openToolDialog('https', real, s)) },
      { k: 'perms',   label: 'Reset permissions', desc: 'Fix file ownership & groups',
        icon: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M12 18v-4 M10 16h4', go: on(() => this.toolResetPermissions(real, s)) },
      { k: 'maint',   label: 'Maintenance mode…', desc: 'Toggle public accessibility',
        icon: 'M14.7 6.3a4 4 0 0 0 5 5l-9.4 9.4a2.8 2.8 0 0 1-4-4Z', go: on(() => this.openToolDialog('maintenance', real, s)) },
      { k: 'launch',  label: 'Launch site…',      desc: 'Go-live domain replacement',
        icon: 'M4.5 16.5c-1.5 1.3-2 5-2 5s3.7-.5 5-2a2.1 2.1 0 0 0-3-3Z M12 15l-3-3a22 22 0 0 1 8-10 10 10 0 0 1 5 5 22 22 0 0 1-10 8Z', go: on(() => this.openToolDialog('launch', real, s)) }
    ];
    // Provider-backed domain mappings (Kinsta / Rocket.net) — jumps to the
    // site's Domains tab, which is the mapping manager (list, add, delete,
    // set primary, verification records).
    const fRow = real ? this.FLEET.find(x => String(x.id) === String(real.siteId)) : null;
    const sdProvider = ((real && real.site && real.site.provider) || (fRow && fRow.provider) || '').toLowerCase();
    if (sdProvider === 'kinsta' || sdProvider === 'rocketdotnet') {
      tools.push({ k: 'mappings', label: 'Configure domain mappings', desc: 'Add, remove or set the primary domain',
        icon: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Z M2 12h20 M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z',
        go: on(() => { this.setState({ siteTab: 'sitedomains' }); this.loadEnvDomains(); }) });
    }

    const dlg = s.toolDlg || '';
    return {
      siteTools: tools,
      ...this.computeScheduled(real, s),
      // ── Apply HTTPS ──
      thOpen: dlg === 'https',
      thWww: !!s.httpsWww,
      thWwwMark: s.httpsWww ? '✓' : '',
      thToggleWww: () => this.setState(st => ({ httpsWww: !st.httpsWww })),
      thSiteName: real ? this.toolSiteName(real, s) : '',
      thRun: on(() => this.toolApplyHttps(real, s)),
      // ── Launch ──
      tlOpen: dlg === 'launch',
      tlDomain: s.lnDomain || '',
      onTlDomain: e => this.setState({ lnDomain: e.target.value }),
      tlDisabled: !(s.lnDomain || '').trim(),
      tlBg: (s.lnDomain || '').trim() ? 'var(--brand)' : 'var(--ink-dim)',
      tlRun: on(() => this.toolLaunch(real, s)),
      // ── Migrate ──
      tmOpen: dlg === 'migrate',
      tmUrl: s.mgUrl || '',
      onTmUrl: e => this.setState({ mgUrl: e.target.value }),
      tmUpdate: !!s.mgUpdateUrls,
      tmUpdateMark: s.mgUpdateUrls ? '✓' : '',
      tmToggleUpdate: () => this.setState(st => ({ mgUpdateUrls: !st.mgUpdateUrls })),
      tmDisabled: !(s.mgUrl || '').trim(),
      tmBg: (s.mgUrl || '').trim() ? 'var(--bad)' : 'var(--ink-dim)',
      tmSiteName: real ? this.toolSiteName(real, s) : '',
      tmRun: on(() => this.toolMigrate(real, s)),
      // ── Maintenance ──
      tnOpen: dlg === 'maintenance',
      tnSiteName: real ? this.toolSiteName(real, s) : '',
      tnFields: [
        { k: 'Business name', v: s.mtName || '', on: e => this.setState({ mtName: e.target.value }) },
        { k: 'Business link', v: s.mtLink || '', on: e => this.setState({ mtLink: e.target.value }) },
        { k: 'Heading',       v: s.mtSubject || '', on: e => this.setState({ mtSubject: e.target.value }) },
        { k: 'Status message', v: s.mtStatus || '', on: e => this.setState({ mtStatus: e.target.value }) },
        { k: 'Action text',   v: s.mtAction || '', on: e => this.setState({ mtAction: e.target.value }) }
      ],
      tnDeactivate: on(() => this.toolMaintenance(real, s, true)),
      tnActivate: on(() => this.toolMaintenance(real, s, false)),
      closeToolDlg: () => this.closeToolDialog()
    };
  }

});
