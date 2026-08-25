// CaptainCore v3 — site detail real-data layer (mixin).
// Overrides openSite() to load the real detail bundle and provides the
// real* helpers computeDetail() consults when this._detail matches the open
// site. Endpoints per the v1 contract: /sites/{id}/environments (full env
// records incl. credentials + plugins/themes JSON), /sites/{id}/details
// (site+account+domains+shared_with), /sites/{id}/users (keyed by env name),
// /sites/{id}/{env}/logs [+ /fetch], /sites/{id}/{env}/magiclogin[/{uid}],
// /sites/{id}/{env}/sync/data, /sites/environments/push, /run/code.

Object.assign(Component.prototype, {

  openSite(id, env) {
    this.setState({ route: 'site', siteId: id, siteTab: 'overview', env: env || 'Production', qsOpen: '', bkOpen: '', paletteOpen: false, logFile: '', logMode: 'live', laView: '', capSel: '', capLimit: 60, rgFilter: '' });
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
      // Cold deep link to a lazy tab: goSiteTab ran BEFORE this fetch resolved,
      // so it bailed on the missing _detail and the tab would sit empty until
      // you clicked away and back. Fire the current leaf's load now that the
      // environment list exists (stats/registry/captures key off it).
      const deferred = { sitedomains: 'loadEnvDomains', stats: 'loadStats', registry: 'loadRegistry', captures: 'loadCaptures',
        logs: 'loadLogs', versions: 'loadQuicksaves', backups: 'loadBackups',
        snapshots: 'loadSnapshots', timeline: 'loadTimeline' }[this.state.siteTab];
      if (deferred && this[deferred]) setTimeout(() => this[deferred](), 0);
      // Overview stat tiles (Backups / Versions) read the same lists the
      // History tabs do. Without a prefetch they sat at an em-dash until you
      // visited those tabs; both fetches are cheap (cached list.json / a DB
      // read), so kick them here and let the tiles fill in.
      setTimeout(() => this.loadOverviewCounts(), 0);
      this.syncFleetFromDetail(detail);
      bump();
    }).catch(() => { detail.envs = []; bump(); });
    this.api('/sites/' + id + '/details').then(d => {
      detail.site = d && d.site; detail.account = d && d.account;
      detail.domains = (d && d.domains) || []; detail.sharedWith = (d && d.shared_with) || [];
      this.syncFleetFromDetail(detail);
      bump();
    }).catch(() => bump());
    this.api('/sites/' + id + '/users').then(u => { detail.users = u || {}; bump(); }).catch(() => bump());
    this.loadTimeline();
  },

  // FLEET is built ONCE by hydrate(), but the site header, the Sites row, the
  // ⌘K palette and every ctx menu read from it — so a server-side identity
  // change (Launch swaps the .kinsta.cloud name for the real domain; migrate
  // and rename do the same) left the OLD name on screen until a full browser
  // reload, even though _detail had refreshed. Re-point the FLEET row at the
  // authoritative record whenever the detail loads; every refresh path
  // (tools, terminal sync, rename) already funnels through loadSiteDetail.
  syncFleetFromDetail(detail) {
    if (this._detail !== detail) return; // a newer site won the race
    const src = detail.site;
    if (!src) return;
    const row = this.FLEET.find(x => x.id === String(src.site_id));
    if (!row) return;
    if (src.name) row.name = src.name;
    if (src.site) row.site = src.site;
    if (src.provider) row.provider = String(src.provider).replace(/\b[a-z]/g, c => c.toUpperCase());
    row.providerSiteId = src.provider_site_id || '';
    row.removed = !!src.removed;
    row.unassigned = !src.account_id || src.account_id == '0';
    const acc = (this.ACCOUNTS || []).find(a => a.id === String(src.account_id));
    if (acc) row.account = acc.name;
    if (src.core) row.core = src.core;
    if (src.visits != null && src.visits !== '') row.visits = Number(src.visits).toLocaleString();
    if (src.storage != null && src.storage !== '') row.storage = this.fmtStorage(src.storage);
    // Environments carry the home_url the launch just rewrote.
    if (Array.isArray(detail.envs) && detail.envs.length) {
      row.environmentsRaw = detail.envs;
      row.envs = detail.envs.map(e => e.environment === 'Production' ? 'Prod' : e.environment)
        .filter(Boolean).join(' · ') || 'Prod';
      const prod = detail.envs.find(e => e.environment === 'Production') || detail.envs[0];
      if (prod && prod.home_url) row.home_url = prod.home_url;
    }
  },

  // Backups + quicksaves for the Overview stat tiles. Both loaders are
  // environment-keyed and self-guarding, so this is safe to call on detail
  // load and again on every environment switch.
  loadOverviewCounts() {
    const real = this._detail;
    if (!real || !real.envs || !real.envs.length) return;
    if (this.loadBackups) this.loadBackups();
    if (this.loadQuicksaves) this.loadQuicksaves();
  },

  currentEnv(real, s) {
    if (!real || !real.envs) return null;
    return real.envs.find(e => e.environment === s.env) || real.envs[0] || null;
  },

  setEnv(name) {
    const real = this._detail;
    if (real && real.envs && !real.envs.some(e => e.environment === name)) return;
    this.setState({ env: name, logFile: '', laView: '', capSel: '', capLimit: 60, rgHash: '', rgDetail: null, rgOpenIdx: -1, rgFilter: '' });
    if (real && this.state.siteTab === 'logs') this.loadLogs(name);
    if (real && this.state.siteTab === 'logs' && this.state.logMode === 'archive') setTimeout(() => this.loadLogsArchive(name), 0);
    if (real && this.state.siteTab === 'registry') setTimeout(() => this.loadRegistry(), 0);
    if (real && this.state.siteTab === 'stats') setTimeout(() => this.loadStats(), 0);
    if (real && this.state.siteTab === 'captures') setTimeout(() => this.loadCaptures(), 0);
    if (real) setTimeout(() => this.loadOverviewCounts(), 0);
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

  // ── Managed-update settings dialog (v1 parity: dialog_update_settings) ──
  // PUT /sites/{id}/settings { environment, value: { updates_enabled,
  // updates_exclude_plugins: [], updates_exclude_themes: [] } }; the handler
  // stores per-env and dispatches its own `site sync`.
  openUpdSettings(real, s) {
    const e = this.currentEnv(real, s);
    if (!e) return;
    const csv = v => String(v || '').split(',').map(x => x.trim()).filter(Boolean);
    this.setState({ usOpen: true,
      usEnabled: !!(e.updates_enabled && e.updates_enabled !== '0'),
      usExPlugins: csv(e.updates_exclude_plugins),
      usExThemes: csv(e.updates_exclude_themes) });
  },

  updSettingsVals(real, s) {
    if (!s.usOpen || !real) return { usOpen: false };
    const e = this.currentEnv(real, s) || {};
    const mkChips = (list, key) => (list || []).map(p => { const slug = p.name || p.slug || '';
      const on = (this.state[key] || []).includes(slug);
      return { label: slug,
        bg: on ? 'var(--warn-soft)' : 'var(--panel-2)', fg: on ? 'var(--ink)' : 'var(--ink-dim)',
        bd: on ? 'var(--warn)' : 'var(--rule)',
        toggle: () => this.setState(st => ({ [key]: (st[key] || []).includes(slug)
          ? st[key].filter(x => x !== slug) : [...(st[key] || []), slug] })) };
    }).filter(c => c.label);
    return {
      usOpen: true,
      usEnvLabel: e.environment || 'Production',
      usEnabled: !!s.usEnabled,
      usFlip: () => this.setState(st => ({ usEnabled: !st.usEnabled })),
      usTogBg: s.usEnabled ? 'var(--ok)' : 'var(--rule)',
      usTogJust: s.usEnabled ? 'flex-end' : 'flex-start',
      usPluginChips: mkChips(e.plugins, 'usExPlugins'),
      usThemeChips: mkChips(e.themes, 'usExThemes'),
      usClose: () => this.setState({ usOpen: false }),
      usSave: () => {
        const st = this.state;
        const value = { updates_enabled: st.usEnabled ? '1' : '0',
          updates_exclude_plugins: st.usExPlugins || [], updates_exclude_themes: st.usExThemes || [] };
        const tid = this.toast('Saving update settings…', { kind: 'loading' });
        this.api('/sites/' + real.siteId + '/settings', { method: 'PUT',
          body: { environment: e.environment || 'Production', value } })
          .then(() => {
            // Reflect the save locally; the handler's own `site sync` will
            // refresh the canonical row later.
            e.updates_enabled = value.updates_enabled;
            e.updates_exclude_plugins = value.updates_exclude_plugins.join(',');
            e.updates_exclude_themes = value.updates_exclude_themes.join(',');
            this.updateToast(tid, 'Update settings saved', { kind: 'success' });
            this.setState({ usOpen: false });
          })
          .catch(() => this.updateToast(tid, 'Could not save update settings', { kind: 'error' }));
      }
    };
  },

  // ── Environment domain mappings (Inventory › Domains; v1 parity:
  // dialog_domain_mappings). Provider-backed (Kinsta / Rocket.net):
  // GET/POST/DELETE /sites/{id}/{env}/domains + PUT …/domains/primary.
  // Writes are async at the provider, so v1's refetch-after-5s ritual carries
  // over. Rows: { id, name, is_active, verification_records }.
  _sdKey() {
    const real = this._detail;
    return real ? real.siteId + ':' + (this.state.env || 'Production') : '';
  },

  loadEnvDomains(force) {
    const real = this._detail;
    if (!real) return;
    const key = this._sdKey();
    if (!force && this._sd && this._sd.key === key) return;
    const sd = this._sd = { key, list: null, error: '' };
    const bump = () => { if (this._sd === sd) this.setState({}); };
    this.api('/sites/' + real.siteId + '/' + (this.state.env || 'Production').toLowerCase() + '/domains')
      .then(list => {
        if (list && list.code) { sd.error = list.message || 'Could not load domains.'; sd.list = []; }
        else sd.list = Array.isArray(list) ? list : [];
        bump();
      })
      .catch(() => { sd.error = 'Could not load domains.'; sd.list = []; bump(); });
  },

  _sdRefetchSoon() {
    clearTimeout(this._sdTimer);
    this._sdTimer = setTimeout(() => this.loadEnvDomains(true), 5000);
  },

  envDomainsVals(real, s) {
    if (s.siteTab !== 'sitedomains' || !real) return { sdSupported: false, sdUnsupported: false, sdRows: [], sdLoading: false, sdHasError: false, sdError: '' };
    const f = this.FLEET.find(x => String(x.id) === String(real.siteId));
    const provider = ((real.site && real.site.provider) || (f && f.provider) || '').toLowerCase();
    const supported = provider === 'kinsta' || provider === 'rocketdotnet';
    if (!supported) return { sdSupported: false, sdUnsupported: true, sdRows: [], sdLoading: false, sdHasError: false, sdError: '' };
    const e = this.currentEnv(real, s) || {};
    const home = String(e.home_url || '');
    const sd = (this._sd && this._sd.key === this._sdKey()) ? this._sd : null;
    // Env switch while on the tab: the cached key no longer matches — refetch.
    if (!sd) setTimeout(() => this.loadEnvDomains(), 0);
    const list = (sd && sd.list) || [];
    const envLower = (s.env || 'Production').toLowerCase();
    const path = '/sites/' + real.siteId + '/' + envLower + '/domains';
    return {
      sdSupported: true, sdUnsupported: false,
      sdLoading: !sd || sd.list === null,
      sdHasError: !!(sd && sd.error), sdError: (sd && sd.error) || '',
      sdEmpty: !!sd && Array.isArray(sd.list) && !sd.list.length && !sd.error,
      sdNew: s.sdNew || '', onSdNew: ev => this.setState({ sdNew: ev.target.value }),
      sdAdd: () => {
        const name = (this.state.sdNew || '').trim().replace(/^https?:\/\//, '').replace(/\/$/, '');
        if (!name) return;
        const tid = this.toast('Adding ' + name + '…', { kind: 'loading' });
        this.api(path, { method: 'POST', body: { domain_name: name } })
          .then(() => { this.updateToast(tid, name + ' is being added', { kind: 'success' });
            this.setState({ sdNew: '' }); this._sdRefetchSoon(); })
          .catch(() => this.updateToast(tid, 'Could not add ' + name, { kind: 'error' }));
      },
      sdRows: list.map(d => {
        const name = d.name || '';
        const system = name.includes('kinsta.cloud') || name.includes('onrocket.site');
        const primary = !!name && home.includes(name);
        // Pending domains carry the DNS records Kinsta wants to see before it
        // activates the mapping — show them so "Pending DNS" is actionable.
        const recs = (Array.isArray(d.verification_records) ? d.verification_records : []).map(r => ({
          type: String(r.type || '').toUpperCase(), rname: r.name || '', value: r.value || '',
          copy: () => { try { navigator.clipboard.writeText(r.value || ''); } catch (e) {}
            this.toast('Record value copied', { kind: 'success' }); } }));
        return {
          name, system, primary,
          active: !!d.is_active,
          hasRecs: !system && !d.is_active && recs.length > 0, recs,
          // Verify: re-check with Kinsta and force re-inject the verification
          // records into the hosted zone (bypasses the daily provision cache).
          canVerify: !system && !d.is_active,
          verify: () => {
            const tid = this.toast('Verifying ' + name + '…', { kind: 'loading' });
            this.api(path + '/verify', { method: 'POST', body: { domain_id: d.id } }).then(res => {
              if (res && res.code) { this.updateToast(tid, res.message || 'Verification failed', { kind: 'error' }); return; }
              if (res && res.active) { this.updateToast(tid, name + ' is verified', { kind: 'success' }); this.loadEnvDomains(true); return; }
              const p = (res && res.provision) || '';
              const msg = p === 'provisioned' ? 'DNS records added to the hosted zone — Kinsta usually verifies within a few minutes.'
                : p === 'exists' ? 'Records are in place — waiting on Kinsta to verify.'
                : p === 'no_zone' || p === 'not_ours' ? 'DNS for this domain is not hosted here — add the records shown at its DNS host.'
                : 'Still pending — records refreshed.';
              this.updateToast(tid, msg, { kind: p === 'no_zone' || p === 'not_ours' ? 'info' : 'success' });
              this._sdRefetchSoon();
            }).catch(() => this.updateToast(tid, 'Verification failed', { kind: 'error' }));
          },
          statusLabel: d.is_active ? 'Active' : 'Pending DNS',
          stBg: d.is_active ? 'var(--ok-soft)' : 'var(--warn-soft)',
          badge: system ? 'System' : primary ? 'Primary' : '',
          // Pending domains can be made primary too (Kinsta's change-primary
          // takes any mapped domain) — the confirm carries an extra warning
          // since the site may be unreachable on it until DNS verifies.
          canPrimary: !system && !primary,
          setPrimary: () => {
            const warn = d.is_active
              ? 'Set ' + name + ' as the primary domain? This runs a search-and-replace on the site.'
              : 'Set ' + name + ' as the primary domain? Its DNS has not verified yet, so the site may be unreachable on it until DNS is in place. This also runs a search-and-replace on the site.';
            if (!confirm(warn)) return;
            const tid = this.toast('Setting ' + name + ' as primary…', { kind: 'loading' });
            this.api(path + '/primary', { method: 'PUT', body: { domain_id: d.id, run_search_and_replace: true } })
              .then(() => { this.updateToast(tid, name + ' is becoming primary (may take a few minutes)', { kind: 'success' }); this._sdRefetchSoon(); })
              .catch(() => this.updateToast(tid, 'Could not set primary', { kind: 'error' }));
          },
          canDel: !system && !primary,
          del: () => {
            if (!confirm('Delete the domain ' + name + '? This cannot be undone.')) return;
            const tid = this.toast('Deleting ' + name + '…', { kind: 'loading' });
            this.api(path, { method: 'DELETE', body: { domain_ids: [d.id] } })
              .then(() => { this.updateToast(tid, name + ' is being deleted', { kind: 'success' }); this._sdRefetchSoon(); })
              .catch(() => this.updateToast(tid, 'Could not delete ' + name, { kind: 'error' }));
          }
        };
      })
    };
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

  realMagicLogin(real, s, user) { return this.magicLogin(real.siteId, s.env.toLowerCase(), user); },

  magicLogin(siteId, envLower, user) {
    const who = (user && (user.display_name || user.user_login)) ? ' as ' + (user.display_name || user.user_login) : '';
    const tid = this.toast('Signing in' + who + '…', { kind: 'loading' });
    const path = '/sites/' + siteId + '/' + envLower + '/magiclogin' + (user && user.ID ? '/' + user.ID : '');
    this.api(path).then(url => {
      if (typeof url === 'string' && /^https?:\/\//i.test(url.trim())) {
        this.safeOpen(url.trim());
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
    this.realPushTo(real, source.environment_id, target.environment_id,
      source.environment.toLowerCase() + ' → ' + target.environment.toLowerCase() + ' on ' + name);
  },

  // Targets for "Push to another site" — the provider endpoint returns every
  // environment (same Kinsta account, permission-filtered) except the source.
  loadPushTargets(real, s) {
    const env = real.envs && real.envs.find(e => e.environment === s.env);
    if (!env) { this.setState({ ptoTargets: [] }); return; }
    this.api('/sites/' + real.siteId + '/environments/' + env.environment_id + '/push-targets')
      .then(list => this.setState({ ptoTargets: Array.isArray(list) ? list : [] }))
      .catch(err => { console.warn('push-targets failed', err); this.toast('Could not load push targets', { kind: 'error' }); this.setState({ ptoTargets: [] }); });
  },

  // Bindings for the deploy confirm dialog (spread into computeDetail's return).
  // dir 'other' confirms a push onto the target picked in the pto dialog.
  computeDeployConfirm(real, s, site) {
    const dir = s.deployConfirm;
    const t = s.ptoSel || {};
    const from = dir === 'up' ? 'staging on ' + site.name
      : dir === 'down' ? 'production on ' + site.name
      : (s.env || '').toLowerCase() + ' on ' + site.name;
    const to = dir === 'up' ? 'production on ' + site.name
      : dir === 'down' ? 'staging on ' + site.name
      : (t.environment || '').toLowerCase() + ' on ' + (t.name || '');
    const label = from + ' → ' + to;
    return {
      depOpen: !!dir,
      depTitle: dir === 'up' ? 'Push to production' : dir === 'down' ? 'Pull to staging' : 'Push to another site',
      depFrom: from, depTo: to,
      depWarn: 'This overwrites ' + to + ' with a copy of ' + from + '. Anything on the target that isn’t in the copy will be lost.',
      depBtn: dir === 'up' ? 'Push to production' : dir === 'down' ? 'Pull to staging' : 'Push to ' + (t.name || 'site'),
      // Red whenever a production environment is about to be overwritten.
      depBtnBg: (dir === 'up' || (dir === 'other' && t.environment === 'Production')) ? 'var(--bad)' : 'var(--brand)',
      closeDep: () => this.setState({ deployConfirm: '', ptoSel: null }),
      depGo: () => {
        if (!dir) return;
        this.setState({ deployConfirm: '', ptoSel: null });
        if (dir === 'other') {
          if (!t.environment_id) return;
          if (!real) { this.runJob('deploy', label); return; }
          const env = real.envs && real.envs.find(e => e.environment === s.env);
          if (env) this.realPushTo(real, env.environment_id, t.environment_id, label);
          return;
        }
        if (real) this.realPush(real, dir);
        else this.runJob('deploy', (dir === 'up' ? 'staging → production' : 'production → staging') + ' on ' + site.name);
      },
    };
  },

  // Bindings for the "Push to another site" target-picker dialog.
  computePushToOther(real, s, site) {
    const q = (s.ptoQ || '').toLowerCase();
    const loaded = Array.isArray(s.ptoTargets);
    const rows = (loaded ? s.ptoTargets : [])
      .filter(t => !q || (t.name || '').toLowerCase().includes(q) || (t.home_url || '').toLowerCase().includes(q))
      .slice(0, 100)
      .map(t => ({
        label: t.name + ' (' + (t.environment || '').toLowerCase() + ')',
        sub: t.home_url || '',
        pick: () => this.setState({ ptoOpen: false, ptoSel: t, deployConfirm: 'other' }),
      }));
    return {
      pushOther: () => {
        // Sample mode demos with other fleet sites; real mode fetches the
        // provider's permission-filtered target list.
        this.setState({ ptoOpen: true, ptoQ: '', ptoSel: null,
          ptoTargets: real ? null : this.FLEET.filter(x => x.id !== site.id).slice(0, 12)
            .map(x => ({ site_id: x.id, name: x.name, environment: 'Production', environment_id: -1, home_url: x.name })) });
        if (real) this.loadPushTargets(real, s);
      },
      ptoOpen: !!s.ptoOpen,
      closePto: () => this.setState({ ptoOpen: false }),
      ptoTitle: 'Push ' + site.name + ' (' + (s.env || '').toLowerCase() + ') to…',
      ptoQ: s.ptoQ, onPtoQ: e => this.setState({ ptoQ: e.target.value }),
      ptoLoading: !!s.ptoOpen && !loaded,
      ptoEmpty: !!s.ptoOpen && loaded && rows.length === 0,
      ptoHasRows: rows.length > 0,
      ptoRows: rows,
    };
  },

  realPushTo(real, sourceEnvId, targetEnvId, label) {
    // Push is a provider operation (202 + operation_id), not a token job —
    // the dock entry is resolved by polling /provider-actions/check until the
    // registered action leaves the active list (v1: checkProviderActions).
    const jobId = this.startJob({
      label: 'deploy', target: label,
      command: 'push', siteId: real.siteId
    });
    const job = this._jobObjs[jobId];
    this.api('/sites/environments/push', { method: 'POST',
      body: { source_environment_id: sourceEnvId, target_environment_id: targetEnvId } })
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
      if (typeof url === 'string' && /^https?:\/\//i.test(url.trim())) {
        this.safeOpen(url.trim());
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
    }).catch(err => this.setState({ shareSending: false,
      // api() throws 'auth' on 401/403 before the body is readable — name the
      // real reason instead of the generic line.
      shareErr: (err && err.message === 'auth') ? 'Your access level does not allow sending invites.' : 'Error sending invite.' }));
  },

  // Bindings for the Share Access dialog (spread into computeDetail's return).
  computeShareDialog(real, s, site) {
    const p = this._sharePreview;
    const email = (s.shareEmail || '').trim();
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    const siteName = (real && real.site && real.site.name) || site.name;
    // Both lists are capped so a big account (10 sites / 28 domains here)
    // stays scannable; the remainder is stated, never silently dropped.
    const CAP = 12;
    const chipsOf = (list, key) => {
      const all = (list || []).map(x => x.name).filter(Boolean);
      return { chips: all.slice(0, CAP).map(name => ({ name })),
        more: all.length > CAP ? '+' + (all.length - CAP) + ' more' : '',
        moreShow: all.length > CAP };
    };
    const siteChips = chipsOf(p && p.sites_list);
    const domChips = chipsOf(p && p.domains_list);
    let acctLead = '', acctSites = '', acctSitesList = '', acctDomains = '', simpleLine = '';
    if (p && p.has_account_access) {
      acctLead = 'Inviting ' + email + ' will grant them access to the account ' + (p.account_name || '') + '. This includes:';
      const n = p.total_sites || 0;
      acctSites = n + ' Website' + (n === 1 ? '' : 's');
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
      // Named lists — the payload carries domains_list too, which v1 fetches
      // and then throws away (it only ever printed the domain COUNT).
      shSiteChips: siteChips.chips, shSiteMore: siteChips.more, shSiteMoreShow: siteChips.moreShow,
      shDomChips: domChips.chips, shDomMore: domChips.more, shDomMoreShow: domChips.moreShow,
      shHasDomList: !!(p && p.has_account_access && domChips.chips.length),
      shSimpleLine: simpleLine,
      shareErr: s.shareErr, shareHasErr: !!s.shareErr,
      shareSendLabel: s.shareSending ? 'Sending…' : 'Send invite',
      shareCanSend: valid && !s.shareSending,
      shareSendBg: valid && !s.shareSending ? 'var(--brand)' : 'var(--rule)',
      shareSend: () => this.sendSiteInvite()
    };
  },

  // ── Accounts card (customer/billing contacts + assignment) ────
  // Scoped write: PUT /sites/{id}/accounts {customer_id, account_id,
  // shared_with} — never the monolithic /sites/update, whose payload
  // round-trips environments and deletes any it doesn't carry.
  siteAcctIds(real) {
    return (real && real.sharedWith ? real.sharedWith : []).map(a => String(a.account_id));
  },

  saveSiteAccounts(patch, doneMsg) {
    const real = this._detail;
    if (!real || !real.site || this._acctSaving) return;
    this._acctSaving = true;
    const body = {
      customer_id: patch.customer_id !== undefined ? patch.customer_id : (real.site.customer_id || ''),
      account_id: patch.account_id !== undefined ? patch.account_id : (real.site.account_id || ''),
      shared_with: patch.shared_with !== undefined ? patch.shared_with : this.siteAcctIds(real)
    };
    const tid = this.toast('Saving account assignment…', { kind: 'loading' });
    this.api('/sites/' + real.siteId + '/accounts', { method: 'PUT', body }).then(res => {
      this._acctSaving = false;
      if (!res || res.code || !res.site) {
        this.updateToast(tid, (res && res.message) || 'Could not save account assignment', { kind: 'error' });
        return;
      }
      if (this._detail === real) {
        real.site = res.site;
        real.sharedWith = res.shared_with || [];
      }
      this.updateToast(tid, doneMsg || 'Account assignment saved', { kind: 'success' });
      this.setState({ tick: this.state.tick });
    }).catch(() => { this._acctSaving = false; this.updateToast(tid, 'Could not save account assignment', { kind: 'error' }); });
  },

  // Bindings for the assign-account picker (spread into computeDetail's return).
  computeAssignAccount(real, s) {
    const assigned = new Set(this.siteAcctIds(real));
    const q = (s.asgQ || '').toLowerCase();
    const rows = (s.asgOpen ? this.ACCOUNTS : [])
      .filter(a => !assigned.has(String(a.id)))
      .filter(a => !q || (a.name || '').toLowerCase().includes(q))
      .slice(0, 100)
      .map(a => ({
        label: a.name,
        sub: [a.sites + ' site' + (a.sites === 1 ? '' : 's'), a.plan].filter(Boolean).join(' · '),
        pick: () => {
          this.setState({ asgOpen: false });
          this.saveSiteAccounts({ shared_with: [...assigned, String(a.id)] }, 'Assigned ' + a.name);
        }
      }));
    return {
      acctEdit: !!real && ((window.CC_BOOT && window.CC_BOOT.dcRole) || 'operator') === 'operator',
      openAsg: () => this.setState({ asgOpen: true, asgQ: '' }),
      asgOpen: !!s.asgOpen,
      closeAsg: () => this.setState({ asgOpen: false }),
      asgQ: s.asgQ || '', onAsgQ: e => this.setState({ asgQ: e.target.value }),
      asgRows: rows, asgHasRows: rows.length > 0, asgEmpty: !!s.asgOpen && rows.length === 0
    };
  },

  // ── Edit site (identity) + environment connection editing ─────
  // Operator-only. Scoped routes — PUT /sites/{id}/identity and
  // PUT|DELETE /sites/{id}/environments/{environment_id} — so a partial
  // payload can never delete an environment (the v1 monolith's trap:
  // Site::update removes any environment missing from its payload).
  ES_PROVIDERS: [['kinsta', 'Kinsta'], ['gridpane', 'GridPane'], ['rocketdotnet', 'Rocket.net'], ['wpengine', 'WP Engine']],

  openEditSite() {
    const real = this._detail;
    if (!real || !real.site) return;
    this._es = { name: real.site.name || '', provider: real.site.provider || '', key: real.site.key || '' };
    this.setState({ edsOpen: true, edsSaving: false, edsEnvBusy: '' });
    if (this._esKeys === undefined) {
      this._esKeys = null; // loading
      this.api('/keys/').then(list => { this._esKeys = Array.isArray(list) ? list : []; this.setState({ tick: this.state.tick }); })
        .catch(() => { this._esKeys = []; this.setState({ tick: this.state.tick }); });
    }
  },

  saveEditSite() {
    const real = this._detail, d = this._es || {};
    if (!real || this.state.edsSaving) return;
    if (!(d.name || '').trim()) { this.toast('Domain name is required', { kind: 'error' }); return; }
    this.setState({ edsSaving: true });
    this.api('/sites/' + real.siteId + '/identity', { method: 'PUT',
      body: { name: d.name.trim(), provider: d.provider || '', key: d.key === '' ? '' : d.key } }).then(res => {
      if (!res || res.code || !res.site) {
        this.setState({ edsSaving: false });
        this.toast((res && res.message) || 'Could not save site', { kind: 'error' });
        return;
      }
      if (this._detail === real) real.site = res.site;
      const f = this.FLEET.find(x => x.id === String(real.siteId));
      if (f) { f.name = res.site.name; f.provider = (res.site.provider || '').replace(/\b[a-z]/g, c => c.toUpperCase()); }
      this.setState({ edsOpen: false, edsSaving: false });
      this.toast('Site updated', { kind: 'success' });
    }).catch(() => { this.setState({ edsSaving: false }); this.toast('Could not save site', { kind: 'error' }); });
  },

  computeEditSite(real, s) {
    const isOp = ((window.CC_BOOT && window.CC_BOOT.dcRole) || 'operator') === 'operator';
    const d = this._es || {};
    const keys = this._esKeys;
    return {
      edsShow: !!real && isOp,
      edsOpenDlg: () => this.openEditSite(),
      edsOpen: !!s.edsOpen,
      closeEds: () => this.setState({ edsOpen: false }),
      edsName: d.name || '', onEdsName: e => { this._es.name = e.target.value; },
      edsSlug: (real && real.site && real.site.site) || '',
      edsProviders: this.ES_PROVIDERS.map(([value, label]) => ({ label,
        bg: (d.provider || '') === value ? 'var(--panel-2)' : 'transparent',
        fg: (d.provider || '') === value ? 'var(--ink)' : 'var(--ink-dim)',
        go: () => { this._es.provider = value; this.setState({ tick: this.state.tick }); } })),
      edsKeysLoading: keys === null,
      edsKeys: [{ key_id: '', title: 'Default (primary SSH key)' }, ...(Array.isArray(keys) ? keys : [])].map(k => ({
        label: k.title,
        mark: String(d.key || '') === String(k.key_id || '') ? '✓' : '',
        bg: String(d.key || '') === String(k.key_id || '') ? 'var(--brand-soft)' : 'transparent',
        pick: () => { this._es.key = k.key_id; this.setState({ tick: this.state.tick }); } })),
      edsSaveLabel: s.edsSaving ? 'Saving…' : 'Save changes',
      edsSave: () => this.saveEditSite(),
      ...this.computeEditSiteEnvs(real, s)
    };
  },

  // ── Edit site → Environments section ──────────────────────────
  // Three ways to end up with a Staging row, in the order an operator should
  // reach for them:
  //   1. Create staging  — POST /providers/{p}/deploy-to-staging. Provisions
  //      it AT the host (Kinsta clones live → staging) and queues a
  //      ProviderAction whose last step calls connect_staging() for us. The
  //      browser must poll or the chain never advances.
  //   2. Link staging    — POST /sites/{id}/environments/connect. The staging
  //      already exists at the host; we only missed the record. Reports
  //      'none' (nothing to link) / 'exists' / 'skipped' rather than erroring.
  //   3. Add manually    — POST /sites/{id}/environments. Connection details
  //      typed in, for providers with no API for any of this.
  computeEditSiteEnvs(real, s) {
    const site = (real && real.site) || {};
    const envs = (real && real.envs) || [];
    const provider = String(site.provider || '');
    const provLabel = (this.ES_PROVIDERS.find(([v]) => v === provider) || [null, provider])[1] || provider;
    const hasStaging = envs.some(e => e.environment === 'Staging');
    // Provider automation needs a live link to a host-side site record.
    const linked = !!provider && !!site.provider_site_id;
    const busy = s.edsEnvBusy || '';
    return {
      edsEnvRows: envs.map(e => ({
        label: e.environment,
        sub: [e.address, e.port].filter(Boolean).join(':') || 'no connection details',
        subFg: e.address ? 'var(--ink-dim)' : 'var(--bad)',
        edit: () => this.openEnvEdit(e.environment_id)
      })),
      edsEnvEmpty: envs.length === 0,
      // Reconcile address/port/user/password/web-root from the provider.
      edsPullShow: linked,
      edsPullLabel: busy === 'sync' ? 'Pulling…' : 'Pull from ' + provLabel,
      edsPull: () => this.pullEnvFromProvider(),
      edsStagingShow: !hasStaging,
      edsStagingNote: linked
        ? 'No staging environment. Create one at ' + provLabel + ', link one that already exists there, or add the connection by hand.'
        : 'No staging environment. Add its connection details by hand.',
      edsCreateStagingShow: linked,
      edsCreateStagingLabel: busy === 'create' ? 'Creating…' : 'Create staging at ' + provLabel,
      edsCreateStaging: () => this.createProviderStaging(),
      edsLinkStagingShow: linked,
      edsLinkStagingLabel: busy === 'link' ? 'Linking…' : 'Link existing staging',
      edsLinkStaging: () => this.linkProviderStaging(),
      edsAddStaging: () => this.openEnvNew('Staging')
    };
  },

  // Pull live connection details from the provider onto the local rows.
  // The route answers with { status, message, changes[], environments? }.
  pullEnvFromProvider() {
    const real = this._detail;
    if (!real || this.state.edsEnvBusy) return;
    this.setState({ edsEnvBusy: 'sync' });
    this.api('/sites/' + real.siteId + '/remote-sync', { method: 'POST', body: {} }).then(res => {
      this.setState({ edsEnvBusy: '' });
      if (!res || res.code) { this.toast((res && res.message) || 'Could not reach the provider', { kind: 'error' }); return; }
      if (Array.isArray(res.environments) && this._detail === real) real.envs = res.environments;
      const n = Array.isArray(res.changes) ? res.changes.length : 0;
      this.toast(res.message || (n ? n + ' field(s) updated' : 'Already in sync'),
        { kind: res.status === 'error' ? 'error' : res.status === 'updated' ? 'success' : 'info' });
      this.setState({ tick: this.state.tick });
    }).catch(() => { this.setState({ edsEnvBusy: '' }); this.toast('Could not reach the provider', { kind: 'error' }); });
  },

  linkProviderStaging() {
    const real = this._detail;
    if (!real || this.state.edsEnvBusy) return;
    this.setState({ edsEnvBusy: 'link' });
    this.api('/sites/' + real.siteId + '/environments/connect', { method: 'POST', body: { environment: 'Staging' } }).then(res => {
      this.setState({ edsEnvBusy: '' });
      if (!res || res.code) { this.toast((res && res.message) || 'Could not link staging', { kind: 'error' }); return; }
      if (res.status !== 'connected') { this.toast(res.message || 'Nothing to link', { kind: 'info' }); return; }
      this.toast('Staging environment connected', { kind: 'success' });
      this.reloadSiteDetail();
    }).catch(() => { this.setState({ edsEnvBusy: '' }); this.toast('Could not link staging', { kind: 'error' }); });
  },

  // Kinsta clones live → staging server-side; the response is an operation id,
  // not a finished environment. pollProviderActions drives the rest of the
  // chain (its last step links the new environment) and toasts on completion.
  createProviderStaging() {
    const real = this._detail;
    if (!real || !real.site || this.state.edsEnvBusy) return;
    const provider = real.site.provider;
    const name = real.site.name || 'this site';
    if (!confirm('Create a staging environment for ' + name + ' at ' + provider + '?\n\nThe host copies production into a new staging environment. This can take several minutes.')) return;
    this.setState({ edsEnvBusy: 'create' });
    this.api('/providers/' + provider + '/deploy-to-staging', { method: 'POST', body: { site_id: real.siteId } }).then(res => {
      this.setState({ edsEnvBusy: '' });
      if (!res || res.code || res === false) { this.toast((res && res.message) || 'Could not start the staging build', { kind: 'error' }); return; }
      this.toast('Staging environment is being created — it will appear here once the host finishes', { kind: 'success' });
      this.setState({ edsOpen: false });
      if (this.pollProviderActions) this.pollProviderActions();
    }).catch(() => { this.setState({ edsEnvBusy: '' }); this.toast('Could not start the staging build', { kind: 'error' }); });
  },

  // Force a fresh detail load (loadSiteDetail short-circuits on a matching id).
  reloadSiteDetail() {
    const real = this._detail;
    if (!real) return;
    const id = real.siteId;
    this._detail = null;
    this.loadSiteDetail(id);
  },

  // environmentId targets a specific row (the Edit site dialog's Environments
  // list); omitted, it edits whichever environment the header is showing.
  openEnvEdit(environmentId) {
    const real = this._detail;
    const env = environmentId
      ? ((real && real.envs) || []).find(e => String(e.environment_id) === String(environmentId))
      : this.currentEnv(real, this.state);
    if (!real || !env || !env.environment_id) return;
    this._ee = { environment_id: env.environment_id, envName: env.environment,
      address: env.address || '', home_directory: env.home_directory || '',
      username: env.username || '', password: env.password || '',
      protocol: env.protocol || 'sftp', port: env.port || '' };
    this.setState({ eeOpen: true, eeSaving: false });
  },

  // Same dialog, create mode: a draft with no environment_id, which saveEnvEdit
  // POSTs instead of PUTs. "Preload from Production" is the fast path here.
  openEnvNew(name) {
    const real = this._detail;
    if (!real) return;
    this._ee = { environment_id: '', envName: name || 'Staging',
      address: '', home_directory: '', username: '', password: '',
      protocol: 'sftp', port: '' };
    this.setState({ eeOpen: true, eeSaving: false });
  },

  // v1 preloadStagingEnvironment, applied to the dialog draft. The DC runtime
  // binds input value like defaultValue, so the dialog is remounted (close →
  // reopen next tick) to re-seed the visible fields from the mutated draft.
  eePreloadApply() {
    const real = this._detail, d = this._ee;
    const prod = real && real.envs ? real.envs.find(e => e.environment === 'Production') : null;
    if (!prod || !d) return;
    d.address = prod.address || '';
    if (d.address.includes('.kinsta.cloud')) d.address = 'staging-' + prod.address;
    const isKinsta = !!(real.site && real.site.provider === 'kinsta');
    d.username = isKinsta ? (prod.username || '') : (prod.username || '') + '-staging';
    if (isKinsta) d.password = prod.password || '';
    d.port = prod.port || '';
    d.protocol = prod.protocol || 'sftp';
    d.home_directory = prod.home_directory || '';
    this.setState({ eeOpen: false });
    setTimeout(() => this.setState({ eeOpen: true }), 0);
  },

  saveEnvEdit() {
    const real = this._detail, d = this._ee || {}, s = this.state;
    if (!real || s.eeSaving) return;
    this.setState({ eeSaving: true });
    const body = { address: d.address, home_directory: d.home_directory, username: d.username,
      password: d.password, protocol: d.protocol, port: d.port };
    if (!d.environment_id) { this.createEnvRecord(body); return; }
    this.api('/sites/' + real.siteId + '/environments/' + d.environment_id, { method: 'PUT', body }).then(res => {
      if (!res || res.code) {
        this.setState({ eeSaving: false });
        this.toast((res && res.message) || 'Could not save environment', { kind: 'error' });
        return;
      }
      // Reflect immediately, then re-sync so the CLI validates the new
      // connection (mirrors v1 kicking `update` after an edit).
      const env = real.envs && real.envs.find(e => String(e.environment_id) === String(d.environment_id));
      if (env) Object.assign(env, body);
      this.setState({ eeOpen: false, eeSaving: false });
      this.toast(d.envName + ' connection settings saved', { kind: 'success' });
      this.realSync(real, s);
    }).catch(() => { this.setState({ eeSaving: false }); this.toast('Could not save environment', { kind: 'error' }); });
  },

  createEnvRecord(body) {
    const real = this._detail, d = this._ee || {};
    this.api('/sites/' + real.siteId + '/environments', { method: 'POST',
      body: Object.assign({ environment: d.envName }, body) }).then(res => {
      if (!res || res.code || !res.environment_id) {
        this.setState({ eeSaving: false });
        this.toast((res && res.message) || 'Could not add environment', { kind: 'error' });
        return;
      }
      this.setState({ eeOpen: false, eeSaving: false });
      this.toast(d.envName + ' environment added', { kind: 'success' });
      this.reloadSiteDetail();
    }).catch(() => { this.setState({ eeSaving: false }); this.toast('Could not add environment', { kind: 'error' }); });
  },

  deleteEnvRecord() {
    const real = this._detail, d = this._ee || {};
    if (!real || !d.environment_id) return;
    const name = (real.site && real.site.name) || '';
    if (!confirm('Delete the ' + d.envName + ' environment record for ' + name + '? This removes it from CaptainCore only — the hosting environment itself is not touched.')) return;
    this.api('/sites/' + real.siteId + '/environments/' + d.environment_id, { method: 'DELETE' }).then(res => {
      if (!res || res.code) { this.toast((res && res.message) || 'Could not delete environment', { kind: 'error' }); return; }
      this.setState({ eeOpen: false, env: 'Production' });
      this.toast(d.envName + ' environment removed', { kind: 'success' });
      this._detail = null;
      this.loadSiteDetail(real.siteId);
    }).catch(() => this.toast('Could not delete environment', { kind: 'error' }));
  },

  computeEnvEdit(real, s) {
    const d = this._ee || {};
    const isStaging = !!d.envName && d.envName !== 'Production';
    const isNew = !d.environment_id;
    return {
      eeOpen: !!s.eeOpen,
      eeOpenDlg: () => this.openEnvEdit(),
      closeEe: () => this.setState({ eeOpen: false }),
      eeTitle: (isNew ? 'Add ' : 'Edit ') + (d.envName || '') + ' connection',
      eeAddress: d.address || '', onEeAddress: e => { this._ee.address = e.target.value; },
      eeHome: d.home_directory || '', onEeHome: e => { this._ee.home_directory = e.target.value; },
      eeUser: d.username || '', onEeUser: e => { this._ee.username = e.target.value; },
      eePass: d.password || '', onEePass: e => { this._ee.password = e.target.value; },
      eePort: d.port || '', onEePort: e => { this._ee.port = e.target.value; },
      eeProtocols: ['sftp', 'ssh', 'ftp'].map(p => ({ label: p,
        bg: (d.protocol || 'sftp') === p ? 'var(--panel-2)' : 'transparent',
        fg: (d.protocol || 'sftp') === p ? 'var(--ink)' : 'var(--ink-dim)',
        go: () => { this._ee.protocol = p; this.setState({ tick: this.state.tick }); } })),
      eePreloadShow: isStaging && !!real,
      eePreload: () => this.eePreloadApply(),
      eeDeleteShow: isStaging && !isNew,
      eeDelete: () => this.deleteEnvRecord(),
      eeSaveLabel: s.eeSaving ? 'Saving…' : isNew ? 'Add environment' : 'Save changes',
      eeSave: () => this.saveEnvEdit()
    };
  },

  // ── Addons ────────────────────────────────────────────────────
  // Fleet update-queue cache (operator only): slug+type → resolved update
  // target, used to light per-addon "Update to X" and the Update all count.
  loadUpdateQueue() {
    if (this._uqLoading || this._updateQueue !== undefined) return;
    if ((window.CC_BOOT || {}).dcRole !== 'operator') { this._updateQueue = null; return; }
    this._uqLoading = true;
    this.api('/update-queue').then(res => {
      this._uqLoading = false;
      const map = {};
      ((res && res.items) || []).forEach(it => { map[(it.type || 'plugin') + ':' + it.slug] = it; });
      this._updateQueue = map; this.setState({});
    }).catch(() => { this._uqLoading = false; this._updateQueue = null; });
  },

  uqLatest(kind, slug) {
    const map = this._updateQueue;
    if (!map) return '';
    const it = map[(kind === 'themes' ? 'theme' : 'plugin') + ':' + slug];
    return it ? (it.update_to || it.steer_to || '') : '';
  },

  _verCmp(a, b) {
    const pa = String(a).split(/[.\-]/).map(x => parseInt(x, 10) || 0);
    const pb = String(b).split(/[.\-]/).map(x => parseInt(x, 10) || 0);
    for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
      if ((pa[i] || 0) !== (pb[i] || 0)) return (pa[i] || 0) > (pb[i] || 0) ? 1 : -1;
    }
    return 0;
  },

  // Update target for an installed addon: the queue's resolved target, but
  // only when it is actually NEWER than what the site runs (the fleet
  // baseline can lag a site that is ahead — never offer a downgrade).
  uqUpdateTarget(kind, slug, installed) {
    const t = this.uqLatest(kind, slug);
    return (t && installed && this._verCmp(t, installed) > 0) ? t : '';
  },

  realAddonSrc(real, s) {
    const e = this.currentEnv(real, s);
    if (!e) return [];
    if (this.loadUpdateQueue) this.loadUpdateQueue();
    const list = s.addonKind === 'plugins' ? e.plugins : e.themes;
    if (!Array.isArray(list)) return [];
    return list.map(p => ({
      name: p.title || p.plugin || p.name || '',
      slug: p.name || p.slug || '',
      v: p.version || '',
      latest: this.uqUpdateTarget(s.addonKind, p.name || p.slug || '', p.version || '') || p.version || '',
      active: p.status === 'active' || p.status === 'active-network',
      // must-use plugins and drop-ins can't be toggled, updated, or deleted
      // by wp plugin commands — the UI renders them as a static chip.
      mu: p.status === 'must-use' || p.status === 'dropin',
      muLabel: p.status === 'dropin' ? 'Drop-in' : 'Must-use',
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

  realDeleteAddon(a, real, s) {
    const e = this.currentEnv(real, s);
    if (!e) return;
    const kind = s.addonKind === 'plugins' ? 'plugin' : 'theme';
    const name = (real.site && real.site.name) || '';
    if (kind === 'theme' && a.active) { this.toast("The active theme can't be deleted — activate another theme first.", { kind: 'error' }); return; }
    if (!confirm('Delete ' + a.slug + ' from ' + name + ' (' + s.env + ')? Its files are removed from the site.')) return;
    // Deactivate an active plugin first so delete never races a request
    // still running its code (v1 parity: wp plugin delete, no uninstall hooks).
    const code = (kind === 'plugin' && a.active ? 'wp plugin deactivate ' + a.slug + ' --skip-themes --skip-plugins && ' : '')
      + 'wp ' + kind + ' delete ' + a.slug + ' --skip-themes --skip-plugins';
    // Optimistic removal; the chained sync restores truth if the delete failed.
    const list = kind === 'plugin' ? e.plugins : e.themes;
    if (Array.isArray(list)) {
      const i = list.findIndex(p => (p.name || p.slug) === a.slug);
      if (i >= 0) list.splice(i, 1);
    }
    this.startJob({
      label: kind + ' delete', target: a.slug + ' on ' + name, command: 'manage',
      siteId: real.siteId, environment: s.env,
      dispatch: () => this.api('/run/code', { method: 'POST', body: { environments: [e.environment_id], code } }),
      onFinish: () => this.realSync(real, { env: s.env })
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
        ID: u.ID,
        magic: () => this.realMagicLogin(real, s, u)
      };
    });
  },

  reloadSiteUsers() {
    const detail = this._detail;
    if (!detail) return;
    this.api('/sites/' + detail.siteId + '/users').then(u => {
      if (this._detail === detail) { detail.users = u || {}; this.setState({ tick: this.state.tick }); }
    }).catch(() => {});
  },

  // Shell-safe single-quoted CLI argument (v1 wrapped args in single quotes;
  // we additionally strip quote/backslash characters instead of escaping).
  termArg(v) { return String(v || '').replace(/['"\\]/g, ''); },

  // v1 parity (core.php createSiteUser): wp user create over /run/code.
  createSiteUser() {
    const real = this._detail, s = this.state, d = s.nsu || {};
    const env = this.currentEnv(real, s);
    if (!real || !env) { this.setState({ nsuOpen: false }); return; }
    if (!(d.username || '').trim() || !(d.email || '').trim()) { this.setState({ nsuMsg: 'Username and email are required.' }); return; }
    let cli = "wp user create '" + this.termArg(d.username) + "' '" + this.termArg(d.email) + "' --role=" + (d.role || 'subscriber') + ' --skip-themes --skip-plugins';
    if (d.password) cli += " --user_pass='" + this.termArg(d.password) + "'";
    if (d.first) cli += " --first_name='" + this.termArg(d.first) + "'";
    if (d.last) cli += " --last_name='" + this.termArg(d.last) + "'";
    if (d.notify) cli += ' --send-email';
    this.setState({ nsuOpen: false });
    this.startJob({ label: 'create-user',
      target: this.termArg(d.username) + ' on ' + ((real.site && real.site.name) || '') + ' · ' + env.environment,
      dispatch: () => this.api('/run/code', { method: 'POST', body: { environments: [Number(env.environment_id) || env.environment_id], code: cli } }),
      onFinish: () => this.reloadSiteUsers() });
  },

  // v1 parity (core.php deleteUser): reassign is REQUIRED — wp user delete
  // --reassign=<ID> over /run/code (--yes: the daemon shell is non-interactive).
  deleteSiteUser() {
    const real = this._detail, s = this.state, d = s.dsu || {};
    const env = this.currentEnv(real, s);
    if (!real || !env || !d.reassign) return;
    if (!confirm('Delete user ' + d.username + ' from ' + env.environment + '? Content will be reassigned to ' + (d.reassignLogin || 'the selected user') + '.')) return;
    const cli = "wp user delete '" + this.termArg(d.username) + "' --reassign=" + (Number(d.reassign) || 0) + ' --yes --skip-themes --skip-plugins';
    this.setState({ dsuOpen: false });
    this.startJob({ label: 'delete-user',
      target: this.termArg(d.username) + ' on ' + ((real.site && real.site.name) || '') + ' · ' + env.environment,
      dispatch: () => this.api('/run/code', { method: 'POST', body: { environments: [Number(env.environment_id) || env.environment_id], code: cli } }),
      onFinish: () => this.reloadSiteUsers() });
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

  // ── Archived logs (B2 long-term retention) ────────────────────
  // Phase 1: browse + signed-link download over the EXISTING site-scoped
  // routes — GET /site/{id}/{env}/logs-archive (list: {name, type, date,
  // epoch, size}) and .../logs-archive/download?file=… ({link, expires_at}).
  // Files are Kinsta-rotated {access|error}.log-YYYY-MM-DD-EPOCH[.gz]; the
  // daily archive cron runs on the CLI server, so a fresh environment (or
  // staging before 2026-08) legitimately lists zero files.
  loadLogsArchive(envName) {
    const real = this._detail;
    if (!real) return;
    const env = (envName || this.state.env).toLowerCase();
    real.la = real.la || {};
    if (real.la[env]) return;
    const bucket = real.la[env] = { loading: true, files: [], error: '' };
    this.setState({ tick: this.state.tick });
    this.api('/site/' + real.siteId + '/' + env + '/logs-archive').then(res => {
      if (Array.isArray(res)) bucket.files = res;
      else bucket.error = (res && res.error) || 'Could not load the archive list.';
      bucket.loading = false;
      this.setState({ tick: this.state.tick });
    }).catch(() => { bucket.loading = false; bucket.error = 'Could not load the archive list.'; this.setState({ tick: this.state.tick }); });
  },

  downloadArchivedLog(name) {
    const real = this._detail;
    if (!real) return;
    const env = this.state.env.toLowerCase();
    const tid = this.toast('Preparing download…', { kind: 'loading' });
    this.api('/site/' + real.siteId + '/' + env + '/logs-archive/download?file=' + encodeURIComponent(name)).then(res => {
      if (res && res.link) {
        this.safeOpen(String(res.link).trim());
        this.updateToast(tid, 'Download link opened' + (res.expires_in ? ' — valid for ' + res.expires_in : ''), { kind: 'success' });
      } else {
        this.updateToast(tid, (res && res.error) || 'Could not create a download link', { kind: 'error' });
      }
    }).catch(() => this.updateToast(tid, 'Could not create a download link', { kind: 'error' }));
  },

  fmtBytes(n) {
    n = Number(n) || 0;
    if (n >= 1073741824) return (n / 1073741824).toFixed(1) + ' GB';
    if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB';
    if (n >= 1024) return Math.round(n / 1024) + ' KB';
    return n + ' B';
  },

  // Phase 2: in-browser viewing. The B2 bucket has no CORS rules (verified:
  // signed-link GET carries no Access-Control-Allow-Origin), so the browser
  // can't fetch the signed URL itself — the Manager proxies instead:
  // GET /site/{id}/{env}/logs-archive/view?file=… resolves the link via the
  // CLI, downloads + gunzips server-side, and returns the last 1000 lines
  // as {file, total, truncated, content}.
  viewArchivedLog(name) {
    const real = this._detail;
    if (!real) return;
    const env = this.state.env.toLowerCase();
    const bucket = (real.la || {})[env];
    if (!bucket) return;
    bucket.content = bucket.content || {};
    this.setState({ laView: name });
    if (bucket.content[name] !== undefined) return;
    bucket.content[name] = null; // loading
    this.api('/site/' + real.siteId + '/' + env + '/logs-archive/view?file=' + encodeURIComponent(name)).then(res => {
      bucket.content[name] = (res && typeof res.content === 'string')
        ? { text: res.content, total: res.total || 0, truncated: !!res.truncated }
        : { text: '', error: (res && res.message) || 'Could not load this file.' };
      this.setState({ tick: this.state.tick });
    }).catch(() => { bucket.content[name] = { text: '', error: 'Could not load this file.' }; this.setState({ tick: this.state.tick }); });
  },

  // Design-preview sample rows, dated relative to today so the default
  // 30-day range never renders an empty preview.
  laSample() {
    const out = [];
    for (let i = 1; i <= 6; i++) {
      const d = new Date(Date.now() - i * 86400000);
      const date = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
      out.push({ name: 'access.log-' + date + '-175400000' + i + '.gz', type: 'access', date, epoch: 1754000000 + i, size: 45056 + i * 3210 });
      out.push({ name: 'error.log-' + date + '-175400000' + i + '.gz', type: 'error', date, epoch: 1754000000 + i, size: 2048 + i * 512 });
    }
    return out;
  },

  // Bindings for the Logs tab's Archive view (spread into computeDetail's
  // return AFTER the base logMeta so the archive count line overrides it).
  computeLogsArchive(real, s) {
    if (s.logMode !== 'archive') return {};
    const env = (s.env || '').toLowerCase();
    const bucket = real
      ? ((real.la || {})[env] || { loading: true, files: [], error: '' })
      : { loading: false, files: window.CC_BOOT ? [] : this.laSample(), error: '' };
    const range = s.laRange === undefined ? 30 : s.laRange;
    const type = s.laType || '';
    let files = bucket.files;
    if (range) {
      const cut = new Date(Date.now() - range * 86400000);
      const cutStr = cut.getFullYear() + '-' + String(cut.getMonth() + 1).padStart(2, '0') + '-' + String(cut.getDate()).padStart(2, '0');
      files = files.filter(f => f.date >= cutStr);
    }
    if (type) files = files.filter(f => f.type === type);
    files = [...files].sort((a, b) => b.date.localeCompare(a.date) || b.epoch - a.epoch);
    const groups = [];
    let cur = null;
    files.forEach(f => {
      const d = new Date(f.date + 'T12:00:00');
      const label = d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
      if (!cur || cur.label !== label) { cur = { label, rows: [] }; groups.push(cur); }
      cur.rows.push({
        day: d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }),
        typeLabel: f.type,
        typeBg: f.type === 'error' ? 'var(--bad-soft)' : 'var(--brand-soft)',
        typeFg: f.type === 'error' ? 'var(--bad)' : 'var(--brand-ink)',
        size: this.fmtBytes(f.size),
        name: f.name,
        view: (e) => { if (e && e.stopPropagation) e.stopPropagation();
          if (real) this.viewArchivedLog(f.name); else this.setState({ laView: f.name }); },
        // The whole row opens the viewer, so Download must not bubble into it.
        dl: (e) => { if (e && e.stopPropagation) e.stopPropagation();
          if (real) this.downloadArchivedLog(f.name); else this.toast('Sample data — downloads work on a real site', { kind: 'info' }); }
      });
    });
    const totalSize = files.reduce((t, f) => t + (Number(f.size) || 0), 0);
    // Inline viewer (replaces the list while a file is open).
    const viewName = s.laView || '';
    let vc = null; // {text, total, truncated, error} | {loading:true}
    if (viewName) {
      if (real) {
        const c = ((bucket.content || {})[viewName]);
        vc = c === undefined || c === null ? { loading: true } : c;
      } else {
        vc = { text: Array.from({ length: 8 }, (_, i) =>
          '2026-08-0' + (i % 6 + 1) + ' 12:0' + i + ':11 [error] PHP Warning: sample archived log line ' + (i + 1)).join('\n'), total: 8, truncated: false };
      }
    }
    const viewLines = vc && !vc.loading && !vc.error
      ? (vc.text ? vc.text.split('\n') : ['(empty file)']).map((text, i) => ({ n: String(i + 1), segs: this.logSegments(text) }))
      : [];
    return {
      laListShow: !viewName,
      laViewOpen: !!viewName,
      laViewName: viewName,
      laViewMeta: !vc ? '' : vc.loading ? 'Loading…' : vc.error ? '' :
        (vc.truncated ? 'last ' + viewLines.length.toLocaleString() + ' of ' + Number(vc.total).toLocaleString() + ' lines' : viewLines.length.toLocaleString() + ' lines'),
      laViewLoading: !!(vc && vc.loading),
      laViewErr: (vc && vc.error) || '', laViewHasErr: !!(vc && vc.error),
      laViewLines: viewLines,
      laBack: () => this.setState({ laView: '' }),
      laViewDl: () => { if (real) this.downloadArchivedLog(viewName); },
      laRanges: [[7, '7 days'], [30, '30 days'], [90, '90 days'], [0, 'All']].map(([d, label]) => ({ label,
        bg: range === d ? 'var(--panel-2)' : 'transparent', fg: range === d ? 'var(--ink)' : 'var(--ink-dim)',
        go: () => this.setState({ laRange: d }) })),
      laTypes: [['', 'All'], ['access', 'Access'], ['error', 'Error']].map(([t, label]) => ({ label,
        bg: type === t ? 'var(--panel-2)' : 'transparent', fg: type === t ? 'var(--ink)' : 'var(--ink-dim)',
        go: () => this.setState({ laType: t }) })),
      laGroups: groups,
      laLoading: !!bucket.loading,
      laError: bucket.error, laHasError: !!bucket.error,
      laEmpty: !bucket.loading && !bucket.error && groups.length === 0,
      laEmptyText: bucket.files.length
        ? 'No archived logs match the current filters.'
        : 'No archived logs for this environment yet — rotated server logs are archived to long-term storage daily.',
      logMeta: bucket.loading ? 'Loading…' : files.length + ' file' + (files.length === 1 ? '' : 's') + ' · ' + this.fmtBytes(totalSize)
    };
  },

  // Lightweight log-line highlighter — nginx/PHP-FPM/access-log flavored.
  // Emits [{t, fg, w}] segments per line; no external libraries.
  LOG_TOKEN_SRC: [
    '(\\d{4}[\\/-]\\d{2}[\\/-]\\d{2}[ T]\\d{2}:\\d{2}:\\d{2}(?:[.,]\\d+)?)',              // 1 timestamp
    '(\\[\\d{2}\\/[A-Za-z]{3}\\/\\d{4}:\\d{2}:\\d{2}:\\d{2}[^\\]]*\\])',                  // 2 access-log timestamp
    '(\\[(?:error|crit|alert|emerg)\\]|PHP (?:Fatal error|Parse error)|Uncaught (?:Error|Exception|TypeError)|Fatal error|Stack trace:)', // 3 error
    '(\\[(?:warn|warning)\\]|PHP Warning|PHP Deprecated|Deprecated:)',                     // 4 warning
    '(\\[(?:notice|info|debug)\\]|PHP Notice)',                                            // 5 notice
    '("[^"]*")',                                                                           // 6 quoted string
    '(\\b\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}\\b|\\B::1\\b)',                         // 7 IP
    '((?:\\/[\\w.@~-]+){2,})'                                                              // 8 path
  ].join('|'),
  LOG_TOKEN_STYLE: [null,
    ['var(--brand-ink)', 400], ['var(--brand-ink)', 400],
    ['var(--bad)', 600], ['var(--warn)', 600], ['var(--brand-ink)', 600],
    ['var(--ok)', 400], ['var(--warn)', 400], ['var(--ink)', 400]],
  logSegments(text) {
    const re = this._logTokenRe = this._logTokenRe || new RegExp(this.LOG_TOKEN_SRC, 'g');
    re.lastIndex = 0;
    const segs = [];
    let last = 0, m;
    while ((m = re.exec(text)) !== null) {
      if (m.index > last) segs.push({ t: text.slice(last, m.index), fg: 'inherit', w: 400 });
      let g = 1;
      while (m[g] === undefined) g++;
      const st = this.LOG_TOKEN_STYLE[g];
      segs.push({ t: m[0], fg: st[0], w: st[1] });
      last = m.index + m[0].length;
      if (m[0].length === 0) re.lastIndex++; // safety
    }
    if (last < text.length) segs.push({ t: text.slice(last), fg: 'inherit', w: 400 });
    return segs.length ? segs : [{ t: text, fg: 'inherit', w: 400 }];
  },

  realLogLines(real, s) {
    const bucket = real.logs[s.env.toLowerCase()];
    if (!bucket) return [{ text: real.logsLoading ? 'Loading log list…' : 'Open this tab to load logs.', ph: true }];
    const content = bucket.content[s.logFile];
    if (content === null) return [{ text: 'Loading ' + s.logFile + '…', ph: true }];
    if (content === undefined) return [{ text: bucket.files.length ? 'Select a log file.' : 'No log files found.', ph: true }];
    return content.split('\n').slice(-1000).map(text => ({ text }));
  },

  // ── Site removal (v1 parity) ────────────────────────────────────────────
  // TWO different operations, deliberately not the same button:
  //   REQUEST removal — anyone with access. POST /sites/{id}
  //     { details: { removed: true|false } }. The server merges the flag,
  //     emails the operators (Mailer::send_site_removal_request) and writes a
  //     requested_removal / cancelled_removal ActivityLog row. Nothing is
  //     destroyed; an operator takes the final backup, then deletes.
  //   DELETE the site — ADMIN ONLY, and enforced server-side as of this round
  //     (the route previously accepted any owner). Dispatches the CLI
  //     `site delete` and marks the record inactive. Irreversible.

  setSiteRemoved(next) {
    const s = this.state;
    const site = this.FLEET.find(x => x.id === s.siteId);
    if (!site) return;
    const tid = this.toast(next ? 'Requesting removal of ' + site.name + '…' : 'Cancelling removal request…', { kind: 'loading' });
    this.api('/sites/' + site.id, { method: 'POST', body: { details: { removed: next } } })
      .then(() => {
        site.removed = next; // patch FLEET in place; no refetch of 2.9k sites
        this.updateToast(tid, next
          ? site.name + ' is marked for removal — an operator will follow up'
          : 'Removal request cancelled for ' + site.name, { kind: 'success' });
        this.setState({});
      })
      .catch(() => this.updateToast(tid, 'Could not update the removal request', { kind: 'error' }));
  },

  requestSiteRemoval() {
    const site = this.FLEET.find(x => x.id === this.state.siteId);
    if (!site) return;
    if (!confirm('Mark ' + site.name + ' for removal?\n\nEvery environment will be removed once an operator processes the request. Nothing is deleted right now, and you can cancel until then.')) return;
    this.setSiteRemoved(true);
  },

  cancelSiteRemoval() { this.setSiteRemoved(false); },

  // Admin-only hard delete. Typed confirmation, not a plain OK — v1 used a
  // bare confirm() for an irreversible fleet-wide destructive action.
  deleteSiteHard() {
    const s = this.state;
    const site = this.FLEET.find(x => x.id === s.siteId);
    if (!site) return;
    const typed = prompt('DELETE ' + site.name + ' permanently?\n\nThis removes every environment at the host and cannot be undone.\nType the site name to confirm:');
    if (typed == null) return;
    if (typed.trim() !== site.name) { this.toast('Name did not match — nothing was deleted', { kind: 'error' }); return; }
    const tid = this.toast('Deleting ' + site.name + '…', { kind: 'loading' });
    this.api('/sites/' + site.id, { method: 'DELETE' })
      .then(res => {
        if (res && res.code) { this.updateToast(tid, res.message || 'Delete refused', { kind: 'error' }); return; }
        this.FLEET = this.FLEET.filter(x => x.id !== site.id);
        this.updateToast(tid, (res && res.message) || site.name + ' deleted', { kind: 'success' });
        this._detail = null;
        this.setState({ route: 'sites', siteId: null });
      })
      .catch(() => this.updateToast(tid, 'Could not delete ' + site.name, { kind: 'error' }));
  },

  computeRemoval(s, site, isOp) {
    const marked = !!(site && site.removed);
    return {
      rmMarked: marked,
      rmBannerText: site ? site.name + ' is marked for removal. Every environment will be removed once an operator processes the request.' : '',
      rmCancel: () => this.cancelSiteRemoval(),
      rmRequest: () => this.requestSiteRemoval(),
      rmRequestShow: !marked,
      // "Request deletion" for customers vs "Mark for removal" for operators —
      // same call, but the operator is queueing their own work, not asking.
      rmRequestLabel: isOp ? 'Mark for removal…' : 'Request site deletion…',
      rmCanDelete: isOp,
      rmDelete: () => this.deleteSiteHard()
    };
  }

});
