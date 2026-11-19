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

  // A Kinsta operation (PHP switch, restart, cache clear) runs for a while
  // after Kinsta accepts it, and Kinsta fails anything else sent to that
  // environment meanwhile. The server keeps the running operation and reports
  // it as `pending`; the card shows it, holds every control, keeps the old
  // PHP version, and polls until it ends. The toast that started it stays a
  // spinner until then and finishes with Kinsta's real outcome.
  trackKinstaOp(key, tid, kind, env) {
    this._ssetOp = { key, tid, kind, env };
  },

  finishKinstaOp(key, res) {
    const op = this._ssetOp;
    if (!op || op.key !== key || (res && res.pending)) return;
    this._ssetOp = null;
    if (res && res.error) { this.updateToast(op.tid, res.error, { kind: 'error', timeout: 12000 }); return; }
    const text = op.kind === 'php' ? op.env + ' is now on PHP ' + ((res && res.php && res.php.version) || '?')
      : op.kind === 'restart' ? 'PHP restarted on ' + op.env : 'Cache cleared at Kinsta';
    this.updateToast(op.tid, text, { kind: 'success', timeout: 7000 });
  },

  loadSiteSettings(force) {
    const key = this.siteSettingsKey();
    // Left the environment while Kinsta works: let go of the spinner.
    if (this._ssetOp && this._ssetOp.key !== key) {
      this.updateToast(this._ssetOp.tid, 'Kinsta is still working on ' + this._ssetOp.env + '. Its Settings card shows when it is done.', { kind: 'info', timeout: 6000 });
      this._ssetOp = null;
    }
    if (!key || (!force && this._sset && this._sset.key === key) || this._ssetLoading === key) return;
    this._ssetLoading = key;
    const [siteId, env] = key.split('|');
    this.api('/sites/' + siteId + '/' + encodeURIComponent(env) + '/host-settings').then(res => {
      this._ssetLoading = null;
      if (key !== this.siteSettingsKey()) return;
      if (!res || res.code) { this._sset = { key, error: (res && res.message) || 'Could not load the settings' }; this.setState({}); return; }
      this._sset = { key, data: res };
      this.finishKinstaOp(key, res);
      this.setState({});
      this.pollSiteSettings(res);
    }).catch(() => { this._ssetLoading = null; this._sset = { key, error: 'Could not load the settings' }; this.setState({}); });
  },

  // While a change is applied on our servers, or Kinsta runs an operation,
  // check again (Kinsta less often: its API rate-limits).
  pollSiteSettings(res) {
    clearTimeout(this._ssetPoll);
    if (res && res.applying) this._ssetPoll = setTimeout(() => this.loadSiteSettings(true), res.host === 'kinsta' ? 8000 : 5000);
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
        if (res.host === 'kinsta' && res.pending) {
          this.updateToast(tid, doneText, { kind: 'loading' });
          this.trackKinstaOp(key, tid, res.pending.kind, env);
        } else {
          this.updateToast(tid, doneText, { kind: 'success', timeout: 7000 });
        }
        this.pollSiteSettings(res);
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
        if (res.host === 'kinsta' && res.settings) {
          this._sset = { key, data: res.settings };
          this.updateToast(tid, 'Kinsta is clearing the cache of ' + env + '…', { kind: 'loading' });
          this.trackKinstaOp(key, tid, 'cache', env);
          this.pollSiteSettings(res.settings);
        } else {
          this.updateToast(tid, 'Cache cleared (' + n + ' saved ' + (n === 1 ? 'page' : 'pages') + ')', { kind: 'success', timeout: 5000 });
        }
        this.setState({ ssetClearing: false });
      })
      .catch(e => { this.setState({ ssetClearing: false }); this.updateToast(tid, (e && e.message) || 'Could not clear the cache', { kind: 'error', timeout: 8000 }); });
  },

  restartSitePhp() {
    const key = this.siteSettingsKey();
    if (!key || this.state.ssetRestarting) return;
    const [siteId, env] = key.split('|');
    this.setState({ ssetRestarting: true });
    const tid = this.toast('Restarting PHP on ' + env + '…', { kind: 'loading' });
    this.api('/sites/' + siteId + '/' + encodeURIComponent(env) + '/restart-php', { method: 'POST', body: {} })
      .then(res => {
        if (!res || res.code) throw new Error((res && res.message) || 'PHP did not restart');
        if (res.host === 'kinsta' && res.settings) {
          this._sset = { key, data: res.settings };
          this.updateToast(tid, 'Kinsta is restarting PHP on ' + env + '…', { kind: 'loading' });
          this.trackKinstaOp(key, tid, 'restart', env);
          this.pollSiteSettings(res.settings);
        } else {
          const ok = res.health === '200';
          this.updateToast(tid, ok ? 'PHP restarted. The site answers again.' : 'PHP restarted, but the site answered ' + (res.health || 'nothing') + '. Check it.',
            { kind: ok ? 'success' : 'error', timeout: 7000 });
        }
        this.setState({ ssetRestarting: false });
      })
      .catch(e => { this.setState({ ssetRestarting: false }); this.updateToast(tid, (e && e.message) || 'PHP did not restart', { kind: 'error', timeout: 8000 }); });
  },

  siteSettingsVals(real, s) {
    const host = this.siteSettingsHost(real, s);
    const off = { ssetShow: false, ssetPhpOpen: false, ssetRdOpen: false, ssetKeysShow: false };
    if (!host) return off;
    const key = this.siteSettingsKey();
    if (!this._sset || this._sset.key !== key) setTimeout(() => this.loadSiteSettings(), 0);
    const ss = this._sset && this._sset.key === key ? this._sset : null;
    const d = ss && ss.data;
    const env = s.env || 'Production';
    const onKinsta = host === 'kinsta';

    const rows = [];
    const pend = onKinsta && d && d.pending ? d.pending : null;
    const held = fn => pend ? () => {} : fn;
    const phpV = d ? (d.php.version || '?') + (pend && pend.kind === 'php' ? ' → ' + pend.version : '') : (ss && ss.error ? '?' : '…');
    rows.push({ k: 'PHP', v: phpV, act: pend && pend.kind === 'php' ? 'Switching…' : 'Change…', go: held(() => d && this.setState({ ssetPhpOpen: true, ssetPhpPick: d.php.version })),
      act2: s.ssetRestarting || (pend && pend.kind === 'restart') ? 'Restarting…' : 'Restart', go2: held(() => d && this.restartSitePhp()) });
    if (!d || d.cache) rows.push({ k: 'Cache', v: '', act: s.ssetClearing || (pend && pend.kind === 'cache') ? 'Clearing…' : 'Clear cache', go: held(() => d && this.clearSiteCache()) });
    if (!onKinsta && (!d || Array.isArray(d.redirects))) {
      const n = d ? d.redirects.length : -1;
      rows.push({ k: 'Redirects', v: n < 0 ? '…' : n === 0 ? 'None' : String(n), act: 'Manage…',
        go: () => { if (!d) return; this._ssetRd = JSON.parse(JSON.stringify(d.redirects)); this.setState({ ssetRdOpen: true, ssetRFrom: '', ssetRTo: '', ssetRCode: 301 }); } });
    }

    const out = {
      ssetShow: true,
      ssetRows: rows.map(r => ({ ...r, hasV: r.v !== '', hasAct2: !!r.act2, act2: r.act2 || '', go2: r.go2 || (() => {}),
        actOp: pend && r.k !== 'Redirects' ? '.45' : '1', actCur: pend && r.k !== 'Redirects' ? 'default' : 'pointer' })),
      // Our servers sign in with the person's own keys (Profile → SSH keys, my-ssh-keys.js).
      ...(host === 'captaincore' && this.myKeysConnectVals ? this.myKeysConnectVals() : { ssetKeysShow: false }),
      ssetApplying: !!(d && d.applying),
      ssetApplyingText: pend
        ? pend.label + ' at Kinsta. This can take a few minutes; the site stays up. Other changes wait until it is done.'
        : 'Applying a change…',
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
        ? 'Kinsta restarts PHP on the new version, which can take a few minutes. Visitors may see a short pause. This card shows the new version once Kinsta has finished.'
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
          onKinsta ? 'Kinsta is switching ' + env + ' to PHP ' + pick + '. This can take a few minutes…' : env + ' is moving to PHP ' + pick + '. It takes about a minute.')
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
