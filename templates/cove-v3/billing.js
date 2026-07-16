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

  // ── Add card via Stripe Elements ─────────────────────────────
  openAddCard() {
    const boot = window.CC_BOOT || {};
    if (!boot.stripeKey || !window.Stripe) { if (boot.addPaymentUrl) window.location.href = boot.addPaymentUrl; return; }
    this.setState({ cardDlgOpen: true, cardErr: '', cardSaving: false });
    // Mount after the dialog paints.
    setTimeout(() => {
      try {
        if (!this._stripe) this._stripe = window.Stripe(boot.stripeKey);
        const mount = document.getElementById('cc-card-element');
        if (!mount) return;
        if (this._cardEl) { try { this._cardEl.unmount(); } catch (e) {} }
        this._cardElements = this._stripe.elements();
        this._cardEl = this._cardElements.create('card', { hidePostalCode: false });
        this._cardEl.mount(mount);
        this._cardEl.on('change', ev => { if (ev.error) this.setState({ cardErr: ev.error.message }); else if (this.state.cardErr) this.setState({ cardErr: '' }); });
      } catch (e) { this.setState({ cardErr: 'Could not load the card form.' }); }
    }, 60);
  },

  closeAddCard() {
    if (this._cardEl) { try { this._cardEl.unmount(); } catch (e) {} this._cardEl = null; }
    this.setState({ cardDlgOpen: false, cardSaving: false, cardErr: '' });
  },

  submitCard() {
    if (!this._stripe || !this._cardEl || this.state.cardSaving) return;
    this.setState({ cardSaving: true, cardErr: '' });
    const tid = this.toast('Adding card…', { kind: 'loading' });
    this._stripe.createSource(this._cardEl, { type: 'card' }).then(result => {
      if (result.error) { this.setState({ cardSaving: false, cardErr: result.error.message }); this.dismissToast(tid); return; }
      this.api('/billing/payment-methods', { method: 'POST', body: { source_id: result.source.id } }).then(res => {
        if (res && res.error) { this.setState({ cardSaving: false, cardErr: res.error }); this.updateToast(tid, 'Card declined', { kind: 'error' }); return; }
        this.closeAddCard();
        this.updateToast(tid, 'Card added', { kind: 'success' });
        this.loadBilling(true);
      }).catch(() => { this.setState({ cardSaving: false, cardErr: 'Could not save the card.' }); this.updateToast(tid, 'Could not save the card', { kind: 'error' }); });
    }).catch(() => { this.setState({ cardSaving: false, cardErr: 'Could not process the card.' }); this.dismissToast(tid); });
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
    if (!b) return { invoices: [], payMethods: [], billShowAdd: false, billNotice: true, billNoticeText: 'Loading billing…',
      addrL1: '—', addrL2: '', addrL3: '', addrL4: '', billAddrOpen: false, openBillAddr: () => {}, closeBillAddr: () => {}, billAddrFields: [], saveBillAddr: () => {} };
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
    const boot = window.CC_BOOT || {};
    const noticeText = !invoices.length && s.billTab === 'invoices' ? 'No invoices yet.'
      : !payMethods.length && s.billTab === 'methods' ? 'No payment methods on file.' : '';
    const ADDR_FIELDS = [
      ['first_name', 'First name'], ['last_name', 'Last name'], ['company', 'Company'],
      ['address_1', 'Address 1'], ['address_2', 'Address 2'], ['city', 'City'],
      ['state', 'State'], ['postcode', 'Postcode'], ['country', 'Country'],
      ['email', 'Email'], ['phone', 'Phone']
    ];
    return {
      invoices, payMethods,
      billShowAdd: !!(boot.stripeKey || boot.addPaymentUrl),
      addPaymentMethod: () => {
        // Prefer in-SPA Stripe Elements; fall back to the WC page if the
        // library or key isn't available.
        if (boot.stripeKey && window.Stripe) { this.openAddCard(); return; }
        if (boot.addPaymentUrl) window.location.href = boot.addPaymentUrl;
      },
      cardDlgOpen: !!s.cardDlgOpen, cardErr: s.cardErr || '', cardSaving: !!s.cardSaving,
      closeAddCard: () => this.closeAddCard(),
      submitCard: () => this.submitCard(),
      billNotice: !!noticeText, billNoticeText: noticeText,
      addrL1: [[a.first_name, a.last_name].filter(Boolean).join(' '), a.company].filter(Boolean).join(' · ') || '—',
      addrL2: [a.address_1, a.address_2].filter(Boolean).join(', '),
      addrL3: [[a.city, a.state].filter(Boolean).join(', '), a.postcode].filter(Boolean).join(' ') + (a.country ? ' · ' + a.country : ''),
      addrL4: [a.email, a.phone].filter(Boolean).join(' · '),
      billAddrOpen: !!s.billAddrOpen,
      openBillAddr: () => this.setState({ billAddrOpen: true, billAddrDraft: { ...a } }),
      closeBillAddr: () => this.setState({ billAddrOpen: false }),
      billAddrFields: ADDR_FIELDS.map(([k, label]) => ({ label, v: (s.billAddrDraft || {})[k] || '',
        on: e => this.setState(st => ({ billAddrDraft: { ...st.billAddrDraft, [k]: e.target.value } })) })),
      saveBillAddr: () => {
        this.api('/billing/update', { method: 'PUT', body: { address: this.state.billAddrDraft } })
          .then(() => { this.setState({ billAddrOpen: false }); this.loadBilling(true); }).catch(() => {});
      }
    };
  }

});
