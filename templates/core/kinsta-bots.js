// ── Bot protection (Kinsta environments) ────────────────────────────────────
// The Settings card's Bot protection row opens this dialog: the protection
// level, AI crawlers, typical WordPress automations and exceptions of the
// environment being viewed. REST: /sites/{id}/{env}/bot-protection
// (KinstaBots), one change per request. Every change is a Kinsta operation, so
// the dialog holds its controls and polls until Kinsta has finished, then
// reads the settings again.
Object.assign(Component.prototype, {

  KBOT_LEVELS: [
    ['block-malicious-traffic', 'Block malicious traffic', 'Kinsta’s baseline for every site. Known attacks are blocked and nothing else is affected.', ''],
    ['block-automations', 'Block automations', 'Also blocks automated traffic. People and verified bots such as search engines get through.', 'Integrations, webhooks and monitors that are not verified bots are blocked unless you allow them below. Test them afterwards.'],
    ['challenge-bots', 'Challenge bots', 'Also challenges likely bots and unclassified traffic. For scraping, spam or suspicious traffic.', 'Integrations, webhooks and monitors that are not verified bots are blocked or challenged unless you allow them below. Test them afterwards.'],
    ['challenge-everyone', 'Challenge everyone', 'Challenges every visitor, people included. For a site under active attack.', 'Every visitor sees a challenge first, which can be hard or impossible for people who use assistive technology. Lower it again once the attack is over.']
  ],
  KBOT_TYPES: [['ip', 'IP address', '192.0.2.1 or 192.0.2.0/24'], ['path', 'Path', '/wp-json/'], ['user_agent', 'User agent', 'Slackbot'], ['country', 'Country', 'Two-letter code, like DE']],
  KBOT_ACTIONS: [['skip', 'Allow'], ['challenge', 'Challenge'], ['block', 'Block']],

  kbotCountry(code) {
    try { return new Intl.DisplayNames(['en'], { type: 'region' }).of(String(code).toUpperCase()) || code; } catch (e) { return code; }
  },

  openBots() {
    const key = this.siteSettingsKey();
    if (!key) return;
    this._kbot = null;
    this.setState({ kbotOpen: true, kbotKey: key, kbotPick: '', kbotType: 'ip', kbotAction: 'skip', kbotValue: '', kbotErr: '', kbotBusy: '' });
    this.loadBots(key);
  },

  closeBots() {
    clearTimeout(this._kbotPoll);
    this.setState({ kbotOpen: false });
    if (this._kbot && this._kbot.changed) this.loadSiteSettings(true);
  },

  kbotUrl(key) {
    const [siteId, env] = key.split('|');
    return '/sites/' + siteId + '/' + encodeURIComponent(env) + '/bot-protection';
  },

  loadBots(key) {
    clearTimeout(this._kbotPoll);
    const here = () => this.state.kbotOpen && this.state.kbotKey === key;
    this.api(this.kbotUrl(key)).then(res => {
      if (!here()) return;
      const prev = this._kbot && this._kbot.key === key ? this._kbot : { key };
      if (!res || res.code) {
        this._kbot = { ...prev, error: (res && res.message) || 'Could not load bot protection.' };
        this.setState({ kbotBusy: '' });
      } else if (res.pending) {
        this._kbot = { ...prev, error: '' };
        this.setState({ kbotBusy: res.pending });
        this._kbotPoll = setTimeout(() => this.loadBots(key), 3000);
      } else {
        // Only the outcome of a change made here belongs in this dialog.
        const failed = prev.waiting ? res.error : '';
        this._kbot = { ...prev, data: res, error: '', waiting: false };
        this.setState({ kbotBusy: '', kbotPick: '', kbotErr: failed || '', ...(prev.waiting && !failed ? { kbotValue: '' } : {}) });
        if (prev.waiting && !failed) this.toast('Bot protection updated. It takes effect in about a minute.', { kind: 'success', timeout: 7000 });
      }
    }).catch(err => {
      if (!here()) return;
      this._kbot = { ...(this._kbot && this._kbot.key === key ? this._kbot : { key }), error: err && err.message === 'auth' ? 'You don’t have access to this.' : 'Could not load bot protection.' };
      this.setState({ kbotBusy: '' });
    });
  },

  saveBots(body, busy) {
    const key = this.state.kbotKey;
    if (!key || this.state.kbotBusy) return;
    this.setState({ kbotBusy: busy, kbotErr: '' });
    const here = () => this.state.kbotOpen && this.state.kbotKey === key;
    this.api(this.kbotUrl(key), { method: 'POST', body }).then(res => {
      if (!res || res.code) throw new Error((res && res.message) || 'Kinsta did not accept the change.');
      this._kbot = { ...(this._kbot && this._kbot.key === key ? this._kbot : { key }), waiting: true, changed: true };
      if (!here()) { this.loadSiteSettings(true); return; }
      this.setState({ kbotBusy: res.pending || busy });
      this._kbotPoll = setTimeout(() => this.loadBots(key), 1500);
    }).catch(err => {
      const msg = err && err.message === 'auth' ? 'Your access does not allow changing bot protection for this site.' : (err && err.message) || 'Kinsta did not accept the change.';
      if (here()) this.setState({ kbotBusy: '', kbotErr: msg }); else this.toast(msg, { kind: 'error' });
    });
  },

  async removeBotRule(r) {
    if (this.state.kbotBusy) return;
    if (!(await this.uiConfirm('Remove this exception? ' + r.title))) return;
    this.saveBots({ remove: r.id }, 'Removing an exception');
  },

  kinstaBotsVals(real, s) {
    const off = { kbotOpen: false };
    if (!s.kbotOpen || s.kbotKey !== this.siteSettingsKey()) return off;
    const kb = this._kbot && this._kbot.key === s.kbotKey ? this._kbot : null;
    const d = kb && kb.data;
    const env = String(s.kbotKey).split('|')[1];
    const busy = !!s.kbotBusy;
    const edit = !!d && d.can_edit && !busy;
    const out = {
      kbotOpen: true, kbotTitle: 'Bot protection for ' + env,
      closeKbot: () => this.closeBots(),
      kbotLoading: !d && !(kb && kb.error), kbotLoadingText: busy ? s.kbotBusy + ' at Kinsta…' : 'Loading from Kinsta…',
      kbotLoadErr: (kb && kb.error) || '', kbotHasLoadErr: !!(kb && kb.error),
      kbotReady: !!d, kbotBusyShow: busy && !!d, kbotBusyText: s.kbotBusy + ' at Kinsta…',
      kbotErr: s.kbotErr || '', kbotHasErr: !!s.kbotErr,
      kbotLocked: !!d && !d.can_edit, kbotWaf: !!d && d.custom_waf, kbotEdit: !!d && d.can_edit
    };
    if (!d) return { ...out, kbotLevels: [], kbotToggles: [], kbotRules: [] };

    const pick = s.kbotPick || d.level;
    const changed = pick !== d.level;
    const raised = pick !== 'block-malicious-traffic';
    out.kbotLevels = this.KBOT_LEVELS.map(([id, label, desc]) => ({ label, desc, cur: id === d.level,
      mark: id === pick ? '●' : '', bd: id === pick ? 'var(--brand)' : 'var(--rule)', bg: id === pick ? 'var(--brand-soft)' : 'var(--paper)',
      cursor: edit ? 'pointer' : 'default', go: () => { if (edit) this.setState({ kbotPick: id, kbotErr: '' }); } }));
    const lv = this.KBOT_LEVELS.find(x => x[0] === pick);
    out.kbotWarn = changed && lv ? lv[3] : '';
    out.kbotWarnShow = !!out.kbotWarn;
    out.kbotMonitorNote = changed && raised && d.monitor_known && !d.monitor_allowed;
    out.kbotMonitorMissing = !changed && d.level !== 'block-malicious-traffic' && d.monitor_known && !d.monitor_allowed;
    out.kbotAllowMonitor = () => { if (edit) this.saveBots({ allow_monitor: true }, 'Allowing our uptime monitor'); };
    out.kbotLevelChanged = changed && d.can_edit;
    out.kbotLevelLabel = 'Change to ' + (lv ? lv[1] : pick);
    out.kbotLevelOp = edit ? '1' : '.5';
    out.kbotSaveLevel = () => { if (edit && changed) this.saveBots({ level: pick }, 'Setting bot protection to ' + (lv ? lv[1] : pick)); };
    out.kbotLevelCancel = () => this.setState({ kbotPick: '' });

    const toggle = (on, label, desc, go) => ({ label, desc, bgc: on ? 'var(--brand)' : 'var(--rule)', just: on ? 'flex-end' : 'flex-start',
      cursor: edit ? 'pointer' : 'default', op: edit ? '1' : '.55', go: () => { if (edit) go(); } });
    out.kbotToggles = [
      toggle(d.ai_blocked, 'Block AI crawlers', 'Turns away AI training crawlers such as GPTBot. Search engines, AI search and people using AI tools are not affected.',
        () => this.saveBots({ ai_blocked: !d.ai_blocked }, d.ai_blocked ? 'Unblocking AI crawlers' : 'Blocking AI crawlers')),
      toggle(d.wp_automations, 'Allow typical WordPress automations', 'Lets common WordPress tools through the stricter levels: the REST API, WooCommerce sync, form integrations, webhooks and monitors on Kinsta’s list.',
        () => this.saveBots({ wp_automations: !d.wp_automations }, d.wp_automations ? 'Turning off typical WordPress automations' : 'Allowing typical WordPress automations'))
    ];

    const typeLabel = t => { const x = this.KBOT_TYPES.find(k => k[0] === t); return x ? x[1] : t; };
    const actLabel = a => { const x = this.KBOT_ACTIONS.find(k => k[0] === a); return x ? x[1] : a; };
    const tone = { skip: ['var(--ok-soft)', 'var(--ok)'], challenge: ['var(--panel-2)', 'var(--ink-dim)'], block: ['var(--panel-2)', 'var(--bad)'] };
    out.kbotRules = (d.rules || []).map(r => {
      const value = r.type === 'country' && /^[A-Za-z]{2}$/.test(r.value) ? this.kbotCountry(r.value) : r.value;
      const t = tone[r.action] || tone.challenge;
      const row = { id: r.id, value, type: r.monitor ? 'Our uptime monitor' : typeLabel(r.type), action: actLabel(r.action), bg: t[0], fg: t[1], title: actLabel(r.action) + ' ' + value, canRemove: edit };
      row.remove = () => this.removeBotRule(row);
      return row;
    });
    const n = out.kbotRules.length;
    out.kbotCount = n + ' of ' + d.max_rules;
    out.kbotNoRules = !n;
    const type = s.kbotType || 'ip', action = s.kbotAction || 'skip', value = (s.kbotValue || '').trim();
    const chip = (on, label, go) => ({ label, go, fg: on ? 'var(--ink)' : 'var(--ink-dim)', bg: on ? 'var(--paper)' : 'transparent', shadow: on ? '0 1px 2px rgb(0 0 0/.08)' : 'none' });
    out.kbotTypes = this.KBOT_TYPES.map(([id, label]) => chip(type === id, label, () => this.setState({ kbotType: id, kbotValue: '', kbotErr: '' })));
    out.kbotActions = this.KBOT_ACTIONS.map(([id, label]) => chip(action === id, label, () => this.setState({ kbotAction: id, kbotErr: '' })));
    out.kbotValue = s.kbotValue || '';
    out.kbotPlaceholder = (this.KBOT_TYPES.find(k => k[0] === type) || [])[2] || '';
    out.onKbotValue = e => this.setState({ kbotValue: e.target.value, kbotErr: '' });
    const add = () => {
      if (!edit || !value) return;
      this.saveBots({ add: { type, action, value } }, 'Adding an exception');
    };
    out.kbotAdd = add;
    out.kbotValueKey = e => { if (e.key === 'Enter') add(); };
    out.kbotAddOp = edit && value ? '1' : '.5';
    out.kbotCountryName = type === 'country' && /^[A-Za-z]{2}$/.test(value) && this.kbotCountry(value) !== value.toUpperCase() ? this.kbotCountry(value) : '';
    out.kbotCountryShow = !!out.kbotCountryName;
    out.kbotFull = n >= d.max_rules;
    out.kbotAddShow = d.can_edit && n < d.max_rules;
    return out;
  }
});
