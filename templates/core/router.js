// CaptainCore v3 — deep-linking / History API router (mixin).
// Router.php already serves the SPA for every /account/<route> path, so this
// keeps the URL in sync with state.route (+ detail ids and primary tabs) and
// restores state on load / back-forward. This UI is the server default now —
// pushes drop the obsolete ?ui=v3 dev-gate param (old bookmarks still load;
// the first navigation cleans the URL) while preserving any other query.
//
// applyUrl()  — parse location → setState (mount + popstate)
// syncUrl()   — called from componentDidUpdate; pushState when the path drifts
// Detail routes (site/domain/account) go through openSite/openDomain/
// openAccount so their bundles load; after hydration the current URL is
// re-applied so a deep-linked detail actually fetches.

Object.assign(Component.prototype, {

  routeBase() {
    const p = (window.CC_BOOT && window.CC_BOOT.path) || '/account/';
    return p.endsWith('/') ? p : p + '/';
  },

  // state.route → the path segment(s) after the base.
  ROUTE_SEG: { home: '', sites: 'sites', site: 'sites', domains: 'domains', domain: 'domains',
    accounts: 'accounts', account: 'accounts', billing: 'billing', invoice: 'billing', security: 'security',
    audits: 'site-audits', activity: 'activity', reports: 'reports', archives: 'archives', settings: 'settings', profile: 'profile' },
  SEG_ROUTE: { '': 'home', sites: 'sites', domains: 'domains', accounts: 'accounts', billing: 'billing',
    security: 'security', 'site-audits': 'audits', activity: 'activity', reports: 'reports', archives: 'archives',
    settings: 'settings', profile: 'profile' },

  pathForState() {
    const s = this.state;
    const base = this.routeBase();
    const seg = this.ROUTE_SEG[s.route];
    if (seg === undefined) return base; // stub/unknown → home
    let path = base + seg;
    if (s.route === 'site' && s.siteId) { path += '/' + s.siteId; if (s.siteTab && s.siteTab !== 'overview') path += '/' + s.siteTab; }
    else if (s.route === 'domain' && s.domainId) { path += '/' + s.domainId; }
    else if (s.route === 'account' && s.accountId) { path += '/' + s.accountId; }
    else if (s.route === 'invoice' && s.invoiceId) { path += '/' + s.invoiceId; }
    else if (s.route === 'security' && s.secTab && s.secTab !== 'vulns') { path += '/' + s.secTab; }
    return path;
  },

  applyUrl() {
    const base = this.routeBase();
    let rel = location.pathname;
    if (rel.indexOf(base) === 0) rel = rel.slice(base.length);
    rel = rel.replace(/^\/+|\/+$/g, '');
    const parts = rel ? rel.split('/') : [];
    const head = parts[0] || '';
    this._suppressPush = true;
    if (head === 'sites' && parts[1]) {
      this.openSite(parts[1]);
      // goSiteTab normalizes legacy leaf names ('addons'), syncs addonKind and
      // fires that leaf's lazy load — a plain setState would skip all three.
      if (parts[2]) this.goSiteTab(parts[2]);
    } else if (head === 'domains' && parts[1]) {
      this.openDomain(parts[1]);
    } else if (head === 'accounts' && parts[1]) {
      this.openAccount(parts[1]);
    } else if (head === 'billing' && parts[1]) {
      this.openInvoice(parts[1]);
    } else if (head === 'security') {
      const tab = parts[1] || 'vulns';
      const ok = { vulns: 1, checksums: 1, coverage: 1, core: 1 };
      this.setState({ route: 'security', secTab: ok[tab] ? tab : 'vulns' });
    } else {
      const route = this.SEG_ROUTE[head] || 'home';
      this.setState({ route });
    }
  },

  // location.search minus the retired ui=... switch (v3 was gated on ?ui=v3;
  // legacy uses ?ui=legacy and must never ride along on SPA pushes).
  cleanSearch() {
    try {
      const p = new URLSearchParams(location.search);
      p.delete('ui');
      const s = p.toString();
      return s ? '?' + s : '';
    } catch (e) { return location.search; }
  },

  syncUrl() {
    if (this._suppressPush) { this._suppressPush = false; return; }
    const target = this.pathForState();
    if (target === location.pathname && this.cleanSearch() === location.search) return;
    try { history.pushState({ ccv3: true }, '', target + this.cleanSearch()); } catch (e) {}
  },

  initRouter() {
    if (this._routerReady) return;
    this._routerReady = true;
    window.addEventListener('popstate', () => this.applyUrl());
    this.applyUrl();
    // Replace the initial entry so back returns to the real landing URL.
    try { history.replaceState({ ccv3: true }, '', this.pathForState() + this.cleanSearch()); } catch (e) {}
  }

});
