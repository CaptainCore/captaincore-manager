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
      tfa: this._prof.tfaEnabled ? 'on' : 'off'
    });
    this.api('/sessions').then(res => {
      const list = (res && res.sessions) || (Array.isArray(res) ? res : []);
      if (Array.isArray(list)) { this._prof.sessions = list; this.setState({}); }
    }).catch(() => {});
    if (!this._prof.appPassword) this.api('/me/application-password').then(res => {
      if (res && (res.created || res.uuid)) { this._prof.appPassword = { created: res.created, uuid: res.uuid }; this.setState({}); }
    }).catch(() => {});
  },

  saveProfileReal() {
    const body = { display_name: (this.state.profName || '').trim(), email: (this.state.profEmail || '').trim() };
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
    this.api('/me/tfa_activate').then(res => {
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
    this.api('/me/tfa_deactivate').then(() => { this._prof.tfaEnabled = false; this.setState({ tfa: 'off' }); }).catch(() => {});
  },

  appPwGenReal() {
    const has = this._prof && this._prof.appPassword;
    const path = has ? '/me/application-password/rotate' : '/me/application-password';
    this.api(path, { method: 'POST', body: {} }).then(res => {
      if (res && res.password) { this._prof.appPassword = { created: res.created }; this.setState({ appPw: res.password }); }
    }).catch(() => {});
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

  realProfileVals(s) {
    if (s.route === 'profile') this.profileInit();
    if (!this._prof) return {};
    return {
      profName: s.profName, onProfName: e => this.setState({ profName: e.target.value }),
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
      appPwShown: !!s.appPw, appPw: s.appPw,
      appPwBtn: (this._prof.appPassword || s.appPw) ? 'Rotate' : 'Generate',
      genAppPw: () => this.appPwGenReal(),
      appPwMark: s.copied === 'apppw' ? 'Copied ✓' : 'Copy',
      copyAppPw: () => { try { navigator.clipboard.writeText(this.state.appPw); } catch (e) {}
        this.setState({ copied: 'apppw' }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
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
