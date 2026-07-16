// CaptainCore v3 — Domains / DNS / Email real-data layer (mixin).
// Overrides openDomain() to load the real bundle and realDomainVals() replaces
// the design's mock bindings inside computeDomain when hydrated.
//
// Contract (audited from v1):
//   GET  /domain/{id}                → { provider:{nameservers,contacts,locked,whois_privacy,status},
//                                        accounts[], connected_sites[], details:{mailgun_id,mailgun_zone,
//                                        mailgun_forwarding_id,…} }
//   GET  /dns/{id}                   → { records:[Constellix records], nameservers[] } | {code:'no_zone'}
//   POST/PUT/DELETE /dns/{id}/records[/{rid}] — per-record CRUD ({id} = CaptainCore domain_id;
//        the v1 bulk endpoint instead keys on the Constellix remote_id — deliberately not used here).
//        value shapes: A/AAAA/ANAME/CNAME/TXT/SPF [{value}], MX [{server,priority}],
//        SRV [{host,priority,weight,port}], HTTP url-string.
//   POST /domain/{id}/activate-dns-zone · GET /domains/{id}/zone (BIND export)
//   GET  /domain/{id}/lock_{on|off} | /privacy_{on|off} | /auth_code
//   POST /domain/{id}/nameservers { nameservers:[…] } · /contacts { contacts:{…} }
//   POST /domain/{id}/activate-forward-email (409 mx_conflict → re-post {overwrite_mx:true})
//   GET/POST/DELETE /domain/{id}/email-forwards[/{alias_id}] · GET /email-forwarding/status
//   GET  /domain/{id}/mailgun · POST /mailgun/setup {domain} · POST /mailgun/verify · GET /mailgun/events
// Known contract gaps (STATUS.md): no expiry/auto-renew anywhere; /domains/ list has no account names.
// DNS edits stage locally (design behavior) and commit per-record on Save.

