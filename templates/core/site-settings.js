// ── Site settings every host offers ─────────────────────────────────────────
// PHP version, Clear cache and Redirects in the Overview's Settings card, for
// customers and operators alike. One control per setting, whichever host runs
// the environment: our servers (a hosting revision + host apply, or host
// cache) or Kinsta (its API with the site's own key). The REST side is
// /sites/{id}/{env}/host-settings and /sites/{id}/{env}/cache/clear, gated by
// access to the site. Redirects exist on our servers only (Kinsta's API has
// none). Everything else about our servers stays on the operator Hosting tab.
Object.assign(Component.prototype, {

  // 'captaincore' | 'kinsta' | '' for the environment being viewed.
  siteSettingsHost(real, s) {
    if (!real || !real.site || !real.envs) return '';
    const envRow = real.envs.find(e => e.environment === (s.env || 'Production'));
    if (!envRow) return '';
    const own = envRow.host ? envRow.host === 'captaincore' : real.site.provider === 'captaincore';
    if (own) return 'captaincore';
    return real.site.provider === 'kinsta' && real.site.provider_site_id ? 'kinsta' : '';
  },

  siteSettingsKey() {
    const real = this._detail;
    return real ? real.siteId + '|' + (this.state.env || 'Production') : '';
  },

  loadSiteSettings(force) {
    const key = this.siteSettingsKey();
    if (!key || (!force && this._sset && this._sset.key === key) || this._ssetLoading === key) return;
    this._ssetLoading = key;
    const [siteId, env] = key.split('|');
    this.api('/sites/' + siteId + '/' + encodeURIComponent(env) + '/host-settings').then(res => {
      this._ssetLoading = null;
      if (key !== this.siteSettingsKey()) return;
      if (!res || res.code) { this._sset = { key, error: (res && res.message) || 'Could not load the settings' }; this.setState({}); return; }
      this._sset = { key, data: res };
      this.setState({});
      // While a change is being applied on our servers, check again.
      clearTimeout(this._ssetPoll);
      if (res.applying) this._ssetPoll = setTimeout(() => this.loadSiteSettings(true), 5000);
    }).catch(() => { this._ssetLoading = null; this._sset = { key, error: 'Could not load the settings' }; this.setState({}); });
  },

  saveSiteSettings(body, busyText, doneText) {
    const key = this.siteSettingsKey();
    if (!key || this.state.ssetBusy) return Promise.resolve(false);
    const [siteId, env] = key.split('|');
    this.setState({ ssetBusy: true });
    const tid = this.toast(busyText, { kind: 'loading' });
    return this.api('/sites/' + siteId + '/' + encodeURIComponent(env) + '/host-settings', { method: 'POST', body })
      .then(res => {
        if (!res || res.code) throw new Error((res && res.message) || 'Could not save');
        this._sset = { key, data: res };
        this.setState({ ssetBusy: false });
        this.updateToast(tid, doneText, { kind: 'success', timeout: 7000 });
        clearTimeout(this._ssetPoll);
        if (res.applying) this._ssetPoll = setTimeout(() => this.loadSiteSettings(true), 5000);
        // Kinsta switches PHP on its own time: read it back after a while.
        if (res.operation_id) this._ssetPoll = setTimeout(() => this.loadSiteSettings(true), 45000);
        return true;
      })
      .catch(e => { this.setState({ ssetBusy: false }); this.updateToast(tid, (e && e.message) || 'Could not save', { kind: 'error', timeout: 9000 }); return false; });
  },

  clearSiteCache() {
    const key = this.siteSettingsKey();
    if (!key || this.state.ssetClearing) return;
    const [siteId, env] = key.split('|');
    this.setState({ ssetClearing: true });
    const tid = this.toast('Clearing the cache of ' + env + '…', { kind: 'loading' });
    this.api('/sites/' + siteId + '/' + encodeURIComponent(env) + '/cache/clear', { method: 'POST', body: {} })
      .then(res => {
        if (!res || res.code) throw new Error((res && res.message) || 'Could not clear the cache');
        const n = res.cleared;
        this.updateToast(tid, n === null || n === undefined ? 'Cache cleared at Kinsta' : 'Cache cleared (' + n + ' saved ' + (n === 1 ? 'page' : 'pages') + ')', { kind: 'success', timeout: 5000 });
        this.setState({ ssetClearing: false });
      })
      .catch(e => { this.setState({ ssetClearing: false }); this.updateToast(tid, (e && e.message) || 'Could not clear the cache', { kind: 'error', timeout: 8000 }); });
  },

  siteSettingsVals(real, s) {
    const host = this.siteSettingsHost(real, s);
    const off = { ssetShow: false, ssetPhpOpen: false, ssetRdOpen: false };
    if (!host) return off;
    const key = this.siteSettingsKey();
    if (!this._sset || this._sset.key !== key) setTimeout(() => this.loadSiteSettings(), 0);
    const ss = this._sset && this._sset.key === key ? this._sset : null;
    const d = ss && ss.data;
    const env = s.env || 'Production';
    const onKinsta = host === 'kinsta';

    const rows = [];
    const phpV = d ? (d.php.version || '?') : (ss && ss.error ? '?' : '…');
    rows.push({ k: 'PHP', v: phpV, act: 'Change…', go: () => d && this.setState({ ssetPhpOpen: true, ssetPhpPick: d.php.version }) });
    if (!d || d.cache) rows.push({ k: 'Cache', v: '', act: s.ssetClearing ? 'Clearing…' : 'Clear cache', go: () => d && this.clearSiteCache() });
    if (!onKinsta && (!d || Array.isArray(d.redirects))) {
      const n = d ? d.redirects.length : -1;
      rows.push({ k: 'Redirects', v: n < 0 ? '…' : n === 0 ? 'None' : String(n), act: 'Manage…',
        go: () => { if (!d) return; this._ssetRd = JSON.parse(JSON.stringify(d.redirects)); this.setState({ ssetRdOpen: true, ssetRFrom: '', ssetRTo: '', ssetRCode: 301 }); } });
    }

    const out = {
      ssetShow: true,
      ssetRows: rows.map(r => ({ ...r, hasV: r.v !== '' })),
      ssetApplying: !!(d && d.applying),
      ssetError: (ss && ss.error) || (d && d.error) || '',
      ssetHasError: !!((ss && ss.error) || (d && d.error)),
      ssetPhpOpen: false, ssetRdOpen: false
    };

    // PHP version dialog.
    if (s.ssetPhpOpen && d) {
      const pick = s.ssetPhpPick || d.php.version;
      out.ssetPhpOpen = true;
      out.ssetPhpTitle = 'PHP version for ' + env;
      out.ssetPhpIllo = this.illo ? this.illo('container') : null;
      out.ssetPhpNote = onKinsta
        ? 'Kinsta restarts PHP on the new version, usually within a minute. Visitors may see a short pause.'
        : 'The site moves to a fresh build on the new version. A backup is taken first, the site is offline for about 10 seconds, and it is put back on the old version automatically if it stops answering.';
      out.ssetPhpVersions = d.php.versions.map(v => {
        const on = v === pick, cur = v === d.php.version;
        return { label: 'PHP ' + v, desc: cur ? 'Current' : '', mark: on ? '●' : '',
          bd: on ? 'var(--brand)' : 'var(--rule)', bg: on ? 'var(--brand-soft)' : 'var(--paper)',
          pick: () => this.setState({ ssetPhpPick: v }) };
      });
      out.ssetPhpSame = pick === d.php.version;
      out.ssetPhpSubmitLabel = pick === d.php.version ? 'No change' : 'Switch to PHP ' + pick;
      out.ssetPhpSubmitOpacity = pick === d.php.version || s.ssetBusy ? '.5' : '1';
      out.ssetPhpClose = () => this.setState({ ssetPhpOpen: false });
      out.ssetPhpSubmit = () => {
        if (pick === d.php.version || s.ssetBusy) return;
        this.saveSiteSettings({ php_version: pick }, 'Switching ' + env + ' to PHP ' + pick + '…',
          onKinsta ? 'Kinsta is switching ' + env + ' to PHP ' + pick : env + ' is moving to PHP ' + pick + '. It takes about a minute.')
          .then(ok => { if (ok) this.setState({ ssetPhpOpen: false }); });
      };
    }

    // Redirects dialog (our servers). Edits a draft; Save sends the whole list.
    if (s.ssetRdOpen && d && Array.isArray(d.redirects)) {
      const draft = this._ssetRd || [];
      const code = s.ssetRCode || 301;
      const dirty = JSON.stringify(draft) !== JSON.stringify(d.redirects);
      out.ssetRdOpen = true;
      out.ssetRdTitle = 'Redirects for ' + env;
      out.ssetRdRows = draft.map((r, i) => ({ from: r.from, to: r.to, code: String(r.code),
        remove: () => { draft.splice(i, 1); this.setState({}); } }));
      out.ssetRdEmpty = draft.length === 0;
      out.ssetRFrom = s.ssetRFrom || '';
      out.ssetRTo = s.ssetRTo || '';
      out.onSsetRFrom = e => this.setState({ ssetRFrom: e.target.value });
      out.onSsetRTo = e => this.setState({ ssetRTo: e.target.value });
      out.ssetRCodeChips = [301, 302].map(c => ({ label: c + (c === 301 ? ' permanent' : ' temporary'),
        fg: code === c ? 'var(--brand-ink)' : 'var(--ink-dim)', bg: code === c ? 'var(--brand-soft)' : 'transparent',
        bd: code === c ? 'var(--brand)' : 'var(--rule)', pick: () => this.setState({ ssetRCode: c }) }));
      out.ssetRdAdd = () => {
        const from = (this.state.ssetRFrom || '').trim(), to = (this.state.ssetRTo || '').trim();
        if (!/^\/[A-Za-z0-9._~%\/+-]*\*?$/.test(from)) { this.toast('From must be a path like /old-page or /blog/*', { kind: 'error' }); return; }
        if (!/^(\/[A-Za-z0-9._~%\/+?=&#-]*|https?:\/\/[A-Za-z0-9.-]+(:\d+)?(\/[A-Za-z0-9._~%\/+?=&#-]*)?)$/.test(to)) { this.toast('To must be a path or an http(s) URL, without spaces', { kind: 'error' }); return; }
        if (draft.some(r => r.from === from)) { this.toast(from + ' already redirects', { kind: 'error' }); return; }
        draft.push({ from, to, code });
        this.setState({ ssetRFrom: '', ssetRTo: '' });
      };
      out.ssetRdSaveLabel = dirty ? 'Save redirects' : 'No changes';
      out.ssetRdSaveOpacity = dirty && !s.ssetBusy ? '1' : '.5';
      out.ssetRdClose = () => this.setState({ ssetRdOpen: false });
      out.ssetRdSave = () => {
        if (!dirty || s.ssetBusy) return;
        this.saveSiteSettings({ redirects: draft }, 'Saving redirects…', 'Redirects saved. They take effect in a few seconds.')
          .then(ok => { if (ok) this.setState({ ssetRdOpen: false }); });
      };
    }
    return out;
  }
});
