// CaptainCore v3 — New site (mixin).
// The friendly "New" path of the New site dialog: one web address input that
// names the site, account cards (customers with more than one account), a
// Fresh / Copy starting point, a datacenter picker that pairs region cards
// with a spinning dot globe, a notes box for the team, and a live preview of
// the site being made. createKinstaSite (app.js) still does the actual work.
// Operators can host it on one of our servers instead (Hosted by): the cards
// become our server locations (GET /servers/locations), the Manager puts the
// site on the server with the most room there unless one is picked, and
// createServerSite (POST /servers/sites) starts the `host site create` job.

// Land sampled on an equal-area grid from Natural Earth 1:110m land (public
// domain, via world-atlas). One row per latitude band:
// [lat, cells in the band, start, end, start, end, …] where each start/end
// pair is a run of land cells (end exclusive) counted east from 180°W.
const NS_LAND = 
[[-54.0,132,39,41],[-52.4,137,40,42],[-50.8,142,41,44],[-49.2,147,43,46],[-47.6,152,44,48],[-46.0,156,46,49,150,152],[-44.4,161,47,51,156,157],[-42.8,165,48,53,149,150,161,162],[-41.2,169,50,54,152,154,165,167],[-39.6,173,51,57,170,172],[-38.0,177,52,60,158,159,160,161,174,176],[-36.4,181,54,62,161,166],[-34.8,185,55,63,152,153,164,170,181,182],[-33.2,188,57,66,104,108,154,159,164,173],[-31.6,192,58,69,106,112,158,165,166,177],[-30.0,195,59,70,107,114,160,180],[-28.4,198,60,72,108,117,162,183],[-26.8,201,61,73,109,119,164,186],[-25.2,204,62,75,110,121,127,128,167,189],[-23.6,206,63,77,111,123,128,130,168,190],[-22.0,209,64,81,113,125,130,132,171,191],[-20.4,211,64,82,113,126,131,134,175,193],[-18.8,213,65,83,114,128,133,136,178,193],[-17.2,215,65,84,115,131,134,137,181,191,192,195],[-15.6,217,64,85,116,133,137,138,183,191,194,196],[-14.0,218,63,85,117,134,138,139,187,191,195,196],[-12.4,220,63,87,118,135,190,194,197,198],[-10.8,221,63,88,119,135],[-9.2,222,63,89,119,135,187,189,198,199,202,203],[-7.6,223,62,90,120,136,178,181,197,203],[-6.0,224,62,90,120,136,178,179,198,204,205,206,208,209],[-4.4,224,61,89,119,137,176,178,186,187,188,189,196,202,206,207],[-2.8,225,63,86,119,138,176,179,181,185,187,189,195,201],[-1.2,225,62,84,118,139,175,178,181,186,187,189,194,196],[0.4,225,62,81,118,140,174,177,181,186,187,190,192,193],[2.0,225,63,81,118,141,174,175,177,178,182,186],[3.6,225,64,81,118,142,173,174,176,177,183,186],[5.2,224,64,79,106,112,115,142,171,173,174,176,184,186],[6.8,223,63,75,104,142,161,162,173,174,188,189],[8.4,223,60,62,63,74,103,143,159,160,161,162,172,174,188,190],[10.0,222,58,60,64,73,102,142,158,160,176,177],[11.6,220,57,59,65,66,100,136,156,159,170,171,173,177,184,185,186,187],[13.2,219,55,59,99,135,136,137,155,158,169,170,171,176,184,185],[14.8,218,53,59,98,134,135,139,154,157,168,175,182,183],[16.4,216,49,55,98,131,134,139,152,157,165,173,180,181],[18.0,214,46,55,61,62,64,65,67,68,97,130,132,141,151,157,163,170,179,180],[19.6,212,14,15,44,49,53,54,63,65,96,128,130,140,149,156,161,168,170,171],[21.2,210,44,48,53,54,59,60,95,127,128,139,146,156,159,168,169,170],[22.8,207,43,47,55,58,94,124,126,138,143,170],[24.4,205,41,47,94,123,124,132,133,135,141,170,171,172],[26.0,202,38,39,40,46,55,56,93,120,121,129,132,168],[27.6,199,36,37,38,46,54,55,92,118,119,126,128,166],[29.2,196,35,36,37,46,53,54,92,124,126,164],[30.8,193,34,35,36,53,91,106,107,162],[32.4,190,33,52,90,103,106,107,113,159,164,165],[34.0,187,32,53,90,99,112,156,161,162],[35.6,183,30,53,88,89,91,97,110,152,156,157,159,163],[37.2,179,29,52,85,89,94,95,96,97,100,101,103,115,116,150,152,154,157,160],[38.8,175,27,51,83,88,95,96,98,99,100,111,114,145,148,150,155,156],[40.4,171,26,50,81,86,89,90,93,94,95,97,99,109,111,143,144,147,152,153],[42.0,167,26,51,79,85,89,91,92,96,103,106,108,144,148,149],[43.6,163,25,50,81,85,86,88,89,94,99,103,105,143,146,147],[45.2,159,25,53,79,85,86,93,94,100,102,140],[46.8,154,24,50,76,136],[48.4,149,24,48,50,53,73,132,133,134],[50.0,145,21,46,49,50,73,129,130,131],[51.6,140,20,48,68,70,71,126],[53.2,135,19,37,38,47,64,65,66,68,69,120,126,128],[54.8,130,18,35,36,44,62,65,68,69,72,114,121,124],[56.4,125,7,8,17,32,36,41,60,62,65,66,67,68,70,110,117,119],[58.0,119,7,8,15,29,34,39,63,65,68,106,112,113],[59.6,114,6,8,13,27,32,35,36,37,59,63,66,106,108,109],[61.2,108,4,26,31,33,39,41,56,59,60,101,103,106],[62.8,103,4,25,31,32,37,39,53,57,58,103],[64.4,97,5,28,29,31,34,38,43,44,51,54,55,58,59,96],[66.0,92,24,27,30,32,37,40,42,49,55,57],[67.6,86,1,4,24,26,28,30,35,46,54,55,60,61],[69.2,80,4,9,10,17,18,20,21,22,23,25,29,34,40,44,48,53,54,55,77,78],[70.8,74,4,5,13,16,17,18,19,23,26,32,42,43,48,49,51,70],[72.4,68,10,11,12,13,15,16,17,20,24,29,47,48,49,58,60,62],[74.0,62,10,11,21,27,40,41,46,50],[75.6,56,10,13,19,25,37,38,42,46,49,51],[77.2,50,8,9,13,14,15,22,39,40],[78.8,44,9,10,11,20,23,24,34,35],[80.4,38,9,17,21,22],[82.0,31,8,11,12,13]];

