// CaptainCore v3 — "Work on it locally" (mixin). The Deploy card's link to
// Cove (https://cove.run), the local WordPress environment: a dialog that
// shows how a site comes down to your own computer in one command, built
// from the environment's SSH details. Nothing runs from here; it shows the
// commands and copies them.
//   1. install Cove (once)
//   2. cove pull --ssh "<user>@<address> -p <port>" --site <name> [--path] [--tenant] [--proxy-uploads]
//   3. cove login <name>        (one-time sign-in link; the site is at https://<name>.localhost)
//   then, optionally, cove push to the site's staging environment.

const COVE_INSTALL = 'bash <(curl -fsSL https://cove.run/install-cove.sh)';

Object.assign(Component.prototype, {

  // The local site's name: the site's slug, plus the environment for any
  // environment but Production, so a staging copy never lands on the live one.
  coveName(real, env) {
    const base = String((real.site && real.site.site) || (real.site && real.site.name) || 'site').toLowerCase()
      .replace(/\.[a-z]+$/, '').replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40) || 'site';
    const e = String(env.environment || 'Production').toLowerCase();
    return e === 'production' ? base : base + '-' + e.replace(/[^a-z0-9]+/g, '');
  },

  coveSsh(env) {
    return '"' + env.username + '@' + env.address + ' -p ' + (env.port || 22) + '"';
  },

  // Extra flags an environment needs: its WordPress folder when it is not
  // ~/public (Cove's default), and its tenant on a WP Freighter host.
  coveFlags(env) {
    const f = [];
    const home = String(env.home_directory || '').trim();
    if (home && !/(^|\/)public\/?$/.test(home)) f.push('--path "' + home.replace(/"/g, '') + '"');
    const fr = env.details && typeof env.details === 'object' ? env.details.freighter : null;
    if (fr && fr.role === 'tenant' && fr.tenant_id) f.push('--tenant ' + String(fr.tenant_id).replace(/[^A-Za-z0-9._-]/g, ''));
    return f;
  },

  coveLocalVals(real, s) {
    const off = { coveShow: false, coveOpen: false };
    const env = real ? this.currentEnv(real, s) : null;
    if (!env || !env.address || !env.username) return off;
    const det = env.details && typeof env.details === 'object' ? env.details : {};
    if (det.start === 'plain' || (det.freighter && det.freighter.role === 'host')) return off;
    const base = { coveShow: true, coveOpenDlg: () => { this.loadMyKeys && this.loadMyKeys(); this.setState({ coveOpen: true, coveProxy: null }); } };
    if (!s.coveOpen) return { ...base, coveOpen: false };

    const site = real.site || {};
    const name = this.coveName(real, env);
    const host = env.host || site.provider || '';
    const ours = host === 'captaincore';
    const bytes = Number(env.storage) || 0;
    // Big media libraries: leave uploads on the live site (images load from it).
    const proxy = s.coveProxy === null || s.coveProxy === undefined ? bytes > 2 * 1073741824 : !!s.coveProxy;
    const pull = ['cove pull', '--ssh ' + this.coveSsh(env), ...this.coveFlags(env), '--site ' + name].concat(proxy ? ['--proxy-uploads'] : []).join(' ');
    const login = 'cove login ' + name;
    const staging = (real.envs || []).find(e => e.environment !== 'Production' && e.environment !== env.environment && e.address && e.username
      && String(e.environment).toLowerCase() === 'staging') || (env.environment !== 'Production' ? env : null);
    const push = staging ? ['cove push', '--site ' + name, '--ssh ' + this.coveSsh(staging), ...this.coveFlags(staging)].join(' ') : '';
    const keys = this._myKeys ? this._myKeys.keys.length : -1;
    const copy = (text, what) => () => this.ctxCopy(text, what);
    const sign = ours
      ? (keys === 0 ? 'Signs in with your SSH key. You have none yet: add one in Profile first.' : 'Signs in with your SSH key, the same one you use for SFTP.')
      : 'When it asks for a password, use this environment\'s SFTP password' + (env.password ? ' (copy it below)' : '') + '. With your SSH key on the site, it won\'t ask.';
    const size = bytes ? this.fmtStorage(bytes) : '';
    const homeUrl = String(env.home_url || '').replace(/^https?:\/\//, '').replace(/\/$/, '') || site.name || '';
    const chips = (real.envs || []).filter(e => e.address && e.username).map(e => ({ label: e.environment,
      fg: e.environment === env.environment ? 'var(--brand-ink)' : 'var(--ink-dim)', bg: e.environment === env.environment ? 'var(--brand-soft)' : 'transparent',
      bd: e.environment === env.environment ? 'var(--brand)' : 'var(--rule)', pick: () => this.setEnv(e.environment) }));
    return {
      ...base, coveOpen: true,
      coveClose: () => this.setState({ coveOpen: false }),
      coveFrom: homeUrl, coveFromEnv: env.environment, coveTo: name + '.localhost',
      coveEnvChips: chips, coveEnvShow: chips.length > 1,
      coveInstall: COVE_INSTALL, coveCopyInstall: copy(COVE_INSTALL, 'install command'),
      covePull: pull, coveCopyPull: copy(pull, 'pull command'),
      coveSign: sign,
      coveKeysShow: ours && keys === 0, coveKeysGo: () => this.setState({ coveOpen: false, route: 'profile' }),
      covePassShow: !ours && !!env.password, coveCopyPass: copy(String(env.password || ''), 'SFTP password'),
      coveProxyOn: proxy, coveProxyMark: proxy ? '✓' : '',
      coveProxyToggle: () => this.setState({ coveProxy: !proxy }),
      coveProxySub: (size ? 'This environment is ' + size + '. ' : '') + 'Copies everything but wp-content/uploads; images load from the live site instead. Much faster for big media libraries.',
      coveLogin: login, coveCopyLogin: copy(login, 'login command'),
      coveUrl: 'https://' + name + '.localhost',
      covePushShow: !!push, covePush: push, coveCopyPush: copy(push, 'push command'),
      covePushNote: staging ? 'Overwrites ' + (staging === env ? 'this' : 'the ' + staging.environment.toLowerCase()) + ' environment with your local copy, files and database. Check it there before it goes to production.' : ''
    };
  }
});
