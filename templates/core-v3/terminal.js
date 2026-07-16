// CaptainCore v3 — dock terminal (mixin). Loads after jobs.js and overrides
// its termRun with a multi-target version modeled on v1's console:
//   · target picker (@) — search + checkbox multi-select over every fleet
//     environment (FLEET[].environmentsRaw carries environment_id/home_url);
//     with nothing selected it falls back to the open site's current env.
//   · cookbook (book icon) — GET /recipes once, search, click inserts the
//     recipe content into the input for review (v3 never auto-runs).
//   · multiline auto-growing textarea — Enter = newline, ⌘⏎ = run. The DC
//     runtime binds value like defaultValue, so the run path clears the
//     textarea through this._termEl directly.
// Dispatch: POST /run/code { environments: [ids], code } → one daemon token
// streaming a combined `run site-env …` job.

Object.assign(Component.prototype, {

  termEnvList() {
    const out = [];
    this.FLEET.forEach(f => {
      (f.environmentsRaw || []).forEach(e => {
        if (!e || !e.environment_id) return;
        out.push({ id: String(e.environment_id), site: f.name, env: e.environment || 'Production',
          label: f.name + ' · ' + (e.environment || 'Production'), home_url: e.home_url || '' });
      });
    });
    return out;
  },

  // Explicit picks win; otherwise fall back to the open site's current env.
  resolveTermTargets() {
    const sel = this.state.termSel || [];
    if (sel.length) {
      const by = {}; this.termEnvList().forEach(t => { by[t.id] = t; });
      return sel.map(id => by[id]).filter(Boolean);
    }
    const real = this._detail;
    if (real && this.state.route === 'site') {
      const e = this.currentEnv(real, this.state);
      if (e && e.environment_id) {
        return [{ id: String(e.environment_id), site: (real.site && real.site.name) || '',
          env: e.environment, label: ((real.site && real.site.name) || 'site') + ' · ' + e.environment }];
      }
    }
    return [];
  },

  termRun() {
    const cmd = (this.state.termCmd || '').trim();
    if (!cmd || !this._hydrated) return;
    const targets = this.resolveTermTargets();
    if (!targets.length) { this.setState({ tpOpen: true, cookOpen: false }); return; }
    const where = targets.length === 1 ? targets[0].label : targets.length + ' environments';
    const firstLine = cmd.split('\n')[0];
    this.setState({ termCmd: '' });
    if (this._termEl) { this._termEl.value = ''; this._termEl.style.height = 'auto'; }
    this.startJob({
      label: 'run', expand: true,
      target: (firstLine.length > 42 ? firstLine.slice(0, 42) + '…' : firstLine) + ' · ' + where,
      command: 'run',
      dispatch: () => this.api('/run/code', { method: 'POST',
        body: { environments: targets.map(t => Number(t.id) || t.id), code: cmd } })
    });
  },

  loadRecipes() {
    if (this._recipes || this._recipesLoading) return;
    this._recipesLoading = true;
    this.api('/recipes').then(r => {
      this._recipes = Array.isArray(r) ? r : [];
      this._recipesLoading = false;
      this.setState({});
    }).catch(() => { this._recipes = []; this._recipesLoading = false; this.setState({}); });
  },

  insertRecipe(recipe) {
    const content = recipe.content || '';
    this.setState({ termCmd: content, cookOpen: false, cookQ: '' });
    const el = this._termEl;
    if (el) {
      el.value = content;
      el.style.height = 'auto';
      el.style.height = Math.min(el.scrollHeight, 160) + 'px';
      el.focus();
    }
  },

  computeTermVals(s) {
    const targets = this._hydrated ? this.resolveTermTargets() : [];
    const selSet = new Set(s.termSel || []);
    const explicit = selSet.size > 0;
    const nq = (s.tpQ || '').trim().toLowerCase();
    const envs = (s.tpOpen && this._hydrated) ? this.termEnvList() : [];
    const tpResults = (nq ? envs.filter(t => (t.label + ' ' + t.home_url).toLowerCase().includes(nq)) : envs)
      .slice(0, 60).map(t => ({ ...t,
        mark: selSet.has(t.id) ? '✓' : '',
        bg: selSet.has(t.id) ? 'var(--brand-soft)' : 'transparent',
        toggle: () => this.setState(st => {
          const sel = new Set(st.termSel || []);
          sel.has(t.id) ? sel.delete(t.id) : sel.add(t.id);
          return { termSel: [...sel] };
        }) }));
    const cq = (s.cookQ || '').trim().toLowerCase();
    const recipes = this._recipes || [];
    const cookResults = (cq ? recipes.filter(r => (r.title || '').toLowerCase().includes(cq)) : recipes)
      .slice(0, 60).map(r => ({ ...r,
        sub: r.public == 1 ? 'public recipe' : 'private',
        pick: () => this.insertRecipe(r) }));
    return {
      termCmd: s.termCmd || '',
      termRef: (el) => { this._termEl = el; },
      onTermCmd: e => {
        this.setState({ termCmd: e.target.value });
        e.target.style.height = 'auto';
        e.target.style.height = Math.min(e.target.scrollHeight, 160) + 'px';
      },
      termRun: () => this.termRun(),
      termRunFg: (s.termCmd || '').trim() ? 'var(--brand-ink)' : 'var(--ink-dim)',
      termRunBg: (s.termCmd || '').trim() ? 'var(--brand-soft)' : 'transparent',
      termTargetLabel: targets.length === 0 ? 'Select target'
        : targets.length === 1 ? targets[0].label
        : targets.length + ' environments selected',
      termTargetFg: targets.length ? 'var(--brand-ink)' : 'var(--ink-dim)',
      termTargetClearable: explicit,
      termTargetClear: () => this.setState({ termSel: [] }),
      tpOpen: s.tpOpen, tpQ: s.tpQ || '',
      tpToggle: () => this.setState(st => ({ tpOpen: !st.tpOpen, cookOpen: false, tpQ: '' })),
      tpClose: () => this.setState({ tpOpen: false }),
      onTpQ: e => this.setState({ tpQ: e.target.value }),
      tpCount: selSet.size + ' selected',
      tpResults,
      cookOpen: s.cookOpen, cookQ: s.cookQ || '',
      cookToggle: () => { this.setState(st => ({ cookOpen: !st.cookOpen, tpOpen: false, cookQ: '' })); this.loadRecipes(); },
      cookClose: () => this.setState({ cookOpen: false }),
      onCookQ: e => this.setState({ cookQ: e.target.value }),
      cookResults,
      cookEmpty: !recipes.length && !this._recipesLoading,
      cookLoading: !!this._recipesLoading
    };
  }

});
