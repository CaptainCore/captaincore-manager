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
// Invoices ride in the same bundle (Account::fetch only attaches them for an
// admin or tier_permissions['invoices'] = full-billing), so the Invoices tab
// carries the same owner/administrator gate as Plan.

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

  PLAN_INTERVAL_UNITS: { 1: 'month', 3: 'quarter', 6: 'biannually', 12: 'year' },

  // "2026-12-01 05:00:00" -> "December 1st 2026". The date part is split by
  // hand rather than handed to Date(): the stored string has no zone, and
  // letting the engine parse it can land the renewal on the previous day.
  fmtLongDate(value) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec( String( value || '' ) );
    if ( !m ) return String( value || '' );
    const d = new Date( +m[1], +m[2] - 1, +m[3] );
    if ( isNaN( d ) ) return String( value );
    const day = d.getDate(), tens = day % 100;
    const suffix = ( tens >= 11 && tens <= 13 ) ? 'th' : ( { 1: 'st', 2: 'nd', 3: 'rd' }[ day % 10 ] || 'th' );
    return d.toLocaleDateString( undefined, { month: 'long' } ) + ' ' + day + suffix + ' ' + d.getFullYear();
  },

  // Next renewal estimate — v1's "Plan Estimate Breakdown" dialog, inlined into
  // the Plan tab. The line items mirror Account::generate_invoice() so the
  // preview matches the invoice that actually gets cut: base plan (or the
  // per-site count in per_site mode), usage overages priced from
  // configurations.usage_pricing, then addons, charges and credits.
  //
  // Overage costs are quoted against their OWN interval in the config, so they
  // are rescaled to the plan's billing interval before they are multiplied out.
  // parseInt on a blank limit gives NaN and every overage test is a `> 0`, so
  // a plan with no limit set (Custom) prices no overages rather than billing
  // every site as extra.
  planEstimate(plan) {
    const p = plan || {};
    const cfg = ( window.CC_BOOT || {} ).usagePricing || {};
    const usage = p.usage || {}, limits = p.limits || {};
    const interval = parseInt( p.interval, 10 ) || 12;
    const num = v => { const n = parseFloat( v ); return isNaN( n ) ? 0 : n; };
    const int = v => parseInt( v, 10 );
    const unitPrice = key => { const c = cfg[ key ] || {};
      const cost = num( c.cost ), ci = parseInt( c.interval, 10 );
      return ( !ci || ci === interval ) ? cost : ( cost / ci ) * interval; };
    const rows = [];

    if ( p.billing_mode === 'per_site' ) {
      const sites = int( usage.sites ) || 0;
      rows.push({ type: 'Plan', name: 'Per site', qty: sites, price: num( p.price ), total: sites * num( p.price ) });
    } else {
      rows.push({ type: 'Plan', name: p.name || 'Plan', qty: 1, price: num( p.price ), total: num( p.price ) });

      const extraSites = int( usage.sites ) - int( limits.sites );
      if ( extraSites > 0 ) { const u = unitPrice( 'sites' );
        rows.push({ type: 'Extra', name: 'Sites', qty: extraSites, price: u, total: extraSites * u }); }

      const stepStorage = num( ( cfg.storage || {} ).quantity ) || 10;
      const extraStorage = Math.ceil( ( ( num( usage.storage ) / 1073741824 ) - int( limits.storage ) ) / stepStorage );
      if ( extraStorage > 0 ) { const u = unitPrice( 'storage' );
        rows.push({ type: 'Extra', name: 'Storage (' + stepStorage + 'GB blocks)', qty: extraStorage, price: u, total: extraStorage * u }); }

      const stepVisits = num( ( cfg.traffic || {} ).quantity ) || 1000000;
      const extraVisits = Math.ceil( ( int( usage.visits ) - int( limits.visits ) ) / stepVisits );
      if ( extraVisits > 0 ) { const u = unitPrice( 'traffic' );
        rows.push({ type: 'Extra', name: 'Visits (' + Number( stepVisits ).toLocaleString() + ' blocks)', qty: extraVisits, price: u, total: extraVisits * u }); }
    }

    [ [ 'addons', 'Addon' ], [ 'charges', 'Charge' ] ].forEach( ( [ key, label ] ) => {
      ( p[ key ] || [] ).forEach( it => rows.push({ type: label, name: it.name || '', qty: num( it.quantity ),
        price: num( it.price ), total: num( it.quantity ) * num( it.price ) }) );
    });
    ( p.credits || [] ).forEach( it => rows.push({ type: 'Credit', name: it.name || '', qty: num( it.quantity ),
      price: num( it.price ), total: num( it.quantity ) * num( it.price ), credit: true }) );

    const total = Math.max( 0, rows.reduce( ( sum, r ) => sum + ( r.credit ? -r.total : r.total ), 0 ) );
    return { rows, total, unit: this.PLAN_INTERVAL_UNITS[ interval ] || '' };
  },

  // Estimate rows/labels for the Plan tab. Gated on next_renewal the way v1
  // was — an account with no renewal date is deactivated, so there is nothing
  // upcoming to preview.
  computePlanEstimate(plan) {
    const p = plan || {};
    if ( !p.next_renewal || typeof p.price === 'undefined' ) return { planEstShow: false, planEstRows: [] };
    const est = this.planEstimate( p );
    const money = n => '$' + Math.abs( n ).toFixed( 2 );
    return {
      planEstShow: true,
      planEstTotal: money( est.total ),
      planEstUnit: est.unit ? 'per ' + est.unit : '',
      planEstRenews: this.fmtLongDate( p.next_renewal ),
      planEstRows: est.rows.map( r => ({ type: r.type, name: r.name,
        qty: String( r.qty ),
        price: ( r.credit ? '-' : '' ) + money( r.price ),
        total: ( r.credit ? '-' : '' ) + money( r.total ),
        fg: r.credit ? 'var(--ok)' : 'var(--ink)' }) )
    };
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
    // Site defaults are visible AND editable for admins and full-access
    // members (the PUT route enforces the same tiers server-side).
    const canDefaults = isOp || !!d.owner || ['full-billing', 'full'].includes(d.level);
    // Invoices are owner material too — Account::fetch only attaches them for
    // an admin or a full-billing member, so the tab uses the Plan gate.
    const canInvoices = canPlan;
    const tabs = [['users', 'Users & access'], ['sites', 'Sites'], ['domains', 'Domains'], ...(canDefaults ? [['defaults', 'Site defaults']] : []), ...(canInvoices ? [['invoices', 'Invoices']] : []), ...(canPlan ? [['plan', 'Plan']] : []), ['activity', 'Activity']].map(([id, label]) => ({ label,
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
      accTabDefaults: s.accTab === 'defaults' && canDefaults,
      // Invoices tab — v1 parity with the legacy account dialog's Invoices
      // data table (Order / Date / Name / Status / Total). Rows reuse the
      // billing list's status palette and route into the shared invoice page.
      accTabInvoices: s.accTab === 'invoices' && canInvoices,
      accInvoiceRows: (d.invoices || []).map(iv => {
        const paid = /completed|processing|paid|refunded/i.test(iv.status || '');
        const payable = /pending|failed|on-hold/i.test(iv.status || '');
        return { id: '#' + iv.order_id, name: iv.name || '', date: iv.date || '',
          amount: '$' + (Number(iv.total) || 0).toFixed(2),
          status: iv.status || '',
          stBg: paid ? 'var(--ok-soft)' : payable ? 'var(--warn-soft)' : 'var(--panel-2)',
          view: () => this.openInvoice(iv.order_id, { accountId: acc.accountId, label: (this.decodeHtml(a.name) || '').trim() }),
          pdf: () => this.downloadInvoicePdf(iv.order_id) };
      }),
      accInvoicesEmpty: !acc.loading && !(d.invoices || []).length,
      accDefRows: (() => { const def = a.defaults || {};
        return [
          ['Default email', def.email || '—'],
          ['Timezone', def.timezone || '—'],
          ['Recipes on new site', (def.recipes || []).length ? (def.recipes || []).length + ' recipe(s)' : '—'],
          ['Default users', (def.users || []).length ? (def.users || []).length + ' user(s)' : '—']
        ].map(([k, v]) => ({ k, v })); })(),
      // Opens the SHARED Site defaults dialog (settings.js) with this
      // account as the save target. loadSettings() supplies the recipe
      // chips; the dialog paints once that fetch lands.
      openAccDefaults: () => { const def = a.defaults || {};
        this._accDefSeed = { ...def };
        if (!this._set && !this._setLoading) this.loadSettings();
        this.setState({ defDlgOpen: true, defTarget: String(acc.accountId),
          defEmail: def.email || '', defTimezone: def.timezone || '',
          defRecipes: (def.recipes || []).map(String),
          defUsers: (def.users || []).map(u => ({ ...u })) }); },
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
      accTermOpen: () => this.openAccountTerminal(acc.accountId, (this.decodeHtml(a.name) || '').trim()),
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
      ...this.computePlanEstimate(plan),
      // No next_renewal = the account was never activated (Accounts::update_plan
      // and the renewal cron both treat an empty renewal as inactive), so the
      // Plan card flips to a setup call to action — v1 showed the same
      // "Hosting plan not active" state on the Plan tab.
      planInactive: !!a.account_id && !plan.next_renewal,
      planInactiveText: isOp
        ? 'Hosting plan not active. Set a next renewal date to start billing this account.'
        : 'Hosting plan not active.',
      planEditLabel: plan.next_renewal ? 'Edit plan' : 'Setup plan',
      planRows: [
        { k: 'Plan', v: plan.name || '—' },
        { k: 'Price', v: plan.price ? '$' + plan.price + (plan.interval == 1 ? '/mo' : ' / ' + plan.interval + ' mo') : '—' },
        { k: 'Next renewal', v: plan.next_renewal ? this.fmtLongDate( plan.next_renewal ) : '—' },
        { k: 'Auto-pay', v: plan.auto_pay === 'true' || plan.auto_pay === true ? 'On' : 'Off' },
        { k: 'Addons', v: (plan.addons || []).length ? (plan.addons || []).length + ' addon' + (plan.addons.length === 1 ? '' : 's') : '—' },
        { k: 'Credits', v: (plan.credits || []).length ? plan.credits.length + ' credit' + (plan.credits.length === 1 ? '' : 's') : '—' }
      ],
      accActivity: acc.activity || []
    };
  }

});
