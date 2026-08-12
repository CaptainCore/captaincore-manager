// CaptainCore v3 — Performance Monitor (mixin). v1 parity for the Stats-tab
// toggle + the fullscreen monitor dashboard, on the two EXISTING site-scoped
// routes (no backend changes):
//
//   POST /sites/{id}/{env}/performance-monitor { enabled }  → CLI activate/deactivate
//   GET  /sites/{id}/{env}/performance-monitor?format=raw[&hours=N]
//        → { samples: [{ time, db, load, code, resp, workers, max_workers }],
//            max_workers }
//
// v1 draws this with Chart.js + the zoom plugin; v3 vendors NO chart library,
// so the four series render as inline SVG polylines. The trick that makes an
// SVG chart fill a fluid-width card without distorting: a fixed viewBox with
// preserveAspectRatio="none" (so the plot stretches to the card) plus
// vector-effect="non-scaling-stroke" on every stroked element (so lines stay
// 1-2px however far it stretches). Text can't live inside such an SVG — it
// would stretch too — so axis labels are HTML positioned around the plot.
//
// Downsampling keeps the MAX per bucket, not the average: this dashboard
// exists to show spikes (a 120/120 worker pin, a 10s response), and averaging
// is exactly what hides them. The KPI tiles are computed from the FULL sample
// set, so Avg/Peak stay exact no matter how much the line was reduced.

