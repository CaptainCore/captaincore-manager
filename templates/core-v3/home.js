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
      this._activity = items.map(x => this.activityRow(x));
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

  // entity_type → a short human "type" label for the activity chip. Falls
  // back to a title-cased version of the raw type for anything unmapped.
  ACT_TYPE: { dns_record: 'DNS', site: 'Site', domain: 'Domain', account: 'Account',
    environment: 'Deploy', email_forward: 'Email', file: 'File', session: 'Security' },
  activityType(t) {
    if (!t) return '';
    return this.ACT_TYPE[t] || t.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  },

  // Shared shape for both the home feed and the full Activity page. Adds the
  // actor (name + gravatar or initials) and a type chip alongside the text.
  activityRow(x) {
    const name = x.user_name || 'System';
    const isSystem = !x.avatar_url && (name === 'System' || !x.user_id);
    return {
      t: this.relTime(x.created_at),
      text: x.description || [x.action, x.entity_type, x.entity_name].filter(Boolean).join(' '),
      user: name,
      type: this.activityType(x.entity_type),
      avatar: x.avatar_url || '',
      // Avatar precedence: gravatar → system gear (automated rows) → initials.
      hasAvatar: !!x.avatar_url,
      isSystem,
      showInitials: !x.avatar_url && !isSystem,
      initials: name.replace(/[^a-zA-Z0-9]/g, ' ').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase() || '·'
    };
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

// ── Activity page ── full fleet event log (same endpoint as the home feed,
// larger page; self-scoped for customers by the API itself).
Object.assign(Component.prototype, {

  ACT_PER_PAGE: 100,

  loadActivityPage(page) {
    page = page || 1;
    // A new page swaps the visible rows for a skeleton; guard re-entrancy per
    // in-flight page so a double-click doesn't stack fetches.
    if (this._actFullLoading === page) return;
    this._actFullLoading = page;
    this._actFull = null; // show the loading skeleton while the page swaps
    this.setState({ actPage: page });
    this.api('/activity-logs?per_page=' + this.ACT_PER_PAGE + '&page=' + page).then(res => {
      this._actFullLoading = false;
      const items = (res && Array.isArray(res.items)) ? res.items : [];
      this._actFull = items.map(x => this.activityRow(x));
      this._actMeta = { page: (res && res.page) || page, pages: (res && res.pages) || 1, total: (res && res.total) || items.length };
      this.setState({});
    }).catch(err => { console.warn('CaptainCore v3 activity page failed.', err); this._actFullLoading = false; this._actFull = []; this._actMeta = null; this.setState({}); });
  },

  computeActivityPage(s) {
    const active = s.route === 'activity';
    if (active && window.CC_BOOT && !this._actFull && !this._actFullLoading) setTimeout(() => this.loadActivityPage(s.actPage || 1), 0);
    const rows = this._actFull || (window.CC_BOOT ? [] : (this._sampleActivity || []));
    const meta = this._actMeta;
    const page = meta ? meta.page : (s.actPage || 1);
    const pages = meta ? meta.pages : 1;
    const total = meta ? meta.total : rows.length;
    // "1–100 of 4,213" — the slice the current page represents.
    const from = total ? (page - 1) * this.ACT_PER_PAGE + 1 : 0;
    const to = Math.min(total, page * this.ACT_PER_PAGE);
    const busy = !!this._actFullLoading;
    return {
      showActivity: active,
      actRows: rows,
      actCount: total ? total.toLocaleString() + ' events' : '',
      ...(active && total ? { screenSub: total.toLocaleString() + ' events', screenSubDisplay: 'inline-block' } : {}),
      actLoading: active && !!window.CC_BOOT && !this._actFull,
      actEmpty: active && !!this._actFull && rows.length === 0,
      actPagerShow: active && pages > 1,
      actRangeText: total ? from.toLocaleString() + '–' + to.toLocaleString() + ' of ' + total.toLocaleString() : '',
      actPrevFg: page > 1 && !busy ? 'var(--brand-ink)' : 'var(--ink-dim)',
      actNextFg: page < pages && !busy ? 'var(--brand-ink)' : 'var(--ink-dim)',
      actPrevCursor: page > 1 && !busy ? 'pointer' : 'default',
      actNextCursor: page < pages && !busy ? 'pointer' : 'default',
      actPrev: () => { if (page > 1 && !this._actFullLoading) this.loadActivityPage(page - 1); },
      actNext: () => { if (page < pages && !this._actFullLoading) this.loadActivityPage(page + 1); }
    };
  }

});
