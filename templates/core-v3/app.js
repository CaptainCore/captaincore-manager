// CaptainCore v3 — application logic. Forked 2026-07-16 from the Claude Design
// project "Anchor Hosting UI Revamp" (Anchor Home.dc.html); hand-maintained since.
// Evaluated by the DC runtime (public/js/v3/support.js); companion mixins in
// this directory extend Component.prototype after this class definition.
class Component extends DCLogic {
  state = {
    route: 'home', theme: null, dockOpen: false, paletteOpen: false, ctxMenu: null,
    palQuery: '', palIdx: 0, tick: 0,
    view: 'table', q: '', fProv: 'Any', fHealth: 'All', sel: {},
    fUnassigned: false, fBackup: 'Any', fCore: 'Any', fTheme: 'Any',
    fPlugin: 'Any', fPlugVer: 'Any', fPlugStatus: 'Any', fPlugIs: 'IS', fOp: 'AND', labelsSel: {},
    siteId: null, siteTab: 'overview', env: 'Production', addonKind: 'plugins',
    qsOpen: '', bkOpen: '', logFile: 'error.log', copied: '',
    qsFile: '', diffMode: 'unified', bkDirs: { 'wp-content/': true }, bkPreview: '', bkSel: {},
    qsDialog: '', qsView: 'components', rbComp: '', bkDialog: '', shared: null, shareDraft: '',
    timeline: null, tlDraft: '', tlEdit: 0, tlEditText: '',
    nsOpen: false, nsPath: 'request', nsName: '', nsNotes: '', nsAddr: '', nsUser: '', nsPass: '',
    nsAcc: 'Bloom & Branch Floral', nsDc: 'US Central (Iowa)', nsClone: 'None (fresh install)',
    nsProv: 'Kinsta', nsEnvs: 'Production only', nsImportSel: {},
    ndOpen: false, ndName: '', ndAcc: 'Bloom & Branch Floral', ndZone: true, domList: null,
    naOpen: false, naName: '', naMsg: '', billAddrOpen: false, billAddrDraft: {},
    dnsEdit: 0, dnsEN: '', dnsEV: '', dnsETtl: '',
    zoneOpen: false, zoneText: '', nsvOpen: false, nsvText: '', nsCustom: null,
    ctOpen: false, contact: null, ctDraft: {},
    snapFilter: 'Everything', dq: '', domainId: null, domTab: 'dns',
    dnsRecs: null, dnsDirty: false, dnsT: 'A', dnsN: '', dnsV: '',
    fwds: null, fwdAlias: '', fwdDest: '', reg: { auto: true, lock: true, priv: true },
    ddOpen: '', ddQ: '',
    aq: '', accountId: null, accTab: 'users', accInvites: null, trusted: null,
    invEmail: '', invLevel: 'Full access', billTab: 'invoices', paid: {}, primaryPm: 0,
    statG: 'Daily', statR: 'Last 28 days', statShare: 'Off', statPw: '',
    secTab: 'vulns', threatOpen: '', threatStatus: {}, tNotes: null, noteDraft: '', ckOpen: '',
    audits: null, audSite: 'bloomandbranch.com', audTypes: { Core: true, Plugins: true }, 
    repMode: 'Site', repTarget: 'bloomandbranch.com', repRange: 'Last month', repInt: 'Monthly',
    repEmail: '', schedules: null, repSendMsg: '', repPreviewOpen: false, repPreviewHtml: '', repPreviewLoading: false,
    archList: null, archUrl: '', archErr: false,
    setTab: 'branding', brandName: 'Anchor Hosting', keyDraft: '', sshKeys: null,
    recipeDlgOpen: false, recipeEditId: null, recipeTitle: '', recipeContent: '', recipePublic: false,
    procDlgOpen: false, procDlgName: '', procDlgBody: '',
    defDlgOpen: false, defEmail: '', defTimezone: '', defRecipes: [], defUsers: [],
    provDlgOpen: false, provEditId: null, provName: '', provType: '', provCreds: [],
    schedEditOpen: false, schedEditId: null, schedEditInt: 'Monthly', schedEditEmail: '',
    transferOpen: false, transferPick: null, toasts: [],
    cardDlgOpen: false, cardErr: "", cardSaving: false, chartHoverIdx: -1, chartHoverX: 0, chartHoverY: 0,
    achDlgOpen: false, achName: '', achErr: '', achSaving: false,
    verifyDlgOpen: false, verifyToken: null, verifyA1: '', verifyA2: '', verifyErr: '', verifySaving: false,
    profName: 'Austin Ginder', profEmail: 'austin@anchor.host', tfa: 'off', tfaCode: '', appPw: '', sessions: null,
    tpOpen: false, tpQ: '', termSel: [], cookOpen: false, cookQ: '',
    aaOpen: false, aaTab: 'upload', aaQ: '', aaEQ: '', aaDrag: false,
    shareDlgOpen: false, shareEmail: '', shareErr: '', shareSending: false, shareLoading: false,
    jobs: window.CC_BOOT ? [] : [
      { id: 1, label: 'update-wp', target: '3 sites · steer queue', state: 'running', pct: 64 },
      { id: 2, label: 'backup', target: 'cascadecoffeeroasters.com', state: 'running', pct: 31 },
      { id: 3, label: 'quicksave', target: 'bloomandbranch.com', state: 'done', right: '12m ago' },
      { id: 4, label: 'sync-data', target: 'petersonlaw.com', state: 'done', right: '38m ago' }
    ]
  };

  FLEET = window.CC_BOOT ? [] : [
    { id: 'bloom', name: 'bloomandbranch.com', provider: 'Kinsta', account: 'Bloom & Branch Floral', core: '6.8.1', visits: '12,400', storage: '2.1 GB', envs: 'Prod · Staging', updates: 2, vuln: 1, owned: true, theme: 'kadence', backup: 'Direct', labels: [], plugins: { gravityforms: { v: '2.9.1', status: 'active' }, woocommerce: { v: '9.8.2', status: 'active' }, 'wordpress-seo': { v: '25.3', status: 'active' } } },
    { id: 'cascade', name: 'cascadecoffeeroasters.com', provider: 'Kinsta', account: 'Cascade Coffee', core: '6.8.0', visits: '8,900', storage: '4.6 GB', envs: 'Prod', updates: 2, vuln: 0, theme: 'kadence', backup: 'Direct', labels: [], plugins: { woocommerce: { v: '9.9.0', status: 'active' }, jetpack: { v: '14.2', status: 'inactive' } } },
    { id: 'peterson', name: 'petersonlaw.com', provider: 'WP Engine', account: 'Peterson Law', core: '6.8.1', visits: '3,100', storage: '1.2 GB', envs: 'Prod · Staging', updates: 5, vuln: 0, theme: 'astra', backup: 'Local', labels: [], plugins: { gravityforms: { v: '2.9.4', status: 'active' }, 'advanced-custom-fields': { v: '6.3.7', status: 'active' } } },
    { id: 'harbor', name: 'harborlightyoga.com', provider: 'Kinsta', account: 'Harbor Light Yoga', core: '6.8.1', visits: '1,700', storage: '860 MB', envs: 'Prod', updates: 3, vuln: 1, owned: true, theme: 'kadence', backup: 'Direct', labels: [], plugins: { gravityforms: { v: '2.9.1', status: 'active' }, jetpack: { v: '13.9', status: 'active' } } },
    { id: 'midwest', name: 'midwestmakersmarket.com', provider: 'Rocket.net', account: 'Midwest Makers', core: '6.8.1', visits: '5,300', storage: '3.4 GB', envs: 'Prod', updates: 0, vuln: 0, theme: 'generatepress', backup: 'Direct', labels: [], plugins: { 'advanced-custom-fields': { v: '6.7.0', status: 'active' }, 'wordpress-seo': { v: '25.3', status: 'active' } } },
    { id: 'wildflower', name: 'thewildflowerpantry.com', provider: 'Kinsta', account: 'Wildflower Pantry', core: '6.8.1', visits: '2,200', storage: '1.8 GB', envs: 'Prod', updates: 0, vuln: 0, owned: true, theme: 'astra', backup: 'Direct', labels: [], plugins: { jetpack: { v: '14.2', status: 'active' } } },
    { id: 'stonebridge', name: 'stonebridgedental.com', provider: 'GridPane', account: 'Stonebridge Dental', core: '6.7.2', visits: '940', storage: '620 MB', envs: 'Prod', updates: 7, vuln: 0, theme: 'twentytwentythree', backup: 'Off', labels: ['down'], unassigned: true, plugins: { 'query-monitor': { v: '3.19.0', status: 'inactive' } } },
    { id: 'lakeside', name: 'lakesideinn.com', provider: 'Kinsta', account: 'Lakeside Inn', core: '6.8.1', visits: '4,100', storage: '2.9 GB', envs: 'Prod · Staging', updates: 0, vuln: 0, owned: true, theme: 'kadence', backup: 'Direct', labels: ['moved'], plugins: { woocommerce: { v: '9.9.0', status: 'active' }, jetpack: { v: '14.2', status: 'active' } } }
  ];
  PLUGINS = window.CC_BOOT ? [] : [
    { name: 'Gravity Forms', slug: 'gravityforms', v: '2.9.1', latest: '2.9.4', active: true },
    { name: 'WooCommerce', slug: 'woocommerce', v: '9.8.2', latest: '9.9.0', active: true },
    { name: 'Yoast SEO', slug: 'wordpress-seo', v: '25.3', latest: '25.3', active: true },
    { name: 'Kadence Blocks', slug: 'kadence-blocks', v: '3.5.12', latest: '3.5.12', active: true },
    { name: 'WP Rocket', slug: 'wp-rocket', v: '3.18.1', latest: '3.18.1', active: true },
    { name: 'Query Monitor', slug: 'query-monitor', v: '3.19.0', latest: '3.19.0', active: false }
  ];
  THEMES = window.CC_BOOT ? [] : [
    { name: 'Kadence', slug: 'kadence', v: '1.2.14', latest: '1.2.15', active: true },
    { name: 'Twenty Twenty-Five', slug: 'twentytwentyfive', v: '1.2', latest: '1.2', active: false }
  ];
  QUICKSAVES = window.CC_BOOT ? [] : [
    { hash: '8f3c21a', kind: 'Update', desc: 'gravityforms 2.9.1 → 2.9.4', files: '129 files changed', when: 'Today · 9:14 AM', summary: '129 files changed · +59,118 −5,763 · WP 6.8.1 · 2 themes · 6 plugins', more: 122 },
    { hash: 'b2e90d4', kind: 'Scheduled', desc: 'Nightly quicksave — no changes', files: '0 files', when: 'Yesterday · 11:02 PM', summary: '0 files changed · WP 6.8.1 · 2 themes · 6 plugins', more: 0 },
    { hash: '77aa1fe', kind: 'Manual', desc: 'Before homepage redesign', files: '12 files changed', when: 'Jul 12 · 2:31 PM', summary: '12 files changed · +1,842 −960 · WP 6.8.1 · 2 themes · 6 plugins', more: 5 },
    { hash: 'c30d88b', kind: 'Update', desc: 'WordPress core 6.8.0 → 6.8.1', files: '41 files changed', when: 'Jul 10 · 3:00 AM', summary: '41 files changed · +12,406 −11,988 · WP 6.8.0 → 6.8.1', more: 34 }
  ];
  QS_COMPONENTS = window.CC_BOOT ? [] : [
    { kind: 'theme', name: 'Kadence', from: '1.2.14', to: '1.2.14', status: 'active' },
    { kind: 'theme', name: 'Twenty Twenty-Five', from: '1.2', to: '1.2', status: 'inactive' },
    { kind: 'plugin', name: 'CaptainCore Helm', from: '1.0.1', to: '', status: 'active', deleted: true },
    { kind: 'plugin', name: 'Minn Admin', from: '', to: '0.10.0', status: 'active', added: true, viewFile: 'wp-content/plugins/minn-admin/changelog.md' },
    { kind: 'plugin', name: 'Gravity Forms', from: '2.9.1', to: '2.9.4', status: 'active', updated: true, viewFile: 'wp-content/plugins/gravityforms/gravityforms.php' },
    { kind: 'plugin', name: 'WooCommerce', from: '9.8.2', to: '9.8.2', status: 'active' },
    { kind: 'plugin', name: 'Yoast SEO', from: '25.3', to: '25.3', status: 'active' },
    { kind: 'plugin', name: 'advanced-cache.php', from: '', to: '', status: 'dropin' }
  ];
  QS_FILES = window.CC_BOOT ? [] : [
    { path: 'wp-content/plugins/gravityforms/gravityforms.php', st: 'M', add: 2, del: 2, diff: [
      ['ctx', '@@ -15,7 +15,7 @@'],
      ['ctx', '   * Plugin Name: Gravity Forms'],
      ['del', '-  * Version: 2.9.1'],
      ['add', '+  * Version: 2.9.4'],
      ['ctx', '   * Author: Gravity Forms'],
      ['ctx', '@@ -1102,7 +1102,7 @@'],
      ['del', "-  define( 'GF_MIN_WP_VERSION', '6.4' );"],
      ['add', "+  define( 'GF_MIN_WP_VERSION', '6.5' );"]
    ] },
    { path: 'wp-content/plugins/gravityforms/common.php', st: 'M', add: 4, del: 1, diff: [
      ['ctx', '@@ -1042,6 +1042,9 @@'],
      ['add', "+    if ( ! current_user_can( 'edit_posts' ) ) {"],
      ['add', "+        return new WP_Error( 'forbidden', 'Access denied.' );"],
      ['add', '+    }'],
      ['ctx', '     $form = self::get_form( $form_id );'],
      ['ctx', '@@ -2210,7 +2210,7 @@'],
      ['del', "-        $nonce = wp_create_nonce( 'gf_api' );"],
      ['add', "+        $nonce = wp_create_nonce( 'gf_api_v2' );"]
    ] },
    { path: 'wp-content/plugins/gravityforms/js/gravityforms.min.js', st: 'M', add: 1, del: 1, diff: [
      ['ctx', '(minified asset — 1 chunk changed)'],
      ['del', '-var gf_global_version="2.9.1";'],
      ['add', '+var gf_global_version="2.9.4";']
    ] },
    { path: 'wp-content/plugins/minn-admin/changelog.md', st: 'A', add: 341, del: 0, diff: [
      ['ctx', 'new file mode 100644 · @@ -0,0 +1,341 @@'],
      ['add', '+# Changelog'],
      ['add', '+'],
      ['add', '+## v0.10.0 — July 10, 2026'],
      ['add', '+'],
      ['add', '+The surfaces release. One nav item per job, with every capable plugin layered in behind it: Forms, Email Log, Activity Log, Snippets, Redirects and Backups are first-class views with a provider switcher.'],
      ['add', '+'],
      ['add', '+### Added'],
      ['add', '+* Spam settings page showing who filters comment spam.'],
      ['add', '+* Integrations diagnostics on the System page.']
    ] },
    { path: 'wp-content/plugins/minn-admin/assets/js/app.js', st: 'A', add: 1204, del: 0, diff: [
      ['ctx', 'new file — 1,204 lines'],
      ['add', '+(function () {'],
      ['add', '+  const minn = { surfaces: [], providers: new Map() };'],
      ['add', '+  // …'],
      ['add', '+})();']
    ] },
    { path: 'wp-content/plugins/minn-admin/LICENSE', st: 'A', add: 21, del: 0, diff: [
      ['ctx', 'new file mode 100644'],
      ['add', '+GNU GENERAL PUBLIC LICENSE Version 2'],
      ['add', '+Copyright (c) 2026 Minn']
    ] },
    { path: 'wp-content/plugins/captaincore-helm/helm.php', st: 'D', add: 0, del: 812, diff: [
      ['ctx', 'deleted file mode 100644'],
      ['del', '-<?php'],
      ['del', '-/* Plugin Name: CaptainCore Helm */'],
      ['del', '-/* Version: 1.0.1 */']
    ] }
  ];
  BACKUPS = window.CC_BOOT ? [] : [
    { id: 'a81f03c2', when: 'Today · 3:00 AM', size: '2.1 GB', files: '18,442 files' },
    { id: '59be77d1', when: 'Yesterday · 3:00 AM', size: '2.1 GB', files: '18,438 files' },
    { id: '02c1f9ae', when: 'Jul 13 · 3:00 AM', size: '2.0 GB', files: '18,401 files' },
    { id: 'edd04b7c', when: 'Jul 12 · 3:00 AM', size: '2.0 GB', files: '18,394 files' }
  ];
  BK_TREE = window.CC_BOOT ? [] : [
    { p: 'wp-config.php', meta: '3.2 KB', file: true, prev: true, cnt: 1, kb: 3.2 },
    { p: 'mysql.sql', meta: '48 MB · database dump', file: true, cnt: 1, kb: 49152 },
    { p: 'wp-admin/', meta: '1,204 files · 38 MB', dir: true, cnt: 1204, kb: 38912, children: [
      { p: 'wp-admin/about.php', meta: '12 KB', file: true, cnt: 1, kb: 12 },
      { p: 'wp-admin/admin-ajax.php', meta: '4 KB', file: true, cnt: 1, kb: 4 }
    ] },
    { p: 'wp-includes/', meta: '2,488 files · 64 MB', dir: true, cnt: 2488, kb: 65536, children: [] },
    { p: 'wp-content/', meta: '17,939 files · 1.9 GB', dir: true, cnt: 17939, kb: 1992294, children: [
      { p: 'wp-content/uploads/', meta: '12,204 files · 1.4 GB', dir: true, cnt: 12204, kb: 1468006, children: [
        { p: 'wp-content/uploads/2026/07/hero-summer.jpg', meta: '1.2 MB', file: true, cnt: 1, kb: 1228 },
        { p: 'wp-content/uploads/2026/07/menu-july.pdf', meta: '420 KB', file: true, cnt: 1, kb: 420 }
      ] },
      { p: 'wp-content/themes/kadence/', meta: '388 files · 12 MB', dir: true, cnt: 388, kb: 12288, children: [
        { p: 'wp-content/themes/kadence/functions.php', meta: '18 KB', file: true, prev: true, cnt: 1, kb: 18 },
        { p: 'wp-content/themes/kadence/style.css', meta: '6 KB', file: true, prev: true, cnt: 1, kb: 6 }
      ] },
      { p: 'wp-content/plugins/', meta: '4,102 files · 96 MB', dir: true, cnt: 4102, kb: 98304, children: [
        { p: 'wp-content/plugins/gravityforms/', meta: '412 files · 18 MB', dir: true, cnt: 412, kb: 18432, children: [] },
        { p: 'wp-content/plugins/woocommerce/', meta: '2,381 files · 54 MB', dir: true, cnt: 2381, kb: 55296, children: [] }
      ] },
      { p: 'wp-content/cache/', meta: 'omitted — still restorable', omitted: true, dir: true }
    ] }
  ];
  PREVIEWS = window.CC_BOOT ? {} : {
    'wp-config.php': ['<?php', "define( 'DB_NAME', 'wp_bloomandbranch' );", "define( 'DB_USER', 'bloom_db' );", "define( 'DB_PASSWORD', '************' );", "define( 'WP_DEBUG', false );", "define( 'WP_MEMORY_LIMIT', '256M' );", "$table_prefix = 'wp_';"],
    'wp-content/themes/kadence/style.css': ['/*', ' Theme Name: Kadence', ' Version: 1.2.14', '*/', ':root { --global-palette1: #2c3e50; }'],
    'wp-content/themes/kadence/functions.php': ['<?php', "define( 'KADENCE_VERSION', '1.2.14' );", "require_once get_template_directory() . '/inc/init.php';"],
    default: ['(binary file — no inline preview, use Download)']
  };
  WP_USERS = window.CC_BOOT ? [] : [
    { n: 'Sarah Whitfield', e: 'sarah@SITE', role: 'Administrator', last: '2h ago' },
    { n: 'Austin Ginder', e: 'austin@anchor.host', role: 'Administrator', last: '1d ago' },
    { n: 'Maya Chen', e: 'maya@SITE', role: 'Editor', last: '6d ago' }
  ];
  LOGS = window.CC_BOOT ? {} : {
    'error.log': [
      '[16-Jul-2026 09:14:22 UTC] PHP Warning:  Undefined array key "size" in /www/wp-content/plugins/woocommerce/includes/wc-cart-functions.php on line 118',
      '[16-Jul-2026 08:52:10 UTC] PHP Deprecated:  _load_textdomain_just_in_time called incorrectly for domain "kadence".',
      '[16-Jul-2026 07:03:41 UTC] PHP Warning:  Attempt to read property "post_type" on null in /www/wp-includes/post.php on line 1067',
      '[15-Jul-2026 22:18:03 UTC] PHP Fatal error:  Allowed memory size exhausted (tried to allocate 2.6 MB) in /www/wp-includes/class-wpdb.php',
      '[15-Jul-2026 20:44:56 UTC] PHP Warning:  Undefined variable $args in /www/wp-content/themes/kadence/inc/template-tags.php on line 44'
    ],
    'access.log': [
      '203.0.113.4 - - [16/Jul/2026:09:12:01 +0000] "GET / HTTP/2" 200 18442 "-" "Mozilla/5.0"',
      '198.51.100.7 - - [16/Jul/2026:09:11:48 +0000] "POST /wp-cron.php HTTP/2" 200 0 "-" "WordPress/6.8.1"',
      '203.0.113.9 - - [16/Jul/2026:09:11:32 +0000] "GET /shop/ HTTP/2" 200 24810 "-" "Mozilla/5.0"',
      '192.0.2.14 - - [16/Jul/2026:09:10:58 +0000] "GET /wp-login.php HTTP/1.1" 403 146 "-" "python-requests/2.32"'
    ],
    'debug.log': [
      '[16-Jul-2026 09:00:12 UTC] CaptainCore: sync-data completed in 4.2s',
      '[16-Jul-2026 03:00:08 UTC] CaptainCore: restic snapshot a81f03c2 created',
      '[16-Jul-2026 03:00:01 UTC] Cron: captaincore_nightly_quicksave started'
    ]
  };

