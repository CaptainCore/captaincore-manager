// ── Your SSH keys (Profile) ────────────────────────────────────────────────
// Anyone signed in keeps their own public SSH keys in Profile. The Manager
// replicates them (PublicKeys, kind personal) to every environment on our
// servers whose site that person can access, beside the team keys, and keeps
// them in step as access changes (`ssh-keys deploy --stale` after each change
// and from core's cron). Kinsta sites are not touched: there, keys belong to
// the person's MyKinsta account; customers use each Kinsta site's SFTP password.
//   GET    /me/ssh-keys        → { keys:[{id,title,key_type,fingerprint,created_at}], reach, max }
//   POST   /me/ssh-keys        { title, public_key }
//   DELETE /me/ssh-keys/{id}
// The site page's Connect card (our servers) shows the count and links here.
Object.assign(Component.prototype, {

  loadMyKeys(force) {
    if ((!force && this._myKeys) || this._myKeysLoading) return;
    this._myKeysLoading = true;
    this.api('/me/ssh-keys').then(res => {
      this._myKeysLoading = false;
      this._myKeys = res && Array.isArray(res.keys) ? res : { keys: [], reach: 0, max: 10, error: (res && res.message) || 'Could not load your SSH keys' };
      this.setState({});
    }).catch(() => { this._myKeysLoading = false; this._myKeys = { keys: [], reach: 0, max: 10, error: 'Could not load your SSH keys' }; this.setState({}); });
  },

  addMyKey() {
    const text = (this.state.mkText || '').trim().replace(/\s+/g, ' ');
    if (!/^(ssh-ed25519|ssh-rsa|ecdsa-sha2-nistp(256|384|521)|sk-ssh-ed25519@openssh\.com|sk-ecdsa-sha2-nistp256@openssh\.com) [A-Za-z0-9+/]+={0,3}( .*)?$/.test(text)) {
      this.toast('Paste a public key: one line starting with ssh-ed25519, ssh-rsa or ecdsa (the .pub file, never the private key)', { kind: 'error', timeout: 7000 });
      return;
    }
    if (this.state.mkBusy) return;
    this.setState({ mkBusy: true });
    this.api('/me/ssh-keys', { method: 'POST', body: { title: (this.state.mkTitle || '').trim(), public_key: text } })
      .then(res => {
        if (!res || res.code) throw new Error((res && res.message) || 'Could not add the key');
        this._myKeys = res;
        this.setState({ mkBusy: false, mkTitle: '', mkText: '' });
        this.toast(res.reach ? 'Key added. It reaches your sites on our servers within a minute.' : 'Key added', { kind: 'success', timeout: 6000 });
      })
      .catch(e => { this.setState({ mkBusy: false }); this.toast((e && e.message) || 'Could not add the key', { kind: 'error', timeout: 8000 }); });
  },

  async removeMyKey(k) {
    if (!(await this.uiConfirm('Remove the SSH key "' + k.title + '"?\n\nIt stops working on every site within a minute.', { label: 'Remove key' }))) return;
    this.api('/me/ssh-keys/' + k.id, { method: 'DELETE' })
      .then(res => {
        if (!res || res.code) throw new Error((res && res.message) || 'Could not remove the key');
        this._myKeys = res;
        this.setState({});
        this.toast('Key removed', { kind: 'success', timeout: 4000 });
      })
      .catch(e => this.toast((e && e.message) || 'Could not remove the key', { kind: 'error', timeout: 8000 }));
  },

  // Profile → SSH keys card.
  myKeysVals(s) {
    if (s.route === 'profile') this.loadMyKeys();
    const m = this._myKeys;
    const n = m ? m.keys.length : 0;
    const reach = m ? Number(m.reach || 0) : 0;
    return {
      mkLoading: !m,
      mkEmpty: !!m && n === 0,
      mkError: (m && m.error) || '', mkHasError: !!(m && m.error),
      mkRows: (m ? m.keys : []).map(k => ({ title: k.title, meta: k.key_type + ' · ' + k.fingerprint + (k.created_at ? ' · added ' + String(k.created_at).slice(0, 10) : ''),
        remove: () => this.removeMyKey(k) })),
      mkReach: !m ? '' : reach === 0
        ? 'None of your sites run on our servers yet. Keys you add here will reach them when they do.'
        : 'Your keys sign you in over SSH and SFTP on ' + reach + ' site environment' + (reach === 1 ? '' : 's') + ' on our servers: every one you can access here, as that changes.',
      mkTitle: s.mkTitle || '', onMkTitle: e => this.setState({ mkTitle: e.target.value }),
      mkText: s.mkText || '', onMkText: e => this.setState({ mkText: e.target.value }),
      mkAdd: () => this.addMyKey(),
      mkAddLabel: s.mkBusy ? 'Adding…' : 'Add key',
      mkFull: !!m && n >= (m.max || 10)
    };
  },

  // Connect card on an environment on our servers: your keys, and where to add one.
  myKeysConnectVals() {
    this.loadMyKeys();
    const m = this._myKeys;
    const n = m ? m.keys.length : -1;
    return {
      ssetKeysShow: true,
      ssetKeysLabel: n < 0 ? '…' : n === 0 ? 'You have no SSH key yet' : n === 1 ? 'Your 1 SSH key works here' : 'Your ' + n + ' SSH keys work here',
      ssetKeysOpenDlg: () => this.setState({ route: 'profile' }),
      ssetKeysAct: n === 0 ? 'Add one in Profile →' : 'Manage in Profile →'
    };
  }
});
