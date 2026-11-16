// CaptainCore v3 — Chat customers (mixin, operators).
// /account/chat/customers: a buddy list of customers signed in to the
// dashboard. Who is on it right now comes live from the chat relay: the
// operator's first frame lists connected customers and customer.presence
// frames keep it current (active = a dashboard tab in front, away = in the
// background). Everyone else signed in, with when they were last seen, comes
// from the Manager (GET /chat/customers, Chat::signed_in_customers).

Object.assign(Component.prototype, {

  pplState() {
    const c = this._chat;
    if (!c) return null;
    if (!c.ppl) c.ppl = { list: [], live: {}, loaded: false, loading: false, err: '', q: '', seq: 0, relay: null, tick: null, refetch: null };
    return c.ppl;
  },

  // Show the Customers view (from the toggle or the /chat/customers URL).
  chatPeopleOpen() {
    const c = this.chatInit();
    const p = this.pplState();
    if (!c || !p) return;
    this.setState({ chatPeople: true, chatTriage: false });
    this.pplLoad(p.loaded);
    // Connect times count up while the list is on screen.
    if (!p.tick) p.tick = setInterval(() => { if (this.state.route === 'chat' && this.state.chatPeople) this.setState({}); }, 30000);
  },

  chatPeopleClose() {
    const p = this._chat && this._chat.ppl;
    if (p && p.tick) { clearInterval(p.tick); p.tick = null; }
    if (this.state.chatPeople) this.setState({ chatPeople: false });
  },

  pplLoad(quiet) {
    const p = this.pplState();
    if (!p) return;
    const seq = ++p.seq;
    if (!quiet) { p.loading = true; this.setState({}); }
    this.api('/chat/customers').then(r => {
      if (seq !== p.seq) return;
      if (!r || r.code) throw new Error((r && r.message) || 'Could not load customers');
      p.list = Array.isArray(r.customers) ? r.customers : [];
      p.loaded = true; p.loading = false; p.err = '';
      this.setState({});
    }).catch(e => {
      if (seq !== p.seq) return;
      p.loading = false; p.err = e && e.message && e.message !== 'auth' ? e.message : 'Could not load customers';
      this.setState({});
    });
  },

  // The relay's first frame (operators): everyone connected right now. A
  // relay without presence sends no list, which the view says.
  chatPeopleReady(m) {
    const p = this.pplState();
    if (!p) return;
    p.relay = Array.isArray(m.customers);
    p.live = {};
    (m.customers || []).forEach(x => { p.live[x.id] = x; });
    this.pplMaybeRefetch();
  },

  chatPeopleOnEvent(ev) {
    const p = this.pplState();
    if (!p || !ev.customer) return;
    const x = ev.customer;
    if (x.online) p.live[x.id] = x;
    else {
      delete p.live[x.id];
      const row = p.list.find(r => r.id === x.id);
      if (row) row.seen = x.seen;
    }
    this.pplMaybeRefetch();
    if (this.state.route === 'chat' && this.state.chatPeople) this.setState({});
  },

  // A customer the Manager list doesn't have yet (signed in after it
  // loaded): fetch it again, at most every 15 seconds.
  pplMaybeRefetch() {
    const p = this._chat && this._chat.ppl;
    if (!p || !p.loaded || p.refetch) return;
    if (!Object.keys(p.live).some(id => !p.list.some(r => r.id === +id))) return;
    p.refetch = setTimeout(() => { p.refetch = null; this.pplLoad(true); }, 15000);
  },

  pplDur(sec) {
    const s = Math.max(0, Math.floor(sec));
    if (s < 60) return 'just now';
    const m = Math.floor(s / 60), h = Math.floor(m / 60), d = Math.floor(h / 24);
    if (d) return d + 'd ' + (h % 24) + 'h';
    if (h) return h + 'h ' + (m % 60) + 'm';
    return m + 'm';
  },

  pplMessage(row) {
    if (row.conversation && row.conversation.id) {
      this.chatPeopleClose();
      this.chatOpen(row.conversation.id);
      return;
    }
    this.chatPeopleClose();
    this.chatNewForCustomer();
    this.chatPickCustomer({ id: row.id, name: row.name, email: row.email });
  },

  chatPeopleVals(s) {
    const p = this._chat && this._chat.ppl;
    const on = !!(p && s.route === 'chat' && s.chatPeople);
    const base = { chatPeopleOn: on, chatTabPeople: () => this.chatPeopleOpen(),
      chatTabPeopleBg: on ? 'var(--paper)' : 'transparent', chatTabPeopleFg: on ? 'var(--ink)' : 'var(--ink-dim)', chatTabPeopleShadow: on ? 'var(--shadow-sm)' : 'none' };
    if (!on) return Object.assign(base, { pplGroups: [] });
    const now = Date.now() / 1000;
    const byId = {};
    p.list.forEach(r => { byId[r.id] = Object.assign({}, r); });
    Object.values(p.live).forEach(x => { if (!byId[x.id]) byId[x.id] = { id: x.id, name: x.name, email: '', avatar: '', signed_in: true, seen: x.seen, conversation: null }; });
    const q = String(p.q || '').trim().toLowerCase();
    const rows = Object.values(byId).filter(r => !q || (r.name || '').toLowerCase().includes(q) || (r.email || '').toLowerCase().includes(q)).map(r => {
      const live = p.live[r.id];
      const state = live ? (live.active ? 'active' : 'away') : 'offline';
      const seen = Math.max(r.seen || 0, r.login || 0);
      const line = state === 'active' ? 'Online · connected ' + this.pplDur(now - live.since)
        : state === 'away' ? 'Away · tab in the background · connected ' + this.pplDur(now - live.since)
        : seen ? (r.signed_in ? 'Signed in · last seen ' : 'Last seen ') + (this.chatAgo(seen) === 'now' ? 'just now' : /^\d/.test(this.chatAgo(seen)) ? this.chatAgo(seen) + ' ago' : this.chatAgo(seen)) : 'Signed in';
      const name = r.name || r.email || 'Customer #' + r.id;
      const conv = r.conversation;
      return {
        key: 'p' + r.id, state, sort: live ? -live.since : -seen, name, email: r.email || '', line,
        initials: this.chatInitials(name), avBg: this.chatColor(name), avatar: this.chatAvUrl(r.avatar), hasAvatar: !!this.chatAvUrl(r.avatar),
        dot: state === 'active' ? 'var(--ok)' : state === 'away' ? 'var(--warn)' : 'var(--ink-dim)',
        dotLabel: state === 'active' ? 'Online' : state === 'away' ? 'Away' : 'Offline',
        actLabel: conv ? (conv.status === 'snoozed' ? 'Snoozed chat' : 'Open chat') : 'Message',
        actPrimary: conv ? '1' : '0',
        go: () => this.pplMessage(r)
      };
    });
    const group = (k, label) => {
      const list = rows.filter(r => r.state === k).sort((a, b) => a.sort - b.sort);
      return { key: k, label, count: String(list.length), rows: list, show: list.length > 0 };
    };
    const groups = [group('active', 'Online'), group('away', 'Away'), group('offline', 'Signed in, not on the dashboard')];
    const online = groups[0].rows.length + groups[1].rows.length;
    const c = this._chat;
    const note = c.wsState !== 'live' ? 'Not connected to the live relay, so nobody shows as online. Last-seen times still come from the Manager.'
      : p.relay === false ? 'The chat relay doesn’t report presence yet (it needs the update), so nobody shows as online.'
      : '';
    return Object.assign(base, {
      pplGroups: groups,
      pplSummary: online + ' online · ' + rows.length + ' signed in' + (q ? ' matching' : ''),
      pplLoading: p.loading && !p.loaded, pplErr: p.err, pplHasErr: !!p.err,
      pplEmpty: p.loaded && !rows.length, pplEmptyText: q ? 'Nobody matches “' + p.q + '”.' : 'Nobody is signed in right now.',
      pplNote: note, pplHasNote: !!note,
      pplQ: p.q, onPplQ: e => { p.q = e.target.value; this.setState({}); },
      pplRefresh: () => this.pplLoad(false)
    });
  }

});
