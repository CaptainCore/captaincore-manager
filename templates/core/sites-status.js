// ── Sites list: status ─────────────────────────────────────────────────────
// Each site carries `health` from Sites::list(): state down | attention |
// removed | ok, a label and, for an outage, `since`. Down = an open uptime
// outage (monitor_incidents); attention = the production backup check failed
// ("Backups behind" when the newest snapshot is over 36 hours old, else
// "Backups failing") or the last sync could not connect. The Status column,
// the Down / Needs attention filter chips and every health dot (pinned strip,
// cards, account pages) read it. Design-mode fixtures have no `health`, so
// they keep the sample vuln/updates fields.
Object.assign(Component.prototype, {

  siteHealthOf(x) {
    const h = x && x.health;
    if (!h) return x && x.vuln ? ['Vulnerability', 'var(--bad)'] : x && x.updates ? ['Updates pending', 'var(--warn)'] : ['No issues', 'var(--ok)'];
    const tone = { down: 'var(--bad)', attention: 'var(--warn)', removed: 'var(--ink-dim)' }[h.state] || 'var(--ok)';
    return [h.label || 'No issues', tone];
  },

  siteStatusCell(x) {
    const h = (x && x.health) || {};
    const [label, dot] = this.siteHealthOf(x);
    let text = label;
    if (h.state === 'down' && h.since) {
      const ago = this.agoText(this.parseTs(h.since) * 1000);
      if (ago && ago !== 'just now') text = 'Down for ' + ago.replace(/ ago$/, '');
    }
    const fg = { down: 'var(--bad)', attention: 'color-mix(in srgb,var(--warn) 70%,var(--ink))' }[h.state] || (h.state === 'removed' ? 'var(--ink-dim)' : 'var(--ink-dim)');
    return { stText: text, stDot: dot, stFg: fg, stTitle: h.detail || text,
      stRing: 'color-mix(in srgb,' + dot + ' 22%,transparent)', stWeight: h.state === 'down' || h.state === 'attention' ? 600 : 400 };
  },

  // Production first, then Staging, then the rest; two chips and "+N".
  siteEnvChips(x) {
    const names = ((x.environmentsRaw && x.environmentsRaw.length)
      ? x.environmentsRaw.map(e => e.environment)
      : String(x.envs || 'Prod').split(' · ').map(l => l === 'Prod' ? 'Production' : l)).filter(Boolean);
    const rank = n => n === 'Production' ? 0 : n === 'Staging' ? 1 : 2;
    const sorted = names.slice().sort((a, b) => rank(a) - rank(b));
    const chips = sorted.slice(0, 2).map(en => ({ label: en === 'Production' ? 'Prod' : en,
      go: (ev) => { ev.stopPropagation(); this.openSite(x.id, en); } }));
    const more = sorted.length - chips.length;
    return { envChips: chips, envMore: more > 0 ? '+' + more : '', hasEnvMore: more > 0,
      envMoreTitle: sorted.slice(2).join(', ') };
  },

  // ── Sites table: the last column's metric ──
  // One column, switched from its header menu between Visits / yr and Size
  // (production + every environment's storage), so sorting by size adds no
  // column. The pick is remembered per browser (cc-sites-metric).
  SITE_METRICS: [['visits', 'Visits / yr'], ['size', 'Size']],

  siteMetric(s) {
    if (s.siteMetric) return s.siteMetric;
    let m = '';
    try { m = localStorage.getItem('cc-sites-metric') || ''; } catch (e) {}
    return this.SITE_METRICS.some(([k]) => k === m) ? m : 'visits';
  },

  siteMetricCol(s) {
    return this.siteMetric(s) === 'size'
      ? { label: 'Size', k: 'size', val: x => Number(x.storageBytes) || 0 }
      : { label: 'Visits / yr', k: 'visits', val: x => parseInt(String(x.visits).replace(/\D/g, ''), 10) || 0 };
  },

  siteMetricCell(s, x) {
    return this.siteMetric(s) === 'size' ? (Number(x.storageBytes) ? x.storage : '\u2014') : x.visits;
  },

  // Picking a metric sorts by it, largest first, so "Size" answers "which
  // sites are biggest" in one click; clicking the header still flips it.
  pickSiteMetric(k) {
    try { localStorage.setItem('cc-sites-metric', k); } catch (e) {}
    this.setState({ siteMetric: k, ddOpen: '', sitesSort: { k, d: -1 }, sitesPage: 1 });
  },

  // The header cells from mkSortCols, with the metric menu on the last one.
  siteMetricCols(cols, s) {
    const cur = this.siteMetric(s), open = s.ddOpen === 'siteMetric';
    return cols.map((c, i) => i < cols.length - 1 ? { ...c, menu: false } : { ...c, menu: true,
      menuOpen: open,
      menuToggle: (e) => { e.stopPropagation(); this.setState(st => ({ ddOpen: st.ddOpen === 'siteMetric' ? '' : 'siteMetric' })); },
      menuClose: (e) => { e && e.stopPropagation && e.stopPropagation(); this.setState({ ddOpen: '' }); },
      menuItems: this.SITE_METRICS.map(([k, label]) => ({ label, mark: k === cur ? '\u2713' : '',
        pick: (e) => { e.stopPropagation(); this.pickSiteMetric(k); } })) });
  },

  // On a phone the table cannot fit, so it shows as cards unless Table was
  // picked there on purpose.
  sitesViewVals(s) {
    let narrow = false;
    try { narrow = window.matchMedia('(max-width: 700px)').matches; } catch (e) {}
    const view = narrow && s.view === 'table' && !s.viewForced ? 'cards' : s.view;
    const on = v => view === v;
    return { viewTable: on('table'), viewCards: on('cards'), viewList: on('list'),
      tblBg: on('table') ? 'var(--panel-2)' : 'transparent', tblFg: on('table') ? 'var(--ink)' : 'var(--ink-dim)',
      crdBg: on('cards') ? 'var(--panel-2)' : 'transparent', crdFg: on('cards') ? 'var(--ink)' : 'var(--ink-dim)',
      lstBg: on('list') ? 'var(--panel-2)' : 'transparent', lstFg: on('list') ? 'var(--ink)' : 'var(--ink-dim)' };
  },

  statusChipVals(s, fleet) {
    const n = { down: 0, attention: 0 };
    fleet.forEach(x => { const st = (x.health || {}).state; if (n[st] !== undefined) n[st]++; });
    const chips = [['down', 'Down', 'var(--bad)'], ['attention', 'Needs attention', 'var(--warn)']]
      .filter(([k]) => n[k] > 0 || s.fStatus === k)
      .map(([k, label, tone]) => {
        const on = s.fStatus === k;
        return { label, n: String(n[k]), dot: tone,
          bd: on ? tone : 'var(--rule)', bg: on ? 'color-mix(in srgb,' + tone + ' 12%,var(--paper))' : 'var(--paper)',
          fg: on ? 'var(--ink)' : 'var(--ink-dim)', pressed: on ? 'true' : 'false',
          go: () => this.setState({ fStatus: on ? '' : k, sitesPage: 1 }) };
      });
    return { statusChips: chips, hasStatusChips: chips.length > 0 };
  }
});
