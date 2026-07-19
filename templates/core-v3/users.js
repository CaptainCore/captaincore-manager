// CaptainCore v3 — Users page (operator-only fleet user management, mixin).
// Mirrors v1 core.php's Users view over the same routes:
//   GET  /users/         — list (admin-gated server-side)
//   POST /users          — create {first_name,last_name,email,login,account_ids} → {errors?}
//   GET  /users/{id}     — fetch for edit (includes account_ids)
//   PUT  /users/{id}     — update {user_id,name,first_name,last_name,email,account_ids} → {errors?}
// New users are created as subscribers with a generated password and get the
// Mailer::notify_new_user welcome email — same as v1.

Object.assign(Component.prototype, {

  USERS_SAMPLE: [
    { user_id: 1, name: 'Sarah Whitfield', username: 'sarahw', email: 'sarah@bloomandbranch.com', roles: ['customer'] },
    { user_id: 2, name: 'Austin Ginder', username: 'austinginder', email: 'austin@anchor.host', roles: ['administrator'] },
    { user_id: 3, name: 'Kara Jimenez', username: 'karaj', email: 'kara@petersonlaw.com', roles: ['customer'] }
  ],

  loadUsersPage() {
    if (this._usersLoading) return;
    this._usersLoading = true;
    this.api('/users/').then(res => {
      this._users = Array.isArray(res) ? res : [];
      this.setState({});
    }).catch(err => { console.warn('CaptainCore v3 users failed.', err); this._users = []; this.setState({}); });
  },

  userDlgDefaults() {
    return { udOpen: false, udMode: 'create', udId: 0, udFirst: '', udLast: '', udEmail: '', udLogin: '', udName: '', udAccounts: [], udAcq: '', udErrors: [], udBusy: false };
  },

  openNewUser() { this.setState({ ...this.userDlgDefaults(), udOpen: true }); },

  openEditUser(id) {
    this.setState({ ...this.userDlgDefaults(), udOpen: true, udMode: 'edit', udId: id, udBusy: true });
    this.api('/users/' + id).then(u => this.setState({
      udFirst: u.first_name || '', udLast: u.last_name || '', udEmail: u.email || '',
      udLogin: u.username || '', udName: u.name || '',
      udAccounts: (u.account_ids || []).map(String), udBusy: false
    })).catch(err => { console.warn(err); this.setState({ udErrors: ['Failed to load user.'], udBusy: false }); });
  },

  submitUserDialog() {
    const s = this.state;
    if (s.udBusy) return;
    this.setState({ udBusy: true, udErrors: [] });
    const done = res => {
      if (res && res.errors && res.errors.length) { this.setState({ udErrors: res.errors, udBusy: false }); return; }
      this._users = null; this._usersLoading = false;
      this.setState({ udOpen: false, udBusy: false });
      this.toast(s.udMode === 'create' ? 'New user added.' : 'User updated.', { kind: 'success' });
    };
    const fail = err => { console.warn(err); this.setState({ udErrors: ['Request failed. Try again.'], udBusy: false }); };
    if (s.udMode === 'create') {
      this.api('/users', { method: 'POST', body: {
        first_name: s.udFirst, last_name: s.udLast, email: s.udEmail, login: s.udLogin,
        account_ids: s.udAccounts || []
      } }).then(done).catch(fail);
    } else {
      this.api('/users/' + s.udId, { method: 'PUT', body: {
        user_id: s.udId, name: ((s.udFirst || '') + ' ' + (s.udLast || '')).trim() || s.udName,
        first_name: s.udFirst, last_name: s.udLast, email: s.udEmail,
        account_ids: s.udAccounts || []
      } }).then(done).catch(fail);
    }
  },

  computeUsersPage(s) {
    const active = s.route === 'users';
    if (active && window.CC_BOOT && !this._users) setTimeout(() => this.loadUsersPage(), 0);
    const all = this._users || (window.CC_BOOT ? [] : this.USERS_SAMPLE);
    const q = (s.uq || '').toLowerCase();
    const filtered = q ? all.filter(u => ((u.name || '') + ' ' + (u.username || '') + ' ' + (u.email || '')).toLowerCase().includes(q)) : all;
    const rows = filtered.slice(0, 250).map(u => ({
      id: u.user_id,
      name: u.name || u.username,
      initials: (((u.name || u.username || '?').trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('')) || '?').toUpperCase(),
      username: u.username,
      email: u.email,
      role: Array.isArray(u.roles) && u.roles.length ? u.roles[0] : '',
      switchUrl: u.switch_to_url || '#',
      switchDisplay: u.switch_to_url ? 'inline-flex' : 'none',
      edit: () => this.openEditUser(u.user_id),
      ctx: (e) => this.openCtxMenu(e, [
        { label: 'Edit user', act: () => this.openEditUser(u.user_id) },
        ...(u.switch_to_url ? [{ label: 'Access as ' + (u.name || u.username), act: () => { window.location.href = u.switch_to_url; } }] : []),
        { label: 'Copy email', act: () => this.ctxCopy(u.email, 'email') }
      ])
    }));
    const selected = s.udAccounts || [];
    const acq = (s.udAcq || '').toLowerCase();
    const udSuggestions = (acq ? this.ACCOUNTS.filter(a => (a.name || '').toLowerCase().includes(acq)) : [])
      .filter(a => !selected.includes(a.id)).slice(0, 6)
      .map(a => ({ id: a.id, name: a.name, pick: () => this.setState(st => ({ udAccounts: [...(st.udAccounts || []), a.id], udAcq: '' })) }));
    const udChips = selected.map(id => {
      const a = this.ACCOUNTS.find(x => x.id === id);
      return { id, name: a ? a.name : ('#' + id), remove: () => this.setState(st => ({ udAccounts: (st.udAccounts || []).filter(x => x !== id) })) };
    });
    const usersCount = filtered.length ? (filtered.length === all.length ? all.length + ' users' : filtered.length + ' of ' + all.length + ' users') : '';
    return {
      showUsers: active,
      usersRows: rows,
      usersCount,
      ...(active && usersCount ? { screenSub: usersCount, screenSubDisplay: 'inline-block' } : {}),
      usersLoading: active && !!window.CC_BOOT && !this._users,
      usersEmpty: !!this._users && filtered.length === 0,
      uq: s.uq || '', onUq: e => this.setState({ uq: e.target.value }),
      openNewUser: () => this.openNewUser(),
      udOpen: !!s.udOpen, udClose: () => this.setState({ udOpen: false }),
      udTitle: s.udMode === 'edit' ? 'Edit user' : 'Add user',
      udLoginDisplay: s.udMode === 'edit' ? 'none' : 'block',
      udFirst: s.udFirst || '', onUdFirst: e => this.setState({ udFirst: e.target.value }),
      udLast: s.udLast || '', onUdLast: e => this.setState({ udLast: e.target.value }),
      udEmail: s.udEmail || '', onUdEmail: e => this.setState({ udEmail: e.target.value }),
      udLogin: s.udLogin || '', onUdLogin: e => this.setState({ udLogin: e.target.value }),
      udAcq: s.udAcq || '', onUdAcq: e => this.setState({ udAcq: e.target.value }),
      udSuggestions, udChips,
      udErrors: (s.udErrors || []).map(text => ({ text })),
      udSubmitLabel: s.udBusy ? 'Working…' : (s.udMode === 'edit' ? 'Save user' : 'Create user'),
      udSubmit: () => this.submitUserDialog()
    };
  }

});
