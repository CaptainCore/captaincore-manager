// CaptainCore v3 — site Stats tab (Fathom analytics) real-data layer (mixin).
// loadStats() fetches GET /sites/{id}/stats (live Fathom aggregations; params
// from_at/grouping/environment/fathom_id) plus the top-pages/top-referrers
// routes. The tracker code comes from the current environment's
// fathom_analytics[0].code; no tracker = a friendly empty state. Grouping maps
// Daily/Monthly/Yearly → day/month/year (Fathom has no week grouping).
// Sharing: POST /sites/{id}/stats/share { fathom_id, sharing, share_password };
// the private-link password auto-saves debounced while Private is selected.
// Results cache on this._stats keyed by site|env|grouping|range.

Object.assign(Component.prototype, {

  STAT_GROUPINGS: { 'Daily': 'day', 'Monthly': 'month', 'Yearly': 'year' },

  statRangeFrom(label) {
    const d = new Date();
    if (label === 'Last 7 days') d.setDate(d.getDate() - 7);
    else if (label === 'Last 28 days') d.setDate(d.getDate() - 28);
    else if (label === 'Last 90 days') d.setDate(d.getDate() - 90);
    else d.setMonth(0, 1); // This year
    return d.toISOString().slice(0, 10);
  },

  statTracker(real, s) {
    const e = this.currentEnv(real, s);
    const list = (e && e.fathom_analytics) || [];
    return (list.length && list[0] && list[0].code) ? list[0] : null;
  },

  loadStats(force) {
    const real = this._detail;
    if (!this._hydrated || !real) return;
    const s = this.state;
    const e = this.currentEnv(real, s);
    if (!e) return;
    const st = this._stats = this._stats || {};
    const key = [real.siteId, e.environment, s.statG, s.statR].join('|');
    const tracker = this.statTracker(real, s);
    if (!tracker) { st.current = { key, empty: true }; st.pages = st.refs = null; this.setState({}); return; }
    if (!force && st.current && st.current.key === key && !st.current.error) return;
    st.current = { key, loading: true };
    st.pages = st.refs = null;
    this.setState({});
    const range = '&from_at=' + this.statRangeFrom(s.statR) + '&environment=' + encodeURIComponent(e.environment);
    this.api('/sites/' + real.siteId + '/stats?grouping=' + this.STAT_GROUPINGS[s.statG] + range
      + '&fathom_id=' + encodeURIComponent(tracker.code)).then(res => {
      if (st.current.key !== key) return;
      if (res && res.summary) {
        st.current = { key, data: res };
        const share = (res.site && res.site.sharing) || 'none';
        this.setState({ statShare: share === 'private' ? 'Private' : share === 'public' ? 'Public' : 'Off',
          statPw: (res.site && res.site.share_password) || '' });
      } else {
        st.current = { key, error: (res && (res.Error || res.message)) || 'Stats unavailable.' };
        this.setState({});
      }
    }).catch(() => {
      if (st.current.key === key) { st.current = { key, error: 'Stats unavailable.' }; this.setState({}); }
    });
    this.api('/sites/' + real.siteId + '/stats/top-pages?limit=5' + range).then(r => {
      if (st.current.key === key) { st.pages = Array.isArray(r) ? r : []; this.setState({}); }
    }).catch(() => {});
    this.api('/sites/' + real.siteId + '/stats/top-referrers?limit=5' + range).then(r => {
      if (st.current.key === key) { st.refs = Array.isArray(r) ? r : []; this.setState({}); }
    }).catch(() => {});
  },

  shareStats(label) {
    const real = this._detail;
    const cur = this._stats && this._stats.current;
    if (!real || !cur || !cur.data) return;
    this.setState({ statShare: label });
    const sharing = label === 'Private' ? 'private' : label === 'Public' ? 'public' : 'none';
    const body = { fathom_id: cur.data.fathom_id, sharing };
    if (sharing === 'private') body.share_password = this.state.statPw;
    this.api('/sites/' + real.siteId + '/stats/share', { method: 'POST', body }).then(() => {
      if (cur.data.site) cur.data.site.sharing = sharing;
    }).catch(() => {});
  },

  saveStatPw(v) {
    this.setState({ statPw: v });
    clearTimeout(this._statPwT);
    this._statPwT = setTimeout(() => {
      if (this.state.statShare === 'Private') this.shareStats('Private');
    }, 900);
  },

  fmtDuration(sec) {
    sec = Math.round(parseFloat(sec) || 0);
    return sec >= 60 ? Math.floor(sec / 60) + 'm ' + (sec % 60) + 's' : sec + 's';
  },

  // Fathom only returns buckets that saw traffic; zero-fill the series so the
  // chart has one slot per period. Labels must byte-match the server's PHP
  // date() formats — day 'M d Y' ("Jul 05 2026"), month 'M Y', year 'Y'.
  statBuckets(s) {
    const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const from = new Date(this.statRangeFrom(s.statR) + 'T00:00:00');
    const now = new Date();
    const out = [];
    if (s.statG === 'Daily') {
      for (const d = new Date(from); d <= now && out.length < 400; d.setDate(d.getDate() + 1))
        out.push(MON[d.getMonth()] + ' ' + String(d.getDate()).padStart(2, '0') + ' ' + d.getFullYear());
    } else if (s.statG === 'Monthly') {
      for (const d = new Date(from.getFullYear(), from.getMonth(), 1); d <= now && out.length < 400; d.setMonth(d.getMonth() + 1))
        out.push(MON[d.getMonth()] + ' ' + d.getFullYear());
    } else {
      for (let y = from.getFullYear(); y <= now.getFullYear(); y++) out.push(String(y));
    }
    return out;
  },

  // Placeholder while the site detail hydrates — avoids flashing the design's
  // mock stats (9,120 visitors…) before the real Fathom data loads.
  emptyStatVals() {
    return {
      statTilesBig: ['Visitors', 'Pageviews', 'Avg time on site', 'Bounce rate'].map(k => ({ k, v: '—', delta: '', deltaFg: 'var(--ink-dim)' })),
      statBars: [], topPages: [], topRefs: [],
      statsShowPerf: false, chartMove: () => {}, chartLeave: () => {}, chartTipShow: false, chartTipLeft: 0, chartTipTop: 0, chartTipDate: '', chartTipViews: '', chartTipVisits: '',
      statsNotice: true, statsNoticeText: 'Loading analytics…'
    };
  },

  // Binding overrides computeDetail() spreads in when this._detail is live.
  realStatVals(s, site) {
    const st = this._stats || {};
    const cur = st.current || {};
    const d = cur.data;
    const sum = d && d.summary;
    const fmtN = n => Math.round(parseFloat(n) || 0).toLocaleString();
    const items = (d && d.items) || [];
    const max = items.reduce((m, i) => Math.max(m, parseFloat(i.pageviews) || 0), 0);
    const note = cur.empty ? 'Fathom analytics is not configured for this environment.'
      : cur.error ? cur.error
      : cur.loading ? 'Loading analytics…' : '';
    const statBars = (() => {
      if (!items.length) return [];
      const byDate = {}; items.forEach(i => { byDate[i.date] = i; });
      let buckets = this.statBuckets(s);
      if (!buckets.some(label => byDate[label])) buckets = items.map(i => i.date);
      return buckets.map((label, idx) => {
        const i = byDate[label];
        const views = i ? (parseFloat(i.pageviews) || 0) : 0;
        return {
          h: views && max ? Math.max(4, Math.round(92 * views / max)) : 4,
          date: label, views: fmtN(views), visits: i ? fmtN(i.visits) : '0',
          tip: label + ' · ' + fmtN(views) + ' views' + (i ? ' · ' + fmtN(i.visits) + ' visits' : ''),
          bg: !views ? 'var(--panel-2)'
            : idx === buckets.length - 1 ? 'var(--brand)' : 'color-mix(in srgb, var(--brand) 38%, transparent)',
          enter: () => this.setState({ chartHoverIdx: idx })
        };
      });
    })();
    const hi = s.chartHoverIdx;
    const hovered = (hi != null && hi >= 0 && hi < statBars.length) ? statBars[hi] : null;
    return {
      statTilesBig: [
        { k: 'Visitors', v: sum ? fmtN(sum.visits) : '—', delta: '', deltaFg: 'var(--ink-dim)' },
        { k: 'Pageviews', v: sum ? fmtN(sum.pageviews) : '—', delta: '', deltaFg: 'var(--ink-dim)' },
        { k: 'Avg time on site', v: sum ? this.fmtDuration(sum.avg_duration) : '—', delta: '', deltaFg: 'var(--ink-dim)' },
        { k: 'Bounce rate', v: sum ? Math.round(parseFloat(sum.bounce_rate) || 0) + '%' : '—', delta: '', deltaFg: 'var(--ink-dim)' }
      ],
      statBars, statsShowPerf: false,
      chartMove: e => { const r = e.currentTarget.getBoundingClientRect(); this.setState({ chartHoverX: Math.round(e.clientX - r.left), chartHoverY: Math.round(e.clientY - r.top) }); },
      chartLeave: () => this.setState({ chartHoverIdx: -1 }),
      chartTipShow: !!hovered,
      chartTipLeft: hovered ? (s.chartHoverX || 0) : 0,
      chartTipTop: hovered ? ((s.chartHoverY || 0) - 14) : 0,
      chartTipDate: hovered ? hovered.date : '',
      chartTipViews: hovered ? hovered.views : '',
      chartTipVisits: hovered ? hovered.visits : '',
      statsNotice: !!note, statsNoticeText: note,
      topPages: (st.pages || []).map(p => ({ k: p.pathname || '/', v: fmtN(p.pageviews) })),
      topRefs: (st.refs || []).map(r => ({ k: r.referrer_hostname || 'direct', v: fmtN(r.visits) })),
      shareChips: ['Off', 'Private', 'Public'].map(label => ({ label,
        bg: s.statShare === label ? 'var(--brand-soft)' : 'var(--paper)',
        fg: s.statShare === label ? 'var(--brand-ink)' : 'var(--ink-dim)',
        bd: s.statShare === label ? 'var(--brand)' : 'var(--rule)',
        go: () => this.shareStats(label) })),
      onStatPw: e => this.saveStatPw(e.target.value),
      copyStatLink: () => {
        const url = d ? 'https://app.usefathom.com/share/' + d.fathom_id + '/' + ((d.site && d.site.name) || site.name) : '';
        try { navigator.clipboard.writeText(url); } catch (e) {}
        this.setState({ copied: 'statlink' }); clearTimeout(this._ct);
        this._ct = setTimeout(() => this.setState({ copied: '' }), 1400);
      }
    };
  }

});
