// CaptainCore v3 — Sites-list theme/plugin filters (mixin).
// The /sites list carries no theme/plugin data, so these facets resolve
// server-side: GET /site-filters gives the fleet-wide option list
// [{name,title,search,type:'themes'|'plugins'}]; picking one POSTs
// /filters/sites { themes:[…], plugins:[…], logic } → { results:[{site_id}] }
// and we intersect that set with the displayed FLEET. Presence-only for now
// (version/status sub-filters stay client-side/'Any').

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
      pick: () => { this.setState({ [key]: 'Any', ddOpen: '', ddQ: '', ...(extraReset || {}) }); this.applyServerFilter(); } };
    return [anyRow, ...matched.map(o => ({ label: o.title || o.name, name: o.name, badge: '',
      mark: cur === o.name ? '✓' : '', bg: cur === o.name ? 'var(--brand-soft)' : 'transparent',
      pick: () => { this.setState({ [key]: o.name, ddOpen: '', ddQ: '', ...(extraReset || {}) }); this.applyServerFilter(); } }))];
  },

  // Look up the full option object for a selected name (for the POST body).
  filterOptionByName(options, name) {
    return (options || []).find(o => o.name === name) || { name, title: name, search: name };
  },

  applyServerFilter() {
    // Deferred so it reads the just-set state.
    setTimeout(() => {
      const s = this.state;
      const themeSel = s.fTheme && s.fTheme !== 'Any';
      const plugSel = s.fPlugin && s.fPlugin !== 'Any';
      if (!themeSel && !plugSel) { this._filterMatch = null; this.setState({}); return; }
      const body = {
        logic: s.fOp || 'AND',
        themes: themeSel ? [pluck(this.filterOptionByName(this.THEME_OPTIONS, s.fTheme))] : [],
        plugins: plugSel ? [pluck(this.filterOptionByName(this.PLUGIN_OPTIONS, s.fPlugin))] : [],
        core: [], versions: [], statuses: []
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
