// CaptainCore v3 — Sites-list theme/plugin filters (mixin).
// The /sites list carries no theme/plugin data, so these facets resolve
// server-side: GET /site-filters gives the fleet-wide option list
// [{name,title,search,type:'themes'|'plugins'}]; picking one POSTs
// /filters/sites { themes:[…], plugins:[…], versions:[…], statuses:[…],
// logic, status_mode } → { results:[{site_id}] } and we intersect that set
// with the displayed FLEET. Picking a plugin also loads its version/status
// sub-filter options from GET /filters/<name>/versions|statuses.

Object.assign(Component.prototype, {

  loadSiteFilters() {
    if (this._siteFiltersLoading || this._siteFilters) return;
    this._siteFiltersLoading = true;
    this.api('/site-filters').then(res => {
      this._siteFiltersLoading = false;
      const list = Array.isArray(res) ? res : [];
      this.THEME_OPTIONS = list.filter(x => x && x.type === 'themes');
      this.PLUGIN_OPTIONS = list.filter(x => x && x.type === 'plugins');
      this._siteFilters = true;
      this.setState({});
    }).catch(() => { this._siteFiltersLoading = false; this._siteFilters = true; this.THEME_OPTIONS = []; this.PLUGIN_OPTIONS = []; });
  },

  // Options for a theme/plugin facet dropdown: searchable, capped, 'Any' first.
  filterFacetOpts(options, cur, key, extraReset) {
    const nq = (this.state.ddQ || '').trim().toLowerCase();
    const matched = nq
      ? (options || []).filter(o => (o.search || o.title || o.name || '').toLowerCase().includes(nq)).slice(0, 60)
      : (options || []).slice(0, 60);
    const anyRow = { label: 'Any', name: 'Any', mark: cur === 'Any' || !cur ? '✓' : '', badge: '',
      bg: (cur === 'Any' || !cur) ? 'var(--brand-soft)' : 'transparent',
      pick: () => { this.setState({ [key]: 'Any', ddOpen: '', ddQ: '', sitesPage: 1, ...(extraReset || {}) }); this.applyServerFilter(); } };
    return [anyRow, ...matched.map(o => ({ label: o.title || o.name, name: o.name, badge: '',
      mark: cur === o.name ? '✓' : '', bg: cur === o.name ? 'var(--brand-soft)' : 'transparent',
      pick: () => { this.setState({ [key]: o.name, ddOpen: '', ddQ: '', sitesPage: 1, ...(extraReset || {}) }); this.applyServerFilter(); } }))];
  },

  // Look up the full option object for a selected name (for the POST body).
  filterOptionByName(options, name) {
    return (options || []).find(o => o.name === name) || { name, title: name, search: name };
  },

  // Version/status options for the selected plugin, fetched fleet-wide.
  loadPluginSubfilters(name) {
    const token = this._subToken = (this._subToken || 0) + 1;
    this.PLUGVER_OPTIONS = [];
    this.PLUGSTATUS_OPTIONS = [];
    const enc = encodeURIComponent(name);
    Promise.all([this.api('/filters/' + enc + '/versions/'), this.api('/filters/' + enc + '/statuses/')]).then(([vers, stats]) => {
      if (token !== this._subToken) return;
      const vRow = (Array.isArray(vers) ? vers : []).find(x => x && x.name === name);
      const sRow = (Array.isArray(stats) ? stats : []).find(x => x && x.name === name);
      this.PLUGVER_OPTIONS = (vRow && vRow.versions) || [];
      this.PLUGSTATUS_OPTIONS = (sRow && sRow.statuses) || [];
      this.setState({});
    }).catch(() => {});
  },

  // Rows for a version/status sub-facet inside the plugin chip popover:
  // largest site count first; picking keeps the popover open for stacking.
  subFacetOpts(options, cur, key) {
    const sorted = (options || []).slice().sort((a, b) => (b.count || 0) - (a.count || 0) || String(a.name).localeCompare(String(b.name)));
    const row = (label, badge) => ({ label, badge,
      mark: cur === label || (label === 'Any' && !cur) ? '✓' : '',
      bg: cur === label || (label === 'Any' && !cur) ? 'var(--brand-soft)' : 'transparent',
      pick: () => { this.setState({ [key]: label, sitesPage: 1 }); if (this._hydrated) this.applyServerFilter(); } });
    return [row('Any', ''), ...sorted.map(o => row(String(o.name), (o.count || 0) + ' sites'))];
  },

  applyServerFilter() {
    // Deferred so it reads the just-set state.
    setTimeout(() => {
      const s = this.state;
      const themeSel = s.fTheme && s.fTheme !== 'Any';
      const plugSel = s.fPlugin && s.fPlugin !== 'Any';
      if (plugSel && this._subFor !== s.fPlugin) { this._subFor = s.fPlugin; this.loadPluginSubfilters(s.fPlugin); }
      if (!plugSel) { this._subFor = null; this.PLUGVER_OPTIONS = []; this.PLUGSTATUS_OPTIONS = []; }
      if (!themeSel && !plugSel) { this._filterMatch = null; this.setState({}); return; }
      const verSel = plugSel && s.fPlugVer && s.fPlugVer !== 'Any';
      const statSel = plugSel && s.fPlugStatus && s.fPlugStatus !== 'Any';
      const body = {
        logic: s.fOp || 'AND',
        themes: themeSel ? [pluck(this.filterOptionByName(this.THEME_OPTIONS, s.fTheme))] : [],
        plugins: plugSel ? [pluck(this.filterOptionByName(this.PLUGIN_OPTIONS, s.fPlugin))] : [],
        core: [],
        versions: verSel ? [{ name: s.fPlugVer, slug: s.fPlugin, type: 'plugins' }] : [],
        statuses: statSel ? [{ name: s.fPlugStatus, slug: s.fPlugin, type: 'plugins' }] : [],
        status_mode: s.fPlugIs === 'IS NOT' ? 'exclude' : 'include'
      };
      const token = this._filterToken = (this._filterToken || 0) + 1;
      this._filterMatch = 'loading';
      this.setState({});
      this.api('/filters/sites', { method: 'POST', body }).then(res => {
        if (token !== this._filterToken) return;
        const results = (res && res.results) || [];
        this._filterMatch = new Set(results.map(r => String(r.site_id)));
        this.setState({});
      }).catch(() => { if (token === this._filterToken) { this._filterMatch = new Set(); this.setState({}); } });
    }, 0);

    function pluck(o) { return { name: o.name, title: o.title, search: o.search, type: o.type }; }
  }

});
