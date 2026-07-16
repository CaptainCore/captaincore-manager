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
      run: () => { this.insertRecipe(r); this.setState({ dockOpen: true }); },
      edit: () => this.openRecipe(r) }));
    const handRows = set.processes.map(h => ({ name: h.name, updated: (h.updated_at || '').slice(0, 10),
      view: () => this.setState({ procDlgOpen: true, procDlgName: h.name,
        procDlgBody: this.processBodyHtml(h) }) }));
    return {
      brandName: s.brandName, onBrandName: e => this.setState({ brandName: e.target.value }),
      brandSwatches, brandSaveLabel: s.copied === 'brand' ? 'Saved ✓' : 'Save branding',
      saveBrand: () => this.saveBranding(),
      provRows, defRows, keyRows, recipeRows, handRows,
      // recipe editor
      recipeDlgOpen: s.recipeDlgOpen, recipeDlgEditing: !!s.recipeEditId,
      recipeDlgTitle: s.recipeEditId ? 'Edit recipe' : 'New recipe',
      recipeTitle: s.recipeTitle, onRecipeTitle: e => this.setState({ recipeTitle: e.target.value }),
      recipeContent: s.recipeContent, onRecipeContent: e => this.setState({ recipeContent: e.target.value }),
      recipePublicBg: s.recipePublic ? 'var(--brand)' : 'var(--rule)',
      recipePublicJust: s.recipePublic ? 'flex-end' : 'flex-start',
      toggleRecipePublic: () => this.setState(st => ({ recipePublic: !st.recipePublic })),
      newRecipe: () => this.setState({ recipeDlgOpen: true, recipeEditId: null, recipeTitle: '', recipeContent: '', recipePublic: false }),
      closeRecipeDlg: () => this.setState({ recipeDlgOpen: false }),
      saveRecipe: () => this.saveRecipeReal(),
      deleteRecipe: () => this.deleteRecipeReal(),
      // handbook viewer
      procDlgOpen: s.procDlgOpen, procDlgName: s.procDlgName, procDlgBody: s.procDlgBody,
      closeProcDlg: () => this.setState({ procDlgOpen: false })
    };
  },

  openRecipe(r) {
    this.setState({ recipeDlgOpen: true, recipeEditId: r.recipe_id, recipeTitle: r.title || '',
      recipeContent: r.content || '', recipePublic: r.public == 1 });
  },

  saveRecipeReal() {
    const title = (this.state.recipeTitle || '').trim();
    if (!title) return;
    const body = { title, content: this.state.recipeContent || '', public: this.state.recipePublic ? 1 : 0 };
    const id = this.state.recipeEditId;
    const req = id ? this.api('/recipes/' + id, { method: 'PUT', body }) : this.api('/recipes', { method: 'POST', body });
    this.setState({ recipeDlgOpen: false });
    req.then(() => this.loadSettings(true)).catch(() => {});
  },

  deleteRecipeReal() {
    const id = this.state.recipeEditId;
    if (!id || !confirm('Delete this recipe?')) return;
    this.setState({ recipeDlgOpen: false });
    this.api('/recipes/' + id, { method: 'DELETE' }).then(() => this.loadSettings(true)).catch(() => {});
  },

  // Wrap the process body in a minimal styled document for the iframe.
  processBodyHtml(h) {
    const body = h.description || h.content || h.body || '<p><em>No content.</em></p>';
    const meta = [h.time_estimate && ('⏱ ' + h.time_estimate), h.repeat_interval && ('↻ ' + h.repeat_interval),
      Array.isArray(h.roles) && h.roles.length && ('👤 ' + h.roles.join(', '))].filter(Boolean).join(' &nbsp;·&nbsp; ');
    return '<!doctype html><meta charset="utf-8"><style>body{font:14px/1.7 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#1a2230;margin:20px;max-width:640px}h1,h2,h3{line-height:1.3}pre,code{font-family:ui-monospace,monospace;background:#f2f4f7;border-radius:6px}pre{padding:12px;overflow:auto}code{padding:1px 4px}.meta{color:#667085;font-size:12px;margin-bottom:16px}</style>'
      + (meta ? '<div class="meta">' + meta + '</div>' : '') + body;
  }

});