const NS_DC_GROUPS = [['americas', 'Americas'], ['europe', 'Europe'], ['apac', 'Asia Pacific'], ['mea', 'Middle East & Africa']];
const nsDcGroupOf = value => /^(us|ca|sa)-/.test(value) ? 'americas' : /^(eu|uk)-/.test(value) ? 'europe' : /^ap-/.test(value) ? 'apac' : 'mea';

// Our server locations take a value prefix by country, so they group like
// Kinsta's (nsDcGroupOf) and read as datacenters to the cards and the globe.
const NS_AMERICAS = 'US CA MX BR AR CL CO PE UY EC VE BO PY CR PA GT DO PR JM'.split(' ');
const NS_EUROPE = 'GB IE FR DE NL BE LU CH AT IT ES PT DK SE NO FI IS PL CZ SK HU RO BG GR HR SI RS UA LT LV EE MT CY'.split(' ');
const NS_APAC = 'JP KR CN TW HK SG IN ID MY TH VN PH AU NZ'.split(' ');
const nsOurPrefix = cc => NS_AMERICAS.includes(cc) ? 'us' : NS_EUROPE.includes(cc) ? 'eu' : NS_APAC.includes(cc) ? 'ap' : 'me';

// "Ashburn (US East)" → { city: 'Ashburn', country: 'United States', qual: 'East' }.
const nsDcMeta = (() => {
  let names = null;
  try { names = new Intl.DisplayNames(['en'], { type: 'region' }); } catch (e) { names = null; }
  const cache = {};
  return d => {
    if (cache[d.value]) return cache[d.value];
    const m = /^(.*?) \(([A-Z]{2})(?: (.+))?\)$/.exec(d.title) || [null, d.title, '', ''];
    let country = m[2];
    try { if (names && m[2]) country = names.of(m[2]) || m[2]; } catch (e) { country = m[2]; }
    return (cache[d.value] = { city: m[1], cc: m[2], country, qual: m[3] || '' });
  };
})();

