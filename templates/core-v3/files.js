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
//
// Every listing and file view is cached for the session (this._fmCache /
// this._fmViewCache, keyed by environment_id + path). Navigation is
// stale-while-revalidate: a cache hit renders instantly with a quiet
// "Refreshing…" note while the ssh roundtrip re-fetches in the background and
// swaps in whatever changed. A background refresh that ERRORS (path deleted)
// replaces the stale listing; one that merely fails to connect keeps showing
// the cached copy.

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
    const cache = this._fmCache = this._fmCache || {};
    const hit = cache[envId + ':' + path];
    const next = this._fm = { envId, path: hit ? hit.path : path,
      entries: hit ? hit.entries : null, loading: !hit, syncing: !!hit, err: '' };
    this.setState({});
    this.api('/environment/' + envId + '/files?path=' + encodeURIComponent(path)).then(res => {
      const ok = res && !res.code && Array.isArray(res.entries);
      if (ok) {
        const stored = { path: res.path || '', entries: res.entries };
        cache[envId + ':' + path] = stored;
        if (stored.path !== path) cache[envId + ':' + stored.path] = stored;
      }
      if (this._fm !== next) return; // navigated away — cache updated anyway
      next.loading = false;
      next.syncing = false;
      if (ok) {
        next.path = res.path || '';
        next.entries = res.entries;
      } else if (res && res.code && res.message) {
        // The server answered with a real error (e.g. the path no longer
        // exists) — a stale listing would mislead, so show the error.
        next.entries = null;
        next.err = res.message;
      } else if (!hit) {
        next.err = 'Could not load files.';
      }
      this.setState({});
    }).catch(() => {
      if (this._fm !== next) return;
      next.loading = false;
      next.syncing = false;
      if (!hit) next.err = 'Could not load files.'; // hit: keep the cached copy
      this.setState({});
    });
  },

  viewFile(name) {
    const fm = this._fm;
    if (!fm) return;
    const p = (fm.path ? fm.path + '/' : '') + name;
    const cache = this._fmViewCache = this._fmViewCache || {};
    const key = fm.envId + ':' + p;
    const hit = cache[key];
    this.setState(hit
      ? { fmViewOpen: true, fmViewName: name, fmViewText: hit.text, fmViewImg: hit.img || '', fmViewNote: hit.note + ' · refreshing…' }
      : { fmViewOpen: true, fmViewName: name, fmViewText: '', fmViewImg: '', fmViewNote: 'Loading…' });
    this.api('/environment/' + fm.envId + '/files?action=view&path=' + encodeURIComponent(p)).then(res => {
      const live = this.state.fmViewOpen && this.state.fmViewName === name;
      if (!res || res.code) {
        if (live && !hit) this.setState({ fmViewNote: (res && res.message) || 'Could not load the file.' });
        else if (live) this.setState({ fmViewNote: hit.note });
        return;
      }
      if (res.image) {
        // Rendered via <img src="data:…"> — a data-URI image (SVG included)
        // cannot execute scripts, so a hostile file on a managed site stays inert.
        const img = 'data:' + res.image + ';base64,' + (res.content_b64 || '');
        const note = this.fmSize(res.size);
        cache[key] = { text: '', img, note };
        if (live) this.setState({ fmViewText: '', fmViewImg: img, fmViewNote: note });
        return;
      }
      if (res.binary) {
        const isImg = /\.(png|jpe?g|gif|webp|svg|ico|bmp|avif)$/i.test(name);
        const note = (isImg ? 'Image too large to preview · ' : 'Binary file — no preview · ') + this.fmSize(res.size);
        cache[key] = { text: '', note };
        if (live) this.setState({ fmViewImg: '', fmViewNote: note });
        return;
      }
      let text = '';
      try {
        text = new TextDecoder().decode(Uint8Array.from(atob(res.content_b64 || ''), c => c.charCodeAt(0)));
      } catch (e) { /* leave empty */ }
      const note = this.fmSize(res.size) + (res.truncated ? ' · showing the first 512 KB' : '');
      cache[key] = { text, note };
      if (live) this.setState({ fmViewText: text, fmViewImg: '', fmViewNote: note });
    }).catch(() => {
      if (this.state.fmViewName !== name) return;
      this.setState({ fmViewNote: hit ? hit.note : 'Could not load the file.' });
    });
  },

  // Delete one regular file (the remote script refuses directories and
  // symlinks). Optimistically drops the row + caches, then re-fetches for truth.
  deleteFmFile(name) {
    const fm = this._fm;
    const real = this._detail;
    if (!fm || !real) return;
    const path = fm.path;
    const p = (path ? path + '/' : '') + name;
    const site = (real.site && real.site.name) || '';
    if (!confirm('Delete ' + p + ' from ' + site + '? The file is removed from the server.')) return;
    this.api('/environment/' + fm.envId + '/files?path=' + encodeURIComponent(p), { method: 'DELETE' }).then(res => {
      if (!res || res.code || !res.deleted) {
        if (this.toast) this.toast((res && res.message) || 'Could not delete ' + name + '.', { kind: 'error' });
        return;
      }
      if (this.toast) this.toast(p + ' deleted.', { kind: 'success' });
      const drop = l => l ? l.filter(en => en.name !== name) : l;
      const cached = (this._fmCache || {})[fm.envId + ':' + path];
      if (cached) cached.entries = drop(cached.entries);
      if (this._fm && this._fm.envId === fm.envId && this._fm.path === path) this._fm.entries = drop(this._fm.entries);
      if (this._fmViewCache) delete this._fmViewCache[fm.envId + ':' + p];
      this.setState({});
      this.loadFiles(path);
    }).catch(() => { if (this.toast) this.toast('Could not delete ' + name + '.', { kind: 'error' }); });
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
      a.type === b.type ? a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }) : (a.type === 'dir' ? -1 : 1));
    const dateFmt = t => t ? new Date(t * 1000).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) : '';
    const relOf = n => (fm && fm.path ? fm.path + '/' : '') + n;
    const rows = sorted.map(en => {
      const isDir = en.type === 'dir';
      const open = () => { if (!real) return;
        if (isDir) this.loadFiles(relOf(en.name));
        else this.viewFile(en.name); };
      return {
        name: en.name,
        isDir,
        icon: isDir
          ? 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'
          : 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6',
        iconFg: isDir ? 'var(--brand-ink)' : 'var(--ink-dim)',
        linkShow: !!en.link,
        sizeLabel: isDir ? '' : this.fmSize(en.size),
        dateLabel: dateFmt(en.mtime),
        open,
        // Delete is files-only (the remote script refuses dirs + symlinks); a
        // symlinked file is excluded here too since the server won't remove it.
        ctx: (e) => this.openCtxMenu(e, [
          { label: isDir ? 'Open folder' : 'View file', act: open },
          { label: 'Copy path', act: () => this.ctxCopy(relOf(en.name), 'path') },
          ...(!isDir && !en.link ? [{ label: 'Delete…', danger: true, act: () => this.deleteFmFile(en.name) }] : [])
        ])
      };
    });
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
      fmSyncing: !!(fm && fm.syncing),
      fmViewOpen: !!s.fmViewOpen,
      closeFmView: () => this.setState({ fmViewOpen: false }),
      fmViewName: s.fmViewName || '',
      fmViewNote: s.fmViewNote || '',
      fmViewBody: s.fmViewText || '',
      fmViewHasBody: !!(s.fmViewText),
      fmViewImg: s.fmViewImg || '',
      fmViewHasImg: !!(s.fmViewImg)
    };
  }

});
