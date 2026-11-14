// CaptainCore v3 — Chat triage (mixin, operators).
// /account/chat/triage: every open conversation, grouped by an agent's
// first pass (Chat::triage_board): Likely close, Draft ready, Needs you,
// and Not triaged (no pass yet, or a comment since). Pick a group and work
// it one conversation at a time: the latest exchange, why it is in that
// group, a reply box holding any draft, and Close / Send / Skip with
// keyboard shortcuts. Each action moves on to the next conversation.
// Agents write triage over REST (POST /chat/conversations/<id>/triage).

Object.assign(Component.prototype, {

  CCT_GROUPS: [
    ['close', 'Likely close', 'Looks finished: answered, resolved or gone quiet. Close the ones that are done.'],
    ['reply', 'Draft ready', 'A reply is drafted. Check it, edit if needed, and send.'],
    ['attention', 'Needs you', 'Needs your judgment or some work before anyone replies.'],
    ['untriaged', 'Not triaged', 'No first pass yet, or the conversation moved on since the last one.']
  ],

  cctState() {
    const c = this._chat;
    if (!c) return null;
    if (!c.tri) c.tri = { list: [], loaded: false, loading: false, group: 'close', sel: 0, legs: {}, full: {}, edits: {}, busy: 0, undo: null, seq: 0 };
    return c.tri;
  },

  // Show the Triage view (from the toggle or the /chat/triage URL).
  chatTriageOpen() {
    const c = this.chatInit();
    const t = this.cctState();
    if (!c || !t) return;
    this.cctKeysInit();
    this.setState({ chatTriage: true });
    this.cctLoad(t.loaded);
  },

  chatTriageClose() {
    this.setState({ chatTriage: false });
  },

  cctLoad(quiet) {
    const t = this.cctState();
    if (!t) return;
    const seq = ++t.seq;
    if (!quiet) { t.loading = true; this.setState({}); }
    this.api('/chat/triage').then(r => {
      if (seq !== t.seq) return;
      t.loading = false;
      if (!r || r.code || !Array.isArray(r.conversations)) throw new Error((r && r.message) || 'Could not load triage');
      t.list = r.conversations;
      t.loaded = true;
      // Arriving with nothing in the chosen group: start on the first group that has something.
      if (!quiet && !this.cctRows(t.group).length) {
        const g = this.CCT_GROUPS.find(([k]) => this.cctRows(k).length);
        if (g) t.group = g[0];
      }
      if (t.sel && !t.list.some(x => x.id === t.sel)) t.sel = 0;
      if (!t.sel && window.innerWidth > 760) this.cctSelect((this.cctRows(t.group)[0] || {}).id || 0);
      this.setState({});
    }).catch(e => {
      if (seq !== t.seq) return;
      t.loading = false;
      this.setState({});
      this.toast(e && e.message !== 'auth' ? e.message : 'Could not load triage', { kind: 'error' });
    });
  },

  // "just now", "5m ago", "3d ago", then a date (chatAgo's short forms).
  cctWhen(ts) {
    const a = this.chatAgo(ts);
    if (!a) return '';
    return a === 'now' ? 'just now' : /^\d+[mhd]$/.test(a) ? a + ' ago' : 'on ' + a;
  },

  cctGroupOf(x) {
    return x.triage && !x.triage.stale ? x.triage.category : 'untriaged';
  },

  cctRows(group) {
    const t = this.cctState();
    return t ? t.list.filter(x => this.cctGroupOf(x) === group) : [];
  },

  cctItem(id) {
    const t = this.cctState();
    return t && id ? t.list.find(x => x.id === id) || null : null;
  },

  cctSelect(id) {
    const t = this.cctState();
    if (!t) return;
    t.sel = id || 0;
    if (id) {
      // The selected one and the next two load now, so moving on is instant.
      const rows = this.cctRows(t.group);
      const i = rows.findIndex(x => x.id === id);
      [id].concat(i > -1 ? rows.slice(i + 1, i + 3).map(x => x.id) : []).forEach(n => this.cctLoadLeg(n));
      requestAnimationFrame(() => {
        const row = document.querySelector('.cct-row[data-id="' + id + '"]');
        if (row && row.scrollIntoView) row.scrollIntoView({ block: 'nearest' });
        const leg = document.querySelector('.cct-leg');
        if (leg) leg.scrollTop = leg.scrollHeight;
      });
    }
    this.setState({});
  },

  cctLoadLeg(id, force) {
    const t = this.cctState();
    if (!t || !id || (t.legs[id] && !force)) return;
    t.legs[id] = t.legs[id] || { loading: true };
    this.api('/chat/conversations/' + id + '?limit=40').then(r => {
      if (!r || r.code || !r.conversation) throw new Error((r && r.message) || 'Could not load');
      t.legs[id] = { messages: r.messages || [], draft: r.draft || null, conversation: r.conversation };
      if (!(id in t.edits) && r.draft && r.draft.kind === 'comment') t.edits[id] = r.draft.body;
      this.setState({});
    }).catch(e => { t.legs[id] = { error: (e && e.message) || 'Could not load' }; this.setState({}); });
  },

  // Move along the open group: +1 next, -1 previous.
  cctStep(dir) {
    const t = this.cctState();
    const rows = this.cctRows(t.group);
    if (!rows.length) return;
    const i = rows.findIndex(x => x.id === t.sel);
    const n = i < 0 ? 0 : Math.max(0, Math.min(rows.length - 1, i + dir));
    this.cctSelect(rows[n].id);
  },

  // After an action takes `id` out of the group: select what took its place.
  cctAfter(id, rowsBefore) {
    const t = this.cctState();
    if (t.sel !== id) { this.setState({}); return; }
    const i = rowsBefore.findIndex(x => x.id === id);
    const rest = rowsBefore.filter(x => x.id !== id && t.list.some(y => y.id === x.id && this.cctGroupOf(y) === t.group));
    const next = rest[Math.min(Math.max(i, 0), rest.length - 1)];
    this.cctSelect(next ? next.id : 0);
  },

  cctClose(id) {
    const t = this.cctState();
    const x = this.cctItem(id);
    if (!x || t.busy) return;
    const before = this.cctRows(t.group);
    t.busy = id;
    this.setState({});
    this.api('/chat/conversations/' + id, { method: 'PATCH', body: { status: 'closed' } }).then(r => {
      if (!r || r.code) throw new Error((r && r.message) || 'Could not close');
      t.busy = 0;
      t.list = t.list.filter(y => y.id !== id);
      this.cctUndoSet({ id, verb: 'Closed', subject: x.subject, triage: x.triage, item: x });
      if (this._chat) this.chatMergeConv(r.conversation);
      this.cctAfter(id, before);
    }).catch(e => { t.busy = 0; this.setState({}); this.toast(e && e.message !== 'auth' ? e.message : 'Could not close', { kind: 'error' }); });
  },

  cctSend(id, close) {
    const t = this.cctState();
    const x = this.cctItem(id);
    const body = String(t.edits[id] || '').trim();
    if (!x || !body || t.busy) return;
    const before = this.cctRows(t.group);
    t.busy = id;
    this.setState({});
    this.api('/chat/conversations/' + id + '/messages', { method: 'POST', body: { body, kind: 'comment' } }).then(r => {
      if (!r || r.code) throw new Error((r && r.message) || 'Could not send');
      delete t.edits[id];
      return close ? this.api('/chat/conversations/' + id, { method: 'PATCH', body: { status: 'closed' } }) : r;
    }).then(r => {
      t.busy = 0;
      if (r && r.conversation && this._chat) this.chatMergeConv(r.conversation);
      // Answered: it leaves the groups (a closed one for good; an open one
      // waits on the customer and comes back untriaged on the next load).
      t.list = t.list.filter(y => y.id !== id);
      delete t.legs[id];
      this.toast(close ? 'Sent and closed' : 'Sent', { kind: 'success' });
      this.cctAfter(id, before);
    }).catch(e => { t.busy = 0; this.setState({}); this.toast(e && e.message !== 'auth' ? e.message : 'Could not send', { kind: 'error' }); });
  },

  // Put a conversation in another group, as the operator's own call.
  cctMove(id, category) {
    const t = this.cctState();
    const x = this.cctItem(id);
    if (!x || this.cctGroupOf(x) === category) return;
    const before = this.cctRows(t.group);
    const reason = x.triage && x.triage.reason ? x.triage.reason : '';
    this.api('/chat/conversations/' + id + '/triage', { method: 'POST', body: { category, reason } }).then(r => {
      if (!r || r.code) throw new Error((r && r.message) || 'Could not move');
      x.triage = r.triage;
      const label = (this.CCT_GROUPS.find(g => g[0] === category) || [])[1] || category;
      this.toast('Moved to ' + label, { kind: 'success' });
      this.cctAfter(id, before);
    }).catch(e => this.toast(e && e.message !== 'auth' ? e.message : 'Could not move', { kind: 'error' }));
  },

  cctUndoSet(u) {
    const t = this.cctState();
    clearTimeout(t.undoTimer);
    t.undo = u;
    t.undoTimer = setTimeout(() => { t.undo = null; this.setState({}); }, 10000);
  },

  // Undo a close: reopen it and put its triage back.
  cctUndo() {
    const t = this.cctState();
    const u = t.undo;
    if (!u) return;
    t.undo = null;
    clearTimeout(t.undoTimer);
    this.api('/chat/conversations/' + u.id, { method: 'PATCH', body: { status: 'open' } }).then(r => {
      if (!r || r.code) throw new Error((r && r.message) || 'Could not reopen');
      if (this._chat) this.chatMergeConv(r.conversation);
      const tr = u.triage;
      const back = tr ? this.api('/chat/conversations/' + u.id + '/triage', { method: 'POST', body: Object.assign({ category: tr.category, reason: tr.reason }, tr.agent ? { agent: tr.agent } : {}) }) : Promise.resolve(null);
      return back.then(b => {
        const item = Object.assign({}, u.item, r.conversation, { triage: b && b.triage ? b.triage : null });
        t.list = t.list.filter(y => y.id !== u.id).concat([item]).sort((a, b2) => (b2.last_message_at || 0) - (a.last_message_at || 0) || b2.id - a.id);
        this.cctSelect(u.id);
        this.toast('Reopened', { kind: 'success' });
      });
    }).catch(e => this.toast(e && e.message !== 'auth' ? e.message : 'Could not undo', { kind: 'error' }));
  },

  // Keep the reply box's text as the draft (it stays the agent's draft).
  cctSaveDraft(id) {
    const t = this.cctState();
    const leg = t.legs[id];
    const text = String(t.edits[id] || '');
    if (!text.trim() || !leg || leg.loading || leg.error) return;
    const was = leg.draft && leg.draft.kind === 'comment' ? leg.draft.body : '';
    if (text === was) return;
    this.api('/chat/conversations/' + id + '/draft', { method: 'PUT', body: { body: text, kind: 'comment', keep_author: true } }).then(r => {
      if (r && r.draft) leg.draft = r.draft;
    }).catch(() => {});
  },

  // Open the conversation in the inbox (the whole thread, the sidebar).
  cctOpenThread(id) {
    this.setState({ chatTriage: false });
    this.chatOpen(id);
  },

  // Live events (chat.js chatOnEvent): keep the board current while open.
  chatTriageOnEvent(ev) {
    const t = this._chat && this._chat.tri;
    if (!t || !t.loaded) return;
    const id = ev.conversation_id || (ev.conversation && ev.conversation.id) || (ev.message && ev.message.conversation_id);
    if (ev.type === 'triage.updated') {
      const x = this.cctItem(ev.conversation_id);
      if (x) x.triage = ev.triage || null;
      else if (ev.triage) { clearTimeout(t.reload); t.reload = setTimeout(() => this.cctLoad(true), 1500); }
      return;
    }
    if (ev.type === 'conversation.deleted') { t.list = t.list.filter(y => y.id !== ev.conversation_id); return; }
    if (ev.conversation) {
      const x = this.cctItem(ev.conversation.id);
      if (x && ev.conversation.status !== 'open' && t.sel !== x.id) t.list = t.list.filter(y => y.id !== x.id);
      else if (x) Object.assign(x, ev.conversation);
      else if (ev.conversation.status === 'open') { clearTimeout(t.reload); t.reload = setTimeout(() => this.cctLoad(true), 1500); }
    }
    if (ev.type === 'message.created' && ev.message) {
      const x = this.cctItem(id);
      const m = ev.message;
      if (x && m.kind === 'comment') {
        x.last_author = m.author_kind;
        x.preview = m.body || '';
        if (x.triage && m.id > (x.triage.based_on_message_id || 0)) x.triage.stale = true;
      }
      const leg = t.legs[id];
      if (leg && leg.messages && !leg.messages.some(y => y.id === m.id)) leg.messages.push(m);
    }
    if (ev.type === 'draft.updated') {
      const leg = t.legs[ev.conversation_id];
      if (leg && !leg.loading) leg.draft = ev.draft || null;
    }
  },

  // Shortcuts while the Triage view is up and nothing is being typed.
  cctKeysInit() {
    if (this._cctKeys) return;
    this._cctKeys = e => {
      const s = this.state;
      const t = this._chat && this._chat.tri;
      if (!t || s.route !== 'chat' || !s.chatTriage || s.paletteOpen || s.ucOpen || s.ctxMenu) return;
      const el = e.target;
      const typing = el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable);
      if (typing) {
        if (el.classList && el.classList.contains('cct-reply')) {
          if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); this.cctSend(t.sel, e.shiftKey); }
          else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); el.blur(); }
        }
        return;
      }
      if (e.metaKey || e.ctrlKey || e.altKey) return;
      const k = e.key;
      if (k === 'j' || k === 'ArrowDown') { e.preventDefault(); this.cctStep(1); }
      else if (k === 'k' || k === 'ArrowUp') { e.preventDefault(); this.cctStep(-1); }
      else if (k === 'c' && t.sel) { e.preventDefault(); this.cctClose(t.sel); }
      else if (k === 's' && t.sel && String(t.edits[t.sel] || '').trim()) { e.preventDefault(); this.cctSend(t.sel, false); }
      else if (k === 'o' && t.sel) { e.preventDefault(); this.cctOpenThread(t.sel); }
      else if ((k === 'r' || k === 'e') && t.sel) { const ta = document.querySelector('.cct-reply'); if (ta) { e.preventDefault(); ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length); } }
      else if (k === 'z' && t.undo) { e.preventDefault(); this.cctUndo(); }
      else if ((k === '1' || k === '2' || k === '3') && t.sel) { e.preventDefault(); this.cctMove(t.sel, ['close', 'reply', 'attention'][+k - 1]); }
    };
    window.addEventListener('keydown', this._cctKeys);
  },

  // The latest stretch of the conversation, as HTML (escaped by chatText).
  cctLegHtml(id) {
    const t = this.cctState();
    const leg = t.legs[id];
    const x = this.cctItem(id) || (leg && leg.conversation) || {};
    if (!leg || leg.loading) return '<div class="cct-legload"><div class="cc-skel" style="height:54px;border-radius:10px"></div><div class="cc-skel" style="height:54px;border-radius:10px;margin-top:10px"></div></div>';
    if (leg.error) return '<div class="ccc-empty">' + this.chatEsc(leg.error) + '</div>';
    const esc = s => this.chatEsc(s);
    const when = ts => { const d = new Date(ts * 1000); return d.toLocaleDateString(undefined, Object.assign({ month: 'short', day: 'numeric' }, d.getFullYear() !== new Date().getFullYear() ? { year: 'numeric' } : {})) + ', ' + d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }); };
    const all = leg.messages || [];
    const show = t.full[id] ? all : all.slice(-6);
    let out = '';
    if (all.length > show.length) out += '<button type="button" class="cct-earlier" data-cct-full="' + id + '">Show ' + (all.length - show.length) + ' earlier</button>';
    show.forEach(m => {
      const name = m.author_name || (m.author_kind === 'operator' ? 'Support' : x.customer_name || 'Customer');
      if (m.kind === 'event') { out += '<div class="cct-ev">' + esc(m.body) + ' · ' + esc(when(m.created_at)) + '</div>'; return; }
      const files = (m.attachments || []).length ? '<div class="cct-files">' + m.attachments.map(f => '<span>📎 ' + esc(f.name) + '</span>').join('') + '</div>' : '';
      const body = m.body ? '<div class="ccc-mb">' + this.chatText(m.body) + '</div>' : '';
      if (m.kind === 'note') {
        out += '<div class="cct-m cct-note"><div class="cct-mh"><b>Internal note · ' + esc(name) + '</b>' + (m.via === 'bot' ? '<span class="ccc-bot">Bot</span>' : '') + '<span>' + esc(when(m.created_at)) + '</span></div>' + body + files + '</div>';
        return;
      }
      const op = m.author_kind === 'operator';
      out += '<div class="cct-m' + (op ? ' cct-op' : '') + '"><div class="cct-mh"><b>' + esc(name) + '</b>' + (op ? '<span class="ccc-staff">Staff</span>' : '') + (m.via === 'email' ? '<span class="ccc-via">by email</span>' : '') + '<span>' + esc(when(m.created_at)) + '</span></div>' + body + files + '</div>';
    });
    if (!all.length) out += '<div class="ccc-empty">No messages.</div>';
    return out;
  },

  cctLegRef(el) {
    if (!el) return;
    const t = this.cctState();
    if (!el._cctBound) {
      el._cctBound = true;
      el.addEventListener('click', e => {
        const b = e.target.closest && e.target.closest('[data-cct-full]');
        if (b) { e.preventDefault(); t.full[+b.dataset.cctFull] = true; this.setState({}); }
      });
    }
    const html = this.cctLegHtml(t.sel);
    if (el._cctHtml !== html) {
      const fresh = el._cctId !== t.sel;
      el._cctHtml = html;
      el._cctId = t.sel;
      el.innerHTML = html;
      if (fresh) el.scrollTop = el.scrollHeight;
    }
  },

  chatTriageVals(s) {
    const t = this._chat && this._chat.tri;
    const on = !!(t && s.route === 'chat' && s.chatTriage);
    const base = { chatTriageOn: on, chatInboxHide: on ? '1' : '0', chatTabInbox: () => this.chatTriageClose(), chatTabTriage: () => this.chatTriageOpen(),
      chatTabInboxBg: on ? 'transparent' : 'var(--paper)', chatTabInboxFg: on ? 'var(--ink-dim)' : 'var(--ink)', chatTabInboxShadow: on ? 'none' : 'var(--shadow-sm)',
      chatTabTriageBg: on ? 'var(--paper)' : 'transparent', chatTabTriageFg: on ? 'var(--ink)' : 'var(--ink-dim)', chatTabTriageShadow: on ? 'var(--shadow-sm)' : 'none' };
    if (!on) return Object.assign(base, { cctTabs: [], cctRows: [], cctMoves: [] });
    const counts = {};
    t.list.forEach(x => { const g = this.cctGroupOf(x); counts[g] = (counts[g] || 0) + 1; });
    const groupMeta = this.CCT_GROUPS.find(g => g[0] === t.group) || this.CCT_GROUPS[0];
    const rows = this.cctRows(t.group);
    const x = this.cctItem(t.sel);
    const leg = x ? t.legs[x.id] : null;
    const pos = x ? rows.findIndex(y => y.id === x.id) : -1;
    const first = x ? String(x.customer_name || 'the customer').split(' ')[0] : '';
    const reply = x ? String(t.edits[x.id] || '') : '';
    const hasText = !!reply.trim();
    const g = x ? this.cctGroupOf(x) : '';
    const tr = x && x.triage;
    const draft = leg && leg.draft && leg.draft.kind === 'comment' ? leg.draft : null;
    const busy = !!(x && t.busy === x.id);
    const chip = (label, kind) => ({ label, bg: kind === 'wait' ? 'var(--brand-soft)' : kind === 'draft' ? 'color-mix(in srgb,var(--ok) 13%,transparent)' : kind === 'new' ? 'var(--warn-soft)' : 'var(--panel-2)',
      fg: kind === 'wait' ? 'var(--brand-ink)' : kind === 'draft' ? 'color-mix(in srgb,var(--ok) 70%,var(--ink))' : kind === 'new' ? 'color-mix(in srgb,var(--warn) 75%,var(--ink))' : 'var(--ink-dim)' });
    const chipsFor = y => {
      const out = [];
      if (y.waiting_since) out.push(chip('Waiting ' + this.chatAgo(y.waiting_since), 'wait'));
      else if (y.last_author === 'operator') out.push(chip('You replied last'));
      if (y.draft) out.push(chip(y.draft === 'note' ? 'Draft note' : 'Draft reply', 'draft'));
      if (y.triage && y.triage.stale) out.push(chip('New message since ' + y.triage.by + '’s pass', 'new'));
      if (y.guest) out.push(chip('Guest'));
      if (y.source === 'intercom') out.push(chip('Intercom'));
      return out;
    };
    const plain = v => String(v || '').replace(/```/g, '').replace(/\*\*|~~|`/g, '').replace(/\s+/g, ' ').trim();
    return Object.assign(base, {
      cctLoading: t.loading && !t.loaded,
      cctTabs: this.CCT_GROUPS.map(([k, label]) => ({
        label, count: String(counts[k] || 0), on: t.group === k ? '1' : '0',
        go: () => { if (t.group === k) return; t.group = k; t.full = {}; const r0 = this.cctRows(k)[0]; this.cctSelect(window.innerWidth > 760 && r0 ? r0.id : 0); }
      })),
      cctGroupNote: groupMeta[2],
      cctRows: rows.map(y => ({
        id: String(y.id), on: y.id === t.sel ? '1' : '0',
        name: y.customer_name || y.customer_email || 'Guest', ago: this.chatAgo(y.last_message_at), subject: y.subject || '(no subject)',
        line: y.triage && !y.triage.stale && y.triage.reason ? y.triage.reason : plain(y.preview),
        lineIsReason: !!(y.triage && !y.triage.stale && y.triage.reason),
        chips: chipsFor(y),
        go: () => this.cctSelect(y.id), ctx: e => this.chatConvCtx(e, y)
      })),
      cctEmpty: t.loaded && !rows.length,
      cctEmptyText: t.group === 'untriaged' ? 'Every open conversation has a first pass.' : 'Nothing in ' + groupMeta[1] + '.',
      cctRefresh: () => this.cctLoad(true),
      cctPhoneView: x ? 'detail' : 'list',
      cctBack: () => { t.sel = 0; this.setState({}); },
      cctHasSel: !!x, cctNoSel: !x && t.loaded,
      cctPos: x ? (pos > -1 ? (pos + 1) + ' of ' + rows.length : 'Moved out of ' + groupMeta[1]) : '',
      cctPrev: () => this.cctStep(-1), cctNext: () => this.cctStep(1),
      cctPrevOff: pos <= 0 ? '1' : '0', cctNextOff: pos < 0 || pos >= rows.length - 1 ? '1' : '0',
      cctSubject: x ? x.subject || '(no subject)' : '',
      cctWho: x ? [x.customer_name || 'Guest', x.customer_email, 'opened ' + this.cctWhen(x.created_at)].filter(Boolean).join(' · ') : '',
      cctChips: x ? chipsFor(x) : [],
      cctHasTriage: !!tr, cctNoTriage: !!x && !tr,
      cctTriageBy: tr ? tr.by + ' · ' + tr.label + ' · ' + this.cctWhen(tr.at) : '',
      cctTriageReason: tr ? tr.reason || 'No reason given.' : '',
      cctTriageStale: !!(tr && tr.stale),
      cctMoves: x ? [['close', 'Likely close', '1'], ['reply', 'Draft ready', '2'], ['attention', 'Needs you', '3']].filter(([k]) => k !== g).map(([k, label, key]) => ({
        label, key, title: 'Move to ' + label + ' (' + key + ')', go: () => this.cctMove(x.id, k)
      })) : [],
      cctLegRef: el => this.cctLegRef(el),
      cctReply: reply,
      cctReplyPh: draft ? '' : 'Reply to ' + first + ' (optional)',
      onCctReply: e => { if (x) { t.edits[x.id] = e.target.value; this.setState({}); } },
      cctDraftBy: draft ? 'Draft by ' + draft.by + ' · ' + this.cctWhen(draft.updated_at) : '',
      cctDraftWarn: !!(draft && leg && (leg.messages || []).some(m => m.kind === 'comment' && m.author_kind === 'customer' && m.id > draft.based_on_message_id)),
      cctHasDraft: !!draft,
      // value="{{ }}" only seeds a field, so the ref puts in this
      // conversation's text (its draft, or what was typed here) whenever the
      // selection changes or the draft arrives while the box isn't in use.
      // Grows with the text; leaving it saves the text back as the draft.
      cctReplyRef: el => {
        if (!el) return;
        const id = x ? x.id : 0;
        if (el._cctId !== id || (document.activeElement !== el && el.value !== reply)) el.value = reply;
        el._cctId = id;
        if (!el._cctBlur) { el._cctBlur = true; el.addEventListener('blur', () => { if (el._cctId) this.cctSaveDraft(el._cctId); }); }
        el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 260) + 'px';
      },
      cctCanSend: hasText && !busy,
      cctSendOpacity: hasText && !busy ? '1' : '.45',
      cctSend: () => x && this.cctSend(x.id, false),
      cctSendClose: () => x && this.cctSend(x.id, true),
      cctClose: () => x && this.cctClose(x.id),
      cctOpen: () => x && this.cctOpenThread(x.id),
      cctSkip: () => this.cctStep(1),
      // The group's main action looks primary: Close, Send, or Open.
      cctClosePrimary: g === 'close' || (g === 'untriaged' && !hasText) ? '1' : '0',
      cctSendPrimary: hasText ? '1' : '0',
      cctOpenPrimary: g === 'attention' && !hasText ? '1' : '0',
      cctBusy: busy,
      cctUndo: !!t.undo, cctUndoText: t.undo ? t.undo.verb + ' “' + (t.undo.subject || 'conversation') + '”.' : '', cctUndoGo: () => this.cctUndo(),
      cctKeysHint: 'J/K next/prev · C close · S send · R reply · O open thread · 1 2 3 move · Z undo'
    });
  }

});
