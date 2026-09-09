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

  // ── Billing details gate ─────────────────────────────────────
  // A card cannot be added or charged without a billing address: Stripe wants
  // the owner address for AVS, and WooCommerce has nothing to bill to without
  // a name and country on the customer. v1 collected this in the invoice
  // dialog before it would let you pay (dialog_invoice.customer); v3 shipped
  // the card dialog without it, so a brand-new account — which has no address
  // on file yet — could only fail its first payment. These fields are the same
  // ones v1 marked required.
  BILL_REQ: [
    ['first_name', 'First name'], ['last_name', 'Last name'], ['address_1', 'Street address'],
    ['city', 'City'], ['postcode', 'ZIP / Postal code'], ['country', 'Country'], ['email', 'Email']
  ],

  // Country / state lists ride the /billing/ response (see
  // captaincore_billing_func) — they are only needed on this screen.
  billCountries() {
    const b = this._billing;
    return (b && !b.error && Array.isArray(b.countries)) ? b.countries : [];
  },

  // States for a country, as [{ value, title }] — empty when the country has
  // none, in which case State is not required.
  billStates(country) {
    const b = this._billing;
    const map = (b && !b.error && b.states) ? b.states : {};
    const st = country ? map[country] : null;
    if (!st || Array.isArray(st)) return [];
    return Object.keys(st).map(k => ({ value: k, title: st[k] }));
  },

  // Labels of every required field this address is missing.
  billMissing(a) {
    a = a || {};
    const miss = this.BILL_REQ.filter(([k]) => !String(a[k] == null ? '' : a[k]).trim()).map(([, label]) => label);
    if (this.billStates(a.country).length && !String(a.state || '').trim()) miss.push('State');
    return miss;
  },

  // The billing address a fresh card dialog starts from: what is on file,
  // topped up with the profile name and account email so a new customer
  // normally only has to type the address itself.
  cardAddrSeed() {
    const boot = window.CC_BOOT || {};
    const a = (this._billing && !this._billing.error && this._billing.address) || {};
    const draft = {};
    ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone']
      .forEach(k => { draft[k] = a[k] == null ? '' : String(a[k]); });
    if (!draft.first_name) draft.first_name = boot.profFirst || '';
    if (!draft.last_name) draft.last_name = boot.profLast || '';
    if (!draft.email) draft.email = boot.userEmail || '';
    if (!draft.country) draft.country = 'US';
    return draft;
  },

  // ── Add card via Stripe Elements ─────────────────────────────
  // Pay-mode variant: same dialog, but submit pays the invoice with the new
  // card in one step (pay-invoice's source_id branch adds the method, pays,
  // and sets it primary server-side).
  openInvoiceCard(id) {
    this._cardPayInvoice = String(id);
    this.openAddCard();
  },

  openAddCard() {
    const boot = window.CC_BOOT || {};
    if (!boot.stripeKey || !window.Stripe) { if (boot.addPaymentUrl) window.location.href = boot.addPaymentUrl; return; }
    // The billing-details draft stays null until the customer types: it is
    // derived from whatever is on file (cardAddrSeed), so a /billing/ fetch
    // still in flight when the dialog opens fills the form in when it lands.
    if (!this._billing && !this._billingLoading) this.loadBilling();
    this.setState({ cardDlgOpen: true, cardErr: '', cardSaving: false, cardPayInvoice: this._cardPayInvoice || '',
      cardAddr: null, cardAddrEditing: false, cardAddrTried: false, ddOpen: '', ddQ: '' });
    this._cardPayInvoice = null;
    // Mount after the dialog paints.
    setTimeout(() => {
      try {
        if (!this._stripe) this._stripe = window.Stripe(boot.stripeKey);
        const mount = document.getElementById('cc-card-element');
        if (!mount) return;
        if (this._cardEl) { try { this._cardEl.unmount(); } catch (e) {} }
        this._cardElements = this._stripe.elements();
        // The billing form above collects the postcode and it rides along as
        // owner.address.postal_code, so Stripe's own ZIP box would be a second
        // ask for the same number.
        this._cardEl = this._cardElements.create('card', { hidePostalCode: true });
        this._cardEl.mount(mount);
        this._cardEl.on('change', ev => { if (ev.error) this.setState({ cardErr: ev.error.message }); else if (this.state.cardErr) this.setState({ cardErr: '' }); });
      } catch (e) { this.setState({ cardErr: 'Could not load the card form.' }); }
    }, 60);
  },

  closeAddCard() {
    if (this._cardEl) { try { this._cardEl.unmount(); } catch (e) {} this._cardEl = null; }
    this.setState({ cardDlgOpen: false, cardSaving: false, cardErr: '', cardPayInvoice: '',
      cardAddrEditing: false, ddOpen: '', ddQ: '' });
  },

  submitCard() {
    if (!this._stripe || !this._cardEl || this.state.cardSaving) return;
    const addr = this.state.cardAddr || this.cardAddrSeed();
    const miss = this.billMissing(addr);
    if (miss.length) {
      this.setState({ cardAddrEditing: true, cardAddrTried: true, cardErr: 'Billing details are required: ' + miss.join(', ') + '.' });
      return;
    }
    const payId = this.state.cardPayInvoice || '';
    this.setState({ cardSaving: true, cardErr: '' });
    const tid = this.toast(payId ? 'Processing payment…' : 'Adding card…', { kind: 'loading' });
    const fail = (msg, toastMsg) => { this.setState({ cardSaving: false, cardErr: msg });
      if (toastMsg) this.updateToast(tid, toastMsg, { kind: 'error' }); else this.dismissToast(tid); };
    // Save the address FIRST — the charge is billed to the WooCommerce
    // customer, so it has to be on file before the source is attached.
    this.api('/billing/update', { method: 'PUT', body: { address: addr } }).then(res => {
      if (res && (res.error || res.code)) { fail(String(res.error || res.message || 'Could not save billing details.')); return; }
      if (this._billing && !this._billing.error) this._billing.address = { ...addr };
      return this._stripe.createSource(this._cardEl, {
        type: 'card',
        currency: 'usd',
        owner: {
          name: [addr.first_name, addr.last_name].filter(Boolean).join(' '),
          email: addr.email,
          phone: addr.phone || undefined,
          address: {
            line1: addr.address_1, line2: addr.address_2 || undefined, city: addr.city,
            state: addr.state || undefined, postal_code: addr.postcode, country: addr.country
          }
        }
      }).then(result => {
        if (result.error) { fail(result.error.message); return; }
        const req = payId
          ? this.api('/billing/pay-invoice', { method: 'POST', body: { value: payId, source_id: result.source.id } })
          : this.api('/billing/payment-methods', { method: 'POST', body: { source_id: result.source.id } });
        return req.then(res2 => {
          if (res2 && (res2.error || res2.code)) { const msg = res2.error || res2.message || 'Card declined';
            fail(String(msg), payId ? 'Payment failed' : 'Card declined'); return; }
          this.closeAddCard();
          this.updateToast(tid, payId ? 'Payment submitted' : 'Card added', { kind: 'success' });
          if (payId) { this._invoiceView = null; this.openInvoice(payId); }
          this.loadBilling(true);
        }).catch(() => fail(payId ? 'Payment failed.' : 'Could not save the card.', payId ? 'Payment failed' : 'Could not save the card'));
      });
    }).catch(() => fail('Could not process the card.'));
  },

  // ── Add bank account (ACH via Stripe Financial Connections) ──
  // Mirrors core.php: setup-intent → collectBankAccountForSetup →
  // (confirmUsBankAccountSetup if instant) → POST ach/payment-method.
  openAddAch() {
    const boot = window.CC_BOOT || {};
    if (!boot.stripeKey || !window.Stripe) { this.toast('Bank payments unavailable', { kind: 'error' }); return; }
    const a = (this._billing && this._billing.address) || {};
    this.setState({ achDlgOpen: true, achErr: '', achSaving: false,
      achName: [a.first_name, a.last_name].filter(Boolean).join(' ') });
  },
  closeAddAch() { this.setState({ achDlgOpen: false, achSaving: false, achErr: '' }); },

  submitAch() {
    const boot = window.CC_BOOT || {};
    const name = (this.state.achName || '').trim();
    if (!name) { this.setState({ achErr: 'Enter the account holder name.' }); return; }
    if (this.state.achSaving) return;
    this.setState({ achSaving: true, achErr: '' });
    if (!this._stripe) this._stripe = window.Stripe(boot.stripeKey);
    const stripe = this._stripe;
    const email = ((this._billing && this._billing.address) || {}).email || boot.userEmail || '';
    const tid = this.toast('Connecting bank…', { kind: 'loading' });
    this.api('/billing/ach/setup-intent', { method: 'POST', body: {} }).then(setup => {
      if (!setup || setup.error || !setup.client_secret) { this.setState({ achSaving: false, achErr: (setup && setup.error) || 'Could not start bank setup.' }); this.dismissToast(tid); return; }
      const clientSecret = setup.client_secret, setupIntentId = setup.setup_intent_id;
      stripe.collectBankAccountForSetup({
        clientSecret,
        params: { payment_method_type: 'us_bank_account', payment_method_data: { billing_details: { name, email } } },
        expand: ['payment_method']
      }).then(({ setupIntent, error }) => {
        if (error) { this.setState({ achSaving: false, achErr: error.message }); this.dismissToast(tid); return; }
        if (setupIntent.status === 'requires_payment_method') { this.setState({ achSaving: false }); this.dismissToast(tid); return; } // user cancelled
        const finish = () => this.api('/billing/ach/payment-method', { method: 'POST', body: { setup_intent_id: setupIntentId } }).then(res => {
          this.setState({ achSaving: false });
          if (res && res.error) { this.setState({ achErr: res.error }); this.updateToast(tid, 'Bank not added', { kind: 'error' }); return; }
          this.setState({ achDlgOpen: false });
          this.updateToast(tid, res && res.verified ? 'Bank account added' : 'Bank added — verification pending', { kind: 'success' });
          this.loadBilling(true);
        }).catch(() => { this.setState({ achSaving: false, achErr: 'Could not save the bank account.' }); this.updateToast(tid, 'Could not save the bank account', { kind: 'error' }); });
        if (setupIntent.status === 'requires_confirmation') {
          stripe.confirmUsBankAccountSetup(clientSecret).then(({ error: cErr }) => {
            if (cErr) { this.setState({ achSaving: false, achErr: cErr.message }); this.dismissToast(tid); return; }
            finish();
          });
        } else finish();
      });
    }).catch(() => { this.setState({ achSaving: false, achErr: 'Could not start bank setup.' }); this.dismissToast(tid); });
  },

  // Billing address dialog — a method (not just a closure in realBillingVals)
  // because the invoice page opens it too when a saved card cannot be charged
  // for want of an address.
  openBillingAddress() {
    const a = (this._billing && !this._billing.error && this._billing.address) || {};
    this.setState({ billAddrOpen: true, billAddrDraft: { ...a }, billAddrTried: false });
  },

  openVerifyAch(token) { this.setState({ verifyDlgOpen: true, verifyToken: token, verifyA1: '', verifyA2: '', verifyErr: '', verifySaving: false }); },
  closeVerifyAch() { this.setState({ verifyDlgOpen: false }); },
  submitVerifyAch() {
    const a1 = parseInt(this.state.verifyA1, 10), a2 = parseInt(this.state.verifyA2, 10);
    if (!a1 || !a2 || a1 <= 0 || a2 <= 0) { this.setState({ verifyErr: 'Enter both amounts in cents.' }); return; }
    const tid = this.toast('Verifying bank…', { kind: 'loading' });
    this.api('/billing/ach/verify', { method: 'POST', body: { token_id: this.state.verifyToken, amounts: [a1, a2] } }).then(res => {
      if (res && res.error) { this.setState({ verifyErr: res.error }); this.updateToast(tid, 'Verification failed', { kind: 'error' }); return; }
      this.setState({ verifyDlgOpen: false });
      this.updateToast(tid, (res && res.message) || 'Bank account verified', { kind: 'success' });
      this.loadBilling(true);
    }).catch(() => this.updateToast(tid, 'Verification failed', { kind: 'error' }));
  },

  // ── Invoice detail page (/account/billing/{order_id}) ────────
  // `from` remembers where the invoice was opened from so the back link goes
  // there instead of Billing — an operator reading a customer invoice off the
  // account page should not land in their OWN billing screen.
  openInvoice(id, from) {
    id = String(id).replace(/^#/, '');
    this._invoiceFrom = from || null;
    this.setState({ route: 'invoice', invoiceId: id, paletteOpen: false, invPaySel: null, invPayConfirm: false });
    if (!this._hydrated) return;
    if (this._invoiceView && this._invoiceView.id === id && this._invoiceView.data) return;
    const view = this._invoiceView = { id, data: null };
    this.api('/invoices/' + id).then(d => {
      if (this._invoiceView !== view) return;
      view.data = (d && !d.code) ? d : { error: 'Could not load invoice #' + id + '.' };
      this.setState({});
    }).catch(() => { if (this._invoiceView === view) { view.data = { error: 'Could not load invoice #' + id + '.' }; this.setState({}); } });
  },

  // Props for the invoice page (spread from computeBilling).
  computeInvoice(s) {
    // WooCommerce renders line totals as HTML price spans — flatten to text.
    const txt = h => String(h || '').replace(/<[^>]*>/g, '').replace(/&#36;/g, '$').replace(/&amp;/g, '&').replace(/&nbsp;/g, ' ').trim();
    const id = s.invoiceId ? String(s.invoiceId) : '';
    const from = this._invoiceFrom;
    const back = (from && from.accountId)
      ? () => { this._invoiceFrom = null; this.openAccount(String(from.accountId)); this.setState({ accTab: 'invoices' }); }
      : () => this.setState({ route: 'billing', billTab: 'invoices' });
    const backLabel = (from && from.accountId) ? ('\u2190 ' + (from.label || 'Account')) : '\u2190 Invoices';
    let d = null, loading = false, err = '';
    if (this._hydrated) {
      const view = this._invoiceView;
      if (view && view.id === id) {
        if (view.data && view.data.error) err = view.data.error;
        else if (view.data) d = view.data;
        else loading = true;
      } else loading = true;
    } else {
      // Demo mode: synthesize a detail record from the sample row.
      const row = this.INVOICES.find(iv => String(iv.id).replace(/^#/, '') === id);
      if (row) d = { order_id: id, status: row.due && !s.paid[row.id] ? 'pending' : 'completed',
        line_items: [{ name: row.items, quantity: 1, description: [], total: row.amount }],
        payment_method: 'Visa ··4242', paid_on: row.due ? '' : row.date, total: row.amount.replace('$', ''),
        _dateLabel: row.date };
      else err = 'Invoice not found.';
    }
    const status = d ? String(d.status || '') : '';
    const paid = /completed|processing|paid|refunded/i.test(status);
    const canPay = /pending|failed|on-hold/i.test(status);
    // ── Payment section (payable invoices): saved methods with a selectable
    // row (default preselected), Pay button, and add-card-and-pay. The
    // methods ride /billing/ — load it the first time a payable invoice
    // renders. Unverified ACH accounts cannot pay, so they are left out.
    if (this._hydrated && canPay && !this._billing && !this._billingLoading) setTimeout(() => this.loadBilling(), 0);
    const bill = this._hydrated ? this._billing : null;
    const methods = this._hydrated
      ? ((bill && !bill.error && Array.isArray(bill.payment_methods)) ? bill.payment_methods.filter(pm => !(pm.type === 'ach' && !pm.verified)) : [])
      : [{ token: 'demo', is_default: true, type: 'card', expires: '12/27', method: { brand: 'Visa', last4: '4242' } }];
    const defTok = (methods.find(m => m.is_default) || methods[0] || {}).token;
    const selTok = s.invPaySel != null ? String(s.invPaySel) : (defTok != null ? String(defTok) : '');
    const amount = d ? ('$' + (Number(String(d.total).replace(/[^0-9.]/g, '')) || 0).toFixed(2)) : '';
    const invPayMethods = methods.map(pm => { const m = pm.method || {}; const on = String(pm.token) === selTok;
      const label = (m.brand || m.bank_name || 'Card') + ' ··' + (m.last4 || '????');
      return {
        label,
        sub: pm.type === 'ach' ? [m.bank_name, m.account_type].filter(Boolean).join(' · ') : (pm.expires ? 'Expires ' + pm.expires : ''),
        isDefault: !!pm.is_default,
        dotBd: on ? 'var(--brand)' : 'var(--rule)', dotBg: on ? 'var(--brand)' : 'transparent',
        rowBd: on ? 'var(--brand)' : 'var(--rule)', rowBg: on ? 'var(--brand-soft)' : 'transparent',
        pick: () => this.setState({ invPaySel: String(pm.token) }),
        remove: async (ev) => { if (ev && ev.stopPropagation) ev.stopPropagation();
          if (!(await this.uiConfirm('Remove ' + label + ' from your payment methods?'))) return;
          const tid = this.toast('Removing ' + label + '…', { kind: 'loading' });
          this.api('/billing/payment-methods/' + pm.token, { method: 'DELETE' }).then(() => {
            this.updateToast(tid, label + ' removed', { kind: 'success' });
            this.setState(st => String(st.invPaySel) === String(pm.token) ? { invPaySel: null, invPayConfirm: false } : {});
            this.loadBilling(true);
          }).catch(() => this.updateToast(tid, 'Could not remove ' + label, { kind: 'error' })); }
      }; });
    const selLabel = (methods.map(pm => pm).filter(pm => String(pm.token) === selTok).map(pm => {
      const m = pm.method || {}; return (m.brand || m.bank_name || 'Card') + ' ··' + (m.last4 || '????'); })[0]) || '';
    // A saved method still cannot be charged without a billing address on the
    // WooCommerce customer — send them to the address dialog instead of
    // letting the charge fail with a bare gateway error.
    const addrMiss = (this._hydrated && bill && !bill.error) ? this.billMissing(bill.address) : [];
    return {
      invPayMethods,
      invHasMethods: invPayMethods.length > 0,
      invNoMethods: canPay && !!bill && !bill.error && invPayMethods.length === 0,
      invPayLoadingMethods: this._hydrated && canPay && !bill,
      invPayLabel: 'Pay ' + amount,
      // Two-step in-UI confirmation (no browser confirm): the Pay button
      // swaps the footer into a confirm row naming the exact method.
      invPayConfirm: !!s.invPayConfirm && invPayMethods.length > 0,
      invPayNotConfirm: !s.invPayConfirm || invPayMethods.length === 0,
      invPayConfirmText: 'Pay ' + amount + ' with ' + (selLabel || 'the selected method') + '?',
      invPayAsk: () => {
        if (addrMiss.length) {
          this.toast('Add your billing details first — missing ' + addrMiss.join(', ') + '.', { kind: 'error' });
          this.openBillingAddress();
          return;
        }
        this.setState({ invPayConfirm: true });
      },
      invPayCancel: () => this.setState({ invPayConfirm: false }),
      invPayGo: () => {
        if (!this._hydrated) { this.setState(st => ({ paid: { ...st.paid, ['#' + id]: true }, invPayConfirm: false })); return; }
        if (!selTok) return;
        this.setState({ invPayConfirm: false });
        const tid = this.toast('Paying invoice #' + id + '…', { kind: 'loading' });
        this.api('/billing/pay-invoice', { method: 'POST', body: { value: id, payment_id: selTok } }).then(res => {
          if (res && (res.error || res.code)) { this.updateToast(tid, String(res.error || res.message || 'Payment failed'), { kind: 'error' }); return; }
          this.updateToast(tid, 'Payment submitted', { kind: 'success' });
          this._invoiceView = null;
          this.openInvoice(id);
          this.loadBilling(true);
        }).catch(() => this.updateToast(tid, 'Payment failed', { kind: 'error' }));
      },
      openInvCard: () => this._hydrated ? this.openInvoiceCard(id) : null,
      invBack: back, invBackLabel: backLabel,
      invLoading: loading,
      invErr: err, invHasErr: !!err,
      invReady: !!d,
      invTitle: 'Invoice #' + id,
      invStatus: status || '—',
      invStBg: paid ? 'var(--ok-soft)' : canPay ? 'var(--warn-soft)' : 'var(--panel-2)',
      invDate: d ? (d._dateLabel || (d.created_at && this.fmtEpoch ? this.fmtEpoch(d.created_at) : '')) : '',
      invLines: d ? (Array.isArray(d.line_items) ? d.line_items : []).map(li => ({
        name: li.name || '',
        qty: String(li.quantity || 1),
        desc: (Array.isArray(li.description) ? li.description : []).map(m => txt(m.value)).filter(Boolean).join('\n'),
        total: txt(li.total)
      })) : [],
      invTotal: d ? '$' + (Number(String(d.total).replace(/[^0-9.]/g, '')) || 0).toFixed(2) : '',
      invPayMethod: d ? txt(d.payment_method) : '',
      invPaidOn: d && d.paid_on ? String(d.paid_on) : '',
      invHasPaidOn: !!(d && d.paid_on),
      invCanPay: !!d && canPay,
      invPdf: () => this._hydrated ? this.downloadInvoicePdf(id) : null
    };
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

  // Billing-details half of the card dialog: a summary once the address is
  // complete, the form while anything required is missing (or after Edit).
  cardAddrVals(s) {
    const d = s.cardAddr || this.cardAddrSeed();
    const countries = this.billCountries();
    const states = this.billStates(d.country);
    const tried = !!s.cardAddrTried;
    const set = (k, v) => this.setState(st => ({ cardAddr: { ...(st.cardAddr || this.cardAddrSeed()), [k]: v }, cardErr: '' }));
    const row = (k, label, req, ph) => ({ label: req ? label + ' *' : label, ph: ph || '',
      v: d[k] == null ? '' : String(d[k]),
      bd: (req && tried && !String(d[k] || '').trim()) ? 'var(--bad)' : 'var(--rule)',
      on: e => set(k, e.target.value) });
    const opts = (list, cur, k) => { const nq = (s.ddQ || '').trim().toLowerCase();
      return (nq ? list.filter(o => o.title.toLowerCase().indexOf(nq) !== -1) : list).map(o => ({ label: o.title,
        mark: o.value === cur ? '\u2713' : '', bg: o.value === cur ? 'var(--brand-soft)' : 'transparent',
        // Changing country invalidates the state, which belongs to the old one.
        pick: () => this.setState(st => ({
          cardAddr: { ...(st.cardAddr || this.cardAddrSeed()), [k]: o.value, ...(k === 'country' ? { state: '' } : {}) },
          cardErr: '', ddOpen: '', ddQ: '' })) })); };
    const title = (list, v, fallback) => (list.filter(o => o.value === v).map(o => o.title)[0]) || fallback;
    const toggle = key => () => this.setState(st => ({ ddOpen: st.ddOpen === key ? '' : key, ddQ: '' }));
    const editing = !!s.cardAddrEditing || this.billMissing(d).length > 0;
    return {
      cardAddrForm: editing, cardAddrDone: !editing,
      cardAddrL1: [[d.first_name, d.last_name].filter(Boolean).join(' '), d.company].filter(Boolean).join(' \u00b7 ') || '\u2014',
      cardAddrL2: [d.address_1, d.address_2].filter(Boolean).join(', '),
      cardAddrL3: [[d.city, title(states, d.state, d.state)].filter(Boolean).join(', '), d.postcode].filter(Boolean).join(' ')
        + (d.country ? ' \u00b7 ' + title(countries, d.country, d.country) : ''),
      cardAddrL4: [d.email, d.phone].filter(Boolean).join(' \u00b7 '),
      cardAddrEdit: () => this.setState({ cardAddrEditing: true }),
      cardAddrTop: [
        row('first_name', 'First name', true), row('last_name', 'Last name', true),
        row('company', 'Company', false, 'Optional'),
        row('address_1', 'Street address', true, 'House number and street name'),
        row('address_2', 'Apt, suite', false, 'Optional'),
        row('city', 'City', true)
      ],
      cardAddrBottom: [
        row('postcode', 'ZIP / Postal', true), row('email', 'Email', true), row('phone', 'Phone', false, 'Optional')
      ],
      // Country / state pickers — the same searchable dropdown the rest of the
      // app uses. Falls back to a plain input if WooCommerce gave us no list.
      cardHasCountries: countries.length > 0, cardNoCountries: countries.length === 0,
      cardCountryLabel: title(countries, d.country, d.country || 'Select country'),
      cardCountryBd: (tried && !String(d.country || '').trim()) ? 'var(--bad)' : 'var(--rule)',
      ddCardCountryOpen: s.ddOpen === 'cardCountry', ddToggleCardCountry: toggle('cardCountry'),
      ddCardCountryOpts: opts(countries, d.country, 'country'),
      cardCountryText: d.country || '', onCardCountry: e => set('country', e.target.value),
      cardHasStates: states.length > 0, cardNoStates: states.length === 0,
      cardStateLabel: title(states, d.state, d.state || 'Select state'),
      cardStateBd: (tried && states.length && !String(d.state || '').trim()) ? 'var(--bad)' : 'var(--rule)',
      ddCardStateOpen: s.ddOpen === 'cardState', ddToggleCardState: toggle('cardState'),
      ddCardStateOpts: opts(states, d.state, 'state'),
      cardStateText: d.state || '', onCardState: e => set('state', e.target.value)
    };
  },

  realBillingVals(s) {
    if (s.route === 'billing' && !this._billing && !this._billingLoading) setTimeout(() => this.loadBilling(), 0);
    const b = this._billing;
    if (!b) return { invoices: [], payMethods: [], billShowAdd: false, billNotice: true, billNoticeText: 'Loading billing…',
      billSkelRows: Array.from({ length: 4 }, () => ({})),
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
        view: () => this.openInvoice(iv.order_id),
        pdf: () => this.downloadInvoicePdf(iv.order_id),
        // Route to the invoice page — the one pay surface with method
        // selection and its own confirm (no browser confirm, no blind
        // "default payment method" that may not exist).
        pay: () => this.openInvoice(iv.order_id) };
    });
    const payMethods = (b.payment_methods || []).map(pm => {
      const m = pm.method || {};
      const needsVerify = pm.type === 'ach' && !pm.verified;
      return {
        label: (m.brand || 'Card') + ' ··' + (m.last4 || '????'),
        sub: pm.type === 'ach'
          ? [m.bank_name, m.account_type].filter(Boolean).join(' · ')
          : (pm.expires ? 'Expires ' + pm.expires : ''),
        isPrimary: !!pm.is_default, canPrimary: !pm.is_default,
        needsVerify, verify: () => this.openVerifyAch(pm.token),
        setPrimary: () => this.api('/billing/payment-methods/' + pm.token + '/primary', { method: 'PUT' })
          .then(() => this.loadBilling(true)).catch(() => {}),
        remove: async () => { if (!(await this.uiConfirm('Remove ' + (m.brand || 'payment method') + ' ··' + (m.last4 || '') + '?'))) return;
          this.api('/billing/payment-methods/' + pm.token, { method: 'DELETE' })
            .then(() => this.loadBilling(true)).catch(() => {}); } };
    });
    const a = b.address || {};
    const boot = window.CC_BOOT || {};
    const noticeText = !invoices.length && s.billTab === 'invoices' ? 'No invoices yet.'
      : !payMethods.length && s.billTab === 'methods' ? 'No payment methods on file.' : '';
    // Required fields carry a marker so the address that a card needs is
    // obvious here too, not only inside the card dialog.
    const REQ_KEYS = this.BILL_REQ.map(([k]) => k);
    const ADDR_FIELDS = [
      ['first_name', 'First name'], ['last_name', 'Last name'], ['company', 'Company'],
      ['address_1', 'Address 1'], ['address_2', 'Address 2'], ['city', 'City'],
      ['state', 'State'], ['postcode', 'Postcode'], ['country', 'Country'],
      ['email', 'Email'], ['phone', 'Phone']
    ];
    return {
      invoices, payMethods,
      billShowAdd: !!(boot.stripeKey || boot.addPaymentUrl),
      billShowAch: !!(boot.stripeKey && window.Stripe),
      addPaymentMethod: () => {
        // Prefer in-SPA Stripe Elements; fall back to the WC page if the
        // library or key isn't available.
        if (boot.stripeKey && window.Stripe) { this.openAddCard(); return; }
        if (boot.addPaymentUrl) window.location.href = boot.addPaymentUrl;
      },
      addBankAch: () => this.openAddAch(),
      cardDlgOpen: !!s.cardDlgOpen, cardErr: s.cardErr || '', cardSaving: !!s.cardSaving,
      cardDlgTitle: s.cardPayInvoice ? 'Pay with a new card' : 'Add card',
      cardSubmitLabel: s.cardPayInvoice ? 'Add card & pay' : 'Add card',
      closeAddCard: () => this.closeAddCard(),
      submitCard: () => this.submitCard(),
      ...this.cardAddrVals(s),
      achDlgOpen: !!s.achDlgOpen, achName: s.achName || '', achErr: s.achErr || '',
      onAchName: e => this.setState({ achName: e.target.value, achErr: '' }),
      closeAddAch: () => this.closeAddAch(), submitAch: () => this.submitAch(),
      verifyDlgOpen: !!s.verifyDlgOpen, verifyA1: s.verifyA1 || '', verifyA2: s.verifyA2 || '', verifyErr: s.verifyErr || '',
      onVerifyA1: e => this.setState({ verifyA1: e.target.value, verifyErr: '' }),
      onVerifyA2: e => this.setState({ verifyA2: e.target.value, verifyErr: '' }),
      closeVerifyAch: () => this.closeVerifyAch(), submitVerifyAch: () => this.submitVerifyAch(),
      billNotice: !!noticeText, billNoticeText: noticeText,
      addrL1: [[a.first_name, a.last_name].filter(Boolean).join(' '), a.company].filter(Boolean).join(' · ') || '—',
      addrL2: [a.address_1, a.address_2].filter(Boolean).join(', '),
      addrL3: [[a.city, a.state].filter(Boolean).join(', '), a.postcode].filter(Boolean).join(' ') + (a.country ? ' · ' + a.country : ''),
      addrL4: [a.email, a.phone].filter(Boolean).join(' · '),
      billAddrOpen: !!s.billAddrOpen,
      billAddrMissing: !!this.billMissing(a).length,
      billAddrMissingText: 'Needed before a card can be added: ' + this.billMissing(a).join(', ') + '.',
      openBillAddr: () => this.openBillingAddress(),
      closeBillAddr: () => this.setState({ billAddrOpen: false }),
      billAddrFields: ADDR_FIELDS.map(([k, label]) => { const v = (s.billAddrDraft || {})[k] || '';
        const req = REQ_KEYS.indexOf(k) !== -1 || (k === 'state' && !!this.billStates((s.billAddrDraft || {}).country).length);
        return { label: req ? label + ' *' : label, v,
          bd: (req && !String(v).trim()) ? 'var(--bad)' : 'var(--rule)',
          on: e => this.setState(st => ({ billAddrDraft: { ...st.billAddrDraft, [k]: e.target.value } })) }; }),
      saveBillAddr: () => {
        this.api('/billing/update', { method: 'PUT', body: { address: this.state.billAddrDraft } })
          .then(() => { this.setState({ billAddrOpen: false }); this.loadBilling(true); }).catch(() => {});
      }
    };
  }

});
