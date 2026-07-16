// CaptainCore v3 — Settings real-data layer (mixin). Admin-only surface.
// Lazy-loads six sources on first Settings render:
//   GET /configurations/  { name, colors{...}, logo_width } — Branding
//   GET /providers        [{provider_id,name,provider,status,credentials}]
//   GET /defaults/        { email, timezone, recipes[], users[] }
//   GET /keys/            [{key_id,title,fingerprint,main}]
//   GET /recipes/         [{recipe_id,title,content,public}]
//   GET /processes/       [{process_id,name,updated_at,…}] — Handbook
// Save branding: PUT /configurations/global (full config, name merged).
// Provider verify: GET /providers/{id}/verify. Key delete: DELETE /keys/{id}
// (+ confirm). Recipe run inserts into the terminal (reuses insertRecipe).

Object.assign(Component.prototype, {

  loadSettings(force) {
    if (this._setLoading || (this._set && !force)) return;
    this._setLoading = true;
    Promise.allSettled([
      this.api('/configurations/'), this.api('/providers'), this.api('/defaults/'),
      this.api('/keys/'), this.api('/recipes/'), this.api('/processes/')
    ]).then(([cfg, prov, def, keys, recipes, procs]) => {
      this._setLoading = false;
      const ok = r => r.status === 'fulfilled' && r.value && !r.value.code ? r.value : null;
      this._set = {
        cfg: ok(cfg) || {},
        providers: Array.isArray(prov.value) ? prov.value : [],
        defaults: (ok(def) && !Array.isArray(def.value)) ? def.value : {},
        keys: Array.isArray(keys.value) ? keys.value : [],
        recipes: Array.isArray(recipes.value) ? recipes.value : [],
        processes: Array.isArray(procs.value) ? procs.value : []
      };
      if (this._set.cfg.name && this.state.brandName !== this._set.cfg.name) this.setState({ brandName: this._set.cfg.name });
      else this.setState({});
    });
  },

  saveBranding() {
    const set = this._set;
    if (!set || !set.cfg) return;
    const cfg = { ...set.cfg, name: (this.state.brandName || '').trim() || set.cfg.name };
    this.setState({ copied: 'brand' });
    clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1600);
    this.api('/configurations/global', { method: 'PUT', body: cfg }).catch(() => {});
  },

  realSettingsVals(s) {
    if (s.route === 'settings' && !this._set && !this._setLoading) setTimeout(() => this.loadSettings(), 0);
    const set = this._set;
    if (!set) return {};
    const reload = () => this.loadSettings(true);
    const colors = (set.cfg && set.cfg.colors) || {};
    const brandSwatches = [['primary', colors.primary], ['success', colors.success], ['warning', colors.warning], ['error', colors.error], ['accent', colors.accent]]
      .filter(([, c]) => c).map(([k, c]) => ({ k, c }));
    const provRows = set.providers.map(p => {
      const connected = (p.credentials || []).length > 0;
      const sub = (connected ? 'Connected' : 'Not connected') + ' · ' + (p.provider || '');
      return { name: p.name || p.provider, sub, dot: connected ? 'var(--ok)' : 'var(--ink-dim)',
        action: 'Verify', canImport: false,
        verify: () => { this.setState({ copied: 'prov' + p.provider_id });
          this.api('/providers/' + p.provider_id + '/verify').then(() => reload()).catch(() => {});
          clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        doImport: () => {} };
    });
    const d = set.defaults || {};
    const defRows = [
      ['Default email', d.email || '—'],
      ['Timezone', d.timezone || '—'],
      ['Recipes on new site', (d.recipes || []).length ? (d.recipes || []).length + ' recipe(s)' : '—'],
      ['Default users', (d.users || []).length ? (d.users || []).length + ' user(s)' : '—']
    ].map(([k, v]) => ({ k, v }));
    const keyRows = set.keys.map(k => ({ name: k.title, fp: 'SHA256:' + (k.fingerprint || '').slice(0, 20) + '…', primary: k.main == 1,
      del: () => { if (!confirm('Delete SSH key "' + k.title + '"? This affects fleet site access.')) return;
        this.api('/keys/' + k.key_id, { method: 'DELETE' }).then(reload).catch(() => {}); } }));
    const recipeRows = set.recipes.map(r => ({ name: r.title, vis: r.public == 1 ? 'Public' : 'Private',
      visBg: r.public == 1 ? 'var(--ok-soft)' : 'var(--panel-2)', runs: '', hasRuns: false,
      run: () => { this.insertRecipe(r); this.setState({ dockOpen: true }); } }));
    const handRows = set.processes.map(h => ({ name: h.name, updated: (h.updated_at || '').slice(0, 10) }));
    return {
      brandName: s.brandName, onBrandName: e => this.setState({ brandName: e.target.value }),
      brandSwatches, brandSaveLabel: s.copied === 'brand' ? 'Saved ✓' : 'Save branding',
      saveBrand: () => this.saveBranding(),
      provRows, defRows, keyRows, recipeRows, handRows
    };
  }

});
