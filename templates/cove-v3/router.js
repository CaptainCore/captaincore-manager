// CaptainCore v3 — deep-linking / History API router (mixin).
// Router.php already serves the SPA for every /account/<route> path, so this
// keeps the URL in sync with state.route (+ detail ids and primary tabs) and
// restores state on load / back-forward. The ?ui=v3 query string is preserved
// on every push during the dev phase (v3 is gated on ?ui=v3 server-side).
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
    accounts: 'accounts', account: 'accounts', billing: 'billing', security: 'security',
    audits: 'site-audits', reports: 'reports', archives: 'archives', settings: 'settings', profile: 'profile' },
  SEG_ROUTE: { '': 'home', sites: 'sites', domains: 'domains', accounts: 'accounts', billing: 'billing',
    security: 'security', 'site-audits': 'audits', reports: 'reports', archives: 'archives',
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
      if (parts[2]) this.setState({ siteTab: parts[2] });
    } else if (head === 'domains' && parts[1]) {
      this.openDomain(parts[1]);
    } else if (head === 'accounts' && parts[1]) {
      this.openAccount(parts[1]);
    } else {
      const route = this.SEG_ROUTE[head] || 'home';
      this.setState({ route });
    }
  },

  syncUrl() {
    if (this._suppressPush) { this._suppressPush = false; return; }
    const target = this.pathForState();
    if (target === location.pathname) return;
    try { history.pushState({ ccv3: true }, '', target + location.search); } catch (e) {}
  },

  initRouter() {
    if (this._routerReady) return;
    this._routerReady = true;
    window.addEventListener('popstate', () => this.applyUrl());
    this.applyUrl();
    // Replace the initial entry so back returns to the real landing URL.
    try { history.replaceState({ ccv3: true }, '', this.pathForState() + location.search); } catch (e) {}
  }

});
