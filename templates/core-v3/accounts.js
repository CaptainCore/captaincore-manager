// CaptainCore v3 — Accounts / Users / Access real-data layer (mixin).
// openAccount() override loads GET /accounts/{id} — a tier-gated bundle:
//   { account:{name, plan:{name,limits,usage,interval,next_renewal,auto_pay,
//     addons,credits,billing_user_id}, metrics}, level, owner,
//     users:[{user_id,name,email,level}], invites:[{invite_id,email,token,
//     level,created_at}], sites:[{site_id,name,visits,storage}],
//     domains:[{domain_id,name}], usage_breakdown, invoices }
// Levels: full-billing=Owner (ownership = plan.billing_user_id, not the pivot),
// full, sites-only, domains-only. Invites: POST/DELETE /accounts/{id}/invites;
// remove user: DELETE /accounts/{id}/users/{user_id} (server refuses the owner).
// Trusted devices have NO REST surface (usermeta only) — section hidden when
// real. Activity tab lazy-loads /activity-logs?account_id={id}.

Object.assign(Component.prototype, {

  ACC_LEVEL_LABELS: { 'full-billing': 'Owner', 'full': 'Full access', 'sites-only': 'Sites only', 'domains-only': 'Domains only' },
  ACC_LEVEL_API: { 'Full access': 'full', 'Sites only': 'sites-only', 'Domains only': 'domains-only' },

  openAccount(id) {
    this.setState({ route: 'account', accountId: id, accTab: 'users', paletteOpen: false,
      accInvites: this._hydrated ? [] : [{ uid: 1, e: 'bookkeeper@ledgerly.com', level: 'Domains only', sent: 'Jul 10' }],
      trusted: this._hydrated ? [] : this.TRUSTED.map(t => ({ ...t })), invEmail: '', invLevel: 'Full access' });
    if (this._hydrated) this.loadAccountDetail(id);
  },

  // Refetch /accounts/ into this.ACCOUNTS (mirrors data.js hydrate mapping).
  reloadAccounts() {
    return this.api('/accounts/').then(accounts => {
      this.ACCOUNTS = (Array.isArray(accounts) ? accounts : []).map(a => ({ id: String(a.account_id), name: a.name,
        users: (a.metrics && a.metrics.users) || 0, sites: (a.metrics && a.metrics.sites) || 0,
        domains: (a.metrics && a.metrics.domains) || 0, plan: a.plan_name || '', owned: true,
        due: !!(a.metrics && a.metrics.outstanding_invoices > 0) }));
      this.setState({});
    }).catch(() => {});
  },

  createAccountReal() {
    const name = (this.state.naName || '').trim();
    if (!name) { this.setState({ naMsg: 'Enter an account name.' }); return; }
    if (!this._hydrated) { // design fallback
      this.ACCOUNTS = [{ id: 'a' + Date.now(), name, users: 1, sites: 0, domains: 0, plan: '', owned: true, due: false }, ...this.ACCOUNTS];
      this.setState({ naOpen: false, naName: '' });
      return;
    }
    this.setState({ naMsg: 'Creating…' });
    this.api('/accounts/', { method: 'POST', body: { name } }).then(res => {
      if (res && res.code) { this.setState({ naMsg: res.message || 'Create failed.' }); return; }
      this.setState({ naOpen: false, naName: '', naMsg: '' });
      this.reloadAccounts();
    }).catch(() => this.setState({ naMsg: 'Create failed.' }));
  },

  loadAccountDetail(id) {
    const acc = this._account = { accountId: id, data: null, err: '', loading: true, activity: null };
    this.api('/accounts/' + id).then(res => {
      if (this._account !== acc) return;
      acc.loading = false;
      if (!res || res.code) { acc.err = (res && res.message) || 'Could not load account.'; this.setState({}); return; }
      acc.data = res;
      this.setState({ accInvites: (res.invites || []).map(iv => ({
        uid: iv.invite_id, e: iv.email, token: iv.token,
        level: this.ACC_LEVEL_LABELS[iv.level] || iv.level || 'Full access',
        sent: (iv.created_at || '').slice(0, 10) })) });
    }).catch(() => { if (this._account === acc) { acc.loading = false; acc.err = 'Could not load account.'; this.setState({}); } });
  },

  // Transfer ownership: pick a non-owner member → PUT their level to
  // full-billing (server demotes the prior owner). Two-step confirm in the UI.
  transferVals(s, d, reload) {
    const acc = this._account;
    const candidates = (d.users || []).filter(u => (u.level || '') !== 'full-billing');
    const sel = s.transferPick;
    return {
      transferOpen: !!s.transferOpen,
      openTransfer: () => this.setState({ transferOpen: true, transferPick: null }),
      closeTransfer: () => this.setState({ transferOpen: false }),
      transferEmpty: !candidates.length,
      transferBtnBg: sel ? 'var(--brand)' : 'var(--ink-dim)',
      transferCandidates: candidates.map(u => ({
        n: u.name || u.email, e: u.email,
        init: (u.name || u.email).split(/[\s@]/).map(w => w[0]).join('').slice(0, 2).toUpperCase(),
        mark: sel === u.user_id ? '✓ new owner' : '',
        bd: sel === u.user_id ? 'var(--brand)' : 'var(--rule)',
        bg: sel === u.user_id ? 'var(--brand-soft)' : 'var(--paper)',
        pick: () => this.setState({ transferPick: u.user_id }) })),
      confirmTransfer: () => {
        const uid = this.state.transferPick;
        if (!uid || !acc) return;
        const u = candidates.find(x => x.user_id === uid);
        if (!confirm('Make ' + (u ? (u.name || u.email) : 'this user') + ' the billing owner? You will be demoted to Full access.')) return;
        this.setState({ transferOpen: false });
        this.api('/accounts/' + acc.accountId + '/users/' + uid + '/level', { method: 'PUT', body: { level: 'full-billing' } })
          .then(reload).catch(() => {});
      }
    };
  },

  loadAccountActivity() {
    const acc = this._account;
    if (!acc || acc.activity) return;
    acc.activity = [];
    this.api('/activity-logs?per_page=20&account_id=' + acc.accountId).then(res => {
      if (this._account !== acc) return;
      acc.activity = ((res && res.items) || []).map(x => ({
        t: this.relTime(x.created_at),
        text: x.description || [x.action, x.entity_type, x.entity_name].filter(Boolean).join(' ') }));
      this.setState({});
    }).catch(() => {});
  },

  realAccountVals(s) {
    const acc = (this._account && this._account.accountId === s.accountId) ? this._account : null;
    if (!acc) return {};
    const d = acc.data || {};
    const a = d.account || {};
    const plan = a.plan || {};
    const metrics = a.metrics || {};
    const reload = () => { this._account = null; this.loadAccountDetail(acc.accountId); };
    const gb = n => (parseInt(n, 10) || 0) / 1073741824;
    const pct = (used, limit) => limit > 0 ? Math.min(100, Math.round(used / limit * 100)) : 0;
    const usage = plan.usage || {};
    const limits = plan.limits || {};
    const tabs = [['users', 'Users & access'], ['sites', 'Sites'], ['domains', 'Domains'], ['plan', 'Plan'], ['activity', 'Activity']].map(([id, label]) => ({ label,
      fg: s.accTab === id ? 'var(--ink)' : 'var(--ink-dim)',
      bg: s.accTab === id ? 'var(--panel-2)' : 'transparent',
      go: () => { this.setState({ accTab: id }); if (id === 'activity') this.loadAccountActivity(); } }));
    return {
      accName: a.name || (acc.loading ? 'Loading…' : 'Account'),
      accMeta: [plan.name, (metrics.users || 0) + ' users', (metrics.sites || 0) + ' sites',
        (metrics.domains || 0) + ' domain' + (metrics.domains === 1 ? '' : 's')].filter(Boolean).join(' · ')
        + (acc.err ? ' · ' + acc.err : ''),
      accTabs: tabs,
      accShowTransfer: (d.users || []).some(u => (u.level || '') !== 'full-billing') && (d.owner || d.level === 'full-billing'),
      accShowTrusted: false, accShowCancel: false,
      ...this.transferVals(s, d, reload),
      accUsers: (d.users || []).map(u => { const label = this.ACC_LEVEL_LABELS[u.level] || u.level || 'Full access';
        return { n: u.name || u.email, e: u.email, level: label, last: '',
          init: (u.name || u.email).split(/[\s@]/).map(w => w[0]).join('').slice(0, 2).toUpperCase(),
          lvlBg: label === 'Owner' ? 'var(--brand-soft)' : 'var(--panel-2)',
          lvlFg: label === 'Owner' ? 'var(--brand-ink)' : 'var(--ink-dim)',
          canSwitch: false, removable: label !== 'Owner',
          switchTo: () => {},
          remove: () => { if (!confirm('Remove ' + u.email + ' from this account?')) return;
            this.api('/accounts/' + acc.accountId + '/users/' + u.user_id, { method: 'DELETE' })
              .then(reload).catch(() => {}); } }; }),
      accInvites: (s.accInvites || []).map(iv => ({ ...iv,
        mark: s.copied === 'inv' + iv.uid ? 'Copied ✓' : 'Copy link',
        copyLink: () => { try { navigator.clipboard.writeText(location.origin + '/account/?account=' + acc.accountId + '&token=' + iv.token); } catch (e) {}
          this.setState({ copied: 'inv' + iv.uid }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        del: () => this.api('/accounts/' + acc.accountId + '/invites/' + iv.uid, { method: 'DELETE' })
          .then(reload).catch(() => {}) })),
      sendInvite: () => { const e = this.state.invEmail.trim(); if (!e) return;
        this.setState({ invEmail: '' });
        this.api('/accounts/' + acc.accountId + '/invites', { method: 'POST',
          body: { invite: e, level: this.ACC_LEVEL_API[this.state.invLevel] || 'full' } })
          .then(reload).catch(() => {}); },
      accSites: (d.sites || []).map(x => { const f = this.FLEET.find(z => z.id === String(x.site_id));
        const health = f ? (f.vuln ? 'Vulnerability' : f.updates ? 'Updates pending' : 'Healthy') : 'Healthy';
        return { name: x.name, envs: f ? f.envs : '', provider: f ? f.provider : '',
          health: (x.visits ? Number(x.visits).toLocaleString() + ' visits · ' : '') + this.fmtStorage(x.storage),
          dot: health === 'Healthy' ? 'var(--ok)' : health === 'Vulnerability' ? 'var(--bad)' : 'var(--warn)',
          open: () => this.openSite(String(x.site_id)) }; }),
      accDomains: (d.domains || []).map(x => ({ name: x.name, registrar: '', expires: '', expFg: 'var(--ink-dim)',
        open: () => this.openDomain(String(x.domain_id)) })),
      planUsage: [
        { k: 'Sites', used: (usage.sites || 0) + ' of ' + (limits.sites || '—'), pct: pct(usage.sites || 0, parseInt(limits.sites, 10) || 0) },
        { k: 'Storage', used: gb(usage.storage).toFixed(1) + ' of ' + (limits.storage || '—') + ' GB', pct: pct(gb(usage.storage), parseFloat(limits.storage) || 0) },
        { k: 'Visits / mo', used: (Number(usage.visits) || 0).toLocaleString() + ' of ' + (Number(limits.visits) || 0).toLocaleString(), pct: pct(Number(usage.visits) || 0, Number(limits.visits) || 0) }
      ].map(u => ({ ...u, fill: u.pct >= 80 ? 'var(--warn)' : 'var(--brand)' })),
      planRows: [
        { k: 'Plan', v: plan.name || '—' },
        { k: 'Price', v: plan.price ? '$' + plan.price + (plan.interval == 1 ? '/mo' : ' / ' + plan.interval + ' mo') : '—' },
        { k: 'Next renewal', v: (plan.next_renewal || '—').slice(0, 10) },
        { k: 'Auto-pay', v: plan.auto_pay === 'true' || plan.auto_pay === true ? 'On' : 'Off' },
        { k: 'Addons', v: (plan.addons || []).length ? (plan.addons || []).length + ' addon' + (plan.addons.length === 1 ? '' : 's') : '—' },
        { k: 'Credits', v: (plan.credits || []).length ? plan.credits.length + ' credit' + (plan.credits.length === 1 ? '' : 's') : '—' }
      ],
      accActivity: acc.activity || []
    };
  }

});
