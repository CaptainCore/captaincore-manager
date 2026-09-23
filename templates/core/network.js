// CaptainCore v3 — Network sites on the site Overview (mixin).
// Reads the current environment's details as the CLI's sync-data leaves
// them: `network_sites` (multisite: {mode,count,sites:[{id,name,url,main,
// registered,last_updated,public,archived,spam,deleted}]}) and `freighter`
// (WP Freighter: {role,tenant_id,main_url,files,domain_mapping,count,
// tenants:[{id,name,domain,url,created_at}]}). The site's FLEET row carries
// the Manager's resolved `network` (tenant_sites → CaptainCore site ids,
// host_site_id), which turns a tenant row into a link to its own site page.
// Both lists are capped at 500 by the collector; the counts stay exact.

Object.assign(Component.prototype, {

  computeNetwork(real, s) {
    const off = { netShow: false, netTenantShow: false, netRows: [], netFilterShow: false };
    const env = real ? this.currentEnv(real, s) : null;
    if (!env) return off;
    const det = env.details || {};
    const ms = det.network_sites && typeof det.network_sites === 'object' ? det.network_sites : null;
    const fr = det.freighter && typeof det.freighter === 'object' ? det.freighter : null;
    const row = this.FLEET.find(x => x.id === String(real.siteId)) || {};
    const net = row.network || {};
    const byId = {}; this.FLEET.forEach(x => { byId[x.id] = x; });
    const envLower = String(env.environment || 'Production').toLowerCase();
    const q = (s.netQ || '').trim().toLowerCase();
    // Subsites can sit untouched for years, so past two months read in
    // months, then years (relTime alone would say "3512d").
    const age = (ts) => {
      const t = typeof ts === 'number' || /^\d+$/.test(String(ts)) ? Number(ts) * 1000 : Date.parse(String(ts).replace(' ', 'T') + 'Z');
      if (!t) return '';
      const d = Math.max(0, Math.floor((Date.now() - t) / 86400000));
      if (d < 1) return 'today';
      if (d < 60) return d + 'd ago';
      if (d < 730) return Math.floor(d / 30) + 'mo ago';
      return Math.floor(d / 365) + 'y ago';
    };
    const act = (label, go, primary) => ({ label, primary: !!primary,
      bg: primary ? 'var(--brand)' : 'var(--paper)', fg: primary ? '#fff' : 'var(--ink)',
      bd: primary ? 'var(--brand)' : 'var(--rule)',
      go: (e) => { if (e) e.stopPropagation(); go(); } });
    const finish = (base, all) => {
      const shown = q ? all.filter(r => (r.name + ' ' + r.sub).toLowerCase().includes(q)) : all;
      return { ...off, ...base, netShow: true, netRows: shown,
        netFilterShow: all.length > 12, netQ: s.netQ || '',
        onNetQ: (e) => this.setState({ netQ: e.target.value }),
        netEmpty: shown.length === 0, netEmptyText: q ? 'No sites match “' + (s.netQ || '').trim() + '”.' : 'No sites reported yet.' };
    };

    // A tenant: point back at the host, whatever else is on the page.
    let tenant = {};
    if (fr && fr.role === 'tenant' || net.type === 'tenant') {
      const hostId = net.host_site_id ? String(net.host_site_id) : '';
      const host = hostId && byId[hostId];
      const hostName = host ? host.name : (fr && fr.main_url ? fr.main_url.replace(/^https?:\/\//, '') : 'its host');
      tenant = { netTenantShow: true,
        netTenantText: 'WP Freighter tenant ' + ((fr && fr.tenant_id) || net.tenant_id || '?') + ' on ' + hostName,
        netTenantSub: fr ? [fr.files ? fr.files + ' files' : '', fr.domain_mapping ? 'domain mapping on' : 'domain mapping off'].filter(Boolean).join(' · ') : '',
        netTenantLink: !!host, netTenantGo: () => { if (host) this.openSite(host.id); } };
    }

    if (ms && Array.isArray(ms.sites) && ms.sites.length) {
      // Main site first, then live subsites, then archived/spam/deleted, so
      // a network of mostly retired blogs still leads with what is running.
      const rank = b => b.main ? 0 : (['archived', 'spam', 'deleted'].some(k => Number(b[k])) ? 2 : 1);
      const all = ms.sites.slice().sort((a, b) => rank(a) - rank(b) || a.id - b.id).map(b => {
        const flags = ['archived', 'spam', 'deleted'].filter(k => Number(b[k]));
        const url = b.url || '';
        const actions = [act('open ↗', () => this.safeOpen(url))];
        // Subsites sign in on their own URL as one of their own admins (or
        // a super admin), which the collector lists per subsite.
        const canLogin = (b.admins && b.admins.length) || (ms.super_admins && ms.super_admins.length);
        if (b.main) actions.push(act('log in', () => this.magicLogin(real.siteId, envLower), true));
        else if (canLogin) actions.push(act('log in', () => this.magicLogin(real.siteId, envLower, null, { blog: b.id, label: b.name || url }), true));
        else actions.push(act('admin ↗', () => this.safeOpen(url.replace(/\/?$/, '/') + 'wp-admin/')));
        return { key: 'b' + b.id, name: b.name || url, sub: url.replace(/^https?:\/\//, ''),
          badges: [...(b.main ? [{ t: 'main', bg: 'var(--brand-soft)', fg: 'var(--brand-ink)' }] : []),
            ...(Number(b.public) === 0 && !flags.length ? [{ t: 'private', bg: 'var(--panel-2)', fg: 'var(--ink-dim)' }] : []),
            ...flags.map(f => ({ t: f, bg: f === 'archived' ? 'var(--panel-2)' : 'var(--bad-soft)', fg: f === 'archived' ? 'var(--ink-dim)' : 'var(--bad)' }))],
          when: age(b.last_updated), whenTitle: b.last_updated ? 'Last updated ' + b.last_updated + ' UTC' : '',
          op: flags.length ? '.55' : '1', actions };
      });
      const count = Number(ms.count) || all.length;
      const live = ms.sites.filter(b => rank(b) < 2).length;
      const meta = [ms.mode, count + ' site' + (count === 1 ? '' : 's'), live < ms.sites.length ? live + ' live' : ''].filter(Boolean).join(' · ')
        + (count > all.length ? ' · first ' + all.length + ' shown' : '');
      return finish({ ...tenant, netTitle: 'Network sites', netMeta: meta }, all);
    }

    if (fr && fr.role === 'host') {
      const linked = {}; (net.tenant_sites || []).forEach(t => { linked[String(t.tenant_id)] = String(t.site_id); });
      const mainUrl = fr.main_url || env.home_url || '';
      const all = [{ key: 'main', name: row.name || mainUrl, sub: mainUrl.replace(/^https?:\/\//, ''),
        badges: [{ t: 'host', bg: 'var(--brand-soft)', fg: 'var(--brand-ink)' }], when: '', whenTitle: '', op: '1',
        actions: [act('open ↗', () => this.safeOpen(mainUrl)), act('log in', () => this.magicLogin(real.siteId, envLower), true)] }];
      (fr.tenants || []).forEach(t => {
        const sid = linked[String(t.id)];
        const site = sid && byId[sid];
        const url = t.url || '';
        const actions = [];
        if (url) actions.push(act('open ↗', () => this.safeOpen(url)));
        if (site) {
          actions.push(act('manage', () => this.openSite(site.id)));
          actions.push(act('log in', () => this.magicLogin(site.id, 'production'), true));
        } else if (url && t.helper && t.admins && t.admins.length) {
          // Not a CaptainCore site: sign in through the host with the
          // tenant's own admins, on its mapped domain.
          actions.push(act('log in', () => this.magicLogin(real.siteId, envLower, null, { tenant: t.id, label: t.name || url }), true));
        }
        all.push({ key: 't' + t.id, name: t.name || t.domain || ('Tenant ' + t.id),
          sub: (url ? url.replace(/^https?:\/\//, '') : (t.domain || 'no own domain · switch from WP Freighter')) + ' · #' + t.id,
          badges: site ? [] : [{ t: 'not in CaptainCore', bg: 'var(--panel-2)', fg: 'var(--ink-dim)' }],
          when: t.created_at ? age(t.created_at) : '', whenTitle: t.created_at ? 'Created ' + new Date(t.created_at * 1000).toLocaleDateString() : '',
          op: site ? '1' : '.7', actions });
      });
      const count = Number(fr.count) || (fr.tenants || []).length;
      const tracked = (net.tenant_sites || []).length;
      const meta = [fr.files ? fr.files + ' files' : '', fr.domain_mapping ? 'domain mapping' : '',
        count + ' tenant' + (count === 1 ? '' : 's'), tracked + ' in CaptainCore'].filter(Boolean).join(' · ');
      return finish({ ...tenant, netTitle: 'Tenant sites', netMeta: meta }, all);
    }

    // Before the host syncs with the network collector: the Manager may
    // still have inferred it from the tenants sharing its SSH endpoint.
    if (net.type === 'host' && (net.tenant_sites || []).length) {
      const all = net.tenant_sites.map(t => { const site = byId[String(t.site_id)];
        return { key: 't' + t.tenant_id, name: site ? site.name : 'Site ' + t.site_id, sub: 'tenant #' + t.tenant_id,
          badges: [], when: '', whenTitle: '', op: '1',
          actions: site ? [act('manage', () => this.openSite(site.id)), act('log in', () => this.magicLogin(site.id, 'production'), true)] : [] }; });
      return finish({ ...tenant, netTitle: 'Tenant sites', netMeta: all.length + ' tracked · full list after the next sync' }, all);
    }

    return { ...off, ...tenant };
  }

});
