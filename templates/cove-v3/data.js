// CaptainCore v3 — real-data layer (mixin).
// REST plumbing + fleet hydration. Loaded after app.js by templates/cove-v3.php.

Object.assign(Component.prototype, {

  api(path, opts = {}) {
    const boot = window.CC_BOOT || {};
    return fetch(boot.restRoot + 'captaincore/v1' + path, {
      headers: Object.assign({ 'X-WP-Nonce': boot.nonce, 'Content-Type': 'application/json' }, opts.headers || {}),
      method: opts.method || 'GET',
      body: opts.body ? JSON.stringify(opts.body) : undefined
    }).then(r => {
      if (r.status === 401 || r.status === 403) throw new Error('auth');
      return r.json();
    });
  },

  fmtStorage(b) {
    const n = parseInt(b, 10) || 0;
    if (!n) return '\u2014';
    return n >= 1073741824 ? (n / 1073741824).toFixed(1) + ' GB' : Math.round(n / 1048576) + ' MB';
  },

  hydrate() {
    const boot = window.CC_BOOT;
    if (!boot || !boot.nonce) return;
    Promise.all([this.api('/sites/'), this.api('/accounts/'), this.api('/domains/')]).then(([sites, accounts, domains]) => {
      const accName = {}; (Array.isArray(accounts) ? accounts : []).forEach(a => { accName[a.account_id] = a.name; });
      this.FLEET = (Array.isArray(sites) ? sites : []).filter(x => !x.removed).map(x => {
        const envs = (x.environments || []).map(e => e.environment === 'Production' ? 'Prod' : e.environment).filter(Boolean).join(' \u00b7 ') || 'Prod';
        const provider = (x.provider || '').replace(/\b[a-z]/g, c => c.toUpperCase());
        return { id: String(x.site_id), name: x.name, provider, account: accName[x.account_id] || '',
          core: x.core || '', visits: x.visits ? Number(x.visits).toLocaleString() : '\u2014',
          storage: this.fmtStorage(x.storage), envs, updates: 0, vuln: 0, owned: true, theme: '',
          backup: 'Direct',
          labels: (Array.isArray(x.labels) ? x.labels : []).map(l => typeof l === 'string' ? l : (l && (l.type || l.text)) || '').filter(Boolean),
          unassigned: !x.account_id,
          plugins: {}, home_url: x.home_url, screenshot: x.screenshot,
          environmentsRaw: x.environments || [] };
      });
      this.ACCOUNTS = (Array.isArray(accounts) ? accounts : []).map(a => ({ id: String(a.account_id), name: a.name,
        users: (a.metrics && a.metrics.users) || 0, sites: (a.metrics && a.metrics.sites) || 0,
        domains: (a.metrics && a.metrics.domains) || 0, plan: a.plan_name || '', owned: true }));
      this.DOMAINS = (Array.isArray(domains) ? domains : []).map(d => ({ id: String(d.domain_id), name: d.name,
        account: '', registrar: d.provider_id ? 'Registrar' : '\u2014', dns: !!d.remote_id,
        expires: '\u2014', auto: null, owned: true }));
      this._hydrated = true;
      // Drop the design's sample jobs; only real dispatched jobs from here on.
      this.setState(st => ({ tick: st.tick, jobs: st.jobs.filter(j => j.real) }));
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
    return [...sites, ...doms,
      { label: 'Open terminal', sub: 'Streamed console on any site', kind: 'command', icon: this.ICONS.terminal, act: 'dock' },
      { label: 'Go to Billing \u2192 Invoices', sub: '', kind: 'command', icon: this.ICONS.billing, act: 'billing' },
      ...(role === 'operator' ? [
        { label: 'Go to Security \u2192 Coverage', sub: 'Fleet audit coverage', kind: 'command', icon: this.ICONS.security, act: 'security' },
        { label: 'Bulk tools on filtered sites\u2026', sub: 'sync \u00b7 deploy defaults \u00b7 https \u00b7 backup', kind: 'command', icon: this.ICONS.sites, act: 'sites' }
      ] : [])];
  },

  realStats(running) {
    return this.FLEET.length + ' sites \u00b7 ' + this.DOMAINS.length + ' domains \u00b7 ' + running + ' jobs running';
  }

});
