// CaptainCore v3 — Status page (mixin). Operators open incidents, post
// updates, resolve them and write postmortems for the public status page
// (CaptainCore\StatusPage); everyone gets a Status alerts toggle in Profile.
//   GET    /status/incidents?limit&offset&search → { incidents, total, subscribers, page_url }
//   POST   /status/incidents { kind, title, impact, status, body, notify, scheduled_for, scheduled_until }
//   GET    /status/incidents/{id} → incident + updates + subscribers
//   PUT    /status/incidents/{id} { title, impact, started_at, resolved_at, … }
//   DELETE /status/incidents/{id}
//   POST   /status/incidents/{id}/updates { status, body, notify, impact }
//   PUT    /status/incidents/{id}/postmortem { body, publish, notify }
//   PUT    /status/updates/{id} { body }   DELETE /status/updates/{id}
//   GET    /status/subscribers
//   GET|PUT /me/status-alerts { enabled }

Object.assign(Component.prototype, {

  STP_INCIDENT: [['investigating', 'Investigating'], ['identified', 'Identified'], ['monitoring', 'Monitoring'], ['resolved', 'Resolved']],
  STP_MAINT: [['scheduled', 'Scheduled'], ['in_progress', 'In progress'], ['verifying', 'Verifying'], ['completed', 'Completed']],
  STP_IMPACT: [['none', 'None'], ['minor', 'Minor'], ['major', 'Major'], ['critical', 'Critical']],

  stpTone(v) {
    return ({ critical: 'var(--bad)', major: 'var(--warn)', minor: '#c48a00', none: 'var(--ink-dim)', maintenance: '#5b5bd6',
      resolved: 'var(--ok)', completed: 'var(--ok)', monitoring: 'var(--brand)', verifying: 'var(--brand)',
      identified: 'var(--warn)', investigating: 'var(--bad)', scheduled: '#5b5bd6', in_progress: '#5b5bd6' })[v] || 'var(--ink-dim)';
  },
  stpLabel(v) {
    const all = [...this.STP_INCIDENT, ...this.STP_MAINT, ...this.STP_IMPACT, ['maintenance', 'Maintenance']];
    const hit = all.find(([k]) => k === v);
    return hit ? hit[1] : String(v || '');
  },
  stpWhen(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return isNaN(d) ? '' : d.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
  },
  // <input type="datetime-local"> wants local "YYYY-MM-DDTHH:MM".
  stpLocalInput(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d)) return '';
    const p = n => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
  },
  stpIso(local) { if (!local) return ''; const d = new Date(local); return isNaN(d) ? '' : d.toISOString(); },

  // ── loading ─────────────────────────────────────────────────────────

  loadStatus(force) {
    if (this._stpLoading || (this._stp && !force)) return;
    this._stpLoading = true;
    const q = (this.state.stpQ || '').trim();
    const limit = this._stpLimit || 50;
    this.api('/status/incidents?limit=' + limit + (q ? '&search=' + encodeURIComponent(q) : '')).then(res => {
      this._stpLoading = false;
      this._stp = res && Array.isArray(res.incidents) ? res : { incidents: [], total: 0, subscribers: 0, failed: true };
      this.setState({});
    }).catch(() => { this._stpLoading = false; this._stp = { incidents: [], total: 0, subscribers: 0, failed: true }; this.setState({}); });
  },

  openStatusIncident(id) {
    if (((window.CC_BOOT && window.CC_BOOT.dcRole) || 'operator') !== 'operator') { this.setState({ route: 'home' }); return; }
    this.setState({ route: 'status', stpId: Number(id) || null, stpEditing: false, stpEditUpd: null });
    this._stpOne = null;
    if (id) this.loadStatusIncident(id);
  },

  loadStatusIncident(id) {
    this.api('/status/incidents/' + id).then(res => {
      if (!res || res.code) { this._stpOne = { failed: true }; this.setState({}); return; }
      this._stpOne = res;
      const st = this.state;
      // Seed the composer and postmortem draft once per incident.
      const seed = st.stpSeeded !== res.id;
      this.setState(seed ? {
        stpSeeded: res.id, stpUpdStatus: res.status, stpUpdBody: '', stpUpdNotify: !['resolved', 'completed'].includes(res.status), stpUpdOpen: false,
        stpPm: res.postmortem || '', stpPmNotify: !res.postmortem_published_at && res.postmortem_notify === ''
      } : {});
      // Keep polling while email is going out so the counts move.
      const sending = (res.updates || []).some(u => u.notify === 'pending' || u.notify === 'sending') || ['pending', 'sending'].includes(res.postmortem_notify);
      clearTimeout(this._stpPoll);
      if (sending && this.state.route === 'status' && this.state.stpId === res.id) this._stpPoll = setTimeout(() => this.loadStatusIncident(res.id), 4000);
    }).catch(() => { this._stpOne = { failed: true }; this.setState({}); });
  },

  // ── writes ──────────────────────────────────────────────────────────

  stpNew(kind) {
    this.setState({ stpCompose: true, stpKind: kind, stpTitle: '', stpBody: '', stpImpact: 'minor',
      stpStatus: kind === 'maintenance' ? 'scheduled' : 'investigating', stpNotify: true, stpFrom: '', stpUntil: '' });
  },

  stpCreate() {
    const s = this.state;
    const title = (s.stpTitle || '').trim(), body = (s.stpBody || '').trim();
    if (!title) { this.toast('Give the incident a title', { kind: 'error' }); return; }
    if (!body) { this.toast('Write the first update', { kind: 'error' }); return; }
    if (s.stpKind === 'maintenance' && !s.stpFrom) { this.toast('Pick when the maintenance starts', { kind: 'error' }); return; }
    const subs = (this._stp && this._stp.subscribers) || 0;
    const tid = this.toast(s.stpNotify ? 'Publishing and emailing ' + subs + ' subscribers…' : 'Publishing…', { kind: 'loading' });
    this.api('/status/incidents', { method: 'POST', body: {
      kind: s.stpKind, title, body, impact: s.stpImpact, status: s.stpStatus, notify: !!s.stpNotify,
      scheduled_for: this.stpIso(s.stpFrom), scheduled_until: this.stpIso(s.stpUntil)
    } }).then(res => {
      if (!res || res.code) { this.updateToast(tid, (res && res.message) || 'Could not publish', { kind: 'error' }); return; }
      this.updateToast(tid, 'Published to the status page', { kind: 'success' });
      this.setState({ stpCompose: false });
      this._stp = null; this.loadStatus(true);
      this.openStatusIncident(res.id);
    }).catch(e => this.updateToast(tid, (e && e.message) || 'Could not publish', { kind: 'error' }));
  },

  stpPostUpdate() {
    const s = this.state, one = this._stpOne;
    const body = (s.stpUpdBody || '').trim();
    if (!one || !body) { this.toast('Write the update first', { kind: 'error' }); return; }
    const tid = this.toast(s.stpUpdNotify ? 'Posting and emailing ' + (one.subscribers || 0) + ' subscribers…' : 'Posting…', { kind: 'loading' });
    this.api('/status/incidents/' + one.id + '/updates', { method: 'POST', body: { status: s.stpUpdStatus, body, notify: !!s.stpUpdNotify } }).then(res => {
      if (!res || res.code) { this.updateToast(tid, (res && res.message) || 'Could not post the update', { kind: 'error' }); return; }
      this.updateToast(tid, 'Update posted', { kind: 'success' });
      this.setState({ stpUpdBody: '', stpUpdStatus: res.status });
      this._stp = null;
      this.loadStatusIncident(one.id);
    }).catch(e => this.updateToast(tid, (e && e.message) || 'Could not post the update', { kind: 'error' }));
  },

  stpSaveEdit() {
    const s = this.state, one = this._stpOne;
    if (!one) return;
    const body = { title: (s.stpEdTitle || '').trim(), impact: s.stpEdImpact, started_at: this.stpIso(s.stpEdStart) };
    if (one.resolved_at || s.stpEdEnd) body.resolved_at = this.stpIso(s.stpEdEnd);
    this.api('/status/incidents/' + one.id, { method: 'PUT', body }).then(res => {
      if (!res || res.code) { this.toast((res && res.message) || 'Could not save', { kind: 'error' }); return; }
      this.toast('Saved', { kind: 'success' });
      this._stpOne = res; this._stp = null;
      this.setState({ stpEditing: false });
    }).catch(() => this.toast('Could not save', { kind: 'error' }));
  },

  async stpDelete() {
    const one = this._stpOne;
    if (!one || !(await this.uiConfirm('Delete "' + one.title + '" and its ' + (one.updates || []).length + ' updates from the status page? Emails already sent are not recalled.', { label: 'Delete incident' }))) return;
    this.api('/status/incidents/' + one.id, { method: 'DELETE' }).then(() => {
      this.toast('Incident deleted', { kind: 'success' });
      this._stp = null; this.setState({ stpId: null }); this.loadStatus(true);
    }).catch(() => this.toast('Could not delete', { kind: 'error' }));
  },

  stpSaveUpdateText(u) {
    const body = (this.state.stpEditUpdBody || '').trim();
    if (!body) return;
    this.api('/status/updates/' + u.id, { method: 'PUT', body: { body } }).then(res => {
      if (!res || res.code) { this.toast((res && res.message) || 'Could not save', { kind: 'error' }); return; }
      this.setState({ stpEditUpd: null }); this.loadStatusIncident(this._stpOne.id);
    }).catch(() => this.toast('Could not save', { kind: 'error' }));
  },

  async stpDeleteUpdate(u) {
    if (!(await this.uiConfirm('Delete this ' + this.stpLabel(u.status).toLowerCase() + ' update? Emails already sent are not recalled.', { label: 'Delete update' }))) return;
    this.api('/status/updates/' + u.id, { method: 'DELETE' }).then(() => this.loadStatusIncident(this._stpOne.id))
      .catch(() => this.toast('Could not delete', { kind: 'error' }));
  },

  stpSavePostmortem(publish) {
    const s = this.state, one = this._stpOne;
    if (!one) return;
    const body = (s.stpPm || '').trim();
    if (publish && !body) { this.toast('Write the postmortem first', { kind: 'error' }); return; }
    const notify = publish && !!s.stpPmNotify;
    const tid = this.toast(publish ? (notify ? 'Publishing and emailing subscribers…' : 'Publishing…') : 'Saving draft…', { kind: 'loading' });
    this.api('/status/incidents/' + one.id + '/postmortem', { method: 'PUT', body: { body, publish, notify } }).then(res => {
      if (!res || res.code) { this.updateToast(tid, (res && res.message) || 'Could not save', { kind: 'error' }); return; }
      this.updateToast(tid, publish ? 'Postmortem published' : (one.postmortem_published_at ? 'Postmortem unpublished, saved as a draft' : 'Draft saved'), { kind: 'success' });
      this._stp = null;
      this.loadStatusIncident(one.id);
    }).catch(e => this.updateToast(tid, (e && e.message) || 'Could not save', { kind: 'error' }));
  },

  // ── view values ─────────────────────────────────────────────────────

  statusVals(s) {
    if (s.route !== 'status') return { showStatus: false };
    if (!this._stp) this.loadStatus();
    const data = this._stp || { incidents: [], total: 0, subscribers: 0 };
    const subs = data.subscribers || 0;
    const chip = (opts, cur, set) => opts.map(([v, label]) => ({ label, go: () => this.setState({ [set]: v }),
      bg: cur === v ? 'var(--paper)' : 'transparent', fg: cur === v ? 'var(--ink)' : 'var(--ink-dim)', sh: cur === v ? 'var(--shadow-sm)' : 'none' }));
    const row = i => {
      const closed = i.status === 'resolved' || i.status === 'completed';
      const impact = i.kind === 'maintenance' ? 'maintenance' : i.impact;
      return {
        title: i.title, open: () => this.openStatusIncident(i.id),
        dot: this.stpTone(impact), impact: this.stpLabel(impact),
        statusLabel: this.stpLabel(i.status), statusFg: this.stpTone(i.status),
        when: this.stpWhen(i.kind === 'maintenance' && i.scheduled_for ? i.scheduled_for : i.started_at),
        meta: (i.update_count || 0) + ' update' + (i.update_count === 1 ? '' : 's') + (i.postmortem_published_at ? ' · postmortem' : i.postmortem ? ' · postmortem draft' : ''),
        isOpen: !closed
      };
    };
    const rows = data.incidents.map(row);
    const base = {
      showStatus: true, stpList: !s.stpId, stpDetail: !!s.stpId,
      stpSummary: this._stp ? data.total + ' incidents · ' + subs + ' subscriber' + (subs === 1 ? '' : 's') : 'Loading…',
      stpPageUrl: data.page_url || '#', stpFailed: !!data.failed,
      stpOpenRows: rows.filter(r => r.isOpen), stpHasOpen: rows.some(r => r.isOpen),
      stpPastRows: rows.filter(r => !r.isOpen), stpNoPast: !!this._stp && !rows.some(r => !r.isOpen),
      stpLoaded: !!this._stp, stpLoading: !this._stp,
      stpMore: this._stp && data.incidents.length < data.total,
      stpLoadMore: () => { this._stpLimit = (this._stpLimit || 50) + 50; this.loadStatus(true); },
      stpQ: s.stpQ || '', onStpQ: e => { this.setState({ stpQ: e.target.value }); clearTimeout(this._stpQt); this._stpQt = setTimeout(() => this.loadStatus(true), 300); },
      stpNewIncident: () => this.stpNew('incident'), stpNewMaint: () => this.stpNew('maintenance'),
      // composer
      stpCompose: !!s.stpCompose, stpCloseCompose: () => this.setState({ stpCompose: false }),
      stpComposeTitle: s.stpKind === 'maintenance' ? 'Schedule maintenance' : 'New incident',
      stpIsMaint: s.stpKind === 'maintenance', stpIsIncident: s.stpKind !== 'maintenance',
      stpTitle: s.stpTitle || '', onStpTitle: e => this.setState({ stpTitle: e.target.value }),
      stpBody: s.stpBody || '', onStpBody: e => this.setState({ stpBody: e.target.value }),
      stpImpacts: chip(this.STP_IMPACT, s.stpImpact, 'stpImpact'),
      stpStatuses: chip(s.stpKind === 'maintenance' ? this.STP_MAINT : this.STP_INCIDENT, s.stpStatus, 'stpStatus'),
      stpFrom: s.stpFrom || '', onStpFrom: e => this.setState({ stpFrom: e.target.value }),
      stpUntil: s.stpUntil || '', onStpUntil: e => this.setState({ stpUntil: e.target.value }),
      stpNotifyOn: !!s.stpNotify, stpNotifyOff: !s.stpNotify, stpToggleNotify: () => this.setState({ stpNotify: !s.stpNotify }),
      stpNotifyLabel: 'Email ' + subs + ' subscriber' + (subs === 1 ? '' : 's'),
      stpCreate: () => this.stpCreate()
    };
    if (!s.stpId) return base;

    // ── one incident ──
    const one = this._stpOne;
    const ready = !!(one && !one.failed && one.id === s.stpId);
    if (!ready) return { ...base, stpOneReady: false, stpOneFailed: !!(one && one.failed), stpBack: () => this.setState({ stpId: null }) };
    const isMaint = one.kind === 'maintenance';
    const impact = isMaint ? 'maintenance' : one.impact;
    const updSubs = one.subscribers || 0;
    const pmPublished = !!one.postmortem_published_at;
    const pmSendNote = one.postmortem_notify === 'sent' ? 'Emailed to ' + one.postmortem_notified + ' subscriber' + (one.postmortem_notified === 1 ? '' : 's') + '.'
      : ['pending', 'sending'].includes(one.postmortem_notify) ? 'Emailing… ' + one.postmortem_notified + ' sent so far.' : '';
    return {
      ...base, stpOneReady: true, stpOneFailed: false,
      stpBack: () => { clearTimeout(this._stpPoll); this.setState({ stpId: null }); },
      stpOneTitle: one.title, stpOneUrl: one.url,
      stpOneStatus: this.stpLabel(one.status), stpOneStatusBg: 'color-mix(in srgb,' + this.stpTone(one.status) + ' 14%,transparent)', stpOneStatusFg: this.stpTone(one.status),
      stpOneImpact: this.stpLabel(impact) + (isMaint ? '' : ' impact'), stpOneDot: this.stpTone(impact),
      stpOneTimes: (isMaint && one.scheduled_for ? 'Scheduled ' + this.stpWhen(one.scheduled_for) + (one.scheduled_until ? ' until ' + this.stpWhen(one.scheduled_until) : '') : 'Started ' + this.stpWhen(one.started_at))
        + (one.resolved_at ? ' · ' + (isMaint ? 'Completed ' : 'Resolved ') + this.stpWhen(one.resolved_at) : ''),
      stpOneImported: !!one.source_id,
      stpDelete: () => this.stpDelete(),
      // edit details
      stpEditing: !!s.stpEditing, stpNotEditingHead: !s.stpEditing,
      stpStartEdit: () => this.setState({ stpEditing: true, stpEdTitle: one.title, stpEdImpact: one.impact, stpEdStart: this.stpLocalInput(one.started_at), stpEdEnd: this.stpLocalInput(one.resolved_at) }),
      stpCancelEdit: () => this.setState({ stpEditing: false }),
      stpEdTitle: s.stpEdTitle || '', onStpEdTitle: e => this.setState({ stpEdTitle: e.target.value }),
      stpEdImpacts: isMaint ? [] : chip(this.STP_IMPACT, s.stpEdImpact, 'stpEdImpact'), stpEdShowImpact: !isMaint,
      stpEdStart: s.stpEdStart || '', onStpEdStart: e => this.setState({ stpEdStart: e.target.value }),
      stpEdEnd: s.stpEdEnd || '', onStpEdEnd: e => this.setState({ stpEdEnd: e.target.value }),
      stpSaveEdit: () => this.stpSaveEdit(),
      // post an update (folded on a closed incident: a late note is rare, and an accidental email is worse)
      stpUpdShow: !['resolved', 'completed'].includes(one.status) || !!s.stpUpdOpen,
      stpUpdFolded: ['resolved', 'completed'].includes(one.status) && !s.stpUpdOpen,
      stpUpdUnfold: () => this.setState({ stpUpdOpen: true }),
      stpUpdStatuses: chip(isMaint ? this.STP_MAINT : this.STP_INCIDENT, s.stpUpdStatus, 'stpUpdStatus'),
      stpUpdBody: s.stpUpdBody || '', onStpUpdBody: e => this.setState({ stpUpdBody: e.target.value }),
      stpUpdNotifyOn: !!s.stpUpdNotify, stpUpdNotifyOff: !s.stpUpdNotify, stpToggleUpdNotify: () => this.setState({ stpUpdNotify: !s.stpUpdNotify }),
      stpUpdNotifyLabel: 'Email ' + updSubs + ' subscriber' + (updSubs === 1 ? '' : 's'),
      stpUpdPlaceholder: (s.stpUpdStatus === 'resolved' || s.stpUpdStatus === 'completed') ? 'What was fixed, and anything customers should do now… Markdown supported.' : 'What we know, what we are doing, when the next update comes… Markdown supported.',
      stpUpdLabel: (s.stpUpdStatus === 'resolved' ? 'Resolve' : s.stpUpdStatus === 'completed' ? 'Complete' : 'Post update'),
      stpPostUpdate: () => this.stpPostUpdate(),
      // timeline
      stpUpdates: (one.updates || []).map(u => {
        const editing = s.stpEditUpd === u.id;
        return {
          label: this.stpLabel(u.status), fg: this.stpTone(u.status), when: this.stpWhen(u.created_at),
          by: u.author ? '· ' + u.author : '',
          sent: u.notify === 'sent' ? '· emailed ' + u.notified : (u.notify === 'pending' || u.notify === 'sending') ? '· emailing… ' + u.notified + ' sent' : '',
          bodyRef: el => { if (!el) return; if (el._md === u.body_html) return; el._md = u.body_html; el.innerHTML = u.body_html;
            el.querySelectorAll('a[href]').forEach(a => { a.target = '_blank'; a.rel = 'noopener noreferrer'; }); },
          editing, notEditing: !editing,
          edit: () => this.setState({ stpEditUpd: u.id, stpEditUpdBody: u.body }),
          editBody: s.stpEditUpdBody || '', onEditBody: e => this.setState({ stpEditUpdBody: e.target.value }),
          save: () => this.stpSaveUpdateText(u), cancel: () => this.setState({ stpEditUpd: null }),
          del: () => this.stpDeleteUpdate(u)
        };
      }),
      // postmortem
      stpPm: s.stpPm || '', onStpPm: e => this.setState({ stpPm: e.target.value }),
      stpPmState: pmPublished ? 'Published ' + this.stpWhen(one.postmortem_published_at) : (one.postmortem ? 'Draft, not on the page yet' : 'Not written'),
      stpPmPublished: pmPublished, stpPmUnpublished: !pmPublished,
      stpPmSendNote: pmSendNote, stpPmHasSendNote: !!pmSendNote,
      stpPmCanNotify: !pmPublished && one.postmortem_notify === '',
      stpPmNotifyOn: !!s.stpPmNotify, stpPmNotifyOff: !s.stpPmNotify, stpTogglePmNotify: () => this.setState({ stpPmNotify: !s.stpPmNotify }),
      stpPmNotifyLabel: 'Email ' + updSubs + ' subscriber' + (updSubs === 1 ? '' : 's'),
      stpPmSaveDraft: () => this.stpSavePostmortem(false),
      stpPmPublish: () => this.stpSavePostmortem(true),
      stpPmPublishLabel: pmPublished ? 'Save' : 'Publish postmortem',
      stpPmUnpublish: () => this.stpSavePostmortem(false)
    };
  },

  // ── Profile: Status alerts toggle (any user) ────────────────────────

  statusAlertVals(s) {
    if (s.route !== 'profile') return {};
    if (this._stpMe === undefined) {
      this._stpMe = null;
      this.api('/me/status-alerts').then(res => { this._stpMe = res && typeof res.enabled === 'boolean' ? res : { enabled: false, page_url: '' }; this.setState({}); })
        .catch(() => { this._stpMe = { enabled: false, page_url: '', failed: true }; this.setState({}); });
    }
    const me = this._stpMe;
    const on = !!(me && me.enabled);
    return {
      stpMeReady: !!me, stpMeOn: on,
      stpMeTrack: on ? 'var(--brand)' : 'var(--panel-2)', stpMeKnob: on ? 'translateX(18px)' : 'translateX(0)',
      stpMePageUrl: (me && me.page_url) || '#',
      stpMeNote: on ? 'You will get an email when an outage is posted, updated or resolved, and before scheduled maintenance.' : 'Off. You will not get outage or maintenance emails.',
      stpMeToggle: () => {
        if (!me || this._stpMeBusy) return;
        this._stpMeBusy = true;
        const want = !on;
        this._stpMe = Object.assign({}, me, { enabled: want }); this.setState({});
        this.api('/me/status-alerts', { method: 'PUT', body: { enabled: want } }).then(res => {
          this._stpMeBusy = false;
          if (!res || res.code) throw new Error((res && res.message) || 'failed');
          this._stpMe = Object.assign({}, me, { enabled: !!res.enabled }); this.setState({});
          this.toast(res.enabled ? 'Status alerts on' : 'Status alerts off', { kind: 'success' });
        }).catch(e => { this._stpMeBusy = false; this._stpMe = me; this.setState({}); this.toast((e && e.message) || 'Could not change status alerts', { kind: 'error' }); });
      }
    };
  }

});