// mybakery.com → mybakery · https://www.shop.my-bakery.co.uk/x → shop-my-bakery.
// Kinsta wants 5–32 characters; the dialog shows the count and lets people edit.
const nsNameFromDomain = raw => {
  let d = String(raw || '').trim().toLowerCase().replace(/^[a-z]+:\/\//, '').replace(/[/?#].*$/, '').replace(/^www\./, '');
  const parts = d.split('.').filter(Boolean);
  if (parts.length > 1) {
    parts.pop();
    const sld = ['co', 'com', 'org', 'net', 'gov', 'ac', 'edu'];
    if (parts.length > 1 && sld.includes(parts[parts.length - 1])) parts.pop();
  }
  return parts.join('-').replace(/[^a-z0-9-]+/g, '').replace(/-+/g, '-').replace(/^-|-$/g, '').slice(0, 32);
};

Object.assign(Component.prototype, {

  onNsDomainInput(e) {
    const v = e.target.value;
    const patch = { nsDomain: v };
    if (!this.state.nsNameTouched) patch.nsName = nsNameFromDomain(v);
    this.setState(patch);
  },

  onNsNameInput(e) {
    const v = e.target.value;
    // Clearing the field hands naming back to the web address.
    this.setState(v.trim() ? { nsName: v, nsNameTouched: true } : { nsName: nsNameFromDomain(this.state.nsDomain), nsNameTouched: false });
  },

  // Sites the caller could copy. At Kinsta it is Kinsta's clone, which needs
  // a Kinsta site on the selected connection and stays in its datacenter. On
  // our servers (this._nsSpots is set) any site works: the server restores
  // its latest backup, the same job that adds an environment from a backup.
  nsCloneSources(s) {
    if (this._nsSpots) return this.FLEET.filter(f => f && f.id && f.name && !f.removed);
    const prov = String(s.nsProviderId || NS_KINSTA_PROVIDER);
    return this.FLEET.filter(f => f.provider === 'Kinsta' && f.providerSiteId
      && String(f.providerConnId || String(NS_KINSTA_PROVIDER)) === prov);
  },

  // The account a customer's site is billed to. openNewSite seeds it, but the
  // dialog can open before accounts load, so fall back to the first account.
  nsAccountId(s, isOp) {
    if (isOp) return s.nsBillingId || '';
    const ok = this.ACCOUNTS.find(a => a.id === String(s.nsBillingId));
    return ok ? ok.id : ((this.ACCOUNTS[0] || {}).id || '');
  },

  nsPickDc(d) {
    if (d.loc) { this.setState({ nsLoc: d.title, nsDcGroup: nsDcGroupOf(d.value), nsServerId: '' }); return; }
    this.setState({ nsDc: d.title, nsDcGroup: nsDcGroupOf(d.value) });
  },

  // Operators: where a site can go on our servers.
  loadNsLocations() {
    if (this._nsLocsLoading) return;
    this._nsLocsLoading = true;
    this.api('/servers/locations').then(res => {
      this._nsLocsLoading = false;
      this._nsLocs = res && Array.isArray(res.locations) ? res : { locations: [], zone: '' };
      this.setState({});
    }).catch(() => { this._nsLocsLoading = false; this._nsLocs = { locations: [], zone: '' }; });
  },

  // Our locations shaped like NS_DATACENTERS rows (value, title, lat, lng).
  nsOurSpots() {
    return ((this._nsLocs && this._nsLocs.locations) || []).map(l => ({
      value: nsOurPrefix(l.country) + '-srv-' + l.key,
      title: l.city + (l.country ? ' (' + l.country + ')' : ''),
      lat: l.lat, lng: l.lng, loc: l
    }));
  },

  createServerSite() {
    const st = this.state;
    const name = (st.nsName || '').trim();
    const domain = (st.nsDomain || '').trim().toLowerCase().replace(/^[a-z]+:\/\//, '').replace(/[/?#].*$/, '');
    const spots = this.nsOurSpots();
    const spot = spots.find(d => d.title === st.nsLoc) || spots[0];
    // Copy an existing site: restored on the server from its latest backup.
    const copying = st.nsOurStart === 'clone';
    const copy = copying && !String(st.nsClone).startsWith('None') ? this.nsCloneSources(st).find(f => f.name === st.nsClone) : null;
    const errors = [];
    if (!name) errors.push('Enter a web address or a name for the site');
    else if (name.replace(/[^A-Za-z0-9]/g, '').length < 3) errors.push('Site names need at least 3 letters or digits');
    if (!spot) errors.push('Pick a location');
    if (copying && !copy) errors.push('Choose the site to copy, or start from a fresh WordPress install');
    if ((st.nsNotes || '').length > 2000) errors.push('Notes can be at most 2,000 characters');
    if (errors.length) { this.setState({ nsErrors: errors }); return; }
    this.setState({ nsBusy: true, nsErrors: [] });
    this.api('/servers/sites', { method: 'POST', body: {
      location: spot.loc.key, server_id: Number(st.nsServerId) || 0, name, domain,
      start: st.nsOurStart === 'plain' ? 'plain' : 'wordpress', copy_site_id: copy ? Number(copy.id) : 0,
      account_id: Number(st.nsBillingId) || 0, customer_id: Number(st.nsCustomerId) || 0,
      shared_with: (st.nsShared || []).map(Number), notes: (st.nsNotes || '').trim()
    } }).then(res => {
      if (!res || res.code || !res.site_id) { this.setState({ nsBusy: false, nsErrors: [(res && res.message) || 'Could not create the site'] }); return; }
      this.toast((domain || name) + ' is on its way to ' + res.server + (copy ? ', copied from the latest backup of ' + copy.name + '.' : '. Its container builds in about a minute.'), { kind: 'success', timeout: 6000 });
      this.setState({ nsOpen: false, nsBusy: false, nsName: '', nsDomain: '', nsNotes: '', nsNameTouched: false,
        nsShared: [], nsCustomerId: '', nsBillingId: '', nsServerId: '', nsClone: 'None (fresh install)', nsOurStart: 'wordpress' });
      if (this.reloadAccounts) this.reloadAccounts();
      this._nsLocs = null; // site counts changed
      this.startJob({ label: 'create', target: name + ' on ' + res.server, command: 'host site create', siteId: res.site_id, environment: 'Production',
        expand: true, dispatch: () => Promise.resolve(res), onFinish: () => { if (this.hydrate) this.hydrate(); } });
    }).catch(e => this.setState({ nsBusy: false, nsErrors: [(e && e.message) || 'Request failed. Try again.'] }));
  },

  nsVals(s, isOp) {
    const brand = (window.CC_BOOT && window.CC_BOOT.name) || 'Anchor Hosting';
    const name = (s.nsName || '').trim();
    const domain = (s.nsDomain || '').trim();
    const len = name.length;
    const nameBad = len > 32 || (len > 0 && len < 5);
    const slug = name.toLowerCase().replace(/[^a-z0-9]/g, '');
    // Hosted by: Kinsta's datacenters, or our server locations (operators).
    if (isOp && s.nsOpen && !this._nsLocs) this.loadNsLocations();
    const ourSpots = isOp ? this.nsOurSpots() : [];
    const ours = isOp && s.nsHost === 'ours' && ourSpots.length > 0;
    const spots = ours ? ourSpots : NS_DATACENTERS;
    this._nsSpots = ours ? spots : null;
    const dc = spots.find(d => d.title === (ours ? s.nsLoc : s.nsDc)) || spots[0];
    this._nsSpotSel = dc.title;
    const loc = ours ? dc.loc : null;
    const srvAll = loc ? loc.servers : [];
    const srvPicked = srvAll.find(v => String(v.server_id) === String(s.nsServerId) && v.ok);
    const srvBest = srvAll.find(v => v.server_id === (loc && loc.best));
    const srvUse = srvPicked || srvBest;
    const room = v => (v.free === null ? 'no site limit' : v.free + ' of ' + v.max_sites + ' site slots free') + ', disk ' + Math.round(v.disk) + '% used';
    const meta = nsDcMeta(dc);
    const group = s.nsDcGroup && spots.some(d => nsDcGroupOf(d.value) === s.nsDcGroup) ? s.nsDcGroup : nsDcGroupOf(dc.value);
    const ranked = s.dcpPlace ? (this._dcpRanked || []) : [];
    const miles = {};
    ranked.forEach((d, i) => { miles[d.title] = { mi: d.miles, nearest: i === 0 }; });
    const sources = this.nsCloneSources(s);
    const isClone = (ours ? s.nsOurStart === 'clone' : s.nsStart === 'clone') && sources.length > 0;
    const cloneSrc = isClone && !String(s.nsClone).startsWith('None') ? s.nsClone : '';
    // Kinsta's clone stays in the original's datacenter; a copy on our
    // servers is restored from the source's latest backup where you pick.
    const sameDc = !ours && isClone;
    const cloneNote = !isClone ? ''
      : sameDc ? 'The copy is made in the same datacenter as the original.'
      : 'Copied from the latest backup of ' + (cloneSrc || 'the site') + ', onto the server you pick.';
    const on = sel => ({
      bd: sel ? 'var(--brand)' : 'var(--rule)',
      bg: sel ? 'var(--brand-soft)' : 'var(--paper)',
      ring: sel ? '0 0 0 3px var(--ring)' : 'none',
      mark: sel ? '✓' : ''
    });
    const accId = this.nsAccountId(s, isOp);
    const acc = this.ACCOUNTS.find(a => a.id === String(accId));
    const custAcc = this.ACCOUNTS.find(a => a.id === String(s.nsCustomerId));
    const accLabel = isOp
      ? (custAcc ? custAcc.name : 'New account for ' + (domain || name || 'the site'))
      : (acc ? acc.name : 'Pick an account');
    const initials = n => String(n || '?').replace(/[^A-Za-z0-9 ]/g, ' ').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase() || '?';
    if (!this._nsGlobeRefFn) this._nsGlobeRefFn = el => this.nsGlobeAttach(el);
    let hint = '', hintColor = 'var(--ink-dim)';
    if (len > 32) { hint = 'Site names can be at most 32 characters.'; hintColor = 'var(--bad)'; }
    else if (len > 0 && len < 5) { hint = 'Site names need at least 5 characters. Add a word or two.'; hintColor = 'var(--bad)'; }
    else if (len) hint = s.nsStart === 'empty' && s.nsHost !== 'ours' ? 'Shown in your dashboard.' : 'Shown in your dashboard and used as the WordPress site title.';
    return {
      nsDialogW: s.nsPath === 'kinsta' ? 900 : 620,
      nsShowTabs: isOp,
      nsTitle: isOp ? 'New site' : 'Create a new site',
      nsLede: 'A fresh WordPress site on ' + brand + ', usually ready in a few minutes.',
      nsLedeShow: s.nsPath === 'kinsta',
      onNsDomainInput: e => this.onNsDomainInput(e),
      onNsNameInput: e => this.onNsNameInput(e),
      nsNameEdited: !!s.nsNameTouched,
      nsNameHint: hint, nsNameHintColor: hintColor, nsNameHintShow: !!hint,
      nsNameBd: nameBad ? 'var(--bad)' : 'var(--rule)',
      nsTempUrl: ours ? (slug || 'yoursite').slice(0, 30) + '-xxxx.' + ((this._nsLocs && this._nsLocs.zone) || 'temporary domain') : (slug || 'yoursite') + '.kinsta.cloud',
      nsHostShow: isOp && ourSpots.length > 0,
      nsHostCards: [
        { id: 'kinsta', title: 'Kinsta', sub: 'Managed by Kinsta in ' + NS_DATACENTERS.length + ' datacenters.' },
        { id: 'ours', title: 'Our servers', sub: ourSpots.length + ' location' + (ourSpots.length === 1 ? '' : 's') + ', on the server with the most room.' }
      ].map(c => ({ ...c, ...on((c.id === 'ours') === ours),
        // The two hosts copy from different lists, so a picked source resets.
        pick: () => this.setState({ nsHost: c.id, nsDcGroup: '', dcpPlace: '', nsServerId: '', nsClone: 'None (fresh install)' }) })),
      nsSrvShow: ours && srvAll.length > 1,
      nsSrvChips: [{ id: '', label: 'Most room' + (srvBest ? ' · ' + srvBest.name : ''), ok: !!srvBest, title: srvBest ? room(srvBest) : 'No server here can take a site' }]
        .concat(srvAll.map(v => ({ id: String(v.server_id), label: v.name, ok: v.ok, title: v.ok ? room(v) : v.why })))
        .map(c => { const sel = c.id === String(srvPicked ? srvPicked.server_id : ''); return { label: c.label, title: c.title,
          bd: sel ? 'var(--brand)' : 'var(--rule)', bg: sel ? 'var(--brand-soft)' : 'var(--paper)', fg: c.ok ? 'var(--ink)' : 'var(--ink-dim)',
          cursor: c.ok ? 'pointer' : 'not-allowed', op: c.ok ? '1' : '.55', pick: () => { if (c.ok) this.setState({ nsServerId: c.id }); } }; }),
      nsSrvNote: srvUse ? srvUse.name + ': ' + room(srvUse) + '.' : 'No server here can take a new site right now.',
      nsSumServerDisp: ours ? 'block' : 'none',
      nsSumServer: srvUse ? srvUse.name + (srvPicked ? '' : ' (most room)') : 'None available',
      ...(ours ? { nsProvShow: false, nsCtaDim: s.nsBusy || !srvUse ? '.5' : '1' } : {}),
      nsTempUrlDim: slug ? 'var(--ink)' : 'var(--ink-dim)',
      nsPreviewTitle: name || 'Your new site',
      nsPreviewTitleDim: name ? 'var(--ink)' : 'var(--ink-dim)',
      nsPreviewNote: domain && domain.includes('.')
        ? 'Your site starts on this temporary address so you can build in private. When you are ready to launch, we help you point ' + domain + ' at it.'
        : 'Your site starts on this temporary address so you can build in private. Add your own domain whenever you are ready.',
      // Account cards: customers with more than one account pick where the site lives.
      nsAccShow: !isOp && this.ACCOUNTS.length > 1,
      nsAccCards: (!isOp ? this.ACCOUNTS : []).map(a => ({
        name: a.name, initials: initials(a.name),
        sites: (Number(a.sites) || 0) + ' site' + (Number(a.sites) === 1 ? '' : 's'),
        ...on(String(accId) === a.id),
        pick: () => this.setState({ nsBillingId: a.id })
      })),
      // Starting point: a fresh install, an empty site (Kinsta's plain site,
      // no WordPress, for a migration) or a copy of a site they already have.
      nsStartShow: true,
      nsStartCards: ours ? [
        { id: 'wordpress', title: 'Fresh WordPress', sub: 'A clean install, ready to build on.', icon: 'M12 3v18M3 12h18' },
        { id: 'plain', title: 'Plain site', sub: 'HTML files only: no WordPress, no database.', icon: 'M4 4h16v16H4zM8 9h8M8 13h5' },
        ...(sources.length ? [{ id: 'clone', title: 'Copy an existing site', sub: 'Start from one of your sites, content and all.', icon: 'M8 8h11v11H8zM5 16V5h11' }] : [])
      ].map(c => ({ ...c, ...on(c.id === (isClone ? 'clone' : s.nsOurStart === 'plain' ? 'plain' : 'wordpress')), pick: () => this.setState({ nsOurStart: c.id }) })) : [
        { id: 'fresh', title: 'Fresh WordPress', sub: 'A clean install, ready to build on.', icon: 'M12 3v18M3 12h18' },
        { id: 'empty', title: 'Empty site', sub: 'No WordPress. For moving a site in or building it yourself.', icon: 'M4 4h16v16H4z' },
        ...(sources.length ? [{ id: 'clone', title: 'Copy an existing site', sub: 'Start from one of your sites, content and all.', icon: 'M8 8h11v11H8zM5 16V5h11' }] : [])
      ].map(c => ({ ...c, ...on(c.id === (isClone ? 'clone' : s.nsStart === 'empty' ? 'empty' : 'fresh')),
        pick: () => this.setState(c.id === 'clone' ? { nsStart: 'clone' } : { nsStart: c.id, nsClone: 'None (fresh install)' }) })),
      nsIsClone: isClone,
      nsCloneLabel: cloneSrc || 'Choose a site to copy…',
      nsCloneLabelDim: cloneSrc ? 'var(--ink)' : 'var(--ink-dim)',
      nsCloneNote: cloneNote,
      nsShowWhere: !sameDc,
      nsDcTabs: NS_DC_GROUPS.filter(([id]) => spots.some(d => nsDcGroupOf(d.value) === id)).map(([id, label]) => {
        const sel = id === group, hasPick = nsDcGroupOf(dc.value) === id;
        return { label, dot: hasPick ? 'inline-block' : 'none',
          bg: sel ? 'var(--ink)' : 'transparent', fg: sel ? 'var(--paper)' : 'var(--ink-dim)',
          go: () => this.setState({ nsDcGroup: id }) };
      }),
      nsDcCards: spots.filter(d => nsDcGroupOf(d.value) === group).map(d => {
        const m = nsDcMeta(d), r = miles[d.title];
        const okSrv = d.loc ? d.loc.servers.filter(v => v.ok) : [];
        const slots = okSrv.some(v => v.free === null) ? 'room' : okSrv.reduce((n, v) => n + v.free, 0) + ' free';
        return { city: m.city, sub: d.loc
            ? m.country + ' · ' + (okSrv.length ? slots : 'Full')
            : m.country + (m.qual ? ' · ' + m.qual : ''),
          dist: r ? r.mi.toLocaleString() + ' mi' + (r.nearest ? ' · closest' : '') : '',
          distShow: !!r, distFg: r && r.nearest ? 'var(--ok)' : 'var(--ink-dim)',
          ...on(d.title === dc.title), pick: () => this.nsPickDc(d) };
      }),
      nsDcCity: meta.city, nsDcCountry: meta.country + (meta.qual ? ' · ' + meta.qual : ''),
      nsGlobeRef: this._nsGlobeRefFn,
      nsWhereNote: s.dcpPlace && ranked.length
        ? 'Closest to ' + s.dcpPlace + '. Pick another any time.'
        : 'Pick the region closest to most of your visitors. Not sure? Search a city below.',
      nsSumAcc: accLabel,
      nsSumWhere: sameDc ? 'Same as the copied site' : meta.city + ', ' + meta.country,
      nsSumStart: isClone ? (cloneSrc ? 'Copy of ' + cloneSrc + (ours ? ' (latest backup)' : '') : 'Copy of…')
        : ours ? (s.nsOurStart === 'plain' ? 'Plain site' : 'Fresh WordPress') : s.nsStart === 'empty' ? 'Empty site' : 'Fresh WordPress',
      nsNotesCount: (s.nsNotes || '').length ? (s.nsNotes || '').length + ' / 2000' : '',
      nsCtaLabel: s.nsBusy ? 'Creating…' : 'Create site'
    };
  },

  // ── Dot globe ────────────────────────────────────────────────────
  // Canvas, orthographic projection, land as dots. Drag to spin, click a
  // datacenter to pick it; picking from the cards turns the globe to it.
  // State lives on this._nsGlobe so a re-mounted canvas carries on.
  nsGlobeAttach(el) {
    if (!el) return;
    const RAD = Math.PI / 180;
    let g = this._nsGlobe;
    if (!g) {
      const lat = [], lon = [];
      NS_LAND.forEach(row => {
        const n = row[1];
        for (let i = 2; i < row.length; i += 2)
          for (let k = row[i]; k < row[i + 1]; k++) { lat.push(row[0]); lon.push(-180 + (k + 0.5) * 360 / n); }
      });
      const N = lat.length, P = new Float32Array(N * 4);
      for (let i = 0; i < N; i++) {
        P[i * 4] = Math.sin(lat[i] * RAD); P[i * 4 + 1] = Math.cos(lat[i] * RAD);
        P[i * 4 + 2] = Math.sin(lon[i] * RAD); P[i * 4 + 3] = Math.cos(lon[i] * RAD);
      }
      g = this._nsGlobe = { P, N, lon: -77, lat: 25, drag: null, target: null, hover: null, lastDc: null, raf: 0, frame: 0, colors: null, misses: 0 };
    }
    if (g.el === el) return;
    g.el = el;
    g.lastDc = null; // aim at the selection again (with the entrance spin)
    g.intro = true;
    const pick = e => {
      const r = el.getBoundingClientRect();
      return { x: e.clientX - r.left, y: e.clientY - r.top };
    };
    el.addEventListener('pointerdown', e => {
      const p = pick(e);
      g.drag = { x: p.x, y: p.y, lon: g.lon, lat: g.lat, moved: false };
      try { el.setPointerCapture(e.pointerId); } catch (err) {}
    });
    el.addEventListener('pointermove', e => {
      const p = pick(e);
      if (g.drag) {
        const dx = p.x - g.drag.x, dy = p.y - g.drag.y;
        if (Math.abs(dx) + Math.abs(dy) > 3) g.drag.moved = true;
        if (g.drag.moved) {
          const k = 180 / (Math.PI * (g.R || 120));
          g.lon = g.drag.lon - dx * k;
          g.lat = Math.max(-60, Math.min(70, g.drag.lat + dy * k));
          g.target = null;
        }
      }
      g.mouse = p;
    });
    const up = e => {
      if (g.drag && !g.drag.moved && g.hover) this.nsPickDc(g.hover);
      g.drag = null;
    };
    el.addEventListener('pointerup', up);
    el.addEventListener('pointercancel', () => { g.drag = null; });
    el.addEventListener('pointerleave', () => { if (!g.drag) g.mouse = null; });
    if (!g.raf) {
      const tick = t => {
        if (!g.el || !g.el.isConnected) {
          // A fresh canvas can arrive a frame before it is attached; give
          // it a moment, then stop until the dialog opens again.
          if (++g.misses > 60) { g.raf = 0; g.el = null; return; }
        } else { g.misses = 0; this.nsGlobeDraw(g, t); }
        g.raf = requestAnimationFrame(tick);
      };
      g.raf = requestAnimationFrame(tick);
    }
  },

  nsGlobeDraw(g, t) {
    const el = g.el, RAD = Math.PI / 180;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const W = el.clientWidth || 260, H = el.clientHeight || 260;
    if (el.width !== Math.round(W * dpr) || el.height !== Math.round(H * dpr)) {
      el.width = Math.round(W * dpr); el.height = Math.round(H * dpr);
    }
    if (!g.colors || g.frame++ % 45 === 0) {
      const cs = getComputedStyle(el);
      const v = (n, f) => (cs.getPropertyValue(n) || '').trim() || f;
      g.colors = { brand: v('--brand', '#3b82c4'), ink: v('--ink', '#1a1a1f'), dim: v('--ink-dim', '#5e5e69'),
        rule: v('--rule', '#e7e7ea'), paper: v('--paper', '#fff'), deep: v('--panel-2', '#eeeef1'),
        font: cs.fontFamily || 'sans-serif', dark: document.documentElement.getAttribute('data-theme') === 'dark' };
    }
    const C = g.colors;
    const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    // Aim at the selected datacenter whenever the selection changes.
    const st = this.state;
    // Kinsta's datacenters, or our server locations (nsVals sets both).
    const spotList = this._nsSpots || NS_DATACENTERS;
    const dc = spotList.find(d => d.title === (this._nsSpotSel || st.nsDc)) || spotList[0];
    if (g.lastDc !== dc.title) {
      g.lastDc = dc.title;
      g.target = { lon: dc.lng, lat: Math.max(-35, Math.min(45, dc.lat * 0.8)) };
      if (g.intro) { g.intro = false; if (!reduced) g.lon = dc.lng - 110; }
    }
    if (g.target && !g.drag) {
      let dl = ((g.target.lon - g.lon + 540) % 360) - 180;
      const k = reduced ? 1 : 0.085;
      g.lon += dl * k; g.lat += (g.target.lat - g.lat) * k;
      if (Math.abs(dl) < 0.05 && Math.abs(g.target.lat - g.lat) < 0.05) g.target = null;
    }
    const ctx = el.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, W, H);
    const R = g.R = Math.min(W, H) / 2 - 18, cx = W / 2, cy = H / 2;
    const sl0 = Math.sin(g.lat * RAD), cl0 = Math.cos(g.lat * RAD);
    const sL0 = Math.sin(g.lon * RAD), cL0 = Math.cos(g.lon * RAD);
    // Sphere: soft glow, then a gently shaded disc.
    const glow = ctx.createRadialGradient(cx, cy, R * 0.9, cx, cy, R * 1.12);
    glow.addColorStop(0, C.brand); glow.addColorStop(1, 'transparent');
    ctx.globalAlpha = C.dark ? 0.22 : 0.12; ctx.fillStyle = glow;
    ctx.beginPath(); ctx.arc(cx, cy, R * 1.12, 0, Math.PI * 2); ctx.fill();
    const disc = ctx.createRadialGradient(cx - R * 0.35, cy - R * 0.4, R * 0.1, cx, cy, R);
    disc.addColorStop(0, C.paper); disc.addColorStop(1, C.deep);
    ctx.globalAlpha = 1; ctx.fillStyle = disc;
    ctx.beginPath(); ctx.arc(cx, cy, R, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = C.rule; ctx.lineWidth = 1; ctx.stroke();
    // Land dots, smaller and fainter towards the rim.
    const P = g.P, N = g.N, ds = R / 105;
    ctx.fillStyle = C.dim;
    for (let i = 0; i < N; i++) {
      const sl = P[i * 4], cl = P[i * 4 + 1], sL = P[i * 4 + 2], cL = P[i * 4 + 3];
      const cd = cL * cL0 + sL * sL0;               // cos(lon - lon0)
      const z = sl0 * sl + cl0 * cl * cd;
      if (z <= 0.02) continue;
      const sd = sL * cL0 - cL * sL0;               // sin(lon - lon0)
      const x = cx + R * cl * sd, y = cy - R * (cl0 * sl - sl0 * cl * cd);
      const s = ds * (0.55 + 0.75 * z);
      ctx.globalAlpha = (C.dark ? 0.28 : 0.22) + 0.5 * z;
      ctx.fillRect(x - s / 2, y - s / 2, s, s);
    }
    const proj = (lat, lon) => {
      const sl = Math.sin(lat * RAD), cl = Math.cos(lat * RAD);
      const d = (lon - g.lon) * RAD;
      const z = sl0 * sl + cl0 * cl * Math.cos(d);
      return { x: cx + R * cl * Math.sin(d), y: cy - R * (cl0 * sl - sl0 * cl * Math.cos(d)), z };
    };
    // Great-circle arc from the searched place to the picked datacenter.
    const place = st.dcpPlace && this._dcpPoint;
    if (place) {
      const a = [Math.cos(place.lat * RAD) * Math.cos(place.lng * RAD), Math.cos(place.lat * RAD) * Math.sin(place.lng * RAD), Math.sin(place.lat * RAD)];
      const b = [Math.cos(dc.lat * RAD) * Math.cos(dc.lng * RAD), Math.cos(dc.lat * RAD) * Math.sin(dc.lng * RAD), Math.sin(dc.lat * RAD)];
      const om = Math.acos(Math.max(-1, Math.min(1, a[0] * b[0] + a[1] * b[1] + a[2] * b[2])));
      if (om > 0.002) {
        ctx.globalAlpha = 0.9; ctx.strokeStyle = C.brand; ctx.lineWidth = 1.6; ctx.setLineDash([3, 3]);
        ctx.beginPath(); let pen = false;
        for (let i = 0; i <= 48; i++) {
          const f = i / 48, s1 = Math.sin((1 - f) * om) / Math.sin(om), s2 = Math.sin(f * om) / Math.sin(om);
          const v = [a[0] * s1 + b[0] * s2, a[1] * s1 + b[1] * s2, a[2] * s1 + b[2] * s2];
          const lift = 1 + 0.12 * Math.sin(f * Math.PI);  // arc rises off the surface
          const p = proj(Math.asin(v[2]) / RAD, Math.atan2(v[1], v[0]) / RAD);
          if (p.z <= 0) { pen = false; continue; }
          const x = cx + (p.x - cx) * lift, y = cy + (p.y - cy) * lift;
          if (pen) ctx.lineTo(x, y); else { ctx.moveTo(x, y); pen = true; }
        }
        ctx.stroke(); ctx.setLineDash([]);
      }
      const pp = proj(place.lat, place.lng);
      if (pp.z > 0) {
        ctx.globalAlpha = 1; ctx.fillStyle = C.ink;
        ctx.beginPath(); ctx.arc(pp.x, pp.y, 3.5, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = C.paper; ctx.lineWidth = 1.5; ctx.stroke();
      }
    }
    // Datacenters. Hover finds the nearest visible one under the pointer.
    let hover = null, best = 12;
    const pts = spotList.map(d => ({ d, p: proj(d.lat, d.lng) }));
    if (g.mouse && !(g.drag && g.drag.moved)) pts.forEach(({ d, p }) => {
      if (p.z <= 0.05) return;
      const dist = Math.hypot(p.x - g.mouse.x, p.y - g.mouse.y);
      if (dist < best) { best = dist; hover = d; }
    });
    g.hover = hover;
    el.style.cursor = g.drag && g.drag.moved ? 'grabbing' : hover ? 'pointer' : 'grab';
    pts.forEach(({ d, p }) => {
      if (p.z <= 0 || d === dc) return;
      ctx.globalAlpha = 0.35 + 0.65 * p.z;
      ctx.fillStyle = d === hover ? C.brand : C.ink;
      ctx.beginPath(); ctx.arc(p.x, p.y, d === hover ? 4 : 2.6, 0, Math.PI * 2); ctx.fill();
      ctx.strokeStyle = C.paper; ctx.lineWidth = 1.2; ctx.stroke();
    });
    const sp = proj(dc.lat, dc.lng);
    if (sp.z > 0) {
      const pulse = reduced ? 0.5 : (t / 1400) % 1;
      ctx.globalAlpha = (1 - pulse) * 0.55; ctx.strokeStyle = C.brand; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.arc(sp.x, sp.y, 5 + pulse * 13, 0, Math.PI * 2); ctx.stroke();
      ctx.globalAlpha = 1; ctx.fillStyle = C.brand;
      ctx.beginPath(); ctx.arc(sp.x, sp.y, 5, 0, Math.PI * 2); ctx.fill();
      ctx.strokeStyle = C.paper; ctx.lineWidth = 2; ctx.stroke();
    }
    // Label pill for the hovered (or else the selected) datacenter.
    const lab = hover || (sp.z > 0.15 ? dc : null);
    if (lab) {
      const p = lab === dc ? sp : proj(lab.lat, lab.lng);
      const text = nsDcMeta(lab).city;
      ctx.font = '600 11.5px ' + C.font;
      const tw = ctx.measureText(text).width, bw = tw + 14, bh = 20;
      let bx = p.x - bw / 2, by = p.y - 30;
      bx = Math.max(2, Math.min(W - bw - 2, bx)); if (by < 2) by = p.y + 12;
      ctx.globalAlpha = 0.96; ctx.fillStyle = lab === dc ? C.brand : C.ink;
      ctx.beginPath();
      if (ctx.roundRect) ctx.roundRect(bx, by, bw, bh, 10); else ctx.rect(bx, by, bw, bh);
      ctx.fill();
      ctx.globalAlpha = 1; ctx.fillStyle = lab === dc ? '#fff' : C.paper;
      ctx.textBaseline = 'middle'; ctx.fillText(text, bx + 7, by + bh / 2 + 0.5);
    }
    ctx.globalAlpha = 1;
  }
});
