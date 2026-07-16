// CaptainCore v3 — Reports real-data layer (mixin).
// Reports are server-rendered HTML. Preview returns { html } shown in an
// iframe dialog; send emails it; schedules are CRUD rows.
//   POST /report/preview        { site_ids, start_date, end_date } → { html }
//   POST /account-report/preview{ account_id, start_date, end_date } (admin)
//   POST /report/send           { site_ids, start_date, end_date, recipient }
//   POST /account-report/send   { account_id, … } (admin)
//   POST /report/default-recipient { site_ids } → { email }
//   GET/POST/DELETE /scheduled-reports  (record: site_ids|account_id, interval,
//     recipient, next_run, site_names[]|account_name). recipient is singular.

Object.assign(Component.prototype, {

  loadSchedules(force) {
    if (this._schedLoading || (this._sched && !force)) return;
    this._schedLoading = true;
    this.api('/scheduled-reports').then(res => {
      this._schedLoading = false;
      this._sched = Array.isArray(res) ? res : [];
      this.setState({});
    }).catch(() => { this._schedLoading = false; this._sched = []; this.setState({}); });
  },

  // Range preset → {start_date, end_date} as Y-m-d.
  reportRange(label) {
    const iso = d => d.toISOString().slice(0, 10);
    const now = new Date();
    if (label === 'Last quarter') {
      const end = new Date(now.getFullYear(), now.getMonth(), 0);
      const start = new Date(end.getFullYear(), end.getMonth() - 2, 1);
      return { start_date: iso(start), end_date: iso(end) };
    }
    if (label === 'This year') return { start_date: now.getFullYear() + '-01-01', end_date: iso(now) };
    // Last month (default)
    const end = new Date(now.getFullYear(), now.getMonth(), 0);
    const start = new Date(end.getFullYear(), end.getMonth(), 1);
    return { start_date: iso(start), end_date: iso(end) };
  },

  reportTargetBody() {
    const s = this.state;
    if (s.repMode === 'Account') {
      const a = this.ACCOUNTS.find(x => x.name === s.repTarget) || this.ACCOUNTS[0];
      return a ? { account_id: Number(a.id) } : null;
    }
    const f = this.FLEET.find(x => x.name === s.repTarget) || this.FLEET[0];
    return f ? { site_ids: [Number(f.id)] } : null;
  },

  reportBase() { return this.state.repMode === 'Account' ? '/account-report' : '/report'; },

  previewReport() {
    const body = this.reportTargetBody();
    if (!body) return;
    this.setState({ repPreviewOpen: true, repPreviewHtml: '', repPreviewLoading: true });
    this.api(this.reportBase() + '/preview', { method: 'POST', body: { ...body, ...this.reportRange(this.state.repRange) } })
      .then(res => this.setState({ repPreviewHtml: (res && res.html) || '<p style="padding:24px;font-family:sans-serif">No report content.</p>', repPreviewLoading: false }))
      .catch(() => this.setState({ repPreviewHtml: '<p style="padding:24px;font-family:sans-serif">Preview failed.</p>', repPreviewLoading: false }));
  },

  sendReport() {
    const body = this.reportTargetBody();
    const recipient = (this.state.repEmail || '').trim();
    if (!body || !recipient) { this.setState({ repSendMsg: 'Enter a recipient email first.' }); return; }
    if (!confirm('Send this report to ' + recipient + '?')) return;
    this.setState({ repSendMsg: 'Sending…' });
    this.api(this.reportBase() + '/send', { method: 'POST', body: { ...body, ...this.reportRange(this.state.repRange), recipient } })
      .then(res => this.setState({ repSendMsg: (res && res.message) || 'Report sent.', repPreviewOpen: false }))
      .catch(() => this.setState({ repSendMsg: 'Send failed.' }));
  },

  fetchReportRecipient() {
    const body = this.reportTargetBody();
    if (!body) return;
    this.api(this.reportBase() + '/default-recipient', { method: 'POST', body }).then(res => {
      if (res && res.email && !(this.state.repEmail || '').trim()) this.setState({ repEmail: res.email });
    }).catch(() => {});
  },

  realReportsVals(s) {
    if (s.route === 'reports' && !this._sched && !this._schedLoading) setTimeout(() => this.loadSchedules(), 0);
    if (s.route === 'reports' && !this._repRecipFetched) { this._repRecipFetched = true; setTimeout(() => this.fetchReportRecipient(), 0); }
    const reload = () => this.loadSchedules(true);
    const intLabel = { monthly: 'Monthly', quarterly: 'Quarterly', yearly: 'Yearly' };
    const schedRows = (this._sched || []).map(r => ({
      id: r.scheduled_report_id,
      target: r.account_name || (r.site_names || []).join(', ') || 'report',
      interval: intLabel[r.interval] || r.interval,
      next: (r.next_run || '').slice(0, 10),
      recipients: r.recipient || '',
      del: () => this.api('/scheduled-reports/' + r.scheduled_report_id, { method: 'DELETE' }).then(reload).catch(() => {})
    }));
    return {
      repPreview: () => this.previewReport(),
      repSend: () => this.sendReport(),
      onRepEmail: e => this.setState({ repEmail: e.target.value, repSendMsg: '' }),
      repSendMsg: s.repSendMsg || '', repHasSendMsg: !!(s.repSendMsg || ''),
      repPreviewOpen: !!s.repPreviewOpen, repPreviewHtml: s.repPreviewHtml || '',
      repPreviewLoading: !!s.repPreviewLoading, repPreviewReady: !!s.repPreviewOpen && !s.repPreviewLoading,
      closeRepPreview: () => this.setState({ repPreviewOpen: false }),
      schedRows, schedEmpty: !schedRows.length,
      addSchedule: () => { const body = this.reportTargetBody();
        const recipient = (this.state.repEmail || '').trim();
        if (!body || !recipient) { this.setState({ repSendMsg: 'Enter a recipient to schedule.' }); return; }
        this.api('/scheduled-reports', { method: 'POST',
          body: { ...body, interval: (this.state.repInt || 'Monthly').toLowerCase(), recipient } })
          .then(reload).catch(() => {}); }
    };
  }

});
