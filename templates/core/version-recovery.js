// CaptainCore v3 — Version & Recovery real-data layer (mixin).
// Quicksaves (git version history + update logs merged), restic backups
// (lazy tree + preview + selected-file download), snapshots (tokenized
// links), and the site timeline (process logs). Loaders cache onto
// this._detail; computeDetail() consults them when `real` is set.

Object.assign(Component.prototype, {

  fmtEpoch(epoch) {
    const n = Number(epoch);
    if (!n) return '';
    const d = new Date(n * 1000);
    const opts = { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
    if (d.getFullYear() !== new Date().getFullYear()) opts.year = 'numeric';
    return d.toLocaleString(undefined, opts);
  },

  // ── Visual captures (Captures tab) ────────────────────────────
  // GET /site/{id}/{env}/captures → [{capture_id, created_at_friendly,
  // git_commit, pages:[{name,image,image_url}]}] newest first. Cached per
  // environment on this._detail.caps; the render effect self-guards reloads.
  loadCaptures() {
    const real = this._detail;
    if (!real) return;
    real.caps = real.caps || {};
    const env = this.state.env;
    if (real.caps[env] !== undefined) return;
    real.caps[env] = null; // loading
    this.api('/site/' + real.siteId + '/' + env.toLowerCase() + '/captures')
      .then(rows => { if (this._detail === real) { real.caps[env] = Array.isArray(rows) ? rows : []; this.setState({ tick: this.state.tick }); } })
      .catch(() => { if (this._detail === real) { real.caps[env] = []; this.setState({ tick: this.state.tick }); } });
  },

  // Props for the Captures tab + the Overview teaser (spread into computeDetail).
  computeCaptures(real, s) {
    const capsRaw = real && real.caps ? real.caps[s.env] : undefined;
    const loading = !!real && (capsRaw === undefined || capsRaw === null);
    const caps = Array.isArray(capsRaw) ? capsRaw : [];
    const limit = s.capLimit || 60;
    const selId = s.capSel && caps.some(c => String(c.capture_id) === String(s.capSel))
      ? String(s.capSel) : (caps[0] ? String(caps[0].capture_id) : '');
    const sel = caps.find(c => String(c.capture_id) === selId) || null;
    const curEnv = real ? this.currentEnv(real, s) : null;
    const teaserN = curEnv && Number(curEnv.captures) ? Number(curEnv.captures) : 0;
    return {
      capRows: caps.slice(0, limit).map(c => {
        const n = Array.isArray(c.pages) ? c.pages.length : 0;
        return {
          when: c.created_at_friendly || c.created_at,
          hash: String(c.git_commit || '').slice(0, 7),
          pagesN: n + (n === 1 ? ' page' : ' pages'),
          bg: String(c.capture_id) === selId ? 'var(--brand-soft)' : 'transparent',
          fg: String(c.capture_id) === selId ? 'var(--brand-ink)' : 'var(--ink)',
          pick: () => this.setState({ capSel: String(c.capture_id) })
        };
      }),
      capMoreShow: caps.length > limit,
      capMoreLabel: 'Show older (' + (caps.length - limit).toLocaleString() + ' more)',
      capMore: () => this.setState({ capLimit: limit + 200 }),
      capCount: caps.length ? caps.length.toLocaleString() + ' captures' : '',
      capLoading: loading,
      capEmpty: !loading && caps.length === 0,
      capHasSel: !!sel,
      capSelWhen: sel ? (sel.created_at_friendly || '') : '',
      capSelHash: sel && sel.git_commit ? String(sel.git_commit).slice(0, 7) : '',
      capPages: sel ? (Array.isArray(sel.pages) ? sel.pages : []).map(p => ({
        name: p.name || '/',
        url: p.image_url || '',
        open: () => { if (p.image_url) this.safeOpen(p.image_url); },
        // Older captures can be pruned from remote storage — swap the broken
        // image for a quiet note instead of the browser's broken-image icon.
        err: (e) => { const el = e.target; if (el._swapped) return; el._swapped = true; el.style.display = 'none';
          const d = document.createElement('div');
          d.textContent = 'Image no longer available in storage';
          d.style.cssText = 'padding:26px;text-align:center;font:400 var(--fs-125) var(--sans);color:var(--ink-dim)';
          el.parentNode.appendChild(d); }
      })) : [],
      capTeaserShow: teaserN > 0,
      capTeaserLabel: teaserN.toLocaleString() + (teaserN === 1 ? ' capture' : ' captures'),
      goCaptures: () => this.goSiteTab('captures')
    };
  },

  // ── Quicksaves (Versions tab) ─────────────────────────────────
  // Keyed by environment: the old `qs !== undefined` guard alone meant the
  // Production list stayed on screen (and in the Versions stat tile) after a
  // switch to Staging.
  loadQuicksaves() {
    const real = this._detail;
    if (!real) return;
    const env = this.state.env;
    if (real.qs !== undefined && real.qsEnv === env) return;
    real.qs = null; // loading
    real.qsEnv = env;
    const bump = () => this.setState({ tick: this.state.tick });
    const q = '?site_id=' + real.siteId + '&environment=' + encodeURIComponent(env);
    Promise.all([
      this.api('/quicksaves/' + q).catch(() => []),
      this.api('/update-logs/' + q).catch(() => [])
    ]).then(([qs, logs]) => {
      if (this._detail !== real || real.qsEnv !== env) return;
      const updatesByHash = {};
      (Array.isArray(logs) ? logs : []).forEach(l => {
        if (l && l.hash_after) updatesByHash[l.hash_after] = l;
      });
      real.qs = (Array.isArray(qs) ? qs : []).map(row => {
        const upd = updatesByHash[row.hash];
        const comps = (Number(row.theme_count) || 0) + (Number(row.plugin_count) || 0);
        return {
          hash: row.hash,
          hashShort: String(row.hash || '').slice(0, 7),
          kind: upd ? 'Update' : 'Quicksave',
          desc: upd
            ? ((Number(upd.themes_changed) || 0) + (Number(upd.plugins_changed) || 0)) + ' components updated' + (upd.status === 'failed' ? ' · failed' : '')
            : 'WP ' + (row.core || '?') + ' · ' + comps + ' components',
          files: '',
          when: this.fmtEpoch(row.created_at),
          summary: 'WP ' + (row.core || '?') + ' · ' + (row.theme_count || 0) + ' themes · ' + (row.plugin_count || 0) + ' plugins',
          more: 0,
          _raw: row
        };
      });
      bump();
    });
  },

  loadQuicksaveDetail(hash) {
    const real = this._detail;
    if (!real || !hash) return;
    real.qsDetail = real.qsDetail || {};
    real.qsFiles = real.qsFiles || {};
    if (real.qsDetail[hash] === undefined) {
      real.qsDetail[hash] = null;
      const q = '?site_id=' + real.siteId + '&environment=' + encodeURIComponent(this.state.env);
      this.api('/quicksaves/' + hash + q)
        .then(d => { real.qsDetail[hash] = d || {}; this.setState({ tick: this.state.tick }); })
        .catch(() => { real.qsDetail[hash] = {}; this.setState({ tick: this.state.tick }); });
    }
    if (real.qsFiles[hash] === undefined) {
      real.qsFiles[hash] = null;
      const q = '?site_id=' + real.siteId + '&environment=' + encodeURIComponent(this.state.env);
      this.api('/quicksaves/' + hash + '/changed' + q)
        .then(text => {
          const lines = (typeof text === 'string' ? text.trim() : '').split('\n').filter(Boolean);
          real.qsFiles[hash] = lines.map(line => {
            const parts = line.split('\t');
            return { st: (parts[0] || 'M').charAt(0), path: parts[1] || parts[0], add: '', del: '', _line: line };
          });
          this.setState({ tick: this.state.tick });
        })
        .catch(() => { real.qsFiles[hash] = []; this.setState({ tick: this.state.tick }); });
    }
  },

  loadQsDiff(hash, path) {
    const real = this._detail;
    if (!real || !hash || !path) return;
    real.qsDiff = real.qsDiff || {};
    const key = hash + '|' + path;
    if (real.qsDiff[key] !== undefined) return;
    real.qsDiff[key] = null;
    const q = '?site_id=' + real.siteId + '&environment=' + encodeURIComponent(this.state.env) + '&file=' + encodeURIComponent(path);
    this.api('/quicksaves/' + hash + '/filediff' + q)
      .then(text => {
        let raw = typeof text === 'string' ? text : '';
        // The CLI's --html flag entity-escapes the diff; we render as text nodes.
        raw = raw.replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
        real.qsDiff[key] = raw.split('\n').map(l =>
          [l.startsWith('+') ? 'add' : l.startsWith('-') ? 'del' : 'ctx', l]);
        this.setState({ tick: this.state.tick });
      })
      .catch(() => { real.qsDiff[key] = [['ctx', '(failed to load diff)']]; this.setState({ tick: this.state.tick }); });
  },

  realQuicksaves(real) {
    if (real.qs === null) return [{ hash: '', hashShort: '…', kind: 'Quicksave', desc: 'Loading quicksaves…', files: '', when: '', summary: '', more: 0 }];
    return real.qs || [];
  },

  realQsFiles(real, s) {
    const files = (real.qsFiles && real.qsFiles[s.qsDialog]) || null;
    if (files === null) return [{ path: 'Loading changed files…', st: 'M', add: '', del: '', diff: [['ctx', '']] }];
    const diffs = real.qsDiff || {};
    return files.map(f => {
      const d = diffs[s.qsDialog + '|' + f.path];
      return { ...f, diff: d === null ? [['ctx', 'Loading diff…']] : (d || null) };
    });
  },

  realQsComponents(real, s, kind) {
    const d = real.qsDetail && real.qsDetail[s.qsDialog];
    if (!d) return [{ kind, name: 'Loading…', from: '', to: '', status: '' }];
    const live = (kind === 'theme' ? d.themes : d.plugins) || [];
    const deleted = (kind === 'theme' ? d.themes_deleted : d.plugins_deleted) || [];
    const rows = live.map(c => ({
      kind, name: c.title || c.name || '',
      from: '', to: c.version || '',
      status: c.status || '',
      added: !!c.new,
      updated: !!(c.changed_version || c.changed_status),
      viewFile: null,
      _slug: c.name || ''
    }));
    return rows.concat(deleted.map(c => ({
      kind, name: c.title || c.name || '',
      from: c.version || '', to: '',
      status: c.status || '', deleted: true, viewFile: null, _slug: c.name || ''
    })));
  },

  realNewQuicksave(real) {
    const name = (real.site && real.site.name) || '';
    this.startJob({
      label: 'quicksave', target: name, command: 'quick_backup', siteId: real.siteId,
      dispatch: () => this.api('/sites/cli', { method: 'POST', body: { post_id: Number(real.siteId), command: 'quick_backup', environment: this.state.env } }),
      onFinish: () => { real.qs = undefined; this.loadQuicksaves(); }
    });
  },

  realRollbackAll(real, hash) {
    const name = (real.site && real.site.name) || '';
    this.startJob({
      label: 'rollback', target: name + ' → ' + String(hash).slice(0, 7), command: 'rollback', siteId: real.siteId,
      dispatch: () => this.api('/quicksaves/' + hash + '/rollback', { method: 'POST',
        body: { site_id: Number(real.siteId), environment: this.state.env, version: 'this', type: 'all' } }),
      onFinish: () => { real.qs = undefined; this.loadQuicksaves(); }
    });
  },

  realRollbackComponent(real, hash, comp, version) {
    const name = (real.site && real.site.name) || '';
    this.startJob({
      label: 'rollback', target: (comp._slug || comp.name) + ' → ' + version + ' (' + String(hash).slice(0, 7) + ')',
      command: 'rollback', siteId: real.siteId,
      dispatch: () => this.api('/quicksaves/' + hash + '/rollback', { method: 'POST',
        body: { site_id: Number(real.siteId), environment: this.state.env, version, type: comp.kind, value: comp._slug || comp.name } })
    });
  },

  realRestoreFile(real, hash, path) {
    this.startJob({
      label: 'restore-file', target: path.split('/').pop() + ' from ' + String(hash).slice(0, 7),
      command: 'quicksave_file_restore', siteId: real.siteId,
      dispatch: () => this.api('/sites/cli', { method: 'POST',
        body: { post_id: Number(real.siteId), environment: this.state.env, hash, command: 'quicksave_file_restore', value: path } })
    });
  },

  realSandbox(real, hash) {
    this.api('/quicksaves/' + hash + '/sandbox-token', { method: 'POST',
      body: { site_id: Number(real.siteId), environment: this.state.env.toLowerCase(), include_database: true } })
      .then(res => {
        if (res && res.blueprint_url) window.open('https://playground.wordpress.net/?blueprint-url=' + encodeURIComponent(res.blueprint_url), '_blank');
        else console.warn('sandbox-token unexpected response', res);
      })
      .catch(err => console.warn('sandbox failed', err));
  },

  // ── Restic backups ────────────────────────────────────────────
  // Also feeds the Overview "Backups" stat tile, which is why the overview
  // prefetches this: the daemon answers off a cached list.json (~1s for a
  // 2,000-snapshot repo), so the count + retention start are cheap enough to
  // show without opening the tab. Keyed by environment like loadQuicksaves.
  loadBackups() {
    const real = this._detail;
    if (!real) return;
    const env = this.state.env;
    if (real.backups !== undefined && real.backupsEnv === env) return;
    real.backups = null;
    real.backupsEnv = env;
    real.backupsSince = '';
    this.api('/site/' + real.siteId + '/' + env.toLowerCase() + '/backups')
      .then(rows => {
        if (this._detail !== real || real.backupsEnv !== env) return;
        const list = Array.isArray(rows) ? rows : [];
        real.backups = list.map(b => ({
          id: b.id, idShort: String(b.id || '').slice(0, 8),
          when: b.time ? new Date(b.time).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '',
          size: '', files: '', _raw: b
        }));
        // Retention start = the oldest snapshot still in the repo. Scan for the
        // min rather than trusting list order (Site::backups usorts with a bool
        // comparator, so the ordering is newest-first only by accident) and
        // parse with Date — the times carry mixed offsets (Z and -04:00), so a
        // string compare would pick the wrong one.
        let oldest = 0;
        list.forEach(b => {
          const t = b && b.time ? Date.parse(b.time) : NaN;
          if (!isNaN(t) && (oldest === 0 || t < oldest)) oldest = t;
        });
        real.backupsSince = oldest ? new Date(oldest).toLocaleDateString(undefined, { month: 'short', year: 'numeric' }) : '';
        this.setState({ tick: this.state.tick });
      })
      .catch(() => {
        if (this._detail !== real || real.backupsEnv !== env) return;
        real.backups = []; this.setState({ tick: this.state.tick });
      });
  },

  loadBackupTree(backupId) {
    const real = this._detail;
    if (!real || !backupId) return;
    real.bkTree = real.bkTree || {};
    if (real.bkTree[backupId] !== undefined) return;
    real.bkTree[backupId] = null;
    const bump = () => this.setState({ tick: this.state.tick });
    this.api('/sites/' + real.siteId + '/' + this.state.env.toLowerCase() + '/backups/' + backupId)
      .then(url => {
        if (typeof url !== 'string' || url.indexOf('http') !== 0) throw new Error('unexpected backup-get response');
        return fetch(url).then(r => r.json());
      })
      .then(data => { real.bkTree[backupId] = { files: (data && data.files) || [], omitted: (data && data.omitted) || [] }; bump(); })
      .catch(() => { real.bkTree[backupId] = { files: [], omitted: [], error: true }; bump(); });
  },

  // Map the daemon's tree nodes {id,name,size,count,type,path,children,ext}
  // into the design's node shape {p, meta, dir/file, prev, cnt, kb, children}.
  realBkTree(real, s) {
    const bucket = real.bkTree && real.bkTree[s.bkDialog];
    if (bucket === null || bucket === undefined) return [{ p: 'Loading file tree…', meta: '', omitted: true, dir: true }];
    if (bucket.error) return [{ p: '(failed to load file tree)', meta: '', omitted: true, dir: true }];
    const textExt = /^(php|js|css|html|htm|txt|md|json|xml|yml|yaml|ini|log|sql|htaccess|conf|sh|lock)$/i;
    const mapNode = n => {
      const dir = n.type === 'dir';
      const kb = (Number(n.size) || 0) / 1024;
      const dirMeta = [];
      if (Number(n.count) > 1) dirMeta.push(Number(n.count).toLocaleString() + ' files');
      if (Number(n.size) > 0) dirMeta.push(this.fmtStorage(n.size));
      return {
        p: n.path + (dir ? '/' : ''),
        meta: dir ? dirMeta.join(' · ') : this.fmtStorage(n.size),
        dir, file: !dir,
        prev: !dir && (textExt.test(n.ext || '') || /^(png|jpg|jpeg|gif|webp|svg)$/i.test(n.ext || '')),
        cnt: Number(n.count) || 1, kb,
        _ext: n.ext || '',
        children: dir ? (n.children || []).map(mapNode) : undefined
      };
    };
    const rows = (bucket.files || []).map(mapNode);
    const omitted = bucket.omitted;
    if (Array.isArray(omitted)) omitted.forEach(o => rows.push({ p: (typeof o === 'string' ? o : (o && o.path) || 'omitted') + '/', meta: 'omitted — still restorable', omitted: true, dir: true }));
    return rows;
  },

  b64url(path) {
    return btoa(unescape(encodeURIComponent(path))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  },

  loadBackupPreview(path) {
    const real = this._detail;
    const backupId = this.state.bkDialog;
    if (!real || !backupId || !path) return;
    real.bkPreview = real.bkPreview || {};
    const key = backupId + '|' + path;
    if (real.bkPreview[key] !== undefined) return;
    real.bkPreview[key] = null;
    this.api('/sites/' + real.siteId + '/' + this.state.env.toLowerCase() + '/backups/' + backupId + '?file=' + this.b64url(path))
      .then(text => { real.bkPreview[key] = typeof text === 'string' ? text : JSON.stringify(text, null, 2); this.setState({ tick: this.state.tick }); })
      .catch(() => { real.bkPreview[key] = '(failed to load preview)'; this.setState({ tick: this.state.tick }); });
  },

  realBkPreviewLines(real, s) {
    const content = real.bkPreview && real.bkPreview[s.bkDialog + '|' + s.bkPreview];
    if (content === null) return [{ text: 'Loading ' + s.bkPreview + '…' }];
    if (content === undefined) return [{ text: '(no preview loaded)' }];
    return content.split('\n').slice(0, 400).map(text => ({ text }));
  },

  realBackupNow(real) {
    const name = (real.site && real.site.name) || '';
    this.startJob({
      label: 'backup', target: name, command: 'backup', siteId: real.siteId,
      dispatch: () => this.api('/sites/cli', { method: 'POST', body: { post_id: Number(real.siteId), command: 'backup', environment: this.state.env } }),
      onFinish: () => { real.backups = undefined; this.loadBackups(); }
    });
  },

  realBackupDownload(real, s, topSel, flatAll) {
    const files = [], directories = [];
    topSel.forEach(p => {
      const n = flatAll.find(x => x.p === p);
      if (!n || n.omitted) return;
      (n.dir ? directories : files).push(p.replace(/\/$/, ''));
    });
    const email = (window.CC_BOOT || {}).userEmail || '';
    const jobId = this.startJob({
      label: 'backup-download', target: (files.length + directories.length) + ' items → ' + email,
      command: 'backup_download', siteId: real.siteId
    });
    const job = this._jobObjs[jobId];
    this.api('/sites/cli', { method: 'POST', body: {
      post_id: Number(real.siteId), command: 'backup_download', environment: s.env,
      value: { files: JSON.stringify(files), directories: JSON.stringify(directories), backup_id: s.bkDialog }
    } }).then(() => {
      job.stream.push('Download requested — a link will be emailed to ' + email + ' when ready.');
      this.finishJob(job, 'done');
    }).catch(err => { job.stream.push('Error: ' + (err && err.message || err)); this.finishJob(job, 'error'); });
    this.setState({ bkSel: {} });
  },

  // "Restore" from a backup = point-in-time snapshot via --rollback={date}.
  realBackupRestore(real, s) {
    const b = (real.backups || []).find(x => x.id === s.bkDialog) || {};
    const email = (window.CC_BOOT || {}).userEmail || '';
    const date = b._raw && b._raw.time ? b._raw.time : '';
    this.startJob({
      label: 'snapshot', target: 'point-in-time from ' + (b.idShort || 'backup') + ' → ' + email,
      command: 'snapshot', siteId: real.siteId,
      dispatch: () => this.api('/sites/cli', { method: 'POST', body: {
        post_id: Number(real.siteId), command: 'snapshot', environment: s.env,
        value: email, date, notes: 'Point-in-time snapshot from backup ' + b.id
      } }),
      onFinish: () => { real.snapshots = undefined; this.loadSnapshots(); }
    });
  },

  // ── Snapshots ─────────────────────────────────────────────────
  loadSnapshots() {
    const real = this._detail;
    if (!real || real.snapshots !== undefined) return;
    real.snapshots = null;
    this.api('/site/' + real.siteId + '/snapshots')
      .then(byEnv => {
        const rows = (byEnv && byEnv[this.state.env]) || [];
        real.snapshots = rows.map(r => this.mapSnapshot(real, r));
        this.setState({ tick: this.state.tick });
      })
      .catch(() => { real.snapshots = []; this.setState({ tick: this.state.tick }); });
  },

  mapSnapshot(real, r) {
    const boot = window.CC_BOOT || {};
    const now = Date.now() / 1000;
    const exp = r.expires_at ? (new Date(r.expires_at).getTime() / 1000 || Number(r.expires_at)) : 0;
    const hoursLeft = exp ? Math.floor((exp - now) / 3600) : 0;
    return {
      id: String(r.snapshot_id), name: r.snapshot_name || ('snapshot-' + r.snapshot_id),
      when: this.fmtEpoch(r.created_at), size: this.fmtStorage(r.storage),
      filter: r.notes || '', expires: exp && exp > now ? (hoursLeft > 0 ? hoursLeft + 'h left' : '<1h left') : 'expired',
      _real: true, _token: r.token,
      _url: r.token ? (boot.restRoot + 'captaincore/v1/site/' + real.siteId + '/snapshots/' + r.snapshot_id + '-' + r.token + '/' + encodeURIComponent(r.snapshot_name || '')) : ''
    };
  },

  realSnapshots(real) {
    if (real.snapshots === null) return [{ id: '', name: 'Loading snapshots…', when: '', size: '', filter: '', expires: 'expired' }];
    return real.snapshots || [];
  },

  realSnapshotLink(real, sn) {
    this.api('/sites/' + real.siteId + '/snapshot-link/' + sn.id)
      .then(res => {
        if (res && res.token) {
          const idx = (real.snapshots || []).findIndex(x => x.id === sn.id);
          if (idx >= 0) real.snapshots[idx] = this.mapSnapshot(real, {
            snapshot_id: sn.id, snapshot_name: sn.name, created_at: 0,
            storage: 0, notes: sn.filter, token: res.token, expires_at: res.expires_at
          });
          if (idx >= 0) { real.snapshots[idx].when = sn.when; real.snapshots[idx].size = sn.size; }
          this.setState({ tick: this.state.tick });
        }
      })
      .catch(err => console.warn('snapshot-link failed', err));
  },

  realCreateSnapshot(real, s) {
    const email = (window.CC_BOOT || {}).userEmail || '';
    const filterMap = { Everything: null, Database: ['database'], Themes: ['themes'], Plugins: ['plugins'], Uploads: ['uploads'] };
    const filters = filterMap[s.snapFilter] || null;
    const body = { post_id: Number(real.siteId), command: 'snapshot', environment: s.env, value: email, notes: s.snapFilter };
    if (filters) body.filters = filters;
    this.startJob({
      label: 'snapshot', target: s.snapFilter + ' · ' + ((real.site && real.site.name) || ''), command: 'snapshot', siteId: real.siteId,
      dispatch: () => this.api('/sites/cli', { method: 'POST', body }),
      onFinish: () => { real.snapshots = undefined; this.loadSnapshots(); }
    });
  },

  // ── Timeline (process logs) ───────────────────────────────────
  loadTimeline() {
    const real = this._detail;
    if (!real || real.timeline !== undefined) return;
    real.timeline = null;
    this.api('/sites/' + real.siteId + '/timeline')
      .then(rows => {
        real.timeline = (Array.isArray(rows) ? rows : []).map(r => ({
          uid: r.process_log_id,
          text: r.description_raw
            || (r.description ? String(r.description).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : '')
            || (r.name ? String(r.name) : '')
            || (Array.isArray(r.files) && r.files.length ? r.files.length + ' file change(s)' : ''),
          // description arrives server-rendered (Parsedown markdown → HTML,
          // same data v1 trusts with v-html); text above stays the raw
          // markdown for the edit flow.
          html: (r.description && String(r.description).trim()) || '',
          who: r.author || 'System',
          when: this.fmtEpoch(r.created_at),
          _raw: r
        }));
        this.setState({ tick: this.state.tick });
      })
      .catch(() => { real.timeline = []; this.setState({ tick: this.state.tick }); });
  },

  reloadTimeline() {
    if (this._detail) { this._detail.timeline = undefined; this.loadTimeline(); }
  },

  realTimelineAdd(real, text, files) {
    const body = { site_ids: [Number(real.siteId)], process_id: 0, description: text };
    if (files && files.length) body.files = files;
    this.api('/process-logs', { method: 'POST', body })
      .then(() => this.reloadTimeline())
      .catch(err => console.warn('timeline add failed', err));
  },

  realTimelineEdit(real, row, text, files) {
    this.api('/process-logs/' + row.uid).then(log => {
      log.description_raw = text;
      // Always send the full attachment set in the assign_files input shape —
      // the round-tripped GET shape uses file_path, and any UI adds/removals
      // live in the passed list (seeded at startEdit).
      log.files = (files || []).map(f => f.path ? f : this.tlFileToPayload(f));
      return this.api('/process-logs/' + row.uid, { method: 'POST', body: log });
    }).then(() => this.reloadTimeline())
      .catch(err => console.warn('timeline edit failed', err));
  },

  realTimelineDelete(real, row) {
    this.api('/process-logs/' + row.uid, { method: 'DELETE' })
      .then(() => this.reloadTimeline())
      .catch(err => console.warn('timeline delete failed', err));
  },

  // ── Timeline file diffs ───────────────────────────────────────
  // Attachments are stored per process log as file rows with pre-computed
  // hunks (line_start / context_before / removed / added / context_after —
  // the /captaincore-log contract). The dialog below renders those hunks;
  // the attach form computes them client-side from two pasted versions.

  // Map a REST file row (file_path/hunks/…) to the assign_files input shape.
  tlFileToPayload(f) {
    return { path: f.file_path || f.path || '', change_type: f.change_type || 'modified',
      hunks: f.hunks || [], lines_added: f.lines_added || 0, lines_removed: f.lines_removed || 0 };
  },

  // Line-diff two texts into the hunk format. LCS on the changed middle when
  // affordable; one whole-block hunk for very large inputs. Each maximal run
  // of changed lines becomes one hunk (the format can't interleave context
  // inside a hunk), with up to 2 context lines each side.
  tlComputeHunks(orig, patched) {
    const split = t => { const v = String(t || '').replace(/\r\n/g, '\n'); return v === '' ? [] : v.split('\n'); };
    const a = split(orig), b = split(patched);
    let pre = 0;
    while (pre < a.length && pre < b.length && a[pre] === b[pre]) pre++;
    let sufA = a.length, sufB = b.length;
    while (sufA > pre && sufB > pre && a[sufA - 1] === b[sufB - 1]) { sufA--; sufB--; }
    const midA = a.slice(pre, sufA), midB = b.slice(pre, sufB);
    if (!midA.length && !midB.length) return [];
    let ops;
    if (midA.length * midB.length <= 250000) {
      const m = midA.length, n = midB.length;
      const dp = Array.from({ length: m + 1 }, () => new Array(n + 1).fill(0));
      for (let i = m - 1; i >= 0; i--)
        for (let j = n - 1; j >= 0; j--)
          dp[i][j] = midA[i] === midB[j] ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
      ops = [];
      let i = 0, j = 0;
      while (i < m && j < n) {
        if (midA[i] === midB[j]) { ops.push({ t: ' ', l: midA[i] }); i++; j++; }
        else if (dp[i + 1][j] >= dp[i][j + 1]) { ops.push({ t: '-', l: midA[i] }); i++; }
        else { ops.push({ t: '+', l: midB[j] }); j++; }
      }
      while (i < m) ops.push({ t: '-', l: midA[i++] });
      while (j < n) ops.push({ t: '+', l: midB[j++] });
    } else {
      ops = [...midA.map(l => ({ t: '-', l })), ...midB.map(l => ({ t: '+', l }))];
    }
    const full = [
      ...a.slice(0, pre).map(l => ({ t: ' ', l })),
      ...ops,
      ...a.slice(sufA).map(l => ({ t: ' ', l }))
    ];
    const hunks = [];
    let k = 0, oldLine = 1;
    while (k < full.length) {
      if (full[k].t === ' ') { k++; oldLine++; continue; }
      let end = k;
      while (end < full.length && full[end].t !== ' ') end++;
      const removed = [], added = [];
      for (let x = k; x < end; x++) (full[x].t === '-' ? removed : added).push(full[x].l);
      const ctxBefore = [];
      for (let x = k - 1; x >= 0 && ctxBefore.length < 2 && full[x].t === ' '; x--) ctxBefore.unshift(full[x].l);
      const ctxAfter = [];
      for (let x = end; x < full.length && ctxAfter.length < 2 && full[x].t === ' '; x++) ctxAfter.push(full[x].l);
      hunks.push({ line_start: Math.max(1, oldLine - ctxBefore.length), context_before: ctxBefore,
        removed, added, context_after: ctxAfter });
      oldLine += removed.length;
      k = end;
    }
    return hunks;
  },

  // Bindings for the code-diff dialog + the attach-a-diff form (spread into
  // computeDetail's return). The form is shared: tlAttachFor is 'new' when
  // opened from the composer, the row uid when opened from a row's editor.
  computeTlDiff(real, s) {
    const rows = real && Array.isArray(real.timeline) ? real.timeline : [];
    const log = rows.find(t => t.uid === s.plfUid);
    const files = log ? ((log._raw && log._raw.files) || []) : [];
    const open = !!(s.plfUid && files.length);
    const idx = Math.min(s.plfIdx || 0, Math.max(0, files.length - 1));
    const cur = open ? files[idx] : null;
    const nameOf = p => String(p || '').split('/').pop();
    const dirOf = p => String(p || '').split('/').slice(0, -1).join('/');
    const diffRows = [];
    if (cur) (cur.hunks || []).forEach((h, hi) => {
      if (hi) diffRows.push({ no: '', m: '', text: '⋯', bg: 'transparent', fg: 'var(--ink-dim)', mFg: 'var(--ink-dim)' });
      const push = (m, no, text) => diffRows.push({
        no: String(no), m, text: String(text),
        bg: m === '-' ? 'var(--bad-soft)' : m === '+' ? 'var(--ok-soft)' : 'transparent',
        fg: m === ' ' ? 'var(--ink-dim)' : 'var(--ink)',
        mFg: m === '-' ? 'var(--bad)' : m === '+' ? 'var(--ok)' : 'var(--ink-dim)'
      });
      let oldLine = h.line_start || 1, newLine = h.line_start || 1;
      (h.context_before || []).forEach(l => { push(' ', oldLine, l); oldLine++; newLine++; });
      (h.removed || []).forEach(l => { push('-', oldLine, l); oldLine++; });
      (h.added || []).forEach(l => { push('+', newLine, l); newLine++; });
      (h.context_after || []).forEach(l => { push(' ', oldLine, l); oldLine++; newLine++; });
    });
    const chip = (f, rm) => ({ name: nameOf(f.path || f.file_path), plus: f.lines_added ? '+' + f.lines_added : '',
      minus: f.lines_removed ? '−' + f.lines_removed : '', rm });
    const pendingNew = s.tlNewFiles || [];
    const pendingEdit = s.tlEditFiles || [];
    const atKey = String(s.tlAttachFor || '');
    const seedRef = el => { if (el && el._forKey !== atKey) { el._forKey = atKey; el.value = ''; } };
    return {
      plfShow: open,
      closePlf: () => this.setState({ plfUid: 0 }),
      plfTitle: cur ? 'code-diff — ' + nameOf(cur.file_path) : 'code-diff',
      plfCountLabel: files.length + (files.length === 1 ? ' file' : ' files'),
      plfList: files.map((f, i) => ({
        name: nameOf(f.file_path), dir: dirOf(f.file_path),
        plus: f.lines_added ? '+' + f.lines_added : '', minus: f.lines_removed ? '−' + f.lines_removed : '',
        bg: i === idx ? 'var(--panel-2)' : 'transparent',
        select: () => this.setState({ plfIdx: i })
      })),
      plfPath: cur ? 'File: ' + cur.file_path : '',
      plfOrig: cur ? '--- ' + nameOf(cur.file_path) + ' (original)' : '',
      plfPatched: cur ? '+++ ' + nameOf(cur.file_path) + ' (patched)' : '',
      plfRows: diffRows,
      plfEmpty: !!cur && !diffRows.length,
      plfStatRemoved: cur && cur.lines_removed ? '− ' + cur.lines_removed + ' removed' : '',
      plfStatAdded: cur && cur.lines_added ? '+ ' + cur.lines_added + ' added' : '',
      // ── attach-a-diff form ──
      tlAtNewOpen: atKey === 'new',
      tlAtEditOpen: !!(atKey && atKey !== 'new'),
      openTlAtNew: () => this.setState({ tlAttachFor: 'new', tlAtPath: '', tlAtOrig: '', tlAtPatched: '' }),
      openTlAtEdit: () => this.setState({ tlAttachFor: s.tlEdit, tlAtPath: '', tlAtOrig: '', tlAtPatched: '' }),
      cancelTlAt: () => this.setState({ tlAttachFor: 0 }),
      onTlAtPath: e => this.setState({ tlAtPath: e.target.value }),
      onTlAtOrig: e => this.setState({ tlAtOrig: e.target.value }),
      onTlAtPatched: e => this.setState({ tlAtPatched: e.target.value }),
      tlAtPathRef: seedRef, tlAtOrigRef: seedRef, tlAtPatchedRef: seedRef,
      tlAtSubmit: () => {
        const st = this.state;
        const path = (st.tlAtPath || '').trim();
        if (!path) { if (this.toast) this.toast('A file path is required.', { kind: 'error' }); return; }
        const orig = st.tlAtOrig || '', patched = st.tlAtPatched || '';
        const hunks = this.tlComputeHunks(orig, patched);
        if (!hunks.length) { if (this.toast) this.toast('The two versions are identical — nothing to attach.', { kind: 'error' }); return; }
        const file = {
          path,
          change_type: !orig.trim() ? 'added' : !patched.trim() ? 'removed' : 'modified',
          hunks,
          lines_added: hunks.reduce((n, h) => n + h.added.length, 0),
          lines_removed: hunks.reduce((n, h) => n + h.removed.length, 0)
        };
        if (st.tlAttachFor === 'new') this.setState({ tlNewFiles: [...(st.tlNewFiles || []), file], tlAttachFor: 0 });
        else this.setState({ tlEditFiles: [...(st.tlEditFiles || []), file], tlFilesDirty: true, tlAttachFor: 0 });
      },
      tlNewChips: pendingNew.map((f, i) => chip(f, () =>
        this.setState(st => ({ tlNewFiles: (st.tlNewFiles || []).filter((_, x) => x !== i) })))),
      tlNewChipsShow: !!pendingNew.length,
      tlEditChips: pendingEdit.map((f, i) => chip(f, () =>
        this.setState(st => ({ tlEditFiles: (st.tlEditFiles || []).filter((_, x) => x !== i), tlFilesDirty: true })))),
      tlEditChipsShow: !!pendingEdit.length
    };
  }

});
