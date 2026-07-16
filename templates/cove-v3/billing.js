// CaptainCore v3 — Billing real-data layer (mixin).
// GET /billing/ → { invoices:[{order_id,date,status,total}], payment_methods:
//   [{type,method{brand,last4,bank_name,account_type},expires,is_default,
//   token,verified}], subscriptions[], address:{WC billing fields} }.
// Everything is WooCommerce-backed (orders tagged captaincore_account_id).
// PDF: GET /invoices/{id}/pdf (blob). Pay: POST /billing/pay-invoice
// { value: order_id } — uses the default payment method server-side.
// Primary/delete: PUT|DELETE /billing/payment-methods/{token}[/primary].
// Adding cards/ACH needs Stripe elements — buttons hidden when real.
// Lazy-load: computeBilling schedules loadBilling() the first time the
// billing route renders hydrated (covers nav, launcher, and palette entry).

Object.assign(Component.prototype, {

  loadBilling(force) {
    if (this._billingLoading || (this._billing && !force)) return;
    this._billingLoading = true;
    this.api('/billing/').then(res => {
      this._billingLoading = false;
      this._billing = (res && !res.code) ? res : { error: (res && res.message) || 'Could not load billing.' };
      this.setState({});
    }).catch(() => { this._billingLoading = false; this._billing = { error: 'Could not load billing.' }; this.setState({}); });
  },

  downloadInvoicePdf(orderId) {
    const boot = window.CC_BOOT || {};
    fetch(boot.restRoot + 'captaincore/v1/invoices/' + orderId + '/pdf', { headers: { 'X-WP-Nonce': boot.nonce } })
      .then(r => r.blob())
      .then(blob => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'invoice-' + orderId + '.pdf';
        a.click();
        URL.revokeObjectURL(a.href);
      }).catch(() => {});
  },

  realBillingVals(s) {
    if (s.route === 'billing' && !this._billing && !this._billingLoading) setTimeout(() => this.loadBilling(), 0);
    const b = this._billing;
    if (!b) return { billShowAdd: false, billNotice: true, billNoticeText: 'Loading billing…' };
    if (b.error) return { billShowAdd: false, billNotice: true, billNoticeText: b.error, invoices: [], payMethods: [] };
    const invoices = (b.invoices || []).map(iv => {
      const paid = /completed|processing|paid|refunded/i.test(iv.status || '');
      const canPay = /pending|failed|on-hold/i.test(iv.status || '');
      return { id: '#' + iv.order_id, items: '', date: iv.date || '',
        amount: '$' + (Number(iv.total) || 0).toFixed(2),
        status: iv.status || '',
        stBg: paid ? 'var(--ok-soft)' : canPay ? 'var(--warn-soft)' : 'var(--panel-2)', stFg: 'var(--ink)',
        canPay,
        pdf: () => this.downloadInvoicePdf(iv.order_id),
        pay: () => { if (!confirm('Pay invoice #' + iv.order_id + ' ($' + iv.total + ') with your default payment method?')) return;
          this.api('/billing/pay-invoice', { method: 'POST', body: { value: iv.order_id } })
            .then(() => this.loadBilling(true)).catch(() => {}); } };
    });
    const payMethods = (b.payment_methods || []).map(pm => {
      const m = pm.method || {};
      return {
        label: (m.brand || 'Card') + ' ··' + (m.last4 || '????'),
        sub: pm.type === 'ach'
          ? [m.bank_name, m.account_type, pm.verified ? 'verified' : 'pending verification'].filter(Boolean).join(' · ')
          : (pm.expires ? 'Expires ' + pm.expires : ''),
        isPrimary: !!pm.is_default, canPrimary: !pm.is_default,
        setPrimary: () => this.api('/billing/payment-methods/' + pm.token + '/primary', { method: 'PUT' })
          .then(() => this.loadBilling(true)).catch(() => {}),
        remove: () => { if (!confirm('Remove ' + (m.brand || 'payment method') + ' ··' + (m.last4 || '') + '?')) return;
          this.api('/billing/payment-methods/' + pm.token, { method: 'DELETE' })
            .then(() => this.loadBilling(true)).catch(() => {}); } };
    });
    const a = b.address || {};
    const noticeText = !invoices.length && s.billTab === 'invoices' ? 'No invoices yet.'
      : !payMethods.length && s.billTab === 'methods' ? 'No payment methods on file.' : '';
    return {
      invoices, payMethods,
      billShowAdd: false,
      billNotice: !!noticeText, billNoticeText: noticeText,
      addrL1: [[a.first_name, a.last_name].filter(Boolean).join(' '), a.company].filter(Boolean).join(' · ') || '—',
      addrL2: [a.address_1, a.address_2].filter(Boolean).join(', '),
      addrL3: [[a.city, a.state].filter(Boolean).join(', '), a.postcode].filter(Boolean).join(' ') + (a.country ? ' · ' + a.country : ''),
      addrL4: [a.email, a.phone].filter(Boolean).join(' · ')
    };
  }

});
