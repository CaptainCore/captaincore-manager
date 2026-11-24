// ── MyKinsta access to a site ───────────────────────────────────────────────
// The MyKinsta row in the Accounts card and its dialog: who has MyKinsta
// access to this site, inviting someone, and asking for someone to be removed.
// The server decides who may (KinstaAccess): /details carries `kinsta_access`,
// so the row only shows where an invite would be allowed. The list is fetched
// when the dialog opens. Kinsta's API cannot take access away, so Request
// removal tells the team.
Object.assign(Component.prototype, {

  KA_ROLES: [
    ['site-admin', 'Site administrator', 'Everything in MyKinsta for this site: live and staging, backups, logs, analytics and pushing staging to live. They cannot delete the site, buy add-ons or see billing.'],
    ['site-developer', 'Site developer', 'Staging environments only. They cannot open the live environment or push to live.']
  ],

  kaRoleLabel(role) {
    const r = this.KA_ROLES.find(x => x[0] === role);
    return r ? r[1] : role;
  },

  openKinstaAccess() {
    const real = this._detail;
    if (!real) return;
    this._ka = null;
    this.setState({ kaOpen: true, kaSite: real.siteId, kaEmail: '', kaRole: 'site-admin', kaErr: '', kaNote: '', kaBusy: false });
    this.loadKinstaAccess(real.siteId);
  },

  loadKinstaAccess(siteId) {
    this.api('/sites/' + siteId + '/kinsta-access').then(res => {
      if (this.state.kaSite !== siteId) return;
      if (!res || res.code) this._ka = { siteId, error: (res && res.message) || 'Could not load MyKinsta access.' };
      else this._ka = { siteId, data: res };
      this.setState({});
    }).catch(err => {
      if (this.state.kaSite !== siteId) return;
      this._ka = { siteId, error: err && err.message === 'auth' ? 'Your access level does not allow managing MyKinsta access.' : 'Could not load MyKinsta access.' };
      this.setState({});
    });
  },

  sendKinstaInvite() {
    const siteId = this.state.kaSite;
    if (!siteId || this.state.kaBusy) return;
    const email = (this.state.kaEmail || '').trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { this.setState({ kaErr: 'Enter a valid email address.' }); return; }
    this.setState({ kaBusy: true, kaErr: '', kaNote: '' });
    const here = () => this.state.kaSite === siteId && this.state.kaOpen;
    this.api('/sites/' + siteId + '/kinsta-access', { method: 'POST', body: { email, role: this.state.kaRole } }).then(res => {
      if (!res || res.code) {
        const msg = (res && res.message) || 'The invite could not be sent.';
        if (here()) this.setState({ kaBusy: false, kaErr: msg }); else this.toast(msg, { kind: 'error' });
        return;
      }
      if (!here()) { this.toast(res.message, { kind: 'success' }); return; }
      if (Array.isArray(res.members)) this._ka = { siteId, data: Object.assign({}, (this._ka && this._ka.data) || {}, res) };
      this.setState({ kaBusy: false, kaEmail: res.status === 'sent' ? '' : this.state.kaEmail, kaNote: res.message });
    }).catch(err => {
      const msg = err && err.message === 'auth' ? 'Your access level does not allow sending invites.' : 'The invite could not be sent.';
      if (here()) this.setState({ kaBusy: false, kaErr: msg }); else this.toast(msg, { kind: 'error' });
    });
  },

  async requestKinstaRemoval(email) {
    const siteId = this.state.kaSite;
    if (!(await this.uiConfirm('Ask us to remove ' + email + ' from this site in MyKinsta?'))) return;
    this.api('/sites/' + siteId + '/kinsta-access/remove', { method: 'POST', body: { email } }).then(res => {
      if (!res || res.code) { this.toast((res && res.message) || 'The request could not be sent.', { kind: 'error' }); return; }
      this._kaAsked = Object.assign({}, this._kaAsked, { [siteId + '|' + email.toLowerCase()]: 1 });
      this.setState({});
      this.toast(res.message, { kind: 'success' });
    }).catch(() => this.toast('The request could not be sent.', { kind: 'error' }));
  },

  kinstaAccessVals(real, s) {
    const role = s.kaRole || 'site-admin';
    const email = (s.kaEmail || '').trim();
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    const ka = this._ka && this._ka.siteId === s.kaSite ? this._ka : null;
    const d = ka && ka.data;
    const row = (p, pending) => {
      const asked = !!(this._kaAsked && this._kaAsked[s.kaSite + '|' + String(p.email).toLowerCase()]);
      return { name: p.name || p.email, email: p.name ? p.email : '', hasEmail: !!p.name, role: this.kaRoleLabel(p.role),
        pending, asked, canAsk: !asked, ask: () => this.requestKinstaRemoval(p.email) };
    };
    const rows = d ? [...(d.pending || []).map(p => row(p, true)), ...(d.members || []).map(p => row(p, false))] : [];
    const also = (d && d.also_covers) || [];
    const roleInfo = this.KA_ROLES.find(x => x[0] === role) || this.KA_ROLES[0];
    const name = (real && real.site && real.site.name) || '';
    return {
      kaShow: !!(real && real.kinstaAccess),
      openKa: () => this.openKinstaAccess(),
      kaOpen: !!s.kaOpen && !!real && s.kaSite === real.siteId,
      closeKa: () => this.setState({ kaOpen: false }),
      kaTitle: 'Invite someone to ' + name + ' in MyKinsta, Kinsta’s own dashboard. They get this site only, and the invite helps them create a MyKinsta account if they need one.',
      kaEmail: s.kaEmail || '', onKaEmail: e => this.setState({ kaEmail: e.target.value, kaErr: '', kaNote: '' }),
      kaKey: e => { if (e.key === 'Enter') this.sendKinstaInvite(); },
      kaRoles: this.KA_ROLES.map(([id, label]) => ({ label,
        fg: role === id ? 'var(--ink)' : 'var(--ink-dim)',
        bg: role === id ? 'var(--paper)' : 'transparent',
        shadow: role === id ? '0 1px 2px rgb(0 0 0/.08)' : 'none',
        go: () => this.setState({ kaRole: id }) })),
      kaRoleText: roleInfo[2],
      kaAlso: also.length ? 'This Kinsta site also holds ' + also.slice(0, 6).join(', ') + (also.length > 6 ? ' and ' + (also.length - 6) + ' more' : '') + '. The invite gives access to all of them.' : '',
      kaAlsoShow: also.length > 0,
      kaLoading: !ka, kaRows: rows, kaEmpty: !!d && !rows.length,
      kaLoadErr: (ka && ka.error) || '', kaHasLoadErr: !!(ka && ka.error),
      kaErr: s.kaErr || '', kaHasErr: !!s.kaErr, kaNote: s.kaNote || '', kaHasNote: !!s.kaNote,
      kaSendLabel: s.kaBusy ? 'Sending…' : 'Send invite',
      kaSendBg: valid && !s.kaBusy ? 'var(--brand)' : 'var(--rule)',
      kaSend: () => this.sendKinstaInvite()
    };
  }
});
