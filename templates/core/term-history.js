// CaptainCore v3 — terminal command history (mixin). Loads after terminal.js.
// Commands stay in this browser: localStorage, one list per user id, so User
// Switching never shows one person's history to another account's session.
// Nothing here is sent to the server. Two tiers:
//   · saved  — localStorage, newest HIST_MAX commands, dropped after HIST_DAYS
//   · memory — this page load only: commands that look like they carry a
//     password, key or token, start with a space (the shell's ignorespace),
//     are very long, or ran while saving is off
// The looks-like-a-secret test is a help, not a guarantee. The History
// popover removes one command, clears everything, or turns saving off.

Object.assign(Component.prototype, {

  HIST_MAX: 200,
  HIST_DAYS: 90,
  HIST_LEN: 4000,

  // What a saved command must not contain.
  HIST_SECRET: [
    /(^|\s)--?\w*pass(word|wd|phrase)?\b/i,
    /pass(word|wd|phrase)?["']?\s*[=:]/i,
    /(secret|token|api[_-]?key|access[_-]?key|private[_-]?key|license[_-]?key|salt)[\w-]*["']?\s*[=:]/i,
    /\b(config set|option (update|add|set|patch)|meta (update|add|set))\b.*(pass|secret|token|key|salt)/i,
    /\buser (update|create|reset-password)\b.*pass/i,
    /authorization\s*:|\bbearer\s+\S/i,
    /\b(mysql|mysqldump|mariadb|mariadb-dump|sshpass)\b.*\s-p\S/i,
    /(^|\s)(-u|--user)[ =]?[^\s:]+:\S/,
    /:\/\/[^\s/:@]+:[^\s/@]+@/,
    /-----BEGIN |AKIA[0-9A-Z]{16}|\b(sk|rk)_live_|\bgh[pousr]_|\bglpat-|\bxox[baprs]-/
  ],

  termHistKey(kind) {
    const uid = (window.CC_BOOT && window.CC_BOOT.chat && window.CC_BOOT.chat.userId) || 0;
    return (kind === 'off' ? 'ccTermHistoryOff:' : 'ccTermHistory:') + uid;
  },

  termHistOff() {
    try { return localStorage.getItem(this.termHistKey('off')) === '1'; } catch (e) { return true; }
  },

  // Saved commands, oldest first. Read fresh each time: other tabs write too.
  termHistSaved() {
    let rows = [];
    try { rows = JSON.parse(localStorage.getItem(this.termHistKey()) || '[]'); } catch (e) {}
    const since = Date.now() - this.HIST_DAYS * 86400000;
    return (Array.isArray(rows) ? rows : [])
      .filter(r => r && typeof r.c === 'string' && r.c.trim() && r.t > since);
  },

  termHistWrite(rows) {
    try {
      if (rows.length) localStorage.setItem(this.termHistKey(), JSON.stringify(rows.slice(-this.HIST_MAX)));
      else localStorage.removeItem(this.termHistKey());
      return true;
    } catch (e) { return false; }
  },

  // Saved and memory together, oldest first, each command once (its last use).
  termHistAll() {
    const by = new Map();
    this.termHistSaved().concat((this._termMem || []).map(r => ({ ...r, mem: true })))
      .sort((a, b) => a.t - b.t)
      .forEach(r => { by.delete(r.c.trim()); by.set(r.c.trim(), r); });
    return [...by.values()];
  },

  termHistAdd(raw) {
    raw = String(raw || '').replace(/\s+$/, '');
    const cmd = raw.trim();
    if (!cmd) return;
    this._termNav = null;
    const now = Date.now();
    const mem = (this._termMem || []).filter(r => r.c.trim() !== cmd);
    const save = !this.termHistOff() && !/^\s/.test(raw) && cmd.length <= this.HIST_LEN
      && !this.HIST_SECRET.some(re => re.test(cmd));
    if (save && this.termHistWrite(this.termHistSaved().filter(r => r.c !== cmd).concat({ c: cmd, t: now }))) {
      this._termMem = mem;
      return;
    }
    // Kept with its leading space, so running it again stays unsaved.
    this._termMem = mem.concat({ c: raw, t: now }).slice(-this.HIST_MAX);
  },

  termHistRemove(cmd) {
    cmd = cmd.trim();
    this._termNav = null;
    this._termMem = (this._termMem || []).filter(r => r.c.trim() !== cmd);
    this.termHistWrite(this.termHistSaved().filter(r => r.c.trim() !== cmd));
    this.setState({});
  },

  termHistClear() {
    this._termNav = null;
    this._termMem = [];
    this.termHistWrite([]);
    this.setState({ histArm: false, histIdx: 0 });
  },

  termHistToggleSave() {
    try {
      if (this.termHistOff()) localStorage.removeItem(this.termHistKey('off'));
      else localStorage.setItem(this.termHistKey('off'), '1');
    } catch (e) {}
    this.setState({});
  },

  // Put text in the command line from code (the textarea has no value
  // binding, see termRef), cursor at the end.
  setTermInput(v) {
    this.setState({ termCmd: v });
    const el = this._termEl;
    if (!el) return;
    el.value = v;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
    el.focus({ preventScroll: true });
    el.setSelectionRange(v.length, v.length);
    el.scrollTop = el.scrollHeight;
  },

  // One step through history. _termNav holds the list as it was when the walk
  // began, where we are in it (list.length is the line being typed), and any
  // edits made along the way, kept per line until something runs (readline).
  termHistMove(dir) {
    const el = this._termEl;
    let nav = this._termNav;
    if (!nav || nav.pos === nav.list.length) {
      const list = this.termHistAll().map(r => r.c);
      nav = this._termNav = { list, pos: list.length, edits: {}, shown: null };
    }
    const to = nav.pos + dir;
    if (to < 0 || to > nav.list.length) return false;
    nav.edits[nav.pos] = el.value;
    nav.pos = to;
    nav.shown = nav.edits[to] != null ? nav.edits[to] : (nav.list[to] || '');
    this.setTermInput(nav.shown);
    return true;
  },

  // ↑ on the first line and ↓ on the last walk history. In between they move
  // the cursor, except right after a recall: then they keep walking, so a
  // multi-line command doesn't stop the walk (←, Home or a click edits it).
  termKeydown(e) {
    const el = e.target;
    if (e.isComposing) return;
    if (e.ctrlKey && !e.metaKey && !e.altKey && !e.shiftKey && e.key.toLowerCase() === 'r') {
      e.preventDefault();
      this.openTermHist();
      return;
    }
    if (e.metaKey || e.ctrlKey || e.altKey || e.shiftKey) return;
    if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
    if (el.selectionStart !== el.selectionEnd) return;
    const up = e.key === 'ArrowUp';
    const nav = this._termNav;
    const walking = nav && nav.shown === el.value && el.selectionStart === el.value.length;
    const edge = up ? el.value.slice(0, el.selectionStart).indexOf('\n') === -1
      : el.value.slice(el.selectionStart).indexOf('\n') === -1;
    if (!walking && !edge) return;
    if (!up && !nav) return;
    // At either end there is nowhere to go: hold still, as a shell does.
    if (this.termHistMove(up ? -1 : 1) || walking) e.preventDefault();
  },

  openTermHist() {
    // Esc closes the popover wherever focus is: a click on one of its links
    // leaves focus on the page, and the shell's Esc would close the dock.
    if (!this._termHistEsc) {
      this._termHistEsc = e => {
        if (e.key !== 'Escape' || !this.state.histOpen) return;
        e.preventDefault(); e.stopPropagation();
        this.closeTermHist();
      };
      document.addEventListener('keydown', this._termHistEsc, true);
    }
    this.setState(st => ({ histOpen: !st.histOpen, histQ: '', histIdx: 0, histArm: false, tpOpen: false, cookOpen: false }));
  },

  closeTermHist() {
    this.setState({ histOpen: false, histArm: false });
    if (this._termEl) this._termEl.focus({ preventScroll: true });
  },

  termHistPick(cmd) {
    this._termNav = null;
    this.setState({ histOpen: false, histArm: false });
    this.setTermInput(cmd);
  },

  // Newest first.
  termHistMatches() {
    const q = (this.state.histQ || '').trim().toLowerCase();
    return this.termHistAll().reverse().filter(r => !q || r.c.toLowerCase().includes(q));
  },

  termHistKeydown(e) {
    const rows = this.termHistMatches();
    const idx = Math.min(this.state.histIdx || 0, Math.max(0, rows.length - 1));
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault(); e.stopPropagation();
      this.setState({ histIdx: Math.max(0, Math.min(rows.length - 1, idx + (e.key === 'ArrowDown' ? 1 : -1))) });
    }
    // Enter only fills the command line. Any Enter: ⌘⏎ would otherwise reach
    // the shell's own handler and run whatever was typed before.
    else if (e.key === 'Enter' && !e.isComposing) {
      e.preventDefault(); e.stopPropagation();
      if (rows[idx]) this.termHistPick(rows[idx].c);
    }
  },

  computeTermHist(s) {
    const open = !!s.histOpen;
    const rows = open ? this.termHistMatches() : [];
    const off = open && this.termHistOff();
    const idx = Math.min(s.histIdx || 0, Math.max(0, rows.length - 1));
    const ago = t => {
      const m = Math.floor((Date.now() - t) / 60000);
      return m < 1 ? 'just now' : m < 60 ? m + 'm ago' : m < 1440 ? Math.floor(m / 60) + 'h ago' : Math.floor(m / 1440) + 'd ago';
    };
    return {
      histOpen: open,
      histToggle: () => this.openTermHist(),
      histClose: () => this.closeTermHist(),
      histQ: s.histQ || '',
      onHistQ: e => this.setState({ histQ: e.target.value, histIdx: 0 }),
      histSearchRef: (el) => {
        if (!el || el._ccHist) return;
        el._ccHist = true;
        el.focus({ preventScroll: true });
        el.addEventListener('keydown', e => this.termHistKeydown(e));
      },
      histRows: rows.map((r, i) => ({
        // One line in the list; the whole command is in the tooltip.
        text: r.c.trim().replace(/\s*\n\s*/g, ' ⏎ '),
        full: r.c.trim(),
        when: ago(r.t),
        mem: !!r.mem,
        bg: i === idx ? 'var(--panel-2)' : 'transparent',
        rowRef: el => { if (el && i === idx && el._ccIdx !== idx + ':' + (s.histQ || '')) { el._ccIdx = idx + ':' + (s.histQ || ''); el.scrollIntoView({ block: 'nearest' }); } else if (el && i !== idx) el._ccIdx = ''; },
        pick: () => this.termHistPick(r.c),
        remove: e => { e.stopPropagation(); this.termHistRemove(r.c); }
      })),
      histEmpty: open && !rows.length,
      histEmptyText: (s.histQ || '').trim() ? 'No matching commands.' : 'No commands yet. The ones you run show up here.',
      histNote: off ? 'Saving is off. New commands last until you reload.' : 'Kept in this browser only.',
      histSaveLabel: off ? 'Turn on saving' : 'Turn off saving',
      histSaveToggle: () => this.termHistToggleSave(),
      histClearShow: open && this.termHistAll().length > 0,
      histClearLabel: s.histArm ? 'Clear everything?' : 'Clear',
      histClearFg: s.histArm ? 'var(--bad)' : 'var(--ink-dim)',
      histClear: () => s.histArm ? this.termHistClear() : this.setState({ histArm: true })
    };
  }

});
