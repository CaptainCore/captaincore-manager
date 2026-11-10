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

    return { ...vals,
      hostReady: true,
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
      hostPhpChips: (d.php_versions || []).map(v => ({ label: 'PHP ' + v.version + (v.available ? '' : ' (soon)'),
        fg: c.php.version === v.version ? 'var(--brand-ink)' : v.available ? 'var(--ink-dim)' : 'var(--ink-faint, var(--ink-dim))',
        bg: c.php.version === v.version ? 'var(--brand-soft)' : 'transparent',
        bd: c.php.version === v.version ? 'var(--brand)' : 'var(--rule)',
        op: v.available ? '1' : '.5',
        pick: () => { if (!v.available) { this.toast('PHP ' + v.version + ' has no server image yet', { kind: 'info' }); return; } set(cc => { cc.php.version = v.version; }); } })),
      ...Object.fromEntries(Object.entries(fields).flatMap(([k, f]) => [['hostF_' + k, f.val], ['hostOn_' + k, f.on], ['hostLim_' + k, lim(k)]])),
      // History
      hostHist: (d.history || []).map(r => ({ rev: 'r' + r.revision, note: r.note || '—', by: r.by, when: ago(r.created_at),
        status: (statusMap[r.status] || [r.status])[0], fg: (statusMap[r.status] || [0, 'var(--ink-dim)'])[1],
        canRestore: r.revision !== d.revision,
        restore: async () => { if (await this.uiConfirm('Restore revision ' + r.revision + '? It is saved as a new revision and applied.', { label: 'Restore' })) this.hostingSend('/restore', { revision: r.revision }, 'Restoring revision ' + r.revision); } })),
      hostHistEmpty: !(d.history || []).length
    };
  }
});