Object.assign(Component.prototype, {

  openDomain(id) {
    this.setState({ route: 'domain', domainId: id, domTab: 'dns', paletteOpen: false,
      dnsRecs: this._hydrated ? [] : this.DNS_RECS.map(r => ({ ...r })),
      dnsDirty: false, dnsDel: [], dnsT: 'A', dnsN: '', dnsV: '', dnsEdit: 0,
      fwds: this._hydrated ? [] : this.FWDS.map(f => ({ ...f })), fwdAlias: '', fwdDest: '',
      reg: { auto: false, lock: false, priv: false } });
    if (this._hydrated) this.loadDomainDetail(id);
  },

  loadDomainDetail(id) {
    const dom = this._domain = { domainId: id, info: null, infoErr: '', dns: null, dnsErr: '',
      noZone: false, dnsLoading: true, saving: false, forwards: null, fwdStatus: null,
      fwdLoading: false, fwdErr: '', mailgun: null, mgLoading: false, mgErr: '', mgEvents: null };
    const bump = () => { if (this._domain === dom) this.setState({}); };
    this.api('/domain/' + id).then(info => {
      if (this._domain !== dom) return;
      dom.info = info || {};
      const p = info && info.provider;
      if (p && !p.errors) this.setState({ reg: { auto: false, lock: p.locked === 'on', priv: p.whois_privacy === 'on' } });
      bump();
    }).catch(() => { dom.infoErr = 'Could not load domain details.'; bump(); });
    this.loadDnsZone();
  },

  loadDnsZone() {
    const dom = this._domain;
    if (!dom) return;
    dom.dnsLoading = true; dom.noZone = false; dom.dnsErr = '';
    this.setState({});
    this.api('/dns/' + dom.domainId).then(z => {
      if (this._domain !== dom) return;
      dom.dnsLoading = false;
      if (z && z.code === 'no_zone') { dom.noZone = true; this.setState({ dnsRecs: [], dnsDel: [], dnsDirty: false }); return; }
      if (!z || !Array.isArray(z.records)) { dom.dnsErr = (z && z.message) || 'Could not load DNS records.'; this.setState({}); return; }
      dom.dns = z;
      this.setState({ dnsRecs: z.records.map(r => this.dnsRowFromApi(r)), dnsDel: [], dnsDirty: false, dnsEdit: 0 });
    }).catch(() => { if (this._domain === dom) { dom.dnsLoading = false; dom.dnsErr = 'Could not load DNS records.'; this.setState({}); } });
  },

  dnsRowFromApi(r) {
    const type = String(r.type || '').toUpperCase();
    const v = r.value;
    let value;
    if (type === 'MX') value = (Array.isArray(v) ? v : []).map(x => x.priority + ' ' + x.server).join(', ');
    else if (type === 'SRV') value = (Array.isArray(v) ? v : []).map(x => [x.priority, x.weight, x.port, x.host].join(' ')).join(', ');
    else if (type === 'HTTP') value = (v && v.url) || '';
    else value = Array.isArray(v) ? v.map(x => x.value).join(', ') : String(v == null ? '' : v);
    return { uid: r.id, recId: r.id, type, name: r.name || '@', value, ttl: String(r.ttl == null ? 3600 : r.ttl) };
  },

  dnsValueForApi(type, valueStr) {
    const t = String(type).toUpperCase();
    const parts = String(valueStr).split(',').map(s => s.trim()).filter(Boolean);
    if (t === 'MX') return parts.map(s => { const m = s.split(/\s+/);
      return m.length > 1 ? { server: m[1], priority: parseInt(m[0], 10) || 10 } : { server: m[0], priority: 10 }; });
    if (t === 'SRV') return parts.map(s => { const m = s.split(/\s+/);
      return { priority: parseInt(m[0], 10) || 0, weight: parseInt(m[1], 10) || 0, port: parseInt(m[2], 10) || 0, host: m[3] || '' }; });
    if (t === 'HTTP') return String(valueStr).trim();
    return parts.map(s => ({ value: s }));
  },

  saveDnsReal() {
    const dom = this._domain;
    if (!dom || dom.saving) return;
    dom.saving = true;
    this.setState({});
    const s = this.state;
    const calls = [];
    (s.dnsRecs || []).forEach(r => {
      const body = { type: r.type, name: r.name === '@' ? '' : r.name,
        value: this.dnsValueForApi(r.type, r.value), ttl: parseInt(r.ttl, 10) || 3600 };
      if (!r.recId) calls.push(this.api('/dns/' + dom.domainId + '/records', { method: 'POST', body }));
      else if (r.edited) calls.push(this.api('/dns/' + dom.domainId + '/records/' + r.recId, { method: 'PUT', body }));
    });
    (s.dnsDel || []).forEach(id => calls.push(this.api('/dns/' + dom.domainId + '/records/' + id, { method: 'DELETE' })));
    Promise.allSettled(calls).then(rs => {
      if (this._domain !== dom) return;
      dom.saving = false;
      const failed = rs.filter(x => x.status === 'rejected' || (x.value && x.value.code && x.value.data && x.value.data.status >= 400)).length;
      dom.dnsErr = failed ? failed + ' record change' + (failed === 1 ? '' : 's') + ' failed — zone reloaded.' : '';
      this.loadDnsZone();
    });
  },

  activateDnsZone() {
    const dom = this._domain;
    if (!dom) return;
    dom.dnsLoading = true; this.setState({});
    this.api('/domain/' + dom.domainId + '/activate-dns-zone', { method: 'POST', body: {} })
      .then(() => this.loadDnsZone())
      .catch(() => { if (this._domain === dom) { dom.dnsLoading = false; dom.dnsErr = 'Could not activate the DNS zone.'; this.setState({}); } });
  },

  exportZoneReal(name) {
    this.api('/domains/' + this._domain.domainId + '/zone').then(zone => {
      const text = typeof zone === 'string' ? zone : JSON.stringify(zone, null, 2);
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([text], { type: 'text/plain' }));
      a.download = name + '.txt';
      a.click();
      URL.revokeObjectURL(a.href);
    }).catch(() => {});
  },

  // ── Email forwarding ─────────────────────────────────────────
  loadForwards() {
    const dom = this._domain;
    if (!dom || !dom.info || dom.fwdLoading) return;
    const details = dom.info.details || {};
    if (!details.mailgun_forwarding_id) { dom.forwards = null; this.setState({}); return; }
    dom.fwdLoading = true; dom.fwdErr = '';
    this.setState({});
    Promise.allSettled([
      this.api('/domain/' + dom.domainId + '/email-forwards'),
      this.api('/domain/' + dom.domainId + '/email-forwarding/status')
    ]).then(([f, st]) => {
      if (this._domain !== dom) return;
      dom.fwdLoading = false;
      dom.forwards = (f.status === 'fulfilled' && Array.isArray(f.value)) ? f.value : [];
      if (f.status !== 'fulfilled') dom.fwdErr = 'Could not load forwards.';
      dom.fwdStatus = st.status === 'fulfilled' ? st.value : null;
      const ok = dom.fwdStatus && dom.fwdStatus.has_mx_record;
      this.setState({ fwds: dom.forwards.map(x => ({ uid: x.id, alias: x.name || '*',
        dest: (x.recipients || []).join(', '),
        status: (x.name || '*') === '*' ? 'Catch-all' : ok ? 'Verified' : 'Pending verification' })) });
    });
  },

  activateForwarding(overwrite) {
    const dom = this._domain;
    if (!dom) return;
    dom.fwdLoading = true; dom.fwdErr = '';
    this.setState({});
    this.api('/domain/' + dom.domainId + '/activate-forward-email', { method: 'POST', body: overwrite ? { overwrite_mx: true } : {} }).then(res => {
      if (this._domain !== dom) return;
      dom.fwdLoading = false;
      if (res && res.code === 'mx_conflict') {
        this.setState({});
        if (confirm('This domain already has MX records. Overwrite them with Mailgun forwarding MX records?')) this.activateForwarding(true);
        return;
      }
      if (res && res.code) { dom.fwdErr = res.message || 'Activation failed.'; this.setState({}); return; }
      this.loadDomainDetail(dom.domainId);
      this.setState({ domTab: 'forwarding' });
    }).catch(() => { if (this._domain === dom) { dom.fwdLoading = false; dom.fwdErr = 'Activation failed.'; this.setState({}); } });
  },

  // ── Mailgun sending ──────────────────────────────────────────
  loadMailgun() {
    const dom = this._domain;
    if (!dom || !dom.info || dom.mgLoading) return;
    const details = dom.info.details || {};
    if (!details.mailgun_id) { dom.mailgun = null; this.setState({}); return; }
    dom.mgLoading = true; dom.mgErr = '';
    this.setState({});
    this.api('/domain/' + dom.domainId + '/mailgun').then(res => {
      if (this._domain !== dom) return;
      dom.mgLoading = false;
      if (res && res.code) { dom.mgErr = res.message || 'Could not load Mailgun details.'; }
      else dom.mailgun = res;
      this.setState({});
    }).catch(() => { if (this._domain === dom) { dom.mgLoading = false; dom.mgErr = 'Could not load Mailgun details.'; this.setState({}); } });
    this.api('/domain/' + dom.domainId + '/mailgun/events').then(res => {
      if (this._domain !== dom) return;
      dom.mgEvents = (res && Array.isArray(res.items)) ? res.items : [];
      this.setState({});
    }).catch(() => {});
  },

  // ── Binding overrides (spread at the end of computeDomain) ───
  realDomainVals(s, d) {
    const dom = (this._domain && this._domain.domainId === s.domainId) ? this._domain : null;
    if (!dom) return {};
    const info = dom.info || {};
    const provider = (info.provider && !info.provider.errors) ? info.provider : null;
    const details = info.details || {};
    const typeBg = { A: 'var(--brand-soft)', AAAA: 'var(--brand-soft)', MX: 'var(--warn-soft)', TXT: 'var(--ok-soft)', SPF: 'var(--ok-soft)' };
    const dnsNote = dom.dnsLoading ? 'Loading DNS records…'
      : dom.saving ? 'Saving record changes…'
      : dom.noZone ? 'No DNS zone is active for this domain.'
      : dom.dnsErr || '';
    const lazyTabs = [['dns', 'DNS'], ['registrar', 'Registrar'], ['forwarding', 'Email forwarding'], ['sending', 'Sending']].map(([id, label]) => ({ label,
      fg: s.domTab === id ? 'var(--brand-ink)' : 'var(--ink-dim)',
      line: s.domTab === id ? 'var(--brand)' : 'transparent',
      go: () => { this.setState({ domTab: id });
        if (id === 'forwarding') this.loadForwards();
        else if (id === 'sending') this.loadMailgun(); } }));
    const dnsRows = (s.dnsRecs || []).map(r => ({ ...r, bg: typeBg[r.type] || 'var(--panel-2)',
      editing: s.dnsEdit === r.uid, notEditing: s.dnsEdit !== r.uid,
      startEdit: () => this.setState({ dnsEdit: r.uid, dnsEN: r.name, dnsEV: r.value, dnsETtl: r.ttl }),
      del: (e) => { e.stopPropagation(); this.setState(st => ({
        dnsRecs: st.dnsRecs.filter(x => x.uid !== r.uid),
        dnsDel: r.recId ? [...(st.dnsDel || []), r.recId] : (st.dnsDel || []),
        dnsDirty: true })); } }));
    const owner = (provider && provider.contacts && (provider.contacts.owner || provider.contacts.admin)) || {};
    const ct = {
      Name: owner.name || [owner.firstName, owner.lastName].filter(Boolean).join(' ') || '—',
      Organization: owner.organization || owner.org_name || '—',
      Email: owner.email || '—',
      Phone: owner.phone || '—',
      Address: owner.address1 || owner.address || '—',
      'City / State': [owner.city, owner.stateProvince || owner.state].filter(Boolean).join(', ') || '—',
      Country: owner.country || '—'
    };
    const nsReal = (provider && Array.isArray(provider.nameservers) && provider.nameservers.length)
      ? provider.nameservers.map(n => (n && n.value) || String(n)).filter(Boolean)
      : (dom.dns && Array.isArray(dom.dns.nameservers) ? dom.dns.nameservers.map(n => (n && n.value) || String(n)) : []);
    const regToggle = (key, label, path) => ({ label,
      bg: s.reg[key] ? 'var(--brand)' : 'var(--rule)',
      just: s.reg[key] ? 'flex-end' : 'flex-start',
      state: provider ? (s.reg[key] ? 'On' : 'Off') : '—',
      flip: () => { if (!provider) return;
        const next = !this.state.reg[key];
        this.setState(st => ({ reg: { ...st.reg, [key]: next } }));
        this.api('/domain/' + dom.domainId + '/' + path + '_' + (next ? 'on' : 'off')).catch(() => {}); } });
    const fwdActive = !!details.mailgun_forwarding_id;
    const mgActive = !!details.mailgun_id;
    const mgRecs = ((dom.mailgun && dom.mailgun.sending_dns_records) || []).map(r => ({
      type: String(r.record_type || '').toUpperCase(), host: r.name || '', value: r.value || '',
      stLabel: r.valid === 'valid' ? 'Verified' : 'Pending', stFg: r.valid === 'valid' ? 'var(--ok)' : 'var(--warn)',
      pending: r.valid !== 'valid',
      verify: () => { this.api('/domain/' + dom.domainId + '/mailgun/verify', { method: 'POST', body: {} })
        .then(() => { dom.mgLoading = false; dom.mailgun = null; this.loadMailgun(); }).catch(() => {}); } }));
    const mgEvents = (dom.mgEvents || []).map(ev => ({
      t: ev.timestamp ? new Date(ev.timestamp * 1000).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '',
      text: (ev.event || '') + (ev.message && ev.message.headers && ev.message.headers.subject ? ' · ' + ev.message.headers.subject : '')
        + (ev.recipient ? ' → ' + ev.recipient : '') }));
    return {
      domTabs: lazyTabs,
      domStatus: (dom.noZone ? 'DNS inactive' : 'DNS active') + (provider ? ' · Registrar ' + (provider.status || 'connected') : ' · No registrar connected')
        + ((info.accounts || []).length ? ' · ' + info.accounts.map(a => a.name).join(', ') : ''),
      dnsRows,
      dnsNotice: !!dnsNote, dnsNoticeText: dnsNote,
      dnsShowActivate: dom.noZone && !dom.dnsLoading,
      activateZone: () => this.activateDnsZone(),
      dnsEditDone: () => this.setState(st => ({ dnsRecs: st.dnsRecs.map(x => x.uid === st.dnsEdit
        ? { ...x, name: st.dnsEN.trim() || '@', value: st.dnsEV.trim() || x.value, ttl: st.dnsETtl.trim() || '3600', edited: !!x.recId }
        : x), dnsEdit: 0, dnsDirty: true })),
      saveDns: () => this.saveDnsReal(),
      discardDns: () => this.loadDnsZone(),
      zoneReplace: () => this.setState(st => ({
        dnsRecs: this.parseZone(st.zoneText).map((r, i) => ({ ...r, uid: 'z' + Date.now() + i })),
        dnsDel: [...(st.dnsDel || []), ...st.dnsRecs.filter(x => x.recId).map(x => x.recId)],
        dnsDirty: true, zoneOpen: false, zoneText: '' })),
      exportZone: () => this.exportZoneReal(d.name),
      regRegistrar: provider ? (provider.status || 'Connected') : 'Not connected',
      regExpires: '—', regExpFg: 'var(--ink)', regWarn: false, regShowRenew: false,
      regShowAuto: false,
      togAuto: { label: 'Auto-renew', bg: 'var(--rule)', just: 'flex-start', state: '—', flip: () => {} },
      togLock: regToggle('lock', 'Transfer lock', 'lock'),
      togPriv: regToggle('priv', 'WHOIS privacy', 'privacy'),
      nsList: nsReal.map(n => ({ n })),
      saveNsv: () => { const lines = this.state.nsvText.split('\n').map(l => l.trim()).filter(Boolean);
        if (!lines.length) return;
        this.setState({ nsvOpen: false });
        this.api('/domain/' + dom.domainId + '/nameservers', { method: 'POST', body: { nameservers: lines } })
          .then(() => this.loadDomainDetail(dom.domainId)).catch(() => {}); },
      ctLine1: ct.Name + ' · ' + ct.Organization,
      ctLine2: ct.Address + ', ' + ct['City / State'] + ' · ' + ct.Country,
      ctLine3: ct.Email + ' · ' + ct.Phone,
      ctFields: Object.keys(ct).map(label => ({ label, v: (s.ctDraft || {})[label] ?? '',
        on: e => this.setState(st => ({ ctDraft: { ...st.ctDraft, [label]: e.target.value } })) })),
      openCtDlg: () => this.setState({ ctOpen: true, ctDraft: { ...ct } }),
      saveCt: () => { this.setState({ ctOpen: false });
        this.api('/domain/' + dom.domainId + '/contacts', { method: 'POST', body: { contacts: this.state.ctDraft } })
          .then(() => this.loadDomainDetail(dom.domainId)).catch(() => {}); },
      authMark: s.copied === 'auth' ? 'Copied ✓' : (s.authBusy ? 'Fetching…' : 'Copy'),
      authCopy: () => { if (this.state.authBusy) return;
        this.setState({ authBusy: true });
        this.api('/domain/' + dom.domainId + '/auth_code').then(r => {
          const code = typeof r === 'string' ? r : (r && (r.auth_code || r.code || r.message)) || '';
          try { navigator.clipboard.writeText(code); } catch (e) {}
          this.setState({ authBusy: false, copied: 'auth' });
          clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400);
        }).catch(() => this.setState({ authBusy: false })); },
      fwdInactive: !fwdActive, fwdLoading: dom.fwdLoading,
      fwdNotice: !!dom.fwdErr, fwdNoticeText: dom.fwdErr,
      activateFwd: () => this.activateForwarding(false),
      addFwd: () => { const a = this.state.fwdAlias.trim(), t = this.state.fwdDest.trim();
        if (!a || !t || !fwdActive) return;
        this.setState({ fwdAlias: '', fwdDest: '' });
        this.api('/domain/' + dom.domainId + '/email-forwards', { method: 'POST',
          body: { name: a.replace(/@.*$/, ''), recipients: t } })
          .then(() => { dom.fwdLoading = false; this.loadForwards(); }).catch(() => {}); },
      fwdRows: (s.fwds || []).map(f => ({ ...f, aliasFull: (f.alias === '*' ? 'anything' : f.alias) + '@' + d.name,
        stFg: f.status === 'Verified' ? 'var(--ok)' : f.status === 'Catch-all' ? 'var(--ink-dim)' : 'var(--warn)',
        del: () => this.api('/domain/' + dom.domainId + '/email-forwards/' + f.uid, { method: 'DELETE' })
          .then(() => { dom.fwdLoading = false; this.loadForwards(); }).catch(() => {}) })),
      mgInactive: !mgActive, mgLoading: dom.mgLoading,
      mgNotice: !!dom.mgErr, mgNoticeText: dom.mgErr,
      mgSetup: () => { this.api('/domain/' + dom.domainId + '/mailgun/setup', { method: 'POST', body: { domain: 'mg.' + d.name } })
        .then(() => this.loadDomainDetail(dom.domainId)).catch(() => {}); },
      mgHost: details.mailgun_zone || 'mg.' + d.name,
      mgSupp: dom.mailgun && dom.mailgun.state ? 'state: ' + dom.mailgun.state : '',
      mgRecs, mgEvents,
      mgShowDeploy: false, mgDeploy: () => {}
    };
  }

});
