// CaptainCore v3 — WP Registry audit coverage on a single site (mixin).
// v1 parity with core.php's showAuditCoverage/showAuditFindings dialogs, but
// as a site tab. Every installed component's content hash is matched against
// findings.wpregistry.io (server-side via RegistryClient):
//   GET /sites/{id}/environments/{envId}/audit-coverage        → {summary, components}
//   GET /sites/{id}/environments/{envId}/audit-coverage/{hash} → per-hash findings
// The per-hash route is the PUBLIC projection — embargoed findings never reach
// the browser, so this tab is safe for customers as well as operators.

Object.assign(Component.prototype, {

  // Severity → chip colors (v1 auditChipColor, mapped onto Minn tokens).
  regTone(status, malware) {
    if (malware) return ['var(--bad-soft)', 'var(--bad)'];
    switch (status) {
      case 'critical': return ['var(--bad-soft)', 'var(--bad)'];
      case 'high':     return ['var(--warn-soft)', 'var(--warn)'];
      case 'medium':   return ['var(--warn-soft)', 'var(--warn)'];
      case 'low':      return ['var(--panel-2)', 'var(--ink)'];
      case 'clean':    return ['var(--ok-soft)', 'var(--ok)'];
      default:         return ['var(--panel-2)', 'var(--ink-dim)'];
    }
  },

  // Severity is a property of the code; this is whether the code RUNS. WP's
  // status is the second axis of site risk — everything but 'inactive' loads
  // (a parent theme of the active child still runs, and must-use plugins and
  // drop-ins cannot be switched off at all).
  //
  // "Not loaded" is NOT "unreachable": a plugin's standalone PHP files stay
  // web-reachable while deactivated, so a missing-ABSPATH direct-request bug is
  // unaffected by activation state. Deactivated rows are therefore demoted in
  // order and weight, never relabelled to a lower severity.
  regLoadState(c) {
    const st = String((c && c.state) || '');
    if (!c || c.kind === 'file') return { loaded: true, pill: '' };
    if (st === 'inactive') return { loaded: false, pill: 'inactive' };
    if (st === 'must-use') return { loaded: true, pill: 'must-use' };
    if (st === 'dropin')   return { loaded: true, pill: 'drop-in' };
    if (st === 'parent')   return { loaded: true, pill: 'parent theme' };
    return { loaded: true, pill: '' };
  },

  loadRegistry() {
    const real = this._detail;
    if (!real) return;
    const env = this.currentEnv(real, this.state);
    if (!env || !env.environment_id) return;
    real.reg = real.reg || {};
    const key = String(env.environment_id);
    if (real.reg[key] !== undefined) return;
    real.reg[key] = null; // loading
    this.api('/sites/' + real.siteId + '/environments/' + env.environment_id + '/audit-coverage')
      .then(res => { if (this._detail !== real) return;
        real.reg[key] = (res && Array.isArray(res.components))
          ? { summary: res.summary || null, components: res.components }
          : { summary: null, components: [], err: (res && res.message) || 'Failed to load audit coverage.' };
        this.setState({ tick: this.state.tick });
      })
      .catch(() => { if (this._detail !== real) return;
        real.reg[key] = { summary: null, components: [], err: 'Failed to load audit coverage.' };
        this.setState({ tick: this.state.tick }); });
  },

  openRegFindings(row) {
    const real = this._detail;
    const env = real ? this.currentEnv(real, this.state) : null;
    if (!real || !env) return;
    // Seed the dialog from the row so the header renders before the fetch lands.
    this.setState({ rgHash: row.hash, rgLoading: true,
      rgDetail: { display_name: row.name, slug: row.slug, version: row.version,
        status: row.status, malware: row.malware, hash: row.hash,
        key_issue: row.key_issue, component_type: row.kind, findings: null } });
    this.api('/sites/' + real.siteId + '/environments/' + env.environment_id + '/audit-coverage/' + row.hash)
      .then(res => { if (this.state.rgHash !== row.hash) return;
        this.setState({ rgDetail: res && res.hash ? res : null, rgLoading: false }); })
      .catch(() => { if (this.state.rgHash === row.hash) this.setState({ rgLoading: false }); });
  },

  computeRegistry(real, s) {
    const env = real ? this.currentEnv(real, s) : null;
    const bundle = (real && real.reg && env) ? real.reg[String(env.environment_id)] : undefined;
    const loading = !!real && (bundle === undefined || bundle === null);
    const comps = bundle && Array.isArray(bundle.components) ? bundle.components : [];
    const sum = bundle ? bundle.summary : null;

    // Group by kind, v1's order; "file" reads as "Loose files".
    const KINDS = [['plugin', 'Plugins'], ['theme', 'Themes'], ['mu-plugin', 'MU-plugins'], ['file', 'Loose files']];
    const mkRow = (c, firstOff) => {
      const [bg, fg] = this.regTone(c.status, c.malware);
      const audited = c.status !== 'unaudited';
      const ls = this.regLoadState(c);
      // A deactivated plugin is pure attack surface with no upside, so the
      // fix is usually delete rather than update — same confirm + job path
      // as the Plugins tab's own Delete.
      const canDrop = !ls.loaded && (c.kind === 'plugin' || c.kind === 'theme');
      const dropKind = c.kind === 'plugin' ? 'plugin' : 'theme';
      return { name: c.name, version: c.version ? 'v' + c.version : '', hash: c.short_hash,
        label: c.malware ? 'malware' : c.status, chipBg: bg, chipFg: fg,
        issue: c.key_issue || '', issueShow: !!c.key_issue,
        countShow: audited && !!c.findings_count,
        count: (c.findings_count || 0) + ' finding' + (c.findings_count === 1 ? '' : 's'),
        rowCursor: audited ? 'pointer' : 'default',
        pill: ls.pill, pillShow: !!ls.pill,
        dim: ls.loaded ? '1' : '.55',
        dividerShow: !!firstOff,
        dividerLabel: 'Deactivated',
        dividerNote: 'WordPress does not load these, so most findings cannot fire. Plugin files can still be reached directly over HTTP.',
        open: () => { if (audited) this.openRegFindings(c); },
        ctx: (e) => this.openCtxMenu(e, [
          ...(audited ? [{ label: 'View findings', act: () => this.openRegFindings(c) },
            { label: 'Open on WP Registry ↗', act: () => window.open('https://wpregistry.io/finding/' + c.hash, '_blank') }] : []),
          { label: 'Copy hash', act: () => this.ctxCopy(c.hash, 'hash') },
          ...(canDrop ? [{ label: 'Delete ' + dropKind + '…', danger: true,
            act: () => this.realDeleteAddon({ slug: c.slug, active: false, mu: false }, real,
              Object.assign({}, this.state, { addonKind: dropKind === 'plugin' ? 'plugins' : 'themes' })) }] : [])
        ]) };
    };
    // Summary-chip filter: '' = all; 'malware' matches the malware flag,
    // status labels match non-malware rows of that status (a malware row only
    // ever shows under its malware chip, mirroring the chip label logic).
    const filt = s.rgFilter || '';
    const matches = c => !filt ? true :
      filt === 'not loaded' ? !this.regLoadState(c).loaded :
      filt === 'malware' ? !!c.malware :
      filt === 'unaudited' ? c.status === 'unaudited' :
      (!c.malware && c.status === filt);
    // Within a group, code that runs sorts above code that doesn't; the server
    // already ordered by severity, so partitioning keeps that order inside each
    // half. Triage order then reads as effective risk, not raw severity.
    const regGroups = KINDS.map(([kind, label]) => {
      const all = comps.filter(c => c.kind === kind && matches(c));
      const on  = all.filter(c => this.regLoadState(c).loaded);
      const off = all.filter(c => !this.regLoadState(c).loaded);
      return { label, count: all.length,
        dimNote: off.length + ' not loaded', dimNoteShow: off.length > 0 && on.length > 0,
        rows: on.map(c => mkRow(c, false)).concat(off.map((c, i) => mkRow(c, i === 0))),
        show: all.length > 0 };
    }).filter(g => g.show);

    const chip = (n, label, tone) => n > 0 ? [{ n: String(n), label, bg: tone[0], fg: tone[1] }] : [];
    const regChips = (sum ? [
      { n: String(sum.total || 0), label: 'total', bg: 'var(--panel-2)', fg: 'var(--ink)' },
      ...chip(sum.malware, 'malware', ['var(--bad-soft)', 'var(--bad)']),
      ...chip(sum.critical, 'critical', ['var(--bad-soft)', 'var(--bad)']),
      ...chip(sum.high, 'high', ['var(--warn-soft)', 'var(--warn)']),
      ...chip(sum.medium, 'medium', ['var(--warn-soft)', 'var(--warn)']),
      ...chip(sum.low, 'low', ['var(--panel-2)', 'var(--ink)']),
      ...chip(sum.clean, 'clean', ['var(--ok-soft)', 'var(--ok)']),
      ...chip(sum.unaudited, 'unaudited', ['var(--panel-2)', 'var(--ink-dim)']),
      ...chip(comps.filter(c => !this.regLoadState(c).loaded).length, 'not loaded', ['var(--panel-2)', 'var(--ink-dim)'])
    ] : []).map(c => ({ ...c,
      // Active chip wears its own text color as a ring; "total" clears.
      bd: filt && filt === c.label ? c.fg : 'transparent',
      go: () => this.setState(st => ({ rgFilter: (c.label === 'total' || st.rgFilter === c.label) ? '' : c.label })) }));

    // Headline: what the chip row above cannot say. Counted off `components`
    // rather than the (possibly cached) summary so the sentence and the rows
    // below it can never disagree.
    const flagged = c => !!c.malware || ['critical', 'high', 'medium', 'low'].indexOf(c.status) >= 0;
    const live = comps.filter(c => this.regLoadState(c).loaded);
    const liveMal  = live.filter(c => c.malware).length;
    const liveCrit = live.filter(c => !c.malware && c.status === 'critical').length;
    const liveHigh = live.filter(c => !c.malware && c.status === 'high').length;
    const offFlag  = comps.filter(c => !this.regLoadState(c).loaded && flagged(c)).length;
    const bits = [];
    if (liveMal)  bits.push(liveMal + ' malware');
    if (liveCrit) bits.push(liveCrit + ' critical');
    if (liveHigh) bits.push(liveHigh + ' high');
    const riskStrong = bits.length ? bits.join(', ') + ' on code that loads'
      : (offFlag ? 'Nothing critical or high on code that loads' : '');
    const riskTail = offFlag ? (riskStrong ? ' · ' : '') + offFlag + ' flagged component' + (offFlag === 1 ? '' : 's')
      + ' deactivated' : '';

    // Findings dialog.
    const d = s.rgDetail;
    let dlg = { rgOpen: false };
    if (s.rgHash && d) {
      const [bg, fg] = this.regTone(d.status, d.malware);
      const findings = Array.isArray(d.findings) ? d.findings : [];
      dlg = {
        rgOpen: true,
        rgTitle: d.display_name || d.slug || '',
        rgVersion: d.version ? 'v' + d.version : '',
        rgStatus: d.malware ? 'malware' : (d.status || ''),
        rgStatusBg: bg, rgStatusFg: fg,
        rgSub: [d.component_type, d.slug].filter(Boolean).join(' · '),
        rgHashFull: d.hash || '',
        rgIssue: d.key_issue || '', rgIssueShow: !!d.key_issue,
        rgIssueBg: (d.malware || d.status === 'critical') ? 'var(--bad-soft)' : 'var(--warn-soft)',
        rgIssueFg: (d.malware || d.status === 'critical') ? 'var(--bad)' : 'var(--warn)',
        rgAudit: d.audit ? 'Audited by ' + (d.audit.auditor || 'unknown') + (d.audit.date ? ' on ' + String(d.audit.date).slice(0, 10) : '') : '',
        rgAuditShow: !!d.audit,
        // PUBLIC registry, keyed by the content hash — findings.wpregistry.io is
        // the private origin and 401s for customers. /finding/<hash> is the
        // worker's public page (embargo-filtered projection, same as this data).
        rgAuditUrl: d.hash ? 'https://wpregistry.io/finding/' + d.hash : '',
        rgAuditLinkShow: !!d.hash,
        rgLoadingDetail: !!s.rgLoading,
        rgCount: findings.length ? 'Findings (' + findings.length + ')' : '',
        rgCountShow: findings.length > 0,
        // Accordion rows: rgOpenIdx tracks the expanded finding.
        rgFindings: findings.map((f, i) => {
          const [fbg, ffg] = this.regTone(f.severity, false);
          const open = s.rgOpenIdx === i;
          return { sev: f.severity || '', sevBg: fbg, sevFg: ffg,
            title: f.title || '(untitled finding)',
            type: (f.vuln_type || '').replace(/_/g, ' '),
            arrow: open ? '▾' : '▸', bodyShow: open,
            desc: f.description || '', descShow: !!f.description,
            loc: f.location_path ? f.location_path + (f.location_lines ? ':' + f.location_lines : '') : '',
            locShow: !!f.location_path,
            cve: f.cve ? f.cve + (f.cvss_score ? ' (CVSS ' + f.cvss_score + ')' : '') : '',
            cveShow: !!f.cve,
            code: f.code_snippet || '', codeShow: !!f.code_snippet,
            rec: f.recommendation || '', recShow: !!f.recommendation,
            toggle: () => this.setState(st => ({ rgOpenIdx: st.rgOpenIdx === i ? -1 : i })) };
        }),
        rgEmpty: !s.rgLoading && findings.length === 0,
        rgClose: () => this.setState({ rgHash: '', rgDetail: null, rgOpenIdx: -1 })
      };
    }

    return {
      regLoading: loading,
      regGroups,
      regChips,
      regChipsShow: regChips.length > 0,
      regRiskShow: !!(riskStrong || riskTail),
      regRiskStrong: riskStrong,
      regRiskFg: (liveMal || liveCrit) ? 'var(--bad)' : liveHigh ? 'var(--warn)' : 'var(--ink)',
      regRiskTail: riskTail,
      regCoverage: sum ? sum.audited + ' / ' + sum.total + ' components audited (' + (sum.coverage_pct || 0) + '%)' : '',
      regCoverageShow: !!sum,
      regBarW: sum ? Math.min(100, sum.coverage_pct || 0) + '%' : '0%',
      regErr: (bundle && bundle.err) || '',
      regErrShow: !!(bundle && bundle.err),
      regEmpty: !loading && !!bundle && comps.length === 0 && !(bundle && bundle.err),
      regFilterEmpty: !loading && !!filt && comps.length > 0 && regGroups.length === 0,
      regFilterEmptyText: 'No components match "' + filt + '" — click the chip again to show everything.',
      ...dlg
    };
  }

});
