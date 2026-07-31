// CaptainCore v3 — fleet bulk-process monitor (mixin).
// Surfaces the CLI's bulk runs (captaincore ssh @staging, update, backup …)
// in the dock + topbar. The Manager proxies the CLI dispatch server:
//   GET    /progress/        → list of runs (progress meta + log counts)
//   GET    /progress/{pid}   → detail (completed_sites + pending_sites)
//   DELETE /progress/{pid}   → kill (running) or dismiss stale files
// Operator-only — the routes are admin-gated server-side, and polling as a
// customer would 403 (api() throws 'auth'); gate on dcRole before fetching.

Object.assign(Component.prototype, {

  initBulkProgress() {
    const boot = window.CC_BOOT || {};
    if (boot.dcRole !== 'operator') return;
    this.fetchBulkProgress();
    this._bulkTimer = setInterval(() => this.fetchBulkProgress(), 15000);
  },

  fetchBulkProgress() {
    this.api('/progress/').then(list => {
      const ops = Array.isArray(list) ? list : [];
      // A finished run deletes its progress files, so it just vanishes from
      // the list — announce the completion instead of silently dropping it.
      (this._bulkPrev || []).forEach(prev => {
        if (prev.running && !ops.some(o => o.pid === prev.pid) && this.toast) {
          this.toast(prev.command + ' finished across ' + prev.total + ' sites', { kind: 'success' });
        }
      });
      this._bulkPrev = ops;
      this.setState({ bulkOps: ops });
      const pid = this.state.bpPid;
      if (pid && ops.some(o => o.pid === pid)) this.loadBulkDetail(pid, true);
      if (pid && !ops.some(o => o.pid === pid)) this.setState({ bpPid: 0, bpDetail: null });
    }).catch(() => { /* CLI server unreachable — keep the last known list */ });
  },

  openBulkOp(pid) {
    this.setState({ bpPid: pid, bpTab: 'pending', bpDetail: null, bpLoading: true, bpKilling: false });
    this.loadBulkDetail(pid);
  },

  // silent: a background refresh while the dialog is open (no spinner).
  loadBulkDetail(pid, silent) {
    if (!silent) this.setState({ bpLoading: true });
    this.api('/progress/' + pid).then(detail => {
      if (this.state.bpPid !== pid) return;
      this.setState({ bpDetail: detail && detail.pid ? detail : null, bpLoading: false });
    }).catch(() => { if (this.state.bpPid === pid) this.setState({ bpLoading: false }); });
  },

  killBulkOp(op) {
    if (!confirm('Kill "' + op.command + '" (PID ' + op.pid + ')? ' + (op.total - op.completed) + ' sites are still pending.')) return;
    this.setState({ bpKilling: true });
    this.api('/progress/' + op.pid, { method: 'DELETE' }).then(() => {
      this.setState({ bpKilling: false, bpPid: 0, bpDetail: null });
      if (this.toast) this.toast('Killed ' + op.command + ' (PID ' + op.pid + ')', { kind: 'info' });
      this._bulkPrev = (this._bulkPrev || []).filter(o => o.pid !== op.pid);
      this.fetchBulkProgress();
    }).catch(() => {
      this.setState({ bpKilling: false });
      if (this.toast) this.toast('Failed to kill process', { kind: 'error' });
    });
  },

  // Stale run (process died) — DELETE just removes the leftover files.
  dismissBulkOp(pid) {
    this.api('/progress/' + pid, { method: 'DELETE' }).then(() => {
      this._bulkPrev = (this._bulkPrev || []).filter(o => o.pid !== pid);
      this.setState(st => ({ bulkOps: st.bulkOps.filter(o => o.pid !== pid), bpPid: st.bpPid === pid ? 0 : st.bpPid }));
    }).catch(() => {});
  },

  computeBulkOps(s) {
    const ops = s.bulkOps || [];
    const targetsOf = op => op.target ? op.target.split(/\s+/).filter(Boolean).length : op.total;

    const bulkRows = ops.map(op => ({
      pid: op.pid,
      label: op.command,
      sub: targetsOf(op) + ' sites' + (op.args ? ' · ' + op.args : ''),
      dot: op.running ? 'var(--brand)' : 'var(--warn)',
      dotAnim: op.running ? 'ccpulse 1.4s ease infinite' : 'none',
      barW: Math.min(100, op.percent || 0) + '%',
      barColor: op.failed > 0 ? 'var(--warn)' : 'var(--brand)',
      right: op.completed + '/' + op.total + ' · ' + op.percent + '%'
        + (op.running && op.eta ? ' · ETA ' + op.eta : ''),
      staleShow: !op.running,
      pick: () => this.openBulkOp(op.pid),
      dismiss: (e) => { e.stopPropagation(); this.dismissBulkOp(op.pid); }
    }));

    const sel = s.bpPid ? ops.find(o => o.pid === s.bpPid) : null;
    const det = sel && s.bpDetail && s.bpDetail.pid === sel.pid ? s.bpDetail : null;

    let bpVals = { bpOpen: false };
    if (sel) {
      const completedOk = det ? (det.completed_sites || []).filter(e => e.exit_code === 0) : [];
      const failedEntries = det ? (det.completed_sites || []).filter(e => e.exit_code !== 0) : [];
      const pendingList = det ? (det.pending_sites || []) : [];
      const counts = {
        pending: det ? pendingList.length : Math.max(0, sel.total - sel.completed),
        completed: det ? completedOk.length : sel.completed - sel.failed,
        failed: sel.failed
      };
      const tabs = ['pending', 'completed'].concat(sel.failed > 0 ? ['failed'] : []);
      const tab = tabs.includes(s.bpTab) ? s.bpTab : 'pending';
      const CHIP_CAP = 400;
      const chipSrc = tab === 'pending' ? pendingList.map(site => ({ site }))
        : tab === 'completed' ? completedOk : failedEntries;
      const chipStyle = tab === 'completed' ? ['var(--ok-soft)', 'var(--ok)']
        : tab === 'failed' ? ['var(--bad-soft)', 'var(--bad)'] : ['var(--panel-2)', 'var(--ink)'];
      bpVals = {
        bpOpen: true,
        bpTitle: sel.command,
        bpStaleShow: !sel.running,
        bpCount: sel.completed + ' / ' + sel.total,
        bpPct: sel.percent + '%',
        bpBarW: Math.min(100, sel.percent || 0) + '%',
        bpBarColor: sel.failed > 0 ? 'var(--warn)' : 'var(--brand)',
        bpStats: [
          { k: 'Elapsed', v: sel.elapsed || '—', fg: 'var(--ink)' },
          { k: 'ETA', v: sel.running && sel.eta ? sel.eta : '—', fg: 'var(--ink)' },
          { k: 'Parallel', v: String(sel.parallel), fg: 'var(--ink)' },
          { k: 'Failed', v: String(sel.failed), fg: sel.failed > 0 ? 'var(--warn)' : 'var(--ink)' },
          { k: 'PID', v: String(sel.pid), fg: 'var(--ink)' },
          { k: 'Target', v: targetsOf(sel) + ' sites', fg: 'var(--ink)' }
        ],
        bpArgs: sel.args || '',
        bpArgsShow: !!sel.args,
        bpTabs: tabs.map(t => ({
          label: t.charAt(0).toUpperCase() + t.slice(1) + ' (' + counts[t] + ')',
          bg: tab === t ? 'var(--panel-2)' : 'transparent',
          fg: tab === t ? 'var(--ink)' : 'var(--ink-dim)',
          pick: () => this.setState({ bpTab: t })
        })),
        bpLoadingDetail: s.bpLoading && !det,
        bpChips: chipSrc.slice(0, CHIP_CAP).map(e => ({ label: e.site, bg: chipStyle[0], fg: chipStyle[1] })),
        bpChipsEmpty: !s.bpLoading && chipSrc.length === 0,
        bpChipsEmptyText: tab === 'pending' ? 'All sites completed.' : 'No sites here yet.',
        bpMoreShow: chipSrc.length > CHIP_CAP,
        bpMoreLabel: '+ ' + (chipSrc.length - CHIP_CAP) + ' more',
        bpKillShow: !!sel.running,
        bpKilling: s.bpKilling,
        bpKillLabel: s.bpKilling ? 'Killing…' : 'Kill process',
        bpKill: () => this.killBulkOp(sel),
        bpDismissShow: !sel.running,
        bpDismiss: () => this.dismissBulkOp(sel.pid),
        bpClose: () => this.setState({ bpPid: 0, bpDetail: null })
      };
    }

    return { bulkOps: bulkRows, bulkShow: bulkRows.length > 0, ...bpVals };
  }

});
