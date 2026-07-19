// CaptainCore v3 — home-screen truth (mixin).
// Hydrates the Needs-attention feed and Recent-activity feed from real
// signals. Called from componentDidMount alongside hydrate(); fetches run in
// parallel and each arrival re-renders. Admin-gated endpoints are only
// requested for operators (a 403 in api() would bounce to the login page).
//
// Sources:
//   /activity-logs?per_page=20  — any logged-in user, self-scoped (activity feed)
//   /security-threats           — admin only: { total_threats, severity_summary, threats[] }
//   /update-queue               — admin only: { needs_update, generated_at, not_built }
//   unassigned sites            — no endpoint; derived from FLEET[].unassigned (account_id empty)
// NOT wired (v1 contract gaps, see STATUS.md): domain expirations (/domains/
// carries no expiry field) and a home jobs backfill (/process-logs is
// unpaginated and returns the entire table — 12+ MB).

Object.assign(Component.prototype, {

  hydrateHome() {
    const boot = window.CC_BOOT || {};
    if (!boot.nonce) return;
    const role = boot.dcRole || this.props.role || 'operator';
    const swallow = err => { console.warn('CaptainCore v3 home signal failed.', err); return null; };

    this.api('/activity-logs?per_page=20').then(res => {
      const items = (res && Array.isArray(res.items)) ? res.items : [];
      this._activity = items.map(x => ({
        t: this.relTime(x.created_at),
        text: x.description || [x.action, x.entity_type, x.entity_name].filter(Boolean).join(' ')
      }));
      this.setState({});
    }).catch(swallow);

    if (role !== 'operator') return;

    this.api('/security-threats').then(res => {
      if (res && Array.isArray(res.threats)) { this._homeThreats = res; this.setState({}); }
    }).catch(swallow);

    this.api('/update-queue').then(res => {
      if (res && !res.not_built) { this._homeQueue = res; this.setState({}); }
    }).catch(swallow);
  },

  relTime(ts) {
    const t = typeof ts === 'string' && !/^\d+$/.test(ts) ? Math.floor(Date.parse(ts) / 1000) : parseInt(ts, 10);
    if (!t) return '';
    const d = Math.max(0, Math.floor(Date.now() / 1000) - t);
    if (d < 60) return 'now';
    if (d < 3600) return Math.floor(d / 60) + 'm';
    if (d < 86400) return Math.floor(d / 3600) + 'h';
    return Math.floor(d / 86400) + 'd';
  },

  realAttention(isOp) {
    const rows = [];
    if (isOp) {
      const t = this._homeThreats;
      if (t && t.total_threats > 0) {
        const sev = Object.entries(t.severity_summary || {}).filter(([, n]) => n > 0)
          .map(([k, n]) => n + ' ' + k).join(' · ');
        const sites = t.threats.reduce((n, x) => n + (parseInt(x.affected_count, 10) || 0), 0);
        rows.push({ dot: 'var(--bad)',
          title: t.total_threats + ' security threat' + (t.total_threats === 1 ? '' : 's') + ' across ' + sites + ' site' + (sites === 1 ? '' : 's'),
          sub: sev || 'Open the security screen for details', action: 'Review', act: 'security' });
      }
      const q = this._homeQueue;
      if (q && q.needs_update > 0) {
        rows.push({ dot: 'var(--warn)',
          title: q.needs_update + ' component' + (q.needs_update === 1 ? '' : 's') + ' have updates pending',
          sub: 'Update queue' + (q.generated_at ? ' · built ' + this.relTime(q.generated_at) + ' ago' : ''),
          action: 'Update', act: 'sites' });
      }
      const unassigned = this.FLEET.filter(x => x.unassigned).length;
      if (unassigned > 0) {
        rows.push({ dot: 'var(--ink-dim)',
          title: unassigned + ' site' + (unassigned === 1 ? ' is' : 's are') + ' unassigned to an account',
          sub: 'Assign owners so access and billing stay accurate', action: 'Assign', act: 'accounts' });
      }
    }
    if (!rows.length) {
      rows.push({ clear: true, dot: 'var(--ok)', title: 'All clear, nothing needs attention',
        sub: this.FLEET.length + ' site' + (this.FLEET.length === 1 ? '' : 's') + ' under management',
        action: 'View sites', act: 'sites' });
    }
    return rows;
  }

});