  SNAPSHOTS = window.CC_BOOT ? [] : [
    { id: 'snap_4c8aa', name: 'db-only-checkout-bug', when: 'Today · 8:02 AM', size: '48 MB', filter: 'Database', expires: '23h left' },
    { id: 'snap_9f2e1', name: 'pre-redesign-full', when: 'Jul 12 · 2:12 PM', size: '2.4 GB', filter: 'Everything', expires: 'expired' },
    { id: 'snap_77b03', name: 'uploads-june', when: 'Jun 30 · 4:44 PM', size: '1.4 GB', filter: 'Uploads', expires: 'expired' }
  ];
  DOMAINS = window.CC_BOOT ? [] : [
    { id: 'bloomd', name: 'bloomandbranch.com', account: 'Bloom & Branch Floral', registrar: 'Hover', dns: true, expires: 'Mar 12, 2027', auto: true, owned: true },
    { id: 'harbord', name: 'harborlightyoga.com', account: 'Harbor Light Yoga', registrar: 'Hover', dns: true, expires: 'Jul 28, 2026', auto: false, warn: true, owned: true },
    { id: 'petersond', name: 'petersonlaw.com', account: 'Peterson Law', registrar: 'Spaceship', dns: true, expires: 'Nov 3, 2026', auto: true },
    { id: 'wildflowerd', name: 'thewildflowerpantry.com', account: 'Wildflower Pantry', registrar: 'Hover', dns: true, expires: 'Feb 9, 2027', auto: true, owned: true },
    { id: 'midwestd', name: 'midwestmakersmarket.com', account: 'Midwest Makers', registrar: 'Spaceship', dns: true, expires: 'Sep 17, 2026', auto: true },
    { id: 'cascaded', name: 'cascadecoffeeroasters.com', account: 'Cascade Coffee', registrar: 'External (GoDaddy)', dns: false, expires: '—', auto: null },
    { id: 'lakesided', name: 'lakesideinn.com', account: 'Lakeside Inn', registrar: 'Hover', dns: true, expires: 'Jan 22, 2027', auto: true, owned: true }
  ];
  DNS_RECS = window.CC_BOOT ? [] : [
    { uid: 1, type: 'A', name: '@', value: '35.223.94.108', ttl: '3600' },
    { uid: 2, type: 'CNAME', name: 'www', value: '@', ttl: '3600' },
    { uid: 3, type: 'MX', name: '@', value: '10 mxa.mailgun.org', ttl: '3600' },
    { uid: 4, type: 'MX', name: '@', value: '10 mxb.mailgun.org', ttl: '3600' },
    { uid: 5, type: 'TXT', name: '@', value: 'v=spf1 include:mailgun.org ~all', ttl: '3600' },
    { uid: 6, type: 'TXT', name: 'krs._domainkey', value: 'k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GN…', ttl: '3600' },
    { uid: 7, type: 'CNAME', name: 'email', value: 'mailgun.org', ttl: '3600' }
  ];
  FWDS = window.CC_BOOT ? [] : [
    { uid: 1, alias: 'hello', dest: 'sarah.whitfield@gmail.com', status: 'Verified' },
    { uid: 2, alias: 'orders', dest: 'sarah.whitfield@gmail.com', status: 'Verified' },
    { uid: 3, alias: '*', dest: 'sarah.whitfield@gmail.com', status: 'Catch-all' }
  ];
  MG_RECS = window.CC_BOOT ? [] : [
    { type: 'TXT', host: 'mg', value: 'v=spf1 include:mailgun.org ~all', ok: true },
    { type: 'TXT', host: 'krs._domainkey.mg', value: 'k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GN…', ok: true },
    { type: 'CNAME', host: 'email.mg', value: 'mailgun.org', ok: false }
  ];
  MG_EVENTS = window.CC_BOOT ? [] : [
    { t: '9:18 AM', text: 'Delivered · Order receipt #4521 → customer@gmail.com' },
    { t: '8:47 AM', text: 'Delivered · Contact form → sarah@bloomandbranch.com' },
    { t: '7:02 AM', text: 'Opened · July newsletter (214 of 1,180 so far)' },
    { t: 'Yesterday', text: 'Bounced · promo@oldclient.net (mailbox full) — suppressed' }
  ];

  ACCOUNTS = window.CC_BOOT ? [] : [
    { id: 'bloomacc', name: 'Bloom & Branch Floral', users: 3, sites: 2, domains: 1, plan: 'Growth · $68/mo', due: true, owned: true },
    { id: 'petersonacc', name: 'Peterson Law', users: 3, sites: 1, domains: 1, plan: 'Standard · $45/mo' },
    { id: 'cascadeacc', name: 'Cascade Coffee', users: 1, sites: 1, domains: 1, plan: 'Growth · $68/mo' },
    { id: 'harboracc', name: 'Harbor Light Yoga', users: 1, sites: 1, domains: 1, plan: 'Starter · $25/mo', owned: true },
    { id: 'midwestacc', name: 'Midwest Makers', users: 2, sites: 1, domains: 1, plan: 'Standard · $45/mo' },
    { id: 'wildfloweracc', name: 'Wildflower Pantry', users: 1, sites: 1, domains: 1, plan: 'Starter · $25/mo', owned: true },
    { id: 'lakesideacc', name: 'Lakeside Inn', users: 1, sites: 1, domains: 1, plan: 'Growth · $68/mo', owned: true }
  ];
  ACC_USERS = window.CC_BOOT ? [] : [
    { n: 'Sarah Whitfield', e: 'sarah@bloomandbranch.com', level: 'Owner', last: '2h ago' },
    { n: 'Kara Jimenez', e: 'kara@bloomandbranch.com', level: 'Full access', last: '3d ago' },
    { n: 'Devon Price', e: 'devon@studio-partner.com', level: 'Sites only', last: '2w ago' }
  ];
  TRUSTED = window.CC_BOOT ? [] : [
    { uid: 1, where: 'Lancaster, PA · Comcast', ua: 'macOS · Safari', added: 'via login + TFA', last: 'today' },
    { uid: 2, where: 'Philadelphia, PA · Verizon', ua: 'iOS · Safari', added: 'via invoice link', last: 'Jul 8' }
  ];
  ACC_ACTIVITY = window.CC_BOOT ? [] : [
    { t: '2h', text: 'Quicksave created on bloomandbranch.com' },
    { t: '1d', text: 'Kara Jimenez logged in from a trusted location' },
    { t: '3d', text: 'Invoice #4482 issued — $68.00, due Jul 22' },
    { t: 'Jul 10', text: 'bookkeeper@ledgerly.com invited (Domains only)' },
    { t: 'Jul 8', text: 'New location verified for Sarah Whitfield (Philadelphia, PA)' },
    { t: 'Jul 1', text: 'Plan renewed — Growth, $68.00 via Visa ··4242' }
  ];
  INVOICES = window.CC_BOOT ? [] : [
    { id: '#4482', date: 'Jul 1, 2026', amount: '$68.00', items: 'Growth plan · July', due: true },
    { id: '#4391', date: 'Jun 1, 2026', amount: '$68.00', items: 'Growth plan · June' },
    { id: '#4302', date: 'May 1, 2026', amount: '$83.00', items: 'Growth plan + migration · May' },
    { id: '#4218', date: 'Apr 1, 2026', amount: '$68.00', items: 'Growth plan · April' }
  ];
  PAY_METHODS = window.CC_BOOT ? [] : [
    { label: 'Visa ··4242', sub: 'Expires 04/28' },
    { label: 'ACH ··6789', sub: 'Chase · verified via micro-deposits' }
  ];

  openAccount(id) {
    this.setState({ route: 'account', accountId: id, accTab: 'users', paletteOpen: false,
      accInvites: [{ uid: 1, e: 'bookkeeper@ledgerly.com', level: 'Domains only', sent: 'Jul 10' }],
      trusted: this.TRUSTED.map(t => ({ ...t })), invEmail: '', invLevel: 'Full access' });
  }

  computeAccounts(s, isOp) {
    const list = isOp ? this.ACCOUNTS : this.ACCOUNTS.filter(a => a.owned);
    const nq = s.aq.trim().toLowerCase();
    const ACC_COLS = [
      { label: 'Account', k: 'name', val: a => (a.name || '').toLowerCase() },
      { label: 'Users', k: 'users', val: a => Number(a.users) || 0 },
      { label: 'Sites', k: 'sites', val: a => Number(a.sites) || 0 },
      { label: 'Domains', k: 'domains', val: a => Number(a.domains) || 0 },
      { label: 'Plan', k: 'plan', val: a => a.plan || '' },
      { label: 'Billing', k: 'due', val: a => a.due ? 0 : 1 }
    ];
    const filtered = this.sortRows('accSort', ACC_COLS, list.filter(a => !nq || a.name.toLowerCase().includes(nq)));
    return {
      accCount: filtered.length + ' accounts',
      ...(s.route === 'accounts' ? { screenSub: filtered.length + ' accounts', screenSubDisplay: 'inline-block' } : {}),
      aq: s.aq, onAq: e => this.setState({ aq: e.target.value }),
      accCols: this.mkSortCols('accSort', ACC_COLS),
      accRows: filtered.map(a => ({ ...a,
        billLabel: a.due ? 'Invoice due' : 'Current',
        billFg: a.due ? 'var(--warn)' : 'var(--ink-dim)',
        open: () => this.openAccount(a.id),
        ctx: (e) => this.openCtxMenu(e, [
          { label: 'Open account', act: () => this.openAccount(a.id) },
          { label: 'Copy account name', act: () => this.ctxCopy(a.name, 'account name') }
        ]) })),
      naOpen: s.naOpen, naName: s.naName, naMsg: s.naMsg, naHasMsg: !!s.naMsg,
      openNewAccount: () => this.setState({ naOpen: true, naName: '', naMsg: '' }),
      closeNa: () => this.setState({ naOpen: false }),
      onNaName: e => this.setState({ naName: e.target.value, naMsg: '' }),
      createAccount: () => this.createAccountReal()
    };
  }

