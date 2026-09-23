// CaptainCore v3 — real-data layer (mixin).
// REST plumbing + fleet hydration. Loaded after app.js by templates/core.php.

Object.assign(Component.prototype, {

  // One REST call. The wp_rest nonce in CC_BOOT expires after 12-24 hours
  // (WordPress nonce tick) and dies outright when the login session ends. A
  // tab left open overnight used to fail every call with a silent 403 (the
  // legacy Vue app had an axios interceptor that re-fetched the nonce and
  // retried once; this is the same idea). On `rest_cookie_invalid_nonce` we
  // pull a fresh nonce through core's `rest-nonce` admin-ajax action and
  // replay the request. The replay is safe for POST/PUT/DELETE too: the nonce
  // check runs in rest_authentication_errors, before any handler executes.
  // If the nonce cannot be refreshed the WordPress session itself is gone and
  // the user is sent to the login page (with the current URL to come back to).
  // Every other 401/403 still surfaces as the 'auth' error callers already
  // handle (a customer on an operator route is a real denial, not a stale
  // session, and must not bounce them to login).
  api(path, opts = {}) {
    const boot = window.CC_BOOT || {};
    const send = () => fetch(boot.restRoot + 'captaincore/v1' + path, {
      headers: Object.assign({ 'X-WP-Nonce': boot.nonce, 'Content-Type': 'application/json' }, opts.headers || {}),
      method: opts.method || 'GET',
      body: opts.body ? JSON.stringify(opts.body) : undefined
    });
    // Some routes answer 200 with an empty body (a handler that ends in a
    // bare `return;`). Treat that as null instead of a JSON parse failure,
    // which used to surface as a spurious "Could not …" toast.
    const parse = r => r.text().then(t => (t && t.trim()) ? JSON.parse(t) : null);
    return send().then(r => {
      if (r.status !== 401 && r.status !== 403) return parse(r);
      return r.text().then(t => {
        let code = '';
        try { code = (JSON.parse(t) || {}).code || ''; } catch (e) {}
        if (code !== 'rest_cookie_invalid_nonce' || opts._retried) throw new Error('auth');
        return this.refreshNonce().then(
          () => this.api(path, Object.assign({}, opts, { _retried: true })),
          () => { this.sessionExpired(); throw new Error('auth'); }
        );
      });
    });
  },

  // Fetch a fresh wp_rest nonce for the current login session via core's
  // `rest-nonce` admin-ajax action (the same call @wordpress/api-fetch makes).
  // Resolves with the nonce, which is also written into CC_BOOT so the raw
  // fetch()/XHR callers that read boot.nonce directly (PDF, audit HTML, zip
  // upload) pick it up. Rejects when the session is no longer logged in
  // (admin-ajax answers 400 "0"). Concurrent callers share one in-flight
  // request so a burst of stale polls does not fan out into N refreshes.
  refreshNonce() {
    const boot = window.CC_BOOT || {};
    if (boot._nonceRefresh) return boot._nonceRefresh;
    if (!boot.ajaxUrl) return Promise.reject(new Error('auth'));
    const done = () => { boot._nonceRefresh = null; };
    boot._nonceRefresh = fetch(boot.ajaxUrl + '?action=rest-nonce', { credentials: 'same-origin', cache: 'no-store' })
      .then(r => r.ok ? r.text() : Promise.reject(new Error('auth')))
      .then(t => {
        const nonce = (t || '').trim();
        if (!/^[a-f0-9]{10}$/.test(nonce)) throw new Error('auth');
        boot.nonce = nonce;
        boot.nonceAt = Date.now();
        return nonce;
      })
      .then(n => { done(); return n; }, e => { done(); throw e; });
    return boot._nonceRefresh;
  },

  // The login session is gone: go to the login page, carrying the current
  // app URL so a successful sign-in lands back on the same screen.
  sessionExpired() {
    const boot = window.CC_BOOT || {};
    if (!boot.loginUrl || boot._expiring) return;
    boot._expiring = true;
    this.stopSessionWatch();
    const back = location.pathname + location.search;
    location.href = boot.loginUrl + (back.indexOf(boot.path || '/') === 0 ? '?redirect_to=' + encodeURIComponent(back) : '');
  },

  // Background keep-alive for the nonce. Refreshes it every 30 minutes while
  // the tab is open, and immediately when a hidden tab comes back to the
  // foreground after more than 5 minutes, so a dashboard left open overnight
  // already holds a valid nonce before the first click. A refresh that
  // reports the session as logged out redirects to the login page right away
  // instead of waiting for the next call to fail.
  startSessionWatch() {
    const boot = window.CC_BOOT;
    if (!boot || !boot.nonce || !boot.ajaxUrl) return;
    boot.nonceAt = boot.nonceAt || Date.now();
    const bump = () => this.refreshNonce().catch(() => this.sessionExpired());
    this._sessionTimer = setInterval(bump, 30 * 60 * 1000);
    this._onSessionVisible = () => {
      if (document.visibilityState !== 'visible') return;
      if (Date.now() - (boot.nonceAt || 0) > 5 * 60 * 1000) bump();
    };
    document.addEventListener('visibilitychange', this._onSessionVisible);
  },

  stopSessionWatch() {
    if (this._sessionTimer) { clearInterval(this._sessionTimer); this._sessionTimer = null; }
    if (this._onSessionVisible) { document.removeEventListener('visibilitychange', this._onSessionVisible); this._onSessionVisible = null; }
  },

  fmtStorage(b) {
    const n = parseInt(b, 10) || 0;
    if (!n) return '\u2014';
    if (n >= 1099511627776) return (n / 1099511627776).toFixed(1) + ' TB';
    return n >= 1073741824 ? (n / 1073741824).toFixed(1) + ' GB' : Math.round(n / 1048576) + ' MB';
  },

  // Stat-tile count for a lazily fetched list. undefined = never requested,
  // null = in flight (both render as an ellipsis); an empty array is a real
  // zero and must not fall through to a placeholder.
  statCount(list) {
    if (!Array.isArray(list)) return '…';
    return Number(list.length).toLocaleString();
  },

  hydrate() {
    const boot = window.CC_BOOT;
    if (!boot || !boot.nonce) return;
    Promise.all([this.api('/sites/'), this.api('/accounts/'), this.api('/domains/')]).then(([sites, accounts, domains]) => {
      const accName = {}; (Array.isArray(accounts) ? accounts : []).forEach(a => { accName[a.account_id] = this.decodeHtml(a.name); });
      this.LABEL_META = {};
      // Raw fleet totals for the home "Fleet at a glance" card (the FLEET
      // records store display-formatted strings, so accumulate here).
      const totals = this._fleetTotals = { visits: 0, storage: 0, cores: {}, providers: {} };
      // Sites marked for removal STAY in the fleet (v1 keeps them listed too).
      // Filtering them out stranded the request: you could not open the site to
      // cancel, and operators could not see the pending-removal queue. They
      // carry `removed` instead and wear a chip + an operator-only filter.
      this.FLEET = (Array.isArray(sites) ? sites : []).map(x => {
        totals.visits += Number(x.visits) || 0;
        totals.storage += parseInt(x.storage, 10) || 0;
        if (x.core) totals.cores[x.core] = (totals.cores[x.core] || 0) + 1;
        const provKey = (x.provider || '').replace(/\b[a-z]/g, c => c.toUpperCase()) || 'Other';
        totals.providers[provKey] = (totals.providers[provKey] || 0) + 1;
        (Array.isArray(x.labels) ? x.labels : []).forEach(l => {
          if (l && typeof l === 'object' && l.type && !this.LABEL_META[l.type]) {
            this.LABEL_META[l.type] = { color: l.color || 'grey', icon: l.icon || '' };
          }
        });
        const envs = (x.environments || []).map(e => e.environment === 'Production' ? 'Prod' : e.environment).filter(Boolean).join(' \u00b7 ') || 'Prod';
        const provider = (x.provider || '').replace(/\b[a-z]/g, c => c.toUpperCase());
        return { id: String(x.site_id), name: x.name, site: x.site || '', provider, providerSiteId: x.provider_site_id || '', providerConnId: String(x.provider_id || ''), account: accName[x.account_id] || '', accountId: String(x.account_id || ''),
          core: x.core || '', visits: x.visits ? Number(x.visits).toLocaleString() : '\u2014',
          storage: this.fmtStorage(x.storage), envs, updates: 0, vuln: 0, owned: true, theme: '',
          backup: 'Direct',
          labels: (Array.isArray(x.labels) ? x.labels : []).map(l => typeof l === 'string' ? l : (l && (l.type || l.text)) || '').filter(Boolean),
          unassigned: !x.account_id || x.account_id == '0',
          removed: !!x.removed,
          plugins: {}, home_url: x.home_url, screenshot: x.screenshot,
          network: x.network || null,
          netType: ({ multisite: 'Multisite', host: 'Freighter host', tenant: 'Freighter tenant' })[(x.network && x.network.type) || ''] || 'Standalone',
          environmentsRaw: x.environments || [] };
      });
      this.ACCOUNTS = (Array.isArray(accounts) ? accounts : []).map(a => ({ id: String(a.account_id), name: this.decodeHtml(a.name),
        users: (a.metrics && a.metrics.users) || 0, sites: (a.metrics && a.metrics.sites) || 0,
        domains: (a.metrics && a.metrics.domains) || 0, plan: a.plan_name || '', owned: true,
        due: !!(a.metrics && a.metrics.outstanding_invoices > 0) }));
      // Registrar column: registered through us \u2192 the provider name for
      // operators (Hover.com / Spaceship \u2014 operationally useful) and the
      // configured business name (Branding settings, CC_BOOT.name) for
      // customers (the vendor is an implementation detail); else "External".
      const domIsOp = (boot.dcRole || 'operator') === 'operator';
      const brandName = boot.name || 'Anchor Hosting';
      this.DOMAINS = (Array.isArray(domains) ? domains : []).map(d => ({ id: String(d.domain_id), name: d.name,
        account: '', registrar: d.provider_id ? (domIsOp && d.provider ? d.provider : brandName) : 'External',
        providerId: d.provider_id || '', dns: !!d.remote_id,
        forwarding: !!d.forwarding, sending: !!d.sending, expires: '\u2014', auto: null, owned: true }));
      this._hydrated = true;
      // Drop the design's sample jobs; only real dispatched jobs from here on.
      this.setState(st => ({ tick: st.tick, jobs: st.jobs.filter(j => j.real) }));
      // Re-apply the URL so a deep-linked detail (e.g. /account/sites/135) that
      // couldn't fetch pre-hydration now loads its bundle.
      if (this._routerReady && ['site', 'domain', 'account', 'invoice'].includes(this.state.route)) this.applyUrl();
    }).catch(err => {
      if (err && err.message === 'auth' && boot.loginUrl) { location.href = boot.loginUrl; return; }
      console.warn('CaptainCore v3 hydrate failed; using design sample data.', err);
    });
  },

  realPinned() {
    const healthOf = x => x.vuln ? ['Vulnerability', 'var(--bad)'] : x.updates ? ['Updates pending', 'var(--warn)'] : ['Healthy', 'var(--ok)'];
    return this.FLEET.slice(0, 4).map(x => { const [health, dot] = healthOf(x);
      return { id: x.id, name: x.name, sub: [x.provider, x.core, x.envs].filter(Boolean).join(' \u00b7 '), health, dot }; });
  },

  realPalItems(role) {
    const sites = this.FLEET.map(x => ({ label: x.name, sub: [x.provider, x.envs].filter(Boolean).join(' \u00b7 '),
      kind: 'site', icon: this.ICONS.site, act: 'site', sid: x.id }));
    const doms = this.DOMAINS.filter(d => d.dns).map(d => ({ label: d.name, sub: 'DNS active',
      kind: 'domain', icon: this.ICONS.domains, act: 'domain', did: d.id }));
    const accs = this.ACCOUNTS.map(a => ({ label: a.name,
      sub: [a.plan, a.sites + ' site' + (a.sites === 1 ? '' : 's')].filter(Boolean).join(' \u00b7 '),
      kind: 'account', icon: this.ICONS.accounts, act: 'account', aid: a.id }));
    return [...sites, ...doms, ...accs,
      { label: 'Open terminal', sub: 'Streamed console on any site', kind: 'command', icon: this.ICONS.terminal, act: 'dock' },
      { label: (this.state.navHidden ? 'Show' : 'Hide') + ' sidebar', sub: '\u2318.', kind: 'command', icon: 'M3 3h18v18H3z M9 3v18', act: 'navtoggle' },
      { label: 'Go to Billing \u2192 Invoices', sub: '', kind: 'command', icon: this.ICONS.billing, act: 'billing' },
      { label: 'Help & shortcuts', sub: 'What \u2318K, the terminal and right-click can do', kind: 'command', icon: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7 M12 17h.01', act: 'help' },
      ...(role === 'operator' ? [
        { label: 'Go to Security \u2192 Coverage', sub: 'Fleet audit coverage', kind: 'command', icon: this.ICONS.security, act: 'security' },
        { label: 'Go to Security \u2192 Core', sub: 'Core probe results', kind: 'command', icon: this.ICONS.security, act: 'security-core' },
        { label: 'Bulk tools on filtered sites\u2026', sub: 'sync \u00b7 deploy defaults \u00b7 https \u00b7 backup', kind: 'command', icon: this.ICONS.sites, act: 'sites' }
      ] : [])];
  },

  realStats() {
    const p = (n, w) => n + ' ' + w + (n === 1 ? '' : 's');
    return p(this.FLEET.length, 'site') + ' \u00b7 ' + p(this.DOMAINS.length, 'domain');
  },

  // Home "Fleet at a glance" rows \u2014 computed client-side from hydration totals.
  realFleetGlance() {
    const t = this._fleetTotals;
    if (!t || !this.FLEET.length) return [];
    // Dominant core version — "78% on 6.9.1" reads honestly; the newest
    // version is usually a tiny early-adopter slice.
    const mode = Object.entries(t.cores).sort((a, b) => b[1] - a[1])[0] || ['', 0];
    const latest = mode[0];
    const onLatest = latest ? Math.round(mode[1] / this.FLEET.length * 100) : 0;
    const provs = Object.entries(t.providers).sort((a, b) => b[1] - a[1]);
    const provLine = provs.slice(0, 2).map(([n, c]) => n + ' ' + c.toLocaleString()).join(' \u00b7 ')
      + (provs.length > 2 ? ' \u00b7 +' + (provs.length - 2) + ' more' : '');
    const visits = t.visits >= 1e6 ? (t.visits / 1e6).toFixed(1) + 'M' : t.visits >= 1e3 ? Math.round(t.visits / 1e3) + 'k' : String(t.visits);
    return [
      { k: 'WP core', v: onLatest + '% on ' + latest },
      { k: 'Providers', v: provLine },
      { k: 'Traffic', v: visits + ' visits/wk' },
      { k: 'Storage', v: this.fmtStorage(t.storage) }
    ];
  }

});
