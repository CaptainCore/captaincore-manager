// CaptainCore v3 — dock terminal (mixin). Loads after jobs.js and overrides
// its termRun with a multi-target version modeled on v1's console:
//   · target picker (@) — search + checkbox multi-select over every fleet
//     environment (FLEET[].environmentsRaw carries environment_id/home_url);
//     with nothing selected it falls back to the open site's current env.
//   · cookbook (book icon) — GET /recipes once, search. A PRIVATE recipe
//     inserts its content into the input for review; a PUBLIC (system) recipe
//     opens a Run confirm instead — its code is never shown (v1 parity).
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
    // Session model: one activity row per target set. Same targets → append
    // to that session; a different set (or a session mid-run) starts its own.
    const sessionKey = targets.map(t => String(t.id)).sort().join(',');
    const target = (firstLine.length > 42 ? firstLine.slice(0, 42) + '…' : firstLine) + ' · ' + where;
    const dispatch = () => this.api('/run/code', { method: 'POST',
      body: { environments: targets.map(t => Number(t.id) || t.id), code: cmd } });
    const session = this.sessionJobFor(sessionKey);
    if (session) { this.runInSession(session, { cmd, target, dispatch }); return; }
    const id = this.startJob({
      label: 'run', expand: true, session: sessionKey, where,
      target,
      command: 'run',
      dispatch
    });
    // Echo the first command into the fresh session's scrollback (repeat runs
    // get theirs from runInSession) — pushed synchronously, so it lands ahead
    // of any socket output.
    const job = this._jobObjs && this._jobObjs[id];
    if (job) job.stream.push('$ ' + cmd);
  },

  // ── Scheduling (v1 parity: terminal_schedule) ────────────────
  // POST /scripts/schedule per target environment. The server parses
  // "{date} {time}" in the browser's IANA zone and stores a UTC timestamp, so
  // the payload must carry the zone — never a pre-converted time.
  openTermSchedule() {
    const cmd = (this.state.termCmd || '').trim();
    if (!cmd) return;
    if (!this.resolveTermTargets().length) { this.setState({ tpOpen: true, cookOpen: false }); return; }
    // Default to an hour out, in local time, rounded to the minute.
    const d = new Date(Date.now() + 3600000);
    const pad = n => String(n).padStart(2, '0');
    this.setState({ schedOpen: true, tpOpen: false, cookOpen: false, schedErr: '',
      schedDate: d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()),
      schedTime: pad(d.getHours()) + ':' + pad(d.getMinutes()) });
  },

  confirmTermSchedule() {
    const s = this.state;
    const cmd = (s.termCmd || '').trim();
    const targets = this.resolveTermTargets();
    if (!cmd || !targets.length || !s.schedDate || !s.schedTime) {
      this.setState({ schedErr: 'Pick a date and time.' });
      return;
    }
    this.setState({ schedBusy: true, schedErr: '' });
    const run_at = { date: s.schedDate, time: s.schedTime,
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone };
    Promise.all(targets.map(t => this.api('/scripts/schedule', { method: 'POST',
      body: { environment_id: Number(t.id) || t.id, code: cmd, run_at } })))
      .then(() => {
        this.setState({ schedOpen: false, schedBusy: false, termCmd: '' });
        if (this._termEl) { this._termEl.value = ''; this._termEl.style.height = 'auto'; }
        this.toast('Scheduled on ' + (targets.length === 1 ? targets[0].label : targets.length + ' environments')
          + ' for ' + s.schedDate + ' ' + s.schedTime, { kind: 'success' });
        // The list lives on the environments payload, so refresh the open site.
        if (this._detail && this.state.route === 'site') {
          const id = this._detail.siteId; this._detail = null; this.loadSiteDetail(id);
        }
      })
      .catch(() => this.setState({ schedBusy: false, schedErr: 'Could not schedule. Try again.' }));
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

  // Public (system) recipes never land in the input — picking one opens a
  // confirm naming the recipe + targets, and Run dispatches the stored content
  // directly (v1 parity: v1 runs public recipes immediately behind a confirm).
  // Private recipes keep the insert-for-review behavior.
  confirmRecipeRun(r) {
    if (!this._hydrated) return;
    if (!this.resolveTermTargets().length) {
      this.setState({ tpOpen: true, cookOpen: false });
      this.toast('Pick target environments first', { kind: 'info' });
      return;
    }
    this.setState({ crRecipe: r.recipe_id, cookOpen: false, cookQ: '' });
  },

  // termRun's dispatch path minus the textarea: same session model, same
  // POST /run/code, but dispatched BY RECIPE ID — the server resolves the
  // stored content (public system recipes reach non-admin browsers
  // content-stripped, so the client couldn't send the code anyway) — and the
  // scrollback echoes the recipe NAME, never its code.
  runRecipeNow() {
    const r = (this._recipes || []).find(x => String(x.recipe_id) === String(this.state.crRecipe));
    this.setState({ crRecipe: 0 });
    if (!r || !this._hydrated) return;
    const targets = this.resolveTermTargets();
    if (!targets.length) { this.setState({ tpOpen: true }); return; }
    const where = targets.length === 1 ? targets[0].label : targets.length + ' environments';
    const sessionKey = targets.map(t => String(t.id)).sort().join(',');
    const title = r.title || 'recipe';
    const target = (title.length > 42 ? title.slice(0, 42) + '…' : title) + ' · ' + where;
    const dispatch = () => this.api('/run/code', { method: 'POST',
      body: { environments: targets.map(t => Number(t.id) || t.id), recipe_id: r.recipe_id } });
    const echo = '[recipe] ' + title;
    const session = this.sessionJobFor(sessionKey);
    if (session) { this.runInSession(session, { cmd: echo, target, dispatch }); return; }
    const id = this.startJob({ label: 'run', expand: true, session: sessionKey, where, target, command: 'run', dispatch });
    const job = this._jobObjs && this._jobObjs[id];
    if (job) job.stream.push('$ ' + echo);
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
        pick: () => r.public == 1 ? this.confirmRecipeRun(r) : this.insertRecipe(r) }));
    const crRecipe = s.crRecipe ? recipes.find(x => String(x.recipe_id) === String(s.crRecipe)) : null;
    return {
      termCmd: s.termCmd || '',
      // The textarea has no value binding (the DC runtime treats value like
      // defaultValue), so a FRESH mount starts empty even when state.termCmd
      // holds text. Seed it here or anything that fills the input while the
      // dock is CLOSED is lost when the dock opens — which is exactly what
      // Cookbook → Run did. `_seeded` lives on the DOM node, so it re-seeds on
      // every real remount while surviving the ref(null)/ref(el) churn that
      // fires on ordinary re-renders. Side benefit: a typed draft now survives
      // closing and reopening the dock.
      termRef: (el) => {
        this._termEl = el;
        if (el && el._seeded !== true) {
          el._seeded = true;
          const pending = this.state.termCmd || '';
          if (pending) {
            el.value = pending;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 160) + 'px';
            el.focus();
          }
        }
      },
      onTermCmd: e => {
        this.setState({ termCmd: e.target.value });
        e.target.style.height = 'auto';
        e.target.style.height = Math.min(e.target.scrollHeight, 160) + 'px';
      },
      termRun: () => this.termRun(),
      termRunFg: (s.termCmd || '').trim() ? '#fff' : 'var(--ink-dim)',
      termRunBg: (s.termCmd || '').trim() ? 'var(--brand)' : 'var(--panel-2)',
      // Schedule the typed command instead of running it now.
      termSchedShow: !!(s.termCmd || '').trim(),
      termSchedule: () => this.openTermSchedule(),
      schedOpen: !!s.schedOpen,
      schedDate: s.schedDate || '', onSchedDate: e => this.setState({ schedDate: e.target.value }),
      schedTime: s.schedTime || '', onSchedTime: e => this.setState({ schedTime: e.target.value }),
      schedTargets: (() => { const t = targets; return t.length === 1 ? t[0].label : t.length + ' environments'; })(),
      schedCode: (s.termCmd || '').trim(),
      schedErr: s.schedErr || '', schedErrShow: !!s.schedErr,
      schedBusy: !!s.schedBusy,
      schedLabel: s.schedBusy ? 'Scheduling…' : 'Schedule',
      schedZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      schedClose: () => this.setState({ schedOpen: false }),
      schedConfirm: () => this.confirmTermSchedule(),
      termTargetLabel: targets.length === 0 ? 'Select target'
        : targets.length === 1 ? targets[0].label
        : targets.length + ' environments selected',
      termTargetFg: targets.length ? 'var(--brand-ink)' : 'var(--ink-dim)',
      termTargetBg: targets.length ? 'var(--brand-soft)' : 'var(--panel-2)',
      termTargetClearable: explicit,
      termTargetClear: () => this.setState({ termSel: [] }),
      tpOpen: s.tpOpen, tpQ: s.tpQ || '',
      tpToggle: () => this.setState(st => ({ tpOpen: !st.tpOpen, cookOpen: false, tpQ: '' })),
      tpClose: () => this.setState({ tpOpen: false }),
      onTpQ: e => this.setState({ tpQ: e.target.value }),
      tpCount: selSet.size + ' selected',
      tpResults,
      cookOpen: s.cookOpen, cookQ: s.cookQ || '',
      // Search autofocus rides the shared leadFocusRef (app.js) in the markup.
      cookToggle: () => { this.setState(st => ({ cookOpen: !st.cookOpen, tpOpen: false, cookQ: '' })); this.loadRecipes(); },
      cookClose: () => this.setState({ cookOpen: false }),
      onCookQ: e => this.setState({ cookQ: e.target.value }),
      cookResults,
      cookEmpty: !recipes.length && !this._recipesLoading,
      cookLoading: !!this._recipesLoading,
      // Public-recipe run confirm
      crOpen: !!crRecipe,
      crTitle: crRecipe ? (crRecipe.title || 'recipe') : '',
      crTargets: targets.length === 1 ? targets[0].label : targets.length + ' environments',
      crClose: () => this.setState({ crRecipe: 0 }),
      crGo: () => this.runRecipeNow()
    };
  }

});
