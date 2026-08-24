// CaptainCore v3 — Site requests (mixin).
// Customer "New site › Request" submissions + the operator queue that walks
// each request through its 3 steps (Requested → Preparing → Ready). v1 parity:
// core-legacy.php requested_sites stepper (markup 3792-3872, methods 17074+).
// Backend: GET/POST /site-requests, POST /site-requests/continue|back,
// PUT /site-requests/update, POST /site-requests/delete — every write returns
// the fresh list, so `_siteRequests` is always server-truth after an action.
// Requests are stored per-user (user meta); operators see every user's, and
// each admin row carries the owning user_id which must ride back on writes.

Object.assign(Component.prototype, {

  loadSiteRequests() {
    if (this._srLoading) return;
    this._srLoading = true;
    this.api('/site-requests').then(list => {
      this._srLoading = false;
      this._siteRequests = Array.isArray(list) ? list : [];
      this.setState({});
    }).catch(() => { this._srLoading = false; this._siteRequests = []; this.setState({}); });
  },

  // Customer submission from the New site › Request tab (real path for the
  // mock nsCreate branch). v1 contract: client stamps created_at + step.
  submitSiteRequest() {
    const st = this.state;
    const name = (st.nsName || '').trim();
    const acc = this.ACCOUNTS.find(a => a.name === st.nsAcc);
    if (!name) { this.toast('Enter a site name', { kind: 'error' }); return; }
    if (!acc) { this.toast('Pick an account', { kind: 'error' }); return; }
    const request = { name, account_id: acc.id, notes: (st.nsNotes || '').trim(),
      created_at: Math.round(Date.now() / 1000), step: 1 };
    const tid = this.toast('Requesting new site…', { kind: 'loading' });
    this.api('/site-requests', { method: 'POST', body: { request, account_id: acc.id } })
      .then(list => { this._siteRequests = Array.isArray(list) ? list : [];
        this.updateToast(tid, 'Site request sent', { kind: 'success' });
        this.setState({ nsOpen: false, nsName: '', nsNotes: '' }); })
      .catch(() => this.updateToast(tid, 'Could not send the request', { kind: 'error' }));
  },

  _srAction(path, req, method) {
    // Send the row as the server handed it to us (matching is by created_at,
    // and admin writes need the owning user_id riding along).
    this.api('/site-requests' + path, { method: method || 'POST', body: { request: req } })
      .then(list => { this._siteRequests = Array.isArray(list) ? list : []; this.setState({}); })
      .catch(() => this.toast('Request update failed', { kind: 'error' }));
  },

  siteRequestsVals(s) {
    const empty = { srShow: false, srRows: [], srEditOpen: false };
    if (!this._hydrated) return empty;
    if (s.route === 'sites' && this._siteRequests === undefined && !this._srLoading)
      setTimeout(() => this.loadSiteRequests(), 0);
    const list = this._siteRequests || [];
    if (!list.length) return empty;
    const isOp = (window.CC_BOOT || {}).dcRole === 'operator';
    const when = t => t ? new Date(t * 1000).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : '';
    const accName = id => { const a = this.ACCOUNTS.find(x => String(x.id) === String(id)); return a ? a.name : ''; };
    const stepStyle = (step, n) => step > n ? { bg: 'var(--ok-soft)', fg: 'var(--ink)' }
      : step === n ? { bg: 'var(--brand-soft)', fg: 'var(--brand-ink)' }
      : { bg: 'var(--panel-2)', fg: 'var(--ink-dim)' };
    return {
      srShow: true,
      srRows: list.map((r, i) => {
        const step = parseInt(r.step, 10) || 1;
        const steps = [
          { label: 'Requested', sub: when(r.created_at) },
          { label: 'Preparing', sub: when(r.processing_at) },
          { label: 'Ready', sub: when(r.ready_at) }
        ].map((x, n) => ({ ...x, ...stepStyle(step, n + 1) }));
        return {
          id: 'sr' + i, name: r.name, acc: accName(r.account_id),
          notes: r.notes || '', hasNotes: !!r.notes,
          url: r.url || '', hasUrl: step === 3 && typeof r.url === 'string' && r.url !== '',
          openUrl: () => this.safeOpen(r.url),
          steps, isOp,
          canCont: isOp && step < 3, cont: () => this._srAction('/continue', r),
          canBack: isOp && step > 1, back: () => this._srAction('/back', r),
          finishLabel: step === 3 ? 'Finish' : 'Cancel',
          finish: () => this._srAction('/delete', r),
          modify: () => this.setState({ srEditOpen: true, srEditIdx: i,
            srEditName: r.name || '', srEditUrl: r.url || '', srEditNotes: r.notes || '' })
        };
      }),
      srEditOpen: !!s.srEditOpen,
      srEditName: s.srEditName || '', onSrEditName: e => this.setState({ srEditName: e.target.value }),
      srEditUrl: s.srEditUrl || '', onSrEditUrl: e => this.setState({ srEditUrl: e.target.value }),
      srEditNotes: s.srEditNotes || '', onSrEditNotes: e => this.setState({ srEditNotes: e.target.value }),
      closeSrEdit: () => this.setState({ srEditOpen: false }),
      srEditSave: () => {
        const r = (this._siteRequests || [])[this.state.srEditIdx];
        if (!r) { this.setState({ srEditOpen: false }); return; }
        const req = { ...r, name: (this.state.srEditName || '').trim() || r.name,
          url: (this.state.srEditUrl || '').trim(), notes: this.state.srEditNotes || '' };
        this.setState({ srEditOpen: false });
        this._srAction('/update', req, 'PUT');
      }
    };
  }

});
