// CaptainCore v3 — Profile real-data layer (mixin). Self-service, any user.
// Initial state (name/email/tfa/app-password/sessions) is server-rendered into
// CC_BOOT via User::profile() — same source as v1 — so no fetch on load.
//   PUT  /me/profile { display_name, email, new_password? }
//   GET  /me/tfa_activate → otpauth:// URI (secret stored server-side)
//   POST /me/tfa_validate { token } → bool (enables)
//   GET  /me/tfa_deactivate
//   POST /me/application-password → { password, created }  (plaintext once)
//   POST /me/application-password/rotate → { password, created }
//   DELETE /me/application-password
//   GET  /sessions → { sessions:[{hash,ua_browser,ua_os,country_name,region,
//        asn_org,is_local,is_current,login_at}] }
//   DELETE /sessions { hash } | { all_others:1 }

Object.assign(Component.prototype, {

  profileInit() {
    if (this._profInit) return;
    this._profInit = true;
    const boot = window.CC_BOOT || {};
    // tfa_enabled is server-rendered; sessions + app-password are fetched (they
    // aren't always present on User::profile()).
    this._prof = {
      tfaEnabled: !!boot.tfaEnabled,
      appPassword: boot.appPassword || null, // { created, uuid } or null
      sessions: Array.isArray(boot.sessions) ? boot.sessions : []
    };
    this.setState({
      profName: boot.userDisplayName || this.state.profName,
      profEmail: boot.userEmail || this.state.profEmail,
      profFirst: boot.profFirst || '',
      profLast: boot.profLast || '',
      tfa: this._prof.tfaEnabled ? 'on' : 'off'
    });
    this.api('/sessions').then(res => {
      const list = (res && res.sessions) || (Array.isArray(res) ? res : []);
      if (Array.isArray(list)) { this._prof.sessions = list; this.setState({}); }
    }).catch(() => {});
    if (!this._appPws) this.api('/me/application-passwords').then(res => {
      this._appPws = Array.isArray(res) ? res : [];
      this.setState({});
    }).catch(() => { this._appPws = []; this.setState({}); });
  },

  saveProfileReal() {
    const body = { display_name: (this.state.profName || '').trim(), email: (this.state.profEmail || '').trim(),
      first_name: (this.state.profFirst || '').trim(), last_name: (this.state.profLast || '').trim() };
    if ((this.state.profPw || '').trim()) body.new_password = this.state.profPw.trim();
    this.setState({ copied: 'prof' });
    clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1600);
    this.api('/me/profile', { method: 'PUT', body }).then(res => {
      if (res && res.errors) this.setState({ profMsg: (Array.isArray(res.errors) ? res.errors.join(' ') : String(res.errors)) });
      else this.setState({ profPw: '', profMsg: '' });
    }).catch(() => {});
  },

  tfaStartReal() {
    this.setState({ tfa: 'setup', tfaCode: '', tfaSecret: '…' });
    this.api('/me/tfa_activate', { method: 'POST' }).then(res => {
      const uri = typeof res === 'string' ? res : (res && (res.uri || res.otpauth || res.message)) || '';
      let secret = '';
      const m = /[?&]secret=([^&]+)/i.exec(uri);
      if (m) secret = decodeURIComponent(m[1]);
      else { const parts = String(uri).split('='); secret = parts[parts.length - 1]; }
      this.setState({ tfaSecret: secret || uri });
    }).catch(() => this.setState({ tfaSecret: 'Could not start setup.' }));
  },

  tfaActivateReal() {
    const token = (this.state.tfaCode || '').trim();
    if (token.length !== 6) return;
    this.api('/me/tfa_validate', { method: 'POST', body: { token } }).then(res => {
      const ok = res === true || (res && (res.success || res.valid));
      if (ok) { this._prof.tfaEnabled = true; this.setState({ tfa: 'on', tfaCode: '' }); }
      else this.setState({ profMsg: 'Invalid code — try again.' });
    }).catch(() => {});
  },

  tfaDisableReal() {
    this.api('/me/tfa_deactivate', { method: 'POST' }).then(() => { this._prof.tfaEnabled = false; this.setState({ tfa: 'off' }); }).catch(() => {});
  },

  // ── Application passwords (managed list, Minn Admin parity) ──
  // GET/POST /me/application-passwords + DELETE …/{uuid}. The plaintext
  // password exists only in the create response — the reveal row shows it
  // once and it never comes back.
  apCreateReal() {
    const name = (this.state.apName || '').trim();
    this.api('/me/application-passwords', { method: 'POST', body: { name } }).then(res => {
      if (res && res.error) { this.toast(res.error, { kind: 'error' }); return; }
      if (!res || !res.password) { this.toast('Could not create the password', { kind: 'error' }); return; }
      this._appPws = [...(this._appPws || []), { uuid: res.uuid, name: res.name, created: res.created, last_used: null }];
      this.setState({ appPw: res.password, appPwName: res.name, apName: '' });
      if (this._apNameEl) this._apNameEl.value = '';
    }).catch(() => this.toast('Could not create the password', { kind: 'error' }));
  },

  apRevokeReal(p) {
    if (!confirm('Revoke "' + p.name + '"? Anything authenticating with it stops working immediately.')) return;
    this.api('/me/application-passwords/' + p.uuid, { method: 'DELETE' }).then(res => {
      if (res && res.error) { this.toast(res.error, { kind: 'error' }); return; }
      this._appPws = (this._appPws || []).filter(x => x.uuid !== p.uuid);
      this.setState({});
      this.toast('Application password revoked', { kind: 'success' });
    }).catch(() => this.toast('Could not revoke the password', { kind: 'error' }));
  },

  // "Dec 16, 2025" for other years, "Feb 19" for this one (Minn's format).
  fmtApDate(ts) {
    const n = parseInt(ts, 10);
    if (!n) return '';
    const d = new Date(n * 1000);
    const opts = { month: 'short', day: 'numeric' };
    if (d.getFullYear() !== new Date().getFullYear()) opts.year = 'numeric';
    return d.toLocaleDateString([], opts);
  },

  fmtLoginAt(v) {
    if (v == null || v === '') return '';
    const n = typeof v === 'number' ? v : (/^\d+$/.test(String(v)) ? parseInt(v, 10) : null);
    if (n) return new Date(n * 1000).toLocaleDateString([], { month: 'short', day: 'numeric' });
    return String(v).slice(0, 16);
  },

  sessionRows() {
    const list = (this._prof.sessions || []).slice().sort((a, b) => (b.is_current ? 1 : 0) - (a.is_current ? 1 : 0));
    return list.map(se => {
      const where = se.is_local ? 'Local / private network'
        : [se.country_name || se.country, se.region].filter(Boolean).join(' · ') || se.ip || 'Unknown';
      return {
        id: se.hash, where,
        ua: [se.ua_os, se.ua_browser].filter(Boolean).join(' · ') || 'Unknown device',
        last: se.is_current ? 'this device' : this.fmtLoginAt(se.login_at),
        current: !!se.is_current, killable: !se.is_current,
        kill: () => this.api('/sessions', { method: 'DELETE', body: { hash: se.hash } })
          .then(r => { if (r && r.sessions) { this._prof.sessions = r.sessions; this.setState({}); } }).catch(() => {})
      };
    });
  },

  // ── API documentation (legacy parity: GET /me/api-docs) ──────
  // ?format=html → { html } (server-side Parsedown, {your-site} already
  // substituted); no format → raw markdown with attachment headers. The HTML
  // is our own file rendered server-side — same trust as v1's v-html.
  viewApiDocs() {
    this.setState({ adOpen: true, adLoading: !this._adHtml });
    if (this._adHtml) return;
    this.api('/me/api-docs?format=html').then(res => {
      // Stamp ids on h2/h3 for the TOC (v1's exact slug rules, duplicates
      // suffixed) before the HTML is frozen into a string.
      const doc = new DOMParser().parseFromString((res && res.html) || '', 'text/html');
      const toc = []; const used = {};
      doc.querySelectorAll('h2, h3').forEach(h => {
        let id = h.textContent.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
        if (used[id]) { used[id]++; id += '-' + used[id]; } else { used[id] = 1; }
        h.setAttribute('id', id);
        toc.push({ id, text: h.textContent.trim(), level: parseInt(h.tagName[1], 10) });
      });
      this._adHtml = doc.body.innerHTML;
      this._adToc = toc;
      this.setState({ adLoading: false });
    }).catch(() => {
      this.setState({ adOpen: false, adLoading: false });
      this.toast('Failed to load API documentation', { kind: 'error' });
    });
  },

  downloadApiDocs() {
    const boot = window.CC_BOOT || {};
    fetch(boot.restRoot + 'captaincore/v1/me/api-docs', { headers: { 'X-WP-Nonce': boot.nonce } })
      .then(r => { if (!r.ok) throw 0; return r.blob(); })
      .then(b => {
        const url = URL.createObjectURL(b);
        const a = document.createElement('a');
        a.href = url; a.download = 'captaincore-api-docs.md'; a.click();
        URL.revokeObjectURL(url);
      })
      .catch(() => this.toast('Failed to download API documentation', { kind: 'error' }));
  },

  // ── Interface preference (legacy dashboard) ──────────────────
  // POST /me/legacy-ui sets per-user meta the Router reads: on the next page
  // load this user gets core-legacy.php. Turning it ON therefore reloads into
  // the old interface right away; the legacy Profile carries the mirror
  // toggle (plus ?ui=v3 as the escape hatch).
  legacyUiOn(s) {
    return s.legacyUi === undefined ? !!(window.CC_BOOT || {}).legacyUi : !!s.legacyUi;
  },

  setLegacyUi() {
    const next = !this.legacyUiOn(this.state);
    this.setState({ legacyUi: next });
    this.api('/me/legacy-ui', { method: 'POST', body: { enabled: next } }).then(() => {
      if (next) { window.location = (window.CC_BOOT || {}).path || '/account/'; }
      else this.toast('Legacy dashboard turned off', { kind: 'success' });
    }).catch(() => {
      this.setState({ legacyUi: !next });
      this.toast('Could not save the preference', { kind: 'error' });
    });
  },

  apiDocsVals(s) {
    const legacyOn = this.legacyUiOn(s);
    return {
      legacyUiBg: legacyOn ? 'var(--brand)' : 'var(--rule)',
      legacyUiJust: legacyOn ? 'flex-end' : 'flex-start',
      toggleLegacyUi: () => this.setLegacyUi(),
      apiDocsView: () => this.viewApiDocs(),
      apiDocsDownload: () => this.downloadApiDocs(),
      adOpen: !!s.adOpen, adLoading: !!s.adLoading, adReady: !!s.adOpen && !s.adLoading,
      adClose: () => this.setState({ adOpen: false }),
      // Content scroll container + raw-HTML escape hatch (the DC runtime has
      // no innerHTML binding — the timeline .cc-md ref pattern).
      adScrollRef: el => { this._adScrollEl = el; },
      adBodyRef: el => { if (el && el._html !== this._adHtml) { el._html = this._adHtml; el.innerHTML = this._adHtml || ''; } },
      adTocRows: (this._adToc || []).map(t => ({
        text: t.text,
        pad: t.level === 2 ? '6px 16px' : '4px 16px 4px 30px',
        weight: t.level === 2 ? '600' : '400',
        size: t.level === 2 ? '13px' : '12.5px',
        go: () => {
          const c = this._adScrollEl;
          const el = c && c.querySelector('#' + (window.CSS && CSS.escape ? CSS.escape(t.id) : t.id));
          if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }))
    };
  },

  realProfileVals(s) {
    if (s.route === 'profile') this.profileInit();
    if (!this._prof) return { ...this.apiDocsVals(s) };
    return {
      ...this.apiDocsVals(s),
      profName: s.profName, onProfName: e => this.setState({ profName: e.target.value }),
      profFirst: s.profFirst || '', onProfFirst: e => this.setState({ profFirst: e.target.value }),
      profLast: s.profLast || '', onProfLast: e => this.setState({ profLast: e.target.value }),
      profEmail: s.profEmail, onProfEmail: e => this.setState({ profEmail: e.target.value }),
      profPw: s.profPw || '', onProfPw: e => this.setState({ profPw: e.target.value }),
      profMsg: s.profMsg || '', profHasMsg: !!(s.profMsg || ''),
      profSaveLabel: s.copied === 'prof' ? 'Saved ✓' : 'Save profile',
      saveProfile: () => this.saveProfileReal(),
      tfaOff: s.tfa === 'off', tfaSetup: s.tfa === 'setup', tfaOn: s.tfa === 'on',
      tfaLabel: s.tfa === 'on' ? 'On' : 'Off',
      tfaBg: s.tfa === 'on' ? 'var(--ok-soft)' : 'var(--panel-2)',
      tfaSecret: s.tfaSecret || '', tfaHasSecret: !!(s.tfaSecret || ''),
      tfaStart: () => this.tfaStartReal(),
      tfaCode: s.tfaCode, onTfaCode: e => this.setState({ tfaCode: e.target.value }),
      tfaActivate: () => this.tfaActivateReal(),
      tfaDisable: () => this.tfaDisableReal(),
      appPwShown: !!s.appPw, appPw: s.appPw, appPwName: s.appPwName || '',
      appPwMark: s.copied === 'apppw' ? 'Copied ✓' : 'Copy',
      copyAppPw: () => { try { navigator.clipboard.writeText(this.state.appPw); } catch (e) {}
        this.setState({ copied: 'apppw' }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
      apRows: (this._appPws || []).map(p => ({
        name: p.name,
        meta: 'created ' + this.fmtApDate(p.created) + ' · ' + (p.last_used ? 'last used ' + this.fmtApDate(p.last_used) : 'never used'),
        revoke: () => this.apRevokeReal(p) })),
      apLoading: !Array.isArray(this._appPws),
      apEmpty: Array.isArray(this._appPws) && !this._appPws.length,
      apName: s.apName || '', onApName: e => this.setState({ apName: e.target.value }),
      apNameRef: el => { this._apNameEl = el; },
      apCreate: () => this.apCreateReal(),
      ...(function (self) {
        const all = self.sessionRows();
        const CAP = 5;
        return {
          sessRows: all.slice(0, CAP),
          sessAll: all,
          sessMoreShow: all.length > CAP,
          sessMoreLabel: 'View all ' + all.length + ' sessions',
          sessModalOpen: !!s.sessModalOpen,
          openSessModal: () => self.setState({ sessModalOpen: true }),
          closeSessModal: () => self.setState({ sessModalOpen: false })
        };
      })(this),
      killOthers: () => this.api('/sessions', { method: 'DELETE', body: { all_others: 1 } })
        .then(r => { if (r && r.sessions) { this._prof.sessions = r.sessions; this.setState({}); } }).catch(() => {})
    };
  }

});