Object.assign(Component.prototype, {

  PM_RANGES: [['1H', 1], ['24H', 24], ['3D', 72], ['7D', 168], ['14D', 336], ['ALL', 0]],
  PM_MAX_POINTS: 900,

  // Environment details carry the flag (object from the REST layer, but older
  // rows can still be a JSON string — v1 parses defensively, so do the same).
  perfEnabledFor(env) {
    if (!env) return false;
    let d = env.details;
    if (typeof d === 'string') { try { d = JSON.parse(d); } catch (e) { return false; } }
    return !!(d && d.performance_monitor_enabled);
  },

  togglePerfMonitor() {
    const real = this._detail;
    const s = this.state;
    const env = this.currentEnv(real, s);
    if (!real || !env) return;
    const next = !this.perfEnabledFor(env);
    // Optimistic flip so the switch doesn't lag the CLI round-trip; the flag
    // lives inside details, which is what perfEnabledFor reads back.
    let d = env.details;
    if (typeof d === 'string') { try { d = JSON.parse(d); } catch (e) { d = {}; } }
    env.details = Object.assign({}, d || {}, { performance_monitor_enabled: next });
    this.setState({});
    const tid = this.toast((next ? 'Enabling' : 'Disabling') + ' performance monitor…', { kind: 'loading' });
    this.api('/sites/' + real.siteId + '/' + env.environment.toLowerCase() + '/performance-monitor',
      { method: 'POST', body: { enabled: next } })
      .then(() => this.updateToast(tid, 'Performance monitor ' + (next ? 'ON' : 'OFF') + ' for ' + (env.home_url || 'this environment'), { kind: 'success' }))
      .catch(() => {
        env.details = Object.assign({}, d || {}, { performance_monitor_enabled: !next });
        this.updateToast(tid, 'Could not change the performance monitor', { kind: 'error' });
        this.setState({});
      });
  },

  openPerfMonitor() {
    this.setState({ pmOpen: true, pmHover: -1 });
    this.loadPerfData(this.state.pmHours, true);
  },

  closePerfMonitor() { this.setState({ pmOpen: false, pmHover: -1 }); },

  setPerfHours(hours) {
    this.setState({ pmHours: hours, pmHover: -1 });
    this.loadPerfData(hours, true);
  },

  loadPerfData(hours, force) {
    const real = this._detail;
    const s = this.state;
    const env = this.currentEnv(real, s);
    if (!real || !env) return;
    const key = [real.siteId, env.environment, hours].join('|');
    const cache = this._perf = this._perf || {};
    if (!force && cache.key === key && cache.data) return;
    cache.key = key;
    this.setState({ pmLoading: true, pmError: '' });
    let path = '/sites/' + real.siteId + '/' + env.environment.toLowerCase() + '/performance-monitor?format=raw';
    if (hours > 0) path += '&hours=' + hours;
    this.api(path).then(res => {
      if (this._perf.key !== key) return; // a newer range won the race
      if (!res || !Array.isArray(res.samples) || !res.samples.length) {
        this._perf.data = null;
        this.setState({ pmLoading: false, pmError: (res && res.message) || 'No performance data collected for this range yet.' });
        return;
      }
      this._perf.data = res;
      this.setState({ pmLoading: false, pmError: '' });
    }).catch(() => {
      if (this._perf.key !== key) return;
      this._perf.data = null;
      this.setState({ pmLoading: false, pmError: 'Could not load performance data.' });
    });
  },

  // Bucketed max — see the header note on why max and not average.
  pmReduce(values, target) {
    if (values.length <= target) return values.map((v, i) => ({ v, i }));
    const size = values.length / target;
    const out = [];
    for (let b = 0; b < target; b++) {
      const start = Math.floor(b * size), end = Math.min(values.length, Math.floor((b + 1) * size));
      let best = null, bestIdx = start;
      for (let i = start; i < end; i++) {
        const v = values[i];
        if (v == null) continue;
        if (best == null || v > best) { best = v; bestIdx = i; }
      }
      out.push({ v: best == null ? 0 : best, i: bestIdx });
    }
    return out;
  },

  // Round a max up to a readable axis top (1/2/5 × 10^n).
  pmNiceMax(max) {
    if (!(max > 0)) return 1;
    const mag = Math.pow(10, Math.floor(Math.log10(max)));
    const n = max / mag;
    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * mag;
  },

  pmFmt(v, digits) {
    if (v == null) return '—';
    if (digits === 0) return Math.round(v).toLocaleString();
    return (Math.round(v * Math.pow(10, digits)) / Math.pow(10, digits)).toFixed(digits);
  },

  // One chart's geometry. W/H are viewBox units, not pixels — the SVG stretches.
  pmChart(def, samples, points, hoverIdx) {
    const W = 1000, H = 200;
    const raw = samples.map(x => x[def.key]);
    const reduced = this.pmReduce(raw, this.PM_MAX_POINTS);
    let peak = 0; reduced.forEach(p => { if (p.v > peak) peak = p.v; });
    if (def.ceiling > peak) peak = def.ceiling;
    const top = this.pmNiceMax(peak);
    const stepX = reduced.length > 1 ? W / (reduced.length - 1) : W;
    const y = v => H - (Math.max(0, Math.min(top, v || 0)) / top) * H;
    const pts = reduced.map((p, i) => (i * stepX).toFixed(1) + ',' + y(p.v).toFixed(1)).join(' ');
    const hoverAt = hoverIdx >= 0 && reduced.length
      ? Math.max(0, Math.min(reduced.length - 1, Math.round(hoverIdx * (reduced.length - 1)))) : -1;
    const hov = hoverAt >= 0 ? reduced[hoverAt] : null;
    const sample = hov ? samples[hov.i] : null;
    return {
      title: def.title, color: def.color, yLabel: def.yLabel,
      line: pts,
      // Close the polygon along the baseline for the translucent area fill.
      area: reduced.length ? '0,' + H + ' ' + pts + ' ' + ((reduced.length - 1) * stepX).toFixed(1) + ',' + H : '',
      yTicks: [1, 0.75, 0.5, 0.25, 0].map(f => ({ v: this.pmFmt(top * f, def.digits === 0 ? 0 : 1), gy: (H - f * H).toFixed(1) })),
      ceilingShow: !!(def.ceiling && def.ceiling <= top),
      ceilingY: def.ceiling ? y(def.ceiling).toFixed(1) : 0,
      ceilingLabel: def.ceiling ? def.ceiling + ' max' : '',
      hoverShow: !!sample,
      hoverX: hov ? ((hoverAt * stepX) / W * 100).toFixed(3) + '%' : '0%',
      hoverY: hov ? (y(hov.v) / H * 100).toFixed(3) + '%' : '0%',
      hoverVal: sample ? this.pmFmt(sample[def.key], def.digits) + (def.unit || '') : ''
    };
  },

  computePerf(s) {
    const real = this._detail;
    const env = real ? this.currentEnv(real, s) : null;
    const enabled = this.perfEnabledFor(env);
    const data = (this._perf && this._perf.data) || null;
    const samples = data ? data.samples : [];
    const maxWorkers = data ? (data.max_workers || 0) : 0;
    const vals = k => samples.map(x => x[k]).filter(v => v != null);
    const avg = a => a.length ? a.reduce((x, y) => x + y, 0) / a.length : 0;
    const peak = a => a.length ? Math.max.apply(null, a) : 0;
    const hoverFrac = s.pmHover;
    const hovered = (hoverFrac >= 0 && samples.length)
      ? samples[Math.max(0, Math.min(samples.length - 1, Math.round(hoverFrac * (samples.length - 1))))]
      : null;
    const fmtTime = t => { try { return new Date(t).toLocaleString(); } catch (e) { return String(t); } };
    // Axis labels: five evenly spaced stamps, terse inside a day.
    const shortStamp = t => { const d = new Date(t);
      return s.pmHours && s.pmHours <= 24
        ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); };

    const defs = [
      { key: 'db', title: 'DB Connections', yLabel: 'Connections', color: '#00c853', digits: 0 },
      { key: 'load', title: 'Server Load', yLabel: 'Load', color: '#00a6d6', digits: 2 },
      { key: 'resp', title: 'Response Time (seconds)', yLabel: 'Seconds', color: '#8a5cf6', digits: 3, unit: 's' },
      { key: 'workers', title: 'PHP Workers', yLabel: 'Workers', color: '#f57c00', digits: 0, ceiling: maxWorkers }
    ];

    return {
      // ── Stats-tab card ──────────────────────────────────────────────
      pmCardShow: !!(real && env),
      pmEnabled: enabled,
      pmSwitchBg: enabled ? 'var(--brand)' : 'var(--rule)',
      pmSwitchJust: enabled ? 'flex-end' : 'flex-start',
      // 30s cadence is the collector's real interval — the CLI sizes its tail
      // as hours × 120 samples, and consecutive stamps measure exactly 30s.
      pmStatusText: enabled ? 'Sampling every 30 seconds' : 'Off — no samples are being collected',
      pmToggle: () => this.togglePerfMonitor(),
      pmOpenBtn: () => this.openPerfMonitor(),

      // ── Fullscreen monitor ──────────────────────────────────────────
      pmOpen: !!s.pmOpen,
      pmClose: () => this.closePerfMonitor(),
      pmTitle: (env && env.home_url) || (real && real.site && real.site.name) || 'Performance Monitor',
      pmRanges: this.PM_RANGES.map(([label, hours]) => ({ label,
        bg: s.pmHours === hours ? 'var(--brand)' : 'transparent',
        fg: s.pmHours === hours ? '#fff' : 'var(--ink-dim)',
        go: () => this.setPerfHours(hours) })),
      pmRefresh: () => this.loadPerfData(s.pmHours, true),
      pmLoading: !!s.pmLoading,
      pmError: s.pmError || '',
      pmHasError: !!s.pmError,
      pmHasData: !!(data && samples.length) && !s.pmLoading,
      pmSubtitle: samples.length
        ? samples.length.toLocaleString() + ' samples  |  ' + fmtTime(samples[0].time) + ' — ' + fmtTime(samples[samples.length - 1].time)
        : '',
      pmHoverStamp: hovered ? fmtTime(hovered.time) : '',
      pmHoverShow: !!hovered,
      // One mousemove drives ALL four charts (v1's synced crosshair, minus
      // Chart.js): store the 0–1 position and let each chart resolve its own
      // nearest point from it.
      pmMove: e => { const r = e.currentTarget.getBoundingClientRect();
        if (!r.width) return;
        this.setState({ pmHover: Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)) }); },
      pmLeave: () => this.setState({ pmHover: -1 }),
      pmTiles: !samples.length ? [] : [
        { k: 'Avg DB Conns', v: this.pmFmt(avg(vals('db')), 1), c: '#00c853' },
        { k: 'Peak DB Conns', v: this.pmFmt(peak(vals('db')), 0), c: '#e53935' },
        { k: 'Avg Load', v: this.pmFmt(avg(vals('load')), 2), c: '#00a6d6' },
        { k: 'Peak Load', v: this.pmFmt(peak(vals('load')), 2), c: '#e53935' },
        { k: 'Avg Response', v: this.pmFmt(avg(vals('resp')), 4) + 's', c: '#8a5cf6' },
        { k: 'Peak Response', v: this.pmFmt(peak(vals('resp')), 3) + 's', c: '#e53935' }
      ].concat(maxWorkers > 0 ? [{ k: 'Peak Workers', v: this.pmFmt(peak(vals('workers')), 0) + '/' + maxWorkers, c: '#f57c00' }] : []),
      pmCharts: samples.length ? defs.map(d => this.pmChart(d, samples, null, hoverFrac)) : [],
      pmXLabels: samples.length
        ? [0, 0.25, 0.5, 0.75, 1].map(f => ({ t: shortStamp(samples[Math.round(f * (samples.length - 1))].time) }))
        : []
    };
  }

});
