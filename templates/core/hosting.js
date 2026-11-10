// CaptainCore v3 — Hosting tab (mixin). Operators, sites on our own servers
// only (environment server_id > 0). Edits a draft of the environment's
// hosting configuration; Save & apply stores a new revision and the Manager
// starts `captaincore host apply` on the CLI server.
//   GET  /sites/{id}/hosting?environment=   → { config, revision, status, result, history, server, php_versions, limits, dns }
//   PUT  /sites/{id}/hosting                → { environment, config, note }  (+ token)
//   POST /sites/{id}/hosting/apply          → re-apply the latest revision
//   POST /sites/{id}/hosting/restore        → { environment, revision }
//   POST /sites/{id}/hosting/temporary-domain → { environment, primary }

Object.assign(Component.prototype, {

  hostingKey() { return (this._detail ? this._detail.siteId : '') + '|' + (this.state.env || 'Production'); },

  loadHosting(force) {
    const key = this.hostingKey();
    if (!this._detail || (!force && this._host && this._host.key === key) || this._hostLoading === key) return;
    this._hostLoading = key;
    const [siteId, env] = key.split('|');
    this.api('/sites/' + siteId + '/hosting?environment=' + encodeURIComponent(env)).then(res => {
      this._hostLoading = null;
      if (!res || res.code) { this._host = { key, error: (res && res.message) || 'Could not load hosting' }; this.setState({}); return; }
      this._host = { key, data: res, draft: JSON.parse(JSON.stringify(res.config)) };
      this.setState({ hostNote: '', hostAddName: '', hostAddWww: true });
      // While an apply is running, poll until it settles.
      clearTimeout(this._hostPoll);
      if (res.status === 'pending') this._hostPoll = setTimeout(() => this.loadHosting(true), 4000);
    }).catch(() => { this._hostLoading = null; this._host = { key, error: 'Could not load hosting' }; this.setState({}); });
  },

  // Local backups (ZFS snapshots on the node), listed live through the CLI.
  loadHostBackups(force) {
    const key = this.hostingKey();
    if (!force && this._hostBk && this._hostBk.key === key) return;
    const [siteId, env] = key.split('|');
    this._hostBk = { key, loading: true, list: (this._hostBk && this._hostBk.key === key) ? this._hostBk.list : [] };
    this.setState({});
    this.api('/sites/' + siteId + '/hosting/backups?environment=' + encodeURIComponent(env)).then(res => {
      if (this._hostBk.key !== key) return;
      this._hostBk = (!res || res.code) ? { key, error: (res && res.message) || 'Could not list local backups', list: [] } : { key, list: res.backups || [] };
      this.setState({});
    }).catch(() => { this._hostBk = { key, error: 'Could not list local backups', list: [] }; this.setState({}); });
  },

  hostBackupJob(action, backup, what, label) {
    const [siteId, env] = this.hostingKey().split('|');
    const site = (this._detail && this._detail.site) || {};
    this.startJob({ label: action === 'create' ? 'backup' : action, target: label + ' · ' + (site.name || ''), command: 'host backup ' + action,
      siteId, environment: env,
      dispatch: () => this.api('/sites/' + siteId + '/hosting/backups', { method: 'POST', body: { environment: env, action, backup, what } }),
      onFinish: () => this.loadHostBackups(true) });
  },

  hostingSend(path, body, label) {
    const [siteId, env] = this.hostingKey().split('|');
    const tid = this.toast(label + '…', { kind: 'loading' });
    const method = path === '' ? 'PUT' : 'POST';
    return this.api('/sites/' + siteId + '/hosting' + path, { method, body: { environment: env, ...body } }).then(res => {
      if (!res || res.code) { this.updateToast(tid, (res && res.message) || 'Something went wrong', { kind: 'error', timeout: 7000 }); return; }
      this.updateToast(tid, res.token ? label.replace(/…$/, '') + ': applying to ' + ((res.server && res.server.name) || 'the server') : 'Saved, but the CLI server did not answer. Use Apply again.', { kind: res.token ? 'success' : 'info' });
      this._host = null; this.loadHosting(true);
    }).catch(e => this.updateToast(tid, (e && e.message) || 'Something went wrong', { kind: 'error' }));
  },

  // Outgoing mail: the relay's domain and this site's recent messages (Mailgun events by tag).
  loadHostMail(force) {
    const key = this.hostingKey();
    if (!force && this._hostMail && this._hostMail.key === key) return;
    const [siteId, env] = key.split('|');
    this._hostMail = { key, loading: true, data: (this._hostMail && this._hostMail.key === key) ? this._hostMail.data : null };
    this.setState({});
    this.api('/sites/' + siteId + '/hosting/mail?environment=' + encodeURIComponent(env)).then(res => {
      if (this._hostMail.key !== key) return;
      this._hostMail = (!res || res.code) ? { key, error: (res && res.message) || 'Could not read mail' } : { key, data: res };
      this.setState({});
    }).catch(() => { this._hostMail = { key, error: 'Could not read mail' }; this.setState({}); });
  },

  // Page cache: the setting lives in the hosting config (saved and applied
  // with everything else); the saved-page count and Clear cache talk to the
  // node right away.
  loadHostCache(force) {
    const key = this.hostingKey();
    if (!force && this._hostCache && this._hostCache.key === key) return;
    const [siteId, env] = key.split('|');
    this._hostCache = { key, loading: true, data: (this._hostCache && this._hostCache.key === key) ? this._hostCache.data : null };
    this.setState({});
    this.api('/sites/' + siteId + '/hosting/cache?environment=' + encodeURIComponent(env)).then(res => {
      if (!this._hostCache || this._hostCache.key !== key) return;
      this._hostCache = (!res || res.code) ? { key, error: (res && res.message) || 'Could not read the page cache' } : { key, data: res };
      this.setState({});
    }).catch(() => { this._hostCache = { key, error: 'Could not read the page cache' }; this.setState({}); });
  },

  clearHostCache() {
    const key = this.hostingKey(), [siteId, env] = key.split('|');
    const tid = this.toast('Clearing the page cache…', { kind: 'loading' });
    this.api('/sites/' + siteId + '/hosting/cache?environment=' + encodeURIComponent(env), { method: 'POST', body: { environment: env } }).then(res => {
      if (!res || res.code) { this.updateToast(tid, (res && res.message) || 'Could not clear the page cache', { kind: 'error', timeout: 8000 }); return; }
      this.updateToast(tid, 'Cleared ' + (res.cleared || 0) + (res.cleared === 1 ? ' saved page' : ' saved pages') + '. Each page is saved again on its next visit.', { kind: 'success', timeout: 6000 });
      if (this._hostCache && this._hostCache.key === key) { this._hostCache = { key, data: res }; this.setState({}); }
    }).catch(() => this.updateToast(tid, 'Could not clear the page cache', { kind: 'error' }));
  },

  hostCacheVals(c, set, saved) {
    if (!this._hostCache || this._hostCache.key !== this.hostingKey()) setTimeout(() => this.loadHostCache(), 0);
    const pc = c.page_cache = c.page_cache || { enabled: false, ttl: 60, exclude: [] };
    pc.exclude = pc.exclude || [];
    const m = this._hostCache || {}, d = m.data || {};
    const ago = t => { const s = Math.max(0, (d.now || 0) - t); return s < 90 ? 'a minute' : s < 5400 ? Math.round(s / 60) + ' minutes' : s < 172800 ? Math.round(s / 3600) + ' hours' : Math.round(s / 86400) + ' days'; };
    const size = d.bytes >= 1048576 ? (d.bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round((d.bytes || 0) / 1024)) + ' KB';
    // The node knows whether the cache runs now; the form may hold an unsaved change.
    const live = !!(saved && saved.page_cache && saved.page_cache.enabled);
    let status = '';
    if (m.error) status = '';
    else if (m.loading && !m.data) status = 'Counting saved pages…';
    else if (!live) status = 'Off. Every page view runs PHP.';
    else if (!d.pages) status = 'No pages saved yet. A page is saved the first time a visitor asks for it.';
    else status = d.pages.toLocaleString() + (d.pages === 1 ? ' page' : ' pages') + ' saved · ' + size + ' · oldest ' + ago(d.oldest) + ' old';
    const addEx = () => {
      const raw = (this.state.hostCacheEx || '').trim().toLowerCase();
      const path = '/' + raw.replace(/^https?:\/\/[^/]+/, '').replace(/^\/+|\/+$/g, '');
      if (!/^(\/[a-z0-9._~-]+)+$/.test(path)) { this.toast('Enter a path like /members or /shop/sale', { kind: 'error' }); return; }
      set(cc => { if (!cc.page_cache.exclude.includes(path)) cc.page_cache.exclude.push(path); });
      this.setState({ hostCacheEx: '' });
    };
    return {
      hostCacheIco: this.icon('bolt'),
      hostCacheOn: !!pc.enabled, hostCacheMark: pc.enabled ? '✓' : '',
      toggleHostCache: () => set(cc => { cc.page_cache.enabled = !cc.page_cache.enabled; }),
      hostCacheTtls: [[15, '15 minutes'], [60, '1 hour'], [360, '6 hours'], [1440, '1 day'], [10080, '1 week']].map(([v, l]) => ({ label: l,
        fg: pc.ttl === v ? 'var(--brand-ink)' : 'var(--ink-dim)', bg: pc.ttl === v ? 'var(--brand-soft)' : 'transparent', bd: pc.ttl === v ? 'var(--brand)' : 'var(--rule)',
        pick: () => set(cc => { cc.page_cache.ttl = v; }) })),
      hostCacheExcl: pc.exclude.map(p => ({ path: p, remove: () => set(cc => { cc.page_cache.exclude = cc.page_cache.exclude.filter(x => x !== p); }) })),
      hostCacheEx: this.state.hostCacheEx || '', onHostCacheEx: e => this.setState({ hostCacheEx: e.target.value }),
      onHostCacheExKey: e => { if (e.key === 'Enter') { e.preventDefault(); addEx(); } }, addHostCacheEx: addEx,
      hostCacheStatus: status, hostCacheHasStatus: !!status,
      hostCacheErr: m.error || '', hostCacheHasErr: !!m.error,
      hostCacheCanClear: live && !m.error,
      hostCacheRefresh: () => this.loadHostCache(true), hostCacheClear: () => this.clearHostCache()
    };
  },

  hostMailVals(c, set) {
    if (!this._hostMail || this._hostMail.key !== this.hostingKey()) setTimeout(() => this.loadHostMail(), 0);
    const m = this._hostMail || {}, d = m.data || {};
    const on = c.mail !== 'off';
    const primary = ((c.domains || []).find(x => x.primary) || (c.domains || [])[0] || {});
    const zone = (d.domain || '').replace(/^mg\./, '');
    const aligned = !!zone && (primary.name === zone || String(primary.name || '').endsWith('.' + zone));
    const tone = { delivered: 'var(--ok)', accepted: 'var(--brand)', failed: 'var(--bad)', rejected: 'var(--bad)', complained: 'var(--warn)' };
    const label = { delivered: 'Delivered', accepted: 'Accepted', failed: 'Failed', rejected: 'Rejected', complained: 'Complaint' };
    // One row per message: Mailgun lists newest first, so the latest event wins.
    const seen = {}, rows = [];
    (d.events || []).forEach(e => { const k = e.subject + '|' + e.to + '|' + Math.round(e.time / 300); if (seen[k]) return; seen[k] = 1; rows.push(e); });
    return {
      hostMailIco: this.icon('mail'), hostMailIllo: this.illo('mail'),
      hostMailOn: on, hostMailMark: on ? '✓' : '', toggleHostMail: () => set(cc => { cc.mail = cc.mail === 'off' ? 'relay' : 'off'; }),
      hostMailDomain: d.domain || 'the hosting zone',
      hostMailFrom: 'wordpress@' + (primary.name || 'the primary domain'),
      hostMailAligned: on && aligned, hostMailUnaligned: on && !aligned && !!primary.name,
      hostMailAlignNote: 'Mail from ' + (primary.name || 'this domain') + ' is signed for ' + (d.domain || 'the relay') + ', not for ' + (primary.name || 'it') + '. Receivers accept it unless the domain has a strict DMARC policy; sending records for the site\'s own domain come next.',
      hostMailRows: rows.slice(0, 12).map(e => ({ status: label[e.event] || e.event, fg: tone[e.event] || 'var(--ink-dim)',
        subject: e.subject || '(no subject)', to: e.to, when: e.time ? new Date(e.time * 1000).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '',
        reason: e.reason, hasReason: !!e.reason && e.event !== 'delivered' && e.event !== 'accepted', test: !!e.test })),
      hostMailEmpty: !m.loading && !m.error && !rows.length, hostMailLoading: !!m.loading && !rows.length,
      hostMailErr: m.error || (d.error || ''), hostMailHasErr: !!(m.error || d.error),
      hostMailRefresh: () => this.loadHostMail(true)
    };
  },

  hostBackupVals() {
    if (!this._hostBk || this._hostBk.key !== this.hostingKey()) setTimeout(() => this.loadHostBackups(), 0);
    const bk = this._hostBk || { loading: true, list: [] };
    const typeLabel = { daily: 'Daily', manual: 'Manual', 'pre-restore': 'Before restore', 'pre-deploy': 'Before deploy' };
    const fmt = iso => { const d = new Date(iso); return isNaN(d) ? iso : d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }); };
    return {
      hostBkLoading: !!bk.loading && !bk.list.length, hostBkErr: bk.error || '', hostBkHasErr: !!bk.error,
      hostBkEmpty: !bk.loading && !bk.error && !bk.list.length,
      hostBkRows: bk.list.map(b => ({
        when: fmt(b.created), type: typeLabel[b.type] || b.type, size: (b.used_bytes / 1e6 < 0.1 ? '<0.1' : (b.used_bytes / 1e6).toFixed(1)) + ' MB unique',
        canDelete: b.type === 'manual',
        restore: e => this.openCtxMenu(e, [
          { label: 'Restore files and database', act: () => this.hostRestoreConfirm(b, 'both') },
          { label: 'Restore files only', act: () => this.hostRestoreConfirm(b, 'files') },
          { label: 'Restore database only', act: () => this.hostRestoreConfirm(b, 'database') }]),
        del: async () => { if (await this.uiConfirm('Delete the manual backup from ' + fmt(b.created) + '?', { label: 'Delete backup' })) this.hostBackupJob('delete', b.name, '', 'delete backup'); }
      })),
      hostBkCreate: () => this.hostBackupJob('create', '', '', 'local backup'),
      hostBkRefresh: () => this.loadHostBackups(true)
    };
  },

  async hostRestoreConfirm(b, what) {
    const words = { both: 'files and database', files: 'files', database: 'database' }[what];
    if (!(await this.uiConfirm('Restore the ' + words + ' from ' + new Date(b.created).toLocaleString() + '? A backup of the site as it is now is taken first.', { label: 'Restore' }))) return;
    this.hostBackupJob('restore', b.name, what, 'restore ' + words);
  },

  computeHosting(real, s) {
    const off = { hostShow: false };
    if (!real || !real.site) return off;
    const isOp = ((window.CC_BOOT && window.CC_BOOT.dcRole) || 'operator') === 'operator';
    const envRow = (real.envs || []).find(e => e.environment === (s.env || 'Production')) || {};
    const own = real.site.provider === 'captaincore';
    if (!isOp || !own) return off;
    const vals = { hostShow: true };
    if (this.siteLeaf(s.siteTab) !== 'hosting') return vals;
    if (!this._host || this._host.key !== this.hostingKey()) { setTimeout(() => this.loadHosting(), 0); return { ...vals, hostLoading: true }; }
    if (this._host.error) return { ...vals, hostError: this._host.error, hostHasError: true };

    const h = this._host, d = h.data, c = h.draft;
    const set = fn => { fn(c); this.setState({}); };
    const dirty = JSON.stringify(c) !== JSON.stringify(d.config);
    const dnsBy = {}; (d.dns || []).forEach(x => { dnsBy[x.name] = x; });
    const certs = (d.result && d.result.certs) || {};
    const ip = d.server ? d.server.address : '';
    const ago = t => t ? this.srvAgo(t) : '';

    const domRows = c.domains.map((dm, i) => {
      const names = [dm.name].concat(dm.www ? ['www.' + dm.name] : []);
      const pointed = names.every(n => dnsBy[n] && dnsBy[n].pointed);
      const known = names.every(n => dnsBy[n]);
      const cert = names.every(n => certs[n]);
      return {
        name: dm.name, www: dm.www ? '+ www.' + dm.name : '', hasWww: !!dm.www,
        primary: !!dm.primary, notPrimary: !dm.primary, temporary: !!dm.temporary,
        dnsLabel: !known ? 'Save to check DNS' : pointed ? 'Points here' : 'Not pointed yet',
        dnsFg: !known ? 'var(--ink-dim)' : pointed ? 'var(--ok)' : 'var(--warn)',
        dnsHint: known && !pointed ? 'Add an A record to ' + ip + (dm.www ? ' for both names' : '') + '. Now: ' + (names.map(n => (dnsBy[n] && dnsBy[n].a.length ? dnsBy[n].a.join(', ') : 'nothing')).join(' · ')) : '',
        hasDnsHint: known && !pointed,
        certLabel: cert ? 'SSL issued' : (pointed ? 'SSL pending' : ''), certShow: cert || pointed,
        certFg: cert ? 'var(--ok)' : 'var(--ink-dim)',
        makePrimary: () => set(cc => cc.domains.forEach((x, j) => { x.primary = j === i; })),
        toggleWww: () => set(cc => { cc.domains[i].www = !cc.domains[i].www; }),
        remove: () => set(cc => { cc.domains.splice(i, 1); })
      };
    });

    const addName = (s.hostAddName || '').trim().toLowerCase().replace(/^https?:\/\//, '').replace(/\/.*$/, '');
    const num = (key, path) => ({ val: String(path(c)[key]), on: e => set(cc => { path(cc)[key] = Number(e.target.value.replace(/[^0-9]/g, '')) || 0; }) });
    const php = cc => cc.php, root = cc => cc;
    const fields = {
      workers: num('workers', php), max_workers: num('max_workers', php), memory_limit: num('memory_limit', php),
      upload_max_filesize: num('upload_max_filesize', php), max_execution_time: num('max_execution_time', php), memory_mb: num('memory_mb', root)
    };
    const lim = k => d.limits && d.limits[k] ? d.limits[k][0] + '–' + d.limits[k][1] : '';
    const statusMap = { applied: ['Applied', 'var(--ok)'], pending: ['Applying…', 'var(--brand-ink)'], failed: ['Failed', 'var(--bad)'], superseded: ['Replaced', 'var(--ink-dim)'], default: ['Not saved yet', 'var(--ink-dim)'] };
    const [stLabel, stFg] = statusMap[d.status] || [d.status, 'var(--ink-dim)'];
    const err = d.status === 'failed' && d.result && d.result.error ? d.result.error : '';

    const appNames = (c.apps || []).filter(a => a.enabled !== false).map(a => a.name);
    return { ...vals,
      hostReady: true,
      hostRuns: 'Runs WordPress' + (appNames.length ? ' + ' + appNames.join(', ') : '') + ' in container ' + (d.container || '—'),
      hostCtIllo: this.illo('container'), hostCtName: d.container || '', hostCtServer: d.server ? d.server.name : '',
      hostRunsItems: [{ name: 'WordPress', illo: this.illo('wordpress') }].concat((c.apps || []).filter(a => a.enabled !== false).map(a => ({ name: a.name, illo: this.illo(this.appIllo(a)) }))).concat([{ name: 'MariaDB', illo: this.illo('database') }]),
      hostIcoArrow: this.icon('arrow'), hostBkIllo: this.illo('backups'),
      hostIcoDomains: this.icon('globe'), hostIcoPhp: this.icon('code'), hostIcoRedirects: this.icon('redirect'), hostIcoAccess: this.icon('shield'),
      hostIcoTools: this.icon('wrench'), hostIcoBackups: this.icon('layers'), hostIcoHistory: this.icon('history'),
      hostHasCt: !!(d.container && d.server),
      hostOpenCt: () => { if (d.container && d.server) this.openContainer(d.server.server_id, d.container); },
      hostServer: d.server ? d.server.name + ' · ' + d.server.address : '',
      hostRev: d.revision ? 'Revision ' + d.revision : 'Defaults (no revision yet)',
      hostStatus: stLabel, hostStatusFg: stFg,
      hostWhen: d.applied_at ? 'applied ' + ago(d.applied_at) : '',
      hostErr: err, hostHasErr: !!err,
      hostDirty: dirty, hostClean: !dirty,
      hostNote: s.hostNote || '', onHostNote: e => this.setState({ hostNote: e.target.value }),
      hostSave: () => this.hostingSend('', { config: c, note: this.state.hostNote || '' }, 'Saving'),
      hostDiscard: () => { h.draft = JSON.parse(JSON.stringify(d.config)); this.setState({}); },
      hostApply: () => this.hostingSend('/apply', {}, 'Applying'),
      // Domains
      hostDomRows: domRows,
      hostAddName: s.hostAddName || '', onHostAddName: e => this.setState({ hostAddName: e.target.value }),
      hostAddWww: s.hostAddWww !== false, hostAddWwwMark: s.hostAddWww !== false ? '✓' : '',
      toggleHostAddWww: () => this.setState({ hostAddWww: !(this.state.hostAddWww !== false) }),
      hostAddDomain: () => {
        if (!/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/.test(addName)) { this.toast('Enter a domain like example.com', { kind: 'error' }); return; }
        if (c.domains.some(x => x.name === addName)) { this.toast(addName + ' is already listed', { kind: 'error' }); return; }
        set(cc => cc.domains.push({ name: addName, primary: false, www: this.state.hostAddWww !== false && !addName.startsWith('www.') }));
        this.setState({ hostAddName: '' });
      },
      hostCanTemp: !c.domains.some(x => x.temporary) && !dirty,
      hostAddTemp: () => this.hostingSend('/temporary-domain', { primary: false }, 'Adding a temporary domain'),
      hostIp: ip,
      hostForceHttps: !!c.force_https, hostForceMark: c.force_https ? '✓' : '',
      toggleHostForce: () => set(cc => { cc.force_https = !cc.force_https; }),
      hostRedirect: !!c.redirect_to_primary, hostRedirectMark: c.redirect_to_primary ? '✓' : '',
      toggleHostRedirect: () => set(cc => { cc.redirect_to_primary = !cc.redirect_to_primary; }),
      // PHP
      hostPhpSwitch: !!(d.config && d.config.php && c.php.version !== d.config.php.version),
      hostPhpSwitchNote: 'Moving from PHP ' + ((d.config && d.config.php && d.config.php.version) || '') + ' to ' + c.php.version + ' rebuilds this site\'s container on the PHP ' + c.php.version + ' image. A local backup is taken first; the swap takes about 10 seconds, and requests in that time wait instead of failing. Files, database, address and SSH keys stay as they are. If the site answers with an error on PHP ' + c.php.version + ', the switch is refused and the current container comes straight back.',
      hostPhpChips: (d.php_versions || []).map(v => ({ label: 'PHP ' + v.version + (v.available ? '' : ' (soon)'),
        fg: c.php.version === v.version ? 'var(--brand-ink)' : v.available ? 'var(--ink-dim)' : 'var(--ink-faint, var(--ink-dim))',
        bg: c.php.version === v.version ? 'var(--brand-soft)' : 'transparent',
        bd: c.php.version === v.version ? 'var(--brand)' : 'var(--rule)',
        op: v.available ? '1' : '.5',
        pick: () => { if (!v.available) { this.toast('PHP ' + v.version + ' has no server image yet', { kind: 'info' }); return; } set(cc => { cc.php.version = v.version; }); } })),
      ...Object.fromEntries(Object.entries(fields).flatMap(([k, f]) => [['hostF_' + k, f.val], ['hostOn_' + k, f.on], ['hostLim_' + k, lim(k)]])),
      // Redirects
      hostRedirRows: (c.redirects || []).map((r, i) => ({ from: r.from, to: r.to, code: String(r.code),
        remove: () => set(cc => { cc.redirects.splice(i, 1); }) })),
      hostRedirEmpty: !(c.redirects || []).length,
      hostRFrom: s.hostRFrom || '', onHostRFrom: e => this.setState({ hostRFrom: e.target.value }),
      hostRTo: s.hostRTo || '', onHostRTo: e => this.setState({ hostRTo: e.target.value }),
      hostRCodeChips: [301, 302].map(code => ({ label: String(code) + (code === 301 ? ' permanent' : ' temporary'),
        fg: (s.hostRCode || 301) === code ? 'var(--brand-ink)' : 'var(--ink-dim)', bg: (s.hostRCode || 301) === code ? 'var(--brand-soft)' : 'transparent',
        bd: (s.hostRCode || 301) === code ? 'var(--brand)' : 'var(--rule)', pick: () => this.setState({ hostRCode: code }) })),
      hostAddRedirect: () => {
        const from = (this.state.hostRFrom || '').trim(), to = (this.state.hostRTo || '').trim();
        if (!/^\/[A-Za-z0-9._~%\/+-]*\*?$/.test(from)) { this.toast('From must be a path like /old-page or /blog/*', { kind: 'error' }); return; }
        if (!/^(\/\S*|https?:\/\/\S+)$/.test(to)) { this.toast('To must be a path or an http(s) URL', { kind: 'error' }); return; }
        set(cc => { cc.redirects = cc.redirects || []; cc.redirects.push({ from, to, code: this.state.hostRCode || 301 }); });
        this.setState({ hostRFrom: '', hostRTo: '' });
      },
      // Access: IP deny + password protection
      hostDenyRows: (c.ip_deny || []).map((ipx, i) => ({ ip: ipx, remove: () => set(cc => { cc.ip_deny.splice(i, 1); }) })),
      hostDenyEmpty: !(c.ip_deny || []).length,
      hostDenyIn: s.hostDenyIn || '', onHostDenyIn: e => this.setState({ hostDenyIn: e.target.value }),
      hostAddDeny: () => {
        const v = (this.state.hostDenyIn || '').trim();
        if (!/^[0-9a-fA-F:.]+(\/\d{1,3})?$/.test(v)) { this.toast('Enter an IP address or CIDR range', { kind: 'error' }); return; }
        set(cc => { cc.ip_deny = cc.ip_deny || []; if (!cc.ip_deny.includes(v)) cc.ip_deny.push(v); });
        this.setState({ hostDenyIn: '' });
      },
      hostPpOn: !!(c.password_protect && c.password_protect.enabled), hostPpMark: c.password_protect && c.password_protect.enabled ? '✓' : '',
      toggleHostPp: () => set(cc => { cc.password_protect = cc.password_protect || {}; cc.password_protect.enabled = !cc.password_protect.enabled; }),
      hostPpUser: (c.password_protect && c.password_protect.username) || '',
      onHostPpUser: e => set(cc => { cc.password_protect.username = e.target.value; }),
      hostPpPass: (c.password_protect && c.password_protect.password) || '',
      onHostPpPass: e => set(cc => { cc.password_protect.password = e.target.value; }),
      hostPpPassPh: c.password_protect && c.password_protect.has_password ? 'Password saved; type to change it' : 'Password (8+ characters)',
      // Tools
      hostDebugMark: c.debug ? '✓' : '', toggleHostDebug: () => set(cc => { cc.debug = !cc.debug; }),
      hostNoindexMark: c.noindex !== false ? '✓' : '', toggleHostNoindex: () => set(cc => { cc.noindex = cc.noindex === false; }),
      hostSuspendMark: c.suspended ? '✓' : '', toggleHostSuspend: () => set(cc => { cc.suspended = !cc.suspended; }),
      hostIsSuspended: !!(d.config && d.config.suspended),
      hostRemoveIco: this.icon('restart'),
      hostRemoveName: s.hostRemoveName || '', onHostRemoveName: e => this.setState({ hostRemoveName: e.target.value }),
      hostRemoveWho: ((this._detail && this._detail.site && this._detail.site.name) || '') ,
      hostRemoveReady: !!(this._detail && this._detail.site) && ['name', 'site'].some(k => String((this._detail.site || {})[k] || '').toLowerCase() === String(s.hostRemoveName || '').trim().toLowerCase()),
      hostRemove: () => {
        const site = (this._detail && this._detail.site) || {};
        const [siteId, env] = this.hostingKey().split('|');
        if (!['name', 'site'].some(k => String(site[k] || '').toLowerCase() === String(this.state.hostRemoveName || '').trim().toLowerCase())) { this.toast('Type the site name to confirm', { kind: 'error' }); return; }
        this.setState({ hostRemoveName: '' });
        this.startJob({ label: 'remove', target: (site.name || '') + ' · ' + env, command: 'host site remove', siteId, environment: env,
          dispatch: () => this.api('/sites/' + siteId + '/hosting/remove', { method: 'POST', body: { environment: env, confirm: site.name } }),
          // The site is gone from this server (and inactive in the fleet):
          // leave its page for the Servers screen and refresh both lists.
          onFinish: () => {
            this._host = null;
            this.FLEET = this.FLEET.filter(f => String(f.id) !== String(siteId) || String(env).toLowerCase() !== 'production');
            this.toast((site.name || 'The site') + ' is off its server. The container is kept for 7 days.', { kind: 'success', timeout: 8000 });
            this.go('servers')(); this.loadServers(true); this.hydrate();
          } });
      },
      // Local backups
      ...this.hostBackupVals(), ...this.hostMailVals(c, set), ...this.hostCacheVals(c, set, d.config),
      // History
      hostHist: (d.history || []).map(r => ({ rev: 'r' + r.revision, note: r.note || '—', by: r.by, when: ago(r.created_at),
        status: (statusMap[r.status] || [r.status])[0], fg: (statusMap[r.status] || [0, 'var(--ink-dim)'])[1],
        canRestore: r.revision !== d.revision,
        restore: async () => { if (await this.uiConfirm('Restore revision ' + r.revision + '? It is saved as a new revision and applied.', { label: 'Restore' })) this.hostingSend('/restore', { revision: r.revision }, 'Restoring revision ' + r.revision); } })),
      hostHistEmpty: !(d.history || []).length
    };
  }
});
