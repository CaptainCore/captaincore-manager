// CaptainCore v3 — CaptainCore Chat (mixin).
// Operators get a full-screen inbox at /account/chat; customers with chat
// enabled get a launcher + panel in the corner (in place of Intercom) and
// their whole history as a page at /account/support.
// Storage and every rule live in the Manager (app/Chat.php, REST under
// /chat/*). Live delivery comes from the captaincore-chat relay: a
// websocket authenticated with a short-lived token from GET /chat/token.
// The Manager publishes each change to the relay, which pushes it to the
// operators and the owning customer. With no relay configured
// (CC_BOOT.chat.live false) the panel refreshes every 30 seconds instead.
// Message bodies are plain text: everything is escaped before it renders.

Object.assign(Component.prototype, {

  chatBoot() { return (window.CC_BOOT && window.CC_BOOT.chat) || {}; },

  chatInit() {
    const cb = this.chatBoot();
    if (!cb.enabled || this._chat) return this._chat;
    const c = this._chat = {
      list: [], counts: null, filter: cb.operator ? 'waiting' : 'all', q: '', more: false, loading: true, listSeq: 0,
      id: 0, conv: null, convLoading: false, convErr: '', typing: {}, online: false, history: {},
      ws: null, wsState: 'off', everLive: false, retry: 0, lastTyped: 0, readSent: {}, nearBottom: true, scrollPin: false
    };
    this.chatLoadList();
    if (cb.operator) this.chatPushInit();
    if (cb.operator) this.chatFocusInit();
    if (cb.live) this.chatConnect();
    else this._chatPoll = setInterval(() => { this.chatLoadList(true); if (c.id) this.chatLoadConv(c.id, true); }, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) this.chatMarkRead(); });
    return c;
  },

  // ── data ──────────────────────────────────────────────────────────────

  chatLoadList(quiet, more) {
    const c = this._chat;
    if (!c) return;
    const seq = ++c.listSeq;
    const p = new URLSearchParams();
    if (c.filter !== 'all') p.set('status', c.filter);
    if (c.q.trim()) p.set('q', c.q.trim());
    p.set('limit', '40');
    if (more && c.list.length) p.set('before', String(c.list[c.list.length - 1].last_message_at || 0));
    if (!quiet && !more) { c.loading = true; this.setState({}); }
    this.api('/chat/conversations?' + p.toString()).then(r => {
      if (seq !== c.listSeq) return;
      const rows = (r && r.conversations) || [];
      c.loading = false;
      c.list = more ? c.list.concat(rows.filter(x => !c.list.some(y => y.id === x.id))) : rows;
      c.more = rows.length === 40;
      if (r && r.counts) { c.counts = r.counts; this.chatAppBadge(r.counts.waiting); }
      this.setState({});
      this.chatSupportPick();
    }).catch(() => { if (seq === c.listSeq) { c.loading = false; this.setState({}); } });
  },

  // Customer Support page: on a wide screen something is always open, so
  // arriving there shows the newest conversation (phones start on the list).
  chatSupportPick() {
    const c = this._chat;
    if (!c || this.state.route !== 'support' || c.id || c.newDraft || c.loading || window.innerWidth <= 760) return;
    if (c.list.length) this.chatOpen(c.list[0].id);
  },

  // Whether a customer can see the open thread right now.
  chatCustomerViewing() {
    const s = this.state;
    return s.route === 'support' || (!!s.chatWidgetOpen && s.chatWView === 'thread');
  },

  chatOpen(id) {
    const c = this.chatInit();
    if (!c) return;
    id = parseInt(id, 10) || 0;
    if (c.id === id && c.conv) return;
    c.id = id; c.conv = null; c.convErr = ''; c.convLoading = !!id; c.scrollPin = true; c.newDraft = false;
    this._chatClear = true;
    this.setState({ chatId: id ? String(id) : '', chatBody: '', chatMode: 'reply' });
    if (id) this.chatLoadConv(id);
  },

  chatLoadConv(id, quiet) {
    const c = this._chat;
    this.api('/chat/conversations/' + id).then(r => {
      if (c.id !== id) return;
      c.convLoading = false;
      if (!r || r.code || !r.conversation) {
        if (!quiet) { c.conv = null; c.convErr = (r && r.message) || 'Conversation not found.'; }
        this.setState({});
        return;
      }
      const had = c.conv ? c.conv.messages.length : -1;
      c.conv = r;
      if (had !== r.messages.length) c.scrollPin = c.scrollPin || c.nearBottom;
      const row = c.list.find(x => x.id === id);
      if (row) Object.assign(row, r.conversation, { unread: 0 });
      this.setState({});
      this.chatMarkRead();
      if (this.chatBoot().operator) { this.chatLoadHistory(r.conversation); this.chatLoadAccess(r.conversation); }
    }).catch(() => { if (c.id === id) { c.convLoading = false; if (!quiet) c.convErr = 'Could not load this conversation.'; this.setState({}); } });
  },

  // The customer's other conversations, for the operator sidebar.
  chatLoadHistory(conv) {
    const c = this._chat;
    const uid = conv.customer_user_id;
    if (!uid || c.history[uid]) return;
    c.history[uid] = { loading: true, list: [] };
    this.api('/chat/conversations?customer=' + uid + '&limit=25').then(r => {
      c.history[uid] = { loading: false, list: (r && r.conversations) || [] };
      this.setState({});
    }).catch(() => { c.history[uid] = { loading: false, list: [] }; });
  },

  // What the customer can reach (accounts, sites, domains), for the
  // operator sidebar. Cached per customer for the session.
  chatLoadAccess(conv) {
    const c = this._chat;
    const uid = conv.customer_user_id;
    c.access = c.access || {};
    if (!uid || c.access[uid]) return;
    c.access[uid] = { loading: true };
    this.api('/chat/customers/' + uid + '/access').then(r => {
      c.access[uid] = (r && !r.code) ? r : { error: true };
      this.setState({});
    }).catch(() => { c.access[uid] = { error: true }; this.setState({}); });
  },

  // Mark the open thread read up to its newest message, once per id, and
  // only while the thread is actually on screen.
  chatMarkRead() {
    const c = this._chat;
    if (!c || !c.conv || document.hidden) return;
    const cb = this.chatBoot();
    if (cb.operator ? this.state.route !== 'chat' : !this.chatCustomerViewing()) return;
    const msgs = c.conv.messages;
    const last = msgs.length ? msgs[msgs.length - 1].id : 0;
    const cid = c.conv.conversation.id;
    if (!last || (c.readSent[cid] || 0) >= last) return;
    c.readSent[cid] = last;
    const row = c.list.find(x => x.id === cid);
    if (row) row.unread = 0;
    this.api('/chat/conversations/' + cid + '/read', { method: 'POST', body: { message_id: last } }).catch(() => {});
  },

  chatSend() {
    const c = this._chat;
    const s = this.state;
    const cb = this.chatBoot();
    const body = (s.chatBody || '').trim();
    if (!c || !body || s.chatSending) return;
    const note = cb.operator && s.chatMode === 'note';
    this.setState({ chatSending: true });
    const req = c.id
      ? this.api('/chat/conversations/' + c.id + '/messages', { method: 'POST', body: { body, kind: note ? 'note' : 'comment' } })
      : this.api('/chat/conversations', { method: 'POST', body: { body } });
    req.then(r => {
      if (!r || r.code) throw new Error((r && r.message) || 'Could not send');
      if (r.messages) {
        // A new conversation (customers).
        c.id = r.conversation.id; c.newDraft = false;
        c.conv = { conversation: r.conversation, messages: r.messages };
        this.chatMergeConv(r.conversation, true);
        this.chatOnMessage(r.messages[0]);
      } else {
        this.chatMergeConv(r.conversation);
        this.chatOnMessage(r.message);
      }
      c.scrollPin = true;
      this._chatClear = true;
      this.setState({ chatSending: false, chatBody: '', chatId: String(c.id) });
      this.chatMarkRead();
    }).catch(e => {
      this.setState({ chatSending: false });
      this.toast(e && e.message && e.message !== 'auth' ? e.message : 'Could not send the message', { kind: 'error' });
    });
  },

  chatPatch(patch, done) {
    const c = this._chat;
    if (!c || !c.id) return;
    this.api('/chat/conversations/' + c.id, { method: 'PATCH', body: patch }).then(r => {
      if (!r || r.code) throw new Error((r && r.message) || 'Could not update');
      this.chatMergeConv(r.conversation);
      if (done) this.toast(done, { kind: 'success' });
      this.chatLoadConv(c.id, true);
      if (this.chatBoot().operator) this.chatLoadList(true);
    }).catch(e => this.toast(e && e.message !== 'auth' ? e.message : 'Could not update', { kind: 'error' }));
  },

  chatSnoozeMenu(e) {
    const at = (h, m, addDays) => { const d = new Date(); d.setDate(d.getDate() + addDays); d.setHours(h, m, 0, 0); return Math.floor(d.getTime() / 1000); };
    const nextMonday = () => { const d = new Date(); d.setDate(d.getDate() + ((8 - d.getDay()) % 7 || 7)); d.setHours(9, 0, 0, 0); return Math.floor(d.getTime() / 1000); };
    const snooze = (until, label) => () => this.chatPatch({ status: 'snoozed', snoozed_until: until }, 'Snoozed until ' + label);
    this.openCtxMenu(e, [
      { label: 'In 1 hour', act: snooze(Math.floor(Date.now() / 1000) + 3600, 'in an hour') },
      { label: 'Tomorrow, 9am', act: snooze(at(9, 0, 1), 'tomorrow 9am') },
      { label: 'Next Monday, 9am', act: snooze(nextMonday(), 'Monday 9am') },
      { label: 'In a week', act: snooze(Math.floor(Date.now() / 1000) + 7 * 86400, 'next week') }
    ]);
  },

  // ── live relay ────────────────────────────────────────────────────────

  chatConnect() {
    const c = this._chat;
    if (!c || c.wsState === 'connecting' || c.ws) return;
    c.wsState = 'connecting';
    clearTimeout(c.reconnectTimer);
    this.api('/chat/token').then(t => {
      if (!t || !t.token || !t.websocket) throw new Error('token');
      const ws = new WebSocket(t.websocket);
      c.ws = ws;
      ws.onopen = () => ws.send(JSON.stringify({ type: 'auth', token: t.token }));
      ws.onmessage = e => {
        let m;
        try { m = JSON.parse(e.data); } catch (x) { return; }
        if (m.type === 'ready') {
          c.wsState = 'live'; c.retry = 0; c.online = !!m.operators_online;
          // Events published before this socket was ready (down, or still
          // connecting after the first load) were missed: refetch what's on screen.
          this.chatLoadList(true); if (c.id) this.chatLoadConv(c.id, true);
          c.everLive = true;
          c.focusSent = null;
          this.chatFocusTick();
          // Tokens last 10 minutes; refresh in place every 8.
          clearInterval(c.refreshTimer);
          c.refreshTimer = setInterval(() => {
            this.api('/chat/token').then(n => { if (n && n.token && c.ws === ws && ws.readyState === 1) ws.send(JSON.stringify({ type: 'auth', token: n.token })); }).catch(() => {});
          }, 8 * 60 * 1000);
          this.setState({});
        } else if (m.type !== 'reauthed' && m.type !== 'pong') {
          this.chatOnEvent(m);
        }
      };
      ws.onclose = e => {
        if (c.ws !== ws) return;
        c.ws = null; c.wsState = 'off';
        clearInterval(c.refreshTimer);
        this.setState({});
        // 4001 = token rejected or expired: fetch a new one right away.
        const delay = e.code === 4001 ? 500 : Math.min(30000, 1000 * Math.pow(2, c.retry++));
        c.reconnectTimer = setTimeout(() => this.chatConnect(), delay);
      };
    }).catch(() => {
      c.wsState = 'off';
      this.setState({});
      c.reconnectTimer = setTimeout(() => this.chatConnect(), Math.min(60000, 2000 * Math.pow(2, c.retry++)));
    });
  },

  chatOnEvent(ev) {
    const c = this._chat;
    const cb = this.chatBoot();
    if (ev.type === 'conversation.created' || ev.type === 'conversation.updated' || ev.type === 'message.created') {
      if (ev.conversation) this.chatMergeConv(ev.conversation, ev.type === 'conversation.created');
      if (ev.message) this.chatOnMessage(ev.message);
      if (cb.operator && ev.type !== 'message.created') {
        clearTimeout(c.countsTimer);
        c.countsTimer = setTimeout(() => this.chatLoadList(true), 1500);
      }
    } else if (ev.type === 'typing') {
      const u = ev.user || {};
      if (u.id === cb.userId) return;
      // A customer's typing reaches every operator; show it only on that
      // customer's own conversation.
      if (cb.operator && !u.operator) {
        const conv = c.list.find(x => x.id === ev.conversation_id) || (c.conv && c.conv.conversation.id === ev.conversation_id ? c.conv.conversation : null);
        if (!conv || conv.customer_user_id !== u.id) return;
      }
      c.typing[ev.conversation_id] = { name: u.name || 'Someone', until: Date.now() + 4000 };
      clearTimeout(c.typingTimer);
      c.typingTimer = setTimeout(() => this.setState({}), 4100);
    } else if (ev.type === 'read') {
      if (c.conv && c.conv.conversation.id === ev.conversation_id)
        c.conv.customer_last_read_message_id = Math.max(c.conv.customer_last_read_message_id || 0, ev.message_id || 0);
    } else if (ev.type === 'presence') {
      c.online = !!ev.operators_online;
    }
    this.setState({});
  },

  // Fold a conversation from an event or response into the list and the open thread.
  chatMergeConv(conv, created) {
    const c = this._chat;
    if (!conv || !conv.id) return;
    const row = c.list.find(x => x.id === conv.id);
    if (row) Object.assign(row, conv);
    else if (created || this.chatMatchesFilter(conv)) c.list.unshift(Object.assign({ preview: '', unread: 0 }, conv));
    c.list.sort((a, b) => (b.last_message_at || 0) - (a.last_message_at || 0) || b.id - a.id);
    if (c.conv && c.conv.conversation.id === conv.id) Object.assign(c.conv.conversation, conv);
  },

  chatMatchesFilter(conv) {
    const c = this._chat;
    if (c.q.trim()) return false;
    if (c.filter === 'all') return true;
    if (c.filter === 'waiting') return conv.status === 'open' && !!conv.waiting_since;
    if (c.filter === 'open' && !this.chatBoot().operator) return conv.status !== 'closed';
    return conv.status === c.filter;
  },

  chatOnMessage(m) {
    const c = this._chat;
    const cb = this.chatBoot();
    if (!m || !m.id) return;
    const mine = cb.operator ? m.author_kind === 'operator' : m.author_kind === 'customer';
    const viewing = c.conv && c.conv.conversation.id === m.conversation_id;
    if (viewing && !c.conv.messages.some(x => x.id === m.id)) {
      c.conv.messages.push(m);
      c.conv.messages.sort((a, b) => a.id - b.id);
      c.scrollPin = c.scrollPin || c.nearBottom || mine;
    }
    delete c.typing[m.conversation_id];
    const row = c.list.find(x => x.id === m.conversation_id);
    if (row && m.kind === 'comment') {
      if (row._lastSeen !== m.id) {
        row._lastSeen = m.id;
        row.preview = String(m.body || '').slice(0, 200);
        const onScreen = viewing && !document.hidden && (cb.operator ? this.state.route === 'chat' : this.chatCustomerViewing());
        if (!mine && !onScreen) row.unread = (row.unread || 0) + 1;
      }
    }
    if (viewing) setTimeout(() => this.chatMarkRead(), 0);
  },

  chatTyping() {
    const c = this._chat;
    const cb = this.chatBoot();
    if (!c || !c.ws || c.ws.readyState !== 1 || !c.id || Date.now() - c.lastTyped < 2000) return;
    c.lastTyped = Date.now();
    const frame = { type: 'typing', conversation_id: c.id };
    // Operators name the customer. A note in progress goes to operators only.
    if (cb.operator) frame.customer_user_id = this.state.chatMode === 'note' || !c.conv ? 0 : c.conv.conversation.customer_user_id;
    c.ws.send(JSON.stringify(frame));
  },

  // ── phone notifications (operators) ───────────────────────────────────
  // Web push (ChatPush on the server, push-sw.js on the device). On an
  // iPhone this only works from the home-screen app, so Safari gets a hint
  // instead. A subscription is re-sent on every load, which also repairs
  // one the server dropped.

  chatPushInit() {
    const c = this._chat;
    const cfg = this.chatBoot().push;
    const ios = /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    c.push = { state: 'unsupported', ios, standalone, sub: null, reg: null };
    if (!cfg || !cfg.publicKey || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      if (ios && !standalone) c.push.state = 'install';
      return;
    }
    c.push.state = Notification.permission === 'denied' ? 'denied' : 'off';
    navigator.serviceWorker.addEventListener('message', e => {
      if (!e.data || e.data.type !== 'cc-open' || !e.data.url) return;
      try { history.pushState(null, '', new URL(e.data.url).pathname); this.applyUrl(); } catch (x) {}
    });
    navigator.serviceWorker.register(cfg.sw).then(reg => {
      c.push.reg = reg;
      return reg.pushManager.getSubscription();
    }).then(sub => {
      if (sub && this.chatPushKeyMatches(sub, cfg.publicKey)) {
        c.push.sub = sub; c.push.state = 'on';
        this.api('/chat/push', { method: 'POST', body: sub.toJSON() }).catch(() => {});
      } else if (sub) {
        sub.unsubscribe().catch(() => {}); // made with an older server key
      }
      this.setState({});
    }).catch(() => {});
  },

  chatPushKeyMatches(sub, key) {
    try {
      const have = new Uint8Array(sub.options.applicationServerKey || []);
      const want = this.chatB64ToBytes(key);
      return have.length === 0 || (have.length === want.length && have.every((b, i) => b === want[i]));
    } catch (x) { return true; }
  },

  chatB64ToBytes(b64) {
    const s = atob((b64 + '='.repeat((4 - b64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(s, ch => ch.charCodeAt(0));
  },

  // The bell in the inbox's top bar.
  chatPushButton(e) {
    const c = this._chat;
    const p = c && c.push;
    if (!p) return;
    if (p.state === 'install') {
      this.toast('To get notifications on this iPhone, tap Share, then Add to Home Screen, and open the app from there.', { kind: 'info' });
      return;
    }
    if (p.state === 'denied') {
      this.toast('Notifications are blocked for this site. Allow them in your browser or iOS Settings, then try again.', { kind: 'info' });
      return;
    }
    if (p.state === 'on') {
      this.openCtxMenu(e, [
        { label: 'Send a test notification', act: () => this.api('/chat/push/test', { method: 'POST' }).then(r => this.toast(r && r.sent ? 'Test sent.' : 'No device took the test. Try turning notifications off and on.', { kind: r && r.sent ? 'success' : 'info' })).catch(() => this.toast('Could not send a test.', { kind: 'error' })) },
        { label: 'Turn off on this device', act: () => this.chatPushOff() }
      ]);
      return;
    }
    this.chatPushOn();
  },

  chatPushOn() {
    const c = this._chat;
    const p = c.push;
    const cfg = this.chatBoot().push;
    if (!p.reg || p.state === 'busy') return;
    p.state = 'busy'; this.setState({});
    // requestPermission must run straight from the tap (iOS).
    Notification.requestPermission().then(perm => {
      if (perm !== 'granted') throw new Error(perm === 'denied' ? 'denied' : 'dismissed');
      return p.reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.chatB64ToBytes(cfg.publicKey) });
    }).then(sub => {
      p.sub = sub;
      return this.api('/chat/push', { method: 'POST', body: sub.toJSON() });
    }).then(r => {
      if (!r || r.code) throw new Error((r && r.message) || 'save');
      p.state = 'on'; this.setState({});
      return this.api('/chat/push/test', { method: 'POST' });
    }).then(() => this.toast('Notifications are on for this device.', { kind: 'success' }))
      .catch(err => {
        p.state = err && err.message === 'denied' ? 'denied' : (p.sub ? 'on' : 'off');
        this.setState({});
        if (err && err.message !== 'dismissed') this.toast(err.message === 'denied' ? 'Notifications were blocked.' : 'Could not turn on notifications.', { kind: 'error' });
      });
  },

  // Also called before logging out: an explicit log out stops this
  // device's notifications; an expired session does not.
  chatPushOff() {
    const c = this._chat;
    const p = c && c.push;
    if (!p || !p.sub) return Promise.resolve();
    const sub = p.sub;
    p.sub = null; p.state = 'off'; this.setState({});
    return Promise.all([
      this.api('/chat/push', { method: 'DELETE', body: { endpoint: sub.endpoint } }).catch(() => {}),
      sub.unsubscribe().catch(() => {})
    ]).then(() => this.chatAppBadge(0));
  },

  chatAppBadge(n) {
    if (!this.chatBoot().operator || !navigator.setAppBadge) return;
    (n > 0 ? navigator.setAppBadge(n) : navigator.clearAppBadge()).catch(() => {});
  },

  // ── focus frames (operators) ──────────────────────────────────────────
  // The relay reports operators who have the inbox on screen and in use
  // (visible tab, input in the last 5 minutes), and their phones stay quiet.

  chatFocusInit() {
    const c = this._chat;
    c.lastInput = Date.now();
    const touch = () => { const idle = Date.now() - c.lastInput > 60000; c.lastInput = Date.now(); if (idle) this.chatFocusTick(); };
    ['pointerdown', 'keydown', 'wheel', 'touchstart', 'mousemove'].forEach(ev => window.addEventListener(ev, touch, { passive: true }));
    document.addEventListener('visibilitychange', () => { if (!document.hidden) c.lastInput = Date.now(); this.chatFocusTick(); });
    setInterval(() => this.chatFocusTick(), 30000);
  },

  chatFocusTick() {
    const c = this._chat;
    if (!c || !this.chatBoot().operator || !c.ws || c.ws.readyState !== 1) return;
    const active = this.state.route === 'chat' && !document.hidden && Date.now() - (c.lastInput || 0) < 5 * 60 * 1000;
    if (c.focusSent === active) return;
    c.focusSent = active;
    c.ws.send(JSON.stringify({ type: 'focus', active }));
  },

  // ── rendering helpers ─────────────────────────────────────────────────

  chatEsc(t) { return String(t == null ? '' : t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); },

  // Escaped text with http(s) links and line breaks. Nothing else becomes markup.
  chatText(t) {
    return this.chatEsc(t)
      .replace(/https?:\/\/[^\s<]+[^\s<.,;:!?)\]'"&]/g, u => '<a href="' + u + '" target="_blank" rel="noopener noreferrer">' + u + '</a>')
      .replace(/\n/g, '<br>');
  },

  chatInitials(name) {
    const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
    return ((parts[0] || '?')[0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
  },

  chatColor(seed) {
    const pal = ['#3b82c4', '#7c5cff', '#0e9f6e', '#c2410c', '#be185d', '#0891b2', '#4d7c0f', '#9333ea', '#b45309'];
    let h = 0;
    for (const ch of String(seed || '')) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
    return pal[h % pal.length];
  },

  chatAgo(ts) {
    if (!ts) return '';
    const d = Math.max(0, Date.now() / 1000 - ts);
    if (d < 60) return 'now';
    if (d < 3600) return Math.floor(d / 60) + 'm';
    if (d < 86400) return Math.floor(d / 3600) + 'h';
    if (d < 86400 * 7) return Math.floor(d / 86400) + 'd';
    const dt = new Date(ts * 1000);
    return dt.toLocaleDateString(undefined, Object.assign({ month: 'short', day: 'numeric' }, dt.getFullYear() !== new Date().getFullYear() ? { year: '2-digit' } : {}));
  },

  chatFileUrl(f) {
    const boot = window.CC_BOOT || {};
    return f.url + (f.url.indexOf('?') > -1 ? '&' : '?') + '_wpnonce=' + encodeURIComponent(boot.nonce || '');
  },

  chatFilesHtml(files) {
    if (!files || !files.length) return '';
    const h = b => b < 1024 ? b + ' B' : b < 1048576 ? Math.round(b / 1024) + ' KB' : (b / 1048576).toFixed(1) + ' MB';
    return '<div class="ccc-files">' + files.map(f => {
      const url = this.chatEsc(this.chatFileUrl(f));
      return f.inline
        ? '<a class="ccc-img" href="' + url + '" target="_blank" rel="noopener"><img src="' + url + '" alt="' + this.chatEsc(f.name) + '" loading="lazy"></a>'
        : '<a class="ccc-file" href="' + url + '" download><span>📄</span>' + this.chatEsc(f.name) + ' <em>' + h(f.size || 0) + '</em></a>';
    }).join('') + '</div>';
  },

  // The thread as HTML. Operators and the customer Support page get flat
  // rows (notes and events inline); the corner panel gets bubbles.
  chatThreadHtml() {
    const c = this._chat;
    const cb = this.chatBoot();
    if (!c.conv) return '';
    const conv = c.conv.conversation;
    const msgs = c.conv.messages;
    const day = ts => new Date(ts * 1000).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    const time = ts => new Date(ts * 1000).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    const esc = t => this.chatEsc(t);
    // Email replies (ChatNotify): operators can check what the cleanup cut.
    const orig = m => (cb.operator && m.via === 'email' && m.original) ? '<details class="ccc-orig"><summary>Original email</summary><pre>' + esc(m.original) + '</pre></details>' : '';
    const flat = cb.operator || this.state.route === 'support';
    let out = '';
    let lastDay = '';
    let prev = null;
    if (conv.source === 'intercom') out += '<div class="ccc-imported">Imported from Intercom. Replies here continue the same conversation.</div>';
    msgs.forEach(m => {
      const d = day(m.created_at);
      if (d !== lastDay) { out += '<div class="ccc-day">' + esc(d) + '</div>'; lastDay = d; prev = null; }
      const name = m.author_name || (m.author_kind === 'operator' ? (window.CC_BOOT && window.CC_BOOT.name) || 'Support' : conv.customer_name || 'Customer');
      if (m.kind === 'event') {
        out += '<div class="ccc-sys' + (m.internal ? ' ccc-sys-int' : '') + '">' + esc(m.body) + ' · ' + esc(time(m.created_at)) + '</div>';
        prev = null;
        return;
      }
      if (m.kind === 'note') {
        out += '<div class="ccc-note"><div class="ccc-nh">Internal note · ' + esc(name) + ' · ' + esc(time(m.created_at)) + '</div><div class="ccc-mb">' + this.chatText(m.body) + '</div>' + this.chatFilesHtml(m.attachments) + orig(m) + '</div>';
        prev = null;
        return;
      }
      if (flat) {
        const cont = prev && prev.author_kind === m.author_kind && prev.author_name === m.author_name && m.created_at - prev.created_at < 300;
        out += '<div class="ccc-msg' + (cont ? ' ccc-cont' : '') + '">' +
          '<span class="ccc-av" style="background:' + this.chatColor(name) + '">' + esc(this.chatInitials(name)) + '</span><div class="ccc-mbody">' +
          (cont ? '' : '<div class="ccc-mh"><b>' + esc(name) + '</b>' + (m.author_kind === 'operator' ? '<span class="ccc-staff">Staff</span>' : '') + (m.via === 'email' ? '<span class="ccc-via">by email</span>' : '') + '<span class="ccc-t">' + esc(time(m.created_at)) + '</span></div>') +
          (m.body ? '<div class="ccc-mb">' + this.chatText(m.body) + '</div>' : '') + this.chatFilesHtml(m.attachments) + orig(m) + '</div></div>';
      } else {
        const me = m.author_kind === 'customer';
        const cont = prev && prev.author_kind === m.author_kind && m.created_at - prev.created_at < 300;
        out += '<div class="ccc-bub ' + (me ? 'ccc-me' : 'ccc-them') + '">' +
          (me ? '' : '<span class="ccc-av ccc-av-sm" style="background:' + this.chatColor(name) + ';visibility:' + (cont ? 'hidden' : 'visible') + '">' + esc(this.chatInitials(name)) + '</span>') +
          '<div class="ccc-bwrap">' + (m.body ? '<div class="ccc-bb">' + this.chatText(m.body) + '</div>' : '') + this.chatFilesHtml(m.attachments) + '</div></div>' +
          '<div class="ccc-bmeta' + (me ? ' ccc-r' : '') + '">' + (me ? '' : esc(name) + ' · ') + esc(time(m.created_at)) + '</div>';
      }
      prev = m;
    });
    // "Seen" receipt under the last operator reply (operators only).
    if (cb.operator && conv.customer_user_id) {
      const lastOp = [...msgs].reverse().find(m => m.kind === 'comment' && m.author_kind === 'operator');
      if (lastOp && !lastOp.imported && (c.conv.customer_last_read_message_id || 0) >= lastOp.id) out += '<div class="ccc-seen">Seen by ' + esc(String(conv.customer_name || 'the customer').split(' ')[0]) + '</div>';
    }
    const ty = c.typing[conv.id];
    if (ty && ty.until > Date.now()) out += '<div class="ccc-typing"><i></i><i></i><i></i> ' + esc(ty.name) + ' is typing</div>';
    if (!msgs.length) out += '<div class="ccc-empty">No messages yet.</div>';
    return out;
  },

  // ref for the thread scroller: rebuilds only when its content changes and
  // keeps the reader pinned to the bottom while they are there.
  chatThreadRef(el) {
    if (!el) return;
    const c = this._chat;
    if (!c) return;
    if (!el._cccBound) {
      el._cccBound = true;
      el.addEventListener('scroll', () => { c.nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 80; });
    }
    const html = this.chatThreadHtml();
    if (el._cccHtml !== html) {
      el._cccHtml = html;
      el.innerHTML = '<div class="ccc-thread-in">' + html + '</div>';
      el.querySelectorAll('img').forEach(img => img.addEventListener('load', () => { if (c.nearBottom) el.scrollTop = el.scrollHeight; }));
    }
    if (c.scrollPin && c.conv) { el.scrollTop = el.scrollHeight; c.scrollPin = false; c.nearBottom = true; }
  },

  chatInputRef(el) {
    if (!el) return;
    // value="{{ }}" only seeds the field (DC runtime), so clearing after a
    // send or a conversation switch happens here.
    if (this._chatClear) { this._chatClear = false; el.value = ''; }
    // Grow with the text, up to the CSS max-height.
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 180) + 'px';
    if (this._chatFocus) { this._chatFocus = false; setTimeout(() => el.focus(), 0); }
  },

  // ── view values ───────────────────────────────────────────────────────

  chatVals(s) {
    const cb = this.chatBoot();
    const c = this._chat;
    const isOp = !!cb.operator;
    const off = {
      showChat: false, chatPushShow: false, chatPhoneView: 'list', chatAccessGroups: [], chatSideOpen: '0', chatWidgetShow: false, chatWidgetOpen: false, chatRows: [], chatFilters: [], chatActions: [], chatModes: [],
      chatHistory: [], chatHasHistory: false, chatNoHistory: false, supFilters: []
    };
    if (!c) return off;
    if (s.route !== 'chat') this._chatPrevRoute = s.route;
    const conv = c.conv && c.conv.conversation;
    const live = !cb.live ? 'Refreshing every 30s' : c.wsState === 'live' ? 'Live' : c.wsState === 'connecting' ? 'Connecting…' : 'Reconnecting…';
    const liveFg = c.wsState === 'live' ? 'var(--ok)' : cb.live ? 'var(--warn)' : 'var(--ink-dim)';

    const statusChip = x => x.status === 'closed' ? { label: isOp ? 'Closed' : 'Resolved', bg: 'var(--panel-2)', fg: 'var(--ink-dim)' }
      : x.status === 'snoozed' && isOp ? { label: 'Snoozed', bg: 'var(--warn-soft)', fg: 'color-mix(in srgb,var(--warn) 75%,var(--ink))' }
      : x.waiting_since && isOp ? { label: 'Waiting ' + this.chatAgo(x.waiting_since), bg: 'var(--brand-soft)', fg: 'var(--brand-ink)' }
      : { label: 'Open', bg: 'var(--ok-soft)', fg: 'color-mix(in srgb,var(--ok) 75%,var(--ink))' };

    const rows = c.list.map(x => {
      const on = x.id === c.id;
      const name = isOp ? (x.customer_name || x.customer_email || 'Guest') : x.subject;
      const chips = [statusChip(x)];
      if (isOp && x.guest) chips.push({ label: 'Guest', bg: 'var(--panel-2)', fg: 'var(--ink-dim)' });
      if (isOp && x.site_id) chips.push({ label: 'Site #' + x.site_id, bg: 'var(--panel-2)', fg: 'var(--ink-dim)' });
      return {
        key: 'c' + x.id, name, subject: isOp ? x.subject : (x.preview || ''), preview: isOp ? (x.preview || '') : '', ago: this.chatAgo(x.last_message_at),
        initials: this.chatInitials(isOp ? name : 'Support'), avBg: this.chatColor(isOp ? name : 'Support'),
        chips, unread: String(x.unread || 0), hasUnread: (x.unread || 0) > 0, bold: (x.unread || 0) > 0 ? 700 : 600,
        bg: on ? 'var(--paper)' : 'transparent', shadow: on ? 'var(--shadow-sm)' : 'none', outline: on ? '1px solid var(--rule)' : '0',
        bar: on ? 'var(--brand)' : 'transparent',
        letter: String(x.subject || '?').trim().charAt(0).toUpperCase() || '?', letterBg: this.chatColor(x.subject || String(x.id)),
        open: () => { this.chatOpen(x.id); if (!isOp) this.setState({ chatWView: 'thread' }); }
      };
    });
    const counts = c.counts || {};
    const filters = [['waiting', 'Waiting'], ['open', 'Open'], ['snoozed', 'Snoozed'], ['closed', 'Closed'], ['all', 'All']].map(([v, l]) => ({
      label: l, count: counts[v === 'all' ? 'total' : v] !== undefined ? Number(counts[v === 'all' ? 'total' : v]).toLocaleString() : '',
      bg: c.filter === v ? 'var(--panel-2)' : 'transparent', fg: c.filter === v ? 'var(--ink)' : 'var(--ink-dim)',
      go: () => { if (c.filter !== v) { c.filter = v; this.chatLoadList(); } }
    }));

    const note = isOp && s.chatMode === 'note';
    const ty = conv && c.typing[conv.id] && c.typing[conv.id].until > Date.now();
    const custFirst = conv ? String(conv.customer_name || 'the customer').split(' ')[0] : '';
    const actions = !conv || !isOp ? [] : [
      conv.status === 'closed'
        ? { label: 'Reopen', go: () => this.chatPatch({ status: 'open' }, 'Reopened'), primary: false }
        : { label: 'Close', go: () => this.chatPatch({ status: 'closed' }, 'Closed'), primary: true },
      conv.status === 'snoozed'
        ? { label: 'Unsnooze', go: () => this.chatPatch({ status: 'open' }, 'Back in the inbox'), primary: false }
        : { label: 'Snooze ▾', go: e => this.chatSnoozeMenu(e), primary: false }
    ].map(a => Object.assign(a, { bg: a.primary ? 'var(--brand)' : 'var(--paper)', fg: a.primary ? 'white' : 'var(--ink)', bd: a.primary ? 'var(--brand)' : 'var(--rule)' }));
    const hist = conv && c.history[conv.customer_user_id] ? c.history[conv.customer_user_id].list.filter(x => x.id !== conv.id) : [];
    // Access groups: links into the Manager. A plain click navigates in the
    // app; modified clicks open the real URL in a new tab.
    const acc = conv && c.access ? c.access[conv.customer_user_id] : null;
    const appBase = this.routeBase();
    const levelLabel = { 'full-billing': 'Owner', 'full': 'Full', 'sites-only': 'Sites only', 'domains-only': 'Domains only' };
    const link = (href, open) => e => { if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return; e.preventDefault(); c.sideOpen = false; open(); };
    const group = (key, label, items, max) => {
      const all = !!(c.accessAll && c.accessAll[key]);
      const shown = all ? items : items.slice(0, max);
      return {
        label, count: String(items.length), items: shown,
        hasMore: items.length > shown.length, moreLabel: 'Show all ' + items.length,
        more: () => { c.accessAll = Object.assign({}, c.accessAll, { [key]: true }); this.setState({}); },
        empty: !items.length
      };
    };
    const accessGroups = !acc || acc.loading || acc.error || acc.operator ? [] : [
      group('accounts', 'Accounts', acc.accounts.map(a => ({ name: a.name, sub: levelLabel[a.level] || '', href: appBase + 'accounts/' + a.id, go: link(appBase + 'accounts/' + a.id, () => this.openAccount(a.id)) })), 5),
      group('sites', 'Sites', acc.sites.map(x => ({ name: x.name, sub: ((h => h === x.name || h === 'www.' + x.name ? '' : h)((x.url || '').replace(/^https?:\/\//, '').replace(/\/$/, ''))), href: appBase + 'sites/' + x.id, go: link(appBase + 'sites/' + x.id, () => this.openSite(x.id)) })), 6),
      group('domains', 'Domains', acc.domains.map(d => ({ name: d.name, sub: '', href: appBase + 'domains/' + d.id, go: link(appBase + 'domains/' + d.id, () => this.openDomain(d.id)) })), 6)
    ];

    // Customer launcher: the dock owns the corner while it is open.
    const unreadTotal = isOp ? 0 : c.list.reduce((n, x) => n + (x.unread || 0), 0);
    const wView = s.chatWView || 'home';
    const first = (window.CC_BOOT && window.CC_BOOT.userFirstName) || '';
    const brand = (window.CC_BOOT && window.CC_BOOT.name) || 'Support';
    // Route changes move the operator in or out of the inbox: tell the relay.
    if (isOp && c.lastRoute !== s.route) { c.lastRoute = s.route; setTimeout(() => this.chatFocusTick(), 0); }
    const push = (isOp && c.push) || { state: 'unsupported' };
    // Customer Support page: arriving there opens the newest conversation.
    if (!isOp && c.lastCustRoute !== s.route) { c.lastCustRoute = s.route; if (s.route === 'support') setTimeout(() => { this.chatSupportPick(); this.chatMarkRead(); }, 0); }
    const supMsgs = conv && c.conv ? c.conv.messages.filter(m => m.kind === 'comment') : [];
    const supReplies = Math.max(0, supMsgs.length - 1);
    const fullDate = ts => ts ? new Date(ts * 1000).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '';
    const supSite = conv && conv.site_id && !isOp ? (this.FLEET || []).find(x => String(x.id) === String(conv.site_id)) : null;
    const pushLabel = { off: 'Turn on notifications', busy: 'Turning on…', on: 'Notifications on', denied: 'Notifications blocked', install: 'Get notifications' }[push.state] || '';

    return {
      showChat: isOp && s.route === 'chat',
      chatExit: () => this.setState({ route: this._chatPrevRoute && this._chatPrevRoute !== 'chat' ? this._chatPrevRoute : 'home' }),
      chatLiveLabel: live, chatLiveFg: liveFg,
      chatPushShow: push.state !== 'unsupported', chatPushLabel: pushLabel, chatPushIsOn: push.state === 'on', chatPushFg: push.state === 'on' ? 'var(--brand-ink)' : 'var(--ink)',
      chatPushGo: e => this.chatPushButton(e),
      // Phones show the list or the thread, not both.
      chatPhoneView: (c.id || c.newDraft) ? 'thread' : 'list',
      chatBack: () => this.chatOpen(0),
      chatQ: c.q, onChatQ: e => { c.q = e.target.value; clearTimeout(c.qTimer); c.qTimer = setTimeout(() => this.chatLoadList(), 300); this.setState({}); },
      chatFilters: filters, chatRows: rows,
      chatListLoading: c.loading && !c.list.length, chatEmpty: !c.loading && !c.list.length,
      chatEmptyText: c.q.trim() ? 'No conversations match.' : isOp ? (c.filter === 'waiting' ? 'Nobody is waiting. 🎉' : 'Nothing here.') : 'No conversations yet.',
      chatMore: c.more, chatLoadMore: () => this.chatLoadList(true, true),
      chatNoConv: !c.id && !c.newDraft, chatConvLoading: c.convLoading, chatHasErr: !!c.convErr, chatErr: c.convErr,
      chatHasConv: !!conv,
      chatSubject: conv ? conv.subject : '',
      chatSub: conv ? [conv.customer_email || (conv.guest ? 'Guest' : ''), conv.source === 'intercom' ? 'Imported from Intercom' : 'Started in chat', 'Opened ' + this.chatAgo(conv.created_at) + ' ago'].filter(Boolean).join(' · ') : '',
      chatStatusLabel: conv ? statusChip(conv).label : '', chatStatusBg: conv ? statusChip(conv).bg : '', chatStatusFg: conv ? statusChip(conv).fg : '',
      chatActions: actions,
      chatThreadRef: el => this.chatThreadRef(el),
      chatInputRef: el => this.chatInputRef(el),
      chatModes: isOp ? [['reply', 'Reply'], ['note', 'Internal note']].map(([v, l]) => ({
        label: l, go: () => { this._chatFocus = true; this.setState({ chatMode: v }); },
        bg: (s.chatMode || 'reply') === v ? (v === 'note' ? 'var(--ccc-note)' : 'var(--panel-2)') : 'transparent',
        fg: (s.chatMode || 'reply') === v ? (v === 'note' ? 'color-mix(in srgb,var(--warn) 80%,var(--ink))' : 'var(--ink)') : 'var(--ink-dim)'
      })) : [],
      chatBody: s.chatBody || '',
      onChatBody: e => { this.setState({ chatBody: e.target.value }); this.chatTyping(); },
      chatKey: e => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); this.chatSend(); } },
      chatSendGo: () => this.chatSend(),
      chatSendOpacity: (s.chatBody || '').trim() && !s.chatSending ? 1 : 0.45,
      chatBoxBg: note ? 'var(--ccc-note)' : 'var(--panel)', chatBoxRule: note ? 'var(--ccc-note-rule)' : 'var(--rule)',
      chatPlaceholder: note ? 'Write an internal note. Only staff can see it.' : isOp ? 'Reply to ' + custFirst : c.id ? 'Write a reply…' : 'How can we help?',
      chatHint: note ? 'Internal notes are never shown to the customer.' : isOp ? 'Enter sends, Shift+Enter for a new line.' + (conv && conv.guest ? ' This contact has no Manager login, so they won’t see replies here.' : '') : '',
      chatTypingShow: !!ty,
      // Operator sidebar
      chatCustName: conv ? (conv.customer_name || 'Guest') : '', chatCustEmail: conv ? (conv.customer_email || '') : '',
      chatCustInitials: conv ? this.chatInitials(conv.customer_name || conv.customer_email) : '', chatCustBg: conv ? this.chatColor(conv.customer_name || conv.customer_email) : '',
      chatCustMeta: conv ? (conv.guest ? 'Guest · no Manager login' : 'User #' + conv.customer_user_id) : '',
      chatHasSite: !!(conv && conv.site_id), chatSiteLabel: conv && conv.site_id ? ((acc && acc.sites && (acc.sites.find(x => x.id === conv.site_id) || {}).name) || 'Site #' + conv.site_id) : '',
      chatOpenSite: () => { if (conv && conv.site_id) this.openSite(conv.site_id); },
      chatAccessGroups: accessGroups,
      chatAccessLoading: !!(acc && acc.loading),
      chatAccessNote: !conv ? '' : !conv.customer_user_id ? 'Guest: no Manager login, so no access.' : acc && acc.error ? 'Could not load access.' : acc && acc.operator ? 'This person is an operator and can see everything.' : (acc && !acc.loading && !acc.accounts.length && !acc.sites.length && !acc.domains.length) ? 'No accounts, sites or domains.' : '',
      chatSideOpen: c.sideOpen ? '1' : '0',
      chatSideToggle: () => { c.sideOpen = !c.sideOpen; this.setState({}); },
      chatHistory: hist.map(x => ({ subject: x.subject, meta: statusChip(x).label + ' · ' + this.chatAgo(x.last_message_at), go: () => this.chatOpen(x.id) })),
      chatHasHistory: hist.length > 0, chatNoHistory: !!conv && hist.length === 0,
      // Customer widget
      chatWidgetShow: !isOp && !s.dockOpen && s.route !== 'support',
      chatWidgetOpen: !isOp && !!s.chatWidgetOpen && s.route !== 'support',
      // "View history": the Support page, on the conversation the panel had open.
      chatWHistory: () => this.setState({ route: 'support', chatWidgetOpen: false, chatWView: 'home' }),
      chatToggleWidget: () => { const open = !s.chatWidgetOpen; this.setState({ chatWidgetOpen: open }); if (open) { this.chatLoadList(true); setTimeout(() => this.chatMarkRead(), 0); } },
      chatLauncherUnread: String(unreadTotal), chatLauncherHasUnread: unreadTotal > 0,
      chatWHome: wView === 'home', chatWThread: wView === 'thread',
      chatWBack: wView === 'thread',
      chatWGoHome: () => { this.chatOpen(0); this.setState({ chatWView: 'home' }); this.chatLoadList(true); },
      chatWNew: () => { this.chatOpen(0); c.newDraft = true; this._chatFocus = true; this.setState({ chatWView: 'thread' }); },
      chatWTitle: wView === 'thread' && conv ? conv.subject : brand,
      chatWSub: c.online ? 'We’re online now' : 'We’ll reply by email if you leave',
      chatWOnlineBg: c.online ? 'var(--ok)' : 'var(--ink-dim)',
      chatWHello: 'Hi ' + (first || 'there') + ' 👋',
      chatWHasRows: rows.length > 0,
      chatWNewHint: c.newDraft && !c.id,
      chatWCanResolve: !!(conv && conv.status !== 'closed'),
      chatWResolve: () => this.chatPatch({ status: 'closed' }, 'Marked as resolved'),
      // Customer Support page
      supFilters: [['all', 'All'], ['open', 'Open'], ['closed', 'Resolved']].map(([v, l]) => ({
        label: l, bg: c.filter === v ? 'var(--paper)' : 'transparent', fg: c.filter === v ? 'var(--ink)' : 'var(--ink-dim)', shadow: c.filter === v ? 'var(--shadow-sm)' : 'none',
        go: () => { if (c.filter !== v) { c.filter = v; this.chatLoadList(); } }
      })),
      supNew: () => { this.chatOpen(0); c.newDraft = true; this._chatFocus = true; this.setState({}); },
      supNewDraft: !!(c.newDraft && !c.id), supComposer: !!conv || !!(c.newDraft && !c.id),
      supPick: !c.id && !c.newDraft && (c.loading || c.list.length > 0 || !!c.q.trim() || c.filter !== 'all'),
      supNone: !c.loading && !c.list.length && !c.q.trim() && c.filter === 'all' && !c.id && !c.newDraft,
      supLetter: conv ? (String(conv.subject || '?').trim().charAt(0).toUpperCase() || '?') : '', supLetterBg: conv ? this.chatColor(conv.subject || String(conv.id)) : '',
      supMeta: conv ? ['Started' + (supMsgs[0] ? ' by ' + (supMsgs[0].author_kind === 'customer' ? 'you' : (supMsgs[0].author_name || brand)) : '') + ' on ' + fullDate(conv.created_at), supReplies === 1 ? '1 reply' : supReplies + ' replies'].join(' · ') : '',
      supCanResolve: !!(conv && conv.status !== 'closed'),
      supHint: conv && conv.status === 'closed' ? 'This conversation is resolved. Replying reopens it.' : 'Enter sends, Shift+Enter for a new line.' + (c.online ? '' : ' If you leave, we’ll reply by email.'),
      supStarted: conv ? fullDate(conv.created_at) : '', supLast: conv ? fullDate(conv.last_message_at) : '',
      supHasSite: !!supSite, supSiteName: supSite ? supSite.name : '', supOpenSite: () => { if (supSite) this.openSite(supSite.id); },
      supOnlineLabel: c.online ? 'We’re online now' : 'Away right now', supOnlineSub: c.online ? 'Replies usually arrive here in minutes.' : 'Leave a message and we’ll reply here and by email.',
      supTotal: c.list.length ? (c.list.length + (c.more ? '+' : '') + (c.list.length === 1 ? ' conversation' : ' conversations')) : ''
    };
  }

});
