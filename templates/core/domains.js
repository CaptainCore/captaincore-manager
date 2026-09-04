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
//   GET  /domain/{id}/mailgun/usage?period=day|month|year → { totals:{sent,delivered,failed,delivery_rate},
//        series:[{time,label,sent,received,delivered,failed}] } (Mailgun stats/total, 10-min transient)
//   DELETE /domains/{id} — domain record + linked DNS / forwarding / Mailgun zones
// Known contract gaps (STATUS.md): no expiry/auto-renew anywhere; /domains/ list has no account names.
// DNS edits stage locally (design behavior) and commit per-record on Save.

Object.assign(Component.prototype, {

  openDomain(id) {
    this.setState({ route: 'domain', domainId: id, domTab: 'dns', paletteOpen: false,
      dnsRecs: this._hydrated ? [] : this.DNS_RECS.map(r => ({ ...r })),
      dnsDirty: false, dnsDel: [], dnsT: 'A', dnsN: '', dnsV: '', dnsEdit: 0,
      fwds: this._hydrated ? [] : this.FWDS.map(f => ({ ...f })), fwdAlias: '', fwdDest: '',
      mgSuppOpen: false, mgDeployOpen: false, mgDepQ: '', mgDepTarget: null, mgDepFrom: '', mgDepBusy: false,
      mgSub: 'mg',
      reg: { auto: false, lock: false, priv: false } });
    if (this._hydrated) this.loadDomainDetail(id);
  },

  loadDomainDetail(id) {
    const dom = this._domain = { domainId: id, info: null, infoErr: '', dns: null, dnsErr: '',
      noZone: false, dnsLoading: true, saving: false, forwards: null, fwdStatus: null,
      fwdLoading: false, fwdErr: '', mailgun: null, mgLoading: false, mgErr: '', mgEvents: null,
      mgUsage: null, mgUsagePeriod: 'day', mgUsageLoading: false, mgUsageErr: '',
      suppType: 'bounces', suppItems: null, suppLoading: false, suppErr: '' };
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

  // Structured sub-values ride on `subs` (the legacy editor's per-value model:
  // MX priority/server pairs, TXT/A/… round-robin value lists) while `value`
  // stays the joined display string. Editing multi-value types operates on
  // subs — so a TXT value CONTAINING a comma survives, which the old
  // join-then-split-on-comma round trip corrupted.
  dnsRowFromApi(r) {
    const type = String(r.type || '').toUpperCase();
    const v = r.value;
    let value, subs = null;
    if (type === 'MX') {
      subs = (Array.isArray(v) ? v : []).map(x => ({ priority: String(x.priority == null ? 10 : x.priority), server: x.server || '' }));
      value = subs.map(x => x.priority + ' ' + x.server).join(', ');
    } else if (type === 'SRV') {
      subs = (Array.isArray(v) ? v : []).map(x => ({ priority: String(x.priority || 0), weight: String(x.weight || 0), port: String(x.port || 0), host: x.host || '' }));
      value = subs.map(x => [x.priority, x.weight, x.port, x.host].join(' ')).join(', ');
    } else if (type === 'HTTP') {
      value = (v && v.url) || '';
    } else if (Array.isArray(v)) {
      subs = v.map(x => ({ value: String(x.value == null ? '' : x.value) }));
      value = subs.map(x => x.value).join(', ');
    } else {
      value = String(v == null ? '' : v);
    }
    return { uid: r.id, recId: r.id, type, name: r.name || '@', value, subs, ttl: String(r.ttl == null ? 3600 : r.ttl) };
  },

  // Single-value types keep the plain input; everything else edits sub-rows.
  DNS_SINGLE_TYPES: ['CNAME', 'HTTP'],

  // Editable copy of a row's sub-values (each with a stable suid so the
  // ref-seeded inputs survive row removal without index reuse corrupting
  // neighbours). Rows born from zone import / the add bar have no subs yet —
  // parse their display string once.
  dnsSubsFor(r) {
    if (this.DNS_SINGLE_TYPES.includes(r.type)) return null;
    let base = r.subs;
    if (!base || !base.length) {
      const parsed = this.dnsValueForApi(r.type, r.value);
      base = (Array.isArray(parsed) ? parsed : []).map(x => typeof x === 'object'
        ? Object.fromEntries(Object.entries(x).map(([k, val]) => [k, String(val == null ? '' : val)]))
        : { value: String(x) });
    }
    if (!base.length) base = [r.type === 'MX' ? { priority: '10', server: '' } : r.type === 'SRV' ? { priority: '0', weight: '0', port: '0', host: '' } : { value: '' }];
    this._suid = (this._suid || 0);
    return base.map(x => ({ ...x, suid: 's' + (++this._suid) }));
  },

  // Drop empty sub-rows; keep field strings (API conversion happens on save).
  dnsCleanSubs(subs) {
    return (subs || []).filter(x => ((x.server != null ? x.server : (x.host != null ? x.host : x.value)) || '').trim() !== '')
      .map(({ suid, ...rest }) => rest);
  },

  // Sub-values for a row WITHOUT editor suids — the row's own subs when it
  // has them, else its display string parsed once (rows from the add bar /
  // zone import carry only `value`).
  dnsSubsPlain(r) {
    if (r.subs && r.subs.length) return r.subs.map(({ suid, ...rest }) => rest);
    const parsed = this.dnsValueForApi(r.type, r.value);
    return (Array.isArray(parsed) ? parsed : []).map(x => typeof x === 'object'
      ? Object.fromEntries(Object.entries(x).map(([k, val]) => [k, String(val == null ? '' : val)]))
      : { value: String(x) });
  },

  // Legacy groupDNS() port. Constellix keeps ONE record per name+type and
  // stacks the values inside it, so a second TXT "@" (or A / MX / …) row must
  // fold into the existing row rather than POST as a new record — the API
  // rejects the duplicate. Every multi-value type groups; CNAME/HTTP are
  // single by definition. Later rows merge into the first row with the same
  // key: values append, the survivor is marked edited when it exists at
  // Constellix, and a swallowed row that had its own record id is queued for
  // deletion (the legacy editor only dropped it locally).
  dnsGroupRecs(recs, del) {
    const key = r => r.type + '|' + (String(r.name || '@').trim().toLowerCase() || '@');
    const out = []; const byKey = {}; const extraDel = [];
    (recs || []).forEach(r => {
      if (this.DNS_SINGLE_TYPES.includes(r.type)) { out.push(r); return; }
      const k = key(r); const t = byKey[k];
      if (!t) { byKey[k] = r; out.push(r); return; }
      const subs = this.dnsCleanSubs([...this.dnsSubsPlain(t), ...this.dnsSubsPlain(r)]);
      const merged = { ...t, subs, value: this.dnsSubsToText(t.type, subs), edited: !!t.recId };
      byKey[k] = merged; out[out.indexOf(t)] = merged;
      if (r.recId) extraDel.push(r.recId);
    });
    return { recs: out, del: [...(del || []), ...extraDel] };
  },

  dnsSubsToText(type, subs) {
    if (type === 'MX') return subs.map(x => x.priority + ' ' + x.server).join(', ');
    if (type === 'SRV') return subs.map(x => [x.priority, x.weight, x.port, x.host].join(' ')).join(', ');
    return subs.map(x => x.value).join(', ');
  },

  dnsSubsForApi(type, subs) {
    if (type === 'MX') return subs.map(x => ({ server: x.server, priority: parseInt(x.priority, 10) || 10 }));
    if (type === 'SRV') return subs.map(x => ({ priority: parseInt(x.priority, 10) || 0, weight: parseInt(x.weight, 10) || 0, port: parseInt(x.port, 10) || 0, host: x.host || '' }));
    return subs.map(x => ({ value: x.value }));
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

  // Legacy-editor autocorrect, applied to the API-shaped value right before
  // save: hostname targets (CNAME/ANAME value, MX server, SRV host) must be
  // fully qualified, so a missing trailing "." is added; TXT values are
  // wrapped in double quotes when the user left them bare. Constellix rejects
  // (or silently mis-stores) both shapes, and nothing server-side fixes them.
  dnsNormalizeValue(type, value) {
    const t = String(type).toUpperCase();
    const fqdn = h => { h = String(h == null ? '' : h).trim(); return h && !h.endsWith('.') ? h + '.' : h; };
    if (!Array.isArray(value)) return value;
    if (t === 'CNAME' || t === 'ANAME') return value.map(x => ({ ...x, value: fqdn(x.value) }));
    if (t === 'MX') return value.map(x => ({ ...x, server: fqdn(x.server) }));
    if (t === 'SRV') return value.map(x => ({ ...x, host: fqdn(x.host) }));
    if (t === 'TXT') return value.map(x => {
      let v = String(x.value == null ? '' : x.value).trim();
      if (!v.startsWith('"')) v = '"' + v;
      if (v.length < 2 || !v.endsWith('"')) v = v + '"';
      return { ...x, value: v };
    });
    return value;
  },

  saveDnsReal() {
    const dom = this._domain;
    if (!dom || dom.saving) return;
    dom.saving = true;
    // Fold duplicate name+type rows before building calls (zone import and
    // edit-renames can create them; the add bar already merges on entry).
    const s = this.dnsGroupRecs(this.state.dnsRecs, this.state.dnsDel);
    this.setState({ dnsRecs: s.recs, dnsDel: s.del });
    const calls = [];
    s.recs.forEach(r => {
      const body = { type: r.type, name: r.name === '@' ? '' : r.name,
        value: this.dnsNormalizeValue(r.type, (r.subs && !this.DNS_SINGLE_TYPES.includes(r.type))
          ? this.dnsSubsForApi(r.type, r.subs)
          : this.dnsValueForApi(r.type, r.value)), ttl: parseInt(r.ttl, 10) || 3600 };
      if (!r.recId) calls.push(this.api('/dns/' + dom.domainId + '/records', { method: 'POST', body }));
      else if (r.edited) calls.push(this.api('/dns/' + dom.domainId + '/records/' + r.recId, { method: 'PUT', body }));
    });
    s.del.forEach(id => calls.push(this.api('/dns/' + dom.domainId + '/records/' + id, { method: 'DELETE' })));
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

  // ── Admin zone teardown (v1 parity; each has a v1 DELETE route) ──
  // DNS zone → Constellix, forwarding → Mailgun routes, sending → Mailgun
  // sending domain. All destructive, so each confirms with the domain name.
  async deleteZone(kind) {
    const dom = this._domain;
    if (!dom) return;
    // GET /domain/{id} carries no name; fall back to the Domains list row.
    const name = (dom.info && dom.info.name) || ((this.state.domList || this.DOMAINS || []).find(x => String(x.id) === String(dom.domainId)) || {}).name || 'this domain';
    const spec = {
      dns:     { path: '/domain/' + dom.domainId + '/dns-zone',        label: 'DNS zone',
                 warn: 'Delete the DNS zone for ' + name + '? Every record is removed at Constellix and the domain stops resolving through Anchor.' },
      forward: { path: '/domain/' + dom.domainId + '/email-forwarding', label: 'Email forwarding',
                 warn: 'Delete email forwarding for ' + name + '? All aliases stop delivering immediately.' },
      sending: { path: '/domain/' + dom.domainId + '/mailgun',          label: 'Email sending',
                 warn: 'Delete the sending zone for ' + name + '? Any site using these SMTP credentials will stop sending mail.' }
    }[kind];
    if (!spec || !(await this.uiConfirm(spec.warn))) return;
    this.setState({ zoneBusy: kind });
    this.api(spec.path, { method: 'DELETE' }).then(() => {
      if (this._domain !== dom) return;
      if (this.toast) this.toast(spec.label + ' deleted.', { kind: 'success' });
      this.setState({ zoneBusy: '', fwds: [], dnsRecs: null });
      this.loadDomainDetail(dom.domainId);
    }).catch(() => {
      this.setState({ zoneBusy: '' });
      if (this.toast) this.toast('Could not delete the ' + spec.label.toLowerCase() + '.', { kind: 'error' });
    });
  },

  // Per-tab operator teardown rows. Creation already lives in each tab's
  // Activate button (activate-dns-zone / activate-forward-email /
  // mailgun/setup), so this only adds the delete half.
  zoneAdminVals(s, dom, d, details, fwdActive) {
    const isOp = (window.CC_BOOT || {}).dcRole === 'operator';
    const busy = s.zoneBusy || '';
    return {
      zoneShowDns:  isOp && !dom.noZone && !dom.dnsLoading,
      zoneShowFwd:  isOp && fwdActive,
      zoneShowSend: isOp && !!details.mailgun_id,
      zoneDnsLabel:  busy === 'dns' ? 'Deleting…' : 'Delete DNS zone',
      zoneFwdLabel:  busy === 'forward' ? 'Deleting…' : 'Delete email forwarding',
      zoneSendLabel: busy === 'sending' ? 'Deleting…' : 'Delete sending zone',
      zoneDelDns:  () => this.deleteZone('dns'),
      zoneDelFwd:  () => this.deleteZone('forward'),
      zoneDelSend: () => this.deleteZone('sending'),
      // Domain delete is the record itself (v1's deleteDomain). Cascades to
      // the linked DNS / forwarding / sending zones when they exist.
      domShowDelete: isOp,
      domDeleteLabel: busy === 'domain' ? 'Deleting…' : 'Delete domain…',
      domDelete: () => this.deleteDomain(dom.domainId, d.name, {
        dns: !dom.noZone && !dom.dnsLoading,
        forward: fwdActive,
        sending: !!details.mailgun_id
      })
    };
  },

  // DELETE /domains/{id} — removes the CaptainCore record and any linked
  // DNS zone, Mailgun forwarding (apex), and Mailgun sending zone. Does not
  // cancel a registrar registration. extras is optional: when we already
  // know which linked services exist (detail page), the confirm names them.
  async deleteDomain(id, name, extras) {
    if (!id) return;
    const bits = [];
    if (extras) {
      if (extras.dns) bits.push('the DNS zone');
      if (extras.forward) bits.push('email forwarding');
      if (extras.sending) bits.push('the Mailgun sending zone');
    }
    const cascade = bits.length
      ? 'This also deletes ' + (bits.length === 1 ? bits[0] : bits.slice(0, -1).join(', ') + ' and ' + bits[bits.length - 1]) + '.'
      : 'This also deletes the linked DNS zone, email forwarding, and Mailgun sending zone if they exist.';
    if (!(await this.uiConfirm('Delete domain ' + name + '?\n\n' + cascade + '\n\nThe registrar registration is not cancelled. This cannot be undone.'))) return;
    if (!this._hydrated) {
      this.DOMAINS = (this.DOMAINS || []).filter(d => String(d.id) !== String(id));
      this.setState(st => ({
        domList: (st.domList || []).filter(d => String(d.id) !== String(id)),
        route: 'domains', domainId: null, zoneBusy: ''
      }));
      return;
    }
    this.setState({ zoneBusy: 'domain' });
    this.api('/domains/' + id, { method: 'DELETE' }).then(res => {
      if (res && res.code) {
        this.setState({ zoneBusy: '' });
        if (this.toast) this.toast(res.message || 'Could not delete the domain.', { kind: 'error' });
        return;
      }
      this.DOMAINS = (this.DOMAINS || []).filter(d => String(d.id) !== String(id));
      this._domain = null;
      if (this.toast) this.toast((res && res.message) || ('Deleted ' + name + '.'), { kind: 'success' });
      this.setState(st => ({
        domList: st.domList ? st.domList.filter(d => String(d.id) !== String(id)) : st.domList,
        route: 'domains', domainId: null, zoneBusy: ''
      }));
    }).catch(() => {
      this.setState({ zoneBusy: '' });
      if (this.toast) this.toast('Could not delete the domain.', { kind: 'error' });
    });
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

  // v1 parity: re-ask Mailgun to check the zone, then re-render the record
  // panel from the fresh response (GET …/status?verify=true both triggers the
  // check and returns the updated sending/receiving record sets).
  verifyForwardingDns() {
    const dom = this._domain;
    if (!dom || dom.fwdVerifying) return;
    dom.fwdVerifying = true; this.setState({});
    const tid = this.toast('Checking DNS records with Mailgun…', { kind: 'loading' });
    this.api('/domain/' + dom.domainId + '/email-forwarding/status?verify=true').then(st => {
      if (this._domain !== dom) return;
      dom.fwdVerifying = false;
      dom.fwdStatus = st || null;
      const active = st && st.state === 'active';
      this.updateToast(tid, active ? 'Domain verified with Mailgun'
        : 'Checked — some records are still pending. DNS can take up to 24 hours.',
        { kind: active ? 'success' : 'info' });
      this.setState({});
    }).catch(() => {
      if (this._domain !== dom) return;
      dom.fwdVerifying = false;
      this.updateToast(tid, 'Could not reach Mailgun to verify', { kind: 'error' });
      this.setState({});
    });
  },

  // The Constellix injection lives in Domain::activate_email_forwarding(), which
  // the activate route runs — so re-running activation is how you (re)push the
  // Mailgun records into an Anchor-managed zone. Needed when the zone was
  // created AFTER forwarding was switched on, or when records were edited away.
  async injectForwardingDns() {
    const dom = this._domain;
    if (!dom) return;
    if (!(await this.uiConfirm('Add the Mailgun verification records to this domain’s Anchor DNS zone?\n\nExisting Mailgun TXT/CNAME/MX entries are updated in place.', { label: 'Add records' }))) return;
    const tid = this.toast('Adding records to the DNS zone…', { kind: 'loading' });
    this.api('/domain/' + dom.domainId + '/activate-forward-email', { method: 'POST', body: {} }).then(async res => {
      if (this._domain !== dom) return;
      if (res && res.code === 'mx_conflict') {
        this.updateToast(tid, 'Existing MX records found', { kind: 'info' });
        if (await this.uiConfirm('This domain already has MX records. Replace them with Mailgun’s forwarding MX records?', { label: 'Replace MX records', danger: true })) this.activateForwarding(true);
        return;
      }
      if (res && res.code) { this.updateToast(tid, res.message || 'Could not add the records', { kind: 'error' }); return; }
      this.updateToast(tid, 'Records added — verifying with Mailgun…', { kind: 'success' });
      this.loadDnsZone();
      this.verifyForwardingDns();
    }).catch(() => this.updateToast(tid, 'Could not add the records', { kind: 'error' }));
  },

  activateForwarding(overwrite) {
    const dom = this._domain;
    if (!dom) return;
    dom.fwdLoading = true; dom.fwdErr = '';
    this.setState({});
    this.api('/domain/' + dom.domainId + '/activate-forward-email', { method: 'POST', body: overwrite ? { overwrite_mx: true } : {} }).then(async res => {
      if (this._domain !== dom) return;
      dom.fwdLoading = false;
      if (res && res.code === 'mx_conflict') {
        this.setState({});
        if (await this.uiConfirm('This domain already has MX records. Overwrite them with Mailgun forwarding MX records?', { label: 'Overwrite MX records', danger: true })) this.activateForwarding(true);
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
    this.loadMailgunUsage(dom.mgUsagePeriod);
  },

  loadMailgunUsage(period) {
    const dom = this._domain;
    if (!dom || !dom.info) return;
    if (!(dom.info.details || {}).mailgun_id) { dom.mgUsage = null; this.setState({}); return; }
    dom.mgUsagePeriod = period; dom.mgUsageLoading = true; dom.mgUsageErr = '';
    this.setState({});
    this.api('/domain/' + dom.domainId + '/mailgun/usage?period=' + period).then(res => {
      if (this._domain !== dom || dom.mgUsagePeriod !== period) return;
      dom.mgUsageLoading = false;
      if (!res || res.code) { dom.mgUsageErr = (res && res.message) || 'Could not load usage.'; dom.mgUsage = null; }
      else dom.mgUsage = res;
      this.setState({});
    }).catch(() => { if (this._domain === dom) { dom.mgUsageLoading = false; dom.mgUsageErr = 'Could not load usage.'; this.setState({}); } });
  },

  // ── Mailgun suppressions (v1 parity: core.php View Suppressions) ──
  loadMailgunSuppressions(type) {
    const dom = this._domain;
    if (!dom) return;
    dom.suppType = type; dom.suppLoading = true; dom.suppErr = ''; dom.suppItems = null;
    this.setState({});
    this.api('/domain/' + dom.domainId + '/mailgun/suppressions/' + type).then(res => {
      if (this._domain !== dom || dom.suppType !== type) return;
      dom.suppLoading = false;
      if (!res || res.code) { dom.suppErr = (res && res.message) || 'Could not load suppressions.'; this.setState({}); return; }
      // Bounces/unsubscribes/complaints stamp created_at; the allowlist uses createdAt.
      dom.suppItems = (res.items || []).slice().sort((a, b) =>
        new Date(b.created_at || b.createdAt) - new Date(a.created_at || a.createdAt));
      this.setState({});
    }).catch(() => { if (this._domain === dom) { dom.suppLoading = false; dom.suppErr = 'Could not load suppressions.'; this.setState({}); } });
  },

  async deleteMailgunSuppression(item) {
    const dom = this._domain;
    if (!dom) return;
    const type = dom.suppType || 'bounces';
    const identifier = type === 'whitelists' ? item.value : item.address;
    if (!identifier || !(await this.uiConfirm('Remove ' + identifier + ' from the ' + (type === 'whitelists' ? 'allowlist' : type) + '?'))) return;
    dom.suppLoading = true;
    this.setState({});
    this.api('/domain/' + dom.domainId + '/mailgun/suppressions/' + type + '?address=' + encodeURIComponent(identifier), { method: 'DELETE' })
      .then(() => { if (this._domain !== dom) return;
        if (this.toast) this.toast(identifier + ' removed.', { kind: 'success' });
        dom.suppLoading = false;
        this.loadMailgunSuppressions(type); })
      .catch(() => { if (this._domain === dom) { dom.suppLoading = false; this.setState({});
        if (this.toast) this.toast('Could not remove ' + identifier + '.', { kind: 'error' }); } });
  },

  // Deploy the zone's SMTP credentials to a connected site via Gravity SMTP.
  // The v1 endpoint only reads site_id / environment / from_name.
  deployMailgunReal() {
    const dom = this._domain;
    const site = this.state.mgDepTarget;
    const from = (this.state.mgDepFrom || '').trim();
    if (!dom || !site || this.state.mgDepBusy) return;
    if (!from) { if (this.toast) this.toast('The send-from name cannot be empty.', { kind: 'error' }); return; }
    this.setState({ mgDepBusy: true });
    this.api('/domain/' + dom.domainId + '/mailgun/deploy', { method: 'POST',
      body: { site_id: site.id, environment: site.environment, from_name: from } }).then(res => {
      if (res && res.code) { this.setState({ mgDepBusy: false });
        if (this.toast) this.toast(res.message || 'Mailgun deploy failed.', { kind: 'error' }); return; }
      this.setState({ mgDepBusy: false, mgDeployOpen: false });
      if (this.toast) this.toast('Gravity SMTP deployed to ' + site.name + ' (' + site.environment + ').', { kind: 'success' });
    }).catch(() => { this.setState({ mgDepBusy: false });
      if (this.toast) this.toast('Mailgun deploy failed.', { kind: 'error' }); });
  },

  // Sync a domain's full account list (v1's updateDomainAccount):
  // PUT /domains/{id}/account { account_ids, provider_id }. assign_accounts()
  // diffs against the pivot table, so the FULL desired list goes up each
  // time. provider_id must ride along with its current value — the endpoint
  // writes it unconditionally and omitting it would clear the registrar link.
  saveDomainAccounts(ids, label) {
    const dom = this._domain;
    if (!dom) return;
    const tid = this.toast('Saving account assignment…', { kind: 'loading' });
    this.api('/domains/' + dom.domainId + '/account', { method: 'PUT',
      body: { account_ids: ids, provider_id: this.domainProviderId(dom) } }).then(res => {
      if (res && (res.errors || res.code)) { this.updateToast(tid, 'Could not save the assignment', { kind: 'error' }); return; }
      this.updateToast(tid, label, { kind: 'success' });
      this.setState({ dmaOpen: false });
      this.loadDomainDetail(dom.domainId);
    }).catch(() => this.updateToast(tid, 'Could not save the assignment', { kind: 'error' }));
  },

  // The domain's current registrar provider id: the loaded detail
  // (GET /domain/{id} returns provider_id) first, else the list row.
  domainProviderId(dom) {
    if (dom.info && dom.info.provider_id != null && dom.info.provider_id !== '') return String(dom.info.provider_id);
    const row = (this.state.domList || this.DOMAINS).find(x => x.id === String(dom.domainId)) || {};
    return row.providerId ? String(row.providerId) : null;
  },

  // Registrar providers for the Edit registrar picker (legacy Edit Domain's
  // Provider autocomplete, which listed hoverdotcom + spaceship connections).
  // GET /providers is operator-only; fetched once per session.
  REGISTRAR_KINDS: ['hoverdotcom', 'spaceship'],
  loadRegistrarProviders() {
    if (this._regProviders !== undefined) return;
    this._regProviders = null; // loading
    this.api('/providers').then(list => {
      this._regProviders = (Array.isArray(list) ? list : []).filter(p => this.REGISTRAR_KINDS.includes(p.provider));
      this.setState({});
    }).catch(() => { this._regProviders = []; this.setState({}); });
  },

  // Set (or clear) where the domain is registered. Same endpoint as the
  // account assignment — it writes both fields unconditionally, so the
  // current account list rides along unchanged. providerId null = external.
  saveDomainRegistrar(providerId, label) {
    const dom = this._domain;
    if (!dom) return;
    const ids = ((dom.info && dom.info.accounts) || []).filter(a => a && a.account_id).map(a => String(a.account_id));
    const tid = this.toast('Saving registrar…', { kind: 'loading' });
    this.api('/domains/' + dom.domainId + '/account', { method: 'PUT',
      body: { account_ids: ids, provider_id: providerId } }).then(res => {
      if (res && (res.errors || res.code)) { this.updateToast(tid, 'Could not save the registrar', { kind: 'error' }); return; }
      this.updateToast(tid, label, { kind: 'success' });
      // Keep the Domains list column in step (same mapping as data.js hydrate).
      const prov = providerId ? (this._regProviders || []).find(p => String(p.provider_id) === String(providerId)) : null;
      const isOp = ((window.CC_BOOT && window.CC_BOOT.dcRole) || 'operator') === 'operator';
      const brand = (window.CC_BOOT && window.CC_BOOT.name) || 'Anchor Hosting';
      const registrar = providerId ? (isOp && prov ? prov.name : brand) : 'External';
      const patch = r => String(r.id) === String(dom.domainId) ? { ...r, providerId: providerId || '', registrar } : r;
      this.DOMAINS = (this.DOMAINS || []).map(patch);
      this.setState(st => ({ drgOpen: false, domList: st.domList ? st.domList.map(patch) : st.domList }));
      this.loadDomainDetail(dom.domainId);
    }).catch(() => this.updateToast(tid, 'Could not save the registrar', { kind: 'error' }));
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
      startEdit: () => this.setState({ dnsEdit: r.uid, dnsEN: r.name, dnsEV: r.value, dnsETtl: r.ttl, dnsESubs: this.dnsSubsFor(r) }),
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
    // Mailgun splits what it needs into sending records (SPF/DKIM/CNAME) and
    // receiving records (the two MX rows). Both have to land in DNS before the
    // zone verifies, so show them in one table — MX carries a priority, which
    // rides in the value column the way a zone file writes it.
    const mgRec = (r, withPriority) => ({
      type: String(r.record_type || '').toUpperCase(), host: r.name || '',
      value: (withPriority && r.priority != null && r.priority !== '' ? r.priority + ' ' : '') + (r.value || ''),
      stLabel: r.valid === 'valid' ? 'Verified' : 'Pending', stFg: r.valid === 'valid' ? 'var(--ok)' : 'var(--warn)',
      pending: r.valid !== 'valid',
      verify: () => { this.api('/domain/' + dom.domainId + '/mailgun/verify', { method: 'POST', body: {} })
        .then(() => { dom.mgLoading = false; dom.mailgun = null; this.loadMailgun(); }).catch(() => {}); } });
    const mgRecs = ((dom.mailgun && dom.mailgun.sending_dns_records) || []).map(r => mgRec(r, false))
      .concat(((dom.mailgun && dom.mailgun.receiving_dns_records) || []).map(r => mgRec(r, true)));
    const usage = dom.mgUsage;
    const usageSeries = (usage && usage.series) || [];
    const usagePeak = usageSeries.reduce((m, b) => Math.max(m, b.sent), 0) || 1;
    const usageTotals = (usage && usage.totals) || {};
    const num = n => (n == null ? '—' : Number(n).toLocaleString());
    const mgUsagePeriods = [['day', 'Daily'], ['month', 'Monthly'], ['year', 'Yearly']].map(([id, label]) => ({ label,
      fg: dom.mgUsagePeriod === id ? 'var(--ink)' : 'var(--ink-dim)',
      bg: dom.mgUsagePeriod === id ? 'var(--panel-2)' : 'transparent',
      go: () => this.loadMailgunUsage(id) }));
    const mgUsageStats = [
      { label: 'Sent', v: num(usageTotals.sent), fg: 'var(--ink)' },
      { label: 'Delivered', v: num(usageTotals.delivered), fg: 'var(--ok)' },
      { label: 'Failed', v: num(usageTotals.failed), fg: 'var(--bad)' },
      { label: 'Delivery rate', v: usageTotals.delivery_rate == null ? '—' : usageTotals.delivery_rate + '%', fg: 'var(--ink)' }
    ];
    // Every Nth label only — a label is wider than one of 30 daily bar slots, so the
    // rest are blanked and the kept ones are allowed to overflow into their neighbors.
    const usageSkip = Math.ceil(usageSeries.length / 6) || 1;
    const mgUsageBars = usageSeries.map((b, i) => ({
      h: Math.max(2, Math.round(b.sent / usagePeak * 100)) + '%',
      tip: b.label + ' · ' + b.sent + ' sent, ' + b.delivered + ' delivered, ' + b.failed + ' failed',
      label: i % usageSkip === 0 ? b.label : '',
      // Hover tooltip, same pattern as the site Stats chart (stats.js).
      bucket: b.label, sent: String(b.sent), delivered: String(b.delivered), failed: String(b.failed),
      // Snap to the column, not the cursor — shared helper with the Stats chart.
      enter: (e) => this.setState(this.barAnchor(e, 'mgHover', i)) }));
    const mgHi = s.mgHoverIdx;
    const mgHovered = (mgHi != null && mgHi >= 0 && mgHi < mgUsageBars.length) ? mgUsageBars[mgHi] : null;
    const suppType = dom.suppType || 'bounces';
    const suppRows = (dom.suppItems || []).map(item => ({
      addr: suppType === 'whitelists' ? (item.value || '') : (item.address || ''),
      detail: suppType === 'bounces' ? [item.code, item.error].filter(Boolean).join(' · ')
        : suppType === 'unsubscribes' ? (Array.isArray(item.tags) ? item.tags.filter(t => t && t !== '*').join(', ') : '')
        : suppType === 'whitelists' ? (item.reason || '') : '',
      date: (dt => isNaN(dt) ? '' : dt.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }))(new Date(item.created_at || item.createdAt)),
      del: () => this.deleteMailgunSuppression(item) }));
    const suppNote = dom.suppLoading ? 'Loading suppressions…'
      : dom.suppErr ? dom.suppErr
      : (dom.suppItems && !dom.suppItems.length ? 'No entries on the ' + (suppType === 'whitelists' ? 'allowlist' : suppType) + ' list.' : '');
    const depQ = (s.mgDepQ || '').toLowerCase();
    const depRows = (info.connected_sites || [])
      .filter(cs => !depQ || (cs.name + ' ' + cs.environment).toLowerCase().includes(depQ))
      .map(cs => ({ name: cs.name, env: cs.environment, pick: () => this.setState({ mgDepTarget: cs }) }));
    const mgEvents = (dom.mgEvents || []).map(ev => ({
      t: ev.timestamp ? new Date(ev.timestamp * 1000).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '',
      text: (ev.event || '') + (ev.message && ev.message.headers && ev.message.headers.subject ? ' · ' + ev.message.headers.subject : '')
        + (ev.recipient ? ' → ' + ev.recipient : '') }));
    const accs = (info.accounts || []).filter(a => a && (a.account_id || a.name));
    // Operator-only account assignment (legacy Edit Domain parity): the ✕ on
    // a chip unassigns, the Assign… link opens a searchable account picker.
    const isOpDom = ((window.CC_BOOT && window.CC_BOOT.dcRole) || 'operator') === 'operator';
    const accIds = accs.map(a => String(a.account_id));
    const dmaQ = (s.dmaQ || '').toLowerCase();
    const dmaRows = (s.dmaOpen ? this.ACCOUNTS : [])
      .filter(a => !accIds.includes(String(a.id)))
      .filter(a => !dmaQ || (a.name || '').toLowerCase().includes(dmaQ))
      .slice(0, 100)
      .map(a => ({
        label: a.name,
        sub: [a.sites + ' site' + (a.sites === 1 ? '' : 's'), a.plan].filter(Boolean).join(' · '),
        pick: () => this.saveDomainAccounts([...accIds, String(a.id)], 'Assigned ' + a.name)
      }));
    // Registrar row: the connection's name (Hover.com / Spaceship) for
    // operators, the brand for customers, "External" when unlinked. The
    // provider_id link is what says "registered through us"; the remote
    // lookup may still be loading or erroring, so it is only decoration.
    const curProviderId = this.domainProviderId(dom);
    const listRow = (s.domList || this.DOMAINS || []).find(x => String(x.id) === String(dom.domainId)) || {};
    const regProv = curProviderId ? (this._regProviders || []).find(p => String(p.provider_id) === curProviderId) : null;
    const regLabel = !curProviderId ? 'External'
      : (regProv ? regProv.name : (listRow.registrar || 'Connected')) + (provider && provider.status ? ' · ' + provider.status : '');
    const drgRows = !s.drgOpen ? [] : [
      ...(this._regProviders || []).map(p => ({
        label: p.name, sub: p.provider === 'hoverdotcom' ? 'Hover.com connection' : p.provider === 'spaceship' ? 'Spaceship connection' : p.provider,
        current: String(p.provider_id) === curProviderId, action: String(p.provider_id) === curProviderId ? 'Current' : 'Select',
        pick: () => { if (String(p.provider_id) !== curProviderId) this.saveDomainRegistrar(String(p.provider_id), 'Registered through ' + p.name); } })),
      { label: 'Registered externally', sub: 'Managed at another registrar; no Anchor registrar connection',
        current: !curProviderId, action: !curProviderId ? 'Current' : 'Select',
        pick: () => { if (curProviderId) this.saveDomainRegistrar(null, 'Marked as registered externally'); } }
    ];
    return {
      domTabs: lazyTabs,
      domStatus: (dom.noZone ? 'DNS inactive' : 'DNS active') + (curProviderId ? ' · Registered through ' + (regProv ? regProv.name : (listRow.registrar || 'Anchor Hosting')) : ' · Registered externally'),
      domHasAccounts: accs.length > 0 || isOpDom,
      domAccounts: accs.map(a => { const name = this.decodeHtml(a.name);
        return {
          name,
          open: () => this.openAccount(String(a.account_id)),
          canDrop: isOpDom,
          drop: async (e) => { e.stopPropagation();
            if (!(await this.uiConfirm('Remove ' + name + ' from this domain? Its members lose access to the domain.'))) return;
            this.saveDomainAccounts(accIds.filter(id => id !== String(a.account_id)), 'Removed ' + name); }
        }; }),
      domCanAssign: isOpDom,
      openDmaDlg: () => this.setState({ dmaOpen: true, dmaQ: '' }),
      dmaOpen: !!s.dmaOpen,
      closeDma: () => this.setState({ dmaOpen: false }),
      dmaQ: s.dmaQ || '', onDmaQ: e => this.setState({ dmaQ: e.target.value }),
      dmaRows, dmaHasRows: dmaRows.length > 0, dmaEmpty: !!s.dmaOpen && dmaRows.length === 0,
      dnsRows,
      dnsNotice: !!dnsNote, dnsNoticeText: dnsNote,
      // Loading affordance: spinner in the notice bar + shimmer skeleton rows
      // in the (otherwise empty) records card while the zone fetch runs.
      dnsSpin: !!(dom.dnsLoading || dom.saving),
      dnsSkelShow: !!dom.dnsLoading && !(s.dnsRecs || []).length,
      dnsSkelRows: [{}, {}, {}, {}, {}],
      dnsShowActivate: dom.noZone && !dom.dnsLoading,
      activateZone: () => this.activateDnsZone(),
      // Operator zone management — create is the existing per-tab Activate
      // button; this card adds the teardown side for all three zone types.
      ...this.zoneAdminVals(s, dom, d, details, fwdActive),
      dnsEditDone: () => this.setState(st => {
        const subs = st.dnsESubs ? this.dnsCleanSubs(st.dnsESubs) : null;
        return { dnsRecs: st.dnsRecs.map(x => x.uid !== st.dnsEdit ? x
          : (subs && subs.length)
            ? { ...x, name: st.dnsEN.trim() || '@', subs, value: this.dnsSubsToText(x.type, subs), ttl: st.dnsETtl.trim() || '3600', edited: !!x.recId }
            : { ...x, name: st.dnsEN.trim() || '@', ...(st.dnsESubs ? {} : { value: st.dnsEV.trim() || x.value }), ttl: st.dnsETtl.trim() || '3600', edited: !!x.recId }),
          dnsEdit: 0, dnsESubs: null, dnsDirty: true };
      }),
      // Sub-value editor for the row being edited (MX pairs, SRV quads,
      // round-robin value lists). Inputs seed via suid-keyed refs — the DC
      // runtime binds value like defaultValue, and removing a middle row
      // would otherwise leave stale text in reused DOM nodes.
      dnsEIsMulti: !!s.dnsESubs,
      dnsEIsSingle: !s.dnsESubs,
      dnsESubRows: (s.dnsESubs || []).map((sub, i) => {
        const set = (k, v) => this.setState(st => ({ dnsESubs: (st.dnsESubs || []).map((x, j) => j === i ? { ...x, [k]: v } : x) }));
        const seed = val => el => { if (el && el._suid !== sub.suid) { el._suid = sub.suid; el.value = val == null ? '' : val; } };
        return {
          isMx: 'server' in sub, isSrv: 'host' in sub, isVal: 'value' in sub,
          refPriority: seed(sub.priority), onPriority: e => set('priority', e.target.value),
          refWeight: seed(sub.weight), onWeight: e => set('weight', e.target.value),
          refPort: seed(sub.port), onPort: e => set('port', e.target.value),
          refServer: seed(sub.server), onServer: e => set('server', e.target.value),
          refHost: seed(sub.host), onHost: e => set('host', e.target.value),
          refValue: seed(sub.value), onValue: e => set('value', e.target.value),
          canRemove: (s.dnsESubs || []).length > 1,
          remove: () => this.setState(st => ({ dnsESubs: (st.dnsESubs || []).filter((_, j) => j !== i) }))
        };
      }),
      dnsEAddSub: () => this.setState(st => {
        const rec = (st.dnsRecs || []).find(x => x.uid === st.dnsEdit) || {};
        const blank = rec.type === 'MX' ? { priority: '10', server: '' }
          : rec.type === 'SRV' ? { priority: '0', weight: '0', port: '0', host: '' } : { value: '' };
        this._suid = (this._suid || 0) + 1;
        return { dnsESubs: [...(st.dnsESubs || []), { ...blank, suid: 's' + this._suid }] };
      }),
      // Add bar: a value for a name+type that already has a row lands inside
      // that row (Constellix one-record-per-name+type; see dnsGroupRecs).
      addRec: () => { if (!this.state.dnsV.trim()) return;
        this.setState(st => {
          const row = { uid: 'n' + Date.now(), type: st.dnsT, name: st.dnsN.trim() || '@', value: st.dnsV.trim(), ttl: '3600' };
          const grouped = this.dnsGroupRecs([...(st.dnsRecs || []), row], st.dnsDel);
          return { dnsRecs: grouped.recs, dnsDel: grouped.del, dnsDirty: true, dnsN: '', dnsV: '' };
        }); },
      saveDns: () => this.saveDnsReal(),
      discardDns: () => this.loadDnsZone(),
      zoneReplace: () => this.setState(st => ({
        dnsRecs: this.parseZone(st.zoneText).map((r, i) => ({ ...r, uid: 'z' + Date.now() + i })),
        dnsDel: [...(st.dnsDel || []), ...st.dnsRecs.filter(x => x.recId).map(x => x.recId)],
        dnsDirty: true, zoneOpen: false, zoneText: '' })),
      exportZone: () => this.exportZoneReal(d.name),
      // No registrar provider → the domain is registered elsewhere: say
      // "External" and collapse the registrar-only rows (expiry, locks, auth
      // code), the Contacts card, and the nameserver Edit (POST /nameservers
      // needs a connected registrar). The nameserver list stays — it comes
      // from the DNS zone and is informational.
      regRegistrar: regLabel,
      regConnected: !!provider, regExternal: !provider,
      // Operator-only registrar edit (legacy Edit Domain's Provider field):
      // pick a registrar connection or mark the domain registered externally.
      regCanEdit: isOpDom,
      openDrgDlg: () => { this.loadRegistrarProviders(); this.setState({ drgOpen: true }); },
      drgOpen: !!s.drgOpen,
      closeDrg: () => this.setState({ drgOpen: false }),
      drgLoading: !!s.drgOpen && this._regProviders === null,
      drgRows,
      nsCanEdit: !!provider,
      // Seed the edit dialog from the CURRENT nameservers (the design mock
      // seeded sample values into real sessions).
      openNsvDlg: () => this.setState({ nsvOpen: true, nsvText: nsReal.join('\n') }),
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
      // The alias list + add row only make sense once a Mailgun forwarding
      // zone exists — adding a forward before that just 400s.
      fwdActive, fwdInactive: !fwdActive, fwdLoading: dom.fwdLoading,
      fwdNotice: !!dom.fwdErr, fwdNoticeText: dom.fwdErr,
      // ── Mailgun verification panel (v1 parity) ────────────────────────
      // Mailgun reports every record it needs plus a per-record valid flag;
      // until state === 'active' the domain cannot receive mail, so show the
      // exact records with copy buttons rather than a bare "pending" chip.
      ...(() => {
        const st = dom.fwdStatus || null;
        const verified = !!(st && st.state === 'active');
        const rec = (r, withPriority) => ({
          kind: r.record_type + ' record' + (withPriority && r.priority ? ' (Priority ' + r.priority + ')' : ''),
          name: r.name || d.name, value: r.value || '',
          hasName: !withPriority,
          ok: r.valid === 'valid',
          mark: r.valid === 'valid' ? '✓' : '✕',
          markFg: r.valid === 'valid' ? 'var(--ok)' : 'var(--bad)',
          copyName: () => this.ctxCopy(r.name || d.name, 'record name'),
          copyValue: () => this.ctxCopy(r.value || '', 'record value')
        });
        const sending = (st && Array.isArray(st.sending_dns_records) ? st.sending_dns_records : []).map(r => rec(r, false));
        const receiving = (st && Array.isArray(st.receiving_dns_records) ? st.receiving_dns_records : []).map(r => rec(r, true));
        const anyRecords = sending.length > 0 || receiving.length > 0;
        return {
          fwdVerified: verified,
          // Only nag when forwarding is on, Mailgun says not-active, and we
          // actually have records to show.
          fwdShowVerify: fwdActive && !verified && anyRecords,
          fwdVerifying: !!dom.fwdVerifying,
          fwdSendRecs: sending, fwdRecvRecs: receiving,
          fwdHasSend: sending.length > 0, fwdHasRecv: receiving.length > 0,
          fwdVerifyGo: () => this.verifyForwardingDns(),
          // Auto-inject is only meaningful when the zone lives on Anchor DNS.
          fwdCanInject: fwdActive && !verified && anyRecords && !dom.noZone && !dom.dnsLoading,
          fwdInjectGo: () => this.injectForwardingDns()
        };
      })(),
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
      mgActive, mgInactive: !mgActive, mgLoading: dom.mgLoading,
      mgNotice: !!dom.mgErr, mgNoticeText: dom.mgErr,
      // The sending host is a subdomain of this domain, and which one matters:
      // Mailgun names are unique platform-wide, so when mg. is already held
      // elsewhere another label is the way through. The legacy dialog asked for
      // it; this keeps that, defaulted to mg.
      mgSub: s.mgSub === undefined ? 'mg' : s.mgSub,
      mgSubSuffix: '.' + d.name,
      onMgSub: e => this.setState({ mgSub: String(e.target.value || '').toLowerCase().replace(/[^a-z0-9.-]/g, '') }),
      // api() resolves with the WP_Error body on a non-2xx, so a bare catch
      // never saw it and a failed setup looked like nothing happened. Show the
      // server's reason in the notice the panel already renders.
      mgSetup: () => {
        const sub = String(s.mgSub === undefined ? 'mg' : s.mgSub).replace(/^[.-]+|[.-]+$/g, '');
        if (!sub) { dom.mgErr = 'Enter a subdomain to send from.'; this.setState({}); return; }
        dom.mgErr = ''; this.setState({});
        this.api('/domain/' + dom.domainId + '/mailgun/setup', { method: 'POST', body: { domain: sub + '.' + d.name } })
          .then(res => {
            if (res && res.code) { dom.mgErr = res.message || 'Could not set up Mailgun sending.'; this.setState({}); return; }
            this.loadDomainDetail(dom.domainId);
          })
          .catch(() => { dom.mgErr = 'Could not set up Mailgun sending.'; this.setState({}); }); },
      // Before setup there is no zone yet, so the header follows the subdomain
      // being chosen below rather than always claiming mg.
      mgHost: details.mailgun_zone || (String(s.mgSub === undefined ? 'mg' : s.mgSub).replace(/^[.-]+|[.-]+$/g, '') || 'mg') + '.' + d.name,
      mgSupp: dom.mailgun && dom.mailgun.state ? 'state: ' + dom.mailgun.state : '',
      mgRecs, mgEvents,
      mgHasRecs: mgRecs.length > 0,
      // Same plain-text block the legacy UI copied, so it can be pasted
      // straight into a ticket or handed to a customer's DNS provider.
      mgCopyRecs: () => {
        const mg = dom.mailgun || {};
        const lines = [ 'Mailgun DNS Records for ' + d.name + ':', '' ];
        (mg.sending_dns_records || []).forEach(r => {
          lines.push('Type: ' + (r.record_type || ''), 'Name: ' + (r.name || ''), 'Value: ' + (r.value || ''), '');
        });
        (mg.receiving_dns_records || []).forEach(r => {
          lines.push('Type: ' + (r.record_type || ''), 'Name: ' + (r.name || ''), 'Priority: ' + (r.priority ?? ''), 'Value: ' + (r.value || ''), '');
        });
        this.ctxCopy(lines.join('\n'), 'Mailgun DNS records');
      },
      mgUsagePeriods, mgUsageStats, mgUsageBars,
      mgChartLeave: () => this.setState({ mgHoverIdx: -1 }),
      mgTipShow: !!mgHovered,
      mgTipLeft: mgHovered ? (s.mgHoverX || 0) : 0,
      mgTipTop: mgHovered ? (s.mgHoverY || 0) : 0,
      mgTipBucket: mgHovered ? mgHovered.bucket : '',
      mgTipLine: mgHovered ? (mgHovered.sent + ' sent · ' + mgHovered.delivered + ' delivered · ' + mgHovered.failed + ' failed') : '',
      mgUsageHasData: !!usageSeries.length,
      mgUsageRange: usage ? usage.start + ' — ' + usage.end : (dom.mgUsageLoading ? 'Loading usage…' : ''),
      mgUsageNotice: !!dom.mgUsageErr, mgUsageNoticeText: dom.mgUsageErr,
      // Suppressions dialog (v1 parity: core.php View Suppressions)
      mgOpenSupp: () => { this.setState({ mgSuppOpen: true }); this.loadMailgunSuppressions('bounces'); },
      closeMgSupp: () => this.setState({ mgSuppOpen: false }),
      mgSuppOpen: !!s.mgSuppOpen,
      mgSuppTabs: [['bounces', 'Bounces'], ['unsubscribes', 'Unsubscribes'], ['complaints', 'Complaints'], ['whitelists', 'Allowlist']].map(([id, label]) => ({ label,
        fg: suppType === id ? 'var(--ink)' : 'var(--ink-dim)',
        bg: suppType === id ? 'var(--panel-2)' : 'transparent',
        go: () => this.loadMailgunSuppressions(id) })),
      mgSuppRefresh: () => this.loadMailgunSuppressions(suppType),
      mgSuppRows: suppRows, mgSuppHasRows: suppRows.length > 0,
      mgSuppNotice: !!suppNote, mgSuppNoticeText: suppNote,
      // Deploy dialog (v1 parity: core.php Deploy to…)
      mgOpenDeploy: () => this.setState({ mgDeployOpen: true, mgDepQ: '', mgDepTarget: null, mgDepFrom: '' }),
      closeMgDeploy: () => this.setState({ mgDeployOpen: false }),
      mgDeployOpen: !!s.mgDeployOpen,
      mgDepQ: s.mgDepQ || '', onMgDepQ: e => this.setState({ mgDepQ: e.target.value }),
      mgDepRows: depRows, mgDepHasRows: depRows.length > 0, mgDepNone: depRows.length === 0,
      mgDepPicking: !s.mgDepTarget, mgDepPicked: !!s.mgDepTarget,
      mgDepTargetLabel: s.mgDepTarget ? s.mgDepTarget.name + ' (' + s.mgDepTarget.environment + ')' : '',
      mgDepFrom: s.mgDepFrom || '', onMgDepFrom: e => this.setState({ mgDepFrom: e.target.value }),
      mgDepBack: () => this.setState({ mgDepTarget: null }),
      mgDepSubmit: () => this.deployMailgunReal(),
      mgDepSubmitLabel: s.mgDepBusy ? 'Deploying…' : 'Deploy'
    };
  }

});
