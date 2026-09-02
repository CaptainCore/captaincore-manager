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
  // Sub-facet state keys per chip kind. Ver/Status pick a value, VerIs/Is
  // flip that value between IS and IS NOT (server: per-entry include/exclude).
  SUB_KEYS: {
    theme:  { name: 'fTheme',  ver: 'fThemeVer', verIs: 'fThemeVerIs', status: 'fThemeStatus', statusIs: 'fThemeIs', type: 'themes',  label: 'theme' },
    plugin: { name: 'fPlugin', ver: 'fPlugVer',  verIs: 'fPlugVerIs',  status: 'fPlugStatus', statusIs: 'fPlugIs',  type: 'plugins', label: 'plugin' }
  },
  subFacetReset(kind) {
    const k = this.SUB_KEYS[kind];
    return { [k.ver]: 'Any', [k.verIs]: 'IS', [k.status]: 'Any', [k.statusIs]: 'IS' };
  },

  // Version + status option lists for one theme/plugin name. The two
  // endpoints search plugins AND themes by name, so pick the row whose
  // entries carry this chip's type (a theme and a plugin can share a slug).
  loadSubfilters(kind, name) {
    const k = this.SUB_KEYS[kind];
    this._sub = this._sub || {};
    const slot = this._sub[kind] = { name, token: ((this._sub[kind] || {}).token || 0) + 1, vers: [], stats: [] };
    const enc = encodeURIComponent(name);
    const pickRow = (rows, listKey) => { const cands = (Array.isArray(rows) ? rows : []).filter(x => x && x.name === name);
      return cands.find(x => (x[listKey] || []).some(e => e.type === k.type)) || cands[0]; };
    Promise.all([this.api('/filters/' + enc + '/versions/'), this.api('/filters/' + enc + '/statuses/')]).then(([vers, stats]) => {
      if (this._sub[kind] !== slot) return;
      const vRow = pickRow(vers, 'versions'), sRow = pickRow(stats, 'statuses');
      slot.vers = ((vRow && vRow.versions) || []).filter(e => !e.type || e.type === k.type);
      slot.stats = ((sRow && sRow.statuses) || []).filter(e => !e.type || e.type === k.type);
      this.setState({});
    }).catch(() => {});
  },

  // Chip suffix so a negated sub-filter is legible without opening the
  // popover: "Plugin · elementor-pro · ≠ 4.2.3 · inactive".
  subFacetSuffix(s, kind) {
    const k = this.SUB_KEYS[kind]; const out = [];
    if (s[k.ver] && s[k.ver] !== 'Any') out.push((s[k.verIs] === 'IS NOT' ? '≠ ' : '') + s[k.ver]);
    if (s[k.status] && s[k.status] !== 'Any') out.push((s[k.statusIs] === 'IS NOT' ? 'not ' : '') + s[k.status]);
    return out.length ? ' · ' + out.join(' · ') : '';
  },

  // Per-chip popover bindings (spread into the facet row in app.js).
  subFacetVals(s, kind, demoVerCnt, demoStatCnt) {
    const k = this.SUB_KEYS[kind];
    const slot = (this._sub && this._sub[kind] && this._sub[kind].name === s[k.name]) ? this._sub[kind] : null;
    const demo = kind === 'plugin' && !this._hydrated;
    const vers = this._hydrated ? (slot ? slot.vers : []) : (demo ? Object.keys(demoVerCnt || {}).map(x => ({ name: x, count: demoVerCnt[x] })) : []);
    const stats = this._hydrated ? (slot ? slot.stats : []) : (demo ? Object.keys(demoStatCnt || {}).map(x => ({ name: x, count: demoStatCnt[x] })) : []);
    const chips = key => ['IS', 'IS NOT'].map(label => ({ label,
      bg: s[key] === label ? 'var(--panel-2)' : 'transparent',
      fg: s[key] === label ? 'var(--ink)' : 'var(--ink-dim)',
      go: () => { this.setState({ [key]: label, sitesPage: 1 }); if (this._hydrated) this.applyServerFilter(); } }));
    return {
      verOpts: this.subFacetOpts(vers, s[k.ver], k.ver),
      statusOpts: this.subFacetOpts(stats, s[k.status], k.status),
      verIsChips: chips(k.verIs),
      isChips: chips(k.statusIs),
      removeLabel: '✕ Remove ' + k.label + ' filter',
      clearSub: () => { this.setState({ [k.name]: 'Any', ...this.subFacetReset(kind), ddOpen: '', sitesPage: 1 }); if (this._hydrated) this.applyServerFilter(); }
    };
  },

  // Rows for a version/status sub-facet inside a theme/plugin chip popover:
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
      this._sub = this._sub || {};
      // (Re)load the version/status option lists when a chip's name changes.
      [['theme', themeSel, s.fTheme], ['plugin', plugSel, s.fPlugin]].forEach(([kind, sel, name]) => {
        if (sel && (!this._sub[kind] || this._sub[kind].name !== name)) this.loadSubfilters(kind, name);
        if (!sel) delete this._sub[kind];
      });
      if (!themeSel && !plugSel) { this._filterMatch = null; this.setState({}); return; }
      const versions = [], statuses = [];
      [['theme', themeSel], ['plugin', plugSel]].forEach(([kind, sel]) => {
        if (!sel) return;
        const k = this.SUB_KEYS[kind];
        const mode = key => s[key] === 'IS NOT' ? 'exclude' : 'include';
        if (s[k.ver] && s[k.ver] !== 'Any') versions.push({ name: s[k.ver], slug: s[k.name], type: k.type, mode: mode(k.verIs) });
        if (s[k.status] && s[k.status] !== 'Any') statuses.push({ name: s[k.status], slug: s[k.name], type: k.type, mode: mode(k.statusIs) });
      });
      const body = {
        logic: s.fOp || 'AND',
        themes: themeSel ? [pluck(this.filterOptionByName(this.THEME_OPTIONS, s.fTheme))] : [],
        plugins: plugSel ? [pluck(this.filterOptionByName(this.PLUGIN_OPTIONS, s.fPlugin))] : [],
        core: [],
        versions, statuses,
        // Legacy request-wide modes for older servers; each entry also carries
        // its own mode, which DB.php prefers when present.
        version_mode: versions.some(v => v.mode === 'exclude') && versions.every(v => v.mode === 'exclude') ? 'exclude' : 'include',
        status_mode: statuses.some(v => v.mode === 'exclude') && statuses.every(v => v.mode === 'exclude') ? 'exclude' : 'include'
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
