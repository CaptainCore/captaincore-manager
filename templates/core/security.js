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

  realSecurityVals(s) {
    if (s.route === 'security' && !this._sec && !this._secLoading) setTimeout(() => this.loadSecurity(), 0);
    const sec = this._sec;
    const loading = this._secLoading && !sec;
    if (!sec) return { threats: [], secLoading: loading, secEmpty: !loading, secEmptyText: loading ? 'Loading security data…' : '',
      secSkelRows: loading ? Array.from({ length: 4 }, () => ({})) : [],
      coreFails: [], plugFails: [], covShowActions: false };
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
      covTiles, covBars, covShowActions: false,
      covNote: cov ? ((cov.without_hashes ? ((cov.without_hashes.plugin || 0) + (cov.without_hashes.theme || 0)) + ' components have no content hash yet.' : '')) : '',
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
    'ssh': ['var(--panel-2)', 'var(--ink-dim)']
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
        this.api('/core-update-runs/' + id + '/results?result=fail')
      ]).then(([run, fails]) => {
        this._coreRunsLoading = false;
        this._coreRuns = {
          runs: list,
          run: (run && !run.code) ? run : pick,
          fails: Array.isArray(fails) ? fails : []
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
        coreTiles: [], coreMeta: '', coreGroups: [], coreRunPickerShow: false, coreRunLabel: '', ddCoreOpen: false, ddToggleCore: () => {}, ddCoreOpts: []
      };
    }
    const run = data.run;
    if (!run) {
      return {
        coreHasRun: false, coreEmpty: true, coreEmptyText: 'No core probe runs yet. Fleet probe results land here after update-core finishes.',
        coreTiles: [], coreMeta: '', coreGroups: [], coreRunPickerShow: false, coreRunLabel: '', ddCoreOpen: false, ddToggleCore: () => {}, ddCoreOpts: []
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
      coreTiles: tiles, coreMeta: meta.trim(), coreGroups: groups,
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
        copyLink: () => { try { navigator.clipboard.writeText(a.report_url || ''); } catch (e) {}
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
  }

});
