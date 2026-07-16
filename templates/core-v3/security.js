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
        getPatch: () => { if (t.patch && t.patch.download_url) window.open(t.patch.download_url, '_blank'); },
        markInv: () => this.trackThreat(t, 'investigating'),
        markRes: () => this.resolveThreat(t)
      };
    });
    const base = p => (p || '').split('/').slice(-2).join('/');
    const coreFails = (sec.core || []).map((c, i) => {
      const d = c.core_checksum_details || {};
      const mod = (d.modified || []).length, missing = (d.missing || []).length, extra = (d.extra || []).length;
      return { id: 'core' + i, site: c.site_name, mod, extra: extra + missing,
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
      return { id: 'plug' + i, site: c.site_name, slug: (c.slugs_affected || []).join(', ') || '—',
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
      covNote: cov ? ((cov.without_hashes ? ((cov.without_hashes.plugin || 0) + (cov.without_hashes.theme || 0)) + ' components have no content hash yet.' : '')) : ''
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
    if (a.report_url) { window.open(a.report_url, '_blank'); return; }
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
        cancel: () => { if (!confirm('Cancel this audit request?')) return;
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
