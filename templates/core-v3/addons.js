// CaptainCore v3 — Add plugin/theme dialog (mixin).
// "+ Add" on the site Addons tab. Three sources, mirroring v1's new_plugin /
// new_theme dialogs (templates/core.php:153): Upload (drag & drop .zip →
// upload.php → install the returned URL), WordPress.org search
// (GET /wp-plugins | /wp-themes — plugins_api/themes_api passthrough), and
// Envato purchases (GET /providers/envato/plugins|themes cached list;
// install resolves a signed URL via .../plugin|theme/{id}/download).
// Installs dispatch POST /run/code on the current environment and chain a
// data sync (realSync) so the addons list refreshes. Upload requires
// manage_options (upload.php gate) so the tab is hidden for customers.
// The DC runtime has no onDrop event prop — drop/dragover/change listeners
// bind natively through ref callbacks.

Object.assign(Component.prototype, {

  openAddAddon() {
    const boot = window.CC_BOOT || {};
    this._addon = { kind: this.state.addonKind, items: null, pages: 0, page: 1, loading: false, uploads: [],
      envato: null, envatoLoading: false };
    this.setState({ aaOpen: true, aaTab: boot.dcRole === 'customer' ? 'wporg' : 'upload', aaQ: '', aaEQ: '', aaDrag: false });
    this.aaFetch();
  },

  aaTick() { this.setState({ tick: this.state.tick }); },

  // Decode the HTML entities wp.org puts in names/descriptions (v1 used v-html).
  aaText(str) {
    if (!str || str.indexOf('&') === -1) return str || '';
    const el = this._aaDecodeEl = this._aaDecodeEl || document.createElement('textarea');
    el.innerHTML = str;
    return el.value;
  },

  // wp.org/Envato titles are keyword-stuffed ("Rank Math SEO – AI SEO Tools
  // to Dominate…") — keep the product name. Ported from Minn Admin's
  // cleanPluginName (minn-admin/assets/js/app.js:145). Cut at the first
  // separator: dashes/pipes/middots need surrounding space (WP-Optimize
  // survives), colon/semicolon/period/comma just a following space, parens
  // always. "X by Vendor" comes off only when a multi-word name remains, so
  // "Login by Auth0" survives. The full name stays in the title tooltip.
  aaCleanName(name) {
    const full = this.aaText(name || '').trim();
    let out = full.split(/\s+[–—|·]\s+|\s+-\s+|[:;.,]\s+|\s*[({]/)[0].trim();
    const by = out.match(/^(.+?)\s+by\s+\S/i);
    if (by && by[1].trim().includes(' ')) out = by[1].trim();
    return out.length >= 2 ? out : full;
  },

  aaFetch() {
    const st = this._addon;
    if (!st) return;
    // Sequence guard: local REST is slow enough that a dialog-open browse
    // fetch can resolve AFTER a subsequent search fetch and clobber it.
    const seq = st.seq = (st.seq || 0) + 1;
    st.loading = true;
    this.aaTick();
    const q = this.state.aaQ.trim();
    const path = (st.kind === 'themes' ? '/wp-themes' : '/wp-plugins')
      + '?page=' + st.page + (q ? '&value=' + encodeURIComponent(q) : '');
    this.api(path).then(res => {
      if (this._addon !== st || st.seq !== seq) return;
      st.loading = false;
      st.items = (st.kind === 'themes' ? res && res.themes : res && res.plugins) || [];
      st.pages = (res && res.info && res.info.pages) || 0;
      this.aaTick();
    }).catch(() => {
      if (this._addon !== st || st.seq !== seq) return;
      st.loading = false;
      st.items = [];
      this.aaTick();
    });
  },

  aaSearch() {
    const st = this._addon;
    if (!st) return;
    st.page = 1;
    this.aaFetch();
  },

  aaPage(dir) {
    const st = this._addon;
    if (!st || st.loading) return;
    const next = st.page + dir;
    if (next < 1 || (st.pages && next > st.pages)) return;
    st.page = next;
    this.aaFetch();
  },

  // ── Envato purchases ──────────────────────────────────────────
  aaEnvatoFetch() {
    const st = this._addon;
    if (!st || st.envato !== null || st.envatoLoading) return;
    st.envatoLoading = true;
    this.aaTick();
    this.api('/providers/envato/' + (st.kind === 'themes' ? 'themes' : 'plugins')).then(res => {
      if (this._addon !== st) return;
      st.envatoLoading = false;
      st.envato = Array.isArray(res) ? res : [];
      this.aaTick();
    }).catch(() => {
      if (this._addon !== st) return;
      st.envatoLoading = false;
      st.envato = [];
      this.aaTick();
    });
  },

  aaEnvatoInstall(item) {
    const st = this._addon, real = this._detail;
    if (!st || !real) return;
    const kind = st.kind === 'themes' ? 'theme' : 'plugin';
    const name = (real.site && real.site.name) || '';
    const tid = this.toast('Downloading ' + this.aaCleanName(item.name) + ' from Envato…', { kind: 'loading' });
    this.setState({ aaOpen: false });
    // Resolve a fresh signed download URL, then install it (v1: core.php:24271).
    this.api('/providers/envato/' + kind + '/' + item.id + '/download').then(url => {
      if (typeof url !== 'string' || url.indexOf('http') !== 0) throw new Error('no download url');
      this.updateToast(tid, 'Installing ' + this.aaCleanName(item.name) + '…', { kind: 'loading' });
      const code = 'wp ' + kind + " install --force --skip-plugins --skip-themes '" + url + "'";
      this.aaRunCode(code, this.aaCleanName(item.name) + ' on ' + name, tid);
    }).catch(() => this.updateToast(tid, 'Failed to get Envato download link', { kind: 'error' }));
  },

  // Dispatch a wp-cli line on the current environment, then chain a data
  // sync so the addons list reflects the change (same shape as realToggleAddon).
  // Pass toastId to resolve an already-showing loading toast instead of the
  // one startJob mints.
  aaRunCode(code, target, toastId) {
    const real = this._detail;
    if (!real || !real.envs) { this.toast('Site environments still loading', { kind: 'error' }); return; }
    const e = real.envs.find(x => x.environment === this.state.env) || real.envs[0];
    if (!e) return;
    const env = e.environment;
    const jobId = this.startJob({
      label: 'install', target, command: 'manage', siteId: real.siteId, environment: env,
      dispatch: () => this.api('/run/code', { method: 'POST', body: { environments: [e.environment_id], code } }),
      onFinish: () => this.realSync(real, { env })
    });
    if (toastId) {
      const job = this._jobObjs[jobId];
      if (job._toastId) this.dismissToast(job._toastId);
      job._toastId = toastId;
    }
  },

  aaInstall(item) {
    const st = this._addon, real = this._detail;
    if (!st || !real) return;
    const name = (real.site && real.site.name) || '';
    // Themes install by slug; plugins by download_link (v1 parity,
    // core.php:24472 / 24718).
    const code = st.kind === 'themes'
      ? "wp theme install '" + item.slug + "' --force"
      : "wp plugin install --force --skip-plugins --skip-themes '" + item.download_link + "'";
    if (st.kind !== 'themes' && !item.download_link) { this.toast('No download link for ' + item.slug, { kind: 'error' }); return; }
    this.setState({ aaOpen: false });
    this.aaRunCode(code, item.slug + ' on ' + name);
  },

  aaUninstall(item) {
    const st = this._addon, real = this._detail;
    if (!st || !real) return;
    const name = (real.site && real.site.name) || '';
    const code = st.kind === 'themes'
      ? 'wp theme delete ' + item.slug + ' --skip-themes --skip-plugins'
      : 'wp plugin delete ' + item.slug + ' --skip-themes --skip-plugins';
    this.setState({ aaOpen: false });
    this.aaRunCode(code, item.slug + ' on ' + name);
  },

  // ── Upload (drag & drop) ──────────────────────────────────────
  aaBindDrop(el) {
    if (!el || el._aaBound) return;
    el._aaBound = true;
    const stop = e => { e.preventDefault(); e.stopPropagation(); };
    el.addEventListener('dragover', e => { stop(e); if (!this.state.aaDrag) this.setState({ aaDrag: true }); });
    el.addEventListener('dragleave', e => { stop(e); this.setState({ aaDrag: false }); });
    el.addEventListener('drop', e => {
      stop(e);
      this.setState({ aaDrag: false });
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) this.aaUploadFiles(e.dataTransfer.files);
    });
  },

  aaBindFile(el) {
    if (!el) return;
    this._aaFileEl = el;
    if (el._aaBound) return;
    el._aaBound = true;
    el.addEventListener('change', () => {
      if (el.files && el.files.length) { this.aaUploadFiles(el.files); el.value = ''; }
    });
  },

  aaUploadFiles(files) {
    const boot = window.CC_BOOT || {};
    const st = this._addon;
    if (!st) return;
    if (!boot.uploadUrl) { this.toast('Upload endpoint not configured', { kind: 'error' }); return; }
    Array.from(files).forEach(f => {
      const entry = { name: f.name, size: f.size, pct: 0, status: 'uploading', error: '' };
      st.uploads.push(entry);
      if (!/\.zip$/i.test(f.name)) { entry.status = 'error'; entry.error = 'Only .zip archives'; this.aaTick(); return; }
      this.aaTick();
      const xhr = new XMLHttpRequest();
      xhr.open('POST', boot.uploadUrl);
      xhr.setRequestHeader('X-WP-Nonce', boot.nonce);
      xhr.upload.onprogress = e => {
        if (e.lengthComputable) { entry.pct = Math.round(e.loaded / e.total * 100); this.aaTick(); }
      };
      xhr.onload = () => {
        let res = null;
        try { res = JSON.parse(xhr.responseText); } catch (e) { /* non-JSON error page */ }
        if (xhr.status === 200 && res && res.response === 'Success' && res.url) {
          entry.status = 'done';
          entry.pct = 100;
          this.aaTick();
          this.aaInstallUpload(res.url, f.name);
        } else {
          entry.status = 'error';
          entry.error = xhr.status === 403 ? 'Not allowed' : (res && res.response && res.response !== 'Error' ? String(res.response) : 'Upload failed');
          this.aaTick();
        }
      };
      xhr.onerror = () => { entry.status = 'error'; entry.error = 'Upload failed'; this.aaTick(); };
      const fd = new FormData();
      fd.append('file', f, f.name);
      xhr.send(fd);
    });
  },

  aaInstallUpload(url, filename) {
    const st = this._addon, real = this._detail;
    if (!st || !real) return;
    const name = (real.site && real.site.name) || '';
    // v1 parity: uploaded plugins install --activate (core.php:16736); themes don't.
    const code = st.kind === 'themes'
      ? "wp theme install '" + url + "' --force"
      : "wp plugin install --skip-plugins --skip-themes --force --activate '" + url + "'";
    this.setState({ aaOpen: false });
    this.aaRunCode(code, filename + ' on ' + name);
  },

  // ── Bindings (spread into computeDetail's return) ─────────────
  computeAddAddon(real, s, site) {
    const boot = window.CC_BOOT || {};
    const st = this._addon;
    const kind = st ? st.kind : s.addonKind;
    const isUp = s.aaTab !== 'wporg' && s.aaTab !== 'envato';
    const isWp = s.aaTab === 'wporg';
    const isEnv = s.aaTab === 'envato';
    const installedSlugs = {};
    if (real) this.realAddonSrc(real, Object.assign({}, s, { addonKind: kind })).forEach(a => { installedSlugs[a.slug] = true; });
    const items = ((st && st.items) || []).map(it => {
      const icon = kind === 'themes'
        ? (it.screenshot_url || '')
        : ((it.icons && (it.icons['1x'] || it.icons['2x'] || it.icons.svg)) || '');
      const installed = !!installedSlugs[it.slug];
      return {
        slug: it.slug,
        title: this.aaCleanName(it.name),
        fullName: this.aaText(it.name),
        version: 'Version ' + (it.version || '—'),
        desc: this.aaText(it.short_description || ''),
        icon, hasIcon: !!icon,
        isTheme: kind === 'themes', notTheme: kind !== 'themes',
        installed, notInstalled: !installed,
        doInstall: () => this.aaInstall(it),
        doUninstall: () => this.aaUninstall(it)
      };
    });
    const pages = st ? st.pages : 0;
    const page = st ? st.page : 1;
    const uploads = ((st && st.uploads) || []).map(u => ({
      name: u.name,
      sizeLabel: u.size >= 1048576 ? (u.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(u.size / 1024)) + ' KB',
      statusLabel: u.status === 'uploading' ? 'Uploading ' + u.pct + '%' : u.status === 'done' ? 'Uploaded' : (u.error || 'Failed'),
      fg: u.status === 'error' ? 'var(--bad)' : u.status === 'done' ? 'var(--ok)' : 'var(--ink-dim)'
    }));
    const eq = (s.aaEQ || '').toLowerCase();
    const envItems = ((st && st.envato) || [])
      .filter(it => !eq || String(it.name || '').toLowerCase().includes(eq))
      .map(it => ({
        id: it.id,
        title: this.aaCleanName(it.name),
        fullName: this.aaText(it.name),
        idLabel: 'ID: ' + it.id,
        icon: (it.previews && it.previews.icon_preview && it.previews.icon_preview.icon_url) || '',
        doInstall: () => this.aaEnvatoInstall(it)
      }));
    return {
      aaOpen: s.aaOpen,
      openAddAddon: () => this.openAddAddon(),
      closeAa: () => this.setState({ aaOpen: false }),
      aaTitle: 'Add ' + (kind === 'themes' ? 'theme' : 'plugin') + ' to ' + ((real && real.site && real.site.name) || site.name),
      aaShowUpload: boot.dcRole !== 'customer',
      aaTabUpload: isUp, aaTabWporg: isWp, aaTabEnvato: isEnv,
      aaUpFg: isUp ? 'var(--brand-ink)' : 'var(--ink-dim)', aaUpLine: isUp ? 'var(--brand)' : 'transparent',
      aaWpFg: isWp ? 'var(--brand-ink)' : 'var(--ink-dim)', aaWpLine: isWp ? 'var(--brand)' : 'transparent',
      aaEnFg: isEnv ? 'var(--brand-ink)' : 'var(--ink-dim)', aaEnLine: isEnv ? 'var(--brand)' : 'transparent',
      setAaUpload: () => this.setState({ aaTab: 'upload' }),
      setAaWporg: () => this.setState({ aaTab: 'wporg' }),
      setAaEnvato: () => { this.setState({ aaTab: 'envato' }); this.aaEnvatoFetch(); },
      aaEQ: s.aaEQ,
      onAaEQ: e => this.setState({ aaEQ: e.target.value }),
      aaEnvItems: envItems,
      aaEnvLoading: !!(st && st.envatoLoading),
      aaEnvEmpty: !!(st && st.envato && !envItems.length),
      aaEnvLabel: s.env,
      aaDropRef: el => this.aaBindDrop(el),
      aaFileRef: el => this.aaBindFile(el),
      aaPickFiles: () => { if (this._aaFileEl) this._aaFileEl.click(); },
      aaDropBorder: s.aaDrag ? 'var(--brand)' : 'var(--rule)',
      aaDropBg: s.aaDrag ? 'var(--brand-soft)' : 'var(--panel-2)',
      aaUploads: uploads, aaHasUploads: uploads.length > 0,
      aaQ: s.aaQ,
      onAaQ: e => this.setState({ aaQ: e.target.value }),
      aaKeyQ: e => { if (e.key === 'Enter') this.aaSearch(); },
      aaDoSearch: () => this.aaSearch(),
      aaLoading: !!(st && st.loading),
      aaItems: items,
      aaEmpty: !!(st && !st.loading && st.items && !st.items.length),
      aaHasPages: pages > 1,
      aaPageLabel: 'Page ' + page + ' of ' + pages,
      aaPrev: () => this.aaPage(-1),
      aaNext: () => this.aaPage(1),
      aaPrevFg: page > 1 ? 'var(--ink)' : 'var(--rule)',
      aaNextFg: pages && page < pages ? 'var(--ink)' : 'var(--rule)'
    };
  }

});

// Toast verbs for the new job labels (jobs.js JOB_VERBS lookup).
Component.prototype.JOB_VERBS = Object.assign({}, Component.prototype.JOB_VERBS, { install: 'Install' });
