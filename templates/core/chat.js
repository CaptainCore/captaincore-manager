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
      list: [], counts: null, filter: cb.operator ? 'waiting' : (/\/support(\/|$)/.test(location.pathname) ? 'open' : 'all'), supFilter: 'open', q: '', more: false, loading: true, listSeq: 0,
      id: 0, conv: null, convLoading: false, convErr: '', typing: {}, online: false, history: {},
      ws: null, wsState: 'off', everLive: false, retry: 0, lastTyped: 0, readSent: {}, nearBottom: true, scrollPin: false,
      files: [], drag: 0
    };
    this.chatLoadList();
    if (cb.operator) this.chatPushInit();
    if (cb.operator) this.chatAlertInit();
    if (cb.operator) this.chatFocusInit();
    if (cb.live) this.chatConnect();
    else this._chatPoll = setInterval(() => { this.chatLoadList(true); if (c.id) this.chatLoadConv(c.id, true); }, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) this.chatMarkRead(); });
    this.chatKeyboardInit();
    return c;
  },

  // Phone keyboards. iOS keeps the page its full height when the keyboard
  // opens and slides it up, so the header and the top of a short thread go
  // out of sight. While a keyboard is up (the visual viewport is well short
  // of the layout one), html gets .cc-kb and the visible area as
  // --cc-vvtop/--cc-vvh; the chat screens pin to it (app.html), and a
  // reader at the bottom of the thread stays there.
  chatKeyboardInit() {
    const vv = window.visualViewport;
    if (!vv || this._chatKb) return;
    this._chatKb = true;
    const root = document.documentElement;
    const apply = () => {
      const kb = window.innerWidth <= 760 && root.clientHeight - vv.height > 120;
      root.classList.toggle('cc-kb', kb);
      if (kb) {
        root.style.setProperty('--cc-vvh', Math.round(vv.height) + 'px');
        root.style.setProperty('--cc-vvtop', Math.round(vv.offsetTop) + 'px');
      } else {
        root.style.removeProperty('--cc-vvh');
        root.style.removeProperty('--cc-vvtop');
      }
      const c = this._chat;
      if (c && c.nearBottom) requestAnimationFrame(() => document.querySelectorAll('.ccc-thread').forEach(el => { el.scrollTop = el.scrollHeight; }));
    };
    vv.addEventListener('resize', apply);
    vv.addEventListener('scroll', apply);
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
    this.chatDraftFlush();
    c.dftSeed = ''; c.dftRemote = false; c.logs = [];
    c.id = id; c.conv = null; c.convErr = ''; c.convLoading = !!id; c.scrollPin = true; c.newDraft = false; c.edit = null;
    this.chatClearFiles();
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
      const prevDraft = c.conv && c.conv.conversation.id === id ? c.conv.draft : undefined;
      c.conv = r;
      if (this.chatBoot().operator) this.chatDraftSync(r.draft || null, prevDraft);
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
  // Operators: start a conversation with a customer. The To picker searches
  // every non-admin user (GET /users, loaded once when first needed).
  chatNewForCustomer() {
    const c = this.chatInit();
    if (!c) return;
    this.chatOpen(0);
    c.newDraft = true; c.newTo = null; c.toQ = ''; c.newSite = ''; c.siteQ = ''; c.newSubject = '';
    this._chatToFocus = true;
    this.chatLoadUsers();
    this.setState({ chatBody: '', chatMode: 'reply' });
  },

  chatLoadUsers() {
    if (this._chatUsers !== undefined) return;
    this._chatUsers = null;
    this.api('/users/').then(r => {
      this._chatUsers = (Array.isArray(r) ? r : [])
        .filter(u => !(u.roles || []).includes('administrator'))
        .map(u => ({ id: parseInt(u.user_id, 10), name: this.decodeHtml ? this.decodeHtml(u.name || u.username || '') : (u.name || u.username || ''), email: String(u.email || '') }));
      this.setState({});
    }).catch(() => { this._chatUsers = undefined; this.setState({}); });
  },

  chatPickCustomer(u) {
    const c = this._chat;
    c.newTo = u; c.toQ = ''; c.newSite = ''; c.siteQ = '';
    this.chatLoadAccess({ customer_user_id: u.id });
    this._chatFocus = true;
    this.setState({});
  },

  chatToRef(el) {
    if (el && this._chatToFocus) { this._chatToFocus = false; setTimeout(() => el.focus(), 0); }
  },

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
    if (cb.operator) this.chatAlertSeen(c.conv.conversation.id);
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
    if (!c) return;
    c.logs = c.logs || [];
    if ((!body && !c.files.length && !c.logs.length) || s.chatSending) return;
    const note = cb.operator && s.chatMode === 'note';
    this.setState({ chatSending: true });
    const opNew = cb.operator && !c.id;
    if (opNew && !c.newTo) { this.setState({ chatSending: false }); this.toast('Choose who to message first.', { kind: 'info' }); return; }
    const fields = Object.assign({ body }, c.id ? { kind: note ? 'note' : 'comment' } : {}, c.id && c.logs.length ? { log_refs: c.logs.map(l => l.id + ':' + l.site_id).join(',') } : {}, !c.id && c.newSite ? { site_id: parseInt(c.newSite, 10) } : {},
      opNew ? { customer_user_id: c.newTo.id, subject: String(c.newSubject || '').trim() } : {});
    let payload = fields;
    if (c.files.length) {
      // Attachments go as multipart; the text rides along as fields.
      payload = new FormData();
      Object.keys(fields).forEach(k => payload.append(k, String(fields[k])));
      c.files.forEach(f => payload.append('files[]', f.file, f.name));
    }
    const req = c.id
      ? this.api('/chat/conversations/' + c.id + '/messages', { method: 'POST', body: payload })
      : this.api('/chat/conversations', { method: 'POST', body: payload });
    req.then(r => {
      if (!r || r.code) throw new Error((r && r.message) || 'Could not send');
      if (r.messages) {
        // A new conversation (a customer's, or one an operator started).
        c.id = r.conversation.id; c.newDraft = false; c.newSite = ''; c.newTo = null; c.newSubject = ''; c.toQ = '';
        c.conv = { conversation: r.conversation, messages: r.messages };
        this.chatMergeConv(r.conversation, true);
        this.chatOnMessage(r.messages[0]);
      } else {
        this.chatMergeConv(r.conversation);
        this.chatOnMessage(r.message);
      }
      c.scrollPin = true;
      this._chatClear = true;
      this.chatClearFiles();
      c.logs = [];
      clearTimeout(c.draftTimer); c.draftText = c.draftSent = '';
      // The server clears the draft this answered (Chat::clear_draft).
      clearTimeout(c.dftTimer); c.dftPending = null; c.dftSeed = ''; c.dftRemote = false;
      if (cb.operator && c.conv && c.conv.draft && (!note || c.conv.draft.kind === 'note')) {
        c.conv.draft = null;
        const lr = c.list.find(x => x.id === c.id); if (lr) lr.draft = '';
      }
      this.setState({ chatSending: false, chatBody: '', chatId: String(c.id) });
      this.chatMarkRead();
    }).catch(e => {
      this.setState({ chatSending: false });
      this.toast(e && e.message && e.message !== 'auth' ? e.message : 'Could not send the message', { kind: 'error' });
    });
  },

  // ── attachments (composer) ───────────────────────────────────────────
  // Files wait in c.files until the message is sent. They come from a drop
  // on the thread, a paste into the composer, or the paperclip.

  chatCanAttach() {
    const c = this._chat;
    if (!c || !(c.id || c.newDraft)) return false;
    const s = this.state;
    return this.chatBoot().operator ? s.route === 'chat' : (s.route === 'support' || (s.chatWidgetOpen && s.chatWView === 'thread'));
  },

  chatAddFiles(list) {
    const c = this._chat;
    const cb = this.chatBoot();
    if (!c || !list || !list.length) return;
    const maxFile = cb.maxFile || 10485760, maxFiles = cb.maxFiles || 10, maxPost = cb.maxPost || 0;
    const mb = n => (n / 1048576).toFixed(n < 10485760 ? 1 : 0).replace(/\.0$/, '') + ' MB';
    const skipped = [];
    let total = c.files.reduce((n, f) => n + f.size, 0);
    [...list].forEach(file => {
      if (c.files.length >= maxFiles) { skipped.push(file.name + ' (' + maxFiles + ' files max)'); return; }
      if (!file.size) { skipped.push(file.name + ' (empty)'); return; }
      if (file.size > maxFile) { skipped.push(file.name + ' (over ' + mb(maxFile) + ')'); return; }
      if (maxPost && total + file.size > maxPost - 65536) { skipped.push(file.name + ' (over ' + mb(maxPost) + ' in one message)'); return; }
      total += file.size;
      const isImg = /^image\/(png|jpe?g|gif|webp)$/.test(file.type);
      // A pasted screenshot is always "image.png"; give it a useful name.
      const name = file.name && file.name !== 'image.png' ? file.name : 'screenshot-' + new Date().toISOString().slice(0, 19).replace(/[T:]/g, '-') + '.png';
      c.files.push({ key: 'f' + Date.now() + Math.random().toString(36).slice(2, 6), file, name, size: file.size, isImg, url: isImg ? URL.createObjectURL(file) : '' });
    });
    if (skipped.length) this.toast('Not attached: ' + skipped.join(', '), { kind: 'error' });
    this._chatFocus = true;
    this.setState({});
  },

  chatRemoveFile(key) {
    const c = this._chat;
    const f = c.files.find(x => x.key === key);
    if (f && f.url) URL.revokeObjectURL(f.url);
    c.files = c.files.filter(x => x.key !== key);
    this.setState({});
  },

  chatClearFiles() {
    const c = this._chat;
    if (!c || !c.files.length) return;
    c.files.forEach(f => { if (f.url) URL.revokeObjectURL(f.url); });
    c.files = [];
  },

  chatPickFiles() {
    const input = document.createElement('input');
    input.type = 'file';
    input.multiple = true;
    input.addEventListener('change', () => this.chatAddFiles(input.files));
    input.click();
  },

  // ref for anything files can be dropped on (the thread pane, the panel).
  // A counter keeps the overlay steady while the drag crosses children.
  chatDropRef(el) {
    if (!el || el._cccDrop) return;
    el._cccDrop = true;
    const c = this._chat;
    const files = e => e.dataTransfer && [...(e.dataTransfer.types || [])].includes('Files');
    el.addEventListener('dragenter', e => { if (!files(e)) return; e.preventDefault(); if (!this.chatCanAttach()) return; if (c.drag++ === 0) this.setState({}); });
    el.addEventListener('dragover', e => { if (!files(e)) return; e.preventDefault(); e.dataTransfer.dropEffect = this.chatCanAttach() ? 'copy' : 'none'; });
    el.addEventListener('dragleave', e => { if (!files(e) || !c.drag) return; if (--c.drag === 0) this.setState({}); });
    el.addEventListener('drop', e => {
      if (!files(e)) return;
      e.preventDefault();
      c.drag = 0;
      if (this.chatCanAttach()) this.chatAddFiles(e.dataTransfer.files); else this.setState({});
    });
  },

  // Patch a conversation: the open one, or `id` (context menus on rows).
  chatPatch(patch, done, id) {
    const c = this._chat;
    id = id || (c && c.id);
    if (!c || !id) return;
    this.api('/chat/conversations/' + id, { method: 'PATCH', body: patch }).then(r => {
      if (!r || r.code) throw new Error((r && r.message) || 'Could not update');
      this.chatMergeConv(r.conversation);
      if (done) this.toast(done, { kind: 'success' });
      if (id === c.id) this.chatLoadConv(c.id, true);
      if (this.chatBoot().operator) this.chatLoadList(true);
    }).catch(e => this.toast(e && e.message !== 'auth' ? e.message : 'Could not update', { kind: 'error' }));
  },

  // Snooze choices for the open conversation, or `id`. `prefix` labels them
  // when they sit in a longer context menu.
  chatSnoozeEntries(id, prefix) {
    const at = (h, m, addDays) => { const d = new Date(); d.setDate(d.getDate() + addDays); d.setHours(h, m, 0, 0); return Math.floor(d.getTime() / 1000); };
    const nextMonday = () => { const d = new Date(); d.setDate(d.getDate() + ((8 - d.getDay()) % 7 || 7)); d.setHours(9, 0, 0, 0); return Math.floor(d.getTime() / 1000); };
    const snooze = (until, label) => () => this.chatPatch({ status: 'snoozed', snoozed_until: until }, 'Snoozed until ' + label, id);
    const p = prefix || '';
    return [
      { label: p + (p ? '1 hour' : 'In 1 hour'), act: snooze(Math.floor(Date.now() / 1000) + 3600, 'in an hour') },
      { label: p + (p ? 'until tomorrow, 9am' : 'Tomorrow, 9am'), act: snooze(at(9, 0, 1), 'tomorrow 9am') },
      { label: p + (p ? 'until Monday, 9am' : 'Next Monday, 9am'), act: snooze(nextMonday(), 'Monday 9am') },
      ...(p ? [] : [{ label: 'In a week', act: snooze(Math.floor(Date.now() / 1000) + 7 * 86400, 'next week') }])
    ];
  },

  chatSnoozeMenu(e) {
    this.openCtxMenu(e, this.chatSnoozeEntries());
  },

  // ── context menus ─────────────────────────────────────────────────────
  // Right-click anywhere in chat. Shift+right-click keeps the browser's own
  // menu (spell check, inspect).

  chatLink(id) { return location.origin + this.routeBase() + 'chat/' + id; },

  // A conversation: an inbox or Support row, the open one's header, or the
  // sidebar's other conversations.
  chatConvCtx(e, x) {
    if (!x || e.shiftKey) return;
    const c = this._chat;
    const isOp = this.chatBoot().operator;
    const open = x.id !== c.id;
    const items = [];
    if (open) items.push({ label: 'Open', act: () => { this.chatOpen(x.id); if (!isOp) this.setState({ chatWView: 'thread' }); } });
    items.push({ label: 'Open in new tab ↗', act: () => window.open(this.chatLink(x.id), '_blank') });
    if (!isOp) {
      items.push(x.status === 'closed'
        ? { label: 'Reopen', act: () => this.chatPatch({ status: 'open' }, 'Reopened', x.id) }
        : { label: 'Mark as resolved', act: () => this.chatPatch({ status: 'closed' }, 'Marked as resolved', x.id) });
      items.push({ label: 'Copy link', act: () => this.ctxCopy(this.chatLink(x.id), 'link') });
      return this.openCtxMenu(e, items);
    }
    if ((x.unread || 0) > 0) items.push({ label: 'Mark as read', act: () => this.chatMarkConv(x, false) });
    else if (x.waiting_since) items.push({ label: 'Mark as unread', act: () => this.chatMarkConv(x, true) });
    if (x.status === 'closed') items.push({ label: 'Reopen', act: () => this.chatPatch({ status: 'open' }, 'Reopened', x.id) });
    else items.push({ label: 'Close', act: () => this.chatPatch({ status: 'closed' }, 'Closed', x.id) });
    if (x.status === 'snoozed') items.push({ label: 'Unsnooze', act: () => this.chatPatch({ status: 'open' }, 'Back in the inbox', x.id) });
    else if (x.status !== 'closed') items.push(...this.chatSnoozeEntries(x.id, 'Snooze '));
    if (x.site_id) items.push({ label: 'Open site', act: () => this.openSite(String(x.site_id)) });
    if (x.customer_user_id) {
      const first = String(x.customer_name || 'customer').split(' ')[0];
      items.push({ label: 'New message to ' + first, act: () => this.chatNewTo(x) });
    }
    if (x.customer_email) items.push({ label: 'Copy email', act: () => this.ctxCopy(x.customer_email, 'email') });
    items.push({ label: 'Copy link', act: () => this.ctxCopy(this.chatLink(x.id), 'link') });
    this.openCtxMenu(e, items);
  },

  // Operator Mark as read / unread from a row. The open conversation marks
  // itself read again while on screen, so marking it unread leaves it.
  chatMarkConv(x, unread) {
    const c = this._chat;
    this.api('/chat/conversations/' + x.id + '/read', { method: 'POST', body: unread ? { unread: true } : { message_id: 0 } }).then(r => {
      if (r && r.code) throw new Error(r.message);
      if (unread && x.id === c.id) this.chatOpen(0);
      this.chatLoadList(true);
      this.toast(unread ? 'Marked as unread' : 'Marked as read', { kind: 'success' });
    }).catch(err => this.toast(err && err.message && err.message !== 'auth' ? err.message : 'Could not update', { kind: 'error' }));
  },

  // New message panel, already addressed to this conversation's customer.
  chatNewTo(x) {
    this.chatNewForCustomer();
    this.chatPickCustomer({ id: Number(x.customer_user_id), name: x.customer_name || x.customer_email || '', email: x.customer_email || '' });
  },

  // The customer card in the operator sidebar.
  chatCustCtx(e) {
    const c = this._chat;
    const conv = c && c.conv && c.conv.conversation;
    if (!conv || e.shiftKey) return;
    const acc = conv.customer_user_id && c.access ? c.access[conv.customer_user_id] : null;
    const first = String(conv.customer_name || 'customer').split(' ')[0];
    const items = [];
    if (conv.customer_user_id) items.push({ label: 'New message to ' + first, act: () => this.chatNewTo(conv) });
    if (acc && acc.switch_url) items.push({ label: 'Access as ' + first + ' ↗', act: () => { window.location.href = acc.switch_url; } });
    if (conv.customer_email) items.push({ label: 'Copy email', act: () => this.ctxCopy(conv.customer_email, 'email') });
    if (conv.customer_name) items.push({ label: 'Copy name', act: () => this.ctxCopy(conv.customer_name, 'name') });
    if (conv.customer_user_id) items.push({ label: 'Copy user ID', act: () => this.ctxCopy(String(conv.customer_user_id), 'user ID') });
    if (items.length) this.openCtxMenu(e, items);
  },

  // A site, account or domain under "Can access".
  chatAccessCtx(e, kind, x, href) {
    if (e.shiftKey) return;
    const tab = { label: 'Open in new tab ↗', act: () => window.open(location.origin + href, '_blank') };
    if (kind === 'sites') {
      const f = (this.FLEET || []).find(s => String(s.id) === String(x.id));
      const entries = this.siteCtxEntries(f || { id: String(x.id), name: x.name, envs: '' });
      entries.splice(1, 0, tab);
      return this.openCtxMenu(e, entries);
    }
    if (kind === 'accounts') return this.openCtxMenu(e, [
      { label: 'Open account', act: () => this.openAccount(x.id) }, tab,
      { label: 'Copy account name', act: () => this.ctxCopy(x.name, 'account name') }
    ]);
    this.openCtxMenu(e, [
      { label: 'Open domain', act: () => this.openDomain(x.id) }, tab,
      { label: 'Visit ' + x.name + ' ↗', act: () => window.open('https://' + x.name, '_blank') },
      { label: 'Copy domain', act: () => this.ctxCopy(x.name, 'domain') }
    ]);
  },

  // Anything in a thread: a message or note, an image or file, a link, or a
  // text selection. The thread is plain HTML, so one listener covers it.
  chatThreadCtx(e, el) {
    if (e.shiftKey) return;
    const c = this._chat;
    const conv = c && c.conv && c.conv.conversation;
    if (!conv) return;
    const isOp = this.chatBoot().operator;
    const row = e.target.closest('[data-mid]');
    const m = row ? c.conv.messages.find(x => x.id === +row.dataset.mid) : null;
    const items = [];
    const sel = String((window.getSelection && window.getSelection()) || '').trim();
    if (sel && el.contains(window.getSelection().anchorNode)) items.push({ label: 'Copy selection', act: () => this.ctxCopy(sel, 'selection') });
    const vid = e.target.closest('.ccc-vid');
    const file = vid || e.target.closest('a[href*="/chat/attachments/"]');
    const link = !file && e.target.closest('a[href]');
    if (file) {
      const href = vid ? vid.dataset.url : file.href;
      if (vid) {
        const v = vid.querySelector('video');
        items.push({ label: 'Play larger', act: () => this.chatMediaOpen(el, vid) });
        items.push({ label: v.paused ? 'Play' : 'Pause', act: () => { if (v.paused) v.play().catch(() => {}); else v.pause(); } });
      }
      if (file.classList.contains('ccc-img')) items.push({ label: 'View image', act: () => this.chatMediaOpen(el, file) });
      items.push({ label: 'Open in new tab ↗', act: () => window.open(href, '_blank') });
      items.push({ label: 'Download', act: () => { window.location.href = href + (href.indexOf('?') > -1 ? '&' : '?') + 'download=1'; } });
    } else if (link) {
      items.push({ label: 'Open link in new tab ↗', act: () => window.open(link.href, '_blank', 'noopener') });
      items.push({ label: 'Copy link address', act: () => this.ctxCopy(link.href, 'link') });
    }
    if (m) {
      if (m.body) items.push({ label: m.kind === 'note' ? 'Copy note' : m.kind === 'event' ? 'Copy event' : 'Copy message', act: () => this.ctxCopy(m.body, 'text') });
      if (m.body && m.kind !== 'event' && this.chatComposer()) items.push({ label: 'Quote in reply', act: () => this.chatQuote(m.body) });
      if (this.chatCanEdit(m)) items.push({ label: m.kind === 'note' ? 'Edit note' : 'Edit message', act: () => this.chatEditStart(m.id) });
      if (isOp && m.edited_at) items.push({ label: 'Show edit history', act: () => this.chatHistory(m.id) });
      const opener = row.querySelector('[data-react-open]');
      if (opener && m.kind !== 'event') items.push({ label: 'Add reaction…', act: () => this.chatReactPicker(opener, m.id) });
      const orig = isOp && row.querySelector('details.ccc-orig');
      if (orig) items.push({ label: orig.open ? 'Hide original email' : 'Show original email', act: () => { orig.open = !orig.open; } });
      if (isOp && m.email_from) items.push({ label: 'Copy sender address', act: () => this.ctxCopy(m.email_from, 'address') });
    }
    const logCard = e.target.closest('.ccc-log');
    if (logCard && logCard.dataset.url) items.push({ label: 'Open site timeline ↗', act: () => window.open(logCard.dataset.url, '_blank') });
    if (isOp && this.chatComposer()) items.push({ label: 'Link a log…', act: () => this.chatLogPicker(document.querySelector('[data-log-btn]')) });
    items.push({ label: 'Copy link to conversation', act: () => this.ctxCopy(this.chatLink(conv.id), 'link') });
    this.openCtxMenu(e, items);
  },

  // The composer on screen (inbox, Support page or corner panel), or null.
  chatComposer() {
    return [...document.querySelectorAll('textarea')].find(t => t._cccPaste && t.offsetParent !== null) || null;
  },

  chatQuote(body) {
    const t = this.chatComposer();
    if (!t) return;
    const quote = String(body).split('\n').map(l => '> ' + l).join('\n') + '\n\n';
    const before = t.value && !/\n\n$/.test(t.value) ? t.value.replace(/\n*$/, '') + '\n\n' : t.value;
    t.value = before + quote;
    this.setState({ chatBody: t.value });
    t.focus();
    t.setSelectionRange(t.value.length, t.value.length);
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
          this.chatFallbackPoll(false);
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
        this.chatFallbackPoll(true);
        this.setState({});
        // 4001 = token rejected or expired: fetch a new one right away.
        const delay = e.code === 4001 ? 500 : Math.min(30000, 1000 * Math.pow(2, c.retry++));
        c.reconnectTimer = setTimeout(() => this.chatConnect(), delay);
      };
    }).catch(() => {
      c.wsState = 'off';
      this.chatFallbackPoll(true);
      this.setState({});
      c.reconnectTimer = setTimeout(() => this.chatConnect(), Math.min(60000, 2000 * Math.pow(2, c.retry++)));
    });
  },

  // While the relay is unreachable, refresh from the Manager every 30
  // seconds the way a site without a relay does, so nothing waits for the
  // socket to come back. Stops once it is live again.
  chatFallbackPoll(on) {
    const c = this._chat;
    if (!c) return;
    if (on && !c.fallbackTimer) {
      c.fallbackTimer = setInterval(() => { this.chatLoadList(true); if (c.id) this.chatLoadConv(c.id, true); }, 30000);
    } else if (!on && c.fallbackTimer) {
      clearInterval(c.fallbackTimer);
      c.fallbackTimer = null;
    }
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
    } else if (ev.type === 'draft.updated') {
      const lr = c.list.find(x => x.id === ev.conversation_id);
      if (lr) lr.draft = ev.draft ? ev.draft.kind : '';
      if (cb.operator && c.conv && c.conv.conversation.id === ev.conversation_id) this.chatDraftSync(ev.draft || null, c.conv.draft);
    } else if (ev.type === 'message.updated') {
      if (ev.conversation) this.chatMergeConv(ev.conversation);
      if (ev.message) this.chatAfterEdit(ev.message);
    } else if (ev.type === 'message.reacted') {
      const m = c.conv && c.conv.conversation.id === ev.conversation_id && c.conv.messages.find(x => x.id === ev.message_id);
      if (m) m.reactions = ev.reactions || [];
    } else if (ev.type === 'typing') {
      const u = ev.user || {};
      if (u.id === cb.userId) return;
      // A customer's typing reaches every operator; show it only on that
      // customer's own conversation.
      if (cb.operator && !u.operator) {
        const conv = c.list.find(x => x.id === ev.conversation_id) || (c.conv && c.conv.conversation.id === ev.conversation_id ? c.conv.conversation : null);
        if (!conv || conv.customer_user_id !== u.id) return;
      }
      // A customer's draft preview carries text ('' once they clear it).
      const hasText = typeof ev.text === 'string';
      if (hasText && !ev.text.trim()) delete c.typing[ev.conversation_id];
      else c.typing[ev.conversation_id] = { name: u.name || 'Someone', until: Date.now() + (hasText ? 8000 : 4000), text: hasText ? ev.text : '' };
      if (c.conv && c.conv.conversation.id === ev.conversation_id && c.nearBottom) c.scrollPin = true;
      clearTimeout(c.typingTimer);
      c.typingTimer = setTimeout(() => this.setState({}), (hasText ? 8000 : 4000) + 100);
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
    // Grok Bot's triage note marks the row; the customer's next message clears it.
    if (row && cb.operator && m.via === 'bot' && m.kind === 'note') row.triaged = true;
    if (row && cb.operator && m.kind === 'comment' && m.author_kind === 'customer') row.triaged = false;
    if (row && m.kind === 'comment') {
      if (row._lastSeen !== m.id) {
        row._lastSeen = m.id;
        row.preview = String(m.body || '').slice(0, 200) || ((m.logs || []).length ? '📋 Shared a log' : (m.attachments || []).length ? '📎 Sent a file' : '');
        const onScreen = viewing && !document.hidden && (cb.operator ? this.state.route === 'chat' : this.chatCustomerViewing());
        if (!mine && !onScreen) row.unread = (row.unread || 0) + 1;
      }
    }
    if (cb.operator && m.kind === 'comment' && !m.internal) {
      const onScreen = viewing && !document.hidden && this.state.route === 'chat';
      if (m.author_kind === 'customer' && !onScreen) this.chatAlert(m.conversation_id);
      else if (m.author_kind === 'operator') this.chatAlertSeen(m.conversation_id); // answered, here or elsewhere
    }
    if (viewing) setTimeout(() => this.chatMarkRead(), 0);
  },

  chatTyping(text) {
    const c = this._chat;
    const cb = this.chatBoot();
    if (!c || !c.ws || c.ws.readyState !== 1 || !c.id) return;
    // Customers send their draft so far, which operators see as a preview
    // (the relay passes a customer's text to operators only). At most one
    // frame per 600ms, the last one trailing so the final text arrives.
    if (!cb.operator) {
      c.draftText = String(text || '').slice(-1000);
      clearTimeout(c.draftTimer);
      const send = () => {
        if (!c.ws || c.ws.readyState !== 1 || !c.id || c.draftText === c.draftSent) return;
        c.draftSent = c.draftText; c.draftAt = Date.now();
        c.ws.send(JSON.stringify({ type: 'typing', conversation_id: c.id, text: c.draftText }));
      };
      const wait = 600 - (Date.now() - (c.draftAt || 0));
      if (wait <= 0) send(); else c.draftTimer = setTimeout(send, wait);
      return;
    }
    if (Date.now() - c.lastTyped < 2000) return;
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

  // The bell in the inbox's top bar: device notifications and the sound.
  chatPushButton(e) {
    const c = this._chat;
    const p = c && c.push;
    if (!p) return;
    const items = [];
    if (p.state === 'off') items.push({ label: 'Turn on notifications', act: () => this.chatPushOn() });
    if (p.state === 'install') items.push({ label: 'Get notifications on iPhone', act: () => this.toast('To get notifications on this iPhone, tap Share, then Add to Home Screen, and open the app from there.', { kind: 'info' }) });
    if (p.state === 'denied') items.push({ label: 'Notifications are blocked', act: () => this.toast('Notifications are blocked for this site. Allow them in your browser or iOS Settings, then try again.', { kind: 'info' }) });
    if (p.state === 'on') {
      items.push({ label: 'Send a test notification', act: () => this.api('/chat/push/test', { method: 'POST' }).then(r => this.toast(r && r.sent ? 'Test sent.' : 'No device took the test. Try turning notifications off and on.', { kind: r && r.sent ? 'success' : 'info' })).catch(() => this.toast('Could not send a test.', { kind: 'error' })) });
      items.push({ label: 'Turn off notifications', act: () => this.chatPushOff() });
    }
    const a = c.alert;
    if (a) items.push({ label: a.sound ? 'Mute the chime' : 'Turn on the chime', act: () => this.chatSoundToggle() });
    if (items.length) this.openCtxMenu(e, items);
  },

  chatPushOn() {
    const c = this._chat;
    const p = c.push;
    const cfg = this.chatBoot().push;
    if (p.state === 'busy') return;
    if (!p.reg) { this.toast('Notifications could not start in this browser. Reload the page and try again.', { kind: 'error' }); return; }
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

  // ── alerts (operators) ────────────────────────────────────────────────
  // A customer message that arrives while its conversation is not on
  // screen (another tab, another page, another conversation) plays a chime
  // and counts in the tab: "(2) Title" and a badge on the favicon. The
  // count is conversations, and one clears when it is opened on screen or
  // an operator answers it. The sound is per browser (localStorage).

  chatAlertInit() {
    const c = this._chat;
    let sound = true;
    try { sound = localStorage.getItem('cc-chat-sound') !== 'off'; } catch (x) {}
    c.alert = { ids: new Set(), sound, ctx: null, title: document.title, icon: null, iconImg: null };
    // Browsers only let a page play sound after a click or key press.
    const unlock = () => { this.chatAudio(); document.removeEventListener('pointerdown', unlock, true); document.removeEventListener('keydown', unlock, true); };
    document.addEventListener('pointerdown', unlock, true);
    document.addEventListener('keydown', unlock, true);
  },

  chatAudio() {
    const a = this._chat && this._chat.alert;
    if (!a) return null;
    try {
      if (!a.ctx) { const AC = window.AudioContext || window.webkitAudioContext; if (!AC) return null; a.ctx = new AC(); }
      if (a.ctx.state === 'suspended') a.ctx.resume().catch(() => {});
    } catch (x) { return null; }
    return a.ctx;
  },

  chatChime() {
    const a = this._chat && this._chat.alert;
    if (!a || !a.sound) return;
    const ctx = this.chatAudio();
    if (!ctx || ctx.state !== 'running') return;
    const now = ctx.currentTime;
    [[880, 0], [1318.5, 0.12]].forEach(([freq, at]) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.value = freq;
      gain.gain.setValueAtTime(0.0001, now + at);
      gain.gain.exponentialRampToValueAtTime(0.18, now + at + 0.015);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + at + 0.45);
      osc.connect(gain).connect(ctx.destination);
      osc.start(now + at);
      osc.stop(now + at + 0.5);
    });
  },

  chatSoundToggle() {
    const a = this._chat.alert;
    a.sound = !a.sound;
    try { localStorage.setItem('cc-chat-sound', a.sound ? 'on' : 'off'); } catch (x) {}
    if (a.sound) this.chatChime();
    this.toast(a.sound ? 'The chime is on for new messages.' : 'The chime is muted.', { kind: 'success' });
    this.setState({});
  },

  chatAlert(conversationId) {
    const a = this._chat && this._chat.alert;
    if (!a) return;
    a.ids.add(conversationId);
    this.chatChime();
    this.chatAlertPaint();
  },

  chatAlertSeen(conversationId) {
    const a = this._chat && this._chat.alert;
    if (!a || !a.ids.delete(conversationId)) return;
    this.chatAlertPaint();
  },

  chatAlertPaint() {
    const a = this._chat.alert;
    const n = a.ids.size;
    document.title = n ? '(' + n + ') ' + a.title : a.title;
    let link = document.getElementById('cc-favicon');
    if (!link) return;
    if (a.icon === null) a.icon = link.href;
    if (!n) { link.href = a.icon; return; }
    const draw = img => {
      try {
        const cv = document.createElement('canvas');
        cv.width = cv.height = 64;
        const g = cv.getContext('2d');
        if (img) g.drawImage(img, 0, 0, 64, 64);
        g.beginPath(); g.arc(44, 20, 20, 0, Math.PI * 2); g.fillStyle = '#e5484d'; g.fill();
        g.fillStyle = '#fff'; g.font = 'bold ' + (n > 9 ? 22 : 28) + 'px system-ui, sans-serif'; g.textAlign = 'center'; g.textBaseline = 'middle';
        g.fillText(n > 9 ? '9+' : String(n), 44, 21);
        link.href = cv.toDataURL('image/png');
      } catch (x) { if (img) draw(null); } // an icon from another origin taints the canvas: badge only
    };
    if (a.iconImg) { draw(a.iconImg.complete && a.iconImg.naturalWidth ? a.iconImg : null); return; }
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => { a.iconImg = img; draw(img); };
    img.onerror = () => { a.iconImg = img; draw(null); };
    img.src = a.icon;
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
  // Message text as HTML: basic markdown on escaped text, so nothing a
  // person types becomes markup except what these rules make. Blocks:
  // ``` fences, > quotes, - / * and 1. lists, paragraphs with line breaks.
  // Inline: `code`, [text](https://…), bare links, **bold**, *italic* or
  // _italic_, ~~strike~~. Chat::markdown_html renders the same for email.
  chatText(t) {
    const keep = [];
    const hold = html => '\u0000' + (keep.push(html) - 1) + '\u0000';
    const link = (href, text) => hold('<a href="' + href + '" target="_blank" rel="noopener noreferrer">' + text + '</a>');
    const inline = x => x
      .replace(/`([^`\n]+)`/g, (m, code) => hold('<code>' + code + '</code>'))
      .replace(/\[([^\]\n]+)\]\((https?:\/\/[^\s)<]+)\)/g, (m, text, href) => link(href, text))
      .replace(/https?:\/\/[^\s<\u0000]+[^\s<.,;:!?)\]'"&\u0000]/g, u => link(u, u))
      .replace(/\*\*(?=\S)([^*\n]*?\S)\*\*/g, '<strong>$1</strong>')
      .replace(/~~(?=\S)([^~\n]*?\S)~~/g, '<del>$1</del>')
      .replace(/(^|[^\w*])\*(?=\S)([^*\n]*?\S)\*(?![\w*])/g, '$1<em>$2</em>')
      .replace(/(^|[^\w])_(?=\S)([^_\n]*?\S)_(?!\w)/g, '$1<em>$2</em>');
    const lines = this.chatEsc(t).split('\n');
    const out = [];
    let para = [];
    const flush = () => { if (para.length) { out.push('<p>' + para.map(inline).join('<br>') + '</p>'); para = []; } };
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      if (/^\s*```/.test(line)) {
        flush();
        const code = [];
        for (i++; i < lines.length && !/^\s*```/.test(lines[i]); i++) code.push(lines[i]);
        out.push('<pre><code>' + code.join('\n') + '</code></pre>');
        continue;
      }
      const block = (re, wrap, item) => {
        if (!re.test(line)) return false;
        flush();
        const items = [];
        for (; i < lines.length && re.test(lines[i]); i++) items.push(inline(lines[i].replace(re, '')));
        i--;
        out.push(wrap[0] + items.map(item).join('') + wrap[1]);
        return true;
      };
      if (block(/^\s*&gt;\s?/, ['<blockquote>', '</blockquote>'], (x, n) => (n ? '<br>' : '') + x)) continue;
      if (block(/^\s*[-*]\s+/, ['<ul>', '</ul>'], x => '<li>' + x + '</li>')) continue;
      if (block(/^\s*\d+[.)]\s+/, ['<ol>', '</ol>'], x => '<li>' + x + '</li>')) continue;
      if (!line.trim()) { flush(); continue; }
      para.push(line);
    }
    flush();
    return out.join('').replace(/\u0000(\d+)\u0000/g, (m, n) => keep[n]);
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
      const chip = '<a class="ccc-file" href="' + url + '" download><span>' + (f.video ? '🎬' : '📄') + '</span>' + this.chatEsc(f.name) + ' <em>' + h(f.size || 0) + '</em></a>';
      if (f.video) {
        // #t= makes Safari paint the first frame as the poster. A video the
        // browser can't play turns back into the download chip (chatThreadRef).
        return '<div class="ccc-vid" data-aid="' + f.id + '" data-url="' + url + '" data-name="' + this.chatEsc(f.name) + '">' +
          '<video src="' + url + '#t=0.1" controls preload="metadata" playsinline title="' + this.chatEsc(f.name) + '"></video>' +
          '<button type="button" class="ccc-vid-max" data-vid-max title="Play larger" aria-label="Play ' + this.chatEsc(f.name) + ' larger"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h5v5M20 4l-6 6M9 20H4v-5M4 20l6-6"></path></svg></button>' +
          '<template>' + chip + '</template></div>';
      }
      return f.inline
        ? '<a class="ccc-img" href="' + url + '" target="_blank" rel="noopener"><img src="' + url + '" alt="' + this.chatEsc(f.name) + '" loading="lazy"></a>'
        : chip;
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
    // "by email", plus the sending address for operators when it isn't the
    // customer's own (their mail forwards to it; ChatNotify::receive).
    const viaTag = m => {
      if (m.via !== 'email') return '';
      const from = String(m.email_from || '').toLowerCase();
      const fwd = cb.operator && m.author_kind === 'customer' && from && from !== String(conv.customer_email || '').toLowerCase();
      return fwd
        ? '<span class="ccc-via" title="' + esc('Sent from ' + from + ', which receives mail for ' + (conv.customer_email || 'the customer') + '. It replied to our email directly, so it counts as theirs.') + '">by email from ' + esc(from) + '</span>'
        : '<span class="ccc-via">by email</span>';
    };
    const flat = cb.operator || this.state.route === 'support';
    let out = '';
    let lastDay = '';
    let prev = null;
    if (conv.source === 'intercom') out += '<div class="ccc-imported">Imported from Intercom. Replies here continue the same conversation.</div>';
    // A customer message without its own picture (imported, guest) uses the
    // conversation's customer.
    const avatarOf = m => m.author_avatar || (m.author_kind === 'customer' ? conv.customer_avatar : '') || '';
    // The text, or the inline editor while this message is being edited.
    const body = (m, cls) => c.edit && c.edit.id === m.id ? this.chatEditHtml() : (m.body ? '<div class="' + cls + '">' + this.chatText(m.body) + '</div>' : '');
    // "edited": operators open the earlier versions from it.
    const edited = m => {
      if (!m.edited_at) return '';
      const tip = esc('Edited ' + day(m.edited_at) + ', ' + time(m.edited_at) + (m.edited_by ? ' by ' + m.edited_by : '') + (cb.operator ? '. Click for the earlier versions.' : ''));
      return cb.operator ? '<button type="button" class="ccc-edited" data-history="' + m.id + '" title="' + tip + '">edited</button>' : '<span class="ccc-edited" title="' + tip + '">edited</span>';
    };
    msgs.forEach(m => {
      const d = day(m.created_at);
      if (d !== lastDay) { out += '<div class="ccc-day">' + esc(d) + '</div>'; lastDay = d; prev = null; }
      const name = m.author_name || (m.author_kind === 'operator' ? (window.CC_BOOT && window.CC_BOOT.name) || 'Support' : conv.customer_name || 'Customer');
      if (m.kind === 'event') {
        out += '<div data-mid="' + m.id + '" class="ccc-sys' + (m.internal ? ' ccc-sys-int' : '') + '">' + esc(m.body) + ' · ' + esc(time(m.created_at)) + '</div>';
        prev = null;
        return;
      }
      if (m.kind === 'note') {
        out += '<div data-mid="' + m.id + '" class="ccc-note">' + this.chatEditBtn(m) + this.chatReactBtn(m) + '<div class="ccc-nh">Internal note · ' + esc(name) + (m.via === 'bot' ? ' <span class="ccc-bot">Bot</span>' : '') + ' · ' + esc(time(m.created_at)) + (m.edited_at ? ' · ' + edited(m) : '') + '</div>' + body(m, 'ccc-mb') + this.chatLogsHtml(m) + this.chatFilesHtml(m.attachments) + orig(m) + this.chatReactsHtml(m) + '</div>';
        prev = null;
        return;
      }
      if (flat) {
        const cont = prev && prev.author_kind === m.author_kind && prev.author_name === m.author_name && m.created_at - prev.created_at < 300;
        out += '<div data-mid="' + m.id + '" class="ccc-msg' + (cont ? ' ccc-cont' : '') + '">' +
          '<span class="ccc-av" style="background:' + this.chatColor(name) + '">' + esc(this.chatInitials(name)) + this.chatAvImg(avatarOf(m)) + '</span><div class="ccc-mbody">' +
          (cont ? '' : '<div class="ccc-mh"><b>' + esc(name) + '</b>' + (m.author_kind === 'operator' ? '<span class="ccc-staff">Staff</span>' : '') + viaTag(m) + '<span class="ccc-t">' + esc(time(m.created_at)) + '</span>' + edited(m) + '</div>') +
          body(m, 'ccc-mb') + (cont && m.edited_at ? '<div class="ccc-edline">' + edited(m) + '</div>' : '') + this.chatLogsHtml(m) + this.chatFilesHtml(m.attachments) + orig(m) + this.chatReactsHtml(m) + '</div>' + this.chatEditBtn(m) + this.chatReactBtn(m) + '</div>';
      } else {
        const me = m.author_kind === 'customer';
        const cont = prev && prev.author_kind === m.author_kind && m.created_at - prev.created_at < 300;
        out += '<div data-mid="' + m.id + '" class="ccc-bub ' + (me ? 'ccc-me' : 'ccc-them') + '">' +
          (me ? '' : '<span class="ccc-av ccc-av-sm" style="background:' + this.chatColor(name) + ';visibility:' + (cont ? 'hidden' : 'visible') + '">' + esc(this.chatInitials(name)) + this.chatAvImg(avatarOf(m)) + '</span>') +
          (me ? this.chatEditBtn(m) + this.chatReactBtn(m) : '') +
          '<div class="ccc-bwrap' + (c.edit && c.edit.id === m.id ? ' ccc-bwrap-edit' : '') + '">' + body(m, 'ccc-bb') + this.chatLogsHtml(m) + this.chatFilesHtml(m.attachments) + this.chatReactsHtml(m) + '</div>' + (me ? '' : this.chatReactBtn(m)) + '</div>' +
          '<div class="ccc-bmeta' + (me ? ' ccc-r' : '') + '">' + (me ? '' : esc(name) + ' · ') + esc(time(m.created_at)) + (m.edited_at ? ' · ' + edited(m) : '') + '</div>';
      }
      prev = m;
    });
    // "Seen" receipt under the last operator reply (operators only).
    if (cb.operator && conv.customer_user_id) {
      const lastOp = [...msgs].reverse().find(m => m.kind === 'comment' && m.author_kind === 'operator');
      if (lastOp && !lastOp.imported && (c.conv.customer_last_read_message_id || 0) >= lastOp.id) out += '<div class="ccc-seen">Seen by ' + esc(String(conv.customer_name || 'the customer').split(' ')[0]) + '</div>';
    }
    const ty = c.typing[conv.id];
    if (ty && ty.until > Date.now() && ty.text && cb.operator) {
      // The customer's draft as they write it (operators only).
      out += '<div class="ccc-msg ccc-draft"><span class="ccc-av" style="background:' + this.chatColor(ty.name) + '">' + esc(this.chatInitials(ty.name)) + this.chatAvImg(conv.customer_avatar) + '</span><div class="ccc-mbody">' +
        '<div class="ccc-mh"><b>' + esc(ty.name) + '</b><span class="ccc-typing ccc-typing-in"><i></i><i></i><i></i> typing</span></div>' +
        '<div class="ccc-mb">' + this.chatText(ty.text) + '</div></div></div>';
    } else if (ty && ty.until > Date.now()) out += '<div class="ccc-typing"><i></i><i></i><i></i> ' + esc(ty.name) + ' is typing</div>';
    if (!msgs.length) out += '<div class="ccc-empty">No messages yet.</div>';
    return out;
  },

  // Gravatar over the initials. A URL that 404s (no picture) is remembered
  // so it is not asked for again this session; the initials show through.
  chatAvUrl(url) {
    return url && !(this._avFail && this._avFail.has(url)) ? url : '';
  },

  chatAvImg(url) {
    const u = this.chatAvUrl(url);
    return u ? '<img class="ccc-avimg" src="' + u.replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '" alt="" loading="lazy" referrerpolicy="no-referrer">' : '';
  },

  chatAvRef(el) {
    if (!el || el._ccAv) return;
    el._ccAv = true;
    // Hide, never remove: template-managed nodes must stay where the
    // runtime put them. The next render leaves the failed URL out.
    const fail = () => {
      (this._avFail = this._avFail || new Set()).add(el.getAttribute('src'));
      el.style.display = 'none';
      clearTimeout(this._avRerender); this._avRerender = setTimeout(() => this.setState({}), 50);
    };
    el.addEventListener('error', fail);
    if (el.complete && el.naturalWidth === 0 && el.getAttribute('src')) fail();
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
      el.addEventListener('contextmenu', e => this.chatThreadCtx(e, el));
      // The inline editor: Enter saves, Shift+Enter is a new line, Esc
      // cancels (and stops here, so the page doesn't close dialogs too).
      el.addEventListener('keydown', e => {
        const ta = e.target.classList && e.target.classList.contains('ccc-edit-ta') ? e.target : null;
        if (!ta) return;
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); this.chatEditCancel(); }
        else if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); this.chatEditSave(ta.value); }
      });
      el.addEventListener('input', e => { if (e.target.classList && e.target.classList.contains('ccc-edit-ta')) this.chatGrow(e.target, 320); });
      // Images open in the lightbox; modified clicks still open a new tab.
      el.addEventListener('click', e => {
        const chip = e.target.closest && e.target.closest('[data-react]');
        if (chip) { e.preventDefault(); this.chatReact(+chip.dataset.mid, chip.dataset.react, chip.getAttribute('aria-pressed') !== 'true'); return; }
        const opener = e.target.closest && e.target.closest('[data-react-open]');
        if (opener) { e.preventDefault(); this.chatReactPicker(opener, +opener.dataset.mid); return; }
        const more = e.target.closest && e.target.closest('[data-log-more]');
        if (more) { e.preventDefault(); c.logOpen = c.logOpen || {}; c.logOpen[more.dataset.logMore] = !c.logOpen[more.dataset.logMore]; this.setState({}); return; }
        const ed = e.target.closest && e.target.closest('[data-edit-open],[data-edit-save],[data-edit-cancel],[data-history]');
        if (ed) {
          e.preventDefault();
          if (ed.hasAttribute('data-edit-open')) this.chatEditStart(+ed.dataset.mid);
          else if (ed.hasAttribute('data-edit-save')) this.chatEditSave(el.querySelector('.ccc-edit-ta').value);
          else if (ed.hasAttribute('data-edit-cancel')) this.chatEditCancel();
          else this.chatHistory(+ed.dataset.history, ed);
          return;
        }
        const max = e.target.closest && e.target.closest('[data-vid-max]');
        if (max) { e.preventDefault(); this.chatMediaOpen(el, max.closest('.ccc-vid')); return; }
        const a = e.target.closest && e.target.closest('a.ccc-img');
        if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
        e.preventDefault();
        this.chatMediaOpen(el, a);
      });
    }
    const html = this.chatThreadHtml();
    if (el._cccHtml !== html) {
      el._cccHtml = html;
      // A re-render (a new message, a reaction) must not stop a video
      // someone is watching: note where each one was and carry it over.
      const playing = {};
      el.querySelectorAll('.ccc-vid video').forEach(v => {
        if (v.currentTime > 0.2) playing[v.parentNode.dataset.aid] = { t: v.currentTime, paused: v.paused, muted: v.muted, volume: v.volume };
      });
      // Nor lose what someone is typing into the inline editor.
      const ta = el.querySelector('.ccc-edit-ta');
      const keep = ta ? { id: ta.parentNode.dataset.edit, v: ta.value, a: ta.selectionStart, b: ta.selectionEnd, f: document.activeElement === ta } : null;
      el.innerHTML = '<div class="ccc-thread-in">' + html + '</div>';
      const nta = el.querySelector('.ccc-edit-ta');
      if (nta && keep && keep.id === nta.parentNode.dataset.edit) {
        nta.value = keep.v;
        if (keep.f) { nta.focus(); nta.setSelectionRange(keep.a, keep.b); }
      }
      if (nta) this.chatGrow(nta, 320);
      el.querySelectorAll('img').forEach(img => img.addEventListener('load', () => { if (c.nearBottom) el.scrollTop = el.scrollHeight; }));
      el.querySelectorAll('.ccc-vid').forEach(box => {
        const v = box.querySelector('video');
        const was = playing[box.dataset.aid];
        v.addEventListener('loadedmetadata', () => {
          if (c.nearBottom) el.scrollTop = el.scrollHeight;
          if (was) { v.currentTime = was.t; v.muted = was.muted; v.volume = was.volume; if (!was.paused) v.play().catch(() => {}); }
        }, { once: true });
        v.addEventListener('error', () => { const t = box.querySelector('template'); if (t) box.replaceWith(t.content.cloneNode(true)); }, { once: true });
      });
      this.chatMakeThumbs();
      el.querySelectorAll('img.ccc-avimg').forEach(img => this.chatAvRef(img));
    }
    if (c.scrollPin && c.conv) { el.scrollTop = el.scrollHeight; c.scrollPin = false; c.nearBottom = true; }
    if (c.edit && c.edit.focus) {
      const t = el.querySelector('.ccc-edit-ta');
      if (t) {
        c.edit.focus = false;
        t.focus();
        t.setSelectionRange(t.value.length, t.value.length);
        const box = t.closest('[data-mid]');
        if (box && box.scrollIntoView) box.scrollIntoView({ block: 'nearest' });
      }
    }
  },

  // Reply emails show a video as a picture with a play button. The server
  // can't make one, so the first browser allowed to (an operator, or the
  // person who sent it) draws a frame and posts it (Chat::save_thumb).
  chatMakeThumbs() {
    const c = this._chat, cb = this.chatBoot();
    if (!c || !c.conv) return;
    c.thumbTried = c.thumbTried || {};
    c.conv.messages.forEach(m => (m.attachments || []).forEach(f => {
      if (!f.video || f.thumb || c.thumbTried[f.id]) return;
      if (!cb.operator && m.author_user_id !== cb.userId) return;
      c.thumbTried[f.id] = true;
      this.chatVideoFrame(this.chatFileUrl(f)).then(blob => {
        const fd = new FormData();
        fd.append('thumb', blob, 'thumb.jpg');
        return this.api('/chat/attachments/' + f.id + '/thumb', { method: 'POST', body: fd });
      }).then(r => { if (r && r.thumb) f.thumb = true; }).catch(() => {});
    }));
  },

  // A JPEG of one frame (a second in, or a quarter of a short clip).
  chatVideoFrame(url) {
    return new Promise((resolve, reject) => {
      const v = document.createElement('video');
      v.muted = true; v.playsInline = true; v.preload = 'auto';
      let timer;
      const end = (err, blob) => { clearTimeout(timer); v.removeAttribute('src'); v.load(); err ? reject(err) : resolve(blob); };
      timer = setTimeout(() => end(new Error('timeout')), 20000);
      v.addEventListener('loadedmetadata', () => { v.currentTime = Math.min(1, (v.duration || 0) / 4); }, { once: true });
      v.addEventListener('seeked', () => {
        try {
          const k = Math.min(1, 1280 / (v.videoWidth || 1280));
          const cv = document.createElement('canvas');
          cv.width = Math.max(16, Math.round(v.videoWidth * k)); cv.height = Math.max(16, Math.round(v.videoHeight * k));
          cv.getContext('2d').drawImage(v, 0, 0, cv.width, cv.height);
          cv.toBlob(b => b ? end(null, b) : end(new Error('blob')), 'image/jpeg', 0.85);
        } catch (e) { end(e); }
      }, { once: true });
      v.addEventListener('error', () => end(new Error('decode')), { once: true });
      v.src = url;
    });
  },

  // Open the viewer on one image or video of a thread, with every image
  // and video in the thread to step through. A video picks up where the
  // inline player was, and the inline player picks up where the big one
  // stopped.
  chatMediaOpen(el, target) {
    const nodes = [...el.querySelectorAll('a.ccc-img, .ccc-vid')];
    const items = nodes.map(n => n.classList.contains('ccc-vid')
      ? { url: n.dataset.url, name: n.dataset.name || 'video', video: n.querySelector('video') }
      : { url: n.href, name: (n.querySelector('img') || {}).alt || 'image' });
    items.forEach(it => { if (it.video) { it.t = it.video.currentTime || 0; it.video.pause(); } });
    this.chatLightbox(items, nodes.indexOf(target));
  },

  // Full-size viewer over everything: the thread's images and videos in
  // order, arrows (or ← →) to step through, Download, Open in a new tab,
  // Esc or a click outside to close. Plain DOM, so it works from every thread.
  chatLightbox(items, index) {
    if (!items.length) return;
    if (this._chatLb) this._chatLb.close();
    const back = document.activeElement;
    const lb = document.createElement('div');
    lb.className = 'ccc-lb';
    lb.setAttribute('role', 'dialog');
    lb.setAttribute('aria-modal', 'true');
    lb.setAttribute('aria-label', 'Media viewer');
    const icon = d => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' + d + '"></path></svg>';
    lb.innerHTML =
      '<div class="ccc-lb-bar"><span class="ccc-lb-name"></span><span class="ccc-lb-count"></span>' +
      '<a class="ccc-lb-btn" data-dl>' + icon('M12 4v11M7 10l5 5 5-5M5 20h14') + '<span>Download</span></a>' +
      '<a class="ccc-lb-btn" data-open target="_blank" rel="noopener" title="Open in a new tab">' + icon('M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5') + '</a>' +
      '<button class="ccc-lb-btn" data-close title="Close (Esc)">' + icon('M6 6l12 12M18 6L6 18') + '</button></div>' +
      '<div class="ccc-lb-stage"><button class="ccc-lb-nav" data-prev title="Previous (←)">' + icon('M15 6l-6 6 6 6') + '</button>' +
      '<img class="ccc-lb-img" alt=""><video class="ccc-lb-vid" controls playsinline hidden></video><button class="ccc-lb-nav" data-next title="Next (→)">' + icon('M9 6l6 6-6 6') + '</button></div>';
    const $ = q => lb.querySelector(q);
    let i = Math.max(0, index);
    const vid = $('.ccc-lb-vid');
    // Hand a video's position back to its inline player.
    const leave = () => {
      const it = items[i];
      if (!it || !it.video || vid.hidden) return;
      it.t = vid.currentTime;
      vid.pause();
      try { it.video.currentTime = it.t; } catch (e) {}
    };
    const show = () => {
      const it = items[i];
      $('.ccc-lb-img').hidden = !!it.video;
      vid.hidden = !it.video;
      if (it.video) {
        $('.ccc-lb-img').removeAttribute('src');
        vid.src = it.url;
        vid.addEventListener('loadedmetadata', () => { if (it.t) vid.currentTime = it.t; }, { once: true });
        vid.play().catch(() => {});
      } else {
        vid.pause();
        vid.removeAttribute('src');
        $('.ccc-lb-img').src = it.url;
      }
      $('.ccc-lb-img').alt = it.name;
      $('.ccc-lb-name').textContent = it.name;
      $('.ccc-lb-count').textContent = items.length > 1 ? (i + 1) + ' of ' + items.length : '';
      $('[data-dl]').href = it.url + (it.url.indexOf('?') > -1 ? '&' : '?') + 'download=1';
      $('[data-dl]').setAttribute('download', it.name);
      $('[data-open]').href = it.url;
      $('[data-prev]').style.visibility = i > 0 ? 'visible' : 'hidden';
      $('[data-next]').style.visibility = i < items.length - 1 ? 'visible' : 'hidden';
    };
    const step = d => { const n = i + d; if (n >= 0 && n < items.length) { leave(); i = n; show(); } };
    const key = e => {
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
      else if (e.key === ' ' && !vid.hidden && e.target !== vid) { e.preventDefault(); if (vid.paused) vid.play().catch(() => {}); else vid.pause(); }
      else if (e.key === 'ArrowLeft') step(-1);
      else if (e.key === 'ArrowRight') step(1);
    };
    const close = () => {
      leave();
      vid.removeAttribute('src');
      document.removeEventListener('keydown', key, true);
      lb.remove();
      this._chatLb = null;
      if (back && back.focus) back.focus();
    };
    lb.addEventListener('click', e => {
      if (e.target.closest('[data-close]')) close();
      else if (e.target.closest('[data-prev]')) step(-1);
      else if (e.target.closest('[data-next]')) step(1);
      else if (e.target === lb || e.target.classList.contains('ccc-lb-stage')) close();
    });
    document.addEventListener('keydown', key, true);
    document.body.appendChild(lb);
    this._chatLb = { close };
    show();
    $('[data-close]').focus();
  },

  // ── reactions (Groddle's set) ────────────────────────────────────────

  CHAT_REACTIONS: [['like', '👍', 'Thumbs up'], ['heart', '❤️', 'Heart'], ['laugh', '😂', 'Laughing'], ['wow', '😮', 'Surprised'], ['sad', '😢', 'Sad'], ['tada', '🎉', 'Hooray']],

  // A message's reaction pills: emoji, count, pressed when one is yours,
  // and who reacted in the tooltip (eight names, then "and N others").
  // ── CaptainCore logs in messages ──────────────────────────────────────
  // Operators link timeline entries from the site picker (Chat::log_options);
  // they ride the message as log_refs and render as cards (Chat::logs_for).

  chatLogsHtml(m) {
    const logs = m.logs || [];
    if (!logs.length) return '';
    const c = this._chat;
    const esc = t => this.chatEsc(t);
    const day = ts => new Date(ts * 1000).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    return '<div class="ccc-logs">' + logs.map(l => {
      if (l.missing) return '<div class="ccc-log ccc-log-gone">This log was removed.</div>';
      const key = m.id + '-' + l.id;
      const long = String(l.body || '').length > 420 || String(l.body || '').split('\n').length > 7;
      const open = c.logOpen && c.logOpen[key];
      const meta = [l.site_name, day(l.created_at), l.author, l.files ? l.files + (l.files === 1 ? ' file changed' : ' files changed') : ''].filter(Boolean).join(' · ');
      return '<div class="ccc-log" data-url="' + esc(l.url || '') + '">' +
        '<div class="ccc-log-h"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V9z"></path><path d="M14 3v6h6M8 13h8M8 17h5"></path></svg>' +
        '<b>' + esc(l.title) + '</b>' + (l.public ? '' : '<span class="ccc-log-int">Internal</span>') + '</div>' +
        '<div class="ccc-log-meta">' + esc(meta) + '</div>' +
        (l.body && l.body.trim() !== l.title ? '<div class="ccc-log-b' + (long && !open ? ' ccc-log-clip' : '') + '">' + this.chatText(l.body) + '</div>' : '') +
        '<div class="ccc-log-foot">' + (long ? '<button type="button" data-log-more="' + key + '">' + (open ? 'Show less' : 'Show all') + '</button>' : '') +
        (l.url ? '<a href="' + esc(l.url) + '" target="_blank" rel="noopener">Site timeline ↗</a>' : '') + '</div></div>';
    }).join('') + '</div>';
  },

  // The picker: a popover above the composer with the customer's sites,
  // a search, and the newest logs on the chosen site. Internal (private)
  // logs can go in a note only.
  chatLogPicker(btn) {
    const c = this._chat;
    if (!c || !c.id) return;
    if (this._chatLogPick) { this._chatLogPick.close(); return; }
    const cid = c.id;
    const esc = t => this.chatEsc(t);
    const pick = document.createElement('div');
    pick.className = 'ccc-lpick';
    pick.setAttribute('role', 'dialog');
    pick.setAttribute('aria-label', 'Link a CaptainCore log');
    pick.innerHTML = '<div class="ccc-lpick-head"><b>Link a log</b><select aria-label="Site"></select></div>' +
      '<input type="search" placeholder="Search this site’s logs" aria-label="Search logs"><div class="ccc-lpick-list" role="listbox"><div class="ccc-lpick-none">Loading…</div></div>';
    document.body.appendChild(pick);
    const sel = pick.querySelector('select'), input = pick.querySelector('input'), list = pick.querySelector('.ccc-lpick-list');
    const place = () => {
      const r = (btn && btn.getBoundingClientRect && btn.offsetParent !== null) ? btn.getBoundingClientRect() : { left: window.innerWidth / 2 - 220, top: window.innerHeight - 80 };
      const w = pick.offsetWidth, h = pick.offsetHeight;
      pick.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, r.left)) + 'px';
      pick.style.top = Math.max(8, r.top - h - 8) + 'px';
    };
    let data = null, timer = 0, seq = 0;
    const note = () => this.state.chatMode === 'note';
    const render = () => {
      if (!data) return;
      const linked = new Set((c.logs || []).map(l => l.id + ':' + l.site_id));
      if (!data.logs.length) { list.innerHTML = '<div class="ccc-lpick-none">' + (data.site_id ? (input.value.trim() ? 'No logs match.' : 'No logs on this site yet.') : 'The customer has no sites. Pick one with “Other site…”.') + '</div>'; place(); return; }
      list.innerHTML = data.logs.map(l => {
        const off = !l.public && !note();
        const on = linked.has(l.id + ':' + data.site_id);
        return '<button type="button" role="option" class="ccc-lpick-row" data-log="' + l.id + '"' + (off ? ' disabled title="Internal log: switch to Internal note to link it"' : '') + ' aria-selected="' + on + '">' +
          '<span class="ccc-lpick-t">' + esc(l.title) + (l.public ? '' : ' <em>Internal</em>') + (on ? ' <em>Linked</em>' : '') + '</span>' +
          '<span class="ccc-lpick-s">' + esc(new Date(l.created_at * 1000).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) + ' · ' + l.author + (l.excerpt && l.excerpt !== l.title ? ' · ' + l.excerpt : '')) + '</span></button>';
      }).join('');
      place();
    };
    const load = site => {
      const n = ++seq;
      const qs = [];
      if (site) qs.push('site=' + site);
      if (input.value.trim()) qs.push('q=' + encodeURIComponent(input.value.trim()));
      this.api('/chat/conversations/' + cid + '/logs' + (qs.length ? '?' + qs.join('&') : '')).then(r => {
        if (n !== seq) return;
        if (!r || r.code) throw new Error((r && r.message) || 'Could not load logs');
        data = r;
        sel.innerHTML = r.sites.map(st => '<option value="' + st.id + '"' + (st.id === r.site_id ? ' selected' : '') + '>' + esc(st.name + (st.customer ? '' : ' (not theirs)')) + '</option>').join('') + '<option value="other">Other site…</option>';
        render();
      }).catch(e => { if (n === seq) { list.innerHTML = '<div class="ccc-lpick-none">' + esc(e && e.message !== 'auth' ? e.message : 'Could not load logs') + '</div>'; place(); } });
    };
    sel.addEventListener('change', () => {
      if (sel.value === 'other') {
        const v = window.prompt('Site ID to pick a log from (it can only go in a note unless the customer can access it):');
        if (v && /^\d+$/.test(v.trim())) load(+v.trim()); else if (data) sel.value = String(data.site_id);
        return;
      }
      load(+sel.value);
    });
    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load(data ? data.site_id : 0), 250); });
    list.addEventListener('click', e => {
      const b = e.target.closest('[data-log]');
      if (!b || b.disabled || !data) return;
      const l = data.logs.find(x => x.id === +b.dataset.log);
      const site = data.sites.find(st => st.id === data.site_id);
      c.logs = c.logs || [];
      const k = l.id + ':' + data.site_id;
      if (c.logs.some(x => x.id + ':' + x.site_id === k)) c.logs = c.logs.filter(x => x.id + ':' + x.site_id !== k);
      else if (c.logs.length >= 5) { this.toast('Link at most 5 logs in one message.', { kind: 'info' }); return; }
      else c.logs.push(Object.assign({ site_id: data.site_id, site_name: site ? site.name : '' }, l));
      this.setState({});
      this.chatDraftEdited(this.state.chatBody || '');
      close();
      this._chatFocus = true;
    });
    const close = () => { clearTimeout(timer); document.removeEventListener('mousedown', away, true); document.removeEventListener('keydown', key, true); window.removeEventListener('resize', place); pick.remove(); this._chatLogPick = null; };
    const away = e => { if (!pick.contains(e.target) && !(btn && btn.contains(e.target))) close(); };
    const key = e => { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); } };
    document.addEventListener('mousedown', away, true);
    document.addEventListener('keydown', key, true);
    window.addEventListener('resize', place);
    this._chatLogPick = { close };
    place();
    input.focus();
    load(0);
  },

  // ── drafts (operators) ────────────────────────────────────────────────
  // One waiting reply per conversation (Chat::save_draft), usually written
  // by an AI model through /draft-chat. Opening the thread puts it in the
  // reply box; edits there save back to it; sending clears it. c.dftSeed is
  // the draft text last put in (or saved from) the box, so a newer draft
  // only replaces text the operator hasn't changed.

  chatDraftSync(draft, prev) {
    const c = this._chat;
    if (!c || !c.conv) return;
    c.conv.draft = draft;
    // The same draft again (a refetch) or the echo of our own save: nothing new.
    if (draft && ((prev && prev.body === draft.body && prev.updated_at === draft.updated_at) || draft.body === c.dftSeed)) return;
    const cur = this.state.chatBody || '';
    const untouched = !cur.trim() || cur === c.dftSeed;
    c.dftRemote = false;
    if (draft && cur === draft.body) { c.dftSeed = cur; return; }
    if (draft && untouched) this.chatDraftFill(draft);
    else if (draft) c.dftRemote = true; // they've changed the text: offer, don't overwrite
    else if (c.dftSeed && cur === c.dftSeed) { c.dftSeed = ''; c.dftFill = ''; c.logs = []; this.setState({ chatBody: '' }); } // sent or discarded elsewhere
    else c.dftSeed = '';
  },

  chatDraftFill(draft) {
    const c = this._chat;
    // Logs the draft links come along as chips.
    c.logs = (draft.logs || []).filter(l => !l.missing).map(l => ({ id: l.id, site_id: l.site_id, site_name: l.site_name, title: l.title, created_at: l.created_at, public: l.public }));
    c.dftSeed = draft.body;
    c.dftFill = draft.body;
    c.dftRemote = false;
    this.setState({ chatBody: draft.body, chatMode: draft.kind === 'note' ? 'note' : 'reply' });
  },

  // A change in the reply box while a draft is waiting saves back to it
  // (1.2s after typing stops). An emptied box leaves the draft alone:
  // Discard is the way to drop it.
  chatDraftEdited(text) {
    const c = this._chat;
    if (!c || !c.conv || !c.conv.draft || c.dftRemote || (!String(text).trim() && !(c.logs || []).length)) return;
    c.dftPending = { id: c.conv.conversation.id, body: String(text), kind: this.state.chatMode === 'note' ? 'note' : 'comment', log_refs: (c.logs || []).map(l => l.id + ':' + l.site_id).join(',') };
    clearTimeout(c.dftTimer);
    c.dftTimer = setTimeout(() => this.chatDraftFlush(), 1200);
  },

  chatDraftFlush() {
    const c = this._chat;
    if (!c || !c.dftPending) return;
    clearTimeout(c.dftTimer);
    const p = c.dftPending;
    c.dftPending = null;
    if (c.conv && c.conv.conversation.id === p.id) c.dftSeed = p.body;
    this.api('/chat/conversations/' + p.id + '/draft', { method: 'PUT', body: { body: p.body, kind: p.kind, log_refs: p.log_refs } }).then(r => {
      if (r && r.draft && c.conv && c.conv.conversation.id === p.id && c.conv.draft) c.conv.draft = r.draft;
    }).catch(() => {});
  },

  chatDraftDiscard() {
    const c = this._chat;
    if (!c || !c.conv || !c.conv.draft) return;
    const id = c.conv.conversation.id;
    const was = c.conv.draft;
    clearTimeout(c.dftTimer); c.dftPending = null;
    const cur = this.state.chatBody || '';
    const clear = cur === c.dftSeed || cur === was.body;
    c.conv.draft = null; c.dftSeed = ''; c.dftRemote = false;
    const lr = c.list.find(x => x.id === id); if (lr) lr.draft = '';
    if (clear) { c.dftFill = ''; c.logs = []; this.setState({ chatBody: '' }); } else this.setState({});
    this.api('/chat/conversations/' + id + '/draft', { method: 'DELETE' }).then(r => {
      if (!r || r.code) throw new Error((r && r.message) || 'Could not discard the draft');
      this.toast('Draft discarded', { kind: 'info' });
    }).catch(e => {
      if (c.conv && c.conv.conversation.id === id) { c.conv.draft = was; if (lr) lr.draft = was.kind; this.setState({}); }
      this.toast(e && e.message && e.message !== 'auth' ? e.message : 'Could not discard the draft', { kind: 'error' });
    });
  },

  chatDraftVals() {
    const c = this._chat;
    const d = c && c.conv && c.conv.draft;
    if (!d || !this.chatBoot().operator) return { chatDraftShow: false };
    const conv = c.conv.conversation;
    const since = c.conv.messages.filter(m => m.id > d.based_on_message_id && m.kind === 'comment' && m.author_kind === 'customer').length;
    const who = String(conv.customer_name || 'The customer').split(' ')[0];
    return {
      chatDraftShow: true,
      chatDraftLabel: (d.kind === 'note' ? 'Draft note by ' : 'Draft by ') + (d.by || 'Someone') + ' · ' + (this.chatAgo(d.created_at) === 'now' ? 'just now' : /^\d/.test(this.chatAgo(d.created_at)) ? this.chatAgo(d.created_at) + ' ago' : this.chatAgo(d.created_at)),
      chatDraftTitle: 'Written ' + new Date(d.created_at * 1000).toLocaleString() + (d.updated_at > d.created_at ? ', last changed ' + new Date(d.updated_at * 1000).toLocaleString() : '') + '. Sending clears it.',
      chatDraftWarn: since ? who + ' wrote ' + (since === 1 ? 'again' : since + ' more times') + ' since' : '',
      chatDraftHasWarn: since > 0,
      chatDraftNewer: !!c.dftRemote,
      chatDraftUse: () => this.chatDraftFill(c.conv.draft),
      chatDraftDiscard: () => this.chatDraftDiscard()
    };
  },

  // ── edits ─────────────────────────────────────────────────────────────
  // Same rules as Chat::can_edit. The server keeps each replaced version
  // (Chat::revisions); operators read them from the "edited" label.

  chatCanEdit(m) {
    const cb = this.chatBoot();
    if (!m || (m.kind !== 'comment' && m.kind !== 'note')) return false;
    if (cb.operator) return true;
    return m.kind === 'comment' && m.author_kind === 'customer' && m.author_user_id === cb.userId;
  },

  // The pencil beside the reaction button, on your own messages. Others'
  // (an operator fixing a customer's) go through the right-click menu.
  chatEditBtn(m) {
    if (!this.chatCanEdit(m) || m.author_user_id !== this.chatBoot().userId || m.via === 'bot') return '';
    return '<button type="button" class="ccc-react-add ccc-edit-btn" data-edit-open data-mid="' + m.id + '" title="Edit" aria-label="Edit message">' +
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"></path></svg></button>';
  },

  chatEditHtml() {
    const e = this._chat.edit;
    return '<div class="ccc-edit" data-edit="' + e.id + '"><textarea class="ccc-edit-ta" rows="2" aria-label="Edit message"' + (e.saving ? ' readonly' : '') + '>' + this.chatEsc(e.seed) + '</textarea>' +
      '<div class="ccc-edit-bar"><span>Enter to save · Esc to cancel</span><button type="button" class="ccc-edit-cancel" data-edit-cancel>Cancel</button>' +
      '<button type="button" class="ccc-edit-save" data-edit-save' + (e.saving ? ' disabled' : '') + '>' + (e.saving ? 'Saving…' : 'Save') + '</button></div></div>';
  },

  chatGrow(t, max) {
    t.style.height = 'auto';
    t.style.height = Math.min(t.scrollHeight + 2, max) + 'px';
  },

  chatEditStart(mid) {
    const c = this._chat;
    const m = c && c.conv && c.conv.messages.find(x => x.id === mid);
    if (!this.chatCanEdit(m)) return;
    c.edit = { id: mid, seed: m.body || '', focus: true };
    this.setState({});
  },

  // The newest message you wrote here, for Up in an empty composer.
  chatEditLast() {
    const c = this._chat;
    const me = this.chatBoot().userId;
    if (!c || !c.conv || c.edit) return false;
    const m = [...c.conv.messages].reverse().find(x => x.author_user_id === me && x.via !== 'bot' && this.chatCanEdit(x));
    if (!m) return false;
    this.chatEditStart(m.id);
    return true;
  },

  chatEditCancel() {
    const c = this._chat;
    if (!c || !c.edit) return;
    c.edit = null;
    this.setState({});
    const t = this.chatComposer();
    if (t) t.focus();
  },

  chatEditSave(text) {
    const c = this._chat;
    const e = c && c.edit;
    if (!e || e.saving) return;
    const m = c.conv && c.conv.messages.find(x => x.id === e.id);
    if (!m) { c.edit = null; this.setState({}); return; }
    const body = String(text || '').trim();
    if (!body && !(m.attachments || []).length) { this.toast('A message needs some text.', { kind: 'info' }); return; }
    if (body === String(m.body || '').trim()) { this.chatEditCancel(); return; }
    e.saving = true;
    this.setState({});
    this.api('/chat/messages/' + e.id, { method: 'POST', body: { body } }).then(r => {
      if (!r || r.code || !r.message) throw new Error((r && r.message) || 'Could not save the change');
      if (c.edit === e) c.edit = null;
      this.chatAfterEdit(r.message);
      const t = this.chatComposer();
      if (t) t.focus();
    }).catch(err => {
      e.saving = false;
      this.setState({});
      this.toast(err && err.message && err.message !== 'auth' ? err.message : 'Could not save the change', { kind: 'error' });
    });
  },

  // Fold an edited message (ours, or message.updated from the relay) into
  // the thread, and into the list preview when it is the latest reply.
  chatAfterEdit(msg) {
    const c = this._chat;
    if (!c || !msg || !msg.id) return;
    const viewing = c.conv && c.conv.conversation.id === msg.conversation_id;
    const m = viewing && c.conv.messages.find(x => x.id === msg.id);
    if (m) Object.assign(m, msg);
    const row = c.list.find(x => x.id === msg.conversation_id);
    const last = viewing && [...c.conv.messages].reverse().find(x => x.kind === 'comment');
    if (row && msg.kind === 'comment' && (row._lastSeen === msg.id || (last && last.id === msg.id))) row.preview = String(msg.body || '').slice(0, 200);
    this.setState({});
  },

  // Edit history (operators): every version, newest first, each showing
  // what changed from the one before it.
  chatHistory(mid, from) {
    const opener = from || document.activeElement;
    this.api('/chat/messages/' + mid + '/revisions').then(r => {
      if (!r || r.code || !r.revisions) throw new Error((r && r.message) || 'Could not load the edit history');
      const list = r.revisions;
      const esc = t => this.chatEsc(t);
      const when = ts => new Date(ts * 1000).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
      const box = document.createElement('div');
      box.className = 'ccc-hist';
      box.innerHTML = '<div class="ccc-hist-box" role="dialog" aria-modal="true" aria-labelledby="ccc-hist-h">' +
        '<div class="ccc-hist-head"><h2 id="ccc-hist-h">Edit history</h2><span>' + list.length + ' versions</span><button type="button" class="ccc-tool" data-close aria-label="Close">✕</button></div>' +
        '<ol class="ccc-hist-list">' + list.map((v, i) => {
          const older = list[i + 1];
          const label = v.current ? 'Current' : (!older ? 'Original' : 'Version ' + (list.length - i));
          return '<li><div class="ccc-hist-meta"><b>' + label + '</b> · ' + esc(v.by || 'Someone') + ' · ' + esc(when(v.at)) + '</div>' +
            '<div class="ccc-hist-body">' + (older ? this.chatWordDiff(older.body, v.body) : esc(v.body)) + '</div></li>';
        }).join('') + '</ol></div>';
      document.body.appendChild(box);
      const close = () => { document.removeEventListener('keydown', key, true); box.remove(); if (opener && opener.focus && document.body.contains(opener)) opener.focus(); };
      const key = e => { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); } };
      box.addEventListener('click', e => { if (e.target === box || e.target.closest('[data-close]')) close(); });
      document.addEventListener('keydown', key, true);
      box.querySelector('[data-close]').focus();
    }).catch(e => this.toast(e && e.message && e.message !== 'auth' ? e.message : 'Could not load the edit history', { kind: 'error' }));
  },

  // Word-level diff of two texts as escaped HTML: removed words in <del>,
  // added in <ins>. Very long texts that both changed throughout skip the
  // diff and show the newer text.
  chatWordDiff(a, b) {
    const esc = t => this.chatEsc(t);
    const A = String(a || '').split(/(\s+)/).filter(Boolean);
    const B = String(b || '').split(/(\s+)/).filter(Boolean);
    let p = 0;
    while (p < A.length && p < B.length && A[p] === B[p]) p++;
    let q = 0;
    while (q < A.length - p && q < B.length - p && A[A.length - 1 - q] === B[B.length - 1 - q]) q++;
    const x = A.slice(p, A.length - q), y = B.slice(p, B.length - q);
    const N = x.length, M = y.length, W = M + 1;
    if (N * M > 3000000) return esc(b);
    const L = new Uint32Array((N + 1) * W);
    for (let i = N - 1; i >= 0; i--) for (let j = M - 1; j >= 0; j--) L[i * W + j] = x[i] === y[j] ? L[(i + 1) * W + j + 1] + 1 : Math.max(L[(i + 1) * W + j], L[i * W + j + 1]);
    const ops = [];
    const push = (k, t) => { const o = ops[ops.length - 1]; if (o && o.k === k) o.t += t; else ops.push({ k, t }); };
    let i = 0, j = 0;
    while (i < N && j < M) {
      if (x[i] === y[j]) { push('=', x[i]); i++; j++; }
      else if (L[(i + 1) * W + j] >= L[i * W + j + 1]) push('-', x[i++]);
      else push('+', y[j++]);
    }
    while (i < N) push('-', x[i++]);
    while (j < M) push('+', y[j++]);
    return esc(A.slice(0, p).join('')) + ops.map(o => o.k === '=' ? esc(o.t) : o.k === '-' ? '<del>' + esc(o.t) + '</del>' : '<ins>' + esc(o.t) + '</ins>').join('') + esc(A.slice(A.length - q).join(''));
  },

  chatReactsHtml(m) {
    const me = this.chatBoot().userId;
    const defs = Object.fromEntries(this.CHAT_REACTIONS.map(r => [r[0], r]));
    const chips = (m.reactions || []).filter(r => defs[r.key] && r.by.length).map(r => {
      const mine = r.by.some(b => b.id === me);
      const names = r.by.map(b => b.id === me ? 'You' : b.name);
      const shown = names.slice(0, 8);
      if (names.length > 8) shown.push(names.length - 8 === 1 ? '1 other' : (names.length - 8) + ' others');
      const who = shown.length > 1 ? shown.slice(0, -1).join(', ') + ' and ' + shown[shown.length - 1] : shown[0];
      const label = this.chatEsc(defs[r.key][2] + ': ' + who);
      return '<button type="button" class="ccc-chip" data-react="' + r.key + '" data-mid="' + m.id + '" aria-pressed="' + mine + '" title="' + label + '" aria-label="' + label + '">' + defs[r.key][1] + '<span>' + r.by.length + '</span></button>';
    }).join('');
    return chips ? '<div class="ccc-reacts">' + chips + '</div>' : '';
  },

  chatReactBtn(m) {
    return '<button type="button" class="ccc-react-add" data-react-open data-mid="' + m.id + '" title="React" aria-label="Add a reaction" aria-haspopup="true">' +
      '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M8.5 14.5a4.5 4.5 0 007 0M9 9.5h.01M15 9.5h.01"></path></svg></button>';
  },

  // Toggle one reaction, showing it at once and settling on the server's answer.
  chatReact(mid, key, on) {
    const c = this._chat;
    const m = c && c.conv && c.conv.messages.find(x => x.id === mid);
    if (!m) return;
    const me = this.chatBoot().userId;
    const before = JSON.parse(JSON.stringify(m.reactions || []));
    let list = (m.reactions || []).map(r => ({ key: r.key, by: r.by.filter(b => b.id !== me || r.key !== key) }));
    if (on) {
      const g = list.find(r => r.key === key);
      if (g) g.by.push({ id: me, name: 'You' }); else list.push({ key, by: [{ id: me, name: 'You' }] });
    }
    m.reactions = list.filter(r => r.by.length);
    this.setState({});
    this.api('/chat/messages/' + mid + '/reactions', { method: 'POST', body: { e: key, on } }).then(r => {
      if (!r || r.code) throw new Error((r && r.message) || 'Could not react');
      m.reactions = r.reactions || [];
      this.setState({});
    }).catch(e => { m.reactions = before; this.setState({}); this.toast(e && e.message !== 'auth' ? e.message : 'Could not react', { kind: 'error' }); });
  },

  // The picker: six emoji in a popover by the button. Esc, a pick or a
  // click elsewhere closes it.
  chatReactPicker(btn, mid) {
    if (this._chatPick) this._chatPick.close();
    const c = this._chat;
    const m = c && c.conv && c.conv.messages.find(x => x.id === mid);
    if (!m) return;
    const me = this.chatBoot().userId;
    const pick = document.createElement('div');
    pick.className = 'ccc-rpick';
    pick.setAttribute('role', 'menu');
    pick.setAttribute('aria-label', 'Add a reaction');
    pick.innerHTML = this.CHAT_REACTIONS.map(([k, g, l]) => {
      const mine = (m.reactions || []).some(r => r.key === k && r.by.some(b => b.id === me));
      return '<button type="button" role="menuitemcheckbox" data-pick="' + k + '" aria-checked="' + mine + '" title="' + l + '">' + g + '</button>';
    }).join('');
    document.body.appendChild(pick);
    const r = btn.getBoundingClientRect();
    const w = pick.offsetWidth, h = pick.offsetHeight;
    pick.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, r.right - w)) + 'px';
    pick.style.top = (r.top - h - 6 > 8 ? r.top - h - 6 : r.bottom + 6) + 'px';
    const close = () => { document.removeEventListener('mousedown', away, true); document.removeEventListener('keydown', key, true); window.removeEventListener('scroll', close, true); pick.remove(); this._chatPick = null; };
    const away = e => { if (!pick.contains(e.target)) close(); };
    const key = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
    pick.addEventListener('click', e => {
      const b = e.target.closest('[data-pick]');
      if (!b) return;
      this.chatReact(mid, b.dataset.pick, b.getAttribute('aria-checked') !== 'true');
      close();
    });
    document.addEventListener('mousedown', away, true);
    document.addEventListener('keydown', key, true);
    window.addEventListener('scroll', close, true);
    this._chatPick = { close };
    pick.querySelector('button').focus();
  },

  chatInputRef(el) {
    if (!el) return;
    // value="{{ }}" only seeds the field (DC runtime), so clearing after a
    // send or a conversation switch happens here.
    if (this._chatClear) { this._chatClear = false; el.value = ''; }
    const c0 = this._chat;
    if (c0 && c0.dftFill != null) { el.value = c0.dftFill; c0.dftFill = null; }
    // A pasted screenshot (or copied file) attaches instead of pasting text.
    if (!el._cccPaste) {
      el._cccPaste = true;
      el.addEventListener('paste', e => {
        const files = e.clipboardData && e.clipboardData.files;
        if (files && files.length) { e.preventDefault(); this.chatAddFiles(files); }
      });
    }
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
      chatHistory: [], chatHasHistory: false, chatNoHistory: false, supFilters: [], chatPending: [], chatDraftShow: false
    };
    if (!c) return off;
    if (s.route !== 'chat') this._chatPrevRoute = s.route;
    const conv = c.conv && c.conv.conversation;
    const live = !cb.live ? 'Refreshing every 30s' : c.wsState === 'live' ? 'Live' : c.fallbackTimer ? 'Reconnecting… (refreshing every 30s)' : c.wsState === 'connecting' ? 'Connecting…' : 'Reconnecting…';
    const liveFg = c.wsState === 'live' ? 'var(--ok)' : cb.live ? 'var(--warn)' : 'var(--ink-dim)';

    const statusChip = x => x.status === 'closed' ? { label: isOp ? 'Closed' : 'Resolved', bg: 'var(--panel-2)', fg: 'var(--ink-dim)' }
      : x.status === 'snoozed' && isOp ? { label: 'Snoozed', bg: 'var(--warn-soft)', fg: 'color-mix(in srgb,var(--warn) 75%,var(--ink))' }
      : x.waiting_since && isOp ? { label: 'Waiting ' + this.chatAgo(x.waiting_since), bg: 'var(--brand-soft)', fg: 'var(--brand-ink)' }
      : { label: 'Open', bg: 'var(--ok-soft)', fg: 'color-mix(in srgb,var(--ok) 75%,var(--ink))' };

    // List previews drop the markdown marks.
    const plain = t => String(t || '').replace(/```/g, '').replace(/\*\*|~~|`/g, '').replace(/\[([^\]\n]+)\]\((https?:[^)\s]+)\)/g, '$1');
    const rowTyping = id => { const t = c.typing[id]; return t && t.until > Date.now() ? (t.text ? '✎ ' + t.text.slice(-120) : 'Typing…') : ''; };
    const rows = c.list.map(x => {
      const on = x.id === c.id;
      const name = isOp ? (x.customer_name || x.customer_email || 'Guest') : x.subject;
      const chips = [statusChip(x)];
      if (isOp && x.guest) chips.push({ label: 'Guest', bg: 'var(--panel-2)', fg: 'var(--ink-dim)' });
      if (isOp && x.triaged) chips.push({ label: 'Triaged by Grok Bot', bg: 'color-mix(in srgb,#7c5cff 12%,transparent)', fg: 'color-mix(in srgb,#7c5cff 80%,var(--ink))' });
      if (isOp && x.draft) chips.push({ label: x.draft === 'note' ? 'Draft note' : 'Draft reply', bg: 'color-mix(in srgb,var(--ok) 13%,transparent)', fg: 'color-mix(in srgb,var(--ok) 70%,var(--ink))' });
      if (isOp && x.site_id) chips.push({ label: 'Site #' + x.site_id, bg: 'var(--panel-2)', fg: 'var(--ink-dim)' });
      return {
        key: 'c' + x.id, name, subject: isOp ? x.subject : plain(x.preview), preview: isOp ? (rowTyping(x.id) || plain(x.preview)) : '', ago: this.chatAgo(x.last_message_at),
        initials: this.chatInitials(isOp ? name : 'Support'), avBg: this.chatColor(isOp ? name : 'Support'),
        avatar: isOp ? this.chatAvUrl(x.customer_avatar) : '', hasAvatar: isOp && !!this.chatAvUrl(x.customer_avatar),
        chips, unread: String(x.unread || 0), hasUnread: (x.unread || 0) > 0, bold: (x.unread || 0) > 0 ? 700 : 600,
        bg: on ? 'var(--paper)' : 'transparent', shadow: on ? 'var(--shadow-sm)' : 'none', outline: on ? '1px solid var(--rule)' : '0',
        bar: on ? 'var(--brand)' : 'transparent',
        letter: String(x.subject || '?').trim().charAt(0).toUpperCase() || '?', letterBg: this.chatColor(x.subject || String(x.id)),
        open: () => { this.chatOpen(x.id); if (!isOp) this.setState({ chatWView: 'thread' }); },
        ctx: e => this.chatConvCtx(e, x)
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
        ? { label: 'Unsnooze', title: 'Unsnooze', go: () => this.chatPatch({ status: 'open' }, 'Back in the inbox'), primary: false, clock: true }
        : { label: 'Snooze ▾', title: 'Snooze', go: e => this.chatSnoozeMenu(e), primary: false, clock: true }
    ].map(a => Object.assign(a, { bg: a.primary ? 'var(--brand)' : 'var(--paper)', fg: a.primary ? 'white' : 'var(--ink)', bd: a.primary ? 'var(--brand)' : 'var(--rule)',
      title: a.title || a.label, clock: !!a.clock, iconOnly: a.clock ? '1' : '0' })); // phones show the clock alone
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
      group('accounts', 'Accounts', acc.accounts.map(a => ({ name: a.name, sub: levelLabel[a.level] || '', href: appBase + 'accounts/' + a.id, go: link(appBase + 'accounts/' + a.id, () => this.openAccount(a.id)), ctx: e => this.chatAccessCtx(e, 'accounts', a, appBase + 'accounts/' + a.id) })), 5),
      group('sites', 'Sites', acc.sites.map(x => ({ name: x.name, sub: ((h => h === x.name || h === 'www.' + x.name ? '' : h)((x.url || '').replace(/^https?:\/\//, '').replace(/\/$/, ''))), href: appBase + 'sites/' + x.id, go: link(appBase + 'sites/' + x.id, () => this.openSite(x.id)), ctx: e => this.chatAccessCtx(e, 'sites', x, appBase + 'sites/' + x.id) })), 6),
      group('domains', 'Domains', acc.domains.map(d => ({ name: d.name, sub: '', href: appBase + 'domains/' + d.id, go: link(appBase + 'domains/' + d.id, () => this.openDomain(d.id)), ctx: e => this.chatAccessCtx(e, 'domains', d, appBase + 'domains/' + d.id) })), 6)
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
    if (!isOp && c.lastCustRoute !== s.route) { c.lastCustRoute = s.route; if (s.route === 'support') setTimeout(() => { if (c.filter !== c.supFilter) { c.filter = c.supFilter; this.chatLoadList(); } this.chatSupportPick(); this.chatMarkRead(); }, 0); }
    const supMsgs = conv && c.conv ? c.conv.messages.filter(m => m.kind === 'comment') : [];
    const supReplies = Math.max(0, supMsgs.length - 1);
    const fullDate = ts => ts ? new Date(ts * 1000).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '';
    const newSite = !isOp && c.newSite ? (this.FLEET || []).find(x => x.id === c.newSite) : null;
    const siteQ = String(c.siteQ || '').trim().toLowerCase();
    const siteHits = isOp || !c.newDraft ? [] : (this.FLEET || []).filter(x => !siteQ || String(x.name).toLowerCase().includes(siteQ) || String(x.home_url || '').toLowerCase().includes(siteQ));
    // The account beside each site only helps when the sites span accounts.
    const siteManyAccounts = siteHits.length > 0 && new Set((this.FLEET || []).map(x => x.accountId)).size > 1;
    const supSite = conv && conv.site_id && !isOp ? (this.FLEET || []).find(x => String(x.id) === String(conv.site_id)) : null;
    const soundOn = !!(c.alert && c.alert.sound);
    // Operator "New message" panel.
    const opNew = isOp && !!c.newDraft && !c.id;
    const newTo = opNew ? c.newTo : null;
    const newToFirst = newTo ? String(newTo.name || newTo.email).split(' ')[0] : '';
    const newToAcc = newTo && c.access ? c.access[newTo.id] : null;
    const toQ = String(c.toQ || '').trim().toLowerCase();
    const users = this._chatUsers;
    const toHits = opNew && !newTo && users && toQ ? users.filter(u => (u.name + ' ' + u.email).toLowerCase().includes(toQ)).slice(0, 8) : [];
    const newSites = newToAcc && Array.isArray(newToAcc.sites) ? newToAcc.sites : [];
    const nsQ = String(c.siteQ || '').trim().toLowerCase();
    const nsHits = newSites.filter(x => !nsQ || (x.name + ' ' + (x.url || '')).toLowerCase().includes(nsQ));
    const nsPicked = opNew && c.newSite ? newSites.find(x => String(x.id) === String(c.newSite)) : null;
    const pushLabel = { off: 'Turn on notifications', busy: 'Turning on…', on: 'Notifications on', denied: 'Notifications blocked', install: 'Get notifications' }[push.state] || (soundOn ? 'Chime on' : 'Chime muted');

    return {
      showChat: isOp && s.route === 'chat',
      chatExit: () => this.setState({ route: this._chatPrevRoute && this._chatPrevRoute !== 'chat' ? this._chatPrevRoute : 'home' }),
      chatLiveLabel: live, chatLiveFg: liveFg,
      chatPushShow: isOp, chatPushLabel: pushLabel, chatPushIsOn: push.state === 'on', chatPushFg: push.state === 'on' ? 'var(--brand-ink)' : 'var(--ink)',
      chatPushGo: e => this.chatPushButton(e),
      chatNewGo: () => this.chatNewForCustomer(),
      chatOpNew: opNew,
      chatNewCancel: () => { c.newDraft = false; c.newTo = null; this.setState({ chatBody: '' }); },
      chatToPicked: !!newTo, chatToUnpicked: opNew && !newTo,
      chatToName: newTo ? newTo.name : '', chatToEmail: newTo ? newTo.email : '',
      chatToClear: () => { c.newTo = null; c.newSite = ''; this._chatToFocus = true; this.setState({}); },
      chatToQ: c.toQ || '', onChatToQ: e => { c.toQ = e.target.value; this.setState({}); }, chatToRef: el => this.chatToRef(el),
      chatToHits: toHits.map(u => ({ name: u.name || u.email, email: u.email, go: () => this.chatPickCustomer(u) })),
      chatToLoading: opNew && !newTo && users === null,
      chatToNone: opNew && !newTo && !!users && !!toQ && !toHits.length,
      chatToTip: opNew && !newTo && !!users && !toQ,
      chatNewSitesShow: !!newTo && newSites.length > 0,
      chatNewSitePicked: !!nsPicked, chatNewSiteUnpicked: !nsPicked, chatNewSiteName: nsPicked ? nsPicked.name : '',
      chatNewSiteClear: () => { c.newSite = ''; this.setState({}); },
      chatNewSiteQ: c.siteQ || '', onChatNewSiteQ: e => { c.siteQ = e.target.value; this.setState({}); },
      chatNewSites: nsHits.slice(0, 6).map(x => ({ name: x.name, sub: (x.url || '').replace(/^https?:\/\//, '').replace(/\/$/, ''), go: () => { c.newSite = x.id; c.siteQ = ''; this.setState({}); } })),
      chatNewSitesMore: nsHits.length > 6 ? (nsHits.length - 6) + ' more. Search to narrow.' : '',
      onChatNewSubject: e => { c.newSubject = e.target.value; },
      // Phones show the list or the thread, not both.
      chatPhoneView: (c.id || c.newDraft) ? 'thread' : 'list',
      chatBack: () => this.chatOpen(0),
      chatQ: c.q, onChatQ: e => { c.q = e.target.value; clearTimeout(c.qTimer); c.qTimer = setTimeout(() => this.chatLoadList(), 300); this.setState({}); },
      chatFilters: filters, chatRows: rows,
      chatListLoading: c.loading && !c.list.length, chatEmpty: !c.loading && !c.list.length,
      chatEmptyText: c.q.trim() ? 'No conversations match.' : isOp ? (c.filter === 'waiting' ? 'Nobody is waiting. 🎉' : 'Nothing here.') : c.filter === 'open' ? 'No open conversations.' : c.filter === 'closed' ? 'Nothing resolved yet.' : 'No conversations yet.',
      chatMore: c.more, chatLoadMore: () => this.chatLoadList(true, true),
      chatNoConv: !c.id && !c.newDraft, chatConvLoading: c.convLoading, chatHasErr: !!c.convErr, chatErr: c.convErr,
      chatHasConv: !!conv,
      chatSubject: conv ? conv.subject : '',
      chatSub: conv ? [conv.customer_email || (conv.guest ? 'Guest' : ''), conv.source === 'intercom' ? 'Imported from Intercom' : 'Started in chat', 'Opened ' + this.chatAgo(conv.created_at) + ' ago'].filter(Boolean).join(' · ') : '',
      chatStatusLabel: conv ? statusChip(conv).label : '', chatStatusBg: conv ? statusChip(conv).bg : '', chatStatusFg: conv ? statusChip(conv).fg : '',
      chatActions: actions,
      chatThreadRef: el => this.chatThreadRef(el),
      chatInputRef: el => this.chatInputRef(el),
      ...this.chatDraftVals(),
      chatModes: isOp ? [['reply', 'Reply'], ['note', 'Internal note']].map(([v, l]) => ({
        label: l, go: () => { this._chatFocus = true; this.setState({ chatMode: v }); },
        bg: (s.chatMode || 'reply') === v ? (v === 'note' ? 'var(--ccc-note)' : 'var(--panel-2)') : 'transparent',
        fg: (s.chatMode || 'reply') === v ? (v === 'note' ? 'color-mix(in srgb,var(--warn) 80%,var(--ink))' : 'var(--ink)') : 'var(--ink-dim)'
      })) : [],
      chatBody: s.chatBody || '',
      onChatBody: e => { this.setState({ chatBody: e.target.value }); this.chatTyping(e.target.value); if (isOp) this.chatDraftEdited(e.target.value); },
      chatKey: e => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); this.chatSend(); }
        // Up in an empty composer edits your last message, as in Slack.
        else if (e.key === 'ArrowUp' && !e.target.value && !e.shiftKey && !e.altKey && !e.metaKey && !e.ctrlKey && this.chatEditLast()) e.preventDefault();
      },
      chatSendGo: () => this.chatSend(),
      chatSendOpacity: ((s.chatBody || '').trim() || c.files.length || (c.logs || []).length) && !s.chatSending ? 1 : 0.45,
      chatCanLink: isOp && !!c.id,
      chatLogGo: e => this.chatLogPicker(e && e.currentTarget),
      chatHasLogs: isOp && (c.logs || []).length > 0,
      chatLinked: (c.logs || []).map(l => ({
        key: 'l' + l.id + ':' + l.site_id, title: l.title, sub: [l.site_name, new Date(l.created_at * 1000).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }), l.public ? '' : 'internal'].filter(Boolean).join(' · '),
        remove: () => { c.logs = c.logs.filter(x => x !== l); this.setState({}); this.chatDraftEdited(s.chatBody || ''); }
      })),
      chatDropRef: el => this.chatDropRef(el),
      chatDragging: c.drag > 0 && this.chatCanAttach(),
      chatAttachGo: () => this.chatPickFiles(),
      chatHasFiles: c.files.length > 0,
      chatPending: c.files.map(f => ({
        key: f.key, name: f.name, size: f.size < 1048576 ? Math.max(1, Math.round(f.size / 1024)) + ' KB' : (f.size / 1048576).toFixed(1) + ' MB',
        isImg: f.isImg, notImg: !f.isImg, ico: /^video\//.test(f.file.type) ? '🎬' : '📄', url: f.url, remove: () => this.chatRemoveFile(f.key)
      })),
      chatUploading: !!s.chatSending && c.files.length > 0,
      chatUploadLabel: 'Uploading ' + (c.files.length === 1 ? '1 file' : c.files.length + ' files') + '…',
      chatBoxBg: note ? 'var(--ccc-note)' : 'var(--panel)', chatBoxRule: note ? 'var(--ccc-note-rule)' : 'var(--rule)',
      chatPlaceholder: note ? 'Write an internal note. Only staff can see it.' : isOp && !c.id ? 'Write your message…' : isOp ? 'Reply to ' + custFirst : c.id ? 'Write a reply…' : 'How can we help?',
      chatNewHint: !newTo ? 'Choose who to message, then write it here. Enter sends.'
        : newToAcc && newToAcc.chat === false ? 'Chat isn’t on for ' + newToFirst + ' yet, so this goes to ' + newTo.email + ' by email right away. Their reply to that email comes back here.'
        : newToFirst + ' sees this in the Manager. If it’s still unread after 10 minutes (5 if they’re offline), it goes to ' + newTo.email + ' by email.',
      chatHint: note ? 'Internal notes are never shown to the customer. **bold**, _italic_, `code` and - lists work.' : isOp ? 'Enter sends, Shift+Enter for a new line. **bold**, _italic_, `code` and - lists work.' + (conv && conv.guest ? ' This contact has no Manager login, so they won’t see replies here.' : '') : '',
      chatTypingShow: !!ty,
      // Operator sidebar
      chatCustName: conv ? (conv.customer_name || 'Guest') : '', chatCustEmail: conv ? (conv.customer_email || '') : '',
      chatCustAvatar: conv ? this.chatAvUrl(conv.customer_avatar) : '', chatCustHasAvatar: !!(conv && this.chatAvUrl(conv.customer_avatar)), chatAvRef: el => this.chatAvRef(el),
      chatCustInitials: conv ? this.chatInitials(conv.customer_name || conv.customer_email) : '', chatCustBg: conv ? this.chatColor(conv.customer_name || conv.customer_email) : '',
      chatCustMeta: conv ? (conv.guest ? 'Guest · no Manager login' : 'User #' + conv.customer_user_id) : '',
      chatHasSite: !!(conv && conv.site_id), chatSiteLabel: conv && conv.site_id ? ((acc && acc.sites && (acc.sites.find(x => x.id === conv.site_id) || {}).name) || 'Site #' + conv.site_id) : '',
      chatOpenSite: () => { if (conv && conv.site_id) this.openSite(conv.site_id); },
      chatAccessGroups: accessGroups,
      chatAccessLoading: !!(acc && acc.loading),
      chatAccessNote: !conv ? '' : !conv.customer_user_id ? 'Guest: no Manager login, so no access.' : acc && acc.error ? 'Could not load access.' : acc && acc.operator ? 'This person is an operator and can see everything.' : (acc && !acc.loading && !acc.accounts.length && !acc.sites.length && !acc.domains.length) ? 'No accounts, sites or domains.' : '',
      chatSideOpen: c.sideOpen ? '1' : '0',
      chatSideToggle: () => { c.sideOpen = !c.sideOpen; this.setState({}); },
      chatHistory: hist.map(x => ({ subject: x.subject, meta: statusChip(x).label + ' · ' + this.chatAgo(x.last_message_at), go: () => this.chatOpen(x.id), ctx: e => this.chatConvCtx(e, x) })),
      chatHeadCtx: e => this.chatConvCtx(e, conv),
      chatCustCtx: e => this.chatCustCtx(e),
      chatHasHistory: hist.length > 0, chatNoHistory: !!conv && hist.length === 0,
      // Customer widget
      chatWidgetShow: !isOp && !s.dockOpen && s.route !== 'support',
      chatWidgetOpen: !isOp && !!s.chatWidgetOpen && s.route !== 'support',
      // "View history": the Support page, on the conversation the panel had open.
      chatWHistory: () => this.setState({ route: 'support', chatWidgetOpen: false, chatWView: 'home' }),
      // The panel lists everything recent; the Support page keeps its own filter (Open by default).
      chatToggleWidget: () => { const open = !s.chatWidgetOpen; this.setState({ chatWidgetOpen: open }); if (open) { const q = c.filter !== 'all' || c.q; c.filter = 'all'; c.q = ''; this.chatLoadList(!q); setTimeout(() => this.chatMarkRead(), 0); } },
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
        go: () => { c.supFilter = v; if (c.filter !== v) { c.filter = v; this.chatLoadList(); } }
      })),
      supNew: () => { this.chatOpen(0); c.newDraft = true; c.newSite = ''; c.siteQ = ''; this._chatFocus = true; this.setState({}); },
      // Optional "About a site" on a new message: the customer's own sites
      // (the server checks it again). Eight at a time, search for the rest.
      supSitePickShow: !!(c.newDraft && !c.id) && (this.FLEET || []).length > 0,
      supSitePicked: !!newSite, supSitePickedName: newSite ? newSite.name : '', supSiteUnpicked: !newSite,
      supSiteClear: () => { c.newSite = ''; this.setState({}); },
      supSiteQ: c.siteQ || '', onSupSiteQ: e => { c.siteQ = e.target.value; this.setState({}); },
      supSites: siteHits.slice(0, 8).map(x => ({ name: x.name, sub: siteManyAccounts ? (x.account || '') : '', go: () => { c.newSite = x.id; c.siteQ = ''; this._chatFocus = true; this.setState({}); } })),
      supSitesMore: siteHits.length > 8 ? (siteHits.length - 8) + ' more. Type to narrow it down.' : '',
      supSitesNone: siteHits.length === 0,
      supNewSub: newSite ? 'About ' + newSite.name : (c.online ? 'We’re online now' : 'We’ll reply by email if you leave'),
      supNewDraft: !!(c.newDraft && !c.id), supComposer: !!conv || !!(c.newDraft && !c.id),
      supPick: !c.id && !c.newDraft && (c.loading || c.list.length > 0 || !!c.q.trim()),
      supNone: !c.loading && !c.list.length && !c.q.trim() && !c.id && !c.newDraft,
      supNoneTitle: c.filter === 'open' ? 'Nothing open right now' : c.filter === 'closed' ? 'Nothing resolved yet' : 'No conversations yet',
      supNoneText: c.filter === 'all' ? 'Ask about a site, billing or anything else. Every conversation stays here, including replies you send by email.' : 'Start a new conversation, or look under All for past ones.',
      supLetter: conv ? (String(conv.subject || '?').trim().charAt(0).toUpperCase() || '?') : '', supLetterBg: conv ? this.chatColor(conv.subject || String(conv.id)) : '',
      supMeta: conv ? ['Started' + (supMsgs[0] ? ' by ' + (supMsgs[0].author_kind === 'customer' ? 'you' : (supMsgs[0].author_name || brand)) : '') + ' on ' + fullDate(conv.created_at), supReplies === 1 ? '1 reply' : supReplies + ' replies'].join(' · ') : '',
      supCanResolve: !!(conv && conv.status !== 'closed'),
      supHint: conv && conv.status === 'closed' ? 'This conversation is resolved. Replying reopens it.' : 'Enter sends, Shift+Enter for a new line.' + (c.online ? '' : ' If you leave, we’ll reply by email.'),
      supStarted: conv ? fullDate(conv.created_at) : '', supLast: conv ? fullDate(conv.last_message_at) : '',
      supHasSite: !!supSite, supSiteName: supSite ? supSite.name : '', supOpenSite: () => { if (supSite) this.openSite(supSite.id); },
      supOnlineLabel: c.online ? 'We’re online now' : 'Away right now', supOnlineSub: c.online ? 'Replies usually arrive here in minutes.' : 'Leave a message and we’ll reply here and by email.',
      supTotal: c.list.length ? (c.list.length + (c.more ? '+' : '') + (c.filter === 'open' ? ' open' : c.filter === 'closed' ? ' resolved' : c.list.length === 1 ? ' conversation' : ' conversations')) : ''
    };
  }

});
