// CaptainCore v3 — Security & Site Audits real-data layer (mixin).
// Admin-gated. Lazy-loads on first render of each route.
//
// Security (computeSecurity): GET /security-threats {threats[],total_threats,
//   severity_summary}, GET /security-coverage {coverage_pct,by_type,…},
//   GET /checksum-failures [core], GET /plugin-checksum-failures {failures,
//   plugin_totals}. Threat actions: POST /security-threats/track|note|resolve
//   keyed on (slug,version,type). Statuses new|investigating|reported|resolved.
// Site Audits (computeAudits): GET /site-audits (list w/ finding_counts),
//   POST /site-audits/request {site_id,environment,report_type},
//   POST|DELETE /site-audits/{id}/publish, POST /site-audits/{id}/cancel,
//   GET /site-audits/{id}/html (nonce → blob).

Object.assign(Component.prototype, {

  SEV_STYLE: { critical: ['var(--bad-soft)', 'var(--bad)'], high: ['var(--bad-soft)', 'var(--bad)'],
    medium: ['var(--warn-soft)', 'var(--ink)'], low: ['var(--panel-2)', 'var(--ink-dim)'] },
  THREAT_ST_BG: { new: 'var(--bad-soft)', investigating: 'var(--warn-soft)', reported: 'var(--brand-soft)', resolved: 'var(--ok-soft)' },

  loadSecurity(force) {
    if (this._secLoading || (this._sec && !force)) return;
    this._secLoading = true;
    Promise.allSettled([
      this.api('/security-threats'), this.api('/security-coverage'),
      this.api('/checksum-failures'), this.api('/plugin-checksum-failures')
    ]).then(([t, c, ck, pk]) => {
      this._secLoading = false;
      this._sec = {
        threats: (t.status === 'fulfilled' && t.value && Array.isArray(t.value.threats)) ? t.value : { threats: [] },
        coverage: (c.status === 'fulfilled' && c.value && !c.value.code) ? c.value : null,
        core: (ck.status === 'fulfilled' && Array.isArray(ck.value)) ? ck.value : [],
        plug: (pk.status === 'fulfilled' && pk.value && Array.isArray(pk.value.failures)) ? pk.value : { failures: [], plugin_totals: [] }
      };
      this.setState({});
    });
  },

  threatKey(t) { return { slug: t.slug, version: t.version, type: t.type }; },

  trackThreat(t, status) {
    this.api('/security-threats/track', { method: 'POST', body: { ...this.threatKey(t), status } })
      .then(() => this.loadSecurity(true)).catch(() => {});
  },
  resolveThreat(t) {
    this.api('/security-threats/resolve', { method: 'POST', body: this.threatKey(t) })
      .then(() => this.loadSecurity(true)).catch(() => {});
  },
  noteThreat(t) {
    const note = (this.state.noteDraft || '').trim();
    if (!note) return;
    this.setState({ noteDraft: '' });
    this.api('/security-threats/note', { method: 'POST', body: { ...this.threatKey(t), note } })
      .then(() => this.loadSecurity(true)).catch(() => {});
  },

  threatToTerminal(t) {
    const ids = (t.affected_sites || []).map(s => String(s.environment_id)).filter(Boolean);
    this.setState({ dockOpen: true, termSel: ids });
  },

  // ── Coverage map (Security → Coverage) ─────────────────────────────────
  // GET /security-coverage/map?type=plugin|theme → {summary, rows[]} with one
  // positional row per slug (see captaincore_security_coverage_map_func for the
  // layout). Rendered outside React: the DC runtime has no innerHTML binding,
  // and ~6k cells with a hover tooltip re-rendering through setState would
  // crawl, so the board's ref builds the grid once per payload and a delegated
  // listener drives the tooltip + click. Admin projection (operators only).
  COV_TIERS: [
    ['clean',     'clean',     'var(--ok)',                                    'audited, nothing found'],
    ['low',       'low',       'color-mix(in srgb, var(--ok) 55%, var(--panel-2))', 'low-severity findings'],
    ['medium',    'medium',    'var(--warn)',                                  'medium-severity findings'],
    ['high',      'high',      'color-mix(in srgb, var(--warn) 45%, var(--bad))',   'high-severity findings'],
    ['critical',  'critical',  'var(--bad)',                                   'critical findings or malware'],
    ['unaudited', 'unaudited', 'var(--panel-2)',                               'no audited build on the fleet']
  ],
  // Column 11 is the number of sites on a nulled/malware-flagged build; those
  // builds never set `status` (a nulled copy is a site problem, not a plugin
  // verdict), they ring the cell and open on request from the dialog.
  COV_COL: { rank: 0, slug: 1, name: 2, sites: 3, active: 4, status: 5, findings: 6, auditedBuilds: 7, builds: 8,
    hash: 9, version: 10, flaggedSites: 11, auditedSites: 12, versions: 13, versionCount: 14, primarySites: 15,
    flaggedHash: 16, flaggedBuilds: 17 },

  loadCoverageMap(type, force) {
    const t = type === 'theme' ? 'theme' : 'plugin';
    this._cm = this._cm || {};
    if (this._cm[t + ':loading']) return;
    if (this._cm[t] && !force) return;
    this._cm[t + ':loading'] = true;
    this.setState({});
    this.api('/security-coverage/map?type=' + t + (force ? '&refresh=1' : ''))
      .then(res => {
        this._cm[t + ':loading'] = false;
        this._cm[t] = (res && Array.isArray(res.rows)) ? res : { rows: [], summary: null, err: (res && res.message) || 'Could not load the coverage map.' };
        this.setState({});
      })
      .catch(() => { this._cm[t + ':loading'] = false;
        this._cm[t] = { rows: [], summary: null, err: 'Could not load the coverage map.' }; this.setState({}); });
  },

  // Click on a cell: open the shared findings dialog for the slug's primary
  // build (its worst audited hash, or the most-installed hash when unaudited).
  openFleetFindings(row, type) {
    const C = this.COV_COL, hash = row[C.hash];
    const n = v => Number(v || 0).toLocaleString();
    const sites = (k, v) => n(v) + ' site' + (v === 1 ? '' : 's');
    // Meta line as parts: every site count is a link that opens the Sites
    // list filtered to this slug (all builds) or to one exact build (hash).
    const slug = row[C.slug];
    const link = (v, h) => ({ text: sites(0, v), go: () => this.openSitesForBuild(type, slug, h ? '' : row[C.version], h || '') });
    const bits = [[{ text: 'Installed on ' }, link(row[C.sites]), ...(row[C.active] !== row[C.sites] ? [{ text: ' (' + n(row[C.active]) + ' active)' }] : [])]];
    if (row[C.builds]) bits.push([{ text: row[C.auditedBuilds] + ' of ' + row[C.builds] + ' build' + (row[C.builds] === 1 ? '' : 's') + ' audited' }]);
    if (row[C.status] !== 'unaudited' && row[C.builds] > 1)
      bits.push([{ text: 'showing the worst legitimate build, on ' }, link(row[C.primarySites], hash)]);
    const flagged = row[C.flaggedSites] > 0;
    if (flagged) bits.push([link(row[C.flaggedSites], row[C.flaggedHash]), { text: ' run' + (row[C.flaggedSites] === 1 ? 's' : '') + ' a nulled or malware-flagged build' }]);
    const joinBits = list => list.flatMap((b, i) => i ? [{ text: ' · ' }, ...b] : b);
    const seed = { display_name: row[C.name], slug: row[C.slug], version: row[C.version], status: row[C.status],
      malware: false, hash, component_type: type, findings: null };
    // The flagged build opens on request, never by default: its verdict is
    // about the site carrying it, not the plugin.
    const alt = flagged && row[C.flaggedHash] ? { label: 'Open the flagged build →',
      go: () => this.openFleetHash(row[C.flaggedHash],
        { display_name: row[C.name], slug: row[C.slug], version: '', status: 'critical', malware: true, hash: row[C.flaggedHash], component_type: type, findings: null },
        [{ text: 'Nulled or malware-flagged build · on ' }, link(row[C.flaggedSites], row[C.flaggedHash]), ...(row[C.flaggedBuilds] > 1 ? [{ text: ' across ' + row[C.flaggedBuilds] + ' flagged builds (most installed shown)' }] : [])],
        { label: '← Back to the legitimate build', go: () => this.openFleetFindings(row, type) }) } : null;
    if (!hash) {
      // No content hash on the fleet yet (a sync predating hashes): nothing to look up.
      this.setState({ rgHash: 'nohash:' + row[C.slug], rgLoading: false, rgOpenIdx: -1, rgDetail: seed,
        rgFleet: { meta: [...joinBits(bits), { text: ' · no content hash synced yet' }], alt } });
      return;
    }
    this.openFleetHash(hash, seed, joinBits(bits), alt);
  },

  openFleetHash(hash, seed, meta, alt) {
    this.setState({ rgHash: hash, rgLoading: true, rgOpenIdx: -1, rgDetail: seed, rgFleet: { meta, alt } });
    this.api('/security-coverage/hash/' + hash)
      .then(res => { if (this.state.rgHash !== hash) return;
        this.setState({ rgDetail: (res && res.hash && res.status !== 'unaudited') ? res : seed, rgLoading: false }); })
      .catch(() => { if (this.state.rgHash === hash) this.setState({ rgLoading: false }); });
  },

  covMapBuild(el, rows, type) {
    const C = this.COV_COL;
    const esc = v => String(v == null ? '' : v).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const html = [];
    for (let i = 0; i < rows.length; i++) {
      const r = rows[i];
      html.push('<i class="cc-cov-cell ' + r[C.status] + (r[C.flaggedSites] ? ' flagged' : '') + '" data-i="' + i + '" role="button" tabindex="0" aria-label="'
        + esc(r[C.name] + ', rank ' + r[C.rank] + ', ' + (r[C.status] === 'unaudited' ? 'not audited' : r[C.status]) + (r[C.flaggedSites] ? ', flagged build present' : '')) + '"></i>');
    }
    el.innerHTML = '<div class="cc-cov-grid">' + html.join('') + '</div><div class="cc-cov-pop" hidden></div>';
    el._cmRows = rows; el._cmType = type;
    if (el._cmBound) return;
    el._cmBound = true;
    const grid = () => el.firstChild, pop = () => el.lastChild;
    let current = null;
    const n = v => Number(v || 0).toLocaleString();
    const show = cell => {
      const r = el._cmRows[+cell.getAttribute('data-i')];
      if (!r) return;
      const st = r[C.status], audited = st !== 'unaudited', p = pop();
      const vers = (r[C.versions] || []).slice(0, 3).map(v => 'v' + esc(v)).join(', ')
        + (r[C.versionCount] > 3 ? ' +' + (r[C.versionCount] - 3) + ' more' : '');
      const share = r[C.sites] ? Math.round(r[C.auditedSites] * 100 / r[C.sites]) : 0;
      let foot;
      if (!audited) foot = r[C.flaggedBuilds] && r[C.flaggedBuilds] === r[C.auditedBuilds] ? 'no legitimate build audited yet'
        : r[C.builds] ? 'none of ' + r[C.builds] + ' build' + (r[C.builds] === 1 ? '' : 's') + ' on the fleet audited yet' : 'no content hash synced yet';
      else {
        foot = r[C.auditedBuilds] + ' / ' + r[C.builds] + ' builds audited · ' + share + '% of installs';
        if (r[C.builds] > 1 && r[C.primarySites] < r[C.sites]) foot += '<br>worst build v' + esc(r[C.version]) + ' on ' + n(r[C.primarySites]) + ' site' + (r[C.primarySites] === 1 ? '' : 's');
      }
      if (r[C.flaggedSites]) foot += '<div class="flag">⚠ ' + n(r[C.flaggedSites]) + ' site' + (r[C.flaggedSites] === 1 ? ' runs' : 's run') + ' a nulled or malware-flagged build</div>';
      p.className = 'cc-cov-pop ' + st;
      p.innerHTML =
        '<div class="ph"><i></i><b>' + esc(r[C.name]) + '</b><span class="r">#' + r[C.rank] + '</span></div>' +
        '<div class="slug">' + esc(r[C.slug]) + '</div>' +
        '<div class="row"><span class="grade">' + (audited ? st : 'unaudited') + '</span>' +
          '<span class="meta">' + n(r[C.sites]) + ' sites' + (vers ? ' <span>' + vers + '</span>' : '') + '</span></div>' +
        '<div class="bar"><div style="width:' + share + '%"></div></div>' +
        '<div class="foot">' + (audited && r[C.findings] ? r[C.findings] + ' finding' + (r[C.findings] === 1 ? '' : 's') + ' · ' : '') + foot + '</div>';
      p.hidden = false;
      const b = el.getBoundingClientRect(), c = cell.getBoundingClientRect();
      let left = c.left - b.left + c.width + 10, top = c.top - b.top + c.height + 10;
      if (left + 300 > b.width) left = Math.max(0, c.left - b.left - 310);
      if (top + p.offsetHeight > b.height) top = Math.max(0, c.top - b.top - p.offsetHeight - 10);
      p.style.left = left + 'px'; p.style.top = top + 'px';
      if (current && current !== cell) current.classList.remove('is-on');
      current = cell; cell.classList.add('is-on');
    };
    const hide = () => { pop().hidden = true; if (current) { current.classList.remove('is-on'); current = null; } };
    el.addEventListener('mouseover', e => { const c = e.target.closest('.cc-cov-cell'); if (c) show(c); });
    el.addEventListener('mouseleave', hide);
    el.addEventListener('focusin', e => { const c = e.target.closest('.cc-cov-cell'); if (c) show(c); });
    el.addEventListener('focusout', e => { if (!el.contains(e.relatedTarget)) hide(); });
    const open = c => { const r = el._cmRows[+c.getAttribute('data-i')]; if (r) this.openFleetFindings(r, el._cmType); };
    el.addEventListener('click', e => { const c = e.target.closest('.cc-cov-cell'); if (c) open(c); });
    el.addEventListener('keydown', e => { const c = e.target.closest('.cc-cov-cell');
      if (c && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); open(c); } });
  },

  covMapVals(s) {
    const type = s.cmType === 'theme' ? 'theme' : 'plugin';
    const cm = this._cm || {};
    if (s.route === 'security' && s.secTab === 'coverage' && !cm[type] && !cm[type + ':loading']) setTimeout(() => this.loadCoverageMap(type), 0);
    const bundle = cm[type];
    const loading = !!cm[type + ':loading'];
    const sum = bundle && bundle.summary;
    const rows = bundle ? bundle.rows : [];
    const off = s.cmOff || {};
    const n = v => Number(v || 0).toLocaleString();
    const noun = type === 'theme' ? 'theme' : 'plugin';
    const cmTiles = sum ? [
      { k: 'Install-weighted', v: (sum.weighted_pct || 0) + '%', sub: 'installs on an audited build', fg: sum.weighted_pct >= 80 ? 'var(--ink)' : 'var(--warn)' },
      { k: 'Audited', v: n(sum.audited), sub: (sum.total ? Math.round(sum.audited * 100 / sum.total) : 0) + '% of ' + n(sum.total) + ' ' + noun + 's', fg: 'var(--ink)' },
      { k: 'Gap', v: n(sum.unaudited), sub: 'no audited build yet', fg: sum.unaudited ? 'var(--warn)' : 'var(--ink)' },
      { k: 'Generated', v: bundle.generated ? String(bundle.generated).slice(0, 10) : '—', sub: bundle.cached ? 'cached up to 10 minutes' : 'fresh', fg: 'var(--ink)' }
    ] : [];
    const cmLegend = sum ? this.COV_TIERS.filter(([k]) => sum.tiers && sum.tiers[k]).map(([k, label, sw, blurb]) => ({
      label, n: n(sum.tiers[k]), sw, title: blurb, ring: 'none',
      op: off[k] ? '.45' : '1',
      go: () => this.setState(st => ({ cmOff: { ...(st.cmOff || {}), [k]: !(st.cmOff || {})[k] } })) })) : [];
    // Flagged is a highlight, not a tier: toggling it dims every cell WITHOUT a
    // nulled/malware build, so the ringed cells stand alone.
    if (sum && sum.malware) cmLegend.push({ label: 'flagged', n: n(sum.malware), sw: 'var(--paper)', ring: 'inset 0 0 0 2px var(--bad)',
      title: n(sum.malware) + ' ' + noun + (sum.malware === 1 ? ' has' : 's have') + ' a nulled or malware-flagged build on ' + n(sum.malware_sites) + ' site' + (sum.malware_sites === 1 ? '' : 's') + '. Flagged builds never set a cell\'s color; click to show only these.',
      op: s.cmOnlyFlagged ? '1' : '.85',
      go: () => this.setState(st => ({ cmOnlyFlagged: !st.cmOnlyFlagged })) });
    const dimClass = Object.keys(off).filter(k => off[k]).map(k => ' dim-' + k).join('') + (s.cmOnlyFlagged ? ' only-flagged' : '');
    const key = type + '|' + (bundle ? (bundle.generated || '') + '|' + rows.length : '');
    return {
      cmTiles, cmLegend,
      cmTypes: [['plugin', 'Plugins'], ['theme', 'Themes']].map(([id, label]) => ({ label,
        fg: type === id ? 'var(--ink)' : 'var(--ink-dim)', bg: type === id ? 'var(--panel-2)' : 'transparent',
        go: () => { this.setState({ cmType: id, cmOff: {} }); this.loadCoverageMap(id); } })),
      cmTitle: (type === 'theme' ? 'Theme' : 'Plugin') + ' coverage map',
      cmLead: 'One cell per ' + noun + ' across active production sites, ranked by install count. Color is the worst audited build present on the fleet.',
      cmLoading: loading && !bundle,
      cmRefreshing: loading && !!bundle,
      cmRefresh: () => this.loadCoverageMap(type, true),
      cmErr: (bundle && bundle.err) || '',
      cmErrShow: !!(bundle && bundle.err),
      cmShow: !!bundle && !bundle.err && rows.length > 0,
      cmEmpty: !!bundle && !bundle.err && rows.length === 0,
      cmNote: 'Cells run left to right, top to bottom in install order, so the top-left cell is the most installed ' + noun + '. Hover for details; click to open the findings for its worst legitimate build. A ringed cell has a nulled or malware-flagged copy on at least one site; that copy never sets the color, since it says something about the site rather than the ' + noun + '. Embargoed findings are included here and never in the customer Registry tab.',
      cmBoardRef: (el) => { if (!el) return;
        if (el._cmKey !== key) { el._cmKey = key; this.covMapBuild(el, rows, type); }
        if (el.firstChild && el.firstChild.className !== 'cc-cov-grid' + dimClass) el.firstChild.className = 'cc-cov-grid' + dimClass; },
      ...this.regDialogVals(s)
    };
  },

  realSecurityVals(s) {
    if (s.route === 'security' && !this._sec && !this._secLoading) setTimeout(() => this.loadSecurity(), 0);
    const sec = this._sec;
    const loading = this._secLoading && !sec;
    if (!sec) return { threats: [], secLoading: loading, secEmpty: !loading, secEmptyText: loading ? 'Loading security data…' : '',
      secSkelRows: loading ? Array.from({ length: 4 }, () => ({})) : [],
      coreFails: [], plugFails: [], covShowActions: false,
      covTiles: [], covBars: [], covNote: '', covSkelRows: loading ? Array.from({ length: 4 }, () => ({})) : [],
      ...this.covMapVals(s) };
    const notes = notesFor => notesFor; // unused; notes render from tracking
    const threats = (sec.threats.threats || []).map(t => {
      const id = [t.type, t.slug, t.version].join('|');
      const sev = (t.severity || 'low').toLowerCase();
      const [sevBg, sevFg] = this.SEV_STYLE[sev] || this.SEV_STYLE.low;
      const tr = t.tracking || {};
      const status = (tr.status || 'new');
      const f0 = (t.findings || [])[0] || {};
      return {
        id, sev: sev.charAt(0).toUpperCase() + sev.slice(1),
        name: t.title || t.slug,
        cve: f0.cve || f0.finding_code || t.slug + ' ' + t.version,
        patch: !!t.patch,
        status: status.charAt(0).toUpperCase() + status.slice(1),
        sevBg, sevFg, stBg: this.THREAT_ST_BG[status] || 'var(--panel-2)',
        siteCount: t.affected_count || (t.affected_sites || []).length,
        findings: (t.findings || []).map(f => f.title).filter(Boolean).join(' · ') || f0.description || '',
        rec: f0.recommendation || (t.patch && t.patch.description) || '',
        open: s.threatOpen === id,
        toggle: () => this.setState(st => ({ threatOpen: st.threatOpen === id ? '' : id, noteDraft: '' })),
        siteRows: (t.affected_sites || []).map(a => ({ name: a.name, go: () => this.openSite(String(a.site_id)) })),
        notes: (tr.notes || []).map(n => ({ who: 'Note', when: (n.date || '').slice(0, 16), text: n.note })),
        addNote: () => this.noteThreat(t),
        openTerm: () => this.threatToTerminal(t),
        getPatch: () => { if (t.patch && t.patch.download_url) this.safeOpen(t.patch.download_url); },
        markInv: () => this.trackThreat(t, 'investigating'),
        markRes: () => this.resolveThreat(t)
      };
    });
    const base = p => (p || '').split('/').slice(-2).join('/');
    // Row title is the environment's home_url (site_name repeats across
    // production + staging rows and doesn't say which is which).
    const envRow = c => ({ site: (c.home_url || '').replace(/^https?:\/\//, '').replace(/\/$/, '') || c.site_name,
      env: c.environment || '', envShow: !!c.environment });
    const coreFails = (sec.core || []).map((c, i) => {
      const d = c.core_checksum_details || {};
      const mod = (d.modified || []).length, missing = (d.missing || []).length, extra = (d.extra || []).length;
      return { id: 'core' + i, ...envRow(c), mod, extra: extra + missing,
        files: [...(d.modified || []).map(p => p + ' — modified'), ...(d.missing || []).map(p => p + ' — missing'), ...(d.extra || []).map(p => p + ' — extra')].map(p => ({ p })),
        open: s.ckOpen === 'core' + i,
        toggle: () => this.setState(st => ({ ckOpen: st.ckOpen === 'core' + i ? '' : 'core' + i })),
        sshMark: s.copied === 'sshcore' + i ? 'Copied ✓' : 'Copy SSH',
        copySSH: (e) => { e.stopPropagation(); try { navigator.clipboard.writeText(c.username && c.address ? ('ssh ' + c.username + '@' + c.address + (c.port ? ' -p ' + c.port : '')) : c.home_url); } catch (err) {}
          this.setState({ copied: 'sshcore' + i }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        repair: (e) => { e.stopPropagation(); this.setState({ dockOpen: true }); } };
    });
    const plugFails = (sec.plug.failures || []).map((c, i) => {
      const mod = (c.plugin_checksum_details && c.plugin_checksum_details.modified) || [];
      return { id: 'plug' + i, ...envRow(c), slug: (c.slugs_affected || []).join(', ') || '—',
        chips: mod.slice(0, 4).map(m => ({ f: base(m.slug + '/' + m.file) })),
        open: s.ckOpen === 'plug' + i,
        toggle: () => this.setState(st => ({ ckOpen: st.ckOpen === 'plug' + i ? '' : 'plug' + i })),
        diff: mod.map(m => ({ text: m.slug + '/' + m.file + ' — ' + (m.message || 'changed'), fg: 'var(--ink-dim)', bg: 'transparent' })) };
    });
    const cov = sec.coverage;
    const covTiles = cov ? [
      { k: 'Fleet coverage', v: (cov.coverage_pct != null ? cov.coverage_pct : 0) + '%', fg: cov.coverage_pct >= 80 ? 'var(--ink)' : 'var(--warn)' },
      { k: 'Audited builds', v: (cov.audited_hashes || 0).toLocaleString() + ' / ' + (cov.total_unique_hashes || 0).toLocaleString(), fg: 'var(--ink)' },
      { k: 'Unaudited', v: (cov.unaudited_hashes || 0).toLocaleString(), fg: cov.unaudited_hashes ? 'var(--warn)' : 'var(--ink)' },
      { k: 'Sites', v: (cov.total_sites || 0).toLocaleString(), fg: 'var(--ink)' }
    ] : [];
    const bt = (cov && cov.by_type) || {};
    const covBars = cov ? [['Plugins', bt.plugins], ['Themes', bt.themes], ['Must-use', bt.mu_plugins], ['Files', bt.files]]
      .filter(([, o]) => o).map(([k, o]) => { const pct = o.unique_hashes ? Math.round(o.audited / o.unique_hashes * 100) : 0;
        return { k, pct, fill: pct >= 80 ? 'var(--ok)' : pct >= 50 ? 'var(--warn)' : 'var(--bad)' }; }) : [];
    return {
      threats, secLoading: false,
      secEmpty: !threats.length && s.secTab === 'vulns',
      secEmptyText: 'No active threats across the fleet.',
      coreFails, plugFails,
      ckEmptyCore: !coreFails.length, ckEmptyPlug: !plugFails.length,
      covTiles, covBars, covShowActions: false, covSkelRows: [],
      covNote: cov ? ((cov.without_hashes ? ((cov.without_hashes.plugin || 0) + (cov.without_hashes.theme || 0)) + ' components have no content hash yet.' : '')) : '',
      ...this.covMapVals(s),
      ...this.realCoreRunVals(s)
    };
  },

  CORE_CLASS_LABEL: {
    'widget-factory': 'Widget factory',
    'php-fatal': 'PHP fatal',
    'named-parameter': 'Named parameter',
    'signature-mismatch': 'Signature mismatch',
    'undefined-constant': 'Undefined constant',
    'memory': 'CLI memory',
    'not-wp-root': 'Not a WordPress root',
    'http': 'HTTP probe',
    'boot': 'Boot',
    'render': 'Render',
    'theme-abspath-require': 'Theme path',
    'version': 'Version check',
    'ssh': 'SSH',
    'other': 'Other'
  },
  CORE_CLASS_STYLE: {
    'widget-factory': ['var(--bad-soft)', 'var(--bad)'],
    'php-fatal': ['var(--bad-soft)', 'var(--bad)'],
    'named-parameter': ['var(--bad-soft)', 'var(--bad)'],
    'signature-mismatch': ['var(--bad-soft)', 'var(--bad)'],
    'undefined-constant': ['var(--bad-soft)', 'var(--bad)'],
    'memory': ['var(--warn-soft)', 'var(--ink)'],
    'boot': ['var(--warn-soft)', 'var(--ink)'],
    'render': ['var(--warn-soft)', 'var(--ink)'],
    'theme-abspath-require': ['var(--warn-soft)', 'var(--ink)'],
    'not-wp-root': ['var(--panel-2)', 'var(--ink-dim)'],
    'http': ['var(--panel-2)', 'var(--ink-dim)'],
    'version': ['var(--panel-2)', 'var(--ink-dim)'],
    'ssh': ['var(--panel-2)', 'var(--ink-dim)'],
    'cli-oxygen': ['var(--brand-soft)', 'var(--brand-ink)'],
    'cli-known': ['var(--brand-soft)', 'var(--brand-ink)'],
    'cli-new': ['var(--warn-soft)', 'var(--ink)'],
    'cli-render': ['var(--warn-soft)', 'var(--ink)']
  },

  fmtDur(sec) {
    sec = parseInt(sec, 10) || 0;
    if (sec < 60) return sec + 's';
    const m = Math.floor(sec / 60), r = sec % 60;
    if (m < 60) return r ? (m + 'm ' + r + 's') : (m + 'm');
    return Math.floor(m / 60) + 'h ' + (m % 60) + 'm';
  },

  loadCoreRuns(force, runId) {
    if (this._coreRunsLoading) return;
    if (this._coreRuns && !force && !runId) return;
    this._coreRunsLoading = true;
    const wanted = runId || this.state.coreRunId;
    this.api('/core-update-runs?per_page=20').then(runs => {
      const list = Array.isArray(runs) ? runs : [];
      const pick = wanted
        ? (list.find(r => String(r.core_update_run_id) === String(wanted)) || list[0])
        : list[0];
      if (!pick) {
        this._coreRunsLoading = false;
        this._coreRuns = { runs: [], run: null, fails: [] };
        this.setState({});
        return;
      }
      const id = pick.core_update_run_id;
      return Promise.all([
        this.api('/core-update-runs/' + id),
        this.api('/core-update-runs/' + id + '/results?result=fail'),
        this.api('/core-update-runs/' + id + '/results?action=info')
      ]).then(([run, fails, infos]) => {
        this._coreRunsLoading = false;
        this._coreRuns = {
          runs: list,
          run: (run && !run.code) ? run : pick,
          fails: Array.isArray(fails) ? fails : [],
          infos: Array.isArray(infos) ? infos : []
        };
        this.setState({});
      });
    }).catch(() => {
      this._coreRunsLoading = false;
      this._coreRuns = { runs: [], run: null, fails: [] };
      this.setState({});
    });
  },

  selectCoreRun(id) {
    const current = this.state.coreRunId || (this._coreRuns && this._coreRuns.run && this._coreRuns.run.core_update_run_id);
    if (String(current) === String(id)) return;
    this.setState({ coreRunId: id, coreGroupOpen: '' });
    this.loadCoreRuns(true, id);
  },

  resolveCoreResult(id) {
    const current = this.state.coreRunId || (this._coreRuns && this._coreRuns.run && this._coreRuns.run.core_update_run_id);
    this.api('/core-update-results/' + id, { method: 'PUT', body: { status: 'resolved' } })
      .then(() => this.loadCoreRuns(true, current)).catch(() => {});
  },

  realCoreRunVals(s) {
    if (s.route === 'security' && s.secTab === 'core' && !this._coreRuns && !this._coreRunsLoading) {
      setTimeout(() => this.loadCoreRuns(), 0);
    }
    const data = this._coreRuns;
    const loading = this._coreRunsLoading && !data;
    if (!data) {
      return {
        coreHasRun: false, coreEmpty: true, coreEmptyText: loading ? 'Loading core probe runs…' : 'No core probe runs yet.',
        coreTiles: [], coreMeta: '', coreGroups: [], coreInfoGroups: [], coreHasInfo: false, coreRunPickerShow: false, coreRunLabel: '', ddCoreOpen: false, ddToggleCore: () => {}, ddCoreOpts: []
      };
    }
    const run = data.run;
    if (!run) {
      return {
        coreHasRun: false, coreEmpty: true, coreEmptyText: 'No core probe runs yet. Fleet probe results land here after update-core finishes.',
        coreTiles: [], coreMeta: '', coreGroups: [], coreInfoGroups: [], coreHasInfo: false, coreRunPickerShow: false, coreRunLabel: '', ddCoreOpen: false, ddToggleCore: () => {}, ddCoreOpts: []
      };
    }
    const failed = parseInt(run.failed_count, 10) || 0;
    const skipped = parseInt(run.skipped_count, 10) || 0;
    const total = parseInt(run.total, 10) || 0;
    const ver = run.version_resolved || run.version_requested || '—';
    const tiles = [
      { k: 'Sites', v: total.toLocaleString(), fg: 'var(--ink)' },
      { k: 'Passed', v: skipped.toLocaleString(), fg: 'var(--ink)' },
      { k: 'Failed', v: failed.toLocaleString(), fg: failed ? 'var(--bad)' : 'var(--ink)' },
      { k: 'Version', v: ver, fg: 'var(--ink)' }
    ];
    let meta = (run.version_requested || '') + (run.version_resolved && run.version_requested && run.version_resolved !== run.version_requested ? ' resolved to ' + run.version_resolved : '');
    if (run.duration_seconds) meta += (meta ? ' · ' : '') + this.fmtDur(run.duration_seconds);
    if (run.created_at) meta += (meta ? ' · ' : '') + String(run.created_at).slice(0, 16).replace('T', ' ');
    const fails = data.fails || [];
    const infos = data.infos || [];
    const rawGroups = Array.isArray(run.groups) ? run.groups.filter(g => g.result === 'fail') : [];
    const groups = rawGroups.map(g => {
      const key = g.error_class || 'other';
      const [bg, fg] = this.CORE_CLASS_STYLE[key] || ['var(--panel-2)', 'var(--ink-dim)'];
      const open = s.coreGroupOpen === key;
      const sites = open ? fails.filter(f => (f.error_class || 'other') === key).map(f => {
        const env = /-staging$/i.test(f.site || '') ? 'Staging' : (/production$/i.test(f.site || '') ? 'Production' : '');
        const reason = (f.reason || f.excerpt || '').replace(/\s+/g, ' ').slice(0, 160);
        const versions = (f.core_before || f.core_after)
          ? ((f.core_before || '?') + ' \u2192 ' + (f.core_after || '?'))
          : '';
        return {
          id: f.core_update_result_id,
          name: (f.home_url || '').replace(/^https?:\/\//, '').replace(/\/$/, '') || f.site,
          env, envShow: !!env,
          stage: f.stage || '',
          versions, versionsShow: !!versions,
          reason,
          status: f.status || 'open',
          canResolve: (f.status || 'open') !== 'resolved',
          go: () => { if (f.site_id) this.openSite(String(f.site_id)); },
          resolve: (e) => { e.stopPropagation(); this.resolveCoreResult(f.core_update_result_id); }
        };
      }) : [];
      return {
        key, label: this.CORE_CLASS_LABEL[key] || key || 'Other',
        n: String(g.n), fg, bg, open, sites, sitesShow: open && sites.length > 0,
        toggle: () => this.setState(st => ({ coreGroupOpen: st.coreGroupOpen === key ? '' : key }))
      };
    });
    const infoByClass = {};
    infos.forEach(f => {
      const key = f.error_class || (String(f.reason || '').indexOf('CLI-only (known)') === 0 ? 'cli-known' : 'cli-new');
      (infoByClass[key] || (infoByClass[key] = [])).push(f);
    });
    const infoGroups = Object.keys(infoByClass).sort().map(key => {
      const rows = infoByClass[key];
      const [bg, fg] = this.CORE_CLASS_STYLE[key] || ['var(--brand-soft)', 'var(--brand-ink)'];
      const open = s.coreInfoOpen === key;
      const sites = open ? rows.map(f => {
        const env = /-staging$/i.test(f.site || '') ? 'Staging' : (/production$/i.test(f.site || '') ? 'Production' : '');
        const reason = (f.reason || f.excerpt || '').replace(/\s+/g, ' ').slice(0, 200);
        const versions = (f.core_before || f.core_after)
          ? ((f.core_before || '?') + ' → ' + (f.core_after || '?'))
          : '';
        return {
          id: f.core_update_result_id,
          name: (f.home_url || '').replace(/^https?:\/\//, '').replace(/\/$/, '') || f.site,
          env, envShow: !!env,
          stage: f.stage || '',
          versions, versionsShow: !!versions,
          reason,
          status: f.status || 'open',
          canResolve: false,
          go: () => { if (f.site_id) this.openSite(String(f.site_id)); },
          resolve: () => {}
        };
      }) : [];
      return {
        key, label: this.CORE_CLASS_LABEL[key] || key || 'CLI info',
        n: String(rows.length), fg, bg, open, sites, sitesShow: open && sites.length > 0,
        toggle: () => this.setState(st => ({ coreInfoOpen: st.coreInfoOpen === key ? '' : key }))
      };
    });
    const coreHasInfo = infoGroups.length > 0;

    const selectedId = s.coreRunId || run.core_update_run_id;
    const runLabel = r => {
      const when = String(r.created_at || '').slice(0, 16).replace('T', ' ');
      const failN = parseInt(r.failed_count, 10) || 0;
      const tgt = r.target || '';
      const ver = r.version_resolved || r.version_requested || '';
      return [when, tgt, ver, failN + ' failed'].filter(Boolean).join(' · ');
    };
    const nq = (s.ddQ || '').trim().toLowerCase();
    const selected = (data.runs || []).find(r => String(r.core_update_run_id) === String(selectedId)) || run;
    const ddCoreOpts = (data.runs || []).filter(r => !nq || runLabel(r).toLowerCase().includes(nq)).map(r => {
      const on = String(r.core_update_run_id) === String(selectedId);
      return {
        label: runLabel(r),
        mark: on ? '✓' : '',
        bg: on ? 'var(--brand-soft)' : 'transparent',
        pick: () => { this.setState({ ddOpen: '', ddQ: '' }); this.selectCoreRun(r.core_update_run_id); }
      };
    });
    return {
      coreHasRun: true, coreEmpty: false, coreEmptyText: '',
      coreTiles: tiles, coreMeta: meta.trim(), coreGroups: groups, coreInfoGroups: infoGroups, coreHasInfo,
      coreRunPickerShow: (data.runs || []).length > 0,
      coreRunLabel: selected ? runLabel(selected) : 'Select a run',
      ddCoreOpen: s.ddOpen === 'coreRun',
      ddToggleCore: () => this.setState(st => ({ ddOpen: st.ddOpen === 'coreRun' ? '' : 'coreRun', ddQ: '' })),
      ddCoreOpts
    };
  },

  // ── Site Audits ──────────────────────────────────────────────
  loadAudits(force) {
    if (this._audLoading || (this._aud && !force)) return;
    this._audLoading = true;
    this.api('/site-audits').then(res => {
      this._audLoading = false;
      this._aud = Array.isArray(res) ? res : (res && res.items) || [];
      this.setState({});
    }).catch(() => { this._audLoading = false; this._aud = []; this.setState({}); });
  },

  AUDIT_ST: { requested: ['Queued', 'var(--panel-2)'], in_progress: ['Running', 'var(--warn-soft)'],
    clean: ['Clean', 'var(--ok-soft)'], issues_found: ['Issues found', 'var(--warn-soft)'],
    compromised: ['Compromised', 'var(--bad-soft)'], remediated: ['Remediated', 'var(--ok-soft)'] },
  AUDIT_TYPE_LABEL: { security_audit: 'Security', malware_incident: 'Malware', performance_review: 'Performance',
    accessibility_audit: 'Accessibility', debug_report: 'Debug', incident_report: 'Incident' },

  // The list endpoint returns report_path only; published reports live at /reports/<file>.
  auditReportUrl(a) {
    if (a.report_url) return a.report_url;
    if (!a.report_path) return '';
    const home = ((window.CC_BOOT && window.CC_BOOT.homeLink) || location.origin).replace(/\/+$/, '');
    return home + '/reports/' + a.report_path;
  },

  openAuditReport(a) {
    if (a.report_url) { this.safeOpen(a.report_url); return; }
    const boot = window.CC_BOOT || {};
    fetch(boot.restRoot + 'captaincore/v1/site-audits/' + a.site_audit_id + '/html', { headers: { 'X-WP-Nonce': boot.nonce } })
      .then(r => r.text()).then(html => {
        const w = window.open('', '_blank');
        if (w) { w.document.open(); w.document.write(html); w.document.close(); }
      }).catch(() => {});
  },

  realAuditsVals(s) {
    if (s.route === 'audits' && !this._aud && !this._audLoading) setTimeout(() => this.loadAudits(), 0);
    const list = this._aud;
    if (!list) return { audRows: [], audEmpty: true, audEmptyText: this._audLoading ? 'Loading audits…' : '' };
    const reload = () => this.loadAudits(true);
    const audRows = list.map(a => {
      const [stLabel, stBg] = this.AUDIT_ST[a.status] || ['Queued', 'var(--panel-2)'];
      const published = !!a.report_path;
      const fc = a.finding_counts || {};
      const terminal = ['clean', 'issues_found', 'compromised', 'remediated'].includes(a.status);
      return {
        id: a.site_audit_id, site: a.site_name || ('site ' + a.site_id),
        env: a.environment || 'Production',
        types: this.AUDIT_TYPE_LABEL[a.report_type] || 'Audit',
        when: (a.created_at || '').slice(0, 10),
        findings: fc.total ? (fc.open || 0) + ' open · ' + (fc.resolved || 0) + ' resolved' : (a.issues_count ? a.issues_count + ' issues' : '—'),
        status: published ? 'Published' : stLabel,
        stBg: published ? 'var(--ok-soft)' : stBg,
        done: terminal, pub: published,
        pubLabel: published ? 'Unpublish' : 'Publish',
        cancellable: a.status === 'requested',
        view: () => this.openAuditReport(a),
        togglePub: () => this.api('/site-audits/' + a.site_audit_id + '/publish', { method: published ? 'DELETE' : 'POST', body: {} }).then(reload).catch(() => {}),
        copyLink: () => { const url = this.auditReportUrl(a); if (!url) return;
          try { navigator.clipboard.writeText(url); } catch (e) {}
          this.setState({ copied: 'aud' + a.site_audit_id }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        mark: s.copied === 'aud' + a.site_audit_id ? 'Copied ✓' : 'Copy link',
        cancel: async () => { if (!(await this.uiConfirm('Cancel this audit request?', { label: 'Cancel request', danger: true }))) return;
          this.api('/site-audits/' + a.site_audit_id + '/cancel', { method: 'POST', body: {} }).then(reload).catch(() => {}); }
      };
    });
    return {
      audRows, audEmpty: !audRows.length, audEmptyText: 'No site audits yet.',
      requestAudit: () => { const f = this.FLEET.find(x => x.name === this.state.audSite);
        if (!f) return;
        const env = (f.environmentsRaw || [])[0];
        this.api('/site-audits/request', { method: 'POST', body: { site_id: Number(f.id), environment: 'Production', report_type: 'security_audit' } })
          .then(reload).catch(() => {}); }
    };
  },

  // ── Signals: the security-signal review queue ───────────────────────────
  // GET /malware-findings/search {items,total,counts,emails_enabled}, filtered by
  // view (attention|unreviewed|open|resolved|all), source and search. A row's
  // follow-up trail loads on expand from GET /malware-findings/{id}. Writes:
  // POST /malware-findings/{id}/verdict, /acknowledge, /resolve, /{id}/notes,
  // /settings {emails_enabled}. Agents use the same routes; see the
  // security-signal docs in the dev-captaincore skill.
  SIG_VIEWS: [['attention', 'Needs attention'], ['unreviewed', 'Unreviewed'], ['open', 'Open'], ['resolved', 'Resolved'], ['all', 'All']],
  SIG_VERDICT_STYLE: { compromise: ['var(--bad-soft)', 'var(--bad)'], residue: ['var(--warn-soft)', 'var(--ink)'], actionable: ['var(--warn-soft)', 'var(--ink)'],
    'needs-human': ['var(--brand-soft)', 'var(--brand-ink)'], benign: ['var(--ok-soft)', 'var(--ok)'], '': ['var(--panel-2)', 'var(--ink-dim)'] },
  SIG_SOURCE_LABEL: { capture: 'Injection', 'core-checksum': 'Core checksum', config: 'Role settings', session: 'Sessions', 'db-scan': 'Database', native: 'Files', 'native-full': 'Files (full)',
    provenance: 'Root files', 'provenance-scan': 'Root files (rules)', 'hidden-plugin': 'Hidden plugin', media: 'Media', wordfence: 'Wordfence',
    license: 'License', 'domain-change': 'Domain change' },

  sigQuery(s) {
    const q = { view: s.sigView || 'attention', source: s.sigSource || '', search: s.sigSearch || '', page: s.sigPage || 1 };
    return q;
  },

  loadSignals(force) {
    const q = this.sigQuery(this.state);
    const key = JSON.stringify(q);
    if (!force && (this._sigKey === key || this._sigLoadingKey === key)) return;
    this._sigLoadingKey = key;
    const qs = '?view=' + encodeURIComponent(q.view) + '&source=' + encodeURIComponent(q.source) + '&search=' + encodeURIComponent(q.search) + '&page=' + q.page + '&per_page=50';
    this.api('/malware-findings/search' + qs).then(res => {
      if (this._sigLoadingKey !== key) return;
      this._sigLoadingKey = '';
      this._sigKey = key;
      this._sig = (res && Array.isArray(res.items)) ? res : { items: [], total: 0, counts: {}, emails_enabled: false };
      this.setState({});
    }).catch(() => { this._sigLoadingKey = ''; this._sigKey = key; this._sig = { items: [], total: 0, counts: {}, error: true }; this.setState({}); });
  },

  loadSignalNotes(id) {
    this._sigNotes = this._sigNotes || {};
    this.api('/malware-findings/' + id).then(row => {
      this._sigNotes[id] = (row && Array.isArray(row.notes)) ? row.notes : [];
      this.setState({});
    }).catch(() => {});
  },

  // Run one write, toast the outcome, then refresh the list and the open row's trail.
  sigWrite(path, body, label, id) {
    const tid = this.toast(label + '…', { kind: 'loading' });
    return this.api(path, { method: 'POST', body }).then(res => {
      if (res && res.code) throw new Error(res.message || 'refused');
      const err = res && Array.isArray(res.results) && res.results.find(r => r.error);
      if (err) throw new Error(err.error);
      this.updateToast(tid, label + ' ✓', { kind: 'success' });
      this.setState({ sigDraft: '' });
      if (id) this.loadSignalNotes(id);
      this.loadSignals(true);
    }).catch(e => this.updateToast(tid, (e && e.message && e.message !== 'refused') ? e.message : 'Could not save', { kind: 'error' }));
  },

  sigVerdict(f, verdict) {
    const reason = (this.state.sigDraft || '').trim();
    if (reason.length < 25) { this.toast('Write the reason for "' + verdict + '" in the note box first (25+ characters).', { kind: 'info' }); return; }
    this.sigWrite('/malware-findings/' + f.malware_finding_id + '/verdict', { verdict, reason }, 'Verdict ' + verdict, f.malware_finding_id);
  },
  sigAck(f) {
    const note = (this.state.sigDraft || '').trim() || 'Picked up in the Signals screen.';
    this.sigWrite('/malware-findings/acknowledge', { ids: [f.malware_finding_id], note }, 'Marked picked up', f.malware_finding_id);
  },
  sigResolve(f) {
    const resolution = (this.state.sigDraft || '').trim();
    if (resolution.length < 25) { this.toast('Say what was cleaned up in the note box first (25+ characters).', { kind: 'info' }); return; }
    this.sigWrite('/malware-findings/resolve', { ids: [f.malware_finding_id], resolution }, 'Resolved', f.malware_finding_id);
  },
  sigNote(f) {
    const body = (this.state.sigDraft || '').trim();
    if (!body) return;
    this.sigWrite('/malware-findings/' + f.malware_finding_id + '/notes', { body }, 'Note added', f.malware_finding_id);
  },
  sigToggleEmails() {
    const next = !(this._sig && this._sig.emails_enabled);
    const go = () => this.api('/malware-findings/settings', { method: 'POST', body: { emails_enabled: next } })
      .then(res => { if (this._sig) this._sig.emails_enabled = !!(res && res.emails_enabled); this.setState({}); this.toast('Security alert emails ' + (next ? 'on' : 'off'), { kind: 'success' }); })
      .catch(() => this.toast('Could not change the setting', { kind: 'error' }));
    if (!next) return go();
    this.uiConfirm('Turn security alert emails back on? Malware, injection, checksum, role and session alerts will email the admin address again, alongside the attention list.', { label: 'Turn on' })
      .then(ok => { if (ok) go(); });
  },

  realSignalVals(s) {
    const active = s.route === 'security' && s.secTab === 'signals';
    if (active) setTimeout(() => this.loadSignals(), 0);
    const data = this._sig;
    const q = this.sigQuery(s);
    const loading = !data || this._sigKey !== JSON.stringify(q);
    const counts = (data && data.counts) || {};
    const sigViews = this.SIG_VIEWS.map(([id, label]) => ({ label, n: counts[id] != null ? Number(counts[id]).toLocaleString() : '',
      fg: q.view === id ? 'var(--ink)' : 'var(--ink-dim)', bg: q.view === id ? 'var(--panel-2)' : 'transparent',
      go: () => this.setState({ sigView: id, sigPage: 1, sigOpen: 0 }) }));
    const chip = (id, label) => ({ label, fg: q.source === id ? 'var(--brand-ink)' : 'var(--ink-dim)', bg: q.source === id ? 'var(--brand-soft)' : 'var(--paper)',
      bd: q.source === id ? 'var(--brand)' : 'var(--rule)', go: () => this.setState({ sigSource: id, sigPage: 1, sigOpen: 0 }) });
    const sigSources = [chip('', 'All sources'), ...((counts.sources || []).map(src => chip(src, this.SIG_SOURCE_LABEL[src] || src)))];
    const notesMap = this._sigNotes || {};
    const who = a => String(a || '').replace(/^(review|user):/, '').replace(/:/, ' · ') || 'system';
    const sigRows = ((data && data.items) || []).map(f => {
      const id = Number(f.malware_finding_id);
      const sev = String(f.severity || 'low').toLowerCase();
      const [sevBg, sevFg] = this.SEV_STYLE[sev] || this.SEV_STYLE.low;
      const v = f.verdict || '';
      const [vBg, vFg] = this.SIG_VERDICT_STYLE[v] || this.SIG_VERDICT_STYLE[''];
      const open = s.sigOpen === id;
      const notes = (notesMap[id] || []).map(n => ({ who: who(n.author), kind: n.kind, when: (n.created_at || '').slice(0, 16), text: n.body }));
      const meta = ['First seen ' + (f.first_seen || '').slice(0, 10), 'last seen ' + (f.last_seen || '').slice(0, 16), 'seen ' + f.seen_count + '×',
        Number(f.reopened_count) ? 'reopened ' + f.reopened_count + '×' : '', Number(f.priority) ? 'priority' : '', '#' + id].filter(Boolean).join(' · ');
      return {
        sev: sev.charAt(0).toUpperCase() + sev.slice(1), sevBg, sevFg,
        site: f.site_name || ('site ' + f.site_id), env: f.environment || '', path: f.path, source: this.SIG_SOURCE_LABEL[f.source] || f.source,
        verdict: v ? (v === 'needs-human' ? 'Needs human' : v.charAt(0).toUpperCase() + v.slice(1)) : (Number(f.priority) ? 'Priority' : 'Unreviewed'),
        vBg: !v && Number(f.priority) ? 'var(--bad-soft)' : vBg, vFg: !v && Number(f.priority) ? 'var(--bad)' : vFg,
        ackShow: !!f.acknowledged_at && f.status === 'open', ackTitle: 'Picked up by ' + who(f.acknowledged_by) + ' · ' + (f.acknowledged_at || '').slice(0, 16),
        ago: this.relTime(String(f.last_seen || '').replace(' ', 'T') + 'Z'), lastSeen: f.last_seen,
        open,
        toggle: () => { const next = open ? 0 : id; this.setState({ sigOpen: next, sigDraft: '' }); if (next) this.loadSignalNotes(id); },
        sigName: f.signature_name || f.signature_id, sigId: f.signature_id, desc: f.description || '',
        match: f.matched_text || '', matchShow: !!f.matched_text,
        reason: f.verdict_reason || '', reasonShow: !!f.verdict_reason, reviewer: who(f.reviewed_by),
        resolution: f.resolution || '', resShow: f.status === 'resolved' && !!f.resolution, resBy: who(f.resolved_by),
        meta, notes, notesEmpty: !notes.length, notesEmptyText: notesMap[id] ? 'No follow-up yet.' : 'Loading…',
        addNote: () => this.sigNote(f),
        verdictBtns: [['benign', 'Benign', 'var(--ok)'], ['actionable', 'Actionable', 'var(--ink)'], ['residue', 'Residue', 'var(--ink)'], ['compromise', 'Compromise', 'var(--bad)'], ['needs-human', 'Needs human', 'var(--brand-ink)']]
          .filter(([k]) => k !== v).map(([k, label, fg]) => ({ label, fg, go: () => this.sigVerdict(f, k) })),
        openSite: () => this.openSite(String(f.site_id)),
        canAck: f.status === 'open' && !f.acknowledged_at, ack: () => this.sigAck(f),
        canResolve: f.status === 'open' && v !== 'benign', resolve: () => this.sigResolve(f)
      };
    });
    const total = (data && data.total) || 0, page = q.page, pages = Math.max(1, Math.ceil(total / 50));
    const emptyText = data && data.error ? 'Could not load signals.' : ({ attention: 'Nothing needs attention. Every open signal is reviewed and picked up.',
      unreviewed: 'No signals waiting for a review.', open: 'No open signals.', resolved: 'No resolved signals match.', all: 'No signals match.' })[q.view] || 'No signals match.';
    const emailsOn = !!(data && data.emails_enabled);
    return {
      secTabSig: s.secTab === 'signals', sigViews, sigSources, sigRows,
      sigSkelRows: loading && !sigRows.length ? Array.from({ length: 4 }, () => ({})) : [],
      sigEmpty: !loading && !sigRows.length, sigEmptyText: emptyText,
      sigSearchDraft: s.sigSearchDraft != null ? s.sigSearchDraft : (s.sigSearch || ''),
      onSigSearch: e => { const val = e.target.value; this.setState({ sigSearchDraft: val }); clearTimeout(this._sigSearchT);
        this._sigSearchT = setTimeout(() => this.setState({ sigSearch: val.trim(), sigPage: 1, sigOpen: 0 }), 350); },
      sigDraft: s.sigDraft || '', onSigDraft: e => this.setState({ sigDraft: e.target.value }),
      sigEmailsLabel: 'Security emails: ' + (emailsOn ? 'on' : 'off'),
      sigEmailsTitle: emailsOn ? 'Alerts also email the admin address. Click to turn off.' : 'Signals are collected silently; agents work the attention list. Click to turn emails back on.',
      sigToggleEmails: () => this.sigToggleEmails(),
      sigPagerShow: total > 50, sigPagerText: total.toLocaleString() + ' signals · page ' + page + ' of ' + pages,
      sigPrevFg: page > 1 ? 'var(--brand-ink)' : 'var(--rule)', sigNextFg: page < pages ? 'var(--brand-ink)' : 'var(--rule)',
      sigPrev: () => { if (page > 1) this.setState({ sigPage: page - 1, sigOpen: 0 }); },
      sigNext: () => { if (page < pages) this.setState({ sigPage: page + 1, sigOpen: 0 }); }
    };
  }

});
