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
  EP_INTERVALS: [['1', 'Monthly'], ['3', 'Quarterly'], ['6', 'Biannual'], ['12', 'Yearly']],

  // Edit plan (operator) — v1 parity with core.php modifyPlan/updatePlan.
  // Draft lives on this._ep (instance, not state) and is mutated in place;
  // hosting plans from GET /configurations/ (+ a synthetic Custom entry, v1
  // convention). Save = PUT /accounts/{id}/plan {plan} (admin-gated).
  openEditPlan(plan) {
    const d = JSON.parse(JSON.stringify(plan || {}));
    d.limits = d.limits || {};
    ['addons', 'credits', 'charges'].forEach(k => { if (!Array.isArray(d[k])) d[k] = []; });
    if (!d.billing_mode) d.billing_mode = 'standard';
    if (!d.interval) d.interval = '12';
    if (d.next_renewal == null) d.next_renewal = '';
    if (d.additional_emails == null) d.additional_emails = '';
    if (Array.isArray(d.price)) d.price = '';
    d.auto_pay = d.auto_pay === true || d.auto_pay === 'true';
    d.auto_switch = d.auto_switch === true || d.auto_switch === 'true';
    this._ep = d; this._epMsg = ''; this._epSaving = false; this._epRenewalGen = 0;
    if (!this._epPlans) {
      const custom = { name: 'Custom', interval: '12', price: '', limits: { visits: '', storage: '', sites: '' } };
      this.api('/configurations/').then(cfg => {
        const plans = (cfg && Array.isArray(cfg.hosting_plans)) ? cfg.hosting_plans : [];
        this._epPlans = plans.concat([custom]);
        this.setState({});
      }).catch(() => { this._epPlans = [custom]; this.setState({}); });
    }
    this.setState({ epOpen: true });
  },

  computeEditPlan(s, acc, a, plan, reload) {
    const users = ((acc.data || {}).users) || [];
    const ep = this._ep || {};
    const plans = this._epPlans || [];
    const mut = fn => { fn(this._ep); this.setState({}); };
    const recalc = () => { const base = plans.find(p => p.name === ep.name);
      if (!base || !base.price) { if (base) ep.price = base.price; return; }
      const bi = parseInt(base.interval, 10) || 12, ci = parseInt(ep.interval, 10) || 12;
      ep.price = ci === bi ? base.price : ((parseFloat(base.price) / bi) * ci).toFixed(2); };
    const chip = on => ({ bd: on ? 'var(--brand)' : 'var(--rule)', bg: on ? 'var(--brand-soft)' : 'var(--paper)', fg: on ? 'var(--brand-ink)' : 'var(--ink-dim)' });
    const listRows = key => (ep[key] || []).map((it, i) => { const locked = key === 'addons' && !!it.required;
      return { name: it.name || '', qty: it.quantity || '', price: it.price || '',
        editable: !locked, locked,
        onName: e => mut(x => { x[key][i].name = e.target.value; }),
        onQty: e => mut(x => { x[key][i].quantity = e.target.value; }),
        onPrice: e => mut(x => { x[key][i].price = e.target.value; }),
        del: () => mut(x => { x[key].splice(i, 1); }) }; });
    const addRow = key => () => mut(x => { x[key].push({ name: '', quantity: '', price: '' }); });
    const toggle = key => ({ bg: ep[key] ? 'var(--brand)' : 'var(--rule)', just: ep[key] ? 'flex-end' : 'flex-start',
      flip: () => mut(x => { x[key] = !x[key]; }) });
    const ap = toggle('auto_pay'), asw = toggle('auto_switch');
    return {
      accShowEditPlan: (window.CC_BOOT || {}).dcRole === 'operator',
      openEditPlan: () => this.openEditPlan(plan),
      epOpen: !!s.epOpen && !!this._ep,
      closeEp: () => this.setState({ epOpen: false }),
      epTitle: 'Edit plan for ' + (a.name || ''),
      epPlansLoading: !this._epPlans,
      epPlanChips: plans.map(p => ({ label: p.name, ...chip(ep.name === p.name),
        pick: () => mut(x => { x.name = p.name; x.limits = JSON.parse(JSON.stringify(p.limits || {}));
          if (p.billing_mode) x.billing_mode = p.billing_mode;
          x.price = p.price; recalc(); }) })),
      epIntervalChips: this.EP_INTERVALS.map(([v, label]) => ({ label, ...chip(String(ep.interval) === v),
        pick: () => mut(x => { x.interval = v; recalc(); }) })),
      epHasBillUsers: users.length > 0,
      // Clicking the selected row TOGGLES it off — an account can legitimately
      // have no billing user (Account.php normalizes empty to 0 and the
      // invoice path guards on it), and there was otherwise no way back out
      // once one was picked.
      epBillRows: users.map(u => {
        const on = String(ep.billing_user_id) === String(u.user_id);
        return { label: u.name || u.email, sub: u.email,
          mark: on ? '✓' : '',
          bg: on ? 'var(--brand-soft)' : 'transparent',
          title: on ? 'Click to clear the billing user' : 'Set as billing user',
          pick: () => mut(x => { x.billing_user_id = on ? 0 : u.user_id; }) };
      }),
      epBillClearShow: !!Number(ep.billing_user_id),
      epBillClear: () => mut(x => { x.billing_user_id = 0; }),
      epBillNone: !Number(ep.billing_user_id),
      // datetime-local wants "YYYY-MM-DDTHH:MM:SS"; the API stores "YYYY-MM-DD HH:MM:SS".
      // Native pickers can't be emptied, and clearing is how accounts are
      // deactivated (Accounts::update_plan + the renewal cron both treat
      // empty next_renewal as inactive). Clear remounts the input because
      // the DC runtime binds value like defaultValue.
      epRenewal: (ep.next_renewal || '').replace(' ', 'T'),
      epRenewalA: (this._epRenewalGen || 0) % 2 === 0,
      epRenewalB: (this._epRenewalGen || 0) % 2 === 1,
      epRenewalClearShow: !!(ep.next_renewal || '').trim(),
      epRenewalNone: !(ep.next_renewal || '').trim(),
      epRenewalClear: () => mut(x => { x.next_renewal = ''; this._epRenewalGen = (this._epRenewalGen || 0) + 1; }),
      onEpRenewal: e => mut(x => {
        const v = e.target.value || '';
        x.next_renewal = v ? (v.replace('T', ' ') + (v.length === 16 ? ':00' : '')) : '';
      }),
      epAutoPayBg: ap.bg, epAutoPayJust: ap.just, epAutoPayFlip: ap.flip,
      epAutoSwitchBg: asw.bg, epAutoSwitchJust: asw.just, epAutoSwitchFlip: asw.flip,
      epModeChips: [['standard', 'Standard'], ['per_site', 'Per site']].map(([v, label]) => ({ label, ...chip(ep.billing_mode === v),
        pick: () => mut(x => { x.billing_mode = v; }) })),
      epPerSite: ep.billing_mode === 'per_site',
      epIsCustom: ep.billing_mode !== 'per_site' && ep.name === 'Custom',
      epFixed: ep.billing_mode !== 'per_site' && ep.name !== 'Custom',
      epStorage: (ep.limits || {}).storage || '', onEpStorage: e => mut(x => { x.limits.storage = e.target.value; }),
      epVisits: (ep.limits || {}).visits || '', onEpVisits: e => mut(x => { x.limits.visits = e.target.value; }),
      epSites: (ep.limits || {}).sites || '', onEpSites: e => mut(x => { x.limits.sites = e.target.value; }),
      epPrice: ep.price == null ? '' : String(ep.price), onEpPrice: e => mut(x => { x.price = e.target.value; }),
      epSitesActive: (((plan || {}).usage || {}).sites || 0) + ' active',
      epFixedRows: [['Storage (GBs)', (ep.limits || {}).storage], ['Visits', (ep.limits || {}).visits], ['Sites', (ep.limits || {}).sites], ['Price', ep.price]]
        .map(([k, v]) => ({ k, v: (v == null || v === '' || (Array.isArray(v) && !v.length)) ? '—' : String(v) })),
      epAddons: listRows('addons'), addEpAddon: addRow('addons'),
      epCredits: listRows('credits'), addEpCredit: addRow('credits'),
      epCharges: listRows('charges'), addEpCharge: addRow('charges'),
      epEmails: ep.additional_emails || '', onEpEmails: e => mut(x => { x.additional_emails = e.target.value; }),
      epMsg: this._epMsg || '', epHasMsg: !!this._epMsg,
      epSaveLabel: this._epSaving ? 'Saving…' : 'Save changes',
      epSave: () => {
        if (this._epSaving || !this._ep) return;
        const payload = JSON.parse(JSON.stringify(this._ep));
        payload.auto_pay = this._ep.auto_pay ? 'true' : 'false';
        payload.auto_switch = this._ep.auto_switch ? 'true' : 'false';
        if (payload.limits && payload.limits.visits != null) payload.limits.visits = String(payload.limits.visits).replace(/,/g, '');
        payload.addons = (payload.addons || []).filter(x => !x.required);
        this._epSaving = true; this._epMsg = ''; this.setState({});
        this.api('/accounts/' + acc.accountId + '/plan', { method: 'PUT', body: { plan: payload } })
          .then(res => { this._epSaving = false;
            if (res && res.code) { this._epMsg = res.message || 'Save failed.'; this.setState({}); return; }
            this._ep = null; this.setState({ epOpen: false }); this.toast('Plan updated', { kind: 'success' }); reload(); })
          .catch(() => { this._epSaving = false; this._epMsg = 'Save failed.'; this.setState({}); });
      }
    };
  },

  openAccount(id) {
    this.setState({ route: 'account', accountId: id, accTab: 'users', paletteOpen: false, accRename: false,
      accInvites: this._hydrated ? [] : [{ uid: 1, e: 'bookkeeper@ledgerly.com', level: 'Domains only', sent: 'Jul 10' }],
      trusted: this._hydrated ? [] : this.TRUSTED.map(t => ({ ...t })), invEmail: '', invLevel: 'Full access' });
    if (this._hydrated) this.loadAccountDetail(id);
  },

  // Refetch /accounts/ into this.ACCOUNTS (mirrors data.js hydrate mapping).
  reloadAccounts() {
    return this.api('/accounts/').then(accounts => {
      this.ACCOUNTS = (Array.isArray(accounts) ? accounts : []).map(a => ({ id: String(a.account_id), name: this.decodeHtml(a.name),
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

  renameAccount(acc, a, plan, reload) {
    const name = (this.state.accRenameVal || '').trim();
    if (!name || name === (this.decodeHtml(a.name) || '').trim()) { this.setState({ accRename: false }); return; }
    const tid = this.toast('Renaming account…', { kind: 'loading' });
    // billing_user_id rides along for v1 payload parity (the hardened route
    // only writes supplied keys either way).
    const body = { account: { name, ...(plan.billing_user_id ? { billing_user_id: plan.billing_user_id } : {}) } };
    this.api('/accounts/' + acc.accountId, { method: 'PUT', body }).then(res => {
      if (res && res.code) { this.updateToast(tid, res.message || 'Rename failed', { kind: 'error' }); return; }
      this.updateToast(tid, 'Account renamed', { kind: 'success' });
      const row = this.ACCOUNTS.find(x => x.id === String(acc.accountId));
      if (row) row.name = name;
      this.setState({ accRename: false });
      reload();
    }).catch(() => this.updateToast(tid, 'Rename failed', { kind: 'error' }));
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
    const isOp = ((window.CC_BOOT && window.CC_BOOT.dcRole) || 'operator') === 'operator';
    // Plan is owner-only (tier_permissions: plan=false below full-billing);
    // operators keep it for Edit plan. The server strips plan details down to
    // the name for everyone else, so full-access users get neither the tab
    // nor the data.
    const canPlan = isOp || !!d.owner || d.level === 'full-billing';
    const tabs = [['users', 'Users & access'], ['sites', 'Sites'], ['domains', 'Domains'], ...(canPlan ? [['plan', 'Plan']] : []), ['activity', 'Activity']].map(([id, label]) => ({ label,
      fg: s.accTab === id ? 'var(--ink)' : 'var(--ink-dim)',
      bg: s.accTab === id ? 'var(--panel-2)' : 'transparent',
      go: () => { this.setState({ accTab: id }); if (id === 'activity') this.loadAccountActivity(); } }));
    const doRename = () => this.renameAccount(acc, a, plan, reload);
    return {
      accName: (this.decodeHtml(a.name) || '').trim() || (acc.loading ? 'Loading…' : 'Account'),
      // Inline rename (pencil beside the name). Route: PUT /accounts/{id}
      // {account:{name}} — owner/admin gated server-side (verify_account_owner);
      // the UI gate mirrors it. Input seeds via ref (DC binds value like
      // defaultValue), keyed by account id so switching accounts re-seeds.
      accCanRename: !!a.account_id && (isOp || !!d.owner || d.level === 'full-billing'),
      accRenaming: !!s.accRename, accNotRenaming: !s.accRename,
      accStartRename: () => this.setState({ accRename: true, accRenameVal: (this.decodeHtml(a.name) || '').trim() }),
      accRenameRef: el => { if (el && el._forId !== String(acc.accountId)) { el._forId = String(acc.accountId);
        el.value = (this.decodeHtml(a.name) || '').trim(); el.focus(); el.select(); } },
      onAccRename: e => this.setState({ accRenameVal: e.target.value }),
      accRenameKey: e => { if (e.key === 'Enter') { e.preventDefault(); doRename(); } },
      accRenameCancel: () => this.setState({ accRename: false }),
      accRenameSave: doRename,
      accMeta: [plan.name, (metrics.users || 0) + ' users', (metrics.sites || 0) + ' sites',
        (metrics.domains || 0) + ' domain' + (metrics.domains === 1 ? '' : 's')].filter(Boolean).join(' · ')
        + (acc.err ? ' · ' + acc.err : ''),
      accTabs: tabs,
      accTabPlan: s.accTab === 'plan' && canPlan,
      accShowTransfer: (d.users || []).some(u => (u.level || '') !== 'full-billing') && (d.owner || d.level === 'full-billing'),
      accShowTrusted: false, accShowCancel: false,
      // v1 parity (deleteAccount): admin-only; the route also kicks off the
      // CLI's background "account delete" cleanup.
      accShowDelete: (window.CC_BOOT || {}).dcRole === 'operator',
      accDelete: () => {
        if (!confirm('Delete account "' + (this.decodeHtml(a.name) || '') + '"? This cannot be undone.')) return;
        this.api('/accounts/' + acc.accountId, { method: 'DELETE' }).then(() => {
          this.ACCOUNTS = this.ACCOUNTS.filter(x => x.id !== String(acc.accountId));
          this._account = null;
          if (this.toast) this.toast('Deleting account ' + (a.name || '') + '.', { kind: 'success' });
          this.setState({ route: 'accounts', accountId: null });
        }).catch(() => { if (this.toast) this.toast('Failed to delete account.', { kind: 'error' }); });
      },
      ...this.transferVals(s, d, reload),
      ...this.computeEditPlan(s, acc, a, plan, reload),
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
      // "Request changes" — small dialog → POST /billing/request-plan-changes
      // (v1 dialog_modify_plan's customer path; Mailer renders subscription.name,
      // plan.name and plan.interval, so the request message rides plan.name).
      planRequest: () => this.setState({ planReqOpen: true, planReqText: '' }),
      planReqOpen: !!s.planReqOpen,
      planReqText: s.planReqText || '',
      onPlanReqText: e => this.setState({ planReqText: e.target.value }),
      closePlanReq: () => this.setState({ planReqOpen: false }),
      planReqPlan: plan.name || '—',
      planReqSend: () => {
        const msg = (this.state.planReqText || '').trim();
        if (!msg) return;
        const tid = this.toast('Sending request…', { kind: 'loading' });
        this.api('/billing/request-plan-changes', { method: 'POST', body: { subscription: {
          name: plan.name || 'Current plan',
          plan: { name: msg, interval: plan.interval || '—' } } } })
          .then(() => { this.updateToast(tid, 'Change request sent', { kind: 'success' }); this.setState({ planReqOpen: false }); })
          .catch(() => { this.updateToast(tid, 'Could not send the request', { kind: 'error' }); });
      },
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