  computeAccount(s) {
    const acc = this.ACCOUNTS.find(a => a.id === s.accountId) || this.ACCOUNTS[0]
      || { id: '', name: '', users: 0, sites: 0, domains: 0, plan: '', owned: true, due: false };
    const tabs = [['users', 'Users & access'], ['sites', 'Sites'], ['domains', 'Domains'], ['plan', 'Plan'], ['activity', 'Activity']].map(([id, label]) => ({ label,
      fg: s.accTab === id ? 'var(--ink)' : 'var(--ink-dim)',
      bg: s.accTab === id ? 'var(--panel-2)' : 'transparent',
      go: () => this.setState({ accTab: id }) }));
    const healthOf = x => x.vuln ? ['Vulnerability', 'var(--bad)'] : x.updates ? ['Updates pending', 'var(--warn)'] : ['Healthy', 'var(--ok)'];
    return {
      accName: acc.name,
      accMeta: acc.plan + ' · ' + acc.users + ' users · ' + acc.sites + ' sites · ' + acc.domains + ' domain' + (acc.domains > 1 ? 's' : ''),
      accBack: () => this.setState({ route: 'accounts' }),
      accTabs: tabs,
      accTabUsers: s.accTab === 'users', accTabSites: s.accTab === 'sites', accTabDomains: s.accTab === 'domains',
      accTabPlan: s.accTab === 'plan', accTabActivity: s.accTab === 'activity',
      accShowTransfer: true, accShowTrusted: true, accShowCancel: true,
      transferOpen: false, openTransfer: () => {}, closeTransfer: () => {}, transferEmpty: false,
      transferBtnBg: 'var(--ink-dim)', transferCandidates: [], confirmTransfer: () => {},
      accUsers: this.ACC_USERS.map(u => ({ ...u,
        init: u.n.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
        lvlBg: u.level === 'Owner' ? 'var(--brand-soft)' : 'var(--panel-2)',
        lvlFg: u.level === 'Owner' ? 'var(--brand-ink)' : 'var(--ink-dim)',
        canSwitch: true, removable: true,
        switchTo: () => this.runJob('switch-to', u.e + ' (reason + duration logged)'),
        remove: () => this.runJob('remove-user', u.e + ' from ' + acc.name) })),
      accInvites: (s.accInvites || []).map(iv => ({ ...iv,
        mark: s.copied === 'inv' + iv.uid ? 'Copied ✓' : 'Copy link',
        copyLink: () => { try { navigator.clipboard.writeText('https://anchor.host/invite/' + iv.uid + '?key=…'); } catch (e) {}
          this.setState({ copied: 'inv' + iv.uid }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        del: () => this.setState(st => ({ accInvites: st.accInvites.filter(x => x.uid !== iv.uid) })) })),
      invEmail: s.invEmail, onInvEmail: e => this.setState({ invEmail: e.target.value }),
      invLevel: s.invLevel,
      ddInvOpen: s.ddOpen === 'inv',
      ddToggleInv: () => this.setState(st => ({ ddOpen: st.ddOpen === 'inv' ? '' : 'inv', ddQ: '' })),
      ddInvOpts: this.ddOpts(['Full access', 'Sites only', 'Domains only'], s.invLevel, 'invLevel'),
      sendInvite: () => { const e = this.state.invEmail.trim(); if (!e) return;
        this.setState(st => ({ accInvites: [...st.accInvites, { uid: Date.now(), e, level: st.invLevel, sent: 'just now' }], invEmail: '' })); },
      trusted: (s.trusted || []).map(td => ({ ...td,
        revoke: () => this.setState(st => ({ trusted: st.trusted.filter(x => x.uid !== td.uid) })) })),
      accSites: this.FLEET.filter(x => x.account === acc.name).map(x => { const [health, dot] = healthOf(x);
        return { ...x, health, dot, open: () => this.openSite(x.id) }; }),
      accDomains: this.DOMAINS.filter(d => d.account === acc.name).map(d => ({ ...d,
        expFg: d.warn ? 'var(--bad)' : 'var(--ink-dim)', open: () => this.openDomain(d.id) })),
      planUsage: [
        { k: 'Sites', used: acc.sites + ' of 3', pct: Math.round(acc.sites / 3 * 100) },
        { k: 'Storage', used: '3.9 of 10 GB', pct: 39 },
        { k: 'Visits / mo', used: '14.1k of 50k', pct: 28 }
      ].map(u => ({ ...u, fill: u.pct >= 80 ? 'var(--warn)' : 'var(--brand)' })),
      planRows: [
        { k: 'Plan', v: acc.plan }, { k: 'Interval', v: 'Monthly' }, { k: 'Next renewal', v: 'Aug 1, 2026' },
        { k: 'Auto-pay', v: 'On · Visa ··4242' }, { k: 'Addons', v: 'Priority support +$10/mo' }, { k: 'Credits', v: '−$15.00' }
      ],
      planRequest: () => this.runJob('plan-request', acc.name + ' — change request sent'),
      accActivity: this.ACC_ACTIVITY,
      ...(this._hydrated ? this.realAccountVals(s) : {})
    };
  }

  computeBilling(s) {
    const tabs = [['invoices', 'Invoices'], ['methods', 'Payment methods'], ['address', 'Billing address']].map(([id, label]) => ({ label,
      fg: s.billTab === id ? 'var(--ink)' : 'var(--ink-dim)',
      bg: s.billTab === id ? 'var(--panel-2)' : 'transparent',
      go: () => this.setState({ billTab: id }) }));
    return {
      billTabs: tabs,
      billTabInv: s.billTab === 'invoices', billTabPm: s.billTab === 'methods', billTabAddr: s.billTab === 'address',
      invoices: this.INVOICES.map(iv => { const paid = !iv.due || s.paid[iv.id];
        return { ...iv,
          status: !iv.due ? 'Paid' : s.paid[iv.id] ? 'Paid · just now' : 'Due Jul 22',
          stBg: paid ? 'var(--ok-soft)' : 'var(--warn-soft)', stFg: 'var(--ink)',
          canPay: iv.due && !s.paid[iv.id],
          pdf: () => {},
          pay: () => this.setState(st => ({ paid: { ...st.paid, [iv.id]: true } })) }; }),
      payMethods: this.PAY_METHODS.map((pm, i) => ({ ...pm,
        isPrimary: s.primaryPm === i, canPrimary: s.primaryPm !== i, needsVerify: false, verify: () => {},
        setPrimary: () => this.setState({ primaryPm: i }), remove: () => {} })),
      billShowAdd: true, billShowAch: false, billNotice: false, billNoticeText: '',
      addPaymentMethod: () => {}, addBankAch: () => {}, cardDlgOpen: false, cardErr: '', cardSaving: false, closeAddCard: () => {}, submitCard: () => {},
      achDlgOpen: false, achName: '', achErr: '', onAchName: () => {}, closeAddAch: () => {}, submitAch: () => {},
      verifyDlgOpen: false, verifyA1: '', verifyA2: '', verifyErr: '', onVerifyA1: () => {}, onVerifyA2: () => {}, closeVerifyAch: () => {}, submitVerifyAch: () => {},
      addrL1: 'Sarah Whitfield · Bloom & Branch LLC', addrL2: '412 Larkspur Lane',
      addrL3: 'Lancaster, PA 17601 · United States', addrL4: 'sarah@bloomandbranch.com',
      billAddrOpen: false, openBillAddr: () => {}, closeBillAddr: () => {}, billAddrFields: [], saveBillAddr: () => {},
      ...(this._hydrated ? this.realBillingVals(s) : {})
    };
  }

  THREATS = window.CC_BOOT ? [] : [
    { id: 't1', sev: 'High', name: 'Gravity Forms ≤ 2.9.3 — auth bypass', cve: 'CVE-2026-3181', patch: true, status: 'New',
      sites: ['bloomandbranch.com', 'harborlightyoga.com', 'petersonlaw.com', 'cascadecoffeeroasters.com', 'lakesideinn.com'],
      findings: 'Authentication bypass in the form-submission REST endpoint allows unauthenticated entry creation. Fixed upstream in 2.9.4.',
      rec: 'Update to 2.9.4 or apply the vendor patch. Exploitation observed in the wild.' },
    { id: 't2', sev: 'Medium', name: 'WooCommerce 9.8.x — order note XSS', cve: 'CVE-2026-2907', patch: false, status: 'Investigating',
      sites: ['bloomandbranch.com', 'cascadecoffeeroasters.com'],
      findings: 'Stored XSS via customer order notes rendered unescaped in wp-admin.',
      rec: 'Update to 9.9.0.' },
    { id: 't3', sev: 'Low', name: 'Query Monitor — info disclosure', cve: '—', patch: false, status: 'Resolved',
      sites: ['stonebridgedental.com'],
      findings: 'Debug output visible to logged-in subscriber-role users.',
      rec: 'Keep deactivated on production.' }
  ];
  T_NOTES_INIT = window.CC_BOOT ? {} : { t1: [{ who: 'Austin', when: 'Jul 9', text: 'Vendor patch confirmed working on staging.' }] };
  CORE_FAILS = window.CC_BOOT ? [] : [
    { id: 'cf1', site: 'stonebridgedental.com', mod: 2, extra: 1,
      files: ['wp-includes/pluggable.php — modified', 'wp-admin/index.php — modified', 'wp-content/db-error.php — extra (not in 6.7.2 manifest)'] }
  ];
  PLUG_FAILS = window.CC_BOOT ? [] : [
    { id: 'pf1', site: 'midwestmakersmarket.com', slug: 'wordpress-seo', chips: ['admin/class-admin.php ~', 'inc/options.php ~'],
      diff: [['ctx', '@@ admin/class-admin.php vs wordpress.org 25.3 @@'], ['del', '-        echo $notice;'], ['add', '+        echo base64_decode( $opt[\'x\'] ); // injected']] }
  ];
  AUDITS_INIT = window.CC_BOOT ? [] : [
    { id: 'a1', site: 'bloomandbranch.com', env: 'Production', types: 'Full audit', status: 'Published', when: 'Jul 8', findings: '2 medium · 5 low', pub: true },
    { id: 'a2', site: 'petersonlaw.com', env: 'Production', types: 'Plugins + Themes', status: 'Complete', when: 'Jul 11', findings: '1 high · 3 low', pub: false },
    { id: 'a3', site: 'stonebridgedental.com', env: 'Production', types: 'Core checksums', status: 'Running', when: 'today', findings: '—', running: true }
  ];
  SCHED_INIT = window.CC_BOOT ? [] : [
    { id: 's1', target: 'Bloom & Branch Floral', interval: 'Monthly', next: 'Aug 1', recipients: '2' },
    { id: 's2', target: 'Peterson Law', interval: 'Quarterly', next: 'Oct 1', recipients: '1' }
  ];
  ARCH_INIT = window.CC_BOOT ? [] : [
    { id: 'ar1', name: 'oldclientsite-migration.zip', size: '1.8 GB', mod: 'Jul 2, 2026' },
    { id: 'ar2', name: 'legacy-multisite-export.zip', size: '6.2 GB', mod: 'May 18, 2026' },
    { id: 'ar3', name: 'photography-portfolio.zip', size: '940 MB', mod: 'Mar 30, 2026' }
  ];
  KEYS_INIT = window.CC_BOOT ? [] : [{ id: 'k1', name: 'MacBook Pro', fp: 'SHA256:pR2wVd…3kQz', primary: true }];
  TIMELINE_INIT = window.CC_BOOT ? [] : [
    { uid: 1, text: 'Install Elementor Pro', who: 'Austin Ginder', when: 'Apr 9, 2026 · 4:32 PM' },
    { uid: 2, text: 'Security update: updated gravityforms to 2.9.31', who: 'Austin Ginder', when: 'Apr 5, 2026 · 8:24 AM' },
    { uid: 3, text: 'Restored website from restic snapshot', who: 'Austin Ginder', when: 'Mar 2, 2026 · 6:55 AM' },
    { uid: 4, text: 'Reset file permissions', who: 'Austin Ginder', when: 'Mar 1, 2026 · 12:38 AM' },
    { uid: 5, text: 'Updated A records for new Kinsta Cloudflare integration', who: 'Austin Ginder', when: 'Apr 6, 2021 · 2:50 PM' }
  ];
  SHARED_INIT = window.CC_BOOT ? [] : [
    { uid: 1, name: 'Bloom & Branch Floral', people: 3, level: 'Owner account', accId: 'bloomacc', owner: true },
    { uid: 2, name: 'Studio Partner LLC', people: 1, level: 'Sites only', accId: null }
  ];
  SESS_INIT = window.CC_BOOT ? [] : [
    { id: 'se1', where: 'Lancaster, PA', ua: 'macOS · Safari', last: 'active now', current: true },
    { id: 'se2', where: 'Philadelphia, PA', ua: 'iOS · Safari', last: 'Jul 8' }
  ];

  computeSecurity(s) {
    const tabs = [['vulns', 'Vulnerabilities'], ['checksums', 'Checksums'], ['coverage', 'Coverage']].map(([id, label]) => ({ label,
      fg: s.secTab === id ? 'var(--ink)' : 'var(--ink-dim)',
      bg: s.secTab === id ? 'var(--panel-2)' : 'transparent',
      go: () => this.setState({ secTab: id }) }));
    const sevStyle = { High: ['var(--bad-soft)', 'var(--bad)'], Medium: ['var(--warn-soft)', 'var(--ink)'], Low: ['var(--panel-2)', 'var(--ink-dim)'] };
    const stBg = { New: 'var(--bad-soft)', Investigating: 'var(--warn-soft)', Resolved: 'var(--ok-soft)' };
    const notes = s.tNotes || this.T_NOTES_INIT;
    const threats = this.THREATS.map(t => { const status = s.threatStatus[t.id] || t.status; return { ...t, status,
      sevBg: sevStyle[t.sev][0], sevFg: sevStyle[t.sev][1],
      stBg: stBg[status] || 'var(--panel-2)',
      siteCount: t.sites.length,
      open: s.threatOpen === t.id,
      toggle: () => this.setState(st => ({ threatOpen: st.threatOpen === t.id ? '' : t.id, noteDraft: '' })),
      siteRows: t.sites.map(name => { const f = this.FLEET.find(x => x.name === name);
        return { name, go: () => f && this.openSite(f.id) }; }),
      notes: notes[t.id] || [],
      addNote: () => { const txt = this.state.noteDraft.trim(); if (!txt) return;
        this.setState(st => { const cur = st.tNotes || this.T_NOTES_INIT;
          return { tNotes: { ...cur, [t.id]: [...(cur[t.id] || []), { who: 'Austin', when: 'just now', text: txt }] }, noteDraft: '' }; }); },
      openTerm: () => this.setState({ dockOpen: true }),
      getPatch: () => this.runJob('patch-download', t.cve),
      markInv: () => this.setState(st => ({ threatStatus: { ...st.threatStatus, [t.id]: 'Investigating' } })),
      markRes: () => { this.setState(st => ({ threatStatus: { ...st.threatStatus, [t.id]: 'Resolved' } }));
        this.runJob('threat-resolve', t.cve + ' — process log on ' + t.sites.length + ' sites'); } }; });
    return {
      secTabs: tabs, secTabVulns: s.secTab === 'vulns', secTabCk: s.secTab === 'checksums', secTabCov: s.secTab === 'coverage',
      threats, noteDraft: s.noteDraft, onNoteDraft: e => this.setState({ noteDraft: e.target.value }),
      coreFails: this.CORE_FAILS.map(c => ({ ...c, open: s.ckOpen === c.id,
        files: c.files.map(p => ({ p })),
        toggle: () => this.setState(st => ({ ckOpen: st.ckOpen === c.id ? '' : c.id })),
        sshMark: s.copied === 'ssh' + c.id ? 'Copied ✓' : 'Copy SSH',
        copySSH: (e) => { e.stopPropagation(); try { navigator.clipboard.writeText('ssh ' + c.site.split('.')[0] + '@35.223.94.108'); } catch (err) {}
          this.setState({ copied: 'ssh' + c.id }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        repair: (e) => { e.stopPropagation(); this.runJob('checksum-repair', c.site); } })),
      plugFails: this.PLUG_FAILS.map(c => ({ ...c, open: s.ckOpen === c.id,
        chips: c.chips.map(f => ({ f })),
        toggle: () => this.setState(st => ({ ckOpen: st.ckOpen === c.id ? '' : c.id })),
        diff: c.diff.map(([kind, text]) => ({ text,
          fg: kind === 'add' ? 'var(--ok)' : kind === 'del' ? 'var(--bad)' : 'var(--ink-dim)',
          bg: kind === 'add' ? 'var(--ok-soft)' : kind === 'del' ? 'var(--bad-soft)' : 'transparent' })) })),
      covTiles: [
        { k: 'Fleet coverage', v: '87%', fg: 'var(--ink)' },
        { k: 'With fresh hashes', v: '92%', fg: 'var(--ink)' },
        { k: 'Vulns scanned', v: '128 / 128', fg: 'var(--ink)' },
        { k: 'Audits < 30d old', v: '74%', fg: 'var(--warn)' }
      ],
      covBars: [['Core', 100], ['Plugins', 89], ['Themes', 81], ['Must-use / dropins', 64]].map(([k, pct]) => ({ k, pct,
        fill: pct >= 80 ? 'var(--ok)' : pct >= 50 ? 'var(--warn)' : 'var(--bad)' })),
      queueStale: () => this.runJob('audit-queue', '9 stale sites'),
      steerQueue: () => { this.runJob('drift --steer --force', '14 sites · updates before audit'); this.setState({ dockOpen: true }); },
      secLoading: false, secEmpty: false, secEmptyText: '', ckEmptyCore: false, ckEmptyPlug: false,
      covShowActions: true, covNote: '',
      ...(this._hydrated ? this.realSecurityVals(s) : {})
    };
  }

  computeAudits(s) {
    const audits = s.audits || this.AUDITS_INIT;
    const stBg = { Published: 'var(--ok-soft)', Complete: 'var(--brand-soft)', Running: 'var(--warn-soft)', Queued: 'var(--panel-2)' };
    return {
      audSite: s.audSite,
      ddAudOpen: s.ddOpen === 'aud',
      ddToggleAud: () => this.setState(st => ({ ddOpen: st.ddOpen === 'aud' ? '' : 'aud', ddQ: '' })),
      ddAudOpts: this.ddOpts(this.FLEET.map(f => f.name), s.audSite, 'audSite'),
      audTypeChips: ['Core', 'Plugins', 'Themes', 'Users', 'Config', 'Malware'].map(label => { const on = !!s.audTypes[label];
        return { label,
          bg: on ? 'var(--brand-soft)' : 'var(--paper)', fg: on ? 'var(--brand-ink)' : 'var(--ink-dim)', bd: on ? 'var(--brand)' : 'var(--rule)',
          go: () => this.setState(st => ({ audTypes: { ...st.audTypes, [label]: !st.audTypes[label] } })) }; }),
      requestAudit: () => { const types = Object.keys(this.state.audTypes).filter(k => this.state.audTypes[k]);
        if (!types.length) return;
        this.setState(st => ({ audits: [{ id: 'a' + Date.now(), site: st.audSite, env: 'Production', types: types.join(' + '), status: 'Queued', when: 'just now', findings: '—', queued: true }, ...(st.audits || this.AUDITS_INIT)] }));
        this.runJob('site-audit', this.state.audSite); },
      audRows: audits.map(a => ({ ...a,
        stBg: stBg[a.status] || 'var(--panel-2)',
        done: a.status === 'Published' || a.status === 'Complete',
        pubLabel: a.pub ? 'Unpublish' : 'Publish',
        cancellable: a.status === 'Running' || a.status === 'Queued',
        togglePub: () => this.setState(st => ({ audits: (st.audits || this.AUDITS_INIT).map(x => x.id === a.id ? { ...x, pub: !x.pub, status: x.pub ? 'Complete' : 'Published' } : x) })),
        copyLink: () => { try { navigator.clipboard.writeText('https://anchor.host/site-audits/' + a.id + '?key=…'); } catch (e) {}
          this.setState({ copied: 'aud' + a.id }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        view: () => {},
        mark: s.copied === 'aud' + a.id ? 'Copied ✓' : 'Copy link',
        cancel: () => this.setState(st => ({ audits: (st.audits || this.AUDITS_INIT).filter(x => x.id !== a.id) })) })),
      audEmpty: false, audEmptyText: '',
      ...(this._hydrated ? this.realAuditsVals(s) : {})
    };
  }

  computeReports(s, isOp) {
    const targets = s.repMode === 'Site' ? this.FLEET.map(f => f.name) : this.ACCOUNTS.map(a => a.name);
    const target = targets.includes(s.repTarget) ? s.repTarget : targets[0];
    const schedules = s.schedules || this.SCHED_INIT;
    return {
      repModeChips: ['Site', 'Account'].map(label => ({ label,
        bg: s.repMode === label ? 'var(--brand-soft)' : 'var(--paper)', fg: s.repMode === label ? 'var(--brand-ink)' : 'var(--ink-dim)', bd: s.repMode === label ? 'var(--brand)' : 'var(--rule)',
        go: () => this.setState({ repMode: label }) })),
      repTarget: target,
      ddRepTOpen: s.ddOpen === 'repT',
      ddToggleRepT: () => this.setState(st => ({ ddOpen: st.ddOpen === 'repT' ? '' : 'repT', ddQ: '' })),
      ddRepTOpts: this.ddOpts(targets, target, 'repTarget'),
      repRange: s.repRange,
      ddRepROpen: s.ddOpen === 'repR',
      ddToggleRepR: () => this.setState(st => ({ ddOpen: st.ddOpen === 'repR' ? '' : 'repR', ddQ: '' })),
      ddRepROpts: this.ddOpts(['Last month', 'Last quarter', 'This year'], s.repRange, 'repRange'),
      repInt: s.repInt,
      ddRepIOpen: s.ddOpen === 'repI',
      ddToggleRepI: () => this.setState(st => ({ ddOpen: st.ddOpen === 'repI' ? '' : 'repI', ddQ: '' })),
      ddRepIOpts: this.ddOpts(['Monthly', 'Quarterly', 'Yearly'], s.repInt, 'repInt'),
      repEmail: s.repEmail, onRepEmail: e => this.setState({ repEmail: e.target.value }),
      repPreview: () => this.runJob('report-preview', target + ' · ' + this.state.repRange),
      repSend: () => this.runJob('report-send', target + ' → ' + this.state.repEmail),
      addSchedule: () => this.setState(st => ({ schedules: [...(st.schedules || this.SCHED_INIT), { id: 's' + Date.now(), target, interval: st.repInt, next: 'Aug 1', recipients: '1' }] })),
      schedRows: schedules.map(sr => ({ ...sr, edit: () => {},
        del: () => this.setState(st => ({ schedules: (st.schedules || this.SCHED_INIT).filter(x => x.id !== sr.id) })) })),
      repSendMsg: '', repHasSendMsg: false, repPreviewOpen: false, repPreviewHtml: '', repPreviewLoading: false,
      closeRepPreview: () => {}, schedEmpty: false, repPreviewReady: false,
      schedEditOpen: false, schedIntChips: [], schedEditEmail: '', onSchedEmail: () => {}, closeSchedEdit: () => {}, saveSchedEdit: () => {},
      ...(this._hydrated ? this.realReportsVals(s) : {})
    };
  }

  computeArchives(s) {
    const list = s.archList || this.ARCH_INIT;
    return {
      archTotal: list.length + ' archives · 8.9 GB on Backblaze B2',
      archUrl: s.archUrl, onArchUrl: e => this.setState({ archUrl: e.target.value, archErr: false }),
      archBd: s.archErr ? 'var(--bad)' : 'var(--rule)', archErr: s.archErr,
      storeArch: () => { const u = this.state.archUrl.trim();
        if (!u.toLowerCase().endsWith('.zip')) { this.setState({ archErr: true }); return; }
        const name = u.split('/').pop();
        this.setState(st => ({ archList: [{ id: 'ar' + Date.now(), name, size: '—', mod: 'storing', storing: true }, ...(st.archList || this.ARCH_INIT)], archUrl: '' }));
        this.runJob('archive-store', name); },
      archRows: list.map(ar => ({ ...ar,
        mark: s.copied === ar.id ? 'Copied ✓' : 'Share link (7d)',
        share: () => { try { navigator.clipboard.writeText('https://f002.backblazeb2.com/anchor-archives/' + ar.name + '?auth=…'); } catch (e) {}
          this.setState({ copied: ar.id }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        archCanDelete: true,
        del: () => this.setState(st => ({ archList: (st.archList || this.ARCH_INIT).filter(x => x.id !== ar.id) })) })),
      archEmpty: false, archEmptyText: '', archStoreMsg: '', archHasStoreMsg: false,
      ...(this._hydrated ? this.realArchivesVals(s) : {})
    };
  }

  computeSettings(s) {
    const tabs = [['branding', 'Branding'], ['providers', 'Providers'], ['defaults', 'Site defaults'], ['keys', 'SSH keys'], ['cookbook', 'Cookbook'], ['handbook', 'Handbook']].map(([id, label]) => ({ label,
      fg: s.setTab === id ? 'var(--ink)' : 'var(--ink-dim)',
      bg: s.setTab === id ? 'var(--panel-2)' : 'transparent',
      go: () => this.setState({ setTab: id }) }));
    const keys = s.sshKeys || this.KEYS_INIT;
    return {
      setTabs: tabs,
      setTabBrand: s.setTab === 'branding', setTabProv: s.setTab === 'providers', setTabDef: s.setTab === 'defaults',
      setTabKeys: s.setTab === 'keys', setTabCook: s.setTab === 'cookbook', setTabHand: s.setTab === 'handbook',
      brandName: s.brandName, onBrandName: e => this.setState({ brandName: e.target.value }),
      brandSwatches: [['primary', '#3b82c4'], ['success', '#22a06b'], ['warning', '#d9a406'], ['error', '#d94a3d'], ['accent', '#7c5cff']].map(([k, c]) => ({ k, c, on: () => {} })),
      brandSaveLabel: s.copied === 'brand' ? 'Saved ✓' : 'Save branding',
      saveBrand: () => { this.setState({ copied: 'brand' }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
      provRows: [
        { name: 'Kinsta', sub: 'Connected · 82 sites', dot: 'var(--ok)', action: 'Verify', canImport: true },
        { name: 'WP Engine', sub: 'Connected · 24 sites', dot: 'var(--ok)', action: 'Verify', canImport: true },
        { name: 'Rocket.net', sub: 'Connected · 12 sites', dot: 'var(--ok)', action: 'Verify', canImport: true },
        { name: 'GridPane', sub: 'Token expired — reconnect required', dot: 'var(--bad)', action: 'Reconnect', canImport: false },
        { name: 'Envato', sub: 'Connected · plugin & theme purchases', dot: 'var(--ok)', action: 'Verify', canImport: false }
      ].map(p => ({ ...p,
        verify: () => this.runJob(p.action.toLowerCase() + '-provider', p.name),
        doImport: () => this.runJob('provider-import', p.name + ' — remote sites + billing preview') })),
      defRows: [['Default email', 'support@anchor.host'], ['Timezone', 'America/New_York'], ['Recipes on new site', 'security-baseline · smtp-setup'], ['Default users', 'anchor-admin (Administrator)']].map(([k, v]) => ({ k, v })),
      keyRows: keys.map(k => ({ ...k,
        del: () => this.setState(st => ({ sshKeys: (st.sshKeys || this.KEYS_INIT).filter(x => x.id !== k.id) })) })),
      keyDraft: s.keyDraft, onKeyDraft: e => this.setState({ keyDraft: e.target.value }),
      addKey: () => { const v = this.state.keyDraft.trim(); if (!v.startsWith('ssh-')) return;
        this.setState(st => ({ sshKeys: [...(st.sshKeys || this.KEYS_INIT), { id: 'k' + Date.now(), name: v.split(' ').pop() || 'new key', fp: 'SHA256:' + Math.random().toString(36).slice(2, 8) + '…', primary: false }], keyDraft: '' })); },
      rotateKey: () => this.runJob('rotate-management-key', 'fleet-wide SSH key rotation'),
      recipeRows: [
        { name: 'Install security baseline', vis: 'Public', runs: '142' },
        { name: 'Deploy SMTP via Mailgun', vis: 'Public', runs: '96' },
        { name: 'Clean transients + optimize DB', vis: 'Private', runs: '61' },
        { name: 'Set up Fathom analytics', vis: 'Public', runs: '38' }
      ].map(r => ({ ...r, hasRuns: true, visBg: r.vis === 'Public' ? 'var(--ok-soft)' : 'var(--panel-2)',
        run: () => { this.runJob('recipe', r.name); this.setState({ dockOpen: true }); } })),
      handRows: [
        { name: 'New site onboarding', updated: 'Jun 12' },
        { name: 'Site migration checklist', updated: 'May 30' },
        { name: 'Incident response — malware', updated: 'Apr 22' },
        { name: 'Offboarding a customer', updated: 'Feb 14' }
      ].map(h => ({ ...h })),
      recipeDlgOpen: false, recipeDlgEditing: false, recipeDlgTitle: 'New recipe', recipeTitle: '', recipeContent: '',
      onRecipeTitle: () => {}, onRecipeContent: () => {}, recipePublicBg: 'var(--rule)', recipePublicJust: 'flex-start',
      toggleRecipePublic: () => {}, newRecipe: () => {}, closeRecipeDlg: () => {}, saveRecipe: () => {}, deleteRecipe: () => {},
      procDlgOpen: false, procDlgName: '', procDlgBody: '', closeProcDlg: () => {},
      defDlgOpen: false, defEmail: '', defTimezone: '', onDefEmail: () => {}, onDefTimezone: () => {},
      openDefaults: () => {}, closeDefaults: () => {}, saveDefaults: () => {},
      defRecipeChips: [], defUserRows: [], addDefUser: () => {},
      provShowAdd: false, addProvider: () => {}, provDlgOpen: false, provDlgEditing: false, provDlgTitle: 'Add provider',
      provName: '', onProvName: () => {}, provTypeLabel: 'Select type…', provTypeOpen: false, toggleProvType: () => {},
      closeProvType: () => {}, provTypeOpts: [], provCredRows: [], addProvCred: () => {}, closeProvider: () => {},
      saveProvider: () => {}, deleteProvider: () => {},
      ...(this._hydrated ? this.realSettingsVals(s) : {})
    };
  }

  computeProfile(s) {
    const sessions = s.sessions || this.SESS_INIT;
    return {
      profName: s.profName, onProfName: e => this.setState({ profName: e.target.value }),
      profEmail: s.profEmail, onProfEmail: e => this.setState({ profEmail: e.target.value }),
      profSaveLabel: s.copied === 'prof' ? 'Saved ✓' : 'Save profile',
      saveProfile: () => { this.setState({ copied: 'prof' }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
      tfaOff: s.tfa === 'off', tfaSetup: s.tfa === 'setup', tfaOn: s.tfa === 'on',
      tfaLabel: s.tfa === 'on' ? 'On' : 'Off',
      tfaBg: s.tfa === 'on' ? 'var(--ok-soft)' : 'var(--panel-2)',
      tfaStart: () => this.setState({ tfa: 'setup', tfaCode: '' }),
      tfaCode: s.tfaCode, onTfaCode: e => this.setState({ tfaCode: e.target.value }),
      tfaActivate: () => { if (this.state.tfaCode.trim().length === 6) this.setState({ tfa: 'on' }); },
      tfaDisable: () => this.setState({ tfa: 'off' }),
      appPwShown: !!s.appPw, appPw: s.appPw,
      appPwBtn: s.appPw ? 'Rotate' : 'Generate',
      genAppPw: () => this.setState({ appPw: 'ccpw_' + Math.random().toString(36).slice(2, 10) + Math.random().toString(36).slice(2, 6) }),
      appPwMark: s.copied === 'apppw' ? 'Copied ✓' : 'Copy',
      copyAppPw: () => { try { navigator.clipboard.writeText(this.state.appPw); } catch (e) {}
        this.setState({ copied: 'apppw' }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
      sessRows: sessions.map(se => ({ ...se, killable: !se.current,
        kill: () => this.setState(st => ({ sessions: (st.sessions || this.SESS_INIT).filter(x => x.id !== se.id) })) })),
      killOthers: () => this.setState(st => ({ sessions: (st.sessions || this.SESS_INIT).filter(x => x.current) })),
      profPw: '', onProfPw: () => {}, profMsg: '', profHasMsg: false, tfaSecret: '', tfaHasSecret: false,
      ...(this._hydrated ? this.realProfileVals(s) : {})
    };
  }

  runJob(label, target) {
    this.setState(st => ({ jobs: [{ id: Date.now(), label, target, state: 'running', pct: 4 }, ...st.jobs] }));
  }
  openSite(id, env) {
    this.setState({ route: 'site', siteId: id, siteTab: 'overview', env: env || 'Production', qsOpen: '', bkOpen: '', paletteOpen: false });
  }

  computeList(s, isOp) {
    if (this._hydrated && s.route === 'sites' && !this._siteFilters && !this._siteFiltersLoading) setTimeout(() => this.loadSiteFilters(), 0);
    const healthOf = x => x.vuln ? ['Vulnerability', 'var(--bad)'] : x.updates ? ['Updates pending', 'var(--warn)'] : ['Healthy', 'var(--ok)'];
    const fleet = isOp ? this.FLEET : this.FLEET.filter(x => x.owned);
    const nq = s.q.trim().toLowerCase();
    const inactive = v => !v || v === 'Any' || v === 'All';
    const cntBy = fn => { const m = {}; fleet.forEach(x => { const k = fn(x); if (k) m[k] = (m[k] || 0) + 1; }); return m; };
    const plugCnt = {}; fleet.forEach(x => Object.keys(x.plugins || {}).forEach(sl => { plugCnt[sl] = (plugCnt[sl] || 0) + 1; }));
    const withPlug = inactive(s.fPlugin) ? [] : fleet.filter(x => (x.plugins || {})[s.fPlugin]);
    const verCnt = {}; const statCnt = {};
    withPlug.forEach(x => { const p = x.plugins[s.fPlugin]; verCnt[p.v] = (verCnt[p.v] || 0) + 1; statCnt[p.status] = (statCnt[p.status] || 0) + 1; });
    const conds = [];
    if (s.fUnassigned) conds.push(x => !!x.unassigned);
    if (!inactive(s.fProv)) conds.push(x => x.provider === s.fProv);
    if (!inactive(s.fBackup)) conds.push(x => x.backup === s.fBackup);
    if (!inactive(s.fCore)) conds.push(x => x.core === s.fCore);
    if (this._hydrated) {
      // Theme/plugin resolve server-side (see sites-filters.js). One combined
      // cond over the matched site-id set; while loading, don't hide anything.
      if (!inactive(s.fTheme) || !inactive(s.fPlugin)) {
        conds.push(x => this._filterMatch instanceof Set ? this._filterMatch.has(String(x.id)) : true);
      }
    } else {
      if (!inactive(s.fTheme)) conds.push(x => x.theme === s.fTheme);
      if (!inactive(s.fPlugin)) conds.push(x => { const p = (x.plugins || {})[s.fPlugin];
        if (!p) return false;
        if (!inactive(s.fPlugVer) && p.v !== s.fPlugVer) return false;
        if (!inactive(s.fPlugStatus)) { const eq = p.status === s.fPlugStatus; if (s.fPlugIs === 'IS' ? !eq : eq) return false; }
        return true; });
    }
    const selLabels = Object.keys(s.labelsSel).filter(k => s.labelsSel[k]);
    const passFacets = x => conds.length === 0 || (s.fOp === 'AND' ? conds.every(c => c(x)) : conds.some(c => c(x)));
    const filtered = fleet.filter(x => (!nq || x.name.includes(nq) || x.account.toLowerCase().includes(nq))
      && passFacets(x)
      && (selLabels.length === 0 || (x.labels || []).some(l => selLabels.includes(l))));
    const facetOpts = (cntMap, cur, key, extraReset) => {
      const items = ['Any', ...Object.keys(cntMap).sort()];
      const nq2 = s.ddQ.trim().toLowerCase();
      return (nq2 ? items.filter(i => i.toLowerCase().includes(nq2)) : items).map(label => ({ label,
        badge: label === 'Any' ? '' : (cntMap[label] || 0) + ' sites',
        mark: label === cur ? '✓' : '',
        bg: label === cur ? 'var(--brand-soft)' : 'transparent',
        pick: () => this.setState({ [key]: label, ddOpen: '', ddQ: '', ...(extraReset || {}) }) }));
    };
    const mkFacet = (id, base, cur, opts) => ({ id,
      label: inactive(cur) ? base : base + ' · ' + cur,
      bg: inactive(cur) ? 'var(--paper)' : 'var(--brand-soft)',
      fg: inactive(cur) ? 'var(--ink-dim)' : 'var(--brand-ink)',
      bd: inactive(cur) ? 'var(--rule)' : 'var(--brand)',
      open: s.ddOpen === id,
      toggle: () => this.setState(st => ({ ddOpen: st.ddOpen === id ? '' : id, ddQ: '' })),
      opts });
    const unassignedCnt = fleet.filter(x => x.unassigned).length;
    const labelCnt = {};
    fleet.forEach(x => { [...new Set(x.labels || [])].forEach(l => { if (l) labelCnt[l] = (labelCnt[l] || 0) + 1; }); });
    const chip = (label, cur, key) => ({ label,
      bg: cur === label ? 'var(--brand-soft)' : 'var(--paper)',
      fg: cur === label ? 'var(--brand-ink)' : 'var(--ink-dim)',
      bd: cur === label ? 'var(--brand)' : 'var(--rule)',
      go: () => this.setState({ [key]: label }) });
    const selIds = filtered.filter(x => s.sel[x.id]).map(x => x.id);
    const SITE_COLS = [
      { label: 'Site', k: 'name', val: x => (x.name || '').toLowerCase() },
      { label: 'Environments', k: 'envs', val: x => (x.environmentsRaw && x.environmentsRaw.length) || String(x.envs || '').split('\u00b7').length },
      { label: 'Provider', k: 'provider', val: x => x.provider || '' },
      { label: 'Core', k: 'core', val: x => x.core || '' },
      { label: 'Visits / wk', k: 'visits', val: x => parseInt(String(x.visits).replace(/\D/g, ''), 10) || 0 }
    ];
    const sorted = this.sortRows('sitesSort', SITE_COLS, filtered);
    const thumbOf = (x, size) => {
      const ru = (window.CC_BOOT && window.CC_BOOT.remoteUploadUri) || '';
      if (!ru || !x.site) return '';
      const envsRaw = x.environmentsRaw || [];
      const er = envsRaw.find(e => e.environment === 'Production' && e.screenshot_base) || envsRaw.find(e => e.screenshot_base);
      if (!er) return '';
      return ru + x.site + '_' + x.id + '/' + (er.environment || 'Production').toLowerCase() + '/screenshots/' + er.screenshot_base + '_thumb-' + size + '.jpg';
    };
    const rows = sorted.map(x => { const [health, dot] = healthOf(x); return { ...x, health, dot,
      mono: (x.name || '?').slice(0, 2).toUpperCase(),
      thumb: thumbOf(x, 100), hasThumb: !!thumbOf(x, 100),
      thumbLarge: thumbOf(x, 800), hasThumbLarge: !!thumbOf(x, 800),
      envChips: ((x.environmentsRaw && x.environmentsRaw.length)
        ? x.environmentsRaw.map(e => e.environment)
        : String(x.envs || 'Prod').split(' \u00b7 ').map(l => l === 'Prod' ? 'Production' : l)
      ).map(en => ({
        label: en === 'Production' ? 'Prod' : en,
        go: (ev) => { ev.stopPropagation(); this.openSite(x.id, en); } })),
      envCards: ((x.environmentsRaw && x.environmentsRaw.length) ? x.environmentsRaw : [{ environment: 'Production', core: x.core, home_url: x.home_url, visits: x.visits, storage: '', screenshot_base: '' }]).map(e => {
        const en = e.environment || 'Production';
        const ru = (window.CC_BOOT && window.CC_BOOT.remoteUploadUri) || '';
        const shot = (ru && x.site && e.screenshot_base) ? ru + x.site + '_' + x.id + '/' + en.toLowerCase() + '/screenshots/' + e.screenshot_base + '_thumb-800.jpg' : '';
        return {
          env: en.toUpperCase(),
          envBg: en === 'Production' ? 'var(--ok-soft)' : 'var(--panel-2)',
          envFg: en === 'Production' ? 'var(--ok)' : 'var(--ink-dim)',
          core: e.core || x.core || '', url: e.home_url || '',
          visits: e.visits ? Number(e.visits).toLocaleString() : '\u2014',
          storage: e.storage ? this.fmtStorage(e.storage) : '\u2014',
          shot, hasShot: !!shot,
          visit: (ev) => { ev.stopPropagation(); if (e.home_url) window.open(e.home_url, '_blank'); },
          manage: (ev) => { ev.stopPropagation(); this.openSite(x.id, en); },
          login: (ev) => { ev.stopPropagation(); if (this._hydrated) this.magicLogin(x.id, en.toLowerCase()); else this.runJob('magiclogin', x.name); } };
      }),
      wpLogin: (e) => { e.stopPropagation(); if (this._hydrated) this.magicLogin(x.id, 'production'); else this.runJob('magiclogin', x.name); },
      updLabel: x.updates ? x.updates + ' pending' : '—',
      updBg: x.updates ? 'var(--warn-soft)' : 'transparent',
      updFg: x.updates ? 'var(--ink)' : 'var(--ink-dim)',
      check: s.sel[x.id] ? '✓' : '', checkBg: s.sel[x.id] ? 'var(--brand)' : 'var(--paper)',
      toggle: (e) => { e.stopPropagation(); this.setState(st => ({ sel: { ...st.sel, [x.id]: !st.sel[x.id] } })); },
      open: () => this.openSite(x.id),
      ctx: (e) => this.openCtxMenu(e, [
        { label: 'Open site', act: () => this.openSite(x.id) },
        { label: 'Visit site ↗', act: () => window.open('https://' + x.name, '_blank') },
        { label: 'Open terminal', act: () => this.setState({ dockOpen: true }) },
        { label: 'Copy domain', act: () => this.ctxCopy(x.name, 'domain') }
      ]),
      openTerm: (e) => { e.stopPropagation(); this.setState({ dockOpen: true }); } }; });
    const envCount = filtered.reduce((n, x) => n + (x.envs.includes('Staging') ? 2 : 1), 0);
    const allSel = filtered.length > 0 && selIds.length === filtered.length;
    return {
      sitesCount: filtered.length + ' sites · ' + envCount + ' environments',
      screenSub: s.route === 'sites' ? filtered.length + ' sites · ' + envCount + ' environments' : '',
      screenSubDisplay: s.route === 'sites' ? 'inline-block' : 'none',
      q: s.q, onQ: (e) => this.setState({ q: e.target.value }),
      unassignedLabel: unassignedCnt + ' unassigned',
      unBg: s.fUnassigned ? 'var(--warn-soft)' : 'var(--paper)',
      unFg: s.fUnassigned ? 'var(--ink)' : 'var(--ink-dim)',
      unBd: s.fUnassigned ? 'var(--warn)' : 'var(--rule)',
      unassignedToggle: () => this.setState(st => ({ fUnassigned: !st.fUnassigned })),
      facets: [
        mkFacet('fProv', 'Provider', s.fProv, facetOpts(cntBy(x => x.provider), s.fProv, 'fProv')),
        mkFacet('fBackup', 'Backup', s.fBackup, facetOpts(cntBy(x => x.backup), s.fBackup, 'fBackup')),
        mkFacet('fCore', 'Core', s.fCore, facetOpts(cntBy(x => x.core), s.fCore, 'fCore')),
        mkFacet('fTheme', 'Theme', s.fTheme, this._hydrated
          ? this.filterFacetOpts(this.THEME_OPTIONS, s.fTheme, 'fTheme')
          : facetOpts(cntBy(x => x.theme), s.fTheme, 'fTheme')),
        mkFacet('fPlugin', 'Plugin', s.fPlugin, this._hydrated
          ? this.filterFacetOpts(this.PLUGIN_OPTIONS, s.fPlugin, 'fPlugin', { fPlugVer: 'Any', fPlugStatus: 'Any' })
          : facetOpts(plugCnt, s.fPlugin, 'fPlugin', { fPlugVer: 'Any', fPlugStatus: 'Any' }))
      ],
      opChips: ['AND', 'OR'].map(label => ({ label,
        bg: s.fOp === label ? 'var(--panel-2)' : 'transparent',
        fg: s.fOp === label ? 'var(--ink)' : 'var(--ink-dim)',
        go: () => this.setState({ fOp: label }) })),
      plugRowShow: !inactive(s.fPlugin),
      plugRowLabel: s.fPlugin,
      facets2: [
        mkFacet('fPlugVer', 'Version', s.fPlugVer, facetOpts(verCnt, s.fPlugVer, 'fPlugVer')),
        mkFacet('fPlugStatus', 'Status', s.fPlugStatus, facetOpts(statCnt, s.fPlugStatus, 'fPlugStatus'))
      ],
      isChips: ['IS', 'IS NOT'].map(label => ({ label,
        bg: s.fPlugIs === label ? 'var(--panel-2)' : 'transparent',
        fg: s.fPlugIs === label ? 'var(--ink)' : 'var(--ink-dim)',
        go: () => this.setState({ fPlugIs: label }) })),
      clearPlugin: () => this.setState({ fPlugin: 'Any', fPlugVer: 'Any', fPlugStatus: 'Any' }),
      hasLabels: Object.keys(labelCnt).length > 0,
      labelChips: Object.keys(labelCnt)
        .sort((a, b) => labelCnt[b] - labelCnt[a] || a.localeCompare(b))
        .map(label => {
          const fallbackColor = { down: 'red', 'domain-expired': 'red', 'dns-elsewhere': 'blue', moved: 'orange' };
          const fallbackIcon = { down: 'mdi-alert', 'domain-expired': 'mdi-calendar-remove', 'dns-elsewhere': 'mdi-dns', moved: 'mdi-swap-horizontal', note: 'mdi-tag', 'not-wordpress': 'mdi-cancel' };
          const meta = (this.LABEL_META && this.LABEL_META[label]) || {};
          const color = meta.color || fallbackColor[label] || 'grey';
          const icon = this.LABEL_ICONS[meta.icon || fallbackIcon[label]] || this.LABEL_ICONS['mdi-tag'];
          const [bg, fg, bd] = {
            red: ['var(--bad-soft)', 'var(--bad)', 'var(--bad)'],
            orange: ['var(--warn-soft)', 'var(--ink)', 'var(--warn)'],
            amber: ['var(--warn-soft)', 'var(--ink)', 'var(--warn)'],
            blue: ['var(--brand-soft)', 'var(--brand-ink)', 'var(--brand)'],
            green: ['var(--ok-soft)', 'var(--ok)', 'var(--ok)']
          }[color] || ['var(--panel-2)', 'var(--ink-dim)', 'var(--ink-dim)'];
          const on = !!s.labelsSel[label];
          return { label, n: labelCnt[label], bg, fg, icon, bd: on ? bd : 'transparent',
            go: () => this.setState(st => ({ labelsSel: { ...st.labelsSel, [label]: !st.labelsSel[label] } })) };
        }),
      hasFilters: !!nq || conds.length > 0 || selLabels.length > 0,
      clearFilters: () => { this.setState({ q: '', fProv: 'Any', fUnassigned: false, fBackup: 'Any', fCore: 'Any', fTheme: 'Any', fPlugin: 'Any', fPlugVer: 'Any', fPlugStatus: 'Any', fPlugIs: 'IS', labelsSel: {} }); this._filterMatch = null; if (this.applyServerFilter) this.applyServerFilter(); },
      hasSel: selIds.length > 0, selCount: selIds.length,
      clearSel: () => this.setState({ sel: {} }),
      selAllMark: allSel ? '✓' : '', selAllBg: allSel ? 'var(--brand)' : 'var(--paper)',
      toggleAll: () => this.setState({ sel: allSel ? {} : Object.fromEntries(filtered.map(x => [x.id, true])) }),
      bulkActions: ['Sync data', 'Update WP', 'Back up', 'Apply HTTPS', 'Scan errors'].map(label => ({ label,
        go: () => { this.runJob(label.toLowerCase().replace(/ /g, '-'), selIds.length + ' sites'); this.setState({ sel: {}, dockOpen: true }); } })),
      openNewSite: () => this.setState({ nsOpen: true }),
      closeNs: () => this.setState({ nsOpen: false }),
      nsOpen: s.nsOpen,
      nsPaths: [['request', 'Request'], ['kinsta', 'New on Kinsta'], ['import', 'Import from provider'], ['manual', 'Connect manually']].map(([id, label]) => ({ label,
        bg: s.nsPath === id ? 'var(--brand-soft)' : 'var(--paper)', fg: s.nsPath === id ? 'var(--brand-ink)' : 'var(--ink-dim)', bd: s.nsPath === id ? 'var(--brand)' : 'var(--rule)',
        go: () => this.setState({ nsPath: id }) })),
      nsIsRequest: s.nsPath === 'request', nsIsKinsta: s.nsPath === 'kinsta', nsIsImport: s.nsPath === 'import', nsIsManual: s.nsPath === 'manual',
      nsName: s.nsName, onNsName: e => this.setState({ nsName: e.target.value }),
      nsNotes: s.nsNotes, onNsNotes: e => this.setState({ nsNotes: e.target.value }),
      nsAddr: s.nsAddr, onNsAddr: e => this.setState({ nsAddr: e.target.value }),
      nsUser: s.nsUser, onNsUser: e => this.setState({ nsUser: e.target.value }),
      nsPass: s.nsPass, onNsPass: e => this.setState({ nsPass: e.target.value }),
      nsAcc: s.nsAcc,
      ddNsAccOpen: s.ddOpen === 'nsAcc',
      ddToggleNsAcc: () => this.setState(st => ({ ddOpen: st.ddOpen === 'nsAcc' ? '' : 'nsAcc', ddQ: '' })),
      ddNsAccOpts: this.ddOpts(this.ACCOUNTS.map(a => a.name), s.nsAcc, 'nsAcc'),
      nsClone: s.nsClone,
      ddNsCloneOpen: s.ddOpen === 'nsClone',
      ddToggleNsClone: () => this.setState(st => ({ ddOpen: st.ddOpen === 'nsClone' ? '' : 'nsClone', ddQ: '' })),
      ddNsCloneOpts: this.ddOpts(['None (fresh install)', ...this.FLEET.map(f => f.name)], s.nsClone, 'nsClone'),
      nsDcChips: ['US Central (Iowa)', 'US East (S. Carolina)', 'EU West (Belgium)'].map(l => chip(l, s.nsDc, 'nsDc')),
      nsProvChips: ['Kinsta', 'WP Engine', 'Rocket.net', 'GridPane'].map(l => chip(l, s.nsProv, 'nsProv')),
      nsEnvChips: ['Production only', 'Production + Staging'].map(l => chip(l, s.nsEnvs, 'nsEnvs')),
      nsRemote: [['clientsite-alpha.com', '1.2 GB'], ['clientsite-beta.com', '640 MB'], ['legacyshop.net', '3.8 GB']].map(([name, size]) => ({ name, size,
        check: s.nsImportSel[name] ? '✓' : '', checkBg: s.nsImportSel[name] ? 'var(--brand)' : 'var(--paper)',
        toggle: () => this.setState(st => ({ nsImportSel: { ...st.nsImportSel, [name]: !st.nsImportSel[name] } })) })),
      nsBilling: (() => { const n = Object.keys(s.nsImportSel).filter(k => s.nsImportSel[k]).length;
        return n ? 'Billing preview: +$' + n * 45 + '/mo · Standard plan × ' + n : 'Select sites to see a billing preview.'; })(),
      nsCta: { request: 'Request site', kinsta: 'Create on Kinsta',
        import: 'Import ' + Object.keys(s.nsImportSel).filter(k => s.nsImportSel[k]).length + ' sites',
        manual: 'Connect site' }[s.nsPath],
      nsCreate: () => { const st = this.state;
        const sel = Object.keys(st.nsImportSel).filter(k => st.nsImportSel[k]);
        if (st.nsPath === 'request') this.runJob('site-request', (st.nsName || 'new site') + ' · ' + st.nsAcc);
        else if (st.nsPath === 'kinsta') this.runJob('kinsta-new-site', (st.nsName || 'new site') + ' · ' + st.nsDc + (st.nsClone.startsWith('None') ? '' : ' · clone of ' + st.nsClone));
        else if (st.nsPath === 'import') { if (!sel.length) return; this.runJob('provider-import', sel.length + ' sites from ' + st.nsProv); }
        else this.runJob('connect-site', (st.nsName || st.nsAddr || 'new site') + ' · ' + st.nsEnvs);
        this.setState({ nsOpen: false, nsName: '', nsNotes: '', nsImportSel: {}, dockOpen: true }); },
      viewTable: s.view === 'table', viewCards: s.view === 'cards', viewList: s.view === 'list',
      tblBg: s.view === 'table' ? 'var(--panel-2)' : 'transparent', tblFg: s.view === 'table' ? 'var(--ink)' : 'var(--ink-dim)',
      crdBg: s.view === 'cards' ? 'var(--panel-2)' : 'transparent', crdFg: s.view === 'cards' ? 'var(--ink)' : 'var(--ink-dim)',
      lstBg: s.view === 'list' ? 'var(--panel-2)' : 'transparent', lstFg: s.view === 'list' ? 'var(--ink)' : 'var(--ink-dim)',
      setViewTable: () => this.setState({ view: 'table' }), setViewCards: () => this.setState({ view: 'cards' }),
      setViewList: () => this.setState({ view: 'list' }),
      listRows: rows,
      siteCols: this.mkSortCols('sitesSort', SITE_COLS)
    };
  }

  parseZone(text) {
    const types = ['A', 'AAAA', 'ANAME', 'CNAME', 'MX', 'TXT', 'SPF', 'SRV', 'HTTP'];
    const recs = [];
    (text || '').split('\n').forEach(line => {
      const l = line.trim();
      if (!l || l.startsWith(';') || l.startsWith('$')) return;
      const toks = l.split(/\s+/);
      let idx = -1;
      for (let i = 1; i < toks.length; i++) { const u = toks[i].toUpperCase(); if (u === 'IN') continue; if (types.includes(u)) { idx = i; break; } }
      if (idx < 0) return;
      const value = toks.slice(idx + 1).join(' ').replace(/^"|"$/g, '');
      if (!value) return;
      const ttlTok = toks.slice(1, idx).find(t => /^\d+$/.test(t));
      recs.push({ type: toks[idx].toUpperCase(), name: toks[0].replace(/\.$/, '') || '@', value, ttl: ttlTok || '3600' });
    });
    return recs;
  }

  ddOpts(list, cur, key) {
    const nq = this.state.ddQ.trim().toLowerCase();
    return (nq ? list.filter(o => o.toLowerCase().includes(nq)) : list).map(label => ({ label,
      mark: label === cur ? '✓' : '',
      bg: label === cur ? 'var(--brand-soft)' : 'transparent',
      pick: () => this.setState({ [key]: label, ddOpen: '', ddQ: '' }) }));
  }

  openDomain(id) {
    this.setState({ route: 'domain', domainId: id, domTab: 'dns', paletteOpen: false,
      dnsRecs: this.DNS_RECS.map(r => ({ ...r })), dnsDirty: false, dnsT: 'A', dnsN: '', dnsV: '',
      fwds: this.FWDS.map(f => ({ ...f })), fwdAlias: '', fwdDest: '',
      reg: { auto: true, lock: true, priv: true } });
  }

  computeDomains(s, isOp) {
    const base = s.domList || this.DOMAINS;
    const list = isOp ? base : base.filter(d => d.owned);
    const nq = s.dq.trim().toLowerCase();
    const DOM_COLS = [
      { label: 'Domain', k: 'name', val: d => (d.name || '').toLowerCase() },
      { label: 'Registrar', k: 'registrar', val: d => d.registrar || '' },
      { label: 'DNS', k: 'dns', val: d => d.dns ? 1 : 0 }
    ];
    const filtered = this.sortRows('domSort', DOM_COLS, list.filter(d => !nq || d.name.includes(nq) || d.account.toLowerCase().includes(nq)));
    return {
      domCount: filtered.length + ' domains',
      ...(s.route === 'domains' ? { screenSub: filtered.length + ' domains', screenSubDisplay: 'inline-block' } : {}),
      dq: s.dq, onDq: e => this.setState({ dq: e.target.value }),
      openNewDomain: () => this.setState({ ndOpen: true }),
      closeNd: () => this.setState({ ndOpen: false }),
      ndOpen: s.ndOpen,
      ndName: s.ndName, onNdName: e => this.setState({ ndName: e.target.value }),
      ndAcc: s.ndAcc,
      ddNdAccOpen: s.ddOpen === 'ndAcc',
      ddToggleNdAcc: () => this.setState(st => ({ ddOpen: st.ddOpen === 'ndAcc' ? '' : 'ndAcc', ddQ: '' })),
      ddNdAccOpts: this.ddOpts(this.ACCOUNTS.map(a => a.name), s.ndAcc, 'ndAcc'),
      ndZoneBg: s.ndZone ? 'var(--brand)' : 'var(--rule)',
      ndZoneJust: s.ndZone ? 'flex-end' : 'flex-start',
      ndZoneLabel: s.ndZone ? 'On — records editable here' : 'Off — DNS stays external',
      ndZoneFlip: () => this.setState(st => ({ ndZone: !st.ndZone })),
      ndCreate: () => { const v = this.state.ndName.trim().toLowerCase(); if (!v) return;
        if (this._hydrated) {
          const acc = this.ACCOUNTS.find(a => a.name === this.state.ndAcc);
          this.setState({ ndOpen: false, ndName: '' });
          this.api('/domains', { method: 'POST', body: { name: v, account_id: acc ? acc.id : '', create_dns_zone: !!this.state.ndZone } })
            .then(res => {
              if (res && res.code) { console.warn('domain create failed', res); return; }
              this.api('/domains/').then(domains => {
                this.DOMAINS = (Array.isArray(domains) ? domains : []).map(x => ({ id: String(x.domain_id), name: x.name,
                  account: '', registrar: x.provider_id ? 'Registrar' : '—', dns: !!x.remote_id,
                  expires: '—', auto: null, owned: true }));
                this.setState({});
              }).catch(() => {});
            }).catch(() => {});
          return;
        }
        this.setState(st => ({ domList: [{ id: 'd' + Date.now(), name: v, account: st.ndAcc, registrar: 'Hover', dns: st.ndZone, expires: 'Jul 2027', auto: true, owned: true }, ...(st.domList || this.DOMAINS)], ndOpen: false, ndName: '' }));
        this.runJob('domain-create', v + (this.state.ndZone ? ' + DNS zone' : '')); },
      domCols: this.mkSortCols('domSort', DOM_COLS),
      domRows: filtered.map(d => ({ ...d,
        dnsLabel: d.dns ? 'Active' : '—', dnsFg: d.dns ? 'var(--ok)' : 'var(--ink-dim)',
        expFg: d.warn ? 'var(--bad)' : 'var(--ink)',
        autoLabel: d.auto === null ? '—' : d.auto ? 'On' : 'Off',
        autoFg: d.auto === false ? 'var(--warn)' : 'var(--ink-dim)',
        open: () => this.openDomain(d.id),
        ctx: (e) => this.openCtxMenu(e, [
          { label: 'Open domain', act: () => this.openDomain(d.id) },
          { label: 'Visit site ↗', act: () => window.open('https://' + d.name, '_blank') },
          { label: 'Copy domain', act: () => this.ctxCopy(d.name, 'domain') }
        ]) }))
    };
  }

  computeDomain(s) {
    const domBase = s.domList || this.DOMAINS;
    const d = domBase.find(x => x.id === s.domainId) || domBase[0]
      || { id: '', name: '', account: '', registrar: '', dns: false, expires: '—', auto: null, owned: true };
    const tabs = [['dns', 'DNS'], ['registrar', 'Registrar'], ['forwarding', 'Email forwarding'], ['sending', 'Sending']].map(([id, label]) => ({ label,
      fg: s.domTab === id ? 'var(--ink)' : 'var(--ink-dim)',
      bg: s.domTab === id ? 'var(--panel-2)' : 'transparent',
      go: () => this.setState({ domTab: id }) }));
    const typeBg = { A: 'var(--brand-soft)', AAAA: 'var(--brand-soft)', MX: 'var(--warn-soft)', TXT: 'var(--ok-soft)', SPF: 'var(--ok-soft)' };
    const dnsRows = (s.dnsRecs || []).map(r => ({ ...r, bg: typeBg[r.type] || 'var(--panel-2)',
      editing: s.dnsEdit === r.uid, notEditing: s.dnsEdit !== r.uid,
      startEdit: () => this.setState({ dnsEdit: r.uid, dnsEN: r.name, dnsEV: r.value, dnsETtl: r.ttl }),
      del: (e) => { e.stopPropagation(); this.setState(st => ({ dnsRecs: st.dnsRecs.filter(x => x.uid !== r.uid), dnsDirty: true })); } }));
    const zoneRecs = s.zoneOpen ? this.parseZone(s.zoneText) : [];
    const ct = s.contact || { Name: 'Sarah Whitfield', Organization: 'Bloom & Branch LLC', Email: 'sarah@bloomandbranch.com', Phone: '+1 (717) 555-0164', Address: '412 Larkspur Lane', 'City / State': 'Lancaster, PA 17601', Country: 'United States' };
    const mkTog = (key, label) => ({ label,
      bg: s.reg[key] ? 'var(--brand)' : 'var(--rule)',
      just: s.reg[key] ? 'flex-end' : 'flex-start',
      state: s.reg[key] ? 'On' : 'Off',
      flip: () => this.setState(st => ({ reg: { ...st.reg, [key]: !st.reg[key] } })) });
    const fwdRows = (s.fwds || []).map(f => ({ ...f, aliasFull: (f.alias === '*' ? 'anything' : f.alias) + '@' + d.name,
      stFg: f.status === 'Verified' ? 'var(--ok)' : f.status === 'Catch-all' ? 'var(--ink-dim)' : 'var(--warn)',
      del: () => this.setState(st => ({ fwds: st.fwds.filter(x => x.uid !== f.uid) })) }));
    const mgRecs = this.MG_RECS.map(r => ({ ...r, host: r.host + '.' + d.name,
      stLabel: r.ok ? 'Verified' : 'Pending', stFg: r.ok ? 'var(--ok)' : 'var(--warn)', pending: !r.ok,
      verify: () => this.runJob('mailgun-verify', r.host + '.' + d.name) }));
    return {
      domName: d.name,
      domStatus: (d.dns ? 'DNS active · ' : 'DNS inactive · ') + 'Registered via ' + d.registrar + (d.expires !== '—' ? ' · expires ' + d.expires : ''),
      domBack: () => this.setState({ route: 'domains' }),
      domTabs: tabs,
      domTabDns: s.domTab === 'dns', domTabReg: s.domTab === 'registrar', domTabFwd: s.domTab === 'forwarding', domTabSnd: s.domTab === 'sending',
      dnsRows, dnsDirty: s.dnsDirty, dnsT: s.dnsT, dnsN: s.dnsN, dnsV: s.dnsV,
      dnsEN: s.dnsEN, onDnsEN: e => this.setState({ dnsEN: e.target.value }),
      dnsEV: s.dnsEV, onDnsEV: e => this.setState({ dnsEV: e.target.value }),
      dnsETtl: s.dnsETtl, onDnsETtl: e => this.setState({ dnsETtl: e.target.value }),
      dnsEditDone: () => this.setState(st => ({ dnsRecs: st.dnsRecs.map(x => x.uid === st.dnsEdit ? { ...x, name: st.dnsEN.trim() || '@', value: st.dnsEV.trim() || x.value, ttl: st.dnsETtl.trim() || '3600' } : x), dnsEdit: 0, dnsDirty: true })),
      dnsEditCancel: () => this.setState({ dnsEdit: 0 }),
      openZoneDlg: () => this.setState({ zoneOpen: true, zoneText: '' }),
      closeZone: () => this.setState({ zoneOpen: false }),
      zoneOpen: s.zoneOpen,
      zoneText: s.zoneText, onZoneText: e => this.setState({ zoneText: e.target.value }),
      hasZoneRecs: zoneRecs.length > 0, zoneEmpty: zoneRecs.length === 0,
      zoneCount: zoneRecs.length + ' records parsed',
      zonePreview: zoneRecs.map(r => ({ ...r, bg: typeBg[r.type] || 'var(--panel-2)' })),
      zoneAppend: () => this.setState(st => ({ dnsRecs: [...(st.dnsRecs || []), ...this.parseZone(st.zoneText).map((r, i) => ({ ...r, uid: Date.now() + i }))], dnsDirty: true, zoneOpen: false, zoneText: '' })),
      zoneReplace: () => this.setState(st => ({ dnsRecs: this.parseZone(st.zoneText).map((r, i) => ({ ...r, uid: Date.now() + i })), dnsDirty: true, zoneOpen: false, zoneText: '' })),
      openNsvDlg: () => this.setState({ nsvOpen: true, nsvText: (this.state.nsCustom || ['ns11.constellix.com', 'ns21.constellix.com', 'ns31.constellix.com']).join('\n') }),
      closeNsv: () => this.setState({ nsvOpen: false }),
      nsvOpen: s.nsvOpen,
      nsvText: s.nsvText, onNsvText: e => this.setState({ nsvText: e.target.value }),
      saveNsv: () => { const lines = this.state.nsvText.split('\n').map(l => l.trim()).filter(Boolean);
        if (!lines.length) return;
        this.setState({ nsCustom: lines, nsvOpen: false });
        this.runJob('nameservers-update', d.name + ' → ' + lines.length + ' nameservers'); },
      openCtDlg: () => this.setState({ ctOpen: true, ctDraft: { ...ct } }),
      closeCt: () => this.setState({ ctOpen: false }),
      ctOpen: s.ctOpen,
      ctFields: Object.keys(ct).map(label => ({ label, v: (s.ctDraft || {})[label] ?? '',
        on: e => this.setState(st => ({ ctDraft: { ...st.ctDraft, [label]: e.target.value } })) })),
      saveCt: () => { this.setState(st => ({ contact: { ...st.ctDraft }, ctOpen: false }));
        this.runJob('contacts-update', d.name + ' · all 4 roles'); },
      ctLine1: ct.Name + ' · ' + ct.Organization,
      ctLine2: ct.Address + ', ' + ct['City / State'] + ' · ' + ct.Country,
      ctLine3: ct.Email + ' · ' + ct.Phone,
      ddDnsOpen: s.ddOpen === 'dns',
      ddToggleDns: () => this.setState(st => ({ ddOpen: st.ddOpen === 'dns' ? '' : 'dns', ddQ: '' })),
      ddDnsOpts: this.ddOpts(['A', 'AAAA', 'ANAME', 'CNAME', 'MX', 'TXT', 'SPF', 'SRV', 'HTTP'], s.dnsT, 'dnsT'),
      onDnsN: e => this.setState({ dnsN: e.target.value }),
      onDnsV: e => this.setState({ dnsV: e.target.value }),
      addRec: () => { if (!this.state.dnsV.trim()) return;
        this.setState(st => ({ dnsRecs: [...st.dnsRecs, { uid: Date.now(), type: st.dnsT, name: st.dnsN.trim() || '@', value: st.dnsV.trim(), ttl: '3600' }], dnsDirty: true, dnsN: '', dnsV: '' })); },
      saveDns: () => { this.runJob('dns-bulk-save', d.name); this.setState({ dnsDirty: false }); },
      discardDns: () => this.setState({ dnsRecs: this.DNS_RECS.map(r => ({ ...r })), dnsDirty: false }),
      importZone: () => this.runJob('dns-import', 'zone file → ' + d.name),
      exportZone: () => this.runJob('dns-export', d.name + ' → BIND'),
      regRegistrar: d.registrar,
      regExpires: d.expires + (d.warn ? ' · in 12 days' : ''),
      regExpFg: d.warn ? 'var(--bad)' : 'var(--ink)',
      regWarn: !!d.warn,
      renewNow: () => this.runJob('renew-domain', d.name),
      togAuto: mkTog('auto', 'Auto-renew'), togLock: mkTog('lock', 'Transfer lock'), togPriv: mkTog('priv', 'WHOIS privacy'),
      authMark: s.copied === 'auth' ? 'Copied ✓' : 'Copy',
      authCopy: () => { try { navigator.clipboard.writeText('XK7-99Q2-RRB1'); } catch (e) {}
        this.setState({ copied: 'auth' }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
      nsList: (s.nsCustom || ['ns11.constellix.com', 'ns21.constellix.com', 'ns31.constellix.com']).map(n => ({ n })),
      fwdRows, fwdAlias: s.fwdAlias, fwdDest: s.fwdDest,
      onFwdAlias: e => this.setState({ fwdAlias: e.target.value }),
      onFwdDest: e => this.setState({ fwdDest: e.target.value }),
      addFwd: () => { const a = this.state.fwdAlias.trim(), t = this.state.fwdDest.trim(); if (!a || !t) return;
        this.setState(st => ({ fwds: [...st.fwds, { uid: Date.now(), alias: a.replace(/@.*$/, ''), dest: t, status: 'Pending verification' }], fwdAlias: '', fwdDest: '' }));
        this.runJob('verify-forward', a.replace(/@.*$/, '') + '@' + d.name); },
      mgHost: 'mg.' + d.name,
      mgRecs, mgEvents: this.MG_EVENTS,
      mgSupp: '2 bounces · 0 unsubscribes · 0 complaints',
      mgDeploy: () => this.runJob('deploy-mailgun', d.name + ' → SMTP on connected site'),
      dnsNotice: false, dnsNoticeText: '', dnsShowActivate: false, activateZone: () => {},
      fwdInactive: false, fwdLoading: false, fwdNotice: false, fwdNoticeText: '', activateFwd: () => {},
      mgInactive: false, mgLoading: false, mgNotice: false, mgNoticeText: '', mgSetup: () => {},
      regShowRenew: true, mgShowDeploy: true, regShowAuto: true,
      ...(this._hydrated ? this.realDomainVals(s, d) : {})
    };
  }

  computeDetail(s) {
    const site = this.FLEET.find(x => x.id === s.siteId) || this.FLEET[0]
      || { id: '', name: '', provider: '', account: '', core: '', visits: '', storage: '', envs: '', updates: 0, vuln: 0, labels: [], plugins: {}, theme: '', backup: '' };
    const real = this._detail && this._detail.siteId === s.siteId ? this._detail : null;
    // Load the active tab's data whenever the site detail is shown — covers
    // deep links to /account/sites/{id}/{tab}, not just tab clicks. Gated on
    // envs being loaded (stats needs the env's tracker); each loader self-guards
    // against re-fetching, so calling on render is cheap.
    if (real && real.envs && s.route === 'site') setTimeout(() => {
      const tab = this.state.siteTab;
      if (tab === 'stats') this.loadStats();
      else if (tab === 'logs') this.loadLogs();
      else if (tab === 'versions') this.loadQuicksaves();
      else if (tab === 'backups') this.loadBackups();
      else if (tab === 'snapshots') this.loadSnapshots();
      else if (tab === 'timeline') this.loadTimeline();
    }, 0);
    const slug = site.name.split('.')[0];
    const host = s.env === 'Staging' ? 'staging-' + site.name : site.name;
    const segBg = l => s.env === l ? 'var(--panel-2)' : 'transparent';
    const segFg = l => s.env === l ? 'var(--ink)' : 'var(--ink-dim)';
    const mkCopy = ([k, v]) => ({ k, v, mark: s.copied === k ? 'Copied ✓' : 'Copy',
      copy: () => { try { navigator.clipboard.writeText(v); } catch (e) {}
        this.setState({ copied: k }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); } });
    const credRows = (real ? this.realCredPairs(real, s) : [
      ['Site URL', 'https://' + host], ['WP admin', 'https://' + host + '/wp-admin'],
      ['SFTP', slug + '@sftp.kinsta.com:22'], ['SFTP password', 'uY3!kW8#pQ2v'],
      ['Database', 'wp_' + slug.replace(/-/g, '_')], ['DB password', 'mR7$xT4@nL9c'],
      ['SSH', 'ssh ' + slug + '@35.223.94.108']
    ]).map(mkCopy);
    const tabs = [['overview', 'Overview'], ['stats', 'Stats'], ['addons', 'Addons'], ['versions', 'Versions'], ['backups', 'Backups'], ['snapshots', 'Snapshots'], ['users', 'Users'], ['logs', 'Logs'], ['timeline', 'Timeline']]
      .map(([id, label]) => ({ label,
        fg: s.siteTab === id ? 'var(--ink)' : 'var(--ink-dim)',
        bg: s.siteTab === id ? 'var(--panel-2)' : 'transparent',
        go: () => { this.setState({ siteTab: id });
          if (!this._detail) return;
          if (id === 'logs') this.loadLogs();
          else if (id === 'stats') this.loadStats();
          else if (id === 'versions') this.loadQuicksaves();
          else if (id === 'backups') this.loadBackups();
          else if (id === 'snapshots') this.loadSnapshots();
          else if (id === 'timeline') this.loadTimeline(); } }));
    const addonsSrc = real ? this.realAddonSrc(real, s) : (s.addonKind === 'plugins' ? this.PLUGINS : this.THEMES);
    const addons = addonsSrc.map(a => { const upd = a.v !== a.latest; return { ...a, upd,
      vulnB: !!(site.vuln && a.slug === 'gravityforms'),
      dot: a.active ? 'var(--ok)' : 'var(--rule)',
      statusLabel: a.active ? 'Active' : 'Inactive',
      toggleLabel: a.active ? 'Deactivate' : 'Activate',
      doToggle: () => real ? this.realToggleAddon(a, real, s) : this.runJob(a.active ? 'deactivate' : 'activate', a.slug + ' on ' + site.name),
      doUpdate: () => this.runJob('update', a.slug + ' ' + a.v + ' → ' + a.latest + ' on ' + site.name) }; });
    const updCount = real ? 0 : this.PLUGINS.concat(this.THEMES).filter(a => a.v !== a.latest).length;
    const qsFiles = real ? this.realQsFiles(real, s) : this.QS_FILES;
    const curPath = qsFiles.some(f => f.path === s.qsFile) ? s.qsFile : (qsFiles[0] ? qsFiles[0].path : '');
    const curFile = qsFiles.find(f => f.path === curPath) || { path: '', st: 'M', add: 0, del: 0, diff: [] };
    if (!curFile.diff) curFile.diff = [['ctx', 'Loading diff…']];
    const mkLine = ([kind, text]) => ({ text,
      fg: kind === 'add' ? 'var(--ok)' : kind === 'del' ? 'var(--bad)' : 'var(--ink-dim)',
      bg: kind === 'add' ? 'var(--ok-soft)' : kind === 'del' ? 'var(--bad-soft)' : 'transparent' });
    const splitRows = curFile.diff.map(([kind, text]) => kind === 'del'
      ? { l: text, r: '', lbg: 'var(--bad-soft)', rbg: 'transparent', lfg: 'var(--bad)', rfg: 'var(--ink-dim)' }
      : kind === 'add' ? { l: '', r: text, lbg: 'transparent', rbg: 'var(--ok-soft)', lfg: 'var(--ink-dim)', rfg: 'var(--ok)' }
      : { l: text, r: text, lbg: 'transparent', rbg: 'transparent', lfg: 'var(--ink-dim)', rfg: 'var(--ink-dim)' });
    const qsSrc = real ? this.realQuicksaves(real) : this.QUICKSAVES;
    const quicksaves = qsSrc.map(qk => ({ ...qk,
      hash: qk.hashShort || qk.hash,
      kindBg: qk.kind === 'Update' ? 'var(--warn-soft)' : qk.kind === 'Manual' ? 'var(--brand-soft)' : 'var(--panel-2)',
      openD: () => { this.setState({ qsDialog: qk.hash, qsView: 'components', qsFile: '' }); if (real && qk.hash) this.loadQuicksaveDetail(qk.hash); },
      doRollback: () => real ? this.realRollbackAll(real, qk.hash) : this.runJob('rollback', site.name + ' → ' + qk.hash) }));
    // Guard on a non-empty selection: the loading-placeholder rows carry
    // hash/id '' which would match the initial '' state and ghost the dialog.
    const dlgQk = s.qsDialog ? qsSrc.find(q => q.hash === s.qsDialog) : null;
    const stMap = { A: ['var(--ok-soft)', 'var(--ok)'], M: ['var(--warn-soft)', 'var(--ink)'], D: ['var(--bad-soft)', 'var(--bad)'] };
    const dlgFiles = qsFiles.map(f => { const stC = stMap[f.st] || ['var(--panel-2)', 'var(--ink-dim)'];
      return { ...f, addN: f.add === '' ? '' : '+' + f.add, delN: f.del === '' ? '' : '−' + f.del,
      stBg: stC[0], stFg: stC[1],
      pick: () => { this.setState({ qsFile: f.path, qsView: 'diff' }); if (real) this.loadQsDiff(s.qsDialog, f.path); } }; });
    const mkComp = c => ({ ...c,
      rowBg: c.deleted ? 'var(--bad-soft)' : c.added ? 'var(--ok-soft)' : 'transparent',
      deco: c.deleted ? 'line-through' : 'none',
      nameFg: c.deleted ? 'var(--bad)' : 'var(--ink)',
      verCell: c.deleted ? c.from : c.added ? c.to : c.updated ? ((c.from && c.from !== c.to) ? c.from + ' → ' + c.to : c.to) : (c.from || c.to || '—'),
      verFg: c.updated ? 'var(--ink)' : 'var(--ink-dim)',
      badge: c.added ? 'New' : c.deleted ? 'Deleted' : c.updated ? 'Updated' : '',
      badgeBg: c.added ? 'var(--ok-soft)' : c.deleted ? 'var(--bad-soft)' : 'var(--warn-soft)',
      hasBadge: !!(c.added || c.deleted || c.updated),
      canView: !!c.viewFile,
      viewChanges: () => this.setState({ qsFile: c.viewFile, qsView: 'diff' }),
      rollback: () => this.setState({ rbComp: c.name }) });
    const dlgIdx = s.qsDialog ? qsSrc.findIndex(q => q.hash === s.qsDialog) : -1;
    const prevQk = dlgIdx >= 0 ? qsSrc[dlgIdx + 1] : null;
    const bkSrc = real ? (real.backups === null ? [{ id: '', idShort: '', when: 'Loading backups…', size: '', files: '' }] : (real.backups || [])) : this.BACKUPS;
    const backups = bkSrc.map(b => ({ ...b,
      id: b.idShort || b.id,
      openB: () => { this.setState({ bkDialog: b.id, bkSel: {}, bkPreview: '' }); if (real && b.id) this.loadBackupTree(b.id); },
      doRestore: () => real ? this.realBackupRestore(real, { ...s, bkDialog: b.id }) : this.runJob('restore', b.id + ' on ' + site.name) }));
    const bkDlg = s.bkDialog ? bkSrc.find(b => b.id === s.bkDialog) : null;
    const bkTreeSrc = real ? this.realBkTree(real, s) : this.BK_TREE;
    const flatAll = [];
    const flattenAll = nodes => nodes.forEach(n => { flatAll.push(n); if (n.children) flattenAll(n.children); });
    flattenAll(bkTreeSrc);
    const bkRows = [];
    const walk = (nodes, depth) => nodes.forEach(n => {
      const open = !!s.bkDirs[n.p];
      bkRows.push({ key: n.p,
        name: n.dir ? n.p.replace(/\/$/, '').split('/').pop() + '/' : n.p.split('/').pop(),
        meta: n.meta,
        pad: (14 + depth * 20) + 'px',
        arrow: n.dir && !n.omitted ? (open ? '▾' : '▸') : '',
        fg: n.omitted ? 'var(--ink-dim)' : 'var(--ink)',
        check: s.bkSel[n.p] ? '✓' : '', checkBg: s.bkSel[n.p] ? 'var(--brand)' : 'var(--paper)',
        toggleSel: (e) => { e.stopPropagation(); if (n.omitted) return;
          const val = !this.state.bkSel[n.p];
          const upd = { [n.p]: val };
          if (n.dir) flatAll.filter(x => x.p !== n.p && x.p.startsWith(n.p)).forEach(x => { upd[x.p] = val; });
          this.setState(st => ({ bkSel: { ...st.bkSel, ...upd } })); },
        rowClick: () => { if (n.dir && !n.omitted) this.setState(st => ({ bkDirs: { ...st.bkDirs, [n.p]: !st.bkDirs[n.p] } }));
          else if (n.prev) { this.setState({ bkPreview: n.p }); if (real) this.loadBackupPreview(n.p); } } });
      if (n.dir && open && n.children) walk(n.children, depth + 1);
    });
    walk(bkTreeSrc, 0);
    const selKeys = Object.keys(s.bkSel).filter(k => s.bkSel[k]);
    const topSel = selKeys.filter(k => !selKeys.some(o => o !== k && o.endsWith('/') && k.startsWith(o)));
    let selCnt = 0, selKb = 0;
    topSel.forEach(p => { const n = flatAll.find(x => x.p === p); if (n) { selCnt += n.cnt || 1; selKb += n.kb || 0; } });
    const fmtKb = kb => kb < 1024 ? Math.round(kb) + ' kB' : kb < 1048576 ? (kb / 1024).toFixed(1) + ' MB' : (kb / 1048576).toFixed(1) + ' GB';
    const dUsers = real ? this.realUserRows(real, s) : this.WP_USERS.map(u => ({ ...u, e: u.e.replace('SITE', site.name),
      init: u.n.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
      magic: () => this.runJob('magiclogin', u.e.replace('SITE', site.name)) }));
    const logChips = (real ? this.realLogFiles(real, s) : ['error.log', 'access.log', 'debug.log']).map(f => ({ label: f.split('/').pop(),
      bg: s.logFile === f ? 'var(--brand-soft)' : 'transparent',
      fg: s.logFile === f ? 'var(--brand-ink)' : 'var(--ink-dim)',
      bd: s.logFile === f ? 'var(--brand)' : 'transparent',
      go: () => real ? this.pickLogFile(f) : this.setState({ logFile: f }) }));
    const logLines = (real ? this.realLogLines(real, s) : (this.LOGS[s.logFile] || []).map(text => ({ text })))
      .map((l, i) => ({ ...l, segs: this.logSegments(l.text || ''), n: l.ph ? '' : String(i + 1) }));
    return {
      dName: site.name,
      dMeta: site.provider + ' · ' + site.account + ' · WP ' + site.core + ' · ' + site.visits + ' visits/wk · ' + site.storage + ' · ' + s.env,
      pBg: segBg('Production'), pFg: segFg('Production'), sBg: segBg('Staging'), sFg: segFg('Staging'),
      hasStaging: real && real.envs ? real.envs.some(e => e.environment === 'Staging') : true,
      setEnvProd: () => this.setEnv('Production'), setEnvStag: () => this.setEnv('Staging'),
      backToSites: () => this.setState({ route: 'sites' }),
      dSync: () => real ? this.realSync(real, s) : this.runJob('sync-data', site.name),
      dTerm: () => this.setState({ dockOpen: true }),
      dWpLogin: () => real ? this.realMagicLogin(real, s) : this.runJob('magiclogin', site.name),
      pushEnv: () => real ? this.realPush(real, 'up') : this.runJob('deploy', 'staging → production on ' + site.name),
      pullEnv: () => real ? this.realPush(real, 'down') : this.runJob('deploy', 'production → staging on ' + site.name),
      dTabs: tabs,
      tabOverview: s.siteTab === 'overview', tabStats: s.siteTab === 'stats', tabAddons: s.siteTab === 'addons', tabVersions: s.siteTab === 'versions',
      tabBackups: s.siteTab === 'backups', tabSnapshots: s.siteTab === 'snapshots', tabUsers: s.siteTab === 'users', tabLogs: s.siteTab === 'logs', tabTimeline: s.siteTab === 'timeline',
      credRows,
      statTiles: [
        { k: 'Visits / wk', v: site.visits, delta: real ? '' : '+8%', deltaFg: 'var(--ok)', act: 'stats', icon: 'M22 12h-4l-3 9L9 3l-3 9H2' },
        { k: 'Backups', v: real ? (real.backups ? String(real.backups.length) : '—') : '1,284', delta: 'nightly + PITR', deltaFg: 'var(--ink-dim)', act: 'backups', icon: 'M21 8v13H3V8 M1 3h22v5H1 M10 12h4' },
        { k: 'Versions', v: real ? (real.qs ? String(real.qs.length) : '—') : '412', delta: 'quicksaves + updates', deltaFg: 'var(--ink-dim)', act: 'versions', icon: 'M6 3v12 M6 21a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z M18 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z M18 9a9 9 0 0 1-9 9' },
        { k: 'Timeline', v: real ? (Array.isArray(real.timeline) ? String(real.timeline.length) : '—') : '86', delta: real ? 'process log' : 'last note 2h ago', deltaFg: 'var(--ink-dim)', act: 'timeline', icon: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z M12 7v5l3 3' }
      ].map(t => ({ ...t, tip: 'Open ' + t.act, go: () => { this.setState({ siteTab: t.act });
        if (!real) return;
        if (t.act === 'backups') this.loadBackups(); else if (t.act === 'versions') this.loadQuicksaves(); else if (t.act === 'timeline') this.loadTimeline(); else if (t.act === 'stats') this.loadStats(); } })),
      openStats: () => { this.setState({ siteTab: 'stats' }); if (real) this.loadStats(); },
      statG: s.statG, statR: s.statR,
      ddStatGOpen: s.ddOpen === 'statG',
      ddToggleStatG: () => this.setState(st => ({ ddOpen: st.ddOpen === 'statG' ? '' : 'statG', ddQ: '' })),
      ddStatGOpts: this.ddOpts(real ? ['Daily', 'Monthly', 'Yearly'] : ['Daily', 'Weekly', 'Monthly'], s.statG, 'statG')
        .map(o => real ? { ...o, pick: () => { o.pick(); setTimeout(() => this.loadStats(), 0); } } : o),
      ddStatROpen: s.ddOpen === 'statR',
      ddToggleStatR: () => this.setState(st => ({ ddOpen: st.ddOpen === 'statR' ? '' : 'statR', ddQ: '' })),
      ddStatROpts: this.ddOpts(['Last 7 days', 'Last 28 days', 'Last 90 days', 'This year'], s.statR, 'statR')
        .map(o => real ? { ...o, pick: () => { o.pick(); setTimeout(() => this.loadStats(), 0); } } : o),
      shareChips: ['Off', 'Private', 'Public'].map(label => ({ label,
        bg: s.statShare === label ? 'var(--brand-soft)' : 'var(--paper)',
        fg: s.statShare === label ? 'var(--brand-ink)' : 'var(--ink-dim)',
        bd: s.statShare === label ? 'var(--brand)' : 'var(--rule)',
        go: () => this.setState({ statShare: label }) })),
      statPrivate: s.statShare === 'Private', statShared: s.statShare !== 'Off',
      statPw: s.statPw, onStatPw: e => this.setState({ statPw: e.target.value }),
      statLinkMark: s.copied === 'statlink' ? 'Copied ✓' : 'Copy share link',
      copyStatLink: () => { try { navigator.clipboard.writeText('https://' + site.name + '/stats?share=…'); } catch (e) {}
        this.setState({ copied: 'statlink' }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
      statTilesBig: [
        { k: 'Visitors', v: '9,120', delta: '+6%', deltaFg: 'var(--ok)' },
        { k: 'Pageviews', v: '24,388', delta: '+8%', deltaFg: 'var(--ok)' },
        { k: 'Avg time on site', v: '1m 42s', delta: '', deltaFg: 'var(--ink-dim)' },
        { k: 'Bounce rate', v: '38%', delta: '−3%', deltaFg: 'var(--ok)' }
      ],
      statChartLabel: s.statG.toLowerCase() + ' · ' + s.statR.toLowerCase() + ' · Fathom',
      statBars: (() => { const n = s.statG === 'Daily' ? 28 : s.statG === 'Weekly' ? 12 : 6;
        return Array.from({ length: n }, (_, i) => { const h = 28 + ((i * 37 + 11) % 58);
          return { h, tip: Math.round(h * 14) + ' views', date: 'Day ' + (i + 1), views: String(Math.round(h * 14)), visits: String(Math.round(h * 9)), enter: () => this.setState({ chartHoverIdx: i }), bg: i === n - 1 ? 'var(--brand)' : 'color-mix(in srgb, var(--brand) 38%, transparent)' }; }); })(),
      statsShowPerf: true, chartMove: e => { const r = e.currentTarget.getBoundingClientRect(); this.setState({ chartHoverX: Math.round(e.clientX - r.left), chartHoverY: Math.round(e.clientY - r.top) }); }, chartLeave: () => this.setState({ chartHoverIdx: -1 }),
      chartTipShow: false, chartTipLeft: 0, chartTipTop: 0, chartTipDate: '', chartTipViews: '', chartTipVisits: '',
      topPages: [['/', '4,812'], ['/shop/', '2,391'], ['/about/', '1,204'], ['/blog/summer-arrangements/', '986'], ['/contact/', '743']].map(([k, v]) => ({ k, v })),
      topRefs: [['direct', '4,201'], ['google.com', '3,104'], ['instagram.com', '1,822'], ['facebook.com', '640'], ['pinterest.com', '512']].map(([k, v]) => ({ k, v })),
      perfRows: [['TTFB (p75)', '142 ms'], ['Largest Contentful Paint (p75)', '1.8 s'], ['Checks · last 24h', '288 · all passing']].map(([k, v]) => ({ k, v })),
      visitBars: [35, 42, 38, 55, 48, 60, 52, 45, 66, 58, 72, 64, 80, 74].map((h, i) => ({ h,
        bg: i === 13 ? 'var(--brand)' : 'color-mix(in srgb, var(--brand) 38%, transparent)' })),
      ...(real ? this.realStatVals(s, site) : (window.CC_BOOT ? this.emptyStatVals() : { statsNotice: false, statsNoticeText: '' })),
      envRows: (real ? this.realEnvRows(real, s) : [['WordPress', site.core], ['PHP', '8.3.8'], ['Storage', site.storage], ['Visits / wk', site.visits], ['Uptime monitor', 'On · 99.98%'], ['Managed updates', site.updates ? site.updates + ' pending' : 'Up to date']]).map(([k, v]) => ({ k, v })),
      dDomains: (real && real.domains
        ? real.domains.map(d => ({ name: (d && d.name) || String(d), did: d && d.domain_id ? String(d.domain_id) : '' }))
        : [site.name, 'www.' + site.name].map(name => ({ name, did: '' })))
        .map(d => ({ ...d, open: () => { if (d.did) this.openDomain(d.did); } })),
      dGoDomains: () => this.setState({ route: 'domains' }),
      sharedRows: (real && real.sharedWith
        ? [...real.sharedWith.map(a => ({ uid: 'acc' + a.account_id, name: a.name, accId: String(a.account_id),
            owner: real.site && String(real.site.customer_id) === String(a.account_id),
            level: real.site && String(real.site.customer_id) === String(a.account_id) ? 'Owner' : 'Shared' })),
           ...(s.shared || [])]
        : (s.shared || this.SHARED_INIT)).map(sh => ({ ...sh,
        level: sh.level || (sh.pending ? 'Invited' : 'Shared'),
        sub: sh.pending ? 'invite sent — pending' : (sh.people !== undefined ? sh.people + (sh.people === 1 ? ' person' : ' people') : 'account with access'),
        lvlBg: sh.owner ? 'var(--brand-soft)' : 'var(--panel-2)',
        lvlFg: sh.owner ? 'var(--brand-ink)' : 'var(--ink-dim)',
        removable: !sh.owner && !!sh.pending,
        open: () => { if (sh.accId) this.openAccount(sh.accId); },
        remove: () => this.setState(st => ({ shared: (st.shared || []).filter(x => x.uid !== sh.uid) })) })),
      shareDraft: s.shareDraft, onShareDraft: e => this.setState({ shareDraft: e.target.value }),
      doShare: () => { if (real) { this.openShareDialog(); return; }
        if (window.CC_BOOT) { this.toast('Site details still loading…', { kind: 'info' }); return; }
        const v = this.state.shareDraft.trim(); if (!v) return;
        this.setState(st => ({ shared: [...(st.shared || this.SHARED_INIT), { uid: Date.now(), name: v, people: 0, level: 'Sites only', pending: true }], shareDraft: '' }));
        this.runJob('grant-access', v + ' → ' + site.name); },
      ...this.computeShareDialog(real, s, site),
      showPma: !!(real && real.site && ['kinsta', 'rocketdotnet'].includes(real.site.provider)) || (!real && !window.CC_BOOT),
      openPma: () => { if (real) this.realPhpMyAdmin(real, s); },
      akpBg: s.addonKind === 'plugins' ? 'var(--panel-2)' : 'transparent', akpFg: s.addonKind === 'plugins' ? 'var(--ink)' : 'var(--ink-dim)',
      aktBg: s.addonKind === 'themes' ? 'var(--panel-2)' : 'transparent', aktFg: s.addonKind === 'themes' ? 'var(--ink)' : 'var(--ink-dim)',
      setAddP: () => this.setState({ addonKind: 'plugins' }), setAddT: () => this.setState({ addonKind: 'themes' }),
      addons, hasUpdates: updCount > 0, updateAllLabel: 'Update all (' + updCount + ')',
      doUpdateAll: () => this.runJob('update-wp', site.name + ' · ' + updCount + ' components'),
      ...this.computeAddAddon(real, s, site),
      quicksaves, newQuicksave: () => real ? this.realNewQuicksave(real) : this.runJob('quicksave', site.name),
      qsDialogOpen: !!dlgQk,
      dlgHash: dlgQk ? (dlgQk.hashShort || dlgQk.hash) : '', dlgDesc: dlgQk ? dlgQk.desc : '', dlgWhen: dlgQk ? dlgQk.when : '',
      dlgSummary: dlgQk ? dlgQk.summary : '',
      dlgMoreFiles: !real && dlgQk && dlgQk.more > 0 ? '… ' + dlgQk.more + ' more files — search or narrow by component to see the rest.' : '',
      closeQsDlg: () => this.setState({ qsDialog: '' }),
      dlgIsComp: s.qsView === 'components', dlgIsFiles: s.qsView === 'files', dlgIsDiff: s.qsView === 'diff',
      dlgNotDiff: s.qsView !== 'diff',
      dlgCompFg: s.qsView === 'components' ? 'var(--ink)' : 'var(--ink-dim)',
      dlgCompBg: s.qsView === 'components' ? 'var(--panel-2)' : 'transparent',
      dlgFilesFg: s.qsView === 'files' ? 'var(--ink)' : 'var(--ink-dim)',
      dlgFilesBg: s.qsView === 'files' ? 'var(--panel-2)' : 'transparent',
      setDlgComp: () => this.setState({ qsView: 'components' }),
      setDlgFiles: () => this.setState({ qsView: 'files' }),
      dlgThemes: (real ? this.realQsComponents(real, s, 'theme') : this.QS_COMPONENTS.filter(c => c.kind === 'theme')).map(mkComp),
      dlgPlugins: (real ? this.realQsComponents(real, s, 'plugin') : this.QS_COMPONENTS.filter(c => c.kind === 'plugin')).map(mkComp),
      dlgFiles, dlgFilePath: curPath,
      dlgDiff: curFile.diff.map(mkLine), dlgSplit: splitRows,
      backToFiles: () => this.setState({ qsView: 'files' }),
      dlgSandbox: () => real ? this.realSandbox(real, s.qsDialog) : this.runJob('sandbox', 'Playground preview of ' + (dlgQk ? dlgQk.hash : '')),
      dlgRollback: () => real ? this.realRollbackAll(real, s.qsDialog) : this.runJob('rollback', site.name + ' → ' + (dlgQk ? dlgQk.hash : '')),
      dlgRestoreFile: () => real ? this.realRestoreFile(real, s.qsDialog, curPath) : this.runJob('restore-file', curPath.split('/').pop() + ' from ' + (dlgQk ? dlgQk.hash : '')),
      rbOpen: !!s.rbComp,
      rbTitle: 'Roll back ' + s.rbComp + '?',
      rbThisSub: dlgQk ? dlgQk.when + ' · ' + dlgQk.hash : '',
      rbPrevSub: prevQk ? prevQk.when + ' · ' + prevQk.hash : 'No earlier quicksave',
      closeRb: () => this.setState({ rbComp: '' }),
      rbPickThis: () => { if (real) { const c = this.realQsComponents(real, s, 'theme').concat(this.realQsComponents(real, s, 'plugin')).find(x => x.name === s.rbComp);
          if (c) this.realRollbackComponent(real, s.qsDialog, c, 'this'); }
        else this.runJob('rollback-component', s.rbComp + ' → version in ' + (dlgQk ? dlgQk.hash : ''));
        this.setState({ rbComp: '' }); },
      rbPickPrev: () => { if (!prevQk) return;
        if (real) { const c = this.realQsComponents(real, s, 'theme').concat(this.realQsComponents(real, s, 'plugin')).find(x => x.name === s.rbComp);
          if (c) this.realRollbackComponent(real, s.qsDialog, c, 'previous'); }
        else this.runJob('rollback-component', s.rbComp + ' → version in ' + prevQk.hash);
        this.setState({ rbComp: '' }); },
      qsUnified: s.diffMode === 'unified', qsSplit: s.diffMode === 'split',
      uniBg: s.diffMode === 'unified' ? 'var(--panel-2)' : 'transparent', uniFg: s.diffMode === 'unified' ? 'var(--ink)' : 'var(--ink-dim)',
      splBg: s.diffMode === 'split' ? 'var(--panel-2)' : 'transparent', splFg: s.diffMode === 'split' ? 'var(--ink)' : 'var(--ink-dim)',
      setUni: () => this.setState({ diffMode: 'unified' }), setSplit: () => this.setState({ diffMode: 'split' }),
      backups, backupNow: () => real ? this.realBackupNow(real) : this.runJob('backup', site.name),
      bkRows,
      bkDlgOpen: !!bkDlg, bkDlgId: bkDlg ? (bkDlg.idShort || bkDlg.id) : '', bkDlgWhen: bkDlg ? bkDlg.when : '',
      bkDlgMeta: bkDlg ? bkDlg.size + ' · ' + bkDlg.files : '',
      closeBkDlg: () => this.setState({ bkDialog: '', bkPreview: '', bkSel: {} }),
      bkDlgRestore: () => real ? this.realBackupRestore(real, s) : this.runJob('restore', (bkDlg ? bkDlg.id : '') + ' on ' + site.name),
      bkHasSel: selCnt > 0,
      bkSelTitle: selCnt.toLocaleString() + ' items selected',
      bkSelSize: fmtKb(selKb),
      bkDownload: () => { if (real) { this.realBackupDownload(real, s, topSel, flatAll); return; }
        this.runJob('backup-download-notify', selCnt.toLocaleString() + ' items (' + fmtKb(selKb) + ') → austin@anchor.host'); this.setState({ bkSel: {} }); },
      cancelSel: () => this.setState({ bkSel: {} }),
      bkShowPrev: !!s.bkPreview && selCnt === 0,
      bkShowPlaceholder: selCnt === 0 && !s.bkPreview,
      selectAll: () => { const upd = {}; flatAll.forEach(n => { if (!n.omitted) upd[n.p] = true; }); this.setState({ bkSel: upd }); },
      bkPrevPath: s.bkPreview,
      bkPrevLines: real ? this.realBkPreviewLines(real, s) : (this.PREVIEWS[s.bkPreview] || this.PREVIEWS.default || []).map(text => ({ text })),
      closePrev: () => this.setState({ bkPreview: '' }),
      snapFilter: s.snapFilter,
      ddSnapOpen: s.ddOpen === 'snap',
      ddToggleSnap: () => this.setState(st => ({ ddOpen: st.ddOpen === 'snap' ? '' : 'snap', ddQ: '' })),
      ddSnapOpts: this.ddOpts(['Everything', 'Database', 'Themes', 'Plugins', 'Uploads'], s.snapFilter, 'snapFilter'),
      createSnap: () => real ? this.realCreateSnapshot(real, s) : this.runJob('snapshot', this.state.snapFilter + ' · ' + site.name),
      snapshots: (real ? this.realSnapshots(real) : this.SNAPSHOTS).map(sn => { const expired = sn.expires === 'expired'; return { ...sn, expired, live: !expired,
        expLabel: expired ? 'Link expired' : 'Link expires in ' + sn.expires,
        expFg: expired ? 'var(--ink-dim)' : 'var(--ok)',
        mark: s.copied === sn.id ? 'Copied ✓' : 'Copy link',
        copyLink: () => { try { navigator.clipboard.writeText(sn._real ? sn._url : 'https://' + site.name + '/snapshot/' + sn.id); } catch (e) {}
          this.setState({ copied: sn.id }); clearTimeout(this._ct); this._ct = setTimeout(() => this.setState({ copied: '' }), 1400); },
        regen: () => sn._real ? this.realSnapshotLink(real, sn) : this.runJob('snapshot-link', 'new 24h link · ' + sn.name),
        doDl: () => sn._real ? window.open(sn._url) : this.runJob('snapshot-download', sn.name) }; }),
      dUsers, logChips, logLines,
      logMeta: real ? (real.logsLoading ? 'Loading…' : logLines.length + ' lines') : logLines.length + ' lines · last 24h',
      tlRows: (real ? (real.timeline === null ? [{ uid: 0, text: 'Loading timeline…', who: 'System', when: '' }] : (real.timeline || [])) : (s.timeline || this.TIMELINE_INIT)).map(t => ({ ...t,
        init: t.who.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
        // Raw-HTML escape hatch: the DC runtime has no innerHTML binding, so
        // the row's ref injects the server-rendered markdown (or escaped text).
        mdRef: (el) => { if (!el) return;
          const want = t.html || String(t.text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
          if (el._md !== want) { el._md = want; el.innerHTML = want; } },
        editing: s.tlEdit === t.uid, notEditing: s.tlEdit !== t.uid,
        startEdit: () => this.setState({ tlEdit: t.uid, tlEditText: t.text }),
        doneEdit: () => { if (real) { const v = this.state.tlEditText.trim(); if (v && v !== t.text) this.realTimelineEdit(real, t, v); this.setState({ tlEdit: 0 }); return; }
          this.setState(st => ({ timeline: (st.timeline || this.TIMELINE_INIT).map(x => x.uid === st.tlEdit ? { ...x, text: st.tlEditText.trim() || x.text } : x), tlEdit: 0 })); },
        cancelEdit: () => this.setState({ tlEdit: 0 }),
        del: () => { if (real) { this.realTimelineDelete(real, t); return; }
          this.setState(st => ({ timeline: (st.timeline || this.TIMELINE_INIT).filter(x => x.uid !== t.uid) })); } })),
      tlDraft: s.tlDraft, onTlDraft: e => this.setState({ tlDraft: e.target.value }),
      tlEditText: s.tlEditText, onTlEditText: e => this.setState({ tlEditText: e.target.value }),
      addTl: () => { const v = this.state.tlDraft.trim(); if (!v) return;
        if (real) { this.realTimelineAdd(real, v); this.setState({ tlDraft: '' }); return; }
        this.setState(st => ({ timeline: [{ uid: Date.now(), text: v, who: 'Austin Ginder', when: 'just now' }, ...(st.timeline || this.TIMELINE_INIT)], tlDraft: '' })); },
      exportTl: () => { if (real) {
          const blob = new Blob([JSON.stringify({ site: { name: site.name, site_id: real.siteId }, entries: (real.timeline || []).map(t => t._raw || t) }, null, 2)], { type: 'application/json' });
          const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'timeline.json'; a.click(); URL.revokeObjectURL(a.href); return; }
        this.runJob('timeline-export', site.name + ' → JSON') }
    };
  }

  // mdi label-icon names → feather-style 24x24 stroke paths (site labels come
  // from monitor-check with an mdi icon name; fall back to the tag glyph).
  LABEL_ICONS = {
    'mdi-tag': 'M20.6 13.4 12 22l-9-9V4a1 1 0 011-1h8zM7.5 7.5h.01',
    'mdi-alert': 'M12 3 2 20h20zM12 9v5M12 17.5h.01',
    'mdi-cancel': 'M12 3a9 9 0 100 18 9 9 0 000-18zM5.6 5.6l12.8 12.8',
    'mdi-swap-horizontal': 'M7 8h13l-3.5-3.5M17 16H4l3.5 3.5',
    'mdi-dns': 'M4 5h16v5H4zM4 14h16v5H4zM7.5 7.5h.01M7.5 16.5h.01',
    'mdi-calendar-remove': 'M4 5h16v16H4zM4 9h16M8 3v4M16 3v4M9.5 14.5l5 3M14.5 14.5l-5 3',
    'mdi-clock-alert': 'M11 3a8 8 0 100 16 8 8 0 000-16zM11 7v4l2.5 2M20 8v4M20 16h.01',
    'mdi-web': 'M12 3a9 9 0 100 18 9 9 0 000-18zM3 12h18M12 3c2.7 2.7 2.7 15.3 0 18-2.7-2.7-2.7-15.3 0-18z',
    'mdi-check': 'M4 12l5 5L20 6',
    'mdi-star': 'M12 3l2.6 5.6 6 .8-4.4 4.2 1.1 6-5.3-3-5.3 3 1.1-6L3.4 9.4l6-.8z',
    'mdi-flag': 'M5 21V4h11l-1.5 4L16 12H5',
    'mdi-lock': 'M6 11h12v9H6zM8 11V8a4 4 0 018 0v3'
  };
  ICONS = {
    home: 'M3 10.5 12 3l9 7.5V20a1 1 0 01-1 1h-5v-6h-6v6H4a1 1 0 01-1-1z',
    sites: 'M3 12l9 5 9-5M3 7l9 5 9-5-9-5-9 5z',
    domains: 'M12 3a9 9 0 100 18 9 9 0 000-18zM3 12h18M12 3c2.7 2.7 2.7 15.3 0 18-2.7-2.7-2.7-15.3 0-18z',
    accounts: 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M15 3.13a4 4 0 010 7.75',
    billing: 'M2 6.5A1.5 1.5 0 013.5 5h17A1.5 1.5 0 0122 6.5v11a1.5 1.5 0 01-1.5 1.5h-17A1.5 1.5 0 012 17.5zM2 10h20M6 14.5h4',
    security: 'M12 3l8 3v6c0 4.6-3.2 7.9-8 9-4.8-1.1-8-4.4-8-9V6l8-3z',
    audits: 'M9 3h6v4H9zM6 5H5v16h14V5h-1M9 13.5l2 2 4.5-4.5',
    reports: 'M4 20h16M7 16v-5M12 16V7M17 16v-9',
    archives: 'M3 7h18v4H3zM5 11v9a1 1 0 001 1h12a1 1 0 001-1v-9M10 15h4',
    settings: 'M4 7h9M17 7h3M13 4v6M4 17h3M11 17h9M7 14v6',
    terminal: 'M4 5h16v14H4zM7.5 9l3 3-3 3M12.5 15H17',
    activity: 'M22 12h-4l-3 9L9 3l-3 9H2',
    search: 'M11 4a7 7 0 100 14 7 7 0 000-14zm10 17-5.2-5.2',
    support: 'M12 3a9 9 0 100 18 9 9 0 000-18zm0 14v.01M9.5 9a2.5 2.5 0 115 0c0 1.5-2.5 2-2.5 3.5',
    site: 'M4 5h16v14H4zM4 9h16M7 7h.01',
    quicksave: 'M12 8v4l3 3M12 3a9 9 0 100 18 9 9 0 000-18z'
  };

  CONSOLE_SCRIPT = [
    ['dim', '$ captaincore update-wp @updates-pending --parallel=3'],
    ['ink', '[bloomandbranch.com] Creating quicksave before update…'],
    ['ok',  '[bloomandbranch.com] ✓ Quicksave 8f3c21a'],
    ['ink', '[bloomandbranch.com] Updating gravityforms 2.9.1 → 2.9.4'],
    ['ok',  '[bloomandbranch.com] ✓ gravityforms updated'],
    ['ink', '[harborlightyoga.com] Updating woocommerce 9.8.2 → 9.9.0'],
    ['ok',  '[harborlightyoga.com] ✓ woocommerce updated'],
    ['ink', '[petersonlaw.com] Updating theme kadence 1.2.14 → 1.2.15'],
    ['ok',  '[petersonlaw.com] ✓ kadence updated'],
    ['ink', '[petersonlaw.com] Verifying checksums…'],
    ['ok',  '[petersonlaw.com] ✓ Checksums clean · 0 modified files']
  ];

  componentDidMount() {
    const saved = localStorage.getItem('captaincore-theme');
    const theme = saved || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    this.setState({ theme });
    this.applyTheme(theme);
    this.applyBrand();
    // Real users (CC_BOOT injected) start with an empty job list — the design's
    // sample jobs only exist for the DC-editor preview.
    if (window.CC_BOOT) { this.setState({ jobs: [] }); this.initRouter(); }
    // Debug/test handle — lets DevTools and Playwright probes reach the
    // component instance (e.g. seed fake jobs to exercise the dock).
    window.CC = this;
    this.onKey = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); this.setState(s => ({ paletteOpen: !s.paletteOpen, palQuery: '', palIdx: 0 })); }
      else if (e.ctrlKey && e.key === '`') { e.preventDefault(); this.setState(s => ({ dockOpen: !s.dockOpen })); }
      else if ((e.metaKey || e.ctrlKey) && e.key === 'Enter' && this.state.dockOpen) { e.preventDefault(); this.termRun(); }
      else if (e.key === 'Escape') { if (this.state.rbComp) this.setState({ rbComp: '' }); else this.setState({ paletteOpen: false, qsDialog: '', bkDialog: '', nsOpen: false, ndOpen: false, zoneOpen: false, nsvOpen: false, ctOpen: false, tpOpen: false, cookOpen: false }); }
      else if (this.state.paletteOpen && e.key === 'ArrowDown') { e.preventDefault(); this.setState(s => ({ palIdx: Math.min(s.palIdx + 1, this.filteredPal(s.palQuery).length - 1) })); }
      else if (this.state.paletteOpen && e.key === 'ArrowUp') { e.preventDefault(); this.setState(s => ({ palIdx: Math.max(s.palIdx - 1, 0) })); }
      else if (this.state.paletteOpen && e.key === 'Enter') { const r = this.filteredPal(this.state.palQuery)[this.state.palIdx]; if (r) this.runPal(r); }
    };
    window.addEventListener('keydown', this.onKey);
    this.hydrate();
    if (this.hydrateHome) this.hydrateHome();
    this.timer = setInterval(() => this.setState(s => ({ tick: s.tick + 1,
      jobs: s.jobs.map(j => j.state === 'running' && !j.real
        ? (j.pct >= 100 ? { ...j, state: 'done', right: 'just now', pct: 100 } : { ...j, pct: Math.min(100, j.pct + 3 + Math.random() * 7) })
        : j) })), 1800);
  }
  componentWillUnmount() { window.removeEventListener('keydown', this.onKey); clearInterval(this.timer); }
  componentDidUpdate() {
    this.applyBrand();
    if (this._routerReady) this.syncUrl();
    // Sticky-bottom like a real terminal: only follow output while the user
    // is parked at the bottom; a manual scroll up releases the pin until they
    // scroll back down (tracked by the consoleRef scroll listener).
    if (this._consoleEl && this._consolePinned) this._consoleEl.scrollTop = this._consoleEl.scrollHeight;
    // Focus the command palette input the moment it opens (autofocus only
    // fires on first page load, not on dynamic mount).
    if (this.state.paletteOpen && !this._palWasOpen) {
      const el = document.querySelector('input[placeholder^="Search sites, domains, accounts"]');
      if (el) el.focus();
    }
    this._palWasOpen = this.state.paletteOpen;
  }

  applyTheme(t) { document.documentElement.dataset.theme = t; }

  // ── Context menus (Minn pattern) ── entries are built from each row's own
  // actions so the menu can never drift from the row.
  openCtxMenu(e, entries) {
    e.preventDefault(); e.stopPropagation();
    const w = 230, h = 10 + entries.length * 37;
    this.setState({ ctxMenu: {
      x: Math.max(8, Math.min(e.clientX, window.innerWidth - w - 12)),
      y: Math.max(8, Math.min(e.clientY, window.innerHeight - h - 12)),
      entries } });
  }

  closeCtxMenu() { if (this.state.ctxMenu) this.setState({ ctxMenu: null }); }

  // ── Sortable table headers (Minn pattern) ── cols: [{label, k?, val?}].
  // Emits header cells with direction arrows; clicking toggles asc/desc on the
  // per-route sort state key. sortRows applies the matching state to a list.
  mkSortCols(stateKey, cols) {
    const cur = this.state[stateKey] || { k: '', d: 1 };
    return cols.map(c => ({ label: c.label,
      arrow: c.k && cur.k === c.k ? (cur.d === 1 ? ' ↑' : ' ↓') : '',
      fg: c.k && cur.k === c.k ? 'var(--ink)' : 'var(--ink-dim)',
      cursor: c.k ? 'pointer' : 'default',
      go: c.k ? () => { const p = this.state[stateKey] || { k: '', d: 1 };
        this.setState({ [stateKey]: { k: c.k, d: p.k === c.k ? -p.d : 1 } }); } : () => {} }));
  }

  sortRows(stateKey, cols, list) {
    const cur = this.state[stateKey] || { k: '', d: 1 };
    if (!cur.k) return list;
    const col = cols.find(c => c.k === cur.k);
    if (!col || !col.val) return list;
    return list.slice().sort((a, b) => {
      const va = col.val(a), vb = col.val(b);
      const r = (typeof va === 'number' && typeof vb === 'number') ? va - vb
        : String(va).localeCompare(String(vb), undefined, { numeric: true, sensitivity: 'base' });
      return r * cur.d;
    });
  }

  ctxCopy(text, label) {
    if (navigator.clipboard) navigator.clipboard.writeText(text).then(() => this.toast('Copied ' + label + '.', { kind: 'success' })).catch(() => {});
  }
  applyBrand() { document.documentElement.style.setProperty('--brand', (window.CC_BOOT && window.CC_BOOT.brandColor) || this.props.brandColor || '#3b82c4'); }

  go(route) { return () => this.setState({ route, paletteOpen: false, ctxMenu: null }); }

  navItem(id, label, icon) {
    const active = this.state.route === id;
    const count = this._hydrated ? (id === 'sites' ? this.FLEET.length : id === 'domains' ? this.DOMAINS.length : 0) : 0;
    return { id, label, icon: this.ICONS[icon || id],
      fg: active ? 'var(--brand-ink)' : 'var(--ink-dim)',
      bg: active ? 'var(--brand-soft)' : 'transparent',
      count: count ? count.toLocaleString() : '', countDisplay: count ? 'inline-block' : 'none',
      go: this.go(id) };
  }

  filteredPal(q) {
    const role = (window.CC_BOOT && window.CC_BOOT.dcRole) || this.props.role || 'operator';
    const items = (this._hydrated || window.CC_BOOT) ? this.realPalItems(role) : [
      { label: 'bloomandbranch.com', sub: 'Kinsta · Production + Staging', kind: 'site', icon: this.ICONS.site, act: 'site', sid: 'bloom' },
      { label: 'harborlightyoga.com', sub: 'Kinsta · Production', kind: 'site', icon: this.ICONS.site, act: 'site', sid: 'harbor' },
      { label: 'petersonlaw.com', sub: 'WP Engine · Production + Staging', kind: 'site', icon: this.ICONS.site, act: 'site', sid: 'peterson' },
      { label: 'cascadecoffeeroasters.com', sub: 'Kinsta · Production', kind: 'site', icon: this.ICONS.site, act: 'site', sid: 'cascade' },
      { label: 'thewildflowerpantry.com', sub: 'DNS active · Hover', kind: 'domain', icon: this.ICONS.domains, act: 'domain', did: 'wildflowerd' },
      { label: 'midwestmakersmarket.com', sub: 'DNS active · Spaceship', kind: 'domain', icon: this.ICONS.domains, act: 'domain', did: 'midwestd' },
      { label: 'Open terminal', sub: 'Streamed console on any site', kind: 'command', icon: this.ICONS.terminal, act: 'dock' },
      { label: 'New quicksave on…', sub: 'Git snapshot of a site', kind: 'command', icon: this.ICONS.quicksave, act: 'dock' },
      { label: 'Go to Billing → Invoices', sub: '', kind: 'command', icon: this.ICONS.billing, act: 'billing' },
      ...(role === 'operator' ? [
        { label: 'Go to Security → Coverage', sub: 'Fleet audit coverage', kind: 'command', icon: this.ICONS.security, act: 'security' },
        { label: 'Bulk tools on filtered sites…', sub: 'sync · deploy defaults · https · backup', kind: 'command', icon: this.ICONS.sites, act: 'sites' }
      ] : [])
    ];
    const needle = q.trim().toLowerCase();
    return (needle ? items.filter(i => (i.label + ' ' + i.sub + ' ' + i.kind).toLowerCase().includes(needle)) : items).slice(0, 8);
  }
  runPal(r) {
    if (r.act === 'dock') this.setState({ dockOpen: true, paletteOpen: false });
    else if (r.act === 'site') this.openSite(r.sid);
    else if (r.act === 'domain') this.openDomain(r.did);
    else this.setState({ route: r.act, paletteOpen: false });
  }

  renderVals() {
    const role = (window.CC_BOOT && window.CC_BOOT.dcRole) || this.props.role || 'operator';
    const variant = this.props.shellVariant ?? 'rail';
    const s = this.state;
    const isOp = role === 'operator';
    const hour = new Date().getHours();
    const dayPart = hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening';
    const userName = (window.CC_BOOT && window.CC_BOOT.userFirstName) || (isOp ? 'Austin' : 'Kara');

    const primary = isOp
      ? [this.navItem('home', 'Home'), this.navItem('sites', 'Sites'), this.navItem('domains', 'Domains'), this.navItem('accounts', 'Accounts'), this.navItem('billing', 'Billing')]
      : [this.navItem('home', 'Home'), this.navItem('sites', 'Sites'), this.navItem('domains', 'Domains'), this.navItem('billing', 'Billing'), this.navItem('reports', 'Reports')];
    const operate = [this.navItem('security', 'Security'), this.navItem('audits', 'Site Audits', 'audits'), this.navItem('activity', 'Activity'), this.navItem('reports', 'Reports'), this.navItem('archives', 'Archives')];

    // When booted (real app), never show design sample counts — '…' until
    // hydration lands, then real numbers. Samples remain for the DC editor.
    const booted = !!window.CC_BOOT;
    const launcher = (isOp ? [
      { label: 'Sites', desc: 'Fleet list, filters, bulk tools', meta: this._hydrated ? String(this.FLEET.length) : (booted ? '…' : '128'), icon: this.ICONS.sites, act: 'sites', acc: 'sites' },
      { label: 'Domains & DNS', desc: 'Zones, registrar, email', meta: this._hydrated ? String(this.DOMAINS.length) : (booted ? '…' : '94'), icon: this.ICONS.domains, act: 'domains', acc: 'domains' },
      { label: 'Security', desc: 'Vulnerabilities, checksums, coverage', meta: this._homeThreats ? this._homeThreats.total_threats + ' open' : (booted ? '…' : '2 open'), icon: this.ICONS.security, act: 'security', acc: 'security' },
      { label: 'Billing', desc: 'Invoices, plans, subscriptions', meta: booted ? '' : '$12.4k/mo', icon: this.ICONS.billing, act: 'billing', acc: 'billing' },
      { label: 'Activity', desc: 'Fleet-wide event log', meta: this._activity ? String(this._activity.filter(a => a.t && !/\dd$/.test(a.t)).length) + ' today' : (booted ? '…' : '14 today'), icon: this.ICONS.activity, act: 'activity', acc: 'terminal' }
    ] : [
      { label: 'My sites', desc: 'Backups, updates, stats', meta: this._hydrated ? String(this.FLEET.length) : (booted ? '…' : '4'), icon: this.ICONS.sites, act: 'sites', acc: 'sites' },
      { label: 'Domains', desc: 'DNS and email forwarding', meta: this._hydrated ? String(this.DOMAINS.length) : (booted ? '…' : '6'), icon: this.ICONS.domains, act: 'domains', acc: 'domains' },
      { label: 'Billing', desc: 'Invoices and payment methods', meta: booted ? '' : '1 due', icon: this.ICONS.billing, act: 'billing', acc: 'billing' },
      { label: 'Reports', desc: 'Monthly maintenance summaries', meta: booted ? '' : 'June ready', icon: this.ICONS.reports, act: 'reports', acc: 'reports' },
      { label: 'Get help', desc: 'Invite a teammate or contact us', meta: '', icon: this.ICONS.support, act: 'accounts', acc: 'terminal' }
    ]).map(l => ({ ...l,
      // Per-section accent hue on the icon chip (falls back to brand blue).
      chipBg: l.acc ? 'color-mix(in srgb,var(--acc-' + l.acc + ') 13%,transparent)' : 'var(--brand-soft)',
      chipFg: l.acc ? 'var(--acc-' + l.acc + ')' : 'var(--brand-ink)',
      // Shimmer instead of '…' while hydrating.
      metaSkel: l.meta === '…', meta: l.meta === '…' ? '' : l.meta,
      go: l.act === 'dock' ? () => this.setState({ dockOpen: true }) : this.go(l.act) }));
    // One flag drives the home skeleton placeholders (attention/activity/pinned).
    const homeSkel = booted && !this._hydrated;

    const attention = (this._hydrated ? this.realAttention(isOp) : booted ? [] : isOp ? [
      { dot: 'var(--bad)', title: '2 plugin vulnerabilities across 5 sites', sub: 'gravityforms 2.9.1 (high) · woocommerce 9.8.2 (medium)', action: 'Review', act: 'security' },
      { dot: 'var(--warn)', title: '14 sites have updates pending', sub: 'Steer queue ready · last fleet update ran 6 days ago', action: 'Update', act: 'sites' },
      { dot: 'var(--warn)', title: 'harborlightyoga.com expires in 12 days', sub: 'Auto-renew is off at Hover', action: 'Renew', act: 'domains' },
      { dot: 'var(--ink-dim)', title: '3 sites are unassigned to an account', sub: 'Imported from Kinsta on Jul 9', action: 'Assign', act: 'accounts' }
    ] : [
      { dot: 'var(--warn)', title: 'Invoice #4482 is due July 22', sub: '$68.00 · Visa ··4242 on file — pay in one click', action: 'Pay', act: 'billing' },
      { dot: 'var(--ok)', title: 'Your June maintenance report is ready', sub: 'bloomandbranch.com · 9 plugin updates, 99.98% uptime', action: 'View', act: 'reports' },
      { dot: 'var(--ink-dim)', title: 'Backups healthy on all 4 sites', sub: 'Most recent quicksave 2 hours ago', action: 'Details', act: 'sites' }
    ]).map(a => ({ ...a, go: this.go(a.act) }));

    const jobsBase = isOp ? s.jobs : s.jobs.filter(j => !/\d sites/.test(j.target));
    const jobs = jobsBase.map(j => ({ ...j, pct: Math.round(j.pct),
      right: j.state === 'running' ? Math.round(j.pct) + '%' : j.right,
      running: j.state === 'running',
      fg: j.state === 'running' ? 'var(--brand-ink)' : 'var(--ink-dim)',
      dot: j.state === 'running' ? 'var(--brand)' : j.state === 'error' ? 'var(--bad)' : 'var(--ok)',
      dotAnim: j.state === 'running' ? 'ccpulse 1.4s ease infinite' : 'none',
      rowBg: s.jobSel === j.id ? 'var(--panel-2)' : 'transparent',
      // Picking a terminal session also adopts its target set, so the next
      // command runs against that session's environments.
      pick: () => this.setState(j.session ? { jobSel: j.id, termSel: j.session.split(',') } : { jobSel: j.id }) }));
    // Dock job strip (Minn mockup): running rows first. The dock is
    // bottom-anchored with height:auto, so every row grows it UPWARD; the
    // strip caps at a few rows ("+ N more" expands) so a busy fleet can't
    // swallow the console — but running jobs always stay visible.
    const dockSorted = [...jobs.filter(j => j.running), ...jobs.filter(j => !j.running)];
    const DOCK_JOB_CAP = 4;
    const dockRunning = dockSorted.filter(j => j.running);
    const dockJobs = (s.dockJobsAll || dockSorted.length <= DOCK_JOB_CAP) ? dockSorted
      : dockRunning.length >= DOCK_JOB_CAP ? dockRunning
      : dockSorted.slice(0, DOCK_JOB_CAP);

    const activity = this._activity ? this._activity : booted ? [] : isOp ? [
      { t: '2m', text: 'Quicksave 8f3c21a on bloomandbranch.com — 3 files changed' },
      { t: '18m', text: 'Austin deployed staging → production on petersonlaw.com' },
      { t: '1h', text: 'Mailgun sending verified for thewildflowerpantry.com' },
      { t: '3h', text: 'kara@petersonlaw.com accepted invite to Peterson Law (sites-only)' },
      { t: '5h', text: 'Restic backup completed on 128 sites — 0 failures' },
      { t: '8h', text: 'DNS zone imported for midwestmakersmarket.com (14 records)' }
    ] : [
      { t: '2h', text: 'Quicksave created on bloomandbranch.com — 3 files changed' },
      { t: '6h', text: 'Nightly backup completed on all 4 sites' },
      { t: '1d', text: 'gravityforms updated 2.9.1 → 2.9.4 on bloomandbranch.com' },
      { t: '2d', text: 'June maintenance report sent to 2 recipients' },
      { t: '4d', text: 'DNS record added on harborlightyoga.com (TXT · verification)' }
    ];

    const pinned = (this._hydrated ? this.realPinned() : booted ? [] : isOp ? [
      { id: 'bloom', name: 'bloomandbranch.com', sub: 'Kinsta · 6.8.1 · 12.4k visits/wk', health: 'Vulnerability', dot: 'var(--bad)' },
      { id: 'peterson', name: 'petersonlaw.com', sub: 'WP Engine · 6.8.1 · 3.1k visits/wk', health: 'Updates pending', dot: 'var(--warn)' },
      { id: 'cascade', name: 'cascadecoffeeroasters.com', sub: 'Kinsta · 6.8.0 · 8.9k visits/wk', health: 'Healthy', dot: 'var(--ok)' },
      { id: 'harbor', name: 'harborlightyoga.com', sub: 'Kinsta · 6.8.1 · 1.7k visits/wk', health: 'Vulnerability', dot: 'var(--bad)' }
    ] : [
      { id: 'bloom', name: 'bloomandbranch.com', sub: '6.8.1 · backed up 2h ago', health: 'Healthy', dot: 'var(--ok)' },
      { id: 'harbor', name: 'harborlightyoga.com', sub: '6.8.1 · backed up 6h ago', health: 'Updates pending', dot: 'var(--warn)' },
      { id: 'wildflower', name: 'thewildflowerpantry.com', sub: '6.8.1 · backed up 6h ago', health: 'Healthy', dot: 'var(--ok)' },
      { id: 'lakeside', name: 'lakesideinn.com', sub: '6.8.1 · backed up 6h ago', health: 'Healthy', dot: 'var(--ok)' }
    ]).map(p => ({ ...p, mono: p.name.slice(0, 2).toUpperCase(), go: () => this.openSite(p.id),
      ctx: (e) => this.openCtxMenu(e, [
        { label: 'Open site', act: () => this.openSite(p.id) },
        { label: 'Visit site ↗', act: () => window.open('https://' + p.name, '_blank') },
        { label: 'Copy domain', act: () => this.ctxCopy(p.name, 'domain') }
      ]) }));

    const listVals = this.computeList(s, isOp);
    const detailVals = this.computeDetail(s);
    const domainsVals = this.computeDomains(s, isOp);
    const domainVals = this.computeDomain(s);
    const accountsVals = this.computeAccounts(s, isOp);
    const accountVals = this.computeAccount(s);
    const billingVals = this.computeBilling(s);
    const securityVals = this.computeSecurity(s);
    const auditsVals = this.computeAudits(s);
    const reportsVals = this.computeReports(s, isOp);
    const archivesVals = this.computeArchives(s);
    const settingsVals = this.computeSettings(s);
    const profileVals = this.computeProfile(s);

    let consoleLines;
    // Real users (CC_BOOT injected) get the live console immediately — the
    // scripted mock only plays in the design/DC-editor preview (no CC_BOOT).
    if (window.CC_BOOT || this._hydrated) {
      consoleLines = this.realConsoleLines();
    } else {
      const scriptLen = this.CONSOLE_SCRIPT.length;
      const total = s.tick + 8;
      consoleLines = [];
      for (let i = Math.max(0, total - 30); i < total; i++) {
        const [k, text] = this.CONSOLE_SCRIPT[i % scriptLen];
        consoleLines.push({ text, fg: k === 'ok' ? 'var(--ok)' : k === 'dim' ? 'var(--ink-dim)' : 'var(--ink)' });
      }
    }
    const liveTail = consoleLines.length ? consoleLines[consoleLines.length - 1].text : 'No jobs running';

    const palResults = this.filteredPal(s.palQuery).map((r, i) => ({ ...r,
      bg: i === s.palIdx ? 'var(--panel-2)' : 'transparent', run: () => this.runPal(r) }));

    const stub = ['', '', 'home'];

    return {
      userName, userInitials: userName.slice(0, 2).toUpperCase(),
      greeting: `Good ${dayPart}, ${userName}`,
      statsLine: this._hydrated ? this.realStats() : booted ? '' : (isOp ? '128 sites · 94 domains · fleet coverage 87%' : '4 sites · 6 domains · everything backed up'),
      homeSkel, statsSkel: homeSkel,
      fleetGlance: this._hydrated ? this.realFleetGlance() : [],
      fgShow: this._hydrated && this.realFleetGlance().length > 0,
      nav: primary, navOperate: operate.map(n => n), navBottom: isOp ? [this.navItem('users', 'Users', 'accounts'), this.navItem('settings', 'Settings')] : [this.navItem('settings', 'Settings')],
      screenTitle: ({ home: 'Home', sites: 'Sites', site: 'Sites', domains: 'Domains', domain: 'Domains', accounts: 'Accounts', account: 'Accounts', billing: 'Billing', security: 'Security', audits: 'Site Audits', activity: 'Activity', reports: 'Reports', users: 'Users', archives: 'Archives', settings: 'Settings', profile: 'Profile' })[s.route] || stub[0],
      userRole: isOp ? 'Operator' : 'Customer',
      showMinnAdmin: !!(window.CC_BOOT && window.CC_BOOT.minnAdminUrl),
      minnAdminUrl: (window.CC_BOOT && window.CC_BOOT.minnAdminUrl) || '#',
      logoutUrl: (window.CC_BOOT && window.CC_BOOT.logoutUrl) || '#',
      showSwitchBack: !!(window.CC_BOOT && window.CC_BOOT.switchBackUrl),
      switchBackUrl: (window.CC_BOOT && window.CC_BOOT.switchBackUrl) || '#',
      switchBackLabel: (window.CC_BOOT && window.CC_BOOT.switchBackLabel) || 'Switch back',
      dockBtnTitle: 'Terminal · ⌃`',
      dockBtnIcon: 'M4 17l6-6-6-6M12 19h8',
      runningLabel: (c => c === 1 ? '1 job running' : c + ' jobs running')(jobs.filter(j => j.running).length),
      showOperate: isOp && variant !== 'topnav',
      showRail: variant !== 'topnav', showTopNav: variant === 'topnav',
      railWidth: variant === 'slim' ? '56px' : '208px',
      labelDisplay: variant === 'slim' ? 'none' : 'inline',
      railJustify: variant === 'slim' ? 'center' : 'flex-start',
      // Dock lives bottom-right in the Minn shell so it never overlaps the sidebar user card.
      dockSide: 'right',
      showHome: s.route === 'home', showSites: s.route === 'sites', showSite: s.route === 'site',
      showDomains: s.route === 'domains', showDomain: s.route === 'domain',
      showAccounts: s.route === 'accounts', showAccount: s.route === 'account', showBilling: s.route === 'billing',
      showSecurity: s.route === 'security', showAudits: s.route === 'audits', showReports: s.route === 'reports',
      showArchives: s.route === 'archives', showSettings: s.route === 'settings', showProfile: s.route === 'profile',
      showStub: !['home', 'sites', 'site', 'domains', 'domain', 'accounts', 'account', 'billing', 'security', 'audits', 'activity', 'reports', 'archives', 'settings', 'profile', 'users'].includes(s.route),
      stubTitle: stub[0], stubDesc: stub[1], stubIcon: this.ICONS[stub[2]],
      launcher, attention, attentionCount: attention.filter(a => !a.clear).length,
      jobs, activity, pinned, pinnedTitle: isOp ? 'Pinned sites' : 'Your sites',
      dockJobs, dockJobsShow: dockSorted.length > 0,
      dockMoreShow: s.dockJobsAll ? dockSorted.length > DOCK_JOB_CAP : dockSorted.length > dockJobs.length,
      dockMoreLabel: s.dockJobsAll ? 'Show fewer' : '+ ' + (dockSorted.length - dockJobs.length) + ' more',
      toggleDockJobs: () => this.setState(st => ({ dockJobsAll: !st.dockJobsAll })),
      ...listVals, ...detailVals, ...domainsVals, ...domainVals,
      ...accountsVals, ...accountVals, ...billingVals,
      ...securityVals, ...auditsVals, ...reportsVals, ...archivesVals, ...settingsVals, ...profileVals,
      goProfile: this.go('profile'),
      // Inline refs re-fire ref(null) + ref(el) on EVERY render, so "is this a
      // fresh mount" must compare against a slot the null call never clears
      // (_consoleKnown) — comparing against _consoleEl made every re-render
      // look like a mount and re-pinned the user to the bottom mid-scroll.
      consoleRef: (el) => {
        if (el && el !== this._consoleKnown) {
          this._consoleKnown = el;
          this._consolePinned = true;
          el.scrollTop = el.scrollHeight;
          el.addEventListener('scroll', () => {
            this._consolePinned = el.scrollHeight - el.scrollTop - el.clientHeight < 12;
          });
        }
        this._consoleEl = el;
      },
      runningCount: jobs.filter(j => j.running).length,
      hasRunning: jobs.some(j => j.running),
      dockIdle: !jobs.some(j => j.running),
      consoleLines, liveTail, consoleBg: 'var(--panel)',
      ...this.computeTermVals(s),
      ...this.computeActivityPage(s),
      ...this.computeUsersPage(s),
      dockOpen: s.dockOpen, dockClosed: !s.dockOpen,
      // The console is available to everyone. The server scopes /run/code to
      // sites the caller can access (captaincore_verify_permissions per env),
      // so a customer can only ever run against their own sites — the target
      // picker already only lists their FLEET.
      termShow: true,
      paletteOpen: s.paletteOpen, palQuery: s.palQuery, palResults,
      themeIcon: (s.theme === 'dark')
        ? 'M12 4V2m0 20v-2M4 12H2m20 0h-2M5.6 5.6 4.2 4.2m15.6 15.6-1.4-1.4m0-12.8 1.4-1.4M4.2 19.8l1.4-1.4M12 7a5 5 0 100 10 5 5 0 000-10z'
        : 'M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z',
      toggleTheme: () => { const t = this.state.theme === 'dark' ? 'light' : 'dark'; this.setState({ theme: t }); this.applyTheme(t); localStorage.setItem('captaincore-theme', t); },
      toasts: this.toastVals(),
      goHome: this.go('home'), goSites: this.go('sites'), goActivity: this.go('activity'),
      ctxOpen: !!s.ctxMenu,
      ctxX: s.ctxMenu ? s.ctxMenu.x + 'px' : '0px',
      ctxY: s.ctxMenu ? s.ctxMenu.y + 'px' : '0px',
      ctxEntries: (s.ctxMenu ? s.ctxMenu.entries : []).map(en => ({
        label: en.label, fg: en.danger ? 'var(--bad)' : 'var(--ink)',
        run: () => { this.closeCtxMenu(); en.act(); } })),
      ctxClose: () => this.closeCtxMenu(),
      ctxCloseCtx: (e) => { e.preventDefault(); this.closeCtxMenu(); },
      openDock: () => this.setState({ dockOpen: true }),
      closeDock: () => this.setState({ dockOpen: false }),
      openPalette: () => this.setState({ paletteOpen: true, palQuery: '', palIdx: 0 }),
      closePalette: () => this.setState({ paletteOpen: false }),
      ddClose: () => this.setState({ ddOpen: '', ddQ: '' }),
      ddQ: s.ddQ, onDdQ: e => this.setState({ ddQ: e.target.value }),
      stopProp: (e) => e.stopPropagation(),
      onPalInput: (e) => this.setState({ palQuery: e.target.value, palIdx: 0 })
    };
  }
}
