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
