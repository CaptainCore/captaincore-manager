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
    set.cfg.colors = set.cfg.colors || {};
    const colors = set.cfg.colors;
    const toHex = c => /^#[0-9a-f]{6}$/i.test(c || '') ? c : '#3b82c4';
    const brandSwatches = ['primary', 'success', 'warning', 'error', 'accent']
      .filter(k => colors[k]).map(k => ({ k, c: toHex(colors[k]),
        on: e => { set.cfg.colors[k] = e.target.value; this.setState({}); } }));
    const provRows = set.providers.map(p => {
      const connected = (p.credentials || []).length > 0;
      const sub = (connected ? 'Connected' : 'Not connected') + ' · ' + (p.provider || '');
      return { name: p.name || p.provider, sub, dot: connected ? 'var(--ok)' : 'var(--ink-dim)',
        action: 'Verify', canImport: false, editable: true,
        verify: () => { const tid = this.toast('Verifying ' + (p.name || p.provider) + '…', { kind: 'loading' });
          this.api('/providers/' + p.provider_id + '/verify')
            .then(() => { this.updateToast(tid, 'Verified', { kind: 'success' }); reload(); })
            .catch(() => this.updateToast(tid, 'Verification failed', { kind: 'error' })); },
        edit: () => this.openProviderReal(p),
        doImport: () => {} };
    });
    const d = set.defaults || {};
    const defRows = [
      ['Default email', d.email || '—', true],
      ['Timezone', d.timezone || '—', true],
      ['Recipes on new site', (d.recipes || []).length ? (d.recipes || []).length + ' recipe(s)' : '—', false],
      ['Default users', (d.users || []).length ? (d.users || []).length + ' user(s)' : '—', false]
    ].map(([k, v, editable]) => ({ k, v, editable }));
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
      closeProcDlg: () => this.setState({ procDlgOpen: false }),
      // site defaults editor
      defDlgOpen: s.defDlgOpen, defEmail: s.defEmail, defTimezone: s.defTimezone,
      onDefEmail: e => this.setState({ defEmail: e.target.value }),
      onDefTimezone: e => this.setState({ defTimezone: e.target.value }),
      openDefaults: () => this.setState({ defDlgOpen: true, defEmail: d.email || '', defTimezone: d.timezone || '' }),
      closeDefaults: () => this.setState({ defDlgOpen: false }),
      saveDefaults: () => this.saveDefaultsReal(),
      // provider add/edit
      provShowAdd: true,
      addProvider: () => this.setState({ provDlgOpen: true, provEditId: null, provName: '', provType: '', provCreds: [{ name: '', value: '' }] }),
      ...this.providerDialogVals(s, reload)
    };
  },

  PROVIDER_TYPES: [
    ['kinsta', 'Hosting - Kinsta'], ['wpengine', 'Hosting - WP Engine'], ['rocketdotnet', 'Hosting - Rocket.net'],
    ['gridpane', 'Hosting - GridPane'], ['constellix', 'DNS - Constellix'], ['hoverdotcom', 'Domain - Hover.com'],
    ['spaceship', 'Domain - Spaceship'], ['mailgun', 'Email - Mailgun'], ['forwardemail', 'Email - Forward Email'],
    ['fathom', 'Analytics - Fathom'], ['intercom', 'Live chat - Intercom'], ['envato', 'Marketplace - Envato']
  ],

  openProviderReal(p) {
    this.setState({ provDlgOpen: true, provEditId: p.provider_id, provName: p.name || '', provType: p.provider || '',
      provCreds: (p.credentials || []).map(c => ({ name: c.name, value: c.value })).concat((p.credentials || []).length ? [] : [{ name: '', value: '' }]) });
  },

  providerDialogVals(s, reload) {
    const typeLabel = (this.PROVIDER_TYPES.find(t => t[0] === s.provType) || [null, 'Select type…'])[1];
    const setCred = (i, key, val) => this.setState(st => ({ provCreds: (st.provCreds || []).map((c, j) => j === i ? { ...c, [key]: val } : c) }));
    return {
      provDlgOpen: s.provDlgOpen, provDlgEditing: !!s.provEditId,
      provDlgTitle: s.provEditId ? 'Edit provider' : 'Add provider',
      provName: s.provName, onProvName: e => this.setState({ provName: e.target.value }),
      provTypeLabel: typeLabel, provTypeOpen: s.ddOpen === 'provType',
      toggleProvType: () => this.setState(st => ({ ddOpen: st.ddOpen === 'provType' ? '' : 'provType' })),
      closeProvType: () => this.setState({ ddOpen: '' }),
      provTypeOpts: this.PROVIDER_TYPES.map(([val, label]) => ({ label, mark: s.provType === val ? '✓' : '',
        bg: s.provType === val ? 'var(--brand-soft)' : 'transparent',
        pick: () => this.setState({ provType: val, ddOpen: '' }) })),
      provCredRows: (s.provCreds || []).map((c, i) => ({ name: c.name, value: c.value,
        onName: e => setCred(i, 'name', e.target.value), onValue: e => setCred(i, 'value', e.target.value),
        remove: () => this.setState(st => ({ provCreds: (st.provCreds || []).filter((_, j) => j !== i) })) })),
      addProvCred: () => this.setState(st => ({ provCreds: [...(st.provCreds || []), { name: '', value: '' }] })),
      closeProvider: () => this.setState({ provDlgOpen: false }),
      saveProvider: () => this.saveProviderReal(reload),
      deleteProvider: () => this.deleteProviderReal(reload)
    };
  },

  saveProviderReal(reload) {
    const name = (this.state.provName || '').trim();
    const provider = this.state.provType;
    if (!name || !provider) { this.toast('Name and type are required', { kind: 'error' }); return; }
    const credentials = (this.state.provCreds || []).filter(c => (c.name || '').trim()).map(c => ({ name: c.name.trim(), value: c.value }));
    const body = { provider: { name, provider, credentials } };
    const id = this.state.provEditId;
    const tid = this.toast(id ? 'Saving provider…' : 'Adding provider…', { kind: 'loading' });
    const req = id ? this.api('/providers/' + id, { method: 'PUT', body }) : this.api('/providers', { method: 'POST', body });
    this.setState({ provDlgOpen: false });
    req.then(res => { if (res && res.errors) { this.updateToast(tid, (res.errors[0] || 'Save failed'), { kind: 'error' }); return; }
      this.updateToast(tid, 'Provider saved', { kind: 'success' }); this.loadSettings(true); })
      .catch(() => this.updateToast(tid, 'Save failed', { kind: 'error' }));
  },

  deleteProviderReal(reload) {
    const id = this.state.provEditId;
    if (!id || !confirm('Delete this provider?')) return;
    this.setState({ provDlgOpen: false });
    const tid = this.toast('Deleting provider…', { kind: 'loading' });
    this.api('/providers/' + id, { method: 'DELETE' })
      .then(() => { this.updateToast(tid, 'Provider deleted', { kind: 'success' }); this.loadSettings(true); })
      .catch(() => this.updateToast(tid, 'Delete failed', { kind: 'error' }));
  },

  saveDefaultsReal() {
    const set = this._set;
    if (!set) return;
    const body = { ...(set.defaults || {}), email: (this.state.defEmail || '').trim(), timezone: (this.state.defTimezone || '').trim() };
    this.setState({ defDlgOpen: false });
    this.api('/defaults/global', { method: 'PUT', body }).then(() => this.loadSettings(true)).catch(() => {});
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
