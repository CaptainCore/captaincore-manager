// CaptainCore v3 — AI Relay projects (mixin).
// A project is a customer build started on the public AI Relay page. Until it
// launches it is NOT a site: it shows as its own card on the Sites page and
// opens a project page with everything the customer sent, a message thread
// (text + files, both directions) and, once staff mark the preview ready, a
// Launch button that charges the first year and hands the site over. An
// existing-site build (billing 'none') launches with no charge at all.
// Backend: app/AiRelay.php. GET /ai-relay/projects, GET|PUT
// /ai-relay/projects/<id>, POST …/messages, POST …/launch, GET …/files/<fid>,
// POST|DELETE /ai-relay/files (staging, one file per request).
// Hosting details never ride along before launch: the server leaves site_id
// out of a customer's payload until the launch has been paid for.

Object.assign(Component.prototype, {

  loadRelayProjects() {
    if (this._rlLoading) return;
    this._rlLoading = true;
    this.api('/ai-relay/projects').then(list => {
      this._rlLoading = false;
      this._relayProjects = Array.isArray(list) ? list : [];
      this.setState({});
    }).catch(() => { this._rlLoading = false; this._relayProjects = []; this.setState({}); });
  },

  openRelay(id) {
    this._rlUploads = [];
    this.setState({ route: 'relay', relayId: String(id), paletteOpen: false, rlBody: '', rlSending: false,
      rlStatus: '', rlPreview: '', rlSite: '', rlSaving: false, rlLaunching: false });
    if (this._hydrated) this.loadRelay(id);
  },

  loadRelay(id) {
    const rel = this._relay = { id: String(id), data: null, loading: true, err: '' };
    this.api('/ai-relay/projects/' + id).then(p => {
      if (this._relay !== rel) return;
      rel.loading = false;
      if (!p || p.code) { rel.err = (p && p.message) || 'Project not found.'; this.setState({}); return; }
      rel.data = p;
      this.setState({ rlStatus: p.status, rlPreview: p.preview_url || '', rlSite: p.site_id ? String(p.site_id) : '' });
    }).catch(() => { if (this._relay === rel) { rel.loading = false; rel.err = 'Could not load this project.'; this.setState({}); } });
  },

  _relayReplace(p) {
    if (!p || p.code) return false;
    if (this._relay) this._relay.data = p;
    const list = this._relayProjects || [];
    const i = list.findIndex(x => String(x.id) === String(p.id));
    if (i > -1) list[i] = Object.assign({}, list[i], p);
    return true;
  },

  // Files go up one per request into the sender's staging area; the message
  // then references their ids.
  relayBindFile(el) {
    if (!el) return;
    this._rlFileEl = el;
    if (el._rlBound) return;
    el._rlBound = true;
    el.addEventListener('change', () => {
      if (el.files && el.files.length) { this.relayUpload(el.files); el.value = ''; }
    });
  },

  relayUpload(files) {
    const boot = window.CC_BOOT || {};
    this._rlUploads = this._rlUploads || [];
    Array.prototype.forEach.call(files, f => {
      const entry = { name: f.name, size: f.size, state: 'uploading', id: '' };
      this._rlUploads.push(entry);
      const fd = new FormData();
      fd.append('file', f, f.name);
      fetch(boot.restRoot + 'captaincore/v1/ai-relay/files', {
        method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': boot.nonce }, body: fd
      }).then(r => r.json().then(j => ({ ok: r.ok, j }))).then(({ ok, j }) => {
        if (ok && j && j.id) { entry.id = j.id; entry.state = 'done'; }
        else { entry.state = 'error'; entry.error = (j && j.message) || 'Upload failed'; }
        this.setState({});
      }).catch(() => { entry.state = 'error'; entry.error = 'Upload failed'; this.setState({}); });
    });
    this.setState({});
  },

  relayDropUpload(entry) {
    this._rlUploads = (this._rlUploads || []).filter(x => x !== entry);
    if (entry.id) this.api('/ai-relay/files/' + entry.id, { method: 'DELETE' }).catch(() => {});
    this.setState({});
  },

  relaySend() {
    const rel = this._relay;
    if (!rel || !rel.data || this.state.rlSending) return;
    const ups = this._rlUploads || [];
    if (ups.some(u => u.state === 'uploading')) { this.toast('Files are still uploading', { kind: 'info' }); return; }
    const files = ups.filter(u => u.state === 'done').map(u => u.id);
    const body = (this.state.rlBody || '').trim();
    if (!body && !files.length) { this.toast('Write a message or attach a file', { kind: 'error' }); return; }
    this.setState({ rlSending: true });
    this.api('/ai-relay/projects/' + rel.id + '/messages', { method: 'POST', body: { body, files } }).then(p => {
      if (!this._relayReplace(p)) throw new Error((p && p.message) || 'send');
      this._rlUploads = [];
      this.setState({ rlSending: false, rlBody: '' });
    }).catch(e => {
      this.setState({ rlSending: false });
      this.toast(e && e.message && e.message !== 'send' ? e.message : 'Could not send the message', { kind: 'error' });
    });
  },

  relaySave() {
    const rel = this._relay;
    if (!rel || !rel.data) return;
    const s = this.state;
    this.setState({ rlSaving: true });
    // login_url is emailed once with the site link and never stored, so the
    // field clears after a successful save.
    this.api('/ai-relay/projects/' + rel.id, { method: 'PUT', body: {
      status: s.rlStatus, preview_url: (s.rlPreview || '').trim(), site_id: parseInt(s.rlSite, 10) || 0,
      login_url: (s.rlLogin || '').trim()
    } }).then(p => {
      this.setState({ rlSaving: false });
      if (!this._relayReplace(p)) { this.toast((p && p.message) || 'Could not save', { kind: 'error' }); return; }
      this.setState({ rlLogin: '' });
      this.toast('Project updated', { kind: 'success' });
    }).catch(() => { this.setState({ rlSaving: false }); this.toast('Could not save', { kind: 'error' }); });
  },

  async relayLaunch() {
    const rel = this._relay;
    if (!rel || !rel.data || this.state.rlLaunching) return;
    const p = rel.data;
    const free = p.billing === 'none';
    const ok = await this.uiConfirm(free
      ? 'Launch ' + p.name + '? There is no charge. The site is already part of your hosting, and we will take it live and let you know.'
      : 'Launch ' + p.name + '? Your card on file is charged $' + Math.round(p.price) +
        ' now for one year of hosting, and the site moves into your account.', { label: 'Launch', danger: false });
    if (!ok) return;
    this.setState({ rlLaunching: true });
    const tid = this.toast('Launching…', { kind: 'loading' });
    this.api('/ai-relay/projects/' + rel.id + '/launch', { method: 'POST' }).then(res => {
      this.setState({ rlLaunching: false });
      if (!this._relayReplace(res)) { this.updateToast(tid, (res && res.message) || 'Launch failed', { kind: 'error', timeout: 8000 }); return; }
      this.updateToast(tid, free ? 'Thanks. We will take ' + p.name + ' live and let you know.' : p.name + ' is live', { kind: 'success' });
      this.loadSites && this.loadSites();
    }).catch(() => { this.setState({ rlLaunching: false }); this.updateToast(tid, 'Launch failed', { kind: 'error' }); });
  },

  relayVals(s) {
    const boot = window.CC_BOOT || {};
    const isOp = boot.dcRole === 'operator';
    const when = t => {
      if (!t) return '';
      const d = new Date(String(t).replace(' ', 'T'));
      return isNaN(d) ? '' : d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    };
    const chip = st => ({
      building: { bg: 'var(--brand-soft)', fg: 'var(--brand-ink)' },
      preview: { bg: 'var(--ok-soft)', fg: 'var(--ok)' },
      launched: { bg: 'var(--panel-2)', fg: 'var(--ink-dim)' },
      cancelled: { bg: 'var(--panel-2)', fg: 'var(--ink-dim)' }
    }[st] || { bg: 'var(--panel-2)', fg: 'var(--ink-dim)' });
    const human = b => b < 1024 ? b + ' B' : b < 1048576 ? Math.round(b / 1024) + ' KB' : (b / 1048576).toFixed(1) + ' MB';

    // ── Sites page cards ──
    const out = { rlShow: false, rlRows: [], showRelay: s.route === 'relay' };
    if (this._hydrated && s.route === 'sites' && this._relayProjects === undefined && !this._rlLoading)
      setTimeout(() => this.loadRelayProjects(), 0);
    const list = (this._relayProjects || []).filter(p => p.status === 'building' || p.status === 'preview');
    if (list.length) {
      out.rlShow = true;
      out.rlRows = list.map(p => {
        const c = chip(p.status);
        return { id: 'rl' + p.id, name: p.name, acc: p.account, status: p.status_label, chipBg: c.bg, chipFg: c.fg,
          sub: 'Updated ' + when(p.last_message_at), canLaunch: !!p.can_launch && !isOp,
          open: () => this.openRelay(p.id) };
      });
    }

    if (s.route !== 'relay') return out;

    // ── Project page ──
    const rel = this._relay;
    const p = rel && rel.data;
    const c = chip(p ? p.status : '');
    const fileHref = f => boot.restRoot + 'captaincore/v1/ai-relay/projects/' + (p ? p.id : 0) + '/files/' + f.id + '?_wpnonce=' + encodeURIComponent(boot.nonce || '');
    const msgs = p ? (p.messages || []) : [];
    const ups = (this._rlUploads || []).map(u => ({
      name: u.name, meta: u.state === 'uploading' ? 'Uploading…' : u.state === 'error' ? u.error : human(u.size),
      metaFg: u.state === 'error' ? 'var(--bad)' : 'var(--ink-dim)', drop: () => this.relayDropUpload(u)
    }));
    const open = p && (p.status === 'building' || p.status === 'preview');
    const free = !!(p && p.billing === 'none');

    return Object.assign(out, {
      rlBack: () => { this.setState({ route: 'sites' }); },
      rlLoading: !!(rel && rel.loading), rlErr: rel ? rel.err : '', rlHasErr: !!(rel && rel.err),
      rlReady: !!p,
      rlName: p ? p.name : '', rlAccount: p ? p.account : '',
      rlStatusLabel: p ? p.status_label : '', rlChipBg: c.bg, rlChipFg: c.fg,
      rlStarted: p ? 'Started ' + when(p.created_at) : '',
      rlSource: p && p.mode === 'port' ? 'Porting ' + p.source_url : 'New site from uploaded files and notes',
      rlHasPreview: !!(p && p.preview_url), rlPreviewUrl: p ? p.preview_url : '',
      rlOpenPreview: () => p && this.safeOpen(p.preview_url),
      rlBuilding: !!(p && p.status === 'building'),
      rlBuildingNote: 'We are building your site from what you sent. Once it is up you can open it from here while we work. Add anything new below. ' +
        (free ? 'Launch becomes available when the site is ready. There is no charge for this build.'
              : 'Launch becomes available when the site is ready, and nothing is charged until you press it.'),
      rlCanLaunch: !!(p && p.can_launch && !isOp),
      rlLaunchLabel: s.rlLaunching ? 'Launching…' : free ? 'Launch · No charge' : 'Launch · $' + Math.round(p ? p.price : 0) + '/year',
      rlLaunch: () => this.relayLaunch(),
      rlLaunched: !!(p && p.status === 'launched'),
      rlOpenSite: () => p && p.site_id && this.openSite(p.site_id),
      rlHasSite: !!(p && p.status === 'launched' && p.site_id),
      rlMessages: msgs.map(m => ({
        name: m.name || (m.author === 'staff' ? 'Anchor' : 'Customer'),
        when: when(m.created_at), body: m.body || '', hasBody: !!(m.body || '').trim(),
        // Internal notes are staff-only (the server never sends them to a customer).
        bg: m.author === 'internal' ? 'var(--panel-2)' : m.author === 'staff' ? 'var(--brand-soft)' : 'var(--paper)',
        tag: m.author === 'internal' ? 'Internal note' : m.author === 'staff' ? 'Team' : '',
        hasTag: m.author === 'staff' || m.author === 'internal',
        files: (m.files || []).map(f => ({ name: f.name, size: human(f.size || 0), href: fileHref(f) })),
        hasFiles: !!(m.files || []).length
      })),
      rlCanPost: !!open,
      rlBody: s.rlBody || '', onRlBody: e => this.setState({ rlBody: e.target.value }),
      rlFileRef: el => this.relayBindFile(el),
      rlPick: () => { if (this._rlFileEl) this._rlFileEl.click(); },
      rlUploads: ups, rlHasUploads: ups.length > 0,
      rlSend: () => this.relaySend(), rlSendLabel: s.rlSending ? 'Sending…' : 'Send',
      // Staff controls
      rlIsOp: isOp && !!open,
      rlStatusOpts: [['building', 'Building'], ['preview', 'Preview ready'], ['cancelled', 'Cancelled']].map(([v, l]) => ({
        label: l, bg: s.rlStatus === v ? 'var(--brand)' : 'var(--paper)', fg: s.rlStatus === v ? 'white' : 'var(--ink)',
        go: () => this.setState({ rlStatus: v })
      })),
      rlPreviewInput: s.rlPreview || '', onRlPreview: e => this.setState({ rlPreview: e.target.value }),
      rlLoginInput: s.rlLogin || '', onRlLogin: e => this.setState({ rlLogin: e.target.value }),
      rlSiteInput: s.rlSite || '', onRlSite: e => this.setState({ rlSite: e.target.value.replace(/[^0-9]/g, '') }),
      rlSave: () => this.relaySave(), rlSaveLabel: s.rlSaving ? 'Saving…' : 'Save',
      rlOwner: p && p.user_email ? p.user_email : '',
      // Billing follows the linked site: one not on the staff-held account is
      // an existing customer site, so Launch charges nothing.
      rlBilling: !p ? '' : free ? 'No charge. Site #' + p.site_id + ' is an existing customer site, so Launch makes no plan, invoice or account change.'
        : p.site_id ? 'Launch charges $' + Math.round(p.price) + '/year and moves site #' + p.site_id + ' to the customer.'
        : 'Launch charges $' + Math.round(p.price) + '/year. Link an existing customer site instead to make this build free.'
    });
  }

});
