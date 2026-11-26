// ── Kinsta site links resolver (Settings → Providers) ────────────────────────
// Audits active non-stacked Kinsta sites against live Kinsta accounts and lets
// operators resolve missing, wrong-account, or stale provider_site_ids visually
// (pick from live Kinsta sites, paste a MyKinsta URL/UUID, or fix wrong-account
// rows in one click). Each link pulls updated connection details from Kinsta.
//
//   GET  /kinsta/links              → { checked_at, partial, providers, summary, sites, remote_sites }
//   POST /kinsta/links/fix-accounts → { fixed, sites }
//   POST /sites/{id}/kinsta-link    → { provider_id, provider_name, provider_site_id, remote_site, remote_sync, site, environments }
Object.assign(Component.prototype, {

  KL_PAGE_SIZE: 40,

  openKinstaLinksResolver() {
    this.setState({ route: 'settings', setTab: 'providers', edsOpen: false, klFilter: 'all', klPage: 1 });
    this.loadKinstaLinks(true);
  },

  loadKinstaLinks(force) {
    if (this._klLoading || (this._kl && !force)) return;
    this._klLoading = true;
    this._klErr = '';
    this.setState({});
    this.api('/kinsta/links').then(res => {
      this._klLoading = false;
      if (!res || res.code || !Array.isArray(res.sites)) {
        this._klErr = (res && res.message) || 'Could not check Kinsta links.';
      } else {
        this._kl = res;
        this._klErr = '';
      }
      this.setState({ klPage: 1 });
    }).catch(() => {
      this._klLoading = false;
      this._klErr = 'Could not check Kinsta links.';
      this.setState({});
    });
  },

  // Update the cached audit snapshot + FLEET after a site is linked so the row
  // resolves immediately without re-fetching every Kinsta account.
  klApplyLinked(siteId, res) {
    const f = (this.FLEET || []).find(x => x.id === String(siteId));
    if (f) {
      f.provider = 'Kinsta';
      f.providerSiteId = res.provider_site_id || '';
      f.providerConnId = String(res.provider_id || '');
    }
    if (this._detail && String(this._detail.siteId) === String(siteId)) {
      if (res.site) this._detail.site = res.site;
      if (Array.isArray(res.environments)) this._detail.envs = res.environments;
    }
    const kl = this._kl;
    if (!kl) return;
    const idx = (kl.sites || []).findIndex(x => x.site_id === Number(siteId));
    const prev = idx >= 0 ? kl.sites[idx] : null;
    if (prev) {
      kl.sites.splice(idx, 1);
      if (kl.summary && kl.summary[prev.issue] > 0) kl.summary[prev.issue]--;
      if (kl.summary) kl.summary.valid = (kl.summary.valid || 0) + 1;
    }
    const rid = String(res.provider_site_id || '').toLowerCase();
    const siteName = (res.site && res.site.name) || (prev && prev.name) || '';
    if (rid && Array.isArray(kl.remote_sites)) {
      let wasUnclaimed = false;
      kl.remote_sites.forEach(r => {
        if (r.remote_id === rid) {
          if (!r.linked_site_id) wasUnclaimed = true;
          r.linked_site_id = Number(siteId);
          r.linked_site_name = siteName;
        }
      });
      if (wasUnclaimed && kl.summary && kl.summary.unclaimed_remote > 0) {
        kl.summary.unclaimed_remote--;
      }
      // Drop any suggestion on other rows that pointed at this newly claimed remote site.
      (kl.sites || []).forEach(row => {
        if (row.suggestion && row.suggestion.remote_id === rid) row.suggestion = null;
      });
    }
  },

  klFormatSyncToast(siteName, res) {
    const rs = res && res.remote_sync;
    const prov = (res && res.provider_name) || 'Kinsta';
    if (!rs) return 'Linked ' + siteName + ' (' + prov + ')';
    const n = Array.isArray(rs.changes) ? rs.changes.length : 0;
    if (rs.status === 'error') return 'Linked ' + siteName + ', but pull failed: ' + (rs.message || 'error');
    if (n > 0) return 'Linked ' + siteName + ' · ' + n + ' field(s) pulled from Kinsta';
    return 'Linked ' + siteName + ' (' + prov + ') · already in sync';
  },

  async klLinkSite(row, payload) {
    const siteId = row.site_id;
    const key = String(siteId);
    if (this._klBusy && this._klBusy[key]) return;
    this._klBusy = Object.assign({}, this._klBusy, { [key]: true });
    this.setState({ klPickErr: '' });
    const tid = this.toast('Linking ' + row.name + ' and pulling from Kinsta…', { kind: 'loading' });
    const send = body => this.api('/sites/' + siteId + '/kinsta-link', { method: 'POST', body });
    try {
      let res = await send(Object.assign({ pull: true }, payload));
      if (res && res.code === 'already_linked') {
        this.dismissToast && this.dismissToast(tid);
        const ok = await this.uiConfirm((res.message || 'That Kinsta site is already linked to another site.') + '\n\nLink ' + row.name + ' to it anyway?');
        if (!ok) {
          delete this._klBusy[key];
          this.setState({});
          return;
        }
        const tid2 = this.toast('Linking ' + row.name + ' and pulling from Kinsta…', { kind: 'loading' });
        res = await send(Object.assign({ pull: true, force: true }, payload));
        delete this._klBusy[key];
        if (!res || res.code) {
          const msg = (res && res.message) || 'Could not link site';
          this.updateToast(tid2, msg, { kind: 'error' });
          this.setState({ klPickErr: msg });
          return;
        }
        this.klApplyLinked(siteId, res);
        this.updateToast(tid2, this.klFormatSyncToast(row.name, res), { kind: res.remote_sync && res.remote_sync.status === 'error' ? 'info' : 'success' });
        this.setState({ klPickOpen: false, klPickRow: null, klPickInput: '', klPickQ: '', klPickErr: '' });
        return;
      }
      delete this._klBusy[key];
      if (!res || res.code) {
        const msg = (res && res.message) || 'Could not link site';
        this.updateToast(tid, msg, { kind: 'error' });
        this.setState({ klPickErr: msg });
        return;
      }
      this.klApplyLinked(siteId, res);
      this.updateToast(tid, this.klFormatSyncToast(row.name, res), { kind: res.remote_sync && res.remote_sync.status === 'error' ? 'info' : 'success' });
      this.setState({ klPickOpen: false, klPickRow: null, klPickInput: '', klPickQ: '', klPickErr: '' });
    } catch (e) {
      delete this._klBusy[key];
      this.updateToast(tid, 'Could not link site', { kind: 'error' });
      this.setState({ klPickErr: 'Could not reach Kinsta.' });
    }
  },

  async klClearSite(row) {
    if (!(await this.uiConfirm('Clear the stale Kinsta site ID on ' + row.name + '?\n\nYou can link it to the right Kinsta site afterwards or change its provider.'))) return;
    const key = String(row.site_id);
    this._klBusy = Object.assign({}, this._klBusy, { [key]: true });
    this.setState({});
    const tid = this.toast('Clearing stale Kinsta ID…', { kind: 'loading' });
    this.api('/sites/' + row.site_id + '/kinsta-link', { method: 'POST', body: { clear: true } }).then(res => {
      delete this._klBusy[key];
      if (!res || res.code) {
        this.updateToast(tid, (res && res.message) || 'Could not clear ID', { kind: 'error' });
        this.setState({});
        return;
      }
      const f = (this.FLEET || []).find(x => x.id === String(row.site_id));
      if (f) f.providerSiteId = '';
      if (this._kl && this._kl.summary) {
        if (row.issue === 'invalid' && this._kl.summary.invalid > 0) this._kl.summary.invalid--;
        this._kl.summary.missing = (this._kl.summary.missing || 0) + 1;
      }
      row.provider_site_id = '';
      row.issue = 'missing';
      this.updateToast(tid, 'Cleared Kinsta ID on ' + row.name, { kind: 'success' });
      this.setState({});
    }).catch(() => {
      delete this._klBusy[key];
      this.updateToast(tid, 'Could not clear ID', { kind: 'error' });
      this.setState({});
    });
  },

  async klFixAllWrongAccounts() {
    const kl = this._kl;
    const count = (kl && kl.summary && kl.summary.wrong_account) || 0;
    if (!count || this._klFixingAll) return;
    if (!(await this.uiConfirm('Move all ' + count + ' site(s) whose Kinsta ID already exists in another connected Kinsta account to their matching account?'))) return;
    this._klFixingAll = true;
    this.setState({});
    const tid = this.toast('Updating Kinsta accounts for ' + count + ' site(s)…', { kind: 'loading' });
    this.api('/kinsta/links/fix-accounts', { method: 'POST', body: {} }).then(res => {
      this._klFixingAll = false;
      if (!res || res.code) {
        this.updateToast(tid, (res && res.message) || 'Could not update accounts', { kind: 'error' });
        this.setState({});
        return;
      }
      const fixedIds = new Set((res.sites || []).map(x => Number(x.site_id)));
      (res.sites || []).forEach(x => {
        const f = (this.FLEET || []).find(item => item.id === String(x.site_id));
        if (f) f.providerConnId = String(x.provider_id || '');
      });
      if (this._kl) {
        this._kl.sites = (this._kl.sites || []).filter(r => !(r.issue === 'wrong_account' && fixedIds.has(Number(r.site_id))));
        if (this._kl.summary) {
          this._kl.summary.wrong_account = Math.max(0, (this._kl.summary.wrong_account || 0) - fixedIds.size);
          this._kl.summary.valid = (this._kl.summary.valid || 0) + fixedIds.size;
        }
      }
      this.updateToast(tid, 'Moved ' + (res.fixed || 0) + ' site(s) to their matching Kinsta account', { kind: 'success' });
      this.setState({});
    }).catch(() => {
      this._klFixingAll = false;
      this.updateToast(tid, 'Could not update accounts', { kind: 'error' });
      this.setState({});
    });
  },

  klOpenPicker(row) {
    // Seed search with SFTP username if present so matching Kinsta sites surface right away,
    // without auto-selecting anything.
    const seed = row.prod_username || row.site || '';
    this.setState({
      klPickOpen: true,
      klPickRow: row,
      klPickInput: '',
      klPickQ: seed,
      klPickShowLinked: false,
      klPickErr: ''
    });
  },

  klClosePicker() {
    this.setState({ klPickOpen: false, klPickRow: null, klPickErr: '' });
  },

  klSubmitPickerInput() {
    const row = this.state.klPickRow;
    const raw = (this.state.klPickInput || '').trim();
    if (!row || !raw) { this.setState({ klPickErr: 'Paste a MyKinsta URL or site UUID first.' }); return; }
    this.klLinkSite(row, { input: raw });
  },

  kinstaLinksVals(s) {
    const isOp = ((window.CC_BOOT && window.CC_BOOT.dcRole) || 'operator') === 'operator';
    const hasKinstaProv = !!(this._set && Array.isArray(this._set.providers) && this._set.providers.some(p => p.provider === 'kinsta'));
    const kl = this._kl;
    const loading = !!this._klLoading;
    const loaded = !!kl;
    const sum = (kl && kl.summary) || { total: 0, valid: 0, missing: 0, wrong_account: 0, invalid: 0, unchecked: 0, unclaimed_remote: 0 };
    const filter = s.klFilter || 'all';
    const q = (s.klQ || '').trim().toLowerCase();
    const issueTotal = (sum.missing || 0) + (sum.wrong_account || 0) + (sum.invalid || 0);

    const tabs = [
      ['all', 'All issues', issueTotal],
      ['missing', 'Missing ID', sum.missing || 0],
      ['wrong_account', 'Wrong account', sum.wrong_account || 0],
      ['invalid', 'Not found', sum.invalid || 0],
      ['unclaimed', 'Unclaimed at Kinsta', sum.unclaimed_remote || 0]
    ].map(([id, label, count]) => ({
      label: label + ' (' + count + ')',
      fg: filter === id ? 'var(--ink)' : 'var(--ink-dim)',
      bg: filter === id ? 'var(--panel-2)' : 'transparent',
      go: () => this.setState({ klFilter: id, klPage: 1 })
    }));

    const failedProvs = kl && Array.isArray(kl.providers) ? kl.providers.filter(p => !p.ok) : [];
    const partialMsg = failedProvs.length
      ? 'Skipped "Not found" checks because ' + failedProvs.map(p => p.name + (p.error ? ' (' + p.error + ')' : '')).join(', ') + ' could not be reached.'
      : '';

    const isUnclaimedTab = filter === 'unclaimed';
    let list = [];
    if (kl) {
      if (isUnclaimedTab) {
        list = (kl.remote_sites || []).filter(r => !r.linked_site_id);
        if (q) {
          list = list.filter(r =>
            (r.name || '').toLowerCase().includes(q) ||
            (r.slug || '').toLowerCase().includes(q) ||
            (r.provider_name || '').toLowerCase().includes(q) ||
            (r.remote_id || '').toLowerCase().includes(q)
          );
        }
      } else {
        list = (kl.sites || []).filter(r => filter === 'all' || r.issue === filter);
        if (q) {
          list = list.filter(r =>
            (r.name || '').toLowerCase().includes(q) ||
            (r.site || '').toLowerCase().includes(q) ||
            (r.prod_username || '').toLowerCase().includes(q) ||
            (r.prod_address || '').toLowerCase().includes(q) ||
            (r.provider_site_id || '').toLowerCase().includes(q) ||
            (r.provider_name || '').toLowerCase().includes(q) ||
            (r.actual_provider_name || '').toLowerCase().includes(q)
          );
        }
      }
    }

    const pageSize = this.KL_PAGE_SIZE;
    const totalPages = Math.max(1, Math.ceil(list.length / pageSize));
    const page = Math.min(Math.max(1, s.klPage || 1), totalPages);
    const start = (page - 1) * pageSize;
    const slice = list.slice(start, start + pageSize);

    const toneFor = issue => issue === 'missing'
      ? ['Missing ID', 'var(--warn)', 'color-mix(in srgb,var(--warn) 14%,transparent)']
      : issue === 'wrong_account'
        ? ['Wrong account', 'var(--brand-ink)', 'var(--brand-soft)']
        : ['Not found', 'var(--bad)', 'color-mix(in srgb,var(--bad) 14%,transparent)'];

    const rows = isUnclaimedTab ? [] : slice.map(r => {
      const [badge, badgeFg, badgeBg] = toneFor(r.issue);
      const busy = !!(this._klBusy && this._klBusy[String(r.site_id)]);
      const parts = [];
      if (r.site) parts.push(r.site);
      if (r.prod_username && r.prod_username !== r.site) parts.push('user: ' + r.prod_username);
      if (r.prod_address) parts.push(r.prod_address + (r.prod_port ? ':' + r.prod_port : ''));
      if (r.issue === 'wrong_account') parts.push((r.provider_name || 'Unknown') + ' → ' + (r.actual_provider_name || 'Kinsta'));
      else if (r.provider_name) parts.push(r.provider_name);
      if (r.issue === 'invalid' && r.provider_site_id) parts.push('id: ' + r.provider_site_id.slice(0, 8) + '…');
      const sug = r.suggestion;
      return {
        name: r.name || r.site || ('#' + r.site_id),
        open: () => this.openSite(r.site_id),
        badge, badgeFg, badgeBg,
        meta: parts.join(' · '),
        hasConnErr: !!r.connection_error,
        connErr: r.connection_error || '',
        isWrongAcct: r.issue === 'wrong_account',
        wrongRemoteLabel: r.actual_remote ? ((r.actual_remote.name || r.actual_remote.slug) + ' · ' + r.actual_remote.slug) : '',
        moveLabel: busy ? 'Moving…' : ('Move to ' + (r.actual_provider_name || 'account')),
        move: () => this.klLinkSite(r, { provider_site_id: r.provider_site_id, provider_id: r.actual_provider_id }),
        hasSug: !!sug,
        sugLabel: sug ? ('Exact SFTP match: ' + (sug.name || sug.slug) + ' (' + sug.slug + ' · ' + sug.provider_name + ')') : '',
        linkSugLabel: busy ? 'Linking…' : 'Link & pull',
        linkSug: () => sug && this.klLinkSite(r, { provider_site_id: sug.remote_id, provider_id: sug.provider_id }),
        canPick: r.issue !== 'wrong_account',
        pickLabel: busy ? 'Working…' : 'Pick Kinsta site…',
        pick: () => this.klOpenPicker(r),
        canClear: r.issue === 'invalid',
        clear: () => this.klClearSite(r)
      };
    });

    const unclaimedRows = !isUnclaimedTab ? [] : slice.map(r => ({
      name: r.name || r.slug,
      slug: r.slug,
      prov: r.provider_name,
      uuid: r.remote_id,
      status: r.status || '',
      copy: () => {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(r.remote_id).then(() => this.toast('Copied Kinsta site UUID', { kind: 'info' })).catch(() => {});
        }
      }
    }));

    // Picker modal computed values
    const pickRow = s.klPickRow || null;
    const pickOpen = !!s.klPickOpen && !!pickRow;
    const pickBusy = !!(pickRow && this._klBusy && this._klBusy[String(pickRow.site_id)]);
    const pickQ = (s.klPickQ || '').trim().toLowerCase();
    const showLinked = !!s.klPickShowLinked;
    let pickMatches = [];
    if (pickOpen && kl && Array.isArray(kl.remote_sites)) {
      pickMatches = kl.remote_sites.filter(r => {
        if (!showLinked && r.linked_site_id) return false;
        if (!pickQ) return true;
        return (r.name || '').toLowerCase().includes(pickQ) ||
          (r.slug || '').toLowerCase().includes(pickQ) ||
          (r.provider_name || '').toLowerCase().includes(pickQ) ||
          (r.remote_id || '').toLowerCase().includes(pickQ);
      });
    }
    const pickTotal = pickMatches.length;
    const pickOpts = pickMatches.slice(0, 60).map(r => ({
      name: r.name || r.slug,
      meta: [r.slug, r.provider_name, r.remote_id.slice(0, 8) + '…'].filter(Boolean).join(' · '),
      linked: !!r.linked_site_id,
      linkedLabel: r.linked_site_id ? ('Linked to ' + r.linked_site_name) : 'Unclaimed',
      linkedFg: r.linked_site_id ? 'var(--ink-dim)' : 'var(--ok)',
      pick: () => !pickBusy && this.klLinkSite(pickRow, { provider_site_id: r.remote_id, provider_id: r.provider_id })
    }));

    return {
      klShow: isOp && hasKinstaProv,
      klLoading: loading,
      klLoaded: loaded,
      klHasErr: !!this._klErr,
      klErr: this._klErr || '',
      klBtnLabel: loading ? 'Checking…' : loaded ? 'Refresh' : 'Check Kinsta links',
      klRun: () => this.loadKinstaLinks(true),
      klPartial: !!partialMsg,
      klPartialMsg: partialMsg,
      klValidText: loaded ? ('✓ ' + (sum.valid || 0) + ' valid') : '',
      klTabs: tabs,
      klFixAllShow: loaded && (sum.wrong_account || 0) > 0,
      klFixAllLabel: this._klFixingAll ? 'Fixing accounts…' : ('Fix all wrong accounts (' + (sum.wrong_account || 0) + ')'),
      klFixAll: () => this.klFixAllWrongAccounts(),
      klQ: s.klQ || '',
      onKlQ: e => this.setState({ klQ: e.target.value, klPage: 1 }),
      klIsUnclaimed: isUnclaimedTab,
      klIsIssues: !isUnclaimedTab,
      klRows: rows,
      klUnclaimedRows: unclaimedRows,
      klEmpty: loaded && list.length === 0,
      klEmptyText: q
        ? 'No sites match "' + s.klQ + '".'
        : isUnclaimedTab
          ? 'Every live Kinsta site is linked to an active CaptainCore site.'
          : filter === 'all'
            ? 'All ' + (sum.valid || 0) + ' active Kinsta sites are linked to a valid Kinsta site ID.'
            : 'Nothing in this category.',
      klShowPager: loaded && list.length > pageSize,
      klPagerText: 'Showing ' + (start + 1) + '–' + Math.min(start + pageSize, list.length) + ' of ' + list.length,
      klCanPrev: page > 1,
      klCanNext: page < totalPages,
      klPrev: () => page > 1 && this.setState({ klPage: page - 1 }),
      klNext: () => page < totalPages && this.setState({ klPage: page + 1 }),
      // Picker dialog
      klPickOpen: pickOpen,
      klClosePick: () => this.klClosePicker(),
      klPickSiteName: pickRow ? (pickRow.name || pickRow.site) : '',
      klPickSiteMeta: pickRow ? [pickRow.site, pickRow.prod_username ? ('SFTP user: ' + pickRow.prod_username) : '', pickRow.prod_address ? (pickRow.prod_address + (pickRow.prod_port ? ':' + pickRow.prod_port : '')) : ''].filter(Boolean).join(' · ') : '',
      klPickInput: s.klPickInput || '',
      onKlPickInput: e => this.setState({ klPickInput: e.target.value, klPickErr: '' }),
      klPickInputKey: e => { if (e.key === 'Enter') this.klSubmitPickerInput(); },
      klPickSubmit: () => this.klSubmitPickerInput(),
      klPickSubmitLabel: pickBusy ? 'Linking…' : 'Link & pull',
      klPickHasErr: !!s.klPickErr,
      klPickErr: s.klPickErr || '',
      klPickQ: s.klPickQ || '',
      onKlPickQ: e => this.setState({ klPickQ: e.target.value }),
      klPickClearQ: () => this.setState({ klPickQ: '' }),
      klPickHasQ: !!pickQ,
      klPickShowLinked: showLinked,
      klPickLinkedBg: showLinked ? 'var(--brand)' : 'var(--rule)',
      klPickLinkedJust: showLinked ? 'flex-end' : 'flex-start',
      klPickToggleLinked: () => this.setState({ klPickShowLinked: !showLinked }),
      klPickCountText: pickTotal > 60 ? ('Showing first 60 of ' + pickTotal + ' Kinsta sites — refine search to narrow') : (pickTotal + ' Kinsta site(s)'),
      klPickOpts: pickOpts,
      klPickEmpty: pickOpen && pickOpts.length === 0
    };
  }
});
