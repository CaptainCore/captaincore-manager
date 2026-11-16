// CaptainCore v3 — APM tab (mixin). Operators, sites on our own servers
// (shown next to Hosting). Two layers, both from the node's hourly files:
//   requests: every request the site's web server answered (access log),
//     always collected: what answered it (PHP, a saved page, a file) and how
//     long PHP took, by page.
//   WordPress: only while the recorder is on (a switch with an end time):
//     transactions in WordPress's terms, time per plugin and theme, the
//     slowest queries and outside calls, and sampled slow requests with the
//     phases of their page load.
//   GET  /sites/{id}/hosting/apm?environment=&range=1h|24h|7d
//   POST /sites/{id}/hosting/apm { environment, hours }   (0 = off; a hosting revision, applied)

const APM_PHASES = {
  'mu-plugins': 'Start-up and must-use plugins', plugins: 'Plugins loading', theme: 'Theme setup',
  'init start': 'Until init', init: 'init', query: 'Main query', 'redirects start': 'After the query',
  redirects: 'template_redirect', render: 'Template and output'
};

Object.assign(Component.prototype, {

  apmKey() { return this.hostingKey() + '|' + (this.state.apmRange || '24h'); },

  loadApm(force) {
    const key = this.apmKey();
    if (!this._detail) return;
    if (!force && this._apm && this._apm.key === key && (this._apm.data || this._apm.loading || this._apm.error)) return;
    const [siteId, env, range] = key.split('|');
    this._apm = { key, loading: true, data: this._apm && this._apm.key.split('|').slice(0, 2).join('|') === key.split('|').slice(0, 2).join('|') ? this._apm.data : null };
    this.setState({});
    this.api('/sites/' + siteId + '/hosting/apm?environment=' + encodeURIComponent(env) + '&range=' + range).then(res => {
      if (!this._apm || this._apm.key !== key) return;
      this._apm = (!res || res.code) ? { key, error: (res && res.message) || 'Could not read APM' } : { key, data: res, at: Date.now() };
      this.setState({});
    }).catch(() => { if (this._apm && this._apm.key === key) { this._apm = { key, error: 'Could not read APM' }; this.setState({}); } });
  },

  apmSwitch(hours) {
    const [siteId, env] = this.hostingKey().split('|');
    const tid = this.toast(hours ? 'Turning on the WordPress recorder…' : 'Turning off the WordPress recorder…', { kind: 'loading' });
    this.api('/sites/' + siteId + '/hosting/apm', { method: 'POST', body: { environment: env, hours } }).then(res => {
      if (!res || res.code) { this.updateToast(tid, (res && res.message) || 'Could not change APM', { kind: 'error', timeout: 8000 }); return; }
      this.updateToast(tid, !res.token ? 'Saved, but the CLI server did not answer. Use Apply again on the Hosting tab.'
        : hours ? 'Recording for ' + (hours === 1 ? '1 hour' : hours + ' hours') + '. Pages show up here within a couple of minutes.'
        : 'Recorder off. What it recorded stays here for 14 days.', { kind: res.token ? 'success' : 'info', timeout: 7000 });
      if (this._apm && this._apm.data) this._apm.data.apm = res.apm;
      this._host = null;
      this.setState({});
      setTimeout(() => this.loadApm(true), 4000);
    }).catch(e => this.updateToast(tid, (e && e.message) || 'Could not change APM', { kind: 'error' }));
  },

  apmMs(v) {
    if (v === null || v === undefined || v === '') return '–';
    const n = Number(v);
    return n >= 10000 ? (n / 1000).toFixed(1) + ' s' : n >= 1000 ? (n / 1000).toFixed(2) + ' s' : (n < 10 && n % 1 ? n.toFixed(1) : Math.round(n)) + ' ms';
  },

  apmComp(c) {
    c = String(c || '');
    if (c === 'core') return 'WordPress core';
    if (c === 'hosting') return 'Hosting (page cache)';
    if (c.startsWith('theme:')) return c.slice(6) + ' (theme)';
    if (c.startsWith('mu:')) return c.slice(3) + ' (must-use)';
    return c;
  },

  // Bars (requests by what answered them) or lines (time) as inline SVG,
  // stretched to the card: a fixed viewBox with preserveAspectRatio none and
  // non-scaling strokes (the same trick as the performance monitor).
  apmChart(el, kind) {
    if (!el) return;
    const d = this._apm && this._apm.data;
    const pts = (d && d.series) || [];
    const W = 600, H = 120, n = Math.max(1, pts.length), bw = W / n;
    const t = ts => new Date(ts * 1000).toLocaleString([], (d && d.range) === '7d' ? { weekday: 'short', hour: 'numeric' } : { hour: 'numeric', minute: '2-digit' });
    let svg = '';
    if (kind === 'req') {
      const max = Math.max(1, ...pts.map(p => p.n));
      pts.forEach((p, i) => {
        const hc = (p.hit + p.static) / max * (H - 4), hp = p.php / max * (H - 4);
        const x = (i * bw + bw * 0.12).toFixed(1), w = Math.max(0.6, bw * 0.76).toFixed(1);
        svg += '<g><title>' + t(p.t) + ': ' + p.n.toLocaleString() + ' requests, ' + p.php.toLocaleString() + ' by PHP' + (p.e5 ? ', ' + p.e5 + ' errors' : '') + '</title>'
          + '<rect x="' + x + '" y="' + (H - hp - hc).toFixed(1) + '" width="' + w + '" height="' + hc.toFixed(1) + '" fill="color-mix(in srgb,var(--brand) 30%,transparent)"/>'
          + '<rect x="' + x + '" y="' + (H - hp).toFixed(1) + '" width="' + w + '" height="' + hp.toFixed(1) + '" fill="var(--brand)"/>'
          + (p.e5 ? '<rect x="' + x + '" y="' + (H - 3) + '" width="' + w + '" height="3" fill="var(--bad)"/>' : '') + '</g>';
      });
    } else {
      const keys = [['php_avg_ms', 'var(--brand)'], ['wp_db_ms', 'var(--warn)']];
      const max = Math.max(1, ...pts.flatMap(p => keys.map(([k]) => Number(p[k]) || 0)));
      keys.forEach(([k, col]) => {
        let path = '', pen = false;
        pts.forEach((p, i) => {
          const v = p[k];
          if (v === null || v === undefined) { pen = false; return; }
          const x = (i * bw + bw / 2).toFixed(1), y = (H - 2 - v / max * (H - 6)).toFixed(1);
          path += (pen ? 'L' : 'M') + x + ' ' + y + ' ';
          pen = true;
        });
        if (path) svg += '<path d="' + path + '" fill="none" stroke="' + col + '" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round"/>';
      });
      pts.forEach((p, i) => {
        if (p.php_avg_ms === null || p.php_avg_ms === undefined) return;
        svg += '<rect x="' + (i * bw).toFixed(1) + '" y="0" width="' + bw.toFixed(1) + '" height="' + H + '" fill="transparent"><title>' + t(p.t) + ': PHP '
          + this.apmMs(p.php_avg_ms) + ' on average' + (p.wp_db_ms !== null && p.wp_db_ms !== undefined ? ', database ' + this.apmMs(p.wp_db_ms) : '') + '</title></rect>';
      });
    }
    el.innerHTML = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" style="display:block;width:100%;height:120px">'
      + '<line x1="0" y1="' + (H - 0.5) + '" x2="' + W + '" y2="' + (H - 0.5) + '" stroke="var(--rule)" vector-effect="non-scaling-stroke"/>' + svg + '</svg>';
  },

  computeApm(real, s, hostingVals) {
    const off = { tabApm: false };
    if (!hostingVals || !hostingVals.hostShow || this.siteLeaf(s.siteTab) !== 'apm') return off;
    if (!this._apm || this._apm.key !== this.apmKey()) setTimeout(() => this.loadApm(), 0);
    const m = this._apm || {}, d = m.data;
    const range = s.apmRange || '24h';
    const base = {
      tabApm: true, apmIco: this.icon ? this.icon('pulse') : null,
      apmRanges: [['1h', '1 hour'], ['24h', '24 hours'], ['7d', '7 days']].map(([v, l]) => ({ label: l,
        fg: range === v ? 'var(--brand-ink)' : 'var(--ink-dim)', bg: range === v ? 'var(--brand-soft)' : 'transparent', bd: range === v ? 'var(--brand)' : 'var(--rule)',
        pick: () => { this.setState({ apmRange: v }); setTimeout(() => this.loadApm(), 0); } })),
      apmRefresh: () => this.loadApm(true),
      apmLoading: !!m.loading && !d, apmBusy: !!m.loading,
      apmErr: m.error || '', apmHasErr: !!m.error,
      apmReady: !!d
    };
    if (!d) return base;

    const e = d.edge || {}, w = d.wp || {}, apm = d.apm || {};
    const now = d.now || Math.floor(Date.now() / 1000);
    const span = { '1h': 3600, '24h': 86400, '7d': 604800 }[range] || 86400;
    const pct = (a, b) => b ? Math.round(a / b * 100) + '%' : '–';
    const ago = ts => { const x = Math.max(0, now - ts); return x < 90 ? 'just now' : x < 5400 ? Math.round(x / 60) + ' min ago' : x < 172800 ? Math.round(x / 3600) + ' h ago' : Math.round(x / 86400) + ' days ago'; };
    const recording = !!apm.enabled && apm.until > now;
    const left = recording ? apm.until - now : 0;
    const until = recording ? new Date(apm.until * 1000).toLocaleString([], { weekday: left > 86400 ? 'short' : undefined, hour: 'numeric', minute: '2-digit' }) : '';
    const leftLabel = left >= 7200 ? Math.round(left / 3600) + ' hours' : left >= 3600 ? '1 hour' : Math.max(1, Math.round(left / 60)) + ' minutes';
    const cached = (e.hit || 0) + (e.static || 0);
    const refKey = m.key + '|' + (m.at || 0);
    this._apmRefs = this._apmRefs && this._apmRefs.key === refKey ? this._apmRefs
      : { key: refKey, req: el => this.apmChart(el, 'req'), time: el => this.apmChart(el, 'time') };

    const share = (v, tot) => Math.max(2, Math.round(v / Math.max(1, tot) * 100)) + '%';
    const routeTotal = (e.routes || []).reduce((a, r) => a + r.total_ms, 0);
    const txTotal = (w.transactions || []).reduce((a, r) => a + r.total_ms, 0);
    const plTotal = (w.plugins || []).filter(p => p.component !== 'core').reduce((a, r) => a + r.total_ms, 0);
    const openId = s.apmSample || '';
    const sampleRows = (w.samples || []).map((x, i) => {
      const id = String(x.t) + '-' + i;
      const open = openId === id;
      const marks = (x.d && x.d.ph) || [];
      let prev = 0;
      const phases = marks.map(([name, at]) => { const row = { label: APM_PHASES[name] || name, ms: Math.max(0, at - prev), start: prev }; prev = at; return row; })
        .filter(p => p.ms >= 0.5);
      const tot = Math.max(1, x.ms);
      const loads = ((x.d && x.d.ld) || []).filter(l => l[1] !== null).sort((a, b) => b[1] - a[1]).slice(0, 8);
      return {
        tx: x.tx, url: (x.m && x.m !== 'GET' ? x.m + ' ' : '') + (x.u || ''), ms: this.apmMs(x.ms), when: ago(x.t),
        code: String(x.st || ''), codeFg: x.st >= 500 ? 'var(--bad)' : x.st >= 400 ? 'var(--warn)' : 'var(--ink-dim)',
        meta: 'Database ' + this.apmMs(x.db) + ' in ' + (x.q || 0) + ' queries · outside calls ' + this.apmMs(x.http) + (x.hn ? ' (' + x.hn + ')' : '') + ' · ' + (x.mem || 0) + ' MB',
        open, chev: open ? '▾' : '▸',
        toggle: () => this.setState({ apmSample: open ? '' : id }),
        phases: phases.map(p => ({ label: p.label, ms: this.apmMs(p.ms), left: (p.start / tot * 100).toFixed(2) + '%', width: Math.max(0.4, p.ms / tot * 100).toFixed(2) + '%' })),
        hasPhases: phases.length > 0,
        loads: loads.map(l => ({ name: this.apmComp(l[0]), ms: this.apmMs(l[1]) })), hasLoads: loads.length > 0,
        queries: (x.qs || []).slice(0, 8).map(q => ({ sql: q[0], who: this.apmComp(q[1]), ms: this.apmMs(q[2]) })), hasQueries: (x.qs || []).length > 0,
        calls: (x.hc || []).map(c => ({ call: c[0] + ' ' + c[1] + c[2], who: this.apmComp(c[3]), ms: this.apmMs(c[4]), code: String(c[5]),
          codeFg: c[5] === 'error' || c[5] >= 400 ? 'var(--bad)' : 'var(--ink-dim)' })), hasCalls: (x.hc || []).length > 0
      };
    });

    return {
      ...base,
      apmRecording: recording, apmNotRecording: !recording,
      apmRecLine: recording ? 'Recording WordPress detail until ' + until + ' (' + leftLabel + ' left).' : (w.last ? 'Off. Last recorded ' + ago(w.last) + '.' : 'Off.'),
      apmRecHint: 'Times each page PHP builds: plugins, theme, database queries and outside calls. It adds a few milliseconds to each page and stops by itself. Turning it on or off restarts PHP for a moment.',
      apmRecOpts: [[1, '1 hour'], [4, '4 hours'], [24, '24 hours'], [72, '3 days']].map(([h, l]) => ({ label: l, go: () => this.apmSwitch(h) })),
      apmRecOff: () => this.apmSwitch(0),
      apmTiles: [
        { k: 'Requests', v: (e.requests || 0).toLocaleString(), sub: e.requests ? Math.round(e.requests / (span / 60) * 10) / 10 + ' a minute' : 'none yet' },
        { k: 'Answered without PHP', v: pct(cached, e.requests), sub: (e.hit || 0).toLocaleString() + (e.hit === 1 ? ' saved page, ' : ' saved pages, ') + (e.static || 0).toLocaleString() + (e.static === 1 ? ' file' : ' files') },
        { k: 'PHP median', v: this.apmMs(e.php_p50_ms), sub: (e.php || 0).toLocaleString() + ' PHP requests' },
        { k: 'PHP 95th percentile', v: this.apmMs(e.php_p95_ms), sub: 'slowest ' + this.apmMs(e.php_max_ms) },
        { k: 'Errors', v: (e.e5 || 0).toLocaleString(), sub: (e.e4 || 0).toLocaleString() + ' not found or refused (4xx)', c: e.e5 ? 'var(--bad)' : 'var(--ink)' }
      ].map(t => ({ ...t, c: t.c || 'var(--ink)' })),
      apmReqRef: this._apmRefs.req, apmTimeRef: this._apmRefs.time,
      apmSpanFrom: new Date((now - span) * 1000).toLocaleString([], range === '7d' ? { weekday: 'short', month: 'short', day: 'numeric' } : { hour: 'numeric', minute: '2-digit' }),
      apmSpanTo: 'now',
      apmHasDb: (d.series || []).some(p => p.wp_db_ms !== null && p.wp_db_ms !== undefined),
      apmRoutes: (e.routes || []).slice(0, 25).map(r => ({ name: r.route, n: r.n.toLocaleString(), avg: this.apmMs(r.avg_ms), p95: this.apmMs(r.p95_ms), max: this.apmMs(r.max_ms),
        share: share(r.total_ms, routeTotal), errs: r.e5 ? r.e5 + ' errors' : '', hasErrs: !!r.e5 })),
      apmHasRoutes: (e.routes || []).length > 0, apmNoRoutes: !(e.routes || []).length,
      apmWpCard: d.stack !== 'plain',
      apmHasWp: (w.requests || 0) > 0, apmNoWp: !(w.requests || 0),
      apmNoWpLine: recording ? 'Recording. Pages show up here within a couple of minutes of being visited.' : 'Nothing recorded in this range. Turn on the WordPress recorder above to see what each page spends its time on.',
      apmWpTiles: [
        { k: 'Pages recorded', v: (w.requests || 0).toLocaleString() },
        { k: 'Average page', v: this.apmMs(w.avg_ms) },
        { k: 'Database, per page', v: this.apmMs(w.db_ms) },
        { k: 'Outside calls, per page', v: this.apmMs(w.http_ms) }
      ],
      apmTx: (w.transactions || []).slice(0, 25).map(r => ({ name: r.tx, n: r.n.toLocaleString(), avg: this.apmMs(r.avg_ms), p95: this.apmMs(r.p95_ms),
        db: this.apmMs(r.db_ms), q: String(r.queries), http: r.http_calls ? this.apmMs(r.http_ms) : '–', share: share(r.total_ms, txTotal),
        errs: r.e5 ? r.e5 + ' errors' : '', hasErrs: !!r.e5 })),
      apmPlugins: (w.plugins || []).slice(0, 25).map(p => ({ name: this.apmComp(p.component), avg: this.apmMs(p.avg_ms), load: this.apmMs(p.load_ms),
        db: this.apmMs(p.db_ms), q: String(p.queries), http: p.http_calls ? this.apmMs(p.http_ms) : '–',
        share: p.component === 'core' ? '0%' : share(p.total_ms, plTotal), core: p.component === 'core' })),
      apmQueries: (w.queries || []).slice(0, 20).map(q => ({ sql: q.sql, who: this.apmComp(q.component), n: q.n.toLocaleString(), avg: this.apmMs(q.avg_ms),
        max: this.apmMs(q.max_ms), total: this.apmMs(q.total_ms) })),
      apmHasQueries: (w.queries || []).length > 0,
      apmCalls: (w.calls || []).slice(0, 20).map(c => ({ call: c.call, who: this.apmComp(c.component), n: c.n.toLocaleString(), avg: this.apmMs(c.avg_ms),
        max: this.apmMs(c.max_ms), errs: c.errors ? c.errors + ' failed' : '', hasErrs: !!c.errors })),
      apmHasCalls: (w.calls || []).length > 0, apmNoCalls: !(w.calls || []).length,
      apmSamples: sampleRows, apmHasSamples: sampleRows.length > 0
    };
  }
});
