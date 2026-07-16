// CaptainCore v3 — Archives real-data layer (mixin). Admin-only.
//   GET  /archive              → rclone lsjson [{Name,Size,ModTime,Path}]
//   POST /archive/store {url}  → { status:'queued', token }  (url must end .zip)
//        progress: GET /my-jobs/{token} poll (SSE stream also exists at
//        /my-jobs/{token}/stream — not wired; a light poll refreshes the list)
//   POST /archive/share {file} → { link }  (7-day B2 signed URL; == download)
// No delete route and no dedicated download route in v1 — Delete is hidden,
// download IS the share link.

Object.assign(Component.prototype, {

  loadArchives(force) {
    if (this._archLoading || (this._arch && !force)) return;
    this._archLoading = true;
    this.api('/archive').then(res => {
      this._archLoading = false;
      this._arch = Array.isArray(res) ? res : [];
      this.setState({});
    }).catch(() => { this._archLoading = false; this._arch = []; this.setState({}); });
  },

  storeArchive() {
    const url = (this.state.archUrl || '').trim();
    if (!url.toLowerCase().endsWith('.zip')) { this.setState({ archErr: true }); return; }
    this.setState({ archErr: false, archStoreMsg: 'Queued — storing archive…', archUrl: '' });
    this.api('/archive/store', { method: 'POST', body: { url } }).then(res => {
      const token = res && res.token;
      if (!token) { this.setState({ archStoreMsg: (res && res.message) || 'Store failed.' }); return; }
      this.pollArchiveJob(token, 0);
    }).catch(() => this.setState({ archStoreMsg: 'Store failed.' }));
  },

  pollArchiveJob(token, tries) {
    if (tries > 45) { this.setState({ archStoreMsg: 'Still storing — check back shortly.' }); this.loadArchives(true); return; }
    this.api('/my-jobs/' + token).then(res => {
      const status = res && res.status;
      if (status === 'completed') { this.setState({ archStoreMsg: 'Archive stored.' }); this.loadArchives(true); return; }
      const p = res && res.progress;
      if (p && p.phase) this.setState({ archStoreMsg: p.phase + (p.percent != null ? ' ' + p.percent + '%' : '') + '…' });
      setTimeout(() => this.pollArchiveJob(token, tries + 1), 4000);
    }).catch(() => setTimeout(() => this.pollArchiveJob(token, tries + 1), 4000));
  },

  shareArchive(nameOrPath, id) {
    this.setState({ copied: id });
    this.api('/archive/share', { method: 'POST', body: { file: nameOrPath } }).then(res => {
      if (res && res.link) { try { navigator.clipboard.writeText(res.link); } catch (e) {} }
    }).catch(() => {});
    clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1600);
  },

  realArchivesVals(s) {
    if (s.route === 'archives' && !this._arch && !this._archLoading) setTimeout(() => this.loadArchives(), 0);
    const raw = this._arch;
    if (!raw) return { archTotal: this._archLoading ? 'Loading…' : '', archRows: [], archEmpty: !this._archLoading, archEmptyText: 'Loading…' };
    // The rclone remote lists every object (incl. .bzEmpty markers + dirs);
    // archives are the .zip files. Newest first.
    const list = raw.filter(a => !a.IsDir && /\.zip$/i.test(a.Name || a.Path || ''))
      .sort((a, b) => String(b.ModTime || '').localeCompare(String(a.ModTime || '')));
    const totalBytes = list.reduce((n, a) => n + (parseInt(a.Size, 10) || 0), 0);
    const archRows = list.map((a, i) => ({
      name: a.Name || a.Path,
      size: this.fmtStorage(a.Size),
      mod: (a.ModTime || '').slice(0, 10),
      storing: false, archCanDelete: false,
      mark: s.copied === 'arch' + i ? 'Copied ✓' : 'Share link (7d)',
      share: () => this.shareArchive(a.Path || a.Name, 'arch' + i),
      del: () => {}
    }));
    return {
      archTotal: list.length + ' archive' + (list.length === 1 ? '' : 's') + ' · ' + this.fmtStorage(totalBytes) + ' on Backblaze B2',
      archRows, archEmpty: !archRows.length, archEmptyText: 'No archives stored yet.',
      storeArch: () => this.storeArchive(),
      archStoreMsg: s.archStoreMsg || '', archHasStoreMsg: !!(s.archStoreMsg || '')
    };
  }

});
