// CaptainCore v3 — File manager (site Inventory → Files leaf) (mixin).
// Read-only browser over GET /environment/{id}/files (path, action=list|view).
// The Manager proxies to `captaincore ssh <target> --script=file-manager`
// through the CLI dispatch server's synchronous /run; the remote script
// resolves every request through realpath and refuses anything outside the
// environment's home directory (symlinks included), so the lock lives on the
// site itself, not in this UI.
//
// Listing state lives on this._fm (envId/path/entries/loading/err); the view
// dialog's strings live in component state. loadFiles() with NO argument is
// the render-time/self-guarded form — it only fetches when the current env has
// nothing loaded, so calling it on every render is cheap (site-detail lazy
// pattern). loadFiles(path) always fetches.

Object.assign(Component.prototype, {

  loadFiles(path) {
    const real = this._detail;
    if (!real || !real.envs) return;
    const e = this.currentEnv(real, this.state);
    if (!e || !e.environment_id) return;
    const envId = e.environment_id;
    const cur = this._fm;
    if (path === undefined) {
      if (cur && cur.envId === envId) return; // already loaded/loading this env
      path = '';
    }
    const next = this._fm = { envId, path, entries: null, loading: true, err: '' };
    this.setState({});
    this.api('/environment/' + envId + '/files?path=' + encodeURIComponent(path)).then(res => {
      if (this._fm !== next) return;
      next.loading = false;
      if (!res || res.code || !Array.isArray(res.entries)) {
        next.err = (res && res.message) || 'Could not load files.';
      } else {
        next.path = res.path || '';
        next.entries = res.entries;
      }
      this.setState({});
    }).catch(() => {
      if (this._fm !== next) return;
      next.loading = false;
      next.err = 'Could not load files.';
      this.setState({});
    });
  },

  viewFile(name) {
    const fm = this._fm;
    if (!fm) return;
    const p = (fm.path ? fm.path + '/' : '') + name;
    this.setState({ fmViewOpen: true, fmViewName: name, fmViewText: '', fmViewNote: 'Loading…' });
    this.api('/environment/' + fm.envId + '/files?action=view&path=' + encodeURIComponent(p)).then(res => {
      if (!this.state.fmViewOpen || this.state.fmViewName !== name) return;
      if (!res || res.code) { this.setState({ fmViewNote: (res && res.message) || 'Could not load the file.' }); return; }
      if (res.binary) { this.setState({ fmViewNote: 'Binary file — no preview · ' + this.fmSize(res.size) }); return; }
      let text = '';
      try {
        text = new TextDecoder().decode(Uint8Array.from(atob(res.content_b64 || ''), c => c.charCodeAt(0)));
      } catch (e) { /* leave empty */ }
      this.setState({ fmViewText: text,
        fmViewNote: this.fmSize(res.size) + (res.truncated ? ' · showing the first 512 KB' : '') });
    }).catch(() => {
      if (this.state.fmViewName === name) this.setState({ fmViewNote: 'Could not load the file.' });
    });
  },

  fmSize(bytes) {
    const n = parseInt(bytes, 10);
    if (isNaN(n)) return '—';
    if (n >= 1073741824) return (n / 1073741824).toFixed(1) + ' GB';
    if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB';
    if (n >= 1024) return Math.round(n / 1024) + ' KB';
    return n + ' B';
  },

  // Static listing for the design/demo surface (pre-hydration).
  FM_DEMO: [
    { name: 'wp-admin', type: 'dir', link: false, size: null, mtime: 1754006400 },
    { name: 'wp-content', type: 'dir', link: false, size: null, mtime: 1754006400 },
    { name: 'wp-includes', type: 'dir', link: false, size: null, mtime: 1754006400 },
    { name: '.htaccess', type: 'file', link: false, size: 612, mtime: 1754006400 },
    { name: 'index.php', type: 'file', link: false, size: 405, mtime: 1754006400 },
    { name: 'wp-config.php', type: 'file', link: false, size: 3358, mtime: 1754006400 },
    { name: 'wp-login.php', type: 'file', link: false, size: 51872, mtime: 1754006400 }
  ],

  // ── Bindings (spread into computeDetail's return) ─────────────
  computeFiles(real, s) {
    const fm = real ? ((this._fm && this._fm.envId === (this.currentEnv(real, s) || {}).environment_id) ? this._fm : null) : null;
    const entries = real ? ((fm && fm.entries) || []) : this.FM_DEMO;
    const path = fm ? fm.path : '';
    const loading = !!real && (!fm || fm.loading);
    const err = (fm && fm.err) || '';
    const sorted = entries.slice().sort((a, b) =>
      a.type === b.type ? a.name.localeCompare(b.name) : (a.type === 'dir' ? -1 : 1));
    const dateFmt = t => t ? new Date(t * 1000).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) : '';
    const rows = sorted.map(en => ({
      name: en.name,
      isDir: en.type === 'dir',
      icon: en.type === 'dir'
        ? 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'
        : 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6',
      iconFg: en.type === 'dir' ? 'var(--brand-ink)' : 'var(--ink-dim)',
      linkShow: !!en.link,
      sizeLabel: en.type === 'dir' ? '' : this.fmSize(en.size),
      dateLabel: dateFmt(en.mtime),
      open: () => { if (!real) return;
        if (en.type === 'dir') this.loadFiles((this._fm && this._fm.path ? this._fm.path + '/' : '') + en.name);
        else this.viewFile(en.name); }
    }));
    const segs = path ? path.split('/') : [];
    const crumbs = [{ label: 'Home', fg: segs.length ? 'var(--brand-ink)' : 'var(--ink)', sep: segs.length > 0,
      go: () => { if (real) this.loadFiles(''); } }];
    segs.forEach((seg, i) => crumbs.push({ label: seg,
      fg: i === segs.length - 1 ? 'var(--ink)' : 'var(--brand-ink)', sep: i < segs.length - 1,
      go: () => { if (real && i < segs.length - 1) this.loadFiles(segs.slice(0, i + 1).join('/')); } }));
    const note = loading ? 'Loading files…' : err ? err : (!rows.length ? 'This directory is empty.' : '');
    return {
      fmCrumbs: crumbs,
      fmRows: rows, fmHasRows: !loading && !err && rows.length > 0,
      fmNotice: !!note, fmNoticeText: note,
      fmUpShow: !!path && !loading,
      fmUp: () => this.loadFiles(segs.slice(0, -1).join('/')),
      fmRefresh: () => { if (real) this.loadFiles(path); },
      fmViewOpen: !!s.fmViewOpen,
      closeFmView: () => this.setState({ fmViewOpen: false }),
      fmViewName: s.fmViewName || '',
      fmViewNote: s.fmViewNote || '',
      fmViewBody: s.fmViewText || '',
      fmViewHasBody: !!(s.fmViewText)
    };
  }

});
