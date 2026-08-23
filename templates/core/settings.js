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
    // Customers skip the operator-only sources (defaults/keys/processes) —
    // those routes 403 for them anyway, and their tabs are hidden.
    const isOp = ((window.CC_BOOT && window.CC_BOOT.dcRole) || 'operator') === 'operator';
    const skip = () => Promise.resolve({ code: 'skipped' });
    Promise.allSettled([
      this.api('/configurations/'), this.api('/providers'), isOp ? this.api('/defaults/') : skip(),
      isOp ? this.api('/keys/') : skip(), this.api('/recipes/'), isOp ? this.api('/processes/') : skip()
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
      ['Default email', d.email || '—'],
      ['Timezone', d.timezone || '—'],
      ['Recipes on new site', (d.recipes || []).length ? (d.recipes || []).length + ' recipe(s)' : '—'],
      ['Default users', (d.users || []).length ? (d.users || []).length + ' user(s)' : '—']
    ].map(([k, v]) => ({ k, v, editable: true }));
    const keyRows = set.keys.map(k => ({ name: k.title, fp: 'SHA256:' + (k.fingerprint || '').slice(0, 20) + '…', primary: k.main == 1,
      del: () => { if (!confirm('Delete SSH key "' + k.title + '"? This affects fleet site access.')) return;
        this.api('/keys/' + k.key_id, { method: 'DELETE' }).then(reload).catch(() => {}); } }));
    // Customers manage only their OWN recipes: list() marks non-owned rows
    // user_id "system" (content-stripped) — those get no Edit, and running a
    // public recipe goes through the confirm-run path (dispatch by recipe_id;
    // the code is never shown or inserted). Own/private recipes still insert
    // into the terminal for review.
    const isOp = ((window.CC_BOOT && window.CC_BOOT.dcRole) || 'operator') === 'operator';
    // Cookbook scope: "Mine" (scripts you own — the default for customers) vs
    // "System" (public scripts; operators land here — the fleet library IS
    // their working set). Splitting on the public flag doubles as the
    // ownership split, since non-admins can never own a public recipe.
    const cookScope = s.cookScope || (isOp ? 'system' : 'mine');
    const isSys = r => r.public == 1;
    const scoped = set.recipes.filter(r => (cookScope === 'system') === isSys(r));
    const recipeRows = scoped.map(r => { const canEdit = isOp || r.user_id !== 'system';
      return { name: r.title, vis: r.public == 1 ? 'Public' : 'Private',
        visBg: r.public == 1 ? 'var(--ok-soft)' : 'var(--panel-2)', runs: '', hasRuns: false, canEdit,
        run: () => { this.setState({ dockOpen: true });
          if (r.public == 1) this.confirmRecipeRun(r);
          else this.insertRecipe(r); },
        edit: () => { if (canEdit) this.openRecipe(r); } }; });
    const cookScopeTabs = [['mine', 'Mine'], ['system', 'System']].map(([id, label]) => {
      const n = set.recipes.filter(r => (id === 'system') === isSys(r)).length;
      return { label: label + ' (' + n + ')',
        fg: cookScope === id ? 'var(--ink)' : 'var(--ink-dim)',
        bg: cookScope === id ? 'var(--panel-2)' : 'transparent',
        go: () => this.setState({ cookScope: id }) }; });
    const handRows = set.processes.map(h => ({ name: h.name, updated: (h.updated_at || '').slice(0, 10),
      view: () => this.setState({ procDlgOpen: true, procDlgName: h.name,
        procDlgBody: this.processBodyHtml(h) }),
      edit: (ev) => { ev.stopPropagation(); this.openProcessEdit(h); } }));
    return {
      brandName: s.brandName, onBrandName: e => this.setState({ brandName: e.target.value }),
      brandSwatches, brandSaveLabel: s.copied === 'brand' ? 'Saved ✓' : 'Save branding',
      saveBrand: () => this.saveBranding(),
      provRows, defRows, keyRows, recipeRows, handRows,
      cookScopeTabs,
      cookTabEmpty: !recipeRows.length,
      cookTabEmptyText: cookScope === 'mine'
        ? 'No scripts of your own yet — create one with + New recipe.'
        : 'No system scripts.',
      // recipe editor
      recipeDlgOpen: s.recipeDlgOpen, recipeDlgEditing: !!s.recipeEditId,
      recipeDlgTitle: s.recipeEditId ? 'Edit recipe' : 'New recipe',
      recipeTitle: s.recipeTitle, onRecipeTitle: e => this.setState({ recipeTitle: e.target.value }),
      recipeContent: s.recipeContent, onRecipeContent: e => this.setState({ recipeContent: e.target.value }),
      recipePublicBg: s.recipePublic ? 'var(--brand)' : 'var(--rule)',
      recipePublicJust: s.recipePublic ? 'flex-end' : 'flex-start',
      // Only admins may publish a recipe fleet-wide (the create/update routes
      // force public=0 for everyone else — this hides the dead toggle).
      recipePubShow: isOp,
      toggleRecipePublic: () => this.setState(st => ({ recipePublic: !st.recipePublic })),
      newRecipe: () => this.setState({ recipeDlgOpen: true, recipeEditId: null, recipeTitle: '', recipeContent: '', recipePublic: false }),
      closeRecipeDlg: () => this.setState({ recipeDlgOpen: false }),
      saveRecipe: () => this.saveRecipeReal(),
      deleteRecipe: () => this.deleteRecipeReal(),
      // handbook viewer
      procDlgOpen: s.procDlgOpen, procDlgName: s.procDlgName, procDlgBody: s.procDlgBody,
      closeProcDlg: () => this.setState({ procDlgOpen: false }),
      // handbook editor (v1 parity: raw fetch → PUT the raw object back).
      // The description textarea seeds through a ref keyed by process_id — the
      // DC runtime binds value like defaultValue (the timeline-composer rule).
      procEditOpen: !!s.procEditOpen,
      procName: s.procName, onProcName: e => this.setState({ procName: e.target.value }),
      procTime: s.procTime, onProcTime: e => this.setState({ procTime: e.target.value }),
      procQty: s.procQty, onProcQty: e => this.setState({ procQty: e.target.value }),
      procRepeatLabel: (this.PROC_REPEAT.find(r => r[0] === s.procRepeat) || this.PROC_REPEAT[0])[1],
      procRepeatOpen: s.ddOpen === 'procRepeat',
      procRepeatToggle: e => this.ddToggleAt('procRepeat', e),
      procRepeatOpts: this.PROC_REPEAT.map(([val, label]) => ({ label, mark: s.procRepeat === val ? '✓' : '',
        bg: s.procRepeat === val ? 'var(--brand-soft)' : 'transparent',
        pick: () => this.setState({ procRepeat: val, ddOpen: '', ddQ: '' }) })),
      procDescRef: el => { const id = String((this._procEdit || {}).process_id || '');
        if (el && el._forId !== id) { el._forId = id; el.value = this.state.procDesc || ''; } },
      onProcDesc: e => this.setState({ procDesc: e.target.value }),
      closeProcEdit: () => this.setState({ procEditOpen: false }),
      saveProcess: () => this.saveProcessReal(),
      // site defaults editor (email/timezone + default recipes + default users)
      defDlgOpen: s.defDlgOpen, defEmail: s.defEmail, defTimezone: s.defTimezone,
      onDefEmail: e => this.setState({ defEmail: e.target.value }),
      onDefTimezone: e => this.setState({ defTimezone: e.target.value }),
      openDefaults: () => this.setState({ defDlgOpen: true, defEmail: d.email || '', defTimezone: d.timezone || '',
        defRecipes: (d.recipes || []).map(String),
        defUsers: (d.users || []).map(u => ({ ...u })) }),
      closeDefaults: () => this.setState({ defDlgOpen: false }),
      saveDefaults: () => this.saveDefaultsReal(),
      defRecipeChips: set.recipes.map(r => { const on = (s.defRecipes || []).includes(String(r.recipe_id));
        return { label: r.title, on, bd: on ? 'var(--brand)' : 'var(--rule)',
          bg: on ? 'var(--brand-soft)' : 'var(--paper)', fg: on ? 'var(--brand-ink)' : 'var(--ink-dim)',
          toggle: () => this.setState(st => { const id = String(r.recipe_id); const cur = st.defRecipes || [];
            return { defRecipes: cur.includes(id) ? cur.filter(x => x !== id) : [...cur, id] }; }) }; }),
      defUserRows: (s.defUsers || []).map((u, i) => { const set2 = (k, v) => this.setState(st => ({ defUsers: (st.defUsers || []).map((x, j) => j === i ? { ...x, [k]: v } : x) }));
        // Role is a pick list (v1's role select: lowercase value, capitalized
        // label), anchored-fixed per the dialog-dropdown rule (ddToggleAt).
        const role = u.role || 'administrator';
        const ddKey = 'defRole' + i;
        return { username: u.username || '', email: u.email || '', first_name: u.first_name || '', last_name: u.last_name || '', role,
          onUsername: e => set2('username', e.target.value), onEmail: e => set2('email', e.target.value),
          onFirst: e => set2('first_name', e.target.value), onLast: e => set2('last_name', e.target.value),
          roleLabel: (this.WP_ROLES.find(r => r[0] === role) || [null, role])[1],
          roleOpen: s.ddOpen === ddKey,
          roleToggle: e => this.ddToggleAt(ddKey, e),
          roleOpts: this.WP_ROLES.map(([val, label]) => ({ label, mark: role === val ? '✓' : '',
            bg: role === val ? 'var(--brand-soft)' : 'transparent',
            pick: () => { set2('role', val); this.setState({ ddOpen: '', ddQ: '' }); } })),
          remove: () => this.setState(st => ({ defUsers: (st.defUsers || []).filter((_, j) => j !== i) })) }; }),
      addDefUser: () => this.setState(st => ({ defUsers: [...(st.defUsers || []), { username: '', email: '', first_name: '', last_name: '', role: 'administrator' }] })),
      // provider add/edit
      provShowAdd: true,
      addProvider: () => this.setState({ provDlgOpen: true, provEditId: null, provName: '', provType: '', provCreds: [{ name: '', value: '' }] }),
      ...this.providerDialogVals(s, reload)
    };
  },

  // v1's fixed repeat vocabulary (core.php Edit Process dialog).
  PROC_REPEAT: [
    ['as-needed', 'As needed'], ['1-daily', 'Daily'], ['2-weekly', 'Weekly'],
    ['3-monthly', 'Monthly'], ['4-yearly', 'Yearly']
  ],

  // Fetch the RAW process row (markdown description, repeat keys, role_id) and
  // send it back whole on save with the edited fields applied — v1's contract.
  // Roles ride through untouched: the role vocabulary (captaincore_process_roles)
  // has no REST surface, so v3 doesn't offer a role picker yet.
  openProcessEdit(h) {
    this.api('/processes/' + h.process_id + '/raw').then(p => {
      if (!p || !p.process_id) { this.toast('Could not load process', { kind: 'error' }); return; }
      this._procEdit = p;
      this.setState({ procEditOpen: true, procDlgOpen: false, procName: p.name || '',
        procTime: p.time_estimate || '', procRepeat: p.repeat_interval || 'as-needed',
        procQty: p.repeat_quantity || '', procDesc: p.description || '' });
    }).catch(() => this.toast('Could not load process', { kind: 'error' }));
  },

  saveProcessReal() {
    const p = this._procEdit;
    if (!p) return;
    const name = (this.state.procName || '').trim();
    if (!name) { this.toast('Name is required', { kind: 'error' }); return; }
    const body = { ...p, name, time_estimate: this.state.procTime || '',
      repeat_interval: this.state.procRepeat || 'as-needed',
      repeat_quantity: this.state.procQty || '', description: this.state.procDesc || '' };
    this.setState({ procEditOpen: false });
    const tid = this.toast('Saving process…', { kind: 'loading' });
    this.api('/processes/' + p.process_id, { method: 'PUT', body })
      .then(() => { this.updateToast(tid, 'Process saved', { kind: 'success' }); this.loadSettings(true); })
      .catch(() => this.updateToast(tid, 'Save failed', { kind: 'error' }));
  },

  // v1's roles list (core.php): stored value lowercase, displayed capitalized.
  WP_ROLES: [
    ['subscriber', 'Subscriber'], ['contributor', 'Contributor'], ['author', 'Author'],
    ['editor', 'Editor'], ['administrator', 'Administrator']
  ],

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
    const body = { ...(set.defaults || {}),
      email: (this.state.defEmail || '').trim(), timezone: (this.state.defTimezone || '').trim(),
      recipes: this.state.defRecipes || [],
      users: (this.state.defUsers || []).filter(u => (u.username || '').trim()) };
    this.setState({ defDlgOpen: false });
    const tid = this.toast('Saving site defaults…', { kind: 'loading' });
    this.api('/defaults/global', { method: 'PUT', body })
      .then(() => { this.updateToast(tid, 'Site defaults saved', { kind: 'success' }); this.loadSettings(true); })
      .catch(() => this.updateToast(tid, 'Save failed', { kind: 'error' }));
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
    // Land on the tab the saved recipe lives in, so it's visible right away.
    this.setState({ recipeDlgOpen: false, cookScope: body.public ? 'system' : 'mine' });
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
