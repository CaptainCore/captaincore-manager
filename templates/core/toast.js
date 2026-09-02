// CaptainCore v3 — toast / snackbar feedback (mixin).
// this.toast(text, {kind}) → id; kind: 'loading' | 'success' | 'error' | 'info'.
// 'loading' toasts persist (spinner) until updateToast/dismissToast; others
// auto-dismiss. this.updateToast(id, text, {kind}) flips a loading toast to a
// result. Rendered by toastVals() into a fixed stack (see app.html).

Object.assign(Component.prototype, {

  toast(text, opts = {}) {
    const id = 't' + Date.now() + Math.floor(Math.random() * 10000);
    const kind = opts.kind || 'info';
    this.setState(st => ({ toasts: [...(st.toasts || []), { id, text, kind }] }));
    const ms = opts.timeout != null ? opts.timeout : (kind === 'loading' ? 0 : 3200);
    if (ms) { clearTimeout(this._toastTimers && this._toastTimers[id]);
      this._toastTimers = this._toastTimers || {};
      this._toastTimers[id] = setTimeout(() => this.dismissToast(id), ms); }
    return id;
  },

  updateToast(id, text, opts = {}) {
    const kind = opts.kind || 'success';
    this.setState(st => ({ toasts: (st.toasts || []).map(t => t.id === id ? { ...t, text, kind } : t) }));
    const ms = opts.timeout != null ? opts.timeout : 3200;
    if (ms) { this._toastTimers = this._toastTimers || {};
      this._toastTimers[id] = setTimeout(() => this.dismissToast(id), ms); }
  },

  dismissToast(id) {
    this.setState(st => ({ toasts: (st.toasts || []).filter(t => t.id !== id) }));
  },

  // ── In-app confirm (replaces window.confirm) ────────────────────────
  // await this.uiConfirm('Delete X? This cannot be undone.', { label, danger, title, sub })
  // → Promise<boolean>. A plain message splits at its first "?" into title +
  // body (a leading statement before the question moves into the body);
  // "\n\n" starts a new paragraph. The action button defaults to the title's
  // verb for Delete / Remove / Revoke, else "Confirm"; danger styling (red
  // button) is inferred from destructive verbs or "cannot be undone". Only
  // one confirm is open at a time — a newer request cancels the older one.
  uiConfirm(msg, opts = {}) {
    if (this._uc && this._uc.resolve) this._uc.resolve(false);
    const text = String(msg == null ? '' : msg);
    let title = opts.title || '', body = text;
    if (!opts.title) {
      const q = text.indexOf('?');
      if (q > -1 && q < 140) {
        let head = text.slice(0, q + 1).trim(); body = text.slice(q + 1).trim();
        const lead = head.lastIndexOf('. ');
        if (lead > -1) { body = (head.slice(0, lead + 1) + (body ? '\n\n' + body : '')); head = head.slice(lead + 2); }
        title = head;
      } else { title = 'Are you sure?'; }
    }
    const verb = (title.match(/^([A-Z][a-z]+)/) || [])[1] || '';
    const destructive = /^(Delete|Remove|Revoke|Kill|Cancel|Overwrite|Migrate)$/.test(verb) || /cannot be undone|OVERWRITES/i.test(text);
    const label = opts.label || (/^(Delete|Remove|Revoke)$/.test(verb) ? verb : 'Confirm');
    return new Promise(resolve => {
      this._uc = { title, sub: opts.sub || '', paras: body.split(/\n\s*\n/).map(t => t.trim()).filter(Boolean), label,
        danger: opts.danger != null ? !!opts.danger : destructive, resolve };
      this.setState({ ucOpen: true });
    });
  },

  ucResolve(ok) {
    const uc = this._uc; this._uc = null;
    this.setState({ ucOpen: false });
    if (uc && uc.resolve) uc.resolve(!!ok);
  },

  confirmVals(s) {
    const uc = s.ucOpen && this._uc ? this._uc : null;
    return {
      ucOpen: !!uc,
      ucTitle: uc ? uc.title : '', ucSub: uc ? uc.sub : '', ucHasSub: !!(uc && uc.sub),
      ucParas: uc ? uc.paras.map(t => ({ t })) : [],
      ucHasBody: !!(uc && uc.paras.length),
      ucLabel: uc ? uc.label : 'Confirm',
      ucBtnBg: uc && uc.danger ? 'var(--bad)' : 'var(--brand)',
      ucGo: () => this.ucResolve(true),
      closeUc: () => this.ucResolve(false)
    };
  },

  toastVals() {
    return (this.state.toasts || []).map(t => ({
      id: t.id, text: t.text,
      spinner: t.kind === 'loading', notSpinner: t.kind !== 'loading',
      dot: t.kind === 'success' ? 'var(--ok)' : t.kind === 'error' ? 'var(--bad)'
        : t.kind === 'loading' ? 'var(--brand)' : 'var(--ink-dim)',
      close: () => this.dismissToast(t.id)
    }));
  }

});
